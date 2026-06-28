<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Responsive Image Variants
    |--------------------------------------------------------------------------
    |
    | Images uploaded through the admin are converted to WebP and stored at
    | multiple widths. The largest "original" variant is still capped so mobile
    | and desktop storefront pages do not accidentally serve camera-size files.
    |
    */

    'image_quality' => (int) env('MEDIA_IMAGE_QUALITY', 76),

    'image_variants' => [
        'thumbnail' => [
            'width' => (int) env('MEDIA_THUMBNAIL_WIDTH', 300),
        ],
        'card' => [
            'width' => (int) env('MEDIA_CARD_WIDTH', 600),
        ],
        'gallery' => [
            'width' => (int) env('MEDIA_GALLERY_WIDTH', 1200),
        ],
        'original' => [
            'width' => (int) env('MEDIA_ORIGINAL_MAX_WIDTH', 2000),
        ],
    ],

    'logo_variants' => [
        'thumbnail' => [
            'width' => (int) env('MEDIA_LOGO_THUMBNAIL_WIDTH', 160),
        ],
        'card' => [
            'width' => (int) env('MEDIA_LOGO_CARD_WIDTH', 320),
        ],
        'gallery' => [
            'width' => (int) env('MEDIA_LOGO_GALLERY_WIDTH', 640),
        ],
        'original' => [
            'width' => (int) env('MEDIA_LOGO_ORIGINAL_MAX_WIDTH', 1200),
        ],
    ],
];
