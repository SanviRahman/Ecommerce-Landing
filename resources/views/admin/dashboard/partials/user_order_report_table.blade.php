<div class="table-responsive">
    <table class="table table-bordered table-hover table-striped mb-0" id="userOrderReportTable">
        <thead class="thead-light">
            <tr>
                <th>Date</th>
                <th>User Name</th>
                <th class="text-center">Total Order</th>
                <th class="text-center">Processing</th>
                <th class="text-center">Pending Payment</th>
                <th class="text-center">On Hold</th>
                <th class="text-center">Canceled</th>
                <th class="text-center">Completed</th>
                <th class="text-center">Invoiced</th>
                <th class="text-center">Stock Out</th>
                <th class="text-center">Delivered</th>
                <th class="text-center">Paid</th>
                <th class="text-center">Return</th>
                <th class="text-right">Paid Amount</th>
            </tr>
        </thead>

        <tbody>
            @forelse($userReportRows as $row)
                <tr>
                    <td>{{ $row['date'] ?? 'All Time' }}</td>
                    <td>{{ $row['user_name'] ?? 'N/A' }}</td>
                    <td class="text-center">{{ number_format((int) ($row['total_order'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['processing'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['pending_payment'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['on_hold'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['cancelled'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['completed'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['invoiced'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['stock_out'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['delivered'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['paid'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['return'] ?? 0)) }}</td>
                    <td class="text-right">৳{{ number_format((float) ($row['paid_amount'] ?? 0)) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="text-center text-muted py-4">
                        No user order report found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
