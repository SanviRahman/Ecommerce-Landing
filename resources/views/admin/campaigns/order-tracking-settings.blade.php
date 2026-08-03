@extends('adminlte::page')

@section('title', $title ?? 'Order Tracking Settings')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0">Order Tracking Settings</h1>
            <small class="text-muted">Homepage tracking section and header menu visibility manage করুন।</small>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h3 class="card-title font-weight-bold mb-0">
                <i class="fas fa-search-location text-success mr-1"></i>
                Homepage Order Tracking
            </h3>
        </div>

        <div class="card-body">
            @if(! $campaign)
                <div class="alert alert-warning mb-0">
                    কোনো active homepage campaign পাওয়া যায়নি। আগে একটি campaign active/default করুন।
                </div>
            @else
                <div class="mb-4">
                    <div class="text-muted small">Current Homepage Campaign</div>
                    <div class="font-weight-bold">{{ $campaign->title }}</div>
                </div>

                <form action="{{ route('admin.order-tracking-settings.update', $campaign) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <input type="hidden" name="order_tracking_section_status" value="0">

                    <div class="custom-control custom-switch mb-4">
                        <input type="checkbox"
                               class="custom-control-input"
                               id="order_tracking_section_status"
                               name="order_tracking_section_status"
                               value="1"
                               @checked(old('order_tracking_section_status', $campaign->order_tracking_section_status ?? true))>
                        <label class="custom-control-label font-weight-bold" for="order_tracking_section_status">
                            Order Tracking Section + Header Active
                        </label>
                    </div>

                    <div class="alert alert-light border">
                        Switch OFF করলে homepage-এর tracking form এবং header-এর “অর্ডার ট্র্যাক করুন” link দুটোই hide হবে।
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save mr-1"></i> Save Settings
                    </button>
                </form>
            @endif
        </div>
    </div>
@stop
