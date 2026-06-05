<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\Order;
use App\Models\OrderField;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\BdCourierFraudCheckerService;
use App\Services\OrderAssignmentService;
use App\Services\PathaoCourierService;
use App\Services\SteadfastCourierService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class OrderController extends Controller
{
    private function adminOnly(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function adminOrEmployeeOnly(): void
    {
        if (! auth()->check() || (! auth()->user()->isAdmin() && ! auth()->user()->isEmployee())) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function ensureEmployeeOrderAccess(Order $order): void
    {
        if (auth()->user()?->isEmployee() && (int) $order->assigned_employee_id !== (int) auth()->id()) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function accessibleOrderIds(array $ids, bool $trash = false): array
    {
        $query = $trash ? Order::onlyTrashed() : Order::query();

        return $query
            ->forLoggedInUser()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function orderQuery(bool $trash = false): Builder
    {
        $query = $trash ? Order::onlyTrashed() : Order::query();

        return $query
            ->with([
                'campaign',
                'assignedEmployee',
                'items.product',
                'courier',
                'courierAccount',
                'orderField',
            ])
            ->forLoggedInUser()
            ->latest();
    }

    private function getOrderStatuses(): array
    {
        return [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
            Order::STATUS_FAKE,
            Order::STATUS_STOCK_OUT,
        ];
    }

    private function getPaymentStatuses(): array
    {
        return [
            Order::PAYMENT_STATUS_COD_PENDING,
            Order::PAYMENT_STATUS_COLLECTED,
            Order::PAYMENT_STATUS_FAILED,
            Order::PAYMENT_STATUS_UNPAID,
        ];
    }

    private function getCourierServices(): array
    {
        $couriers = Courier::query()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'code')
            ->toArray();

        return array_merge([
            'none' => 'No Courier',
        ], $couriers);
    }

    private function getActiveCouriers(): Collection
    {
        return Courier::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    private function getActiveOrderFields(): Collection
    {
        return OrderField::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->withCount([
                'orders as orders_count' => function ($query) {
                    $query->forLoggedInUser();
                },
            ])
            ->get();
    }

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

        $imageAttributes = [
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
            'avatar',
        ];

        foreach ($imageAttributes as $attribute) {
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


    private function getStats(): array
    {
        $baseQuery = Order::query()->forLoggedInUser();

        $fields = $this->getActiveOrderFields()
            ->map(fn (OrderField $field) => [
                'id' => $field->id,
                'name' => $field->name,
                'slug' => $field->slug,
                'color' => $field->color ?: '#2563eb',
                'count' => (int) $field->orders_count,
            ])
            ->values();

        return [
            'all'       => (clone $baseQuery)->count(),
            'new'       => (clone $baseQuery)->where('order_status', Order::STATUS_PROCESSING)->count(),
            'pending'   => (clone $baseQuery)->where('order_status', Order::STATUS_PENDING)->count(),
            // Complete Orders = confirmed orders. Delivered has separate card/menu.
            'completed' => (clone $baseQuery)->where('order_status', Order::STATUS_CONFIRMED)->count(),
            'delivered' => (clone $baseQuery)->where('order_status', Order::STATUS_DELIVERED)->count(),
            'cancelled' => (clone $baseQuery)->where('order_status', Order::STATUS_CANCELLED)->count(),
            'stock_out' => (clone $baseQuery)->where('order_status', Order::STATUS_STOCK_OUT)->count(),

            // Invoice Management stats.
            'invoice_pending'  => (clone $baseQuery)
                ->where('order_status', Order::STATUS_CONFIRMED)
                ->whereNull('invoice_printed_at')
                ->count(),
            'invoice_complete' => (clone $baseQuery)
                ->whereNotNull('invoice_printed_at')
                ->count(),
            'fake'      => (clone $baseQuery)->where(function ($query) {
                $query->where('is_fake', true)
                    ->orWhere('order_status', Order::STATUS_FAKE);
            })->count(),
            'fields'    => $fields,
        ];
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_id', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('courier_service', 'like', "%{$search}%")
                    ->orWhereHas('assignedEmployee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('courier', function ($courierQuery) use ($search) {
                        $courierQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('orderField', function ($fieldQuery) use ($search) {
                        $fieldQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('campaign', function ($campaignQuery) use ($search) {
                        $campaignQuery->where('title', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($itemQuery) use ($search) {
                        $itemQuery->where('product_name', 'like', "%{$search}%")
                            ->orWhereHas('product', function ($productQuery) use ($search) {
                                $productQuery->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($request->filled('order_status') && $request->order_status !== 'all') {
            $query->where('order_status', $request->order_status);
        }

        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('delivery_area') && $request->delivery_area !== 'all') {
            $query->where('delivery_area', $request->delivery_area);
        }

        if ($request->filled('courier_service') && $request->courier_service !== 'all') {
            if ($request->courier_service === 'none') {
                $query->whereNull('courier_id')->whereNull('courier_service');
            } else {
                $query->where('courier_service', $request->courier_service);
            }
        }

        if ($request->filled('courier_id') && $request->courier_id !== 'all') {
            if ($request->courier_id === 'none') {
                $query->whereNull('courier_id');
            } else {
                $query->where('courier_id', $request->courier_id);
            }
        }

        if ($request->filled('order_field_id') && $request->order_field_id !== 'all') {
            if ($request->order_field_id === 'none') {
                $query->whereNull('order_field_id');
            } else {
                $query->where('order_field_id', $request->order_field_id);
            }
        }

        if ($request->filled('fake_status') && $request->fake_status !== 'all') {
            if ($request->fake_status === 'fake') {
                $query->where(function ($q) {
                    $q->where('is_fake', true)
                        ->orWhere('order_status', Order::STATUS_FAKE);
                });
            }

            if ($request->fake_status === 'real') {
                $query->where(function ($q) {
                    $q->where('is_fake', false)
                        ->orWhereNull('is_fake');
                })->where('order_status', '!=', Order::STATUS_FAKE);
            }
        }

        if ($request->filled('assigned_employee_id') && $request->assigned_employee_id !== 'all') {
            if ($request->assigned_employee_id === 'unassigned') {
                $query->whereNull('assigned_employee_id');
            } else {
                $query->where('assigned_employee_id', $request->assigned_employee_id);
            }
        }

        if ($request->filled('employee_id') && $request->employee_id !== 'all') {
            if ($request->employee_id === 'unassigned') {
                $query->whereNull('assigned_employee_id');
            } else {
                $query->where('assigned_employee_id', $request->employee_id);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    private function listResponse(
        Request $request,
        Builder $query,
        string $title,
        bool $isTrash = false,
        string $currentStatusView = 'active',
        ?OrderField $currentOrderField = null
    ) {
        $query = $this->applyFilters($query, $request);
        $perPageOptions = [20, 50, 100, 150, 200, 250, 300, 350, 400, 450, 500];
        $perPage = (int) $request->input('per_page', 20);

        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 20;
        }

        $orders = $query->paginate($perPage)->withQueryString();

        $employees = User::query()
            ->where('role', 'employee')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $couriers = $this->getActiveCouriers();
        $orderFields = $this->getActiveOrderFields();
        $defaultCourier = CourierAccount::defaultActive();
        $stats = $this->getStats();
        $orderStatuses = $this->getOrderStatuses();
        $paymentStatuses = $this->getPaymentStatuses();
        $courierServices = $this->getCourierServices();

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'stats'  => $stats,
                'html'   => view('admin.orders.partials.table', [
                    'orders'          => $orders,
                    'isTrash'         => $isTrash,
                    'defaultCourier'  => $defaultCourier,
                    'couriers'         => $couriers,
                    'courierServices' => $courierServices,
                    'orderFields'     => $orderFields,
                ])->render(),
            ]);
        }

        return view('admin.orders.index', [
            'title'              => $title,
            'orders'             => $orders,
            'employees'          => $employees,
            'couriers'           => $couriers,
            'courierAccounts'    => collect(),
            'courierServices'    => $courierServices,
            'defaultCourier'     => $defaultCourier,
            'stats'              => $stats,
            'orderStatuses'      => $orderStatuses,
            'paymentStatuses'    => $paymentStatuses,
            'orderFields'        => $orderFields,
            'currentStatusView'  => $currentStatusView,
            'currentOrderField'  => $currentOrderField,
            'isTrash'            => $isTrash,
            'breadcrumb'         => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => $title, 'url' => url()->current()],
            ],
        ]);
    }

    private function activeCourierByCode(string $code): ?CourierAccount
    {
        $courierAccount = CourierAccount::query()
            ->active()
            ->where('code', $code)
            ->where('is_default', true)
            ->latest()
            ->first();

        if (! $courierAccount) {
            $courierAccount = CourierAccount::query()
                ->active()
                ->where('code', $code)
                ->latest()
                ->first();
        }

        return $courierAccount;
    }

    private function activeCourierListByCode(string $code): ?Courier
    {
        return Courier::query()
            ->active()
            ->where('code', $code)
            ->latest()
            ->first();
    }

    private function assignCourierToOrder(Order $order, CourierAccount $courierAccount): Order
    {
        $courier = $this->activeCourierListByCode($courierAccount->code);

        $order->update([
            'courier_id'         => $courier?->id,
            'courier_account_id' => $courierAccount->id,
            'courier_service'    => $courierAccount->code,
        ]);

        return $order->fresh(['courier', 'courierAccount', 'items.product']);
    }


    private function getOrderProductDescription(Order $order): string
    {
        return $order->items
            ->map(function (OrderItem $item) {
                $name = $item->product_name ?: ($item->product->name ?? 'Product');
                $price = (float) ($item->unit_price ?? 0);
                $quantity = (int) ($item->quantity ?? 0);

                return $name . ' (' . number_format($price, 0, '.', '') . 'X' . $quantity . ')';
            })
            ->filter()
            ->implode(' ,');
    }

    private function getOrderProductQuantity(Order $order): int
    {
        return (int) $order->items->sum('quantity');
    }

    private function getOrderProductAmount(Order $order): float
    {
        return (float) $order->items->sum(function (OrderItem $item) {
            return (float) ($item->total_price ?? (($item->unit_price ?? 0) * ($item->quantity ?? 0)));
        });
    }

    private function getOrderStoreName(Order $order): string
    {
        return $order->campaign?->title
            ?: $order->campaign?->name
            ?: config('app.name', 'Store');
    }

    private function getOrderInstruction(Order $order): string
    {
        return collect([
            $order->customer_note,
            $order->admin_note,
        ])->filter()->implode(' | ');
    }

    private function markOrdersAsInvoicePrinted(Collection $orders): void
    {
        $ids = $orders->pluck('id')->filter()->values();

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('orders')
            ->whereIn('id', $ids)
            ->update([
                'invoice_printed_at' => now(),
                'invoice_print_count' => DB::raw('COALESCE(invoice_print_count, 0) + 1'),
                'updated_at' => now(),
            ]);
    }

    private function buildExportSheetData(Collection $orders, string $type): array
    {
        $type = strtolower($type ?: 'default');
        $rows = [];

        if ($type === 'steadfast') {
            /*
             * SteadFast export format
             * Screenshot sample অনুযায়ী simple title রাখা হয়েছে।
             */
            $headers = ['Invoice', 'Name', 'Address', 'Phone', 'Amount', 'Note', 'Contact Name', 'Contact Phone'];
            $fileName = 'SteadFastExport_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

            foreach ($orders as $order) {
                $rows[] = [
                    $order->invoice_id,
                    $order->customer_name,
                    $order->address,
                    $order->phone,
                    (float) $order->total_amount,
                    $this->getOrderInstruction($order),
                    $order->customer_name,
                    $order->phone,
                ];
            }
        } elseif ($type === 'pathao') {
            /*
             * Pathao export থেকে screenshot-e red mark করা unnecessary fields remove করা হয়েছে:
             * ItemType, RecipientCity, RecipientZone, RecipientArea, ItemWeight, ItemDesc, SpecialInstruction.
             */
            $headers = ['Store Name', 'Invoice', 'Name', 'Phone', 'Address', 'Amount', 'Quantity'];
            $fileName = 'PathaoExport_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

            foreach ($orders as $order) {
                $rows[] = [
                    $this->getOrderStoreName($order),
                    $order->invoice_id,
                    $order->customer_name,
                    $order->phone,
                    $order->address,
                    (float) $order->total_amount,
                    $this->getOrderProductQuantity($order),
                ];
            }
        } elseif ($type === 'redex' || $type === 'redx') {
            $headers = ['Invoice', 'Customer Name', 'Contact No.', 'Customer Address', 'District', 'Area', 'Area ID', 'Division', 'Products', 'Price', 'Weight(g)', 'Instruction', 'Product Selling Price', 'Seller Name', 'Seller Phone'];
            $fileName = 'RedxExport_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

            foreach ($orders as $order) {
                $rows[] = [
                    $order->invoice_id,
                    $order->customer_name,
                    $order->phone,
                    $order->address,
                    '',
                    '',
                    '',
                    '',
                    $this->getOrderProductDescription($order),
                    (float) $order->total_amount,
                    500,
                    $this->getOrderInstruction($order),
                    $this->getOrderProductAmount($order),
                    '',
                    '',
                ];
            }
        } else {
            $headers = ['Store Name', 'Order Id', 'Customer Name', 'Customer Phone No', 'Customer Address', 'Item Description', 'Item Quentity', 'Order Amount', 'Delivery Charge', 'Total'];
            $fileName = 'orders_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

            foreach ($orders as $order) {
                $rows[] = [
                    $this->getOrderStoreName($order),
                    $order->invoice_id,
                    $order->customer_name,
                    $order->phone,
                    $order->address,
                    $this->getOrderProductDescription($order),
                    $this->getOrderProductQuantity($order),
                    $this->getOrderProductAmount($order),
                    (float) $order->shipping_charge,
                    (float) $order->total_amount,
                ];
            }
        }

        return [$headers, $rows, $fileName];
    }

    private function xmlEscape($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function xlsxColumnName(int $columnNumber): string
    {
        $columnName = '';

        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $columnName = chr(65 + $remainder) . $columnName;
            $columnNumber = intdiv($columnNumber - 1, 26);
        }

        return $columnName;
    }

    private function buildXlsxSheetXml(array $headers, array $rows): string
    {
        $sheetRows = array_merge([$headers], $rows);
        $xmlRows = [];

        foreach ($sheetRows as $rowIndex => $row) {
            $excelRowNumber = $rowIndex + 1;
            $cells = [];

            foreach (array_values($row) as $columnIndex => $value) {
                $cellReference = $this->xlsxColumnName($columnIndex + 1) . $excelRowNumber;

                /*
                 * inlineStr ব্যবহার করা হয়েছে যাতে Bangla text, phone number,
                 * invoice id, address সবকিছু Excel-এ safe text হিসেবে থাকে।
                 */
                $cells[] = '<c r="' . $cellReference . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $this->xmlEscape($value)
                    . '</t></is></c>';
            }

            $xmlRows[] = '<row r="' . $excelRowNumber . '">' . implode('', $cells) . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
            . '</worksheet>';
    }

    private function downloadXlsx(array $headers, array $rows, string $fileName)
    {
        /*
         * PhpSpreadsheet package ছাড়া native XLSX generate করা হচ্ছে।
         * Server-e ZipArchive disabled থাকলে fallback হিসেবে Excel-compatible .xls download হবে।
         */
        if (! class_exists(\ZipArchive::class)) {
            return $this->downloadExcelHtml($headers, $rows, str_replace('.xlsx', '.xls', $fileName));
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'orders_export_');

        if ($tempFile === false) {
            return back()->with('error', 'Temporary file create করা যায়নি।');
        }

        $zip = new \ZipArchive();

        if ($zip->open($tempFile, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);

            return $this->downloadExcelHtml($headers, $rows, str_replace('.xlsx', '.xls', $fileName));
        }

        $sheetXml = $this->buildXlsxSheetXml($headers, $rows);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Orders" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>');

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return response()
            ->download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
            ])
            ->deleteFileAfterSend(true);
    }

    private function downloadExcelHtml(array $headers, array $rows, string $fileName)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';

            echo '<tr>';
            foreach ($headers as $header) {
                echo '<th>' . e($header) . '</th>';
            }
            echo '</tr>';

            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $value) {
                    echo '<td style="mso-number-format:\@;">' . e((string) ($value ?? '')) . '</td>';
                }
                echo '</tr>';
            }

            echo '</table></body></html>';
        }, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function index(Request $request)
    {
        return $this->new($request);
    }

    public function all(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery(), 'All Orders', false, 'all');
    }

    public function new(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse(
            $request,
            $this->orderQuery()->newOrders(),
            'New Orders',
            false,
            'new'
        );
    }

    public function pending(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->pending(), 'Pending Orders', false, 'pending');
    }

    public function confirmed(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->confirmed(), 'Complete Orders', false, 'completed');
    }

    public function processing(Request $request)
    {
        return $this->new($request);
    }

    public function shipped(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->shipped(), 'Shipped Orders', false, 'shipped');
    }

    public function delivered(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->delivered(), 'Delivered Orders', false, 'delivered');
    }

    public function cancelled(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->cancelled(), 'Cancelled Orders', false, 'cancelled');
    }

    public function fake(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->fake(), 'Fake Orders', false, 'fake');
    }

    public function stockOut(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->stockOut(), 'Stock Out Orders', false, 'stock-out');
    }

    public function pendingInvoices(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse(
            $request,
            $this->orderQuery()
                ->where('order_status', Order::STATUS_CONFIRMED)
                ->whereNull('invoice_printed_at'),
            'Pending Invoice',
            false,
            'pending-invoice'
        );
    }

    public function completeInvoices(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse(
            $request,
            $this->orderQuery()->whereNotNull('invoice_printed_at'),
            'Complete Invoice',
            false,
            'complete-invoice'
        );
    }

    public function orderListOne(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse(
            $request,
            $this->orderQuery()->where('custom_order_list', 'order_list_1'),
            'Order List 1',
            false,
            'order-list-1'
        );
    }

    public function orderListTwo(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse(
            $request,
            $this->orderQuery()->where('custom_order_list', 'order_list_2'),
            'Order List 2',
            false,
            'order-list-2'
        );
    }

    public function field(Request $request, OrderField $orderField)
    {
        $this->adminOrEmployeeOnly();
        abort_if(! $orderField->status, 404);

        return $this->listResponse(
            $request,
            $this->orderQuery()->where('order_field_id', $orderField->id),
            $orderField->name,
            false,
            'field',
            $orderField
        );
    }

    public function trash(Request $request)
    {
        $this->adminOnly();

        return $this->listResponse($request, $this->orderQuery(true), 'Order Trash', true, 'trash');
    }

    public function show(Order $order)
    {
        $this->adminOrEmployeeOnly();

        $this->ensureEmployeeOrderAccess($order);

        $order->load([
            'campaign',
            'assignedEmployee',
            'items.product',
            'statusLogs',
            'fakeLogs',
            'courier',
            'courierAccount',
            'orderField',
        ]);

        return view('admin.orders.show', [
            'title'      => 'Order Details',
            'order'      => $order,
            'couriers'   => $this->getActiveCouriers(),
            'orderFields'=> $this->getActiveOrderFields(),
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Orders', 'url' => route('admin.orders.index')],
                ['text' => $order->invoice_id, 'url' => route('admin.orders.show', $order->id)],
            ],
        ]);
    }

    public function edit(Order $order)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);

        $order->load(['items.product', 'assignedEmployee', 'courier', 'orderField']);

        $products = Product::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $productImageMap = $products
            ->mapWithKeys(fn (Product $product) => [
                $product->id => $this->resolveProductImageUrl($product),
            ]);

        return view('admin.orders.edit', [
            'title'           => 'Edit Order - ' . $order->invoice_id,
            'order'           => $order,
            'products'        => $products,
            'productImageMap' => $productImageMap,
            'employees'       => User::query()->where('role', 'employee')->where('is_active', true)->orderBy('name')->get(),
            'couriers'        => $this->getActiveCouriers(),
            'orderFields'     => $this->getActiveOrderFields(),
            'orderStatuses'   => $this->getOrderStatuses(),
            'paymentStatuses' => $this->getPaymentStatuses(),
            'breadcrumb'      => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Orders', 'url' => route('admin.orders.index')],
                ['text' => 'Edit Order', 'url' => route('admin.orders.edit', $order->id)],
            ],
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);

        if (auth()->user()?->isEmployee()) {
            $request->merge([
                'assigned_employee_id' => $order->assigned_employee_id,
            ]);
        }

        $request->validate([
            'invoice_id'           => ['required', 'string', 'max:255', Rule::unique('orders', 'invoice_id')->ignore($order->id)],
            'customer_name'        => ['required', 'string', 'max:255'],
            'phone'                => ['required', 'string', 'max:20'],
            'address'              => ['required', 'string'],
            'delivery_area'        => ['nullable', 'string', 'max:255'],
            'customer_note'        => ['nullable', 'string'],
            'admin_note'           => ['nullable', 'string'],
            'order_status'         => ['required', 'string', Rule::in($this->getOrderStatuses())],
            'payment_status'       => ['required', 'string', Rule::in($this->getPaymentStatuses())],
            'shipping_charge'      => ['nullable', 'numeric', 'min:0'],
            'cod_charge'           => ['nullable', 'numeric', 'min:0'],
            'assigned_employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'courier_id'           => ['nullable', 'integer', 'exists:couriers,id'],
            'order_field_id'       => ['nullable', 'integer', 'exists:order_fields,id'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.id'           => ['nullable', 'integer', 'exists:order_items,id'],
            'items.*.product_id'   => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($request, $order) {
            $products = Product::query()
                ->whereIn('id', collect($request->items)->pluck('product_id')->filter()->unique())
                ->get()
                ->keyBy('id');

            $subTotal = 0;
            $keepItemIds = [];

            foreach ($request->items as $row) {
                $product = $products->get((int) $row['product_id']);

                if (! $product) {
                    continue;
                }

                $quantity = max(1, (int) $row['quantity']);
                $unitPrice = max(0, (float) $row['unit_price']);
                $discountAmount = max(0, (float) ($row['discount_amount'] ?? 0));
                $grossLineTotal = $quantity * $unitPrice;
                $lineTotal = max(0, $grossLineTotal - $discountAmount);
                $subTotal += $lineTotal;

                $item = null;

                if (! empty($row['id'])) {
                    $item = $order->items()->whereKey((int) $row['id'])->first();
                }

                if (! $item) {
                    $item = new OrderItem(['order_id' => $order->id]);
                }

                $item->fill([
                    'order_id'     => $order->id,
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'unit_price'       => $unitPrice,
                    'quantity'         => $quantity,
                    'discount_amount'  => $discountAmount,
                    'total_price'      => $lineTotal,
                ])->save();

                $keepItemIds[] = $item->id;
            }

            if (! count($keepItemIds)) {
                return back()->withInput()->with('error', 'Please add at least one valid product.');
            }

            $order->items()->whereNotIn('id', $keepItemIds)->delete();

            $courier = null;
            $courierAccount = null;

            if ($request->filled('courier_id')) {
                $courier = Courier::query()->active()->find($request->courier_id);

                if ($courier && in_array($courier->code, ['steadfast', 'pathao'], true)) {
                    $courierAccount = $this->activeCourierByCode($courier->code);
                }
            }

            $shippingCharge = (float) ($request->shipping_charge ?? 0);
            $codCharge = (float) ($request->cod_charge ?? 0);
            $totalAmount = $subTotal + $shippingCharge + $codCharge;
            $status = $request->order_status;

            $updateData = [
                'invoice_id'           => $request->invoice_id,
                'customer_name'        => $request->customer_name,
                'phone'                => $request->phone,
                'address'              => $request->address,
                'delivery_area'        => $request->delivery_area,
                'customer_note'        => $request->customer_note,
                'admin_note'           => $request->admin_note,
                'order_status'         => $status,
                'payment_status'       => $request->payment_status,
                'assigned_employee_id' => $request->assigned_employee_id ?: null,
                'order_field_id'       => $request->order_field_id ?: null,
                'courier_id'           => $courier?->id,
                'courier_account_id'   => $courierAccount?->id,
                'courier_service'      => $courier?->code,
                'sub_total'            => $subTotal,
                'shipping_charge'      => $shippingCharge,
                'is_free_delivery'     => $shippingCharge <= 0,
                'cod_charge'           => $codCharge,
                'total_amount'         => $totalAmount,
            ];

            if ($status === Order::STATUS_CONFIRMED && ! $order->confirmed_at) {
                $updateData['confirmed_at'] = now();
            }

            if ($status === Order::STATUS_DELIVERED && ! $order->delivered_at) {
                $updateData['delivered_at'] = now();
            }

            if ($status === Order::STATUS_CANCELLED && ! $order->cancelled_at) {
                $updateData['cancelled_at'] = now();
            }

            if ($status === Order::STATUS_FAKE) {
                $updateData['is_fake'] = true;
                $updateData['marked_fake_at'] = $order->marked_fake_at ?: now();
            }

            $order->update($updateData);

            return redirect()
                ->route('admin.orders.edit', $order->id)
                ->with('success', 'Order updated successfully.');
        });
    }

    public function updateCourier(Request $request, Order $order)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);

        $request->validate([
            'courier_id' => ['nullable', 'exists:couriers,id'],
        ]);

        $courier = null;
        if ($request->filled('courier_id')) {
            $courier = Courier::query()->active()->findOrFail($request->courier_id);
        }

        $courierAccount = null;
        if ($courier && in_array($courier->code, ['steadfast', 'pathao'], true)) {
            $courierAccount = $this->activeCourierByCode($courier->code);
        }

        $order->update([
            'courier_id'         => $courier?->id,
            'courier_account_id' => $courierAccount?->id,
            'courier_service'    => $courier?->code,
        ]);

        return response()->json([
            'status'  => true,
            'message' => $courier ? 'Courier selected successfully: ' . $courier->name : 'Courier removed successfully.',
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);

        $request->validate([
            'order_status' => ['required', 'string'],
            'note'         => ['nullable', 'string', 'max:1000'],
        ]);

        $status = $request->order_status;
        $updateData = ['order_status' => $status];

        if ($status === Order::STATUS_CONFIRMED) {
            $updateData['confirmed_at'] = now();
        }
        if ($status === Order::STATUS_DELIVERED) {
            $updateData['delivered_at'] = now();
        }
        if ($status === Order::STATUS_CANCELLED) {
            $updateData['cancelled_at'] = now();
        }
        if ($status === Order::STATUS_FAKE) {
            $updateData['is_fake'] = true;
            $updateData['marked_fake_at'] = now();
        }

        $order->update($updateData);

        OrderStatusLog::create([
            'order_id'   => $order->id,
            'status'     => $status,
            'note'       => $request->note,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Order status updated successfully.',
        ]);
    }

    public function updatePaymentStatus(Request $request, Order $order)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);

        $request->validate([
            'payment_status' => ['required', 'string'],
        ]);

        $order->update([
            'payment_status' => $request->payment_status,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Payment status updated successfully.',
        ]);
    }

    public function updateAdminNote(Request $request, Order $order)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);

        $request->validate([
            'admin_note' => ['nullable', 'string'],
        ]);

        $order->update([
            'admin_note' => $request->admin_note,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Admin note updated successfully.',
        ]);
    }

    public function updateOrderField(Request $request, Order $order)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);

        $request->validate([
            'order_field_id' => ['nullable', 'integer', 'exists:order_fields,id'],
        ]);

        $order->update([
            'order_field_id' => $request->order_field_id ?: null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order field updated successfully.',
        ]);
    }

    public function markAsFake(Request $request, Order $order)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);

        $request->validate([
            'fake_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $order->update([
            'is_fake'        => true,
            'order_status'   => Order::STATUS_FAKE,
            'marked_fake_at' => now(),
        ]);

        $order->fakeLogs()->create([
            'fake_reason' => $request->fake_reason ?: 'Marked manually',
            'detected_by' => 'manual',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Order marked as fake.',
        ]);
    }

    public function restoreFake(Order $order)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);

        $order->update([
            'is_fake'        => false,
            'order_status'   => Order::STATUS_PENDING,
            'marked_fake_at' => null,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Fake order restored successfully.',
        ]);
    }

    public function assignUnassignedOrders()
    {
        $this->adminOnly();

        $count = app(OrderAssignmentService::class)->assignUnassigned();

        return response()->json([
            'status'  => true,
            'message' => "{$count} orders assigned successfully.",
        ]);
    }

    public function selectedInvoices(Request $request)
    {
        $this->adminOrEmployeeOnly();

        $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $orders = Order::query()
            ->forLoggedInUser()
            ->whereIn('id', $request->ids)
            ->with(['items.product', 'campaign', 'courier', 'courierAccount', 'assignedEmployee'])
            ->get();

        // Important: selected invoices are NOT marked as printed here.
        // They will stay in Pending Invoice until admin confirms from the print preview page.
        $siteSetting = SiteSetting::query()->where('status', true)->latest()->first();
        $courierServices = $this->getCourierServices();

        return view('admin.orders.multiple-invoices', [
            'title'           => 'Selected Invoices',
            'orders'          => $orders,
            'siteSetting'     => $siteSetting,
            'courierServices' => $courierServices,
            'selectedOrderIds' => $orders->pluck('id')->values(),
        ]);
    }

    public function markSelectedInvoicesPrinted(Request $request)
    {
        $this->adminOrEmployeeOnly();

        $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $orders = Order::query()
            ->forLoggedInUser()
            ->whereIn('id', $request->ids)
            ->where('order_status', Order::STATUS_CONFIRMED)
            ->whereNull('invoice_printed_at')
            ->get();

        $this->markOrdersAsInvoicePrinted($orders);

        return response()->json([
            'status'  => true,
            'message' => $orders->count() . ' invoices marked as printed successfully.',
            'printed' => $orders->count(),
        ]);
    }

    public function exportSelected(Request $request)
    {
        $this->adminOrEmployeeOnly();

        $ids = $request->input('ids', $request->query('ids', []));

        if (is_string($ids)) {
            $ids = collect(explode(',', $ids))->filter()->values()->all();
        }

        $request->merge(['ids' => $ids]);

        $request->validate([
            'type'  => ['nullable', 'string', Rule::in(['steadfast', 'pathao', 'redex', 'redx', 'default', 'general'])],
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $orders = Order::query()
            ->forLoggedInUser()
            ->whereIn('id', $request->ids)
            ->with(['items.product', 'campaign', 'courier', 'courierAccount', 'assignedEmployee'])
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return back()->with('error', 'No selected orders found for export.');
        }

        [$headers, $rows, $fileName] = $this->buildExportSheetData($orders, $request->input('type', 'default'));

        return $this->downloadXlsx($headers, $rows, $fileName);
    }

    public function invoice(Order $order)
    {
        $this->adminOrEmployeeOnly();

        $this->ensureEmployeeOrderAccess($order);

        $order->load(['items.product', 'campaign', 'courier', 'courierAccount', 'assignedEmployee']);

        /*
         * Important:
         * Single invoice preview/open korlei invoice complete hobe na.
         * Print dialog close howar pore admin popup-e Yes dile only then
         * invoice_printed_at update hobe and Pending Invoice theke Complete Invoice-e jabe.
         */
        $siteSetting = SiteSetting::query()->where('status', true)->latest()->first();
        $courierServices = $this->getCourierServices();

        return view('admin.orders.multiple-invoices', [
            'title'            => 'Invoice - ' . $order->invoice_id,
            'orders'           => collect([$order]),
            'siteSetting'      => $siteSetting,
            'courierServices'  => $courierServices,
            'selectedOrderIds' => collect([$order->id])->values(),
        ]);
    }

    public function downloadInvoice(Order $order)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);

        $order->load(['items.product', 'campaign', 'courier', 'courierAccount', 'assignedEmployee']);

        $pdf = Pdf::loadView('admin.orders.invoice-pdf', [
            'order'           => $order,
            'siteSetting'     => SiteSetting::query()->where('status', true)->latest()->first(),
            'courierServices' => $this->getCourierServices(),
        ]);

        return $pdf->download($order->invoice_id . '.pdf');
    }

    public function sendToSteadfast(Order $order, SteadfastCourierService $steadfastCourierService)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);
        $courierAccount = $this->activeCourierByCode('steadfast');

        if (! $courierAccount) {
            return response()->json(['status' => false, 'message' => 'Active SteadFast courier API account not found.'], 422);
        }

        try {
            $order = $this->assignCourierToOrder($order, $courierAccount);
            $data = $steadfastCourierService->createOrder($order);

            return response()->json([
                'status'  => true,
                'message' => data_get($data, 'message', 'Order sent to SteadFast successfully.'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }


    public function bulkAssignCourier(Request $request)
    {
        $this->adminOrEmployeeOnly();

        $request->validate([
            'ids'        => ['required', 'array', 'min:1'],
            'ids.*'      => ['integer', 'exists:orders,id'],
            'courier_id' => ['required'],
        ]);

        $accessibleIds = $this->accessibleOrderIds($request->ids);

        if (empty($accessibleIds)) {
            return response()->json([
                'status'  => false,
                'message' => 'No accessible selected orders found.',
            ], 403);
        }

        $courierId = $request->input('courier_id');

        if ($courierId === 'none') {
            $updated = Order::query()
                ->whereIn('id', $accessibleIds)
                ->update([
                    'courier_id'         => null,
                    'courier_account_id' => null,
                    'courier_service'    => null,
                    'updated_at'         => now(),
                ]);

            return response()->json([
                'status'  => true,
                'message' => "{$updated} selected orders removed from courier successfully.",
                'updated' => $updated,
            ]);
        }

        $courier = Courier::query()
            ->active()
            ->findOrFail((int) $courierId);

        $courierAccount = null;
        $courierCode = strtolower((string) ($courier->code ?? ''));

        if (in_array($courierCode, ['steadfast', 'pathao'], true)) {
            $courierAccount = $this->activeCourierByCode($courierCode);
        }

        $updated = Order::query()
            ->whereIn('id', $accessibleIds)
            ->update([
                'courier_id'         => $courier->id,
                'courier_account_id' => $courierAccount?->id,
                'courier_service'    => $courier->code,
                'updated_at'         => now(),
            ]);

        return response()->json([
            'status'  => true,
            'message' => "{$updated} selected orders assigned to {$courier->name} successfully.",
            'updated' => $updated,
        ]);
    }

    public function bulkSendToSteadfast(Request $request, SteadfastCourierService $steadfastCourierService)
    {
        $this->adminOrEmployeeOnly();
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:orders,id']]);

        $courierAccount = $this->activeCourierByCode('steadfast');
        if (! $courierAccount) {
            return response()->json(['status' => false, 'message' => 'Active SteadFast courier API account not found.'], 422);
        }

        $accessibleIds = $this->accessibleOrderIds($request->ids);

        if (empty($accessibleIds)) {
            return response()->json(['status' => false, 'message' => 'No accessible selected orders found.'], 403);
        }

        $orders = Order::query()->with(['courier', 'courierAccount', 'items.product'])->whereIn('id', $accessibleIds)->get();

        if ($orders->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No selected orders found.'], 422);
        }

        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($orders as $order) {
            try {
                $order = $this->assignCourierToOrder($order, $courierAccount);
                $steadfastCourierService->createOrder($order);
                $success++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = $order->invoice_id . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'status'  => true,
            'message' => "SteadFast bulk send completed. Success: {$success}, Failed: {$failed}",
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors,
        ]);
    }

    public function sendToPathao(Order $order, PathaoCourierService $pathaoCourierService)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);
        $courierAccount = $this->activeCourierByCode('pathao');

        if (! $courierAccount) {
            return response()->json(['status' => false, 'message' => 'Active Pathao courier API account not found.'], 422);
        }

        try {
            $order = $this->assignCourierToOrder($order, $courierAccount);
            $data = $pathaoCourierService->createOrder($order);

            return response()->json([
                'status'  => true,
                'message' => data_get($data, 'message', 'Order sent to Pathao successfully.'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function bulkSendToPathao(Request $request, PathaoCourierService $pathaoCourierService)
    {
        $this->adminOrEmployeeOnly();
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:orders,id']]);

        $courierAccount = $this->activeCourierByCode('pathao');
        if (! $courierAccount) {
            return response()->json(['status' => false, 'message' => 'Active Pathao courier API account not found.'], 422);
        }

        $accessibleIds = $this->accessibleOrderIds($request->ids);

        if (empty($accessibleIds)) {
            return response()->json(['status' => false, 'message' => 'No accessible selected orders found.'], 403);
        }

        $orders = Order::query()->with(['courier', 'courierAccount', 'items.product'])->whereIn('id', $accessibleIds)->get();

        if ($orders->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No selected orders found.'], 422);
        }

        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($orders as $order) {
            try {
                $order = $this->assignCourierToOrder($order, $courierAccount);
                $pathaoCourierService->createOrder($order);
                $success++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = $order->invoice_id . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'status'  => true,
            'message' => "Pathao bulk send completed. Success: {$success}, Failed: {$failed}",
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors,
        ]);
    }

    public function syncSteadfastStatus(Order $order, SteadfastCourierService $steadfastCourierService)
    {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);
        $order->loadMissing('courierAccount');
        $courierAccount = $this->activeCourierByCode('steadfast');

        if (! $courierAccount) {
            return response()->json(['status' => false, 'message' => 'Active SteadFast courier API account not found.'], 422);
        }

        if ($order->courierAccount?->code !== 'steadfast') {
            $order = $this->assignCourierToOrder($order, $courierAccount);
        }

        try {
            $data = $steadfastCourierService->syncStatus($order);

            return response()->json([
                'status'  => true,
                'message' => 'SteadFast status synced successfully.',
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function steadfastBalance(SteadfastCourierService $steadfastCourierService)
    {
        $this->adminOrEmployeeOnly();

        $courierAccount = CourierAccount::query()->active()->where('code', 'steadfast')->default()->latest()->first();

        try {
            $data = $steadfastCourierService->getBalance($courierAccount);

            return response()->json([
                'status'  => true,
                'message' => 'Balance fetched successfully.',
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Order $order)
    {
        $this->adminOnly();
        $order->delete();

        return response()->json(['status' => true, 'message' => 'Order moved to trash successfully.']);
    }

    public function restore($id)
    {
        $this->adminOnly();
        Order::onlyTrashed()->findOrFail($id)->restore();

        return response()->json(['status' => true, 'message' => 'Order restored successfully.']);
    }

    public function forceDelete($id)
    {
        $this->adminOnly();
        Order::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json(['status' => true, 'message' => 'Order permanently deleted successfully.']);
    }

    public function multipleAction(Request $request)
    {
        $this->adminOrEmployeeOnly();

        $request->validate([
            'action' => ['required', 'string'],
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer'],
        ]);

        $action = $request->action;
        $ids = $request->ids;

        if (in_array($action, ['delete', 'restore', 'force_delete'], true)) {
            $this->adminOnly();
        }

        if ($action === 'delete') {
            Order::whereIn('id', $ids)->delete();
            return response()->json(['status' => true, 'message' => 'Selected orders moved to trash.']);
        }

        if ($action === 'restore') {
            Order::onlyTrashed()->whereIn('id', $ids)->restore();
            return response()->json(['status' => true, 'message' => 'Selected orders restored.']);
        }

        if ($action === 'force_delete') {
            Order::onlyTrashed()->whereIn('id', $ids)->forceDelete();
            return response()->json(['status' => true, 'message' => 'Selected orders permanently deleted.']);
        }

        $accessibleIds = $this->accessibleOrderIds($ids);

        if (empty($accessibleIds)) {
            return response()->json([
                'status' => false,
                'message' => 'No accessible selected orders found.',
            ], 403);
        }

        if (str_starts_with($action, 'status_')) {
            $status = str_replace('status_', '', $action);

            if (in_array($status, $this->getOrderStatuses(), true)) {
                Order::whereIn('id', $accessibleIds)->update([
                    'order_status' => $status,
                    'updated_at'   => now(),
                ]);

                return response()->json(['status' => true, 'message' => 'Selected orders status updated.']);
            }
        }

        if (str_starts_with($action, 'field_')) {
            $fieldId = (int) str_replace('field_', '', $action);
            $field = OrderField::query()->active()->find($fieldId);

            if ($field) {
                Order::whereIn('id', $accessibleIds)->update([
                    'order_field_id' => $field->id,
                    'updated_at'     => now(),
                ]);

                return response()->json(['status' => true, 'message' => 'Selected orders moved to ' . $field->name . '.']);
            }
        }

        if ($action === 'field_none') {
            Order::whereIn('id', $accessibleIds)->update([
                'order_field_id' => null,
                'updated_at'     => now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Selected orders removed from custom order field.']);
        }

        if ($action === 'order_list_1') {
            Order::whereIn('id', $accessibleIds)->update([
                'custom_order_list' => 'order_list_1',
                'order_status'      => Order::STATUS_CONFIRMED,
                'updated_at'        => now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Selected orders moved to Order List 1.']);
        }

        if ($action === 'order_list_2') {
            Order::whereIn('id', $accessibleIds)->update([
                'custom_order_list' => 'order_list_2',
                'order_status'      => Order::STATUS_CONFIRMED,
                'updated_at'        => now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Selected orders moved to Order List 2.']);
        }

        if ($action === 'order_list_none') {
            Order::whereIn('id', $accessibleIds)->update([
                'custom_order_list' => null,
                'updated_at'        => now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Selected orders removed from static order list.']);
        }

        return response()->json(['status' => false, 'message' => 'Invalid action selected.'], 422);
    }

    public function bulkAssignEmployee(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'ids'                  => ['required', 'array', 'min:1'],
            'ids.*'                => ['integer', 'exists:orders,id'],
            'assigned_employee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $employeeId = $request->assigned_employee_id ?: null;

        if ($employeeId) {
            User::query()
                ->where('role', 'employee')
                ->where('is_active', true)
                ->findOrFail($employeeId);
        }

        $updated = Order::query()
            ->whereIn('id', $request->ids)
            ->update([
                'assigned_employee_id' => $employeeId,
                'updated_at'           => now(),
            ]);

        return response()->json([
            'status'  => true,
            'message' => $employeeId
                ? "{$updated} selected orders assigned to employee successfully."
                : "{$updated} selected orders unassigned successfully.",
            'updated' => $updated,
        ]);
    }

    public function bulkDeleteLimit(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'limit' => ['required', 'integer', Rule::in([50,100,150,200,250,300,350,400,450,500])],
        ]);

        $ids = $this->orderQuery()
            ->when($request->filled('current_status_view'), function ($query) use ($request) {
                match ($request->current_status_view) {
                    'new' => $query->newOrders(),
                    'pending' => $query->pending(),
                    'shipped' => $query->shipped(),
                    'completed' => $query->confirmed(),
                    'delivered' => $query->delivered(),
                    'cancelled' => $query->cancelled(),
                    'pending-invoice' => $query->confirmed()->whereNull('invoice_printed_at'),
                    'complete-invoice' => $query->whereNotNull('invoice_printed_at'),
                    'stock-out' => $query->stockOut(),
                    'order-list-1' => $query->orderListOne(),
                    'order-list-2' => $query->orderListTwo(),
                    default => $query,
                };
            })
            ->limit((int) $request->limit)
            ->pluck('id');

        $deleted = Order::query()->whereIn('id', $ids)->delete();

        return response()->json([
            'status' => true,
            'message' => "{$deleted} orders moved to trash successfully.",
            'deleted' => $deleted,
        ]);
    }

    public function emptyTrash()
    {
        $this->adminOnly();

        $deleted = Order::onlyTrashed()->forceDelete();

        return response()->json([
            'status' => true,
            'message' => "Trash bin emptied successfully. {$deleted} orders permanently deleted.",
            'deleted' => $deleted,
        ]);
    }

    public function fraudCheck(Order $order, BdCourierFraudCheckerService $bdCourierFraudCheckerService)
    {
        $this->adminOrEmployeeOnly();

        $this->ensureEmployeeOrderAccess($order);

        try {
            $data = $bdCourierFraudCheckerService->check($order->phone);

            return response()->json([
                'status'  => true,
                'message' => 'Fraud checker data fetched successfully.',
                'order'   => [
                    'id'            => $order->id,
                    'invoice_id'    => $order->invoice_id,
                    'customer_name' => $order->customer_name,
                    'phone'         => $order->phone,
                ],
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function storeOrderField(Request $request)
    {
        $this->adminOrEmployeeOnly();

        $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:order_fields,name'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $maxSort = (int) OrderField::query()->max('sort_order');

        $field = OrderField::create([
            'name' => trim($request->name),
            'color' => $request->color ?: '#2563eb',
            'status' => true,
            'sort_order' => $maxSort + 1,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order field created successfully.',
            'field' => [
                'id' => $field->id,
                'name' => $field->name,
                'slug' => $field->slug,
                'url' => route('admin.orders.field', $field->slug),
                'count' => 0,
                'color' => $field->color,
            ],
        ]);
    }

    public function orderFields()
    {
        $this->adminOrEmployeeOnly();

        $fields = $this->getActiveOrderFields()
            ->map(fn (OrderField $field) => [
                'id' => $field->id,
                'name' => $field->name,
                'slug' => $field->slug,
                'url' => route('admin.orders.field', $field->slug),
                'count' => (int) $field->orders_count,
                'color' => $field->color ?: '#2563eb',
            ])
            ->values();

        return response()->json([
            'status' => true,
            'fields' => $fields,
        ]);
    }
}
