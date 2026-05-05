@extends('adminlte::page')

@section('title', 'Page Details')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">Page Details</h1>
            @if(isset($breadcrumb))
                <ol class="breadcrumb mt-2 mb-0 bg-transparent p-0">
                    @foreach($breadcrumb as $item)
                        <li class="breadcrumb-item"><a href="{{ $item['url'] }}">{{ $item['text'] }}</a></li>
                    @endforeach
                </ol>
            @endif
        </div>
        <div>
            <a href="{{ route('admin.pages.edit', $page->id) }}" class="btn btn-primary btn-sm shadow-none mr-2">
                <i class="fas fa-edit mr-1"></i> Edit Page
            </a>
            <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary btn-sm shadow-none">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 font-weight-bold text-primary">{{ $page->page_name }}</h5>
                </div>
                <div class="card-body p-4 bg-light">
                    @if($page->description)
                        <div class="bg-white p-4 rounded shadow-sm border">
                            {!! $page->description !!}
                        </div>
                    @else
                        <p class="text-muted font-italic mb-0">No description provided for this page.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 font-weight-bold"><i class="fas fa-info-circle mr-2 text-info"></i> Details & SEO</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-borderless table-striped mb-0">
                        <tbody>
                            <tr>
                                <td colspan="2" class="text-center px-4 py-3 border-bottom">
                                    <img src="{{ $page->banner }}" alt="Banner" class="img-fluid rounded shadow-sm" style="max-height: 150px;">
                                </td>
                            </tr>
                            <tr>
                                <th width="40%" class="text-muted px-4 py-3">Status</th>
                                <td class="px-4 py-3">
                                    @if($page->status)
                                        <span class="badge badge-success px-2 py-1 shadow-xs">Active</span>
                                    @else
                                        <span class="badge badge-secondary px-2 py-1 shadow-xs">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Slug</th>
                                <td class="px-4 py-3 text-primary">{{ $page->slug }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Created On</th>
                                <td class="px-4 py-3">{{ $page->created_at->format('d M, Y h:i A') }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted px-4 py-3">Last Updated</th>
                                <td class="px-4 py-3">{{ $page->updated_at->format('d M, Y h:i A') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            @if($page->meta_title || $page->meta_description)
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 font-weight-bold"><i class="fas fa-search mr-2 text-warning"></i> SEO Meta Data</h6>
                    </div>
                    <div class="card-body p-4">
                        @if($page->meta_title)
                            <h6 class="font-weight-bold text-primary mb-1">{{ $page->meta_title }}</h6>
                        @endif
                        @if($page->meta_description)
                            <p class="small text-muted mb-0">{{ $page->meta_description }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('css')
    <style> 
        .breadcrumb-item + .breadcrumb-item::before { content: ">"; } 
        .table-borderless th, .table-borderless td { border-bottom: 1px solid #f1f3f5; }
    </style>
@endsection