<?php
// Datenbankkonfiguration für VAIBad_2
$host = 'localhost';
$username = 'strafe_user';
$password = 'Michael'; // Kein Passwort
$database = 'vaibad_2';

// Verbindung herstellen
$conn = new mysqli($host, $username, $password, $database);

// Verbindung prüfen
if ($conn->connect_error) {
    die("Verbindung zur Datenbank fehlgeschlagen: " . $conn->connect_error);
}

// Zeitzone setzen
date_default_timezone_set('Europe/Berlin');

// UTF-8 für die Verbindung erzwingen
$conn->set_charset("utf8mb4");
?>
