/**
 * ALAMAT WEB POLOS → TAUTAN YANG BISA DIKLIK, untuk catatan dari tim.
 *
 * Admin biasanya menempelkan link Zoom/Meet sebagai teks biasa di editor —
 * tanpa menekan tombol tautan. Di email alamat itu sudah jadi tautan (EVO Mail
 * mengubahnya sendiri), tapi di portal ia tampil sebagai teks mati yang harus
 * disalin kandidat ke peramban. Padahal justru itulah yang ia cari.
 *
 * Yang diubah hanya SIMPUL TEKS, bukan HTML mentah lewat regex: atribut, dan
 * tautan yang memang sudah ada, tidak ikut tersentuh. Dibaca lewat <template>
 * supaya tidak ada gambar yang dimuat atau skrip yang jalan selama diurai.
 * Penyaringan tetap tugas KontenAman — keluaran ini masuk ke sana.
 */
const POLA = /\bhttps?:\/\/[^\s<>"']+/gi;

// Tanda baca penutup kalimat bukan bagian alamat: "…/landing." → "…/landing".
const EKOR = /[.,;:!?)\]}]+$/;

export function tautkan(html) {
    const isi = String(html || '');
    if (!/https?:\/\//i.test(isi) || typeof document === 'undefined') {
        return isi;
    }

    const wadah = document.createElement('template');
    wadah.innerHTML = isi;

    const sasaran = [];
    const jalan = document.createTreeWalker(wadah.content, NodeFilter.SHOW_TEXT);
    while (jalan.nextNode()) {
        const n = jalan.currentNode;
        POLA.lastIndex = 0;
        if (!n.parentElement?.closest('a') && POLA.test(n.nodeValue)) {
            sasaran.push(n);
        }
    }

    for (const n of sasaran) {
        const teks = n.nodeValue;
        const pecahan = document.createDocumentFragment();
        let dari = 0;

        POLA.lastIndex = 0;
        for (const m of teks.matchAll(POLA)) {
            const ekor = (m[0].match(EKOR) || [''])[0];
            const url = ekor ? m[0].slice(0, -ekor.length) : m[0];

            pecahan.append(teks.slice(dari, m.index));
            const a = document.createElement('a');
            a.href = url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.textContent = url;
            pecahan.append(a);
            dari = m.index + url.length;
        }
        pecahan.append(teks.slice(dari));
        n.replaceWith(pecahan);
    }

    return wadah.innerHTML;
}
