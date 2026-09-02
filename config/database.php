<?php
require_once __DIR__ . '/load_env.php';

function mc_database_url(): string {
    $url = getenv('DATABASE_URL');
    if (!$url) throw new RuntimeException('DATABASE_URL não configurada.');
    return trim($url);
}
function mc_parse_database_url(): array {
    $parsed = parse_url(mc_database_url());
    if ($parsed === false || empty($parsed['host']) || empty($parsed['user']) || empty($parsed['path'])) throw new RuntimeException('DATABASE_URL inválida.');
    $host=$parsed['host']; $port=$parsed['port']??5432; $user=urldecode($parsed['user']); $pass=isset($parsed['pass'])?urldecode($parsed['pass']):''; $db=ltrim($parsed['path'],'/');
    parse_str($parsed['query']??'', $query); $sslmode=$query['sslmode']??'require'; $endpoint=explode('.',$host)[0];
    if (!empty($query['options']) && str_starts_with($query['options'],'endpoint=')) $endpoint=substr($query['options'],strlen('endpoint='));
    return compact('host','port','user','pass','db','sslmode','endpoint');
}
function mc_make_pdo(string $dsn,string $user,string $password): PDO {
    return new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function mc_pdo(): PDO {
    static $pdo=null; if($pdo instanceof PDO)return $pdo; $c=mc_parse_database_url();
    $base="pgsql:host={$c['host']};port={$c['port']};dbname={$c['db']};sslmode={$c['sslmode']}"; $withEndpoint=$base.";options=endpoint={$c['endpoint']}";
    try { $pdo=mc_make_pdo($withEndpoint,$c['user'],$c['pass']); }
    catch(PDOException $e){
        if(str_contains($c['host'],'.neon.tech') && stripos($e->getMessage(),'Endpoint ID is not specified')!==false){$pdo=mc_make_pdo($base,$c['user'],'endpoint='.$c['endpoint'].'$'.$c['pass']);}
        else throw $e;
    }
    return $pdo;
}
function mc_ensure_schema(PDO $pdo): void {
    static $done=false;if($done)return;
    $sqls=[
"CREATE TABLE IF NOT EXISTS metalcolor_users (id BIGSERIAL PRIMARY KEY,name TEXT NOT NULL,email TEXT UNIQUE NOT NULL,password_hash TEXT NOT NULL,role TEXT NOT NULL DEFAULT 'CUSTOMER',phone TEXT,cpf_cnpj TEXT,created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW())",
"CREATE TABLE IF NOT EXISTS metalcolor_orders (id TEXT PRIMARY KEY,user_id BIGINT REFERENCES metalcolor_users(id) ON DELETE SET NULL,status TEXT NOT NULL DEFAULT 'PENDING',fulfillment_status TEXT NOT NULL DEFAULT 'AWAITING_PAYMENT',payment_method TEXT,checkout_id TEXT,checkout_url TEXT,checkout_expires_at TIMESTAMPTZ,customer JSONB NOT NULL,address JSONB NOT NULL,items JSONB NOT NULL,shipping JSONB NOT NULL,subtotal_cents INTEGER NOT NULL,shipping_cents INTEGER NOT NULL,payment_fee_cents INTEGER NOT NULL DEFAULT 0,commission_cents INTEGER NOT NULL,total_cents INTEGER NOT NULL,split_enabled BOOLEAN NOT NULL DEFAULT FALSE,tracking_code TEXT,tracking_carrier TEXT,shipped_at TIMESTAMPTZ,delivered_at TIMESTAMPTZ,access_token_hash TEXT,created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW())",
"ALTER TABLE metalcolor_orders ADD COLUMN IF NOT EXISTS access_token_hash TEXT",
"CREATE TABLE IF NOT EXISTS metalcolor_saved_carts (user_id BIGINT PRIMARY KEY REFERENCES metalcolor_users(id) ON DELETE CASCADE,items JSONB NOT NULL DEFAULT '[]'::jsonb,updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW())",
"CREATE TABLE IF NOT EXISTS metalcolor_webhook_events (id TEXT PRIMARY KEY,event_type TEXT NOT NULL,received_at TIMESTAMPTZ NOT NULL DEFAULT NOW())",
"CREATE TABLE IF NOT EXISTS metalcolor_rate_limits (key_hash TEXT NOT NULL,bucket BIGINT NOT NULL,hits INTEGER NOT NULL DEFAULT 1,updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),PRIMARY KEY(key_hash,bucket))",
"CREATE TABLE IF NOT EXISTS metalcolor_user_logins (id BIGSERIAL PRIMARY KEY,user_id BIGINT REFERENCES metalcolor_users(id) ON DELETE CASCADE,ip VARCHAR(45),created_at TIMESTAMPTZ NOT NULL DEFAULT NOW())",
"CREATE INDEX IF NOT EXISTS idx_metal_orders_user ON metalcolor_orders(user_id,created_at DESC)",
"CREATE INDEX IF NOT EXISTS idx_metal_orders_status ON metalcolor_orders(status,fulfillment_status,created_at DESC)",
"CREATE INDEX IF NOT EXISTS idx_metal_checkout_id ON metalcolor_orders(checkout_id)"
    ];
    foreach($sqls as $sql)$pdo->exec($sql); $done=true;
}
