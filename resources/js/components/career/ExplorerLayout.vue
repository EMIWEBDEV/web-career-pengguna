<!--
    KERANGKA JELAJAH — daftar di kiri, isinya di kanan.

    ══ KENAPA BENTUK INI ══════════════════════════════════════════════════════

    Bentuk sebelumnya adalah akordion: satu daftar kartu memanjang ke bawah,
    dan menekan sebuah kartu membuka rinciannya DI DALAM daftar itu. Tiga hal
    rusak karenanya, dan ketiganya makin parah begitu datanya bertambah:

      1. Membuka satu program mendorong seluruh program di bawahnya jauh ke
         bawah. Setelah membaca rinciannya, orang harus menggulir balik untuk
         menemukan tempatnya semula — dan sering tidak menemukannya.

      2. Rinciannya panjang (syarat, batch, tabel posisi), sehingga hanya satu
         yang praktis bisa dibuka. Membandingkan dua program berarti menutup
         yang satu dan membuka yang lain, mengingat sendiri isi yang barusan
         hilang.

      3. Daftarnya berpaginasi enam-enam. Program yang dicari kerap ada di
         halaman lain, dan tidak ada satu pun tanda ke mana harus menekan.

    Bentuk jelajah menjawab ketiganya sekaligus: daftarnya tetap diam di kiri
    sambil isinya berganti di kanan — pola yang sama dengan penjelajah berkas,
    kotak surel, dan hampir setiap alat yang dipakai orang setiap hari. Yang
    dipilih tetap terlihat, dan berpindah antar butir tidak memindahkan apa pun
    di layar selain isi panel kanan.

    ══ DI LAYAR SEMPIT ════════════════════════════════════════════════════════

    Dua panel berdampingan menuntut lebar yang tidak dimiliki ponsel. Di bawah
    1024px keduanya jadi SATU tumpukan: daftarnya penuh selebar layar, dan
    memilih satu butir menggantinya dengan panel isi berikut tombol kembali.
    Itu bukan tata letak lain — itu pola yang sama, dilipat.

    ══ CARA DIPAKAI ═══════════════════════════════════════════════════════════

        <ExplorerLayout :items="rows" :selected-id="sel" item-key="id"
                        :loading="loading" @select="sel = $event">
            <template #aside-head> …pencarian, jumlah… </template>
            <template #item="{ item, active }"> …satu baris daftar… </template>
            <template #detail-head="{ item }"> …judul + tombol aksi… </template>
            <template #detail="{ item }"> …isi panel kanan… </template>
        </ExplorerLayout>
-->
<template>
    <div class="exp" :class="{ 'is-detail': sempitDetail }">
        <!-- ══ PANEL KIRI — DAFTAR ═══════════════════════════════════════════ -->
        <aside class="exp__aside">
            <div class="exp__asidehead">
                <slot name="aside-head" />
            </div>

            <!--
                Navigasi papan tik. Daftar panjang yang hanya bisa ditelusuri
                dengan tetikus memaksa orang melepas tangan dari papan tik untuk
                setiap langkah — padahal yang ia lakukan cuma "berikutnya".
            -->
            <div
                ref="daftar"
                v-loading="loading"
                class="exp__list"
                role="listbox"
                tabindex="0"
                :aria-activedescendant="selectedId ? `exp-opt-${selectedId}` : undefined"
                @keydown.down.prevent="geser(1)"
                @keydown.up.prevent="geser(-1)"
                @keydown.home.prevent="keUjung(0)"
                @keydown.end.prevent="keUjung(-1)"
            >
                <button
                    v-for="it in items"
                    :id="`exp-opt-${it[itemKey]}`"
                    :key="it[itemKey]"
                    type="button"
                    role="option"
                    :aria-selected="it[itemKey] === selectedId"
                    class="exp__item"
                    :class="{ 'is-on': it[itemKey] === selectedId }"
                    @click="$emit('select', it[itemKey])"
                >
                    <slot name="item" :item="it" :active="it[itemKey] === selectedId" />
                </button>

                <div v-if="!loading && !items.length" class="exp__empty">
                    <slot name="empty"><i class="bi bi-inbox"></i> Tidak ada data.</slot>
                </div>
            </div>

            <div v-if="$slots['aside-foot']" class="exp__asidefoot">
                <slot name="aside-foot" />
            </div>
        </aside>

        <!-- ══ PANEL KANAN — ISI ═════════════════════════════════════════════ -->
        <section class="exp__main">
            <template v-if="terpilih">
                <header class="exp__mainhead">
                    <!-- Hanya di layar sempit: di layar lebar daftarnya tidak
                         pernah hilang, jadi tidak ada yang perlu "dikembalikan". -->
                    <button type="button" class="exp__back" @click="$emit('select', null)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <slot name="detail-head" :item="terpilih" />
                </header>
                <div class="exp__mainbody">
                    <slot name="detail" :item="terpilih" />
                </div>
            </template>

            <!-- Belum ada yang dipilih. Bukan panel kosong tanpa kata: layar
                 kosong tanpa keterangan terbaca sebagai kegagalan memuat. -->
            <div v-else class="exp__none">
                <slot name="none">
                    <span class="exp__none-ico"><i class="bi bi-hand-index-thumb"></i></span>
                    <strong>Pilih salah satu di sebelah kiri</strong>
                    <small>Rinciannya akan tampil di sini.</small>
                </slot>
            </div>
        </section>
    </div>
</template>

<script>
export default {
    name: 'ExplorerLayout',
    props: {
        items: { type: Array, default: () => [] },
        /** Nama properti yang jadi identitas tiap butir. */
        itemKey: { type: String, default: 'id' },
        selectedId: { type: [String, Number], default: null },
        loading: { type: Boolean, default: false },
    },
    emits: ['select'],
    computed: {
        terpilih() {
            if (this.selectedId === null || this.selectedId === undefined) return null;

            return this.items.find((x) => x[this.itemKey] === this.selectedId) || null;
        },
        /** Di layar sempit: panel isi menggantikan daftar, bukan mendampinginya. */
        sempitDetail() {
            return !!this.terpilih;
        },
    },
    methods: {
        geser(arah) {
            if (!this.items.length) return;

            const i = this.items.findIndex((x) => x[this.itemKey] === this.selectedId);
            // Belum ada yang dipilih → mulai dari ujung yang sesuai arah, bukan
            // dari indeks -1 yang akan melompat ke butir kedua.
            const next = i < 0
                ? (arah > 0 ? 0 : this.items.length - 1)
                : Math.min(this.items.length - 1, Math.max(0, i + arah));

            this.$emit('select', this.items[next][this.itemKey]);
            this.$nextTick(() => this.tampakkan(next));
        },
        keUjung(pos) {
            if (!this.items.length) return;

            const i = pos < 0 ? this.items.length - 1 : 0;
            this.$emit('select', this.items[i][this.itemKey]);
            this.$nextTick(() => this.tampakkan(i));
        },
        tampakkan(i) {
            this.$refs.daftar?.children?.[i]?.scrollIntoView({ block: 'nearest' });
        },
    },
};
</script>

<style scoped>
/* ── KERANGKA ────────────────────────────────────────────────────────────── */
.exp {
    display: grid;
    grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
    gap: 16px;
    align-items: start;
    margin-top: 18px;
}

/* Kedua panel MENGGULIR SENDIRI, bukan halamannya.
   Tanpa ini daftar 40 butir memanjangkan halaman sampai panel kanan — yang
   isinya sedang dibaca — hanyut jauh ke atas layar. */
.exp__aside,
.exp__main {
    display: flex;
    flex-direction: column;
    min-width: 0;
    max-height: calc(100vh - 132px);
    border: 1px solid #e8eaf4;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(15, 23, 42, .05);
    overflow: hidden;
}

/* ── PANEL KIRI ──────────────────────────────────────────────────────────── */
.exp__asidehead {
    flex: none;
    padding: 13px 14px;
    border-bottom: 1px solid #eef0f7;
    background: linear-gradient(180deg, #fbfbfe, #fff);
}
.exp__list {
    flex: 1;
    min-height: 120px;
    overflow-y: auto;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.exp__list:focus-visible {
    outline: 2px solid #a5b4fc;
    outline-offset: -2px;
}
.exp__item {
    appearance: none;
    display: block;
    width: 100%;
    text-align: left;
    font: inherit;
    color: inherit;
    cursor: pointer;
    padding: 10px 11px;
    border: 1px solid transparent;
    border-radius: 12px;
    background: transparent;
    transition: background .14s, border-color .14s;
}
.exp__item:hover {
    background: #f7f8fd;
}
/* Terpilih ditandai LATAR + GARIS, bukan warna teks saja: pada daftar padat
   satu kata berwarna terlalu mudah luput, dan orang kehilangan jejak di mana
   ia berada setiap kali matanya kembali dari panel kanan. */
.exp__item.is-on {
    background: #eef2ff;
    border-color: #c7d2fe;
}
.exp__empty {
    padding: 34px 16px;
    text-align: center;
    font-size: 12.5px;
    font-weight: 600;
    color: #94a3b8;
}
.exp__empty .bi {
    display: block;
    font-size: 22px;
    margin-bottom: 7px;
    color: #cbd5e1;
}
.exp__asidefoot {
    flex: none;
    padding: 9px 12px;
    border-top: 1px solid #eef0f7;
    background: #fbfbfe;
}

/* ── PANEL KANAN ─────────────────────────────────────────────────────────── */
.exp__mainhead {
    flex: none;
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 13px 16px;
    border-bottom: 1px solid #eef0f7;
    background: linear-gradient(180deg, #fbfbfe, #fff);
}
.exp__mainbody {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
}
.exp__back {
    display: none;
    flex: none;
    appearance: none;
    width: 32px;
    height: 32px;
    border: 1px solid #e6e9f3;
    border-radius: 10px;
    background: #fff;
    color: #475569;
    cursor: pointer;
}
.exp__none {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 48px 20px;
    text-align: center;
    min-height: 280px;
}
.exp__none-ico {
    display: grid;
    place-items: center;
    width: 52px;
    height: 52px;
    margin-bottom: 6px;
    border-radius: 16px;
    background: #eef2ff;
    color: #6366f1;
    font-size: 21px;
}
.exp__none strong {
    font-size: 14px;
    font-weight: 800;
    color: #334155;
}
.exp__none small {
    font-size: 12.5px;
    color: #94a3b8;
}

/* ── LAYAR SEMPIT: SATU TUMPUKAN ─────────────────────────────────────────── */
@media (max-width: 1023px) {
    .exp {
        grid-template-columns: 1fr;
    }
    .exp__aside,
    .exp__main {
        max-height: none;
    }
    /* Panel isi hanya digambar setelah ada yang dipilih; sebelum itu ajakan
       "pilih di sebelah kiri" tidak berlaku — tidak ada sebelah kiri. */
    .exp__main {
        display: none;
    }
    .exp.is-detail .exp__aside {
        display: none;
    }
    .exp.is-detail .exp__main {
        display: flex;
    }
    .exp__back {
        display: grid;
        place-items: center;
    }
    .exp__list {
        max-height: 60vh;
    }
}
</style>
