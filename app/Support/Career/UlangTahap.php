<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * MENGULANG TAHAP — mengembalikan kandidat ke tahap sebelumnya.
 *
 * Dipakai saat sebuah tahap harus dijalani ulang: jadwalnya salah, hasilnya
 * salah catat, atau tesnya gagal terkirim. Sebelum ini satu-satunya jalan
 * adalah menjalankan skrip SQL manual yang MENGHAPUS jejak tahapnya.
 *
 * ── YANG MEMBEDAKANNYA DARI SKRIP LAMA ──────────────────────────────────────
 *
 * Tidak ada yang dihapus. Baris tahap & aktivitasnya DISALIN dulu ke tabel
 * arsip (`..._Riwayat`), baru baris hidupnya dikembalikan ke keadaan bersih.
 * Berkas tidak dipindah sama sekali — ia hanya ditandai milik putaran ke
 * berapa, karena memindahkannya berarti memutus tautan berkas yang sudah
 * diunggah kandidat.
 *
 * Bentuk itu dipilih supaya tidak ada satu pun query yang sudah ada perlu
 * diubah: arsipnya berdiri di tabel sendiri, dan baris hidup tetap satu per
 * tahap seperti sebelumnya.
 *
 * ── APA YANG DIPERTAHANKAN SAAT DIRESET ─────────────────────────────────────
 *
 * Kolom cetakan — Label, Urutan, Kode, mode keputusan, formulir yang dibekukan,
 * aturan tuntas & talent pool — TIDAK disentuh. Semuanya dibekukan saat lamaran
 * dibuat justru supaya perjalanan yang sedang berlangsung tidak berubah
 * maknanya di tengah jalan; mengulang tahap bukan alasan untuk membatalkan
 * pembekuan itu. Yang dibersihkan hanya jejak PERJALANANNYA: status, hasil,
 * nilai, jadwal, keputusan, hold, dan tanggapan kandidat.
 */
class UlangTahap
{
    private const T_TAHAP = 'N_WEB_CAREERS_Lamaran_Tahap';

    private const T_TES = 'N_WEB_CAREERS_Lamaran_Tahap_Tes';

    private const T_TAHAP_ARSIP = 'N_WEB_CAREERS_Lamaran_Tahap_Riwayat';

    private const T_TES_ARSIP = 'N_WEB_CAREERS_Lamaran_Tahap_Tes_Riwayat';

    private const T_ULANG = 'N_WEB_CAREERS_Lamaran_Ulang';

    private const T_BERKAS_TAHAP = 'N_WEB_CAREERS_Lamaran_Tahap_Berkas';

    private const T_BERKAS_TES = 'N_WEB_CAREERS_Lamaran_Tes_Berkas';

    /** Cakupan: hanya tahap itu, atau tahap itu sampai yang terakhir. */
    public const CAKUPAN_TAHAP = 'TAHAP';

    public const CAKUPAN_RANGKAIAN = 'RANGKAIAN';

    /**
     * Jalankan pengulangan.
     *
     * @param  int     $lamaranId   lamaran yang diulang
     * @param  int     $urutan      tahap tujuan (kandidat berdiri lagi di sini)
     * @param  string  $cakupan     CAKUPAN_TAHAP | CAKUPAN_RANGKAIAN
     * @param  string  $alasan      wajib — jejak "kenapa"
     * @param  array   $admin       ['id' => int, 'nama' => string]
     * @return array   ringkasan untuk ditampilkan & dicatat
     */
    public static function jalankan(
        int $lamaranId,
        int $urutan,
        string $cakupan,
        string $alasan,
        array $admin,
        ?string $alasanHtml = null,
        bool $kirimEmail = false
    ): array {
        $cakupan = $cakupan === self::CAKUPAN_RANGKAIAN ? self::CAKUPAN_RANGKAIAN : self::CAKUPAN_TAHAP;

        return DB::transaction(function () use ($lamaranId, $urutan, $cakupan, $alasan, $alasanHtml, $admin, $kirimEmail) {
            $now = now();
            $nama = $admin['nama'] ?? 'SISTEM';
            $adminId = $admin['id'] ?? null;

            $lamaran = DB::table('N_WEB_CAREERS_Lamaran')
                ->where('Id_Lamaran', $lamaranId)
                ->lockForUpdate()
                ->first();

            if (! $lamaran) {
                throw new \RuntimeException('Lamaran tidak ditemukan.');
            }

            // Tahap yang ikut diulang. CAKUPAN_TAHAP menyentuh satu urutan saja;
            // CAKUPAN_RANGKAIAN menyentuh tahap tujuan sampai yang terakhir —
            // karena tahap sesudahnya berdiri di atas hasil tahap ini, dan
            // membiarkannya utuh berarti kandidat punya dua kebenaran sekaligus.
            $tahapQ = DB::table(self::T_TAHAP)->where('Lamaran_Id', $lamaranId);
            $tahapQ = $cakupan === self::CAKUPAN_RANGKAIAN
                ? $tahapQ->where('Urutan', '>=', $urutan)
                : $tahapQ->where('Urutan', $urutan);

            $tahapRows = $tahapQ->orderBy('Urutan')->get();

            if ($tahapRows->isEmpty()) {
                throw new \RuntimeException('Tahap yang diminta tidak ada pada lamaran ini.');
            }

            $tahapIds = $tahapRows->pluck('Id_Lamaran_Tahap')->all();
            $tesRows = DB::table(self::T_TES)->whereIn('Lamaran_Tahap_Id', $tahapIds)->get();

            $putaran = (int) ($lamaran->Putaran ?? 1);

            // 1) CATATAN TINDAKANNYA — dibuat lebih dulu supaya arsip di
            //    bawahnya punya induk untuk ditunjuk.
            $ulangId = DB::table(self::T_ULANG)->insertGetId([
                'Lamaran_Id' => $lamaranId,
                'Putaran' => $putaran,
                'Cakupan' => $cakupan,
                'Dari_Urutan' => $urutan,
                'Sampai_Urutan' => (int) $tahapRows->max('Urutan'),
                'Urutan_Tahap_Sebelum' => $lamaran->Urutan_Tahap,
                'Status_Sebelum' => $lamaran->Status,
                'Hasil_Akhir_Sebelum' => $lamaran->Hasil_Akhir,
                'Alasan' => $alasan,
                'Alasan_Html' => $alasanHtml,
                'Flag_Email' => $kirimEmail ? 'Y' : 'T',
                'Jml_Tahap' => $tahapRows->count(),
                'Jml_Aktivitas' => $tesRows->count(),
                'Jml_Berkas' => 0,
                'Created_At' => $now,
                'Created_By' => $nama,
                'Created_By_Id' => $adminId,
            ], 'Id_Lamaran_Ulang');

            // 2) ARSIPKAN apa adanya — sebelum satu kolom pun diubah.
            self::arsipkan(self::T_TAHAP_ARSIP, $tahapRows, $ulangId, $putaran, $now);
            self::arsipkan(self::T_TES_ARSIP, $tesRows, $ulangId, $putaran, $now);

            // 3) TANDAI BERKAS milik putaran ini. Tidak dipindah, tidak dihapus —
            //    hanya diberi induk, supaya layar bisa memisahkan "berkas
            //    putaran berjalan" dari "berkas riwayat" tanpa keduanya hilang.
            $berkasTahap = DB::table(self::T_BERKAS_TAHAP)
                ->whereIn('Lamaran_Tahap_Id', $tahapIds)
                ->whereNull('Ulang_Id')
                ->update(['Ulang_Id' => $ulangId, 'Putaran' => $putaran]);

            $tesIds = $tesRows->pluck('Id_Lamaran_Tahap_Tes')->all();
            $berkasTes = $tesIds
                ? DB::table(self::T_BERKAS_TES)
                    ->whereIn('Lamaran_Tahap_Tes_Id', $tesIds)
                    ->whereNull('Ulang_Id')
                    ->update(['Ulang_Id' => $ulangId, 'Putaran' => $putaran])
                : 0;

            // 4) KELUARKAN dari sesi penjadwalan tahap-tahap itu. Barisnya
            //    dihapus, bukan diarsipkan: peserta sesi adalah daftar hadir
            //    yang HIDUP — meninggalkannya di sana membuat kandidat terhitung
            //    sebagai peserta sesi yang tidak lagi ia jalani, dan sesinya
            //    tidak akan pernah bisa ditutup. Jejaknya tetap terbaca di arsip
            //    tahap (Penjadwalan_Tahap_Id ikut tersalin).
            $penjadwalanIds = $tahapRows->pluck('Penjadwalan_Tahap_Id')
                ->merge($tesRows->pluck('Penjadwalan_Tahap_Id'))
                ->filter()->unique()->values()->all();

            if ($penjadwalanIds) {
                DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                    ->whereIn('Penjadwalan_Tahap_Id', $penjadwalanIds)
                    ->where('Lamaran_Id', $lamaranId)
                    ->delete();
            }

            // 5) RESET baris hidupnya.
            foreach ($tahapRows as $i => $t) {
                DB::table(self::T_TAHAP)
                    ->where('Id_Lamaran_Tahap', $t->Id_Lamaran_Tahap)
                    ->update(self::tahapBersih(
                        // Tahap tujuan langsung BERJALAN — kandidat berdiri di
                        // sana begitu halaman dibuka. Sisanya kembali MENUNGGU.
                        pertama: (int) $t->Urutan === $urutan,
                        nama: $nama,
                        adminId: $adminId,
                        now: $now
                    ));
            }

            if ($tesIds) {
                DB::table(self::T_TES)
                    ->whereIn('Id_Lamaran_Tahap_Tes', $tesIds)
                    ->update(self::aktivitasBersih($nama, $adminId, $now));

                // Undangan konfirmasi & permintaan jadwal lain putaran lalu
                // selesai — jadwalnya barusan dilepas. Jadwal_Versi SENGAJA
                // tidak dinolkan: tautan dari surel lama harus tetap mati.
                KonfirmasiJadwal::tutupAktivitas($tesIds, 'DIULANG');
            }

            // Tahap tujuan kini BERJALAN lagi: batas pengisiannya dihitung ulang
            // dari saat ini, sama seperti tahap yang baru dibuka.
            $tujuan = $tahapRows->firstWhere('Urutan', $urutan);
            if ($tujuan) {
                BatasIsi::buka((int) $tujuan->Id_Lamaran_Tahap, $now, $nama, $adminId);
            }

            // 6) KEMBALIKAN lamarannya sendiri ke keadaan berjalan.
            DB::table('N_WEB_CAREERS_Lamaran')
                ->where('Id_Lamaran', $lamaranId)
                ->update([
                    'Urutan_Tahap' => $urutan,
                    'Status' => 'BERJALAN',
                    'Hasil_Akhir' => null,
                    'Gugur_Di_Tahap' => null,
                    'Alasan_Gugur' => null,
                    'Waktu_Selesai' => null,
                    'Putaran' => $putaran + 1,
                    'Updated_At' => $now,
                    'Updated_By' => $nama,
                    'Updated_By_Id' => $adminId,
                ]);

            DB::table(self::T_ULANG)
                ->where('Id_Lamaran_Ulang', $ulangId)
                ->update(['Jml_Berkas' => $berkasTahap + $berkasTes]);

            return [
                'ulangId' => $ulangId,
                'putaran' => $putaran,
                'cakupan' => $cakupan,
                'dariUrutan' => $urutan,
                'sampaiUrutan' => (int) $tahapRows->max('Urutan'),
                'jmlTahap' => $tahapRows->count(),
                'jmlAktivitas' => $tesRows->count(),
                'jmlBerkas' => $berkasTahap + $berkasTes,
                'label' => $tahapRows->firstWhere('Urutan', $urutan)->Label ?? null,
            ];
        });
    }

    /**
     * Salin baris apa adanya ke tabel arsip.
     *
     * Kolomnya dicocokkan lewat INFORMATION_SCHEMA, bukan didaftar dengan
     * tangan: tabel tahap punya 61 kolom dan aktivitas 78, dan daftar yang
     * diketik manual pasti tertinggal begitu ada kolom baru ditambahkan —
     * diam-diam, tanpa galat, dengan kolom terbaru justru yang hilang dari
     * riwayat.
     */
    private static function arsipkan(string $tabelArsip, $rows, int $ulangId, int $putaran, $now): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        static $kolomArsip = [];
        $kolomArsip[$tabelArsip] ??= collect(DB::select(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?',
            [$tabelArsip]
        ))->pluck('COLUMN_NAME')->flip();

        $sah = $kolomArsip[$tabelArsip];
        $muatan = [];

        foreach ($rows as $r) {
            $baris = ['Ulang_Id' => $ulangId, 'Putaran' => $putaran, 'Arsip_At' => $now];
            foreach ((array) $r as $k => $v) {
                if ($sah->has($k)) {
                    $baris[$k] = $v;
                }
            }
            $muatan[] = $baris;
        }

        // Dipotong 200-an baris per suntikan: SQL Server membatasi 2100
        // parameter per perintah, dan satu baris tahap saja sudah 65 kolom.
        foreach (array_chunk($muatan, 25) as $bagian) {
            DB::table($tabelArsip)->insert($bagian);
        }
    }

    /** Kolom perjalanan yang dinolkan pada tahap. Cetakannya tidak disentuh. */
    private static function tahapBersih(bool $pertama, string $nama, ?int $adminId, $now): array
    {
        // Catatan untuk kandidat ikut dinolkan — ia menempel pada keputusan
        // yang barusan dibatalkan (salinannya sudah masuk arsip). Hanya bila
        // kolomnya sudah dibuat; sebelum itu kolomnya memang belum ada.
        $eksternal = CatatanEksternal::siap() ? [CatatanEksternal::KOLOM => null] : [];

        // Batas pengisian putaran lalu ikut dinolkan (aturannya tetap); tahap
        // tujuan dihitung ulang sesudahnya lewat BatasIsi::buka().
        return $eksternal + BatasIsi::kolomBersih() + [
            'Status' => $pertama ? 'BERJALAN' : 'MENUNGGU',
            'Hasil' => null,
            'Skor' => null,
            'Catatan' => null,
            'Catatan_Html' => null,
            'Penjadwalan_Tahap_Id' => null,
            'Tanggal_Pengumuman' => null,
            'Waktu_Diumumkan' => null,
            'Waktu_Mulai' => null,
            'Waktu_Selesai' => null,
            'Diputus_By' => null,
            'Diputus_By_Id' => null,
            'Diputus_At' => null,
            'Rekomendasi' => null,
            'Rekomendasi_Alasan' => null,
            'Rekomendasi_At' => null,
            'Jejak_Json' => null,
            'Siap_Diputus' => 'T',
            'Flag_Bypass' => 'T',
            'Tanggapan_Kandidat' => null,
            'Tanggapan_Catatan' => null,
            'Tanggapan_At' => null,
            'Tanggal_Konfirmasi_Kandidat' => null,
            'Masuk_Talent_Pool' => null,
            // Hold ikut dilepas: penahanan menempel pada keputusan yang barusan
            // dibatalkan, dan membawanya ke putaran baru berarti kandidat
            // tertahan karena alasan yang sudah tidak berlaku.
            'Hold_Flag' => 'T',
            'Hold_Alasan' => null,
            'Hold_Alasan_Html' => null,
            'Hold_Alasan_Kode' => null,
            'Hold_At' => null,
            'Hold_By' => null,
            'Hold_By_Id' => null,
            'Hold_Lepas_At' => null,
            'Hold_Lepas_By' => null,
            'Hold_Lepas_By_Id' => null,
            'Updated_At' => $now,
            'Updated_By' => $nama,
            'Updated_By_Id' => $adminId,
        ];
    }

    /** Kolom perjalanan yang dinolkan pada aktivitas. */
    private static function aktivitasBersih(string $nama, ?int $adminId, $now): array
    {
        return [
            'Status' => 'BELUM',
            'Hasil' => null,
            'Nilai' => null,
            'Nilai_Teks' => null,
            'Total_Soal' => null,
            'Flag_Selesai' => 'T',
            'Waktu_Selesai' => null,
            'Catatan' => null,
            'Catatan_Html' => null,
            'Penjadwalan_Tahap_Id' => null,
            // Seluruh jadwal dilepas — token & tautan ujian lama menunjuk sesi
            // yang tidak lagi berlaku.
            'Jadwal_Mode' => null,
            'Jadwal_Mulai' => null,
            'Jadwal_Selesai' => null,
            'Jadwal_Link' => null,
            'Jadwal_Lokasi' => null,
            'Jadwal_Lokasi_Id' => null,
            'Jadwal_Lokasi_Nama' => null,
            'Jadwal_Lokasi_Alamat' => null,
            'Jadwal_Kontak' => null,
            'Jadwal_Catatan' => null,
            'Jadwal_At' => null,
            'Jadwal_By' => null,
            'Jadwal_By_Id' => null,
            'Jadwal_Email_At' => null,
            'Jadwal_Hadir' => null,
            'Jadwal_Hadir_At' => null,
            'Jadwal_Hadir_By' => null,
            'Mcu_Status' => null,
            'Mcu_Penyedia' => null,
            'Mcu_Catatan' => null,
            'Mcu_Tanggal' => null,
            'Unggah_Kirim_At' => null,
            'Unggah_Kirim_By' => null,
            'Unggah_Kirim_Ip' => null,
            'Lanjut_At' => null,
            'Lanjut_By' => null,
            'Lanjut_By_Id' => null,
            'Adjudikasi' => null,
            'Adjudikasi_At' => null,
            'Adjudikasi_By' => null,
            'Adjudikasi_By_Id' => null,
            'Adjudikasi_Ringkas' => null,
            'Persetujuan_Sumber' => null,
            'Persetujuan_Ref' => null,
            'Persetujuan_At' => null,
            'Persetujuan_By' => null,
            'Tanggapan_Diminta_At' => null,
            'Tanggapan_Isi' => null,
            'Tanggapan_At' => null,
            'Updated_At' => $now,
            'Updated_By' => $nama,
            'Updated_By_Id' => $adminId,
        ] + self::jadwalTambahanBersih();
    }

    /**
     * Kolom jadwal 30-09-2026 (instruksi, tempat vendor, peta, surat pengantar)
     * — ikut dilepas bila kolomnya sudah ada. Tanpa ini, surat pengantar
     * percobaan sebelumnya terbawa diam-diam ke jadwal percobaan berikutnya.
     */
    private static function jadwalTambahanBersih(): array
    {
        $kolom = [];
        if (UndanganJadwal::siapInstruksi()) {
            $kolom['Jadwal_Catatan_Html'] = null;
        }
        if (UndanganJadwal::siapVendor()) {
            $kolom += [
                'Jadwal_Lokasi_Html' => null,
                'Jadwal_Maps_Url' => null,
                'Jadwal_Surat_Json' => null,
            ];
        }
        if (UndanganJadwal::siapTampilBiaya()) {
            $kolom['Jadwal_Tampil_Biaya'] = null;
        }
        if (Skema::adaKolom('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Jadwal_Kalimat_Biaya')) {
            $kolom['Jadwal_Kalimat_Biaya'] = null;
        }
        if (UndanganJadwal::siapBatasUnggah()) {
            $kolom['Jadwal_Batas_Unggah'] = null;
        }
        // Jawaban konfirmasi ikut dilepas; Jadwal_Versi TIDAK (lihat
        // KonfirmasiJadwal — versi tidak pernah mundur).
        if (Skema::adaKolom('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Konfirmasi_Status')) {
            $kolom['Konfirmasi_Status'] = null;
        }

        return $kolom;
    }
}
