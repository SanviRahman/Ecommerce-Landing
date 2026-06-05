<?php

namespace App\Services;

use App\Models\EmployeeAssignmentCursor;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderAssignmentService
{
    private const CURSOR_KEY = 'order_employee_round_robin';

    /**
     * Assign single order to the next active employee using fixed round-robin sequence.
     * Example with 3 employees: 1st -> employee 1, 2nd -> employee 2,
     * 3rd -> employee 3, 4th -> employee 1, and so on.
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

            if (in_array($lockedOrder->order_status, [
                Order::STATUS_DELIVERED,
                Order::STATUS_CANCELLED,
                Order::STATUS_FAKE,
            ], true)) {
                return null;
            }

            $employee = $this->nextEmployee();

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
     * Assign all unassigned active orders using the same round-robin sequence.
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
            ->oldest('id')
            ->get();

        foreach ($orders as $order) {
            if ($this->assign($order)) {
                $assignedCount++;
            }
        }

        return $assignedCount;
    }

    /**
     * Return next active employee by ID order and update cursor safely.
     */
    private function nextEmployee(): ?User
    {
        $employees = User::activeEmployees()
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        if ($employees->isEmpty()) {
            return null;
        }

        $cursor = EmployeeAssignmentCursor::query()
            ->where('key', self::CURSOR_KEY)
            ->lockForUpdate()
            ->first();

        if (! $cursor) {
            $cursor = EmployeeAssignmentCursor::create([
                'key' => self::CURSOR_KEY,
                'last_employee_id' => null,
            ]);

            $cursor = EmployeeAssignmentCursor::query()
                ->whereKey($cursor->id)
                ->lockForUpdate()
                ->first();
        }

        $employeeIds = $employees->pluck('id')->values();

        if (! $cursor->last_employee_id) {
            $nextEmployee = $employees->first();
        } else {
            $lastIndex = $employeeIds->search((int) $cursor->last_employee_id);

            if ($lastIndex === false) {
                $nextEmployee = $employees->first();
            } else {
                $nextIndex = ($lastIndex + 1) % $employees->count();
                $nextEmployee = $employees->values()->get($nextIndex);
            }
        }

        $cursor->forceFill([
            'last_employee_id' => $nextEmployee->id,
        ])->save();

        return $nextEmployee;
    }
}
