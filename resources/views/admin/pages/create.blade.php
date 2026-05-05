@extends('adminlte::page')

@section('title', $title ?? 'Create Page')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">{{ $title ?? 'Create Page' }}</h1>
            @if(isset($breadcrumb))
                <ol class="breadcrumb mt-2 mb-0 bg-transparent p-0">
                    @foreach($breadcrumb as $item)
                        <li class="breadcrumb-item"><a href="{{ $item['url'] }}">{{ $item['text'] }}</a></li>
                    @endforeach
                </ol>
            @endif
        </div>
        <div>
            <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary btn-sm shadow-none">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>
@endsection

@section('content')
    <form action="{{ route('admin.pages.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm border-0" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 font-weight-bold"><i class="fas fa-file-signature mr-2 text-primary"></i> Page Content</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mb-4">
                            <label>Page Name <span class="text-danger">*</span></label>
                            <input type="text" name="page_name" class="form-control @error('page_name') is-invalid @enderror" value="{{ old('page_name') }}" placeholder="e.g. About Us" required>
                            @error('page_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group mb-4">
                            <label>Slug <small class="text-muted">(Optional - Auto generated if left blank)</small></label>
                            <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug') }}" placeholder="e.g. about-us">
                            @error('slug') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group mb-0">
                            <label>Page Description</label>
                            <textarea name="description" class="form-control summernote">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 font-weight-bold"><i class="fas fa-cog mr-2 text-warning"></i> Settings & SEO</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mb-4">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>Active (Published)</option>
                                <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Inactive (Draft)</option>
                            </select>
                        </div>

                        <div class="form-group mb-4">
                            <label>Banner Image <small class="text-muted">(Optional)</small></label>
                            <input type="file" name="banner_image" class="form-control-file @error('banner_image') is-invalid @enderror" accept="image/*">
                            @error('banner_image') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                        </div>

                        <hr>
                        <label class="text-muted small text-uppercase font-weight-bold">SEO Information</label>
                        
                        <div class="form-group mb-3 mt-2">
                            <label>Meta Title</label>
                            <input type="text" name="meta_title" class="form-control" value="{{ old('meta_title') }}" placeholder="SEO Title">
                        </div>

                        <div class="form-group mb-0">
                            <label>Meta Description</label>
                            <textarea name="meta_description" class="form-control" rows="3" placeholder="SEO Description">{{ old('meta_description') }}</textarea>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-top text-right py-3" style="border-radius: 0 0 12px 12px;">
                        <button type="submit" class="btn btn-primary font-weight-bold shadow-sm px-4">
                            <i class="fas fa-save mr-1"></i> Save Page
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('plugins.Summernote', true)

@section('css')
    <style> .breadcrumb-item + .breadcrumb-item::before { content: ">"; } </style>
@endsection

@section('js')
    <script>
        $(document).ready(function() {
            $('.summernote').summernote({
                height: 300,
                placeholder: 'Write page content here...',
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });
        });
    </script>
@endsection