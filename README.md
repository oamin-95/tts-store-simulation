# متجر SaaS - نظام إدارة الاشتراكات مع Keycloak SSO

نظام مركزي لإدارة الاشتراكات في منصات Laravel متعددة (Frappe/Kayan ERP، منصة التدريب، منصة الخدمات) مع إدارة مركزية للهوية عبر Keycloak.

## جدول المحتويات

1. [نظرة عامة](#نظرة-عامة)
2. [البنية المعمارية](#البنية-المعمارية)
3. [التثبيت والإعداد](#التثبيت-والإعداد)
4. [آلية عمل النظام](#آلية-عمل-النظام)
5. [الملفات الأساسية](#الملفات-الأساسية)
6. [API Endpoints](#api-endpoints)
7. [إدارة Keycloak Realms](#إدارة-keycloak-realms)
8. [Queue Workers](#queue-workers)
9. [استكشاف الأخطاء](#استكشاف-الأخطاء)

---

## نظرة عامة

المتجر (Marketplace) هو نظام مركزي مسؤول عن:

- **إدارة المستخدمين**: تسجيل المستخدمين الجدد وإدارة بياناتهم
- **إدارة الاشتراكات**: إنشاء وإدارة اشتراكات المستخدمين في المنتجات المختلفة
- **إدارة Keycloak Realms**: إنشاء realm معزول لكل مستخدم في Keycloak
- **ربط المنتجات**: توفير API للمنتجات للحصول على معلومات Realm والاتصال
- **عرض لوحة تحكم**: عرض معلومات Keycloak وروابط الوصول لمدير النظام

---

## البنية المعمارية

### المنتجات المدعومة:

1. **Frappe/Kayan ERP** (منفذ على Frappe Framework)
2. **منصة التدريب** (Training Platform - Laravel)
3. **منصة الخدمات** (Services Platform - Laravel)

### تدفق البيانات:

```
المستخدم
    ↓
المتجر (Marketplace)
    ↓
1. إنشاء حساب مستخدم
2. إنشاء Keycloak Realm معزول
3. تخزين معلومات Realm
    ↓
عند الاشتراك في منتج:
    ↓
المتجر → Product API
    ↓
المنتج يطلب Realm Info من المتجر
    ← realm_id, keycloak_url
    ↓
المنتج ينشئ Client في Realm
```

---

## التثبيت والإعداد

### 1. المتطلبات:

- PHP 8.2+
- MySQL/MariaDB
- Composer
- Keycloak Server (http://localhost:8080)

### 2. التثبيت:

```bash
# استنساخ المشروع
git clone https://github.com/oamin-95/saas-marketplace.git
cd saas-marketplace

# تثبيت الحزم
composer install

# نسخ ملف البيئة
cp .env.example .env

# توليد مفتاح التطبيق
php artisan key:generate

# إعداد قاعدة البيانات
php artisan migrate

# تشغيل Seeder (إنشاء المنتجات والخطط)
php artisan db:seed
```

### 3. إعداد ملف .env:

```env
APP_NAME="SaaS Marketplace"
APP_URL=http://localhost:4000
APP_PORT=4000

# قاعدة البيانات
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marketplace
DB_USERNAME=root
DB_PASSWORD=root

# Keycloak Admin
KEYCLOAK_URL=http://localhost:8080
KEYCLOAK_ADMIN_USERNAME=admin
KEYCLOAK_ADMIN_PASSWORD=admin

# عناوين المنتجات
FRAPPE_URL=http://localhost:8000
TRAINING_URL=http://localhost:5000
SERVICES_URL=http://localhost:7000

# Queue
QUEUE_CONNECTION=database
```

### 4. تشغيل التطبيق:

```bash
# تشغيل الخادم
php artisan serve --host=0.0.0.0 --port=4000

# تشغيل Queue Worker (في terminal منفصل)
php artisan queue:work --tries=3 --sleep=3 --timeout=900
```

---

## آلية عمل النظام

### 1. تسجيل مستخدم جديد:

عند تسجيل مستخدم جديد في المتجر، يحدث التالي تلقائياً:

```
1. إنشاء سجل في جدول users
2. إنشاء Keycloak Realm معزول للمستخدم
3. إنشاء Keycloak User في Realm
4. تخزين معلومات Realm في جدول users
```

**الكود المسؤول:** `App\Models\User` (Model Events)

```php
protected static function boot()
{
    parent::boot();
    
    static::created(function ($user) {
        // إنشاء Keycloak Realm
        dispatch(new SetupKeycloakRealmJob($user));
    });
}
```

---

### 2. إنشاء Keycloak Realm:

**Job:** `App\Jobs\SetupKeycloakRealmJob`

**الخطوات:**

#### أ. إنشاء Realm Name:

```php
$realmName = "tenant-{$user->id}";
// مثال: tenant-92
```

#### ب. إنشاء Realm في Keycloak:

```php
POST http://localhost:8080/admin/realms
{
    "realm": "tenant-92",
    "enabled": true,
    "displayName": "Realm للمستخدم {user_name}",
    "registrationAllowed": false,
    "resetPasswordAllowed": true,
    "rememberMe": true,
    "loginWithEmailAllowed": true,
    "duplicateEmailsAllowed": false,
    "sslRequired": "none"
}
```

#### ج. إنشاء Keycloak User في Realm:

```php
POST http://localhost:8080/admin/realms/tenant-92/users
{
    "username": "user@example.com",
    "email": "user@example.com",
    "firstName": "User",
    "lastName": "Name",
    "enabled": true,
    "emailVerified": true,
    "credentials": [{
        "type": "password",
        "value": "user_password",
        "temporary": false
    }]
}
```

#### د. حفظ معلومات Realm:

```php
$user->update([
    'keycloak_realm' => 'tenant-92',
    'keycloak_user_id' => '{keycloak_user_uuid}',
    'keycloak_configured' => true,
]);
```

**جدول users:**

| id | name | email | keycloak_realm | keycloak_user_id | keycloak_configured |
|----|------|-------|----------------|------------------|---------------------|
| 92 | أحمد | ahmed@example.com | tenant-92 | uuid-xxx-xxx | 1 |

---

### 3. إنشاء اشتراك في منتج:

عند اشتراك مستخدم في أحد المنتجات:

**Endpoint:** `POST /api/subscriptions`

**Request:**
```json
{
    "user_id": 92,
    "product_id": 2,
    "plan_id": 1,
    "company_name": "شركة الأصل"
}
```

**ما يحدث:**

1. **إنشاء سجل Subscription:**

```php
$subscription = Subscription::create([
    'user_id' => 92,
    'product_id' => 2, // Training Platform
    'plan_id' => 1,
    'status' => 'active',
    'starts_at' => now(),
    'ends_at' => now()->addYear(),
]);
```

2. **استدعاء API المنتج لإنشاء Tenant:**

```php
// للمنتجات Laravel (Training/Services)
POST http://localhost:5000/api/tenants/create
{
    "user_id": "89772",
    "company_name": "شركة الأصل",
    "email": "ahmed@example.com"
}

// للمنتجات Frappe
POST http://localhost:8000/api/method/create_site
{
    "user_id": "89772",
    "company_name": "شركة الأصل",
    "admin_email": "ahmed@example.com"
}
```

3. **المنتج ينشئ Tenant ثم يطلب معلومات Realm:**

```php
POST http://localhost:4000/api/keycloak/get-realm
{
    "tenant_id": "training-user-89772-1763532310",
    "product": "training"
}
```

**Response:**
```json
{
    "success": true,
    "realm_id": "tenant-92",
    "user_id": "89772",
    "keycloak_url": "http://localhost:8080"
}
```

4. **المنتج ينشئ Keycloak Client في Realm المشترك:**

```php
// في المنتج (Training Platform)
POST http://localhost:8080/admin/realms/tenant-92/clients
{
    "clientId": "training-user-89772-1763532310-client",
    "redirectUris": [
        "http://training-user-89772-1763532310.localhost:5000/*",
        "http://training-user-89772-1763532310.localhost:5000/auth/keycloak/callback"
    ],
    ...
}
```

---

### 4. Shared Realm Architecture:

كل مستخدم له **Realm واحد مشترك** بين جميع منتجاته:

```
Realm: tenant-92 (للمستخدم أحمد)
├── User: ahmed@example.com
├── Groups (من جميع المنتجات)
│   ├── super_admin (Frappe)
│   ├── Accounts Manager (Frappe)
│   ├── lmsrole (Training)
│   ├── servicerole (Services)
│   └── serviceuser (Services)
└── Clients (لكل منتج)
    ├── kayan-user-89772-1763530000-client (Frappe)
    ├── training-user-89772-1763532310-client (Training)
    └── services-user-89772-1763532307-client (Services)
```

**الفوائد:**

- تسجيل دخول موحد (SSO) عبر جميع المنتجات
- إدارة مركزية للمستخدمين
- مشاركة Groups/Roles بين المنتجات (مع التصفية)
- عزل كامل بين مستخدمين مختلفين

---

## الملفات الأساسية

### 1. Controllers:

#### `app/Http/Controllers/SubscriptionController.php`
المسؤول عن:
- إنشاء اشتراكات جديدة
- استدعاء API المنتجات لإنشاء Tenants
- إدارة حالة الاشتراكات

**الدوال الرئيسية:**
```php
// إنشاء اشتراك جديد
public function store(Request $request)

// عرض الاشتراكات
public function index()

// إلغاء اشتراك
public function cancel($id)
```

#### `app/Http/Controllers/Api/KeycloakIntegrationController.php`
المسؤول عن:
- توفير API للمنتجات للحصول على معلومات Realm
- التحقق من صحة طلبات المنتجات

**الدوال الرئيسية:**
```php
// إرجاع معلومات Realm للمنتج
public function getRealm(Request $request)
{
    // التحقق من tenant_id و product
    $userId = $this->extractUserIdFromTenantId($request->tenant_id);
    
    $user = User::find($userId);
    
    return response()->json([
        'success' => true,
        'realm_id' => $user->keycloak_realm,
        'user_id' => $user->id,
        'keycloak_url' => config('services.keycloak.url'),
    ]);
}
```

---

### 2. Services:

#### `app/Services/KeycloakService.php`
خدمة شاملة للتعامل مع Keycloak Admin API:

**الدوال الرئيسية:**

| الدالة | الوصف |
|--------|-------|
| `getAdminToken()` | الحصول على Access Token للإدارة |
| `createRealm()` | إنشاء Realm جديد معزول |
| `createUser()` | إنشاء مستخدم في Realm |
| `setUserPassword()` | تعيين كلمة مرور للمستخدم |
| `getUserInfo()` | الحصول على معلومات المستخدم |
| `deleteRealm()` | حذف Realm |

**مثال الاستخدام:**

```php
$keycloakService = app(KeycloakService::class);

// إنشاء Realm
$realm = $keycloakService->createRealm("tenant-92", "شركة الأصل");

// إنشاء مستخدم
$userId = $keycloakService->createUser(
    "tenant-92",
    "ahmed@example.com",
    "أحمد",
    "محمد"
);

// تعيين كلمة مرور
$keycloakService->setUserPassword("tenant-92", $userId, "password123");
```

---

### 3. Jobs:

#### `app/Jobs/SetupKeycloakRealmJob.php`
يعمل تلقائياً عند إنشاء مستخدم جديد:

```php
public function handle(KeycloakService $keycloakService)
{
    // إنشاء Realm
    $realmName = "tenant-{$this->user->id}";
    $keycloakService->createRealm($realmName, $this->user->name);
    
    // إنشاء User
    $keycloakUserId = $keycloakService->createUser(
        $realmName,
        $this->user->email,
        $this->user->name,
        ''
    );
    
    // تعيين كلمة المرور
    $keycloakService->setUserPassword(
        $realmName,
        $keycloakUserId,
        $this->user->password_plain
    );
    
    // حفظ المعلومات
    $this->user->update([
        'keycloak_realm' => $realmName,
        'keycloak_user_id' => $keycloakUserId,
        'keycloak_configured' => true,
    ]);
}
```

---

### 4. Models:

#### `app/Models/User.php`
```php
protected $fillable = [
    'name',
    'email',
    'password',
    'keycloak_realm',
    'keycloak_user_id',
    'keycloak_configured',
];

// Event لإنشاء Realm تلقائياً
protected static function boot()
{
    parent::boot();
    
    static::created(function ($user) {
        dispatch(new SetupKeycloakRealmJob($user));
    });
}
```

#### `app/Models/Subscription.php`
```php
protected $fillable = [
    'user_id',
    'product_id',
    'plan_id',
    'status',
    'starts_at',
    'ends_at',
];

// علاقات
public function user()
{
    return $this->belongsTo(User::class);
}

public function product()
{
    return $this->belongsTo(Product::class);
}

public function plan()
{
    return $this->belongsTo(Plan::class);
}
```

#### `app/Models/Product.php`
```php
protected $fillable = [
    'name',
    'slug',
    'description',
    'api_endpoint',
    'type', // 'frappe' or 'laravel'
];

// المنتجات المدعومة:
// 1. Kayan ERP (Frappe)
// 2. Training Platform (Laravel)
// 3. Services Platform (Laravel)
```

---

### 5. Database Schema:

#### جدول users:
```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    keycloak_realm VARCHAR(255),
    keycloak_user_id VARCHAR(255),
    keycloak_configured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### جدول subscriptions:
```sql
CREATE TABLE subscriptions (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    product_id BIGINT,
    plan_id BIGINT,
    status VARCHAR(50), -- 'active', 'cancelled', 'expired'
    starts_at TIMESTAMP,
    ends_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (plan_id) REFERENCES plans(id)
);
```

#### جدول products:
```sql
CREATE TABLE products (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    slug VARCHAR(255),
    description TEXT,
    api_endpoint VARCHAR(255),
    type VARCHAR(50), -- 'frappe' or 'laravel'
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## API Endpoints

### 1. للمنتجات (Products API):

#### الحصول على معلومات Realm:

```
POST /api/keycloak/get-realm
Content-Type: application/json

{
    "tenant_id": "training-user-89772-1763532310",
    "product": "training"
}
```

**Response:**
```json
{
    "success": true,
    "realm_id": "tenant-92",
    "user_id": "89772",
    "keycloak_url": "http://localhost:8080"
}
```

---

### 2. للمستخدمين (User API):

#### إنشاء اشتراك:

```
POST /api/subscriptions
Content-Type: application/json

{
    "user_id": 92,
    "product_id": 2,
    "plan_id": 1,
    "company_name": "شركة الأصل"
}
```

**Response:**
```json
{
    "success": true,
    "subscription_id": 123,
    "tenant_id": "training-user-89772-1763532310",
    "admin_url": "http://training-user-89772-1763532310.localhost:5000/admin",
    "credentials": {
        "email": "ahmed@example.com",
        "password": "generated_password"
    }
}
```

---

## إدارة Keycloak Realms

### عرض معلومات Keycloak في لوحة التحكم:

**الصفحة:** `resources/views/admin/keycloak-info.blade.php`

**المعلومات المعروضة:**

- عنوان Keycloak Server: `http://localhost:8080`
- Realm Name: `tenant-92`
- رابط Admin Console: `http://localhost:8080/admin/tenant-92/console`
- Keycloak User ID: `{uuid}`
- حالة الإعداد: مُفعل / غير مُفعل

**كيفية الوصول:**

1. تسجيل الدخول كمدير نظام في المتجر
2. الذهاب إلى "إعدادات Keycloak"
3. عرض المعلومات والروابط

---

### مشاركة معلومات Keycloak مع المنتجات:

**التخزين في المتجر:**

```php
// جدول users
[
    'keycloak_realm' => 'tenant-92',
    'keycloak_user_id' => 'uuid-xxx-xxx',
    'keycloak_configured' => true,
]
```

**الإرسال للمنتجات عبر API:**

```php
// عندما يطلب منتج معلومات Realm
return [
    'realm_id' => $user->keycloak_realm,
    'keycloak_url' => config('services.keycloak.url'),
    'user_id' => $user->id,
];
```

**استخدام المنتجات:**

```php
// في منتج Laravel (Training/Services)
$response = Http::post($marketplaceUrl . '/api/keycloak/get-realm', [
    'tenant_id' => $tenantId,
    'product' => 'training',
]);

$realmName = $response['realm_id']; // tenant-92
$keycloakUrl = $response['keycloak_url']; // http://localhost:8080

// إنشاء Client في Realm
$this->createClient($realmName, $clientId, $redirectUris);
```

---

## Queue Workers

### لماذا نحتاج Queue Workers؟

- إنشاء Keycloak Realm يستغرق وقتاً
- عدم حجب طلب تسجيل المستخدم
- معالجة Jobs الفاشلة وإعادة المحاولة

### تشغيل Queue Worker:

```bash
cd /home/vboxuser/saas-marketplace
php artisan queue:work --tries=3 --sleep=3 --timeout=900
```

### للإنتاج (Production):

**ملف Supervisor:** `/etc/supervisor/conf.d/marketplace-queue.conf`

```ini
[program:marketplace-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /home/vboxuser/saas-marketplace/artisan queue:work --sleep=3 --tries=3 --timeout=900
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=vboxuser
numprocs=1
redirect_stderr=true
stdout_logfile=/home/vboxuser/marketplace-queue.log
stopwaitsecs=3600
```

---

## استكشاف الأخطاء

### 1. Realm لم يتم إنشاؤه:

**الأسباب:**
- Queue Worker لا يعمل
- Keycloak Server غير متاح
- بيانات اعتماد Admin خاطئة

**الحل:**
```bash
# التحقق من Queue Worker
ps aux | grep "queue:work"

# التحقق من Keycloak
curl http://localhost:8080

# فحص الـ Logs
tail -f storage/logs/laravel.log | grep SetupKeycloakRealmJob

# إعادة تشغيل Job يدوياً
php artisan tinker
>>> dispatch(new \App\Jobs\SetupKeycloakRealmJob(User::find(92)));
```

---

### 2. المنتج لا يحصل على Realm ID:

**المشكلة:** API endpoint غير صحيح أو tenant_id خاطئ.

**الحل:**
```bash
# اختبار API
curl -X POST http://localhost:4000/api/keycloak/get-realm \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":"training-user-89772-1763532310","product":"training"}'

# التحقق من استخراج user_id من tenant_id
php artisan tinker
>>> preg_match('/user-(\d+)-/', 'training-user-89772-1763532310', $matches);
>>> $matches[1]; // يجب أن يرجع 89772
```

---

### 3. مستخدم لا يستطيع تسجيل الدخول في Keycloak:

**الأسباب:**
- كلمة المرور غير صحيحة
- المستخدم غير مُفعل في Keycloak
- Realm غير موجود

**الحل:**
```bash
# إعادة تعيين كلمة المرور
php artisan tinker
>>> $user = User::find(92);
>>> app(\App\Services\KeycloakService::class)->setUserPassword(
    $user->keycloak_realm,
    $user->keycloak_user_id,
    'new_password'
);
```

---

## أوامر مفيدة

### إنشاء Realm يدوياً:

```bash
php artisan tinker
>>> $user = User::find(92);
>>> dispatch(new \App\Jobs\SetupKeycloakRealmJob($user));
```

---

### حذف Realm:

```bash
php artisan tinker
>>> $keycloak = app(\App\Services\KeycloakService::class);
>>> $keycloak->deleteRealm('tenant-92');
```

---

### عرض جميع Realms:

```bash
# عبر Keycloak Admin Console
# أو عبر API:
curl -X GET http://localhost:8080/admin/realms \
  -H "Authorization: Bearer {admin_token}"
```

---

## ملاحظات مهمة

1. **Realm Name Format**: `tenant-{user_id}` (مثل: tenant-92)

2. **عزل تام**: كل مستخدم له Realm معزول تماماً عن باقي المستخدمين

3. **Shared Realm بين المنتجات**: نفس المستخدم يستخدم نفس Realm لجميع منتجاته

4. **Client لكل Tenant**: كل اشتراك (tenant) له Client منفصل في Realm

5. **Queue Worker ضروري**: لإنشاء Realms تلقائياً

6. **API للمنتجات**: المنتجات تتصل بالمتجر عبر API للحصول على Realm ID

---

## الدعم

للمزيد من المساعدة، راجع:
- [README منصة الخدمات](https://github.com/oamin-95/es-sso-keycloak)
- [README منصة التدريب](https://github.com/oamin-95/lms-sso-keycloak)
- [Keycloak Documentation](https://www.keycloak.org/documentation)

---

**آخر تحديث:** 2025-11-19
