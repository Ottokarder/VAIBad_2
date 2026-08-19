<?php
/**
 * VAIBad 2 - Authentifizierung (TELEKOM Online-Version)
 *
 * Wird in config.php (und includes/header.php) eingebunden und läuft damit
 * auf JEDER Seite als allererstes, vor jeglicher Logik oder HTML-Ausgabe.
 *
 * Prüft, ob ein Benutzer eingeloggt ist. Wenn nicht, wird er zum
 * Login weitergeleitet. Ausgenommen sind login.php, logout.php
 * und das Anlege-Skript benutzer_anlegen.php.
 */

// Session mit Cookie-Lifetime = 0 (Session-Cookie) - wird beim Schließen des Browsers gelöscht
session_set_cookie_params(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Funktionen für CSRF-Token (in Login-Formularen verwendet)
function auth_generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function auth_verify_csrf_token(?string $token): bool {
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Erlaubte Seiten ohne Login
$auth_public_pages = ['login.php', 'logout.php', 'benutzer_anlegen.php'];
$auth_current_page = basename($_SERVER['SCRIPT_NAME'] ?? '');

if (!in_array($auth_current_page, $auth_public_pages, true)) {
    if (empty($_SESSION['benutzer_id'])) {
        // Ziel-URL merken, um nach dem Login dorthin zurückzukehren
        $auth_ziel = $_SERVER['REQUEST_URI'] ?? '';
        if ($auth_ziel !== '') {
            $_SESSION['login_redirect'] = $auth_ziel;
        }
        header('Location: /login.php');
        exit;
    }
}
