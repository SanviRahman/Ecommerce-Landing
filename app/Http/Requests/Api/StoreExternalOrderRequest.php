<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', $this->input('products', []));

        if (is_array($items)) {
            $items = array_map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                return [
                    'source_product_id' => $item['source_product_id'] ?? $item['product_id'] ?? $item['id'] ?? null,
                    'product_name' => $item['product_name'] ?? $item['name'] ?? $item['title'] ?? null,
                    'quantity' => $item['quantity'] ?? $item['qty'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? $item['price'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? $item['discount'] ?? 0,
                    'total_price' => $item['total_price'] ?? $item['line_total'] ?? null,
                ];
            }, $items);
        }

        $externalOrderId = $this->input(
            'external_order_id',
            $this->input('sync_uuid', $this->input('order_id', $this->input('invoice_id')))
        );

        $this->merge([
            'external_order_id' => $externalOrderId,
            'sync_uuid' => $this->input('sync_uuid', $externalOrderId),
            'customer_name' => $this->input('customer_name', $this->input('name')),
            'phone' => $this->normalizePhone(
                $this->input('phone', $this->input('customer_phone'))
            ),
            'address' => $this->input('address', $this->input('customer_address')),
            'sub_total' => $this->input('sub_total', $this->input('subtotal')),
            'shipping_charge' => $this->input(
                'shipping_charge',
                $this->input('delivery_charge', 0)
            ),
            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        return [
            'external_order_id' => ['required', 'string', 'max:191'],
            'sync_uuid' => ['nullable', 'uuid'],
            'source_order_id' => ['nullable', 'string', 'max:191'],
            'source_invoice_id' => ['nullable', 'string', 'max:191'],
            'source_website_name' => ['nullable', 'string', 'max:255'],
            'source_website_domain' => ['nullable', 'url:http,https', 'max:2000'],

            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^01[0-9]{9}$/'],
            'address' => ['required', 'string', 'max:5000'],
            'delivery_area' => ['nullable', 'string', 'max:255'],

            'sub_total' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'shipping_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'cod_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'total_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],

            'payment_method' => ['nullable', 'string', 'max:100'],
            'payment_status' => ['nullable', 'string', 'max:100'],
            'order_status' => ['nullable', 'string', 'max:100'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],

            'source_ip' => ['nullable', 'ip'],
            'user_agent' => ['nullable', 'string', 'max:2000'],
            'source_url' => ['nullable', 'url:http,https', 'max:2000'],
            'ordered_at' => ['nullable', 'date'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.source_product_id' => ['nullable', 'string', 'max:191'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'external_order_id.required' => 'A unique external_order_id is required.',
            'sync_uuid.uuid' => 'sync_uuid must be a valid UUID.',
            'phone.regex' => 'Phone number must contain exactly 11 local digits and start with 01.',
            'items.required' => 'At least one order item is required.',
            'items.*.product_name.required' => 'Every order item must include product_name.',
        ];
    }

    private function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if (strlen($phone) === 13 && str_starts_with($phone, '8801')) {
            return substr($phone, 2);
        }

        return $phone;
    }
}
