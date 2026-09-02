-- Metal Color v8 auditado - PostgreSQL/Neon
-- O backend PHP também executa estas criações/migrações automaticamente.
CREATE TABLE IF NOT EXISTS metalcolor_users (
 id BIGSERIAL PRIMARY KEY,
 name TEXT NOT NULL,
 email TEXT UNIQUE NOT NULL,
 password_hash TEXT NOT NULL,
 role TEXT NOT NULL DEFAULT 'CUSTOMER',
 phone TEXT,
 cpf_cnpj TEXT,
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS metalcolor_orders (
 id TEXT PRIMARY KEY,
 user_id BIGINT REFERENCES metalcolor_users(id) ON DELETE SET NULL,
 status TEXT NOT NULL DEFAULT 'PENDING',
 fulfillment_status TEXT NOT NULL DEFAULT 'AWAITING_PAYMENT',
 payment_method TEXT,
 checkout_id TEXT,
 checkout_url TEXT,
 checkout_expires_at TIMESTAMPTZ,
 customer JSONB NOT NULL,
 address JSONB NOT NULL,
 items JSONB NOT NULL,
 shipping JSONB NOT NULL,
 subtotal_cents INTEGER NOT NULL,
 shipping_cents INTEGER NOT NULL,
 payment_fee_cents INTEGER NOT NULL DEFAULT 0,
 commission_cents INTEGER NOT NULL,
 total_cents INTEGER NOT NULL,
 split_enabled BOOLEAN NOT NULL DEFAULT FALSE,
 tracking_code TEXT,
 tracking_carrier TEXT,
 shipped_at TIMESTAMPTZ,
 delivered_at TIMESTAMPTZ,
 access_token_hash TEXT,
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


CREATE TABLE IF NOT EXISTS metalcolor_sessions (
 token_hash TEXT PRIMARY KEY,
 user_id BIGINT NOT NULL REFERENCES metalcolor_users(id) ON DELETE CASCADE,
 expires_at TIMESTAMPTZ NOT NULL,
 revoked_at TIMESTAMPTZ,
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_metal_sessions_user ON metalcolor_sessions(user_id,expires_at);

CREATE TABLE IF NOT EXISTS metalcolor_saved_carts (
 user_id BIGINT PRIMARY KEY REFERENCES metalcolor_users(id) ON DELETE CASCADE,
 items JSONB NOT NULL DEFAULT '[]'::jsonb,
 updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS metalcolor_webhook_events (
 id TEXT PRIMARY KEY,
 event_type TEXT NOT NULL,
 received_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS metalcolor_rate_limits (
 key_hash TEXT NOT NULL,
 bucket BIGINT NOT NULL,
 hits INTEGER NOT NULL DEFAULT 1,
 updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 PRIMARY KEY(key_hash,bucket)
);

CREATE TABLE IF NOT EXISTS metalcolor_user_logins (
 id BIGSERIAL PRIMARY KEY,
 user_id BIGINT REFERENCES metalcolor_users(id) ON DELETE CASCADE,
 ip VARCHAR(45),
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_metal_orders_user ON metalcolor_orders(user_id,created_at DESC);
CREATE INDEX IF NOT EXISTS idx_metal_orders_status ON metalcolor_orders(status,fulfillment_status,created_at DESC);
CREATE UNIQUE INDEX IF NOT EXISTS idx_metal_checkout_id ON metalcolor_orders(checkout_id) WHERE checkout_id IS NOT NULL;
