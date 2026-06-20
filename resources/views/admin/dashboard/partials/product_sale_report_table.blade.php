<div class="product-sale-toolbar">
    <div>
        <label class="mb-0 mr-1">Show</label>
        <select id="productSalePerPage"
                class="form-control form-control-sm d-inline-block"
                style="width: 80px;"
                aria-label="Product sale entries per page">
            <option value="10" selected>10</option>
            <option value="all">All</option>
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
                <th>Product Code</th>
                <th>Product Name</th>

                {{-- Orders Management sidebar অনুযায়ী --}}
                <th class="text-center">Total Orders</th>
                <th class="text-center">New Orders</th>
                <th class="text-center">Pending Orders</th>
                <th class="text-center">Complete Orders</th>
                <th class="text-center">Cancelled Orders</th>
                <th class="text-center">Order List 1</th>
                <th class="text-center">Order List 2</th>
                <th class="text-center">Shipped</th>
                <th class="text-center">Delivered</th>
                <th class="text-center">Stock Out</th>
                <th class="text-center">Fake Orders</th>

                {{-- Invoice Management sidebar অনুযায়ী --}}
                <th class="text-center">Pending Invoice</th>
                <th class="text-center">Complete Invoice</th>
            </tr>
        </thead>

        <tbody>
            @forelse($productSaleRows as $row)
                <tr class="product-sale-row">
                    <td>{{ filled($row['product_code'] ?? null) ? $row['product_code'] : 'N/A' }}</td>
                    <td>{{ $row['product_name'] ?? 'Unknown Product' }}</td>

                    <td class="text-center">{{ number_format((int) ($row['total_orders'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['new_orders'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['pending_orders'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['complete_orders'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['cancelled_orders'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['order_list_1'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['order_list_2'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['shipped_orders'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['delivered_orders'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['stock_out_orders'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['fake_orders'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['pending_invoice'] ?? 0)) }}</td>
                    <td class="text-center">{{ number_format((int) ($row['complete_invoice'] ?? 0)) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="15" class="text-center text-muted py-4">
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
