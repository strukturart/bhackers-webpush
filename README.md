# bhackers-webpush

#create table
sqlite3 subscriptions.db "CREATE TABLE subscriptions (user_id TEXT PRIMARY KEY, subscription_data TEXT NOT NULL);"

#test
sqlite3 subscriptions.db ".tables"

#subscription
curl -X POST http://localhost:8000/subscribe.php \
 -H "Content-Type: application/json" \
 -d '{"user_id": "user123", "subscription": {"endpoint": "https://fcm.googleapis.com/fcm/send/example", "keys": {"p256dh": "xyz", "auth": "abc"}}}'

#send test
curl -X POST http://localhost:8000/webpush-server.php \
 -H "Content-Type: application/json" \
 -d '{"user_id": "user123", "message": "Hallo, das ist ein Test!"}'
