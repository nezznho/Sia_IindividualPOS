<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Category;
use Modules\Product\Entities\Product;
use Modules\Sale\Entities\Sale;

/**
 * Read-only JSON API for the GreenCart POS.
 * Amounts are returned in currency units (e.g. 125.50), not cents.
 */
class PosApiController extends Controller
{
    private function perPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 20), 1), 100);
    }

    // GET /api/v1/products?search=&category_id=&per_page=
    public function products(Request $request)
    {
        $products = Product::with('category')
            ->when($request->query('search'), function ($q, $s) {
                $q->where(fn ($q) => $q->where('product_name', 'like', "%{$s}%")
                                       ->orWhere('product_code', 'like', "%{$s}%"));
            })
            ->when($request->query('category_id'), fn ($q, $c) => $q->where('category_id', $c))
            ->orderBy('product_name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $products->through(fn ($p) => $this->product($p));
    }

    // GET /api/v1/products/{id}
    public function product($product)
    {
        $p = $product instanceof Product ? $product : Product::with('category')->findOrFail($product);

        return [
            'id'          => $p->id,
            'name'        => $p->product_name,
            'code'        => $p->product_code,
            'category'    => $p->category ? ['id' => $p->category->id, 'name' => $p->category->category_name] : null,
            'price'       => (float) $p->product_price,
            'cost'        => (float) $p->product_cost,
            'unit'        => $p->product_unit,
            'stock'       => (int) $p->product_quantity,
            'stock_alert' => (int) $p->product_stock_alert,
            'low_stock'   => $p->product_quantity <= $p->product_stock_alert,
            'image_url'   => $p->getFirstMediaUrl('images') ?: null,
        ];
    }

    // GET /api/v1/categories
    public function categories()
    {
        return Category::withCount('products')->orderBy('category_name')->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'code'           => $c->category_code,
                'name'           => $c->category_name,
                'products_count' => $c->products_count,
            ]);
    }

    // GET /api/v1/customers?search=&per_page=
    public function customers(Request $request)
    {
        return Customer::query()
            ->when($request->query('search'), function ($q, $s) {
                $q->where(fn ($q) => $q->where('customer_name', 'like', "%{$s}%")
                                       ->orWhere('customer_phone', 'like', "%{$s}%"));
            })
            ->orderBy('customer_name')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn ($c) => $this->customer($c));
    }

    // GET /api/v1/customers/{id}
    public function customer($customer)
    {
        $c = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);

        return [
            'id'      => $c->id,
            'name'    => $c->customer_name,
            'email'   => $c->customer_email,
            'phone'   => $c->customer_phone,
            'city'    => $c->city,
            'country' => $c->country,
            'address' => $c->address,
        ];
    }

    // GET /api/v1/sales?from=YYYY-MM-DD&to=YYYY-MM-DD&payment_status=&per_page=
    public function sales(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        return Sale::query()
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->query('payment_status'), fn ($q, $s) => $q->where('payment_status', $s))
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn ($s) => $this->saleSummary($s));
    }

    // GET /api/v1/sales/{id}
    public function sale($id)
    {
        $s = Sale::with(['saleDetails', 'salePayments'])->findOrFail($id);

        return $this->saleSummary($s) + [
            'tax_percentage'      => $s->tax_percentage,
            'tax_amount'          => (float) $s->tax_amount,
            'discount_percentage' => $s->discount_percentage,
            'discount_amount'     => (float) $s->discount_amount,
            'shipping_amount'     => (float) $s->shipping_amount,
            'note'                => $s->note,
            'items' => $s->saleDetails->map(fn ($d) => [
                'product_id' => $d->product_id,
                'name'       => $d->product_name,
                'code'       => $d->product_code,
                'quantity'   => (int) $d->quantity,
                'unit_price' => (float) $d->unit_price,
                'sub_total'  => (float) $d->sub_total,
            ]),
            'payments' => $s->salePayments->map(fn ($p) => [
                'date'   => $p->date,
                'amount' => (float) $p->amount,
                'method' => $p->payment_method,
            ]),
        ];
    }

    private function saleSummary(Sale $s): array
    {
        return [
            'id'             => $s->id,
            'reference'      => $s->reference,
            'date'           => $s->date,
            'customer'       => ['id' => $s->customer_id, 'name' => $s->customer_name],
            'total_amount'   => (float) $s->total_amount,
            'paid_amount'    => (float) $s->paid_amount,
            'due_amount'     => (float) $s->due_amount,
            'status'         => $s->status,
            'payment_status' => $s->payment_status,
            'payment_method' => $s->payment_method,
        ];
    }

    // GET /api/v1/summary?date=YYYY-MM-DD  (defaults to today)
    public function summary(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);
        $date = $request->query('date', now()->toDateString());

        $sales = Sale::whereDate('date', $date)->get();

        return [
            'date'              => $date,
            'sales_count'       => $sales->count(),
            'total_sales'       => (float) $sales->sum('total_amount'),
            'total_paid'        => (float) $sales->sum('paid_amount'),
            'total_due'         => (float) $sales->sum('due_amount'),
            'products_count'    => Product::count(),
            'low_stock_count'   => Product::whereColumn('product_quantity', '<=', 'product_stock_alert')->count(),
            'customers_count'   => Customer::count(),
        ];
    }
}
