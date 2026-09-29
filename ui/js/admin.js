$(function () {
    'use strict';
    const rights = [
        ['add_new_order', 'Skapa uppgifter', 'Kan lägga till nya uppgifter.'],
        ['orders_show_all', 'Se alla uppgifter', 'Kan se hela teamets uppgifter.'],
        ['changeOrder', 'Ändra andras uppgifter', 'Kan korrigera uppgifter skapade av andra.'],
        ['deleteOrder', 'Ta bort uppgifter', 'Kan flytta uppgifter till papperskorgen.'],
        ['admin', 'Administration', 'Kan öppna adminpanelen och hantera användare.'],
        ['all', 'Alla standardrättigheter', 'Ger alla vanliga rättigheter, inklusive administration.'],
        ['maintenanceLogin', 'Tillgång vid underhåll', 'Kan arbeta när underhållsläge är aktivt.']
    ];
    let users = [];
    const $list = $('#userList');
    const $notice = $('#notice');
    function notify(message, error) { $notice.text(message).toggleClass('error', !!error).prop('hidden', false); }
    function label(user) { return user.user_role === 2 ? 'Administratör' : 'Medarbetare'; }
    function render() {
        const search = $('#userSearch').val().trim().toLocaleLowerCase('sv');
        const filtered = users.filter(user => (user.username + ' ' + user.email).toLocaleLowerCase('sv').includes(search));
        $('#activeCount').text(users.filter(user => user.active).length);
        $('#pendingCount').text(users.filter(user => !user.active).length);
        $list.empty();
        if (!filtered.length) { $list.append($('<p class="emptyState">').text(search ? 'Ingen användare matchar sökningen.' : 'Inga användare finns ännu.')); return; }
        filtered.forEach(user => {
            const $row = $('<div class="userRow">');
            const $identity = $('<div class="userIdentity">');
            const initial = (user.username || '?').trim().charAt(0).toLocaleUpperCase('sv');
            $identity.append($('<span class="initial">').text(initial));
            const $names = $('<div>');
            $names.append($('<strong>').text(user.username));
            $names.append($('<small>').text(user.email));
            $identity.append($names);
            $row.append($identity);
            $row.append($('<span class="roleTag">').text(label(user)));
            $row.append($('<span class="statusTag">').addClass(user.active ? 'enabled' : 'waiting').text(user.active ? 'Aktiv' : 'Väntar på aktivering'));
            const $actions = $('<div class="rowActions">');
            if (user.active) $actions.append($('<button type="button" class="button subtle">').text('Rättigheter').on('click', () => openRights(user)));
            $row.append($actions);
            $list.append($row);
        });
    }
    function loadUsers() {
        $.getJSON('functions/getAllUsers.php').done(data => { users = data; render(); })
            .fail(() => { $list.html('<p class="emptyState">Kunde inte läsa användarna. Ladda om sidan och försök igen.</p>'); });
    }
    function openRights(user) {
        $('#rightsUserId').val(user.id);
        $('#rightsTitle').text(user.username);
        $('#rightsDescription').text(user.email + ' · ' + label(user));
        const $options = $('#rightsOptions').empty();
        rights.forEach(([key, title, description]) => {
            const $label = $('<label class="rightOption">');
            $label.append($('<input type="checkbox" name="rights[]">').val(key).prop('checked', user.rights.includes(key)));
            const $copy = $('<span>');
            $copy.append($('<strong>').text(title), $('<small>').text(description));
            $label.append($copy);
            $options.append($label);
        });
        document.getElementById('rightsDialog').showModal();
    }
    $('#userSearch').on('input', render);
    $('#showInvite').on('click', () => document.getElementById('inviteDialog').showModal());
    $('.closeDialog').on('click', function () { this.closest('dialog').close(); });
    $('#inviteForm').on('submit', function (event) {
        event.preventDefault();
        const $submit = $(this).find('[type=submit]').prop('disabled', true);
        $.post('functions/addUser.php', $(this).serialize() + '&csrf_token=' + encodeURIComponent(window.AdminCsrf), null, 'json')
            .done(data => { this.reset(); document.getElementById('inviteDialog').close(); notify('Inbjudan skapad. Aktiveringskod: ' + data.code + '. Kontrollera e-postleveransen.'); loadUsers(); })
            .fail(xhr => { const errors = { USER_TAKEN: 'Användarnamnet eller e-postadressen används redan.', USER_PENDING: 'En inbjudan väntar redan på aktivering.', FIELDS_EMPTY: 'Fyll i alla fält.', INVALID_INPUT: 'Kontrollera e-postadress och roll.' }; notify(errors[xhr.responseJSON?.status] || 'Kunde inte skapa användaren.', true); })
            .always(() => $submit.prop('disabled', false));
    });
    $('#rightsForm').on('submit', function (event) {
        event.preventDefault();
        const $submit = $(this).find('[type=submit]').prop('disabled', true);
        $.post('functions/saveRights.php', $(this).serialize() + '&csrf_token=' + encodeURIComponent(window.AdminCsrf), null, 'json')
            .done(() => { document.getElementById('rightsDialog').close(); notify('Rättigheterna har sparats.'); loadUsers(); })
            .fail(() => notify('Kunde inte spara rättigheterna. Kontrollera att du inte tar bort din egen administratörsbehörighet.', true))
            .always(() => $submit.prop('disabled', false));
    });
    loadUsers();
});
