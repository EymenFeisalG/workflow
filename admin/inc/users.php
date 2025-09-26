<script>
    $(document).ready(function()
    {

        $(document).on('click', '.yes', function()
        {
            $('.generalModal').remove();
        });


        $(document).on('click', '.cancel', function()
        {  
           $('.open_addUser').removeClass('active');
           $('.open_addUser').html('Lägg till en ny användare');
           $('.newUser').remove();
        });


        function generateUsers(username, email, role, active = true)
        {
            if(!active)
            {
                return `
                    <div class="user">
                        <div class="active pending">Aktiveringskod: ${role}</div>
                        <h5>${username}</h5>
                        <h5>${email}</h5>
                    </div>
            `;
            }


            return `
                    <div class="user">
                        <div class="active">Aktiv</div>
                        <h5>${username}</h5>
                        <h5>${email}</h5>
                    </div>
            `;
        }


        $('.open_addUser').click(function()
        {
            if(!$(this).hasClass("active"))
            {
                $(this).addClass("active");
                $(this).html("Spara");

                var newUser = `
                    <div class="newUser">
                        <div class="form">
                        <label>Användarnamn</label>
                       <input type="text" required class="username">
                       <label>Timlön i kronor</label>
                       <input type="number" value="0" required class="salary">
                       <label>Email</label>
                       <input type="email" required class="email">
                       <label>Roll</label>
                       <select class="role">
                            <option name="Utvecklare">Arbetare</option>
                            <option name="admin">Admin</option>
                       </select>
                       <button class="cancel">Ångra</button>
                       </div>
                    </div>
            `;

            $('.output').prepend(newUser);
        }
        else
        {
            var username = $.trim($('.form .username').val());
            var email = $.trim($('.form .email').val());
            var salary = $.trim($('.form .salary').val());
            var role = $.trim($('.form .role').val());

            $.post('functions/addUser.php', {'username': username, 'email': email, 'role': role, 'salary': salary}, function(success)
            {
                if(success == "FIELDS_EMPTY")
                {
                    pageAlert("Notis", "Alla fält är obligatoriska att fylla i");
                    return false;
                }

                if(success == "USER_TAKEN")
                {
                    pageAlert("Notis", "Användarnamnet eller mailet är redan i bruk");
                    return false;
                }

                
                if(success == "USER_PENDING")
                {
                    pageAlert("Notis", "Användarnaren väntar redan på aktivering");
                    return false;
                }


        
                    message(username + " har lagts till. Ett mail med koden skickas till " + email + ". Den ska skrivas in i registrerings sidan för att slutföra registreringen.");
                    $('.output').append(generateUsers(username, email, success, false));
                    $('.cancel').trigger('click');
                    return false;
                   
            });
        }


      
        });


        $.get('functions/getAllUsers.php', function(data){

            
            data = JSON.parse(data);
            for(var key in data)
            {
                var isActive = (data[key].active == 'true') ? true : false;
                //var roleName = (data[key].user_role == 'true') ? true : false;

                $('.output').append(generateUsers(data[key].username, data[key].email, data[key].user_role, isActive));
            }
        });
      
    });
</script>

<div class="output">
    <div class="addUser">
        <button class="open_addUser">Lägg till en ny användare</button>
    </div>
</div>
