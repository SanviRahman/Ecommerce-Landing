@extends('frontend.layouts.master')

@php
    $websiteName = $siteSetting->website_name ?? config('app.name', 'Laravel');

    $localStatusLabels = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'complete_invoice' => 'Invoice Complete',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'canceled' => 'Cancelled',
        'fake' => 'Fake',
        'stock_out' => 'Stock Out',
    ];
@endphp

@section('title', 'Order Tracking - ' . $websiteName)
@section('meta_description', 'ফোন নম্বর দিয়ে আপনার অর্ডারের সর্বশেষ অবস্থা দেখুন।')

@push('css')
<style>
    .track-page {
        min-height: 70vh;
        padding: 65px 0;
        background: #f8fafc;
    }

    .track-box {
        max-width: 860px;
        margin: 0 auto;
        padding: 34px;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 18px 50px rgba(15, 23, 42, .07);
    }

    .track-title {
        font-size: 30px;
        font-weight: 900;
        color: #334155;
        text-align: center;
    }

    .track-subtitle {
        color: #64748b;
        text-align: center;
        margin-bottom: 26px;
    }

    .track-input {
        height: 50px;
        border-radius: 10px;
    }

    .track-button {
        min-width: 150px;
        height: 50px;
        border-radius: 10px;
        font-weight: 800;
    }

    .track-order-card {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 20px;
        margin-top: 18px;
    }

    .track-status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 999px;
        background: #dcfce7;
        color: #166534;
        font-weight: 800;
        font-size: 13px;
    }

    .track-meta {
        color: #64748b;
        font-size: 14px;
        line-height: 1.8;
    }

    @media (max-width: 575px) {
        .track-box {
            padding: 24px 18px;
        }

        .track-form-row {
            display: block !important;
        }

        .track-button {
            width: 100%;
            margin-top: 10px;
        }
    }
</style>
@endpush

@section('content')
<section class="track-page">
    <div class="container">
        <div class="track-box">
            <h1 class="track-title">অর্ডার ট্র্যাক করুন</h1>
            <p class="track-subtitle">অর্ডারে ব্যবহার করা ১১ সংখ্যার ফোন নম্বরটি লিখুন।</p>

            <form action="{{ route('order.track.search') }}" method="POST">
                @csrf
                <div class="d-flex track-form-row">
                    <input
                        type="tel"
                        name="phone"
                        value="{{ old('phone', $searchedPhone) }}"
                        class="form-control track-input mr-sm-2 @error('phone') is-invalid @enderror"
                        placeholder="01XXXXXXXXX"
                        maxlength="11"
                        inputmode="numeric"
                        pattern="01[0-9]{9}"
                        autocomplete="tel"
                        required
                    >

                    <button type="submit" class="btn btn-success track-button">
                        <i class="fas fa-search mr-1"></i> Track Order
                    </button>
                </div>

                @error('phone')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </form>

            @if($orders !== null)
                @if($orders->isEmpty())
                    <div class="alert alert-warning mt-4 mb-0">
                        এই ফোন নম্বরে কোনো অর্ডার পাওয়া যায়নি।
                    </div>
                @else
                    <div class="mt-4">
                        <div class="font-weight-bold text-muted">
                            সর্বশেষ {{ $orders->count() }}টি অর্ডার
                        </div>

                        @foreach($orders as $order)
                            @php
                                $isPathaoOrder = strtolower((string) $order->courier_service) === 'pathao'
                                    || filled($order->pathao_consignment_id);

                                $rawCourierStatus = $isPathaoOrder
                                    ? $order->pathao_status
                                    : $order->steadfast_status;

                                $courierStatus = $rawCourierStatus
                                    ? ucwords(str_replace('_', ' ', $rawCourierStatus))
                                    : 'Not available yet';

                                $courierStatusLabel = $isPathaoOrder
                                    ? 'Pathao Status'
                                    : 'SteadFast Status';

                                $trackingCode = $isPathaoOrder
                                    ? $order->pathao_consignment_id
                                    : $order->steadfast_tracking_code;

                                $localStatus = $localStatusLabels[$order->order_status]
                                    ?? ucwords(str_replace('_', ' ', (string) $order->order_status));
                            @endphp

                            <div class="track-order-card">
                                <div class="d-flex justify-content-between align-items-start flex-wrap">
                                    <div>
                                        <div class="font-weight-bold text-dark">
                                            Invoice: {{ $order->invoice_id }}
                                        </div>
                                        <div class="track-meta">
                                            Order Date: {{ optional($order->local_created_at)->format('d M Y, h:i A') ?: '-' }}
                                        </div>
                                    </div>

                                    <span class="track-status mt-2 mt-sm-0">{{ $localStatus }}</span>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-6 track-meta">
                                        <strong>Courier:</strong> {{ $order->courier_name }}<br>
                                        <strong>{{ $courierStatusLabel }}:</strong> {{ $courierStatus }}<br>
                                        <strong>Tracking Code:</strong> {{ $trackingCode ?: '-' }}
                                    </div>
                                    <div class="col-md-6 track-meta mt-2 mt-md-0">
                                        <strong>Amount:</strong> ৳{{ number_format((float) $order->total_amount, 2) }}<br>
                                        <strong>Payment:</strong> {{ ucwords(str_replace('_', ' ', (string) $order->payment_status)) }}<br>
                                        <strong>Items:</strong>
                                        {{ $order->items->map(fn ($item) => $item->quantity . '× ' . $item->product_name)->implode(', ') ?: '-' }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</section>
@endsection
