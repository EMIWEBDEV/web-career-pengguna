<!-- WEB CAREER — JENDELA TUNDA JADWAL / PERBARUI INFO PENUNDAAN (sisi tim).

     Dulu formulir tunda terbuka di dalam panel konfirmasi yang sudah penuh —
     membingungkan (masukan user 1 Okt 2026). Sekarang jendela tersendiri
     dengan tiga langkah yang jelas + pratinjau yang diterima kandidat:
       1. alasan            pilihan dari master (jenis TUNDA); "Lainnya" wajib
                            dijelaskan di pesan
       2. jadwal pengganti  "belum diketahui — akan dikabarkan" ATAU perkiraan
                            tanggal (tanggal saja: jam & tempat menyusul lewat
                            Atur Jadwal)
       3. pesan             opsional, dibaca kandidat apa adanya
     Mode "perbarui": info penundaan diganti (mis. dari belum tahu menjadi
     perkiraan tgl 3) dan kandidat boleh dikabari lewat email.
     Aturannya ditegakkan server (KonfirmasiJadwal::periksaTunda). -->
<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useZIndex } from 'element-plus';
import AdminModal from './AdminModal.vue';
import { galatDari, muatOpsiTunda, perbaruiTunda, tanggalSingkat, tundaJadwal } from '@utils/career/konfirmasi';

const props = defineProps({
    show: { type: Boolean, default: false },
    idAktivitas: { type: String, required: true },
    /** tunda | perbarui */
    mode: { type: String, default: 'tunda' },
    /** Master alasan jenis TUNDA: [{ kode, nama, butuhCatatan }] */
    alasan: { type: Array, default: () => [] },
    /** { tanggalMin, tanggalMaks } — rentang perkiraan yang diterima server. */
    batasTanggal: { type: Object, default: () => ({}) },
    /** { kandidat, aktivitas, waktuTeks } */
    konteks: { type: Object, default: () => ({}) },
    /** Info penundaan saat ini (mode perbarui): { alasanKode, perkiraan, pesan, ... } */
    awal: { type: Object, default: null },
});

const emit = defineEmits(['close', 'selesai']);

const f = reactive({ alasan: '', ada: 'BELUM', perkiraan: '', pesan: '', kabari: true });
const sibuk = ref(false);
const galat = ref('');
const perbarui = computed(() => props.mode === 'perbarui');

// Dibuka dari dalam drawer (worklist / Agenda) — lapisannya harus di atas
// drawer Element Plus, yang z-index-nya naik tiap kali sebuah popup dibuka.
const { nextZIndex } = useZIndex();
const lapis = ref(null);

/**
 * Bahan yang tidak dikirim pemanggil (tombol di baris aktivitas worklist tidak
 * memegang master alasan) dimuat sendiri lewat opsi-tunda — ringan & sekali per
 * halaman; kandidat/aktivitas/waktu sudah dipegang pemanggil lewat `konteks`.
 */
const bahan = reactive({ alasan: [], batas: {}, memuat: false, galat: '' });
const daftarAlasan = computed(() => (props.alasan.length ? props.alasan : bahan.alasan));
const batasPakai = computed(() => (props.batasTanggal?.tanggalMin ? props.batasTanggal : bahan.batas));
const konteksPakai = computed(() => props.konteks || {});

async function muatBahan() {
    bahan.memuat = true;
    bahan.galat = '';
    try {
        const d = await muatOpsiTunda();
        bahan.alasan = d?.alasan || [];
        bahan.batas = d?.tunda || {};
    } catch (e) {
        bahan.galat = galatDari(e, 'Pilihan alasan gagal dimuat.').pesan;
    } finally {
        bahan.memuat = false;
    }
}

watch(() => props.show, (buka) => {
    if (!buka) return;
    lapis.value = nextZIndex();
    if (!props.alasan.length) muatBahan();
    galat.value = '';
    const a = props.awal;
    f.alasan = a?.alasanKode || '';
    f.ada = a?.perkiraan ? 'ADA' : 'BELUM';
    f.perkiraan = a?.perkiraan || '';
    f.pesan = a?.pesan || '';
    f.kabari = true;
}, { immediate: true });

const defAlasan = computed(() => daftarAlasan.value.find((a) => a.kode === f.alasan) || null);
const butuhPesan = computed(() => !!defAlasan.value?.butuhCatatan);

const galatIsian = computed(() => {
    if (!f.alasan) return 'Pilih alasan penundaan.';
    if (f.ada === 'ADA' && !f.perkiraan) return 'Pilih perkiraan tanggal jadwal pengganti.';
    if (butuhPesan.value && f.pesan.trim().length < 5) return 'Jelaskan alasannya di pesan untuk kandidat (minimal 5 huruf).';

    return '';
});

const subjudul = computed(() => {
    const k = konteksPakai.value;
    const dasar = [k.aktivitas, k.kandidat].filter(Boolean).join(' · ');
    if (perbarui.value) return dasar;

    return k.waktuTeks ? `${dasar} · jadwal sekarang ${k.waktuTeks}` : dasar;
});

/** el-date-picker: di luar rentang server = tidak bisa dipilih. */
function tanggalMati(d) {
    const ymd = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const { tanggalMin, tanggalMaks } = batasPakai.value || {};

    return (!!tanggalMin && ymd < tanggalMin) || (!!tanggalMaks && ymd > tanggalMaks);
}

/** Cermin isi email & kartu portal kandidat — kalimatnya sama dengan surelnya. */
const pratinjau = computed(() => ({
    judul: perbarui.value
        ? `Kabar terbaru jadwal ${konteksPakai.value.aktivitas || ''}`.trim()
        : `Jadwal ${konteksPakai.value.aktivitas || ''} ditunda`.replace(/\s+/g, ' '),
    alasan: butuhPesan.value ? (f.pesan.trim() || '…') : (defAlasan.value?.nama || '…'),
    pengganti: f.ada === 'ADA' && f.perkiraan
        ? `Diperkirakan ${tanggalSingkat(f.perkiraan)} — waktu & tempat pastinya menyusul`
        : 'Belum ditetapkan — akan kami kabarkan',
    pesan: !butuhPesan.value && f.pesan.trim() ? f.pesan.trim() : '',
}));

async function simpan() {
    if (sibuk.value) return;
    if (galatIsian.value) {
        galat.value = galatIsian.value;
        return;
    }
    sibuk.value = true;
    galat.value = '';
    const isian = {
        alasan: f.alasan,
        perkiraan: f.ada === 'ADA' ? f.perkiraan : null,
        pesan: f.pesan.trim() || null,
    };
    try {
        const res = perbarui.value
            ? await perbaruiTunda(props.idAktivitas, isian, f.kabari)
            : await tundaJadwal(props.idAktivitas, isian);
        emit('selesai', res?.message || 'Tersimpan.');
    } catch (e) {
        galat.value = galatDari(e, perbarui.value ? 'Info penundaan gagal disimpan.' : 'Jadwal gagal ditunda.').pesan;
    } finally {
        sibuk.value = false;
    }
}
</script>

<template>
    <AdminModal
        :show="show"
        :title="perbarui ? 'Perbarui info penundaan' : 'Tunda jadwal'"
        :subtitle="subjudul"
        icon="bi-pause-circle"
        size="lg"
        :save-label="perbarui ? (f.kabari ? 'Simpan & kabari kandidat' : 'Simpan tanpa email') : 'Tunda jadwal'"
        :save-disabled="!!galatIsian"
        :busy="sibuk"
        busy-label="Menyimpan…"
        :foot-note="perbarui ? 'Portal kandidat langsung ikut berubah.' : 'Kandidat dikabari lewat email dan Portal Kandidat.'"
        :z-index="lapis"
        @close="emit('close')"
        @save="simpan"
    >
        <div class="tnd">
            <p v-if="!perbarui" class="tnd-akibat">
                <i class="bi bi-info-circle-fill"></i>
                <span>
                    Jadwal sekarang<template v-if="konteksPakai.waktuTeks"> (<b>{{ konteksPakai.waktuTeks }}</b>)</template> dikosongkan.
                    Tautan konfirmasi lama tidak berlaku, dan permintaan jadwal lain yang masih terbuka ditutup.
                </span>
            </p>

            <section class="tnd-bag">
                <h4><span class="tnd-no">1</span> Alasan penundaan</h4>
                <div class="tnd-opsi" role="radiogroup" aria-label="Alasan penundaan">
                    <button
                        v-for="a in daftarAlasan"
                        :key="a.kode"
                        type="button"
                        role="radio"
                        class="tnd-chip"
                        :class="{ on: f.alasan === a.kode }"
                        :aria-checked="String(f.alasan === a.kode)"
                        @click="f.alasan = a.kode"
                    >{{ a.nama }}</button>
                </div>
                <p v-if="bahan.memuat" class="tnd-pelan">Memuat pilihan alasan…</p>
                <p v-else-if="bahan.galat" class="tnd-galat">{{ bahan.galat }}</p>
                <p v-else-if="!daftarAlasan.length" class="tnd-pelan">Master alasan penundaan belum terpasang (jalankan skrip SQL konfirmasi).</p>
            </section>

            <section class="tnd-bag">
                <h4><span class="tnd-no">2</span> Jadwal pengganti</h4>
                <div class="tnd-pilih" role="radiogroup" aria-label="Jadwal pengganti">
                    <button type="button" role="radio" class="tnd-pil" :class="{ on: f.ada === 'BELUM' }" :aria-checked="String(f.ada === 'BELUM')" @click="f.ada = 'BELUM'">
                        <i class="bi bi-hourglass-split"></i>
                        <span>
                            <b>Belum diketahui</b>
                            <small>Kandidat diberi tahu jadwal penggantinya akan dikabarkan.</small>
                        </span>
                    </button>
                    <button type="button" role="radio" class="tnd-pil" :class="{ on: f.ada === 'ADA' }" :aria-checked="String(f.ada === 'ADA')" @click="f.ada = 'ADA'">
                        <i class="bi bi-calendar-event"></i>
                        <span>
                            <b>Sudah ada perkiraan tanggal</b>
                            <small>Tanggal saja — jam & tempatnya menyusul lewat Atur Jadwal.</small>
                        </span>
                    </button>
                </div>
                <div v-if="f.ada === 'ADA'" class="tnd-tanggal">
                    <el-date-picker
                        v-model="f.perkiraan"
                        type="date"
                        value-format="YYYY-MM-DD"
                        format="DD/MM/YYYY"
                        placeholder="Perkiraan tanggal"
                        :disabled-date="tanggalMati"
                        :teleported="true"
                        style="width: 220px"
                    />
                    <span v-if="f.perkiraan" class="tnd-pelan">{{ tanggalSingkat(f.perkiraan) }}</span>
                </div>
            </section>

            <section class="tnd-bag">
                <h4>
                    <span class="tnd-no">3</span> Pesan untuk kandidat
                    <small>{{ butuhPesan ? 'wajib — menjelaskan alasan "Lainnya"' : 'opsional' }}</small>
                </h4>
                <textarea
                    v-model="f.pesan"
                    rows="3"
                    maxlength="1000"
                    class="tnd-input"
                    :placeholder="butuhPesan ? 'Jelaskan alasan penundaannya untuk kandidat.' : 'mis. Mohon maaf atas perubahannya — rincian jadwal pengganti kami kirim lewat email.'"
                ></textarea>
            </section>

            <label v-if="perbarui" class="tnd-centang">
                <input v-model="f.kabari" type="checkbox" />
                <span>Kabari kandidat lewat email <small>(kabar terbaru penundaan)</small></span>
            </label>

            <section class="tnd-pratinjau" aria-label="Pratinjau untuk kandidat">
                <div class="tnd-pratinjau__alis"><i class="bi bi-eye"></i> Yang diterima kandidat — email &amp; Portal Kandidat</div>
                <b class="tnd-pratinjau__judul">{{ pratinjau.judul }}</b>
                <dl>
                    <dt>Alasan</dt>
                    <dd>{{ pratinjau.alasan }}</dd>
                    <dt>Jadwal pengganti</dt>
                    <dd>{{ pratinjau.pengganti }}</dd>
                    <template v-if="pratinjau.pesan">
                        <dt>Pesan tim</dt>
                        <dd>“{{ pratinjau.pesan }}”</dd>
                    </template>
                </dl>
                <small v-if="perbarui && !f.kabari" class="tnd-pelan">Tanpa email — perubahan hanya tampil di Portal Kandidat.</small>
            </section>

            <p v-if="galat" class="tnd-galat" role="alert"><i class="bi bi-exclamation-triangle-fill"></i> {{ galat }}</p>
        </div>
    </AdminModal>
</template>

<style scoped>
.tnd { display: flex; flex-direction: column; gap: 14px; font-size: 13px; color: #0f172a; }
.tnd-pelan { color: #64748b; font-size: 12px; }
.tnd-akibat { margin: 0; display: flex; gap: 8px; align-items: flex-start; padding: 10px 12px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; line-height: 1.5; }
.tnd-akibat > i { color: #6366f1; margin-top: 2px; }
.tnd-bag h4 { margin: 0 0 8px; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; color: #1e293b; }
.tnd-bag h4 small { font-weight: 600; color: #64748b; }
.tnd-no { width: 22px; height: 22px; border-radius: 50%; display: grid; place-items: center; font-size: 11.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg, #a78bfa, #7c3aed); flex: none; }
.tnd-opsi { display: flex; flex-wrap: wrap; gap: 7px; }
.tnd-chip { padding: 7px 12px; border-radius: 999px; border: 1.5px solid #dbe1f1; background: #fff; font: inherit; font-size: 12.5px; font-weight: 700; color: #334155; cursor: pointer; }
.tnd-chip:hover { border-color: #c4b5fd; }
.tnd-chip.on { border-color: #7c3aed; background: #f5f3ff; color: #5b21b6; }
.tnd-pilih { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.tnd-pil { display: flex; gap: 10px; align-items: flex-start; text-align: left; padding: 11px 12px; border-radius: 12px; border: 1.5px solid #dbe1f1; background: #fff; font: inherit; cursor: pointer; color: #334155; }
.tnd-pil > i { font-size: 17px; color: #7c3aed; margin-top: 1px; }
.tnd-pil b { display: block; font-size: 13px; color: #1e293b; }
.tnd-pil small { display: block; margin-top: 2px; font-size: 11.5px; color: #64748b; line-height: 1.4; }
.tnd-pil.on { border-color: #7c3aed; background: #faf8ff; box-shadow: 0 0 0 3px rgba(124, 58, 237, .12); }
.tnd-tanggal { margin-top: 9px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.tnd-input { width: 100%; box-sizing: border-box; border: 1.5px solid #dbe1f1; border-radius: 12px; padding: 9px 11px; font: inherit; font-size: 13px; resize: vertical; }
.tnd-input:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124, 58, 237, .14); }
.tnd-centang { display: flex; gap: 9px; align-items: center; font-weight: 700; color: #334155; }
.tnd-centang input { width: 17px; height: 17px; }
.tnd-centang small { font-weight: 600; color: #64748b; }
.tnd-pratinjau { padding: 12px 14px; border-radius: 14px; background: #f5f3ff; border: 1px dashed #c4b5fd; }
.tnd-pratinjau__alis { display: flex; gap: 6px; align-items: center; font-size: 10.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #6d28d9; }
.tnd-pratinjau__judul { display: block; margin-top: 6px; font-size: 14px; color: #1e293b; }
.tnd-pratinjau dl { margin: 8px 0 0; display: grid; grid-template-columns: max-content 1fr; gap: 5px 12px; }
.tnd-pratinjau dt { color: #7c7a99; font-weight: 700; }
.tnd-pratinjau dd { margin: 0; color: #1e293b; min-width: 0; overflow-wrap: anywhere; }
.tnd-galat { margin: 0; display: flex; gap: 6px; align-items: flex-start; color: #b91c1c; font-weight: 700; }
@media (max-width: 640px) {
    .tnd-pilih { grid-template-columns: 1fr; }
    .tnd-pratinjau dl { grid-template-columns: 1fr; gap: 1px; }
    .tnd-pratinjau dd { margin-bottom: 5px; }
}
</style>
