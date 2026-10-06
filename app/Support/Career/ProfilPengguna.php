<?php

namespace App\Support\Career;

use App\Support\Career\Shell\IdentitasShell;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — DATA HALAMAN /profil untuk SEMUA peran.
 *
 * ── KENAPA BERKAS INI ADA ────────────────────────────────────────────────
 *
 * Halaman /profil dulu diberi makan CareerShell::adminUser(), yaitu identitas
 * SHELL: name / username / nik / department. Bentuk itu memang benar untuk
 * footer sidebar, tapi layar profil membaca kunci lain sama sekali (nama,
 * email, no_hp, klasifikasi, valid_until, last_login_at ...). Tidak satu pun
 * cocok, jadi seluruh isi kartunya kosong tanpa pernah memunculkan galat —
 * kegagalan diam yang paling mahal untuk ditemukan.
 *
 * Identitas shell juga tidak akan pernah cukup: sesi hanya membawa segelintir
 * kolom yang dibutuhkan gerbang akses (id, nama, email, role, klasifikasi,
 * valid_until). Nomor HP, status verifikasi email, tanggal daftar, dan waktu
 * login terakhir tidak ada di sana — dan kolom-kolom itulah isi halaman ini.
 * Karena itu profil dibaca ULANG dari DB, bukan disalin dari sesi: sekalian
 * membuat perubahan yang dilakukan admin di Master Akun langsung terlihat
 * pemiliknya tanpa harus login ulang.
 *
 * Satu halaman untuk kandidat, admin, dan superadmin. Yang berbeda hanya
 * ringkasannya: kandidat dapat ringkasan LAMARAN, admin dapat ringkasan
 * AKSES — bukan ringkasan lamaran kosong yang tidak pernah bisa terisi.
 */
class ProfilPengguna
{
    /**
     * Perusahaan yang dilayani modul Web Careers — Karyawan berkunci komposit
     * (Kode_Perusahaan + Kode_Karyawan). Nilainya sama dengan MasterAkun.
     */
    private const KODE_PERUSAHAAN = '001';

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
        $adminis = in_array($role, IdentitasShell::PERAN_ADMIN, true);

        return [
            // ── IDENTITAS ────────────────────────────────────────────────
            'nama' => $u->Nama,
            'email' => $u->Email,
            'no_hp' => $u->No_Hp,
            'nik' => self::samarkanNik($u->NIK),
            'kode_calon' => $u->Kode_Calon,
            'role' => $role,
            'roleLabel' => IdentitasShell::labelPeran($role),
            'adalahAdmin' => $adminis,

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

            // ── KEPEGAWAIAN (admin yang ditautkan ke Karyawan) ───────────
            'kodeKaryawan' => $u->Kode_Karyawan,
            'karyawanNama' => self::namaKaryawan($u->Kode_Karyawan),

            // ── RINGKASAN SESUAI PERAN ───────────────────────────────────
            'ringkasan' => $adminis ? null : self::ringkasanLamaran($userId),
            'akses' => $adminis ? self::ringkasanAkses() : null,
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
            Log::channel('web_career')->warning('Ringkasan lamaran profil gagal: ' . $e->getMessage());

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

    /**
     * Ringkasan hak akses admin — dibaca dari PAKET SESI, bukan dari DB.
     *
     * Sengaja: paket sesi itulah yang benar-benar menentukan apa yang bisa
     * dibuka akun ini sampai ia login lagi. Menghitung ulang dari DB akan
     * menampilkan angka yang lebih besar daripada yang sungguh berlaku tepat
     * setelah admin lain menambah aksesnya — dan pemiliknya akan mengira
     * menunya rusak karena jumlahnya tidak cocok dengan yang terlihat.
     */
    private static function ringkasanAkses(): array
    {
        $izin = (array) session('career_akses.permissions', []);

        return [
            'halaman' => count($izin),
            'aksi' => array_sum(array_map(fn ($a) => count((array) $a), $izin)),
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
     * Nama karyawan pemilik Kode_Karyawan.
     *
     * Kueri terpisah dan dibungkus try: tabel Karyawan milik HRIS, jadi ia
     * bisa saja belum ada di basis data pengembangan. Halaman profil tidak
     * boleh ikut mati hanya karena satu baris pelengkap tidak bisa dibaca.
     */
    private static function namaKaryawan(?string $kode): ?string
    {
        if (! $kode) {
            return null;
        }

        try {
            return DB::table('Karyawan')
                ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
                ->where('Kode_Karyawan', $kode)
                ->value('Nama');
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Nama karyawan profil gagal: ' . $e->getMessage());

            return null;
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
