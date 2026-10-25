<?php
// Shares only the fresh explicit isolated identity fixture of centralenIntegration.
require __DIR__.'/../php/classes/centralenShell.class.php';
$_SESSION=['centralen_identity'=>['user_id'=>$ids[1],'session_hash'=>$session],'csrf_token'=>'retained'];
putenv('CENTRALEN_URL=http://localhost/websites/centralen');putenv('WORKFLOW_INTEGRATION_SECRET='.$secret);
$client=new CentralenShell($identity,static function($url,$raw,$signature)use($check,$ids,$session,$secret){
    $packet=json_decode($raw,true);
    $check($packet['audience']==='centralen-shell-v1'&&$packet['payload']['actor_id']===$ids[1]&&$packet['payload']['actor_session_hash']===$session,'shell client overwrites untrusted actor/session');
    $check(hash_equals(hash_hmac('sha256',$raw,$secret),$signature)&&$url==='http://localhost/websites/centralen/src/functions/core/workflow-shell.php','shell client fixed endpoint and raw HMAC');
    return ['status'=>200,'body'=>'{"data":{"ok":true}}'];
});
$check($client->request('chat',['actor_id'=>$ids[2],'actor_session_hash'=>str_repeat('f',64)])===['ok'=>true]&&$_SESSION['csrf_token']==='retained','shell client pure data and preserved session values');
$oldSession=$_SESSION;$_SESSION['centralen_identity']['session_hash']=str_repeat('f',64);
$invalidClient=new CentralenShell($identity);$reject(fn()=>$invalidClient->request('shell'),'shell client revoked session',CentralenShellException::class);$_SESSION=$oldSession;
