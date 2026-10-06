<script setup>
/**
 * PANEL TENGGAT SLA MPP — dashboard monitoring.
 *
 * Menjawab satu pertanyaan yang selama ini hanya bisa dijawab dengan membuka
 * halaman MPP satu per satu: "MPP mana yang tenggatnya sudah mepet?"
 *
 * ── YANG TIDAK DIHITUNG DI SINI ────────────────────────────────────────────
 *
 * Sisa hari, nada, dan labelnya semua datang dari server (SlaMpp::keadaan).
 * Menghitungnya ulang di sini akan membuat dashboard, worklist, dan kartu MPP
 * bisa menyebut angka berbeda untuk MPP yang sama — dan yang paling berbahaya
 * bukan selisihnya, melainkan bahwa tak seorang pun tahu mana yang benar.
 *
 * Yang ditampilkan hanya MPP REKRUTMEN yang kursinya BELUM penuh; keduanya
 * disaring server.
 */
defineProps({
    /** @type {{lewat:number, genting:number, waspada:number, aman:number}} */
    ringkas: { type: Object, default: () => ({ lewat: 0, genting: 0, waspada: 0, aman: 0 }) },
    baris: { type: Array, default: () => [] },
});

const NADA = {
    lewat: { label: 'Lewat tenggat', ikon: 'bi-exclamation-octagon-fill' },
    'hari-ini': { label: 'Jatuh tempo hari ini', ikon: 'bi-alarm-fill' },
    genting: { label: 'Genting', ikon: 'bi-hourglass-bottom' },
    waspada: { label: 'Waspada', ikon: 'bi-hourglass-split' },
    aman: { label: 'Aman', ikon: 'bi-hourglass-top' },
};

const tglId = (t) => {
    if (!t) return '—';
    const d = new Date(t);

    return Number.isNaN(d.getTime())
        ? t
        : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<template>
    <div class="wsla">
        <!-- Ringkasan dulu: yang dibaca sekilas adalah "ada berapa yang
             mendesak", bukan daftarnya. -->
        <div class="wsla__ring">
            <div class="wsla__kartu is-lewat">
                <b>{{ ringkas.lewat || 0 }}</b>
                <span>Lewat tenggat</span>
            </div>
            <div class="wsla__kartu is-genting">
                <b>{{ ringkas.genting || 0 }}</b>
                <span>≤ 3 hari kerja</span>
            </div>
            <div class="wsla__kartu is-waspada">
                <b>{{ ringkas.waspada || 0 }}</b>
                <span>≤ 7 hari kerja</span>
            </div>
            <div class="wsla__kartu is-aman">
                <b>{{ ringkas.aman || 0 }}</b>
                <span>Masih longgar</span>
            </div>
        </div>

        <p v-if="!baris.length" class="wsla__kosong">
            <i class="bi bi-check2-circle"></i>
            Tidak ada MPP berjalan yang tenggatnya perlu dikejar.
        </p>

        <table v-else class="wsla__tbl">
            <thead>
                <tr>
                    <th style="width: 30%">MPP &amp; Posisi</th>
                    <th style="width: 14%">Kursi</th>
                    <th style="width: 20%">Tenggat</th>
                    <th>Sisa</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in baris" :key="r.mpp" :class="'is-' + r.nada">
                    <td>
                        <b>{{ r.posisi }}</b>
                        <small>{{ r.mpp }}<template v-if="r.departemen"> · {{ r.departemen }}</template></small>
                    </td>
                    <td class="wsla__kursi">
                        {{ r.terisi }} / {{ r.kuota }}
                    </td>
                    <td>
                        {{ tglId(r.batas) }}
                        <!-- Tenggat asli ikut disebut supaya perpanjangan tidak
                             menghapus jejak janji semula. -->
                        <small v-if="r.perpanjanganKe > 0">
                            diperpanjang {{ r.perpanjanganKe }}× · semula {{ tglId(r.batasAwal) }}
                        </small>
                    </td>
                    <td>
                        <span class="wsla__nada" :class="'is-' + r.nada">
                            <i class="bi" :class="(NADA[r.nada] || NADA.aman).ikon"></i>
                            {{ r.lewat ? `Telat ${Math.abs(r.sisa)} hari kerja` : r.label }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.wsla__ring { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px; }

.wsla__kartu {
    display: flex; flex-direction: column; gap: 2px;
    padding: 11px 13px; border-radius: 11px; border: 1px solid transparent;
}
.wsla__kartu b { font-size: 20px; font-weight: 800; line-height: 1.1; }
.wsla__kartu span { font-size: 11px; font-weight: 600; opacity: .85; }
.wsla__kartu.is-lewat   { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
.wsla__kartu.is-genting { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }
.wsla__kartu.is-waspada { background: #fefce8; border-color: #fde68a; color: #a16207; }
.wsla__kartu.is-aman    { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }

.wsla__kosong {
    display: flex; align-items: center; gap: 8px; margin: 0;
    padding: 14px; border-radius: 10px; background: #f8fafc;
    border: 1px solid #e2e8f0; font-size: 12.5px; color: #64748b;
}
.wsla__kosong .bi { color: #10b981; font-size: 15px; }

.wsla__tbl { width: 100%; border-collapse: collapse; font-size: 12px; }
.wsla__tbl th {
    text-align: left; padding: 7px 10px; background: #f8fafc;
    border-bottom: 1px solid #e2e8f0; font-size: 10.5px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .4px; color: #64748b;
}
.wsla__tbl td { padding: 9px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.wsla__tbl tbody tr.is-lewat   { background: #fffafa; }
.wsla__tbl tbody tr.is-genting { background: #fffcf8; }
.wsla__tbl td b { display: block; font-size: 12.5px; font-weight: 700; color: #0f172a; }
.wsla__tbl td small { display: block; margin-top: 2px; font-size: 10.5px; color: #94a3b8; }
.wsla__kursi { font-variant-numeric: tabular-nums; color: #475569; }

.wsla__nada {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 999px;
    font-size: 11px; font-weight: 700; white-space: nowrap;
}
.wsla__nada.is-lewat    { background: #fee2e2; color: #991b1b; }
.wsla__nada.is-hari-ini { background: #fee2e2; color: #b91c1c; }
.wsla__nada.is-genting  { background: #ffedd5; color: #c2410c; }
.wsla__nada.is-waspada  { background: #fef9c3; color: #a16207; }
.wsla__nada.is-aman     { background: #dcfce7; color: #15803d; }

@media (max-width: 720px) {
    .wsla__ring { grid-template-columns: repeat(2, 1fr); }
}
</style>
