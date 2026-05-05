<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light small text-uppercase font-weight-bold text-muted">
            <tr>
                <th width="40" class="text-center px-4">
                    <input type="checkbox" id="check_all" class="shadow-none cursor-pointer">
                </th>
                <th width="40%">Question & Answer</th>
                <th>Campaign (Placement)</th>
                <th>Sort Order</th>
                <th>Status</th>
                <th width="120" class="text-right px-4">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($faqs as $faq)
                <tr class="{{ isset($isTrash) && $isTrash ? 'bg-light-red' : '' }}">
                    <td class="text-center px-4">
                        <input type="checkbox" class="row-checkbox shadow-none cursor-pointer" value="{{ $faq->id }}">
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark mb-1">
                            <i class="fas fa-question-circle text-info mr-1"></i> {{ $faq->question }}
                        </div>
                        <div class="small text-muted text-wrap" style="max-height: 2.8em; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                            {{ strip_tags($faq->answer) ?? 'No answer provided.' }}
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-light border shadow-xs px-2 py-1">
                            {{ $faq->campaign->title ?? 'General FAQ' }}
                        </span>
                    </td>
                    <td>
                        <span class="font-weight-bold text-muted">{{ $faq->sort_order }}</span>
                    </td>
                    <td>
                        @if(isset($isTrash) && $isTrash)
                            <span class="badge badge-danger px-2 py-1 shadow-xs">Deleted</span>
                        @else
                            @if($faq->status)
                                <span class="badge badge-success px-2 py-1 shadow-xs">Active</span>
                            @else
                                <span class="badge badge-secondary px-2 py-1 shadow-xs">Inactive</span>
                            @endif
                        @endif
                    </td>
                    <td class="text-right px-4">
                        <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                            @if(isset($isTrash) && $isTrash)
                                <button type="button" class="btn btn-sm btn-white text-success btnAction" data-action="restore" data-url="{{ route('admin.faqs.restore', $faq->id) }}" title="Restore">
                                    <i class="fas fa-trash-restore"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-danger btnAction" data-action="force_delete" data-url="{{ route('admin.faqs.force_delete', $faq->id) }}" title="Delete Permanently">
                                    <i class="fas fa-skull-crossbones"></i>
                                </button>
                            @else
                                <button type="button" class="btn btn-sm btn-white text-info btnModal" data-url="{{ route('admin.faqs.show', $faq->id) }}" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-primary btnModal" data-url="{{ route('admin.faqs.edit', $faq->id) }}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-danger btnAction" data-action="delete" data-url="{{ route('admin.faqs.destroy', $faq->id) }}" title="Move to Trash">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="fas fa-question text-light fa-3x mb-3"></i>
                        <h6 class="text-muted">No FAQs found matching your criteria.</h6>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($faqs->hasPages())
    <div class="px-4 py-3 border-top bg-white d-flex justify-content-center">
        {!! $faqs->appends(request()->all())->links('pagination::bootstrap-4') !!}
    </div>
@endif