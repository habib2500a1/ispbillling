<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Inventory\InventorySaleService;
use App\Support\CompanyBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryShopController extends Controller
{
    private function tenantId(): int
    {
        return (int) config('inventory.default_tenant_id', 1);
    }

    public function index(): View
    {
        if (! config('inventory.shop_enabled', true)) {
            abort(404);
        }

        $products = Product::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->where('show_on_shop', true)
            ->where('stock_qty', '>', 0)
            ->orderBy('name')
            ->get();

        return view('shop.index', [
            'company' => CompanyBranding::name(),
            'phone' => config('isp.company_phone'),
            'products' => $products,
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        if (! config('inventory.shop_enabled', true)) {
            abort(404);
        }

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:99',
            'customer_name' => 'required|string|max:120',
            'customer_phone' => 'required|string|max:32',
            'payment_method' => 'required|in:cash,bkash,nagad,bank',
            'notes' => 'nullable|string|max:500',
        ]);

        $product = Product::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->where('show_on_shop', true)
            ->findOrFail((int) $validated['product_id']);

        if ((int) $product->stock_qty < (int) $validated['quantity']) {
            return back()->withErrors(['quantity' => 'Not enough stock. Available: '.$product->stock_qty])->withInput();
        }

        $sale = app(InventorySaleService::class)->recordSale(
            tenantId: $this->tenantId(),
            lines: [[
                'product_id' => $product->id,
                'quantity' => (int) $validated['quantity'],
            ]],
            channel: 'shop',
            customerName: $validated['customer_name'],
            customerPhone: $validated['customer_phone'],
            paymentMethod: $validated['payment_method'],
            notes: $validated['notes'] ?? 'Landing shop order',
        );

        return redirect()
            ->route('shop.index')
            ->with('shop_success', 'Order '.$sale->sale_number.' placed! Total '.number_format((float) $sale->total, 2).' BDT. We will contact you shortly.');
    }
}
