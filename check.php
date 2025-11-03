<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use GET."]);
    exit();
}

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;

try {
    // Load environment
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    // Connect to SQLite
    $db = new PDO('sqlite:subscriptions.db');

    // Get user_id from URL parameter
    $userId = $_GET['id'] ?? null;

    if (!$userId) {
        echo json_encode(["error" => "Missing user_id parameter."]);
        exit();
    }

    // Check if subscription exists
    $stmt = $db->prepare("SELECT subscription_data FROM subscriptions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode([
            "registered" => false,
            "message" => "User is not registered for push notifications."
        ]);
        exit();
    }

    // Decode subscription data
    $subscriptionData = json_decode($row['subscription_data'], true);

    if (!isset($subscriptionData['subscription'])) {
        echo json_encode([
            "registered" => false,
            "message" => "Invalid subscription format."
        ]);
        exit();
    }

    $sub = $subscriptionData['subscription'];
    $isValid = isset($sub['endpoint'], $sub['keys']['p256dh'], $sub['keys']['auth']);

    echo json_encode([
        "registered" => $isValid,
        "message" => $isValid
            ? "User is registered for push notifications."
            : "Incomplete subscription data."
    ]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
