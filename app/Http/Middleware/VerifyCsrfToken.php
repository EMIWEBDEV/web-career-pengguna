<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/handle-task',
        'scheduler-T4sk-RuNn3r-sEcr3t',
        // Backfill cap KPI di-hit dari Postman (tanpa session/XSRF cookie); diamankan
        // secret token di controller. Dikecualikan dari CSRF agar tidak 419.
        'api/v1/kpi-enforcement/backfill-cap',
        // Webhook hasil tes dari CAT/HCLearn (server-to-server, tanpa session/CSRF);
        // diamankan header X-WC-Secret di controller.
        'api/v1/webhook/*',
        // [feat/feedback] Admin actions (reassign/resend) dilindungi career.auth,
        // tidak perlu CSRF tambahan.
        'api/v1/karir/feedback/reassign',
        'api/v1/karir/feedback/resend',
        // [feat/feedback] Master feedback CRUD — semua mutation endpoint
        // dilindungi career.auth + career.role:ADMIN,SUPERADMIN.
        'api/v1/karir/master-feedback/*',
        // [feat/landing-page] Master info divisi — dilindungi career.auth +
        // career.role + career.permission:masterInfoDivisiPage.
        'api/v1/master-info-divisi/*',
        // Konfirmasi kehadiran kandidat — POST bertanda tangan (middleware
        // `signed`): tanda tangan tautan adalah rahasianya; peramban dalam
        // aplikasi surel kerap tanpa cookie sesi sehingga CSRF berujung 419.
        'karir/konfirmasi/*/*/jawab',
        'karir/konfirmasi/*/*/cabut',
        'karir/konfirmasi/*/*/dibuka',
    ];
}
