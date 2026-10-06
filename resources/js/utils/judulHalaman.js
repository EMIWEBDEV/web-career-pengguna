/*
 * JUDUL TAB PERAMBAN — "{Nama Halaman} | Careers Evo Group"
 * ---------------------------------------------------------------------------
 * Dipakai lewat opsi `title` di createInertiaApp (resources/js/app.js), jadi
 * SETIAP halaman memakai format yang sama tanpa perlu mengulang akhirannya di
 * tiap komponen. Halaman cukup menulis:
 *
 *     <Head title="Master Kampus" />
 *
 * dan tab menampilkan "Master Kampus | Careers Evo Group".
 *
 * PENTING — bentuk <Head><title>...</title></Head> TIDAK melewati fungsi ini.
 * Inertia hanya menerapkan callback `title` pada PROP `title`; isi slot
 * dipasang apa adanya. Itulah sebabnya seluruh halaman diseragamkan memakai
 * prop, bukan slot.
 *
 * Nama situs dan pemisahnya dibaca dari <meta> yang dicetak Blade
 * (resources/views/components/seo.blade.php), yang isinya berasal dari
 * config/seo.php. Dengan begitu sisi server dan sisi peramban mustahil
 * berbeda: mengganti nama situs cukup di satu berkas config.
 */

function bacaMeta(nama, cadangan) {
    if (typeof document === 'undefined') return cadangan;

    const isi = document.querySelector(`meta[name="${nama}"]`)?.content;

    return isi && isi.trim() !== '' ? isi : cadangan;
}

export const namaSitus = bacaMeta('app-site-name', 'Careers Evo Group');
export const pemisahJudul = bacaMeta('app-title-separator', ' | ');

/**
 * Susun judul tab lengkap dari judul halaman.
 *
 * Cerminan persis App\Support\Seo\SeoMeta::composeTitle() di sisi PHP —
 * termasuk penjagaannya: judul yang sudah memuat nama situs tidak ditempeli
 * lagi, supaya tidak pernah muncul "Careers Evo Group | Careers Evo Group"
 * pada halaman yang judulnya memang nama situs itu sendiri.
 */
export function judulHalaman(judul) {
    const bersih = (judul ?? '').trim();

    if (bersih === '') return namaSitus;
    if (bersih.toLowerCase().includes(namaSitus.toLowerCase())) return bersih;

    return `${bersih}${pemisahJudul}${namaSitus}`;
}

export default judulHalaman;
