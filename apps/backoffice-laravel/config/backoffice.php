<?php

return [
    'brand' => [
        'html_title' => 'EstetiCAN Backoffice',
        'kicker' => 'EstetiCAN',
        'shell_title' => 'Backoffice operativo',
        'version' => '260426-2225',
        'favicon' => 'favicon.webp',
    ],

    'fonts' => [
        'preconnect' => [
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
        ],
        'stylesheet_url' => 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700&display=swap',
    ],

    'shell' => [
        'home_label' => 'Inicio',
        'mobile_menu_label' => 'Menú',
    ],

    'ui' => [
        'density' => 'compact',
        'palette' => 'earth-clinic',
        'enable_resizable_tables' => true,
        'show_screen_debug_ids' => true,
        'tooltip_delay_ms' => env('BACKOFFICE_TOOLTIP_DELAY_MS', 1500),
        'toast_duration_ms' => env('BACKOFFICE_TOAST_DURATION_MS', 5000),
        'palettes' => [
            'earth-clinic' => [
                'label' => 'Tierra clínica',
                'description' => 'Arena cálida, nogal operativo y acento arcilla para una lectura más terrenal y serena.',
                'colors' => ['#f5e8d8', '#7b5a45', '#c9703c', '#2b2621'],
            ],
            'coast-professional' => [
                'label' => 'Floral editorial',
                'description' => 'Rosas empolvados, vino suave y crema pétalo para una presencia delicada pero profesional.',
                'colors' => ['#f7e8ee', '#b45f7d', '#d9899f', '#3f2b33'],
            ],
            'slate-premium' => [
                'label' => 'Pizarra premium',
                'description' => 'Piedra editorial, azul acero y bronce sobrio para pantallas densas con presencia ejecutiva.',
                'colors' => ['#ebe9e4', '#4b5d7a', '#b9905a', '#232831'],
            ],
            'sage-ledger' => [
                'label' => 'Sage ledger',
                'description' => 'Salvia contable y tierra seca para priorizar filas, bloques y sensación de orden administrativo.',
                'colors' => ['#edf3ea', '#587a53', '#9d6b3f', '#223028'],
            ],
        ],
    ],

    'assets' => [
        'use_hot_reload' => env('BACKOFFICE_USE_VITE_HOT', false),
    ],

    'system' => [
        'timezone' => env('BACKOFFICE_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
        'locale' => env('BACKOFFICE_LOCALE', env('APP_LOCALE', 'es')),
        'currency_code' => env('BACKOFFICE_CURRENCY_CODE', 'MXN'),
        'date_format' => env('BACKOFFICE_DATE_FORMAT', 'd/m/Y'),
        'time_format' => env('BACKOFFICE_TIME_FORMAT', '12h'),
        'resource_cleaning_buffer_minutes' => env('BACKOFFICE_RESOURCE_CLEANING_BUFFER_MINUTES', 15),
        'default_address_country' => env('BACKOFFICE_DEFAULT_ADDRESS_COUNTRY', 'México'),
        'default_address_state' => env('BACKOFFICE_DEFAULT_ADDRESS_STATE', ''),
        'default_address_city' => env('BACKOFFICE_DEFAULT_ADDRESS_CITY', ''),
    ],

    'profiling' => [
        'enabled' => env('BACKOFFICE_PROFILING', env('APP_ENV') === 'local' && env('APP_DEBUG', false)),
        'response_headers' => env('BACKOFFICE_PROFILING_HEADERS', true),
        'slow_request_ms' => env('BACKOFFICE_SLOW_REQUEST_MS', 250),
        'slow_query_ms' => env('BACKOFFICE_SLOW_QUERY_MS', 80),
        'log_channel' => env('BACKOFFICE_PROFILING_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
    ],

    'cache' => [
        'catalog_ttl_seconds' => env('BACKOFFICE_CATALOG_CACHE_TTL_SECONDS', 1800),
    ],

    'security' => [
        'confirm_destructive_actions' => true,
        'session_idle_minutes' => env('BACKOFFICE_SESSION_IDLE_MINUTES', env('SESSION_LIFETIME', 120)),
    ],

    'images' => [
        'pets' => [
            'main_max_size' => env('BACKOFFICE_PET_IMAGE_MAX_SIZE', 1200),
            'main_quality' => env('BACKOFFICE_PET_IMAGE_QUALITY', 80),
            'thumbnail_width' => env('BACKOFFICE_PET_THUMB_WIDTH', 160),
            'thumbnail_height' => env('BACKOFFICE_PET_THUMB_HEIGHT', 120),
            'thumbnail_quality' => env('BACKOFFICE_PET_THUMB_QUALITY', 65),
        ],
        'operators' => [
            'main_max_size' => env('BACKOFFICE_OPERATOR_IMAGE_MAX_SIZE', 800),
            'main_quality' => env('BACKOFFICE_OPERATOR_IMAGE_QUALITY', 80),
            'thumbnail_size' => env('BACKOFFICE_OPERATOR_THUMB_SIZE', 160),
            'thumbnail_quality' => env('BACKOFFICE_OPERATOR_THUMB_QUALITY', 65),
        ],
        'resources' => [
            'main_max_size' => env('BACKOFFICE_RESOURCE_IMAGE_MAX_SIZE', 1200),
            'main_quality' => env('BACKOFFICE_RESOURCE_IMAGE_QUALITY', 80),
            'thumbnail_width' => env('BACKOFFICE_RESOURCE_THUMB_WIDTH', 160),
            'thumbnail_height' => env('BACKOFFICE_RESOURCE_THUMB_HEIGHT', 120),
            'thumbnail_quality' => env('BACKOFFICE_RESOURCE_THUMB_QUALITY', 65),
        ],
        'users' => [
            'main_max_size' => env('BACKOFFICE_USER_IMAGE_MAX_SIZE', 800),
            'main_quality' => env('BACKOFFICE_USER_IMAGE_QUALITY', 80),
            'thumbnail_size' => env('BACKOFFICE_USER_THUMB_SIZE', 160),
            'thumbnail_quality' => env('BACKOFFICE_USER_THUMB_QUALITY', 65),
        ],
    ],
];