<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

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

        $orderedAt = $this->input(
            'ordered_at',
            $this->input('created_at', $this->input('order_created_at'))
        );

        $sourceUpdatedAt = $this->input(
            'source_updated_at',
            $this->input('updated_at', $orderedAt)
        );

        $wasShipped = $this->input('was_shipped');

        if ($wasShipped === null) {
            $wasShipped = filled($this->input('shipped_at'))
                || in_array(
                    strtolower(trim((string) $this->input('order_status'))),
                    ['shipped', 'dispatched'],
                    true
                );
        }

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
            'ordered_at' => $orderedAt,
            'source_updated_at' => $sourceUpdatedAt,
            'was_shipped' => $wasShipped,
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
            'source_timeline_version' => ['nullable', 'integer', 'min:1', 'max:100'],
            'source_timezone' => ['nullable', 'timezone'],

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

            /*
             * ordered_at is mandatory. Silently replacing a missing source date
             * with API receive time would make historical imports appear in
             * today's Dashboard/Report and is therefore intentionally rejected.
             */
            'ordered_at' => ['required', 'date'],
            'source_updated_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'confirmed_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'shipped_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'was_shipped' => ['required', 'boolean'],
            'delivered_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'cancelled_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'invoice_printed_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'invoice_print_count' => ['nullable', 'integer', 'min:0', 'max:65535'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.source_product_id' => ['nullable', 'string', 'max:191'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('ordered_at')) {
                return;
            }

            try {
                $orderedAt = Carbon::parse((string) $this->input('ordered_at'))->utc();
            } catch (\Throwable) {
                return;
            }

            /*
             * Small clock differences are allowed, but a source order timestamp
             * far in the future is rejected instead of contaminating reports.
             */
            if ($orderedAt->greaterThan(now()->utc()->addMinutes(10))) {
                $validator->errors()->add(
                    'ordered_at',
                    'The source order time is too far in the future. Check the source website clock/timezone.'
                );
            }

            $wasShipped = filter_var(
                $this->input('was_shipped'),
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE
            );

            if ($wasShipped === false && filled($this->input('shipped_at'))) {
                $validator->errors()->add(
                    'was_shipped',
                    'was_shipped cannot be false when shipped_at is provided.'
                );
            }

            $status = strtolower(trim((string) $this->input('order_status')));

            if (in_array($status, ['shipped', 'dispatched'], true) && $wasShipped !== true) {
                $validator->errors()->add(
                    'was_shipped',
                    'A shipped/dispatched order must include was_shipped=true.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'external_order_id.required' => 'A unique external_order_id is required.',
            'sync_uuid.uuid' => 'sync_uuid must be a valid UUID.',
            'phone.regex' => 'Phone number must contain exactly 11 local digits and start with 01.',
            'ordered_at.required' => 'The original source order time (ordered_at) is required so daily reports remain accurate.',
            'source_updated_at.after_or_equal' => 'source_updated_at cannot be earlier than ordered_at.',
            'confirmed_at.after_or_equal' => 'confirmed_at cannot be earlier than ordered_at.',
            'shipped_at.after_or_equal' => 'shipped_at cannot be earlier than ordered_at.',
            'delivered_at.after_or_equal' => 'delivered_at cannot be earlier than ordered_at.',
            'cancelled_at.after_or_equal' => 'cancelled_at cannot be earlier than ordered_at.',
            'invoice_printed_at.after_or_equal' => 'invoice_printed_at cannot be earlier than ordered_at.',
            'was_shipped.required' => 'was_shipped is required so shipped totals remain accurate.',
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
