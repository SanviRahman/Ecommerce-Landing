@extends('adminlte::page')

@section('title', $title ?? 'Landing Page Manage')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">{{ $title ?? 'Landing Page Manage' }}</h1>

        <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-plus mr-1"></i> Create
        </a>
    </div>
@stop

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body">

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            <form action="{{ $isTrash ? route('admin.campaigns.trashed') : route('admin.campaigns.index') }}"
                  method="GET"
                  class="mb-3">

                <div class="row">
                    <div class="col-md-4">
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               class="form-control"
                               placeholder="Search by title, slug, description...">
                    </div>

                    <div class="col-md-3">
                        <select name="status" class="form-control">
                            <option value="all" @selected(request('status') === 'all' || request('status') === null)>All Status</option>
                            <option value="1" @selected(request('status') === '1')>Active</option>
                            <option value="0" @selected(request('status') === '0')>Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-search mr-1"></i> Filter
                        </button>

                        <a href="{{ $isTrash ? route('admin.campaigns.trashed') : route('admin.campaigns.index') }}"
                           class="btn btn-secondary">
                            <i class="fas fa-sync-alt mr-1"></i> Reset
                        </a>

                        @if (! $isTrash)
                            <a href="{{ route('admin.campaigns.trashed') }}" class="btn btn-warning">
                                <i class="fas fa-trash mr-1"></i> Trash
                            </a>
                        @else
                            <a href="{{ route('admin.campaigns.index') }}" class="btn btn-primary">
                                <i class="fas fa-arrow-left mr-1"></i> Back
                            </a>
                        @endif
                    </div>
                </div>
            </form>

            @include('admin.campaigns.partials.table', [
                'campaigns' => $campaigns,
                'isTrash' => $isTrash ?? false,
            ])

        </div>
    </div>
@stop

@section('js')
    <script>
        $(document).on('click', '.delete-btn', function (e) {
            e.preventDefault();

            let url = $(this).data('url');

            if (!confirm('Are you sure?')) {
                return;
            }

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    _method: 'DELETE'
                },
                success: function (response) {
                    if (response.status) {
                        location.reload();
                    } else {
                        alert(response.message || 'Something went wrong.');
                    }
                },
                error: function () {
                    alert('Something went wrong.');
                }
            });
        });

        $(document).on('click', '.restore-btn', function (e) {
            e.preventDefault();

            let url = $(this).data('url');

            if (!confirm('Restore this campaign?')) {
                return;
            }

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function (response) {
                    if (response.status) {
                        location.reload();
                    } else {
                        alert(response.message || 'Something went wrong.');
                    }
                },
                error: function () {
                    alert('Something went wrong.');
                }
            });
        });
    </script>
@stop