<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $campaign->meta_title ?: $campaign->title }}</title>

    @if ($campaign->meta_description)
        <meta name="description" content="{{ $campaign->meta_description }}">
    @endif

    {{-- Dynamic Tracking Scripts --}}
    {{-- Meta Pixel / GTM / TikTok / Google Analytics scripts will render from database --}}
    @include('frontend.partials.tracking-pixels')

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        body {
            background: #ffffff;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        .campaign-header {
            background: linear-gradient(90deg, #04420c, #008b1f, #04420c);
            color: #ffffff;
            padding: 35px 15px;
            text-align: center;
        }

        .campaign-header h1 {
            font-size: 34px;
            font-weight: 800;
            margin: 0;
        }

        .offer-box {
            border: 2px dashed #078b20;
            border-radius: 8px;
            padding: 22px;
            text-align: center;
            margin: 35px 0;
            color: #7a4216;
            font-size: 30px;
            font-weight: 700;
        }

        .image-card {
            background: #ffffff;
            border: 6px solid #008b25;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 10px 25px rgba(0,0,0,.10);
            margin-bottom: 35px;
        }

        .image-card-title {
            background: #255f34;
            color: #ffffff;
            text-align: center;
            padding: 14px;
            border-radius: 6px;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 25px;
        }

        .campaign-img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            background: #f8f9fa;
        }

        .price-box {
            background: #008b25;
            color: #ffffff;
            padding: 18px;
            text-align: center;
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 25px;
        }

        .cta-btn {
            display: inline-block;
            background: linear-gradient(180deg, #e4572e, #b01459);
            color: #ffffff;
            font-size: 28px;
            font-weight: 800;
            padding: 13px 45px;
            border-radius: 7px;
            border: 3px solid #ffd400;
            text-decoration: none;
            box-shadow: 0 5px 14px rgba(0,0,0,.25);
        }

        .cta-btn:hover {
            color: #ffffff;
            text-decoration: none;
        }

        .description-box {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 25px;
            margin-bottom: 35px;
            font-size: 18px;
            line-height: 1.8;
        }

        .order-section {
            background: linear-gradient(180deg, #fff6b7, #f2cbd1);
            padding: 45px 0;
        }

        .order-card {
            border: 7px solid #008b25;
            border-radius: 8px;
            padding: 18px;
            background: #ffffff;
        }

        .order-title {
            background: #008b25;
            color: #ffffff;
            text-align: center;
            padding: 18px;
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 25px;
        }

        .product-table img {
            width: 45px;
            height: 45px;
            object-fit: cover;
        }

        .quantity-input {
            width: 80px;
            text-align: center;
            font-weight: 700;
        }

        .order-info-box {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #ffffff;
            padding: 16px;
        }

        .form-control {
            min-height: 42px;
        }

        .courier-help {
            font-size: 13px;
            color: #6b7280;
            margin-top: 5px;
        }

        .summary-row th {
            background: #f8fafc;
            font-size: 15px;
        }

        .grand-total-row th {
            background: #008b25 !important;
            color: #ffffff;
            font-size: 18px;
        }

        .line-total {
            font-weight: 800;
            color: #008b25;
        }

        .live-summary-box {
            border: 2px solid #008b25;
            border-radius: 8px;
            background: #f8fff9;
            padding: 14px;
            margin-bottom: 18px;
        }

        .live-summary-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px dashed #b7d9bd;
            padding: 7px 0;
            font-size: 15px;
        }

        .live-summary-item:last-child {
            border-bottom: none;
        }

        .live-summary-item strong {
            font-size: 18px;
        }

        .live-total {
            color: #008b25;
            font-size: 24px !important;
        }

        .submit-total-text {
            font-weight: 800;
        }

        @media (max-width: 768px) {
            .campaign-header h1 {
                font-size: 24px;
            }

            .offer-box {
                font-size: 22px;
            }

            .campaign-img {
                height: 220px;
                margin-bottom: 15px;
            }

            .cta-btn {
                font-size: 22px;
                padding: 12px 25px;
            }

            .product-table {
                font-size: 13px;
            }

            .quantity-input {
                width: 70px;
            }
        }
    </style>
</head>

<body>

<section class="campaign-header">
    <div class="container">
        <h1>{{ $campaign->offer_text ?: $campaign->title }}</h1>
    </div>
</section>

<main>
    <div class="container">

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mt-4">
                <strong>Success!</strong> {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show mt-4">
                <strong>Error!</strong> {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-4">
                <strong>দয়া করে নিচের ভুলগুলো ঠিক করুন:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="offer-box">
            {{ $campaign->title }}
        </div>

        <div class="row mb-4">
            @if ($campaign->image_one_url)
                <div class="col-md-6 mb-3">
                    <img src="{{ $campaign->image_one_url }}" class="campaign-img shadow-sm" alt="{{ $campaign->title }}">
                </div>
            @endif

            @if ($campaign->image_two_url)
                <div class="col-md-6 mb-3">
                    <img src="{{ $campaign->image_two_url }}" class="campaign-img shadow-sm" alt="{{ $campaign->title }}">
                </div>
            @endif
        </div>

        <div class="row text-center mb-4">
            <div class="col-md-6 mb-3">
                <div class="offer-box m-0">
                    ১০০% কালার গ্যারান্টি
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="offer-box m-0">
                    ১০০% কোয়ালিটিফুল
                </div>
            </div>
        </div>

        <div class="offer-box text-danger">
            প্রিমিয়াম কোয়ালিটির নিশ্চয়তা
        </div>

        @if ($campaign->short_description)
            <div class="description-box">
                {!! $campaign->short_description !!}
            </div>
        @endif

        <div class="image-card">
            <div class="image-card-title">
                {{ $campaign->title }}
            </div>

            <div class="row justify-content-center">
                @if ($campaign->image_one_url)
                    <div class="col-md-4 mb-3">
                        <img src="{{ $campaign->image_one_url }}" class="campaign-img" alt="{{ $campaign->title }}">
                    </div>
                @endif

                @if ($campaign->image_two_url)
                    <div class="col-md-4 mb-3">
                        <img src="{{ $campaign->image_two_url }}" class="campaign-img" alt="{{ $campaign->title }}">
                    </div>
                @endif

                @if ($campaign->image_three_url)
                    <div class="col-md-4 mb-3">
                        <img src="{{ $campaign->image_three_url }}" class="campaign-img" alt="{{ $campaign->title }}">
                    </div>
                @endif
            </div>

            <div class="text-center mt-3">
                <a href="#order-form" class="cta-btn order-cta-btn">
                    {{ $campaign->button_text ?: 'অর্ডার করুন' }}
                </a>
            </div>
        </div>

        <div class="price-box">
            ৫০+ review
        </div>

        @if ($campaign->review_image_url)
            <div class="row mb-4">
                @for ($i = 0; $i < 4; $i++)
                    <div class="col-md-3 col-6 mb-3">
                        <img src="{{ $campaign->review_image_url }}" class="campaign-img" alt="Review">
                    </div>
                @endfor
            </div>
        @endif

        @if ($campaign->full_description)
            <div class="description-box">
                {!! $campaign->full_description !!}
            </div>
        @endif

    </div>

    <section class="order-section" id="order-form">
        <div class="container">
            <div class="order-card">
                <div class="order-title">
                    {{ $campaign->order_form_title ?: 'অফারটি সীমিত সময়ের জন্য, তাই অফার শেষ হওয়ার আগেই অর্ডার করুন' }}
                </div>

                @if ($campaign->order_form_subtitle)
                    <p class="text-center mb-4">
                        {{ $campaign->order_form_subtitle }}
                    </p>
                @endif

                <form action="{{ route('campaign.order.store', $campaign->slug) }}" method="POST" id="campaignOrderForm">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h5 class="border p-2 mb-0">পণ্যের বিবরণ</h5>

                            <table class="table table-bordered product-table bg-white">
                                <thead>
                                <tr>
                                    <th>প্রোডাক্ট</th>
                                    <th width="95">পরিমাণ</th>
                                    <th width="130">মূল্য</th>
                                </tr>
                                </thead>

                                <tbody>
                                @php
                                    $subtotal = 0;
                                    $totalQty = 0;
                                @endphp

                                @forelse ($products as $product)
                                    @php
                                        $price = (int) ($product->pivot->campaign_price ?: $product->new_price);
                                        $qty = (int) old('products.' . $loop->index . '.quantity', 1);
                                        $qty = $qty < 1 ? 1 : $qty;
                                        $lineTotal = $price * $qty;

                                        $subtotal += $lineTotal;
                                        $totalQty += $qty;
                                    @endphp

                                    <tr class="product-row" data-unit-price="{{ $price }}">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="{{ $product->thumbnail }}" class="mr-2" alt="{{ $product->name }}">
                                                <div>
                                                    <span>{{ \Illuminate\Support\Str::limit($product->name, 35) }}</span>
                                                    <br>
                                                    <small class="text-muted">
                                                        Unit Price:
                                                        <strong>৳{{ number_format($price) }}</strong>
                                                    </small>
                                                </div>
                                            </div>

                                            <input type="hidden" name="products[{{ $loop->index }}][id]" value="{{ $product->id }}">
                                        </td>

                                        <td>
                                            <input type="number"
                                                   name="products[{{ $loop->index }}][quantity]"
                                                   value="{{ $qty }}"
                                                   min="1"
                                                   step="1"
                                                   inputmode="numeric"
                                                   data-unit-price="{{ $price }}"
                                                   class="form-control quantity-input">
                                        </td>

                                        <td>
                                            <span class="line-total">৳{{ number_format($lineTotal) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-danger">
                                            কোনো প্রোডাক্ট পাওয়া যায়নি।
                                        </td>
                                    </tr>
                                @endforelse

                                @php
                                    $oldDeliveryArea = old('delivery_area');
                                    $shippingCharge = $oldDeliveryArea === 'inside_dhaka'
                                        ? 70
                                        : ($oldDeliveryArea === 'outside_dhaka' ? 130 : 0);

                                    $grandTotal = $subtotal + $shippingCharge;
                                @endphp

                                <tr class="summary-row">
                                    <th colspan="2">মোট পণ্য</th>
                                    <th>
                                        <span id="summaryTotalQty">{{ $totalQty }}</span> pcs
                                    </th>
                                </tr>

                                <tr class="summary-row">
                                    <th colspan="2">পণ্যের মোট</th>
                                    <th id="summarySubTotal">৳{{ number_format($subtotal) }}</th>
                                </tr>

                                <tr class="summary-row">
                                    <th colspan="2">ডেলিভারি চার্জ</th>
                                    <th id="summaryShippingCharge">
                                        {{ $shippingCharge > 0 ? '৳' . number_format($shippingCharge) : 'এরিয়া সিলেক্ট করুন' }}
                                    </th>
                                </tr>

                                <tr class="grand-total-row">
                                    <th colspan="2">সর্বমোট</th>
                                    <th id="summaryGrandTotal">৳{{ number_format($grandTotal) }}</th>
                                </tr>
                                </tbody>
                            </table>

                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle mr-1"></i>
                                পরিমাণ বা ডেলিভারি এরিয়া পরিবর্তন করলে মোট টাকা সাথে সাথে আপডেট হবে।
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5 class="border p-2 mb-0">আপনার ইনফরমেশন দিন</h5>

                            <div class="order-info-box">
                                <div class="live-summary-box">
                                    <div class="live-summary-item">
                                        <span>পণ্যের মোট</span>
                                        <strong id="sideSubTotal">৳{{ number_format($subtotal) }}</strong>
                                    </div>

                                    <div class="live-summary-item">
                                        <span>ডেলিভারি চার্জ</span>
                                        <strong id="sideShippingCharge">
                                            {{ $shippingCharge > 0 ? '৳' . number_format($shippingCharge) : '৳0' }}
                                        </strong>
                                    </div>

                                    <div class="live-summary-item">
                                        <span>মোট পরিমাণ</span>
                                        <strong><span id="sideTotalQty">{{ $totalQty }}</span> pcs</strong>
                                    </div>

                                    <div class="live-summary-item">
                                        <span>সর্বমোট</span>
                                        <strong class="live-total" id="sideGrandTotal">৳{{ number_format($grandTotal) }}</strong>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>আপনার নাম লিখুন <span class="text-danger">*</span></label>
                                    <input type="text"
                                           name="customer_name"
                                           value="{{ old('customer_name') }}"
                                           class="form-control @error('customer_name') is-invalid @enderror"
                                           placeholder="নাম"
                                           required>

                                    @error('customer_name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label>আপনার মোবাইল লিখুন <span class="text-danger">*</span></label>
                                    <input type="text"
                                           name="phone"
                                           value="{{ old('phone') }}"
                                           class="form-control @error('phone') is-invalid @enderror"
                                           placeholder="+৮৮ বাদে ১১ সংখ্যা"
                                           required>

                                    @error('phone')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label>আপনার ঠিকানা লিখুন <span class="text-danger">*</span></label>
                                    <textarea name="address"
                                              class="form-control @error('address') is-invalid @enderror"
                                              rows="3"
                                              placeholder="জেলা, থানা, গ্রাম"
                                              required>{{ old('address') }}</textarea>

                                    @error('address')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label>আপনার এরিয়া সিলেক্ট করুন <span class="text-danger">*</span></label>
                                    <select name="delivery_area"
                                            id="deliveryAreaSelect"
                                            class="form-control @error('delivery_area') is-invalid @enderror"
                                            required>
                                        <option value="">Select Area</option>
                                        <option value="inside_dhaka" @selected(old('delivery_area') === 'inside_dhaka')>
                                            ঢাকার ভিতরে ৭০ টাকা
                                        </option>
                                        <option value="outside_dhaka" @selected(old('delivery_area') === 'outside_dhaka')>
                                            ঢাকার বাইরে ১৩০ টাকা
                                        </option>
                                    </select>

                                    @error('delivery_area')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label>কুরিয়ার সার্ভিস সিলেক্ট করুন <span class="text-danger">*</span></label>
                                    <select name="courier_service"
                                            class="form-control @error('courier_service') is-invalid @enderror"
                                            required>
                                        <option value="">কুরিয়ার সার্ভিস নির্বাচন করুন</option>

                                        @foreach(($courierServices ?? config('couriers.list', [])) as $key => $label)
                                            <option value="{{ $key }}" @selected(old('courier_service') === $key)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <div class="courier-help">
                                        আপনি যে কুরিয়ার সার্ভিস সিলেক্ট করবেন, সেটি আপনার অর্ডারের সাথে সেভ হবে।
                                    </div>

                                    @error('courier_service')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label>নোট / কালার / সাইজ</label>
                                    <textarea name="customer_note"
                                              class="form-control"
                                              rows="2"
                                              placeholder="পছন্দের কালার/সাইজ লিখুন">{{ old('customer_note') }}</textarea>
                                </div>

                                <button type="submit" class="btn btn-success btn-block btn-lg" id="orderSubmitBtn">
                                    <span class="submit-label">অর্ডার কনফার্ম করুন</span>
                                    <span class="submit-total-text">
                                        - <span id="buttonGrandTotal">৳{{ number_format($grandTotal) }}</span>
                                    </span>
                                </button>

                                <p class="text-center mt-3 mb-0">
                                    অর্ডার করার পর আমাদের প্রতিনিধি আপনার সাথে যোগাযোগ করবে।
                                </p>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </section>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function () {
        const deliveryCharges = {
            inside_dhaka: 70,
            outside_dhaka: 130
        };

        function money(amount) {
            amount = Number(amount || 0);

            return '৳' + amount.toLocaleString('en-US', {
                maximumFractionDigits: 0
            });
        }

        function safeQuantity(input) {
            let qty = parseInt($(input).val(), 10);

            if (isNaN(qty) || qty < 1) {
                qty = 1;
            }

            $(input).val(qty);

            return qty;
        }

        function calculateOrderTotal() {
            let subTotal = 0;
            let totalQty = 0;

            $('.quantity-input').each(function () {
                const input = $(this);
                const qty = safeQuantity(input);
                const unitPrice = parseInt(input.data('unit-price'), 10) || 0;
                const lineTotal = qty * unitPrice;

                subTotal += lineTotal;
                totalQty += qty;

                input.closest('tr').find('.line-total').text(money(lineTotal));
            });

            const deliveryArea = $('#deliveryAreaSelect').val();
            const shippingCharge = deliveryCharges[deliveryArea] || 0;
            const grandTotal = subTotal + shippingCharge;

            $('#summaryTotalQty').text(totalQty);
            $('#sideTotalQty').text(totalQty);

            $('#summarySubTotal').text(money(subTotal));
            $('#sideSubTotal').text(money(subTotal));

            if (shippingCharge > 0) {
                $('#summaryShippingCharge').text(money(shippingCharge));
            } else {
                $('#summaryShippingCharge').text('এরিয়া সিলেক্ট করুন');
            }

            $('#sideShippingCharge').text(money(shippingCharge));
            $('#summaryGrandTotal').text(money(grandTotal));
            $('#sideGrandTotal').text(money(grandTotal));
            $('#buttonGrandTotal').text(money(grandTotal));
        }

        $(document).on('input change keyup', '.quantity-input', function () {
            calculateOrderTotal();
        });

        $('#deliveryAreaSelect').on('change', function () {
            calculateOrderTotal();
        });

        $('#campaignOrderForm').on('submit', function () {
            calculateOrderTotal();

            const button = $('#orderSubmitBtn');

            button.prop('disabled', true);
            button.html(
                '<span class="spinner-border spinner-border-sm mr-2"></span> অর্ডার সাবমিট হচ্ছে...'
            );
        });

        calculateOrderTotal();
    });
</script>

{{-- Optional Tracking Events --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const orderCtaButtons = document.querySelectorAll('.order-cta-btn');

        orderCtaButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (typeof fbq !== 'undefined') {
                    fbq('track', 'Lead');
                }
            });
        });

        const campaignOrderForm = document.getElementById('campaignOrderForm');

        if (campaignOrderForm) {
            campaignOrderForm.addEventListener('submit', function () {
                if (typeof fbq !== 'undefined') {
                    fbq('track', 'InitiateCheckout');
                }
            });
        }
    });
</script>

@if (session('success'))
    <script>
        if (typeof fbq !== 'undefined') {
            fbq('track', 'Lead');
        }
    </script>
@endif

</body>
</html>