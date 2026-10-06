<!--
  WEB CAREER — Ambil foto verifikasi langsung dari kamera.

  Sengaja TIDAK memakai <input type="file" capture>. Di desktop atribut itu
  diabaikan dan dialognya jatuh ke pemilih berkas biasa, sehingga foto orang
  lain tetap bisa dikirim — padahal seluruh gunanya justru memastikan kandidat
  hadir saat pengambilan.

  FOTO HITAM. Aliran kamera sudah "menyala" begitu izin diberikan, tetapi
  beberapa detik pertama bingkainya gelap selagi pengaturan cahaya otomatis
  bekerja. Karena itu tombol "Ambil Foto" baru bisa ditekan setelah jeda
  pemanasan (JEDA_KAMERA_DETIK), dan bingkai yang masih gelap/polos saat
  ditekan ditolak — lihat @utils/kameraWajah.

  Verifikasi wajah WAJIB: tidak ada jalan pintas unggah berkas. Perangkat tanpa
  kamera mendapat kalimat yang mengatakan itu, bukan tombol yang diam saja.

  Aliran kamera dimatikan begitu foto terambil dan saat komponen dilepas. Track
  yang menggantung membuat lampu kamera tetap menyala sepanjang sisa pengisian
  formulir, dan itu wajar dibaca kandidat sebagai perekaman diam-diam.
-->
<template>
    <div class="af">
        <div class="af__panggung" :class="{ 'is-aktif': kameraNyala || nilaiFoto }">
            <img v-if="nilaiFoto" :src="nilaiFoto" alt="Foto verifikasi" />
            <video v-show="kameraNyala && !nilaiFoto" ref="videoEl" autoplay playsinline muted></video>
            <!-- PEMANASAN. Hitungan mundur di atas pratinjau: kandidat melihat
                 dirinya sambil menunggu, dan tahu persis kapan tombolnya bisa
                 ditekan — tombol mati tanpa keterangan terbaca sebagai rusak. -->
            <div v-if="kameraNyala && !nilaiFoto && sisaJeda > 0" class="af__jeda" role="status" aria-live="polite">
                <span class="af__jedaangka">{{ sisaJeda }}</span>
                <span class="af__jedateks">Menyiapkan kamera… posisikan wajahmu di tengah</span>
            </div>
            <!-- Foto sudah pernah diambil tapi gambarnya tidak ada di memori
                 komponen ini. Terjadi setiap kali kandidat pindah langkah lalu
                 kembali: layout bertahap melepas komponen langkah yang tidak
                 sedang dibuka, sehingga dataURL-nya hilang — padahal berkasnya
                 masih dipegang halaman induk dan tetap ikut terkirim.
                 Tanpa penanda ini panggungnya tampak kosong dan kandidat
                 mengira fotonya batal. -->
            <div v-if="!kameraNyala && adaFotoTersimpan" class="af__tersimpan">
                <i class="bi bi-check-circle-fill"></i>
                <strong>Foto sudah diambil</strong>
                <small>{{ namaTersimpan }}</small>
            </div>
            <div v-else-if="!kameraNyala && !nilaiFoto" class="af__idle">
                <i class="bi bi-person-bounding-box"></i>
                <span>Kamera belum aktif</span>
            </div>
            <canvas ref="canvasEl" hidden></canvas>
        </div>

        <p v-if="galatKamera" class="af__galat" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> {{ galatKamera }}
        </p>

        <div class="af__aksi">
            <template v-if="!nilaiFoto && !adaFotoTersimpan">
                <button
                    v-if="!kameraNyala"
                    type="button"
                    class="af__btn af__btn--utama"
                    :disabled="disabled || menyalakan"
                    :onClick="disabled || menyalakan ? null : nyalakan"
                >
                    <i class="bi" :class="menyalakan ? 'bi-arrow-repeat af__spin' : 'bi-camera-video-fill'"></i>
                    {{ menyalakan ? 'Menunggu izin kamera…' : 'Aktifkan Kamera' }}
                </button>
                <template v-else>
                    <button
                        type="button"
                        class="af__btn af__btn--utama"
                        :disabled="disabled || sisaJeda > 0"
                        :onClick="disabled || sisaJeda > 0 ? null : jepret"
                    >
                        <i class="bi" :class="sisaJeda > 0 ? 'bi-hourglass-split' : 'bi-camera-fill'"></i>
                        {{ sisaJeda > 0 ? `Menyiapkan kamera (${sisaJeda})` : 'Ambil Foto' }}
                    </button>
                    <button type="button" class="af__btn" :disabled="disabled" :onClick="disabled ? null : matikan">
                        <i class="bi bi-x-lg"></i> Batal
                    </button>
                </template>
            </template>
            <button v-else type="button" class="af__btn" :disabled="disabled" :onClick="disabled ? null : ulangi">
                <i class="bi bi-arrow-repeat"></i> Ambil Ulang
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { JEDA_KAMERA_DETIK, PESAN_TANPA_KAMERA, adaKamera, galatBingkai, pesanGalatKamera } from '@utils/kameraWajah';

const props = defineProps({
    modelValue: { type: String, default: '' },
    // Nama berkas yang tersimpan di jawaban. Jadi satu-satunya bukti bahwa foto
    // pernah diambil setelah komponen ini dipasang ulang tanpa membawa gambarnya.
    namaTersimpan: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    // Kualitas JPEG. Cukup untuk pencocokan wajah, tapi tetap ringan diunggah
    // dari jaringan seluler yang jadi andalan sebagian besar pelamar.
    kualitas: { type: Number, default: 0.85 },
});

const emit = defineEmits(['update:modelValue', 'foto']);

const videoEl = ref(null);
const canvasEl = ref(null);
const kameraNyala = ref(false);
const menyalakan = ref(false);
const galatKamera = ref('');
const nilaiFoto = ref(props.modelValue || '');
/** Detik pemanasan yang tersisa; 0 = foto boleh diambil. */
const sisaJeda = ref(0);

/** Foto pernah diambil, tapi gambarnya tidak ada di komponen ini. */
const adaFotoTersimpan = computed(() => ! nilaiFoto.value && !! props.namaTersimpan);

let aliran = null;
let pewaktuJeda = null;
let cadanganJeda = null;
/** Komponen sudah dilepas — dipakai membatalkan permintaan izin yang telat datang. */
let dibuang = false;

// Foto draf datang BELAKANGAN — URL-nya baru diketahui setelah permintaan draf
// ke server selesai, sementara komponen sudah terpasang lebih dulu. Tanpa
// pengawas ini foto yang sudah tersimpan tidak pernah tampil.
watch(() => props.modelValue, (v) => {
    if (v && v !== nilaiFoto.value) nilaiFoto.value = v;
});

function hentikanJeda() {
    if (pewaktuJeda) clearInterval(pewaktuJeda);
    if (cadanganJeda) clearTimeout(cadanganJeda);
    pewaktuJeda = null;
    cadanganJeda = null;
    sisaJeda.value = 0;
}

/**
 * Hitung mundur pemanasan — dimulai saat video BENAR-BENAR mengalirkan gambar,
 * bukan saat izin diberikan. Di perangkat lambat selisih keduanya bisa beberapa
 * detik, dan jeda yang dimulai terlalu dini habis sebelum gambar pertama tiba.
 * Tombolnya sendiri sudah terkunci sejak kamera menyala (lihat nyalakan()).
 */
function mulaiJeda() {
    if (pewaktuJeda || ! kameraNyala.value) return;
    sisaJeda.value = JEDA_KAMERA_DETIK;
    pewaktuJeda = setInterval(() => {
        sisaJeda.value = Math.max(0, sisaJeda.value - 1);
        if (sisaJeda.value === 0) {
            hentikanJeda();
            // Masih gelap setelah pemanasan = cahaya ruangan atau lensa, bukan
            // soal waktu. Disebutkan sekarang, sebelum kandidat menekan tombol.
            galatKamera.value = galatBingkai(videoEl.value) || '';
        }
    }, 1000);
}

/**
 * Menyalakan kamera.
 *
 * `getUserMedia` menunggu kandidat menjawab dialog izin peramban — bisa
 * berdetik-detik, dan selama itu ia bebas menekan Kembali atau mengklik
 * tombolnya lagi. Dua hal yang harus dijaga:
 *
 *   1. Kalau komponen sudah dilepas saat izin akhirnya diberikan, aliran yang
 *      baru lahir tidak bisa dijangkau siapa pun lagi — lampu kamera menyala
 *      terus sampai tab ditutup. Karena itu aliran yang datang terlambat
 *      langsung dihentikan di tempat.
 *   2. Klik ganda akan menimpa `aliran` dengan aliran kedua dan menelantarkan
 *      yang pertama, dengan akibat yang sama. Karena itu permintaan yang sedang
 *      berjalan mengunci tombolnya.
 */
async function nyalakan() {
    if (menyalakan.value || kameraNyala.value) return;

    galatKamera.value = '';
    if (! navigator.mediaDevices?.getUserMedia) {
        galatKamera.value = 'Peramban ini tidak mendukung akses kamera. Coba Chrome atau Safari versi terbaru.';

        return;
    }

    menyalakan.value = true;
    try {
        // Perangkat yang jelas-jelas tanpa kamera tidak perlu disodori dialog
        // izin yang ujungnya gagal juga.
        if ((await adaKamera()) === false) {
            galatKamera.value = PESAN_TANPA_KAMERA;

            return;
        }

        const baru = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });

        // Dibatalkan selagi izin ditunggu (komponen dilepas, atau matikan()
        // dipanggil). Aliran ini tidak akan pernah dipakai — hentikan sekarang.
        if (dibuang || ! menyalakan.value) {
            baru.getTracks().forEach((t) => t.stop());

            return;
        }

        aliran = baru;
        // Kamera dicabut / dipakai aplikasi lain di tengah jalan: berhenti
        // dengan kalimat yang jelas, bukan pratinjau beku.
        aliran.getVideoTracks().forEach((t) => {
            t.addEventListener('ended', () => {
                if (aliran !== baru) return;
                matikan();
                galatKamera.value = 'Kamera terputus. Sambungkan kembali, lalu aktifkan kamera lagi.';
            });
        });

        kameraNyala.value = true;
        // Tombol LANGSUNG terkunci; hitung mundurnya baru berjalan saat gambar
        // pertama mengalir (lihat mulaiJeda).
        sisaJeda.value = JEDA_KAMERA_DETIK;
        // Elemen <video> baru benar-benar ada di DOM setelah v-show ikut
        // berubah, jadi pemasangan srcObject menunggu satu putaran.
        await Promise.resolve();
        const v = videoEl.value;
        if (v) {
            v.addEventListener('playing', mulaiJeda, { once: true });
            v.srcObject = aliran;
        }
        // Cadangan untuk peramban yang tidak memicu `playing`: hitung mundur
        // tetap berjalan, dan penilaian bingkai saat jepret tetap menjaga.
        cadanganJeda = setTimeout(() => {
            cadanganJeda = null;
            if (sisaJeda.value === JEDA_KAMERA_DETIK) mulaiJeda();
        }, 2500);
    } catch (e) {
        kameraNyala.value = false;
        galatKamera.value = pesanGalatKamera(e);
    } finally {
        menyalakan.value = false;
    }
}

function matikan() {
    // Menandai permintaan yang mungkin sedang menunggu izin sebagai batal.
    menyalakan.value = false;
    hentikanJeda();
    if (aliran) {
        aliran.getTracks().forEach((t) => t.stop());
        aliran = null;
    }
    kameraNyala.value = false;
}

/**
 * Sisi terpanjang foto. Server menolak berkas di atas 2 MB, dan kamera ponsel
 * kelas atas menangkap 4K — satu jepretan bisa menembus batas itu lalu ditolak
 * setelah kandidat mengira fotonya sudah masuk. 1280 px lebih dari cukup untuk
 * mencocokkan wajah, dan hasilnya konsisten di bawah beberapa ratus kilobyte.
 */
const SISI_MAKS = 1280;

function jepret() {
    const v = videoEl.value;
    const c = canvasEl.value;
    if (! v || ! c || sisaJeda.value > 0) return;

    // Pagar terakhir: bingkai yang masih hitam/polos TIDAK dijadikan foto.
    const galat = galatBingkai(v);
    if (galat) {
        galatKamera.value = galat;

        return;
    }
    galatKamera.value = '';

    const skala = Math.min(1, SISI_MAKS / Math.max(v.videoWidth, v.videoHeight));
    c.width = Math.round(v.videoWidth * skala);
    c.height = Math.round(v.videoHeight * skala);
    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);

    const dataUrl = c.toDataURL('image/jpeg', props.kualitas);
    nilaiFoto.value = dataUrl;
    matikan();

    emit('update:modelValue', dataUrl);
    emit('foto', dataUrl);
}

function ulangi() {
    nilaiFoto.value = '';
    emit('update:modelValue', '');
    emit('foto', '');
    nyalakan();
}

onBeforeUnmount(() => {
    dibuang = true;
    matikan();
});
</script>

<style scoped>
.af { display: flex; flex-direction: column; gap: .5rem; }

/* Blok kamera ditaruh di tengah kolomnya. Field ini hampir selalu memakai
   lebar penuh, jadi panggung 22rem yang menempel ke kiri menyisakan ruang
   kosong lebar di kanan dan terbaca seperti tata letak yang belum selesai. */
.af__panggung {
    position: relative;
    aspect-ratio: 4 / 3;
    width: 100%;
    max-width: 22rem;
    margin-inline: auto;
    border-radius: 12px;
    overflow: hidden;
    background: #f1f5f9;
    border: 1.5px dashed rgba(11, 16, 51, .16);
    display: grid;
    place-items: center;
}
.af__panggung.is-aktif { border-style: solid; border-color: rgba(79, 70, 229, .35); }
.af__panggung img,
.af__panggung video { width: 100%; height: 100%; object-fit: cover; display: block; }
/* Pratinjau kamera dicerminkan supaya terasa seperti cermin. Tanpa ini gerakan
   ke kanan tampak ke kiri dan kandidat kesulitan memposisikan wajahnya. */
.af__panggung video { transform: scaleX(-1); }

/* Hitung mundur pemanasan — di atas pratinjau, tidak menutupi wajah. */
.af__jeda {
    position: absolute;
    left: 50%;
    bottom: .7rem;
    transform: translateX(-50%);
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    width: max-content;
    max-width: calc(100% - 1.4rem);
    padding: .35rem .75rem .35rem .4rem;
    border-radius: 16px;
    background: rgba(15, 23, 42, .72);
    color: #fff;
    font-size: 11.5px;
    font-weight: 700;
    backdrop-filter: blur(4px);
}
.af__jedaangka {
    flex: none;
    width: 1.7rem;
    height: 1.7rem;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 13px;
    font-weight: 900;
    background: linear-gradient(140deg, #4f46e5, #7c3aed);
    animation: afDenyut 1s ease-in-out infinite;
}
/* Boleh dua baris di layar sempit — kalimat petunjuk yang terpotong di tengah
   justru menyembunyikan bagian yang paling perlu dibaca. */
.af__jedateks { line-height: 1.3; }
@keyframes afDenyut { 50% { box-shadow: 0 0 0 5px rgba(124, 58, 237, .28); } }

.af__idle { display: flex; flex-direction: column; align-items: center; gap: .35rem; color: #94a3b8; font-size: 12px; }
.af__idle .bi { font-size: 1.8rem; }

.af__tersimpan { display: flex; flex-direction: column; align-items: center; gap: .2rem; padding: 0 1rem; text-align: center; color: #059669; }
.af__tersimpan .bi { font-size: 1.8rem; }
.af__tersimpan strong { font-size: 12.5px; font-weight: 800; }
.af__tersimpan small { font-size: 11px; color: #94a3b8; overflow-wrap: anywhere; }

.af__spin { display: inline-block; animation: afSpin .9s linear infinite; }
@keyframes afSpin { to { transform: rotate(360deg); } }

.af__galat { margin: 0 auto; max-width: 22rem; font-size: 11.5px; line-height: 1.5; color: #b91c1c; display: flex; align-items: flex-start; justify-content: center; gap: .3rem; text-align: center; }
.af__galat .bi { flex: none; margin-top: .1rem; }

.af__aksi { display: flex; flex-wrap: wrap; justify-content: center; gap: .4rem; }
.af__btn {
    border: 1px solid rgba(11, 16, 51, .14); border-radius: .7rem; background: #fff; color: #475569;
    font: inherit; font-size: 12.5px; font-weight: 700; padding: .5rem .9rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: .35rem; transition: filter 150ms ease;
    min-height: 40px;
}
.af__btn:disabled { opacity: .5; cursor: not-allowed; }
.af__btn--utama { border-color: transparent; background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; }
.af__btn:hover:not(:disabled) { filter: brightness(1.05); }

@media (prefers-reduced-motion: reduce) {
    .af__jedaangka, .af__spin { animation: none; }
}
</style>
