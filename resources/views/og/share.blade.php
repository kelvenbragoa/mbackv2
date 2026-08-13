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
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:secure_url" content="{{ $image }}">
    <meta property="og:image:alt" content="{{ $title }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">

    {{-- Um humano só chega aqui se abrir /og/... à mão: segue para o site. --}}
    <meta http-equiv="refresh" content="0; url={{ $url }}">
</head>

<body>
    <p><a href="{{ $url }}">{{ $title }}</a></p>
</body>

</html>
