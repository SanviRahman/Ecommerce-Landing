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
     */
    public function run(): void
    {
        $generatedBy = null;

        if (Schema::hasTable('users')) {
            $generatedBy = User::query()->inRandomOrder()->value('id');
        }

        $reports = [
            [
                'title' => 'Today Order Report',
                'report_type' => 'order_report',
                'group_by' => 'daily',
                'format' => 'html',
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
            ],
            [
                'title' => 'Monthly Sales Report',
                'report_type' => 'sales_report',
                'group_by' => 'monthly',
                'format' => 'csv',
                'summary' => [
                    'total_orders' => 120,
                    'gross_sales' => 185000,
                    'delivered_sales' => 145000,
                    'shipping_total' => 9500,
                    'cod_total' => 0,
                    'unique_customers' => 95,
                ],
            ],
            [
                'title' => 'Campaign Performance Report',
                'report_type' => 'campaign_report',
                'group_by' => 'campaign',
                'format' => 'html',
                'summary' => [
                    'total_campaigns' => 5,
                    'total_orders' => 75,
                    'gross_sales' => 120000,
                    'best_campaign' => 'Shanto Gift Shop BD',
                ],
            ],
            [
                'title' => 'Product Sales Report',
                'report_type' => 'product_report',
                'group_by' => 'product',
                'format' => 'csv',
                'summary' => [
                    'total_products' => 15,
                    'total_quantity' => 210,
                    'total_sales' => 155000,
                    'top_product' => 'Premium Honey',
                ],
            ],
            [
                'title' => 'Employee Order Assignment Report',
                'report_type' => 'employee_order_report',
                'group_by' => 'employee',
                'format' => 'html',
                'summary' => [
                    'total_employees' => 4,
                    'assigned_orders' => 100,
                    'unassigned_orders' => 20,
                ],
            ],
            [
                'title' => 'Fake Order Report',
                'report_type' => 'fake_order_report',
                'group_by' => 'status',
                'format' => 'html',
                'summary' => [
                    'total_fake_orders' => 8,
                    'fake_rate' => '6.5%',
                ],
            ],
            [
                'title' => 'Payment Report',
                'report_type' => 'payment_report',
                'group_by' => 'payment_status',
                'format' => 'csv',
                'summary' => [
                    'cod_pending' => 55,
                    'paid' => 30,
                    'failed' => 5,
                    'refunded' => 2,
                ],
            ],
            [
                'title' => 'Delivery Area Report',
                'report_type' => 'delivery_report',
                'group_by' => 'delivery_area',
                'format' => 'html',
                'summary' => [
                    'inside_dhaka_orders' => 60,
                    'outside_dhaka_orders' => 45,
                ],
            ],
            [
                'title' => 'Tracking Pixel Report',
                'report_type' => 'tracking_pixel_report',
                'group_by' => null,
                'format' => 'html',
                'summary' => [
                    'total_pixels' => 4,
                    'active_pixels' => 3,
                    'inactive_pixels' => 1,
                ],
            ],
        ];

        foreach ($reports as $index => $reportData) {
            $fileData = [
                'file_name' => null,
                'file_path' => null,
                'file_disk' => 'public',
                'mime_type' => null,
                'file_size' => null,
            ];

            if ($reportData['format'] === 'csv') {
                $fileData = $this->createDummyCsvFile($reportData['report_type']);
            }

            ReportExport::create([
                'report_uid' => $this->generateReportUid(),

                'title' => $reportData['title'],
                'report_type' => $reportData['report_type'],

                'date_from' => now()->subDays(30)->toDateString(),
                'date_to' => now()->toDateString(),

                'group_by' => $reportData['group_by'],
                'format' => $reportData['format'],

                'filters' => [
                    'campaign_id' => null,
                    'product_id' => null,
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

                'status' => 'completed',
                'error_message' => null,

                'generated_by' => $generatedBy,
                'generated_at' => now()->subDays($index),

                'created_at' => now()->subDays($index),
                'updated_at' => now()->subDays($index),
            ]);
        }

        ReportExport::create([
            'report_uid' => $this->generateReportUid(),

            'title' => 'Failed PDF Sales Report',
            'report_type' => 'sales_report',

            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),

            'group_by' => 'daily',
            'format' => 'pdf',

            'filters' => [
                'order_status' => 'delivered',
            ],

            'columns' => [
                'invoice_id',
                'customer_name',
                'total_amount',
            ],

            'summary' => [
                'total_orders' => 0,
                'gross_sales' => 0,
            ],

            'file_name' => null,
            'file_path' => null,
            'file_disk' => 'public',
            'mime_type' => null,
            'file_size' => null,

            'status' => 'failed',
            'error_message' => 'PDF package is not configured yet.',

            'generated_by' => $generatedBy,
            'generated_at' => now(),

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createDummyCsvFile(string $type): array
    {
        $fileName = 'seed-' . $type . '-' . now()->format('YmdHis') . '-' . Str::random(5) . '.csv';
        $filePath = 'reports/' . $fileName;

        $csvContent = "label,total_orders,total_sales\n";
        $csvContent .= "Day 1,20,25000\n";
        $csvContent .= "Day 2,35,42000\n";
        $csvContent .= "Day 3,40,53000\n";

        Storage::disk('public')->put($filePath, $csvContent);

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_disk' => 'public',
            'mime_type' => 'text/csv',
            'file_size' => Storage::disk('public')->size($filePath),
        ];
    }

    private function generateReportUid(): string
    {
        do {
            $uid = 'RPT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
        } while (ReportExport::where('report_uid', $uid)->exists());

        return $uid;
    }
}