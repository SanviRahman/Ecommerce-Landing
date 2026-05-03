<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;

class OrderAssignmentService
{
    /**
     * Assign order to an active employee.
     *
     * Logic:
     * - Only active employee users will receive orders.
     * - Employee with lowest active order count gets priority.
     * - If multiple employees have same count, one will be selected randomly.
     */
    public function assign(Order $order): ?User
    {
        if ($order->assigned_employee_id) {
            return $order->assignedEmployee;
        }

        $employees = User::activeEmployees()
            ->withCount([
                'activeAssignedOrders as active_orders_count',
            ])
            ->get();

        if ($employees->isEmpty()) {
            return null;
        }

        $minimumOrderCount = $employees->min('active_orders_count');

        $employee = $employees
            ->where('active_orders_count', $minimumOrderCount)
            ->random();

        $order->forceFill([
            'assigned_employee_id' => $employee->id,
        ])->saveQuietly();

        return $employee;
    }
}