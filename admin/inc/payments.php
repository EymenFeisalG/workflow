<?php
    
    require '../../global.php';
    require '../../php/classes/admin.class.php';

    $admin = new admin();
?>

<script>
   
</script>

<div class="limitTable">
<table border="1">
    <tr>
        <th>Summa</th>
        <th>Datum</th>
        <th>Tid</th>
        <th>Användare</th>
    </tr>
    <?php $admin->payments(); ?>
</table>
</div>



