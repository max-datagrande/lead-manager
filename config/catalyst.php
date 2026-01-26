<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Catalyst Service Status
    |--------------------------------------------------------------------------
    |
    | This option controls whether the Catalyst SDK is globally active.
    | You can use this as a "kill switch" to disable the script injection
    | without deploying new code. It's recommended to tie this to an
    | environment variable for quick toggling.
    |
    */
    'active' => env('CATALYST_ACTIVE', true),

    /*
    |--------------------------------------------------------------------------
    | Catalyst API URL
    |--------------------------------------------------------------------------
    |
    | This is the base URL for the API endpoint where the Catalyst SDK will
    | send its tracking data. This should be a full URL. It's strongly
    | recommended to set this via an environment variable.
    |
    */
    'api_url' => env('CATALYST_API_URL'),
];
