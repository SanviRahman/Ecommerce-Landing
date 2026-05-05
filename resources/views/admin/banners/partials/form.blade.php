<form id="ajaxForm" action="{{ $action }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="modal-header bg-light border-bottom-0">
        <h5 class="modal-title font-weight-bold">
            <i class="fas {{ $isEdit ? 'fa-edit text-primary' : 'fa-plus-circle text-success' }} mr-2"></i>
            {{ $isEdit ? 'Edit Banner' : 'Create New Banner' }}
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <div class="modal-body p-4">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label>Banner Image {!! $isEdit ? '' : '<span class="text-danger">*</span>' !!}</label>
                @if($isEdit)
                    <div class="mb-2">
                        <img src="{{ $banner->image }}" alt="Current Banner" class="img-thumbnail rounded" style="max-height: 120px;">
                    </div>
                @endif
                <input type="file" name="banner_image" class="form-control-file" accept="image/*" {{ $isEdit ? '' : 'required' }}>
                <small class="text-muted">Max size: 2MB (jpg, jpeg, png, webp).</small>
            </div>

            <div class="col-md-12 mb-3">
                <label>Title <small class="text-muted">(Optional)</small></label>
                <input type="text" name="title" class="form-control" value="{{ $isEdit ? $banner->title : '' }}" placeholder="Enter banner title">
            </div>

            <div class="col-md-6 mb-3">
                <label>Position <span class="text-danger">*</span></label>
                <select name="position" class="form-control" required>
                    <option value="" disabled {{ !$isEdit ? 'selected' : '' }}>Select Position</option>
                    <option value="hero" {{ ($isEdit && $banner->position == 'hero') ? 'selected' : '' }}>Hero (Top)</option>
                    <option value="middle" {{ ($isEdit && $banner->position == 'middle') ? 'selected' : '' }}>Middle</option>
                    <option value="footer" {{ ($isEdit && $banner->position == 'footer') ? 'selected' : '' }}>Footer</option>
                    <option value="popup" {{ ($isEdit && $banner->position == 'popup') ? 'selected' : '' }}>Popup</option>
                    <option value="review" {{ ($isEdit && $banner->position == 'review') ? 'selected' : '' }}>Review Section</option>
                    <option value="help" {{ ($isEdit && $banner->position == 'help') ? 'selected' : '' }}>Help Section</option>
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label>Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="{{ $isEdit ? $banner->sort_order : '0' }}" min="0">
            </div>

            <div class="col-md-12 mb-3">
                <label>Target Link <small class="text-muted">(Optional URL)</small></label>
                <input type="url" name="link" class="form-control" value="{{ $isEdit ? $banner->link : '' }}" placeholder="https://example.com/product/123">
            </div>

            <div class="col-md-12">
                <div class="custom-control custom-switch mt-2">
                    <input type="checkbox" class="custom-control-input" id="statusSwitch" name="status" value="1" {{ ($isEdit && $banner->status) || !$isEdit ? 'checked' : '' }}>
                    <label class="custom-control-label" for="statusSwitch">Active Status</label>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary px-4">
            <i class="fas fa-save mr-1"></i> {{ $isEdit ? 'Update Changes' : 'Save Banner' }}
        </button>
    </div>
</form>