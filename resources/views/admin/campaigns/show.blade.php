@extends('adminlte::page')

@section('title', $title ?? 'Campaign Details')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">{{ $title ?? 'Campaign Details' }}</h1>

        <div>
            <a href="{{ route('campaign.show', $campaign->slug) }}"
               target="_blank"
               class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-eye mr-1"></i> View Landing Page
            </a>

            <a href="{{ route('admin.campaigns.edit', $campaign->id) }}"
               class="btn btn-info rounded-pill px-4">
                <i class="fas fa-edit mr-1"></i> Edit
            </a>

            <a href="{{ route('admin.campaigns.index') }}"
               class="btn btn-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>
@stop

@section('content')

    <div class="row">

        {{-- Campaign Basic Info --}}
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-bullhorn mr-1"></i> Campaign Information
                    </h3>
                </div>

                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 220px;">Landing Page Title</th>
                            <td>{{ $campaign->title }}</td>
                        </tr>

                        <tr>
                            <th>Slug</th>
                            <td>
                                <span class="badge badge-light">
                                    {{ $campaign->slug }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <th>Public URL</th>
                            <td>
                                <a href="{{ route('campaign.show', $campaign->slug) }}" target="_blank">
                                    {{ route('campaign.show', $campaign->slug) }}
                                </a>
                            </td>
                        </tr>

                        <tr>
                            <th>Campaign Type</th>
                            <td>{{ ucfirst($campaign->campaign_type ?? 'single') }}</td>
                        </tr>

                        <tr>
                            <th>Offer Text</th>
                            <td>{{ $campaign->offer_text ?? 'N/A' }}</td>
                        </tr>

                        <tr>
                            <th>Old Price</th>
                            <td>
                                @if ($campaign->old_price)
                                    ৳{{ number_format($campaign->old_price) }}
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>New Price</th>
                            <td>
                                @if ($campaign->new_price)
                                    ৳{{ number_format($campaign->new_price) }}
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>Button Text</th>
                            <td>{{ $campaign->button_text ?? 'অর্ডার করুন' }}</td>
                        </tr>

                        <tr>
                            <th>Bulk Order</th>
                            <td>
                                @if ($campaign->enable_bulk_order)
                                    <span class="badge badge-success">Enabled</span>
                                @else
                                    <span class="badge badge-secondary">Disabled</span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>Status</th>
                            <td>
                                @if ($campaign->status)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>Created At</th>
                            <td>{{ optional($campaign->created_at)->format('d M Y, h:i A') }}</td>
                        </tr>

                        <tr>
                            <th>Updated At</th>
                            <td>{{ optional($campaign->updated_at)->format('d M Y, h:i A') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            {{-- Description --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-align-left mr-1"></i> Descriptions
                    </h3>
                </div>

                <div class="card-body">
                    <h5>Short Description</h5>
                    <div class="border rounded p-3 mb-4">
                        @if ($campaign->short_description)
                            {!! $campaign->short_description !!}
                        @else
                            <span class="text-muted">No short description added.</span>
                        @endif
                    </div>

                    <h5>Full Description</h5>
                    <div class="border rounded p-3">
                        @if ($campaign->full_description)
                            {!! $campaign->full_description !!}
                        @else
                            <span class="text-muted">No full description added.</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Products --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-box mr-1"></i> Campaign Products
                    </h3>
                </div>

                <div class="card-body">
                    @if ($campaign->products && $campaign->products->count())
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 70px;">SL</th>
                                        <th>Product Name</th>
                                        <th style="width: 130px;">Product Price</th>
                                        <th style="width: 150px;">Campaign Price</th>
                                        <th style="width: 120px;">Default</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($campaign->products as $product)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>

                                            <td>
                                                <strong>{{ $product->name }}</strong>
                                            </td>

                                            <td>
                                                ৳{{ number_format($product->new_price ?? 0) }}
                                            </td>

                                            <td>
                                                ৳{{ number_format($product->pivot->campaign_price ?? $product->new_price ?? 0) }}
                                            </td>

                                            <td>
                                                @if ($product->pivot->is_default)
                                                    <span class="badge badge-success">Yes</span>
                                                @else
                                                    <span class="badge badge-secondary">No</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            No product attached with this campaign.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Media Preview --}}
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-images mr-1"></i> Campaign Images
                    </h3>
                </div>

                <div class="card-body">

                    <div class="mb-3">
                        <label class="font-weight-bold">Banner Image</label>
                        <div>
                            <img src="{{ $campaign->banner_image_url }}"
                                 alt="Banner Image"
                                 class="img-fluid img-thumbnail">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="font-weight-bold">Image One</label>
                        <div>
                            <img src="{{ $campaign->image_one_url }}"
                                 alt="Image One"
                                 class="img-fluid img-thumbnail">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="font-weight-bold">Image Two</label>
                        <div>
                            <img src="{{ $campaign->image_two_url }}"
                                 alt="Image Two"
                                 class="img-fluid img-thumbnail">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="font-weight-bold">Image Three</label>
                        <div>
                            <img src="{{ $campaign->image_three_url }}"
                                 alt="Image Three"
                                 class="img-fluid img-thumbnail">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="font-weight-bold">Review Image</label>
                        <div>
                            <img src="{{ $campaign->review_image_url }}"
                                 alt="Review Image"
                                 class="img-fluid img-thumbnail">
                        </div>
                    </div>

                </div>
            </div>

            {{-- SEO --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-search mr-1"></i> SEO Information
                    </h3>
                </div>

                <div class="card-body">
                    <p>
                        <strong>Meta Title:</strong><br>
                        {{ $campaign->meta_title ?? 'N/A' }}
                    </p>

                    <p class="mb-0">
                        <strong>Meta Description:</strong><br>
                        {{ $campaign->meta_description ?? 'N/A' }}
                    </p>
                </div>
            </div>
        </div>

    </div>

@stop