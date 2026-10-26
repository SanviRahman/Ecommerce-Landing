<div class="sf-reviews-shell" data-review-carousel>
    <div class="sf-grid sf-grid-reviews" data-review-track>
        @foreach($section['items'] as $item)
            @if($item['is_enabled'] && $item['review_id'] && $reviews->has($item['review_id']))
                @php $review = $reviews[$item['review_id']]; @endphp
                <figure class="sf-review">
                    <div class="sf-rating" aria-label="{{ (int) $review->rating }} out of 5 stars">{{ str_repeat('★', max(0, min(5, (int) $review->rating))) }}{{ str_repeat('☆', max(0, 5 - (int) $review->rating)) }}</div>
                    <blockquote>{{ $review->review_text }}</blockquote>
                    <figcaption>{{ $review->customer_name }}</figcaption>
                </figure>
            @endif
        @endforeach
    </div>
    <div class="sf-review-controls" data-review-controls hidden>
        <button type="button" data-review-prev aria-label="Previous review">←</button>
        <button type="button" data-review-next aria-label="Next review">→</button>
    </div>
</div>
