<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BulkOrderBatch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CampaignAutoSelectionService;
use App\Services\CustomerIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BulkOrderEntryController extends Controller
{
    private const MAX_LINES = 500;

    private function adminOrEmployeeOnly(): void
    {
        if (! auth()->check() || (! auth()->user()->isAdmin() && ! auth()->user()->isEmployee())) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function safeOrderReturnUrl(?string $url = null): string
    {
        $fallback = route('admin.orders.index');

        if (! $url) {
            return $fallback;
        }

        $url = trim($url);
        $baseUrl = request()->getSchemeAndHttpHost();

        if (str_starts_with($url, $baseUrl)) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return $baseUrl . $url;
        }

        return $fallback;
    }

    public function create(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return view('admin.orders.bulk-create', [
            'title' => 'Create Bulk Orders',
            'returnUrl' => $this->safeOrderReturnUrl(
                $request->query('return_url', route('admin.orders.index'))
            ),
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Orders', 'url' => route('admin.orders.index')],
                ['text' => 'Bulk Order', 'url' => route('admin.orders.bulk_create')],
            ],
        ]);
    }

    public function store(
        Request $request,
        CustomerIdentityService $customerIdentityService,
        CampaignAutoSelectionService $campaignAutoSelectionService
    ) {
        $this->adminOrEmployeeOnly();

        $validated = $request->validate([
            'bulk_orders' => ['required', 'string', 'max:100000'],
            'return_url'  => ['nullable', 'string', 'max:2048'],
        ], [
            'bulk_orders.required' => 'Please enter at least one bulk order line.',
        ]);

        [$rows, $products] = $this->validateAndPrepareRows(
            $validated['bulk_orders'],
            $customerIdentityService,
            $campaignAutoSelectionService
        );

        $currentUser = auth()->user();
        $isEmployeeCreator = $currentUser?->isEmployee() === true;

        $batch = DB::transaction(function () use (
            $request,
            $validated,
            $rows,
            $products,
            $currentUser,
            $isEmployeeCreator,
            $customerIdentityService
        ) {
            $batch = BulkOrderBatch::query()->create([
                'batch_uid'       => $this->generateBatchUid(),
                'created_by'      => $currentUser->id,
                'created_via'     => $isEmployeeCreator
                    ? Order::CREATED_VIA_EMPLOYEE_BULK
                    : Order::CREATED_VIA_ADMIN_BULK,
                'raw_input'       => $validated['bulk_orders'],
                'total_orders'    => 0,
                'total_customers' => 0,
                'total_items'     => 0,
                'total_amount'    => 0,
            ]);

            $customerIds = [];
            $totalItems = 0;
            $totalAmount = 0.0;

            foreach ($rows as $row) {
                $customer = $customerIdentityService->resolveOrCreate(
                    $row['customer_name'],
                    $row['phone'],
                    'bulk_orders'
                );

                $customerIds[] = $customer->id;
                $catalogSubTotal = 0.0;
                $negotiatedFinalTotal = (float) $row['negotiated_price'];
                $preparedItems = [];

                foreach ($row['items'] as $item) {
                    $product = $products->get($item['lookup_code']);
                    $quantity = (int) $item['quantity'];
                    $unitPrice = max(0, (float) $product->new_price);
                    $lineTotal = $unitPrice * $quantity;

                    $catalogSubTotal += $lineTotal;
                    $totalItems += $quantity;

                    $preparedItems[] = [
                        'product'     => $product,
                        'quantity'    => $quantity,
                        'unit_price'  => $unitPrice,
                        'total_price' => $lineTotal,
                    ];
                }

                $order = Order::query()->create([
                    'invoice_id'           => $this->generateInvoiceId(),
                    'success_token'        => Str::random(40),
                    'campaign_id'          => $row['campaign_id'] ?? null,
                    'customer_id'          => $customer->id,
                    'bulk_order_batch_id'  => $batch->id,
                    'assigned_employee_id' => $isEmployeeCreator ? $currentUser->id : null,
                    'created_via'          => $isEmployeeCreator
                        ? Order::CREATED_VIA_EMPLOYEE_BULK
                        : Order::CREATED_VIA_ADMIN_BULK,
                    'created_by_admin_id'  => $currentUser->id,

                    'customer_name'        => $customer->name,
                    'phone'                => $customer->phone,
                    'address'              => $row['address'],
                    'delivery_area'        => null,

                    'courier_service'      => null,
                    'courier_account_id'   => null,
                    'courier_id'           => null,

                    // Product rows keep the current products.new_price for display.
                    // Price: is the negotiated FINAL total and already includes delivery.
                    // Delivery is not free; its amount is intentionally not separated.
                    // The invoice detects bulk created_via and shows "Included in Total".
                    'sub_total'            => $catalogSubTotal,
                    'shipping_charge'      => 0,
                    'is_free_delivery'     => false,
                    'cod_charge'           => 0,
                    'total_amount'         => $negotiatedFinalTotal,

                    'payment_method'       => Order::PAYMENT_COD,
                    'payment_status'       => Order::PAYMENT_STATUS_COD_PENDING,
                    'order_status'         => Order::STATUS_PROCESSING,

                    'is_fake'              => false,
                    // Keep the initial note empty. After the Order model finishes
                    // round-robin assignment, a short employee-based note is saved.
                    'admin_note'           => null,
                    'source_ip'            => $request->ip(),
                    'user_agent'           => $request->userAgent(),
                    'source_url'           => route('admin.orders.bulk_create'),
                ]);

                foreach ($preparedItems as $item) {
                    OrderItem::query()->create([
                        'order_id'        => $order->id,
                        'product_id'      => $item['product']->id,
                        'product_name'    => $item['product']->name,
                        'quantity'        => $item['quantity'],
                        'unit_price'      => $item['unit_price'],
                        'discount_amount' => 0,
                        'total_price'     => $item['total_price'],
                    ]);
                }

                /*
                 * Order::created runs the existing assignment service. For an
                 * Admin-created bulk order this gives us the round-robin employee;
                 * for an Employee-created bulk order it keeps the current employee.
                 * Save only the concise note requested by the client.
                 */
                $order->loadMissing('assignedEmployee');

                $bulkCompletionNote = $this->makeBulkCompletionNote(
                    $order,
                    $currentUser
                );

                // Direct query avoids firing unrelated Order updated events/logs.
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'admin_note' => $bulkCompletionNote,
                        'updated_at' => now(),
                    ]);

                $order->forceFill([
                    'admin_note' => $bulkCompletionNote,
                ]);

                $totalAmount += $negotiatedFinalTotal;
            }

            $batch->update([
                'total_orders'    => count($rows),
                'total_customers' => count(array_unique($customerIds)),
                'total_items'     => $totalItems,
                'total_amount'    => $totalAmount,
            ]);

            return $batch;
        });

        return redirect()
            ->to($this->safeOrderReturnUrl($validated['return_url'] ?? null))
            ->with(
                'success',
                sprintf(
                    '%d bulk orders created successfully. Batch: %s',
                    $batch->total_orders,
                    $batch->batch_uid
                )
            );
    }

    /**
     * Build the short Admin Note requested for a bulk order.
     *
     * The assigned employee is preferred so Admin-created round-robin orders
     * show the same employee name as the Employee column. If assignment is not
     * available, the authenticated Admin/Employee name is used as fallback.
     *
     * Capitalizing "Bulk Order" intentionally keeps "Order completed by"
     * inside the text. The existing OrderController duplicate-note protection
     * therefore will not append another order-completed line later.
     */
    private function makeBulkCompletionNote(Order $order, $currentUser): string
    {
        $employeeName = trim((string) (
            $order->assignedEmployee?->name
            ?: $currentUser?->name
            ?: 'System'
        ));

        $timezone = method_exists(Order::class, 'displayTimezone')
            ? Order::displayTimezone()
            : config('app.order_display_timezone', 'Asia/Dhaka');

        $completedAt = $order->created_at
            ? $order->created_at->copy()->timezone($timezone)
            : now()->timezone($timezone);

        return sprintf(
            'Bulk Order completed by %s on %s at %s.',
            $employeeName,
            $completedAt->format('d M Y'),
            $completedAt->format('h:i A')
        );
    }

    private function validateAndPrepareRows(
        string $rawInput,
        CustomerIdentityService $customerIdentityService,
        CampaignAutoSelectionService $campaignAutoSelectionService
    ): array {
        [$records, $formatErrors] = $this->parseInputRecords($rawInput);

        if (empty($records)) {
            throw ValidationException::withMessages([
                'bulk_orders' => 'Please enter at least one complete customer order.',
            ]);
        }

        if (count($records) > self::MAX_LINES) {
            throw ValidationException::withMessages([
                'bulk_orders' => 'A maximum of ' . self::MAX_LINES . ' orders can be submitted at once.',
            ]);
        }

        $rows = [];
        $errors = $formatErrors;
        $batchPhoneOwners = [];
        $allLookupCodes = [];

        foreach ($records as $index => $record) {
            $orderNumber = $index + 1;
            $lineNumber = (int) ($record['start_line'] ?? $orderNumber);
            $orderLabel = "Order {$orderNumber} (starting line {$lineNumber})";

            $name = trim((string) ($record['customer_name'] ?? ''));
            $phone = $customerIdentityService->normalizePhone(
                (string) ($record['phone'] ?? '')
            );
            $address = trim((string) ($record['address'] ?? ''));
            $productText = trim((string) ($record['product_text'] ?? ''));
            $priceText = trim((string) ($record['negotiated_price'] ?? ''));
            $negotiatedPrice = $this->parseNegotiatedPrice($priceText);
            $normalizedName = $customerIdentityService->normalizeName($name);

            if ($name === '' || mb_strlen($name) > 255) {
                $errors[] = "{$orderLabel}: customer name is required and must be within 255 characters.";
            }

            if (! preg_match('/^01\d{9}$/', $phone)) {
                $errors[] = "{$orderLabel}: phone must be exactly 11 digits and start with 01.";
            }

            if ($address === '' || mb_strlen($address) > 2000) {
                $errors[] = "{$orderLabel}: address is required and must be within 2000 characters.";
            }

            if ($negotiatedPrice === null) {
                $errors[] = "{$orderLabel}: The fifth value must be a valid positive final price, for example 500.";
            } elseif ($negotiatedPrice <= 0 || $negotiatedPrice > 999999999.99) {
                $errors[] = "{$orderLabel}: Price must be greater than 0 and not exceed 999999999.99.";
            }

            if (preg_match('/^01\d{9}$/', $phone)) {
                if (
                    isset($batchPhoneOwners[$phone])
                    && $batchPhoneOwners[$phone] !== $normalizedName
                ) {
                    $errors[] = "{$orderLabel}: phone {$phone} is already used by another customer name in this bulk input.";
                } else {
                    $batchPhoneOwners[$phone] = $normalizedName;
                }
            }

            $items = $this->parseProductItems(
                $productText,
                $lineNumber,
                $errors,
                $orderNumber
            );

            foreach ($items as $item) {
                $allLookupCodes[] = $item['lookup_code'];
            }

            $rows[] = [
                'line_number'   => $lineNumber,
                'order_number'  => $orderNumber,
                'customer_name' => $name,
                'phone'         => $phone,
                'address'          => $address,
                'negotiated_price' => $negotiatedPrice ?? 0,
                'items'            => $items,
            ];
        }

        $validPhones = collect(array_keys($batchPhoneOwners))
            ->filter(fn ($phone) => preg_match('/^01\d{9}$/', $phone))
            ->values();

        if ($validPhones->isNotEmpty()) {
            $existingCustomers = Customer::query()
                ->whereIn('phone', $validPhones)
                ->get(['name', 'normalized_name', 'phone'])
                ->keyBy('phone');

            foreach ($batchPhoneOwners as $phone => $normalizedName) {
                $customer = $existingCustomers->get($phone);

                if ($customer && $customer->normalized_name !== $normalizedName) {
                    $errors[] = sprintf(
                        'Phone %s already belongs to customer "%s" and cannot be used by another customer.',
                        $phone,
                        $customer->name
                    );
                }
            }
        }

        $products = $this->loadProductsByCodes(array_unique($allLookupCodes));

        foreach ($rows as $rowIndex => $row) {
            $productIds = [];

            foreach ($row['items'] as $item) {
                if (! $products->has($item['lookup_code'])) {
                    $errors[] = sprintf(
                        'Order %d (starting line %d): active product code "%s" was not found.',
                        $row['order_number'],
                        $row['line_number'],
                        $item['original_code']
                    );

                    continue;
                }

                $productIds[] = (int) $products->get($item['lookup_code'])->id;
            }

            $rows[$rowIndex]['campaign_id'] = $campaignAutoSelectionService->resolveForProductIds($productIds);
        }

        if ($errors) {
            throw ValidationException::withMessages([
                'bulk_orders' => implode("\n", array_slice(array_values(array_unique($errors)), 0, 50)),
            ]);
        }

        return [$rows, $products];
    }

    /**
     * Parse the textarea format shown in the Bulk Order form.
     *
     * Preferred format (one blank line between customers):
     * Rohim
     * 01711111111
     * Dhaka, Bangladesh
     * PROD-0001:2, PROD-0002
     * 500
     *
     * Product items keep their current products.new_price for display.
     * Price is the Admin/Employee negotiated final order total, including delivery.
     * No separate Delivery charge line is required.
     * The previous pipe format remains accepted with Price as the fifth value.
     */
    private function parseInputRecords(string $rawInput): array
    {
        $lines = preg_split('/\R/u', $rawInput) ?: [];

        $hasPipeFormat = collect($lines)->contains(
            fn ($line) => substr_count(trim((string) $line), '|') >= 3
        );

        $hasPrefixedFormat = collect($lines)->contains(function ($line) {
            return preg_match(
                '/^(name|phone|address|product\s*codes?|products?|price|final\s*price|total\s*price|negotiated\s*price)\s*[:：]/iu',
                trim((string) $line)
            ) === 1;
        });

        if (! $hasPipeFormat && ! $hasPrefixedFormat) {
            return $this->parsePositionalInputRecords($lines);
        }

        $records = [];
        $errors = [];
        $current = [];
        $currentStartLine = null;

        foreach ($lines as $zeroBasedIndex => $rawLine) {
            $lineNumber = $zeroBasedIndex + 1;
            $line = trim((string) $rawLine);

            if ($line === '') {
                $this->flushInputRecord(
                    $records,
                    $current,
                    $currentStartLine
                );
                continue;
            }

            // Keep the old one-line pipe syntax working without changing old data entry habits.
            if (substr_count($line, '|') >= 3) {
                $this->flushInputRecord(
                    $records,
                    $current,
                    $currentStartLine
                );

                $parts = array_map('trim', explode('|', $line, 5));

                if (count($parts) < 4) {
                    $errors[] = "Line {$lineNumber}: invalid pipe format.";
                    continue;
                }

                $records[] = [
                    'start_line'       => $lineNumber,
                    'customer_name'    => $parts[0] ?? '',
                    'phone'            => $parts[1] ?? '',
                    'address'          => $parts[2] ?? '',
                    'product_text'     => $parts[3] ?? '',
                    'negotiated_price' => $parts[4] ?? '',
                ];

                continue;
            }

            if (! preg_match(
                '/^(name|phone|address|product\s*codes?|products?|price|final\s*price|total\s*price|negotiated\s*price)\s*[:：]\s*(.*)$/iu',
                $line,
                $matches
            )) {
                $errors[] = "Line {$lineNumber}: invalid legacy format. Use five value-only lines per order.";
                continue;
            }

            $field = $this->normalizeInputFieldName($matches[1]);
            $value = trim((string) $matches[2]);

            // A new Name: starts the next customer even when the blank line was omitted.
            if ($field === 'customer_name' && ! empty($current)) {
                $this->flushInputRecord(
                    $records,
                    $current,
                    $currentStartLine
                );
            }

            if ($currentStartLine === null) {
                $currentStartLine = $lineNumber;
            }

            if (array_key_exists($field, $current)) {
                $errors[] = "Line {$lineNumber}: duplicate {$matches[1]} field in the same customer order.";
            }

            $current[$field] = $value;
        }

        $this->flushInputRecord(
            $records,
            $current,
            $currentStartLine
        );

        return [$records, $errors];
    }

    private function parsePositionalInputRecords(array $lines): array
    {
        $blocks = [];
        $currentBlock = [];
        $currentStartLine = null;

        foreach ($lines as $zeroBasedIndex => $rawLine) {
            $lineNumber = $zeroBasedIndex + 1;
            $value = trim((string) $rawLine);

            if ($value === '') {
                if (! empty($currentBlock)) {
                    $blocks[] = [
                        'start_line' => $currentStartLine ?: $lineNumber,
                        'values'     => $currentBlock,
                    ];
                }

                $currentBlock = [];
                $currentStartLine = null;
                continue;
            }

            if ($currentStartLine === null) {
                $currentStartLine = $lineNumber;
            }

            $currentBlock[] = [
                'line'  => $lineNumber,
                'value' => $value,
            ];
        }

        if (! empty($currentBlock)) {
            $blocks[] = [
                'start_line' => $currentStartLine ?: 1,
                'values'     => $currentBlock,
            ];
        }

        $records = [];
        $errors = [];

        foreach ($blocks as $block) {
            $values = $block['values'];

            if (count($values) % 5 !== 0) {
                $errors[] = sprintf(
                    'Starting line %d: each order requires exactly 5 value-only lines: name, phone, address, product codes and final price.',
                    (int) $block['start_line']
                );

                continue;
            }

            foreach (array_chunk($values, 5) as $chunk) {
                $records[] = [
                    'start_line'       => (int) $chunk[0]['line'],
                    'customer_name'    => (string) $chunk[0]['value'],
                    'phone'            => (string) $chunk[1]['value'],
                    'address'          => (string) $chunk[2]['value'],
                    'product_text'     => (string) $chunk[3]['value'],
                    'negotiated_price' => (string) $chunk[4]['value'],
                ];
            }
        }

        return [$records, $errors];
    }

    private function normalizeInputFieldName(string $field): string
    {
        $field = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $field)), 'UTF-8');

        return match (true) {
            $field === 'name' => 'customer_name',
            $field === 'phone' => 'phone',
            $field === 'address' => 'address',
            in_array($field, ['price', 'final price', 'total price', 'negotiated price'], true) => 'negotiated_price',
            default => 'product_text',
        };
    }

    private function flushInputRecord(
        array &$records,
        array &$current,
        ?int &$currentStartLine
    ): void {
        if (empty($current)) {
            $currentStartLine = null;
            return;
        }

        $records[] = [
            'start_line'    => $currentStartLine ?: 1,
            'customer_name' => (string) ($current['customer_name'] ?? ''),
            'phone'         => (string) ($current['phone'] ?? ''),
            'address'          => (string) ($current['address'] ?? ''),
            'product_text'     => (string) ($current['product_text'] ?? ''),
            'negotiated_price' => (string) ($current['negotiated_price'] ?? ''),
        ];

        $current = [];
        $currentStartLine = null;
    }

    /**
     * Parse the negotiated final price entered by Admin/Employee.
     * Accepted examples: 500, 500.50, 1,500, ৳500, 500 taka,
     * and the screenshot-style value: 500 (set by Admin/Employee).
     */
    private function parseNegotiatedPrice(string $value): ?float
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match(
            '/^\s*(?:৳|tk\.?|taka)?\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)(?:\s*(?:৳|tk\.?|taka))?(?:\s*\([^\r\n]*\))?\s*$/iu',
            $value,
            $matches
        )) {
            return null;
        }

        $normalized = str_replace(',', '', $matches[1]);

        if (! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function parseProductItems(
        string $productText,
        int $lineNumber,
        array &$errors,
        ?int $orderNumber = null
    ): array {
        $label = $orderNumber
            ? "Order {$orderNumber} (starting line {$lineNumber})"
            : "Line {$lineNumber}";
        $tokens = collect(explode(',', $productText))
            ->map(fn ($token) => trim((string) $token))
            ->filter();

        if ($tokens->isEmpty()) {
            $errors[] = "{$label}: at least one product code is required.";
            return [];
        }

        $grouped = [];

        foreach ($tokens as $token) {
            if (! preg_match('/^([^:*]+?)(?:\s*[:*]\s*(\d+))?$/u', $token, $matches)) {
                $errors[] = "{$label}: invalid product entry \"{$token}\".";
                continue;
            }

            $originalCode = trim($matches[1]);
            $lookupCode = mb_strtolower($originalCode, 'UTF-8');
            $quantity = isset($matches[2]) ? (int) $matches[2] : 1;

            if ($originalCode === '' || mb_strlen($originalCode) > 255) {
                $errors[] = "{$label}: invalid product code.";
                continue;
            }

            if ($quantity < 1 || $quantity > 100000) {
                $errors[] = "{$label}: quantity for {$originalCode} must be between 1 and 100000.";
                continue;
            }

            if (! isset($grouped[$lookupCode])) {
                $grouped[$lookupCode] = [
                    'lookup_code'   => $lookupCode,
                    'original_code' => $originalCode,
                    'quantity'      => 0,
                ];
            }

            $grouped[$lookupCode]['quantity'] += $quantity;
        }

        return array_values($grouped);
    }

    private function loadProductsByCodes(array $lookupCodes): Collection
    {
        $lookupCodes = collect($lookupCodes)
            ->map(fn ($code) => mb_strtolower(trim((string) $code), 'UTF-8'))
            ->filter()
            ->unique()
            ->values();

        if ($lookupCodes->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->where('status', true)
            ->where(function ($query) use ($lookupCodes) {
                foreach ($lookupCodes as $code) {
                    $query->orWhereRaw('LOWER(product_code) = ?', [$code]);
                }
            })
            ->get()
            ->keyBy(fn (Product $product) => mb_strtolower((string) $product->product_code, 'UTF-8'));
    }

    private function generateInvoiceId(): string
    {
        do {
            $invoiceId = 'INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Order::withTrashed()->where('invoice_id', $invoiceId)->exists());

        return $invoiceId;
    }

    private function generateBatchUid(): string
    {
        do {
            $batchUid = 'BULK-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
        } while (BulkOrderBatch::query()->where('batch_uid', $batchUid)->exists());

        return $batchUid;
    }
}
