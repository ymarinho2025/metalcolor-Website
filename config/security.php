<?php
require_once __DIR__.'/database.php';
function mc_client_ip(): string {
    $candidates=[
        $_SERVER['HTTP_X_VERCEL_FORWARDED_FOR']??'',
        $_SERVER['HTTP_X_FORWARDED_FOR']??'',
        $_SERVER['REMOTE_ADDR']??''
    ];
    foreach($candidates as $raw){$ip=trim(explode(',',(string)$raw)[0]);if(filter_var($ip,FILTER_VALIDATE_IP))return substr($ip,0,45);}return 'unknown';
}
function mc_secret(): string {
    $v=(string)(getenv('RATE_LIMIT_SECRET')?:'');
    if(strlen($v)>=32)return $v;
    $prod=strtolower((string)(getenv('APP_ENV')?:getenv('VERCEL_ENV')?:''))==='production';
    if($prod)throw new RuntimeException('RATE_LIMIT_SECRET ausente ou muito curto.');
    return (string)(getenv('JWT_SECRET')?:'dev-only-change-me');
}
function mc_rate_limit(PDO $pdo,string $scope,int $limit=20,int $window=900,string $extra=''): bool {
    mc_ensure_schema($pdo);$key=hash_hmac('sha256',$scope.'|'.mc_client_ip().'|'.$extra,mc_secret());$bucket=(int)floor(time()/$window);
    $st=$pdo->prepare("INSERT INTO metalcolor_rate_limits(key_hash,bucket,hits,updated_at) VALUES(:k,:b,1,NOW()) ON CONFLICT(key_hash,bucket) DO UPDATE SET hits=metalcolor_rate_limits.hits+1,updated_at=NOW() RETURNING hits");
    $st->execute([':k'=>$key,':b'=>$bucket]);return (int)$st->fetchColumn()<=$limit;
}
function mc_safe_equal(?string $a,?string $b): bool { $a=(string)$a;$b=(string)$b;return $a!==''&&$b!==''&&strlen($a)===strlen($b)&&hash_equals($a,$b); }
function mc_random_token(int $bytes=32): string { return rtrim(strtr(base64_encode(random_bytes($bytes)),'+/','-_'),'='); }
function mc_token_hash(string $v): string { return hash('sha256',$v); }
function mc_valid_email(string $v,bool $allowEmpty=true): bool { if($v==='')return $allowEmpty;return strlen($v)<=160&&filter_var($v,FILTER_VALIDATE_EMAIL)!==false; }
function mc_valid_cpf_cnpj(string $v): bool {
    $d=preg_replace('/\D/','',$v);
    if(strlen($d)===11){if(preg_match('/^(\d)\1{10}$/',$d))return false;for($t=9;$t<11;$t++){$sum=0;for($i=0;$i<$t;$i++)$sum+=(int)$d[$i]*(($t+1)-$i);$digit=(10*($sum%11))%11;if($digit===10)$digit=0;if((int)$d[$t]!==$digit)return false;}return true;}
    if(strlen($d)===14){if(preg_match('/^(\d)\1{13}$/',$d))return false;$weights=[[5,4,3,2,9,8,7,6,5,4,3,2],[6,5,4,3,2,9,8,7,6,5,4,3,2]];for($round=0;$round<2;$round++){$len=12+$round;$sum=0;for($i=0;$i<$len;$i++)$sum+=(int)$d[$i]*$weights[$round][$i];$r=$sum%11;$digit=$r<2?0:11-$r;if((int)$d[$len]!==$digit)return false;}return true;}
    return false;
}
function mc_valid_tracking(string $v): bool { return preg_match('/^[A-Z0-9-]{5,40}$/',$v)===1; }
function mc_validate_production_secrets(): void {
    if(strtolower((string)(getenv('APP_ENV')?:getenv('VERCEL_ENV')?:''))!=='production')return;
    foreach(['RATE_LIMIT_SECRET','JWT_SECRET','ADMIN_PASSWORD'] as $n){$v=(string)getenv($n);if(strlen($v)<24||stripos($v,'troque-')!==false||stripos($v,'aleatorio')!==false||stripos($v,'dev-only')!==false)throw new RuntimeException("$n ausente ou fraco para produção.");}
    if(strtolower((string)(getenv('ASAAS_ENVIRONMENT')?:'sandbox'))==='production'){
        $api=(string)getenv('ASAAS_API_KEY');$web=(string)getenv('ASAAS_WEBHOOK_TOKEN');
        if(strlen($api)<20)throw new RuntimeException('ASAAS_API_KEY ausente para produção.');
        if(strlen($web)<32)throw new RuntimeException('ASAAS_WEBHOOK_TOKEN ausente ou fraco para produção.');
        if(strtolower((string)(getenv('SHIPPING_MODE')?:'demo'))==='demo')throw new RuntimeException('SHIPPING_MODE=demo não pode ser usado com Asaas em produção.');
    }
}
