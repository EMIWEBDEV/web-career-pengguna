<!-- WEB CAREER — PILIH PERPANJANGAN jadwal pengisian formulir.

     Tiga cara (keputusan user): + N hari, + N jam, atau langsung pilih tanggal
     & jam di kalender. Hanya MAJU: jadwal yang sudah dipasang tidak disunting,
     jadi tanggal sebelum batas sekarang tidak bisa dipilih sama sekali.

     Pratinjau "sekarang → menjadi" dihitung utils/career/batasIsi.js, cermin
     BatasIsi::batasBaru() di server — terutama supaya perpanjangan batas yang
     SUDAH LEWAT tidak mengejutkan (dihitung dari hari ini, bukan batas lamanya).
     Dipakai modal kolom & modal kandidat di worklist Pelamar. -->
<template>
    <div class="ppb">
        <div class="ppb__seg" role="radiogroup" aria-label="Cara perpanjangan">
            <button
                v-for="c in CARA"
                :key="c.kode"
                type="button"
                role="radio"
                class="ppb__segb"
                :class="{ on: modelValue.cara === c.kode }"
                :aria-checked="modelValue.cara === c.kode"
                @click="ubah({ cara: c.kode })"
            >
                <i class="bi" :class="c.ikon" aria-hidden="true"></i> {{ c.label }}
            </button>
        </div>

        <div v-if="modelValue.cara !== 'SAMPAI'" class="ppb__lama">
            <div class="ppb__cepat">
                <button
                    v-for="n in cepat"
                    :key="n"
                    type="button"
                    class="ppb__chip"
                    :class="{ on: Number(nilai) === n }"
                    @click="ubahNilai(n)"
                >
                    +{{ n }} {{ satuan }}
                </button>
            </div>
            <div class="ppb__angka">
                <!-- Pembungkus berlebar tetap: tema memaksa .el-input-number
                     selebar 100% (!important), gaya sebaris kalah. -->
                <div class="ppb__num">
                    <el-input-number
                        :model-value="nilai"
                        :min="1"
                        :max="maks"
                        :step="1"
                        step-strictly
                        controls-position="right"
                        @update:model-value="ubahNilai"
                    />
                </div>
                <span>{{ satuan }}</span>
            </div>
        </div>
        <div v-else class="ppb__tgl">
            <el-date-picker
                :model-value="modelValue.sampai"
                type="datetime"
                format="DD MMM YYYY HH:mm"
                value-format="YYYY-MM-DD HH:mm:ss"
                placeholder="Pilih tanggal & jam batas baru"
                :disabled-date="tanggalMati"
                :default-time="JAM_BATAS"
                style="width: 100%"
                @update:model-value="ubah({ sampai: $event || '' })"
            />
        </div>

        <div class="ppb__hasil" :class="{ 'is-err': !!salahTampil }">
            <template v-if="batasKini">
                <div class="ppb__titik">
                    <small>Batas sekarang</small>
                    <b>{{ teksBatas(batasKini) }}</b>
                    <em v-if="kiniLewat">sudah lewat</em>
                </div>
                <i class="bi bi-arrow-right ppb__panah" aria-hidden="true"></i>
                <div class="ppb__titik is-baru">
                    <small>Menjadi</small>
                    <b>{{ baru ? teksBatas(baru) : '—' }}</b>
                </div>
            </template>
            <p v-else-if="modelValue.cara === 'SAMPAI'" class="ppb__ket">
                Yang batasnya lebih awal menjadi <b>{{ baru ? teksBatas(baru) : '…' }}</b>;
                yang sudah lebih lambat tidak diubah.
            </p>
            <p v-else class="ppb__ket">
                Masing-masing <b>+{{ nilai || '…' }} {{ satuan }}</b> dari batasnya sendiri —
                yang sudah lewat dihitung dari hari ini.
            </p>
        </div>

        <p v-if="salahTampil" class="ppb__err">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> {{ salahTampil }}
        </p>
        <p v-else-if="batasKini && kiniLewat && modelValue.cara !== 'SAMPAI'" class="ppb__hint">
            Batasnya sudah lewat, jadi perpanjangan dihitung dari hari ini.
        </p>
    </div>
</template>

<script>
import { MAKS_HARI, MAKS_JAM, batasBaru, keDate, salahPerpanjang, teksBatas } from '@utils/career/batasIsi';

const CARA = [
    { kode: 'HARI', label: '+ Hari', ikon: 'bi-calendar-plus' },
    { kode: 'JAM', label: '+ Jam', ikon: 'bi-clock-history' },
    { kode: 'SAMPAI', label: 'Tanggal & jam', ikon: 'bi-calendar-event' },
];

export default {
    props: {
        /** { cara: 'HARI'|'JAM'|'SAMPAI', hari, jam, sampai } — v-model. */
        modelValue: { type: Object, required: true },
        /** Batas sekarang "YYYY-MM-DD HH:mm:ss"; null = banyak kandidat, masing-masing dari batasnya. */
        batasKini: { type: String, default: null },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            CARA,
            // Batas berakhir di detik terakhir harinya — sama dengan server.
            JAM_BATAS: new Date(2000, 0, 1, 23, 59, 59),
            kini: new Date(),
        };
    },
    computed: {
        nilai() { return this.modelValue.cara === 'HARI' ? this.modelValue.hari : this.modelValue.jam; },
        maks() { return this.modelValue.cara === 'HARI' ? MAKS_HARI : MAKS_JAM; },
        satuan() { return this.modelValue.cara === 'HARI' ? 'hari' : 'jam'; },
        cepat() { return this.modelValue.cara === 'HARI' ? [1, 2, 3, 7, 14] : [1, 3, 6, 12, 24]; },
        kiniLewat() {
            const d = keDate(this.batasKini);

            return !!d && d < this.kini;
        },
        baru() { return batasBaru(this.batasKini, this.modelValue, this.kini); },
        /** Tanggal belum dipilih bukan kesalahan — tombolnya saja yang mati. */
        salahTampil() {
            if (this.modelValue.cara === 'SAMPAI' && !this.modelValue.sampai) return null;

            return salahPerpanjang(this.batasKini, this.modelValue, this.kini);
        },
    },
    methods: {
        teksBatas,
        ubah(p) {
            this.kini = new Date();
            this.$emit('update:modelValue', { ...this.modelValue, ...p });
        },
        ubahNilai(n) {
            this.ubah(this.modelValue.cara === 'HARI' ? { hari: n } : { jam: n });
        },
        /** Kalender: hari sebelum batas sekarang (atau sebelum hari ini) tak bisa dipilih. */
        tanggalMati(d) {
            const lama = keDate(this.batasKini);
            const acuan = lama && lama > this.kini ? lama : this.kini;

            return d < new Date(acuan.getFullYear(), acuan.getMonth(), acuan.getDate());
        },
    },
};
</script>

<style scoped>
.ppb { display: grid; gap: 10px; min-width: 0; }
.ppb__seg {
    display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 4px;
    padding: 4px; border-radius: 12px; background: #f2f4fb; border: 1px solid #e6e9f3;
}
.ppb__segb {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-width: 0;
    padding: 8px 6px; border: 1px solid transparent; border-radius: 9px; background: transparent;
    font: inherit; font-size: 12.5px; font-weight: 800; color: #64748b; cursor: pointer;
    white-space: nowrap; transition: all 0.16s ease;
}
.ppb__segb:hover { color: #334155; }
.ppb__segb.on { background: #fff; border-color: rgba(99, 102, 241, 0.35); color: #4338ca; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06); }

.ppb__lama { display: grid; gap: 8px; }
.ppb__cepat { display: flex; flex-wrap: wrap; gap: 6px; }
.ppb__chip {
    appearance: none; cursor: pointer; font: inherit; font-size: 11.5px; font-weight: 800;
    padding: 5px 10px; border-radius: 999px; border: 1px solid #e3e6f0; background: #fff; color: #475569;
    transition: all 0.16s ease;
}
.ppb__chip:hover { border-color: #c7cbdb; }
.ppb__chip.on { border-color: rgba(99, 102, 241, 0.5); background: rgba(99, 102, 241, 0.09); color: #4338ca; }
.ppb__angka { display: flex; align-items: center; gap: 8px; }
.ppb__num { flex: none; width: 150px; }
.ppb__angka > span { font-size: 12.5px; font-weight: 700; color: #475569; }

.ppb__hasil {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 11px;
    background: #eef2ff; border: 1px solid #c7d2fe;
}
.ppb__hasil.is-err { background: #fef2f2; border-color: #fecaca; }
.ppb__titik { display: grid; gap: 1px; min-width: 0; flex: 1; }
.ppb__titik small { font-size: 10.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
.ppb__titik b { font-size: 12.5px; font-weight: 800; color: #334155; }
.ppb__titik.is-baru b { color: #4338ca; }
.ppb__titik em { font-style: normal; font-size: 10.5px; font-weight: 800; color: #b91c1c; }
.ppb__panah { flex: none; color: #6366f1; font-size: 15px; }
.ppb__ket { margin: 0; font-size: 12px; line-height: 1.55; color: #3730a3; }
.ppb__err, .ppb__hint { display: flex; align-items: flex-start; gap: 6px; margin: 0; font-size: 11.5px; line-height: 1.5; }
.ppb__err { color: #b91c1c; font-weight: 700; }
.ppb__hint { color: #92400e; }

@media (max-width: 480px) {
    .ppb__hasil { flex-direction: column; align-items: stretch; }
    .ppb__panah { transform: rotate(90deg); align-self: center; }
}
</style>
