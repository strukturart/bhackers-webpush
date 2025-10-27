<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Preflight-Request sofort beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

try {
    $db = new PDO('sqlite:subscriptions.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tabelle erstellen, falls sie nicht existiert
    $db->exec("
        CREATE TABLE IF NOT EXISTS subscriptions (
            user_id TEXT PRIMARY KEY,
            subscription_data TEXT NOT NULL,
            last_push_sent_at TEXT,
            created_at TEXT
        )
    ");

    // Prüfen, ob 'created_at' existiert – falls nicht, hinzufügen (ohne Default)
    $columns = $db->query("PRAGMA table_info(subscriptions)")->fetchAll(PDO::FETCH_ASSOC);
    $hasCreatedAt = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'created_at') {
            $hasCreatedAt = true;
            break;
        }
    }

    if (!$hasCreatedAt) {
        $db->exec("ALTER TABLE subscriptions ADD COLUMN created_at TEXT");
    }
} catch (PDOException $e) {
    echo json_encode(["error" => "DB error: " . $e->getMessage()]);
    exit();
}




// Die empfangene JSON-Daten auslesen
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['user_id'])) {
    echo json_encode(["error" => "User-ID missing"]);
    exit();
}

$userId = $data['user_id'];
$action = $_GET['action'] ?? '';

// Aktionen ausführen
if ($action === "add") {

    try {
        $now = date('c'); // ISO 8601 Format
        $stmt = $db->prepare("
            INSERT INTO subscriptions (user_id, subscription_data, created_at)
            VALUES (?, ?, ?)
            ON CONFLICT(user_id)
            DO UPDATE SET 
                subscription_data = excluded.subscription_data
        ");
        $stmt->execute([$userId, json_encode($data), $now]);
        echo json_encode(["message" => "Subscription gespeichert"]);
    } catch (PDOException $e) {
        echo json_encode(["error" => "DB Error: " . $e->getMessage()]);
    }
} elseif ($action === "delete") {
    // Subscription löschen
    try {
        $stmt = $db->prepare("DELETE FROM subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(["message" => "Subscription deleted"]);
    } catch (PDOException $e) {
        echo json_encode(["error" => "DB Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["error" => "Ungültige Aktion"]);
}
