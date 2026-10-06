import './bootstrap';

/*
 * URUTAN IMPOR CSS DI BAWAH INI MENENTUKAN SIAPA YANG MENANG.
 *
 * Vite memancarkan CSS mengikuti urutan impornya, dan pada kekhususan yang
 * sama, yang belakangan menang. Sebelumnya evo-theme.css diimpor di baris
 * kedua sementara element-plus/dist/index.css jauh di bawah — artinya SELURUH
 * tema EVO kalah dari gaya bawaan Element Plus, kecuali aturan yang dipaksa
 * dengan !important.
 *
 * Itu sebabnya evo-theme.css penuh !important pada kotak input dan select:
 * satu-satunya cara menang melawan urutan yang terbalik. Dan yang belum
 * sempat dipaksa — warna item dropdown, popper, ikon chevron — tetap tampil
 * dengan warna bawaan Element Plus, di layar yang seluruh sisanya sudah
 * indigo EVO.
 *
 * Maka pustakanya dimuat DULU, tema aplikasi SESUDAHNYA. Ini urutan yang
 * lazim untuk pustaka komponen: bawaan dulu, penyesuaian belakangan.
 *
 * JANGAN dikembalikan ke urutan lama tanpa membaca ini. Gejalanya tidak akan
 * berupa galat — hanya sebagian warna yang diam-diam berubah kembali.
 */
import 'element-plus/dist/index.css';
import '../css/evo-theme.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import FloatingVue from 'floating-vue';
import 'floating-vue/dist/style.css';
import { plugin, defaultConfig } from '@formkit/vue';
import '@formkit/themes/genesis';
import 'vue-select/dist/vue-select.css';
import ElementPlus from 'element-plus';
// CSS-nya sudah diimpor paling atas — lihat catatan urutan di sana.
import axios from 'axios';
import AppShell from './Layouts/AppShell.vue';
// Refresh CSRF single-flight dipusatkan di utils/csrf agar dipakai bersama
// interceptor axios (di bawah) dan wrapper fetch (di bootstrap.js).
import { refreshCsrfToken } from './utils/csrf';
// Akhiran judul tab ("... | Careers Evo Group") — satu format untuk semua halaman.
import { judulHalaman } from './utils/judulHalaman';

// Bersihkan SEMUA jejak token CSRF lama dari sebuah request config sebelum
// retry. Laravel memeriksa token dengan urutan: body `_token` -> header
// X-CSRF-TOKEN -> header/cookie X-XSRF-TOKEN. Jadi membuang header saja TIDAK
// cukup kalau komponen ikut menaruh `_token` di body (token meta yang basi) —
// retry tetap 419. Fungsi ini menghapus header stale + field `_token` dari body
// (object / JSON string / FormData / URLSearchParams) supaya axios mengisi ulang
// X-XSRF-TOKEN otomatis dari cookie XSRF-TOKEN yang segar.
function stripStaleCsrf(config) {
    if (!config) return;

    // 1) Header stale.
    if (config.headers) {
        delete config.headers['X-XSRF-TOKEN'];
        delete config.headers['X-CSRF-TOKEN'];
        if (config.headers.common) {
            delete config.headers.common['X-XSRF-TOKEN'];
            delete config.headers.common['X-CSRF-TOKEN'];
        }
    }

    // 2) Field `_token` di body.
    const data = config.data;
    if (!data) return;

    if (typeof FormData !== 'undefined' && data instanceof FormData) {
        if (data.has('_token')) data.delete('_token');
        return;
    }

    if (typeof URLSearchParams !== 'undefined' && data instanceof URLSearchParams) {
        if (data.has('_token')) data.delete('_token');
        return;
    }

    if (typeof data === 'string') {
        // Body sudah ter-serialize jadi JSON string (axios biasanya melakukan ini
        // SETELAH interceptor, tapi jaga-jaga kalau komponen kirim string sendiri).
        try {
            const parsed = JSON.parse(data);
            if (parsed && typeof parsed === 'object' && '_token' in parsed) {
                delete parsed._token;
                config.data = JSON.stringify(parsed);
            }
        } catch (e) {
            // Bukan JSON (mis. form-urlencoded string) — abaikan, biarkan apa adanya.
        }
        return;
    }

    if (typeof data === 'object' && '_token' in data) {
        delete data._token;
    }
}

axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error?.response?.status;
        const config = error?.config || {};
        const requestUrl = config.url || '';

        // Endpoint secure PDF viewer punya alur token/nonce sendiri (handshake,
        // grant, page image). 401/403/419 di sini = token replay/expired/rate-limit,
        // BUKAN sesi browser habis. Viewer menangani sendiri (reload handshake),
        // jadi jangan paksa reload/redirect.
        const isSecurePdfRequest = requestUrl.includes('/lms/materi/secure-pdf/');

        // Permintaan refresh CSRF itu sendiri tidak boleh memicu refresh lagi.
        const isRefreshRequest = config.skipAuthRefresh === true;

        if (status === 419 && !isSecurePdfRequest && !isRefreshRequest && !config._csrfRetried) {
            // 419 = CSRF token mismatch (mis. session di-regenerate saat login).
            // Daripada reload halaman (yang membuang aksi user & memaksa klik ulang),
            // segarkan token CSRF secara silent lalu ULANGI request asli sekali.
            // User tidak melihat error apa pun; approve/reject langsung berhasil.
            try {
                await refreshCsrfToken();

                config._csrfRetried = true; // tandai agar tidak retry berulang

                // PENTING: jangan set token manual. config asli masih membawa
                // header X-XSRF-TOKEN / X-CSRF-TOKEN token LAMA. Hapus keduanya
                // agar axios mengisi ulang X-XSRF-TOKEN otomatis dari cookie
                // XSRF-TOKEN yang baru (sesuai xsrfCookieName/xsrfHeaderName).
                // Menimpa manual dengan nilai cookie mentah justru bisa salah
                // format -> tetap 419.
                stripStaleCsrf(config);

                return axios(config);
            } catch (refreshError) {
                // Refresh gagal -> sesi benar-benar mati. Lempar ke login.
                window.location.assign('/login');
                return new Promise(() => {});
            }
        }

        return Promise.reject(error);
    },
);

createInertiaApp({
    // SATU tempat yang menentukan bentuk judul tab untuk SELURUH halaman.
    // Halaman cukup menulis <Head title="Master Kampus" />, Inertia yang
    // menempelkan " | Careers Evo Group". Halaman tanpa <Head> tetap dapat
    // judul default, bukan URL mentah seperti sebelumnya.
    title: judulHalaman,
    progress: {
        color: '#4f46e5',
        delay: 150,
        includeCSS: true,
        showSpinner: false,
    },
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
        if (typeof page.default.layout === 'undefined') {
            page.default.layout = AppShell;
        }

        return page;
    },
    setup({ el, App, props, plugin: inertiaPlugin }) {
        createApp({ render: () => h(App, props) })
            .use(inertiaPlugin)
            .use(FloatingVue)
            .use(plugin, defaultConfig)
            .use(ElementPlus)
            .mount(el);
    },
});
