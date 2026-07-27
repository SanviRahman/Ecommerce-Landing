<div class="modal-header bg-danger text-white border-bottom-0">
    <h5 class="modal-title font-weight-bold">
        <i class="fas fa-user-shield mr-2"></i>Customer Block Details
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<div class="modal-body p-0">
    <div class="p-4 bg-light border-bottom">
        <h5 class="font-weight-bold mb-1">
            {{ $blockedCustomer->customer_name ?: 'Unknown Customer' }}
        </h5>
        <div class="text-muted">Block Rule #{{ $blockedCustomer->id }}</div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-borderless mb-0">
            <tbody>
                <tr>
                    <th class="pl-4" width="34%">Phone</th>
                    <td>
                        {{ $blockedCustomer->phone ?: 'Not available' }}
                        @if($blockedCustomer->block_phone)
                            <span class="badge badge-danger ml-2">Blocked</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th class="pl-4">IP Address</th>
                    <td>
                        {{ $blockedCustomer->ip_address ?: 'Not captured' }}
                        @if($blockedCustomer->block_ip)
                            <span class="badge badge-danger ml-2">Blocked</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th class="pl-4">Status</th>
                    <td>
                        @if($blockedCustomer->status)
                            <span class="badge badge-danger">Blocked</span>
                        @else
                            <span class="badge badge-success">Unblocked</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th class="pl-4">Reason</th>
                    <td style="white-space: pre-line;">{{ $blockedCustomer->reason ?: 'No reason provided.' }}</td>
                </tr>
                <tr>
                    <th class="pl-4">Source Order</th>
                    <td>{{ $blockedCustomer->sourceOrder->invoice_id ?? 'Manual rule' }}</td>
                </tr>
                <tr>
                    <th class="pl-4">Blocked By</th>
                    <td>
                        {{ $blockedCustomer->blockedBy->name ?? 'System' }}
                        <div class="small text-muted">
                            {{ optional($blockedCustomer->blocked_at)->format('d M Y h:i A') ?: '—' }}
                        </div>
                    </td>
                </tr>
                @if($blockedCustomer->unblocked_at)
                    <tr>
                        <th class="pl-4">Unblocked By</th>
                        <td>
                            {{ $blockedCustomer->unblockedBy->name ?? 'System' }}
                            <div class="small text-muted">
                                {{ $blockedCustomer->unblocked_at->format('d M Y h:i A') }}
                            </div>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<div class="modal-footer bg-light border-top-0">
    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Close</button>
</div>
