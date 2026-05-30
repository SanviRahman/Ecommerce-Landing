<?php

namespace Database\Seeders;

use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportExportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * NOTE:
     * - Report Management এখন শুধু CSV Excel-readable file generate করবে।
     * - Seeder থেকে HTML / PDF / Excel format remove করা হয়েছে।
     * - সব seed report-এর format হবে csv এবং file_path সহ real CSV file generate হবে।
     */
    public function run(): void
    {
        $generatedBy = null;

        if (Schema::hasTable('users')) {
            $generatedBy = User::query()->inRandomOrder()->value('id');
        }

        Storage::disk('public')->makeDirectory('reports');

        $reports = [
            [
                'title' => 'Today Order Report',
                'report_type' => 'order_report',
                'group_by' => 'daily',
                'summary' => [
                    'total_orders' => 25,
                    'pending_orders' => 7,
                    'confirmed_orders' => 5,
                    'processing_orders' => 4,
                    'shipped_orders' => 3,
                    'delivered_orders' => 5,
                    'cancelled_orders' => 1,
                    'fake_orders' => 0,
                    'gross_sales' => 35000,
                ],
                'rows' => [
                    ['label' => now()->toDateString(), 'total_orders' => 25, 'total_sales' => 35000],
                ],
            ],
            [
                'title' => 'Monthly Sales Report',
                'report_type' => 'sales_report',
                'group_by' => 'monthly',
                'summary' => [
                    'total_orders' => 120,
                    'gross_sales' => 185000,
                    'delivered_sales' => 145000,
                    'shipping_total' => 9500,
                    'cod_total' => 0,
                    'unique_customers' => 95,
                ],
                'rows' => [
                    ['label' => now()->format('Y-m'), 'total_orders' => 120, 'total_sales' => 185000],
                ],
            ],
            [
                'title' => 'Campaign Performance Report',
                'report_type' => 'campaign_report',
                'group_by' => 'campaign',
                'summary' => [
                    'total_campaigns' => 5,
                    'total_orders' => 75,
                    'gross_sales' => 120000,
                    'best_campaign' => 'Shanto Gift Shop BD',
                ],
                'rows' => [
                    ['label' => 'Shanto Gift Shop BD', 'total_orders' => 75, 'total_sales' => 120000],
                ],
            ],
            [
                'title' => 'Product Sales Report',
                'report_type' => 'product_report',
                'group_by' => 'product',
                'summary' => [
                    'total_products' => 3,
                    'total_quantity' => 210,
                    'total_sales' => 155000,
                    'top_product' => 'Premium Honey',
                    'product_names' => [
                        'Premium Honey',
                        'Bluetooth Headphone',
                        'Natural Face Wash',
                    ],
                ],
                'product_summary' => [
                    ['product_name' => 'Premium Honey', 'total_orders' => 45, 'total_quantity' => 90, 'total_sales' => 75000],
                    ['product_name' => 'Bluetooth Headphone', 'total_orders' => 30, 'total_quantity' => 60, 'total_sales' => 50000],
                    ['product_name' => 'Natural Face Wash', 'total_orders' => 25, 'total_quantity' => 60, 'total_sales' => 30000],
                ],
                'rows' => [
                    ['product_name' => 'Premium Honey', 'total_orders' => 45, 'total_quantity' => 90, 'total_sales' => 75000],
                    ['product_name' => 'Bluetooth Headphone', 'total_orders' => 30, 'total_quantity' => 60, 'total_sales' => 50000],
                    ['product_name' => 'Natural Face Wash', 'total_orders' => 25, 'total_quantity' => 60, 'total_sales' => 30000],
                ],
            ],
            [
                'title' => 'Employee Order Assignment Report',
                'report_type' => 'employee_order_report',
                'group_by' => 'employee',
                'summary' => [
                    'total_employees' => 4,
                    'assigned_orders' => 100,
                    'unassigned_orders' => 20,
                ],
                'rows' => [
                    ['label' => 'Demo Employee', 'total_orders' => 60, 'total_sales' => 90000],
                    ['label' => 'Test Employee', 'total_orders' => 40, 'total_sales' => 65000],
                ],
            ],
            [
                'title' => 'Fake Order Report',
                'report_type' => 'fake_order_report',
                'group_by' => 'status',
                'summary' => [
                    'total_fake_orders' => 8,
                    'fake_rate' => '6.5%',
                ],
                'rows' => [
                    ['label' => 'Fake', 'total_orders' => 8, 'total_sales' => 0],
                ],
            ],
            [
                'title' => 'Payment Report',
                'report_type' => 'payment_report',
                'group_by' => 'payment_status',
                'summary' => [
                    'cod_pending' => 55,
                    'paid' => 30,
                    'failed' => 5,
                    'refunded' => 2,
                ],
                'rows' => [
                    ['label' => 'COD Pending', 'total_orders' => 55, 'total_sales' => 85000],
                    ['label' => 'Collected', 'total_orders' => 30, 'total_sales' => 65000],
                    ['label' => 'Failed', 'total_orders' => 5, 'total_sales' => 0],
                ],
            ],
            [
                'title' => 'Delivery Area Report',
                'report_type' => 'delivery_report',
                'group_by' => 'delivery_area',
                'summary' => [
                    'inside_dhaka_orders' => 60,
                    'outside_dhaka_orders' => 45,
                ],
                'rows' => [
                    ['label' => 'Inside Dhaka', 'total_orders' => 60, 'total_sales' => 98000],
                    ['label' => 'Outside Dhaka', 'total_orders' => 45, 'total_sales' => 76000],
                ],
            ],
            [
                'title' => 'Tracking Pixel Report',
                'report_type' => 'tracking_pixel_report',
                'group_by' => null,
                'summary' => [
                    'total_pixels' => 4,
                    'active_pixels' => 3,
                    'inactive_pixels' => 1,
                ],
                'rows' => [
                    ['label' => 'Facebook', 'total_pixels' => 2, 'active_pixels' => 2, 'inactive_pixels' => 0],
                    ['label' => 'Google', 'total_pixels' => 2, 'active_pixels' => 1, 'inactive_pixels' => 1],
                ],
            ],
        ];

        foreach ($reports as $index => $reportData) {
            $fileData = $this->createExcelReadableCsvFile($reportData);

            ReportExport::create([
                'report_uid' => $this->generateReportUid(),

                'title' => $reportData['title'],
                'report_type' => $reportData['report_type'],

                'date_from' => now()->subDays(30)->toDateString(),
                'date_to' => now()->toDateString(),

                'group_by' => $reportData['group_by'],
                'format' => 'csv',

                'filters' => [
                    'campaign_id' => null,
                    'product_id' => null,
                    'product_ids' => [],
                    'employee_id' => null,
                    'order_status' => null,
                    'payment_status' => null,
                    'delivery_area' => null,
                    'is_fake' => null,
                ],

                'columns' => [
                    'invoice_id',
                    'customer_name',
                    'phone',
                    'delivery_area',
                    'payment_status',
                    'order_status',
                    'total_amount',
                    'created_at',
                ],

                'summary' => $reportData['summary'],

                'file_name' => $fileData['file_name'],
                'file_path' => $fileData['file_path'],
                'file_disk' => $fileData['file_disk'],
                'mime_type' => $fileData['mime_type'],
                'file_size' => $fileData['file_size'],

                'status' => ReportExport::STATUS_COMPLETED,
                'error_message' => null,

                'generated_by' => $generatedBy,
                'generated_at' => now()->subDays($index),

                'created_at' => now()->subDays($index),
                'updated_at' => now()->subDays($index),
            ]);
        }
    }

    private function createExcelReadableCsvFile(array $reportData): array
    {
        $fileName = 'seed-' . $reportData['report_type'] . '-' . now()->format('YmdHis') . '-' . Str::random(5) . '.csv';
        $filePath = 'reports/' . $fileName;

        $csvContent = $this->buildExcelReadableCsv($reportData);

        Storage::disk('public')->put($filePath, $csvContent);

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_disk' => 'public',
            'mime_type' => 'text/csv; charset=UTF-8',
            'file_size' => Storage::disk('public')->size($filePath),
        ];
    }

    private function buildExcelReadableCsv(array $reportData): string
    {
        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM দিলে Bangla/Unicode text Excel-এ readable থাকে।
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['Report Information']);
        fputcsv($handle, ['Report Title', $reportData['title']]);
        fputcsv($handle, ['Report Type', ucwords(str_replace('_', ' ', $reportData['report_type']))]);
        fputcsv($handle, ['Date Range', now()->subDays(30)->toDateString() . ' to ' . now()->toDateString()]);
        fputcsv($handle, ['Group By', $reportData['group_by'] ?: 'Default']);
        fputcsv($handle, ['Format', 'CSV Excel Format']);
        fputcsv($handle, ['Generated At', now()->format('d M Y, h:i A')]);
        fputcsv($handle, []);

        fputcsv($handle, ['Summary']);
        foreach (($reportData['summary'] ?? []) as $key => $value) {
            fputcsv($handle, [
                ucwords(str_replace('_', ' ', $key)),
                is_array($value) || is_object($value) ? implode(', ', (array) $value) : $value,
            ]);
        }
        fputcsv($handle, []);

        if (! empty($reportData['product_summary'])) {
            fputcsv($handle, ['Product Summary']);
            fputcsv($handle, ['Product Name', 'Total Orders', 'Total Quantity', 'Total Sales']);

            foreach ($reportData['product_summary'] as $productRow) {
                fputcsv($handle, [
                    $productRow['product_name'] ?? 'N/A',
                    $productRow['total_orders'] ?? 0,
                    $productRow['total_quantity'] ?? 0,
                    $productRow['total_sales'] ?? 0,
                ]);
            }

            fputcsv($handle, []);
        }

        fputcsv($handle, ['Data']);

        $rows = $reportData['rows'] ?? [];

        if (empty($rows)) {
            fputcsv($handle, ['No data found']);
        } else {
            fputcsv($handle, array_map(
                fn ($heading) => ucwords(str_replace('_', ' ', $heading)),
                array_keys($rows[0])
            ));

            foreach ($rows as $row) {
                fputcsv($handle, array_map(function ($value) {
                    if (is_array($value) || is_object($value)) {
                        return json_encode($value, JSON_UNESCAPED_UNICODE);
                    }

                    return $value;
                }, $row));
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }

    private function generateReportUid(): string
    {
        do {
            $uid = 'RPT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
        } while (ReportExport::where('report_uid', $uid)->exists());

        return $uid;
    }
}
