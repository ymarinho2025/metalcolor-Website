<?php
function mc_json(int $status,array $body): never { http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, max-age=0');header('X-Content-Type-Options: nosniff');echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }
function mc_body(): array { $raw=file_get_contents('php://input');if(!$raw)return []; $d=json_decode($raw,true);return is_array($d)?$d:[]; }
function mc_require_method(string $method): void { if(($_SERVER['REQUEST_METHOD']??'GET')!==$method)mc_json(405,['error'=>'Método não permitido.']); }
function mc_site_url(): string { $v=rtrim((string)(getenv('PUBLIC_SITE_URL')?:''),'/');if($v!=='')return $v;$host=$_SERVER['HTTP_HOST']??'localhost';$proto=(($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https'||(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'))?'https':'http';return "$proto://$host"; }
function mc_same_origin(): void {
    $site=strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'')); if($site && !in_array($site,['same-origin','same-site','none'],true))mc_json(403,['error'=>'Origem da requisição não autorizada.']);
    $host=$_SERVER['HTTP_HOST']??'';$expected=mc_site_url();$check=function(string $url)use($host,$expected){$p=parse_url($url);if(!$p||empty($p['host']))return false;if($p['host']===$host)return true;$e=parse_url($expected);return !empty($e['host'])&&$p['host']===$e['host'];};
    $origin=(string)($_SERVER['HTTP_ORIGIN']??'');$ref=(string)($_SERVER['HTTP_REFERER']??''); if($origin && !$check($origin))mc_json(403,['error'=>'Origem da requisição não autorizada.']);if(!$origin&&$ref&&!$check($ref))mc_json(403,['error'=>'Origem da requisição não autorizada.']);
}
function mc_http_json(string $url,string $method='GET',array $headers=[],?array $payload=null,int $timeout=20): array {
    $ch=curl_init($url);$hdr=[];foreach($headers as $k=>$v)$hdr[]=$k.': '.$v;curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$hdr,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>8]);if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_SLASHES));$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);if($raw===false)throw new RuntimeException('Falha de comunicação externa: '.$err);$data=json_decode($raw,true);if(!is_array($data))$data=['raw'=>$raw];return ['status'=>$status,'data'=>$data];
}
