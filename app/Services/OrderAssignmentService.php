<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderAssignmentService
{
    /**
     * Assign order to an active employee.
     *
     * Logic:
     * - Only active employees receive orders.
     * - Employee with the lowest active order count gets priority.
     * - If multiple employees have same count, one is selected randomly.
     * - Transaction + lock reduces wrong assignment during simultaneous orders.
     */
    public function assign(Order $order): ?User
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder) {
                return null;
            }

            if ($lockedOrder->assigned_employee_id) {
                return $lockedOrder->assignedEmployee;
            }

            $employees = User::activeEmployees()
                ->withCount([
                    'activeAssignedOrders as active_orders_count',
                ])
                ->lockForUpdate()
                ->get();

            if ($employees->isEmpty()) {
                return null;
            }

            $minimumOrderCount = $employees->min('active_orders_count');

            $employee = $employees
                ->where('active_orders_count', $minimumOrderCount)
                ->shuffle()
                ->first();

            if (! $employee) {
                return null;
            }

            $lockedOrder->forceFill([
                'assigned_employee_id' => $employee->id,
            ])->saveQuietly();

            $order->forceFill([
                'assigned_employee_id' => $employee->id,
            ]);

            return $employee;
        });
    }
}