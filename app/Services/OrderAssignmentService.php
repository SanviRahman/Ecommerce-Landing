<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderAssignmentService
{
    /**
     * Assign single order to an active employee.
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

            $employee = $this->findAvailableEmployee();

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

    /**
     * Assign all unassigned active orders.
     * This method is called from OrderController::assignUnassignedOrders().
     */
    public function assignUnassigned(): int
    {
        $assignedCount = 0;

        $orders = Order::query()
            ->whereNull('assigned_employee_id')
            ->whereNotIn('order_status', [
                Order::STATUS_DELIVERED,
                Order::STATUS_CANCELLED,
                Order::STATUS_FAKE,
            ])
            ->oldest()
            ->get();

        foreach ($orders as $order) {
            $employee = $this->assign($order);

            if ($employee) {
                $assignedCount++;
            }
        }

        return $assignedCount;
    }

    /**
     * Pick active employee with lowest active assigned order count.
     */
    private function findAvailableEmployee(): ?User
    {
        $employees = User::activeEmployees()
            ->withCount([
                'activeAssignedOrders as active_orders_count',
            ])
            ->get();

        if ($employees->isEmpty()) {
            return null;
        }

        $minimumOrderCount = $employees->min('active_orders_count');

        return $employees
            ->where('active_orders_count', $minimumOrderCount)
            ->shuffle()
            ->first();
    }
}