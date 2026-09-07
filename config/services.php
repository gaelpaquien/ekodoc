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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LibreOffice (Office document preview conversion)
    |--------------------------------------------------------------------------
    |
    | Binary invoked by ConvertDocumentToPreviewAction to convert imported
    | Word/Excel files to PDF for preview (`soffice --headless --convert-to
    | pdf`). Defaults to "soffice" resolved from the system PATH; override
    | with LIBREOFFICE_BINARY if LibreOffice isn't on PATH locally.
    |
    */

    'libreoffice' => [
        'binary' => env('LIBREOFFICE_BINARY', 'soffice'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Browsershot (created-document PDF export)
    |--------------------------------------------------------------------------
    |
    | Path to the Chrome/Chromium executable ExportDocumentToPdfAction hands
    | to Browsershot (spec-2-4, AD-11) so it drives the system browser
    | already installed locally instead of letting the bundled puppeteer
    | package download its own Chromium. Left blank, Browsershot falls back
    | to puppeteer's own resolution.
    |
    */

    'browsershot' => [
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
    ],

];
