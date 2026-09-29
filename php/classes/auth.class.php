<?php
use Mailer\Mailer;

class auth extends database
{
    private const REMEMBER_COOKIE = 'workflow_remember';
    private const REMEMBER_LIFETIME = 400 * 24 * 60 * 60;
    public $mailServer;

    public function __construct($email_settings = [])
    {
        if(is_null($this->mailServer))
            $this->mailServer = $email_settings;
    }

    public function setDir()
    {   
        $start =  'all';
        // check for pending
        $query = self::query("SELECT id FROM query WHERE status = 'pending' AND creator = '".$_SESSION['user']['userid']."' LIMIT 1")->assoc();
            
        if($query > 0)
            $start = 'pending';

        $query = self::query("SELECT id FROM query WHERE status = 'completed' AND worker_name_id = '".$_SESSION['user']['userid']."' LIMIT 1")->assoc();

        if($query > 0)
            $start = 'completed';

        $query = self::query("SELECT id FROM query WHERE status = 'rework' AND worker_name_id = '".$_SESSION['user']['userid']."' LIMIT 1")->assoc();

        if($query > 0)
            $start = 'rework';

        $query = self::query("SELECT id FROM query WHERE status = 'ongoing' AND worker_name_id = '".$_SESSION['user']['userid']."' LIMIT 1")->assoc();

        if($query > 0)
            $start = 'ongoing';



        if(!isset($_GET['dir'])) return $start;

        $directions = ['all', 'ongoing', 'pending', 'rework', 'completed', 'asap'];

        $dir = (in_array($_GET['dir'], $directions)) ? $_GET['dir'] : 'all';

        return $dir;
    }

    public function initRights()
    {
        $rights = [];
        $query = self::query("SELECT privilege FROM privileges WHERE userid='".$_SESSION['user']['userid']."'");

        if($query->numrows() > 0)
        {
            while($skriv = $query->assoc())
            {
                array_push($rights, $skriv['privilege']);
            }
        }
        else
            array_push($rights, 'none');

        $_SESSION['rights'] = $rights;
    }

    
    public function hasRight($cmd, $allowAll = true)
    {
        if(!isset($_SESSION['rights']) || !is_array($_SESSION['rights']))
        {
            return false;
        }

        // check if has all
        if($allowAll)
        {
            if(in_array('all', $_SESSION['rights']))
            {
                return true;
            }
        }

        if(in_array($cmd, $_SESSION['rights']))
        {
            return true;
        }
        else
        {
            return false;
        }
        
    }


    public function Maintenance()
    {
        $query = self::query("SELECT `value` FROM workgui_settings WHERE setting = 'Maintenance' LIMIT 1")->assoc();

        if(isset($_SESSION['user']))
        {
            if($query['value'] == 1 && !($this->hasRight('maintenanceLogin', false)))
            {
                return true;
            }
        }
            elseif($query['value'] == 1)
            {
                return true;
            }
            else
                return false;         
  }

    public function userLoginCheck()
    {
        if(isset($_COOKIE['user']))
        {
            // The old serialized user cookie was not authenticated and must never be trusted.
            self::clearCookie('user');
        }

        if(!defined('login_req'))
            define('login_req', false); 

        if(!login_req && isset($_SESSION['user']))
        {
            header('location: home.php');
            exit;
        }
        elseif(login_req && !isset($_SESSION['user']))
        {
            header('location: index.php');
            exit;
        }

        if(isset($_SESSION['user']))
       {
            // Read current permissions on each request so admin changes take effect after reload.
            $this->initRights();
            if(isset($_SESSION['addOrder']))
            {
                unset($_SESSION['addOrder']);
            }

            // Äldre korrigeringssessioner öppnas via den nya modalen i home.php.
       }
    }


    public function lastActive()
    {
        $data = self::query("SELECT username FROM users WHERE username NOT like '".$_SESSION['user']['username']."' ORDER by lastLogin  DESC LIMIT 4 ");

        while($skriv = $data->assoc())
        {
            echo '<h5>'.$skriv['username'].'</h5>';
        }
    }

    private static function cookieOptions(int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private static function clearCookie(string $name): void
    {
        setcookie($name, '', self::cookieOptions(time() - 3600));
        unset($_COOKIE[$name]);
    }

    private static function userFromRow(array $row): array
    {
        return [
            'username' => $row['username'],
            'email' => $row['email'],
            'rank' => $row['user_role'],
            'userid' => $row['id'],
        ];
    }

    public function restoreRememberedLogin(): void
    {
        if (isset($_SESSION['user'])) return;

        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
        if (!is_string($cookie) || !preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/D', $cookie, $parts)) {
            if ($cookie !== '') self::clearCookie(self::REMEMBER_COOKIE);
            return;
        }

        $stmt = self::$mysql->prepare('SELECT t.user_id, t.token_hash, u.id, u.username, u.email, u.user_role FROM remember_tokens t JOIN users u ON u.id = t.user_id WHERE t.selector = ? AND t.expires_at > NOW() LIMIT 1');
        $stmt->bind_param('s', $parts[1]);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !hash_equals($row['token_hash'], hash('sha256', $parts[2]))) {
            self::clearCookie(self::REMEMBER_COOKIE);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = self::userFromRow($row);
        $expires = time() + self::REMEMBER_LIFETIME;
        $stmt = self::$mysql->prepare('UPDATE remember_tokens SET expires_at = FROM_UNIXTIME(?) WHERE selector = ?');
        $stmt->bind_param('is', $expires, $parts[1]);
        $stmt->execute();
        $stmt->close();
        setcookie(self::REMEMBER_COOKIE, $cookie, self::cookieOptions($expires));
    }

    private function rememberUser(int $userId): void
    {
        $selector = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = time() + self::REMEMBER_LIFETIME;
        $stmt = self::$mysql->prepare('INSERT INTO remember_tokens (selector, user_id, token_hash, expires_at) VALUES (?, ?, ?, FROM_UNIXTIME(?))');
        $stmt->bind_param('sisi', $selector, $userId, $hash, $expires);
        $stmt->execute();
        $stmt->close();
        setcookie(self::REMEMBER_COOKIE, $selector . ':' . $token, self::cookieOptions($expires));
    }

    public function forgetRememberedLogin(): void
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
        if (is_string($cookie) && preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/D', $cookie, $parts)) {
            $hash = hash('sha256', $parts[2]);
            $stmt = self::$mysql->prepare('DELETE FROM remember_tokens WHERE selector = ? AND token_hash = ?');
            $stmt->bind_param('ss', $parts[1], $hash);
            $stmt->execute();
            $stmt->close();
        }
        self::clearCookie(self::REMEMBER_COOKIE);
        self::clearCookie('user');
    }

    public function login($username, $password, $isAjax = true, $remember = false)
    {
        $username = self::escape($username);
        $password = self::escape($password, true);

        $query = self::query("SELECT * FROM users WHERE username = '".$username."' AND password = '".$password."' OR email='".$username."' AND password='".$password."'");

        if($query->numrows() > 0)
        {   
            $query = $query->assoc();

            session_regenerate_id(true);
            $_SESSION['user'] = self::userFromRow($query);
            $this->forgetRememberedLogin();
            if ($remember) $this->rememberUser((int)$query['id']);
            
            // latest login update
            
            self::query("UPDATE users SET lastLogin = '".time()."' WHERE username='".$username."'");

            $this->initRights();
            
            if($isAjax) echo "success";
            return true;
        }
        else
        {
            if($isAjax) echo "fail";
            return false;
        }
    }

    public function register($seckey, $password, $cpassword)
    {  

        $seckey = self::escape($seckey);
        $rawPassword = self::escape($password);
        $password = self::escape($password, true);
        $cPassword = self::escape($cpassword, true);
       // check seckey

       $query = self::query("SELECT * FROM register_users WHERE Seckey = '".$seckey."' LIMIT 1");
       $data = $query->assoc();

       if($query->numrows() > 0)
       {
            if($password == $cPassword)
            {
                $role = ($data['role'] == 'Arbetare') ? '1' : '2';
                $newUserId = 0;
                try {
                    self::query("INSERT INTO users (username, password, email, user_role, hourSalary) VALUES ('".$data['username']."', '".$password."', '".$data['email']."', '".$role."', '0')");
                    $newUserId = (int)self::$mysql->insert_id;
                    $defaultRights = ($role === '2') ? ['admin', 'all', 'maintenanceLogin'] : ['add_new_order'];
                    foreach ($defaultRights as $right) {
                        self::query("INSERT INTO privileges (userid, privilege) VALUES ($newUserId, '".self::escape($right)."')");
                    }
                    self::query("DELETE FROM register_users WHERE Seckey = '".$seckey."'");
                } catch (Throwable $error) {
                    // users/privileges can be MyISAM, where transaction rollback has no effect.
                    if ($newUserId > 0) {
                        self::$mysql->query("DELETE FROM privileges WHERE userid = $newUserId");
                        self::$mysql->query("DELETE FROM users WHERE id = $newUserId");
                    }
                    throw $error;
                }
                
                $this->login($data['username'], $rawPassword, false);
                
                header('location: home.php');
                exit;
                
            }
            else
            {
                echo '<h5>Lösenorden stämmer inte överens, dubbelkolla.</h5><br>';
            }
       }
       else
       {
            echo '<h5>Aktiveringskoden är ogiltig....</h5><br>';
       }
    } 
}
