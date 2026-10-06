<!-- WEB CAREER — JENDELA PROSES PERMINTAAN JADWAL LAIN (sisi tim).

     Dulu tiga tindakannya (Setujui usulan · Tawarkan waktu lain · Tolak)
     tersembunyi di dalam panel konfirmasi yang dibuka dari chip — "berbahaya,
     jangan di dropdown" (masukan user 2 Okt 2026). Sekarang tombolnya berdiri
     di baris aksi aktivitas (worklist) / panel Agenda, dan masing-masing
     membuka jendela ini: permintaan kandidat terbaca utuh di atas, lalu satu
     formulir yang jelas akibatnya.

       setujui   pilih usulan + jam mulai (dalam rentang usulan) → versi baru,
                 kandidat langsung "akan hadir" dan jawabannya FINAL (jadwal ini
                 usulannya sendiri — KonfirmasiJadwal::aturanUbah, sebab USULAN)
       tawarkan  waktu baru dari tim → kandidat mengonfirmasi ulang
       tolak     catatan wajib → jadwal semula tetap, kandidat menjawab lagi

     Aturannya ditegakkan server (KonfirmasiJadwal::setujui/tawarkan/tolak). -->
<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useZIndex } from 'element-plus';
import AdminModal from './AdminModal.vue';
import { galatDari, setujuiPermintaan, tawarkanWaktu, tolakPermintaan, urai } from '@utils/career/konfirmasi';

const props = defineProps({
    show: { type: Boolean, default: false },
    /** setujui | tawarkan | tolak */
    mode: { type: String, default: 'setujui' },
    /** KonfirmasiJadwal::bentukPermintaan() — { id, alasan, catatan, usulan: [{ teks, tanggal, mulai, selesai }], slaTeks, slaNada, slaLewat } */
    permintaan: { type: Object, default: null },
    /** { kandidat, aktivitas, waktuTeks } */
    konteks: { type: Object, default: () => ({}) },
});

// berubah: permintaan sudah diproses orang lain (409) — induk memuat ulang.
const emit = defineEmits(['close', 'selesai', 'berubah']);

const JUDUL = {
    setujui: { judul: 'Setujui usulan kandidat', ikon: 'bi-check2-circle', simpan: 'Setujui & kirim jadwal' },
    tawarkan: { judul: 'Tawarkan waktu lain', ikon: 'bi-calendar2-plus', simpan: 'Kirim waktu baru' },
    tolak: { judul: 'Tolak permintaan', ikon: 'bi-x-circle', simpan: 'Tolak permintaan' },
};
const def = computed(() => JUDUL[props.mode] || JUDUL.setujui);
const minta = computed(() => props.permintaan || {});
const usulan = computed(() => minta.value.usulan || []);

const f = reactive({ urutan: 0, jam: '', mulai: '', catatan: '' });
const sibuk = ref(false);
const galat = ref('');

// Dibuka dari dalam drawer — lapisannya harus di atas drawer Element Plus.
const { nextZIndex } = useZIndex();
const lapis = ref(null);

// JADWAL TIDAK BOLEH MUNDUR KE MASA LALU (masukan user 2 Okt 2026). Usulan
// kandidat paling cepat besok saat diajukan, tetapi permintaan yang baru
// diproses beberapa hari kemudian bisa berisi tanggal yang sudah lewat.
// Batasnya sama dengan "Tawarkan waktu lain" & server: paling cepat sejam lagi.
// `kini` berdetak selama jendela terbuka, jadi pilihan ikut padam sendiri.
const SEJAM = 3600 * 1000;
const kini = ref(Date.now());
let detak = null;
const pad = (n) => String(n).padStart(2, '0');

/** Date dari tanggal usulan ('YYYY-MM-DD') + jam ('HH:mm'). */
function waktuUsulan(u, jam) {
    const [y, b, t] = String(u.tanggal).split('-').map(Number);
    const [h, m] = String(jam || '00:00').split(':').map(Number);

    return new Date(y, b - 1, t, h, m);
}
const terlaluDekat = (u, jam) => waktuUsulan(u, jam).getTime() <= kini.value + SEJAM;
/** Jam terakhir yang bisa dipilih pun sudah kurang dari sejam lagi. */
const usulanLewat = (u) => terlaluDekat(u, kurang15(u.selesai));

/** Jam pertama (kelipatan 15 menit dari awal rentang) yang masih boleh. */
function jamPertama(u) {
    if (!u) return '';
    const [h0, m0] = String(u.mulai).split(':').map(Number);
    const [h1, m1] = kurang15(u.selesai).split(':').map(Number);
    for (let t = h0 * 60 + m0; t <= h1 * 60 + m1; t += 15) {
        const jam = `${pad(Math.floor(t / 60))}:${pad(t % 60)}`;
        if (!terlaluDekat(u, jam)) return jam;
    }

    return '';
}

watch(() => props.show, (buka) => {
    clearInterval(detak);
    if (!buka) return;
    lapis.value = nextZIndex();
    kini.value = Date.now();
    detak = setInterval(() => { kini.value = Date.now(); }, 30000);
    galat.value = '';
    // -1 = semua usulan sudah lewat: tidak ada yang terpilih.
    f.urutan = usulan.value.findIndex((u) => !usulanLewat(u));
    f.jam = jamPertama(usulan.value[f.urutan]);
    f.mulai = '';
    f.catatan = '';
}, { immediate: true });
onBeforeUnmount(() => clearInterval(detak));

const usulanDipilih = computed(() => usulan.value[f.urutan] || null);
watch(() => f.urutan, () => { f.jam = jamPertama(usulanDipilih.value); });
const semuaLewat = computed(() => usulan.value.length > 0 && usulan.value.every(usulanLewat));
// Usulan terpilih yang keburu lewat selama jendela terbuka → pilihannya dilepas.
watch(kini, () => {
    if (usulanDipilih.value && usulanLewat(usulanDipilih.value)) f.urutan = -1;
});
/** el-time-select memadamkan jam ≤ min-time — diisi bila usulannya jatuh di hari "sejam lagi". */
const minJam = computed(() => {
    const u = usulanDipilih.value;
    if (!u) return undefined;
    const b = new Date(kini.value + SEJAM);

    return `${b.getFullYear()}-${pad(b.getMonth() + 1)}-${pad(b.getDate())}` === u.tanggal
        ? `${pad(b.getHours())}:${pad(b.getMinutes())}`
        : undefined;
});
const jamTerlaluDekat = computed(() => !!usulanDipilih.value && !!f.jam && terlaluDekat(usulanDipilih.value, f.jam));

/** '12:00' → '11:45' — server menolak jam mulai = akhir rentang usulan. */
function kurang15(hhmm) {
    const [h, m] = String(hhmm || '00:00').split(':').map(Number);
    const t = Math.max(0, h * 60 + m - 15);

    return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
}

/** Hari yang sudah lewat tidak bisa dipilih di kalender. */
function hariLampau(d) {
    const k = new Date();

    return d < new Date(k.getFullYear(), k.getMonth(), k.getDate());
}

const galatIsian = computed(() => {
    if (!props.permintaan?.id) return 'Permintaan tidak ditemukan.';
    if (props.mode === 'setujui') {
        if (semuaLewat.value) return 'Semua jadwal yang diajukan sudah lewat — tawarkan waktu lain, atau tolak permintaan ini.';
        const u = usulanDipilih.value;
        if (!u) return 'Pilih salah satu usulan.';
        if (usulanLewat(u)) return 'Usulan ini sudah lewat — pilih usulan lain.';
        if (!f.jam) return 'Pilih jam mulai.';
        if (f.jam < u.mulai || f.jam >= u.selesai) return `Jam mulai harus di dalam rentang usulan (${u.mulai}–${u.selesai}).`;
        if (terlaluDekat(u, f.jam)) return 'Jam mulai paling cepat satu jam dari sekarang — pilih jam yang lebih lambat.';
    }
    if (props.mode === 'tawarkan') {
        if (!f.mulai) return 'Pilih tanggal & jam waktu baru.';
        const m = urai(f.mulai);
        if (!m || m.getTime() <= kini.value + SEJAM) return 'Waktu baru paling cepat satu jam dari sekarang.';
    }
    if (props.mode === 'tolak' && f.catatan.trim().length < 5) return 'Tulis catatan untuk kandidat (minimal 5 huruf) — kenapa jadwal semula tetap.';

    return '';
});

const subjudul = computed(() => {
    const k = props.konteks || {};
    const dasar = [k.aktivitas, k.kandidat].filter(Boolean).join(' · ');

    return k.waktuTeks ? `${dasar} · jadwal semula ${k.waktuTeks}` : dasar;
});

async function simpan() {
    if (sibuk.value) return;
    kini.value = Date.now();
    if (galatIsian.value) {
        galat.value = galatIsian.value;
        return;
    }
    sibuk.value = true;
    galat.value = '';
    const catatan = f.catatan.trim() || null;
    try {
        const id = props.permintaan.id;
        const res = props.mode === 'setujui'
            ? await setujuiPermintaan(id, { urutan: f.urutan, jam: f.jam, catatan })
            : props.mode === 'tawarkan'
                ? await tawarkanWaktu(id, { mulai: f.mulai, catatan })
                : await tolakPermintaan(id, { catatan });
        emit('selesai', res?.message || 'Tersimpan.');
    } catch (e) {
        const g = galatDari(e, 'Permintaan gagal diproses.');
        galat.value = g.pesan;
        if (g.status === 409) emit('berubah');
    } finally {
        sibuk.value = false;
    }
}
</script>

<template>
    <AdminModal
        :show="show"
        :title="def.judul"
        :subtitle="subjudul"
        :icon="def.ikon"
        size="lg"
        :save-label="def.simpan"
        :save-disabled="!!galatIsian"
        :busy="sibuk"
        busy-label="Menyimpan…"
        :foot-note="mode === 'tolak' ? 'Kandidat menerima catatanmu lewat email.' : 'Kandidat menerima jadwal barunya lewat email.'"
        :z-index="lapis"
        @close="emit('close')"
        @save="simpan"
    >
        <div class="pmd">
            <!-- PERMINTAAN KANDIDAT — dibaca utuh sebelum memutuskan. -->
            <section class="pmd-minta" aria-label="Permintaan kandidat">
                <div class="pmd-minta__kepala">
                    <b><i class="bi bi-arrow-repeat"></i> Permintaan kandidat</b>
                    <span v-if="minta.slaTeks" class="pmd-sla" :class="`is-${minta.slaNada || 'hijau'}`">
                        <i class="bi bi-stopwatch"></i> {{ minta.slaLewat ? 'Tenggat tim lewat' : 'Tenggat tim' }} {{ minta.slaTeks }}
                    </span>
                </div>
                <dl>
                    <div><dt>Alasan</dt><dd>{{ minta.alasan || '—' }}</dd></div>
                    <div v-if="minta.catatan"><dt>Catatan kandidat</dt><dd class="pmd-kutip">“{{ minta.catatan }}”</dd></div>
                    <div>
                        <dt>Jadwal yang diajukan</dt>
                        <dd>
                            <ol class="pmd-usulan">
                                <li v-for="(u, i) in usulan" :key="i">{{ u.teks }}</li>
                            </ol>
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- ═══ SETUJUI ═══ -->
            <template v-if="mode === 'setujui'">
                <section class="pmd-bag">
                    <h4><span class="pmd-no">1</span> Usulan yang disetujui</h4>
                    <div class="pmd-pilih" role="radiogroup" aria-label="Usulan yang disetujui">
                        <button
                            v-for="(u, i) in usulan"
                            :key="i"
                            type="button"
                            role="radio"
                            class="pmd-pil"
                            :class="{ on: f.urutan === i }"
                            :aria-checked="String(f.urutan === i)"
                            :disabled="usulanLewat(u)"
                            @click="f.urutan = i"
                        >
                            <i class="bi" :class="f.urutan === i ? 'bi-record-circle-fill' : 'bi-circle'"></i>
                            <span>{{ u.teks }}</span>
                            <small v-if="usulanLewat(u)" class="pmd-lewat">sudah lewat</small>
                        </button>
                    </div>
                    <p v-if="semuaLewat" class="pmd-akibat is-merah pmd-akibat--atas">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Semua jadwal yang diajukan kandidat <b>sudah lewat</b>. Tutup jendela ini, lalu pilih <b>Tawarkan waktu lain</b> atau <b>Tolak</b>.</span>
                    </p>
                </section>
                <!-- Semua usulan lewat: tak ada yang bisa disetujui, jadi isian di
                     bawahnya ikut disembunyikan — yang tersisa hanya arahannya. -->
                <template v-if="!semuaLewat">
                <section class="pmd-bag">
                    <h4><span class="pmd-no">2</span> Jam mulai</h4>
                    <div class="pmd-jam">
                        <el-time-select
                            v-if="usulanDipilih"
                            v-model="f.jam"
                            :start="usulanDipilih.mulai"
                            :end="kurang15(usulanDipilih.selesai)"
                            :min-time="minJam"
                            step="00:15"
                            placeholder="Pilih jam"
                            style="width: 160px"
                        />
                        <span class="pmd-pelan">Durasi mengikuti jadwal semula; tempat & tautan tetap.</span>
                    </div>
                    <p v-if="jamTerlaluDekat" class="pmd-galat pmd-galat--isian">
                        <i class="bi bi-clock-history"></i> Jam itu sudah kurang dari sejam lagi — pilih jam yang lebih lambat.
                    </p>
                </section>
                <section class="pmd-bag">
                    <h4><span class="pmd-no">3</span> Catatan untuk kandidat <small>opsional</small></h4>
                    <textarea v-model="f.catatan" rows="2" maxlength="1000" class="pmd-input" placeholder="mis. Sampai jumpa di jadwal barunya."></textarea>
                </section>
                <p class="pmd-akibat is-hijau">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Kandidat langsung tercatat <b>akan hadir</b> dan menerima jadwal barunya. Karena waktunya usulan kandidat sendiri, jawabannya <b>final</b> — ia tidak bisa mengubahnya lagi.</span>
                </p>
                </template>
            </template>

            <!-- ═══ TAWARKAN ═══ -->
            <template v-else-if="mode === 'tawarkan'">
                <section class="pmd-bag">
                    <h4><span class="pmd-no">1</span> Waktu baru</h4>
                    <el-date-picker
                        v-model="f.mulai"
                        type="datetime"
                        format="DD MMM YYYY HH:mm"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        placeholder="Pilih tanggal & jam"
                        style="width: 100%"
                        :disabled-date="hariLampau"
                    />
                </section>
                <section class="pmd-bag">
                    <h4><span class="pmd-no">2</span> Catatan untuk kandidat <small>opsional</small></h4>
                    <textarea v-model="f.catatan" rows="2" maxlength="1000" class="pmd-input" placeholder="mis. Panel penuh di tanggal usulanmu; ini waktu terdekat."></textarea>
                </section>
                <p class="pmd-akibat">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Kandidat diminta mengonfirmasi ulang — bisa dijawab sampai waktu baru ini dimulai.</span>
                </p>
            </template>

            <!-- ═══ TOLAK ═══ -->
            <template v-else>
                <section class="pmd-bag">
                    <h4><span class="pmd-no">1</span> Catatan untuk kandidat <small class="pmd-wajib">wajib</small></h4>
                    <textarea v-model="f.catatan" rows="3" maxlength="1000" class="pmd-input" placeholder="mis. Jadwal panel tidak bisa digeser; mohon hadir di jadwal semula."></textarea>
                </section>
                <p class="pmd-akibat is-merah">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Jadwal semula <b>tetap berlaku</b>. Kandidat diminta menjawab lagi, paling lambat saat jadwal itu dimulai.</span>
                </p>
            </template>

            <p v-if="galat" class="pmd-galat" role="alert"><i class="bi bi-exclamation-triangle-fill"></i> {{ galat }}</p>
        </div>
    </AdminModal>
</template>

<style scoped>
.pmd { display: flex; flex-direction: column; gap: 14px; font-size: 13px; color: #0f172a; }
.pmd-pelan { color: #64748b; font-size: 12px; }

.pmd-minta { padding: 12px 14px; border-radius: 14px; background: #fffdf5; border: 1px solid #fde68a; }
.pmd-minta__kepala { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 6px 10px; }
.pmd-minta__kepala b { display: inline-flex; align-items: center; gap: 7px; color: #92400e; font-size: 13px; }
.pmd-sla { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 800; }
.pmd-sla.is-hijau { background: #dcfce7; color: #15803d; }
.pmd-sla.is-kuning { background: #fef3c7; color: #b45309; }
.pmd-sla.is-merah { background: #fee2e2; color: #b91c1c; }
.pmd-minta dl { margin: 10px 0 0; display: flex; flex-direction: column; gap: 9px; }
.pmd-minta dt { font-size: 11px; font-weight: 800; color: #94a3b8; letter-spacing: .02em; }
.pmd-minta dd { margin: 2px 0 0; color: #1e293b; font-weight: 600; line-height: 1.5; overflow-wrap: anywhere; }
.pmd-kutip { white-space: pre-line; font-weight: 500 !important; font-style: italic; color: #475569 !important; }
.pmd-usulan { margin: 2px 0 0; padding-left: 18px; }
.pmd-usulan li + li { margin-top: 3px; }

.pmd-bag h4 { margin: 0 0 8px; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; color: #1e293b; }
.pmd-bag h4 small { font-weight: 600; color: #64748b; }
.pmd-bag h4 .pmd-wajib { padding: 1px 7px; border-radius: 999px; background: #fef2f2; color: #b91c1c; font-size: 10.5px; font-weight: 800; }
.pmd-no { width: 22px; height: 22px; border-radius: 50%; display: grid; place-items: center; font-size: 11.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg, #818cf8, #4f46e5); flex: none; }
.pmd-pilih { display: flex; flex-direction: column; gap: 7px; }
.pmd-pil { display: flex; gap: 10px; align-items: center; text-align: left; padding: 10px 12px; border-radius: 12px; border: 1.5px solid #dbe1f1; background: #fff; font: inherit; font-weight: 700; cursor: pointer; color: #334155; }
.pmd-pil > i { font-size: 15px; color: #94a3b8; }
.pmd-pil.on { border-color: #16a34a; background: #f0fdf4; color: #14532d; box-shadow: 0 0 0 3px rgba(22, 163, 74, .12); }
.pmd-pil.on > i { color: #16a34a; }
.pmd-pil:disabled { cursor: not-allowed; background: #f8fafc; color: #94a3b8; border-style: dashed; box-shadow: none; }
.pmd-pil:disabled > span { text-decoration: line-through; text-decoration-color: #cbd5e1; }
.pmd-lewat { margin-left: auto; flex: none; padding: 1px 8px; border-radius: 999px; background: #f1f5f9; color: #64748b; font-size: 10.5px; font-weight: 800; }
.pmd-jam { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
.pmd-input { width: 100%; box-sizing: border-box; border: 1.5px solid #dbe1f1; border-radius: 12px; padding: 9px 11px; font: inherit; font-size: 13px; resize: vertical; }
.pmd-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, .14); }
.pmd-akibat { margin: 0; display: flex; gap: 8px; align-items: flex-start; padding: 10px 12px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; line-height: 1.5; }
.pmd-akibat > i { margin-top: 2px; color: #6366f1; }
.pmd-akibat.is-hijau { background: #f0fdf4; border-color: #bbf7d0; color: #14532d; }
.pmd-akibat.is-hijau > i { color: #16a34a; }
.pmd-akibat.is-merah { background: #fef2f2; border-color: #fecaca; color: #7f1d1d; }
.pmd-akibat.is-merah > i { color: #dc2626; }
.pmd-akibat--atas { margin-top: 8px; }
.pmd-galat { margin: 0; display: flex; gap: 6px; align-items: flex-start; color: #b91c1c; font-weight: 700; }
.pmd-galat--isian { margin-top: 6px; font-size: 12px; }
</style>
