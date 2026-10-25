<?php
declare(strict_types=1);
require_once __DIR__.'/centralenIdentity.class.php';

final class CentralenTasks
{
    public function __construct(private mysqli $db,private CentralenIdentity $identity,private main $tasks,private string $workflowUrl) {}
    private function actor(string $id): ?array { return $this->identity->linked($id); }
    private function canCreate(int $id): bool
    {
        $maintenance=$this->db->query("SELECT value FROM workgui_settings WHERE setting='Maintenance' LIMIT 1")->fetch_assoc();
        if((int)($maintenance['value']??0)===1){
            $stmt=$this->db->prepare("SELECT privilege FROM privileges WHERE userid=? AND privilege='maintenanceLogin' LIMIT 1");$stmt->bind_param('i',$id);$stmt->execute();if(!$stmt->get_result()->fetch_assoc())return false;
        }
        $stmt=$this->db->prepare("SELECT privilege FROM privileges WHERE userid=? AND privilege IN ('all','add_new_order') LIMIT 1");$stmt->bind_param('i',$id);$stmt->execute();return (bool)$stmt->get_result()->fetch_assoc();
    }
    public function authenticate(string $raw,string $signature,string $secret,?int $now=null): array
    {
        $now??=time();
        if(strlen($raw)>65536||!preg_match('/\A[a-f0-9]{64}\z/D',$signature)||!hash_equals(hash_hmac('sha256',$raw,$secret),$signature))throw new DomainException('Integrationsanropet kunde inte verifieras.');
        $request=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($request)||!in_array($request['action']??null,['context','create','operation'],true)||!is_int($request['timestamp']??null)||abs($now-$request['timestamp'])>90||!is_string($request['nonce']??null)||!preg_match('/\A[a-f0-9]{64}\z/D',$request['nonce'])||!is_array($request['payload']??null))throw new InvalidArgumentException('Integrationsanropet är ogiltigt.');
        $hash=hash('sha256',$request['nonce']);
        $this->db->query('DELETE FROM workflow_integration_nonces WHERE expires_at<UTC_TIMESTAMP()');
        try{$stmt=$this->db->prepare('INSERT INTO workflow_integration_nonces(nonce_hash,expires_at) VALUES(?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 180 SECOND))');$stmt->bind_param('s',$hash);$stmt->execute();}
        catch(mysqli_sql_exception$error){if($error->getCode()===1062)throw new DomainException('Integrationsanropet har redan använts.');throw$error;}
        return $request;
    }
    private function uuid(mixed $value): string
    {
        if(!is_string($value)||!preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D',$value))throw new InvalidArgumentException('Ogiltig identitet.');return$value;
    }
    public function handle(string $action,array $payload): array
    {
        $actorId=$this->uuid($payload['actor_id']??null);$session=$payload['actor_session_hash']??null;
        if(!is_string($session)||!preg_match('/\A[a-f0-9]{64}\z/D',$session)||!$this->identity->user($actorId,$session))throw new DomainException('Logga in i Centralen igen.');
        $actor=$this->identity->linked($actorId,$session);$actorProvisionable=$this->identity->provisionable($actorId,$session);$can=$actor&&$this->canCreate($actor['userid']);
        if($action==='context'){
            $recipientId=$this->uuid($payload['recipient_id']??null);$central=$this->identity->user($recipientId);$recipient=$this->identity->linked($recipientId);
            if(!$central)throw new DomainException('Mottagaren är inte tillgänglig.');
            $maintenance=$this->db->query("SELECT value FROM workgui_settings WHERE setting='Maintenance' LIMIT 1")->fetch_assoc();
            return ['available'=>true,'actor_linked'=>(bool)$actor,'recipient_linked'=>(bool)$recipient,'actor_provisionable'=>$actorProvisionable,'recipient_provisionable'=>$this->identity->provisionable($recipientId),'can_create'=>(bool)($can||($actorProvisionable&&(int)($maintenance['value']??0)!==1)),'recipient'=>['id'=>$recipientId,'name'=>$central['name']],'workflow_url'=>$this->workflowUrl,'link_url'=>$this->workflowUrl.'/centralen-login.php?action=start'];
        }
        $requestId=$this->uuid($payload['request_id']??null);
        if($action==='operation'){
            $maintenance=$this->db->query("SELECT value FROM workgui_settings WHERE setting='Maintenance' LIMIT 1")->fetch_assoc();
            if((int)($maintenance['value']??0)===1){
                if(!$actor)throw new DomainException('Workflow är under underhåll.');
                $stmt=$this->db->prepare("SELECT privilege FROM privileges WHERE userid=? AND privilege='maintenanceLogin' LIMIT 1");$stmt->bind_param('i',$actor['userid']);$stmt->execute();if(!$stmt->get_result()->fetch_assoc())throw new DomainException('Workflow är under underhåll.');
            }
            $stmt=$this->db->prepare('SELECT actor_id,result FROM workflow_task_operations WHERE request_id=?');$stmt->bind_param('s',$requestId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
            if(!$row)return ['found'=>false];if($row['actor_id']!==$actorId)throw new DomainException('Begäran tillhör ett annat konto.');
            if($row['result']===null)throw new RuntimeException('Task operation has no confirmed result');
            return ['found'=>true,'result'=>json_decode($row['result'],true,32,JSON_THROW_ON_ERROR)];
        }
        if($action!=='create')throw new InvalidArgumentException('Åtgärden stöds inte.');
        if(!$can&&!$actorProvisionable)throw new DomainException('Kontot saknar rätt att skapa uppgifter i Workflow.');
        $recipientId=$this->uuid($payload['recipient_id']??null);$recipient=$this->identity->linked($recipientId);
        if(!$recipient&&!$this->identity->provisionable($recipientId))throw new DomainException('Mottagaren behöver välja sin Workflow-profil.');
        $title=$payload['title']??null;$description=$payload['description']??'';$steps=$payload['steps']??[];$priority=$payload['priority']??'normal';
        if(!is_string($title)||trim($title)===''||mb_strlen($title)>255||!is_string($description)||mb_strlen($description)>20000||!is_array($steps)||!array_is_list($steps)||count($steps)>100||!in_array($priority,['normal','asap'],true))throw new InvalidArgumentException('Kontrollera uppgiftens uppgifter.');
        $clean=[];foreach($steps as$step){if(!is_string($step)||trim($step)===''||mb_strlen($step)>2000)throw new InvalidArgumentException('Kontrollera delmomenten.');$clean[]=trim($step);}
        $title=trim($title);$description=trim($description);
        // Existing task, step and notification rows must share an atomic engine.
        $rows=$this->db->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('query','steps','task_notifications','workflow_task_operations')")->fetch_all(MYSQLI_ASSOC);
        if(count($rows)!==4||array_filter($rows,static fn($row)=>strtoupper($row['ENGINE'])!=='INNODB'))throw new RuntimeException('Atomic task tables required');
        $hash=hash('sha256',json_encode([$actorId,$recipientId,$title,$description,$clean,$priority],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        $htmlDescription=nl2br(htmlspecialchars($description,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));
        if(strlen($htmlDescription)>60000)throw new InvalidArgumentException('Beskrivningen är för lång.');
        if(!$actor){$actor=$this->identity->provision($actorId,$session);$can=$actor&&$this->canCreate($actor['userid']);}
        if(!$can)throw new DomainException('Kontot saknar rätt att skapa uppgifter i Workflow.');
        if(!$recipient)$recipient=$this->identity->provision($recipientId);
        if(!$recipient)throw new DomainException('Mottagaren behöver välja sin Workflow-profil.');
        $result=$this->tasks->createOrderUnified(['order_title'=>$title,'order_desc'=>$htmlDescription,'worker'=>$recipient['userid'],'steps'=>$clean,'asap'=>$priority==='asap'?'asap':''],null,
            ['actor_local_id'=>$actor['userid'],'actor_id'=>$actorId,'recipient_id'=>$recipientId,'recipient_name'=>$recipient['username'],'workflow_url'=>$this->workflowUrl,'request_id'=>$requestId,'intent_hash'=>$hash]);
        if(!($result['success']??false)||!isset($result['integration_result']))throw new RuntimeException('Task creation did not confirm a result');
        return $result['integration_result'];
    }
}
