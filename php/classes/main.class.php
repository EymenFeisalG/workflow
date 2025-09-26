<?php
use Mailer\Mailer;

class main extends database
{   

    public $mailServer;


    
	public function __construct($email_settings = [])
    {
        if(is_null($this->mailServer))
            $this->mailServer = $email_settings;
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
	

    public function uploadImages($files = [], $imagesExists = true)
    {

        if(!$imagesExists)
        {
            self::query("UPDATE query set Path = 'NONE' order by id DESC LIMIT 1");   
            return false; 
        }
        
        $image = $files;
        $newPath = $_SESSION['addOrder'];
        $path = '../../media/' . $newPath;

        mkdir('../../media/' . $newPath, 0777);

        $size = count($image['image']['tmp_name']);
        $images = [];



        for($i = 0; $i < $size; $i++)
        {

        $tmp_dir = $image['image']['tmp_name'][$i];
        $type = explode('/', $image['image']['type'][$i]);
        $newName = rand(500, 50000) + $i . '.' . $type[1];
        $fullUrl = $path . '/' . $newName;

        array_push($images, 'media/'.$newPath.'/'.$newName);

        move_uploaded_file($tmp_dir, $fullUrl);

        }


        $this->saveImages('media/'.$newPath, $images);

    }

    public function saveImages($path, $images = [])
    {

        foreach($images as $image => $key)
        {
           self::query("INSERT INTO images (imageUrl, `Path`) VALUES ('".$key."', '".$path."')");
        }
    }

    public function focusOrder($orderId, $dir = '')
    {
        if(!isset($_SESSION['focusOrder']))
        {
            $_SESSION['focusOrder']['orderid'] = $orderId;
            $_SESSION['focusOrder']['dir'] = $dir;
        }
        else
            unset($_SESSION['focusOrder']);
    }

    public function getSavedCustomersData($id)
    {
        $query = self::query("SELECT  * FROM customers WHERE id = '".self::escape($id)."'")->assoc();

        echo json_encode($query);
    }

    public function savePrio($list = [])
    {
     
        foreach ($list as $order)
         {

            echo $prio = $order['priority'];
            echo  $id = $order['orderId'];

            self::query("UPDATE query SET number_prio = $prio WHERE query.id = $id");
         }

    }

    public function getSavedCustomers()
    {
        $query = self::query("SELECT * FROM customers ORDER by id DESC");

            ?>
               
            <div class="checkbox">
            <select class="registered_users">
                <option value="newCustomer">Ny kund</option>
                
                <?php
                    while($skriv = $query->assoc())
                    {
                        echo '<option value="'.$skriv['id'].'">'.$skriv['name'].'</option>';
                    }
               ?>
               
            </select>
                <input type="checkbox" class="saveCustomer markSaveCustomer"><span class="markSaveCustomer">Spara Kunden</span>
            </div>

            <?php
    
    }

    public function getDeletedOrders()
    {
        $data = self::query("SELECT id FROM query WHERE `status` = 'canceled'")->numrows();
        
        echo $data;
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

    public function MyWorkingTime($userid, $paid = false)
    {   
        if($_SESSION['user']['rank'] == 2)
        {
            if($paid == false)
                $string = "SELECT worktime FROM `query` WHERE  paid = '0'";
             else
                 $string = "SELECT worktime FROM `query` WHERE paid = '1'";

        }
        else
        {
        
            if($paid == false)
                $string = "SELECT worktime FROM `query` WHERE  worker_name_id  = '".$userid."' AND paid = '0'";
            else
                $string = "SELECT worktime FROM `query` WHERE  worker_name_id  = '".$userid."' AND paid = '1'";
        }


        $query = self::query($string);
        $collect = 0;

        if($query->numrows() > 0)
        {
           while($skriv = $query->assoc())
           {
                $collect += $skriv['worktime'];
           }
        }

        return $this->TimeToMinutes($collect);
    }

    public function restoreOrder($id)
    {
        self::query("UPDATE query SET status = 'ongoing' WHERE status = 'canceled' AND id='".$id."' LIMIT 1");
        echo $id;
    }

    public function countSalary($totalMinutes)
    {
        $hourSalary = $_SESSION['user']['salary'];

        $perMinute = ($hourSalary / 60);

        return ceil($perMinute * $totalMinutes);
    }

    public function addTimeWorker($time, $id, $orderDesc, $action)
    {
        $clock = $this->TimeToMinutes($time);
        $orderId = self::escape($id);
        $orderDesc = self::escape($orderDesc) ?? 'ingen beskrivning...';

        if($action == "deny")
        {
            self::query("UPDATE query SET `status` = 'rework', messageToDev = '".$orderDesc."' WHERE id = '".$orderId."'");
            return;
        }
        
        self::query("UPDATE query SET worktime = worktime + '".$clock['totalTime']."', `status` = 'pending' WHERE id = '".$orderId."'");
        
        $query = self::query("SELECT `query`.*, users.email, users.username  FROM query INNER JOIN users ON (query.creator = users.id) WHERE `query`.id='".$orderId."'")->assoc();

        $mailer = new Mailer($this->mailServer);

        if($orderDesc !='')
            $more = 'Meddelande av '.$_SESSION['user']['username']. ': ' .$orderDesc;
        else
            $more = '';

        $message = 'Hej '.$query['username'].'! är nu färdig med uppgiften (' . $query['Name'] .') arbetstiden: ' .$clock['hours'] . ' timmar och ' . $clock['minutes'] . ' minuter <br><br>' . $more;
        $subject = 'En ny uppgift redo att attesteras';
        $email = $query['email'];
        
        $mailer = new Mailer($this->mailServer);
        $mailer->sendEmail($email, $subject, $message);
        
        $myTime = $this->MyWorkingTime($_SESSION['user']['userid']);

        $timing = ['orderTime' => $clock['hours'] . ' timmar och ' . $clock['minutes'] . ' minuter', 'totalTime' => $myTime['hours'] . 'h '. $myTime['minutes'] . 'm'];

         echo json_encode($timing);
    }


    public function acceptOrder($id)
    {

        $id = self::escape($id);
        self::query("UPDATE query SET `status` = 'completed' WHERE id = '".$id."' LIMIT 1");

    }

    public function deleteOrder($id)
    {
        if(!self::hasRight('deleteOrder')) return;

        $id = self::escape($id);
        self::query("UPDATE query SET `status` = 'canceled' WHERE id = '".$id."' LIMIT 1");

    }

    public function getOrderNav()
    {
       $myId = $_SESSION['user']['userid'];

       $query = self::query("SELECT id FROM query WHERE status = 'ongoing'");
       $all = ($query->numrows() > 0) ? $query->numrows() : 0;
       
       $query = self::query("SELECT id FROM query WHERE Prio = 'asap' AND status  = 'ongoing' AND worker_name_id='".$myId."' OR creator = '".$myId."' AND Prio = 'asap' AND status = 'ongoing'");
       $asap = ($query->numrows() > 0) ? $query->numrows() : 0;
       
       $query = self::query("SELECT id FROM query WHERE `status` = 'ongoing' AND worker_name_id = '".$myId."'");
       $ongoing = ($query->numrows() > 0) ? $query->numrows() : 0; 

       if($this->hasRight('orders_show_all'))
            $query = self::query("SELECT id FROM query WHERE `status` = 'pending'");
       else
            $query = self::query("SELECT id FROM query WHERE `status` = 'pending' AND creator = '".$myId."' OR worker_name_id='".$myId."' AND status='pending'");

       $pending = ($query->numrows() > 0) ? $query->numrows() : 0; 
    
       $query = self::query("SELECT id FROM query WHERE `status` = 'rework' AND worker_name_id = '".$myId."' OR creator = '".$myId."' AND status = 'rework'");
       $rework = ($query->numrows() > 0) ? $query->numrows() : 0; 

       if($this->hasRight('orders_show_all'))
            $query = self::query("SELECT id FROM query WHERE `status` = 'completed'");
       else 
            $query = self::query("SELECT id FROM query WHERE `status` = 'completed' AND worker_name_id = '".$myId."'");

       $completed = ($query->numrows() > 0) ? $query->numrows() : 0; 


       ?>

    <div id="parentStats">
        <a <?php if($all == 0 || !$this->hasRight('orders_show_all')) echo ' style="display: none;"'; ?>><span data-url="all" class="badge"><div class="blip orange"><span><?php echo $all; ?></span></div><label>Alla</label></span></a>
        <a <?php if($ongoing == 0) echo ' style="display: none;"'; ?>><span data-url="ongoing" class="badge"><div class="blip orange"><span><?php echo $ongoing; ?></span></div><label>MINA</label></span></a>
        <a <?php if($completed == 0) echo ' style="display: none;"'; ?>><span data-url="completed" class="badge"><div class="blip green"><span><?php echo $completed; ?></span></div><label>GODKÄNDA</label></span></a>
        <a <?php if($asap == 0) echo ' style="display: none;"'; ?>><span data-url="asap" class="badge"><div class="blip red"><span><?php echo $asap; ?></span></div><label>AKUT</label></span></a>
        <a <?php if($pending == 0) echo ' style="display: none;"'; ?>><span data-url="pending" class="badge"><div class="blip purple"><span><?php echo $pending; ?></span></div><label>GRANSKAS</label></span></a>
        <a <?php if($rework == 0) echo ' style="display: none;"'; ?>><span data-url="rework" class="badge "><div class="blip aqua"><span> <?php echo $rework; ?> </span></div><label>KOMPLETTERAS</label></span></a>
        <div class="clear"></div>
    </div>

        
       <?php
    }
 


    public function updateOrder($orderid, $company, $domain, $desc, $worker, $admin = 'tomt', $password = 'tomt', $asap = false, $messageToDev = "")
    {
        if($company == '')
            return false;

        if($admin == "") $admin = 'tomt';
        if($password == "") $password = 'tomt';

        $comapny = self::escape($company);
        $domain = self::escape($domain);
        $admin = self::escape($admin);
        $password = self::escape($password);
        $desc = self::escape($desc);
        $worker = self::escape($worker);
        $message = self::escape($messageToDev);
        $date = date("Y-m-d H:i");

        self::query("

            UPDATE query
                SET Name = '".$company."', 
                Hostname = '".$domain."',
                Info = '".$desc."', 
                admin = '".$admin."',
                password = '".$password."', 
                Prio = '".$asap."', 
                worker_name_id = '".$worker."'

                WHERE id = '".$orderid."'
        ");

    }

    public function getImg($orderid)
    {
            $images = self::query("SELECT * FROM images WHERE Path = '".$orderid."'");

           while($skriv = $images->assoc())
           {
                echo '<a class="img" target="_blank" href="'.$skriv['imageUrl'].'"><img class="galleryImage" src="'.$skriv['imageUrl'].'"></a>';
           }
        
    }


    public function checkStep($stepid, $stepMsg)
    {
        $myId = $_SESSION['user']['userid'];
        // check if is mine, orderid 
        $query = self::query("SELECT `query`.`Name`FROM steps INNER JOIN `query`ON (steps.orderId = `query`.id) WHERE `query`.worker_name_id = '".$myId."' AND steps.id = '".$stepid."'");
        
       
        if($query->numrows() == 0)
        {
            echo 'notMine';
            return;
        }
        

        self::query("UPDATE steps SET completed = '1' WHERE id = '".$stepid."'");

        $getEmail = self::query("SELECT email FROM users JOIN steps ON users.id = steps.creator WHERE steps.id = '".$stepid."' LIMIT 1")->assoc();
        $message = $_SESSION['user']['username'] . " har checkat av (" . $stepMsg . ") ifrån listan";
        $subject = 'En uppgift blev nyss klar';
        $email = $getEmail['email'];
        
        $mailer = new Mailer($this->mailServer);

        $mailer->sendEmail($email, $subject, $message);
    }

    public function getSteps($orderId)
    {
        $query = self::query("SELECT * FROM steps WHERE orderId = '".$orderId."'");

        if($query->numrows() > 0)
        
        ?>


        <?php


        while($skriv = $query->assoc())
        {
            ?>
               <div data-stepid = '<?php echo $skriv['id']; ?>' class="step">
                    
                <div class="checkmark">
                    <input <?php if($skriv['completed'] == 1) echo 'checked disabled'; ?> class="checkStep" type="checkbox" />
                </div>
                    <div <?php if($skriv['completed'] == 1) echo 'style="text-decoration: line-through;"'; ?> class="stepValue"><?php echo $skriv['step'] . ' ' . $skriv['desc']; ?></div>
                </div>

            <?php
        }
    }

    
    public function searchOrder($string)
    {   
        $myId = $_SESSION['user']['userid'];
        $string = self::escape($string);

        if($this->hasRight('orders_show_all'))
            $search = "SELECT *, username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE (`query`.`Name` LIKE '".$string."%' OR `query`.`Hostname` LIKE '".$string."%') AND NOT status = 'canceled' ORDER BY `query`.id DESC";
        else
            $search = "SELECT *, username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE (`query`.`Name` LIKE '".$string."%' OR `query`.`Hostname` LIKE '".$string."%') AND NOT status = 'canceled' AND worker_name_id='".$myId."' ORDER BY `query`.id DESC";

        $query = self::query($search);

        if($query->numrows() == 0)
        {
            echo 'empty';
            return false; 
        }

        $colors = ['ongoing' => 'orange', 'completed' => 'green', 'pending' => 'purple', 'canceled' => 'gray', 'rework' => '#1ebab4'];

        while($skriv = $query->assoc())
        {
          
            $color = $colors[$skriv['status']];
            
            
            ?>

       <div data-orderId="<?php echo $skriv['queryid']; ?>" <?php echo 'style="border-right: 2px solid '.$color.';"'; ?> class="order">
           
           <div class="info">
                <div class="order_desc">
                    <input type="text" value="<?php echo $skriv['Name']; ?>" class="nameEdit">
                    <h5 class="name"><?php echo '<span class="orderId"># '.$skriv['queryid'].'</span> <img class="icon" src="ui/style/images/icons/User-blue-icon.png">'. $skriv['Name']; ?></h5>
                    <input type="text" value="<?php echo $skriv['Hostname']; ?>" class="hostEdit">
                     <h5 class="hostname"><a  target="_blank" href="<?php echo $skriv['Hostname']; ?>"><?php echo '<img class="icon" src="ui/style/images/icons/Internet-icon.png">' .$skriv['Hostname']; ?></a></h5>
                    </div> 

      

                <?php if($skriv['status'] != 'completed' && $skriv['status'] != 'canceled')
                { ?>

               <div class="changeOrder">
                Mer
                <div class="orderHandler">
                    <?php
                          if($skriv['status'] == 'pending' && $skriv['creator'] == $myId)
                          {
                              ?>  
                                  <button class="acceptOrder">Godkänn</button>
                                  <button class="denyOrder">Komplettera</button>
                              <?php
                          }

                        if($skriv['status'] != 'pending' && $skriv['status'] != 'completed' && $skriv['worker_name_id'] == $myId)
                        {
                            ?>  
                                <button class="done">Attestera</button>
                            <?php
                        }

                        if($this->hasRight('changeOrder'))
                        {
                                ?>
                                   
                                     <button class="change">Korrigera order</button>
                                <?php
                        }
                    
                        if($this->hasRight('deleteOrder'))
                        {
                                ?>
                                    <button class="delete">Papperskorgen</button>
                                <?php
                        }

                    ?>
                    <div data-dir="<?php echo $skriv['status']; ?>" class="focusOnOrder"><div title="lås in / lås upp ordern ifrån vyn"><img src="ui/style/images/icons/focus.png"></div></div>
                </div>
               </div>

               <?php 
               if($skriv['Prio'] == 'asap' && $skriv['status'] != 'completed') echo '<div class="asap">AKUT</div>'; 

               }
               else
               {
                  if($skriv['status'] == 'completed') echo '<div class="completed"></div>'; 
               }
               
               ?>
              
            </div>   



        
            <div data-steps = '<?php echo $skriv['queryid']; ?>' class="Steps">
            <?php 
                        if($skriv['status'] == 'rework')
                        {
                            echo '<div class="reworkMsg">'.$skriv['messageToDev'].'</div>';
                        }
                    ?>
            </div>

            <?php
                if($skriv['Info'] != "")
                {
                    ?>
                    <div class="desc <?php if(strlen($skriv['Info']) > 100) echo ' masked'; ?>">
                        <?php echo $skriv['Info']; ?>
                    </div>
                    <?php
                    
                     if(strlen($skriv['Info']) > 100)
                        echo '<div class="showText"><button data-orderid="'.$skriv['queryid'].'" class="readMore"></button></div>';

                }
            ?>
          

           <div class="stat">
           <button class="saveOrder">Spara</button>
           </div>

           <?php 
                if($skriv['Path'] != 'NONE') echo '<div id="imgArea"><img data-path="'.$skriv['Path'].'" class="icon openGallery" src="ui/style/images/icons/galleryIcon.png"></div>';
          ?>
           <p class="date"><?php echo '<img class="icon" src="ui/style/images/icons/worker.png"> '.$skriv['username'] . ' '. $skriv['date']; ?></p>             
       </div>
                                
            <?php

        
        }

    }



    public function listOrders($category)
    {

        $myId = $_SESSION['user']['userid'];

        $category = self::escape($category);

        switch($category)
        {
            case 'prio':

                if($this->hasRight('orders_show_all'))
                    $string = "SELECT *, username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE `query`.`status` = 'ongoing'  ORDER BY `query`.number_prio ASC";
                else 
                    $string = 0;

            break;

            case 'all':

                if($this->hasRight('orders_show_all'))
                    $string = "SELECT *, username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE `query`.`status` = 'ongoing'  ORDER BY `query`.number_prio ASC";
                else 
                    $string = 0;

            break;

            case 'asap':
                $string = "SELECT  *, username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE Prio = 'asap' AND `status` = 'ongoing' AND worker_name_id = '".$myId."' OR creator = '".$myId."' AND Prio = 'asap' AND status = 'ongoing' order BY query.number_prio ASC";
            break;

            case 'ongoing':
                $string = "SELECT *, username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id)  WHERE worker_name_id = '".$myId."' AND  `status` = 'ongoing'  order BY query.number_prio ASC";
            break;

            case 'pending':
                
                if($this->hasRight('orders_show_all'))
                    $string = "SELECT *, username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'pending' order BY query.number_prio ASC";
                else
                    $string = "SELECT *, username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'pending' WHERE query.creator = '".$myId."' OR query.worker_name_id ='".$myId."' order BY query.number_prio ASC";
            break;

            case 'rework':
                $string = "SELECT *, username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'rework'  WHERE query.creator = '".$myId."' OR query.worker_name_id ='".$myId."' order BY query.number_prio ASC";
            break;

            case 'completed':
               
                if($this->hasRight('orders_show_all'))
                    $string = "SELECT *, username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'completed' order BY query.number_prio ASC";
                else
                    $string = "SELECT *, username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'completed'  WHERE query.creator = '".$myId."' OR query.worker_name_id ='".$myId."' AND status = 'completed' order BY query.number_prio ASC";
            break;

            case 'canceled':
                $string = "SELECT *, username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'canceled' order BY query.number_prio ASC";

            break;

            default:
                $string = "SELECT  *, username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE `status` = '".$category."' AND worker_name_id = '".$myId."' order BY query.number_prio ASC";
            break;
        }

        $query = self::query($string);

        $colors = ['ongoing' => 'orange', 'completed' => 'green', 'pending' => 'purple', 'canceled' => 'gray', 'rework' => '#1ebab4'];
        
        $odd = false;

        if($query->numrows() == 0)
        {
            echo 'empty';
            return false;
        }

    
        
        while($skriv = $query->assoc())
        {
          
            $color = $colors[$skriv['status']];
            
            
            ?>

       <div data-orderId="<?php echo $skriv['queryid']; ?>" <?php echo 'style="border-right: 2px solid '.$color.';"'; ?> class="order<?php if(isset($_SESSION['focusOrder'])){if($_SESSION['focusOrder']['orderid'] == $skriv['queryid']) echo ' orderFocus';} ?>">
           
           <div class="info">
                <div class="order_desc">
                    <input type="text" value="<?php echo $skriv['Name']; ?>" class="nameEdit">
                    <h5 class="name"><?php echo '<span class="orderId"># '.$skriv['queryid'].'</span> <img class="icon" src="ui/style/images/icons/User-blue-icon.png">'. $skriv['Name']; ?></h5>
                     <h5 class="hostname"><a  target="_blank" href="<?php echo 'https://www.'.$skriv['Hostname']; ?>"><?php echo '<img class="icon" src="ui/style/images/icons/Internet-icon.png">' .$skriv['Hostname']; ?></a></h5>
                    </div> 



                    <?php
                        if($skriv['status'] == 'canceled')
                        {
                            ?>
                                <button class="Restore">Återställ</button>
                            <?php
                        }
                    ?>

                <?php if($skriv['status'] != 'completed' && $skriv['status'] != 'canceled')
                { ?>

               <div class="changeOrder">
                Mer
                <div class="orderHandler">
                    <?php
                          if($skriv['status'] == 'pending' && ($skriv['creator'] == $myId || $this->hasRight('orders_show_all')))
                          {
                              ?>  
                                  <button class="acceptOrder">Godkänn</button>
                                  <button class="denyOrder">Komplettera</button>
                              <?php
                          }

                        if($skriv['status'] != 'pending' && $skriv['status'] != 'completed' && $skriv['worker_name_id'] == $myId)
                        {
                            ?>  
                                <button class="done">Attestera</button>
                            <?php
                        }

                        if($this->hasRight('changeOrder'))
                        {
                                ?>
                                   
                                     <button class="change">Korrigera order</button>
                                <?php
                        }
                    
                        if($this->hasRight('deleteOrder'))
                        {
                                ?>
                                    <button class="delete">Papperskorgen</button>
                                <?php
                        }

                    ?>
                    <div data-dir="<?php echo $category; ?>" class="focusOnOrder"><div title="lås in / lås upp ordern ifrån vyn"><img src="ui/style/images/icons/focus.png"></div></div>
                </div>
               </div>

               <?php 
               if($skriv['Prio'] == 'asap' && $skriv['status'] != 'completed') echo '<div class="asap">AKUT</div>'; 

               }
               else
               {
                  if($skriv['status'] == 'completed') echo '<div class="completed"></div>'; 
               }
               
               ?>
              
            </div>   



        
            <div data-steps = '<?php echo $skriv['queryid']; ?>' class="Steps">
            <?php 
                        if($skriv['status'] == 'rework')
                        {
                            echo '<div class="reworkMsg">'.$skriv['messageToDev'].'</div>';
                        }
                    ?>
            </div>

            <?php
                if($skriv['Info'] != "")
                {
                    ?>
                    <div class="desc <?php if(strlen($skriv['Info']) > 100) echo ' masked'; ?>">
                        <?php echo $skriv['Info']; ?>
                    </div>
                    <?php
                    
                     if(strlen($skriv['Info']) > 100)
                        echo '<div class="showText"><button data-orderid="'.$skriv['queryid'].'" class="readMore"></button></div>';

                }
            ?>
           <div class="stat">
           <button class="saveOrder">Spara</button>
           </div>

          <?php 
                if($skriv['Path'] != 'NONE') echo '<div id="imgArea"><img data-path="'.$skriv['Path'].'" class="icon openGallery" src="ui/style/images/icons/galleryIcon.png"></div>';
          ?>

           <p class="date"><?php echo '<img class="icon" src="ui/style/images/icons/worker.png"> '.$skriv['username'] . ' '. $skriv['date']; ?></p>             
       </div>
                                
            <?php

        
        }

        return true;

    }

    public function formatDomain($string)
    {

        $patterns = array("https://", "http://", "www.");
        $replacements = array("", "", "");

        $result = str_replace($patterns, $replacements, $string);

        return $result;
    }

    public function sendOrder($company, $creator, $org, $contact, $domain, $desc, $worker, $admin = 'tomt', $password = 'tomt', $asap = false, $messageToDev = "", $path = "", $saveCustomer = false)
    {
        if($company == '')
            return false;

        if($admin == "") $admin = 'tomt';
        if($password == "") $password = 'tomt';

        $domain = $this->formatDomain($domain);

        $company = self::escape($company);
        $domain = self::escape($domain);
        $admin = self::escape($admin);
        $password = self::escape($password);
        $plain = $desc;
        $desc = self::escape($desc);
        $worker = self::escape($worker);
        $message = self::escape($messageToDev);
        $org = self::escape($org) ?? 'tomt';
        $contact = self::escape($contact) ?? 'tomt';
        $date = date("Y-m-d H:i");
        
        
        self::query("

            INSERT INTO query
                (Name, Hostname, Info, status, admin, password, Prio, worktime, worker_name_id, date, messageToDev, Path, creator)
            VALUES
                ('".$company."', '".$domain."', '".$desc."', 'ongoing', '".$admin."', '".$password."', '".$asap."', '0', '".$worker."', '".$date."', '".$message."', '".$path."', '".$creator."')

        ");

        if($saveCustomer == "true")
        {
            // check if customer is already saved
            $checkCustomer = self::query("SELECT id from customers WHERE name = '".$company."'")->numrows();
            if($checkCustomer < 1)
                self::query("INSERT INTO customers (`name`, `url`, `admin_password`, `admin_username`, `org`, `contact`) VALUES ('".$company."', '".$domain."', '".$admin."', '".$password."', '".$org."', '".$contact."')");
        }

        // add steps if any

        if($message != "")
        {
          
            $steps = array_values(array_filter(explode('$', $message)));
            $stepsCount = (count($steps) != "") ?  count($steps) : 0;
            $stepsData = "";

            if($stepsCount > 0)
            {
                $getOrderId = self::query("SELECT id FROM query ORDER by id DESC")->assoc();
                $orderId = $getOrderId['id'];
                $counter = 1;

                for($i = 0; $i < $stepsCount; $i++)
                {
                    
                    if($stepsCount == 1)
                    {
                        $stepsData = "('".$counter."', '".$steps[$i]."', '".$orderId."', '".$_SESSION['user']['userid']."')";
                        break;
                    }

                    
                    if($stepsCount != $counter)
                    {
                        $stepsData .= "('".$counter."', '".$steps[$i]."', '".$orderId."', '".$_SESSION['user']['userid']."'),";

                    }
                    else
                    {
                        $stepsData .= "('".$counter."', '".$steps[$i]."', '".$orderId."', '".$_SESSION['user']['userid']."')";

                    }

                    $counter++;

                }
               
                echo $domain;
                self::query("INSERT INTO steps (step, `desc`, orderId, creator) VALUES " . $stepsData);
                
            }

         }

        $email = self::query("SELECT email, username FROM users WHERE id = '".$worker."'")->assoc();
        
        $subjectOne = "Ett nytt uppdrag av " . $_SESSION['user']['username'];
        $messageOne = "<h1>Du har fått en ny uppgift</h1><br> ". $company . "<br>" . $domain ."<br>". "admin login: ". $admin . "<br> admin lösen: " . $password . "<br>" .$plain;
        
        $subjectTwo = "Din uppgift skickades till ". $email['username'];
        $messageTwo = "<h1>Du har precis skickat en uppgift till ".$email['username']."</h1><br> ". $company . "<br>" . $domain ."<br>". "admin login: ". $admin . "<br>admin lösen: " . $password . "<br>" .$plain;
       
        $email = $email['email'];
        
        $mailer = new Mailer($this->mailServer);

        $mailer->sendEmail($email, $subjectOne, $messageOne);
        $mailer->sendEmail($_SESSION['user']['email'], $subjectTwo, $messageTwo);

        echo $desc;

    }

    public function getWorkers($orderWorker = '')
    {   
        $myId = $_SESSION['user']['userid'];
        $data = self::query("SELECT username, id FROM users WHERE NOT id = '".$myId."'");

        while($username = $data->assoc())
        {
            ?>
                <option <?php if($orderWorker == $username['id']) echo 'selected'; ?> value="<?php echo $username['id']; ?>"><?php echo 'Skicka uppgiften till: ' .$username['username']; ?></option>
            <?php
        }
    }

    public function getMysteps($orderid)
    {
        $query = self::query("SELECT * FROM");
    }
}