<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingCharge;
use App\Services\OrderAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CampaignOrderController extends Controller
{
    private function normalizeProductImagePath(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) || str_starts_with($value, 'data:') || str_starts_with($value, '//')) {
            return $value;
        }

        $value = ltrim($value, '/');

        if (str_starts_with($value, 'public/')) {
            return Storage::url(substr($value, 7));
        }

        if (str_starts_with($value, 'storage/app/public/')) {
            return Storage::url(substr($value, 19));
        }

        if (str_starts_with($value, 'storage/')) {
            return asset($value);
        }

        return Storage::url($value);
    }

    private function resolveProductImageUrl(?Product $product): ?string
    {
        if (! $product) {
            return null;
        }

        foreach ([
            'image_url',
            'thumbnail_url',
            'photo_url',
            'product_image_url',
            'image',
            'photo',
            'thumbnail',
            'thumb',
            'product_image',
            'product_photo',
            'main_image',
            'featured_image',
            'featured_photo',
            'image_path',
            'photo_path',
            'thumbnail_path',
            'picture',
        ] as $attribute) {
            try {
                $value = $product->{$attribute} ?? null;
            } catch (\Throwable $e) {
                $value = null;
            }

            if (is_array($value)) {
                $value = collect($value)->filter()->first();
            }

            if (is_string($value) && str_starts_with(trim($value), '[')) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = collect($decoded)->filter()->first();
                }
            }

            if ($url = $this->normalizeProductImagePath(is_string($value) ? $value : null)) {
                return $url;
            }
        }

        if (method_exists($product, 'getFirstMediaUrl')) {
            foreach ([
                'product_image',
                'product_images',
                'product_thumbnail',
                'product_photo',
                'product_gallery',
                'product',
                'products',
                'image',
                'images',
                'photo',
                'photos',
                'thumbnail',
                'thumb',
                'gallery',
                'main_image',
                'featured_image',
                'default',
            ] as $collection) {
                try {
                    $url = $product->getFirstMediaUrl($collection);

                    if ($url) {
                        return $url;
                    }
                } catch (\Throwable $e) {
                    // Continue checking other media collections.
                }
            }

            try {
                $url = $product->getFirstMediaUrl();

                if ($url) {
                    return $url;
                }
            } catch (\Throwable $e) {
                // No default media collection available.
            }
        }

        return null;
    }

    public function store(Request $request, Campaign $campaign)
    {
        abort_if(! $campaign->status, 404);

        $request->validate([
            'customer_name'       => ['required', 'string', 'max:255'],
            'phone'               => ['required', 'string', 'max:20'],
            'address'             => ['required', 'string'],
            'delivery_area'       => ['required', 'string', 'max:255'],
            'customer_note'       => ['nullable', 'string', 'max:1000'],
            'order_form_token'    => ['required', 'string', 'max:100'],

            'products'            => ['required', 'array', 'min:1'],
            'products.*.id'       => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (! $this->consumeOrderFormToken($request->input('order_form_token'))) {
            return back()
                ->withInput($request->except(['products']))
                ->with('error', 'এই পুরোনো ফর্ম দিয়ে আবার অর্ডার করা যাবে না। অনুগ্রহ করে পেজ refresh করে নতুন করে অর্ডার করুন।');
        }

        return DB::transaction(function () use ($request, $campaign) {
            $campaign->load([
                'products' => function ($query) {
                    $query->where('products.status', true);
                },
            ]);

            $campaignProducts = $campaign->products->keyBy('id');

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
                $campaignPrice = isset($product->pivot) ? (int) ($product->pivot->campaign_price ?? 0) : 0;
                $unitPrice = $campaignPrice > 0 ? $campaignPrice : (int) $product->new_price;

                if ($unitPrice <= 0) {
                    continue;
                }

                $lineTotal = $unitPrice * $quantity;
                $subTotal += $lineTotal;

                $orderItems[] = [
                    'product'          => $product,
                    'quantity'         => $quantity,
                    'unit_price'       => $unitPrice,
                    'line_total'       => $lineTotal,
                    'is_free_delivery' => (bool) ($product->is_free_delivery ?? false),
                ];
            }

            if (empty($orderItems)) {
                return back()
                    ->withInput()
                    ->with('error', 'Please select at least one valid product.');
            }

            $hasAnyFreeDeliveryProduct = collect($orderItems)
                ->contains(fn ($item) => (bool) $item['is_free_delivery']);

            $selectedShippingCharge = null;

            if (! $hasAnyFreeDeliveryProduct) {
                $selectedShippingCharge = ShippingCharge::query()
                    ->active()
                    ->whereKey($request->delivery_area)
                    ->first();

                // Safe fallback for older frontend values before dynamic shipping charge update.
                if (! $selectedShippingCharge && in_array($request->delivery_area, ['inside_dhaka', 'outside_dhaka'], true)) {
                    $shippingCharge = $request->delivery_area === 'inside_dhaka' ? 70 : 130;
                    $deliveryAreaName = $request->delivery_area;
                } else {
                    if (! $selectedShippingCharge) {
                        return back()
                            ->withInput()
                            ->with('error', 'Please select a valid delivery area.');
                    }

                    $shippingCharge = (int) $selectedShippingCharge->delivery_charge;
                    $deliveryAreaName = $selectedShippingCharge->area_name;
                }
            } else {
                $shippingCharge = 0;
                $deliveryAreaName = 'free_delivery';
            }

            $codCharge = 0;
            $totalAmount = $subTotal + $shippingCharge + $codCharge;

            $order = Order::create([
                'invoice_id'        => $this->generateInvoiceId(),
                'success_token'     => Str::random(40),
                'campaign_id'       => $campaign->id,

                'customer_name'     => $request->customer_name,
                'phone'             => $request->phone,
                'address'           => $request->address,
                'delivery_area'     => $deliveryAreaName,

                'courier_service'    => null,
                'courier_account_id' => null,
                'courier_id'         => null,

                'sub_total'         => $subTotal,
                'shipping_charge'   => $shippingCharge,
                'is_free_delivery'  => $hasAnyFreeDeliveryProduct,
                'cod_charge'        => $codCharge,
                'total_amount'      => $totalAmount,

                'payment_method'    => Order::PAYMENT_COD,
                'payment_status'    => Order::PAYMENT_STATUS_COD_PENDING,

                // New customer order will be listed in both All Orders and New Orders.
                // New Orders means Processing status according to your requirement.
                'order_status'      => Order::STATUS_PROCESSING,

                'is_fake'           => false,
                'customer_note'     => $request->customer_note,

                'source_ip'         => $request->ip(),
                'user_agent'        => $request->userAgent(),
                'source_url'        => url()->previous(),
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

            app(OrderAssignmentService::class)->assign($order);

            return redirect()
                ->route('order.success', $order->success_token)
                ->with('success', 'আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে। খুব শীঘ্রই আমাদের প্রতিনিধি যোগাযোগ করবে।');
        });
    }

    private function consumeOrderFormToken(?string $token): bool
    {
        if (! $token) {
            return false;
        }

        $tokens = session()->get('campaign_order_form_tokens', []);

        if (! is_array($tokens) || ! in_array($token, $tokens, true)) {
            return false;
        }

        $tokens = array_values(array_filter($tokens, fn ($storedToken) => $storedToken !== $token));

        session()->put('campaign_order_form_tokens', $tokens);

        return true;
    }

    private function generateInvoiceId(): string
    {
        do {
            $invoiceId = 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('invoice_id', $invoiceId)->exists());

        return $invoiceId;
    }
}

