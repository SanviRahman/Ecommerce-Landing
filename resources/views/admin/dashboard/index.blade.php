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

<div class="card card-outline card-primary shadow-sm mb-3 dashboard-filter-card">
    <div class="card-header bg-white">
        <h3 class="card-title font-weight-bold">
            <i class="fas fa-sliders-h mr-2"></i> Dashboard Analytics Filters
        </h3>
    </div>

    <div class="card-body">
        <form id="filterForm">
            <div class="row align-items-end">
                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label>Campaign</label>
                        <select name="campaign_id" id="campaign_id" class="form-control dashboard-filter-input">
                            <option value="">All Campaigns</option>
                            @foreach($campaigns as $campaign)
                                <option value="{{ $campaign->id }}">{{ $campaign->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-2 col-sm-6">
                    <div class="form-group">
                        <label>Date Filter</label>
                        <select name="date_filter" id="dateFilter" class="form-control dashboard-filter-input">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last_week">Last 7 Days</option>
                            <option value="this_week">This Week</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_year">This Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-3 col-sm-12" id="customDateRange" style="display:none;">
                    <div class="form-group">
                        <label>Custom Range</label>
                        <div class="input-group">
                            <input type="date" name="start_date" id="start_date" class="form-control dashboard-filter-input">
                            <div class="input-group-prepend input-group-append">
                                <span class="input-group-text">to</span>
                            </div>
                            <input type="date" name="end_date" id="end_date" class="form-control dashboard-filter-input">
                        </div>
                    </div>
                </div>

                <div class="col-md-2 col-sm-6">
                    <div class="form-group">
                        <label>Order Status</label>
                        <select name="order_status" id="order_status" class="form-control dashboard-filter-input">
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

                <div class="col-md-2 col-sm-6">
                    <div class="form-group">
                        <label>Payment</label>
                        <select name="payment_status" id="payment_status" class="form-control dashboard-filter-input">
                            <option value="">All Payment</option>
                            <option value="cod_pending">COD Pending</option>
                            <option value="paid">Paid</option>
                            <option value="collected">Collected</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-2 col-sm-6">
                    <div class="form-group">
                        <label>Delivery Area</label>
                        <select name="delivery_area" id="delivery_area" class="form-control dashboard-filter-input">
                            <option value="">All Area</option>
                            <option value="inside_dhaka">Inside Dhaka</option>
                            <option value="outside_dhaka">Outside Dhaka</option>
                            <option value="free_delivery">Free Delivery</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-2 col-sm-6">
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

<div class="card card-outline card-info shadow-sm mb-3">
    <div class="card-header bg-white">
        <h3 class="card-title font-weight-bold">
            <i class="fas fa-chart-pie text-info mr-2"></i> Today's Report Summary
        </h3>
        <small class="d-block text-muted" id="todayReportRangeText">
            Daily order, invoice and delivery summary (12:00 AM - 11:59 PM)
        </small>
    </div>

    <div class="card-body">
        <div class="row today-report-grid">
            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-primary"><i class="fas fa-shopping-cart"></i></span>
                    <div>
                        <strong>Total Orders</strong>
                        <h5 id="today_todaysOrder">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-secondary"><i class="fas fa-cart-plus"></i></span>
                    <div>
                        <strong>New Order</strong>
                        <h5 id="today_newOrder">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-success"><i class="fas fa-check-circle"></i></span>
                    <div>
                        <strong>Complete Order</strong>
                        <h5 id="today_completedOrder">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-success"><i class="fas fa-file-invoice-dollar"></i></span>
                    <div>
                        <strong>Complete Invoice</strong>
                        <h5 id="today_completedInvoice">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-info"><i class="fas fa-truck-loading"></i></span>
                    <div>
                        <strong>Shipped</strong>
                        <h5 id="today_shippedOrders">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-primary"><i class="fas fa-truck"></i></span>
                    <div>
                        <strong>Delivered</strong>
                        <h5 id="today_deliveredOrder">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-danger"><i class="fas fa-times-circle"></i></span>
                    <div>
                        <strong>Cancelled</strong>
                        <h5 id="today_cancelled">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-warning"><i class="fas fa-clock"></i></span>
                    <div>
                        <strong>Pending</strong>
                        <h5 id="today_pendingOrder">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-info"><i class="fas fa-spinner"></i></span>
                    <div>
                        <strong>Incompleted Order</strong>
                        <h5 id="today_incompletedOrder">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-dark"><i class="fas fa-box-open"></i></span>
                    <div>
                        <strong>Stock Out</strong>
                        <h5 id="today_stockOutOrder">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-primary"><i class="fas fa-list-ol"></i></span>
                    <div>
                        <strong>Order List 1</strong>
                        <h5 id="today_orderList1">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-success"><i class="fas fa-list-ol"></i></span>
                    <div>
                        <strong>Order List 2</strong>
                        <h5 id="today_orderList2">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item">
                    <span class="today-icon bg-danger"><i class="fas fa-file-invoice"></i></span>
                    <div>
                        <strong>Incompleted Invoice</strong>
                        <h5 id="today_incompletedInvoice">0</h5>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="today-report-item mb-0">
                    <span class="today-icon bg-secondary"><i class="fas fa-cash-register"></i></span>
                    <div>
                        <strong>Total Checkout</strong>
                        <h5 id="today_totalCheckout">0</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-primary shadow-sm mb-3">
    <div class="card-header bg-white">
        <h3 class="card-title font-weight-bold">Product Sale Report</h3>
        <small class="d-block text-muted">
            Daily product order summary (12:00 AM - 11:59 PM). Use Show 10 or All to control visible rows.
        </small>
    </div>

    <div class="card-body" id="productSaleReportContainer">
        <div class="text-center p-4">
            <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
        </div>
    </div>
</div>

<div class="card card-outline card-secondary shadow-sm mb-3" id="userOrderReportCard">
    <div class="card-header bg-white">
        <h3 class="card-title font-weight-bold">User Order Report</h3>
        <small class="d-block text-muted">
            Date range will follow the Dashboard Analytics Filters above. Today/All Time default uses 12:00 AM - 11:59 PM.
        </small>
    </div>

    <div class="card-body border-bottom">
        <form id="userReportForm">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label>Select User</label>
                        <select name="report_user_id" id="report_user_id" class="form-control">
                            <option value="">All Users</option>
                            @foreach($users as $reportUser)
                                <option value="{{ $reportUser->id }}">{{ $reportUser->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card-body table-responsive p-0" id="userOrderReportContainer">
        <div class="text-center p-4">
            <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
        </div>
    </div>
</div>


<div class="dashboard-section-heading mb-2" aria-label="Total count summary">
    <h5 class="mb-0 font-weight-bold">
        <i class="fas fa-calculator text-primary mr-2"></i>Total Count
    </h5>
    <small class="text-muted">Overall dashboard totals based on the selected analytics filters.</small>
</div>

<div class="row" id="statsContainer">
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-info shadow-sm dashboard-stat-box">
            <div class="inner">
                <h3 id="stat_totalOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Total Orders</p>
            </div>
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            <a href="{{ route('admin.orders.index') }}" class="small-box-footer">Orders <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-danger shadow-sm dashboard-stat-box">
            <div class="inner">
                <h3 id="stat_cancelledOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Cancelled Orders</p>
            </div>
            <div class="icon"><i class="fas fa-times-circle"></i></div>
            <a href="{{ route('admin.orders.cancelled') }}" class="small-box-footer">Cancelled <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-primary shadow-sm dashboard-stat-box">
            <div class="inner">
                <h3 id="stat_confirmedOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Confirmed Orders</p>
            </div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <a href="{{ route('admin.orders.confirmed') }}" class="small-box-footer">Confirmed <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-info shadow-sm dashboard-stat-box">
            <div class="inner">
                <h3 id="stat_shippedOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Shipped Orders</p>
            </div>
            <div class="icon"><i class="fas fa-truck-loading"></i></div>
            <a href="{{ route('admin.orders.shipped') }}" class="small-box-footer">Shipped <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-success shadow-sm dashboard-stat-box">
            <div class="inner">
                <h3 id="stat_deliveredOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Delivered Orders</p>
            </div>
            <div class="icon"><i class="fas fa-truck"></i></div>
            <a href="{{ route('admin.orders.delivered') }}" class="small-box-footer">Delivered <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-warning shadow-sm dashboard-stat-box">
            <div class="inner">
                <h3 id="stat_pendingOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Pending Orders</p>
            </div>
            <div class="icon"><i class="fas fa-clock"></i></div>
            <a href="{{ route('admin.orders.pending') }}" class="small-box-footer">Pending <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-secondary shadow-sm dashboard-stat-box">
            <div class="inner">
                <h3 id="stat_processingOrders"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Processing Orders</p>
            </div>
            <div class="icon"><i class="fas fa-spinner"></i></div>
            <a href="{{ route('admin.orders.processing') }}" class="small-box-footer">Processing <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-maroon shadow-sm dashboard-stat-box">
            <div class="inner">
                <h3 id="stat_grossSales"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Gross Sales</p>
            </div>
            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
            <a href="{{ route('admin.reports.index') }}" class="small-box-footer">Reports <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-dark shadow-sm dashboard-stat-box">
            <div class="inner">
                <h3 id="stat_totalProducts"><i class="fas fa-spinner fa-spin"></i></h3>
                <p>Total Products</p>
            </div>
            <div class="icon"><i class="fas fa-box"></i></div>
            <a href="{{ route('admin.products.index') }}" class="small-box-footer">Products <i class="fas fa-arrow-circle-right"></i></a>
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
<script>
$(function() {
    const dashboardUrl = '{{ route("admin.dashboard") }}';
    let dashboardRequest = null;

    function setLoadingState() {
        const loader = '<i class="fas fa-spinner fa-spin"></i>';

        $('#stat_totalOrders, #stat_cancelledOrders, #stat_confirmedOrders, #stat_shippedOrders, #stat_deliveredOrders, #stat_pendingOrders, #stat_processingOrders, #stat_grossSales, #stat_totalProducts').html(loader);

        $('#today_todaysOrder, #today_newOrder, #today_completedOrder, #today_completedInvoice, #today_shippedOrders, #today_deliveredOrder, #today_cancelled, #today_pendingOrder, #today_incompletedOrder, #today_stockOutOrder, #today_orderList1, #today_orderList2, #today_incompletedInvoice, #today_totalCheckout').html(loader);

        $('#productSaleReportContainer, #userOrderReportContainer')
            .html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>');
    }

    function updateStats(stats) {
        stats = stats || {};

        $('#stat_totalOrders').text(stats.totalOrders || '0');
        $('#stat_cancelledOrders').text(stats.cancelledOrders || '0');
        $('#stat_confirmedOrders').text(stats.confirmedOrders || '0');
        $('#stat_shippedOrders').text(stats.shippedOrders || '0');
        $('#stat_deliveredOrders').text(stats.deliveredOrders || '0');
        $('#stat_pendingOrders').text(stats.pendingOrders || '0');
        $('#stat_processingOrders').text(stats.processingOrders || '0');
        $('#stat_grossSales').text(stats.grossSales || '৳0');
        $('#stat_totalProducts').text(stats.totalProducts || '0');
    }

    function updateTodayReport(todayReport) {
        todayReport = todayReport || {};

        $('#today_todaysOrder').text(todayReport.todaysOrder || '0');
        $('#today_newOrder').text(todayReport.newOrder || '0');
        $('#today_completedOrder').text(todayReport.completedOrder || '0');
        $('#today_completedInvoice').text(todayReport.completedInvoice || '0');
        $('#today_shippedOrders').text(todayReport.shippedOrders || '0');
        $('#today_deliveredOrder').text(todayReport.deliveredOrder || '0');
        $('#today_cancelled').text(todayReport.cancelled || '0');
        $('#today_pendingOrder').text(todayReport.pendingOrder || '0');
        $('#today_incompletedOrder').text(todayReport.incompletedOrder || '0');
        $('#today_stockOutOrder').text(todayReport.stockOutOrder || '0');
        $('#today_orderList1').text(todayReport.orderList1 || '0');
        $('#today_orderList2').text(todayReport.orderList2 || '0');
        $('#today_incompletedInvoice').text(todayReport.incompletedInvoice || '0');
        $('#today_totalCheckout').text(todayReport.totalCheckout || '0');
    }

    function requestPayload() {
        return $('#filterForm').serialize() + '&' + $('#userReportForm').serialize();
    }

    function loadDashboardData() {
        setLoadingState();

        if (dashboardRequest) {
            dashboardRequest.abort();
        }

        dashboardRequest = $.ajax({
            url: dashboardUrl,
            type: 'GET',
            data: requestPayload(),
            dataType: 'json',
            success: function(response) {
                updateStats(response.stats || {});
                updateTodayReport(response.todayReport || {});

                if (response.summaryRangeLabel) {
                    $('#todayReportRangeText').text(
                        'Selected Bangladesh date range: '
                        + response.summaryRangeLabel
                        + ' (12:00 AM - 11:59 PM)'
                    );
                }

                $('#productSaleReportContainer').html(response.sections?.productSaleReport || '<div class="text-center text-muted p-4">No product sale data found.</div>');
                $('#userOrderReportContainer').html(response.sections?.userOrderReport || '<div class="text-center text-muted p-4">No user report data found.</div>');
                initProductSalePagination(1);

                if (response.status === false && response.message) {
                    console.warn(response.message);
                }
            },
            error: function(xhr, status) {
                if (status === 'abort') {
                    return;
                }

                updateStats({});
                updateTodayReport({});

                $('#productSaleReportContainer, #userOrderReportContainer')
                    .html('<div class="text-center p-4 text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading dashboard data.</div>');
            },
            complete: function() {
                dashboardRequest = null;
            }
        });
    }

    $('#dateFilter').on('change', function() {
        const isCustom = $(this).val() === 'custom';
        $('#customDateRange').toggle(isCustom);

        if (! isCustom) {
            $('#start_date, #end_date').val('');
            loadDashboardData();
        }
    });

    $('#campaign_id, #order_status, #payment_status, #delivery_area').on('change', loadDashboardData);

    $('#start_date, #end_date').on('change', function() {
        if ($('#dateFilter').val() === 'custom') {
            loadDashboardData();
        }
    });

    $('#report_user_id').on('change', loadDashboardData);

    $('#resetBtn').on('click', function() {
        $('#filterForm')[0].reset();
        $('#customDateRange').hide();
        loadDashboardData();
    });

    function productSaleFilteredRows() {
        const keyword = ($('#productSaleSearch').val() || '').toLowerCase();
        const rows = $('#productSaleTable tbody tr.product-sale-row');

        if (! keyword) {
            return rows;
        }

        return rows.filter(function() {
            return $(this).text().toLowerCase().indexOf(keyword) > -1;
        });
    }

    function productSalePerPageValue() {
        const selectedValue = String($('#productSalePerPage').val() || '10').toLowerCase();

        if (selectedValue === 'all') {
            return 'all';
        }

        const parsedValue = parseInt(selectedValue, 10);

        return Number.isFinite(parsedValue) && parsedValue > 0 ? parsedValue : 10;
    }

    function initProductSalePagination(page) {
        const allRows = $('#productSaleTable tbody tr.product-sale-row');

        if (! allRows.length) {
            $('#productSalePagination').empty();
            $('#productSaleInfo').text('Showing 0 to 0 of 0 entries');
            return;
        }

        const filteredRows = productSaleFilteredRows();
        const total = filteredRows.length;
        const selectedPerPage = productSalePerPageValue();
        const showAll = selectedPerPage === 'all';
        const perPage = showAll ? Math.max(total, 1) : selectedPerPage;
        const totalPages = showAll ? 1 : Math.max(1, Math.ceil(total / perPage));
        const currentPage = showAll
            ? 1
            : Math.min(Math.max(parseInt(page || 1, 10), 1), totalPages);

        const startIndex = showAll ? 0 : (currentPage - 1) * perPage;
        const endIndex = showAll ? total : Math.min(startIndex + perPage, total);

        allRows.hide();
        filteredRows.slice(startIndex, endIndex).show();

        $('#productSaleInfo').text(total
            ? `Showing ${startIndex + 1} to ${endIndex} of ${total} entries`
            : 'Showing 0 to 0 of 0 entries'
        );

        if (showAll || totalPages <= 1) {
            $('#productSalePagination').empty();
            return;
        }

        let paginationHtml = '';
        paginationHtml += `<button type="button" class="btn btn-sm btn-light border js-product-page" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>Previous</button>`;

        for (let i = 1; i <= totalPages; i++) {
            paginationHtml += `<button type="button" class="btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-light border'} js-product-page" data-page="${i}">${i}</button>`;
        }

        paginationHtml += `<button type="button" class="btn btn-sm btn-light border js-product-page" data-page="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''}>Next</button>`;

        $('#productSalePagination').html(paginationHtml);
    }

    $(document).on('change', '#productSalePerPage', function() {
        initProductSalePagination(1);
    });

    $(document).on('keyup', '#productSaleSearch', function() {
        initProductSalePagination(1);
    });

    $(document).on('click', '.js-product-page', function() {
        if ($(this).prop('disabled')) {
            return;
        }

        initProductSalePagination($(this).data('page'));
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

.dashboard-filter-card .form-group {
    margin-bottom: .75rem;
}

.dashboard-section-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 4px 16px;
    padding: 2px 2px 4px;
}

.dashboard-section-heading h5 {
    color: #343a40;
    font-size: 17px;
}

.dashboard-stat-box h3 {
    font-size: 1.85rem;
    font-weight: 700;
}

.today-report-item {
    display: flex;
    align-items: center;
    gap: 14px;
    background: #fff;
    border: 1px solid #edf0f5;
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,.03);
}

.today-report-item strong {
    display: block;
    color: #6c757d;
    font-size: 13px;
}

.today-report-item h5 {
    margin: 5px 0 0;
    font-size: 20px;
    font-weight: 700;
}

.today-icon {
    width: 52px;
    height: 52px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 22px;
    flex: 0 0 52px;
}

.product-sale-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
}

.product-sale-toolbar .form-control {
    max-width: 240px;
}

#productSaleTable th,
#productSaleTable td,
#userOrderReportTable th,
#userOrderReportTable td {
    white-space: nowrap;
    vertical-align: middle;
}
</style>
@endsection
