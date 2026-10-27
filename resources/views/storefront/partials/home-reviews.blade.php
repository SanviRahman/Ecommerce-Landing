@php
    // Private builder preview retains revision-pinned IDs. Public homepage renders
    // live active global reviews, so newly approved Admin reviews appear immediately.
    $visibleReviews = ($isPreview ?? false)
        ? collect($section['items'])->filter(static fn ($item) => $item['is_enabled'] && $item['review_id'] && $reviews->has($item['review_id']))
            ->map(static fn ($item) => $reviews[$item['review_id']])->values()
        : collect($publicReviews ?? [])->values();
@endphp
<div class="sf-reviews-shell p37-review-shell" data-review-carousel>
    <div class="sf-grid sf-grid-reviews" data-review-track>
        @forelse($visibleReviews as $review)
            <figure class="sf-review p37-review-card">
                <div class="p37-review-stars" aria-label="{{ (int) $review->rating }} out of 5 stars">
                    {{ str_repeat('★', max(0, min(5, (int) $review->rating))) }}{{ str_repeat('☆', max(0, 5 - (int) $review->rating)) }}
                </div>
                <blockquote>{{ $review->review_text ?: 'Rated '.(int) $review->rating.' out of 5 stars.' }}</blockquote>
                <figcaption class="p37-review-author">
                    @php $photo = $review->getFirstMediaUrl('review_customer_image'); @endphp
                    @if($photo)
                        <img src="{{ $photo }}" alt="" class="p37-review-avatar" loading="lazy" decoding="async">
                    @else
                        <span class="p37-review-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($review->customer_name, 0, 1)) }}</span>
                    @endif
                    <span><strong>{{ $review->customer_name }}</strong>
                        @if($review->location)<small>{{ $review->location }}</small>@endif
                    </span>
                </figcaption>
            </figure>
        @empty
            @if($isPreview ?? false)<p>No active reviews are selected in this draft.</p>@endif
        @endforelse
    </div>
    <div class="sf-review-controls" data-review-controls hidden>
        <button type="button" data-review-prev aria-label="Previous review">←</button>
        <button type="button" data-review-next aria-label="Next review">→</button>
    </div>
</div>
