<?php
// Datenbankverbindung einbinden
require_once 'config.php';

// Schwimmer-ID prüfen
if (!isset($_GET['schwimmer_id']) || !is_numeric($_GET['schwimmer_id'])) {
    header("Location: /VAIBad_2/webapp/schwimmerliste.php");
    exit();
}
$schwimmer_id = intval($_GET['schwimmer_id']);

// Schwimmerdaten abrufen
$stmt = $conn->prepare("SELECT id, vorname, nachname FROM Schwimmer WHERE id = ?");
$stmt->bind_param("i", $schwimmer_id);
$stmt->execute();
$result = $stmt->get_result();
$schwimmer = $result->fetch_assoc();
$stmt->close();

if (!$schwimmer) {
    header("Location: /VAIBad_2/webapp/schwimmerliste.php");
    exit();
}

// Alle Sponsoren abrufen
$sponsoren_result = $conn->query("SELECT id, name, betrag_pro_bahn, `limit` FROM Sponsoren ORDER BY name");

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sponsoren_id = intval($_POST['sponsoren_id']);
    // Validierung
    $fehler = [];
    if ($sponsoren_id <= 0) {
        $fehler[] = "Bitte wählen Sie einen Sponsor aus.";
    } else {
        // Prüfen, ob die Verknüpfung bereits existiert
        $stmt = $conn->prepare("SELECT id FROM schwimmer_sponsor WHERE schwimmer_id = ? AND sponsoren_id = ?");
        $stmt->bind_param("ii", $schwimmer_id, $sponsoren_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $fehler[] = "Diese Verknüpfung existiert bereits.";
        }
        $stmt->close();
    }

    if (empty($fehler)) {
        // Verknüpfung einfügen
        $stmt = $conn->prepare("INSERT INTO schwimmer_sponsor (schwimmer_id, sponsoren_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $schwimmer_id, $sponsoren_id);
        $stmt->execute();
        $stmt->close();
        // Auf der Seite bleiben, damit mehrere Sponsoren nacheinander verknüpft werden können
        header("Location: /VAIBad_2/webapp/neue_verknuepfung.php?schwimmer_id=" . $schwimmer_id . "&saved=1");
        exit();
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
        <title>Neue Verknüpfung - VAIBad</title>
        <link rel="stylesheet" href="/VAIBad_2/webapp/css/style.css">
    </head>
    <body>';
}
?>

<div class="container">
    <h1>Neue Verknüpfung für <?php echo htmlspecialchars($schwimmer['vorname'] . ' ' . $schwimmer['nachname']); ?></h1>

    <!-- Erfolgsmeldung -->
    <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
        <div class="success-box">
            Verknüpfung gespeichert. Weitere Sponsoren hinzufügen oder "Fertig" klicken.
        </div>
    <?php endif; ?>

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

    <!-- Formular -->
    <form method="POST" action="/VAIBad_2/webapp/neue_verknuepfung.php?schwimmer_id=<?php echo $schwimmer_id; ?>" class="form">
        <div class="form-group">
            <label for="sponsor_filter">Sponsor filtern:</label>
            <input type="text" id="sponsor_filter" placeholder="Sponsorname eingeben..." onkeyup="filtereSponsoren()" style="width:100%; padding:.4rem .6rem; margin-bottom:.5rem;">
            <label for="sponsoren_id">Sponsor auswählen:</label>
            <select id="sponsoren_id" name="sponsoren_id" required>
                <option value="">-- Bitte auswählen --</option>
                <?php while ($sponsor = $sponsoren_result->fetch_assoc()): ?>
                    <option value="<?php echo $sponsor['id']; ?>">
                        <?php echo htmlspecialchars($sponsor['name']); ?>
                        (<?php echo number_format($sponsor['betrag_pro_bahn'], 2, ',', '.'); ?> € pro Bahn,
                        <?php echo ($sponsor['limit'] !== null) ? 'Limit: ' . htmlspecialchars($sponsor['limit']) : 'Ohne Limit'; ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Verknüpfung speichern</button>
            <a href="/VAIBad_2/webapp/schwimmerliste.php" class="btn btn-secondary">Abbrechen</a>
            <a href="/VAIBad_2/webapp/schwimmerliste.php" class="btn btn-primary">Fertig</a>
        </div>
    </form>
</div>

<script>
function filtereSponsoren() {
    var suchbegriff = document.getElementById('sponsor_filter').value.toLowerCase();
    var select = document.getElementById('sponsoren_id');
    for (var i = 1; i < select.options.length; i++) {
        var text = select.options[i].text.toLowerCase();
        select.options[i].style.display = (suchbegriff === '' || text.indexOf(suchbegriff) !== -1) ? '' : 'none';
    }
}
</script>

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
