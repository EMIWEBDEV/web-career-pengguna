<?php

namespace App\Support\Career\Shell;

/**
 * WEB CAREERS — SIAPA yang sedang membuka shell portal.
 *
 * Project pengguna hanya mengenal akun KANDIDAT: tabel akun publik tidak
 * pernah berisi akun admin (panel admin hidup di zona dalam).
 */
class IdentitasShell
{
    /** Nama peran yang dibaca manusia — footer sidebar & kartu topbar. */
    public const LABEL_PERAN = [
        'KANDIDAT' => 'Kandidat',
    ];

    /** Peran pengguna saat ini (dari sesi). */
    public static function peran(): string
    {
        return session('career_auth.role') ?: 'KANDIDAT';
    }

    /** Label peran; peran tak dikenal dipulangkan apa adanya, bukan ditebak. */
    public static function labelPeran(?string $peran = null): string
    {
        $peran = strtoupper((string) ($peran ?: self::peran()));

        return self::LABEL_PERAN[$peran] ?? ucfirst(strtolower(str_replace('_', ' ', $peran)));
    }

    /** Beranda: portal kandidat bila sudah masuk, halaman utama bila belum. */
    public static function beranda(): string
    {
        return session('career_auth.id') ? '/kandidat/portal' : '/';
    }

    /** Identitas pengguna dari sesi login (career_auth). */
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
            // Dipakai badge topbar (kontrak shell bersama) — isinya kode peran.
            'nik' => $role,
            'department' => self::labelPeran($role),
        ];
    }
}
