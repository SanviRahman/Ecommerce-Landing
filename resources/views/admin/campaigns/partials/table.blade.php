<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead class="thead-light">
            <tr>
                <th style="width: 45px;">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox"
                               class="custom-control-input"
                               id="check_all">
                        <label class="custom-control-label" for="check_all"></label>
                    </div>
                </th>

                <th style="width: 70px;">SL</th>
                <th>Landing Page Title</th>
                <th>Slug</th>
                <th>Products</th>
                <th style="width: 110px;">Status</th>
                <th style="width: 230px;">Action</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($campaigns as $campaign)
                <tr>
                    <td>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox"
                                   class="custom-control-input row-checkbox"
                                   id="campaign_check_{{ $campaign->id }}"
                                   value="{{ $campaign->id }}">
                            <label class="custom-control-label" for="campaign_check_{{ $campaign->id }}"></label>
                        </div>
                    </td>

                    <td>{{ $campaigns->firstItem() + $loop->index }}</td>

                    <td>
                        <strong>{{ $campaign->title }}</strong>
                    </td>

                    <td>
                        <span class="badge badge-light border">
                            {{ $campaign->slug }}
                        </span>
                    </td>

                    <td>
                        @if ($campaign->products && $campaign->products->count())
                            @foreach ($campaign->products->take(3) as $product)
                                <span class="badge badge-info mb-1">
                                    {{ $product->name }}
                                </span>
                            @endforeach

                            @if ($campaign->products->count() > 3)
                                <span class="badge badge-secondary">
                                    +{{ $campaign->products->count() - 3 }} more
                                </span>
                            @endif
                        @else
                            <span class="text-muted">No product</span>
                        @endif
                    </td>

                    <td>
                        @if ($campaign->status)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                    </td>

                    <td>
                        @if (! $isTrash)
                            <a href="{{ route('campaign.show', $campaign->slug) }}"
                               target="_blank"
                               class="btn btn-sm btn-primary"
                               title="View Landing Page">
                                <i class="fas fa-eye"></i>
                            </a>

                            <a href="{{ route('admin.campaigns.show', $campaign->id) }}"
                               class="btn btn-sm btn-dark"
                               title="Admin Details">
                                <i class="fas fa-desktop"></i>
                            </a>

                            <a href="{{ route('admin.campaigns.edit', $campaign->id) }}"
                               class="btn btn-sm btn-info"
                               title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>

                            <button type="button"
                                    class="btn btn-sm btn-danger btnDelete"
                                    data-url="{{ route('admin.campaigns.destroy', $campaign->id) }}"
                                    title="Move to Trash">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        @else
                            <button type="button"
                                    class="btn btn-sm btn-success btnRestore"
                                    data-url="{{ route('admin.campaigns.restore', $campaign->id) }}"
                                    title="Restore">
                                <i class="fas fa-trash-restore"></i>
                            </button>

                            <button type="button"
                                    class="btn btn-sm btn-danger btnForceDelete"
                                    data-url="{{ route('admin.campaigns.force_delete', $campaign->id) }}"
                                    title="Purge Permanently">
                                <i class="fas fa-times"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        No campaigns found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($campaigns->hasPages())
    <div class="px-4 py-3 border-top bg-white">
        {{ $campaigns->withQueryString()->links() }}
    </div>
@endif