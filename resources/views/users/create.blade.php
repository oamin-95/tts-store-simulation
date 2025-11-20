@extends('layouts.app')
@section('title', 'إضافة مستخدم')
@section('content')
<div class="min-h-screen bg-gray-100" style="margin-right: 16rem;">
    @include('partials.sidebar')

    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">إضافة مستخدم جديد</h1>
            <p class="text-gray-600 mt-2">إنشاء مستخدم جديد في Keycloak</p>
        </div>

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الاسم الأول</label>
                        <input type="text" name="first_name"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               value="{{ old('first_name') }}">
                        @error('first_name')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">اسم العائلة</label>
                        <input type="text" name="last_name"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               value="{{ old('last_name') }}">
                    </div>
                </div>

                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني *</label>
                    <input type="email" name="email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           value="{{ old('email') }}">
                    @error('email')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم المستخدم *</label>
                    <input type="text" name="username" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           value="{{ old('username') }}">
                    @error('username')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور *</label>
                    <input type="password" name="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-sm text-gray-500 mt-1">كلمة المرور المؤقتة - سيُطلب من المستخدم تغييرها عند أول تسجيل دخول</p>
                    @error('password')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="enabled" value="1" checked
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="mr-2 text-sm text-gray-700">تفعيل المستخدم</span>
                    </label>
                </div>

                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">المجموعات</label>
                    <div class="border border-gray-300 rounded-md p-3 max-h-48 overflow-y-auto">
                        @forelse($groups as $group)
                            <label class="flex items-center py-2">
                                <input type="checkbox" name="groups[]" value="{{ $group['id'] }}"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="mr-2 text-sm text-gray-700">{{ $group['name'] }}</span>
                            </label>
                        @empty
                            <p class="text-gray-500 text-sm">لا توجد مجموعات متاحة</p>
                        @endforelse
                    </div>
                    <p class="text-sm text-gray-500 mt-1">اختر المجموعات التي تريد إضافة المستخدم إليها</p>
                </div>

                <div class="mt-6 flex gap-4">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        إضافة المستخدم
                    </button>
                    <a href="{{ route('users.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-300">
                        إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
