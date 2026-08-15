<?php
// HTML-Header einbinden
if (file_exists('includes/header.php')) {
    include 'includes/header.php';
} else {
    echo '<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>VAIBad 2 - Startseite</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>

<!-- Hauptmenü (ähnlich wie foerderverein-enztalbad.de) -->
<header class="site-header">
    <div class="header-container">
        <div class="logo">
            <a href="/VAIBad_2/webapp/index.php">
                <h1>VAIBad 2</h1>
            </a>
        </div>
        <nav class="main-nav">
            <ul>
                <li><a href="/VAIBad_2/webapp/index.php" class="active">Startseite</a></li>
                <li><a href="/VAIBad_2/webapp/schwimmerliste.php">Schwimmer</a></li>
                <li><a href="/VAIBad_2/webapp/sponsorenliste.php">Sponsoren</a></li>
                <li><a href="/VAIBad_2/webapp/hauptsponsorenliste.php">Hauptsponsoren</a></li>
                <li><a href="/VAIBad_2/webapp/teams.php">Teams</a></li>
                <li><a href="#">Auswertungen</a></li>
            </ul>
        </nav>
    </div>
</header>

<!-- Hauptinhalt -->
<div class="container">
    <div class="hero-section">
        <h2>Willkommen im Schwimmwettbewerb-Verwaltungssystem</h2>
        <p>Verwalten Sie hier einfach und übersichtlich die Teilnehmer, Sponsoren und Hauptsponsoren Ihres Schwimmwettbewerbs.</p>
    </div>

    <!-- Quick-Access-Karten -->
    <div class="dashboard">
        <div class="card">
            <h3>Teilnehmer</h3>
            <p>Verwalten Sie alle Schwimmer und ihre Leistungen.</p>
            <a href="/VAIBad_2/webapp/schwimmerliste.php" class="btn btn-primary">Zur Teilnehmerliste</a>
        </div>

        <div class="card">
            <h3>Sponsoren</h3>
            <p>Verwalten Sie alle Sponsoren und ihre Beiträge.</p>
            <a href="/VAIBad_2/webapp/sponsorenliste.php" class="btn btn-primary">Zur Sponsorenliste</a>
        </div>

        <div class="card">
            <h3>Hauptsponsoren</h3>
            <p>Verwalten Sie alle Hauptsponsoren und ihre Limits.</p>
            <a href="/VAIBad_2/webapp/hauptsponsorenliste.php" class="btn btn-primary">Zur Hauptsponsorenliste</a>
        </div>

        <div class="card">
            <h3>Teams</h3>
            <p>Verwalten Sie alle Teams, deren Spendensummen und Limits.</p>
            <a href="/VAIBad_2/webapp/teams.php" class="btn btn-primary">Zur Teamliste</a>
        </div>

        <div class="card">
            <h3>Auswertungen</h3>
            <p>Sehen Sie sich Statistiken und Auswertungen an.</p>
            <a href="#" class="btn btn-primary">Zu den Auswertungen</a>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="site-footer">
    <div class="footer-container">
        <p>&copy; <?php echo date('Y'); ?> VAIBad 2. Alle Rechte vorbehalten.</p>
    </div>
</footer>

<?php
// HTML-Footer einbinden
if (file_exists('includes/footer.php')) {
    include 'includes/footer.php';
} else {
    echo '</body>
    </html>';
}
?>
