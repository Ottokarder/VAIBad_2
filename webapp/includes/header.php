<?php
/**
 * VAIBad 2 - Zentraler HTML-Header (TELEKOM Online-Version)
 *
 * Diese Datei wird von allen Seiten eingebunden, die den
 * file_exists('includes/header.php')-Fallback nutzen.
 * Sie stellt das gemeinsame HTML-Gerüst und die Navigation bereit.
 *
 * Der eigentliche Login-Schutz läuft bereits über config.php -> auth.php,
 * das VOR der ersten HTML-Ausgabe greift.
 */
require_once __DIR__ . '/../auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$aktueller_benutzer = $_SESSION['benutzername'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VAIBad 2</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="header-container">
        <div class="logo">
            <a href="/index.php">
                <h1>VAIBad 2</h1>
            </a>
        </div>
        <nav class="main-nav">
            <ul>
                <li><a href="/index.php">Startseite</a></li>
                <li><a href="/schwimmerliste.php">Schwimmer</a></li>
                <li><a href="/sponsorenliste.php">Sponsoren</a></li>
                <li><a href="/teams.php">Teams</a></li>
                <li><a href="/hauptsponsorenliste.php">Hauptsponsoren</a></li>
                <li><a href="/schwimmleistung_eingeben.php">Schwimmleistung eingeben</a></li>
                <li><a href="/auswertungen.php">Auswertungen</a></li>
                <li><a href="/spendenlisten.php">Spendenlisten</a></li>
                <li><a href="/spenden_sponsoren.php">Spenden (Schwimmer)</a></li>
                <?php if ($aktueller_benutzer !== ''): ?>
                <li class="nav-user">
                    <span class="nav-user-name"><?php echo htmlspecialchars($aktueller_benutzer, ENT_QUOTES, 'UTF-8'); ?></span>
                    <a href="/logout.php" class="nav-logout">Abmelden</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
<div class="container">
