<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light small text-uppercase font-weight-bold text-muted">
            <tr>
                <th width="40" class="text-center px-4">
                    <input type="checkbox" id="check_all" class="shadow-none cursor-pointer">
                </th>
                <th width="120">Banner</th>
                <th>Details</th>
                <th>Position & Order</th>
                <th>Status</th>
                <th width="120" class="text-right px-4">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($banners as $banner)
                <tr class="{{ isset($isTrash) && $isTrash ? 'bg-light-red' : '' }}">
                    <td class="text-center px-4">
                        <input type="checkbox" class="row-checkbox shadow-none cursor-pointer" value="{{ $banner->id }}">
                    </td>
                    <td>
                        <img src="{{ $banner->image }}" alt="Banner" class="img-thumbnail rounded" style="width: 100px; height: 50px; object-fit: cover;">
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark">{{ $banner->title ?? 'No Title' }}</div>
                        <div class="small text-muted">Link: <a href="{{ $banner->link }}" target="_blank" class="text-primary">{{ Str::limit($banner->link, 30) ?? 'N/A' }}</a></div>
                    </td>
                    <td>
                        <div class="text-uppercase font-weight-bold text-info small">{{ $banner->position }}</div>
                        <div class="small text-muted">Sort Order: {{ $banner->sort_order }}</div>
                    </td>
                    <td>
                        @if(isset($isTrash) && $isTrash)
                            <span class="badge badge-danger px-2 py-1 shadow-xs">Deleted</span>
                        @else
                            @if($banner->status)
                                <span class="badge badge-success px-2 py-1 shadow-xs">Active</span>
                            @else
                                <span class="badge badge-secondary px-2 py-1 shadow-xs">Inactive</span>
                            @endif
                        @endif
                    </td>
                    <td class="text-right px-4">
                        <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                            @if(isset($isTrash) && $isTrash)
                                <button type="button" class="btn btn-sm btn-white text-success btnAction" data-action="restore" data-url="{{ route('admin.banners.restore', $banner->id) }}" title="Restore">
                                    <i class="fas fa-trash-restore"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-danger btnAction" data-action="force_delete" data-url="{{ route('admin.banners.force_delete', $banner->id) }}" title="Delete Permanently">
                                    <i class="fas fa-skull-crossbones"></i>
                                </button>
                            @else
                                <button type="button" class="btn btn-sm btn-white text-info btnModal" data-url="{{ route('admin.banners.show', $banner->id) }}" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-primary btnModal" data-url="{{ route('admin.banners.edit', $banner->id) }}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-danger btnAction" data-action="delete" data-url="{{ route('admin.banners.destroy', $banner->id) }}" title="Move to Trash">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="fas fa-images fa-3x text-light mb-3"></i>
                        <h6 class="text-muted">No banners found matching your criteria.</h6>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($banners->hasPages())
    <div class="px-4 py-3 border-top bg-white d-flex justify-content-center">
        {!! $banners->appends(request()->all())->links('pagination::bootstrap-4') !!}
    </div>
@endif