<form id="brandForm" action="{{ $action }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="form-group">
        <label for="name" class="font-weight-normal text-muted">Brand Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control border-light shadow-sm" id="name" name="name" 
               value="{{ $brand->name ?? '' }}" placeholder="Enter brand name" required>
    </div>

    <div class="form-group">
        <label for="slug" class="font-weight-normal text-muted">Slug (Optional)</label>
        <input type="text" class="form-control border-light shadow-sm" id="slug" name="slug" 
               value="{{ $brand->slug ?? '' }}" placeholder="Auto generated if empty">
        <small class="text-muted">Unique URL identifier. Leave blank to auto-generate from name.</small>
    </div>

    <div class="form-group mb-4">
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="status" name="status" value="1" 
                   {{ ($isEdit && $brand->status) || !$isEdit ? 'checked' : '' }}>
            <label class="custom-control-label font-weight-normal text-muted" for="status">Active Status</label>
        </div>
    </div>

    <div class="text-right border-top pt-3">
        <button type="button" class="btn btn-light px-4 mr-2" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary px-4 shadow-sm">
            <i class="fas fa-save mr-1"></i> {{ $isEdit ? 'Update Brand' : 'Save Brand' }}
        </button>
    </div>
</form>