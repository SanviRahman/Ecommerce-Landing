<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $campaign->meta_title ?: $campaign->title }}</title>

    @if ($campaign->meta_description)
        <meta name="description" content="{{ $campaign->meta_description }}">
    @endif

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
            width: 75px;
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
                <a href="#order-form" class="cta-btn">
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

                <form action="{{ route('campaign.order.store', $campaign->slug) }}" method="POST">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h5 class="border p-2 mb-0">পণ্যের বিবরণ</h5>

                            <table class="table table-bordered product-table bg-white">
                                <thead>
                                <tr>
                                    <th>প্রোডাক্ট</th>
                                    <th width="95">পরিমাণ</th>
                                    <th width="120">মূল্য</th>
                                </tr>
                                </thead>

                                <tbody>
                                @php
                                    $subtotal = 0;
                                @endphp

                                @foreach ($products as $product)
                                    @php
                                        $price = $product->pivot->campaign_price ?: $product->new_price;
                                        $subtotal += $price;
                                    @endphp

                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="{{ $product->thumbnail }}" class="mr-2" alt="{{ $product->name }}">
                                                <span>{{ \Illuminate\Support\Str::limit($product->name, 35) }}</span>
                                            </div>

                                            <input type="hidden" name="products[{{ $loop->index }}][id]" value="{{ $product->id }}">
                                        </td>

                                        <td>
                                            <input type="number"
                                                   name="products[{{ $loop->index }}][quantity]"
                                                   value="1"
                                                   min="1"
                                                   class="form-control quantity-input">
                                        </td>

                                        <td>৳{{ number_format($price) }}</td>
                                    </tr>
                                @endforeach

                                <tr>
                                    <th colspan="2">পণ্যের মোট</th>
                                    <th>৳{{ number_format($campaign->new_price ?: $subtotal) }}</th>
                                </tr>

                                <tr>
                                    <th colspan="2">ঢাকার ভিতরে ডেলিভারি</th>
                                    <th>৳70</th>
                                </tr>

                                <tr>
                                    <th colspan="2">ঢাকার বাইরে ডেলিভারি</th>
                                    <th>৳130</th>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h5 class="border p-2 mb-0">আপনার ইনফরমেশন দিন</h5>

                            <div class="border p-3 bg-white">
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
                                    <label>নোট / কালার / সাইজ</label>
                                    <textarea name="customer_note"
                                              class="form-control"
                                              rows="2"
                                              placeholder="পছন্দের কালার/সাইজ লিখুন">{{ old('customer_note') }}</textarea>
                                </div>

                                <button type="submit" class="btn btn-success btn-block btn-lg">
                                    অর্ডার কনফার্ম করুন
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

</body>
</html>