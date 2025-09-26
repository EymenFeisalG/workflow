<?php
   define('login_req', true);

   require 'global.php';

   $auth->userLoginCheck();

   if(!isset($_SESSION['focusOrder']))
        $_GET['dir'] = $dir = $auth->setDir();
    else
        $_GET['dir'] = $dir = $_SESSION['focusOrder']['dir'];

   // Get working time
   $timeWorked_attest = $main->MyWorkingTime($_SESSION['user']['userid']);
   $salary = $main->countSalary($timeWorked_attest['totalTime']); 
   $timeWorked_paid = $main->MyWorkingTime($_SESSION['user']['userid'], true); 

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="ui/style/css/app.css" rel="stylesheet">
    <link href="ui/style/css/general.css" rel="stylesheet">
    <link href="ui/style/css/workflow.css" rel="stylesheet">
    <script src="ui/js/jquery.js"></script>
    <script>
               var workflow = true;
    </script>
    <script defer src="ui/js/general.js"></script>
    <script src="ui/js/app.js"></script>
    <script src="resources/tinymce/tinymce.min.js"></script>
    <script>
        var Direction = "<?php echo $_GET['dir']; ?>";
        var orderInFocus = "<?php if(isset($_SESSION['focusOrder'])) echo 'true'; else echo 'false'; ?>";

    </script>



<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const ordersContainer = document.querySelector(".orders");

        // Initialize SortableJS
        new Sortable(ordersContainer, {
            animation: 150,
            ghostClass: "dragging",
            onEnd: function () {
                updatePriorities();
            }
        });

        // Function to update order priorities and log them
        function updatePriorities() {
            let orders = Array.from(document.querySelectorAll('.order'));
            let orderList = orders.map((order, index) => ({
                orderId: order.dataset.orderid, // Extract order ID
                priority: index + 1 // Assign new priority based on position
            }));

               $.post('php/functions/sendPrio.php', {'list': orderList },function(e){

                console.log(e);

               });
        }
    });
</script>


    <title>WorkGUI</title>
    <link rel="icon" type="image/x-icon" href="ui/style/images/icons/W.ico">
</head>
<body>

<div class="container">

    <button class="dark-button" onclick="location.href='/home'"><</button>
        
        <div class="orders">

        </div>

   </div>



    <div hidden id="focusOrder"><?php if(isset($_SESSION['focusOrder'])) echo $_SESSION['focusOrder']['orderid']; ?></div>
        <div class="modalFocus" <?php if(isset($_SESSION['focusOrder'])) echo 'style="display: block;'; ?>></div>
        <div class="modal">
             <form class="timeForm" method="post">
                <h5>Arbetad tid</h5>
                <br>
                <div class="fields">
                <input type="number" min="0"  autocomplete="off" placeholder="h" class="hours" name="hours"> timmar
                <input type="number" min="0"  autocomplete="off" step="30" placeholder="m" class="minutes" name="minutes"> minuter
                <div class="clear"></div>
                </div>
                <input type="text" name="orderid" class="orderid" value="" hidden>
                <input type="text" name="action" class="action" value="" hidden>
                <h5>Skriv ett meddelande till Namo</h5>
                <textarea class="content" name="comment"></textarea>
                <input type="submit" value="Spara" class="saveTime">
                <input type="submit" class="closeTime close" value="Ångra">
            </form>
        </div>

</body>
</html>