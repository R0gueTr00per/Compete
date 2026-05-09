<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inactivity Session Timeout
    |--------------------------------------------------------------------------
    | Minutes of browser inactivity before the user is automatically logged out.
    | Set INACTIVITY_TIMEOUT_MINUTES=0 in .env to disable.
    | Recommended range: 5–60 minutes.
    */
    'inactivity_timeout' => (int) env('INACTIVITY_TIMEOUT_MINUTES', 30),
];
