@php
    $media = $getRecord();
    $url = $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl();
@endphp

<img
    src="{{ $url }}"
    alt="{{ $media->name }}"
    loading="lazy"
    class="aspect-square w-full rounded-lg object-cover"
/>
