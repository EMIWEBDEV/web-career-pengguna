<!-- WEB CAREER — MONITORING: JEJAK SERAH TERIMA PIC LOKER.

     Read-only, dan itu disengaja. Tidak ada tombol membatalkan di sini:
     membatalkan sebuah serah terima adalah serah terima BARU yang juga harus
     punya alasannya sendiri, dan tempatnya di Master Akun / Program Kegiatan.
     Halaman kendali mutu yang bisa mengubah data yang sedang ia periksa
     berhenti jadi alat pemeriksa. -->
<template>
    <div class="rpc">
        <!-- ── ANGKA KENDALI ──
             Dihitung dari SELURUH riwayat yang cocok penyaring, bukan dari baris
             yang kebetulan tampil — angka yang berubah saat menggulir tidak bisa
             dipakai memutuskan apa pun. -->
        <div class="rpc-kpi">
            <div class="rpc-kpi__item">
                <small>Perpindahan</small>
                <b>{{ ringkas.total ?? 0 }}</b>
            </div>
            <div class="rpc-kpi__item">
                <small>Loker tersentuh</small>
                <b>{{ ringkas.lokerTersentuh ?? 0 }}</b>
            </div>
            <div class="rpc-kpi__item">
                <small>Kandidat ikut pindah</small>
                <b>{{ ringkas.kandidat ?? 0 }}</b>
            </div>
            <div class="rpc-kpi__item is-warn">
                <small>Dipindahkan admin</small>
                <b>{{ ringkas.olehAdmin ?? 0 }}</b>
            </div>
        </div>

        <!-- LOKER YANG TERUS BERPINDAH — sinyal, bukan hiasan. Loker yang
             berganti tangan berkali-kali biasanya berarti tidak ada yang
             benar-benar merasa memegangnya, dan di situlah kandidat paling
             sering tertinggal tanpa ada yang menyadari. -->
        <div v-if="sering.length" class="rpc-alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <b>{{ sering.length }} loker berpindah tangan lebih dari dua kali.</b>
                <div class="rpc-alert__list">
                    <span v-for="(s, i) in sering" :key="i" class="rpc-alert__chip">
                        {{ s.posisi }} <em>{{ s.program }}</em> &times;{{ s.jml }}
                    </span>
                </div>
            </div>
        </div>

        <!-- ── SARINGAN ── -->
        <div class="rpc-bar">
            <div class="rpc-cari">
                <i class="bi bi-search"></i>
                <input v-model="q" type="text" placeholder="Cari nama, loker, program, atau alasan…" @input="cariDebounce" />
                <button v-if="q" type="button" @click="q = ''; muat()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="rpc-seg" role="group">
                <button
                    v-for="s in SUMBER"
                    :key="s.value"
                    type="button"
                    :class="{ on: sumber === s.value }"
                    :title="s.desc"
                    @click="sumber = s.value; muat()"
                >{{ s.label }}</button>
            </div>
            <el-date-picker
                v-model="rentang" type="daterange" value-format="YYYY-MM-DD"
                start-placeholder="Dari" end-placeholder="Sampai" range-separator="—"
                style="width:250px" @change="muat"
            />
        </div>

        <!-- ── DAFTAR ── -->
        <div v-loading="sibuk" class="rpc-list">
            <div v-for="r in data" :key="r.id" class="rpc-row">
                <div class="rpc-row__waktu">
                    <b>{{ tgl(r.at) }}</b>
                    <small>{{ jam(r.at) }}</small>
                </div>

                <div class="rpc-row__alur">
                    <!-- Kosong = penugasan PERTAMA, bukan "diambil dari orang
                         yang namanya hilang". Dua hal yang berbeda. -->
                    <span class="rpc-org" :class="{ 'is-kosong': !r.dari }">
                        {{ r.dari || 'belum bertuan' }}
                    </span>
                    <i class="bi bi-arrow-right"></i>
                    <span class="rpc-org is-ke">{{ r.ke }}</span>
                </div>

                <div class="rpc-row__loker">
                    <strong>{{ r.posisi }}</strong>
                    <small>{{ r.program }}<template v-if="r.mppRef"> &middot; {{ r.mppRef }}</template></small>
                </div>

                <div class="rpc-row__meta">
                    <span class="rpc-tag" :class="r.sumber === 'ADMIN' ? 'is-admin' : 'is-sendiri'">
                        <i class="bi" :class="r.sumber === 'ADMIN' ? 'bi-shield-lock-fill' : 'bi-person-check-fill'"></i>
                        {{ r.sumber === 'ADMIN' ? 'Dipindahkan admin' : 'Diserahkan sendiri' }}
                    </span>
                    <span class="rpc-kand"><i class="bi bi-people-fill"></i> {{ r.kandidat }}</span>
                </div>

                <p class="rpc-row__alasan">
                    <i class="bi bi-quote"></i>{{ r.alasan }}
                    <em>— dicatat {{ r.oleh || 'sistem' }}</em>
                </p>
            </div>

            <div v-if="!sibuk && !data.length" class="rpc-kosong">
                <i class="bi" :class="adaSaringan ? 'bi-funnel' : 'bi-clock-history'"></i>
                <b>{{ adaSaringan ? 'Tidak ada yang cocok' : 'Belum ada serah terima' }}</b>
                <span v-if="adaSaringan">Longgarkan saringannya, atau kembalikan ke semula.</span>
                <span v-else>
                    Setiap perpindahan tanggung jawab loker tercatat di sini — dari siapa, ke siapa,
                    dengan alasan apa, dan berapa kandidat yang ikut berpindah.
                </span>
                <button v-if="adaSaringan" type="button" class="rpc-reset" @click="reset">
                    <i class="bi bi-arrow-counterclockwise"></i> Kembalikan saringan
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

const API = '/api/v1/karir/monitoring/riwayat-pic';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    name: 'RiwayatPic',
    data() {
        return {
            data: [],
            ringkas: {},
            sering: [],
            sibuk: false,
            q: '',
            sumber: '',
            rentang: null,
            timer: null,
            SUMBER: [
                { value: '', label: 'Semua', desc: 'Seluruh perpindahan' },
                { value: 'SENDIRI', label: 'Sendiri', desc: 'Rekruter melepas lokernya sendiri' },
                { value: 'ADMIN', label: 'Admin', desc: 'Dipindahkan admin tanpa menyentuh pemiliknya' },
            ],
        };
    },
    computed: {
        adaSaringan() {
            return !!(this.q || this.sumber || (this.rentang && this.rentang.length));
        },
    },
    mounted() {
        this.muat();
    },
    methods: {
        cariDebounce() {
            if (this.timer) clearTimeout(this.timer);
            this.timer = setTimeout(() => this.muat(), 400);
        },
        reset() {
            this.q = '';
            this.sumber = '';
            this.rentang = null;
            this.muat();
        },
        async muat() {
            this.sibuk = true;
            try {
                const res = await axios.get(API, {
                    ...CFG,
                    params: {
                        q: this.q || undefined,
                        sumber: this.sumber || undefined,
                        dari: this.rentang?.[0] || undefined,
                        sampai: this.rentang?.[1] || undefined,
                    },
                });
                const r = res.data.result || {};
                this.data = r.data || [];
                this.ringkas = r.ringkas || {};
                this.sering = r.sering || [];
            } catch (e) {
                this.data = [];
            } finally {
                this.sibuk = false;
            }
        },
        tgl(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));
            return isNaN(d) ? '—' : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        jam(v) {
            if (!v) return '';
            const d = new Date(String(v).replace(' ', 'T'));
            return isNaN(d) ? '' : d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        },
    },
};
</script>

<style scoped>
.rpc { display: flex; flex-direction: column; gap: .9rem; }

/* ── ANGKA KENDALI ── */
.rpc-kpi { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .7rem; }
@media (max-width: 760px) { .rpc-kpi { grid-template-columns: repeat(2, 1fr); } }
.rpc-kpi__item { padding: .8rem .95rem; border-radius: .9rem; border: 1px solid #e2e8f0; background: #fff; }
.rpc-kpi__item small { display: block; font-size: .66rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #94a3b8; }
.rpc-kpi__item b { display: block; margin-top: .2rem; font-size: 1.35rem; font-weight: 800; color: #312e81; font-variant-numeric: tabular-nums; }
/* Perpindahan oleh admin dibedakan warnanya: bukan buruk, tapi selalu perlu
   ditengok — ia terjadi tanpa sepengetahuan pemilik lokernya. */
.rpc-kpi__item.is-warn { border-color: #fde68a; background: linear-gradient(180deg, #fffbeb, #fff); }
.rpc-kpi__item.is-warn b { color: #92400e; }

/* ── SINYAL ── */
.rpc-alert { display: flex; gap: .7rem; padding: .8rem .95rem; border-radius: .9rem; border: 1px solid #fde68a; background: #fffbeb; }
.rpc-alert > i { flex: none; margin-top: .1rem; color: #d97706; font-size: 1rem; }
.rpc-alert b { font-size: .82rem; color: #92400e; }
.rpc-alert__list { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .45rem; }
.rpc-alert__chip { padding: .18rem .5rem; border-radius: 999px; background: #fff; border: 1px solid #fcd34d; font-size: .7rem; font-weight: 700; color: #92400e; }
.rpc-alert__chip em { font-style: normal; font-weight: 500; color: #a16207; }

/* ── SARINGAN ── */
.rpc-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
.rpc-cari { position: relative; flex: 1 1 16rem; display: flex; align-items: center; }
.rpc-cari > i { position: absolute; left: .75rem; color: #94a3b8; font-size: .8rem; }
.rpc-cari input { width: 100%; padding: .55rem .75rem .55rem 2rem; border: 1px solid #e2e8f0; border-radius: .7rem; font: inherit; font-size: .82rem; background: #fff; }
.rpc-cari input:focus { outline: none; border-color: #6366f1; }
.rpc-cari button { position: absolute; right: .5rem; border: none; background: none; color: #94a3b8; cursor: pointer; font-size: .7rem; }
.rpc-seg { display: inline-flex; border: 1px solid #e2e8f0; border-radius: .7rem; overflow: hidden; background: #fff; }
.rpc-seg button { border: none; background: none; padding: .5rem .85rem; font: inherit; font-size: .78rem; font-weight: 700; color: #64748b; cursor: pointer; }
.rpc-seg button.on { background: #eef2ff; color: #4338ca; }

/* ── DAFTAR ── */
.rpc-list { display: flex; flex-direction: column; gap: .5rem; min-height: 8rem; }
.rpc-row {
    display: grid;
    grid-template-columns: 5.5rem minmax(0, 1.1fr) minmax(0, 1.2fr) auto;
    grid-template-areas: 'waktu alur loker meta' '. alasan alasan alasan';
    gap: .45rem .85rem; align-items: center;
    padding: .75rem .9rem; border: 1px solid #e2e8f0; border-radius: .85rem; background: #fff;
}
@media (max-width: 860px) {
    .rpc-row { grid-template-columns: 1fr; grid-template-areas: 'waktu' 'alur' 'loker' 'meta' 'alasan'; }
}
.rpc-row__waktu { grid-area: waktu; display: flex; flex-direction: column; }
.rpc-row__waktu b { font-size: .78rem; color: #334155; }
.rpc-row__waktu small { font-size: .7rem; color: #94a3b8; font-variant-numeric: tabular-nums; }

.rpc-row__alur { grid-area: alur; display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; }
.rpc-row__alur > i { color: #a5b4fc; font-size: .75rem; }
.rpc-org { padding: .2rem .55rem; border-radius: .5rem; background: #f1f5f9; color: #475569; font-size: .76rem; font-weight: 700; }
.rpc-org.is-ke { background: #eef2ff; color: #4338ca; }
.rpc-org.is-kosong { background: transparent; border: 1px dashed #cbd5e1; color: #94a3b8; font-weight: 500; font-style: italic; }

.rpc-row__loker { grid-area: loker; display: flex; flex-direction: column; min-width: 0; }
.rpc-row__loker strong { font-size: .82rem; color: #1e293b; overflow-wrap: anywhere; }
.rpc-row__loker small { font-size: .72rem; color: #64748b; overflow-wrap: anywhere; }

.rpc-row__meta { grid-area: meta; display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; }
.rpc-tag { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
.rpc-tag.is-sendiri { background: #ecfdf5; color: #047857; }
.rpc-tag.is-admin { background: #fff7ed; color: #9a3412; }
.rpc-kand { display: inline-flex; align-items: center; gap: .25rem; font-size: .72rem; font-weight: 700; color: #64748b; }

.rpc-row__alasan { grid-area: alasan; margin: 0; font-size: .76rem; line-height: 1.6; color: #475569; overflow-wrap: anywhere; }
.rpc-row__alasan > i { margin-right: .25rem; color: #c7d2fe; }
.rpc-row__alasan em { font-style: normal; color: #94a3b8; }

/* ── KOSONG ── */
.rpc-kosong { display: flex; flex-direction: column; align-items: center; gap: .45rem; padding: 2.4rem 1.2rem; text-align: center; background: #fff; border: 1px dashed rgba(15, 23, 42, .12); border-radius: .9rem; }
.rpc-kosong > i { font-size: 1.6rem; color: #a5b4fc; }
.rpc-kosong b { font-size: .88rem; color: #334155; }
.rpc-kosong span { font-size: .78rem; line-height: 1.65; color: #64748b; max-width: 32rem; }
.rpc-reset { margin-top: .3rem; display: inline-flex; align-items: center; gap: .3rem; border: 1px solid rgba(79, 70, 229, .25); background: #eef2ff; color: #4338ca; font: inherit; font-size: .74rem; font-weight: 700; border-radius: .55rem; padding: .3rem .65rem; cursor: pointer; }
</style>
