<div class="modal-header bg-primary text-white border-bottom-0">
    <h5 class="modal-title font-weight-bold">
        <i class="fas fa-share-alt mr-2"></i> Link Details
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<div class="modal-body p-0">
    <div class="text-center py-4 bg-light border-bottom">
        @if($socialMedia->icon_class)
            <i class="{{ $socialMedia->icon_class }} text-primary mb-2" style="font-size: 3rem;"></i>
        @else
            <i class="fas fa-link text-secondary mb-2" style="font-size: 3rem;"></i>
        @endif
        <h4 class="font-weight-bold text-dark mb-0 mt-2">{{ $socialMedia->platform_name }}</h4>
    </div>

    <table class="table table-borderless table-striped mb-0">
        <tbody>
            <tr>
                <th width="30%" class="text-muted px-4 py-3 border-bottom">Target URL</th>
                <td class="px-4 py-3 border-bottom font-weight-bold">
                    <a href="{{ $socialMedia->link }}" target="_blank" class="text-info text-break">
                        {{ $socialMedia->link }} <i class="fas fa-external-link-alt small ml-1"></i>
                    </a>
                </td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3 border-bottom">Icon Class</th>
                <td class="px-4 py-3 border-bottom text-muted">
                    <code>{{ $socialMedia->icon_class ?? 'N/A' }}</code>
                </td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3 border-bottom">Status</th>
                <td class="px-4 py-3 border-bottom">
                    @if($socialMedia->status)
                        <span class="badge badge-success px-3 py-1">Active</span>
                    @else
                        <span class="badge badge-secondary px-3 py-1">Inactive</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th class="text-muted px-4 py-3">Created At</th>
                <td class="px-4 py-3">{{ $socialMedia->created_at->format('d M, Y h:i A') }}</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="modal-footer bg-light border-top-0">
    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Close</button>
</div>