<?php
declare(strict_types=1);
require __DIR__.'/global.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
$error='';
try{
    if(!$centralenIdentity)throw new RuntimeException('Integration is disabled');
    $action=$_GET['action']??'start';
    if(!is_string($action)||!in_array($action,['start','link-start','callback','link'],true))throw new DomainException('Inloggningslänken är ogiltig.');
    if($action==='start'||$action==='link-start'){
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')throw new DomainException('Metoden stöds inte.');
        $state=bin2hex(random_bytes(32));$verifier=bin2hex(random_bytes(32));
        // Only this app-owned destination can follow authentication.
        $_SESSION['centralen_login']=['state'=>$state,'verifier'=>$verifier,'expires'=>time()+300,'purpose'=>$action==='link-start'?'legacy-link':'login'];
        unset($_SESSION['centralen_pending']);
        $url=CentralenIdentity::url('CENTRALEN_URL').'/src/functions/core/workflow-login.php?action=authorize&state='.$state.'&challenge='.hash('sha256',$verifier);
        header('Location: '.$url, true, 302);exit;
    }
    if($action==='callback'){
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')throw new DomainException('Metoden stöds inte.');
        $pending=$_SESSION['centralen_login']??null;unset($_SESSION['centralen_login']);
        $state=$_GET['state']??null;$code=$_GET['code']??null;
        if(!is_array($pending)||($pending['expires']??0)<time()||!is_string($state)||!is_string($code)||!hash_equals($pending['state'],$state))throw new DomainException('Inloggningen har gått ut. Försök igen.');
        $grant=$centralenIdentity->consume($code,$state,$pending['verifier']);
        session_regenerate_id(true);$_SESSION['csrf_token']=bin2hex(random_bytes(32));
        $identity=['user_id'=>$grant['user']['id'],'session_hash'=>$grant['session_hash']];
        if(($pending['purpose']??'login')==='legacy-link'){
            $_SESSION['centralen_pending']=$identity+['expires'=>time()+300,'attempts'=>0];
            header('Location: centralen-login.php?action=link',true,303);exit;
        }
        $user=$centralenIdentity->linked($identity['user_id'],$identity['session_hash'])??$centralenIdentity->provision($identity['user_id'],$identity['session_hash']);
        if($user){$_SESSION['centralen_identity']=$identity;$_SESSION['user']=$user;$auth->initRights();header('Location: '.($auth->Maintenance()?'Maintenance.php':'home.php'),true,303);exit;}
        throw new DomainException('Workflow-kontot kunde inte öppnas. Försök igen.');
    }
    $pending=$_SESSION['centralen_pending']??null;
    if(!is_array($pending)||($pending['expires']??0)<time()||!$centralenIdentity->user($pending['user_id'],$pending['session_hash']))throw new DomainException('Logga in i Centralen igen.');
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        if(($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site'||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf_token'],$_POST['csrf']))throw new DomainException('Ladda om sidan och försök igen.');
        if(++$_SESSION['centralen_pending']['attempts']>5)throw new DomainException('För många försök. Logga in igen senare.');
        $fresh=($_POST['profile_action']??'link')==='new';
        $login=$_POST['username']??null;$password=$_POST['password']??null;
        if($fresh){
            if(!CentralenIdentity::automaticProfiles())throw new DomainException('Ny medarbetarprofil är inte aktiverad.');
            $user=$centralenIdentity->provision($pending['user_id'],$pending['session_hash'],true);
            if(!$user)throw new DomainException('Profilen kunde inte skapas.');
        }else{
            if(!is_string($login)||!is_string($password))throw new DomainException('Kontouppgifterna stämmer inte.');
            $user=$centralenIdentity->link($pending['user_id'],$pending['session_hash'],trim($login),$password);
        }
        session_regenerate_id(true);$_SESSION['centralen_identity']=['user_id'=>$pending['user_id'],'session_hash'=>$pending['session_hash']];$_SESSION['user']=$user;
        unset($_SESSION['centralen_pending']);$_SESSION['csrf_token']=bin2hex(random_bytes(32));$auth->initRights();header('Location: '.($auth->Maintenance()?'Maintenance.php':'home.php'),true,303);exit;
    }
}catch(DomainException$failure){$error=$failure->getMessage();http_response_code(400);}
catch(Throwable$failure){error_log('Workflow shared login failed: '.get_class($failure));$error='Inloggningen kunde inte slutföras. Försök igen.';http_response_code(503);}
$pending=$_SESSION['centralen_pending']??null;
$canLink=is_array($pending)&&($pending['expires']??0)>=time()&&($pending['attempts']??0)<5;
$profileTitle=CentralenIdentity::automaticProfiles()?'Din Workflow-profil':'Koppla Workflow-konto';
$escape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<!doctype html><html lang="sv"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $escape($profileTitle) ?></title><link rel="stylesheet" href="ui/style/css/login.css"><link rel="stylesheet" href="ui/style/css/general.css"></head><body>
<main class="loginHolder"><section class="loginForm" aria-labelledby="link-title"><h1 id="link-title"><?= $escape($profileTitle) ?></h1>
<?php if($error!==''): ?><p role="alert"><?= $escape($error) ?></p><?php endif; ?>
<?php if($canLink): ?><p><?= CentralenIdentity::automaticProfiles()?'Koppla en tidigare profil för att behålla dina uppgifter, eller fortsätt med en ny medarbetarprofil.':'Bekräfta ditt befintliga Workflow-konto en gång. Därefter loggar du in med Centralen.' ?></p>
<form method="post" action="centralen-login.php?action=link"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['csrf_token']) ?>">
<div class="fieldGroup"><label for="workflow-user">Workflow-användarnamn eller e-post</label><input id="workflow-user" type="text" name="username" autocomplete="username" required maxlength="254" value="<?= $escape(is_string($_POST['username']??null)?$_POST['username']:'') ?>"></div>
<div class="fieldGroup"><label for="workflow-password">Nuvarande Workflow-lösenord</label><input id="workflow-password" name="password" type="password" autocomplete="current-password" required maxlength="512"></div>
<div class="buttons"><button class="login" type="submit">Koppla konto</button></div></form>
<?php if(CentralenIdentity::automaticProfiles()): ?><form method="post" action="centralen-login.php?action=link"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['csrf_token']) ?>"><input type="hidden" name="profile_action" value="new"><button class="centralen-new-profile" type="submit">Fortsätt med ny medarbetarprofil</button></form><?php endif; ?>
<?php endif; ?><a class="centralen-login-return" href="centralen-login.php?action=start">Logga in med Centralen</a></section></main></body></html>
