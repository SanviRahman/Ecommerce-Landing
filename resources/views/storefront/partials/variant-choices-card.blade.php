@php
    $optionMatrix = $catalog->selectionData($product);
    $optionGroupPrefix = $optionGroupPrefix ?? ('card-'.$product->id);
@endphp
@if(!empty($optionMatrix['options']))
    <div class="sfa-card-variation" data-card-variant-matrix="{{ json_encode($optionMatrix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}"
         aria-label="{{ $product->name }} available variations">
        @foreach($optionMatrix['options'] as $option)
            <fieldset class="sfa-card-variation-group" data-choice-group="{{ $option['id'] }}">
                <legend>{{ $option['name'] }}</legend>
                <div class="sfa-card-variation-values">
                    @foreach($option['values'] as $value)
                        @php $swatch = isset($value['swatch']) && preg_match('/\A#[0-9a-fA-F]{6}\z/D', (string) $value['swatch']) ? $value['swatch'] : null; @endphp
                        <label title="{{ empty($value['configured']) ? 'No SKU configured for this value' : $value['label'] }}">
                            <input type="radio" name="{{ $optionGroupPrefix }}-option-{{ $option['id'] }}" data-option-id="{{ $option['id'] }}"
                                   value="{{ $value['id'] }}" @disabled(empty($value['configured']))>
                            <span class="sfa-variant-chip">
                                @if($swatch)<i class="sfa-variant-swatch" style="--sfa-variant-swatch: {{ $swatch }}" aria-hidden="true"></i>@endif
                                {{ $value['label'] }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
        <p class="sfa-card-variation-status" data-card-variant-status role="status" aria-live="polite"></p>
    </div>
@endif
