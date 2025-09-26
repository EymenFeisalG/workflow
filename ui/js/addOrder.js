$(document).ready(function() {

    var stepsValue = "";
    var Mysteps = [];

   
    $('form').submit(function(e){
        e.preventDefault();

    });

    tinymce.init({
        selector: '.content',  // change this value according to your HTML
        language: 'sv_SE',
        promotion: false,
        min_height: 600
      });


      $(showUploader).click(function()
      {
        $('.imagesContainer').show();
      });

    $('.registered_users').on('change', function(){
         var value = $(this).val();
         var customerData = [];

         if(value != "newCustomer")
         {
            $('.markSaveCustomer').hide();
            
            $.post('php/functions/getSavedCustomers.php', {'customerID': value}, function(success)
            {
                customerData = JSON.parse(success);

                $('.name').val(customerData['name']).prop('disabled', true);
                $('.host').val(customerData['url']).prop('disabled', true);
                $('.admin_name').val(customerData['admin_username']).prop('disabled', true);
                $('.admin_password').val(customerData['admin_password']).prop('disabled', true);
                $('.org').val(customerData['org']).prop('disabled', true);
                $('.contact').val(customerData['contact']).prop('disabled', true);
            });
         }
            
        else
        {
            $('.markSaveCustomer').show();

            $('.name').val("").prop('disabled', false);
            $('.host').val("").prop('disabled', false);
            $('.admin_name').val("").prop('disabled', false);
            $('.admin_password').val("").prop('disabled', false);
            $('.org').val("").prop('disabled', false);
            $('.contact').val("").prop('disabled', false);

        }

    });

    $(document).on('click', '.yes', function(){
        location.href = "php/functions/closeOrder.php";
    });

    $(document).on('click', '.no', function(){
        $('.generalModal').remove();
    });

    $('.closeOrder').click(function(){
        pageAlert("Är du säker?", "Du är påväg att stänga ner sidan. Ordern kommer att försvinna", true);
    });


    function copySteps(orderid)
    {
        stepsValue = "";
        $('.messageToDev').children().each(function(){
            var data = $(this).children("input").val();
            
            stepsValue += "$" + data;

        });

    }


    $('.saveOrder').click(function(){
       
        tinyMCE.triggerSave();
        copySteps();

        var name = $('.name').val();
        var host = $('.host').val();
        var org = $('.org').val();
        var contact = $('.contact').val();
        var adminName = $('.admin_name').val();
        var adminPassword = $('.admin_password').val();
        var devName = $('.devName').val();
        var messageToDev = stepsValue;
        var markAsap = ($('.markAsap').is(':checked')) ? 'asap' : 'normal';
        var saveCustomerToDb = ($('.saveCustomer').is(':checked')) ? 'true' : 'false';
        var orderText = $('.content').val();
        
        if(name =="")
        {
            message("Ordern ser tom ut. Kommer inte att skickas");
            return false;
        }
    
        $.post('php/functions/addOrder.php', 
        {
            "company_name": name,
            "company_domain": host,
            "order_desc": orderText,
            "worker": devName,
            "company_admin_username": adminName,
            "company_admin_password": adminPassword,
            "asap": markAsap,
            "devMessage": messageToDev,
            "path": path,
            "saveCustomer": saveCustomerToDb,
            "org": org,
            "contact": contact
        }, 

        function(message){

                var getImages = exportImages();

                if(getImages.length > 0)
                {
                    var form = new FormData();
                    
                    for(var i = 0; i < getImages.length; i++)
                    {
                        form.append('image[]', getImages[i]);
                    }

                    $.ajax({
                        url: 'php/functions/upload.php',
                        type: "POST",
                        data: form,
                        success: function(success)
                        {
                            location.href = 'php/functions/closeOrder.php';
                        },
                        processData: false,
                        contentType: false
                    });
                }
                else
                {
                    // no images were uploaded
                    $.post('php/functions/upload.php', {'imagesExists' : false});

                    location.href = 'php/functions/closeOrder.php';   

                   
                }

        });

    
    });


    function createStep()
    {
        var html = "<div class='stepCount'>" +
                "<span class='Remove'></span>"+
                "<input class='stepByStep' type='text'>"+
                "</div>"; 

            $(html).appendTo('.messageToDev');
            $('.stepBystep:last-child').focus();

            var counter = 1;
            $('.messageToDev').children('.stepCount').each(function () {
                $(this).children('.Remove').html(counter);
                counter++;
            });


            $('.stepByStep:last-child').focus();
            $('.resetList').show();

    }


    function storeList()
    {
        
        $('.stepByStep').each(function(){
            Mysteps.push($(this).val());
        });
    }

    function reCreateList()
    {

      var counter  = 1;

       for(var i = 0; i < Mysteps.length; i++)
       {

            var html = "<div class='stepCount'>" +
                    "<span class='Remove'>"+counter+"</span>"+
                    "<input class='stepByStep' value='"+Mysteps[i]+"' type='text'>"+
                    "</div>"; 
                    $(html).appendTo('.messageToDev');

                    counter++;

       }
    }

    $('.resetList').click(function() 
    {  
        storeList();
        $('.reCreateList').show();
        $('.messageToDev').empty();
        $(this).hide();
       
    });

    $('.reCreateList').click(function()
    {
        $(this).hide();
        $('.resetList').show();
        reCreateList();
        Mysteps = [];
    });


    $('.messageToDev').click(function()
    {
        if($('.messageToDev').children().length == 0)
             createStep();
    });

    $('.addToList').on('click', function(e){

        createStep();

    });

    $(document).on('keypress', '.stepByStep', function(e)
    {
        if(e.which == 13)
        {
                createStep();
        }
    });


    $(document).on('click', '.Remove', function(e)
    {   
        $(this).parent().remove();
        var counter = 1;
        $('.messageToDev').children('.stepCount').each(function () {
           $(this).children('.Remove').html(counter);
           counter++;
       });
        
    });


    $(document).on('mouseenter', '.Remove', function()
    {
       
       $(this).addClass('delete');

    }).on('mouseleave', '.Remove', function()
    {
        $(this).removeClass('delete');
     
    });



});