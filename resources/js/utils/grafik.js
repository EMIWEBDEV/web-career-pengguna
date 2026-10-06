/*
 * GRAFIK EVO — tema Highcharts + perakit opsi siap pakai.
 * ---------------------------------------------------------------------------
 * Satu tempat untuk SEMUA grafik panel admin, supaya dua halaman yang
 * bersebelahan tidak terlihat digambar oleh dua orang berbeda.
 *
 * KENAPA TEMANYA DIPISAH DARI HALAMAN?
 * Bawaan Highcharts itu abu-abu, bersudut tajam, berjudul di tengah, dan
 * berlabel "Highcharts.com" di pojok — tampilan laporan 2010 yang persis
 * disebut "kaku". Yang membuatnya tidak kaku bukan satu-dua opsi, melainkan
 * belasan: font Inter, sumbu tanpa garis, grid putus-putus tipis, isi area
 * bergradien, garis membulat, tooltip gelap melayang, animasi masuk
 * berurutan. Menulis ulang belasan opsi itu di tiap grafik adalah cara paling
 * pasti agar cepat atau lambat ada yang beda sendiri.
 *
 * Modul heatmap dan funnel diimpor di sini juga — Highcharts inti tidak membawa
 * tipe heatmap maupun pyramid, dan lupa mengimpornya menghasilkan grafik kosong
 * tanpa pesan galat apa pun.
 */

import Highcharts from 'highcharts';
// JALUR `esm/` WAJIB, jangan disingkat jadi 'highcharts/modules/heatmap'.
// Paket highcharts tidak punya peta "exports", jadi jalur pendek itu jatuh ke
// berkas UMD di modules/ yang mencari variabel global `_Highcharts` — variabel
// yang tidak pernah ada di bundel Vite. Akibatnya bukan galat saat build,
// melainkan galat saat halaman dibuka. Berkas di esm/ mengimpor
// ../highcharts.js, instansi yang SAMA dengan baris di atas, sehingga tipe
// heatmap benar-benar terdaftar.
import 'highcharts/esm/modules/heatmap';
// Tipe 'pictorial' (piramida seleksi per loker) juga tidak ada di Highcharts inti.
import 'highcharts/esm/modules/pictorial';

/** Palet EVO — diambil dari berkas rancangan, bukan ditebak. */
export const WARNA = {
    indigo: '#4f46e5',
    indigoMuda: '#818cf8',
    indigoTua: '#4338ca',
    ungu: '#8b5cf6',
    unguMuda: '#a78bfa',
    unguTua: '#7c3aed',
    emas: '#f59e0b',
    emasMuda: '#fbbf24',
    emasTua: '#d97706',
    hijau: '#059669',
    hijauMuda: '#34d399',
    merah: '#dc2626',
    biru: '#2563eb',
    tinta: '#0f172a',
    abu: '#64748b',
    abuMuda: '#94a3b8',
    garis: 'rgba(148,163,184,.2)',
};

/** Deret warna untuk grafik multi-seri (satu garis per loker). */
export const DERET = [
    '#4f46e5', '#8b5cf6', '#d97706', '#2563eb', '#059669',
    '#db2777', '#0891b2', '#7c3aed', '#ca8a04', '#475569',
];

const FONT = "'Inter','Plus Jakarta Sans',system-ui,-apple-system,sans-serif";

let temaTerpasang = false;

/**
 * Pasang tema EVO ke Highcharts. Aman dipanggil berkali-kali — hanya jalan
 * sekali, karena setOptions itu global dan menumpuknya membuang waktu.
 */
export function pasangTemaEvo() {
    if (temaTerpasang) return Highcharts;
    temaTerpasang = true;

    Highcharts.setOptions({
        lang: {
            months: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
            shortMonths: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            weekdays: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
            shortWeekdays: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
            thousandsSep: '.',
            decimalPoint: ',',
            noData: 'Belum ada data',
        },
        credits: { enabled: false },
        title: { text: undefined },
        subtitle: { text: undefined },
        accessibility: { enabled: false },
        chart: {
            backgroundColor: 'transparent',
            style: { fontFamily: FONT },
            spacing: [8, 2, 6, 2],
            animation: { duration: 700, easing: 'easeOutQuart' },
        },
        colors: DERET,
        xAxis: {
            lineWidth: 0,
            tickWidth: 0,
            gridLineWidth: 0,
            labels: { style: { color: WARNA.abuMuda, fontSize: '11px', fontWeight: '700' } },
            crosshair: {
                width: 1.5,
                color: 'rgba(99,102,241,.45)',
                dashStyle: 'ShortDash',
            },
        },
        yAxis: {
            title: { text: undefined },
            gridLineColor: WARNA.garis,
            gridLineDashStyle: 'ShortDot',
            labels: { style: { color: WARNA.abuMuda, fontSize: '11px', fontWeight: '700' } },
        },
        legend: {
            itemStyle: { color: '#334155', fontSize: '11.5px', fontWeight: '700' },
            itemHoverStyle: { color: WARNA.indigo },
            symbolRadius: 3,
            symbolHeight: 9,
            symbolWidth: 9,
        },
        tooltip: {
            backgroundColor: 'rgba(15,23,42,.96)',
            borderWidth: 0,
            borderRadius: 12,
            shadow: { color: 'rgba(15,23,42,.34)', offsetX: 0, offsetY: 10, opacity: 0.32, width: 16 },
            style: { color: '#fff', fontSize: '12px', fontFamily: FONT },
            useHTML: true,
            padding: 0,
            outside: true,
        },
        plotOptions: {
            series: {
                animation: { duration: 800 },
                states: { inactive: { opacity: 0.18 } },
            },
        },
        noData: {
            style: { color: WARNA.abuMuda, fontSize: '12.5px', fontWeight: '700' },
        },
    });

    return Highcharts;
}

/**
 * Isi tooltip — dibungkus fungsi agar seluruh grafik memakai kerangka HTML yang
 * sama: kicker huruf kecil di atas, angka besar di bawah.
 */
export function tooltipEvo(kicker, utama, tambahan = '') {
    return (
        '<div style="padding:9px 13px">'
        + `<div style="font-size:9.5px;font-weight:800;letter-spacing:.11em;color:rgba(226,232,240,.66);text-transform:uppercase">${kicker}</div>`
        + `<div style="margin-top:3px;font-size:14px;font-weight:800">${utama}</div>`
        + (tambahan ? `<div style="margin-top:3px;font-size:11px;color:rgba(226,232,240,.72)">${tambahan}</div>` : '')
        + '</div>'
    );
}

/** Gradien vertikal untuk isi area — dipakai grafik garis besar. */
export function gradienArea(atas, bawah = atas, opasitas = 0.3) {
    return {
        linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
        stops: [
            [0, Highcharts.color(atas).setOpacity(opasitas).get('rgba')],
            [1, Highcharts.color(bawah).setOpacity(0).get('rgba')],
        ],
    };
}

/** Gradien horizontal untuk garis — ungu → indigo, ciri khas layar EVO. */
export function gradienGaris(kiri = WARNA.unguMuda, tengah = '#6366f1', kanan = WARNA.indigo) {
    return {
        linearGradient: { x1: 0, y1: 0, x2: 1, y2: 0 },
        stops: [[0, kiri], [0.55, tengah], [1, kanan]],
    };
}

/**
 * GRAFIK GARIS — "Pelamar Masuk".
 *
 * @param {{kategori: string[], nilai: number[], nama?: string, satuan?: string, judulTip?: (i:number)=>string}} o
 */
export function opsiGaris(o) {
    const nama = o.nama || 'Pelamar';
    const satuan = o.satuan || 'pelamar';

    return {
        chart: { type: 'areaspline', height: o.tinggi || 250, spacing: [10, 4, 4, 0] },
        xAxis: {
            categories: o.kategori,
            tickmarkPlacement: 'on',
            labels: {
                // Deret 30 hari punya 30 label yang saling menimpa sampai tak
                // satu pun terbaca. Highcharts memang bisa melompatinya sendiri,
                // tapi hasilnya tidak menentu; step eksplisit membuatnya tetap.
                step: o.kategori.length > 20 ? 5 : o.kategori.length > 10 ? 2 : 1,
                style: { color: WARNA.abuMuda, fontSize: '11px', fontWeight: '700' },
            },
        },
        yAxis: {
            min: 0,
            allowDecimals: false,
            labels: { style: { color: WARNA.abuMuda, fontSize: '10.5px', fontWeight: '700' } },
        },
        legend: { enabled: false },
        tooltip: {
            shared: true,
            formatter() {
                const t = o.judulTip ? o.judulTip(this.point.index) : String(this.x);

                return tooltipEvo(t, `${Highcharts.numberFormat(this.y, 0, ',', '.')} ${satuan}`);
            },
        },
        plotOptions: {
            areaspline: {
                lineWidth: 3,
                color: gradienGaris(),
                fillColor: gradienArea(WARNA.ungu, '#6366f1', 0.28),
                marker: {
                    enabled: true,
                    radius: 4,
                    fillColor: '#fff',
                    lineColor: WARNA.indigo,
                    lineWidth: 2.5,
                    symbol: 'circle',
                    states: { hover: { radius: 6, lineWidth: 3 } },
                },
                states: { hover: { lineWidth: 3.4, halo: { size: 10, opacity: 0.16 } } },
            },
        },
        series: [{ name: nama, data: o.nilai }],
    };
}

/**
 * HEATMAP — "Pelamar per Loker".
 *
 * Klik satu sel memanggil `o.onKlik(barisIndex, kolomIndex, nilai)`. Itu yang
 * mengubah heatmap dari gambar menjadi cara masuk ke detail loker: sel yang
 * paling gelap adalah loker yang paling diserbu, dan itulah yang ingin dibuka
 * orang berikutnya.
 *
 * @param {{baris: string[], kolom: string[], data: number[][], onKlik?: Function, subBaris?: string[]}} o
 */
export function opsiHeatmap(o) {
    const titik = [];
    o.data.forEach((baris, y) => baris.forEach((v, x) => titik.push([x, y, v])));
    const puncak = Math.max(1, ...titik.map((t) => t[2]));

    return {
        chart: {
            type: 'heatmap',
            height: Math.max(210, 42 + o.baris.length * (o.kolom.length > 14 ? 26 : 34)),
            spacing: [8, 6, 4, 0],
            // Sel bisa diklik, jadi kursornya harus mengatakan begitu.
            events: o.onKlik
                ? {
                    load() {
                        this.series[0]?.points.forEach((p) => p.graphic?.css({ cursor: 'pointer' }));
                    },
                }
                : undefined,
        },
        xAxis: {
            categories: o.kolom,
            opposite: true,
            lineWidth: 0,
            labels: { style: { color: WARNA.abuMuda, fontSize: '10px', fontWeight: '800', letterSpacing: '.06em' } },
        },
        yAxis: {
            categories: o.baris,
            title: { text: undefined },
            reversed: true,
            gridLineWidth: 0,
            labels: {
                style: { color: WARNA.tinta, fontSize: '11.5px', fontWeight: '800', textOverflow: 'ellipsis' },
                // Nama loker bisa panjang; dipotong agar tidak menekan lebar peta.
                formatter() {
                    const s = String(this.value);

                    return s.length > 22 ? `${s.slice(0, 21)}…` : s;
                },
            },
        },
        colorAxis: {
            min: 0,
            max: puncak,
            // Skala indigo EVO: hampir putih di nol, pekat di puncak. Bukan
            // merah-hijau bawaan — buta warna merah-hijau adalah yang paling
            // umum, dan skala satu rona tetap terbaca oleh semua orang.
            stops: [
                [0, '#f1f5ff'],
                [0.25, '#c7d2fe'],
                [0.5, '#a5b4fc'],
                [0.75, '#6366f1'],
                [1, '#4338ca'],
            ],
            labels: { style: { color: WARNA.abuMuda, fontSize: '10px', fontWeight: '700' } },
        },
        legend: {
            align: 'right',
            layout: 'horizontal',
            verticalAlign: 'bottom',
            margin: 4,
            y: 6,
            symbolHeight: 10,
            symbolWidth: 190,
            title: { text: 'INTENSITAS', style: { color: WARNA.abuMuda, fontSize: '9.5px', fontWeight: '800', letterSpacing: '.13em' } },
        },
        tooltip: {
            formatter() {
                const sub = o.subBaris?.[this.point.y];

                return tooltipEvo(
                    `${o.baris[this.point.y]}${sub ? ` · ${sub}` : ''}`,
                    `${this.point.value} pelamar`,
                    `${o.kolom[this.point.x]} · klik untuk detail loker`,
                );
            },
        },
        plotOptions: {
            heatmap: {
                borderWidth: 3,
                borderColor: 'rgba(255,255,255,.95)',
                borderRadius: 8,
                dataLabels: {
                    // Pada 30 kolom, angka di dalam sel tidak muat dan berubah
                    // jadi bubur tinta. Warnanya sendiri sudah menyampaikan
                    // intensitas; angka persisnya tetap ada di tooltip.
                    enabled: o.kolom.length <= 14,
                    style: { fontSize: '10.5px', fontWeight: '800', textOutline: 'none' },
                    formatter() {
                        return this.point.value || '';
                    },
                    color: 'contrast',
                },
                states: { hover: { brightness: 0.06, borderColor: WARNA.indigo } },
                point: o.onKlik
                    ? { events: { click() { o.onKlik(this.y, this.x, this.value); } } }
                    : undefined,
            },
        },
        series: [{ name: 'Pelamar', data: titik }],
    };
}

/**
 * DONUT — "Pemenuhan Kuota" / "Pemenuhan Kursi".
 *
 * @param {{persen: number, terisi: number, kuota: number, label?: string}} o
 */
export function opsiDonat(o) {
    const persen = Math.max(0, Math.min(100, o.persen || 0));

    return {
        chart: { type: 'pie', height: o.tinggi || 210, spacing: [4, 4, 4, 4] },
        tooltip: { enabled: false },
        legend: { enabled: false },
        title: {
            // Angka di tengah lubang donat: yang dicari mata lebih dulu.
            text: `<div style="text-align:center;line-height:1.05">
                     <div style="font-size:26px;font-weight:800;letter-spacing:-.04em;color:${WARNA.tinta}">${persen}%</div>
                     <div style="margin-top:3px;font-size:9.5px;font-weight:800;letter-spacing:.13em;color:${WARNA.abuMuda}">${o.label || 'TERISI'}</div>
                   </div>`,
            useHTML: true,
            align: 'center',
            verticalAlign: 'middle',
            y: 6,
        },
        plotOptions: {
            pie: {
                innerSize: '76%',
                borderWidth: 0,
                dataLabels: { enabled: false },
                startAngle: 0,
                states: { hover: { halo: { size: 0 } } },
            },
        },
        series: [{
            enableMouseTracking: false,
            animation: { duration: 900 },
            data: [
                {
                    y: persen,
                    color: {
                        linearGradient: { x1: 0, y1: 0, x2: 1, y2: 1 },
                        stops: [[0, WARNA.unguMuda], [1, WARNA.indigoTua]],
                    },
                    borderRadius: persen > 0 && persen < 100 ? 8 : 0,
                },
                { y: 100 - persen, color: 'rgba(148,163,184,.2)' },
            ],
        }],
    };
}

/**
 * SPARKLINE kartu metrik — grafik mini tanpa sumbu, tanpa tooltip.
 *
 * @param {{nilai: number[], warna: string}} o
 */
export function opsiSpark(o) {
    return {
        chart: {
            type: 'areaspline',
            height: 44,
            margin: [3, 0, 3, 0],
            spacing: [0, 0, 0, 0],
            backgroundColor: 'transparent',
        },
        xAxis: { visible: false },
        yAxis: { visible: false, min: 0 },
        legend: { enabled: false },
        tooltip: { enabled: false },
        plotOptions: {
            areaspline: {
                lineWidth: 2.2,
                color: o.warna,
                fillColor: gradienArea(o.warna, o.warna, 0.22),
                marker: {
                    enabled: false,
                    states: { hover: { enabled: false } },
                },
                enableMouseTracking: false,
                // Titik terakhir diberi bulatan agar mata tahu di mana "sekarang".
                zoneAxis: 'x',
            },
        },
        series: [{
            data: o.nilai.map((v, i) => (i === o.nilai.length - 1
                ? { y: v, marker: { enabled: true, radius: 3, fillColor: o.warna, lineWidth: 0 } }
                : v)),
        }],
    };
}

/**
 * KOLOM BERTUMPUK — "Pelamar Masuk" tingkat terbitan.
 *
 * ══ KENAPA BUKAN GARIS ══════════════════════════════════════════════════════
 *
 * Satu terbitan membawa banyak MPP. Garis tunggal menjumlahkan semuanya, jadi
 * ia menjawab "berapa" tapi tidak pernah "dari loker mana" — padahal itulah
 * yang menentukan tindakan rekruter. Lebih buruk lagi pada data jarang: kurva
 * halus yang ditarik melewati deretan nol menggambar bukit landai di hari-hari
 * yang sebenarnya NOL pelamar. Kolom tidak bisa berbohong begitu: hari nol
 * adalah kolom yang tidak ada.
 *
 * Tumpukannya membuat tinggi total tetap terbaca sebagai jumlah harian,
 * sementara tiap potongan warna menyebut loker asalnya.
 *
 * @param {{kategori: string[], seri: {nama: string, data: number[]}[], satuan?: string, judulTip?: (i:number)=>string}} o
 */
export function opsiKolomTumpuk(o) {
    const satuan = o.satuan || 'pelamar';
    const banyak = o.seri.length;

    return {
        chart: { type: 'column', height: o.tinggi || 280, spacing: [10, 4, 4, 0] },
        xAxis: {
            categories: o.kategori,
            labels: {
                step: o.kategori.length > 20 ? 3 : 1,
                style: { color: WARNA.abuMuda, fontSize: '11px', fontWeight: '700' },
            },
        },
        yAxis: {
            min: 0,
            allowDecimals: false,
            title: { text: undefined },
            stackLabels: {
                enabled: true,
                style: { color: '#4338ca', fontSize: '10.5px', fontWeight: '800', textOutline: 'none' },
                formatter() {
                    return this.total || '';
                },
            },
            labels: { style: { color: WARNA.abuMuda, fontSize: '10.5px', fontWeight: '700' } },
        },
        legend: {
            // Dua belas loker tidak muat dalam satu baris legenda; digulung,
            // bukan dipotong diam-diam.
            enabled: banyak > 1,
            align: 'left',
            verticalAlign: 'bottom',
            maxHeight: 62,
            navigation: { activeColor: WARNA.indigo, inactiveColor: '#cbd5e1' },
            itemStyle: { color: '#334155', fontSize: '11px', fontWeight: '700' },
        },
        tooltip: {
            shared: true,
            formatter() {
                const t = o.judulTip ? o.judulTip(this.points?.[0]?.point.index ?? 0) : String(this.x);
                const total = (this.points || []).reduce((n, x) => n + x.y, 0);
                const baris = (this.points || [])
                    .filter((x) => x.y > 0)
                    .map((x) => `<div style="display:flex;gap:9px;align-items:center;margin-top:4px">
                            <span style="width:8px;height:8px;border-radius:3px;background:${x.color}"></span>
                            <span style="flex:1;font-size:11px;color:rgba(226,232,240,.86)">${x.series.name}</span>
                            <span style="font-size:11.5px;font-weight:800">${x.y}</span>
                        </div>`)
                    .join('');

                return (
                    '<div style="padding:9px 13px;min-width:170px">'
                    + `<div style="font-size:9.5px;font-weight:800;letter-spacing:.11em;color:rgba(226,232,240,.66);text-transform:uppercase">${t}</div>`
                    + `<div style="margin-top:3px;font-size:14px;font-weight:800">${total} ${satuan}</div>`
                    + (baris || '<div style="margin-top:4px;font-size:11px;color:rgba(226,232,240,.6)">tidak ada lamaran</div>')
                    + '</div>'
                );
            },
        },
        plotOptions: {
            column: {
                stacking: 'normal',
                borderWidth: 0,
                borderRadius: 5,
                pointPadding: 0.06,
                groupPadding: 0.12,
                maxPointWidth: 46,
                states: { hover: { brightness: 0.08 } },
            },
        },
        series: o.seri.map((x, i) => ({
            name: x.nama,
            data: x.data,
            color: DERET[i % DERET.length],
        })),
    };
}

/**
 * Siluet piramida untuk grafik pictorial.
 *
 * Sisinya sedikit CEKUNG, bukan garis lurus. Segitiga polos terbaca seperti
 * bentuk bawaan yang kebetulan terpakai; lengkung tipis ini membuatnya terbaca
 * sebagai corong yang menyempit — persis yang terjadi pada proses seleksi.
 * Puncak dan alasnya dibulatkan supaya sudut lancipnya tidak menusuk tata letak
 * kartu yang serba lengkung.
 */
const SILUET_PIRAMIDA = 'M50 3 Q53 3 54 8 Q72 55 95 93 Q97 99 91 99 L9 99 '
    + 'Q3 99 5 93 Q28 55 46 8 Q47 3 50 3 Z';

/**
 * PIRAMIDA SELEKSI — satu loker, digambar sebagai PICTORIAL.
 *
 * ══ KENAPA PICTORIAL, BUKAN TIPE `pyramid` BAWAAN ═══════════════════════════
 *
 * Tipe `pyramid` menggambar tiap tahap sebagai potongan trapesium yang LEBARNYA
 * mengikuti nilai. Akibatnya bentuk keseluruhannya berubah-ubah: satu loker
 * menghasilkan piramida ramping, loker sebelahnya menghasilkan balok, dan dua
 * panel yang bersebelahan tidak bisa dibandingkan sekilas karena siluetnya
 * sendiri sudah berbeda.
 *
 * Pictorial membalik hubungan itu: SILUETNYA TETAP — selalu piramida yang sama
 * — dan yang berubah hanya seberapa jauh tiap tahap mengisinya. Itu membuat
 * dua loker bisa ditumpuk di kepala dan langsung terbaca mana yang menyempit
 * lebih cepat. Bentuknya juga tidak lagi bergantung pada nilai, jadi tahap
 * bernilai kecil tidak menghilang jadi garis rambut.
 *
 * Tumpukannya dari bawah: tahap pertama di DASAR (paling lebar), tahap terakhir
 * di PUNCAK (paling sempit).
 *
 * @param {{tahap: {label: string, sampai: number, persen: number}[], total: number}} o
 */
export function opsiPictorialAlur(o) {
    const tahap = o.tahap || [];
    const jumlah = tahap.reduce((n, t) => n + (t.sampai || 0), 0);

    // Gradasi indigo: dasar paling muda, puncak paling pekat — makin tinggi,
    // makin sedikit, makin berharga.
    const rona = ['#dbeafe', '#c7d2fe', '#a5b4fc', '#818cf8', '#6366f1', '#4f46e5', '#4338ca', '#3730a3'];
    const n = tahap.length;

    return {
        chart: { type: 'pictorial', height: 330, spacing: [10, 4, 6, 4] },
        xAxis: { visible: false, categories: [''] },
        yAxis: {
            visible: false,
            // Maksimum dikunci ke jumlah tumpukan supaya siluetnya TERISI PENUH.
            // Dibiarkan otomatis, Highcharts menyisakan ruang di atas dan
            // puncak piramidanya terpotong kosong tanpa alasan yang terbaca.
            max: jumlah || 1,
            reversed: false,
            gridLineWidth: 0,
        },
        legend: {
            // Tahap bernilai NOL tidak menghasilkan pita apa pun. Tanpa legenda,
            // tahap yang belum dijalani hilang tanpa jejak dan rekruter mengira
            // alurnya memang cuma sependek itu.
            enabled: true,
            align: 'center',
            verticalAlign: 'bottom',
            maxHeight: 74,
            itemStyle: { color: '#334155', fontSize: '11px', fontWeight: '700' },
            navigation: { activeColor: WARNA.indigo, inactiveColor: '#cbd5e1' },
        },
        tooltip: {
            formatter() {
                const t = tahap[this.series.index] || {};

                return tooltipEvo(
                    `Tahap ${this.series.index + 1} · ${this.series.name}`,
                    `${this.y} pelamar`,
                    `${t.persen ?? 0}% dari tahap terlebar`,
                );
            },
        },
        plotOptions: {
            series: {
                stacking: 'normal',
                borderWidth: 0,
                paths: [{ definition: SILUET_PIRAMIDA }],
                dataLabels: {
                    enabled: true,
                    inside: true,
                    align: 'center',
                    verticalAlign: 'middle',
                    style: { fontSize: '11px', fontWeight: '800', textOutline: 'none' },
                    formatter() {
                        const t = tahap[this.series.index] || {};

                        // Pita paling tipis tidak muat memuat apa pun; angkanya
                        // tetap ada di tooltip dan di legenda.
                        if (!this.y) return null;

                        return `${t.persen ?? 0}%`;
                    },
                    // Pita gelap di puncak butuh teks terang, pita muda di dasar
                    // butuh teks gelap. 'contrast' mengurusnya sendiri.
                    color: 'contrast',
                },
            },
        },
        // Satu seri per tahap, masing-masing satu titik — itulah cara pictorial
        // menumpuk beberapa warna ke dalam satu siluet.
        series: tahap.map((t, i) => ({
            name: `${i + 1}. ${t.label}`,
            data: [t.sampai || 0],
            color: rona[Math.min(Math.round((i / Math.max(1, n - 1)) * (rona.length - 1)), rona.length - 1)],
        })),
    };
}

export default Highcharts;
