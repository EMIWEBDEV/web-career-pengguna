<?php

namespace App\Support\Career;

/**
 * WEB CAREERS — penyaring HTML dengan daftar-izin (allowlist).
 *
 * DIPAKAI UNTUK APA
 * Jawaban detail FAQ (`N_WEB_CAREERS_Master_Faq.Jawaban_Detail`) diisi admin
 * lewat editor Quill, yang mengirim HTML MENTAH. HTML itu nantinya dirender
 * dengan `v-html` di halaman PUBLIK /karir/faq.
 *
 * KENAPA DISARING SAAT SIMPAN, BUKAN SAAT RENDER
 * Kalau hanya disaring saat render, isi berbahaya tetap tersimpan di database
 * dan ikut ke mana pun data itu dipakai nanti (ekspor, email, halaman lain yang
 * lupa menyaring). Menyaring di gerbang tulis membuat isi kolom SELALU aman —
 * penyaringan di sisi klien (DOMPurify) tinggal jadi lapisan kedua.
 *
 * CARA KERJA
 * Tag di luar daftar-izin di-UNWRAP (isi teksnya dipertahankan, tag-nya dibuang)
 * alih-alih dihapus total, supaya admin tidak kehilangan tulisan hanya karena
 * memakai format yang tidak didukung. Semua atribut dibuang kecuali `href` pada
 * tautan, dan tautan wajib berskema http/https/mailto.
 */
class HtmlBersih
{
    /** Tag yang boleh bertahan. Sengaja sempit: sepadan dengan toolbar Quill. */
    private const TAG_DIIZINKAN = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li', 'h3', 'h4', 'blockquote', 'a', 'img',
    ];

    /** Skema tautan yang boleh. `javascript:` & `data:` sengaja tidak ada. */
    private const SKEMA_DIIZINKAN = ['http', 'https', 'mailto'];

    /**
     * Satu-satunya bentuk `src` yang boleh bertahan pada <img>.
     *
     * Gambar dalam catatan penilaian WAJIB berkas milik kita sendiri, disajikan
     * lewat rute berwenang. Dua hal yang ditutup sekaligus:
     *
     *   1. `data:` URI — satu potret ponsel jadi ~2,7 MB base64 di dalam kolom,
     *      ikut terbawa setiap kali baris itu dibaca, dan tak bisa dibersihkan
     *      saat catatannya disunting.
     *   2. Tautan ke host luar — gambar dari domain asing menjadikan setiap
     *      pembaca catatan mengirim jejak (IP, waktu buka) ke pemilik domain
     *      itu, dan gambarnya bisa diganti apa pun setelah catatan disetujui.
     */
    private const POLA_SRC_GAMBAR = '#^/api/v1/karir/lamaran/catatan/gambar/[A-Za-z0-9]+$#';

    /**
     * Satu-satunya `class` yang boleh bertahan: perataan bawaan Quill.
     *
     * Perataan di Quill BUKAN atribut style melainkan kelas pada bloknya
     * (`ql-align-center`). Karena seluruh atribut dibuang di sini, gambar yang
     * susah payah ditengahkan penilai kembali menempel ke kiri begitu
     * catatannya disimpan — dan tak ada galat apa pun yang menjelaskannya.
     *
     * Daftarnya sengaja tertutup: hanya tiga nilai ini, bukan "kelas apa pun
     * yang berawalan ql-". Kelas bebas dari isian orang adalah pintu masuk
     * gaya yang bisa menyamarkan isi halaman.
     */
    private const KELAS_DIIZINKAN = ['ql-align-center', 'ql-align-right', 'ql-align-justify'];

    /**
     * Saring HTML jadi versi yang aman disimpan & dirender.
     * Mengembalikan null bila hasilnya tidak berisi apa pun (mis. hanya "<p><br></p>"
     * yang dikirim Quill saat editor dibiarkan kosong) — kolom lebih baik NULL
     * daripada menyimpan markup kosong yang bikin blok kosong di halaman.
     */
    public static function saring(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');

        // Quill mengirim potongan HTML tanpa <html>/<body>, dan isinya bisa
        // mengandung karakter yang bikin libxml mengeluh. Kesalahan parsing
        // ditelan: yang tidak bisa dibaca cukup tidak muncul di hasil.
        $sebelumnya = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="akar">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($sebelumnya);

        // XPath, bukan getElementById(): tanpa DTD, libxml tidak mendaftarkan
        // atribut id sebagai ID sejati sehingga getElementById() bisa null
        // walau elemennya ada.
        $akar = (new \DOMXPath($dom))->query('//div[@id="akar"]')->item(0);
        if (! $akar instanceof \DOMElement) {
            return null;
        }

        self::bersihkanAnak($akar);

        $hasil = '';
        foreach (iterator_to_array($akar->childNodes) as $anak) {
            $hasil .= $dom->saveHTML($anak);
        }

        $hasil = trim($hasil);

        // Markup tanpa teks apa pun = kosong. Quill mengirim "<p><br></p>" untuk
        // editor yang dibiarkan kosong; menyimpannya berarti halaman FAQ
        // menampilkan blok kosong di bawah jawaban ringkas.
        //
        // KECUALI bila isinya gambar. Catatan wawancara yang hanya berupa
        // potret lembar penilaian tidak punya satu huruf pun teks, tapi justru
        // itulah isinya — membuangnya berarti menghapus catatan yang barusan
        // ditulis orang.
        if (self::keTeks($hasil) === '' && ! str_contains($hasil, '<img')) {
            return null;
        }

        return $hasil;
    }

    /**
     * HTML → teks polos. Dipakai untuk pencarian sisi server dan ringkasan
     * (mis. meta description) tanpa ikut membawa tag.
     */
    public static function keTeks(?string $html): string
    {
        // Tag diganti SPASI, bukan dihapus: "…MT:</p><p>Form 1…" kalau tag-nya
        // dihapus jadi "MT:Form 1" — dua kata melekat, dan pencarian kata
        // "Form" tidak lagi menemukannya.
        $teks = preg_replace('/<[^>]*>/', ' ', (string) $html);
        $teks = html_entity_decode($teks, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $teks));
    }

    /** Telusuri anak-anak sebuah node dan bereskan yang tidak diizinkan. */
    private static function bersihkanAnak(\DOMNode $induk): void
    {
        // Snapshot: daftar anak berubah saat node dibuang/di-unwrap.
        foreach (iterator_to_array($induk->childNodes) as $anak) {
            if ($anak instanceof \DOMText) {
                continue;
            }

            // Komentar, CDATA, processing instruction — tidak ada gunanya di sini.
            if (! ($anak instanceof \DOMElement)) {
                $induk->removeChild($anak);
                continue;
            }

            $tag = strtolower($anak->nodeName);

            // <script>/<style> dibuang TOTAL (termasuk isinya). Kalau di-unwrap
            // seperti tag lain, isi skrip malah bocor jadi teks yang tampil.
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                $induk->removeChild($anak);
                continue;
            }

            // Bersihkan isinya lebih dulu supaya unwrap tidak menyisakan sampah.
            self::bersihkanAnak($anak);

            if (! in_array($tag, self::TAG_DIIZINKAN, true)) {
                self::unwrap($anak);
                continue;
            }

            self::bersihkanAtribut($anak, $tag);
        }
    }

    /** Ganti sebuah elemen dengan anak-anaknya (tag hilang, isi tetap). */
    private static function unwrap(\DOMElement $el): void
    {
        $induk = $el->parentNode;
        if (! $induk) {
            return;
        }

        while ($el->firstChild) {
            $induk->insertBefore($el->firstChild, $el);
        }

        $induk->removeChild($el);
    }

    /**
     * Buang semua atribut; hanya `href` pada <a> dan `src`/`alt` pada <img>
     * yang dipertahankan.
     */
    private static function bersihkanAtribut(\DOMElement $el, string $tag): void
    {
        $href = $tag === 'a' ? trim((string) $el->getAttribute('href')) : '';
        $src = $tag === 'img' ? trim((string) $el->getAttribute('src')) : '';
        $alt = $tag === 'img' ? trim((string) $el->getAttribute('alt')) : '';

        // Perataan Quill disaring dari daftar kelas yang ada, bukan diambil
        // mentah: satu elemen bisa membawa banyak kelas sekaligus dan hanya
        // yang ada di daftar-izin yang boleh bertahan.
        $kelas = array_values(array_intersect(
            preg_split('/\s+/', trim((string) $el->getAttribute('class'))) ?: [],
            self::KELAS_DIIZINKAN,
        ));

        foreach (iterator_to_array($el->attributes ?? []) as $atribut) {
            $el->removeAttribute($atribut->nodeName);
        }

        if ($kelas) {
            $el->setAttribute('class', implode(' ', $kelas));
        }

        if ($tag === 'img') {
            // Gambar yang bukan milik kita DIBUANG SELURUHNYA, tidak di-unwrap:
            // <img> tak punya isi teks yang bisa diselamatkan, dan menyisakan
            // elemen tanpa src hanya membuat ikon rusak di tengah catatan.
            if (! preg_match(self::POLA_SRC_GAMBAR, $src)) {
                $el->parentNode?->removeChild($el);

                return;
            }

            $el->setAttribute('src', $src);
            $el->setAttribute('alt', $alt !== '' ? $alt : 'Lampiran catatan');
            // Gambar catatan kerap potret ponsel beresolusi penuh; tanpa ini ia
            // menjebol lebar kolom tempat catatannya ditampilkan.
            $el->setAttribute('loading', 'lazy');

            return;
        }

        if ($tag !== 'a') {
            return;
        }

        if (! self::hrefAman($href)) {
            // Tautan tanpa alamat yang sah tidak layak jadi tautan — jadikan teks biasa.
            self::unwrap($el);

            return;
        }

        $el->setAttribute('href', $href);
        // Tautan keluar dari situs karir: buka di tab baru dan potong akses
        // window.opener supaya halaman tujuan tidak bisa menyetir tab kita.
        $el->setAttribute('target', '_blank');
        $el->setAttribute('rel', 'noopener noreferrer');
    }

    private static function hrefAman(string $href): bool
    {
        if ($href === '') {
            return false;
        }

        // Jangkar internal & path relatif aman (tidak bisa jadi vektor skrip).
        if (str_starts_with($href, '#') || str_starts_with($href, '/')) {
            return true;
        }

        $skema = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        return in_array($skema, self::SKEMA_DIIZINKAN, true);
    }
}
