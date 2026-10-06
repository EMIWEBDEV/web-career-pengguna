/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
import { installFetchCsrfGuard } from './utils/csrf';

window.axios = axios;

// Pasang guard CSRF terpusat untuk fetch() native. Setiap fetch() mutating
// same-origin otomatis membawa X-XSRF-TOKEN dari cookie segar + auto-retry 419,
// sama seperti interceptor axios. Jadi kode fetch() lama anti-419 tanpa diubah.
installFetchCsrfGuard();

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Andalkan cookie XSRF-TOKEN: axios otomatis membacanya per-request dan
// mengirimnya sebagai header X-XSRF-TOKEN. Karena Inertia adalah SPA (tanpa
// full reload antar page), token dari <meta> hanya benar saat load pertama;
// jika session di-regenerate server (mis. setelah login) token meta menjadi
// basi dan POST berikutnya kena 419. Cookie selalu mengikuti token terbaru.
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
window.axios.defaults.withCredentials = true;

// CATATAN: SENGAJA tidak menyetel header default X-CSRF-TOKEN dari <meta>.
// Laravel memeriksa header X-CSRF-TOKEN LEBIH DULU daripada cookie X-XSRF-TOKEN.
// Jika kita kirim X-CSRF-TOKEN dari <meta> (yang menjadi BASI setelah session
// di-regenerate, mis. saat login), Laravel memakai token meta lama itu dan
// MENGABAIKAN cookie XSRF-TOKEN yang segar -> 419 terus walau cookie sudah baru.
// Andalkan SEPENUHNYA cookie XSRF-TOKEN (otomatis dikirim axios sebagai
// X-XSRF-TOKEN), yang selalu mengikuti session terbaru.
const csrfToken = document.head.querySelector('meta[name="csrf-token"]');
if (!csrfToken) {
    console.error('CSRF token tidak ditemukan: <meta name="csrf-token"> tidak ada.');
}
