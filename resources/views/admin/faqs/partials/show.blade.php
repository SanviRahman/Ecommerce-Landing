<div class="modal-header bg-primary text-white border-bottom-0">
    <h5 class="modal-title font-weight-bold">
        <i class="fas fa-info-circle mr-2"></i> FAQ Details
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<div class="modal-body p-0">
    <div class="p-4 bg-light border-bottom">
        <h5 class="font-weight-bold text-dark mb-3">
            <span class="text-primary mr-2">Q.</span> {{ $faq->question }}
        </h5>
        <div class="bg-white p-3 rounded shadow-sm border" style="font-size: 1.05rem; line-height: 1.6;">
            <span class="text-success font-weight-bold mr-2">A.</span> 
            {!! nl2br(e($faq->answer)) !!}
        </div>
    </div>

    <table class="table table-borderless table-striped mb-0">
        <tbody>
            <tr>
                <th width="35%" class="text-muted px-4 py-3 border-bottom">Linked Campaign</th>
                <td class="px-4 py-3 border-bottom font-weight-bold text-info">
                    {{ $faq->campaign->title ?? 'General FAQ' }}
                </td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3 border-bottom">Sort Order</th>
                <td class="px-4 py-3 border-bottom">
                    <span class="badge badge-dark">{{ $faq->sort_order }}</span>
                </td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3 border-bottom">Status</th>
                <td class="px-4 py-3 border-bottom">
                    @if($faq->status)
                        <span class="badge badge-success px-3 py-1">Active</span>
                    @else
                        <span class="badge badge-secondary px-3 py-1">Inactive</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3">Created At</th>
                <td class="px-4 py-3">{{ $faq->created_at->format('d M, Y h:i A') }}</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="modal-footer bg-light border-top-0">
    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Close</button>
</div>