<!DOCTYPE html>
<html lang="bn" class="iv-shop">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop — {{ $company }}</title>
    <link rel="stylesheet" href="{{ asset('css/inventory-hub-pro.css') }}?v={{ @filemtime(public_path('css/inventory-hub-pro.css')) ?: 1 }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #0b1220; color: #e2e8f0; font-family: system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen iv-shop">
    @include('partials.demo-banner')
    <header class="iv-shop-header">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-orange-400">Asset shop</p>
                <h1 class="text-xl font-extrabold text-white">{{ $company }}</h1>
                <p class="text-sm text-slate-400">Hardware &amp; accessories · live stock</p>
            </div>
            <a href="{{ url('/') }}" class="text-sm font-semibold text-orange-300 hover:underline">← Home</a>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('shop_success'))
            <div class="mb-6 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-emerald-200">
                {{ session('shop_success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-rose-200">
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($products->isEmpty())
            <p class="text-center text-slate-400">No products available right now. Check back soon.</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2">
                @foreach ($products as $product)
                    @php
                        $sell = $product->effectiveSellPrice();
                    @endphp
                    <article class="iv-shop-card p-5">
                        @if ($product->imageUrl())
                            <img
                                src="{{ $product->imageUrl() }}"
                                alt="{{ $product->name }}"
                                class="mb-4 aspect-[4/3] w-full rounded-xl border border-white/10 object-cover"
                                loading="lazy"
                            >
                        @endif
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-bold text-white">{{ $product->name }}</h2>
                                @if ($product->sku)
                                    <p class="font-mono text-xs text-slate-500">{{ $product->sku }}</p>
                                @endif
                            </div>
                            <span class="iv-shop-badge">{{ $product->stock_qty }} in stock</span>
                        </div>
                        @if ($product->description)
                            <p class="mt-2 text-sm text-slate-400">{{ $product->description }}</p>
                        @endif
                        <p class="mt-4 text-2xl font-extrabold text-white">
                            {{ number_format($sell, 0) }} <span class="text-sm font-normal text-slate-400">BDT</span>
                        </p>

                        <form method="post" action="{{ route('shop.checkout') }}" class="mt-4 space-y-3 border-t border-white/10 pt-4">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <div class="grid grid-cols-2 gap-2">
                                <label class="block text-xs text-slate-400">
                                    Qty
                                    <input type="number" name="quantity" min="1" max="{{ $product->stock_qty }}" value="1"
                                        class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white">
                                </label>
                                <label class="block text-xs text-slate-400">
                                    Pay with
                                    <select name="payment_method" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white">
                                        <option value="cash">Cash on delivery</option>
                                        <option value="bkash">bKash</option>
                                        <option value="nagad">Nagad</option>
                                        <option value="bank">Bank</option>
                                    </select>
                                </label>
                            </div>
                            <label class="block text-xs text-slate-400">
                                Your name
                                <input type="text" name="customer_name" required value="{{ old('customer_name') }}"
                                    class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white">
                            </label>
                            <label class="block text-xs text-slate-400">
                                Mobile
                                <input type="tel" name="customer_phone" required value="{{ old('customer_phone') }}"
                                    class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white">
                            </label>
                            <button type="submit" class="iv-shop-btn">
                                Order now
                            </button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif

        @if ($phone)
            <p class="mt-8 text-center text-sm text-slate-500">Questions? Call {{ $phone }}</p>
        @endif
    </main>
</body>
</html>
