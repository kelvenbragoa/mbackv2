<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $url }}">

    <meta property="og:site_name" content="{{ config('opengraph.site_name') }}">
    <meta property="og:locale" content="pt_PT">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:image" content="{{ $image['url'] }}">
    <meta property="og:image:secure_url" content="{{ $image['url'] }}">
    <meta property="og:image:alt" content="{{ $title }}">
    @if ($image['type'])
        <meta property="og:image:type" content="{{ $image['type'] }}">
    @endif
    @if ($image['width'] && $image['height'])
        <meta property="og:image:width" content="{{ $image['width'] }}">
        <meta property="og:image:height" content="{{ $image['height'] }}">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image['url'] }}">
</head>

<body>
    <p><a href="{{ $url }}">{{ $title }}</a></p>

    {{-- Redirecionamento em JS, não em meta refresh: os crawlers não executam
         JavaScript, por isso não registam isto como um salto de redirect. Só um
         humano que abra /og/... à mão é que segue para o site. --}}
    <script>window.location.replace(@json($url));</script>
</body>

</html>
