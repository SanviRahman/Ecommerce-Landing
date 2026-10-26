@extends('adminlte::page')

@section('title', 'Homepage Builder')
@section('content_header')
<div class="d-flex justify-content-between align-items-start flex-wrap">
    <div>
        <h1 class="h3 mb-1">Storefront Homepage Builder</h1>
        <p class="text-muted mb-0">Manage the public Storefront Homepage. Campaigns remain accessible at /campaign/{slug}.</p>
    </div>
    @if($state['draft'] || $state['published'])
        <a href="{{ route('admin.storefront.home.preview') }}" class="btn btn-outline-success mt-2" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-eye mr-1"></i> Preview homepage
        </a>
    @endif
</div>
@endsection

@section('content')
@php
    $page = $state['page'];
    $draft = $state['draft'];
    $published = $state['published'];
    $version = (int) ($page['lock_version'] ?? 0);
    $colors = $draft['settings']['theme'] ?? $themeDefaults;
@endphp
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())
<div class="alert alert-danger" role="alert"><strong>Changes were not saved:</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="alert alert-info" role="note">
    @if(\App\Support\LocalHomepageAccess::configuredForBuilder())
        <strong>Local development Homepage Builder.</strong> Drafts, sections and published revisions are saved to your approved local database.
    @else
        <strong>Isolated QA Homepage Builder.</strong> This editor uses the restricted test database.
    @endif
    Publishing a revision updates the public Storefront Homepage at <strong><code>/</code></strong>. Campaigns remain separate at <code>/campaign/{slug}</code>.
    Use the <strong>Preview homepage</strong> button to review the frontend before a separate public cutover.
    <span class="d-block mt-1">If another browser tab saves first, this page returns HTTP 409. Reload before editing.</span>
</div>
<div class="row">
    <div class="col-md-4"><div class="small-box bg-light border"><div class="inner"><small class="text-muted">Draft</small><h4>{{ $draft ? 'Revision #'.$draft['revision_number'] : 'None' }}</h4></div></div></div>
    <div class="col-md-4"><div class="small-box bg-light border"><div class="inner"><small class="text-muted">Published (public)</small><h4>{{ $published ? 'Revision #'.$published['revision_number'] : 'Not published' }}</h4></div></div></div>
    <div class="col-md-4"><div class="small-box bg-light border"><div class="inner"><small class="text-muted">Optimistic version</small><h4 id="p06-page-version">{{ $version }}</h4></div></div></div>
</div>
@if(!$draft)
<section class="card card-outline card-success"><div class="card-body">
    <h2 class="h5">{{ $published ? 'Create a new draft from the publication' : 'Start your first storefront homepage' }}</h2>
    <p class="text-muted">No public content will change until the separate storefront release is approved.</p>
    <form method="POST" action="{{ route('admin.storefront.home.draft') }}">@csrf
        <input type="hidden" name="expected_version" value="{{ $version }}">
        <button class="btn btn-success"><i class="fas fa-pen mr-1"></i> Create Draft</button>
    </form>
</div></section>
@else
<section class="card card-outline card-success" aria-label="Draft controls">
    <div class="card-header"><h2 class="card-title">Add a typed section</h2></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.storefront.home.sections.store') }}" class="form-inline">
            @csrf<input type="hidden" name="expected_version" value="{{ $version }}">
            <label for="section-type" class="mr-2">Section type</label>
            <select class="custom-select mr-2 mb-2" id="section-type" name="type" required>
                @foreach($types as $slug=>$name)<option value="{{ $slug }}">{{ $name }}</option>@endforeach
            </select>
            <button class="btn btn-success mb-2">Add draft section</button>
        </form>
        <p class="text-muted small mb-0">Sections start disabled. Configure content and colors, then enable them. Move controls work without JavaScript.</p>
    </div>
</section>

<section class="card" aria-label="Global theme">
    <div class="card-header"><h2 class="card-title">Global Theme — Green-inspired Default</h2></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.storefront.home.theme') }}">@csrf @method('PATCH')
            <input type="hidden" name="expected_version" value="{{ $version }}">
            <div class="row">
                @foreach(\App\Support\HomeSectionSchema::STYLE_KEYS as $key)
                    @php $value = old('theme.'.$key, $colors[$key] ?? $themeDefaults[$key]); @endphp
                    <div class="form-group col-sm-6 col-lg-3">
                        <label for="theme-{{ $key }}">{{ \Illuminate\Support\Str::headline($key) }}</label>
                        <div class="d-flex p06-color-controls">
                            <input type="color" class="p06-color-picker" aria-label="Pick {{ $key }} color"
                                data-p06-color-picker data-p06-hex-input="theme-{{ $key }}"
                                value="{{ $value ?: $themeDefaults[$key] }}">
                            <input type="text" class="form-control" id="theme-{{ $key }}" name="theme[{{ $key }}]"
                                data-p06-hex-value value="{{ $value }}" placeholder="#RRGGBB or empty"
                                maxlength="7" pattern="#[0-9A-Fa-f]{6}">
                        </div>
                    </div>
                @endforeach
            </div>
            <button class="btn btn-outline-success">Save Theme</button>
        </form>
    </div>
</section>

<section class="card card-outline card-success" aria-labelledby="p06-order-title">
    <div class="card-header"><h2 id="p06-order-title" class="card-title">Section Organizer · Drag &amp; Drop</h2></div>
    <div class="card-body">
        <p id="p06-sort-help" class="text-muted small mb-2">
            Drag the handle with mouse or touch. Keyboard: focus a handle and press ↑ or ↓.
            The order saves automatically. The Up/Down buttons on each section work without JavaScript.
        </p>
        <ol id="p06-sort-list" class="p06-sort-list" aria-label="Draft section order"
            data-reorder-url="{{ route('admin.storefront.home.sections.reorder') }}"
            data-csrf-token="{{ csrf_token() }}" data-version="{{ $version }}">
            @foreach($draft['sections'] as $position=>$entry)
                <li class="p06-sort-item" data-section-key="{{ $entry['section_key'] }}">
                    <button class="p06-drag-handle" type="button" aria-describedby="p06-sort-help"
                        aria-label="Reorder {{ $types[$entry['type']] ?? 'section' }} {{ $position+1 }} using arrow keys or dragging"
                        aria-keyshortcuts="ArrowUp ArrowDown Home End" title="Drag or press arrow keys">☷</button>
                    <span class="p06-sort-number" aria-hidden="true">{{ $position+1 }}</span>
                    <span class="p06-sort-label">{{ $entry['title'] ?: ($types[$entry['type']] ?? 'Section') }}</span>
                    <small class="text-muted ml-auto">{{ $entry['is_enabled'] ? 'Enabled' : 'Draft' }}</small>
                </li>
            @endforeach
        </ol>
        <p id="p06-sort-feedback" role="status" aria-live="polite" aria-atomic="true" class="small mb-0 text-muted">
            {{ count($draft['sections']) < 2 ? 'Add two or more sections to reorder.' : 'Current draft order is saved.' }}
        </p>
        <noscript><p class="text-muted small">Use each section's Up/Down controls below to save its position.</p></noscript>
    </div>
</section>
<div id="p06-section-editors">
@forelse($draft['sections'] as $idx=>$section)
<section data-p06-section-card data-section-key="{{ $section['section_key'] }}" class="card card-outline {{ $section['is_enabled'] ? 'card-success' : 'card-secondary' }}" id="section-{{ $section['section_key'] }}">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
        <h2 class="h5 mb-0"><span class="p06-card-number">{{ $idx+1 }}</span>. {{ $types[$section['type']] ?? 'Unknown Section' }} <small class="text-muted">{{ $section['is_enabled'] ? 'Enabled' : 'Draft only / disabled' }}</small></h2>
        <div class="btn-group btn-group-sm" aria-label="Reorder section {{ $idx + 1 }}">
            <form method="POST" action="{{ route('admin.storefront.home.sections.move',$section['section_key']) }}">@csrf @method('PATCH')
                <input type="hidden" name="expected_version" value="{{ $version }}"><input type="hidden" name="direction" value="up">
                <button class="btn btn-outline-secondary" data-p06-direction="up" title="Move up" @disabled($idx === 0)>↑ Up</button>
            </form>
            <form method="POST" action="{{ route('admin.storefront.home.sections.move',$section['section_key']) }}">@csrf @method('PATCH')
                <input type="hidden" name="expected_version" value="{{ $version }}"><input type="hidden" name="direction" value="down">
                <button class="btn btn-outline-secondary" data-p06-direction="down" title="Move down" @disabled($idx === count($draft['sections'])-1)>↓ Down</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.storefront.home.sections.update',$section['section_key']) }}">
            @csrf @method('PATCH')<input type="hidden" name="expected_version" value="{{ $version }}">
            <div class="row"><div class="form-group col-md-6"><label>Heading</label>
                <input class="form-control" type="text" name="title" maxlength="160" value="{{ $section['title'] }}"></div>
                <div class="form-group col-md-6"><label>Subtitle</label>
                <input class="form-control" type="text" name="subtitle" maxlength="200" value="{{ $section['subtitle'] }}"></div></div>
            <div class="form-group"><label>Description</label>
                <textarea class="form-control" name="description" rows="2" maxlength="3000">{{ $section['description'] }}</textarea></div>
            <div class="form-group d-flex flex-wrap" style="gap:1.5rem">
                <label><input type="checkbox" name="is_enabled" value="1" @checked($section['is_enabled'])> Enable section</label>
                <label><input type="checkbox" name="show_desktop" value="1" @checked($section['show_desktop'])> Desktop</label>
                <label><input type="checkbox" name="show_mobile" value="1" @checked($section['show_mobile'])> Mobile</label>
            </div>
            @if($section['type']==='category_grid')
                <fieldset><legend class="h6">Show approved categories (max 24)</legend><div class="row">
                    @forelse($categories as $cat)<label class="col-sm-6 col-lg-3 small mb-2"><input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" @checked(in_array($cat->id,$section['categories']))> {{ $cat->name }}</label>
                    @empty <p class="text-muted ml-3">No active categories available.</p>@endforelse
                </div></fieldset>
            @elseif($section['type']==='category_products')
                <fieldset><legend class="h6">Category-wise product shelf</legend>
                    <p class="text-muted small">Create one section per category (e.g. Wholesale, Kitchen). Products load automatically from active inventory, including eligible subcategories if selected.</p>
                    <div class="row"><div class="form-group col-lg-5">
                        <label for="category-source-{{ $section['section_key'] }}">Source category</label>
                        <select class="form-control" required name="source_category_id" id="category-source-{{ $section['section_key'] }}">
                            <option value="">Choose active category</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((int)($section['category_id'] ?? 0)===(int)$cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div><div class="form-group col-lg-3">
                        <label>Products to show</label>
                        <input class="form-control" type="number" name="product_limit" min="1" max="24" value="{{ $section['product_limit'] ?: 8 }}">
                    </div><div class="form-group col-lg-4"><label>Sort products</label>
                        <select name="product_sort" class="form-control">
                            @foreach(['newest'=>'Newest', 'name'=>'Name', 'price_asc'=>'Price: low to high', 'price_desc'=>'Price: high to low'] as $sortKey=>$sortTitle)
                                <option value="{{ $sortKey }}" @selected($section['product_sort']===$sortKey)>{{ $sortTitle }}</option>
                            @endforeach
                        </select>
                    </div></div>
                    <label><input type="checkbox" name="include_descendants" value="1" @checked($section['include_descendants'])> Include direct subcategory products</label>
                </fieldset>
            @elseif($section['type']==='product_list')
                <fieldset><legend class="h6">Select curated products (max 50)</legend>
                    <div style="max-height:250px;overflow-y:auto" class="border rounded p-2"><div class="row">
                    @forelse($products as $product)<label class="col-sm-6 col-lg-4 small mb-2"><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(in_array($product->id,$section['products']))> {{ $product->name }}</label>
                    @empty <p class="text-muted ml-3">No active products available.</p>@endforelse
                    </div></div>
                    <small class="form-text text-muted">List is capped at 200 recent active products; selected products outside this window remain saved until explicitly replaced.</small>
                </fieldset>
            @endif
            <details class="mt-3 mb-3"><summary class="font-weight-bold">Section Design (optional overrides)</summary>
                <div class="row mt-2">
                    @foreach(\App\Support\HomeSectionSchema::STYLE_KEYS as $key)
                    @php $override = $section['styles'][$key] ?? ''; @endphp
                    <div class="form-group col-sm-6 col-lg-4">
                        <label for="section-{{ $section['section_key'] }}-{{ $key }}">{{ \Illuminate\Support\Str::headline($key) }}</label>
                        <div class="d-flex p06-color-controls">
                            <input type="color" class="p06-color-picker" aria-label="Pick {{ $key }} for this section"
                                data-p06-color-picker data-p06-hex-input="section-{{ $section['section_key'] }}-{{ $key }}"
                                value="{{ $override ?: ($colors[$key] ?? $themeDefaults[$key]) }}">
                            <input id="section-{{ $section['section_key'] }}-{{ $key }}" class="form-control" type="text"
                                data-p06-hex-value name="styles[{{ $key }}]" value="{{ $override }}"
                                placeholder="Global" maxlength="7" pattern="#[0-9a-fA-F]{6}">
                            <button class="btn btn-outline-secondary btn-sm" type="button"
                                data-p06-clear-color="section-{{ $section['section_key'] }}-{{ $key }}"
                                title="Clear override and inherit global" aria-label="Inherit global {{ $key }}">Reset</button>
                        </div>
                    </div>@endforeach
                </div><small class="text-muted">Blank = inherit global theme. Preview reflects only saved draft tokens. Raw CSS is rejected.</small>
            </details>
            <button class="btn btn-success">Save Section</button>
        </form>
        @if(in_array($section['type'], ['hero_slider','image_text','reviews']))
        @if($section['type'] === 'hero_slider')
        <div class="card border-primary bg-light mt-3 mb-3" id="p33-upload-{{ $section['section_key'] }}">
            <div class="card-body">
                <h3 class="h5 mb-2"><i class="fas fa-images mr-1"></i> Add multiple hero images</h3>
                <p class="small text-muted">Upload 1–6 JPG, PNG or WebP images (max 4 MB each). Each file becomes a separate slide in this <strong>draft</strong>. The public carousel auto-scrolls from right to left after publishing. You can also choose an existing approved image below.</p>
                <form action="{{ route('admin.storefront.home.hero-images.store', $section['section_key']) }}" method="POST" enctype="multipart/form-data">
                    @csrf<input type="hidden" name="expected_version" value="{{ $version }}">
                    <div class="form-group">
                        <label for="p33-images-{{ $section['section_key'] }}">Hero banner images</label>
                        <input id="p33-images-{{ $section['section_key'] }}" type="file" name="hero_images[]" class="form-control-file" accept="image/jpeg,image/png,image/webp" multiple required>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fas fa-upload mr-1"></i> Upload &amp; add slides</button>
                    <small class="d-block mt-2 text-muted">Tip: use landscape banners (roughly 1600×700). Uploads cannot replace an immutable published revision. Remove from draft using the buttons below.</small>
                </form>
            </div>
        </div>
        @endif
        <hr><h3 class="h6">Section Items ({{ count($section['items']) }})</h3>
        @forelse($section['items'] as $item)
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
            <div class="d-flex align-items-center" style="gap:12px">
                @if($item['media_id'] && isset($mediaThumbnails[$item['media_id']]))
                    <img src="{{ $mediaThumbnails[$item['media_id']]->getUrl() }}" alt="" loading="lazy" style="width:110px;height:55px;object-fit:cover;border-radius:7px">
                @endif
                <div><strong>{{ $item['title'] ?: ($item['type']==='review' ? 'Review #'.$item['review_id'] : 'Image') }}</strong>
                <small class="text-muted d-block">{{ $item['type'] }} · {{ $item['media_id'] ? 'Media #'.$item['media_id'] : '' }}</small></div>
            </div>
            <form method="POST" action="{{ route('admin.storefront.home.items.delete',[$section['section_key'],$item['item_key']]) }}">@csrf @method('DELETE')
                <input type="hidden" name="expected_version" value="{{ $version }}">
                <button class="btn btn-outline-danger btn-sm" aria-label="Remove item">Remove</button>
            </form>
        </div>
        @empty <p class="text-muted small">No items yet.</p> @endforelse
        <form method="POST" action="{{ route('admin.storefront.home.items.store',$section['section_key']) }}" class="mt-3">
            @csrf<input type="hidden" name="expected_version" value="{{ $version }}">
            <div class="row">
                @if($section['type']==='reviews')
                <div class="form-group col-md-6"><label>Public / Global Review</label>
                    <select class="form-control" name="review_id" required><option value="">Choose a review</option>
                        @foreach($reviews as $review)<option value="{{ $review->id }}">#{{ $review->id }} — {{ $review->customer_name }}</option>@endforeach
                    </select>
                </div>
                @if($reviews->isEmpty())
                    <div class="col-12">
                        <div class="alert alert-warning py-2 mb-3" role="status">
                            <strong>No active Global / Store Review is available.</strong>
                            Campaign-specific reviews are intentionally excluded from this homepage.
                            Open <a href="{{ route('admin.reviews.index', ['campaign_id' => 'global']) }}"
                                target="_blank" rel="noopener noreferrer" class="alert-link">Customer Reviews</a>
                            to add a genuine General / Store Review, or edit an existing one and change
                            its campaign to General (this removes its original campaign association).
                            Keep the review Active, save it, then reload this Builder page.
                        </div>
                    </div>
                @endif
                @else
                <div class="form-group col-md-6"><label>Approved public media file</label>
                    <select class="form-control" name="media_id" required><option value="">Choose media</option>
                        @foreach($mediaChoices as $choice)<option value="{{ $choice->id }}">#{{ $choice->id }} — {{ $choice->file_name }}</option>@endforeach
                    </select></div>
                <div class="form-group col-md-6"><label>Title</label><input class="form-control" name="title" maxlength="160"></div>
                <div class="form-group col-md-6"><label>Text</label><input class="form-control" name="description" maxlength="2000"></div>
                <div class="form-group col-md-3"><label>CTA label</label><input class="form-control" name="link_label" maxlength="80"></div>
                <div class="form-group col-md-3"><label>CTA URL (HTTPS or /path)</label><input class="form-control" name="link_url" maxlength="2048"></div>
                @endif
            </div>
            <button class="btn btn-outline-success"
                @disabled($section['type']==='reviews' && $reviews->isEmpty())>Add Item</button>
        </form>
        @endif
        <hr>
        <form method="POST" action="{{ route('admin.storefront.home.sections.delete',$section['section_key']) }}">
            @csrf @method('DELETE')<input type="hidden" name="expected_version" value="{{ $version }}">
            <button class="btn btn-outline-danger btn-sm">Delete Draft Section</button>
        </form>
    </div>
</section>
@empty
<section class="card"><div class="card-body text-center text-muted py-5">
    <h2 class="h5">No homepage sections yet</h2>
    <p class="mb-0">Add Hero, Category or Product Collection above.</p>
</div></section>
@endforelse
</div>
<section class="card card-outline card-success"><div class="card-body d-flex justify-content-between align-items-center flex-wrap">
    <div><h2 class="h5 mb-1">Publish this revision to the live Storefront Homepage</h2><p class="text-muted small mb-0">Checks active resources, image files and contrast, then publishes at / without replacing campaign pages.</p></div>
    <form method="POST" action="{{ route('admin.storefront.home.publish') }}" class="mt-2">@csrf
        <input type="hidden" name="expected_version" value="{{ $version }}">
        <button class="btn btn-success">Validate &amp; Publish Staged Revision</button>
    </form>
</div></section>
@endif
@if(count($history)>0)
<section class="card"><div class="card-header"><h2 class="card-title">Revision History (immutable content)</h2></div>
<div class="card-body table-responsive"><table class="table table-sm"><thead><tr><th>Revision</th><th>Status</th><th>Published</th><th>Preview</th><th>Restore as New Draft</th></tr></thead><tbody>
@foreach($history as $entry)<tr>
    <td>#{{ $entry['revision_number'] }}</td><td>{{ strtoupper($entry['status']) }}</td>
    <td>{{ $entry['published_at'] ?? '—' }}</td>
    <td><a target="_blank" rel="noopener noreferrer" href="{{ route('admin.storefront.home.preview',['revision'=>$entry['id']]) }}">Preview</a></td>
    <td>@if(!$draft && in_array($entry['status'], ['published','archived']))
        <form method="POST" action="{{ route('admin.storefront.home.restore',$entry['id']) }}">@csrf
            <input type="hidden" name="expected_version" value="{{ $version }}">
            <button class="btn btn-outline-secondary btn-sm">Restore a Copy</button>
        </form>
    @else <span class="text-muted small">{{ $draft ? 'Finish draft first' : '—' }}</span> @endif</td>
</tr>@endforeach
</tbody></table></div></section>
@endif
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('frontend/css/home-builder-sort.css') }}">
@stop

@section('js')
<script src="{{ asset('frontend/js/home-builder-sort.js') }}" defer></script>
@stop
