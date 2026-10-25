@extends('adminlte::page')

@section('title', 'Product Option Values')

@section('content_header')
<div class="d-flex align-items-center justify-content-between flex-wrap">
    <div>
        <h1 class="mb-1">Product Options</h1>
        <p class="text-muted mb-0">Color, Size, Weight and Piece Count &mdash; controlled option definitions.</p>
    </div>
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Back to Products</a>
</div>
@endsection

@section('content')
<div class="alert alert-info" role="note">
    <strong>Isolated development schema only.</strong> Existing orders and published campaigns are not modified here.
    Labels can be updated later without changing a variant's recorded value IDs.
</div>
@if(session('success'))
<div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger" role="alert">
    <strong>Please correct the form:</strong>
    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

@if($options->whereIn('code', array_keys($supported))->count() < count($supported))
<div class="card shadow-sm">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap">
        <p class="mb-2">Initialize the four supported option types before adding values.</p>
        <form method="POST" action="{{ route('admin.products.options.bootstrap') }}">
            @csrf
            <button type="submit" class="btn btn-success">Initialize Supported Options</button>
        </form>
    </div>
</div>
@endif

<div class="row">
@forelse($options->whereIn('code', array_keys($supported)) as $option)
    <div class="col-xl-6 mb-4">
        <section class="card shadow-sm h-100" aria-labelledby="option-title-{{ $option->id }}">
            <div class="card-header bg-white">
                <h2 id="option-title-{{ $option->id }}" class="h5 mb-0">{{ $option->name }}</h2>
                <small class="text-muted">{{ $option->code }}</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Value</th><th>Code</th><th>Color</th><th>State</th></tr></thead>
                        <tbody>
                        @forelse($option->values as $value)
                        <tr>
                            <td>{{ $value->label }}</td>
                            <td><code>{{ $value->code }}</code></td>
                            <td>{{ $value->swatch_hex ?? '—' }}</td>
                            <td>{{ $value->is_active ? 'Active' : 'Inactive' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-muted">No values yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('admin.products.options.values.store', $option->id) }}" class="border-top pt-3">
                    @csrf
                    <h3 class="h6">Add {{ $option->name }} Value</h3>
                    <div class="form-row">
                        <div class="form-group col-sm-6">
                            <label for="label-{{ $option->id }}">Display label</label>
                            <input id="label-{{ $option->id }}" class="form-control" type="text" name="label" maxlength="100" required placeholder="e.g. 2 kg / Black / XL">
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="code-{{ $option->id }}">Unique code</label>
                            <input id="code-{{ $option->id }}" class="form-control" type="text" name="code" maxlength="80" required pattern="[a-z0-9][a-z0-9_-]*" placeholder="e.g. 2kg / black / xl">
                        </div>
                        @if($option->code === 'color')
                        <div class="form-group col-sm-6">
                            <label for="swatch-{{ $option->id }}">Swatch hex (optional)</label>
                            <input id="swatch-{{ $option->id }}" class="form-control" type="text" name="swatch_hex" maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="#663399">
                        </div>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-success">Add Value</button>
                </form>
            </div>
        </section>
    </div>
@empty
<div class="col-12"><div class="alert alert-warning">No option definitions are available.</div></div>
@endforelse
</div>
@endsection
