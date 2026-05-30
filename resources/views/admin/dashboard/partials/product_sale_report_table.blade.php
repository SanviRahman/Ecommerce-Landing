<div class="product-sale-toolbar">
    <div>
        <label class="mb-0 mr-1">Show</label>
        <select class="form-control form-control-sm d-inline-block" style="width: 80px;" disabled>
            <option selected>5</option>
        </select>
        <span class="ml-1">entries</span>
    </div>

    <div class="d-flex align-items-center">
        <label class="mb-0 mr-2">Search:</label>
        <input type="text" id="productSaleSearch" class="form-control form-control-sm" placeholder="Search product...">
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover table-striped mb-0" id="productSaleTable">
        <thead class="thead-light">
            <tr>
                <th>Product ID</th>
                <th>Product Name</th>
                <th class="text-center">Total Orders</th>
                <th class="text-center">Invoiced</th>
                <th class="text-center">Delivered</th>
                <th class="text-center">Canceled</th>
                <th class="text-center">Customer On Hold</th>
                <th class="text-center">Payment Pending</th>
                <th class="text-center">Processing</th>
            </tr>
        </thead>

        <tbody>
            @forelse($productSaleRows as $row)
                <tr class="product-sale-row">
                    <td>{{ $row['product_id'] ?: 'N/A' }}</td>
                    <td>{{ $row['product_name'] ?? 'Unknown Product' }}</td>
                    <td class="text-center">{{ number_format((int) ($row['total_orders'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['invoiced'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['delivered'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['cancelled'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['customer_on_hold'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['payment_pending'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['processing'] ?? 0)) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        No product sale report found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap mt-2">
    <div class="text-muted" id="productSaleInfo">
        Showing 0 to 0 of 0 entries
    </div>

    <div class="btn-group" id="productSalePagination" role="group" aria-label="Product sale pagination"></div>
</div>
