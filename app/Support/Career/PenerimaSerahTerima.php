<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * CALON PEMEGANG LOKER — dipakai pemilih PIC dan panel serah terima.
 *
 * ══ KENAPA DIPISAH JADI LAYANAN SENDIRI ═════════════════════════════════════
 *
 * Daftarnya dulu hanya hidup sebagai satu metode di MasterAkunController, dan
 * halaman Program Kegiatan ikut memanggil rutenya. Akibatnya rekruter yang
 * tidak memegang `masterAkunPage` menerima 403 saat membuka panel serah terima
 * di halaman yang JELAS-JELAS boleh ia buka — pintunya dijaga izin milik
 * halaman lain, dan tidak ada satu pun petunjuk di layar tentang kenapa.
 *
 * Menempelkan izin Master Akun ke rekruter untuk menambalnya justru lebih
 * buruk: itu membuka seluruh modul akun (membuat, menyunting, menonaktifkan
 * akun siapa pun) hanya agar satu dropdown terisi.
 *
 * Jadi kuerinya pindah ke sini, dan dua controller memanggilnya lewat rute
 * MASING-MASING dengan izin masing-masing. Satu perilaku, satu tempat — tanpa
 * salinan kedua yang kelak berbeda pendapat soal siapa "calon yang sah".
 *
 * ══ KENAPA DARI AKUN, BUKAN DARI TABEL KARYAWAN ═════════════════════════════
 *
 * Pemegang loker harus orang yang BISA MENGERJAKANNYA — dan untuk itu ia perlu
 * akun yang bisa masuk. Karyawan tanpa akun bukan calon yang sah, seberapa pun
 * benar kodenya.
 */
class PenerimaSerahTerima
{
    /**
     * @param  string|null  $q       kata pencarian (nama / kode karyawan)
     * @param  bool  $untukPenerima  true = daftar "siapa yang boleh MENERIMA
     *                               pekerjaan saya"; false = daftar "siapa yang
     *                               boleh SAYA TUGASKAN memegang program"
     * @param  array<int, string>|null  $bolehKode  batas kode karyawan yang
     *                                              boleh muncul; null = tanpa batas
     * @return array<int, array{value: string, label: string, nama: string, jmlMpp: int}>
     */
    public static function daftar(?string $q, bool $untukPenerima, ?array $bolehKode): array
    {
        $q = trim((string) $q);
        $saya = AksesService::kodeKaryawanSaya();

        $rows = DB::table('N_WEB_CAREERS_Users')
            ->whereIn('Role', ['ADMIN', 'SUPERADMIN'])
            ->where('Status', 'AKTIF')
            ->whereNotNull('Kode_Karyawan')
            ->when($bolehKode !== null, fn ($w) => $w->whereIn('Kode_Karyawan', $bolehKode ?: ['__tidak_ada__']))
            // Diri sendiri dibuang dari daftar PENERIMA. Menyerahkan kepada diri
            // sendiri tidak memindahkan apa pun, tapi tetap menulis satu baris
            // riwayat serah terima — jejak untuk peristiwa yang tidak terjadi.
            ->when($untukPenerima && $saya, fn ($w) => $w->where('Kode_Karyawan', '!=', $saya))
            ->when($q !== '', function ($w) use ($q) {
                $esc = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $q);
                $like = '%' . $esc . '%';
                $w->where(fn ($x) => $x->where('Nama', 'like', $like)->orWhere('Kode_Karyawan', 'like', $like));
            })
            ->orderBy('Nama')
            ->limit(50)
            ->get(['Nama', 'Kode_Karyawan']);

        // ── BERAPA MPP YANG BENAR-BENAR IA PEGANG ───────────────────────────
        //
        // Menugaskan program kepada orang yang tidak memegang satu pun MPP
        // adalah jalan buntu yang baru ketahuan di langkah Posisi: daftarnya
        // kosong, dan admin mengira sistemnya rusak. Angkanya dibawa di sini
        // supaya peringatannya muncul PADA SAAT MEMILIH.
        //
        // Hanya MPP yang masih bisa dipakai yang dihitung — yang dibatalkan
        // (Status='Y') dan yang sudah selesai tidak akan pernah muncul sebagai
        // pilihan, jadi menghitungnya cuma melahirkan janji yang tak ditepati.
        $mpp = DB::table('HRIS_Transaksi_GForm')
            ->whereIn('User_Penganggung_Jawab', $rows->pluck('Kode_Karyawan')->filter()->all() ?: ['__'])
            ->whereRaw("ISNULL(Status, '') <> 'Y'")
            ->whereRaw("ISNULL(Flag_Selesai, '') <> 'Y'")
            ->selectRaw('User_Penganggung_Jawab AS kode, COUNT(*) AS n')
            ->groupBy('User_Penganggung_Jawab')
            ->pluck('n', 'kode');

        return $rows->map(function ($r) use ($mpp) {
            $n = (int) ($mpp[$r->Kode_Karyawan] ?? 0);

            return [
                'value' => $r->Kode_Karyawan,
                // Jumlahnya ikut di label supaya terbaca SEBELUM dipilih —
                // bukan sebagai kejutan sesudahnya.
                'label' => "{$r->Nama} ({$r->Kode_Karyawan}) — {$n} MPP",
                'nama' => $r->Nama,
                'jmlMpp' => $n,
            ];
        })->values()->all();
    }

    /**
     * Batas kode karyawan untuk sebuah halaman + maksud pemakaian.
     *
     * Untuk daftar PENERIMA batasnya sengaja NULL. Keduanya sempat memakai
     * penyaring yang sama — lingkup PIC halaman — dan itu keliru pada yang
     * kedua sampai membuat fiturnya mustahil dipakai: rekruter berlingkup
     * SENDIRI hanya "boleh melihat pekerjaan dirinya", sehingga daftar
     * penerimanya menyusut jadi DIRINYA SENDIRI — satu-satunya orang yang tidak
     * mungkin ia tuju.
     *
     * Yang perlu dijaga bukan KEPADA SIAPA diberikan, melainkan APA yang boleh
     * diberikan — dan itu dijaga di sisi sumber: serahTerima() menolak setiap
     * loker yang bukan miliknya.
     *
     * ══ SERAH_TERIMA BUKAN IZIN MENUGASKAN ══════════════════════════════════
     *
     * Sampai perbaikan ini, pemegang SERAH_TERIMA dilepas dari batas lingkup
     * pada KEDUA daftar. Akibatnya rekruter berlingkup SENDIRI — yang hanya
     * berhak atas pekerjaannya sendiri — melihat SELURUH akun rekruter di
     * pemilih "Penanggung Jawab Program", dan benar-benar bisa membuat program
     * atas nama orang lain.
     *
     * Dua wewenang itu tidak sama, dan menyamakannya adalah kekeliruan yang
     * mahal:
     *
     *   SERAH_TERIMA   "saya boleh menyerahkan pekerjaan SAYA kepada orang
     *                   lain" — sumbernya diperiksa, panelnya sendiri.
     *   Lingkup PIC    "saya boleh membuat pekerjaan ATAS NAMA orang lain."
     *
     * Yang pertama tidak pernah menyiratkan yang kedua. Maka pelepasannya
     * dicabut dari daftar PENUGASAN, dan hanya disisakan pada daftar PENERIMA —
     * di sana ia memang perlu, sebab menyerahkan kepada diri sendiri tidak
     * memindahkan apa pun.
     *
     * @return array<int, string>|null  null = tanpa batas
     */
    public static function batas(string $page, bool $untukPenerima): ?array
    {
        if ($untukPenerima) {
            return null;
        }

        return AksesService::picDiizinkan($page);
    }
}
