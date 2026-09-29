$('document').ready(function()
{
    let dir = 'users';

    // load initial page
    loadPage(dir);

    function loadPage(dir)
    {
        switch(dir)
        {
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