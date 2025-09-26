function message(string)

{

    

    $(".notis").html("<p>" + string + "</p>");
    $(".notis").slideDown(); 

    setTimeout(function(){

            $(".notis").slideUp(); 

    }, 3000)
}





var images = [];

var errorMsgs = [];



function isValid(file)

{

    errorMsgs = [];

    var check = file.type.split("/");

    

    if(check[0] != "image")

        errorMsgs.push('Filen är inte en bild...');

    

    for(var i = 0; i < images.length; i++)

    {

        if(images[i].size == file.size && images[i].name == file.name)

        {

            errorMsgs.push('Filen är redan tillagd');

            break;

        }

    }



    if(errorMsgs.length <= 0)

        return true;

    else {

        errorMsgs.unshift("File:\n" + file.name);

        return false;

    }

}



function appendImages(files)

{

    let arrayStart = images.length;

    for(var i = 0; i < files.length; i++)
    {   
        
        if(isValid(files[i]))

        {       

            images.push(files[i]);



            var fileReader = new FileReader();

            

            fileReader.readAsDataURL(images[images.length-1]);

            var img = "<div data-imageid='"+(arrayStart++)+"' id='imageHandler'><button class='DeleteImage'>Radera</button>";

            $('.showCase').prepend(img);
                

            $('#imageHandler').addClass('loading');

            var imgid = images.length-1;

            fileReader.onload = function(e)
            {
                console.log(imgid);
                $('.showCase [data-imageid="'+(imgid--)+'"]').css('background-image', 'url('+e.target.result+')').removeClass('loading');
                
            }

        }

        else

        {

            var tempMsg = "";

            $.each(errorMsgs, function(index, text) {

                if(index == 0)

                    tempMsg += text + "\n\r";

                else

                    tempMsg += "• " + text + "\n";

            });

            message(tempMsg);
        }

    

    }

}







$('.uploadbg').click(function(){

    $('.image').click();

});



$('.images').on('dragover', function(e){

    e.preventDefault();

});



$('.images').on('dragenter', function(e){

    e.preventDefault();

});



$('.images').on('drop', function(e){

    e.preventDefault();

    var file = e.originalEvent.dataTransfer.files;
    appendImages(file);

});





$('.image').on('change', function(e){

    var file = $(this).prop('files');

    appendImages(file);

});




$(document).on('click', '.DeleteImage', function(){

    var id = $(this).parent().attr('data-imageid');
    $(this).parent().remove();
    images[id] = "DELETED";

});





function exportImages()

{

    if(images.length <= 0) return false;

    var newArray = [];



    for(let i = 0; i < images.length; i++)

    {

        if(images[i] != "DELETED")

            newArray.push(images[i]);

    }



    return newArray;

}

