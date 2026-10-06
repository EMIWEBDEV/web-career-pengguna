<!-- WEB CAREER — Halaman Detail Lowongan (route: /karir/landing-page/lowongan/{id}) -->
<template>
    <Head :title="job.posisi" />

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <section class="wc-detail">
            <!-- Posisi milik program MT kembali ke PROGRAMNYA, bukan ke daftar
                 lowongan umum — di sanalah posisi ini berada. -->
            <Link v-if="job.induk" class="wc-back" :href="mtUrl(job.induk.id)">
                <i class="bi bi-arrow-left"></i> Kembali ke {{ job.induk.nama }}
            </Link>
            <button v-else type="button" class="wc-back" @click="goToSection('lowongan')">
                <i class="bi bi-arrow-left"></i> Kembali ke Lowongan
            </button>

            <div class="wc-detail__wrap">
                <div class="wc-detail__main">
                    <header class="wc-dhead wc-reveal">
                        <div class="wc-dhead__pattern" aria-hidden="true"></div>
                        <div class="wc-dhead__badges">
                            <span v-if="job.induk" class="wc-badge wc-badge--violet">
                                <i class="bi bi-mortarboard-fill"></i> Management Trainee<template v-if="job.induk.batch"> · {{ job.induk.batch }}</template>
                            </span>
                            <span class="wc-badge" :class="typeClass(job.tipeKerja)">{{ job.tipeKerja }}</span>
                            <span v-if="job.unggulan" class="wc-badge wc-badge--star"
                                ><i class="bi bi-star-fill"></i> Unggulan</span
                            >
                            <span v-if="job.pengalaman && job.pengalaman !== '—'" class="wc-badge wc-badge--muted"
                                ><i class="bi bi-briefcase"></i> {{ job.pengalaman }}</span
                            >
                        </div>
                        <h1>{{ job.posisi }}</h1>
                        <!-- TANPA info perusahaan/divisi — sorot benefit teratas dari MPP. -->
                        <p v-if="(job.benefit || []).length" class="wc-dhead__co"><i class="bi bi-gift"></i> {{ job.benefit[0] }}<template v-if="job.benefit.length > 1"> · +{{ job.benefit.length - 1 }} benefit lain</template></p>
                        <div class="wc-dhead__meta">
                            <span><i class="bi bi-geo-alt"></i> {{ lokasiLabel(job) }}</span>
                            <span v-if="job.pengalaman && job.pengalaman !== '—'"><i class="bi bi-briefcase"></i> {{ job.pengalaman }}</span>
                        </div>
                    </header>

                    <!-- Banner kuota DIHAPUS. Angka kursi adalah rencana internal,
                         dan bar "x dari y terisi" membuat pelamar menakar peluang
                         dari data yang bukan urusannya. Yang menutup lowongan di
                         halaman ini cuma tanggal tutup. -->

                    <section class="wc-block wc-reveal">
                        <h2><i class="bi bi-file-text"></i> Deskripsi Pekerjaan</h2>
                        <p>{{ job.deskripsi }}</p>
                    </section>
                    <section v-if="job.tanggungJawab && job.tanggungJawab.length" class="wc-block wc-reveal">
                        <h2><i class="bi bi-list-check"></i> Tanggung Jawab</h2>
                        <ul class="wc-list">
                            <li v-for="(t, i) in job.tanggungJawab" :key="i">
                                <i class="bi bi-check-circle-fill"></i><span>{{ t }}</span>
                            </li>
                        </ul>
                    </section>
                    <section v-if="job.persyaratan && job.persyaratan.length" class="wc-block wc-reveal">
                        <h2><i class="bi bi-clipboard-check"></i> Persyaratan</h2>
                        <ul class="wc-list">
                            <li v-for="(t, i) in job.persyaratan" :key="i">
                                <i class="bi bi-dot"></i><span>{{ t }}</span>
                            </li>
                        </ul>
                    </section>
                    <section v-if="job.skill && job.skill.length" class="wc-block wc-reveal">
                        <h2><i class="bi bi-tags"></i> Skill yang Dibutuhkan</h2>
                        <div class="wc-tags wc-tags--lg">
                            <span v-for="s in job.skill" :key="s">{{ s }}</span>
                        </div>
                    </section>
                    <section v-if="job.benefit && job.benefit.length" class="wc-block wc-reveal">
                        <h2><i class="bi bi-gift"></i> Benefit</h2>
                        <div class="wc-benefit-grid">
                            <div v-for="(b, i) in job.benefit" :key="i" class="wc-benefit">
                                <i class="bi bi-patch-check-fill"></i><span>{{ b }}</span>
                            </div>
                        </div>
                    </section>
                    <!-- Tahapan Seleksi WAJIB dari DB (alur). Tanpa tahap -> kartu hilang. -->
                    <section v-if="job.pipeline && job.pipeline.length" class="wc-block wc-reveal">
                        <h2><i class="bi bi-signpost-split"></i> Tahapan Seleksi</h2>
                        <ol class="wc-pipeline">
                            <li v-for="(p, i) in job.pipeline" :key="i">
                                <span class="wc-pipeline__num">{{ i + 1 }}</span>
                                <div>
                                    <strong>{{ p.label }}</strong
                                    ><small>{{ stageTypeLabel(p.tipe) }}</small>
                                </div>
                            </li>
                        </ol>
                    </section>
                </div>

                <aside class="wc-side wc-reveal" style="--d: 120ms">
                    <div class="wc-apply">
                        <div class="wc-apply__title"><i class="bi bi-briefcase-fill"></i> Info Lamaran</div>
                        <ul class="wc-apply__facts">
                            <li>
                                <span><i class="bi bi-person-lines-fill"></i> Pelamar</span
                                ><b>{{ job.pelamar }} orang</b>
                            </li>
                            <li>
                                <span><i class="bi bi-geo-alt"></i> Lokasi</span><b>{{ job.lokasi }}</b>
                            </li>
                            <li>
                                <span><i class="bi bi-calendar-event"></i> Ditutup</span
                                ><b>{{ formatDateTime(job.tanggalTutup) }}</b>
                            </li>
                            <li>
                                <span><i class="bi bi-clock"></i> Sisa waktu</span
                                ><b :class="{ 'wc-danger': daysLeft(job.tanggalTutup) <= 7 }">{{
                                    deadlineLabel(job.tanggalTutup)
                                }}</b>
                            </li>
                        </ul>
                        <!-- Tombol tidak lagi punya varian "Kuota Telah Penuh":
                             lowongan yang lewat tanggal tutup sudah tidak sampai
                             ke halaman ini (disaring visibleLowongan di backend). -->
                        <button type="button" class="wc-btn wc-btn--primary wc-btn--full" @click="goApply(job)">
                            <i :class="loggedIn ? 'bi bi-send-fill' : 'bi bi-box-arrow-in-right'"></i>
                            {{ loggedIn ? 'Lamar Sekarang' : 'Masuk untuk Melamar' }}
                        </button>
                        <!-- Tamu diberi tahu SEBELUM menekan tombol; dulu ia
                             mendarat di formulir lengkap yang tidak pernah bisa
                             terkirim karena lamaran butuh pemilik akun. -->
                        <p v-if="!loggedIn" class="wc-apply__note">
                            <i class="bi bi-info-circle"></i>
                            Melamar butuh akun. Belum punya?
                            <Link :href="registerUrl">Daftar dulu</Link
                            >, gratis dan sekali saja.
                        </p>
                    </div>
                </aside>
            </div>
        </section>
    </CareerLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import {
    applyUrl,
    daysLeft,
    deadlineLabel,
    formatDateTime,
    goApply,
    goToSection,
    lokasiLabel,
    mtUrl,
    observeReveal,
    stageTypeLabel,
    sudahLogin,
    typeClass,
} from '@utils/career/data';

defineOptions({ layout: null });

const props = defineProps({
    lowongan: { type: Object, default: () => ({}) },
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
});

const job = computed(() => props.lowongan || {});
const hasMt = computed(() => props.hasMt);
const offices = computed(() => props.offices || []);

const loggedIn = computed(() => sudahLogin());
// Daftar pun membawa tujuan: sesudah akun jadi, kandidat kembali ke lowongan
// ini, bukan terdampar di portal kosong.
const registerUrl = computed(() => '/register?redirect=' + encodeURIComponent(applyUrl(job.value)));

let revealObs = null;
onMounted(() => nextTick(() => (revealObs = observeReveal())));
onUnmounted(() => revealObs?.disconnect());
</script>
