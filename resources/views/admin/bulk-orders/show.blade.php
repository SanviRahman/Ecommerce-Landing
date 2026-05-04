@extends('adminlte::page')

@section('title', 'Bulk Order Details')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">Bulk Order Details</h1>
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
            <button onclick="history.back()" class="btn btn-outline-secondary btn-sm shadow-none">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </button>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        {{-- Request Information --}}
        <div class="col-md-7 mb-4">
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 font-weight-bold"><i class="fas fa-info-circle mr-2 text-primary"></i> Request Information</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-borderless table-striped mb-0">
                        <tbody>
                            <tr>
                                <th width="35%" class="text-muted px-4 py-3">Request ID</th>
                                <td class="font-weight-bold px-4 py-3 text-primary">#{{ str_pad($bulkOrder->id, 5, '0', STR_PAD_LEFT) }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Submitted At</th>
                                <td class="px-4 py-3">{{ $bulkOrder->created_at->format('d M, Y h:i A') }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Customer Name</th>
                                <td class="font-weight-bold px-4 py-3">{{ $bulkOrder->customer_name }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Phone Number</th>
                                <td class="px-4 py-3"><a href="tel:{{ $bulkOrder->phone }}">{{ $bulkOrder->phone }}</a></td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Company Name</th>
                                <td class="px-4 py-3">{{ $bulkOrder->company_name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Address</th>
                                <td class="px-4 py-3">{{ $bulkOrder->address ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Target Product</th>
                                <td class="font-weight-bold px-4 py-3 text-dark">{{ $bulkOrder->product_name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Expected Quantity</th>
                                <td class="px-4 py-3"><span class="badge badge-info px-2 py-1">{{ $bulkOrder->expected_quantity }}</span></td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Source Campaign</th>
                                <td class="px-4 py-3">
                                    @if($bulkOrder->campaign)
                                        <a href="{{ route('admin.campaigns.show', $bulkOrder->campaign_id) }}">{{ $bulkOrder->campaign->title }}</a>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Requirement Message --}}
            @if($bulkOrder->requirement_message)
                <div class="card shadow-sm border-0 mt-4" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 font-weight-bold"><i class="fas fa-comment-alt mr-2 text-info"></i> Customer Message</h6>
                    </div>
                    <div class="card-body p-4 bg-light">
                        <p class="mb-0 font-italic text-muted">"{{ $bulkOrder->requirement_message }}"</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Action & Status Management --}}
        <div class="col-md-5 mb-4">
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 font-weight-bold"><i class="fas fa-tasks mr-2 text-warning"></i> Manage Request</h6>
                </div>
                <div class="card-body p-4">
                    <form id="statusUpdateForm" action="{{ route('admin.bulk-orders.update_status', $bulkOrder->id) }}">
                        @csrf
                        @method('PATCH')

                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-muted small text-uppercase">Current Status</label>
                            <select name="status" class="form-control border-light shadow-sm" required>
                                <option value="new" {{ $bulkOrder->status === 'new' ? 'selected' : '' }}>New Request</option>
                                <option value="contacted" {{ $bulkOrder->status === 'contacted' ? 'selected' : '' }}>Contacted</option>
                                <option value="quoted" {{ $bulkOrder->status === 'quoted' ? 'selected' : '' }}>Quoted / Priced</option>
                                <option value="confirmed" {{ $bulkOrder->status === 'confirmed' ? 'selected' : '' }}>Confirmed / Converted</option>
                                <option value="cancelled" {{ $bulkOrder->status === 'cancelled' ? 'selected' : '' }}>Cancelled / Rejected</option>
                            </select>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-muted small text-uppercase">Admin Notes</label>
                            <textarea name="admin_note" class="form-control border-light shadow-sm" rows="5" placeholder="Add internal notes about this deal here...">{{ $bulkOrder->admin_note }}</textarea>
                            <small class="text-muted mt-1 d-block">These notes are strictly internal and hidden from the customer.</small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block shadow-sm font-weight-bold py-2">
                            <i class="fas fa-save mr-1"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <strong>
        © Copyright 2026 All rights reserved | This website developed by <a href="https://sfashanto.netlify.app/" target="_blank">SFA Shanto</a>
    </strong>
@endsection

@section('plugins.Sweetalert2', true)

@section('js')
    <script>
        $(document).ready(function () {
            function showToast(type, message) {
                Swal.fire({
                    icon: type,
                    type: type,
                    title: message,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true,
                    toast: true
                });
            }

            $('#statusUpdateForm').on('submit', function (e) {
                e.preventDefault();
                let form = $(this);
                let btn = form.find('button[type="submit"]');

                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: form.serialize(),
                    beforeSend: function () {
                        btn.prop('disabled', true).prepend('<i class="fas fa-spinner fa-spin mr-1"></i> ');
                    },
                    complete: function() { btn.prop('disabled', false).find('i.fa-spinner').remove(); },
                    success: function (res) {
                        if (res.status) {
                            showToast('success', res.message);
                        }
                    },
                    error: function (xhr) {
                        let message = 'Update failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        Swal.fire('Error', message, 'error');
                    }
                });
            });
        });
    </script>
@endsection

@section('css')
    <style>
        .breadcrumb-item + .breadcrumb-item::before { content: ">"; }
        .table-borderless th { border-bottom: 1px solid #f1f3f5; }
        .table-borderless td { border-bottom: 1px solid #f1f3f5; }
    </style>
@endsection