<!-- WEB CAREER — Input telepon dengan pemilih KODE NEGARA (default Indonesia +62).
     SATU kotak utuh: [🚩 +62 ▾] | [nomor]. Kiri = tombol pembuka dropdown negara
     (pencarian di DALAM dropdown, jadi ketikan nomor TIDAK tertelan ke pencarian);
     kanan = kolom nomor. Nilai tersimpan = dial + nomor lokal ("62"+"81234…"). -->
<template>
    <div class="tnp" :class="{ 'is-disabled': disabled }">
        <el-popover
            ref="popRef"
            placement="bottom-start"
            :width="300"
            trigger="click"
            popper-class="tnp-pop"
            :disabled="disabled"
            @show="onShow"
            @hide="cari = ''"
        >
            <template #reference>
                <button type="button" class="tnp__trigger" :disabled="disabled" tabindex="-1">
                    <img v-if="isoTerpilih" :src="flagUrl(isoTerpilih.iso)" class="tnp__flag" alt="" />
                    <span class="tnp__dial">+{{ dialTerpilih }}</span>
                    <i class="tnp__chev bi bi-chevron-down"></i>
                </button>
            </template>

            <div class="tnp__panel">
                <input
                    ref="cariEl"
                    v-model="cari"
                    class="tnp__search"
                    type="text"
                    placeholder="Cari negara / kode…"
                />
                <div class="tnp__list">
                    <button
                        v-for="n in daftarTampil"
                        :key="n.iso"
                        type="button"
                        class="tnp__item"
                        :class="{ 'is-active': n.iso === iso }"
                        @click="pilihNegara(n.iso)"
                    >
                        <img :src="flagUrl(n.iso)" class="tnp__flag" alt="" loading="lazy" />
                        <span class="tnp__optname">{{ n.nama }}</span>
                        <span class="tnp__optdial">+{{ n.dial }}</span>
                    </button>
                    <div v-if="!daftarTampil.length" class="tnp__empty">Negara tidak ditemukan</div>
                </div>
            </div>
        </el-popover>

        <span class="tnp__sep" aria-hidden="true"></span>

        <input
            class="tnp__num"
            type="tel"
            inputmode="numeric"
            autocomplete="tel-national"
            :maxlength="maksLokal"
            :value="lokal"
            :disabled="disabled"
            :placeholder="placeholder || '81234567890'"
            @beforeinput="tolakBukanAngka"
            @input="ubahLokal($event)"
        />
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { KODE_TELEPON, KODE_DEFAULT, deteksiNegara } from '../../utils/kodeTelepon';

const props = defineProps({
    modelValue: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const iso = ref(KODE_DEFAULT);
const lokal = ref('');
const cari = ref('');
const popRef = ref(null);
const cariEl = ref(null);

const isoTerpilih = computed(() => KODE_TELEPON.find((k) => k.iso === iso.value) || null);
const dialTerpilih = computed(() => (isoTerpilih.value ? isoTerpilih.value.dial : '62'));

const daftarTampil = computed(() => {
    const q = cari.value.trim().toLowerCase().replace(/^\+/, '');
    if (!q) return KODE_TELEPON;
    return KODE_TELEPON.filter((k) => k.nama.toLowerCase().includes(q) || k.dial.includes(q));
});

function flagUrl(code) { return `https://flagcdn.com/20x15/${String(code).toLowerCase()}.png`; }
function onShow() { nextTick(() => cariEl.value && cariEl.value.focus()); }

function gabung() { return lokal.value ? (dialTerpilih.value + lokal.value) : ''; }

/**
 * Batas panjang bagian LOKAL, dihitung dari kode negaranya.
 *
 * E.164 membatasi nomor telepon di 15 digit TERMASUK kode negara. Angka mati 18
 * yang dipakai sebelumnya membiarkan orang mengetik nomor yang pasti ditolak
 * saat disimpan — dan penolakannya baru muncul setelah seluruh formulir diisi.
 * Negara berkode panjang (mis. +1264) otomatis dapat jatah lebih pendek.
 */
const maksLokal = computed(() => Math.max(4, 15 - dialTerpilih.value.length));

/**
 * TOLAK HURUF SEBELUM SEMPAT MASUK.
 *
 * `inputmode="numeric"` hanya MENYARANKAN papan angka di ponsel; di desktop ia
 * tidak menghalangi apa pun, dan `type="tel"` memang sengaja mengizinkan huruf
 * (sebagian negara memakai nomor bergaya 1-800-FLOWERS). Jadi keduanya bukan
 * pengaman.
 */
function tolakBukanAngka(e) {
    // Hanya penyisipan langsung yang dicegat. Hapus/undo/tempel dibiarkan lewat
    // lalu dibersihkan ubahLokal() — menolaknya di sini akan ikut memblokir
    // Backspace, dan tempelan "+62 812-3456" yang sebenarnya sah jadi mustahil.
    if (typeof e.data === 'string' && /\D/.test(e.data)) {
        e.preventDefault();
    }
}

function ubahLokal(e) {
    const el = e.target;
    // Hanya digit; buang 0 di depan (trunk prefix, mis. 0812 -> 812 untuk +62).
    let bersih = String(el.value || '').replace(/\D/g, '').replace(/^0+/, '');

    // NOMOR LENGKAP YANG DITEMPEL DIPECAH, BUKAN DITUMPUK.
    //
    // Menempelkan "+62 812-3456-789" ke kolom nomor adalah hal yang paling
    // lazim dilakukan orang. Hasilnya dulu 62628123456789: kode negaranya ikut
    // jadi bagian nomor lokal, lalu dial ditambahkan LAGI di depannya.
    // Panjangnya tetap masuk akal (14 digit), jadi validasi meloloskannya —
    // dan yang tersimpan adalah nomor yang tak bisa dihubungi siapa pun.
    //
    // DUA PAGAR supaya nomor lokal yang sah tidak ikut terpotong:
    //   1. hanya saat TEMPEL — mengetik digit satu per satu tak pernah memicunya;
    //   2. hanya bila berawalan kode negara YANG SEDANG DIPILIH, bukan kode
    //      negara mana pun. Tanpa pagar kedua, nomor Indonesia "812345678"
    //      akan terbaca berkode Jepang (+81) lalu dipenggal jadi "2345678".
    if (e.inputType === 'insertFromPaste'
        && bersih.startsWith(dialTerpilih.value)
        && bersih.length - dialTerpilih.value.length >= 6) {
        bersih = bersih.slice(dialTerpilih.value.length).replace(/^0+/, '');
    }

    // KOTAKNYA DIPAKSA IKUT BERSIH — dan ini bukan kehati-hatian berlebihan.
    //
    // Input ini terikat lewat :value, jadi Vue hanya menyentuh DOM saat nilai
    // reaktifnya BERUBAH. Ketik "8" lalu "a": hasil bersihnya tetap "8" — tidak
    // berubah — sehingga Vue tidak memperbarui apa pun dan huruf "a" TETAP
    // TERLIHAT di kotak. Yang tersimpan sudah benar, tapi orang membaca layar,
    // bukan variabel: ia melihat "8a" dan mengira nomornya memang begitu.
    if (el.value !== bersih) {
        el.value = bersih;
    }

    if (bersih === lokal.value) {
        return;
    }

    lokal.value = bersih;
    emit('update:modelValue', gabung());
}
function pilihNegara(newIso) {
    iso.value = newIso;
    cari.value = '';
    if (popRef.value && popRef.value.hide) popRef.value.hide();
    emit('update:modelValue', gabung());
}

// Sinkron dari luar (prefill/draf): pecah nomor tersimpan → negara + lokal.
watch(
    () => props.modelValue,
    (v) => {
        if (v === gabung()) return; // perubahan dari komponen ini sendiri
        const digits = String(v || '').replace(/\D/g, '');
        const det = deteksiNegara(digits);
        iso.value = det.iso;
        lokal.value = digits.startsWith(det.dial) ? digits.slice(det.dial.length) : digits;
    },
    { immediate: true },
);
</script>

<style scoped>
/* SATU kotak utuh; isinya borderless supaya menyatu (bukan dua kotak terpisah). */
.tnp {
    display: flex;
    align-items: stretch;
    width: 100%;
    height: 40px;
    border: 1px solid #dcdfe6;
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.tnp:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12); }
.tnp.is-disabled { background: #f5f7fa; }

/* Tombol pembuka dropdown negara (bendera + kode + chevron). */
.tnp__trigger {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 100%;
    padding: 0 10px 0 12px;
    border: none;
    background: transparent;
    cursor: pointer;
    font: inherit;
    color: #1f2937;
    white-space: nowrap;
}
.tnp__trigger:disabled { cursor: not-allowed; }
.tnp__dial { font-size: 14px; font-weight: 600; }
.tnp__chev { font-size: 11px; color: #94a3b8; }

.tnp__sep { width: 1px; align-self: stretch; background: #e5e7eb; margin: 7px 0; }

/* Kolom nomor — native, tanpa border sendiri, mengisi sisa lebar. */
.tnp__num {
    flex: 1;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    box-shadow: none;
    padding: 0 12px;
    font-size: 14px;
    font-family: inherit;
    color: #1f2937;
}
.tnp__num::placeholder { color: #9aa4b2; }
.tnp__num:disabled { cursor: not-allowed; color: #a8abb2; }

.tnp__flag { width: 20px; height: 15px; border-radius: 2px; object-fit: cover; box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08); flex: none; }

/* Isi dropdown (di dalam popover). */
.tnp__panel { display: flex; flex-direction: column; gap: 8px; }
.tnp__search { width: 100%; height: 34px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0 10px; font: inherit; font-size: 13px; outline: none; }
.tnp__search:focus { border-color: #6366f1; }
.tnp__list { max-height: 260px; overflow-y: auto; display: flex; flex-direction: column; }
.tnp__item { display: flex; align-items: center; gap: 9px; width: 100%; padding: 7px 8px; border: none; background: transparent; cursor: pointer; border-radius: 8px; font: inherit; text-align: left; }
.tnp__item:hover { background: #f3f4f6; }
.tnp__item.is-active { background: rgba(99, 102, 241, 0.1); }
.tnp__optname { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; color: #1f2937; }
.tnp__optdial { color: #94a3b8; font-size: 12px; flex: none; }
.tnp__empty { padding: 14px; text-align: center; color: #9aa4b2; font-size: 13px; }
</style>

<style>
.tnp-pop { padding: 8px !important; }
</style>
