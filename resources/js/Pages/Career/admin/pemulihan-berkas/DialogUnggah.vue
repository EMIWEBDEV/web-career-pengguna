<!-- WEB CAREER — Pemulihan Berkas: dialog UNGGAH satu isian.

     Dipakai dua jalan: kartu rekomendasi sistem, dan langkah terakhir Tambah
     Manual. Aturan "sudah ada" dijawab di dua tempat:
       - isian yang DIKETAHUI sudah berberkas (Tambah Manual) → peringatan keras
         muncul SEBELUM satu byte pun dikirim;
       - server menjawab 409 (admin lain lebih dulu memulihkannya) → pemegang
         TIMPA mendapat peringatan keras, admin biasa mendapat penolakan yang
         menyebut berkas mana yang ditangkap sistem. -->
<template>
    <AdminModal
        :show="show"
        title="Unggah Berkas Pemulihan"
        :subtitle="konteks ? `${butir.label} · ${kandidat.nama}` : ''"
        icon="bi-cloud-arrow-up-fill"
        size="lg"
        :busy="busy && !alertShow"
        :busy-label="labelSibuk"
        :foot-note="tolak ? 'Tidak ada berkas yang diunggah.' : 'Berkas tercatat atas nama kandidat. Nama Anda dan alasannya ikut disimpan.'"
        @close="tutup"
    >
        <div v-if="konteks" class="pbk-dlg">
            <!-- ── SIAPA & ISIAN MANA ─────────────────────────────────── -->
            <section class="pbk-dlg__ctx">
                <div class="pbk-dlg__who">
                    <span class="pbk-dlg__av" :style="{ background: warnaOrang(kandidat.nama) }">{{ inisial(kandidat.nama) }}</span>
                    <div class="pbk-dlg__whomain">
                        <b>{{ kandidat.nama }}</b>
                        <span>{{ kandidat.kode }}<template v-if="kandidat.posisi"> · {{ kandidat.posisi }}</template></span>
                    </div>
                </div>
                <div class="pbk-dlg__slot">
                    <div class="pbk-dlg__slottop">
                        <i class="bi" :class="ikonIsian" aria-hidden="true"></i>
                        <b>{{ butir.label }}</b>
                        <span v-if="butir.wajib" class="pbk-dlg__tag is-red">Wajib</span>
                        <span v-else class="pbk-dlg__tag">Opsional</span>
                        <span v-if="asal === 'manual'" class="pbk-dlg__tag is-violet">Tambah manual</span>
                    </div>
                    <p v-if="lokasi" class="pbk-dlg__loc"><i class="bi bi-diagram-3" aria-hidden="true"></i> {{ lokasi }}</p>
                    <p class="pbk-dlg__loc"><i class="bi bi-ui-checks-grid" aria-hidden="true"></i> {{ asalFormulir(butir) }}</p>
                </div>
                <p v-if="butir.namaTercatat && !berkasAda.length" class="pbk-dlg__jejak">
                    <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                    <span>
                        Kandidat sempat memilih <b>{{ butir.namaTercatat }}</b>, tetapi berkasnya tidak pernah tersimpan
                        di server. Minta berkas itu lagi dari kandidat.
                    </span>
                </p>
            </section>

            <!-- ── DITOLAK: sudah berberkas, akun tanpa hak TIMPA ────── -->
            <section v-if="tolak" class="pbk-dlg__tolak" role="alert">
                <div class="pbk-dlg__tolakhd">
                    <i class="bi bi-slash-circle-fill" aria-hidden="true"></i>
                    <div>
                        <b>Unggahan ditolak. Isian ini sudah berberkas.</b>
                        <p>
                            Sistem menangkap berkas berikut sudah tercatat untuk isian ini, dan unggahan ganda tidak
                            diizinkan untuk akun Anda. Bila berkas yang ada keliru, minta superadmin memeriksanya.
                        </p>
                    </div>
                </div>
                <ul class="pbk-dlg__files">
                    <li v-for="b in tolak.sudahAda" :key="b.id">
                        <i class="bi" :class="ikonBerkas(b.nama)" aria-hidden="true"></i>
                        <span class="pbk-dlg__fmain">
                            <b>{{ b.nama }}</b>
                            <span>{{ ukuranBerkas(b.ukuran) }} · {{ tglJam(b.waktu) }}<template v-if="b.olehAdmin"> · diunggah admin</template></span>
                        </span>
                        <a :href="b.url" target="_blank" rel="noopener" class="pbk-dlg__open"><i class="bi bi-box-arrow-up-right"></i><span>Buka</span></a>
                    </li>
                </ul>
            </section>

            <template v-else>
                <!-- ── SUDAH ADA (hanya lewat Tambah Manual, pemegang TIMPA) ── -->
                <section v-if="berkasAda.length" class="pbk-dlg__ada">
                    <div class="pbk-dlg__adahd">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <span>
                            Isian ini sudah punya <b>{{ berkasAda.length }} berkas</b>. Berkas baru akan
                            <b>ditambahkan</b>, bukan menggantikan, dan Anda akan diminta konfirmasi sekali lagi.
                        </span>
                    </div>
                    <ul class="pbk-dlg__files">
                        <li v-for="b in berkasAda" :key="b.id">
                            <i class="bi" :class="ikonBerkas(b.nama)" aria-hidden="true"></i>
                            <span class="pbk-dlg__fmain">
                                <b>{{ b.nama }}</b>
                                <span>{{ ukuranBerkas(b.ukuran) }} · {{ tglJam(b.waktu) }}<template v-if="b.olehAdmin"> · diunggah admin</template></span>
                            </span>
                            <a :href="b.url" target="_blank" rel="noopener" class="pbk-dlg__open"><i class="bi bi-box-arrow-up-right"></i><span>Buka</span></a>
                        </li>
                    </ul>
                </section>

                <!-- ── ATURAN BERKAS — dari skema isian ini ─────────────── -->
                <div class="pbk-dlg__aturan">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span>Format <b>{{ (butir.aturan?.format || []).join(', ') }}</b></span>
                    <span class="pbk-dlg__sep" aria-hidden="true"></span>
                    <span>Maks. <b>{{ String(butir.aturan?.maksMb ?? 5).replace('.', ',') }} MB</b></span>
                </div>

                <!-- ── DROPZONE ─────────────────────────────────────────── -->
                <label
                    class="pbk-drop"
                    :class="{ 'is-over': seret > 0, 'is-ada': !!file, 'is-galat': !!galatFile, 'is-busy': busy }"
                    @dragenter.prevent="seret++"
                    @dragover.prevent
                    @dragleave.prevent="seret = Math.max(0, seret - 1)"
                    @drop.prevent="jatuh"
                >
                    <input
                        ref="input"
                        type="file"
                        class="pbk-drop__input"
                        :accept="butir.aturan?.accept || '.pdf'"
                        :disabled="busy"
                        aria-describedby="pbk-drop-aturan"
                        @change="pilih"
                    />
                    <template v-if="!file">
                        <span class="pbk-drop__ic" aria-hidden="true"><i class="bi bi-cloud-arrow-up"></i></span>
                        <span class="pbk-drop__t">Seret berkas ke sini</span>
                        <span class="pbk-drop__s">atau <u>pilih dari perangkat</u></span>
                        <span id="pbk-drop-aturan" class="pbk-drop__hint">{{ teksAturan(butir.aturan) }}</span>
                    </template>
                    <template v-else>
                        <span class="pbk-drop__thumb" aria-hidden="true">
                            <img v-if="gambar && urlPratinjau" :src="urlPratinjau" alt="" />
                            <i v-else class="bi" :class="ikonBerkas(file.name)"></i>
                        </span>
                        <span class="pbk-drop__fmain">
                            <b>{{ file.name }}</b>
                            <span>{{ ukuranBerkas(file.size) }}<template v-if="!galatFile"> · siap diunggah</template></span>
                        </span>
                        <span class="pbk-drop__acts">
                            <a v-if="urlPratinjau" :href="urlPratinjau" target="_blank" rel="noopener" class="pbk-drop__btn">
                                <i class="bi bi-eye"></i><span>Pratinjau</span>
                            </a>
                            <button type="button" class="pbk-drop__btn" :disabled="busy" @click.prevent="ganti">
                                <i class="bi bi-arrow-repeat"></i><span>Ganti</span>
                            </button>
                        </span>
                    </template>
                </label>
                <div v-if="busy" class="pbk-dlg__prog" role="progressbar" :aria-valuenow="progres" aria-valuemin="0" aria-valuemax="100">
                    <span :style="{ width: `${progres}%` }"></span>
                </div>
                <p v-if="galatFile" class="pbk-dlg__err" role="alert"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i> {{ galatFile }}</p>

                <!-- ── ALASAN — wajib ───────────────────────────────────── -->
                <div class="pbk-dlg__alasan">
                    <label :for="idAlasan" class="pbk-dlg__lbl">Alasan / asal berkas <span class="pbk-dlg__req">*</span></label>
                    <div class="pbk-dlg__cepat">
                        <button
                            v-for="a in ALASAN_CEPAT"
                            :key="a"
                            type="button"
                            class="pbk-dlg__chip"
                            :class="{ on: alasan === a }"
                            :disabled="busy"
                            @click="alasan = a"
                        >
                            {{ a }}
                        </button>
                    </div>
                    <textarea
                        :id="idAlasan"
                        v-model="alasan"
                        class="wca-textarea"
                        rows="3"
                        :maxlength="ALASAN_MAKS"
                        :disabled="busy"
                        placeholder="Contoh: CV dikirim ulang kandidat lewat email ke tim rekrutmen."
                    ></textarea>
                    <div class="pbk-dlg__hitung">
                        <span v-if="alasan && alasanPendek" class="is-kurang">Minimal {{ ALASAN_MIN }} karakter</span>
                        <span>{{ alasan.length }}/{{ ALASAN_MAKS }}</span>
                    </div>
                </div>

                <p v-if="galat" class="pbk-dlg__err is-box" role="alert"><i class="bi bi-x-octagon-fill" aria-hidden="true"></i> {{ galat }}</p>
            </template>
        </div>

        <template #footer>
            <button class="wca-btn wca-btn--ghost" type="button" :disabled="busy" @click="tutup">
                <i class="bi bi-x-lg"></i> {{ tolak ? 'Tutup' : 'Batal' }}
            </button>
            <button v-if="!tolak" class="wca-btn wca-btn--dark" type="button" :disabled="!bisaKirim" @click="kirim(false)">
                <span v-if="busy" class="wca-spin" aria-hidden="true"></span>
                <i v-else class="bi bi-cloud-arrow-up-fill"></i>
                {{ busy ? labelSibuk : 'Unggah Berkas' }}
            </button>
        </template>
    </AdminModal>

    <AlertTimpa
        v-if="alertShow && konteks"
        :kandidat="kandidat.nama"
        :label="butir.label"
        :sudah-ada="sudahAda"
        :busy="busy"
        @batal="alertShow = false"
        @lanjut="kirim(true)"
    />
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AdminModal from '@career/AdminModal.vue';
import AlertTimpa from './AlertTimpa.vue';
import { tglJam } from '@utils/tanggal';
import { inisial, warnaOrang } from '@utils/orang';
import {
    ALASAN_CEPAT,
    ALASAN_MAKS,
    ALASAN_MIN,
    asalFormulir,
    extDari,
    galatDari,
    ikonBerkas,
    kirimBerkas,
    lokasiIsian,
    periksaLokal,
    teksAturan,
    ukuranBerkas,
} from '@utils/career/pemulihanBerkas';

const props = defineProps({
    show: { type: Boolean, default: false },
    /** { kandidat: {nama, kode, posisi}, butir, asal: 'rekomendasi'|'manual' } */
    konteks: { type: Object, default: null },
    hak: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'selesai', 'berubah']);

const idAlasan = `pbk-alasan-${Math.random().toString(36).slice(2, 8)}`;

const file = ref(null);
const galatFile = ref('');
const alasan = ref('');
const galat = ref('');
const busy = ref(false);
const progres = ref(0);
const seret = ref(0);
const tolak = ref(null);
const alertShow = ref(false);
const sudahAda = ref([]);
const urlPratinjau = ref('');
const input = ref(null);

const kandidat = computed(() => props.konteks?.kandidat || {});
const butir = computed(() => props.konteks?.butir || {});
const asal = computed(() => props.konteks?.asal || 'rekomendasi');
const berkasAda = computed(() => butir.value.berkas || []);
const lokasi = computed(() => lokasiIsian(butir.value));
const gambar = computed(() => ['jpg', 'jpeg', 'png'].includes(extDari(file.value?.name)));
const ikonIsian = computed(() => ((butir.value.aturan?.format || []).includes('PDF') ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image'));
const alasanPendek = computed(() => alasan.value.trim().length < ALASAN_MIN);
const bisaKirim = computed(() => !!file.value && !galatFile.value && !alasanPendek.value && !busy.value);
const labelSibuk = computed(() => (progres.value < 100 ? `Mengunggah ${progres.value}%…` : 'Menyimpan…'));

function lepasPratinjau() {
    if (urlPratinjau.value) URL.revokeObjectURL(urlPratinjau.value);
    urlPratinjau.value = '';
}

function pasang(f) {
    lepasPratinjau();
    file.value = f || null;
    galat.value = '';
    galatFile.value = f ? periksaLokal(f, butir.value.aturan) || '' : '';
    if (f) urlPratinjau.value = URL.createObjectURL(f);
}

function pilih(e) {
    pasang(e.target.files?.[0]);
    // Dikosongkan supaya memilih berkas yang SAMA lagi tetap memicu change.
    e.target.value = '';
}

function jatuh(e) {
    seret.value = 0;
    if (busy.value) return;
    pasang(e.dataTransfer?.files?.[0]);
}

async function ganti() {
    await nextTick();
    input.value?.click();
}

function reset() {
    pasang(null);
    alasan.value = '';
    galat.value = '';
    busy.value = false;
    progres.value = 0;
    seret.value = 0;
    tolak.value = null;
    alertShow.value = false;
    sudahAda.value = [];
}

function tutup() {
    if (busy.value) return;
    emit('close');
}

async function kirim(timpa) {
    if (busy.value) return;
    galat.value = '';

    const g = periksaLokal(file.value, butir.value.aturan);
    if (g) {
        galatFile.value = g;
        return;
    }
    if (alasanPendek.value) return;

    // Isian yang DIKETAHUI sudah berberkas: peringatan dulu, baru kirim.
    if (!timpa && berkasAda.value.length) {
        if (props.hak?.timpa) {
            sudahAda.value = berkasAda.value;
            alertShow.value = true;
        } else {
            tolak.value = { sudahAda: berkasAda.value };
        }
        return;
    }

    busy.value = true;
    progres.value = 0;
    try {
        const r = await kirimBerkas(
            { butir: butir.value, file: file.value, alasan: alasan.value, timpa },
            (p) => (progres.value = p),
        );
        alertShow.value = false;
        busy.value = false;
        emit('selesai', { pesan: r.message || 'Berkas tersimpan.' });
    } catch (e) {
        const x = galatDari(e, 'Berkas gagal diunggah. Coba lagi.');
        if (x.status === 409 && x.result?.sudahAda) {
            // Keadaan di server berubah sejak daftar dimuat — halaman perlu tahu.
            emit('berubah');
            sudahAda.value = x.result.sudahAda;
            if (x.result.bolehTimpa && !timpa) {
                alertShow.value = true;
            } else {
                alertShow.value = false;
                tolak.value = { sudahAda: x.result.sudahAda };
            }
        } else {
            alertShow.value = false;
            galat.value = x.pesan;
        }
    } finally {
        busy.value = false;
    }
}

watch(
    () => props.show,
    (v) => {
        if (v) reset();
        else lepasPratinjau();
    },
);

onBeforeUnmount(lepasPratinjau);
</script>

<style scoped>
.pbk-dlg {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.pbk-dlg__ctx {
    display: grid;
    gap: 12px;
    padding: 14px;
    border-radius: 16px;
    background: linear-gradient(135deg, #f8f7ff, #f5f8ff);
    border: 1px solid #e7e3fb;
}
.pbk-dlg__who {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}
.pbk-dlg__av {
    flex: none;
    width: 42px;
    height: 42px;
    border-radius: 13px;
    display: grid;
    place-items: center;
    font-weight: 900;
    font-size: 0.85rem;
    color: #fff;
}
.pbk-dlg__whomain {
    min-width: 0;
    display: flex;
    flex-direction: column;
}
.pbk-dlg__whomain b {
    font-size: 0.95rem;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-dlg__whomain span {
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 600;
}
.pbk-dlg__slot {
    padding-top: 12px;
    border-top: 1px dashed #ddd6fe;
    display: grid;
    gap: 4px;
}
.pbk-dlg__slottop {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px 8px;
}
.pbk-dlg__slottop i {
    color: #6d28d9;
}
.pbk-dlg__slottop b {
    font-size: 0.95rem;
    color: #1e1b4b;
}
.pbk-dlg__tag {
    padding: 2px 9px;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 900;
    background: #f1f5f9;
    color: #475569;
}
.pbk-dlg__tag.is-red {
    background: #fee2e2;
    color: #b91c1c;
}
.pbk-dlg__tag.is-violet {
    background: #ede9fe;
    color: #6d28d9;
}
.pbk-dlg__loc {
    margin: 0;
    font-size: 0.8rem;
    color: #475569;
    font-weight: 600;
    display: flex;
    gap: 7px;
    align-items: baseline;
}
.pbk-dlg__loc i {
    color: #94a3b8;
}
.pbk-dlg__jejak {
    margin: 0;
    display: flex;
    gap: 9px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    font-size: 0.8rem;
    line-height: 1.5;
    color: #78350f;
}
.pbk-dlg__jejak i {
    color: #d97706;
    margin-top: 2px;
}
.pbk-dlg__ada,
.pbk-dlg__tolak {
    border-radius: 16px;
    padding: 12px;
    display: grid;
    gap: 10px;
}
.pbk-dlg__ada {
    background: #fff7ed;
    border: 1px solid #fed7aa;
}
.pbk-dlg__adahd {
    display: flex;
    gap: 9px;
    font-size: 0.82rem;
    line-height: 1.5;
    color: #7c2d12;
}
.pbk-dlg__adahd i {
    color: #ea580c;
    margin-top: 2px;
}
.pbk-dlg__tolak {
    background: #fef2f2;
    border: 1.5px solid #fca5a5;
}
.pbk-dlg__tolakhd {
    display: flex;
    gap: 12px;
}
.pbk-dlg__tolakhd > i {
    flex: none;
    font-size: 1.5rem;
    color: #dc2626;
}
.pbk-dlg__tolakhd b {
    color: #7f1d1d;
    font-size: 0.92rem;
}
.pbk-dlg__tolakhd p {
    margin: 4px 0 0;
    font-size: 0.82rem;
    line-height: 1.55;
    color: #7f1d1d;
}
.pbk-dlg__files {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 6px;
}
.pbk-dlg__files li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
}
.pbk-dlg__files li > i {
    font-size: 1.3rem;
    color: #64748b;
}
.pbk-dlg__fmain {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.pbk-dlg__fmain b {
    font-size: 0.83rem;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-dlg__fmain span {
    font-size: 0.73rem;
    color: #64748b;
}
.pbk-dlg__open {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 36px;
    padding: 0 10px;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 800;
    color: #4f46e5;
    border: 1px solid #e0e7ff;
    text-decoration: none;
}
.pbk-dlg__open:hover {
    background: #eef2ff;
}
.pbk-dlg__aturan {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px 10px;
    font-size: 0.8rem;
    color: #334155;
    font-weight: 600;
}
.pbk-dlg__aturan i {
    color: #10b981;
}
.pbk-dlg__sep {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #cbd5e1;
}

/* ── DROPZONE ───────────────────────────────────────────────── */
.pbk-drop {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-height: 168px;
    padding: 20px;
    border-radius: 18px;
    border: 2px dashed #c7d2fe;
    background:
        radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.08), transparent 60%),
        #fafaff;
    text-align: center;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        transform 0.15s ease;
}
.pbk-drop:hover,
.pbk-drop.is-over {
    border-color: #6366f1;
    background: #f5f3ff;
}
.pbk-drop.is-over {
    transform: scale(1.01);
}
.pbk-drop:focus-within {
    outline: 3px solid rgba(99, 102, 241, 0.35);
    outline-offset: 2px;
}
.pbk-drop.is-ada {
    flex-direction: row;
    justify-content: flex-start;
    text-align: left;
    min-height: 0;
    gap: 14px;
    padding: 14px;
    border-style: solid;
    border-color: #a5b4fc;
    background: #fff;
}
.pbk-drop.is-galat {
    border-color: #fca5a5;
    background: #fffafa;
}
.pbk-drop.is-busy {
    cursor: progress;
    opacity: 0.85;
}
.pbk-drop__input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}
.pbk-drop__ic {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    display: grid;
    place-items: center;
    font-size: 1.7rem;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.32);
    margin-bottom: 6px;
}
.pbk-drop__t {
    font-weight: 900;
    font-size: 0.98rem;
    color: #1e1b4b;
}
.pbk-drop__s {
    font-size: 0.84rem;
    color: #475569;
}
.pbk-drop__s u {
    color: #4f46e5;
    font-weight: 800;
    text-underline-offset: 3px;
}
.pbk-drop__hint {
    margin-top: 6px;
    font-size: 0.74rem;
    font-weight: 700;
    color: #94a3b8;
}
.pbk-drop__thumb {
    flex: none;
    width: 64px;
    height: 64px;
    border-radius: 14px;
    overflow: hidden;
    display: grid;
    place-items: center;
    background: #eef2ff;
    font-size: 1.9rem;
    color: #6366f1;
}
.pbk-drop__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.pbk-drop__fmain {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.pbk-drop__fmain b {
    font-size: 0.9rem;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-drop__fmain span {
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 600;
}
.pbk-drop__acts {
    flex: none;
    display: flex;
    gap: 6px;
}
.pbk-drop__btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 40px;
    padding: 0 12px;
    border-radius: 11px;
    border: 1px solid #e0e7ff;
    background: #fff;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 800;
    color: #4f46e5;
    text-decoration: none;
    cursor: pointer;
}
.pbk-drop__btn:hover {
    background: #eef2ff;
}
.pbk-drop__btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.pbk-dlg__prog {
    height: 6px;
    border-radius: 999px;
    background: #e0e7ff;
    overflow: hidden;
    margin-top: -6px;
}
.pbk-dlg__prog span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #8b5cf6, #6366f1);
    transition: width 0.2s ease;
}
.pbk-dlg__err {
    margin: -6px 0 0;
    display: flex;
    gap: 8px;
    font-size: 0.8rem;
    font-weight: 700;
    color: #b91c1c;
}
.pbk-dlg__err i {
    margin-top: 1px;
}
.pbk-dlg__err.is-box {
    margin: 0;
    padding: 10px 12px;
    border-radius: 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
}

/* ── ALASAN ─────────────────────────────────────────────────── */
.pbk-dlg__alasan {
    display: grid;
    gap: 8px;
}
.pbk-dlg__lbl {
    font-size: 0.8rem;
    font-weight: 900;
    color: #1e293b;
}
.pbk-dlg__req {
    color: #dc2626;
}
.pbk-dlg__cepat {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.pbk-dlg__chip {
    min-height: 34px;
    padding: 0 12px;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #fff;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
    transition: all 0.14s ease;
}
.pbk-dlg__chip:hover {
    border-color: #a5b4fc;
    color: #4338ca;
}
.pbk-dlg__chip.on {
    background: #eef2ff;
    border-color: #6366f1;
    color: #4338ca;
}
.pbk-dlg__hitung {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #94a3b8;
}
.pbk-dlg__hitung .is-kurang {
    margin-right: auto;
    color: #d97706;
}

@media (max-width: 560px) {
    .pbk-drop.is-ada {
        flex-wrap: wrap;
    }
    .pbk-drop__acts {
        width: 100%;
    }
    .pbk-drop__btn {
        flex: 1;
        justify-content: center;
    }
    .pbk-dlg__open span {
        display: none;
    }
    .pbk-dlg__chip {
        min-height: 38px;
    }
}
</style>

<!-- Ponsel: dialog AdminModal yang memuat panel ini menjadi LEMBAR BAWAH. Tanpa
     scoped karena modalnya di-teleport ke <body>; dipilih lewat :has() supaya
     modal halaman lain tidak ikut berubah. -->
<style>
@media (max-width: 560px) {
    .wca-modal-mask:has(.pbk-dlg) {
        place-items: end stretch;
        padding: 0;
    }
    .wca-modal:has(.pbk-dlg) {
        width: 100%;
        max-height: 94dvh;
        border-radius: 22px 22px 0 0;
    }
    .wca-modal:has(.pbk-dlg) .wca-modal__foot {
        padding-bottom: calc(14px + env(safe-area-inset-bottom));
    }
    /* Tombol selebar layar — jempol tidak perlu membidik. */
    .wca-modal:has(.pbk-dlg) .wca-modal__footbtns {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .wca-modal:has(.pbk-dlg) .wca-modal__footbtns .wca-btn {
        width: 100%;
        min-height: 46px;
    }
}
</style>
