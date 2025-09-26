$(document).ready(function(){

    
tinymce.init({

    selector: '.content',  // change this value according to your HTML

    language: 'sv_SE',

    promotion: false,

    height: 400,
    width: 800

  });


    let stepCheck = 0;
    let orderId = $('#focusOrder').text();
    let justOk = {'mode': 'none', 'justOk': true};
    let orderFocus = (orderInFocus == "true") ? true : false;
    let dir = Direction;


    console.log(workflow);


    $(document).on('click', '.yes', function()
    {
        switch(justOk.mode)
        {
            case 'steps':

                if(justOk.justOk)
                {
                    $('.generalModal').remove();
                    $('[data-stepid="'+stepCheck+'"] .checkStep').prop('checked', false);
                    justOk = 'none';
                    return false;
                }

                let stepMsg = $('[data-stepid="'+stepCheck+'"] .stepValue').html();

                $.post('php/functions/checkStep.php', {stepid: stepCheck, value: stepMsg}, function(success){

                    if(success == 'notMine') 
                    {   
                        $('.generalModal').hide();
                        pageAlert('Misslyckades', 'du kan inte makera steg som inte är dina', false);
                        justOk.mode = 'general';
                        return false;
                    }

                    $('[data-stepid="'+stepCheck+'"] .checkStep').prop('disabled', true);
                    $('.generalModal').hide();
                    $('.generalModal').hide();
                    $('[data-stepid="'+stepCheck+'"] .stepValue').css('text-decoration', 'line-through');

                    message("Steget: (" + stepMsg + ") är markerad som färdig. Ett mail skickas nu till beställaren");
                });

        break;
        
        case 'general':
            $('.generalModal').remove();
            justOk.mode = 'none';
            justOk.justOk = true;
        break;
    }

    });



    $(document).on('click', '.no', function()
    {   
        if(justOk.mode == 'steps')
        {
            $('[data-stepid="'+stepCheck+'"] .checkStep').prop( "checked", false);
            $('[data-stepid="'+stepCheck+'"] .stepValue').css('text-decoration: none;');
            $('.generalModal').hide();
            justOk.mode = 'none';
            justOk.justOk = true;
        }
    });

    $('#parentStats span').click(function(e)
    {
        e.preventDefault();

        let dir = $(this).data('url');
             $('#parentStats span').removeClass('active');
             $('.recycle').removeClass('active');
             $(this).addClass('active');
        getOrders(dir);
    });

    function getOrders(url = dir)
    {

        if(workflow)
            url = 'prio';

        $.get('php/functions/getOrders.php', {'dir': url}, function(success){

            
            if(success == 'empty')
            {
                return false;
            }
            else
            {
              $('[data-url="'+url+'"]').addClass('active');
              $('.orders').html(success);
              $('#orderArea').append('<div class="clear"></div>');
              addToOrder();
            }
           });

    }

    $(".recycle").click(function(){
        $('#parentStats span').removeClass('active');
        $(this).addClass('active');

        getOrders('canceled');
    });

    $('#Menu .close').click(function(){

        $("#Menu").hide();

        $(".modal").hide();

    });


    $('.openMenu').click(function(){

        $("#Menu").show();

        $(".modal").show();

    });

    
    $(".addOrder").click(function(){
    
        location.href = 'order.php';

    });


    $('.closeTime').click(function(e){

        e.preventDefault();

        $('.modal').hide();
        $('.modalFocus').hide();
        $('.timeForm').hide();
        $('.timeForm input[type="number"]').val("");
        $('.timeForm .textarea').val("");

    });

    $('.saveTime').click(function(e){

            e.preventDefault();

            tinyMCE.triggerSave();
            
            $.post('php/functions/addWorkerTime.php', 

            $('.timeForm').serialize(), 

            function(success){

                if(success == "0")
                {
                    alert("Tiden är inte giltig...");
                    return false;
                }
                    if($('.timeForm .action').val() == "deny")
                        message("Uppgiften skickas tillbaka för korrigering");
                    else
                    {
                        var clock = JSON.parse(success);
                        message(clock['orderTime']);
                        $('.attestedTime').html(clock['totalTime']);
                    }

                    $('.modal').hide();
                    $('.modalFocus').hide();
                    $('.timeForm').hide();
                    $('.timeForm input[type="number"]').val("");
                    $('.timeForm textarea').val("");
                    $('.timeForm .action').val("");
                    $('.timeForm .orderid').val("");

                    if(orderFocus)
                    {
                        $("[data-orderId='" + id + "']").remove();
                        orderFocus = false;
                    }
                    else
                        $("[data-orderId='" + id + "']").slideUp();

            });

    });



    $(document).on('click', '.done', function(){

        $('.modal').show();
        id = $(this).parent().parent().parent().parent().attr("data-orderId");


        $('.timeForm').show();

        $('.timeForm .orderid').val(id);
    });



    $(document).on('click', '.delete', function(){
        
        if(orderFocus)
        {
            $(this).parent().children('.focusOnOrder').click();
        }

        id = $(this).parent().parent().parent().parent().attr("data-orderId");

        $(this).parent().parent().parent().parent().slideUp();



        $.post('php/functions/deleteOrder.php', {postid: id}, function(e){

            message("Flyttade ordern till papperskorgen");

        });

    });

    

    $(document).on('click', '.change', function(){

        id = $(this).parent().parent().parent().parent().attr("data-orderId");
        $.post('php/functions/changeOrder.php', {orderId: id}, function(message)
        {
            location.href = 'changeorder.php';
        });
    });


    
    $(document).on('click', '.Restore', function(){
         
        var id = $(this).parent().parent().attr("data-orderId");

        $.post('php/functions/restoreOrder.php', {'id': id});

        getOrders();
        $('.recycle').removeClass('active');
    });

    $(document).on('click', '.denyOrder', function(){
         
        $('.modal').show();
        id = $(this).parent().parent().parent().parent().attr("data-orderId");
        $('.timeForm .orderid').val(id);
        $('.timeForm .action').val("deny");

        $('.timeForm h5').remove();
        $('.timeForm').prepend("<h5>Vad behöver ändras?</h5>");
        $('.timeForm .fields').remove();
        $('.timeForm input[type="number"').remove();
        $('.timeForm').show();

    });

    $(document).on('click', '.acceptOrder', function()
    {
            
        if(orderFocus)
        {
            $(this).parent().children('.focusOnOrder').click();
        }

            var id = $(this).parent().parent().parent().parent().attr("data-orderId");

            $(this).parent().parent().parent().parent().slideUp();



            $.post('php/functions/acceptOrder.php', {orderId: id}, function(e){

                message("Uppgiften blev godkänd!");

            });

    });



    function addToOrder()
    {

        $('.order').each(function(){

            let id = $(this).data('orderid');
            $.get('php/functions/getSteps.php', {orderId: id}, function (success) {
                $('[data-steps="'+id+'"]').append(success);
              });
        })
    }

    $(document).on('click', '.openGallery', function(){

        var id = $(this).data('path');
        $.get('php/functions/getImages.php', {'orderid': id}, function(success){
            $('body').prepend('<div class="generalModal galleryModal"><div class="imageHolder"></div></div>');
             $('.generalModal').prepend("<div class='close'><button class='closeGallery'>X</button></div>");
            $('.imageHolder').html(success);
            $('.generalModal').show();
        });
    });

    $(document).on('click', '.closeGallery', function()
    {
        $('.generalModal').remove();
    });

    $(document).on('click', '.checkStep', function(){

        stepCheck = $(this).parent().parent().attr('data-stepid');
        orderId = $(this).parent().parent().parent().attr('data-steps');
        justOk.mode = 'steps';
        justOk.justOk = false;
        pageAlert("Markera ordern", "Vill du markera steget som färdigt? beställaren kommer att mailas.", true);

    });



    $(document).on('click', '.readMore', function(){
        var orderid = $(this).data('orderid');
        
        var order = $('[data-orderid="'+orderid+'"] .desc');
        
        if(!order.hasClass('autoHeight'))
        {
            order.addClass('autoHeight');
            order.removeClass('masked');
            $(this).addClass('rotate');
        }
        else
        {
            order.removeClass('autoHeight');
            order.addClass('masked');
            $(this).removeClass('rotate');
        }

        var height = $('[data-orderid="'+orderid+'"] .desc').css('height');
        
    });




    $(document).on('click', '.focusOnOrder', function()
    {
         orderId =  $(this).parent().parent().parent().parent().attr('data-orderid');
         let dir =  $(this).attr('data-dir');

         if(!orderFocus)
         {
             $('.modalFocus').show();
             $(this).parent().parent().parent().parent().addClass('orderFocus');
           
             $('[data-orderid="'+orderId+'"] .showText').click();
             $.post('php/functions/focusOrder.php', {'orderId': orderId, 'dir': dir});

             orderFocus = true;
         }
         else
         {
             $('.modalFocus').hide();
             $(this).parent().parent().parent().parent().removeClass('orderFocus');
             $('[data-orderid="'+orderId+'"] .showText').click();
             $.post('php/functions/focusOrder.php', {'orderId': orderId});
             orderFocus = false
         
         }
    });
   

    $('.modalFocus').click(function()
    {

            $.post('php/functions/focusOrder.php', {'orderId': 'none'});
            $('[data-orderid="'+orderId+'"]').removeClass('orderFocus');
            $('[data-orderid="'+orderId+'"] .showText').click();
            orderFocus = false;
            $(this).hide();

    });


    $(".searchOrder").on('input', function(event) {

           let string = $(this).val();

           if(string == '')
           {
                console.log('empty');
                $('.results').html('');
                getOrders();
                return;
           }
  
            $.post('php/functions/searchOrders.php', {'string': string}, function(success)
            {
                if(success == "empty")
                {
                    $('.results').html('0 resultat');
                    getOrders();
                    return false;
                }
                else
                {
                    $('.orders').html(success);
                    $('.orders').children().length;
                    $('.results').html($('.orders').children().length + ' resultat');
                }
            });
         
    });

    getOrders();


});

