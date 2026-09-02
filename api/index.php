<?php
require_once dirname(__DIR__).'/config/load_env.php';
require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/config/http.php';
require_once dirname(__DIR__).'/config/security.php';
require_once dirname(__DIR__).'/config/auth.php';
require_once dirname(__DIR__).'/config/catalog.php';
require_once dirname(__DIR__).'/config/money.php';
require_once dirname(__DIR__).'/config/shipping.php';
require_once dirname(__DIR__).'/config/asaas.php';
require_once dirname(__DIR__).'/config/correios.php';
require_once dirname(__DIR__).'/config/orders.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

// Configuração pública gerada no servidor. Segredos nunca são expostos.
if ($path === '/runtime-config.js') {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    $runtime = [
        'storeName' => (string)(getenv('STORE_NAME') ?: 'METAL COLOR'),
        'whatsappNumber' => preg_replace('/\D/', '', (string)(getenv('WHATSAPP_NUMBER') ?: '')),
        'products' => mc_products(),
    ];
    echo 'window.METALCOLOR_RUNTIME=' . json_encode($runtime, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . ';';
    exit;
}

// Igual à lógica do InvestigationZ: páginas públicas são PHP e passam pelo front controller.
if (!str_starts_with($path, '/api/')) {
    $root = realpath(dirname(__DIR__));
    $pagePath = $path;
    if ($pagePath === '/' || $pagePath === '') $pagePath = '/index.php';
    elseif (str_ends_with($pagePath, '/')) $pagePath .= 'index.php';
    elseif (!str_ends_with(strtolower($pagePath), '.php')) $pagePath .= '.php';

    $candidate = realpath($root . '/' . ltrim($pagePath, '/'));
    if ($candidate === false || !str_starts_with($candidate, $root . DIRECTORY_SEPARATOR) || !is_file($candidate) || strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) !== 'php') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo '404 - Página não encontrada';
        exit;
    }
    chdir(dirname($candidate));
    require $candidate;
    exit;
}

$pdo = mc_pdo();
mc_ensure_schema($pdo);

function cleanv($v,int $max=200): string { return mb_substr(trim((string)$v),0,$max); }
function digitsv($v,int $max=50): string { return substr(preg_replace('/\D/','',(string)$v),0,$max); }
function user_json(?array $u): ?array { if(!$u)return null;return ['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']]; }

try {
switch ($path) {
case '/api/health':
    mc_require_method('GET'); mc_json(200,['ok'=>true,'backend'=>'php-sgc-style','asaasEnvironment'=>getenv('ASAAS_ENVIRONMENT')?:'sandbox','shippingMode'=>getenv('SHIPPING_MODE')?:'demo','databaseConfigured'=>(bool)getenv('DATABASE_URL'),'asaasConfigured'=>(bool)getenv('ASAAS_API_KEY'),'splitConfigured'=>(bool)getenv('ASAAS_SPLIT_WALLET_YURI'),'melhorEnvioConfigured'=>(bool)getenv('MELHOR_ENVIO_TOKEN')]);

case '/api/auth/me':
    mc_require_method('GET'); mc_json(200,['user'=>user_json(mc_current_user($pdo))]);

case '/api/auth/register':
    mc_require_method('POST'); mc_same_origin(); $b=mc_body();$name=cleanv($b['name']??'',100);$email=strtolower(cleanv($b['email']??'',160));$password=(string)($b['password']??'');
    if(!mc_rate_limit($pdo,'register',6,3600,$email))mc_json(429,['error'=>'Muitas tentativas de cadastro. Tente novamente mais tarde.']);
    if(mb_strlen($name)<2||!mc_valid_email($email)||strlen($password)<10||strlen($password)>200)mc_json(400,['error'=>'Informe nome, e-mail válido e senha com pelo menos 10 caracteres.']);
    $st=$pdo->prepare("INSERT INTO metalcolor_users(name,email,password_hash,role,phone,cpf_cnpj) VALUES(:n,:e,:p,'CUSTOMER',:ph,:cpf) ON CONFLICT(email) DO NOTHING RETURNING id,name,email,role");$st->execute([':n'=>$name,':e'=>$email,':p'=>password_hash($password,PASSWORD_DEFAULT),':ph'=>cleanv($b['phone']??'',30),':cpf'=>cleanv($b['cpfCnpj']??'',30)]);$u=$st->fetch();if(!$u)mc_json(409,['error'=>'Já existe uma conta com este e-mail.']);mc_issue_auth_cookie($u);mc_record_login($pdo,(int)$u['id']);mc_json(201,['user'=>user_json($u)]);

case '/api/auth/login':
    mc_require_method('POST');mc_same_origin();mc_validate_production_secrets();$b=mc_body();$email=strtolower(cleanv($b['email']??'',160));$password=(string)($b['password']??'');if(!mc_rate_limit($pdo,'login',10,900,$email))mc_json(429,['error'=>'Muitas tentativas de login. Aguarde 15 minutos.']);
    $st=$pdo->prepare('SELECT id,name,email,role,password_hash FROM metalcolor_users WHERE LOWER(email)=LOWER(:e) LIMIT 1');$st->execute([':e'=>$email]);$u=$st->fetch();
    if(!$u && $email!=='' && $email===strtolower((string)getenv('ADMIN_EMAIL')) && hash_equals((string)getenv('ADMIN_PASSWORD'),$password)){$st=$pdo->prepare("INSERT INTO metalcolor_users(name,email,password_hash,role) VALUES(:n,:e,:p,'ADMIN') RETURNING id,name,email,role,password_hash");$st->execute([':n'=>(string)(getenv('ADMIN_NAME')?:'Metal Color'),':e'=>$email,':p'=>password_hash($password,PASSWORD_DEFAULT)]);$u=$st->fetch();}
    if(!$u||!mc_verify_password_and_upgrade($pdo,$u,$password))mc_json(401,['error'=>'E-mail ou senha inválidos.']);mc_issue_auth_cookie($u);mc_record_login($pdo,(int)$u['id']);mc_json(200,['user'=>user_json($u)]);

case '/api/auth/logout':
    mc_require_method('POST');mc_same_origin();mc_clear_auth_cookie();mc_json(200,['ok'=>true]);

case '/api/account/cart':
    $u=mc_current_user($pdo);if(!$u)mc_json(401,['error'=>'Faça login.']);
    if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){$st=$pdo->prepare('SELECT items,updated_at FROM metalcolor_saved_carts WHERE user_id=:id LIMIT 1');$st->execute([':id'=>$u['id']]);$r=$st->fetch();mc_json(200,['cart'=>$r?json_decode($r['items'],true):[],'updatedAt'=>$r['updated_at']??null]);}
    if(($_SERVER['REQUEST_METHOD']??'')==='POST'){mc_same_origin();$b=mc_body();$raw=$b['cart']??[];$cart=$raw?mc_normalize_cart($raw):[];$small=array_map(fn($i)=>['id'=>$i['id'],'option'=>$i['option'],'quantity'=>$i['quantity']],$cart);$st=$pdo->prepare("INSERT INTO metalcolor_saved_carts(user_id,items,updated_at) VALUES(:id,CAST(:items AS jsonb),NOW()) ON CONFLICT(user_id) DO UPDATE SET items=EXCLUDED.items,updated_at=NOW()");$st->execute([':id'=>$u['id'],':items'=>json_encode($small)]);mc_json(200,['ok'=>true]);}mc_json(405,['error'=>'Método não permitido.']);

case '/api/account/orders':
    mc_require_method('GET');$u=mc_current_user($pdo);if(!$u)mc_json(401,['error'=>'Faça login.']);$st=$pdo->prepare('SELECT id,status,fulfillment_status,payment_method,items,shipping,total_cents,tracking_code,tracking_carrier,created_at,updated_at FROM metalcolor_orders WHERE user_id=:id ORDER BY created_at DESC LIMIT 100');$st->execute([':id'=>$u['id']]);$rows=$st->fetchAll();foreach($rows as &$r){$r['items']=json_decode($r['items'],true)?:[];$r['shipping']=json_decode($r['shipping'],true)?:[];}mc_json(200,['orders'=>$rows]);

case '/api/admin/orders':
    mc_require_method('GET');$u=mc_current_user($pdo);if(!mc_is_admin($u))mc_json(403,['error'=>'Acesso restrito.']);$f=(string)($_GET['filter']??'');$where='';if($f==='pending')$where="WHERE status='PENDING'";elseif($f==='shipping')$where="WHERE status='PAID' AND fulfillment_status IN ('AWAITING_SHIPMENT','PREPARING')";elseif($f==='sent')$where="WHERE fulfillment_status='SHIPPED'";$rows=$pdo->query("SELECT * FROM metalcolor_orders $where ORDER BY created_at DESC LIMIT 200")->fetchAll();foreach($rows as &$r)$r=mc_decode_row($r);mc_json(200,['orders'=>$rows]);

case '/api/admin/users':
    mc_require_method('GET');$u=mc_current_user($pdo);if(!mc_is_admin($u))mc_json(403,['error'=>'Acesso restrito.']);$rows=$pdo->query("SELECT u.id,u.name,u.email,u.role,u.phone,u.created_at,COUNT(o.id)::int AS order_count,COALESCE(SUM(CASE WHEN o.status='PAID' THEN o.total_cents ELSE 0 END),0)::int AS paid_total_cents FROM metalcolor_users u LEFT JOIN metalcolor_orders o ON o.user_id=u.id GROUP BY u.id ORDER BY u.created_at DESC LIMIT 300")->fetchAll();mc_json(200,['users'=>$rows]);

case '/api/admin/update-order':
    mc_require_method('POST');mc_same_origin();$u=mc_current_user($pdo);if(!mc_is_admin($u))mc_json(403,['error'=>'Acesso restrito.']);if(!mc_rate_limit($pdo,'admin-update',120,600,(string)$u['id']))mc_json(429,['error'=>'Muitas alterações em pouco tempo.']);$b=mc_body();$id=cleanv($b['id']??'',80);$status=cleanv($b['fulfillmentStatus']??'',30);$tracking=strtoupper(cleanv($b['trackingCode']??'',40));$carrier=strtoupper(cleanv($b['trackingCarrier']??'CORREIOS',30));$allowed=['AWAITING_SHIPMENT','PREPARING','SHIPPED','DELIVERED','CANCELED'];if(!$id||!in_array($status,$allowed,true))mc_json(400,['error'=>'Dados inválidos.']);if($status==='SHIPPED'&&!mc_valid_tracking($tracking))mc_json(400,['error'=>'Informe um código de rastreio válido antes de marcar como enviado.']);$st=$pdo->prepare("UPDATE metalcolor_orders SET fulfillment_status=:s,tracking_code=:t,tracking_carrier=:c,shipped_at=CASE WHEN :s='SHIPPED' THEN COALESCE(shipped_at,NOW()) ELSE shipped_at END,delivered_at=CASE WHEN :s='DELIVERED' THEN NOW() ELSE delivered_at END,updated_at=NOW() WHERE id=:id RETURNING id,fulfillment_status,tracking_code");$st->execute([':s'=>$status,':t'=>$tracking?:null,':c'=>$carrier?:null,':id'=>$id]);$o=$st->fetch();if(!$o)mc_json(404,['error'=>'Pedido não encontrado.']);mc_json(200,['ok'=>true,'order'=>['id'=>$o['id'],'fulfillmentStatus'=>$o['fulfillment_status'],'trackingCode'=>$o['tracking_code']]]);

case '/api/shipping-quote':
    mc_require_method('POST');if(!mc_rate_limit($pdo,'shipping',60,600))mc_json(429,['error'=>'Muitas solicitações. Aguarde um pouco.']);$b=mc_body();$cart=mc_normalize_cart($b['cart']??[]);$cep=mc_clean_cep($b['cep']??'');if(strlen($cep)!==8)mc_json(400,['error'=>'Informe um CEP válido.']);$options=mc_shipping_quote($cart,$cep);if(!$options)mc_json(422,['error'=>'Nenhuma opção de frete disponível para esse CEP.']);mc_json(200,['options'=>$options,'mode'=>getenv('SHIPPING_MODE')?:'demo']);

case '/api/price-preview':
    mc_require_method('POST');if(!mc_rate_limit($pdo,'price-preview',120,600))mc_json(429,['error'=>'Muitas solicitações. Aguarde um pouco.']);$b=mc_body();$cart=mc_normalize_cart($b['cart']??[]);$ship=max(0,(int)round((float)($b['shippingCents']??0)));$sub=mc_subtotal($cart);$base=$sub+$ship;$pix=mc_payment_fee('PIX',$base);$card=mc_payment_fee('CREDIT_CARD',$base);mc_json(200,['subtotalCents'=>$sub,'shippingCents'=>$ship,'commissionCents'=>mc_commission($sub),'pixFeeCents'=>$pix,'pixTotalCents'=>$base+$pix,'cardFeeCents'=>$card,'cardTotalCents'=>$base+$card,'cardFeePassedToCustomer'=>$card>0]);

case '/api/create-checkout':
    mc_require_method('POST');mc_same_origin();mc_validate_production_secrets();if(!mc_rate_limit($pdo,'checkout',12,600))mc_json(429,['error'=>'Muitas tentativas de checkout. Aguarde alguns minutos.']);$b=mc_body();$cart=mc_normalize_cart($b['cart']??[]);$u=mc_current_user($pdo);$method=(($b['paymentMethod']??'')==='CREDIT_CARD')?'CREDIT_CARD':'PIX';$customer=['name'=>cleanv($b['customer']['name']??'',100),'cpfCnpj'=>digitsv($b['customer']['cpfCnpj']??'',20),'email'=>strtolower(cleanv($b['customer']['email']??'',120)),'phone'=>digitsv($b['customer']['phone']??'',20)];$address=['postalCode'=>substr(digitsv($b['address']['postalCode']??'',8),0,8),'address'=>cleanv($b['address']['address']??'',120),'addressNumber'=>cleanv($b['address']['addressNumber']??'',20),'complement'=>cleanv($b['address']['complement']??'',80),'province'=>cleanv($b['address']['province']??'',80),'cityName'=>cleanv($b['address']['cityName']??'',80),'cityIbge'=>(int)digitsv($b['address']['cityIbge']??'',12),'uf'=>strtoupper(cleanv($b['address']['uf']??'',2))];if(mb_strlen($customer['name'])<2||!mc_valid_cpf_cnpj($customer['cpfCnpj'])||!mc_valid_email($customer['email'])||strlen($address['postalCode'])!==8||!$address['address']||!$address['addressNumber']||!$address['province'])mc_json(400,['error'=>'Preencha nome, CPF/CNPJ válido, e-mail válido (se informado) e endereço completo.']);$shipping=$b['shipping']??[];$shippingId=cleanv($shipping['id']??'',80);if(!$shippingId||!cleanv($shipping['name']??'',120))mc_json(400,['error'=>'Selecione uma opção de frete válida.']);$quotes=mc_shipping_quote($cart,$address['postalCode']);$verified=null;foreach($quotes as $q)if((string)$q['id']===$shippingId){$verified=$q;break;}if(!$verified)mc_json(400,['error'=>'A opção de frete expirou. Calcule novamente.']);$ship=(int)$verified['priceCents'];$sub=mc_subtotal($cart);$min=(int)round((float)(getenv('MIN_ORDER_VALUE')?:0)*100);if($min&&$sub<$min)mc_json(400,['error'=>'Pedido mínimo de R$ '.number_format($min/100,2,',','.')]);$fee=mc_payment_fee($method,$sub+$ship);$commission=mc_commission($sub);$total=$sub+$ship+$fee;$orderId='MC-'.strtoupper(base_convert((string)time(),10,36)).'-'.strtoupper(bin2hex(random_bytes(4)));$token=mc_random_token();$splitWallet=cleanv(getenv('ASAAS_SPLIT_WALLET_YURI')?:'',100);$splitEnabled=$splitWallet!=='';$expire=max(10,min(1440,(int)(getenv('ASAAS_CHECKOUT_EXPIRE_MINUTES')?:30)));$st=$pdo->prepare("INSERT INTO metalcolor_orders(id,user_id,status,fulfillment_status,payment_method,customer,address,items,shipping,subtotal_cents,shipping_cents,payment_fee_cents,commission_cents,total_cents,split_enabled,checkout_expires_at,access_token_hash) VALUES(:id,:uid,'PENDING','AWAITING_PAYMENT',:pm,CAST(:customer AS jsonb),CAST(:address AS jsonb),CAST(:items AS jsonb),CAST(:shipping AS jsonb),:sub,:ship,:fee,:com,:total,:split,NOW()+make_interval(mins => :mins),:hash)");$st->execute([':id'=>$orderId,':uid'=>$u['id']??null,':pm'=>$method,':customer'=>json_encode($customer),':address'=>json_encode($address),':items'=>json_encode($cart),':shipping'=>json_encode($verified),':sub'=>$sub,':ship'=>$ship,':fee'=>$fee,':com'=>$commission,':total'=>$total,':split'=>($splitEnabled?'true':'false'),':mins'=>$expire,':hash'=>mc_token_hash($token)]);
    $base=mc_site_url();$cb='&token='.rawurlencode($token);$items=[];foreach($cart as $i)$items[]=['externalReference'=>$i['id'],'name'=>$i['name'],'description'=>'Opção: '.$i['option'],'quantity'=>$i['quantity'],'value'=>mc_brl($i['priceCents'])];$items[]=['name'=>'Frete','description'=>$verified['name'],'quantity'=>1,'value'=>mc_brl($ship)];if($fee)$items[]=['name'=>$method==='PIX'?'Taxa de processamento Pix':'Taxa de processamento do cartão','description'=>'Repasse configurável da taxa de pagamento','quantity'=>1,'value'=>mc_brl($fee)];$customerData=['name'=>$customer['name'],'cpfCnpj'=>$customer['cpfCnpj'],'postalCode'=>$address['postalCode'],'address'=>$address['address'],'addressNumber'=>$address['addressNumber'],'province'=>$address['province'],'city'=>$address['cityIbge']];if($customer['email'])$customerData['email']=$customer['email'];if($customer['phone'])$customerData['phone']=$customer['phone'];if($address['complement'])$customerData['complement']=$address['complement'];$payload=['billingTypes'=>[$method],'chargeTypes'=>['DETACHED'],'minutesToExpire'=>$expire,'externalReference'=>$orderId,'callback'=>['successUrl'=>$base.'/pedido/?order='.rawurlencode($orderId).$cb.'&return=success','cancelUrl'=>$base.'/pagamento/?order='.rawurlencode($orderId).$cb.'&return=cancel','expiredUrl'=>$base.'/pagamento/?order='.rawurlencode($orderId).$cb.'&return=expired'],'items'=>$items,'customerData'=>$customerData];if($splitEnabled)$payload['splits']=[['walletId'=>$splitWallet,'fixedValue'=>mc_brl($commission),'externalReference'=>$orderId.':YURI','description'=>'Comissão Yuri - 15% dos produtos']];$checkout=mc_asaas('/checkouts','POST',$payload);if(empty($checkout['id']))throw new RuntimeException('Asaas não retornou o identificador do Checkout.');$checkoutUrl=mc_checkout_url((string)$checkout['id']);$pdo->prepare('UPDATE metalcolor_orders SET checkout_id=:cid,checkout_url=:url,updated_at=NOW() WHERE id=:id')->execute([':cid'=>$checkout['id'],':url'=>$checkoutUrl,':id'=>$orderId]);mc_json(200,['orderId'=>$orderId,'orderAccessToken'=>$token,'checkoutId'=>$checkout['id'],'checkoutUrl'=>$checkoutUrl,'expiresInMinutes'=>$expire,'totals'=>['subtotalCents'=>$sub,'shippingCents'=>$ship,'paymentFeeCents'=>$fee,'commissionCents'=>$commission,'totalCents'=>$total],'splitEnabled'=>$splitEnabled]);

case '/api/order':
    mc_require_method('GET');if(!mc_rate_limit($pdo,'order-read',120,600))mc_json(429,['error'=>'Muitas consultas. Aguarde um pouco.']);$id=cleanv($_GET['id']??'',100);if(!$id)mc_json(400,['error'=>'Pedido não informado.']);$o=mc_get_order($pdo,$id);if(!$o)mc_json(404,['error'=>'Pedido não encontrado.']);$u=mc_current_user($pdo);$owner=!empty($o['user_id'])&&$u&&((int)$o['user_id']===(int)$u['id']);$admin=mc_is_admin($u);$sup=(string)($_GET['token']??'');$tokenOk=!empty($o['access_token_hash'])&&$sup!==''&&hash_equals($o['access_token_hash'],mc_token_hash($sup));if(!$owner&&!$admin&&!$tokenOk)mc_json($u?403:401,['error'=>'Acesso ao pedido não autorizado.']);mc_json(200,['id'=>$o['id'],'status'=>$o['status'],'fulfillmentStatus'=>$o['fulfillment_status'],'paymentMethod'=>$o['payment_method'],'customer'=>['name'=>$o['customer']['name']??''],'address'=>['postalCode'=>$o['address']['postalCode']??'','cityName'=>$o['address']['cityName']??'','uf'=>$o['address']['uf']??''],'items'=>$o['items'],'shipping'=>$o['shipping'],'subtotalCents'=>(int)$o['subtotal_cents'],'shippingCents'=>(int)$o['shipping_cents'],'paymentFeeCents'=>(int)$o['payment_fee_cents'],'totalCents'=>(int)$o['total_cents'],'splitEnabled'=>(bool)$o['split_enabled'],'trackingCode'=>$o['tracking_code'],'trackingCarrier'=>$o['tracking_carrier'],'checkoutExpiresAt'=>$o['checkout_expires_at'],'createdAt'=>$o['created_at'],'updatedAt'=>$o['updated_at']]);

case '/api/tracking/order':
    mc_require_method('GET');if(!mc_rate_limit($pdo,'tracking',60,600))mc_json(429,['error'=>'Muitas consultas de rastreio.']);$id=cleanv($_GET['id']??'',100);$o=mc_get_order($pdo,$id);if(!$o)mc_json(404,['error'=>'Pedido não encontrado.']);$u=mc_current_user($pdo);$owner=!empty($o['user_id'])&&$u&&((int)$o['user_id']===(int)$u['id']);$admin=mc_is_admin($u);$sup=(string)($_GET['token']??'');$tokenOk=!empty($o['access_token_hash'])&&$sup!==''&&hash_equals($o['access_token_hash'],mc_token_hash($sup));if(!$owner&&!$admin&&!$tokenOk)mc_json($u?403:401,['error'=>'Sem acesso a este pedido.']);if(!$o['tracking_code'])mc_json(200,['available'=>false,'fulfillmentStatus'=>$o['fulfillment_status']]);$code=preg_replace('/[^A-Z0-9-]/','',strtoupper($o['tracking_code']));$events=null;$source='LINK';if(($o['tracking_carrier']?:'CORREIOS')==='CORREIOS'&&getenv('CORREIOS_IDCORREIOS')&&getenv('CORREIOS_API_CODE')){try{$events=mc_correios_track($code);$source='CORREIOS_API';}catch(Throwable $e){error_log('tracking: '.$e->getMessage());}}mc_json(200,['available'=>true,'code'=>$code,'carrier'=>$o['tracking_carrier']?:'CORREIOS','source'=>$source,'events'=>$events,'externalUrl'=>'https://rastreamento.correios.com.br/app/index.php?objetos='.rawurlencode($code)]);

case '/api/webhooks/asaas':
    mc_require_method('POST');mc_validate_production_secrets();$expected=(string)getenv('ASAAS_WEBHOOK_TOKEN');$received=(string)($_SERVER['HTTP_ASAAS_ACCESS_TOKEN']??'');if(!$expected||!mc_safe_equal($expected,$received))mc_json(401,['error'=>'Webhook não autorizado.']);$b=mc_body();$eventId=cleanv($b['id']??'',200);$event=cleanv($b['event']??'',80);if(!$eventId||!$event)mc_json(400,['error'=>'Evento inválido.']);$checkoutId=cleanv($b['checkout']['id']??'',100);$externalReference=cleanv($b['checkout']['externalReference']??'',100);if($checkoutId){$o=mc_get_order_by_checkout($pdo,$checkoutId);if(!$o)mc_json(200,['ok'=>true,'ignored'=>'checkout desconhecido']);if($externalReference&&$externalReference!==$o['id'])mc_json(400,['error'=>'Referência externa divergente.']);}$st=$pdo->prepare('INSERT INTO metalcolor_webhook_events(id,event_type) VALUES(:id,:e) ON CONFLICT(id) DO NOTHING RETURNING id');$st->execute([':id'=>$eventId,':e'=>$event]);if(!$st->fetchColumn())mc_json(200,['ok'=>true,'duplicate'=>true]);if($checkoutId){$map=['CHECKOUT_PAID'=>'PAID','CHECKOUT_CANCELED'=>'CANCELED','CHECKOUT_EXPIRED'=>'EXPIRED','CHECKOUT_CREATED'=>'PENDING'];if(isset($map[$event])){$status=$map[$event];$ful=$status==='PAID'?'AWAITING_SHIPMENT':($status==='EXPIRED'?'PAYMENT_EXPIRED':($status==='CANCELED'?'CANCELED':'AWAITING_PAYMENT'));$sql='UPDATE metalcolor_orders SET status=:s,fulfillment_status=:f,updated_at=NOW() WHERE checkout_id=:cid';$p=[':s'=>$status,':f'=>$ful,':cid'=>$checkoutId];if($externalReference){$sql.=' AND id=:id';$p[':id']=$externalReference;}$pdo->prepare($sql)->execute($p);}}mc_json(200,['ok'=>true]);

default: mc_json(404,['error'=>'Endpoint não encontrado.']);
}
} catch (InvalidArgumentException $e) { mc_json(400,['error'=>$e->getMessage()]); }
catch (Throwable $e) { error_log('MetalColor API: '.$e->getMessage().' @ '.$path);$status=(int)$e->getCode();if($status<400||$status>599)$status=500;mc_json($status,['error'=>$status===500?'Erro interno do servidor.':$e->getMessage()]); }
