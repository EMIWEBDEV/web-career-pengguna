<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Services\WebCareers\HclClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREERS — PENERBITAN TOKEN UJIAN KE HCLEARN (latar belakang).
 *
 * KENAPA DIANTREKAN
 * Menekan "Generate" untuk 200 kandidat berarti satu permintaan HTTP menunggu
 * HCLearn membuatkan 200 token. Permintaannya habis waktu, admin menekan ulang,
 * dan lahirlah penjadwalan ganda. Sejak sekarang baris penjadwalan disimpan
 * lebih dulu (status DIANTRIKAN), lalu penerbitan tokennya dikerjakan job ini.
 *
 * KONTRAK STATUS — dibaca layar Daftar Penjadwalan:
 *   DIANTRIKAN  baris sudah ada, token belum terbit (atau baru sebagian);
 *   BERJALAN    minimal satu token terbit;
 *   GAGAL       tak satu pun terbit. Barisnya SENGAJA tidak dihapus supaya
 *               admin tahu apa yang terjadi; tautan kandidatnya dilepas agar
 *               mereka kembali muncul di antrean "menunggu jadwal".
 *
 * Idempoten: peserta yang tokennya sudah terbit dilewati, jadi percobaan ulang
 * setelah gagal separuh tidak pernah menerbitkan token dobel.
 *
 * ══ KENAPA DIKIRIM SEPOTONG-SEPOTONG ═════════════════════════════════════
 *
 * CAT membelah perlakuannya di angka 25 (HclPenjadwalanService::BATAS_SINKRON).
 * Sampai batas itu ia menjawab dengan token lengkap. Di ATASNYA ia menitipkan
 * pekerjaan ke antreannya sendiri, membalas `mode: ANTRIAN`, dan menaruh
 * hasilnya HANYA di cache-nya — cache file, di Cloud Run, milik satu instance.
 * Instance lain yang ditanya lewat Url_Status menjawab "batch tidak ditemukan".
 *
 * Akibatnya bukan galat, melainkan diam: token terbit di CAT, tak pernah
 * sampai ke kita, dan kandidatnya membaca "menunggu token HCLearn" selamanya.
 * JDW-0010 — 96 peserta, 96 token yatim — lahir dari jalur itu.
 *
 * Maka jalur itu tidak dimasuki. Kiriman dipecah ke bawah batas sinkron,
 * sehingga CAT selalu menjawab dengan token. Berapa pun kandidatnya, yang
 * bertambah cuma banyaknya potongan.
 *
 * Tiga sifat yang membuatnya tahan banting:
 *   1. Tiap potongan LANGSUNG diserap dan kandidatnya langsung ditautkan.
 *      Mati di potongan ke-30 dari 50 berarti 600 orang sudah punya token,
 *      bukan nol.
 *   2. Penyaringnya `whereNull('Short_Token')`, jadi percobaan ulang hanya
 *      mengirim yang memang belum punya.
 *   3. Lewat anggaran waktu, sisanya diserahkan ke job lanjutan alih-alih
 *      menabrak $timeout. Yang tumbuh jumlah job, bukan durasi satu job —
 *      itulah sebabnya 1000 kandidat tidak lagi berarti 1000 antrean.
 *
 * Nama antrean `wc-penjadwalanworker` harus ada di Cloud Tasks:
 *     gcloud tasks queues create wc-penjadwalanworker
 * Di lokal cukup `php artisan queue:work` (lihat AntreanWebCareers).
 */
class WcPenjadwalanJob implements ShouldQueue
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-penjadwalanworker';

    /** Menerbitkan ratusan token bisa lama; beri ruang sebelum dianggap mati. */
    public $timeout = 900;

    /** HCLearn sesekali tersendat — dicoba ulang, dengan jeda menaik. */
    public $tries = 3;

    public $backoff = [30, 120];

    public function __construct(
        private int $penjadwalanId,
        private int $penjadwalanTahapId,
        // Pengenal paket dari HCLearn: STRING terenkripsi, bukan angka.
        private string $idMasterUjian,
        private string $waktuMulai,
        private string $waktuAkhir,
        /**
         * Admin yang meminta penjadwalan ini.
         *
         * Job berjalan tanpa sesi, sementara HCLearn menuntut identitas ORANG
         * untuk menentukan ujian mana yang boleh dijadwalkan. Tanpa ini,
         * penjadwalan selalu ditolak "Akun Anda belum ditautkan" — bahkan untuk
         * akun yang sudah ditautkan.
         *
         * Yang dibawa ID-nya, bukan kuncinya: kunci tidak ikut mengendap di
         * tabel antrean, dan pencabutan tautan langsung berlaku bahkan untuk
         * job yang sudah telanjur mengantre.
         */
        private ?int $dimintaOlehId = null,
        /**
         * Kursor: peserta yang ditangani job ini adalah yang ber-id DI ATAS
         * nilai ini. Selalu 0 untuk kiriman pertama.
         *
         * Dipakai job lanjutan saat anggaran waktu habis. Kursor, bukan daftar
         * id: daftar ikut mengendap di tabel antrean dan membengkak seiring
         * jumlah peserta, sementara kursor tetap satu angka berapa pun besarnya.
         *
         * Ia juga yang mencegah putaran tak berujung. Penyaring utamanya
         * `whereNull('Short_Token')`, jadi potongan yang SELURUH pesertanya
         * gagal akan terambil lagi persis sama pada putaran berikutnya —
         * selamanya. Kursor yang selalu maju memastikan setiap peserta dicoba
         * tepat sekali per jalannya job.
         */
        private int $mulaiDariId = 0,
        /**
         * Batasi job ini pada SATU peserta saja.
         *
         * Dipakai tombol "Coba lagi" di panel antrean, yang menyasar satu orang
         * yang gagal — bukan seluruh gelombang. Tanpa pembatas ini, mencoba
         * ulang satu nama akan ikut mengirim ulang semua yang masih tertunda,
         * dan admin yang sengaja menunda sisanya kehilangan kendali itu.
         *
         * Yang TIDAK berubah: penyaring `whereNull('Short_Token')` tetap
         * berlaku, jadi ia tidak pernah bisa menerbitkan token dobel. Dan
         * tutupBuku() tetap menghitung SELURUH peserta tahap, sehingga status
         * gelombang tidak ikut jatuh ke GAGAL hanya karena satu percobaan
         * ulang yang kebetulan gagal lagi.
         */
        private ?int $hanyaPesertaId = null,
    ) {
        $this->aturAntrean(self::QUEUE);
    }

    /**
     * Galat dari CAT → kalimat yang berguna bagi admin.
     *
     * Yang tampil di layar penjadwalan sebelumnya adalah lemparan mentah SQL
     * Server milik sistem lain — lengkap dengan nama constraint acak dan
     * potongan perintah INSERT. Bagi orang yang menjadwalkan tes, itu bukan
     * keterangan apa pun: ia tidak tahu apa yang salah, apalagi apa yang harus
     * dilakukan. Yang teknis tetap disimpan utuh di log.
     *
     * Sengaja mengenali POLA, bukan mencocokkan nama constraint: nama seperti
     * `UQ__N_HRIS_K__CA0EF675D95DB9EF` dibuat otomatis SQL Server dan berbeda
     * di tiap lingkungan.
     */
    private static function pesanRamah(string $asli): string
    {
        $l = strtolower($asli);

        if (str_contains($l, 'duplicate key') && str_contains($l, 'id_wc_penjadwalan_peserta')) {
            return 'CAT menolak karena pengenal peserta sudah terpakai di sana. '
                .'Coba jadwalkan ulang — sistem akan memakai pengenal baru. '
                .'Bila tetap gagal, laporkan ke tim CAT (rincian teknis ada di log).';
        }

        if (str_contains($l, 'duplicate key')) {
            return 'CAT menolak karena datanya dianggap ganda. Coba jadwalkan ulang; bila tetap gagal, laporkan ke tim CAT (rincian teknis ada di log).';
        }

        if (str_contains($l, 'request expired')) {
            // Sudah pernah memakan waktu berjam-jam untuk didiagnosis.
            return 'CAT menolak karena selisih jam server. Samakan waktu mesin ini dengan CAT (maksimal 60 detik), lalu coba lagi.';
        }

        if (str_contains($l, 'master ujian tidak ditemukan')) {
            return 'Paket ujian tidak dikenali CAT. Pilih ulang paket tesnya di layar penjadwalan.';
        }

        // Galat yang belum dikenali dikirim apa adanya — menyembunyikannya di
        // balik "terjadi kesalahan" justru menghapus satu-satunya petunjuk.
        return $asli;
    }

    public function handle(HclClient $hcl): void
    {
        $penjadwalan = DB::table('N_WEB_CAREERS_Penjadwalan')
            ->where('Id_Penjadwalan', $this->penjadwalanId)->first();

        // Penjadwalannya sudah dibatalkan/dihapus sementara job menunggu giliran.
        if (! $penjadwalan) {
            Log::channel('web_career')->info("[ANTREAN] Penjadwalan #{$this->penjadwalanId} sudah tidak ada — job dilewati.");

            return;
        }

        $tahap = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
            ->where('Id_Penjadwalan_Tahap', $this->penjadwalanTahapId)->first();
        $program = DB::table('N_WEB_CAREERS_Program')
            ->where('Id_Program', $penjadwalan->Program_Id)->first();

        $ukuran = (int) config('hclearn.chunk_peserta', 20);
        $jedaMs = (int) config('hclearn.chunk_jeda_ms', 150);
        $anggaran = (int) config('hclearn.chunk_batas_detik', 600);

        // Dihitung sekali di muka supaya catatan kemajuan tidak menambah kueri
        // pada tiap potongan.
        $total = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)->count();
        $terbit = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)
            ->whereNotNull('Short_Token')->count();

        $mulaiJob = microtime(true);
        $kursor = $this->mulaiDariId;
        $adaKiriman = false;
        $pesanGagal = null;

        while (($peserta = $this->pesertaTertunda($kursor, $ukuran))->isNotEmpty()) {
            // ANGGARAN WAKTU HABIS — sisanya diserahkan, bukan dipaksakan.
            //
            // Diperiksa SETELAH ada yang terkirim, supaya tiap job dijamin maju
            // minimal satu potongan. Tanpa syarat itu, anggaran yang kebetulan
            // disetel terlalu kecil melahirkan rantai job yang saling melempar
            // pekerjaan tanpa satu token pun terbit.
            if ($adaKiriman && (microtime(true) - $mulaiJob) >= $anggaran) {
                $this->serahkanSisanya($kursor, $terbit, $total);

                return;
            }

            $peserta = $this->berinomorCat($peserta);

            $hasil = $hcl->sebagaiPengguna($this->dimintaOlehId)->post(
                'penjadwalan',
                $this->muatan($penjadwalan, $tahap, $program, $peserta),
                [
                    'Jenis_Event' => 'PENJADWALAN_UJIAN',
                    'Penjadwalan_Id' => $this->penjadwalanId,
                    'Penjadwalan_Tahap_Id' => $this->penjadwalanTahapId,
                ]
            );

            $adaKiriman = true;
            // Kursor maju lebih dulu: apa pun hasilnya, potongan ini sudah
            // dicoba dan tidak boleh terambil lagi di putaran berikutnya.
            $kursor = (int) $peserta->max('Id_Penjadwalan_Peserta');

            if (! $hasil['sukses']) {
                // Ditolak mentah. Dilempar supaya antrean mencoba ulang —
                // dan percobaan ulang itu hanya akan mengirim yang belum punya
                // token, jadi potongan yang telanjur berhasil tetap aman.
                $sejauhIni = $terbit > 0 ? " ({$terbit} dari {$total} token sudah terbit sebelum ini)" : '';

                DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $this->penjadwalanTahapId)
                    ->update([
                        'Status' => $terbit > 0 ? 'SEBAGIAN' : 'GAGAL',
                        'Pesan_Error' => substr(self::pesanRamah($hasil['message']).$sejauhIni, 0, 480),
                        'Updated_At' => now(),
                    ]);

                // Log menyimpan pesan ASLI — yang dirapikan hanya yang dibaca admin.
                Log::channel('web_career')->error('[PENJADWALAN] HCLearn menolak: '.$hasil['message']);

                throw new \RuntimeException('HCLearn menolak permintaan: '.$hasil['message']);
            }

            $res = $hasil['result'] ?? [];

            // GERBANG ANTI-KEJUT.
            //
            // Potongan kita dibuat di bawah batas sinkron CAT, jadi balasan ini
            // seharusnya mustahil. Kalau tetap muncul, artinya CAT menurunkan
            // batasnya — dan diam-diam mengikuti jalur antrean berarti mengulang
            // persis kegagalan JDW-0010: token terbit di sana, tak pernah sampai
            // ke sini, kandidat menggantung tanpa satu galat pun.
            //
            // Maka dihentikan dengan keras, dan pesannya menyebut jalan keluarnya.
            if (($res['mode'] ?? null) === 'ANTRIAN') {
                $pesan = 'HCLearn memproses lewat antreannya sendiri padahal kiriman ini hanya '
                    .$peserta->count().' peserta — berarti batas sinkronnya sudah diturunkan. '
                    .'Hasil jalur itu tidak pernah sampai ke Web Careers. '
                    .'Turunkan HCLEARN_CHUNK_PESERTA di bawah batas baru CAT, lalu tekan Coba Lagi.';

                DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $this->penjadwalanTahapId)
                    ->update([
                        'Status' => $terbit > 0 ? 'SEBAGIAN' : 'GAGAL',
                        'Pesan_Error' => substr($pesan, 0, 480),
                        'Updated_At' => now(),
                    ]);

                Log::channel('web_career')->error('[PENJADWALAN] '.$pesan);

                throw new \RuntimeException($pesan);
            }

            [$s, $g, $p] = app(\App\Http\Controllers\Career\Penjadwalan\PenjadwalanController::class)
                ->serapBalasanHclearn($res, $peserta);

            $terbit += $s;
            $pesanGagal ??= $p;

            // TAUTAN DIPASANG PER POTONGAN, bukan di akhir.
            //
            // Kandidat potongan pertama sudah bisa membuka tesnya sementara
            // potongan terakhir masih dikirim. Hanya id potongan ini yang
            // dilewatkan: memasang ulang seluruh daftar pada tiap putaran
            // membuat ongkosnya tumbuh kuadratik seiring jumlah peserta.
            $this->pasangTautanKandidat($peserta->pluck('Lamaran_Id')->filter()->unique()->all());

            $this->catatKemajuan($terbit, $total);

            Log::channel('web_career')->info(
                "[ANTREAN] {$penjadwalan->Kode} potongan: {$s} terbit, {$g} gagal — {$terbit}/{$total}."
            );

            if ($jedaMs > 0) {
                usleep($jedaMs * 1000);
            }
        }

        $this->tutupBuku($penjadwalan, $pesanGagal);
    }

    /**
     * Potongan peserta berikutnya yang tokennya BELUM terbit.
     *
     * Dua penyaring, dua tugas berbeda. `whereNull('Short_Token')` yang membuat
     * percobaan ulang aman — yang sudah berhasil tidak pernah dikirim dua kali.
     * Kursor `> $setelahId` yang membuat PUTARAN INI berhenti: tanpa dia,
     * potongan yang seluruh pesertanya gagal akan terambil lagi persis sama,
     * selamanya.
     */
    private function pesertaTertunda(int $setelahId, int $ukuran): \Illuminate\Support\Collection
    {
        return DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)
            ->whereNull('Short_Token')
            ->where('Id_Penjadwalan_Peserta', '>', $setelahId)
            // Percobaan ulang satu orang: yang lain tidak ikut terkirim.
            ->when($this->hanyaPesertaId, fn ($q) => $q->where('Id_Penjadwalan_Peserta', $this->hanyaPesertaId))
            ->orderBy('Id_Penjadwalan_Peserta')
            ->limit($ukuran)
            ->get();
    }

    /**
     * PENGENAL UNTUK CAT DISIAPKAN DI SINI, tepat sebelum dikirim.
     *
     * CAT memakai `Id_WC_Penjadwalan_Peserta` sebagai KUNCI UNIK di tabelnya.
     * Dulu kita mengirim kolom IDENTITY kita sendiri — dan IDENTITY bisa
     * TERULANG setelah tabel peserta di-reset, sehingga peserta baru
     * bertabrakan dengan peserta angkatan lama yang masih tersimpan di CAT.
     * Galatnya muncul sebagai "Violation of UNIQUE KEY" mentah, dan sudah dua
     * kali terjadi.
     *
     * Nomornya kini dari SEQUENCE, yang tidak tersentuh TRUNCATE maupun reseed
     * — jadi tidak pernah mundur, apa pun yang terjadi pada tabel.
     *
     * Diberikan hanya kepada yang BELUM punya: percobaan ulang atas kiriman
     * yang gagal memakai nomor yang sama, karena barisnya memang tidak pernah
     * berhasil masuk ke CAT.
     */
    private function berinomorCat(\Illuminate\Support\Collection $peserta): \Illuminate\Support\Collection
    {
        return $peserta->map(function ($p) {
            if ($p->Ref_Cat_Peserta === null) {
                $p->Ref_Cat_Peserta = DB::selectOne(
                    'SELECT NEXT VALUE FOR SEQ_WC_Penjadwalan_Peserta_Cat AS n'
                )->n;

                DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                    ->where('Id_Penjadwalan_Peserta', $p->Id_Penjadwalan_Peserta)
                    ->update(['Ref_Cat_Peserta' => $p->Ref_Cat_Peserta, 'Updated_At' => now()]);
            }

            return $p;
        });
    }

    /** Muatan satu potongan — bentuknya sama persis, berapa pun potongannya. */
    private function muatan(object $penjadwalan, ?object $tahap, ?object $program, \Illuminate\Support\Collection $peserta): array
    {
        return [
            'Kode_WC_Penjadwalan' => $penjadwalan->Kode,
            'Nama_Penjadwalan' => $penjadwalan->Nama,
            'Id_WC_Penjadwalan' => $this->penjadwalanId,
            'Id_WC_Penjadwalan_Tahap' => $this->penjadwalanTahapId,
            'Id_WC_Program' => $penjadwalan->Program_Id,
            'Nama_Program' => $program->Nama ?? '',
            'Kode_WC_Tahap' => $tahap->Kode ?? '',
            'Label_Tahap' => $tahap->Label ?? '',
            'Urutan_WC_Tahap' => (int) ($tahap->Urutan ?? 0),
            'Total_WC_Tahap' => (int) $penjadwalan->Jumlah_Tahap,
            'Id_Master_Ujian' => $this->idMasterUjian,
            'Waktu_Mulai' => $this->waktuMulai,
            'Waktu_Akhir' => $this->waktuAkhir,
            // KAMERA WAJIB dipaksa dari sisi Web Careers agar CAT tak pernah
            // menjalankan sesi tanpa kamera.
            config('hclearn.kamera_field', 'Flag_Camera') => (bool) config('hclearn.wajib_kamera', true),
            'Url_Callback' => config('hclearn.public_url').'/'.ltrim(config('hclearn.callback_path'), '/'),
            'peserta' => $peserta->map(fn ($p) => [
                // Nomor SEQUENCE, bukan IDENTITY kita — lihat penjelasan di atas.
                'Id_WC_Penjadwalan_Peserta' => (int) $p->Ref_Cat_Peserta,
                'Kode_Peserta' => $p->Kode_Peserta,
                'Jenis_User' => 'eksternal',
                'Id_WC_Users' => $p->Users_Id,
                'Id_WC_Lamaran' => $p->Lamaran_Id,
                'Nama' => $p->Nama,
                'Email' => $p->Email,
                'No_Hp' => $p->No_Hp,
                'Posisi_Dilamar' => $p->Posisi_Dilamar,
                // KE MANA KANDIDAT PULANG setelah ujiannya selesai.
                //
                // Dipakai CAT untuk DUA jalan keluar sekaligus: tombol "Selesai
                // & Keluar" di Berita Acara, dan hitung mundur 60 detik yang
                // habis. Tanpa ini keduanya bermuara ke halaman akses HCLearn —
                // halaman yang bukan milik kandidat kita, tanpa jalan balik.
                //
                // Dikirim PER PESERTA, bukan per penjadwalan: alamatnya memuat
                // id lamaran, dan satu penjadwalan berisi banyak lamaran.
                //
                // Basisnya `hclearn.public_url`, BUKAN app.url: itulah alamat
                // yang memang sudah ditetapkan sebagai "cara dunia luar
                // menjangkau kami" untuk integrasi ini — sama dengan yang
                // dipakai Url_Callback, jadi keduanya tak bisa lagi menyimpang.
                //
                // `?dari=tes` adalah penanda ASAL, bukan hiasan: halaman lamaran
                // memakainya untuk menyambut kandidat yang baru pulang dari tes
                // dan menunggu hasilnya masuk — lihat LamaranDetail.vue.
                'Url_Kembali' => $p->Lamaran_Id
                    ? rtrim(config('hclearn.public_url'), '/')
                        .'/kandidat/lamaran/'.Hashids::encode($p->Lamaran_Id).'?dari=tes'
                    : null,
            ])->values()->all(),
        ];
    }

    /**
     * Kabar kemajuan yang bisa dibaca admin sambil menunggu.
     *
     * Penjadwalan besar berjalan menit-menitan. Tanpa ini layarnya cuma
     * berbunyi "DIANTRIKAN" dari awal sampai akhir — tak terbedakan dari
     * penjadwalan yang macet, dan itulah yang membuat admin menekan Generate
     * untuk kedua kalinya.
     */
    private function catatKemajuan(int $terbit, int $total): void
    {
        DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $this->penjadwalanId)
            ->update([
                'Status' => 'DIANTRIKAN',
                'Catatan' => "Menerbitkan token — {$terbit} dari {$total} kandidat selesai.",
                'Updated_At' => now(),
            ]);
    }

    /**
     * Anggaran waktu habis: sisanya diteruskan ke job berikutnya.
     *
     * Bukan pembagian kerja, melainkan penyambungan: satu job aktif per
     * penjadwalan, tidak peduli pesertanya seratus atau seribu. Antrean tidak
     * pernah dibanjiri, dan tak ada satu job pun yang perlu hidup lebih lama
     * dari $timeout-nya.
     */
    private function serahkanSisanya(int $kursor, int $terbit, int $total): void
    {
        self::dispatch(
            $this->penjadwalanId,
            $this->penjadwalanTahapId,
            $this->idMasterUjian,
            $this->waktuMulai,
            $this->waktuAkhir,
            $this->dimintaOlehId,
            $kursor,
            // Cakupannya ikut diwariskan. Tanpa ini, percobaan ulang satu orang
            // yang kebetulan menabrak anggaran waktu akan dilanjutkan job
            // berikutnya sebagai kiriman SELURUH peserta yang tertunda.
            $this->hanyaPesertaId,
        );

        DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $this->penjadwalanId)
            ->update([
                'Status' => 'DIANTRIKAN',
                'Catatan' => "Menerbitkan token — {$terbit} dari {$total} kandidat selesai, sisanya dilanjutkan.",
                'Updated_At' => now(),
            ]);

        Log::channel('web_career')->info(
            "[ANTREAN] Penjadwalan #{$this->penjadwalanId} dilanjutkan job berikutnya dari peserta #{$kursor} ({$terbit}/{$total})."
        );
    }

    /**
     * Semua potongan selesai — status disimpulkan dari KENYATAAN di tabel.
     *
     * Dihitung ulang dari database, bukan dari penjumlahan selama job ini
     * berjalan: pekerjaannya bisa terbagi ke beberapa job berantai, dan job
     * terakhir hanya tahu jatahnya sendiri. Yang menentukan status adalah
     * berapa token yang benar-benar ada, bukan berapa yang job ini terbitkan.
     */
    private function tutupBuku(object $penjadwalan, ?string $pesanGagal): void
    {
        $dasar = fn () => DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId);

        $total = $dasar()->count();

        // Tidak ada pesertanya sama sekali — tak ada yang bisa disimpulkan.
        if ($total === 0) {
            $this->rapikanStatus($pesanGagal);

            return;
        }

        $terbit = $dasar()->whereNotNull('Short_Token')->count();
        $gagal = $total - $terbit;

        // Pesan dari job SEBELUMNYA di rantai yang sama tidak dibawa dalam
        // memori — diambil kembali dari baris pesertanya.
        if (! $pesanGagal && $gagal > 0) {
            $pesanGagal = $dasar()->whereNull('Short_Token')
                ->whereNotNull('Pesan_Error')
                ->value('Pesan_Error');
        }

        DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $this->penjadwalanTahapId)
            ->update([
                'Status' => $gagal === 0 ? 'TERKIRIM' : ($terbit > 0 ? 'SEBAGIAN' : 'GAGAL'),
                'Pesan_Error' => $pesanGagal ? substr($pesanGagal, 0, 480) : null,
                'Waktu_Kirim' => now(), 'Updated_At' => now(),
            ]);

        $this->rapikanStatus($pesanGagal);

        Log::channel('web_career')->info("[ANTREAN] {$penjadwalan->Kode}: {$terbit} token terbit, {$gagal} gagal (dari {$total}).");
    }

    /**
     * Pasang (atau pasang ulang) tautan tahap lamaran kandidat → penjadwalan ini.
     *
     * Inilah yang membuat portal kandidat menampilkan token, OTP, dan jendela
     * waktu tesnya. Aman dipanggil berulang: hanya menyentuh peserta yang
     * tokennya BENAR-BENAR terbit, dan hanya aktivitas yang belum selesai.
     *
     * Aktivitasnya ditentukan `Penjadwalan_Tahap.Tes_Urutan` — bukan ditebak.
     * Satu tahap bisa memuat Psikotes 1, Psikotes 2, dan DISC yang dijadwalkan
     * sendiri-sendiri; menebak berarti kandidat menerima token untuk aktivitas
     * yang salah, dan itu jauh lebih sulit disadari daripada tidak ada token.
     *
     * @param  int[]|null  $hanyaLamaran  batasi pada lamaran ini saja — dipakai
     *                                    setelah tiap potongan, supaya ongkosnya
     *                                    tidak tumbuh kuadratik saat pesertanya
     *                                    ratusan. null = seluruh yang tokennya
     *                                    sudah terbit (jalur pemulihan).
     */
    /**
     * Kolom identitas aktivitas sudah ada di Penjadwalan_Tahap?
     *
     * Cerminan PenjadwalanController::punyaKolomIdentitasTes(). Skrip SQL-nya
     * dijalankan manual per lingkungan; selama belum, job ini tidak boleh gagal
     * — ia cuma kembali memakai nomor urut seperti sebelumnya.
     */
    private static function punyaKolomIdentitasTes(): bool
    {
        static $ada = null;

        try {
            return $ada ??= Schema::hasColumn('N_WEB_CAREERS_Penjadwalan_Tahap', 'Master_Alur_Tahap_Tes_Id');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function pasangTautanKandidat(?array $hanyaLamaran = null): void
    {
        // ── CERMIN PERSIS DARI store() ──────────────────────────────────────
        //
        // Dua kolom ini adalah SALINAN dari apa yang dipakai
        // PenjadwalanController::store() saat menandai snapshot kandidat:
        // Master_Alur_Tahap_Tes_Id bila kolomnya ada, kalau tidak Tes_Urutan.
        // Keduanya dibaca apa adanya, TIDAK diturunkan ulang dari master —
        // menurunkan berarti menjawab pertanyaan yang sama dengan sumber yang
        // berbeda, dan dua jawaban yang bisa berselisih hanya menunggu giliran
        // menautkan token ke aktivitas yang salah.
        //
        // Kolom identitas ditambahkan lewat skrip SQL opsional
        // (2026-08-13-penjadwalan-identitas-aktivitas.sql). Selama belum ada,
        // kedua sisi sama-sama memakai nomor urut — tetap sepasang, tetap benar.
        $adaKolomTes = self::punyaKolomIdentitasTes();

        $tahap = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
            ->where('Id_Penjadwalan_Tahap', $this->penjadwalanTahapId)
            ->first(array_merge(
                ['Urutan', 'Kode', 'Tes_Urutan'],
                $adaKolomTes ? ['Master_Alur_Tahap_Tes_Id'] : [],
            ));

        if (! $tahap) {
            return;
        }

        $tesIdMaster = $adaKolomTes ? ($tahap->Master_Alur_Tahap_Tes_Id ?? null) : null;

        // Hanya kandidat yang tokennya terbit. Peserta yang gagal tetap tidak
        // tertaut — portalnya jujur berbunyi "menunggu jadwal", karena memang
        // belum ada yang bisa ia kerjakan.
        $lamaranIds = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)
            ->whereNotNull('Short_Token')
            ->when($hanyaLamaran !== null, fn ($q) => $q->whereIn('Lamaran_Id', $hanyaLamaran ?: [0]))
            ->pluck('Lamaran_Id')
            ->filter()
            ->unique()
            ->values();

        if ($lamaranIds->isEmpty()) {
            return;
        }

        // Dipecah per 500: jalur pemulihan memasang ulang SELURUH peserta yang
        // tokennya sudah terbit, dan pada penjadwalan seribu orang daftar itu
        // melewati batas 2100 parameter milik SQL Server.
        // Penyaring tahap milik kandidat — Kode bila ada, nomor urut hanya untuk
        // baris pra-mesin yang memang tak punya Kode. Aturan yang sama dengan
        // PenjadwalanController::store(); nomor urut sendirian menunjuk tahap
        // yang lain begitu alur program disunting atau dialihkan.
        $kodeTahap = trim((string) ($tahap->Kode ?? '')) ?: null;
        $saringTahap = fn ($q) => $kodeTahap
            ? $q->where('Kode', $kodeTahap)
            : $q->where('Urutan', $tahap->Urutan);

        DB::transaction(function () use ($tesIdMaster, $tahap, $saringTahap, $lamaranIds) {
            $now = now();

            foreach ($lamaranIds->chunk(500) as $sepotong) {
                DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->whereIn('Lamaran_Id', $sepotong->all())
                    ->where($saringTahap)
                    ->whereNull('Penjadwalan_Tahap_Id')
                    ->update(['Penjadwalan_Tahap_Id' => $this->penjadwalanTahapId, 'Updated_At' => $now]);

                $tahapIds = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->whereIn('Lamaran_Id', $sepotong->all())
                    ->where($saringTahap)
                    ->pluck('Id_Lamaran_Tahap')
                    ->all();

                if (! $tahapIds) {
                    continue;
                }

                foreach (collect($tahapIds)->chunk(500) as $sepotongTahap) {
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                        ->whereIn('Lamaran_Tahap_Id', $sepotongTahap->all())
                        // IDENTITAS AKTIVITAS DULU, nomor urut cadangan.
                        //
                        // Nomor urut milik master, sedangkan baris yang disentuh
                        // di sini milik SNAPSHOT kandidat. Keduanya berselisih
                        // begitu urutan aktivitas di Master Alur disunting — dan
                        // yang tertaut token lalu aktivitas yang salah. Aturannya
                        // sama persis dengan PenjadwalanController::store().
                        ->when($tesIdMaster, fn ($q, $i) => $q->where(fn ($w) => $w
                            ->where('Master_Alur_Tahap_Tes_Id', $i)
                            ->when($tahap->Tes_Urutan, fn ($x, $u) => $x->orWhere(fn ($y) => $y
                                ->whereNull('Master_Alur_Tahap_Tes_Id')
                                ->where('Urutan', $u)))))
                        ->when(! $tesIdMaster && $tahap->Tes_Urutan, fn ($q) => $q->where('Urutan', $tahap->Tes_Urutan))
                        ->where('Flag_Selesai', 'N')
                        ->whereNull('Penjadwalan_Tahap_Id')
                        ->update([
                            'Status' => 'DIJADWALKAN',
                            'Penjadwalan_Tahap_Id' => $this->penjadwalanTahapId,
                            'Updated_At' => $now,
                        ]);
                }
            }
        });

        Log::channel('web_career')->info(
            '[ANTREAN] tautan kandidat dipasang untuk penjadwalan tahap #'.$this->penjadwalanTahapId
            .' ('.count($lamaranIds).' lamaran).'
        );
    }

    /**
     * Selaraskan status penjadwalan dengan kenyataan tokennya.
     *
     * Tak satu pun token terbit → GAGAL, dan tautan kandidat DILEPAS supaya
     * mereka kembali ke antrean "menunggu jadwal". Barisnya tidak dihapus:
     * penjadwalan yang lenyap tanpa jejak membuat admin menebak-nebak.
     */
    private function rapikanStatus(?string $pesanGagal = null): void
    {
        $terbit = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)
            ->whereNotNull('Short_Token')->count();

        if ($terbit > 0) {
            // TAUTAN KANDIDAT DIPASANG ULANG.
            //
            // Saat penerbitan token gagal, blok di bawah sengaja MELEPAS tautan
            // supaya kandidat kembali ke antrean "menunggu jadwal". Tapi tak ada
            // satu pun kode yang memasangnya kembali ketika penjadwalan yang
            // sama dicoba ulang dan kali ini berhasil — akibatnya token terbit,
            // admin melihat TERKIRIM, dan kandidat melihat "Menunggu
            // dijadwalkan" selamanya tanpa galat di mana pun.
            //
            // Dikerjakan di sini, bukan di tombol coba-ulang: antrean juga
            // mencoba sendiri saat job gagal, dan perbaikan yang hanya ada di
            // tombol tidak akan pernah berjalan pada jalur itu.
            $this->pasangTautanKandidat();

            DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $this->penjadwalanId)
                ->update(['Status' => 'BERJALAN', 'Catatan' => $pesanGagal ? substr($pesanGagal, 0, 480) : null, 'Updated_At' => now()]);

            return;
        }

        DB::transaction(function () use ($pesanGagal) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)
                ->where('Flag_Selesai', 'N')
                ->update(['Penjadwalan_Tahap_Id' => null, 'Status' => 'BELUM', 'Updated_At' => now()]);

            DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Penjadwalan_Tahap_Id', $this->penjadwalanTahapId)
                ->update(['Penjadwalan_Tahap_Id' => null, 'Updated_At' => now()]);

            DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $this->penjadwalanId)
                ->update([
                    'Status' => 'GAGAL',
                    'Catatan' => substr($pesanGagal ?: 'Tidak ada token yang berhasil diterbitkan HCLearn.', 0, 480),
                    'Updated_At' => now(),
                ]);
        });

        Log::channel('web_career')->warning("[ANTREAN] Penjadwalan #{$this->penjadwalanId} GAGAL total — kandidat dikembalikan ke antrean.");
    }

    /** Percobaan terakhir pun gagal: tandai jelas, jangan biarkan menggantung. */
    public function failed(\Throwable $e): void
    {
        Log::channel('web_career')->error("[ANTREAN] WcPenjadwalanJob #{$this->penjadwalanId} gagal total: ".$e->getMessage());
        $this->rapikanStatus($e->getMessage());
    }
}
