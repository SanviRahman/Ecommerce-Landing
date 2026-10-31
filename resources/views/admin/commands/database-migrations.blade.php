@extends('adminlte::page')

@section('title', 'Database Migrations')

@section('content_header')
    <h1><i class="fas fa-database mr-2" aria-hidden="true"></i>Database Migrations</h1>
@stop

@section('content')
<div class="container-fluid pb-4" style="max-width: 1000px;">
    <div class="alert alert-warning" role="alert">
        <strong>Production database operation.</strong> Take a verified MySQL backup first.
        <code>migrate --force</code> can execute every pending migration, including schema changes.
        Never use <code>migrate:fresh</code>, <code>rollback</code>, or <code>db:wipe</code> here.
    </div>

    @if (session('migration_result'))
        @php($result = session('migration_result'))
        <div class="alert {{ !empty($result['success']) ? 'alert-success' : 'alert-danger' }}" role="status">
            <strong>{{ $result['message'] ?? 'Migration result' }}</strong>
            @if (!empty($result['output']))
                <pre class="mb-0 mt-2 p-3 rounded bg-white text-dark" style="white-space: pre-wrap; overflow-wrap: anywhere; max-height: 300px; overflow: auto;">{{ $result['output'] }}</pre>
            @endif
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Migration not started.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-outline card-info shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
            <h3 class="card-title"><i class="fas fa-list-alt mr-1" aria-hidden="true"></i>Current Migration Status</h3>
            <a href="{{ route('command.database-migrations.index') }}" class="btn btn-sm btn-outline-secondary ml-auto">Refresh Status</a>
        </div>
        <div class="card-body">
            @if ($statusReady)
                <p class="text-muted small">Review every pending migration before proceeding. The confirmation expires after 10 minutes.</p>
            @else
                <p class="text-danger mb-2">Could not read migration status. Running migrations is disabled.</p>
            @endif
            <pre class="mb-0 p-3 rounded" style="background: #f3f5f8; color: #273347; overflow: auto; max-height: 360px; white-space: pre; font-size: .83rem;">{{ $statusOutput }}</pre>
        </div>
    </div>

    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header"><h3 class="card-title">Run Approved Migration</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('command.database-migrations.store') }}" autocomplete="off"
                  onsubmit="return confirm('This modifies the database. Have you verified a recent MySQL backup and reviewed the selected scope?');">
                @csrf
                <fieldset {{ !$statusReady ? 'disabled' : '' }}>
                    <div class="form-group">
                        <label for="migration_scope">Migration Scope</label>
                        <select class="form-control" id="migration_scope" name="scope" required>
                            @if (!$auditMigrationExists)
                                <option value="" selected disabled>Select a migration scope</option>
                            @endif
                            <option value="audit" {{ $auditMigrationExists && old('scope', 'audit') === 'audit' ? 'selected' : '' }}
                                {{ !$auditMigrationExists ? 'disabled' : '' }}>
                                Only Order Purge Audit Table — recommended (migrate --path=... --force)
                            </option>
                            <option value="storefront" {{ old('scope') === 'storefront' ? 'selected' : '' }}
                                {{ !$storefrontMigrationsExist ? 'disabled' : '' }}>
                                Storefront Prerequisites — stock-state + database sessions only (2 approved migrations)
                            </option>
                            <option value="all" {{ old('scope') === 'all' ? 'selected' : '' }}>
                                All Pending Migrations (php artisan migrate --force) — advanced
                            </option>
                        </select>
                        @if (!$auditMigrationExists)
                            <small class="form-text text-danger">The P55 audit migration file was not found on this server.</small>
                        @else
                            <small class="form-text text-muted">Default option runs only the P55 audit table migration. It does not run other pending migrations.</small>
                        @endif
                        @if (!$storefrontMigrationsExist)
                            <small class="form-text text-danger d-block">The approved storefront stock-state or sessions migration file is missing.</small>
                        @else
                            <small class="form-text text-muted d-block">Storefront Prerequisites runs only the P61 stock-state column and the Laravel database sessions table. It verifies the approved production host and actual MySQL database.</small>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="migration_confirmation">Type confirmation phrase</label>
                        <input id="migration_confirmation" name="confirmation" type="text" class="form-control" maxlength="30"
                               value="{{ old('confirmation') }}" required spellcheck="false" autocomplete="off"
                               placeholder="MIGRATE AUDIT / STOREFRONT / ALL">
                        <small class="form-text text-muted">Audit Only: <code>MIGRATE AUDIT</code>. Storefront Prerequisites: <code>MIGRATE STOREFRONT</code>. All Pending: <code>MIGRATE ALL</code>.</small>
                    </div>

                    <div class="form-group">
                        <label for="migration_password">Current Admin Password</label>
                        <input id="migration_password" name="current_password" type="password" class="form-control"
                               required maxlength="255" autocomplete="current-password">
                    </div>

                    <div class="custom-control custom-checkbox mb-3">
                        <input id="migration_backup" class="custom-control-input" name="backup_confirmed" type="checkbox" value="1" required>
                        <label for="migration_backup" class="custom-control-label">I have taken a recent, restorable database backup.</label>
                    </div>

                    <button class="btn btn-danger" type="submit">
                        <i class="fas fa-play mr-1" aria-hidden="true"></i>Run Selected Migration
                    </button>
                </fieldset>
            </form>
        </div>
        <div class="card-footer small text-muted">
            Only active Admin users may execute this action. Protected by CSRF, password check, single-use status preview,
            rate limiting, and a migration lock. It never accepts arbitrary shell commands or file paths.
        </div>
    </div>
</div>
@stop
