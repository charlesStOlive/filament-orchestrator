{{-- Le fichier que l'on s'apprête à choisir (action « pickOne » de MediaLibraryTable), entier, dans la confirmation. --}}
<div class="flex justify-center" data-library-pick-preview="{{ $media->getKey() }}">
    @if ($media->isVideo())
        <video src="{{ $media->getUrl() }}#t=0.1" preload="metadata" muted playsinline class="max-h-64 rounded-lg bg-black"></video>
    @else
        <img
            src="{{ $media->isYoutube() ? $media->thumbUrl() : $media->conversionUrl('medium') }}"
            alt="{{ $media->name }}"
            class="max-h-64 rounded-lg object-contain"
        />
    @endif
</div>
