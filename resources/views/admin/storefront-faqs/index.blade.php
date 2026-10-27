@extends('adminlte::page')
@section('title','Storefront FAQs')
@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div><h1>Storefront FAQs</h1><p class="text-muted">Storewide questions only. Campaign FAQs remain in their existing module.</p></div>
        <a class="btn btn-success" href="{{ route('admin.storefront-faqs.create') }}"><i class="fas fa-plus mr-1"></i> Add FAQ</a>
    </div>
@stop
@section('content')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card shadow-sm"><div class="card-body table-responsive">
        <table class="table table-hover"><thead><tr><th>Order</th><th>Question</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($faqs as $faq)
                <tr><td>{{ $faq->sort_order }}</td><td>{{ $faq->question }}</td>
                    <td><span class="badge badge-{{ $faq->is_active ? 'success' : 'secondary' }}">{{ $faq->is_active ? 'Published' : 'Draft' }}</span></td>
                    <td><div class="d-flex flex-wrap" style="gap:8px">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.storefront-faqs.edit',$faq) }}"><i class="fas fa-edit"></i> Edit</a>
                        <form method="POST" action="{{ route('admin.storefront-faqs.destroy',$faq) }}" onsubmit="return confirm('Move this Storefront FAQ to trash? Campaign FAQs are unaffected.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> Delete</button>
                        </form>
                    </div></td>
                </tr>
            @empty<tr><td colspan="4">No Storefront FAQs yet. Add your first question.</td></tr>@endforelse
            </tbody>
        </table>
        {{ $faqs->links() }}
    </div></div>
@stop
