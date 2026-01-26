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

  'ses' => [
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
  ],

  'resend' => [
    'key' => env('RESEND_KEY'),
  ],

  'slack' => [
    'webhook_url' => env('SLACK_WEBHOOK_URL'),
    /*  'notifications' => [
      'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
      'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
    ], */
  ],
  'ipapi' => [
    'base_url' => env('IPAPI_BASE_URL', 'https://ipapi.co'),
    'token' => env('IPAPI_TOKEN'),
  ],
  'natural_intelligence' => [
    'login_url' => env('NATURAL_INTELLIGENCE_LOGIN_URL', 'https://partner-login.naturalint.com/token'),
    'report_url' => env('NATURAL_INTELLIGENCE_REPORT_URL', 'https://partner-api.naturalint.com/publisherhubservice/get-report'),
    'username' => env('NATURAL_INTELLIGENCE_API_USER', 'carla@datagrande.io'),
    'password' => env('NATURAL_INTELLIGENCE_API_KEY', 'qunttj8dUYmYaL9'),
  ],
  'facebook' => [
    'app_secret' => env('FACEBOOK_APP_SECRET'),
    'verify_token' => env('FACEBOOK_VERIFY_TOKEN'),
  ],
];
