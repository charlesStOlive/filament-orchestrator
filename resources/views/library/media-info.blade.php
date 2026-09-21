{{--
    Ce que le serveur sait du fichier, en lecture seule : le bandeau gauche de la fenêtre d'édition d'une image ou d'une
    vidéo. La durée et les dimensions d'une vidéo, que le serveur ne lit pas, s'affichent sous son lecteur.
--}}
@php
    $dimensions = $media->isImage() ? $media->dimensions() : null;
@endphp

<dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-xs" data-library-info>
    <dt class="text-gray-500 dark:text-gray-400">Fichier</dt>
    <dd class="min-w-0 break-words font-medium text-gray-950 dark:text-white">{{ $media->file_name }}</dd>

    <dt class="text-gray-500 dark:text-gray-400">Type</dt>
    <dd class="text-gray-950 dark:text-white">{{ $media->isVideo() ? 'Vidéo' : 'Image' }} · {{ strtoupper(str_replace(['image/', 'video/', 'quicktime'], ['', '', 'mov'], (string) $media->mime_type)) }}</dd>

    <dt class="text-gray-500 dark:text-gray-400">Poids</dt>
    <dd class="text-gray-950 dark:text-white">{{ $media->human_readable_size }}</dd>

    @if ($dimensions)
        <dt class="text-gray-500 dark:text-gray-400">Taille</dt>
        <dd class="text-gray-950 dark:text-white">{{ $dimensions['width'] }} × {{ $dimensions['height'] }} px</dd>
    @endif

    <dt class="text-gray-500 dark:text-gray-400">Date</dt>
    <dd class="text-gray-950 dark:text-white">{{ $media->dateSourceLabel() }}</dd>

    <dt class="text-gray-500 dark:text-gray-400">Chargé le</dt>
    <dd class="text-gray-950 dark:text-white">{{ $media->created_at?->locale('fr')->translatedFormat('j F Y à H:i') }}</dd>
</dl>

<a
    href="{{ $media->getUrl() }}"
    target="_blank"
    rel="noopener"
    class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
>
    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-3.5 w-3.5" />
    Ouvrir le fichier d’origine
</a>
