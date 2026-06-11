<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fogot' => [
        'base_url' => env('FOGOT_API_BASE_URL', 'https://py.hexugo.com/api/product'),
        'timeout' => (int) env('FOGOT_API_TIMEOUT', 600),
        'connect_timeout' => (int) env('FOGOT_API_CONNECT_TIMEOUT', 30),
        'retry_times' => (int) env('FOGOT_API_RETRY_TIMES', 0),
        'retry_sleep_ms' => (int) env('FOGOT_API_RETRY_SLEEP_MS', 0),
        'retry_statuses' => array_values(array_filter(array_map(
            'intval',
            explode(',', env('FOGOT_API_RETRY_STATUSES', '420,429,500,502,503,504')),
        ))),
        'image_request_delay_ms' => (int) env('FOGOT_IMAGE_REQUEST_DELAY_MS', 3000),
        'remote_image_download_delay_ms' => (int) env('FOGOT_REMOTE_IMAGE_DOWNLOAD_DELAY_MS', 2000),
        'remote_image_cache_enabled' => filter_var(env('FOGOT_REMOTE_IMAGE_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'send_image_base64' => filter_var(env('FOGOT_SEND_IMAGE_BASE64', true), FILTER_VALIDATE_BOOL),
        'remote_image_cache_directory' => env('FOGOT_REMOTE_IMAGE_CACHE_DIRECTORY', 'fogot-image-cache'),
        'category_dict_text' => env('FOGOT_CATEGORY_DICT_TEXT', ''),
    ],

    'onebound' => [
        'key' => env('ONEBOUND_API_KEY', 't7100'),
        'secret' => env('ONEBOUND_API_SECRET', '7100fb80'),
        'shop_import_max_pages' => (int) env('ONEBOUND_SHOP_IMPORT_MAX_PAGES', 0),
    ],

];
