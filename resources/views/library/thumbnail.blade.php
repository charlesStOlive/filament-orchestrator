@php
    $media = $getRecord();
    $url = $media->thumbUrl();
@endphp

<img
    src="{{ $url }}"
    alt="{{ $media->name }}"
    loading="lazy"
    class="aspect-square w-full rounded-lg object-cover"
/>
