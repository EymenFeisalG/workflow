
function message(string, subject = "Meddelande")
{

    $('body').append('<div class="notis"><h1>'+subject+'</h1>' + '<p>' + string + '</p>' + '</div>');

    $(".notis").slideDown(); 


    setTimeout(function(){

            $(".notis").slideUp(); 

    }, 6000);

    

}

function pageAlert(messageTitle = "Notis", message, confirm = false)
{

    if(!confirm)

    {

        var html =    '<div class="generalModal">' +

                '<div class="alert">' +

                '<div class="title">'+messageTitle+'</div>' +

                '<p>'+message+'</p>'+

                '<div class="confirm">'+

                    '<button class="yes">Okej</button>'+

                '</div>'+

            '</div>'+

        '</div>';
    }
    else
    {
        var html =    '<div class="generalModal">' +

        '<div class="alert">' +

        '<div class="title">'+messageTitle+'</div>' +

                '<p>'+message+'</p>'+

        '<div class="confirm">'+

            '<button class="yes">Bekräfta</button>'+

            '<button class="no">Ångra</button>'+

        '</div>'+

            '</div>'+

        '</div>';

    }
    $('body').append(html);
}   