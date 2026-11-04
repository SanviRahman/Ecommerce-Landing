@extends('adminlte::page')
@section('title', 'Stock Management')

@section('content_header')
<h1><i class="fas fa-boxes mr-2 text-success" aria-hidden="true"></i> Stock Management</h1>
<p class="text-muted mb-0">Product-wise stock, in one place. Existing Campaign and Storefront order confirmation rules remain unchanged.</p>
@stop

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Stock was not changed.</strong>
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="card shadow-sm stock-management-card">
    <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap">
        <div>
            <h2 class="h5 mb-1">Total {{ $variants->total() }} Product Stock</h2>
            <span class="text-muted small">Shared inventory &middot; one record per managed product</span>
        </div>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#stockPurchaseModal"
            @disabled($purchaseVariants->isEmpty())>
            <i class="fas fa-plus-circle mr-1" aria-hidden="true"></i> Add New Stock
        </button>
    </div>
    <div class="card-body pb-0">
        <form method="GET" action="{{ route('admin.stock.index') }}" class="form-row align-items-end stock-toolbar">
            <div class="col-md-6 col-lg-4 mb-3">
                <label for="stockSearch" class="small font-weight-bold">Search products</label>
                <input type="search" id="stockSearch" name="q" class="form-control"
                    placeholder="Name or product code" maxlength="80" value="{{ $query }}">
            </div>
            <div class="col-auto mb-3">
                <button type="submit" class="btn btn-outline-primary">Search</button>
            </div>
            @if($query !== '')
                <div class="col-auto mb-3"><a href="{{ route('admin.stock.index') }}" class="btn btn-outline-secondary">Clear</a></div>
            @endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover stock-table mb-0">
            <thead class="thead-light"><tr>
                <th scope="col">Product Code</th>
                <th scope="col">Product Name</th>
                <th scope="col">In a Stock <span title="Opening stock + receipts and positive corrections, minus reversed purchases" aria-label="Total received units"><i class="far fa-question-circle" aria-hidden="true"></i></span></th>
                <th scope="col">Available Stock</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
            </tr></thead>
            <tbody>
                @forelse($variants as $variant)
                    @php
                        $product = $variant->product;
                        $held = (int) ($reserved[$variant->id] ?? 0);
                        $physical = (int) $variant->stock;
                        $receivedCount = max(0, (int) ($received[$variant->id] ?? 0));
                        $available = max(0, $physical - $held);
                        $canAdjust = (int) $product->inventory_version === 1
                            && ! $product->has_variants && $variant->option_signature === 'default';
                        $last = $latestMovements[$variant->id] ?? null;
                        $canDelete = $canAdjust && $last !== null
                            && $last->kind === 'adjustment'
                            && $last->order_item_id === null
                            && (int) $last->quantity_delta > 0
                            && str_starts_with((string) $last->reason, 'Stock Management purchase: ')
                            && (int) $last->stock_after === $physical
                            && $physical - (int) $last->quantity_delta >= $held;
                        $isActive = (bool) $product->status && (bool) $variant->is_active;
                    @endphp
                    <tr>
                        <td class="text-nowrap font-weight-bold">{{ $product->product_code ?: 'Product #' . $product->id }}</td>
                        <td>{{ $product->name }}</td>
                        <td class="stock-number">{{ number_format($receivedCount) }}</td>
                        <td class="stock-number">
                            <strong>{{ number_format($available) }}</strong>
                            @if($held > 0)
                                <div class="small text-muted">{{ number_format($held) }} held &middot; {{ number_format($physical) }} physical</div>
                            @endif
                        </td>
                        <td><span class="badge {{ $isActive ? 'badge-success' : 'badge-secondary' }}">{{ $isActive ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-nowrap stock-actions">
                            @if($canAdjust)
                                <button type="button" class="btn btn-sm btn-primary stock-edit-button"
                                    data-toggle="modal" data-target="#stockEditModal"
                                    data-action="{{ route('admin.stock.exact', $variant->id) }}"
                                    data-product="{{ $product->name }}"
                                    data-code="{{ $product->product_code ?: 'Product #' . $product->id }}"
                                    data-physical="{{ $physical }}"
                                    data-held="{{ $held }}"
                                    data-received="{{ $receivedCount }}">Edit</button>
                            @else
                                <button type="button" class="btn btn-sm btn-secondary" disabled title="This product needs shared-stock setup">Edit</button>
                            @endif
                            @if($canDelete)
                                <form method="POST" class="d-inline" action="{{ route('admin.stock.purchase.reverse', $last->id) }}"
                                    onsubmit="return confirm('Delete only the latest UNUSED stock purchase? The purchase will be reversed and the audit history kept.');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger" title="Reverse latest unused purchase; never deletes the product">Delete</button>
                                </form>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                    title="Only the latest unused purchase can be deleted. Use Edit for a safe stock correction.">Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        No managed product stock found. Create a product and configure its shared stock first.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap align-items-center justify-content-between">
        <small class="text-muted">In a Stock = received inventory. Available = physical stock minus unconfirmed order reservations.</small>
        {{ $variants->links('pagination::bootstrap-4') }}
    </div>
</div>

<div class="alert alert-light border small" role="note">
    <i class="fas fa-shield-alt text-success mr-1" aria-hidden="true"></i>
    <strong>Safe stock changes:</strong> Edit adjusts the physical quantity with an audit reason. Delete reverses only the most recent unused purchase, never the product or order history.
    Order confirmation continues to deduct stock exactly once using the existing inventory ledger.
</div>

@if($legacy->isNotEmpty())
<div class="card border-warning">
    <div class="card-header"><h2 class="h6 mb-0">Legacy Stock (View Only)</h2></div>
    <div class="card-body small text-muted">
        Products below do not yet have audited shared inventory; editing them from this page is blocked.
        <ul class="mt-2 mb-0">@foreach($legacy as $p)
            <li>{{ $p->name }} ({{ $p->product_code ?: 'Product #' . $p->id }}): {{ $p->stock }}</li>
        @endforeach</ul>
    </div>
</div>
@endif

<details class="card stock-history">
    <summary class="card-header font-weight-bold" style="cursor:pointer">Recent Stock Movement History <span class="text-muted small font-weight-normal">(view audit entries)</span></summary>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
            <thead><tr><th>ID</th><th>Product</th><th>Type</th><th>Change</th><th>Before / After</th><th>Reason</th></tr></thead>
            <tbody>@forelse($history as $movement)
                <tr><td>{{ $movement->id }}</td><td>{{ $movement->product_name }}</td><td>{{ $movement->kind }}</td>
                    <td>{{ (int) $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }}</td>
                    <td>{{ $movement->stock_before }} / {{ $movement->stock_after }}</td><td>{{ $movement->reason }}</td></tr>
            @empty<tr><td colspan="6" class="text-center text-muted">No movement history.</td></tr>@endforelse</tbody>
        </table>
    </div>
</details>

{{-- New Purchase: use the existing ledger-backed stock receipt endpoint. --}}
<div class="modal fade" id="stockPurchaseModal" tabindex="-1" role="dialog" aria-labelledby="stockPurchaseTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title h5" id="stockPurchaseTitle">Add New Purchase</h2>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <form method="POST" id="stockPurchaseForm">
            @csrf
            <input type="hidden" name="expected_stock" id="stockPurchaseExpected">
            <div class="modal-body">
                <div class="form-group">
                    <label for="stockPurchaseProduct">Product <span class="text-danger">*</span></label>
                    <select id="stockPurchaseProduct" class="form-control" required>
                        <option value="">Select a product</option>
                        @foreach($purchaseVariants as $item)
                            <option value="{{ $item->id }}" data-action="{{ route('admin.stock.purchase', $item->id) }}" data-stock="{{ (int)$item->stock }}">
                                {{ $item->product->name }} — {{ $item->product->product_code ?: 'Product #' . $item->product_id }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">Choose a product; received stock is added to its shared inventory.</small>
                </div>
                <div class="form-group">
                    <label for="stockPurchaseQuantity">Purchased quantity <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="quantity" id="stockPurchaseQuantity" min="1" max="1000000" step="1" required placeholder="e.g. 20">
                </div>
                <div class="form-group mb-0">
                    <label for="stockPurchaseReference">Supplier / Invoice reference <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="reference" id="stockPurchaseReference" minlength="3" maxlength="120" required placeholder="Supplier name or invoice number">
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" id="stockPurchaseSave" class="btn btn-primary" disabled>Save Purchase</button></div>
        </form>
    </div></div>
</div>

{{-- Edit: displayed modes translate to an exact target; the server rechecks
     current quantity, reservations and ledger under the existing locks. --}}
<div class="modal fade" id="stockEditModal" tabindex="-1" role="dialog" aria-labelledby="stockEditTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title h5" id="stockEditTitle">Edit Product Stock</h2>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <form method="POST" id="stockEditForm">
            @csrf @method('PATCH')
            <input type="hidden" name="expected_stock" id="stockEditExpected">
            <input type="hidden" name="quantity" id="stockEditNewExact">
            <div class="modal-body">
                <dl class="stock-edit-summary mb-3">
                    <div><dt>Product:</dt><dd id="stockEditProduct"></dd></div>
                    <div><dt>Product Code:</dt><dd id="stockEditCode"></dd></div>
                    <div><dt>In a Stock (Total Received):</dt><dd id="stockEditReceived"></dd></div>
                    <div><dt>Current Physical:</dt><dd id="stockEditPhysical"></dd></div>
                    <div><dt>Current Available:</dt><dd id="stockEditAvailable"></dd></div>
                    <div><dt>Pending Order Holds:</dt><dd id="stockEditHeld"></dd></div>
                </dl>
                <div class="form-group">
                    <label for="stockEditMode">Available Stock Adjustment</label>
                    <select class="form-control" id="stockEditMode">
                        <option value="exact">Set exact physical stock</option>
                        <option value="add">Add quantity (+)</option>
                        <option value="remove">Remove quantity (-)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="stockEditAmount">Quantity <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="stockEditAmount" min="0" max="1000000" step="1" required>
                    <small class="text-muted">Exact mode sets the physical total, while Add/Remove changes it by the entered quantity.</small>
                </div>
                <div class="form-group">
                    <label for="stockEditReason">Reason for adjustment <span class="text-danger">*</span></label>
                    <input type="text" name="reason" id="stockEditReason" class="form-control" minlength="8" maxlength="160" required placeholder="e.g. Physical stock count correction">
                </div>
                <div class="alert alert-light border small mb-0" id="stockEditPreview" role="status" aria-live="polite"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="stockEditSave" disabled>Update Stock</button></div>
        </form>
    </div></div>
</div>
@stop

@section('css')
<style>
    .stock-management-card .card-header { gap: .75rem; }
    .stock-table th, .stock-table td { vertical-align: middle; }
    .stock-table th { white-space: nowrap; }
    .stock-table .stock-number { font-variant-numeric: tabular-nums; }
    .stock-actions .btn { min-width: 55px; margin: 2px 2px 2px 0; }
    .stock-edit-summary div { display: flex; gap: .4rem; margin-bottom: .3rem; flex-wrap: wrap; }
    .stock-edit-summary dt { margin: 0; font-weight: 600; }
    .stock-edit-summary dd { margin: 0; }
    .stock-history summary { list-style: none; }
    .stock-history summary::-webkit-details-marker { display: none; }
    @media(max-width: 767px) { .stock-management-card .card-header .btn { width:100%; } .stock-table { min-width: 760px; } }
</style>
@stop

@section('js')
<script>
(function () {
    'use strict';
    const purchaseForm = document.getElementById('stockPurchaseForm');
    const purchaseProduct = document.getElementById('stockPurchaseProduct');
    const purchaseExpected = document.getElementById('stockPurchaseExpected');
    const purchaseSave = document.getElementById('stockPurchaseSave');
    if (purchaseProduct && purchaseForm) {
        purchaseProduct.addEventListener('change', function () {
            const option = this.options[this.selectedIndex];
            const action = option && option.dataset.action;
            purchaseForm.action = action || '';
            purchaseExpected.value = action ? option.dataset.stock : '';
            purchaseSave.disabled = !action;
        });
    }

    const editForm = document.getElementById('stockEditForm');
    const editMode = document.getElementById('stockEditMode');
    const editAmount = document.getElementById('stockEditAmount');
    const editReason = document.getElementById('stockEditReason');
    const editExact = document.getElementById('stockEditNewExact');
    const editExpected = document.getElementById('stockEditExpected');
    const editSave = document.getElementById('stockEditSave');
    const editPreview = document.getElementById('stockEditPreview');
    let physical = 0, held = 0;

    function updatePreview() {
        const amount = Number(editAmount.value);
        const isValidAmount = editAmount.value.trim() !== '' && Number.isInteger(amount)
            && amount >= (editMode.value === 'exact' ? 0 : 1) && amount <= 1000000;
        const target = editMode.value === 'add' ? physical + amount
            : editMode.value === 'remove' ? physical - amount : amount;
        const isValidTarget = isValidAmount && target >= held && target >= 0
            && target <= 1000000 && target !== physical;
        editExact.value = isValidTarget ? String(target) : '';
        editSave.disabled = !isValidTarget || editReason.value.trim().length < 8;
        if (!isValidAmount) {
            editPreview.textContent = 'Enter a valid whole number of units.';
        } else if (target < held) {
            editPreview.textContent = 'Cannot reduce physical stock below ' + held + ' units reserved by pending orders.';
        } else if (target < 0 || target > 1000000) {
            editPreview.textContent = 'The adjusted physical quantity must be between 0 and 1,000,000.';
        } else {
            editPreview.textContent = 'New physical stock: ' + target
                + '  |  New available stock: ' + (target - held) + (target === physical ? ' (unchanged)' : '');
        }
    }

    if (typeof jQuery !== 'undefined' && editForm) {
        jQuery('#stockEditModal').on('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            physical = Number(button.dataset.physical) || 0;
            held = Number(button.dataset.held) || 0;
            editForm.action = button.dataset.action;
            editForm.reset();
            editExpected.value = String(physical);
            editMode.value = 'exact';
            editAmount.value = String(physical);
            document.getElementById('stockEditProduct').textContent = button.dataset.product || '';
            document.getElementById('stockEditCode').textContent = button.dataset.code || '';
            document.getElementById('stockEditReceived').textContent = button.dataset.received || '0';
            document.getElementById('stockEditPhysical').textContent = String(physical);
            document.getElementById('stockEditAvailable').textContent = String(Math.max(0, physical - held));
            document.getElementById('stockEditHeld').textContent = String(held);
            updatePreview();
        });
    }
    if (editMode && editAmount && editReason) {
        editMode.addEventListener('change', function () {
            editAmount.min = editMode.value === 'exact' ? '0' : '1';
            editAmount.value = editMode.value === 'exact' ? String(physical) : '';
            updatePreview();
        });
        editAmount.addEventListener('input', updatePreview);
        editReason.addEventListener('input', updatePreview);
    }
    if (editForm) editForm.addEventListener('submit', function (event) {
        updatePreview();
        if (editSave.disabled) event.preventDefault();
    });
})();
</script>
@stop
