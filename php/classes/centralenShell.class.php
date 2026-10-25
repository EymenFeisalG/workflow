<?php
declare(strict_types=1);
require_once __DIR__.'/centralenIdentity.class.php';

final class CentralenShellException extends RuntimeException
{
    public function __construct(public readonly int $status,public readonly string $errorCode,string $message,public readonly array $details=[]){parent::__construct($message);}
}

/** Server-to-server transport. Neither signing key nor Core session enters browser data. */
final class CentralenShell
{
    private array $identity;
    public function __construct(private CentralenIdentity $centralenIdentity,private ?Closure $transport=null)
    {
        $this->identity=$_SESSION['centralen_identity']??[];
    }
    public function request(string $action,array $payload=[]): mixed
    {
        $actor=$this->identity['user_id']??'';$hash=$this->identity['session_hash']??'';
        if(!is_string($actor)||!is_string($hash)||!preg_match('/\A[a-f0-9]{64}\z/D',$hash)||!$this->centralenIdentity->user($actor,$hash))throw new CentralenShellException(401,'login_required','Logga in igen.');
        if(!in_array($action,['shell','chat','tasks'],true))throw new CentralenShellException(404,'not_found','Funktionen finns inte.');
        // Close the Workflow cookie session before Core may call Workflow's task API.
        // The in-memory values remain available to subsequent SSR includes.
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        $payload['actor_id']=$actor;$payload['actor_session_hash']=$hash;
        $body=json_encode(['audience'=>'centralen-shell-v1','action'=>$action,'timestamp'=>time(),'nonce'=>bin2hex(random_bytes(32)),'payload'=>$payload],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $secret=CentralenIdentity::setting('WORKFLOW_INTEGRATION_SECRET');
        if(!preg_match('/\A[a-f0-9]{64}\z/D',$secret))throw new CentralenShellException(503,'bridge_unavailable','Centralen kunde inte nås.');
        $signature=hash_hmac('sha256',$body,$secret);$url=CentralenIdentity::url('CENTRALEN_URL').'/src/functions/core/workflow-shell.php';
        $response=$this->transport?($this->transport)($url,$body,$signature):$this->send($url,$body,$signature);
        if(!is_array($response)||!is_int($response['status']??null)||!is_string($response['body']??null)||strlen($response['body'])>2097152)throw new CentralenShellException(503,'bridge_unavailable','Centralen kunde inte nås.');
        try{$decoded=json_decode($response['body'],true,64,JSON_THROW_ON_ERROR);}catch(JsonException){throw new CentralenShellException(503,'bridge_unavailable','Centralen kunde inte nås.');}
        if($response['status']!==200){$error=$decoded['error']??[];throw new CentralenShellException(in_array($response['status'],[400,401,403,404,405,409,413,415,422,429,503],true)?$response['status']:503,is_string($error['code']??null)?$error['code']:'bridge_unavailable',is_string($error['message']??null)?$error['message']:'Centralen kunde inte nås.',is_array($error['details']??null)?$error['details']:[]);}
        if(!is_array($decoded)||!array_key_exists('data',$decoded))throw new CentralenShellException(503,'bridge_unavailable','Centralen kunde inte nås.');
        return $decoded['data'];
    }
    private function send(string $url,string $body,string $signature): array
    {
        $curl=curl_init($url);$response='';
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Integration-Signature: '.$signature],CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($curl,$chunk)use(&$response):int{if(strlen($response)+strlen($chunk)>2097152)return 0;$response.=$chunk;return strlen($chunk);}]);
        try{if(curl_exec($curl)===false)throw new CentralenShellException(503,'bridge_unavailable','Centralen kunde inte nås. Kontrollera meddelandets status innan du försöker igen.');return ['status'=>(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'body'=>$response];}finally{curl_close($curl);}
    }
}
