<?php
require_once __DIR__.'/database.php';
require_once __DIR__.'/security.php';

function mc_b64url_encode(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
function mc_b64url_decode(string $raw): string|false {
    $pad = strlen($raw) % 4;
    if ($pad) $raw .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($raw, '-_', '+/'), true);
}
function mc_jwt_key(): string {
    $k=(string)getenv('JWT_SECRET');
    if(strlen($k)<32) throw new RuntimeException('JWT_SECRET ausente ou muito curto.');
    return $k;
}
function mc_jwt_encode(array $payload): string {
    $header=['alg'=>'HS256','typ'=>'JWT'];
    $h=mc_b64url_encode(json_encode($header,JSON_UNESCAPED_SLASHES));
    $p=mc_b64url_encode(json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $sig=hash_hmac('sha256', "$h.$p", mc_jwt_key(), true);
    return "$h.$p.".mc_b64url_encode($sig);
}
function mc_jwt_decode(string $jwt): ?array {
    $parts=explode('.',$jwt);
    if(count($parts)!==3) return null;
    [$h,$p,$s]=$parts;
    $sig=mc_b64url_decode($s);
    if($sig===false) return null;
    $expected=hash_hmac('sha256', "$h.$p", mc_jwt_key(), true);
    if(!hash_equals($expected,$sig)) return null;
    $payloadRaw=mc_b64url_decode($p);
    if($payloadRaw===false) return null;
    $payload=json_decode($payloadRaw,true);
    if(!is_array($payload)) return null;
    $now=time();
    if(isset($payload['nbf']) && (int)$payload['nbf']>$now+30) return null;
    if(!isset($payload['exp']) || (int)$payload['exp']<=$now) return null;
    if(empty($payload['id']) || empty($payload['sid'])) return null;
    return $payload;
}
function mc_cookie_options(int $expires): array {
    $https=((($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https')||(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'));
    $s=strtolower((string)(getenv('COOKIE_SECURE')?:''));
    if(in_array($s,['1','true','yes'],true))$https=true;
    if(in_array($s,['0','false','no'],true))$https=false;
    $o=['expires'=>$expires,'path'=>'/','httponly'=>true,'secure'=>$https,'samesite'=>'Lax'];
    $d=trim((string)(getenv('COOKIE_DOMAIN')?:''));if($d!=='')$o['domain']=$d;
    return $o;
}
function mc_issue_auth_cookie(array $u, ?PDO $pdo=null): string {
    $pdo=$pdo?:mc_pdo();mc_ensure_schema($pdo);
    $now=time();$ttl=60*60*24*30;$sid=mc_random_token(32);$sidHash=mc_token_hash($sid);
    $pdo->prepare("INSERT INTO metalcolor_sessions(token_hash,user_id,expires_at) VALUES(:h,:u,NOW()+INTERVAL '30 days')")->execute([':h'=>$sidHash,':u'=>(int)$u['id']]);
    $p=['iat'=>$now,'nbf'=>$now,'exp'=>$now+$ttl,'id'=>(int)$u['id'],'sid'=>$sid,'role'=>(string)($u['role']??'CUSTOMER')];
    $jwt=mc_jwt_encode($p);setcookie('metalcolor_auth',$jwt,mc_cookie_options($now+$ttl));$_COOKIE['metalcolor_auth']=$jwt;return $jwt;
}
function mc_clear_auth_cookie(?PDO $pdo=null): void {
    $t=$_COOKIE['metalcolor_auth']??'';
    if($t!=='' && $pdo){
        try{$d=mc_jwt_decode($t);if($d&&!empty($d['sid']))$pdo->prepare('UPDATE metalcolor_sessions SET revoked_at=NOW() WHERE token_hash=:h AND revoked_at IS NULL')->execute([':h'=>mc_token_hash((string)$d['sid'])]);}catch(Throwable $e){}
    }
    setcookie('metalcolor_auth','',mc_cookie_options(time()-3600));unset($_COOKIE['metalcolor_auth']);
}
function mc_current_user(?PDO $pdo=null): ?array {
    $t=$_COOKIE['metalcolor_auth']??'';if($t==='')return null;
    try{
        $d=mc_jwt_decode($t);if(!$d){mc_clear_auth_cookie();return null;}
        $pdo=$pdo?:mc_pdo();mc_ensure_schema($pdo);
        $st=$pdo->prepare("SELECT u.id,u.name,u.email,u.role,u.phone,u.cpf_cnpj FROM metalcolor_sessions s JOIN metalcolor_users u ON u.id=s.user_id WHERE s.token_hash=:h AND s.user_id=:uid AND s.revoked_at IS NULL AND s.expires_at>NOW() LIMIT 1");
        $st->execute([':h'=>mc_token_hash((string)$d['sid']),':uid'=>(int)$d['id']]);$u=$st->fetch();
        if(!$u){mc_clear_auth_cookie();return null;}return $u;
    }catch(Throwable $e){mc_clear_auth_cookie();return null;}
}
function mc_verify_password_and_upgrade(PDO $pdo,array $u,string $password): bool {
    $stored=(string)($u['password_hash']??'');
    if($stored!==''&&password_verify($password,$stored)){
        if(password_needs_rehash($stored,PASSWORD_DEFAULT)){$h=password_hash($password,PASSWORD_DEFAULT);$pdo->prepare('UPDATE metalcolor_users SET password_hash=:p,updated_at=NOW() WHERE id=:id')->execute([':p'=>$h,':id'=>(int)$u['id']]);}
        return true;
    }
    return false;
}
function mc_record_login(PDO $pdo,int $id): void { try{$pdo->prepare('INSERT INTO metalcolor_user_logins(user_id,ip) VALUES(:id,:ip)')->execute([':id'=>$id,':ip'=>mc_client_ip()]);}catch(Throwable $e){} }
function mc_is_admin(?array $u): bool { return $u && (($u['role']??'')==='ADMIN'); }
