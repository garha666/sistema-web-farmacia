@php
    $isEdit = $product !== null;
@endphp

@if ($errors->any())
    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="mt-5 space-y-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <label for="name" class="text-sm font-semibold text-slate-700">{{ __('pos.inventory.products.form_name') }}</label>
        <input
            id="name"
            name="name"
            type="text"
            maxlength="120"
            required
            value="{{ old('name', $product?->name) }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
        >
    </div>

    <div>
        <label for="active_ingredient" class="text-sm font-semibold text-slate-700">{{ __('pos.inventory.products.form_active_ingredient') }}</label>
        <input
            id="active_ingredient"
            name="active_ingredient"
            type="text"
            maxlength="120"
            value="{{ old('active_ingredient', $product?->active_ingredient) }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
        >
    </div>

    <div>
        <label for="barcode" class="text-sm font-semibold text-slate-700">{{ __('pos.inventory.products.form_barcode') }}</label>
        <input
            id="barcode"
            name="barcode"
            type="text"
            inputmode="numeric"
            maxlength="32"
            value="{{ old('barcode', $product?->barcode) }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 tabular-nums"
        >
        <p class="mt-1 text-xs text-slate-500">{{ __('pos.inventory.products.form_barcode_help') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="price" class="text-sm font-semibold text-slate-700">{{ __('pos.inventory.products.form_price') }}</label>
            <input
                id="price"
                name="price"
                type="number"
                min="1"
                step="1"
                required
                value="{{ old('price', $product?->price) }}"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 tabular-nums"
            >
        </div>

        <div>
            <label for="sale_category" class="text-sm font-semibold text-slate-700">{{ __('pos.inventory.products.form_category') }}</label>
            <select id="sale_category" name="sale_category" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                @foreach (\App\Pos\Enum\SaleCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected(old('sale_category', $product?->sale_category?->value) === $category->value)>
                        {{ __('pos.inventory.products.category.'.$category->value) }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="min_stock" class="text-sm font-semibold text-slate-700">{{ __('pos.inventory.products.form_min_stock') }}</label>
            <input
                id="min_stock"
                name="min_stock"
                type="number"
                min="0"
                step="1"
                required
                value="{{ old('min_stock', $product?->min_stock ?? 0) }}"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 tabular-nums"
            >
        </div>

        @if ($isEdit)
            <div>
                <span class="text-sm font-semibold text-slate-700">{{ __('pos.inventory.products.form_stock_current') }}</span>
                <p class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm tabular-nums text-slate-700">
                    {{ $product->stock }}
                </p>
            </div>
        @else
            <div>
                <label for="stock" class="text-sm font-semibold text-slate-700">{{ __('pos.inventory.products.form_stock') }}</label>
                <input
                    id="stock"
                    name="stock"
                    type="number"
                    min="0"
                    step="1"
                    required
                    value="{{ old('stock', 0) }}"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 tabular-nums"
                >
            </div>
        @endif
    </div>

    @if ($isEdit)
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="sellable" value="0">
                <input
                    type="checkbox"
                    name="sellable"
                    value="1"
                    class="rounded border-slate-300 text-emerald-600"
                    @checked(old('sellable', $product->sellable))
                >
                {{ __('pos.inventory.products.form_sellable') }}
            </label>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="active" value="0">
                <input
                    type="checkbox"
                    name="active"
                    value="1"
                    class="rounded border-slate-300 text-emerald-600"
                    @checked(old('active', $product->active))
                >
                {{ __('pos.inventory.products.form_active') }}
            </label>
        </div>
    @endif

    <div class="flex gap-3 pt-2">
        <a href="{{ route('inventory.products.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
            {{ __('pos.inventory.products.form_cancel') }}
        </a>
        <button type="submit" class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
            {{ __('pos.inventory.products.form_save') }}
        </button>
    </div>
</form>
