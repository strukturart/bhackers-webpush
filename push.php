<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

require_once  'vendor/autoload.php';

use Dotenv\Dotenv;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;


function ensureColumnExists(PDO $db, string $table, string $column, string $type = 'TEXT')
{
    $stmt = $db->query("PRAGMA table_info($table)");
    foreach ($stmt as $col) {
        if ($col['name'] === $column) {
            return; // Spalte existiert bereits
        }
    }

    // Spalte hinzufügen
    $db->exec("ALTER TABLE $table ADD COLUMN $column $type");
}


try {


    // Load the .env file
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    // Access the VAPID keys
    $publicKey = $_ENV['VAPID_PUBLICKEY'];
    $privateKey = $_ENV['VAPID_PRIVATEKEY'];

    // WebPush configuration
    $webPush = new WebPush([
        'VAPID' => [
            'subject' => 'mailto:strukturart@gmail.com',
            'publicKey' => $publicKey,
            'privateKey' => $privateKey
        ]
    ]);

    // Connect to the SQLite database
    $db = new PDO('sqlite:subscriptions.db'); // Your SQLite database

    // Read the request data
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $data['user_id']; // The recipient

    $message = [
        'title' => 'New Message',
        'body'  => $data['message'] ?? 'You have a new message!',
    ];

    // Retrieve subscription data
    $stmt = $db->prepare("SELECT subscription_data FROM subscriptions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if a user was found
    if ($row) {


        ensureColumnExists($db, 'subscriptions', 'last_push_sent_at');


        // Decode the subscription data
        $subscriptionData = json_decode($row['subscription_data'], true);


        // Access the inner 'subscription' object
        if (isset($subscriptionData['subscription'])) {
            $subscriptionData = $subscriptionData['subscription'];

            // Check if the subscription data is complete
            if (!isset($subscriptionData['endpoint'], $subscriptionData['keys']['p256dh'], $subscriptionData['keys']['auth'])) {
                echo json_encode(["error" => "Invalid subscription data"]);
                exit;
            }

            // Create the subscription object
            $subscriptionObject = Subscription::create([
                'endpoint' => $subscriptionData['endpoint'],
                'keys' => [
                    'p256dh' => $subscriptionData['keys']['p256dh'],
                    'auth' => $subscriptionData['keys']['auth']
                ]
            ]);

            // Send the push notification
            try {
                $report = $webPush->sendOneNotification(
                    $subscriptionObject,
                    json_encode($message)
                );



                // Check if the message was successfully sent
                if ($report->isSuccess()) {

                    $stmt = $db->prepare("UPDATE subscriptions SET last_push_sent_at = :timestamp WHERE user_id = :user_id");
                    $stmt->execute([
                        ':timestamp' => date('Y-m-d H:i:s'),
                        ':user_id' => $userId
                    ]);


                    echo json_encode(["success" => "Push notification successfully sent."]);
                    exit;
                } else {
                    echo json_encode(["error" => "Error sending push notification."]);
                    exit;
                }
            } catch (Exception $e) {
                echo json_encode(["error" => "Error: " . $e->getMessage()]);
            }

            exit;
        } else {
            echo json_encode(["error" => "Missing 'subscription' object"]);
            exit;
        }
    } else {
        echo json_encode(["error" => "User not found."]);
    }
} catch (Exception $e) {
    // Fehler bei anderen Problemen
    echo json_encode(["error" =>  $e->getMessage()]);
}
