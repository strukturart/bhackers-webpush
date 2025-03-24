<?php
// .env einlesen
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

// Zugriffsschutz
$validKey = $_ENV['SUBSCRIPTION_VIEW_KEY'] ?? null;
$providedKey = $_GET['key'] ?? '';

if (!$validKey || $providedKey !== $validKey) {
    http_response_code(403);
    echo "Zugriff verweigert.";
    exit;
}

// Verbindung zur SQLite-Datenbank
try {
    $db = new PDO('sqlite:subscriptions.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->query("SELECT * FROM subscriptions");
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Datenbankfehler: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Subscription Übersicht</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 2rem;
            background: #f8f9fa;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            background: white;
        }

        th,
        td {
            padding: 0.75rem;
            border: 1px solid #ccc;
            text-align: left;
        }

        th {
            background: #eee;
        }

        pre {
            margin: 0;
        }
    </style>
</head>

<body>
    <h1>Subscription Übersicht</h1>
    <?php if (empty($subscriptions)): ?>
        <p>Keine Subscriptions vorhanden.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Last Push</th>

                    <th>Subscription Data (JSON)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscriptions as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['user_id']) ?></td>
                        <td><?= htmlspecialchars($row['last_push_sent_at'] ?? '-') ?></td>

                        <td>
                            <pre><?= htmlspecialchars($row['subscription_data']) ?></pre>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</body>

</html>