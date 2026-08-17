<?php
// Datenbankkonfiguration fuer VAIBad_2
// Aufruf: https://schwimmen.foerderverein-enztalbad.de
// Docroot: /home/www/public_html/vaibad
//
// TODO: Die folgenden Werte durch die echten Telekom-Zugangsdaten ersetzen.
$host = 'localhost';          // Telekom-Datenbankhost (ggf. localhost oder db-Adresse)
$username = 'vaibad_user';   // Telekom-Datenbankbenutzer
$password = 'HIER_PASSWORT_EINTRAGEN'; // Telekom-Datenbankpasswort
$database = 'vaibad_2';      // Name der Datenbank auf dem Telekom-Server

// Verbindung herstellen
$conn = new mysqli($host, $username, $password, $database);

// Verbindung pruefen
if ($conn->connect_error) {
    die("Verbindung zur Datenbank fehlgeschlagen: " . $conn->connect_error);
}

// Zeitzone setzen
date_default_timezone_set('Europe/Berlin');

// UTF-8 fuer die Verbindung erzwingen
$conn->set_charset("utf8mb4");
?>
