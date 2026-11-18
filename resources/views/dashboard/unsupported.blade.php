<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('pos.panel.unsupported.title') }} · {{ __('pos.app.brand') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 px-4 py-16 text-slate-900 antialiased">
    <main class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm font-semibold text-emerald-700">{{ __('pos.app.brand') }}</p>
        <h1 class="mt-2 text-lg font-bold text-slate-900">{{ __('pos.panel.unsupported.title') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ __('pos.panel.unsupported.message') }}</p>
    </main>
</body>
</html>
