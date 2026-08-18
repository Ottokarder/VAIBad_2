<?php
/**
 * VAIBad 2 - Benutzer anlegen (TELEKOM Online-Version)
 *
 * Dieses Skript legt neue Benutzer in der Datenbank an.
 * Passwörter werden mit password_hash() (bcrypt) verschlüsselt
 * gespeichert und stehen NIE im Klartext in der Datenbank.
 *
 * SICHERHEITSHINWEIS:
 *  1. Tragen Sie unten in $neue_benutzer die gewünschten
 *     Benutzername/Passwort-Paare ein.
 *  2. Rufen Sie DIESE DATEI EINMALIG im Browser auf:
 *        https://schwimmen.foerderverein-enztalbad.de/benutzer_anlegen.php
 *  3. LÖSCHEN SIE DIE DATEI ANSCHLIESSEND VOM SERVER oder benennen
 *     Sie sie um (z.B. in benutzer_anlegen.php.bak), damit niemand
 *     unbefugt Benutzer anlegen kann.
 *
 * Dieses Skript benötigt KEIN Login, damit der allererste
 * Administrator angelegt werden kann. Bitte danach entfernen!
 */

require_once __DIR__ . '/config.php';

// ============================================================
// HIER DIE BENUTZER EINTRAGEN
// Format: 'benutzername' => 'passwort'
// ============================================================
$neue_benutzer = [
    // 'admin' => 'IhrSicheresPasswort!',
    // 'mitarbeiter1' => 'AnderesSicheresPasswort!',
];
// ============================================================

$ergebnisse = [];
$fehler = [];

if (!empty($neue_benutzer)) {
    foreach ($neue_benutzer as $bn => $pw) {
        $bn_trim = trim($bn);
        if ($bn_trim === '' || $pw === '') {
            $fehler[] = "Leerer Benutzername oder leeres Passwort übersprungen.";
            continue;
        }
        $hash = password_hash($pw, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            'INSERT INTO benutzer (benutzername, passwort_hash, aktiv) VALUES (?, ?, 1)'
        );
        $stmt->bind_param('ss', $bn_trim, $hash);
        if ($stmt->execute()) {
            $ergebnisse[] = "Benutzer '" . htmlspecialchars($bn_trim, ENT_QUOTES, 'UTF-8') . "' wurde angelegt (verschlüsselt).";
        } else {
            $fehler[] = "Fehler bei '" . htmlspecialchars($bn_trim, ENT_QUOTES, 'UTF-8') . "': " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8');
        }
        $stmt->close();
    }
}

// Passwörter nach dem Hashen aus dem Speicher werfen
unset($neue_benutzer);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VAIBad 2 - Benutzer anlegen</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Benutzer anlegen</h1>

        <?php if (!empty($ergebnisse)): ?>
            <div class="success-box">
                <?php foreach ($ergebnisse as $e): ?>
                    <p><?php echo $e; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($fehler)): ?>
            <div class="error-box">
                <ul>
                    <?php foreach ($fehler as $f): ?>
                        <li><?php echo $f; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="error-box">
            <strong>WICHTIG:</strong> Bitte löschen Sie diese Datei nach dem Anlegen
            der Benutzer vom Server oder benennen Sie sie um, damit niemand unbefugt
            weitere Benutzer anlegen kann.
        </div>

        <?php if (empty($ergebnisse) && empty($fehler)): ?>
            <p>Es sind keine Benutzer zum Anlegen eingetragen.</p>
            <p>Bitte tragen Sie die gewünschten Benutzer in der Datei
            <code>webapp/benutzer_anlegen.php</code> im Array
            <code>$neue_benutzer</code> ein und rufen Sie diese Seite erneut auf.</p>
        <?php endif; ?>

        <p><a href="/login.php" class="btn btn-secondary">Zum Login</a></p>
    </div>
</body>
</html>
