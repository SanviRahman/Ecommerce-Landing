@extends('adminlte::page')

@section('title', 'Dashboard')

@section('content_header')
    <h1>Dashboard</h1>
@endsection

@section('content')

    @if(isset($isEmployee) && $isEmployee)
        <div class="alert alert-info">
            <i class="fas fa-info-circle mr-1"></i>
            You are viewing your assigned orders only.
        </div>
    @endif

    <div class="row">
        {{-- Total Orders --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $totalOrders ?? 0 }}</h3>
                    <p>Total Orders</p>
                </div>

                <div class="icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>

                <a href="{{ route('admin.orders.index') }}" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        {{-- Pending Orders --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $pendingOrders ?? 0 }}</h3>
                    <p>Pending Orders</p>
                </div>

                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>

                <a href="{{ route('admin.orders.pending') }}" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        {{-- Confirmed Orders --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $confirmedOrders ?? 0 }}</h3>
                    <p>Confirmed Orders</p>
                </div>

                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>

                <a href="{{ route('admin.orders.confirmed') }}" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        {{-- Processing Orders --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ $processingOrders ?? 0 }}</h3>
                    <p>Processing Orders</p>
                </div>

                <div class="icon">
                    <i class="fas fa-spinner"></i>
                </div>

                <a href="{{ route('admin.orders.processing') }}" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        {{-- Delivered Orders --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $deliveredOrders ?? 0 }}</h3>
                    <p>Delivered Orders</p>
                </div>

                <div class="icon">
                    <i class="fas fa-truck"></i>
                </div>

                <a href="{{ route('admin.orders.delivered') }}" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        {{-- Cancelled Orders --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $cancelledOrders ?? 0 }}</h3>
                    <p>Cancelled Orders</p>
                </div>

                <div class="icon">
                    <i class="fas fa-times-circle"></i>
                </div>

                <a href="{{ route('admin.orders.cancelled') }}" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        {{-- Total Products --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-dark">
                <div class="inner">
                    <h3>{{ $totalProducts ?? 0 }}</h3>
                    <p>Total Products</p>
                </div>

                <div class="icon">
                    <i class="fas fa-box"></i>
                </div>

                <a href="{{ route('admin.products.index') }}" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        {{-- User Role --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-light">
                <div class="inner">
                    <h3>{{ auth()->user()->role_text ?? ucfirst(auth()->user()->role ?? 'User') }}</h3>
                    <p>Logged In Role</p>
                </div>

                <div class="icon">
                    <i class="fas fa-user-shield"></i>
                </div>

                <a href="{{ route('admin.profile') }}" class="small-box-footer text-dark">
                    Profile <i class="fas fa-arrow-circle-right"></i>
                </a>
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