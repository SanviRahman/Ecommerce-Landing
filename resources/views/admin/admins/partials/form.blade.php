<form action="{{ $action }}" method="POST" enctype="multipart/form-data" id="adminForm">
    @csrf

    @if(isset($isEdit) && $isEdit)
        @method('PUT')
    @endif

    @if(isset($breadcrumb))
        <ol class="breadcrumb bg-light">
            @foreach($breadcrumb as $item)
                <li class="breadcrumb-item">
                    <a href="{{ $item['url'] }}">{{ $item['text'] }}</a>
                </li>
            @endforeach
        </ol>
    @endif

    <div class="row">

        <div class="col-md-6">
            <div class="form-group">
                <label for="name">Full Name <span class="text-danger">*</span></label>
                <input id="name"
                       name="name"
                       type="text"
                       class="form-control"
                       value="{{ old('name', $admin->name ?? '') }}"
                       placeholder="Enter full name">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label for="email">Email Address <span class="text-danger">*</span></label>
                <input id="email"
                       name="email"
                       type="email"
                       class="form-control"
                       value="{{ old('email', $admin->email ?? '') }}"
                       placeholder="Enter email address">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label for="role">User Role <span class="text-danger">*</span></label>
                <select name="role" id="role" class="form-control">
                    <option value="admin" {{ old('role', $admin->role ?? 'employee') === 'admin' ? 'selected' : '' }}>
                        Admin
                    </option>
                    <option value="employee" {{ old('role', $admin->role ?? 'employee') === 'employee' ? 'selected' : '' }}>
                        Employee
                    </option>
                </select>

                <small class="text-muted">
                    Employee can view assigned orders and products only.
                </small>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label for="is_active">Status <span class="text-danger">*</span></label>
                <select name="is_active" id="is_active" class="form-control">
                    <option value="1" {{ old('is_active', isset($admin) ? (int) $admin->is_active : 1) == 1 ? 'selected' : '' }}>
                        Active
                    </option>

                    <option value="0" {{ old('is_active', isset($admin) ? (int) $admin->is_active : 1) == 0 ? 'selected' : '' }}>
                        Inactive
                    </option>
                </select>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label for="password">
                    Password
                    @if(! isset($isEdit) || ! $isEdit)
                        <span class="text-danger">*</span>
                    @else
                        <small class="text-muted">(Leave empty if unchanged)</small>
                    @endif
                </label>

                <div class="input-group">
                    <input id="password"
                           name="password"
                           type="password"
                           class="form-control password-input"
                           placeholder="Enter password">

                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary toggle-password" type="button">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>

                <div class="input-group">
                    <input id="password_confirmation"
                           name="password_confirmation"
                           type="password"
                           class="form-control password-input"
                           placeholder="Confirm password">

                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary toggle-password" type="button">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label for="photo">Profile Picture</label>
                <input id="photo"
                       name="photo"
                       type="file"
                       class="form-control-file"
                       accept="image/*">
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <img class="rounded border"
                 id="selected-image-preview"
                 style="max-height: 150px; max-width: 150px; object-fit: cover;"
                 src="{{ isset($admin) && $admin ? $admin->image_url : asset('vendor/adminlte/dist/img/user2-160x160.jpg') }}"
                 alt="{{ $admin->name ?? 'Preview' }}">
        </div>

    </div>

    <div class="text-right">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
            Close
        </button>

        <button class="btn btn-success" type="submit">
            <i class="fas fa-save mr-1"></i> Save
        </button>
    </div>
</form>

<script>
    $(document).ready(function () {

        $('.toggle-password').on('click', function () {
            const input = $(this).closest('.input-group').find('.password-input');
            const icon = $(this).find('i');

            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });

        $('#photo').on('change', function (e) {
            const file = e.target.files[0];

            if (file) {
                const reader = new FileReader();

                reader.onload = function (event) {
                    $('#selected-image-preview').attr('src', event.target.result);
                };

                reader.readAsDataURL(file);
            }
        });

    });
</script>