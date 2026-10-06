<?php

namespace App\Console\Commands;

use App\Services\Surat\SuratClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pemeriksa jalur ke EVO Mail Server — pengganti `karir:cek-smtp`.
 *
 * ══ KENAPA PENGGANTINYA BUKAN SEKADAR NAMA BARU ═══════════════════════════
 *
 * Yang dulu diperiksa adalah port SMTP: terbuka atau dijatuhkan, kredensialnya
 * diterima atau ditolak. Semua itu sudah bukan urusan layanan ini — port SMTP
 * tidak pernah lagi dibuka dari sini, dan kata sandinya tidak ada di sini.
 *
 * Yang kini menentukan sampai-tidaknya sebuah surat ada tiga, dan ketiganya
 * yang diperiksa perintah ini:
 *
 *   1. server suratnya menyala dan terjangkau
 *   2. kunci di .env memang dikenali, dan tanda tangannya cocok
 *   3. template yang dipanggil job memang diizinkan untuk kunci ini
 *
 * Nomor 3 yang paling sering terlewat, dan gejalanya paling menyesatkan:
 * empat dari lima surat berangkat normal, satu template dijawab 403, dan yang
 * tidak menerima suratnya cuma sebagian kandidat.
 *
 * ══ PEMAKAIAN ═════════════════════════════════════════════════════════════
 *
 *   php artisan karir:cek-surat
 *       Periksa saja — tidak ada surat yang berangkat.
 *
 *   php artisan karir:cek-surat --kirim=orang@contoh.com
 *       Kirim SATU surat contoh (verifikasi-email) ke alamat itu.
 *
 *   php artisan karir:cek-surat --kirim=orang@contoh.com --semua
 *       Kirim contoh SELURUH template, satu per satu — termasuk surat hasil
 *       lamaran lengkap dengan foto sematannya.
 */
class CekSurat extends Command
{
    protected $signature = 'karir:cek-surat
        {--kirim= : alamat tujuan surat contoh (kosong = tidak mengirim apa pun)}
        {--template= : kirim satu template ini saja}
        {--semua : kirim contoh untuk SEMUA template}';

    protected $description = 'Periksa jalur ke EVO Mail Server, dan opsional kirim surat contoh.';

    public function handle(SuratClient $surat): int
    {
        $this->line('');
        $this->line('<options=bold>PEMERIKSAAN JALUR SURAT — EVO MAIL SERVER</>');
        $this->line(str_repeat('=', 64));

        $basis = (string) config('surat.basis');
        $public = (string) config('surat.api_public');

        $this->line('  server     : '.($basis ?: '<comment>KOSONG</comment>'));
        $this->line('  api public : '.($public ? Str::limit($public, 16, '…') : '<comment>KOSONG</comment>'));
        $this->line('  api secret : '.(config('surat.api_secret') ? 'terisi' : '<comment>kosong</comment>'));
        $this->line('  batas waktu: '.config('surat.timeout').' detik');
        $this->line('');

        if (! $basis || ! $public || ! config('surat.api_secret')) {
            $this->error('Konfigurasi belum lengkap. Isi SURAT_BASIS, SURAT_API_PUBLIC, dan SURAT_API_SECRET.');

            return self::FAILURE;
        }

        // ── 1. denyut — tanpa tanda tangan, jadi ia memisahkan "server mati"
        //       dari "kunci salah". Keduanya sama-sama membuat pengiriman
        //       gagal, tapi yang harus diperbaiki sama sekali berbeda.
        $this->line('<options=bold>1. Server hidup?</>');
        try {
            $jawab = file_get_contents(rtrim($basis, '/').'/api/v1/surat/denyut', false, stream_context_create([
                'http' => ['timeout' => 10, 'ignore_errors' => true],
            ]));
            $isi = json_decode((string) $jawab, true);

            if (! ($isi['success'] ?? false)) {
                $this->error('   TIDAK — server menjawab, tapi bukan jawaban yang dikenali.');

                return self::FAILURE;
            }

            $this->info('   YA — '.($isi['result']['layanan'] ?? 'server surat').', waktu server '.($isi['result']['waktu'] ?? '?'));
        } catch (Throwable $e) {
            $this->error('   TIDAK terjangkau: '.$e->getMessage());
            $this->line('   Periksa SURAT_BASIS, lalu apakah layanannya memang sudah ter-deploy.');

            return self::FAILURE;
        }

        // ── 2. kunci & izin ───────────────────────────────────────────────
        $this->line('');
        $this->line('<options=bold>2. Kunci dikenali & template mana yang tersedia?</>');

        try {
            $template = $surat->template();
        } catch (Throwable $e) {
            $this->error('   DITOLAK: '.$e->getMessage());
            $this->line('');
            $this->line('   401 → kunci tidak cocok, atau jam mesin ini melenceng jauh dari server surat.');
            $this->line('   403 → kuncinya benar, tapi endpoint ini belum diizinkan untuknya.');

            return self::FAILURE;
        }

        $this->info('   Kunci dikenali. '.count($template ?? []).' template terdaftar:');
        foreach ($template ?? [] as $t) {
            $this->line(sprintf('     - %-22s %s', $t['kode'], $t['nama']));
        }

        // ── 3. kirim contoh ───────────────────────────────────────────────
        $kirim = trim((string) $this->option('kirim'));
        if ($kirim === '') {
            $this->line('');
            $this->comment('Tidak ada surat yang dikirim. Tambahkan --kirim=alamat@contoh.com untuk mencoba sungguhan.');

            return self::SUCCESS;
        }

        if (! filter_var($kirim, FILTER_VALIDATE_EMAIL)) {
            $this->error('Alamat --kirim tidak sah: '.$kirim);

            return self::FAILURE;
        }

        $pilih = $this->option('semua')
            ? array_keys(self::contoh())
            : [(string) ($this->option('template') ?: 'verifikasi-email')];

        $this->line('');
        $this->line('<options=bold>3. Kirim surat contoh ke '.$kirim.'</>');

        $gagal = 0;

        foreach ($pilih as $kode) {
            $data = self::contoh()[$kode] ?? null;

            if ($data === null) {
                $this->error(sprintf('   %-22s tidak ada contoh untuk template ini.', $kode));
                $gagal++;

                continue;
            }

            $mulai = microtime(true);

            try {
                $hasil = $surat->kirim(
                    kepada: $kirim,
                    template: $kode,
                    data: $data,
                    // Acak, supaya menjalankan perintah ini dua kali memang
                    // menghasilkan dua surat. Kunci tetap akan membuat
                    // percobaan kedua dijawab "sudah pernah dikirim" — dan
                    // seorang yang sedang menguji akan menyimpulkan sistemnya
                    // rusak, padahal justru sedang bekerja.
                    kunciIdempotensi: 'coba:'.Str::random(20),
                );

                $ms = (int) round((microtime(true) - $mulai) * 1000);

                $this->info(sprintf(
                    '   %-22s TERKIRIM  %4d ms  %s',
                    $kode,
                    $ms,
                    Str::limit((string) ($hasil['message_id'] ?? '-'), 40, '…'),
                ));
                $this->line(sprintf('   %-22s subjek: %s', '', $hasil['subjek'] ?? '-'));
            } catch (Throwable $e) {
                $gagal++;
                $this->error(sprintf('   %-22s GAGAL — %s', $kode, $e->getMessage()));
            }
        }

        $this->line('');

        if ($gagal > 0) {
            $this->error($gagal.' dari '.count($pilih).' template gagal.');

            return self::FAILURE;
        }

        $this->info('Semua surat contoh berangkat. Periksa kotak masuk '.$kirim.'.');

        return self::SUCCESS;
    }

    /**
     * Muatan contoh per template.
     *
     * Sengaja diisi selengkap mungkin — termasuk kolom yang boleh kosong —
     * supaya yang diuji adalah tampilan surat yang PENUH. Surat yang dirender
     * dengan setengah kolom kosong tampak baik-baik saja pada kolom yang tidak
     * ada, dan cacat tata letaknya baru muncul pada kandidat sungguhan.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function contoh(): array
    {
        $portal = rtrim((string) config('app.url'), '/');

        return [
            'verifikasi-email' => [
                'nama' => 'Frans Bachtiar',
                'verif_url' => $portal.'/verifikasi-email?email=contoh%40evopet.id&token='.Str::random(64),
                'berlaku_menit' => 30,
            ],

            'reset-sandi-otp' => [
                'nama' => 'Frans Bachtiar',
                'otp' => '482915',
                'berlaku_menit' => 10,
            ],

            'reset-sandi-selesai' => [
                'nama' => 'Frans Bachtiar',
            ],

            'undangan-jadwal' => [
                'nama' => 'Frans Bachtiar',
                'kode' => 'WC-2026-000123',
                'tahap' => 'Wawancara HR',
                'aktivitas' => 'Wawancara HR',
                'posisi' => 'Software Engineer',
                'waktu_teks' => 'Sabtu, 22 Agustus 2026 · 09.00 – 10.30 WIB',
                'daring' => false,
                'lokasi' => 'Kantor Pusat EVO Group',
                'alamat' => 'Jl. Contoh Raya No. 1, Jakarta Selatan',
                'patokan' => 'Seberang halte busway',
                'kontak' => '021-1234567',
                'mapsUrl' => 'https://maps.google.com/?q=-6.2,106.8',
                'catatan' => 'Bawa KTP dan ijazah asli.',
            ],

            'pengingat-batas' => [
                'nama' => 'Frans Bachtiar',
                'tahap' => 'Kelengkapan Data',
                'batas_teks' => 'Kamis, 15 Okt 2026 pukul 23.59 WIB',
                'sisa_teks' => 'sekitar 20 jam lagi',
                'posisi' => 'Software Engineer',
                'program' => 'Management Trainee 2026',
                'kode' => 'WC-2026-000123',
            ],
            'hasil-lamaran' => [
                'nama' => 'Frans Bachtiar',
                'status' => 'LOLOS',
                'kode' => 'WC-2026-000123',
                'posisi' => 'Software Engineer',
                'program' => 'Management Trainee 2026',
                'tahap_lolos' => 'Seleksi Administrasi',
                'tahap_berikut' => 'Psikotes',
                'urutan' => 1,
                'total' => 4,
                'diterima' => false,
                // Catatan eksternal tahap — bentuknya sama dengan keluaran
                // CatatanEksternal::keTeksSurat(): teks polos, alamat web mentah.
                'catatan' => "Psikotes online Senin 09.00 WIB.\n\nLink Zoom (https://zoom.us/j/123456789)\n\n• Siapkan KTP dan alat tulis",
                'email' => 'contoh@evopet.id',
                'tgl_lahir' => '21 Jul 2001',
                'kampus' => 'Universitas Contoh',
                'jenis_kelamin' => 'Laki-laki',
                'hp' => '+62 822-6936-2834',
                // Gambar contoh dibuat di tempat, bukan diambil dari GCS:
                // yang diuji di sini jalur SEMATAN GAMBAR-nya, dan itu harus
                // bisa diuji tanpa satu berkas kandidat sungguhan pun.
                'foto_base64' => self::gambarContoh(),
            ],
        ];
    }

    /**
     * Sebuah JPEG kecil yang benar-benar sah, dibuat di tempat.
     *
     * Ada supaya jalur sematan gambar bisa diuji ujung ke ujung — base64 di
     * muatan, diurai di server surat, disematkan lewat CID, lalu tampil di
     * klien surat. Tanpa gambar sungguhan, satu-satunya yang teruji adalah
     * cabang "tidak ada foto".
     *
     * Mengembalikan null bila ekstensi GD tidak ada. Suratnya tetap terkirim,
     * memakai inisial nama — persis seperti yang terjadi pada kandidat yang
     * fotonya gagal diambil.
     */
    private static function gambarContoh(): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $img = imagecreatetruecolor(160, 160);
        imagefill($img, 0, 0, imagecolorallocate($img, 79, 70, 229));
        imagefilledellipse($img, 80, 62, 56, 56, imagecolorallocate($img, 255, 255, 255));
        imagefilledellipse($img, 80, 150, 104, 84, imagecolorallocate($img, 255, 255, 255));

        ob_start();
        imagejpeg($img, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return base64_encode($bytes);
    }
}
