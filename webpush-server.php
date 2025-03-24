<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");



require_once  'vendor/autoload.php';

use Dotenv\Dotenv;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

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

    // Decode the subscription data
    $subscriptionData = json_decode($row['subscription_data'], true);

    // Check if decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["error" => "Error decoding JSON data: " . json_last_error_msg()]);
        exit;
    }

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
            if ($report->isSuccessful()) {
                echo json_encode(["success" => "Push notification successfully sent."]);
            } else {
                echo json_encode(["error" => "Error sending push notification."]);
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
