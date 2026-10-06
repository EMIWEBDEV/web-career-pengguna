<!-- WEB CAREER — Pemulihan Berkas: TAMBAH MANUAL (hak TAMBAH_MANUAL).

     Untuk berkas hilang yang TIDAK ditangkap sistem — mis. kandidat mengunggah
     berkas yang salah, atau isian opsional yang ternyata dibutuhkan. Dua
     langkah di sini (kandidat → isian), langkah ketiga (unggah) adalah dialog
     unggah yang sama dengan rekomendasi sistem: satu tempat untuk aturan berkas,
     alasan, dan peringatan "sudah ada".

     Hanya formulir yang sudah TERKIRIM yang bisa ditambahi — keputusan user
     28 Sep 2026. Kandidat tanpa kiriman ditampilkan, tetapi tak bisa dipilih. -->
<template>
    <AdminModal
        :show="show"
        title="Tambah Manual"
        subtitle="Pulihkan berkas yang tidak tertangkap sistem"
        icon="bi-person-plus-fill"
        size="xl"
        :foot-note="step === 1 ? 'Hanya kandidat dengan formulir terkirim yang bisa dipilih.' : 'Berkas lama tidak pernah dihapus dari panel ini.'"
        @close="$emit('close')"
    >
        <template #sticky>
            <ol class="pbk-man__steps" aria-label="Langkah tambah manual">
                <li :class="{ on: step === 1, done: step > 1 }" :aria-current="step === 1 ? 'step' : undefined">
                    <span class="pbk-man__stepno"><i v-if="step > 1" class="bi bi-check-lg"></i><template v-else>1</template></span>
                    <span class="pbk-man__steplbl">Kandidat</span>
                </li>
                <li :class="{ on: step === 2 }" :aria-current="step === 2 ? 'step' : undefined">
                    <span class="pbk-man__stepno">2</span>
                    <span class="pbk-man__steplbl">Isian berkas</span>
                </li>
                <li>
                    <span class="pbk-man__stepno">3</span>
                    <span class="pbk-man__steplbl">Unggah</span>
                </li>
            </ol>
        </template>

        <div class="pbk-man">
            <!-- ═══ LANGKAH 1 — KANDIDAT ═══ -->
            <section v-if="step === 1" class="pbk-man__sec">
                <div class="pbk-man__filter">
                    <div class="wca-segt wca-segt--sm pbk-man__seg" role="group" aria-label="Cari kandidat berdasarkan">
                        <button type="button" class="wca-segt__it" :class="{ on: mode === 'program' }" :aria-pressed="mode === 'program'" @click="gantiMode('program')">
                            <i class="bi bi-collection"></i> Program
                        </button>
                        <button type="button" class="wca-segt__it" :class="{ on: mode === 'loker' }" :aria-pressed="mode === 'loker'" @click="gantiMode('loker')">
                            <i class="bi bi-briefcase"></i> Lowongan (MPP)
                        </button>
                    </div>
                    <el-select
                        v-if="mode === 'program'"
                        v-model="programDipilih"
                        class="pbk-man__sel"
                        filterable
                        clearable
                        placeholder="Semua program"
                        @change="cari"
                    >
                        <el-option v-for="p in opsiProgram" :key="p.id" :value="p.id" :label="p.nama">
                            <span class="pbk-man__optnama">{{ p.nama }}</span>
                            <span class="pbk-man__optn">{{ p.pelamar }} pelamar</span>
                        </el-option>
                    </el-select>
                    <el-select
                        v-else
                        v-model="lokerDipilih"
                        class="pbk-man__sel"
                        filterable
                        clearable
                        placeholder="Semua lowongan"
                        @change="cari"
                    >
                        <el-option v-for="l in opsiLoker" :key="l.kunci" :value="l.kunci" :label="labelLoker(l)">
                            <span class="pbk-man__optnama">{{ l.posisi || 'Tanpa posisi' }}</span>
                            <span class="pbk-man__optn">{{ l.mppRef || 'tanpa MPP' }}</span>
                        </el-option>
                    </el-select>
                    <label class="pbk-man__search">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input
                            v-model="q"
                            type="search"
                            placeholder="Cari nama, kode lamaran, atau email…"
                            aria-label="Cari kandidat"
                            enterkeyhint="search"
                            autocomplete="off"
                            @input="cariTertunda"
                            @keydown.enter.prevent="cari"
                        />
                    </label>
                </div>

                <div v-if="memuat" class="pbk-man__list" aria-busy="true">
                    <div v-for="i in 4" :key="i" class="pbk-man__sk"></div>
                </div>
                <div v-else-if="galat" class="pbk-man__kosong is-galat" role="alert">
                    <i class="bi bi-wifi-off" aria-hidden="true"></i>
                    <b>{{ galat }}</b>
                    <button type="button" class="wca-btn wca-btn--soft wca-btn--sm" @click="cari">Coba lagi</button>
                </div>
                <div v-else-if="!kandidat.length" class="pbk-man__kosong">
                    <i class="bi bi-person-x" aria-hidden="true"></i>
                    <b>Tidak ada kandidat yang cocok</b>
                    <span>Ubah program/lowongan atau kata kunci pencarian.</span>
                </div>
                <template v-else>
                    <p class="pbk-man__info">
                        <template v-if="kandidat.length >= 60">Menampilkan 60 lamaran terbaru yang cocok. Persempit dengan program, lowongan, atau nama.</template>
                        <template v-else>{{ kandidat.length }} lamaran ditemukan.</template>
                    </p>
                    <div class="pbk-man__list">
                        <button
                            v-for="k in kandidat"
                            :key="k.id"
                            type="button"
                            class="pbk-man__kand"
                            :disabled="!k.formulir"
                            @click="pilihKandidat(k)"
                        >
                            <span class="pbk-man__av" :style="{ background: warnaOrang(k.nama) }">{{ inisial(k.nama) }}</span>
                            <span class="pbk-man__kmain">
                                <b>{{ k.nama }}</b>
                                <span>{{ k.kode }}<template v-if="k.posisi"> · {{ k.posisi }}</template></span>
                                <span class="pbk-man__kprog">{{ k.program }}<template v-if="k.mppRef"> · {{ k.mppRef }}</template></span>
                            </span>
                            <span class="pbk-man__kside">
                                <span class="pbk-man__status" :class="`is-${(k.status || '').toLowerCase()}`">{{ labelStatus(k.status) }}</span>
                                <span v-if="k.formulir" class="pbk-man__form"><i class="bi bi-ui-checks-grid"></i> {{ k.formulir }} formulir</span>
                                <span v-else class="pbk-man__form is-nol">Belum ada formulir terkirim</span>
                            </span>
                            <i v-if="k.formulir" class="bi bi-chevron-right pbk-man__go" aria-hidden="true"></i>
                        </button>
                    </div>
                </template>
            </section>

            <!-- ═══ LANGKAH 2 — ISIAN BERKAS ═══ -->
            <section v-else class="pbk-man__sec">
                <div class="pbk-man__who">
                    <span class="pbk-man__av" :style="{ background: warnaOrang(dipilih.nama) }">{{ inisial(dipilih.nama) }}</span>
                    <span class="pbk-man__kmain">
                        <b>{{ dipilih.nama }}</b>
                        <span>{{ dipilih.kode }}<template v-if="dipilih.posisi"> · {{ dipilih.posisi }}</template> · {{ dipilih.program }}</span>
                    </span>
                    <button type="button" class="wca-btn wca-btn--ghost wca-btn--sm pbk-man__ganti" @click="kembali">
                        <i class="bi bi-arrow-left-right"></i> Ganti
                    </button>
                </div>

                <div class="pbk-man__legend" aria-label="Keterangan">
                    <span><i class="pbk-man__dot is-amber"></i> Hilang · rekomendasi sistem</span>
                    <span><i class="pbk-man__dot is-slate"></i> Kosong · opsional</span>
                    <span><i class="pbk-man__dot is-green"></i> Sudah berberkas</span>
                </div>

                <div v-if="memuatIsian" class="pbk-man__list" aria-busy="true">
                    <div v-for="i in 3" :key="i" class="pbk-man__sk is-tinggi"></div>
                </div>
                <div v-else-if="galatIsian" class="pbk-man__kosong is-galat" role="alert">
                    <i class="bi bi-wifi-off" aria-hidden="true"></i>
                    <b>{{ galatIsian }}</b>
                    <button type="button" class="wca-btn wca-btn--soft wca-btn--sm" @click="segarkan">Coba lagi</button>
                </div>
                <div v-else-if="!isian.length" class="pbk-man__kosong">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    <b>Belum ada formulir terkirim</b>
                    <span>Berkas hanya bisa dipulihkan pada formulir yang sudah dikirim kandidat.</span>
                </div>
                <template v-else>
                    <article v-for="f in isian" :key="f.pengisianId" class="pbk-man__form-card">
                        <header class="pbk-man__fhead">
                            <span class="pbk-man__fic" aria-hidden="true"><i class="bi bi-ui-checks-grid"></i></span>
                            <div class="pbk-man__ftitle">
                                <b>{{ f.formulir }}</b>
                                <span>{{ f.sumber === 'PENDAFTARAN' ? 'Formulir pendaftaran' : f.tahap ? `Tahap ${f.tahap}` : 'Formulir' }} · dikirim {{ tglJam(f.waktuKirim) }}</span>
                            </div>
                        </header>

                        <p v-if="!terlihat(f).length" class="pbk-man__noslot">Formulir ini tidak punya isian berkas yang tampil ke kandidat.</p>
                        <ul class="pbk-man__slots">
                            <li v-for="x in terlihat(f)" :key="x.kunci" class="pbk-man__slot" :class="`is-${keadaan(x)}`">
                                <span class="pbk-man__sdot" aria-hidden="true"></span>
                                <div class="pbk-man__smain">
                                    <div class="pbk-man__stop">
                                        <b>{{ x.label }}</b>
                                        <span v-if="x.wajib" class="pbk-man__req">Wajib</span>
                                    </div>
                                    <span v-if="lokasiIsian(x)" class="pbk-man__sloc">{{ lokasiIsian(x) }}</span>
                                    <span class="pbk-man__sstate">
                                        <template v-if="x.berkas.length">
                                            <i class="bi bi-check-circle-fill"></i>
                                            {{ x.berkas.length }} berkas:
                                            <a v-for="b in x.berkas" :key="b.id" :href="b.url" target="_blank" rel="noopener" class="pbk-man__flink">{{ b.nama }}</a>
                                        </template>
                                        <template v-else-if="x.direkomendasikan">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            {{ x.namaTercatat ? `Nama tercatat “${x.namaTercatat}”, berkasnya tidak ada` : 'Wajib, belum ada berkas' }}
                                        </template>
                                        <template v-else><i class="bi bi-dash-circle"></i> Kosong · {{ teksAturan(x.aturan) }}</template>
                                    </span>
                                </div>
                                <button
                                    v-if="keadaan(x) === 'kunci'"
                                    type="button"
                                    class="pbk-man__act is-kunci"
                                    disabled
                                    title="Sudah berberkas. Menambah berkas lagi butuh hak Timpa."
                                >
                                    <i class="bi bi-lock-fill"></i><span>Terkunci</span>
                                </button>
                                <button v-else type="button" class="pbk-man__act" :class="`is-${keadaan(x)}`" @click="pilihIsian(x)">
                                    <template v-if="keadaan(x) === 'timpa'"><i class="bi bi-plus-square-dotted"></i><span>Tambah lagi</span></template>
                                    <template v-else><i class="bi bi-cloud-arrow-up-fill"></i><span>Unggah</span></template>
                                </button>
                            </li>
                        </ul>

                        <!-- Isian yang TIDAK tampil ke kandidat (syarat tampilnya tak
                             terpenuhi). Jarang dibutuhkan, jadi dilipat. -->
                        <div v-if="tersembunyi(f).length" class="pbk-man__hid">
                            <button type="button" class="pbk-man__hidbtn" :aria-expanded="!!buka[f.pengisianId]" @click="buka[f.pengisianId] = !buka[f.pengisianId]">
                                <i class="bi" :class="buka[f.pengisianId] ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                {{ tersembunyi(f).length }} isian tersembunyi di formulir kandidat
                            </button>
                            <ul v-if="buka[f.pengisianId]" class="pbk-man__slots is-hid">
                                <li v-for="x in tersembunyi(f)" :key="x.kunci" class="pbk-man__slot" :class="`is-${keadaan(x)}`">
                                    <span class="pbk-man__sdot" aria-hidden="true"></span>
                                    <div class="pbk-man__smain">
                                        <div class="pbk-man__stop"><b>{{ x.label }}</b></div>
                                        <span v-if="lokasiIsian(x)" class="pbk-man__sloc">{{ lokasiIsian(x) }}</span>
                                        <span class="pbk-man__sstate"><i class="bi bi-eye-slash"></i> Tidak tampil ke kandidat karena syarat isiannya tidak terpenuhi</span>
                                    </div>
                                    <button v-if="keadaan(x) === 'kunci'" type="button" class="pbk-man__act is-kunci" disabled><i class="bi bi-lock-fill"></i><span>Terkunci</span></button>
                                    <button v-else type="button" class="pbk-man__act" :class="`is-${keadaan(x)}`" @click="pilihIsian(x)">
                                        <i class="bi bi-cloud-arrow-up-fill"></i><span>{{ keadaan(x) === 'timpa' ? 'Tambah lagi' : 'Unggah' }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </article>
                </template>
            </section>
        </div>

        <template #footer>
            <button v-if="step === 2" type="button" class="wca-btn wca-btn--ghost" @click="kembali">
                <i class="bi bi-arrow-left"></i> Kandidat lain
            </button>
            <button type="button" class="wca-btn wca-btn--dark" @click="$emit('close')">
                <i class="bi bi-check2"></i> Selesai
            </button>
        </template>
    </AdminModal>
</template>

<script setup>
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import AdminModal from '@career/AdminModal.vue';
import { tglJam } from '@utils/tanggal';
import { inisial, warnaOrang } from '@utils/orang';
import { cariKandidat, galatDari, labelStatus, lokasiIsian, muatIsian, teksAturan } from '@utils/career/pemulihanBerkas';

const props = defineProps({
    show: { type: Boolean, default: false },
    hak: { type: Object, default: () => ({}) },
    opsiProgram: { type: Array, default: () => [] },
    opsiLoker: { type: Array, default: () => [] },
    /** Cakupan halaman saat dialog dibuka: { mode, program, loker }. */
    awal: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'pilih']);

const step = ref(1);
const mode = ref('program');
const programDipilih = ref('');
const lokerDipilih = ref('');
const q = ref('');
const kandidat = ref([]);
const memuat = ref(false);
const galat = ref('');
const dipilih = ref({});
const isian = ref([]);
const memuatIsian = ref(false);
const galatIsian = ref('');
const buka = reactive({});
let sudahDibuka = false;
let token = 0;
let tokenIsian = 0;
let tm = null;

function labelLoker(l) {
    return [l.posisi || 'Tanpa posisi', l.mppRef].filter(Boolean).join(' · ');
}

function gantiMode(m) {
    if (mode.value === m) return;
    mode.value = m;
    programDipilih.value = '';
    lokerDipilih.value = '';
    cari();
}

async function cari() {
    if (tm) clearTimeout(tm);
    const t = ++token;
    memuat.value = true;
    galat.value = '';
    try {
        const r = await cariKandidat({
            program: mode.value === 'program' ? programDipilih.value || undefined : undefined,
            loker: mode.value === 'loker' ? lokerDipilih.value || undefined : undefined,
            q: q.value.trim() || undefined,
        });
        if (t === token) kandidat.value = r;
    } catch (e) {
        if (t === token) galat.value = galatDari(e, 'Daftar kandidat gagal dimuat.').pesan;
    } finally {
        if (t === token) memuat.value = false;
    }
}

function cariTertunda() {
    if (tm) clearTimeout(tm);
    tm = setTimeout(cari, 350);
}

function pilihKandidat(k) {
    if (!k.formulir) return;
    dipilih.value = k;
    step.value = 2;
    segarkan();
}

function kembali() {
    step.value = 1;
    isian.value = [];
}

/** Muat ulang isian kandidat terpilih — dipanggil juga oleh halaman sesudah unggah. */
async function segarkan() {
    if (!dipilih.value?.id) return;
    const t = ++tokenIsian;
    memuatIsian.value = true;
    galatIsian.value = '';
    try {
        const r = await muatIsian(dipilih.value.id);
        if (t === tokenIsian) isian.value = r;
    } catch (e) {
        if (t === tokenIsian) galatIsian.value = galatDari(e, 'Isian berkas gagal dimuat.').pesan;
    } finally {
        if (t === tokenIsian) memuatIsian.value = false;
    }
}

const terlihat = (f) => f.isian.filter((x) => x.terlihat);
const tersembunyi = (f) => f.isian.filter((x) => !x.terlihat);

/** rekomendasi | kosong | timpa (berberkas, boleh ditambah) | kunci (berberkas, tanpa hak) */
function keadaan(x) {
    if (x.berkas.length) return props.hak?.timpa ? 'timpa' : 'kunci';

    return x.direkomendasikan ? 'rekomendasi' : 'kosong';
}

function pilihIsian(x) {
    emit('pilih', {
        kandidat: { id: dipilih.value.id, nama: dipilih.value.nama, kode: dipilih.value.kode, posisi: dipilih.value.posisi },
        butir: x,
    });
}

// Pertama kali dibuka: mulai dari cakupan halaman — admin yang sedang melihat
// satu program hampir pasti mencari kandidat program itu juga.
watch(
    () => props.show,
    (v) => {
        if (!v || sudahDibuka) return;
        sudahDibuka = true;
        mode.value = props.awal?.mode === 'loker' ? 'loker' : 'program';
        programDipilih.value = mode.value === 'program' ? props.awal?.program || '' : '';
        lokerDipilih.value = mode.value === 'loker' ? props.awal?.loker || '' : '';
        cari();
    },
    { immediate: true },
);

onBeforeUnmount(() => tm && clearTimeout(tm));

defineExpose({ segarkan });
</script>

<style scoped>
/* ── LANGKAH ────────────────────────────────────────────────── */
.pbk-man__steps {
    list-style: none;
    margin: 0;
    padding: 10px 0;
    display: flex;
    gap: 6px;
    counter-reset: none;
}
.pbk-man__steps li {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    padding: 6px 10px;
    border-radius: 12px;
    color: #94a3b8;
    font-size: 0.8rem;
    font-weight: 800;
}
.pbk-man__steps li.on {
    background: #eef2ff;
    color: #4338ca;
}
.pbk-man__steps li.done {
    color: #059669;
}
.pbk-man__stepno {
    flex: none;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.75rem;
    font-weight: 900;
    background: #f1f5f9;
    color: inherit;
}
.pbk-man__steps li.on .pbk-man__stepno {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
}
.pbk-man__steps li.done .pbk-man__stepno {
    background: #d1fae5;
}
.pbk-man__steplbl {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pbk-man {
    min-height: 320px;
}
.pbk-man__sec {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* ── PENYARING ──────────────────────────────────────────────── */
.pbk-man__filter {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}
.pbk-man__seg {
    margin-bottom: 0;
}
/* !important — lihat catatan .pbk-field__sel di PemulihanBerkas.vue. */
.pbk-man__sel {
    width: 260px !important;
}
.pbk-man__optnama {
    font-weight: 700;
}
.pbk-man__optn {
    float: right;
    margin-left: 14px;
    font-size: 11.5px;
    color: #64748b;
}
.pbk-man__search {
    flex: 1 1 220px;
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 40px;
    padding: 0 12px;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    background: #fff;
    color: #94a3b8;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}
.pbk-man__search:focus-within {
    border-color: #818cf8;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
}
.pbk-man__search input {
    flex: 1;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    font: inherit;
    font-size: 0.86rem;
    color: #0f172a;
}
.pbk-man__info {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 700;
    color: #64748b;
}

/* ── DAFTAR KANDIDAT ────────────────────────────────────────── */
.pbk-man__list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 10px;
}
.pbk-man__kand {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    padding: 12px 14px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    background: #fff;
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.15s ease;
}
.pbk-man__kand:hover:not(:disabled) {
    border-color: #a5b4fc;
    box-shadow: 0 10px 24px -12px rgba(79, 70, 229, 0.4);
    transform: translateY(-1px);
}
.pbk-man__kand:focus-visible {
    outline: 3px solid rgba(99, 102, 241, 0.4);
    outline-offset: 2px;
}
.pbk-man__kand:disabled {
    cursor: not-allowed;
    background: #f8fafc;
    opacity: 0.75;
}
.pbk-man__av {
    flex: none;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    color: #fff;
    font-weight: 900;
    font-size: 0.8rem;
}
.pbk-man__kmain {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.pbk-man__kmain b {
    font-size: 0.9rem;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-man__kmain span {
    font-size: 0.76rem;
    color: #64748b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-man__kprog {
    color: #94a3b8 !important;
}
.pbk-man__kside {
    flex: none;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}
.pbk-man__status {
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.66rem;
    font-weight: 900;
    background: #f1f5f9;
    color: #475569;
}
.pbk-man__status.is-berjalan {
    background: #e0e7ff;
    color: #4338ca;
}
.pbk-man__status.is-lulus {
    background: #d1fae5;
    color: #047857;
}
.pbk-man__status.is-gugur,
.pbk-man__status.is-mundur {
    background: #fee2e2;
    color: #b91c1c;
}
.pbk-man__form {
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    white-space: nowrap;
}
.pbk-man__form.is-nol {
    color: #b45309;
}
.pbk-man__go {
    color: #a5b4fc;
}

.pbk-man__sk {
    height: 72px;
    border-radius: 16px;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: pbkManKilau 1.3s ease infinite;
}
.pbk-man__sk.is-tinggi {
    height: 140px;
    grid-column: 1 / -1;
}
@keyframes pbkManKilau {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}
.pbk-man__kosong {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 44px 16px;
    text-align: center;
    border-radius: 16px;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    font-size: 0.84rem;
}
.pbk-man__kosong > i {
    font-size: 2rem;
    color: #a5b4fc;
}
.pbk-man__kosong b {
    color: #1e293b;
}
.pbk-man__kosong.is-galat > i {
    color: #f87171;
}

/* ── LANGKAH 2 ──────────────────────────────────────────────── */
.pbk-man__who {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 16px;
    background: linear-gradient(135deg, #f8f7ff, #f5f8ff);
    border: 1px solid #e7e3fb;
}
.pbk-man__ganti {
    flex: none;
}
.pbk-man__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 16px;
    font-size: 0.74rem;
    font-weight: 700;
    color: #64748b;
}
.pbk-man__legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.pbk-man__dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
}
.pbk-man__dot.is-amber {
    background: #f59e0b;
}
.pbk-man__dot.is-slate {
    background: #cbd5e1;
}
.pbk-man__dot.is-green {
    background: #10b981;
}
.pbk-man__form-card {
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    background: #fff;
    overflow: hidden;
}
.pbk-man__fhead {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: #f8fafc;
    border-bottom: 1px solid #eef2f7;
}
.pbk-man__fic {
    flex: none;
    width: 36px;
    height: 36px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    background: #ede9fe;
    color: #6d28d9;
}
.pbk-man__ftitle {
    min-width: 0;
    display: flex;
    flex-direction: column;
}
.pbk-man__ftitle b {
    font-size: 0.9rem;
    color: #1e1b4b;
}
.pbk-man__ftitle span {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 600;
}
.pbk-man__noslot {
    margin: 0;
    padding: 14px;
    font-size: 0.8rem;
    color: #94a3b8;
}
.pbk-man__slots {
    list-style: none;
    margin: 0;
    padding: 6px;
    display: grid;
    gap: 4px;
}
.pbk-man__slot {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 10px 10px 12px;
    border-radius: 14px;
    transition: background 0.15s ease;
}
.pbk-man__slot:hover {
    background: #f8fafc;
}
.pbk-man__slot.is-rekomendasi {
    background: #fffbeb;
}
.pbk-man__sdot {
    flex: none;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #cbd5e1;
}
.pbk-man__slot.is-rekomendasi .pbk-man__sdot {
    background: #f59e0b;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.18);
}
.pbk-man__slot.is-timpa .pbk-man__sdot,
.pbk-man__slot.is-kunci .pbk-man__sdot {
    background: #10b981;
}
.pbk-man__smain {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.pbk-man__stop {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.pbk-man__stop b {
    font-size: 0.87rem;
    color: #0f172a;
}
.pbk-man__req {
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 0.64rem;
    font-weight: 900;
    background: #fee2e2;
    color: #b91c1c;
}
.pbk-man__sloc {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 600;
}
.pbk-man__sstate {
    font-size: 0.75rem;
    color: #64748b;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 6px;
}
.pbk-man__slot.is-rekomendasi .pbk-man__sstate {
    color: #b45309;
    font-weight: 700;
}
.pbk-man__slot.is-timpa .pbk-man__sstate i,
.pbk-man__slot.is-kunci .pbk-man__sstate i {
    color: #10b981;
}
.pbk-man__flink {
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #4f46e5;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 2px;
}
.pbk-man__act {
    flex: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 40px;
    min-width: 112px;
    padding: 0 14px;
    border-radius: 12px;
    border: 1px solid transparent;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 800;
    cursor: pointer;
    color: #fff;
    background: linear-gradient(120deg, #4f46e5, #7c3aed);
    box-shadow: 0 8px 18px rgba(124, 58, 237, 0.25);
    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease;
}
.pbk-man__act:hover:not(:disabled) {
    transform: translateY(-1px);
}
.pbk-man__act.is-kosong {
    color: #4338ca;
    background: #eef2ff;
    border-color: #c7d2fe;
    box-shadow: none;
}
.pbk-man__act.is-timpa {
    color: #b91c1c;
    background: #fef2f2;
    border-color: #fca5a5;
    box-shadow: none;
}
.pbk-man__act.is-kunci {
    color: #94a3b8;
    background: #f1f5f9;
    border-color: #e2e8f0;
    box-shadow: none;
    cursor: not-allowed;
}
.pbk-man__hid {
    border-top: 1px dashed #e2e8f0;
    padding: 6px;
}
.pbk-man__hidbtn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 10px;
    border: none;
    border-radius: 10px;
    background: transparent;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 800;
    color: #64748b;
    cursor: pointer;
}
.pbk-man__hidbtn:hover {
    background: #f1f5f9;
}
.pbk-man__slots.is-hid .pbk-man__slot {
    opacity: 0.85;
}

@media (max-width: 720px) {
    .pbk-man__sel {
        width: 100% !important;
    }
    .pbk-man__seg {
        width: 100%;
    }
    .pbk-man__seg .wca-segt__it {
        flex: 1;
        justify-content: center;
        min-height: 40px;
    }
    .pbk-man__search {
        flex-basis: 100%;
        min-height: 44px;
    }
    .pbk-man__list {
        grid-template-columns: 1fr;
    }
    /* Hanya langkah yang sedang dikerjakan yang berlabel; sisanya tinggal
       nomor — tiga label tak muat di satu baris ponsel. */
    .pbk-man__steps li {
        flex: 0 0 auto;
    }
    .pbk-man__steps li.on {
        flex: 1 1 auto;
    }
    .pbk-man__steplbl {
        display: none;
    }
    .pbk-man__steps li.on .pbk-man__steplbl {
        display: inline;
    }
    .pbk-man__slot {
        flex-wrap: wrap;
    }
    .pbk-man__act {
        width: 100%;
        min-height: 44px;
    }
    .pbk-man__flink {
        max-width: 100%;
    }
}
</style>

<style>
@media (max-width: 560px) {
    .wca-modal-mask:has(.pbk-man) {
        place-items: end stretch;
        padding: 0;
    }
    .wca-modal:has(.pbk-man) {
        width: 100%;
        max-height: 96dvh;
        border-radius: 22px 22px 0 0;
    }
    .wca-modal:has(.pbk-man) .wca-modal__foot {
        padding-bottom: calc(14px + env(safe-area-inset-bottom));
    }
    /* Tombol selebar layar — jempol tidak perlu membidik. */
    .wca-modal:has(.pbk-man) .wca-modal__footbtns {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .wca-modal:has(.pbk-man) .wca-modal__footbtns .wca-btn {
        width: 100%;
        min-height: 46px;
    }
}
</style>
