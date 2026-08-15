<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Berechnet die Hauptsponsor-Spendenbeträge.
//
// Jeder Hauptsponsor zahlt für JEDEDN Schwimmer (Kreuzprodukt Hauptsponsoren ×
// Schwimmer). Pro Paar gilt pro Durchlauf:
//   rohbetrag = schwimmleistung * betrag_pro_bahn (aus Tabelle Hauptsponsoren)
//   spendenbetrag_vormittag / _nachmittag = jeweiliger Rohbetrag
//   spendenbetrag_gesamt = vormittag + nachmittag
//
// Das Hauptsponsor-Limit ist der Maximalbetrag über die SUMME aller Schwimmer
// dieses Hauptsponsors. Die Summe wird auf das Limit gedeckelt; die Kappung wird
// anteilig auf die Schwimmer verteilt -> spendenbetrag_gedeckelt.
// (NULL = ohne Begrenzung, dann ist spendenbetrag_gedeckelt = gesamt)
//
// Nur Schwimmer mit schwimmleistung_gesamt > 0 werden berücksichtigt
// (also nur Schwimmer, die eine Spende erschwommen haben).
//
// Die Ergebnistabelle spenden_hauptsponsoren wird bei jedem Lauf vollständig neu
// berechnet (TRUNCATE + INSERT), damit die Ergebnisse eine aktuelle
// Momentaufnahme darstellen.

$berechnet = false;
$anzahl_eintraege = 0;
$summe_gesamt = 0.0;
$summe_gedeckelt = 0.0;
$fehler = [];

// Berechnung anstoßen, sobald der Bestätigungs-Button gedrückt wurde.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['berechnen'])) {

    // Kreuzprodukt: jeder Hauptsponsor × jeder Schwimmer (mit Leistung > 0).
    $sql = "
        SELECT h.id AS hauptsponsor_id,
               h.betrag_pro_bahn,
               h.`limit`,
               sw.id AS schwimmer_id,
               sw.schwimmleistung_vormittag,
               sw.schwimmleistung_nachmittag
        FROM Hauptsponsoren h
        CROSS JOIN Schwimmer sw
        WHERE (sw.schwimmleistung_vormittag + sw.schwimmleistung_nachmittag) > 0
    ";
    $result = $conn->query($sql);

    if ($result === false) {
        $fehler[] = "Daten konnten nicht gelesen werden: " . htmlspecialchars($conn->error);
    } else {
        // Erst alle Rohbeträge je Paar berechnen und nach Hauptsponsor gruppieren.
        $paare = [];        // Liste aller zu speichernden Paare
        $nach_hauptsponsor = [];  // hauptsponsor_id => liste der paare (Referenzen)

        while ($row = $result->fetch_assoc()) {
            $betrag_v = (float)$row['schwimmleistung_vormittag'] * (float)$row['betrag_pro_bahn'];
            $betrag_n = (float)$row['schwimmleistung_nachmittag'] * (float)$row['betrag_pro_bahn'];
            $gesamt = $betrag_v + $betrag_n;

            $eintrag = [
                'hauptsponsor_id'           => $row['hauptsponsor_id'],
                'schwimmer_id'              => $row['schwimmer_id'],
                'spendenbetrag_vormittag'   => $betrag_v,
                'spendenbetrag_nachmittag'  => $betrag_n,
                'spendenbetrag_gesamt'      => $gesamt,
                'limit'                     => $row['limit'],
                'gedeckelt'                 => $gesamt, // vorläufig, unten angepasst
            ];
            $paare[] = $eintrag;
            $nach_hauptsponsor[$row['hauptsponsor_id']][] = &$paare[count($paare) - 1];
        }
        $result->free();

        // Pro Hauptsponsor: Summe bilden, auf Limit deckeln, anteilig verteilen.
        foreach ($nach_hauptsponsor as $hid => &$gruppen_paare) {
            $summe = 0.0;
            foreach ($gruppen_paare as $p) {
                $summe += $p['spendenbetrag_gesamt'];
            }

            if ($gruppen_paare[0]['limit'] !== null && $summe > (float)$gruppen_paare[0]['limit']) {
                // Anteilige Kappung.
                $limit_val = (float)$gruppen_paare[0]['limit'];
                if ($summe > 0) {
                    foreach ($gruppen_paare as &$p) {
                        $anteil = $p['spendenbetrag_gesamt'] / $summe;
                        $p['gedeckelt'] = round($limit_val * $anteil, 2);
                    }
                    unset($p);
                }
            } else {
                // Kein Limit oder Summe <= Limit: gedeckelt = gesamt.
                foreach ($gruppen_paare as &$p) {
                    $p['gedeckelt'] = $p['spendenbetrag_gesamt'];
                }
                unset($p);
            }
        }
        unset($gruppen_paare);

        // Transaktion: Tabelle leeren und neu befüllen – atomar und konsistent.
        $conn->begin_transaction();

        try {
            // Alte Ergebnisse verwerfen.
            if (!$conn->query("TRUNCATE TABLE spenden_hauptsponsoren")) {
                throw new Exception("Tabelle spenden_hauptsponsoren konnte nicht geleert werden.");
            }

            $insert = $conn->prepare("
                INSERT INTO spenden_hauptsponsoren
                    (hauptsponsor_id, schwimmer_id,
                     spendenbetrag_vormittag, spendenbetrag_nachmittag, spendenbetrag_gesamt,
                     spendenbetrag_gedeckelt)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            if ($insert === false) {
                throw new Exception("Insert-Statement konnte nicht vorbereitet werden.");
            }

            foreach ($paare as $p) {
                $insert->bind_param("iidddd",
                    $p['hauptsponsor_id'],
                    $p['schwimmer_id'],
                    $p['spendenbetrag_vormittag'],
                    $p['spendenbetrag_nachmittag'],
                    $p['spendenbetrag_gesamt'],
                    $p['gedeckelt']
                );
                $insert->execute();

                $anzahl_eintraege++;
                $summe_gesamt    += $p['spendenbetrag_gesamt'];
                $summe_gedeckelt += $p['gedeckelt'];
            }

            $insert->close();
            $conn->commit();
            $berechnet = true;

        } catch (Exception $e) {
            $conn->rollback();
            $fehler[] = $e->getMessage();
        }
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
        <title>Hauptsponsor-Spendenberechnung - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>
<div class="container">
    <h1>Hauptsponsor-Spendenbeträge berechnen</h1>

    <p>
        Jeder Hauptsponsor zahlt für <strong>jeden</strong> Schwimmer. Pro Paar wird berechnet:<br>
        <strong>Betrag = Schwimmleistung × Betrag pro Bahn</strong> (aus der Tabelle Hauptsponsoren),
        getrennt nach Vormittag und Nachmittag. Das <strong>Hauptsponsor-Limit</strong> gilt als
        Maximalbetrag über die Summe aller Schwimmer eines Hauptsponsors; die Kappung wird anteilig
        auf die Schwimmer verteilt (<em>spendenbetrag_gedeckelt</em>). NULL als Limit bedeutet keine
        Begrenzung. Nur Schwimmer, die eine Spende erschwommen haben, werden berücksichtigt.
        Die Ergebnisse werden in der Tabelle <em>spenden_hauptsponsoren</em> abgelegt.
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
            Summe gesamt (ungekürzt): <strong><?php echo number_format($summe_gesamt, 2, ',', '.'); ?> €</strong> |
            Summe gedeckelt: <strong><?php echo number_format($summe_gedeckelt, 2, ',', '.'); ?> €</strong>
        </div>
    <?php endif; ?>

    <!-- Formular zum Anstoßen der Berechnung -->
    <form method="POST" action="/VAIBad_2/webapp/spendenberechnung_hauptsponsoren.php" class="form">
        <div class="form-actions">
            <button type="submit" name="berechnen" value="1" class="btn btn-primary"
                    onclick="return confirm('Sollen die Hauptsponsor-Spendenbeträge jetzt neu berechnet werden? Vorherige Ergebnisse werden überschrieben.')">
                Hauptsponsor-Spendenbeträge berechnen
            </button>
            <a href="/VAIBad_2/webapp/spenden_hauptsponsoren.php" class="btn btn-secondary">Ergebnisse ansehen</a>
            <a href="/VAIBad_2/webapp/index.php" class="btn btn-secondary">Startseite</a>
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
