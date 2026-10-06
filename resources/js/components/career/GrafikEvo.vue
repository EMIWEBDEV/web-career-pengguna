<!--
    GRAFIK EVO — pembungkus tipis Highcharts untuk Vue.
    ---------------------------------------------------------------------------
    Sengaja ditulis sendiri, bukan memakai paket highcharts-vue, karena tiga
    hal yang justru paling sering bermasalah di panel ini:

      1. REFLOW. Grafik hidup di dalam kartu flex yang lebarnya berubah saat
         panel kiri disembunyikan atau tab berganti. Highcharts hanya mengukur
         ulang saat window resize; kartu yang menyusut tanpa window ikut
         berubah meninggalkan grafik selebar ukuran lamanya, menonjol keluar
         kartu. ResizeObserver di bawah menutup itu.
      2. GRAFIK DI TAB TERSEMBUNYI. Elemen ber-display:none punya lebar 0 saat
         digambar, dan Highcharts mengunci ukuran itu. Di sini penggambaran
         ditunda sampai elemennya benar-benar punya lebar.
      3. Akses langsung ke instansi chart (untuk klik sel heatmap) tanpa
         menebak-nebak bentuk API pembungkus pihak ketiga.

    Pemakaian:
        <GrafikEvo :opsi="opsiHeatmap({...})" />
-->
<template>
    <div ref="wadah" class="gev" :style="{ minHeight: (opsi?.chart?.height || 220) + 'px' }"></div>
</template>

<script>
import { pasangTemaEvo } from '@utils/grafik';

export default {
    name: 'GrafikEvo',
    props: {
        /** Opsi Highcharts lengkap — biasanya hasil perakit di utils/grafik.js. */
        opsi: { type: Object, required: true },
        /**
         * Gambar ulang dari nol alih-alih memperbarui yang ada. Diperlukan saat
         * TIPE grafik berubah (garis → heatmap); update biasa tidak bisa
         * mengganti tipe seri dengan bersih.
         */
        bangunUlang: { type: Boolean, default: false },
    },
    emits: ['siap'],
    data() {
        return { chart: null, ro: null, tungguLebar: null };
    },
    watch: {
        opsi: {
            deep: true,
            handler(baru) {
                if (!this.chart) { this.gambar(); return; }
                if (this.bangunUlang) { this.buang(); this.gambar(); return; }

                // oneToOne: seri yang hilang dari opsi ikut dibuang, bukan
                // ditinggal menumpuk. Tanpa itu, berganti terbitan meninggalkan
                // garis milik terbitan sebelumnya di atas grafik yang baru.
                this.chart.update(baru, true, true, true);
            },
        },
    },
    mounted() {
        this.gambar();

        if (typeof ResizeObserver !== 'undefined') {
            this.ro = new ResizeObserver(() => {
                if (!this.chart) { this.gambar(); return; }
                this.chart.reflow();
            });
            this.ro.observe(this.$refs.wadah);
        }
    },
    beforeUnmount() {
        this.ro?.disconnect();
        clearTimeout(this.tungguLebar);
        this.buang();
    },
    methods: {
        gambar() {
            const el = this.$refs.wadah;
            if (!el || this.chart) return;

            // Lebar 0 = elemennya belum tampil (tab lain, induk display:none).
            // Menggambar sekarang mengunci grafik pada lebar nol selamanya.
            if (!el.clientWidth) {
                clearTimeout(this.tungguLebar);
                this.tungguLebar = setTimeout(() => this.gambar(), 80);

                return;
            }

            const Highcharts = pasangTemaEvo();
            this.chart = Highcharts.chart(el, this.opsi);
            this.$emit('siap', this.chart);
        },
        buang() {
            try { this.chart?.destroy(); } catch (e) { /* sudah terlepas */ }
            this.chart = null;
        },
    },
};
</script>

<style scoped>
.gev { width: 100%; }
/* Highcharts menyisipkan div-nya sendiri; pastikan tidak melebihi kartu. */
.gev :deep(.highcharts-container) { width: 100% !important; }
</style>
