@extends('adminlte::page')

@section('title', $title ?? 'Tracking Pixel Details')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Tracking Pixel Details' }}</h1>

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
        <a href="{{ route('admin.tracking-pixels.edit', $trackingPixel->id) }}" class="btn btn-info">
            <i class="fas fa-edit mr-1"></i> Edit
        </a>

        <a href="{{ route('admin.tracking-pixels.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
    </div>
</div>
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th style="width: 180px;">Name</th>
                <td>{{ $trackingPixel->name ?? 'N/A' }}</td>
            </tr>

            <tr>
                <th>Platform</th>
                <td>{{ $platforms[$trackingPixel->platform] ?? ucfirst($trackingPixel->platform) }}</td>
            </tr>

            <tr>
                <th>Pixel ID</th>
                <td><code>{{ $trackingPixel->pixel_id }}</code></td>
            </tr>

            <tr>
                <th>Status</th>
                <td>
                    @if ($trackingPixel->status)
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-danger">Inactive</span>
                    @endif
                </td>
            </tr>

            <tr>
                <th>Created At</th>
                <td>{{ optional($trackingPixel->created_at)->format('d M Y, h:i A') }}</td>
            </tr>
        </table>

        <h5 class="mt-4">Full Script Code</h5>

        <pre class="bg-dark text-light p-3 rounded" style="max-height: 500px; overflow:auto;"><code>{{ $trackingPixel->script_code }}</code></pre>
    </div>
</div>
@endsection