@extends('adminlte::page')

@section('title', 'Color, Size & Product Options')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-1">Product Options <span class="badge badge-success align-middle" style="font-size:12px">Manage Values</span></h1>
        <p class="text-muted mb-0">Create, edit and organize Color, Size, Weight and Piece Count for product variations.</p>
    </div>
    <div class="d-flex flex-wrap mt-2 mt-md-0">
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary mr-2"><i class="fas fa-arrow-left mr-1"></i> Products</a>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary"><i class="fas fa-plus-circle mr-1"></i> Create Product</a>
    </div>
</div>
@endsection

@section('content')
<style>
.sf-options-page .sf-options-info{background:linear-gradient(120deg,#f2fbf6,#ffffff);border:1px solid #cde8d9;border-radius:14px;padding:15px 18px;color:#23543e;margin-bottom:18px}
.sf-options-page .sf-options-card{overflow:hidden;border:1px solid #e5eee8;border-radius:16px;box-shadow:0 10px 30px rgba(24,64,44,.06)}
.sf-options-page .sf-options-card .card-header{padding:16px 20px;background:linear-gradient(125deg,#145a3a,#258b5c);color:white;border:0}
.sf-options-page .sf-options-card .card-header small{color:#d3efdf}
.sf-options-page .sf-option-table th{font-size:11px;letter-spacing:.05em;color:#627568;text-transform:uppercase;border-top:0;white-space:nowrap}
.sf-options-page .sf-option-table td{vertical-align:middle}
.sf-options-page .sf-option-table .form-control{min-width:85px;height:35px;font-size:13px;border-radius:8px}
.sf-options-page .sf-option-table input[name="sort_order"]{min-width:62px;width:65px}
.sf-options-page .sf-option-table input[name="code"]{min-width:95px}
.sf-options-page .sf-option-table input[name="swatch_hex"]{min-width:90px;width:102px}
.sf-options-page .sf-swatch{display:inline-block;width:18px;height:18px;border-radius:50%;border:1px solid #cbd5d0;vertical-align:middle;margin-right:6px}
.sf-options-page .sf-options-add{background:#f8fcf9;border:1px solid #e1efe7;border-radius:13px;padding:17px}
.sf-options-page .sf-options-add .form-control{border-radius:9px}
.sf-options-page .sf-status-select{min-width:95px}
.sf-options-page button:focus-visible,.sf-options-page a:focus-visible{outline:3px solid #7cd5a4;outline-offset:2px}
</style>
<div class="sf-options-page">
    <div class="sf-options-info" role="note">
        <i class="fas fa-shield-alt mr-1"></i>
        <strong>Safe catalog management:</strong> Existing variant values cannot be permanently deleted or have their codes changed. Deactivate active variants before disabling a referenced option. Stock and historical orders are untouched.
        <a class="ml-1" href="{{ route('admin.products.index') }}">Manage product variants from each product's Variants action.</a>
    </div>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Please check:</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($options->whereIn('code', array_keys($supported))->count() < count($supported))
        <div class="card border-success shadow-sm"><div class="card-body d-flex justify-content-between align-items-center flex-wrap">
            <div><strong>Initialize supported options</strong><p class="text-muted mb-0 small">This creates missing Color, Size, Weight and Piece Count definitions. No product is modified.</p></div>
            <form method="POST" action="{{ route('admin.products.options.bootstrap') }}">@csrf
                <button class="btn btn-success mt-2 mt-md-0" type="submit">Initialize Options</button>
            </form>
        </div></div>
    @endif

    <div class="row">
        @forelse($options->whereIn('code', array_keys($supported)) as $option)
            <div class="col-12 mb-4">
                <section class="card sf-options-card" aria-labelledby="sf-option-title-{{ $option->id }}">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
                        <div><h2 class="h5 mb-0" id="sf-option-title-{{ $option->id }}">
                            <i class="fas {{ $option->code === 'color' ? 'fa-palette' : ($option->code === 'size' ? 'fa-ruler-combined' : 'fa-tags') }} mr-2"></i>{{ $option->name }} Values</h2>
                            <small>{{ $option->values->count() }} total values · Used in Product Create and Variant Manager</small>
                        </div>
                        <span class="badge badge-light px-3 py-2">{{ ucfirst($option->code) }}</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table sf-option-table table-hover">
                                <thead><tr><th scope="col">Display label</th><th scope="col">Unique code</th>
                                    @if($option->code === 'color')<th scope="col">Swatch HEX</th>@endif
                                    <th scope="col">Order</th><th scope="col">State</th><th scope="col">Actions</th>
                                </tr></thead>
                                <tbody>
                                    @forelse($option->values as $value)
                                    @php $editFormId = 'edit-option-value-'.$value->id; @endphp
                                    <tr>
                                        <td>
                                            <form id="{{ $editFormId }}" method="POST" action="{{ route('admin.products.options.values.update', [$option->id, $value->id]) }}">
                                                @csrf @method('PATCH')
                                                <label class="sr-only" for="option-label-{{ $value->id }}">{{ $option->name }} label</label>
                                                <input id="option-label-{{ $value->id }}" class="form-control" name="label" value="{{ $value->label }}" maxlength="100" required>
                                            </form>
                                        </td>
                                        <td>
                                            <label class="sr-only" for="option-code-{{ $value->id }}">Value code</label>
                                            <input id="option-code-{{ $value->id }}" form="{{ $editFormId }}" class="form-control" name="code" value="{{ $value->code }}" maxlength="80" pattern="[a-z0-9][a-z0-9_-]*" required>
                                        </td>
                                        @if($option->code === 'color')
                                        <td class="text-nowrap">
                                            @if(preg_match('/\A#[0-9a-fA-F]{6}\z/D', (string) $value->swatch_hex))<span class="sf-swatch" style="background-color:{{ $value->swatch_hex }}" aria-hidden="true"></span>@endif
                                            <label class="sr-only" for="option-swatch-{{ $value->id }}">HEX code</label>
                                            <input id="option-swatch-{{ $value->id }}" form="{{ $editFormId }}" class="form-control d-inline-block" name="swatch_hex" value="{{ $value->swatch_hex }}" maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="#000000">
                                        </td>
                                        @endif
                                        <td><label class="sr-only" for="option-order-{{ $value->id }}">Sort order</label>
                                            <input id="option-order-{{ $value->id }}" form="{{ $editFormId }}" class="form-control" type="number" name="sort_order" min="0" max="1000000" required value="{{ $value->sort_order }}">
                                        </td>
                                        <td><label class="sr-only" for="option-active-{{ $value->id }}">State</label>
                                            <select id="option-active-{{ $value->id }}" form="{{ $editFormId }}" class="form-control sf-status-select" name="is_active">
                                                <option value="1" @selected($value->is_active)>Active</option>
                                                <option value="0" @selected(!$value->is_active)>Inactive</option>
                                            </select>
                                        </td>
                                        <td class="text-nowrap">
                                            <button form="{{ $editFormId }}" type="submit" class="btn btn-outline-success btn-sm" title="Save changes" aria-label="Save {{ $value->label }}"><i class="fas fa-save mr-1"></i> Save</button>
                                            <form method="POST" class="d-inline-block" action="{{ route('admin.products.options.values.destroy', [$option->id, $value->id]) }}" onsubmit="return confirm('Delete this unused option value? Values used by variants cannot be deleted.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm ml-1" title="Delete unused value" aria-label="Delete {{ $value->label }}"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="{{ $option->code === 'color' ? 6 : 5 }}" class="text-center text-muted py-4">No {{ strtolower($option->name) }} values yet. Create one below.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <form method="POST" action="{{ route('admin.products.options.values.store', $option->id) }}" class="sf-options-add">
                            @csrf
                            <h3 class="h6 font-weight-bold mb-3"><i class="fas fa-plus-circle text-success mr-1"></i> Add {{ $option->name }} Value</h3>
                            <div class="form-row align-items-end">
                                <div class="form-group col-md-4"><label for="new-label-{{ $option->id }}">Display label</label>
                                    <input id="new-label-{{ $option->id }}" class="form-control" name="label" type="text" maxlength="100" placeholder="e.g. Black / XL / 500g" required>
                                </div>
                                <div class="form-group col-md-3"><label for="new-code-{{ $option->id }}">Unique code</label>
                                    <input id="new-code-{{ $option->id }}" class="form-control" name="code" maxlength="80" pattern="[a-z0-9][a-z0-9_-]*" placeholder="black / xl / 500g" required>
                                </div>
                                @if($option->code === 'color')
                                <div class="form-group col-md-3"><label for="new-swatch-{{ $option->id }}">HEX color (optional)</label>
                                    <input id="new-swatch-{{ $option->id }}" class="form-control" name="swatch_hex" maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="#101010">
                                </div>
                                @endif
                                <div class="form-group col-md-2"><button class="btn btn-success btn-block" type="submit"><i class="fas fa-plus mr-1"></i> Add</button></div>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        @empty
            <div class="col-12"><p class="alert alert-warning">Initialize the supported option definitions above to begin.</p></div>
        @endforelse
    </div>
</div>
@endsection
