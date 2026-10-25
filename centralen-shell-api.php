<?php
declare(strict_types=1);
require_once __DIR__.'/global.php';
require_once __DIR__.'/php/classes/centralenShell.class.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
try{
    if(!$centralenIdentity||!isset($_SESSION['user'])||!$centralenIdentity->validateSession($_SESSION))throw new CentralenShellException(401,'login_required','Logga in igen.');
    if($auth->Maintenance())throw new CentralenShellException(403,'workflow_maintenance','Workflow är under underhåll.');
    if(($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site')throw new CentralenShellException(403,'cross_site','Öppna formuläret i Workflow.');
    $method=$_SERVER['REQUEST_METHOD']??'';
    if(!in_array($method,['GET','POST'],true))throw new CentralenShellException(405,'method_not_allowed','Metoden stöds inte.');
    $input=[];
    if($method!=='GET'){
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if(!is_string($csrf)||!is_string($_SESSION['csrf_token']??null)||!hash_equals($_SESSION['csrf_token'],$csrf))throw new CentralenShellException(403,'csrf_failed','Öppna formuläret igen.');
        if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new CentralenShellException(415,'json_required','JSON krävs.');
        $raw=file_get_contents('php://input',false,null,0,262145);if($raw===false||strlen($raw)>262144)throw new CentralenShellException(413,'body_too_large','Formuläret är för stort.');
        $object=json_decode($raw,false,32,JSON_THROW_ON_ERROR);if(!$object instanceof stdClass)throw new CentralenShellException(422,'invalid_json','Ett formulärobjekt krävs.');$input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    }
    $client=new CentralenShell($centralenIdentity);
    if(isset($_GET['route'])){
        $route=$_GET['route'];if(!is_string($route)||strlen($route)>200)throw new CentralenShellException(404,'not_found','Funktionen finns inte.');
        $data=$client->request('chat',['method'=>$method,'route'=>$route,'input'=>$input,'query'=>$_GET]);
    }else{
        $action=$_GET['action']??'shell';
        if($action==='shell'&&$method==='GET')$data=$client->request('shell');
        elseif(in_array($action,['context','create','operation'],true)&&$method===($action==='create'?'POST':'GET'))$data=$client->request('tasks',['action'=>$action,'input'=>$method==='GET'?$_GET:$input]);
        else throw new CentralenShellException(405,'method_not_allowed','Metoden stöds inte.');
    }
    echo json_encode(['data'=>$data],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(CentralenShellException $error){http_response_code($error->status);echo json_encode(['error'=>['code'=>$error->errorCode,'message'=>$error->getMessage(),'details'=>$error->details]],JSON_UNESCAPED_UNICODE);}
catch(JsonException){http_response_code(422);echo json_encode(['error'=>['code'=>'invalid_json','message'=>'Formuläret kunde inte läsas.']]);}
catch(Throwable $error){error_log('Centralen shell proxy failed: '.get_class($error));http_response_code(503);echo json_encode(['error'=>['code'=>'bridge_unavailable','message'=>'Centralen kunde inte nås. Kontrollera status innan du försöker igen.']]);}
