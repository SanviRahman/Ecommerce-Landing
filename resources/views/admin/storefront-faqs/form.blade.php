@extends('adminlte::page')
@section('title', $faq->exists ? 'Edit Storefront FAQ' : 'Add Storefront FAQ')
@section('content_header')
<h1>{{ $faq->exists ? 'Edit Storefront FAQ' : 'Add Storefront FAQ' }}</h1>
@stop
@section('content')
<div class="row"><div class="col-lg-9"><div class="card shadow-sm"><div class="card-body">
    <p class="text-muted">This answer appears only on the Storefront Help page. Existing campaign FAQs are separate.</p>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $faq->exists ? route('admin.storefront-faqs.update',$faq) : route('admin.storefront-faqs.store') }}">
        @csrf @if($faq->exists) @method('PUT') @endif
        <div class="form-group"><label for="sf_faq_question">Question *</label>
            <input class="form-control" id="sf_faq_question" name="question" maxlength="300" required value="{{ old('question',$faq->question ?? '') }}"></div>
        <div class="form-group"><label for="sf_faq_answer">Answer *</label>
            <textarea class="form-control" id="sf_faq_answer" name="answer" rows="7" maxlength="6000" required>{{ old('answer',$faq->answer ?? '') }}</textarea></div>
        <div class="form-row"><div class="form-group col-md-5"><label for="sf_faq_order">Display Order</label>
            <input class="form-control" id="sf_faq_order" type="number" min="0" max="100000" name="sort_order" required value="{{ old('sort_order',$faq->sort_order ?? 0) }}"></div>
            <div class="form-group col-md-7"><label for="sf_faq_status">Status</label>
                <select class="form-control" id="sf_faq_status" name="is_active">
                    <option value="0" @selected((string) old('is_active', (int) ($faq->is_active ?? false)) === '0')>Draft / Hidden</option>
                    <option value="1" @selected((string) old('is_active', (int) ($faq->is_active ?? false)) === '1')>Published / Visible</option>
                </select>
            </div></div>
        <button class="btn btn-success" type="submit">{{ $faq->exists ? 'Save Changes' : 'Create FAQ' }}</button>
        <a href="{{ route('admin.storefront-faqs.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div></div></div>
@stop
