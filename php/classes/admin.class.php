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


    public function TimeToMinutes($value)
    {

        if(str_contains($value, ':'))
        {
            $time = explode(":", $value);
            $totalMinutes = ($time[0] * 60) + $time[1];
        }
        else
        {
            $totalMinutes = $value;
        }

        $hours = intval($totalMinutes / 60);
        $minutes = $totalMinutes - (60 * $hours);
        
        $clock = ["hours" => $hours, "minutes" => $minutes, "totalTime" => $totalMinutes];
        
        return $clock;
    }

   public function pay($pay = [], $paymentOrder = [])
   {
        $money = $paymentOrder[0];
        $time = $paymentOrder[1];
        $user = $paymentOrder[2];

        $date = date("Y-m-d H:i");

                
        self::query("INSERT INTO payments (minutes, date, money, user) 
        VALUES ('".$time."',  '".$date."', '".$money."', '".$user."')");

        foreach($pay as $paid => $key)
        {
            self::query("UPDATE query SET Paid = '1' WHERE id = '".$key."'");
        }



   }

   public function payments()
   {
       $query =  self::query("
       SELECT * 
FROM payments
GROUP BY date
ORDER BY id DESC;
       ");

        while($skriv = $query->assoc())
        {
            ?>
            
            <tr>
            <td><?php echo $skriv['money']; ?> kronor</td>
            <td><?php echo $skriv['date']; ?></td>
            <td><?php echo $skriv['minutes']; ?></td>
            <td><?php echo $skriv['user']; ?></td>
            </tr>
            
            <?php
        }
   }

   public function getTimeWorked($workerId)
   {
        $data = [];

        $query = self::query("
			SELECT query.*, 
       Worker.username AS worker, 
       Creator.username AS creator 
FROM query
LEFT JOIN users Worker ON Worker.id = query.worker_name_id
LEFT JOIN users Creator ON Creator.id = query.creator
WHERE query.status = 'completed' 
AND query.Paid = '0' 
AND query.worker_name_id = '".$workerId."' 
AND query.worktime > 0;

        ");

        while($skriv = $query->assoc())
        {

         
            $skriv['messageToDev'] = $skriv['worktime'];

            $timeFormat = $this->TimeToMinutes($skriv['worktime']);

            $finalFormat = ($timeFormat['hours'] > 1) ?  $timeFormat['hours'] . ' timmar' :  $timeFormat['hours'] .' timme';
            $finalFormat .= ' och ';
            $finalFormat .= ($timeFormat['hours'] > 1) ?  $timeFormat['minutes'] . ' minuter' :  $timeFormat['minutes'] .' minut';

            $skriv['worktime'] = $finalFormat;

            array_push($data, $skriv);
        }

       echo json_encode($data);
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

   public function addUser($username, $email, $role, $salary)
   {
        // check if fields are empty
        if($username == '' || $email == '' || $role == '' || $salary == '')
        {
            echo 'FIELDS_EMPTY';
            return false;
        }


      $username = self::escape($username);
      $email = self::escape($email);
      $role = self::escape($role);
      $salary = self::escape($salary);
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

        self::query("INSERT INTO register_users (username, email, role, Seckey, salary, timestamp) VALUES ('".$username."', '".$email."', '".$role."', '".$randomString."', '".$salary."', '".$timestamp."')");
        
        // send email

        $mailer = new Mailer($this->mailServer);

        $message = 'Du har fått en aktiveringskod för att registrera kontot ' . $username . ', klicka på länken https://workgui.com/register.php och skriv in koden: ' . $randomString;
        $mailer->sendEmail($email, 'Aktiveringskod WorkGui.com', $message);
        echo $randomString;
     }
   
    }
}
