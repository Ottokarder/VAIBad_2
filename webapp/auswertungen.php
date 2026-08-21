<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Automatische Spendenberechnung ausführen
if (file_exists(__DIR__ . '/includes/auto_berechnen.php')) {
    require_once __DIR__ . '/includes/auto_berechnen.php';
}

// Auswertungen je Durchlauf: Vormittag, Nachmittag oder Gesamt.
// ?durchlauf=vormittag|nachmittag|gesamt  (Default: gesamt)
$durchlauf = isset($_GET['durchlauf']) ? $_GET['durchlauf'] : 'gesamt';
if (!in_array($durchlauf, ['vormittag', 'nachmittag', 'gesamt'], true)) {
    $durchlauf = 'gesamt';
}

// Zu verwendende Schwimmleistungsspalte und -label je Durchlauf.
if ($durchlauf === 'vormittag') {
    $leistung_spalte = 's.schwimmleistung_vormittag';
    $leistung_label = 'Bahnen Vormittag';
    $spenden_spalte_sponsoren = 'ss.spendenbetrag_vormittag';
    $spenden_spalte_teams = 'st.spendenbetrag_vormittag';
    $spenden_spalte_hauptsponsoren = 'sh.spendenbetrag_vormittag';
    // Bei der Distanzberechnung: nur den jeweiligen Durchlauf.
    $distanz_spalte_v = 's.schwimmleistung_vormittag';
    $distanz_spalte_n = 's.schwimmleistung_vormittag'; // für "Gesamt-Spalte" = Vormittag
    $titel = 'Auswertungen – Vormittag';
} elseif ($durchlauf === 'nachmittag') {
    $leistung_spalte = 's.schwimmleistung_nachmittag';
    $leistung_label = 'Bahnen Nachmittag';
    $spenden_spalte_sponsoren = 'ss.spendenbetrag_nachmittag';
    $spenden_spalte_teams = 'st.spendenbetrag_nachmittag';
    $spenden_spalte_hauptsponsoren = 'sh.spendenbetrag_nachmittag';
    $distanz_spalte_v = 's.schwimmleistung_nachmittag';
    $distanz_spalte_n = 's.schwimmleistung_nachmittag';
    $titel = 'Auswertungen – Nachmittag';
} else {
    $leistung_spalte = 's.schwimmleistung_gesamt';
    $leistung_label = 'Bahnen Gesamt';
    $spenden_spalte_sponsoren = 'ss.spendenbetrag_gesamt';
    $spenden_spalte_teams = 'st.spendenbetrag_gesamt';
    $spenden_spalte_hauptsponsoren = 'sh.spendenbetrag_gesamt';
    $distanz_spalte_v = 's.schwimmleistung_vormittag';
    $distanz_spalte_n = 's.schwimmleistung_nachmittag';
    $titel = 'Auswertungen – Gesamt';
}

$aktuelles_jahr = (int)date('Y');

// Hilfsfunktion: eine Abfrage ausführen und als Array zurückgeben.
function hole_liste($conn, $sql) {
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

// --- Top-Ten Schwimmer über 14 (50m-Bahnen) ---
$top10_ueber14 = hole_liste($conn, "
    SELECT s.startnummer, CONCAT(s.vorname, ' ', s.nachname) AS name,
           (" . $aktuelles_jahr . " - s.geburtsjahr) AS alter_jahre,
           " . $leistung_spalte . " AS leistung
    FROM Schwimmer s
    WHERE (" . $aktuelles_jahr . " - s.geburtsjahr) > 14
      AND " . $leistung_spalte . " > 0
    ORDER BY " . $leistung_spalte . " DESC, s.startnummer ASC
    LIMIT 10
");

// --- Top-Ten Schwimmer unter 14 (25m-Bahnen) ---
$top10_unter14 = hole_liste($conn, "
    SELECT s.startnummer, CONCAT(s.vorname, ' ', s.nachname) AS name,
           (" . $aktuelles_jahr . " - s.geburtsjahr) AS alter_jahre,
           " . $leistung_spalte . " AS leistung
    FROM Schwimmer s
    WHERE (" . $aktuelles_jahr . " - s.geburtsjahr) <= 14
      AND " . $leistung_spalte . " > 0
    ORDER BY " . $leistung_spalte . " DESC, s.startnummer ASC
    LIMIT 10
");

// --- Top-Ten Sponsoren nach Spendensumme (jeweiliger Durchlauf) ---
$top10_sponsoren = hole_liste($conn, "
    SELECT sp.name AS sponsor_name,
           SUM(" . $spenden_spalte_sponsoren . ") AS summe
    FROM spenden_sponsoren ss
    JOIN Sponsoren sp ON ss.sponsoren_id = sp.id
    GROUP BY sp.id, sp.name
    ORDER BY summe DESC
    LIMIT 10
");

// --- Top-Ten Teams nach Spendensumme ---
// Teams: beim Gesamt-Durchlauf spendenbetrag_gedeckelt (Summen-Limit), sonst den jeweiligen Durchlauf.
if ($durchlauf === 'gesamt') {
    $teams_summe = 'SUM(st.spendenbetrag_gedeckelt)';
} else {
    $teams_summe = 'SUM(' . $spenden_spalte_teams . ')';
}
$top10_teams = hole_liste($conn, "
    SELECT t.name AS team_name,
           " . $teams_summe . " AS summe
    FROM spenden_teams st
    JOIN Teams t ON st.team_id = t.id
    GROUP BY t.id, t.name
    ORDER BY summe DESC
    LIMIT 10
");

// --- Top-Ten Hauptsponsoren nach Spendensumme ---
if ($durchlauf === 'gesamt') {
    $hs_summe = 'SUM(sh.spendenbetrag_gedeckelt)';
} else {
    $hs_summe = 'SUM(' . $spenden_spalte_hauptsponsoren . ')';
}
$top10_hauptsponsoren = hole_liste($conn, "
    SELECT h.name AS hauptsponsor_name,
           " . $hs_summe . " AS summe
    FROM spenden_hauptsponsoren sh
    JOIN Hauptsponsoren h ON sh.hauptsponsor_id = h.id
    GROUP BY h.id, h.name
    ORDER BY summe DESC
    LIMIT 10
");

// --- Gesamt geschwommene Distanz in km ---
// Unter 14: 25 m/Bahn, über 14: 50 m/Bahn. km = Bahnen * m/Bahn / 1000.
// Beim Vormittags-/Nachmittags-Durchlauf nur die jeweilige Leistung.
if ($durchlauf === 'gesamt') {
    $dist_unter14 = hole_liste($conn, "
        SELECT SUM(s.schwimmleistung_gesamt) AS bahnen
        FROM Schwimmer s WHERE (" . $aktuelles_jahr . " - s.geburtsjahr) <= 14
    ");
    $dist_ueber14 = hole_liste($conn, "
        SELECT SUM(s.schwimmleistung_gesamt) AS bahnen
        FROM Schwimmer s WHERE (" . $aktuelles_jahr . " - s.geburtsjahr) > 14
    ");
    $km_unter14 = ($dist_unter14 && $dist_unter14[0]['bahnen']) ? ($dist_unter14[0]['bahnen'] * 25 / 1000.0) : 0.0;
    $km_ueber14 = ($dist_ueber14 && $dist_ueber14[0]['bahnen']) ? ($dist_ueber14[0]['bahnen'] * 50 / 1000.0) : 0.0;
} else {
    $dist_unter14 = hole_liste($conn, "
        SELECT SUM(" . $leistung_spalte . ") AS bahnen
        FROM Schwimmer s WHERE (" . $aktuelles_jahr . " - s.geburtsjahr) <= 14
    ");
    $dist_ueber14 = hole_liste($conn, "
        SELECT SUM(" . $leistung_spalte . ") AS bahnen
        FROM Schwimmer s WHERE (" . $aktuelles_jahr . " - s.geburtsjahr) > 14
    ");
    $km_unter14 = ($dist_unter14 && $dist_unter14[0]['bahnen']) ? ($dist_unter14[0]['bahnen'] * 25 / 1000.0) : 0.0;
    $km_ueber14 = ($dist_ueber14 && $dist_ueber14[0]['bahnen']) ? ($dist_ueber14[0]['bahnen'] * 50 / 1000.0) : 0.0;
}
$km_total = $km_unter14 + $km_ueber14;

// Anzahl der Bahnen, die der Distanzberechnung zugrunde liegen.
$bahnen_unter14 = ($dist_unter14 && $dist_unter14[0]['bahnen']) ? (int)$dist_unter14[0]['bahnen'] : 0;
$bahnen_ueber14 = ($dist_ueber14 && $dist_ueber14[0]['bahnen']) ? (int)$dist_ueber14[0]['bahnen'] : 0;
$bahnen_total = $bahnen_unter14 + $bahnen_ueber14;

// --- Jüngster Schwimmer mit den meisten Bahnen ---
// Jüngster = höchstes Geburtsjahr; bei mehreren entscheidet die höhere Bahnenzahl.
// Die Bahnen beziehen sich auf den jeweiligen Durchlauf ($leistung_spalte).
$juengster = hole_liste($conn, "
    SELECT s.startnummer, s.vorname, s.nachname, s.geburtsjahr,
           (" . $aktuelles_jahr . " - s.geburtsjahr) AS alter_jahre,
           " . $leistung_spalte . " AS bahnen
    FROM Schwimmer s
    WHERE " . $leistung_spalte . " > 0
    ORDER BY s.geburtsjahr DESC, " . $leistung_spalte . " DESC, s.startnummer ASC
    LIMIT 1
");

// --- Ältester Schwimmer mit den meisten Bahnen ---
// Ältester = niedrigstes Geburtsjahr; bei mehreren entscheidet die höhere Bahnenzahl.
$aeltester = hole_liste($conn, "
    SELECT s.startnummer, s.vorname, s.nachname, s.geburtsjahr,
           (" . $aktuelles_jahr . " - s.geburtsjahr) AS alter_jahre,
           " . $leistung_spalte . " AS bahnen
    FROM Schwimmer s
    WHERE " . $leistung_spalte . " > 0
    ORDER BY s.geburtsjahr ASC, " . $leistung_spalte . " DESC, s.startnummer ASC
    LIMIT 1
");

// --- Anzahl der Schwimmer pro Durchlauf ---
// Vormittag: Schwimmer mit Bahnen > 0 am Vormittag.
$anzahl_vormittag = hole_liste($conn, "
    SELECT COUNT(*) AS anzahl
    FROM Schwimmer s WHERE s.schwimmleistung_vormittag > 0
");
$anz_v = ($anzahl_vormittag && $anzahl_vormittag[0]['anzahl']) ? (int)$anzahl_vormittag[0]['anzahl'] : 0;

// Nachmittag: Schwimmer mit Bahnen > 0 am Nachmittag.
$anzahl_nachmittag = hole_liste($conn, "
    SELECT COUNT(*) AS anzahl
    FROM Schwimmer s WHERE s.schwimmleistung_nachmittag > 0
");
$anz_n = ($anzahl_nachmittag && $anzahl_nachmittag[0]['anzahl']) ? (int)$anzahl_nachmittag[0]['anzahl'] : 0;

// Gesamt: Schwimmer, die an mindestens einem Durchlauf teilgenommen haben
// (Vormittag > 0 ODER Nachmittag > 0). Wer an beiden dabei war, wird nur einmal gezählt.
$anzahl_gesamt = hole_liste($conn, "
    SELECT COUNT(*) AS anzahl
    FROM Schwimmer s
    WHERE s.schwimmleistung_vormittag > 0 OR s.schwimmleistung_nachmittag > 0
");
$anz_g = ($anzahl_gesamt && $anzahl_gesamt[0]['anzahl']) ? (int)$anzahl_gesamt[0]['anzahl'] : 0;

// --- Anzahl Sponsoren / Teams / Hauptsponsoren ---
$anz_sponsoren = hole_liste($conn, "SELECT COUNT(*) AS anzahl FROM Sponsoren");
$anz_sp = ($anz_sponsoren && $anz_sponsoren[0]['anzahl']) ? (int)$anz_sponsoren[0]['anzahl'] : 0;

$anz_teams = hole_liste($conn, "SELECT COUNT(*) AS anzahl FROM Teams");
$anz_t = ($anz_teams && $anz_teams[0]['anzahl']) ? (int)$anz_teams[0]['anzahl'] : 0;

$anz_hauptsponsoren = hole_liste($conn, "SELECT COUNT(*) AS anzahl FROM Hauptsponsoren");
$anz_h = ($anz_hauptsponsoren && $anz_hauptsponsoren[0]['anzahl']) ? (int)$anz_hauptsponsoren[0]['anzahl'] : 0;

// --- Gesamtsummen der Spenden (über alle Einträge) ---
$gesamt_sponsoren = hole_liste($conn, "
    SELECT SUM(" . $spenden_spalte_sponsoren . ") AS summe
    FROM spenden_sponsoren ss
");
$g_sp = $gesamt_sponsoren ? $gesamt_sponsoren[0]['summe'] : 0;

if ($durchlauf === 'gesamt') {
    $gesamt_teams = hole_liste($conn, "SELECT SUM(st.spendenbetrag_gedeckelt) AS summe FROM spenden_teams st");
    $gesamt_hauptsponsoren = hole_liste($conn, "SELECT SUM(sh.spendenbetrag_gedeckelt) AS summe FROM spenden_hauptsponsoren sh");
} else {
    $gesamt_teams = hole_liste($conn, "SELECT SUM(" . $spenden_spalte_teams . ") AS summe FROM spenden_teams st");
    $gesamt_hauptsponsoren = hole_liste($conn, "SELECT SUM(" . $spenden_spalte_hauptsponsoren . ") AS summe FROM spenden_hauptsponsoren sh");
}
$g_t = $gesamt_teams ? $gesamt_teams[0]['summe'] : 0;
$g_h = $gesamt_hauptsponsoren ? $gesamt_hauptsponsoren[0]['summe'] : 0;
$g_total = $g_sp + $g_t + $g_h;

// CSV-Export: wenn ?export=csv, Datei direkt zum Download ausliefern.
// Trenner Semikolon + UTF-8-BOM, damit Excel die Datei mit Umlauten korrekt öffnet.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $dateiname = 'auswertung_' . $durchlauf . '_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8-BOM für Excel

    $fmt = function($v) { return ($v === null) ? '' : $v; };
    $euro = function($v) { return ($v === null) ? '' : number_format((float)$v, 2, ',', ''); };
    $km = function($v) { return number_format((float)$v, 3, ',', ''); };

    fputcsv($out, ['Auswertung – ' . ucfirst($durchlauf)], ';');
    fputcsv($out, [], ';');

    // Distanz
    fputcsv($out, ['Gesamt geschwommene Distanz (km)'], ';');
    fputcsv($out, ['Altersgruppe', 'm/Bahn', 'Anzahl Bahnen', 'Distanz (km)'], ';');
    fputcsv($out, ['Unter 14 Jahre (25m)', '25', $bahnen_unter14, $km($km_unter14)], ';');
    fputcsv($out, ['Über 14 Jahre (50m)', '50', $bahnen_ueber14, $km($km_ueber14)], ';');
    fputcsv($out, ['Gesamt', '', $bahnen_total, $km($km_total)], ';');
    fputcsv($out, [], ';');

    // Übersichtsstatistiken
    fputcsv($out, ['Übersicht'], ';');
    fputcsv($out, ['Jüngster Schwimmer mit den meisten Bahnen'], ';');
    fputcsv($out, ['Startnummer', 'Nachname', 'Vorname', 'Geburtsjahr', 'Alter', $leistung_label], ';');
    if (!empty($juengster)) {
        $j = $juengster[0];
        fputcsv($out, [$fmt($j['startnummer']), $fmt($j['nachname']), $fmt($j['vorname']), $fmt($j['geburtsjahr']), $fmt($j['alter_jahre']), $fmt($j['bahnen'])], ';');
    }
    fputcsv($out, ['Ältester Schwimmer mit den meisten Bahnen'], ';');
    fputcsv($out, ['Startnummer', 'Nachname', 'Vorname', 'Geburtsjahr', 'Alter', $leistung_label], ';');
    if (!empty($aeltester)) {
        $a = $aeltester[0];
        fputcsv($out, [$fmt($a['startnummer']), $fmt($a['nachname']), $fmt($a['vorname']), $fmt($a['geburtsjahr']), $fmt($a['alter_jahre']), $fmt($a['bahnen'])], ';');
    }
    fputcsv($out, [], ';');
    fputcsv($out, ['Anzahl / Summen'], ';');
    fputcsv($out, ['Schwimmer am Vormittag', $anz_v], ';');
    fputcsv($out, ['Schwimmer am Nachmittag', $anz_n], ';');
    fputcsv($out, ['Schwimmer gesamt (jeder nur einmal)', $anz_g], ';');
    fputcsv($out, ['Anzahl Sponsoren', $anz_sp], ';');
    fputcsv($out, ['Anzahl Teams', $anz_t], ';');
    fputcsv($out, ['Anzahl Hauptsponsoren', $anz_h], ';');
    fputcsv($out, [], ';');

    // Top-Ten Schwimmer über 14 (50m)
    fputcsv($out, ['Top-Ten Schwimmer über 14 Jahre (50m-Bahnen)'], ';');
    fputcsv($out, ['Platz', 'Startnr.', 'Schwimmer', 'Alter', $leistung_label], ';');
    $platz = 1;
    foreach ($top10_ueber14 as $r) {
        fputcsv($out, [$platz++, $fmt($r['startnummer']), $r['name'], $fmt($r['alter_jahre']), $fmt($r['leistung'])], ';');
    }
    fputcsv($out, [], ';');

    // Top-Ten Schwimmer unter 14 (25m)
    fputcsv($out, ['Top-Ten Schwimmer unter 14 Jahre (25m-Bahnen)'], ';');
    fputcsv($out, ['Platz', 'Startnr.', 'Schwimmer', 'Alter', $leistung_label], ';');
    $platz = 1;
    foreach ($top10_unter14 as $r) {
        fputcsv($out, [$platz++, $fmt($r['startnummer']), $r['name'], $fmt($r['alter_jahre']), $fmt($r['leistung'])], ';');
    }
    fputcsv($out, [], ';');

    // Top-Ten Sponsoren
    fputcsv($out, ['Top-Ten Sponsoren nach Spendensumme'], ';');
    fputcsv($out, ['Platz', 'Sponsor', 'Summe'], ';');
    $platz = 1;
    foreach ($top10_sponsoren as $r) {
        fputcsv($out, [$platz++, $fmt($r['sponsor_name']), $euro($r['summe'])], ';');
    }
    fputcsv($out, ['Gesamtsumme (alle Sponsoren)', '', $euro($g_sp)], ';');
    fputcsv($out, [], ';');

    // Top-Ten Teams
    fputcsv($out, ['Top-Ten Teams nach Spendensumme'], ';');
    fputcsv($out, ['Platz', 'Team', 'Summe'], ';');
    $platz = 1;
    foreach ($top10_teams as $r) {
        fputcsv($out, [$platz++, $fmt($r['team_name']), $euro($r['summe'])], ';');
    }
    fputcsv($out, ['Gesamtsumme (alle Teams)', '', $euro($g_t)], ';');
    fputcsv($out, [], ';');

    // Top-Ten Hauptsponsoren
    fputcsv($out, ['Top-Ten Hauptsponsoren nach Spendensumme'], ';');
    fputcsv($out, ['Platz', 'Hauptsponsor', 'Summe'], ';');
    $platz = 1;
    foreach ($top10_hauptsponsoren as $r) {
        fputcsv($out, [$platz++, $fmt($r['hauptsponsor_name']), $euro($r['summe'])], ';');
    }
    fputcsv($out, ['Gesamtsumme (alle Hauptsponsoren)', '', $euro($g_h)], ';');
    fputcsv($out, [], ';');

    // Gesamtsumme aller Spenden
    fputcsv($out, ['Gesamtsumme aller Spenden'], ';');
    fputcsv($out, ['Sponsoren', $euro($g_sp)], ';');
    fputcsv($out, ['Teams', $euro($g_t)], ';');
    fputcsv($out, ['Hauptsponsoren', $euro($g_h)], ';');
    fputcsv($out, ['Sponsoren + Teams + Hauptsponsoren', $euro($g_total)], ';');

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
        <title>' . htmlspecialchars($titel) . ' - VAIBad</title>
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
}
?>
<div class="container">
    <h1><?php echo htmlspecialchars($titel); ?> (Top Ten)</h1>

    <div class="action-bar">
        <a href="/auswertungen.php?durchlauf=vormittag" class="btn <?php echo ($durchlauf==='vormittag')?'btn-primary':'btn-secondary'; ?>">Vormittag</a>
        <a href="/auswertungen.php?durchlauf=nachmittag" class="btn <?php echo ($durchlauf==='nachmittag')?'btn-primary':'btn-secondary'; ?>">Nachmittag</a>
        <a href="/auswertungen.php?durchlauf=gesamt" class="btn <?php echo ($durchlauf==='gesamt')?'btn-primary':'btn-secondary'; ?>">Gesamt</a>
    </div>

    <div class="action-bar">
        <a href="/auswertungen.php?durchlauf=<?php echo $durchlauf; ?>&export=csv" class="btn btn-primary">Als CSV herunterladen</a>
        <a href="/index.php" class="btn btn-secondary">Startseite</a>
    </div>

    <?php
    $hinweis = (empty($top10_sponsoren) && empty($top10_teams) && empty($top10_hauptsponsoren));
    if ($hinweis):
    ?>
        <div class="error-box" style="margin: 1rem 0;">
            Hinweis: Die Sponsoren-/Teams-/Hauptsponsoren-Top-Ten sind leer.
            Bitte zuerst die jeweiligen Spendenberechnungen durchführen:
            <a href="/spendenberechnung.php">Sponsoren</a>,
            <a href="/spendenberechnung_teams.php">Teams</a>,
            <a href="/spendenberechnung_hauptsponsoren.php">Hauptsponsoren</a>.
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
                    <th>Anzahl Bahnen</th>
                    <th>Distanz (km)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Unter 14 Jahre (25m)</td>
                    <td>25</td>
                    <td><?php echo number_format($bahnen_unter14, 0, '', '.'); ?></td>
                    <td><?php echo number_format($km_unter14, 3, ',', '.'); ?></td>
                </tr>
                <tr>
                    <td>Über 14 Jahre (50m)</td>
                    <td>50</td>
                    <td><?php echo number_format($bahnen_ueber14, 0, '', '.'); ?></td>
                    <td><?php echo number_format($km_ueber14, 3, ',', '.'); ?></td>
                </tr>
                <tr style="font-weight: bold; background-color: #f0f0f0;">
                    <td colspan="2">Gesamt</td>
                    <td><?php echo number_format($bahnen_total, 0, '', '.'); ?></td>
                    <td><?php echo number_format($km_total, 3, ',', '.'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Übersichtsstatistiken -->
    <h2 style="margin-top: 2rem;">Übersicht</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Jüngster Schwimmer mit den meisten Bahnen</th>
                    <th>Startnummer</th>
                    <th>Nachname</th>
                    <th>Vorname</th>
                    <th>Geburtsjahr</th>
                    <th>Alter</th>
                    <th><?php echo htmlspecialchars($leistung_label); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($juengster)):
                    $j = $juengster[0]; ?>
                    <tr>
                        <td>&nbsp;</td>
                        <td><?php echo htmlspecialchars($j['startnummer']); ?></td>
                        <td><?php echo htmlspecialchars($j['nachname']); ?></td>
                        <td><?php echo htmlspecialchars($j['vorname']); ?></td>
                        <td><?php echo htmlspecialchars($j['geburtsjahr']); ?></td>
                        <td><?php echo htmlspecialchars($j['alter_jahre']); ?></td>
                        <td><strong><?php echo htmlspecialchars($j['bahnen']); ?></strong></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="7" class="no-data">Keine Schwimmer gefunden.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <table class="data-table" style="margin-top: 1rem;">
            <thead>
                <tr>
                    <th>Ältester Schwimmer mit den meisten Bahnen</th>
                    <th>Startnummer</th>
                    <th>Nachname</th>
                    <th>Vorname</th>
                    <th>Geburtsjahr</th>
                    <th>Alter</th>
                    <th><?php echo htmlspecialchars($leistung_label); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($aeltester)):
                    $a = $aeltester[0]; ?>
                    <tr>
                        <td>&nbsp;</td>
                        <td><?php echo htmlspecialchars($a['startnummer']); ?></td>
                        <td><?php echo htmlspecialchars($a['nachname']); ?></td>
                        <td><?php echo htmlspecialchars($a['vorname']); ?></td>
                        <td><?php echo htmlspecialchars($a['geburtsjahr']); ?></td>
                        <td><?php echo htmlspecialchars($a['alter_jahre']); ?></td>
                        <td><strong><?php echo htmlspecialchars($a['bahnen']); ?></strong></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="7" class="no-data">Keine Schwimmer gefunden.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <table class="data-table" style="margin-top: 1rem;">
            <thead>
                <tr>
                    <th colspan="2">Anzahl / Summen</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Schwimmer am Vormittag</td><td><strong><?php echo $anz_v; ?></strong></td></tr>
                <tr><td>Schwimmer am Nachmittag</td><td><strong><?php echo $anz_n; ?></strong></td></tr>
                <tr><td>Schwimmer gesamt (jeder nur einmal)</td><td><strong><?php echo $anz_g; ?></strong></td></tr>
                <tr><td>Anzahl Sponsoren</td><td><strong><?php echo $anz_sp; ?></strong></td></tr>
                <tr><td>Anzahl Teams</td><td><strong><?php echo $anz_t; ?></strong></td></tr>
                <tr><td>Anzahl Hauptsponsoren</td><td><strong><?php echo $anz_h; ?></strong></td></tr>
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
                    <th><?php echo htmlspecialchars($leistung_label); ?></th>
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
                            <td><strong><?php echo htmlspecialchars($r['leistung']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="no-data">Keine Schwimmer über 14 gefunden.</td></tr>
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
                    <th><?php echo htmlspecialchars($leistung_label); ?></th>
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
                            <td><strong><?php echo htmlspecialchars($r['leistung']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="no-data">Keine Schwimmer unter 14 gefunden.</td></tr>
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
                    <th>Summe</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($top10_sponsoren)): ?>
                    <?php $platz = 1; foreach ($top10_sponsoren as $r): ?>
                        <tr>
                            <td><strong><?php echo $platz++; ?></strong></td>
                            <td><?php echo htmlspecialchars($r['sponsor_name']); ?></td>
                            <td><strong><?php echo number_format($r['summe'], 2, ',', '.'); ?> €</strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="no-data">Noch keine Sponsoren-Spenden berechnet.</td></tr>
                <?php endif; ?>
                <tr style="font-weight: bold; background-color: #f0f0f0;">
                    <td colspan="2">Gesamtsumme (alle Sponsoren)</td>
                    <td><?php echo number_format($g_sp, 2, ',', '.'); ?> €</td>
                </tr>
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
                    <th>Summe</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($top10_teams)): ?>
                    <?php $platz = 1; foreach ($top10_teams as $r): ?>
                        <tr>
                            <td><strong><?php echo $platz++; ?></strong></td>
                            <td><?php echo htmlspecialchars($r['team_name']); ?></td>
                            <td><strong><?php echo number_format($r['summe'], 2, ',', '.'); ?> €</strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="no-data">Noch keine Team-Spenden berechnet.</td></tr>
                <?php endif; ?>
                <tr style="font-weight: bold; background-color: #f0f0f0;">
                    <td colspan="2">Gesamtsumme (alle Teams)</td>
                    <td><?php echo number_format($g_t, 2, ',', '.'); ?> €</td>
                </tr>
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
                    <th>Summe</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($top10_hauptsponsoren)): ?>
                    <?php $platz = 1; foreach ($top10_hauptsponsoren as $r): ?>
                        <tr>
                            <td><strong><?php echo $platz++; ?></strong></td>
                            <td><?php echo htmlspecialchars($r['hauptsponsor_name']); ?></td>
                            <td><strong><?php echo number_format($r['summe'], 2, ',', '.'); ?> €</strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="no-data">Noch keine Hauptsponsor-Spenden berechnet.</td></tr>
                <?php endif; ?>
                <tr style="font-weight: bold; background-color: #f0f0f0;">
                    <td colspan="2">Gesamtsumme (alle Hauptsponsoren)</td>
                    <td><?php echo number_format($g_h, 2, ',', '.'); ?> €</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Gesamtsumme aller Spenden -->
    <div style="margin-top: 1.5rem; padding: 1rem; background-color: #e8f4e8; border-radius: 4px;">
        <strong>Gesamtsumme aller Spenden (Sponsoren):</strong>
        <?php echo number_format($g_sp, 2, ',', '.'); ?> €<br>
        <strong>Gesamtsumme aller Spenden (Teams):</strong>
        <?php echo number_format($g_t, 2, ',', '.'); ?> €<br>
        <strong>Gesamtsumme aller Spenden (Hauptsponsoren):</strong>
        <?php echo number_format($g_h, 2, ',', '.'); ?> €<br><br>
        <strong style="font-size: 1.2rem;">Gesamtsumme aller Spenden (Sponsoren + Teams + Hauptsponsoren):</strong>
        <?php echo number_format($g_total, 2, ',', '.'); ?> €
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
