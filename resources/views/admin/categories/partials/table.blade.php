<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light small text-uppercase font-weight-bold text-muted">
            <tr>
                <th width="40" class="text-center px-4">
                    <input type="checkbox" id="check_all" class="shadow-none cursor-pointer">
                </th>

                <th>Category Detail</th>
                <th>Slug</th>
                <th>Products</th>
                <th>Front View</th>
                <th>Status</th>
                <th width="160" class="text-right px-4">Actions</th>
            </tr>
        </thead>

        <tbody>
            @forelse($categories as $category)
            <tr class="{{ isset($isTrash) && $isTrash ? 'bg-light-red' : '' }}">
                <td class="text-center px-4">
                    <input type="checkbox" class="row-checkbox shadow-none cursor-pointer" value="{{ $category->id }}">
                </td>

                <td>
                    <div class="d-flex align-items-center">
                        <div class="mr-3 rounded border bg-white shadow-xs"
                            style="width: 55px; height: 55px; overflow: hidden; flex-shrink: 0;">
                            <img src="{{ $category->image }}" style="width: 100%; height: 100%; object-fit: cover;"
                                alt="{{ $category->name }}"
                                onerror="this.onerror=null;this.src='{{ asset('vendor/adminlte/dist/img/no-image.png') }}';">
                        </div>

                        <div>
                            <div class="font-weight-bold text-dark">
                                {{ $category->name }}
                            </div>

                            <div class="small text-muted">
                                ID: #{{ $category->id }}
                            </div>
                        </div>
                    </div>
                </td>

                <td>
                    <span class="text-muted">/{{ $category->slug }}</span>
                </td>

                <td>
                    <div class="font-weight-bold text-dark">
                        Total: {{ $category->products_count ?? 0 }}
                    </div>

                    <div class="small text-muted">
                        Active: {{ $category->active_products_count ?? 0 }}
                    </div>
                </td>

                <td>
                    @if($category->is_front_view)
                    <span class="badge badge-primary px-3">Front View</span>
                    @else
                    <span class="badge badge-light border text-muted px-3">Hidden</span>
                    @endif
                </td>

                <td>
                    @if(isset($isTrash) && $isTrash)
                    <span class="badge badge-danger px-3">Deleted</span>
                    @else
                    <span class="badge {{ $category->status ? 'badge-success' : 'badge-warning' }} px-3 shadow-xs">
                        {{ $category->status ? 'Active' : 'Inactive' }}
                    </span>
                    @endif
                </td>

                <td class="text-right px-4">
                    <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                        @if(isset($isTrash) && $isTrash)
                        <button type="button" class="btn btn-sm btn-white text-success btnRestore"
                            data-url="{{ route('admin.categories.restore', $category->id) }}" title="Restore">
                            <i class="fas fa-trash-restore"></i>
                        </button>

                        <button type="button" class="btn btn-sm btn-white text-danger btnForceDelete"
                            data-url="{{ route('admin.categories.force_delete', $category->id) }}"
                            title="Delete Forever">
                            <i class="fas fa-skull-crossbones"></i>
                        </button>
                        @else
                        <a href="{{ route('admin.categories.show', $category->id) }}"
                            class="btn btn-sm btn-white text-info" title="View">
                            <i class="fas fa-eye"></i>
                        </a>

                        <button type="button" class="btn btn-sm btn-white text-primary btnEdit"
                            data-url="{{ route('admin.categories.edit', $category->id) }}" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>

                        <button type="button" class="btn btn-sm btn-white text-danger btnDelete"
                            data-url="{{ route('admin.categories.destroy', $category->id) }}" title="Move to Trash">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center py-5">
                    <div class="py-4">
                        <i class="fas fa-list fa-3x text-light mb-3"></i>
                        <h6 class="text-muted">No categories found matching your criteria.</h6>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($categories->hasPages())
<div class="px-4 py-3 border-top bg-white d-flex justify-content-center">
    {!! $categories->appends(request()->all())->links('pagination::bootstrap-4') !!}
</div>
@endif

<style>
.bg-light-red {
    background-color: #fffafa;
}

.shadow-xs {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.cursor-pointer {
    cursor: pointer;
}

.btn-white {
    background: #fff;
    border: none;
    transition: 0.2s;
}

.btn-white:hover {
    background: #f8f9fa;
    transform: translateY(-1px);
}

.pagination {
    margin-bottom: 0;
}

.page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}

.page-link {
    color: #6c757d;
    border-radius: 5px !important;
    margin: 0 2px;
}
</style>