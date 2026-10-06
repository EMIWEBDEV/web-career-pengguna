<?php

namespace App\Support\Career;

use App\Support\Career\Shell\IdentitasShell;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — DATA HALAMAN /profil kandidat.
 *
 * Dibaca ULANG dari tabel akun setiap halaman dibuka, bukan disalin dari sesi:
 * sesi hanya membawa kolom untuk gerbang akses, sedangkan nomor HP, status
 * verifikasi, tanggal daftar, dan login terakhir adalah isi halaman ini.
 */
class ProfilPengguna
{
    /** Status lamaran yang dihitung sebagai "selesai" pada ringkasan kandidat. */
    private const STATUS_SELESAI = ['LULUS', 'GUGUR', 'MUNDUR'];

    /**
     * Profil pengguna yang sedang login, atau null bila belum login —
     * layar profil menggambar ajakan masuk untuk null, bukan kartu kosong.
     */
    public static function payload(): ?array
    {
        $userId = (int) session('career_auth.id');
        if ($userId <= 0) {
            return null;
        }

        $u = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $userId)->first();
        if (! $u) {
            // Sesi menunjuk akun yang sudah tidak ada (dihapus admin saat
            // pemiliknya masih login). Diperlakukan seperti belum masuk.
            return null;
        }

        $role = strtoupper((string) ($u->Role ?: 'KANDIDAT'));

        return [
            // ── IDENTITAS ────────────────────────────────────────────────
            'nama' => $u->Nama,
            'email' => $u->Email,
            'no_hp' => $u->No_Hp,
            'nik' => self::samarkanNik($u->NIK),
            'role' => $role,
            'roleLabel' => IdentitasShell::labelPeran($role),

            // ── MASA BERLAKU AKUN ────────────────────────────────────────
            'status' => $u->Status,
            'klasifikasi' => $u->Klasifikasi,
            'klasifikasiLabel' => self::labelKlasifikasi($u->Klasifikasi),
            'mulai_berlaku' => $u->Mulai_Berlaku,
            'valid_until' => $u->Valid_Until,

            // ── KEAMANAN & JEJAK ─────────────────────────────────────────
            'emailVerified' => ($u->Flag_Email_Verified ?? 'T') === 'Y',
            'emailVerifiedAt' => $u->Email_Verified_At,
            'last_login_at' => $u->Last_Login_At,
            'pwdChangedAt' => $u->Pwd_Changed_At,
            'terdaftarSejak' => $u->Created_At,

            // ── RINGKASAN LAMARAN ────────────────────────────────────────
            'ringkasan' => self::ringkasanLamaran($userId),
        ];
    }

    /**
     * Ringkasan lamaran kandidat — dihitung di DB, satu kueri.
     *
     * Sebelumnya layar profil menghitungnya dari sessionStorage peramban
     * (utils/career/session.js), peninggalan masa prototipe tanpa DB. Angkanya
     * karena itu selalu 0 untuk pelamar sungguhan, dan berubah-ubah kalau
     * pelamar berpindah peramban. Status yang dipakai sama dengan portal
     * Lamaran Saya supaya dua layar tidak pernah menyebut angka berbeda.
     */
    private static function ringkasanLamaran(int $userId): array
    {
        try {
            $per = DB::table('N_WEB_CAREERS_Lamaran')
                ->where('Id_Users', $userId)
                ->select('Status', DB::raw('COUNT(*) as Jumlah'))
                ->groupBy('Status')
                ->pluck('Jumlah', 'Status');
        } catch (\Throwable $e) {
            Log::warning('Ringkasan lamaran profil gagal: '.$e->getMessage());

            return ['total' => 0, 'berjalan' => 0, 'lulus' => 0, 'gugur' => 0, 'selesai' => 0];
        }

        $ambil = fn (string $s) => (int) ($per[$s] ?? 0);

        return [
            'total' => (int) $per->sum(),
            'berjalan' => $ambil('BERJALAN'),
            'lulus' => $ambil('LULUS'),
            'gugur' => $ambil('GUGUR') + $ambil('MUNDUR'),
            'selesai' => array_sum(array_map($ambil, self::STATUS_SELESAI)),
        ];
    }

    /** Nama klasifikasi dari masternya — bukan daftar tetap yang ikut basi. */
    private static function labelKlasifikasi(?string $kode): ?string
    {
        if (! $kode) {
            return null;
        }

        try {
            return DB::table('N_WEB_CAREERS_Klasifikasi_Akun')->where('Kode', $kode)->value('Nama') ?: $kode;
        } catch (\Throwable $e) {
            return $kode;
        }
    }

    /**
     * KTP disamarkan walau ini profil pemiliknya sendiri.
     *
     * Layar ini sering dibuka sambil dibantu orang lain (screenshot ke tim
     * rekrutmen, layar dibagikan saat wawancara daring). Empat digit terakhir
     * sudah cukup untuk memastikan "ini KTP saya yang benar", dan itulah satu-
     * satunya kebutuhan halaman ini terhadap nomornya.
     */
    private static function samarkanNik(?string $nik): ?string
    {
        $nik = trim((string) $nik);
        if ($nik === '') {
            return null;
        }
        if (strlen($nik) <= 4) {
            return $nik;
        }

        return str_repeat('*', strlen($nik) - 4) . substr($nik, -4);
    }
}
