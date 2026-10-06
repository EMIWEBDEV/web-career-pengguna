<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://' . env('PAPERTRAIL_URL') . ':' . env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],


        'ticket_log' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ticket_log.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'ubahApproval' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ubahApproval.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'CreateUserLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/CreateUserLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'FlagCheckinout' => [
            'driver' => 'daily',
            'path' => storage_path('logs/FlagCheckinout.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'waAddTeam' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waAddTeam.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'addTeam' => [
            'driver' => 'daily',
            'path' => storage_path('logs/addTeam.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'WaCreateUserLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/WaCreateUserLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'CreateUserLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/CreateUserLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'KaryawanTeamLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/KaryawanTeamLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'loginLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/loginLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'waUlangLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waUlangLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'BatasCutiAkhirTahunLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/BatasCutiAkhirTahunLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'cutiHangusHarian' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cutiHangusHarian.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'expiredIzin' => [
            'driver' => 'daily',
            'path' => storage_path('logs/expiredIzin.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'HakCutiTambahan' => [
            'driver' => 'daily',
            'path' => storage_path('logs/HakCutiTambahan.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'kuotaAwalCuti' => [
            'driver' => 'daily',
            'path' => storage_path('logs/kuotaAwalCuti.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'tambahKuotaCutiBulanan' => [
            'driver' => 'daily',
            'path' => storage_path('logs/tambahKuotaCutiBulanan.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'waUDashLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waUDashLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'uDashLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/uDashLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waIzinLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waIzinLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'izinLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/izinLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waAbsensiLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waAbsensiLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'absensiLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/absensiLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waShiftLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waShiftLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'shiftLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/shiftLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waLemburLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waLemburLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'lemburLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lemburLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waConfirmIzin' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waConfirmIzin.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waIzinPage' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waIzinPage.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waCommandExpiredIzin' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waCommandExpiredIzin.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'CommandExpiredIzin' => [
            'driver' => 'daily',
            'path' => storage_path('logs/CommandExpiredIzin.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waCommandWaUlang' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waCommandWaUlang.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'CommandBeriNotifUlang' => [
            'driver' => 'daily',
            'path' => storage_path('logs/CommandBeriNotifUlang.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'whatsapp_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/whatsapp_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'absensi_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/absensi_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'Wa_izin_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/Wa_izin_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'izin_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/izin_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'Wa_absensi_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/Wa_absensi_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'shift_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/shift_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'wa_shift_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/Wa_shift_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'lembur_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lembur_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'wa_lembur_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/Wa_lembur_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'openaiError' => [
            'driver' => 'daily',
            'path' => storage_path('logs/openaiError.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'review_log' => [
            'driver' => 'daily',
            'path' => storage_path('logs/review_log.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => LOG_USER,
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],

];
