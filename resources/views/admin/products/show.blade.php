@extends('adminlte::page')

@section('title', $title ?? 'Product Details')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Product Details' }}</h1>

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
        <a href="{{ route('admin.products.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>

        @if(auth()->user()->isAdmin())
        <button type="button" class="btn btn-primary btn-sm btnEditFromShow"
            data-url="{{ route('admin.products.edit', $product->id) }}">
            <i class="fas fa-edit mr-1"></i> Edit
        </button>
        @endif
    </div>
</div>
@endsection

@section('content')

<div class="row">
    <div class="col-lg-8">
        {{-- Product Information --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-box text-primary mr-2"></i>
                    Product Information
                </h5>
            </div>

            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th width="30%">Name</th>
                            <td>{{ $product->name }}</td>
                        </tr>

                        <tr>
                            <th>Slug</th>
                            <td>/{{ $product->slug }}</td>
                        </tr>

                        <tr>
                            <th>Product Code</th>
                            <td>{{ $product->product_code }}</td>
                        </tr>

                        <tr>
                            <th>Category</th>
                            <td>{{ $product->category->name ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Brand</th>
                            <td>{{ $product->brand->name ?? 'No Brand' }}</td>
                        </tr>

                        <tr>
                            <th>Weight / Size</th>
                            <td>{{ $product->weight_size ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Status</th>
                            <td>
                                @if($product->status)
                                <span class="badge badge-success">Active</span>
                                @else
                                <span class="badge badge-warning">Inactive</span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>Flags</th>
                            <td>
                                @if($product->is_top_sale)
                                <span class="badge badge-warning">Top Sale</span>
                                @endif

                                @if($product->is_feature)
                                <span class="badge badge-primary">Featured</span>
                                @endif

                                @if($product->is_flash_sale)
                                <span class="badge badge-danger">Flash Sale</span>
                                @endif

                                @if(! $product->is_top_sale && ! $product->is_feature && ! $product->is_flash_sale)
                                <span class="text-muted">--</span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>Short Description</th>
                            <td>{{ $product->short_description ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Full Description</th>
                            <td>{!! nl2br(e($product->full_description ?? '-')) !!}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Gallery --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-images text-info mr-2"></i>
                    Product Gallery
                </h5>
            </div>

            <div class="card-body">
                <div class="row">
                    @forelse($product->getMedia('product_gallery') as $media)
                    <div class="col-md-3 mb-3">
                        <a href="{{ $media->getUrl() }}" target="_blank">
                            <img src="{{ $media->getUrl() }}" class="rounded border"
                                style="width: 100%; height: 130px; object-fit: cover;">
                        </a>
                    </div>
                    @empty
                    <div class="col-12 text-muted">
                        No gallery images found.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Thumbnail --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body text-center">
                <img src="{{ $product->thumbnail }}" class="rounded border mb-3"
                    style="width: 220px; height: 220px; object-fit: cover;"
                    onerror="this.onerror=null;this.src='{{ asset('vendor/adminlte/dist/img/no-image.png') }}';">

                <h5 class="font-weight-bold">{{ $product->name }}</h5>
                <p class="text-muted mb-0">{{ $product->product_code }}</p>
            </div>
        </div>

        {{-- Price & Stock --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    Price & Stock
                </h5>
            </div>

            <div class="card-body">
                <p>
                    <strong>Purchase Price:</strong>
                    {{ number_format($product->purchase_price ?? 0, 2) }}
                </p>

                <p>
                    <strong>Old Price:</strong>
                    {{ $product->old_price ? number_format($product->old_price, 2) : '-' }}
                </p>

                <p>
                    <strong>New Price:</strong>
                    {{ number_format($product->new_price ?? 0, 2) }}
                </p>

                <p>
                    <strong>Discount Amount:</strong>
                    {{ number_format($product->discount_amount ?? 0, 2) }}
                </p>

                <p>
                    <strong>Discount Percent:</strong>
                    {{ $product->discount_percent ?? 0 }}%
                </p>

                <p>
                    <strong>Stock:</strong>
                    {{ $product->stock }}
                </p>

                <p class="mb-0">
                    <strong>Sold:</strong>
                    {{ $product->sold_quantity }}
                </p>
            </div>
        </div>

        {{-- Campaigns --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    Campaigns
                </h5>
            </div>

            <div class="card-body">
                @forelse($product->campaigns as $campaign)
                <span class="badge badge-primary mb-1">
                    {{ $campaign->title }}
                </span>
                @empty
                <span class="text-muted">No campaign attached.</span>
                @endforelse
            </div>
        </div>

        {{-- SEO --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    SEO
                </h5>
            </div>

            <div class="card-body">
                <p>
                    <strong>Meta Title:</strong>
                    {{ $product->meta_title ?? '-' }}
                </p>

                <p class="mb-0">
                    <strong>Meta Description:</strong>
                    {{ $product->meta_description ?? '-' }}
                </p>
            </div>
        </div>
    </div>
</div>

@if(auth()->user()->isAdmin())
<div class="modal fade" id="ajaxModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title font-weight-bold text-primary">
                    Product Configuration
                </h6>

                <button type="button" class="close px-4 outline-none" data-dismiss="modal">
                    &times;
                </button>
            </div>

            <div class="modal-body p-4" id="modal-body"></div>
        </div>
    </div>
</div>
@endif

@endsection

@section('footer')
<strong>
    © Copyright 2026 All rights reserved |
    This website developed by
    <a href="https://sfashanto.netlify.app/" target="_blank">SFA Shanto</a>
</strong>
@endsection

@section('plugins.Sweetalert2', true)

@section('js')
<script>
$(document).ready(function() {
    $('.btnEditFromShow').on('click', function() {
        let url = $(this).data('url');

        $.get(url, function(res) {
            if (res.status && res.html) {
                $('#modal-body').html(res.html);
                $('#ajaxModal').modal('show');
            }
        });
    });

    $(document).on('submit', '#productForm', function(e) {
        e.preventDefault();

        let form = $(this);
        let formData = new FormData(this);

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,

            success: function(res) {
                $('#ajaxModal').modal('hide');

                Swal.fire({
                    icon: 'success',
                    title: res.message,
                    timer: 1200,
                    showConfirmButton: false
                });

                setTimeout(function() {
                    window.location.reload();
                }, 1200);
            },

            error: function(xhr) {
                let message = 'Validation error.';

                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
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
.breadcrumb-item+.breadcrumb-item::before {
    content: ">";
}

.swal2-container {
    z-index: 999999 !important;
}
</style>
@endsection