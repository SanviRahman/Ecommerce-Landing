@php
    $custom = $media->custom_properties ?? [];
    $isImage = str_starts_with((string) $media->mime_type, 'image/');
    $isVideo = str_starts_with((string) $media->mime_type, 'video/');
@endphp

<div class="modal-header">
    <h5 class="modal-title font-weight-bold">
        <i class="fas fa-edit text-primary mr-1"></i>
        Edit Media
    </h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<div class="modal-body">
    <div class="row">
        <div class="col-md-5 mb-3">
            <div class="border rounded p-3 bg-light text-center">
                @if($isImage)
                    <img src="{{ $url }}" alt="{{ $media->name }}" class="modal-media-preview">
                @elseif($isVideo)
                    <video controls class="modal-media-preview">
                        <source src="{{ $url }}" type="{{ $media->mime_type }}">
                    </video>
                @else
                    <div class="py-5 text-muted">
                        <i class="fas fa-file-alt fa-4x"></i>
                        <div class="mt-2">{{ $media->mime_type }}</div>
                    </div>
                @endif
            </div>

            <div class="mt-3 small">
                <div><strong>Owner:</strong> {{ $modelLabel }} — {{ $ownerLabel }}</div>
                <div><strong>Collection:</strong> {{ $collectionLabel }}</div>
                <div><strong>File:</strong> <code>{{ $media->file_name }}</code></div>
                <div><strong>Disk:</strong> {{ $media->disk }}</div>
                <div><strong>Size:</strong> {{ number_format((int) ($media->size ?? 0) / 1024, 2) }} KB</div>
            </div>
        </div>

        <div class="col-md-7">
            <form id="media-info-form" action="{{ route('admin.media-management.update', $media->id) }}" method="POST">
                @csrf
                @method('PATCH')

                <div class="form-group">
                    <label>Media Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ $media->name }}" required>
                </div>

                <div class="form-group">
                    <label>Alt Text</label>
                    <input type="text" name="alt_text" class="form-control" value="{{ $custom['alt_text'] ?? '' }}" placeholder="SEO/accessibility alt text">
                </div>

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" value="{{ $custom['title'] ?? '' }}" placeholder="Optional media title">
                </div>

                <div class="form-group">
                    <label>Caption</label>
                    <textarea name="caption" class="form-control" rows="3" placeholder="Optional caption">{{ $custom['caption'] ?? '' }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i> Save Info
                </button>
            </form>

            <hr>

            @php
                $pickerType = $isImage ? 'image' : ($isVideo ? 'video' : 'all');
                $pickerAccept = $isImage
                    ? 'image/*'
                    : ($isVideo
                        ? 'video/*'
                        : '.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt');
            @endphp

            <div class="mb-2 font-weight-bold">
                <i class="fas fa-images text-primary mr-1"></i> Change Media
            </div>

            <form id="media-replace-form"
                  action="{{ route('admin.media-management.replace', $media->id) }}"
                  method="POST"
                  enctype="multipart/form-data">
                @csrf

                {{--
                    The native file control and extra Replace button are intentionally hidden.
                    MediaPicker injects the single visible “Browse Media” button.
                    Selecting a media item triggers immediate replacement through AJAX.
                --}}
                <input type="file"
                       name="file"
                       class="d-none"
                       accept="{{ $pickerAccept }}"
                       data-media-picker-type="{{ $pickerType }}"
                       data-media-picker-auto-apply="1"
                       required>

                <input type="hidden" name="name" value="{{ $media->name }}">
            </form>

            <div class="small text-muted mt-2 media-auto-apply-help">
                Media Library থেকে একটি file select করলে change সঙ্গে সঙ্গে apply হবে।
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <a href="{{ $url }}" target="_blank" class="btn btn-outline-secondary">
        <i class="fas fa-external-link-alt mr-1"></i> Open File
    </a>
    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
</div>

