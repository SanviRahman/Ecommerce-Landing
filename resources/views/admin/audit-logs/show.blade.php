@extends('adminlte::page')

@section('title', 'Order Audit #'.$entry->id)

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap p56-page-heading">
    <div>
        <h1 class="mb-1">Audit Record #{{ $entry->id }}</h1>
        <p class="text-muted mb-0">Original Order #{{ $entry->original_order_id }} &middot; Permanent deletion evidence</p>
    </div>
    <a href="{{ route('admin.reports.audit-logs.index') }}" class="btn btn-outline-secondary mt-2 mt-md-0"><i class="fas fa-arrow-left mr-1" aria-hidden="true"></i> Back to Audit Logs</a>
</div>
@stop

@section('content')
@php
    $detail = $snapshot ?? [];
    $items = is_array($detail['items'] ?? null) ? $detail['items'] : [];
    $movements = is_array($detail['movement_ids'] ?? null) ? $detail['movement_ids'] : [];
    $statuses = is_array($detail['status_history'] ?? null) ? $detail['status_history'] : [];
    $requests = is_array($detail['checkout_request_ids'] ?? null) ? $detail['checkout_request_ids'] : [];
@endphp
<main class="p56-audit" aria-label="Immutable order purge audit details">
    <div class="p56-hero d-flex align-items-center justify-content-between flex-wrap mb-4">
        <div class="d-flex align-items-center"><span class="p56-hero-icon"><i class="fas fa-fingerprint" aria-hidden="true"></i></span><div>
            <span class="p56-eyebrow">AUDIT EVIDENCE</span>
            <h2 class="h4 mt-1 mb-1 font-weight-bold">Purge #{{ $entry->id }}</h2>
            <p class="mb-0">This is a read-only snapshot. The deleted order cannot be restored from this page.</p>
        </div></div>
        <span class="p56-readonly mt-3 mt-md-0"><i class="fas fa-lock mr-1" aria-hidden="true"></i> Retained evidence</span>
    </div>

    @if($snapshot === null)
        <div class="alert alert-warning" role="alert"><i class="fas fa-exclamation-triangle mr-1" aria-hidden="true"></i> Stored audit snapshot is malformed. Do not modify or delete the audit row; contact your developer.</div>
    @endif

    <div class="row">
        <div class="col-lg-4 col-md-6 mb-3"><div class="p56-detail-stat"><small>Original Order</small><strong>#{{ $entry->original_order_id }}</strong><span>{{ $entry->invoice_id ?: 'No invoice number' }}</span></div></div>
        <div class="col-lg-4 col-md-6 mb-3"><div class="p56-detail-stat"><small>Deleted By</small><strong>{{ $entry->admin_name ?: 'Former admin' }}</strong><span>Admin ID: #{{ $entry->admin_id }}</span></div></div>
        <div class="col-lg-4 col-md-12 mb-3"><div class="p56-detail-stat"><small>Archived At</small><strong>{{ \Carbon\Carbon::parse($entry->created_at)->format('d M Y') }}</strong><span>{{ \Carbon\Carbon::parse($entry->created_at)->format('h:i A') }}</span></div></div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card p56-card h-100"><div class="card-header p56-card-header"><h3 class="card-title mb-0"><i class="fas fa-receipt text-success mr-2" aria-hidden="true"></i>Financial Snapshot</h3></div>
                <div class="card-body">
                    <dl class="p56-key-values">
                        <div><dt>Order Source</dt><dd>{{ $detail['created_via'] ?? '—' }}</dd></div>
                        <div><dt>Campaign ID</dt><dd>{{ $detail['campaign_id'] ?? '—' }}</dd></div>
                        <div><dt>Order Status</dt><dd>{{ $detail['order_status'] ?? '—' }}</dd></div>
                        <div><dt>Payment Status</dt><dd>{{ $detail['payment_status'] ?? '—' }}</dd></div>
                        <div><dt>Original Order Date</dt><dd>{{ $detail['created_at'] ?? '—' }}</dd></div>
                        <div><dt>Subtotal (BDT)</dt><dd>{{ $detail['sub_total'] ?? '—' }}</dd></div>
                        <div><dt>Shipping (BDT)</dt><dd>{{ $detail['shipping_charge'] ?? '—' }}</dd></div>
                        <div class="p56-grand-total"><dt>Total (BDT)</dt><dd>{{ $detail['total_amount'] ?? '—' }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-7 mb-4">
            <div class="card p56-card h-100"><div class="card-header p56-card-header"><h3 class="card-title mb-0"><i class="fas fa-boxes text-success mr-2" aria-hidden="true"></i>Inventory & Checkout Evidence</h3></div>
                <div class="card-body">
                    <dl class="p56-key-values">
                        <div><dt>Inventory System</dt><dd><span class="badge {{ (int) $entry->inventory_version === 1 ? 'badge-success' : 'badge-secondary' }}">{{ (int) $entry->inventory_version === 1 ? 'Managed' : 'Legacy' }}</span></dd></div>
                        <div><dt>Item Records</dt><dd>{{ count($items) }}</dd></div>
                        <div><dt>Inventory Movement IDs</dt><dd>{{ $movements === [] ? 'None' : implode(', ', array_map('strval', $movements)) }}</dd></div>
                        <div><dt>Checkout Request IDs</dt><dd>{{ $requests === [] ? 'None' : implode(', ', array_map('strval', $requests)) }}</dd></div>
                    </dl>
                    <div class="p56-safety"><i class="fas fa-info-circle mr-1" aria-hidden="true"></i> Audit retention does not restore stock or recreate a deleted order. Inventory ledger entries are retained by the P55 purge service.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card p56-card mb-4"><div class="card-header p56-card-header"><h3 class="card-title mb-0"><i class="fas fa-box mr-2 text-success" aria-hidden="true"></i>Items ({{ count($items) }})</h3></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table p56-table mb-0"><thead><tr><th scope="col">Item ID</th><th scope="col">Product ID</th><th scope="col">SKU</th><th scope="col">Qty</th><th scope="col">Unit Price</th><th scope="col">Line Total</th><th scope="col">Stock Allocated</th></tr></thead><tbody>
            @forelse($items as $item)
                @if(is_array($item))
                <tr><td>#{{ $item['id'] ?? '—' }}</td><td>{{ $item['product_id'] ?? '—' }}</td><td><span class="p56-code">{{ $item['sku'] ?? 'Default' }}</span></td><td>{{ $item['quantity'] ?? '—' }}</td><td>{{ $item['unit_price'] ?? '—' }}</td><td>{{ $item['total_price'] ?? '—' }}</td><td>{{ $item['allocated_quantity'] ?? 0 }}</td></tr>
                @endif
            @empty
                <tr><td colspan="7" class="text-muted text-center py-4">No line items were recorded in the snapshot.</td></tr>
            @endforelse
        </tbody></table></div></div>
    </div>

    <div class="card p56-card mb-4"><div class="card-header p56-card-header"><h3 class="card-title mb-0"><i class="fas fa-stream text-success mr-2" aria-hidden="true"></i>Status History</h3></div>
        <div class="card-body"><ol class="p56-timeline">
            @forelse($statuses as $status)
                @if(is_array($status))
                    <li><strong>{{ $status['status'] ?? 'Unknown status' }}</strong><span>{{ $status['at'] ?? 'Time unavailable' }}</span></li>
                @endif
            @empty
                <li>No recorded status transitions.</li>
            @endforelse
        </ol></div>
    </div>
    <div class="p56-safety mb-4"><i class="fas fa-shield-alt mr-1" aria-hidden="true"></i> This page has no editing, deleting, or restoration actions. Financial evidence is preserved by the P55 audit service.</div>
</main>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/p56-audit-logs.css') }}">
@stop
