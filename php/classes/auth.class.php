<?php
use Mailer\Mailer;

class auth extends database
{
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
            $userinfo = unserialize($_COOKIE['user']);

            $_SESSION['user'] = $userinfo;

            $this->initRights();
        }

        if(!defined('login_req'))
            define('login_req', false); 

        if(!login_req && isset($_SESSION['user']))
            header('location: home.php');

        elseif(login_req && !isset($_SESSION['user']))
            header('location: index.php');

       if(isset($_SESSION['user']))
       {
            if(isset($_SESSION['addOrder']) || isset($_SESSION['changeOrder']) && isset($_SESSION['addOrder']))
            {
                if(isset($_SESSION['changeOrder']))
                    unset($_SESSION['changeOrder']);
                
                header("location: order.php");
            }

            if(isset($_SESSION['user']) && isset($_SESSION['changeOrder']))
            {
                header("location: changeorder.php");
            }
       }
    }

    public function changeOrderCheck($orderId)
    {
        if(!isset($_SESSION['changeOrder']))
        {
            header('location: home.php');
            return;
        }

        $orderId = $_SESSION['changeOrder'];
        $order = self::query("SELECT * FROM query WHERE id = '".$orderId."'");

        if($order->numrows() > 0)
        {
            return $order->assoc();
        }
        else
        {
            unset($_SESSION['changeOrder']);
            header('location: home.php');
        }
    }

    public function getCustomerSteps($orderId)
    {
        $steps = self::query("SELECT * FROM steps WHERE orderId = '".$orderId."'");

        while($skriv = $steps->assoc())
        {
            ?>
                <div class="stepCount <?php  if($skriv['completed'] == 1) echo ' disabled'; ?>"><span class="Remove"><?php echo $skriv['step']; ?></span><input class="stepByStep" value="<?php echo $skriv['desc']; ?>" type="text"></div>
            <?php
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

    public function login($username, $password)
    {
        $username = self::escape($username);
        $password = self::escape($password, true);

        $query = self::query("SELECT * FROM users WHERE username = '".$username."' AND password = '".$password."' OR email='".$username."' AND password='".$password."'");

        if($query->numrows() > 0)
        {   
            $query = $query->assoc();

            $user = ['username', 'email', 'rank', 'salary', 'userid'];

            $user['username'] = $query['username'];
            $user['email'] = $query['email'];
            $user['rank'] = $query['user_role'];
            $user['salary'] = $query['hourSalary'];
            $user['userid'] = $query['id'];
 

            $_SESSION['user'] = $user;

            $cookieName = "user";
            $cookieValue = serialize($user);
            $expirationTime = time() + 10 * 365 * 24 * 60 * 60; // 10 years

            setcookie($cookieName, $cookieValue, $expirationTime, '/');
            
            // latest login update
            
            self::query("UPDATE users SET lastLogin = '".time()."' WHERE username='".$username."'");

            $this->initRights();
            
            echo "success";
        }
        else
        {
            echo "fail";
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
                self::query("INSERT INTO users (username, password, email, user_role, hourSalary) VALUES ('".$data['username']."', '".$password."', '".$data['email']."', '".$role."', '".$data['salary']."')");

                // delete seckey
                self::query("DELETE FROM register_users WHERE seckey = '".$seckey."'");
                
                $this->login($data['username'], $rawPassword);
                
                header('location: home.php');
                
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