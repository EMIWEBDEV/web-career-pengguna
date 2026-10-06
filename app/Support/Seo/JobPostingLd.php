<?php

namespace App\Support\Seo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * WEB CAREERS — SIMPUL schema.org/JobPosting.
 *
 * Inilah yang membuat sebuah lowongan muncul di GOOGLE JOBS — kotak lowongan
 * bergambar di puncak hasil pencarian — alih-alih sekadar satu baris biru di
 * antara sepuluh hasil lain. Untuk kata kunci seperti "lowongan management
 * trainee Palembang", bedanya bukan peringkat: yang tanpa JobPosting tidak
 * ikut dipertimbangkan sama sekali di kotak itu.
 *
 * ── GAJI SENGAJA TIDAK DICETAK ──────────────────────────────────────────────
 *
 * `baseSalary` hanya DISARANKAN Google, bukan diwajibkan. Kebijakan EVO Group
 * tidak menampilkan gaji di iklan lowongan, dan menerbitkan rentang yang
 * dikarang demi kelengkapan jauh lebih merugikan daripada tidak mencantumkan
 * apa pun: angkanya jadi janji publik yang terbaca kandidat, terarsip mesin
 * pencari, dan tidak bisa ditarik.
 *
 * Yang WAJIB — title, description, datePosted, hiringOrganization,
 * jobLocation — seluruhnya sudah tersedia dari MPP.
 *
 * ── KENAPA BUKAN DI CONTROLLER ──────────────────────────────────────────────
 *
 * Dua halaman memakainya (lowongan biasa & Management Trainee) dengan bentuk
 * data yang berbeda. Ditulis dua kali, keduanya pasti berselisih begitu Google
 * mengubah syaratnya — dan yang ketinggalan tidak menimbulkan galat apa pun,
 * ia cuma berhenti muncul.
 */
class JobPostingLd
{
    /**
     * Padanan tipe kerja kami → nilai baku schema.org.
     *
     * Yang tidak dikenali dibiarkan JATUH ke null, bukan ditebak "FULL_TIME".
     * Nilai yang salah membuat Google menyaring lowongan ini keluar dari
     * pencarian "paruh waktu" — dan kesalahannya tidak pernah terlihat.
     */
    private const TIPE = [
        'FULL-TIME' => 'FULL_TIME',
        'FULL TIME' => 'FULL_TIME',
        'PENUH WAKTU' => 'FULL_TIME',
        'PART-TIME' => 'PART_TIME',
        'PART TIME' => 'PART_TIME',
        'PARUH WAKTU' => 'PART_TIME',
        'KONTRAK' => 'CONTRACTOR',
        'CONTRACT' => 'CONTRACTOR',
        'MAGANG' => 'INTERN',
        'INTERNSHIP' => 'INTERN',
        'FREELANCE' => 'TEMPORARY',
        'HARIAN' => 'TEMPORARY',
    ];

    /**
     * Bangun simpul JobPosting — null bila datanya belum cukup.
     *
     * Mengembalikan null jauh lebih baik daripada memaksakan simpul setengah
     * jadi: JobPosting yang kekurangan field wajib ditolak Google, dan
     * penolakan berulang menurunkan kepercayaannya pada SELURUH situs.
     *
     * @param  array  $j  kartu lowongan / program MT
     */
    public static function dari(array $j, string $url, string $organisasi, ?string $logo = null): ?array
    {
        $judul = trim((string) ($j['posisi'] ?? $j['nama'] ?? ''));
        if ($judul === '') {
            return null;
        }

        $uraian = self::uraian($j);
        if (Str::length($uraian) < 40) {
            // Google menolak deskripsi yang terlalu pendek. Lebih baik halaman
            // ini tampil biasa daripada ditandai sebagai data terstruktur cacat.
            return null;
        }

        $dibuka = self::tanggal($j['dibuka'] ?? null) ?: Carbon::now();

        $simpul = [
            '@type' => 'JobPosting',
            'title' => $judul,
            // Google membaca HTML di sini — daftar tanggung jawab & syarat
            // memang lebih terbaca sebagai <ul>, dan itu yang ditampilkannya
            // di kotak lowongan.
            'description' => $uraian,
            'datePosted' => $dibuka->toIso8601String(),
            'employmentType' => self::tipeKerja($j['tipeKerja'] ?? null),
            'hiringOrganization' => array_filter([
                '@type' => 'Organization',
                'name' => $organisasi,
                'sameAs' => self::asal($url),
                'logo' => $logo,
            ]),
            'jobLocation' => self::lokasi($j),
            'jobLocationType' => self::jarakJauh($j['tempatKerja'] ?? null) ? 'TELECOMMUTE' : null,
            'directApply' => true,
            'url' => $url,
            'identifier' => array_filter([
                '@type' => 'PropertyValue',
                'name' => $organisasi,
                'value' => (string) ($j['id'] ?? ''),
            ]),
            'industry' => ($j['departemen'] ?? null) !== '—' ? ($j['departemen'] ?? null) : null,
            'occupationalCategory' => $j['level'] ?? null,
            'experienceRequirements' => self::pengalaman($j['pengalaman'] ?? null),
            'skills' => self::daftarTeks($j['skill'] ?? []),
            'qualifications' => self::daftarTeks($j['persyaratan'] ?? []),
            'responsibilities' => self::daftarTeks($j['tanggungJawab'] ?? []),
            'jobBenefits' => self::daftarTeks($j['benefit'] ?? []),
        ];

        // validThrough hanya untuk lowongan yang MEMANG punya tanggal tutup.
        // Lowongan tanpa batas yang diberi tanggal karangan akan menghilang
        // dari Google pada hari itu, tanpa ada yang tahu kenapa.
        if ($tutup = self::tanggal($j['tanggalTutup'] ?? null)) {
            $simpul['validThrough'] = $tutup->endOfDay()->toIso8601String();
        }

        return array_filter($simpul, static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * Uraian pekerjaan sebagai HTML sederhana.
     *
     * Digabung dari deskripsi + tanggung jawab + persyaratan, karena ketiganya
     * memang satu hal di mata pencari kerja: "pekerjaannya apa, dan saya harus
     * bisa apa".
     */
    private static function uraian(array $j): string
    {
        $h = [];

        $awal = trim(strip_tags((string) ($j['deskripsi'] ?? $j['tagline'] ?? '')));
        if ($awal !== '') {
            $h[] = '<p>'.e($awal).'</p>';
        }

        foreach ([
            'Tanggung Jawab' => $j['tanggungJawab'] ?? [],
            'Persyaratan' => $j['persyaratan'] ?? [],
        ] as $judul => $butir) {
            $isi = self::butir($butir);
            if (! $isi) {
                continue;
            }
            $h[] = '<h3>'.e($judul).'</h3><ul>'
                .implode('', array_map(static fn ($b) => '<li>'.e($b).'</li>', $isi))
                .'</ul>';
        }

        return implode('', $h);
    }

    /** Butir bisa berupa teks, atau larik objek ber-`teks`/`nama`/`judul`. */
    private static function butir($v): array
    {
        if (is_string($v)) {
            $v = preg_split('/\r\n|\r|\n/', $v) ?: [];
        }
        if (! is_array($v)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($b) {
            if (is_array($b)) {
                $b = $b['teks'] ?? $b['nama'] ?? $b['judul'] ?? $b['label'] ?? '';
            }

            return trim(strip_tags((string) $b));
        }, $v)));
    }

    private static function daftarTeks($v): ?string
    {
        $b = self::butir($v);

        return $b ? implode(', ', $b) : null;
    }

    /**
     * Lokasi penempatan.
     *
     * Selalu menyebut Sumatera Selatan sebagai wilayahnya bila lokasinya tidak
     * menyebut provinsi lain — inilah yang membuat "lowongan kerja Palembang"
     * dan "loker Sumsel" menemukan lowongan ini. Tanpa addressRegion, Google
     * tidak punya dasar mencocokkannya dengan pencarian berwilayah.
     */
    private static function lokasi(array $j): array
    {
        $kota = self::kota($j['lokasi'] ?? null)
            ?? self::kota($j['penempatan'] ?? null)
            ?? (string) config('seo.geo_placename', 'Palembang');

        return [
            '@type' => 'Place',
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => $kota,
                'addressRegion' => (string) config('seo.geo_region_name', 'Sumatera Selatan'),
                'addressCountry' => 'ID',
            ]),
        ];
    }

    /**
     * Ambil KOTA saja — tolak yang sebenarnya cara kerja.
     *
     * Kolom `Program_Posisi.Lokasi` di lapangan sering diisi "Hybrid",
     * "Remote (WFH)", atau "On-site (WFO)" alih-alih nama kota; lihat
     * lokasiLabel() di CareerLandingController yang sudah lama menangani hal
     * yang sama untuk tampilan.
     *
     * Menyalinnya mentah-mentah ke addressLocality berakibat fatal dan senyap:
     * Google membaca kota lowongan ini sebagai "Hybrid", lalu lowongan itu
     * TIDAK PERNAH muncul untuk pencarian "lowongan kerja Palembang" — tanpa
     * galat, tanpa peringatan di Search Console. Lebih baik jatuh ke Palembang
     * (kantor pusat, dan memang benar untuk hampir seluruh lowongan) daripada
     * mengisi kota dengan kata yang bukan tempat.
     */
    private static function kota($v): ?string
    {
        $t = trim((string) $v);
        if ($t === '' || $t === '—') {
            return null;
        }

        // Label gabungan "Palembang · Hybrid" → ambil bagian kotanya.
        foreach (['·', '|', ' - '] as $pisah) {
            if (str_contains($t, $pisah)) {
                $t = trim(explode($pisah, $t)[0]);
                break;
            }
        }

        $polos = preg_replace('/[^a-z]/', '', strtolower($t));
        foreach (['onsite', 'wfo', 'remote', 'wfh', 'hybrid', 'workfromhome',
            'workfromoffice', 'fleksibel', 'flexible', 'jarakjauh'] as $bukanKota) {
            if ($polos === $bukanKota || str_starts_with($polos, $bukanKota)) {
                return null;
            }
        }

        return $t;
    }

    private static function tipeKerja(?string $v): ?string
    {
        $k = strtoupper(trim((string) $v));

        return $k === '' ? null : (self::TIPE[$k] ?? null);
    }

    private static function jarakJauh(?string $tempat): bool
    {
        $t = strtolower((string) $tempat);

        return str_contains($t, 'remote') || str_contains($t, 'jarak jauh');
    }

    /** "Min. 1 - 2 Tahun" → kalimat yang dibaca Google apa adanya. */
    private static function pengalaman(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    private static function tanggal($v): ?Carbon
    {
        if (! $v) {
            return null;
        }

        try {
            return Carbon::parse($v);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function asal(string $url): string
    {
        $p = parse_url($url);

        return ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '');
    }
}
