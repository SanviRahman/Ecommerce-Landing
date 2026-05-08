<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CampaignOrderController extends Controller
{
    public function store(Request $request, Campaign $campaign)
    {
        abort_if(! $campaign->status, 404);

        $request->validate([
            'customer_name'       => ['required', 'string', 'max:255'],
            'phone'               => ['required', 'string', 'max:20'],
            'address'             => ['required', 'string'],
            'delivery_area'       => ['required', 'in:inside_dhaka,outside_dhaka'],

            'courier_service'     => [
                'required',
                'string',
                Rule::in(array_keys(config('couriers.list', []))),
            ],

            'customer_note'       => ['nullable', 'string', 'max:1000'],

            'products'            => ['required', 'array', 'min:1'],
            'products.*.id'       => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($request, $campaign) {
            /*
            |--------------------------------------------------------------------------
            | Campaign Attached Products
            |--------------------------------------------------------------------------
            | Attached thakle campaign price use hobe.
            */
            $campaign->load([
                'products' => function ($query) {
                    $query->where('products.status', true);
                },
            ]);

            $campaignProducts = $campaign->products->keyBy('id');

            /*
            |--------------------------------------------------------------------------
            | Submitted Product IDs
            |--------------------------------------------------------------------------
            | Same product duplicate submit hole quantity merge kore nibe.
            */
            $submittedProducts = collect($request->products)
                ->groupBy('id')
                ->map(function ($items) {
                    return [
                        'id'       => (int) $items->first()['id'],
                        'quantity' => (int) $items->sum('quantity'),
                    ];
                })
                ->values();

            $subTotal = 0;
            $orderItems = [];

            foreach ($submittedProducts as $submittedProduct) {
                $productId = (int) $submittedProduct['id'];

                /*
                |--------------------------------------------------------------------------
                | First priority: campaign attached product
                | Second priority: normal active product
                |--------------------------------------------------------------------------
                */
                $product = $campaignProducts->get($productId);

                if (! $product) {
                    $product = Product::query()
                        ->where('status', true)
                        ->whereKey($productId)
                        ->first();
                }

                if (! $product) {
                    continue;
                }

                $quantity = max(1, (int) $submittedProduct['quantity']);

                /*
                |--------------------------------------------------------------------------
                | Price Calculation
                |--------------------------------------------------------------------------
                | Campaign attached hole pivot campaign_price use hobe.
                | Na hole product new_price use hobe.
                */
                $campaignPrice = 0;

                if (isset($product->pivot)) {
                    $campaignPrice = (int) ($product->pivot->campaign_price ?? 0);
                }

                $unitPrice = $campaignPrice > 0
                    ? $campaignPrice
                    : (int) $product->new_price;

                if ($unitPrice <= 0) {
                    continue;
                }

                $lineTotal = $unitPrice * $quantity;
                $subTotal += $lineTotal;

                $orderItems[] = [
                    'product'    => $product,
                    'quantity'   => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if (empty($orderItems)) {
                return back()
                    ->withInput()
                    ->with('error', 'Please select at least one valid product.');
            }

            $shippingCharge = $request->delivery_area === 'inside_dhaka' ? 70 : 130;
            $codCharge = 0;
            $totalAmount = $subTotal + $shippingCharge + $codCharge;

            $order = Order::create([
                'invoice_id'      => $this->generateInvoiceId(),
                'campaign_id'     => $campaign->id,

                'customer_name'   => $request->customer_name,
                'phone'           => $request->phone,
                'address'         => $request->address,

                'delivery_area'   => $request->delivery_area,
                'courier_service' => $request->courier_service,

                'sub_total'       => $subTotal,
                'shipping_charge' => $shippingCharge,
                'cod_charge'      => $codCharge,
                'total_amount'    => $totalAmount,

                'payment_method'  => 'cash_on_delivery',
                'payment_status'  => 'cod_pending',
                'order_status'    => 'pending',

                'is_fake'         => false,
                'customer_note'   => $request->customer_note,

                'source_ip'       => $request->ip(),
                'user_agent'      => $request->userAgent(),
                'source_url'      => url()->previous(),
            ]);

            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'unit_price'   => $item['unit_price'],
                    'quantity'     => $item['quantity'],
                    'total_price'  => $item['line_total'],
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Auto Assign Order To Active Employee
            |--------------------------------------------------------------------------
            */
            app(OrderAssignmentService::class)->assign($order);

            return redirect()
                ->route('campaign.show', $campaign->slug)
                ->with('success', 'আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে। খুব শীঘ্রই আমাদের প্রতিনিধি যোগাযোগ করবে।');
        });
    }

    private function generateInvoiceId(): string
    {
        do {
            $invoiceId = 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('invoice_id', $invoiceId)->exists());

        return $invoiceId;
    }
}