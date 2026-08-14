<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Parameter prüfen
if (!isset($_GET['schwimmer_id']) || !is_numeric($_GET['schwimmer_id']) ||
    !isset($_GET['sponsor_id']) || !is_numeric($_GET['sponsor_id'])) {
    header("Location: /VAIBad_2/webapp/schwimmerliste.php");
    exit();
}

$schwimmer_id = intval($_GET['schwimmer_id']);
$sponsor_id = intval($_GET['sponsor_id']);

// Verknüpfung löschen
$stmt = $conn->prepare("DELETE FROM schwimmer_sponsor WHERE schwimmer_id = ? AND sponsoren_id = ?");
$stmt->bind_param("ii", $schwimmer_id, $sponsor_id);
$stmt->execute();
$stmt->close();

// Zurück zur Bearbeitungsseite
header("Location: /VAIBad_2/webapp/bearbeiten_schwimmer.php?id=$schwimmer_id");
exit();
?>
