<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Berechnet die Spendenbeträge für alle Schwimmer-Sponsor-Verknüpfungen.
//
// Für jede Verknüpfung (Tabelle schwimmer_sponsor) gilt je Durchlauf:
//   rohbetrag   = schwimmleistung * betrag_pro_bahn (aus Tabelle Sponsoren)
//   spendenbetrag = MIN(rohbetrag, limit)   -> das Limit ist der Maximalbetrag
//                                            (NULL = ohne Begrenzung)
//   spendenbetrag_gesamt = spendenbetrag_vormittag + spendenbetrag_nachmittag
//
// Die Ergebnistabelle spenden_sponsoren wird bei jedem Lauf vollständig neu
// berechnet (TRUNCATE + INSERT), damit die Ergebnisse immer eine aktuelle
// Momentaufnahme darstellen.

$berechnet = false;
$anzahl_eintraege = 0;
$summe_vormittag = 0.0;
$summe_nachmittag = 0.0;
$summe_gesamt = 0.0;
$fehler = [];

// Berechnung anstoßen, sobald der Bestätigungs-Button gedrückt wurde.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['berechnen'])) {

    // Alle Daten holen: je Verknüpfung die Schwimmerleistung und die Sponsor-Konditionen.
    $sql = "
        SELECT ss.schwimmer_id,
               ss.sponsoren_id,
               sw.schwimmleistung_vormittag,
               sw.schwimmleistung_nachmittag,
               sp.betrag_pro_bahn,
               sp.`limit`
        FROM schwimmer_sponsor ss
        JOIN Schwimmer sw ON ss.schwimmer_id = sw.id
        JOIN Sponsoren sp ON ss.sponsoren_id = sp.id
    ";
    $result = $conn->query($sql);

    if ($result === false) {
        $fehler[] = "Daten konnten nicht gelesen werden: " . htmlspecialchars($conn->error);
    } else {
        // Transaktion: Tabelle leeren und neu befüllen – atomar und konsistent.
        $conn->begin_transaction();

        try {
            // Alte Ergebnisse verwerfen.
            if (!$conn->query("TRUNCATE TABLE spenden_sponsoren")) {
                throw new Exception("Tabelle spenden_sponsoren konnte nicht geleert werden.");
            }

            // Insert mit vorbereitetem Statement für alle Verknüpfungen.
            $insert = $conn->prepare("
                INSERT INTO spenden_sponsoren
                    (schwimmer_id, sponsoren_id,
                     spendenbetrag_vormittag, spendenbetrag_nachmittag, spendenbetrag_gesamt)
                VALUES (?, ?, ?, ?, ?)
            ");

            if ($insert === false) {
                throw new Exception("Insert-Statement konnte nicht vorbereitet werden.");
            }

            while ($row = $result->fetch_assoc()) {
                // Vormittag: roh = bahnen * betrag_pro_bahn; gedeckelt auf limit (falls gesetzt).
                $roh_v = (float)$row['schwimmleistung_vormittag'] * (float)$row['betrag_pro_bahn'];
                if ($row['limit'] !== null) {
                    $betrag_v = min($roh_v, (float)$row['limit']);
                } else {
                    $betrag_v = $roh_v;
                }

                // Nachmittag: selbe Logik.
                $roh_n = (float)$row['schwimmleistung_nachmittag'] * (float)$row['betrag_pro_bahn'];
                if ($row['limit'] !== null) {
                    $betrag_n = min($roh_n, (float)$row['limit']);
                } else {
                    $betrag_n = $roh_n;
                }

                $gesamt = $betrag_v + $betrag_n;

                $insert->bind_param("iiddd",
                    $row['schwimmer_id'],
                    $row['sponsoren_id'],
                    $betrag_v,
                    $betrag_n,
                    $gesamt
                );
                $insert->execute();

                $anzahl_eintraege++;
                $summe_vormittag   += $betrag_v;
                $summe_nachmittag  += $betrag_n;
                $summe_gesamt      += $gesamt;
            }

            $insert->close();
            $conn->commit();
            $berechnet = true;

        } catch (Exception $e) {
            $conn->rollback();
            $fehler[] = $e->getMessage();
        }

        $result->free();
    }
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
        <title>Spendenberechnung - VAIBad</title>
        <link rel="stylesheet" href="/css/style.css">
    </head>
    <body>';
}
?>
<div class="container">
    <h1>Spendenbeträge berechnen</h1>

    <p>
        Für jeden Schwimmer werden aus der Tabelle <em>schwimmer_sponsor</em> die verknüpften Sponsoren
        ermittelt. Pro Verknüpfung und Durchlauf wird berechnet:<br>
        <strong>Betrag = Schwimmleistung × Betrag pro Bahn</strong>, gedeckelt auf das <strong>Limit</strong>
        des Sponsors (falls gesetzt). Vormittag und Nachmittag werden getrennt berechnet, die Summe
        ergibt den jeweiligen Gesamt-Spendenbetrag. Die Ergebnisse werden in der Tabelle
        <em>spenden_sponsoren</em> abgelegt (eine Zeile je Schwimmer-Sponsor-Paar).
    </p>

    <!-- Fehler anzeigen -->
    <?php if (!empty($fehler)): ?>
        <div class="error-box">
            <ul>
                <?php foreach ($fehler as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Bestätigungsmeldung nach Berechnung -->
    <?php if ($berechnet): ?>
        <div class="success-box">
            Berechnung abgeschlossen.<br>
            Es wurden <strong><?php echo $anzahl_eintraege; ?></strong> Einträge erzeugt.<br>
            Summe Vormittag: <strong><?php echo number_format($summe_vormittag, 2, ',', '.'); ?> €</strong> |
            Summe Nachmittag: <strong><?php echo number_format($summe_nachmittag, 2, ',', '.'); ?> €</strong> |
            Summe Gesamt: <strong><?php echo number_format($summe_gesamt, 2, ',', '.'); ?> €</strong>
        </div>
    <?php endif; ?>

    <!-- Formular zum Anstoßen der Berechnung -->
    <form method="POST" action="/spendenberechnung.php" class="form">
        <div class="form-actions">
            <button type="submit" name="berechnen" value="1" class="btn btn-primary"
                    onclick="return confirm('Sollen die Spendenbeträge jetzt neu berechnet werden? Vorherige Ergebnisse werden überschrieben.')">
                Spendenbeträge berechnen
            </button>
            <a href="/spenden_sponsoren.php" class="btn btn-secondary">Ergebnisse ansehen</a>
            <a href="/index.php" class="btn btn-secondary">Startseite</a>
        </div>
    </form>
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
