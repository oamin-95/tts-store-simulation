<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SaaS Marketplace')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Ensure sidebar is always on the right in RTL */
        [dir="rtl"] .fixed.right-0 {
            right: 0 !important;
            left: auto !important;
        }
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        /* Custom scrollbar for sidebar */
        .overflow-y-auto::-webkit-scrollbar {
            width: 6px;
        }
        .overflow-y-auto::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 3px;
        }
        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }
    </style>
</head>
<body class="bg-gray-50">
    @yield('content')
</body>
</html>
