@extends('adminlte::page')

@section('title', $title ?? 'Media Management')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Media Management' }}</h1>

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

    <div class="btn-group btn-group-sm mt-2 mt-md-0">
        <a href="{{ route('admin.media-management.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-photo-video mr-1"></i> Active Media
        </a>
        <a href="{{ route('admin.media-management.trash', ['context' => $context ?? 'all']) }}" class="btn btn-outline-danger">
            <i class="fas fa-trash mr-1"></i> Trash Bin
        </a>
    </div>
</div>
@endsection

@section('css')
<style>
    .media-stat-card {
        border: 0;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, .08);
        min-height: 84px;
    }

    .media-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 20px;
    }

    .media-thumb {
        width: 78px;
        height: 58px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
    }

    .media-file-box {
        width: 78px;
        height: 58px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        font-size: 22px;
    }

    .media-table td,
    .media-table th {
        vertical-align: middle !important;
        font-size: 13px;
    }

    .media-owner {
        max-width: 210px;
        white-space: normal;
        word-break: break-word;
    }

    .media-file-name {
        max-width: 260px;
        white-space: normal;
        word-break: break-all;
    }

    .media-toolbar .form-control,
    .media-toolbar .custom-select {
        height: 36px;
        font-size: 13px;
    }

    .modal-media-preview {
        max-width: 100%;
        max-height: 260px;
        object-fit: contain;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #f8fafc;
    }

    .media-context-nav .btn {
        margin-bottom: 4px;
    }

    #media-table-wrapper.opacity-50 {
        opacity: .55;
        pointer-events: none;
    }
</style>
@endsection

@section('content')
@php
    $currentContext = $context ?? 'all';
    $trashMode = ! empty($isTrash);
@endphp

<div id="media-management-wrapper">
    <div class="alert {{ $trashMode ? 'alert-warning' : 'alert-info' }} border-0 shadow-sm">
        <i class="fas {{ $trashMode ? 'fa-exclamation-triangle' : 'fa-info-circle' }} mr-1"></i>
        {{ $description ?? 'Manage uploaded media safely.' }}
        @if($trashMode)
            <strong>Trash থেকে Restore করলে media আবার active list-এ ফিরে আসবে। Force Delete করলে physical file permanently remove হবে।</strong>
        @else
            <strong>Delete করলে media Trash Bin-এ যাবে, physical file থাকবে। Force Delete শুধু Trash থেকে করবেন।</strong>
        @endif
    </div>

    @if(empty($trashEnabled))
        <div class="alert alert-danger border-0 shadow-sm">
            <i class="fas fa-database mr-1"></i>
            Media Trash feature ব্যবহার করতে included migration run করতে হবে। Migration ছাড়া Delete/Trash কাজ করবে না।
        </div>
    @endif

    <div class="row mb-3" id="media-stat-row">
        <div class="col-md-3 mb-3">
            <div class="card media-stat-card mb-0">
                <div class="card-body d-flex align-items-center">
                    <span class="media-stat-icon bg-primary mr-3"><i class="fas fa-folder-open"></i></span>
                    <div>
                        <div class="text-muted small font-weight-bold">{{ $trashMode ? 'Trashed Media' : 'Total Media' }}</div>
                        <h4 class="mb-0" data-stat="total">{{ number_format((int) ($stats['total'] ?? 0)) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card media-stat-card mb-0">
                <div class="card-body d-flex align-items-center">
                    <span class="media-stat-icon bg-success mr-3"><i class="fas fa-image"></i></span>
                    <div>
                        <div class="text-muted small font-weight-bold">Images</div>
                        <h4 class="mb-0" data-stat="images">{{ number_format((int) ($stats['images'] ?? 0)) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card media-stat-card mb-0">
                <div class="card-body d-flex align-items-center">
                    <span class="media-stat-icon bg-warning mr-3"><i class="fas fa-video"></i></span>
                    <div>
                        <div class="text-muted small font-weight-bold">Videos</div>
                        <h4 class="mb-0" data-stat="videos">{{ number_format((int) ($stats['videos'] ?? 0)) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card media-stat-card mb-0">
                <div class="card-body d-flex align-items-center">
                    <span class="media-stat-icon bg-secondary mr-3"><i class="fas fa-hdd"></i></span>
                    <div>
                        <div class="text-muted small font-weight-bold">Storage Used</div>
                        <h4 class="mb-0" data-stat="size">{{ $stats['size'] ?? '0 B' }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-photo-video text-primary mr-1"></i>
                {{ $title ?? 'Media Management' }}
                <span class="badge {{ $trashMode ? 'badge-danger' : 'badge-light' }} border ml-1">
                    {{ $trashMode ? 'Trash List' : 'Active List' }}
                </span>
            </h5>

            <div class="btn-group btn-group-sm mt-2 mt-md-0 media-context-nav" role="group">
                <a href="{{ route('admin.media-management.index') }}" class="btn btn-outline-secondary {{ !$trashMode && $currentContext === 'all' ? 'active' : '' }}">All</a>
                <a href="{{ route('admin.media-management.category') }}" class="btn btn-outline-primary {{ !$trashMode && $currentContext === 'category' ? 'active' : '' }}">Category</a>
                <a href="{{ route('admin.media-management.products') }}" class="btn btn-outline-primary {{ !$trashMode && $currentContext === 'products' ? 'active' : '' }}">Products</a>
                <a href="{{ route('admin.media-management.campaign') }}" class="btn btn-outline-primary {{ !$trashMode && str_starts_with($currentContext, 'campaign') ? 'active' : '' }}">Campaign</a>
                <a href="{{ route('admin.media-management.other') }}" class="btn btn-outline-primary {{ !$trashMode && $currentContext === 'other' ? 'active' : '' }}">Other</a>
                <a href="{{ route('admin.media-management.trash', ['context' => $currentContext]) }}" class="btn btn-outline-danger {{ $trashMode ? 'active' : '' }}">
                    <i class="fas fa-trash mr-1"></i> Trash
                </a>
            </div>
        </div>

        <div class="card-body media-toolbar border-bottom">
            <form id="media-filter-form" method="GET" autocomplete="off" action="{{ $trashMode ? route('admin.media-management.trash') : url()->current() }}">
                @if($trashMode)
                    <input type="hidden" name="context" value="{{ $currentContext }}">
                @endif

                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="small text-muted font-weight-bold">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search file name, collection, model...">
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="small text-muted font-weight-bold">Type</label>
                        <select name="type" class="custom-select">
                            <option value="all">All Types</option>
                            <option value="image" @selected(request('type') === 'image')>Images</option>
                            <option value="video" @selected(request('type') === 'video')>Videos</option>
                            <option value="document" @selected(request('type') === 'document')>Documents</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-2">
                        <label class="small text-muted font-weight-bold">Collection</label>
                        <select name="collection" class="custom-select">
                            <option value="all">All Collections</option>
                            @foreach($collections as $collection)
                                <option value="{{ $collection }}" @selected(request('collection') === $collection)>
                                    {{ $collectionLabels[$collection] ?? ucwords(str_replace('_', ' ', $collection)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="small text-muted font-weight-bold">Disk</label>
                        <select name="disk" class="custom-select">
                            <option value="all">All Disks</option>
                            @foreach($disks as $disk)
                                <option value="{{ $disk }}" @selected(request('disk') === $disk)>{{ $disk }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-1 mb-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-dark btn-block" title="Filter">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <a href="{{ $trashMode ? route('admin.media-management.trash', ['context' => $currentContext]) : url()->current() }}" class="btn btn-sm btn-outline-secondary media-reset-filter">
                        <i class="fas fa-sync-alt mr-1"></i> Reset Filter
                    </a>
                </div>
            </form>
        </div>

        <div class="card-body py-2 border-bottom bg-light">
            <div class="d-flex align-items-center flex-wrap">
                <div class="form-check mr-3 mb-1">
                    <input type="checkbox" class="form-check-input" id="select-all-media">
                    <label class="form-check-label small font-weight-bold" for="select-all-media">Select All</label>
                </div>

                @if($trashMode)
                    <button type="button" class="btn btn-sm btn-success mr-2 mb-1 media-bulk-action" data-action="restore">
                        <i class="fas fa-undo mr-1"></i> Restore Selected
                    </button>
                    <button type="button" class="btn btn-sm btn-danger mb-1 media-bulk-action" data-action="force_delete">
                        <i class="fas fa-times-circle mr-1"></i> Force Delete Selected
                    </button>
                @else
                    <button type="button" class="btn btn-sm btn-danger mb-1 media-bulk-action" data-action="delete">
                        <i class="fas fa-trash mr-1"></i> Move Selected To Trash
                    </button>
                @endif
            </div>
        </div>

        <div id="media-table-wrapper">
            @include('admin.media-management.partials.table')
        </div>
    </div>
</div>

<div class="modal fade" id="mediaEditModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" id="media-edit-modal-content">
            <div class="modal-body text-center py-5">
                <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
(function($) {
    'use strict';

    const csrfToken = '{{ csrf_token() }}';

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    });

    function notify(type, message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire(type === 'success' ? 'Success' : (type === 'error' ? 'Error' : 'Notice'), message, type);
            return;
        }

        alert(message);
    }

    function confirmAction(title, text, callback, confirmText = 'Yes, Continue') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: confirmText,
                confirmButtonColor: '#dc3545'
            }).then(function(result) {
                if (result.isConfirmed || result.value) {
                    callback();
                }
            });
            return;
        }

        if (confirm(text)) {
            callback();
        }
    }

    function selectedMediaIds() {
        return $('.media-row-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
    }

    function updateStats(stats) {
        if (!stats) return;

        $('[data-stat="total"]').text(Number(stats.total || 0).toLocaleString());
        $('[data-stat="images"]').text(Number(stats.images || 0).toLocaleString());
        $('[data-stat="videos"]').text(Number(stats.videos || 0).toLocaleString());
        $('[data-stat="size"]').text(stats.size || '0 B');
    }

    let mediaSearchTimer = null;
    let mediaReloadRequest = null;

    function buildMediaAjaxUrl(targetUrl = null) {
        const formEl = document.getElementById('media-filter-form');
        const url = new URL(targetUrl || formEl.getAttribute('action'), window.location.origin);
        const keepPage = targetUrl ? url.searchParams.get('page') : null;
        const formData = new FormData(formEl);

        url.search = '';

        formData.forEach(function(value, key) {
            value = String(value || '').trim();

            // Keep trash context; skip empty/all values to avoid stale browser-restored filters.
            if (key !== 'context' && (value === '' || value === 'all')) {
                return;
            }

            url.searchParams.set(key, value);
        });

        if (keepPage) {
            url.searchParams.set('page', keepPage);
        }

        return url.toString();
    }

    function reloadMediaTable(targetUrl = null) {
        const ajaxUrl = buildMediaAjaxUrl(targetUrl);

        if (mediaReloadRequest) {
            mediaReloadRequest.abort();
        }

        mediaReloadRequest = $.ajax({
            url: ajaxUrl,
            method: 'GET',
            cache: false,
            beforeSend: function() {
                $('#media-table-wrapper').addClass('opacity-50');
            },
            success: function(res) {
                if (!res.status) {
                    notify('error', res.message || 'Media table reload failed.');
                    return;
                }

                $('#media-table-wrapper').html(res.html);
                $('#select-all-media').prop('checked', false);
                updateStats(res.stats);

                if (window.history) {
                    history.replaceState(null, '', ajaxUrl);
                }
            },
            error: function(xhr) {
                if (xhr.statusText === 'abort') {
                    return;
                }

                notify('error', xhr.responseJSON?.message || 'Media table reload failed.');
            },
            complete: function() {
                $('#media-table-wrapper').removeClass('opacity-50');
                mediaReloadRequest = null;
            }
        });
    }

    $('#media-filter-form').on('submit', function(e) {
        e.preventDefault();
        reloadMediaTable();
    });

    $(document).on('change', '#media-filter-form select', function() {
        reloadMediaTable();
    });

    $(document).on('input', '#media-filter-form input[name="search"]', function() {
        clearTimeout(mediaSearchTimer);
        mediaSearchTimer = setTimeout(function() {
            reloadMediaTable();
        }, 450);
    });

    $(document).on('click', '#media-table-wrapper .pagination a', function(e) {
        e.preventDefault();
        reloadMediaTable($(this).attr('href'));
    });

    $(document).on('click', '.media-reset-filter', function(e) {
        e.preventDefault();
        const form = $('#media-filter-form');
        form.find('input[name="search"]').val('');
        form.find('select[name="type"]').val('all');
        form.find('select[name="collection"]').val('all');
        form.find('select[name="disk"]').val('all');
        reloadMediaTable(form.attr('action'));
    });

    $(document).on('change', '#select-all-media, #select-all-media-table', function() {
        const checked = $(this).is(':checked');
        $('#select-all-media, #select-all-media-table').prop('checked', checked);
        $('.media-row-checkbox').prop('checked', checked);
    });

    $(document).on('change', '.media-row-checkbox', function() {
        const total = $('.media-row-checkbox').length;
        const checked = $('.media-row-checkbox:checked').length;
        $('#select-all-media, #select-all-media-table').prop('checked', total > 0 && total === checked);
    });

    $(document).on('click', '.btn-media-edit', function() {
        const url = $(this).data('url');
        $('#mediaEditModal').modal('show');
        $('#media-edit-modal-content').html('<div class="modal-body text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>');

        $.get(url, function(res) {
            if (!res.status) {
                notify('error', res.message || 'Failed to load media edit form.');
                return;
            }

            $('#media-edit-modal-content').html(res.html);
        }).fail(function(xhr) {
            notify('error', xhr.responseJSON?.message || 'Failed to load media edit form.');
        });
    });

    $(document).on('submit', '#media-info-form', function(e) {
        e.preventDefault();

        const form = $(this);
        const button = form.find('button[type="submit"]');
        const oldHtml = button.html();

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            beforeSend: function() {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            },
            success: function(res) {
                button.prop('disabled', false).html(oldHtml);
                if (res.status) {
                    $('#mediaEditModal').modal('hide');
                    notify('success', res.message || 'Media updated.');
                    reloadMediaTable();
                } else {
                    notify('error', res.message || 'Media update failed.');
                }
            },
            error: function(xhr) {
                button.prop('disabled', false).html(oldHtml);
                notify('error', xhr.responseJSON?.message || 'Media update failed.');
            }
        });
    });

    function replaceMediaImmediately(input) {
        const form = $(input).closest('#media-replace-form');

        if (!form.length || !input.files || !input.files.length || form.data('replacing')) {
            return;
        }

        const formData = new FormData(form[0]);
        const browseButton = form.find('.js-open-media-picker');
        const oldButtonHtml = browseButton.html();

        form.data('replacing', true);

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                browseButton
                    .prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin mr-1"></i> Applying...');
            },
            success: function(res) {
                if (!res.status) {
                    notify('error', res.message || 'Media change failed.');
                    return;
                }

                $('#mediaEditModal').modal('hide');
                notify('success', res.message || 'Media changed successfully.');
                reloadMediaTable();
            },
            error: function(xhr) {
                notify('error', xhr.responseJSON?.message || 'Media change failed.');
            },
            complete: function() {
                form.data('replacing', false);
                browseButton.prop('disabled', false).html(oldButtonHtml);

                // Allow the same media file to be selected again after an error.
                input.value = '';
            }
        });
    }

    $(document).on(
        'change',
        '#media-replace-form input[name="file"][data-media-picker-auto-apply="1"]',
        function() {
            replaceMediaImmediately(this);
        }
    );

    // Defensive fallback: no visible submit button exists, but prevent accidental normal submission.
    $(document).on('submit', '#media-replace-form', function(e) {
        e.preventDefault();
        const input = this.querySelector('input[name="file"]');
        replaceMediaImmediately(input);
    });

    $(document).on('click', '.btn-media-delete', function() {
        const url = $(this).data('url');

        confirmAction('Move media to trash?', 'This file will be hidden from active Media Management list but physical file will stay until Force Delete.', function() {
            $.ajax({
                url: url,
                method: 'POST',
                data: { _method: 'DELETE' },
                success: function(res) {
                    if (res.status) {
                        notify('success', res.message || 'Media moved to trash.');
                        reloadMediaTable();
                    } else {
                        notify('error', res.message || 'Media delete failed.');
                    }
                },
                error: function(xhr) {
                    notify('error', xhr.responseJSON?.message || 'Media delete failed.');
                }
            });
        }, 'Move To Trash');
    });

    $(document).on('click', '.btn-media-restore', function() {
        const url = $(this).data('url');

        $.post(url, function(res) {
            if (res.status) {
                notify('success', res.message || 'Media restored.');
                reloadMediaTable();
            } else {
                notify('error', res.message || 'Media restore failed.');
            }
        }).fail(function(xhr) {
            notify('error', xhr.responseJSON?.message || 'Media restore failed.');
        });
    });

    $(document).on('click', '.btn-media-force-delete', function() {
        const url = $(this).data('url');

        confirmAction('Force delete media?', 'This will permanently delete the database row and physical file. This cannot be undone.', function() {
            $.ajax({
                url: url,
                method: 'POST',
                data: { _method: 'DELETE' },
                success: function(res) {
                    if (res.status) {
                        notify('success', res.message || 'Media permanently deleted.');
                        reloadMediaTable();
                    } else {
                        notify('error', res.message || 'Media force delete failed.');
                    }
                },
                error: function(xhr) {
                    notify('error', xhr.responseJSON?.message || 'Media force delete failed.');
                }
            });
        }, 'Force Delete');
    });

    $('.media-bulk-action').on('click', function() {
        const ids = selectedMediaIds();
        const action = $(this).data('action');

        if (!ids.length) {
            notify('info', 'Please select at least one media file.');
            return;
        }

        let title = 'Move selected media to trash?';
        let text = ids.length + ' media file(s) will be moved to trash.';
        let confirmText = 'Move To Trash';

        if (action === 'restore') {
            title = 'Restore selected media?';
            text = ids.length + ' media file(s) will be restored to active list.';
            confirmText = 'Restore';
        }

        if (action === 'force_delete') {
            title = 'Force delete selected media?';
            text = ids.length + ' media file(s) will be permanently deleted from database and storage.';
            confirmText = 'Force Delete';
        }

        confirmAction(title, text, function() {
            $.ajax({
                url: '{{ route('admin.media-management.multiple-action') }}',
                method: 'POST',
                data: {
                    action: action,
                    ids: ids
                },
                success: function(res) {
                    if (res.status) {
                        notify('success', res.message || 'Bulk action completed.');
                        $('#select-all-media').prop('checked', false);
                        reloadMediaTable();
                    } else {
                        notify('error', res.message || 'Bulk action failed.');
                    }
                },
                error: function(xhr) {
                    notify('error', xhr.responseJSON?.message || 'Bulk action failed.');
                }
            });
        }, confirmText);
    });
})(jQuery);
</script>
@endsection

