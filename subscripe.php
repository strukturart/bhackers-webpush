<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Falls eine OPTIONS-Anfrage kommt (Preflight-Request), beende die Anfrage sofort
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // Preflight-Request zulassen
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // 405 = Method Not Allowed
    exit();
}



try {
    // Verbindung zur SQLite-Datenbank
    $db = new PDO('sqlite:subscriptions.db'); // Datenbank-Datei
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Fehlerbehandlung aktivieren

    // Tabelle erstellen, falls sie nicht existiert
    $db->exec("CREATE TABLE IF NOT EXISTS subscriptions (
        user_id TEXT PRIMARY KEY,
        subscription_data TEXT NOT NULL
    )");

    // Subscription von der Anfrage lesen
    $subscription = json_decode(file_get_contents('php://input'), true);

    if (!$subscription || !isset($subscription['user_id'])) {
        throw new Exception('Subscription failed');
    }

    $userId = $subscription['user_id']; // Eindeutige Benutzer-ID

    // Subscription speichern oder aktualisieren
    $stmt = $db->prepare("INSERT INTO subscriptions (user_id, subscription_data) 
                          VALUES (?, ?) 
                          ON CONFLICT(user_id) 
                          DO UPDATE SET subscription_data = excluded.subscription_data");
    $stmt->execute([$userId, json_encode($subscription)]);


    // Die gespeicherte Subscription abrufen
    $stmt = $db->prepare("SELECT subscription_data FROM subscriptions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $savedSubscription = $stmt->fetchColumn();

    echo json_encode([
        "message" => "Subscription stored.",
        "data" => json_decode($savedSubscription, true)
    ]);
} catch (PDOException $e) {
    // Fehler bei der Datenbankverbindung oder -abfrage
    echo "DB error: " . $e->getMessage();
} catch (Exception $e) {
    // Fehler bei anderen Problemen
    echo "Error " . $e->getMessage();
}
