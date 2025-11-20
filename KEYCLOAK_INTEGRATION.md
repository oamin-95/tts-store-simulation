# 🔐 توثيق تكامل Keycloak مع نظام SaaS Marketplace

**📅 آخر تحديث:** 2025-11-20
**🔧 الإصدار:** 2.1

---

## 📋 جدول المحتويات

1. [نظرة عامة على الهيكل](#نظرة-عامة-على-الهيكل)
2. [المنتجات المدعومة](#المنتجات-المدعومة)
3. [العزل والأمان](#العزل-والأمان)
4. [آلية المصادقة](#آلية-المصادقة)
5. [ضبط Social Login Key وتسجيل الدخول](#ضبط-social-login-key-وتسجيل-الدخول)
6. [دورة حياة الاشتراك](#دورة-حياة-الاشتراك)
7. [مزامنة الأدوار والمجموعات](#مزامنة-الأدوار-والمجموعات)
8. [إدارة المستخدمين من المتجر](#إدارة-المستخدمين-من-المتجر)
9. [Queue Workers المطلوبة](#queue-workers-المطلوبة)
10. [الأكواد المسؤولة](#الأكواد-المسؤولة)
11. [استكشاف الأخطاء](#استكشاف-الأخطاء)

---

## 🏗️ نظرة عامة على الهيكل

### المكونات الأساسية

```
┌──────────────────────────────────────────────────────────────────┐
│                    Keycloak Container                            │
│                    (localhost:8090)                              │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Master Realm                                               │ │
│  │  └─ Service Account: saas-marketplace-admin               │ │
│  │     ├─ Client ID: saas-marketplace-admin                  │ │
│  │     ├─ Client Secret: JIBnwkGRp4VBPvV4d2bfg1N4iwXvEMvV    │ │
│  │     └─ Permissions:                                       │ │
│  │        ✓ create-realm                                     │ │
│  │        ✓ manage-clients (per realm)                       │ │
│  │        ✓ manage-users (per realm)                         │ │
│  │        ✓ view-realm (per realm)                           │ │
│  │        ✗ delete-realm (ممنوع للأمان)                     │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Tenant Realm: tenant-{user_id}                            │ │
│  │  ├─ Users (managed by marketplace)                        │ │
│  │  ├─ Groups (synced from products)                         │ │
│  │  └─ Clients:                                              │ │
│  │     ├─ training-platform                                  │ │
│  │     │  ├─ Type: Service Account                           │ │
│  │     │  ├─ Secret: {unique_secret_per_subscription}        │ │
│  │     │  └─ Permissions: manage-users, query-groups, etc.   │ │
│  │     ├─ services-platform                                  │ │
│  │     │  ├─ Type: Service Account                           │ │
│  │     │  ├─ Secret: {unique_secret_per_subscription}        │ │
│  │     │  └─ Permissions: manage-users, query-groups, etc.   │ │
│  │     └─ kayan-erp                                          │ │
│  │        ├─ Type: Service Account + Standard Flow          │ │
│  │        ├─ Secret: {unique_secret_per_subscription}        │ │
│  │        ├─ Redirect URIs: http://localhost:{port}/*        │ │
│  │        └─ Permissions: manage-users, manage-realm, etc.   │ │
│  └────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────┘
           ▲                    ▲                    ▲
           │                    │                    │
    ┌──────┴──────┐     ┌──────┴──────┐     ┌──────┴──────┐
    │ Marketplace │     │  Training   │     │  Services   │
    │  (Port 4000)│     │ (Port 5000) │     │ (Port 7000) │
    └─────────────┘     └─────────────┘     └─────────────┘
                                ▲
                                │
                        ┌───────┴────────┐
                        │   Kayan ERP    │
                        │ (Dynamic Port) │
                        │ (Port 40896+)  │
                        └────────────────┘
```

### مبدأ العزل (Isolation)

1. **عزل على مستوى Realm**: كل مستخدم في المتجر له Realm معزول خاص به
2. **عزل على مستوى Client**: كل منتج له Client منفصل بمفتاح سري فريد
3. **عزل على مستوى الصلاحيات**: كل Client له صلاحيات محدودة فقط لـ Realm الخاص به


---

## 🎯 المنتجات المدعومة

### 1. Training Platform (Laravel Multi-tenant)

**التقنيات:**
- Laravel 12
- Port: 5000

**آلية العمل:**
- Schema منفصل لكل tenant
- Domain-based routing: `training-user-{id}-{timestamp}.localhost`
- OIDC authentication via Keycloak

**الملفات الرئيسية:**
- `/training-platform/app/Http/Controllers/Api/KeycloakWebhookController.php`
- `/training-platform/app/Services/KeycloakAdminService.php`

---

### 2. Services Platform (Laravel Multi-tenant)

**التقنيات:**
- Laravel 12
- Port: 7000

**آلية العمل:**
- Schema منفصل لكل tenant
- Domain-based routing: `services-user-{id}-{timestamp}.localhost`
- OIDC authentication via Keycloak

**الملفات الرئيسية:**
- `/services-platform/app/Http/Controllers/Api/KeycloakWebhookController.php`
- `/services-platform/app/Services/KeycloakAdminService.php`
- `/services-platform/app/Http/Controllers/Auth/KeycloakController.php` - معالجة تسجيل الدخول
- `/services-platform/resources/views/filament/pages/auth/login.blade.php` - زر SSO

---

### 3. Kayan ERP (Frappe/ERPNext)

**التقنيات:**
- Frappe Framework 14
- ERPNext
- MariaDB
- Dynamic Ports: 40000+

**آلية العمل:**
- **موقع معزول تماماً** (fully isolated site) لكل tenant
- قاعدة بيانات منفصلة: `kayan-user-{id}`
- منفذ ديناميكي: يتم تخصيصه تلقائياً (مثال: 40896)
- Domain: `kayan-user-{id}.local`
- **OIDC authentication** via Keycloak + **Client Credentials** for role sync

**الميزات الخاصة:**
- ✅ عزل كامل على مستوى الموقع (site-level isolation)
- ✅ مزامنة الأدوار باستخدام OAuth 2.0 Client Credentials
- ✅ لا يحتاج لـ Keycloak Admin credentials
- ✅ كل موقع له App Server منفصل

**الملفات الرئيسية:**
- `/kayan-erp/apps/oidc_extended/oidc_extended/setup.py`
- `/kayan-erp/apps/oidc_extended/oidc_extended/sync.py`
- `/kayan-erp/scripts/create_tenant_site.py`

---

## 🔒 العزل والأمان

### 1. صلاحيات المتجر (Marketplace Service Account)

المتجر يتصل بـ Keycloak عبر Service Account بمفتاح سري ثابت:

```env
# .env
KEYCLOAK_SERVICE_CLIENT_ID=saas-marketplace-admin
KEYCLOAK_SERVICE_CLIENT_SECRET=JIBnwkGRp4VBPvV4d2bfg1N4iwXvEMvV
```

#### صلاحيات على Master Realm:
```
✓ default-roles-master       # الأدوار الافتراضية
✓ create-realm                # إنشاء Realms جديدة فقط
✗ delete-realm                # ممنوع (للأمان)
```

#### صلاحيات على كل Tenant Realm (ديناميكية):
```
✓ view-clients                # عرض Clients
✓ query-clients               # الاستعلام عن Clients
✓ manage-clients              # إدارة Clients (إنشاء، تعديل، منح صلاحيات)
✓ manage-identity-providers   # إدارة مزودي الهوية
✓ view-identity-providers     # عرض مزودي الهوية
✓ view-authorization          # عرض التفويضات
✓ query-users                 # الاستعلام عن المستخدمين
✓ query-realms                # الاستعلام عن Realms
✓ view-users                  # عرض المستخدمين
✓ manage-users                # إدارة المستخدمين (إنشاء، تعديل، حذف)
✓ query-groups                # الاستعلام عن المجموعات
✓ manage-realm                # إدارة إعدادات Realm
✗ impersonation               # ممنوع (للأمان)
```

**⚠️ ملاحظات أمنية مهمة:**
- المتجر **لا يمتلك** صلاحية `delete-realm` - لا يمكنه حذف Realms
- المتجر **لا يمتلك** صلاحية `impersonation` - لا يمكنه انتحال شخصية المستخدمين
- كل صلاحية مقيدة بـ Realm محدد فقط

---

### 2. صلاحيات المنتجات (Product Service Accounts)

#### Training & Services Platform Client:

```
Client ID: training-platform (أو services-platform)
Client Secret: {generated_unique_secret}
Grant Types: client_credentials
```

**الصلاحيات:**
```
✓ manage-users      # إدارة المستخدمين
✓ view-users        # عرض المستخدمين
✓ query-users       # الاستعلام عن المستخدمين
✓ query-groups      # الاستعلام عن المجموعات
✓ view-realm        # عرض معلومات Realm
✓ manage-realm      # إدارة Realm (محدود)
```

#### Kayan ERP Client:

```
Client ID: kayan-erp
Client Secret: {generated_unique_secret}
Grant Types: authorization_code, client_credentials
Standard Flow: Enabled (للمستخدمين)
Service Account: Enabled (لمزامنة الأدوار)
Direct Access Grants: Enabled
```

**الصلاحيات:**
```
✓ manage-users      # إدارة المستخدمين
✓ view-users        # عرض المستخدمين
✓ query-users       # الاستعلام عن المستخدمين
✓ query-groups      # الاستعلام عن المجموعات
✓ view-realm        # عرض معلومات Realm
✓ manage-realm      # إدارة Realm (إنشاء Groups)
```

**Redirect URIs الخاصة بـ Kayan ERP:**
```
http://localhost:{dynamic_port}/*
http://localhost:{dynamic_port}/api/method/oidc_extended.callback.custom/keycloak-{realm_id}
```

**مبدأ العزل:**
- كل Client Secret فريد لكل اشتراك
- المنتج يمكنه الوصول **فقط** للـ Realm الذي يمتلك Client فيه
- لا يمكن للمنتج الوصول لـ Realms أخرى حتى لو حصل على Client Secret
- Kayan ERP يستخدم **موقع معزول تماماً** بقاعدة بيانات منفصلة

---

## 🔐 آلية المصادقة

### 1. مصادقة المتجر مع Keycloak

**الملف:** `/app/Services/KeycloakService.php`

```php
class KeycloakService
{
    protected $serviceClientId;
    protected $serviceClientSecret;
    protected $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.keycloak.url');
        $this->serviceClientId = config('services.keycloak.service_account.client_id');
        $this->serviceClientSecret = config('services.keycloak.service_account.client_secret');
    }

    /**
     * الحصول على Access Token من Keycloak
     * يستخدم OAuth 2.0 Client Credentials Flow
     */
    protected function getAdminToken($forceRefresh = false)
    {
        if ($forceRefresh) {
            $this->accessToken = null;
        }

        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = $this->client->post('/realms/master/protocol/openid-connect/token', [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->serviceClientId,
                'client_secret' => $this->serviceClientSecret,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $this->accessToken = $data['access_token'];

        return $this->accessToken;
    }

    /**
     * تحديث Token (يُستدعى بعد إنشاء Realm أو Client)
     * مهم جداً: Keycloak يخزن صلاحيات Realms في الـ token
     */
    protected function refreshToken()
    {
        return $this->getAdminToken(true);
    }
}
```

---

### 2. مصادقة Training/Services Platform مع Keycloak

**الملف:** `/training-platform/app/Services/KeycloakAdminService.php`

```php
class KeycloakAdminService
{
    /**
     * الحصول على Access Token باستخدام Client Credentials
     * يستخدم client_secret الخاص بالـ Tenant
     */
    protected function getTenantToken($realmId, $clientId, $clientSecret)
    {
        $response = $this->client->post("/realms/{$realmId}/protocol/openid-connect/token", [
            'form_params' => [
                'client_id' => $clientId,
                'client_secret' => decrypt($clientSecret),  // فك التشفير
                'grant_type' => 'client_credentials',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['access_token'];
    }
}
```

---

### 3. مصادقة Kayan ERP مع Keycloak

#### 3.1. للمستخدمين (OIDC Standard Flow):

**الملف:** `/kayan-erp/apps/oidc_extended/oidc_extended/login.py`

```python
# المستخدم يُعاد توجيهه إلى Keycloak للمصادقة
authorization_url = f"{keycloak_url}/realms/{realm_id}/protocol/openid-connect/auth"
params = {
    "client_id": client_id,
    "redirect_uri": redirect_uri,
    "response_type": "code",
    "scope": "openid profile email"
}

# بعد المصادقة، Keycloak يُعيد التوجيه مع authorization code
# ثم نستبدله بـ access_token
```

#### 3.2. لمزامنة الأدوار (Client Credentials):

**الملف:** `/kayan-erp/apps/oidc_extended/oidc_extended/sync.py`

```python
def get_client_credentials_token(realm_id, client_id, client_secret, keycloak_url):
    """
    Get access token using OAuth 2.0 Client Credentials flow
    هذه الطريقة الآمنة الجديدة - لا تحتاج لـ admin credentials
    """
    response = requests.post(
        f"{keycloak_url}/realms/{realm_id}/protocol/openid-connect/token",
        data={
            "grant_type": "client_credentials",
            "client_id": client_id,
            "client_secret": client_secret,
        },
        timeout=10
    )

    if response.status_code == 200:
        return response.json()['access_token']
    else:
        frappe.logger().error(f"Failed to get token: {response.status_code}")
        return None

def sync_roles_with_client_credentials(realm_id, client_id, client_secret, keycloak_url, roles):
    """
    مزامنة أدوار Frappe إلى Keycloak Groups باستخدام client credentials
    الطريقة الجديدة الآمنة - تستخدم token الخاص بالـ product
    """
    # الحصول على access token
    access_token = get_client_credentials_token(realm_id, client_id, client_secret, keycloak_url)

    if not access_token:
        return {"success": False, "message": "Failed to get access token"}

    synced_count = 0

    # إنشاء group لكل role
    for role_name in roles:
        group_data = {
            "name": role_name,
            "attributes": {
                "source": ["frappe"],
                "auto_synced": ["true"],
                "synced_via": ["client_credentials"]
            }
        }

        response = requests.post(
            f"{keycloak_url}/admin/realms/{realm_id}/groups",
            headers={
                "Authorization": f"Bearer {access_token}",
                "Content-Type": "application/json"
            },
            json=group_data,
            timeout=10
        )

        if response.status_code in [201, 409]:  # Created or already exists
            synced_count += 1

    return {
        "success": True,
        "message": f"Successfully synced {synced_count} roles",
        "synced_count": synced_count
    }
```

---

## 🔑 ضبط Social Login Key وتسجيل الدخول

### في منتجات Laravel (Training & Services)

#### 1. استلام Client Credentials من المتجر

عندما يرسل المتجر Webhook إلى المنتج، يتم حفظ Client Credentials في جدول `tenants`:

```php
// /training-platform/app/Http/Controllers/Api/KeycloakWebhookController.php

public function setup(Request $request)
{
    // 1. إيجاد أو إنشاء Tenant
    $tenant = Tenant::where('id', $request->tenant_id)->first();

    if (!$tenant) {
        $tenant = Tenant::create(['id' => $request->tenant_id]);
        $tenant->domains()->create(['domain' => $request->domain]);
    }

    // 2. حفظ معلومات Keycloak في قاعدة البيانات
    $tenant->update([
        'keycloak_realm' => $request->realm_id,           // tenant-89796
        'keycloak_client_id' => $request->client_id,       // training-platform
        'keycloak_client_secret' => encrypt($request->client_secret), // مشفّر
        'keycloak_configured' => true,  // علامة تفعيل SSO
    ]);

    // 3. تفعيل سياق Tenant لمزامنة الأدوار
    tenancy()->initialize($tenant);

    // 4. مزامنة أدوار Laravel إلى Keycloak Groups
    $this->syncRolesToKeycloak($tenant, app(KeycloakAdminService::class));

    return response()->json(['success' => true]);
}
```

#### 2. ظهور زر تسجيل الدخول بـ Keycloak SSO

**الملف:** `/training-platform/resources/views/filament/pages/auth/login.blade.php`

```blade
{{-- نموذج تسجيل الدخول العادي --}}
<x-filament-panels::form wire:submit="authenticate">
    {{ $this->form }}
    <!-- الحقول العادية: email, password -->
</x-filament-panels::form>

{{-- زر تسجيل الدخول بـ Keycloak SSO --}}
@if(tenancy()->tenant?->keycloak_configured)
    <div class="mt-6">
        <div class="relative">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="px-2 bg-white text-gray-500">
                    {{ __('Or continue with') }}
                </span>
            </div>
        </div>

        <div class="mt-6">
            <form action="{{ route('keycloak.redirect') }}" method="GET">
                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border rounded-lg shadow-sm">
                    <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24">
                        <path d="M11.5 0L0 6v7.5C0 19.8 5 24 11.5 24..."/>
                    </svg>
                    {{ __('Sign in with Keycloak SSO') }}
                </button>
            </form>
        </div>
    </div>
@endif
```

**الشرط المهم:**
- الزر يظهر **فقط** إذا كان `keycloak_configured = true`
- يتم تفعيله تلقائياً عند استلام Webhook من المتجر

#### 3. معالجة تسجيل الدخول وتعيين الأدوار

**الملف:** `/training-platform/app/Http/Controllers/Auth/KeycloakController.php`

```php
public function redirect()
{
    $tenant = tenancy()->tenant;

    // إعادة توجيه المستخدم إلى Keycloak للمصادقة
    $authUrl = "{$keycloakUrl}/realms/{$tenant->keycloak_realm}/protocol/openid-connect/auth?" . http_build_query([
        'client_id' => $tenant->keycloak_client_id,
        'redirect_uri' => url('/auth/keycloak/callback'),
        'response_type' => 'code',
        'scope' => 'openid profile email',  // Groups تُضمّن في Token
    ]);

    return redirect($authUrl);
}

public function callback(Request $request)
{
    $tenant = tenancy()->tenant;

    // 1. استبدال authorization code بـ access token
    $tokenResponse = Http::asForm()->post("{$keycloakUrl}/realms/{$tenant->keycloak_realm}/protocol/openid-connect/token", [
        'grant_type' => 'authorization_code',
        'client_id' => $tenant->keycloak_client_id,
        'client_secret' => decrypt($tenant->keycloak_client_secret),
        'code' => $request->code,
        'redirect_uri' => url('/auth/keycloak/callback'),
    ]);

    $accessToken = $tokenResponse->json()['access_token'];

    // 2. فك تشفير JWT Token للحصول على Groups
    $tokenParts = explode('.', $accessToken);
    $payload = json_decode(base64_decode($tokenParts[1]), true);

    // 3. استخراج معلومات المستخدم
    $userInfo = Http::withToken($accessToken)->get("{$keycloakUrl}/realms/{$tenant->keycloak_realm}/protocol/openid-connect/userinfo")->json();

    // 4. إيجاد أو إنشاء مستخدم في Laravel
    $user = User::updateOrCreate(
        ['email' => $userInfo['email']],
        [
            'name' => $userInfo['name'],
            'email' => $userInfo['email'],
            'password' => bcrypt(Str::random(32)), // كلمة مرور عشوائية
            'email_verified_at' => now(),
        ]
    );

    // 5. استخراج Groups من Token
    $groups = $payload['groups'] ?? [];  // مثال: ["super_admin", "editor"]

    // 6. مزامنة الأدوار: تعيين Groups كـ Roles في Laravel
    $this->syncRoles($user, $groups);

    // 7. تسجيل دخول المستخدم
    Auth::login($user, true);

    return redirect('/admin');
}

/**
 * مزامنة Groups من Keycloak إلى Roles في Laravel
 */
protected function syncRoles(User $user, array $groups)
{
    // تعيين team_id للـ Multi-tenancy
    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

    $roleNames = [];
    foreach ($groups as $group) {
        $roleNames[] = is_array($group) ? $group['name'] : $group;
    }

    // التحقق من الأدوار الموجودة في Laravel
    $existingRoles = \Spatie\Permission\Models\Role::pluck('name')->toArray();

    // تعيين فقط الأدوار الموجودة
    $validRoleNames = array_intersect($roleNames, $existingRoles);

    // مزامنة الأدوار (حذف القديمة وإضافة الجديدة)
    $user->syncRoles($validRoleNames);

    Log::info("Roles synced for user", [
        'email' => $user->email,
        'keycloak_groups' => $groups,
        'assigned_roles' => $validRoleNames,
    ]);
}
```

### آلية تعيين الأدوار (Role Assignment Flow)

```
1. المتجر ينشئ مستخدماً في Keycloak Realm
         ↓
2. المتجر يعين المستخدم لـ Group (مثلاً: "super_admin")
         ↓
3. المستخدم يضغط "Sign in with Keycloak SSO" في منتج Training
         ↓
4. Keycloak يُصدر JWT Token يحتوي على:
    - email
    - name
    - groups: ["super_admin", "editor"]  ← عبر Group Mapper
         ↓
5. KeycloakController يقرأ groups من Token
         ↓
6. KeycloakController يبحث عن Roles في Laravel بنفس الاسم
         ↓
7. KeycloakController يعين Roles للمستخدم: $user->syncRoles(["super_admin"])
         ↓
8. Laravel يطبق Permissions حسب الـ Role
```

### في منتج Kayan ERP (Frappe)

#### 1. ضبط Social Login Key تلقائياً

**الملف:** `/kayan-erp/apps/oidc_extended/oidc_extended/setup.py`

```python
def configure_keycloak(realm_id, client_id, client_secret, keycloak_url, ...):
    """
    إنشاء Social Login Key في Frappe لتمكين تسجيل الدخول بـ Keycloak
    """
    # 1. إنشاء أو تحديث Social Login Key
    key_name = f"keycloak-{realm_id}"

    if frappe.db.exists("Social Login Key", key_name):
        social_key = frappe.get_doc("Social Login Key", key_name)
    else:
        social_key = frappe.new_doc("Social Login Key")
        social_key.name = key_name

    # 2. ضبط الإعدادات
    social_key.provider_name = "Keycloak"
    social_key.enable_social_login = 1  # ← تفعيل زر تسجيل الدخول
    social_key.client_id = client_id
    social_key.client_secret = client_secret
    social_key.base_url = keycloak_url
    social_key.authorize_url = f"{keycloak_url}/realms/{realm_id}/protocol/openid-connect/auth"
    social_key.access_token_url = f"{keycloak_url}/realms/{realm_id}/protocol/openid-connect/token"
    social_key.redirect_url = f"{frappe.utils.get_url()}/api/method/frappe.integrations.oauth2_logins.custom/keycloak-{realm_id}"

    social_key.save(ignore_permissions=True)
    frappe.db.commit()

    return social_key.name
```

#### 2. مزامنة الأدوار من Frappe إلى Keycloak

**عند استلام Webhook:**

```python
@frappe.whitelist(allow_guest=True)
def keycloak_webhook_setup(**kwargs):
    # 1. حفظ Client Credentials
    # 2. ضبط Social Login Key (أعلاه)

    # 3. جلب جميع أدوار Frappe
    roles = frappe.get_all("Role", filters={"disabled": 0}, pluck="name")
    # مثال: ["System Manager", "Sales User", "HR Manager", ...]

    # 4. مزامنة كل دور إلى Keycloak كـ Group
    from oidc_extended.sync import sync_roles_with_client_credentials

    result = sync_roles_with_client_credentials(
        realm_id=realm_id,
        client_id=client_id,
        client_secret=client_secret,
        keycloak_url=keycloak_url,
        roles=roles  # 49 دور
    )

    # نتيجة: 49 Group في Keycloak Realm
```

#### 3. تعيين الأدوار عند تسجيل الدخول

**الملف:** `/kayan-erp/apps/oidc_extended/oidc_extended/callback.py`

```python
def handle_keycloak_callback(code, state):
    """
    معالجة Callback من Keycloak وتعيين الأدوار
    """
    # 1. استبدال code بـ access_token
    token_response = requests.post(token_url, data={
        'grant_type': 'authorization_code',
        'client_id': client_id,
        'client_secret': client_secret,
        'code': code,
        'redirect_uri': redirect_uri,
    })

    access_token = token_response.json()['access_token']

    # 2. فك تشفير JWT Token
    payload = jwt.decode(access_token, options={"verify_signature": False})

    # 3. استخراج Groups من Token
    groups = payload.get('groups', [])  # ["System Manager", "Sales User"]

    # 4. إيجاد أو إنشاء مستخدم Frappe
    user_email = payload.get('email')
    if not frappe.db.exists("User", user_email):
        user = frappe.get_doc({
            "doctype": "User",
            "email": user_email,
            "first_name": payload.get('given_name'),
            "last_name": payload.get('family_name'),
        })
        user.insert(ignore_permissions=True)
    else:
        user = frappe.get_doc("User", user_email)

    # 5. مزامنة Groups إلى Roles في Frappe
    sync_user_roles(user, groups)

    # 6. تسجيل دخول المستخدم
    frappe.local.login_manager.login_as(user_email)

    return user

def sync_user_roles(user, groups):
    """
    تعيين Groups من Keycloak كـ Roles في Frappe
    """
    # الحصول على الأدوار الموجودة في Frappe
    existing_roles = frappe.get_all("Role", filters={"disabled": 0}, pluck="name")

    # تصفية Groups للاحتفاظ فقط بالأدوار الموجودة
    valid_roles = [g for g in groups if g in existing_roles]

    # حذف جميع الأدوار القديمة
    user.roles = []

    # إضافة الأدوار الجديدة
    for role_name in valid_roles:
        user.append("roles", {"role": role_name})

    user.save(ignore_permissions=True)
    frappe.db.commit()

    frappe.logger().info(f"Synced roles for {user.email}: {valid_roles}")
```

### ملخص آلية تعيين الأدوار

| الخطوة | المتجر | المنتج (Laravel) | المنتج (Frappe) |
|--------|--------|-----------------|-----------------|
| 1 | ينشئ User في Keycloak | - | - |
| 2 | يعين User لـ Groups | - | - |
| 3 | - | يحفظ Client Credentials في DB | يحفظ في Social Login Key |
| 4 | - | يزامن Roles → Keycloak Groups | يزامن Roles → Keycloak Groups |
| 5 | - | User يسجل دخول عبر SSO | User يسجل دخول عبر SSO |
| 6 | - | Token يحتوي Groups | Token يحتوي Groups |
| 7 | - | يقرأ Groups ويعين Roles | يقرأ Groups ويعين Roles |
| 8 | - | Laravel يطبق Permissions | Frappe يطبق Permissions |

**النقاط المهمة:**
- ✅ المتجر يُدير Users و Groups في Keycloak
- ✅ المنتجات تُزامن Roles الخاصة بها إلى Keycloak كـ Groups
- ✅ عند تسجيل الدخول، المنتج يقرأ Groups من Token ويعينها كـ Roles محلية
- ✅ **المنتج لا يُنشئ Groups** - فقط يزامن أسماء الأدوار الموجودة لديه
- ✅ **المتجر هو المسؤول الوحيد** عن تعيين Users إلى Groups

---

## 🔄 دورة حياة الاشتراك

### المرحلة 1: إنشاء حساب جديد في المتجر

```
المستخدم يُسجل في المتجر
         ↓
UserObserver::created()
         ↓
dispatch(CreateUserKeycloakRealm)  ← Queue Job
         ↓
KeycloakService::createTenantRealm()
         ↓
Keycloak: إنشاء Realm (tenant-{user_id})
         ↓
حفظ keycloak_realm_id في جدول users
```

**الملف:** `/app/Jobs/CreateUserKeycloakRealm.php`

```php
public function handle()
{
    $keycloak = app(KeycloakService::class);

    // 1. إنشاء Realm جديد
    $realmId = "tenant-{$this->user->id}";
    $keycloak->createTenantRealm($realmId, $this->user->company_name);

    // 2. تحديث Token للحصول على صلاحيات الـ Realm الجديد
    $keycloak->refreshToken();

    // 3. تثبيت Admin Console Client
    $keycloak->fixAdminConsoleClient($realmId);

    // 4. إنشاء مستخدم في Keycloak وتعيين دور realm-admin
    $keycloak->createUser($realmId, [/* ... */]);
    $keycloak->assignRealmAdminRole($realmId, $this->user->email);

    // 5. حفظ معلومات Keycloak في قاعدة البيانات
    $this->user->update([
        'keycloak_realm_id' => $realmId,
    ]);

    Log::info("Successfully created Keycloak realm {$realmId} for user {$this->user->id}");
}
```

---

### المرحلة 2: الاشتراك في Training أو Services

```
المستخدم يضغط "اشتراك" في المتجر
         ↓
SubscriptionController::subscribe('training')
         ↓
استدعاء Product API: POST /api/tenants/create
         ↓
المنتج ينشئ Tenant ويُرجع (tenant_id, domain)
         ↓
حفظ Subscription في قاعدة البيانات
    - status: 'active'  ← مهم!
    - is_active: true
         ↓
Event: SubscriptionCreated (بعد DB::afterCommit)
         ↓
Listener: CreateKeycloakRealm::handle()
         ↓
KeycloakService::createProductClient()
    - إنشاء Client في Keycloak
    - توليد Client Secret
    - منح صلاحيات realm-management
    - إضافة Group Mapper
         ↓
حفظ client_id, client_secret في Subscription
         ↓
إرسال Webhook للمنتج: POST /api/keycloak/setup
         ↓
المنتج يستلم:
    - realm_id
    - client_id
    - client_secret
    - tenant_id
    - domain
         ↓
المنتج يحفظ Client Secret (مشفّر)
         ↓
المنتج يزامن الأدوار مع Keycloak
```

**الكود الرئيسي:**

```php
// app/Http/Controllers/SubscriptionController.php

private function subscribeTraining($user)
{
    // 1. استدعاء Training API
    $response = Http::post('http://localhost:5000/api/tenants/create', [
        'user_id' => $user->id,
        'company_name' => $user->company_name,
        'email' => $user->email,
    ]);

    $data = $response->json();

    // 2. حفظ Subscription مع status = 'active'
    return Subscription::create([
        'user_id' => $user->id,
        'product' => 'training',
        'tenant_id' => $data['tenant_id'],
        'domain' => $data['domain'],
        'url' => $data['admin_url'],
        'is_active' => true,
        'status' => 'active',  // 
        'meta' => [/* ... */],
    ]);

    // 3. Event SubscriptionCreated يُطلق تلقائياً عبر Model::boot()
}
```

---

### المرحلة 3: الاشتراك في Kayan ERP (عملية خاصة)

```
المستخدم يضغط "اشتراك في Kayan ERP"
         ↓
SubscriptionController::subscribeKayanERP()
         ↓
حفظ Subscription:
    - status: 'pending'  ← في انتظار إنشاء الموقع
    - is_active: false
    - url: null
    - domain: null
         ↓
dispatch(CreateKayanERPSite)  ← Queue Job
         ↓
Job يستدعى Python Script:
    /kayan-erp/scripts/create_tenant_site.py
         ↓
Python Script ينشئ:
    ✓ قاعدة بيانات منفصلة: kayan-user-{id}
    ✓ موقع Frappe معزول
    ✓ منفذ ديناميكي: 40XXX
    ✓ domain: kayan-user-{id}.local
         ↓
Job يحدّث Subscription:
    - tenant_id: kayan-user-{id}
    - domain: kayan-user-{id}.local
    - url: http://localhost:40XXX
    - status: 'active'  ← الآن أصبح نشطاً
    - is_active: true
         ↓
Job يُطلق Event: SubscriptionCreated (مرة أخرى)
         ↓
Listener: CreateKeycloakRealm::handle()
    - يتحقق: هل url موجود؟ نعم ✓
    - ينشئ Client في Keycloak
    - redirect_uri: http://localhost:40XXX/*
         ↓
Listener يرسل Webhook:
    POST http://localhost:40XXX/api/method/oidc_extended.setup.keycloak_webhook_setup
         ↓
Frappe يستلم Webhook ويعالجه:
    1. يحفظ client_id, client_secret في site_config.json
    2. ينشئ Social Login Key
    3. ينشئ OIDC Extended Configuration
    4. يزامن 49 دور Frappe إلى Keycloak Groups
    5. ينشئ group-role mappings
```

**الكود التفصيلي:**

```php
// app/Http/Controllers/SubscriptionController.php

private function subscribeKayanERP($user)
{
    $adminPassword = 'admin' . rand(1000, 9999);

    // 1. إنشاء Subscription بحالة pending
    $subscription = Subscription::create([
        'user_id' => $user->id,
        'product' => 'kayan_erp',
        'tenant_id' => null,      // سيتم ملؤه لاحقاً
        'domain' => null,          // سيتم ملؤه لاحقاً
        'url' => null,             // سيتم ملؤه لاحقاً
        'is_active' => false,      // سيتم تفعيله لاحقاً
        'status' => 'pending',     // في الانتظار
        'meta' => [
            'admin_password' => $adminPassword,
        ],
    ]);

    // 2. إطلاق Job لإنشاء الموقع
    dispatch(new CreateKayanERPSite($subscription, $user, $adminPassword));

    return $subscription;
}
```

```php
// app/Jobs/CreateKayanERPSite.php

public function handle(): void
{
    // 1. تحديث الحالة إلى processing
    $this->subscription->update(['status' => 'processing']);

    // 2. استدعاء Python Script لإنشاء الموقع
    $scriptPath = '/home/vboxuser/kayan-erp/scripts/create_tenant_site.py';
    $command = sprintf(
        'python3 %s %d %s %s %s',
        escapeshellarg($scriptPath),
        $this->user->id,
        escapeshellarg($this->user->company_name),
        escapeshellarg($this->user->email),
        escapeshellarg($this->adminPassword)
    );

    exec($command, $output, $returnCode);
    $result = json_decode(implode("\n", $output), true);

    if (!$result || !$result['success']) {
        throw new \Exception($result['message'] ?? 'Failed to create site');
    }

    // 3. تحديث Subscription بمعلومات الموقع
    $this->subscription->update([
        'tenant_id' => $result['site_name'],    // kayan-user-123
        'domain' => $result['domain'],           // kayan-user-123.local
        'url' => $result['url'],                 // http://localhost:40896
        'status' => 'active',                    // الآن نشط!
        'is_active' => true,
        'meta' => json_encode([
            'site_name' => $result['site_name'],
            'admin_password' => $this->adminPassword,
            'port' => $result['port'],
        ]),
    ]);

    // 4. إطلاق Event لإنشاء Keycloak Client
    $this->subscription->refresh();
    event(new \App\Events\SubscriptionCreated($this->subscription, $this->user));
}
```

```php
// app/Listeners/CreateKeycloakRealm.php

public function handle(SubscriptionCreated $event): void
{
    $subscription = $event->subscription;
    $user = $event->user;

    // للمنتج Kayan ERP: تخطي إذا لم يتم إنشاء الموقع بعد
    if ($subscription->product === 'kayan_erp' && empty($subscription->url)) {
        Log::info("Skipping Keycloak client - site not created yet");
        return;  // سينفذ Event مرة أخرى بعد إنشاء الموقع
    }

    // استخدام URL ديناميكي لـ Kayan ERP
    $productUrl = $subscription->product === 'kayan_erp' && $subscription->url
        ? $subscription->url
        : $productConfig['url'];

    // إنشاء Client في Keycloak
    $clientData = $this->keycloak->createProductClient(
        $user->keycloak_realm_id,
        'kayan-erp',
        $productUrl,  // http://localhost:40896
        $subscription->domain
    );

    // إرسال Webhook للموقع
    $webhookUrl = $subscription->url . '/api/method/oidc_extended.setup.keycloak_webhook_setup';
    Http::post($webhookUrl, [
        'realm_id' => $user->keycloak_realm_id,
        'client_id' => $clientData['client_id'],
        'client_secret' => $clientData['client_secret'],
        // ...
    ]);
}
```

```python
# /kayan-erp/apps/oidc_extended/oidc_extended/setup.py

@frappe.whitelist(allow_guest=True)
def keycloak_webhook_setup(**kwargs):
    """
    Webhook endpoint من Laravel Marketplace
    يستقبل client credentials ويضبط الموقع
    """
    data = frappe._dict(kwargs)

    realm_id = data.realm_id
    client_id = data.client_id
    client_secret = data.client_secret
    keycloak_base_url = data.get('realm_url', '').replace(f"/realms/{realm_id}", "")

    # 1. حفظ إعدادات Keycloak في Social Login Key
    configure_keycloak(
        realm_id=realm_id,
        client_id=client_id,
        client_secret=client_secret,
        keycloak_url=keycloak_base_url,
        # ...
    )

    # 2. حفظ Client Credentials في site_config.json
    from frappe.installer import update_site_config
    update_site_config("keycloak_realm_id", realm_id)
    update_site_config("keycloak_client_id", client_id)
    update_site_config("keycloak_client_secret", client_secret)
    update_site_config("keycloak_url", keycloak_base_url)

    # 3. جلب جميع أدوار Frappe
    roles_result = get_all_roles()
    roles = roles_result.get('roles', [])

    # 4. مزامنة الأدوار مع Keycloak باستخدام Client Credentials
    from oidc_extended.sync import sync_roles_with_client_credentials
    sync_result = sync_roles_with_client_credentials(
        realm_id=realm_id,
        client_id=client_id,
        client_secret=client_secret,
        keycloak_url=keycloak_base_url,
        roles=roles
    )

    # 5. إنشاء group-role mappings في Frappe
    if sync_result.get('success'):
        mapping_result = sync_group_role_mappings(social_login_key_name, roles)

    return {
        "success": True,
        "roles_synced": sync_result.get('synced_count', 0),
        "mappings_created": mapping_result.get('mappings_created', 0)
    }
```

---

## 🔄 مزامنة الأدوار والمجموعات

### مبدأ المزامنة

```
قاعدة بيانات المنتج (Roles/Permissions Table)
         ↓
استدعاء KeycloakAdminService بـ Client Credentials
         ↓
مصادقة مع Keycloak (grant_type: client_credentials)
         ↓
إنشاء/تحديث Groups في Keycloak Realm
         ↓
Groups تُضمّن في JWT Token عند تسجيل دخول المستخدمين
         ↓
المنتج يقرأ Groups من Token ويطبق الصلاحيات
```

### التطبيق في كل منتج:

#### 1. Training/Services Platform:

```php
// الملف: /training-platform/app/Services/KeycloakAdminService.php

public function syncRolesToGroups($realmId, $roles)
{
    $tenant = app(\Stancl\Tenancy\Tenant::class);

    // المصادقة باستخدام Client Credentials الخاصة بالـ Tenant
    $token = $this->getTenantToken(
        $tenant->keycloak_realm,
        $tenant->keycloak_client_id,
        $tenant->keycloak_client_secret
    );

    foreach ($roles as $role) {
        // التحقق من وجود Group
        $existingGroups = $this->getGroups($realmId);
        $existingGroup = collect($existingGroups)->firstWhere('name', $role['name']);

        if ($existingGroup) {
            // تحديث Group موجود
            $this->updateGroup($realmId, $existingGroup['id'], ['name' => $role['name']]);
        } else {
            // إنشاء Group جديد
            $this->createGroup($realmId, ['name' => $role['name']]);
        }
    }
}
```

#### 2. Kayan ERP (Frappe):

```python
# الملف: /kayan-erp/apps/oidc_extended/oidc_extended/sync.py

def sync_roles_with_client_credentials(realm_id, client_id, client_secret, keycloak_url, roles):
    """
    مزامنة أدوار Frappe إلى Keycloak Groups
    الطريقة الجديدة الآمنة - تستخدم Client Credentials
    """
    # 1. الحصول على access token
    access_token = get_client_credentials_token(
        realm_id, client_id, client_secret, keycloak_url
    )

    if not access_token:
        return {"success": False}

    synced_count = 0

    # 2. إنشاء group لكل role
    for role_name in roles:
        group_data = {
            "name": role_name,
            "attributes": {
                "source": ["frappe"],
                "auto_synced": ["true"],
                "synced_via": ["client_credentials"]
            }
        }

        response = requests.post(
            f"{keycloak_url}/admin/realms/{realm_id}/groups",
            headers={
                "Authorization": f"Bearer {access_token}",
                "Content-Type": "application/json"
            },
            json=group_data
        )

        if response.status_code in [201, 409]:  # Created or exists
            synced_count += 1

    return {
        "success": True,
        "synced_count": synced_count
    }
```

---

## 👥 إدارة المستخدمين من المتجر

### واجهة الإدارة المركزية

المتجر يوفر واجهة كاملة لإدارة المستخدمين والمجموعات **مباشرة** في Keycloak بدون الحاجة لـ Admin Console:

**المسارات:**
```
/users                     - قائمة المستخدمين في Realm
/users/create              - إنشاء مستخدم جديد
/users/{id}/edit           - تعديل مستخدم
/users/{id}/reset-password - إعادة تعيين كلمة المرور
/groups                    - قائمة المجموعات
/groups/{id}/members       - أعضاء المجموعة
```

### الأكواد المسؤولة:

#### 1. عرض قائمة المستخدمين:

```php
// app/Http/Controllers/UserManagementController.php

public function index(Request $request)
{
    $user = Auth::user();

    // جلب المستخدمين من Keycloak مباشرة
    $users = $this->keycloak->getUsers($user->keycloak_realm_id);

    return view('users.index', [
        'users' => $users,
        'realmId' => $user->keycloak_realm_id,
    ]);
}
```

#### 2. إنشاء مستخدم جديد:

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'username' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'first_name' => 'nullable|string|max:255',
        'last_name' => 'nullable|string|max:255',
        'password' => 'required|string|min:8',
        'enabled' => 'boolean',
        'groups' => 'nullable|array',  // يمكن اختيار مجموعات أثناء الإنشاء
    ]);

    $user = Auth::user();

    // 1. إنشاء المستخدم في Keycloak
    $this->keycloak->createUser($user->keycloak_realm_id, [
        'username' => $request->username,
        'email' => $request->email,
        'first_name' => $request->first_name,  // firstName في Keycloak
        'last_name' => $request->last_name,    // lastName في Keycloak
        'password' => $request->password,
        'enabled' => $request->boolean('enabled', true),
        'email_verified' => $request->boolean('email_verified', false),
        'temporary_password' => $request->boolean('temporary_password', true),
    ]);

    // 2. تعيين المجموعات إذا تم اختيارها
    if ($request->has('groups') && is_array($request->groups)) {
        $users = $this->keycloak->getUsers($user->keycloak_realm_id);
        $newUser = collect($users)->firstWhere('username', $request->username);

        if ($newUser) {
            foreach ($request->groups as $groupId) {
                $this->keycloak->assignUserToGroup(
                    $user->keycloak_realm_id,
                    $newUser['id'],
                    $groupId
                );
            }
        }
    }

    return redirect()->route('users.index')
        ->with('success', 'تم إنشاء المستخدم بنجاح');
}
```

**دالة createUser في KeycloakService:**

```php
public function createUser($realmId, array $userData)
{
    $token = $this->getAdminToken();

    $response = $this->client->post("/admin/realms/{$realmId}/users", [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'username' => $userData['username'],
            'email' => $userData['email'],
            'firstName' => $userData['first_name'] ?? '',  // camelCase مهم
            'lastName' => $userData['last_name'] ?? '',    // camelCase مهم
            'enabled' => $userData['enabled'] ?? true,
            'emailVerified' => $userData['email_verified'] ?? false,
            'credentials' => isset($userData['password']) ? [[
                'type' => 'password',
                'value' => $userData['password'],
                'temporary' => $userData['temporary_password'] ?? false,
            ]] : [],
        ],
    ]);

    Log::info("Created user {$userData['username']} in realm {$realmId}");
    return true;
}
```

#### 3. تعيين مستخدم لمجموعة:

```php
public function assignGroup(Request $request, $userId)
{
    $validated = $request->validate([
        'group_id' => 'required|string',
    ]);

    $user = Auth::user();

    $this->keycloak->assignUserToGroup(
        $user->keycloak_realm_id,
        $userId,
        $validated['group_id']
    );

    return back()->with('success', 'تم إضافة المستخدم للمجموعة بنجاح');
}
```

**دالة assignUserToGroup:**

```php
public function assignUserToGroup($realmId, $userId, $groupId)
{
    $token = $this->getAdminToken();

    $this->client->put(
        "/admin/realms/{$realmId}/users/{$userId}/groups/{$groupId}",
        ['headers' => ['Authorization' => 'Bearer ' . $token]]
    );

    Log::info("Assigned user {$userId} to group {$groupId} in realm {$realmId}");
    return true;
}
```

---

## ⚙️ Queue Workers المطلوبة

### Worker الأساسي للمتجر

**الأمر:**
```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=900
```

**المسؤوليات:**
- معالجة `CreateUserKeycloakRealm` - إنشاء Realm عند تسجيل مستخدم جديد
- معالجة `CreateKayanERPSite` - إنشاء موقع Frappe معزول (قد يستغرق 5-10 دقائق)

**ملاحظات مهمة:**
- `timeout=900`: ضروري لأن إنشاء موقع Kayan ERP يستغرق وقتاً طويلاً
- `tries=3`: يعيد المحاولة 3 مرات في حالة الفشل
- يجب تشغيل الـ Worker **باستمرار** في الخلفية

**تشغيل كـ Systemd Service (للإنتاج):**

```ini
# /etc/systemd/system/saas-queue-worker.service

[Unit]
Description=SaaS Marketplace Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/saas-marketplace
ExecStart=/usr/bin/php /var/www/saas-marketplace/artisan queue:work --sleep=3 --tries=3 --timeout=900
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

**الأوامر:**
```bash
sudo systemctl enable saas-queue-worker
sudo systemctl start saas-queue-worker
sudo systemctl status saas-queue-worker
```

---

### Worker لـ Kayan ERP Sites (اختياري)

إذا كنت تريد worker منفصل لمواقع Kayan ERP:

```bash
php artisan queue:work --queue=kayan-erp --sleep=3 --tries=1 --timeout=1800
```

**الفوائد:**
- عزل معالجة Kayan ERP عن باقي العمليات
- timeout أطول (30 دقيقة) للمواقع الكبيرة
- `tries=1`: لا يعيد المحاولة (لتجنب إنشاء مواقع مكررة)

**تعديل الكود لاستخدام Queue منفصل:**

```php
// app/Http/Controllers/SubscriptionController.php

dispatch(new CreateKayanERPSite($subscription, $user, $adminPassword))
    ->onQueue('kayan-erp');  // إرسال إلى queue منفصل
```

---

### Monitoring وإدارة الـ Queue

#### 1. مراقبة حالة الـ Jobs:

```bash
# عرض Jobs الفاشلة
php artisan queue:failed

# إعادة تشغيل Job فاشل
php artisan queue:retry {job_id}

# إعادة تشغيل جميع Jobs الفاشلة
php artisan queue:retry all

# حذف Jobs الفاشلة
php artisan queue:flush
```

#### 2. Logs:

```bash
# Log المتجر
tail -f /var/www/saas-marketplace/storage/logs/laravel.log

# Log الـ Queue Worker (إذا كنت تستخدم systemd)
journalctl -u saas-queue-worker -f
```

#### 3. Horizon (اختياري - للإنتاج):

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

Horizon يوفر:
- واجهة ويب لمراقبة الـ Queue
- إحصائيات الأداء
- إدارة Jobs الفاشلة
- Auto-scaling للـ Workers

---

## 📚 الأكواد المسؤولة

### في المتجر (saas-marketplace):

#### Core Services:
- `/app/Services/KeycloakService.php` - خدمة Keycloak الرئيسية
  - `getAdminToken()` - المصادقة مع Keycloak
  - `createTenantRealm()` - إنشاء Realm
  - `createProductClient()` - إنشاء Client للمنتج
  - `grantProductClientPermissions()` - منح الصلاحيات
  - `addGroupMapperToClient()` - إضافة Group Mapper

#### Event & Listeners:
- `/app/Events/SubscriptionCreated.php` - Event عند إنشاء اشتراك
- `/app/Listeners/CreateKeycloakRealm.php` - إنشاء Client وإرسال Webhook

#### Jobs:
- `/app/Jobs/CreateUserKeycloakRealm.php` - إنشاء Realm عند تسجيل مستخدم
- `/app/Jobs/CreateKayanERPSite.php` - إنشاء موقع Frappe معزول

#### Controllers:
- `/app/Http/Controllers/SubscriptionController.php` - إدارة الاشتراكات
- `/app/Http/Controllers/UserManagementController.php` - إدارة المستخدمين
- `/app/Http/Controllers/GroupController.php` - إدارة المجموعات
- `/app/Http/Controllers/ProductController.php` - عرض تفاصيل المنتجات

#### Models:
- `/app/Models/Subscription.php` - Model الاشتراكات مع Event Boot
- `/app/Models/User.php` - Model المستخدمين
- `/app/Observers/UserObserver.php` - Observer لإنشاء Realm تلقائياً

#### Configuration:
- `/config/products.php` - إعدادات المنتجات
- `/config/services.php` - إعدادات Keycloak

---

### في Training Platform:

- `/app/Http/Controllers/Api/TenantController.php` - API لإنشاء Tenants
- `/app/Http/Controllers/Api/KeycloakWebhookController.php` - استلام Webhooks
- `/app/Services/KeycloakAdminService.php` - مزامنة الأدوار
- `/app/Models/Tenant.php` - Model المستأجرين
- `/database/migrations/add_keycloak_columns_to_tenants.php` - حقول Keycloak

---

### في Services Platform:

- `/app/Http/Controllers/Api/TenantController.php` - API لإنشاء Tenants
- `/app/Http/Controllers/Api/KeycloakWebhookController.php` - استلام Webhooks
- `/app/Services/KeycloakAdminService.php` - مزامنة الأدوار
- `/app/Models/Tenant.php` - Model المستأجرين

---

### في Kayan ERP (Frappe):

#### Setup & Configuration:
- `/kayan-erp/apps/oidc_extended/oidc_extended/setup.py`
  - `keycloak_webhook_setup()` - استلام Webhook من المتجر
  - `configure_keycloak()` - ضبط Social Login Key
  - `sync_group_role_mappings()` - إنشاء Group-Role Mappings

#### Role Synchronization:
- `/kayan-erp/apps/oidc_extended/oidc_extended/sync.py`
  - `get_client_credentials_token()` - المصادقة بـ Client Credentials
  - `sync_roles_with_client_credentials()` - مزامنة الأدوار بطريقة آمنة

#### Site Creation:
- `/kayan-erp/scripts/create_tenant_site.py` - Python Script لإنشاء موقع معزول
- `/kayan-erp/scripts/setup_keycloak_integration.py` - **DEPRECATED** (لا يُستخدم)

#### Authentication:
- `/kayan-erp/apps/oidc_extended/oidc_extended/login.py` - تسجيل دخول OIDC
- `/kayan-erp/apps/oidc_extended/oidc_extended/callback.py` - معالجة Callback

---

## 🔍 استكشاف الأخطاء

### 1. Subscription لا يظهر في القائمة الجانبية

**السبب:**
- `status` ليس `'active'`

**الحل:**
```php
// تأكد من أن SubscriptionController يضع status = 'active'
Subscription::create([
    'status' => 'active',  // ← مهم!
    'is_active' => true,
]);
```

---

### 2. Kayan ERP: Webhook فشل

**السبب:**
- الموقع لم يتم إنشاؤه بعد (URL = null)
- الموقع لا يعمل على المنفذ المحدد

**الحل:**
```bash
# تحقق من أن الموقع يعمل
cd /home/vboxuser/kayan-erp
bench --site {site_name} serve --port {port}

# تحقق من Logs
tail -f sites/{site_name}/logs/frappe.log
```

---

### 3. أدوار لا تُزامن في Kayan ERP

**السبب:**
- Client Secret غير صحيح
- صلاحيات Client غير كافية

**الحل:**
```bash
# تحقق من Client Secret المحفوظ
php artisan tinker --execute="
echo App\Models\Subscription::find({id})->keycloak_client_secret;
"

# تحقق من Logs في Frappe
tail -f /home/vboxuser/kayan-erp/sites/{site_name}/logs/frappe.log
```

---

### 4. Queue Worker لا يعمل

**الحل:**
```bash
# إعادة تشغيل الـ Worker
pkill -f "queue:work"
cd /home/vboxuser/saas-marketplace
php artisan queue:work --sleep=3 --tries=3 --timeout=900 > /tmp/queue-worker.log 2>&1 &

# مراقبة الـ Log
tail -f /tmp/queue-worker.log
```

---

### 5. First/Last Name لا يُحفظ

**السبب:**
- الحقول في النموذج تستخدم `firstName` بدلاً من `first_name`

**الحل:**
تأكد من أن النموذج يستخدم `first_name` و `last_name`:
```html
<input type="text" name="first_name" />  <!-- صحيح -->
<input type="text" name="firstName" />   <!-- خطأ -->
```

---

## ✅ الخلاصة

### ما تم تحقيقه:

1. ✅ **عزل كامل**: كل مستأجر له Realm معزول في Keycloak
2. ✅ **أمان محكم**: صلاحيات محدودة ومفاتيح فريدة لكل اشتراك
3. ✅ **ثلاث منتجات**: Training (Laravel 12), Services (Laravel 12), Kayan ERP (Frappe 14)
4. ✅ **تكامل تلقائي**: من التسجيل حتى المزامنة - كل شيء تلقائي
5. ✅ **إدارة مركزية**: المستخدم لا يحتاج Keycloak Admin Console
6. ✅ **مزامنة الأدوار**:
   - المنتجات تُزامن Roles المحلية إلى Keycloak كـ Groups
   - المتجر يعين المستخدمين إلى Groups
   - المنتجات تقرأ Groups من Token وتطبقها كـ Roles
7. ✅ **Social Login Key**: يتم ضبطه تلقائياً في جميع المنتجات عبر Webhook
8. ✅ **OAuth 2.0 Client Credentials**: طريقة آمنة بدون admin credentials
9. ✅ **عزل على مستوى الموقع**: Kayan ERP يستخدم مواقع معزولة تماماً
10. ✅ **Queue Workers**: معالجة غير متزامنة للعمليات الطويلة

### مصفوفة المصادقة والصلاحيات:

| المنتج | التقنية | نوع العزل | المصادقة | مزامنة الأدوار | SSO Button | Status بعد الاشتراك |
|--------|---------|----------|----------|----------------|-----------|---------------------|
| Training | Laravel 12 | Schema-based | OIDC | Client Credentials | Filament Login | active فوراً |
| Services | Laravel 12 | Schema-based | OIDC | Client Credentials | Filament Login | active فوراً |
| Kayan ERP | Frappe 14 | Site-based | OIDC + Client Credentials | Client Credentials | Social Login Key | pending → active |

### تقييد الصلاحيات:

| الطرف | الصلاحيات | القيود |
|-------|-----------|---------|
| المتجر | create-realm, manage-users, manage-clients | ❌ delete-realm, ❌ impersonation |
| Training/Services | manage-users, manage-realm, query-groups | ✅ محدود بـ realm واحد |
| Kayan ERP | manage-users, manage-realm, query-groups | ✅ محدود بـ realm واحد |
| المستخدم | إدارة عبر واجهة المتجر | ❌ لا وصول مباشر لـ Keycloak |

---

**📅 تاريخ التوثيق:** 2025-11-20
**🔧 الإصدار:** 2.1

**📝 آخر تحديث:**
- تصحيح إصدارات المنتجات (Training/Services: Laravel 12, Kayan ERP: Frappe 14)
- إضافة قسم شامل عن Social Login Key وآلية تسجيل الدخول
- توضيح آلية مزامنة الأدوار وتعيينها من Keycloak إلى المنتجات
- شرح كيفية ظهور زر SSO في واجهة المنتجات
