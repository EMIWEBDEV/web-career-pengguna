<!--
  ZONA D — KHAS MAGANG: KAMPUS, KEMITRAAN & BATCH (Redesigned)
-->
<template>
    <div class="mg-container">
        <!-- ══════ KEMITRAAN / MoU ══════ -->
        <div class="mg-section-hd">
            <i class="bi bi-file-earmark-text-fill"></i>
            <span>Kemitraan &amp; MoU Kampus</span>
        </div>

        <KeadaanPanel v-if="!khas.kemitraan.length" keadaan="kosong" rapat ikon="bi-file-earmark-x"
            teks="Belum ada kemitraan terdaftar"
            ket="Kemitraan dikelola di menu Master Kemitraan / MoU." />

        <div v-else class="mg-section-wrap">
            <!-- Toolbar Kemitraan -->
            <div class="mg-toolbar">
                <div class="mg-search">
                    <i class="bi bi-search"></i>
                    <input v-model="cariKemitraan" type="text" placeholder="Cari mitra, kampus, kota..." />
                    <button v-if="cariKemitraan" type="button" class="mg-search-clear" @click="cariKemitraan = ''">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
                <div class="mg-per-hal">
                    <span>Tampil:</span>
                    <button v-for="opt in [4, 8, 16, 0]" :key="opt" type="button"
                        class="mg-per-hal__btn" :class="{ 'is-on': perHalKemitraan === opt }"
                        @click="perHalKemitraan = opt">
                        {{ opt === 0 ? 'Semua' : opt }}
                    </button>
                </div>
            </div>

            <div v-if="kemitraanPaginated.length" class="wcd-grid wcd-grid--2">
                <div v-for="(m, i) in kemitraanPaginated" :key="i" class="mg-mou" :class="{ 'is-habis': habis(m), 'is-segera': segera(m) }">
                    <div class="mg-mou__hd">
                        <strong>{{ m.mitra }}</strong>
                        <span v-if="m.jenis" class="wcd-lb">{{ m.jenis }}</span>
                    </div>
                    <p class="mg-mou__kampus">
                        <i class="bi bi-building"></i>
                        {{ m.kampus || 'Kampus belum ditautkan' }}<template v-if="m.kota"> · {{ m.kota }}</template>
                    </p>

                    <div class="mg-mou__kuota">
                        <div class="wcd-meter">
                            <span :style="{ width: pct(m) + '%', background: m.terisi >= m.kuota && m.kuota ? STATUS.good.warna : 'linear-gradient(90deg, #10b981, #059669)' }"></span>
                        </div>
                        <span><b>{{ angka(m.terisi) }}</b> / {{ angka(m.kuota) }} kuota</span>
                    </div>

                    <div class="mg-mou__ft">
                        <span class="wcd-lb" :style="nadaMasa(m)">
                            <i class="bi" :class="ikonMasa(m)"></i>
                            <template v-if="m.sisaHari === null">Tanpa tanggal akhir</template>
                            <template v-else-if="m.sisaHari < 0">Berakhir {{ Math.abs(m.sisaHari) }} hari lalu</template>
                            <template v-else-if="m.sisaHari === 0">Berakhir hari ini</template>
                            <template v-else>{{ m.sisaHari }} hari lagi</template>
                        </span>
                        <span class="mg-mou__tgl">{{ tanggal(m.mulai) }} – {{ m.selesai ? tanggal(m.selesai) : '∞' }}</span>
                        <span v-if="m.status" class="wcd-lb">{{ m.status }}</span>
                    </div>

                    <p v-if="segera(m) && m.terisi < m.kuota" class="mg-mou__ingat">
                        <i class="bi" :class="STATUS.serious.ikon"></i>
                        Masih {{ angka(m.kuota - m.terisi) }} kuota belum dipakai dan masa berlakunya hampir habis.
                    </p>
                </div>
            </div>
            <KeadaanPanel v-else-if="cariKemitraan" keadaan="kosong" rapat
                teks="Kemitraan tidak ditemukan"
                ket="Coba ubah kata kunci pencarian." />

            <!-- Paginasi Kemitraan -->
            <div v-if="kemitraanTersaring.length && (totalHalKemitraan > 1 || perHalKemitraan > 0)" class="mg-paginasi">
                <span class="mg-paginasi__info">
                    Menampilkan <b>{{ perHalKemitraan === 0 ? 1 : ((halKemitraan - 1) * perHalKemitraan) + 1 }} - {{ perHalKemitraan === 0 ? kemitraanTersaring.length : Math.min(halKemitraan * perHalKemitraan, kemitraanTersaring.length) }}</b> dari <b>{{ kemitraanTersaring.length }}</b> mitra
                </span>
                <div v-if="totalHalKemitraan > 1" class="mg-paginasi__nav">
                    <button type="button" class="mg-paginasi__btn" :disabled="halKemitraan <= 1" :onClick="halKemitraan <= 1 ? null : () => halKemitraan--">
                        <i class="bi bi-chevron-left"></i> Sebelum
                    </button>
                    <span class="mg-paginasi__page">{{ halKemitraan }} / {{ totalHalKemitraan }}</span>
                    <button type="button" class="mg-paginasi__btn" :disabled="halKemitraan >= totalHalKemitraan" @click="halKemitraan++">
                        Lanjut <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ BATCH ══════ -->
        <div class="mg-section-hd mg-jarak">
            <i class="bi bi-layers-fill"></i>
            <span>Progres Batch</span>
        </div>

        <KeadaanPanel v-if="!khas.batch.length" keadaan="kosong" rapat ikon="bi-layers"
            teks="Belum ada batch magang"
            ket="Batch dibuat di dalam program di menu Program Kegiatan." />

        <div v-else class="wcd-tw mg-tw">
            <table class="wcd-tbl">
                <thead>
                    <tr><th>Batch</th><th>Program</th><th class="wcd-num">Terisi</th><th>Progres</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(b, i) in khas.batch" :key="i">
                        <td class="wcd-tbl__utama"><strong>{{ b.nama }}</strong></td>
                        <td>{{ b.program }}</td>
                        <td class="wcd-num"><b>{{ angka(b.terisi) }}</b> <small>/ {{ angka(b.kuota) }}</small></td>
                        <td>
                            <div class="mg-prog">
                                <div class="wcd-meter">
                                    <span :style="{ width: pct(b) + '%', background: 'linear-gradient(90deg, #10b981, #059669)' }"></span>
                                </div>
                                <span>{{ b.kuota ? pct(b) + '%' : '—' }}</span>
                            </div>
                        </td>
                        <td><span class="wcd-lb">{{ b.status || '—' }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ══════ SEBARAN PENDIDIKAN ══════ -->
        <div class="mg-section-hd mg-jarak">
            <i class="bi bi-mortarboard-fill"></i>
            <span>Profil Pendidikan Pelamar</span>
        </div>

        <div class="wcd-grid wcd-grid--3">
            <div class="wcd-card wcd-card--datar">
                <h4 class="mg-sub">Jenjang</h4>
                <DaftarBatang :items="khas.pendidikan.jenjang" warna="#10b981" satuan="org"
                    ikon="bi-mortarboard" teks-kosong="Jenjang belum terindeks" />
            </div>
            <div class="wcd-card wcd-card--datar">
                <h4 class="mg-sub">Kampus Teratas</h4>
                <DaftarBatang :items="khas.pendidikan.kampus" warna="#10b981" satuan="org"
                    ikon="bi-building" teks-kosong="Kampus belum terindeks"
                    ket-kosong="Field kampus terindeks bila dipakai sebagai syarat program." />
            </div>
            <div class="wcd-card wcd-card--datar">
                <h4 class="mg-sub">Jurusan Teratas</h4>
                <DaftarBatang :items="khas.pendidikan.jurusan" warna="#10b981" satuan="org"
                    ikon="bi-book" teks-kosong="Jurusan belum terindeks" />
            </div>
        </div>

        <div v-if="khas.pendidikan.ipk" class="wcd-card wcd-card--datar mg-ipk">
            <h4 class="mg-sub">
                <span>Sebaran IPK</span>
                <small>{{ angka(khas.pendidikan.ipk.jml) }} pelamar terdata</small>
            </h4>
            <div class="ex-mini-grid">
                <div class="ex-stat-item" :style="{ '--tone': '#10b981' }">
                    <div class="ex-stat-item__num">{{ desimal(khas.pendidikan.ipk.rata, 2) }}</div>
                    <div class="ex-stat-item__lbl">IPK Rata-rata</div>
                    <div class="ex-stat-item__ket">{{ desimal(khas.pendidikan.ipk.terendah, 2) }} – {{ desimal(khas.pendidikan.ipk.tertinggi, 2) }}</div>
                </div>
                <div class="ex-stat-item" :style="{ '--tone': '#10b981' }">
                    <div class="ex-stat-item__num">{{ angka(khas.pendidikan.ipk.atas35) }}</div>
                    <div class="ex-stat-item__lbl">IPK ≥ 3,50</div>
                </div>
                <div class="ex-stat-item" :style="{ '--tone': '#64748b' }">
                    <div class="ex-stat-item__num">{{ angka(khas.pendidikan.ipk.tiga) }}</div>
                    <div class="ex-stat-item__lbl">3,00 – 3,49</div>
                </div>
                <div class="ex-stat-item" :style="{ '--tone': '#64748b' }">
                    <div class="ex-stat-item__num">{{ angka(khas.pendidikan.ipk.bawah3) }}</div>
                    <div class="ex-stat-item__lbl">Di bawah 3,00</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { angka, desimal, tanggal, persen, STATUS } from '@utils/career/dashboard';
import DaftarBatang from '../panels/DaftarBatang.vue';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ khas: { type: Object, required: true } });

const cariKemitraan = ref('');
const halKemitraan = ref(1);
const perHalKemitraan = ref(4);

const pct = (x) => (x.kuota ? Math.min(100, persen(x.terisi, x.kuota)) : 0);
const habis = (m) => m.sisaHari !== null && m.sisaHari < 0;
const segera = (m) => m.sisaHari !== null && m.sisaHari >= 0 && m.sisaHari <= 60;

const kemitraanUrut = computed(() => [...props.khas.kemitraan].sort((a, b) => {
    const sa = a.sisaHari === null ? 99999 : a.sisaHari;
    const sb = b.sisaHari === null ? 99999 : b.sisaHari;
    return sa - sb;
}));

const kemitraanTersaring = computed(() => {
    let list = kemitraanUrut.value;
    if (!cariKemitraan.value.trim()) return list;
    const q = cariKemitraan.value.toLowerCase().trim();
    return list.filter((m) => {
        const mitra = (m.mitra || '').toLowerCase();
        const kampus = (m.kampus || '').toLowerCase();
        const kota = (m.kota || '').toLowerCase();
        return mitra.includes(q) || kampus.includes(q) || kota.includes(q);
    });
});

watch([cariKemitraan, perHalKemitraan], () => {
    halKemitraan.value = 1;
});

const totalHalKemitraan = computed(() => {
    if (perHalKemitraan.value === 0) return 1;
    return Math.max(1, Math.ceil(kemitraanTersaring.value.length / perHalKemitraan.value));
});

const kemitraanPaginated = computed(() => {
    if (perHalKemitraan.value === 0) return kemitraanTersaring.value;
    const start = (halKemitraan.value - 1) * perHalKemitraan.value;
    return kemitraanTersaring.value.slice(start, start + perHalKemitraan.value);
});

function nadaMasa(m) {
    if (habis(m)) return { background: '#f1f5f9', color: '#64748b' };
    if (m.sisaHari !== null && m.sisaHari <= 30) return { background: `color-mix(in srgb, ${STATUS.critical.warna} 14%, #fff)`, color: STATUS.critical.warna };
    if (segera(m)) return { background: `color-mix(in srgb, ${STATUS.serious.warna} 16%, #fff)`, color: '#9a3412' };
    return { background: '#ecfdf5', color: '#047857' };
}

function ikonMasa(m) {
    if (habis(m)) return 'bi-slash-circle';
    if (segera(m)) return STATUS.serious.ikon;
    return 'bi-calendar-check';
}
</script>

<style scoped>
.mg-container { padding-top: 4px; }
.mg-section-hd {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    font-weight: 900;
    color: #0f172a;
    margin-bottom: 14px;
}
.mg-section-hd .bi { color: #10b981; font-size: 1.1rem; }
.mg-jarak { margin-top: 24px; }
.mg-sub {
    margin: 0 0 12px;
    font-size: 0.8rem;
    font-weight: 900;
    color: #0f172a;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.mg-sub small { font-weight: 600; color: #94a3b8; font-size: 0.72rem; }

.mg-mou {
    padding: 16px;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #10b981;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
}
.mg-mou.is-segera { border-left-color: #ea580c; }
.mg-mou.is-habis { border-left-color: #94a3b8; opacity: 0.75; }

.mg-mou__hd { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.mg-mou__hd strong { font-size: 0.88rem; font-weight: 900; color: #0f172a; }
.mg-mou__kampus { margin: 6px 0 12px; font-size: 0.78rem; color: #64748b; }
.mg-mou__kampus .bi { color: #10b981; }

.mg-mou__kuota { display: flex; align-items: center; gap: 12px; }
.mg-mou__kuota .wcd-meter { flex: 1; }
.mg-mou__kuota span { font-size: 0.76rem; color: #475569; font-variant-numeric: tabular-nums; }
.mg-mou__kuota b { font-weight: 900; color: #0f172a; }

.mg-mou__ft { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
.mg-mou__tgl { font-size: 0.73rem; color: #94a3b8; font-weight: 500; }

.mg-mou__ingat {
    margin: 12px 0 0;
    font-size: 0.76rem;
    line-height: 1.5;
    color: #9a3412;
    font-weight: 600;
}
.mg-mou__ingat .bi { color: #ea580c; }

.mg-prog { display: flex; align-items: center; gap: 10px; min-width: 130px; }
.mg-prog span { font-size: 0.76rem; font-weight: 800; color: #475569; }
.mg-ipk { margin-top: 16px; }

/* ══════════ TOOLBAR FILTER & SEARCH ══════════ */
.mg-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.mg-search {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    flex: 1;
    max-width: 320px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.mg-search i { color: #94a3b8; font-size: 0.85rem; }
.mg-search input {
    width: 100%;
    border: 0;
    outline: 0;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 700;
    color: #0f172a;
    background: transparent;
}
.mg-search-clear {
    border: 0;
    background: transparent;
    color: #cbd5e1;
    cursor: pointer;
    padding: 0;
}
.mg-search-clear:hover { color: #ef4444; }

.mg-per-hal {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    margin-left: auto;
}
.mg-per-hal__btn {
    padding: 4px 9px;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #334155;
    font: inherit;
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.mg-per-hal__btn.is-on {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
}

.mg-paginasi {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 10px;
    padding: 8px 14px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.74rem;
    color: #475569;
}
.mg-paginasi__info b { color: #0f172a; font-weight: 800; }
.mg-paginasi__nav {
    display: flex;
    align-items: center;
    gap: 8px;
}
.mg-paginasi__btn {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #1e293b;
    font: inherit;
    font-size: 0.72rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.mg-paginasi__btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.mg-paginasi__btn:hover:not(:disabled) {
    border-color: #10b981;
    color: #10b981;
}
.mg-paginasi__page {
    font-weight: 800;
    color: #0f172a;
}
</style>
