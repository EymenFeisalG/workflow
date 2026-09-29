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
        return ["hours" => 0, "minutes" => 0, "totalTime" => 0];
    }

    public function restoreOrder($id)
    {
        self::query("UPDATE query SET status = 'ongoing' WHERE status = 'canceled' AND id='".$id."' LIMIT 1");
        echo $id;
    }

    public function countSalary($totalMinutes)
    {
        return 0;
    }

    public function addTimeWorker($time, $id, $orderDesc, $action)
    {
        $orderId = self::escape($id);
        $orderDesc = self::escape($orderDesc) ?? 'ingen beskrivning...';

        if($action == "deny")
        {
            self::query("UPDATE query SET `status` = 'rework', messageToDev = '".$orderDesc."' WHERE id = '".$orderId."'");
            return;
        }
        
        self::query("UPDATE query SET `status` = 'pending' WHERE id = '".$orderId."'");
        
        $query = self::query("SELECT `query`.*, users.email, users.username  FROM query INNER JOIN users ON (query.creator = users.id) WHERE `query`.id='".$orderId."'")->assoc();

        if(!empty($query['email']))
        {
            $mailer = new Mailer($this->mailServer);

            if($orderDesc !='')
                $more = 'Meddelande av '.$_SESSION['user']['username']. ': ' .$orderDesc;
            else
                $more = '';

            $message = 'Hej '.$query['username'].'! '.$_SESSION['user']['username'].' är nu färdig med uppgiften (' . $query['Name'] .').<br><br>' . $more;
            $subject = 'En ny uppgift redo att attesteras';
            $email = $query['email'];
            
            $mailer->sendEmail($email, $subject, $message);
        }

        echo json_encode(['status' => 'success']);
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

       $query = self::query("SELECT id FROM query WHERE creator = '".$myId."' AND NOT status = 'canceled'");
       $created = ($query->numrows() > 0) ? $query->numrows() : 0; 

       ?>

    <div id="parentStats" class="dockStatsGroup">
        <?php if($this->hasRight('orders_show_all')): ?>
        <a class="dockItemLink">
            <span data-url="all" class="dockTextTab badge dockFilterAll" role="button" tabindex="0">
                <span class="dockTabText">Alla</span>
                <?php if($all > 0): ?><span class="dockBadge badgeAll"><?php echo $all; ?></span><?php endif; ?>
            </span>
        </a>
        <?php endif; ?>

        <a class="dockItemLink">
            <span data-url="ongoing" class="dockTextTab badge dockFilterOngoing" role="button" tabindex="0">
                <span class="dockTabText">Mina</span>
                <?php if($ongoing > 0): ?><span class="dockBadge badgeOngoing"><?php echo $ongoing; ?></span><?php endif; ?>
            </span>
        </a>

        <a class="dockItemLink">
            <span data-url="asap" class="dockTextTab badge dockFilterAsap" role="button" tabindex="0">
                <span class="dockTabText">Akut</span>
                <?php if($asap > 0): ?><span class="dockBadge badgeAsap"><?php echo $asap; ?></span><?php endif; ?>
            </span>
        </a>

        <a class="dockItemLink">
            <span data-url="pending" class="dockTextTab badge dockFilterPending" role="button" tabindex="0">
                <span class="dockTabText">Granskas</span>
                <?php if($pending > 0): ?><span class="dockBadge badgePending"><?php echo $pending; ?></span><?php endif; ?>
            </span>
        </a>

        <a class="dockItemLink">
            <span data-url="rework" class="dockTextTab badge dockFilterRework" role="button" tabindex="0">
                <span class="dockTabText">Kompletteras</span>
                <?php if($rework > 0): ?><span class="dockBadge badgeRework"><?php echo $rework; ?></span><?php endif; ?>
            </span>
        </a>

        <a class="dockItemLink">
            <span data-url="completed" class="dockTextTab badge dockFilterCompleted" role="button" tabindex="0">
                <span class="dockTabText">Godkända</span>
                <?php if($completed > 0): ?><span class="dockBadge badgeCompleted"><?php echo $completed; ?></span><?php endif; ?>
            </span>
        </a>
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

            case 'created_by_me':
                $string = "SELECT *, username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) WHERE query.creator = '".$myId."' AND NOT query.status = 'canceled' ORDER BY query.id DESC";
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

    public function getWorkersList()
    {
        $myId = (int)($_SESSION['user']['userid'] ?? 0);
        $workers = [];
        $data = self::query("SELECT id, username, email, user_role FROM users ORDER BY username ASC");

        if ($data && $data->numrows() > 0) {
            while ($row = $data->assoc()) {
                $workers[] = [
                    'id'       => (int)$row['id'],
                    'username' => $row['username'],
                    'email'    => $row['email'],
                    'role'     => (int)$row['user_role'],
                    'is_me'    => ((int)$row['id'] === $myId)
                ];
            }
        }
        return $workers;
    }

    public function getMyCreatedOrders($limit = 15)
    {
        $myId = (int)($_SESSION['user']['userid'] ?? 0);
        $orders = [];
        $limit = max(1, min(100, (int)$limit));
        
        $sql = "SELECT query.id, query.Name, query.Hostname, query.status, query.Prio, query.date, query.worker_name_id,
                       users.username AS worker_name, users.email AS worker_email
                FROM query
                LEFT JOIN users ON (query.worker_name_id = users.id)
                WHERE query.creator = '$myId' AND NOT query.status = 'canceled'
                ORDER BY query.id DESC LIMIT $limit";

        $res = self::query($sql);
        if ($res && $res->numrows() > 0) {
            while ($row = $res->assoc()) {
                $orders[] = [
                    'id'          => (int)$row['id'],
                    'name'        => $row['Name'],
                    'hostname'    => $row['Hostname'],
                    'status'      => $row['status'],
                    'prio'        => $row['Prio'],
                    'date'        => $row['date'],
                    'worker_id'   => (int)$row['worker_name_id'],
                    'worker_name' => $row['worker_name'] ?? 'Okänd',
                    'is_self'     => ((int)$row['worker_name_id'] === $myId)
                ];
            }
        }
        return $orders;
    }

    public function getMyCreatedOrdersStats()
    {
        $myId = (int)($_SESSION['user']['userid'] ?? 0);
        $stats = [
            'total'     => 0,
            'ongoing'   => 0,
            'pending'   => 0,
            'rework'    => 0,
            'completed' => 0,
            'delegated' => 0
        ];

        $sql = "SELECT status, worker_name_id FROM query WHERE creator = '$myId' AND NOT status = 'canceled'";
        $res = self::query($sql);
        if ($res && $res->numrows() > 0) {
            while ($row = $res->assoc()) {
                $stats['total']++;
                $st = $row['status'];
                if (isset($stats[$st])) {
                    $stats[$st]++;
                }
                if ((int)$row['worker_name_id'] !== $myId) {
                    $stats['delegated']++;
                }
            }
        }
        return $stats;
    }

    public function searchCustomers($queryTerm = '')
    {
        $queryTerm = trim($queryTerm);
        $customers = [];

        if ($queryTerm !== '') {
            $searchTerm = self::escape($queryTerm);
            $sql = "SELECT id, name, url, admin_username, admin_password, org, contact 
                    FROM customers 
                    WHERE name LIKE '%{$searchTerm}%' OR org LIKE '%{$searchTerm}%' OR url LIKE '%{$searchTerm}%' 
                    ORDER BY name ASC LIMIT 15";
        } else {
            $sql = "SELECT id, name, url, admin_username, admin_password, org, contact 
                    FROM customers 
                    ORDER BY id DESC LIMIT 20";
        }

        $result = self::query($sql);

        if ($result && $result->numrows() > 0) {
            while ($row = $result->assoc()) {
                $customers[] = [
                    'id'             => (int)$row['id'],
                    'name'           => $row['name'] ?? '',
                    'url'            => $row['url'] ?? '',
                    'admin_username' => $row['admin_username'] ?? '',
                    'admin_password' => $row['admin_password'] ?? '',
                    'org'            => $row['org'] ?? '',
                    'contact'        => $row['contact'] ?? ''
                ];
            }
        }

        return $customers;
    }

    public function createOrderUnified($data, $files = null)
    {
        $creator = (int)($_SESSION['user']['userid'] ?? 0);
        $company = trim($data['company_name'] ?? '');
        $domain  = trim($data['company_domain'] ?? '');
        $org     = trim($data['org'] ?? '');
        $contact = trim($data['contact'] ?? '');
        $admin   = trim($data['company_admin_username'] ?? '');
        $pass    = trim($data['company_admin_password'] ?? '');
        $worker  = (int)($data['worker'] ?? 0);
        $asap    = ($data['asap'] ?? '') === 'asap' ? 'asap' : 'normal';
        $desc    = trim($data['order_desc'] ?? '');
        $saveCust = !empty($data['saveCustomer']) && ($data['saveCustomer'] === 'true' || $data['saveCustomer'] === '1' || $data['saveCustomer'] === true);
        $custId  = (int)($data['customer_id'] ?? 0);
        $steps   = isset($data['steps']) ? (is_array($data['steps']) ? $data['steps'] : json_decode($data['steps'], true)) : [];

        if (empty($company)) {
            return ['success' => false, 'error' => 'Kontaktpersonens namn är obligatoriskt.'];
        }

        if ($domain !== '') {
            $domain = $this->formatDomain($domain);
        }

        // 1. Kundhantering (spara / uppdatera)
        if ($saveCust) {
            $escComp = self::escape($company);
            $escDom  = self::escape($domain);
            $escOrg  = self::escape($org);
            $escCont = self::escape($contact);
            $escAdm  = self::escape($admin);
            $escPass = self::escape($pass);

            $existingId = 0;
            if ($custId > 0) {
                $check = self::query("SELECT id FROM customers WHERE id = '$custId' LIMIT 1");
                if ($check && $check->numrows() > 0) {
                    $existingId = $custId;
                }
            }
            if ($existingId === 0) {
                $check = self::query("SELECT id FROM customers WHERE name = '$escComp' LIMIT 1");
                if ($check && $check->numrows() > 0) {
                    $row = $check->assoc();
                    $existingId = (int)$row['id'];
                }
            }

            if ($existingId > 0) {
                self::query("UPDATE customers SET name = '$escComp', url = '$escDom', org = '$escOrg', contact = '$escCont', admin_username = '$escAdm', admin_password = '$escPass' WHERE id = '$existingId'");
            } else {
                self::query("INSERT INTO customers (name, url, org, contact, admin_username, admin_password) VALUES ('$escComp', '$escDom', '$escOrg', '$escCont', '$escAdm', '$escPass')");
            }
        }

        // 2. Skapa order
        $escCompany = self::escape($company);
        $escDomain  = self::escape($domain);
        $escAdmin   = self::escape($admin !== '' ? $admin : 'tomt');
        $escPass    = self::escape($pass !== '' ? $pass : 'tomt');
        $escDesc    = self::escape($desc);
        $date       = date("Y-m-d H:i");
        $relPath    = 'media/order_' . date("ymd_His") . '_' . rand(100, 999);

        // Samla steg till messageToDev för bakåtkompatibilitet
        $cleanSteps = [];
        if (!empty($steps) && is_array($steps)) {
            foreach ($steps as $st) {
                $st = trim((string)$st);
                if ($st !== '') {
                    $cleanSteps[] = $st;
                }
            }
        }
        $legacyMessage = self::escape(implode('$', $cleanSteps));

        self::query("INSERT INTO query 
            (Name, Hostname, Info, status, admin, password, Prio, worktime, worker_name_id, date, messageToDev, Path, creator)
            VALUES
            ('$escCompany', '$escDomain', '$escDesc', 'ongoing', '$escAdmin', '$escPass', '$asap', '0', '$worker', '$date', '$legacyMessage', '$relPath', '$creator')");

        $getOrderId = self::query("SELECT id FROM query WHERE creator = '$creator' ORDER BY id DESC LIMIT 1")->assoc();
        $orderId = (int)($getOrderId['id'] ?? 0);

        if ($orderId <= 0) {
            return ['success' => false, 'error' => 'Kunde inte skapa ordern i databasen.'];
        }

        // 3. Spara delmoment i tabellen steps
        if (!empty($cleanSteps)) {
            $stepCounter = 1;
            foreach ($cleanSteps as $sText) {
                $escText = self::escape($sText);
                self::query("INSERT INTO steps (step, `desc`, orderId, creator, completed) VALUES ('$stepCounter', '$escText', '$orderId', '$creator', 0)");
                $stepCounter++;
            }
        }

        // 4. Hantera bilduppladdning
        $hasImages = false;
        if ($files && isset($files['tmp_name']) && is_array($files['tmp_name'])) {
            $diskPath = dirname(__DIR__, 2) . '/' . $relPath;
            $fileCount = count($files['tmp_name']);

            for ($i = 0; $i < $fileCount; $i++) {
                if (empty($files['tmp_name'][$i]) || !is_uploaded_file($files['tmp_name'][$i])) {
                    continue;
                }

                if (!$hasImages) {
                    if (!is_dir($diskPath)) {
                        mkdir($diskPath, 0777, true);
                    }
                    $hasImages = true;
                }

                $originalName = $files['name'][$i] ?? 'image.jpg';
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'svg'])) {
                    $ext = 'jpg';
                }
                $newName    = 'img_' . ($i + 1) . '_' . rand(1000, 9999) . '.' . $ext;
                $targetFile = $diskPath . '/' . $newName;
                $imageUrl   = $relPath . '/' . $newName;

                if (move_uploaded_file($files['tmp_name'][$i], $targetFile)) {
                    $escImgUrl = self::escape($imageUrl);
                    $escPath   = self::escape($relPath);
                    self::query("INSERT INTO images (imageUrl, `Path`) VALUES ('$escImgUrl', '$escPath')");
                }
            }
        }

        if (!$hasImages) {
            self::query("UPDATE query SET Path = 'NONE' WHERE id = '$orderId'");
        }

        // 5. E-postnotifiering
        if ($worker > 0 && !empty($this->mailServer['website_mail'])) {
            $workerData = self::query("SELECT email, username FROM users WHERE id = '$worker'")->assoc();
            if (!empty($workerData['email'])) {
                $mailer = new Mailer($this->mailServer);
                $creatorName = $_SESSION['user']['username'] ?? 'En kollega';
                $subject = "Ny uppgift tilldelad: $company";
                $body = "<h2>Du har tilldelats en ny uppgift</h2>"
                      . "<p><strong>Kontaktperson:</strong> " . htmlspecialchars($company) . "</p>"
                      . ($domain ? "<p><strong>Webb:</strong> " . htmlspecialchars($domain) . "</p>" : "")
                      . "<p><strong>Tilldelad av:</strong> " . htmlspecialchars($creatorName) . "</p>"
                      . ($asap === 'asap' ? "<p style='color:red;'><strong>OBS: Akut uppgift!</strong></p>" : "")
                      . "<hr><p>" . nl2br(htmlspecialchars($desc)) . "</p>";

                $mailer->sendEmail($workerData['email'], $subject, $body);
            }
        }

        return ['success' => true, 'orderId' => $orderId, 'order_id' => $orderId];
    }
}