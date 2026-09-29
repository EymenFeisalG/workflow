<?php
define('login_req', true);
require '../global.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($path && !str_ends_with($path, '/') && !str_ends_with($path, '.php')) {
    header('Location: ' . $path . '/' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
    exit;
}
$auth->userLoginCheck();
if (!$auth->hasRight('admin')) {
    header('Location: ../home.php');
    exit;
}
?>
<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Användare · Workflow</title>
    <link rel="stylesheet" href="../ui/style/css/admin.css?v=1">
    <script src="../ui/js/jquery.js" defer></script>
    <script src="../ui/js/admin.js?v=1" defer></script>
</head>
<body>
    <div class="adminShell">
        <aside class="adminSidebar">
            <a class="brand" href="../home.php"><span class="brandMark">W</span><span>Workflow<small>Administration</small></span></a>
            <nav aria-label="Administrationsmeny"><span class="navCaption">Hantera</span><a class="navItem current" href="./" aria-current="page">Användare</a><a class="navItem" href="../home.php">Till arbetsytan</a></nav>
            <div class="sidebarFooter"><span>Inloggad som</span><strong><?= htmlspecialchars((string)($_SESSION['user']['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></div>
        </aside>
        <main class="adminMain">
            <header class="topbar"><a href="../home.php" class="mobileBrand">Workflow</a><a href="../home.php">← Till arbetsytan</a></header>
            <div class="pageContent">
                <div class="pageHeading"><div><p class="eyebrow">Administration</p><h1>Användare</h1><p class="intro">Bjud in kollegor och styr vad de kan göra i Workflow.</p></div><button type="button" class="button primary" id="showInvite">+ Lägg till användare</button></div>
                <div id="notice" class="notice" role="status" aria-live="polite" hidden></div>
                <section class="summary" aria-label="Översikt"><div><span>Aktiva konton</span><strong id="activeCount">–</strong></div><div><span>Väntar på aktivering</span><strong id="pendingCount">–</strong></div></section>
                <section class="panel"><div class="panelHeading"><div><h2>Alla användare</h2><p>Välj en användare för att ändra rättigheter.</p></div><label class="searchLabel"><span class="srOnly">Sök användare</span><input id="userSearch" type="search" placeholder="Sök namn eller e-post"></label></div><div id="userList" class="userList"><p class="emptyState">Läser in användare…</p></div></section>
            </div>
        </main>
    </div>
    <dialog id="inviteDialog" class="editorDialog"><form id="inviteForm"><div class="dialogHeading"><div><p class="eyebrow">Ny användare</p><h2>Lägg till användare</h2></div><button type="button" class="iconButton closeDialog" aria-label="Stäng">×</button></div><p>En aktiveringskod skickas till e-postadressen.</p><label>Användarnamn<input name="username" required maxlength="100" autocomplete="off"></label><label>E-postadress<input name="email" type="email" required maxlength="255" autocomplete="off"></label><label>Roll<select name="role"><option value="Arbetare">Medarbetare</option><option value="Admin">Administratör</option></select></label><div class="dialogActions"><button type="button" class="button closeDialog">Avbryt</button><button type="submit" class="button primary">Skicka inbjudan</button></div></form></dialog>
    <dialog id="rightsDialog" class="editorDialog"><form id="rightsForm"><input type="hidden" name="user_id" id="rightsUserId"><div class="dialogHeading"><div><p class="eyebrow">Behörigheter</p><h2 id="rightsTitle">Användare</h2></div><button type="button" class="iconButton closeDialog" aria-label="Stäng">×</button></div><p id="rightsDescription"></p><div id="rightsOptions" class="rightsOptions"></div><p class="helpText">Ändringar gäller när användaren laddar om sidan.</p><div class="dialogActions"><button type="button" class="button closeDialog">Avbryt</button><button type="submit" class="button primary">Spara rättigheter</button></div></form></dialog>
    <script>window.AdminCsrf = <?= json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
</body>
</html>
