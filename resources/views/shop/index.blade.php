<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop — {{ $company }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <div>
                <h1 class="text-lg font-bold">{{ $company }}</h1>
                <p class="text-sm text-slate-400">Hardware & accessories shop</p>
            </div>
            <a href="{{ url('/') }}" class="text-sm font-semibold text-teal-400 hover:underline">← Home</a>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('shop_success'))
            <div class="mb-6 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-emerald-200">
                {{ session('shop_success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-rose-200">
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
                        $cost = $product->effectiveCost();
                    @endphp
                    <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-lg">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-semibold">{{ $product->name }}</h2>
                                @if ($product->sku)
                                    <p class="font-mono text-xs text-slate-500">{{ $product->sku }}</p>
                                @endif
                            </div>
                            <span class="rounded-full bg-teal-500/20 px-2 py-0.5 text-xs font-bold text-teal-300">
                                {{ $product->stock_qty }} in stock
                            </span>
                        </div>
                        @if ($product->description)
                            <p class="mt-2 text-sm text-slate-400">{{ $product->description }}</p>
                        @endif
                        <p class="mt-4 text-2xl font-bold text-white">
                            {{ number_format($sell, 0) }} <span class="text-sm font-normal text-slate-400">BDT</span>
                        </p>

                        <form method="post" action="{{ route('shop.checkout') }}" class="mt-4 space-y-3 border-t border-slate-800 pt-4">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <div class="grid grid-cols-2 gap-2">
                                <label class="block text-xs text-slate-400">
                                    Qty
                                    <input type="number" name="quantity" min="1" max="{{ $product->stock_qty }}" value="1"
                                        class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm">
                                </label>
                                <label class="block text-xs text-slate-400">
                                    Pay with
                                    <select name="payment_method" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm">
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
                                    class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm">
                            </label>
                            <label class="block text-xs text-slate-400">
                                Mobile
                                <input type="tel" name="customer_phone" required value="{{ old('customer_phone') }}"
                                    class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm">
                            </label>
                            <button type="submit"
                                class="w-full rounded-lg bg-teal-600 py-2.5 text-sm font-bold text-white hover:bg-teal-500">
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
