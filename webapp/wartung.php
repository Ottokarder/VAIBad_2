<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Seite für Wartungszwecke: Zeigt fehlende Zuordnungen an

// HTML-Header einbinden
if (file_exists('includes/header.php')) {
    include 'includes/header.php';
} else {
    echo '<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Wartung - Fehlende Zuordnungen - VAIBad</title>
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
}
?>
<div class="container">
    <h1>Wartung - Fehlende Zuordnungen</h1>
    <p>Diese Seite zeigt Daten, die einer Überprüfung bedürfen.</p>

    <div class="action-bar">
        <a href="/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <!-- Schwimmer ohne Schwimmleistung -->
    <h2 style="margin-top: 2rem;">Schwimmer ohne Schwimmleistung</h2>
    <?php
    $schwimmer_ohne_leistung = [];
    $res = $conn->query("
        SELECT id, startnummer, vorname, nachname
        FROM Schwimmer
        WHERE schwimmleistung_vormittag = 0 AND schwimmleistung_nachmittag = 0
        ORDER BY startnummer, nachname, vorname
    ");
    if ($res) { while ($r = $res->fetch_assoc()) $schwimmer_ohne_leistung[] = $r; $res->free(); }
    if (!empty($schwimmer_ohne_leistung)):
    ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Startnr.</th>
                    <th>Name</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schwimmer_ohne_leistung as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['startnummer']); ?></td>
                        <td><?php echo htmlspecialchars($r['vorname'] . ' ' . $r['nachname']); ?></td>
                        <td>
                            <a href="/bearbeiten_schwimmer.php?id=<?php echo $r['id']; ?>" class="btn btn-primary">Bearbeiten</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="success-box" style="margin: 1rem 0;">
            ✓ Alle Schwimmer haben Schwimmleistungen eingegeben.
        </div>
    <?php endif; ?>

    <!-- Sponsoren ohne Schwimmer-Zuordnung -->
    <h2 style="margin-top: 2rem;">Sponsoren ohne Schwimmer-Zuordnung</h2>
    <?php
    $sponsoren_ohne_schwimmer = [];
    $res = $conn->query("
        SELECT sp.id, sp.name
        FROM Sponsoren sp
        LEFT JOIN spenden_sponsoren ss ON sp.id = ss.sponsoren_id
        WHERE ss.sponsoren_id IS NULL
        ORDER BY sp.name
    ");
    if ($res) { while ($r = $res->fetch_assoc()) $sponsoren_ohne_schwimmer[] = $r; $res->free(); }
    if (!empty($sponsoren_ohne_schwimmer)):
    ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sponsor</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sponsoren_ohne_schwimmer as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td>
                            <a href="/bearbeiten_sponsor.php?id=<?php echo $r['id']; ?>" class="btn btn-primary">Bearbeiten</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="success-box" style="margin: 1rem 0;">
            ✓ Alle Sponsoren sind Schwimmern zugeordnet.
        </div>
    <?php endif; ?>

    <!-- Teams ohne Schwimmer-Zuordnung -->
    <h2 style="margin-top: 2rem;">Teams ohne Schwimmer-Zuordnung</h2>
    <?php
    $teams_ohne_schwimmer = [];
    $res = $conn->query("
        SELECT t.id, t.name
        FROM Teams t
        LEFT JOIN spenden_teams st ON t.id = st.team_id
        WHERE st.team_id IS NULL
        ORDER BY t.name
    ");
    if ($res) { while ($r = $res->fetch_assoc()) $teams_ohne_schwimmer[] = $r; $res->free(); }
    if (!empty($teams_ohne_schwimmer)):
    ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Team</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams_ohne_schwimmer as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td>
                            <a href="/bearbeiten_team.php?id=<?php echo $r['id']; ?>" class="btn btn-primary">Bearbeiten</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="success-box" style="margin: 1rem 0;">
            ✓ Alle Teams sind Schwimmern zugeordnet.
        </div>
    <?php endif; ?>

    <!-- Hauptsponsoren ohne Schwimmer-Zuordnung -->
    <h2 style="margin-top: 2rem;">Hauptsponsoren ohne Schwimmer-Zuordnung</h2>
    <?php
    $hauptsponsoren_ohne_schwimmer = [];
    $res = $conn->query("
        SELECT h.id, h.name
        FROM Hauptsponsoren h
        LEFT JOIN spenden_hauptsponsoren sh ON h.id = sh.hauptsponsor_id
        WHERE sh.hauptsponsor_id IS NULL
        ORDER BY h.name
    ");
    if ($res) { while ($r = $res->fetch_assoc()) $hauptsponsoren_ohne_schwimmer[] = $r; $res->free(); }
    if (!empty($hauptsponsoren_ohne_schwimmer)):
    ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Hauptsponsor</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hauptsponsoren_ohne_schwimmer as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td>
                            <a href="/bearbeiten_hauptsponsor.php?id=<?php echo $r['id']; ?>" class="btn btn-primary">Bearbeiten</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="success-box" style="margin: 1rem 0;">
            ✓ Alle Hauptsponsoren sind Schwimmern zugeordnet.
        </div>
    <?php endif; ?>
</div>
<?php
// HTML-Footer einbinden
if (file_exists('includes/footer.php')) {
    include 'includes/footer.php';
} else {
    echo '</body>
    </html>';
}
$conn->close();
?>