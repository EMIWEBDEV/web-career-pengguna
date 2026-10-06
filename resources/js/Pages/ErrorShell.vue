<!--
  WEB CAREERS — status/error DI DALAM shell admin (navbar + sidebar tetap tampil).
  Sengaja TIDAK meng-export `layout`, sehingga app.js memasang AppShell seperti
  halaman biasa. Dipakai saat pengguna sudah masuk panel dan sebuah menu
  sedang dalam mode pemeliharaan — ia tetap bisa berpindah ke menu lain.
-->
<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ErrorContent from '@career/ErrorContent.vue';

const props = defineProps({
    status: { type: [Number, String], default: 503 },
    message: { type: String, default: '' },
    title: { type: String, default: '' },
    homeUrl: { type: String, default: '/karir' },
    referensi: { type: String, default: '' },
    maintenanceMenu: { type: String, default: '' },
    jendela: { type: String, default: '21.00 – 22.00 WIB' },
    tanggal: { type: String, default: '' },
    // Props shell (layout & auth) ikut dikirim controller — tidak dipakai di sini,
    // tetapi harus dideklarasikan agar tidak jatuh sebagai attribute HTML.
    layout: { type: Object, default: null },
    auth: { type: Object, default: null },
    akses: { type: Object, default: null },
});

const judulTab = computed(() => {
    const peta = { 403: 'Akses Ditolak', 404: 'Tidak Ditemukan', 503: 'Mode Pemeliharaan' };
    return peta[String(props.status)] || 'Informasi';
});
</script>

<template>
    <Head :title="judulTab" />

    <div class="errs-wrap">
        <ErrorContent
            :status="status" :message="message" :title="title" :home-url="homeUrl"
            :referensi="referensi" :maintenance-menu="maintenanceMenu"
            :jendela="jendela" :tanggal="tanggal"
        />
    </div>
</template>

<style scoped>
/* Di dalam shell: kartu diberi latar lembut sendiri (tanpa aurora full-screen)
   supaya tidak bertabrakan dengan latar panel admin. */
.errs-wrap {
    position: relative;
    padding: clamp(8px, 2vw, 20px) 0;
    font-family: 'Inter', 'Plus Jakarta Sans', system-ui, sans-serif;
}
.errs-wrap::before {
    content: '';
    position: absolute; inset: -10% -6% auto -6%; height: 70%;
    background: radial-gradient(ellipse at 30% 20%, rgba(139, 92, 246, .18), transparent 60%),
                radial-gradient(ellipse at 80% 10%, rgba(99, 102, 241, .16), transparent 62%);
    filter: blur(50px); pointer-events: none; z-index: 0;
}
.errs-wrap > * { position: relative; z-index: 1; }
</style>
