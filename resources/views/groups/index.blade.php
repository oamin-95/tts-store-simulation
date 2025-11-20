@extends('layouts.app')
@section('title', 'إدارة المجموعات')
@section('content')
<div class="min-h-screen bg-gray-100" style="margin-right: 16rem;">
    @include('partials.sidebar')

    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">إدارة المجموعات (الأدوار)</h1>
            <a href="{{ route('groups.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                + إضافة مجموعة
            </a>
        </div>

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

        <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg mb-6">
            <p class="text-blue-800 text-sm">
                💡 <strong>ملاحظة:</strong> المجموعات (Groups) في Keycloak تُستخدم كأدوار (Roles) في المنتجات.
                المستخدمون الذين يسجلون دخول عبر SSO سيحصلون تلقائياً على الأدوار المُعينة لهم في المجموعات.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($groups as $group)
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">{{ $group['name'] }}</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ $group['path'] }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            دور
                        </span>
                    </div>

                    <div class="mb-4">
                        <p class="text-sm text-gray-600">
                            <span class="font-medium">عدد الأعضاء:</span>
                            {{ $group['membersCount'] ?? 0 }}
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <a href="{{ route('groups.members', $group['id']) }}"
                           class="flex-1 text-center bg-gray-100 text-gray-700 px-3 py-2 rounded-md hover:bg-gray-200 text-sm">
                            عرض الأعضاء
                        </a>
                        <a href="{{ route('groups.edit', $group['id']) }}"
                           class="text-blue-600 hover:text-blue-900 px-3 py-2">
                            تعديل
                        </a>
                        <form method="POST" action="{{ route('groups.destroy', $group['id']) }}" class="inline"
                              onsubmit="return confirm('هل أنت متأكد من حذف هذه المجموعة؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900 px-3 py-2">حذف</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <div class="bg-white rounded-lg shadow p-12 text-center">
                        <div class="text-gray-400 mb-4">
                            <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">لا توجد مجموعات</h3>
                        <p class="text-gray-500 mb-4">ابدأ بإنشاء مجموعة جديدة لتنظيم المستخدمين</p>
                        <a href="{{ route('groups.create') }}" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                            إضافة أول مجموعة
                        </a>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
