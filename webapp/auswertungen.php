<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Übersicht / Auswertungen
//
// Top-Ten-Schwimmer getrennt nach Altersgruppen:
//   - über 14 Jahre (Alter > 14): 50m-Bahnen
//   - unter 14 Jahre (Alter <= 14): 25m-Bahnen
// je nach schwimmleistung_vormittag / _nachmittag / _gesamt.
// Alter = aktuelles Jahr - geburtsjahr.
//
// Top-Ten-Sponsoren, -Teams, -Hauptsponsoren nach Spendensumme (aus den
// Ergebnistabellen spenden_sponsoren / spenden_teams / spenden_hauptsponsoren).
// Dafür müssen die Spendenberechnungen vorher gelaufen sein.

$aktuelles_jahr = (int)date('Y');

// Hilfsfunktion: eine Top-10-Abfrage ausführen und als Array zurückgeben.
function hole_top10($conn, $sql) {
    $out = [];
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $out[] = $r;
        }
        $res->free();
    }
    return $out;
}

// --- Top-Ten Schwimmer über 14 (50m-Bahnen), nach Gesamt ---
$top10_ueber14 = hole_top10($conn, "
    SELECT s.startnummer, CONCAT(s.vorname, ' ', s.nachname) AS name,
           (" . $aktuelles_jahr . " - s.geburtsjahr) AS alter_jahre,
           s.schwimmleistung_vormittag, s.schwimmleistung_nachmittag, s.schwimmleistung_gesamt
    FROM Schwimmer s
    WHERE (" . $aktuelles_jahr . " - s.geburtsjahr) > 14
      AND s.schwimmleistung_gesamt > 0
    ORDER BY s.schwimmleistung_gesamt DESC, s.schwimmleistung_vormittag DESC, s.startnummer ASC
    LIMIT 10
");

// --- Top-Ten Schwimmer unter 14 (25m-Bahnen), nach Gesamt ---
$top10_unter14 = hole_top10($conn, "
    SELECT s.startnummer, CONCAT(s.vorname, ' ', s.nachname) AS name,
           (" . $aktuelles_jahr . " - s.geburtsjahr) AS alter_jahre,
           s.schwimmleistung_vormittag, s.schwimmleistung_nachmittag, s.schwimmleistung_gesamt
    FROM Schwimmer s
    WHERE (" . $aktuelles_jahr . " - s.geburtsjahr) <= 14
      AND s.schwimmleistung_gesamt > 0
    ORDER BY s.schwimmleistung_gesamt DESC, s.schwimmleistung_vormittag DESC, s.startnummer ASC
    LIMIT 10
");

// --- Top-Ten Sponsoren nach Spendensumme (ge deckelt aus spenden_sponsoren) ---
$top10_sponsoren = hole_top10($conn, "
    SELECT sp.name AS sponsor_name,
           SUM(ss.spendenbetrag_gesamt) AS summe_gesamt,
           SUM(ss.spendenbetrag_vormittag) AS summe_vormittag,
           SUM(ss.spendenbetrag_nachmittag) AS summe_nachmittag
    FROM spenden_sponsoren ss
    JOIN Sponsoren sp ON ss.sponsoren_id = sp.id
    GROUP BY sp.id, sp.name
    ORDER BY summe_gesamt DESC
    LIMIT 10
");

// --- Top-Ten Teams nach Spendensumme (ge deckelt aus spenden_teams) ---
$top10_teams = hole_top10($conn, "
    SELECT t.name AS team_name,
           SUM(st.spendenbetrag_gesamt) AS summe_gesamt,
           SUM(st.spendenbetrag_gedeckelt) AS summe_gedeckelt
    FROM spenden_teams st
    JOIN Teams t ON st.team_id = t.id
    GROUP BY t.id, t.name
    ORDER BY summe_gedeckelt DESC
    LIMIT 10
");

// --- Top-Ten Hauptsponsoren nach Spendensumme (ge deckelt aus spenden_hauptsponsoren) ---
$top10_hauptsponsoren = hole_top10($conn, "
    SELECT h.name AS hauptsponsor_name,
           SUM(sh.spendenbetrag_gesamt) AS summe_gesamt,
           SUM(sh.spendenbetrag_gedeckelt) AS summe_gedeckelt
    FROM spenden_hauptsponsoren sh
    JOIN Hauptsponsoren h ON sh.hauptsponsor_id = h.id
    GROUP BY h.id, h.name
    ORDER BY summe_gedeckelt DESC
    LIMIT 10
");

// --- Gesamt geschwommene Distanz in km ---
// Unter 14 Jahre: 25 m pro Bahn, ueber 14 Jahre: 50 m pro Bahn.
// Distanz (km) = Bahnen * Meter pro Bahn / 1000.
$distanz_unter14 = hole_top10($conn, "
    SELECT SUM(s.schwimmleistung_gesamt) AS bahnen_gesamt,
           SUM(s.schwimmleistung_vormittag) AS bahnen_vormittag,
           SUM(s.schwimmleistung_nachmittag) AS bahnen_nachmittag
    FROM Schwimmer s
    WHERE (" . $aktuelles_jahr . " - s.geburtsjahr) <= 14
");
$distanz_ueber14 = hole_top10($conn, "
    SELECT SUM(s.schwimmleistung_gesamt) AS bahnen_gesamt,
           SUM(s.schwimmleistung_vormittag) AS bahnen_vormittag,
           SUM(s.schwimmleistung_nachmittag) AS bahnen_nachmittag
    FROM Schwimmer s
    WHERE (" . $aktuelles_jahr . " - s.geburtsjahr) > 14
");

// Hilfsfunktion: Bahnen -> km, Metadaten je nach Altersgruppe.
function bahnen_zu_km($bahnen, $meter_pro_bahn) {
    return ($bahnen !== null && $bahnen > 0) ? ($bahnen * $meter_pro_bahn / 1000.0) : 0.0;
}

$m_unter14 = 25;
$m_ueber14 = 50;

$km_unter14_vormittag  = bahnen_zu_km($distanz_unter14 ? $distanz_unter14[0]['bahnen_vormittag'] : 0, $m_unter14);
$km_unter14_nachmittag = bahnen_zu_km($distanz_unter14 ? $distanz_unter14[0]['bahnen_nachmittag'] : 0, $m_unter14);
$km_unter14_gesamt     = bahnen_zu_km($distanz_unter14 ? $distanz_unter14[0]['bahnen_gesamt'] : 0, $m_unter14);

$km_ueber14_vormittag  = bahnen_zu_km($distanz_ueber14 ? $distanz_ueber14[0]['bahnen_vormittag'] : 0, $m_ueber14);
$km_ueber14_nachmittag = bahnen_zu_km($distanz_ueber14 ? $distanz_ueber14[0]['bahnen_nachmittag'] : 0, $m_ueber14);
$km_ueber14_gesamt     = bahnen_zu_km($distanz_ueber14 ? $distanz_ueber14[0]['bahnen_gesamt'] : 0, $m_ueber14);

$km_gesamt_vormittag  = $km_unter14_vormittag + $km_ueber14_vormittag;
$km_gesamt_nachmittag = $km_unter14_nachmittag + $km_ueber14_nachmittag;
$km_gesamt            = $km_unter14_gesamt + $km_ueber14_gesamt;

// CSV-Export: wenn ?export=csv, Datei direkt zum Download ausliefern.
// Trenner Semikolon + UTF-8-BOM, damit Excel die Datei mit Umlauten korrekt öffnet.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $dateiname = 'auswertung_top10_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8-BOM für Excel

    $fmt = function($v) { return ($v === null) ? '' : $v; };
    $euro = function($v) { return ($v === null) ? '' : number_format((float)$v, 2, ',', ''); };
    $km = function($v) { return number_format((float)$v, 3, ',', ''); };

    // Gesamt geschwommene Distanz in km
    fputcsv($out, ['Gesamt geschwommene Distanz (km)'], ';');
    fputcsv($out, ['Altersgruppe', 'm/Bahn', 'Vormittag (km)', 'Nachmittag (km)', 'Gesamt (km)'], ';');
    fputcsv($out, ['Unter 14 Jahre', '25', $km($km_unter14_vormittag), $km($km_unter14_nachmittag), $km($km_unter14_gesamt)], ';');
    fputcsv($out, ['Über 14 Jahre', '50', $km($km_ueber14_vormittag), $km($km_ueber14_nachmittag), $km($km_ueber14_gesamt)], ';');
    fputcsv($out, ['Gesamt', '', $km($km_gesamt_vormittag), $km($km_gesamt_nachmittag), $km($km_gesamt)], ';');
    fputcsv($out, [], ';');

    // Top-Ten Schwimmer über 14 (50m)
    fputcsv($out, ['Top-Ten Schwimmer über 14 Jahre (50m-Bahnen)'], ';');
    fputcsv($out, ['Platz', 'Startnr.', 'Schwimmer', 'Alter', 'Bahnen Vormittag', 'Bahnen Nachmittag', 'Bahnen Gesamt'], ';');
    $platz = 1;
    foreach ($top10_ueber14 as $r) {
        fputcsv($out, [
            $platz++, $fmt($r['startnummer']), $r['name'], $fmt($r['alter_jahre']),
            $fmt($r['schwimmleistung_vormittag']), $fmt($r['schwimmleistung_nachmittag']), $fmt($r['schwimmleistung_gesamt'])
        ], ';');
    }
    fputcsv($out, [], ';');

    // Top-Ten Schwimmer unter 14 (25m)
    fputcsv($out, ['Top-Ten Schwimmer unter 14 Jahre (25m-Bahnen)'], ';');
    fputcsv($out, ['Platz', 'Startnr.', 'Schwimmer', 'Alter', 'Bahnen Vormittag', 'Bahnen Nachmittag', 'Bahnen Gesamt'], ';');
    $platz = 1;
    foreach ($top10_unter14 as $r) {
        fputcsv($out, [
            $platz++, $fmt($r['startnummer']), $r['name'], $fmt($r['alter_jahre']),
            $fmt($r['schwimmleistung_vormittag']), $fmt($r['schwimmleistung_nachmittag']), $fmt($r['schwimmleistung_gesamt'])
        ], ';');
    }
    fputcsv($out, [], ';');

    // Top-Ten Sponsoren
    fputcsv($out, ['Top-Ten Sponsoren nach Spendensumme'], ';');
    fputcsv($out, ['Platz', 'Sponsor', 'Summe Vormittag', 'Summe Nachmittag', 'Summe Gesamt'], ';');
    $platz = 1;
    foreach ($top10_sponsoren as $r) {
        fputcsv($out, [
            $platz++, $fmt($r['sponsor_name']),
            $euro($r['summe_vormittag']), $euro($r['summe_nachmittag']), $euro($r['summe_gesamt'])
        ], ';');
    }
    fputcsv($out, [], ';');

    // Top-Ten Teams
    fputcsv($out, ['Top-Ten Teams nach Spendensumme'], ';');
    fputcsv($out, ['Platz', 'Team', 'Summe gesamt (ungekürzt)', 'Summe gedeckelt'], ';');
    $platz = 1;
    foreach ($top10_teams as $r) {
        fputcsv($out, [
            $platz++, $fmt($r['team_name']),
            $euro($r['summe_gesamt']), $euro($r['summe_gedeckelt'])
        ], ';');
    }
    fputcsv($out, [], ';');

    // Top-Ten Hauptsponsoren
    fputcsv($out, ['Top-Ten Hauptsponsoren nach Spendensumme'], ';');
    fputcsv($out, ['Platz', 'Hauptsponsor', 'Summe gesamt (ungekürzt)', 'Summe gedeckelt'], ';');
    $platz = 1;
    foreach ($top10_hauptsponsoren as $r) {
        fputcsv($out, [
            $platz++, $fmt($r['hauptsponsor_name']),
            $euro($r['summe_gesamt']), $euro($r['summe_gedeckelt'])
        ], ';');
    }

    fclose($out);
    $conn->close();
    exit;
}

// HTML-Header einbinden
if (file_exists('includes/header.php')) {
    include 'includes/header.php';
} else {
    echo '<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Auswertungen - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>
<div class="container">
    <h1>Auswertungen (Top Ten)</h1>

    <div class="action-bar">
        <a href="/VAIBad_2/webapp/auswertungen.php?export=csv" class="btn btn-primary">Als CSV herunterladen</a>
        <a href="/VAIBad_2/webapp/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <?php
    $hinweis = (empty($top10_sponsoren) || empty($top10_teams) || empty($top10_hauptsponsoren));
    if ($hinweis):
    ?>
        <div class="error-box" style="margin: 1rem 0;">
            Hinweis: Die Sponsoren-/Teams-/Hauptsponsoren-Top-Ten sind leer.
            Bitte zuerst die jeweiligen Spendenberechnungen durchführen:
            <a href="/VAIBad_2/webapp/spendenberechnung.php">Sponsoren</a>,
            <a href="/VAIBad_2/webapp/spendenberechnung_teams.php">Teams</a>,
            <a href="/VAIBad_2/webapp/spendenberechnung_hauptsponsoren.php">Hauptsponsoren</a>.
        </div>
    <?php endif; ?>

    <!-- Gesamt geschwommene Distanz in km -->
    <h2 style="margin-top: 2rem;">Gesamt geschwommene Distanz (km)</h2>
    <p>
        Unter 14 Jahre: 25&nbsp;m pro Bahn &middot; Über 14 Jahre: 50&nbsp;m pro Bahn.
    </p>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Altersgruppe</th>
                    <th>m pro Bahn</th>
                    <th>Vormittag (km)</th>
                    <th>Nachmittag (km)</th>
                    <th>Gesamt (km)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Unter 14 Jahre</td>
                    <td>25</td>
                    <td><?php echo number_format($km_unter14_vormittag, 3, ',', '.'); ?></td>
                    <td><?php echo number_format($km_unter14_nachmittag, 3, ',', '.'); ?></td>
                    <td><strong><?php echo number_format($km_unter14_gesamt, 3, ',', '.'); ?></strong></td>
                </tr>
                <tr>
                    <td>Über 14 Jahre</td>
                    <td>50</td>
                    <td><?php echo number_format($km_ueber14_vormittag, 3, ',', '.'); ?></td>
                    <td><?php echo number_format($km_ueber14_nachmittag, 3, ',', '.'); ?></td>
                    <td><strong><?php echo number_format($km_ueber14_gesamt, 3, ',', '.'); ?></strong></td>
                </tr>
                <tr style="font-weight: bold; background-color: #f0f0f0;">
                    <td colspan="2">Gesamt</td>
                    <td><?php echo number_format($km_gesamt_vormittag, 3, ',', '.'); ?></td>
                    <td><?php echo number_format($km_gesamt_nachmittag, 3, ',', '.'); ?></td>
                    <td><?php echo number_format($km_gesamt, 3, ',', '.'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Top-Ten Schwimmer über 14 (50m-Bahnen) -->
    <h2 style="margin-top: 2rem;">Top-Ten Schwimmer über 14 Jahre (50m-Bahnen)</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Platz</th>
                    <th>Startnr.</th>
                    <th>Schwimmer</th>
                    <th>Alter</th>
                    <th>Bahnen Vormittag</th>
                    <th>Bahnen Nachmittag</th>
                    <th>Bahnen Gesamt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($top10_ueber14)): ?>
                    <?php $platz = 1; foreach ($top10_ueber14 as $r): ?>
                        <tr>
                            <td><strong><?php echo $platz++; ?></strong></td>
                            <td><?php echo htmlspecialchars($r['startnummer']); ?></td>
                            <td><?php echo htmlspecialchars($r['name']); ?></td>
                            <td><?php echo htmlspecialchars($r['alter_jahre']); ?></td>
                            <td><?php echo htmlspecialchars($r['schwimmleistung_vormittag']); ?></td>
                            <td><?php echo htmlspecialchars($r['schwimmleistung_nachmittag']); ?></td>
                            <td><strong><?php echo htmlspecialchars($r['schwimmleistung_gesamt']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="no-data">Keine Schwimmer über 14 gefunden.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Top-Ten Schwimmer unter 14 (25m-Bahnen) -->
    <h2 style="margin-top: 2rem;">Top-Ten Schwimmer unter 14 Jahre (25m-Bahnen)</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Platz</th>
                    <th>Startnr.</th>
                    <th>Schwimmer</th>
                    <th>Alter</th>
                    <th>Bahnen Vormittag</th>
                    <th>Bahnen Nachmittag</th>
                    <th>Bahnen Gesamt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($top10_unter14)): ?>
                    <?php $platz = 1; foreach ($top10_unter14 as $r): ?>
                        <tr>
                            <td><strong><?php echo $platz++; ?></strong></td>
                            <td><?php echo htmlspecialchars($r['startnummer']); ?></td>
                            <td><?php echo htmlspecialchars($r['name']); ?></td>
                            <td><?php echo htmlspecialchars($r['alter_jahre']); ?></td>
                            <td><?php echo htmlspecialchars($r['schwimmleistung_vormittag']); ?></td>
                            <td><?php echo htmlspecialchars($r['schwimmleistung_nachmittag']); ?></td>
                            <td><strong><?php echo htmlspecialchars($r['schwimmleistung_gesamt']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="no-data">Keine Schwimmer unter 14 gefunden.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Top-Ten Sponsoren -->
    <h2 style="margin-top: 2rem;">Top-Ten Sponsoren nach Spendensumme</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Platz</th>
                    <th>Sponsor</th>
                    <th>Summe Vormittag</th>
                    <th>Summe Nachmittag</th>
                    <th>Summe Gesamt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($top10_sponsoren)): ?>
                    <?php $platz = 1; foreach ($top10_sponsoren as $r): ?>
                        <tr>
                            <td><strong><?php echo $platz++; ?></strong></td>
                            <td><?php echo htmlspecialchars($r['sponsor_name']); ?></td>
                            <td><?php echo number_format($r['summe_vormittag'], 2, ',', '.'); ?> €</td>
                            <td><?php echo number_format($r['summe_nachmittag'], 2, ',', '.'); ?> €</td>
                            <td><strong><?php echo number_format($r['summe_gesamt'], 2, ',', '.'); ?> €</strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="no-data">Noch keine Sponsoren-Spenden berechnet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Top-Ten Teams -->
    <h2 style="margin-top: 2rem;">Top-Ten Teams nach Spendensumme</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Platz</th>
                    <th>Team</th>
                    <th>Summe gesamt (ungekürzt)</th>
                    <th>Summe gedeckelt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($top10_teams)): ?>
                    <?php $platz = 1; foreach ($top10_teams as $r): ?>
                        <tr>
                            <td><strong><?php echo $platz++; ?></strong></td>
                            <td><?php echo htmlspecialchars($r['team_name']); ?></td>
                            <td><?php echo number_format($r['summe_gesamt'], 2, ',', '.'); ?> €</td>
                            <td><strong><?php echo number_format($r['summe_gedeckelt'], 2, ',', '.'); ?> €</strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="no-data">Noch keine Team-Spenden berechnet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Top-Ten Hauptsponsoren -->
    <h2 style="margin-top: 2rem;">Top-Ten Hauptsponsoren nach Spendensumme</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Platz</th>
                    <th>Hauptsponsor</th>
                    <th>Summe gesamt (ungekürzt)</th>
                    <th>Summe gedeckelt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($top10_hauptsponsoren)): ?>
                    <?php $platz = 1; foreach ($top10_hauptsponsoren as $r): ?>
                        <tr>
                            <td><strong><?php echo $platz++; ?></strong></td>
                            <td><?php echo htmlspecialchars($r['hauptsponsor_name']); ?></td>
                            <td><?php echo number_format($r['summe_gesamt'], 2, ',', '.'); ?> €</td>
                            <td><strong><?php echo number_format($r['summe_gedeckelt'], 2, ',', '.'); ?> €</strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="no-data">Noch keine Hauptsponsor-Spenden berechnet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
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
