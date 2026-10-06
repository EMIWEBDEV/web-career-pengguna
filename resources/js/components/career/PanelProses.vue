<!--
    WEB CAREER — PANEL PEKERJAAN LATAR (pojok kanan bawah).

    RUPA YANG SAMA UNTUK SEMUA PEKERJAAN YANG BERJALAN DI LATAR.

    Sebelum ini tiap layar membuat panelnya sendiri: penjadwalan punya panel
    antrean bertajuk gelap, worklist punya panel unduhan berwarna putih, dan
    keputusan massal punya panel ketiga yang lain lagi. Tiga benda yang
    mengatakan hal yang sama — "ada pekerjaan berjalan, ini kemajuannya" —
    dengan tiga bentuk, tiga ukuran, dan tiga tempat berbeda. Yang membaca
    layar harus belajar tiga kali.

    Panel ini rupanya, dan hanya rupanya. Ia tidak menghubungi server, tidak
    menyimpan apa pun, dan tidak tahu pekerjaan apa yang sedang berjalan:
    seluruh angka datang dari induknya. Dengan begitu ia tidak pernah jadi
    tempat kedua yang menyimpan kebenaran.

    DUA BENTUK ISI, SATU CANGKANG:

      grup   dipakai pekerjaan yang punya kumpulan — satu program berisi
             banyak kandidat. Barisnya bisa dibuka, dan nama orangnya muncul
             di dalamnya berikut sebab kegagalan masing-masing.
      slot   dipakai pekerjaan yang isinya daftar datar (unduhan berkas).
             Induknya merender sendiri, panel ini cuma menyediakan bingkai.
-->
<template>
    <transition name="pp-pop">
        <section
            v-if="tampil"
            class="pp"
            :class="{ 'is-lipat': lipat }"
            role="status"
            aria-live="polite"
        >
            <!-- ══ KEPALA ══ -->
            <header class="pp__head" @click="lipat = !lipat">
                <span class="pp__ico" :class="'is-' + nada">
                    <i class="bi" :class="ikon"></i>
                </span>
                <div class="pp__ttl">
                    <b>{{ judul }}</b>
                    <small>{{ ringkas }}</small>
                </div>
                <button
                    type="button" class="pp__btn"
                    :title="lipat ? 'Bentangkan' : 'Lipat'"
                    @click.stop="lipat = !lipat"
                >
                    <i class="bi" :class="lipat ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </button>
                <!-- Menutup panel TIDAK membatalkan apa pun — pekerjaannya
                     berjalan di antrean, bukan di tab ini. Judulnya menyebut itu
                     supaya tak seorang pun ragu menekannya. -->
                <button
                    v-if="dapatTutup"
                    type="button" class="pp__btn"
                    :title="jalan ? 'Sembunyikan — pekerjaannya tetap berjalan di latar belakang' : 'Tutup'"
                    @click.stop="$emit('tutup')"
                >
                    <i class="bi bi-x-lg"></i>
                </button>
            </header>

            <!-- Bilah kemajuan MELEKAT DI KEPALA, jadi ia tetap terbaca saat
                 panelnya dilipat — itulah satu-satunya hal yang benar-benar
                 ingin diketahui orang saat menunggu. -->
            <div class="pp__rail">
                <span class="pp__fill" :class="{ 'is-gagal': nada === 'gagal' }" :style="{ width: persen + '%' }"></span>
                <span v-if="jalan" class="pp__kilau"></span>
            </div>

            <div v-show="!lipat" class="pp__isi">
                <!-- ISI BEBAS (dipakai panel unduhan: daftar datar bergaya Drive). -->
                <slot>
                    <article v-for="g in grup" :key="g.id" class="pp-row" :class="{ 'is-buka': buka === g.id }">
                        <button type="button" class="pp-row__head" @click="toggle(g)">
                            <span class="pp-row__ico" :class="'is-' + nadaGrup(g)">
                                <i class="bi" :class="ikonGrup(g)"></i>
                            </span>
                            <span class="pp-row__in">
                                <span class="pp-row__ttl">
                                    <!-- NAMA PROGRAM yang disebut, bukan kode gelombang:
                                         kode itu tidak berarti apa-apa bagi orang yang baru
                                         saja menekan tombolnya. -->
                                    <b>{{ g.judul }}</b>
                                    <em v-if="g.sub">{{ g.sub }}</em>
                                </span>
                                <span class="pp-row__meta">
                                    <b>{{ g.selesai }}</b> dari {{ g.total }} kandidat
                                    <template v-if="g.gagal"> · <i class="is-gagal">{{ g.gagal }} gagal</i></template>
                                </span>
                                <span class="pp-row__rail">
                                    <span class="pp-row__fill" :style="{ width: persenGrup(g) + '%' }"></span>
                                </span>
                            </span>
                            <span class="pp-row__persen">{{ persenGrup(g) }}%</span>
                            <i class="bi pp-row__caret" :class="buka === g.id ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </button>

                        <div v-if="buka === g.id" class="pp-row__body">
                            <div class="pp-tabs">
                                <button
                                    v-for="t in tab(g)" :key="t.kode"
                                    type="button" class="pp-tab" :class="{ 'is-on': tabAktif === t.kode }"
                                    @click="tabAktif = t.kode"
                                >
                                    {{ t.label }} <span>{{ t.jumlah }}</span>
                                </button>
                            </div>

                            <div v-if="!orang(g).length" class="pp-kosong">Tidak ada kandidat pada keadaan ini.</div>

                            <div v-else class="pp-orang">
                                <div v-for="o in orang(g)" :key="o.id" class="pp-o" :class="'is-' + o.keadaan">
                                    <span class="pp-o__dot"><i class="bi" :class="ikonOrang(o)"></i></span>
                                    <span class="pp-o__in">
                                        <b>{{ o.nama }}</b>
                                        <em v-if="o.keadaan === 'gagal'" :title="o.pesan">{{ o.pesan || 'Gagal tanpa keterangan' }}</em>
                                        <em v-else-if="o.keadaan === 'selesai'">{{ o.ket || 'Selesai' }}</em>
                                        <em v-else>Menunggu giliran…</em>
                                    </span>
                                    <button
                                        v-if="o.keadaan === 'gagal' && dapatUlang"
                                        type="button" class="pp-o__ulang"
                                        :title="`Coba lagi untuk ${o.nama} saja`"
                                        @click="$emit('ulang-satu', g, o)"
                                    >
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                            </div>

                            <button
                                v-if="dapatUlang && g.gagal > 1"
                                type="button" class="pp-semua"
                                @click="$emit('ulang', g)"
                            >
                                <i class="bi bi-arrow-clockwise"></i>
                                Coba lagi {{ g.gagal }} yang gagal
                            </button>
                        </div>
                    </article>

                    <div v-if="!grup.length" class="pp-kosong">Belum ada pekerjaan berjalan.</div>
                </slot>
            </div>
        </section>
    </transition>
</template>

<script>
export default {
    name: 'PanelProses',
    props: {
        tampil: { type: Boolean, default: false },
        judul: { type: String, default: 'Sedang diproses' },
        ringkas: { type: String, default: '' },
        /** jalan | ok | separuh | gagal — mewarnai ikon & bilah. */
        nada: { type: String, default: 'jalan' },
        persen: { type: Number, default: 0 },
        /** Masih ada yang berjalan? Menyalakan kilau & mengubah judul tombol tutup. */
        jalan: { type: Boolean, default: false },
        dapatTutup: { type: Boolean, default: true },
        dapatUlang: { type: Boolean, default: false },
        /** [{ id, judul, sub, total, selesai, gagal, menunggu, baris: [...] }] */
        grup: { type: Array, default: () => [] },
    },
    emits: ['tutup', 'ulang', 'ulang-satu'],
    data() {
        return {
            lipat: false,
            buka: null,
            tabAktif: 'SEMUA',
        };
    },
    computed: {
        ikon() {
            if (this.nada === 'gagal') return 'bi-exclamation-triangle-fill';
            if (this.nada === 'ok') return 'bi-check-circle-fill';

            return this.jalan ? 'bi-hourglass-split' : 'bi-info-circle-fill';
        },
    },
    watch: {
        // Satu-satunya grup dibuka sendiri: menuntut admin mengklik untuk
        // melihat isi yang cuma satu-satunya sama saja dengan menyembunyikannya.
        grup: {
            immediate: true,
            handler(v) {
                if (v.length === 1 && this.buka === null) this.buka = v[0].id;
            },
        },
    },
    methods: {
        toggle(g) {
            this.buka = this.buka === g.id ? null : g.id;
            this.tabAktif = 'SEMUA';
        },
        persenGrup(g) {
            const selesai = (g.selesai || 0) + (g.gagal || 0);

            return g.total ? Math.round((selesai / g.total) * 100) : 0;
        },
        nadaGrup(g) {
            if (g.menunggu > 0) return 'jalan';
            if (g.gagal && !g.selesai) return 'gagal';

            return g.gagal ? 'separuh' : 'ok';
        },
        ikonGrup(g) {
            const n = this.nadaGrup(g);

            return n === 'jalan' ? 'bi-hourglass-split' : (n === 'ok' ? 'bi-check-lg' : 'bi-exclamation-lg');
        },
        ikonOrang(o) {
            if (o.keadaan === 'gagal') return 'bi-exclamation-lg';

            return o.keadaan === 'selesai' ? 'bi-check-lg' : 'bi-three-dots';
        },
        tab(g) {
            const b = g.baris || [];

            return [
                { kode: 'SEMUA', label: 'Semua', jumlah: b.length },
                { kode: 'gagal', label: 'Gagal', jumlah: b.filter((x) => x.keadaan === 'gagal').length },
                { kode: 'menunggu', label: 'Menunggu', jumlah: b.filter((x) => x.keadaan === 'menunggu').length },
                { kode: 'selesai', label: 'Selesai', jumlah: b.filter((x) => x.keadaan === 'selesai').length },
            ];
        },
        orang(g) {
            const b = g.baris || [];

            // YANG GAGAL SELALU DI ATAS. Pada gelombang 150 orang, tiga baris
            // merah di posisi ke-90 sama saja dengan tidak ada — dan hanya
            // ketiganya yang menuntut tindakan.
            const urut = { gagal: 0, menunggu: 1, selesai: 2 };

            return (this.tabAktif === 'SEMUA' ? b : b.filter((x) => x.keadaan === this.tabAktif))
                .slice()
                .sort((x, y) => (urut[x.keadaan] ?? 9) - (urut[y.keadaan] ?? 9));
        },
    },
};
</script>

<style scoped>
/* Rupa panel ini SENGAJA sama persis dengan panel antrean penjadwalan —
   posisi, lebar, warna kepala, dan bilah kemajuannya. Dua pekerjaan berbeda
   yang sama-sama berjalan di latar tidak boleh tampil sebagai dua jenis benda. */
.pp {
    position: fixed; right: 20px; bottom: 20px; z-index: 1080;
    width: 380px; max-width: calc(100vw - 32px);
    background: #fff; border: 1px solid #e2e5f0; border-radius: 14px;
    box-shadow: 0 18px 44px rgba(15, 23, 42, .18), 0 2px 6px rgba(15, 23, 42, .06);
    overflow: hidden; display: flex; flex-direction: column;
}
.pp__head { display: flex; align-items: center; gap: 10px; padding: 11px 12px; cursor: pointer; background: #1e1b4b; }
.pp__ico { flex: none; width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; font-size: 13px; color: #fff; }
.pp__ico.is-jalan { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pp__ico.is-ok { background: linear-gradient(135deg, #34d399, #10b981); }
.pp__ico.is-separuh { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.pp__ico.is-gagal { background: linear-gradient(135deg, #f87171, #ef4444); }
.pp__ttl { flex: 1; min-width: 0; }
.pp__ttl b { display: block; font-size: 12.5px; font-weight: 800; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pp__ttl small { display: block; font-size: 11px; color: #a5b4fc; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pp__btn { appearance: none; flex: none; width: 26px; height: 26px; border-radius: 7px; border: none; background: transparent; color: #c7d2fe; cursor: pointer; display: grid; place-items: center; font-size: 11px; transition: background .16s, color .16s; }
.pp__btn:hover { background: rgba(255, 255, 255, .12); color: #fff; }

.pp__rail { position: relative; height: 3px; background: #312e81; overflow: hidden; }
.pp__fill { display: block; height: 100%; background: linear-gradient(90deg, #a78bfa, #6366f1); transition: width .25s ease; }
.pp__fill.is-gagal { background: linear-gradient(90deg, #fca5a5, #ef4444); }
.pp__kilau { position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .55), transparent); transform: translateX(-100%); animation: ppKilau 1.5s ease-in-out infinite; }
@keyframes ppKilau { to { transform: translateX(100%); } }

.pp__isi { max-height: 62vh; overflow-y: auto; overscroll-behavior: contain; background: #fbfbfe; }
.pp__isi::-webkit-scrollbar { width: 6px; }
.pp__isi::-webkit-scrollbar-thumb { background: #d9def0; border-radius: 999px; }

.pp-row { border-bottom: 1px solid #eef0f7; }
.pp-row:last-child { border-bottom: none; }
.pp-row.is-buka { background: #fff; }
.pp-row__head { appearance: none; width: 100%; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: none; background: transparent; font: inherit; text-align: left; cursor: pointer; transition: background .14s; }
.pp-row__head:hover { background: #f5f3ff; }
.pp-row__ico { flex: none; width: 24px; height: 24px; border-radius: 7px; display: grid; place-items: center; font-size: 11px; color: #fff; }
.pp-row__ico.is-jalan { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pp-row__ico.is-ok { background: linear-gradient(135deg, #34d399, #10b981); }
.pp-row__ico.is-separuh { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.pp-row__ico.is-gagal { background: linear-gradient(135deg, #f87171, #ef4444); }
.pp-row__in { flex: 1; min-width: 0; }
.pp-row__ttl { display: flex; align-items: baseline; gap: 7px; min-width: 0; }
.pp-row__ttl b { font-size: 12px; font-weight: 800; color: #1e293b; flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pp-row__ttl em { font-style: normal; font-size: 11px; color: #94a3b8; flex: 0 1 auto; max-width: 45%; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pp-row__meta { display: block; font-size: 10.5px; color: #64748b; margin-top: 2px; }
.pp-row__meta b { color: #4338ca; font-weight: 800; }
.pp-row__meta .is-gagal { font-style: normal; color: #dc2626; font-weight: 700; }
.pp-row__rail { display: block; height: 4px; border-radius: 99px; background: #eef0f7; margin-top: 6px; overflow: hidden; }
.pp-row__fill { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); transition: width .25s ease; }
.pp-row__persen { flex: none; font-size: 11.5px; font-weight: 800; color: #4338ca; font-variant-numeric: tabular-nums; min-width: 34px; text-align: right; }
.pp-row__caret { flex: none; font-size: 10px; color: #a2a9ba; }

.pp-row__body { padding: 0 12px 11px; }
.pp-tabs { display: flex; gap: 5px; margin-bottom: 8px; }
.pp-tab { appearance: none; flex: 1; border: 1px solid #e6e9f3; background: #fff; border-radius: 8px; padding: 5px 7px; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800; color: #64748b; transition: all .15s; }
.pp-tab:hover { border-color: #c7d2fe; color: #4f46e5; }
.pp-tab.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.pp-tab span { opacity: .75; }
.pp-orang { max-height: 220px; overflow-y: auto; overscroll-behavior: contain; display: flex; flex-direction: column; gap: 5px; }
.pp-orang::-webkit-scrollbar { width: 5px; }
.pp-orang::-webkit-scrollbar-thumb { background: #dfe3f0; border-radius: 999px; }
.pp-o { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 9px; background: #fbfbfe; border: 1px solid #eef0f7; }
.pp-o.is-gagal { background: #fef2f2; border-color: #fecaca; }
.pp-o.is-selesai { background: #f0fdf4; border-color: #bbf7d0; }
.pp-o__dot { flex: none; width: 18px; height: 18px; border-radius: 5px; display: grid; place-items: center; font-size: 9px; color: #fff; background: #94a3b8; }
.pp-o.is-gagal .pp-o__dot { background: #ef4444; }
.pp-o.is-selesai .pp-o__dot { background: #10b981; }
.pp-o.is-menunggu .pp-o__dot { background: #cbd5e1; color: #475569; }
.pp-o__in { flex: 1; min-width: 0; }
.pp-kosong { padding: 14px 8px; text-align: center; font-size: 11.5px; color: #a2a9ba; }
.pp-o__in b { display: block; font-size: 11.5px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
/* Sebab kegagalan dipotong DUA BARIS, bukan satu: kalimat galat kerap menyebut
   tindakan yang harus diambil di ujungnya, dan elipsis pada baris pertama
   justru membuang bagian yang berguna. */
.pp-o__in em { display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-style: normal; font-size: 10px; color: #94a3b8; margin-top: 1px; line-height: 1.4; }
.pp-o.is-gagal .pp-o__in em { color: #b91c1c; }
.pp-o__ulang { appearance: none; flex: none; width: 24px; height: 24px; border-radius: 7px; border: 1px solid #fecaca; background: #fff; color: #dc2626; cursor: pointer; display: grid; place-items: center; font-size: 11px; transition: all .15s; }
.pp-o__ulang:hover { background: #dc2626; color: #fff; border-color: #dc2626; }
.pp-semua { appearance: none; width: 100%; margin-top: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 7px; border-radius: 9px; border: 1px solid #fecaca; background: #fff; color: #b91c1c; cursor: pointer; font: inherit; font-size: 11.5px; font-weight: 800; transition: all .15s; }
.pp-semua:hover { background: #fef2f2; border-color: #f87171; }
.pp-pop-enter-active, .pp-pop-leave-active { transition: opacity .22s, transform .22s cubic-bezier(.22, 1, .36, 1); }
.pp-pop-enter-from, .pp-pop-leave-to { opacity: 0; transform: translateY(14px) scale(.97); }

@media (max-width: 560px) {
    .pp { right: 12px; left: 12px; bottom: 12px; width: auto; }
    .pp__isi { max-height: 55vh; }
}
@media (prefers-reduced-motion: reduce) {
    .pp__kilau { animation: none; opacity: 0; }
}
</style>
