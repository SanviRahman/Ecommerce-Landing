@php
    use App\Models\TrackingPixel;

    $trackingScripts = TrackingPixel::query()
        ->active()
        ->whereNotNull('script_code')
        ->where('script_code', '!=', '')
        ->pluck('script_code')
        ->filter()
        ->unique()
        ->values();
@endphp

@if ($trackingScripts->isNotEmpty())
    @foreach ($trackingScripts as $scriptCode)
        {!! $scriptCode !!}
    @endforeach
@endif