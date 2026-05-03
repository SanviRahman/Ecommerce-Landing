<table id="datatable" class="table table-bordered table-striped">
    <thead>
        <tr>
            <th width="8%">Photo</th>
            <th width="20%">Name</th>
            <th>Email</th>
            <th width="12%">Role</th>
            <th width="12%">Status</th>
            <th width="14%">Created</th>
            <th width="14%">Action</th>
        </tr>
    </thead>

    <tbody>
        @foreach($admins as $admin)
            <tr>
                <td>
                    <img src="{{ $admin->image_url }}"
                         alt="{{ $admin->name }}"
                         class="rounded border admin-photo">
                </td>

                <td>{{ $admin->name }}</td>

                <td>{{ $admin->email }}</td>

                <td>
                    @if($admin->role === 'admin')
                        <span class="badge badge-primary">Admin</span>
                    @elseif($admin->role === 'employee')
                        <span class="badge badge-info">Employee</span>
                    @else
                        <span class="badge badge-secondary">{{ ucfirst($admin->role ?? 'N/A') }}</span>
                    @endif
                </td>

                <td>
                    <div class="custom-control custom-switch">
                        <input type="checkbox"
                               class="custom-control-input changeAdminStatus"
                               id="statusSwitch{{ $admin->id }}"
                               data-url="{{ route('admin.users.update_status', $admin->id) }}"
                               {{ $admin->is_active ? 'checked' : '' }}
                               {{ auth()->id() === $admin->id ? 'disabled' : '' }}>

                        <label class="custom-control-label" for="statusSwitch{{ $admin->id }}">
                            @if($admin->is_active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-danger">Inactive</span>
                            @endif
                        </label>
                    </div>
                </td>

                <td>
                    {{ $admin->created_at ? $admin->created_at->format('d M, Y') : '-' }}
                </td>

                <td class="text-center">
                    <button type="button"
                            class="btn btn-info px-1 py-0 btn-sm viewAdminBtn"
                            data-url="{{ route('admin.users.show', $admin->id) }}">
                        <i class="fa fa-eye"></i>
                    </button>

                    <button type="button"
                            class="btn btn-warning px-1 py-0 btn-sm editAdminBtn"
                            data-url="{{ route('admin.users.edit', $admin->id) }}">
                        <i class="fa fa-pen"></i>
                    </button>

                    @if(auth()->id() !== $admin->id)
                        <button type="button"
                                class="btn btn-danger px-1 py-0 btn-sm deleteAdminBtn"
                                data-url="{{ route('admin.users.destroy', $admin->id) }}">
                            <i class="fa fa-trash"></i>
                        </button>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>