<?php
function mc_brl(int $cents): float { return round($cents / 100, 2); }

function mc_commission_rate(): float
{
    $raw = getenv('COMMISSION_RATE');
    $rate = ($raw === false || $raw === '') ? 0.15 : (float)$raw;
    if (!is_finite($rate) || $rate < 0 || $rate > 1) {
        throw new RuntimeException('COMMISSION_RATE inválida. Use um valor entre 0 e 1, por exemplo 0.15 para 15%.');
    }
    return $rate;
}

function mc_commission(int $subtotal): int
{
    return (int)round($subtotal * mc_commission_rate());
}

function mc_payment_fee(string $method, int $base): int
{
    if ($base < 0) throw new RuntimeException('Base de cálculo inválida.');

    if ($method === 'CREDIT_CARD') {
        if (strtolower((string)(getenv('PASS_CARD_FEE_TO_CUSTOMER') ?: 'false')) !== 'true') return 0;
        $pct = (float)(getenv('CARD_FEE_PERCENT') ?: 0);
        $fixed = (int)round((float)(getenv('CARD_FEE_FIXED') ?: 0) * 100);
        if (!is_finite($pct) || $pct < 0 || $pct >= 1 || $fixed < 0) {
            throw new RuntimeException('Configuração de taxa do cartão inválida.');
        }
        $gross = (int)ceil(($base + $fixed) / (1 - $pct));
        return max(0, $gross - $base);
    }

    if (strtolower((string)(getenv('PASS_PIX_FEE_TO_CUSTOMER') ?: 'false')) !== 'true') return 0;
    $fixed = (float)(getenv('PIX_FEE_FIXED') ?: 0);
    if (!is_finite($fixed) || $fixed < 0) throw new RuntimeException('Configuração de taxa Pix inválida.');
    return (int)round($fixed * 100);
}
