<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Parameter prüfen
if (!isset($_GET['schwimmer_id']) || !is_numeric($_GET['schwimmer_id']) ||
    !isset($_GET['team_id']) || !is_numeric($_GET['team_id'])) {
    header("Location: /schwimmerliste.php");
    exit();
}

$schwimmer_id = intval($_GET['schwimmer_id']);
$team_id = intval($_GET['team_id']);

// Verknüpfung löschen
$stmt = $conn->prepare("DELETE FROM schwimmer_team WHERE schwimmer_id = ? AND team_id = ?");
$stmt->bind_param("ii", $schwimmer_id, $team_id);
$stmt->execute();
$stmt->close();

// Zurück zur Bearbeitungsseite
header("Location: /bearbeiten_schwimmer.php?id=$schwimmer_id");
exit();
?>
