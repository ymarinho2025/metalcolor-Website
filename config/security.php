<?php
require_once __DIR__.'/database.php';
function mc_client_ip(): string { $x=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??''))[0]);return substr($x?:($_SERVER['REMOTE_ADDR']??'unknown'),0,45); }
function mc_secret(): string { return (string)(getenv('RATE_LIMIT_SECRET')?:getenv('ASAAS_WEBHOOK_TOKEN')?:getenv('JWT_SECRET')?:'dev-only-change-me'); }
function mc_rate_limit(PDO $pdo,string $scope,int $limit=20,int $window=900,string $extra=''): bool { mc_ensure_schema($pdo);$key=hash_hmac('sha256',$scope.'|'.mc_client_ip().'|'.$extra,mc_secret());$bucket=(int)floor(time()/$window);$st=$pdo->prepare("INSERT INTO metalcolor_rate_limits(key_hash,bucket,hits,updated_at) VALUES(:k,:b,1,NOW()) ON CONFLICT(key_hash,bucket) DO UPDATE SET hits=metalcolor_rate_limits.hits+1,updated_at=NOW() RETURNING hits");$st->execute([':k'=>$key,':b'=>$bucket]);return (int)$st->fetchColumn()<=$limit; }
function mc_safe_equal(?string $a,?string $b): bool { $a=(string)$a;$b=(string)$b;return $a!==''&&$b!==''&&strlen($a)===strlen($b)&&hash_equals($a,$b); }
function mc_random_token(int $bytes=32): string { return rtrim(strtr(base64_encode(random_bytes($bytes)),'+/','-_'),'='); }
function mc_token_hash(string $v): string { return hash('sha256',$v); }
function mc_valid_email(string $v): bool { return $v===''||(strlen($v)<=160&&filter_var($v,FILTER_VALIDATE_EMAIL)!==false); }
function mc_valid_cpf_cnpj(string $v): bool { $d=preg_replace('/\D/','',$v);return strlen($d)===11||strlen($d)===14; }
function mc_valid_tracking(string $v): bool { return preg_match('/^[A-Z0-9-]{5,40}$/',$v)===1; }
function mc_validate_production_secrets(): void { if(strtolower((string)(getenv('APP_ENV')?:getenv('VERCEL_ENV')?:''))!=='production')return;foreach(['ASAAS_WEBHOOK_TOKEN','RATE_LIMIT_SECRET','JWT_SECRET','ADMIN_PASSWORD'] as $n){$v=(string)getenv($n);if(strlen($v)<16||str_contains($v,'troque-')||str_contains($v,'dev-only'))throw new RuntimeException("$n ausente ou fraco para produção.");} }
