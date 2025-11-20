@extends('layouts.app')

@section('title', 'تعديل المجموعة')

@section('content')
<div class="min-h-screen bg-gray-100" style="margin-right: 16rem;">
    @include('partials.sidebar')

    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">تعديل المجموعة</h1>
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
            <form method="POST" action="{{ route('groups.update', $group['id']) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        اسم المجموعة *
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $group['name']) }}"
                        required
                        placeholder="Admin, Manager, User"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    <p class="text-sm text-gray-500 mt-1">
                        هذا الاسم سيُستخدم كدور في المنتجات المشترك بها
                    </p>
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        الوصف (اختياري)
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >{{ old('description', $group['attributes']['description'][0] ?? '') }}</textarea>
                    @error('description')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
                    <h4 class="font-medium text-blue-900 mb-2">معلومات إضافية</h4>
                    <div class="text-sm text-blue-800 space-y-1">
                        <p><strong>المسار:</strong> {{ $group['path'] }}</p>
                        <p><strong>المعرّف:</strong> {{ $group['id'] }}</p>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <a href="{{ route('groups.index') }}" class="text-gray-600 hover:text-gray-800">
                        إلغاء
                    </a>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        حفظ التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
