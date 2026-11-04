@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Check the following fields:</strong>
        <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<form action="{{ $action }}" method="POST" id="sfa-pixel-form">
    @csrf
    @if ($isEdit)
        @method('PUT')
        @include('admin.tracking-pixels.partials.fields', [
            'fieldPrefix' => '', 'fieldOldPrefix' => '', 'pixelData' => $trackingPixel,
            'isEdit' => true,
        ])
    @else
        <p class="text-muted">Add multiple independent tracking integrations. For Google Tag Manager, select its platform and enter the GTM Container ID; the full script is optional. Meta CAPI remains available only for Meta integrations.</p>
        <div id="sfa-pixel-entries">
            @foreach(old('pixels', [['platform'=>'meta', 'status'=>1, 'capi_enabled'=>0]]) as $index => $row)
                @include('admin.tracking-pixels.partials.fields', [
                    'fieldPrefix' => 'pixels['.$index.']', 'fieldOldPrefix' => 'pixels.'.$index,
                    'rowData' => is_array($row) ? $row : [], 'isEdit' => false,
                ])
            @endforeach
        </div>
        <button type="button" id="sfa-add-pixel" class="btn btn-outline-primary mb-3"><i class="fas fa-plus mr-1"></i>Add More Integration</button>
        <template id="sfa-pixel-template">
            @include('admin.tracking-pixels.partials.fields', [
                'fieldPrefix' => 'pixels[__INDEX__]', 'fieldOldPrefix' => 'pixels.__INDEX__',
                'rowData' => ['platform'=>'meta','status'=>1,'capi_enabled'=>0], 'isEdit' => false,
            ])
        </template>
    @endif
    <div class="border-top pt-3">
        <button type="submit" class="btn btn-success px-4"><i class="fas fa-save mr-1"></i>{{ $isEdit ? 'Update Integration' : 'Save Tracking Integrations' }}</button>
        <a href="{{ route('admin.tracking-pixels.index') }}" class="btn btn-secondary px-4"><i class="fas fa-times mr-1"></i>Cancel</a>
    </div>
</form>
<script src="{{ asset('frontend/js/admin-tracking-pixels.js') }}?v=20261104-gtm-admin-v1" defer></script>
