<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Halaman Perkenalan Tim  (route: /karir/tim/{slug})
     Contoh isi: tim Information Technology.
     CATATAN: seluruh teks & gambar ditulis langsung di file ini
     (tanpa props/array JS) supaya mudah disesuaikan manual.
     Foto tiap momen tim dipasang lewat :style pada elemennya
     (lihat TimSection.vue), bukan url() di CSS.
     ══════════════════════════════════════════════════════════ -->
<template>
    <Head :title="judulTab" />

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <div class="dt">
            <button type="button" class="wc-back" @click="goToSection('tim')">
                <i class="bi bi-arrow-left"></i> Kembali ke Fungsi Perusahaan
            </button>

            <!-- ══════════ KONTEN DINAMIS (Master Info Divisi) ══════════ -->
            <template v-if="tim">
                <!-- ── HERO ──────────────────────────────────────── -->
                <header class="dt-hero wc-reveal">
                    <!-- Foto hero hanya digambar bila divisinya punya sampul.
                         Tanpa foto, TIDAK ada kotak berwatermark yang menggantikan
                         — judul & keterangannya berdiri di atas latar polos, dan
                         itu sudah cukup. Kotak berlogo hanya mengisi ruang tanpa
                         memberi tahu apa pun tentang divisinya. -->
                    <template v-if="tim.img?.header">
                        <div
                            class="dt-hero__photo"
                            :style="{ backgroundImage: `url('${tim.img.header}')` }"
                        ></div>
                        <div class="dt-hero__scrim"></div>
                    </template>
                    <div class="dt-hero__body">
                        <span class="dt-hero__eyebrow"><span class="dt-dot"></span> Fungsi Perusahaan</span>
                        <h1>{{ tim.nama }}</h1>
                        <p v-if="tim.deskripsiSingkat">{{ tim.deskripsiSingkat }}</p>
                        <div class="dt-hero__stats">
                            <div class="dt-hero__stat-group">
                                <div><strong>{{ tim.stats?.subFungsi ?? subFungsi.length }}</strong><small>Sub-fungsi</small></div>
                                <span class="dt-hero__sep"></span>
                                <div><strong>{{ lowonganTampil.length }}</strong><small>Lowongan terbuka</small></div>
                            </div>
                            <button type="button" class="dt-hero__btn" @click="scrollToLowongan">
                                <i class="bi bi-briefcase-fill"></i> Lihat Lowongan <i class="bi bi-arrow-down-short"></i>
                            </button>
                        </div>
                    </div>
                </header>

                <!-- ── TENTANG TIM ───────────────────────────────── -->
                <section v-if="tim.judulUtama || tim.deskripsiDetail || tim.poin?.length || galeri.length" class="dt-section">
                    <div class="dt-about">
                        <div class="dt-about__text wc-reveal">
                            <h2>{{ tim.judulUtama || `Tentang tim ${tim.nama}` }}</h2>
                            <p v-if="tim.deskripsiDetail" style="white-space: pre-line">{{ tim.deskripsiDetail }}</p>
                            <ul v-if="tim.poin?.length" class="dt-points">
                                <li v-for="(p, i) in tim.poin" :key="i">
                                    <i class="bi bi-check-circle-fill"></i> {{ p }}
                                </li>
                            </ul>
                        </div>

                        <div v-if="galeri.length" class="dt-about__media wc-reveal" style="--d: 90ms">
                            <!-- Tanpa cabang placeholder: `galeri` kini hanya
                                 berisi foto yang memang ada. -->
                            <div
                                v-for="(g, i) in galeri"
                                :key="i"
                                class="dt-shot"
                                :style="{ backgroundImage: `url('${g}')` }"
                            ></div>
                        </div>
                    </div>
                </section>

                <!-- ── SUB-FUNGSI ────────────────────────────────── -->
                <section v-if="subFungsi.length" class="dt-section">
                    <div class="dt-head wc-reveal">
                        <h2>Sub-fungsi di tim ini</h2>
                        <p>Bagian-bagian yang bekerja sama menjalankan fungsi {{ tim.nama }}.</p>
                    </div>
                    <div class="dt-subgrid wc-reveal" style="--d: 70ms">
                        <article
                            v-for="(s, i) in subFungsi"
                            :key="s.nama"
                            class="dt-subcard"
                            :class="{ 'is-nofoto': !s.img }"
                        >
                            <!-- Bidang gambar hanya digambar bila fotonya ADA.
                                 Sub-fungsi tanpa foto tetap tampil — nama dan
                                 keterangannya yang penting — tapi tanpa kotak
                                 ungu berlogo yang tidak menerangkan apa pun.
                                 Kartunya lalu ditandai `is-nofoto` supaya
                                 sudut atasnya tetap membulat. -->
                            <div
                                v-if="s.img"
                                class="dt-subcard__img"
                                :style="{ backgroundImage: `url('${s.img}')` }"
                            ></div>
                            <div class="dt-subcard__body">
                                <h3>{{ s.nama }}</h3>
                                <p v-if="s.deskripsi">{{ s.deskripsi }}</p>
                            </div>
                        </article>
                    </div>
                </section>
            </template>

            <!-- ══════════ FALLBACK STATIS (Master Info Divisi belum diisi) ══════════ -->
            <template v-else>
                <!-- ── HERO ──────────────────────────────────────── -->
                <header class="dt-hero wc-reveal">
                    <!-- Sepola hero dinamis di atas: tanpa foto, tanpa kotak
                         watermark pengganti. -->
                    <div class="dt-hero__body">
                        <span class="dt-hero__eyebrow"><span class="dt-dot"></span> Fungsi Perusahaan</span>
                        <h1>Information Technology</h1>
                        <p>
                            Tim yang menjaga agar seluruh sistem, data, dan alat kerja di Evo berjalan lancar —
                            dari lantai produksi sampai aplikasi yang dipakai tim lapangan setiap hari.
                        </p>
                        <div class="dt-hero__stats">
                            <div class="dt-hero__stat-group">
                                <div><strong>12</strong><small>Anggota tim</small></div>
                                <span class="dt-hero__sep"></span>
                                <div><strong>4</strong><small>Sub-fungsi</small></div>
                                <span class="dt-hero__sep"></span>
                                <div><strong>3</strong><small>Lowongan terbuka</small></div>
                            </div>
                            <button type="button" class="dt-hero__btn" @click="scrollToLowongan">
                                <i class="bi bi-briefcase-fill"></i> Lihat Lowongan <i class="bi bi-arrow-down-short"></i>
                            </button>
                        </div>
                    </div>
                </header>

                <!-- ── TENTANG TIM ───────────────────────────────── -->
                <section class="dt-section">
                    <div class="dt-about">
                        <div class="dt-about__text wc-reveal">
                            <h2>Bukan sekadar tim pendukung, tapi rekan berpikir.</h2>
                            <p>
                                Tim IT Evo terlibat sejak awal ketika sebuah proses ingin diperbaiki. Kami duduk bersama tim
                                produksi, gudang, dan penjualan untuk memahami masalahnya dulu, baru menentukan solusi
                                teknologinya — bukan sebaliknya.
                            </p>
                            <p>
                                Sehari-hari kami mengurus sistem internal, integrasi data antar cabang, perangkat di pabrik,
                                sampai aplikasi rekrutmen yang sedang kamu buka sekarang.
                            </p>
                            <ul class="dt-points">
                                <li><i class="bi bi-check-circle-fill"></i> Membangun & merawat aplikasi internal perusahaan</li>
                                <li><i class="bi bi-check-circle-fill"></i> Menjaga infrastruktur, jaringan, dan keamanan data</li>
                                <li><i class="bi bi-check-circle-fill"></i> Mendampingi tim lain mengadopsi alat kerja baru</li>
                            </ul>
                        </div>

                        <div class="dt-about__media wc-reveal" style="--d: 90ms">
                            <div class="dt-shot dt-shot--a"></div>
                            <div class="dt-shot dt-shot--b"></div>
                            <div class="dt-shot dt-shot--c"></div>
                        </div>
                    </div>
                </section>
            </template>

            <!-- ── LOWONGAN DI TIM INI (dinamis & fallback) ──────── -->
            <section id="section-lowongan" class="dt-section">
                <div class="dt-head dt-head--row wc-reveal">
                    <div>
                        <h2>Lowongan di tim ini</h2>
                        <p>Posisi yang sedang kami cari untuk memperkuat tim {{ namaTim }}.</p>
                    </div>
                    <span class="dt-count"><i class="bi bi-briefcase-fill"></i> {{ lowonganTampil.length }} lowongan terbuka</span>
                </div>

                <div v-if="lowonganTampil.length" class="rek__grid wc-reveal" style="--d: 70ms">
                    <LowonganCard
                        v-for="job in lowonganTampil"
                        :key="job.id"
                        :job="job"
                        :is-saved="isSaved(job.id)"
                        @toggle-save="toggleSaveJob"
                    />
                </div>
                <div v-else class="dt-empty wc-reveal">
                    <i class="bi bi-clipboard-x"></i>
                    Belum ada lowongan terbuka di tim ini. Pantau terus halaman karir kami.
                </div>
            </section>


        



            <!-- ── CTA ───────────────────────────────────────────── -->
            <section class="dt-section">
                <div class="ctal__panel wc-reveal">
                    <span class="ctal__eyebrow"><i class="bi bi-stars"></i> Mulai Perjalananmu</span>
                    <h2>Siap naik level bersama <span>EVO Group?</span></h2>
                    <p>Ambil langkah pertama menuju karier impianmu hari ini — proses transparan, tim yang suportif, dan ruang untuk bertumbuh.</p>

                    <div class="ctal__actions">
                        <Link class="ctal__btn ctal__btn--primary" href="/karir/lowongan">
                            <i class="bi bi-search"></i> Lihat Semua Lowongan
                        </Link>
                        <Link class="ctal__btn ctal__btn--ghost" href="/register">
                            <i class="bi bi-person-plus"></i> Buat Akun
                        </Link>
                    </div>

                    <ul class="ctal__trust">
                        <li><i class="bi bi-check-lg"></i> Proses seleksi transparan</li>
                        <li><i class="bi bi-check-lg"></i> Respons cepat</li>
                        <li><i class="bi bi-check-lg"></i> Lingkungan suportif</li>
                    </ul>
                </div>
            </section>
        </div>
    </CareerLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import LowonganCard from './components/LowonganCard.vue';
import { goToSection, observeReveal } from '@utils/career/data';

defineOptions({ layout: null });

// CONTOH ISI — hanya dipakai mode FALLBACK statis (Master Info Divisi kosong).
// Bentuk objeknya mengikuti kartu lowongan di halaman "Semua Lowongan".
const contohLowongan = [
    {
        id: 'RC-2026-011',
        posisi: 'Backend Developer',
        tipeKerja: 'Full-time',
        unggulan: true,
        benefit: ['Gaji + THR', 'BPJS', 'Laptop kerja', 'Pelatihan'],
        ringkasan: 'Membangun dan merawat layanan internal perusahaan, mulai dari sistem rekrutmen sampai integrasi data antar cabang.',
        lokasi: 'Palembang',
        tempatKerja: 'On-site (WFO)',
        pengalaman: 'Min. 1 - 2 Tahun',
        skill: ['Laravel', 'MySQL', 'REST API', 'Git'],
        pelamar: 8,
    },
    {
        id: 'RC-2026-012',
        posisi: 'Frontend Developer',
        tipeKerja: 'Full-time',
        unggulan: false,
        benefit: ['Gaji + THR', 'BPJS', 'Laptop kerja'],
        ringkasan: 'Menerjemahkan kebutuhan tim lain menjadi antarmuka yang rapi, cepat, dan nyaman dipakai setiap hari.',
        lokasi: 'Palembang',
        tempatKerja: 'On-site (WFO)',
        pengalaman: 'Min. 1 Tahun',
        skill: ['Vue.js', 'Inertia', 'CSS', 'Figma'],
        pelamar: 12,
    },
    {
        id: 'RC-2026-013',
        posisi: 'IT Support',
        tipeKerja: 'Contract',
        unggulan: false,
        benefit: ['Gaji + THR', 'BPJS'],
        ringkasan: 'Menangani kendala teknis harian di kantor dan pabrik, serta merawat perangkat kerja seluruh karyawan.',
        lokasi: 'Palembang',
        tempatKerja: 'On-site (WFO)',
        pengalaman: 'Fresh Graduate',
        skill: ['Troubleshooting', 'Jaringan', 'Hardware'],
        pelamar: 21,
    },
];

const props = defineProps({
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
    slug: { type: String, default: '' },
    // Konten dari Master Info Divisi. Null = tabel belum diisi → fallback statis.
    tim: { type: Object, default: null },
    subFungsi: { type: Array, default: () => [] },
    lowonganTim: { type: Array, default: () => [] },
});

const hasMt = computed(() => props.hasMt);
const offices = computed(() => props.offices || []);
const tim = computed(() => props.tim);
const subFungsi = computed(() => props.subFungsi || []);

/**
 * Foto galeri yang BENAR-BENAR ada — yang kosong dibuang, bukan diisi.
 *
 * Dulu larik ini dipulangkan apa adanya, termasuk slot yang null. Karena
 * `galeri.length` selalu 3, blok galerinya selalu digambar, dan tiap slot
 * kosong diisi kotak ungu berlogo EVO. Divisi yang belum mengunggah satu foto
 * pun tetap menampilkan tiga kotak — hiasan yang tidak memberi tahu apa-apa,
 * dan menghabiskan seluruh lebar layar di tempat yang seharusnya kosong.
 *
 * Sekarang: ada foto tampil, tidak ada tidak digambar sama sekali.
 */
const galeri = computed(() => {
    return [props.tim?.img?.utama, props.tim?.img?.img2, props.tim?.img?.img3]
        .filter((u) => !!u);
});



const namaTim = computed(() => props.tim?.nama || 'Information Technology');
const judulTab = computed(() => `Tim ${namaTim.value}`);

function scrollToLowongan() {
    const el = document.getElementById('section-lowongan');
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Mode dinamis pakai lowongan real per divisi; fallback pakai contoh statis.
const lowonganTampil = computed(() => (props.tim ? props.lowonganTim || [] : contohLowongan));

// 🔖 Manajemen Lowongan Disimpan (Bookmark) — tersinkron dengan localStorage ('evo_saved_jobs')
const savedJobIds = ref([]);

function loadSavedJobs() {
    try {
        const raw = localStorage.getItem('evo_saved_jobs');
        savedJobIds.value = raw ? JSON.parse(raw) : [];
    } catch (e) {
        savedJobIds.value = [];
    }
}

function isSaved(id) {
    return savedJobIds.value.includes(id);
}

function toggleSaveJob(id) {
    if (!id) return;
    const idx = savedJobIds.value.indexOf(id);
    if (idx > -1) {
        savedJobIds.value.splice(idx, 1);
    } else {
        savedJobIds.value.push(id);
    }
    try {
        localStorage.setItem('evo_saved_jobs', JSON.stringify(savedJobIds.value));
    } catch (e) {
        /* noop */
    }
}

let revealObs = null;
onMounted(() => {
    loadSavedJobs();
    nextTick(() => (revealObs = observeReveal()));
});
onUnmounted(() => revealObs?.disconnect());
</script>

<style scoped>
.dt {
    max-width: 1140px;
    margin: 0 auto;
    padding: 1.5rem 1.25rem 4rem;
}
.dt-section {
    margin-top: clamp(3rem, 7vw, 4.75rem);
}

/* ── Judul kecil & heading ───────────────────────────────── */
.dt-label {
    display: inline-block;
    margin-bottom: 0.6rem;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--indigo);
}
.dt-head {
    max-width: 44rem;
    margin-bottom: 1.9rem;
}
.dt h2 {
    margin: 0 0 0.6rem;
    font-size: clamp(1.5rem, 3vw, 2.05rem);
    font-weight: 900;
    letter-spacing: -0.025em;
    line-height: 1.25;
    color: var(--ink);
}
.dt-head p {
    margin: 0;
    color: var(--slate);
    font-size: 0.94rem;
    font-weight: 600;
    line-height: 1.65;
}

/* ── Hero ────────────────────────────────────────────────── */
.dt-hero {
    position: relative;
    display: flex;
    align-items: flex-end;
    min-height: clamp(22rem, 42vw, 28rem);
    margin-top: 1rem;
    overflow: hidden;
    border-radius: 1.75rem;
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%);
    box-shadow: 0 26px 58px rgba(79, 70, 229, 0.22);
}
.dt-hero__photo {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    transform: scale(1.02);
}
.dt-hero-watermark {
    position: absolute;
    right: clamp(1.5rem, 5vw, 4rem);
    top: 50%;
    transform: translateY(-50%);
    width: clamp(10rem, 25vw, 18rem);
    height: clamp(10rem, 25vw, 18rem);
    opacity: 0.12;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
    filter: brightness(2) drop-shadow(0 10px 30px rgba(255, 255, 255, 0.2));
}
.dt-hero-watermark img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.dt-hero__scrim {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(24, 22, 58, 0.15) 0%, rgba(24, 22, 58, 0.62) 55%, rgba(24, 22, 58, 0.9) 100%);
}
.dt-hero__body {
    position: relative;
    z-index: 1;
    padding: clamp(1.6rem, 4vw, 2.6rem);
    color: #f1f5f9;
}
.dt-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.24);
    -webkit-backdrop-filter: blur(6px);
    backdrop-filter: blur(6px);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}
.dt-dot {
    width: 0.42rem;
    height: 0.42rem;
    border-radius: 50%;
    background: #a78bfa;
}
.dt-hero h1 {
    margin: 1rem 0 0.7rem;
    font-size: clamp(1.9rem, 5vw, 3rem);
    font-weight: 900;
    letter-spacing: -0.03em;
    line-height: 1.1;
    color: #fff;
    text-shadow: 0 2px 16px rgba(15, 23, 42, 0.4);
}
.dt-hero__body > p {
    max-width: 40rem;
    margin: 0;
    color: rgba(238, 242, 248, 0.92);
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.7;
    text-shadow: 0 1px 10px rgba(15, 23, 42, 0.45);
}
.dt-hero__stats {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1.2rem;
    margin-top: 1.6rem;
    padding-top: 1.2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.22);
}
.dt-hero__stat-group {
    display: flex;
    align-items: center;
    gap: 1.3rem;
}
.dt-hero__btn {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.65rem 1.25rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #ffffff;
    font-size: 0.84rem;
    font-weight: 800;
    border: 1px solid rgba(255, 255, 255, 0.35);
    box-shadow: 0 8px 22px rgba(79, 70, 229, 0.38), inset 0 1px 0 rgba(255, 255, 255, 0.4);
    cursor: pointer;
    transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.25s ease, background 0.25s ease;
}
.dt-hero__btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 32px rgba(79, 70, 229, 0.52);
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
}
.dt-hero__btn:active {
    transform: translateY(0);
}
.dt-hero__btn i {
    font-size: 0.9rem;
}
.dt-hero__stats strong {
    display: block;
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1.1;
    color: #fff;
}
.dt-hero__stats small {
    font-size: 0.74rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.78);
}
.dt-hero__sep {
    width: 1px;
    height: 2.1rem;
    background: rgba(255, 255, 255, 0.24);
}

/* ── Tentang tim ─────────────────────────────────────────── */
.dt-about {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
    gap: clamp(1.5rem, 4vw, 2.75rem);
    align-items: center;
}
.dt-about__text p {
    margin: 0 0 0.9rem;
    color: var(--slate);
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.75;
}
.dt-points {
    margin: 1.3rem 0 0;
    padding: 0;
    list-style: none;
}
.dt-points li {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.5rem 0;
    color: var(--ink);
    font-size: 0.89rem;
    font-weight: 700;
}
.dt-points i {
    margin-top: 0.15rem;
    color: var(--indigo);
}

/* Kolase 3 foto */
.dt-about__media {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-template-rows: repeat(2, 9.5rem);
    gap: 0.9rem;
}
.dt-about__media .dt-shot:first-child {
    grid-row: span 2;
}

/* ── Kotak foto placeholder ──────────────────────────────── */
.dt-shot {
    position: relative;
    height: 100%;
    min-height: 8rem;
    overflow: hidden;
    border-radius: 1.1rem;
    background-color: #1e1b4b;
    background-size: cover;
    box-shadow: 0 14px 32px rgba(15, 23, 42, 0.12);
    transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
.dt-shot:hover {
    transform: translateY(-4px);
}

/* ── Wadah Placeholder Logo Resmi EVO Group ──────────────── */
.dt-placeholder-logo {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%);
    overflow: hidden;
    padding: 1rem;
    text-align: center;
}
.dt-placeholder-logo::before {
    content: '';
    position: absolute;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.25), transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(245, 158, 11, 0.18), transparent 50%);
    pointer-events: none;
}
.dt-placeholder-logo__mark {
    position: relative;
    z-index: 2;
    width: 4.5rem;
    height: 4.5rem;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid rgba(255, 255, 255, 0.35);
    box-shadow: 0 14px 32px rgba(0, 0, 0, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
    padding: 8px;
}
.dt-placeholder-logo__mark img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.dt-placeholder-logo__badge {
    position: relative;
    z-index: 2;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #fef08a;
    background: rgba(245, 158, 11, 0.22);
    border: 1px solid rgba(245, 158, 11, 0.4);
    border-radius: 999px;
    padding: 4px 11px;
    max-width: 90%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dt-placeholder-logo--sm .dt-placeholder-logo__mark {
    width: 3.2rem;
    height: 3.2rem;
    border-radius: 0.95rem;
    padding: 6px;
}
.dt-placeholder-logo--sm .dt-placeholder-logo__badge {
    font-size: 0.62rem;
    padding: 3px 8px;
}
.dt-placeholder-logo--card .dt-placeholder-logo__mark {
    width: 3rem;
    height: 3rem;
    border-radius: 0.9rem;
    padding: 5px;
}
.dt-placeholder-logo--card .dt-placeholder-logo__badge {
    font-size: 0.6rem;
    padding: 2px 7px;
}
/* ── Kartu sub-fungsi (konten dinamis Master Info Divisi) ── */
.dt-subgrid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 1rem;
}
.dt-subcard {
    overflow: hidden;
    border-radius: 1.1rem;
    background: #fff;
    border: 1px solid rgba(99, 102, 241, 0.14);
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.07);
    transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s;
}
.dt-subcard:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 38px rgba(79, 70, 229, 0.14);
}
.dt-subcard__img {
    position: relative;
    height: 8.5rem;
    background-size: cover;
    background-position: center;
    overflow: hidden;
}
.dt-subcard__body {
    padding: 1rem 1.1rem 1.15rem;
}

/* Tanpa bidang gambar, isinya perlu jarak atas sendiri — kalau tidak, judul
   menempel persis di tepi kartu. */
.dt-subcard.is-nofoto .dt-subcard__body {
    padding-top: 1.25rem;
}
.dt-subcard__body h3 {
    margin: 0 0 0.35rem;
    font-size: 1rem;
    font-weight: 800;
    letter-spacing: -0.015em;
    color: var(--ink);
}
.dt-subcard__body p {
    margin: 0;
    color: var(--slate);
    font-size: 0.86rem;
    font-weight: 600;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dt-shot--a { background-position: 40% center; }
.dt-shot--b { background-position: 65% 30%; }
.dt-shot--c { background-position: 25% 70%; }
.dt-shot--d { background-position: 50% 40%; }
.dt-shot--e { background-position: 20% center; }
.dt-shot--f { background-position: 80% center; }
.dt-shot--g { background-position: 55% 65%; }
.dt-shot--h { background-position: 35% 45%; }

/* ── Kartu ruang lingkup ─────────────────────────────────── */
.dt-cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}
.dt-card {
    position: relative;
    overflow: hidden;
    padding: 1.5rem 1.35rem;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}
.dt-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, #8b5cf6, #6366f1);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}
.dt-card:hover {
    transform: translateY(-5px);
    border-color: rgba(139, 92, 246, 0.35);
    box-shadow: 0 22px 44px rgba(79, 70, 229, 0.14);
}
.dt-card:hover::before {
    transform: scaleX(1);
}
.dt-card__ic {
    display: grid;
    place-items: center;
    width: 2.7rem;
    height: 2.7rem;
    margin-bottom: 0.95rem;
    border-radius: 0.85rem;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.14), rgba(99, 102, 241, 0.14));
    color: var(--indigo);
    font-size: 1.2rem;
    transition: background 0.25s ease, color 0.25s ease;
}
.dt-card:hover .dt-card__ic {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
}
.dt-card h3 {
    margin: 0 0 0.35rem;
    font-size: 0.98rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--ink);
}
.dt-card p {
    margin: 0;
    color: var(--slate);
    font-size: 0.84rem;
    font-weight: 600;
    line-height: 1.6;
}

/* ── Galeri keseruan ─────────────────────────────────────── */
.dt-gallery {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}
.dt-gal {
    position: relative;
    margin: 0;
    overflow: hidden;
    border-radius: 1.25rem;
    height: 15rem;
}
.dt-gal--wide {
    grid-column: span 2;
}
.dt-gal .dt-shot {
    border-radius: 1.25rem;
    box-shadow: none;
}
.dt-gal:hover .dt-shot {
    transform: scale(1.06);
}
.dt-gal figcaption {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    padding: 1.5rem 1.2rem 1.1rem;
    background: linear-gradient(180deg, rgba(24, 22, 58, 0) 0%, rgba(24, 22, 58, 0.78) 100%);
    color: #f1f5f9;
}
.dt-gal figcaption strong {
    display: block;
    font-size: 0.92rem;
    font-weight: 800;
    text-shadow: 0 1px 8px rgba(15, 23, 42, 0.5);
}
.dt-gal figcaption span {
    font-size: 0.78rem;
    font-weight: 600;
    color: rgba(226, 232, 240, 0.88);
    text-shadow: 0 1px 8px rgba(15, 23, 42, 0.5);
}

/* ── Cara kami bekerja ───────────────────────────────────── */
.dt-work {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap: clamp(1.5rem, 4vw, 2.75rem);
    align-items: center;
}
.dt-work__media .dt-shot {
    min-height: 24rem;
    border-radius: 1.5rem;
}
.dt-steps {
    margin-top: 1.4rem;
}
.dt-step {
    display: flex;
    gap: 1rem;
    padding: 1.15rem 0;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}
.dt-step:last-child {
    border-bottom: none;
}
.dt-step__no {
    flex: none;
    font-size: 0.78rem;
    font-weight: 900;
    color: var(--indigo);
}
.dt-step strong {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--ink);
}
.dt-step p {
    margin: 0;
    color: var(--slate);
    font-size: 0.86rem;
    font-weight: 600;
    line-height: 1.65;
}

/* ── Kutipan ─────────────────────────────────────────────── */
.dt-quote {
    position: relative;
    margin: 0;
    padding: clamp(1.8rem, 4vw, 2.6rem);
    border-radius: 1.5rem;
    background: linear-gradient(150deg, #6d5bd0 0%, #6366f1 100%);
    color: #fff;
    box-shadow: 0 24px 50px rgba(79, 70, 229, 0.28);
}
.dt-quote > i {
    font-size: 2.6rem;
    line-height: 1;
    color: rgba(255, 255, 255, 0.45);
}
.dt-quote p {
    max-width: 46rem;
    margin: 0.4rem 0 1.5rem;
    font-size: clamp(1.05rem, 2.2vw, 1.3rem);
    font-weight: 700;
    line-height: 1.6;
    letter-spacing: -0.01em;
}
.dt-quote footer {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.dt-quote__ava {
    width: 2.9rem;
    height: 2.9rem;
    flex: none;
    border-radius: 50%;
    /* Foto disuplai template lewat --dt-ava, mis.
       :style="{ '--dt-ava': `url('${anggota.foto}')` }". Tanpa itu, abu-abu.

       Sengaja TIDAK menulis url('/img/...') literal di sini: Vite mencoba
       menyelesaikan setiap url() absolut saat build, sementara laravel-vite-plugin
       mematikan publicDir — jadi berkas di public/ berada di luar jangkauannya dan
       setiap build berakhir dengan "didn't resolve at build time". Menyalakan
       publicDir bukan jalan keluarnya: Vite lalu menulis ulang jalurnya jadi
       /build/img/... yang justru tidak ada di sana. */
    background: var(--dt-ava, #e2e8f0) 45% 25% / cover;
    border: 2px solid rgba(255, 255, 255, 0.6);
}
.dt-quote footer strong {
    display: block;
    font-size: 0.92rem;
    font-weight: 800;
}
.dt-quote footer small {
    font-size: 0.78rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.78);
}

/* ── Daftar lowongan tim ─────────────────────────────────── */
.dt-head--row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    max-width: none;
}
.dt-head--row p {
    margin: 0;
}
.dt-count {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.1);
    color: var(--indigo);
    font-size: 0.78rem;
    font-weight: 800;
}
.rek__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.05rem;
}
.dt-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 2.75rem 1rem;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.85);
    border: 1px dashed rgba(203, 213, 225, 0.9);
    color: var(--muted);
    font-size: 0.88rem;
    font-weight: 600;
}

/* ── Tag alat ────────────────────────────────────────────── */
.dt-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}
.dt-tags span {
    padding: 0.55rem 1.05rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: var(--ink);
    font-size: 0.82rem;
    font-weight: 700;
    transition: transform 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}
.dt-tags span:hover {
    transform: translateY(-2px);
    border-color: rgba(139, 92, 246, 0.4);
    color: var(--indigo);
}

/* ── CTA (Light Pastel 1:1 CtaSection Pattern) ──────────── */
.ctal__panel {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    padding: clamp(2.5rem, 5vw, 3.25rem) clamp(1.4rem, 4vw, 2.15rem);
    text-align: center;
    background:
        radial-gradient(600px 300px at 12% 100%, rgba(139, 92, 246, 0.16), rgba(139, 92, 246, 0) 60%),
        radial-gradient(600px 300px at 92% 0%, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0) 60%),
        linear-gradient(135deg, #eef2ff 0%, #f2effe 55%, #eaf0ff 100%);
    border: 1px solid rgba(99, 102, 241, 0.16);
    box-shadow: 0 24px 60px rgba(99, 102, 241, 0.1);
}
.ctal__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 6px 13px;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.22);
    font-size: 0.69rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #4f46e5;
}
.ctal__panel h2 {
    margin: 1rem 0 0;
    font-size: clamp(1.5rem, 3.4vw, 1.9rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.18;
    color: #1e1b4b;
    text-wrap: pretty;
}
.ctal__panel h2 span {
    color: #6366f1;
}
.ctal__panel p {
    margin: 0.75rem auto 0;
    max-width: 520px;
    font-size: 0.9rem;
    color: #64748b;
    line-height: 1.6;
    font-weight: 600;
}
.ctal__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
    margin-top: 26px;
}
.ctal__btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    font-weight: 800;
    border-radius: 14px;
    padding: 14px 24px;
    text-decoration: none;
    cursor: pointer;
    transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease, background 0.16s ease;
}
.ctal__btn--primary {
    color: #fff;
    border: none;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 14px 32px rgba(99, 102, 241, 0.34);
}
.ctal__btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 42px rgba(99, 102, 241, 0.44);
    color: #fff;
}
.ctal__btn--ghost {
    color: #4f46e5;
    background: #fff;
    border: 1px solid #d9def0;
}
.ctal__btn--ghost:hover {
    border-color: #a5b4fc;
    background: #fbfbff;
    transform: translateY(-2px);
    color: #4f46e5;
}
.ctal__trust {
    list-style: none;
    margin: 24px 0 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px 18px;
}
.ctal__trust li {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
}
.ctal__trust i {
    color: #10b981;
    font-weight: 900;
}

/* ── Responsif ───────────────────────────────────────────── */
@media (max-width: 980px) {
    .dt-about,
    .dt-work {
        grid-template-columns: 1fr;
    }
    .dt-work__media {
        order: -1;
    }
    .dt-work__media .dt-shot {
        min-height: 16rem;
    }
    .dt-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .dt-gallery {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .dt-gal--wide {
        grid-column: span 2;
    }
}
@media (max-width: 700px) {
    .dt-head--row {
        flex-direction: column;
        align-items: flex-start;
    }
}
@media (max-width: 600px) {
    .dt-cards,
    .dt-gallery {
        grid-template-columns: 1fr;
    }
    .dt-gal--wide {
        grid-column: span 1;
    }
    .dt-about__media {
        grid-template-rows: repeat(2, 8rem);
    }
    .dt-hero__stats {
        gap: 0.9rem;
    }
}
@media (prefers-reduced-motion: reduce) {
    .dt-shot,
    .dt-card,
    .dt-btn,
    .dt-tags span {
        transition: none;
    }
}
</style>
