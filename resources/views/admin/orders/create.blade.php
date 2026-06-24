@extends('adminlte::page')

@section('title', $title ?? 'Create Manual Order')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">Create Manual Order</h1>

        <ol class="breadcrumb mt-2 mb-0 bg-transparent p-0">
            @foreach($breadcrumb ?? [] as $item)
                <li class="breadcrumb-item">
                    <a href="{{ $item['url'] }}">{{ $item['text'] }}</a>
                </li>
            @endforeach
        </ol>
    </div>

    <a href="{{ $returnUrl ?? route('admin.orders.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to Orders
    </a>
</div>
@endsection

@section('content')
@php
    $productImageMap = $productImageMap ?? collect();
    $isEmployeeCreator = $isEmployeeCreator ?? (auth()->check() && auth()->user()->isEmployee());
    $currentEmployee = $currentEmployee ?? ($isEmployeeCreator ? auth()->user() : null);

    $deliveryAreaOptions = [
        'inside_dhaka' => 'ঢাকার ভিতরে',
        'outside_dhaka' => 'ঢাকার বাইরে',
        'free_delivery' => 'ফ্রি ডেলিভারি',
    ];

    $rows = collect(old('items', [[
        'product_id' => null,
        'quantity' => 1,
        'unit_price' => 0,
        'discount_amount' => 0,
    ]]));

    $initialSubTotal = $rows->sum(function ($row) {
        $quantity = max(1, (int) ($row['quantity'] ?? 1));
        $unitPrice = max(0, (float) ($row['unit_price'] ?? 0));
        $discount = max(0, (float) ($row['discount_amount'] ?? 0));

        return max(0, ($quantity * $unitPrice) - $discount);
    });

    $initialShippingCharge = max(0, (float) old('shipping_charge', 0));
    $initialCodCharge = max(0, (float) old('cod_charge', 0));
    $initialGrandTotal = $initialSubTotal + $initialShippingCharge + $initialCodCharge;

    /*
     * Prepare JavaScript product data in PHP first.
     *
     * Keeping a closure and nested array directly inside Blade's @json(...)
     * directive can confuse the Blade compiler and produce an
     * "Unclosed '[' does not match ')'" ParseError.
     */
    $productsForJs = $products
        ->map(function ($product) use ($productImageMap) {
            return [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'price' => (float) ($product->new_price ?? 0),
                'image' => (string) ($productImageMap[$product->id] ?? ''),
            ];
        })
        ->values();
@endphp

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <div class="font-weight-bold mb-1">Please correct the following information:</div>
        <ul class="mb-0 pl-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="alert alert-success border-0 shadow-sm">
    <i class="fas fa-info-circle mr-1"></i>
    This order will be recorded as a
    <strong>{{ $isEmployeeCreator ? 'Employee Manual Order' : 'Admin Manual Order' }}</strong>
    and will appear with a light-green background in order lists.
</div>

<form action="{{ route('admin.orders.store') }}"
      method="POST"
      id="manualOrderCreateForm"
      novalidate>
    @csrf

    <input type="hidden" name="return_url" value="{{ old('return_url', $returnUrl ?? route('admin.orders.index')) }}">

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
                            <input type="text"
                                   value="Automatically generated after save"
                                   class="form-control"
                                   readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Customer Name <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="customer_name"
                                   value="{{ old('customer_name') }}"
                                   maxlength="255"
                                   class="form-control @error('customer_name') is-invalid @enderror"
                                   required>
                            @error('customer_name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Phone <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="phone"
                                   value="{{ old('phone') }}"
                                   maxlength="11"
                                   minlength="11"
                                   inputmode="numeric"
                                   pattern="01[0-9]{9}"
                                   placeholder="01XXXXXXXXX"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   required>
                            <small class="form-text text-muted">Exactly 11 local digits without +88.</small>
                            @error('phone')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Delivery Area</label>
                            <select name="delivery_area"
                                    class="form-control @error('delivery_area') is-invalid @enderror">
                                <option value="">Select Delivery Area</option>

                                @foreach($deliveryAreaOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('delivery_area', 'inside_dhaka') === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('delivery_area')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Address <span class="text-danger">*</span></label>
                            <textarea name="address"
                                      rows="3"
                                      maxlength="2000"
                                      class="form-control @error('address') is-invalid @enderror"
                                      required>{{ old('address') }}</textarea>
                            @error('address')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Customer Note</label>
                            <textarea name="customer_note"
                                      rows="2"
                                      maxlength="3000"
                                      class="form-control @error('customer_note') is-invalid @enderror">{{ old('customer_note') }}</textarea>
                            @error('customer_note')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-12 mb-0">
                            <label class="font-weight-bold">Admin Note</label>
                            <textarea name="admin_note"
                                      rows="3"
                                      maxlength="5000"
                                      class="form-control @error('admin_note') is-invalid @enderror">{{ old('admin_note') }}</textarea>
                            @error('admin_note')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
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

                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            id="btnAddOrderItem">
                        <i class="fas fa-plus mr-1"></i> Add Product
                    </button>
                </div>

                <div class="card-body">
                    <div id="orderItemsWrapper">
                        @foreach($rows as $index => $item)
                            @php
                                $productId = $item['product_id'] ?? null;
                                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                                $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));
                                $discountAmount = max(0, (float) ($item['discount_amount'] ?? 0));
                                $lineTotal = max(0, ($unitPrice * $quantity) - $discountAmount);
                                $imageUrl = $productImageMap[$productId] ?? null;
                            @endphp

                            <div class="order-item-row border rounded p-3 mb-2 bg-light">
                                <div class="row align-items-end">
                                    <div class="col-md-5 mb-2">
                                        <label class="font-weight-bold">Product <span class="text-danger">*</span></label>

                                        <div class="d-flex align-items-center">
                                            <div class="order-edit-product-image-box mr-2">
                                                @if($imageUrl)
                                                    <img src="{{ $imageUrl }}"
                                                         alt="Product Image"
                                                         class="order-edit-product-image">
                                                @else
                                                    <span class="order-edit-product-image-placeholder">
                                                        <i class="fas fa-image"></i>
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="flex-grow-1">
                                                <select name="items[{{ $index }}][product_id]"
                                                        class="form-control product-select @error("items.{$index}.product_id") is-invalid @enderror"
                                                        required>
                                                    <option value="" data-price="0" data-image="">Select Product</option>

                                                    @foreach($products as $product)
                                                        @php
                                                            $optionImage = $productImageMap[$product->id] ?? null;
                                                        @endphp

                                                        <option value="{{ $product->id }}"
                                                                data-price="{{ $product->new_price ?? 0 }}"
                                                                data-image="{{ $optionImage }}"
                                                                @selected((int) $productId === (int) $product->id)>
                                                            {{ $product->name }} — ৳{{ number_format($product->new_price ?? 0) }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                @error("items.{$index}.product_id")
                                                    <span class="invalid-feedback">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-2 mb-2">
                                        <label class="font-weight-bold">Qty</label>
                                        <input type="number"
                                               name="items[{{ $index }}][quantity]"
                                               value="{{ $quantity }}"
                                               min="1"
                                               max="100000"
                                               class="form-control item-qty"
                                               required>
                                    </div>

                                    <div class="col-md-1 mb-2 order-unit-price-col">
                                        <label class="font-weight-bold">Unit Price</label>
                                        <input type="number"
                                               name="items[{{ $index }}][unit_price]"
                                               value="{{ $unitPrice }}"
                                               min="0"
                                               step="0.01"
                                               class="form-control item-price"
                                               required>
                                    </div>

                                    <div class="col-md-2 mb-2 order-discount-col">
                                        <label class="font-weight-bold">Discount</label>
                                        <input type="number"
                                               name="items[{{ $index }}][discount_amount]"
                                               value="{{ $discountAmount }}"
                                               min="0"
                                               step="0.01"
                                               class="form-control item-discount">
                                    </div>

                                    <div class="col-md-1 mb-2">
                                        <label class="font-weight-bold">Total</label>
                                        <input type="text"
                                               class="form-control item-line-total px-1"
                                               value="৳{{ number_format($lineTotal, 2) }}"
                                               readonly>
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
                        <label class="font-weight-bold">Campaign</label>
                        <select name="campaign_id" class="form-control">
                            <option value="">No Campaign</option>

                            @foreach($campaigns as $campaign)
                                <option value="{{ $campaign->id }}" @selected((int) old('campaign_id') === (int) $campaign->id)>
                                    {{ $campaign->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">
                            Order Date & Time
                            <span class="text-danger">*</span>
                        </label>

                        <input type="datetime-local"
                               name="order_date"
                               value="{{ old('order_date', $defaultOrderDate) }}"
                               step="60"
                               class="form-control @error('order_date') is-invalid @enderror"
                               required>

                        @error('order_date')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror

                        <small class="form-text text-muted">
                            Bangladesh time (Asia/Dhaka). This date is used in order lists and reports.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Order Status</label>
                        <select name="order_status" class="form-control" required>
                            @foreach($orderStatuses as $status)
                                <option value="{{ $status }}" @selected(old('order_status', \App\Models\Order::STATUS_PROCESSING) === $status)>
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Payment Status</label>
                        <select name="payment_status" class="form-control" required>
                            @foreach($paymentStatuses as $status)
                                <option value="{{ $status }}" @selected(old('payment_status', \App\Models\Order::PAYMENT_STATUS_COD_PENDING) === $status)>
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
                                <option value="{{ $field->id }}" @selected((int) old('order_field_id') === (int) $field->id)>
                                    {{ $field->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Assigned Employee</label>

                        @if($isEmployeeCreator)
                            <input type="hidden" name="assigned_employee_id" value="{{ $currentEmployee->id }}">

                            <select class="form-control" disabled>
                                <option selected>{{ $currentEmployee->name }}</option>
                            </select>

                            <small class="form-text text-muted">
                                Employee-created manual orders are automatically assigned to the employee who creates them.
                            </small>
                        @else
                            <select name="assigned_employee_id" class="form-control">
                                <option value="">Auto Assign</option>

                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((int) old('assigned_employee_id') === (int) $employee->id)>
                                        {{ $employee->name }}
                                    </option>
                                @endforeach
                            </select>

                            <small class="form-text text-muted">
                                If no employee is selected, the existing assignment service will run normally.
                            </small>
                        @endif
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Courier</label>
                        <select name="courier_id" class="form-control">
                            <option value="">No Courier</option>

                            @foreach($couriers as $courier)
                                <option value="{{ $courier->id }}" @selected((int) old('courier_id') === (int) $courier->id)>
                                    {{ $courier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label class="font-weight-bold">Sub Total</label>
                        <input type="text"
                               id="subTotalPreview"
                               class="form-control"
                               value="৳{{ number_format($initialSubTotal, 2) }}"
                               readonly>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Shipping Charge</label>
                        <input type="number"
                               name="shipping_charge"
                               value="{{ old('shipping_charge', 0) }}"
                               min="0"
                               step="0.01"
                               class="form-control total-input"
                               id="shippingChargeInput">
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">COD Charge</label>
                        <input type="number"
                               name="cod_charge"
                               value="{{ old('cod_charge', 0) }}"
                               min="0"
                               step="0.01"
                               class="form-control total-input"
                               id="codChargeInput">
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Grand Total</label>
                        <input type="text"
                               id="grandTotalPreview"
                               class="form-control font-weight-bold"
                               value="৳{{ number_format($initialGrandTotal, 2) }}"
                               readonly>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-body d-flex justify-content-between flex-wrap">
                    <a href="{{ $returnUrl ?? route('admin.orders.index') }}"
                       class="btn btn-secondary mb-2 mb-md-0">
                        <i class="fas fa-arrow-left mr-1"></i> Back
                    </a>

                    <button type="submit"
                            class="btn btn-success px-4"
                            id="btnSaveManualOrder">
                        <i class="fas fa-save mr-1"></i> Create Order
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

.order-unit-price-col .item-price {
    min-width: 82px;
}

.order-discount-col .item-discount {
    min-width: 130px;
}

.item-line-total {
    font-size: 12px;
}
</style>
@endsection

@section('js')
<script>
$(document).ready(function() {
    let itemIndex = @json($rows->count());

    const products = @json($productsForJs);

    function toNumber(value) {
        const cleaned = String(value || 0).replace(/[^0-9.\-]/g, '');
        const number = Number(cleaned || 0);

        return Number.isFinite(number) ? number : 0;
    }

    function money(value) {
        const number = toNumber(value);

        return '৳' + number.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
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

        return '<span class="order-edit-product-image-placeholder"><i class="fas fa-image"></i></span>';
    }

    function updateRowImage(row, imageUrl) {
        row.find('.order-edit-product-image-box').html(imageBoxHtml(imageUrl));
    }

    function recalcTotals() {
        let subTotal = 0;

        $('.order-item-row').each(function() {
            const row = $(this);
            const quantity = Math.max(1, toNumber(row.find('.item-qty').val()));
            const price = Math.max(0, toNumber(row.find('.item-price').val()));
            const discount = Math.max(0, toNumber(row.find('.item-discount').val()));
            const lineTotal = Math.max(0, (quantity * price) - discount);

            subTotal += lineTotal;
            row.find('.item-line-total').val(money(lineTotal));
        });

        const shipping = Math.max(0, toNumber($('#shippingChargeInput').val()));
        const cod = Math.max(0, toNumber($('#codChargeInput').val()));

        $('#subTotalPreview').val(money(subTotal));
        $('#grandTotalPreview').val(money(subTotal + shipping + cod));
    }

    function productOptions() {
        return products.map(function(product) {
            return `
                <option value="${product.id}"
                        data-price="${product.price}"
                        data-image="${escapeHtml(product.image)}">
                    ${escapeHtml(product.name)} — ${money(product.price)}
                </option>
            `;
        }).join('');
    }

    $('#btnAddOrderItem').on('click', function() {
        const index = itemIndex++;

        $('#orderItemsWrapper').append(`
            <div class="order-item-row border rounded p-3 mb-2 bg-light">
                <div class="row align-items-end">
                    <div class="col-md-5 mb-2">
                        <label class="font-weight-bold">Product <span class="text-danger">*</span></label>

                        <div class="d-flex align-items-center">
                            <div class="order-edit-product-image-box mr-2">
                                ${imageBoxHtml('')}
                            </div>

                            <div class="flex-grow-1">
                                <select name="items[${index}][product_id]"
                                        class="form-control product-select"
                                        required>
                                    <option value="" data-price="0" data-image="">Select Product</option>
                                    ${productOptions()}
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="font-weight-bold">Qty</label>
                        <input type="number"
                               name="items[${index}][quantity]"
                               value="1"
                               min="1"
                               max="100000"
                               class="form-control item-qty"
                               required>
                    </div>

                    <div class="col-md-1 mb-2 order-unit-price-col">
                        <label class="font-weight-bold">Unit Price</label>
                        <input type="number"
                               name="items[${index}][unit_price]"
                               value="0"
                               min="0"
                               step="0.01"
                               class="form-control item-price"
                               required>
                    </div>

                    <div class="col-md-2 mb-2 order-discount-col">
                        <label class="font-weight-bold">Discount</label>
                        <input type="number"
                               name="items[${index}][discount_amount]"
                               value="0"
                               min="0"
                               step="0.01"
                               class="form-control item-discount">
                    </div>

                    <div class="col-md-1 mb-2">
                        <label class="font-weight-bold">Total</label>
                        <input type="text"
                               class="form-control item-line-total px-1"
                               value="৳0.00"
                               readonly>
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

    $(document).on('change', '.product-select', function() {
        const selectedOption = $(this).find(':selected');
        const row = $(this).closest('.order-item-row');

        row.find('.item-price').val(selectedOption.data('price') || 0);
        updateRowImage(row, selectedOption.data('image') || '');
        recalcTotals();
    });

    $(document).on(
        'input change keyup',
        '.item-qty, .item-price, .item-discount, .total-input',
        recalcTotals
    );

    $(document).on('click', '.btn-remove-item', function() {
        if ($('.order-item-row').length <= 1) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Product Required',
                    text: 'At least one product is required.'
                });
            } else {
                alert('At least one product is required.');
            }

            return;
        }

        $(this).closest('.order-item-row').remove();
        recalcTotals();
    });

    $('input[name="phone"]').on('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });

    $('#manualOrderCreateForm').on('submit', function() {
        const button = $('#btnSaveManualOrder');

        if (button.prop('disabled')) {
            return false;
        }

        button
            .prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin mr-1"></i> Creating...');
    });

    recalcTotals();
    setTimeout(recalcTotals, 100);
});
</script>
@endsection
