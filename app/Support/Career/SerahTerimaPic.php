<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — SERAH TERIMA KEPEMILIKAN LOKER.
 *
 * Satu layanan, dua pintu masuk:
 *
 *   Master Akun        admin memindahkan pekerjaan orang lain (ia cuti, sakit,
 *                      resign) tanpa menyentuh orangnya sama sekali
 *   Program Kegiatan   rekruter menyerahkan lokernya sendiri
 *
 * Keduanya memanggil jalankan() yang sama. Dua logika untuk satu peristiwa
 * berarti dua tempat yang harus diperbaiki setiap kali aturannya bergeser, dan
 * yang kedua selalu ketinggalan.
 *
 * ── APA YANG SEBENARNYA BERPINDAH ───────────────────────────────────────────
 *
 * Hanya satu kolom: Program_Posisi.Pic_Kode_Karyawan.
 *
 * Tidak ada baris lamaran yang disentuh. Kandidat menempel ke lokernya
 * (Lamaran.Program_Posisi_Id), jadi begitu pemilik lokernya berganti seluruh
 * kandidatnya ikut — termasuk yang sedang di tengah tahap, yang jadwal tesnya
 * besok, yang berkasnya sudah diunggah. Tidak ada yang perlu dipindahkan, dan
 * karena itu tidak ada yang bisa tertinggal separuh jalan.
 *
 * Itu pula alasan kepemilikan diletakkan di loker sejak awal, bukan di program.
 */
class SerahTerimaPic
{
    public const SUMBER_SENDIRI = 'SENDIRI';

    public const SUMBER_ADMIN = 'ADMIN';

    /**
     * Pindahkan sekumpulan loker ke satu pemilik baru.
     *
     * @param  array<int, int>  $posisiIds  loker yang dipindahkan
     * @param  array{id:?int, nama:string}  $admin  pelaku, untuk jejak
     * @return array{jml:int, kandidat:int, dilewati:int}
     *
     * SELURUHNYA DALAM SATU TRANSAKSI. Serah terima tiga loker yang berhasil
     * dua lalu gagal satu adalah keadaan yang paling buruk: rekruter penerima
     * mengira sudah pegang semua, yang lama mengira sudah lepas semua, dan satu
     * loker menggantung tanpa ada yang merasa memegangnya.
     */
    public static function jalankan(
        array $posisiIds,
        string $keKode,
        string $alasan,
        array $admin,
        string $sumber = self::SUMBER_ADMIN
    ): array {
        $posisiIds = array_values(array_unique(array_filter(array_map('intval', $posisiIds))));

        if (! $posisiIds) {
            return ['jml' => 0, 'kandidat' => 0, 'dilewati' => 0];
        }

        return DB::transaction(function () use ($posisiIds, $keKode, $alasan, $admin, $sumber) {
            $now = now();

            $keNama = self::nama($keKode);

            // DIKUNCI. Dua admin yang memindahkan loker yang sama ke orang
            // berbeda pada detik yang sama tanpa kunci akan sama-sama menulis
            // riwayat "dari A ke B" dan "dari A ke C" — dua jejak yang keduanya
            // mengaku benar, sementara kolom pemiliknya hanya menyimpan satu.
            $rows = DB::table('N_WEB_CAREERS_Program_Posisi')
                ->whereIn('Id_Program_Posisi', $posisiIds)
                ->lockForUpdate()
                ->get(['Id_Program_Posisi', 'Program_Id', 'Posisi', 'Pic_Kode_Karyawan']);

            $jml = 0;
            $kandidatTotal = 0;
            $dilewati = 0;

            foreach ($rows as $p) {
                // Sudah dipegang orang yang dituju: bukan galat, cuma tidak ada
                // yang perlu dikerjakan. Menuliskannya sebagai riwayat "dari B
                // ke B" hanya mengotori jejak yang justru dibaca saat menelusuri
                // perpindahan yang sesungguhnya.
                if ((string) $p->Pic_Kode_Karyawan === $keKode) {
                    $dilewati++;

                    continue;
                }

                $dariNama = self::nama($p->Pic_Kode_Karyawan);

                // Dihitung SEBELUM dipindahkan dan dibekukan ke riwayat: angkanya
                // berubah terus sesudahnya, sementara yang perlu terbaca kelak
                // adalah sebesar apa beban yang berpindah pada saat itu.
                $kandidat = (int) DB::table('N_WEB_CAREERS_Lamaran')
                    ->where('Program_Posisi_Id', $p->Id_Program_Posisi)
                    ->count();

                DB::table('N_WEB_CAREERS_Program_Posisi')
                    ->where('Id_Program_Posisi', $p->Id_Program_Posisi)
                    ->update([
                        'Pic_Kode_Karyawan' => $keKode,
                        'Pic_Sejak' => $now,
                        'Updated_By_Id' => $admin['id'] ?? null,
                    ]);

                DB::table('N_WEB_CAREERS_Posisi_Pic_Riwayat')->insert([
                    'Program_Posisi_Id' => $p->Id_Program_Posisi,
                    'Program_Id' => $p->Program_Id,
                    'Dari_Kode' => $p->Pic_Kode_Karyawan,
                    'Dari_Nama' => $dariNama,
                    'Ke_Kode' => $keKode,
                    'Ke_Nama' => $keNama,
                    'Alasan' => $alasan,
                    'Sumber' => $sumber,
                    'Jml_Kandidat' => $kandidat,
                    'Created_At' => $now,
                    'Created_By' => $admin['nama'] ?? 'SISTEM',
                    'Created_By_Id' => $admin['id'] ?? null,
                ]);

                $jml++;
                $kandidatTotal += $kandidat;
            }

            if ($jml) {
                Log::channel('web_career')->info(sprintf(
                    '[SERAH-TERIMA] %d loker (%d kandidat) -> %s (%s) oleh %s [%s]. Alasan: %s',
                    $jml, $kandidatTotal, $keNama ?: $keKode, $keKode,
                    $admin['nama'] ?? 'SISTEM', $sumber, $alasan
                ));
            }

            return ['jml' => $jml, 'kandidat' => $kandidatTotal, 'dilewati' => $dilewati];
        });
    }

    /**
     * LOKER YANG SEDANG DIPEGANG SEORANG KARYAWAN.
     *
     * Dipakai panel Master Akun: "orang ini masuk rumah sakit — ia sedang
     * memegang apa saja?". Program yang sudah DIBATALKAN ikut dibawa, tapi
     * ditandai: loker di program batal tidak perlu diserahterimakan, dan
     * menyembunyikannya membuat jumlah di layar tidak cocok dengan kenyataan.
     */
    public static function pekerjaan(string $kodeKaryawan): array
    {
        return DB::table('N_WEB_CAREERS_Program_Posisi as pp')
            ->join('N_WEB_CAREERS_Program as pr', 'pr.Id_Program', '=', 'pp.Program_Id')
            ->where('pp.Pic_Kode_Karyawan', $kodeKaryawan)
            ->orderBy('pr.Nama')
            ->orderBy('pp.Posisi')
            ->get([
                'pp.Id_Program_Posisi', 'pp.Posisi', 'pp.Kuota', 'pp.Terisi',
                'pp.Status', 'pp.Mpp_Ref', 'pp.Pic_Sejak',
                'pr.Id_Program', 'pr.Nama as ProgramNama', 'pr.Kategori', 'pr.Status as ProgramStatus',
            ])
            ->map(fn ($r) => [
                'id' => $r->Id_Program_Posisi,
                'posisi' => $r->Posisi,
                'program' => $r->ProgramNama,
                'programId' => $r->Id_Program,
                'kategori' => $r->Kategori,
                'programAktif' => $r->ProgramStatus !== 'NONAKTIF',
                'kuota' => (int) $r->Kuota,
                'terisi' => (int) $r->Terisi,
                'status' => $r->Status,
                'mppRef' => $r->Mpp_Ref,
                'sejak' => $r->Pic_Sejak,
                'kandidat' => (int) DB::table('N_WEB_CAREERS_Lamaran')
                    ->where('Program_Posisi_Id', $r->Id_Program_Posisi)->count(),
            ])
            ->values()
            ->all();
    }

    /** Riwayat perpindahan sebuah loker — terbaru dulu. */
    public static function riwayat(int $posisiId, int $batas = 20): array
    {
        return DB::table('N_WEB_CAREERS_Posisi_Pic_Riwayat')
            ->where('Program_Posisi_Id', $posisiId)
            ->orderByDesc('Id_Posisi_Pic_Riwayat')
            ->limit($batas)
            ->get()
            ->map(fn ($r) => [
                'dari' => $r->Dari_Nama ?: $r->Dari_Kode,
                'ke' => $r->Ke_Nama ?: $r->Ke_Kode,
                'alasan' => $r->Alasan,
                'sumber' => $r->Sumber,
                'kandidat' => (int) $r->Jml_Kandidat,
                'at' => $r->Created_At,
                'oleh' => $r->Created_By,
            ])
            ->all();
    }

    /**
     * NAMA PEMILIK SEBUAH KODE KARYAWAN.
     *
     * AKUN DULU, tabel kepegawaian belakangan. Kode karyawan kini isian bebas
     * yang sumbernya bisa di luar tabel Karyawan, jadi satu-satunya tempat yang
     * PASTI mengenalinya adalah akun yang memakainya — dan itu juga nama yang
     * dikenal orang di layar ini.
     *
     * Tabel Karyawan tetap dicoba sebagai cadangan: kode yang belum punya akun
     * (mis. PIC MPP yang belum pernah dibuatkan akun) tetap terbaca namanya
     * selama ia memang ada di sana.
     */
    public static function nama(?string $kode): ?string
    {
        $kode = trim((string) $kode);
        if ($kode === '') {
            return null;
        }

        $dariAkun = DB::table('N_WEB_CAREERS_Users')
            ->where('Kode_Karyawan', $kode)
            ->value('Nama');

        if ($dariAkun) {
            return $dariAkun;
        }

        return DB::table('Karyawan')
            ->where('Kode_Perusahaan', self::perusahaan())
            ->where('Kode_Karyawan', $kode)
            ->value('Nama');
    }

    private static function perusahaan(): string
    {
        return '001';
    }
}
