#!/bin/bash
# Script to restart queue worker - يُستدعى تلقائياً بعد تحديث الكود

cd /home/vboxuser/saas-marketplace

# Kill existing queue workers
pkill -f "artisan queue:work"

# Wait for processes to stop
sleep 2

# Start new queue worker
nohup php artisan queue:work --sleep=3 --tries=3 --timeout=900 > storage/logs/queue.log 2>&1 &

echo "✅ Queue worker restarted successfully!"
