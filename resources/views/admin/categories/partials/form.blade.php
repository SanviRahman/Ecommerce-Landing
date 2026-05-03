<form action="{{ $action }}" method="POST" id="categoryForm" enctype="multipart/form-data">
    @csrf

    @if($isEdit)
        @method('PUT')
    @endif

    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label class="font-weight-bold small text-uppercase text-muted">
                            Category Name <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               name="name"
                               id="category_name"
                               class="form-control border-light bg-light shadow-none"
                               value="{{ old('name', $category->name ?? '') }}"
                               placeholder="Enter category name"
                               required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small text-uppercase text-muted">
                            URL Slug
                        </label>

                        <input type="text"
                               name="slug"
                               id="category_slug"
                               class="form-control border-light bg-light shadow-none"
                               value="{{ old('slug', $category->slug ?? '') }}"
                               placeholder="auto-generated">
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <div class="custom-control custom-switch" style="padding-left: 3.5rem;">
                                <input type="checkbox"
                                       name="status"
                                       class="custom-control-input"
                                       id="statusSwitch"
                                       value="1"
                                       {{ (!isset($category) || $category?->status) ? 'checked' : '' }}>

                                <label class="custom-control-label font-weight-bold" for="statusSwitch">
                                    Active
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6 form-group">
                            <div class="custom-control custom-switch" style="padding-left: 3.5rem;">
                                <input type="checkbox"
                                       name="is_front_view"
                                       class="custom-control-input"
                                       id="frontViewSwitch"
                                       value="1"
                                       {{ isset($category) && $category?->is_front_view ? 'checked' : '' }}>

                                <label class="custom-control-label font-weight-bold" for="frontViewSwitch">
                                    Show Front View
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="font-weight-bold small text-uppercase text-muted d-block">
                        Category Image
                    </label>

                    <div class="image-upload-wrapper mb-3 shadow-sm border rounded-lg"
                         style="width: 220px; height: 220px; background: #fff; overflow: hidden; cursor: pointer;"
                         onclick="$('#category_image_input').click()">

                        <img id="category_image_preview"
                             src="{{ isset($category) && $category ? $category->image : asset('vendor/adminlte/dist/img/no-image.png') }}"
                             style="width: 100%; height: 100%; object-fit: cover;">
                    </div>

                    <input type="file"
                           name="image"
                           id="category_image_input"
                           class="d-none"
                           accept="image/*">

                    <button type="button"
                            class="btn btn-outline-primary btn-sm"
                            onclick="$('#category_image_input').click()">
                        <i class="fas fa-camera mr-1"></i> Change Image
                    </button>

                    <p class="small text-muted mt-2 mb-0">
                        Recommended: JPG, PNG, WEBP. Max 2MB.
                    </p>

                    @if(isset($category) && $category && $category->getFirstMedia('category_image'))
                        <button type="button"
                                class="btn btn-outline-danger btn-sm mt-2 btnDeleteCategoryMedia"
                                data-url="{{ route('admin.categories.delete_media', $category->getFirstMedia('category_image')->id) }}">
                            <i class="fas fa-trash mr-1"></i> Remove Image
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="card-footer bg-white border-top p-4 text-right">
            <button type="button"
                    class="btn btn-link text-muted font-weight-bold text-decoration-none mr-3"
                    data-dismiss="modal">
                Cancel
            </button>

            <button type="submit"
                    class="btn btn-primary px-5 font-weight-bold shadow-sm"
                    style="border-radius: 10px;">
                <i class="fas fa-save mr-1"></i>
                {{ $isEdit ? 'Update Category' : 'Create Category' }}
            </button>
        </div>
    </div>
</form>

<script>
    $(document).ready(function () {
        $('#category_image_input').on('change', function () {
            const file = this.files[0];

            if (file) {
                let reader = new FileReader();

                reader.onload = (e) => $('#category_image_preview').attr('src', e.target.result);

                reader.readAsDataURL(file);
            }
        });

        @if(! $isEdit)
            $('#category_name').on('keyup', function () {
                let slug = $(this).val().toLowerCase().trim()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/[\s_-]+/g, '-')
                    .replace(/^-+|-+$/g, '');

                $('#category_slug').val(slug);
            });
        @endif

        $('.btnDeleteCategoryMedia').on('click', function () {
            let url = $(this).data('url');

            Swal.fire({
                title: 'Delete this image?',
                text: 'This image will be removed from category.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (res) {
                            if (res.status) {
                                $('#category_image_preview').attr('src', "{{ asset('vendor/adminlte/dist/img/no-image.png') }}");
                                $('.btnDeleteCategoryMedia').remove();

                                Swal.fire({
                                    icon: 'success',
                                    title: res.message,
                                    timer: 1200,
                                    showConfirmButton: false
                                });
                            }
                        },
                        error: function () {
                            Swal.fire('Error', 'Image delete failed.', 'error');
                        }
                    });
                }
            });
        });
    });
</script>