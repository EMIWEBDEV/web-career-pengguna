<?php

namespace App\Support\Career\Shell;

/**
 * WEB CAREERS — SIAPA yang sedang membuka shell.
 *
 * Dipisah dari CareerShell supaya urusan peran/identitas punya berkasnya
 * sendiri: kalau aturan peran berubah, yang tersentuh hanya berkas ini.
 */
class IdentitasShell
{
    /** Peran yang boleh membuka panel admin. */
    public const PERAN_ADMIN = ['ADMIN', 'SUPERADMIN'];

    /**
     * Nama peran yang dibaca manusia — dipakai footer sidebar & kartu topbar.
     *
     * Ditaruh di sini, bukan di masing-masing layar, karena label yang sama
     * dipakai tiga tempat; menulisnya ulang per layar membuat satu tempat
     * ketinggalan setiap kali ada peran baru.
     */
    public const LABEL_PERAN = [
        'SUPERADMIN' => 'Superadmin',
        'ADMIN' => 'Administrator',
        'KANDIDAT' => 'Kandidat',
    ];

    /** Peran pengguna saat ini (dari sesi). */
    public static function peran(): string
    {
        return session('career_auth.role') ?: 'KANDIDAT';
    }

    public static function adalahAdmin(): bool
    {
        return in_array(self::peran(), self::PERAN_ADMIN, true);
    }

    /** Label peran; peran tak dikenal dipulangkan apa adanya, bukan ditebak. */
    public static function labelPeran(?string $peran = null): string
    {
        $peran = strtoupper((string) ($peran ?: self::peran()));

        return self::LABEL_PERAN[$peran] ?? ucfirst(strtolower(str_replace('_', ' ', $peran)));
    }

    /** Beranda sesuai peran: admin ke panel, kandidat ke portalnya sendiri. */
    public static function beranda(): string
    {
        return self::adalahAdmin() ? '/karir' : '/kandidat/portal';
    }

    /**
     * Identitas pengguna dari sesi login (career_auth).
     *
     * CATATAN PERBAIKAN: versi lama memakai 'ADMIN' sebagai peran cadangan dan
     * memberi label 'Administrator' kepada SIAPA PUN yang bukan SUPERADMIN —
     * termasuk kandidat. Akibatnya footer sidebar portal kandidat menuliskan
     * "Administrator" di bawah nama pelamar. Cadangannya sekarang disamakan
     * dengan peran(): 'KANDIDAT', yaitu peran dengan wewenang paling kecil.
     */
    public static function pengguna(): array
    {
        $auth = session('career_auth');
        $role = strtoupper((string) ($auth['role'] ?? 'KANDIDAT'));

        return [
            'id' => $auth['id'] ?? null,
            'name' => $auth['nama'] ?? 'Pengguna',
            'username' => $auth['email'] ?? '-',
            'email' => $auth['email'] ?? null,
            'role' => $role,
            // Dipakai badge topbar. Bukan nomor KTP — namanya warisan kontrak
            // shell bersama; yang ditampilkan memang kode peran.
            'nik' => $role,
            'kode_karyawan' => $auth['kode_karyawan'] ?? '-',
            'department' => self::labelPeran($role),
        ];
    }
}
