<?php
function mc_products(): array
{
    static $products = null;
    if ($products !== null) return $products;

    $file = dirname(__DIR__) . '/data/products.json';
    $raw = @file_get_contents($file);
    if ($raw === false) throw new RuntimeException('Catálogo de produtos não encontrado.');

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Catálogo de produtos inválido.');
    }

    foreach ($decoded as $id => $product) {
        if (!is_string($id) || $id === '' || !is_array($product)) throw new RuntimeException('Produto inválido no catálogo.');
        if (empty($product['name']) || !isset($product['priceCents']) || (int)$product['priceCents'] < 0) {
            throw new RuntimeException('Dados incompletos no produto: ' . $id);
        }
        if (!empty($product['options']) && !is_array($product['options'])) throw new RuntimeException('Opções inválidas no produto: ' . $id);
    }

    $products = $decoded;
    return $products;
}

function mc_normalize_cart($input): array
{
    if (!is_array($input) || count($input) === 0 || count($input) > 50) throw new InvalidArgumentException('Carrinho vazio ou inválido.');
    $products = mc_products();
    $out = [];
    foreach ($input as $raw) {
        if (!is_array($raw)) throw new InvalidArgumentException('Item do carrinho inválido.');
        $id = trim((string)($raw['id'] ?? ''));
        if (!isset($products[$id])) throw new InvalidArgumentException("Produto inválido: $id");
        $p = $products[$id];
        $q = max(1, min(100, (int)($raw['quantity'] ?? 1)));
        $opts = $p['options'] ?? [];
        $opt = substr((string)($raw['option'] ?? ($opts[0] ?? 'ÚNICO')), 0, 80);
        if ($opts && !in_array($opt, $opts, true)) throw new InvalidArgumentException('Opção inválida para ' . $p['name'] . '.');
        $out[] = [
            'id' => $id,
            'name' => (string)$p['name'],
            'quantity' => $q,
            'option' => $opt,
            'priceCents' => (int)$p['priceCents'],
            'image' => (string)($p['image'] ?? ''),
        ];
    }
    return $out;
}

function mc_subtotal(array $cart): int
{
    $sum = 0;
    foreach ($cart as $item) {
        $line = (int)$item['priceCents'] * (int)$item['quantity'];
        if ($line < 0 || $sum > PHP_INT_MAX - $line) throw new RuntimeException('Valor do carrinho inválido.');
        $sum += $line;
    }
    return $sum;
}
