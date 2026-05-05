@extends('adminlte::page')

@section('title', $title ?? 'Create Tracking Pixel')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Create Tracking Pixel' }}</h1>

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

    <a href="{{ route('admin.tracking-pixels.index') }}" class="btn btn-primary rounded-pill px-4">
        <i class="fas fa-list mr-1"></i> Manage
    </a>
</div>
@endsection

@section('content')
<div class="card border-0 shadow-sm" style="border-radius: 12px;">
    <div class="card-header bg-white">
        <h5 class="mb-0 font-weight-bold">
            <i class="fas fa-code text-primary mr-1"></i>
            Pixel Script Information
        </h5>
        <small class="text-muted">
            Paste one or multiple full Meta Pixel scripts. Each valid script will be stored separately.
        </small>
    </div>

    <div class="card-body">
        @include('admin.tracking-pixels.partials.form', [
            'trackingPixel' => $trackingPixel,
            'platforms' => $platforms,
            'isEdit' => false,
            'action' => $action,
        ])
    </div>
</div>
@endsection