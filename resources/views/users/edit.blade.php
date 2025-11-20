@extends('layouts.app')

@section('title', 'تعديل المستخدم')

@section('content')
<div class="min-h-screen bg-gray-100" style="margin-right: 16rem;">
    @include('partials.sidebar')

    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">تعديل المستخدم</h1>
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

        <div class="bg-white shadow-md rounded-lg p-6">
            <form method="POST" action="{{ route('users.update', $user['id']) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        اسم المستخدم *
                    </label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="{{ old('username', $user['username']) }}"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    @error('username')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        البريد الإلكتروني *
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $user['email']) }}"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    @error('email')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                            الاسم الأول
                        </label>
                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            value="{{ old('first_name', $user['firstName'] ?? '') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                    </div>

                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                            اسم العائلة
                        </label>
                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            value="{{ old('last_name', $user['lastName'] ?? '') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                    </div>
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input
                            type="checkbox"
                            name="enabled"
                            value="1"
                            {{ old('enabled', $user['enabled'] ?? false) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        >
                        <span class="mr-2 text-sm text-gray-700">حساب مفعّل</span>
                    </label>
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input
                            type="checkbox"
                            name="email_verified"
                            value="1"
                            {{ old('email_verified', $user['emailVerified'] ?? false) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        >
                        <span class="mr-2 text-sm text-gray-700">البريد الإلكتروني موثّق</span>
                    </label>
                </div>

                <div class="mb-6">
                    <h3 class="text-lg font-medium mb-3">المجموعات</h3>

                    <div class="space-y-2 mb-4">
                        @forelse($userGroups as $group)
                            <div class="flex items-center justify-between bg-gray-50 p-3 rounded">
                                <span>{{ $group['name'] }}</span>
                                <form method="POST" action="{{ route('users.remove-group', [$user['id'], $group['id']]) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                        إزالة
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">لا يوجد مجموعات مخصصة</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('users.assign-group', $user['id']) }}" class="flex gap-2">
                        @csrf
                        <select name="group_id" class="flex-1 px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">اختر مجموعة...</option>
                            @foreach($groups as $group)
                                @php
                                    $isAssigned = collect($userGroups)->pluck('id')->contains($group['id']);
                                @endphp
                                @if(!$isAssigned)
                                    <option value="{{ $group['id'] }}">{{ $group['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            إضافة
                        </button>
                    </form>
                </div>

                <div class="flex justify-between items-center pt-4 border-t">
                    <a href="{{ route('users.index') }}" class="text-gray-600 hover:text-gray-800">
                        إلغاء
                    </a>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        حفظ التغييرات
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-6 border-t">
                <h3 class="text-lg font-medium mb-3">إعادة تعيين كلمة المرور</h3>
                <form method="POST" action="{{ route('users.reset-password', $user['id']) }}">
                    @csrf
                    <div class="flex gap-4 items-end">
                        <div class="flex-1">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                كلمة المرور الجديدة
                            </label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                minlength="8"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                        </div>
                        <div>
                            <label class="flex items-center mb-2">
                                <input
                                    type="checkbox"
                                    name="temporary"
                                    value="1"
                                    checked
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="mr-2 text-sm text-gray-700">مؤقتة</span>
                            </label>
                        </div>
                        <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700">
                            إعادة التعيين
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
