@extends('layouts.app')
@section('title', 'لوحة التحكم')
@section('content')
@php
    $subscriptions = auth()->user()->subscriptions;
    $trainingSubscription = $subscriptions->where('product', 'training')->first();
    $servicesSubscription = $subscriptions->where('product', 'services')->first();
    $kayanSubscription = $subscriptions->where('product', 'kayan_erp')->first();

    // Check if any Kayan ERP site is being processed
    $isProcessing = $kayanSubscription && in_array($kayanSubscription->status, ['pending', 'processing']);
@endphp

@if($isProcessing)
<script>
    // Auto-refresh page every 10 seconds if site is being created
    setTimeout(function() {
        location.reload();
    }, 10000);
</script>
@endif

<div class="min-h-screen bg-gray-100" style="margin-right: 16rem;">
    @include('partials.sidebar')

    <div class="max-w-7xl mx-auto py-6 px-4">
        @if($isProcessing)
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4 flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>جاري إنشاء موقع Kayan ERP... سيتم تحديث الصفحة تلقائياً كل 10 ثوان</span>
                </div>
                <button onclick="location.reload()" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                    تحديث الآن
                </button>
            </div>
        @endif

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-2xl font-bold">مرحباً، {{ auth()->user()->company_name }}!</h2>
        </div>

        @php
            // Check if user has Keycloak realm
            $user = auth()->user();
            $hasKeycloakRealm = $user->keycloak_realm_id;

            // Build Keycloak info
            $keycloakInfo = null;
            if ($hasKeycloakRealm) {
                $keycloakBaseUrl = config('services.keycloak.url', 'http://localhost:8090');
                $keycloakInfo = [
                    'realm_id' => $hasKeycloakRealm,
                    'realm_login_url' => "{$keycloakBaseUrl}/realms/{$hasKeycloakRealm}/account",
                    'realm_admin_url' => "{$keycloakBaseUrl}/admin/{$hasKeycloakRealm}/console",
                    'admin_email' => $user->email,
                    'admin_temp_password' => 'ChangeMe123!',
                ];
            }
        @endphp

        @if($keycloakInfo)
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-6 rounded-lg shadow mb-6 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h3 class="text-xl font-bold mb-2">🔐 لوحة إدارة الهويات والصلاحيات (Keycloak)</h3>
                            <p class="text-indigo-100 text-sm mb-4">
                                لوحة معزولة خاصة بك لإدارة المستخدمين، الأدوار والصلاحيات عبر جميع منتجاتك
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <!-- Login Portal -->
                                <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                                    <h4 class="font-semibold mb-2">👤 بوابة المستخدمين</h4>
                                    <p class="text-xs text-indigo-100 mb-3">صفحة دخول للمستخدمين النهائيين</p>
                                    <a href="{{ $keycloakInfo['realm_login_url'] }}"
                                       target="_blank"
                                       class="inline-block bg-white text-indigo-600 px-4 py-2 rounded-md hover:bg-indigo-50 transition text-sm font-medium">
                                        افتح بوابة المستخدمين →
                                    </a>
                                </div>

                                <!-- Admin Console -->
                                <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                                    <h4 class="font-semibold mb-2">⚙️ لوحة الإدارة</h4>
                                    <p class="text-xs text-indigo-100 mb-3">إدارة كاملة للمستخدمين والصلاحيات</p>
                                    <a href="{{ $keycloakInfo['realm_admin_url'] }}"
                                       target="_blank"
                                       class="inline-block bg-white text-purple-600 px-4 py-2 rounded-md hover:bg-purple-50 transition text-sm font-medium">
                                        افتح لوحة الإدارة →
                                    </a>
                                </div>
                            </div>

                            <div class="mt-4 p-3 bg-black/20 rounded-lg text-xs">
                                <p class="font-semibold mb-2">📋 معلومات الدخول للوحة الإدارة:</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <div>
                                        <span class="text-indigo-200">البريد الإلكتروني:</span>
                                        <code class="bg-black/30 px-2 py-1 rounded ml-2">{{ $keycloakInfo['admin_email'] }}</code>
                                    </div>
                                    <div>
                                        <span class="text-indigo-200">كلمة المرور (مؤقتة):</span>
                                        <code class="bg-black/30 px-2 py-1 rounded ml-2">{{ $keycloakInfo['admin_temp_password'] }}</code>
                                    </div>
                                </div>
                                <p class="text-indigo-200 mt-2">
                                    ⚠️ سيُطلب منك تغيير كلمة المرور عند أول تسجيل دخول
                                </p>
                            </div>
                        </div>

                        <div class="hidden md:block ml-6">
                            <svg class="w-24 h-24 text-white/30" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-white/20">
                        <details class="text-sm">
                            <summary class="cursor-pointer font-semibold hover:text-indigo-100">
                                ℹ️ ما الذي يمكنك فعله في لوحة Keycloak؟
                            </summary>
                            <ul class="mt-3 space-y-2 text-indigo-100 mr-4">
                                <li>✅ إدارة المستخدمين (إضافة، تعديل، حذف)</li>
                                <li>✅ تعيين الأدوار والصلاحيات</li>
                                <li>✅ مراقبة جلسات المستخدمين النشطة</li>
                                <li>✅ إعداد المصادقة الثنائية (2FA)</li>
                                <li>✅ إدارة تكامل تطبيقات المنتجات (Training, Services, ERP)</li>
                                <li>✅ مراجعة سجلات تسجيل الدخول والأحداث</li>
                            </ul>
                        </details>
                    </div>
                </div>
        @endif
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Training Platform -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="w-12 h-12 bg-blue-500 rounded-md mb-4"></div>
                <h3 class="text-lg font-bold">منصة التدريب</h3>
                <p class="text-gray-600 text-sm mt-2">إدارة الدورات والشهادات</p>
                @if($trainingSubscription && $trainingSubscription->is_active)
                    @php
                        $trainingMeta = is_array($trainingSubscription->meta) ? $trainingSubscription->meta : (json_decode($trainingSubscription->meta, true) ?? []);
                    @endphp
                    <span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm mt-4">مفعل</span>
                    <a href="{{ $trainingSubscription->url }}" target="_blank"
                       class="block w-full bg-blue-600 text-white px-4 py-2 rounded-md mt-4 hover:bg-blue-700 text-center">
                        افتح المنصة
                    </a>

                    <div class="mt-4 p-3 bg-gray-50 rounded text-sm">
                        <p class="font-semibold mb-2">معلومات الدخول:</p>
                        <p class="text-gray-700">
                            <span class="font-medium">الموقع:</span>
                            <a href="{{ $trainingSubscription->url }}" class="text-blue-600 hover:underline" target="_blank">
                                {{ $trainingSubscription->domain }}
                            </a>
                        </p>
                        <p class="text-gray-700">
                            <span class="font-medium">البريد الإلكتروني:</span>
                            <code class="bg-white px-2 py-1 rounded">{{ $trainingMeta['admin_email'] ?? auth()->user()->email }}</code>
                        </p>
                        <p class="text-gray-700">
                            <span class="font-medium">كلمة المرور:</span>
                            <code class="bg-white px-2 py-1 rounded">{{ $trainingMeta['admin_password'] ?? 'N/A' }}</code>
                        </p>
                    </div>
                @else
                    <span class="inline-block bg-gray-100 px-3 py-1 rounded-full text-sm mt-4">غير مفعل</span>
                    <form method="POST" action="{{ route('subscribe') }}">
                        @csrf
                        <input type="hidden" name="product" value="training">
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md mt-4 hover:bg-blue-700">
                            اشترك الآن
                        </button>
                    </form>
                @endif
            </div>

            <!-- Services Platform -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="w-12 h-12 bg-green-500 rounded-md mb-4"></div>
                <h3 class="text-lg font-bold">منصة الخدمات</h3>
                <p class="text-gray-600 text-sm mt-2">إدارة الخدمات والمشاريع</p>
                @if($servicesSubscription && $servicesSubscription->is_active)
                    @php
                        $servicesMeta = is_array($servicesSubscription->meta) ? $servicesSubscription->meta : (json_decode($servicesSubscription->meta, true) ?? []);
                    @endphp
                    <span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm mt-4">مفعل</span>
                    <a href="{{ $servicesSubscription->url }}" target="_blank"
                       class="block w-full bg-green-600 text-white px-4 py-2 rounded-md mt-4 hover:bg-green-700 text-center">
                        افتح المنصة
                    </a>

                    <div class="mt-4 p-3 bg-gray-50 rounded text-sm">
                        <p class="font-semibold mb-2">معلومات الدخول:</p>
                        <p class="text-gray-700">
                            <span class="font-medium">الموقع:</span>
                            <a href="{{ $servicesSubscription->url }}" class="text-green-600 hover:underline" target="_blank">
                                {{ $servicesSubscription->domain }}
                            </a>
                        </p>
                        <p class="text-gray-700">
                            <span class="font-medium">البريد الإلكتروني:</span>
                            <code class="bg-white px-2 py-1 rounded">{{ $servicesMeta['admin_email'] ?? auth()->user()->email }}</code>
                        </p>
                        <p class="text-gray-700">
                            <span class="font-medium">كلمة المرور:</span>
                            <code class="bg-white px-2 py-1 rounded">{{ $servicesMeta['admin_password'] ?? 'N/A' }}</code>
                        </p>
                    </div>
                @else
                    <span class="inline-block bg-gray-100 px-3 py-1 rounded-full text-sm mt-4">غير مفعل</span>
                    <form method="POST" action="{{ route('subscribe') }}">
                        @csrf
                        <input type="hidden" name="product" value="services">
                        <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-md mt-4 hover:bg-green-700">
                            اشترك الآن
                        </button>
                    </form>
                @endif
            </div>

            <!-- Kayan ERP -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="w-12 h-12 bg-purple-500 rounded-md mb-4"></div>
                <h3 class="text-lg font-bold">Kayan ERP</h3>
                <p class="text-gray-600 text-sm mt-2">نظام تخطيط موارد المؤسسات</p>
                @if($kayanSubscription)
                    @php
                        $meta = is_array($kayanSubscription->meta) ? $kayanSubscription->meta : (json_decode($kayanSubscription->meta, true) ?? []);
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'processing' => 'bg-blue-100 text-blue-800',
                            'active' => 'bg-green-100 text-green-800',
                            'failed' => 'bg-red-100 text-red-800',
                        ];
                        $statusText = [
                            'pending' => 'في الانتظار',
                            'processing' => 'جاري الإنشاء',
                            'active' => 'مفعل',
                            'failed' => 'فشل',
                        ];
                        $status = $kayanSubscription->status ?? 'pending';
                    @endphp
                    <span class="inline-block {{ $statusColors[$status] ?? 'bg-gray-100' }} px-3 py-1 rounded-full text-sm mt-4">
                        {{ $statusText[$status] ?? $status }}
                    </span>

                    @if($kayanSubscription->is_active && $kayanSubscription->url)
                        <a href="{{ $kayanSubscription->url }}" target="_blank"
                           class="block w-full bg-purple-600 text-white px-4 py-2 rounded-md mt-4 hover:bg-purple-700 text-center">
                            افتح المنصة
                        </a>

                        <div class="mt-4 p-3 bg-gray-50 rounded text-sm">
                            <p class="font-semibold mb-2">معلومات الدخول:</p>
                            <p class="text-gray-700">
                                <span class="font-medium">الموقع:</span>
                                <a href="{{ $kayanSubscription->url }}" class="text-purple-600 hover:underline" target="_blank">
                                    {{ $kayanSubscription->domain }}
                                </a>
                            </p>
                            <p class="text-gray-700">
                                <span class="font-medium">المستخدم:</span>
                                <code class="bg-white px-2 py-1 rounded">{{ $meta['admin_email'] ?? 'Administrator' }}</code>
                            </p>
                            <p class="text-gray-700">
                                <span class="font-medium">كلمة المرور:</span>
                                <code class="bg-white px-2 py-1 rounded">{{ $meta['admin_password'] ?? 'N/A' }}</code>
                            </p>
                        </div>

                        @if($kayanSubscription->domain !== 'localhost')
                        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm">
                            <p class="font-semibold text-blue-800 mb-2">📝 للوصول من المتصفح:</p>
                            <p class="text-blue-700 mb-2">أضف هذا السطر لملف <code class="bg-white px-1 rounded">/etc/hosts</code>:</p>
                            <div class="bg-gray-800 text-green-400 px-3 py-2 rounded font-mono text-xs overflow-x-auto">
                                127.0.0.1 {{ $kayanSubscription->domain }}
                            </div>
                            <p class="text-blue-600 text-xs mt-2">
                                💡 أو استخدم: <code class="bg-white px-1 rounded">sudo nano /etc/hosts</code>
                            </p>
                        </div>
                        @endif
                    @elseif($status === 'processing')
                        <div class="mt-4 p-3 bg-blue-50 rounded text-sm text-blue-800">
                            <p class="flex items-center">
                                <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                جاري إنشاء موقع ERP الخاص بك... (5-7 دقائق)
                            </p>
                        </div>
                    @elseif($status === 'failed')
                        <div class="mt-4 p-3 bg-red-50 rounded text-sm text-red-800">
                            <p>❌ فشل إنشاء الموقع. يرجى المحاولة مرة أخرى أو الاتصال بالدعم الفني.</p>
                            @if(isset($meta['error']))
                                <p class="text-xs mt-2">{{ $meta['error'] }}</p>
                            @endif
                        </div>
                    @endif
                @else
                    <span class="inline-block bg-gray-100 px-3 py-1 rounded-full text-sm mt-4">غير مفعل</span>
                    <form method="POST" action="{{ route('subscribe') }}">
                        @csrf
                        <input type="hidden" name="product" value="kayan_erp">
                        <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md mt-4 hover:bg-purple-700">
                            اشترك الآن
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
