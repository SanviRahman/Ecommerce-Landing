@extends('adminlte::page')

@section('title', $title ?? 'Edit Report')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Edit Report' }}</h1>

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

    <div>
        <a href="{{ route('admin.reports.show', $report->id) }}" class="btn btn-dark rounded-pill px-4">
            <i class="fas fa-eye mr-1"></i> Details
        </a>

        <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary rounded-pill px-4">
            <i class="fas fa-list mr-1"></i> Manage
        </a>
    </div>
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
            <i class="fas fa-edit text-primary mr-1"></i>
            Edit Report Information
        </h5>
        <small class="text-muted">
            Check regenerate file if you want to create new CSV/PDF file after updating this report.
        </small>
    </div>

    <div class="card-body">
        <form action="{{ route('admin.reports.update', $report->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="alert alert-info">
                <strong>Note:</strong>
                CSV/PDF file will regenerate only if you check <strong>Regenerate File</strong>.
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Report UID</label>
                        <input type="text" value="{{ $report->report_uid }}" class="form-control" readonly>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control" required>
                            <option value="pending" @selected(old('status', $report->status) === 'pending')>Pending</option>
                            <option value="processing" @selected(old('status', $report->status) === 'processing')>Processing</option>
                            <option value="completed" @selected(old('status', $report->status) === 'completed')>Completed</option>
                            <option value="failed" @selected(old('status', $report->status) === 'failed')>Failed</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Report Title <span class="text-danger">*</span></label>
                <input type="text"
                       name="title"
                       value="{{ old('title', $report->title) }}"
                       class="form-control @error('title') is-invalid @enderror"
                       required>

                @error('title')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Report Type <span class="text-danger">*</span></label>
                        <select name="report_type" class="form-control" required>
                            @foreach ($reportTypes as $key => $label)
                                <option value="{{ $key }}" @selected(old('report_type', $report->report_type) === $key)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Format <span class="text-danger">*</span></label>
                        <select name="format" class="form-control" required>
                            @foreach ($formats as $key => $label)
                                <option value="{{ $key }}" @selected(old('format', $report->format) === $key)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">
                            CSV and PDF can generate downloadable files.
                        </small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Date From</label>
                        <input type="date"
                               name="date_from"
                               value="{{ old('date_from', optional($report->date_from)->format('Y-m-d')) }}"
                               class="form-control">
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date"
                               name="date_to"
                               value="{{ old('date_to', optional($report->date_to)->format('Y-m-d')) }}"
                               class="form-control">
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Group By</label>
                        <select name="group_by" class="form-control">
                            <option value="">Default</option>
                            @foreach ($groupByOptions as $key => $label)
                                <option value="{{ $key }}" @selected(old('group_by', $report->group_by) === $key)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            @php
                $filters = old('filters', $report->filters ?? []);
            @endphp

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
                                            @selected((string) ($filters['campaign_id'] ?? '') === (string) $campaign->id)>
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
                                            @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>
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
                                            @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>
                                            {{ $employee->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Order Status</label>
                                <select name="filters[order_status]" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="pending" @selected(($filters['order_status'] ?? '') === 'pending')>Pending</option>
                                    <option value="confirmed" @selected(($filters['order_status'] ?? '') === 'confirmed')>Confirmed</option>
                                    <option value="processing" @selected(($filters['order_status'] ?? '') === 'processing')>Processing</option>
                                    <option value="shipped" @selected(($filters['order_status'] ?? '') === 'shipped')>Shipped</option>
                                    <option value="delivered" @selected(($filters['order_status'] ?? '') === 'delivered')>Delivered</option>
                                    <option value="cancelled" @selected(($filters['order_status'] ?? '') === 'cancelled')>Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Payment Status</label>
                                <select name="filters[payment_status]" class="form-control">
                                    <option value="">All Payment</option>
                                    <option value="cod_pending" @selected(($filters['payment_status'] ?? '') === 'cod_pending')>COD Pending</option>
                                    <option value="paid" @selected(($filters['payment_status'] ?? '') === 'paid')>Paid</option>
                                    <option value="failed" @selected(($filters['payment_status'] ?? '') === 'failed')>Failed</option>
                                    <option value="refunded" @selected(($filters['payment_status'] ?? '') === 'refunded')>Refunded</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Delivery Area</label>
                                <select name="filters[delivery_area]" class="form-control">
                                    <option value="">All Area</option>
                                    <option value="inside_dhaka" @selected(($filters['delivery_area'] ?? '') === 'inside_dhaka')>Inside Dhaka</option>
                                    <option value="outside_dhaka" @selected(($filters['delivery_area'] ?? '') === 'outside_dhaka')>Outside Dhaka</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Fake Order</label>
                                <select name="filters[is_fake]" class="form-control">
                                    <option value="">All</option>
                                    <option value="1" @selected((string) ($filters['is_fake'] ?? '') === '1')>Fake Only</option>
                                    <option value="0" @selected((string) ($filters['is_fake'] ?? '') === '0')>Real Only</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-white border mt-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0 font-weight-bold">
                        <i class="fas fa-columns mr-1"></i> Columns
                    </h6>
                </div>

                <div class="card-body">
                    @php
                        $selectedColumns = old('columns', $report->columns ?? []);
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
                                           @checked(in_array($key, $selectedColumns))>
                                    <label class="custom-control-label" for="column_{{ $key }}">
                                        {{ $label }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="form-group mt-3">
                <label>Error Message</label>
                <textarea name="error_message"
                          class="form-control"
                          rows="3"
                          placeholder="Only use this if report failed">{{ old('error_message', $report->error_message) }}</textarea>
            </div>

            <div class="custom-control custom-switch mb-3">
                <input type="checkbox"
                       name="regenerate_file"
                       value="1"
                       class="custom-control-input"
                       id="regenerate_file">
                <label class="custom-control-label font-weight-bold" for="regenerate_file">
                    Regenerate File
                </label>
                <small class="form-text text-muted">
                    Check this if you want to regenerate downloadable CSV/PDF file.
                </small>
            </div>

            <div class="border-top pt-3 mt-3">
                <button type="submit" class="btn btn-success px-4">
                    <i class="fas fa-save mr-1"></i> Update Report
                </button>

                <a href="{{ route('admin.reports.show', $report->id) }}" class="btn btn-dark px-4">
                    <i class="fas fa-eye mr-1"></i> Details
                </a>

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