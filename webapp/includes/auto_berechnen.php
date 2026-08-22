<?php
// Automatische Spendenberechnung
// Diese Datei führt alle drei Spendenberechnungen aus
// Sie wird am Anfang von auswertungen.php, spenden_sponsoren.php und spendenlisten.php aufgerufen

if (!defined('AUTO_BERECHNET')) {
    define('AUTO_BERECHNET', true);
    
    // Funktion zum Ausführen der Spendenberechnungen
    function fuehre_spendenberechnungen_aus($conn) {
        // 1. Sponsoren-Berechnung
        $sql_sponsoren = "
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
        $result = $conn->query($sql_sponsoren);
        
        if ($result && $result->num_rows > 0) {
            $conn->begin_transaction();
            try {
                // Alte Ergebnisse verwerfen
                $conn->query("TRUNCATE TABLE spenden_sponsoren");
                
                $insert = $conn->prepare("
                    INSERT INTO spenden_sponsoren
                        (schwimmer_id, sponsoren_id,
                         spendenbetrag_vormittag, spendenbetrag_nachmittag, spendenbetrag_gesamt)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                if ($insert) {
                    while ($row = $result->fetch_assoc()) {
                        $roh_v = (float)$row['schwimmleistung_vormittag'] * (float)$row['betrag_pro_bahn'];
                        $betrag_v = ($row['limit'] !== null) ? min($roh_v, (float)$row['limit']) : $roh_v;
                        
                        $roh_n = (float)$row['schwimmleistung_nachmittag'] * (float)$row['betrag_pro_bahn'];
                        $betrag_n = ($row['limit'] !== null) ? min($roh_n, (float)$row['limit']) : $roh_n;
                        
                        $gesamt = $betrag_v + $betrag_n;
                        
                        $insert->bind_param("iiddd",
                            $row['schwimmer_id'],
                            $row['sponsoren_id'],
                            $betrag_v,
                            $betrag_n,
                            $gesamt
                        );
                        $insert->execute();
                    }
                    $insert->close();
                }
                $conn->commit();
                $result->free();
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
        
        // 2. Teams-Berechnung
        $sql_teams = "
            SELECT st.team_id,
                   st.schwimmer_id,
                   sw.schwimmleistung_vormittag,
                   sw.schwimmleistung_nachmittag,
                   t.betrag_pro_bahn,
                   t.`limit`
            FROM schwimmer_team st
            JOIN Schwimmer sw ON st.schwimmer_id = sw.id
            JOIN Teams t ON st.team_id = t.id
            WHERE sw.schwimmleistung_gesamt > 0
        ";
        $result = $conn->query($sql_teams);
        
        if ($result && $result->num_rows > 0) {
            $conn->begin_transaction();
            try {
                // Alte Ergebnisse verwerfen
                $conn->query("TRUNCATE TABLE spenden_teams");
                
                // Erst alle Rohbeträge einfügen
                $insert_roh = $conn->prepare("
                    INSERT INTO spenden_teams
                        (team_id, schwimmer_id,
                         spendenbetrag_vormittag, spendenbetrag_nachmittag, spendenbetrag_gesamt, spendenbetrag_gedeckelt)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                if ($insert_roh) {
                    while ($row = $result->fetch_assoc()) {
                        $roh_v = (float)$row['schwimmleistung_vormittag'] * (float)$row['betrag_pro_bahn'];
                        $roh_n = (float)$row['schwimmleistung_nachmittag'] * (float)$row['betrag_pro_bahn'];
                        $gesamt = $roh_v + $roh_n;
                        
                        $insert_roh->bind_param("iidddd",
                            $row['team_id'],
                            $row['schwimmer_id'],
                            $roh_v,
                            $roh_n,
                            $gesamt,
                            $gesamt
                        );
                        $insert_roh->execute();
                    }
                    $insert_roh->close();
                }
                
                // Team-Limits anwenden (Deckelung)
                $team_sums = [];
                $result2 = $conn->query("SELECT team_id, SUM(spendenbetrag_gesamt) as summe FROM spenden_teams GROUP BY team_id");
                if ($result2) {
                    while ($row2 = $result2->fetch_assoc()) {
                        $team_id = $row2['team_id'];
                        $team_sum = (float)$row2['summe'];
                        
                        // Team-Limit holen
                        $stmt = $conn->prepare("SELECT `limit` FROM Teams WHERE id = ?");
                        $stmt->bind_param("i", $team_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $team = $res->fetch_assoc();
                        $stmt->close();
                        
                        if ($team && $team['limit'] !== null && $team_sum > (float)$team['limit']) {
                            // Deckelung anwenden
                            $factor = (float)$team['limit'] / $team_sum;
                            $stmt_update = $conn->prepare("UPDATE spenden_teams SET spendenbetrag_gedeckelt = spendenbetrag_gesamt * ? WHERE team_id = ?");
                            $stmt_update->bind_param("di", $factor, $team_id);
                            $stmt_update->execute();
                            $stmt_update->close();
                        } else {
                            // Keine Deckelung nötig
                            $stmt_update = $conn->prepare("UPDATE spenden_teams SET spendenbetrag_gedeckelt = spendenbetrag_gesamt WHERE team_id = ?");
                            $stmt_update->bind_param("i", $team_id);
                            $stmt_update->execute();
                            $stmt_update->close();
                        }
                    }
                    $result2->free();
                }
                
                $conn->commit();
                $result->free();
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
        
        // 3. Hauptsponsoren-Berechnung
        $sql_haupt = "
            SELECT h.id as hauptsponsor_id,
                   sw.id as schwimmer_id,
                   sw.schwimmleistung_vormittag,
                   sw.schwimmleistung_nachmittag,
                   h.betrag_pro_bahn,
                   h.`limit`
            FROM Hauptsponsoren h
            CROSS JOIN Schwimmer sw
            WHERE sw.schwimmleistung_gesamt > 0
        ";
        $result = $conn->query($sql_haupt);
        
        if ($result && $result->num_rows > 0) {
            $conn->begin_transaction();
            try {
                // Alte Ergebnisse verwerfen
                $conn->query("TRUNCATE TABLE spenden_hauptsponsoren");
                
                $insert_roh = $conn->prepare("
                    INSERT INTO spenden_hauptsponsoren
                        (hauptsponsor_id, schwimmer_id,
                         spendenbetrag_vormittag, spendenbetrag_nachmittag, spendenbetrag_gesamt, spendenbetrag_gedeckelt)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                if ($insert_roh) {
                    while ($row = $result->fetch_assoc()) {
                        $roh_v = (float)$row['schwimmleistung_vormittag'] * (float)$row['betrag_pro_bahn'];
                        $roh_n = (float)$row['schwimmleistung_nachmittag'] * (float)$row['betrag_pro_bahn'];
                        $gesamt = $roh_v + $roh_n;
                        
                        $insert_roh->bind_param("iidddd",
                            $row['hauptsponsor_id'],
                            $row['schwimmer_id'],
                            $roh_v,
                            $roh_n,
                            $gesamt,
                            $gesamt
                        );
                        $insert_roh->execute();
                    }
                    $insert_roh->close();
                }
                
                // Hauptsponsor-Limits anwenden (Deckelung)
                $hs_sums = [];
                $result2 = $conn->query("SELECT hauptsponsor_id, SUM(spendenbetrag_gesamt) as summe FROM spenden_hauptsponsoren GROUP BY hauptsponsor_id");
                if ($result2) {
                    while ($row2 = $result2->fetch_assoc()) {
                        $hs_id = $row2['hauptsponsor_id'];
                        $hs_sum = (float)$row2['summe'];
                        
                        // Hauptsponsor-Limit holen
                        $stmt = $conn->prepare("SELECT `limit` FROM Hauptsponsoren WHERE id = ?");
                        $stmt->bind_param("i", $hs_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $hs = $res->fetch_assoc();
                        $stmt->close();
                        
                        if ($hs && $hs['limit'] !== null && $hs_sum > (float)$hs['limit']) {
                            // Deckelung anwenden
                            $factor = (float)$hs['limit'] / $hs_sum;
                            $stmt_update = $conn->prepare("UPDATE spenden_hauptsponsoren SET spendenbetrag_gedeckelt = spendenbetrag_gesamt * ? WHERE hauptsponsor_id = ?");
                            $stmt_update->bind_param("di", $factor, $hs_id);
                            $stmt_update->execute();
                            $stmt_update->close();
                        } else {
                            // Keine Deckelung nötig
                            $stmt_update = $conn->prepare("UPDATE spenden_hauptsponsoren SET spendenbetrag_gedeckelt = spendenbetrag_gesamt WHERE hauptsponsor_id = ?");
                            $stmt_update->bind_param("i", $hs_id);
                            $stmt_update->execute();
                            $stmt_update->close();
                        }
                    }
                    $result2->free();
                }
                
                $conn->commit();
                $result->free();
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
    }
}

// Automatische Berechnung ausführen
fuehre_spendenberechnungen_aus($conn);
?>