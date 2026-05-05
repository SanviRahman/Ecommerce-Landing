<form id="ajaxForm" action="{{ $action }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="modal-header bg-light border-bottom-0">
        <h5 class="modal-title font-weight-bold">
            <i class="fas {{ $isEdit ? 'fa-edit text-primary' : 'fa-plus-circle text-success' }} mr-2"></i>
            {{ $isEdit ? 'Edit Review' : 'Add New Review' }}
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <div class="modal-body p-4">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label>Select Campaign (Landing Page) <small class="text-muted">(Optional)</small></label>
                <select name="campaign_id" class="form-control">
                    <option value="">General / Store Review</option>
                    @foreach($campaigns as $camp)
                        <option value="{{ $camp->id }}" {{ ($isEdit && $review->campaign_id == $camp->id) ? 'selected' : '' }}>
                            {{ $camp->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label>Customer Name <span class="text-danger">*</span></label>
                <input type="text" name="customer_name" class="form-control" value="{{ $isEdit ? $review->customer_name : '' }}" placeholder="Enter name" required>
            </div>

            <div class="col-md-6 mb-3">
                <label>Rating (Out of 5) <span class="text-danger">*</span></label>
                <select name="rating" class="form-control" required>
                    <option value="5" {{ ($isEdit && $review->rating == 5) || !$isEdit ? 'selected' : '' }}>5 Stars - Excellent</option>
                    <option value="4" {{ ($isEdit && $review->rating == 4) ? 'selected' : '' }}>4 Stars - Good</option>
                    <option value="3" {{ ($isEdit && $review->rating == 3) ? 'selected' : '' }}>3 Stars - Average</option>
                    <option value="2" {{ ($isEdit && $review->rating == 2) ? 'selected' : '' }}>2 Stars - Poor</option>
                    <option value="1" {{ ($isEdit && $review->rating == 1) ? 'selected' : '' }}>1 Star - Terrible</option>
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label>Location / City <small class="text-muted">(Optional)</small></label>
                <input type="text" name="location" class="form-control" value="{{ $isEdit ? $review->location : '' }}" placeholder="e.g. Dhaka">
            </div>

            <div class="col-md-6 mb-3">
                <label>Social Link <small class="text-muted">(Optional Facebook/Insta link)</small></label>
                <input type="url" name="social_link" class="form-control" value="{{ $isEdit ? $review->social_link : '' }}" placeholder="https://facebook.com/user">
            </div>

            <div class="col-md-12 mb-3">
                <label>Review Text <small class="text-muted">(Optional)</small></label>
                <textarea name="review_text" class="form-control" rows="3" placeholder="Write customer review here...">{{ $isEdit ? $review->review_text : '' }}</textarea>
            </div>

            <div class="col-md-8 mb-3">
                <label>Customer Photo <small class="text-muted">(Optional)</small></label>
                <input type="file" name="customer_image" class="form-control-file" accept="image/*">
                <small class="text-muted">Upload square image for best look.</small>
            </div>
            
            @if($isEdit)
                <div class="col-md-4 mb-3 text-right">
                    <img src="{{ $review->customer_image }}" alt="Current Photo" class="img-thumbnail rounded-circle" style="max-height: 60px;">
                </div>
            @endif

            <div class="col-md-12 mt-2 border-top pt-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="statusSwitch" name="status" value="1" {{ ($isEdit && $review->status) || !$isEdit ? 'checked' : '' }}>
                    <label class="custom-control-label" for="statusSwitch">Publish this review on website</label>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary px-4">
            <i class="fas fa-save mr-1"></i> {{ $isEdit ? 'Update Review' : 'Save Review' }}
        </button>
    </div>
</form>