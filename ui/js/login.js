$(document).ready(function()
{

    $('form').submit(function(e)
    {
        e.preventDefault();

        if($('.generalModal').is(':visible')) return false;

        $.post('php/functions/login.php', $(this).serialize(), function(success){
            
            console.log(success);
            if(success == "success")
            {
                console.log("dw");
                location.href = 'home.php';
            }

            if(success == "fail")
            {
                $(document).focus();
                pageAlert("Ingen matchning", "Dina inloggningsuppgifter stämmer inte överens. Försök igen");
                return;
            }

        });

    });


    $(document).on('click', '.yes', function()
    {
        $('.generalModal').hide();
    });




});