$('document').ready(function()
{
    let dir = 'time';

    // load initial page
        loadPage(dir);

    function loadPage(dir)
    {
        switch(dir)
            {
            
                case 'pay':

                $.get('inc/payments.php', function(success)
                {
                    $('.jsHook').html(success);
                });

                break;

            case 'time':
            
                $.get('inc/timeWorked.php', function(success)
                {
                    $('.jsHook').html(success);
                });

            break;

            case 'users':
            
            $.get('inc/users.php', function(success)
            {
                $('.jsHook').html(success);
            });

        break;
        
        }
    }

    $('.menuButton').click(function()
    {
        let pageName = $(this).data('pagename');
        
        if(dir == pageName)
            return false;

        // check if page Exists
        $.get('inc/check.php', {'pageName': pageName}, function(success)
        {
            if(success == '1')
            {
                dir = pageName;
                console.log(pageName);
                loadPage(pageName);
            }
            else
                return false;
        });

    });

});