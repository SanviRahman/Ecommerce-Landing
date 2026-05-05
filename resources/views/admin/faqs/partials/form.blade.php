<form id="ajaxForm" action="{{ $action }}" method="POST">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="modal-header bg-light border-bottom-0">
        <h5 class="modal-title font-weight-bold">
            <i class="fas {{ $isEdit ? 'fa-edit text-primary' : 'fa-plus-circle text-success' }} mr-2"></i>
            {{ $isEdit ? 'Edit FAQ' : 'Create New FAQ' }}
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <div class="modal-body p-4">
        <div class="row">
            <div class="col-md-8 mb-3">
                <label>Select Campaign <small class="text-muted">(Optional)</small></label>
                <select name="campaign_id" class="form-control">
                    <option value="">General FAQ (Applies globally)</option>
                    @foreach($campaigns as $camp)
                        <option value="{{ $camp->id }}" {{ ($isEdit && $faq->campaign_id == $camp->id) ? 'selected' : '' }}>
                            {{ $camp->title }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">If selected, this FAQ will only show on that specific landing page.</small>
            </div>

            <div class="col-md-4 mb-3">
                <label>Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="{{ $isEdit ? $faq->sort_order : '0' }}" min="0">
                <small class="text-muted">Lower number = higher priority.</small>
            </div>

            <div class="col-md-12 mb-3">
                <label>Question <span class="text-danger">*</span></label>
                <input type="text" name="question" class="form-control font-weight-bold" value="{{ $isEdit ? $faq->question : '' }}" placeholder="e.g. How long does shipping take?" required>
            </div>

            <div class="col-md-12 mb-3">
                <label>Answer <span class="text-danger">*</span></label>
                <textarea name="answer" class="form-control" rows="5" placeholder="Write the answer here..." required>{{ $isEdit ? $faq->answer : '' }}</textarea>
                <small class="text-muted">You can use basic HTML tags like &lt;br&gt; or &lt;b&gt; for formatting.</small>
            </div>

            <div class="col-md-12 mt-2 border-top pt-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="statusSwitch" name="status" value="1" {{ ($isEdit && $faq->status) || !$isEdit ? 'checked' : '' }}>
                    <label class="custom-control-label" for="statusSwitch">Publish this FAQ</label>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary px-4">
            <i class="fas fa-save mr-1"></i> {{ $isEdit ? 'Update FAQ' : 'Save FAQ' }}
        </button>
    </div>
</form>