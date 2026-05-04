@extends('adminlte::page')

@section('title', 'Brand Details')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">Brand Details</h1>
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
            <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary btn-sm shadow-none">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        {{-- Brand Information Card --}}
        <div class="col-md-5 mb-4">
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 font-weight-bold"><i class="fas fa-tag mr-2 text-primary"></i> Brand Information</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-borderless table-striped mb-0">
                        <tbody>
                            <tr>
                                <th width="35%" class="text-muted px-4 py-3">Brand Name</th>
                                <td class="font-weight-bold px-4 py-3">{{ $brand->name }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Slug URL</th>
                                <td class="px-4 py-3"><code>/{{ $brand->slug }}</code></td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Status</th>
                                <td class="px-4 py-3">
                                    @if($brand->status)
                                        <span class="badge badge-success px-3 py-1 shadow-sm">Active</span>
                                    @else
                                        <span class="badge badge-warning px-3 py-1 shadow-sm">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Created At</th>
                                <td class="px-4 py-3 text-muted small">{{ $brand->created_at->format('d M, Y h:i A') }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Last Updated</th>
                                <td class="px-4 py-3 text-muted small">{{ $brand->updated_at->format('d M, Y h:i A') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Attached Products Card --}}
        <div class="col-md-7 mb-4">
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 font-weight-bold"><i class="fas fa-boxes mr-2 text-info"></i> Attached Products</h6>
                    <span class="badge badge-info px-3 py-1">{{ $brand->products()->count() }} Products</span>
                </div>
                <div class="card-body p-0 table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-uppercase text-muted">
                            <tr>
                                <th class="px-4">Product Name</th>
                                <th>Code</th>
                                <th>Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($brand->products as $product)
                                <tr>
                                    <td class="px-4">
                                        <a href="{{ route('admin.products.show', $product->id) }}" class="text-dark font-weight-bold text-decoration-none">
                                            {{ $product->name }}
                                        </a>
                                    </td>
                                    <td class="text-muted small">{{ $product->product_code }}</td>
                                    <td class="font-weight-bold text-success">{{ number_format($product->new_price, 2) }}</td>
                                    <td>
                                        @if($product->status)
                                            <span class="badge badge-success">Active</span>
                                        @else
                                            <span class="badge badge-warning">Inactive</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="fas fa-box-open fa-3x mb-3 text-light"></i><br>
                                        <span class="font-weight-bold">No products attached.</span><br>
                                        <small>There are no active products assigned to this brand yet.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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

@section('css')
    <style>
        .breadcrumb-item + .breadcrumb-item::before { content: ">"; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
    </style>
@endsection