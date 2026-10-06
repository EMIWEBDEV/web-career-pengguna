<!-- WEB CAREER — Admin: Pembukaan Program (publikasi program ke landing + window).
     Tata letak mengikuti rancangan `docs/refrences/v1/v2/EVO Career Login Redesign/
     EVO Pembukaan Program.dc.html`. DATA dari DB via /api/v1/pembukaan.

     TIGA PENYIMPANGAN SENGAJA DARI RANCANGAN — semuanya karena rancangan itu
     digambar di atas data karangan, dan data nyata di sini berbeda bentuknya:

       1. Kartu "Dilihat" dan "Konversi" TIDAK ADA. Tidak ada satu pun tabel
          yang mencatat kunjungan halaman lowongan, jadi keduanya hanya bisa
          diisi angka karangan. Diganti kartu yang benar-benar terukur, dan
          ketiadaannya dikatakan terus terang di layar.
       2. "Pelamar per Loker" SELALU heatmap, tidak pernah multi-garis.
          Rancangan berpindah ke heatmap hanya di atas 10 loker; dua bentuk
          untuk satu pertanyaan berarti rekruter harus belajar membaca dua
          grafik, dan yang multi-garis sudah kusut sejak loker keempat.
       3. Tombol "Ubah" per loker DIHAPUS. Yang dibutuhkan rekruter di layar ini
          cuma satu keputusan — tampil atau tidak — dan itu sudah dijawab
          sakelar yang menempel di kartu/barisnya. Ubah isi loker tempatnya di
          Program Kegiatan, bukan di sini.
-->
<template>
    <Head title="Pembukaan Program" />
    <div class="pbk">
        <!-- ══ KEPALA HALAMAN ═══════════════════════════════════════════════ -->
        <div class="pbk-top">
            <div class="pbk-top__l">
                <nav class="pbk-crumb"><span>Rekrutmen</span><i class="bi bi-chevron-right"></i><span class="on">Pembukaan Program</span></nav>
                <h1 class="pbk-title">
                    <span class="pbk-title__ico"><i class="bi bi-megaphone-fill"></i></span>
                    Pembukaan Program
                </h1>
                <p class="pbk-lead">Kelola publikasi program ke landing publik beserta window pendaftaran — lengkap dengan analitik performa tiap terbitan.</p>
            </div>
            <button class="pbk-cta" @click="openCreate"><i class="bi bi-plus-lg"></i> Buka Program</button>
        </div>

        <div class="pbk-body">
            <!-- ══ KOLOM KIRI ═══════════════════════════════════════════════
                 Daftar + kartu filter dibungkus satu wadah, bukan ditumpuk di
                 dalam <aside>. Alasannya bukan selera: .pbk-aside memakai
                 backdrop-filter, dan elemen ber-backdrop-filter menjadi
                 containing block bagi keturunan position:fixed. Lembar filter
                 di layar sempit karena itu akan terkurung di dalam kotak panel
                 alih-alih menempel ke tepi layar. -->
            <aside class="pbk-aside" :class="{ 'is-hide': selMobile }">
                <div class="pbk-aside__head">
                    <div class="pbk-fold">
                        <span class="pbk-fold__ico"><i class="bi bi-folder-fill"></i></span>
                        <div class="pbk-fold__id">
                            <div class="pbk-fold__t">Terbitan</div>
                            <div class="pbk-fold__s">{{ list.length }} publikasi · {{ jmlTerbit }} ditampilkan</div>
                        </div>
                        <button class="pbk-sort" :title="urutTurun ? 'Terbaru di atas' : 'Terlama di atas'" @click="urutTurun = !urutTurun">
                            <i class="bi" :class="urutTurun ? 'bi-sort-down' : 'bi-sort-up'"></i>
                            {{ urutTurun ? 'Terbaru' : 'Terlama' }}
                        </button>
                    </div>

                    <div class="pbk-search">
                        <i class="bi bi-search"></i>
                        <input v-model="filters.q" placeholder="Cari program atau kode…" @input="cariDebounce" />
                    </div>

                    <div class="pbk-scopes">
                        <button v-for="sc in lingkup" :key="sc.kode" class="pbk-scope" :class="{ on: cakupan === sc.kode }" @click="cakupan = sc.kode">
                            {{ sc.label }} <span>{{ sc.jml }}</span>
                        </button>
                    </div>
                </div>
                <!-- FILTER — bagian dari kartu yang SAMA, bukan kartu kedua.
                     Dua kartu bertumpuk di kolom kiri membuat daftar dan
                     saringannya terbaca seperti dua hal yang tidak berhubungan,
                     padahal yang satu menyaring yang lain.

                     Teleport hanya AKTIF di layar sempit: di sana blok ini pindah
                     ke <body> agar lembar bawahnya menempel ke tepi layar. Tanpa
                     itu ia terkurung di dalam kartu — .pbk-aside memakai
                     backdrop-filter, dan elemen ber-backdrop-filter menjadi
                     containing block bagi keturunan position:fixed. -->
                <Teleport to="body" :disabled="!sempit">
                    <div class="pbk-filter" :class="{ 'is-open': sheetOpen }">
                        <div class="pbk-filter__head">
                            <span class="pbk-filter__title"><i class="bi bi-funnel"></i> Filter</span>
                            <div class="pbk-filter__act">
                                <button v-if="adaFilter" class="pbk-filter__reset" type="button" @click="resetFilter">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                                <button class="pbk-filter__close" type="button" aria-label="Tutup" @click="sheetOpen = false">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="pbk-filter__grid">
                            <div>
                                <label class="wca-field-lbl">Kategori Program</label>
                                <el-select v-model="filters.kategori" placeholder="Semua kategori" clearable style="width:100%" @change="load">
                                    <el-option
                                        v-for="k in kategoriOpsi" :key="k.kode"
                                        :label="`${k.nama} (${k.jumlah})`" :value="k.kode"
                                    />
                                </el-select>
                            </div>
                            <div>
                                <label class="wca-field-lbl">Masa Berlaku</label>
                                <el-select v-model="filters.masa" placeholder="Semua masa berlaku" clearable style="width:100%">
                                    <el-option label="Berbatas — ada tanggal tutup" value="BERBATAS" />
                                    <el-option label="Selalu terbuka — tanpa batas" value="EVERGREEN" />
                                </el-select>
                            </div>
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
                            <div class="pbk-filter__count">
                                <strong>{{ tersaring.length }}</strong> terbitan cocok
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
                <div v-if="loading" class="pbk-list pbk-skel">
                    <div v-for="n in 5" :key="n" class="pbk-skel__row" :style="{ animationDelay: (n * 0.07) + 's' }">
                        <span class="pbk-skel__ico"></span>
                        <span class="pbk-skel__body">
                            <span class="pbk-skel__bar" style="width: 72%"></span>
                            <span class="pbk-skel__bar" style="width: 45%"></span>
                            <span class="pbk-skel__bar" style="width: 58%"></span>
                        </span>
                        <span class="pbk-skel__num"></span>
                    </div>
                </div>

                <div v-else class="pbk-list">
                    <div v-for="g in grup" :key="g.kode" class="pbk-group">
                        <div class="pbk-group__head">
                            <i class="bi bi-caret-down-fill"></i>
                            <span class="pbk-group__lbl">{{ g.label }}</span>
                            <span class="pbk-group__n">{{ g.items.length }}</span>
                            <span class="pbk-group__line"></span>
                        </div>
                        <button
                            v-for="it in g.items" :key="it.id"
                            class="pbk-item" :class="{ on: sel === it.id }"
                            @click="pilih(it.id)"
                        >
                            <span class="pbk-item__accent"></span>
                            <span class="pbk-item__ico" :class="`is-${keadaan(it).nada}`"><i class="bi" :class="keadaan(it).ikon"></i></span>
                            <span class="pbk-item__body">
                                <span class="pbk-item__r1">
                                    <!-- Nama program bisa sangat panjang. Dipotong dengan
                                         elipsis DAN diberi title: yang terpotong masih bisa
                                         dibaca utuh tanpa membuka apa pun. -->
                                    <span class="pbk-item__name" :title="it.programNama">{{ it.programNama || '—' }}</span>
                                    <span class="pbk-item__dot" :style="{ background: keadaan(it).titik }"></span>
                                </span>
                                <span class="pbk-item__r2">
                                    <span class="pbk-item__meta" :title="katLabel(it.kategori)">{{ katLabel(it.kategori) }}</span>
                                </span>
                                <span class="pbk-item__r3">
                                    <span class="pbk-item__pill" :class="`is-${keadaan(it).nada}`">{{ keadaan(it).pendek }}</span>
                                    <span class="pbk-item__range">{{ windowRingkas(it) }}</span>
                                </span>
                            </span>
                            <span class="pbk-item__num">
                                <span class="pbk-item__val">{{ it.jmlPelamar ?? '—' }}</span>
                                <span class="pbk-item__cap">PELAMAR</span>
                                <span class="pbk-item__lok">{{ (it.posisi || []).length }} loker</span>
                            </span>
                        </button>
                    </div>

                    <div v-if="!tersaring.length" class="pbk-none">
                        <span class="pbk-none__ico"><i class="bi bi-folder-x"></i></span>
                        <div class="pbk-none__t">Tidak ada terbitan</div>
                        <div class="pbk-none__s">Ubah kata kunci atau filter statusmu.</div>
                    </div>
                </div>

                <!-- Paginasi, bukan meteran "berapa yang tampil". Meteran itu
                     mengulang angka yang sudah tercetak di kepala panel, sementara
                     daftar puluhan terbitan justru butuh cara berpindah halaman —
                     menggulung 40 baris untuk mencari satu terbitan bukan cara
                     orang bekerja. -->
                <div v-if="totalHalaman > 1" class="pbk-aside__foot pbk-pager">
                    <button class="pbk-pager__nav" :disabled="halaman <= 1" aria-label="Halaman sebelumnya" @click="keHalaman(halaman - 1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="pbk-pager__nums">
                        <button
                            v-for="(h, i) in halamanTampil" :key="`${h}-${i}`"
                            class="pbk-pager__n" :class="{ on: h === halaman, 'is-gap': h === '…' }"
                            :disabled="h === '…'"
                            @click="h !== '…' && keHalaman(h)"
                        >{{ h }}</button>
                    </div>
                    <button class="pbk-pager__nav" :disabled="halaman >= totalHalaman" aria-label="Halaman berikutnya" @click="keHalaman(halaman + 1)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <span class="pbk-pager__info">{{ rentangBaris }}</span>
                </div>
            </aside>

            <!-- ══ FILTER ═══════════════════════════════════════════════════
                 Di layar lebar ia kartu biasa di bawah daftar — terlihat terus,
                 tanpa perlu ditemukan lebih dulu. Di layar sempit elemen YANG
                 SAMA berubah jadi lembar bawah yang dibuka tombol mengambang;
                 satu markup, dua perilaku, jadi tidak ada versi kedua yang
                 kelak ketinggalan bidang. -->

            <!-- ══ PANEL KANAN ══════════════════════════════════════════════ -->
            <main v-if="terpilih" class="pbk-main">
                <section class="pbk-hero">
                    <span class="pbk-hero__glow" :style="{ background: `radial-gradient(circle, ${keadaan(terpilih).cahaya}, transparent 70%)` }"></span>
                    <div class="pbk-hero__row">
                        <div class="pbk-hero__l">
                            <nav class="pbk-hero__crumb">
                                <button class="pbk-back" @click="sel = null"><i class="bi bi-chevron-left"></i></button>
                                <i class="bi bi-folder2-open"></i><span>Terbitan</span>
                                <i class="bi bi-chevron-right"></i><span>{{ katLabel(terpilih.kategori) }}</span>
                                <i class="bi bi-chevron-right"></i><span class="on">{{ terpilih.kode }}</span>
                            </nav>
                            <h2 class="pbk-hero__title" :title="terpilih.programNama">{{ terpilih.programNama || '—' }}</h2>
                            <div class="pbk-chips">
                                <span class="pbk-chip" :class="`is-${keadaan(terpilih).nada}`"><span class="pbk-chip__dot"></span> {{ keadaan(terpilih).judul }}</span>
                                <span v-if="terpilih.alurNama" class="pbk-chip is-plain" :title="terpilih.alurNama"><i class="bi bi-diagram-3"></i> {{ terpilih.alurNama }}</span>
                                <span class="pbk-chip is-plain"><i class="bi bi-briefcase"></i> {{ (terpilih.posisi || []).length }} loker</span>
                            </div>
                        </div>
                        <div class="pbk-hero__act">
                            <!-- Label sengaja menyebut TUJUANNYA, bukan sekadar
                                 "Tampilkan": yang dinyalakan sakelar ini adalah
                                 kemunculannya di landing page, dan itu perlu
                                 terbaca tanpa harus sudah tahu. -->
                            <button class="pbk-pub" :class="{ on: terpilih.statusPublish === 'TERBIT' }" @click="setPublish(terpilih, terpilih.statusPublish !== 'TERBIT')">
                                <span class="pbk-pub__sw"><span class="pbk-pub__knob"></span></span>
                                {{ terpilih.statusPublish === 'TERBIT' ? 'Tampilkan landing page' : 'Tidak ditampilkan' }}
                            </button>
                            <button class="pbk-ib" title="Ubah" @click="openEdit(terpilih)"><i class="bi bi-pencil"></i></button>
                            <button class="pbk-ib is-danger" title="Hapus" @click="askRemove(terpilih)"><i class="bi bi-trash3"></i></button>
                        </div>
                    </div>

                    <div class="pbk-win" :class="`is-${keadaan(terpilih).nada}`">
                        <span class="pbk-win__ico"><i class="bi" :class="keadaan(terpilih).ikon"></i></span>
                        <div class="pbk-win__l">
                            <div class="pbk-win__t">{{ keadaan(terpilih).judul }}</div>
                            <div class="pbk-win__s">{{ keadaan(terpilih).sub }}</div>
                            <div class="pbk-win__bar"><span :style="{ width: majuWindow(terpilih) + '%' }"></span></div>
                        </div>
                        <div class="pbk-win__stats">
                            <div class="pbk-stat"><span>BUKA</span><b>{{ fmt(terpilih.buka) }}</b></div>
                            <div class="pbk-stat"><span>TUTUP</span><b>{{ terpilih.masaBerlaku === 'EVERGREEN' ? 'Tanpa batas' : fmt(terpilih.tutup) }}</b></div>
                            <div class="pbk-stat is-hi"><span>SISA</span><b>{{ sisaWaktu(terpilih) }}</b></div>
                        </div>
                    </div>
                </section>

                <!-- Tab "Riwayat" dari rancangan TIDAK dibuat: belum ada satu pun
                     tabel yang mencatat jejak perubahan terbitan, jadi tabnya
                     hanya akan berisi daftar kosong permanen. -->
                <div class="pbk-tabs">
                    <button v-for="t in tabs" :key="t.k" class="pbk-tab" :class="{ on: tab === t.k }" @click="gantiTab(t.k)">
                        <i class="bi" :class="t.ikon"></i> {{ t.label }}
                    </button>
                </div>

                <!-- ══ ANALITIK ═════════════════════════════════════════════ -->
                <section v-if="tab === 'analitik'" v-loading="muatAnalitik" class="pbk-sec">
                    <div class="pbk-metrics">
                        <div v-for="m in kartuMetrik" :key="m.k" class="pbk-metric">
                            <span class="pbk-metric__glow" :style="{ background: m.cahaya }"></span>
                            <div class="pbk-metric__top">
                                <span class="pbk-metric__ico" :style="{ background: m.grad }"><i class="bi" :class="m.ikon"></i></span>
                                <span v-if="m.delta !== null" class="pbk-metric__delta" :class="m.delta >= 0 ? 'is-up' : 'is-down'">
                                    <i class="bi" :class="m.delta >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right'"></i>
                                    {{ Math.abs(m.delta) }}%
                                </span>
                                <span v-else-if="m.nota" class="pbk-metric__delta is-flat">{{ m.nota }}</span>
                            </div>
                            <div class="pbk-metric__lbl">{{ m.label }}</div>
                            <div class="pbk-metric__val">{{ m.nilai }}</div>
                            <GrafikEvo v-if="m.spark" class="pbk-spark" :opsi="m.spark" />
                            <div v-else class="pbk-metric__sub">{{ m.sub }}</div>
                        </div>
                    </div>

                    <div class="pbk-charts">
                        <div class="pbk-card">
                            <div class="pbk-card__head">
                                <div>
                                    <div class="pbk-card__t">Pelamar Masuk</div>
                                    <div class="pbk-card__s">{{ ringkasDeret }}</div>
                                </div>
                                <div class="pbk-ranges">
                                    <button v-for="r in [7, 14, 30]" :key="r" class="pbk-range" :class="{ on: rentang === r }" @click="pilihRentang(r)">{{ r }} hari</button>
                                </div>
                            </div>
                            <GrafikEvo v-if="adaDeret" class="pbk-chartbox" bangun-ulang :opsi="opsiDeret" />
                            <div v-else class="pbk-empty"><i class="bi bi-bar-chart"></i> Belum ada lamaran masuk pada rentang ini.</div>
                        </div>

                    </div>

                    <!-- ══ PELAMAR PER LOKER — HEATMAP ══════════════════════
                         Selalu heatmap, berapa pun jumlah lokernya. Sel yang
                         gelap adalah loker yang diserbu pada hari itu, dan
                         mengkliknya langsung membuka detail loker tersebut —
                         jalur terpendek dari "yang mana ini?" ke jawabannya. -->
                    <div class="pbk-card">
                        <div class="pbk-card__head">
                            <div>
                                <div class="pbk-card__t">Pelamar per Loker (MPP)</div>
                                <div class="pbk-card__s">{{ ringkasHeat }}</div>
                            </div>
                            <span class="pbk-mode"><i class="bi bi-grid-3x3-gap-fill"></i> Heatmap · klik sel untuk detail</span>
                        </div>
                        <GrafikEvo v-if="adaHeat" class="pbk-chartbox" bangun-ulang :opsi="opsiHeat" />
                        <div v-else class="pbk-empty"><i class="bi bi-grid-3x3-gap"></i> Belum ada lamaran yang bisa dipetakan.</div>
                    </div>

                    <div class="pbk-charts">
                        <div class="pbk-card pbk-card--half">
                            <div class="pbk-card__t">Sumber Pelamar</div>
                            <div class="pbk-card__s">Jawaban “Darimana Anda tahu lowongan kami?”</div>
                            <!-- Seluruh opsi master ditampilkan, termasuk yang
                                 nol. Kanal yang menghilang dari daftar terbaca
                                 seperti kanal yang tidak pernah ditawarkan —
                                 padahal "Job Fair nol pelamar" itu temuan. -->
                            <div v-if="adaSumber" class="pbk-src">
                                <div v-for="(sm, i) in analitik.sumber" :key="i" class="pbk-src__row" :class="{ 'is-nol': !sm.jumlah }">
                                    <span class="pbk-src__ico" :style="warnaSumber(i)"><i class="bi" :class="ikonSumber(sm.label)"></i></span>
                                    <span class="pbk-src__body">
                                        <span class="pbk-src__top">
                                            <span class="pbk-src__lbl" :title="sm.label">{{ sm.label }}</span>
                                            <span class="pbk-src__pct">{{ sm.persen }}%</span>
                                        </span>
                                        <span class="pbk-src__bar">
                                            <span :style="{ width: sm.persen + '%', background: gradSumber(i), animationDelay: (i * 0.08) + 's' }"></span>
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <div v-else class="pbk-empty"><i class="bi bi-signpost-split"></i> Formulir pendaftaran program ini belum punya pertanyaan sumber.</div>
                        </div>

                        <div class="pbk-card pbk-card--half pbk-card--ring">
                            <div class="pbk-card__t pbk-card__t--self">Pemenuhan Kuota</div>
                            <GrafikEvo class="pbk-donat" :opsi="opsiKuota" />
                            <div class="pbk-ring__tags">
                                <span class="pbk-tag is-on"><i class="bi bi-people-fill"></i> {{ (analitik.kuota && analitik.kuota.terisi) || 0 }} terisi</span>
                                <span class="pbk-tag"><i class="bi bi-person-plus"></i> {{ (analitik.kuota && analitik.kuota.kuota) || 0 }} kuota</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ══ LOKER TERBIT — DAFTAR ════════════════════════════════ -->
                <section v-else-if="!lokerBuka" class="pbk-card pbk-card--flush">
                    <div class="pbk-lok__head">
                        <div class="pbk-lok__id2">
                            <span class="pbk-lok__badge"><i class="bi bi-folder2-open"></i></span>
                            <div>
                                <div class="pbk-card__t">Loker yang Terbit</div>
                                <div class="pbk-card__s">{{ jmlAktif(terpilih) }} dari {{ (terpilih.posisi || []).length }} loker tampil di landing · klik untuk detail</div>
                            </div>
                        </div>
                        <div class="pbk-lok__act">
                            <div class="pbk-ranges">
                                <button class="pbk-range" :class="{ on: tampilan === 'kartu' }" @click="tampilan = 'kartu'"><i class="bi bi-grid-3x2-gap-fill"></i> Kartu</button>
                                <button class="pbk-range" :class="{ on: tampilan === 'daftar' }" @click="tampilan = 'daftar'"><i class="bi bi-list-ul"></i> Daftar</button>
                            </div>
                            <span class="pbk-tag is-on"><i class="bi bi-people-fill"></i> Kuota {{ totalKuota(terpilih) }}</span>
                        </div>
                    </div>

                    <div v-if="(terpilih.posisi || []).length > 1" class="pbk-massal">
                        <span class="pbk-massal__t"><i class="bi bi-toggles"></i> Semua loker</span>
                        <button class="pbk-massal__btn" :disabled="sibukPosisi || jmlMati(terpilih) === 0" @click="setSemuaPosisi(terpilih, true)">
                            <i class="bi bi-check2-circle"></i> Tampilkan semua
                        </button>
                        <button class="pbk-massal__btn is-mati" :disabled="sibukPosisi || jmlAktif(terpilih) === 0" @click="setSemuaPosisi(terpilih, false)">
                            <i class="bi bi-slash-circle"></i> Sembunyikan semua
                        </button>
                    </div>

                    <!-- ── KARTU ──────────────────────────────────────────── -->
                    <div v-if="tampilan === 'kartu'" class="pbk-grid">
                        <div
                            v-for="(l, i) in terpilih.posisi" :key="l.id"
                            class="pbk-lcard" :class="{ 'is-mati': !l.aktif }"
                            role="button" tabindex="0"
                            @click="bukaLoker(l)" @keyup.enter="bukaLoker(l)"
                        >
                            <span class="pbk-lcard__st" :class="l.aktif ? 'is-on' : 'is-off'">{{ l.aktif ? 'Tampil' : 'Disembunyikan' }}</span>
                            <span class="pbk-lcard__ico" :style="{ background: gradLoker(i), boxShadow: `0 10px 22px ${ringLoker(i)}` }">
                                <i class="bi" :class="ikonLoker(l)"></i>
                            </span>
                            <div class="pbk-lcard__id">
                                <div class="pbk-lcard__t" :title="l.posisi">{{ l.posisi }}</div>
                                <div class="pbk-lcard__mpp">{{ l.mppRef || 'tanpa MPP' }}</div>
                                <div class="pbk-lcard__dept"><i class="bi bi-diagram-2"></i> {{ l.departemen || '—' }}</div>
                            </div>
                            <div class="pbk-lcard__tags">
                                <span class="pbk-mini is-hi">{{ pelamarLoker(l.id) }} pelamar</span>
                                <span class="pbk-mini">{{ l.level || '—' }}</span>
                            </div>
                            <div class="pbk-lcard__seat">
                                <span class="pbk-lcard__seatt"><span>KURSI</span><b>{{ l.terisi }}/{{ l.kuota }}</b></span>
                                <span class="pbk-lcard__bar"><span :style="{ width: persenKursi(l) + '%', background: gradLoker(i) }"></span></span>
                            </div>
                            <!-- BARIS AKSI — dua keputusan yang paling sering
                                 diambil rekruter, keduanya di permukaan kartu.

                                 Sakelar: rancangan menaruhnya di dalam panel
                                 detail, artinya mematikan tiga loker menuntut
                                 membuka tiga panel.

                                 Salin tautan: tadinya melayang di pojok kartu dan
                                 menindih ikon posisinya. Tempatnya di sini —
                                 sebaris dengan aksi lain, bukan menumpuk di atas
                                 sesuatu yang sudah ada penghuninya.

                                 @click.stop supaya menyentuh keduanya tidak
                                 sekalian membuka panel detail. -->
                            <div class="pbk-lcard__act" @click.stop>
                                <el-switch :model-value="l.aktif" :disabled="sibukPosisi" size="small" @change="(v) => setPosisi(terpilih, l, v)" />
                                <span class="pbk-lcard__swlbl">{{ l.aktif ? 'Tampil di landing' : 'Disembunyikan' }}</span>
                                <button
                                    class="pbk-lcard__copy" type="button"
                                    :class="{ 'is-done': idTersalin === l.id }"
                                    :title="idTersalin === l.id ? 'Tautan tersalin' : 'Salin tautan loker'"
                                    :aria-label="'Salin tautan ' + l.posisi"
                                    @click="salinTautanLoker(l)"
                                ><i class="bi" :class="idTersalin === l.id ? 'bi-check2' : 'bi-link-45deg'"></i></button>
                            </div>
                        </div>

                        <div v-if="!(terpilih.posisi || []).length" class="pbk-empty pbk-empty--span">
                            <i class="bi bi-briefcase"></i>
                            Program ini belum punya loker — tidak ada yang bisa dilamar meski terbitannya ditampilkan.
                        </div>
                    </div>

                    <!-- ── DAFTAR ─────────────────────────────────────────── -->
                    <div v-else class="pbk-tblwrap">
                        <table class="pbk-tbl">
                            <thead>
                                <tr><th>POSISI</th><th>NO. MPP</th><th>DEPARTEMEN</th><th>LOKASI</th><th class="ta-r">PELAMAR</th><th>KURSI</th><th class="ta-r">TAUTAN &amp; TAMPIL</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="l in terpilih.posisi" :key="l.id" :class="{ 'is-mati': !l.aktif }" class="is-klik" @click="bukaLoker(l)">
                                    <td>
                                        <div class="pbk-lok__pos">
                                            <span class="pbk-lok__ico"><i class="bi" :class="ikonLoker(l)"></i></span>
                                            <div class="pbk-lok__id">
                                                <div class="pbk-lok__t" :title="l.posisi">{{ l.posisi }}</div>
                                                <div v-if="l.level" class="pbk-lok__lv">{{ l.level }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code v-if="l.mppRef" class="pbk-mpp">{{ l.mppRef }}</code><span v-else class="pbk-manual">manual</span></td>
                                    <td class="pbk-td" :title="l.departemen">{{ l.departemen || '—' }}</td>
                                    <td class="pbk-td" :title="l.lokasi"><i class="bi bi-geo-alt"></i> {{ l.lokasi || '—' }}</td>
                                    <td class="ta-r pbk-num">{{ pelamarLoker(l.id) }}</td>
                                    <td class="pbk-fillcell">
                                        <div class="pbk-fillcell__n">{{ l.terisi }}/{{ l.kuota }}</div>
                                        <div class="pbk-fillcell__bar"><span :style="{ width: persenKursi(l) + '%' }"></span></div>
                                    </td>
                                    <td class="ta-r pbk-actcell" @click.stop>
                                        <button
                                            class="pbk-rowcopy" type="button"
                                            :class="{ 'is-done': idTersalin === l.id }"
                                            :title="idTersalin === l.id ? 'Tautan tersalin' : 'Salin tautan loker'"
                                            @click="salinTautanLoker(l)"
                                        ><i class="bi" :class="idTersalin === l.id ? 'bi-check2' : 'bi-link-45deg'"></i></button>
                                        <el-switch :model-value="l.aktif" :disabled="sibukPosisi" size="small" @change="(v) => setPosisi(terpilih, l, v)" />
                                    </td>
                                </tr>
                                <tr v-if="!(terpilih.posisi || []).length">
                                    <td colspan="7" class="pbk-tbl__empty">Program ini belum punya loker — tidak ada yang bisa dilamar meski terbitannya ditampilkan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-if="jmlMati(terpilih)" class="pbk-matinote">
                        <i class="bi bi-info-circle"></i>
                        <b>{{ jmlMati(terpilih) }} loker</b> tidak ditampilkan — tidak muncul di landing dan tidak menerima
                        lamaran baru, tapi MPP, PIC, serta lamaran yang sudah masuk tetap utuh.
                    </p>
                </section>

                <!-- ══ LOKER TERBIT — DETAIL SATU LOKER ═════════════════════ -->
                <section v-else v-loading="muatLoker" class="pbk-sec">
                    <div class="pbk-hero pbk-hero--loker">
                        <span class="pbk-hero__glow" style="background: radial-gradient(circle, rgba(99,102,241,.13), transparent 70%)"></span>
                        <div class="pbk-dtop">
                            <button class="pbk-ghost pbk-ghost--sm" @click="tutupLoker"><i class="bi bi-arrow-left"></i> Daftar loker</button>
                            <span class="pbk-dcrumb">
                                <span>{{ terpilih.kode }}</span><i class="bi bi-chevron-right"></i><span>Loker</span>
                                <i class="bi bi-chevron-right"></i><span class="on">{{ lokerBuka.mppRef || 'tanpa MPP' }}</span>
                            </span>
                        </div>

                        <div class="pbk-hero__row pbk-hero__row--d">
                            <div class="pbk-dhead">
                                <span class="pbk-dhead__ico" :style="{ background: gradLoker(indexLoker), boxShadow: `0 14px 30px ${ringLoker(indexLoker)}` }">
                                    <i class="bi" :class="ikonLoker(lokerBuka)"></i>
                                </span>
                                <div class="pbk-dhead__id">
                                    <h3 :title="lokerBuka.posisi">{{ lokerBuka.posisi }}</h3>
                                    <div class="pbk-chips">
                                        <span class="pbk-chip" :class="lokerBuka.aktif ? 'is-buka' : 'is-diam'">
                                            <span class="pbk-chip__dot"></span> {{ lokerBuka.aktif ? 'Tampil di landing' : 'Disembunyikan' }}
                                        </span>
                                        <span v-if="lokerBuka.level" class="pbk-chip is-plain">{{ lokerBuka.level }}</span>
                                        <span class="pbk-chip is-plain"><i class="bi bi-person-check-fill"></i> {{ pelamarLoker(lokerBuka.id) }} pelamar</span>
                                    </div>
                                </div>
                            </div>
                            <div class="pbk-hero__act">
                                <button class="pbk-pub" :class="{ on: lokerBuka.aktif }" @click="setPosisi(terpilih, lokerBuka, !lokerBuka.aktif)">
                                    <span class="pbk-pub__sw"><span class="pbk-pub__knob"></span></span>
                                    {{ lokerBuka.aktif ? 'Tampilkan landing page' : 'Disembunyikan' }}
                                </button>
                                <!-- SALIN TAUTAN — untuk dibagikan ke LinkedIn.
                                     URL-nya dirakit SERVER dari host permintaan,
                                     jadi otomatis benar di lokal, staging, dan
                                     produksi tanpa domain yang ditulis di kode. -->
                                <button class="pbk-copy" :class="{ 'is-done': tautanTersalin }" :disabled="!tautanLoker" @click="salinTautan">
                                    <i class="bi" :class="tautanTersalin ? 'bi-check2' : 'bi-link-45deg'"></i>
                                    {{ tautanTersalin ? 'Tersalin' : 'Salin tautan' }}
                                </button>
                            </div>
                        </div>

                        <p v-if="tautanLoker" class="pbk-url" :title="tautanLoker">
                            <i class="bi bi-globe2"></i>
                            <span>{{ tautanLoker }}</span>
                            <a :href="tautanLoker" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Buka</a>
                        </p>

                        <div class="pbk-fields">
                            <div v-for="f in medanLoker" :key="f.label" class="pbk-field">
                                <div class="pbk-field__l">{{ f.label }}</div>
                                <div class="pbk-field__v" :class="{ 'is-off': f.kosong }">
                                    <i class="bi" :class="f.ikon" :style="{ color: f.warna }"></i> {{ f.nilai }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pbk-charts">
                        <div class="pbk-card">
                            <div class="pbk-card__head">
                                <div>
                                    <div class="pbk-card__t">Piramida Seleksi</div>
                                    <div class="pbk-card__s">{{ ringkasPiramida }}</div>
                                </div>
                            </div>
                            <GrafikEvo v-if="adaPiramida" class="pbk-chartbox" bangun-ulang :opsi="opsiPiramidaLoker" />
                            <div v-else class="pbk-empty"><i class="bi bi-triangle"></i> Belum ada pelamar yang menjalani tahap seleksi loker ini.</div>
                        </div>
                    </div>

                    <div class="pbk-charts">
                        <div class="pbk-card pbk-card--side pbk-card--ring">
                            <div class="pbk-card__t pbk-card__t--self">Pemenuhan Kursi</div>
                            <GrafikEvo class="pbk-donat" :opsi="opsiKursiLoker" />
                            <div class="pbk-ring__tags">
                                <span class="pbk-tag is-on">{{ lokerBuka.terisi }} terisi</span>
                                <span class="pbk-tag">{{ lokerBuka.kuota }} kuota</span>
                            </div>
                        </div>

                        <div class="pbk-card pbk-card--wide">
                            <div class="pbk-card__head">
                                <div>
                                    <div class="pbk-card__t">Pelamar Loker Ini</div>
                                    <div class="pbk-card__s">{{ ringkasTrenLoker }}</div>
                                </div>
                                <span class="pbk-tag is-on"><i class="bi bi-people-fill"></i> {{ pelamarLoker(lokerBuka.id) }} total</span>
                            </div>
                            <GrafikEvo v-if="adaTrenLoker" class="pbk-chartbox" :opsi="opsiTrenLoker" />
                            <div v-else class="pbk-empty"><i class="bi bi-graph-up"></i> Belum ada lamaran untuk loker ini.</div>
                        </div>
                    </div>

                    <!-- ══ PHONE SCREENING ═══════════════════════════════════
                         Digambar HANYA bila loker ini memang punya sesi skrining.
                         Kartu kosong bertuliskan "belum ada data" di layar yang
                         sudah penuh kartu cuma menambah satu hal lagi untuk
                         dilewati mata.

                         Yang ditaruh paling atas bukan skor rata-rata, melainkan
                         PERTANYAAN YANG MENGGUGURKAN. Skor menjawab "seberapa
                         bagus pelamarnya"; pertanyaan penggugur menjawab "apa
                         yang salah dengan iklan lokernya" — dan yang kedua bisa
                         diperbaiki minggu ini. -->
                    <div v-if="skr" class="pbk-card">
                        <div class="pbk-card__head">
                            <div>
                                <div class="pbk-card__t">
                                    <i class="bi bi-telephone-inbound"></i> Hasil Phone Screening
                                </div>
                                <div class="pbk-card__s">
                                    {{ skr.selesai }} selesai dari {{ skr.total }} sesi
                                    <span v-if="skr.berjalan">· {{ skr.berjalan }} masih berjalan</span>
                                    <span v-for="t in skr.template" :key="t.label"> · {{ t.label }} ({{ t.jml }})</span>
                                </div>
                            </div>
                            <span v-if="skr.skor.ada" class="pbk-tag is-on">
                                <i class="bi bi-speedometer2"></i> rata-rata {{ skr.skor.rata }}%
                            </span>
                        </div>

                        <!-- Pertanyaan penggugur — paling atas dan sengaja. -->
                        <div v-if="skr.knockout.jml" class="pbk-skr__ko">
                            <div class="pbk-skr__koHead">
                                <i class="bi bi-exclamation-octagon-fill"></i>
                                <b>{{ skr.knockout.jml }} pelamar ({{ skr.knockout.persen }}%) ditandai gugur otomatis</b>
                            </div>
                            <div v-for="k in skr.knockout.perPertanyaan" :key="k.kode" class="pbk-skr__koRow">
                                <span class="pbk-skr__koN">{{ k.jml }}×</span>
                                <span>{{ k.pesan || k.kode }}</span>
                            </div>
                            <p class="pbk-skr__koNota">
                                Angka besar di satu pertanyaan biasanya bukan soal pelamarnya — melainkan
                                syarat yang belum disebut di iklan lokernya.
                            </p>
                        </div>

                        <div class="pbk-two">
                            <div class="pbk-skr__blok">
                                <div class="pbk-skr__t">Rekomendasi petugas</div>
                                <div v-for="r in skr.rekomendasi" :key="r.label" class="pbk-skr__bar">
                                    <span class="pbk-skr__lbl">{{ r.label }}</span>
                                    <span class="pbk-skr__track">
                                        <span :style="{ width: `${skrPersen(r.jml, skr.total)}%`, background: r.warna }"></span>
                                    </span>
                                    <b>{{ r.jml }}</b>
                                </div>
                            </div>

                            <div class="pbk-skr__blok">
                                <div class="pbk-skr__t">
                                    Sebaran skor
                                    <small v-if="skr.skor.ada">{{ skr.skor.min }}% – {{ skr.skor.maks }}%</small>
                                </div>
                                <template v-if="skr.skor.ada">
                                    <div v-for="b in skr.skor.sebaran" :key="b.label" class="pbk-skr__bar">
                                        <span class="pbk-skr__lbl">{{ b.label }}</span>
                                        <span class="pbk-skr__track">
                                            <span :style="{ width: `${skrPersen(b.jml, skr.selesai)}%` }" class="is-skor"></span>
                                        </span>
                                        <b>{{ b.jml }}</b>
                                    </div>
                                </template>
                                <div v-else class="pbk-skr__samar">
                                    Belum ada sesi selesai yang berskor.
                                </div>
                            </div>
                        </div>

                        <!-- Panggilan yang tidak nyambung. Jarang dicari sampai
                             seseorang bertanya kenapa skrining loker ini lambat. -->
                        <div v-if="skr.kontak.sebaran.length" class="pbk-skr__kontak">
                            <span class="pbk-skr__t">Hasil panggilan</span>
                            <span v-for="k in skr.kontak.sebaran" :key="k.kode" class="pbk-tag">
                                {{ k.label }} <b>{{ k.jml }}</b>
                            </span>
                            <span v-if="skr.kontak.rataPercobaan" class="pbk-skr__samar">
                                rata-rata {{ skr.kontak.rataPercobaan }}× percobaan
                            </span>
                        </div>

                        <!-- Sebaran jawaban per pertanyaan. Dilipat karena
                             panjang, dan yang dicari orang biasanya sudah
                             terjawab tiga blok di atas. -->
                        <template v-if="skr.pertanyaan.length">
                            <button class="pbk-skr__lipat" @click="skrRinci = !skrRinci">
                                <i class="bi" :class="skrRinci ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                                Sebaran jawaban per pertanyaan
                                <span class="pbk-skr__samar">{{ skr.pertanyaan.length }} pertanyaan berskala/berpilihan</span>
                            </button>

                            <div v-if="skrRinci" class="pbk-skr__rinci">
                                <div v-for="q in skr.pertanyaan" :key="q.kode" class="pbk-skr__q">
                                    <div class="pbk-skr__qh">
                                        <b>{{ q.label }}</b>
                                        <span v-if="q.knockout" class="pbk-tag is-ko">auto-gugur</span>
                                        <span v-if="q.rataNilai !== null" class="pbk-skr__samar">rata {{ q.rataNilai }}</span>
                                    </div>
                                    <div v-for="v in q.sebaran" :key="v.label" class="pbk-skr__bar">
                                        <span class="pbk-skr__lbl">{{ v.label }}</span>
                                        <span class="pbk-skr__track">
                                            <span :style="{ width: `${skrPersen(v.jml, q.jml)}%` }"></span>
                                        </span>
                                        <b>{{ v.jml }}</b>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- ══ ISI MPP — sama seperti yang tampil di Master MPP ══ -->
                    <div v-if="mppLoker" class="pbk-card">
                        <div class="pbk-card__head">
                            <div>
                                <div class="pbk-card__t">Isi MPP <code class="pbk-mpp">{{ mppLoker.noTransaksi }}</code></div>
                                <div class="pbk-card__s">Uraian jabatan yang dipakai landing publik dan diperiksa saat pelamar submit</div>
                            </div>
                            <span class="pbk-tag" :class="mppLoker.status === 'AKTIF' ? 'is-on' : ''">{{ mppLoker.status }}</span>
                        </div>

                        <p v-if="mppLoker.deskripsi" class="pbk-desc">{{ mppLoker.deskripsi }}</p>

                        <div class="pbk-two">
                            <div v-if="mppLoker.tanggungJawab.length" class="pbk-poin">
                                <div class="pbk-poin__t"><i class="bi bi-list-check"></i> Tanggung Jawab</div>
                                <div v-for="(t, i) in mppLoker.tanggungJawab" :key="i" class="pbk-poin__row">
                                    <span class="pbk-poin__ico is-idg"><i class="bi bi-dot"></i></span>
                                    <span>{{ t }}</span>
                                </div>
                            </div>
                            <div v-if="mppLoker.persyaratan.length" class="pbk-poin">
                                <div class="pbk-poin__t"><i class="bi bi-patch-check"></i> Kriteria Loker</div>
                                <div v-for="(t, i) in mppLoker.persyaratan" :key="i" class="pbk-poin__row">
                                    <span class="pbk-poin__ico is-ok"><i class="bi bi-check-lg"></i></span>
                                    <span>{{ t }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="mppLoker.skill.length" class="pbk-tagset">
                            <div class="pbk-poin__t"><i class="bi bi-stars"></i> Skill</div>
                            <div class="pbk-tagset__wrap">
                                <span
                                    v-for="(s, i) in mppLoker.skill" :key="i" class="pbk-skill"
                                    :style="s.warna ? { background: `${s.warna}1a`, color: s.warna } : null"
                                    :title="s.kategori || ''"
                                ><i v-if="s.ikon" class="bi" :class="s.ikon"></i> {{ s.nama }}</span>
                            </div>
                        </div>

                        <div v-if="mppLoker.benefit.length" class="pbk-tagset">
                            <div class="pbk-poin__t"><i class="bi bi-gift"></i> Benefit</div>
                            <div class="pbk-tagset__wrap">
                                <span v-for="(b, i) in mppLoker.benefit" :key="i" class="pbk-benefit"><i class="bi bi-check-circle-fill"></i> {{ b }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="!muatLoker" class="pbk-card">
                        <div class="pbk-empty">
                            <i class="bi bi-file-earmark-x"></i>
                            Loker ini tidak tertaut ke MPP mana pun, jadi uraian jabatannya tidak ada untuk ditampilkan.
                        </div>
                    </div>
                </section>
            </main>

            <main v-else class="pbk-main">
                <div class="pbk-pick">
                    <span class="pbk-pick__ico"><i class="bi bi-hand-index-thumb"></i></span>
                    <strong>Pilih terbitan di sebelah kiri</strong>
                    <small>Analitik, window pendaftaran, dan lokernya akan tampil di sini.</small>
                </div>
            </main>
        </div>


        <!-- Latar gelap lembar filter — hanya berarti di layar sempit. -->
        <div v-if="sheetOpen" class="pbk-sheetbg" @click="sheetOpen = false"></div>
        <!-- Tombol mengambang: disembunyikan CSS di layar lebar (filternya sudah
             terlihat di kolom kiri) dan saat panel kiri sedang tergantikan
             detail terbitan — filter itu milik daftar, bukan milik detail. -->
        <button v-if="!selMobile" class="pbk-fab" type="button" aria-label="Filter" @click="sheetOpen = true">
            <i class="bi bi-funnel-fill"></i>
            <span v-if="jumlahFilter" class="pbk-fab__badge">{{ jumlahFilter }}</span>
        </button>

        <!-- Modal buka/ubah -->
        <AdminModal :busy="saving" :show="show" lg :title="editingId ? 'Ubah Pembukaan' : 'Buka Program'" subtitle="Publikasikan program ke landing + window pendaftaran" icon="bi-megaphone" :save-label="editingId ? 'Perbarui' : 'Buka'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-megaphone"></i> Detail Pembukaan</div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Program</label>
                        <div class="pbk-prog">
                            <!-- Kotak cari hanya muncul saat program banyak (>6) — kalau sedikit tak perlu. -->
                            <el-input v-if="programs.length > 6" v-model="programCari" placeholder="Cari program…" clearable size="default" style="margin-bottom:.5rem">
                                <template #prefix><i class="bi bi-search"></i></template>
                            </el-input>
                            <div class="pbk-proglist">
                                <div v-if="programsLoading" class="pbk-progload"><span class="pbk-spin"></span> Memuat program…</div>
                                <button v-for="p in programsTampil" v-else :key="p.kode" type="button" class="pbk-progcard" :class="{ 'is-on': form.program === p.kode }" @click="form.program = p.kode">
                                    <div class="pbk-progcard__head">
                                        <span class="pbk-progcard__ico" :class="katPill(p.kategori)"><i class="bi" :class="katIkon(p.kategori)"></i></span>
                                        <div class="pbk-progcard__id">
                                            <strong>{{ p.nama }}</strong>
                                            <small>{{ katLabel(p.kategori) }} · {{ p.penyelenggara }}</small>
                                        </div>
                                        <i v-if="form.program === p.kode" class="bi bi-check-circle-fill pbk-progcard__chk"></i>
                                    </div>
                                    <div class="pbk-progcard__meta">
                                        <span v-if="p.alur"><i class="bi bi-signpost-split"></i> {{ p.alur }}</span>
                                        <span v-if="p.jumlahPosisi"><i class="bi bi-briefcase"></i> {{ p.jumlahPosisi }} posisi</span>
                                        <span v-if="p.tglMulai" class="pbk-progcard__date"><i class="bi bi-calendar3"></i> {{ fmtTgl(p.tglMulai) }}<template v-if="p.tglSelesai"> – {{ fmtTgl(p.tglSelesai) }}</template></span>
                                        <span v-else-if="p.jadwal"><i class="bi bi-calendar3"></i> {{ p.jadwal }}</span>
                                    </div>
                                </button>
                                <div v-if="!programsLoading && !programsTampil.length" class="pbk-progempty"><i class="bi bi-inbox"></i> Tidak ada program berjalan yang cocok.</div>
                            </div>
                        </div>
                    </div>
                    <div><label class="wca-field-lbl">Masa Berlaku</label>
                        <el-select filterable v-model="form.masaBerlaku" placeholder="Pilih" style="width:100%" @change="onMasa">
                            <el-option label="Berbatas (ada tanggal tutup)" value="BERBATAS" />
                            <el-option label="Selalu Terbuka (tanpa tanggal tutup)" value="EVERGREEN" />
                        </el-select>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Tanggal &amp; Jam Buka</label><el-date-picker v-model="form.buka" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" format="DD MMM YYYY HH:mm" placeholder="Pilih tanggal &amp; jam" style="width:100%" :disabled-date="hariLampau" /></div>
                        <div><label class="wca-field-lbl">Tanggal &amp; Jam Tutup</label><el-date-picker v-model="form.tutup" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" format="DD MMM YYYY HH:mm" placeholder="Pilih tanggal &amp; jam" style="width:100%" :default-time="akhirHari" :disabled="form.masaBerlaku === 'EVERGREEN'" :disabled-date="(d) => sebelumMulai(d, form.buka)" /></div>
                    </div>
                    <p class="pbk-note"><i class="bi bi-info-circle"></i> Begitu disimpan, program <b>langsung terbit</b> ke landing. Ingin menundanya? Jadikan draft lewat tombol status di daftar.</p>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Pembukaan" :busy="deleting" confirm-label="Ya, Hapus" note="Pembukaan program ini akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus pembukaan <strong>{{ delTarget?.programNama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import GrafikEvo from '@career/GrafikEvo.vue';
import RefSelect from '@career/RefSelect.vue';
import { ingatModal } from '@utils/ingatModal';
import { layarLebar } from '@utils/layarLebar';
import { layarSempit } from '@utils/layarSempit';
import { tgl, tglJam, tglPendek } from '@utils/tanggal';
import { WARNA, opsiDonat, opsiGaris, opsiHeatmap, opsiKolomTumpuk, opsiPictorialAlur, opsiSpark } from '@utils/grafik';

const API = '/api/v1/pembukaan';
const CFG = { headers: { Accept: 'application/json' } };

/**
 * KEADAAN SEBUAH TERBITAN — satu tempat, dipakai daftar DAN panel kanan.
 *
 * "Terbit"/"Draf" saja tidak menjawab pertanyaan yang sebenarnya dibawa admin:
 * apakah orang bisa melamar sekarang. Terbitan yang ditampilkan tapi jendelanya
 * belum dibuka, dan yang sudah lewat tanggal tutup, keduanya berlencana sama
 * padahal keduanya berarti nol lamaran masuk.
 */
const NADA = {
    buka: { titik: '#059669', pendek: 'Ditampilkan', ikon: 'bi-broadcast', cahaya: 'rgba(16,185,129,.13)' },
    nanti: { titik: '#d97706', pendek: 'Terjadwal', ikon: 'bi-hourglass-top', cahaya: 'rgba(245,158,11,.14)' },
    tutup: { titik: '#94a3b8', pendek: 'Selesai', ikon: 'bi-lock-fill', cahaya: 'rgba(100,116,139,.12)' },
    diam: { titik: '#d97706', pendek: 'Draf', ikon: 'bi-file-earmark-text', cahaya: 'rgba(245,158,11,.14)' },
};

/** Kelompok di panel kiri — urutannya menentukan urutan tampil. */
const GRUP = [
    { kode: 'buka', label: 'DITAMPILKAN' },
    { kode: 'nanti', label: 'TERJADWAL' },
    { kode: 'diam', label: 'DRAF' },
    { kode: 'tutup', label: 'ARSIP' },
];

/** Gradien & bayangan kartu loker — berputar tiga warna seperti di rancangan. */
const GRAD = [
    'linear-gradient(135deg,#818cf8,#4338ca)',
    'linear-gradient(135deg,#a78bfa,#7c3aed)',
    'linear-gradient(135deg,#fbbf24,#d97706)',
];
const RING = ['rgba(79,70,229,.28)', 'rgba(124,58,237,.28)', 'rgba(217,119,6,.28)'];

const HARI_MS = 86400000;

/** Baris terbitan per halaman di panel kiri. */
const PER_HALAMAN = 10;

export default {
    components: { Head, AdminModal, ConfirmModal, GrafikEvo, RefSelect },
    mixins: [layarSempit(), ingatModal('admin/pembukaan-program/pembukaanProgram')],
    data() {
        return {
            list: [],
            loading: false,
            sel: null,
            tab: 'analitik',
            cakupan: 'SEMUA',
            urutTurun: true,
            halaman: 1,
            // `status` sengaja TIDAK ada di sini: pilihan Semua/Tampil/Draf
            // sudah dijawab lingkup di atas daftar, dan dua kendali untuk satu
            // pertanyaan hanya membuat orang bertanya mana yang menang.
            filters: { q: '', kategori: null, masa: null, pic: null, rentang: null },
            kategoriOpsi: [],
            // Lingkup PIC pengguna pada halaman ini — datang bersama daftar
            // terbitannya, jadi tidak ada permintaan tambahan hanya untuk tahu
            // apakah sebuah saringan perlu digambar.
            akses: { lingkupPic: 'SEMUA', kodeSaya: null },
            sheetOpen: false,
            cariTimer: null,

            // ── analitik ──
            analitik: {},
            muatAnalitik: false,
            rentang: 7,

            // ── loker ──
            tampilan: 'kartu',
            lokerId: null,
            muatLoker: false,
            mppLoker: null,
            corong: { total: 0, tahap: [] },
            skr: null,
            skrRinci: false,
            tautanLoker: '',
            tautanTersalin: false,
            // Id loker yang tautannya baru saja disalin dari kartu/baris —
            // dipakai mengubah ikon rantai jadi centang sebentar.
            idTersalin: null,
            salinTimer: null,

            show: false,
            editingId: null,
            saving: false,
            programs: [],
            programsLoading: false,
            programCari: '',
            akhirHari: new Date(2000, 0, 1, 23, 59, 59),
            form: { program: '', masaBerlaku: 'BERBATAS', buka: null, tutup: null, statusPublish: 'TERBIT' },
            sibukPosisi: false,
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
            fokus: new URLSearchParams(window.location.search).get('fokus'),
        };
    },
    computed: {
        tabs() {
            // "Riwayat" dari rancangan sengaja tidak ada — lihat catatan di template.
            return [
                { k: 'analitik', label: 'Analitik', ikon: 'bi-bar-chart-line-fill' },
                { k: 'loker', label: 'Loker Terbit', ikon: 'bi-briefcase-fill' },
            ];
        },
        jmlTerbit() { return this.list.filter((b) => b.statusPublish === 'TERBIT').length; },
        /**
         * Kartu program di modal "Buka Program", disaring kotak cari.
         *
         * Penyaringannya menyapu nama, kategori, alur, dan penyelenggara
         * sekaligus — rekruter mengetik apa saja yang diingatnya tentang
         * program itu, bukan selalu namanya.
         */
        programsTampil() {
            const q = this.programCari.trim().toLowerCase();
            if (!q) return this.programs;

            return this.programs.filter((p) => `${p.nama} ${p.kategori} ${p.alur || ''} ${p.penyelenggara || ''}`
                .toLowerCase()
                .includes(q));
        },
        lingkup() {
            const n = (f) => this.list.filter(f).length;

            return [
                { kode: 'SEMUA', label: 'Semua', jml: this.list.length },
                { kode: 'TAMPIL', label: 'Tampil', jml: n((b) => this.keadaan(b).nada === 'buka') },
                { kode: 'DRAF', label: 'Draf', jml: n((b) => b.statusPublish !== 'TERBIT') },
            ];
        },
        /** Ada saringan yang sedang berlaku? Menentukan tombol Reset & lencana. */
        adaFilter() {
            return !!(this.filters.q || this.filters.kategori || this.filters.masa || this.filters.pic
                || (this.filters.rentang && this.filters.rentang.length));
        },
        jumlahFilter() {
            return [
                this.filters.q,
                this.filters.kategori,
                this.filters.masa,
                this.filters.pic,
                this.filters.rentang?.length ? 1 : null,
            ].filter(Boolean).length;
        },
        tersaring() {
            let out = this.list;
            if (this.cakupan === 'TAMPIL') out = out.filter((b) => this.keadaan(b).nada === 'buka');
            if (this.cakupan === 'DRAF') out = out.filter((b) => b.statusPublish !== 'TERBIT');
            // Masa berlaku disaring DI SINI, bukan di server: nilainya sudah ikut
            // tiap baris, dan satu perjalanan jaringan untuk menyaring data yang
            // sudah ada di layar hanya membuat daftar berkedip.
            if (this.filters.masa) out = out.filter((b) => b.masaBerlaku === this.filters.masa);
            if (this.filters.pic) out = out.filter((b) => (b.posisi || []).some((l) => l.picKode === this.filters.pic));

            // Urutan dibalik di sini, bukan lewat permintaan ulang ke server:
            // datanya sudah ada di layar, dan meminta ulang hanya untuk membalik
            // urutan membuat daftar berkedip tanpa alasan.
            return this.urutTurun ? out : [...out].reverse();
        },
        /*
         * ══ PAGINASI PANEL KIRI ═════════════════════════════════════════════
         * Dipotong di SISI LAYAR, bukan lewat permintaan ulang ke server:
         * daftar terbitan satu kategori tidak pernah sebesar itu, dan datanya
         * sudah ada — meminta ulang hanya untuk berpindah halaman membuat
         * daftar berkedip tanpa alasan.
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

            this.list.forEach((b) => (b.posisi || []).forEach((l) => {
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
        /** Baris pada halaman yang sedang dibuka. */
        halamanIni() {
            const dari = (this.halaman - 1) * PER_HALAMAN;

            return this.tersaring.slice(dari, dari + PER_HALAMAN);
        },
        rentangBaris() {
            const n = this.tersaring.length;
            if (!n) return '0 terbitan';

            const dari = (this.halaman - 1) * PER_HALAMAN + 1;

            return `${dari}–${Math.min(dari + PER_HALAMAN - 1, n)} dari ${n}`;
        },
        /**
         * Nomor halaman yang dicetak, dengan "…" bila jumlahnya banyak.
         *
         * Selalu menampilkan halaman pertama, terakhir, dan tetangga halaman
         * aktif. Mencetak seluruh nomor membuat panel selebar 300px meluber
         * begitu terbitannya lewat seratus.
         */
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
            return GRUP
                .map((g) => ({ ...g, items: this.halamanIni.filter((b) => this.keadaan(b).nada === g.kode) }))
                .filter((g) => g.items.length);
        },
        terpilih() { return this.list.find((b) => b.id === this.sel) || null; },
        /** Di layar sempit panel kanan MENGGANTIKAN daftar, bukan mendampinginya. */
        selMobile() { return !!this.terpilih; },

        /** Loker yang sedang dibuka detailnya — objek POSISI, bukan salinan. */
        lokerBuka() {
            if (!this.terpilih || this.lokerId === null) return null;

            return (this.terpilih.posisi || []).find((l) => l.id === this.lokerId) || null;
        },
        indexLoker() {
            if (!this.terpilih || this.lokerId === null) return 0;

            return Math.max(0, (this.terpilih.posisi || []).findIndex((l) => l.id === this.lokerId));
        },

        /** Empat kartu angka — seluruhnya terukur. Lihat AnalitikPembukaan.php. */
        kartuMetrik() {
            const r = this.analitik.ringkas || {};
            const d = this.analitik.deret || {};
            const nilai = (d.titik || []).map((t) => t.nilai);
            const spark = nilai.length ? opsiSpark({ nilai, warna: WARNA.indigo }) : null;

            return [
                {
                    k: 'pelamar', label: 'Pelamar Masuk', nilai: this.angka(r.pelamar),
                    ikon: 'bi-person-check-fill', grad: 'linear-gradient(135deg,#818cf8,#4f46e5)',
                    cahaya: 'radial-gradient(circle,rgba(99,102,241,.13),transparent 70%)',
                    spark, delta: d.delta ?? null, nota: d.delta === null ? 'Belum ada pembanding' : '',
                },
                {
                    k: 'proses', label: 'Sedang Diproses', nilai: this.angka(r.berjalan),
                    ikon: 'bi-arrow-repeat', grad: 'linear-gradient(135deg,#fbbf24,#d97706)',
                    cahaya: 'radial-gradient(circle,rgba(245,158,11,.15),transparent 70%)', delta: null,
                    sub: `dari ${this.angka(r.pelamar)} pelamar`,
                },
                {
                    k: 'lulus', label: 'Diterima', nilai: this.angka(r.lulus),
                    ikon: 'bi-patch-check-fill', grad: 'linear-gradient(135deg,#34d399,#059669)',
                    cahaya: 'radial-gradient(circle,rgba(16,185,129,.14),transparent 70%)', delta: null,
                    sub: (r.lulus || r.gugur) ? `${r.persenLulus}% dari yang sudah diputus` : 'belum ada keputusan',
                },
                {
                    k: 'kuota', label: 'Kursi Terisi', nilai: `${this.angka(r.terisi)}/${this.angka(r.kuota)}`,
                    ikon: 'bi-people-fill', grad: 'linear-gradient(135deg,#a78bfa,#7c3aed)',
                    cahaya: 'radial-gradient(circle,rgba(139,92,246,.13),transparent 70%)', delta: null,
                    sub: `${r.persenKuota || 0}% pemenuhan kuota`,
                },
            ];
        },
        ringkasDeret() {
            const d = this.analitik.deret || {};
            if (!d.titik || !d.titik.length) return 'Belum ada data';
            const bagian = [`${d.periodeIni || 0} pelamar dalam ${d.hari} hari terakhir`];
            if (d.delta !== null && d.delta !== undefined) {
                bagian.push(`${d.delta >= 0 ? 'naik' : 'turun'} ${Math.abs(d.delta)}% dari periode sebelumnya`);
            }

            return bagian.join(' · ');
        },
        /**
         * Ada yang layak digambar?
         *
         * Bukan sekadar "deretnya terisi": deret SELALU berisi satu titik per
         * hari, termasuk saat semuanya nol. Grafik batang kosong berikut sumbu
         * lengkap terbaca seperti grafik yang gagal dimuat, bukan seperti
         * "belum ada lamaran".
         */
        adaDeret() { return ((this.analitik.deret || {}).titik || []).some((t) => t.nilai > 0); },
        /**
         * "Pelamar Masuk" — KOLOM BERTUMPUK, satu tumpukan per loker.
         *
         * Bukan garis tunggal. Satu terbitan membawa banyak MPP: garis yang
         * menjumlahkan semuanya menjawab "berapa" tapi tidak pernah "dari loker
         * mana", dan pada data jarang ia menggambar bukit landai di hari-hari
         * yang sebenarnya nol. Tumpukan menjaga total tetap terbaca sebagai
         * tinggi kolom, sekaligus menyebut asal tiap potongannya.
         *
         * Datanya deret harian yang SAMA dengan heatmap di bawahnya — satu
         * perhitungan di server, dua sudut pandang di layar.
         */
        opsiDeret() {
            const titik = (this.analitik.deret || {}).titik || [];
            const baris = this.heat.baris || [];
            const panjang = this.heat.kolomPanjang || [];

            // Belum ada loker terdata (mis. terbitan tanpa posisi) — jatuh ke
            // satu tumpukan "seluruh pelamar" supaya grafiknya tetap benar.
            const seri = baris.length
                ? baris.map((b) => ({ nama: b.mppRef ? `${b.posisi} · ${b.mppRef}` : b.posisi, data: b.sel }))
                : [{ nama: 'Pelamar', data: titik.map((t) => t.nilai) }];

            const kategori = baris.length ? (this.heat.kolom || []) : titik.map((t) => t.label);

            return opsiKolomTumpuk({
                kategori,
                seri,
                tinggi: 290,
                judulTip: (i) => (baris.length
                    ? (panjang[i] || kategori[i] || '')
                    : (tgl(titik[i]?.tanggal) || titik[i]?.label || '')),
            });
        },

        // ── HEATMAP PELAMAR PER LOKER ───────────────────────────────────────
        heat() { return this.analitik.heat || { kolom: [], baris: [], puncak: 0 }; },
        adaHeat() { return (this.heat.baris || []).length > 0; },
        ringkasHeat() {
            const b = this.heat.baris || [];
            if (!b.length) return 'Belum ada loker pada terbitan ini';
            const total = b.reduce((n, x) => n + x.total, 0);

            return `${b.length} loker · ${total} pelamar dalam ${this.heat.hari || this.rentang} hari terakhir · puncak ${this.heat.puncak}/hari`;
        },
        opsiHeat() {
            const baris = this.heat.baris || [];

            return opsiHeatmap({
                baris: baris.map((b) => b.posisi),
                subBaris: baris.map((b) => b.mppRef || ''),
                kolom: this.heat.kolomPanjang || this.heat.kolom || [],
                data: baris.map((b) => b.sel),
                onKlik: (y) => {
                    const target = baris[y];
                    if (!target) return;
                    const posisi = (this.terpilih?.posisi || []).find((l) => l.id === target.id);
                    if (posisi) this.bukaLoker(posisi);
                },
            });
        },

        adaSumber() { return (this.analitik.sumber || []).length > 0; },
        opsiKuota() {
            const k = this.analitik.kuota || {};

            return opsiDonat({ persen: k.persen || 0, terisi: k.terisi || 0, kuota: k.kuota || 0, tinggi: 200 });
        },

        // ── GRAFIK DI PANEL DETAIL LOKER ────────────────────────────────────
        /** Deret harian loker ini — diambil dari baris heatmap yang sama. */
        derajatLoker() {
            if (!this.lokerBuka) return null;

            return (this.heat.baris || []).find((b) => b.id === this.lokerBuka.id) || null;
        },
        adaTrenLoker() { return !!this.derajatLoker && this.derajatLoker.total > 0; },
        ringkasTrenLoker() {
            const d = this.derajatLoker;
            if (!d || !d.total) return 'Belum ada lamaran pada rentang ini';

            return `${d.total} pelamar dalam ${this.heat.hari || this.rentang} hari terakhir · puncak ${Math.max(...d.sel)}/hari`;
        },
        opsiTrenLoker() {
            const d = this.derajatLoker;
            const panjang = this.heat.kolomPanjang || [];

            return opsiGaris({
                kategori: this.heat.kolom || [],
                nilai: d ? d.sel : [],
                tinggi: 210,
                judulTip: (i) => panjang[i] || (this.heat.kolom || [])[i] || '',
            });
        },
        // ── PIRAMIDA SELEKSI LOKER ──────────────────────────────────────────
        /* Lebar batang. Pembagi nol dijaga di sini, bukan di template:
           `0/0` menghasilkan NaN yang diam-diam jadi `width: NaNpx` dan
           batangnya lenyap tanpa satu pun galat di konsol. */
        skrPersen(n, total) {
            return total > 0 ? Math.round((n / total) * 100) : 0;
        },
        adaPiramida() { return (this.corong.tahap || []).some((t) => t.sampai > 0); },
        ringkasPiramida() {
            const t = this.corong.tahap || [];
            if (!t.length) return 'Alur seleksi loker ini belum berjalan';

            const dasar = t[0]?.sampai || 0;
            const puncak = [...t].reverse().find((x) => x.sampai > 0);

            return `${t.length} tahap · ${dasar} masuk seleksi · ${puncak ? `${puncak.sampai} bertahan di ${puncak.label}` : 'belum ada yang lanjut'}`;
        },
        opsiPiramidaLoker() {
            return opsiPictorialAlur({ tahap: this.corong.tahap || [], total: this.corong.total || 0 });
        },

        opsiKursiLoker() {
            const l = this.lokerBuka;
            const persen = l && l.kuota ? Math.round((l.terisi / l.kuota) * 100) : 0;

            return opsiDonat({ persen, terisi: l?.terisi || 0, kuota: l?.kuota || 0, tinggi: 190 });
        },

        /**
         * Kotak-kotak keterangan loker. Sumbernya DUA: baris posisi program
         * (kuota, status tampil) dan MPP-nya (lokasi kerja, tipe kerja,
         * pendidikan). Yang belum terisi tetap ditampilkan dengan nilai "—"
         * agar bentuk gridnya tidak berubah-ubah antar loker.
         */
        medanLoker() {
            const l = this.lokerBuka;
            if (!l) return [];

            const m = this.mppLoker || {};
            const p = this.terpilih;
            const isi = (v, teks) => ({ nilai: v || teks || '—', kosong: !v });

            return [
                { label: 'NO. MPP', ikon: 'bi-hash', warna: '#6366f1', ...isi(l.mppRef, 'tanpa MPP') },
                { label: 'DEPARTEMEN', ikon: 'bi-diagram-2', warna: '#8b5cf6', ...isi(l.departemen) },
                { label: 'LEVEL', ikon: 'bi-bar-chart-steps', warna: '#4f46e5', ...isi(l.level) },
                { label: 'LOKASI KERJA', ikon: 'bi-geo-alt-fill', warna: '#f59e0b', ...isi(m.lokasi || l.lokasi) },
                { label: 'TIPE KERJA', ikon: 'bi-buildings', warna: '#2563eb', ...isi(m.workplaceType) },
                { label: 'STATUS KERJA', ikon: 'bi-briefcase', warna: '#0891b2', ...isi(m.employmentType) },
                { label: 'PENGALAMAN', ikon: 'bi-mortarboard', warna: '#7c3aed', ...isi(m.experienceLevel) },
                { label: 'PELAMAR', ikon: 'bi-person-check-fill', warna: '#4f46e5', nilai: `${this.pelamarLoker(l.id)} orang`, kosong: false },
                { label: 'KURSI', ikon: 'bi-people-fill', warna: '#6366f1', nilai: `${l.terisi} / ${l.kuota} terisi`, kosong: false },
                { label: 'PIC MPP', ikon: 'bi-person-badge-fill', warna: '#059669', ...isi(m.penanggungJawab, 'belum ditugaskan') },
                {
                    label: 'WINDOW',
                    ikon: 'bi-calendar-week',
                    warna: '#d97706',
                    ...isi(
                        p?.buka ? `${this.fmt(p.buka)} – ${p.masaBerlaku === 'EVERGREEN' ? 'tanpa batas' : this.fmt(p.tutup)}` : '',
                        'belum diatur',
                    ),
                },
                { label: 'TERBITAN', ikon: 'bi-folder2-open', warna: '#f59e0b', ...isi(p?.programNama) },
            ];
        },
    },
    mounted() {
        this.load();
    },
    beforeUnmount() {
        clearTimeout(this.salinTimer);
        clearTimeout(this.cariTimer);
        clearTimeout(this.tm);
    },
    methods: {
        /** Hari yang sudah lewat tidak bisa dipilih (masukan user 2 Okt 2026). */
        hariLampau(d) {
            const k = new Date();

            return d < new Date(k.getFullYear(), k.getMonth(), k.getDate());
        },
        /** Lampau, atau sebelum hari `mulai` ('YYYY-MM-DD…'). */
        sebelumMulai(d, mulai) {
            if (this.hariLampau(d)) return true;
            if (!mulai) return false;
            const [y, m, t] = String(mulai).slice(0, 10).split('-').map(Number);

            return d < new Date(y, m - 1, t);
        },
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k || '—'; },
        katIkon(k) { return { REKRUTMEN: 'bi-briefcase', MT: 'bi-mortarboard', INTERNSHIP: 'bi-backpack' }[k] || 'bi-diagram-3'; },
        katPill(k) { return { MT: 'pkg-pill--gold', INTERNSHIP: 'pkg-pill--green', REKRUTMEN: 'pkg-pill--sky' }[k] || 'pkg-pill--slate'; },
        initials(n) {
            if (!n) return 'SY';
            const p = String(n).trim().split(/\s+/);

            return ((p[0]?.[0] || '') + (p[1]?.[0] || p[0]?.[1] || '')).toUpperCase() || 'SY';
        },
        fmt(iso) { return tglJam(iso); },
        fmtTgl(iso) { return tgl(iso, ''); },
        angka(n) { return Number(n || 0).toLocaleString('id-ID'); },
        windowRingkas(b) {
            return b.masaBerlaku === 'EVERGREEN'
                ? `${tglPendek(b.buka)} — tanpa batas`
                : `${tglPendek(b.buka)} – ${tglPendek(b.tutup)}`;
        },

        gradLoker(i) { return GRAD[i % GRAD.length]; },
        ringLoker(i) { return RING[i % RING.length]; },
        /** Ikon ditebak dari nama posisi — sekadar penanda visual, bukan data. */
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
        persenKursi(l) { return l && l.kuota ? Math.min(100, Math.round((l.terisi / l.kuota) * 100)) : 0; },
        /** Jumlah pelamar satu loker — dari analitik, bukan dari baris posisi. */
        pelamarLoker(id) {
            const r = (this.analitik.loker || []).find((x) => x.id === id);

            return r ? r.pelamar : 0;
        },

        // ── KEADAAN ─────────────────────────────────────────────────────────
        keadaan(b) {
            if (!b) return { nada: 'diam', ...NADA.diam, judul: '—', sub: '' };

            const bungkus = (nada, judul, sub) => ({ nada, ...NADA[nada], judul, sub });

            if (b.statusPublish !== 'TERBIT') {
                return bungkus('diam', 'Tidak ditampilkan', 'Nyalakan tombol di kanan atas untuk menampilkannya di landing page.');
            }

            const kini = Date.now();
            const buka = b.buka ? new Date(String(b.buka).replace(' ', 'T')).getTime() : null;
            const tutup = b.masaBerlaku === 'EVERGREEN' || !b.tutup
                ? null
                : new Date(String(b.tutup).replace(' ', 'T')).getTime();

            if (buka && kini < buka) return bungkus('nanti', 'Terjadwal, belum dibuka', `Pendaftaran baru terbuka ${this.fmt(b.buka)}.`);
            if (tutup && kini > tutup) return bungkus('tutup', 'Sudah ditutup', `Jendela pendaftaran berakhir ${this.fmt(b.tutup)}.`);
            if (!(b.posisi || []).length) return bungkus('tutup', 'Ditampilkan, tanpa loker', 'Program ini belum punya posisi — tidak ada yang bisa dilamar.');
            if (!this.jmlAktif(b)) {
                return bungkus('tutup', 'Semua lokernya disembunyikan', `${b.posisi.length} loker tidak ditampilkan — landing tidak memuat apa pun dari terbitan ini.`);
            }

            const mati = this.jmlMati(b);

            return bungkus(
                'buka',
                mati ? `Terbuka — ${this.jmlAktif(b)} dari ${b.posisi.length} loker tampil` : 'Terbuka untuk pelamar',
                tutup ? `Menerima lamaran sampai ${this.fmt(b.tutup)}.` : 'Menerima lamaran tanpa batas waktu.',
            );
        },
        /** Seberapa jauh jendela pendaftaran sudah berjalan (0–100). */
        majuWindow(b) {
            if (b.masaBerlaku === 'EVERGREEN' || !b.buka || !b.tutup) return b.statusPublish === 'TERBIT' ? 100 : 0;

            const a = new Date(String(b.buka).replace(' ', 'T')).getTime();
            const z = new Date(String(b.tutup).replace(' ', 'T')).getTime();
            if (!Number.isFinite(a) || !Number.isFinite(z) || z <= a) return 0;

            return Math.max(0, Math.min(100, Math.round(((Date.now() - a) / (z - a)) * 100)));
        },
        sisaWaktu(b) {
            if (b.statusPublish !== 'TERBIT') return 'Belum tampil';
            if (b.masaBerlaku === 'EVERGREEN' || !b.tutup) return 'Tanpa batas';

            const z = new Date(String(b.tutup).replace(' ', 'T')).getTime();
            if (!Number.isFinite(z)) return '—';

            const sisa = z - Date.now();
            if (sisa <= 0) return 'Selesai';

            const hari = Math.floor(sisa / HARI_MS);
            if (hari >= 1) return `${hari} hari`;

            // Di bawah sehari, "0 hari" terbaca seperti sudah berakhir. Jamnya
            // disebut — dan itu justru hari yang paling perlu dilihat.
            return `${Math.max(1, Math.round(sisa / 3600000))} jam`;
        },

        // ── ANALITIK ────────────────────────────────────────────────────────
        ikonSumber(label) {
            const t = String(label || '').toLowerCase();
            if (t.includes('instagram')) return 'bi-instagram';
            if (t.includes('linkedin')) return 'bi-linkedin';
            if (t.includes('facebook')) return 'bi-facebook';
            if (t.includes('tiktok')) return 'bi-tiktok';
            if (t.includes('kampus') || t.includes('universitas') || t.includes('kuliah')) return 'bi-mortarboard-fill';
            if (t.includes('teman') || t.includes('referral') || t.includes('karyawan')) return 'bi-people-fill';
            if (t.includes('web') || t.includes('situs') || t.includes('website')) return 'bi-globe';
            if (t.includes('koran') || t.includes('poster') || t.includes('brosur')) return 'bi-newspaper';

            return 'bi-link-45deg';
        },
        /** Warna bilah sumber — berputar mengikuti deret palet EVO. */
        gradSumber(i) {
            const g = [
                'linear-gradient(90deg,#a78bfa,#7c3aed)',
                'linear-gradient(90deg,#93c5fd,#2563eb)',
                'linear-gradient(90deg,#a5b4fc,#4f46e5)',
                'linear-gradient(90deg,#6ee7b7,#059669)',
                'linear-gradient(90deg,#fbbf24,#d97706)',
                'linear-gradient(90deg,#f9a8d4,#db2777)',
                'linear-gradient(90deg,#cbd5e1,#64748b)',
            ];

            return g[i % g.length];
        },
        warnaSumber(i) {
            const bg = ['rgba(139,92,246,.11)', 'rgba(37,99,235,.1)', 'rgba(79,70,229,.1)', 'rgba(5,150,105,.1)', 'rgba(245,158,11,.12)', 'rgba(219,39,119,.1)', 'rgba(100,116,139,.11)'];
            const fg = ['#7c3aed', '#2563eb', '#4f46e5', '#059669', '#d97706', '#db2777', '#64748b'];

            return { background: bg[i % bg.length], color: fg[i % fg.length] };
        },

        /**
         * Salin tautan loker LANGSUNG dari kartu/baris daftar.
         *
         * URL-nya dirakit di sini dari host peramban, bukan diminta ke server:
         * satu klik salin tidak layak menunggu perjalanan jaringan, dan
         * bentuknya sama persis dengan yang dikembalikan endpoint detail.
         */
        async salinTautanLoker(l) {
            if (!l || !this.terpilih) return;

            const tautan = `${window.location.origin}/karir/landing-page/lowongan/PB-${this.terpilih.kode}-${l.id}`;
            const ok = await this.keClipboard(tautan);

            if (!ok) return this.notice('Peramban menolak menyalin. Buka detail lokernya — tautannya tercetak di sana.');

            this.idTersalin = l.id;
            this.notice(`Tautan "${l.posisi}" tersalin — siap ditempel ke LinkedIn.`);
            clearTimeout(this.salinTimer);
            this.salinTimer = setTimeout(() => (this.idTersalin = null), 2200);
        },

        pilihRentang(r) {
            if (this.rentang === r) return;
            this.rentang = r;
            this.muatAnalitik2();
        },
        async muatAnalitik2() {
            if (!this.sel) { this.analitik = {}; return; }

            this.muatAnalitik = true;
            try {
                const res = await axios.get(`${API}/${this.sel}/analitik`, { ...CFG, params: { hari: this.rentang } });
                this.analitik = res.data.result || {};
            } catch (e) {
                this.analitik = {};
                this.notice('Gagal memuat analitik.');
            } finally {
                this.muatAnalitik = false;
            }
        },

        // ── DAFTAR ──────────────────────────────────────────────────────────
        async load() {
            this.loading = true;
            try {
                const params = {
                    q: this.filters.q || undefined,
                    kategori: this.filters.kategori || undefined,
                    dari: this.filters.rentang?.[0] || undefined,
                    sampai: this.filters.rentang?.[1] || undefined,
                };
                const r = (await axios.get(API, { ...CFG, params })).data.result || {};
                this.list = r.data || [];
                // Daftar kategori beserta hitungannya datang dari server dan
                // SUDAH disaring hak akses — jadi tidak pernah menawarkan
                // kategori yang tak boleh dibuka pengguna ini.
                this.kategoriOpsi = r.kategori || [];
                this.akses = r.akses || { lingkupPic: 'SEMUA', kodeSaya: null };

                const masihAda = (id) => !!id && this.list.some((b) => b.id === id);
                this.sel = [this.fokus, this.sel].find(masihAda)
                    || (layarLebar() ? (this.grup[0]?.items[0]?.id ?? null) : null);
                this.fokus = null;
            } catch (e) {
                this.notice('Gagal memuat data pembukaan.');
            } finally {
                this.loading = false;
            }
        },
        cariDebounce() {
            if (this.cariTimer) clearTimeout(this.cariTimer);
            this.cariTimer = setTimeout(() => this.load(), 400);
        },
        pilih(id) { this.sel = id; },
        resetFilter() {
            this.filters = { q: '', kategori: null, masa: null, pic: null, rentang: null };
            this.sheetOpen = false;
            this.load();
        },
        keHalaman(h) {
            const t = this.totalHalaman;
            this.halaman = Math.min(Math.max(1, h), t);
            // Daftarnya bergulir sendiri; halaman baru harus mulai dari atas,
            // bukan dari posisi gulung halaman sebelumnya.
            this.$nextTick(() => {
                const el = this.$el?.querySelector('.pbk-list');
                if (el) el.scrollTop = 0;
            });
        },
        gantiTab(k) {
            this.tab = k;
            // Pindah tab selalu kembali ke daftar loker. Membiarkan detail lama
            // terbuka berarti kembali ke tab Loker memperlihatkan loker yang
            // sudah tidak dilihat siapa pun sejak beberapa klik lalu.
            if (k !== 'loker') this.tutupLoker();
        },

        // ── LOKER ───────────────────────────────────────────────────────────
        totalKuota(b) { return (b.posisi || []).filter((l) => l.aktif).reduce((n, l) => n + (Number(l.kuota) || 0), 0); },
        jmlAktif(b) { return (b.posisi || []).filter((l) => l.aktif).length; },
        jmlMati(b) { return (b.posisi || []).filter((l) => !l.aktif).length; },

        /** Buka panel detail satu loker (dari kartu, baris, atau sel heatmap). */
        async bukaLoker(l) {
            if (!l || !this.terpilih) return;

            this.tab = 'loker';
            this.lokerId = l.id;
            this.mppLoker = null;
            this.corong = { total: 0, tahap: [] };
            this.skr = null;
            this.skrRinci = false;
            this.tautanLoker = '';
            this.tautanTersalin = false;
            this.muatLoker = true;

            try {
                const res = await axios.get(`${API}/${this.terpilih.id}/loker/${l.id}`, CFG);
                const r = res.data.result || {};
                this.mppLoker = r.mpp || null;
                this.corong = r.corong || { total: 0, tahap: [] };
                this.skr = r.skrining || null;
                this.tautanLoker = r.tautan || '';
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memuat detail loker.');
            } finally {
                this.muatLoker = false;
            }
        },
        tutupLoker() {
            this.lokerId = null;
            this.mppLoker = null;
            this.corong = { total: 0, tahap: [] };
            this.skr = null;
            this.skrRinci = false;
            this.tautanLoker = '';
            this.tautanTersalin = false;
            this.idTersalin = null;
        },
        /**
         * Salin tautan publik loker ke papan klip.
         *
         * navigator.clipboard hanya tersedia di konteks aman (https / localhost);
         * di http biasa ia `undefined` dan tombolnya akan diam tanpa pesan.
         * Cadangannya textarea + execCommand — usang, tapi itulah satu-satunya
         * yang jalan di sana.
         */
        async salinTautan() {
            if (!this.tautanLoker) return;

            const ok = await this.keClipboard(this.tautanLoker);

            if (!ok) return this.notice('Peramban menolak menyalin. Salin manual dari tautan di bawah judul.');

            this.tautanTersalin = true;
            this.notice('Tautan loker tersalin — siap ditempel ke LinkedIn.');
            clearTimeout(this.salinTimer);
            this.salinTimer = setTimeout(() => (this.tautanTersalin = false), 2200);
        },

        /**
         * Tulis teks ke papan klip, dengan cadangan untuk konteks tidak aman.
         *
         * navigator.clipboard hanya ada di https / localhost; di http biasa ia
         * `undefined` dan tombolnya akan diam tanpa pesan. Cadangannya textarea
         * + execCommand — usang, tapi itulah satu-satunya yang jalan di sana.
         */
        async keClipboard(teks) {
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(teks);

                    return true;
                }
            } catch (e) {
                // jatuh ke cadangan di bawah
            }

            try {
                const ta = document.createElement('textarea');
                ta.value = teks;
                ta.setAttribute('readonly', '');
                ta.style.cssText = 'position:fixed;top:-1000px;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);

                return ok;
            } catch (e) {
                return false;
            }
        },

        setPosisi(b, l, aktif) { return this.kirimPosisi(b, [l.id], aktif); },
        setSemuaPosisi(b, aktif) {
            const ids = (b.posisi || []).filter((l) => !!l.aktif !== aktif).map((l) => l.id);
            if (!ids.length) return Promise.resolve();

            return this.kirimPosisi(b, ids, aktif);
        },
        async kirimPosisi(b, ids, aktif) {
            if (this.sibukPosisi) return;

            const sebelum = new Map((b.posisi || []).map((l) => [l.id, l.aktif]));
            (b.posisi || []).forEach((l) => { if (ids.includes(l.id)) l.aktif = aktif; });

            this.sibukPosisi = true;
            try {
                const res = await axios.patch(`${API}/${b.id}/posisi`, { posisiIds: ids, aktif }, CFG);
                this.notice(res.data?.message || 'Loker diperbarui.');
                await this.load();
            } catch (e) {
                (b.posisi || []).forEach((l) => { if (sebelum.has(l.id)) l.aktif = sebelum.get(l.id); });
                this.notice(e.response?.data?.message || 'Gagal mengubah loker.');
            } finally {
                this.sibukPosisi = false;
            }
        },

        // ── BORANG ──────────────────────────────────────────────────────────
        blankForm() { return { program: '', masaBerlaku: 'BERBATAS', buka: null, tutup: null, statusPublish: 'TERBIT' }; },
        onMasa() { if (this.form.masaBerlaku === 'EVERGREEN') this.form.tutup = null; },
        async loadPrograms() {
            this.programsLoading = true;
            this.programs = [];
            try {
                this.programs = (await axios.get(`${API}/programs`, CFG)).data.result || [];
            } catch (e) {
                this.programs = [];
            } finally {
                this.programsLoading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = this.blankForm();
            this.programCari = '';
            this.loadPrograms();
            this.show = true;
        },
        openEdit(b) {
            this.editingId = b.id;
            this.form = {
                program: b.program || '',
                masaBerlaku: b.masaBerlaku || 'BERBATAS',
                buka: b.buka || null,
                tutup: b.tutup || null,
                statusPublish: b.statusPublish || 'TERBIT',
            };
            this.programCari = '';
            this.loadPrograms();
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.program) return this.notice('Program wajib dipilih.');
            if (!this.form.masaBerlaku) return this.notice('Masa berlaku wajib dipilih.');

            this.saving = true;
            const payload = {
                program: this.form.program,
                masaBerlaku: this.form.masaBerlaku,
                buka: this.form.buka,
                tutup: this.form.masaBerlaku === 'EVERGREEN' ? null : this.form.tutup,
                statusPublish: this.form.statusPublish,
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Pembukaan diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Program dibuka.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setPublish(b, v) {
            const prev = b.statusPublish;
            b.statusPublish = v ? 'TERBIT' : 'DRAFT';
            try {
                await axios.patch(`${API}/${b.id}/toggle`, { aktif: v }, CFG);
                this.notice(`"${b.programNama}" ${v ? 'ditampilkan di landing page' : 'tidak lagi ditampilkan'}.`);
            } catch (e) {
                b.statusPublish = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(b) { this.delTarget = b; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;

            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Pembukaan dihapus.');
                this.delShow = false;
                this.delTarget = null;
                this.sel = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },
        notice(x) {
            this.toast = x;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3000);
        },
    },
    watch: {
        // Menyaring lalu tetap berada di halaman 7 memperlihatkan daftar kosong
        // yang terbaca seperti "tidak ada hasil". Tiap penyaringan kembali ke
        // halaman pertama.
        cakupan() { this.halaman = 1; },
        urutTurun() { this.halaman = 1; },
        'list.length'() { this.halaman = 1; },
        'filters.masa'() { this.halaman = 1; },
        'filters.pic'() { this.halaman = 1; },

        // Analitik dimuat mengikuti terbitan yang sedang dibuka — bukan sekali
        // saat halaman terbit. `immediate` tidak dipakai: saat komponen dipasang
        // `sel` masih null, dan permintaannya akan terbuang.
        sel(baru) {
            this.tab = 'analitik';
            this.tutupLoker();
            if (baru) this.muatAnalitik2();
            else this.analitik = {};
        },
    },
};
</script>
<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   PEMBUKAAN PROGRAM — mengikuti rancangan v1 (docs/refrences/v1).
   Panel kiri: daftar terbitan. Panel kanan: analitik + loker.
   ═══════════════════════════════════════════════════════════════════════════ */

@keyframes pbkRise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
@keyframes pbkFade { from { opacity: 0; } to { opacity: 1; } }
@keyframes pbkGrow { from { width: 0; } }
@keyframes pbkPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,.5); } 70% { box-shadow: 0 0 0 8px rgba(99,102,241,0); } }
@keyframes pbkSheen { 0% { transform: translateX(-170%) skewX(-18deg); } 30%, 100% { transform: translateX(340%) skewX(-18deg); } }

.pbk {
    min-height: 100%;
    padding: clamp(14px, 2vw, 22px) clamp(12px, 2vw, 24px) clamp(28px, 4vw, 42px);
    background:
        radial-gradient(1200px 600px at 12% -8%, rgba(139,92,246,.1), transparent 60%),
        radial-gradient(1000px 500px at 105% 4%, rgba(99,102,241,.1), transparent 62%),
        linear-gradient(180deg, #f8fafc, #eef2f8 60%, #f8fafc);
}

/* ── KEPALA ──────────────────────────────────────────────────────────────── */
.pbk-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; flex-wrap: wrap; animation: pbkRise .6s cubic-bezier(.22,1,.36,1) both; }
.pbk-top__l { min-width: 0; }
.pbk-crumb { display: flex; align-items: center; gap: 8px; font-size: 11.5px; font-weight: 700; color: #94a3b8; }
.pbk-crumb i { font-size: 8px; }
.pbk-crumb .on { color: #4f46e5; }
.pbk-title { margin: 8px 0 0; display: flex; align-items: center; gap: 12px; font-size: clamp(1.5rem, 3vw, 2.05rem); font-weight: 800; letter-spacing: -.03em; color: #0f172a; }
.pbk-title__ico { width: 40px; height: 40px; flex-shrink: 0; border-radius: 13px; background: linear-gradient(135deg,#8b5cf6,#4f46e5); display: grid; place-items: center; color: #fff; font-size: 18px; box-shadow: 0 12px 26px rgba(99,102,241,.3); }
.pbk-lead { margin: 9px 0 0; max-width: 560px; font-size: 13.5px; line-height: 1.6; color: #64748b; text-wrap: pretty; }
.pbk-cta { position: relative; overflow: hidden; display: inline-flex; align-items: center; gap: 9px; min-height: 44px; padding: 12px 20px; border: none; border-radius: 13px; cursor: pointer; font: inherit; font-size: 13.5px; font-weight: 700; color: #fff; background: linear-gradient(135deg,#8b5cf6,#6366f1 55%,#4f46e5); box-shadow: 0 14px 30px rgba(99,102,241,.32); transition: all .26s cubic-bezier(.22,1,.36,1); }
.pbk-cta::before { content: ''; position: absolute; top: 0; left: 0; height: 100%; width: 55%; background: linear-gradient(90deg, transparent, rgba(255,255,255,.45), transparent); transform: translateX(-170%) skewX(-18deg); animation: pbkSheen 5.5s ease-in-out 1.6s infinite; }
.pbk-cta:hover { transform: translateY(-2px); box-shadow: 0 20px 40px rgba(99,102,241,.42); }

/* ── KERANGKA DUA PANEL ──────────────────────────────────────────────────── */
.pbk-body { display: flex; flex-wrap: wrap; gap: clamp(12px, 1.6vw, 18px); margin-top: clamp(16px, 2.4vw, 24px); align-items: flex-start; }

.pbk-aside { flex: 1 1 300px; min-width: 270px; max-width: 392px; position: sticky; top: 14px; display: flex; flex-direction: column; min-height: 0; border-radius: 22px; background: rgba(255,255,255,.86); backdrop-filter: blur(18px); border: 1px solid rgba(255,255,255,.95); box-shadow: 0 20px 52px rgba(15,23,42,.09); overflow: hidden; animation: pbkRise .65s cubic-bezier(.22,1,.36,1) .06s both; }
.pbk-aside__head { padding: 15px 16px 13px; border-bottom: 1px solid rgba(226,232,240,.85); }
.pbk-fold { display: flex; align-items: center; gap: 9px; }
.pbk-fold__ico { width: 30px; height: 30px; flex-shrink: 0; border-radius: 9px; background: linear-gradient(135deg,#fbbf24,#d97706); display: grid; place-items: center; color: #fff; font-size: 13px; }
.pbk-fold__id { min-width: 0; flex: 1; line-height: 1.25; }
.pbk-fold__t { font-size: 13.5px; font-weight: 800; letter-spacing: -.01em; color: #0f172a; }
.pbk-fold__s { font-size: 10.5px; color: #94a3b8; }
.pbk-sort { flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; padding: 8px 10px; border: 1px solid rgba(226,232,240,.95); border-radius: 10px; background: #fff; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800; color: #475569; transition: all .22s ease; }
.pbk-sort:hover { color: #4f46e5; border-color: rgba(99,102,241,.4); }

.pbk-search { position: relative; margin-top: 12px; }
.pbk-search > i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #8b5cf6; font-size: 12.5px; pointer-events: none; }
.pbk-search input { width: 100%; min-height: 42px; padding: 11px 13px 11px 35px; border-radius: 12px; border: 1px solid rgba(226,232,240,.95); background: rgba(248,250,252,.9); font: inherit; font-size: 13px; color: #0f172a; outline: none; transition: all .25s ease; }
.pbk-search input:focus { border-color: #6366f1; background: #fff; box-shadow: 0 0 0 3.5px rgba(99,102,241,.13); }

.pbk-scopes { display: flex; gap: 5px; margin-top: 10px; padding: 4px; border-radius: 11px; background: rgba(241,245,249,.9); }
.pbk-scope { flex: 1; appearance: none; border: none; background: transparent; cursor: pointer; padding: 7px 6px; border-radius: 8px; font: inherit; font-size: 11px; font-weight: 800; color: #64748b; transition: all .22s ease; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pbk-scope span { opacity: .6; }
.pbk-scope:hover { color: #4f46e5; }
.pbk-scope.on { background: #fff; color: #4f46e5; box-shadow: 0 2px 8px rgba(15,23,42,.08); }

.pbk-list { flex: 1; overflow-y: auto; max-height: min(56vh, 520px); padding: 8px; }
.pbk-group { margin-bottom: 6px; }
.pbk-group__head { display: flex; align-items: center; gap: 8px; padding: 9px 9px 7px; }
.pbk-group__head > i { font-size: 8px; color: #cbd5e1; }
.pbk-group__lbl { font-size: 9.5px; font-weight: 800; letter-spacing: .16em; color: #94a3b8; }
.pbk-group__n { padding: 1.5px 7px; border-radius: 999px; background: rgba(241,245,249,.95); font-size: 9.5px; font-weight: 800; color: #64748b; }
.pbk-group__line { flex: 1; height: 1px; background: rgba(226,232,240,.85); }

.pbk-item { position: relative; width: 100%; display: flex; align-items: center; gap: 11px; padding: 10px 11px; border: 1px solid transparent; border-radius: 13px; background: transparent; cursor: pointer; font: inherit; text-align: left; transition: background .22s ease, border-color .22s ease; }
.pbk-item:hover { background: rgba(248,250,252,.95); }
.pbk-item.on { background: rgba(99,102,241,.07); border-color: rgba(99,102,241,.22); }
.pbk-item__accent { position: absolute; left: 0; top: 9px; bottom: 9px; width: 3px; border-radius: 0 3px 3px 0; background: linear-gradient(180deg,#8b5cf6,#4f46e5); opacity: 0; transition: opacity .25s ease; }
.pbk-item.on .pbk-item__accent { opacity: 1; }
.pbk-item__ico { flex-shrink: 0; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 14px; }
.pbk-item__ico.is-buka { background: rgba(16,185,129,.11); color: #059669; }
.pbk-item__ico.is-nanti, .pbk-item__ico.is-diam { background: rgba(245,158,11,.12); color: #d97706; }
.pbk-item__ico.is-tutup { background: rgba(100,116,139,.11); color: #64748b; }
.pbk-item__body { flex: 1; min-width: 0; }
.pbk-item__r1 { display: flex; align-items: center; gap: 7px; }
/* Nama program kerap panjang. Dipotong dengan elipsis; utuhnya ada di title. */
.pbk-item__name { flex: 1; min-width: 0; font-size: 13px; font-weight: 800; letter-spacing: -.01em; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pbk-item.on .pbk-item__name { color: #4338ca; }
.pbk-item__dot { flex-shrink: 0; width: 7px; height: 7px; border-radius: 50%; }
.pbk-item__r2 { display: flex; align-items: center; gap: 7px; margin-top: 3px; font-size: 10.5px; color: #94a3b8; min-width: 0; }
.pbk-item__meta { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pbk-item__r3 { display: flex; align-items: center; gap: 6px; margin-top: 6px; min-width: 0; }
.pbk-item__pill { flex-shrink: 0; padding: 2.5px 7px; border-radius: 6px; font-size: 9.5px; font-weight: 800; }
.pbk-item__pill.is-buka { background: rgba(16,185,129,.12); color: #047857; }
.pbk-item__pill.is-nanti, .pbk-item__pill.is-diam { background: rgba(245,158,11,.14); color: #b45309; }
.pbk-item__pill.is-tutup { background: rgba(100,116,139,.12); color: #475569; }
.pbk-item__range { font-size: 9.5px; font-weight: 600; color: #a8b1c1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pbk-item__num { flex-shrink: 0; text-align: right; }
.pbk-item__val { display: block; font-size: 14px; font-weight: 800; letter-spacing: -.02em; color: #0f172a; }
.pbk-item.on .pbk-item__val { color: #4338ca; }
.pbk-item__cap { display: block; font-size: 9px; font-weight: 700; letter-spacing: .1em; color: #a8b1c1; }

.pbk-none { padding: 38px 18px; text-align: center; animation: pbkFade .3s ease both; }
.pbk-none__ico { display: inline-grid; place-items: center; width: 52px; height: 52px; border-radius: 50%; background: rgba(99,102,241,.09); color: #8b5cf6; font-size: 21px; }
.pbk-none__t { margin-top: 13px; font-size: 13.5px; font-weight: 800; color: #0f172a; }
.pbk-none__s { margin-top: 5px; font-size: 12px; color: #94a3b8; }

.pbk-aside__foot { padding: 13px 16px 15px; border-top: 1px solid rgba(226,232,240,.85); background: rgba(248,250,252,.7); }

/* ── PAGINASI PANEL KIRI ─────────────────────────────────────────────────── */
.pbk-pager { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pbk-pager__nav { display: grid; place-items: center; width: 28px; height: 28px; flex-shrink: 0; border: 1px solid rgba(226,232,240,.95); border-radius: 9px; background: #fff; cursor: pointer; color: #475569; font-size: 12px; transition: all .2s ease; }
.pbk-pager__nav:hover:not(:disabled) { color: #4f46e5; border-color: rgba(99,102,241,.45); }
.pbk-pager__nav:disabled { opacity: .38; cursor: not-allowed; }
.pbk-pager__nums { display: flex; align-items: center; gap: 4px; flex: 1; min-width: 0; overflow: hidden; }
.pbk-pager__n { min-width: 26px; height: 28px; padding: 0 6px; border: none; border-radius: 8px; background: transparent; cursor: pointer; font: inherit; font-size: 11.5px; font-weight: 800; color: #64748b; transition: all .2s ease; }
.pbk-pager__n:hover:not(:disabled):not(.on) { background: rgba(99,102,241,.08); color: #4f46e5; }
.pbk-pager__n.on { background: linear-gradient(135deg,#8b5cf6,#4f46e5); color: #fff; box-shadow: 0 6px 14px rgba(99,102,241,.28); }
.pbk-pager__n.is-gap { cursor: default; color: #cbd5e1; min-width: 14px; padding: 0; }
.pbk-pager__info { width: 100%; text-align: center; font-size: 10.5px; font-weight: 700; color: #94a3b8; }

/* ── PANEL KANAN ─────────────────────────────────────────────────────────── */
.pbk-main { flex: 999 1 520px; min-width: 280px; display: flex; flex-direction: column; gap: clamp(12px, 1.6vw, 18px); }

.pbk-hero { position: relative; overflow: hidden; border-radius: 22px; padding: clamp(16px, 2.4vw, 24px); background: rgba(255,255,255,.88); backdrop-filter: blur(18px); border: 1px solid rgba(255,255,255,.95); box-shadow: 0 20px 52px rgba(15,23,42,.09); animation: pbkRise .65s cubic-bezier(.22,1,.36,1) .1s both; }
.pbk-hero__glow { position: absolute; top: -60px; right: -40px; width: 220px; height: 220px; border-radius: 50%; pointer-events: none; }
.pbk-hero__row { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.pbk-hero__l { min-width: 0; flex: 1; }
.pbk-hero__crumb { display: flex; align-items: center; gap: 9px; font-size: 11px; font-weight: 700; color: #94a3b8; flex-wrap: wrap; }
.pbk-hero__crumb > i.bi-folder2-open { color: #f59e0b; }
.pbk-hero__crumb .on { color: #4f46e5; font-weight: 800; }
.pbk-back { display: none; width: 30px; height: 30px; border: 1px solid rgba(226,232,240,.95); border-radius: 9px; background: #fff; color: #475569; cursor: pointer; }
.pbk-hero__title { margin: 9px 0 0; font-size: clamp(1.3rem, 2.6vw, 1.8rem); font-weight: 800; letter-spacing: -.03em; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pbk-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; min-width: 0; }
.pbk-chip { display: inline-flex; align-items: center; gap: 7px; max-width: 100%; padding: 6px 12px; border-radius: 999px; font-size: 11px; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pbk-chip__dot { flex-shrink: 0; width: 7px; height: 7px; border-radius: 50%; background: currentColor; animation: pbkPulse 2.2s ease-out infinite; }
.pbk-chip.is-buka { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.26); color: #047857; }
.pbk-chip.is-nanti, .pbk-chip.is-diam { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.3); color: #b45309; }
.pbk-chip.is-tutup { background: rgba(100,116,139,.1); border: 1px solid rgba(100,116,139,.26); color: #334155; }
.pbk-chip.is-plain { background: rgba(241,245,249,.95); color: #475569; font-weight: 700; }

.pbk-hero__act { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; flex-shrink: 0; }
.pbk-pub { display: inline-flex; align-items: center; gap: 10px; min-height: 42px; padding: 9px 14px; border: 1px solid rgba(100,116,139,.24); border-radius: 13px; background: rgba(241,245,249,.8); cursor: pointer; font: inherit; font-size: 12.5px; font-weight: 800; color: #475569; transition: all .26s cubic-bezier(.22,1,.36,1); }
.pbk-pub.on { border-color: rgba(16,185,129,.3); background: rgba(16,185,129,.08); color: #047857; }
.pbk-pub__sw { position: relative; width: 38px; height: 22px; border-radius: 999px; background: rgba(148,163,184,.45); transition: background .3s ease; }
.pbk-pub.on .pbk-pub__sw { background: linear-gradient(135deg,#34d399,#059669); }
.pbk-pub__knob { position: absolute; top: 2.5px; left: 2.5px; width: 17px; height: 17px; border-radius: 50%; background: #fff; box-shadow: 0 2px 5px rgba(15,23,42,.25); transition: transform .3s cubic-bezier(.34,1.4,.64,1); }
.pbk-pub.on .pbk-pub__knob { transform: translateX(16px); }
.pbk-ib { width: 42px; height: 42px; border: 1px solid rgba(226,232,240,.95); border-radius: 12px; background: #fff; cursor: pointer; color: #475569; font-size: 14px; transition: all .22s ease; }
.pbk-ib:hover { color: #4f46e5; border-color: rgba(99,102,241,.4); }
.pbk-ib.is-danger { border-color: rgba(220,38,38,.24); background: rgba(220,38,38,.05); color: #b91c1c; }
.pbk-ib.is-danger:hover { background: rgba(220,38,38,.12); color: #b91c1c; border-color: rgba(220,38,38,.35); }

.pbk-win { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-top: 20px; padding: 15px 17px; border-radius: 17px; }
.pbk-win.is-buka { background: rgba(16,185,129,.07); border: 1px solid rgba(16,185,129,.26); }
.pbk-win.is-nanti, .pbk-win.is-diam { background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.3); }
.pbk-win.is-tutup { background: rgba(100,116,139,.08); border: 1px solid rgba(100,116,139,.26); }
.pbk-win__ico { width: 38px; height: 38px; flex-shrink: 0; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 16px; }
.pbk-win.is-buka .pbk-win__ico { background: linear-gradient(135deg,#34d399,#059669); }
.pbk-win.is-nanti .pbk-win__ico, .pbk-win.is-diam .pbk-win__ico { background: linear-gradient(135deg,#fbbf24,#d97706); }
.pbk-win.is-tutup .pbk-win__ico { background: linear-gradient(135deg,#cbd5e1,#64748b); }
.pbk-win__l { flex: 1 1 220px; min-width: 0; }
.pbk-win__t { font-size: 13.5px; font-weight: 800; }
.pbk-win.is-buka .pbk-win__t { color: #065f46; }
.pbk-win.is-nanti .pbk-win__t, .pbk-win.is-diam .pbk-win__t { color: #78350f; }
.pbk-win.is-tutup .pbk-win__t { color: #334155; }
.pbk-win__s { margin-top: 3px; font-size: 12px; color: #64748b; }
.pbk-win__bar { margin-top: 10px; height: 7px; border-radius: 999px; background: rgba(148,163,184,.2); overflow: hidden; }
.pbk-win__bar > span { display: block; height: 100%; border-radius: 999px; animation: pbkGrow 1.4s cubic-bezier(.22,1,.36,1) both; }
.pbk-win.is-buka .pbk-win__bar > span { background: linear-gradient(135deg,#34d399,#059669); }
.pbk-win.is-nanti .pbk-win__bar > span, .pbk-win.is-diam .pbk-win__bar > span { background: linear-gradient(135deg,#fbbf24,#d97706); }
.pbk-win.is-tutup .pbk-win__bar > span { background: linear-gradient(135deg,#cbd5e1,#64748b); }
.pbk-win__stats { display: flex; gap: 8px; flex-wrap: wrap; }
.pbk-stat { padding: 9px 13px; border-radius: 12px; background: rgba(255,255,255,.9); border: 1px solid rgba(226,232,240,.9); text-align: center; min-width: 78px; }
.pbk-stat span { display: block; font-size: 9px; font-weight: 800; letter-spacing: .12em; color: #94a3b8; }
.pbk-stat b { display: block; margin-top: 3px; font-size: 12.5px; font-weight: 800; color: #0f172a; }
.pbk-stat.is-hi { background: linear-gradient(135deg,#818cf8,#4f46e5); border-color: transparent; box-shadow: 0 10px 22px rgba(15,23,42,.12); }
.pbk-stat.is-hi span { color: rgba(255,255,255,.75); }
.pbk-stat.is-hi b { color: #fff; }

/* ── TAB ─────────────────────────────────────────────────────────────────── */
.pbk-tabs { display: flex; gap: 6px; padding: 5px; border-radius: 15px; background: rgba(255,255,255,.8); backdrop-filter: blur(14px); border: 1px solid rgba(226,232,240,.9); box-shadow: 0 10px 26px rgba(15,23,42,.05); overflow-x: auto; }
/* Tiap tab mengambil setengah lebar — persis seperti rancangan (flex:1 1 150px).
   Dua tab kecil di kiri menyisakan lajur kosong panjang yang terlihat seperti
   kelupaan; melebarkannya juga memperbesar sasaran kliknya. */
.pbk-tab { display: inline-flex; flex: 1 1 150px; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 10px 16px; border: none; border-radius: 11px; background: transparent; cursor: pointer; font: inherit; font-size: 12.5px; font-weight: 800; color: #64748b; white-space: nowrap; transition: all .28s cubic-bezier(.22,1,.36,1); }
.pbk-tab:hover { color: #4f46e5; }
.pbk-tab.on { background: linear-gradient(135deg,#8b5cf6,#4f46e5); color: #fff; box-shadow: 0 10px 24px rgba(99,102,241,.3); }

.pbk-sec { display: flex; flex-direction: column; gap: clamp(12px, 1.6vw, 18px); animation: pbkFade .35s ease both; }

/* ── KARTU METRIK ────────────────────────────────────────────────────────── */
.pbk-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(178px, 100%), 1fr)); gap: clamp(10px, 1.4vw, 14px); }
.pbk-metric { position: relative; overflow: hidden; border-radius: 19px; padding: 16px 17px; background: rgba(255,255,255,.88); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,.95); box-shadow: 0 14px 38px rgba(15,23,42,.07); transition: all .3s cubic-bezier(.22,1,.36,1); }
.pbk-metric:hover { transform: translateY(-3px); box-shadow: 0 24px 54px rgba(15,23,42,.12); }
.pbk-metric__glow { position: absolute; top: -26px; right: -26px; width: 98px; height: 98px; border-radius: 50%; }
.pbk-metric__top { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.pbk-metric__ico { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; color: #fff; font-size: 14px; }
.pbk-metric__delta { display: inline-flex; align-items: center; gap: 4px; padding: 3.5px 8px; border-radius: 999px; font-size: 10px; font-weight: 800; }
.pbk-metric__delta i { font-size: 9px; }
.pbk-metric__delta.is-up { background: rgba(16,185,129,.12); color: #047857; }
.pbk-metric__delta.is-down { background: rgba(239,68,68,.1); color: #b91c1c; }
.pbk-metric__delta.is-flat { background: rgba(241,245,249,.95); color: #94a3b8; font-weight: 700; }
.pbk-metric__lbl { position: relative; margin-top: 13px; font-size: 11px; font-weight: 700; color: #64748b; }
.pbk-metric__val { position: relative; margin-top: 3px; font-size: clamp(1.5rem, 2.6vw, 1.85rem); font-weight: 800; letter-spacing: -.035em; color: #0f172a; }
.pbk-metric__sub { position: relative; margin-top: 9px; font-size: 11px; font-weight: 600; color: #94a3b8; }
.pbk-spark { position: relative; margin-top: 8px; }

/* ── KARTU GRAFIK ────────────────────────────────────────────────────────── */
.pbk-charts { display: flex; flex-wrap: wrap; gap: clamp(12px, 1.6vw, 18px); }
.pbk-card { flex: 1 1 100%; min-width: 260px; border-radius: 22px; padding: clamp(16px, 2.2vw, 22px); background: rgba(255,255,255,.88); backdrop-filter: blur(18px); border: 1px solid rgba(255,255,255,.95); box-shadow: 0 18px 46px rgba(15,23,42,.08); }
.pbk-card--wide { flex: 999 1 420px; min-width: 270px; }
.pbk-card--side { flex: 1 1 260px; min-width: 250px; }
.pbk-card--half { flex: 1 1 280px; min-width: 260px; }
.pbk-card--flush { padding: 0; overflow: hidden; }
.pbk-card--ring { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; }
.pbk-card__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
.pbk-card__t { font-size: 14.5px; font-weight: 800; letter-spacing: -.02em; color: #0f172a; }
.pbk-card__t--self { align-self: flex-start; width: 100%; }
.pbk-card__s { margin-top: 3px; font-size: 12px; color: #94a3b8; }

.pbk-ranges { display: flex; gap: 4px; padding: 4px; border-radius: 11px; background: rgba(241,245,249,.9); }
.pbk-range { appearance: none; border: none; background: transparent; cursor: pointer; padding: 6px 11px; border-radius: 8px; font: inherit; font-size: 11px; font-weight: 800; color: #64748b; transition: all .2s ease; }
.pbk-range:hover { color: #4f46e5; }
.pbk-range.on { background: #fff; color: #4f46e5; box-shadow: 0 2px 8px rgba(15,23,42,.08); }

.pbk-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 34px 16px; text-align: center; font-size: 12.5px; font-weight: 600; color: #94a3b8; }
.pbk-empty > i { font-size: 22px; color: #cbd5e1; }

/* ── CINCIN KUOTA (donat Highcharts + label di bawahnya) ─────────────────── */
.pbk-ring__tags { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
.pbk-tag { display: inline-flex; align-items: center; gap: 7px; padding: 7px 13px; border-radius: 11px; background: rgba(241,245,249,.95); font-size: 11.5px; font-weight: 800; color: #475569; }
.pbk-tag i { font-size: 11px; }
.pbk-tag.is-on { background: rgba(99,102,241,.09); border: 1px solid rgba(99,102,241,.22); color: #4338ca; }

/* ── TABEL LOKER ─────────────────────────────────────────────────────────── */
.pbk-lok__head { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; padding: 17px 19px; border-bottom: 1px solid rgba(226,232,240,.85); }
.pbk-massal { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 11px 19px; border-bottom: 1px solid rgba(226,232,240,.85); background: rgba(248,250,252,.6); }
.pbk-massal__t { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #64748b; margin-right: auto; }
.pbk-massal__btn { appearance: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border: 1px solid rgba(16,185,129,.3); border-radius: 9px; background: #fff; color: #047857; font: inherit; font-size: 11.5px; font-weight: 800; transition: all .16s; }
.pbk-massal__btn:hover:not(:disabled) { background: rgba(16,185,129,.08); }
.pbk-massal__btn.is-mati { border-color: rgba(220,38,38,.28); color: #b91c1c; }
.pbk-massal__btn.is-mati:hover:not(:disabled) { background: rgba(220,38,38,.06); }
.pbk-massal__btn:disabled { opacity: .4; cursor: not-allowed; }

.pbk-tblwrap { overflow-x: auto; }
.pbk-tbl { width: 100%; min-width: 680px; border-collapse: collapse; }
.pbk-tbl thead tr { background: rgba(248,250,252,.9); }
.pbk-tbl th { padding: 12px 14px; text-align: left; font-size: 9.5px; font-weight: 800; letter-spacing: .14em; color: #94a3b8; }
.pbk-tbl th:first-child, .pbk-tbl td:first-child { padding-left: 19px; }
.pbk-tbl th:last-child, .pbk-tbl td:last-child { padding-right: 19px; }
.pbk-tbl td { padding: 15px 14px; border-top: 1px solid rgba(226,232,240,.8); }
.pbk-tbl tbody tr { transition: background .22s ease; }
.pbk-tbl tbody tr:hover { background: rgba(99,102,241,.04); }
/* Diredupkan, bukan disembunyikan: barisnya memuat satu-satunya jalan untuk
   menampilkannya kembali. */
.pbk-tbl tbody tr.is-mati { opacity: .5; }
.pbk-tbl tbody tr.is-mati:hover { opacity: .78; }
.ta-r { text-align: right; }
.pbk-lok__pos { display: flex; align-items: center; gap: 11px; min-width: 0; }
.pbk-lok__ico { width: 32px; height: 32px; flex-shrink: 0; border-radius: 10px; background: linear-gradient(135deg,#818cf8,#4f46e5); display: grid; place-items: center; color: #fff; font-size: 13px; }
.pbk-lok__id { min-width: 0; }
.pbk-lok__t { font-size: 13.2px; font-weight: 800; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
.pbk-lok__lv { margin-top: 2px; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; color: #94a3b8; }
.pbk-mpp { padding: 4px 9px; border-radius: 7px; background: rgba(241,245,249,.95); font-family: ui-monospace, SFMono-Regular, monospace; font-size: 11px; font-weight: 700; color: #475569; }
.pbk-manual { font-size: 11px; font-weight: 700; color: #cbd5e1; }
.pbk-td { font-size: 12.5px; color: #475569; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pbk-td i { color: #8b5cf6; font-size: 11px; }
.pbk-fillcell { min-width: 120px; }
.pbk-fillcell__n { font-size: 12.5px; font-weight: 800; color: #0f172a; }
.pbk-fillcell__bar { margin-top: 6px; height: 6px; border-radius: 999px; background: rgba(148,163,184,.2); overflow: hidden; }
.pbk-fillcell__bar > span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(135deg,#818cf8,#4f46e5); animation: pbkGrow 1.2s cubic-bezier(.22,1,.36,1) both; }
.pbk-tbl__empty { padding: 28px 19px !important; text-align: center; color: #94a3b8; font-size: 12.5px; }
.pbk-matinote { display: flex; align-items: flex-start; gap: 8px; margin: 0; padding: 13px 19px; border-top: 1px solid rgba(226,232,240,.85); background: rgba(255,251,235,.7); font-size: 12px; line-height: 1.6; color: #92400e; }
.pbk-matinote .bi { flex-shrink: 0; margin-top: 2px; color: #d97706; }
.pbk-matinote b { color: #78350f; }

.pbk-pick { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; min-height: 340px; padding: 48px 20px; text-align: center; border-radius: 22px; background: rgba(255,255,255,.86); border: 1px solid rgba(255,255,255,.95); box-shadow: 0 20px 52px rgba(15,23,42,.09); }
.pbk-pick__ico { display: grid; place-items: center; width: 52px; height: 52px; margin-bottom: 6px; border-radius: 16px; background: rgba(99,102,241,.09); color: #6366f1; font-size: 21px; }
.pbk-pick strong { font-size: 14px; font-weight: 800; color: #334155; }
.pbk-pick small { font-size: 12.5px; color: #94a3b8; }

/* ── TOMBOL KEPALA ───────────────────────────────────────────────────────── */
.pbk-ghost { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 12px 17px; border: 1px solid rgba(226,232,240,.95); border-radius: 13px; background: rgba(255,255,255,.9); cursor: pointer; font: inherit; font-size: 13px; font-weight: 700; color: #475569; transition: all .22s ease; }
.pbk-ghost:hover:not(:disabled) { color: #4f46e5; border-color: rgba(99,102,241,.4); transform: translateY(-2px); }
.pbk-ghost:disabled { opacity: .45; cursor: not-allowed; }
.pbk-ghost--sm { min-height: 38px; padding: 9px 14px; border-radius: 11px; font-size: 12px; font-weight: 800; }

.pbk-item__lok { display: block; margin-top: 4px; padding: 2px 6px; border-radius: 6px; background: rgba(241,245,249,.95); font-size: 9px; font-weight: 800; color: #64748b; }

/* ── KOTAK GRAFIK (Highcharts) ───────────────────────────────────────────── */
.pbk-chartbox { margin-top: 16px; }
.pbk-donat { width: clamp(160px, 22vw, 200px); }
.pbk-mode { display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border-radius: 11px; background: rgba(99,102,241,.09); border: 1px solid rgba(99,102,241,.22); font-size: 11px; font-weight: 800; color: #4338ca; white-space: nowrap; }
.pbk-mode i { font-size: 11px; }

/* ── KEPALA PANEL LOKER ──────────────────────────────────────────────────── */
.pbk-lok__id2 { display: flex; align-items: center; gap: 11px; min-width: 0; }
.pbk-lok__badge { width: 32px; height: 32px; flex-shrink: 0; border-radius: 10px; background: linear-gradient(135deg,#fbbf24,#d97706); display: grid; place-items: center; color: #fff; font-size: 14px; }
.pbk-lok__act { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }

/* ── KARTU LOKER ─────────────────────────────────────────────────────────── */
.pbk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(224px, 100%), 1fr)); gap: 12px; padding: 14px; animation: pbkFade .3s ease both; }
.pbk-lcard { position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 11px; padding: 16px; border: 1px solid rgba(226,232,240,.9); border-radius: 18px; background: rgba(248,250,252,.75); cursor: pointer; text-align: left; transition: all .28s cubic-bezier(.22,1,.36,1); }
.pbk-lcard:hover, .pbk-lcard:focus-visible { border-color: rgba(99,102,241,.35); background: #fff; transform: translateY(-4px); box-shadow: 0 20px 44px rgba(79,70,229,.14); outline: none; }
/* Diredupkan, bukan disembunyikan: kartunya memuat satu-satunya jalan untuk
   menampilkannya kembali. */
.pbk-lcard.is-mati { opacity: .58; }
.pbk-lcard.is-mati:hover { opacity: 1; }
.pbk-lcard__st { position: absolute; top: 11px; right: 11px; padding: 3px 8px; border-radius: 7px; font-size: 9px; font-weight: 800; }
.pbk-lcard__st.is-on { background: rgba(16,185,129,.12); color: #047857; }
.pbk-lcard__st.is-off { background: rgba(100,116,139,.12); color: #475569; }
.pbk-lcard__ico { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; color: #fff; font-size: 20px; }
.pbk-lcard__id { min-width: 0; }
.pbk-lcard__t { font-size: 13.4px; font-weight: 800; letter-spacing: -.01em; color: #0f172a; text-wrap: pretty; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.pbk-lcard__mpp { margin-top: 4px; font-family: ui-monospace, SFMono-Regular, monospace; font-size: 10.5px; font-weight: 700; color: #64748b; }
.pbk-lcard__dept { display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 10.5px; color: #94a3b8; }
.pbk-lcard__dept i { font-size: 10px; }
.pbk-lcard__dept span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pbk-lcard__tags { display: flex; gap: 6px; flex-wrap: wrap; }
.pbk-mini { padding: 3px 8px; border-radius: 7px; background: rgba(241,245,249,.95); font-size: 9.5px; font-weight: 800; color: #64748b; }
.pbk-mini.is-hi { background: rgba(99,102,241,.09); color: #4338ca; }
.pbk-lcard__seat { margin-top: auto; }
.pbk-lcard__seatt { display: flex; justify-content: space-between; font-size: 10.5px; font-weight: 800; color: #64748b; }
.pbk-lcard__seatt b { color: #0f172a; }
.pbk-lcard__bar { display: block; margin-top: 6px; height: 6px; border-radius: 999px; background: rgba(148,163,184,.2); overflow: hidden; }
.pbk-lcard__bar > span { display: block; height: 100%; border-radius: 999px; animation: pbkGrow 1.1s cubic-bezier(.22,1,.36,1) both; }
.pbk-lcard__act { display: flex; align-items: center; gap: 8px; padding-top: 11px; border-top: 1px solid rgba(226,232,240,.9); }
.pbk-lcard__swlbl { min-width: 0; font-size: 10.5px; font-weight: 800; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.pbk-empty--span { grid-column: 1 / -1; }
.pbk-num { font-size: 13px; font-weight: 800; color: #4338ca; }
.pbk-tbl tbody tr.is-klik { cursor: pointer; }

/* ── PANEL DETAIL SATU LOKER ─────────────────────────────────────────────── */
.pbk-hero--loker { animation: pbkFade .32s ease both; }
.pbk-dtop { position: relative; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.pbk-dcrumb { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 700; color: #94a3b8; min-width: 0; }
.pbk-dcrumb i { font-size: 7px; }
.pbk-dcrumb .on { font-family: ui-monospace, SFMono-Regular, monospace; color: #4f46e5; font-weight: 800; }
.pbk-hero__row--d { margin-top: 18px; align-items: flex-start; }
.pbk-dhead { display: flex; align-items: flex-start; gap: 14px; min-width: 0; flex: 1; }
.pbk-dhead__ico { flex-shrink: 0; width: 54px; height: 54px; border-radius: 16px; display: grid; place-items: center; color: #fff; font-size: 22px; }
.pbk-dhead__id { min-width: 0; }
.pbk-dhead__id h3 { margin: 0; font-size: clamp(1.15rem, 2.3vw, 1.5rem); font-weight: 800; letter-spacing: -.03em; color: #0f172a; text-wrap: pretty; }

.pbk-copy { display: inline-flex; align-items: center; gap: 8px; min-height: 42px; padding: 10px 15px; border: none; border-radius: 12px; cursor: pointer; font: inherit; font-size: 12.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg,#8b5cf6,#4f46e5); box-shadow: 0 12px 26px rgba(99,102,241,.3); transition: all .24s cubic-bezier(.22,1,.36,1); }
.pbk-copy:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 18px 34px rgba(99,102,241,.38); }
.pbk-copy:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }
.pbk-copy.is-done { background: linear-gradient(135deg,#34d399,#059669); box-shadow: 0 12px 26px rgba(16,185,129,.3); }

/* Tautannya ikut DITAMPILKAN, tidak cuma bisa disalin: papan klip ditolak
   peramban di koneksi http biasa, dan tanpa teks yang terlihat rekruter tidak
   punya cara lain mendapatkan alamatnya. */
.pbk-url { position: relative; display: flex; align-items: center; gap: 9px; margin: 14px 0 0; padding: 10px 14px; border-radius: 12px; background: rgba(99,102,241,.06); border: 1px dashed rgba(99,102,241,.3); font-size: 12px; color: #4338ca; min-width: 0; }
.pbk-url > i { flex-shrink: 0; color: #6366f1; }
.pbk-url > span { flex: 1; min-width: 0; font-family: ui-monospace, SFMono-Regular, monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pbk-url a { flex-shrink: 0; display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 800; color: #4f46e5; text-decoration: none; }
.pbk-url a:hover { text-decoration: underline; }

.pbk-fields { position: relative; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(168px, 100%), 1fr)); gap: 10px; margin-top: 18px; }
.pbk-field { padding: 12px 14px; border-radius: 14px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); min-width: 0; }
.pbk-field__l { font-size: 9.5px; font-weight: 800; letter-spacing: .13em; color: #94a3b8; }
.pbk-field__v { margin-top: 5px; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; color: #0f172a; min-width: 0; }
.pbk-field__v i { flex-shrink: 0; font-size: 12px; }
.pbk-field__v.is-off { color: #94a3b8; font-weight: 700; }

/* ── ISI MPP ─────────────────────────────────────────────────────────────── */
.pbk-desc { margin: 14px 0 0; padding: 13px 15px; border-radius: 14px; background: rgba(248,250,252,.9); border: 1px solid rgba(226,232,240,.9); font-size: 12.8px; line-height: 1.7; color: #475569; text-wrap: pretty; }
.pbk-two { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)); gap: clamp(14px, 2vw, 22px); margin-top: 18px; }
.pbk-poin { min-width: 0; }
.pbk-poin__t { display: flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #64748b; }
.pbk-poin__t i { color: #6366f1; font-size: 13px; }
.pbk-poin__row { display: flex; align-items: flex-start; gap: 10px; margin-top: 10px; }
.pbk-poin__ico { flex-shrink: 0; width: 22px; height: 22px; border-radius: 7px; display: grid; place-items: center; font-size: 11px; }
.pbk-poin__ico.is-ok { background: rgba(16,185,129,.12); color: #059669; }
.pbk-poin__ico.is-idg { background: rgba(99,102,241,.1); color: #4f46e5; }
.pbk-poin__row > span:last-child { font-size: 12.4px; line-height: 1.55; color: #334155; text-wrap: pretty; }

.pbk-tagset { margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(226,232,240,.9); }
.pbk-tagset__wrap { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 11px; }
.pbk-skill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 9px; background: rgba(99,102,241,.1); font-size: 11.5px; font-weight: 700; color: #4338ca; }
.pbk-skill i { font-size: 11px; }
.pbk-benefit { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 9px; background: rgba(16,185,129,.1); font-size: 11.5px; font-weight: 700; color: #047857; }
.pbk-benefit i { font-size: 10px; }

/* ── SUMBER PELAMAR (daftar ikon + bilah) ────────────────────────────────── */
.pbk-src { display: flex; flex-direction: column; gap: 13px; margin-top: 18px; }
.pbk-src__row { display: flex; align-items: center; gap: 12px; }
/* Kanal nol pelamar tetap tampil, hanya diredupkan: ketiadaannya adalah
   temuan, bukan alasan untuk menghilangkan barisnya. */
.pbk-src__row.is-nol { opacity: .5; }
.pbk-src__ico { flex-shrink: 0; width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; font-size: 13px; }
.pbk-src__body { flex: 1; min-width: 0; }
.pbk-src__top { display: flex; justify-content: space-between; gap: 10px; font-size: 12.5px; }
.pbk-src__lbl { min-width: 0; font-weight: 700; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pbk-src__pct { flex-shrink: 0; font-weight: 800; color: #64748b; }
.pbk-src__bar { display: block; margin-top: 6px; height: 7px; border-radius: 999px; background: rgba(148,163,184,.18); overflow: hidden; }
.pbk-src__bar > span { display: block; height: 100%; border-radius: 999px; animation: pbkGrow 1.2s cubic-bezier(.22,1,.36,1) both; }

/* ── SALIN TAUTAN DARI KARTU / BARIS ─────────────────────────────────────── */
/* SELALU TAMPIL, tidak menunggu hover. Tombol yang baru muncul saat kursor
   lewat hanya ditemukan orang yang sudah tahu ia ada — dan yang belum tahu
   tidak punya satu pun petunjuk bahwa tautan loker bisa disalin dari sini.
   Layar sentuh bahkan tidak punya hover sama sekali.

   Didorong ke ujung kanan barisnya (margin-left:auto), bukan diletakkan
   melayang di atas kartu: apa pun yang melayang cepat atau lambat menindih
   sesuatu — dan yang tertindih kemarin adalah ikon posisinya sendiri. */
.pbk-lcard__copy { flex-shrink: 0; margin-left: auto; display: grid; place-items: center; width: 30px; height: 30px; border: 1px solid rgba(99,102,241,.25); border-radius: 9px; background: rgba(99,102,241,.07); cursor: pointer; color: #4f46e5; font-size: 14px; transition: all .22s ease; }
.pbk-lcard__copy:hover { border-color: rgba(99,102,241,.5); background: rgba(99,102,241,.14); transform: translateY(-1px); }
.pbk-lcard__copy.is-done { color: #047857; border-color: rgba(16,185,129,.4); background: rgba(16,185,129,.12); }

.pbk-actcell { white-space: nowrap; }
.pbk-rowcopy { display: inline-grid; place-items: center; width: 28px; height: 28px; margin-right: 8px; vertical-align: middle; border: 1px solid rgba(226,232,240,.95); border-radius: 8px; background: #fff; cursor: pointer; color: #64748b; font-size: 13px; transition: all .2s ease; }
.pbk-rowcopy:hover { color: #4f46e5; border-color: rgba(99,102,241,.45); }
.pbk-rowcopy.is-done { color: #047857; border-color: rgba(16,185,129,.4); background: rgba(16,185,129,.1); }

/* ── FILTER: kartu di layar lebar, lembar bawah di layar sempit ──────────── */
/* Bagian dari kartu daftar, bukan kartu tersendiri: tanpa latar, tanpa
   bayangan, hanya dipisahkan satu garis. Bayangan kedua di dalam satu
   kartu membuatnya terbaca seperti dua kartu yang kebetulan bersentuhan. */
.pbk-filter { border-top: 1px solid rgba(226,232,240,.85); background: rgba(248,250,252,.55); }
.pbk-filter__head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 13px 16px; border-bottom: 1px solid rgba(226,232,240,.85); }
.pbk-filter__title { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 800; color: #0f172a; }
.pbk-filter__title i { color: #8b5cf6; }
.pbk-filter__act { display: flex; align-items: center; gap: 6px; }
.pbk-filter__reset { display: inline-flex; align-items: center; gap: 5px; padding: 6px 10px; border: 1px solid rgba(226,232,240,.95); border-radius: 9px; background: #fff; cursor: pointer; font: inherit; font-size: 11px; font-weight: 800; color: #64748b; transition: all .2s ease; }
.pbk-filter__reset:hover { color: #4f46e5; border-color: rgba(99,102,241,.4); }
/* Tombol tutup hanya berarti pada lembar bawah — di layar lebar tidak ada yang
   perlu ditutup. */
.pbk-filter__close { display: none; align-items: center; justify-content: center; width: 28px; height: 28px; border: 1px solid rgba(226,232,240,.95); border-radius: 9px; background: #fff; cursor: pointer; color: #64748b; font-size: 12px; }
.pbk-filter__grid { display: flex; flex-direction: column; gap: 11px; padding: 14px 16px 16px; }
.pbk-filter__count { font-size: 11.5px; color: #94a3b8; }
.pbk-filter__count strong { color: #4338ca; font-size: 13px; }

.pbk-sheetbg { display: none; position: fixed; inset: 0; z-index: 1200; background: rgba(15,23,42,.35); backdrop-filter: blur(2px); }
.pbk-fab { display: none; position: fixed; z-index: 1199; right: clamp(12px, 3vw, 22px); bottom: clamp(16px, 4vw, 26px); width: 52px; height: 52px; border: none; border-radius: 17px; cursor: pointer; color: #fff; font-size: 18px; background: linear-gradient(135deg,#8b5cf6,#4f46e5); box-shadow: 0 16px 34px rgba(99,102,241,.36); transition: all .24s cubic-bezier(.22,1,.36,1); }
.pbk-fab:hover { transform: translateY(-3px); box-shadow: 0 22px 44px rgba(99,102,241,.46); }
.pbk-fab__badge { position: absolute; top: -5px; right: -5px; display: grid; place-items: center; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 999px; background: #f59e0b; color: #fff; font-size: 10.5px; font-weight: 800; border: 2px solid #fff; }

/* ── KERANGKA PEMUATAN PANEL KIRI ────────────────────────────────────────── */
.pbk-skel { display: flex; flex-direction: column; gap: 4px; overflow: hidden; }
.pbk-skel__row { display: flex; align-items: center; gap: 11px; padding: 11px; border-radius: 13px; background: rgba(248,250,252,.7); animation: pbkPulse2 1.4s ease-in-out infinite; }
.pbk-skel__ico { flex-shrink: 0; width: 34px; height: 34px; border-radius: 10px; background: rgba(148,163,184,.22); }
.pbk-skel__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.pbk-skel__bar { display: block; height: 8px; border-radius: 999px; background: rgba(148,163,184,.22); }
.pbk-skel__num { flex-shrink: 0; width: 34px; height: 26px; border-radius: 8px; background: rgba(148,163,184,.18); }
/* Denyut halus, bukan kilau yang berlari: satu panel penuh baris berkilau
   menarik mata ke tempat yang justru belum ada isinya. */
@keyframes pbkPulse2 { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }

/* ── LAYAR SEMPIT ────────────────────────────────────────────────────────── */
@media (max-width: 1023px) {
    .pbk-aside { position: static; max-width: none; flex-basis: 100%; }
    .pbk-aside.is-hide { display: none; }
    .pbk-back { display: grid; place-items: center; }
    .pbk-hero__act { width: 100%; }
    .pbk-list { max-height: 60vh; }

    /* Filternya berhenti jadi kartu dan menjadi lembar yang naik dari bawah.
       Elemen yang sama, tanpa markup kedua — jadi bidang yang ditambahkan
       nanti tidak mungkin lupa ikut ke salah satunya. */
    /* Di layar sempit ia keluar dari kartu (lihat Teleport di template) dan
       berdiri sendiri sebagai lembar — jadi latar & bayangannya dikembalikan. */
    .pbk-filter {
        position: fixed; z-index: 1201; left: 0; right: 0; bottom: 0;
        border: none; border-radius: 20px 20px 0 0; max-height: 78vh; overflow-y: auto;
        background: #fff; box-shadow: 0 -18px 44px rgba(15,23,42,.24);
        transform: translateY(100%); transition: transform .3s cubic-bezier(.22,1,.36,1);
    }
    .pbk-filter.is-open { transform: none; }
    .pbk-filter__head { position: sticky; top: 0; z-index: 1; background: rgba(255,255,255,.97); }
    .pbk-filter__close { display: inline-flex; }
    .pbk-sheetbg { display: block; }
    .pbk-fab { display: grid; place-items: center; }
}
@media (max-width: 640px) {
    .pbk-cta { width: 100%; justify-content: center; }
    .pbk-win__stats { width: 100%; }
    .pbk-stat { flex: 1; min-width: 0; }
}

/* ═══ GAYA MILIK MODAL — dipertahankan dari versi sebelumnya ═══════════════ */
.pkg-pill--gold { color: #b45309; background: rgba(245, 158, 11, .14); }
.pkg-pill--sky { color: #0369a1; background: rgba(14, 165, 233, .12); }
.pkg-pill--green { color: #059669; background: rgba(16, 185, 129, .12); }
.pkg-pill--slate { color: #64748b; background: #eef0f7; }
.pbk-proglist { max-height: 340px; overflow-y: auto; display: grid; grid-template-columns: repeat(2, 1fr); gap: .55rem; padding-right: 2px; }
.pbk-progcard { text-align: left; border: 1px solid rgba(15, 23, 42, .1); background: #fff; border-radius: 13px; padding: .7rem .85rem; cursor: pointer; transition: all .15s; display: flex; flex-direction: column; gap: .5rem; }
.pbk-progcard:hover { border-color: rgba(99, 102, 241, .4); background: #f8fafc; transform: translateY(-1px); }
.pbk-progcard.is-on { border-color: #6366f1; background: #eef2ff; box-shadow: 0 4px 14px rgba(79, 70, 229, .12); }
.pbk-progcard__head { display: flex; align-items: center; gap: .6rem; }
.pbk-progcard__ico { flex: none; width: 2.2rem; height: 2.2rem; border-radius: 10px; display: grid; place-items: center; font-size: 1rem; }
.pbk-progcard__id { min-width: 0; flex: 1; display: flex; flex-direction: column; }
.pbk-progcard__id strong { font-size: 13.5px; color: #1e293b; line-height: 1.25; }
.pbk-progcard__id small { font-size: 11px; color: #94a3b8; }
.pbk-progcard__chk { color: #4f46e5; font-size: 17px; flex: none; }
.pbk-progcard__meta { display: flex; flex-wrap: wrap; gap: .3rem .9rem; font-size: 11.5px; color: #64748b; }
.pbk-progcard__meta span { display: inline-flex; align-items: center; gap: .3rem; }
.pbk-progcard__meta > span > i { color: #94a3b8; }
.pbk-progcard__date { color: #4338ca; font-weight: 700; }
.pbk-progcard__date > i { color: #6366f1 !important; }
.pbk-progload { grid-column: 1 / -1; display: flex; align-items: center; justify-content: center; gap: .5rem; color: #64748b; font-size: 12.5px; font-weight: 600; padding: 2rem 0; }
.pbk-spin { width: 16px; height: 16px; border: 2px solid rgba(99, 102, 241, .25); border-top-color: #6366f1; border-radius: 50%; animation: pbkspin .7s linear infinite; }
@keyframes pbkspin { to { transform: rotate(360deg); } }
.pbk-progempty { grid-column: 1 / -1; text-align: center; color: #94a3b8; font-size: 12.5px; padding: 1.6rem 0; }
.pbk-progempty > i { display: block; font-size: 1.5rem; margin-bottom: .3rem; opacity: .6; }
.pbk-progcard__ico.pkg-pill--gold { background: rgba(234, 179, 8, .16); color: #a16207; }
.pbk-progcard__ico.pkg-pill--green { background: rgba(16, 185, 129, .14); color: #059669; }
.pbk-progcard__ico.pkg-pill--sky { background: rgba(14, 165, 233, .14); color: #0369a1; }
.pbk-progcard__ico.pkg-pill--slate { background: #eef0f7; color: #64748b; }
.pbk-note { display: flex; align-items: flex-start; gap: .4rem; margin: .2rem 0 0; font-size: 11.5px; line-height: 1.55; color: #64748b; }
.pbk-note > i { color: #6366f1; margin-top: 1px; }

/* ── ANALITIK PHONE SCREENING ────────────────────────────────────────────── */
.pbk-skr__ko {
    padding: 13px 15px;
    border-radius: 11px;
    background: rgba(220, 38, 38, 0.05);
    border: 1px solid rgba(220, 38, 38, 0.18);
    margin-bottom: 14px;
}
.pbk-skr__koHead { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.pbk-skr__koHead i { color: #dc2626; }
.pbk-skr__koHead b { font-size: 13.5px; color: #b91c1c; }
.pbk-skr__koRow {
    display: flex;
    align-items: baseline;
    gap: 9px;
    font-size: 12.5px;
    color: #475569;
    padding: 3px 0;
}
.pbk-skr__koN {
    flex: 0 0 auto;
    font-weight: 700;
    color: #b91c1c;
    font-variant-numeric: tabular-nums;
    min-width: 30px;
}
.pbk-skr__koNota { margin: 8px 0 0; font-size: 11.5px; color: #94a3b8; line-height: 1.55; }

.pbk-skr__blok { min-width: 0; }
.pbk-skr__t {
    display: flex;
    align-items: baseline;
    gap: 8px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: #6366f1;
    margin-bottom: 9px;
}
.pbk-skr__t small { font-weight: 500; letter-spacing: 0; text-transform: none; color: #94a3b8; }

.pbk-skr__bar {
    display: grid;
    grid-template-columns: minmax(90px, 130px) minmax(0, 1fr) 34px;
    gap: 9px;
    align-items: center;
    padding: 3px 0;
}
.pbk-skr__lbl {
    font-size: 12px;
    color: #475569;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-skr__track {
    height: 7px;
    border-radius: 4px;
    background: #eef1f7;
    overflow: hidden;
}
.pbk-skr__track span {
    display: block;
    height: 100%;
    border-radius: 4px;
    background: #6366f1;
    transition: width 0.3s;
    min-width: 2px;
}
.pbk-skr__track span.is-skor { background: linear-gradient(90deg, #818cf8, #4f46e5); }
.pbk-skr__bar b {
    font-size: 12px;
    color: #0f172a;
    text-align: right;
    font-variant-numeric: tabular-nums;
}
.pbk-skr__samar { font-size: 11.5px; color: #94a3b8; }

.pbk-skr__kontak {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
    margin-top: 14px;
    padding-top: 13px;
    border-top: 1px solid #f1f5f9;
}
.pbk-skr__kontak .pbk-skr__t { margin-bottom: 0; }

.pbk-skr__lipat {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    border: 0;
    background: transparent;
    padding: 13px 0 0;
    margin-top: 13px;
    border-top: 1px solid #f1f5f9;
    font-family: inherit;
    font-size: 12.5px;
    color: #64748b;
    cursor: pointer;
    text-align: left;
}
.pbk-skr__lipat:hover { color: #4338ca; }
.pbk-skr__rinci { margin-top: 10px; }
.pbk-skr__q { padding: 11px 0; border-top: 1px solid #f8fafc; }
.pbk-skr__q:first-child { border-top: 0; }
.pbk-skr__qh { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 7px; }
.pbk-skr__qh b { font-size: 12.5px; color: #0f172a; }
.pbk-tag.is-ko { background: rgba(220, 38, 38, 0.12); color: #b91c1c; }

@media (max-width: 760px) {
    .pbk-skr__bar { grid-template-columns: minmax(80px, 110px) minmax(0, 1fr) 30px; }
    .pbk-skr__ko { padding: 11px 12px; }
    .pbk-skr__lipat { padding: 14px 0 4px; }
}

/* Di bawah ini label tidak lagi muat sebaris dengan batangnya, jadi ia naik
   ke atas dan memakai lebar penuh — dibaca utuh, bukan dipotong titik tiga. */
@media (max-width: 520px) {
    .pbk-skr__bar {
        grid-template-columns: minmax(0, 1fr) 34px;
        row-gap: 4px;
        padding: 6px 0;
    }
    .pbk-skr__lbl {
        grid-column: 1 / -1;
        white-space: normal;
        overflow: visible;
        font-size: 12.5px;
    }
    .pbk-skr__track { height: 8px; }
    .pbk-skr__kontak { gap: 7px; }
    .pbk-skr__koRow { flex-wrap: wrap; row-gap: 2px; }
}
</style>
