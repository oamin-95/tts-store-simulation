@extends('layouts.app')
@section('title', 'تسجيل الدخول')
@section('content')
<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full bg-white p-8 rounded-lg shadow-lg">
        <h2 class="text-3xl font-bold text-center mb-6">تسجيل الدخول</h2>
        <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
            @csrf
            @if ($errors->any())
                <div class="bg-red-50 border-r-4 border-red-500 p-4 text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif
            <div>
                <label class="block text-sm font-medium mb-1">البريد الإلكتروني</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">كلمة المرور</label>
                <input type="password" name="password" required
                    class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700">
                تسجيل الدخول
            </button>
            <div class="text-center mt-4">
                <a href="{{ route('register') }}" class="text-blue-600">ليس لديك حساب؟ سجل الآن</a>
            </div>
        </form>
    </div>
</div>
@endsection
