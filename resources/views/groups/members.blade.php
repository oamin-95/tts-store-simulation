@extends('layouts.app')

@section('title', 'أعضاء المجموعة')

@section('content')
<div class="min-h-screen bg-gray-100" style="margin-right: 16rem;">
    @include('partials.sidebar')

    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6">
            <div class="flex items-center gap-4">
                <a href="{{ route('groups.index') }}" class="text-blue-600 hover:text-blue-800">
                    ← رجوع
                </a>
                <h1 class="text-2xl font-bold">أعضاء المجموعة: {{ $group['name'] }}</h1>
            </div>
            @if(isset($group['attributes']['description'][0]))
                <p class="text-gray-600 mt-2">{{ $group['attributes']['description'][0] }}</p>
            @endif
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

        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            الاسم
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            البريد الإلكتروني
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            اسم المستخدم
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            الحالة
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            الإجراءات
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($members as $member)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $member['firstName'] ?? '' }} {{ $member['lastName'] ?? '' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ $member['email'] }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ $member['username'] }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($member['enabled'])
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        مفعّل
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        معطّل
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex gap-3">
                                    <a href="{{ route('users.edit', $member['id']) }}" class="text-blue-600 hover:text-blue-900">
                                        تعديل
                                    </a>
                                    <form method="POST" action="{{ route('users.remove-group', [$member['id'], $group['id']]) }}" class="inline" onsubmit="return confirm('هل أنت متأكد من إزالة هذا المستخدم من المجموعة؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                            إزالة من المجموعة
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                <div class="flex flex-col items-center">
                                    <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <p class="text-lg font-medium">لا يوجد أعضاء في هذه المجموعة</p>
                                    <p class="text-sm mt-2">قم بإضافة مستخدمين من صفحة إدارة المستخدمين</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(count($members) > 0)
            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-md p-4">
                <p class="text-sm text-blue-800">
                    <strong>عدد الأعضاء:</strong> {{ count($members) }}
                </p>
            </div>
        @endif
    </div>
</div>
@endsection
