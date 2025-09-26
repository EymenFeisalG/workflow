<?php
    
    require '../../global.php';
    require '../../php/classes/admin.class.php';

    $admin = new admin();
?>

<script>
    $(document).ready(function()
    {


        function generateTable(worker, creator, orderName, date, TimeWorked, orderid, url, orderTime)
        {
            return `
                <tr>
                <td><input class="checkTime" data-ordertime="${orderTime}" data-orderid="${orderid}" type="checkbox"><span>${worker}</span></td>
                <td>${orderName}</td>
                <td>${TimeWorked}</td>
                <td>${orderid}</td>
                <td>${creator}</td>
                <td>${date}</td>
                </tr>
            `;
        }

        function minutesToTotal(time)
            {                
                var hours = Math.floor(time / 60);
                var minutes = time - (60 * hours);

                return hours + ' h ' + minutes + ' m';
            }

            $(document).on('click', '.pay', function()
            {
                event.stopPropagation();
                var orders = [];
                var paymentOrder = [$('.countMoney').html(), $('.countTime').html(), 'Eymen'];

                console.log(paymentOrder);

                $('.checkTime').each(function()
               {    
                    
                    if($(this).is(':checked'))
                    {
                      orders.push($(this).data('orderid'));
                      $(this).parent().parent().fadeOut();
                    }
               });


               $.post('functions/pay.php', {'orders': orders, 'paymentOrder': paymentOrder}, function(success)
               {
                  message("Färdigt! Ordrar markerade som betalt.");

                  location.href = "../home.php";
                return false;
                  
               });

               return false;
            
            });

            function collectTime()
            {
               var selectedTime = 0;
               
               $('.checkTime').each(function()
               {    
                    
                    if($(this).is(':checked'))
                      selectedTime +=  $(this).data('ordertime');

               });

               $('.countTime').html(minutesToTotal(selectedTime));

               var countMoney = selectedTime * (150 / 60);

               $('.countMoney').html(countMoney);


            }

            $(document).on('click', '.checkTime', function()
            {  
                collectTime();
            });


            $(document).on('click', '.SelectAll', function()
            {  
               
                // check if any has been selected, if then unselect vice verse

                var anySelected = false;

                $('.checkTime').each(function()
                {
                    if($(this).is(':checked'))
                        anySelected = true;
                });

                if(anySelected)
                {
                    $('.checkTime').prop('checked', false);
                }
                else
                {
                    $('.checkTime').prop('checked', true);
                }

                collectTime();
            });

            function loadTime()
            {
                $.get('functions/getTimeWorked.php', {'workerId': $('.WorkerId').val()}, function(data){

                data = JSON.parse(data);
                for(var key in data)
                {
                    $('.output').append(generateTable(data[key].worker, data[key].creator, data[key].Name, data[key].date, data[key].worktime, data[key].id, data[key].Hostname, data[key].messageToDev));
                }


                    console.log($('.WorkerId').val());
                });
            }

           loadTime();


           $(document).on('change', '.WorkerId', function(){ 
                $('.output').html('');
                $('.countTime').html('0h 0m');
                loadTime();
           });
    });
</script>

<div class="limitTable">
<table>
<thead>
<tr>
<th>&nbsp; Arbetare <select class="WorkerId">
    <?php $admin->getWorkers(); ?>
    </select>     <button class="SelectAll">Välj alla</button></th>
<th>Uppgift</th>
<th>Arbetad tid</th>
<th># ID</th>
<th>Skapare</th>
<th>Datum</th>
</tr>
</thead>
<tbody class="output">
</tbody>
</table>

</div>

<div class="payMent">
    <span class="total">Totalt <span class="countTime"> 0h 0m</span></span>
    <br>
    <br>
    <span class="totalMoney"><span class="countMoney"></span> Kronor</span>
    <button class="pay">Markera som betalt</button>
</div>



