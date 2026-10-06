<!-- WEB CAREER — PANEL KONFIRMASI KEHADIRAN (sisi tim).

     Satu panel untuk dua tempat: drawer worklist (per aktivitas berjadwal) dan
     Agenda Seleksi. Isinya: status jawaban kandidat atas versi jadwal ini,
     batasnya (= waktu mulai jadwal) & hitungan surel (snapshot CRM),
     permintaan jadwal lain beserta tenggat tim, lalu tindakan — pengingat
     manual, catat jawaban atas nama kandidat, proses permintaan, tunda
     jadwal — dan riwayat. Menggeser batas = menggeser jadwal (Ubah Jadwal).

     PERMINTAAN JADWAL LAIN (Setujui usulan · Tawarkan waktu lain · Tolak)
     diproses di jendela tersendiri (PermintaanDialog). Di worklist tombolnya
     berdiri di baris aksi aktivitas, bukan di panel ini — "jangan di dropdown,
     berbahaya" (masukan user 2 Okt 2026); Agenda Seleksi tetap di panel.

     TUNDA dibuka di jendela tersendiri (TundaDialog) — dulu formulirnya
     menumpuk di panel ini dan membingungkan. Aktivitas yang DITUNDA
     menampilkan alasan, perkiraan jadwal pengganti, dan pesan tim, berikut
     dua langkah lanjutannya: Atur jadwal pengganti, Perbarui info.

     Semua aturan ditegakkan server (KonfirmasiJadwal.php). Panel memuat
     rinciannya sendiri; `ringkas` hanya isian awal sebelum rincian tiba. -->
<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import TundaDialog from './TundaDialog.vue';
import PermintaanDialog from './PermintaanDialog.vue';
import {
    KANAL_TIM,
    catatJawaban,
    galatDari,
    gayaStatus,
    kirimPengingat,
    muatDetail,
    sejak,
    sisaWaktu,
    urai,
} from '@utils/career/konfirmasi';

const props = defineProps({
    idAktivitas: { type: String, required: true },
    ringkas: { type: Object, default: null },
    bolehUbah: { type: Boolean, default: false },
    /** Tanpa kepala & bingkai — dipakai di dalam drawer yang sudah berjudul. */
    polos: { type: Boolean, default: false },
    /**
     * Tombol atas JADWAL (Tunda jadwal, Atur jadwal pengganti, Perbarui info,
     * Setujui usulan / Tawarkan waktu lain / Tolak). Worklist mematikannya: di
     * sana tombol-tombol itu berdiri di baris aksi aktivitas, sejajar Ubah
     * Jadwal (masukan user 1 & 2 Okt 2026). Agenda Seleksi tidak punya baris
     * itu, jadi panel yang menampungnya.
     */
    aksiJadwal: { type: Boolean, default: true },
});

// atur-jadwal: aktivitas DITUNDA → induk membuka jendela Atur Jadwal-nya.
const emit = defineEmits(['berubah', 'pesan', 'atur-jadwal']);

const detail = ref(null);
const memuat = ref(false);
const galatMuat = ref('');
const sibuk = ref(false);
const mode = ref(null); // null | ingat | catat
const riwayatBuka = ref(false);
const galatForm = ref('');

const k = computed(() => detail.value?.konfirmasi || props.ringkas || null);
const gaya = computed(() => gayaStatus(k.value?.status, k.value));
const minta = computed(() => k.value?.permintaan || null);
const opsi = computed(() => detail.value?.opsi || { pilihan: [], alasan: { JADWAL_LAIN: [], MUNDUR: [], TUNDA: [] }, bagianHari: [], tunda: {} });
const terbuka = computed(() => detail.value ? !!detail.value.terbuka : true);
const bisaUbah = computed(() => props.bolehUbah && terbuka.value && !!k.value);
const sekarang = ref(new Date());

/** Aktivitas DITUNDA: jadwalnya kosong, info penundaannya dari jejak. */
const ditunda = computed(() => k.value?.status === 'DITUNDA');
const tunda = computed(() => k.value?.tunda || null);

/**
 * KOMITMEN kandidat (sekali ubah, paling lambat H-N) dalam bahasa tim —
 * supaya jelas kapan perubahan hanya bisa lewat "Catat jawaban".
 */
const teksUbahTim = computed(() => {
    const u = detail.value?.ubah;
    if (!u || ditunda.value || !k.value) return '';
    const kali = u.maks === 1 ? '1×' : `${u.maks}×`;
    if (k.value.status === 'MENUNGGU') {
        return u.sebab === 'WAKTU' || u.sebab === 'SEKALI'
            ? 'Begitu dijawab, jawaban kandidat langsung final.'
            : `Sesudah menjawab, kandidat boleh mengubah ${kali} sampai ${u.batasTeks}.`;
    }
    if (!['AKAN_HADIR', 'JADWAL_LAIN'].includes(k.value.status)) return '';

    if (u.sebab === 'USULAN') return 'Jadwal ini usulan kandidat sendiri yang disetujui — jawabannya final; perubahan hanya lewat "Catat jawaban".';

    return u.terkunci
        ? 'Jawaban kandidat sudah final — perubahan hanya lewat "Catat jawaban".'
        : `Kandidat masih bisa mengubah jawaban ${u.sisa}× sampai ${u.batasTeks}.`;
});

async function muat() {
    memuat.value = true;
    galatMuat.value = '';
    try {
        detail.value = await muatDetail(props.idAktivitas);
    } catch (e) {
        galatMuat.value = galatDari(e, 'Rincian konfirmasi gagal dimuat.').pesan;
    } finally {
        memuat.value = false;
        sekarang.value = new Date();
    }
}

onMounted(muat);
watch(() => props.idAktivitas, () => {
    mode.value = null;
    detail.value = null;
    muat();
});

function buka(m) {
    galatForm.value = '';
    mode.value = mode.value === m ? null : m;
    if (mode.value === 'catat') siapkanCatat();
}

async function jalankan(aksi, pesanCadangan) {
    if (sibuk.value) return;
    sibuk.value = true;
    galatForm.value = '';
    try {
        const res = await aksi();
        const gagal = res?.result?.gagal || [];
        const rinci = gagal.map((g) => g.alasan).filter(Boolean).join(' · ');
        emit('pesan', [res?.message || 'Tersimpan.', rinci].filter(Boolean).join(' — '), gagal.length > 0 && !(res?.result?.berhasil || []).length);
        if (gagal.length && !(res?.result?.berhasil || []).length) {
            galatForm.value = rinci || res?.message;
            return;
        }
        mode.value = null;
        await muat();
        emit('berubah');
    } catch (e) {
        const g = galatDari(e, pesanCadangan);
        galatForm.value = g.pesan;
        if (g.status === 409) await muat();
    } finally {
        sibuk.value = false;
    }
}

// ── PENGINGAT MANUAL ────────────────────────────────────────────────────────

const baruDikirim = computed(() => {
    const t = urai(k.value?.terakhirKirimAt);
    const jam = Number(opsi.value.jarakSopanJam || 16);

    return t ? (sekarang.value.getTime() - t.getTime()) < jam * 3600 * 1000 : false;
});

function kirimIngat() {
    jalankan(() => kirimPengingat([props.idAktivitas]), 'Pengingat gagal dikirim.');
}

// ── CATAT JAWABAN ATAS NAMA KANDIDAT ────────────────────────────────────────

const fCatat = reactive({ kode: '', kanal: 'TELEPON', catatan: '', alasan: '', usulan: [{ tanggal: '', bagian: '' }] });
const defCatat = computed(() => opsi.value.pilihan.find((p) => p.kode === fCatat.kode) || null);

/** Usulan waktu mulai BESOK — sama dengan formulir kandidat & periksaUsulan di server. */
function sebelumBesok(d) {
    const k = new Date();

    return d < new Date(k.getFullYear(), k.getMonth(), k.getDate() + 1);
}

function siapkanCatat() {
    fCatat.kode = '';
    fCatat.kanal = 'TELEPON';
    fCatat.catatan = '';
    fCatat.alasan = '';
    fCatat.usulan = [{ tanggal: '', bagian: '' }];
}

const siapCatat = computed(() => {
    if (!fCatat.kode || !fCatat.kanal || fCatat.catatan.trim().length < 5) return false;
    if (defCatat.value?.bukaPermintaan) {
        if (!fCatat.alasan) return false;
        if (!fCatat.usulan.some((u) => u.tanggal && u.bagian)) return false;
    }

    return true;
});

function kirimCatat() {
    if (!siapCatat.value) return;
    jalankan(() => catatJawaban(props.idAktivitas, {
        kode: fCatat.kode,
        kanal: fCatat.kanal,
        catatan: fCatat.catatan.trim(),
        alasan: fCatat.alasan || null,
        usulan: defCatat.value?.bukaPermintaan ? fCatat.usulan.filter((u) => u.tanggal && u.bagian) : [],
        statusDilihat: k.value?.status || null,
    }), 'Jawaban gagal dicatat.');
}

// ── PROSES PERMINTAAN — jendela tersendiri (PermintaanDialog) ───────────────

const mintaDialog = reactive({ show: false, mode: 'setujui' });

function bukaMinta(m) {
    mode.value = null;
    mintaDialog.mode = m;
    mintaDialog.show = true;
}

async function selesaiMinta(pesan) {
    mintaDialog.show = false;
    emit('pesan', pesan, false);
    await muat();
    emit('berubah');
}

// ── TUNDA — jendela tersendiri (TundaDialog) ────────────────────────────────

const tundaBuka = ref(false);
const tundaMode = ref('tunda'); // tunda | perbarui

function bukaTunda(m) {
    mode.value = null;
    tundaMode.value = m;
    tundaBuka.value = true;
}

async function selesaiTunda(pesan) {
    tundaBuka.value = false;
    emit('pesan', pesan, false);
    await muat();
    emit('berubah');
}

// ── TAMPILAN ────────────────────────────────────────────────────────────────

const JENIS_KIRIM = { UNDANGAN: 'undangan', PENGINGAT: 'pengingat', KIRIM_ULANG: 'kirim ulang' };
const IKON_RIWAYAT = {
    BUAT: 'bi-calendar-plus', UBAH: 'bi-pencil-square', KIRIM_ULANG: 'bi-envelope-arrow-up', DIBUKA: 'bi-eye',
    JAWAB: 'bi-reply-fill', INGAT_JAWAB: 'bi-bell-fill', PROSES_PERMINTAAN: 'bi-arrow-repeat',
    KABAR_TIM: 'bi-megaphone', KEHADIRAN: 'bi-person-check', TUNDA: 'bi-pause-circle', TUNDA_INFO: 'bi-pencil-square',
    PERPANJANG: 'bi-hourglass-top', PENGINGAT: 'bi-bell',
};
const LABEL_SUREL = { TERKIRIM: 'terkirim', ANTRE: 'diantrekan', GAGAL: 'gagal', LEWAT: 'dilewati', PRIVAT: 'internal' };

const ringkasKirim = computed(() => {
    if (!k.value?.terakhirKirimAt) return '';
    const jenis = JENIS_KIRIM[k.value.terakhirKirimJenis] || (k.value.terakhirKirimJenis || '').toLowerCase();

    return `Terakhir: ${jenis} ${sejak(k.value.terakhirKirimAt, sekarang.value)}${k.value.terakhirKirimOleh ? ` oleh ${k.value.terakhirKirimOleh}` : ''}`;
});

function namaKanal(kode) {
    return KANAL_TIM.find((x) => x.kode === kode)?.label || kode;
}
</script>

<template>
    <section class="kfp" :class="{ 'kfp--polos': polos }" :style="{ '--nada': gaya.warna }">
        <header v-if="!polos" class="kfp-kepala">
            <span class="kfp-kepala__judul"><i class="bi bi-calendar-check"></i> Konfirmasi kehadiran</span>
            <span v-if="k" class="kfp-chip"><i class="bi" :class="gaya.ikon"></i> {{ gaya.label }}</span>
        </header>

        <p v-if="memuat && !k" class="kfp-pelan"><i class="bi bi-arrow-repeat kfp-putar"></i> Memuat…</p>
        <p v-else-if="galatMuat" class="kfp-galat">{{ galatMuat }} <button type="button" class="kfp-link" @click="muat">Coba lagi</button></p>
        <p v-else-if="!k" class="kfp-pelan">Aktivitas ini tidak meminta konfirmasi kehadiran.</p>

        <template v-if="k">
            <div v-if="polos" class="kfp-baris-status">
                <span class="kfp-chip"><i class="bi" :class="gaya.ikon"></i> {{ gaya.label }}</span>
                <small v-if="k.dijawabAt">dijawab {{ sejak(k.dijawabAt, sekarang) }}</small>
            </div>

            <!-- DITUNDA — alasan, perkiraan jadwal pengganti, pesan; dua langkah lanjut. -->
            <div v-if="ditunda" class="kfp-tunda">
                <div class="kfp-tunda__kepala">
                    <b><i class="bi bi-pause-circle-fill"></i> Jadwal ditunda</b>
                    <span class="kfp-tunda__tgl" :class="{ 'is-belum': !tunda?.perkiraanTeks }">
                        <i class="bi bi-calendar-event"></i>
                        {{ tunda?.perkiraanTeks ? `Perkiraan ${tunda.perkiraanTeks}` : 'Pengganti belum ditetapkan' }}
                    </span>
                </div>
                <p v-if="tunda?.alasanTeks || tunda?.alasan"><span class="kfp-pelan">Alasan:</span> {{ tunda.alasanTeks || tunda.alasan }}</p>
                <p v-if="tunda?.pesan && !tunda?.butuhCatatan" class="kfp-kutip">“{{ tunda.pesan }}”</p>
                <p v-if="tunda?.jadwalSemula" class="kfp-pelan">Jadwal semula: <s>{{ tunda.jadwalSemula }}</s></p>
                <p v-if="tunda?.ditundaAt" class="kfp-pelan">
                    Ditunda {{ sejak(tunda.ditundaAt, sekarang) }}<template v-if="tunda.ditundaOleh"> oleh {{ tunda.ditundaOleh }}</template>
                    <template v-if="tunda.diperbaruiAt"> · info diperbarui {{ sejak(tunda.diperbaruiAt, sekarang) }}</template>
                </p>
                <div v-if="bisaUbah && aksiJadwal" class="kfp-aksi">
                    <button type="button" class="kfp-btn kfp-btn--utama" @click="emit('atur-jadwal')">
                        <i class="bi bi-calendar-plus"></i> Atur jadwal pengganti
                    </button>
                    <button type="button" class="kfp-btn" @click="bukaTunda('perbarui')">
                        <i class="bi bi-pencil-square"></i> Perbarui info
                    </button>
                </div>
            </div>

            <ul v-if="!ditunda" class="kfp-meta">
                <li v-if="k.batasTeks" :class="{ 'is-lewat': k.batasLewat }">
                    <i class="bi bi-alarm"></i>
                    <template v-if="!k.batasLewat">Bisa dijawab sampai jadwal mulai, <b>{{ k.batasTeks }}</b><span v-if="k.status === 'MENUNGGU'"> · {{ sisaWaktu(k.batas, sekarang) }}</span></template>
                    <template v-else>Jadwal sudah dimulai <b>{{ k.batasTeks }}</b> — catat kehadirannya.</template>
                </li>
                <li v-if="k.undanganAt"><i class="bi bi-envelope"></i> Undangan {{ sejak(k.undanganAt, sekarang) }}</li>
                <li>
                    <i class="bi bi-bell"></i> Pengingat <b>{{ k.jumlahPengingat || 0 }}×</b>
                    · kirim manual <b>{{ k.jumlahKirimManual || 0 }}×</b>
                    <span v-if="k.jumlahGagal" class="kfp-merah"> · gagal {{ k.jumlahGagal }}×</span>
                </li>
                <li v-if="ringkasKirim" class="kfp-pelan"><i class="bi bi-clock-history"></i> {{ ringkasKirim }}</li>
                <li v-if="teksUbahTim" class="kfp-ubah"><i class="bi bi-shield-check"></i> {{ teksUbahTim }}</li>
            </ul>

            <!-- Permintaan jadwal lain -->
            <div v-if="minta" class="kfp-minta">
                <div class="kfp-minta__kepala">
                    <b><i class="bi bi-arrow-repeat"></i> Minta jadwal lain</b>
                    <span class="kfp-sla" :class="`is-${minta.slaNada}`">
                        <i class="bi bi-stopwatch"></i> {{ minta.slaLewat ? 'Tenggat tim lewat' : 'Tenggat tim' }} {{ minta.slaTeks }}
                    </span>
                </div>
                <p><span class="kfp-pelan">Alasan:</span> {{ minta.alasan }}</p>
                <p v-if="minta.catatan" class="kfp-kutip">“{{ minta.catatan }}”</p>
                <p class="kfp-pelan">Jadwal yang diajukan:</p>
                <ol class="kfp-usulan">
                    <li v-for="(u, i) in minta.usulan" :key="i">{{ u.teks }}</li>
                </ol>
                <div v-if="bisaUbah && aksiJadwal" class="kfp-aksi">
                    <button type="button" class="kfp-btn kfp-btn--hijau" @click="bukaMinta('setujui')"><i class="bi bi-check2-circle"></i> Setujui usulan</button>
                    <button type="button" class="kfp-btn" @click="bukaMinta('tawarkan')"><i class="bi bi-calendar2-plus"></i> Tawarkan waktu lain</button>
                    <button type="button" class="kfp-btn kfp-btn--merah" @click="bukaMinta('tolak')"><i class="bi bi-x-circle"></i> Tolak</button>
                </div>
                <p v-else-if="bisaUbah" class="kfp-petunjuk">
                    <i class="bi bi-hand-index"></i> Proses lewat tombol di baris aktivitas: <b>Setujui usulan</b> · <b>Tawarkan waktu lain</b> · <b>Tolak</b>.
                </p>
            </div>

            <!-- Tindakan -->
            <div v-if="bisaUbah && !ditunda" class="kfp-aksi">
                <button
                    v-if="k.bolehPengingat"
                    type="button"
                    class="kfp-btn"
                    :class="{ on: mode === 'ingat' }"
                    @click="buka('ingat')"
                ><i class="bi bi-bell-fill"></i> Kirim pengingat</button>
                <button type="button" class="kfp-btn" :class="{ on: mode === 'catat' }" @click="buka('catat')">
                    <i class="bi bi-telephone-inbound"></i> Catat jawaban
                </button>
                <button v-if="aksiJadwal" type="button" class="kfp-btn kfp-btn--abu" @click="bukaTunda('tunda')">
                    <i class="bi bi-pause-circle"></i> Tunda jadwal…
                </button>
            </div>

            <!-- ═══ FORMULIR ═══ -->
            <div v-if="mode" class="kfp-form">
                <!-- Pengingat -->
                <template v-if="mode === 'ingat'">
                    <p>Kirim email <b>“mohon segera konfirmasi”</b> ke kandidat sekarang? Tercatat sebagai pengingat manual atas nama Anda.</p>
                    <p v-if="baruDikirim" class="kfp-catat is-kuning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Kandidat baru menerima email {{ sejak(k.terakhirKirimAt, sekarang) }}. Satu email sehari sudah cukup — pertimbangkan menelepon.
                    </p>
                    <div class="kfp-form__aksi">
                        <button type="button" class="kfp-btn kfp-btn--teks" @click="mode = null">Batal</button>
                        <button type="button" class="kfp-btn kfp-btn--utama" :disabled="sibuk" @click="kirimIngat"><i class="bi bi-send-fill"></i> Kirim pengingat</button>
                    </div>
                </template>

                <!-- Catat jawaban -->
                <template v-if="mode === 'catat'">
                    <label class="kfp-label">Jawaban kandidat</label>
                    <div class="kfp-opsi-grup">
                        <button v-for="p in opsi.pilihan" :key="p.kode" type="button" class="kfp-opsi" :class="{ on: fCatat.kode === p.kode }" @click="fCatat.kode = p.kode">{{ p.label }}</button>
                    </div>
                    <label class="kfp-label">Lewat</label>
                    <div class="kfp-opsi-grup">
                        <button v-for="c in KANAL_TIM" :key="c.kode" type="button" class="kfp-opsi" :class="{ on: fCatat.kanal === c.kode }" @click="fCatat.kanal = c.kode"><i class="bi" :class="c.ikon"></i> {{ c.label }}</button>
                    </div>
                    <template v-if="defCatat?.bukaPermintaan">
                        <label class="kfp-label">Alasan</label>
                        <el-select v-model="fCatat.alasan" placeholder="Pilih alasan" style="width: 100%">
                            <el-option v-for="a in opsi.alasan.JADWAL_LAIN" :key="a.kode" :value="a.kode" :label="a.nama" />
                        </el-select>
                        <label class="kfp-label">Jadwal yang diajukan</label>
                        <div v-for="(u, i) in fCatat.usulan" :key="i" class="kfp-usulan-baris">
                            <el-date-picker v-model="u.tanggal" type="date" format="DD MMM YYYY" value-format="YYYY-MM-DD" placeholder="Tanggal" style="width: 150px" :disabled-date="sebelumBesok" />
                            <el-select v-model="u.bagian" placeholder="Bagian hari" style="width: 160px">
                                <el-option v-for="b in opsi.bagianHari" :key="b.kode" :value="b.kode" :label="`${b.label} (${b.mulai}–${b.selesai})`" />
                            </el-select>
                            <button v-if="fCatat.usulan.length > 1" type="button" class="kfp-ikon" aria-label="Hapus" @click="fCatat.usulan.splice(i, 1)"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <button v-if="fCatat.usulan.length < 3" type="button" class="kfp-link" @click="fCatat.usulan.push({ tanggal: '', bagian: '' })"><i class="bi bi-plus"></i> Tambah waktu</button>
                    </template>
                    <template v-if="defCatat?.sinyalMundur">
                        <label class="kfp-label">Alasan <small>opsional</small></label>
                        <el-select v-model="fCatat.alasan" clearable placeholder="Pilih alasan" style="width: 100%">
                            <el-option v-for="a in opsi.alasan.MUNDUR" :key="a.kode" :value="a.kode" :label="a.nama" />
                        </el-select>
                        <p class="kfp-catat is-kuning">
                            <i class="bi bi-info-circle-fill"></i>
                            Aktivitas ini langsung ditandai <b>Tidak Hadir</b> — sama seperti tombolnya, dengan catatan percakapan ini sebagai alasannya.
                            Lamarannya tetap ditutup lewat tombol keputusan <b>Mengundurkan Diri</b>.
                        </p>
                    </template>
                    <label class="kfp-label">Catatan percakapan <b>*</b></label>
                    <textarea v-model="fCatat.catatan" rows="3" maxlength="1000" class="kfp-input" placeholder="mis. Ditelepon 10.15 — kandidat memastikan hadir."></textarea>
                    <div class="kfp-form__aksi">
                        <button type="button" class="kfp-btn kfp-btn--teks" @click="mode = null">Batal</button>
                        <button type="button" class="kfp-btn kfp-btn--utama" :disabled="sibuk || !siapCatat" @click="kirimCatat"><i class="bi bi-check2"></i> Simpan jawaban</button>
                    </div>
                </template>

                <p v-if="galatForm" class="kfp-galat" role="alert">{{ galatForm }}</p>
            </div>

            <!-- Riwayat -->
            <div v-if="detail?.riwayat?.length" class="kfp-riwayat">
                <button type="button" class="kfp-link" :aria-expanded="String(riwayatBuka)" @click="riwayatBuka = !riwayatBuka">
                    <i class="bi" :class="riwayatBuka ? 'bi-chevron-down' : 'bi-chevron-right'"></i> Riwayat ({{ detail.riwayat.length }})
                </button>
                <ol v-if="riwayatBuka" class="kfp-lini">
                    <li v-for="r in detail.riwayat" :key="r.id">
                        <span class="kfp-lini__ic"><i class="bi" :class="IKON_RIWAYAT[r.aksi] || 'bi-dot'"></i></span>
                        <div>
                            <b>{{ r.label }}</b>
                            <span v-if="r.ke"> → {{ r.ke }}</span>
                            <span v-if="r.versi !== null" class="kfp-versi">v{{ r.versi }}</span>
                            <small class="kfp-pelan">
                                {{ sejak(r.at, sekarang) }} · {{ r.pelaku === 'KANDIDAT' ? 'kandidat' : (r.oleh || 'sistem') }}<template v-if="r.kanal && r.kanal !== 'SISTEM'"> · {{ namaKanal(r.kanal) }}</template>
                                <template v-if="r.surel"> · email {{ LABEL_SUREL[r.surel] || r.surel }}</template>
                            </small>
                            <small v-if="r.alasanNama" class="kfp-pelan">Alasan: {{ r.alasanNama }}</small>
                            <small v-if="r.alasan || r.catatan" class="kfp-kutip-kecil">{{ r.alasan || r.catatan }}</small>
                            <small v-if="r.tunda" class="kfp-pelan">Pengganti: {{ r.perkiraanTeks ? `perkiraan ${r.perkiraanTeks}` : 'akan dikabarkan' }}</small>
                            <small v-if="r.galat" class="kfp-merah">{{ r.galat }}</small>
                        </div>
                    </li>
                </ol>
            </div>
        </template>

        <PermintaanDialog
            v-if="minta"
            :show="mintaDialog.show"
            :mode="mintaDialog.mode"
            :permintaan="minta"
            :konteks="{ kandidat: detail?.kandidat, aktivitas: detail?.aktivitas, waktuTeks: detail?.waktuTeks }"
            @close="mintaDialog.show = false"
            @selesai="selesaiMinta"
            @berubah="muat(); emit('berubah')"
        />
        <TundaDialog
            :show="tundaBuka"
            :id-aktivitas="idAktivitas"
            :mode="tundaMode"
            :alasan="opsi.alasan?.TUNDA || []"
            :batas-tanggal="opsi.tunda || {}"
            :konteks="{ kandidat: detail?.kandidat, aktivitas: detail?.aktivitas, waktuTeks: detail?.waktuTeks }"
            :awal="tundaMode === 'perbarui' ? tunda : null"
            @close="tundaBuka = false"
            @selesai="selesaiTunda"
        />
    </section>
</template>

<style scoped>
.kfp {
    border: 1px solid #e7e3fb;
    border-left: 4px solid var(--nada, #94a3b8);
    border-radius: 14px;
    background: #fff;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    font-size: 0.85rem;
    color: #0f172a;
}
.kfp--polos {
    border: none;
    border-left: none;
    padding: 0;
    background: transparent;
}
.kfp-kepala {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}
.kfp-kepala__judul {
    font-weight: 800;
    display: inline-flex;
    gap: 6px;
    align-items: center;
    color: #312e81;
}
.kfp-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 800;
    color: var(--nada, #64748b);
    background: color-mix(in srgb, var(--nada, #94a3b8) 12%, #fff);
    border: 1px solid color-mix(in srgb, var(--nada, #94a3b8) 35%, #fff);
}
.kfp-baris-status {
    display: flex;
    align-items: center;
    gap: 8px;
}
.kfp-meta {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.kfp-meta li {
    display: flex;
    gap: 6px;
    align-items: baseline;
    flex-wrap: wrap;
}
.kfp-meta i {
    color: #6366f1;
}
.kfp-meta li.is-lewat,
.kfp-meta li.is-lewat i {
    color: #c2410c;
}
.kfp-pelan {
    color: #64748b;
}
.kfp-merah {
    color: #b91c1c;
    font-weight: 700;
}
.kfp-galat {
    color: #b91c1c;
    font-weight: 700;
    margin: 6px 0 0;
}
.kfp-minta {
    border: 1px solid #fde68a;
    background: #fffbeb;
    border-radius: 12px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.kfp-minta p {
    margin: 0;
}
.kfp-minta__kepala {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    color: #92400e;
}
.kfp-sla {
    display: inline-flex;
    gap: 4px;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 800;
}
.kfp-sla.is-hijau {
    background: #dcfce7;
    color: #166534;
}
.kfp-sla.is-kuning {
    background: #fef3c7;
    color: #92400e;
}
.kfp-sla.is-merah {
    background: #fee2e2;
    color: #991b1b;
}
.kfp-kutip {
    font-style: italic;
    color: #334155;
}
.kfp-usulan {
    margin: 0;
    padding-left: 18px;
}
/* Worklist: tombol permintaan ada di baris aksi aktivitas — panel cukup menunjuk ke sana. */
.kfp-petunjuk {
    margin: 10px 0 0;
    display: flex;
    gap: 7px;
    align-items: flex-start;
    padding: 8px 10px;
    border-radius: 10px;
    background: #fff;
    border: 1px dashed #fcd34d;
    font-size: 12px;
    line-height: 1.5;
    color: #92400e;
}
.kfp-ubah,
.kfp-ubah i {
    color: #5b21b6 !important;
}
.kfp-tunda {
    border: 1px solid #ddd6fe;
    background: #f5f3ff;
    border-radius: 12px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.kfp-tunda p {
    margin: 0;
}
.kfp-tunda__kepala {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    color: #5b21b6;
}
.kfp-tunda__tgl {
    display: inline-flex;
    gap: 5px;
    align-items: center;
    padding: 2px 9px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 800;
    background: #ede9fe;
    color: #5b21b6;
}
.kfp-tunda__tgl.is-belum {
    background: #fff;
    color: #64748b;
    border: 1px dashed #c4b5fd;
}
.kfp-aksi {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.kfp-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    border-radius: 10px;
    border: 1px solid #c7d2fe;
    background: #fff;
    color: #3730a3;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
}
.kfp-btn:hover,
.kfp-btn.on {
    background: #eef2ff;
    border-color: #818cf8;
}
.kfp-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.kfp-btn--utama {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #fff;
}
.kfp-btn--utama:hover {
    background: #4338ca;
    border-color: #4338ca;
}
.kfp-btn--hijau {
    color: #166534;
    border-color: #bbf7d0;
}
.kfp-btn--merah {
    color: #b91c1c;
    border-color: #fecaca;
}
.kfp-btn--abu {
    color: #475569;
    border-color: #e2e8f0;
}
.kfp-btn--teks {
    border-color: transparent;
    background: transparent;
    color: #475569;
}
.kfp-form {
    border: 1px solid #e0e7ff;
    background: #f8f8ff;
    border-radius: 12px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.kfp-form p {
    margin: 0;
}
.kfp-form__aksi {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
    margin-top: 6px;
    flex-wrap: wrap;
}
.kfp-label {
    font-weight: 800;
    font-size: 0.78rem;
    margin-top: 4px;
}
.kfp-label small {
    font-weight: 600;
    color: #64748b;
}
.kfp-input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #dbe1f1;
    border-radius: 10px;
    padding: 8px 10px;
    font: inherit;
    resize: vertical;
}
.kfp-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.kfp-opsi-grup {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.kfp-opsi-grup--kolom {
    flex-direction: column;
    align-items: stretch;
}
.kfp-opsi {
    padding: 5px 10px;
    border-radius: 999px;
    border: 1px solid #dbe1f1;
    background: #fff;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 700;
    color: #334155;
    cursor: pointer;
    text-align: left;
}
.kfp-opsi.on {
    border-color: #6366f1;
    background: #eef2ff;
    color: #3730a3;
}
.kfp-usulan-baris {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.kfp-ikon {
    border: none;
    background: #f1f5f9;
    border-radius: 8px;
    width: 28px;
    height: 28px;
    cursor: pointer;
}
.kfp-link {
    border: none;
    background: none;
    color: #4f46e5;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
    padding: 0;
    display: inline-flex;
    gap: 4px;
    align-items: center;
}
.kfp-catat {
    display: flex;
    gap: 6px;
    align-items: flex-start;
    padding: 7px 9px;
    border-radius: 10px;
    font-size: 0.8rem;
}
.kfp-catat.is-kuning {
    background: #fffbeb;
    color: #92400e;
}
.kfp-lini {
    list-style: none;
    margin: 6px 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.kfp-lini li {
    display: flex;
    gap: 8px;
}
.kfp-lini li > div {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
}
.kfp-lini__ic {
    flex: none;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 0.75rem;
}
.kfp-versi {
    margin-left: 4px;
    font-size: 0.7rem;
    font-weight: 800;
    color: #6366f1;
    background: #eef2ff;
    border-radius: 6px;
    padding: 0 5px;
}
.kfp-kutip-kecil {
    color: #334155;
    font-style: italic;
}
.kfp-putar {
    display: inline-block;
    animation: kfp-putar 1s linear infinite;
}
@keyframes kfp-putar {
    to {
        transform: rotate(360deg);
    }
}
</style>
