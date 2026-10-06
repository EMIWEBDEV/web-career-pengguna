<!-- WEB CAREER — KONFIRMASI KEHADIRAN (kandidat, tanpa login).

     Dibuka dari tombol "Konfirmasi Kehadiran" di surel undangan. Tautannya
     bertanda tangan & terikat versi jadwal; seluruh aturan (batas, jatah,
     komitmen, final) ditegakkan server — layar ini hanya menyajikannya dengan
     bahasa kandidat.

     Bagian jawabnya (status, pilihan, formulir) adalah KonfirmasiJawab — SAMA
     dengan kartu jadwal di Portal Kandidat, tempat kandidat yang sudah masuk
     menjawab tanpa pindah halaman. Jadwal yang DITUNDA menampilkan alasan,
     perkiraan jadwal pengganti, dan pesan tim (JadwalTunda). -->
<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import KonfirmasiJawab from '@career/KonfirmasiJawab.vue';
import JadwalTunda from '@career/JadwalTunda.vue';

defineOptions({ layout: null });

const props = defineProps({
    keadaan: { type: String, default: 'TAUTAN_RUSAK' },
    judul: { type: Object, default: () => ({}) },
    jadwal: { type: Object, default: null },
    konfirmasi: { type: Object, default: null },
    pilihan: { type: Array, default: () => [] },
    alasan: { type: Object, default: () => ({ JADWAL_LAIN: [], MUNDUR: [] }) },
    permintaan: { type: Object, default: null },
    jatah: { type: Object, default: () => ({ maks: 2, terpakai: 0, sisa: 2 }) },
    aturanUsulan: { type: Object, default: () => ({ maks: 3, tanggalMin: '', tanggalMaks: '', bagian: [], catatanMaks: 500 }) },
    ubah: { type: Object, default: () => ({}) },
    tunda: { type: Object, default: null },
    url: { type: Object, default: () => ({}) },
    urlTerbaru: { type: String, default: null },
    jadwalTerbaru: { type: String, default: null },
});

// ── KEADAAN TAUTAN (selain TERBUKA) ─────────────────────────────────────────

const INFO = {
    TAUTAN_RUSAK: {
        ikon: 'bi-link-45deg', nada: 'abu',
        judul: 'Tautan tidak dikenali',
        teks: 'Tautan ini mungkin terpotong saat disalin. Buka kembali tombol “Konfirmasi Kehadiran” dari email undangan terbarumu, atau masuk ke Portal Kandidat.',
    },
    TAUTAN_KEDALUWARSA: {
        ikon: 'bi-clock-history', nada: 'abu',
        judul: 'Tautan sudah kedaluwarsa',
        teks: 'Demi keamanan, tautan konfirmasi punya masa berlaku. Masuk ke Portal Kandidat untuk melihat jadwalmu yang terbaru.',
    },
    LAMARAN_SELESAI: {
        ikon: 'bi-flag-fill', nada: 'abu',
        judul: 'Proses seleksi sudah selesai',
        teks: 'Lamaran ini sudah tidak berjalan, jadi jadwal ini tidak perlu dikonfirmasi lagi. Kabar lengkapnya ada di Portal Kandidat.',
    },
    SELESAI: {
        ikon: 'bi-check2-all', nada: 'hijau',
        judul: 'Aktivitas ini sudah selesai',
        teks: 'Kehadiranmu untuk jadwal ini sudah dicatat tim rekrutmen. Pantau tahap berikutnya di Portal Kandidat.',
    },
    DITUNDA: {
        ikon: 'bi-pause-circle-fill', nada: 'ungu',
        judul: 'Jadwal ini ditunda',
        teks: 'Tim rekrutmen menunda jadwal ini. Jadwal pengganti akan dikirim lewat email dan tampil di Portal Kandidat.',
    },
    VERSI_LAMA: {
        ikon: 'bi-arrow-repeat', nada: 'ungu',
        judul: 'Jadwal ini sudah diperbarui',
        teks: 'Tautan ini milik jadwal yang lama. Gunakan jadwal terbaru di bawah — jawaban untuk jadwal lama tidak berlaku lagi.',
    },
    TIDAK_BERLAKU: {
        ikon: 'bi-info-circle-fill', nada: 'abu',
        judul: 'Jadwal ini tidak memerlukan konfirmasi',
        teks: 'Cukup datang sesuai jadwal. Rinciannya ada di email undangan dan Portal Kandidat.',
    },
};

const info = computed(() => INFO[props.keadaan] || null);
const terbuka = computed(() => props.keadaan === 'TERBUKA' || props.keadaan === 'ACARA_LEWAT');

/** Bahan KonfirmasiJawab — bentuknya sama dengan kartu jadwal portal. */
const bahan = computed(() => ({
    konfirmasi: props.konfirmasi,
    pilihan: props.pilihan,
    alasan: props.alasan,
    permintaan: props.permintaan,
    jatah: props.jatah,
    aturanUsulan: props.aturanUsulan,
    ubah: props.ubah,
    url: props.url,
}));

function segarkan() {
    router.reload({ preserveScroll: true });
}


const salam = computed(() => {
    const n = String(props.judul?.nama || '').trim().split(/\s+/)[0];
    return n ? `Halo, ${n.charAt(0).toUpperCase()}${n.slice(1).toLowerCase()}` : 'Halo';
});
</script>

<template>
    <Head title="Konfirmasi Kehadiran">
        <meta name="robots" content="noindex, nofollow" />
        <meta name="referrer" content="no-referrer" />
    </Head>

    <div class="kfj">
        <div class="kfj-latar" aria-hidden="true"></div>

        <main class="kfj-wadah">
            <header class="kfj-merek">
                <span class="kfj-merek__logo"><i class="bi bi-briefcase-fill"></i></span>
                <div>
                    <b>EVO Career</b>
                    <small>Konfirmasi Kehadiran</small>
                </div>
            </header>

            <!-- ═══ DITUNDA — alasan, perkiraan pengganti, pesan tim ═══ -->
            <template v-if="keadaan === 'DITUNDA' && tunda">
                <section class="kfj-kartu kfj-sapa">
                    <p class="kfj-sapa__halo">{{ salam }} 👋</p>
                    <h1>{{ judul.aktivitas }}</h1>
                    <p class="kfj-sapa__pos">
                        <i class="bi bi-briefcase"></i> {{ judul.posisi }}
                        <span v-if="judul.tahap" class="kfj-titik">·</span>
                        <span v-if="judul.tahap">{{ judul.tahap }}</span>
                    </p>
                </section>
                <JadwalTunda :tunda="tunda" :label="judul.aktivitas" />
                <footer class="kfj-kaki">
                    <a :href="url.portal || '/login'" class="kfj-tautan"><i class="bi bi-grid-1x2"></i> Buka Portal Kandidat</a>
                </footer>
            </template>

            <!-- ═══ KEADAAN TAUTAN SELAIN TERBUKA ═══ -->
            <section v-else-if="!terbuka" class="kfj-kartu kfj-info" :class="`is-${info?.nada || 'abu'}`">
                <span class="kfj-info__ic"><i class="bi" :class="info?.ikon || 'bi-info-circle'"></i></span>
                <h1>{{ info?.judul || 'Tautan tidak dikenali' }}</h1>
                <p v-if="judul?.aktivitas" class="kfj-info__sub">
                    {{ judul.aktivitas }}<template v-if="judul.posisi"> · {{ judul.posisi }}</template>
                </p>
                <p>{{ info?.teks }}</p>

                <div v-if="keadaan === 'VERSI_LAMA' && jadwalTerbaru" class="kfj-terbaru">
                    <small>Jadwal terbaru</small>
                    <b><i class="bi bi-calendar-event"></i> {{ jadwalTerbaru }}</b>
                </div>

                <div class="kfj-aksi-info">
                    <a v-if="keadaan === 'VERSI_LAMA' && urlTerbaru" :href="urlTerbaru" class="kfj-btn kfj-btn--utama">
                        <i class="bi bi-arrow-right-circle-fill"></i> Buka jadwal terbaru
                    </a>
                    <a href="/login" class="kfj-btn kfj-btn--garis">
                        <i class="bi bi-box-arrow-in-right"></i> Masuk ke Portal Kandidat
                    </a>
                </div>
            </section>

            <!-- ═══ TERBUKA / ACARA LEWAT ═══ -->
            <template v-else>
                <section class="kfj-kartu kfj-sapa">
                    <p class="kfj-sapa__halo">{{ salam }} 👋</p>
                    <h1>{{ judul.aktivitas }}</h1>
                    <p class="kfj-sapa__pos">
                        <i class="bi bi-briefcase"></i> {{ judul.posisi }}
                        <span v-if="judul.tahap" class="kfj-titik">·</span>
                        <span v-if="judul.tahap">{{ judul.tahap }}</span>
                    </p>
                    <p v-if="judul.kode" class="kfj-sapa__kode">No. pendaftaran <b>{{ judul.kode }}</b></p>
                </section>

                <!-- Jadwal -->
                <section class="kfj-kartu kfj-jadwal" aria-label="Rincian jadwal">
                    <div class="kfj-jadwal__waktu">
                        <span class="kfj-jadwal__ic"><i class="bi bi-calendar2-week-fill"></i></span>
                        <div>
                            <small>Waktu</small>
                            <b>{{ jadwal?.waktuTeks }}</b>
                        </div>
                    </div>

                    <div class="kfj-baris">
                        <i class="bi" :class="jadwal?.daring ? 'bi-camera-video-fill' : 'bi-geo-alt-fill'"></i>
                        <div>
                            <small>{{ jadwal?.modeNama || (jadwal?.daring ? 'Daring' : 'Tatap muka') }}</small>
                            <template v-if="jadwal?.daring">
                                <a v-if="jadwal.link" :href="jadwal.link" target="_blank" rel="noopener" class="kfj-tautan">{{ jadwal.link }}</a>
                                <span v-else>Tautan pertemuan dikirim tim rekrutmen.</span>
                            </template>
                            <template v-else>
                                <b v-if="jadwal?.tempat?.nama">{{ jadwal.tempat.nama }}</b>
                                <span v-if="jadwal?.tempat?.alamat">{{ jadwal.tempat.alamat }}</span>
                                <span v-if="jadwal?.detailLokasi" class="kfj-pelan">{{ jadwal.detailLokasi }}</span>
                                <a v-if="jadwal?.tempat?.mapsUrl" :href="jadwal.tempat.mapsUrl" target="_blank" rel="noopener" class="kfj-tautan">
                                    <i class="bi bi-map"></i> Buka di Google Maps
                                </a>
                            </template>
                        </div>
                    </div>

                    <div v-if="jadwal?.kontak" class="kfj-baris">
                        <i class="bi bi-telephone-fill"></i>
                        <div><small>Nomor yang dihubungi</small><b>{{ jadwal.kontak }}</b></div>
                    </div>

                    <details v-if="jadwal?.instruksi" class="kfj-instruksi">
                        <summary><i class="bi bi-card-checklist"></i> Instruksi & persiapan</summary>
                        <p>{{ jadwal.instruksi }}</p>
                    </details>
                </section>

                <!-- Status & jawaban — sama persis dengan kartu jadwal portal. -->
                <KonfirmasiJawab
                    :bahan="bahan"
                    mode="halaman"
                    :judul="{ aktivitas: judul.aktivitas, waktuTeks: jadwal?.waktuTeks }"
                    @berubah="segarkan"
                />

                <p v-if="keadaan === 'ACARA_LEWAT'" class="kfj-catat">
                    <i class="bi bi-clock-history"></i> Jadwal ini sudah berlangsung. Kehadiran dicatat oleh tim rekrutmen.
                </p>

                <footer class="kfj-kaki">
                    <a v-if="url.portal" :href="url.portal" class="kfj-tautan"><i class="bi bi-grid-1x2"></i> Buka Portal Kandidat</a>
                    <p><i class="bi bi-shield-lock"></i> Tautan ini pribadi untukmu — jangan dibagikan.</p>
                </footer>
            </template>
        </main>
    </div>
</template>

<style scoped>
.kfj {
    --ink: #0f172a;
    --muted: #64748b;
    --line: #e5e7f5;
    --brand: #4f46e5;
    --brand2: #7c3aed;
    position: relative;
    min-height: 100vh;
    background: #f6f6fd;
    color: var(--ink);
    font-family: 'Plus Jakarta Sans', 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
}
.kfj-latar {
    position: fixed;
    inset: 0;
    pointer-events: none;
    background:
        radial-gradient(circle at 10% -10%, rgba(124, 58, 237, 0.16), transparent 45%),
        radial-gradient(circle at 100% 0%, rgba(79, 70, 229, 0.12), transparent 40%),
        radial-gradient(circle at 50% 120%, rgba(14, 165, 233, 0.08), transparent 45%);
}
.kfj-wadah {
    position: relative;
    max-width: 560px;
    margin: 0 auto;
    padding: 20px 16px 40px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.kfj-merek {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 2px 6px;
}
.kfj-merek__logo {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    color: #fff;
    background: linear-gradient(135deg, var(--brand2), var(--brand));
    box-shadow: 0 10px 22px -10px rgba(79, 70, 229, 0.7);
}
.kfj-merek b {
    display: block;
    font-size: 0.98rem;
    letter-spacing: -0.01em;
}
.kfj-merek small {
    color: var(--muted);
    font-size: 0.76rem;
    font-weight: 600;
}

.kfj-kartu {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 16px;
    box-shadow: 0 14px 34px -28px rgba(30, 27, 75, 0.45);
}
.kfj h1 {
    margin: 2px 0 4px;
    font-size: 1.28rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.25;
}
.kfj-pelan {
    color: var(--muted);
    font-size: 0.85rem;
    font-weight: 500;
}
.kfj-titik {
    margin: 0 4px;
    color: #cbd5e1;
}

/* Sapaan */
.kfj-sapa__halo {
    margin: 0;
    color: var(--brand);
    font-weight: 700;
    font-size: 0.85rem;
}
.kfj-sapa__pos {
    margin: 0;
    color: #334155;
    font-weight: 600;
    font-size: 0.88rem;
}
.kfj-sapa__kode {
    margin: 8px 0 0;
    font-size: 0.78rem;
    color: var(--muted);
}

/* Jadwal */
.kfj-jadwal {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.kfj-jadwal__waktu {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 12px;
    border-radius: 14px;
    background: linear-gradient(135deg, #eef2ff, #f5f3ff);
}
.kfj-jadwal__ic {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    flex: none;
    display: grid;
    place-items: center;
    background: #fff;
    color: var(--brand);
    font-size: 1.15rem;
}
.kfj-jadwal small {
    display: block;
    color: var(--muted);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.kfj-jadwal__waktu b {
    font-size: 0.98rem;
    line-height: 1.35;
}
.kfj-baris {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 0 4px;
}
.kfj-baris > i {
    color: var(--brand);
    margin-top: 2px;
}
.kfj-baris > div {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    font-size: 0.9rem;
}
.kfj-tautan {
    color: var(--brand);
    font-weight: 700;
    text-decoration: none;
    word-break: break-all;
}
.kfj-tautan:hover {
    text-decoration: underline;
}
.kfj-instruksi {
    border-top: 1px dashed var(--line);
    padding-top: 10px;
}
.kfj-instruksi summary {
    cursor: pointer;
    font-weight: 700;
    font-size: 0.88rem;
    color: #334155;
}
.kfj-instruksi p {
    white-space: pre-line;
    margin: 8px 0 0;
    font-size: 0.88rem;
    color: #334155;
    line-height: 1.55;
}
.kfj-catat {
    margin: 0;
    font-size: 0.83rem;
    color: #475569;
    display: flex;
    gap: 8px;
    align-items: flex-start;
}

/* Tombol di kartu keadaan */
.kfj-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 48px;
    padding: 11px 16px;
    border-radius: 14px;
    border: 1.5px solid transparent;
    font: inherit;
    font-weight: 800;
    font-size: 0.95rem;
    cursor: pointer;
    text-decoration: none;
}
.kfj-btn:focus-visible {
    outline: 3px solid #c7d2fe;
    outline-offset: 2px;
}
.kfj-btn--utama {
    color: #fff;
    background: linear-gradient(135deg, #16a34a, #15803d);
}
.kfj-btn--garis {
    color: #3730a3;
    background: #fff;
    border-color: #c7d2fe;
}
.kfj-aksi-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 14px;
}

/* Info keadaan */
.kfj-info {
    text-align: center;
    padding: 26px 18px;
}
.kfj-info__ic {
    width: 64px;
    height: 64px;
    margin: 0 auto 10px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 1.7rem;
    color: #fff;
    background: #94a3b8;
}
.kfj-info.is-hijau .kfj-info__ic {
    background: #16a34a;
}
.kfj-info.is-kuning .kfj-info__ic {
    background: #d97706;
}
.kfj-info.is-ungu .kfj-info__ic {
    background: var(--brand2);
}
.kfj-info p {
    color: #475569;
    font-size: 0.9rem;
    line-height: 1.55;
}
.kfj-info__sub {
    font-weight: 700;
    color: #334155 !important;
}
.kfj-terbaru {
    margin: 12px auto 0;
    padding: 10px 14px;
    border-radius: 12px;
    background: #eef2ff;
    display: inline-flex;
    flex-direction: column;
    gap: 2px;
}
.kfj-terbaru small {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--muted);
}

.kfj-kaki {
    text-align: center;
    font-size: 0.8rem;
    color: var(--muted);
    padding: 8px 0;
}
.kfj-kaki p {
    margin: 8px 0 0;
}

@media (min-width: 640px) {
    .kfj-wadah {
        padding-top: 40px;
    }
    .kfj-kartu {
        padding: 20px;
    }
}
</style>
