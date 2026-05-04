<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light small text-uppercase font-weight-bold text-muted">
            <tr>
                <th width="40" class="text-center px-4">
                    <input type="checkbox" id="check_all" class="shadow-none cursor-pointer">
                </th>
                <th>Brand Name</th>
                <th>Slug</th>
                <th>Status</th>
                <th width="160" class="text-right px-4">Actions</th>
            </tr>
        </thead>

        <tbody>
            @forelse($brands as $brand)
                <tr class="{{ isset($isTrash) && $isTrash ? 'bg-light-red' : '' }}">
                    <td class="text-center px-4">
                        <input type="checkbox" class="row-checkbox shadow-none cursor-pointer" value="{{ $brand->id }}">
                    </td>

                    <td>
                        <div class="font-weight-bold text-dark">
                            <i class="fas fa-tag text-muted mr-2"></i> {{ $brand->name }}
                        </div>
                    </td>

                    <td class="text-muted">
                        /{{ $brand->slug }}
                    </td>

                    <td>
                        @if(isset($isTrash) && $isTrash)
                            <span class="badge badge-danger px-3">Deleted</span>
                        @else
                            <span class="badge {{ $brand->status ? 'badge-success' : 'badge-warning' }} px-3 shadow-xs">
                                {{ $brand->status ? 'Active' : 'Inactive' }}
                            </span>
                        @endif
                    </td>

                    <td class="text-right px-4">
                        <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                            @if(isset($isTrash) && $isTrash)
                                <button type="button" class="btn btn-sm btn-white text-success btnRestore"
                                    data-url="{{ route('admin.brands.restore', $brand->id) }}" title="Restore">
                                    <i class="fas fa-trash-restore"></i>
                                </button>

                                <button type="button" class="btn btn-sm btn-white text-danger btnForceDelete"
                                    data-url="{{ route('admin.brands.force_delete', $brand->id) }}" title="Delete Forever">
                                    <i class="fas fa-skull-crossbones"></i>
                                </button>
                            @else
                                {{-- View/Show Button Added Here --}}
                                <a href="{{ route('admin.brands.show', $brand->id) }}" class="btn btn-sm btn-white text-info" title="View Brand">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <button type="button" class="btn btn-sm btn-white text-primary btnEdit"
                                    data-url="{{ route('admin.brands.edit', $brand->id) }}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <button type="button" class="btn btn-sm btn-white text-danger btnDelete"
                                    data-url="{{ route('admin.brands.destroy', $brand->id) }}" title="Move to Trash">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <div class="py-4">
                            <i class="fas fa-tags fa-3x text-light mb-3"></i>
                            <h6 class="text-muted">No brands found matching your criteria.</h6>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($brands->hasPages())
    <div class="px-4 py-3 border-top bg-white d-flex justify-content-center">
        {!! $brands->appends(request()->all())->links('pagination::bootstrap-4') !!}
    </div>
@endif