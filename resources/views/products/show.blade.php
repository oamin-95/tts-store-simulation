@extends('layouts.app')

@section('title', $productName)

@section('content')
<div class="min-h-screen bg-gray-100" style="margin-right: 16rem;">
    @include('partials.sidebar')

    <div class="max-w-7xl mx-auto py-6 px-4">
        <!-- Breadcrumb -->
        <div class="mb-6">
            <nav class="flex items-center gap-2 text-sm text-gray-600">
                <a href="{{ route('dashboard') }}" class="hover:text-blue-600">الرئيسية</a>
                <span>/</span>
                <span class="text-gray-900 font-medium">{{ $productName }}</span>
            </nav>
        </div>

        <!-- Product Overview Card -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $productName }}</h1>
                    <p class="text-gray-600">تفاصيل الاشتراك والمستخدمين والمجموعات</p>
                </div>
                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                    نشط
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                <!-- Subscription Info -->
                <div class="p-4 bg-blue-50 rounded-lg">
                    <div class="flex items-center gap-3 mb-2">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="font-semibold text-gray-900">معلومات الاشتراك</h3>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">تاريخ الاشتراك:</span>
                            <span class="font-medium">{{ $subscription->created_at->format('Y-m-d') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">النطاق:</span>
                            <span class="font-medium">{{ $subscription->domain }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">الحالة:</span>
                            <span class="font-medium text-green-600">{{ $subscription->status }}</span>
                        </div>
                    </div>
                </div>

                <!-- Users Count -->
                <div class="p-4 bg-purple-50 rounded-lg">
                    <div class="flex items-center gap-3 mb-2">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <h3 class="font-semibold text-gray-900">المستخدمين</h3>
                    </div>
                    <div class="text-3xl font-bold text-purple-600">{{ count($users) }}</div>
                    <p class="text-sm text-gray-600 mt-1">مستخدم نشط</p>
                </div>

                <!-- Groups Count -->
                <div class="p-4 bg-orange-50 rounded-lg">
                    <div class="flex items-center gap-3 mb-2">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 4 0 014 0z"/>
                        </svg>
                        <h3 class="font-semibold text-gray-900">المجموعات</h3>
                    </div>
                    <div class="text-3xl font-bold text-orange-600">{{ count($groups) }}</div>
                    <p class="text-sm text-gray-600 mt-1">مجموعة (دور)</p>
                </div>
            </div>

            <!-- Product URL -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-medium text-gray-700 mb-1">رابط الوصول للمنتج</h4>
                        <a href="{{ $productUrl }}" target="_blank" class="text-blue-600 hover:underline text-sm">
                            {{ $productUrl }}
                        </a>
                    </div>
                    <a href="{{ $productUrl }}" target="_blank" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                        فتح المنتج
                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Users Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900">المستخدمين</h2>
                <a href="{{ route('users.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    إدارة المستخدمين ←
                </a>
            </div>

            @if(count($users) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الاسم</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">البريد الإلكتروني</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">المجموعات</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach(array_slice($users, 0, 5) as $user)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $user['firstName'] ?? '' }} {{ $user['lastName'] ?? '' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500">{{ $user['email'] }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            @if(isset($user['groups']) && count($user['groups']) > 0)
                                                @foreach($user['groups'] as $group)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        {{ $group['name'] }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-sm text-gray-400">لا توجد مجموعات</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($user['enabled'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                مفعل
                                            </span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                معطل
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(count($users) > 5)
                    <div class="mt-4 text-center">
                        <a href="{{ route('users.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                            عرض جميع المستخدمين ({{ count($users) }})
                        </a>
                    </div>
                @endif
            @else
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <p>لا يوجد مستخدمون</p>
                    <a href="{{ route('users.create') }}" class="mt-2 inline-block text-blue-600 hover:text-blue-800">
                        إضافة مستخدم جديد
                    </a>
                </div>
            @endif
        </div>

        <!-- Groups Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900">المجموعات (الأدوار)</h2>
                <a href="{{ route('groups.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    إدارة المجموعات ←
                </a>
            </div>

            @if(count($groups) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($groups as $group)
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ $group['name'] }}</h3>
                                    <p class="text-xs text-gray-500 mt-1">{{ $group['path'] }}</p>
                                </div>
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded-full">
                                    {{ $group['membersCount'] ?? 0 }}
                                </span>
                            </div>
                            @if(isset($group['attributes']['description'][0]))
                                <p class="text-sm text-gray-600 mt-2">{{ $group['attributes']['description'][0] }}</p>
                            @endif
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <a href="{{ route('groups.members', $group['id']) }}" class="text-sm text-blue-600 hover:text-blue-800">
                                    عرض الأعضاء →
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <p>لا توجد مجموعات</p>
                    <a href="{{ route('groups.create') }}" class="mt-2 inline-block text-blue-600 hover:text-blue-800">
                        إضافة مجموعة جديدة
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
