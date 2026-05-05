<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light small text-uppercase font-weight-bold text-muted">
            <tr>
                <th width="40" class="text-center px-4">
                    <input type="checkbox" id="check_all" class="shadow-none cursor-pointer">
                </th>
                <th>Customer Details</th>
                <th>Campaign & Rating</th>
                <th width="30%">Review Text</th>
                <th>Status</th>
                <th width="120" class="text-right px-4">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reviews as $review)
                <tr class="{{ isset($isTrash) && $isTrash ? 'bg-light-red' : '' }}">
                    <td class="text-center px-4">
                        <input type="checkbox" class="row-checkbox shadow-none cursor-pointer" value="{{ $review->id }}">
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="{{ $review->customer_image }}" alt="Customer" class="img-thumbnail rounded-circle mr-3" style="width: 45px; height: 45px; object-fit: cover;">
                            <div>
                                <div class="font-weight-bold text-dark">{{ $review->customer_name }}</div>
                                <div class="small text-muted"><i class="fas fa-map-marker-alt text-danger"></i> {{ $review->location ?? 'Unknown' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="small text-muted mb-1">{{ $review->campaign->title ?? 'General/Store Review' }}</div>
                        <div class="text-orange small">
                            @for($i=1; $i<=5; $i++)
                                <i class="fa{{ $i <= $review->rating ? 's' : 'r' }} fa-star"></i>
                            @endfor
                            <span class="text-dark ml-1 font-weight-bold">({{ $review->rating }})</span>
                        </div>
                    </td>
                    <td>
                        <div class="small text-muted text-wrap" style="max-height: 3.6em; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                            "{{ $review->review_text ?? 'No text provided.' }}"
                        </div>
                    </td>
                    <td>
                        @if(isset($isTrash) && $isTrash)
                            <span class="badge badge-danger px-2 py-1 shadow-xs">Deleted</span>
                        @else
                            @if($review->status)
                                <span class="badge badge-success px-2 py-1 shadow-xs">Active</span>
                            @else
                                <span class="badge badge-secondary px-2 py-1 shadow-xs">Inactive</span>
                            @endif
                        @endif
                    </td>
                    <td class="text-right px-4">
                        <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                            @if(isset($isTrash) && $isTrash)
                                <button type="button" class="btn btn-sm btn-white text-success btnAction" data-action="restore" data-url="{{ route('admin.reviews.restore', $review->id) }}" title="Restore">
                                    <i class="fas fa-trash-restore"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-danger btnAction" data-action="force_delete" data-url="{{ route('admin.reviews.force_delete', $review->id) }}" title="Delete Permanently">
                                    <i class="fas fa-skull-crossbones"></i>
                                </button>
                            @else
                                <button type="button" class="btn btn-sm btn-white text-info btnModal" data-url="{{ route('admin.reviews.show', $review->id) }}" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-primary btnModal" data-url="{{ route('admin.reviews.edit', $review->id) }}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-white text-danger btnAction" data-action="delete" data-url="{{ route('admin.reviews.destroy', $review->id) }}" title="Move to Trash">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="fas fa-star-half-alt fa-3x text-light mb-3"></i>
                        <h6 class="text-muted">No reviews found matching your criteria.</h6>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($reviews->hasPages())
    <div class="px-4 py-3 border-top bg-white d-flex justify-content-center">
        {!! $reviews->appends(request()->all())->links('pagination::bootstrap-4') !!}
    </div>
@endif