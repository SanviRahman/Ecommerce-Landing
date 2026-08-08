<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreExternalOrderRequest;
use App\Models\ExternalWebsite;
use App\Services\ExternalOrderImportService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ExternalOrderController extends Controller
{
    public function status(ExternalWebsite $externalWebsite): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Bidirectional order API credentials are valid.',
            'data' => [
                'website' => $externalWebsite->name,
                'slug' => $externalWebsite->slug,
                'receiver' => config('app.name'),
                'receive_orders' => (bool) $externalWebsite->receive_orders,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    public function store(
        StoreExternalOrderRequest $request,
        ExternalWebsite $externalWebsite,
        ExternalOrderImportService $externalOrderImportService
    ): JsonResponse {
        try {
            $result = $externalOrderImportService->import(
                $externalWebsite,
                $request->validated(),
                $request->ip()
            );

            $order = $result['order'];
            $created = (bool) $result['created'];

            return response()->json([
                'status' => true,
                'created' => $created,
                'message' => $created
                    ? 'Order imported successfully.'
                    : 'This external order was already imported.',
                'data' => [
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice_id,
                    'external_order_id' => $order->external_order_id,
                    'sync_uuid' => $order->sync_uuid,
                    'order_status' => $order->order_status,
                    'assigned_employee_id' => $order->assigned_employee_id,
                ],
            ], $created ? 201 : 200);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Order import failed. Please check the payload and server log.',
            ], 500);
        }
    }
}
