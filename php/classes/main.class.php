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
        // Kept as a compatibility entrypoint for older callers.
        $userId = (int)($_SESSION['user']['userid'] ?? 0);
        if ($userId > 0) $this->notifications()->setFocus($userId, (int)$orderId, $this->hasRight('orders_show_all'));
    }

    private function notifications(): TaskNotifications
    {
        return new TaskNotifications(self::$mysql);
    }

    private function notifyOrder(int $orderId, string $event, string $detail = '', array $extra = []): void
    {
        $this->notifications()->emit($orderId, (int)($_SESSION['user']['userid'] ?? 0), $event, $detail, $extra);
    }

    private function taskTransaction(callable $operation)
    {
        self::$mysql->begin_transaction();
        try {
            $result = $operation();
            self::$mysql->commit();
            return $result;
        } catch (Throwable $error) {
            self::$mysql->rollback();
            throw $error;
        }
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

    public function restoreOrder($id)
    {
        $id = (int)$id;
        $this->taskTransaction(function () use ($id) {
            self::query("UPDATE query SET status = 'ongoing' WHERE status = 'canceled' AND id='".$id."' LIMIT 1");
            if (self::$mysql->affected_rows > 0) $this->notifyOrder($id, 'restored');
        });
        echo $id;
    }

    public function submitOrderDecision($id, $orderDesc, $action)
    {
        $orderId = (int)$id;
        $notificationDetail = trim(strip_tags((string)$orderDesc));
        $orderDesc = self::escape($orderDesc) ?? 'ingen beskrivning...';

        if($action == "deny")
        {
            $this->taskTransaction(function () use ($orderId, $orderDesc, $notificationDetail) {
                self::query("UPDATE query SET `status` = 'rework', messageToDev = '".$orderDesc."' WHERE id = '".$orderId."'");
                if (self::$mysql->affected_rows > 0) $this->notifyOrder($orderId, 'rework', $notificationDetail);
            });
            return;
        }
        
        $this->taskTransaction(function () use ($orderId, $notificationDetail) {
            self::query("UPDATE query SET `status` = 'pending' WHERE id = '".$orderId."'");
            if (self::$mysql->affected_rows > 0) $this->notifyOrder($orderId, 'pending', $notificationDetail);
        });

    }


    public function acceptOrder($id)
    {
        $id = (int)$id;
        $this->taskTransaction(function () use ($id) {
            self::query("UPDATE query SET `status` = 'completed' WHERE id = '".$id."' LIMIT 1");
            if (self::$mysql->affected_rows > 0) {
                $this->notifyOrder($id, 'completed');
                $this->notifications()->clearFocusForOrder($id);
            }
        });

    }

    public function deleteOrder($id)
    {
        if(!self::hasRight('deleteOrder')) return;

        $id = (int)$id;
        $this->taskTransaction(function () use ($id) {
            self::query("UPDATE query SET `status` = 'canceled' WHERE id = '".$id."' LIMIT 1");
            if (self::$mysql->affected_rows > 0) {
                $this->notifyOrder($id, 'canceled');
                $this->notifications()->clearFocusForOrder($id);
            }
        });

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
        <a class="dockItemLink dockItemAll">
            <span data-url="all" class="dockTextTab badge dockFilterAll" role="button" tabindex="0">
                <span class="dockTabText">Alla</span>
                <?php if($all > 0): ?><span class="dockBadge badgeAll"><?php echo $all; ?></span><?php endif; ?>
            </span>
        </a>
        <?php endif; ?>

        <a class="dockItemLink dockItemOngoing">
            <span data-url="ongoing" class="dockTextTab badge dockFilterOngoing" role="button" tabindex="0">
                <span class="dockTabText">Mina</span>
                <?php if($ongoing > 0): ?><span class="dockBadge badgeOngoing"><?php echo $ongoing; ?></span><?php endif; ?>
            </span>
        </a>

        <a class="dockItemLink dockItemAsap">
            <span data-url="asap" class="dockTextTab badge dockFilterAsap" role="button" tabindex="0">
                <span class="dockTabText">Akut</span>
                <?php if($asap > 0): ?><span class="dockBadge badgeAsap"><?php echo $asap; ?></span><?php endif; ?>
            </span>
        </a>

        <a class="dockItemLink dockItemPending">
            <span data-url="pending" class="dockTextTab badge dockFilterPending" role="button" tabindex="0">
                <span class="dockTabText">Granskas</span>
                <?php if($pending > 0): ?><span class="dockBadge badgePending"><?php echo $pending; ?></span><?php endif; ?>
            </span>
        </a>

        <a class="dockItemLink dockItemRework">
            <span data-url="rework" class="dockTextTab badge dockFilterRework" role="button" tabindex="0" aria-label="Kompletteras">
                <span class="dockTabText">Kompletteras</span>
                <?php if($rework > 0): ?><span class="dockBadge badgeRework"><?php echo $rework; ?></span><?php endif; ?>
            </span>
        </a>

        <a class="dockItemLink dockItemCompleted">
            <span data-url="completed" class="dockTextTab badge dockFilterCompleted" role="button" tabindex="0">
                <span class="dockTabText">Godkända</span>
                <?php if($completed > 0): ?><span class="dockBadge badgeCompleted"><?php echo $completed; ?></span><?php endif; ?>
            </span>
        </a>
    </div>

        
       <?php
    }
 


    public function getImg($orderid)
    {
            $images = self::query("SELECT * FROM images WHERE Path = '".$orderid."'");

           while($skriv = $images->assoc())
           {
                echo '<a class="img" target="_blank" href="'.$skriv['imageUrl'].'"><img class="galleryImage" src="'.$skriv['imageUrl'].'"></a>';
           }
        
    }


    public function setStepCompletion(int $stepId, bool $completed): array
    {
        $actorId = (int)($_SESSION['user']['userid'] ?? 0);
        if ($actorId < 1 || $stepId < 1) return ['success' => false, 'error' => 'Ogiltigt steg.'];

        return $this->taskTransaction(function () use ($actorId, $stepId, $completed) {
            $stmt = self::$mysql->prepare('SELECT s.orderId, s.`desc`, s.completed+0 AS completed, q.creator, q.worker_name_id, q.status FROM steps s JOIN `query` q ON q.id = s.orderId WHERE s.id = ? LIMIT 1 FOR UPDATE');
            $stmt->bind_param('i', $stepId);
            $stmt->execute();
            $step = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$step || ((int)$step['creator'] !== $actorId && (int)$step['worker_name_id'] !== $actorId)) {
                return ['success' => false, 'error' => 'Du har inte tillgång till det här steget.'];
            }
            if (!in_array($step['status'], ['ongoing', 'rework'], true)) {
                return ['success' => false, 'error' => 'Steg kan bara ändras på aktiva uppgifter.'];
            }
            if ((bool)$step['completed'] === $completed) {
                return ['success' => true, 'completed' => $completed];
            }

            $value = $completed ? 1 : 0;
            $update = self::$mysql->prepare('UPDATE steps SET completed = ? WHERE id = ?');
            $update->bind_param('ii', $value, $stepId);
            $update->execute();
            $update->close();
            $this->notifyOrder((int)$step['orderId'], $completed ? 'step_completed' : 'step_reopened', $step['desc']);
            return ['success' => true, 'completed' => $completed];
        });
    }

    public function getSteps($orderId)
    {
        $order = $this->notifications()->order((int)$orderId);
        $myId = (int)($_SESSION['user']['userid'] ?? 0);
        if (!$order || !$this->notifications()->canAccess($order, $myId, $this->hasRight('orders_show_all'))) return;
        $canEdit = ((int)$order['creator'] === $myId || (int)$order['worker_name_id'] === $myId)
            && in_array($order['status'], ['ongoing', 'rework'], true);
        $query = self::query("SELECT steps.*, completed+0 AS completed FROM steps WHERE orderId = '".(int)$orderId."' ORDER BY step, id");

        if($query->numrows() > 0)
        
        ?>


        <?php


        while($skriv = $query->assoc())
        {
            ?>
               <div data-stepid="<?php echo (int)$skriv['id']; ?>" class="step<?php if ($skriv['completed'] == 1) echo ' is-complete'; ?>">
                   <label class="stepLabel">
                       <input <?php if ($skriv['completed'] == 1) echo 'checked '; if (!$canEdit) echo 'disabled '; ?>class="checkStep" type="checkbox" />
                       <span class="stepValue"><?php echo htmlspecialchars($skriv['step'] . ' ' . $skriv['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                   </label>
                   <span class="stepStatus" role="status" aria-live="polite"></span>
               </div>

            <?php
        }
    }

    
    public function searchOrder($string)
    {   
        $myId = $_SESSION['user']['userid'];
        $string = self::escape($string);

        if($this->hasRight('orders_show_all'))
            $search = "SELECT query.*, users.username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE (`query`.`Name` LIKE '".$string."%' OR `query`.`Hostname` LIKE '".$string."%') AND NOT status = 'canceled' ORDER BY `query`.id DESC";
        else
            $search = "SELECT query.*, users.username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE (`query`.`Name` LIKE '".$string."%' OR `query`.`Hostname` LIKE '".$string."%') AND NOT status = 'canceled' AND worker_name_id='".$myId."' ORDER BY `query`.id DESC";

        $search = str_replace('query.*, users.username,', 'query.*, users.username, (SELECT creator_user.username FROM users AS creator_user WHERE creator_user.id = query.creator) AS creator_username,', $search);
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
                    <h5 class="name"><?php echo '<span class="orderId"># '.$skriv['queryid'].'</span> '. htmlspecialchars($skriv['Name'], ENT_QUOTES, 'UTF-8'); ?></h5>
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

                        if((int)$skriv['creator'] === (int)$myId || $this->hasRight('changeOrder'))
                        {
                                ?>
                                   
                                     <button class="change">Korrigera uppgift</button>
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
            <?php $this->renderOrderContactAndLogin($skriv); ?>
          

           <div class="stat">
           <button class="saveOrder">Spara</button>
           </div>

           <?php 
                if($skriv['Path'] != 'NONE') echo '<div id="imgArea"><img data-path="'.$skriv['Path'].'" class="icon openGallery" src="ui/style/images/icons/galleryIcon.png"></div>';
          ?>
           <?php $this->renderOrderAttribution($skriv); ?>
       </div>
                                
            <?php

        
        }

    }



    public function listOrders($category, $focusOrderId = 0)
    {

        $myId = $_SESSION['user']['userid'];

        $category = self::escape($category);

        switch($category)
        {
            case 'focus':
                $focus = $this->notifications()->getFocus((int)$myId, $this->hasRight('orders_show_all'));
                if (!$focus || (int)$focus['id'] !== (int)$focusOrderId) { echo 'empty'; return false; }
                $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON query.worker_name_id = users.id WHERE query.id = '".(int)$focusOrderId."' LIMIT 1";
            break;

            case 'single':
                $single = $this->notifications()->order((int)$focusOrderId);
                if (!$single || !$this->notifications()->canAccess($single, (int)$myId, $this->hasRight('orders_show_all'))) { echo 'empty'; return false; }
                $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON query.worker_name_id = users.id WHERE query.id = '".(int)$focusOrderId."' LIMIT 1";
            break;
            case 'prio':

                if($this->hasRight('orders_show_all'))
                    $string = "SELECT query.*, users.username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE `query`.`status` = 'ongoing'  ORDER BY `query`.number_prio ASC";
                else 
                    $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON query.worker_name_id = users.id WHERE `query`.`status` = 'ongoing' AND query.worker_name_id = '".(int)$myId."' ORDER BY query.number_prio ASC";

            break;

            case 'all':

                if($this->hasRight('orders_show_all'))
                    $string = "SELECT query.*, users.username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE `query`.`status` = 'ongoing'  ORDER BY `query`.number_prio ASC";
                else 
                    $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON query.worker_name_id = users.id WHERE `query`.`status` = 'ongoing' AND query.worker_name_id = '".(int)$myId."' ORDER BY query.number_prio ASC";

            break;

            case 'asap':
                $string = "SELECT query.*, users.username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE Prio = 'asap' AND `status` = 'ongoing' AND worker_name_id = '".$myId."' OR creator = '".$myId."' AND Prio = 'asap' AND status = 'ongoing' order BY query.number_prio ASC";
            break;

            case 'ongoing':
                $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id)  WHERE worker_name_id = '".$myId."' AND  `status` = 'ongoing'  order BY query.number_prio ASC";
            break;

            case 'pending':
                
                if($this->hasRight('orders_show_all'))
                    $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'pending' order BY query.number_prio ASC";
                else
                    $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'pending' WHERE query.creator = '".$myId."' OR query.worker_name_id ='".$myId."' order BY query.number_prio ASC";
            break;

            case 'rework':
                $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'rework'  WHERE query.creator = '".$myId."' OR query.worker_name_id ='".$myId."' order BY query.number_prio ASC";
            break;

            case 'completed':
               
                if($this->hasRight('orders_show_all'))
                    $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'completed' order BY query.number_prio ASC";
                else
                    $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'completed'  WHERE query.creator = '".$myId."' OR query.worker_name_id ='".$myId."' AND status = 'completed' order BY query.number_prio ASC";
            break;

            case 'canceled':
                $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) AND `status` = 'canceled' order BY query.number_prio ASC";

            break;

            case 'created_by_me':
                $string = "SELECT query.*, users.username, query.id AS queryid FROM `query` INNER JOIN users ON(users.id = `query`.worker_name_id) WHERE query.creator = '".$myId."' AND NOT query.status = 'canceled' ORDER BY query.id DESC";
            break;

            default:
                $string = "SELECT query.*, users.username, query.id AS queryid FROM query  INNER JOIN users ON (query.worker_name_id = users.id) WHERE `status` = '".$category."' AND worker_name_id = '".$myId."' order BY query.number_prio ASC";
            break;
        }

        $string = str_replace('query.*, users.username,', 'query.*, users.username, (SELECT creator_user.username FROM users AS creator_user WHERE creator_user.id = query.creator) AS creator_username,', $string);
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

       <div data-orderId="<?php echo $skriv['queryid']; ?>" <?php echo 'style="border-right: 2px solid '.$color.';"'; ?> class="order">
           
           <div class="info">
                <div class="order_desc">
                    <input type="text" value="<?php echo $skriv['Name']; ?>" class="nameEdit">
                    <h5 class="name"><?php echo '<span class="orderId"># '.$skriv['queryid'].'</span> '. htmlspecialchars($skriv['Name'], ENT_QUOTES, 'UTF-8'); ?></h5>
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

                        if((int)$skriv['creator'] === (int)$myId || $this->hasRight('changeOrder'))
                        {
                                ?>
                                   
                                     <button class="change">Korrigera uppgift</button>
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
            <?php $this->renderOrderContactAndLogin($skriv); ?>
           <div class="stat">
           <button class="saveOrder">Spara</button>
           </div>

          <?php 
                if($skriv['Path'] != 'NONE') echo '<div id="imgArea"><img data-path="'.$skriv['Path'].'" class="icon openGallery" src="ui/style/images/icons/galleryIcon.png"></div>';
          ?>

           <?php $this->renderOrderAttribution($skriv); ?>
       </div>
                                
            <?php

        
        }

        return true;

    }

    private function renderOrderAttribution(array $order): void
    {
        $creator = htmlspecialchars($order['creator_username'] ?: 'Okänd användare', ENT_QUOTES, 'UTF-8');
        $recipient = htmlspecialchars($order['username'] ?: 'Okänd användare', ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars($order['date'] ?? '', ENT_QUOTES, 'UTF-8');
        ?>
        <p class="date orderAttribution">
            <span class="orderAttributionPerson">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9z"/><path d="M13 3v7h7M12 13v6m-3-3h6"/></svg>
                <span>Skapad av <strong><?php echo $creator; ?></strong></span>
            </span>
            <span class="orderAttributionPerson">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4zM22 2 11 13"/></svg>
                <span>Skickad till <strong><?php echo $recipient; ?></strong></span>
            </span>
            <span class="orderAttributionDate"><?php echo $date; ?></span>
        </p>
        <?php
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
        $asap = $asap === 'asap' ? 'asap' : 'normal';

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
        
        
        $this->taskTransaction(function () use ($company, $creator, $org, $contact, $domain, $desc, $worker, $admin, $password, $asap, $message, $path, $saveCustomer, $date) {
            self::query("

            INSERT INTO query
                (Name, Hostname, Info, status, admin, password, Prio, worktime, worker_name_id, date, messageToDev, Path, creator)
            VALUES
                ('".$company."', '".$domain."', '".$desc."', 'ongoing', '".$admin."', '".$password."', '".$asap."', '0', '".$worker."', '".$date."', '".$message."', '".$path."', '".$creator."')

        ");
        $createdOrderId = (int)self::$mysql->insert_id;

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
                $orderId = $createdOrderId;
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
               
                self::query("INSERT INTO steps (step, `desc`, orderId, creator) VALUES " . $stepsData);
                
            }

         }

        if ($createdOrderId > 0) $this->notifyOrder($createdOrderId, 'assigned');
        });

        echo $desc;

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

    private function renderOrderContactAndLogin($order)
    {
        $website = trim($order['Hostname'] ?? '');
        $name = trim($order['contact_name'] ?? '');
        $org = trim($order['contact_org'] ?? '');
        $contact = trim($order['contact_details'] ?? '');
        $username = trim($order['admin'] ?? '');
        $password = trim($order['password'] ?? '');
        if ($username === 'tomt') $username = '';
        if ($password === 'tomt') $password = '';
        if ($website === '' && $name === '' && $org === '' && $contact === '' && $username === '' && $password === '') return;

        $safe = static function ($value) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        };
        echo '<details class="orderAccessDetails"><summary>Kontakt och inloggning</summary>';
        if ($website !== '') {
            $websiteUrl = preg_match('~^https?://~i', $website) ? $website : 'https://' . $website;
            $websiteLink = filter_var($websiteUrl, FILTER_VALIDATE_URL)
                ? '<a href="' . $safe($websiteUrl) . '" target="_blank" rel="noopener noreferrer">' . $safe($website) . '</a>'
                : $safe($website);
            echo '<p><strong>Webbadress:</strong> ' . $websiteLink . '</p>';
        }
        if ($name !== '') echo '<p><strong>Kontaktperson:</strong> ' . $safe($name) . '</p>';
        if ($org !== '') echo '<p><strong>Företag / org:</strong> ' . $safe($org) . '</p>';
        if ($contact !== '') echo '<p><strong>Kontaktuppgifter:</strong> ' . $safe($contact) . '</p>';
        if ($username !== '') echo '<p><strong>Användarnamn:</strong> ' . $safe($username) . '</p>';
        if ($password !== '') echo '<details><summary>Visa lösenord</summary><p>' . $safe($password) . '</p></details>';
        echo '</details>';
    }

    public function createOrderUnified($data, $files = null)
    {
        $creator = (int)($_SESSION['user']['userid'] ?? 0);
        $title   = trim($data['order_title'] ?? '');
        $company = trim($data['company_name'] ?? '');
        $domain  = trim($data['company_domain'] ?? '');
        $org     = trim($data['org'] ?? '');
        $contact = trim($data['contact'] ?? '');
        $admin   = trim($data['company_admin_username'] ?? '');
        $pass    = trim($data['company_admin_password'] ?? '');
        $worker  = (int)($data['worker'] ?? 0);
        $asap    = ($data['asap'] ?? '') === 'asap' ? 'asap' : 'normal';
        $desc    = trim($data['order_desc'] ?? '');
        $custId  = (int)($data['customer_id'] ?? 0);
        $steps   = isset($data['steps']) ? (is_array($data['steps']) ? $data['steps'] : json_decode($data['steps'], true)) : [];

        if ($title === '') {
            return ['success' => false, 'error' => 'Uppgiftens namn är obligatoriskt.'];
        }

        if ($domain !== '') {
            $domain = $this->formatDomain($domain);
        }

        $movedFiles = [];
        $createdImageDirectory = null;
        try {
            self::$mysql->begin_transaction();

        // 1. Spara namngivna kontaktpersoner så att de kan väljas på fler uppgifter.
        if ($company !== '') {
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
                $check = self::query("SELECT id FROM customers WHERE name = '$escComp' AND COALESCE(org, '') = '$escOrg' LIMIT 1");
                if ($check && $check->numrows() > 0) {
                    $row = $check->assoc();
                    $existingId = (int)$row['id'];
                }
            }

            if ($existingId > 0) {
                $saved = self::$mysql->query("UPDATE customers SET name = '$escComp', url = '$escDom', org = '$escOrg', contact = '$escCont', admin_username = '$escAdm', admin_password = '$escPass' WHERE id = '$existingId'");
            } else {
                $saved = self::$mysql->query("INSERT INTO customers (name, url, org, contact, admin_username, admin_password) VALUES ('$escComp', '$escDom', '$escOrg', '$escCont', '$escAdm', '$escPass')");
            }
            if (!$saved) {
                throw new RuntimeException('Could not save customer');
            }
        }

        // 2. Skapa order
        $escTitle   = self::escape($title);
        $escCompany = self::escape($company);
        $escDomain  = self::escape($domain);
        $escOrg     = self::escape($org);
        $escContact = self::escape($contact);
        $escAdmin   = self::escape($admin);
        $escPass    = self::escape($pass);
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

        $inserted = self::$mysql->query("INSERT INTO query
            (Name, Hostname, Info, status, admin, password, Prio, worktime, worker_name_id, date, messageToDev, Path, creator, contact_name, contact_org, contact_details)
            VALUES
            ('$escTitle', '$escDomain', '$escDesc', 'ongoing', '$escAdmin', '$escPass', '$asap', '0', '$worker', '$date', '$legacyMessage', '$relPath', '$creator', '$escCompany', '$escOrg', '$escContact')");

        $orderId = $inserted ? (int)self::$mysql->insert_id : 0;

        if ($orderId <= 0) {
            throw new RuntimeException('Could not save task');
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
                        if (!mkdir($diskPath, 0777, true)) {
                            throw new RuntimeException('Could not create image directory');
                        }
                        $createdImageDirectory = $diskPath;
                    }
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
                    $hasImages = true;
                    $movedFiles[] = $targetFile;
                    $escImgUrl = self::escape($imageUrl);
                    $escPath   = self::escape($relPath);
                    self::query("INSERT INTO images (imageUrl, `Path`) VALUES ('$escImgUrl', '$escPath')");
                }
            }
        }

        if (!$hasImages) {
            self::query("UPDATE query SET Path = 'NONE' WHERE id = '$orderId'");
            if ($createdImageDirectory !== null && is_dir($createdImageDirectory)) {
                rmdir($createdImageDirectory);
                $createdImageDirectory = null;
            }
        }

        $this->notifyOrder($orderId, 'assigned');

            self::$mysql->commit();

        return ['success' => true, 'orderId' => $orderId, 'order_id' => $orderId];
        } catch (Throwable $error) {
            self::$mysql->rollback();
            foreach ($movedFiles as $file) {
                if (is_file($file)) unlink($file);
            }
            if ($createdImageDirectory !== null && is_dir($createdImageDirectory)) {
                rmdir($createdImageDirectory);
            }
            error_log('Task creation failed (' . get_class($error) . ', code ' . $error->getCode() . ')');
            return ['success' => false, 'error' => 'Kunde inte skapa uppgiften. Försök igen.'];
        }
    }
}
