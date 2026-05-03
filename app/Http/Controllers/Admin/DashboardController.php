<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $orderQuery = Order::query();

        if ($user->isEmployee()) {
            $orderQuery->where('assigned_employee_id', $user->id);
        }

        return view('admin.dashboard.index', [
            'totalOrders' => (clone $orderQuery)->count(),
            'pendingOrders' => (clone $orderQuery)->where('order_status', 'Pending')->count(),
            'confirmedOrders' => (clone $orderQuery)->where('order_status', 'Confirmed')->count(),
            'processingOrders' => (clone $orderQuery)->where('order_status', 'Processing')->count(),
            'deliveredOrders' => (clone $orderQuery)->where('order_status', 'Delivered')->count(),
            'cancelledOrders' => (clone $orderQuery)->where('order_status', 'Cancelled')->count(),

            // Product count both admin/employee দেখতে পারবে
            'totalProducts' => Product::count(),

            'isEmployee' => $user->isEmployee(),
        ]);
    }
}