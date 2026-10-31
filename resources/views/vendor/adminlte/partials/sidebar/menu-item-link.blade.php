<li @isset($item['id']) id="{{ $item['id'] }}" @endisset class="nav-item">
    @if (strtoupper((string) ($item['method'] ?? 'GET')) === 'POST')
        <form method="POST" action="{{ $item['href'] }}" class="m-0"
              onsubmit="return confirm('Run this administrator maintenance action?');">
            @csrf
            <button type="submit"
                    class="nav-link w-100 text-left border-0 {{ $item['class'] }} @isset($item['shift']) {{ $item['shift'] }} @endisset"
                    style="background: transparent; cursor: pointer;"
                    {!! $item['data-compiled'] ?? '' !!}>
                <i class="nav-icon {{ $item['icon'] ?? 'far fa-fw fa-circle' }} {{
                    isset($item['icon_color']) ? 'text-'.$item['icon_color'] : ''
                }}"></i>
                <p>{{ $item['text'] }}</p>
            </button>
        </form>
    @else
        <a class="nav-link {{ $item['class'] }} @isset($item['shift']) {{ $item['shift'] }} @endisset"
           href="{{ $item['href'] }}" @isset($item['target']) target="{{ $item['target'] }}" @endisset
           {!! $item['data-compiled'] ?? '' !!}>
            <i class="nav-icon {{ $item['icon'] ?? 'far fa-fw fa-circle' }} {{
                isset($item['icon_color']) ? 'text-'.$item['icon_color'] : ''
            }}"></i>
            <p>
                {{ $item['text'] }}
                @isset($item['label'])
                    <span class="badge badge-{{ $item['label_color'] ?? 'primary' }} right">
                        {{ $item['label'] }}
                    </span>
                @endisset
            </p>
        </a>
    @endif
</li>
