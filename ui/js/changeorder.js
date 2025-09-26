$(document).ready(function() {

    var Mysteps = [];

    function getSteps()
    {

    }

    tinymce.init({

        selector: '.content',  // change this value according to your HTML
    
        language: 'sv_SE',
    
        promotion: false,
    
        min_height: 600
    
      });
    


    $('.saveOrder').click(function(){

       

        tinyMCE.triggerSave();



        var name = $('.name').val();

        var host = $('.host').val();

        var adminName = $('.admin_name').val();

        var adminPassword = $('.admin_password').val();

        var devName = $('.devName').val();

        var markAsap = ($('.markAsap').is(':checked')) ? 'asap' : 'normal';

        var orderText = $('.content').val();

        

        if(name =="")
        {
            message("Fyll i namn");
            return false;
        }

    



        $.post('php/functions/updateOrder.php', 

        {

            "company_name": name,

            "company_domain": host,

            "order_desc": orderText,

            "worker": devName,

            "company_admin_username": adminName,

            "company_admin_password": adminPassword,

            "asap": markAsap,

            "orderid": path

        }, 



        function(message){

            



        location.href = 'php/functions/closeOrder.php';   

        console.log(message);

          



        });



    

    });

    $('.closeOrder').click(function()
    {
        location.href = 'php/functions/closeOrder.php';   
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