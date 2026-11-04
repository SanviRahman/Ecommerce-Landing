@php
    $prefix = $fieldPrefix ?? '';
    $oldPrefix = $fieldOldPrefix ?? '';
    $field = static fn (string $name): string => $prefix !== '' ? "{$prefix}[{$name}]" : $name;
    $oldField = static fn (string $name): string => $oldPrefix !== '' ? "{$oldPrefix}.{$name}" : $name;
    $row = $rowData ?? [];
    $current = $pixelData ?? null;
    $platform = old($oldField('platform'), $row['platform'] ?? $current?->platform ?? 'meta');
    $active = (bool) old($oldField('status'), $row['status'] ?? $current?->status ?? true);
    $capi = (bool) old($oldField('capi_enabled'), $row['capi_enabled'] ?? $current?->capi_enabled ?? false);
    $scriptValue = (string) old($oldField('script_code'), $row['script_code'] ?? $current?->script_code ?? '');
    $isGtm = $platform === 'gtm';
    $hasToken = $current !== null && !empty($current->capi_access_token);
@endphp
<div class="sfa-pixel-row border rounded p-3 mb-3" data-has-token="{{ $hasToken ? '1' : '0' }}">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <strong class="sfa-pixel-row-title">{{ $current ? 'Edit Tracking Integration' : 'New Tracking Integration' }}</strong>
        @if(!($isEdit ?? false))
            <button class="btn btn-outline-danger btn-sm sfa-remove-pixel" type="button" aria-label="Remove this integration"><i class="fas fa-times mr-1"></i> Remove</button>
        @endif
    </div>
    <div class="form-row">
        <div class="form-group col-md-4">
            <label>Platform <span class="text-danger">*</span></label>
            <select name="{{ $field('platform') }}" data-platform class="form-control" required>
                @foreach($platforms as $key => $label)
                    <option value="{{ $key }}" @selected($platform === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-4">
            <label>Integration Name</label>
            <input name="{{ $field('name') }}" class="form-control" maxlength="255"
                   value="{{ old($oldField('name'), $row['name'] ?? $current?->name ?? '') }}"
                   placeholder="e.g. Main Website Tracking">
        </div>
        <div class="form-group col-md-4">
            <label data-id-label>{{ $isGtm ? 'GTM Container ID' : 'Manual Pixel ID / Container ID' }} @if($isGtm)<span class="text-danger">*</span>@endif</label>
            <input name="{{ $field('pixel_id') }}" data-pixel-id class="form-control" maxlength="255"
                   value="{{ old($oldField('pixel_id'), $row['pixel_id'] ?? $current?->pixel_id ?? '') }}"
                   placeholder="{{ $isGtm ? 'GTM-TS7Q7WGG' : 'e.g. 2355381278153504' }}"
                   @required($isGtm && trim($scriptValue) === '')>
            <small class="text-muted" data-id-help>
                {{ $isGtm ? 'Enter the GTM Container ID beginning with GTM-.' : 'Provide an ID or paste a script containing the ID.' }}
            </small>
        </div>
    </div>

    <div class="alert alert-info py-2 mb-3" data-gtm-help @if(!$isGtm) hidden @endif>
        <strong><i class="fas fa-info-circle mr-1"></i> Google Tag Manager setup</strong>
        <div class="small mt-1">
            Enter only your Web Container ID (for example, <code>GTM-TS7Q7WGG</code>) and leave Full Tracking Script empty. Do not enter a Meta Pixel ID or GA4 Measurement ID here.
            The website automatically generates Google's standard GTM loader in the page head and the noscript iframe after the opening body tag. Use one container for this website, not one per Ads campaign.
            <a href="https://tagmanager.google.com/" target="_blank" rel="noopener noreferrer">Open Google Tag Manager</a>
            <span class="mx-1">·</span>
            <a href="https://tagassistant.google.com/" target="_blank" rel="noopener noreferrer">Test with Tag Assistant</a>
        </div>
    </div>

    <div class="form-group">
        <label data-script-label>Full Tracking Script <small class="text-muted font-weight-normal">(optional)</small></label>
        <textarea name="{{ $field('script_code') }}" data-script class="form-control font-monospace" rows="7"
                  maxlength="20000" placeholder="{{ $isGtm ? 'Optional legacy full GTM snippet. Container ID-only setup is recommended.' : 'Optional: paste the official tracking script here' }}">{{ $scriptValue }}</textarea>
        <small class="text-muted" data-script-help>
            {{ $isGtm ? 'Optional for GTM. A valid Container ID is enough; the official GTM snippets are generated automatically.' : 'Optional for Meta, TikTok and Google Analytics. The platform ID can also be extracted from a supported script.' }}
        </small>
    </div>

    <div class="sfa-capi-options rounded border p-3 mb-3" data-capi-fields @if($platform !== 'meta') hidden @endif>
        <div class="custom-control custom-checkbox mb-3">
            <input type="hidden" name="{{ $field('capi_enabled') }}" value="0">
            <input type="checkbox" class="custom-control-input" data-capi-switch
                   id="sfa-capi-{{ $prefix ?: 'edit' }}" name="{{ $field('capi_enabled') }}" value="1" @checked($capi)>
            <label class="custom-control-label font-weight-bold" for="sfa-capi-{{ $prefix ?: 'edit' }}">Enable Meta Conversions API (server-side) — optional</label>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6 mb-0">
                <label>CAPI Access Token <span class="text-muted">(required only when enabled)</span></label>
                <input type="password" data-capi-token name="{{ $field('capi_access_token') }}"
                       class="form-control" maxlength="4096" autocomplete="new-password"
                       placeholder="{{ $hasToken ? 'Leave empty to keep saved token' : 'Paste Meta CAPI access token' }}">
                <small class="text-muted">Saved encrypted; never displayed after saving.</small>
            </div>
            <div class="form-group col-md-6 mb-0">
                <label>Test Event Code <span class="text-muted">(optional)</span></label>
                <input name="{{ $field('capi_test_event_code') }}" class="form-control" maxlength="100"
                       value="{{ old($oldField('capi_test_event_code'), $row['capi_test_event_code'] ?? $current?->capi_test_event_code ?? '') }}"
                       placeholder="TEST12345">
                <small class="text-muted">Used only with the admin Send Test Event action.</small>
            </div>
        </div>
        <small class="text-muted d-block mt-2">Browser and server events share the same event ID to support Meta deduplication.</small>
    </div>
    <div class="custom-control custom-switch">
        <input type="hidden" name="{{ $field('status') }}" value="0">
        <input type="checkbox" class="custom-control-input" id="sfa-status-{{ $prefix ?: 'edit' }}"
               name="{{ $field('status') }}" value="1" @checked($active)>
        <label class="custom-control-label" for="sfa-status-{{ $prefix ?: 'edit' }}">Active on website</label>
    </div>
</div>
