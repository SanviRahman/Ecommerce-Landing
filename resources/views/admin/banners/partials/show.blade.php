<div class="modal-header bg-light border-bottom-0">
    <h5 class="modal-title font-weight-bold">
        <i class="fas fa-image text-info mr-2"></i> Banner Details
    </h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<div class="modal-body p-0">
    <div class="text-center bg-dark p-3">
        <img src="{{ $banner->image }}" alt="Banner" class="img-fluid rounded shadow-sm" style="max-height: 250px;">
    </div>
    
    <table class="table table-borderless table-striped mb-0">
        <tbody>
            <tr>
                <th width="30%" class="text-muted px-4 py-3 border-bottom">Title</th>
                <td class="px-4 py-3 border-bottom font-weight-bold">{{ $banner->title ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3 border-bottom">Position</th>
                <td class="px-4 py-3 border-bottom text-uppercase text-primary font-weight-bold">{{ $banner->position }}</td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3 border-bottom">Target Link</th>
                <td class="px-4 py-3 border-bottom">
                    @if($banner->link)
                        <a href="{{ $banner->link }}" target="_blank">{{ $banner->link }}</a>
                    @else
                        <span class="text-muted">No Link</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3 border-bottom">Sort Order</th>
                <td class="px-4 py-3 border-bottom">{{ $banner->sort_order }}</td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3 border-bottom">Status</th>
                <td class="px-4 py-3 border-bottom">
                    @if($banner->status)
                        <span class="badge badge-success px-3 py-1">Active</span>
                    @else
                        <span class="badge badge-secondary px-3 py-1">Inactive</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3">Created At</th>
                <td class="px-4 py-3">{{ $banner->created_at->format('d M, Y h:i A') }}</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="modal-footer bg-light border-top-0">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
</div>