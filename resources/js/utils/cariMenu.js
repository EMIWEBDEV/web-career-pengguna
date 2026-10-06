/**
 * CARI MENU SIDEBAR — pencarian menu murni di peramban.
 * ---------------------------------------------------------------------------
 * Sumbernya prop `navigation` milik AppSidebar, yang SUDAH disaring hak akses
 * di server (NavigasiShell::untukPenggunaSaatIni → LayoutShell::bangun).
 * Karena itu pencarian tidak pernah bertanya ke server, dan mustahil
 * menemukan menu yang tidak boleh dibuka pemakainya: yang dicari hanyalah
 * menu yang memang sudah tergambar di sidebarnya sendiri.
 *
 * CARA MENCOCOKKAN — potongan kata (substring), BUKAN fuzzy. "talent" hanya
 * mengembalikan menu yang memuat "talent", entah di judulnya atau di nama
 * grup/sub-grup tempatnya duduk. Fuzzy memang memaafkan salah ketik, tapi
 * menu yang ikut muncul tanpa alasan yang terlihat membuat pemakai berhenti
 * memercayai hasilnya.
 *
 * Kueri dipecah per kata dan SETIAP kata wajib ada: "master talent" hanya
 * menemukan "Master Talent Acquisition", bukan semua menu berawalan Master.
 * Huruf besar-kecil dan tanda aksen diabaikan.
 *
 * URUTAN — cocok di judul mengalahkan cocok di nama grup; awal judul
 * mengalahkan awal kata; awal kata mengalahkan tengah kata. Pengecualiannya:
 * kueri yang PERSIS nama grup/sub-grup ("seleksi", "phone screening") berarti
 * orang sedang menyebut wadahnya, jadi isi wadah itu naik ke atas. Nilai yang
 * sama kembali ke urutan sidebar, supaya hasilnya tetap bisa ditebak.
 */

const TANDA_AKSEN = /\p{Mn}/gu; // tanda gabung (aksen) yang terlepas setelah NFD
const HURUF_ANGKA = /[\p{L}\p{N}]/u;
const PEMISAH_KATA = /[^\p{L}\p{N}]+/u;

/** Nilai satu kata kueri menurut tempat ia ditemukan. */
const BOBOT = {
    awalJudul: 8,
    awalKataJudul: 5,
    tengahJudul: 2,
    awalKataGrup: 1.5,
    tengahGrup: 1,
};

/**
 * Tambahan untuk kueri UTUH. `namaWadah` sengaja di atas awalKataJudul:
 * "seleksi" harus mendahulukan isi grup Seleksi ketimbang "Master Tahapan
 * Seleksi" beserta sembilan tetangga sub-grupnya.
 */
const BONUS = {
    samaPersis: 10,
    namaWadah: 6,
    awalanJudul: 4,
};

/**
 * Huruf kecil + tanpa aksen, sambil mencatat asal tiap hurufnya.
 *
 * Peta asal itulah yang membuat sorotan tetap jatuh tepat pada teks ASLI:
 * tanpa peta, satu huruf asli yang dilipat menjadi nol atau dua huruf
 * menggeser seluruh sorotan sesudahnya.
 */
function lipat(teks) {
    const asli = String(teks ?? '');
    let hasil = '';
    const peta = [];

    for (let i = 0; i < asli.length; i++) {
        const huruf = asli[i].normalize('NFD').replace(TANDA_AKSEN, '').toLowerCase();
        for (let j = 0; j < huruf.length; j++) {
            hasil += huruf[j];
            peta.push(i);
        }
    }

    return { asli, teks: hasil, peta };
}

/**
 * Kata-katanya saja, dipisah satu spasi — bentuk pembanding kueri utuh.
 * Tanda baca dibuang di kedua sisi, jadi "kemitraan mou" tetap sama persis
 * dengan "Kemitraan / MoU".
 */
function rataKata(teksLipat) {
    return teksLipat.split(PEMISAH_KATA).filter(Boolean).join(' ');
}

/** Posisi kata di dalam teks — kemunculan di awal kata diutamakan. */
function temukan(teks, kata) {
    let pertama = -1;
    for (let p = teks.indexOf(kata); p !== -1; p = teks.indexOf(kata, p + 1)) {
        if (p === 0 || !HURUF_ANGKA.test(teks[p - 1])) return { p, awalKata: true };
        if (pertama === -1) pertama = p;
    }

    return pertama === -1 ? null : { p: pertama, awalKata: false };
}

function nilai(entri, kata, utuh) {
    const { judul, jalur, rata } = entri.cari;
    let skor = 0;

    for (const k of kata) {
        const diJudul = temukan(judul.teks, k);
        const diJalur = diJudul ? null : temukan(jalur, k);
        if (diJudul) {
            skor += diJudul.p === 0 ? BOBOT.awalJudul : diJudul.awalKata ? BOBOT.awalKataJudul : BOBOT.tengahJudul;
        } else if (diJalur) {
            skor += diJalur.awalKata ? BOBOT.awalKataGrup : BOBOT.tengahGrup;
        } else {
            return 0; // satu kata saja tidak ada → bukan hasil
        }
    }

    if (rata.judul === utuh) skor += BONUS.samaPersis;
    else if (rata.judul.startsWith(utuh)) skor += BONUS.awalanJudul;
    if (rata.grup === utuh || rata.sub === utuh) skor += BONUS.namaWadah;

    return skor;
}

/**
 * Potong teks asli menjadi [{ t, cocok }] untuk digambar dengan <mark>.
 *
 * Sengaja potongan data, bukan string HTML: judul menu diisi admin lewat
 * Master Menu, dan merangkainya menjadi HTML untuk v-html berarti membuka
 * jalan XSS dari satu kolom teks.
 */
function sorot({ asli, teks, peta }, kata) {
    const rentang = [];
    for (const k of kata) {
        for (let p = teks.indexOf(k); p !== -1; p = teks.indexOf(k, p + 1)) {
            rentang.push([p, p + k.length]);
        }
    }
    if (!rentang.length) return [{ t: asli, cocok: false }];

    // "tal" dan "talent" menyorot huruf yang sama — digabung supaya tidak
    // tergambar sebagai dua <mark> bertumpuk.
    rentang.sort((a, b) => a[0] - b[0]);
    const gabung = [];
    for (const [a, b] of rentang) {
        const ujung = gabung[gabung.length - 1];
        if (ujung && a <= ujung[1]) ujung[1] = Math.max(ujung[1], b);
        else gabung.push([a, b]);
    }

    const potong = [];
    let dari = 0;
    for (const [a, b] of gabung) {
        const awal = Math.max(peta[a], dari);
        const akhir = Math.max(peta[b - 1] + 1, b < peta.length ? peta[b] : asli.length);
        if (akhir <= awal) continue;
        if (awal > dari) potong.push({ t: asli.slice(dari, awal), cocok: false });
        potong.push({ t: asli.slice(awal, akhir), cocok: true });
        dari = akhir;
    }
    if (dari < asli.length) potong.push({ t: asli.slice(dari), cocok: false });

    return potong;
}

/**
 * Ikon menu dari Master Menu apa adanya, dengan dua perbaikan kecil: baris
 * lama ada yang tersimpan "bi-images" tanpa kelas dasar "bi", dan menu tanpa
 * ikon diberi "bi bi-dot" oleh server — titik sekecil itu di tengah ubin ikon
 * tampak seperti ikon yang gagal dimuat, jadi dipakai ikon grupnya.
 */
function rapikanIkon(ikon, cadangan) {
    const k = String(ikon ?? '').trim();
    if (!k || /(^|\s)bi-dot$/.test(k)) return cadangan;

    return /^bi-/.test(k) ? `bi ${k}` : k;
}

function buatGrup(key, title, ikon) {
    const l = lipat(title);

    return { key, title, ikon, lipat: l, rata: rataKata(l.teks) };
}

/**
 * Ratakan navigation menjadi satu daftar yang bisa dicari, berurutan persis
 * seperti sidebar menggambarnya (item langsung dulu, baru sub-grup).
 *
 * Dashboard ikut dicari walau di sidebar ia bukan anggota grup mana pun —
 * LayoutShell memindahkannya ke navigation.home. Mencari "feedback" lalu
 * tidak menemukan Dashboard Feedback justru terasa seperti pencariannya rusak.
 *
 * `ikonGrup(g)` dipinjam dari sidebar supaya label kelompok di hasil memakai
 * ikon yang sama dengan grupnya di menu.
 */
export function susunIndeksMenu(navigation, { ikonGrup = () => 'bi bi-folder2' } = {}) {
    const indeks = [];

    const tambah = (it, grup, sub) => {
        if (!it?.url) return;

        const lipatJudul = lipat(it.title);
        const lipatSub = lipat(sub?.title);
        indeks.push({
            key: `${grup.key}|${it.id ?? ''}|${indeks.length}`,
            urutan: indeks.length,
            title: String(it.title ?? ''),
            url: it.url,
            icon: rapikanIkon(it.icon, grup.ikon),
            isActive: !!it.isActive,
            grup,
            sub: lipatSub.asli,
            cari: {
                judul: lipatJudul,
                sub: lipatSub,
                // Dipisah baris baru supaya satu kata tidak bisa "cocok"
                // dengan menyambung ujung nama grup ke awal nama sub-grup.
                jalur: `${grup.lipat.teks}\n${lipatSub.teks}`,
                rata: {
                    judul: rataKata(lipatJudul.teks),
                    grup: grup.rata,
                    sub: rataKata(lipatSub.teks),
                },
            },
        });
    };

    const home = navigation?.home;
    if (home?.url) {
        const grup = buatGrup('beranda', 'Dashboard', 'bi bi-house-door');
        tambah({ id: 'beranda', title: home.title || 'Dashboard', url: home.url, icon: home.icon, isActive: home.isActive }, grup, null);
        (home.items || []).forEach((it) => tambah(it, grup, null));
    }

    const modul = navigation?.modules || [];
    for (const m of modul) {
        for (const g of m.groups || []) {
            // Nama modul baru perlu disebut bila modulnya lebih dari satu;
            // dengan satu modul ia cuma awalan yang sama di setiap kelompok.
            const judul = modul.length > 1 ? `${m.label || m.name} · ${g.title}` : g.title;
            const grup = buatGrup(`${m.id}:${g.id}`, judul, ikonGrup(g));

            (g.items || []).forEach((it) => tambah(it, grup, null));
            for (const sb of g.subs || []) {
                (sb.items || []).forEach((it) => tambah(it, grup, sb));
            }
        }
    }

    return indeks;
}

/**
 * Jalankan pencarian atas hasil susunIndeksMenu().
 *
 * Pulang null bila kueri kosong (sidebar menggambar menu biasa), atau
 * { total, kelompok, rata }:
 *  - kelompok — hasil per grup; grup yang memuat hasil terbaik di depan;
 *  - rata — seluruh hasil berurutan persis seperti tergambar, dipakai
 *    navigasi ↑/↓. `n` pada tiap hasil adalah indeksnya di sini.
 *
 * Kueri yang isinya hanya tanda baca ("&&") menghasilkan total 0, bukan
 * null: kotak cari berisi sesuatu, jadi sidebar harus bilang "tidak ada"
 * alih-alih diam-diam menggambar menu lengkap.
 */
export function cariMenu(indeks, kueri) {
    const lipatan = lipat(kueri).teks;
    if (!lipatan.trim()) return null;

    const utuh = rataKata(lipatan);
    const kata = [...new Set(utuh.split(' ').filter(Boolean))];
    const cocok = kata.length
        ? indeks.map((e) => ({ e, skor: nilai(e, kata, utuh) })).filter((x) => x.skor > 0)
        : [];
    cocok.sort((a, b) => b.skor - a.skor || a.e.urutan - b.e.urutan);

    const kelompok = [];
    const perGrup = new Map();
    for (const { e } of cocok) {
        let k = perGrup.get(e.grup.key);
        if (!k) {
            k = { key: e.grup.key, title: e.grup.title, ikon: e.grup.ikon, seg: sorot(e.grup.lipat, kata), items: [] };
            perGrup.set(e.grup.key, k);
            kelompok.push(k);
        }
        k.items.push(e);
    }

    const rata = [];
    for (const k of kelompok) {
        k.items = k.items.map((e) => {
            const h = {
                key: e.key,
                n: rata.length,
                title: e.title,
                url: e.url,
                icon: e.icon,
                isActive: e.isActive,
                sub: e.sub,
                segJudul: sorot(e.cari.judul, kata),
                segSub: e.sub ? sorot(e.cari.sub, kata) : [],
            };
            rata.push(h);

            return h;
        });
    }

    return { total: rata.length, kelompok, rata };
}
