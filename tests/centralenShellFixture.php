<?php
declare(strict_types=1);
// CLI-only: real shared header and Workflow body, synthetic identity, no bootstrap/DB.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
class CentralenIdentity { public static function enabled(): bool { return true; } public static function url(string $key): string { return $key==='CENTRALEN_URL'?'http://localhost/websites/centralen':'http://127.0.0.1/websites/workflow2'; } }
$core=dirname(__DIR__,2).'/centralen';
$messages=json_decode(file_get_contents($core.'/resources/site/style_one/lang/sv.json'),true,512,JSON_THROW_ON_ERROR);
$text=static fn(string $key): string=>htmlspecialchars($messages[$key]??$key,ENT_QUOTES,'UTF-8');
$escape=static fn(string $value): string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
$currentUser=['id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','name'=>'TEST Anna Andersson'];
$profilePhoto=['hasPhoto'=>false];$selectedApp=['nameKey'=>'workflow.title'];$isAdminWorkspace=false;
$appConfiguration=new class { public function required(string $key): string { return 'workflow-fixture'; } };
$canPermission=static fn(string $permission): bool=>true;
$shellProfileUrl='http://localhost/websites/centralen/?page=settings';
$basePath='/websites/centralen';$chatEndpoint=$chatWorkflowEndpoint='/websites/workflow2/centralen-shell-api.php';$chatCentralenUrl='http://localhost/websites/centralen/';
ob_start();require $core.'/src/includes/header.php';$header=ob_get_clean();
$centralenShellEnabled=true;$centralenShellBase='http://localhost/websites/centralen';
$centralenShellData=in_array('--failure',$argv,true)?null:['header_html'=>$header,'locale'=>'sv','messages'=>$messages,'palette'=>'ocean'];
$_SESSION=['csrf_token'=>'WF-SYNTHETIC-CSRF','user'=>['username'=>'TEST Anna','userid'=>123]];
require __DIR__.'/../php/includes/centralen-shell-head.php';
$auth=new class { public function hasRight(string $right): bool { return true; } };
$main=new class { public function getOrderNav(): void {} };
$mode=in_array('--admin',$argv,true)?'admin':(in_array('--priority',$argv,true)?'priority':'home');$source=file_get_contents(__DIR__.'/../'.['home'=>'home.php','admin'=>'admin/index.php','priority'=>'workflow.php'][$mode]);$start=strpos($source,['home'=>'<div class="container">','admin'=>'    <div class="adminShell">','priority'=>'<div class="workflowNotificationDock">'][$mode]);$end=strpos($source,'</body>');$body=substr($source,$start,$end-$start);
?>
<!doctype html><html lang="sv"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php foreach($mode==='admin'?['admin']:['app','general','orderModal','macDock','notificationsFocus','workflow'] as $css): ?><link rel="stylesheet" href="/websites/workflow2/ui/style/css/<?= $css ?>.css"><?php endforeach; ?>
<?php centralenShellHead(); ?></head><body class="app-shell centralen-workflow-shell"<?= centralenShellBodyAttributes() ?>>
<?php require __DIR__.'/../php/includes/centralen-shell-header.php'; ?>
<?php eval('?>'.$body); ?>
</body></html>
