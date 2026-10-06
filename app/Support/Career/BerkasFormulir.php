<?php

namespace App\Support\Career;

use Illuminate\Http\UploadedFile;

/**
 * WEB CAREER — PENJAGA BERKAS FORMULIR DI SISI SERVER.
 *
 * Menjawab dua pertanyaan yang selama ini hanya dijawab browser:
 *
 *   1. Berkas mana yang HARUS sudah ada di server untuk jawaban ini?
 *      → harapan() / kurang()
 *   2. Boleh-tidaknya satu berkas diterima untuk sebuah isian?
 *      → aturanUnggah() + periksaBerkas()
 *
 * ── KENAPA ADA ─────────────────────────────────────────────────────────────
 *
 * Nama berkas masuk ke jawaban BEGITU dipilih, sedangkan isinya naik lewat
 * permintaan terpisah. Server dulu hanya menerima jawaban dan tidak pernah
 * bertanya apakah berkas yang disebut namanya benar-benar ada. Unggahan yang
 * gagal, tertimpa unggahan lain, atau ditolak karena ukuran lolos sebagai
 * "Terkirim". Investigasi produksi 26 Sep 2026 menemukan CV, KTP, KK, dan 17
 * sertifikat yang namanya tercatat di jawaban tetapi berkasnya tidak pernah ada.
 *
 * ── ATURANNYA CERMIN BROWSER ───────────────────────────────────────────────
 *
 * Tampil & wajib dinilai PERSIS seperti periksaLangkah() di
 * resources/js/utils/formulir/aturan.js: bagian tersembunyi dilewati, di
 * bagian berulang syarat field dinilai terhadap BARIS-nya sendiri, dan
 * `wajib_jika` menggantikan `wajib`. Kunci field & bagian diturunkan seperti
 * normalisasiSkema() di schema.js. Kalau keduanya berbeda, server menuntut
 * berkas yang di layar tidak pernah diminta dan kandidat terkunci tanpa jalan
 * keluar. Cermin JS-nya: harapanBerkas() di aturan.js.
 *
 * Tidak ada nama formulir atau nama field di sini: semuanya dibaca dari skema,
 * jadi formulir yang disesuaikan admin ikut terjaga tanpa mengubah kode.
 */
final class BerkasFormulir
{
    /** Tipe field yang berupa unggahan berkas. */
    public const TIPE_BERKAS = ['file', 'foto'];

    /**
     * Format yang boleh DISIMPAN server. Skema boleh mempersempit, tidak boleh
     * memperluas — Master Formulir menandai format di luar daftar ini
     * (katalogField.js), supaya admin tidak membuat isian yang pasti ditolak.
     */
    public const EKSTENSI_SERVER = ['pdf', 'jpg', 'png'];

    /** Batas keras ukuran (MB) — sama dengan batas pilihan di Master Formulir. */
    public const MAKS_MB_KERAS = 20;

    /** MIME hasil deteksi ISI berkas yang sah untuk tiap format. */
    private const MIME_SAH = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
    ];

    /** Sama dengan MAKS_PANJANG_KEY di schema.js. */
    private const MAKS_PANJANG_KEY = 56;

    /** Spasi versi String.prototype.trim() JavaScript, bukan hanya ASCII. */
    private const SPASI = '[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]';

    // ══ 1. BERKAS YANG SEHARUSNYA ADA ══════════════════════════════════════

    /**
     * Berkas yang KURANG: seharusnya ada (wajib, atau jawabannya sudah menyebut
     * nama berkas) tetapi $ada() menyatakan salinannya tidak ada di server.
     *
     * @param  callable(?string, ?int, string): bool  $ada
     * @return list<array{bagian: ?string, baris: ?int, field: string, label: string, judulBagian: ?string, nama: string, wajib: bool, pesan: string}>
     */
    public static function kurang(?array $schema, array $jawaban, callable $ada): array
    {
        return array_values(array_filter(
            self::harapan($schema, $jawaban),
            fn (array $h) => ! $ada($h['bagian'], $h['baris'], $h['field']),
        ));
    }

    /**
     * Semua field berkas TERLIHAT yang wajib, atau yang jawabannya menyebut
     * nama berkas. Nama tanpa berkas adalah tanda unggahannya gagal — justru
     * itu yang harus tertangkap, termasuk pada isian yang tidak wajib.
     *
     * Skema kosong/tak terbaca menghasilkan daftar kosong: formulir lama yang
     * lahir sebelum skema dinamis tidak boleh ditolak karena riwayat kode kita.
     */
    public static function harapan(?array $schema, array $jawaban): array
    {
        $out = [];

        foreach (self::slot($schema, $jawaban) as $s) {
            if (! self::diharapkan($s)) {
                continue;
            }

            $awalan = $s['judulBagian'] !== null ? "{$s['judulBagian']} baris " . ($s['baris'] + 1) . ': ' : '';
            $out[] = [
                'bagian' => $s['bagian'],
                'baris' => $s['baris'],
                'field' => $s['field'],
                'label' => $s['label'],
                'judulBagian' => $s['judulBagian'],
                'nama' => $s['nama'],
                'wajib' => $s['wajib'],
                'pesan' => $awalan . ($s['nama'] !== ''
                    ? "\"{$s['label']}\" belum tersimpan di server — unggah ulang berkasnya."
                    : "\"{$s['label']}\" wajib diunggah."),
            ];
        }

        return $out;
    }

    /**
     * SEMUA slot berkas di skema untuk jawaban ini — terlihat maupun tidak.
     *
     * Satu slot = satu isian berkas di luar bagian berulang, atau satu isian
     * berkas pada SATU baris bagian berulang (baris yang memang ada di jawaban).
     * harapan() menyaring daftar ini; panel Pemulihan Berkas memakainya utuh
     * untuk "tambah manual", tempat admin justru butuh isian yang TIDAK
     * dituntut sistem.
     *
     * `terlihat` mengikuti aturan tampil yang sama dengan layar: syarat bagian
     * dinilai terhadap jawaban utuh, syarat field terhadap barisnya sendiri.
     *
     * @return list<array{bagian: ?string, baris: ?int, field: string, label: string, judulBagian: ?string, labelBaris: ?string, nama: string, wajib: bool, terlihat: bool}>
     */
    public static function slot(?array $schema, array $jawaban): array
    {
        $out = [];

        foreach ((array) ($schema['langkah'] ?? []) as $langkah) {
            if (! is_array($langkah)) {
                continue;
            }

            foreach (array_values((array) ($langkah['bagian'] ?? [])) as $iB => $bagian) {
                if (! is_array($bagian)) {
                    continue;
                }

                $tampil = self::syaratTerpenuhi($bagian['tampil_jika'] ?? null, $jawaban);
                $fields = array_values(array_filter((array) ($bagian['field'] ?? []), 'is_array'));

                if (self::benarJs($bagian['berulang'] ?? null)) {
                    $kunci = BerkasBaris::kunciBagian($bagian, $iB);
                    $daftar = $jawaban[$kunci] ?? null;
                    if (! is_array($daftar) || ! array_is_list($daftar)) {
                        continue;
                    }

                    $judul = self::judulBagian($bagian, $iB);
                    foreach ($daftar as $i => $baris) {
                        // Baris sah = objek JSON. Larik JSON bukan baris (JS
                        // melewatinya); objek kosong {} tiba sebagai [] di PHP.
                        if (! is_array($baris) || ($baris !== [] && array_is_list($baris))) {
                            continue;
                        }
                        $labelBaris = self::labelBaris($fields, $baris);
                        foreach ($fields as $f) {
                            if ($s = self::slotSatu($f, $baris, $kunci, $i, $judul, $labelBaris, $tampil)) {
                                $out[] = $s;
                            }
                        }
                    }

                    continue;
                }

                foreach ($fields as $f) {
                    if ($s = self::slotSatu($f, $jawaban, null, null, null, null, $tampil)) {
                        $out[] = $s;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Apakah sebuah slot MENUNTUT berkas: terlihat, dan wajib atau jawabannya
     * sudah menyebut nama berkas. Satu definisi untuk harapan() dan pemindai
     * Pemulihan Berkas — kalau keduanya menilai sendiri-sendiri, panel admin
     * bisa menyebut "lengkap" untuk kiriman yang ditolak server.
     */
    public static function diharapkan(array $slot): bool
    {
        return $slot['terlihat'] && ($slot['wajib'] || $slot['nama'] !== '');
    }

    /** Pola `accept` untuk input berkas di layar admin, dari format yang sah. */
    public static function acceptDari(array $ekstensi): string
    {
        $out = [];
        foreach ($ekstensi as $e) {
            $out[] = '.' . $e;
            if ($e === 'jpg') {
                $out[] = '.jpeg';
            }
        }

        return implode(',', $out);
    }

    /** Penilai `ada` untuk daftar Berkas_Json milik draf formulir tahap. */
    public static function adaDiDaftar(array $daftar): callable
    {
        return function (?string $bagian, ?int $baris, string $field) use ($daftar): bool {
            foreach ($daftar as $e) {
                if (BerkasBaris::cocok($e, $bagian, $baris, $field) && ! empty($e['path'])) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * Satu kalimat untuk kandidat. Rinciannya ikut dikirim terpisah
     * (`berkasKurang`) supaya layar bisa mengosongkan tepat isian yang bermasalah.
     */
    public static function pesanRingkas(array $kurang): string
    {
        $label = array_map(
            fn (array $k) => $k['judulBagian'] !== null ? "{$k['label']} ({$k['judulBagian']} baris " . ($k['baris'] + 1) . ')' : $k['label'],
            $kurang,
        );
        $sebut = implode(', ', array_slice($label, 0, 4));
        if (count($label) > 4) {
            $sebut .= ', dan ' . (count($label) - 4) . ' lainnya';
        }

        return 'Berkas berikut belum ada di server: ' . $sebut . '. Unggah (ulang) berkasnya, lalu kirim kembali.';
    }

    /** Satu slot berkas, atau null bila field ini bukan unggahan berkas. */
    private static function slotSatu(
        array $f,
        array $konteks,
        ?string $bagian,
        ?int $baris,
        ?string $judulBagian,
        ?string $labelBaris,
        bool $bagianTampil,
    ): ?array {
        if (! in_array(self::tipe($f), self::TIPE_BERKAS, true)) {
            return null;
        }

        $key = self::kunciField($f);
        $nilai = $konteks[$key] ?? null;
        $terlihat = $bagianTampil && self::syaratTerpenuhi($f['tampil_jika'] ?? null, $konteks);

        return [
            'bagian' => $bagian,
            'baris' => $baris,
            'field' => $key,
            'label' => self::labelField($f),
            'judulBagian' => $judulBagian,
            'labelBaris' => $labelBaris,
            'nama' => self::kosong($nilai) ? '' : self::teks($nilai),
            // Wajib hanya berarti selagi isiannya terlihat — sama seperti layar.
            'wajib' => $terlihat && self::wajibKini($f, $konteks),
            'terlihat' => $terlihat,
        ];
    }

    /**
     * Nama yang dibaca manusia untuk satu baris bagian berulang: isian teks
     * pertama yang terisi (mis. nama sertifikatnya). "Sertifikat #3" saja tidak
     * cukup bagi admin yang sedang mencocokkan berkas kiriman kandidat.
     */
    private static function labelBaris(array $fields, array $baris): ?string
    {
        foreach ($fields as $f) {
            if (in_array(self::tipe($f), self::TIPE_BERKAS, true)) {
                continue;
            }
            $v = $baris[self::kunciField($f)] ?? null;
            if (is_string($v) && ($v = self::rapikanSpasi($v)) !== '') {
                return mb_strimwidth($v, 0, 70, '…');
            }
        }

        return null;
    }

    // ══ 2. BOLEH-TIDAKNYA SATU BERKAS DITERIMA ═════════════════════════════

    /**
     * Aturan unggah SATU isian menurut skemanya.
     *
     * Batasnya mengikuti skema, bukan angka tetap di kode: admin bisa menaikkan
     * `maks_mb` atau mengganti format di Master Formulir, dan browser langsung
     * menurutinya. Server yang memakai angka sendiri akan menolak berkas yang
     * di layar dinyatakan boleh.
     *
     * Nilainya ditiru dari yang benar-benar dipakai layar: normalisasi mengisi
     * bawaan tipe lebih dulu (bersihkanField di katalogField.js — `maks_mb: 5`,
     * `accept: '.pdf'` bila kosong), lalu FieldRenderer.vue memakai
     * `maks_mb || 2` dan `accept || '.pdf'`. Isian yang tidak ditemukan di
     * skema memakai batas bawaan pemanggil — perilaku sebelum aturan ini ada.
     *
     * @return array{ekstensi: list<string>, maksMb: float, label: string}
     */
    public static function aturanUnggah(?array $schema, ?string $bagian, string $field, float $maksBawaanMb): array
    {
        $f = self::cariField($schema, $bagian, $field);
        if ($f === null) {
            return ['ekstensi' => self::EKSTENSI_SERVER, 'maksMb' => min(self::MAKS_MB_KERAS, $maksBawaanMb), 'label' => $field];
        }

        // Kamera selalu menghasilkan JPEG; AmbilFoto tidak membaca accept/maks_mb.
        if (self::tipe($f) === 'foto') {
            return ['ekstensi' => ['jpg', 'png'], 'maksMb' => min(self::MAKS_MB_KERAS, $maksBawaanMb), 'label' => self::labelField($f)];
        }

        $mb = $f['maks_mb'] ?? null;
        if ($mb === null || $mb === '') {
            $mb = 5;
        }
        // `maks_mb || 2`: nilai 0 berarti 2 MB di layar. Server tidak boleh
        // lebih ketat dari layar, jadi yang dipakai yang lebih longgar.
        $mb = self::benarJs($mb) && is_numeric($mb) ? (float) $mb : max(2.0, $maksBawaanMb);

        $accept = $f['accept'] ?? null;
        $accept = self::benarJs($accept) ? self::teks($accept) : '.pdf';

        return [
            'ekstensi' => self::ekstensiDariAccept($accept),
            'maksMb' => min(self::MAKS_MB_KERAS, $mb),
            'label' => self::labelField($f),
        ];
    }

    /**
     * Periksa satu berkas terhadap aturannya. Ukuran diperiksa SEBELUM isinya
     * dibaca pemanggil, jadi berkas raksasa tidak pernah masuk memori.
     *
     * @return string|null pesan untuk kandidat, atau null bila sah
     */
    public static function periksaBerkas(UploadedFile $file, array $aturan): ?string
    {
        $label = $aturan['label'];

        if (! $aturan['ekstensi']) {
            return "\"{$label}\": format berkasnya belum didukung server. Hubungi tim rekrutmen.";
        }

        $ext = self::normalExt($file->getClientOriginalExtension() ?: (string) $file->extension());
        if (! in_array($ext, $aturan['ekstensi'], true)) {
            return "\"{$label}\": hanya menerima " . self::daftarFormat($aturan['ekstensi']) . '.';
        }

        if ($file->getSize() > (int) round($aturan['maksMb'] * 1024 * 1024)) {
            return "\"{$label}\": ukuran melebihi " . self::teksMb($aturan['maksMb']) . ' MB.';
        }

        $sah = array_merge(...array_map(fn ($e) => self::MIME_SAH[$e] ?? [], $aturan['ekstensi']));
        if (! in_array(strtolower((string) $file->getMimeType()), $sah, true)) {
            return "\"{$label}\": isi berkas tidak sesuai formatnya. Pastikan berkasnya tidak rusak, lalu unggah ulang.";
        }

        return null;
    }

    /** 'jpeg' → 'jpg', tanpa titik, huruf kecil. */
    public static function normalExt(string $ext): string
    {
        $ext = strtolower(ltrim(trim($ext), '.'));

        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    /**
     * Terjemahkan `accept` skema ('.pdf,.jpg' atau 'image/png') ke format yang
     * juga diterima server. Yang di luar EKSTENSI_SERVER dibuang: skema boleh
     * mempersempit, tidak boleh memperluas.
     *
     * @return list<string>
     */
    public static function ekstensiDariAccept(string $accept): array
    {
        $peta = [
            'application/pdf' => ['pdf'],
            'image/jpeg' => ['jpg'],
            'image/jpg' => ['jpg'],
            'image/pjpeg' => ['jpg'],
            'image/png' => ['png'],
            'image/*' => ['jpg', 'png'],
        ];

        $out = [];
        foreach (explode(',', strtolower($accept)) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            $calon = str_starts_with($token, '.') ? [self::normalExt($token)] : ($peta[$token] ?? []);
            foreach ($calon as $e) {
                if (in_array($e, self::EKSTENSI_SERVER, true) && ! in_array($e, $out, true)) {
                    $out[] = $e;
                }
            }
        }

        return $out;
    }

    /**
     * Definisi field di skema. Bagian null = isian biasa di luar bagian
     * berulang; selain itu dicari di bagian berulang berkunci $bagian.
     */
    private static function cariField(?array $schema, ?string $bagian, string $field): ?array
    {
        foreach ((array) ($schema['langkah'] ?? []) as $langkah) {
            foreach (array_values((array) ($langkah['bagian'] ?? [])) as $iB => $b) {
                if (! is_array($b)) {
                    continue;
                }
                $berulang = self::benarJs($b['berulang'] ?? null);
                if ($bagian === null ? $berulang : (! $berulang || BerkasBaris::kunciBagian($b, $iB) !== $bagian)) {
                    continue;
                }
                foreach ((array) ($b['field'] ?? []) as $f) {
                    if (is_array($f) && self::kunciField($f) === $field) {
                        return $f;
                    }
                }
            }
        }

        return null;
    }

    private static function daftarFormat(array $ekstensi): string
    {
        return implode(', ', array_map('strtoupper', $ekstensi));
    }

    private static function teksMb(float $mb): string
    {
        return rtrim(rtrim(number_format($mb, 1, ',', ''), '0'), ',');
    }

    // ══ 3. CERMIN aturan.js & schema.js ════════════════════════════════════

    /** Cermin syaratTerpenuhi() di aturan.js — termasuk keanehan `Number()` JS. */
    public static function syaratTerpenuhi(mixed $syarat, array $jawaban): bool
    {
        if (! is_array($syarat) || ! self::benarJs($syarat['field'] ?? null)) {
            return true;
        }

        $kunci = self::teks($syarat['field']);
        $kiriAda = array_key_exists($kunci, $jawaban);
        $kiri = $kiriAda ? $jawaban[$kunci] : null;
        $kananAda = array_key_exists('nilai', $syarat);
        $kanan = $kananAda ? $syarat['nilai'] : null;
        $op = $syarat['operator'] ?? null;

        // Keanggotaan himpunan.
        if ($op === 'ADA_DI' || $op === 'TIDAK_ADA_DI') {
            $daftar = array_map(
                fn ($v) => self::rapiKecil($v),
                is_array($kanan) && array_is_list($kanan) ? $kanan : [$kanan],
            );
            $punya = fn ($v) => in_array(self::rapiKecil($v), $daftar, true);
            $ada = is_array($kiri) && array_is_list($kiri)
                ? (bool) array_filter($kiri, $punya)
                : $punya($kiri);

            return $op === 'ADA_DI' ? $ada : ! $ada;
        }

        // Checkbox menyimpan array → "=" berarti "mengandung nilai ini".
        // `nilai` yang tidak ada = undefined di JS, yang tak pernah ada di array JSON.
        if (is_array($kiri) && array_is_list($kiri)) {
            $isi = $kananAda && in_array($kanan, $kiri, true);

            return $op === '!=' ? ! $isi : $isi;
        }

        $nKiri = $kiriAda ? self::angkaJs($kiri) : null;
        $nKanan = $kananAda ? self::angkaJs($kanan) : null;
        $keduanyaAngka = $kiriAda && $kiri !== '' && $kiri !== null && $nKiri !== null && $nKanan !== null;

        if ($keduanyaAngka) {
            return match ($op) {
                '!=' => $nKiri != $nKanan,
                '>' => $nKiri > $nKanan,
                '<' => $nKiri < $nKanan,
                '>=' => $nKiri >= $nKanan,
                '<=' => $nKiri <= $nKanan,
                default => $nKiri == $nKanan,
            };
        }

        $a = self::rapiKecil($kiri);
        $b = self::rapiKecil($kanan);

        return match ($op) {
            '!=' => $a !== $b,
            '>' => strcmp($a, $b) > 0,
            '<' => strcmp($a, $b) < 0,
            '>=' => strcmp($a, $b) >= 0,
            '<=' => strcmp($a, $b) <= 0,
            default => $a === $b,
        };
    }

    /** Cermin wajibKini() di aturan.js. */
    public static function wajibKini(array $f, array $konteks): bool
    {
        $jika = $f['wajib_jika'] ?? null;
        if (is_array($jika) && self::benarJs($jika['field'] ?? null)) {
            return self::syaratTerpenuhi($jika, $konteks);
        }

        return self::benarJs($f['wajib'] ?? null);
    }

    /** Cermin slugKey(F.key || F.label || 'field') di schema.js. */
    public static function kunciField(array $f): string
    {
        $dasar = self::benarJs($f['key'] ?? null) ? $f['key'] : (self::benarJs($f['label'] ?? null) ? $f['label'] : 'field');

        $rapi = mb_strtolower(self::rapikanSpasi(self::teks($dasar)));
        $rapi = preg_replace('/[^a-z0-9_]+/', '_', $rapi) ?? '';
        $rapi = preg_replace('/^[0-9]+/', '', $rapi) ?? '';
        $rapi = preg_replace('/^_+|_+$/', '', $rapi) ?? '';
        $rapi = preg_replace('/_+$/', '', substr($rapi, 0, self::MAKS_PANJANG_KEY)) ?? '';

        return $rapi !== '' ? $rapi : 'field';
    }

    /** Cermin normalisasiBagian(): judul tak diisi → "Bagian N". */
    private static function judulBagian(array $b, int $indeks): string
    {
        $j = $b['judul'] ?? null;

        return $j === null ? 'Bagian ' . ($indeks + 1) : self::rapikanSpasi(self::teks($j));
    }

    /** Cermin normalisasiField(): String(F.label || F.key || 'Pertanyaan').trim(). */
    private static function labelField(array $f): string
    {
        $dasar = self::benarJs($f['label'] ?? null) ? $f['label'] : (self::benarJs($f['key'] ?? null) ? $f['key'] : 'Pertanyaan');

        return self::rapikanSpasi(self::teks($dasar));
    }

    private static function tipe(array $f): string
    {
        return strtolower(self::teks($f['tipe'] ?? ''));
    }

    /** Cermin kosong() di aturan.js. */
    private static function kosong(mixed $v): bool
    {
        if (is_array($v) && array_is_list($v)) {
            return $v === [];
        }
        if (is_bool($v)) {
            return $v === false;
        }
        if ($v === null) {
            return true;
        }

        return self::rapikanSpasi(self::teks($v)) === '';
    }

    /** Kebenaran versi JavaScript: '0' benar, 0 / '' / null / false salah. */
    private static function benarJs(mixed $v): bool
    {
        if ($v === null || $v === false || $v === '') {
            return false;
        }
        if (is_int($v) || is_float($v)) {
            return $v != 0 && ! is_nan((float) $v);
        }

        return true;
    }

    /** String(v ?? '').trim().toLowerCase() versi JavaScript. */
    private static function rapiKecil(mixed $v): string
    {
        return mb_strtolower(self::rapikanSpasi(self::teks($v)));
    }

    /**
     * Number(v) versi JavaScript; null = NaN.
     *
     * Detail yang sengaja ditiru: '' dan spasi → 0, true → 1, '0x1f' → 31,
     * '12abc' → NaN. Tanpa itu syarat angka dinilai berbeda dari browser.
     */
    private static function angkaJs(mixed $v): ?float
    {
        if ($v === null) {
            return 0.0;
        }
        if (is_bool($v)) {
            return $v ? 1.0 : 0.0;
        }
        if (is_int($v) || is_float($v)) {
            return is_nan((float) $v) ? null : (float) $v;
        }
        if (is_array($v)) {
            return array_is_list($v) ? self::angkaJs(self::teks($v)) : null;
        }
        if (! is_string($v)) {
            return null;
        }

        $s = self::rapikanSpasi($v);
        if ($s === '') {
            return 0.0;
        }
        if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/', $s)) {
            return (float) $s;
        }
        if (preg_match('/^0[xX][0-9a-fA-F]+$/', $s)) {
            return (float) hexdec(substr($s, 2));
        }
        if (preg_match('/^0[bB][01]+$/', $s)) {
            return (float) bindec(substr($s, 2));
        }
        if (preg_match('/^0[oO][0-7]+$/', $s)) {
            return (float) octdec(substr($s, 2));
        }
        if (preg_match('/^([+-]?)Infinity$/', $s, $m)) {
            return $m[1] === '-' ? -INF : INF;
        }

        return null;
    }

    /** String(v) versi JavaScript untuk nilai hasil json_decode. */
    private static function teks(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v) || is_string($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            if (is_nan($v)) {
                return 'NaN';
            }
            if (is_infinite($v)) {
                return $v > 0 ? 'Infinity' : '-Infinity';
            }
            if ($v == 0) {
                return '0'; // String(-0) === '0'
            }
            if (floor($v) === $v && abs($v) < 1e21) {
                return sprintf('%.0f', $v);
            }

            return (string) json_encode($v);
        }
        if (is_array($v)) {
            return array_is_list($v)
                ? implode(',', array_map(fn ($x) => $x === null ? '' : self::teks($x), $v))
                : '[object Object]';
        }

        return '';
    }

    private static function rapikanSpasi(string $s): string
    {
        return preg_replace('/^' . self::SPASI . '+|' . self::SPASI . '+$/u', '', $s) ?? trim($s);
    }
}
