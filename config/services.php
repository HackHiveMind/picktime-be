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
        'from' => env('RESEND_FROM_ADDRESS'),
        'admin_to' => env('RESEND_ADMIN_EMAIL'),
        'logo_url' => env('RESEND_LOGO_URL', 'https://pictime-ihub-booking-fe.vercel.app/ihub-logo.png'),
    ],

    'booking_email' => [
        'driver' => env('BOOKING_EMAIL_DRIVER', 'mailjet'),
        'admin_to' => env('BOOKING_ADMIN_EMAIL', env('RESEND_ADMIN_EMAIL')),
        'logo_url' => env('BOOKING_EMAIL_LOGO_URL', env('RESEND_LOGO_URL', 'https://pictime-ihub-booking-fe.vercel.app/ihub-logo.png')),
    ],

    'mailjet' => [
        'key' => env('MAILJET_API_KEY', env('MAIL_USERNAME')),
        'secret' => env('MAILJET_SECRET_KEY', env('MAIL_PASSWORD')),
        'from_address' => env('MAILJET_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('MAILJET_FROM_NAME', env('MAIL_FROM_NAME', 'iHUB Booking')),
    ],

    'sendgrid' => [
        'key' => env('SENDGRID_API_KEY'),
        'from_address' => env('SENDGRID_FROM_ADDRESS', env('MAILJET_FROM_ADDRESS', env('MAIL_FROM_ADDRESS'))),
        'from_name' => env('SENDGRID_FROM_NAME', env('MAILJET_FROM_NAME', env('MAIL_FROM_NAME', 'iHUB Booking'))),
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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/admin/google/callback'),
    ],

];
