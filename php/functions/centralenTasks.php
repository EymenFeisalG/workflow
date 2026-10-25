<?php
declare(strict_types=1);
// No browser session or password is accepted: only the signed Centralen server.
require __DIR__.'/../../settings.php';
require __DIR__.'/../classes/database.class.php';
require __DIR__.'/../classes/taskNotifications.class.php';
require __DIR__.'/../classes/main.class.php';
require __DIR__.'/../classes/centralenTasks.class.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try{
    if(!CentralenIdentity::enabled())throw new RuntimeException('Integration is disabled');
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);throw new InvalidArgumentException('Metoden stöds inte.');}
    if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json'){http_response_code(415);throw new InvalidArgumentException('JSON krävs.');}
    $raw=file_get_contents('php://input',false,null,0,65537);if($raw===false||strlen($raw)>65536){http_response_code(413);throw new InvalidArgumentException('För stora uppgifter.');}
    new database($mysql_settings);$identity=CentralenIdentity::connect(database::$mysql);
    $tasks=new CentralenTasks(database::$mysql,$identity,new main(),CentralenIdentity::url('WORKFLOW_URL'));
    $secret=CentralenIdentity::setting('WORKFLOW_INTEGRATION_SECRET');if(!preg_match('/\A[a-f0-9]{64}\z/D',$secret))throw new RuntimeException('Integration key invalid');
    $request=$tasks->authenticate($raw,(string)($_SERVER['HTTP_X_INTEGRATION_SIGNATURE']??''),$secret);
    echo json_encode(['data'=>$tasks->handle($request['action'],$request['payload'])],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
}catch(DomainException$error){http_response_code($error->getCode()===409?409:403);echo json_encode(['error'=>['code'=>$error->getCode()===409?'workflow_conflict':'workflow_forbidden','message'=>$error->getMessage()]],JSON_UNESCAPED_UNICODE);}
catch(InvalidArgumentException|JsonException$error){if(http_response_code()<400)http_response_code(422);echo json_encode(['error'=>['code'=>'workflow_invalid','message'=>'Kontrollera uppgiftens uppgifter.']],JSON_UNESCAPED_UNICODE);}
catch(Throwable$error){error_log('Workflow integration failed: '.get_class($error));http_response_code(503);echo json_encode(['error'=>['code'=>'workflow_unavailable','message'=>'Workflow kunde inte nås. Försök igen.']],JSON_UNESCAPED_UNICODE);}
