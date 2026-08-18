# VAIBad 2 — TELEKOM Online-Version (Branch `telekom-online`)

Dieser Branch enthält die **Online-Version** für den Einsatz auf der Telekom-Subdomain
`https://schwimmen.foerderverein-enztalbad.de`. Er baut auf dem Stand
`vibe/telekom-subdomain-setup-fba029` auf (Root-Pfade, `.htaccess`, Telekom-`config.php`)
und ergänzt ein **Login-System mit Benutzer und Passwort**.

> Die Raspberry-Testversion bleibt unverändert auf dem Branch `main` erhalten.

## Was ist neu gegenüber `vibe/telekom-subdomain-setup-fba029`

- **Login-Pflicht** für alle Seiten der Webapp.
- Passwörter werden **verschlüsselt** (bcrypt via `password_hash()`) in der
  Datenbank gespeichert — niemals im Klartext.
- Neue Dateien:
  - `webapp/auth.php` — zentrale Login-Prüfung (läuft als Erstes über `config.php`)
  - `webapp/login.php` — Login-Formular mit CSRF-Schutz
  - `webapp/logout.php` — sichere Abmeldung
  - `webapp/includes/header.php` — gemeinsames HTML-Gerüst + Abmelden-Link
  - `webapp/includes/footer.php` — gemeinsamer HTML-Footer
  - `webapp/benutzer_anlegen.php` — Skript zum Anlegen der ersten Benutzer
  - `vaibad_2_auth_setup.sql` — legt die Tabelle `benutzer` an
- Alle Links verwenden die Root-Pfade der Subdomain (`/index.php`, `/login.php` usw.).

## Einrichtung (Schritt für Schritt)

### 1. Datenbanktabelle anlegen
Führe das SQL-Skript auf der Telekom-Datenbank aus:
```
mysql -u vaibad_user -p vaibad_2 < vaibad_2_auth_setup.sql
```
Das legt die Tabelle `benutzer` mit den Spalten `benutzername`,
`passwort_hash`, `aktiv` und `letzter_login` an.

### 2. Datenbank-Zugangsdaten prüfen
In `webapp/config.php` die echten Telekom-Zugangsdaten eintragen
(`$username`, `$password`, `$database`); die Platzhalter ersetzen.

### 3. Erste Benutzer anlegen
1. `webapp/benutzer_anlegen.php` öffnen und im Array `$neue_benutzer`
   die gewünschten Benutzername/Passwort-Paare eintragen:
   ```php
   $neue_benutzer = [
       'admin' => 'DeinSicheresPasswort!',
   ];
   ```
2. Die Datei **einmalig** im Browser aufrufen:
   `https://schwimmen.foerderverein-enztalbad.de/benutzer_anlegen.php`
3. Die Passwörter werden verschlüsselt in die Datenbank geschrieben.
4. **Wichtig:** `benutzer_anlegen.php` danach vom Server löschen oder
   umbenennen (z. B. `benutzer_anlegen.php.bak`), damit niemand unbefugt
   weitere Benutzer anlegen kann.

### 4. Login testen
`https://schwimmen.foerderverein-enztalbad.de/login.php` aufrufen und anmelden.
Nach dem Login landet man auf der Startseite; alle anderen Seiten sind
ebenfalls nur noch nach Anmeldung erreichbar.

## Sicherheitshinweise

- Verwende starke Passwörter.
- Für produktiven Einsatz **HTTPS** erzwingen (Subdomain bei der Telekom).
- Das Login nutzt Sessions und CSRF-Tokens.
- Passwörter werden mit `password_hash(…, PASSWORD_DEFAULT)` gehasht (bcrypt);
  die Verifizierung erfolgt mit `password_verify()`.
- Weitere Benutzer können jederzeit hinzugefügt werden, indem das Anlege-Skript
  erneut (temporär) hochgeladen und ausgeführt wird.
