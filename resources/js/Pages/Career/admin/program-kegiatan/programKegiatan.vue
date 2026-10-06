<!-- WEB CAREER — Program Kegiatan (Program + Batch + Posisi + Syarat).
     Tata letak mengikuti rancangan `docs/refrences/v1/v2/EVO Career Login Redesign/
     EVO Program Kegiatan.dc.html`. DATA dari DB via /api/v1/program-kegiatan.

     ══ TIDAK ADA SATU PUN ANGKA "TERISI" DI HALAMAN INI ═══════════════════════
     Program Kegiatan adalah MASTER DATA: ia menetapkan posisi apa saja yang ada
     dan berapa kursi yang dibuka. Berapa kursi yang sudah TERISI adalah angka
     berjalan milik sebuah terbitan — ia berubah tiap kali seorang kandidat
     diterima, dan hanya masuk akal dibaca dalam konteks "terbitan mana".
     Menampilkannya di sini melahirkan dua sumber untuk satu angka, dan yang di
     sini adalah yang salah. Tempatnya di Pembukaan Program, dan hanya di sana.

     ══ YANG DIHAPUS DARI PANEL DETAIL POSISI ═════════════════════════════════
     Rancangan memberi tiga tombol pada detail posisi: "Ubah Posisi", "Ganti
     PIC", dan gembok tutup-posisi. Ketiganya tidak dibuat. Mengubah posisi
     tempatnya di borang program (satu pintu, dengan validasi MPP-nya utuh);
     perpindahan PIC tempatnya di Serah Terima, yang memindahkan kandidat
     sekalian — bukan cuma menukar nama di satu baris.
-->
<template>
    <Head title="Program Kegiatan" />
    <div class="prg">
        <!-- ══ KEPALA HALAMAN ═══════════════════════════════════════════════ -->
        <div class="prg-top">
            <div class="prg-top__l">
                <nav class="prg-crumb"><span>Rekrutmen</span><i class="bi bi-chevron-right"></i><span class="on">Program Kegiatan</span></nav>
                <h1 class="prg-title">
                    <span class="prg-title__ico"><i class="bi bi-diagram-3-fill"></i></span>
                    Program Kegiatan
                </h1>
                <p class="prg-lead">
                    Definisikan program secara menyeluruh — isi, alur seleksi, jadwal, posisi, dan kriteria.
                    Satu program menampung <strong>batch</strong>, <strong>posisi/lowongan</strong>, dan <strong>syarat auto-gugur</strong>.
                </p>
            </div>
            <div class="prg-top__act">
                <!-- ══ CARI REKOMENDASI — SELURUH LOKER SAYA ══
                     Rekruter yang akan cuti tidak berpikir per program: ia
                     berpikir "semua yang saya pegang". Lokernya bisa tersebar di
                     beberapa program, dan menuntutnya menemukan sendiri program
                     mana saja yang berisi miliknya membuat satu yang terlewat
                     berarti kandidatnya menunggu orang yang tidak di kantor.

                     Muncul hanya bila ia MEMANG memegang loker. Tombol yang
                     selalu ada lalu membuka daftar kosong cuma melatih orang
                     mengabaikannya. -->
                <!-- Pintasan ke Master Alur, sesuai rancangan. Alur seleksi
                     memang tetangga terdekat halaman ini: hampir setiap program
                     baru butuh memeriksa atau membuat alurnya lebih dulu. -->
                <button class="prg-ghost" @click="gotoAlur"><i class="bi bi-sliders2"></i> Alur Seleksi</button>
                <button v-if="lokerSaya.length" class="prg-ghost prg-ghost--gold" @click="bukaSerahSaya">
                    <i class="bi bi-arrow-left-right"></i> Serah Terima Tugas Saya
                    <span class="prg-ghost__n">{{ lokerSaya.length }}</span>
                </button>
                <button class="prg-cta" @click="openCreate"><i class="bi bi-plus-lg"></i> Buat Program</button>
            </div>
        </div>

        <div class="prg-body">
            <!-- ══ KOLOM KIRI ═══════════════════════════════════════════════
                 Daftar + kartu filter dibungkus satu wadah, bukan ditumpuk di
                 dalam <aside>. .prg-aside memakai backdrop-filter, dan elemen
                 ber-backdrop-filter menjadi containing block bagi keturunan
                 position:fixed — lembar filter di layar sempit akan terkurung
                 di dalam kotak panel alih-alih menempel ke tepi layar. -->
            <aside class="prg-aside" :class="{ 'is-hide': selMobile }">
                <div class="prg-aside__head">
                    <div class="prg-fold">
                        <span class="prg-fold__ico"><i class="bi bi-folder-fill"></i></span>
                        <div class="prg-fold__id">
                            <div class="prg-fold__t">Program</div>
                            <div class="prg-fold__s">{{ list.length }} program · {{ aktif }} berjalan</div>
                        </div>
                        <button class="prg-sort" :title="urutTurun ? 'Terbaru di atas' : 'Terlama di atas'" @click="urutTurun = !urutTurun">
                            <i class="bi" :class="urutTurun ? 'bi-sort-down' : 'bi-sort-up'"></i>
                            {{ urutTurun ? 'Terbaru' : 'Terlama' }}
                        </button>
                    </div>

                    <div class="prg-search">
                        <i class="bi bi-search"></i>
                        <input v-model="filters.q" placeholder="Cari nama / kode program…" @input="cariDebounce" />
                    </div>

                    <div class="prg-scopes">
                        <button v-for="sc in lingkup" :key="sc.kode" class="prg-scope" :class="{ on: cakupan === sc.kode }" @click="cakupan = sc.kode">
                            {{ sc.label }} <span>{{ sc.jml }}</span>
                        </button>
                    </div>

                    <!-- Tab kategori DARI DATABASE, sudah disaring hak akses.
                         Hanya digambar bila memang ada lebih dari satu kategori
                         yang boleh dilihat pengguna ini. -->
                    <div v-if="kategoriTab.length > 1" class="prg-kats">
                        <button class="prg-kat" :class="{ on: tab === '' }" @click="pilihTab('')">
                            <i class="bi bi-grid"></i> Semua <span>{{ totalSemua }}</span>
                        </button>
                        <button v-for="k in kategoriTab" :key="k.kode" class="prg-kat" :class="{ on: tab === k.kode }" @click="pilihTab(k.kode)">
                            <i class="bi" :class="katIkon(k.kode)"></i> {{ k.nama }} <span>{{ k.jumlah }}</span>
                        </button>
                    </div>
                </div>
                <!-- FILTER — bagian dari kartu yang SAMA, bukan kartu kedua.
                     Dua kartu bertumpuk di kolom kiri membuat daftar dan
                     saringannya terbaca seperti dua hal yang tidak berhubungan,
                     padahal yang satu menyaring yang lain.

                     Teleport hanya AKTIF di layar sempit: di sana blok ini pindah
                     ke <body> agar lembar bawahnya menempel ke tepi layar. Tanpa
                     itu ia terkurung di dalam kartu — .prg-aside memakai
                     backdrop-filter, dan elemen ber-backdrop-filter menjadi
                     containing block bagi keturunan position:fixed. -->
                <Teleport to="body" :disabled="!sempit">
                    <div class="prg-filter" :class="{ 'is-open': sheetOpen }">
                        <div class="prg-filter__head">
                            <span class="prg-filter__title"><i class="bi bi-funnel"></i> Filter</span>
                            <div class="prg-filter__act">
                                <button v-if="adaFilter" class="prg-filter__reset" type="button" @click="resetFilter">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                                <button class="prg-filter__close" type="button" aria-label="Tutup" @click="sheetOpen = false">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="prg-filter__grid">
                            <div>
                                <label class="wca-field-lbl">Tanggal Dibuat</label>
                                <el-date-picker
                                    v-model="filters.rentang" type="daterange" value-format="YYYY-MM-DD"
                                    start-placeholder="Mulai" end-placeholder="Akhir" range-separator="—"
                                    style="width:100%" @change="load"
                                />
                            </div>
                            <!-- ══ PEMEGANG LOKER ══════════════════════════
                                 Digambar HANYA bila memang ada yang bisa dipilih.

                                 Lingkup SENDIRI: seluruh yang tampil sudah pasti
                                 miliknya, jadi saringan "hanya saya" tidak
                                 menyaring apa pun — kotak berisi satu pilihan
                                 yang sudah pasti hanya menyisakan pertanyaan apa
                                 gunanya.

                                 Isinya dirakit dari pemegang yang MEMANG muncul di
                                 data yang dimuat — bukan dari daftar karyawan. Satu
                                 divisi di sini berisi seratus orang lebih, dan
                                 sebagian besarnya tidak memegang satu loker pun. -->
                            <div v-if="adaSaringPemegang">
                                <label class="wca-field-lbl">Pemegang Loker</label>
                                <el-select v-model="filters.pic" :placeholder="labelSemuaPemegang" clearable filterable style="width:100%">
                                    <el-option
                                        v-for="r in pemegangOpsi" :key="r.kode"
                                        :label="`${r.nama} (${r.kode})`" :value="r.kode"
                                    />
                                </el-select>
                            </div>
                            <div class="prg-filter__count">
                                <strong>{{ tersaring.length }}</strong> program cocok
                            </div>
                        </div>
                    </div>
                </Teleport>


                <!-- ══ KERANGKA PEMUATAN ═══════════════════════════════════
                     Menggantikan overlay v-loading. Overlay itu digambar DI ATAS
                     daftar; saat daftarnya masih kosong tingginya nol, jadi
                     spinner-nya meluber keluar kartu dan panel kirinya tampak
                     rusak. Kerangka ini justru MENGISI ruang yang sebentar lagi
                     ditempati barisnya, dengan bentuk yang sama — jadi tidak ada
                     yang bergeser saat datanya tiba. -->
                <div v-if="loading" class="prg-list prg-skel">
                    <div v-for="n in 5" :key="n" class="prg-skel__row" :style="{ animationDelay: (n * 0.07) + 's' }">
                        <span class="prg-skel__ico"></span>
                        <span class="prg-skel__body">
                            <span class="prg-skel__bar" style="width: 72%"></span>
                            <span class="prg-skel__bar" style="width: 45%"></span>
                            <span class="prg-skel__bar" style="width: 58%"></span>
                        </span>
                        <span class="prg-skel__num"></span>
                    </div>
                </div>

                <div v-else class="prg-list">
                    <div v-for="g in grup" :key="g.kode" class="prg-group">
                        <div class="prg-group__head">
                            <i class="bi bi-caret-down-fill"></i>
                            <span class="prg-group__lbl">{{ g.label }}</span>
                            <span class="prg-group__n">{{ g.items.length }}</span>
                            <span class="prg-group__line"></span>
                        </div>
                        <button
                            v-for="it in g.items" :key="it.id"
                            class="prg-item" :class="{ on: sel === it.id }"
                            @click="pilih(it.id)"
                        >
                            <span class="prg-item__accent"></span>
                            <span class="prg-item__ico" :class="`is-${nadaStatus(it.status)}`"><i class="bi" :class="statusIkon(it.status)"></i></span>
                            <span class="prg-item__body">
                                <span class="prg-item__r1">
                                    <span class="prg-item__name" :title="it.nama">{{ it.nama || '—' }}</span>
                                    <span class="prg-item__dot" :style="{ background: it.warna || '#6366f1' }"></span>
                                </span>
                                <span class="prg-item__code">{{ it.kode }}</span>
                                <span class="prg-item__r2"><i class="bi bi-diagram-2"></i><span>{{ it.alurNama || it.alur || '—' }}</span></span>
                                <span class="prg-item__r3">
                                    <span class="prg-item__pill is-kat">{{ katLabel(it.kategori) }}</span>
                                    <span class="prg-item__pill" :class="`is-${nadaStatus(it.status)}`">{{ statusLabel(it.status) }}</span>
                                    <span v-if="milikSaya(it)" class="prg-item__pill is-saya"><i class="bi bi-person-fill"></i> Saya</span>
                                </span>
                            </span>
                            <span class="prg-item__num">
                                <span class="prg-item__val">{{ (it.posisi || []).length }}</span>
                                <span class="prg-item__cap">POSISI</span>
                            </span>
                        </button>
                    </div>

                    <div v-if="!tersaring.length" class="prg-none">
                        <span class="prg-none__ico"><i class="bi bi-folder-x"></i></span>
                        <div class="prg-none__t">Tidak ada program</div>
                        <div class="prg-none__s">Ubah kata kunci atau filter statusmu.</div>
                    </div>
                </div>

                <!-- Paginasi, sama seperti Pembukaan Program. Daftar puluhan
                     program butuh cara berpindah halaman; menggulung 40 baris
                     untuk mencari satu program bukan cara orang bekerja. -->
                <div v-if="totalHalaman > 1" class="prg-aside__foot prg-pager">
                    <button class="prg-pager__nav" :disabled="halaman <= 1" aria-label="Halaman sebelumnya" @click="keHalaman(halaman - 1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="prg-pager__nums">
                        <button
                            v-for="(h, i) in halamanTampil" :key="`${h}-${i}`"
                            class="prg-pager__n" :class="{ on: h === halaman, 'is-gap': h === '…' }"
                            :disabled="h === '…'"
                            @click="h !== '…' && keHalaman(h)"
                        >{{ h }}</button>
                    </div>
                    <button class="prg-pager__nav" :disabled="halaman >= totalHalaman" aria-label="Halaman berikutnya" @click="keHalaman(halaman + 1)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <span class="prg-pager__info">{{ rentangBaris }}</span>
                </div>
            </aside>

            <!-- ══ FILTER ═══════════════════════════════════════════════════
                 Di layar lebar kartu biasa di bawah daftar; di layar sempit
                 elemen YANG SAMA jadi lembar bawah yang dibuka tombol
                 mengambang. Satu markup, dua perilaku. -->

            <!-- ══ PANEL KANAN ══════════════════════════════════════════════ -->
            <main v-if="terpilih" class="prg-main">
                <section class="prg-hero">
                    <span class="prg-hero__glow" :style="{ background: `radial-gradient(circle, ${cahayaStatus(terpilih.status)}, transparent 70%)` }"></span>
                    <div class="prg-hero__row">
                        <div class="prg-hero__l">
                            <nav class="prg-hero__crumb">
                                <button class="prg-back" @click="sel = null"><i class="bi bi-chevron-left"></i></button>
                                <i class="bi bi-folder2-open"></i><span>Program</span>
                                <i class="bi bi-chevron-right"></i><span>{{ katLabel(terpilih.kategori) }}</span>
                                <i class="bi bi-chevron-right"></i><span class="on">{{ terpilih.kode }}</span>
                            </nav>
                            <h2 class="prg-hero__title" :title="terpilih.nama">{{ terpilih.nama || '—' }}</h2>
                            <div class="prg-chips">
                                <span class="prg-chip" :class="`is-${nadaStatus(terpilih.status)}`"><span class="prg-chip__dot"></span> {{ statusLabel(terpilih.status) }}</span>
                                <span class="prg-chip is-plain"><i class="bi bi-arrow-repeat"></i> {{ modeLabel(terpilih.mode) }}</span>
                                <span class="prg-chip is-plain"><i class="bi bi-collection"></i> {{ (terpilih.batch || []).length }} batch</span>
                            </div>
                        </div>
                        <div class="prg-hero__act">
                            <button class="prg-pub" :class="{ on: terpilih.status === 'BERJALAN' }" @click="setStatus(terpilih, terpilih.status !== 'BERJALAN')">
                                <span class="prg-pub__sw"><span class="prg-pub__knob"></span></span>
                                {{ terpilih.status === 'BERJALAN' ? 'Aktif' : 'Nonaktif' }}
                            </button>
                            <!-- "Serah Terima" — istilah HR sehari-hari untuk
                                 perpindahan tanggung jawab, dan memang itu yang
                                 terjadi: lokernya pindah tangan berikut seluruh
                                 kandidat yang sedang berjalan di dalamnya. -->
                            <!-- HANYA muncul bila ada loker yang benar-benar
                                 di tangan akun ini. Tombol yang tetap ada
                                 sesudah lokernya diserahkan menjanjikan sesuatu
                                 yang pasti ditolak — dan yang menekannya tidak
                                 punya cara menebak kenapa ditolak. -->
                            <button
                                v-if="lokerSayaDiSini.length"
                                class="prg-ghost prg-ghost--gold"
                                :title="`Pindahkan ${lokerSayaDiSini.length} loker Anda ke rekruter lain`"
                                @click="bukaSerah(terpilih)"
                            ><i class="bi bi-arrow-left-right"></i> Serah Terima</button>
                            <button class="prg-ib" title="Ubah" @click="openEdit(terpilih)"><i class="bi bi-pencil"></i></button>
                            <button class="prg-ib is-danger" title="Hapus" @click="askRemove(terpilih)"><i class="bi bi-trash3"></i></button>
                        </div>
                    </div>

                    <!-- ══ PITA SERAH TERIMA ═══════════════════════════════
                         Muncul saat lokernya TIDAK di tangan akun ini. Yang
                         paling membingungkan bukan kehilangan tombolnya,
                         melainkan hilang tanpa penjelasan — orang mengira
                         haknya dicabut, padahal ia sendiri yang menyerahkan.

                         Alasannya sudah ditulis saat serah terima dan tersimpan
                         di riwayat; di sinilah ia akhirnya dibaca. -->
                    <div v-if="pitaSerah" class="prg-pita" :class="`is-${pitaSerah.jenis}`">
                        <span class="prg-pita__ico"><i class="bi" :class="pitaSerah.ikon"></i></span>
                        <div class="prg-pita__isi">
                            <b>{{ pitaSerah.judul }}</b>
                            <span>{{ pitaSerah.sub }}</span>
                        </div>
                        <el-tooltip v-if="pitaSerah.alasan" placement="bottom-end" :show-after="120" popper-class="skr-pop">
                            <template #content>
                                <div class="prg-pita__tip">
                                    <b>Alasan serah terima</b>
                                    <p>“{{ pitaSerah.alasan }}”</p>
                                    <dl>
                                        <dt>Dari</dt><dd>{{ pitaSerah.dari }}</dd>
                                        <dt>Ke</dt><dd>{{ pitaSerah.ke }}</dd>
                                        <dt>Waktu</dt><dd>{{ fmt(pitaSerah.pada) }}</dd>
                                        <dt>Dicatat</dt><dd>{{ pitaSerah.oleh }}</dd>
                                        <dt>Cara</dt>
                                        <dd>{{ pitaSerah.sumber === 'SENDIRI' ? 'Diserahkan sendiri oleh pemilik lamanya' : 'Dipindahkan admin lewat Master Akun' }}</dd>
                                    </dl>
                                    <p class="prg-pita__tipnote">
                                        Untuk mengerjakannya lagi, <b>{{ pitaSerah.ke }}</b> yang harus
                                        menyerahkannya kembali — atau admin memindahkannya lewat Master Akun.
                                    </p>
                                </div>
                            </template>
                            <button type="button" class="prg-pita__i" aria-label="Kenapa diserahterimakan">
                                <i class="bi bi-info-circle-fill"></i>
                            </button>
                        </el-tooltip>
                    </div>

                    <!-- ══ SEL INFORMASI ═══════════════════════════════════
                         Tiap sel diberi BARIS PENJELAS di bawah nilainya.
                         Sebelum ini sel "Jadwal Kegiatan" mencetak kode mentah
                         dan "Penanggung Jawab" cuma berbunyi "Belum ditugaskan"
                         — dua-duanya membuat orang bertanya "maksudnya apa?"
                         tanpa ada satu pun tempat yang menjawabnya. Sekarang
                         yang kosong menyebutkan AKIBATNYA, bukan cuma
                         ketiadaannya. -->
                    <div class="prg-cells">
                        <div v-for="c in selInfo" :key="c.label" class="prg-cell" :class="{ 'is-hi': c.sorot }">
                            <div class="prg-cell__l">{{ c.label }}</div>
                            <div class="prg-cell__v" :class="{ 'is-off': c.kosong }">
                                <i class="bi" :class="c.ikon" :style="{ color: c.warna }"></i> {{ c.nilai }}
                            </div>
                            <div class="prg-cell__n">{{ c.nota }}</div>
                        </div>
                    </div>
                </section>

                <!-- ══ DAFTAR POSISI ════════════════════════════════════════ -->
                <section v-if="!posisiBuka" class="prg-card prg-card--flush">
                    <div class="prg-lok__head">
                        <div class="prg-lok__id2">
                            <span class="prg-lok__badge"><i class="bi bi-folder2-open"></i></span>
                            <div>
                                <div class="prg-card__t">Posisi / Lowongan</div>
                                <div class="prg-card__s">{{ (terpilih.posisi || []).length }} posisi · {{ totalKuota(terpilih) }} kursi dibuka · klik untuk detail</div>
                            </div>
                        </div>
                        <div class="prg-lok__act">
                            <div class="prg-ranges">
                                <button class="prg-range" :class="{ on: tampilan === 'kartu' }" @click="tampilan = 'kartu'"><i class="bi bi-grid-3x2-gap-fill"></i> Kartu</button>
                                <button class="prg-range" :class="{ on: tampilan === 'daftar' }" @click="tampilan = 'daftar'"><i class="bi bi-list-ul"></i> Daftar</button>
                            </div>
                            <button class="prg-ghost prg-ghost--sm" @click="openEdit(terpilih)"><i class="bi bi-plus-lg"></i> Tambah Posisi</button>
                        </div>
                    </div>

                    <!-- ── KARTU ──────────────────────────────────────────── -->
                    <div v-if="tampilan === 'kartu'" class="prg-grid">
                        <div
                            v-for="(l, i) in terpilih.posisi" :key="l.id || i"
                            class="prg-lcard" role="button" tabindex="0"
                            @click="bukaPosisi(l)" @keyup.enter="bukaPosisi(l)"
                        >
                            <span class="prg-lcard__st" :class="statusLokerKelas(l.status)">{{ l.status || '—' }}</span>
                            <span class="prg-lcard__ico" :style="{ background: gradLoker(i), boxShadow: `0 10px 22px ${ringLoker(i)}` }">
                                <i class="bi" :class="ikonLoker(l)"></i>
                            </span>
                            <div class="prg-lcard__id">
                                <div class="prg-lcard__t" :title="l.posisi">{{ l.posisi }}</div>
                                <div class="prg-lcard__mpp">{{ l.mppRef || 'tanpa MPP' }}</div>
                                <div class="prg-lcard__dept"><i class="bi bi-diagram-2"></i><span>{{ l.departemen || '—' }}</span></div>
                            </div>
                            <div class="prg-lcard__tags">
                                <span class="prg-mini is-hi"><i class="bi bi-people-fill"></i> {{ l.kuota }} kursi</span>
                                <span class="prg-mini" :class="{ 'is-saya': l.picKode && l.picKode === akses.kodeSaya }">
                                    <i class="bi bi-person-badge"></i> {{ l.picKode || 'belum bertuan' }}
                                </span>
                            </div>
                        </div>

                        <div v-if="!(terpilih.posisi || []).length" class="prg-empty prg-empty--span">
                            <i class="bi bi-briefcase"></i>
                            Program ini belum punya posisi. Tambahkan lewat tombol <b>Tambah Posisi</b>.
                        </div>
                    </div>

                    <!-- ── DAFTAR ─────────────────────────────────────────── -->
                    <div v-else class="prg-tblwrap">
                        <table class="prg-tbl">
                            <thead>
                                <tr><th>NAMA POSISI</th><th>NO. MPP</th><th>DEPARTEMEN</th><th>PIC</th><th class="ta-r">KUOTA</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="(l, i) in terpilih.posisi" :key="l.id || i" class="is-klik" @click="bukaPosisi(l)">
                                    <td>
                                        <div class="prg-lok__pos">
                                            <span class="prg-lok__ico" :style="{ background: gradLoker(i) }"><i class="bi" :class="ikonLoker(l)"></i></span>
                                            <div class="prg-lok__id">
                                                <div class="prg-lok__t" :title="l.posisi">
                                                    {{ l.posisi }}
                                                    <span class="prg-lok__st" :class="statusLokerKelas(l.status)">{{ l.status || '—' }}</span>
                                                </div>
                                                <div class="prg-lok__lv">{{ l.level || '—' }} · {{ l.departemen || '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code v-if="l.mppRef" class="prg-mpp">{{ l.mppRef }}</code><span v-else class="prg-manual">manual</span></td>
                                    <td class="prg-td" :title="l.departemen">{{ l.departemen || '—' }}</td>
                                    <td class="prg-td">
                                        <span v-if="l.picKode" class="prg-mini" :class="{ 'is-saya': l.picKode === akses.kodeSaya }"><i class="bi bi-person-badge"></i> {{ l.picKode }}</span>
                                        <span v-else class="prg-manual">belum bertuan</span>
                                    </td>
                                    <td class="ta-r prg-num">{{ l.kuota }}</td>
                                </tr>
                                <tr v-if="!(terpilih.posisi || []).length">
                                    <td colspan="5" class="prg-tbl__empty">Belum ada posisi pada program ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ══ PHONE SCREENING ═══════════════════════════════════
                         Digambar HANYA bila alur program ini memang memuat tahap
                         skrining. Alur tanpa skrining tidak perlu tahu fitur ini
                         ada — kartu kosong berisi "tidak berlaku" cuma menambah
                         satu hal lagi yang harus dilewati mata.

                         Yang ditampilkan sengaja DUA TINGKAT: bawaan untuk
                         seluruh loker, lalu penimpaan per loker. Program berisi
                         50 loker diatur sekali di baris atas; yang benar-benar
                         berbeda saja yang disentuh satu per satu. -->
                    <div v-if="skrAktivitas.length" class="prg-card prg-skr">
                        <div class="prg-card__head">
                            <div>
                                <div class="prg-card__t">
                                    <i class="bi bi-telephone-inbound"></i> Kuesioner Phone Screening
                                </div>
                                <div class="prg-card__s">
                                    Template pertanyaan yang dipakai rekruter saat menskrining pelamar loker ini
                                </div>
                            </div>
                            <span v-if="skrMuat" class="prg-tag">memuat…</span>
                        </div>

                        <div v-for="a in skrAktivitas" :key="a.id" class="prg-skr__akt">
                            <div class="prg-skr__aktHead">
                                <span class="prg-skr__no">{{ a.urutan }}</span>
                                <div>
                                    <b>{{ a.tahap }}</b>
                                    <small v-if="a.label !== a.tahap">{{ a.label }}</small>
                                </div>
                            </div>

                            <!-- Bawaan program -->
                            <div class="prg-skr__baris is-bawaan">
                                <div class="prg-skr__lbl">
                                    <i class="bi bi-diagram-3"></i>
                                    Bawaan seluruh loker
                                </div>
                                <el-select
                                    :model-value="ikatKode(a.id, null)"
                                    placeholder="Belum dipasang — aktivitas jalan tanpa kuesioner"
                                    clearable
                                    filterable
                                    class="skr-sel"
                                    popper-class="skr-pop"
                                    @change="simpanIkat(a.id, null, $event)"
                                >
                                    <el-option
                                        v-for="t in skrTemplate"
                                        :key="t.kode"
                                        :label="`${t.nama}${t.versiTerbit ? ` (v${t.versiTerbit})` : ' — belum terbit'}`"
                                        :value="t.kode"
                                        :disabled="!t.siap"
                                    />
                                </el-select>
                                <button
                                    v-if="ikatKode(a.id, null)"
                                    class="skr-btn skr-btn--sm"
                                    @click="mintaTerapkanSemua(a)"
                                >
                                    <i class="bi bi-arrow-down-up"></i> Terapkan ke semua
                                </button>
                            </div>

                            <!-- Penimpaan per loker -->
                            <button class="prg-skr__toggle" @click="skrBuka === a.id ? (skrBuka = null) : (skrBuka = a.id)">
                                <i class="bi" :class="skrBuka === a.id ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                                Penimpaan per loker
                                <span v-if="jmlTimpa(a.id)" class="prg-skr__badge">{{ jmlTimpa(a.id) }} ditimpa</span>
                                <span v-else class="prg-skr__samar">semua ikut bawaan</span>
                            </button>

                            <div v-if="skrBuka === a.id" class="prg-skr__lokers">
                                <div v-for="l in skrPosisi" :key="l.id" class="prg-skr__baris">
                                    <div class="prg-skr__lbl">
                                        <i class="bi bi-briefcase"></i>
                                        <span :title="l.posisi">{{ l.posisi }}</span>
                                        <code v-if="l.mppRef">{{ l.mppRef }}</code>
                                    </div>
                                    <el-select
                                        :model-value="ikatKode(a.id, l.id)"
                                        :placeholder="warisPlaceholder(a.id)"
                                        clearable
                                        filterable
                                        class="skr-sel"
                                    popper-class="skr-pop"
                                        @change="simpanIkat(a.id, l.id, $event)"
                                    >
                                        <el-option
                                            v-for="t in skrTemplate"
                                            :key="t.kode"
                                            :label="`${t.nama}${t.versiTerbit ? ` (v${t.versiTerbit})` : ' — belum terbit'}`"
                                            :value="t.kode"
                                            :disabled="!t.siap"
                                        />
                                    </el-select>
                                </div>
                                <div v-if="!skrPosisi.length" class="prg-skr__kosong">
                                    Program ini belum punya loker.
                                </div>
                            </div>
                        </div>

                        <!-- Ditulis di kartu, bukan di dokumentasi: inilah yang
                             paling sering disalahpahami, dan salahnya mahal —
                             orang mengira menukar template akan memperbaiki sesi
                             yang sudah telanjur berjalan. -->
                        <p class="prg-skr__nota">
                            <i class="bi bi-snow"></i>
                            Template dibekukan ke pelamar <b>saat ia melamar</b>. Menggantinya di sini hanya
                            berlaku untuk pelamar berikutnya — yang sudah berjalan tetap memakai versi yang
                            berlaku waktu itu.
                        </p>
                    </div>

                    <div class="prg-foot">
                        <span class="prg-foot__by">
                            <span class="prg-foot__av" :style="{ background: terpilih.warna || '#6366f1' }">{{ initials(terpilih.createdBy) }}</span>
                            Dibuat oleh <b>{{ terpilih.createdBy || 'Sistem' }}</b>
                            <span class="prg-foot__at"><i class="bi bi-clock"></i> {{ fmt(terpilih.createdAt) }}</span>
                        </span>
                        <!-- Hanya ditampilkan bila memang PERNAH diubah. Program
                             yang baru dibuat punya Updated_At = Created_At, dan
                             mencetak dua baris berisi waktu yang sama persis
                             hanya menyuruh orang membandingkannya sendiri. -->
                        <span v-if="pernahDiubah" class="prg-foot__by">
                            <i class="bi bi-pencil-square"></i>
                            Diubah oleh <b>{{ terpilih.updatedBy || 'Sistem' }}</b>
                            <span class="prg-foot__at"><i class="bi bi-clock"></i> {{ fmt(terpilih.updatedAt) }}</span>
                        </span>
                    </div>
                </section>

                <!-- ══ DETAIL SATU POSISI ═══════════════════════════════════ -->
                <section v-else v-loading="muatPosisi" class="prg-sec">
                    <div class="prg-hero prg-hero--loker">
                        <span class="prg-hero__glow" style="background: radial-gradient(circle, rgba(99,102,241,.13), transparent 70%)"></span>
                        <div class="prg-dtop">
                            <button class="prg-ghost prg-ghost--sm" @click="tutupPosisi"><i class="bi bi-arrow-left"></i> Daftar posisi</button>
                            <span class="prg-dcrumb">
                                <span>{{ terpilih.kode }}</span><i class="bi bi-chevron-right"></i><span>Posisi</span>
                                <i class="bi bi-chevron-right"></i><span class="on">{{ posisiBuka.mppRef || 'tanpa MPP' }}</span>
                            </span>
                        </div>

                        <!-- TANPA tombol "Ubah Posisi", "Ganti PIC", dan gembok —
                             lihat catatan di kepala berkas. -->
                        <div class="prg-dhead">
                            <span class="prg-dhead__ico" :style="{ background: gradLoker(indexPosisi), boxShadow: `0 14px 30px ${ringLoker(indexPosisi)}` }">
                                <i class="bi" :class="ikonLoker(posisiBuka)"></i>
                            </span>
                            <div class="prg-dhead__id">
                                <h3 :title="posisiBuka.posisi">{{ posisiBuka.posisi }}</h3>
                                <div class="prg-chips">
                                    <span class="prg-chip" :class="statusLokerChip(posisiBuka.status)"><span class="prg-chip__dot"></span> {{ posisiBuka.status || '—' }}</span>
                                    <span v-if="posisiBuka.level" class="prg-chip is-plain">{{ posisiBuka.level }}</span>
                                    <span class="prg-chip is-plain"><i class="bi bi-person-badge"></i> PIC {{ posisiBuka.picKode || 'belum ada' }}</span>
                                    <span class="prg-chip is-plain"><i class="bi bi-people-fill"></i> {{ posisiBuka.kuota }} kursi</span>
                                </div>
                            </div>
                        </div>

                        <div class="prg-fields">
                            <div v-for="f in medanPosisi" :key="f.label" class="prg-field">
                                <div class="prg-field__l">{{ f.label }}</div>
                                <div class="prg-field__v" :class="{ 'is-off': f.kosong }">
                                    <i class="bi" :class="f.ikon" :style="{ color: f.warna }"></i> {{ f.nilai }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ ISI MPP — sama seperti tampilan di Master MPP ══════
                         Rancangan hanya menyediakan "Kriteria Posisi" + deskripsi
                         singkat. Yang dipakai di sini uraian jabatan MPP-nya
                         seutuhnya, karena itulah yang dibaca pelamar di landing
                         dan itu pula yang jadi acuan saat lamarannya diperiksa. -->
                    <div v-if="mppPosisi" class="prg-card">
                        <div class="prg-card__head">
                            <div>
                                <div class="prg-card__t">Isi MPP <code class="prg-mpp">{{ mppPosisi.noTransaksi }}</code></div>
                                <div class="prg-card__s">Uraian jabatan yang tampil di landing publik dan diperiksa saat pelamar submit</div>
                            </div>
                            <span class="prg-tag" :class="mppPosisi.status === 'AKTIF' ? 'is-on' : ''">{{ mppPosisi.status }}</span>
                        </div>

                        <p v-if="mppPosisi.deskripsi" class="prg-desc">{{ mppPosisi.deskripsi }}</p>

                        <div class="prg-two">
                            <div v-if="mppPosisi.tanggungJawab.length" class="prg-poin">
                                <div class="prg-poin__t"><i class="bi bi-list-check"></i> Tanggung Jawab</div>
                                <div v-for="(t, i) in mppPosisi.tanggungJawab" :key="i" class="prg-poin__row">
                                    <span class="prg-poin__ico is-idg"><i class="bi bi-dot"></i></span>
                                    <span>{{ t }}</span>
                                </div>
                            </div>
                            <div v-if="mppPosisi.persyaratan.length" class="prg-poin">
                                <div class="prg-poin__t"><i class="bi bi-patch-check"></i> Kriteria Posisi</div>
                                <div v-for="(t, i) in mppPosisi.persyaratan" :key="i" class="prg-poin__row">
                                    <span class="prg-poin__ico is-ok"><i class="bi bi-check-lg"></i></span>
                                    <span>{{ t }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="mppPosisi.skill.length" class="prg-tagset">
                            <div class="prg-poin__t"><i class="bi bi-stars"></i> Skill</div>
                            <div class="prg-tagset__wrap">
                                <span
                                    v-for="(s, i) in mppPosisi.skill" :key="i" class="prg-skill"
                                    :style="s.warna ? { background: `${s.warna}1a`, color: s.warna } : null"
                                    :title="s.kategori || ''"
                                ><i v-if="s.ikon" class="bi" :class="s.ikon"></i> {{ s.nama }}</span>
                            </div>
                        </div>

                        <div v-if="mppPosisi.benefit.length" class="prg-tagset">
                            <div class="prg-poin__t"><i class="bi bi-gift"></i> Benefit</div>
                            <div class="prg-tagset__wrap">
                                <span v-for="(b, i) in mppPosisi.benefit" :key="i" class="prg-benefit"><i class="bi bi-check-circle-fill"></i> {{ b }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="!muatPosisi" class="prg-card">
                        <div class="prg-empty">
                            <i class="bi bi-file-earmark-x"></i>
                            Posisi ini tidak tertaut ke MPP mana pun, jadi uraian jabatannya tidak ada untuk ditampilkan.
                        </div>
                    </div>
                </section>

                <!-- ══ SYARAT PENYARINGAN ═══════════════════════════════════
                     Tidak ada di rancangan, tapi ini fitur nyata yang sudah
                     jalan dan tidak punya layar lain. Digambar dengan bahasa
                     visual yang sama supaya tetap terasa satu halaman.

                     Judulnya SENGAJA bukan "auto-gugur": tidak semua syarat
                     menggugurkan. Yang menggugurkan hanya yang beraksi "Gugur
                     langsung"; aksi "Tandai" membiarkan kandidat lanjut dan
                     hanya menandainya untuk admin. -->
                <div v-if="!posisiBuka && terpilih.syarat && terpilih.syarat.length" class="prg-card">
                    <div class="prg-card__head">
                        <div>
                            <div class="prg-card__t">Syarat Penyaringan ({{ terpilih.syarat.length }})</div>
                            <div class="prg-card__s">{{ jmlGugur(terpilih) }} menggugurkan otomatis · {{ terpilih.syarat.length - jmlGugur(terpilih) }} hanya menandai</div>
                        </div>
                        <span class="prg-tag"><i class="bi bi-sliders2"></i> Diperiksa saat submit</span>
                    </div>
                    <div class="prg-rules">
                        <div v-for="(sy, i) in terpilih.syarat" :key="i" class="prg-rule">
                            <span class="prg-rule__bar"></span>
                            <div class="prg-rule__top">
                                <span class="prg-rule__name">{{ sy.nama }}</span>
                                <span class="prg-rule__act" :class="sy.aksi === 'GUGUR' ? 'is-gugur' : 'is-mark'">
                                    <i class="bi" :class="sy.aksi === 'GUGUR' ? 'bi-x-octagon' : 'bi-hand-index-thumb'"></i>
                                    {{ sy.aksi === 'GUGUR' ? 'Gugur langsung' : 'Tandai — ketuk palu' }}
                                </span>
                                <span class="prg-rule__match">{{ sy.aturan?.penghubung === 'ATAU' ? 'SALAH SATU' : 'SEMUA' }}</span>
                                <span v-if="!sy.aktif" class="prg-rule__match is-off">Nonaktif</span>
                            </div>
                            <div v-if="(sy.aturan?.aturan || []).length" class="prg-conds">
                                <span v-for="(r, j) in sy.aturan.aturan" :key="j" class="prg-cond">
                                    <i class="bi bi-funnel"></i> <span class="prg-mono">{{ r.field }} {{ r.operator }} {{ r.nilai }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ BATCH ════════════════════════════════════════════════
                     Hanya nama, kuota, dan statusnya. Kolom "terisi" ikut
                     dicabut bersama seluruh hitungan kursi terisi di halaman
                     ini — lihat catatan di kepala berkas. -->
                <div v-if="!posisiBuka && terpilih.batch && terpilih.batch.length" class="prg-card">
                    <div class="prg-card__head">
                        <div>
                            <div class="prg-card__t">Batch ({{ terpilih.batch.length }})</div>
                            <div class="prg-card__s">Pembagian angkatan seleksi program ini</div>
                        </div>
                        <span class="prg-tag is-on"><i class="bi bi-people-fill"></i> {{ kuotaSeluruhBatch }} kursi</span>
                    </div>
                    <div class="prg-batchset">
                        <span v-for="(b, i) in terpilih.batch" :key="i" class="prg-batch">
                            <i class="bi bi-collection"></i>
                            <b>{{ b.nama }}</b>
                            <span>{{ b.kuota }} kursi</span>
                            <em v-if="b.status">{{ b.status }}</em>
                        </span>
                    </div>
                </div>
            </main>

            <main v-else class="prg-main">
                <div class="prg-pick">
                    <span class="prg-pick__ico"><i class="bi bi-hand-index-thumb"></i></span>
                    <strong>Pilih program di sebelah kiri</strong>
                    <small>Alur seleksi, posisi, syarat, dan batch-nya akan tampil di sini.</small>
                </div>
            </main>
        </div>

        <!-- Latar gelap lembar filter — hanya berarti di layar sempit. -->
        <div v-if="sheetOpen" class="prg-sheetbg" @click="sheetOpen = false"></div>
        <!-- Tombol mengambang: disembunyikan CSS di layar lebar (filternya sudah
             terlihat di kolom kiri) dan saat panel kiri sedang tergantikan
             detail program — filter itu milik daftar, bukan milik detail. -->
        <button v-if="!selMobile" class="prg-fab" type="button" aria-label="Filter" @click="sheetOpen = true">
            <i class="bi bi-funnel-fill"></i>
            <span v-if="jumlahFilter" class="prg-fab__badge">{{ jumlahFilter }}</span>
        </button>

        <!-- Modal buat/ubah program -->
        <AdminModal :busy="saving" :show="show" :title="editingId ? 'Ubah Program' : 'Buat Program Kegiatan'" :subtitle="langkahMeta[langkah]?.sub || ''" icon="bi-diagram-3-fill" xl @close="tutupModal">
            <!-- ── Stepper ── -->
            <nav class="pgk-steps">
                <button
                    v-for="(s, i) in langkahMeta"
                    :key="i"
                    class="pgk-step"
                    :class="{ done: i < langkah, cur: i === langkah }"
                    type="button"
                    :disabled="i > langkah"
                    @click="i <= langkah && (langkah = i)"
                >
                    <span class="pgk-step__dot"><i v-if="i < langkah" class="bi bi-check-lg"></i><i v-else class="bi" :class="s.ikon"></i></span>
                    <span class="pgk-step__lbl">{{ s.judul }}<small v-if="s.opsional"> (opsional)</small></span>
                </button>
            </nav>

            <!-- ══════════ LANGKAH 1 — IDENTITAS ══════════
                 Progressive disclosure: HANYA Kategori yang tampil dulu.
                 Field lain baru muncul setelah kategori dipilih (bukan disabled). -->
            <div v-show="langkahKini === 'identitas'" class="pgk-panel">
                <div class="wca-form">
                    <div class="wca-frow wca-frow--single">
                        <div>
                            <label class="wca-field-lbl">Kategori <span v-if="!kategoriTunggal" class="pgk-req">wajib</span></label>

                            <!-- SUMBERNYA kategoriTab, BUKAN master mentah.
                                 kategoriTab sudah disaring hak akses di server; daftar
                                 master penuh akan menawarkan kategori yang tak boleh
                                 dipakai pengguna ini, lalu penyimpanannya ditolak
                                 tanpa ia pernah tahu kenapa. -->
                            <el-select
                                v-if="!kategoriTunggal"
                                filterable
                                v-model="form.kategori"
                                placeholder="Pilih kategori dulu"
                                style="width:100%"
                                @change="onKategori()"
                            >
                                <el-option v-for="k in kategoriTab" :key="k.kode" :value="k.kode" :label="k.nama" />
                            </el-select>

                            <!-- Hanya satu kategori yang boleh: tidak ada yang perlu
                                 dipilih, jadi tidak ada kendali yang ditampilkan.
                                 Nilainya sudah dipasang openCreate(); yang tersisa cuma
                                 keterangan supaya admin tahu program ini masuk ke mana. -->
                            <div v-else class="pgk-kat-tetap">
                                <span class="pkg-pill" :class="katPill(kategoriTunggal.kode)">
                                    <i class="bi" :class="katIkon(kategoriTunggal.kode)"></i> {{ kategoriTunggal.nama }}
                                </span>
                                <small><i class="bi bi-lock-fill"></i> satu-satunya kategori yang menjadi hak akses Anda</small>
                            </div>

                            <div v-if="!kategoriTunggal" class="pgk-hint">Menentukan alur &amp; jadwal yang tersedia di bawah.</div>
                        </div>
                    </div>

                    <!-- Muncul setelah kategori dipilih -->
                    <template v-if="form.kategori">
                        <div class="wca-frow">
                            <div>
                                <label class="wca-field-lbl">Nama Program <span class="pgk-req">wajib</span></label>
                                <el-input v-model="form.nama" placeholder="mis. Rekrutmen Reguler Q4 2026" />
                            </div>
                            <div>
                                <label class="wca-field-lbl">Warna Label</label>
                                <!-- Swatch langsung klik — bukan color-picker mungil dengan ruang kosong. -->
                                <div class="pgk-swatches">
                                    <button
                                        v-for="c in palette"
                                        :key="c"
                                        type="button"
                                        class="pgk-swatch"
                                        :class="{ on: form.warna === c }"
                                        :style="{ background: c }"
                                        :title="c"
                                        @click="form.warna = c"
                                    ><i v-if="form.warna === c" class="bi bi-check-lg"></i></button>
                                    <el-color-picker v-model="form.warna" size="default" title="Warna kustom" />
                                </div>
                            </div>
                        </div>
                        <!-- JADWAL BARU MUNCUL BILA MEMANG ADA.

                             Dulu kotaknya selalu digambar, dan pada kategori yang
                             belum punya jadwal ia berdiri kosong dengan tulisan
                             "Belum ada jadwal untuk alur ini" — bidang wajib-rupa
                             yang tak mungkin diisi, dan orang berhenti di situ
                             menyangka programnya belum boleh dibuat. Jadwal memang
                             tidak wajib (server pun menerimanya kosong), jadi saat
                             tidak ada pilihan yang bisa ditawarkan, tidak ada yang
                             perlu ditanyakan; Alur Seleksi memakai baris itu sendiri. -->
                        <div class="wca-frow" :class="{ 'wca-frow--satu': !tampilJadwal }">
                            <div>
                                <label class="wca-field-lbl">Alur Seleksi</label>
                                <RefSelect type="alur" v-model="form.alur" :params="{ kategori: form.kategori }" placeholder="Pilih alur" no-data-text="Belum ada alur untuk kategori ini" clearable @picked="onAlurGanti" />
                            </div>
                            <div v-if="tampilJadwal">
                                <label class="wca-field-lbl">Jadwal <span class="pgk-opsional">opsional</span></label>
                                <el-select
                                    filterable
                                    clearable
                                    v-model="form.jadwal"
                                    :loading="jadwalMuat"
                                    loading-text="Memuat&hellip;"
                                    placeholder="Pilih jadwal"
                                    style="width:100%"
                                >
                                    <el-option v-for="o in jadwalOpsi" :key="o.value" :label="o.label" :value="o.value" />
                                </el-select>
                            </div>
                        </div>
                        <!-- Status hanya ada saat MENYUNTING: program baru selalu
                             lahir sebagai DRAFT, jadi tidak ada yang perlu dipilih.
                             Saat membuat, Penyelenggara memakai baris penuh. -->
                        <div class="wca-frow" :class="{ 'wca-frow--satu': !bolehTugaskanPic }">
                            <div>
                                <label class="wca-field-lbl">Penyelenggara</label>
                                <el-input v-model="form.penyelenggara" placeholder="mis. Tim Rekrutmen" />
                            </div>
                            <!-- PIC PROGRAM — "dibuat sendiri" vs "dibuatkan".
                                 Atasan bisa membuka program lalu menugaskannya ke
                                 seorang rekruter; tanpa bidang ini program itu
                                 selamanya terbaca sebagai milik atasan, sementara
                                 yang mengerjakannya tidak pernah melihatnya di
                                 daftar "milik saya".

                                 ══ TIDAK DIGAMBAR PADA LINGKUP SENDIRI ══

                                 Satu-satunya orang yang boleh ditugaskan adalah
                                 diri sendiri, dan kotak berisi satu pilihan yang
                                 sudah pasti cuma menyisakan pertanyaan apa
                                 gunanya. Penanggung jawabnya tetap TERCATAT —
                                 form mengirim kode akun ini sendiri, sama dengan
                                 yang sudah dilakukan picLokerBaru() di server.
                                 Yang hilang pilihannya, bukan jawabannya.

                                 Syaratnya lingkup PIC, TITIK. Izin serah terima
                                 sempat ikut membuka bidang ini, dan akibatnya
                                 rekruter berlingkup SENDIRI bisa membuat program
                                 atas nama orang lain — lihat bolehTugaskanPic(). -->
                            <div v-if="bolehTugaskanPic">
                                <label class="wca-field-lbl">
                                    Penanggung Jawab Program
                                    <span class="pgk-opsional">opsional</span>
                                </label>
                                <el-select
                                    v-model="form.picKodeKaryawan"
                                    filterable remote clearable reserve-keyword
                                    :remote-method="cariPic"
                                    :loading="picLoading"
                                    placeholder="Kosongkan bila Anda sendiri yang mengerjakan"
                                    style="width:100%"
                                    @focus="cariPic('')"
                                >
                                    <!-- picPilihan, bukan picOptions: daftar dari server
                                         belum tentu memuat diri sendiri (dan pada
                                         `untuk=penerima` memang sengaja tidak). Tanpa
                                         opsi untuk nilai yang sedang terpasang,
                                         el-select menggambar KODE mentahnya — nama
                                         penanggung jawabnya justru tidak terbaca di
                                         satu-satunya tempat yang menyebutkannya. -->
                                    <el-option v-for="k in picPilihan" :key="k.value" :label="k.label" :value="k.value">
                                        <span>{{ k.nama }} <small class="pgk-opt-kode">({{ k.value }})</small></span>
                                        <!-- Jumlah MPP-nya terbaca SEBELUM dipilih. Yang nol
                                             ditandai merah, bukan disembunyikan: ia mungkin
                                             memang orang yang tepat, dan MPP-nya yang belum
                                             dibuat — menyembunyikannya cuma memindahkan
                                             pertanyaan "kenapa dia tidak ada di daftar". -->
                                        <span class="pgk-opt-mpp" :class="{ 'is-nol': !k.jmlMpp }">
                                            {{ k.jmlMpp }} MPP
                                        </span>
                                    </el-option>
                                    <template #empty><div class="pgk-selempty">Hanya akun internal yang sudah punya kode karyawan</div></template>
                                </el-select>
                            </div>
                        </div>

                        <!-- ══ JALAN BUNTU YANG DIPERINGATKAN DI DEPAN ═══════════
                             Sengaja DI LUAR kolomnya, selebar borang. Kalimatnya
                             dua baris; ditaruh di kolom kanan ia memanjang ke
                             bawah sendirian dan membuat baris di atasnya terlihat
                             timpang — sementara isinya justru yang paling perlu
                             terbaca utuh sebelum orang menekan Lanjut. -->
                        <div v-if="bolehTugaskanPic && picTanpaMpp" class="pgk-picwarn">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div class="pgk-picwarn__teks">
                                <b>{{ picTerpilih.nama }} belum memegang MPP mana pun.</b>
                                <span>
                                    Langkah 2 akan kosong — posisi lowongan hanya bisa diambil dari MPP
                                    yang penanggung jawabnya orang itu sendiri.
                                </span>
                            </div>
                            <button type="button" class="pgk-picwarn__btn" @click="bukaMasterMpp">
                                <i class="bi bi-box-arrow-up-right"></i> Buka Master MPP
                            </button>
                        </div>
                        <div v-else-if="bolehTugaskanPic && picTerpilih" class="pgk-picok">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>
                                Langkah 2 hanya menampilkan <b>{{ picTerpilih.jmlMpp }} MPP</b> milik
                                {{ picTerpilih.nama }} — loker yang lahir dari sini jadi tanggung jawabnya.
                            </span>
                        </div>

                        <!-- Status berdiri di barisnya SENDIRI, tidak menumpang baris
                             di atas: dengan Penyelenggara dan PIC sudah mengisi dua
                             kolom, bidang ketiga akan turun sendirian dan menyisakan
                             separuh baris kosong yang terbaca seperti bidang hilang. -->
                        <div v-if="editingId" class="wca-frow wca-frow--satu">
                            <div>
                                <label class="wca-field-lbl">Status</label>
                                <el-select filterable v-model="form.status" placeholder="Status" style="width:100%">
                                    <el-option label="Draft" value="DRAFT" />
                                    <el-option label="Berjalan" value="BERJALAN" />
                                    <el-option label="Selesai" value="SELESAI" />
                                </el-select>
                            </div>
                        </div>
                    </template>

                    <!-- Sebelum kategori dipilih: ajakan, bukan form kosong terkunci -->
                    <div v-else class="pgk-waitkat">
                        <i class="bi bi-arrow-up-circle"></i>
                        <span>Pilih <strong>kategori</strong> dulu — form identitas, alur & jadwal akan muncul setelahnya.</span>
                    </div>
                </div>
            </div>

            <!-- ══════════ LANGKAH 2 — POSISI ══════════ -->
            <div v-show="langkahKini === 'posisi'" class="pgk-panel">
                <div class="pgk-panel__bar">
                    <div>
                        <div class="pgk-panel__title"><i class="bi bi-briefcase"></i> Posisi / Lowongan</div>
                        <div class="pgk-panel__note">Diambil dari MPP yang sudah disetujui — tidak diketik manual.</div>
                    </div>
                </div>

                <!-- Pilih MPP lewat KARTU (ala Monitoring MPP) — klik kartu = pilih, klik lagi = buang.
                     Ikon info membuka popover detail TANPA menutup modal. -->
                <div class="pgk-mpppick">
                    <div class="pgk-mpppick__head">
                        <!-- Sumber daftarnya disebut terang-terangan. Daftar ini
                             disaring Flag_MT, jadi "kosong" bisa berarti dua hal
                             yang sangat berbeda: belum ada MPP sama sekali, atau
                             belum ada MPP UNTUK KATEGORI INI. Tanpa keterangan,
                             admin membaca yang pertama dan melapor sistemnya
                             rusak. -->
                        <label class="wca-field-lbl">
                            Pilih Posisi dari MPP
                            <span class="pgk-req">klik kartu — bisa banyak</span>
                            <span class="pgk-mppscope" :class="{ 'is-mt': form.kategori === 'MT' }">
                                <i class="bi" :class="form.kategori === 'MT' ? 'bi-mortarboard-fill' : 'bi-briefcase-fill'"></i>
                                {{ form.kategori === 'MT' ? 'MPP Management Trainee' : 'MPP non-MT' }}
                            </span>
                        </label>
                        <div class="pgk-mppsearch">
                            <i class="bi bi-search"></i>
                            <input v-model="mppCari" type="text" placeholder="Cari posisi / departemen / nomor MPP…" />
                            <button v-if="mppCari" type="button" @click="mppCari = ''"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>

                    <!-- ══ DUA TAB LINGKUP ════════════════════════════════════
                         Hanya digambar bila memang ADA yang di luar lingkup.
                         Pengguna berlingkup SEMUA tidak punya "MPP orang lain",
                         jadi menawarkan dua tab kepadanya cuma menambah kendali
                         yang kedua sisinya berisi hal yang sama.

                         Bawaannya "Lingkup Saya": daftar yang bisa dikerjakan
                         langsung ada di depan, dan yang milik orang lain tetap
                         satu klik jauhnya — bukan disembunyikan. -->
                    <div v-if="adaMppTerkunci" class="pgk-mpptabs">
                        <button
                            type="button"
                            class="pgk-mpptab"
                            :class="{ on: mppLingkup === 'SAYA' }"
                            @click="mppLingkup = 'SAYA'"
                        >
                            <i class="bi bi-person-check-fill"></i> Lingkup Saya
                            <span class="pgk-mpptab__n">{{ mppBoleh.length }}</span>
                        </button>
                        <button
                            type="button"
                            class="pgk-mpptab"
                            :class="{ on: mppLingkup === 'SEMUA' }"
                            @click="mppLingkup = 'SEMUA'"
                        >
                            <i class="bi bi-people-fill"></i> Semua MPP
                            <span class="pgk-mpptab__n">{{ mppOptions.length }}</span>
                        </button>
                        <small class="pgk-mpptabs__note">
                            <i class="bi bi-lock-fill"></i>
                            {{ mppTerkunci.length }} MPP dipegang orang lain — terlihat, tapi tidak bisa dipilih.
                        </small>
                    </div>
                    <div v-if="mppOptions.length" class="pgk-mppgrid">
                        <!-- ══ KARTU YANG BOLEH DIPILIH ═══════════════════════
                             Cabang ini DAN cabang terkunci di bawahnya sengaja
                             ditulis terpisah, bukan satu kartu dengan :disabled.

                             `disabled` cuma petunjuk sisi klien — atributnya bisa
                             dicabut lewat inspect element, dan penangan kliknya
                             tetap terpasang di baliknya. Kartu terkunci di bawah
                             TIDAK PUNYA @click sama sekali: tidak ada yang bisa
                             dinyalakan kembali, karena memang tidak ada apa-apa
                             di sana untuk dinyalakan.

                             (Yang menjaga data tetap gerbang di server —
                             galatLingkupPic(). Ini lapis keduanya.) -->
                        <div
                            v-for="o in mppTersaring.filter((x) => x.boleh)"
                            :key="o.value"
                            class="pgk-mppcard"
                            :class="{ on: mppTerpilih.includes(o.value) }"
                            role="button"
                            @click="toggleMpp(o)"
                        >
                            <div class="pgk-mppcard__head">
                                <span class="pgk-mppcard__check"><i class="bi bi-check-lg"></i></span>
                                <h5 class="pgk-mppcard__title">{{ tc(o.posisi) }}</h5>
                                <el-popover placement="top" :width="280" trigger="click">
                                    <template #reference>
                                        <button type="button" class="pgk-mppcard__info" title="Lihat detail MPP" @click.stop><i class="bi bi-info-circle"></i></button>
                                    </template>
                                    <div class="pgk-mppdetail">
                                        <strong>{{ tc(o.posisi) }}</strong>
                                        <div><i class="bi bi-upc-scan"></i> {{ o.value }}</div>
                                        <div><i class="bi bi-diagram-3"></i> {{ o.departemen || '—' }}</div>
                                        <div><i class="bi bi-bar-chart-steps"></i> Level {{ o.level || '—' }}</div>
                                        <div><i class="bi bi-briefcase"></i> {{ o.employment || '—' }}</div>
                                        <div><i class="bi bi-geo-alt"></i> {{ o.workplace || '—' }}</div>
                                        <div><i class="bi bi-stars"></i> {{ o.experience || '—' }}</div>
                                        <div><i class="bi bi-people"></i> Kuota {{ o.kuota }} orang</div>
                                    </div>
                                </el-popover>
                            </div>
                            <div class="pgk-mppcard__badges">
                                <span class="pgk-bdg pgk-bdg--indigo"><i class="bi bi-diagram-3"></i> {{ tc(o.divisi) || '—' }}</span>
                                <span v-if="o.sub" class="pgk-bdg pgk-bdg--sky">{{ tc(o.sub) }}</span>
                            </div>
                            <div class="pgk-mppcard__no"><i class="bi bi-hash"></i>{{ o.value }}</div>
                            <div v-if="o.employment || o.workplace || o.experience" class="pgk-mppcard__tags">
                                <span v-if="o.employment" class="mppt mppt--emp"><span class="mppt__dot mppt__dot--emp"></span><i class="bi bi-briefcase-fill"></i> {{ o.employment }}</span>
                                <span v-if="o.workplace" class="mppt mppt--wp"><span class="mppt__dot mppt__dot--wp"></span><i class="bi bi-geo-alt-fill"></i> {{ o.workplace }}</span>
                                <span v-if="o.experience" class="mppt mppt--exp"><span class="mppt__dot mppt__dot--exp"></span><i class="bi bi-stars"></i> {{ o.experience }}</span>
                            </div>
                            <div class="pgk-mppcard__meta">
                                <span><i class="bi bi-people-fill"></i> {{ o.kuota }} orang</span>
                                <span v-if="o.level"><i class="bi bi-bar-chart-steps"></i> {{ tc(o.level) }}</span>
                            </div>
                        </div>
                        <!-- ══ KARTU TERKUNCI — TANPA role, TANPA @click ══════
                             Bentuknya sama supaya terbaca sebagai MPP yang sama,
                             tapi tidak ada satu pun kendali di dalamnya: penanda
                             centang diganti gembok, dan nama pemegangnya ditulis
                             supaya orang tahu harus minta ke siapa — bukan
                             menelepon satu per satu mencari tahu.

                             Popover detail dibiarkan: membaca isi MPP orang lain
                             tidak berbahaya, dan justru itu yang dibutuhkan untuk
                             memutuskan apakah perlu memintanya. -->
                        <div
                            v-for="o in mppTersaring.filter((x) => !x.boleh)"
                            :key="`kunci-${o.value}`"
                            class="pgk-mppcard is-terkunci"
                        >
                            <div class="pgk-mppcard__head">
                                <span class="pgk-mppcard__gembok"><i class="bi bi-lock-fill"></i></span>
                                <h5 class="pgk-mppcard__title">{{ tc(o.posisi) }}</h5>
                                <el-popover placement="top" :width="280" trigger="click">
                                    <template #reference>
                                        <button type="button" class="pgk-mppcard__info" title="Lihat detail MPP"><i class="bi bi-info-circle"></i></button>
                                    </template>
                                    <div class="pgk-mppdetail">
                                        <strong>{{ tc(o.posisi) }}</strong>
                                        <div><i class="bi bi-upc-scan"></i> {{ o.value }}</div>
                                        <div><i class="bi bi-person-badge"></i> PIC {{ o.picNama || '—' }}</div>
                                        <div><i class="bi bi-diagram-3"></i> {{ o.departemen || '—' }}</div>
                                        <div><i class="bi bi-people"></i> Kuota {{ o.kuota }} orang</div>
                                    </div>
                                </el-popover>
                            </div>
                            <div class="pgk-mppcard__pic">
                                <i class="bi bi-person-badge"></i>
                                <span>Dipegang <b>{{ o.picNama || o.picKode || 'orang lain' }}</b></span>
                            </div>
                            <div class="pgk-mppcard__badges">
                                <span class="pgk-bdg pgk-bdg--indigo"><i class="bi bi-diagram-3"></i> {{ tc(o.divisi) || '—' }}</span>
                                <span v-if="o.sub" class="pgk-bdg pgk-bdg--sky">{{ tc(o.sub) }}</span>
                            </div>
                            <div class="pgk-mppcard__no"><i class="bi bi-hash"></i>{{ o.value }}</div>
                            <div class="pgk-mppcard__meta">
                                <span><i class="bi bi-people-fill"></i> {{ o.kuota }} orang</span>
                                <span v-if="o.level"><i class="bi bi-bar-chart-steps"></i> {{ tc(o.level) }}</span>
                            </div>
                        </div>

                        <div v-if="!mppTersaring.length" class="pgk-empty" style="grid-column:1/-1">Tidak ada MPP yang cocok dengan pencarian.</div>
                    </div>
                    <!-- Kenapa kosongnya disebutkan: daftar ini disaring
                         Flag_MT. Program MT hanya boleh memakai MPP yang memang
                         diajukan sebagai MT di Master MPP — jadi kosong di sini
                         hampir selalu berarti MPP-nya belum dibuat, bukan
                         daftarnya gagal dimuat. -->
                    <div v-else class="pgk-empty">
                        <template v-if="form.kategori === 'MT'">
                            Ajukan dulu di Master MPP dengan jenis program <strong>MT</strong>.
                        </template>
                        <template v-else>
                            Belum ada MPP <b>non-MT</b> yang aktif untuk dipilih.
                        </template>
                    </div>
                </div>

                <!-- Panel SUDAH DIPILIH — area sendiri dengan scrollbar sendiri. -->
                <div class="pgk-selpanel">
                    <div class="pgk-selpanel__hd">
                        <i class="bi bi-check2-circle"></i> Sudah Dipilih ({{ form.posisi.length }})
                        <span v-if="form.posisi.length" class="pgk-selpanel__tot"><i class="bi bi-people"></i> Total kuota {{ kuotaMpp }}</span>
                        <span class="pgk-selpanel__hint">Ikon <i class="bi bi-info-circle"></i> pada kartu menampilkan detail tanpa menutup modal</span>
                    </div>
                    <div class="pgk-selpanel__body">
                <div class="pgk-rows">
                    <!-- Baris posisi terpilih: info sebagai chip elegan (bukan input mati), kuota bisa disetel. -->
                    <div v-for="(l, i) in form.posisi" :key="l.mppRef || i" class="pgk-posrow">
                        <span class="pgk-posrow__accent"></span>
                        <div class="pgk-posrow__main">
                            <div class="pgk-posrow__title">
                                <strong>{{ l.posisi || 'Posisi manual (lama)' }}</strong>
                                <code v-if="l.mppRef" class="pgk-mpp">{{ l.mppRef }}</code>
                            </div>
                            <div class="pgk-posrow__meta">
                                <span v-if="l.departemen"><i class="bi bi-diagram-3"></i> {{ l.departemen }}</span>
                                <span v-if="l.level"><i class="bi bi-person-badge"></i> {{ l.level }}</span>
                                <span v-if="l.lokasi"><i class="bi bi-building"></i> {{ l.lokasi }}</span>
                            </div>
                        </div>
                        <div class="pgk-posrow__kuota">
                            <label>Kuota <small v-if="l.kuotaMpp">dari MPP {{ l.kuotaMpp }}</small></label>
                            <el-input-number v-model="l.kuota" :min="0" :max="l.kuotaMpp || undefined" controls-position="right" style="width:130px" />
                        </div>
                        <div v-if="mengubah" class="pgk-posrow__status">
                            <label>Status</label>
                            <el-select v-model="l.status" style="width:110px">
                                <el-option label="Buka" value="BUKA" />
                                <el-option label="Penuh" value="PENUH" />
                                <el-option label="Tutup" value="TUTUP" />
                            </el-select>
                        </div>
                        <button class="pgk-posrow__del" type="button" title="Hapus posisi" @click="form.posisi.splice(i, 1)"><i class="bi bi-trash"></i></button>
                    </div>
                    <div v-if="!form.posisi.length" class="pgk-empty">
                        Tanpa posisi — program tetap bisa dibuat. Klik kartu MPP di atas, barisnya terbuat otomatis.
                    </div>
                </div>
                    </div>
                </div>
            </div>

            <!-- ══════════ LANGKAH 3 — PENGATURAN TAMBAHAN (opsional) ══════════ -->
            <div v-show="langkahKini === 'tambahan'" class="pgk-panel">
                <div class="pgk-panel__intro">
                    <i class="bi bi-info-circle"></i>
                    Semua di langkah ini <b>boleh dilewati</b>. Aktifkan hanya yang Anda perlukan.
                </div>

                <!-- Batch -->
                <div class="pgk-opsi" :class="{ 'is-on': pakaiBatch }">
                    <label class="pgk-opsi__hd">
                        <el-switch v-model="pakaiBatch" @change="togglePakaiBatch" />
                        <span class="pgk-opsi__t"><i class="bi bi-collection"></i> Bagi per angkatan (Batch)</span>
                        <small>Kalau seleksi dijalankan bergelombang. Kalau tidak, biarkan mati.</small>
                    </label>

                    <div v-if="pakaiBatch" class="pgk-opsi__body">
                        <div class="pgk-opsi__toolbar">
                            <span v-if="kuotaMpp" class="pgk-meter" :class="{ 'is-over': kuotaBatchLebih }">
                                <i class="bi" :class="kuotaBatchLebih ? 'bi-exclamation-octagon-fill' : 'bi-people-fill'"></i>
                                {{ kuotaBatch }} / {{ kuotaMpp }} kursi MPP
                            </span>
                            <span style="flex:1"></span>
                            <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addBatch"><i class="bi bi-plus-circle"></i> Tambah Batch</button>
                        </div>
                        <div v-if="kuotaBatchLebih" class="pgk-alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Total kursi batch <strong>{{ kuotaBatch }}</strong> melebihi kuota MPP <strong>{{ kuotaMpp }}</strong>. Kurangi {{ kuotaBatch - kuotaMpp }} kursi.
                        </div>
                        <div class="pgk-rows">
                            <div v-for="(b, i) in form.batch" :key="i" class="pgk-row">
                                <div class="pgk-row__grid pgk-row__grid--batch" :class="{ 'is-edit': mengubah }">
                                    <div><label class="wca-field-lbl">Nama</label><el-input v-model="b.nama" placeholder="Batch 1 — Mei 2026" /></div>
                                    <div>
                                        <label class="wca-field-lbl">Kuota <small v-if="kuotaMpp">maks {{ batasBatch(i) }}</small></label>
                                        <el-input-number v-model="b.kuota" :min="0" :max="kuotaMpp ? batasBatch(i) : undefined" controls-position="right" style="width:100%" />
                                    </div>
                                    <template v-if="mengubah">
                                        <!-- Kolom "Terisi" DICABUT. Berapa kursi yang
                                             sudah terisi bukan angka yang diketik
                                             tangan di master data — ia dihitung dari
                                             kandidat yang diterima, dan dibaca di
                                             Pembukaan Program. Angka lama tiap batch
                                             tetap utuh: server memungutnya sebelum
                                             menulis ulang barisnya. -->
                                        <div><label class="wca-field-lbl">Status</label>
                                            <el-select v-model="b.status" style="width:100%">
                                                <el-option label="Aktif" value="AKTIF" />
                                                <el-option label="Selesai" value="SELESAI" />
                                                <el-option label="Tutup" value="TUTUP" />
                                            </el-select>
                                        </div>
                                    </template>
                                </div>
                                <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus batch" @click="form.batch.splice(i, 1)"><i class="bi bi-trash"></i></button>
                            </div>
                            <div v-if="!form.batch.length" class="pgk-empty" style="padding:.6rem">Klik "Tambah Batch" untuk menambah angkatan.</div>
                        </div>
                    </div>
                </div>

                <!-- Syarat auto-gugur -->
                <div class="pgk-opsi" :class="{ 'is-on': pakaiSyarat }">
                    <label class="pgk-opsi__hd">
                        <el-switch v-model="pakaiSyarat" :disabled="!tahapFormulir.length" @change="togglePakaiSyarat" />
                        <span class="pgk-opsi__t"><i class="bi bi-sliders2"></i> Syarat Auto-Gugur</span>
                        <small>Saring pelamar otomatis dari jawaban formulir (mis. IPK minimal, usia maksimal).</small>
                    </label>

                    <div v-if="!tahapFormulir.length" class="pgk-alert" style="margin:.5rem 0 0">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Alur <b>{{ form.alur || '—' }}</b> belum punya tahap berformulir. Pasang formulir di Master Tahapan Seleksi dulu — tanpa formulir tidak ada data yang bisa disaring.
                    </div>

                    <div v-if="pakaiSyarat" class="pgk-opsi__body">
                        <div class="pgk-opsi__toolbar">
                            <span style="flex:1"></span>
                            <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addSyarat"><i class="bi bi-plus-circle"></i> Tambah Syarat</button>
                        </div>

                        <div v-for="(S, i) in form.syarat" :key="i" class="pgk-syarat">
                            <!-- Baris identitas syarat: nama + tahap, masing-masing berlabel -->
                            <div class="pgk-syarat__id">
                                <div class="pgk-syarat__idf">
                                    <label class="wca-field-lbl">Nama Syarat</label>
                                    <el-input v-model="S.nama" placeholder="mis. Kelayakan Akademik" />
                                </div>
                                <div class="pgk-syarat__idf">
                                    <label class="wca-field-lbl">Diperiksa di Tahap</label>
                                    <el-select v-model="S.tahapId" placeholder="Pilih tahap berformulir" style="width:100%" @change="(v) => onTahapSyarat(S, v)">
                                        <el-option v-for="t in tahapFormulir" :key="t.tahapId" :value="t.tahapId" :label="`${t.urutan}. ${t.label} — ${t.formulirNama || t.formulir}`" />
                                    </el-select>
                                </div>
                                <button class="wca-iconbtn wca-iconbtn--danger pgk-syarat__del" type="button" title="Hapus syarat" @click="form.syarat.splice(i, 1)"><i class="bi bi-trash"></i></button>
                            </div>

                            <!-- Pohon aturan -->
                            <div class="pgk-aturan">
                                <div class="pgk-aturan__bar">
                                    <span>Pelamar <b>lolos</b> bila</span>
                                    <el-select v-model="S.aturan.penghubung" size="small" style="width:8.5rem">
                                        <el-option value="DAN" label="SEMUA" />
                                        <el-option value="ATAU" label="SALAH SATU" />
                                    </el-select>
                                    <span>kondisi terpenuhi</span>
                                </div>

                                <!-- BINDING TIPE FIELD: operator & input Nilai mengikuti tipe field dari skema
                                     formulir (angka -> input angka, pilihan -> dropdown dari sumber yang sama
                                     dengan formulirnya, teks -> bebas). Seperti formula: tipe menentukan bentuk. -->
                                <div v-for="(K, j) in S.aturan.aturan" :key="j" class="pgk-kondisi">
                                    <div class="pgk-kondisi__f">
                                        <label class="wca-field-lbl">Field</label>
                                        <el-select v-model="K.field" filterable placeholder="Pilih field" style="width:100%" :no-data-text="S.tahapId ? 'Formulir tahap ini tidak punya field' : 'Pilih tahap dulu'" @change="onFieldGanti(S, K)">
                                            <el-option-group label="Dari formulir">
                                                <el-option v-for="f in fieldTahap(S.tahapId)" :key="f.key" :value="f.key" :label="f.label" />
                                            </el-option-group>
                                            <el-option-group label="Dihitung otomatis (mis. usia dari tanggal lahir)">
                                                <el-option v-for="f in fieldTurunan" :key="f.key" :value="f.key" :label="f.label" />
                                            </el-option-group>
                                        </el-select>
                                    </div>
                                    <div class="pgk-kondisi__o">
                                        <label class="wca-field-lbl">
                                            Syarat
                                            <el-tooltip content="Daftar operator otomatis menyesuaikan tipe field yang dipilih." placement="top">
                                                <i class="bi bi-info-circle pgk-lblinfo"></i>
                                            </el-tooltip>
                                        </label>
                                        <el-select v-model="K.operator" style="width:100%" @change="K.nilai = ''">
                                            <el-option v-for="o in operatorUntuk(S, K)" :key="o.value" :value="o.value" :label="o.label">
                                                <div class="pgk-opdesc"><span>{{ o.label }}</span><small>{{ o.desc }}</small></div>
                                            </el-option>
                                        </el-select>
                                    </div>
                                    <div class="pgk-kondisi__v">
                                        <label class="wca-field-lbl">Nilai</label>
                                        <!-- angka + ANTARA: dua kotak rentang -->
                                        <div v-if="tipeField(S, K.field) === 'number' && K.operator === 'ANTARA'" class="pgk-antara">
                                            <el-input-number :model-value="antaraVal(K, 0)" :controls="false" placeholder="dari" @update:model-value="(v) => setAntara(K, 0, v)" />
                                            <span class="pgk-antara__sep">—</span>
                                            <el-input-number :model-value="antaraVal(K, 1)" :controls="false" placeholder="sampai" @update:model-value="(v) => setAntara(K, 1, v)" />
                                        </div>
                                        <!-- angka biasa -->
                                        <el-input-number
                                            v-else-if="tipeField(S, K.field) === 'number'"
                                            :model-value="angkaVal(K)"
                                            controls-position="right"
                                            style="width:100%"
                                            :placeholder="phNilai(K.operator)"
                                            @update:model-value="(v) => (K.nilai = v === null || v === undefined ? '' : String(v))"
                                        />
                                        <!-- pilihan, operator daftar: PANEL CHECKLIST (bukan select sempit) —
                                             muat banyak nilai (mis. puluhan kampus) tetap jelas & bisa dicari -->
                                        <div v-else-if="adaOpsi(S, K.field) && (K.operator === 'ADA_DI' || K.operator === 'TIDAK_ADA_DI')" class="pgk-multichk">
                                            <div class="pgk-multichk__search">
                                                <i class="bi bi-search"></i>
                                                <input :value="K._cari || ''" type="text" placeholder="Cari nilai…" @input="(e) => (K._cari = e.target.value)" />
                                            </div>
                                            <div class="pgk-multichk__list">
                                                <label v-for="o in opsiTersaring(S, K)" :key="o" class="pgk-multichk__item" :class="{ on: listVal(K).includes(o) }">
                                                    <input type="checkbox" :checked="listVal(K).includes(o)" @change="toggleNilai(K, o)" />
                                                    <span>{{ o }}</span>
                                                    <i v-if="listVal(K).includes(o)" class="bi bi-check-lg"></i>
                                                </label>
                                                <div v-if="!opsiTersaring(S, K).length" class="pgk-multichk__empty">Tidak ada nilai yang cocok.</div>
                                            </div>
                                            <div class="pgk-multichk__foot">
                                                <span><b>{{ listVal(K).length }}</b> nilai dipilih</span>
                                                <button v-if="listVal(K).length" type="button" @click="K.nilai = ''"><i class="bi bi-x-circle"></i> Bersihkan</button>
                                            </div>
                                        </div>
                                        <!-- pilihan tunggal -->
                                        <el-select v-else-if="adaOpsi(S, K.field)" v-model="K.nilai" filterable style="width:100%" placeholder="Pilih nilai">
                                            <el-option v-for="o in opsiField(S, K.field)" :key="o" :value="o" :label="o" />
                                        </el-select>
                                        <!-- teks bebas (fallback) -->
                                        <el-input v-else v-model="K.nilai" :placeholder="phNilai(K.operator)" />
                                    </div>
                                    <button class="wca-iconbtn wca-iconbtn--danger pgk-kondisi__del" type="button" title="Hapus kondisi" @click="S.aturan.aturan.splice(j, 1)"><i class="bi bi-x-lg"></i></button>
                                </div>

                                <button class="pgk-addkondisi" type="button" @click="addKondisi(S)"><i class="bi bi-plus-lg"></i> Tambah Kondisi</button>
                                <div v-if="!S.aturan.aturan.length" class="pgk-kondisi-kosong">Belum ada kondisi — syarat ini akan diabaikan mesin.</div>
                            </div>

                            <!-- Aksi bila tidak lolos (Mode Uji dihapus — mulai dari TANDAI dulu bila ragu) -->
                            <div class="pgk-syarat__opt">
                                <div>
                                    <label class="wca-field-lbl">Bila tidak lolos</label>
                                    <el-select v-model="S.aksi" style="width:100%">
                                        <el-option value="TANDAI" label="Tandai — admin yang ketuk palu" />
                                        <el-option value="GUGUR" label="Gugurkan langsung tanpa admin" />
                                    </el-select>
                                </div>
                            </div>

                            <div class="pgk-pesan">
                                <label class="wca-field-lbl">
                                    <i class="bi bi-chat-heart"></i> Pesan penolakan untuk kandidat
                                    <span class="pgk-opt">opsional</span>
                                </label>
                                <el-input v-model="S.pesanGugur" type="textarea" :rows="3" :placeholder="contohPesan" maxlength="500" show-word-limit resize="none" />
                                <button class="pgk-isipesan" type="button" @click="S.pesanGugur = contohPesan"><i class="bi bi-magic"></i> Pakai contoh</button>
                            </div>
                        </div>

                        <div v-if="!form.syarat.length" class="pgk-empty" style="padding:.6rem">Klik "Tambah Syarat" untuk mulai menyaring pelamar.</div>
                    </div>
                </div>
            </div>

            <!-- ══════════ LANGKAH — PHONE SCREENING ══════════
                 Hanya ada bila alur yang dipilih memang memuat tahap skrining.
                 Alur tanpa skrining tidak melihat langkah ini sama sekali —
                 bukan melihatnya dalam keadaan kosong. -->
            <div v-show="langkahKini === 'skrining'" class="pgk-panel">
                <div class="pgk-panel__bar">
                    <div>
                        <div class="pgk-panel__title"><i class="bi bi-telephone-inbound"></i> Kuesioner Phone Screening</div>
                        <div class="pgk-panel__note">
                            Alur <b>{{ form.alur }}</b> memuat tahap skrining — tiap loker perlu tahu kuesioner mana yang dipakai.
                        </div>
                    </div>
                </div>

                <div class="pgk-panel__intro">
                    <i class="bi bi-info-circle"></i>
                    <span>
                        Rekruter <b>tidak bisa menutup</b> aktivitas skrining sebelum kuesionernya diisi.
                        Loker yang dibiarkan tanpa kuesioner tetap berjalan seperti tahap manual biasa —
                        boleh dilewati sekarang, dipasang belakangan di rincian program.
                    </span>
                </div>

                <div v-for="a in wzSkrAktivitas" :key="a.id" class="pgk-skr">
                    <div class="pgk-skr__hd">
                        <span class="pgk-skr__no">{{ a.urutan }}</span>
                        <div>
                            <b>{{ a.tahap }}</b>
                            <small v-if="a.label !== a.tahap">{{ a.label }}</small>
                        </div>
                    </div>

                    <!-- ── SATU TEMPLATE UNTUK SEMUA ────────────────────────
                         Inilah jalur yang dipakai hampir setiap kali: dua belas
                         MPP dengan kuesioner yang sama diselesaikan dengan SATU
                         pilihan, bukan dua belas. Penimpaan per loker hanya
                         untuk yang benar-benar berbeda. -->
                    <div class="pgk-skr__row is-bawaan">
                        <div class="pgk-skr__lbl">
                            <i class="bi bi-diagram-3"></i>
                            Berlaku untuk seluruh loker
                        </div>
                        <el-select
                            v-model="wzSkrPilih[a.id].bawaan"
                            placeholder="Belum dipasang — aktivitas jalan tanpa kuesioner"
                            clearable filterable class="skr-sel" popper-class="skr-pop"
                            @change="wzSkrSamakan(a.id)"
                        >
                            <el-option
                                v-for="t in wzSkrTemplate" :key="t.kode"
                                :label="`${t.nama}${t.versiTerbit ? ` (v${t.versiTerbit})` : ' — belum terbit'}`"
                                :value="t.kode" :disabled="!t.siap"
                            />
                        </el-select>
                    </div>

                    <button v-if="form.posisi.length" class="pgk-skr__toggle" type="button" @click="wzSkrBuka = wzSkrBuka === a.id ? null : a.id">
                        <i class="bi" :class="wzSkrBuka === a.id ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                        Penimpaan per loker
                        <span v-if="wzSkrJmlTimpa(a.id)" class="pgk-skr__badge">{{ wzSkrJmlTimpa(a.id) }} berbeda</span>
                        <span v-else class="pgk-skr__samar">{{ form.posisi.length }} loker ikut bawaan</span>
                    </button>

                    <div v-if="wzSkrBuka === a.id" class="pgk-skr__lokers">
                        <div v-for="l in form.posisi" :key="l.mppRef" class="pgk-skr__row">
                            <div class="pgk-skr__lbl">
                                <i class="bi bi-briefcase"></i>
                                <span :title="l.posisi">{{ l.posisi || '(belum dipilih)' }}</span>
                                <code v-if="l.mppRef">{{ l.mppRef }}</code>
                            </div>
                            <el-select
                                v-model="wzSkrPilih[a.id].perLoker[l.mppRef]"
                                :placeholder="wzSkrPilih[a.id].bawaan ? `Ikut bawaan — ${wzSkrNama(wzSkrPilih[a.id].bawaan)}` : 'Tidak ada kuesioner'"
                                clearable filterable class="skr-sel" popper-class="skr-pop"
                            >
                                <el-option
                                    v-for="t in wzSkrTemplate" :key="t.kode"
                                    :label="`${t.nama}${t.versiTerbit ? ` (v${t.versiTerbit})` : ' — belum terbit'}`"
                                    :value="t.kode" :disabled="!t.siap"
                                />
                            </el-select>
                        </div>
                    </div>
                </div>

                <div v-if="!wzSkrTemplate.length" class="pgk-skr__kosong">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>
                        Belum ada template skrining yang terbit. Buat dulu di <b>Master Template Skrining</b>,
                        lalu pasang di sini atau di rincian program.
                    </span>
                </div>
            </div>

            <!-- ══════════ LANGKAH 4 — PRATINJAU ══════════ -->
            <div v-show="langkahKini === 'pratinjau'" class="pgk-panel">
                <div class="pgk-prev">
                    <!-- HERO ringkasan program -->
                    <div class="pgk-prev__hero">
                        <span class="pgk-prev__heroBar" :style="{ background: form.warna || '#4f46e5' }"></span>
                        <div class="pgk-prev__heroMain">
                            <div class="pgk-prev__heroTop">
                                <h4>{{ form.nama || '—' }}</h4>
                                <span class="pgk-prev__tag pgk-prev__tag--vio">{{ katLabel(form.kategori) }}</span>
                                <span v-if="editingId" class="pgk-prev__tag pgk-prev__tag--ind">{{ statusLabel(form.status) }}</span>
                            </div>
                            <div class="pgk-prev__heroChips">
                                <span><i class="bi bi-signpost-split"></i> Alur <code>{{ form.alur || '—' }}</code></span>
                                <span><i class="bi bi-calendar3-range"></i> Jadwal <code>{{ form.jadwal || '—' }}</code></span>
                                <span><i class="bi bi-person"></i> {{ form.penyelenggara || '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Stat mini -->
                    <div class="pgk-prev__stats">
                        <div><span class="pgk-prev__sico" style="background:rgba(99,102,241,.12);color:#6366f1"><i class="bi bi-briefcase-fill"></i></span><b>{{ form.posisi.length }}</b><span>Posisi</span></div>
                        <div><span class="pgk-prev__sico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-people-fill"></i></span><b>{{ kuotaMpp }}</b><span>Total Kuota</span></div>
                        <div><span class="pgk-prev__sico" style="background:rgba(139,92,246,.12);color:#7c3aed"><i class="bi bi-collection-fill"></i></span><b>{{ form.batch.length }}</b><span>Batch</span></div>
                        <div><span class="pgk-prev__sico" style="background:rgba(245,158,11,.14);color:#b45309"><i class="bi bi-sliders2"></i></span><b>{{ form.syarat.length }}</b><span>Syarat</span></div>
                    </div>

                    <!-- Posisi -->
                    <div class="pgk-prev__card">
                        <div class="pgk-prev__hd">
                            <i class="bi bi-briefcase"></i> Posisi / Lowongan ({{ form.posisi.length }})
                            <span v-if="form.posisi.length" class="pgk-prev__tag pgk-prev__tag--ind"><i class="bi bi-people"></i> Total kuota {{ kuotaMpp }}</span>
                        </div>
                        <div v-if="form.posisi.length" class="pgk-prev__list">
                            <div v-for="(l, i) in form.posisi" :key="i" class="pgk-prev__item">
                                <span class="pgk-prev__dot"></span>
                                <span class="pgk-prev__nm">{{ l.posisi }}</span>
                                <code v-if="l.mppRef" class="pgk-mpp">{{ l.mppRef }}</code>
                                <span class="pgk-prev__kt">{{ l.kuota }} kuota</span>
                            </div>
                        </div>
                        <div v-else class="pgk-prev__none">Tanpa posisi — program dibuat tanpa lowongan tertaut.</div>
                    </div>

                    <!-- Batch -->
                    <div v-if="form.batch.length" class="pgk-prev__card">
                        <div class="pgk-prev__hd"><i class="bi bi-collection"></i> Batch ({{ form.batch.length }})</div>
                        <div class="pgk-prev__list">
                            <div v-for="(b, i) in form.batch" :key="i" class="pgk-prev__item">
                                <span class="pgk-prev__dot"></span>
                                <span class="pgk-prev__nm">{{ b.nama || '(tanpa nama)' }}</span>
                                <span class="pgk-prev__kt">{{ b.kuota }} kursi</span>
                            </div>
                        </div>
                    </div>

                    <!-- Syarat -->
                    <div v-if="form.syarat.length" class="pgk-prev__card">
                        <div class="pgk-prev__hd"><i class="bi bi-sliders2"></i> Syarat Auto-Gugur ({{ form.syarat.length }})</div>
                        <div v-for="(S, i) in form.syarat" :key="i" class="pgk-prev__syarat">
                            <div class="pgk-prev__srow">
                                <b>{{ S.nama || 'Syarat ' + (i + 1) }}</b>
                                <span class="pgk-prev__tag pgk-prev__tag--vio">{{ S.aturan.penghubung === 'ATAU' ? 'SALAH SATU' : 'SEMUA' }}</span>
                                <span class="pgk-prev__tag" :class="S.aksi === 'GUGUR' ? 'pgk-prev__tag--red' : 'pgk-prev__tag--ind'">
                                    <i class="bi" :class="S.aksi === 'GUGUR' ? 'bi-x-octagon' : 'bi-hand-index-thumb'"></i>
                                    {{ S.aksi === 'GUGUR' ? 'Gugur langsung' : 'Tandai — ketuk palu' }}
                                </span>
                            </div>
                            <div class="pgk-prev__conds">
                                <span v-for="(K, j) in S.aturan.aturan" :key="j" class="pgk-prev__cond">
                                    <i class="bi bi-funnel"></i> {{ labelField(S, K.field) }} <b>{{ opLabel(K.operator) }}</b> {{ K.nilai || '—' }}
                                </span>
                                <span v-if="!S.aturan.aturan.length" class="pgk-prev__none">Belum ada kondisi — syarat diabaikan mesin.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Footer wizard ── -->
            <template #footer>
                <button class="wca-btn wca-btn--ghost" type="button" @click="tutupModal"><i class="bi bi-x-circle"></i> Batal</button>
                <span style="flex:1"></span>
                <button v-if="langkah > 0" class="wca-btn wca-btn--ghost" type="button" @click="langkah--"><i class="bi bi-arrow-left"></i> Kembali</button>
                <button v-if="langkah < langkahMeta.length - 1" class="wca-btn wca-btn--primary" type="button" @click="maju">Lanjut <i class="bi bi-arrow-right"></i></button>
                <button v-else class="wca-btn wca-btn--primary" type="button" @click="save"><i class="bi bi-check-circle-fill"></i> {{ editingId ? 'Perbarui' : 'Buat Program' }}</button>
            </template>
        </AdminModal>

        <!-- ══ CARI REKOMENDASI — PINDAH TANGAN LOKER ═════════════════════════
             Pintu untuk rekruternya sendiri: melepas lokernya karena cuti,
             pindah tugas, atau minta dibantu rekan.

             Centang per loker — satu panel melayani "lepas seluruh program"
             (centang semua) dan "bantu satu loker saja" (centang satu). -->
        <AdminModal
            :show="serahShow"
            icon="bi-arrow-left-right"
            :title="serahSaya ? 'Serah Terima Tugas Saya' : 'Serah Terima Loker'"
            :subtitle="serahJudul"
            :busy="serahSibuk"
            busy-label="Memindahkan…"
            foot-note="Kandidat ikut lokernya — tidak ada satu baris lamaran pun yang disentuh."
            @close="serahShow = false"
        >
            <div class="pgk-serah-bar">
                <label class="pgk-chk">
                    <input type="checkbox" :checked="serahSemua" @change="serahPilihSemua($event.target.checked)" />
                    <span>Pilih semua ({{ serahLoker.length }} loker)</span>
                </label>
                <span class="pgk-hint">Terpilih <b>{{ serahPilih.length }}</b></span>
            </div>

            <div class="pgk-serah-list">
                <label
                    v-for="l in serahLoker"
                    :key="l.id"
                    class="pgk-serah-item"
                    :class="{ on: serahPilih.includes(l.id) }"
                >
                    <input type="checkbox" :value="l.id" v-model="serahPilih" />
                    <div class="pgk-serah-item__body">
                        <strong>{{ tc(l.posisi) }}</strong>
                        <!-- Nama programnya disebut saat daftarnya lintas program:
                             tanpa itu dua loker "Staff IT Support" dari program
                             berbeda tampak seperti satu baris yang tercetak dua
                             kali. -->
                        <small>
                            <template v-if="l.programNama"><b>{{ l.programNama }}</b> &middot; </template>
                            {{ l.mppRef || '—' }} &middot; {{ l.kuota }} kursi
                        </small>
                    </div>
                    <span class="pgk-serah-item__pic">
                        <i class="bi bi-person-badge"></i>
                        {{ l.picKode || 'belum bertuan' }}
                    </span>
                </label>
            </div>

            <!-- SATU BIDANG PER BARIS, bukan dua berdampingan.
                 Nama penerima berikut kode dan jumlah MPP-nya panjang, dan
                 setengah lebar memotongnya jadi elipsis persis di bagian yang
                 membedakan satu orang dari yang lain. Alasannya pun bukan
                 sepotong kata: ia terbaca di riwayat serah terima berbulan-bulan
                 kemudian, oleh orang yang tidak ada di ruangan hari ini. -->
            <div class="pgk-serah-form">
                <div class="pgk-serah-field">
                    <label class="wca-field-lbl">Diserahkan kepada <span class="pgk-req">wajib</span></label>
                    <el-select
                        v-model="serahKe"
                        filterable remote clearable reserve-keyword
                        :remote-method="cariPenerima"
                        :loading="penerimaLoading"
                        placeholder="Pilih rekruter penerima…"
                        style="width:100%"
                        @focus="cariPenerima('')"
                    >
                        <el-option v-for="k in penerimaOptions" :key="k.value" :label="k.label" :value="k.value" />
                        <template #empty><div class="pgk-selempty">Belum ada rekan yang bisa dituju — akun penerima harus internal, aktif, dan sudah punya kode karyawan di Master Akun.</div></template>
                    </el-select>
                </div>
                <div class="pgk-serah-field">
                    <label class="wca-field-lbl">Alasan <span class="pgk-req">wajib</span></label>
                    <el-input
                        v-model="serahAlasan"
                        type="textarea"
                        :autosize="{ minRows: 3, maxRows: 6 }"
                        resize="vertical"
                        placeholder="mis. cuti melahirkan mulai 1 September, ditangani sementara sampai Desember"
                        maxlength="500"
                        show-word-limit
                    />
                    <p class="pgk-serah-hint">
                        <i class="bi bi-info-circle"></i>
                        Tercatat di riwayat loker dan terbaca oleh siapa pun yang membukanya nanti — sebutkan sebabnya, bukan cuma "diserahkan".
                    </p>
                </div>
            </div>

            <template #footer>
                <button class="wca-btn wca-btn--ghost" type="button" :disabled="serahSibuk" @click="serahShow = false">
                    <i class="bi bi-x-lg"></i> Batal
                </button>
                <button class="wca-btn wca-btn--dark" type="button" :disabled="!bolehSerah" @click="simpanSerah">
                    <span v-if="serahSibuk" class="wca-spin" aria-hidden="true"></span>
                    <i v-else class="bi bi-arrow-left-right"></i>
                    Serah Terima {{ serahPilih.length || '' }} loker
                </button>
            </template>
        </AdminModal>

        <!-- ═══ ALUR PROGRAM BERGANTI — SIAPA YANG TERDAMPAK ═══════════════
             Muncul HANYA bila alurnya benar-benar berubah DAN memang ada
             pelamar berjalan yang terdampak. Server yang memutuskan itu, bukan
             layar: tanpa seorang pun terdampak, modal berisi angka nol semuanya
             cuma menambah satu klik pada pekerjaan yang tidak menyangkut siapa
             pun.

             Yang tidak ikut disebut SATU PER SATU beserta sebabnya. Angka
             ringkas saja meninggalkan pertanyaan "yang mana, dan kenapa" —
             justru pertanyaan yang paling sering ditanyakan sesudahnya. -->
        <AdminModal
            v-if="dampakShow"
            :show="dampakShow"
            :title="dampak?.alurSama ? 'Ada kandidat yang belum ikut alur ini' : 'Alur program diganti'"
            :subtitle="dampakSub"
            icon="bi-signpost-split-fill"
            lg
            @close="dampakShow = false"
        >
            <div class="pgk-dmp">
                <div class="pgk-dmp__kpi">
                    <div class="pgk-dmp__kartu is-ikut">
                        <span class="pgk-dmp__n">{{ dampak?.ikut ?? 0 }}</span>
                        <span class="pgk-dmp__lbl">Bisa dipindahkan</span>
                        <small>Masih punya tahap yang sama di alur baru</small>
                    </div>
                    <div class="pgk-dmp__kartu is-skip">
                        <span class="pgk-dmp__n">{{ dampak?.tidak ?? 0 }}</span>
                        <span class="pgk-dmp__lbl">Dilewati</span>
                        <small>Tetap menyelesaikan alur lamanya</small>
                    </div>
                    <div class="pgk-dmp__kartu is-total">
                        <span class="pgk-dmp__n">{{ (dampak?.ikut ?? 0) + (dampak?.tidak ?? 0) }}</span>
                        <span class="pgk-dmp__lbl">Total terdampak</span>
                        <small>Pelamar di program ini</small>
                    </div>
                </div>

                <!-- Bilah proporsi: satu baris yang menjawab "seberapa besar
                     bagian yang ikut" tanpa perlu membagi dua angka sendiri. -->
                <div v-if="dampakTotal" class="pgk-dmp__bar">
                    <div class="pgk-dmp__bar-ikut" :style="{ width: dampakPersen + '%' }"></div>
                </div>

                <!-- Keadaan "alur program sudah yang baru, orangnya belum".
                     Lahir dari penyimpanan sebelumnya yang memilih tidak
                     memindahkan siapa pun — dan dulu tidak punya pintu keluar
                     sama sekali, karena modal ini hanya muncul saat kolom alur
                     berubah. -->
                <div v-if="dampak?.alurSama" class="wca-note wca-note--warn">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>
                        Alur program <b>sudah</b> {{ dampak?.alurBaru }}, tapi pelamar di bawah masih berjalan di
                        alur lama — itulah yang mereka lihat di portalnya. Pindahkan bila mereka memang harus
                        mengikuti alur yang sekarang.
                    </span>
                </div>

                <div class="wca-note wca-note--info">
                    <i class="bi bi-info-circle"></i>
                    <span>
                        Yang <b>sudah diterima, gugur, atau mundur tidak pernah ikut</b> — riwayatnya tidak
                        disentuh sama sekali. Tahap yang sudah dijalani juga tidak ditulis ulang; yang
                        disusun ulang hanya tahap yang belum dikerjakan.
                    </span>
                </div>

                <div v-if="dampakTidakIkut.length" class="pgk-dmp__list">
                    <div class="pgk-dmp__ttl">
                        <i class="bi bi-person-dash"></i> Dilewati ({{ dampakTidakIkut.length }})
                    </div>
                    <div v-for="r in dampakTidakIkut" :key="r.kode" class="pgk-dmp__baris">
                        <span class="pgk-dmp__orang">
                            <b>{{ r.nama || r.kode }}</b>
                            <small v-if="r.tahap">{{ r.tahap }}</small>
                        </span>
                        <span class="pgk-dmp__sebab">{{ r.sebab }}</span>
                    </div>
                </div>

                <div v-if="dampakIkut.length" class="pgk-dmp__list is-ikut">
                    <div class="pgk-dmp__ttl">
                        <i class="bi bi-person-check"></i> Ikut pindah ({{ dampakIkut.length }})
                    </div>
                    <div v-for="r in dampakIkut" :key="r.kode" class="pgk-dmp__baris">
                        <span class="pgk-dmp__orang">
                            <b>{{ r.nama || r.kode }}</b>
                            <small v-if="r.tahap">{{ r.tahap }}</small>
                            <!-- Tahap yang sudah ia lewati dan tidak ada di alur
                                 tujuan. Barisnya TETAP di timeline-nya dengan nama
                                 lama — itu bukti pekerjaan yang benar-benar
                                 terjadi, dan admin yang membuka portalnya besok
                                 berhak tahu kenapa namanya berbeda. -->
                            <small v-if="r.riwayatLain && r.riwayatLain.length" class="pgk-dmp__riwayat">
                                riwayat tetap: {{ r.riwayatLain.join(', ') }}
                            </small>
                        </span>
                        <span class="pgk-dmp__ok"><i class="bi bi-arrow-right-short"></i> alur baru</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <button type="button" class="wca-btn wca-btn--ghost" :disabled="saving" @click="dampakShow = false">
                    Batal
                </button>
                <button type="button" class="wca-btn wca-btn--soft" :disabled="saving" @click="simpanDenganPilihan('TIDAK')">
                    <i class="bi bi-people"></i> Simpan — semua tetap di alur lama
                </button>
                <button type="button" class="wca-btn wca-btn--primary" :disabled="saving || !dampak?.ikut" @click="simpanDenganPilihan('SEMUA')">
                    <i class="bi bi-arrow-right-circle"></i> Simpan + pindahkan {{ dampak?.ikut ?? 0 }} pelamar
                </button>
            </template>
        </AdminModal>

        <!-- Perintah ini MEMBUANG penimpaan per loker, dan itu harus disebut
             sebelum ditekan: tanpa membuangnya, "terapkan ke semua" berbohong —
             sebagian loker tetap memakai template lamanya. -->
        <ConfirmModal
            :show="!!skrTanya"
            title="Terapkan ke seluruh loker?"
            :subtitle="skrTanya?.nama || ''"
            icon="bi-arrow-down-up"
            :danger="!!skrTanya?.timpa"
            confirm-label="Ya, Terapkan"
            confirm-icon="bi-check2"
            :note="skrTanya?.timpa
                ? `${skrTanya.timpa} loker yang saat ini memakai template berbeda akan IKUT diseragamkan — penimpaannya dibuang.`
                : 'Seluruh loker memakai template ini. Pelamar yang sudah berjalan tidak berubah.'"
            :busy="skrBusy"
            @cancel="skrTanya = null"
            @confirm="terapkanSemua"
        />

        <ConfirmModal :show="delShow" title="Hapus Program" :busy="deleting" confirm-label="Ya, Hapus" note="Program beserta batch, posisi & kriteria-nya akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus program <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <!-- GUARD INFO DIVISI — daftar divisi yang belum terisi + tombol isi langsung -->
        <div v-if="guardDivisiShow" class="gid-overlay" @click.self="guardDivisiShow = false">
            <div class="gid-modal">
                <div class="gid-modal__head">
                    <span class="gid-modal__ico"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    <div>
                        <h3>Info Divisi Belum Lengkap</h3>
                        <p>Lengkapi informasi divisi berikut dulu sebelum lanjut. Program yang sedang dibuat <b>tetap tersimpan</b> saat Anda mengisinya.</p>
                    </div>
                    <button type="button" class="gid-modal__x" @click="guardDivisiShow = false"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="gid-list">
                    <div v-for="d in divisiBelum" :key="d.id" class="gid-item">
                        <span class="gid-item__l"><i class="bi bi-diagram-3"></i> <b>{{ d.nama }}</b> <span class="gid-item__badge">{{ d.alasan || 'belum lengkap' }}</span></span>
                        <button type="button" class="gid-item__btn" @click="gotoInfoDivisi(d.id)"><i class="bi bi-pencil-square"></i> Isi Sekarang</button>
                    </div>
                </div>
                <div class="gid-modal__foot">
                    <button type="button" class="wca-btn wca-btn--ghost" @click="guardDivisiShow = false"><i class="bi bi-arrow-left"></i> Nanti Dulu</button>
                    <button type="button" class="wca-btn wca-btn--primary" @click="gotoInfoDivisi(divisiBelum[0] && divisiBelum[0].id)"><i class="bi bi-box-arrow-up-right"></i> Buka Master Info Divisi</button>
                </div>
            </div>
        </div>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import RefSelect from '@career/RefSelect.vue';
import { layarLebar } from '@utils/layarLebar';
import { layarSempit } from '@utils/layarSempit';
import { tgl, tglJam } from '@utils/tanggal';
import { skemaDariFormulir, semuaField } from '@career/formulir';
import { ingatModal } from '@utils/ingatModal';

// Salinan lokal (dulu impor dari folder monitoring-mpp yang sudah dihapus —
// fitur itu digabung ke master-mpp, folder ini tetap butuh helper kecilnya).
function titleCase(s) {
    return (s || '').toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
}

const API = '/api/v1/program-kegiatan';
const CFG = { headers: { Accept: 'application/json' } };
// Draft "Buat Program" disimpan di sessionStorage — tahan refresh/pindah halaman,
// hanya dibuang saat Batal/tutup modal atau setelah simpan sukses (BUKAN saat edit).
const DRAFT_KEY = 'evo_pgk_draft_v1';

/** Baris program per halaman di panel kiri. */
const PER_HALAMAN = 10;

/** Gradien & bayangan kartu posisi — berputar tiga warna, sama seperti rancangan. */
const PRG_GRAD = [
    'linear-gradient(135deg,#818cf8,#4338ca)',
    'linear-gradient(135deg,#a78bfa,#7c3aed)',
    'linear-gradient(135deg,#fbbf24,#d97706)',
];
const PRG_RING = ['rgba(79,70,229,.28)', 'rgba(124,58,237,.28)', 'rgba(217,119,6,.28)'];

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [layarSempit(), ingatModal('admin/program-kegiatan/programKegiatan')],
    data() {
        return {
            list: [],
            loading: false,
            // Program yang sedang dibaca di panel kanan. Menggantikan `open`
            // milik akordion lama.
            sel: null,
            tab: '',
            query: '',

            // ── panel kiri (jelajah) ──
            cakupan: 'SEMUA',
            urutTurun: true,
            halaman: 1,

            // ── panel posisi ──
            tampilan: 'kartu',
            posisiId: null,
            muatPosisi: false,

            // ── PHONE SCREENING ──────────────────────────────────────────
            // Dimuat terpisah dari daftar program, bukan ikut di dalamnya:
            // isinya hanya berarti untuk SATU program yang sedang dibuka, dan
            // menyertakannya di daftar berarti seluruh alur setiap program
            // ikut terbaca setiap kali halaman dibuka.
            skrAktivitas: [],
            skrTemplate: [],
            skrIkatan: [],
            skrPosisi: [],
            skrMuat: false,
            skrBuka: null,
            skrTanya: null,
            skrBusy: false,
            mppPosisi: null,

            // Tab kategori dari DB (sudah disaring hak akses) + Filter Panel.
            kategoriTab: [],
            totalSemua: 0,
            // `status` sengaja TIDAK ada di sini: pilihan Semua/Berjalan/Draf/
            // Selesai sudah dijawab lingkup di atas daftar. Dua kendali untuk
            // satu pertanyaan — apalagi yang satu menyaring di server dan yang
            // lain di layar — hanya membuat orang bertanya mana yang menang.
            filters: { q: '', pic: null, rentang: null },
            sheetOpen: false,
            cariTimer: null,
            show: false,
            editingId: null,
            // Wizard: langkah aktif + apakah seksi opsional diaktifkan.
            langkah: 0,
            // Langkah "skrining" disaring keluar bila alurnya memang tidak
            // memuat tahap skrining — lihat computed langkahMeta.
            langkahSemua: [
                { k: 'identitas', judul: 'Identitas', ikon: 'bi-tags', sub: 'Kategori dulu — alur & jadwal mengikutinya.' },
                { k: 'posisi', judul: 'Posisi', ikon: 'bi-briefcase', sub: 'Tautkan lowongan dari MPP yang sudah disetujui.' },
                { k: 'tambahan', judul: 'Tambahan', ikon: 'bi-sliders', opsional: true, sub: 'Batch & syarat auto-gugur — boleh dilewati.' },
                { k: 'skrining', judul: 'Phone Screening', ikon: 'bi-telephone-inbound', sub: 'Alur ini memakai skrining — tentukan kuesioner tiap loker.' },
                { k: 'pratinjau', judul: 'Pratinjau', ikon: 'bi-eye', sub: 'Periksa ringkasan akhir sebelum disimpan.' },
            ],

            // ── LANGKAH PHONE SCREENING ──────────────────────────────────
            //
            // Loker ditunjuk lewat MPP_REF, bukan Id: saat wizard berjalan,
            // lokernya belum tersimpan dan Id-nya memang belum ada.
            wzSkrAktivitas: [],
            wzSkrTemplate: [],
            wzSkrPilih: {},
            wzSkrBuka: null,
            wzSkrMuat: false,
            pakaiBatch: false,
            pakaiSyarat: false,
            form: { nama: '', kategori: '', warna: '#4f46e5', mode: 'ROLLING', alur: '', jadwal: '', penyelenggara: '', picKodeKaryawan: null, status: 'DRAFT', batch: [], posisi: [], syarat: [] },
            picOptions: [],
            picLoading: false,

            // ── SERAH TERIMA ──
            serahShow: false,
            // Mode "seluruh pekerjaan saya" — mengubah judul, isi daftar, dan
            // apakah nama program ikut disebut per baris.
            serahSaya: false,
            serahProgram: null,
            serahLoker: [],
            serahPilih: [],
            serahKe: null,
            serahAlasan: '',
            serahSibuk: false,
            // Daftar sendiri, TERPISAH dari picOptions. Keduanya menjawab
            // pertanyaan berbeda ("siapa yang saya tugaskan" vs "siapa yang
            // menerima pekerjaan saya") dan isinya memang tidak sama — berbagi
            // satu larik membuat yang satu diam-diam menimpa yang lain.
            penerimaOptions: [],
            penerimaLoading: false,

            // Pilihan jadwal DIMILIKI halaman ini, bukan komponen selectnya.
            // Sebabnya melingkar: kotaknya hanya digambar bila ada pilihan, jadi
            // yang menghitung pilihan tidak boleh berada di dalam kotak itu —
            // ia tak akan pernah sempat memuat apa pun.
            jadwalOpsi: [],
            jadwalMuat: false,
            tahapFormulir: [],
            fieldTurunan: [],
            // Kartu MPP (langkah Posisi) + cache opsi dinamis field pilihan (mis. kampus).
            mppOptions: [],
            // Tab lingkup MPP: 'SAYA' (bawaan) | 'SEMUA'. Bawaannya yang bisa
            // dikerjakan langsung; milik orang lain tetap satu klik jauhnya.
            mppLingkup: 'SAYA',
            // Lingkup PIC pengguna pada halaman ini — datang bersama daftar
            // programnya, jadi tidak ada permintaan tambahan hanya untuk tahu
            // apakah sebuah bidang perlu digambar.
            akses: { lingkupPic: 'SEMUA', bolehSerahTerima: false, kodeSaya: null, namaSaya: null },
            mppCari: '',
            opsiSumber: {},
            palette: ['#4f46e5', '#7c3aed', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#64748b'],
            operatorOptions: [
                { value: '>=', label: 'minimal (>=)', desc: 'Jawaban harus ≥ angka ini' },
                { value: '<=', label: 'maksimal (<=)', desc: 'Jawaban harus ≤ angka ini' },
                { value: '=', label: 'sama dengan', desc: 'Jawaban persis sama dengan nilai' },
                { value: '!=', label: 'tidak sama dengan', desc: 'Jawaban apa pun selain nilai ini' },
                { value: '>', label: 'lebih dari', desc: 'Lebih besar, tanpa termasuk nilainya' },
                { value: '<', label: 'kurang dari', desc: 'Lebih kecil, tanpa termasuk nilainya' },
                { value: 'ANTARA', label: 'antara', desc: 'Berada dalam rentang dari–sampai' },
                { value: 'ADA_DI', label: 'salah satu dari', desc: 'Jawaban termasuk daftar yang dicentang' },
                { value: 'TIDAK_ADA_DI', label: 'bukan salah satu dari', desc: 'Jawaban di luar daftar yang dicentang' },
            ],
            presets: [],
            // Contoh pesan penolakan bernada hangat — tombol "Pakai contoh" mengisi ini.
            contohPesan: 'Terima kasih sudah mendaftar di EVO Group. Setelah kami tinjau, untuk kesempatan kali ini Anda belum memenuhi kualifikasi yang dibutuhkan. Kami sangat menghargai minat & waktu Anda, dan berharap dapat bertemu kembali di kesempatan berikutnya. Sukses selalu! 🙏',
            delShow: false,
            // ── NASIB ROMBONGAN SAAT ALUR PROGRAM DIGANTI ──────────────────
            // Terisi hanya bila alurnya BENAR-BENAR berubah dan memang ada
            // pelamar yang terdampak; server yang memutuskan itu.
            dampakShow: false,
            dampak: null,
            dampakPayload: null,
            delTarget: null,
            deleting: false,
            // Guard Info Divisi: daftar divisi yang belum punya info + modal-nya.
            divisiBelum: [],
            guardDivisiShow: false,
            saving: false,
            toast: '',
            tm: null,
        };
    },
    computed: {
        /* ── Ringkasan dampak penggantian alur ────────────────────────────── */
        dampakRincian() { return this.dampak?.rincian || []; },
        dampakIkut() { return this.dampakRincian.filter((r) => r.ikut); },
        dampakTidakIkut() { return this.dampakRincian.filter((r) => !r.ikut); },
        dampakTotal() { return (this.dampak?.ikut ?? 0) + (this.dampak?.tidak ?? 0); },
        dampakSub() {
            if (!this.dampak) return '';

            return this.dampak.alurSama
                ? `masih di alur lama → ${this.dampak.alurBaru}`
                : `${this.dampak.alurLama || 'alur lama'} → ${this.dampak.alurBaru}`;
        },
        dampakPersen() {
            return this.dampakTotal ? Math.round(((this.dampak?.ikut ?? 0) / this.dampakTotal) * 100) : 0;
        },
        /**
         * Langkah yang benar-benar digambar.
         *
         * "Phone Screening" disaring keluar saat alurnya tidak memuat tahap
         * skrining. Menampilkannya dalam keadaan kosong lebih buruk daripada
         * tidak menampilkannya: ia menuntut satu klik "Lanjut" untuk sesuatu
         * yang tidak pernah bisa diisi, dan menimbulkan kesan ada yang
         * terlewat.
         */
        langkahMeta() {
            return this.langkahSemua.filter(
                (x) => x.k !== 'skrining' || this.wzSkrAktivitas.length > 0,
            );
        },
        /** Kunci langkah aktif — dipakai v-show supaya nomor tidak pernah dihitung. */
        langkahKini() {
            return this.langkahMeta[this.langkah]?.k || 'identitas';
        },
        /**
         * Kotak Jadwal digambar?
         *
         * Saat masih memuat pun tetap digambar, supaya barisnya tidak melebar lalu
         * menyempit lagi sepersekian detik kemudian — pergeseran tata letak yang
         * membuat orang kehilangan tempat ia sedang mengetik.
         */
        /**
         * Bidang "Penanggung Jawab Program" ada gunanya?
         *
         * Hanya bila ada ORANG LAIN yang boleh ditugaskan. Pada lingkup SENDIRI
         * daftarnya cuma berisi diri sendiri — dan menugaskan program kepada
         * diri sendiri adalah keadaan bawaannya, bukan pilihan.
         */
        /** Orang yang sedang dipilih di bidang PIC — beserta jumlah MPP-nya. */
        picTerpilih() {
            if (!this.form.picKodeKaryawan) return null;

            return this.picPilihan.find((k) => k.value === this.form.picKodeKaryawan) || null;
        },
        /**
         * Peringatan "PIC ini belum punya MPP" HANYA saat angkanya diketahui.
         *
         * Baris diri sendiri yang disisipkan picPilihan membawa jmlMpp = null —
         * artinya "belum ditanyakan ke server", bukan "nol". Diperlakukan sama,
         * wizard akan menuduh setiap orang yang baru membukanya tidak punya MPP,
         * padahal daftarnya memang belum dimuat.
         */
        picTanpaMpp() {
            return Number.isFinite(this.picTerpilih?.jmlMpp) && this.picTerpilih.jmlMpp === 0;
        },
        /**
         * Daftar pilihan PIC yang SELALU memuat diri sendiri.
         *
         * Dua alasan, dan keduanya nyata:
         *
         *   1. Bidang ini kini bawaannya diri sendiri. Kalau daftar dari server
         *      belum termuat (dropdown belum pernah dibuka), el-select tidak
         *      punya opsi untuk nilai itu dan menggambar kode mentahnya.
         *   2. Pada lingkup SENDIRI bidangnya terkunci — dan yang terkunci
         *      tetap harus terbaca sebagai NAMA, bukan sebagai "H45".
         *
         * `jmlMpp` sengaja tidak ditebak untuk baris diri sendiri: angkanya
         * datang dari server, dan menuliskan 0 di sini akan memicu peringatan
         * "PIC ini belum punya MPP" yang belum tentu benar.
         */
        picPilihan() {
            const saya = this.akses.kodeSaya;
            if (!saya || this.picOptions.some((k) => k.value === saya)) {
                return this.picOptions;
            }

            return [{
                value: saya,
                nama: this.akses.namaSaya || saya,
                label: this.akses.namaSaya || saya,
                jmlMpp: null,
            }, ...this.picOptions];
        },
        /**
         * PIC yang MENENTUKAN daftar MPP di Langkah 2.
         *
         * Sama persis dengan urutan warisan di server (picLokerBaru): PIC program
         * bila ditugaskan, kalau tidak diri sendiri. Kalau keduanya berbeda,
         * kartu yang dipilih di Langkah 2 akan jadi milik orang yang tidak
         * pernah memilihnya.
         */
        picEfektif() {
            return this.form.picKodeKaryawan || this.akses.kodeSaya || '';
        },
        /**
         * Boleh menugaskan program kepada ORANG LAIN?
         *
         * HANYA lingkup PIC yang menentukan. `bolehSerahTerima` sempat ikut
         * dihitung di sini, dan itu keliru: rekruter berlingkup SENDIRI yang
         * kebetulan memegang izin serah terima jadi melihat pemilih berisi
         * SELURUH akun rekruter — lalu benar-benar bisa membuat program atas
         * nama orang lain.
         *
         * Serah terima adalah wewenang atas pekerjaan YANG SUDAH ADA DAN MEMANG
         * miliknya, lewat panelnya sendiri, dan tercatat sebagai peristiwa.
         * Menugaskan program baru atas nama orang lain adalah hal yang berbeda,
         * dan itulah yang dijaga lingkup PIC.
         *
         * Gerbang sebenarnya tetap di server (galatPicProgram) — ini cuma
         * menghindarkan orang dari kotak yang setiap isiannya akan ditolak.
         */
        bolehTugaskanPic() {
            return this.akses.lingkupPic !== 'SENDIRI';
        },
        /**
         * SELURUH loker yang tanggung jawabnya ada pada saya — lintas program.
         *
         * Dihitung dari data yang sudah dimuat halaman ini, bukan lewat
         * permintaan baru: daftar programnya memang sudah membawa `posisi`
         * berikut `picKode` masing-masing.
         *
         * Kosong bila akun belum punya kode karyawan. Itu bukan kelalaian yang
         * perlu ditutupi — tanpa kode itu tidak ada satu loker pun yang bisa
         * dikatakan miliknya, jadi tombolnya memang tidak boleh ada.
         */
        /** Loker di program yang sedang dibuka yang memang di tangan saya. */
        lokerSayaDiSini() {
            const saya = this.akses.kodeSaya;
            if (!saya || !this.terpilih) return [];

            return (this.terpilih.posisi || []).filter((l) => l.id && l.picKode === saya);
        },
        /**
         * Pita keterangan kepemilikan — null bila tak ada yang perlu dikatakan.
         *
         * Tiga keadaan, dan hanya dua yang bicara:
         *
         *   masih milik saya          diam. Tombolnya ada, tidak ada yang aneh.
         *   pernah saya serahkan      pita amber: "Sudah Anda serahterimakan".
         *   memang bukan milik saya   pita netral: sekadar menyebut pemegangnya.
         *
         * Yang kedua paling penting: di situlah tombol Serah Terima menghilang,
         * dan tanpa keterangan orang mengira haknya dicabut diam-diam.
         */
        pitaSerah() {
            const t = this.terpilih;
            const saya = this.akses.kodeSaya;
            const loker = (t?.posisi || []).filter((l) => l.id);
            if (!t || !loker.length || this.lokerSayaDiSini.length) return null;

            // Perpindahan terbaru yang asalnya DARI saya.
            const dariSaya = loker
                .filter((l) => l.serah && saya && l.serah.dariKode === saya)
                .sort((a, b) => String(b.serah.pada).localeCompare(String(a.serah.pada)));

            const pemegang = [...new Set(loker.map((l) => l.picNama || l.picKode).filter(Boolean))];
            const namaPemegang = pemegang.length === 1 ? pemegang[0] : `${pemegang.length} rekruter`;

            if (dariSaya.length) {
                const sr = dariSaya[0].serah;
                const jml = dariSaya.length;

                return {
                    jenis: 'serah',
                    ikon: 'bi-arrow-left-right',
                    judul: `Sudah Anda serahterimakan ke ${sr.ke}`,
                    sub: jml === loker.length
                        ? `Seluruh ${jml} loker program ini kini di tangannya — termasuk kandidat yang sedang berjalan.`
                        : `${jml} dari ${loker.length} loker berpindah. Sisanya dipegang rekruter lain.`,
                    alasan: sr.alasan,
                    dari: sr.dari,
                    ke: sr.ke,
                    pada: sr.pada,
                    oleh: sr.oleh,
                    sumber: sr.sumber,
                };
            }

            if (!pemegang.length) return null;

            return {
                jenis: 'lain',
                ikon: 'bi-person-badge',
                judul: `Dipegang ${namaPemegang}`,
                sub: 'Program ini bukan tanggung jawab akun Anda — Anda bisa melihatnya, tapi tidak menyerahterimakannya.',
                alasan: loker.find((l) => l.serah)?.serah?.alasan || null,
                dari: loker.find((l) => l.serah)?.serah?.dari || '—',
                ke: namaPemegang,
                pada: loker.find((l) => l.serah)?.serah?.pada || null,
                oleh: loker.find((l) => l.serah)?.serah?.oleh || '—',
                sumber: loker.find((l) => l.serah)?.serah?.sumber || 'ADMIN',
            };
        },
        lokerSaya() {
            const saya = this.akses.kodeSaya;
            if (!saya) return [];

            const out = [];
            (this.list || []).forEach((p) => {
                (p.posisi || []).forEach((l) => {
                    if (l.id && l.picKode === saya) out.push({ ...l, programNama: p.nama });
                });
            });

            return out;
        },
        serahJudul() {
            if (this.serahSaya) {
                return `${this.lokerSaya.length} loker dari ${new Set(this.lokerSaya.map((l) => l.programNama)).size} program`;
            }

            return this.serahProgram ? this.serahProgram.nama : '';
        },
        serahSemua() {
            return this.serahLoker.length > 0 && this.serahPilih.length === this.serahLoker.length;
        },
        /** Alasan minimal 5 huruf — sama dengan gerbang di server. */
        bolehSerah() {
            return !this.serahSibuk
                && this.serahPilih.length > 0
                && !!this.serahKe
                && (this.serahAlasan || '').trim().length >= 5;
        },
        tampilJadwal() {
            // `form.jadwal` ikut dihitung: bidang yang SUDAH BERISI tidak boleh
            // disembunyikan. Jadwal yang dinonaktifkan setelah program ini dibuat
            // akan lenyap dari daftar pilihan, dan menyembunyikan kotaknya berarti
            // nilainya ikut tak terlihat — tetap tersimpan, tapi tak bisa dilihat
            // maupun dilepas oleh siapa pun.
            return this.jadwalMuat || this.jadwalOpsi.length > 0 || !!this.form.jadwal;
        },
        // Penyaringan (kategori, pencarian, status, tanggal) SUDAH dikerjakan
        // backend — `list` di sini sudah bersih. Sisanya hanya pemenggalan halaman.
        // Penyaringan (kategori, pencarian, status, tanggal) SUDAH dikerjakan
        // backend — `list` di sini sudah bersih.
        aktif() { return this.list.filter((p) => p.status === 'BERJALAN').length; },
        /** Total kursi yang DIBUKA seluruh program. Bukan yang terisi. */
        totalKuotaPosisi() { return this.list.reduce((n, p) => n + this.totalKuota(p), 0); },

        /*
         * ══ PANEL KIRI ══════════════════════════════════════════════════════
         * Penyaringan kategori/pencarian/status/tanggal dikerjakan SERVER;
         * yang di bawah ini hanya pengelompokan tampilan atas `list` yang sudah
         * bersih — supaya membalik urutan atau berpindah lingkup tidak membuat
         * daftar berkedip karena permintaan ulang yang tidak perlu.
         */
        lingkup() {
            const n = (s) => this.list.filter((p) => p.status === s).length;

            return [
                { kode: 'SEMUA', label: 'Semua', jml: this.list.length },
                { kode: 'BERJALAN', label: 'Berjalan', jml: n('BERJALAN') },
                { kode: 'DRAFT', label: 'Draf', jml: n('DRAFT') },
                // Dulu status "Selesai" hanya bisa dicapai lewat select di panel
                // filter — dua kendali untuk satu pertanyaan, dan yang satu
                // dikirim ke server sementara yang lain menyaring di layar.
                // Sekarang lingkupnya lengkap dan selectnya dibuang.
                { kode: 'SELESAI', label: 'Selesai', jml: n('SELESAI') },
            ];
        },
        tersaring() {
            let out = this.cakupan === 'SEMUA' ? this.list : this.list.filter((p) => p.status === this.cakupan);

            // Disaring DI LAYAR, bukan di server: kepemilikan loker sudah ikut
            // tiap baris (posisi[].picKode), dan satu perjalanan jaringan untuk
            // menyaring data yang sudah ada hanya membuat daftar berkedip.
            if (this.filters.pic) out = out.filter((p) => this.dipegang(p, this.filters.pic));

            return this.urutTurun ? out : [...out].reverse();
        },
        /*
         * ══ PAGINASI PANEL KIRI ═════════════════════════════════════════════
         * Dipotong di SISI LAYAR: penyaringan (kategori, pencarian, status,
         * tanggal) sudah dikerjakan server, dan datanya sudah ada — meminta
         * ulang hanya untuk berpindah halaman membuat daftar berkedip.
         */
        /**
         * Saringan "Pemegang Loker" ada gunanya digambar?
         *
         * TIDAK pada lingkup SENDIRI: apa pun yang tampil di sana sudah pasti
         * miliknya, jadi menyaringnya lagi tidak mengubah satu baris pun —
         * kotak berisi satu pilihan yang sudah pasti hanya menyisakan
         * pertanyaan apa gunanya.
         *
         * TIDAK juga bila lokernya cuma dipegang satu orang: tak ada yang bisa
         * dibedakan dari siapa pun.
         */
        adaSaringPemegang() {
            return this.akses.lingkupPic !== 'SENDIRI' && this.pemegangOpsi.length > 1;
        },
        /**
         * Pilihan pemegang — DARI DATA YANG SEDANG TAMPIL, bukan dari daftar
         * karyawan.
         *
         * Ini keputusan yang sempat salah dan diperbaiki. Versi sebelumnya
         * menurunkan seluruh rekan sedivisi dari server; pada data nyata di
         * sini satu divisi berisi 105 orang, dan dropdown 105 baris yang
         * sebagian besarnya tidak memegang satu loker pun bukan saringan —
         * itu daftar telepon.
         *
         * Dirakit dari picKode yang MEMANG muncul di baris yang dimuat, jadi
         * tiap pilihan dijamin menghasilkan sedikitnya satu hasil, dan
         * panjangnya mengikuti pekerjaan yang ada — bukan besarnya divisi.
         */
        pemegangOpsi() {
            const peta = new Map();

            this.list.forEach((p) => (p.posisi || []).forEach((l) => {
                if (l.picKode && !peta.has(l.picKode)) {
                    peta.set(l.picKode, { kode: l.picKode, nama: l.picNama || l.picKode });
                }
            }));

            const saya = this.akses.kodeSaya;
            const daftar = [...peta.values()].sort((a, b) => a.nama.localeCompare(b.nama));

            // Diri sendiri dinaikkan ke atas dan diberi label yang menyebut
            // dirinya — itu pilihan yang paling sering ditekan.
            return [
                ...daftar.filter((r) => r.kode === saya).map((r) => ({ ...r, nama: 'Saya sendiri' })),
                ...daftar.filter((r) => r.kode !== saya),
            ];
        },
        labelSemuaPemegang() {
            return this.akses.lingkupPic === 'TIM'
                ? `Semua tim (${this.pemegangOpsi.length} pemegang)`
                : `Semua pemegang (${this.pemegangOpsi.length})`;
        },
        totalHalaman() { return Math.max(1, Math.ceil(this.tersaring.length / PER_HALAMAN)); },
        halamanIni() {
            const dari = (this.halaman - 1) * PER_HALAMAN;

            return this.tersaring.slice(dari, dari + PER_HALAMAN);
        },
        rentangBaris() {
            const n = this.tersaring.length;
            if (!n) return '0 program';

            const dari = (this.halaman - 1) * PER_HALAMAN + 1;

            return `${dari}–${Math.min(dari + PER_HALAMAN - 1, n)} dari ${n}`;
        },
        /** Nomor halaman yang dicetak, dengan "…" bila jumlahnya banyak. */
        halamanTampil() {
            const t = this.totalHalaman;
            const h = this.halaman;
            if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1);

            const set = new Set([1, t, h, h - 1, h + 1]);
            if (h <= 3) [2, 3, 4].forEach((x) => set.add(x));
            if (h >= t - 2) [t - 3, t - 2, t - 1].forEach((x) => set.add(x));

            const urut = [...set].filter((x) => x >= 1 && x <= t).sort((a, b) => a - b);
            const out = [];
            urut.forEach((x, i) => {
                if (i && x - urut[i - 1] > 1) out.push('…');
                out.push(x);
            });

            return out;
        },
        grup() {
            const peta = [
                { kode: 'BERJALAN', label: 'BERJALAN' },
                { kode: 'DRAFT', label: 'DRAF' },
                { kode: 'SELESAI', label: 'ARSIP' },
            ];

            return peta
                .map((g) => ({ ...g, items: this.halamanIni.filter((p) => (p.status || 'DRAFT') === g.kode) }))
                .filter((g) => g.items.length);
        },

        /**
         * SEL INFORMASI PROGRAM — nilai PLUS penjelasannya.
         *
         * Yang kosong tidak cukup berbunyi "belum diatur": itu menyebut
         * ketiadaan tanpa menyebut akibatnya, dan pembacanya tidak punya cara
         * tahu apakah itu masalah atau memang wajar. Tiap sel karena itu
         * membawa satu baris nota yang menjawab "lalu kenapa".
         */
        selInfo() {
            const p = this.terpilih;
            if (!p) return [];

            const syarat = p.syarat || [];
            const gugur = this.jmlGugur(p);
            const posisi = (p.posisi || []).length;

            const jadwal = p.jadwalNama || null;
            const rentang = p.jadwalMulai
                ? `${tgl(p.jadwalMulai)} – ${tgl(p.jadwalSelesai || p.jadwalMulai)}`
                : null;

            return [
                {
                    label: 'PENYELENGGARA',
                    ikon: 'bi-building',
                    warna: '#6366f1',
                    nilai: p.penyelenggara || 'EVO Group',
                    kosong: false,
                    nota: p.penyelenggara ? 'Unit yang menjalankan program' : 'Bawaan grup — belum diisi khusus',
                },
                {
                    label: 'ALUR SELEKSI',
                    ikon: 'bi-diagram-3',
                    warna: '#8b5cf6',
                    nilai: p.alurNama || p.alur || 'Belum dipilih',
                    kosong: !(p.alurNama || p.alur),
                    nota: p.alurTahap
                        ? `${p.alurTahap} tahap dijalani tiap pelamar`
                        : (p.alurNama ? 'Alurnya belum punya tahap' : 'Tanpa alur, lamaran tidak bisa diproses'),
                },
                {
                    label: 'JADWAL KEGIATAN',
                    ikon: 'bi-calendar-week',
                    warna: '#f59e0b',
                    nilai: jadwal || 'Tidak ditautkan',
                    kosong: !jadwal,
                    // Jadwal itu OPSIONAL — agenda per tahap tetap bisa diatur
                    // dari Penjadwalan. Tanpa penjelasan ini, kosongnya terbaca
                    // seperti program yang belum siap jalan.
                    nota: jadwal
                        ? (rentang || `${p.jadwalAgenda || 0} agenda`)
                        : 'Opsional — agenda diatur per tes di Penjadwalan',
                },
                {
                    label: 'PENANGGUNG JAWAB',
                    ikon: p.picKodeKaryawan ? 'bi-person-badge-fill' : 'bi-person-dash',
                    warna: '#4f46e5',
                    nilai: p.picNama || p.picKodeKaryawan || 'Mengikuti pembuatnya',
                    kosong: !p.picKodeKaryawan,
                    sorot: !!p.picKodeKaryawan,
                    // Kosong BUKAN berarti tidak ada yang memegang. Server
                    // mewariskan loker baru ke pembuatnya (lihat picLokerBaru),
                    // jadi programnya tetap bertuan.
                    nota: p.picKodeKaryawan
                        ? 'Pemegang loker baru di program ini'
                        : `Loker baru jatuh ke ${p.createdBy || 'pembuat program'}`,
                },
                {
                    label: 'SYARAT PENYARINGAN',
                    ikon: syarat.length ? 'bi-sliders2' : 'bi-slash-circle',
                    warna: '#059669',
                    nilai: syarat.length ? `${syarat.length} aturan` : 'Tidak ada',
                    kosong: !syarat.length,
                    nota: syarat.length
                        ? `${gugur} menggugurkan otomatis · ${syarat.length - gugur} hanya menandai`
                        : 'Semua lamaran masuk diperiksa manual',
                },
                {
                    label: 'POSISI DIBUKA',
                    ikon: 'bi-briefcase',
                    warna: '#0891b2',
                    nilai: posisi ? `${posisi} posisi` : 'Belum ada',
                    kosong: !posisi,
                    nota: posisi
                        ? `${this.totalKuota(p)} kursi dibuka seluruhnya`
                        : 'Tanpa posisi, program tidak bisa diterbitkan',
                },
            ];
        },
        /** Program pernah disunting setelah dibuat? */
        pernahDiubah() {
            const p = this.terpilih;
            if (!p || !p.updatedAt || !p.createdAt) return false;

            // Dibandingkan sebagai stempel waktu, bukan string: server bisa
            // mengirim presisi milidetik yang berbeda untuk dua kolom yang
            // ditulis dalam satu transaksi yang sama.
            const a = new Date(String(p.createdAt).replace(' ', 'T')).getTime();
            const b = new Date(String(p.updatedAt).replace(' ', 'T')).getTime();

            return Number.isFinite(a) && Number.isFinite(b) && b - a > 60000;
        },
        terpilih() { return this.list.find((p) => p.id === this.sel) || null; },
        /** Di layar sempit panel kanan MENGGANTIKAN daftar, bukan mendampinginya. */
        selMobile() { return !!this.terpilih; },

        /** Posisi yang sedang dibuka detailnya. */
        posisiBuka() {
            if (!this.terpilih || this.posisiId === null) return null;

            return (this.terpilih.posisi || []).find((l) => l.id === this.posisiId) || null;
        },
        indexPosisi() {
            if (!this.terpilih || this.posisiId === null) return 0;

            return Math.max(0, (this.terpilih.posisi || []).findIndex((l) => l.id === this.posisiId));
        },
        kuotaSeluruhBatch() {
            return (this.terpilih?.batch || []).reduce((n, b) => n + (Number(b.kuota) || 0), 0);
        },
        /**
         * Kotak keterangan posisi. Sumbernya dua: baris posisi program dan
         * MPP-nya. Yang belum terisi tetap digambar bernilai "—" supaya bentuk
         * gridnya tidak berubah-ubah antar posisi.
         *
         * TIDAK ADA baris "terisi" di sini, dengan sengaja.
         */
        medanPosisi() {
            const l = this.posisiBuka;
            if (!l) return [];

            const m = this.mppPosisi || {};
            const p = this.terpilih;
            const isi = (v, teks) => ({ nilai: v || teks || '—', kosong: !v });

            return [
                { label: 'NO. MPP', ikon: 'bi-hash', warna: '#6366f1', ...isi(l.mppRef, 'tanpa MPP') },
                { label: 'DEPARTEMEN', ikon: 'bi-diagram-2', warna: '#8b5cf6', ...isi(l.departemen) },
                { label: 'LEVEL', ikon: 'bi-bar-chart-steps', warna: '#4f46e5', ...isi(l.level || m.level) },
                { label: 'JABATAN MPP', ikon: 'bi-person-workspace', warna: '#0891b2', ...isi(m.jabatan) },
                { label: 'LOKASI', ikon: 'bi-geo-alt-fill', warna: '#f59e0b', ...isi(m.lokasi || l.lokasi) },
                { label: 'TIPE KERJA', ikon: 'bi-buildings', warna: '#2563eb', ...isi(m.workplaceType) },
                { label: 'STATUS KERJA', ikon: 'bi-briefcase', warna: '#7c3aed', ...isi(m.employmentType) },
                { label: 'PENGALAMAN', ikon: 'bi-mortarboard', warna: '#8b5cf6', ...isi(m.experienceLevel) },
                { label: 'KUOTA', ikon: 'bi-people-fill', warna: '#6366f1', nilai: `${l.kuota} kursi`, kosong: false },
                { label: 'PIC POSISI', ikon: l.picKode ? 'bi-person-badge-fill' : 'bi-person-dash', warna: '#059669', ...isi(l.picKode, 'belum ditugaskan') },
                { label: 'ALUR SELEKSI', ikon: 'bi-diagram-3', warna: '#4f46e5', ...isi(p?.alurNama || p?.alur) },
                { label: 'PROGRAM', ikon: 'bi-folder2-open', warna: '#f59e0b', ...isi(p?.nama) },
            ];
        },

        adaFilter() {
            return !!(this.filters.q || this.filters.pic || (this.filters.rentang && this.filters.rentang.length));
        },
        jumlahFilter() {
            return [this.filters.q, this.filters.pic, this.filters.rentang?.length ? 1 : null].filter(Boolean).length;
        },

        /** Semua field selain Kategori terkunci sampai kategori dipilih. */
        terkunci() { return !this.form.kategori; },

        /**
         * Satu-satunya kategori yang boleh dipakai pengguna ini — atau null bila
         * ia berhak atas lebih dari satu.
         *
         * kategoriTab datang dari AksesService::tabKategori('programPage'), yang
         * sudah menyaring master terhadap hak akses. Jadi "berapa yang boleh"
         * memang dijawab basis data, bukan ditebak di layar.
         *
         * Dipakai untuk MENYEMBUNYIKAN pilihannya, bukan sekadar mengunci: kendali
         * yang tampil tapi cuma berisi satu baris hanya menyuruh orang menekan
         * sesuatu yang jawabannya sudah pasti.
         *
         * Ini kenyamanan, BUKAN pengaman. Siapa pun bisa mengetik kategori lain
         * lewat DevTools — yang menahannya adalah pemeriksaan di sisi server saat
         * menyimpan, bukan baris ini.
         */
        kategoriTunggal() {
            return this.kategoriTab.length === 1 ? this.kategoriTab[0] : null;
        },

        /**
         * Mode ubah. Dipakai untuk menyembunyikan field yang jawabannya sudah pasti
         * pada program baru: status lowongan (BUKA), status batch (AKTIF), dan
         * jumlah terisi (0). Menanyakannya saat membuat cuma bikin ragu.
         */
        mengubah() { return Boolean(this.editingId); },

        /** Pagu kursi = total kuota posisi yang ditarik dari MPP. */
        kuotaMpp() { return this.form.posisi.reduce((n, l) => n + (Number(l.kuota) || 0), 0); },

        /** MPP yang sedang dipakai baris posisi — sumber kebenaran tetap form.posisi. */
        mppTerpilih() { return this.form.posisi.filter((l) => l.mppRef).map((l) => l.mppRef); },

        /** Kartu MPP tersaring kotak cari (posisi/departemen/lokasi/nomor). */
        /** MPP yang PIC-nya masuk lingkup pengguna ini. */
        mppBoleh() {
            return this.mppOptions.filter((o) => o.boleh);
        },
        /** Sisanya — dipegang orang lain. */
        mppTerkunci() {
            return this.mppOptions.filter((o) => !o.boleh);
        },
        /**
         * Tab lingkup perlu digambar?
         *
         * Hanya bila memang ada yang di luar lingkup. Pengguna berlingkup SEMUA
         * tidak punya "MPP orang lain", jadi dua tab yang isinya sama persis
         * cuma menambah kendali yang tidak menjawab apa pun.
         */
        adaMppTerkunci() {
            return this.mppTerkunci.length > 0;
        },
        mppTersaring() {
            // Tab dulu, baru pencarian. Urutannya penting: mencari lebih dulu
            // lalu menyaring tab akan membuat angka pada tab ikut berubah
            // mengikuti kata kunci — dan "Lingkup Saya (3)" yang berubah jadi
            // (1) saat mengetik terbaca seperti MPP yang hilang.
            const dasar = this.mppLingkup === 'SAYA' && this.adaMppTerkunci
                ? this.mppBoleh
                : this.mppOptions;

            const q = this.mppCari.trim().toLowerCase();
            if (!q) return dasar;

            return dasar.filter((o) =>
                [o.value, o.posisi, o.departemen, o.lokasi, o.level, o.picNama].join(' ').toLowerCase().includes(q));
        },
        kuotaBatch() { return this.form.batch.reduce((n, b) => n + (Number(b.kuota) || 0), 0); },
        kuotaBatchLebih() { return this.kuotaMpp > 0 && this.kuotaBatch > this.kuotaMpp; },
    },
    watch: {
        langkahMeta(baru) {
            if (this.langkah > baru.length - 1) this.langkah = Math.max(0, baru.length - 1);
        },
        /**
         * Alur berganti → tanya ulang apakah ia memuat tahap skrining.
         *
         * Ditanyakan ke server, bukan disimpulkan dari nama tahapnya: yang
         * menentukan adalah penanda Flag_Skrining di Master Tipe Tahap, dan
         * menebaknya dari nama berarti tahap bernama "Screening Awal" yang
         * bertipe wawancara ikut terjaring.
         */
        'form.alur': {
            immediate: true,
            handler() { this.muatSkriningAlur(); },
        },
        // Kategori menentukan template mana yang boleh dipakai, jadi daftarnya
        // harus ikut dimuat ulang — bukan menunggu alurnya kebetulan berganti.
        'form.kategori'() { this.muatSkriningAlur(); },
        /* Pengikatan skrining ikut program yang sedang dibuka.
           Dipasang sebagai watcher, bukan di dalam pilih(): `sel` juga berubah
           dari load() saat daftar disegarkan, dan menaruhnya di satu metode
           berarti kartunya kosong setiap kali halaman dimuat ulang. */
        sel: {
            immediate: true,
            handler() { this.muatSkrining(); },
        },
        // Menyaring lalu tetap berada di halaman 7 memperlihatkan daftar kosong
        // yang terbaca seperti "tidak ada hasil".
        cakupan() { this.halaman = 1; },
        urutTurun() { this.halaman = 1; },
        'list.length'() { this.halaman = 1; },
        'filters.pic'() { this.halaman = 1; },

        /**
         * PIC berganti -> daftar MPP Langkah 2 ikut berganti.
         *
         * Posisi yang telanjur dipilih ikut dibuang: kartu itu milik MPP orang
         * sebelumnya, dan membiarkannya berarti program ini lahir membawa loker
         * yang tidak berhak dipegang penanggung jawab barunya.
         */
        'form.picKodeKaryawan'(baru, lama) {
            if (lama === undefined || baru === lama) return;

            if (this.form.posisi.length) {
                this.form.posisi = [];
                this.notice('Penanggung jawab diganti — daftar posisi dikosongkan, pilih ulang dari MPP miliknya.');
            }
            this.loadMppOptions();
        },
        // Persist draft BUAT (bukan edit) tiap ada perubahan — debounce ringan.
        form: { deep: true, handler() { this.simpanDraftDebounce(); } },
        langkah() { this.simpanDraftDebounce(); },
        pakaiBatch() { this.simpanDraftDebounce(); },
        pakaiSyarat() { this.simpanDraftDebounce(); },
    },
    mounted() {
        this.load();
        this.loadPresets();
        this.loadFieldTurunan();
        // Pulihkan draft "Buat Program" bila ada (refresh / pindah halaman lalu kembali).
        this.restoreDraft();
    },
    methods: {
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k || '—'; },
        katBadge(k) { return { MT: 'wca-b--gold', INTERNSHIP: 'wca-b--green', REKRUTMEN: 'wca-b--sky' }[k] || 'wca-b--slate'; },
        modeLabel(m) { return { TERSTRUKTUR: 'Terstruktur', ROLLING: 'Rolling' }[m] || m || '—'; },
        statusLabel(s) { return { DRAFT: 'Draft', BERJALAN: 'Berjalan', SELESAI: 'Selesai' }[s] || s; },
        statusBadge(s) { return { DRAFT: 'wca-b--amber', BERJALAN: 'wca-b--green', SELESAI: 'wca-b--slate' }[s] || 'wca-b--slate'; },
        statusIkon(s) { return { DRAFT: 'bi-pencil-square', BERJALAN: 'bi-play-circle', SELESAI: 'bi-lock-fill' }[s] || 'bi-dot'; },
        totalKuota(p) { return (p.posisi || []).reduce((n, x) => n + (Number(x.kuota) || 0), 0); },
        /** Berapa syarat yang benar-benar MENGGUGURKAN (sisanya hanya menandai). */
        jmlGugur(p) { return (p.syarat || []).filter((s) => s.aksi === 'GUGUR').length; },

        // ── Desain "Program Kegiatan": hitung per kategori, pil warna, inisial pembuat ──
        countKat(k) { return this.list.filter((p) => p.kategori === k).length; },
        katIkon(k) { return { REKRUTMEN: 'bi-briefcase', MT: 'bi-mortarboard', INTERNSHIP: 'bi-backpack' }[k] || 'bi-diagram-3'; },
        katPill(k) { return { MT: 'pkg-pill--gold', INTERNSHIP: 'pkg-pill--green', REKRUTMEN: 'pkg-pill--sky' }[k] || 'pkg-pill--slate'; },
        statusPill(s) { return { DRAFT: 'pkg-pill--amber', BERJALAN: 'pkg-pill--green', SELESAI: 'pkg-pill--slate' }[s] || 'pkg-pill--slate'; },
        initials(name) {
            if (!name) return 'SY';
            const parts = String(name).trim().split(/\s+/);
            return ((parts[0]?.[0] || '') + (parts[1]?.[0] || parts[0]?.[1] || '')).toUpperCase() || 'SY';
        },

        // ── Penyusun syarat ────────────────────────────────────────────
        /**
         * Tahap berformulir milik alur terpilih. Syarat hanya bisa memeriksa
         * field yang memang DITANYAKAN formulir, jadi daftarnya harus mengikuti
         * alur — bukan diketik bebas.
         */
        async loadTahapFormulir() {
            if (!this.form.alur) { this.tahapFormulir = []; return; }
            try {
                const res = await axios.get('/api/v1/karir/options/tahap-formulir', { ...CFG, params: { alur: this.form.alur } });
                this.tahapFormulir = res.data.result || [];
            } catch (e) {
                this.tahapFormulir = [];
            }
        },
        async loadFieldTurunan() {
            try {
                const res = await axios.get('/api/v1/karir/options/field-turunan', CFG);
                this.fieldTurunan = res.data.result || [];
            } catch (e) {
                this.fieldTurunan = [];
            }
        },
        /** Field milik formulir yang dipasang di tahap tsb — dibaca dari skema di kode
         *  untuk formulir lama (FORMULIR_1..4), atau dari schema yang dikirim server
         *  untuk formulir dinamis (tidak punya peta JS statis). LENGKAP dengan tipe &
         *  sumber opsi. Skema formulir = satu-satunya "master syarat": dari sinilah
         *  binding operator + bentuk input Nilai diturunkan otomatis. */
        fieldTahap(tahapId) {
            const t = this.tahapFormulir.find((x) => x.tahapId === tahapId);
            if (!t) return [];
            return semuaField(skemaDariFormulir(t))
                .filter((f) => f.tipe !== 'file' && f.tipe !== 'consent')
                .map((f) => ({ key: f.key, label: f.label, tipe: f.tipe || 'text', opsi: f.opsi || [], sumber_opsi: f.sumber_opsi || null }));
        },

        // ── Binding tipe field → operator + bentuk input Nilai ─────────────
        metaField(S, key) {
            if (!key) return null;
            return this.fieldTahap(S.tahapId).find((f) => f.key === key)
                || this.fieldTurunan.find((f) => f.key === key)
                || null;
        },
        tipeField(S, key) {
            const m = this.metaField(S, key);
            if (!m) return 'text';

            /*
             * DUA KOSAKATA TIPE BERTEMU DI SINI, DAN INI SATU-SATUNYA TEMPAT
             * MENYATUKANNYA.
             *
             * Field formulir memakai kosakata skema borang ('number', 'text',
             * 'date'). Field TURUNAN memakai kosakata domain dari
             * App\Support\Career\FieldTurunan ('ANGKA', 'TEKS', 'TANGGAL') —
             * dan kosakata itu memang dipakai mesin syaratnya di server, jadi
             * bukan tempatnya diganti di sana.
             *
             * Tanpa penyeragaman ini, 'ANGKA' tidak pernah sama dengan 'number'
             * dan Usia diperlakukan sebagai teks: operator yang muncul cuma
             * 'sama dengan' dan 'salah satu dari'. Akibatnya syarat yang paling
             * lazim — USIA MAKSIMAL dan IPK MINIMAL — tidak bisa disusun sama
             * sekali, padahal placeholder di layarnya sendiri menyebut keduanya
             * sebagai contoh. Server sudah lama sanggup mengevaluasi '>=' dan
             * '<=' (lihat MesinSyarat::OPERATOR); yang hilang hanya jalan untuk
             * memilihnya.
             *
             * Dicocokkan setelah huruf kecil supaya penulisan baru — dari mana
             * pun datangnya — tidak diam-diam jatuh ke cabang teks lagi.
             */
            const t = String(m.tipe || 'text').toLowerCase();

            return (t === 'number' || t === 'angka') ? 'number' : t;
        },
        adaOpsi(S, key) {
            const m = this.metaField(S, key);
            return !!(m && (((m.opsi || []).length) || m.sumber_opsi));
        },
        opsiField(S, key) {
            const m = this.metaField(S, key);
            if (!m) return [];
            if (m.sumber_opsi) return this.opsiSumber[m.sumber_opsi] || [];
            return m.opsi || [];
        },
        /** Operator yang masuk akal per tipe — aturan tetap, bukan master DB. */
        operatorUntuk(S, K) {
            if (!K.field) return this.operatorOptions;
            const t = this.tipeField(S, K.field);
            const izin = t === 'number'
                ? ['>=', '<=', '=', '!=', '>', '<', 'ANTARA']
                : ['=', '!=', 'ADA_DI', 'TIDAK_ADA_DI'];
            return this.operatorOptions.filter((o) => izin.includes(o.value));
        },
        /** Field berganti → operator disesuaikan, nilai dikosongkan, opsi sumber dimuat. */
        onFieldGanti(S, K) {
            const boleh = this.operatorUntuk(S, K).map((o) => o.value);
            if (!boleh.includes(K.operator)) K.operator = boleh[0] || '=';
            K.nilai = '';
            const m = this.metaField(S, K.field);
            if (m && m.sumber_opsi) this.loadOpsiSumber(m.sumber_opsi);
        },
        /** Opsi dinamis (mis. kampus dari Master Kampus) — pakai LABEL karena jawaban formulir menyimpan nama. */
        async loadOpsiSumber(sumber) {
            if (this.opsiSumber[sumber]) return;
            try {
                const res = await axios.get(`/api/v1/karir/options/${sumber}`, CFG);
                this.opsiSumber[sumber] = (res.data.result || []).map((o) => o.label ?? o.value);
            } catch (e) {
                this.opsiSumber[sumber] = [];
            }
        },
        /** Title Case ala Monitoring MPP (jabatan/divisi tersimpan UPPERCASE). */
        tc(s) { return titleCase(s || ''); },
        /** Label ramah untuk pratinjau. */
        labelField(S, key) { const m = this.metaField(S, key); return (m && m.label) || key || '—'; },
        opLabel(op) { const o = this.operatorOptions.find((x) => x.value === op); return o ? o.label : op; },

        // Konversi nilai (disimpan sebagai string, ANTARA/daftar dipisah koma).
        angkaVal(K) { const n = parseFloat(K.nilai); return Number.isFinite(n) ? n : undefined; },
        listVal(K) { return String(K.nilai || '').split(',').map((s) => s.trim()).filter(Boolean); },
        antaraVal(K, idx) { const p = String(K.nilai || '').split(','); const n = parseFloat(p[idx]); return Number.isFinite(n) ? n : undefined; },
        setAntara(K, idx, v) {
            const p = String(K.nilai || '').split(',');
            p[idx] = v === null || v === undefined ? '' : String(v);
            K.nilai = [p[0] ?? '', p[1] ?? ''].join(',');
        },
        /**
         * Muat pilihan jadwal untuk kategori + alur yang sedang dipilih.
         *
         * Dipanggil setiap kali salah satunya berganti. Hasilnya menentukan dua
         * hal sekaligus: isi kotaknya, DAN apakah kotaknya digambar sama sekali.
         */
        /** Cari karyawan untuk ditugaskan sebagai PIC program. */
        bukaSerah(p) {
            this.serahSaya = false;
            this.serahProgram = p;
            this.serahLoker = (p.posisi || []).filter((l) => l.id);
            this.serahPilih = [];
            this.mulaiSerah();
        },
        /**
         * Serah terima SELURUH tugas saya — satu borang, sekali isi.
         *
         * Semuanya dicentang sejak awal. Orang yang menekan "Serah Terima
         * Tugas Saya" sudah menyatakan maksudnya; menyodorkan
         * daftar kosong lalu menuntut ia mencentang ulang satu per satu
         * hanyalah menanyakan hal yang sama dua kali. Yang tidak jadi
         * dipindahkan tinggal dilepas centangnya.
         */
        bukaSerahSaya() {
            this.serahSaya = true;
            this.serahProgram = null;
            this.serahLoker = this.lokerSaya;
            this.serahPilih = this.lokerSaya.map((l) => l.id);
            this.mulaiSerah();
        },
        mulaiSerah() {
            this.serahKe = null;
            this.serahAlasan = '';
            this.penerimaOptions = [];
            this.cariPenerima('');
            this.serahShow = true;
        },
        /** Rekan yang bisa MENERIMA pekerjaan — bukan daftar PIC yang boleh saya tugaskan. */
        async cariPenerima(q) {
            const kata = String(q || '').trim();
            this.penerimaLoading = true;
            try {
                // Rute MILIK HALAMAN INI, bukan milik Master Akun. Yang lama
                // dijaga masterAkunPage dan memulangkan 403 untuk rekruter yang
                // tidak memegang modul akun — padahal serah terima justru
                // pekerjaan mereka.
                const res = await axios.get(`${API}/opsi/pemegang`, {
                    ...CFG,
                    params: { q: kata || undefined, untuk: 'penerima' },
                });
                this.penerimaOptions = res.data.result || [];
            } catch (e) {
                this.penerimaOptions = [];
            } finally {
                this.penerimaLoading = false;
            }
        },
        serahPilihSemua(aktif) {
            this.serahPilih = aktif ? this.serahLoker.map((l) => l.id) : [];
        },
        async simpanSerah() {
            if (!this.bolehSerah || this.serahSibuk) return;
            this.serahSibuk = true;
            try {
                const res = await axios.post(`${API}/serah-terima`, {
                    posisiIds: this.serahPilih,
                    keKode: this.serahKe,
                    alasan: this.serahAlasan.trim(),
                }, CFG);
                this.serahShow = false;
                this.notice(res.data?.message || 'Serah terima selesai.');
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyerahkan loker.');
            } finally {
                this.serahSibuk = false;
            }
        },
        /**
         * Calon PIC / penerima — akun internal yang sudah punya kode karyawan.
         *
         * Bukan dari tabel Karyawan: yang memegang loker harus bisa MASUK dan
         * mengerjakannya. Kode karyawan sendiri kini isian bebas di Master Akun,
         * jadi daftar ini juga tidak bergantung pada sumber kepegawaian mana pun.
         */
        bukaMasterMpp() {
            // Tab baru: borang ini biasanya sudah setengah terisi saat admin
            // menyadari MPP-nya belum ada. Berpindah halaman akan membuangnya.
            window.open('/master-mpp', '_blank', 'noopener');
        },
        async cariPic(q) {
            const kata = String(q || '').trim();
            this.picLoading = true;
            try {
                // `page` tidak perlu lagi disebut: rutenya sendiri sudah
                // menetapkan programPage sebagai lingkupnya.
                const res = await axios.get(`${API}/opsi/pemegang`, {
                    ...CFG,
                    params: { q: kata || undefined },
                });
                this.picOptions = res.data.result || [];
            } catch (e) {
                this.picOptions = [];
            } finally {
                this.picLoading = false;
            }
        },
        async muatJadwal() {
            if (!this.form.kategori) {
                this.jadwalOpsi = [];

                return;
            }

            this.jadwalMuat = true;
            try {
                const res = await axios.get('/api/v1/karir/options/jadwal', {
                    headers: { Accept: 'application/json' },
                    params: { kategori: this.form.kategori, alur: this.form.alur || undefined },
                });
                this.jadwalOpsi = res.data.result || [];
            } catch (e) {
                // Gagal memuat DISAMAKAN dengan tidak ada pilihan: keduanya berarti
                // tidak ada jadwal yang bisa ditawarkan sekarang, dan jadwal memang
                // boleh kosong. Menahan borang karenanya akan menghentikan pekerjaan
                // demi bidang yang tidak wajib.
                this.jadwalOpsi = [];
            } finally {
                this.jadwalMuat = false;
            }

            // Jadwal yang terlanjur terpilih tapi tidak ada lagi di daftar baru
            // dibuang — kalau tidak, ia ikut tersimpan sebagai kode yatim.
            if (this.form.jadwal && !this.jadwalOpsi.some((o) => o.value === this.form.jadwal)) {
                this.form.jadwal = '';
            }
        },
        /** Alur berganti -> tahap berformulir berubah, syarat lama tidak berlaku lagi. */
        onAlurGanti() {
            this.form.jadwal = '';
            this.muatJadwal();
            if (this.form.syarat.length) {
                this.form.syarat = [];
                this.notice('Alur diganti — syarat dikosongkan karena tahapnya berubah.');
            }
            this.loadTahapFormulir();
        },
        onTahapSyarat(S, tahapId) {
            const t = this.tahapFormulir.find((x) => x.tahapId === tahapId);
            S.formulir = t ? t.formulir : '';
            // Field milik formulir lama tidak berlaku di formulir baru.
            S.aturan.aturan = [];
        },
        phNilai(op) {
            if (op === 'ANTARA') return 'mis. 20,25';
            if (op === 'ADA_DI' || op === 'TIDAK_ADA_DI') return 'mis. S1,D4';
            return 'nilai';
        },
        async load() {
            this.loading = true;
            try {
                const params = {
                    q: this.filters.q || undefined,
                    kategori: this.tab || undefined,
                    dari: this.filters.rentang?.[0] || undefined,
                    sampai: this.filters.rentang?.[1] || undefined,
                };
                const r = (await axios.get(API, { ...CFG, params })).data.result || {};
                this.list = r.data || [];
                // Lingkup ikut dibaca: menentukan apakah bidang "Penanggung
                // Jawab Program" digambar, dan siapa saja yang boleh dipilih.
                if (r.akses) this.akses = r.akses;
                // Tab kategori datang dari master + hak akses pengguna.
                this.kategoriTab = r.kategori || [];
                this.totalSemua = r.total || 0;

                // ── APA YANG TERPILIH SESUDAH MEMUAT ULANG ──────────────────
                //
                // Yang tadi sedang dibaca didahulukan. Sesudah menyimpan sebuah
                // program daftarnya dimuat ulang, dan tanpa penjagaan ini panel
                // kanan melompat ke program teratas — orang membaca hasil
                // suntingan milik program yang bukan barusan ia ubah.
                // Butir pertama dipilih otomatis HANYA di layar lebar — lihat
                // catatan di @utils/layarLebar.
                this.sel = (this.sel && this.list.some((p) => p.id === this.sel))
                    ? this.sel
                    : (layarLebar() ? (this.grup[0]?.items[0]?.id ?? null) : null);
            } catch (e) {
                this.notice('Gagal memuat data program.');
            } finally {
                this.loading = false;
            }
        },
        /* ══ TAMPILAN PANEL KIRI & KARTU POSISI ═════════════════════════════ */
        nadaStatus(s) { return { BERJALAN: 'jalan', DRAFT: 'draf', SELESAI: 'arsip' }[s] || 'draf'; },
        cahayaStatus(s) {
            return {
                BERJALAN: 'rgba(99,102,241,.14)',
                DRAFT: 'rgba(245,158,11,.14)',
                SELESAI: 'rgba(100,116,139,.12)',
            }[s] || 'rgba(99,102,241,.14)';
        },
        gradLoker(i) { return PRG_GRAD[i % PRG_GRAD.length]; },
        ringLoker(i) { return PRG_RING[i % PRG_RING.length]; },
        /** Ikon ditebak dari nama posisi — penanda visual, bukan data. */
        ikonLoker(l) {
            const t = String(l?.posisi || '').toLowerCase();
            if (/\bit\b|teknologi|program|develop|software|jaringan/.test(t)) return 'bi-pc-display';
            if (/hrd|hrga|hc\b|personalia|rekrut/.test(t)) return 'bi-folder2';
            if (/operator|produksi|mesin|teknisi|maintenance/.test(t)) return 'bi-gear-wide-connected';
            if (/qc|quality|inspek/.test(t)) return 'bi-clipboard-check';
            if (/driver|sopir|logistik|gudang|forklift/.test(t)) return 'bi-truck';
            if (/finance|keuangan|akun|pajak/.test(t)) return 'bi-cash-coin';
            if (/market|sales|penjualan|promosi/.test(t)) return 'bi-megaphone-fill';
            if (/security|satpam/.test(t)) return 'bi-shield-check';
            if (/trainee|magang|intern/.test(t)) return 'bi-mortarboard-fill';

            return 'bi-briefcase-fill';
        },
        statusLokerKelas(s) { return { BUKA: 'is-buka', DRAF: 'is-draf', TUTUP: 'is-tutup' }[String(s || '').toUpperCase()] || 'is-draf'; },
        statusLokerChip(s) { return { BUKA: 'is-jalan', DRAF: 'is-draf', TUTUP: 'is-arsip' }[String(s || '').toUpperCase()] || 'is-draf'; },

        /** Buka panel detail satu posisi + tarik isi MPP-nya. */
        async bukaPosisi(l) {
            if (!l || !l.id || !this.terpilih) return;

            this.posisiId = l.id;
            this.mppPosisi = null;
            this.muatPosisi = true;

            try {
                const res = await axios.get(`${API}/${this.terpilih.id}/posisi/${l.id}`, CFG);
                this.mppPosisi = (res.data.result || {}).mpp || null;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memuat detail posisi.');
            } finally {
                this.muatPosisi = false;
            }
        },
        tutupPosisi() { this.posisiId = null; this.mppPosisi = null; },

        /** Bentuk baku stempel waktu panel admin: '05 Agu 2026 11:45'. */
        fmt(nilai) { return tglJam(nilai); },
        keHalaman(h) {
            this.halaman = Math.min(Math.max(1, h), this.totalHalaman);
            this.$nextTick(() => {
                const el = this.$el?.querySelector('.prg-list');
                if (el) el.scrollTop = 0;
            });
        },
        gotoAlur() { router.visit('/master-alur'); },
        pilihTab(kode) { this.tab = kode; this.load(); },
        cariDebounce() { if (this.cariTimer) clearTimeout(this.cariTimer); this.cariTimer = setTimeout(() => this.load(), 400); },
        resetFilter() {
            this.filters = { q: '', pic: null, rentang: null };
            this.sheetOpen = false;
            this.load();
        },
        pilih(id) { this.sel = id; this.tutupPosisi(); },

        // ══ PHONE SCREENING ═════════════════════════════════════════════════

        /**
         * Muat pengikatan kuesioner untuk program yang sedang dibuka.
         *
         * Diam-diam gagal kalau endpoint-nya menolak, dan itu disengaja: kartu
         * skrining adalah tambahan di halaman yang fungsi utamanya lain.
         * Memunculkan pesan galat merah karena satu kartu tak bisa dimuat akan
         * membuat orang mengira SELURUH halaman program rusak.
         */
        async muatSkrining() {
            this.skrAktivitas = [];
            this.skrTemplate = [];
            this.skrIkatan = [];
            this.skrPosisi = [];
            this.skrBuka = null;

            if (!this.sel) return;

            this.skrMuat = true;
            try {
                const { data } = await axios.get(`${API}/${this.sel}/skrining`, CFG);
                const r = data.result || {};

                this.skrAktivitas = r.aktivitas || [];
                this.skrTemplate = r.template || [];
                this.skrIkatan = r.ikatan || [];
                this.skrPosisi = r.posisi || [];

                // Satu aktivitas skrining: langsung dibuka, tidak ada gunanya
                // menyuruh orang menekan sekali lagi untuk melihat satu-satunya
                // isi yang ada.
                if (this.skrAktivitas.length === 1) this.skrBuka = this.skrAktivitas[0].id;
            } catch {
                this.skrAktivitas = [];
            } finally {
                this.skrMuat = false;
            }
        },

        /** Template yang berlaku untuk satu (aktivitas, loker) — null = ikut bawaan. */
        ikatKode(aktivitasId, posisiId) {
            const x = this.skrIkatan.find(
                (i) => i.aktivitasId === aktivitasId && i.posisiId === (posisiId ?? null),
            );

            return x ? x.kode : null;
        },
        jmlTimpa(aktivitasId) {
            return this.skrIkatan.filter((i) => i.aktivitasId === aktivitasId && i.posisiId !== null).length;
        },
        /**
         * Petunjuk di kotak per-loker.
         *
         * Menyebut NAMA template yang diwarisi, bukan sekadar "ikut bawaan":
         * yang ingin diketahui orang saat memutuskan menimpa adalah apa yang
         * sedang berlaku, dan menyuruhnya menggulir balik ke baris atas untuk
         * mencarinya adalah pekerjaan yang tidak perlu.
         */
        warisPlaceholder(aktivitasId) {
            const kode = this.ikatKode(aktivitasId, null);
            if (!kode) return 'Tidak ada kuesioner';

            const t = this.skrTemplate.find((x) => x.kode === kode);

            return `Ikut bawaan — ${t ? t.nama : kode}`;
        },
        async simpanIkat(aktivitasId, posisiId, kode) {
            this.skrMuat = true;
            try {
                const { data } = await axios.put(
                    `${API}/${this.sel}/skrining`,
                    { aktivitasId, posisiId: posisiId ?? null, kode: kode || null },
                    CFG,
                );
                this.notice(data.message || 'Tersimpan.');
                await this.muatSkrining();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan pengikatan.');
                // Dimuat ulang supaya kotak pilihan kembali menunjukkan keadaan
                // yang BENAR-BENAR tersimpan, bukan pilihan yang barusan ditolak.
                await this.muatSkrining();
            } finally {
                this.skrMuat = false;
            }
        },
        mintaTerapkanSemua(a) {
            const kode = this.ikatKode(a.id, null);
            const t = this.skrTemplate.find((x) => x.kode === kode);
            const timpa = this.jmlTimpa(a.id);

            this.skrTanya = {
                aktivitasId: a.id,
                kode,
                nama: t ? t.nama : kode,
                timpa,
            };
        },
        async terapkanSemua() {
            if (!this.skrTanya) return;

            this.skrBusy = true;
            try {
                const { data } = await axios.post(
                    `${API}/${this.sel}/skrining/semua`,
                    { aktivitasId: this.skrTanya.aktivitasId, kode: this.skrTanya.kode },
                    CFG,
                );
                this.notice(data.message || 'Diterapkan.');
                this.skrTanya = null;
                await this.muatSkrining();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menerapkan.');
            } finally {
                this.skrBusy = false;
            }
        },
        /** Program yang setidaknya satu lokernya jadi tanggung jawab saya. */
        /** Program ini memuat loker yang dipegang `kode`? */
        dipegang(p, kode) {
            return (p.posisi || []).some((l) => l.picKode === kode)
                || p.picKodeKaryawan === kode;
        },
        milikSaya(p) {
            const saya = this.akses.kodeSaya;

            return !!saya && (p.posisi || []).some((l) => l.picKode === saya);
        },
        /* ── Draft "Buat Program" (sessionStorage) ────────────────────────
         * Aturan CLEAR (penting, cegah bug data hilang):
         *   • DIBUANG saat: Batal/tutup modal (tutupModal) & simpan sukses (save).
         *   • DIPERTAHANKAN saat: refresh, pindah halaman, unmount — draft utuh.
         *   • TIDAK aktif untuk mode EDIT (editingId terisi).
         */
        simpanDraftDebounce() {
            if (this.editingId || !this.show) return; // hanya sesi BUAT yang terbuka
            if (this._draftTm) clearTimeout(this._draftTm);
            this._draftTm = setTimeout(() => this.saveDraft(), 300);
        },
        saveDraft() {
            if (this.editingId || !this.show) return;
            try {
                sessionStorage.setItem(DRAFT_KEY, JSON.stringify({
                    form: this.form, langkah: this.langkah,
                    pakaiBatch: this.pakaiBatch, pakaiSyarat: this.pakaiSyarat,
                }));
            } catch (e) { /* storage penuh / privat — abaikan, tak boleh mengganggu UI */ }
        },
        clearDraft() {
            if (this._draftTm) clearTimeout(this._draftTm);
            try { sessionStorage.removeItem(DRAFT_KEY); } catch (e) { /* abaikan */ }
        },
        restoreDraft() {
            let raw = null;
            try { raw = sessionStorage.getItem(DRAFT_KEY); } catch (e) { return; }
            if (!raw) return;
            let d;
            try { d = JSON.parse(raw); } catch (e) { this.clearDraft(); return; }
            if (!d || !d.form || typeof d.form !== 'object') { this.clearDraft(); return; }
            this.editingId = null;
            this.form = d.form;
            this.langkah = Number(d.langkah) || 0;
            this.pakaiBatch = !!d.pakaiBatch;
            this.pakaiSyarat = !!d.pakaiSyarat;
            this.show = true;
            this.loadTahapFormulir();
            if (this.form.kategori) this.loadMppOptions();
            this.notice('Draft program dipulihkan. Lanjutkan, atau Batal untuk membuangnya.');
        },
        /** Tutup/Batal modal = buang draft (sesuai aturan: batal → dibuang). */
        tutupModal() {
            this.show = false;
            this.clearDraft();
        },
        /**
         * Redirect ke Master Info Divisi untuk mengisi info divisi yang kurang.
         * Draft "Buat Program" tetap di sessionStorage → begitu admin kembali ke
         * halaman ini, modal terbuka lagi dengan isian utuh (lihat restoreDraft).
         */
        gotoInfoDivisi(id) {
            this.saveDraft(); // pastikan tersimpan sebelum meninggalkan halaman
            router.visit(id ? `/master-info-divisi?buka=${id}` : '/master-info-divisi');
        },
        openCreate() {
            this.editingId = null;
            // Semua field sengaja KOSONG — mode & warna baru terisi dari preset
            // Master Kategori begitu kategori dipilih. status juga kosong: backend
            // memaksa BERJALAN untuk program baru.
            // picKodeKaryawan TIDAK mulai kosong: program selalu punya penanggung
            // jawab, dan bawaannya orang yang sedang membukanya. Nilai yang sama
            // inilah yang dipakai server (picLokerBaru) bila bidangnya dibiarkan
            // kosong — jadi menuliskannya di sini bukan menambah aturan, melainkan
            // memperlihatkan aturan yang sudah berlaku diam-diam.
            this.form = { nama: '', kategori: '', warna: '', mode: '', alur: '', jadwal: '', penyelenggara: '', picKodeKaryawan: this.akses.kodeSaya || null, status: '', batch: [], posisi: [], syarat: [] };
            this.picOptions = [];
            this.langkah = 0;
            this.pakaiBatch = false;
            this.pakaiSyarat = false;
            this.wzSkrPilih = {};
            this.wzSkrBuka = null;
            this.show = true;

            /*
             * Hak akses cuma satu kategori -> langsung dipasang, berikut presetnya.
             *
             * onKategori() dipanggil, bukan sekadar mengisi form.kategori: mode,
             * warna, dan alur bawaan datang dari preset Master Kategori, dan
             * memasang kodenya saja meninggalkan ketiganya kosong. Admin lalu
             * melihat form yang tampak siap padahal alurnya belum terpilih.
             */
            if (this.kategoriTunggal) {
                this.form.kategori = this.kategoriTunggal.kode;
                this.onKategori();
            }

            this.loadTahapFormulir();
        },

        // ── PHONE SCREENING DI WIZARD ───────────────────────────────────
        /**
         * Aktivitas skrining milik alur yang sedang dipilih.
         *
         * Dipanggil tiap kali alurnya berganti. Hasilnya menentukan langkah
         * "Phone Screening" digambar atau tidak, jadi ia harus selesai sebelum
         * orang sampai di sana — dan karena itu tidak ditunda sampai langkahnya
         * dibuka.
         */
        async muatSkriningAlur() {
            const alur = this.form?.alur || '';
            if (!alur) {
                this.wzSkrAktivitas = [];
                this.wzSkrPilih = {};

                return;
            }

            this.wzSkrMuat = true;
            try {
                const { data } = await axios.get(`${API}/skrining-alur`, {
                    ...CFG,
                    params: { alur, kategori: this.form?.kategori || undefined },
                });
                const r = data.result || {};

                // Alur bisa berganti lagi selagi permintaan ini berjalan.
                if ((this.form?.alur || '') !== alur) return;

                this.wzSkrAktivitas = r.aktivitas || [];
                this.wzSkrTemplate = r.template || [];

                // Pilihan yang sudah dibuat dipertahankan selama aktivitasnya
                // masih ada — mengganti alur lalu kembali ke alur semula tidak
                // menghapus kuesioner yang sudah dipilih.
                const baru = {};
                this.wzSkrAktivitas.forEach((a) => {
                    baru[a.id] = this.wzSkrPilih[a.id] || { bawaan: '', perLoker: {} };
                });
                this.wzSkrPilih = baru;
            } catch (e) {
                this.wzSkrAktivitas = [];
            } finally {
                this.wzSkrMuat = false;
            }
        },
        /**
         * Bawaan berganti → penimpaan yang isinya SAMA dengan bawaan dibuang.
         *
         * Tanpa ini, memilih "Skrining Awal — Umum" sebagai bawaan setelah
         * sepuluh loker ditimpa satu per satu meninggalkan sepuluh baris
         * penimpaan yang tidak menimpa apa pun — dan lencana "10 berbeda"
         * menyatakan sesuatu yang tidak benar.
         */
        wzSkrSamakan(aktivitasId) {
            const p = this.wzSkrPilih[aktivitasId];
            if (!p) return;

            Object.keys(p.perLoker).forEach((ref) => {
                if (!p.perLoker[ref] || p.perLoker[ref] === p.bawaan) delete p.perLoker[ref];
            });
        },
        wzSkrJmlTimpa(aktivitasId) {
            const p = this.wzSkrPilih[aktivitasId];
            if (!p) return 0;

            return Object.values(p.perLoker).filter((k) => k && k !== p.bawaan).length;
        },
        wzSkrNama(kode) {
            return this.wzSkrTemplate.find((t) => t.kode === kode)?.nama || kode;
        },
        /**
         * Pengikatan yang SUDAH terpasang, dibaca saat program disunting.
         *
         * Tanpa ini, membuka "Ubah Program" lalu menekan Perbarui akan menghapus
         * seluruh pengikatan — karena borangnya mengirim daftar kosong, dan
         * penyimpanan menulis ulang seluruh anaknya. Itu kehilangan data yang
         * tidak pernah diminta siapa pun.
         *
         * Dipetakan balik dari Id loker ke MPP_REF: borang bekerja dengan
         * MPP_REF, sementara yang tersimpan menunjuk Id.
         */
        async muatSkriningTerpasang(programId) {
            try {
                const { data } = await axios.get(`${API}/${programId}/skrining`, CFG);
                const r = data.result || {};
                if (!r.siap || !(r.aktivitas || []).length) return;

                const refPer = {};
                (r.posisi || []).forEach((x) => { refPer[x.id] = x.mppRef; });

                const peta = {};
                (r.aktivitas || []).forEach((a) => { peta[a.id] = { bawaan: '', perLoker: {} }; });
                (r.ikatan || []).forEach((ik) => {
                    const p = peta[ik.aktivitasId];
                    if (!p) return;
                    if (ik.posisiId === null) p.bawaan = ik.kode;
                    else if (refPer[ik.posisiId]) p.perLoker[refPer[ik.posisiId]] = ik.kode;
                });

                this.wzSkrPilih = peta;
            } catch (e) { /* pengikatan gagal dimuat — borang tetap bisa dipakai */ }
        },

        /** Bentuk yang diminta server: satu baris per aktivitas. */
        wzSkrMuatan() {
            return this.wzSkrAktivitas.map((a) => {
                const p = this.wzSkrPilih[a.id] || { bawaan: '', perLoker: {} };
                const perLoker = {};
                Object.entries(p.perLoker).forEach(([ref, kode]) => {
                    if (kode && kode !== p.bawaan) perLoker[ref] = kode;
                });

                return { aktivitasId: a.id, bawaan: p.bawaan || null, perLoker };
            });
        },

        /** Lanjut ke langkah berikutnya, validasi minimum langkah aktif dulu. */
        async maju() {
            if (this.langkahKini === 'identitas') {
                if (!this.form.kategori) return this.notice('Kategori wajib dipilih.');
                if (!this.form.nama.trim()) return this.notice('Nama program wajib diisi.');
            }
            if (this.langkahKini === 'posisi') {
                if (this.form.posisi.some((l) => !l.mppRef)) return this.notice('Ada posisi yang belum dipilih dari MPP.');
                // WAJIB: Info Divisi tiap posisi harus sudah terisi (dipakai landing page).
                // Draft tersimpan di sessionStorage — admin bisa buka Master Info Divisi,
                // mengisinya, lalu kembali tanpa kehilangan program yang sedang dibuat.
                const refs = this.form.posisi.map((l) => l.mppRef).filter(Boolean);
                if (refs.length) {
                    try {
                        const res = await axios.post(`${API}/cek-info-divisi`, { mppRefs: refs }, CFG);
                        const belum = res.data?.result?.belumLengkap || [];
                        if (belum.length) {
                            // Tampilkan daftar divisi bermasalah + tombol redirect ke pengisian.
                            this.divisiBelum = belum;
                            this.guardDivisiShow = true;
                            return;
                        }
                    } catch (e) { /* fail-open: jika cek gagal, jangan blokir admin */ }
                }
            }
            this.langkah = Math.min(this.langkah + 1, this.langkahMeta.length - 1);
        },

        /** Matikan batch -> buang datanya; nyalakan saat kosong -> beri satu baris. */
        togglePakaiBatch(v) {
            if (v && !this.form.batch.length) this.addBatch();
            if (!v) this.form.batch = [];
        },
        togglePakaiSyarat(v) {
            if (v && !this.form.syarat.length) this.addSyarat();
            if (!v) this.form.syarat = [];
        },

        /**
         * Kategori berubah -> terapkan preset Master Kategori (mode, alur, warna) dan
         * buang pilihan turunan yang jadi tidak berlaku. Alur & jadwal milik kategori
         * lain tidak boleh menempel di program ini.
         */
        onKategori(opt) {
            this.form.alur = '';
            this.form.jadwal = '';
            this.muatJadwal();
            // Posisi ikut dibuang: MPP milik kategori lama tidak berlaku di kategori baru.
            if (this.form.posisi.length) {
                this.notice('Kategori diganti — daftar posisi dikosongkan, pilih ulang dari MPP.');
            }
            this.form.posisi = [];

            // MPP DIMUAT ULANG DULUAN, sebelum cabang preset.
            //
            // Daftarnya sekarang DISARING kategori (MT mengambil Flag_MT='Y',
            // selainnya mengambil sisanya), jadi ini bukan lagi penyegaran
            // opsional — ia yang menentukan kartu mana yang boleh dipilih.
            //
            // Dulu pemanggilannya ada SESUDAH `if (!preset) return`, dan
            // kategori yang belum punya baris di Master Kategori membuatnya
            // terlewat: posisinya dikosongkan tapi kartu MPP yang terpampang
            // masih milik kategori sebelumnya — daftar yang tampak sah dan
            // seluruhnya salah. Ia hanya bergantung pada form.kategori, yang
            // sudah terisi di sini, jadi aman dipindah ke atas.
            this.loadMppOptions();

            const preset = this.presets.find((p) => p.value === (opt?.value ?? this.form.kategori));
            if (!preset) return;
            if (preset.mode) this.form.mode = preset.mode;
            if (preset.warna) this.form.warna = preset.warna;
            if (preset.alur) this.form.alur = preset.alur;
            // TETAP DI SINI, bukan ikut naik: ia membaca form.alur, yang baru
            // saja dikosongkan di awal method dan hanya dipulihkan oleh preset
            // di baris atas. Dipanggil lebih awal, ia selalu pulang dengan
            // daftar kosong.
            this.loadTahapFormulir();
        },

        async loadPresets() {
            try {
                const res = await axios.get('/api/v1/karir/options/kategori-preset', CFG);
                this.presets = res.data.result || [];
            } catch (e) {
                this.presets = [];
            }
        },
        openEdit(p) {
            this.editingId = p.id;
            this.form = {
                nama: p.nama || '',
                kategori: p.kategori || '',
                warna: p.warna || '#4f46e5',
                mode: p.mode || 'ROLLING',
                alur: p.alur || '',
                jadwal: p.jadwal || '',
                penyelenggara: p.penyelenggara || '',
                // SENGAJA TIDAK jatuh ke diri sendiri saat kosong.
                //
                // Pemegang LINTAS_PIC bisa menyunting program milik orang lain.
                // Mengisi bidang ini dengan kode penyuntingnya berarti membuka
                // program orang lain lalu menyimpannya kembali akan MEMINDAHKAN
                // kepemilikannya — diam-diam, tanpa satu pun yang membacanya.
                picKodeKaryawan: p.picKodeKaryawan || null,
                status: p.status || 'DRAFT',
                batch: (p.batch || []).map((b) => ({ nama: b.nama, kuota: b.kuota, status: b.status })),
                // kuotaMpp diisi dari kuota tersimpan supaya batas atas input tetap
                // masuk akal sebelum admin memilih ulang MPP-nya.
                posisi: (p.posisi || []).map((l) => ({ mppRef: l.mppRef || '', posisi: l.posisi, departemen: l.departemen, lokasi: l.lokasi, level: l.level || '', kuota: l.kuota, kuotaMpp: l.kuota, status: l.status || 'BUKA' })),
                syarat: (p.syarat || []).map((s) => ({ nama: s.nama, tahapId: s.tahapId, formulir: s.formulir, aturan: s.aturan && s.aturan.aturan ? s.aturan : { penghubung: 'DAN', aturan: [] }, aksi: s.aksi || 'TANDAI', pesanGugur: s.pesanGugur || '', uji: false, aktif: s.aktif !== false })),
            };
            this.langkah = 0;
            // Seksi opsional otomatis menyala kalau datanya sudah ada.
            this.pakaiBatch = this.form.batch.length > 0;
            this.pakaiSyarat = this.form.syarat.length > 0;
            this.wzSkrPilih = {};
            this.wzSkrBuka = null;
            this.show = true;
            this.loadTahapFormulir();
            this.loadMppOptions();
            this.muatJadwal();
            this.muatSkriningTerpasang(p.id);
        },
        addBatch() { this.form.batch.push({ nama: '', kuota: 0, status: 'AKTIF' }); },

        /**
         * Batas kuota satu baris batch = pagu MPP dikurangi kursi batch LAIN.
         * Dihitung per baris supaya admin tahu sisa yang masih boleh dipakai,
         * bukan sekadar ditolak saat menyimpan.
         */
        batasBatch(i) {
            const lain = this.form.batch.reduce((n, b, j) => (j === i ? n : n + (Number(b.kuota) || 0)), 0);
            return Math.max(0, this.kuotaMpp - lain);
        },
        /** Muat opsi MPP untuk kategori aktif — dipakai multi-pilih langkah Posisi. */
        async loadMppOptions() {
            if (!this.form.kategori) { this.mppOptions = []; return; }
            try {
                const res = await axios.get('/api/v1/karir/options/mpp', {
                    ...CFG,
                    // MPP disaring ke PIC yang akan memiliki lokernya. Tanpa ini
                    // admin berlingkup SEMUA melihat seluruh MPP lalu memilih
                    // milik orang lain — dan lokernya tetap jatuh ke PIC yang
                    // ditugaskan, yang tak pernah meminta pekerjaan itu.
                    params: { kategori: this.form.kategori, pic: this.picEfektif || undefined },
                });
                this.mppOptions = res.data.result || [];
            } catch (e) {
                this.mppOptions = [];
            }
        },
        /** Klik kartu MPP → baris posisi dibuat; klik lagi → dibuang. */
        toggleMpp(o) {
            const i = this.form.posisi.findIndex((l) => l.mppRef === o.value);
            if (i >= 0) { this.form.posisi.splice(i, 1); return; }
            this.form.posisi.push({
                mppRef: o.value,
                posisi: o.posisi || '',
                departemen: o.departemen || '',
                lokasi: o.lokasi || '',
                level: o.level || '',
                kuotaMpp: o.kuota || 0,
                kuota: o.kuota || 0,
                status: 'BUKA',
            });
        },

        /** Opsi checklist Nilai tersaring kotak carinya sendiri (K._cari). */
        opsiTersaring(S, K) {
            const semua = this.opsiField(S, K.field);
            const q = String(K._cari || '').trim().toLowerCase();
            if (!q) return semua;
            return semua.filter((o) => String(o).toLowerCase().includes(q));
        },
        /** Centang/lepas satu nilai pada operator daftar (disimpan koma-terpisah). */
        toggleNilai(K, o) {
            const list = this.listVal(K);
            const i = list.indexOf(o);
            if (i >= 0) list.splice(i, 1); else list.push(o);
            K.nilai = list.join(',');
        },
        addSyarat() { this.form.syarat.push({ nama: '', tahapId: null, formulir: '', aturan: { penghubung: 'DAN', aturan: [] }, aksi: 'TANDAI', pesanGugur: '', uji: false, aktif: true }); },
        addKondisi(S) { S.aturan.aturan.push({ field: '', operator: '>=', nilai: '' }); },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama program wajib diisi.');
            if (!this.form.kategori) return this.notice('Kategori wajib dipilih.');
            // Batch, posisi & kriteria boleh KOSONG. Yang divalidasi hanya baris yang
            // benar-benar ditambahkan admin.
            if (this.form.batch.some((b) => !b.nama || !String(b.nama).trim())) return this.notice('Setiap batch wajib punya nama.');
            if (this.form.posisi.some((l) => !l.mppRef)) return this.notice('Setiap posisi wajib dipilih dari daftar MPP.');
            if (this.kuotaBatchLebih) return this.notice(`Total kursi batch (${this.kuotaBatch}) melebihi kuota MPP (${this.kuotaMpp}).`);
            if (this.form.syarat.some((s) => !s.nama || !String(s.nama).trim())) return this.notice('Setiap syarat wajib punya nama.');
            if (this.form.syarat.some((s) => !s.tahapId)) return this.notice('Setiap syarat wajib menyebut diperiksa di tahap mana.');
            if (this.form.syarat.some((s) => s.aturan.aturan.some((k) => !k.field || !k.nilai))) return this.notice('Setiap kondisi wajib punya field & nilai.');
            this.saving = true;
            const payload = { ...this.form };
            // kuotaMpp hanya batas atas untuk input, bukan data program.
            payload.posisi = payload.posisi.map(({ kuotaMpp, ...sisa }) => sisa);
            // Pengikatan kuesioner ikut dalam SATU penyimpanan, bukan panggilan
            // kedua sesudahnya: panggilan kedua yang gagal meninggalkan program
            // yang sudah jadi tapi tanpa kuesioner, dan tidak ada satu pun
            // pesan yang memberi tahu bagian mana yang tidak jadi tersimpan.
            if (this.wzSkrAktivitas.length) payload.skrining = this.wzSkrMuatan();
            // Saat membuat, status ditentukan backend (selalu BERJALAN).
            if (!this.editingId) delete payload.status;
            try {
                if (this.editingId) {
                    // ── ALUR PROGRAM BERGANTI = ADA ROMBONGAN YANG TERDAMPAK ──
                    //
                    // Ditanyakan DULU ke server: berapa pelamar berjalan yang
                    // masih punya tempat di alur baru, dan siapa yang tidak
                    // beserta sebabnya. Server menjawab `berdampak: false` bila
                    // alurnya tidak berubah atau tidak ada seorang pun yang
                    // terdampak — dan penyimpanan berjalan seperti biasa.
                    const dampak = await this.hitungDampakAlur(payload.alur);

                    if (dampak?.berdampak) {
                        this.dampak = dampak;
                        this.dampakPayload = payload;
                        this.dampakShow = true;
                        this.saving = false;

                        return; // dilanjutkan simpanDenganPilihan()
                    }

                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Program diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Program ditambahkan.');
                }
                this.clearDraft(); // sukses simpan → draft tak perlu lagi
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        /**
         * Berapa pelamar berjalan yang terdampak bila alur program diganti.
         *
         * Gagal menghitung TIDAK menghentikan penyimpanan: yang hilang cuma
         * pratinjaunya, dan bawaan server memang "jangan pindahkan siapa pun".
         * Menahan pekerjaan admin karena satu panel keterangan tak bisa dimuat
         * jauh lebih merugikan daripada menyimpan tanpa pratinjau.
         */
        async hitungDampakAlur(alur) {
            if (!alur) return null;
            try {
                const res = await axios.get(`${API}/${this.editingId}/dampak-alur`, { ...CFG, params: { alur } });

                return res.data.result || null;
            } catch (e) {
                return null;
            }
        },
        /** Simpan sesudah admin memilih nasib rombongan yang sedang berjalan. */
        async simpanDenganPilihan(migrasi) {
            if (this.saving || !this.dampakPayload) return;
            this.saving = true;
            try {
                const res = await axios.put(
                    `${API}/${this.editingId}`,
                    { ...this.dampakPayload, migrasi },
                    CFG,
                );
                this.dampakShow = false;
                this.dampakPayload = null;
                this.notice(res.data.message || 'Program diperbarui.');
                this.clearDraft();
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(p, v) {
            const prev = p.status;
            p.status = v ? 'BERJALAN' : 'DRAFT';
            try {
                await axios.patch(`${API}/${p.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Program "${p.nama}" ${v ? 'dijalankan' : 'dijadikan draft'}.`);
            } catch (e) {
                p.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(p) { this.delTarget = p; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const p = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${p.id}`, CFG);
                this.notice('Program dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },
        notice(x) { this.toast = x; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>
<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   PROGRAM KEGIATAN — kerangka jelajah yang SAMA dengan Pembukaan Program.
   Disalin dengan sengaja (awalan prg-): dua halaman ini bersebelahan di
   menu, dan <style scoped> tidak bisa dipakai bersama. Kalau salah satu
   digeser, geser juga yang lain — bedanya akan langsung terlihat.
   ═══════════════════════════════════════════════════════════════════════════ */

@keyframes prgRise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
@keyframes prgFade { from { opacity: 0; } to { opacity: 1; } }
@keyframes prgGrow { from { width: 0; } }
@keyframes prgPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,.5); } 70% { box-shadow: 0 0 0 8px rgba(99,102,241,0); } }
@keyframes prgSheen { 0% { transform: translateX(-170%) skewX(-18deg); } 30%, 100% { transform: translateX(340%) skewX(-18deg); } }

.pbk {
    min-height: 100%;
    padding: clamp(14px, 2vw, 22px) clamp(12px, 2vw, 24px) clamp(28px, 4vw, 42px);
    background:
        radial-gradient(1200px 600px at 12% -8%, rgba(139,92,246,.1), transparent 60%),
        radial-gradient(1000px 500px at 105% 4%, rgba(99,102,241,.1), transparent 62%),
        linear-gradient(180deg, #f8fafc, #eef2f8 60%, #f8fafc);
}

/* ── KEPALA ──────────────────────────────────────────────────────────────── */
.prg-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; flex-wrap: wrap; animation: prgRise .6s cubic-bezier(.22,1,.36,1) both; }
.prg-top__l { min-width: 0; }
.prg-crumb { display: flex; align-items: center; gap: 8px; font-size: 11.5px; font-weight: 700; color: #94a3b8; }
.prg-crumb i { font-size: 8px; }
.prg-crumb .on { color: #4f46e5; }
.prg-title { margin: 8px 0 0; display: flex; align-items: center; gap: 12px; font-size: clamp(1.5rem, 3vw, 2.05rem); font-weight: 800; letter-spacing: -.03em; color: #0f172a; }
.prg-title__ico { width: 40px; height: 40px; flex-shrink: 0; border-radius: 13px; background: linear-gradient(135deg,#8b5cf6,#4f46e5); display: grid; place-items: center; color: #fff; font-size: 18px; box-shadow: 0 12px 26px rgba(99,102,241,.3); }
.prg-lead { margin: 9px 0 0; max-width: 560px; font-size: 13.5px; line-height: 1.6; color: #64748b; text-wrap: pretty; }
.prg-cta { position: relative; overflow: hidden; display: inline-flex; align-items: center; gap: 9px; min-height: 44px; padding: 12px 20px; border: none; border-radius: 13px; cursor: pointer; font: inherit; font-size: 13.5px; font-weight: 700; color: #fff; background: linear-gradient(135deg,#8b5cf6,#6366f1 55%,#4f46e5); box-shadow: 0 14px 30px rgba(99,102,241,.32); transition: all .26s cubic-bezier(.22,1,.36,1); }
.prg-cta::before { content: ''; position: absolute; top: 0; left: 0; height: 100%; width: 55%; background: linear-gradient(90deg, transparent, rgba(255,255,255,.45), transparent); transform: translateX(-170%) skewX(-18deg); animation: prgSheen 5.5s ease-in-out 1.6s infinite; }
.prg-cta:hover { transform: translateY(-2px); box-shadow: 0 20px 40px rgba(99,102,241,.42); }

/* ── KERANGKA DUA PANEL ──────────────────────────────────────────────────── */
.prg-body { display: flex; flex-wrap: wrap; gap: clamp(12px, 1.6vw, 18px); margin-top: clamp(16px, 2.4vw, 24px); align-items: flex-start; }

.prg-aside { flex: 1 1 300px; min-width: 270px; max-width: 392px; position: sticky; top: 14px; display: flex; flex-direction: column; min-height: 0; border-radius: 22px; background: rgba(255,255,255,.86); backdrop-filter: blur(18px); border: 1px solid rgba(255,255,255,.95); box-shadow: 0 20px 52px rgba(15,23,42,.09); overflow: hidden; animation: prgRise .65s cubic-bezier(.22,1,.36,1) .06s both; }
.prg-aside__head { padding: 15px 16px 13px; border-bottom: 1px solid rgba(226,232,240,.85); }
.prg-fold { display: flex; align-items: center; gap: 9px; }
.prg-fold__ico { width: 30px; height: 30px; flex-shrink: 0; border-radius: 9px; background: linear-gradient(135deg,#fbbf24,#d97706); display: grid; place-items: center; color: #fff; font-size: 13px; }
.prg-fold__id { min-width: 0; flex: 1; line-height: 1.25; }
.prg-fold__t { font-size: 13.5px; font-weight: 800; letter-spacing: -.01em; color: #0f172a; }
.prg-fold__s { font-size: 10.5px; color: #94a3b8; }
.prg-sort { flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; padding: 8px 10px; border: 1px solid rgba(226,232,240,.95); border-radius: 10px; background: #fff; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800; color: #475569; transition: all .22s ease; }
.prg-sort:hover { color: #4f46e5; border-color: rgba(99,102,241,.4); }

.prg-search { position: relative; margin-top: 12px; }
.prg-search > i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #8b5cf6; font-size: 12.5px; pointer-events: none; }
.prg-search input { width: 100%; min-height: 42px; padding: 11px 13px 11px 35px; border-radius: 12px; border: 1px solid rgba(226,232,240,.95); background: rgba(248,250,252,.9); font: inherit; font-size: 13px; color: #0f172a; outline: none; transition: all .25s ease; }
.prg-search input:focus { border-color: #6366f1; background: #fff; box-shadow: 0 0 0 3.5px rgba(99,102,241,.13); }

.prg-scopes { display: flex; gap: 5px; margin-top: 10px; padding: 4px; border-radius: 11px; background: rgba(241,245,249,.9); }
.prg-scope { flex: 1; appearance: none; border: none; background: transparent; cursor: pointer; padding: 7px 6px; border-radius: 8px; font: inherit; font-size: 11px; font-weight: 800; color: #64748b; transition: all .22s ease; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.prg-scope span { opacity: .6; }
.prg-scope:hover { color: #4f46e5; }
.prg-scope.on { background: #fff; color: #4f46e5; box-shadow: 0 2px 8px rgba(15,23,42,.08); }

.prg-list { flex: 1; overflow-y: auto; max-height: min(56vh, 520px); padding: 8px; }
.prg-group { margin-bottom: 6px; }
.prg-group__head { display: flex; align-items: center; gap: 8px; padding: 9px 9px 7px; }
.prg-group__head > i { font-size: 8px; color: #cbd5e1; }
.prg-group__lbl { font-size: 9.5px; font-weight: 800; letter-spacing: .16em; color: #94a3b8; }
.prg-group__n { padding: 1.5px 7px; border-radius: 999px; background: rgba(241,245,249,.95); font-size: 9.5px; font-weight: 800; color: #64748b; }
.prg-group__line { flex: 1; height: 1px; background: rgba(226,232,240,.85); }

.prg-item { position: relative; width: 100%; display: flex; align-items: center; gap: 11px; padding: 10px 11px; border: 1px solid transparent; border-radius: 13px; background: transparent; cursor: pointer; font: inherit; text-align: left; transition: background .22s ease, border-color .22s ease; }
.prg-item:hover { background: rgba(248,250,252,.95); }
.prg-item.on { background: rgba(99,102,241,.07); border-color: rgba(99,102,241,.22); }
.prg-item__accent { position: absolute; left: 0; top: 9px; bottom: 9px; width: 3px; border-radius: 0 3px 3px 0; background: linear-gradient(180deg,#8b5cf6,#4f46e5); opacity: 0; transition: opacity .25s ease; }
.prg-item.on .prg-item__accent { opacity: 1; }
.prg-item__ico { flex-shrink: 0; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 14px; }
.prg-item__ico.is-buka { background: rgba(16,185,129,.11); color: #059669; }
.prg-item__ico.is-nanti, .prg-item__ico.is-diam { background: rgba(245,158,11,.12); color: #d97706; }
.prg-item__ico.is-tutup { background: rgba(100,116,139,.11); color: #64748b; }
.prg-item__body { flex: 1; min-width: 0; }
.prg-item__r1 { display: flex; align-items: center; gap: 7px; }
/* Nama program kerap panjang. Dipotong dengan elipsis; utuhnya ada di title. */
.prg-item__name { flex: 1; min-width: 0; font-size: 13px; font-weight: 800; letter-spacing: -.01em; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-item.on .prg-item__name { color: #4338ca; }
.prg-item__dot { flex-shrink: 0; width: 7px; height: 7px; border-radius: 50%; }
.prg-item__r2 { display: flex; align-items: center; gap: 7px; margin-top: 3px; font-size: 10.5px; color: #94a3b8; min-width: 0; }
.prg-item__code { flex-shrink: 0; font-family: ui-monospace, SFMono-Regular, monospace; font-weight: 700; color: #64748b; }
.prg-item__r3 { display: flex; align-items: center; gap: 6px; margin-top: 6px; min-width: 0; }
.prg-item__pill { flex-shrink: 0; padding: 2.5px 7px; border-radius: 6px; font-size: 9.5px; font-weight: 800; }
.prg-item__pill.is-buka { background: rgba(16,185,129,.12); color: #047857; }
.prg-item__pill.is-nanti, .prg-item__pill.is-diam { background: rgba(245,158,11,.14); color: #b45309; }
.prg-item__pill.is-tutup { background: rgba(100,116,139,.12); color: #475569; }
.prg-item__num { flex-shrink: 0; text-align: right; }
.prg-item__val { display: block; font-size: 14px; font-weight: 800; letter-spacing: -.02em; color: #0f172a; }
.prg-item.on .prg-item__val { color: #4338ca; }
.prg-item__cap { display: block; font-size: 9px; font-weight: 700; letter-spacing: .1em; color: #a8b1c1; }

.prg-none { padding: 38px 18px; text-align: center; animation: prgFade .3s ease both; }
.prg-none__ico { display: inline-grid; place-items: center; width: 52px; height: 52px; border-radius: 50%; background: rgba(99,102,241,.09); color: #8b5cf6; font-size: 21px; }
.prg-none__t { margin-top: 13px; font-size: 13.5px; font-weight: 800; color: #0f172a; }
.prg-none__s { margin-top: 5px; font-size: 12px; color: #94a3b8; }

.prg-aside__foot { padding: 13px 16px 15px; border-top: 1px solid rgba(226,232,240,.85); background: rgba(248,250,252,.7); }

/* ── PAGINASI PANEL KIRI ─────────────────────────────────────────────────── */
.prg-pager { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.prg-pager__nav { display: grid; place-items: center; width: 28px; height: 28px; flex-shrink: 0; border: 1px solid rgba(226,232,240,.95); border-radius: 9px; background: #fff; cursor: pointer; color: #475569; font-size: 12px; transition: all .2s ease; }
.prg-pager__nav:hover:not(:disabled) { color: #4f46e5; border-color: rgba(99,102,241,.45); }
.prg-pager__nav:disabled { opacity: .38; cursor: not-allowed; }
.prg-pager__nums { display: flex; align-items: center; gap: 4px; flex: 1; min-width: 0; overflow: hidden; }
.prg-pager__n { min-width: 26px; height: 28px; padding: 0 6px; border: none; border-radius: 8px; background: transparent; cursor: pointer; font: inherit; font-size: 11.5px; font-weight: 800; color: #64748b; transition: all .2s ease; }
.prg-pager__n:hover:not(:disabled):not(.on) { background: rgba(99,102,241,.08); color: #4f46e5; }
.prg-pager__n.on { background: linear-gradient(135deg,#6366f1,#4338ca); color: #fff; box-shadow: 0 6px 14px rgba(67,56,202,.28); }
.prg-pager__n.is-gap { cursor: default; color: #cbd5e1; min-width: 14px; padding: 0; }
.prg-pager__info { width: 100%; text-align: center; font-size: 10.5px; font-weight: 700; color: #94a3b8; }

/* ── PANEL KANAN ─────────────────────────────────────────────────────────── */
.prg-main { flex: 999 1 520px; min-width: 280px; display: flex; flex-direction: column; gap: clamp(12px, 1.6vw, 18px); }

.prg-hero { position: relative; overflow: hidden; border-radius: 22px; padding: clamp(16px, 2.4vw, 24px); background: rgba(255,255,255,.88); backdrop-filter: blur(18px); border: 1px solid rgba(255,255,255,.95); box-shadow: 0 20px 52px rgba(15,23,42,.09); animation: prgRise .65s cubic-bezier(.22,1,.36,1) .1s both; }
.prg-hero__glow { position: absolute; top: -60px; right: -40px; width: 220px; height: 220px; border-radius: 50%; pointer-events: none; }
.prg-hero__row { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.prg-hero__l { min-width: 0; flex: 1; }
.prg-hero__crumb { display: flex; align-items: center; gap: 9px; font-size: 11px; font-weight: 700; color: #94a3b8; flex-wrap: wrap; }
.prg-hero__crumb > i.bi-folder2-open { color: #f59e0b; }
.prg-hero__crumb .on { color: #4f46e5; font-weight: 800; }
.prg-back { display: none; width: 30px; height: 30px; border: 1px solid rgba(226,232,240,.95); border-radius: 9px; background: #fff; color: #475569; cursor: pointer; }
.prg-hero__title { margin: 9px 0 0; font-size: clamp(1.3rem, 2.6vw, 1.8rem); font-weight: 800; letter-spacing: -.03em; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; min-width: 0; }
.prg-chip { display: inline-flex; align-items: center; gap: 7px; max-width: 100%; padding: 6px 12px; border-radius: 999px; font-size: 11px; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-chip__dot { flex-shrink: 0; width: 7px; height: 7px; border-radius: 50%; background: currentColor; animation: prgPulse 2.2s ease-out infinite; }
.prg-chip.is-buka { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.26); color: #047857; }
.prg-chip.is-nanti, .prg-chip.is-diam { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.3); color: #b45309; }
.prg-chip.is-tutup { background: rgba(100,116,139,.1); border: 1px solid rgba(100,116,139,.26); color: #334155; }
.prg-chip.is-plain { background: rgba(241,245,249,.95); color: #475569; font-weight: 700; }

.prg-hero__act { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; flex-shrink: 0; }
.prg-pub { display: inline-flex; align-items: center; gap: 10px; min-height: 42px; padding: 9px 14px; border: 1px solid rgba(100,116,139,.24); border-radius: 13px; background: rgba(241,245,249,.8); cursor: pointer; font: inherit; font-size: 12.5px; font-weight: 800; color: #475569; transition: all .26s cubic-bezier(.22,1,.36,1); }
.prg-pub.on { border-color: rgba(16,185,129,.3); background: rgba(16,185,129,.08); color: #047857; }
.prg-pub__sw { position: relative; width: 38px; height: 22px; border-radius: 999px; background: rgba(148,163,184,.45); transition: background .3s ease; }
.prg-pub.on .prg-pub__sw { background: linear-gradient(135deg,#34d399,#059669); }
.prg-pub__knob { position: absolute; top: 2.5px; left: 2.5px; width: 17px; height: 17px; border-radius: 50%; background: #fff; box-shadow: 0 2px 5px rgba(15,23,42,.25); transition: transform .3s cubic-bezier(.34,1.4,.64,1); }
.prg-pub.on .prg-pub__knob { transform: translateX(16px); }
.prg-ib { width: 42px; height: 42px; border: 1px solid rgba(226,232,240,.95); border-radius: 12px; background: #fff; cursor: pointer; color: #475569; font-size: 14px; transition: all .22s ease; }
.prg-ib:hover { color: #4f46e5; border-color: rgba(99,102,241,.4); }
.prg-ib.is-danger { border-color: rgba(220,38,38,.24); background: rgba(220,38,38,.05); color: #b91c1c; }
.prg-ib.is-danger:hover { background: rgba(220,38,38,.12); color: #b91c1c; border-color: rgba(220,38,38,.35); }

/* ── TAB ─────────────────────────────────────────────────────────────────── */

.prg-sec { display: flex; flex-direction: column; gap: clamp(12px, 1.6vw, 18px); animation: prgFade .35s ease both; }

/* ── KARTU GRAFIK ────────────────────────────────────────────────────────── */
.prg-card { flex: 1 1 100%; min-width: 260px; border-radius: 22px; padding: clamp(16px, 2.2vw, 22px); background: rgba(255,255,255,.88); backdrop-filter: blur(18px); border: 1px solid rgba(255,255,255,.95); box-shadow: 0 18px 46px rgba(15,23,42,.08); }
.prg-card--flush { padding: 0; overflow: hidden; }
.prg-card__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
.prg-card__t { font-size: 14.5px; font-weight: 800; letter-spacing: -.02em; color: #0f172a; }
.prg-card__s { margin-top: 3px; font-size: 12px; color: #94a3b8; }

.prg-ranges { display: flex; gap: 4px; padding: 4px; border-radius: 11px; background: rgba(241,245,249,.9); }
.prg-range { appearance: none; border: none; background: transparent; cursor: pointer; padding: 6px 11px; border-radius: 8px; font: inherit; font-size: 11px; font-weight: 800; color: #64748b; transition: all .2s ease; }
.prg-range:hover { color: #4f46e5; }
.prg-range.on { background: #fff; color: #4f46e5; box-shadow: 0 2px 8px rgba(15,23,42,.08); }

.prg-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 34px 16px; text-align: center; font-size: 12.5px; font-weight: 600; color: #94a3b8; }
.prg-empty > i { font-size: 22px; color: #cbd5e1; }

/* ── CINCIN KUOTA (donat Highcharts + label di bawahnya) ─────────────────── */
.prg-tag { display: inline-flex; align-items: center; gap: 7px; padding: 7px 13px; border-radius: 11px; background: rgba(241,245,249,.95); font-size: 11.5px; font-weight: 800; color: #475569; }
.prg-tag i { font-size: 11px; }
.prg-tag.is-on { background: rgba(99,102,241,.09); border: 1px solid rgba(99,102,241,.22); color: #4338ca; }

/* ── TABEL LOKER ─────────────────────────────────────────────────────────── */
.prg-lok__head { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; padding: 17px 19px; border-bottom: 1px solid rgba(226,232,240,.85); }

.prg-tblwrap { overflow-x: auto; }
.prg-tbl { width: 100%; min-width: 680px; border-collapse: collapse; }
.prg-tbl thead tr { background: rgba(248,250,252,.9); }
.prg-tbl th { padding: 12px 14px; text-align: left; font-size: 9.5px; font-weight: 800; letter-spacing: .14em; color: #94a3b8; }
.prg-tbl th:first-child, .prg-tbl td:first-child { padding-left: 19px; }
.prg-tbl th:last-child, .prg-tbl td:last-child { padding-right: 19px; }
.prg-tbl td { padding: 15px 14px; border-top: 1px solid rgba(226,232,240,.8); }
.prg-tbl tbody tr { transition: background .22s ease; }
.prg-tbl tbody tr:hover { background: rgba(99,102,241,.04); }
/* Diredupkan, bukan disembunyikan: barisnya memuat satu-satunya jalan untuk
   menampilkannya kembali. */
.prg-tbl tbody tr.is-mati { opacity: .5; }
.prg-tbl tbody tr.is-mati:hover { opacity: .78; }
.ta-r { text-align: right; }
.prg-lok__pos { display: flex; align-items: center; gap: 11px; min-width: 0; }
.prg-lok__ico { width: 32px; height: 32px; flex-shrink: 0; border-radius: 10px; background: linear-gradient(135deg,#818cf8,#4f46e5); display: grid; place-items: center; color: #fff; font-size: 13px; }
.prg-lok__id { min-width: 0; }
.prg-lok__t { font-size: 13.2px; font-weight: 800; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
.prg-lok__lv { margin-top: 2px; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; color: #94a3b8; }
.prg-mpp { padding: 4px 9px; border-radius: 7px; background: rgba(241,245,249,.95); font-family: ui-monospace, SFMono-Regular, monospace; font-size: 11px; font-weight: 700; color: #475569; }
.prg-manual { font-size: 11px; font-weight: 700; color: #cbd5e1; }
.prg-td { font-size: 12.5px; color: #475569; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-td i { color: #8b5cf6; font-size: 11px; }
.prg-tbl__empty { padding: 28px 19px !important; text-align: center; color: #94a3b8; font-size: 12.5px; }

.prg-pick { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; min-height: 340px; padding: 48px 20px; text-align: center; border-radius: 22px; background: rgba(255,255,255,.86); border: 1px solid rgba(255,255,255,.95); box-shadow: 0 20px 52px rgba(15,23,42,.09); }
.prg-pick__ico { display: grid; place-items: center; width: 52px; height: 52px; margin-bottom: 6px; border-radius: 16px; background: rgba(99,102,241,.09); color: #6366f1; font-size: 21px; }
.prg-pick strong { font-size: 14px; font-weight: 800; color: #334155; }
.prg-pick small { font-size: 12.5px; color: #94a3b8; }

/* ── TOMBOL KEPALA ───────────────────────────────────────────────────────── */
.prg-top__act { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.prg-ghost { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 12px 17px; border: 1px solid rgba(226,232,240,.95); border-radius: 13px; background: rgba(255,255,255,.9); cursor: pointer; font: inherit; font-size: 13px; font-weight: 700; color: #475569; transition: all .22s ease; }
.prg-ghost:hover:not(:disabled) { color: #4f46e5; border-color: rgba(99,102,241,.4); transform: translateY(-2px); }
.prg-ghost:disabled { opacity: .45; cursor: not-allowed; }
.prg-ghost--sm { min-height: 38px; padding: 9px 14px; border-radius: 11px; font-size: 12px; font-weight: 800; }

/* ── KOTAK GRAFIK (Highcharts) ───────────────────────────────────────────── */

/* ── KEPALA PANEL LOKER ──────────────────────────────────────────────────── */
.prg-lok__id2 { display: flex; align-items: center; gap: 11px; min-width: 0; }
.prg-lok__badge { width: 32px; height: 32px; flex-shrink: 0; border-radius: 10px; background: linear-gradient(135deg,#fbbf24,#d97706); display: grid; place-items: center; color: #fff; font-size: 14px; }
.prg-lok__act { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }

/* ── KARTU LOKER ─────────────────────────────────────────────────────────── */
.prg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(224px, 100%), 1fr)); gap: 12px; padding: 14px; animation: prgFade .3s ease both; }
.prg-lcard { position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 11px; padding: 16px; border: 1px solid rgba(226,232,240,.9); border-radius: 18px; background: rgba(248,250,252,.75); cursor: pointer; text-align: left; transition: all .28s cubic-bezier(.22,1,.36,1); }
.prg-lcard:hover, .prg-lcard:focus-visible { border-color: rgba(99,102,241,.35); background: #fff; transform: translateY(-4px); box-shadow: 0 20px 44px rgba(79,70,229,.14); outline: none; }
/* Diredupkan, bukan disembunyikan: kartunya memuat satu-satunya jalan untuk
   menampilkannya kembali. */
.prg-lcard.is-mati { opacity: .58; }
.prg-lcard.is-mati:hover { opacity: 1; }
.prg-lcard__st { position: absolute; top: 11px; right: 11px; padding: 3px 8px; border-radius: 7px; font-size: 9px; font-weight: 800; }
.prg-lcard__st.is-on { background: rgba(16,185,129,.12); color: #047857; }
.prg-lcard__st.is-off { background: rgba(100,116,139,.12); color: #475569; }
.prg-lcard__ico { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; color: #fff; font-size: 20px; }
.prg-lcard__id { min-width: 0; }
.prg-lcard__t { font-size: 13.4px; font-weight: 800; letter-spacing: -.01em; color: #0f172a; text-wrap: pretty; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.prg-lcard__mpp { margin-top: 4px; font-family: ui-monospace, SFMono-Regular, monospace; font-size: 10.5px; font-weight: 700; color: #64748b; }
.prg-lcard__dept { display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 10.5px; color: #94a3b8; }
.prg-lcard__dept i { font-size: 10px; }
.prg-lcard__dept span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-lcard__tags { display: flex; gap: 6px; flex-wrap: wrap; }
.prg-mini { padding: 3px 8px; border-radius: 7px; background: rgba(241,245,249,.95); font-size: 9.5px; font-weight: 800; color: #64748b; }
.prg-mini.is-hi { background: rgba(99,102,241,.09); color: #4338ca; }

.prg-empty--span { grid-column: 1 / -1; }
.prg-num { font-size: 13px; font-weight: 800; color: #4338ca; }
.prg-tbl tbody tr.is-klik { cursor: pointer; }

/* ── PANEL DETAIL SATU LOKER ─────────────────────────────────────────────── */
.prg-hero--loker { animation: prgFade .32s ease both; }
.prg-dtop { position: relative; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.prg-dcrumb { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 700; color: #94a3b8; min-width: 0; }
.prg-dcrumb i { font-size: 7px; }
.prg-dcrumb .on { font-family: ui-monospace, SFMono-Regular, monospace; color: #4f46e5; font-weight: 800; }
.prg-dhead { display: flex; align-items: flex-start; gap: 14px; min-width: 0; flex: 1; }
.prg-dhead__ico { flex-shrink: 0; width: 54px; height: 54px; border-radius: 16px; display: grid; place-items: center; color: #fff; font-size: 22px; }
.prg-dhead__id { min-width: 0; }
.prg-dhead__id h3 { margin: 0; font-size: clamp(1.15rem, 2.3vw, 1.5rem); font-weight: 800; letter-spacing: -.03em; color: #0f172a; text-wrap: pretty; }

/* Tautannya ikut DITAMPILKAN, tidak cuma bisa disalin: papan klip ditolak
   peramban di koneksi http biasa, dan tanpa teks yang terlihat rekruter tidak
   punya cara lain mendapatkan alamatnya. */

.prg-fields { position: relative; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(168px, 100%), 1fr)); gap: 10px; margin-top: 18px; }
.prg-field { padding: 12px 14px; border-radius: 14px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); min-width: 0; }
.prg-field__l { font-size: 9.5px; font-weight: 800; letter-spacing: .13em; color: #94a3b8; }
.prg-field__v { margin-top: 5px; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; color: #0f172a; min-width: 0; }
.prg-field__v i { flex-shrink: 0; font-size: 12px; }
.prg-field__v.is-off { color: #94a3b8; font-weight: 700; }

/* ── ISI MPP ─────────────────────────────────────────────────────────────── */
.prg-desc { margin: 14px 0 0; padding: 13px 15px; border-radius: 14px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); font-size: 12.8px; line-height: 1.7; color: #475569; text-wrap: pretty; }
.prg-two { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)); gap: clamp(14px, 2vw, 22px); margin-top: 18px; }
.prg-poin { min-width: 0; }
.prg-poin__t { display: flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #64748b; }
.prg-poin__t i { color: #6366f1; font-size: 13px; }
.prg-poin__row { display: flex; align-items: flex-start; gap: 10px; margin-top: 10px; }
.prg-poin__ico { flex-shrink: 0; width: 22px; height: 22px; border-radius: 7px; display: grid; place-items: center; font-size: 11px; }
.prg-poin__ico.is-ok { background: rgba(16,185,129,.12); color: #059669; }
.prg-poin__ico.is-idg { background: rgba(99,102,241,.1); color: #4f46e5; }
.prg-poin__row > span:last-child { font-size: 12.4px; line-height: 1.55; color: #334155; text-wrap: pretty; }

.prg-tagset { margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(226,232,240,.9); }
.prg-tagset__wrap { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 11px; }
.prg-skill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 9px; background: rgba(99,102,241,.1); font-size: 11.5px; font-weight: 700; color: #4338ca; }
.prg-skill i { font-size: 11px; }
.prg-benefit { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 9px; background: rgba(16,185,129,.1); font-size: 11.5px; font-weight: 700; color: #047857; }
.prg-benefit i { font-size: 10px; }

/* ── KERANGKA PEMUATAN PANEL KIRI ────────────────────────────────────────── */
.prg-skel { display: flex; flex-direction: column; gap: 4px; overflow: hidden; }
.prg-skel__row { display: flex; align-items: center; gap: 11px; padding: 11px; border-radius: 13px; background: rgba(248,250,252,.7); animation: prgPulse2 1.4s ease-in-out infinite; }
.prg-skel__ico { flex-shrink: 0; width: 34px; height: 34px; border-radius: 10px; background: rgba(148,163,184,.22); }
.prg-skel__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.prg-skel__bar { display: block; height: 8px; border-radius: 999px; background: rgba(148,163,184,.22); }
.prg-skel__num { flex-shrink: 0; width: 34px; height: 26px; border-radius: 8px; background: rgba(148,163,184,.18); }
/* Denyut halus, bukan kilau yang berlari: satu panel penuh baris berkilau
   menarik mata ke tempat yang justru belum ada isinya. */
@keyframes prgPulse2 { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }

/* ── LAYAR SEMPIT ────────────────────────────────────────────────────────── */
@media (max-width: 1023px) {
    .prg-aside { position: static; max-width: none; flex-basis: 100%; }
    .prg-aside.is-hide { display: none; }

    /* Filternya berhenti jadi kartu dan menjadi lembar yang naik dari bawah.
       Elemen yang sama, tanpa markup kedua — jadi bidang yang ditambahkan nanti
       tidak mungkin lupa ikut ke salah satunya. */
    /* Di layar sempit ia keluar dari kartu (lihat Teleport di template) dan
       berdiri sendiri sebagai lembar — jadi latar & bayangannya dikembalikan. */
    .prg-filter {
        position: fixed; z-index: 1201; left: 0; right: 0; bottom: 0;
        border: none; border-radius: 20px 20px 0 0; max-height: 78vh; overflow-y: auto;
        background: #fff; box-shadow: 0 -18px 44px rgba(15,23,42,.24);
        transform: translateY(100%); transition: transform .3s cubic-bezier(.22,1,.36,1);
    }
    .prg-filter.is-open { transform: none; }
    .prg-filter__head { position: sticky; top: 0; z-index: 1; background: rgba(255,255,255,.97); }
    .prg-filter__close { display: inline-flex; }
    .prg-sheetbg { display: block; }
    .prg-fab { display: grid; place-items: center; }
    .prg-back { display: grid; place-items: center; }
    .prg-hero__act { width: 100%; }
    .prg-list { max-height: 60vh; }
}
@media (max-width: 640px) {
    .prg-cta { width: 100%; justify-content: center; }
}

/* ── TOMBOL KEPALA ───────────────────────────────────────────────────────── */
.prg-top__act { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.prg-ghost { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 12px 17px; border: 1px solid rgba(226,232,240,.95); border-radius: 13px; background: rgba(255,255,255,.9); cursor: pointer; font: inherit; font-size: 13px; font-weight: 700; color: #475569; transition: all .22s ease; }
.prg-ghost:hover:not(:disabled) { color: #4f46e5; border-color: rgba(99,102,241,.4); transform: translateY(-2px); }
.prg-ghost:disabled { opacity: .45; cursor: not-allowed; }
.prg-ghost--sm { min-height: 38px; padding: 9px 14px; border-radius: 11px; font-size: 11.5px; font-weight: 800; }
.prg-ghost--gold { border-color: rgba(245,158,11,.35); background: rgba(245,158,11,.08); color: #b45309; font-weight: 800; }
.prg-ghost--gold:hover { background: rgba(245,158,11,.16); border-color: rgba(245,158,11,.5); color: #92400e; }
.prg-ghost__n { display: inline-grid; place-items: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: #b45309; color: #fff; font-size: 10.5px; font-weight: 800; }

/* Ikon judul halaman ini indigo, bukan ungu-emas milik Pembukaan Program —
   penanda cepat "saya sedang di master data, bukan di publikasi". */
.prg-title__ico { background: linear-gradient(135deg,#6366f1,#4338ca); box-shadow: 0 12px 26px rgba(67,56,202,.3); }
.prg-fold__ico { background: linear-gradient(135deg,#6366f1,#4338ca); }
.prg-cta { background: linear-gradient(135deg,#6366f1,#4f46e5 55%,#4338ca); box-shadow: 0 14px 30px rgba(79,70,229,.32); }
.prg-search > i { color: #6366f1; }
.prg-item__accent { background: linear-gradient(180deg,#6366f1,#4338ca); }

/* ── DAFTAR PROGRAM DI PANEL KIRI ────────────────────────────────────────── */
.prg-kats { display: flex; gap: 5px; margin-top: 9px; flex-wrap: wrap; }
.prg-kat { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border: 1px solid rgba(226,232,240,.95); border-radius: 9px; background: #fff; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800; color: #64748b; transition: all .2s ease; }
.prg-kat span { opacity: .6; }
.prg-kat:hover { color: #4f46e5; border-color: rgba(99,102,241,.35); }
.prg-kat.on { background: rgba(99,102,241,.09); border-color: rgba(99,102,241,.32); color: #4338ca; }

.prg-item__ico.is-jalan { background: rgba(16,185,129,.11); color: #059669; }
.prg-item__ico.is-draf { background: rgba(245,158,11,.12); color: #d97706; }
.prg-item__ico.is-arsip { background: rgba(100,116,139,.11); color: #64748b; }
.prg-item__code { display: block; margin-top: 3px; font-family: ui-monospace, SFMono-Regular, monospace; font-size: 10px; font-weight: 700; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-item__r2 { display: flex; align-items: center; gap: 7px; margin-top: 3px; font-size: 10.5px; color: #94a3b8; min-width: 0; }
.prg-item__r2 i { flex-shrink: 0; font-size: 10px; }
.prg-item__r2 span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-item__r3 { flex-wrap: wrap; }
.prg-item__pill.is-kat { background: rgba(99,102,241,.1); color: #4338ca; }
.prg-item__pill.is-jalan { background: rgba(16,185,129,.12); color: #047857; }
.prg-item__pill.is-draf { background: rgba(245,158,11,.14); color: #b45309; }
.prg-item__pill.is-arsip { background: rgba(100,116,139,.12); color: #475569; }
.prg-item__pill.is-saya { display: inline-flex; align-items: center; gap: 4px; background: rgba(245,158,11,.14); color: #b45309; }
.prg-item__pill.is-saya i { font-size: 9px; }

/* ── HERO ────────────────────────────────────────────────────────────────── */
.prg-hero__crumb > i.bi-folder2-open { color: #6366f1; }
.prg-chip.is-jalan { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.26); color: #047857; }
.prg-chip.is-draf { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.3); color: #b45309; }
.prg-chip.is-arsip { background: rgba(100,116,139,.1); border: 1px solid rgba(100,116,139,.26); color: #334155; }

.prg-cells { position: relative; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(190px, 100%), 1fr)); gap: 10px; margin-top: 20px; }
.prg-cell { padding: 13px 15px; border-radius: 15px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); min-width: 0; }
.prg-cell__l { font-size: 9.5px; font-weight: 800; letter-spacing: .14em; color: #94a3b8; }
.prg-cell__v { margin-top: 5px; display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 800; color: #0f172a; min-width: 0; }
.prg-cell__v i { flex-shrink: 0; font-size: 13px; }
.prg-cell__v.is-off { color: #94a3b8; font-weight: 700; }
/* Baris penjelas di bawah nilai — sengaja lebih kecil dan lebih redup, supaya
   ia dibaca saat dicari dan diabaikan saat tidak. */
.prg-cell__n { margin-top: 6px; font-size: 10.5px; line-height: 1.45; color: #94a3b8; text-wrap: pretty; }
.prg-cell.is-hi { background: rgba(99,102,241,.06); border-color: rgba(99,102,241,.2); }

/* ── KEPALA PANEL POSISI ─────────────────────────────────────────────────── */
.prg-lok__id2 { display: flex; align-items: center; gap: 11px; min-width: 0; }
.prg-lok__badge { width: 32px; height: 32px; flex-shrink: 0; border-radius: 10px; background: linear-gradient(135deg,#fbbf24,#d97706); display: grid; place-items: center; color: #fff; font-size: 14px; }
.prg-lok__act { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }

/* ── KARTU POSISI ────────────────────────────────────────────────────────── */
.prg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(216px, 100%), 1fr)); gap: 12px; padding: 14px; animation: prgFade .3s ease both; }
.prg-lcard { position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 11px; padding: 16px; border: 1px solid rgba(226,232,240,.9); border-radius: 18px; background: rgba(248,250,252,.75); cursor: pointer; text-align: left; transition: all .28s cubic-bezier(.22,1,.36,1); }
.prg-lcard:hover, .prg-lcard:focus-visible { border-color: rgba(99,102,241,.35); background: #fff; transform: translateY(-4px); box-shadow: 0 20px 44px rgba(79,70,229,.14); outline: none; }
.prg-lcard__st { position: absolute; top: 11px; right: 11px; padding: 3px 8px; border-radius: 7px; font-size: 9px; font-weight: 800; }
.prg-lcard__st.is-buka { background: rgba(16,185,129,.12); color: #047857; }
.prg-lcard__st.is-draf { background: rgba(245,158,11,.14); color: #b45309; }
.prg-lcard__st.is-tutup { background: rgba(100,116,139,.12); color: #475569; }
.prg-lcard__ico { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; color: #fff; font-size: 20px; }
.prg-lcard__id { min-width: 0; }
.prg-lcard__t { font-size: 13.4px; font-weight: 800; letter-spacing: -.01em; color: #0f172a; text-wrap: pretty; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.prg-lcard__mpp { margin-top: 4px; font-family: ui-monospace, SFMono-Regular, monospace; font-size: 10.5px; font-weight: 700; color: #64748b; }
.prg-lcard__dept { display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 10.5px; color: #94a3b8; }
.prg-lcard__dept i { flex-shrink: 0; font-size: 10px; }
.prg-lcard__dept span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-lcard__tags { margin-top: auto; display: flex; gap: 6px; flex-wrap: wrap; }
.prg-mini { display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; border-radius: 7px; background: rgba(241,245,249,.95); font-size: 9.5px; font-weight: 800; color: #64748b; }
.prg-mini i { font-size: 10px; }
.prg-mini.is-hi { background: rgba(99,102,241,.09); color: #4338ca; }
.prg-mini.is-saya { background: rgba(245,158,11,.14); color: #b45309; }

.prg-empty--span { grid-column: 1 / -1; }
.prg-num { font-size: 13px; font-weight: 800; color: #4338ca; }
.prg-tbl tbody tr.is-klik { cursor: pointer; }
.prg-lok__st { margin-left: 7px; padding: 2px 7px; border-radius: 6px; font-size: 9.5px; font-weight: 800; vertical-align: middle; }
.prg-lok__st.is-buka { background: rgba(16,185,129,.12); color: #047857; }
.prg-lok__st.is-draf { background: rgba(245,158,11,.14); color: #b45309; }
.prg-lok__st.is-tutup { background: rgba(100,116,139,.12); color: #475569; }
.prg-lok__t { max-width: none; white-space: normal; }

.prg-foot { display: flex; align-items: center; justify-content: space-between; gap: 14px 22px; flex-wrap: wrap; padding: 14px 19px; border-top: 1px solid rgba(226,232,240,.85); background: rgba(248,250,252,.7); font-size: 11.5px; color: #64748b; }
.prg-foot__by { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.prg-foot__by > .bi-pencil-square { color: #8b5cf6; font-size: 13px; }
.prg-foot__by b { color: #334155; }
.prg-foot__av { display: grid; place-items: center; width: 24px; height: 24px; border-radius: 8px; color: #fff; font-size: 9px; font-weight: 800; }
.prg-foot__at { display: inline-flex; align-items: center; gap: 7px; color: #94a3b8; }

/* ── DETAIL SATU POSISI ──────────────────────────────────────────────────── */
.prg-hero--loker { animation: prgFade .32s ease both; }
.prg-dtop { position: relative; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.prg-dcrumb { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 700; color: #94a3b8; min-width: 0; }
.prg-dcrumb i { font-size: 7px; }
.prg-dcrumb .on { font-family: ui-monospace, SFMono-Regular, monospace; color: #4f46e5; font-weight: 800; }
.prg-dhead { position: relative; display: flex; align-items: flex-start; gap: 14px; min-width: 0; margin-top: 18px; }
.prg-dhead__ico { flex-shrink: 0; width: 54px; height: 54px; border-radius: 16px; display: grid; place-items: center; color: #fff; font-size: 22px; }
.prg-dhead__id { min-width: 0; }
.prg-dhead__id h3 { margin: 0; font-size: clamp(1.15rem, 2.3vw, 1.5rem); font-weight: 800; letter-spacing: -.03em; color: #0f172a; text-wrap: pretty; }

.prg-fields { position: relative; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(168px, 100%), 1fr)); gap: 10px; margin-top: 20px; }
.prg-field { padding: 12px 14px; border-radius: 14px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); min-width: 0; }
.prg-field__l { font-size: 9.5px; font-weight: 800; letter-spacing: .13em; color: #94a3b8; }
.prg-field__v { margin-top: 5px; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; color: #0f172a; min-width: 0; }
.prg-field__v i { flex-shrink: 0; font-size: 12px; }
.prg-field__v.is-off { color: #94a3b8; font-weight: 700; }

/* ── ISI MPP ─────────────────────────────────────────────────────────────── */
.prg-desc { margin: 14px 0 0; padding: 13px 15px; border-radius: 14px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); font-size: 12.8px; line-height: 1.7; color: #475569; text-wrap: pretty; }
.prg-two { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)); gap: clamp(14px, 2vw, 22px); margin-top: 18px; }
.prg-poin__t { display: flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #64748b; }
.prg-poin__t i { color: #6366f1; font-size: 13px; }
.prg-poin__row { display: flex; align-items: flex-start; gap: 10px; margin-top: 10px; }
.prg-poin__ico { flex-shrink: 0; width: 22px; height: 22px; border-radius: 7px; display: grid; place-items: center; font-size: 11px; }
.prg-poin__ico.is-ok { background: rgba(16,185,129,.12); color: #059669; }
.prg-poin__ico.is-idg { background: rgba(99,102,241,.1); color: #4f46e5; }
.prg-poin__row > span:last-child { font-size: 12.4px; line-height: 1.55; color: #334155; text-wrap: pretty; }
.prg-tagset { margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(226,232,240,.9); }
.prg-tagset__wrap { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 11px; }
.prg-skill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 9px; background: rgba(99,102,241,.1); font-size: 11.5px; font-weight: 700; color: #4338ca; }
.prg-skill i { font-size: 11px; }
.prg-benefit { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 9px; background: rgba(16,185,129,.1); font-size: 11.5px; font-weight: 700; color: #047857; }
.prg-benefit i { font-size: 10px; }

/* ── SYARAT PENYARINGAN ──────────────────────────────────────────────────── */
.prg-rules { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
.prg-rule { position: relative; padding: 13px 15px 13px 19px; border-radius: 14px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); }
.prg-rule__bar { position: absolute; left: 0; top: 12px; bottom: 12px; width: 3px; border-radius: 0 3px 3px 0; background: linear-gradient(180deg,#fbbf24,#d97706); }
.prg-rule__top { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.prg-rule__name { font-size: 12.8px; font-weight: 800; color: #0f172a; }
.prg-rule__act { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 7px; font-size: 10px; font-weight: 800; }
.prg-rule__act.is-gugur { background: rgba(220,38,38,.1); color: #b91c1c; }
.prg-rule__act.is-mark { background: rgba(99,102,241,.1); color: #4338ca; }
.prg-rule__match { padding: 3px 9px; border-radius: 7px; background: rgba(241,245,249,.95); font-size: 10px; font-weight: 800; color: #64748b; }
.prg-rule__match.is-off { background: rgba(100,116,139,.14); color: #475569; }
.prg-conds { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
.prg-cond { display: inline-flex; align-items: center; gap: 6px; padding: 4px 9px; border-radius: 8px; background: #fff; border: 1px solid rgba(226,232,240,.9); font-size: 11px; color: #475569; }
.prg-cond i { color: #8b5cf6; font-size: 10px; }
.prg-mono { font-family: ui-monospace, SFMono-Regular, monospace; }

/* ── BATCH ───────────────────────────────────────────────────────────────── */
.prg-batchset { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
.prg-batch { display: inline-flex; align-items: center; gap: 8px; padding: 8px 13px; border-radius: 12px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); font-size: 11.5px; color: #64748b; }
.prg-batch i { color: #6366f1; font-size: 12px; }
.prg-batch b { font-weight: 800; color: #0f172a; }
.prg-batch em { padding: 2px 7px; border-radius: 6px; background: rgba(99,102,241,.09); font-style: normal; font-size: 9.5px; font-weight: 800; color: #4338ca; }

/* ── FILTER: kartu di layar lebar, lembar bawah di layar sempit ──────────── */
/* Bagian dari kartu daftar, bukan kartu tersendiri: tanpa latar, tanpa
   bayangan, hanya dipisahkan satu garis. Bayangan kedua di dalam satu
   kartu membuatnya terbaca seperti dua kartu yang kebetulan bersentuhan. */
.prg-filter { border-top: 1px solid rgba(226,232,240,.85); background: rgba(248,250,252,.55); }
.prg-filter__head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 13px 16px; border-bottom: 1px solid rgba(226,232,240,.85); }
.prg-filter__title { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 800; color: #0f172a; }
.prg-filter__title i { color: #6366f1; }
.prg-filter__act { display: flex; align-items: center; gap: 6px; }
.prg-filter__reset { display: inline-flex; align-items: center; gap: 5px; padding: 6px 10px; border: 1px solid rgba(226,232,240,.95); border-radius: 9px; background: #fff; cursor: pointer; font: inherit; font-size: 11px; font-weight: 800; color: #64748b; transition: all .2s ease; }
.prg-filter__reset:hover { color: #4f46e5; border-color: rgba(99,102,241,.4); }
/* Tombol tutup hanya berarti pada lembar bawah. */
.prg-filter__close { display: none; align-items: center; justify-content: center; width: 28px; height: 28px; border: 1px solid rgba(226,232,240,.95); border-radius: 9px; background: #fff; cursor: pointer; color: #64748b; font-size: 12px; }
.prg-filter__grid { display: flex; flex-direction: column; gap: 11px; padding: 14px 16px 16px; }
.prg-filter__count { font-size: 11.5px; color: #94a3b8; }
.prg-filter__count strong { color: #4338ca; font-size: 13px; }

.prg-sheetbg { display: none; position: fixed; inset: 0; z-index: 1200; background: rgba(15,23,42,.35); backdrop-filter: blur(2px); }
.prg-fab { display: none; position: fixed; z-index: 1199; right: clamp(12px, 3vw, 22px); bottom: clamp(16px, 4vw, 26px); width: 52px; height: 52px; border: none; border-radius: 17px; cursor: pointer; color: #fff; font-size: 18px; background: linear-gradient(135deg,#6366f1,#4338ca); box-shadow: 0 16px 34px rgba(67,56,202,.34); transition: all .24s cubic-bezier(.22,1,.36,1); }
.prg-fab:hover { transform: translateY(-3px); box-shadow: 0 22px 44px rgba(67,56,202,.44); }
.prg-fab__badge { position: absolute; top: -5px; right: -5px; display: grid; place-items: center; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 999px; background: #f59e0b; color: #fff; font-size: 10.5px; font-weight: 800; border: 2px solid #fff; }

/* ── LAYAR SEMPIT ────────────────────────────────────────────────────────── */
@media (max-width: 1023px) {
    .prg-aside { position: static; max-width: none; flex-basis: 100%; }
    .prg-aside.is-hide { display: none; }
    .prg-back { display: grid; place-items: center; }
    .prg-hero__act { width: 100%; }
    .prg-list { max-height: 60vh; }
}
@media (max-width: 640px) {
    .prg-cta { width: 100%; justify-content: center; }
}

/* ── TOMBOL KEPALA ───────────────────────────────────────────────────────── */
.prg-top__act { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.prg-ghost { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 12px 17px; border: 1px solid rgba(226,232,240,.95); border-radius: 13px; background: rgba(255,255,255,.9); cursor: pointer; font: inherit; font-size: 13px; font-weight: 700; color: #475569; transition: all .22s ease; }
.prg-ghost:hover:not(:disabled) { color: #4f46e5; border-color: rgba(99,102,241,.4); transform: translateY(-2px); }
.prg-ghost:disabled { opacity: .45; cursor: not-allowed; }
.prg-ghost--sm { min-height: 38px; padding: 9px 14px; border-radius: 11px; font-size: 11.5px; font-weight: 800; }
.prg-ghost--gold { border-color: rgba(245,158,11,.35); background: rgba(245,158,11,.08); color: #b45309; font-weight: 800; }
.prg-ghost--gold:hover { background: rgba(245,158,11,.16); border-color: rgba(245,158,11,.5); color: #92400e; }
.prg-ghost__n { display: inline-grid; place-items: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: #b45309; color: #fff; font-size: 10.5px; font-weight: 800; }

/* Ikon judul halaman ini indigo, bukan ungu-emas milik Pembukaan Program —
   penanda cepat "saya sedang di master data, bukan di publikasi". */
.prg-title__ico { background: linear-gradient(135deg,#6366f1,#4338ca); box-shadow: 0 12px 26px rgba(67,56,202,.3); }
.prg-fold__ico { background: linear-gradient(135deg,#6366f1,#4338ca); }
.prg-cta { background: linear-gradient(135deg,#6366f1,#4f46e5 55%,#4338ca); box-shadow: 0 14px 30px rgba(79,70,229,.32); }
.prg-search > i { color: #6366f1; }
.prg-item__accent { background: linear-gradient(180deg,#6366f1,#4338ca); }

/* ── DAFTAR PROGRAM DI PANEL KIRI ────────────────────────────────────────── */
.prg-kats { display: flex; gap: 5px; margin-top: 9px; flex-wrap: wrap; }
.prg-kat { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border: 1px solid rgba(226,232,240,.95); border-radius: 9px; background: #fff; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800; color: #64748b; transition: all .2s ease; }
.prg-kat span { opacity: .6; }
.prg-kat:hover { color: #4f46e5; border-color: rgba(99,102,241,.35); }
.prg-kat.on { background: rgba(99,102,241,.09); border-color: rgba(99,102,241,.32); color: #4338ca; }

.prg-item__ico.is-jalan { background: rgba(16,185,129,.11); color: #059669; }
.prg-item__ico.is-draf { background: rgba(245,158,11,.12); color: #d97706; }
.prg-item__ico.is-arsip { background: rgba(100,116,139,.11); color: #64748b; }
.prg-item__code { display: block; margin-top: 3px; font-family: ui-monospace, SFMono-Regular, monospace; font-size: 10px; font-weight: 700; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-item__r2 { display: flex; align-items: center; gap: 7px; margin-top: 3px; font-size: 10.5px; color: #94a3b8; min-width: 0; }
.prg-item__r2 i { flex-shrink: 0; font-size: 10px; }
.prg-item__r2 span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-item__r3 { flex-wrap: wrap; }
.prg-item__pill.is-kat { background: rgba(99,102,241,.1); color: #4338ca; }
.prg-item__pill.is-jalan { background: rgba(16,185,129,.12); color: #047857; }
.prg-item__pill.is-draf { background: rgba(245,158,11,.14); color: #b45309; }
.prg-item__pill.is-arsip { background: rgba(100,116,139,.12); color: #475569; }
.prg-item__pill.is-saya { display: inline-flex; align-items: center; gap: 4px; background: rgba(245,158,11,.14); color: #b45309; }
.prg-item__pill.is-saya i { font-size: 9px; }

/* ── HERO ────────────────────────────────────────────────────────────────── */
.prg-hero__crumb > i.bi-folder2-open { color: #6366f1; }
.prg-chip.is-jalan { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.26); color: #047857; }
.prg-chip.is-draf { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.3); color: #b45309; }
.prg-chip.is-arsip { background: rgba(100,116,139,.1); border: 1px solid rgba(100,116,139,.26); color: #334155; }

.prg-cells { position: relative; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(190px, 100%), 1fr)); gap: 10px; margin-top: 20px; }
.prg-cell { padding: 13px 15px; border-radius: 15px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); min-width: 0; }
.prg-cell__l { font-size: 9.5px; font-weight: 800; letter-spacing: .14em; color: #94a3b8; }
.prg-cell__v { margin-top: 5px; display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 800; color: #0f172a; min-width: 0; }
.prg-cell__v i { flex-shrink: 0; font-size: 13px; }
.prg-cell__v.is-off { color: #94a3b8; font-weight: 700; }
/* Baris penjelas di bawah nilai — sengaja lebih kecil dan lebih redup, supaya
   ia dibaca saat dicari dan diabaikan saat tidak. */
.prg-cell__n { margin-top: 6px; font-size: 10.5px; line-height: 1.45; color: #94a3b8; text-wrap: pretty; }
.prg-cell.is-hi { background: rgba(99,102,241,.06); border-color: rgba(99,102,241,.2); }

/* ── KEPALA PANEL POSISI ─────────────────────────────────────────────────── */
.prg-lok__id2 { display: flex; align-items: center; gap: 11px; min-width: 0; }
.prg-lok__badge { width: 32px; height: 32px; flex-shrink: 0; border-radius: 10px; background: linear-gradient(135deg,#fbbf24,#d97706); display: grid; place-items: center; color: #fff; font-size: 14px; }
.prg-lok__act { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }

/* ── KARTU POSISI ────────────────────────────────────────────────────────── */
.prg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(216px, 100%), 1fr)); gap: 12px; padding: 14px; animation: prgFade .3s ease both; }
.prg-lcard { position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 11px; padding: 16px; border: 1px solid rgba(226,232,240,.9); border-radius: 18px; background: rgba(248,250,252,.75); cursor: pointer; text-align: left; transition: all .28s cubic-bezier(.22,1,.36,1); }
.prg-lcard:hover, .prg-lcard:focus-visible { border-color: rgba(99,102,241,.35); background: #fff; transform: translateY(-4px); box-shadow: 0 20px 44px rgba(79,70,229,.14); outline: none; }
.prg-lcard__st { position: absolute; top: 11px; right: 11px; padding: 3px 8px; border-radius: 7px; font-size: 9px; font-weight: 800; }
.prg-lcard__st.is-buka { background: rgba(16,185,129,.12); color: #047857; }
.prg-lcard__st.is-draf { background: rgba(245,158,11,.14); color: #b45309; }
.prg-lcard__st.is-tutup { background: rgba(100,116,139,.12); color: #475569; }
.prg-lcard__ico { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; color: #fff; font-size: 20px; }
.prg-lcard__id { min-width: 0; }
.prg-lcard__t { font-size: 13.4px; font-weight: 800; letter-spacing: -.01em; color: #0f172a; text-wrap: pretty; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.prg-lcard__mpp { margin-top: 4px; font-family: ui-monospace, SFMono-Regular, monospace; font-size: 10.5px; font-weight: 700; color: #64748b; }
.prg-lcard__dept { display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 10.5px; color: #94a3b8; }
.prg-lcard__dept i { flex-shrink: 0; font-size: 10px; }
.prg-lcard__dept span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-lcard__tags { margin-top: auto; display: flex; gap: 6px; flex-wrap: wrap; }
.prg-mini { display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; border-radius: 7px; background: rgba(241,245,249,.95); font-size: 9.5px; font-weight: 800; color: #64748b; }
.prg-mini i { font-size: 10px; }
.prg-mini.is-hi { background: rgba(99,102,241,.09); color: #4338ca; }
.prg-mini.is-saya { background: rgba(245,158,11,.14); color: #b45309; }

.prg-empty--span { grid-column: 1 / -1; }
.prg-num { font-size: 13px; font-weight: 800; color: #4338ca; }
.prg-tbl tbody tr.is-klik { cursor: pointer; }
.prg-lok__st { margin-left: 7px; padding: 2px 7px; border-radius: 6px; font-size: 9.5px; font-weight: 800; vertical-align: middle; }
.prg-lok__st.is-buka { background: rgba(16,185,129,.12); color: #047857; }
.prg-lok__st.is-draf { background: rgba(245,158,11,.14); color: #b45309; }
.prg-lok__st.is-tutup { background: rgba(100,116,139,.12); color: #475569; }
.prg-lok__t { max-width: none; white-space: normal; }

.prg-foot { display: flex; align-items: center; justify-content: space-between; gap: 14px 22px; flex-wrap: wrap; padding: 14px 19px; border-top: 1px solid rgba(226,232,240,.85); background: rgba(248,250,252,.7); font-size: 11.5px; color: #64748b; }
.prg-foot__by { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.prg-foot__by > .bi-pencil-square { color: #8b5cf6; font-size: 13px; }
.prg-foot__by b { color: #334155; }
.prg-foot__av { display: grid; place-items: center; width: 24px; height: 24px; border-radius: 8px; color: #fff; font-size: 9px; font-weight: 800; }
.prg-foot__at { display: inline-flex; align-items: center; gap: 7px; color: #94a3b8; }

/* ── DETAIL SATU POSISI ──────────────────────────────────────────────────── */
.prg-hero--loker { animation: prgFade .32s ease both; }
.prg-dtop { position: relative; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.prg-dcrumb { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 700; color: #94a3b8; min-width: 0; }
.prg-dcrumb i { font-size: 7px; }
.prg-dcrumb .on { font-family: ui-monospace, SFMono-Regular, monospace; color: #4f46e5; font-weight: 800; }
.prg-dhead { position: relative; display: flex; align-items: flex-start; gap: 14px; min-width: 0; margin-top: 18px; }
.prg-dhead__ico { flex-shrink: 0; width: 54px; height: 54px; border-radius: 16px; display: grid; place-items: center; color: #fff; font-size: 22px; }
.prg-dhead__id { min-width: 0; }
.prg-dhead__id h3 { margin: 0; font-size: clamp(1.15rem, 2.3vw, 1.5rem); font-weight: 800; letter-spacing: -.03em; color: #0f172a; text-wrap: pretty; }

.prg-fields { position: relative; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(168px, 100%), 1fr)); gap: 10px; margin-top: 20px; }
.prg-field { padding: 12px 14px; border-radius: 14px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); min-width: 0; }
.prg-field__l { font-size: 9.5px; font-weight: 800; letter-spacing: .13em; color: #94a3b8; }
.prg-field__v { margin-top: 5px; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; color: #0f172a; min-width: 0; }
.prg-field__v i { flex-shrink: 0; font-size: 12px; }
.prg-field__v.is-off { color: #94a3b8; font-weight: 700; }

/* ── ISI MPP ─────────────────────────────────────────────────────────────── */
.prg-desc { margin: 14px 0 0; padding: 13px 15px; border-radius: 14px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); font-size: 12.8px; line-height: 1.7; color: #475569; text-wrap: pretty; }
.prg-two { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)); gap: clamp(14px, 2vw, 22px); margin-top: 18px; }
.prg-poin__t { display: flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #64748b; }
.prg-poin__t i { color: #6366f1; font-size: 13px; }
.prg-poin__row { display: flex; align-items: flex-start; gap: 10px; margin-top: 10px; }
.prg-poin__ico { flex-shrink: 0; width: 22px; height: 22px; border-radius: 7px; display: grid; place-items: center; font-size: 11px; }
.prg-poin__ico.is-ok { background: rgba(16,185,129,.12); color: #059669; }
.prg-poin__ico.is-idg { background: rgba(99,102,241,.1); color: #4f46e5; }
.prg-poin__row > span:last-child { font-size: 12.4px; line-height: 1.55; color: #334155; text-wrap: pretty; }
.prg-tagset { margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(226,232,240,.9); }
.prg-tagset__wrap { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 11px; }
.prg-skill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 9px; background: rgba(99,102,241,.1); font-size: 11.5px; font-weight: 700; color: #4338ca; }
.prg-skill i { font-size: 11px; }
.prg-benefit { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 9px; background: rgba(16,185,129,.1); font-size: 11.5px; font-weight: 700; color: #047857; }
.prg-benefit i { font-size: 10px; }

/* ── SYARAT PENYARINGAN ──────────────────────────────────────────────────── */
.prg-rules { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
.prg-rule { position: relative; padding: 13px 15px 13px 19px; border-radius: 14px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); }
.prg-rule__bar { position: absolute; left: 0; top: 12px; bottom: 12px; width: 3px; border-radius: 0 3px 3px 0; background: linear-gradient(180deg,#fbbf24,#d97706); }
.prg-rule__top { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.prg-rule__name { font-size: 12.8px; font-weight: 800; color: #0f172a; }
.prg-rule__act { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 7px; font-size: 10px; font-weight: 800; }
.prg-rule__act.is-gugur { background: rgba(220,38,38,.1); color: #b91c1c; }
.prg-rule__act.is-mark { background: rgba(99,102,241,.1); color: #4338ca; }
.prg-rule__match { padding: 3px 9px; border-radius: 7px; background: rgba(241,245,249,.95); font-size: 10px; font-weight: 800; color: #64748b; }
.prg-rule__match.is-off { background: rgba(100,116,139,.14); color: #475569; }
.prg-conds { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
.prg-cond { display: inline-flex; align-items: center; gap: 6px; padding: 4px 9px; border-radius: 8px; background: #fff; border: 1px solid rgba(226,232,240,.9); font-size: 11px; color: #475569; }
.prg-cond i { color: #8b5cf6; font-size: 10px; }
.prg-mono { font-family: ui-monospace, SFMono-Regular, monospace; }

/* ── BATCH ───────────────────────────────────────────────────────────────── */
.prg-batchset { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
.prg-batch { display: inline-flex; align-items: center; gap: 8px; padding: 8px 13px; border-radius: 12px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); font-size: 11.5px; color: #64748b; }
.prg-batch i { color: #6366f1; font-size: 12px; }
.prg-batch b { font-weight: 800; color: #0f172a; }
.prg-batch em { padding: 2px 7px; border-radius: 6px; background: rgba(99,102,241,.09); font-style: normal; font-size: 9.5px; font-weight: 800; color: #4338ca; }

/* Grid batch di borang kini tiga kolom (Nama, Kuota, Status) — dulu empat,
   sebelum kolom Terisi dicabut. */

.pkg-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; border-radius: 8px; padding: 4px 10px; }
.pkg-pill--gold { color: #b45309; background: rgba(245, 158, 11, .14); }
.pkg-pill--sky { color: #0369a1; background: rgba(14, 165, 233, .12); }
.pkg-pill--green { color: #059669; background: rgba(16, 185, 129, .12); }
.pkg-pill--amber { color: #b45309; background: rgba(245, 158, 11, .14); }
.pkg-pill--slate { color: #64748b; background: #eef0f7; }
@keyframes pkgIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }
/* Rincian kecil di sebelah judul: berapa syarat yang menggugurkan vs menandai. */

/* posisi table (grid, flat) */
/* Pemegang loker, di dalam tabel posisi. Miliknya sendiri ditandai — rekruter
   yang membuka program berisi belasan loker perlu menemukan bagiannya tanpa
   membaca satu per satu. */

/* Langkah 1: kategori dulu — baris tunggal + ajakan sebelum kategori dipilih */
.wca-frow--single { grid-template-columns: 1fr !important; }
.pgk-waitkat { display: flex; align-items: center; gap: 10px; margin-top: .9rem; padding: 14px 16px; border: 1px dashed #d9def0; border-radius: 14px; background: #fbfbfe; color: #64748b; font-size: 13px; }
.pgk-waitkat i { font-size: 1.1rem; color: #8b5cf6; flex: 0 0 auto; }
.pgk-waitkat strong { color: #4f46e5; }
/* Warna label: baris swatch klik-langsung (mengisi ruang, bukan kotak mungil) */
.pgk-swatches { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; min-height: 40px; }
.pgk-swatch { appearance: none; border: none; cursor: pointer; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: .8rem; box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .08); transition: transform .14s, box-shadow .14s; }
.pgk-swatch:hover { transform: scale(1.12); }
.pgk-swatch.on { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #6366f1, 0 6px 14px rgba(99, 102, 241, .3); transform: scale(1.08); }
/* ── Pemilih MPP model KARTU (ala Monitoring MPP) ── */
.pgk-mpppick { margin-bottom: .9rem; }
.pgk-mpppick__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: .6rem; }
.pgk-mppsearch { position: relative; display: flex; align-items: center; flex: 1; min-width: 220px; max-width: 340px; }
.pgk-mppsearch .bi-search { position: absolute; left: 12px; color: #94a3b8; font-size: .8rem; }
.pgk-mppsearch input { width: 100%; padding: 9px 32px 9px 34px; border-radius: 11px; border: 1px solid #e6e9f3; background: #fff; font: inherit; font-size: .8rem; color: #334155; outline: none; transition: all .18s; }
.pgk-mppsearch input:focus { border-color: #a5b4fc; box-shadow: 0 0 0 4px rgba(99, 102, 241, .12); }
.pgk-mppsearch button { position: absolute; right: 8px; appearance: none; border: none; background: #eef0f7; width: 20px; height: 20px; border-radius: 6px; color: #64748b; cursor: pointer; font-size: .6rem; display: flex; align-items: center; justify-content: center; }
/* Grid 3 kolom + scrollbar sendiri; kartu meniru persis MppCard Monitoring MPP. */
.pgk-mppgrid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; max-height: 350px; overflow-y: auto; padding: 3px 6px 6px 3px; }
@media (max-width: 900px) { .pgk-mppgrid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 620px) { .pgk-mppgrid { grid-template-columns: 1fr; } }
/* ── TAB LINGKUP PIC ── */
.pgk-mpptabs { display: flex; align-items: center; flex-wrap: wrap; gap: .45rem; margin: .1rem 0 .75rem; }
.pgk-mpptab {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .35rem .75rem; border-radius: 999px; cursor: pointer;
    border: 1px solid #e2e8f0; background: #fff; color: #64748b;
    font-size: 12px; font-weight: 700; transition: all .18s ease;
}
.pgk-mpptab:hover { border-color: #c7d2fe; color: #4338ca; }
.pgk-mpptab.on { border-color: #6366f1; background: #eef2ff; color: #4338ca; }
.pgk-mpptab__n { padding: 0 .38rem; border-radius: 999px; background: rgba(99,102,241,.14); font-size: 10.5px; }
.pgk-mpptab.on .pgk-mpptab__n { background: #6366f1; color: #fff; }
.pgk-mpptabs__note { display: inline-flex; align-items: center; gap: .3rem; margin-left: auto; font-size: 11px; color: #94a3b8; }
.pgk-mpptabs__note .bi { font-size: 10px; }

/* ── KARTU TERKUNCI ──
   Sengaja TIDAK dibuat pucat sampai tak terbaca: isinya justru perlu dibaca
   untuk memutuskan apakah perlu meminta MPP ini ke pemegangnya. Yang dihapus
   adalah SINYAL BISA DIKLIK — kursor, angkat saat hover, dan penanda centang.
   Kartunya sendiri memang tidak punya penangan klik sama sekali. */
.pgk-mppcard.is-terkunci { cursor: default; background: #fbfcfe; border-style: dashed; border-color: #dbe2ef; box-shadow: none; }
.pgk-mppcard.is-terkunci:hover { transform: none; box-shadow: none; border-color: #cbd5e1; }
.pgk-mppcard__gembok {
    display: grid; place-items: center; flex: none;
    width: 1.4rem; height: 1.4rem; border-radius: .55rem;
    background: #f1f5f9; color: #94a3b8; font-size: 11px;
}
.pgk-mppcard__pic {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .28rem .55rem; border-radius: .5rem; align-self: flex-start;
    background: #fff7ed; color: #9a3412; font-size: 11px; font-weight: 600;
}
.pgk-mppcard__pic b { font-weight: 800; }
.pgk-mppcard__pic .bi { font-size: 10.5px; }

.pgk-mppcard { position: relative; overflow: hidden; display: flex; flex-direction: column; gap: .55rem; cursor: pointer; background: #fff; border: 1px solid #e6e9f3; border-radius: 1.15rem; padding: 1rem 1.05rem; box-shadow: 0 6px 20px rgba(15, 23, 42, .04); transition: transform .2s cubic-bezier(.22, 1, .36, 1), box-shadow .2s ease, border-color .2s ease; }
.pgk-mppcard::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #6366f1, #8b5cf6, #6366f1); opacity: 0; transition: opacity .25s ease; }
.pgk-mppcard:hover::after, .pgk-mppcard.on::after { opacity: 1; }
.pgk-mppcard:hover { transform: translateY(-4px); border-color: #c7d2fe; box-shadow: 0 18px 36px rgba(79, 70, 229, .14), 0 6px 12px rgba(15, 23, 42, .05); }
.pgk-mppcard.on { border-color: #6366f1; background: linear-gradient(135deg, #fbfaff, #f5f4ff); box-shadow: 0 12px 28px rgba(99, 102, 241, .18); }
.pgk-mppcard__head { display: flex; align-items: flex-start; gap: 8px; }
.pgk-mppcard__check { margin-top: 2px; width: 20px; height: 20px; border-radius: 7px; border: 1.5px solid #d9def0; background: #fff; color: transparent; font-size: .7rem; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; transition: all .16s; }
.pgk-mppcard.on .pgk-mppcard__check { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.pgk-mppcard__title { flex: 1; min-width: 0; margin: 0; font-size: .95rem; font-weight: 900; line-height: 1.25; color: #0f172a; }
.pgk-mppcard__info { appearance: none; border: none; background: transparent; cursor: pointer; color: #94a3b8; font-size: .85rem; padding: 2px; flex: 0 0 auto; transition: color .16s; }
.pgk-mppcard__info:hover { color: #4f46e5; }
.pgk-mppcard__badges { display: flex; flex-wrap: wrap; gap: .35rem; }
.pgk-bdg { display: inline-flex; align-items: center; gap: .35rem; font-size: .68rem; font-weight: 800; border-radius: .55rem; padding: .24rem .55rem; }
.pgk-bdg--indigo { color: #4338ca; background: rgba(99, 102, 241, .12); }
.pgk-bdg--sky { color: #0369a1; background: rgba(14, 165, 233, .12); }
.pgk-mppcard__no { display: inline-flex; align-items: center; gap: .15rem; font-size: .74rem; font-weight: 800; color: #94a3b8; font-family: 'JetBrains Mono', monospace; }
.pgk-mppcard__tags { display: flex; flex-wrap: wrap; gap: .4rem; }
.mppt { display: inline-flex; align-items: center; gap: .35rem; font-size: .68rem; font-weight: 800; white-space: nowrap; padding: .26rem .55rem; border-radius: .6rem; }
.mppt--emp { background: linear-gradient(135deg, rgba(99, 102, 241, .12), rgba(139, 92, 246, .08)); color: #4338ca; border: 1px solid rgba(99, 102, 241, .18); }
.mppt--wp { background: linear-gradient(135deg, rgba(16, 185, 129, .1), rgba(6, 182, 212, .06)); color: #0f766e; border: 1px solid rgba(16, 185, 129, .18); }
.mppt--exp { background: linear-gradient(135deg, rgba(245, 158, 11, .1), rgba(251, 191, 36, .06)); color: #b45309; border: 1px solid rgba(245, 158, 11, .2); }
.mppt__dot { width: .42rem; height: .42rem; border-radius: 50%; flex: none; }
.mppt__dot--emp { background: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, .2); }
.mppt__dot--wp { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, .2); }
.mppt__dot--exp { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, .2); }
.pgk-mppcard__meta { display: flex; flex-wrap: wrap; gap: .35rem .9rem; padding-top: .45rem; border-top: 1px dashed #e6e9f3; margin-top: auto; }
.pgk-mppcard__meta span { display: inline-flex; align-items: center; gap: .35rem; font-size: .72rem; font-weight: 700; color: #475569; }
.pgk-mppcard__meta i { color: #6366f1; }
/* Panel "Sudah Dipilih" — scrollbar sendiri */
.pgk-selpanel { margin-top: 1rem; border: 1px solid #e6e9f3; border-radius: 14px; background: #fbfbfe; overflow: hidden; }
.pgk-selpanel__hd { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 10px 14px; border-bottom: 1px solid #eef0f7; background: #fff; font-size: .8rem; font-weight: 800; color: #1e293b; }
.pgk-selpanel__hd > i { color: #059669; }
.pgk-selpanel__tot { display: inline-flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 800; color: #4f46e5; background: rgba(99, 102, 241, .1); border-radius: 999px; padding: 4px 11px; }
.pgk-selpanel__hint { margin-left: auto; font-size: .68rem; font-weight: 600; color: #94a3b8; }
.pgk-selpanel__body { max-height: 280px; overflow-y: auto; padding: 12px; }
/* ── Panel checklist Nilai (operator daftar) ── */
.pgk-multichk { border: 1px solid #e6e9f3; border-radius: 12px; background: #fff; overflow: hidden; }
.pgk-multichk__search { position: relative; display: flex; align-items: center; border-bottom: 1px solid #eef0f7; }
.pgk-multichk__search .bi-search { position: absolute; left: 11px; color: #94a3b8; font-size: .72rem; }
.pgk-multichk__search input { width: 100%; border: none; outline: none; padding: 8px 10px 8px 30px; font: inherit; font-size: .76rem; color: #334155; background: transparent; }
.pgk-multichk__list { max-height: 170px; overflow-y: auto; padding: 4px; }
.pgk-multichk__item { display: flex; align-items: center; gap: 8px; padding: 6px 9px; border-radius: 8px; cursor: pointer; font-size: .78rem; color: #475569; transition: background .12s; }
.pgk-multichk__item:hover { background: #f7f8fc; }
.pgk-multichk__item.on { background: rgba(99, 102, 241, .07); color: #4338ca; font-weight: 700; }
.pgk-multichk__item input { accent-color: #6366f1; }
.pgk-multichk__item span { flex: 1; min-width: 0; }
.pgk-multichk__item .bi-check-lg { color: #6366f1; font-size: .72rem; }
.pgk-multichk__empty { padding: 12px; text-align: center; color: #94a3b8; font-size: .74rem; }
.pgk-multichk__foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 11px; border-top: 1px solid #eef0f7; background: #fbfbfe; font-size: .72rem; color: #64748b; }
.pgk-multichk__foot b { color: #4f46e5; }
.pgk-multichk__foot button { appearance: none; border: none; background: transparent; cursor: pointer; color: #dc2626; font: inherit; font-size: .72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
.pgk-lblinfo { color: #a5b4fc; font-size: .74rem; cursor: help; margin-left: 3px; }
/* ── Baris posisi terpilih (elegan: chip info, bukan input mati) ── */
.pgk-posrow { position: relative; display: flex; align-items: center; gap: 16px; background: #fff; border: 1px solid #e6e9f3; border-radius: 14px; padding: 13px 14px 13px 20px; overflow: hidden; transition: box-shadow .16s, border-color .16s; }
.pgk-posrow:hover { border-color: #c7cdf0; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); }
.pgk-posrow__accent { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: linear-gradient(180deg, #8b5cf6, #6366f1); }
.pgk-posrow__main { flex: 1; min-width: 0; }
.pgk-posrow__title { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.pgk-posrow__title strong { font-size: .9rem; font-weight: 800; color: #1e293b; }
.pgk-posrow__meta { display: flex; flex-wrap: wrap; gap: 5px 14px; margin-top: 6px; }
.pgk-posrow__meta span { display: inline-flex; align-items: center; gap: 5px; font-size: .72rem; color: #64748b; }
.pgk-posrow__meta i { color: #8b5cf6; }
.pgk-posrow__kuota, .pgk-posrow__status { display: flex; flex-direction: column; gap: 4px; flex: 0 0 auto; }
.pgk-posrow__kuota label, .pgk-posrow__status label { font-size: .66rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #94a3b8; }
.pgk-posrow__kuota label small { font-weight: 600; text-transform: none; letter-spacing: 0; color: #b9c0d4; }
.pgk-posrow__del { appearance: none; border: 1px solid #f4d0d0; background: #fff; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #dc2626; flex: 0 0 auto; transition: background .16s; }
.pgk-posrow__del:hover { background: #fef2f2; }
@media (max-width: 640px) { .pgk-posrow { flex-wrap: wrap; } }
/* ── Pratinjau (langkah 4) ── */
.pgk-prev { display: flex; flex-direction: column; gap: 12px; }
/* Hero ringkasan */
.pgk-prev__hero { position: relative; overflow: hidden; display: flex; background: linear-gradient(135deg, #fbfaff, #f3f2ff 60%, #eef2ff); border: 1px solid rgba(99, 102, 241, .16); border-radius: 16px; }
.pgk-prev__heroBar { width: 6px; flex: 0 0 auto; }
.pgk-prev__heroMain { flex: 1; min-width: 0; padding: 16px 18px; }
.pgk-prev__heroTop { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pgk-prev__heroTop h4 { margin: 0; font-size: 1.05rem; font-weight: 900; color: #1e1b4b; letter-spacing: -.01em; min-width: 0; overflow-wrap: anywhere; }
.pgk-prev__heroChips { display: flex; flex-wrap: wrap; gap: 7px 16px; margin-top: 10px; }
.pgk-prev__heroChips span { display: inline-flex; align-items: center; gap: 6px; font-size: .74rem; font-weight: 600; color: #64748b; min-width: 0; }
.pgk-prev__heroChips i { color: #8b5cf6; }
.pgk-prev__heroChips code { font-family: 'JetBrains Mono', monospace; font-size: .68rem; font-weight: 700; color: #4338ca; background: rgba(99, 102, 241, .09); border-radius: 6px; padding: 2px 7px; overflow-wrap: anywhere; }
/* Stat mini */
.pgk-prev__stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
@media (max-width: 640px) { .pgk-prev__stats { grid-template-columns: repeat(2, 1fr); } }
.pgk-prev__stats > div { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #e6e9f3; border-radius: 13px; padding: 11px 13px; }
.pgk-prev__sico { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: .9rem; flex: 0 0 auto; }
.pgk-prev__stats b { font-size: 1.15rem; font-weight: 900; color: #0f172a; }
.pgk-prev__stats > div > span:last-child { font-size: .7rem; font-weight: 700; color: #94a3b8; }
.pgk-prev__card { background: #fff; border: 1px solid #e6e9f3; border-radius: 14px; padding: 15px 17px; }
.pgk-prev__hd { display: flex; align-items: center; gap: 8px; font-size: .82rem; font-weight: 800; color: #1e293b; margin-bottom: 11px; flex-wrap: wrap; }
.pgk-prev__hd i { color: #6366f1; }
.pgk-prev__list { display: flex; flex-direction: column; }
.pgk-prev__item { display: flex; align-items: center; gap: 9px; padding: 8px 2px; border-bottom: 1px solid #f4f5fb; }
.pgk-prev__item:last-child { border-bottom: none; }
.pgk-prev__dot { width: 7px; height: 7px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #6366f1); flex: 0 0 auto; }
.pgk-prev__nm { font-size: .82rem; font-weight: 700; color: #334155; min-width: 0; }
.pgk-prev__kt { margin-left: auto; font-size: .72rem; font-weight: 800; color: #4f46e5; background: rgba(99, 102, 241, .08); border-radius: 8px; padding: 3px 10px; flex: 0 0 auto; }
.pgk-prev__tag { display: inline-flex; align-items: center; gap: 5px; font-size: .64rem; font-weight: 800; border-radius: 7px; padding: 3px 9px; }
.pgk-prev__tag--ind { color: #4f46e5; background: rgba(99, 102, 241, .1); }
.pgk-prev__tag--vio { color: #7c3aed; background: rgba(139, 92, 246, .12); }
.pgk-prev__tag--red { color: #dc2626; background: rgba(239, 68, 68, .1); }
.pgk-prev__syarat { border-top: 1px solid #f4f5fb; padding-top: 10px; margin-top: 10px; }
.pgk-prev__syarat:first-of-type { border-top: none; padding-top: 0; margin-top: 0; }
.pgk-prev__srow { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pgk-prev__srow > b { font-size: .82rem; color: #1e293b; }
.pgk-prev__conds { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.pgk-prev__cond { display: inline-flex; align-items: center; gap: 6px; font-size: .72rem; color: #475569; background: #f7f8fc; border: 1px solid #eef0f7; border-radius: 8px; padding: 5px 10px; }
.pgk-prev__cond i { color: #8b5cf6; }
.pgk-prev__cond b { color: #4338ca; }
.pgk-prev__none { font-size: .76rem; color: #94a3b8; }
/* Nilai rentang (ANTARA) */
.pgk-antara { display: flex; align-items: center; gap: 7px; }
.pgk-antara .el-input-number { flex: 1; width: auto; }
.pgk-antara__sep { color: #94a3b8; font-weight: 700; }
.pgk-empty { color: #94a3b8; font-size: 13px; padding: .8rem; }
.pgk-rows { display: flex; flex-direction: column; gap: .6rem; }
.pgk-row { display: flex; align-items: flex-start; gap: .6rem; background: #f8fafc; border: 1px solid rgba(11, 16, 51, .07); border-radius: 12px; padding: .7rem; }
.pgk-row__grid { flex: 1; display: grid; gap: .5rem; min-width: 0; }
/* Kolom menyesuaikan mode: saat MEMBUAT, field yang jawabannya sudah pasti
   (terisi/status) tidak dirender sama sekali, jadi gridnya ikut menyempit. */
.pgk-row__grid--batch { grid-template-columns: 2fr 1fr; }
/* Tiga kolom, bukan empat: kolom "Terisi" sudah dicabut dari borang. */
.pgk-row__grid--batch.is-edit { grid-template-columns: 1.8fr 1fr 1fr; }

/* Baris posisi disusun bertingkat: picker MPP di atas (lebar penuh, karena
   labelnya panjang), field turunan yang terkunci di bawahnya. */
.pgk-row--stack .pgk-row__grid { flex: 1 1 100%; }

/* ── Stepper wizard ─────────────────────────────────────────── */
/* Stepper: dot di atas, label di bawah — garis penghubung lewat PUSAT antar-dot. */
.pgk-steps { display: flex; margin-bottom: 1.15rem; padding-bottom: .9rem; border-bottom: 1px solid rgba(11, 16, 51, .09); }
.pgk-step { position: relative; flex: 1; display: flex; flex-direction: column; align-items: center; gap: .45rem; border: 0; background: transparent; cursor: pointer; padding: .2rem .4rem; font: inherit; text-align: center; }
.pgk-step:not(:first-child)::before { content: ''; position: absolute; top: calc(.2rem + .95rem - 1px); left: calc(-50% + 1.35rem); right: calc(50% + 1.35rem); height: 2px; border-radius: 99px; background: #e6e9f3; }
.pgk-step.done::before, .pgk-step.cur::before { background: linear-gradient(90deg, #a5b4fc, #8b5cf6); }
.pgk-step:disabled { cursor: not-allowed; opacity: .55; }
.pgk-step__dot { position: relative; z-index: 1; flex: none; width: 1.9rem; height: 1.9rem; display: grid; place-items: center; border-radius: 50%; background: #eef2f7; color: #94a3b8; font-size: .8rem; transition: all 200ms ease; box-shadow: 0 0 0 4px #fff; }
.pgk-step.done .pgk-step__dot { background: rgba(16, 185, 129, .15); color: #059669; }
.pgk-step.cur .pgk-step__dot { background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 6px 14px -6px rgba(79, 70, 229, .9); }
.pgk-step__lbl { font-size: 12.5px; font-weight: 600; color: #94a3b8; line-height: 1.25; }
.pgk-step__lbl small { font-weight: 500; color: #cbd5e1; }
.pgk-step.cur .pgk-step__lbl { color: #4338ca; }
.pgk-step.done .pgk-step__lbl { color: #059669; }

/* ── Panel per langkah ──────────────────────────────────────── */
.pgk-panel__bar { display: flex; align-items: flex-start; justify-content: space-between; gap: .6rem; margin-bottom: .8rem; }
.pgk-panel__title { font-size: 13px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: .4rem; }
.pgk-panel__note { font-size: 11.5px; color: #94a3b8; margin-top: .15rem; }
/* ── LANGKAH PHONE SCREENING DI WIZARD ───────────────────────────────────────
   Bentuknya sengaja sama dengan kartu pengikatan di rincian program: orang
   yang memasangnya saat membuat program dan orang yang menggantinya sebulan
   kemudian melihat hal yang sama, jadi tidak ada yang perlu dipelajari dua
   kali. */
.pgk-skr { border: 1px solid #eef1f7; border-radius: 12px; padding: 13px; margin-bottom: 10px; background: #fff; }
.pgk-skr__hd { display: flex; align-items: center; gap: 10px; margin-bottom: 11px; }
.pgk-skr__no {
    flex: 0 0 auto; width: 24px; height: 24px; border-radius: 7px;
    background: rgba(99, 102, 241, .12); color: #4338ca;
    font-size: 11.5px; font-weight: 700; display: grid; place-items: center;
}
.pgk-skr__hd b { display: block; font-size: 13.5px; color: #0f172a; }
.pgk-skr__hd small { display: block; font-size: 11.5px; color: #94a3b8; }

.pgk-skr__row { display: grid; grid-template-columns: minmax(120px, 210px) minmax(0, 1fr); gap: 10px; align-items: center; padding: 8px 0; }
.pgk-skr__row.is-bawaan { background: rgba(99, 102, 241, .045); border-radius: 9px; padding: 10px 11px; margin-bottom: 4px; }
.pgk-skr__lbl { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: #475569; min-width: 0; }
.pgk-skr__lbl i { color: #94a3b8; flex: 0 0 auto; }
.pgk-skr__lbl span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pgk-skr__lbl code { font-family: ui-monospace, Consolas, monospace; font-size: 10.5px; background: rgba(100,116,139,.1); padding: 1px 5px; border-radius: 4px; color: #64748b; flex: 0 0 auto; }
.pgk-skr__row.is-bawaan .pgk-skr__lbl { font-weight: 600; color: #4338ca; }
.pgk-skr__row.is-bawaan .pgk-skr__lbl i { color: #6366f1; }

.pgk-skr__toggle {
    display: flex; align-items: center; gap: 7px; width: 100%;
    border: 0; background: transparent; padding: 9px 2px 2px;
    font-family: inherit; font-size: 12px; color: #64748b; cursor: pointer; text-align: left;
}
.pgk-skr__toggle:hover { color: #4338ca; }
.pgk-skr__badge { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 5px; background: rgba(245,158,11,.15); color: #b45309; }
.pgk-skr__samar { font-size: 11px; color: #cbd5e1; }
.pgk-skr__lokers { border-top: 1px solid #f1f5f9; margin-top: 7px; padding-top: 4px; }

.pgk-skr__kosong {
    display: flex; align-items: flex-start; gap: 9px;
    padding: 12px 14px; border-radius: 11px;
    background: rgba(245, 158, 11, .07); border: 1px solid rgba(245, 158, 11, .28);
    font-size: 12.5px; color: #475569; line-height: 1.55;
}
.pgk-skr__kosong i { color: #d97706; margin-top: 2px; flex: 0 0 auto; }

@media (max-width: 860px) {
    .pgk-skr__row { grid-template-columns: 1fr; gap: 7px; }
    .pgk-skr__row.is-bawaan { padding: 11px; }
    .pgk-skr { padding: 11px; }
    .pgk-skr__toggle { padding: 12px 2px 6px; font-size: 12.5px; }
}

.pgk-panel__intro { display: flex; align-items: center; gap: .5rem; font-size: 12.5px; color: #475569; background: rgba(79, 70, 229, .06); border-radius: 10px; padding: .6rem .8rem; margin-bottom: 1rem; }

/* ── Seksi opsional dengan toggle ───────────────────────────── */
.pgk-opsi { border: 1px solid rgba(11, 16, 51, .1); border-radius: 14px; margin-bottom: .9rem; overflow: hidden; transition: border-color 180ms ease; }
.pgk-opsi.is-on { border-color: rgba(79, 70, 229, .3); }
.pgk-opsi__hd { display: flex; align-items: center; flex-wrap: wrap; gap: .3rem .7rem; padding: .8rem 1rem; cursor: pointer; margin: 0; }
.pgk-opsi.is-on .pgk-opsi__hd { background: rgba(79, 70, 229, .04); border-bottom: 1px solid rgba(79, 70, 229, .12); }
.pgk-opsi__t { font-size: 13.5px; font-weight: 700; color: #0f172a; display: inline-flex; align-items: center; gap: .4rem; }
.pgk-opsi__hd small { flex: 1 1 100%; font-size: 11.5px; color: #94a3b8; padding-left: 3rem; }
.pgk-opsi__body { padding: .9rem 1rem 1rem; }
.pgk-opsi__toolbar { display: flex; align-items: center; gap: .5rem; margin-bottom: .6rem; }
.pgk-req { font-weight: 600; font-size: 11px; color: #b45309; background: #fef3c7; border-radius: 999px; padding: .1rem .45rem; margin-left: .35rem; }
/* Lingkup daftar MPP yang sedang tampil — MT vs non-MT. Warnanya mengikuti
   lencana kategori di kartu program (MT emas, rekrutmen biru) supaya keduanya
   terbaca sebagai hal yang sama, bukan dua penanda yang kebetulan berdekatan. */
.pgk-mppscope { display: inline-flex; align-items: center; gap: .3rem; font-weight: 700; font-size: 11px; color: #0369a1; background: #e0f2fe; border-radius: 999px; padding: .1rem .5rem; margin-left: .35rem; }
.pgk-mppscope.is-mt { color: #b45309; background: #fef3c7; }
.pgk-mppscope .bi { font-size: 10px; }
/* Lawan dari .pgk-req — nadanya sengaja netral, bukan kuning peringatan.
   "Opsional" adalah izin, bukan tuntutan; mewarnainya seperti label wajib
   membuat mata membacanya sebagai satu hal lagi yang harus dikerjakan. */
.pgk-opsional { font-weight: 600; font-size: 11px; color: #475569; background: #f1f5f9; border-radius: 999px; padding: .1rem .45rem; margin-left: .35rem; }
.pgk-selempty { padding: .6rem .8rem; font-size: 12px; color: #94a3b8; }
.pgk-opt-kode { color: #94a3b8; font-weight: 500; }
.pgk-opt-mpp { float: right; font-size: 11px; font-weight: 700; color: #4338ca; }
/* Nol MPP ditandai, bukan disembunyikan — ia mungkin memang orang yang tepat,
   dan MPP-nya yang belum dibuat. */
.pgk-opt-mpp.is-nol { color: #b91c1c; }
/* Selebar borang, tombolnya di ujung kanan — sejajar teksnya, bukan menumpuk
   di bawahnya, supaya tingginya tetap satu blok pendek. */
.pgk-picwarn { display: flex; align-items: center; gap: .7rem; padding: .7rem .85rem; border-radius: .7rem; border: 1px solid #fecaca; background: #fef2f2; }
.pgk-picwarn > i { flex: none; color: #dc2626; font-size: 1rem; }
.pgk-picwarn__teks { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .15rem; }
.pgk-picwarn b { font-size: 12.5px; color: #991b1b; }
.pgk-picwarn span { font-size: 11.5px; line-height: 1.6; color: #7f1d1d; }
.pgk-picwarn__btn { flex: none; display: inline-flex; align-items: center; gap: .35rem; border: 1px solid #fca5a5; background: #fff; color: #b91c1c; font: inherit; font-size: 11.5px; font-weight: 700; border-radius: .55rem; padding: .4rem .7rem; cursor: pointer; white-space: nowrap; }
.pgk-picwarn__btn:hover { background: #fee2e2; }
@media (max-width: 620px) {
    /* Di layar sempit tombol turun — memaksanya tetap sebaris akan memampatkan
       kalimatnya jadi satu kata per baris. */
    .pgk-picwarn { flex-wrap: wrap; }
    .pgk-picwarn__btn { margin-left: 1.7rem; }
}

/* Keterangan saat PIC-nya SUDAH punya MPP. Nadanya hijau tenang, bukan biru
   informasi: ini penegasan bahwa pilihannya benar, dan bentuknya sengaja sama
   dengan peringatan di atas supaya keduanya menempati ruang yang sama — tidak
   ada pergeseran tata letak saat orang berganti pilihan. */
.pgk-picok { display: flex; align-items: center; gap: .7rem; padding: .7rem .85rem; border-radius: .7rem; border: 1px solid #a7f3d0; background: #ecfdf5; }
.pgk-picok > i { flex: none; color: #059669; font-size: 1rem; }
.pgk-picok span { font-size: 11.5px; line-height: 1.6; color: #065f46; }
.pgk-picok b { color: #047857; }
/* Tombol bernama: lebarnya mengikuti teks, tidak lagi kotak 38px. */
.pgk-serah-form { display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem; }
.pgk-serah-field { min-width: 0; }
.pgk-serah-hint { display: flex; align-items: flex-start; gap: .4rem; margin: .45rem 0 0; font-size: 11.5px; line-height: 1.55; color: #94a3b8; }
.pgk-serah-hint > i { flex-shrink: 0; margin-top: 1px; color: #6366f1; }
.pgk-serah-bar { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; margin-bottom: .6rem; }
.pgk-chk { display: inline-flex; align-items: center; gap: .4rem; font-size: 12.5px; font-weight: 700; color: #334155; cursor: pointer; }
.pgk-serah-list { display: flex; flex-direction: column; gap: .4rem; max-height: 20rem; overflow-y: auto; }
.pgk-serah-item { display: flex; align-items: center; gap: .7rem; padding: .6rem .75rem; border: 1px solid #e2e8f0; border-radius: .7rem; background: #fff; cursor: pointer; transition: all .15s ease; }
.pgk-serah-item:hover { border-color: #c7d2fe; }
.pgk-serah-item.on { border-color: #6366f1; background: #eef2ff; }
.pgk-serah-item__body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.pgk-serah-item__body strong { font-size: 13px; color: #1e293b; }
.pgk-serah-item__body small { font-size: 11.5px; color: #64748b; }
.pgk-serah-item__pic { flex: none; display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .5rem; border-radius: .5rem; background: #fff7ed; color: #9a3412; font-size: 11px; font-weight: 700; }
.pgk-hint { font-size: 11.5px; color: #64748b; margin-top: .3rem; }

/* Kategori yang sudah pasti — keterangan, bukan kendali.
   Sengaja TIDAK menyerupai kotak input: percobaan pertama memakai bingkai
   putus-putus abu-abu, dan hasilnya terbaca seperti input yang mati dengan
   isian kosong. Yang perlu terbaca justru NAMA kategorinya, jadi pilnya dibuat
   sama persis dengan pil kategori di daftar program — warna dan ikonnya pun
   mengikuti kategori, supaya sekali lihat sudah jelas ini MT atau Rekrutmen. */
.pgk-kat-tetap {
    display: flex; align-items: center; gap: .6rem; flex-wrap: wrap;
    padding: .35rem 0 .1rem;
}
.pgk-kat-tetap small {
    display: inline-flex; align-items: center; gap: .3rem;
    font-size: 11.5px; color: #94a3b8;
}
.pgk-kat-tetap small i { font-size: 10px; }
/* Pil daftar berukuran 11px — kekecilan untuk sesuatu yang di sini justru jadi
   satu-satunya keterangan kategori. Dibesarkan sedikit, warna tetap sama. */
.pgk-kat-tetap .pkg-pill { font-size: 12.5px; padding: 6px 12px; }
.pgk-syarat { border: 1px solid rgba(79,70,229,.18); border-radius: 14px; padding: .9rem; margin-bottom: .7rem; background: #fff; }
/* Identitas syarat: nama + tahap berlabel, tombol hapus di kanan. */
.pgk-syarat__id { display: grid; grid-template-columns: 1fr 1fr auto; gap: .6rem; align-items: end; margin-bottom: .7rem; }
.pgk-syarat__del { margin-bottom: .1rem; }
.pgk-pesan { margin-top: .7rem; }
.pgk-pesan .wca-field-lbl { display: flex; align-items: center; gap: .3rem; color: #4338ca; }
.pgk-isipesan { margin-top: .35rem; border: 0; background: transparent; color: #7c3aed; font: inherit; font-size: 11.5px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: .3rem; padding: 0; }
.pgk-isipesan:hover { text-decoration: underline; }

.pgk-aturan { border: 1px solid rgba(79,70,229,.14); border-radius: 12px; padding: .7rem; background: rgba(248,250,252,.7); }
.pgk-aturan__bar { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem; font-size: 12.5px; color: #475569; margin-bottom: .6rem; }

/* Kondisi: grid berlabel yang lega — field cukup lebar sehingga tidak
   terpotong jadi "U". Turun 1 kolom di layar sempit. */
.pgk-kondisi { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(0, 1.2fr) minmax(0, 1fr) auto; gap: .5rem; align-items: end; margin-bottom: .5rem; }
.pgk-kondisi__f, .pgk-kondisi__o, .pgk-kondisi__v { min-width: 0; }
.pgk-kondisi__del { margin-bottom: .1rem; }
.pgk-kondisi-kosong { font-size: 11.5px; color: #94a3b8; padding: .3rem 0; }
.pgk-addkondisi { margin-top: .2rem; width: 100%; border: 1px dashed rgba(79,70,229,.3); border-radius: 9px; background: transparent; color: #4338ca; font: inherit; font-size: 12px; font-weight: 600; padding: .45rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; }
.pgk-addkondisi:hover { background: rgba(79,70,229,.06); }

/* Aksi + mode uji, berdampingan rapi. */
.pgk-syarat__opt { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; align-items: center; margin-top: .7rem; }
.pgk-mpp { font-size: 11.5px; color: #4338ca; background: rgba(79, 70, 229, .08); border-radius: 6px; padding: .1rem .35rem; }

/* Meter kursi: hijau selama masih di dalam pagu MPP, merah begitu terlampaui. */
.pgk-meter { display: inline-flex; align-items: center; gap: .3rem; font-size: 11.5px; font-weight: 600; letter-spacing: 0; text-transform: none; color: #047857; background: rgba(16, 185, 129, .1); border: 1px solid rgba(16, 185, 129, .25); border-radius: 999px; padding: .2rem .55rem; }
.pgk-meter.is-over { color: #b91c1c; background: rgba(239, 68, 68, .1); border-color: rgba(239, 68, 68, .3); }
.pgk-alert { display: flex; align-items: center; gap: .5rem; font-size: 12.5px; color: #b91c1c; background: rgba(239, 68, 68, .08); border: 1px solid rgba(239, 68, 68, .25); border-radius: 10px; padding: .6rem .75rem; }
.pgk-row .wca-iconbtn { margin-top: 1.6rem; }
@media (max-width: 900px) {
    .pgk-row__grid--batch,
    .pgk-row__grid--posisi,
    .pgk-kondisi { grid-template-columns: 1fr 1fr; }
    .pgk-kondisi__f { grid-column: 1 / -1; }
    .pgk-kondisi__del { grid-column: 2; justify-self: end; margin-bottom: 0; }
}
@media (max-width: 560px) {
    .pgk-row__grid--batch,
    .pgk-row__grid--posisi,
    .pgk-row .wca-iconbtn { margin-top: 0; }
    .pgk-steps { gap: .2rem; }
    .pgk-step__lbl { display: none; }
    .pgk-syarat__id { grid-template-columns: 1fr auto; }
    .pgk-syarat__opt { grid-template-columns: 1fr; }
}

/* ── Guard Info Divisi ── */
.gid-overlay { position: fixed; inset: 0; z-index: 3000; background: rgba(15, 23, 42, .5); display: grid; place-items: center; padding: 1rem; }
.gid-modal { width: 100%; max-width: 520px; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, .3); }
.gid-modal__head { display: flex; gap: .8rem; align-items: flex-start; padding: 1.1rem 1.2rem .9rem; border-bottom: 1px solid rgba(15, 23, 42, .08); }
.gid-modal__ico { flex: none; width: 2.4rem; height: 2.4rem; border-radius: 11px; display: grid; place-items: center; background: rgba(245, 158, 11, .14); color: #d97706; font-size: 18px; }
.gid-modal__head h3 { margin: 0; font-size: 15.5px; font-weight: 800; color: #1e293b; }
.gid-modal__head p { margin: .2rem 0 0; font-size: 12px; line-height: 1.55; color: #64748b; }
.gid-modal__x { flex: none; border: none; background: transparent; color: #94a3b8; font-size: 15px; cursor: pointer; padding: 2px; }
.gid-modal__x:hover { color: #64748b; }
.gid-list { padding: .8rem 1.2rem; display: flex; flex-direction: column; gap: .5rem; max-height: 44vh; overflow-y: auto; }
.gid-item { display: flex; align-items: center; justify-content: space-between; gap: .7rem; padding: .6rem .8rem; border: 1px solid rgba(15, 23, 42, .1); border-radius: 11px; background: #f8fafc; }
.gid-item__l { display: inline-flex; align-items: center; gap: .45rem; font-size: 13px; color: #334155; min-width: 0; }
.gid-item__l > i { color: #94a3b8; flex: none; }
.gid-item__badge { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; color: #b91c1c; background: rgba(239, 68, 68, .12); border-radius: 999px; padding: 2px 8px; white-space: nowrap; }
.gid-item__btn { flex: none; display: inline-flex; align-items: center; gap: .35rem; border: none; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; font-size: 11.5px; font-weight: 700; border-radius: 9px; padding: 7px 12px; cursor: pointer; box-shadow: 0 6px 16px rgba(79, 70, 229, .25); }
.gid-item__btn:hover { transform: translateY(-1px); }
.gid-modal__foot { display: flex; align-items: center; justify-content: space-between; gap: .6rem; padding: .9rem 1.2rem; border-top: 1px solid rgba(15, 23, 42, .08); background: #fbfcfe; }
</style>

<!-- Konten el-popover & el-option di-teleport ke <body>, jadi butuh style TIDAK ber-scope. -->
<style>
.pgk-mppdetail { display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; color: #475569; }
.pgk-mppdetail strong { font-size: 13.5px; color: #1e293b; margin-bottom: 2px; }
.pgk-mppdetail div { display: flex; align-items: center; gap: 8px; }
.pgk-mppdetail i { color: #8b5cf6; width: 14px; text-align: center; }
.pgk-opdesc { display: flex; flex-direction: column; line-height: 1.3; padding: 3px 0; }
.pgk-opdesc span { font-size: 13px; font-weight: 600; }
.pgk-opdesc small { font-size: 11px; color: #94a3b8; }

/* ── PITA SERAH TERIMA ───────────────────────────────────────────────────────
   Bentuk pita, bukan kotak peringatan: yang disampaikan bukan galat maupun
   larangan, melainkan KEADAAN — siapa yang memegang program ini sekarang.
   Kotak merah untuk hal semacam ini membuat orang mengira ada yang rusak. */
.prg-pita {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px; margin-bottom: 14px;
    border-radius: 12px;
    border: 1px solid transparent;
    font-size: 12.5px; line-height: 1.5;
}
.prg-pita__ico {
    flex: 0 0 auto; width: 32px; height: 32px; border-radius: 10px;
    display: grid; place-items: center; font-size: 14px;
}
.prg-pita__isi { flex: 1; min-width: 0; }
.prg-pita__isi b { display: block; font-size: 13px; }
.prg-pita__isi span { color: #64748b; }
.prg-pita__i {
    flex: 0 0 auto; width: 28px; height: 28px; border-radius: 9px;
    border: 0; background: transparent; cursor: help; font-size: 14px;
    transition: background .14s;
}
.prg-pita__i:hover { background: rgba(15, 23, 42, .06); }

/* Sudah diserahkan SENDIRI — amber, warna "ada yang berubah karena Anda". */
.prg-pita.is-serah {
    border-color: rgba(245, 158, 11, .32);
    background: linear-gradient(100deg, rgba(245, 158, 11, .12), rgba(245, 158, 11, .05));
}
.prg-pita.is-serah .prg-pita__ico { background: rgba(245, 158, 11, .18); color: #b45309; }
.prg-pita.is-serah .prg-pita__isi b { color: #92400e; }
.prg-pita.is-serah .prg-pita__i { color: #b45309; }

/* Memang bukan milik akun ini — netral, sekadar keterangan. */
.prg-pita.is-lain {
    border-color: rgba(99, 102, 241, .24);
    background: linear-gradient(100deg, rgba(99, 102, 241, .09), rgba(99, 102, 241, .04));
}
.prg-pita.is-lain .prg-pita__ico { background: rgba(99, 102, 241, .16); color: #4338ca; }
.prg-pita.is-lain .prg-pita__isi b { color: #312e81; }
.prg-pita.is-lain .prg-pita__i { color: #4338ca; }

/* Isi tooltip — alasan yang ditulis saat serah terima, apa adanya. */
.prg-pita__tip { font-size: 12px; line-height: 1.6; max-width: 320px; }
.prg-pita__tip > b { display: block; margin-bottom: 4px; }
.prg-pita__tip p { margin: 0 0 8px; font-style: italic; opacity: .92; }
.prg-pita__tip dl {
    display: grid; grid-template-columns: auto 1fr; gap: 2px 10px;
    margin: 0 0 8px; padding: 8px 0; border-block: 1px solid rgba(255, 255, 255, .16);
}
.prg-pita__tip dt { opacity: .7; }
.prg-pita__tip dd { margin: 0; font-weight: 600; }
.prg-pita__tipnote { margin: 0 !important; font-style: normal !important; opacity: .82; }

@media (max-width: 640px) {
    .prg-pita { align-items: flex-start; }
    .prg-pita__isi b { font-size: 12.5px; }
}

/* ── PHONE SCREENING ─────────────────────────────────────────────────────── */
.prg-skr__akt {
    border: 1px solid #eef1f7;
    border-radius: 12px;
    padding: 13px;
    margin-bottom: 10px;
    background: #fff;
}
.prg-skr__aktHead { display: flex; align-items: center; gap: 10px; margin-bottom: 11px; }
.prg-skr__no {
    flex: 0 0 auto;
    width: 24px;
    height: 24px;
    border-radius: 7px;
    background: rgba(99, 102, 241, 0.12);
    color: #4338ca;
    font-size: 11.5px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}
.prg-skr__aktHead b { display: block; font-size: 13.5px; color: #0f172a; }
.prg-skr__aktHead small { display: block; font-size: 11.5px; color: #94a3b8; }

.prg-skr__baris {
    display: grid;
    grid-template-columns: minmax(120px, 210px) minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    padding: 8px 0;
}
.prg-skr__baris.is-bawaan {
    background: rgba(99, 102, 241, 0.045);
    border-radius: 9px;
    padding: 10px 11px;
    margin-bottom: 4px;
}
.prg-skr__lbl {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12.5px;
    color: #475569;
    min-width: 0;
}
.prg-skr__lbl i { color: #94a3b8; flex: 0 0 auto; }
.prg-skr__lbl span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.prg-skr__lbl code {
    font-family: ui-monospace, Consolas, monospace;
    font-size: 10.5px;
    background: rgba(100, 116, 139, 0.1);
    padding: 1px 5px;
    border-radius: 4px;
    color: #64748b;
    flex: 0 0 auto;
}
.prg-skr__baris.is-bawaan .prg-skr__lbl { font-weight: 600; color: #4338ca; }
.prg-skr__baris.is-bawaan .prg-skr__lbl i { color: #6366f1; }

.prg-skr__toggle {
    display: flex;
    align-items: center;
    gap: 7px;
    width: 100%;
    border: 0;
    background: transparent;
    padding: 9px 2px 2px;
    font-family: inherit;
    font-size: 12px;
    color: #64748b;
    cursor: pointer;
    text-align: left;
}
.prg-skr__toggle:hover { color: #4338ca; }
.prg-skr__badge {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 5px;
    background: rgba(245, 158, 11, 0.15);
    color: #b45309;
}
.prg-skr__samar { font-size: 11px; color: #cbd5e1; }
.prg-skr__lokers { border-top: 1px solid #f1f5f9; margin-top: 7px; padding-top: 4px; }
.prg-skr__kosong { font-size: 12px; color: #94a3b8; padding: 10px 2px; }

.prg-skr__nota {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin: 4px 0 0;
    padding: 10px 12px;
    border-radius: 9px;
    background: rgba(14, 165, 233, 0.06);
    border: 1px solid rgba(14, 165, 233, 0.16);
    font-size: 12px;
    color: #475569;
    line-height: 1.55;
}
.prg-skr__nota i { color: #0ea5e9; margin-top: 2px; flex: 0 0 auto; }

@media (max-width: 860px) {
    .prg-skr__baris { grid-template-columns: 1fr; gap: 7px; }
    .prg-skr__baris.is-bawaan { padding: 11px; }
    .prg-skr__baris .skr-btn { justify-self: stretch; }
    .prg-skr__akt { padding: 11px; }
    /* Sasaran sentuh: pelipat penimpaan sering ditekan di ponsel. */
    .prg-skr__toggle { padding: 12px 2px 6px; font-size: 12.5px; }
}
@media (max-width: 520px) {
    .prg-skr__lbl span { white-space: normal; }
    .prg-skr__nota { padding: 11px; font-size: 11.5px; }
}

/* ── DAMPAK PENGGANTIAN ALUR ───────────────────────────────────────────────
   Angka besar dulu, daftar orangnya belakangan. Yang ditanyakan pertama kali
   selalu "berapa", dan baru sesudah itu "siapa saja". */
.pgk-dmp { display: flex; flex-direction: column; gap: 14px; }
.pgk-dmp__kpi { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.pgk-dmp__kartu { border-radius: 14px; padding: 14px 16px; border: 1px solid #eef0f7; background: #fbfcff; display: flex; flex-direction: column; gap: 2px; }
.pgk-dmp__kartu.is-ikut { border-color: #c7d2fe; background: linear-gradient(180deg, #f5f3ff, #fff); }
.pgk-dmp__kartu.is-skip { border-color: #fde68a; background: linear-gradient(180deg, #fffbeb, #fff); }
.pgk-dmp__n { font-size: 26px; font-weight: 900; letter-spacing: -0.02em; color: #1e293b; line-height: 1.1; }
.pgk-dmp__kartu.is-ikut .pgk-dmp__n { color: #4f46e5; }
.pgk-dmp__kartu.is-skip .pgk-dmp__n { color: #b45309; }
.pgk-dmp__lbl { font-size: 12px; font-weight: 800; color: #334155; }
.pgk-dmp__kartu small { font-size: 11px; color: #8792a6; line-height: 1.35; }

.pgk-dmp__bar { height: 8px; border-radius: 99px; background: #fde68a; overflow: hidden; }
.pgk-dmp__bar-ikut { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); transition: width 0.4s; }

.pgk-dmp__list { border: 1px solid #fde68a; background: #fffbeb; border-radius: 14px; padding: 10px 12px; }
.pgk-dmp__list.is-ikut { border-color: #e0e7ff; background: #f8f7ff; }
.pgk-dmp__ttl { display: flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 900; letter-spacing: 0.04em; text-transform: uppercase; color: #92400e; margin-bottom: 4px; }
.pgk-dmp__list.is-ikut .pgk-dmp__ttl { color: #4f46e5; }
.pgk-dmp__baris { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 7px 0; border-top: 1px solid rgba(0, 0, 0, 0.04); }
.pgk-dmp__baris:first-of-type { border-top: none; }
.pgk-dmp__orang { display: flex; flex-direction: column; min-width: 0; }
.pgk-dmp__orang b { font-size: 13px; color: #1e293b; }
.pgk-dmp__orang small { font-size: 11px; color: #8792a6; }
/* Sebabnya boleh membungkus; namanya tidak. Yang perlu terbaca utuh saat
   sempit adalah SIAPA, bukan kalimat penjelasnya. */
.pgk-dmp__sebab { flex: 1 1 40%; text-align: right; font-size: 11.5px; color: #92400e; }
.pgk-dmp__riwayat { color: #b45309 !important; }
.pgk-dmp__ok { flex: 0 0 auto; font-size: 11.5px; font-weight: 800; color: #4f46e5; white-space: nowrap; }

@media (max-width: 640px) {
    .pgk-dmp__kpi { grid-template-columns: 1fr; }
    .pgk-dmp__baris { flex-direction: column; gap: 2px; }
    .pgk-dmp__sebab { text-align: left; }
}
</style>
