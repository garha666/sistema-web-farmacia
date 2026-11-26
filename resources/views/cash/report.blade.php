<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('pos.cash_register.report_title') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-slate-50 text-slate-900">
    <main class="mx-auto max-w-2xl space-y-5 bg-white p-5 sm:p-8">
        <h1 class="text-2xl font-bold">{{ __('pos.cash_register.report_title') }}</h1>
        <div class="space-y-1 text-sm print:hidden">
            <a class="font-semibold text-emerald-800 underline" href="{{ route('cash.report', ['cashRegister' => $register, 'format' => 'csv']) }}">{{ __('pos.cash_register.report_csv') }}</a>
            <p class="text-slate-600">{{ __('pos.cash_register.report_print_hint') }}</p>
        </div>
        <dl class="divide-y divide-slate-200 text-sm">
            @foreach ($fields as [$label, $value, $isCurrency])
                <div class="grid grid-cols-2 gap-3 py-2.5">
                    <dt>{{ $label }}</dt>
                    <dd class="break-words text-right font-semibold tabular-nums">{{ $value === null ? __('pos.common.none') : ($isCurrency ? ($value < 0 ? '-$' : '$').number_format(abs($value), 0, ',', '.') : $value) }}</dd>
                </div>
            @endforeach
        </dl>
    </main>
</body>
</html>
