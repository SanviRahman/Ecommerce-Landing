<div class="table-responsive">
    <table class="table table-hover mb-0 blocked-customer-table">
        <thead class="thead-light">
            <tr>
                <th class="pl-4">Customer</th>
                <th>Phone</th>
                <th>IP Address</th>
                <th>Blocked By</th>
                <th>Reason</th>
                <th>Source Order</th>
                <th>Status</th>
                <th class="text-right pr-4">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($blockedCustomers as $blockedCustomer)
                <tr>
                    <td class="pl-4">
                        <div class="font-weight-bold text-dark">
                            {{ $blockedCustomer->customer_name ?: 'Unknown Customer' }}
                        </div>
                        <div class="small text-muted">
                            Rule #{{ $blockedCustomer->id }}
                        </div>
                    </td>
                    <td>
                        @if($blockedCustomer->phone)
                            <span class="blocked-code">{{ $blockedCustomer->phone }}</span>
                            @if($blockedCustomer->block_phone)
                                <span class="badge badge-danger d-block mt-1" style="width: fit-content;">Phone Block</span>
                            @else
                                <span class="badge badge-light border d-block mt-1" style="width: fit-content;">Not Checked</span>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($blockedCustomer->ip_address)
                            <span class="blocked-code">{{ $blockedCustomer->ip_address }}</span>
                            @if($blockedCustomer->block_ip)
                                <span class="badge badge-danger d-block mt-1" style="width: fit-content;">IP Block</span>
                            @else
                                <span class="badge badge-light border d-block mt-1" style="width: fit-content;">Not Checked</span>
                            @endif
                        @else
                            <span class="text-muted">Not captured</span>
                        @endif
                    </td>
                    <td>
                        <div class="font-weight-bold">{{ $blockedCustomer->blockedBy->name ?? 'System' }}</div>
                        <div class="small text-muted">
                            {{ optional($blockedCustomer->blocked_at)->format('d M Y h:i A') ?: '—' }}
                        </div>
                    </td>
                    <td>
                        <div class="block-reason small text-muted">
                            {{ $blockedCustomer->reason ?: 'No reason provided.' }}
                        </div>
                    </td>
                    <td>
                        @if($blockedCustomer->sourceOrder)
                            @if(auth()->user()?->isAdmin() || (int) $blockedCustomer->sourceOrder->assigned_employee_id === (int) auth()->id())
                                <a href="{{ route('admin.orders.edit', $blockedCustomer->sourceOrder->id) }}"
                                   class="badge badge-info p-2">
                                    {{ $blockedCustomer->sourceOrder->invoice_id }}
                                </a>
                            @else
                                <span class="badge badge-light border p-2">
                                    {{ $blockedCustomer->sourceOrder->invoice_id }}
                                </span>
                            @endif
                        @else
                            <span class="text-muted">Manual rule</span>
                        @endif
                    </td>
                    <td>
                        @if($isTrash ?? false)
                            <span class="badge badge-dark px-2 py-1">Deleted</span>
                        @elseif($blockedCustomer->status)
                            <span class="badge badge-danger px-2 py-1">Blocked</span>
                        @else
                            <span class="badge badge-success px-2 py-1">Unblocked</span>
                            @if($blockedCustomer->unblockedBy)
                                <div class="small text-muted mt-1">
                                    by {{ $blockedCustomer->unblockedBy->name }}
                                </div>
                            @endif
                        @endif
                    </td>
                    <td class="text-right pr-4">
                        <div class="btn-group shadow-sm border rounded bg-white overflow-hidden">
                            @if($isTrash ?? false)
                                <button type="button"
                                        class="btn btn-sm btn-white text-success btn-block-action"
                                        data-action="restore"
                                        data-url="{{ route('admin.blocked-customers.restore', $blockedCustomer->id) }}"
                                        data-confirm="Restore this block rule as inactive?"
                                        title="Restore">
                                    <i class="fas fa-trash-restore"></i>
                                </button>
                                <button type="button"
                                        class="btn btn-sm btn-white text-danger btn-block-action"
                                        data-action="force-delete"
                                        data-url="{{ route('admin.blocked-customers.force-delete', $blockedCustomer->id) }}"
                                        data-confirm="Permanently delete this block rule?"
                                        title="Delete Permanently">
                                    <i class="fas fa-skull-crossbones"></i>
                                </button>
                            @else
                                <button type="button"
                                        class="btn btn-sm btn-white text-info btn-block-modal"
                                        data-url="{{ route('admin.blocked-customers.show', $blockedCustomer->id) }}"
                                        title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button"
                                        class="btn btn-sm btn-white text-primary btn-block-modal"
                                        data-url="{{ route('admin.blocked-customers.edit', $blockedCustomer->id) }}"
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @if($blockedCustomer->status)
                                    <button type="button"
                                            class="btn btn-sm btn-white text-success btn-block-action"
                                            data-action="unblock"
                                            data-url="{{ route('admin.blocked-customers.toggle-status', $blockedCustomer->id) }}"
                                            data-confirm="Unblock this customer?"
                                            title="Unblock">
                                        <i class="fas fa-unlock"></i>
                                    </button>
                                @else
                                    <button type="button"
                                            class="btn btn-sm btn-white text-danger btn-block-action"
                                            data-action="activate"
                                            data-url="{{ route('admin.blocked-customers.toggle-status', $blockedCustomer->id) }}"
                                            data-confirm="Activate this block rule?"
                                            title="Block Again">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                @endif
                                <button type="button"
                                        class="btn btn-sm btn-white text-danger btn-block-action"
                                        data-action="delete"
                                        data-url="{{ route('admin.blocked-customers.destroy', $blockedCustomer->id) }}"
                                        data-confirm="Move this block rule to trash?"
                                        title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <i class="fas fa-user-shield fa-3x text-light mb-3"></i>
                        <h6 class="text-muted mb-0">No customer block rules found.</h6>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($blockedCustomers->hasPages())
    <div class="px-4 py-3 border-top bg-white d-flex justify-content-center">
        {!! $blockedCustomers->links('pagination::bootstrap-4') !!}
    </div>
@endif
