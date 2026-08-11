<?php

return [

    'driver' => env('SCOUT_DRIVER', 'tntsearch'),

    'queue' => false,

    'chunk' => 500,

    'soft_delete' => false,

    'prefix' => env('SCOUT_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | TNTSearch (driver config)
    |--------------------------------------------------------------------------
    |
    | TNTSearch driver necesita su config en `scout.tntsearch` directamente,
    | no bajo `engine.tntsearch`. Por eso este bloque vive al nivel raíz.
    */
    'tntsearch' => [
        'storage'   => storage_path('app/scout'),
        'tokenizer' => \TeamTNT\TNTSearch\TNTSearch::class,
        'tokenizer_params' => [
            'stemmer'    => \TeamTNT\TNTSearch\Stemmer\PorterStemmer::class,
            'stopwords'  => ['a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for', 'if', 'in', 'is', 'it', 'of', 'on', 'or', 'the', 'to', 'with'],
            'min_length' => 3,
        ],
        'fuzziness'      => env('SCOUT_TNTSEARCH_FUZZINESS', true),
        'fuzzy'          => [
            'max_edits'      => 2,
            'prefix_length'  => 1,
            'max_expansions' => 50,
        ],
        'search_boolean' => env('SCOUT_TNTSEARCH_BOOLEAN', true),
        'maxDocs'        => 500,
    ],

    // Mantener por compatibilidad con versiones antiguas de Scout
    'engine' => [
        'tntsearch' => [
            'driver' => 'tntsearch',
        ],
    ],
];
