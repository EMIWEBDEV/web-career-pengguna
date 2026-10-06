<!--
  Master MPP — JENDELA KANDIDAT PENGISI KURSI.

  == BENTUKNYA BUKAN DIRANCANG, TAPI DISALIN ================================

  Seluruh markup dan kelasnya — plw-hero, plw-drawer__*, plw-alur__*,
  plw-dok__* — diambil dari jendela kandidat di Pelamar.vue. Orang yang sama
  membuka kedua jendela untuk membaca orang yang sama; dua tampilan berbeda
  untuk isi yang sama memaksa mereka menghafal dua layar demi satu pekerjaan.

  Termasuk avatarBg(): warnanya diturunkan dari STATUS LAMARAN, bukan dari
  nama. Kandidat yang lulus berlatar hijau di worklist, dan ia harus hijau di
  sini juga — warna berbeda untuk orang yang sama terbaca sebagai dua
  keadaan yang berbeda.

  Gaya di Pelamar.vue ber-scoped sehingga tidak bisa dipinjam lintas komponen;
  aturannya disalin apa adanya ke blok style di bawah. Kalau hero atau linimasa
  worklist berubah, salinan ini harus ikut.

  == BERKAS TAMPIL, BUKAN SEKADAR TERTAUT ===================================

  Kolom kanan memakai `berkas.tautan` dari LaporanKandidat: URL bertanda tangan
  Laravel yang TIDAK menuntut sesi, jadi bisa langsung dipasang sebagai src
  <img>/<iframe>. Rute /master-mpp/no/berkas/id tetap ada untuk unduhan, tapi
  ia menjawab 302 ke GCS — dan redirect lintas-asal itulah yang membuat
  berkas gagal digambar di layar ketika dipakai sebagai src.

  == TIGA HAL YANG SENGAJA TIDAK ADA ========================================

    1. Tombol aksi — Cetak Berkas, Kirim Ulang Email, keputusan tahap. Halaman
       MPP menjawab "kursi ini diisi siapa"; memutuskan nasib kandidat tetap
       pekerjaan worklist. Tombol yang sama di dua tempat membuat dua orang
       bisa memutuskan hal yang sama tanpa saling tahu.
    2. Rapor Tes — skor & catatan penilai adalah bahan untuk MEMUTUSKAN.
    3. Hitungan "N formulir · N isian" — angka yang tak dipakai untuk apa pun;
       yang dicari orang di sini isinya, bukan cacahnya.

  Progres seleksi TETAP ADA (informasi, bukan alat putus) dan berkas tetap bisa
  diunduh.
-->
<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import axios from 'axios';
import AdminModal from '@career/AdminModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    /** Nomor transaksi MPP — dipakai sebagai pagar kepemilikan di server. */
    noMpp: { type: String, default: '' },
    kandidat: { type: Object, default: null },
});

defineEmits(['tutup']);

const memuat = ref(false);
const galat = ref('');
const data = ref(null);

/* -- KEADAAN PEMBACA BERKAS (nama & perilakunya sama dengan worklist) -- */
const dokLihat = ref(null);
const dokSrc = ref('');
const dokMuat = ref(false);
const dokGagal = ref(false);
let dokTimer = null;

const tglId = (t) => {
    if (!t) return '—';
    const d = new Date(t);

    return Number.isNaN(d.getTime())
        ? t
        : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const inisial = (n) => String(n || '?').trim().split(/\s+/).slice(0, 2)
    .map((x) => x[0])
    .join('')
    .toUpperCase();

/**
 * Warna avatar — SALINAN PERSIS dari Pelamar.vue.
 *
 * Diturunkan dari status lamaran, bukan dari nama: hijau berarti lulus, merah
 * gugur, kuning talent pool. Itu satu-satunya alasan warnanya ada. Menurunkan
 * warna dari nama (seperti versi sebelumnya) menghasilkan avatar berbeda untuk
 * orang yang sama di dua layar, sekaligus membuang keterangan statusnya.
 */
const avatarBg = (status) => {
    if (status === 'GUGUR') return 'linear-gradient(135deg,#f87171,#ef4444)';
    if (status === 'TALENT_POOL') return 'linear-gradient(135deg,#fbbf24,#d97706)';
    if (status === 'LULUS') return 'linear-gradient(135deg,#34d399,#10b981)';

    return 'linear-gradient(135deg,#8b5cf6,#6366f1)';
};

/** Ikon petak berkas — salinan ikonBerkas() worklist, ikon terisi & semua. */
const ikonBerkas = (b) => {
    if (!b) return 'bi-folder2-open';
    if (b.isImage) return 'bi-file-earmark-image-fill';
    const e = String(b.ext || '').toLowerCase();
    if (e === 'pdf') return 'bi-file-earmark-pdf-fill';
    if (['xls', 'xlsx', 'csv'].includes(e)) return 'bi-file-earmark-spreadsheet-fill';
    if (['doc', 'docx'].includes(e)) return 'bi-file-earmark-word-fill';

    return 'bi-file-earmark-fill';
};

/** '1843201' -> '1,8 MB'. Nol dibiarkan kosong: nol di sini berarti tak tercatat. */
const ukuranBerkas = (n) => {
    const b = Number(n || 0);
    if (!b) return '—';
    if (b < 1024) return `${b} B`;
    if (b < 1024 * 1024) return `${Math.round(b / 1024)} KB`;

    return `${(b / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
};

/**
 * Seluruh lembar milik kandidat ini, rata dari semua formulir.
 *
 * Sumbernya `dokumen` tiap formulir, BUKAN isian per pertanyaan: berkas lepas
 * (yang tidak menempel pada satu pertanyaan) hanya muncul di sana, dan
 * memungutnya lewat isian membuat sebagian berkas hilang diam-diam.
 */
const dokSemua = computed(() => {
    const keluar = [];

    (data.value?.formulir || []).forEach((f) => {
        (f.dokumen || []).forEach((d, i) => {
            if (!d?.tautan) return;
            keluar.push({
                id: `${f.pengisianId}::${d.field || d.nama}::${i}`,
                nama: d.label || d.nama,
                file: d.nama || '—',
                ext: String(d.ext || '').toUpperCase(),
                isImage: !!d.isImage,
                isPdf: !!d.isPdf,
                ukuran: Number(d.ukuran || 0),
                waktu: d.waktu || f.waktuKirim || '',
                folderLabel: f.label,
                url: d.tautan,
            });
        });
    });

    return keluar;
});

const dokPdf = computed(() => !!dokLihat.value && (dokLihat.value.isPdf || dokLihat.value.ext === 'PDF'));
const dokBisaTampil = computed(() => !!dokLihat.value && (dokLihat.value.isImage || dokPdf.value));

/**
 * Linimasa tahap — bentuk datanya disamakan dengan alurKandidat() worklist,
 * supaya markup plw-alur__* bisa dipakai apa adanya.
 */
const alurKandidat = computed(() => (data.value?.tahap || []).map((t, i) => {
    const no = Number(t.urutan) || i + 1;
    const gugur = t.hasil === 'GUGUR' || t.status === 'GUGUR';

    let keadaan = 'nanti';
    if (t.selesai) keadaan = gugur ? 'tutup' : 'lewat';
    else if (t.status === 'BERJALAN') keadaan = 'kini';

    const teks = {
        lewat: { tag: 'Selesai', catatan: t.diputusPada ? `Sudah dilewati · ${tglId(t.diputusPada)}` : 'Sudah dilewati' },
        kini: { tag: 'Berlangsung', catatan: 'Sedang berjalan' },
        tutup: { tag: 'Berakhir', catatan: 'Perjalanan berakhir di tahap ini' },
        nanti: { tag: 'Menunggu', catatan: 'Belum dimulai' },
    }[keadaan];

    return {
        kunci: `${no}-${t.label}`,
        nomor: String(no).padStart(2, '0'),
        label: t.label,
        keadaan,
        tag: teks.tag,
        catatan: teks.catatan,
    };
}));

const tahapLewat = computed(() => alurKandidat.value.filter((t) => t.keadaan !== 'nanti').length);

const persenAlur = computed(() => {
    const n = alurKandidat.value.length;
    if (!n) return 0;

    return Math.min(100, Math.round((tahapLewat.value / n) * 100));
});

/**
 * Mulai memuat, dengan batas waktu — sama alasannya dengan worklist: URL
 * bertanda tangan yang sudah mati tidak pernah memicu onerror pada iframe,
 * jadi tanpa penjaga waktu spinnernya berputar selamanya.
 */
function mulaiDokMuat() {
    dokMuat.value = true;
    dokGagal.value = false;
    clearTimeout(dokTimer);
    dokTimer = setTimeout(() => { dokMuat.value = false; }, 10000);
}

function dokSelesai(gagal = false) {
    clearTimeout(dokTimer);
    dokMuat.value = false;
    dokGagal.value = gagal;
}

function lihatDok(i) {
    const b = dokSemua.value[i];
    if (!b) return;

    dokLihat.value = b;
    dokSrc.value = b.url;
    mulaiDokMuat();
}

/** Muat ulang — tautan bertanda tangan bisa kedaluwarsa; cache-buster kecil. */
function dokCoba() {
    if (!dokLihat.value) return;
    const u = dokLihat.value.url;
    dokSrc.value = u + (u.includes('?') ? '&' : '?') + 'r=' + Date.now();
    mulaiDokMuat();
}

/** Petak gambar yang gagal dimuat menyerahkan tempatnya pada ikon cadangan. */
function gagalThumb(e) {
    const img = e?.target;
    if (!img) return;
    img.style.display = 'none';
    img.parentElement?.classList.remove('is-img');
}

/**
 * Lembar mana yang terbuka sendiri — PDF terbaru dulu, seperti worklist.
 * Kolom pratinjau yang menyambut dengan bidang kosong menuntut satu klik
 * sebelum ada apa pun yang terlihat, padahal melihat itulah gunanya kolom ini.
 */
function bukaBerkasAwal() {
    if (!dokSemua.value.length) return;

    const pdf = dokSemua.value.findIndex((b) => b.isPdf || b.ext === 'PDF');
    lihatDok(pdf >= 0 ? pdf : 0);
}

async function muat() {
    if (!props.kandidat?.id || !props.noMpp) return;

    memuat.value = true;
    galat.value = '';
    data.value = null;
    dokLihat.value = null;
    dokSrc.value = '';

    try {
        const r = await axios.get(
            `/api/v1/master-mpp/${encodeURIComponent(props.noMpp)}/kandidat/${props.kandidat.id}`,
        );
        data.value = r.data?.result || null;
        await nextTick();
        bukaBerkasAwal();
    } catch (e) {
        galat.value = e?.response?.data?.message || 'Gagal memuat profil kandidat.';
    } finally {
        memuat.value = false;
    }
}

watch(() => props.show, (v) => {
    if (v) muat();
    else clearTimeout(dokTimer);
});
</script>

<template>
    <AdminModal
        :show="show"
        size="full"
        icon="bi-person-vcard-fill"
        :title="data?.lamaran?.pelamar || kandidat?.nama || 'Profil Kandidat'"
        :subtitle="kandidat?.kode ? `${kandidat.kode} · ${noMpp}` : noMpp"
        foot-note=""
        foot-blok
        @close="$emit('tutup')"
    >
        <!-- HERO — markup & kelasnya salinan dari worklist. -->
        <template v-if="data" #sticky>
            <div class="plw-hero">
                <div class="plw-hero__row">
                    <div class="plw-hero__avatar" :style="{ background: avatarBg(data.lamaran.status) }">
                        {{ inisial(data.lamaran.pelamar) }}
                    </div>
                    <div style="flex: 1; min-width: 0">
                        <div class="plw-hero__tags">
                            <span class="plw-drawer__tag" :class="data.lamaran.kategori === 'MT' ? 'is-mt' : 'is-rek'">
                                {{ data.lamaran.kategori === 'MT' ? 'MT' : 'Rekrutmen' }}
                            </span>
                            <span
                                class="plw-card__chip"
                                :class="{
                                    'tone-lolos': data.lamaran.status === 'LULUS',
                                    'tone-gugur': data.lamaran.status === 'GUGUR',
                                    'tone-talent': data.lamaran.status === 'TALENT_POOL',
                                }"
                                style="margin-top: 0"
                            >{{ data.lamaran.status }}</span>
                        </div>
                        <div class="plw-hero__name">{{ data.lamaran.pelamar }}</div>
                        <div class="plw-drawer__meta">
                            {{ data.lamaran.posisi }} · <span class="plw-mono">{{ data.lamaran.kode }}</span>
                        </div>
                        <div class="plw-drawer__chips">
                            <span v-if="data.lamaran.departemen"><i class="bi bi-diagram-3"></i> {{ data.lamaran.departemen }}</span>
                            <span v-if="data.lamaran.lokasi"><i class="bi bi-geo-alt"></i> {{ data.lamaran.lokasi }}</span>
                            <span v-if="data.lamaran.level"><i class="bi bi-bar-chart-steps"></i> {{ data.lamaran.level }}</span>
                            <span class="is-mpp">{{ noMpp }}</span>
                            <span v-if="data.lamaran.diterimaPada"><i class="bi bi-clock-history"></i> {{ tglId(data.lamaran.diterimaPada) }}</span>
                            <a v-if="data.lamaran.email" :href="`mailto:${data.lamaran.email}`" class="is-link"><i class="bi bi-envelope"></i> {{ data.lamaran.email }}</a>
                            <a v-if="data.lamaran.hp" :href="`https://wa.me/${String(data.lamaran.hp).replace(/\D/g, '')}`" target="_blank" rel="noopener" class="is-link"><i class="bi bi-whatsapp"></i> {{ data.lamaran.hp }}</a>
                        </div>
                    </div>
                </div>
                <div style="height: 14px"></div>
            </div>
        </template>

        <div v-if="memuat" class="kdm-muat">
            <span class="plw-spin plw-spin--lg"></span>
            <p>Memuat profil kandidat…</p>
            <small>Mengambil biodata, formulir, dan berkas terlampir</small>
        </div>

        <div v-else-if="galat" class="kdm-galat">
            <i class="bi bi-exclamation-triangle-fill"></i> {{ galat }}
        </div>

        <template v-else-if="data">
            <!-- PROGRES SELEKSI — blok plw-alur, sama persis dengan worklist. -->
            <div v-if="alurKandidat.length" class="plw-alur" style="margin-bottom: 18px">
                <div class="plw-alur__head">
                    <span class="plw-alur__lbl">PROGRES SELEKSI</span>
                    <span class="plw-alur__pos">Tahap {{ tahapLewat }} dari {{ alurKandidat.length }}</span>
                </div>
                <div class="plw-alur__bar"><div :style="{ width: persenAlur + '%' }"></div></div>

                <ol class="plw-alur__line">
                    <li v-for="s in alurKandidat" :key="s.kunci" class="plw-alur__item" :class="'is-' + s.keadaan">
                        <span class="plw-alur__node"><span></span></span>
                        <div class="plw-alur__isi">
                            <div style="min-width: 0">
                                <div class="plw-alur__nama">{{ s.nomor }}. {{ s.label }}</div>
                                <div class="plw-alur__ket">{{ s.catatan }}</div>
                            </div>
                            <span class="plw-alur__tag">{{ s.tag }}</span>
                        </div>
                    </li>
                </ol>
            </div>

            <template v-for="(f, fi) in data.formulir || []" :key="fi">
                <h3 class="kdm-judul">{{ f.label || 'Formulir' }}</h3>
                <div v-for="(bg, bi) in f.bagian || []" :key="bi" class="kdm-bagian">
                    <p v-if="bg.judul" class="kdm-bagian__jd">{{ bg.judul }}</p>
                    <div class="kdm-isian">
                        <div v-for="(it, ii) in bg.isian || []" :key="ii" class="kdm-baris">
                            <span class="kdm-baris__lb">{{ it.label }}</span>
                            <span class="kdm-baris__nl">{{ String(it.nilai ?? '').trim() || '—' }}</span>
                        </div>
                    </div>
                </div>
            </template>

            <p v-if="!(data.formulir || []).length" class="kdm-galat kdm-galat--sepi">
                <i class="bi bi-inbox"></i> Kandidat ini belum mengisi formulir apa pun.
            </p>
        </template>

        <!-- KOLOM KANAN — pembaca berkas worklist, kelas plw-dok__* apa adanya. -->
        <template v-if="data" #aside>
            <div class="plw-dok">
                <div class="plw-dok__head">
                    <span class="plw-dok__headico"><i class="bi" :class="ikonBerkas(dokLihat)"></i></span>
                    <span class="plw-dok__headtxt">
                        <b :title="dokLihat ? dokLihat.nama : 'Berkas Kandidat'">{{ dokLihat ? dokLihat.nama : 'Berkas Kandidat' }}</b>
                        <em>
                            <template v-if="dokLihat">{{ dokLihat.folderLabel }} · {{ dokLihat.ext || 'FILE' }}<template v-if="dokLihat.ukuran"> · {{ ukuranBerkas(dokLihat.ukuran) }}</template><template v-if="dokLihat.waktu"> · {{ tglId(dokLihat.waktu) }}</template></template>
                            <template v-else>{{ dokSemua.length }} lembar</template>
                        </em>
                    </span>
                    <a
                        v-if="dokLihat" :href="dokLihat.url" target="_blank" rel="noopener"
                        class="plw-dok__hbtn" title="Buka di tab baru"
                    >
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    <a
                        v-if="dokLihat" :href="dokLihat.url" :download="dokLihat.file || true"
                        class="plw-dok__hbtn" title="Unduh berkas"
                    >
                        <i class="bi bi-download"></i>
                    </a>
                </div>

                <!-- Jalur lembar. Gambar memakai lembarannya sendiri sebagai
                     petak: satu deret berisi lima "FOTO.jpg" tak terbedakan oleh
                     ikon apa pun, tapi langsung terbedakan oleh gambarnya. -->
                <div v-if="dokSemua.length" class="plw-dok__strip">
                    <div class="plw-dok__striplist">
                        <button
                            v-for="(b, i) in dokSemua" :key="b.id"
                            type="button" class="plw-dok__sitem"
                            :class="{ 'is-on': dokLihat && dokLihat.id === b.id }"
                            :title="`${b.nama} — ${b.folderLabel} · ${b.file}`"
                            @click="lihatDok(i)"
                        >
                            <span class="plw-dok__sthumb" :class="{ 'is-img': b.isImage }">
                                <img v-if="b.isImage" :src="b.url" :alt="b.nama" loading="lazy" @error="gagalThumb($event)">
                                <i v-else class="bi" :class="ikonBerkas(b)"></i>
                                <em class="plw-dok__sext">{{ b.ext || 'FILE' }}</em>
                            </span>
                            <span class="plw-dok__sname">{{ b.nama }}</span>
                        </button>
                    </div>
                </div>

                <div class="plw-dok__view">
                    <div v-if="memuat" class="plw-dok__state">
                        <span class="plw-spin plw-spin--lg"></span>
                        <span>Memuat berkas…</span>
                    </div>
                    <div v-else-if="!dokSemua.length" class="plw-dok__state">
                        <span class="plw-dok__bigico"><i class="bi bi-folder-x"></i></span>
                        <span>Kandidat ini belum mengunggah berkas apa pun.</span>
                    </div>
                    <div v-else-if="!dokLihat" class="plw-dok__state">
                        <span class="plw-dok__bigico"><i class="bi bi-hand-index-thumb"></i></span>
                        <span>Pilih satu berkas di jalur atas.</span>
                    </div>
                    <div v-else-if="dokMuat" class="plw-dok__state">
                        <span class="plw-spin plw-spin--lg"></span>
                        <span>Memuat berkas…</span>
                    </div>
                    <div v-else-if="dokGagal" class="plw-dok__state">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 26px; color: #f87171"></i>
                        <span>Gagal memuat berkas.</span>
                        <button type="button" class="plw-dok__retry" @click="dokCoba">Coba lagi</button>
                    </div>
                    <div v-else-if="!dokBisaTampil" class="plw-dok__state">
                        <span class="plw-dok__bigico"><i class="bi" :class="ikonBerkas(dokLihat)"></i></span>
                        <span><b>{{ dokLihat.ext || 'Berkas' }}</b> tidak bisa dipratinjau di layar.</span>
                        <a :href="dokLihat.url" target="_blank" rel="noopener" class="plw-dok__retry">Unduh berkasnya</a>
                    </div>

                    <!-- Keduanya digambar DI LUAR rantai v-if/v-else di atas:
                         mereka harus tetap ada di DOM selagi `dokMuat` benar,
                         sebab justru merekalah yang memicu peristiwa `load`
                         yang mematikan penanda memuat itu. -->
                    <iframe
                        v-if="dokLihat && dokBisaTampil && dokPdf"
                        v-show="!dokMuat && !dokGagal"
                        :src="dokSrc" :title="dokLihat.nama"
                        class="plw-dok__pdf" @load="dokSelesai()"
                    ></iframe>
                    <img
                        v-else-if="dokLihat && dokBisaTampil"
                        v-show="!dokMuat && !dokGagal"
                        :src="dokSrc" :alt="dokLihat.nama" class="plw-dok__img"
                        @load="dokSelesai()" @error="dokSelesai(true)"
                    >
                </div>
            </div>
        </template>

        <template #footer>
            <div class="kdm-kaki">
                <span><i class="bi bi-eye"></i> Tampilan baca-saja — keputusan kandidat dilakukan di Worklist.</span>
                <button type="button" class="kdm-tutup" @click="$emit('tutup')">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
            </div>
        </template>
    </AdminModal>
</template>

<style scoped>
/* == SALINAN GAYA WORKLIST =================================================
   Diambil apa adanya dari Pelamar.vue. Gaya di sana ber-scoped sehingga tidak
   bisa dipinjam lintas komponen; menyalinnya adalah satu-satunya cara membuat
   kedua jendela benar-benar serupa tanpa membongkar Pelamar.vue. */
.plw-mono { font-family: 'JetBrains Mono', 'Courier New', monospace; font-weight: 700; color: #9aa3b5; }
.plw-card__chip { display: inline-block; margin-top: 11px; max-width: 100%; font-size: 9.5px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; padding: 4px 9px; border-radius: 7px; background: #eef0f7; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-card__chip.tone-nunggu { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.plw-card__chip.tone-perlu { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.plw-card__chip.tone-skor { background: rgba(139, 92, 246, 0.14); color: #7c3aed; }
.plw-card__chip.tone-lolos { background: rgba(16, 185, 129, 0.12); color: #059669; }
.plw-card__chip.tone-pascaPenerimaan { background: rgba(16, 185, 129, 0.16); color: #047857; }
.plw-card__chip.tone-gugur { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
.plw-card__chip.tone-talent { background: rgba(234, 179, 8, 0.16); color: #a16207; }
.plw-card__chip.tone-mundur { background: rgba(124, 58, 237, 0.13); color: #6d28d9; }
.plw-card__chip.tone-hold { background: rgba(100, 116, 139, 0.16); color: #475569; }
.plw-hero { padding: 18px var(--wca-modal-pad, 1.35rem) 0; background: linear-gradient(135deg, #f4f2ff 0%, #eef2ff 52%, #eaf1ff 100%); border-bottom: 1px solid #e4e7f5; }
.plw-hero__row { display: flex; align-items: flex-start; gap: 14px; flex-wrap: wrap; }
.plw-hero__avatar { width: 54px; height: 54px; border-radius: 16px; color: #fff; font-size: 17px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.plw-hero__tags { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-bottom: 7px; }
.plw-hero__name { font-size: 20px; font-weight: 800; color: #1e1b4b; letter-spacing: -0.02em; line-height: 1.2; text-wrap: pretty; }
.plw-dok { display: flex; flex-direction: column; height: 100%; min-height: 0; background: #fff; overflow: hidden; }
.plw-dok__head { display: flex; align-items: center; gap: 9px; padding: 11px 10px 11px 13px; border-bottom: 1px solid #eef0f7; background: linear-gradient(135deg, #f7f5ff, #eef2ff); flex: 0 0 auto; }
.plw-dok__headico { width: 32px; height: 32px; border-radius: 10px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 15px; box-shadow: 0 8px 18px rgba(99, 102, 241, 0.26); }
.plw-dok__headtxt { flex: 1; min-width: 0; }
.plw-dok__headtxt b { display: block; font-size: 12.5px; font-weight: 800; color: #1e1b4b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-dok__headtxt em { display: block; font-size: 10.5px; font-style: normal; color: #8b8bb0; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-dok__hbtn { appearance: none; border: 1px solid #e2ddf9; background: rgba(255, 255, 255, 0.82); cursor: pointer; font-family: inherit; flex: 0 0 auto; width: 28px; height: 28px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 11.5px; color: #7c7ca8; text-decoration: none; transition: all 0.16s; }
.plw-dok__hbtn:hover { background: #fff; color: #4f46e5; border-color: #c7d2fe; }
.plw-dok__strip { flex: 0 0 auto; display: flex; align-items: stretch; gap: 8px; padding: 9px 11px; border-bottom: 1px solid #f1f5f9; background: #fbfbfe; }
.plw-dok__striplist { flex: 1 1 auto; min-width: 0; display: flex; gap: 8px; overflow-x: auto; scrollbar-width: thin; }
.plw-dok__saring { position: relative; appearance: none; border: 1px solid #e6e3f7; background: #fff; cursor: pointer; font-family: inherit; flex: 0 0 auto; width: 36px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #6b6b95; transition: all 0.16s; }
.plw-dok__saring:hover { background: #f4f2ff; color: #4f46e5; border-color: #c7d2fe; }
.plw-dok__saring.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.plw-dok__saring em { position: absolute; top: 5px; right: 5px; width: 7px; height: 7px; border-radius: 999px; background: #f59e0b; box-shadow: 0 0 0 2px #fff; }
.plw-dok__stripkosong { flex: 1 1 auto; min-width: 0; display: flex; align-items: center; gap: 9px; padding: 0 4px; font-size: 11.5px; color: #94a3b8; }
.plw-dok__stripkosong > i { font-size: 13px; color: #c4b5fd; flex: 0 0 auto; }
.plw-dok__stripkosong > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plw-dok__stripkosong > button { appearance: none; border: 1px solid #c7d2fe; background: #fff; cursor: pointer; font-family: inherit; flex: 0 0 auto; margin-left: auto; padding: 5px 11px; border-radius: 8px; font-size: 11px; font-weight: 800; color: #4f46e5; }
.plw-dok__stripkosong > button:hover { background: #f4f2ff; }
.plw-dok__sitem { appearance: none; border: 1px solid #edeaf9; background: #fff; cursor: pointer; font-family: inherit; flex: 0 0 auto; width: 76px; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 6px 5px; border-radius: 11px; transition: transform 0.16s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.16s, border-color 0.16s; }
.plw-dok__sitem:hover { transform: translateY(-2px); border-color: #c7d2fe; box-shadow: 0 10px 22px rgba(99, 102, 241, 0.16); }
.plw-dok__sitem.is-on { border-color: #6366f1; background: #f6f5ff; box-shadow: 0 10px 22px rgba(99, 102, 241, 0.2); }
.plw-dok__sthumb { position: relative; width: 100%; height: 46px; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #eef2ff; border: 1px solid #dfe4fd; color: #4f46e5; font-size: 18px; }
.plw-dok__sthumb.is-img { background: #ecfdf5; border-color: #b6f0d5; color: #059669; }
.plw-dok__sthumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.plw-dok__sext { position: absolute; right: 3px; bottom: 3px; font-style: normal; font-size: 7.5px; font-weight: 800; letter-spacing: 0.04em; padding: 1px 4px; border-radius: 4px; background: rgba(30, 27, 49, 0.72); color: #fff; }
.plw-dok__sname { width: 100%; font-size: 9px; font-weight: 700; color: #64748b; line-height: 1.25; text-align: center; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.plw-dok__sitem.is-on .plw-dok__sname { color: #4338ca; font-weight: 800; }
.plw-dok__filter { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; padding: 9px 11px; border-bottom: 1px solid #f1f5f9; background: #f7f6fd; flex: 0 0 auto; }
.plw-dok__cari { position: relative; flex: 1 1 150px; min-width: 120px; display: flex; align-items: center; }
.plw-dok__cari > i { position: absolute; left: 10px; font-size: 11px; color: #a5a5c4; pointer-events: none; }
.plw-dok__cari input { width: 100%; height: 32px; padding: 0 28px 0 28px; border-radius: 9px; border: 1px solid #e6e3f7; background: #fff; font-size: 11.5px; color: #334155; outline: none; font-family: inherit; transition: all 0.16s; }
.plw-dok__cari input:focus { border-color: #a5b4fc; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12); }
.plw-dok__cari > button { position: absolute; right: 7px; appearance: none; border: none; background: transparent; cursor: pointer; color: #a5a5c4; font-size: 9.5px; padding: 4px; display: flex; }
.plw-dok__cari > button:hover { color: #4f46e5; }
.plw-dok__urut { appearance: none; border: 1px solid #e6e3f7; background: #fff; cursor: pointer; font-family: inherit; flex: 0 0 auto; height: 32px; padding: 0 10px; border-radius: 9px; display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; color: #6b6b95; transition: all 0.16s; }
.plw-dok__urut:hover { background: #f4f2ff; color: #4f46e5; border-color: #c7d2fe; }
.plw-dok__urut i { font-size: 13px; }
.plw-dok__tabs { flex: 1 1 100%; min-width: 0; display: flex; gap: 5px; overflow-x: auto; scrollbar-width: none; }
.plw-dok__tabs::-webkit-scrollbar { display: none; }
.plw-dok__tab { appearance: none; border: 1px solid #ece9fb; background: #fff; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px; height: 30px; padding: 0 9px; border-radius: 9px; font-size: 11px; font-weight: 800; white-space: nowrap; color: #6b6b95; flex: 0 0 auto; transition: all 0.16s; }
.plw-dok__tab i { font-size: 11px; }
.plw-dok__tab:hover { background: #f4f2ff; color: #4f46e5; }
.plw-dok__tab.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 6px 16px rgba(99, 102, 241, 0.26); }
.plw-dok__tab em { font-style: normal; font-size: 9.5px; font-weight: 800; padding: 1px 5px; border-radius: 999px; background: rgba(99, 102, 241, 0.12); color: #4338ca; }
.plw-dok__tab.is-on em { background: rgba(255, 255, 255, 0.26); color: #fff; }
.plw-dok__view { flex: 1 1 auto; min-height: 0; display: flex; align-items: center; justify-content: center; background: #1e1b31; overflow: hidden; position: relative; }
.plw-dok__pdf { width: 100%; height: 100%; border: 0; background: #fff; }
.plw-dok__img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
.plw-dok__state { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 24px 18px; text-align: center; font-size: 12px; color: #b9b6d6; }
.plw-dok__bigico { width: 52px; height: 52px; border-radius: 15px; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.1); color: #b9b6d6; font-size: 24px; }
.plw-dok__retry { appearance: none; border: 1px solid rgba(255, 255, 255, 0.24); background: rgba(255, 255, 255, 0.1); cursor: pointer; font-family: inherit; padding: 6px 14px; border-radius: 9px; font-size: 11.5px; font-weight: 800; color: #e9e7fa; text-decoration: none; }
.plw-dok__retry:hover { background: rgba(255, 255, 255, 0.18); }
.plw-drawer__meta { font-size: 12.5px; color: #6b6597; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-drawer__akun { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; font-size: 11px; font-weight: 600; color: #64748b; background: #f1f5f9; border-radius: 7px; padding: 2px 8px; }
.plw-drawer__chips { display: flex; flex-wrap: wrap; gap: 5px 10px; margin-top: 7px; font-size: 11px; color: #94a3b8; }
.plw-drawer__chips span, .plw-drawer__chips a { display: inline-flex; align-items: center; gap: 4px; }
.plw-drawer__chips .is-mpp { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; color: #64748b; background: #f1f5f9; padding: 1px 6px; border-radius: 4px; }
.plw-drawer__chips .is-link { color: #6366f1; font-weight: 600; text-decoration: none; }
.plw-drawer__chips .is-link:hover { text-decoration: underline; }
.plw-drawer__tag { display: inline-block; padding: 6px 12px; border-radius: 999px; color: #fff; font-size: 11.5px; font-weight: 800; white-space: nowrap; }
.plw-drawer__tag.is-mt { background: linear-gradient(135deg, #fbbf24, #f59e0b); box-shadow: 0 6px 16px rgba(245, 158, 11, 0.34); }
.plw-drawer__tag.is-rek { background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 6px 16px rgba(99, 102, 241, 0.34); }
.plw-hero { padding-top: 15px; }
.plw-hero__row { gap: 10px; }
.plw-hero__avatar { width: 44px; height: 44px; border-radius: 13px; font-size: 15px; }
.plw-hero__name { font-size: 17px; }

/* Linimasa tahap & spinner — juga salinan dari Pelamar.vue. */
.plw-spin { width: 18px; height: 18px; border-radius: 50%; border: 2.5px solid rgba(99, 102, 241, 0.18); border-top-color: #6366f1; animation: plwSpin 0.7s linear infinite; flex: 0 0 auto; }
.plw-spin--lg { width: 30px; height: 30px; border-width: 3px; }
@keyframes plwSpin { to { transform: rotate(360deg); } }
.plw-alur { background: #fff; border: 1px solid #eef0f7; border-radius: 18px; padding: 18px 20px; }
.plw-alur__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
.plw-alur__lbl { font-size: 11px; font-weight: 800; letter-spacing: 0.12em; color: #8b93a7; }
.plw-alur__pos { font-size: 13px; font-weight: 800; color: #4f46e5; }
.plw-alur__bar { height: 8px; border-radius: 99px; background: #eef0f7; overflow: hidden; margin-bottom: 20px; }
.plw-alur__bar > div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); transition: width 0.5s; }
.plw-alur__line { list-style: none; margin: 0; padding: 0 0 0 30px; position: relative; display: flex; flex-direction: column; gap: 14px; }
.plw-alur__line::before { content: ''; position: absolute; left: 13px; top: 8px; bottom: 8px; width: 2px; background: #eef0f7; }
.plw-alur__item { position: relative; }
.plw-alur__node { position: absolute; left: -30px; top: 0; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; border: 3px solid #cbd2e0; }
.plw-alur__node > span { width: 9px; height: 9px; border-radius: 50%; background: #cbd2e0; display: block; }
.plw-alur__item.is-lewat .plw-alur__node { border-color: #10b981; }
.plw-alur__item.is-lewat .plw-alur__node > span { background: #10b981; }
.plw-alur__item.is-kini .plw-alur__node { border-color: #f59e0b; animation: plwPulse 2.2s infinite; }
.plw-alur__item.is-kini .plw-alur__node > span { background: #f59e0b; }
.plw-alur__item.is-tutup .plw-alur__node { border-color: #ef4444; }
.plw-alur__item.is-tutup .plw-alur__node > span { background: #ef4444; }
@keyframes plwPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); } 50% { box-shadow: 0 0 0 7px rgba(245, 158, 11, 0); } }
.plw-alur__isi { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.plw-alur__nama { font-size: 13.5px; font-weight: 800; color: #94a3b8; }
.plw-alur__item.is-lewat .plw-alur__nama, .plw-alur__item.is-kini .plw-alur__nama, .plw-alur__item.is-tutup .plw-alur__nama { color: #1e293b; }
.plw-alur__ket { font-size: 11.5px; color: #8792a6; margin-top: 2px; }
.plw-alur__tag { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 4px 9px; border-radius: 999px; white-space: nowrap; background: #eef0f7; color: #94a3b8; }
.plw-alur__item.is-lewat .plw-alur__tag { background: rgba(16, 185, 129, 0.12); color: #059669; }
.plw-alur__item.is-kini .plw-alur__tag { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.plw-alur__item.is-tutup .plw-alur__tag { background: rgba(239, 68, 68, 0.12); color: #dc2626; }

/* == TAMBAHAN KHUSUS HALAMAN MPP == */
.kdm-muat { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 70px 20px; }
.kdm-muat p { margin: 0; font-size: 13.5px; font-weight: 700; color: #475569; }
.kdm-muat small { font-size: 11.5px; color: #94a3b8; }

.kdm-galat { display: flex; align-items: center; gap: 9px; padding: 16px; border-radius: 12px; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-size: 12.5px; }
.kdm-galat--sepi { background: #f8fafc; border-color: #e2e8f0; color: #64748b; }

.kdm-judul { margin: 20px 0 10px; font-size: 11.5px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #6b6597; padding-bottom: 7px; border-bottom: 2px solid #eef0f7; }
.kdm-bagian { margin-bottom: 14px; }
.kdm-bagian__jd { margin: 0 0 7px; font-size: 12px; font-weight: 800; color: #64748b; }
.kdm-isian { border: 1px solid #eef0f7; border-radius: 12px; overflow: hidden; }
.kdm-baris { display: grid; grid-template-columns: 1fr 1.3fr; gap: 12px; padding: 9px 13px; border-bottom: 1px solid #f4f5fb; }
.kdm-baris:last-child { border-bottom: 0; }
.kdm-baris:nth-child(odd) { background: #fcfcff; }
.kdm-baris__lb { font-size: 11.5px; color: #64748b; }
.kdm-baris__nl { font-size: 12.5px; font-weight: 700; color: #1e293b; word-break: break-word; }

.kdm-kaki { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; }
.kdm-kaki > span { display: inline-flex; align-items: center; gap: 7px; font-size: 11.5px; color: #94a3b8; }
.kdm-tutup { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0; background: #fff; color: #475569; font-size: 12.5px; font-weight: 800; border-radius: 10px; padding: 9px 20px; cursor: pointer; font-family: inherit; }
.kdm-tutup:hover { background: #f8fafc; }

@media (max-width: 780px) {
    .kdm-baris { grid-template-columns: 1fr; gap: 2px; }
}
</style>
