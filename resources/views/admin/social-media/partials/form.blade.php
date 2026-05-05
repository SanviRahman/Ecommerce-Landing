<form id="ajaxForm" action="{{ $action }}" method="POST">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="modal-header bg-light border-bottom-0">
        <h5 class="modal-title font-weight-bold">
            <i class="fas {{ $isEdit ? 'fa-edit text-primary' : 'fa-plus-circle text-success' }} mr-2"></i>
            {{ $isEdit ? 'Edit Social Link' : 'Add Social Link' }}
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <div class="modal-body p-4">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label>Platform Name <span class="text-danger">*</span></label>
                <input type="text" name="platform_name" class="form-control" value="{{ $isEdit ? $socialMedia->platform_name : '' }}" placeholder="e.g. Facebook, Instagram, YouTube" required>
            </div>

            <div class="col-md-12 mb-3">
                <label>Target URL (Link) <span class="text-danger">*</span></label>
                <input type="url" name="link" class="form-control" value="{{ $isEdit ? $socialMedia->link : '' }}" placeholder="https://facebook.com/yourpage" required>
            </div>

            <div class="col-md-12 mb-3">
                <label>FontAwesome Icon Class <small class="text-muted">(Optional)</small></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="{{ $isEdit ? $socialMedia->icon_class : 'fas fa-icons' }}" id="iconPreview"></i></span>
                    </div>
                    <input type="text" name="icon_class" id="iconInput" class="form-control" value="{{ $isEdit ? $socialMedia->icon_class : '' }}" placeholder="e.g. fab fa-facebook">
                </div>
                <small class="text-muted d-block mt-1">Get icon classes from <a href="https://fontawesome.com/icons" target="_blank">FontAwesome</a>.</small>
            </div>

            <div class="col-md-12 mt-2 border-top pt-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="statusSwitch" name="status" value="1" {{ ($isEdit && $socialMedia->status) || !$isEdit ? 'checked' : '' }}>
                    <label class="custom-control-label" for="statusSwitch">Active Status</label>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary px-4">
            <i class="fas fa-save mr-1"></i> {{ $isEdit ? 'Update Link' : 'Save Link' }}
        </button>
    </div>
</form>

<script>
    // Live Icon Preview
    $('#iconInput').on('input', function() {
        let val = $(this).val();
        if(val) {
            $('#iconPreview').attr('class', val + ' text-dark');
        } else {
            $('#iconPreview').attr('class', 'fas fa-icons text-muted');
        }
    });
</script>