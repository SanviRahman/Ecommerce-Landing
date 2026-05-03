@extends('adminlte::page')

@section('title', $title ?? 'Order Invoice')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <h1 class="mb-0">Invoice</h1>

        <div>
            <a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>

            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="fa fa-print mr-1"></i> Print
            </button>
        </div>
    </div>
@endsection

@section('content')

    <div class="card shadow-sm border-0" id="invoiceArea">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0 font-weight-bold">Invoice</h4>
                <small class="text-muted">#{{ $order->invoice_id }}</small>
            </div>

            <div class="text-right">
                <strong>Status:</strong>
                {{ ucfirst(str_replace('_', ' ', $order->order_status)) }} <br>

                <strong>Payment:</strong>
                {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
            </div>
        </div>

        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="font-weight-bold">Customer</h5>
                    <p class="mb-0">
                        <strong>Name:</strong> {{ $order->customer_name }} <br>
                        <strong>Phone:</strong> {{ $order->phone }} <br>
                        <strong>Address:</strong> {{ $order->address }} <br>
                        <strong>Area:</strong> {{ $order->delivery_area ?? '-' }}
                    </p>
                </div>

                <div class="col-md-6 text-md-right">
                    <h5 class="font-weight-bold">Order</h5>
                    <p class="mb-0">
                        <strong>Invoice:</strong> #{{ $order->invoice_id }} <br>
                        <strong>Date:</strong> {{ $order->created_at ? $order->created_at->format('d M, Y h:i A') : '-' }} <br>
                        <strong>Employee:</strong> {{ $order->assignedEmployee->name ?? 'Unassigned' }} <br>
                        <strong>Payment Method:</strong> {{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}
                    </p>
                </div>
            </div>

            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Product</th>
                    <th width="15%" class="text-center">Qty</th>
                    <th width="20%" class="text-right">Unit Price</th>
                    <th width="20%" class="text-right">Total</th>
                </tr>
                </thead>

                <tbody>
                @forelse($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format($item->unit_price ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($item->total_price ?? 0, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            No order items found.
                        </td>
                    </tr>
                @endforelse
                </tbody>

                <tfoot>
                <tr>
                    <th colspan="3" class="text-right">Sub Total</th>
                    <th class="text-right">{{ number_format($order->sub_total ?? 0, 2) }}</th>
                </tr>

                <tr>
                    <th colspan="3" class="text-right">Shipping Charge</th>
                    <th class="text-right">{{ number_format($order->shipping_charge ?? 0, 2) }}</th>
                </tr>

                <tr>
                    <th colspan="3" class="text-right">COD Charge</th>
                    <th class="text-right">{{ number_format($order->cod_charge ?? 0, 2) }}</th>
                </tr>

                <tr>
                    <th colspan="3" class="text-right">Total Amount</th>
                    <th class="text-right">{{ number_format($order->total_amount ?? 0, 2) }}</th>
                </tr>
                </tfoot>
            </table>

            <div class="mt-4">
                <p class="mb-1">
                    <strong>Customer Note:</strong> {{ $order->customer_note ?? '-' }}
                </p>

                <p class="mb-0">
                    <strong>Admin Note:</strong> {{ $order->admin_note ?? '-' }}
                </p>
            </div>
        </div>
    </div>

@endsection

@section('footer')
    <strong>
        © Copyright 2026 All rights reserved |
        This website developed by
        <a href="https://sfashanto.netlify.app/" target="_blank">SFA Shanto</a>
    </strong>
@endsection

@section('css')
    <style>
        @media print {
            body * {
                visibility: hidden;
            }

            #invoiceArea,
            #invoiceArea * {
                visibility: visible;
            }

            #invoiceArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none !important;
                border: none !important;
            }

            .main-footer,
            .main-header,
            .main-sidebar,
            .content-header,
            .btn {
                display: none !important;
            }
        }
    </style>
@endsection