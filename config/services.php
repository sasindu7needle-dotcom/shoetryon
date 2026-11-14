<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'deepar' => [
        'license_key' => env('DEEPAR_LICENSE_KEY'),
        // Builds a DeepAR effect from each GLB Tripo makes
        // (scripts/build-deepar-effect.py --auto). Needs Python 3 with Pillow.
        'effect_builder' => [
            'python' => env('DEEPAR_EFFECT_PYTHON', PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'),
            'template' => 'effects/shoes/street-runner.deepar',
            'timeout' => (int) env('DEEPAR_EFFECT_TIMEOUT', 300),
        ],
    ],

    /*
    | The n8n "ShopAR: admin shoe pipeline" workflow.
    |
    | The admin's Add new shoe page posts the photos to its form trigger
    | (production URL, ending in /form/shopar-new-shoe) with the form's Basic
    | Auth login. Re-optimize posts the admin's comment to its webhook
    | (production URL, ending in /webhook/shopar-fit-reoptimize).
    |
    | `shopar_key` is sent both ways in the X-ShopAR-Key header: on the
    | re-optimize request, and by n8n when it calls back with the GLB or the
    | new fit (/api/n8n/...). Use one long random value in n8n's Header Auth
    | credential and here.
    */
    'n8n' => [
        'new_shoe_form_url' => env('N8N_NEW_SHOE_FORM_URL'),
        'form_username' => env('N8N_FORM_USERNAME'),
        'form_password' => env('N8N_FORM_PASSWORD'),
        'reoptimize_url' => env('N8N_REOPTIMIZE_URL'),
        'shopar_key' => env('N8N_SHOPAR_KEY'),
        'timeout' => (int) env('N8N_TIMEOUT', 120),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
