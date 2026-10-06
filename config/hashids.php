<?php

/*
|--------------------------------------------------------------------------
| Hashids — id ter-hash di URL & payload layar
|--------------------------------------------------------------------------
| WAJIB SAMA dengan project admin: potret portal (Pub_Portal_Lamaran) yang
| dibangun zona dalam membawa id tahap/aktivitas ter-hash, dan endpoint portal
| di sini mengurainya dengan pengaturan yang sama.
*/

return [

    'default' => 'main',

    'connections' => [
        'main' => [
            'salt' => '',
            'length' => 0,
        ],
    ],

];
