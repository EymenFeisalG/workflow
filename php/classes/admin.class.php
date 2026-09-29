<?php
use Mailer\Mailer;

class admin extends database
{
    public $mailServer;

    public function __construct($email_settings = [])
    {
        if(is_null($this->mailServer))
            $this->mailServer = $email_settings;
    }


    
    public function getWorkers()
    {   
        $myId = $_SESSION['user']['userid'];
        $data = self::query("SELECT username, id FROM users WHERE NOT id = '".$myId."' AND NOT user_role = '2'");

        while($username = $data->assoc())
        {
            ?>
                <option value="<?php echo $username['id']; ?>"><?php echo $username['username']; ?></option>
            <?php
        }
    }

    public function RandomString($length = 16)
    {
        $stringSpace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $stringLength = strlen($stringSpace);
        $randomString = '';
        for ($i = 0; $i < $length; $i ++) {
            $randomString = $randomString . $stringSpace[rand(0, $stringLength - 1)];
        }
        return $randomString;
    }


   public function getAllUsers()
   {
        // delete old users

        self::query("DELETE FROM register_users WHERE timestamp < UNIX_TIMESTAMP(NOW() - INTERVAL 10 MINUTE)");

       $users = [];

       $query =  self::query("SELECT username, email, user_role FROM users");
       
       while($skriv = $query->assoc())
       {
            array_push($users, ['username' => $skriv['username'], 'email' => $skriv['email'], 'user_role' => $skriv['user_role'], 'active' => 'true']);
       }

       
       $query = self::query("SELECT username, email, Seckey from register_users");
       
       while($skriv = $query->assoc())
       {
            array_push($users, ['username' => $skriv['username'], 'email' => $skriv['email'], 'user_role' => $skriv['Seckey'], 'active' => 'false']);
       }

       echo json_encode($users);
   }

   public function addUser($username, $email, $role)
   {
        // check if fields are empty
        if($username == '' || $email == '' || $role == '')
        {
            echo 'FIELDS_EMPTY';
            return false;
        }


      $username = self::escape($username);
      $email = self::escape($email);
      $role = self::escape($role);
      $randomString = $this->RandomString(6);
      $timestamp = time();

     // check if username or email is taken
     $query = self::query("SELECT id FROM users WHERE username = '".$username."' OR email = '".$email."'");
     if($query->numrows() > 0)
     {
        echo 'USER_TAKEN';
        return false;
     }
     else
     {
        // check if seckey already exists
        $query = self::query("SELECT id FROM register_users WHERE username = '".$username."' OR email = '".$email."'");
       
        if($query->numrows() > 0)
        {
            echo 'USER_PENDING';
            return false;
        }

        self::query("INSERT INTO register_users (username, email, role, Seckey, salary, timestamp) VALUES ('".$username."', '".$email."', '".$role."', '".$randomString."', '0', '".$timestamp."')");
        
        // send email

        $mailer = new Mailer($this->mailServer);

        $message = 'Du har fått en aktiveringskod för att registrera kontot ' . $username . ', klicka på länken https://workgui.com/register.php och skriv in koden: ' . $randomString;
        $mailer->sendEmail($email, 'Aktiveringskod WorkGui.com', $message);
        echo $randomString;
     }
   
    }
}
