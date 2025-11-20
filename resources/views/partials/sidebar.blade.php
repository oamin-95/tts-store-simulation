@php
    $user = auth()->user();
    $subscriptions = \App\Models\Subscription::where('user_id', $user->id)
        ->where('status', 'active')
        ->get();
@endphp

<div class="fixed right-0 top-0 h-screen w-64 bg-white shadow-lg border-l border-gray-200 overflow-y-auto">
    <!-- Header -->
    <div class="p-6 border-b border-gray-200">
        <a href="{{ route('dashboard') }}" class="block">
            <h1 class="text-xl font-bold text-gray-800">SaaS Marketplace</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $user->name ?? $user->email }}</p>
        </a>
    </div>

    <nav class="p-4">
        <!-- Dashboard Home -->
        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-3 px-4 py-3 mb-2 rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span class="font-medium">الرئيسية</span>
        </a>

        <!-- Products Section -->
        <div class="mb-6">
            <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                المنتجات المشترك فيها
            </div>

            @if($subscriptions->count() > 0)
                <div class="space-y-1">
                    @foreach($subscriptions as $subscription)
                        @php
                            $productNames = [
                                'training' => 'منصة التدريب',
                                'services' => 'منصة الخدمات',
                                'kayan_erp' => 'كيان ERP',
                            ];
                            $productName = $productNames[$subscription->product] ?? $subscription->product;

                            $productIcons = [
                                'training' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
                                'services' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
                                'kayan_erp' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
                            ];
                            $icon = $productIcons[$subscription->product] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>';
                        @endphp

                        <a href="{{ route('products.show', $subscription->product) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition-colors {{ request()->routeIs('products.show') && request()->route('product') == $subscription->product ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {!! $icon !!}
                            </svg>
                            <span class="text-sm">{{ $productName }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="px-4 py-3 text-sm text-gray-500">
                    لا توجد اشتراكات نشطة
                </div>
            @endif
        </div>

        <!-- Identity Management Section -->
        @if($user->keycloak_realm_id)
            <div class="pt-4 border-t border-gray-200">
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    إدارة الهوية والصلاحيات
                </div>

                <div class="space-y-1">
                    <!-- Users -->
                    <a href="{{ route('users.index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition-colors {{ request()->routeIs('users.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <span class="text-sm">المستخدمين</span>
                    </a>

                    <!-- Groups -->
                    <a href="{{ route('groups.index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition-colors {{ request()->routeIs('groups.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="text-sm">المجموعات</span>
                    </a>
                </div>
            </div>
        @endif
    </nav>

    <!-- Logout at bottom -->
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 bg-gray-50">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex items-center gap-3 px-4 py-2.5 w-full rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span class="text-sm font-medium">تسجيل الخروج</span>
            </button>
        </form>
    </div>
</div>
