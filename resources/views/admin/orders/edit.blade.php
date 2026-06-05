@extends('adminlte::page')

@section('title', $title ?? 'Edit Order')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">Edit Order</h1>
        <ol class="breadcrumb mt-2 mb-0 bg-transparent p-0">
            @foreach($breadcrumb ?? [] as $item)
                <li class="breadcrumb-item">
                    <a href="{{ $item['url'] }}">{{ $item['text'] }}</a>
                </li>
            @endforeach
        </ol>
    </div>

    <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to Orders
    </a>
</div>
@endsection

@section('content')
@php
    $productImageMap = $productImageMap ?? collect();
@endphp

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form action="{{ route('admin.orders.update', $order->id) }}" method="POST" id="orderEditForm" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        <i class="fas fa-user text-primary mr-1"></i>
                        Customer Information
                    </h5>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Invoice ID</label>
                            <input type="text" name="invoice_id" value="{{ old('invoice_id', $order->invoice_id) }}" class="form-control @error('invoice_id') is-invalid @enderror" required>
                            @error('invoice_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Customer Name</label>
                            <input type="text" name="customer_name" value="{{ old('customer_name', $order->customer_name) }}" class="form-control @error('customer_name') is-invalid @enderror" required>
                            @error('customer_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Phone</label>
                            <input type="text" name="phone" value="{{ old('phone', $order->phone) }}" class="form-control @error('phone') is-invalid @enderror" required>
                            @error('phone') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Delivery Area</label>
                            <select name="delivery_area" class="form-control">
                                @foreach(['inside_dhaka' => 'Inside Dhaka', 'outside_dhaka' => 'Outside Dhaka', 'free_delivery' => 'Free Delivery'] as $key => $label)
                                    <option value="{{ $key }}" @selected(old('delivery_area', $order->delivery_area) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Address</label>
                            <textarea name="address" rows="3" class="form-control @error('address') is-invalid @enderror" required>{{ old('address', $order->address) }}</textarea>
                            @error('address') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Customer Note</label>
                            <textarea name="customer_note" rows="2" class="form-control">{{ old('customer_note', $order->customer_note) }}</textarea>
                        </div>

                        <div class="col-md-12 mb-0">
                            <label class="font-weight-bold">Admin Note</label>
                            <textarea name="admin_note" rows="3" class="form-control">{{ old('admin_note', $order->admin_note) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 font-weight-bold">
                        <i class="fas fa-box-open text-success mr-1"></i>
                        Products
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddOrderItem">
                        <i class="fas fa-plus mr-1"></i> Add Product
                    </button>
                </div>

                <div class="card-body">
                    <div id="orderItemsWrapper">
                        @php
                            $oldItems = old('items');
                            $rows = $oldItems ? collect($oldItems) : $order->items;
                        @endphp

                        @foreach($rows as $index => $item)
                            @php
                                $isArray = is_array($item);
                                $itemId = $isArray ? ($item['id'] ?? null) : $item->id;
                                $productId = $isArray ? ($item['product_id'] ?? null) : $item->product_id;
                                $quantity = $isArray ? ($item['quantity'] ?? 1) : $item->quantity;
                                $unitPrice = $isArray ? ($item['unit_price'] ?? 0) : $item->unit_price;
                                $discountAmount = $isArray ? ($item['discount_amount'] ?? 0) : ($item->discount_amount ?? 0);
                                $imageUrl = $isArray
                                    ? ($productImageMap[$productId] ?? null)
                                    : ($item->product_image_url ?? ($productImageMap[$productId] ?? null));
                            @endphp

                            <div class="order-item-row border rounded p-3 mb-2 bg-light">
                                <input type="hidden" name="items[{{ $index }}][id]" value="{{ $itemId }}">

                                <div class="row align-items-end">
                                    <div class="col-md-5 mb-2">
                                        <label class="font-weight-bold">Product</label>
                                        <div class="d-flex align-items-center">
                                            <div class="order-edit-product-image-box mr-2">
                                                @if($imageUrl)
                                                    <img src="{{ $imageUrl }}" alt="Product Image" class="order-edit-product-image">
                                                @else
                                                    <span class="order-edit-product-image-placeholder">
                                                        <i class="fas fa-image"></i>
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="flex-grow-1">
                                                <select name="items[{{ $index }}][product_id]" class="form-control product-select" required>
                                                    <option value="" data-price="0" data-image="">Select Product</option>
                                                    @foreach($products as $product)
                                                        @php
                                                            $optionImage = $productImageMap[$product->id] ?? null;
                                                        @endphp
                                                        <option value="{{ $product->id }}"
                                                                data-price="{{ $product->new_price ?? 0 }}"
                                                                data-image="{{ $optionImage }}"
                                                                @selected((int)$productId === (int)$product->id)>
                                                            {{ $product->name }} — ৳{{ number_format($product->new_price ?? 0) }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-2 mb-2">
                                        <label class="font-weight-bold">Qty</label>
                                        <input type="number" name="items[{{ $index }}][quantity]" value="{{ $quantity }}" min="1" class="form-control item-qty" required>
                                    </div>

                                    <div class="col-md-2 mb-2">
                                        <label class="font-weight-bold">Unit Price</label>
                                        <input type="number" name="items[{{ $index }}][unit_price]" value="{{ $unitPrice }}" min="0" step="0.01" class="form-control item-price" required>
                                    </div>

                                    <div class="col-md-1 mb-2">
                                        <label class="font-weight-bold">Discount</label>
                                        <input type="number" name="items[{{ $index }}][discount_amount]" value="{{ $discountAmount }}" min="0" step="0.01" class="form-control item-discount">
                                    </div>

                                    <div class="col-md-1 mb-2">
                                        <label class="font-weight-bold">Total</label>
                                        <input type="text" class="form-control item-line-total px-1" value="0" readonly>
                                    </div>

                                    <div class="col-md-1 mb-2 text-right">
                                        <button type="button" class="btn btn-danger btn-remove-item">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        <i class="fas fa-cogs text-primary mr-1"></i>
                        Order Settings
                    </h5>
                </div>

                <div class="card-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Order Status</label>
                        <select name="order_status" class="form-control">
                            @foreach($orderStatuses as $status)
                                <option value="{{ $status }}" @selected(old('order_status', $order->order_status) === $status)>
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Payment Status</label>
                        <select name="payment_status" class="form-control">
                            @foreach($paymentStatuses as $status)
                                <option value="{{ $status }}" @selected(old('payment_status', $order->payment_status) === $status)>
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Order Field</label>
                        <select name="order_field_id" class="form-control">
                            <option value="">No Field</option>
                            @foreach($orderFields as $field)
                                <option value="{{ $field->id }}" @selected((int)old('order_field_id', $order->order_field_id) === (int)$field->id)>
                                    {{ $field->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Assigned Employee</label>
                        <select name="assigned_employee_id" class="form-control">
                            <option value="">Unassigned</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((int)old('assigned_employee_id', $order->assigned_employee_id) === (int)$employee->id)>
                                    {{ $employee->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Courier</label>
                        <select name="courier_id" class="form-control">
                            <option value="">No Courier</option>
                            @foreach($couriers as $courier)
                                <option value="{{ $courier->id }}" @selected((int)old('courier_id', $order->courier_id) === (int)$courier->id)>
                                    {{ $courier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label class="font-weight-bold">Sub Total</label>
                        <input type="text" id="subTotalPreview" class="form-control" value="0" readonly>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Shipping Charge</label>
                        <input type="number" name="shipping_charge" value="{{ old('shipping_charge', $order->shipping_charge) }}" min="0" step="0.01" class="form-control total-input" id="shippingChargeInput">
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">COD Charge</label>
                        <input type="number" name="cod_charge" value="{{ old('cod_charge', $order->cod_charge) }}" min="0" step="0.01" class="form-control total-input" id="codChargeInput">
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Grand Total</label>
                        <input type="text" id="grandTotalPreview" class="form-control font-weight-bold" value="0" readonly>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-body d-flex justify-content-between flex-wrap">
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary mb-2 mb-md-0">
                        <i class="fas fa-arrow-left mr-1"></i> Back
                    </a>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save mr-1"></i> Update Order
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection


@section('css')
<style>
.order-edit-product-image-box {
    width: 58px;
    min-width: 58px;
    height: 58px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.order-edit-product-image {
    width: 58px;
    height: 58px;
    object-fit: cover;
}
.order-edit-product-image-placeholder {
    color: #94a3b8;
    font-size: 18px;
}
.item-line-total {
    font-size: 12px;
}
</style>
@endsection

@section('js')
<script>
$(document).ready(function () {
    let itemIndex = {{ $rows->count() }};

    function money(value) {
        const number = Number(value || 0);
        return '৳' + number.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function imageBoxHtml(imageUrl) {
        if (imageUrl) {
            return `<img src="${escapeHtml(imageUrl)}" alt="Product Image" class="order-edit-product-image">`;
        }

        return `<span class="order-edit-product-image-placeholder"><i class="fas fa-image"></i></span>`;
    }

    function updateRowImage(row, imageUrl) {
        row.find('.order-edit-product-image-box').html(imageBoxHtml(imageUrl));
    }

    function recalcTotals() {
        let subTotal = 0;

        $('.order-item-row').each(function () {
            const row = $(this);
            const qty = Number(row.find('.item-qty').val() || 0);
            const price = Number(row.find('.item-price').val() || 0);
            const discount = Number(row.find('.item-discount').val() || 0);
            const lineTotal = Math.max(0, (qty * price) - discount);

            subTotal += lineTotal;
            row.find('.item-line-total').val(money(lineTotal));
        });

        const shipping = Number($('#shippingChargeInput').val() || 0);
        const cod = Number($('#codChargeInput').val() || 0);

        $('#subTotalPreview').val(money(subTotal));
        $('#grandTotalPreview').val(money(subTotal + shipping + cod));
    }

    function productOptions() {
        return `@foreach($products as $product)
            @php
                $optionImage = $productImageMap[$product->id] ?? null;
            @endphp
            <option value="{{ $product->id }}"
                    data-price="{{ $product->new_price ?? 0 }}"
                    data-image="{{ $optionImage }}">
                {{ addslashes($product->name) }} — ৳{{ number_format($product->new_price ?? 0) }}
            </option>
        @endforeach`;
    }

    $('#btnAddOrderItem').on('click', function () {
        const index = itemIndex++;

        $('#orderItemsWrapper').append(`
            <div class="order-item-row border rounded p-3 mb-2 bg-light">
                <input type="hidden" name="items[${index}][id]" value="">

                <div class="row align-items-end">
                    <div class="col-md-5 mb-2">
                        <label class="font-weight-bold">Product</label>
                        <div class="d-flex align-items-center">
                            <div class="order-edit-product-image-box mr-2">
                                ${imageBoxHtml('')}
                            </div>

                            <div class="flex-grow-1">
                                <select name="items[${index}][product_id]" class="form-control product-select" required>
                                    <option value="" data-price="0" data-image="">Select Product</option>
                                    ${productOptions()}
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="font-weight-bold">Qty</label>
                        <input type="number" name="items[${index}][quantity]" value="1" min="1" class="form-control item-qty" required>
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="font-weight-bold">Unit Price</label>
                        <input type="number" name="items[${index}][unit_price]" value="0" min="0" step="0.01" class="form-control item-price" required>
                    </div>

                    <div class="col-md-1 mb-2">
                        <label class="font-weight-bold">Discount</label>
                        <input type="number" name="items[${index}][discount_amount]" value="0" min="0" step="0.01" class="form-control item-discount">
                    </div>

                    <div class="col-md-1 mb-2">
                        <label class="font-weight-bold">Total</label>
                        <input type="text" class="form-control item-line-total px-1" value="0" readonly>
                    </div>

                    <div class="col-md-1 mb-2 text-right">
                        <button type="button" class="btn btn-danger btn-remove-item">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `);

        recalcTotals();
    });

    $(document).on('change', '.product-select', function () {
        const option = $(this).find(':selected');
        const price = option.data('price') || 0;
        const imageUrl = option.data('image') || '';
        const row = $(this).closest('.order-item-row');

        row.find('.item-price').val(price);
        updateRowImage(row, imageUrl);
        recalcTotals();
    });

    $(document).on('input', '.item-qty, .item-price, .total-input', recalcTotals);

    $(document).on('click', '.btn-remove-item', function () {
        if ($('.order-item-row').length <= 1) {
            alert('At least one product is required.');
            return;
        }

        $(this).closest('.order-item-row').remove();
        recalcTotals();
    });

    recalcTotals();
});
</script>
@endsection