@extends('adminlte::page')

@section('title', $title ?? 'Create Bulk Orders')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Create Bulk Orders' }}</h1>

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

    <a href="{{ $returnUrl }}" class="btn btn-outline-secondary btn-sm shadow-none">
        <i class="fas fa-arrow-left mr-1"></i> Back to Orders
    </a>
</div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-10 col-lg-11">
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card shadow-sm border-0" style="border-radius: 12px;">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-1 font-weight-bold">
                    <i class="fas fa-layer-group text-primary mr-2"></i>Bulk Order Entry
                </h5>
            </div>

            <form method="POST" action="{{ route('admin.orders.bulk_store') }}" id="bulkOrderForm">
                @csrf
                <input type="hidden" name="return_url" value="{{ old('return_url', $returnUrl) }}">

                <div class="card-body">
                    <div class="alert alert-light border">
                        <div class="font-weight-bold mb-2">Required customer block format</div>
                        <pre class="mb-2 p-3 bg-white border rounded" style="white-space: pre-wrap;"><code>Rohim
01711111111
Dhaka, Bangladesh
PROD-0001-FBGG, PROD-0002-H3GH
500</code></pre>
                        <div class="small text-muted">
                            প্রতিটি order-এর জন্য ঠিক ৫টি line দিন: customer name, phone, address, product codes এবং final price।
                            প্রতিটি customer-এর পরে একটি blank line দিন। Multiple product comma দিয়ে লিখুন।
                            Quantity না দিলে 1 হবে। Quantity দিতে <code>PROD-0001:2</code> অথবা <code>PROD-0001*2</code> ব্যবহার করুন।
                            ৫ম line-এর amount-ই delivery charge-সহ negotiated final total হবে।
                            Product code এবং final price একই number হলেও line position আলাদা হওয়ায় conflict হবে না।
                        </div>
                    </div>


                    <div class="form-group mb-0">
                        <label for="bulk_orders" class="font-weight-bold">
                            Bulk Orders <span class="text-danger">*</span>
                        </label>
                        <textarea name="bulk_orders"
                                  id="bulk_orders"
                                  rows="20"
                                  maxlength="100000"
                                  class="form-control font-monospace @error('bulk_orders') is-invalid @enderror"
                                  placeholder="Rohim&#10;01706940942&#10;Dhaka, Bangladesh&#10;PROD-0001-FBGG, PROD-0002-H3GH&#10;1800&#10;&#10;Korim&#10;01642227081&#10;Dhaka, Bangladesh&#10;504&#10;504"
                                  required>{{ old('bulk_orders') }}</textarea>

                        @error('bulk_orders')
                            <div class="invalid-feedback" style="white-space: pre-line;">{{ $message }}</div>
                        @enderror

                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>Maximum 500 customer orders per submission.</span>
                            <span id="bulkLineCount">0 orders</span>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white border-top d-flex justify-content-end">
                    <a href="{{ $returnUrl }}" class="btn btn-light border mr-2">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="btnCreateBulkOrders">
                        <i class="fas fa-check-circle mr-1"></i> Create Bulk Orders
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
(function () {
    const textarea = document.getElementById('bulk_orders');
    const counter = document.getElementById('bulkLineCount');
    const form = document.getElementById('bulkOrderForm');
    const submitButton = document.getElementById('btnCreateBulkOrders');

    function updateOrderCount() {
        const lines = textarea.value.split(/\r?\n/);
        const legacyPipeOrders = lines.filter(function (line) {
            return ((line.match(/\|/g) || []).length >= 3);
        }).length;

        const prefixedOrders = lines.filter(function (line) {
            return /^\s*name\s*[:：]/i.test(line);
        }).length;

        let count = legacyPipeOrders + prefixedOrders;

        if (count === 0) {
            const nonEmptyValues = lines.filter(function (line) {
                return line.trim() !== '';
            }).length;

            count = Math.floor(nonEmptyValues / 5);
        }

        counter.textContent = count + (count === 1 ? ' order' : ' orders');
    }

    textarea.addEventListener('input', updateOrderCount);
    updateOrderCount();

    form.addEventListener('submit', function () {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Creating...';
    });
})();
</script>
@endsection
