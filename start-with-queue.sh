#!/bin/bash

echo "=========================================="
echo "🚀 تشغيل SaaS Marketplace مع Queue Worker"
echo "=========================================="
echo ""

# التحقق من أن نحن في المجلد الصحيح
cd /home/vboxuser/saas-marketplace

# إيقاف أي queue workers قديمة
echo "🔄 إيقاف Queue Workers القديمة..."
pkill -f "artisan queue:work" 2>/dev/null || true
sleep 1

# تشغيل Queue Worker في الخلفية
echo "✅ تشغيل Queue Worker في الخلفية..."
nohup php artisan queue:work --sleep=3 --tries=3 > storage/logs/queue.log 2>&1 &
QUEUE_PID=$!
echo "   Queue Worker PID: $QUEUE_PID"
echo ""

# تشغيل Laravel Server
echo "✅ تشغيل Laravel Server على المنفذ 4000..."
echo ""
echo "=========================================="
echo "✨ النظام جاهز!"
echo "=========================================="
echo ""
echo "📋 المعلومات:"
echo "   • المتجر: http://localhost:4000"
echo "   • Keycloak: http://localhost:8090"
echo "   • Queue Worker PID: $QUEUE_PID"
echo ""
echo "📝 مراقبة Logs:"
echo "   • Queue: tail -f storage/logs/queue.log"
echo "   • Laravel: tail -f storage/logs/laravel.log"
echo ""
echo "⚠️  لإيقاف Queue Worker:"
echo "   kill $QUEUE_PID"
echo ""
echo "=========================================="
echo ""

php artisan serve --port=4000
