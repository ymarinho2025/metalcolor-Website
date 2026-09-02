<?php
require_once __DIR__.'/database.php';
function mc_decode_row(array $r): array { foreach(['customer','address','items','shipping'] as $k)if(isset($r[$k])&&is_string($r[$k]))$r[$k]=json_decode($r[$k],true)?:[];return $r; }
function mc_get_order(PDO $pdo,string $id): ?array { mc_ensure_schema($pdo);$st=$pdo->prepare('SELECT * FROM metalcolor_orders WHERE id=:id LIMIT 1');$st->execute([':id'=>$id]);$r=$st->fetch();return $r?mc_decode_row($r):null; }
function mc_get_order_by_checkout(PDO $pdo,string $id): ?array { mc_ensure_schema($pdo);$st=$pdo->prepare('SELECT * FROM metalcolor_orders WHERE checkout_id=:id LIMIT 1');$st->execute([':id'=>$id]);$r=$st->fetch();return $r?mc_decode_row($r):null; }
