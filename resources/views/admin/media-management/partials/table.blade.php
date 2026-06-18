@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $mediaItems */
    $trashMode = ! empty($isTrash);
@endphp

<div class="table-responsive">
    <table class="table table-hover table-striped media-table mb-0">
        <thead class="thead-light">
            <tr>
                <th style="width: 36px;">
                    <input type="checkbox" id="select-all-media-table">
                </th>
                <th style="width: 100px;">Preview</th>
                <th>File</th>
                <th>Owner</th>
                <th>Collection</th>
                <th>Type</th>
                <th>Size</th>
                <th>{{ $trashMode ? 'Trashed' : 'Uploaded' }}</th>
                <th style="width: {{ $trashMode ? '150px' : '170px' }};">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($mediaItems as $media)
                @php
                    $isImage = str_starts_with((string) $media->mime_type, 'image/');
                    $isVideo = str_starts_with((string) $media->mime_type, 'video/');
                    $modelLabel = $modelLabels[$media->model_type] ?? class_basename($media->model_type);
                    $collectionLabel = $collectionLabels[$media->collection_name] ?? ucwords(str_replace('_', ' ', $media->collection_name));

                    try {
                        $mediaUrl = $media->getFullUrl() ?: $media->getUrl();
                    } catch (Throwable $exception) {
                        $mediaUrl = asset('vendor/adminlte/dist/img/no-image.png');
                    }

                    $owner = $media->model;
                    $ownerLabel = 'Missing Owner';

                    if ($owner) {
                        foreach (['title', 'name', 'page_name', 'website_name', 'customer_name', 'email'] as $attribute) {
                            if (!empty($owner->{$attribute})) {
                                $ownerLabel = $owner->{$attribute};
                                break;
                            }
                        }

                        if ($ownerLabel === 'Missing Owner') {
                            $ownerLabel = $modelLabel . ' #' . $media->model_id;
                        }
                    }

                    $bytes = (float) ($media->size ?? 0);
                    $size = '0 B';
                    if ($bytes > 0) {
                        $units = ['B', 'KB', 'MB', 'GB'];
                        $factor = min((int) floor(log($bytes, 1024)), count($units) - 1);
                        $size = round($bytes / (1024 ** $factor), 2) . ' ' . $units[$factor];
                    }
                @endphp

                <tr>
                    <td>
                        <input type="checkbox" class="media-row-checkbox" value="{{ $media->id }}">
                    </td>
                    <td>
                        @if($isImage)
                            <a href="{{ $mediaUrl }}" target="_blank">
                                <img src="{{ $mediaUrl }}" alt="{{ $media->name }}" class="media-thumb" onerror="this.src='{{ asset('vendor/adminlte/dist/img/no-image.png') }}'">
                            </a>
                        @elseif($isVideo)
                            <a href="{{ $mediaUrl }}" target="_blank" class="media-file-box text-decoration-none"><i class="fas fa-video"></i></a>
                        @else
                            <a href="{{ $mediaUrl }}" target="_blank" class="media-file-box text-decoration-none"><i class="fas fa-file-alt"></i></a>
                        @endif
                    </td>
                    <td class="media-file-name">
                        <div class="font-weight-bold">{{ $media->name ?: $media->file_name }}</div>
                        <code class="small">{{ $media->file_name }}</code>
                        <div class="text-muted small">Disk: {{ $media->disk }} | ID: {{ $media->id }}</div>
                    </td>
                    <td class="media-owner">
                        <span class="badge badge-info">{{ $modelLabel }}</span>
                        <div class="font-weight-bold mt-1">{{ $ownerLabel }}</div>
                        <div class="text-muted small">Model ID: {{ $media->model_id }}</div>
                    </td>
                    <td>
                        <span class="badge badge-light border">{{ $collectionLabel }}</span>
                        <div class="text-muted small"><code>{{ $media->collection_name }}</code></div>
                    </td>
                    <td>
                        @if($isImage)
                            <span class="badge badge-success">Image</span>
                        @elseif($isVideo)
                            <span class="badge badge-warning">Video</span>
                        @else
                            <span class="badge badge-secondary">File</span>
                        @endif
                        <div class="text-muted small">{{ $media->mime_type }}</div>
                    </td>
                    <td>{{ $size }}</td>
                    <td>
                        @if($trashMode && isset($media->trashed_at))
                            {{ optional($media->trashed_at)->format('d M Y') }}
                            <div class="text-muted small">{{ optional($media->trashed_at)->format('h:i A') }}</div>
                        @else
                            {{ optional($media->created_at)->format('d M Y') }}
                            <div class="text-muted small">{{ optional($media->created_at)->format('h:i A') }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            @if($trashMode)
                                <button type="button"
                                        class="btn btn-success btn-media-restore"
                                        data-url="{{ route('admin.media-management.restore', $media->id) }}"
                                        title="Restore">
                                    <i class="fas fa-undo"></i>
                                </button>

                                <button type="button"
                                        class="btn btn-danger btn-media-force-delete"
                                        data-url="{{ route('admin.media-management.force-delete', $media->id) }}"
                                        title="Force Delete">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            @else
                                <button type="button"
                                        class="btn btn-info btn-media-edit"
                                        data-url="{{ route('admin.media-management.edit', $media->id) }}"
                                        title="Edit / Replace">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <a href="{{ route('admin.media-management.download', $media->id) }}"
                                   class="btn btn-secondary"
                                   title="Download">
                                    <i class="fas fa-download"></i>
                                </a>

                                <button type="button"
                                        class="btn btn-danger btn-media-delete"
                                        data-url="{{ route('admin.media-management.destroy', $media->id) }}"
                                        title="Move To Trash">
                                    <i class="fas fa-trash"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="fas fa-photo-video fa-2x mb-2 d-block"></i>
                        {{ $trashMode ? 'No trashed media files found.' : 'No media files found.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($mediaItems->hasPages())
    <div class="px-3 py-3 border-top bg-white">
        {{ $mediaItems->withQueryString()->links() }}
    </div>
@endif
