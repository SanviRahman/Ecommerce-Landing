<table class="table table-hover table-striped mb-0">
    <thead class="thead-light">
        <tr>
            <th>Title</th>
            <th>Slug</th>
            <th>Type</th>
            <th>Status</th>
            <th>Created</th>
        </tr>
    </thead>

    <tbody>
        @forelse($recentCampaigns as $campaign)
            <tr>
                <td>{{ $campaign->title }}</td>
                <td><code>{{ $campaign->slug }}</code></td>
                <td>{{ ucfirst($campaign->campaign_type ?? 'single') }}</td>
                <td>
                    @if($campaign->status)
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-danger">Inactive</span>
                    @endif
                </td>
                <td>{{ optional($campaign->created_at)->format('d M, Y') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-muted py-3">
                    No recent campaigns found.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>