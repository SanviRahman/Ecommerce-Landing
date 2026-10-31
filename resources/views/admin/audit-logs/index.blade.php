@extends('adminlte::page')

@section('title', 'Order Audit Logs')

@section('content_header')
    <div class="p56-page-heading d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Order Audit Logs</h1>
            <p class="mb-0 text-muted">Read-only history of permanently purged orders</p>
        </div>
        <nav aria-label="Breadcrumb" class="mt-2 mt-md-0">
            <ol class="breadcrumb bg-transparent mb-0 p-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item active" aria-current="page">Audit Logs</li>
            </ol>
        </nav>
    </div>
@stop

@section('content')
    <main class="p56-audit" aria-label="Read-only order purge audit history">
        <div class="p56-hero d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-start">
                <span class="p56-hero-icon"><i class="fas fa-shield-alt" aria-hidden="true"></i></span>
                <div>
                    <span class="p56-eyebrow">ORDER PURGE EVIDENCE</span>
                    <h2 class="h4 font-weight-bold mt-1 mb-2">Read-only Audit Archive</h2>
                    <p class="mb-0">Trace deletion activity without changing orders, stock or saved evidence.</p>
                </div>
            </div>
            <span class="p56-readonly mt-3 mt-md-0"><i class="fas fa-lock mr-1" aria-hidden="true"></i> View only</span>
        </div>

        @if(! $available)
            <div class="alert alert-warning p56-notice mt-4" role="status">
                <i class="fas fa-exclamation-triangle mr-2" aria-hidden="true"></i>
                <strong>Audit table not found.</strong> The P55 <code>order_purge_audits</code> migration has not been applied to this database.
                Ask your authorized developer to verify the database and approved migration status. No audit data was changed.
            </div>
        @else
            @if($errors->any())
                <div class="alert alert-danger mt-4" role="alert">
                    <i class="fas fa-exclamation-circle mr-1" aria-hidden="true"></i>
                    <strong>Check your search filters:</strong> {{ $errors->first() }}
                </div>
            @endif
            <div class="row p56-stat-row mt-4">
                <div class="col-md-4 mb-3"><div class="p56-stat"><span class="p56-stat-icon"><i class="fas fa-archive" aria-hidden="true"></i></span><div><small>Total archived purges</small><strong>{{ number_format($totals['all']) }}</strong></div></div></div>
                <div class="col-md-4 mb-3"><div class="p56-stat"><span class="p56-stat-icon"><i class="far fa-calendar-check" aria-hidden="true"></i></span><div><small>Archived today</small><strong>{{ number_format($totals['today']) }}</strong></div></div></div>
                <div class="col-md-4 mb-3"><div class="p56-stat"><span class="p56-stat-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></span><div><small>Recorded admins</small><strong>{{ number_format($totals['admins']) }}</strong></div></div></div>
            </div>

            <div class="card p56-card mb-4">
                <div class="card-header p56-card-header d-flex align-items-center justify-content-between flex-wrap">
                    <h3 class="card-title mb-0"><i class="fas fa-filter mr-2 text-success" aria-hidden="true"></i>Search Audit History</h3>
                    <small class="text-muted">Order / Invoice / Admin / Date</small>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.reports.audit-logs.index') }}" method="GET" role="search">
                        <div class="row">
                            <div class="col-lg-3 col-md-6 form-group">
                                <label for="p56-search">Order ID or Invoice</label>
                                <input class="form-control" type="search" id="p56-search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="191" placeholder="Search by order number or invoice">
                            </div>
                            <div class="col-lg-3 col-md-6 form-group">
                                <label for="p56-admin">Deleted by</label>
                                <select class="form-control" name="admin_id" id="p56-admin">
                                    <option value="">All admins</option>
                                    @foreach($adminOptions as $admin)
                                        <option value="{{ $admin->admin_id }}" @selected($filters['admin_id'] === (int) $admin->admin_id)>
                                            {{ $admin->admin_name ?: 'Former admin' }} (#{{ $admin->admin_id }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-6 form-group">
                                <label for="p56-from">From date</label>
                                <input class="form-control" type="date" id="p56-from" name="from" value="{{ $filters['from'] ?? '' }}">
                            </div>
                            <div class="col-lg-2 col-md-6 form-group">
                                <label for="p56-to">To date</label>
                                <input class="form-control" type="date" id="p56-to" name="to" value="{{ $filters['to'] ?? '' }}">
                            </div>
                            <div class="col-lg-2 col-md-6 form-group">
                                <label for="p56-stock">Stock</label>
                                <select id="p56-stock" name="inventory_version" class="form-control">
                                    <option value="" @selected($filters['inventory_version'] === null)>All</option>
                                    <option value="1" @selected($filters['inventory_version'] === '1')>Managed</option>
                                    <option value="0" @selected($filters['inventory_version'] === '0')>Legacy</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center p56-filter-actions">
                            <button type="submit" class="btn p56-btn-main mr-2 mb-2"><i class="fas fa-search mr-1" aria-hidden="true"></i> Apply Filters</button>
                            <a href="{{ route('admin.reports.audit-logs.index') }}" class="btn btn-outline-secondary mr-auto mb-2"><i class="fas fa-undo-alt mr-1" aria-hidden="true"></i> Reset</a>
                            <a href="{{ route('admin.reports.audit-logs.export', array_filter($filters, static fn ($v) => $v !== null)) }}" class="btn btn-outline-success mb-2">
                                <i class="fas fa-file-csv mr-1" aria-hidden="true"></i> Export Filtered CSV
                            </a>
                        </div>
                        <small class="text-muted">CSV contains summary fields only and is limited to the latest 5,000 matching records.</small>
                    </form>
                </div>
            </div>

            <div class="card p56-card mb-4">
                <div class="card-header p56-card-header d-flex align-items-center justify-content-between">
                    <h3 class="card-title mb-0"><i class="fas fa-history mr-2 text-success" aria-hidden="true"></i>Deletion History</h3>
                    <span class="p56-count">{{ number_format($audits->total()) }} result(s)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover p56-table mb-0">
                            <thead><tr>
                                <th scope="col">Purge ID</th>
                                <th scope="col">Original Order</th>
                                <th scope="col">Invoice</th>
                                <th scope="col">Deleted By</th>
                                <th scope="col">Inventory</th>
                                <th scope="col">Deleted At</th>
                                <th scope="col" class="text-right">Details</th>
                            </tr></thead>
                            <tbody>
                                @forelse($audits as $audit)
                                    <tr>
                                        <td><strong>#{{ $audit->id }}</strong></td>
                                        <td><span class="p56-code">#{{ $audit->original_order_id }}</span></td>
                                        <td>{{ $audit->invoice_id ?: '—' }}</td>
                                        <td><span class="font-weight-semibold">{{ $audit->admin_name ?: 'Former admin' }}</span><small class="d-block text-muted">Admin #{{ $audit->admin_id }}</small></td>
                                        <td><span class="badge p56-badge {{ (int) $audit->inventory_version === 1 ? 'p56-badge-green' : 'p56-badge-grey' }}">{{ (int) $audit->inventory_version === 1 ? 'Managed' : 'Legacy' }}</span></td>
                                        <td><time datetime="{{ \Carbon\Carbon::parse($audit->created_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($audit->created_at)->format('d M Y, h:i A') }}</time></td>
                                        <td class="text-right"><a class="btn btn-sm btn-outline-success p56-view" href="{{ route('admin.reports.audit-logs.show', $audit->id) }}" aria-label="View details for audit {{ $audit->id }}"><i class="far fa-eye mr-1" aria-hidden="true"></i> View Details</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7"><div class="p56-empty text-center"><i class="fas fa-clipboard-check" aria-hidden="true"></i><h4>No audit logs found</h4><p class="mb-0">Completed eligible Order Trash purges appear here. Change filters to search earlier records.</p></div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($audits->hasPages())
                    <div class="card-footer p56-pagination">{{ $audits->links('pagination::bootstrap-4') }}</div>
                @endif
            </div>
        @endif
        <p class="p56-footer-note"><i class="fas fa-shield-alt mr-1" aria-hidden="true"></i> Audit evidence is write-protected by this interface. Access is limited to active administrators.</p>
    </main>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/p56-audit-logs.css') }}">
@stop
