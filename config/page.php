<?php
require_once __DIR__ . '/load_env.php';
require_once __DIR__ . '/auth.php';

/**
 * Inicialização comum das páginas, inspirada no InvestigationZ:
 * toda rota visual executa PHP antes de entregar o HTML.
 */
function mc_page_bootstrap(string $page = ''): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => strtolower((string)(getenv('COOKIE_SECURE') ?: 'true')) !== 'false',
            'use_strict_mode' => true,
        ]);
    }

    // Cabeçalhos defensivos também em páginas PHP, não só nas APIs.
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }

    // Proteções/atalhos de autenticação no servidor, como no InvestigationZ.
    $normalized = '/' . ltrim($page, '/');
    $needsUser = in_array($normalized, ['/conta/index.php'], true);
    $needsAdmin = in_array($normalized, ['/admin/index.php'], true);
    $guestOnly = in_array($normalized, ['/login/index.php', '/cadastro/index.php'], true);

    if (!$needsUser && !$needsAdmin && !$guestOnly) return;

    try {
        $pdo = mc_pdo();
        mc_ensure_schema($pdo);
        $user = mc_current_user($pdo);

        if ($needsAdmin && !mc_is_admin($user)) {
            header('Location: /login/');
            exit;
        }
        if ($needsUser && !$user) {
            header('Location: /login/');
            exit;
        }
        if ($guestOnly && $user) {
            header('Location: ' . (mc_is_admin($user) ? '/admin/' : '/conta/'));
            exit;
        }
    } catch (Throwable $e) {
        // Não vaza credenciais/DSN. Em páginas protegidas, falhar fechado.
        error_log('MetalColor page bootstrap: ' . $e->getMessage());
        if ($needsUser || $needsAdmin) {
            http_response_code(503);
            exit('Serviço temporariamente indisponível.');
        }
    }
}
