<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light small text-uppercase font-weight-bold text-muted">
            <tr>
                <th width="40" class="text-center px-4">
                    <input type="checkbox" id="check_all" class="shadow-none cursor-pointer">
                </th>
                <th width="80">Banner</th>
                <th>Page Details</th>
                <th>Status</th>
                <th>Created At</th>
                <th width="120" class="text-right px-4">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pages as $page)
                <tr class="{{ isset($isTrash) && $isTrash ? 'bg-light-red' : '' }}">
                    <td class="text-center px-4">
                        <input type="checkbox" class="row-checkbox shadow-none cursor-pointer" value="{{ $page->id }}">
                    </td>
                    <td>
                        <img src="{{ $page->banner }}" alt="Banner" class="img-thumbnail rounded" style="width: 50px; height: 50px; object-fit: cover;">
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark">{{ $page->page_name }}</div>
                        <div class="small text-muted">Slug: <span class="text-primary">{{ $page->slug }}</span></div>
                    </td>
                    <td>
                        @if(isset($isTrash) && $isTrash)
                            <span class="badge badge-danger px-2 py-1 shadow-xs">Deleted</span>
                        @else
                            @if($page->status)
                                <span class="badge badge-success px-2 py-1 shadow-xs">Active</span>
                            @else
                                <span class="badge badge-secondary px-2 py-1 shadow-xs">Inactive</span>
                            @endif
                        @endif
                    </td>
                    <td>
                        <div class="small text-muted">{{ $page->created_at->format('d M, Y') }}</div>
                    </td>
                    <td class="text-right px-4">
                        <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                            @if(isset($isTrash) && $isTrash)
                                <button type="button" class="btn btn-sm btn-white text-success btnAction" data-action="restore" data-url="{{ route('admin.pages.restore', $page->id) }}" title="Restore">
                                    <i class="fas fa-trash-restore"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-danger btnAction" data-action="force_delete" data-url="{{ route('admin.pages.force_delete', $page->id) }}" title="Delete Permanently">
                                    <i class="fas fa-skull-crossbones"></i>
                                </button>
                            @else
                                <a href="{{ route('admin.pages.show', $page->id) }}" class="btn btn-sm btn-white text-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.pages.edit', $page->id) }}" class="btn btn-sm btn-white text-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-white text-danger btnAction" data-action="delete" data-url="{{ route('admin.pages.destroy', $page->id) }}" title="Move to Trash">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="fas fa-file-alt fa-3x text-light mb-3"></i>
                        <h6 class="text-muted">No pages found matching your criteria.</h6>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($pages->hasPages())
    <div class="px-4 py-3 border-top bg-white d-flex justify-content-center">
        {!! $pages->appends(request()->all())->links('pagination::bootstrap-4') !!}
    </div>
@endif