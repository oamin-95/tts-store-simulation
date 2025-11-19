@extends('layouts.app')
@section('title', 'التسجيل')
@section('content')
<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full bg-white p-8 rounded-lg shadow-lg">
        <h2 class="text-3xl font-bold text-center mb-6">إنشاء حساب جديد</h2>
        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">الاسم</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">البريد الإلكتروني</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">اسم الشركة</label>
                <input type="text" name="company_name" value="{{ old('company_name') }}" required
                    class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">كلمة المرور</label>
                <input type="password" name="password" required
                    class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">تأكيد كلمة المرور</label>
                <input type="password" name="password_confirmation" required
                    class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700">
                إنشاء الحساب
            </button>
            <div class="text-center mt-4">
                <a href="{{ route('login') }}" class="text-blue-600">لديك حساب؟ تسجيل الدخول</a>
            </div>
        </form>
    </div>
</div>
@endsection
