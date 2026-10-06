<!-- WEB CAREER — Section 4: Management Trainee (LIGHT, 1:1 desain "EVO Career Landing") -->
<template>
    <section v-if="programMt.length" id="mt" class="mtl">
        <div class="mtl__panel wc-reveal">
            <div class="mtl__grid-bg" aria-hidden="true"></div>

            <div class="mtl__head">
                <span class="mtl__eyebrow"><i class="bi bi-gem"></i> Program Unggulan</span>
                <h2>Management Trainee — jalur cepat calon <span>pemimpin.</span></h2>
                <p>Kaderisasi eksklusif untuk lulusan terbaik yang siap tumbuh menjadi future leader.</p>
            </div>

            <div class="mtl__grid">
                <Link
                    v-for="(mt, i) in tampil"
                    :key="mt.id"
                    class="mtl__card wc-reveal"
                    :style="{ '--d': i * 80 + 'ms' }"
                    :href="mtUrl(mt.id)"
                >
                    <div class="mtl__cardtop">
                        <span class="mtl__batch"><i class="bi bi-stars"></i> {{ mt.batch }}</span>
                        <span class="mtl__status" :class="statusClass(mt.status)">{{ statusLabel(mt.status) }}</span>
                    </div>

                    <h3 class="mtl__title">{{ mt.nama }}</h3>
                    <div class="mtl__tag">{{ mt.tagline || 'Program Management Trainee EVO Group.' }}</div>
                    <p class="mtl__desc">{{ mt.ringkasan }}</p>

                    <!-- Isi program: posisi apa saja yang dibuka di dalamnya. -->
                    <div v-if="(mt.posisi || []).length" class="mtl__posisi">
                        <span class="mtl__posisi-lbl"><i class="bi bi-diagram-3"></i> {{ mt.jumlahPosisi }} posisi</span>
                        <span v-for="p in mt.posisi.slice(0, 3)" :key="p.id" class="mtl__chip">{{ p.posisi }}</span>
                        <span v-if="mt.jumlahPosisi > 3" class="mtl__chip mtl__chip--more">+{{ mt.jumlahPosisi - 3 }}</span>
                    </div>

                    <!-- Hanya fakta terdata — lihat catatan di SemuaLowongan.vue. -->
                    <div class="mtl__meta">
                        <span v-if="mt.penempatan"><i class="bi bi-geo-alt"></i> {{ mt.penempatan }}</span>
                        <span><i class="bi bi-person-lines-fill"></i> {{ mt.pelamar }} pelamar</span>
                    </div>

                    <!-- Bar "kuota terisi" DIHAPUS bersama datanya — sama seperti
                         di /karir/lowongan; jumlah kursi tidak lagi dipublikasi. -->

                    <div class="mtl__foot">
                        <span class="mtl__deadline" :class="{ soon: daysLeft(mt.tanggalTutup) <= 7 }">
                            <i class="bi bi-calendar-event"></i>
                            Ditutup {{ formatDate(mt.tanggalTutup) }}
                        </span>
                        <span class="mtl__cta">
                            {{ mt.jumlahPosisi ? `Lihat ${mt.jumlahPosisi} posisi` : 'Pelajari' }}
                            <i class="bi bi-arrow-right"></i>
                        </span>
                    </div>
                </Link>
            </div>

            <!-- Landing hanya cuplikan 6 program MT — selebihnya di halaman khusus. -->
            <div v-if="programMt.length > MAKS" class="mtl__seeall">
                <Link href="/karir/lowongan?tab=mt" class="mtl__seeall-btn">
                    Lihat Semua {{ programMt.length }} Program MT
                    <i class="bi bi-arrow-right"></i>
                </Link>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { daysLeft, formatDate, mtUrl, statusClass, statusLabel } from '@utils/career/data';

const props = defineProps({
    programMt: { type: Array, default: () => [] },
});

// Landing menampilkan MAKSIMAL 6 kartu MT; sisanya lewat tombol "Lihat Semua".
const MAKS = 6;
const tampil = computed(() => props.programMt.slice(0, MAKS));
</script>

<style scoped>
.mtl {
    width: min(1180px, calc(100vw - 2rem));
    margin: clamp(2.5rem, 5vw, 3.5rem) auto 0;
}
.mtl__panel {
    position: relative;
    overflow: hidden;
    border-radius: 30px;
    padding: clamp(2rem, 4vw, 2.75rem) clamp(1.25rem, 3vw, 2.15rem) clamp(1.9rem, 3.5vw, 2.5rem);
    background:
        radial-gradient(700px 340px at 82% 0%, rgba(139, 92, 246, 0.14), rgba(139, 92, 246, 0) 60%),
        linear-gradient(160deg, #f2effe 0%, #eef2ff 52%, #f4f0ff 100%);
    border: 1px solid rgba(99, 102, 241, 0.14);
    box-shadow: 0 24px 60px rgba(99, 102, 241, 0.1);
}
.mtl__grid-bg {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image:
        linear-gradient(rgba(99, 102, 241, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(99, 102, 241, 0.05) 1px, transparent 1px);
    background-size: 42px 42px;
    -webkit-mask-image: radial-gradient(circle at 80% 0, #000, transparent 62%);
    mask-image: radial-gradient(circle at 80% 0, #000, transparent 62%);
}
.mtl__head {
    position: relative;
    text-align: center;
    max-width: 620px;
    margin: 0 auto;
}
.mtl__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 6px 13px;
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.13);
    border: 1px solid rgba(245, 158, 11, 0.28);
    font-size: 0.69rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #b45309;
}
.mtl__head h2 {
    margin: 1rem 0 0;
    font-size: clamp(1.5rem, 3.3vw, 1.95rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.18;
    color: #1e1b4b;
    text-wrap: pretty;
}
.mtl__head h2 span {
    color: #f59e0b;
}
.mtl__head p {
    margin: 0.75rem 0 0;
    font-size: 0.9rem;
    color: #64748b;
    line-height: 1.6;
    font-weight: 600;
}
.mtl__grid {
    position: relative;
    margin-top: clamp(1.5rem, 3vw, 2.15rem);
    display: grid;
    /* min() — tanpa itu track 288px lebih lebar dari panel di layar ≤370px
       (100vw - 2rem - padding panel) dan kartu meluap ke samping. */
    grid-template-columns: repeat(auto-fill, minmax(min(288px, 100%), 1fr));
    gap: 1.05rem;
}
.mtl__card {
    display: flex;
    flex-direction: column;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 20px;
    padding: 18px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    text-decoration: none;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}
.mtl__card:hover {
    transform: translateY(-5px);
    border-color: rgba(139, 92, 246, 0.4);
    box-shadow: 0 20px 44px rgba(124, 110, 222, 0.18);
}
.mtl__cardtop {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.mtl__batch {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.66rem;
    font-weight: 800;
    color: #b45309;
    background: rgba(245, 158, 11, 0.13);
    border: 1px solid rgba(245, 158, 11, 0.26);
    border-radius: 8px;
    padding: 4px 9px;
}
.mtl__status {
    font-size: 0.63rem;
    font-weight: 800;
    border-radius: 8px;
    padding: 4px 9px;
    color: #059669;
    background: rgba(16, 185, 129, 0.12);
}
.mtl__status.is-soon {
    color: #b45309;
    background: rgba(245, 158, 11, 0.14);
}
.mtl__status.is-closed {
    color: #e11d48;
    background: rgba(225, 29, 72, 0.1);
}
.mtl__title {
    margin: 13px 0 0;
    font-size: 1.03rem;
    font-weight: 800;
    color: #1e293b;
    letter-spacing: -0.01em;
    line-height: 1.25;
    text-wrap: pretty;
}
.mtl__tag {
    font-size: 0.72rem;
    font-weight: 700;
    color: #8b5cf6;
    margin-top: 5px;
}
.mtl__desc {
    margin: 9px 0 0;
    font-size: 0.78rem;
    line-height: 1.55;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.mtl__posisi {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
}
.mtl__posisi-lbl {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.67rem;
    font-weight: 800;
    color: #6d28d9;
}
.mtl__posisi-lbl i {
    font-size: 0.76rem;
}
.mtl__chip {
    max-width: 13rem;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: 0.65rem;
    font-weight: 700;
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.16);
    border-radius: 7px;
    padding: 3px 8px;
}
.mtl__chip--more {
    color: #64748b;
    background: #f1f5f9;
    border-color: #e2e8f0;
}
.mtl__meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px 12px;
    margin-top: 14px;
}
.mtl__meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    color: #64748b;
}
.mtl__meta i {
    color: #8b5cf6;
    font-size: 0.82rem;
}
.mtl__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 15px;
    padding-top: 14px;
    border-top: 1px solid #f1f2f9;
}
.mtl__deadline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.68rem;
    color: #94a3b8;
}
.mtl__deadline.soon {
    color: #b45309;
    font-weight: 700;
}
.mtl__cta {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.75rem;
    font-weight: 800;
    color: #4f46e5;
}
.mtl__cta i {
    transition: transform 0.18s ease;
}
.mtl__card:hover .mtl__cta i {
    transform: translateX(4px);
}
.mtl__seeall {
    position: relative;
    display: flex;
    justify-content: center;
    margin-top: 1.75rem;
}
.mtl__seeall-btn {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    font-size: 0.875rem;
    font-weight: 800;
    color: #fff;
    padding: 14px 26px;
    border-radius: 14px;
    text-decoration: none;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 14px 32px rgba(99, 102, 241, 0.34);
    transition: transform 0.16s ease, box-shadow 0.16s ease;
}
.mtl__seeall-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 42px rgba(99, 102, 241, 0.44);
    color: #fff;
}
.mtl__seeall-btn i {
    transition: transform 0.18s ease;
}
.mtl__seeall-btn:hover i {
    transform: translateX(4px);
}
@media (max-width: 560px) {
    .mtl__meta {
        grid-template-columns: 1fr;
    }
    .mtl__panel {
        border-radius: 22px;
    }
    /* Nama posisi panjang tidak boleh memaksa kartu melebar. */
    .mtl__chip {
        max-width: 100%;
    }
    .mtl__foot {
        flex-wrap: wrap;
    }
    .mtl__seeall-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
