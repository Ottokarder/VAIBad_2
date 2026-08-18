<?php
/**
 * VAIBad 2 - Login-Seite (TELEKOM Online-Version)
 */
require_once __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bereits eingeloggt -> zur Startseite
if (!empty($_SESSION['benutzer_id'])) {
    header('Location: /index.php');
    exit;
}

require_once __DIR__ . '/config.php';

$login_fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!auth_verify_csrf_token($csrf)) {
        $login_fehler = 'Sitzung abgelaufen, bitte Seite neu laden.';
    } else {
        $benutzername = trim($_POST['benutzername'] ?? '');
        $passwort = $_POST['passwort'] ?? '';

        if ($benutzername === '' || $passwort === '') {
            $login_fehler = 'Bitte Benutzername und Passwort eingeben.';
        } else {
            $stmt = $conn->prepare(
                'SELECT id, benutzername, passwort_hash, aktiv FROM benutzer WHERE benutzername = ? LIMIT 1'
            );
            $stmt->bind_param('s', $benutzername);
            $stmt->execute();
            $res = $stmt->get_result();
            $benutzer = $res->fetch_assoc();
            $stmt->close();

            if (!$benutzer) {
                $login_fehler = 'Benutzername oder Passwort falsch.';
            } elseif ((int)$benutzer['aktiv'] !== 1) {
                $login_fehler = 'Dieser Benutzer ist deaktiviert.';
            } elseif (!password_verify($passwort, $benutzer['passwort_hash'])) {
                $login_fehler = 'Benutzername oder Passwort falsch.';
            } else {
                // Login erfolgreich
                session_regenerate_id(true);
                $_SESSION['benutzer_id'] = (int)$benutzer['id'];
                $_SESSION['benutzername'] = $benutzer['benutzername'];

                // Letzten Login speichern
                $upd = $conn->prepare('UPDATE benutzer SET letzter_login = NOW() WHERE id = ?');
                $uid = (int)$benutzer['id'];
                $upd->bind_param('i', $uid);
                $upd->execute();
                $upd->close();

                $redirect = $_SESSION['login_redirect'] ?? '/index.php';
                unset($_SESSION['login_redirect']);
                header('Location: ' . $redirect);
                exit;
            }
        }
    }
}

$csrf_token = auth_generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VAIBad 2 - Anmeldung</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <div class="login-logo">
                <h1>VAIBad 2</h1>
            </div>
            <h2>Anmeldung</h2>

            <?php if ($login_fehler !== ''): ?>
                <div class="error-box"><?php echo htmlspecialchars($login_fehler, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="/login.php" class="form login-form" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="benutzername">Benutzername</label>
                    <input type="text" id="benutzername" name="benutzername"
                           value="<?php echo htmlspecialchars($_POST['benutzername'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           autofocus required>
                </div>
                <div class="form-group">
                    <label for="passwort">Passwort</label>
                    <input type="password" id="passwort" name="passwort" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Anmelden</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
