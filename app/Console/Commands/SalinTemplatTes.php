<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Salin ulang templat laporan tes dari cat-evo-pembaharuan.
 *
 * Halaman hasil psikotes pada Berkas Seleksi Kandidat memakai templat yang
 * SAMA dengan laporan yang diterbitkan CAT — itulah sebabnya keduanya tampak
 * identik. Templat aslinya tetap tinggal di CAT (pemiliknya), dan perintah ini
 * membawa salinannya ke sini.
 *
 * ── KENAPA DISALIN, BUKAN DIPANGGIL LEWAT API SEBAGAI PDF ──────────────────
 *
 * Kalau CAT yang mencetak, tiap tes menghasilkan berkas PDF sendiri yang harus
 * digabungkan — satu langkah tambahan untuk tiap tes, tiap kandidat, tiap kali
 * cetak. Dengan menyalin templatnya, seluruh dokumen dirender sekali jalan dan
 * CAT cukup mengirim datanya.
 *
 * ── DISALIN UTUH, TANPA DIUBAH SAMA SEKALI ────────────────────────────────
 *
 * Templatnya dipindahkan APA ADANYA: kerangka HTML, kop berlogo, kaki
 * "DOKUMEN RAHASIA", tera air, dan seluruh CSS-nya. Satu-satunya tambahan
 * adalah blok pembongkar variabel di awal <body> yang mengubah payload API jadi
 * variabel yang sama dengan yang dipakai controller CAT ($kandidat, $domain,
 * $papiData, $chartImage, ...). Isi laporannya sendiri tidak disentuh.
 *
 * Versi sebelumnya membuang kop, kaki, dan tera air, lalu menamespace
 * seluruh selektor CSS — dengan alasan `position: fixed` akan membeku di
 * setiap halaman dokumen gabungan. Alasan itu TIDAK berlaku di sini: tiap
 * laporan tes dirender jadi PDF-nya SENDIRI oleh renderLaporanTes(), baru
 * digabungkan di tingkat PDF oleh FPDI. Tidak ada satu pun elemennya yang
 * pernah bertemu halaman rancangan, jadi tidak ada yang perlu dilindungi —
 * yang terjadi justru sebaliknya: laporan kehilangan kop & kakinya, dan
 * diagram PAPI berubah bentuk karena CSS-nya ikut tersunting.
 *
 * BERKAS HASILNYA JANGAN DISUNTING LANGSUNG — perbaikan tampilan dilakukan di
 * CAT, lalu perintah ini dijalankan ulang.
 */
class SalinTemplatTes extends Command
{
    protected $signature = 'berkas:salin-templat-tes
                            {--sumber= : Akar project cat-evo-pembaharuan}
                            {--periksa : Hanya laporkan yang berubah, tanpa menulis}';

    protected $description = 'Salin templat laporan tes (TIU/Kraeplin/DISC/PAPI) dari cat-evo-pembaharuan';

    /** Berkas sumber → [berkas tujuan, judul laporan]. */
    private const PETA = [
        'ujian-nilai-akhir-tiu.blade.php' => ['tiu.blade.php', 'TIU — General Reasoning Test'],
        'ujian-nilai-akhir-kraeplin.blade.php' => ['kraeplin.blade.php', 'Result Test Kraepelin'],
        'ujian-nilai-akhir-papikostick.blade.php' => ['papikostick.blade.php', 'Result Test PAPI Kostick'],
        'ujian-nilai-akhir-disc.blade.php' => ['disc.blade.php', 'DISC Assessment Report'],
        'ujian-nilai-akhir.blade.php' => ['generik.blade.php', 'Hasil Tes'],
    ];

    public function handle(): int
    {
        $sumber = rtrim(
            $this->option('sumber') ?: dirname(base_path()) . '/cat-evo-pembaharuan',
            '/\\'
        ) . '/resources/views/pdf/';

        if (! is_dir($sumber)) {
            $this->error("Folder templat CAT tidak ditemukan: {$sumber}");
            $this->line('Sebutkan letaknya dengan --sumber=/jalur/ke/cat-evo-pembaharuan');

            return self::FAILURE;
        }

        $tujuan = resource_path('views/career/berkas/tes/');

        if (! is_dir($tujuan) && ! mkdir($tujuan, 0755, true)) {
            $this->error("Gagal membuat folder tujuan: {$tujuan}");

            return self::FAILURE;
        }

        $periksa = (bool) $this->option('periksa');
        $berubah = 0;

        foreach (self::PETA as $dari => [$ke, $judul]) {
            if (! is_file($sumber . $dari)) {
                $this->warn("  lewat  {$dari} — tidak ada di CAT");

                continue;
            }

            $baru = $this->ubah(file_get_contents($sumber . $dari), $dari, $ke, $judul);
            $lama = is_file($tujuan . $ke) ? file_get_contents($tujuan . $ke) : null;

            if ($lama === $baru) {
                $this->line("  sama   {$ke}");

                continue;
            }

            $berubah++;

            if ($periksa) {
                $this->warn("  beda   {$ke}");

                continue;
            }

            file_put_contents($tujuan . $ke, $baru);
            $this->info("  tulis  {$ke} (" . number_format(strlen($baru)) . ' byte)');
        }

        if ($periksa) {
            $this->newLine();
            $this->line($berubah === 0
                ? 'Seluruh templat sudah sama dengan CAT.'
                : "{$berubah} templat berbeda dari CAT — jalankan tanpa --periksa untuk memperbaruinya.");

            return $berubah === 0 ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->info($berubah === 0 ? 'Tidak ada yang berubah.' : "{$berubah} templat diperbarui.");

        return self::SUCCESS;
    }

    /**
     * Salin satu templat CAT apa adanya, hanya menyisipkan prolog variabel.
     *
     * Yang berubah dari berkas aslinya HANYA dua hal:
     *   1. catatan asal-usul di paling atas;
     *   2. blok pembongkar variabel tepat sesudah <body> yang mengubah payload API
     *      nama variabel yang sama dengan yang dikirim controller CAT.
     *
     * Selebihnya — <style>, <header>, <footer>, tera air, seluruh
     * markup — dibawa byte per byte. Itulah yang membuat halaman hasil tes di
     * berkas seleksi identik dengan PDF yang diterbitkan CAT.
     */
    private function ubah(string $mentah, string $dari, string $ke, string $judul): string
    {
        // Bagian pelanggaran tidak ikut: datanya tidak dikirim endpoint
        // laporan, dan @include ke view CAT jelas tidak ada di sini.
        $mentah = preg_replace(
            "#@include\('pdf\.partials\.pelanggaran-section'[^)]*\)#s",
            '',
            $mentah
        );

        $prolog = $this->prolog($judul);

        // Disisipkan SESUDAH <body ...>, bukan di atas <!DOCTYPE>: blok @php
        // yang mendahului doctype membuat dompdf menerima berkas yang diawali
        // baris kosong, dan @page-nya berhenti berlaku.
        // Disisipkan tepat SESUDAH <body ...>. Newline di depan prolog wajib:
        // Blade hanya mengenali direktifnya di awal baris — ditempelkan
        // langsung ke tag <body>, direktifnya dibaca sebagai teks biasa dan
        // seluruh isinya bocor ke halaman sebagai kode PHP mentah.
        //
        // `str_replace` pada '$' melindungi variabel prolog dari preg_replace,
        // yang akan membaca $data / $kandidat sebagai rujukan grup tangkapan.
        $hasil = preg_replace(
            '#(<body[^>]*>)#i',
            '$1' . "
" . str_replace('$', '\$', $prolog),
            $mentah,
            1,
            $jumlah
        );

        if (! $jumlah) {
            // Templat tanpa <body> tidak akan pernah benar sebagai dokumen
            // berdiri sendiri — lebih baik gagal terang-terangan.
            throw new \RuntimeException("Templat {$dari} tidak punya <body>.");
        }

        return $this->catatan($dari, $ke, $judul) . $hasil;
    }

    /** Catatan asal-usul, ditempel di paling atas berkas hasil. */
    private function catatan(string $dari, string $ke, string $judul): string
    {
        return <<<BLADE
        {{--
            WEB CAREER — HASIL TES: {$judul}

            DISALIN UTUH dari cat-evo-pembaharuan (resources/views/pdf/{$dari}).
            Seluruh isinya — kerangka HTML, kop berlogo, kaki "DOKUMEN RAHASIA",
            tera air, dan CSS — dibawa apa adanya supaya halaman ini identik dengan PDF yang
            diterbitkan CAT. Satu-satunya tambahan adalah blok pembongkar variabel
            tepat sesudah <body>, yang menyiapkan payload API untuk templat ini.

            Laporan ini dirender jadi PDF-nya SENDIRI (renderLaporanTes) lalu
            digabungkan di tingkat PDF oleh FPDI — bukan disisipkan ke dalam
            dokumen rancangan. Itu sebabnya kerangka HTML dan elemen berposisi
            tetap di sini aman: tidak ada halaman lain yang bisa ditumpanginya.

            JANGAN DISUNTING LANGSUNG. Berkas ini dihasilkan ulang oleh
            `php artisan berkas:salin-templat-tes`; suntingan tangan akan tertimpa.
            Perbaikan tampilan dilakukan di CAT, lalu perintah itu dijalankan ulang.
        --}}

        BLADE;
    }

    /**
     * Blok pembongkar payload API menjadi variabel templat.
     *
     * Namanya sengaja disamakan PERSIS dengan yang dikirim controller CAT
     * ($kandidat, $domain, $summaryList, $papiData, $h, $chartImage,
     * $donutImage, $namaReport, $watermark, $pelanggaran) supaya markup di
     * bawahnya tidak perlu disunting satu baris pun.
     */
    private function prolog(string $judul): string
    {
        return str_replace('__JUDUL__', $judul, <<<'BLADE'
@php
    /* Disisipkan oleh berkas:salin-templat-tes — satu-satunya bagian berkas
       ini yang BUKAN salinan dari CAT. Membongkar payload endpoint
       api/v1/web-careers/laporan-tes menjadi variabel yang sama persis dengan
       yang dikirim controller CAT ke templat ini. */
    $data = $lap['data'] ?? [];
    $kandidat = $data['kandidat'] ?? [];
    $domain = collect($data['domain'] ?? [])->map(fn ($r) => (object) $r);
    $summaryList = collect($data['summary_list'] ?? [])->map(function ($r) {
        $r['Norma_Referensi'] = collect($r['Norma_Referensi'] ?? []);

        return $r;
    });
    $papiData = $data['papi_data'] ?? [];
    $h = $data;
    $chartImage = $data['chart_image'] ?? null;
    $donutImage = $data['donut_image'] ?? null;
    $namaReport = $lap['nama_tes'] ?: '__JUDUL__';
    $watermark = $watermark ?? null;
    $pelanggaran = null;
@endphp
BLADE);
    }
}
