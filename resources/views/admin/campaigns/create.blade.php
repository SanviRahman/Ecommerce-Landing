@extends('adminlte::page')

@section('title', $title ?? 'Landing Page Create')

@section('plugins.Select2', true)

@section('css')
    <style>
        .page-title-box {
            background: #ffffff;
            border-radius: 12px;
            padding: 18px 22px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            margin-bottom: 18px;
        }

        .page-title-box h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
        }

        .campaign-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .campaign-card .card-header {
            background: #ffffff;
            border-bottom: 1px solid #edf2f7;
            padding: 18px 22px;
        }

        .campaign-card .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .campaign-card .card-header p {
            margin-bottom: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .campaign-card .card-body {
            padding: 24px;
        }

        .select2-container--default .select2-selection--multiple {
            min-height: 42px;
            border-color: #ced4da;
            border-radius: 6px;
        }

        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #6c63ff;
            box-shadow: 0 0 0 0.15rem rgba(108, 99, 255, 0.15);
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #6c63ff;
            border-color: #6c63ff;
            color: #ffffff;
            border-radius: 4px;
            padding: 2px 8px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #ffffff;
            margin-right: 5px;
        }

        .form-control {
            border-radius: 6px;
            min-height: 42px;
        }

        .form-group label {
            font-weight: 600;
            color: #374151;
        }
    </style>
@stop

@section('content_header')
    <div class="page-title-box d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0">{{ $title ?? 'Landing Page Create' }}</h1>
            <small class="text-muted">Create campaign landing page with products, images and order form.</small>
        </div>

        <a href="{{ route('admin.campaigns.index') }}" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-list mr-1"></i> Manage
        </a>
    </div>
@stop

@section('content')
    <div class="card campaign-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-bullhorn text-primary mr-1"></i>
                Campaign Information
            </h3>
            <p>
                Product field will open only after clicking. You can select multiple products.
            </p>
        </div>

        <div class="card-body">
            @include('admin.campaigns.partials.form', [
                'campaign' => $campaign,
                'products' => $products,
                'selectedProducts' => $selectedProducts,
                'isEdit' => false,
                'action' => $action,
            ])
        </div>
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function () {
            $('.select2-products').select2({
                placeholder: 'Click here and select products',
                allowClear: true,
                width: '100%',
                closeOnSelect: false
            });
        });
    </script>
@stop