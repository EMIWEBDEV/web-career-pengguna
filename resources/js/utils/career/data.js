// ══════════════════════════════════════════════════════════════════
// WEB CAREER — shared helpers (formatters, status, navigation, URLs)
// Modul portabel: seluruh fitur Web Career ada di folder `Pages/Career/`.
// Pindahkan folder ini (+ route & controller Career) untuk memindahkan modul.
// ══════════════════════════════════════════════════════════════════
import { router, usePage } from '@inertiajs/vue3';

export const CAREER_HOME = '/'; // halaman utama (landing) — route root
export const CAREER_LANDING = '/karir/landing-page'; // basis sub-route detail (lowongan/mt)
export const lowonganUrl = (id) => `${CAREER_LANDING}/lowongan/${id}`;
export const mtUrl = (id) => `${CAREER_LANDING}/mt/${id}`;

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export function formatDate(iso) {
    if (!iso) return '-';
    const d = new Date(String(iso).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return iso;
    return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
}

// Tanggal + jam — window pendaftaran (buka/tutup) WAJIB tampil dgn waktunya.
export function formatDateTime(iso) {
    if (!iso) return '-';
    const d = new Date(String(iso).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return iso;
    const jam = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()} · ${jam}`;
}

export function daysLeft(iso) {
    if (!iso) return 999;
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    return Math.ceil((new Date(String(iso).replace(' ', 'T')).getTime() - now.getTime()) / 86400000);
}

export function deadlineLabel(iso) {
    if (!iso) return 'Tanpa batas waktu'; // lowongan EVERGREEN (di-share terus)
    const d = daysLeft(iso);
    if (d < 0) return 'Ditutup';
    if (d === 0) return 'Tutup hari ini';
    if (d === 1) return 'Tutup besok';
    return `${d} hari lagi`;
}

export function typeClass(type) {
    if (type === 'Internship') return 'wc-badge--intern';
    if (type === 'Contract') return 'wc-badge--contract';
    return 'wc-badge--full';
}

/**
 * Kode tipe tahap — kalimat yang dibaca PELAMAR.
 *
 * Daftarnya harus memuat SELURUH kode di Master Tipe Tahap. Sebelumnya hanya
 * delapan yang terdaftar, sementara masternya berisi empat belas; enam sisanya
 * jatuh ke cadangan `|| tipe` dan tercetak apa adanya di halaman publik:
 * "PHONE_SCREEN", "BACKGROUND_CHECK", "NEGOTIATION". Kode internal bergaris
 * bawah bukan keterangan — ia terbaca seperti kebocoran sistem, tepat di
 * halaman yang seharusnya meyakinkan orang untuk melamar.
 *
 * Cadangannya sekarang MERAPIKAN, bukan menampilkan mentah: kode yang belum
 * terdaftar di sini muncul sebagai "Phone Screen", bukan "PHONE_SCREEN".
 * Dengan begitu tipe baru yang ditambahkan lewat master tetap terbaca wajar
 * sebelum sempat diterjemahkan di sini.
 */
export function stageTypeLabel(tipe) {
    const peta = {
        FORM: 'Pengisian formulir & dokumen',
        ADMIN_SCREENING: 'Seleksi administrasi',
        HCLEARN_TEST: 'Tes online (HCLearn)',
        INTERVIEW: 'Wawancara',
        FGD: 'Diskusi kelompok (FGD)',
        DOCUMENT: 'Kelengkapan dokumen',
        DECISION: 'Keputusan & pengumuman',
        OFFERING: 'Penawaran kerja',
        ONBOARDING: 'Onboarding',
        PHONE_SCREEN: 'Wawancara telepon',
        TES_OFFLINE_MANUAL: 'Tes tertulis (luring)',
        MCU: 'Pemeriksaan kesehatan',
        REFERENCE_CHECK: 'Pengecekan referensi',
        BACKGROUND_CHECK: 'Verifikasi latar belakang',
        NEGOTIATION: 'Pembahasan penawaran',
        CONTRACT_SIGNING: 'Penandatanganan kontrak',
    };

    if (peta[tipe]) return peta[tipe];
    if (!tipe) return '';

    // BACKGROUND_CHECK -> "Background Check"
    return String(tipe)
        .toLowerCase()
        .split('_')
        .filter(Boolean)
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

/*
 * TIDAK ADA isFull()/kuotaPct() lagi.
 *
 * Landing publik tidak menerima `kuota`/`kuotaTerisi` dari backend, jadi kedua
 * helper itu tidak punya bahan: satu-satunya yang menutup lowongan di mata
 * publik adalah TANGGAL TUTUP (lihat visibleLowongan di CareerLandingController,
 * yang memang sudah menyaringnya). Kuota tetap ditegakkan di sisi internal —
 * LamaranService menolak penerimaan begitu kursi posisi habis.
 */

/**
 * "Palembang · On-site" — lokasi penempatan digabung tipe tempat kerja.
 * Sebagian `Program_Posisi.Lokasi` memang diisi tipe tempat kerja ("On-site
 * (WFO)"), sehingga penggabungan mentah menghasilkan "On-site (WFO) · On-site
 * (WFO)". Yang kembar cukup ditulis sekali.
 */
export function lokasiLabel(item) {
    const lokasi = (item?.lokasi || '').trim();
    const tempat = (item?.tempatKerja || '').trim();
    if (!tempat || !lokasi) return lokasi || tempat || '—';
    const sama = (s) => s.toLowerCase().replace(/[^a-z0-9]/g, '');
    if (sama(lokasi) === sama(tempat) || sama(lokasi).includes(sama(tempat))) return lokasi;
    return `${lokasi} · ${tempat}`;
}

export function statusLabel(status) {
    return { BUKA: 'Pendaftaran Dibuka', SEGERA: 'Segera Dibuka' }[status] || status;
}

export function statusClass(status) {
    return { BUKA: 'wc-st--open', SEGERA: 'wc-st--soon' }[status] || 'wc-st--open';
}

// ── Navigation (works from landing & from inner/detail pages) ──
function onLanding() {
    if (typeof window === 'undefined') return false;
    const p = window.location.pathname;
    return p === CAREER_HOME || p === CAREER_LANDING;
}

// Smooth-scroll berbasis requestAnimationFrame.
// Penting: pada Windows dengan "Show animations" mati (prefers-reduced-motion: reduce),
// browser MENGABAIKAN `behavior:'smooth'` & CSS `scroll-behavior:smooth` → scroll jadi lompat.
// Animasi manual ini tetap mulus tanpa bergantung pada setting OS tersebut.
let wcScrollRAF = null;
export function animateScroll(targetY, duration = 720) {
    if (typeof window === 'undefined') return;
    const startY = window.scrollY || window.pageYOffset;
    const maxY = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
    const destY = Math.max(0, Math.min(targetY, maxY));
    const dist = destY - startY;
    if (Math.abs(dist) < 2) return;
    if (wcScrollRAF) cancelAnimationFrame(wcScrollRAF);
    // easeInOutCubic — akselerasi lembut lalu melambat elegan
    const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
    let start = null;
    const step = (ts) => {
        if (start === null) start = ts;
        const p = Math.min((ts - start) / duration, 1);
        window.scrollTo(0, startY + dist * ease(p));
        if (p < 1) wcScrollRAF = requestAnimationFrame(step);
        else wcScrollRAF = null;
    };
    wcScrollRAF = requestAnimationFrame(step);
}

export function scrollToId(id, offset = 84) {
    const el = document.getElementById(id);
    if (!el) return;
    const y = el.getBoundingClientRect().top + (window.scrollY || window.pageYOffset) - offset;
    animateScroll(y);
}

/**
 * REVEAL-ON-SCROLL BERSAMA — tandai `.wc-reveal` begitu masuk layar.
 *
 * Penandanya ATRIBUT `data-in`, bukan kelas. Kelas yang dipasang dari luar Vue
 * akan lenyap begitu elemen yang sama punya :class dinamis lalu dirender ulang —
 * Vue menimpa className elemen itu seutuhnya. Uraiannya di evo-theme.css.
 *
 * ══ KENAPA IKUT MENGAMATI ELEMEN YANG LAHIR BELAKANGAN ══════════════════════
 *
 * Versi sebelumnya memindai DOM SEKALI, saat dipanggil. Itu cukup selama
 * seluruh isi halaman sudah ada sejak awal — dan tidak cukup begitu ada bagian
 * yang muncul menyusul.
 *
 * Kegagalannya nyata dan sulit dilacak: section Management Trainee digambar
 * `v-if="programMt.length"`. Saat landing dibuka sementara belum ada program MT
 * terbit, section itu tidak ada di DOM, jadi tidak pernah ikut diamati. Begitu
 * admin menerbitkan program MT dan Inertia memperbarui props tanpa memuat ulang
 * halaman, section-nya MUNCUL di DOM — tapi tidak ada yang memberinya `data-in`.
 * Ia berdiri di sana dengan `opacity: 0`: memakan tinggi, tidak terlihat sedikit
 * pun. Di layar hasilnya adalah CELAH KOSONG di antara dua section — dan yang
 * dilaporkan orang adalah "program MT-nya hilang", bukan "tidak muncul", sebab
 * dari luar keduanya memang tidak bisa dibedakan.
 *
 * Sekarang MutationObserver menjaga pintunya: apa pun yang menyusul masuk DOM
 * ikut diamati.
 *
 * ══ ISI TIDAK BOLEH TERSANDERA ANIMASINYA ══════════════════════════════════
 *
 * Bila IntersectionObserver tidak ada, versi lama memulangkan null — dan tidak
 * ada satu pun yang pernah menandai `data-in`. Seluruh halaman tetap pada
 * `opacity: 0`: kosong total, tanpa galat, tanpa petunjuk. Animasi adalah
 * hiasan; ketiadaannya tidak boleh menghapus isinya. Karena itu, bila
 * pengamatnya tak tersedia — atau pembacanya minta gerak dikurangi — seluruh
 * elemen langsung ditandai tampil.
 *
 * @returns {{disconnect: () => void}} penutup untuk onUnmounted.
 */
export function observeReveal(selector = '.wc-reveal') {
    const kosong = { disconnect() {} };
    if (typeof window === 'undefined' || typeof document === 'undefined') return kosong;

    const tampilkan = (el) => el.setAttribute('data-in', '');
    const semua = () => document.querySelectorAll(selector);

    // Gerak dikurangi = tampilkan apa adanya. Menganimasikannya tetap berarti
    // mengabaikan setelan sistem yang dipasang orang justru karena gerakan
    // membuatnya pusing.
    const diamSaja = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;

    if (!('IntersectionObserver' in window) || !('MutationObserver' in window) || diamSaja) {
        semua().forEach(tampilkan);

        // Tetap pasang penjaga sederhana untuk yang menyusul, supaya bagian yang
        // lahir belakangan tidak ikut tertinggal tak terlihat.
        if ('MutationObserver' in window) {
            const mo = new MutationObserver(() => semua().forEach(tampilkan));
            mo.observe(document.body, { childList: true, subtree: true });

            return { disconnect: () => mo.disconnect() };
        }

        return kosong;
    }

    const io = new IntersectionObserver(
        (entries) =>
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    tampilkan(e.target);
                    io.unobserve(e.target);
                }
            }),
        { threshold: 0.12 },
    );

    // `data-in` dipakai sebagai penanda "sudah diurus", jadi memanggil ini
    // berkali-kali pada elemen yang sama tidak menumpuk pengamatan.
    const amati = (el) => {
        if (!el.hasAttribute('data-in')) io.observe(el);
    };

    semua().forEach(amati);

    const mo = new MutationObserver((mutasi) => {
        mutasi.forEach((m) => {
            m.addedNodes.forEach((n) => {
                if (n.nodeType !== 1) return;
                if (n.matches?.(selector)) amati(n);
                // Section yang baru muncul membawa anak-anaknya sekaligus —
                // simpulnya sendiri belum tentu ber-.wc-reveal.
                n.querySelectorAll?.(selector).forEach(amati);
            });
        });
    });
    mo.observe(document.body, { childList: true, subtree: true });

    return {
        disconnect() {
            io.disconnect();
            mo.disconnect();
        },
    };
}

export function goToSection(id) {
    if (onLanding()) {
        scrollToId(id);
    } else {
        try {
            sessionStorage.setItem('wcScrollTarget', id);
        } catch (e) {
            /* ignore */
        }
        router.visit(CAREER_HOME);
    }
}

export function goHome() {
    if (onLanding()) {
        animateScroll(0);
    } else {
        router.visit(CAREER_HOME);
    }
}

/**
 * Status login kandidat, dari sesi SERVER (prop Inertia `careerAuth`) —
 * bukan sessionStorage, yang isinya bisa dikarang dari devtools.
 * Dipakai hanya untuk memilih tampilan; gerbang sebenarnya ada di middleware
 * `career.auth` pada route /karir/apply/{id}.
 */
export function sudahLogin() {
    const auth = usePage()?.props?.careerAuth;
    return !!(auth && auth.id);
}

export function applyUrl(card) {
    // Menerima objek kartu ATAU id langsung.
    const id = card && typeof card === 'object' ? card.id : card;
    return `/karir/apply/${id || 'RC-2026-001'}`;
}

export function goApply(card) {
    // Formulir pendaftaran memakai DESAIN WIZARD di ApplyForm
    // (Data Diri → Pendidikan → Verifikasi foto → Finalisasi). Route:
    // /karir/apply/{id}.
    //
    // BELUM LOGIN → jangan kirim ke formulir. Route apply dijaga `career.auth`,
    // jadi tamu memang akan dipulangkan, tapi memulangkannya dari sini membuat
    // perjalanannya jujur: satu langkah ke halaman masuk, dengan `?redirect=`
    // menunjuk balik ke lowongan yang barusan diklik — bukan berkedip lewat
    // formulir yang sebenarnya tidak pernah boleh dia buka.
    const tujuan = applyUrl(card);
    if (!sudahLogin()) {
        router.visit('/login?redirect=' + encodeURIComponent(tujuan));
        return;
    }
    router.visit(tujuan);
}
