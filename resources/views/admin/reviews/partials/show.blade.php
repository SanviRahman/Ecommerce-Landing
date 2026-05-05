<div class="modal-header bg-light border-bottom-0">
    <h5 class="modal-title font-weight-bold">
        <i class="fas fa-comment-dots text-info mr-2"></i> Review Details
    </h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<div class="modal-body p-0">
    <div class="bg-dark p-4 d-flex align-items-center">
        <img src="{{ $review->customer_image }}" alt="Customer" class="img-thumbnail rounded-circle shadow border-0" style="width: 80px; height: 80px; object-fit: cover;">
        <div class="ml-3">
            <h5 class="mb-0 text-white font-weight-bold">{{ $review->customer_name }}</h5>
            <div class="text-light small mt-1">
                <i class="fas fa-map-marker-alt text-danger"></i> {{ $review->location ?? 'Location not provided' }}
            </div>
        </div>
    </div>
    
    <div class="p-4 bg-white border-bottom">
        <div class="text-orange mb-2" style="font-size: 1.2rem;">
            @for($i=1; $i<=5; $i++)
                <i class="fa{{ $i <= $review->rating ? 's' : 'r' }} fa-star"></i>
            @endfor
            <span class="text-dark small ml-2 font-weight-bold">({{ $review->rating }} / 5)</span>
        </div>
        <p class="font-italic text-muted mb-0" style="font-size: 1.1rem; line-height: 1.6;">
            "{{ $review->review_text ?? 'No written feedback provided.' }}"
        </p>
    </div>

    <table class="table table-borderless table-striped mb-0">
        <tbody>
            <tr>
                <th width="35%" class="text-muted px-4 py-3 border-bottom">Linked Campaign</th>
                <td class="px-4 py-3 border-bottom font-weight-bold text-primary">
                    {{ $review->campaign->title ?? 'General / Store Review' }}
                </td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3 border-bottom">Social Link</th>
                <td class="px-4 py-3 border-bottom">
                    @if($review->social_link)
                        <a href="{{ $review->social_link }}" target="_blank" class="btn btn-sm btn-outline-info shadow-none"><i class="fas fa-link"></i> Visit Profile</a>
                    @else
                        <span class="text-muted">N/A</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3 border-bottom">Visibility Status</th>
                <td class="px-4 py-3 border-bottom">
                    @if($review->status)
                        <span class="badge badge-success px-3 py-1">Published</span>
                    @else
                        <span class="badge badge-secondary px-3 py-1">Draft / Hidden</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3">Submitted On</th>
                <td class="px-4 py-3">{{ $review->created_at->format('d M, Y h:i A') }}</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="modal-footer bg-light border-top-0">
    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Close</button>
</div>