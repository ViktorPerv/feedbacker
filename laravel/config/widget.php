<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Widget service
    |--------------------------------------------------------------------------
    |
    | The embeddable script is served by the Go service, not by the
    | application, so the snippet has to point at that origin. In production
    | both usually sit behind one domain and this is the same host; in local
    | development they are separate ports.
    |
    */

    'public_url' => env('WIDGET_PUBLIC_URL', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | Homepage demo
    |--------------------------------------------------------------------------
    |
    | The landing page embeds a real widget from this space, so a visitor sees
    | the product working rather than a screenshot of it. Null disables the
    | section.
    |
    */

    'demo_space' => env('WIDGET_DEMO_SPACE', 'admin-space'),

];
