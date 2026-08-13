<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Open Graph (previews de partilha)
    |--------------------------------------------------------------------------
    |
    | O SPA em mticket.co.mz não consegue servir meta tags a crawlers como o
    | WhatsApp/Facebook (não executam JavaScript). O Nginx do frontend desvia
    | esses crawlers para as rotas /og/* deste backend, que devolvem HTML já
    | com as tags. Estes valores definem os links canónicos e as imagens.
    |
    */

    'root_domain' => env('OG_ROOT_DOMAIN', 'mticket.co.mz'),

    'base_url' => env('OG_BASE_URL', 'https://backend.mticket.co.mz'),

    'storage_url' => env('OG_STORAGE_URL', 'https://backend.mticket.co.mz/storage'),

    'fallback_image' => env('OG_FALLBACK_IMAGE', 'https://mticket.co.mz/demo/images/logo2.png'),

    /*
    | JPEG e PNG dentro do limite de tamanho são partilhados directamente do
    | /storage. O resto (WebP, que o WhatsApp não mostra, ou ficheiros grandes
    | demais) passa pela rota /og/imagem/*, que devolve um JPEG em cache.
    */
    'direct_mime_types' => ['image/jpeg', 'image/png'],

    'image_max_width' => 1200,

    'image_quality' => 82,

    'image_max_bytes' => 450 * 1024,

    'site_name' => env('OG_SITE_NAME', 'Mticket'),

    'default_description' => 'Compra bilhetes para os melhores eventos em Moçambique na Mticket.',

];
