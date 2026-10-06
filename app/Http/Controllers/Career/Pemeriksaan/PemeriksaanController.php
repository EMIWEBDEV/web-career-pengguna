<?php

namespace App\Http\Controllers\Career\Pemeriksaan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\Pemeriksaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — PEMERIKSAAN LATAR BELAKANG & REFERENSI.
 *
 * Dua aktivitas ini bukan tes: hasilnya bukan angka, melainkan TEMUAN yang
 * dicatat satuan demi satuan — per komponen pemeriksaan (ijazah, riwayat kerja,
 * catatan hukum) atau per narasumber yang dihubungi.
 *
 * KENAPA ENDPOINT SENDIRI, BUKAN MENUMPANG catat-hasil:
 *
 * catat-hasil menutup aktivitas — sekali ditekan, aktivitasnya final dan tahap
 * ikut dievaluasi. Padahal pemeriksaan berlangsung berhari-hari: verifikasi
 * ijazah dikirim Senin, jawabannya datang Kamis, sementara riwayat kerja masih
 * menunggu. Setiap potongan itu harus bisa disimpan tanpa menutup apa pun.
 *
 * Yang menutup tetap catat-hasil, sesudah semua potongannya terisi.
 */
class PemeriksaanController extends Controller
{
    /**
     * Aktivitas yang sah untuk dikerjakan.
     *
     * `$harusTerbuka = false` dipakai jalur BACA (buka): aktivitas yang sudah
     * final tetap boleh dilihat temuannya — yang dilarang mengubahnya.
     */
    private function aktivitas(string $id, bool $harusTerbuka = true): array
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return [null, 'Aktivitas tidak valid.'];
        }

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Id_Lamaran_Tahap_Tes', $realId)
            ->first();

        if (! $sub) {
            return [null, 'Aktivitas tidak ditemukan.'];
        }

        // AKTIVITAS YANG SUDAH FINAL TIDAK BOLEH DIUBAH TEMUANNYA.
        //
        // Bukan sekadar disiplin: kesimpulan tahap sudah dihitung dari temuan
        // yang ada saat itu, dan mengubahnya belakangan membuat keputusan yang
        // tercatat tidak lagi cocok dengan bukti yang mendasarinya.
        if ($harusTerbuka && ($sub->Flag_Selesai ?? 'N') === 'Y') {
            return [null, 'Aktivitas ini sudah final — temuannya tidak bisa diubah lagi.'];
        }

        if (! Pemeriksaan::untuk($sub->Tipe_Tahap_Kode ?? null)) {
            return [null, 'Aktivitas ini bukan pemeriksaan.'];
        }

        return [$sub, null];
    }

    /**
     * Baris aktivitas yang SUDAH memuat rangkuman terbaru.
     *
     * hitungRingkas() menulis Adjudikasi_Ringkas ke basis data; salinan yang
     * dipegang di sini masih keadaan sebelum tulisan itu. Mengembalikannya apa
     * adanya membuat layar memperlihatkan rangkuman lama sampai halaman dimuat
     * ulang — dan orang menyimpan dua kali karena mengira yang pertama gagal.
     */
    private function segar(object $sub): object
    {
        return DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)
            ->first() ?? $sub;
    }

    private function petugas(): array
    {
        return [
            session('career_auth.nama', 'ADMIN'),
            session('career_auth.id'),
        ];
    }

    /**
     * GET .../sub-tes/{id}/pemeriksaan — isi PENUH, berikut jejak aksesnya.
     *
     * Payload Worklist mengunci isi temuan yang ditandai sensitif; yang membuka
     * kuncinya endpoint ini, dan pembukaan itu yang dicatat. Dua hal sekaligus:
     * data pribadi spesifik tidak ikut terkirim ke setiap pemuatan daftar, dan
     * pertanyaan "siapa yang pernah membaca catatan hukum si A" punya jawaban.
     */
    public function buka(Request $request, string $id)
    {
        [$sub, $galat] = $this->aktivitas($id, false);
        if ($galat) {
            return ResponseHelper::error($galat, 422);
        }

        $penuh = Pemeriksaan::bentuk($sub, true);

        // Yang dicatat hanya komponen sensitif yang BENAR-BENAR berisi. Membuka
        // baris kosong bukan pembukaan data siapa pun.
        $sensitif = Pemeriksaan::kodeSensitif();
        $dibuka = collect($penuh['komponen'] ?? [])
            ->filter(fn ($k) => in_array($k['kode'], $sensitif, true) && ! empty($k['ringkasan']))
            ->pluck('kode')
            ->values()
            ->all();

        Pemeriksaan::catatAkses($sub, $dibuka, $request);

        return ResponseHelper::success($penuh, 'Pemeriksaan dimuat.');
    }

    /**
     * PATCH .../sub-tes/{id}/persetujuan — keputusan kandidat, dicatat petugas.
     *
     * Tiga sumber: TELEPON (ditanya saat tahap dimulai — yang paling lazim),
     * BERKAS (persetujuan tertulis di luar sistem), dan TOLAK (kandidat menolak,
     * alasannya wajib).
     *
     * SATU TEKAN UNTUK SETUJU. Petugas sedang menelepon; menyuruhnya mengetik
     * kalimat persetujuan sambil bicara hanya melahirkan kalimat asal-asalan atau
     * langkah yang dilewati. Kalimat lengkapnya dirakit sistem.
     */
    public function simpanPersetujuan(Request $request, string $id)
    {
        [$sub, $galat] = $this->aktivitas($id);
        if ($galat) {
            return ResponseHelper::error($galat, 422);
        }

        $data = $request->validate([
            'sumber' => ['required', Rule::in(Pemeriksaan::SUMBER_PETUGAS)],
            'keterangan' => 'nullable|string|max:200',
        ]);

        [$nama, $namaId] = $this->petugas();
        $alasan = trim((string) ($data['keterangan'] ?? ''));

        // PENOLAKAN WAJIB PUNYA ALASAN.
        //
        // "Kandidat menolak" tanpa satu kalimat pun tidak bisa ditindaklanjuti:
        // menolak karena keberatan diperiksa catatan hukumnya berbeda jauh dari
        // menolak karena nomornya salah sambung, dan yang kedua semestinya
        // dihubungi ulang, bukan digugurkan.
        if ($data['sumber'] === 'TOLAK' && $alasan === '') {
            return ResponseHelper::error('Tuliskan alasan kandidat menolak pemeriksaan.', 422);
        }

        if ($data['sumber'] === 'BERKAS' && $alasan === '') {
            return ResponseHelper::error('Tuliskan di mana persetujuan tertulisnya berada.', 422);
        }

        // KALIMATNYA DIRAKIT SISTEM untuk persetujuan lewat telepon.
        //
        // Petugas cukup satu tekan; yang tersimpan tetap kalimat lengkap berikut
        // nama penanya dan tanggalnya — sebab yang membacanya setahun lagi
        // membutuhkan ketiganya, dan mengetiknya sendiri tiap kali hanya
        // melahirkan seratus versi kalimat yang sama.
        $ref = match ($data['sumber']) {
            'TELEPON' => mb_substr(sprintf(
                'Kandidat menyatakan SETUJU diperiksa lewat telepon kepada %s, %s.',
                // locale('id') dipaksa: locale aplikasi masih 'en', dan kalimat
                // yang berbunyi "21 August 2026" di tengah kalimat Indonesia terbaca
                // seperti tempelan dari sistem lain.
                $nama, now()->locale('id')->translatedFormat('d F Y'),
            ), 0, 200),
            default => mb_substr($alasan, 0, 200),
        };

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)
            ->update([
                'Persetujuan_Sumber' => $data['sumber'],
                'Persetujuan_Ref' => $ref,
                'Persetujuan_At' => now(),
                'Persetujuan_By' => $nama,
            ]);

        // RIWAYAT: keputusan ini yang paling sering ditanyakan belakangan —
        // "siapa yang bertanya, kapan, dan kandidatnya menjawab apa".
        Pemeriksaan::tulisRiwayat(
            $sub,
            $data['sumber'] === 'TOLAK' ? 'TOLAK_PERIKSA' : 'SETUJU_PERIKSA',
            $ref,
        );

        Log::channel('web_career')->info(sprintf(
            '[PEMERIKSAAN] persetujuan %s untuk aktivitas #%d: %s, oleh %s.',
            $data['sumber'] === 'TOLAK' ? 'DITOLAK' : 'dicatat ('.$data['sumber'].')',
            $sub->Id_Lamaran_Tahap_Tes, $ref, $nama,
        ));

        return ResponseHelper::success(
            Pemeriksaan::bentuk($this->segar($sub), true),
            $data['sumber'] === 'TOLAK'
                ? 'Penolakan kandidat dicatat. Pemeriksaan tidak boleh dijalankan.'
                : 'Persetujuan dicatat — silakan lanjut mengisi hasilnya.',
        );
    }

    /**
     * PATCH .../sub-tes/{id}/tanggapan — hak kandidat menanggapi temuan.
     *
     * `diminta` menandai kandidat sudah diberi tahu; `isi` menyimpan jawabannya.
     * Keduanya dipisah karena "sudah ditanya, belum menjawab" adalah keadaan
     * tersendiri — dan itulah keadaan yang paling sering terjadi.
     */
    public function simpanTanggapan(Request $request, string $id)
    {
        [$sub, $galat] = $this->aktivitas($id);
        if ($galat) {
            return ResponseHelper::error($galat, 422);
        }

        $data = $request->validate([
            'diminta' => 'nullable|boolean',
            'isi' => 'nullable|string|max:1000',
        ]);

        $isi = trim((string) ($data['isi'] ?? ''));

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)
            ->update([
                // Waktu PERMINTAAN tidak ditulis ulang setiap kali jawabannya
                // disunting: yang dicatat kapan kandidat DIBERI TAHU, bukan kapan
                // catatannya terakhir dirapikan.
                'Tanggapan_Diminta_At' => $sub->Tanggapan_Diminta_At
                    ?? (! empty($data['diminta']) || $isi !== '' ? now() : null),
                'Tanggapan_Isi' => $isi !== '' ? $isi : null,
                'Tanggapan_At' => $isi !== '' ? ($sub->Tanggapan_At ?? now()) : null,
            ]);

        Pemeriksaan::tulisRiwayat(
            $sub,
            'TANGGAPAN',
            $isi !== '' ? 'Kandidat menanggapi temuan.' : 'Kandidat diberi kesempatan menanggapi temuan.',
        );

        return ResponseHelper::success(Pemeriksaan::bentuk($this->segar($sub), true), 'Tanggapan dicatat.');
    }

    /**
     * PATCH .../sub-tes/{id}/verifikasi — simpan SATU komponen pemeriksaan.
     *
     * Upsert per (aktivitas, jenis): menyimpan ulang komponen yang sama
     * memperbarui barisnya, bukan menumpuk baris kedua dengan kesimpulan
     * berbeda tentang hal yang sama.
     */
    public function simpanKomponen(Request $request, string $id)
    {
        [$sub, $galat] = $this->aktivitas($id);
        if ($galat) {
            return ResponseHelper::error($galat, 422);
        }

        $data = $request->validate([
            'jenisKode' => ['required', 'string', 'max:30'],
            'status' => ['required', Rule::in(Pemeriksaan::STATUS)],
            'tingkatTemuan' => ['nullable', Rule::in(Pemeriksaan::TINGKAT)],
            'sumber' => 'nullable|string|max:150',
            'vendorRef' => 'nullable|string|max:100',
            'ringkasan' => 'nullable|string|max:1000',
            'tanggalMulai' => 'nullable|date',
            'tanggalSelesai' => 'nullable|date',
        ]);

        $jenis = DB::table('N_WEB_CAREERS_Master_Jenis_Verifikasi')
            ->where('Kode', $data['jenisKode'])
            ->first();

        if (! $jenis) {
            return ResponseHelper::error('Jenis pemeriksaan tidak dikenali.', 422);
        }

        // TEMUAN WAJIB PUNYA KETERANGAN.
        //
        // "Ada temuan" tanpa satu kalimat pun tidak bisa ditindaklanjuti siapa
        // pun: yang membacanya besok tidak tahu temuannya apa, seberapa berat,
        // dan apakah kandidat sudah ditanya. Yang tersisa hanya prasangka yang
        // menempel pada nama orang.
        if ($data['status'] === 'TEMUAN' && trim((string) ($data['ringkasan'] ?? '')) === '') {
            return ResponseHelper::error('Isi ringkasan temuannya — status TEMUAN tidak boleh tanpa keterangan.', 422);
        }

        [$nama, $namaId] = $this->petugas();

        try {
            DB::transaction(function () use ($sub, $data, $jenis, $nama, $namaId) {
                $ada = DB::table('N_WEB_CAREERS_Verifikasi_Latar')
                    ->where('Lamaran_Tahap_Tes_Id', $sub->Id_Lamaran_Tahap_Tes)
                    ->where('Jenis_Kode', $jenis->Kode)
                    ->first();

                $isi = [
                    'Status' => $data['status'],
                    'Tingkat_Temuan' => $data['status'] === 'TEMUAN' ? ($data['tingkatTemuan'] ?? 'RENDAH') : null,
                    'Sumber' => $data['sumber'] ?? null,
                    'Vendor_Ref' => $data['vendorRef'] ?? null,
                    'Ringkasan' => $data['ringkasan'] ?? null,
                    'Tanggal_Mulai' => $data['tanggalMulai'] ?? null,
                    'Tanggal_Selesai' => $data['tanggalSelesai'] ?? null,
                    'Petugas' => $nama,
                    'Petugas_Id' => $namaId,
                    'Updated_At' => now(),
                    'Updated_By' => $nama,
                    'Updated_By_Id' => $namaId,
                ];

                if ($ada) {
                    DB::table('N_WEB_CAREERS_Verifikasi_Latar')
                        ->where('Id_Verifikasi_Latar', $ada->Id_Verifikasi_Latar)
                        ->update($isi);

                    return;
                }

                DB::table('N_WEB_CAREERS_Verifikasi_Latar')->insert($isi + [
                    'Lamaran_Id' => self::lamaranDari($sub),
                    'Lamaran_Tahap_Id' => $sub->Lamaran_Tahap_Id,
                    'Lamaran_Tahap_Tes_Id' => $sub->Id_Lamaran_Tahap_Tes,
                    'Master_Jenis_Verifikasi_Id' => $jenis->Id_Master_Jenis_Verifikasi,
                    // Kode & nama DIBEKUKAN — master boleh berubah, sejarah tidak.
                    'Jenis_Kode' => $jenis->Kode,
                    'Jenis_Nama' => $jenis->Nama,
                    'Created_At' => now(),
                    'Created_By' => $nama,
                    'Created_By_Id' => $namaId,
                ]);
            });

            Pemeriksaan::hitungRingkas((int) $sub->Id_Lamaran_Tahap_Tes, $sub->Tipe_Tahap_Kode);
            Pemeriksaan::tulisRiwayat($sub, 'PERIKSA_'.$data['status'], sprintf(
                '%s: %s%s',
                $jenis->Nama,
                $data['status'],
                $data['status'] === 'TEMUAN' ? ' ('.($data['tingkatTemuan'] ?? 'RENDAH').')' : '',
            ));

            return ResponseHelper::success(
                // Penuh: yang barusan mengetiknya jelas berhak membacanya kembali.
                Pemeriksaan::bentuk($this->segar($sub), true),
                'Komponen “'.$jenis->Nama.'” disimpan.'
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[PEMERIKSAAN] gagal simpan komponen: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan komponen pemeriksaan.', 500);
        }
    }

    /** DELETE .../sub-tes/{id}/verifikasi/{jenisKode} — buang satu komponen. */
    public function hapusKomponen(string $id, string $jenisKode)
    {
        [$sub, $galat] = $this->aktivitas($id);
        if ($galat) {
            return ResponseHelper::error($galat, 422);
        }

        DB::table('N_WEB_CAREERS_Verifikasi_Latar')
            ->where('Lamaran_Tahap_Tes_Id', $sub->Id_Lamaran_Tahap_Tes)
            ->where('Jenis_Kode', $jenisKode)
            ->delete();

        Pemeriksaan::hitungRingkas((int) $sub->Id_Lamaran_Tahap_Tes, $sub->Tipe_Tahap_Kode);

        Pemeriksaan::tulisRiwayat($sub, 'PERIKSA_HAPUS', 'Komponen '.$jenisKode.' dibuang.');

        return ResponseHelper::success(Pemeriksaan::bentuk($this->segar($sub), true), 'Komponen dibuang.');
    }

    /**
     * POST/PATCH .../sub-tes/{id}/referensi — satu narasumber.
     *
     * `refId` kosong berarti menambah; berisi berarti menyunting yang sudah ada.
     */
    public function simpanReferensi(Request $request, string $id)
    {
        [$sub, $galat] = $this->aktivitas($id);
        if ($galat) {
            return ResponseHelper::error($galat, 422);
        }

        $data = $request->validate([
            'refId' => 'nullable|integer',
            'nama' => 'required|string|max:150',
            'jabatan' => 'nullable|string|max:150',
            'perusahaan' => 'nullable|string|max:200',
            'hubungan' => ['nullable', Rule::in(Pemeriksaan::HUBUNGAN)],
            'periodeKerja' => 'nullable|string|max:100',
            'kontakTelp' => 'nullable|string|max:40',
            'kontakEmail' => 'nullable|email|max:150',
            'izinHubungi' => 'nullable|boolean',
            'statusKontak' => ['required', Rule::in(Pemeriksaan::STATUS_KONTAK)],
            'percobaan' => 'nullable|integer|min:0|max:99',
            'tanggalKontak' => 'nullable|date',
            'metode' => ['nullable', Rule::in(Pemeriksaan::METODE)],
            'rekomendasi' => ['nullable', Rule::in(Pemeriksaan::REKOMENDASI)],
            'ringkasan' => 'nullable|string|max:1000',
        ]);

        // NARASUMBER YANG TERHUBUNG HARUS MENINGGALKAN KETERANGAN.
        //
        // Percakapan yang sudah terjadi tapi tak tercatat sama saja dengan yang
        // tak pernah terjadi — kecuali ia meninggalkan kesan di kepala satu
        // orang, dan kesan itulah yang kemudian dipakai memutuskan.
        if ($data['statusKontak'] === 'TERHUBUNG' && trim((string) ($data['ringkasan'] ?? '')) === '') {
            return ResponseHelper::error('Narasumber sudah terhubung — tuliskan ringkasan keterangannya.', 422);
        }

        [$nama, $namaId] = $this->petugas();

        $isi = [
            'Nama' => $data['nama'],
            'Jabatan' => $data['jabatan'] ?? null,
            'Perusahaan' => $data['perusahaan'] ?? null,
            'Hubungan' => $data['hubungan'] ?? null,
            'Periode_Kerja' => $data['periodeKerja'] ?? null,
            'Kontak_Telp' => $data['kontakTelp'] ?? null,
            'Kontak_Email' => $data['kontakEmail'] ?? null,
            'Izin_Hubungi' => ! empty($data['izinHubungi']) ? 'Y' : 'T',
            'Status_Kontak' => $data['statusKontak'],
            'Percobaan' => (int) ($data['percobaan'] ?? 0),
            'Tanggal_Kontak' => $data['tanggalKontak'] ?? null,
            'Metode' => $data['metode'] ?? null,
            'Rekomendasi' => $data['rekomendasi'] ?? null,
            'Ringkasan' => $data['ringkasan'] ?? null,
            'Petugas' => $nama,
            'Petugas_Id' => $namaId,
            'Updated_At' => now(),
            'Updated_By' => $nama,
            'Updated_By_Id' => $namaId,
        ];

        try {
            if (! empty($data['refId'])) {
                $terpengaruh = DB::table('N_WEB_CAREERS_Referensi_Kandidat')
                    ->where('Id_Referensi_Kandidat', $data['refId'])
                    // Kunci ke aktivitasnya: id dari luar tidak boleh menyunting
                    // narasumber milik lamaran orang lain.
                    ->where('Lamaran_Tahap_Tes_Id', $sub->Id_Lamaran_Tahap_Tes)
                    ->update($isi);

                if (! $terpengaruh) {
                    return ResponseHelper::error('Narasumber tidak ditemukan pada aktivitas ini.', 404);
                }
            } else {
                DB::table('N_WEB_CAREERS_Referensi_Kandidat')->insert($isi + [
                    'Lamaran_Id' => self::lamaranDari($sub),
                    'Lamaran_Tahap_Id' => $sub->Lamaran_Tahap_Id,
                    'Lamaran_Tahap_Tes_Id' => $sub->Id_Lamaran_Tahap_Tes,
                    'Created_At' => now(),
                    'Created_By' => $nama,
                    'Created_By_Id' => $namaId,
                ]);
            }

            Pemeriksaan::hitungRingkas((int) $sub->Id_Lamaran_Tahap_Tes, $sub->Tipe_Tahap_Kode);
            Pemeriksaan::tulisRiwayat($sub, 'NARASUMBER', sprintf(
                '%s (%s)%s',
                $data['nama'],
                $data['statusKontak'],
                ! empty($data['rekomendasi']) ? ' — pekerjakan kembali: '.$data['rekomendasi'] : '',
            ));

            return ResponseHelper::success(Pemeriksaan::bentuk($this->segar($sub), true), 'Narasumber disimpan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[PEMERIKSAAN] gagal simpan narasumber: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan narasumber.', 500);
        }
    }

    /** DELETE .../sub-tes/{id}/referensi/{refId} */
    public function hapusReferensi(string $id, int $refId)
    {
        [$sub, $galat] = $this->aktivitas($id);
        if ($galat) {
            return ResponseHelper::error($galat, 422);
        }

        DB::table('N_WEB_CAREERS_Referensi_Kandidat')
            ->where('Id_Referensi_Kandidat', $refId)
            ->where('Lamaran_Tahap_Tes_Id', $sub->Id_Lamaran_Tahap_Tes)
            ->delete();

        Pemeriksaan::hitungRingkas((int) $sub->Id_Lamaran_Tahap_Tes, $sub->Tipe_Tahap_Kode);

        Pemeriksaan::tulisRiwayat($sub, 'NARASUMBER_HAPUS', 'Satu narasumber dibuang dari daftar.');

        return ResponseHelper::success(Pemeriksaan::bentuk($this->segar($sub), true), 'Narasumber dibuang.');
    }

    /**
     * Id lamaran dari aktivitasnya — hanya dipakai bila baris aktivitas tidak
     * membawanya sendiri (tabelnya menyimpan Lamaran_Tahap_Id, bukan Lamaran_Id).
     */
    private static function lamaranDari(object $sub): int
    {
        return (int) (DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)
            ->value('Lamaran_Id') ?? 0);
    }
}
