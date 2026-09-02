<?php
function mc_brl(int $cents): float { return round($cents/100,2); }
function mc_commission(int $subtotal): int { $r=(float)(getenv('COMMISSION_RATE')?:0.15);return (int)round($subtotal*$r); }
function mc_payment_fee(string $method,int $base): int { if($method==='CREDIT_CARD'){if(strtolower((string)(getenv('PASS_CARD_FEE_TO_CUSTOMER')?:'false'))!=='true')return 0;$pct=(float)(getenv('CARD_FEE_PERCENT')?:0);$fixed=(int)round((float)(getenv('CARD_FEE_FIXED')?:0)*100);if($pct<0||$pct>=1||$fixed<0)throw new RuntimeException('Configuração de taxa do cartão inválida.');$gross=(int)ceil(($base+$fixed)/(1-$pct));return max(0,$gross-$base);}if(strtolower((string)(getenv('PASS_PIX_FEE_TO_CUSTOMER')?:'false'))!=='true')return 0;return max(0,(int)round((float)(getenv('PIX_FEE_FIXED')?:0)*100)); }
