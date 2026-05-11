<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light small text-uppercase font-weight-bold text-muted">
            <tr>
                @if(auth()->user()->isAdmin())
                    <th width="40" class="text-center px-4">
                        <input type="checkbox" id="check_all" class="shadow-none cursor-pointer">
                    </th>
                @endif

                <th>Product Detail</th>
                <th>Category / Brand</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Flags</th>
                <th>Status</th>
                <th width="160" class="text-right px-4">Actions</th>
            </tr>
        </thead>

        <tbody>
            @forelse($products as $product)
                <tr class="{{ !empty($isTrash) ? 'bg-light-red' : '' }}">
                    @if(auth()->user()->isAdmin())
                        <td class="text-center px-4">
                            <input type="checkbox"
                                   class="row-checkbox shadow-none cursor-pointer"
                                   value="{{ $product->id }}">
                        </td>
                    @endif

                    <td>
                        <div class="d-flex align-items-center">
                            <div class="mr-3 rounded border bg-white shadow-xs product-thumb-box">
                                <img src="{{ $product->thumbnail }}"
                                     class="product-thumb-img"
                                     alt="{{ $product->name }}"
                                     onerror="this.onerror=null;this.src='{{ asset('vendor/adminlte/dist/img/no-image.png') }}';">
                            </div>

                            <div>
                                <div class="font-weight-bold text-dark">
                                    {{ $product->name }}
                                </div>

                                <div class="small text-muted">
                                    Code: {{ $product->product_code ?: '-' }}
                                </div>

                                <div class="small text-muted">
                                    /{{ $product->slug }}
                                </div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="font-weight-bold small text-dark">
                            {{ $product->category->name ?? '-' }}
                        </div>

                        <div class="small text-muted">
                            Brand: {{ $product->brand->name ?? 'No Brand' }}
                        </div>
                    </td>

                    <td>
                        <div class="font-weight-bold text-dark">
                            New: {{ number_format($product->new_price ?? 0, 2) }}
                        </div>

                        <div class="small text-muted">
                            Purchase: {{ number_format($product->purchase_price ?? 0, 2) }}
                        </div>

                        @if($product->old_price)
                            <div class="small text-danger">
                                Old: {{ number_format($product->old_price ?? 0, 2) }}
                            </div>
                        @endif
                    </td>

                    <td>
                        @if(($product->stock ?? 0) > 0)
                            <span class="badge badge-success px-3">
                                {{ $product->stock }} In Stock
                            </span>
                        @else
                            <span class="badge badge-danger px-3">
                                Out Of Stock
                            </span>
                        @endif

                        <div class="small text-muted mt-1">
                            Sold: {{ $product->sold_quantity ?? 0 }}
                        </div>
                    </td>

                    <td>
                        @if($product->is_top_sale)
                            <span class="badge badge-warning d-block mb-1">Top Sale</span>
                        @endif

                        @if($product->is_feature)
                            <span class="badge badge-primary d-block mb-1">Featured</span>
                        @endif

                        @if($product->is_flash_sale)
                            <span class="badge badge-danger d-block mb-1">Flash Sale</span>
                        @endif

                        @if($product->is_free_delivery)
                            <span class="badge badge-success d-block mb-1">Free Delivery</span>
                        @endif

                        @if(! $product->is_top_sale && ! $product->is_feature && ! $product->is_flash_sale && ! $product->is_free_delivery)
                            <span class="text-muted small">--</span>
                        @endif
                    </td>

                    <td>
                        @if(!empty($isTrash))
                            <span class="badge badge-danger px-3">Deleted</span>
                        @else
                            <span class="badge {{ $product->status ? 'badge-success' : 'badge-warning' }} px-3 shadow-xs">
                                {{ $product->status ? 'Active' : 'Inactive' }}
                            </span>
                        @endif
                    </td>

                    <td class="text-right px-4">
                        <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                            @if(!empty($isTrash))
                                @if(auth()->user()->isAdmin())
                                    <button type="button"
                                            class="btn btn-sm btn-white text-success btnRestore"
                                            data-url="{{ route('admin.products.restore', $product->id) }}"
                                            title="Restore">
                                        <i class="fas fa-trash-restore"></i>
                                    </button>

                                    <button type="button"
                                            class="btn btn-sm btn-white text-danger btnForceDelete"
                                            data-url="{{ route('admin.products.force_delete', $product->id) }}"
                                            title="Delete Forever">
                                        <i class="fas fa-skull-crossbones"></i>
                                    </button>
                                @else
                                    <span class="btn btn-sm btn-white text-muted disabled" title="View Only">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                @endif
                            @else
                                {{-- Admin + Employee both can view product details --}}
                                <a href="{{ route('admin.products.show', $product->id) }}"
                                   class="btn btn-sm btn-white text-info"
                                   title="View">
                                    <i class="fas fa-eye"></i>
                                </a>

                                {{-- Only Admin can edit/delete --}}
                                @if(auth()->user()->isAdmin())
                                    <button type="button"
                                            class="btn btn-sm btn-white text-primary btnEdit"
                                            data-url="{{ route('admin.products.edit', $product->id) }}"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <button type="button"
                                            class="btn btn-sm btn-white text-danger btnDelete"
                                            data-url="{{ route('admin.products.destroy', $product->id) }}"
                                            title="Move to Trash">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}" class="text-center py-5">
                        <div class="py-4">
                            <i class="fas fa-box-open fa-3x text-light mb-3"></i>
                            <h6 class="text-muted">No products found matching your criteria.</h6>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($products->hasPages())
    <div class="px-4 py-3 border-top bg-white d-flex justify-content-center">
        {!! $products->appends(request()->all())->links('pagination::bootstrap-4') !!}
    </div>
@endif

<style>
.bg-light-red {
    background-color: #fffafa;
}

.shadow-xs {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.cursor-pointer {
    cursor: pointer;
}

.product-thumb-box {
    width: 55px;
    height: 55px;
    overflow: hidden;
    flex-shrink: 0;
}

.product-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.btn-white {
    background: #fff;
    border: none;
    transition: 0.2s;
}

.btn-white:hover {
    background: #f8f9fa;
    transform: translateY(-1px);
}

.btn-white.disabled,
.btn-white:disabled {
    pointer-events: none;
    opacity: 0.65;
}

.pagination {
    margin-bottom: 0;
}

.page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}

.page-link {
    color: #6c757d;
    border-radius: 5px !important;
    margin: 0 2px;
}
</style>