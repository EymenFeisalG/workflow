<?php
declare(strict_types=1);

/** Shared identity only; Workflow continues to own permissions and task IDs. */
final class CentralenIdentity
{
    public function __construct(private PDO $shared, private mysqli $workflow) {}

    public static function enabled(): bool { return getenv('CENTRALEN_INTEGRATION_ENABLED') === '1'; }
    public static function setting(string $key): string
    {
        $value=getenv($key);
        if(!is_string($value)||$value==='')throw new RuntimeException('Integration configuration is incomplete');
        return $value;
    }
    public static function url(string $key): string
    {
        $url=rtrim(self::setting($key),'/');$parts=parse_url($url);
        if(!$parts||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||
            !in_array($parts['scheme']??'', ['https','http'],true)||empty($parts['host'])||
            ($parts['scheme']==='http'&&!in_array(strtolower($parts['host']),['localhost','127.0.0.1','[::1]'],true)))throw new RuntimeException('Integration URL is invalid');
        return $url;
    }
    public static function connect(mysqli $workflow): self
    {
        if(!self::enabled())throw new RuntimeException('Integration is not enabled');
        $host=self::setting('SHARED_USERS_DB_HOST');$port=self::setting('SHARED_USERS_DB_PORT');$name=self::setting('SHARED_USERS_DB_NAME');
        if(!ctype_digit($port)||!preg_match('/\A[A-Za-z0-9_]+\z/D',$name))throw new RuntimeException('Shared database configuration invalid');
        $password=getenv('SHARED_USERS_DB_PASSWORD');if($password===false)throw new RuntimeException('Shared database password not configured');
        $pdo=new PDO('mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4',self::setting('SHARED_USERS_DB_USER'),$password,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        return new self($pdo,$workflow);
    }
    public function user(string $id,?string $sessionHash=null): ?array
    {
        if(!preg_match('/\A[0-9a-f-]{36}\z/D',$id))return null;
        $query=$this->shared->prepare('SELECT id,name,email,blocked,verified_at FROM core_users WHERE id=?');$query->execute([$id]);$row=$query->fetch();
        if(!$row||(int)$row['blocked']!==0||!$row['verified_at'])return null;
        if($sessionHash!==null){
            $query=$this->shared->prepare('SELECT token_hash FROM core_sessions WHERE token_hash=? AND user_id=? AND expires_at>UTC_TIMESTAMP()');$query->execute([$sessionHash,$id]);
            if(!$query->fetch())return null;
        }
        return ['id'=>$row['id'],'name'=>$row['name'],'email'=>$row['email']];
    }
    public function linked(string $id,?string $sessionHash=null): ?array
    {
        $central=$this->user($id,$sessionHash);if(!$central)return null;
        $stmt=$this->workflow->prepare('SELECT u.id,u.user_role FROM workflow_user_links l JOIN users u ON u.id=l.workflow_user_id WHERE l.centralen_user_id=?');
        $stmt->bind_param('s',$id);$stmt->execute();$local=$stmt->get_result()->fetch_assoc();
        return $local?['userid'=>(int)$local['id'],'username'=>$central['name'],'email'=>$central['email'],'rank'=>(int)$local['user_role'],'centralen_id'=>$id]:null;
    }
    public static function automaticProfiles(): bool { return getenv('WORKFLOW_AUTO_PROVISION')==='1'; }
    /** Optional legacy diagnostics only; never a gate on normal Core-user access. */
    public function legacyHint(string $id): bool
    {
        $user=$this->user($id);if(!$user)return false;
        $stmt=$this->workflow->prepare('SELECT u.id FROM users u WHERE (u.email=? OR u.username=?) AND NOT EXISTS(SELECT 1 FROM workflow_user_links l WHERE l.workflow_user_id=u.id) LIMIT 1');
        $stmt->bind_param('ss',$user['email'],$user['name']);$stmt->execute();return(bool)$stmt->get_result()->fetch_assoc();
    }
    public function provisionable(string $id,?string $sessionHash=null): bool
    {
        return self::automaticProfiles()&&$this->user($id,$sessionHash)!==null&&$this->linked($id)===null;
    }
    public function forLocal(int $id): ?array
    {
        $stmt=$this->workflow->prepare('SELECT centralen_user_id FROM workflow_user_links WHERE workflow_user_id=?');$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();return$row?$this->linked($row['centralen_user_id']):null;
    }
    public function workers(): array
    {
        $rows=$this->workflow->query('SELECT centralen_user_id FROM workflow_user_links ORDER BY workflow_user_id')->fetch_all(MYSQLI_ASSOC);$workers=[];
        foreach($rows as$row){$user=$this->linked($row['centralen_user_id']);if($user)$workers[]=['id'=>$user['userid'],'username'=>$user['username'],'email'=>$user['email'],'role'=>$user['rank'],'is_me'=>$user['userid']===(int)($_SESSION['user']['userid']??0)];}
        usort($workers,static fn($a,$b)=>strcmp($a['username'],$b['username']));return$workers;
    }
    /** Current shared identity -> local role-1 profile, right and link in one transaction. */
    public function provision(string $id,?string $sessionHash=null,bool $explicitFresh=false): ?array
    {
        if(!self::automaticProfiles())return $this->linked($id,$sessionHash);
        $user=$this->user($id,$sessionHash);if(!$user)throw new DomainException('Logga in i Centralen igen.');
        $existing=$this->linked($id,$sessionHash);if($existing)return$existing;
        $maintenance=$this->workflow->query("SELECT value FROM workgui_settings WHERE setting='Maintenance' LIMIT 1")->fetch_assoc();
        if((int)($maintenance['value']??0)===1)throw new DomainException('Workflow är under underhåll. Försök igen senare.');
        $rows=$this->workflow->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('users','privileges','workflow_user_links')")->fetch_all(MYSQLI_ASSOC);
        if(count($rows)!==3||array_filter($rows,static fn($row)=>strtoupper($row['ENGINE'])!=='INNODB'))throw new RuntimeException('Atomic profile tables required');
        $schema=$this->workflow->query('SELECT DATABASE() name')->fetch_assoc()['name'];$lock='wf:profile:'.substr(hash('sha256',$schema.'|'.$id),0,48);
        $stmt=$this->workflow->prepare('SELECT GET_LOCK(?,5) acquired');$stmt->bind_param('s',$lock);$stmt->execute();if((int)$stmt->get_result()->fetch_assoc()['acquired']!==1)throw new RuntimeException('Profile creation lock unavailable');
        try{
            $this->workflow->begin_transaction();
            $existing=$this->linked($id,$sessionHash);if($existing){$this->workflow->commit();return$existing;}
            if(!$this->user($id,$sessionHash))throw new DomainException('Logga in i Centralen igen.');
            // Presentation cache only. Random unusable legacy hash is not a copied password.
            $username=mb_substr($user['name'],0,50);$email=mb_strlen($user['email'])<=225?$user['email']:'centralen-'.$id.'@identity.invalid';$disabled=md5(random_bytes(32));
            $stmt=$this->workflow->prepare('INSERT INTO users(username,password,email,user_role,hourSalary) VALUES(?,?,?,1,0)');$stmt->bind_param('sss',$username,$disabled,$email);$stmt->execute();$local=(int)$this->workflow->insert_id;
            $stmt=$this->workflow->prepare("INSERT INTO privileges(userid,privilege) VALUES(?,'add_new_order')");$stmt->bind_param('i',$local);$stmt->execute();
            $stmt=$this->workflow->prepare('INSERT INTO workflow_user_links(centralen_user_id,workflow_user_id,created_at) VALUES(?,?,UTC_TIMESTAMP())');$stmt->bind_param('si',$id,$local);$stmt->execute();
            $this->workflow->commit();return$this->linked($id,$sessionHash);
        }catch(Throwable$error){$this->workflow->rollback();throw$error;}
        finally{$stmt=$this->workflow->prepare('SELECT RELEASE_LOCK(?)');$stmt->bind_param('s',$lock);$stmt->execute();}
    }
    public function consume(string $code,string $state,string $verifier): array
    {
        foreach([$code,$state,$verifier]as$value)if(!preg_match('/\A[a-f0-9]{64}\z/D',$value))throw new DomainException('Inloggningslänken är ogiltig.');
        $this->shared->beginTransaction();
        try{
            $query=$this->shared->prepare("SELECT * FROM core_app_login_codes WHERE code_hash=? AND target='workflow' AND expires_at>UTC_TIMESTAMP() AND consumed_at IS NULL FOR UPDATE");
            $query->execute([hash('sha256',$code)]);$grant=$query->fetch();
            if(!$grant||!hash_equals($grant['state_hash'],hash('sha256',$state))||!hash_equals($grant['pkce_challenge'],hash('sha256',$verifier)))throw new DomainException('Inloggningslänken har gått ut eller redan använts.');
            $user=$this->user($grant['user_id'],$grant['session_hash']);if(!$user)throw new DomainException('Logga in i Centralen igen.');
            $query=$this->shared->prepare('UPDATE core_app_login_codes SET consumed_at=UTC_TIMESTAMP() WHERE code_hash=?');$query->execute([$grant['code_hash']]);
            $this->shared->commit();return ['user'=>$user,'session_hash'=>$grant['session_hash']];
        }catch(Throwable $error){if($this->shared->inTransaction())$this->shared->rollBack();throw $error;}
    }
    /** Both identities must be proven; never match or merge users by email alone. */
    public function link(string $id,string $sessionHash,string $login,string $password): array
    {
        if(!$this->user($id,$sessionHash))throw new DomainException('Logga in i Centralen igen.');
        $this->workflow->begin_transaction();
        try{
            $stmt=$this->workflow->prepare('INSERT INTO workflow_link_attempts(centralen_user_id,attempts,window_started) VALUES(?,0,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE centralen_user_id=centralen_user_id');$stmt->bind_param('s',$id);$stmt->execute();
            $stmt=$this->workflow->prepare('SELECT attempts,window_started<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE) AS expired FROM workflow_link_attempts WHERE centralen_user_id=? FOR UPDATE');$stmt->bind_param('s',$id);$stmt->execute();$attempt=$stmt->get_result()->fetch_assoc();
            if(!(int)$attempt['expired']&&(int)$attempt['attempts']>=5)throw new DomainException('För många försök. Försök igen senare.');
            $stmt=$this->workflow->prepare('UPDATE workflow_link_attempts SET attempts=IF(window_started<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),1,attempts+1),window_started=IF(window_started<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),UTC_TIMESTAMP(),window_started) WHERE centralen_user_id=?');$stmt->bind_param('s',$id);$stmt->execute();$this->workflow->commit();
        }catch(Throwable$error){$this->workflow->rollback();throw$error;}
        if(strlen($login)>254||strlen($password)>512)throw new DomainException('Kontouppgifterna stämmer inte.');
        $stmt=$this->workflow->prepare('SELECT id,password FROM users WHERE username=? OR email=? LIMIT 2');$stmt->bind_param('ss',$login,$login);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if(count($rows)!==1||!hash_equals((string)$rows[0]['password'],md5($password)))throw new DomainException('Kontouppgifterna stämmer inte.');
        $local=(int)$rows[0]['id'];
        $this->workflow->begin_transaction();
        try{
            $stmt=$this->workflow->prepare('SELECT centralen_user_id,workflow_user_id FROM workflow_user_links WHERE centralen_user_id=? OR workflow_user_id=? FOR UPDATE');$stmt->bind_param('si',$id,$local);$stmt->execute();$existing=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach($existing as$row)if($row['centralen_user_id']!==$id||(int)$row['workflow_user_id']!==$local)throw new DomainException('Ett av kontona är redan kopplat till ett annat konto.');
            if(!$existing){$stmt=$this->workflow->prepare('INSERT INTO workflow_user_links(centralen_user_id,workflow_user_id,created_at) VALUES(?,?,UTC_TIMESTAMP())');$stmt->bind_param('si',$id,$local);$stmt->execute();}
            $this->workflow->commit();
        }catch(Throwable$error){$this->workflow->rollback();throw$error;}
        return $this->linked($id,$sessionHash)??throw new DomainException('Kontot kunde inte kopplas.');
    }
    public function validateSession(array $session): ?array
    {
        $id=$session['centralen_identity']['user_id']??null;$hash=$session['centralen_identity']['session_hash']??null;
        return is_string($id)&&is_string($hash)&&preg_match('/\A[a-f0-9]{64}\z/D',$hash)?$this->linked($id,$hash):null;
    }
}
