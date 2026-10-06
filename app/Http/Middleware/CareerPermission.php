<?php

namespace App\Http\Middleware;

use App\Support\Career\AksesService;
use App\Support\CareerShell;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * WEB CAREERS — gerbang hak akses per halaman & aksi (pola cat-evo: CheckPermission).
 *
 * Pakai: ->middleware('career.permission:masterAlurPage,VIEW')
 *
 * Dipasang SESUDAH career.auth. Paket akses dibaca dari sesi 'career_akses';
 * bila sesi belum berisi (mis. sesi lama sebelum fitur ini ada, atau kandidat
 * yang baru saja di-provision), paket dibangun ulang dari DB lalu disimpan —
 * jadi pengguna tidak perlu login ulang.
 */
class CareerPermission
{
    public function handle(Request $request, Closure $next, string $jenisPage, string $aksi): Response
    {
        $auth = session('career_auth');

        if (! $auth || empty($auth['id'])) {
            return $this->tolak($request, 'Silakan masuk terlebih dahulu.', 401);
        }

        // Segarkan paket akses bila sesi belum memilikinya.
        if (! session()->has('career_akses')) {
            session(['career_akses' => AksesService::paket(
                (int) $auth['id'],
                (string) ($auth['role'] ?? 'KANDIDAT'),
                $auth['klasifikasi'] ?? null
            )]);
        }

        // ── MODE PEMELIHARAAN (N_WEB_CAREERS_Menu.Flag_Maintenance = 'Y') ──
        // Ditutup untuk semua orang KECUALI SUPERADMIN — jalan keluar supaya
        // admin tetap bisa mematikan flag-nya kembali walau menu pengaturnya
        // ikut ditandai maintenance.
        $maint = AksesService::menuMaintenance($jenisPage);
        if ($maint && ($auth['role'] ?? null) !== 'SUPERADMIN') {
            return $this->pemeliharaan($request, $maint);
        }

        $dimiliki = Arr::get(session('career_akses'), "permissions.{$jenisPage}", []);

        if (! in_array(strtoupper($aksi), $dimiliki, true)) {
            return $this->tolak(
                $request,
                'Anda tidak memiliki hak akses untuk tindakan ini.',
                403
            );
        }

        return $next($request);
    }

    /**
     * Halaman pemeliharaan. Dua varian — inilah pemisah "standalone vs tidak":
     *  - Menu ADMIN  → dirender DI DALAM shell (navbar + sidebar tetap ada),
     *    sehingga admin bisa langsung pindah ke menu lain.
     *  - Menu KANDIDAT / publik → halaman STANDALONE penuh layar.
     */
    private function pemeliharaan(Request $request, object $menu): Response
    {
        $pesan = 'Menu "' . $menu->Nama_Menu . '" sedang dalam pemeliharaan. Silakan kembali beberapa saat lagi.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'status' => 503, 'message' => $pesan], 503);
        }

        $props = [
            'status' => 503,
            'message' => $pesan,
            'maintenanceMenu' => $menu->Nama_Menu,
            'tanggal' => now()->translatedFormat('l, d F Y'),
            'homeUrl' => CareerRole::beranda(session('career_auth.role')),
        ];

        if (($menu->Untuk_Role ?? 'ADMIN') === 'ADMIN') {
            // Dalam shell: bawa props layout supaya sidebar & topbar ikut tampil.
            return Inertia::render('ErrorShell', CareerShell::props($request->path(), 'Mode Pemeliharaan', $props))
                ->toResponse($request)->setStatusCode(503);
        }

        return Inertia::render('Error', $props)->toResponse($request)->setStatusCode(503);
    }

    private function tolak(Request $request, string $pesan, int $status): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'status' => $status, 'message' => $pesan], $status);
        }

        if ($status === 401) {
            return redirect('/login')->with('pesan', $pesan);
        }

        abort(403, 'ANDA TIDAK MEMILIKI HAK AKSES.');
    }
}
