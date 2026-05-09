<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900">

    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between gap-4">
            <a href="/" class="text-base font-bold text-gray-900 tracking-tight">
                {{ config('app.name') }}
            </a>

            <div class="flex items-center gap-3">
                {{ $navEnd ?? '' }}
                @auth
                    <a href="{{ url('/portal') }}"
                       class="text-sm text-gray-600 hover:text-gray-900 transition-colors">
                        ← Portal
                    </a>
                @else
                    <a href="{{ route('filament.portal.auth.login') }}"
                       class="text-sm text-gray-600 hover:text-gray-900 transition-colors">
                        Login
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    <main>
        {{ $slot }}
    </main>

</body>
</html>
