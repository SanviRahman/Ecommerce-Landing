<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead class="thead-light">
            <tr>
                <th style="width: 45px;">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="check_all">
                        <label class="custom-control-label" for="check_all"></label>
                    </div>
                </th>
                <th style="width: 70px;">SL</th>
                <th>Name</th>
                <th>Platform</th>
                <th>Pixel ID</th>
                <th>Script</th>
                <th style="width: 120px;">Status</th>
                <th style="width: 230px;">Action</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($trackingPixels as $trackingPixel)
                <tr>
                    <td>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox"
                                   class="custom-control-input row-checkbox"
                                   id="pixel_check_{{ $trackingPixel->id }}"
                                   value="{{ $trackingPixel->id }}">
                            <label class="custom-control-label" for="pixel_check_{{ $trackingPixel->id }}"></label>
                        </div>
                    </td>

                    <td>{{ $trackingPixels->firstItem() + $loop->index }}</td>

                    <td>
                        <strong>{{ $trackingPixel->name ?? 'N/A' }}</strong>
                    </td>

                    <td>
                        <span class="badge badge-info">
                            {{ $platforms[$trackingPixel->platform] ?? ucfirst($trackingPixel->platform) }}
                        </span>
                    </td>

                    <td>
                        <code>{{ $trackingPixel->pixel_id }}</code>
                    </td>

                    <td>
                        <div class="script-preview text-muted">
                            {{ \Illuminate\Support\Str::limit(strip_tags($trackingPixel->script_code), 80) }}
                        </div>
                    </td>

                    <td>
                        @if (! $isTrash)
                            <div class="custom-control custom-switch">
                                <input type="checkbox"
                                       class="custom-control-input status-switch"
                                       id="status_{{ $trackingPixel->id }}"
                                       data-url="{{ route('admin.tracking-pixels.update_status', $trackingPixel->id) }}"
                                       @checked($trackingPixel->status)>
                                <label class="custom-control-label" for="status_{{ $trackingPixel->id }}">
                                    {{ $trackingPixel->status ? 'Active' : 'Inactive' }}
                                </label>
                            </div>
                        @else
                            @if ($trackingPixel->status)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-danger">Inactive</span>
                            @endif
                        @endif
                    </td>

                    <td>
                        @if (! $isTrash)
                            <a href="{{ route('admin.tracking-pixels.show', $trackingPixel->id) }}"
                               class="btn btn-sm btn-dark"
                               title="Details">
                                <i class="fas fa-eye"></i>
                            </a>

                            <a href="{{ route('admin.tracking-pixels.edit', $trackingPixel->id) }}"
                               class="btn btn-sm btn-info"
                               title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>

                            <button type="button"
                                    class="btn btn-sm btn-danger btnDelete"
                                    data-url="{{ route('admin.tracking-pixels.destroy', $trackingPixel->id) }}"
                                    title="Move to Trash">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        @else
                            <button type="button"
                                    class="btn btn-sm btn-success btnRestore"
                                    data-url="{{ route('admin.tracking-pixels.restore', $trackingPixel->id) }}"
                                    title="Restore">
                                <i class="fas fa-trash-restore"></i>
                            </button>

                            <button type="button"
                                    class="btn btn-sm btn-danger btnForceDelete"
                                    data-url="{{ route('admin.tracking-pixels.force_delete', $trackingPixel->id) }}"
                                    title="Purge Permanently">
                                <i class="fas fa-times"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No tracking pixels found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($trackingPixels->hasPages())
    <div class="px-4 py-3 border-top bg-white">
        {{ $trackingPixels->withQueryString()->links() }}
    </div>
@endif