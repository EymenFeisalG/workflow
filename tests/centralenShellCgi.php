<?php
// Runs only inside the disposable centralenCgi fixture, never the IIS business site.
copy($root.'/centralen-shell-api.php',$site.'/centralen-shell-api.php');
$anonymous=[];$response=$request($anonymous,'centralen-shell-api.php','route=/chat/threads');
$assertCgi($response['status']===401&&$response['json']['error']['code']==='login_required','shell proxy returns typed login error');
$response=$request($cookies,'centralen-shell-api.php','route=/conversations','{}',['CONTENT_TYPE'=>'application/json']);
$assertCgi($response['status']===403&&$response['json']['error']['code']==='csrf_failed','shell proxy rejects missing Workflow CSRF before transport');
$response=$request($cookies,'centralen-shell-api.php','route=/chat/threads',null,['HTTP_SEC_FETCH_SITE'=>'cross-site']);
$assertCgi($response['status']===403&&$response['json']['error']['code']==='cross_site','shell proxy rejects cross-site access');
$local->query("UPDATE workgui_settings SET value='1'");
$response=$request($cookies,'centralen-shell-api.php','action=shell');
$assertCgi($response['status']===403&&$response['json']['error']['code']==='workflow_maintenance'&&$response['location']==='','shell proxy enforces maintenance before all actions without HTML redirect');
$local->query("UPDATE workgui_settings SET value='0'");
