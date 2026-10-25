<?php
// Uses only the isolated databases created by centralenIntegration.php.
putenv('WORKFLOW_AUTO_PROVISION=1');
foreach([5,6,7,8,9]as$n){
    $ids[$n]=sprintf('00000000-0000-4000-8000-%012d',$n);$sessionMap[$ids[$n]]=hash('sha256','session-'.$n);
    $query=$shared->prepare('INSERT INTO core_users(id,name,email,password_hash,verified_at,is_admin) VALUES(?,?,?,?,UTC_TIMESTAMP(),?)');$query->execute([$ids[$n],'Shared person '.$n,'shared'.$n.'@example.invalid',password_hash('SyntheticCentralOnly',PASSWORD_DEFAULT),$n===5?1:0]);
    $query=$shared->prepare('INSERT INTO core_sessions VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))');$query->execute([$sessionMap[$ids[$n]],$ids[$n]]);
}
$handle=static fn(string$action,array$payload):array=>$service->handle($action,$payload+['actor_session_hash'=>$sessionMap[$payload['actor_id']]??str_repeat('0',64)]);
$counts=static fn():array=>[(int)$local->query('SELECT COUNT(*) FROM users')->fetch_row()[0],(int)$local->query('SELECT COUNT(*) FROM privileges')->fetch_row()[0],(int)$local->query('SELECT COUNT(*) FROM workflow_user_links')->fetch_row()[0]];
$before=$counts();$context=$handle('context',['actor_id'=>$ids[5],'recipient_id'=>$ids[6]]);
$check(!$context['actor_linked']&&!$context['recipient_linked']&&$context['actor_provisionable']&&$context['recipient_provisionable']&&$context['can_create'],'fresh Core users can receive ordinary profiles');$check($before===$counts(),'context creates no profiles/rights/links');
$check($handle('operation',['actor_id'=>$ids[5],'request_id'=>$ids[9]])===['found'=>false],'unmapped operation returns explicit not-found without provisioning');$check($before===$counts(),'operation creates no profiles/rights/links');
$reject(fn()=>$identity->provision($ids[5],str_repeat('0',64)),'fresh profile requires current actor session');$check($before===$counts(),'invalid session creates no profile');
$payload=['actor_id'=>$ids[5],'recipient_id'=>$ids[6],'request_id'=>'30000000-0000-4000-8000-000000000001','title'=>'Auto-provisioned task','description'=>'Plain','steps'=>['One'],'priority'=>'normal'];
$local->query("RENAME TABLE workflow_task_operations TO workflow_task_operations_unavailable");$reject(fn()=>$handle('create',$payload),'503 before automatic profile creation',RuntimeException::class);$check($before===$counts(),'pre-profile transport/server failure has no profile side effects');$local->query('RENAME TABLE workflow_task_operations_unavailable TO workflow_task_operations');$check($handle('operation',['actor_id'=>$ids[5],'request_id'=>$payload['request_id']])===['found'=>false],'unknown result before profile can be recovered as not found');
$invalid=$payload;$invalid['title']='';$reject(fn()=>$handle('create',$invalid),'invalid task never provisions',InvalidArgumentException::class);$check($before===$counts(),'validation precedes profile writes');
$local->query("UPDATE workgui_settings SET value='1'");$reject(fn()=>$handle('create',$payload),'maintenance prevents automatic-profile writes');$check($before===$counts(),'maintenance creates no profiles');$local->query("UPDATE workgui_settings SET value='0'");
$longName='Canonical '.str_repeat('n',100);$longEmail='shared.'.str_repeat('x',210).'@example.invalid';$query=$shared->prepare('UPDATE core_users SET name=?,email=? WHERE id=?');$query->execute([$longName,$longEmail,$ids[5]]);
$createdAuto=$handle('create',$payload);$actorAuto=$identity->linked($ids[5],$sessionMap[$ids[5]]);$recipientAuto=$identity->linked($ids[6]);
$check($actorAuto&&$recipientAuto&&$actorAuto['rank']===1&&$recipientAuto['rank']===1,'create POST provisions actor and offline recipient as ordinary workers');
$check($actorAuto['username']===$longName&&$actorAuto['email']===$longEmail,'full canonical name/email remain identity authority');
$profile=$local->query('SELECT * FROM users WHERE id='.(int)$actorAuto['userid'])->fetch_assoc();$check(mb_strlen($profile['username'])<=50&&str_ends_with($profile['email'],'@identity.invalid'),'legacy display-cache limits do not truncate canonical identity');
foreach([$actorAuto,$recipientAuto]as$profile){$rights=$local->query('SELECT privilege FROM privileges WHERE userid='.(int)$profile['userid'])->fetch_all(MYSQLI_ASSOC);$check(array_column($rights,'privilege')===['add_new_order'],'ordinary profile has exactly standard create right, never admin/all/maintenance');}
$after=$counts();$check($handle('create',$payload)==$createdAuto&&$after===$counts(),'exact task retry creates no additional profiles');$check($identity->provision($ids[5],$sessionMap[$ids[5]])['userid']===$actorAuto['userid']&&$after===$counts(),'profile repeat keeps original local ID');
$local->query('DELETE FROM privileges WHERE userid='.(int)$actorAuto['userid']);$check($handle('operation',['actor_id'=>$ids[5],'request_id'=>$payload['request_id']])==['found'=>true,'result'=>$createdAuto],'own committed response stays recoverable after create right revoked');$reject(fn()=>$handle('create',$payload),'revoked create right still prevents another write');$local->query("INSERT INTO privileges VALUES(".(int)$actorAuto['userid'].",'add_new_order')");
// A legacy match is a hint. It cannot grant that legacy account's ID/rights.
$local->query("INSERT INTO users(id,username,email,password,user_role) VALUES(1003,'Legacy hinted user','shared7@example.invalid','".md5('LegacyHintOnly')."',2)");$local->query("INSERT INTO privileges VALUES(1003,'admin'),(1003,'all'),(1003,'maintenanceLogin')");
$before=$counts();$legacyBefore=$local->query('SELECT * FROM users WHERE id=1003')->fetch_assoc();$legacyRightsBefore=$local->query('SELECT * FROM privileges WHERE userid=1003')->fetch_all(MYSQLI_ASSOC);
$hint=$handle('context',['actor_id'=>$ids[7],'recipient_id'=>$ids[6]]);$check($hint['actor_provisionable']&&!$hint['actor_linked']&&$hint['can_create'],'legacy email match never blocks ordinary Core access');
$recipientHint=$handle('context',['actor_id'=>$ids[1],'recipient_id'=>$ids[7]]);$check($recipientHint['recipient_provisionable']&&!$recipientHint['recipient_linked']&&$before===$counts(),'offline matched recipient is provisionable without context writes');
$matchedTask=$handle('create',['actor_id'=>$ids[1],'recipient_id'=>$ids[7],'request_id'=>'30000000-0000-4000-8000-000000000007','title'=>'Matched offline recipient','description'=>'No legacy gate','steps'=>[],'priority'=>'normal']);
$fresh=$identity->linked($ids[7]);$check($matchedTask['recipient']['id']===$ids[7]&&$fresh['userid']!==1003&&$fresh['rank']===1,'task POST automatically provisions matched offline recipient without legacy admin');
$check(array_column($local->query('SELECT privilege FROM privileges WHERE userid='.(int)$fresh['userid'])->fetch_all(MYSQLI_ASSOC),'privilege')===['add_new_order'],'matched profile receives only ordinary default right');
$check($local->query('SELECT * FROM users WHERE id=1003')->fetch_assoc()===$legacyBefore&&$local->query('SELECT * FROM privileges WHERE userid=1003')->fetch_all(MYSQLI_ASSOC)===$legacyRightsBefore,'legacy admin row and all rights remain byte-for-byte unchanged');
// Fresh profile, default right and link roll back together on a real write failure.
$before=$counts();$local->query("CREATE TRIGGER fail_profile_link BEFORE INSERT ON workflow_user_links FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SYNTHETIC profile rollback'");$reject(fn()=>$identity->provision($ids[8],$sessionMap[$ids[8]]),'atomic profile rollback',mysqli_sql_exception::class);$check($before===$counts(),'profile/right/link roll back together');$local->query('DROP TRIGGER fail_profile_link');
// Same UUID in two processes: only one local ID and one ordinary profile is committed.
$snippet=<<<'PHP'
require getenv('WF_TEST_ROOT').'/php/classes/centralenIdentity.class.php';
foreach(['WF_TEST_SHARED','WF_TEST_LOCAL']as$key)if(!preg_match('/\Acentralen_test_[a-z0-9_]+\z/D',getenv($key)))throw new RuntimeException('Unsafe test schema');
putenv('WORKFLOW_AUTO_PROVISION=1');
$p=new PDO('mysql:host='.getenv('CENTRALEN_TEST_DB_HOST').';port='.getenv('CENTRALEN_TEST_DB_PORT').';dbname='.getenv('WF_TEST_SHARED').';charset=utf8mb4',getenv('CENTRALEN_TEST_DB_USER'),getenv('CENTRALEN_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$m=new mysqli(getenv('CENTRALEN_TEST_DB_HOST'),getenv('CENTRALEN_TEST_DB_USER'),getenv('CENTRALEN_TEST_DB_PASSWORD'),getenv('WF_TEST_LOCAL'),(int)getenv('CENTRALEN_TEST_DB_PORT'));
echo json_encode((new CentralenIdentity($p,$m))->provision(getenv('WF_TEST_UUID'),getenv('WF_TEST_SESSION'))['userid']);
PHP;
$environment=getenv();$environment['WF_TEST_ROOT']=dirname(__DIR__);$environment['WF_TEST_SHARED']=$sharedName;$environment['WF_TEST_LOCAL']=$workflowName;$environment['WF_TEST_UUID']=$ids[8];$environment['WF_TEST_SESSION']=$sessionMap[$ids[8]];
$processes=[];for($i=0;$i<2;$i++){$process=proc_open([PHP_BINARY,'-r',$snippet],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$environment);if(!is_resource($process))throw new RuntimeException('Concurrent fixture process failed');fclose($pipes[0]);$processes[]=[$process,$pipes];}
$results=[];foreach($processes as[$process,$pipes]){$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('Concurrent profile fixture failed: '.$errors);$results[]=json_decode($output,true,8,JSON_THROW_ON_ERROR);}
$check($results[0]===$results[1]&&is_int($results[0]),'concurrent provisioning returns same integer ID');$check($counts()===[$before[0]+1,$before[1]+1,$before[2]+1],'concurrent profile has exactly one row/right/link');
// Canonical-only worker list; stale legacy profiles remain historical, never selectable.
foreach(['HOST'=>$host,'PORT'=>$port,'NAME'=>$sharedName,'USER'=>$user,'PASSWORD'=>$password]as$key=>$value)putenv('SHARED_USERS_DB_'.$key.'='.$value);putenv('CENTRALEN_INTEGRATION_ENABLED=1');
$workers=(new main())->getWorkersList();$workerIds=array_column($workers,'id');$check(!in_array(1003,$workerIds,true)&&in_array($actorAuto['userid'],$workerIds,true),'normal selector exposes only active canonical linked profiles');
$shared->exec("UPDATE core_users SET blocked=1 WHERE id='{$ids[6]}'");$check(!in_array($recipientAuto['userid'],array_column((new main())->getWorkersList(),'id'),true),'blocked shared user excluded from normal selector');$shared->exec("UPDATE core_users SET blocked=0 WHERE id='{$ids[6]}'");
$normal=(new main())->createOrderUnified(['order_title'=>'Forbidden legacy assignment','worker'=>1003],null);$check(!$normal['success'],'normal Workflow create rejects Workflow-only recipient');
$before=$counts();$shared->exec("UPDATE core_users SET blocked=1 WHERE id='{$ids[9]}'");$reject(fn()=>$identity->provision($ids[9],$sessionMap[$ids[9]]),'blocked Core profile denied');$check($before===$counts(),'blocked profile has no side effects');$shared->exec("UPDATE core_users SET blocked=0 WHERE id='{$ids[9]}'");
