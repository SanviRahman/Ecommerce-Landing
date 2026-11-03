@if(!empty($campaignMatrix['options']) && !empty($campaignMatrix['variants']))
    <div class="sfa-campaign-variant-preview" data-campaign-variant-matrix="{{ json_encode($campaignMatrix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}"
         data-campaign-product-id="{{ $product->id }}" data-campaign-base-price="{{ $campaignBasePrice }}">
        @foreach($campaignMatrix['options'] as $option)
            <fieldset class="sfa-campaign-choice-group" data-choice-group="{{ $option['id'] }}">
                <legend>{{ $option['name'] }}</legend>
                <div class="sfa-campaign-choice-row">
                    @foreach($option['values'] as $value)
                        @php $swatch = isset($value['swatch']) && preg_match('/\A#[0-9a-fA-F]{6}\z/D', (string) $value['swatch']) ? $value['swatch'] : null; @endphp
                        <label title="{{ empty($value['configured']) ? 'This variation is not configured for this product' : $value['label'] }}">
                            <input type="radio" name="{{ $campaignPrefix }}-option-{{ $option['id'] }}" data-option-id="{{ $option['id'] }}"
                                   value="{{ $value['id'] }}" @disabled(empty($value['configured']))>
                            <span class="sfa-campaign-choice-chip">
                                @if($swatch)<i style="--sfa-swatch:{{ $swatch }}" aria-hidden="true"></i>@endif
                                {{ $value['label'] }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
        <p class="sfa-campaign-choice-status" data-campaign-choice-status role="status" aria-live="polite"></p>
        <small class="sfa-campaign-variant-warning">Variant orders use the Storefront checkout; campaign ordering remains available for simple products.</small>
    </div>
@elseif($product->has_variants)
    <p class="small text-warning">No configured and active variation is available.</p>
@endif
