@extends('adminlte::page')

@section('title', $title ?? 'Generate Report')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Generate Report' }}</h1>

        @if(isset($breadcrumb))
            <ol class="breadcrumb mt-2 mb-0 bg-transparent p-0">
                @foreach($breadcrumb as $item)
                    <li class="breadcrumb-item">
                        <a href="{{ $item['url'] }}">{{ $item['text'] }}</a>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    <a href="{{ route('admin.reports.index') }}" class="btn btn-primary rounded-pill px-4">
        <i class="fas fa-list mr-1"></i> Manage
    </a>
</div>
@endsection

@section('content')

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{!! $error !!}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card border-0 shadow-sm" style="border-radius: 12px;">
    <div class="card-header bg-white">
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-chart-line text-primary mr-1"></i>
            Report Information
        </h5>
        <small class="text-muted">
            Select report type, date range, filters and export format.
        </small>
    </div>

    <div class="card-body">
        <form action="{{ route('admin.reports.store') }}" method="POST">
            @csrf

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Report Title</label>
                        <input type="text"
                               name="title"
                               value="{{ old('title') }}"
                               class="form-control"
                               placeholder="Example: Monthly Sales Report">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Report Type <span class="text-danger">*</span></label>
                        <select name="report_type" class="form-control" required>
                            <option value="">Select Report Type</option>
                            @foreach ($reportTypes as $key => $label)
                                <option value="{{ $key }}" @selected(old('report_type') === $key)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date From</label>
                        <input type="date"
                               name="date_from"
                               value="{{ old('date_from') }}"
                               class="form-control">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date"
                               name="date_to"
                               value="{{ old('date_to') }}"
                               class="form-control">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Group By</label>
                        <select name="group_by" class="form-control">
                            <option value="">Default</option>
                            @foreach ($groupByOptions as $key => $label)
                                <option value="{{ $key }}" @selected(old('group_by') === $key)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Format <span class="text-danger">*</span></label>
                        <select name="format" class="form-control" required>
                            @foreach ($formats as $key => $label)
                                <option value="{{ $key }}" @selected(old('format', 'html') === $key)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">
                            CSV file generate হবে। PDF/Excel history save হবে; package setup করলে file generate করা যাবে।
                        </small>
                    </div>
                </div>
            </div>

            <div class="card bg-light border-0 mt-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0 font-weight-bold">
                        <i class="fas fa-filter mr-1"></i> Filters
                    </h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Campaign</label>
                                <select name="filters[campaign_id]" class="form-control">
                                    <option value="">All Campaigns</option>
                                    @foreach ($campaigns as $campaign)
                                        <option value="{{ $campaign->id }}"
                                            @selected((string) old('filters.campaign_id') === (string) $campaign->id)>
                                            {{ $campaign->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Product</label>
                                <select name="filters[product_id]" class="form-control">
                                    <option value="">All Products</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}"
                                            @selected((string) old('filters.product_id') === (string) $product->id)>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Employee</label>
                                <select name="filters[employee_id]" class="form-control">
                                    <option value="">All Employees</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}"
                                            @selected((string) old('filters.employee_id') === (string) $employee->id)>
                                            {{ $employee->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    orders table-এ assigned_employee_id থাকলে কাজ করবে।
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Order Status</label>
                                <select name="filters[order_status]" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="pending" @selected(old('filters.order_status') === 'pending')>Pending</option>
                                    <option value="confirmed" @selected(old('filters.order_status') === 'confirmed')>Confirmed</option>
                                    <option value="processing" @selected(old('filters.order_status') === 'processing')>Processing</option>
                                    <option value="shipped" @selected(old('filters.order_status') === 'shipped')>Shipped</option>
                                    <option value="delivered" @selected(old('filters.order_status') === 'delivered')>Delivered</option>
                                    <option value="cancelled" @selected(old('filters.order_status') === 'cancelled')>Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Payment Status</label>
                                <select name="filters[payment_status]" class="form-control">
                                    <option value="">All Payment</option>
                                    <option value="cod_pending" @selected(old('filters.payment_status') === 'cod_pending')>COD Pending</option>
                                    <option value="paid" @selected(old('filters.payment_status') === 'paid')>Paid</option>
                                    <option value="failed" @selected(old('filters.payment_status') === 'failed')>Failed</option>
                                    <option value="refunded" @selected(old('filters.payment_status') === 'refunded')>Refunded</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Delivery Area</label>
                                <select name="filters[delivery_area]" class="form-control">
                                    <option value="">All Area</option>
                                    <option value="inside_dhaka" @selected(old('filters.delivery_area') === 'inside_dhaka')>Inside Dhaka</option>
                                    <option value="outside_dhaka" @selected(old('filters.delivery_area') === 'outside_dhaka')>Outside Dhaka</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Fake Order</label>
                                <select name="filters[is_fake]" class="form-control">
                                    <option value="">All</option>
                                    <option value="1" @selected(old('filters.is_fake') === '1')>Fake Only</option>
                                    <option value="0" @selected(old('filters.is_fake') === '0')>Real Only</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-white border mt-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0 font-weight-bold">
                        <i class="fas fa-columns mr-1"></i> Optional Columns
                    </h6>
                </div>

                <div class="card-body">
                    @php
                        $columns = [
                            'invoice_id' => 'Invoice ID',
                            'customer_name' => 'Customer Name',
                            'phone' => 'Phone',
                            'address' => 'Address',
                            'delivery_area' => 'Delivery Area',
                            'payment_status' => 'Payment Status',
                            'order_status' => 'Order Status',
                            'total_amount' => 'Total Amount',
                            'created_at' => 'Created At',
                        ];
                    @endphp

                    <div class="row">
                        @foreach ($columns as $key => $label)
                            <div class="col-md-3 col-sm-6">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox"
                                           name="columns[]"
                                           value="{{ $key }}"
                                           class="custom-control-input"
                                           id="column_{{ $key }}"
                                           @checked(in_array($key, old('columns', [])))>
                                    <label class="custom-control-label" for="column_{{ $key }}">
                                        {{ $label }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <small class="text-muted">
                        Future PDF/Excel detailed report-এর জন্য column preference save হবে।
                    </small>
                </div>
            </div>

            <div class="border-top pt-3 mt-3">
                <button type="submit" class="btn btn-success px-4">
                    <i class="fas fa-save mr-1"></i> Generate Report
                </button>

                <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary px-4">
                    <i class="fas fa-times mr-1"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

@endsection

@section('css')
<style>
.breadcrumb-item+.breadcrumb-item::before {
    content: ">";
}
</style>
@endsection