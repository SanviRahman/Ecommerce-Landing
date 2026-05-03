@if(isset($breadcrumb))
    <ol class="breadcrumb bg-light">
        @foreach($breadcrumb as $item)
            <li class="breadcrumb-item">
                <a href="{{ $item['url'] }}">{{ $item['text'] }}</a>
            </li>
        @endforeach
    </ol>
@endif

<div class="table-responsive">
    <table class="table table-bordered">
        <tbody>
            <tr>
                <th width="30%">Photo</th>
                <td>
                    <img class="rounded border"
                         width="120"
                         height="120"
                         style="object-fit: cover;"
                         src="{{ $admin->image_url }}"
                         alt="{{ $admin->name }}">
                </td>
            </tr>

            <tr>
                <th>Full Name</th>
                <td>{{ $admin->name }}</td>
            </tr>

            <tr>
                <th>Email Address</th>
                <td>{{ $admin->email }}</td>
            </tr>

            <tr>
                <th>Role</th>
                <td>
                    @if($admin->role === 'admin')
                        <span class="badge badge-primary">Admin</span>
                    @elseif($admin->role === 'employee')
                        <span class="badge badge-info">Employee</span>
                    @else
                        <span class="badge badge-secondary">{{ ucfirst($admin->role ?? 'N/A') }}</span>
                    @endif
                </td>
            </tr>

            <tr>
                <th>Status</th>
                <td>
                    @if($admin->is_active)
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-danger">Inactive</span>
                    @endif
                </td>
            </tr>

            <tr>
                <th>Email Verified At</th>
                <td>
                    {{ $admin->email_verified_at ? $admin->email_verified_at->format('d M, Y h:i A') : 'Not Verified' }}
                </td>
            </tr>

            <tr>
                <th>Assigned Active Orders</th>
                <td>
                    {{ method_exists($admin, 'activeAssignedOrders') ? $admin->activeAssignedOrders()->count() : 0 }}
                </td>
            </tr>

            <tr>
                <th>Created At</th>
                <td>
                    {{ $admin->created_at ? $admin->created_at->format('d M, Y h:i A') : '-' }}
                </td>
            </tr>

            <tr>
                <th>Updated At</th>
                <td>
                    {{ $admin->updated_at ? $admin->updated_at->format('d M, Y h:i A') : '-' }}
                </td>
            </tr>
        </tbody>
    </table>

    <div class="text-right">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
            Close
        </button>
    </div>
</div>