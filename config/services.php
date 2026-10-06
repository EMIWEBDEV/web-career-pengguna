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

    'project_config' => [
        'app_name' => env('APP_NAME', 'Laravel'),
        'app_env' => env('APP_ENV', 'production'),
        'app_version' => env('APP_VERSION', '2.13.0'),
        'app_debug' => env('APP_DEBUG', false),
        'maintenance_mode' => env('MAINTENANCE_MODE', false),
        'DB_TEST_BEARER_TOKEN' => env('DB_TEST_BEARER_TOKEN', '914add258161ad4ac0819db2e1918055'),
        'OPS_API_BEARER_TOKEN' => env(
            'OPS_API_BEARER_TOKEN',
            '7cfbb1e7769517c99951b73cf105bbab7790db07e1e876bc1f137b924583749d',
        ),
        'SCHEDULER_BEARER_TOKEN' => env(
            'SCHEDULER_BEARER_TOKEN',
            'bdc43f93e7d057cd1dca14d5a6259eb45c5062cc4cf96641fee05b688b3d85d3',
        ),
        'DUMMY_DATE_BEARER' => env('DUMMY_DATE_BEGIN', '2024-01-01'),
        'log-viewer' => [
            'admin_key' => env(
                'LOG_VIEWER_ADMIN_KEY',
                '0df4cdef0107d3bbfdc8e79680e0fc14e0dfde0b1cf178eaa8a9a7a1b5a419224f0c6b0768f7ab999c9bb524d5ebd8496beedac3a824415a0e02cc444fab43f9',
            ),
        ],
        'gcp' => [
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID', 'project-6a30e7a0-bed0-4614-a28'),
            'location' => env('GOOGLE_CLOUD_LOCATION', 'asia-southeast1'),
            'key_path' => env('GOOGLE_CLOUD_KEY_FILE_PATH', 'C:\gKey\project-6a30e7a0-bed0-4614-a28-7f39d1d2da1c.json'),
            'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET', 'hcisxkpi'),
            'queue_pdf' => env('GOOGLE_CLOUD_TASK_QUEUE_PDF', 'lms-pdf-queue'),
            // Base URL yang dipanggil balik oleh Cloud Tasks (harus URL publik aplikasi
            // ini di Cloud Run). Dipisah dari APP_URL agar tidak ikut berubah saat
            // APP_URL dipakai untuk keperluan lain. Fallback ke APP_URL bila kosong.
            'webhook_base_url' => env('CLOUD_TASK_WEBHOOK_BASE_URL', env('APP_URL')),
        ],
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'hcis' => [
        'cutoff_payroll_date' => [
            'start_day' => (int) env('HCIS_CUTOFF_PAYROLL_START_DAY', 16),
            'end_day' => (int) env('HCIS_CUTOFF_PAYROLL_END_DAY', 15),
        ],
        // General
        'valid_origins' => explode(',', env('HCIS_VALID_ORIGINS', 'https://hcis.evonusabersaudara.co.id')),
        // Izin
        'batas_approve_izin' => (int) env('HCIS_APPROVE_IZIN_LIMIT', 2),

        // Lembur
        // 'overtime_level_list' => explode(',', env('HCIS_OVERTIME_LEVEL_LIST', '1,2,21')),
        'overtime_level_list' => array_map('intval', explode(',', env('HCIS_OVERTIME_LEVEL_LIST', '1,2,21'))),

        // Cuti
        'batas_approve_cuti' => env('HCIS_APPROVE_CUTI_LIMIT') ? (int) env('HCIS_APPROVE_CUTI_LIMIT') : null,

        'attendance_dirty' => [
            'permanent_horizon_days' => (int) env('HCIS_ATTENDANCE_PERMANENT_DIRTY_HORIZON_DAYS', 90),
            'rolling_horizon_days' => (int) env('HCIS_ATTENDANCE_DIRTY_ROLLING_HORIZON_DAYS', 7),
        ],

        //shift
        'shift_request' => [
            // Pergantian shift
            'backdate_max_days' => (int) env('HCIS_SHIFT_REQUEST_BACKDATE_LIMIT', 0),
            'future_max_days' => (int) env('HCIS_SHIFT_REQUEST_FUTURE_LIMIT', 33),
            'backdate_needs_approval' => filter_var(
                env('HCIS_SHIFT_REQUEST_BACKDATE_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'same_day_needs_approval' => filter_var(
                env('HCIS_SHIFT_REQUEST_SAMEDAY_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'future_needs_approval' => filter_var(
                env('HCIS_SHIFT_REQUEST_FUTURE_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ],

        'swap_shift_request' => [
            // Pertukaran hari kerja
            'backdate_max_days' => (int) env('HCIS_SWAP_SHIFT_REQUEST_BACKDATE_LIMIT', 14),
            'future_max_days' => (int) env('HCIS_SWAP_SHIFT_REQUEST_FUTURE_LIMIT', 33),
            'backdate_needs_approval' => filter_var(
                env('HCIS_SWAP_SHIFT_REQUEST_BACKDATE_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'same_day_needs_approval' => filter_var(
                env('HCIS_SWAP_SHIFT_REQUEST_SAMEDAY_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'future_needs_approval' => filter_var(
                env('HCIS_SWAP_SHIFT_REQUEST_FUTURE_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ],

        'leader_shift_request' => [
            'target_hours_per_week' => (float) env('HCIS_LEADER_SHIFT_REQUEST_TARGET_HOURS_PER_WEEK', 0),
            'backdate_max_days' => (int) env('HCIS_LEADER_SHIFT_REQUEST_BACKDATE_LIMIT', 7),
            'future_max_days' => (int) env('HCIS_LEADER_SHIFT_REQUEST_FUTURE_LIMIT', 33),
            'same_days' => filter_var(env('HCIS_LEADER_SHIFT_REQUEST_SAME_DAYS', true), FILTER_VALIDATE_BOOLEAN),
            'backdate_needs_approval' => filter_var(
                env('HCIS_LEADER_SHIFT_REQUEST_BACKDATE_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'same_day_needs_approval' => filter_var(
                env('HCIS_LEADER_SHIFT_REQUEST_SAMEDAY_APPROVE', false),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'future_needs_approval' => filter_var(
                env('HCIS_LEADER_SHIFT_REQUEST_FUTURE_APPROVE', false),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ],

        'lembur_request' => [
            'batas_approve_lembur' => (int) env('HCIS_LEMBUR_REQUEST_APPROVE_LIMIT', 2),
            'minimal_durasi_lembur' => (float) env('HCIS_LEMBUR_REQUEST_MINIMAL_DURATION_LIMIT', 1),
            'batas_durasi_lembur' => (float) env('HCIS_LEMBUR_REQUEST_DURATION_LIMIT', 18),
            'batas_jam_request_lembur' => (float) env('HCIS_LEMBUR_REQUEST_HOUR_LIMIT', 4),
            'batas_kedekatan_akhir_lembur_dengan_jam_kerja' => (float) env(
                'HCIS_LEMBUR_REQUEST_KENDEKATAN_JAM_KERJA_LIMIT',
                0.5,
            ),

            'batas_lembur_awal_kerja_dari_jam_kerja' => (float) env('HCIS_LEMBUR_REQUEST_LEMBUR_AWAL_KERJA_LIMIT', 5),

            'batas_durasi_lembur_libur' => (float) env('HCIS_LEMBUR_REQUEST_DURATION_LIMIT_LIBUR', 18),

            'lembur_hari_libur_needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_LEMBUR_LIBUR_APPROVE', true),
            'lembur_awal_kerja_needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_LEMBUR_AWAL_KERJA_APPROVE', true),
            'lembur_hari_kerja_needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_LEMBUR_HARI_KERJA_APPROVE', true),

            'backdate' => [
                'enable' => (bool) env('HCIS_LEMBUR_REQUEST_BACKDATE_ENABLE', true),
                'max_days' => (int) env('HCIS_LEMBUR_REQUEST_BACKDATE_LIMIT', 7),
                'needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_BACKDATE_APPROVE', true),
            ],
            'future' => [
                'enable' => (bool) env('HCIS_LEMBUR_REQUEST_FUTURE_ENABLE', true),
                'max_days' => (int) env('HCIS_LEMBUR_REQUEST_FUTURE_LIMIT', 33),
                'needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_FUTURE_APPROVE', true),
            ],
            'same_day' => [
                'enable' => (bool) env('HCIS_LEMBUR_REQUEST_SAMEDAY_ENABLE', false),
                'needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_SAMEDAY_APPROVE', true),
            ],
        ],

        'konversi_lembur' => [
            'enable' => (bool) env('HCIS_LEMBUR_REQUEST_CONVERSION_ENABLE', true),
            'max_days' => (int) env('HCIS_LEMBUR_REQUEST_CONVERSION_MAX_DAYS', 30),
            'day_conversion_needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_DAY_CONVERSION_APPROVE', true),
            'material_conversion_needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_MATERIAL_CONVERSION_APPROVE', true),
            'needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_CONVERSION_APPROVE', true),
            'material_conversion_code' => env('HCIS_LEMBUR_REQUEST_MATERIAL_CONVERSION_CODE', 'MATERIAL_CONVERSION'),
            'day_conversion_code' => env('HCIS_LEMBUR_REQUEST_DAY_CONVERSION_CODE', 'DAY_CONVERSION'),
            'to_cuti_conversion_code' => env('HCIS_LEMBUR_REQUEST_TO_CUTI_CONVERSION_CODE', 'KONVERSI_TO_CUTI'),
            'conversion_level_whitelist' => array_map(
                'intval',
                explode(',', env('HCIS_LEMBUR_REQUEST_CONVERSION_LEVEL_WHITELIST', '3,4,5,6,7,8,9,10,11')),
            ),
            'conversion_level_material_whitelist' => array_map(
                'intval',
                explode(',', env('HCIS_LEMBUR_REQUEST_CONVERSION_LEVEL_MATERIAL_WHITELIST', '3,4,5')),
            ),
            'conversion_level_day_off_whitelist' => array_map(
                'intval',
                explode(',', env('HCIS_LEMBUR_REQUEST_CONVERSION_LEVEL_DAY_OFF_WHITELIST', '3,4,5,6,7,8,9,10,11')),
            ),
        ],
    ],

    'kpi' => [
        'max_achievement_score' => (int) env('KPI_MAX_ACHIEVEMENT_SCORE', 125),
        'min_achievement_score' => (int) env('KPI_MIN_ACHIEVEMENT_SCORE', 50),
        // Secret token untuk endpoint backfill cap (di-hit dari Postman). Bila kosong,
        // endpoint menolak semua request (fail-closed).
        'backfill_token' => env(
            'KPI_BACKFILL_TOKEN',
            '5db5e1fc0cf76fb3e8d7f9d3156d02845f1b67fbfe6daf573292b420ee609eb0',
        ),
    ],

    'visitor' => [
        'tamu' => [
            'enabled' => (bool) env('VISITOR_TAMU_ENABLED', true),
            'feature_code' => env('VISITOR_TAMU_FEATURE_CODE', 'TAMU'),
            'needs_approval' => (bool) env('VISITOR_TAMU_NEEDS_APPROVAL', true),
            'approver_limit' => (int) env('VISITOR_TAMU_APPROVER_LIMIT', env('VISITOR_APPROVE_TAMU_LIMIT', 2)),
            'approval_limit' => (int) env('VISITOR_TAMU_APPROVER_LIMIT', env('VISITOR_APPROVE_TAMU_LIMIT', 2)),
            'approval_expire_limit' => (int) env('VISITOR_TAMU_APPROVAL_EXPIRE_LIMIT', 2),
            'allow_submit_without_flow' => (bool) env('VISITOR_TAMU_ALLOW_WITHOUT_FLOW', false),
            'approval_label' => env('VISITOR_TAMU_APPROVAL_LABEL', 'Approval Tamu'),
            'check_in_requires_approval' => (bool) env('VISITOR_TAMU_CHECKIN_REQUIRES_APPROVAL', true),
        ],
        'permit' => [
            'enabled' => (bool) env('VISITOR_PERMIT_ENABLED', true),
            'feature_code' => env('VISITOR_PERMIT_FEATURE_CODE', 'PERMIT'),
            'needs_approval' => (bool) env('VISITOR_PERMIT_NEEDS_APPROVAL', true),
            'approver_limit' => (int) env('VISITOR_PERMIT_APPROVER_LIMIT', env('VISITOR_APPROVE_PERMIT_LIMIT', 2)),
            'approval_limit' => (int) env('VISITOR_PERMIT_APPROVER_LIMIT', env('VISITOR_APPROVE_PERMIT_LIMIT', 2)),
            'approval_expire_limit' => (int) env('VISITOR_PERMIT_APPROVAL_EXPIRE_LIMIT', 2),
            'allow_submit_without_flow' => (bool) env('VISITOR_PERMIT_ALLOW_WITHOUT_FLOW', false),
            'approval_label' => env('VISITOR_PERMIT_APPROVAL_LABEL', 'Approval Permit'),
            'checklist_required' => (bool) env('VISITOR_PERMIT_CHECKLIST_REQUIRED', true),
        ],
    ],

    'lms' => [
        'bunny' => [
            'library_id' => env('BUNNY_LIBRARY_ID', '603117'),
            'api_key' => env('BUNNY_API_KEY', 'b65dfc93-938d-4a39-b6e8a364e735-825f-4d1d'),
            'endpoint' => env('BUNNY_ENDPOINT', 'https://video.bunnycdn.com'),
            'cdn_host' => env('BUNNY_CDN_HOST', 'vz-88d4dd8a-73d.b-cdn.net'),
            'token_key' => env('BUNNY_TOKEN_KEY', 'cf72a875-cc73-4512-8b7b-3900b71f2ea8'),
            'token_mode' => env('BUNNY_TOKEN_MODE', 'basic'),
            'token_ttl_seconds' => (int) env('BUNNY_TOKEN_TTL_SECONDS', 3600),
            'collection_id' => env('BUNNY_COLLECTION_ID', 'cc654650-1850-4a3e-9ef6-3262bd6109b4'),
        ],
        'gcs' => [
            'disk' => env('LMS_GCS_DISK', 'gcs'),
        ],
        // Batas jumlah file base JPEG yang di-cache di local disk (tmpfs di Cloud Run).
        // ~220KB/file. 200 ≈ 44MB (aman 2Gi). Naikkan bila RAM lebih besar.
        'pdf_cache_max_files' => (int) env('LMS_PDF_CACHE_MAX_FILES', 200),
    ],

    // Cloudflare Turnstile (CAPTCHA halaman login Web Careers).
    'cloudflare' => [
        'turnstile_sitekey' => env('CLOUDFLARE_TURNSTILE_SITEKEY'),
        'turnstile_secret' => env('CLOUDFLARE_TURNSTILE_SECRET'),
    ],

    // DEVELOPMENT CONFIGS
    'ridho' => require __DIR__ . '/dev/ridhoDev.php',
    'frans' => require __DIR__ . '/dev/fransDev.php',
];
