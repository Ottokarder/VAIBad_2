<?php
require_once __DIR__ . '/config.php';
// HTML-Header einbinden (liefert <html>/<head>, die <header>-Navigation und oeffnet <div class="container">).
// Login-Schutz laeuft bereits ueber config.php -> auth.php, VOR der ersten HTML-Ausgabe.
if (file_exists('includes/header.php')) {
    include 'includes/header.php';
} else {
    echo '<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>VAIBad 2 - Startseite</title>
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
    echo '<header class="site-header"><div class="header-container"><div class="logo"><a href="/index.php"><h1>VAIBad 2</h1></a></div><nav class="main-nav"><ul><li><a href="/index.php" class="active">Startseite</a></li><li><a href="/schwimmerliste.php">Schwimmer</a></li><li><a href="/sponsorenliste.php">Sponsoren</a></li><li><a href="/hauptsponsorenliste.php">Hauptsponsoren</a></li><li><a href="/teams.php">Teams</a></li><li><a href="/spenden_sponsoren.php">Spenden</a></li><li><a href="/auswertungen.php">Auswertungen</a></li><li><a href="/spendenlisten.php">Spendenlisten</a></li></ul></nav></div></header>';
    echo '<div class="container">';
}
?>

<!-- Hauptinhalt -->
    <div class="hero-section">
        <h2>Willkommen im Schwimmwettbewerb-Verwaltungssystem</h2>
        <p>Verwalten Sie hier einfach und übersichtlich die Teilnehmer, Sponsoren und Hauptsponsoren Ihres Schwimmwettbewerbs.</p>
    </div>

    <!-- Quick-Access-Karten -->
    <div class="dashboard">
        <div class="card">
            <h3>Teilnehmer</h3>
            <p>Verwalten Sie alle Schwimmer und ihre Leistungen.</p>
            <a href="/schwimmerliste.php" class="btn btn-primary">Zur Teilnehmerliste</a>
        </div>

        <div class="card">
            <h3>Sponsoren</h3>
            <p>Verwalten Sie alle Sponsoren und ihre Beiträge.</p>
            <a href="/sponsorenliste.php" class="btn btn-primary">Zur Sponsorenliste</a>
        </div>

        <div class="card">
            <h3>Hauptsponsoren</h3>
            <p>Verwalten Sie alle Hauptsponsoren und ihre Limits.</p>
            <a href="/hauptsponsorenliste.php" class="btn btn-primary">Zur Hauptsponsorenliste</a>
        </div>

        <div class="card">
            <h3>Teams</h3>
            <p>Verwalten Sie alle Teams, deren Spendensummen und Limits.</p>
            <a href="/teams.php" class="btn btn-primary">Zur Teamliste</a>
        </div>

        <div class="card">
            <h3>Spenden (Sponsoren)</h3>
            <p>Berechnen Sie die Spendenbeträge je Schwimmer und Sponsor.</p>
            <a href="/spendenberechnung.php" class="btn btn-primary">Spenden berechnen</a>
            <a href="/spenden_sponsoren.php" class="btn btn-secondary" style="margin-top:.5rem;">Ergebnisse ansehen</a>
        </div>
        <div class="card">
            <h3>Spenden (Teams)</h3>
            <p>Berechnen Sie die Team-Spendenbeträge je Schwimmer mit Limit-Deckelung.</p>
            <a href="/spendenberechnung_teams.php" class="btn btn-primary">Team-Spenden berechnen</a>
            <a href="/spenden_teams.php" class="btn btn-secondary" style="margin-top:.5rem;">Ergebnisse ansehen</a>
        </div>
        <div class="card">
            <h3>Spenden (Hauptsponsoren)</h3>
            <p>Jeder Hauptsponsor zahlt für jeden Schwimmer – mit Limit-Deckelung.</p>
            <a href="/spendenberechnung_hauptsponsoren.php" class="btn btn-primary">Hauptsponsor-Spenden berechnen</a>
            <a href="/spenden_hauptsponsoren.php" class="btn btn-secondary" style="margin-top:.5rem;">Ergebnisse ansehen</a>
        </div>
        <div class="card">
            <h3>Auswertungen</h3>
            <p>Top-Ten-Listen und Spendentabellen. Umschaltung Vormittag/Nachmittag/Gesamt auf der Auswertungsseite.</p>
            <a href="/auswertungen.php" class="btn btn-primary">Zu den Auswertungen</a>
        </div>
        <div class="card">
            <h3>Spendenlisten</h3>
            <p>Listen aller Sponsoren, Teams und Hauptsponsoren mit Einzelschwimmern und Beträgen.</p>
            <a href="/spendenlisten.php" class="btn btn-primary">Zu den Spendenlisten</a>
        </div>
    </div>
</div><!-- /.container -->

<?php
// HTML-Footer einbinden (schliesst <div class="container">, den Footer und <body>/<html>).
if (file_exists('includes/footer.php')) {
    include 'includes/footer.php';
} else {
    echo '<footer class="site-footer"><div class="footer-container"><p>&copy; ' . date('Y') . ' VAIBad 2. Alle Rechte vorbehalten.</p></div></footer>';
    echo '</body>
    </html>';
}
?>
