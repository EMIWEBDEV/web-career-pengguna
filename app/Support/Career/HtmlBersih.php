<?php

namespace App\Support\Career;

/**
 * WEB CAREERS — HTML kaya (jawaban FAQ, catatan) menjadi teks polos.
 *
 * HTML-nya disaring dengan daftar-izin SAAT DISIMPAN di zona dalam (admin);
 * project ini hanya membacanya. Di peramban, isi yang dirender dengan v-html
 * tetap melewati DOMPurify (KontenAman.vue, FaqAccordion.vue) sebagai lapisan
 * kedua.
 */
class HtmlBersih
{
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
}
