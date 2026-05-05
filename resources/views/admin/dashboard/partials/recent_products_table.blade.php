<table class="table table-hover table-striped mb-0">
    <thead class="thead-light">
        <tr>
            <th>Name</th>
            <th>Code</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Status</th>
        </tr>
    </thead>

    <tbody>
        @forelse($recentProducts as $product)
            <tr>
                <td>{{ $product->name }}</td>
                <td><code>{{ $product->product_code }}</code></td>
                <td>৳{{ number_format($product->new_price ?? 0) }}</td>
                <td>{{ number_format($product->stock ?? 0) }}</td>
                <td>
                    @if($product->status)
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-danger">Inactive</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-muted py-3">
                    No recent products found.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>