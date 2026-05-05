<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light small text-uppercase font-weight-bold text-muted">
            <tr>
                <th width="40" class="text-center px-4">
                    <input type="checkbox" id="check_all" class="shadow-none cursor-pointer">
                </th>
                <th>Platform</th>
                <th>Target URL</th>
                <th>Status</th>
                <th width="120" class="text-right px-4">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($socialMedias as $social)
                <tr class="{{ isset($isTrash) && $isTrash ? 'bg-light-red' : '' }}">
                    <td class="text-center px-4">
                        <input type="checkbox" class="row-checkbox shadow-none cursor-pointer" value="{{ $social->id }}">
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark d-flex align-items-center">
                            @if($social->icon_class)
                                <i class="{{ $social->icon_class }} fa-lg text-primary mr-2" style="width: 25px; text-align: center;"></i>
                            @else
                                <i class="fas fa-link fa-lg text-secondary mr-2" style="width: 25px; text-align: center;"></i>
                            @endif
                            {{ $social->platform_name }}
                        </div>
                    </td>
                    <td>
                        <a href="{{ $social->link }}" target="_blank" class="small text-info text-decoration-none">
                            {{ Str::limit($social->link, 40) }} <i class="fas fa-external-link-alt ml-1" style="font-size: 10px;"></i>
                        </a>
                    </td>
                    <td>
                        @if(isset($isTrash) && $isTrash)
                            <span class="badge badge-danger px-2 py-1 shadow-xs">Deleted</span>
                        @else
                            @if($social->status)
                                <span class="badge badge-success px-2 py-1 shadow-xs">Active</span>
                            @else
                                <span class="badge badge-secondary px-2 py-1 shadow-xs">Inactive</span>
                            @endif
                        @endif
                    </td>
                    <td class="text-right px-4">
                        <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                            @if(isset($isTrash) && $isTrash)
                                <button type="button" class="btn btn-sm btn-white text-success btnAction" data-action="restore" data-url="{{ route('admin.social-media.restore', $social->id) }}" title="Restore">
                                    <i class="fas fa-trash-restore"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-danger btnAction" data-action="force_delete" data-url="{{ route('admin.social-media.force_delete', $social->id) }}" title="Delete Permanently">
                                    <i class="fas fa-skull-crossbones"></i>
                                </button>
                            @else
                                <button type="button" class="btn btn-sm btn-white text-info btnModal" data-url="{{ route('admin.social-media.show', $social->id) }}" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-primary btnModal" data-url="{{ route('admin.social-media.edit', $social->id) }}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-danger btnAction" data-action="delete" data-url="{{ route('admin.social-media.destroy', $social->id) }}" title="Move to Trash">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <i class="fas fa-share-alt text-light fa-3x mb-3"></i>
                        <h6 class="text-muted">No social media links found.</h6>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($socialMedias->hasPages())
    <div class="px-4 py-3 border-top bg-white d-flex justify-content-center">
        {!! $socialMedias->appends(request()->all())->links('pagination::bootstrap-4') !!}
    </div>
@endif