<?php
declare(strict_types=1);
// Self-contained databases only: never loads settings.php or the application's .env.
if(getenv('CENTRALEN_TEST_DB_PROFILE')!=='isolated'||!in_array(getenv('CENTRALEN_TEST_DB_HOST'),['127.0.0.1','localhost','::1'],true))throw new RuntimeException('Explicit isolated loopback profile required');
foreach(['HOST','PORT','USER','PASSWORD']as$key)if(getenv('CENTRALEN_TEST_DB_'.$key)===false)throw new RuntimeException('Incomplete isolated profile');
$host=getenv('CENTRALEN_TEST_DB_HOST');$port=getenv('CENTRALEN_TEST_DB_PORT');$user=getenv('CENTRALEN_TEST_DB_USER');$password=getenv('CENTRALEN_TEST_DB_PASSWORD');
if(!ctype_digit($port))throw new RuntimeException('Invalid port');
require __DIR__.'/../php/classes/database.class.php';require __DIR__.'/../php/classes/taskNotifications.class.php';require __DIR__.'/../php/classes/main.class.php';require __DIR__.'/../php/classes/centralenTasks.class.php';
$server=new mysqli($host,$user,$password,'',(int)$port);$sharedName='centralen_test_shared_'.bin2hex(random_bytes(6));$workflowName='centralen_test_workflow_'.bin2hex(random_bytes(6));
$made=[];$checks=0;putenv('WORKFLOW_AUTO_PROVISION=0');
$check=static function(bool$ok,string$label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$reject=static function(callable$operation,string$label,string$class=DomainException::class)use($check):void{try{$operation();}catch(Throwable$error){$check($error instanceof $class,$label.' rejected with '.get_class($error));return;}throw new RuntimeException('Expected rejection: '.$label);};
try{
    foreach([$sharedName,$workflowName]as$name){$server->query('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$made[]=$name;}
    $shared=new PDO('mysql:host='.$host.';port='.$port.';dbname='.$sharedName.';charset=utf8mb4',$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $local=new mysqli($host,$user,$password,$workflowName,(int)$port);$local->set_charset('utf8mb4');database::$mysql=$local;
    $shared->exec('CREATE TABLE core_users(id CHAR(36) PRIMARY KEY,name VARCHAR(150),email VARCHAR(254),password_hash VARCHAR(255),blocked TINYINT DEFAULT 0,verified_at DATETIME NULL,is_admin TINYINT DEFAULT 0) ENGINE=InnoDB');
    $shared->exec('CREATE TABLE core_sessions(token_hash CHAR(64) PRIMARY KEY,user_id CHAR(36),expires_at DATETIME) ENGINE=InnoDB');
    $shared->exec("CREATE TABLE core_app_login_codes(code_hash CHAR(64) PRIMARY KEY,user_id CHAR(36),session_hash CHAR(64),state_hash CHAR(64),pkce_challenge CHAR(64),target VARCHAR(32) DEFAULT 'workflow',expires_at DATETIME,consumed_at DATETIME NULL) ENGINE=InnoDB");
    foreach([
        'CREATE TABLE users(id INT PRIMARY KEY AUTO_INCREMENT,username VARCHAR(50),email VARCHAR(225),password VARCHAR(225),user_role INT,hourSalary INT DEFAULT 0,lastLogin VARCHAR(30)) ENGINE=MyISAM',
        'CREATE TABLE privileges(userid INT,privilege VARCHAR(80)) ENGINE=MyISAM',
        'CREATE TABLE workgui_settings(setting VARCHAR(80),value VARCHAR(255)) ENGINE=InnoDB',
        'CREATE TABLE `query`(id INT PRIMARY KEY AUTO_INCREMENT,Name VARCHAR(255),Hostname TEXT,Info MEDIUMTEXT,status VARCHAR(30),admin VARCHAR(255),password VARCHAR(255),Prio VARCHAR(30),worktime INT,worker_name_id INT,date VARCHAR(30),messageToDev TEXT,Path VARCHAR(255),creator INT,contact_name VARCHAR(255),contact_org VARCHAR(255),contact_details TEXT) ENGINE=InnoDB',
        'CREATE TABLE steps(id INT PRIMARY KEY AUTO_INCREMENT,step INT,`desc` TEXT,orderId INT,creator INT,completed INT) ENGINE=InnoDB',
        'CREATE TABLE task_notifications(id INT PRIMARY KEY AUTO_INCREMENT,recipient_id INT,actor_id INT,order_id INT,event_type VARCHAR(80),detail TEXT) ENGINE=InnoDB',
        'CREATE TABLE remember_tokens(selector CHAR(32),user_id INT,token_hash CHAR(64),expires_at DATETIME) ENGINE=InnoDB'
    ]as$sql)$local->query($sql);
    $local->multi_query(file_get_contents(__DIR__.'/../migrations/20261024_centralen_integration.sql'));while($local->more_results()){$local->next_result();if($result=$local->store_result())$result->free();}
    $local->query("INSERT INTO workgui_settings VALUES('Maintenance','0')");
    $ids=[];foreach([1,2,3,4]as$n){$ids[$n]=sprintf('00000000-0000-4000-8000-%012d',$n);$query=$shared->prepare('INSERT INTO core_users(id,name,email,password_hash,verified_at,is_admin) VALUES(?,?,?,?,UTC_TIMESTAMP(),?)');$query->execute([$ids[$n],'Shared person '.$n,'shared'.$n.'@example.invalid',password_hash('Central-only-password',PASSWORD_DEFAULT),$n===3?1:0]);}
    $local->query("INSERT INTO users(id,username,email,password,user_role) VALUES(101,'Legacy one','legacy1@example.invalid','".md5('LegacyOne!')."',1),(102,'Legacy two','legacy2@example.invalid','".md5('LegacyTwo!')."',1),(103,'Legacy three','legacy3@example.invalid','".md5('LegacyThree!')."',1)");
    $session=hash('sha256','synthetic-core-session');$query=$shared->prepare('INSERT INTO core_sessions VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))');$query->execute([$session,$ids[1]]);
    $identity=new CentralenIdentity($shared,$local);$state=bin2hex(random_bytes(32));$verifier=bin2hex(random_bytes(32));
    $grant=static function(string$target='workflow')use($shared,$ids,$session,$state,$verifier):string{$code=bin2hex(random_bytes(32));$query=$shared->prepare('INSERT INTO core_app_login_codes VALUES(?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 60 SECOND),NULL)');$query->execute([hash('sha256',$code),$ids[1],$session,hash('sha256',$state),hash('sha256',$verifier),$target]);return$code;};
    $code=$grant();$reject(fn()=>$identity->consume($code,str_repeat('f',64),$verifier),'wrong state');$reject(fn()=>$identity->consume($code,$state,str_repeat('f',64)),'wrong PKCE');
    $accepted=$identity->consume($code,$state,$verifier);$check($accepted['user']['id']===$ids[1]&&$accepted['session_hash']===$session,'correct SSO identity');$check(!isset($accepted['user']['password_hash'],$accepted['user']['is_admin']),'auth hash and central admin not projected');$reject(fn()=>$identity->consume($code,$state,$verifier),'one-time code');
    $reject(fn()=>$identity->consume($grant('other'),$state,$verifier),'wrong audience');
    $expired=$grant();$shared->exec("UPDATE core_app_login_codes SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE) WHERE code_hash='".hash('sha256',$expired)."'");$reject(fn()=>$identity->consume($expired,$state,$verifier),'expired code');
    $blockedGrant=$grant();$shared->exec("UPDATE core_users SET blocked=1 WHERE id='{$ids[1]}'");$reject(fn()=>$identity->consume($blockedGrant,$state,$verifier),'code cannot restore blocked user');$shared->exec("UPDATE core_users SET blocked=0 WHERE id='{$ids[1]}'");
    $reject(fn()=>$identity->link($ids[1],$session,'legacy1@example.invalid','wrong'),'no email-only link');$check($identity->linked($ids[1])===null,'wrong credentials did not link');
    $linked=$identity->link($ids[1],$session,'Legacy one','LegacyOne!');$check($linked['userid']===101&&$linked['username']==='Shared person 1','ID retained and shared name authoritative');$check($linked['rank']===1,'local role retained');$check($identity->link($ids[1],$session,'Legacy one','LegacyOne!')['userid']===101,'same link exact retry');
    for($i=0;$i<2;$i++)$reject(fn()=>$identity->link($ids[1],$session,'Legacy one','wrong'),'persistent link attempt counted');
    $reject(fn()=>$identity->link($ids[1],$session,'Legacy one','LegacyOne!'),'new browser state cannot reset link rate limit');
    $local->query("UPDATE workflow_link_attempts SET window_started=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 16 MINUTE)");$check($identity->link($ids[1],$session,'Legacy one','LegacyOne!')['userid']===101,'expired link throttle window resets');
    putenv('WORKFLOW_URL_TEST=http://remote.invalid');$reject(fn()=>CentralenIdentity::url('WORKFLOW_URL_TEST'),'remote HTTP configuration rejected',RuntimeException::class);
    putenv('WORKFLOW_URL_TEST=https://userinfo@safe.invalid');$reject(fn()=>CentralenIdentity::url('WORKFLOW_URL_TEST'),'userinfo URL rejected',RuntimeException::class);putenv('WORKFLOW_URL_TEST');
    $local->query("INSERT INTO workflow_user_links VALUES('{$ids[2]}',102,UTC_TIMESTAMP()),('{$ids[3]}',103,UTC_TIMESTAMP())");
    $local->query("INSERT INTO privileges VALUES(101,'add_new_order'),(103,'all')");
    $validSession=['centralen_identity'=>['user_id'=>$ids[1],'session_hash'=>$session]];$check($identity->validateSession($validSession)['userid']===101,'protected request resolves canonical identity');
    $shared->exec("UPDATE core_users SET blocked=1 WHERE id='{$ids[1]}'");$check($identity->validateSession($validSession)===null,'blocked account rejected');$shared->exec("UPDATE core_users SET blocked=0,verified_at=NULL WHERE id='{$ids[1]}'");$check($identity->validateSession($validSession)===null,'unverified account rejected');$shared->exec("UPDATE core_users SET verified_at=UTC_TIMESTAMP() WHERE id='{$ids[1]}'");
    $shared->exec('DELETE FROM core_sessions');$check($identity->validateSession($validSession)===null,'logout/revoked central session rejected');
    // Recreate revoked session explicitly; the earlier prepared statement variable is not reused.
    $shared->exec("INSERT IGNORE INTO core_sessions VALUES('$session','{$ids[1]}',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");
    $service=new CentralenTasks($local,$identity,new main(),'http://localhost/websites/workflow2');$secret=str_repeat('a',64);
    $sessionMap=[$ids[1]=>$session];
    foreach([2,3,4]as$n){$sessionMap[$ids[$n]]=hash('sha256','session-'.$n);$query=$shared->prepare('INSERT IGNORE INTO core_sessions VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))');$query->execute([$sessionMap[$ids[$n]],$ids[$n]]);}
    $handle=static fn(string$action,array$payload):array=>$service->handle($action,$payload+['actor_session_hash'=>$sessionMap[$payload['actor_id']]??str_repeat('0',64)]);

    $context=$handle('context',['actor_id'=>$ids[1],'recipient_id'=>$ids[2]]);$check($context['can_create']&&$context['actor_linked']&&$context['recipient_linked'],'context permissions and links');
    $noRole=$handle('context',['actor_id'=>$ids[2],'recipient_id'=>$ids[1]]);$check(!$noRole['can_create'],'no implicit create permission');
    $local->query("UPDATE workgui_settings SET value='1' WHERE setting='Maintenance'");$check(!$handle('context',['actor_id'=>$ids[3],'recipient_id'=>$ids[2]])['can_create'],'all does not bypass maintenance');
    $reject(fn()=>$handle('operation',['actor_id'=>$ids[3],'request_id'=>$ids[4]]),'maintenance operation denied');
    $local->query("INSERT INTO privileges VALUES(103,'maintenanceLogin')");$check($handle('context',['actor_id'=>$ids[3],'recipient_id'=>$ids[2]])['can_create'],'explicit maintenance right works');$local->query("UPDATE workgui_settings SET value='0'");
    $envelope=['action'=>'context','timestamp'=>time(),'nonce'=>bin2hex(random_bytes(32)),'payload'=>['actor_id'=>$ids[1],'recipient_id'=>$ids[2]]];$raw=json_encode($envelope,JSON_THROW_ON_ERROR);$signature=hash_hmac('sha256',$raw,$secret);
    $reject(fn()=>$service->authenticate($raw,str_repeat('0',64),$secret),'bad HMAC');$check($service->authenticate($raw,$signature,$secret)['action']==='context','valid HMAC');$reject(fn()=>$service->authenticate($raw,$signature,$secret),'nonce replay');
    $old=$envelope;$old['timestamp']-=91;$rawOld=json_encode($old);$reject(fn()=>$service->authenticate($rawOld,hash_hmac('sha256',$rawOld,$secret),$secret),'expired request',InvalidArgumentException::class);
    $request='10000000-0000-4000-8000-000000000001';$payload=['actor_id'=>$ids[1],'recipient_id'=>$ids[2],'request_id'=>$request,'title'=>'Task " autofocus onfocus="alert(1)','description'=>"<img src=x onerror=alert(1)>\nPlain & text",'steps'=>['<svg onload=alert(1)>','Next step'],'priority'=>'asap'];
    $created=$handle('create',$payload);$check($created['id']>0&&$created['recipient']['id']===$ids[2],'created task recipient');$check($created['url']==='http://localhost/websites/workflow2/order.php?id='.$created['id'],'fixed task link');
    $task=$local->query('SELECT * FROM `query`')->fetch_assoc();$check((int)$task['creator']===101&&(int)$task['worker_name_id']===102,'canonical identities mapped to original task IDs');$check($task['Name']===$payload['title'],'title stays plain for escaped sinks');$check(!str_contains($task['Info'],'<img')&&str_contains($task['Info'],'&lt;img')&&str_contains($task['Info'],'<br'),'plain description safe for raw-rich-HTML sink');
    $notification=$local->query('SELECT * FROM task_notifications')->fetch_assoc();$check((int)$notification['actor_id']===101&&(int)$notification['recipient_id']===102,'notification explicit creator and receiver');$check((int)$local->query('SELECT COUNT(*) FROM steps')->fetch_row()[0]===2,'real checklist saved');
    $check($handle('create',$payload)==$created,'exact retry returns same result');$check((int)$local->query('SELECT COUNT(*) FROM `query`')->fetch_row()[0]===1,'retry created no duplicate task');$check((int)$local->query('SELECT COUNT(*) FROM task_notifications')->fetch_row()[0]===1,'retry created no duplicate notification');
    $check($handle('operation',['actor_id'=>$ids[1],'request_id'=>$request])==['found'=>true,'result'=>$created],'lost response operation recovery');$check($handle('operation',['actor_id'=>$ids[1],'request_id'=>$ids[4]])===['found'=>false],'explicit unknown request state');
    $other=$payload;$other['title']='Changed';$reject(fn()=>$handle('create',$other),'changed intent');$reject(fn()=>$handle('operation',['actor_id'=>$ids[3],'request_id'=>$request]),'other actor operation private');
    $local->query("DELETE FROM privileges WHERE userid=101");$reject(fn()=>$handle('create',$payload),'revoked rights deny exact retry');$local->query("INSERT INTO privileges VALUES(101,'add_new_order')");
    $other=$payload;$other['request_id']='10000000-0000-4000-8000-000000000002';$other['recipient_id']=$ids[4];$reject(fn()=>$handle('create',$other),'unlinked recipient denied');
    $local->query("CREATE TRIGGER fail_task_notification BEFORE INSERT ON task_notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SYNTHETIC rollback'");
    $other['recipient_id']=$ids[2];$reject(fn()=>$handle('create',$other),'notification rollback',RuntimeException::class);$check((int)$local->query('SELECT COUNT(*) FROM `query`')->fetch_row()[0]===1&&(int)$local->query('SELECT COUNT(*) FROM steps')->fetch_row()[0]===2&&(int)$local->query('SELECT COUNT(*) FROM workflow_task_operations')->fetch_row()[0]===1,'task/steps/idempotency rollback together');
    $local->query('DROP TRIGGER fail_task_notification');$check($handle('create',$other)['id']>$created['id'],'same request safely succeeds after rollback');
    // Canonical admin has no automatic role/privilege grant in Workflow.
    $check($identity->linked($ids[3])['rank']===1,'central admin does not grant Workflow admin');
    $source=file_get_contents(__DIR__.'/../php/classes/main.class.php');$check(substr_count($source,"htmlspecialchars($"."skriv['Name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')")===2,'both legacy editable title sinks encode attributes');
    require __DIR__.'/centralenProfiles.php';
    require __DIR__.'/centralenShellClient.php';
    if(in_array('--cgi',$argv,true))require __DIR__.'/centralenCgi.php';
    echo "Workflow/Centralen integration: $checks checks PASS; isolated databases, no app configuration, no emails.\n";
}finally{
    database::$mysql=null;
    if(isset($local))$local->close();
    foreach(array_reverse($made)as$name)$server->query('DROP DATABASE `'.$name.'`');$server->close();
}
