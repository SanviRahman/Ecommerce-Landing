@extends('adminlte::page')

@section('title', $title ?? 'Dashboard')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Dashboard' }}</h1>

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
</div>
@endsection

@section('content')

@if(isset($isEmployee) && $isEmployee)
    <div class="alert alert-info">
        <i class="fas fa-info-circle mr-1"></i>
        You are viewing your assigned orders only.
    </div>
@endif

<div class="card card-outline card-primary shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="card-title font-weight-bold">
            <i class="fas fa-sliders-h mr-2"></i>Dashboard Analytics Filters
        </h3>
    </div>

    <div class="card-body">
        <form id="filterForm">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Campaign</label>
                        <select name="campaign_id" id="campaign_id" class="form-control">
                            <option value="">All Campaigns</option>
                            @foreach($campaigns as $campaign)
                                <option value="{{ $campaign->id }}">{{ $campaign->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Date Filter</label>
                        <select name="date_filter" id="dateFilter" class="form-control">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="this_week">This Week</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_year">This Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-3" id="customDateRange" style="display:none;">
                    <div class="form-group">
                        <label>Custom Range</label>
                        <div class="input-group">
                            <input type="date" name="start_date" id="start_date" class="form-control">
                            <div class="input-group-prepend">
                                <span class="input-group-text">to</span>
                            </div>
                            <input type="date" name="end_date" id="end_date" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Order Status</label>
                        <select name="order_status" id="order_status" class="form-control">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Payment</label>
                        <select name="payment_status" id="payment_status" class="form-control">
                            <option value="">All Payment</option>
                            <option value="cod_pending">COD Pending</option>
                            <option value="paid">Paid</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Delivery Area</label>
                        <select name="delivery_area" id="delivery_area" class="form-control">
                            <option value="">All Area</option>
                            <option value="inside_dhaka">Inside Dhaka</option>
                            <option value="outside_dhaka">Outside Dhaka</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <button type="button" id="resetBtn" class="btn btn-light border btn-block">
                            <i class="fas fa-sync mr-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row" id="statsContainer">
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-info shadow-sm">
            <div class="inner">
                <h3 id="stat_totalOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Total Orders</p>
            </div>
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            <a href="{{ route('admin.orders.index') }}" class="small-box-footer">Orders <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-warning shadow-sm">
            <div class="inner">
                <h3 id="stat_pendingOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Pending Orders</p>
            </div>
            <div class="icon"><i class="fas fa-clock"></i></div>
            <a href="{{ route('admin.orders.pending') }}" class="small-box-footer">Pending <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-primary shadow-sm">
            <div class="inner">
                <h3 id="stat_confirmedOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Confirmed Orders</p>
            </div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <a href="{{ route('admin.orders.confirmed') }}" class="small-box-footer">Confirmed <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-secondary shadow-sm">
            <div class="inner">
                <h3 id="stat_processingOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Processing Orders</p>
            </div>
            <div class="icon"><i class="fas fa-spinner"></i></div>
            <a href="{{ route('admin.orders.processing') }}" class="small-box-footer">Processing <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-success shadow-sm">
            <div class="inner">
                <h3 id="stat_deliveredOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Delivered Orders</p>
            </div>
            <div class="icon"><i class="fas fa-truck"></i></div>
            <a href="{{ route('admin.orders.delivered') }}" class="small-box-footer">Delivered <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-danger shadow-sm">
            <div class="inner">
                <h3 id="stat_cancelledOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Cancelled Orders</p>
            </div>
            <div class="icon"><i class="fas fa-times-circle"></i></div>
            <a href="{{ route('admin.orders.cancelled') }}" class="small-box-footer">Cancelled <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-maroon shadow-sm">
            <div class="inner">
                <h3 id="stat_grossSales"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Gross Sales</p>
            </div>
            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
            <a href="{{ route('admin.reports.index') }}" class="small-box-footer">Reports <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-dark shadow-sm">
            <div class="inner">
                <h3 id="stat_totalProducts"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Total Products</p>
            </div>
            <div class="icon"><i class="fas fa-box"></i></div>
            <a href="{{ route('admin.products.index') }}" class="small-box-footer">Products <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header bg-white">
                <h3 class="card-title">Order Trend</h3>
            </div>
            <div class="card-body">
                <canvas id="orderTrendChart" height="220"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card card-outline card-success shadow-sm">
            <div class="card-header bg-white">
                <h3 class="card-title">Sales Trend</h3>
            </div>
            <div class="card-body">
                <canvas id="salesTrendChart" height="220"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header bg-white">
                <h3 class="card-title">Order Status</h3>
            </div>
            <div class="card-body">
                <canvas id="statusComparisonChart" height="240"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-outline card-warning shadow-sm">
            <div class="card-header bg-white">
                <h3 class="card-title">Payment Status</h3>
            </div>
            <div class="card-body">
                <canvas id="paymentComparisonChart" height="240"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-outline card-secondary shadow-sm">
            <div class="card-header bg-white">
                <h3 class="card-title">Campaign Performance</h3>
            </div>
            <div class="card-body">
                <canvas id="campaignPerformanceChart" height="240"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-6">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header bg-white">
                <h3 class="card-title">Recent Orders</h3>
            </div>
            <div class="card-body table-responsive p-0" id="recentOrdersContainer">
                <div class="text-center p-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card card-outline card-success shadow-sm">
            <div class="card-header bg-white">
                <h3 class="card-title">Recent Products</h3>
            </div>
            <div class="card-body table-responsive p-0" id="recentProductsContainer">
                <div class="text-center p-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-12">
        <div class="card card-outline card-secondary shadow-sm">
            <div class="card-header bg-white">
                <h3 class="card-title">Recent Campaigns</h3>
            </div>
            <div class="card-body table-responsive p-0" id="recentCampaignsContainer">
                <div class="text-center p-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                </div>
            </div>
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

@section('js')
<script src="{{ asset('vendor/chart.js/Chart.min.js') }}"></script>

<script>
$(function() {
    const lineOptions = {
        maintainAspectRatio: false,
        responsive: true,
        legend: { display: false },
        scales: {
            xAxes: [{ gridLines: { display: false } }],
            yAxes: [{
                gridLines: { color: 'rgba(0,0,0,0.06)' },
                ticks: { beginAtZero: true }
            }]
        }
    };

    const doughnutOptions = {
        maintainAspectRatio: false,
        responsive: true,
        legend: { display: true, position: 'bottom' }
    };

    function createLineChart(selector, color) {
        return new Chart($(selector).get(0).getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    backgroundColor: color.replace('1)', '0.18)'),
                    borderColor: color,
                    pointRadius: 3,
                    fill: true,
                    data: []
                }]
            },
            options: lineOptions
        });
    }

    function createBarChart(selector, color) {
        return new Chart($(selector).get(0).getContext('2d'), {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    backgroundColor: color,
                    borderColor: color,
                    data: []
                }]
            },
            options: lineOptions
        });
    }

    function createDoughnut(selector) {
        return new Chart($(selector).get(0).getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#0d6efd',
                        '#198754',
                        '#dc3545',
                        '#ffc107',
                        '#6c757d',
                        '#20c997',
                        '#6610f2',
                        '#fd7e14'
                    ]
                }]
            },
            options: doughnutOptions
        });
    }

    const orderTrendChart = createLineChart('#orderTrendChart', 'rgba(13,110,253,1)');
    const salesTrendChart = createLineChart('#salesTrendChart', 'rgba(25,135,84,1)');
    const statusComparisonChart = createDoughnut('#statusComparisonChart');
    const paymentComparisonChart = createDoughnut('#paymentComparisonChart');
    const campaignPerformanceChart = createBarChart('#campaignPerformanceChart', 'rgba(108,117,125,0.85)');

    function updateChart(chart, chartData) {
        if (! chartData) {
            return;
        }

        chart.data.labels = chartData.labels || [];
        chart.data.datasets[0].data = chartData.values || [];
        chart.update();
    }

    function loadingState() {
        $('#stat_totalOrders, #stat_pendingOrders, #stat_confirmedOrders, #stat_processingOrders, #stat_deliveredOrders, #stat_cancelledOrders, #stat_grossSales, #stat_totalProducts')
            .html('<i class="fas fa-spinner fa-spin"></i>');

        $('#recentOrdersContainer, #recentProductsContainer, #recentCampaignsContainer')
            .html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>');
    }

    function loadDashboardData() {
        loadingState();

        $.get('{{ route("admin.dashboard") }}', $('#filterForm').serialize(), function(response) {
            $('#stat_totalOrders').text(response.stats.totalOrders);
            $('#stat_pendingOrders').text(response.stats.pendingOrders);
            $('#stat_confirmedOrders').text(response.stats.confirmedOrders);
            $('#stat_processingOrders').text(response.stats.processingOrders);
            $('#stat_deliveredOrders').text(response.stats.deliveredOrders);
            $('#stat_cancelledOrders').text(response.stats.cancelledOrders);
            $('#stat_grossSales').text(response.stats.grossSales);
            $('#stat_totalProducts').text(response.stats.totalProducts);

            updateChart(orderTrendChart, response.charts.orderTrend);
            updateChart(salesTrendChart, response.charts.salesTrend);
            updateChart(statusComparisonChart, response.charts.statusComparison);
            updateChart(paymentComparisonChart, response.charts.paymentComparison);
            updateChart(campaignPerformanceChart, response.charts.campaignPerformance);

            $('#recentOrdersContainer').html(response.sections.recentOrders);
            $('#recentProductsContainer').html(response.sections.recentProducts);
            $('#recentCampaignsContainer').html(response.sections.recentCampaigns);
        }).fail(function() {
            $('#recentOrdersContainer, #recentProductsContainer, #recentCampaignsContainer')
                .html('<div class="text-center p-4 text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading data</div>');

            $('#stat_totalOrders, #stat_pendingOrders, #stat_confirmedOrders, #stat_processingOrders, #stat_deliveredOrders, #stat_cancelledOrders, #stat_grossSales, #stat_totalProducts')
                .text('Error');
        });
    }

    $('#dateFilter').on('change', function() {
        $('#customDateRange').toggle($(this).val() === 'custom');

        if ($(this).val() !== 'custom') {
            loadDashboardData();
        }
    });

    $('#campaign_id, #order_status, #payment_status, #delivery_area').on('change', loadDashboardData);

    $('#start_date, #end_date').on('change', function() {
        if ($('#start_date').val() && $('#end_date').val()) {
            loadDashboardData();
        }
    });

    $('#resetBtn').on('click', function() {
        $('#filterForm')[0].reset();
        $('#customDateRange').hide();
        loadDashboardData();
    });

    loadDashboardData();
});
</script>
@endsection

@section('css')
<style>
.breadcrumb-item+.breadcrumb-item::before {
    content: ">";
}

.card-title {
    font-weight: 600;
}

.small-box h3 {
    font-size: 1.8rem;
}
</style>
@endsection