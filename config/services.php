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
        'token' => env('POSTMARK_TOKEN'),
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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Billing
    |--------------------------------------------------------------------------
    |
    | Maps each tenant plan key (Tenant::PLAN_BASIC / PLAN_STANDARD /
    | PLAN_CUSTOM) to a Stripe Price ID. Set per-environment in .env so
    | local/test/prod can each point at their own Stripe products. The
    | "custom" plan has no Stripe price because it's manual sales contact.
    |
    | Trial days come from the Tenant::PLAN_DEFAULTS or fall back to 14.
    | Webhook secret protects /stripe/webhook from forgery — get it from
    | Stripe CLI (`stripe listen`) for local, or the dashboard webhook
    | endpoint config in production.
    */
    'stripe' => [
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
        'prices' => [
            'basic' => env('STRIPE_PRICE_BASIC'),
            'standard' => env('STRIPE_PRICE_STANDARD'),
            // 'custom' — no Stripe price; sales-led contract.
        ],
        'trial_days' => (int) env('STRIPE_TRIAL_DAYS', 14),
    ],

];
