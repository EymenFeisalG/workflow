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
            $randomString = $randomString . $stringSpace[random_int(0, $stringLength - 1)];
        }
        return $randomString;
    }


   public function getAllUsers()
   {
       $users = [];
       $query = self::query("SELECT id, username, email, user_role FROM users ORDER BY username");
       
       while($skriv = $query->assoc())
       {
            $users[(int)$skriv['id']] = ['id' => (int)$skriv['id'], 'username' => $skriv['username'], 'email' => $skriv['email'], 'user_role' => (int)$skriv['user_role'], 'active' => true, 'rights' => []];
       }
       $query = self::query("SELECT userid, privilege FROM privileges");
       while ($right = $query->assoc()) {
           $id = (int)$right['userid'];
           if (isset($users[$id])) $users[$id]['rights'][] = $right['privilege'];
       }
       $users = array_values($users);
       $query = self::query("SELECT username, email, role FROM register_users WHERE timestamp >= UNIX_TIMESTAMP(NOW() - INTERVAL 10 MINUTE) ORDER BY username");
       
       while($skriv = $query->assoc())
       {
            $users[] = ['id' => null, 'username' => $skriv['username'], 'email' => $skriv['email'], 'user_role' => ($skriv['role'] === 'Admin' ? 2 : 1), 'active' => false, 'rights' => []];
       }

       return $users;
   }

   public function addUser($username, $email, $role)
   {
        $username = trim((string)$username);
        $email = trim((string)$email);
        // check if fields are empty
        if($username == '' || $email == '' || $role == '')
        {
            return 'FIELDS_EMPTY';
        }
        if (!in_array($role, ['Arbetare', 'Admin'], true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return 'INVALID_INPUT';


      $username = self::escape($username);
      $email = self::escape($email);
      $role = self::escape($role);
      $randomString = $this->RandomString(6);
      $timestamp = time();

     // check if username or email is taken
     $query = self::query("SELECT id FROM users WHERE username = '".$username."' OR email = '".$email."'");
     if($query->numrows() > 0)
     {
        return 'USER_TAKEN';
     }
     else
     {
        self::query("DELETE FROM register_users WHERE timestamp < UNIX_TIMESTAMP(NOW() - INTERVAL 10 MINUTE)");
        // check if seckey already exists
        $query = self::query("SELECT id FROM register_users WHERE username = '".$username."' OR email = '".$email."'");
       
        if($query->numrows() > 0)
        {
            return 'USER_PENDING';
        }

        self::query("INSERT INTO register_users (username, email, role, Seckey, salary, timestamp) VALUES ('".$username."', '".$email."', '".$role."', '".$randomString."', '0', '".$timestamp."')");
        
        // send email

        $mailer = new Mailer($this->mailServer);

        $message = 'Du har fått en aktiveringskod för att registrera kontot ' . $username . ', klicka på länken https://workgui.com/register.php och skriv in koden: ' . $randomString;
        $mailer->sendEmail($email, 'Aktiveringskod WorkGui.com', $message);
        return ['status' => 'OK', 'code' => $randomString];
     }
   
    }

    public function saveRights(int $userId, array $rights): bool
    {
        $allowed = ['add_new_order', 'orders_show_all', 'changeOrder', 'deleteOrder', 'admin', 'all', 'maintenanceLogin'];
        foreach ($rights as $right) if (!is_string($right)) return false;
        $rights = array_values(array_unique($rights));
        if ($userId < 1 || array_diff($rights, $allowed)) return false;
        $exists = self::$mysql->query("SELECT id FROM users WHERE id = $userId LIMIT 1");
        if (!$exists || !$exists->num_rows) return false;
        // An administrator must not accidentally remove their own access.
        if ($userId === (int)($_SESSION['user']['userid'] ?? 0) && !in_array('admin', $rights, true) && !in_array('all', $rights, true)) return false;
        $previous = [];
        $query = self::$mysql->query("SELECT privilege FROM privileges WHERE userid = $userId");
        while ($row = $query->fetch_assoc()) $previous[] = $row['privilege'];
        // Keep legacy or plugin rights that this panel does not expose.
        $rights = array_values(array_unique(array_merge($rights, array_diff($previous, $allowed))));
        try {
            self::$mysql->query("DELETE FROM privileges WHERE userid = $userId");
            $stmt = self::$mysql->prepare('INSERT INTO privileges (userid, privilege) VALUES (?, ?)');
            foreach ($rights as $right) {
                $stmt->bind_param('is', $userId, $right);
                $stmt->execute();
            }
            $stmt->close();
            return true;
        } catch (Throwable $error) {
            // Existing installations may use MyISAM, so restore rows explicitly.
            self::$mysql->query("DELETE FROM privileges WHERE userid = $userId");
            $restore = self::$mysql->prepare('INSERT INTO privileges (userid, privilege) VALUES (?, ?)');
            foreach ($previous as $right) {
                $restore->bind_param('is', $userId, $right);
                $restore->execute();
            }
            $restore->close();
            throw $error;
        }
    }
}
