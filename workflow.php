<?php
   define('login_req', true);

   require 'global.php';

   $auth->userLoginCheck();

   $_GET['dir'] = $dir = $auth->setDir();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="ui/style/css/app.css?v=2.12" rel="stylesheet">
    <link href="ui/style/css/general.css" rel="stylesheet">
    <link href="ui/style/css/workflow.css?v=2" rel="stylesheet">
    <link href="ui/style/css/notificationsFocus.css?v=9" rel="stylesheet">
    <script src="ui/js/jquery.js"></script>
    <script>
               var workflow = true;
    </script>
    <script defer src="ui/js/general.js"></script>
    <script src="ui/js/app.js?v=2.13"></script>
    <script src="resources/tinymce/tinymce.min.js"></script>
    <script>
        var Direction = "<?php echo $_GET['dir']; ?>";
        var WorkflowCsrf = <?php echo json_encode($_SESSION['csrf_token']); ?>;

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


    <title>Workflow: Prioritering</title>
</head>
<body class="workflowPage">

<div class="workflowNotificationDock">
    <button type="button" class="notificationBell" id="notificationBell" aria-label="Notifikationer" aria-expanded="false" aria-controls="notificationPopover" title="Notifikationer">
        <svg class="dockSmallSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
        <span id="notificationCount" class="notificationCount" hidden>0</span>
    </button>
    <section class="notificationPopover" id="notificationPopover" aria-label="Notifikationer" hidden>
        <div class="notificationHeader"><strong>Notifikationer</strong><button type="button" id="notificationReadAll">Markera övriga som lästa</button></div>
        <div class="notificationList" id="notificationList"></div>
    </section>
</div>

<div class="container">

    <button class="dark-button" onclick="location.href='home.php'">&lt;&lt;</button>
        
        <div class="orders">

        </div>

   </div>



    <div class="focusOverlay" id="focusOverlay" hidden><div class="focusShell" role="dialog" aria-modal="true" aria-labelledby="focusTitle"><div class="focusHeader"><strong id="focusTitle">Fokusläge</strong><button type="button" id="focusClose">Stäng ✕</button></div><div class="focusContent" id="focusContent"></div></div></div>
        <div class="modal">
             <form class="timeForm" method="post">
                <input type="text" name="orderid" class="orderid" value="" hidden>
                <input type="text" name="action" class="action" value="" hidden>
                <h5 class="modalTitle">Meddelande / Kommentar (valfritt)</h5>
                <textarea class="content" name="comment"></textarea>
                <input type="submit" value="Spara" class="saveTime">
                <input type="button" class="closeTime close" value="Ångra">
            </form>
        </div>

</body>
</html>
