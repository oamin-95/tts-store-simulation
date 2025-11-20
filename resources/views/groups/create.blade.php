@extends('layouts.app')
@section('title', 'إضافة مجموعة')
@section('content')
<div class="min-h-screen bg-gray-100" style="margin-right: 16rem;">
    @include('partials.sidebar')

    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">إضافة مجموعة جديدة</h1>
            <p class="text-gray-600 mt-2">إنشاء مجموعة (دور) جديدة في Keycloak</p>
        </div>

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" action="{{ route('groups.store') }}">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم المجموعة (الدور) *</label>
                    <input type="text" name="name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="مثال: Admin, Manager, User"
                           value="{{ old('name') }}">
                    <p class="text-sm text-gray-500 mt-1">
                        هذا الاسم سيُستخدم كدور في المنتجات. اختر اسماً واضحاً مثل: Admin, Editor, Viewer
                    </p>
                    @error('name')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6 bg-blue-50 border border-blue-200 p-4 rounded-lg">
                    <p class="text-blue-800 text-sm">
                        💡 <strong>ملاحظة:</strong> بعد إنشاء المجموعة، يمكنك إضافة المستخدمين إليها من صفحة المستخدمين.
                        المستخدمون الذين يسجلون دخول عبر SSO سيحصلون تلقائياً على هذا الدور.
                    </p>
                </div>

                <div class="mt-6 flex gap-4">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        إضافة المجموعة
                    </button>
                    <a href="{{ route('groups.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-300">
                        إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
