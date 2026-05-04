@extends('adminlte::page')

@section('title', $title ?? 'Edit Campaign')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">{{ $title ?? 'Edit Campaign' }}</h1>

        <a href="{{ route('admin.campaigns.index') }}" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-list mr-1"></i> Manage
        </a>
    </div>
@stop

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.campaigns.partials.form', [
                'campaign' => $campaign,
                'products' => $products,
                'selectedProducts' => $selectedProducts,
                'isEdit' => true,
                'action' => $action,
            ])
        </div>
    </div>
@stop