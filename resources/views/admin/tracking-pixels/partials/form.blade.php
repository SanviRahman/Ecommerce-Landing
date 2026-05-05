@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{!! $error !!}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ $action }}" method="POST">
    @csrf

    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="form-group">
        <label>Platform <span class="text-danger">*</span></label>
        <select name="platform" class="form-control" required>
            @foreach ($platforms as $key => $label)
                <option value="{{ $key }}"
                    @selected(old('platform', $trackingPixel->platform ?? 'meta') === $key)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Name</label>
        <input type="text"
               name="name"
               value="{{ old('name', $trackingPixel->name ?? '') }}"
               class="form-control"
               placeholder="Example: Main Facebook Pixel">
    </div>

    <div class="form-group">
        <label>Manual Pixel ID / Container ID</label>
        <input type="text"
               name="pixel_id"
               value="{{ old('pixel_id', $trackingPixel->pixel_id ?? '') }}"
               class="form-control"
               placeholder="Optional. Example: 2355381278153504">
        <small class="form-text text-muted">
            For Meta Pixel, ID will be extracted from script automatically. For GTM/TikTok/GA, you can put ID here if needed.
        </small>
    </div>

    <div class="form-group">
        <label>
            Full Tracking Script <span class="text-danger">*</span>
        </label>

        <textarea name="script_code"
                  class="form-control @error('script_code') is-invalid @enderror"
                  rows="14"
                  required
                  placeholder="Paste full Meta Pixel script here. You can paste multiple Meta Pixel scripts on create page.">{{ old('script_code', $trackingPixel->script_code ?? '') }}</textarea>

        <small class="form-text text-muted">
            Full script will be saved in database and rendered dynamically in user/frontend view.
        </small>

        @error('script_code')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>

    <div class="custom-control custom-switch mb-3">
        <input type="checkbox"
               name="status"
               value="1"
               class="custom-control-input"
               id="status"
               @checked(old('status', $trackingPixel->status ?? true))>
        <label class="custom-control-label" for="status">Active</label>
    </div>

    <div class="border-top pt-3">
        <button type="submit" class="btn btn-success px-4">
            <i class="fas fa-save mr-1"></i>
            {{ $isEdit ? 'Update Pixel' : 'Save Pixel' }}
        </button>

        <a href="{{ route('admin.tracking-pixels.index') }}" class="btn btn-secondary px-4">
            <i class="fas fa-times mr-1"></i> Cancel
        </a>
    </div>
</form>