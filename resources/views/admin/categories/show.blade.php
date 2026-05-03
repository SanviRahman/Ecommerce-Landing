@extends('adminlte::page')

@section('title', $title ?? 'Category Details')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">{{ $title ?? 'Category Details' }}</h1>

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
            <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>

            <button type="button"
                    class="btn btn-primary btn-sm btnEditFromShow"
                    data-url="{{ route('admin.categories.edit', $category->id) }}">
                <i class="fas fa-edit mr-1"></i> Edit
            </button>
        </div>
    </div>
@endsection

@section('content')

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        <i class="fas fa-list text-primary mr-2"></i>
                        Category Information
                    </h5>
                </div>

                <div class="card-body">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th width="30%">Name</th>
                                <td>{{ $category->name }}</td>
                            </tr>

                            <tr>
                                <th>Slug</th>
                                <td>/{{ $category->slug }}</td>
                            </tr>

                            <tr>
                                <th>Status</th>
                                <td>
                                    @if($category->status)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-warning">Inactive</span>
                                    @endif
                                </td>
                            </tr>

                            <tr>
                                <th>Front View</th>
                                <td>
                                    @if($category->is_front_view)
                                        <span class="badge badge-primary">Yes</span>
                                    @else
                                        <span class="badge badge-light border text-muted">No</span>
                                    @endif
                                </td>
                            </tr>

                            <tr>
                                <th>Total Products</th>
                                <td>{{ $category->products_count ?? 0 }}</td>
                            </tr>

                            <tr>
                                <th>Active Products</th>
                                <td>{{ $category->active_products_count ?? 0 }}</td>
                            </tr>

                            <tr>
                                <th>Created At</th>
                                <td>{{ $category->created_at ? $category->created_at->format('d M, Y h:i A') : '-' }}</td>
                            </tr>

                            <tr>
                                <th>Updated At</th>
                                <td>{{ $category->updated_at ? $category->updated_at->format('d M, Y h:i A') : '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        <i class="fas fa-box text-info mr-2"></i>
                        Category Products
                    </h5>
                </div>

                <div class="card-body">
                    @if($category->products()->exists())
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Code</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach($category->products()->latest()->take(10)->get() as $product)
                                        <tr>
                                            <td>{{ $product->name }}</td>
                                            <td>{{ $product->product_code }}</td>
                                            <td>{{ number_format($product->new_price ?? 0, 2) }}</td>
                                            <td>{{ $product->stock }}</td>
                                            <td>
                                                @if($product->status)
                                                    <span class="badge badge-success">Active</span>
                                                @else
                                                    <span class="badge badge-warning">Inactive</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if(($category->products_count ?? 0) > 10)
                            <p class="text-muted small mb-0">
                                Showing latest 10 products only.
                            </p>
                        @endif
                    @else
                        <p class="text-muted mb-0">No products found in this category.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body text-center">
                    <img src="{{ $category->image }}"
                         class="rounded border mb-3"
                         style="width: 220px; height: 220px; object-fit: cover;">

                    <h5 class="font-weight-bold">{{ $category->name }}</h5>
                    <p class="text-muted mb-0">/{{ $category->slug }}</p>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        Summary
                    </h5>
                </div>

                <div class="card-body">
                    <p>
                        <strong>Total Products:</strong>
                        {{ $category->products_count ?? 0 }}
                    </p>

                    <p>
                        <strong>Active Products:</strong>
                        {{ $category->active_products_count ?? 0 }}
                    </p>

                    <p>
                        <strong>Status:</strong>
                        {{ $category->status ? 'Active' : 'Inactive' }}
                    </p>

                    <p class="mb-0">
                        <strong>Front View:</strong>
                        {{ $category->is_front_view ? 'Yes' : 'No' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ajaxModal" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title font-weight-bold text-primary">
                        Category Configuration
                    </h6>

                    <button type="button" class="close px-4 outline-none" data-dismiss="modal">
                        &times;
                    </button>
                </div>

                <div class="modal-body p-4" id="modal-body"></div>
            </div>
        </div>
    </div>

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
        $(document).ready(function () {
            $('.btnEditFromShow').on('click', function () {
                let url = $(this).data('url');

                $.ajax({
                    url: url,
                    type: "GET",
                    dataType: "json",
                    success: function (res) {
                        if (res.status && res.html) {
                            $('#modal-body').html(res.html);
                            $('#ajaxModal').modal('show');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Category edit form load failed.', 'error');
                    }
                });
            });

            $(document).on('submit', '#categoryForm', function (e) {
                e.preventDefault();

                let form = $(this);
                let formData = new FormData(this);

                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,

                    success: function (res) {
                        if (res.status) {
                            $('#ajaxModal').modal('hide');

                            Swal.fire({
                                icon: 'success',
                                title: res.message,
                                timer: 1200,
                                showConfirmButton: false
                            });

                            setTimeout(function () {
                                window.location.reload();
                            }, 1200);
                        } else {
                            Swal.fire('Error', res.message || 'Category update failed.', 'error');
                        }
                    },

                    error: function (xhr) {
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
        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
        }

        .swal2-container {
            z-index: 999999 !important;
        }
    </style>
@endsection