<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-verifikasi email kandidat (khusus development)
    |--------------------------------------------------------------------------
    |
    | Walaupun flag ini aktif, AuthController tetap membatasinya hanya untuk
    | APP_ENV=local atau APP_ENV=testing. Production selalu wajib melakukan
    | verifikasi email melalui tautan yang dikirimkan.
    |
    */
    'auto_verify_email' => env('CAREER_AUTO_VERIFY_EMAIL', false),

];
