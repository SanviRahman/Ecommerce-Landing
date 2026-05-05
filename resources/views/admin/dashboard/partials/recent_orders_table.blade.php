<table class="table table-hover table-striped mb-0">
    <thead class="thead-light">
        <tr>
            <th>Invoice</th>
            <th>Customer</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Total</th>
            <th>Date</th>
        </tr>
    </thead>

    <tbody>
        @forelse($recentOrders as $order)
            <tr>
                <td>
                    <a href="{{ route('admin.orders.invoice', $order->id) }}">
                        <code>{{ $order->invoice_id }}</code>
                    </a>
                </td>

                <td>{{ $order->customer_name }}</td>
                <td>{{ $order->phone }}</td>

                <td>
                    @if($order->order_status === 'pending')
                        <span class="badge badge-warning">Pending</span>
                    @elseif($order->order_status === 'confirmed')
                        <span class="badge badge-primary">Confirmed</span>
                    @elseif($order->order_status === 'processing')
                        <span class="badge badge-secondary">Processing</span>
                    @elseif($order->order_status === 'shipped')
                        <span class="badge badge-info">Shipped</span>
                    @elseif($order->order_status === 'delivered')
                        <span class="badge badge-success">Delivered</span>
                    @elseif($order->order_status === 'cancelled')
                        <span class="badge badge-danger">Cancelled</span>
                    @else
                        <span class="badge badge-light">{{ ucfirst($order->order_status) }}</span>
                    @endif
                </td>

                <td>৳{{ number_format($order->total_amount ?? 0) }}</td>

                <td>{{ optional($order->created_at)->format('d M, Y') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-muted py-3">
                    No recent orders found.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>