<!-- WEB CAREER ADMIN — WORKLIST PELAMAR, 1:1 desain "Worklist Pelamar" (Claude
     Design): panel program kiri (cari + chip filter + kartu ber-aksen), papan
     kanban tahap alur, drawer detail kandidat (progress alur, akordeon formulir,
     dokumen + lightbox), aksi Loloskan / Tidak Lolos. Data 100% dari API nyata. -->
<template>
    <Head title="Worklist Pelamar" />

    <div class="plw">
        <!-- ═══ PANEL PROGRAM (KIRI) ═══ -->
        <aside class="plw-panel">
            <div class="plw-panel__head">
                <div class="plw-panel__toprow">
                    <div class="plw-panel__title">{{ basisLoker ? 'DAFTAR JOB VACANCY' : 'DAFTAR PROGRAM' }}</div>
                    <span class="plw-panel__count">{{ total }} aktif</span>
                </div>

                <!-- ═══ DASAR DAFTAR — PROGRAM atau JOB VACANCY ═══
                     Dua pertanyaan yang berbeda atas populasi yang sama:

                       · PROGRAM      "gelombang rekrutmen bulan ini sampai mana?"
                       · JOB VACANCY  "pencarian posisi ini sampai mana?"

                     Berbasis program, satu MPP yang dibuka ulang di gelombang
                     berikutnya muncul DUA KALI di daftar ini — di bawah dua nama
                     program berbeda, masing-masing membawa sebagian pelamarnya.
                     Untuk menjawab pertanyaan kedua, rekruter harus membuka dua
                     papan lalu menjumlahkannya sendiri.

                     Bukan diganti, karena pertanyaan pertama juga masih ditanya
                     tiap hari — yang memilih orang yang sedang bekerja. -->
                <div class="plw-basis" role="tablist" aria-label="Dasar daftar">
                    <button
                        type="button" class="plw-basis__btn" :class="{ 'is-on': basis === 'program' }"
                        role="tab" :aria-selected="basis === 'program'"
                        title="Kelompokkan menurut program penyelenggaraan"
                        @click="setBasis('program')"
                    >
                        <i class="bi bi-collection-fill"></i><span>Program</span>
                    </button>
                    <button
                        type="button" class="plw-basis__btn" :class="{ 'is-on': basis === 'loker' }"
                        role="tab" :aria-selected="basis === 'loker'"
                        title="Kelompokkan menurut MPP / lowongan — digabung lintas program"
                        @click="setBasis('loker')"
                    >
                        <i class="bi bi-briefcase-fill"></i><span>Job Vacancy</span>
                    </button>
                </div>

                <div class="plw-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                    <input v-model="q" type="text" :placeholder="basisLoker ? 'Cari posisi, MPP, departemen...' : 'Cari program...'" @input="cariDebounce" />
                </div>
                <!-- CHIP KATEGORI — isinya mengikuti hak akses, bukan seluruh master.
                     "Semua" hanya berarti bila memang ADA yang bisa dipilih: pada
                     admin yang dijatah satu kategori, "Semua" dan chip kategorinya
                     menyaring himpunan yang sama persis, jadi dua tombol untuk satu
                     hasil — dan yang menekannya mengira ada isi lain yang belum
                     terlihat. Satu kategori: seluruh baris chip disembunyikan. -->
                <div v-if="talent.length > 1" class="plw-chips">
                    <button type="button" class="plw-chip" :class="{ 'is-on': jenis === '' }" @click="setJenis('')">Semua</button>
                    <button v-for="t in talent" :key="t.kode" type="button" class="plw-chip" :class="{ 'is-on': jenis === t.kode }" @click="setJenis(t.kode)">{{ t.label }}</button>
                </div>
            </div>

            <div class="plw-panel__list">
                <div v-if="loadingProg" class="plw-load"><span class="plw-spin"></span> Memuat {{ basisLoker ? 'job vacancy' : 'program' }}…</div>
                <div v-else-if="!programs.length" class="plw-empty">Tidak ada {{ basisLoker ? 'job vacancy' : 'program' }}</div>
                <!-- Program yang lokernya sudah berpindah tangan TIDAK BISA
                     dipilih. Papannya memang akan kosong — bukan karena gagal
                     memuat, melainkan karena tidak ada satu kandidat pun yang
                     jadi urusan akun ini. Membiarkannya diklik hanya menukar
                     satu kebingungan dengan kebingungan lain yang lebih sulit
                     ditebak sebabnya. -->
                <!-- ═══ KARTU JOB VACANCY ═══
                     Isinya menjawab pertanyaan yang lain dari kartu program:
                     bukan "gelombang apa ini", melainkan "pekerjaan apa yang
                     dicari, untuk siapa, berapa kursi". Nomor MPP-nya disebut
                     karena itulah rujukan yang dipakai di persetujuan dan yang
                     dicari orang saat menghubungkannya kembali ke HRIS. -->
                <button
                    v-for="v in (basisLoker ? programs : [])"
                    :key="v.id"
                    type="button"
                    class="plw-prog"
                    :class="{ 'is-on': v.id === selectedId, 'is-lepas': diserahkan(v) }"
                    :style="{ '--acc': aksen(v) }"
                    :disabled="diserahkan(v)"
                    :title="diserahkan(v)
                        ? `Seluruh ${v.lokerTotal} loker lowongan ini sudah diserahterimakan — tidak ada kandidat yang jadi urusan Anda.`
                        : ''"
                    @click="pilihProgram(v)"
                >
                    <span class="plw-prog__bar"></span>
                    <span class="plw-prog__toprow">
                        <span class="plw-prog__tag" :class="v.kategori === 'MT' ? 'is-mt' : 'is-rek'">{{ katLabel(v.kategori) }}</span>
                        <span v-if="diserahkan(v)" class="plw-prog__lepas">
                            <i class="bi bi-arrow-left-right"></i> Diserahkan
                        </span>
                        <span class="plw-prog__code">{{ v.mppRef || 'Tanpa MPP' }}</span>
                    </span>
                    <span class="plw-prog__title">{{ v.posisi }}</span>
                    <span class="plw-prog__meta">
                        <i class="bi bi-diagram-3" style="font-size: 12px; flex: 0 0 auto"></i>
                        <span class="plw-ell">{{ v.departemen || 'Tanpa departemen' }}<template v-if="v.level"> · {{ v.level }}</template></span>
                    </span>
                    <span class="plw-prog__meta">
                        <i class="bi bi-geo-alt" style="font-size: 12px; flex: 0 0 auto"></i>
                        <span class="plw-ell">{{ v.lokasi || 'Lokasi belum disetel' }} · {{ v.kuota }} kursi</span>
                    </span>
                    <!-- INILAH SEBAB MODE INI ADA: satu lowongan yang dibuka di
                         lebih dari satu gelombang. Berbasis program ia dua baris
                         terpisah; di sini satu baris, dan jumlah gelombangnya
                         disebut supaya tidak ada yang mengira riwayatnya hilang. -->
                    <span v-if="v.program && v.program.length > 1" class="plw-prog__meta">
                        <i class="bi bi-collection" style="font-size: 12px; flex: 0 0 auto"></i>
                        <span class="plw-ell">Dibuka di {{ v.program.length }} program · {{ v.program.join(', ') }}</span>
                    </span>
                    <span class="plw-prog__stats">
                        <span class="plw-prog__stat">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></svg>
                            <b style="color: #4f46e5">{{ v.aktif }}</b> aktif
                        </span>
                        <span class="plw-prog__stat">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.4"><path d="M20 6L9 17l-5-5" /></svg>
                            <b style="color: #059669">{{ v.lolos }}</b> lolos
                        </span>
                        <span>{{ v.pelamar }} total</span>
                    </span>
                    <span v-if="diserahkan(v)" class="plw-prog__nota">
                        <i class="bi bi-info-circle"></i>
                        Lokernya dipegang rekruter lain. Minta diserahkan kembali,
                        atau hubungi admin lewat Master Akun.
                    </span>
                </button>

                <button
                    v-for="p in (basisLoker ? [] : programs)"
                    :key="p.id"
                    type="button"
                    class="plw-prog"
                    :class="{ 'is-on': p.id === selectedId, 'is-lepas': diserahkan(p) }"
                    :style="{ '--acc': aksen(p) }"
                    :disabled="diserahkan(p)"
                    :title="diserahkan(p)
                        ? `Seluruh ${p.lokerTotal} loker program ini sudah diserahterimakan — tidak ada kandidat yang jadi urusan Anda.`
                        : ''"
                    @click="pilihProgram(p)"
                >
                    <span class="plw-prog__bar"></span>
                    <span class="plw-prog__toprow">
                        <span class="plw-prog__tag" :class="p.kategori === 'MT' ? 'is-mt' : 'is-rek'">{{ katLabel(p.kategori) }}</span>
                        <span v-if="diserahkan(p)" class="plw-prog__lepas">
                            <i class="bi bi-arrow-left-right"></i> Diserahkan
                        </span>
                        <span class="plw-prog__code">{{ p.kode }}</span>
                    </span>
                    <span class="plw-prog__title">{{ p.nama }}</span>
                    <span class="plw-prog__meta">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex: 0 0 auto"><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M3 9h18" /></svg>
                        <span class="plw-ell">{{ p.penyelenggara }}</span>
                    </span>
                    <span class="plw-prog__meta">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex: 0 0 auto"><path d="M6 3v12" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="6" r="3" /><path d="M18 9c0 6-12 3-12 9" /></svg>
                        <span class="plw-ell">{{ p.alur || 'Belum ada alur' }}<template v-if="p.jumlahTahap"> · {{ p.jumlahTahap }} tahap</template></span>
                    </span>
                    <span class="plw-prog__stats">
                        <span class="plw-prog__stat">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></svg>
                            <b style="color: #4f46e5">{{ p.aktif }}</b> aktif
                        </span>
                        <span class="plw-prog__stat">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.4"><path d="M20 6L9 17l-5-5" /></svg>
                            <b style="color: #059669">{{ p.lolos }}</b> lolos
                        </span>
                        <span>{{ p.pelamar }} total</span>
                    </span>
                    <!-- Menyebut SEBABNYA di kartu, bukan cuma menyisakan nol.
                         "0 aktif" saja terbaca sebagai "belum ada yang melamar"
                         — padahal pelamarnya ada, hanya bukan lagi urusan akun
                         ini. Dua keadaan itu menuntut tindakan yang berbeda. -->
                    <span v-if="diserahkan(p)" class="plw-prog__nota">
                        <i class="bi bi-info-circle"></i>
                        Lokernya dipegang rekruter lain. Minta diserahkan kembali,
                        atau hubungi admin lewat Master Akun.
                    </span>
                </button>

                <div v-if="totalPage > 1" class="plw-pager">
                    <button type="button" class="plw-pager__btn" :disabled="page <= 1" :onClick="page <= 1 ? null : () => gotoPage(page - 1)"><i class="bi bi-chevron-left"></i></button>
                    <span>{{ page }} / {{ totalPage }}</span>
                    <button type="button" class="plw-pager__btn" :disabled="page >= totalPage" @click="gotoPage(page + 1)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </aside>

        <!-- ═══ MAIN (KANAN) ═══ -->
        <main class="plw-main">
            <div class="plw-blob plw-blob--a"></div>
            <div class="plw-blob plw-blob--b"></div>

            <div class="plw-main__inner">
                <div class="plw-hgroup">
                    <h1 class="plw-h1">Worklist Pelamar</h1>
                    <p class="plw-sub">
                        {{ basisLoker
                            ? 'Pilih job vacancy di panel kiri — pelamarnya digabung lintas program, lalu pantau & gerakkan pada alur seleksinya.'
                            : 'Pilih program di panel kiri, lalu pantau & gerakkan kandidat pada alur seleksinya.' }}
                    </p>
                </div>

                <template v-if="detail.program">
                    <div class="plw-progrow">
                        <div style="min-width: 0; flex: 1">
                            <div class="plw-eyebrow">
                                {{ katLabel(detail.program.kategori).toUpperCase() }}<template v-if="lokerInfo && lokerInfo.mppRef"> · {{ lokerInfo.mppRef }}</template>
                            </div>
                            <div class="plw-progtitle">{{ detail.program.nama }}</div>
                            <!-- Baris rincian LOWONGAN — hanya pada basis job vacancy.
                                 Di basis program, hal-hal ini berbeda-beda antar loker
                                 di dalam satu papan, jadi menyebut salah satunya di
                                 kepala halaman akan berbohong tentang sisanya. -->
                            <div v-if="lokerInfo" class="plw-progflow">
                                <i class="bi bi-diagram-3" style="font-size: 13px; flex: 0 0 auto"></i>
                                <span class="plw-ell">
                                    {{ lokerInfo.departemen || 'Tanpa departemen' }}<template v-if="lokerInfo.level"> · {{ lokerInfo.level }}</template>
                                    · {{ lokerInfo.lokasi || 'Lokasi belum disetel' }}
                                    · {{ lokerInfo.kuota }} kursi
                                    <template v-if="lokerInfo.program.length > 1"> · dibuka di {{ lokerInfo.program.length }} program</template>
                                </span>
                            </div>
                            <div class="plw-progflow">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex: 0 0 auto"><path d="M6 3v12" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="6" r="3" /><path d="M18 9c0 6-12 3-12 9" /></svg>
                                <span class="plw-ell">{{ detail.program.alur || 'Belum ada alur' }} · {{ detail.kolom.length }} tahap</span>
                            </div>

                            <!-- ══ SISA SLA MPP ══════════════════════════════
                                 Hanya untuk REKRUTMEN. MT direkrut seangkatan
                                 dengan jadwal program yang sudah ditetapkan HC,
                                 bukan dikejar tenggat pemenuhan kursi — menaruh
                                 hitung mundur di sana menekan rekruter atas
                                 target yang bukan miliknya.

                                 Sisa dihitung server (SlaMpp::keadaan), bukan di
                                 sini: kartu MPP dan dashboard membaca angka yang
                                 sama, dan tiga layar tidak boleh berbeda. -->
                            <div v-if="slaLoker" class="plw-sla" :class="'is-' + slaLoker.nada">
                                <!-- ANGKANYA yang jadi tokoh utama, bukan kalimatnya.
                                     Yang dicari mata saat melintasi kepala halaman
                                     adalah "berapa lagi", dan angka yang dibenamkan
                                     di tengah kalimat menuntut membaca dulu untuk
                                     menemukannya. -->
                                <span class="plw-sla__num">
                                    <b>{{ Math.abs(slaLoker.sisa) }}</b>
                                    <em>hari<br>kerja</em>
                                </span>
                                <span class="plw-sla__isi">
                                    <span class="plw-sla__judul">
                                        {{ slaLoker.lewat ? 'Lewat tenggat pemenuhan' : 'Sisa waktu pemenuhan MPP' }}
                                    </span>
                                    <span class="plw-sla__ket">
                                        <span class="plw-sla__tgl">
                                            <i class="bi bi-calendar-event"></i> {{ tglId(slaLoker.batas) }}
                                        </span>
                                        <template v-if="slaLoker.perpanjanganKe > 0">
                                            <span class="plw-sla__pisah"></span>
                                            <span
                                                class="plw-sla__ext"
                                                :title="`Tenggat semula ${tglId(slaLoker.batasAwal)}`"
                                            >
                                                <i class="bi bi-arrow-clockwise"></i>
                                                diperpanjang {{ slaLoker.perpanjanganKe }}×
                                            </span>
                                        </template>
                                    </span>
                                </span>
                                <!-- Bilah kemajuan mini. Angka menjawab "berapa lagi";
                                     bilah menjawab "seberapa jauh sudah berjalan" -
                                     pertanyaan yang tidak bisa dijawab angka tunggal
                                     tanpa menyebut total. Hanya digambar bila totalnya
                                     memang diketahui. -->
                                <span v-if="slaPersen !== null" class="plw-sla__bar" :aria-label="`Terpakai ${slaPersen}%`">
                                    <span :style="{ width: slaPersen + '%' }"></span>
                                </span>
                            </div>
                        </div>
                        <div class="plw-progact">
                            <button type="button" class="plw-btn-jadwal" @click="goJadwal">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /><path d="M9 15l2 2 4-4" /></svg>
                                Jadwalkan Tes
                            </button>
                            <button type="button" class="plw-btn-reload" :class="{ 'is-segar': menyegarkan }" :title="menyegarkan ? 'Menyegarkan…' : 'Muat ulang'" @click="muatDetail(selectedId)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7" /><path d="M21 4v5h-5" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- ═══ TAB KEADAAN ═══
                         Baris sendiri, di atas penyaring. Ia menjawab pertanyaan
                         yang berbeda: tab memilih POPULASI (siapa yang sedang
                         dilihat), penyaring di bawahnya mempersempit populasi itu.
                         Mencampur keduanya dalam satu baris — bentuk lamanya —
                         membuat orang mengira "Tidak Lolos" adalah salah satu
                         nilai penyaring, lalu mencarinya di dalam dropdown. -->
                    <div class="plw-tabs">
                            <!-- SEMUA lebih dulu, dan inilah bawaannya.
                                 Tab-tab sesudahnya menyempitkan, bukan
                                 mengungkap: dengan "Berjalan" sebagai bawaan,
                                 kandidat yang sudah ditutup — gugur, mundur,
                                 talent pool — tidak terlihat sama sekali sampai
                                 seseorang tahu harus menekan tab mana, dan
                                 papan berbunyi seolah mereka lenyap. -->
                            <button type="button" class="plw-tab" :class="{ 'is-all': statusTab === 'SEMUA' }" @click="statusTab = 'SEMUA'">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></svg>
                                Semua <span class="plw-tab__badge">{{ jmlSemua }}</span>
                            </button>
                            <button type="button" class="plw-tab" :class="{ 'is-run': statusTab === 'AKTIF' }" @click="statusTab = 'AKTIF'">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 3h14l-6 8v7l-2 1v-8L5 3z" /></svg>
                                Berjalan <span class="plw-tab__badge">{{ jmlAktif }}</span>
                            </button>
                            <button type="button" class="plw-tab" :class="{ 'is-rej': statusTab === 'GUGUR' }" @click="statusTab = 'GUGUR'">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                                Tidak Lolos <span class="plw-tab__badge">{{ jmlGugur }}</span>
                            </button>
                            <!-- DITAHAN berdiri sendiri, bukan tercampur di
                                 "Berjalan". Mereka memang masih berjalan, tapi
                                 justru itu masalahnya: tercampur di sana, orang
                                 yang sengaja disisihkan tenggelam dan tak pernah
                                 ditinjau lagi. -->
                            <button type="button" class="plw-tab" :class="{ 'is-hold': statusTab === 'HOLD' }" @click="statusTab = 'HOLD'">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M10 9v6M14 9v6" /></svg>
                                Ditahan <span class="plw-tab__badge">{{ jmlHold }}</span>
                            </button>
                            <!-- MENGUNDURKAN DIRI berdiri sendiri — bukan "Tidak
                                 Lolos", apalagi "Berjalan".

                                 Kandidat yang menolak penawaran atau mengundurkan
                                 diri tidak gagal seleksi: perusahaan tidak
                                 menolaknya, ia yang pergi. Menaruhnya di "Tidak
                                 Lolos" membuat laporan berbunyi seolah kita yang
                                 menggugurkan; membiarkannya di "Berjalan" — yang
                                 selama ini terjadi, karena penyaringnya hanya
                                 mengenal GUGUR & TALENT_POOL — jauh lebih buruk:
                                 orang yang sudah pergi tetap menempati kolom
                                 papan dan terus tampak menunggu diproses.

                                 Disebut lengkap, bukan "Mundur": satu kata itu
                                 sama-sama dipakai untuk memundurkan JADWAL, dan
                                 tab yang bisa dibaca dua arti bukan penyaring. -->
                            <button type="button" class="plw-tab" :class="{ 'is-out': statusTab === 'MUNDUR' }" @click="statusTab = 'MUNDUR'">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5M21 12H9" /></svg>
                                Mengundurkan Diri <span class="plw-tab__badge">{{ jmlMundur }}</span>
                            </button>
                    </div>

                    <!-- ═══ PENYARING — SATU KARTU, SELURUHNYA ELEMENT PLUS ═══
                         Semua pemilih bisa DICARI SAMBIL MENGETIK (`filterable`).
                         Bukan hiasan: satu program bisa menampung ratusan kampus
                         dan puluhan lowongan, dan dropdown sepanjang itu tanpa
                         kotak cari memaksa admin menggulung mencari satu baris.

                         Isi tiap pilihan datang dari data yang BENAR-BENAR ada di
                         papan — kampus dari jawaban formulir kandidat program ini,
                         tahap dari alur yang mereka jalani. Tidak ada pilihan yang
                         menghasilkan nol baris, dan tidak ada kandidat yang
                         kampusnya tak bisa dipilih. -->
                    <div class="plw-toolbar" :class="{ 'is-buka': panelBuka }">
                        <!-- KEPALA PANEL. Dulu panel ini langsung mulai dengan
                             enam kotak isian tanpa satu kata pun yang menyebut
                             ia apa — dan papan yang tiba-tiba berisi tiga orang
                             terbaca sebagai data yang hilang, bukan sebagai
                             saringan yang sedang bekerja. Kepala inilah yang
                             menyebutkannya, sekaligus jadi pegangan menutup
                             panelnya di layar sempit. -->
                        <div class="plw-fhead">
                            <span class="plw-fhead__ico"><i class="bi bi-funnel-fill"></i></span>
                            <div class="plw-fhead__ttl">
                                <b>Panel Penyaring</b>
                                <small>{{ chipFilter.length ? `${chipFilter.length} penyaring aktif` : 'Semua kandidat ditampilkan' }}</small>
                            </div>
                            <span v-if="chipFilter.length" class="plw-fhead__n">{{ chipFilter.length }}</span>
                            <button
                                v-if="chipFilter.length" type="button" class="plw-fhead__x"
                                title="Bersihkan seluruh penyaring" @click="bersihkanFilter"
                            >
                                <i class="bi bi-x-circle"></i><span>Bersihkan</span>
                            </button>
                            <button
                                type="button" class="plw-fhead__tgl"
                                :title="panelBuka ? 'Sembunyikan penyaring' : 'Tampilkan penyaring'"
                                :aria-expanded="panelBuka"
                                @click="panelBuka = !panelBuka"
                            >
                                <i class="bi" :class="panelBuka ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                            </button>
                        </div>

                        <div v-show="panelBuka" class="plw-fbody">
                        <div class="plw-fgrid">
                            <el-input v-model="cariKandidat" clearable class="plw-f plw-f--cari" placeholder="Cari nama, kode, posisi, atau kampus">
                                <template #prefix><i class="bi bi-search"></i></template>
                            </el-input>
                            <!-- MULTI-PILIH. Pertanyaan yang benar-benar diajukan
                                 jarang menyangkut satu nilai: "psikotes DAN
                                 wawancara", "Unsri DAN Unila". Dengan satu nilai
                                 saja, membandingkan dua tahap berarti menyaring
                                 dua kali dan mengingat sendiri angka yang
                                 pertama. Tag yang menumpuk diringkas setelah dua
                                 — kotak penyaring yang tumbuh mengikuti jumlah
                                 pilihan akan mendorong seluruh papan turun. -->
                            <el-select
                                v-model="tahapPilih" multiple filterable clearable
                                collapse-tags collapse-tags-tooltip :max-collapse-tags="2"
                                class="plw-f" placeholder="Tahap — semua"
                            >
                                <el-option v-for="(c, i) in kolomTampil" :key="kunciKolom(c)" :value="kunciKolom(c)" :label="c.label">
                                    <div class="plw-lopt">
                                        <b>{{ String(i + 1).padStart(2, '0') }} · {{ c.label }}</b>
                                        <small>{{ jumlahTahap(c) }} kandidat</small>
                                    </div>
                                </el-option>
                            </el-select>
                            <!-- KAMPUS — daftarnya MENGIKUTI yang terdaftar, bukan
                                 Master Kampus. Master itu berisi 328 ribu baris
                                 hasil impor Dapodik/PDDIKTI; menawarkannya utuh di
                                 sini berarti 99,9% pilihannya menghasilkan papan
                                 kosong, dan yang mencari kampus kandidatnya
                                 sendiri justru tenggelam di antara ratusan ribu
                                 nama yang tak seorang pun melamar dari sana. -->
                            <el-select
                                v-model="kampusPilih" multiple filterable clearable
                                collapse-tags collapse-tags-tooltip :max-collapse-tags="2"
                                class="plw-f" placeholder="Kampus — semua"
                            >
                                <el-option v-for="k in kampusOpsi" :key="k.nama" :value="k.nama" :label="k.nama">
                                    <div class="plw-lopt plw-lopt--kampus">
                                        <!-- Bendera negara — sama seperti pemilih
                                             kampus di formulir pendaftaran. Kode
                                             negaranya datang bersama daftar
                                             pelamar (lihat detailProgram), jadi
                                             tidak ada permintaan tambahan. -->
                                        <img
                                            v-if="k.bendera" class="plw-lopt__flag"
                                            :src="`https://flagcdn.com/20x15/${k.bendera}.png`"
                                            alt="" width="20" height="15" loading="lazy"
                                        />
                                        <i v-else class="bi bi-globe2 plw-lopt__flag plw-lopt__flag--kosong" title="Negara tidak tercatat di data institusi"></i>
                                        <b>{{ k.nama }}</b>
                                        <small>{{ k.jumlah }} kandidat</small>
                                    </div>
                                </el-option>
                            </el-select>
                            <el-select
                                v-if="(detail.posisi || []).length"
                                v-model="posisiPilih" multiple filterable clearable
                                collapse-tags collapse-tags-tooltip :max-collapse-tags="2"
                                class="plw-f" placeholder="Lowongan — semua"
                            >
                                <el-option
                                    v-for="p in detail.posisi" :key="p.id" :value="p.id"
                                    :label="`${p.posisi}${p.level ? ' · ' + p.level : ''}`"
                                >
                                    <div class="plw-lopt">
                                        <b>{{ p.posisi }}</b>
                                        <small>{{ [p.departemen, p.lokasi].filter(Boolean).join(' · ') || '—' }} · {{ p.berjalan }} berjalan</small>
                                    </div>
                                </el-option>
                            </el-select>
                            <!-- KEADAAN menjawab "mana yang menunggu SAYA?" —
                                 tanpa ini admin memindai lencana kartu satu per satu. -->
                            <el-select
                                v-model="keadaanPilih" multiple filterable clearable
                                collapse-tags collapse-tags-tooltip :max-collapse-tags="2"
                                class="plw-f" placeholder="Keadaan — semua"
                            >
                                <el-option value="PERLU" label="Perlu keputusan saya" />
                                <el-option value="NUNGGU" label="Menunggu hasil tes" />
                                <el-option value="JADWAL" label="Belum dijadwalkan" />
                                <el-option value="TERJADWAL" label="Sudah dijadwalkan" />
                                <el-option value="HOLD" label="Sedang ditahan" />
                                <!-- KONFIRMASI KEHADIRAN — jawaban kandidat atas undangannya. -->
                                <el-option value="KONF_MENUNGGU" label="Belum konfirmasi" />
                                <el-option value="KONF_LAIN" label="Minta jadwal lain" />
                                <el-option value="KONF_MUNDUR" label="Menyatakan mundur" />
                                <el-option value="KONF_LEWAT" label="Tidak menjawab (jadwal sudah mulai)" />
                                <el-option value="KONF_HADIR" label="Konfirmasi akan hadir" />
                                <el-option value="KONF_DITUNDA" label="Ditunda (pengganti belum terbit)" />
                            </el-select>
                        </div>

                        <!-- RENTANG TANGGAL MELAMAR — BARIS PENUH SENDIRI.
                             Satu kontrol, bukan dua kotak terpisah: "dari" yang
                             lebih baru daripada "sampai" adalah rentang kosong,
                             dan pemilih rentang menutup kemungkinan itu sejak
                             awal. Dulu ia berdesakan sebagai kolom keenam
                             selebar dropdown, memuat DUA tanggal + pemisah di
                             ruang yang cuma cukup untuk satu — placeholdernya
                             terpotong jadi "Melamar dar… s/d" dan tak seorang
                             pun tahu itu penyaring apa. -->
                        <div class="plw-frow">
                            <label class="plw-frow__lbl">
                                <i class="bi bi-calendar-range"></i>
                                Rentang tanggal melamar
                            </label>
                            <div class="plw-frow__isi">
                                <el-date-picker
                                    v-model="rangeTanggal"
                                    type="daterange" unlink-panels
                                    class="plw-f plw-f--tgl"
                                    start-placeholder="Tanggal awal" end-placeholder="Tanggal akhir"
                                    range-separator="→"
                                    value-format="YYYY-MM-DD" format="DD MMM YYYY"
                                />
                                <!-- Pintasan yang benar-benar ditanyakan orang.
                                     Tanpa ini, "yang masuk minggu ini" menuntut
                                     dua kali membuka kalender dan menghitung
                                     mundur tanggalnya sendiri. -->
                                <div class="plw-fquick">
                                    <button
                                        v-for="p in pintasTanggal" :key="p.hari"
                                        type="button" class="plw-fq" :class="{ 'is-on': pintasAktif === p.hari }"
                                        @click="pakaiPintasTanggal(p.hari)"
                                    >
                                        {{ p.label }}
                                    </button>
                                    <button v-if="rangeTanggal" type="button" class="plw-fq is-off" @click="rangeTanggal = null">
                                        <i class="bi bi-x-lg"></i> Semua
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- CHIP PENYARING AKTIF. Penyaring yang menyala di dalam
                             dropdown tidak terbaca sekilas — dan papan yang tiba-tiba
                             berisi tiga orang lalu terbaca sebagai data yang hilang,
                             bukan sebagai saringan yang sedang bekerja. -->
                        <div v-if="chipFilter.length" class="plw-chiprow">
                            <span v-for="ch in chipFilter" :key="ch.key" class="plw-fchip">
                                <span class="plw-fchip__k">{{ ch.jenis }}</span>
                                <span class="plw-ell">{{ ch.label }}</span>
                                <button type="button" title="Copot penyaring ini" @click="copotChip(ch.key)"><i class="bi bi-x-lg"></i></button>
                            </span>
                            <button type="button" class="plw-fclear" @click="bersihkanFilter">Bersihkan semua</button>
                        </div>
                        </div>
                    </div>

                    <!-- Latar gelap khusus ponsel: panel di sana melayang di atas
                         papan, dan tanpa latar ini papan di belakangnya masih
                         tampak bisa disentuh. -->
                    <div v-if="panelBuka" class="plw-ftirai" @click="panelBuka = false"></div>

                    <!-- FAB — pintu masuk penyaring di ponsel. Panelnya sendiri
                         disembunyikan di sana: enam isian bertumpuk memakan satu
                         layar penuh sebelum satu kandidat pun terlihat, padahal
                         yang dibuka orang di ponsel hampir selalu papannya. -->
                    <button
                        type="button" class="plw-fab" :class="{ 'is-aktif': chipFilter.length > 0 }"
                        :aria-label="panelBuka ? 'Tutup penyaring' : 'Buka penyaring'"
                        @click="panelBuka = !panelBuka"
                    >
                        <i class="bi" :class="panelBuka ? 'bi-x-lg' : 'bi-funnel-fill'"></i>
                        <span v-if="chipFilter.length && !panelBuka" class="plw-fab__n">{{ chipFilter.length }}</span>
                    </button>

                    <!-- ═══ MODE TAMPILAN — KANBAN / LIST ═══
                         Dua cara membaca populasi yang sama. Kanban menjawab "di
                         mana orang menumpuk"; list menjawab "siapa saja, dan apa
                         isinya" — termasuk kampus & tanggal melamar yang tak muat
                         di kartu kanban. -->
                    <div class="plw-moderow">
                        <!-- Pemilih tampilan & penyaring alur dikelompokkan di
                             SATU sisi. Keduanya menjawab pertanyaan yang sama —
                             "papan ini menggambarkan apa" — sementara teks hasil
                             di ujung kanan menjawab "isinya berapa". Dipisah
                             begini, matanya tidak perlu melompat. -->
                        <div class="plw-moderow__kiri">
                        <div class="plw-modes">
                            <button
                                type="button" class="plw-mode" :class="{ 'is-on': mode === 'kanban' }"
                                title="Tampilan papan kanban" @click="mode = 'kanban'"
                            >
                                <i class="bi bi-kanban-fill"></i><span>Kanban</span>
                            </button>
                            <button
                                type="button" class="plw-mode" :class="{ 'is-on': mode === 'list' }"
                                title="Tampilan daftar" @click="mode = 'list'"
                            >
                                <i class="bi bi-list-ul"></i><span>List</span>
                            </button>
                        </div>

                        <!-- ═══ PENYARING ALUR — HANYA BASIS JOB VACANCY ═══
                             Satu lowongan yang dibuka ulang di gelombang
                             berikutnya hampir selalu memakai alur yang sudah
                             diperbarui. Menggabungkan semuanya jadi satu papan
                             menghasilkan belasan kolom milik dua rombongan yang
                             tidak pernah bertemu — separuhnya selalu kosong, dan
                             tak satu pun menjawab "gelombang sekarang di mana".

                             Karena itu alur dipilih SATU, bawaannya yang terbaru
                             yang sudah punya pelamar. "Semua alur" tetap ada
                             sebagai pilihan, tapi bukan bawaan: ia bukan
                             pertanyaan sehari-hari.

                             Baru muncul saat memang ADA yang bisa dipilih —
                             pemilih berisi satu baris hanya menyita ruang tanpa
                             menawarkan apa pun. -->
                        <!-- Kelasnya `plw-alurfil`, BUKAN `plw-alur`: nama yang
                             kedua sudah dipakai panel progres alur di drawer
                             kandidat, dan CSS memenangkan definisi terakhir —
                             penyaring ini sempat mewarisi padding 18px milik
                             panel itu lalu tampil sebagai kotak gemuk tanpa
                             teks yang terbaca. -->
                        <div v-if="basisLoker && alurOpsi.length > 1" class="plw-alurfil">
                            <span class="plw-alurfil__ico"><i class="bi bi-signpost-split-fill"></i></span>
                            <span class="plw-alurfil__lbl">Alur</span>
                            <el-select
                                v-model="alurPilih" size="small" class="plw-alurfil__sel"
                                placeholder="Pilih alur" @change="gantiAlur"
                            >
                                <!-- Nomor versi disebut HANYA saat papan ini memang
                                     memuat lebih dari satu versi: versi baru mewarisi
                                     nama alur lamanya, jadi tanpa nomor itu dua baris
                                     di sini berbunyi persis sama.

                                     Label ringkas dipakai untuk kotak tertutup,
                                     dan barisnya sendiri digambar lengkap dengan
                                     jumlah pelamar — nama alur bisa panjang, dan
                                     yang terpotong lebih dulu harus keterangannya,
                                     bukan namanya. -->
                                <el-option
                                    v-for="a in alurOpsi" :key="a.id"
                                    :value="String(a.id)"
                                    :label="`${a.nama}${a.versi ? ' · v' + a.versi : ''}`"
                                >
                                    <span class="plw-alurfil__nama">{{ a.nama }}</span>
                                    <span v-if="a.versi" class="plw-alurfil__versi">v{{ a.versi }}</span>
                                    <span class="plw-alurfil__n" :class="{ 'is-nol': !a.pelamar }">{{ a.pelamar }} pelamar</span>
                                </el-option>
                                <el-option value="SEMUA" label="Semua alur">
                                    <span class="plw-alurfil__nama">Semua alur</span>
                                    <span class="plw-alurfil__n">digabung</span>
                                </el-option>
                            </el-select>
                        </div>
                        </div>

                        <div class="plw-hasil">{{ teksHasil }}</div>
                    </div>

                    <div v-if="loadingDetail" class="plw-load" style="padding: 3rem 0"><span class="plw-spin"></span> Memuat papan seleksi…</div>

                    <!-- KANBAN -->
                    <div v-else-if="mode === 'kanban'" class="plw-kanban" :class="{ 'is-gugur': statusTab === 'GUGUR', 'is-mundur': statusTab === 'MUNDUR' }">
                        <div v-for="(col, i) in kolomTampil" :key="col.kode || col.label" class="plw-col" :class="{ 'is-lawas': col.alurLain }">
                            <div class="plw-col__head">
                                <span class="plw-col__num" :class="{ 'is-hot': kartuKolom(col).length > 0 }">{{ String(i + 1).padStart(2, '0') }}</span>
                                <span class="plw-col__name">
                                    <span class="plw-ell">{{ col.label }}</span>
                                    <svg v-if="col.provider === 'THIRD_PARTY'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="flex: 0 0 auto"><rect x="4" y="4" width="16" height="16" rx="2" /><path d="M9 9h6v6H9z" /></svg>
                                    <!-- Kolom ini bukan bagian alur program sekarang: rombongan
                                         yang masih berjalan di alur sebelumnya. Tanpa keterangan,
                                         admin melihat tahap asing di papannya dan mengira alurnya
                                         rusak — lalu ragu mengambil keputusan di sana. -->
                                    <span
                                        v-if="col.alurLain"
                                        class="plw-col__lawas"
                                        :title="col.cadangan
                                            ? 'Tahap ini sudah tidak ada di alur mana pun. Kandidatnya tetap ditampilkan agar tidak terlewat.'
                                            : 'Tahap dari alur sebelumnya. Kandidat di sini melanjutkan alur yang mereka masuki saat melamar.'"
                                    >alur lama</span>
                                </span>
                                <span class="plw-col__count">{{ kartuKolom(col).length }}</span>
                                <!-- Penyaring MILIK KOLOM INI. Satu tahap bisa
                                     berisi 200 orang sementara tahap sesudahnya
                                     berisi 3 — memaksa keduanya ikut satu
                                     penyaring global berarti ikut menyaring
                                     tahap yang tidak perlu disaring. -->
                                <button
                                    v-if="kartuKolom(col).length || kolomTersaring(col)"
                                    type="button" class="plw-col__ico"
                                    :class="{ 'is-on': filterBuka === kunciKolom(col), 'is-aktif': kolomTersaring(col) }"
                                    title="Saring & urutkan kolom ini"
                                    @click="toggleFilterKolom(col)"
                                >
                                    <i class="bi bi-funnel-fill"></i>
                                </button>
                                <!-- Pemicu pilih-banyak milik KOLOM ini. Hanya
                                     muncul di tab Berjalan dan saat kolomnya
                                     memang berisi — menawarkannya pada kolom
                                     kosong hanya ikon mati. -->
                                <button
                                    v-if="statusTab === 'AKTIF' && kartuKolom(col).length"
                                    type="button" class="plw-col__ico" :class="{ 'is-on': kolomPilih === kunciKolom(col) }"
                                    :title="kolomPilih === kunciKolom(col) ? 'Selesai memilih' : 'Pilih banyak di kolom ini (untuk jadwal massal)'"
                                    @click="togglePilihKolom(col)"
                                >
                                    <i class="bi" :class="kolomPilih === kunciKolom(col) ? 'bi-check2-square' : 'bi-ui-checks'"></i>
                                </button>
                                <!-- JADWAL PENGISIAN KOLOM — hanya tahap Formulir yang
                                     oleh Master Alur diberi jadwal, di papan PROGRAM
                                     (server yang menandai `bolehBatas`; papan per-MPP
                                     tidak). Bukan posisi tahap, bukan nama kolom. -->
                                <button
                                    v-if="col.bolehBatas && statusTab === 'AKTIF'"
                                    type="button" class="plw-col__ico"
                                    :class="{ 'is-on': !!col.batasProgram && !kolomDilepas(col), 'is-aktif': kolomDilepas(col) || (!col.batasProgram && ringkasBatas(col).tutup > 0) }"
                                    :title="kolomDilepas(col)
                                        ? 'Master Alur: tahap ini tanpa jadwal — lepas batas waktu kandidat yang masih terikat jadwal lama'
                                        : col.batasProgram
                                            ? `Jadwal pengisian: ${tglJamId(col.bukaProgram)} – ${tglJamId(col.batasProgram)} · klik untuk memperpanjang`
                                            : 'Atur jadwal pengisian formulir kolom ini'"
                                    @click="askBatasKolom(col)"
                                >
                                    <i class="bi bi-hourglass-split"></i>
                                </button>
                            </div>

                            <!-- KETERANGAN JADWAL KOLOM — di SETIAP kolom yang oleh Master
                                 Alur diberi jadwal, terbaca tanpa membuka apa pun:
                                 jadwal yang berlaku, kandidat yang terkunci menunggu,
                                 atau aturan otomatisnya. Klik = atur / perpanjang. -->
                            <button
                                v-if="col.bolehBatas && statusTab === 'AKTIF'"
                                type="button" class="plw-colbatas"
                                :class="kelasKolomBatas(col)"
                                @click="askBatasKolom(col)"
                            >
                                <i class="bi" :class="ikonKolomBatas(col)"></i>
                                <!-- Master Alur sudah mematikan jadwal tahap ini, tapi masih
                                     ada yang terikat jadwal lama (snapshot) — tawarkan lepas. -->
                                <span v-if="kolomDilepas(col)">
                                    Master Alur: tanpa jadwal · {{ ringkasBatas(col).belum }} kandidat masih terikat jadwal lama.
                                    Lepas batas waktu
                                </span>
                                <span v-else-if="col.batasProgram">
                                    Buka {{ tglJamId(col.bukaProgram) }} · Batas {{ tglJamId(col.batasProgram) }}
                                    · {{ ringkasBatas(col).belum }} belum kirim<template v-if="ringkasBatas(col).lewat"> · <b>{{ ringkasBatas(col).lewat }} lewat</b></template>
                                </span>
                                <span v-else-if="ringkasBatas(col).tutup">
                                    Formulir terkunci — jadwal pengisian belum diatur ({{ ringkasBatas(col).tutup }} kandidat menunggu). Atur sekarang
                                </span>
                                <span v-else-if="col.batasMode === 'OTOMATIS'">
                                    Otomatis {{ col.batasHari || '—' }} hari sejak tahap terbuka · bisa diganti jadwal kolom
                                </span>
                                <span v-else>
                                    Dijadwalkan admin · jadwal kolom belum diatur
                                </span>
                            </button>

                            <!-- PANEL PENYARING KOLOM -->
                            <div v-if="filterBuka === kunciKolom(col)" class="plw-colf">
                                <el-input
                                    v-model="filterKol(col).q"
                                    size="small" clearable
                                    placeholder="Cari di kolom ini…"
                                >
                                    <template #prefix><i class="bi bi-search"></i></template>
                                </el-input>
                                <el-select v-model="filterKol(col).keadaan" size="small" clearable placeholder="Semua keadaan">
                                    <el-option value="PERLU" label="Perlu keputusan" />
                                    <el-option value="NUNGGU" label="Menunggu hasil tes" />
                                    <el-option value="JADWAL" label="Belum dijadwalkan" />
                                    <el-option value="TERJADWAL" label="Sudah dijadwalkan" />
                                    <el-option value="HOLD" label="Ditahan" />
                                    <el-option value="BATAS_TUTUP" label="Formulir belum dibuka" />
                                    <el-option value="BATAS_LEWAT" label="Lewat batas pengisian" />
                                    <el-option value="BATAS_DEKAT" label="Batas ≤ 24 jam" />
                                    <el-option value="KONF_MENUNGGU" label="Belum konfirmasi" />
                                    <el-option value="KONF_LAIN" label="Minta jadwal lain" />
                                    <el-option value="KONF_MUNDUR" label="Menyatakan mundur" />
                                    <el-option value="KONF_LEWAT" label="Tidak menjawab" />
                                    <el-option value="KONF_HADIR" label="Akan hadir" />
                                    <el-option value="KONF_DITUNDA" label="Ditunda" />
                                </el-select>
                                <el-select v-model="filterKol(col).urut" size="small" placeholder="Urutkan">
                                    <el-option value="LAMA" label="Terlama menunggu" />
                                    <el-option value="BARU" label="Terbaru melamar" />
                                    <el-option value="NAMA" label="Nama A–Z" />
                                </el-select>
                                <button v-if="kolomTersaring(col)" type="button" class="plw-colf__reset" @click="bersihkanFilterKolom(col)">
                                    <i class="bi bi-arrow-counterclockwise"></i> Bersihkan kolom ini
                                </button>
                            </div>
                            <!-- PILIH BANYAK ADA DI KOLOM, bukan di atas papan.
                                 Penjadwalan massal selalu menyangkut SATU tahap —
                                 "semua yang di Wawancara HR" — jadi memilihnya
                                 dari sini menghilangkan satu langkah berpikir dan
                                 sekaligus mustahil salah kolom. -->
                            <!-- Dua baris, bukan satu. Kolom papan cuma ±280px;
                                 memaksa "Pilih semua" + 3 tombol aksi berjejer
                                 membuat labelnya terpotong justru saat jumlah
                                 tombolnya paling banyak. Baris atas untuk
                                 memilih, baris bawah untuk bertindak. -->
                            <div v-if="kolomPilih === kunciKolom(col)" class="plw-selbar">
                                <div class="plw-selbar__head">
                                    <button type="button" class="plw-selbar__all" @click="pilihKolom(col)">
                                        <i class="bi" :class="kolomTerpilihPenuh(col) ? 'bi-check-square-fill' : 'bi-square'"></i>
                                        {{ kolomTerpilihPenuh(col) ? 'Batal semua' : 'Pilih semua' }}
                                    </button>
                                    <span class="plw-selbar__n">{{ terpilih.length }}</span>
                                    <button type="button" class="plw-selbar__x" title="Selesai memilih" @click="tutupPilihKolom">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <div class="plw-selbar__aksi">
                                    <button
                                        type="button" class="plw-selbar__go" :disabled="!gerbangJadwalMassal.boleh"
                                        :title="gerbangJadwalMassal.sebab"
                                        :onClick="!gerbangJadwalMassal.boleh ? null : askJadwalMassal"
                                    >
                                        <i class="bi bi-calendar-plus"></i> Jadwalkan
                                    </button>
                                    <!-- KIRIM ULANG EMAIL JADWAL — kandidat terpilih yang
                                         sudah dijadwalkan dan masih ditunggu (mis. MCU
                                         mandiri yang belum mengunggah). Maks. sekali kirim
                                         sama dengan batas massal lain. -->
                                    <button
                                        v-if="aktivitasKirimUlang.length"
                                        type="button" class="plw-selbar__ulang"
                                        :title="`Kirim ulang email jadwal ke kandidat terpilih (maks. ${batasKirimUlang} sekali kirim)`"
                                        @click="askKirimUlangMassal"
                                    >
                                        <i class="bi bi-envelope-arrow-up"></i> Kirim ulang
                                    </button>
                                    <!-- TAHAN MASSAL. Penahanan hampir selalu lahir
                                         dari satu peristiwa yang mengenai banyak
                                         orang sekaligus ("MPP belum turun"), jadi
                                         menahannya satu per satu lewat drawer
                                         berarti mengulang pekerjaan yang sama
                                         belasan kali. -->
                                    <!-- KEPUTUSAN MASSAL. Satu angkatan diputus dalam
                                         satu peristiwa — sesudah rapat panel, sesudah
                                         hasil tes turun. Membuka drawer satu per satu
                                         untuk itu berarti mengulang pekerjaan yang sama
                                         puluhan kali, dan yang terlewat di tengah daftar
                                         tidak meninggalkan jejak apa pun. -->
                                    <button
                                        v-if="bolehPutus"
                                        type="button" class="plw-selbar__putus" :disabled="!gerbangPutusMassal.boleh"
                                        :title="gerbangPutusMassal.sebab"
                                        :onClick="!gerbangPutusMassal.boleh ? null : askPutusMassal"
                                    >
                                        <i class="bi bi-hammer"></i> Keputusan
                                    </button>
                                    <button
                                        type="button" class="plw-selbar__hold" :disabled="!bisaTahanMassal.length"
                                        :title="bisaTahanMassal.length
                                            ? `Tahan ${bisaTahanMassal.length} kandidat terpilih`
                                            : 'Semua yang terpilih sudah ditahan atau tahapnya sudah diputus'"
                                        :onClick="!bisaTahanMassal.length ? null : () => askHoldMassal(true)"
                                    >
                                        <i class="bi bi-pause-circle"></i> Tahan
                                    </button>
                                    <button
                                        v-if="bisaLepasMassal.length"
                                        type="button" class="plw-selbar__lepas"
                                        :title="`Lanjutkan ${bisaLepasMassal.length} kandidat yang sedang ditahan`"
                                        @click="askHoldMassal(false)"
                                    >
                                        <i class="bi bi-play-circle"></i> Lanjutkan
                                    </button>
                                    <!-- JADWAL MASSAL — atur (yang belum berjadwal) /
                                         perpanjang (yang sudah) untuk yang terpilih.
                                         Hanya muncul bila ada yang sedang mengisi formulir. -->
                                    <button
                                        v-if="bisaBatasMassal.length"
                                        type="button" class="plw-selbar__batas"
                                        :title="`Atur / perpanjang jadwal pengisian ${bisaBatasMassal.length} kandidat terpilih`"
                                        @click="askBatasKandidat(bisaBatasMassal)"
                                    >
                                        <i class="bi bi-hourglass-split"></i> Jadwal
                                    </button>
                                </div>
                                <!-- SEBAB TOMBOL MATI, DITULIS — bukan cuma
                                     disembunyikan di `title`. Tooltip tidak pernah
                                     muncul di layar sentuh, dan tombol kelabu tanpa
                                     keterangan terbaca sebagai "hak akses saya
                                     kurang", bukan "centang saya kelebihan tiga". -->
                                <p v-if="peringatanBatasMassal" class="plw-batas">
                                    <i class="bi bi-shield-exclamation"></i>
                                    <span>{{ peringatanBatasMassal }}</span>
                                </p>
                            </div>

                            <div class="plw-col__cards">
                                <div
                                    v-for="r in kartuKolom(col)" :key="r.id"
                                    class="plw-card"
                                    :class="{ 'is-pilih': kolomPilih === kunciKolom(col), 'is-terpilih': terpilih.includes(r.id), 'is-hold': !!r.hold }"
                                    role="button" tabindex="0"
                                    @click="klikKartu(col, r)"
                                    @keyup.enter="klikKartu(col, r)"
                                >
                                    <div class="plw-card__toprow">
                                        <span v-if="kolomPilih === kunciKolom(col)" class="plw-card__cek">
                                            <i class="bi" :class="terpilih.includes(r.id) ? 'bi-check-square-fill' : 'bi-square'"></i>
                                        </span>
                                        <span class="plw-card__avatar" :style="{ background: avatarBg(r) }">{{ inisial(r.pelamar) }}</span>
                                        <span class="plw-card__id">
                                            <span class="plw-card__name">{{ r.pelamar }}</span>
                                            <span class="plw-card__code">{{ r.lamaranKode }}</span>
                                        </span>
                                        <!-- TAHAN / LANJUTKAN — ikon saja di pojok.
                                             Ia jalan keluar yang selalu tersedia,
                                             bukan tindakan utama; tombol berteks di
                                             baris bawah tadi berebut tempat dengan
                                             lencana keadaan dan membuat kartunya
                                             penuh sesak.

                                             TANPA SYARAT apa pun: alasan menahan
                                             justru paling sering muncul sebelum
                                             segala sesuatunya lengkap — kuota belum
                                             turun, user belum menjawab. -->
                                        <button
                                            v-if="kolomPilih !== kunciKolom(col) && r.tahapId && r.statusLamaran === 'BERJALAN'"
                                            type="button" class="plw-card__hold" :class="{ 'is-on': !!r.hold }"
                                            :title="r.hold ? 'Sedang ditahan — klik untuk melanjutkan' : 'Tahan kandidat ini (tanpa email ke kandidat)'"
                                            @click.stop="askHold(!r.hold, r)"
                                        >
                                            <i class="bi" :class="r.hold ? 'bi-play-fill' : 'bi-pause-fill'"></i>
                                        </button>
                                    </div>

                                    <div class="plw-card__pos">{{ r.posisi }}</div>
                                    <!-- Konteks lowongan: SATU baris, dipotong rapi.
                                         Sebelumnya departemen & lokasi dibiarkan
                                         mengalir dan menjebol tepi kartu. -->
                                    <div v-if="r.departemen || r.lokasi" class="plw-card__meta">
                                        <span v-if="r.departemen" :title="r.departemen"><i class="bi bi-diagram-3"></i> {{ r.departemen }}</span>
                                        <span v-if="r.lokasi" :title="r.lokasi"><i class="bi bi-geo-alt"></i> {{ r.lokasi }}</span>
                                    </div>

                                    <div class="plw-card__foot">
                                        <span class="plw-card__chip" :class="'tone-' + r.badge.tone">{{ r.badge.teks }}</span>
                                        <!-- Batas pengisian formulir — hanya selama
                                             formulirnya belum terkirim. -->
                                        <span
                                            v-if="r.batas && !r.batas.terkirim && (r.batas.batas || r.batas.belumDiatur)"
                                            class="plw-card__batas" :class="kelasBatas(r.batas)"
                                            :title="r.batas.belumDiatur ? 'Formulir terkunci — jadwal pengisian belum diatur' : 'Jadwal pengisian: ' + (r.batas.bukaTeks || 'terbuka') + ' s/d ' + r.batas.teks"
                                        >
                                            <i class="bi" :class="r.batas.terkunci ? 'bi-lock-fill' : 'bi-hourglass-split'"></i> {{ labelBatas(r.batas) }}
                                        </span>
                                        <!-- Konfirmasi kehadiran yang paling butuh perhatian
                                             (jadwal lain > mundur > tak menjawab > belum). -->
                                        <span
                                            v-if="konfPerKartu[r.id]"
                                            class="plw-card__konf" :style="{ '--nada': konfPerKartu[r.id].warna || '#94a3b8' }"
                                            :title="`${konfPerKartu[r.id].aktivitas}: ${konfPerKartu[r.id].label}`"
                                        >
                                            <i class="bi" :class="konfPerKartu[r.id].ikon || 'bi-hourglass-split'"></i> {{ konfPerKartu[r.id].pendek }}
                                        </span>
                                        <span v-if="r.waktuLamar" class="plw-card__umur" :title="'Melamar ' + tglId(r.waktuLamar)">
                                            <i class="bi bi-clock-history"></i> {{ umurHari(r.waktuLamar) }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="!kartuKolom(col).length" class="plw-col__empty">—</div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ MODE LIST ═══
                         Satu baris per kandidat, berkolom tetap. Yang tak muat di
                         kartu kanban justru muncul di sini — kampus dan tanggal
                         melamar — sebab keduanya baru berguna ketika kandidat
                         dibandingkan berjajar, bukan saat dilihat satu per satu.

                         Di layar sempit tiap baris melipat jadi kartu (lihat CSS):
                         tabel tujuh kolom pada 390px hanya menghasilkan tulisan
                         setinggi satu huruf, dan menggeser mendatar untuk membaca
                         nama orang bukan cara siapa pun bekerja. -->
                    <div v-else-if="barisList.length" class="plw-list">
                        <!-- ═══ BILAH PILIH-BANYAK MODE LIST ═══
                             Di kanban, memilih banyak terkurung di SATU kolom —
                             dan itu benar di sana: kolomnya sendiri yang berarti
                             "orang-orang di tahap ini". Mode list tidak punya
                             kolom; yang ada penyaring. Jadi di sini pilihannya
                             bebas melintasi tahap, dan yang menjaga kebenaran
                             tetap sama: tiap tombol massal menyaring sendiri
                             siapa yang layak, lalu menyebut yang dilewati
                             sebelum apa pun disimpan. -->
                        <transition name="plw-sel">
                            <div v-if="terpilih.length" class="plw-lsel">
                                <span class="plw-lsel__n">{{ terpilih.length }}</span>
                                <span class="plw-lsel__txt">kandidat terpilih</span>
                                <!-- Jalan kedua ke "pilih semua". Di layar sempit
                                     kepala tabel disembunyikan seluruhnya (barisnya
                                     melipat jadi kartu), jadi kotak centang di sana
                                     tidak pernah terjangkau — dan tanpa tombol ini
                                     dua puluh kandidat harus dicentang satu-satu
                                     dengan jempol. -->
                                <button type="button" class="plw-lsel__all" @click="toggleHalaman">
                                    <i class="bi" :class="halamanTercentangPenuh ? 'bi-x-square' : 'bi-check2-square'"></i>
                                    {{ halamanTercentangPenuh ? 'Batal sehalaman' : 'Semua di halaman' }}
                                </button>
                                <div class="plw-lsel__aksi">
                                    <button
                                        type="button" class="plw-selbar__go" :disabled="!gerbangJadwalMassal.boleh"
                                        :title="gerbangJadwalMassal.sebab"
                                        :onClick="!gerbangJadwalMassal.boleh ? null : askJadwalMassal"
                                    >
                                        <i class="bi bi-calendar-plus"></i> Jadwalkan
                                    </button>
                                    <!-- KIRIM ULANG EMAIL JADWAL — kandidat terpilih yang
                                         sudah dijadwalkan dan masih ditunggu (mis. MCU
                                         mandiri yang belum mengunggah). Maks. sekali kirim
                                         sama dengan batas massal lain. -->
                                    <button
                                        v-if="aktivitasKirimUlang.length"
                                        type="button" class="plw-selbar__ulang"
                                        :title="`Kirim ulang email jadwal ke kandidat terpilih (maks. ${batasKirimUlang} sekali kirim)`"
                                        @click="askKirimUlangMassal"
                                    >
                                        <i class="bi bi-envelope-arrow-up"></i> Kirim ulang
                                    </button>
                                    <button
                                        v-if="bolehPutus"
                                        type="button" class="plw-selbar__putus" :disabled="!gerbangPutusMassal.boleh"
                                        :title="gerbangPutusMassal.sebab"
                                        :onClick="!gerbangPutusMassal.boleh ? null : askPutusMassal"
                                    >
                                        <i class="bi bi-hammer"></i> Keputusan
                                    </button>
                                    <button
                                        type="button" class="plw-selbar__hold" :disabled="!bisaTahanMassal.length"
                                        :title="bisaTahanMassal.length
                                            ? `Tahan ${bisaTahanMassal.length} kandidat terpilih`
                                            : 'Semua yang terpilih sudah ditahan atau tahapnya sudah diputus'"
                                        :onClick="!bisaTahanMassal.length ? null : () => askHoldMassal(true)"
                                    >
                                        <i class="bi bi-pause-circle"></i> Tahan
                                    </button>
                                    <button
                                        v-if="bisaLepasMassal.length"
                                        type="button" class="plw-selbar__lepas"
                                        :title="`Lanjutkan ${bisaLepasMassal.length} kandidat yang sedang ditahan`"
                                        @click="askHoldMassal(false)"
                                    >
                                        <i class="bi bi-play-circle"></i> Lanjutkan
                                    </button>
                                    <button
                                        v-if="bisaBatasMassal.length"
                                        type="button" class="plw-selbar__batas"
                                        :title="`Atur / perpanjang jadwal pengisian ${bisaBatasMassal.length} kandidat terpilih`"
                                        @click="askBatasKandidat(bisaBatasMassal)"
                                    >
                                        <i class="bi bi-hourglass-split"></i> Jadwal
                                    </button>
                                </div>
                                <!-- Sama dengan bilah papan: sebabnya ditulis, tidak
                                     cuma dititipkan ke tooltip. Di sini ia melipat ke
                                     baris sendiri (`plw-batas--lsel`) karena bilah
                                     daftar tersusun mendatar. -->
                                <p v-if="peringatanBatasMassal" class="plw-batas plw-batas--lsel">
                                    <i class="bi bi-shield-exclamation"></i>
                                    <span>{{ peringatanBatasMassal }}</span>
                                </p>
                                <button type="button" class="plw-lsel__x" title="Batalkan pilihan" @click="terpilih = []">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </transition>

                        <div class="plw-list__head">
                            <!-- Centang kepala hanya menyentuh HALAMAN INI. Menyapu
                                 seluruh hasil saringan dari satu centang terlalu
                                 mudah dilakukan tanpa sengaja, dan yang tersapu
                                 tidak terlihat di layar untuk diperiksa. -->
                            <span class="plw-lcek plw-lcek--head">
                                <button
                                    type="button" class="plw-tick"
                                    :class="{ 'is-on': halamanTercentangPenuh, 'is-half': halamanTercentangSebagian }"
                                    :title="halamanTercentangPenuh ? 'Batalkan pilihan di halaman ini' : 'Pilih semua di halaman ini'"
                                    :aria-pressed="halamanTercentangPenuh"
                                    @click="toggleHalaman"
                                >
                                    <i v-if="halamanTercentangPenuh" class="bi bi-check-lg"></i>
                                    <i v-else-if="halamanTercentangSebagian" class="bi bi-dash-lg"></i>
                                </button>
                            </span>
                            <span>KANDIDAT</span>
                            <span>POSISI</span>
                            <span>TAHAP</span>
                            <span>KAMPUS</span>
                            <span>BERKAS</span>
                            <span>KEADAAN</span>
                            <span>MELAMAR</span>
                            <span style="text-align: right">AKSI</span>
                        </div>
                        <div
                            v-for="r in barisList" :key="r.id"
                            class="plw-lrow" :class="{ 'is-hold': !!r.hold, 'is-terpilih': terpilih.includes(r.id) }"
                            role="button" tabindex="0"
                            @click="setAntrean(barisList, 'Daftar'); bukaKandidat(r)"
                            @keyup.enter="setAntrean(barisList, 'Daftar'); bukaKandidat(r)"
                        >
                            <!-- .stop: barisnya sendiri membuka kandidat. Tanpa ini
                                 mencentang orang justru membuka profilnya, dan
                                 memilih dua puluh orang berarti menutup dua puluh
                                 modal. -->
                            <span class="plw-lcek" @click.stop>
                                <button
                                    type="button" class="plw-tick" :class="{ 'is-on': terpilih.includes(r.id) }"
                                    :title="terpilih.includes(r.id) ? `Batal memilih ${r.pelamar}` : `Pilih ${r.pelamar}`"
                                    :aria-pressed="terpilih.includes(r.id)"
                                    @click="toggleBarisList(r)"
                                >
                                    <i v-if="terpilih.includes(r.id)" class="bi bi-check-lg"></i>
                                </button>
                            </span>
                            <span class="plw-lcell plw-lcell--who">
                                <span class="plw-card__avatar" :style="{ background: avatarBg(r) }">{{ inisial(r.pelamar) }}</span>
                                <span style="min-width: 0">
                                    <span class="plw-lname">{{ r.pelamar }}</span>
                                    <span class="plw-lcode">{{ r.lamaranKode }}</span>
                                </span>
                            </span>
                            <span class="plw-lcell" data-k="Posisi">
                                <span class="plw-ell" :title="r.posisi">{{ r.posisi || '—' }}</span>
                            </span>
                            <span class="plw-lcell" data-k="Tahap">
                                <span class="plw-lpill" :title="r.tahap">{{ r.tahap || '—' }}</span>
                            </span>
                            <span class="plw-lcell" data-k="Kampus">
                                <span class="plw-ell" :title="r.kampus || 'Kampus belum terisi di formulir'">{{ r.kampus || '—' }}</span>
                            </span>
                            <!-- BERKAS — jumlah lampiran formulir kandidat.
                                 "Belum ada" ditandai berbeda, bukan sekadar angka
                                 nol: itu satu-satunya keadaan yang menuntut
                                 tindakan, dan nol di tengah deretan angka lain
                                 terlalu mudah terlewat. -->
                            <span class="plw-lcell" data-k="Berkas">
                                <span
                                    class="plw-ldok" :class="{ 'is-kosong': !r.jmlBerkasForm }"
                                    :title="r.jmlBerkasForm ? `${r.jmlBerkasForm} berkas terlampir di formulir` : 'Kandidat belum melampirkan berkas apa pun'"
                                >
                                    <i class="bi" :class="r.jmlBerkasForm ? 'bi-paperclip' : 'bi-dash-circle'"></i>
                                    {{ r.jmlBerkasForm ? `${r.jmlBerkasForm} berkas` : 'Belum ada' }}
                                </span>
                            </span>
                            <span class="plw-lcell" data-k="Keadaan">
                                <span class="plw-card__chip" :class="'tone-' + r.badge.tone" style="margin-top: 0">{{ r.badge.teks }}</span>
                            </span>
                            <span class="plw-lcell" data-k="Melamar">
                                <span v-if="r.waktuLamar" class="plw-ltgl" :title="'Melamar ' + tglId(r.waktuLamar)">
                                    {{ tglId(r.waktuLamar) }}<small>{{ umurHari(r.waktuLamar) }}</small>
                                </span>
                                <span v-else>—</span>
                            </span>
                            <span class="plw-lcell plw-lcell--act">
                                <button
                                    v-if="r.tahapId && r.statusLamaran === 'BERJALAN'"
                                    type="button" class="plw-card__hold" :class="{ 'is-on': !!r.hold }"
                                    :title="r.hold ? 'Sedang ditahan — klik untuk melanjutkan' : 'Tahan kandidat ini (tanpa email ke kandidat)'"
                                    @click.stop="askHold(!r.hold, r)"
                                >
                                    <i class="bi" :class="r.hold ? 'bi-play-fill' : 'bi-pause-fill'"></i>
                                </button>
                                <button type="button" class="plw-ldetail" @click.stop="setAntrean(barisList, 'Daftar'); bukaKandidat(r)">Detail</button>
                            </span>
                        </div>
                    </div>

                    <!-- TIDAK ADA YANG COCOK. Dibedakan dari "program ini memang
                         belum punya pelamar": yang pertama diperbaiki dengan
                         mencopot saringan, yang kedua tidak bisa diperbaiki
                         siapa pun — dan menyamakan keduanya membuat admin
                         mencari-cari tombol yang tak akan menolongnya. -->
                    <div v-else class="plw-kosong">
                        <span class="plw-kosong__ico"><i class="bi bi-search"></i></span>
                        <div class="plw-kosong__judul">{{ chipFilter.length ? 'Tidak ada kandidat yang cocok' : 'Belum ada kandidat' }}</div>
                        <div class="plw-kosong__sub">
                            {{ chipFilter.length
                                ? 'Tidak ada yang memenuhi saringan ini. Copot salah satunya untuk melebarkan hasil.'
                                : 'Tab ini masih kosong untuk program tersebut.' }}
                        </div>
                        <button v-if="chipFilter.length" type="button" class="plw-kosong__btn" @click="bersihkanFilter">Bersihkan saringan</button>
                    </div>

                    <!-- PAGINASI — hanya mode list. Kanban tidak dipaginasi:
                         kolomnya sudah membagi populasi, dan memotongnya lagi per
                         halaman berarti tumpukan yang terbaca di layar bukan
                         tumpukan yang sebenarnya. -->
                    <div v-if="mode === 'list' && totalList > 0" class="plw-pagerbar">
                        <div class="plw-pagerbar__info">Halaman {{ halamanList }} dari {{ totalHalamanList }} · {{ totalList }} kandidat</div>
                        <div class="plw-pagerbar__nav">
                            <button type="button" :disabled="halamanList <= 1" title="Sebelumnya" :onClick="halamanList <= 1 ? null : () => listPage = halamanList - 1">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button
                                v-for="(n, i) in nomorHalaman" :key="i"
                                type="button" class="plw-pnum"
                                :class="{ 'is-on': n === halamanList, 'is-gap': n === '…' }"
                                :disabled="n === '…'"
                                :onClick="n === '…' ? null : () => n !== '…' && (listPage = n)"
                            >{{ n }}</button>
                            <button type="button" :disabled="halamanList >= totalHalamanList" title="Berikutnya" @click="listPage = halamanList + 1">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <div class="plw-pagerbar__per">
                            Per halaman
                            <el-select v-model="listPer" class="plw-perpage">
                                <el-option v-for="n in [10, 20, 50, 100]" :key="n" :value="n" :label="String(n)" />
                            </el-select>
                        </div>
                    </div>
                </template>
                <div v-else class="plw-empty" style="padding: 4rem 0">Pilih {{ basisLoker ? 'job vacancy' : 'program' }} di panel kiri untuk melihat papan seleksi.</div>
            </div>
        </main>

        <!-- ═══ MODAL DETAIL KANDIDAT ═══
             Dulu sebuah drawer selebar 560px yang merayap dari kanan. Bentuk itu
             benar untuk "mengintip sambil papan tetap terlihat" — tapi bukan
             itu yang terjadi di sini: begitu drawer terbuka, seluruh pekerjaan
             pindah ke dalamnya (rapor tes, berkas, keputusan), dan 560px terlalu
             sempit untuk semuanya. Kisi biodata jadi satu kolom, tombol
             keputusan terpotong per kata, dan riwayat kerja digulung berkali-kali.

             Sekarang: modal EVO ber-ukuran XL, isinya dibagi TIGA TAB. Tab
             memisahkan tiga pekerjaan yang memang tidak dilakukan bersamaan —
             menilai hasil tes, memeriksa berkas, dan melihat posisi kandidat di
             alur — sehingga masing-masing dapat lebar penuh. -->
        <AdminModal
            :show="!!detailKandidat"
            size="full"
            icon="bi-person-vcard-fill"
            :title="detailKandidat ? detailKandidat.pelamar : ''"
            :subtitle="detailKandidat ? `${detailKandidat.posisi} · ${detailKandidat.lamaranKode}` : ''"
            foot-note=""
            foot-blok
            @close="tutupKandidat"
        >
            <!-- HERO — identitas, konteks lowongan, kontak, dan tab.
                 Ditaruh di slot `sticky` AdminModal, BUKAN di isi biasa: slot itu
                 duduk tepat di puncak area gulir tanpa padding yang perlu
                 dinetralkan margin negatif. Bentuk lamanya menetralkan padding
                 dengan angka yang ditulis ulang di sini, dan selisih beberapa
                 piksel di antara keduanya menjadi celah — jalur tempat baris di
                 bawahnya terlihat menyembul di atas hero saat isi digulir. -->
            <template v-if="detailKandidat" #sticky>
                <div class="plw-hero">
                    <div class="plw-hero__row">
                        <div class="plw-hero__avatar" :style="{ background: avatarBg(detailKandidat) }">{{ inisial(detailKandidat.pelamar) }}</div>
                        <div style="flex: 1; min-width: 0">
                            <div class="plw-hero__tags">
                                <span class="plw-drawer__tag" :class="detailKandidat.kategori === 'MT' ? 'is-mt' : 'is-rek'">{{ katLabel(detailKandidat.kategori) }}</span>
                                <span class="plw-card__chip" :class="'tone-' + detailKandidat.badge.tone" style="margin-top: 0">{{ detailKandidat.badge.teks }}</span>
                            </div>
                            <!-- Nama RESMI dari formulir. Nama akun disebut di
                                 bawahnya HANYA bila berbeda: rekruter perlu tahu
                                 akun mana yang akan menerima surelnya, dan
                                 menyembunyikannya membuat "SUPRIADI MAMI PERI"
                                 di layar ini tak bisa dicocokkan dengan
                                 "SUPRIADI" di kotak masuk. -->
                            <div class="plw-hero__name">{{ detailKandidat.pelamar }}</div>
                            <div v-if="namaAkunBeda(detailKandidat)" class="plw-drawer__akun">
                                <i class="bi bi-person-badge"></i> Nama akun: {{ detailKandidat.pelamarAkun }}
                            </div>
                            <div class="plw-drawer__meta">{{ detailKandidat.posisi }} · <span class="plw-mono">{{ detailKandidat.lamaranKode }}</span></div>
                            <!-- Konteks lowongan & kontak: dulu admin harus menebak
                                 atau membuka layar lain untuk tahu ini. -->
                            <div class="plw-drawer__chips">
                                <span v-if="detailKandidat.departemen"><i class="bi bi-diagram-3"></i> {{ detailKandidat.departemen }}</span>
                                <span v-if="detailKandidat.lokasi"><i class="bi bi-geo-alt"></i> {{ detailKandidat.lokasi }}</span>
                                <span v-if="detailKandidat.level"><i class="bi bi-bar-chart-steps"></i> {{ detailKandidat.level }}</span>
                                <span v-if="detailKandidat.kampus"><i class="bi bi-mortarboard-fill"></i> {{ detailKandidat.kampus }}</span>
                                <span v-if="detailKandidat.mppRef" class="is-mpp">{{ detailKandidat.mppRef }}</span>
                                <span v-if="detailKandidat.waktuLamar"><i class="bi bi-clock-history"></i> {{ tglId(detailKandidat.waktuLamar) }} · {{ umurHari(detailKandidat.waktuLamar) }}</span>
                                <a v-if="detailKandidat.email" :href="`mailto:${detailKandidat.email}`" class="is-link"><i class="bi bi-envelope"></i> {{ detailKandidat.email }}</a>
                                <a v-if="detailKandidat.hp" :href="`https://wa.me/${String(detailKandidat.hp).replace(/\D/g, '')}`" target="_blank" rel="noopener" class="is-link"><i class="bi bi-whatsapp"></i> {{ detailKandidat.hp }}</a>
                            </div>
                        </div>
                        <!-- CETAK LAPORAN — tersedia untuk SETIAP kandidat di
                             papan, apa pun tahap & statusnya. Laporan paling
                             sering justru diminta untuk yang sudah selesai
                             (arsip keputusan), bukan yang sedang berjalan. -->
                        <!-- Pemanggil kolom pratinjau yang sedang dilipat.
                             Berdiri di hero, bukan di tempat kolomnya tadi:
                             begitu kolomnya hilang, tak ada lagi "tempat itu" —
                             yang tersisa cuma tepi kanan jendela, dan tombol
                             yang menempel di tepi kosong terbaca sebagai
                             hiasan, bukan sebagai sesuatu yang bisa ditekan. -->
                        <button
                            v-if="!dokPanel"
                            type="button" class="plw-drawer__cetak is-dok"
                            title="Tampilkan kolom pratinjau berkas di kanan"
                            @click="setDokPanel(true)"
                        >
                            <i class="bi bi-folder2-open"></i> Berkas
                            <span v-if="dokSemua.length" class="plw-drawer__cetakn">{{ dokSemua.length }}</span>
                        </button>
                        <button type="button" class="plw-drawer__cetak" title="Cetak berkas seleksi kandidat (PDF)" @click="bukaStudio">
                            <i class="bi bi-printer-fill"></i> Cetak Berkas
                        </button>
                        <!-- KIRIM ULANG EMAIL HASIL. Berdiri di kepala drawer, bukan di
                             dalam salah satu tab: yang dicari orang saat kandidat menelepon
                             "saya tidak menerima email apa pun" adalah tombol ini, dan
                             menyembunyikannya di balik tab menuntut ia tahu lebih dulu tab
                             mana. -->
                        <button
                            type="button" class="plw-drawer__cetak"
                            title="Kirim ulang email hasil salah satu tahap kepada kandidat"
                            @click="bukaEmailUlang"
                        >
                            <i class="bi bi-envelope-arrow-up-fill"></i> Kirim Ulang Email
                        </button>
                    </div>

                    <!-- ANTREAN TINJAU — maju/mundur tanpa menutup jendela.
                         Rekruter yang meninjau empat belas orang di satu kolom
                         tidak seharusnya menutup, mencari, lalu membuka lagi
                         empat belas kali; yang dikerjakannya satu tumpukan,
                         bukan satu orang. Nomor urutnya disebutkan supaya jelas
                         seberapa jauh lagi tumpukan itu. -->
                    <div v-if="adaAntreanTinjau" class="plw-nav">
                        <button
                            type="button" class="plw-nav__b"
                            :disabled="!tetanggaTinjau.mundur"
                            :title="tetanggaTinjau.mundur ? 'Kandidat sebelumnya (panah kiri)' : 'Sudah di kandidat pertama'"
                            @click="geserTinjau(-1)"
                        >
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span class="plw-nav__teks">
                            <b>{{ indeksTinjau + 1 }}</b> dari {{ antreanTinjau.length }}
                            <em v-if="antreanLabel">{{ antreanLabel }}</em>
                        </span>
                        <button
                            type="button" class="plw-nav__b"
                            :disabled="!tetanggaTinjau.maju"
                            :title="tetanggaTinjau.maju ? 'Kandidat berikutnya (panah kanan)' : 'Sudah di kandidat terakhir'"
                            @click="geserTinjau(1)"
                        >
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>

                    <div class="plw-mtabs" role="tablist">
                        <button
                            v-for="t in tabDetail" :key="t.key"
                            type="button" class="plw-mtab" :class="{ 'is-on': tabAktif === t.key }"
                            role="tab" :aria-selected="tabAktif === t.key"
                            @click="tabAktif = t.key"
                        >
                            <i class="bi" :class="t.ikon"></i><span>{{ t.label }}</span>
                            <span v-if="t.jumlah" class="plw-mtab__n">{{ t.jumlah }}</span>
                        </button>
                    </div>
                </div>
            </template>

            <template v-if="detailKandidat">
                <div v-show="tabAktif === 'rapor'" class="plw-tabpane">
                    <!-- Info tahap -->
                    <div class="plw-infocard">
                        <div class="plw-infogrid">
                            <div>
                                <div class="plw-klabel">TAHAP</div>
                                <div class="plw-kval">{{ detailKandidat.tahap }}</div>
                            </div>
                            <div>
                                <div class="plw-klabel">POSISI TAHAP</div>
                                <div class="plw-kval">{{ detailKandidat.urutan }} / {{ detailKandidat.totalTahap }}</div>
                            </div>
                            <div>
                                <div class="plw-klabel">STATUS</div>
                                <div class="plw-status" :class="'st-' + detailKandidat.statusLamaran.toLowerCase()">
                                    <span class="plw-status__dot"></span>{{ statusLabel(detailKandidat.statusLamaran) }}
                                </div>
                            </div>
                            <div style="grid-column: 1 / -1">
                                <div class="plw-klabel">PROGRESS ALUR</div>
                                <div class="plw-segs">
                                    <div v-for="n in detailKandidat.totalTahap" :key="n" class="plw-segbar" :class="segKelas(n)"></div>
                                </div>
                            </div>
                        </div>
                        <!-- Mode OTOMATIS di Master Alur: admin memang tidak berperan
                             memutus di sini, jadi alasannya dijelaskan, bukan sekadar
                             tombolnya hilang tanpa keterangan. -->
                        <div v-if="detailKandidat.otomatis" class="plw-sysnote">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto; margin-top: 1px"><path d="M5 3h14l-6 8v7l-2 1v-8L5 3z" /></svg>
                            <div>
                                <b>Tahap ini disetel OTOMATIS di Master Alur.</b>
                                Begitu seluruh aktivitas penentu selesai, sistem yang memutuskan: gagal → langsung Tidak Lolos, lulus → langsung maju ke tahap berikutnya. Admin tidak mengetuk palu di sini.
                                <template v-if="detailKandidat.aktivitasBelumTercatat">
                                    Masih menunggu {{ detailKandidat.aktivitasBelumTercatat }} hasil aktivitas.
                                </template>
                            </div>
                        </div>
                        <div v-else-if="detailKandidat.nungguSistem" class="plw-sysnote">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto; margin-top: 1px"><path d="M5 3h14l-6 8v7l-2 1v-8L5 3z" /></svg>
                            <div>
                                <b>Menunggu hasil aktivitas.</b>
                                Catat hasil {{ detailKandidat.aktivitasBelumTercatat || 'tes' }} aktivitas penentu di rapor bawah — keputusan baru bisa diambil setelah itu.
                            </div>
                        </div>
                        <!-- SIAP DIPUTUS — sebutkan apa KATA DATANYA. Admin tetap yang
                             mengetuk palu, tapi ia tak boleh disuruh menebak hasil tes
                             yang sudah dihitung sistem. -->
                        <div v-else-if="detailKandidat.siapDiputus" class="plw-sysnote" :class="detailKandidat.hasilData === 'GAGAL' ? 'is-fail' : 'is-ready'">
                            <svg v-if="detailKandidat.hasilData === 'GAGAL'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto; margin-top: 1px"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto; margin-top: 1px"><path d="M20 6L9 17l-5-5" /></svg>
                            <div>
                                <b v-if="detailKandidat.hasilData === 'LULUS'">Hasil tes: LULUS.</b>
                                <b v-else-if="detailKandidat.hasilData === 'GAGAL'">Hasil tes: TIDAK LULUS.</b>
                                <b v-else>Semua hasil sudah masuk.</b>
                                {{ detailKandidat.ringkasHasil || 'Tinjau rapor tes di bawah, lalu putuskan.' }}
                                Keputusan akhir tetap di tangan Anda — tekan Loloskan / Tidak Lolos.
                            </div>
                        </div>
                        <div v-else-if="detailKandidat.alasan" class="plw-sysnote">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" style="flex: 0 0 auto; margin-top: 1px"><circle cx="12" cy="12" r="9" /><path d="M12 8v5" /><path d="M12 16h.01" /></svg>
                            <div><b>Catatan sistem:</b> {{ detailKandidat.alasan }}</div>
                        </div>
                    </div>

                    <!-- RAPOR TES — sub-tes tahap ini (baterai multi-tes). Skor informatif
                         ikut tampil sebagai bahan pertimbangan; ia tak menentukan lulus. -->
                    <div v-if="(detailKandidat.tests || []).length">
                        <div class="plw-secrow">
                            <div class="plw-sectitle">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" /></svg>
                                Rapor Tes Tahap Ini
                            </div>
                            <span class="plw-seccount">{{ detailKandidat.tests.length }} tes</span>
                        </div>
                        <div class="plw-tests">
                            <div v-for="t in detailKandidat.tests" :key="t.id" class="plw-test" :class="{ 'is-terkunci': t.terkunci }">
                                <span class="plw-test__ico" :class="t.provider === 'THIRD_PARTY' ? 'is-sys' : 'is-man'">
                                    <i class="bi" :class="t.provider === 'THIRD_PARTY' ? 'bi-robot' : 'bi-person-workspace'"></i>
                                </span>
                                <div class="plw-test__main">
                                    <div class="plw-test__name">
                                        {{ t.label }}
                                        <!-- Aktivitas internal: dicatat tim, tak pernah
                                             terlihat kandidat. Ditandai supaya admin tahu
                                             ia tidak sedang menunggu kandidat berbuat apa pun. -->
                                        <span v-if="t.internal" class="plw-test__tag is-internal" title="Tidak ditampilkan di portal kandidat — hanya dicatat tim">
                                            <i class="bi bi-eye-slash-fill"></i> internal
                                        </span>
                                        <span v-if="t.peran === 'INFORMATIF'" class="plw-test__tag is-info" title="Skor hanya bahan pertimbangan — tidak menentukan lulus">informatif</span>
                                        <span v-if="!t.wajib" class="plw-test__tag">opsional</span>
                                        <!-- Aktivitas inilah yang membuka pilihan jawaban
                                             kandidat begitu hasilnya dicatat. -->
                                        <span v-if="t.penawaran" class="plw-test__tag is-offer" title="Mencatat hasil aktivitas ini menandai penawaran sudah diajukan ke kandidat">penawaran</span>
                                    </div>
                                    <!-- Tipe aktivitas, bukan tipe tahap: satu tahap bisa
                                         berisi ujian online + tes manual + wawancara. -->
                                    <div class="plw-test__sub">
                                        {{ t.tipeNama || '—' }} ·
                                        <template v-if="t.infoSaja">suratnya diunggah di jendela keputusan</template>
                                        <template v-else-if="t.provider === 'THIRD_PARTY'">dijadwalkan di Penjadwalan</template>
                                        <template v-else>dilaksanakan tim</template>
                                    </div>
                                    <!-- Jadwal yang sudah ditetapkan ikut terbaca di
                                         barisnya. Tanpa ini admin harus membuka modal
                                         "Ubah Jadwal" hanya untuk mengingat kapan dan
                                         di mana kandidat diminta datang — padahal itu
                                         justru yang ia perlukan saat menandai hadir. -->
                                    <!-- PEMERIKSAAN: KESIMPULAN & HITUNGANNYA DI BARISNYA.

                                         "5 dari 6 bersih, 1 temuan" adalah hal pertama yang
                                         ingin diketahui orang yang membuka papan seleksi —
                                         dan tanpa baris ini ia harus membuka jendela Catat
                                         Hasil satu per satu hanya untuk tahu mana yang perlu
                                         disentuh. Angkanya sudah tersimpan (Adjudikasi_Ringkas),
                                         jadi menampilkannya tidak menambah satu query pun. -->
                                    <div v-if="t.pemeriksaan" class="plw-test__periksa">
                                        <span v-if="t.pemeriksaan.persetujuan?.ditolak" class="plw-test__adj is-no" title="Kandidat menolak diperiksa">
                                            <i class="bi bi-shield-slash"></i> menolak diperiksa
                                        </span>
                                        <span v-else-if="t.pemeriksaan.persetujuan?.ada" class="plw-test__adj is-ok" title="Persetujuan kandidat tercatat">
                                            <i class="bi bi-shield-check"></i> setuju diperiksa
                                        </span>
                                        <span v-if="t.pemeriksaan.adjudikasi" class="plw-test__adj" :class="kelasAdjudikasi(t.pemeriksaan.adjudikasi)">
                                            <i class="bi bi-flag-fill"></i> {{ labelAdjudikasi(t.pemeriksaan.adjudikasi) }}
                                        </span>
                                        <span v-if="t.pemeriksaan.ringkas">{{ t.pemeriksaan.ringkas }}</span>
                                        <span v-else class="is-samar">belum ada temuan dicatat</span>
                                        <span v-if="t.butuhPersetujuan" class="plw-test__adj is-warn" title="Tanyakan kesediaan kandidat lebih dulu — biasanya lewat telepon">
                                            <i class="bi bi-shield-exclamation"></i> menunggu jawaban kandidat
                                        </span>
                                    </div>
                                    <!-- SKRINING — keadaan sesi, skor, dan penanda gugur.
                                         Angkanya sudah tersimpan di kepala sesi, jadi
                                         menampilkannya di sini tidak menambah satu kueri pun. -->
                                    <div v-if="t.skrining" class="plw-test__periksa">
                                        <template v-if="t.skrining.sesi">
                                            <span
                                                class="plw-test__adj"
                                                :class="t.skrining.sesi.status === 'SELESAI' ? 'is-ok' : 'is-warn'"
                                            >
                                                <i class="bi" :class="t.skrining.sesi.status === 'SELESAI' ? 'bi-lock-fill' : 'bi-pencil-fill'"></i>
                                                {{ t.skrining.sesi.status === 'SELESAI' ? 'skrining selesai' : 'skrining berjalan' }}
                                            </span>
                                            <span v-if="t.skrining.sesi.skorPersen !== null" class="plw-test__adj">
                                                <i class="bi bi-speedometer2"></i> {{ t.skrining.sesi.skorPersen }}%
                                            </span>
                                            <span v-if="t.skrining.sesi.rekomendasi" class="plw-test__adj" :class="kelasRekomSkr(t.skrining.sesi.rekomendasi)">
                                                <i class="bi bi-flag-fill"></i> {{ labelRekomSkr(t.skrining.sesi.rekomendasi) }}
                                            </span>
                                            <span v-if="t.skrining.sesi.knockout" class="plw-test__adj is-no" :title="t.skrining.sesi.knockoutPesan">
                                                <i class="bi bi-exclamation-octagon-fill"></i> ditandai gugur
                                            </span>
                                        </template>
                                        <span v-else-if="t.skrining.terikat" class="is-samar">
                                            kuesioner {{ t.skrining.nama || t.skrining.kode }} siap — belum dimulai
                                        </span>
                                        <span v-else class="plw-test__adj is-warn" title="Pasang templatenya di Program Kegiatan">
                                            <i class="bi bi-plug"></i> belum terikat kuesioner
                                        </span>
                                    </div>
                                    <div v-if="t.jadwal" class="plw-test__jadwalinfo">
                                        <i class="bi" :class="t.jadwal.batasWaktu ? 'bi-hospital' : (t.jadwal.daring ? 'bi-camera-video-fill' : 'bi-geo-alt-fill')"></i>
                                        <span>
                                            {{ jadwalRingkas(t.jadwal) }}
                                            <template v-if="!t.jadwal.daring && !t.jadwal.batasWaktu && t.jadwal.lokasi"> · {{ t.jadwal.lokasi }}</template>
                                            <template v-if="t.jadwal.vendor && t.jadwal.tempat?.nama"> · {{ t.jadwal.tempat.nama }}</template>
                                        </span>
                                        <!-- Surat pengantar yang diterima kandidat — dibuka apa adanya,
                                             satu lencana per surat. -->
                                        <a
                                            v-for="(s, i) in (t.jadwal.surat || [])" :key="'srt' + i"
                                            :href="s.url" target="_blank" rel="noopener"
                                            class="plw-test__adj is-surat" :title="s.nama" @click.stop
                                        >
                                            <i class="bi bi-file-earmark-pdf"></i> surat{{ t.jadwal.surat.length > 1 ? ' ' + (i + 1) : '' }}
                                        </a>
                                        <!-- Batasnya lewat, hasil belum masuk: tindak lanjut ada di
                                             tangan tim (perpanjang atau tandai tidak melaksanakan). -->
                                        <span v-if="t.jadwal.lewat" class="plw-test__adj is-no" title="Batas waktu sudah lewat dan hasilnya belum dicatat">
                                            <i class="bi bi-alarm-fill"></i> lewat batas
                                        </span>
                                        <!-- Batas UNGGAH dimundurkan lewat "Perpanjang" — rentang
                                             pemeriksaan di baris jadwal tetap yang lama. -->
                                        <span
                                            v-else-if="t.jadwal.batasDiperpanjang" class="plw-test__adj is-warn"
                                            :title="`Batas unggah hasil diperpanjang sampai ${t.jadwal.batasUnggahTeks} — tanggal pemeriksaan tetap`"
                                        >
                                            <i class="bi bi-hourglass-split"></i> unggah diperpanjang
                                        </span>
                                    </div>
                                    <!-- KONFIRMASI KEHADIRAN — jawaban kandidat atas versi jadwal
                                         ini (akan hadir / minta jadwal lain / mundur / belum).
                                         Diklik: panel rincian, pengingat manual, proses
                                         permintaan, catat jawaban, tunda, riwayat. -->
                                    <div v-if="t.konfirmasi" class="plw-test__periksa">
                                        <button
                                            type="button"
                                            class="plw-test__adj plw-konf"
                                            :style="{ '--nada': t.konfirmasi.warna || '#94a3b8' }"
                                            :aria-expanded="konfBuka === t.id"
                                            :title="t.konfirmasi.batasTeks ? `Batas konfirmasi ${t.konfirmasi.batasTeks}` : ''"
                                            @click.stop="konfBuka = konfBuka === t.id ? null : t.id"
                                        >
                                            <i class="bi" :class="t.konfirmasi.ikon || 'bi-hourglass-split'"></i>
                                            {{ t.konfirmasi.label }}
                                            <template v-if="t.konfirmasi.permintaan"> · tenggat {{ t.konfirmasi.permintaan.slaTeks }}</template>
                                            <template v-else-if="t.konfirmasi.status === 'MENUNGGU' && t.konfirmasi.batasTeks"> · batas {{ t.konfirmasi.batasTeks }}</template>
                                            <template v-else-if="t.konfirmasi.status === 'DITUNDA'"> · {{ t.konfirmasi.tunda?.perkiraanTeks ? `perkiraan ${t.konfirmasi.tunda.perkiraanTeks}` : 'pengganti belum ditetapkan' }}</template>
                                            <i class="bi" :class="konfBuka === t.id ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                        </button>
                                    </div>
                                    <KonfirmasiPanel
                                        v-if="t.konfirmasi && konfBuka === t.id"
                                        class="plw-konf-panel"
                                        :id-aktivitas="t.id"
                                        :ringkas="t.konfirmasi"
                                        :boleh-ubah="aksiSaya.includes('EDIT')"
                                        :aksi-jadwal="false"
                                        @berubah="segarkanKartu({ tes: t.id })"
                                        @pesan="notice"
                                    />
                                    <!-- PENGGANTIAN BIAYA — ditentukan sistem dari hasil MCU
                                         (lolos = diganti). Tampil begitu hasilnya dicatat. -->
                                    <div v-if="t.biaya && t.biaya.kode !== 'MENUNGGU'" class="plw-test__periksa">
                                        <span
                                            class="plw-test__adj"
                                            :class="{ 'is-ok': t.biaya.kode === 'DIGANTI', 'is-warn': t.biaya.kode === 'TIDAK_DIGANTI' }"
                                            :title="t.biaya.kalimat"
                                        >
                                            <i class="bi bi-cash-coin"></i> {{ t.biaya.label }}
                                        </span>
                                    </div>
                                    <!-- CATATAN PENILAIAN.
                                         Yang berformat dirender apa adanya lewat
                                         KontenAman (penyaring daftar-izin) —
                                         menampilkannya sebagai teks biasa akan
                                         memuntahkan tag mentah ke layar, dan
                                         gambar lembar penilaiannya hilang.
                                         Ringkasan polos tetap dipakai untuk
                                         catatan lama yang belum berformat. -->
                                    <!-- APA YANG MASIH DITUNGGU dari aktivitas ini.
                                         Alasan yang sama persis dipakai server untuk
                                         menolak keputusan — jadi yang terbaca di sini
                                         tidak akan pernah berselisih dengan yang
                                         ditegakkan di balik layar. -->
                                    <div v-if="!t.tuntas && !t.terkunci" class="plw-test__nunggu">
                                        <i class="bi bi-hourglass-split"></i> {{ t.alasanBelumTuntas }}
                                    </div>

                                    <!-- CATATAN & BERKAS DIJADIKAN SATU PANEL.
                                         Dulu ketiganya (catatan berformat, berkas
                                         kandidat, berkas tim) berdiri sendiri-sendiri
                                         dan selalu terbuka. Pada tahap berisi 4-6 tes
                                         offline, satu layar jadi dinding teks setinggi
                                         beberapa gulungan — dan tombol yang benar-benar
                                         perlu ditekan tenggelam di dalamnya.

                                         Sekarang: satu tombol dengan jumlahnya, dibuka
                                         hanya untuk aktivitas yang sedang ditinjau. -->
                                    <button
                                        v-if="jumlahDetail(t)"
                                        type="button" class="plw-test__more"
                                        :class="{ 'is-on': detailTes === t.id }"
                                        :aria-expanded="detailTes === t.id"
                                        @click.stop="toggleDetail(t)"
                                    >
                                        <i class="bi" :class="detailTes === t.id ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                        Catatan &amp; berkas
                                        <span class="plw-test__morecount">{{ jumlahDetail(t) }}</span>
                                    </button>

                                    <!-- PERINGATAN TETAP DI LUAR PANEL.
                                         "Kandidat belum mengunggah padahal wajib" adalah hal
                                         yang harus terlihat SEKILAS, tanpa membuka apa pun.
                                         Menyembunyikannya di balik tombol membuat yang kurang
                                         hanya ketahuan oleh orang yang kebetulan mengklik. -->
                                    <div v-if="t.unggahKandidat && !(t.berkasKandidat || []).length" class="plw-test__kirimkosong" :class="{ 'is-wajib': t.unggahKandidat.wajib }">
                                        <i class="bi" :class="t.unggahKandidat.wajib ? 'bi-exclamation-triangle-fill' : 'bi-hourglass'"></i>
                                        Kandidat belum mengunggah berkas{{ t.unggahKandidat.wajib ? ' (wajib)' : '' }}.
                                        <template v-if="t.unggahKandidat.tertutup">Batas unggahnya sudah lewat — perpanjang bila perlu.</template>
                                        <template v-else-if="t.unggahKandidat.batasTeks">Batas unggah: {{ t.unggahKandidat.batasTeks }}{{ t.unggahKandidat.diperpanjang ? ' (diperpanjang)' : '' }}.</template>
                                    </div>

                                    <!-- PANEL DETAIL — catatan penilaian, berkas kandidat, dan
                                         berkas tim. Ketiganya hanya dirender saat dibuka: pada
                                         tahap berisi enam tes offline, merender semuanya sekaligus
                                         berarti puluhan blok teks berformat yang tak seorang pun
                                         baca serentak. -->
                                    <div v-if="detailTes === t.id" class="plw-test__detail">
                                        <!-- INSTRUKSI yang benar-benar diterima kandidat (salinan
                                             saat dijadwalkan) — tim bisa memastikan apa yang
                                             dibaca kandidat tanpa membuka surelnya. -->
                                        <div v-if="t.jadwal?.catatanHtml" class="plw-test__cat is-kaya">
                                            <i class="bi bi-send"></i>
                                            <div style="min-width: 0; flex: 1">
                                                <small class="plw-test__catlbl">Instruksi terkirim ke kandidat</small>
                                                <KontenAman :html="t.jadwal.catatanHtml" ringkas />
                                            </div>
                                        </div>
                                        <div v-if="t.catatanHtml" class="plw-test__cat is-kaya">
                                            <i class="bi bi-chat-left-text"></i>
                                            <KontenAman :html="t.catatanHtml" />
                                        </div>
                                        <div v-else-if="t.catatan" class="plw-test__cat"><i class="bi bi-chat-left-text"></i> {{ t.catatan }}</div>

                                        <!-- BERKAS DARI KANDIDAT — hasil dari setelan "kandidat
                                             harus mengunggah" di Master Alur. Inilah satu-satunya
                                             layar tempat tim menilainya; berkas yang cuma bisa
                                             dibuka di portal kandidat sama saja tak pernah
                                             diserahkan. -->
                                        <div v-if="(t.berkasKandidat || []).length" class="plw-test__kirim">
                                            <div class="plw-test__kirimhead">
                                                <span class="plw-test__kirimlbl"><i class="bi bi-inbox-fill"></i> Dari kandidat</span>
                                                <!-- SUDAH DINYATAKAN LENGKAP ATAU BELUM. Berkas yang
                                                     masuk belum tentu berkas yang utuh; menilai sebelum
                                                     kandidat menyatakan selesai berarti menilai
                                                     pekerjaan setengah jadi — dan itu tak bisa ditarik.
                                                     Ditaruh di baris kepalanya sendiri supaya tidak lagi
                                                     berdesakan dengan nama-nama berkas. -->
                                                <span
                                                    v-if="t.unggahKandidat"
                                                    class="plw-test__kirimstat"
                                                    :class="t.unggahKandidat.terkirim ? 'is-ok' : 'is-nunggu'"
                                                    :title="t.unggahKandidat.terkirim
                                                        ? `Dinyatakan lengkap oleh kandidat pada ${fmtWaktu(t.unggahKandidat.terkirim)}`
                                                        : 'Kandidat belum menekan Kirim — mungkin masih ada berkas susulan.'"
                                                >
                                                    <i class="bi" :class="t.unggahKandidat.terkirim ? 'bi-patch-check-fill' : 'bi-hourglass-split'"></i>
                                                    {{ t.unggahKandidat.terkirim ? 'dinyatakan lengkap' : 'belum dikirim' }}
                                                </span>
                                            </div>
                                            <div class="plw-test__files">
                                                <button
                                                    v-for="b in t.berkasKandidat" :key="b.id"
                                                    type="button" class="plw-test__kirimfile"
                                                    :title="b.terkirim
                                                        ? `${b.nama} — diserahkan ${fmtWaktu(b.terkirim)}`
                                                        : `${b.nama} — kandidat belum menekan Kirim`"
                                                    @click.stop="bukaDok(b)"
                                                >
                                                    <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                                                    <span class="plw-test__filenama">{{ b.nama }}</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Lampiran yang diunggah TIM. Dipisah dari yang di atas:
                                             siapa yang menyerahkan menentukan cara membacanya —
                                             bukti dari kandidat diverifikasi, berkas penilai adalah
                                             kesimpulan. -->
                                        <div v-if="(t.berkas || []).length" class="plw-test__kirim is-tim">
                                            <div class="plw-test__kirimhead">
                                                <span class="plw-test__kirimlbl"><i class="bi bi-paperclip"></i> Berkas tim</span>
                                            </div>
                                            <div class="plw-test__files">
                                                <button
                                                    v-for="b in t.berkas" :key="b.id"
                                                    type="button" class="plw-test__kirimfile" :title="b.nama"
                                                    @click.stop="bukaDok(b)"
                                                >
                                                    <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                                                    <span class="plw-test__filenama">{{ b.nama }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- ANGKANYA DISEMBUNYIKAN pada alat tes yang keputusannya milik
                                     penilai (PAPI Kostick, DISC, Kraeplin) — sebelum maupun
                                     sesudah diputuskan. Keluarannya profil, bukan nilai
                                     kelulusan; yang tersisa cukup lencana statusnya.
                                     Server yang menentukan lewat `skorBermakna`. -->
                                <span v-if="t.nilai != null && t.skorBermakna" class="plw-test__score">{{ t.nilai }}</span>
                                <span class="plw-test__pill" :class="pillTes(t)">{{ labelTes(t) }}</span>
                                <!-- Tombol aksi turun ke barisnya sendiri: drawer hanya
                                     560px dan bisa memuat tiga tombol sekaligus, sehingga
                                     memaksanya sebaris dengan nama aktivitas membuat
                                     labelnya terpotong per kata. -->
                                <div class="plw-test__aksi">
                                <!-- TERKUNCI URUTAN — tahapnya dikerjakan per langkah dan
                                     giliran aktivitas ini belum tiba. Tombolnya tidak
                                     ditampilkan sama sekali (server pun menolaknya), dan
                                     alasannya disebut supaya tidak terbaca sebagai layar
                                     yang rusak. -->
                                <span v-if="t.terkunci" class="plw-test__gembok">
                                    <i class="bi bi-lock-fill"></i>
                                    Menunggu <b>{{ t.menunggu }}</b> selesai
                                </span>
                                <!-- "Catat Hasil" HANYA untuk aktivitas yang dikerjakan tim
                                     (wawancara, tes offline, FGD) pada tahap multi-aktivitas.
                                     Ujian online tidak punya tombol ini: nilainya datang
                                     sendiri dari HCLearn, dan mengisinya manual justru
                                     menimpa angka resmi. Server yang menentukan lewat
                                     dapatDicatat — lihat LamaranController::rapotTes. -->
                                <!-- ATUR JADWAL — muncul untuk tipe yang menuntut waktu &
                                     tempat (wawancara, MCU, tes offline). Penandanya dari
                                     Master Tipe Tahap (Flag_Jadwal), bukan daftar kode di
                                     dalam kode program, jadi tipe baru cukup ditambahkan
                                     lewat master tanpa perlu deploy. -->
                                <!-- KEHADIRAN: gerbang sebelum hasil boleh dicatat.
                                     Tim tidak bisa melampirkan hasil MCU untuk orang
                                     yang tidak datang, jadi ini ditetapkan lebih dulu.
                                     Keduanya membuka modal konfirmasi dengan catatan
                                     OPSIONAL — lihat askHadir(). -->
                                <!-- MENAHAN AKTIVITAS BERIKUTNYA.
                                     Tombolnya ada di aktivitas yang MENAHAN,
                                     bukan di yang tertahan — di sana admin tak
                                     bisa berbuat apa-apa, dan menaruhnya di situ
                                     membuat ia mengklik hal yang salah. -->
                                <button
                                    v-if="t.perluLanjut"
                                    type="button" class="plw-test__lanjut" :disabled="lanjutId === t.id"
                                    title="Buka aktivitas berikutnya untuk kandidat ini"
                                    :onClick="lanjutId === t.id ? null : () => lanjutkanAktivitas(t)"
                                >
                                    <i class="bi" :class="lanjutId === t.id ? 'bi-arrow-repeat plw-putar' : 'bi-arrow-right-circle-fill'"></i>
                                    {{ lanjutId === t.id ? 'Membuka…' : 'Lanjutkan' }}
                                </button>

                                <!-- BERBATAS WAKTU (MCU mandiri): tidak ada "kehadiran" —
                                     kandidat mengerjakannya sendiri, jadi tombolnya
                                     berbunyi apa yang sebenarnya dicatat: hasilnya,
                                     atau bahwa ia tidak melaksanakannya. Server-nya sama. -->
                                <template v-if="t.butuhKehadiran && !t.terkunci">
                                    <button
                                        type="button" class="plw-test__hdr is-ya"
                                        :title="t.jadwal?.batasWaktu ? 'Hasilnya sudah masuk — catat statusnya (dari berkas kandidat)' : 'Kandidat datang — hasil aktivitas ini lalu bisa dicatat & berkasnya diunggah'"
                                        @click="askHadir(t, 'Y')"
                                    >
                                        <i class="bi" :class="t.jadwal?.batasWaktu ? 'bi-clipboard2-check-fill' : 'bi-person-check-fill'"></i>
                                        {{ t.jadwal?.batasWaktu ? 'Catat Hasil' : 'Hadir' }}
                                    </button>
                                    <button
                                        type="button" class="plw-test__hdr is-tidak"
                                        :title="t.jadwal?.batasWaktu ? 'Kandidat tidak melaksanakannya — aktivitas ditutup dan tahapnya dievaluasi ulang' : 'Kandidat tidak datang — aktivitas ditutup dan tahapnya dievaluasi ulang'"
                                        @click="askHadir(t, 'T')"
                                    >
                                        <i class="bi bi-person-dash-fill"></i>
                                        {{ t.jadwal?.batasWaktu ? 'Tidak Melaksanakan' : 'Tidak Hadir' }}
                                    </button>
                                </template>
                                <!-- Kehadiran yang SUDAH ditetapkan tetap terbaca —
                                     itulah dasar tombol keputusan boleh ditekan. -->
                                <span v-else-if="t.hadir === 'Y'" class="plw-test__hdrtag">
                                    <i class="bi bi-person-check-fill"></i> {{ t.jadwal?.batasWaktu ? 'Dilaksanakan' : 'Hadir' }}
                                </span>
                                <span v-else-if="t.hadir === 'T'" class="plw-test__hdrtag is-no">
                                    <i class="bi bi-person-dash-fill"></i> {{ t.jadwal?.batasWaktu ? 'Tidak dilaksanakan' : 'Tidak hadir' }}
                                </span>

                                <!-- PERMINTAAN JADWAL LAIN TERBUKA — kandidat sudah mengajukan
                                     waktu lain, jadi pertanyaannya sekarang hanya satu: diapakan
                                     usulannya. Tiga tombolnya MENGGANTIKAN Ubah Jadwal, Tunda,
                                     dan Kirim ulang email (yang justru menimpa usulan diam-diam),
                                     dan berdiri di baris ini — bukan tersembunyi di panel
                                     konfirmasi (masukan user 2 Okt 2026). -->
                                <template v-if="bisaProsesMinta(t)">
                                    <button
                                        type="button" class="plw-test__jdw is-setuju"
                                        title="Pilih salah satu usulan kandidat — jadwal baru terkirim dan kandidat langsung tercatat akan hadir"
                                        @click="bukaMintaBaris(t, 'setujui')"
                                    >
                                        <i class="bi bi-check2-circle"></i> Setujui Usulan
                                    </button>
                                    <button
                                        type="button" class="plw-test__jdw"
                                        title="Usulan kandidat tidak bisa — tawarkan waktu lain; kandidat mengonfirmasi ulang"
                                        @click="bukaMintaBaris(t, 'tawarkan')"
                                    >
                                        <i class="bi bi-calendar2-plus"></i> Tawarkan Waktu Lain
                                    </button>
                                    <button
                                        type="button" class="plw-test__jdw is-tolak"
                                        title="Jadwal semula tetap — kandidat diminta menjawab lagi"
                                        @click="bukaMintaBaris(t, 'tolak')"
                                    >
                                        <i class="bi bi-x-circle"></i> Tolak
                                    </button>
                                </template>
                                <button
                                    v-if="t.butuhJadwal && !mintaTerbuka(t)"
                                    type="button" class="plw-test__jdw"
                                    :class="{ 'is-set': t.jadwal }"
                                    :title="judulTombolJadwal(t)"
                                    @click="askJadwal(t)"
                                >
                                    <i class="bi" :class="t.jadwal ? 'bi-calendar-check-fill' : 'bi-calendar-plus'"></i>
                                    {{ t.jadwal ? 'Ubah Jadwal' : 'Atur Jadwal' }}
                                </button>
                                <!-- TUNDA JADWAL — aksi atas JADWALNYA, sejajar Ubah Jadwal (bukan
                                     di panel konfirmasi): jadwal dikosongkan, kandidat dikabari
                                     alasan & perkiraan penggantinya. Yang sudah ditunda: Perbarui
                                     info (mis. dari "belum diketahui" menjadi perkiraan tanggal). -->
                                <button
                                    v-if="bisaTunda(t)"
                                    type="button" class="plw-test__jdw is-tunda"
                                    title="Kosongkan jadwal ini & kabari kandidat — alasan, perkiraan jadwal pengganti, pesan"
                                    @click="bukaTundaBaris(t, 'tunda')"
                                >
                                    <i class="bi bi-pause-circle"></i> Tunda Jadwal
                                </button>
                                <button
                                    v-else-if="bisaPerbaruiTunda(t)"
                                    type="button" class="plw-test__jdw is-tunda"
                                    title="Perbarui alasan / perkiraan jadwal pengganti — kandidat bisa dikabari lagi lewat email"
                                    @click="bukaTundaBaris(t, 'perbarui')"
                                >
                                    <i class="bi bi-pencil-square"></i> Perbarui Info Tunda
                                </button>
                                <!-- PERPANJANG — hanya BATAS UNGGAH hasil (MCU mandiri); tanggal
                                     pemeriksaan tetap. Kotak unggah yang sudah tertutup ikut
                                     terbuka. Vendor tidak punya batas unggah: rentangnya diubah
                                     lewat "Ubah Jadwal". -->
                                <button
                                    v-if="bisaPerpanjang(t)"
                                    type="button" class="plw-test__jdw is-panjang"
                                    title="Mundurkan batas unggah hasil — tanggal pemeriksaan, tempat, surat & instruksi tetap; kandidat dikabari lewat email"
                                    @click="askPerpanjang(t)"
                                >
                                    <i class="bi bi-hourglass-split"></i> Perpanjang
                                </button>
                                <!-- KIRIM ULANG EMAIL — kandidat melapor tidak menerima, atau
                                     belum juga mengunggah. Isinya dirakit dari jadwal yang
                                     berlaku sekarang, bukan salinan email lama. -->
                                <button
                                    v-if="bisaKirimUlang(t)"
                                    type="button" class="plw-test__jdw is-ulang"
                                    :title="judulKirimUlang(t)"
                                    @click="askKirimUlang(t)"
                                >
                                    <i class="bi bi-envelope-arrow-up"></i> Kirim ulang email
                                </button>
                                <!-- KEPUTUSAN LANGSUNG — ujian online ber-peran INFORMATIF.
                                     Kandidat sudah mengerjakan, nilainya sudah masuk dari
                                     HCLearn; yang tersisa satu hal saja: layak atau tidak.
                                     Karena itu dua tombol, bukan jendela berisi bidang nilai
                                     dan catatan yang tak satu pun perlu diisi. Sekali klik
                                     tanpa konfirmasi — ini bukan aksi final yang menutup
                                     lamaran, dan tahapnya masih bisa dinilai ulang lewat
                                     keputusan tahap. -->
                                <template v-if="bisaPutusTes(t)">
                                    <button
                                        type="button" class="plw-test__ver is-lulus" :disabled="keputusanId === t.id"
                                        title="Kandidat dinyatakan LULUS pada aktivitas ini"
                                        :onClick="keputusanId === t.id ? null : () => putusTes(t, 'LULUS')"
                                    >
                                        <i class="bi" :class="keputusanId === t.id && keputusanHasil === 'LULUS' ? 'bi-arrow-repeat plw-putar' : 'bi-check-circle-fill'"></i>
                                        Lulus
                                    </button>
                                    <button
                                        type="button" class="plw-test__ver is-gagal" :disabled="keputusanId === t.id"
                                        title="Kandidat dinyatakan TIDAK LULUS pada aktivitas ini"
                                        :onClick="keputusanId === t.id ? null : () => putusTes(t, 'GAGAL')"
                                    >
                                        <i class="bi" :class="keputusanId === t.id && keputusanHasil === 'GAGAL' ? 'bi-arrow-repeat plw-putar' : 'bi-x-circle-fill'"></i>
                                        Tidak Lulus
                                    </button>
                                </template>
                                <!-- ══ PEMERIKSAAN: PERSETUJUAN DULU, BARU APA PUN ══

                                     Sebelum kandidat ditanya bersedia atau tidak, barisnya hanya
                                     menawarkan dua hal. "Catat Hasil" tidak muncul — mengisinya berarti
                                     mencatat temuan atas pemeriksaan yang belum boleh dijalankan —
                                     dan "Tidak hadir" tidak pernah muncul sama sekali: kandidat tidak
                                     datang ke mana pun, tim yang menelepon kampus & mantan atasannya. -->
                                <template v-if="t.butuhPersetujuan">
                                    <button
                                        type="button" class="plw-test__setuju is-ya" :disabled="setujuId === t.id"
                                        title="Kandidat bersedia diperiksa — dicatat berikut nama penanya & tanggalnya"
                                        :onClick="setujuId === t.id ? null : () => setujuPeriksa(t)"
                                    >
                                        <i class="bi" :class="setujuId === t.id ? 'bi-arrow-repeat plw-putar' : 'bi-check-lg'"></i>
                                        Setuju
                                    </button>
                                    <button
                                        type="button" class="plw-test__setuju is-tidak" :disabled="setujuId === t.id"
                                        title="Kandidat menolak diperiksa — alasannya wajib dicatat"
                                        :onClick="setujuId === t.id ? null : () => askTolakPeriksa(t)"
                                    >
                                        <i class="bi bi-x-lg"></i> Tidak setuju
                                    </button>
                                </template>

                                <!-- Berdiri SEBELUM "Catat Hasil" karena itulah urutan
                                     kerjanya: skrining dijalankan dulu, hasil tahapnya
                                     dicatat sesudah kesimpulannya ada. -->
                                <button
                                    v-if="t.skrining && t.skrining.terikat"
                                    type="button" class="plw-test__rec plw-test__skr"
                                    title="Buka kuesioner phone screening"
                                    @click="bukaSkrining(t)"
                                >
                                    <i class="bi bi-telephone-inbound"></i>
                                    {{ t.skrining.sesi ? 'Buka Skrining' : 'Mulai Skrining' }}
                                </button>

                                <button
                                    v-if="bisaCatat(t) && bisaCatatKehadiran(t)"
                                    type="button" class="plw-test__rec"
                                    title="Rekam hasil aktivitas ini — mesin langsung mengevaluasi tahap"
                                    @click="askCatat(t)"
                                >
                                    <i class="bi bi-pencil-square"></i> Catat Hasil
                                </button>
                                <!-- SINKRON — jaring pengaman ujian online. Nilainya ditarik
                                     dari HCLearn, bukan diketik admin, jadi yang tersimpan
                                     tetap angka resmi penyedia. -->
                                <button
                                    v-if="t.dapatSinkron"
                                    type="button" class="plw-test__sync" :disabled="sinkronId === t.id"
                                    title="Tarik hasil ujian ini langsung dari HCLearn. Dipakai bila hasilnya belum masuk sendiri."
                                    :onClick="sinkronId === t.id ? null : () => sinkronHasil(t)"
                                >
                                    <i class="bi" :class="sinkronId === t.id ? 'bi-arrow-repeat plw-putar' : 'bi-cloud-download'"></i>
                                    {{ sinkronId === t.id ? 'Menarik…' : 'Sinkronkan' }}
                                </button>
                                <button
                                    v-if="bisaTidakHadir(t)"
                                    type="button" class="plw-test__skip"
                                    :title="t.online
                                        ? 'Kandidat tidak mengerjakan ujian ini. Tahap berhenti menunggu hasilnya dari HCLearn.'
                                        : 'Tandai kandidat tidak hadir pada aktivitas ini (tahap tidak lagi menunggu hasilnya)'"
                                    @click="askTidakHadir(t)"
                                >
                                    <i class="bi bi-person-x"></i> Tidak hadir
                                </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ HASIL TAHAP SEBELUMNYA ═══
                         Di tahap mana pun kandidat berada, hasil & berkas tahap
                         yang sudah dilewati (FGD, wawancara, MCU…) tetap terlihat
                         di sini — bukan hanya berkas formulir. Rapor di atas hanya
                         milik tahap aktif; tanpa ini, hasil FGD di tahap 4 hilang
                         dari pandangan begitu kandidat masuk tahap 5. -->
                    <div v-if="tahapSebelumnya.length" class="plw-hts">
                        <div class="plw-hts__head">
                            <span class="plw-hts__lbl"><i class="bi bi-clock-history"></i> HASIL TAHAP SEBELUMNYA</span>
                            <span class="plw-hts__jml">{{ tahapSebelumnya.length }} tahap</span>
                        </div>
                        <div v-for="t in tahapSebelumnya" :key="t.id" class="plw-hts__item">
                            <div class="plw-hts__top">
                                <span class="plw-hts__no">{{ String(t.urutan).padStart(2, '0') }}</span>
                                <div class="plw-hts__isi">
                                    <b>{{ t.label }}</b>
                                    <small>
                                        <span class="plw-test__pill" :class="kelasHasilTahap(t)">{{ labelHasilTahap(t) }}</span>
                                        <template v-if="t.skor !== null"> · skor {{ t.skor }}</template>
                                        <template v-if="t.diputusAt"> · {{ t.diputusBy || '—' }}, {{ tglId(t.diputusAt) }}</template>
                                    </small>
                                </div>
                                <button type="button" class="plw-hts__detail" @click="bukaTahapRiwayat(t.id)">
                                    Detail <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                            <div v-if="t.berkas.length" class="plw-hts__berkas">
                                <button
                                    v-for="b in t.berkas" :key="b.sumber + b.id" type="button" class="plw-hts__file"
                                    :title="`${b.nama}${b.aktivitas ? ' — ' + b.aktivitas : ''}${b.sumber === 'KANDIDAT' ? ' (dari kandidat)' : ''}`"
                                    @click="bukaDok(b)"
                                >
                                    <i class="bi" :class="b.isImage ? 'bi-image' : 'bi-file-earmark-text'"></i>
                                    <span>{{ b.aktivitas || b.nama }}</span>
                                    <em v-if="b.sumber === 'KANDIDAT'">kandidat</em>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ PROGRES SELEKSI ═══
                         Seluruh tahap program dalam satu linimasa, dengan posisi
                         kandidat ini di dalamnya. Tahap yang sudah dimulai BISA
                         DIKLIK: membuka isi tahap itu (hasil, catatan, berkas,
                         formulir) — baca-saja, dan berkasnya bisa dibuka lagi.

                         Ada DI KAKI RAPOR, bukan sebagai tab tersendiri. Pertanyaan
                         yang dijawabnya — "tes ini tahap keberapa, sesudah ini apa
                         lagi?" — muncul justru sambil membaca rapor, dan tab
                         terpisah membuat jawabannya berada satu klik lebih jauh
                         dari pertanyaannya. -->
                    <div class="plw-alur">
                        <div class="plw-alur__head">
                            <span class="plw-alur__lbl">PROGRES SELEKSI</span>
                            <span class="plw-alur__pos">Tahap {{ detailKandidat.urutan }} dari {{ detailKandidat.totalTahap }}</span>
                        </div>
                        <div class="plw-alur__bar"><div :style="{ width: persenAlur + '%' }"></div></div>

                        <ol class="plw-alur__line">
                            <li
                                v-for="s in alurKandidat" :key="s.kunci"
                                class="plw-alur__item" :class="['is-' + s.keadaan, { 'is-klik': !!s.id }]"
                                :role="s.id ? 'button' : null" :tabindex="s.id ? 0 : null"
                                :title="s.id ? 'Lihat hasil, catatan & berkas tahap ini' : null"
                                @click="s.id && bukaTahapRiwayat(s.id)"
                                @keydown.enter="s.id && bukaTahapRiwayat(s.id)"
                            >
                                <span class="plw-alur__node"><span></span></span>
                                <div class="plw-alur__isi">
                                    <div style="min-width: 0">
                                        <div class="plw-alur__nama">{{ s.nomor }}. {{ s.label }}</div>
                                        <div class="plw-alur__ket">{{ s.catatan }}</div>
                                    </div>
                                    <span class="plw-alur__kanan">
                                        <span class="plw-alur__tag">{{ s.tag }}</span>
                                        <i v-if="s.id" class="bi bi-chevron-right plw-alur__buka" aria-hidden="true"></i>
                                    </span>
                                </div>
                                <!-- Catatan UNTUK KANDIDAT yang ditulis saat tahap ini
                                     diputus — yang dibaca kandidat di portalnya, jadi
                                     tim perlu melihat persis kalimat yang sama, dan
                                     tahu apakah kalimat itu sudah sampai. -->
                                <div v-if="s.catatanEksternal" class="plw-alur__ce" :class="{ 'is-tunggu': !s.catatanEksternal.terbit }" @click.stop>
                                    <div class="plw-alur__cehead">
                                        <i class="bi bi-megaphone-fill"></i>
                                        <span>Catatan untuk kandidat</span>
                                        <em>{{ s.catatanEksternal.terbit ? 'sudah terlihat kandidat' : 'terlihat setelah hasil diumumkan' }}</em>
                                    </div>
                                    <KontenAman :html="s.catatanEksternal.html" ringkas />
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- TAB: BERKAS & BIODATA -->
                <div v-show="tabAktif === 'berkas'" class="plw-tabpane">
                    <div>
                        <div class="plw-secrow">
                            <div class="plw-sectitle">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z" /></svg>
                                Berkas &amp; Biodata
                            </div>
                            <!-- Hitungan menyebut DUA hal yang berbeda: berapa
                                 formulir, dan berapa dokumen dari yang diminta.
                                 "7/7" itulah yang dicari verifikator lebih dulu —
                                 jumlah formulir saja tidak pernah menjawabnya. -->
                            <span class="plw-seccount">{{ profil.formulir.length }} formulir · {{ dokLengkap }}/{{ dokTotal }} dokumen</span>
                            <!-- DUA CARA MEMBACA ISI YANG SAMA.
                                 Daftar = jawaban beserta pertanyaannya (bawaan);
                                 Berkas = lembarannya saja, berfolder. Keduanya
                                 memakai data yang sama persis — yang berganti
                                 hanya sudut pandangnya. -->
                            <!-- Layar penuh untuk biodata saja.
                                 Jendela detail memuat tujuh tab; biodata yang
                                 isinya lima puluh isian membaca dalam kolom
                                 selebar separuh layar berarti menggulir dua kali
                                 lebih panjang daripada perlunya, sambil melewati
                                 tab lain yang sedang tidak dibaca. -->
                            <button
                                type="button" class="plw-biofull__btn"
                                title="Buka biodata di layar penuh"
                                @click="bioFull = true"
                            >
                                <i class="bi bi-arrows-fullscreen"></i>
                                <span>Layar penuh</span>
                            </button>
                            <div class="plw-fmview" role="group" aria-label="Cara menampilkan berkas">
                                <button
                                    type="button" class="plw-fmview__b" :class="{ 'is-on': berkasMode === 'berkas' }"
                                    title="Tampilan berkas — folder per formulir, petak lembaran"
                                    :aria-pressed="berkasMode === 'berkas'"
                                    @click="setBerkasMode('berkas')"
                                >
                                    <i class="bi bi-grid-1x2-fill"></i>
                                </button>
                                <button
                                    type="button" class="plw-fmview__b" :class="{ 'is-on': berkasMode === 'daftar' }"
                                    title="Tampilan daftar — jawaban beserta pertanyaannya"
                                    :aria-pressed="berkasMode === 'daftar'"
                                    @click="setBerkasMode('daftar')"
                                >
                                    <i class="bi bi-list-ul"></i>
                                </button>
                            </div>
                        </div>

                        <!-- SELURUH isi tab dipindahkan ke hamparan layar penuh,
                             bukan disalin: akordion yang terbuka, folder berkas
                             yang dipilih, dan gulungan riwayat ikut apa adanya —
                             lalu kembali seperti semula begitu ditutup. -->
                        <Teleport to="#plw-biofull-body" :disabled="!bioFull">

                        <div v-if="loadingProfil" class="plw-load" style="padding: 1.5rem 0"><span class="plw-spin"></span> Memuat berkas…</div>
                        <div v-else-if="!profil.formulir.length" class="plw-empty" style="padding: 1.5rem 0">Belum ada formulir terisi.</div>

                        <!-- ═══ FILE MANAGER ═══
                             Folder kiri (satu per formulir) + petak berkas kanan.
                             Menjawab pertanyaan yang di mode daftar menuntut
                             membuka tiap akordion satu per satu: "mana ijazahnya?"
                             Sekali klik menyorot, bilah pratinjau di kaki panel
                             yang membukanya — supaya klik meleset di petak rapat
                             tidak langsung melempar tab baru. -->
                        <div v-else-if="berkasMode === 'berkas'" class="plw-fm">
                            <aside class="plw-fm__side">
                                <div class="plw-fm__sidehead">
                                    <span class="plw-fm__sideico"><i class="bi bi-folder2-open"></i></span>
                                    <span class="plw-fm__sidetxt">
                                        <b>Berkas Kandidat</b>
                                        <em>{{ fmSemua.length }} lembar · {{ profil.formulir.length }} formulir</em>
                                    </span>
                                </div>
                                <div class="plw-fm__folders">
                                    <button
                                        v-for="fd in fmFolderOpsi" :key="fd.key || 'all'"
                                        type="button" class="plw-fm__folder" :class="{ 'is-on': fmFolder === fd.key }"
                                        :title="fd.label"
                                        @click="fmFolder = fd.key; fmSorot = ''"
                                    >
                                        <span class="plw-fm__fico"><i class="bi" :class="fd.ikon"></i></span>
                                        <span class="plw-fm__flabel">{{ fd.label }}</span>
                                        <span class="plw-fm__fn">{{ fd.jumlah }}</span>
                                    </button>
                                </div>
                                <div class="plw-fm__meter">
                                    <div class="plw-fm__meterhead">
                                        <span>KELENGKAPAN</span>
                                        <span>{{ fmLengkap }} / {{ profil.formulir.length }}</span>
                                    </div>
                                    <div class="plw-fm__bar"><div :style="{ width: fmPersen + '%' }"></div></div>
                                </div>
                            </aside>

                            <section class="plw-fm__main">
                                <div class="plw-fm__toolbar">
                                    <div class="plw-fm__crumb">
                                        <span>Berkas</span>
                                        <i class="bi bi-chevron-right"></i>
                                        <b :title="fmJudul">{{ fmJudul }}</b>
                                    </div>
                                    <div class="plw-fm__cari">
                                        <i class="bi bi-search"></i>
                                        <input v-model="fmCari" type="text" placeholder="Cari berkas atau pertanyaannya…">
                                        <button v-if="fmCari" type="button" title="Bersihkan" @click="fmCari = ''"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </div>

                                <div v-if="fmBerkas.length" class="plw-fm__grid">
                                    <button
                                        v-for="b in fmBerkas" :key="b.id"
                                        type="button" class="plw-fm__file" :class="{ 'is-on': fmSorot === b.id }"
                                        :title="b.file"
                                        @click="fmSorot = fmSorot === b.id ? '' : b.id"
                                    >
                                        <span class="plw-fm__fileico" :class="{ 'is-img': b.isImage }">
                                            <i class="bi" :class="ikonBerkas(b)"></i>
                                        </span>
                                        <span class="plw-fm__filenama">{{ b.nama }}</span>
                                        <span class="plw-fm__filefile">{{ b.file }}</span>
                                        <span class="plw-fm__filefoot">
                                            <span class="plw-fm__fileext">{{ b.ext || 'FILE' }}</span>
                                            <span v-if="b.berkas && b.berkas.olehAdmin" class="plw-oleh" :title="judulOleh(b.berkas)"><i class="bi bi-person-badge-fill"></i>Admin</span>
                                            <span class="plw-fm__filedari">{{ b.konteks }}</span>
                                        </span>
                                    </button>
                                </div>
                                <div v-else class="plw-fm__kosong">
                                    <span class="plw-fm__kosongico"><i class="bi bi-folder-x"></i></span>
                                    <b>{{ fmCari ? 'Berkas tidak ditemukan' : 'Folder ini tidak berisi berkas' }}</b>
                                    <span>{{ fmCari ? 'Coba kata kunci lain atau pilih folder lain.' : 'Formulir ini terisi, tapi tidak ada pertanyaan berkasnya.' }}</span>
                                </div>

                                <!-- PRATINJAU — menempel di kaki panel, bukan melayang.
                                     Menyebut ASAL berkasnya sebelum dibuka: satu kandidat
                                     bisa mengunggah tiga "SCAN.pdf" dari tiga formulir
                                     berbeda, dan nama filenya tidak membedakan apa pun. -->
                                <div v-if="fmTerpilih" class="plw-fm__pratinjau">
                                    <span class="plw-fm__pico"><i class="bi" :class="ikonBerkas(fmTerpilih)"></i></span>
                                    <span class="plw-fm__pin">
                                        <b>{{ fmTerpilih.nama }}</b>
                                        <em>{{ fmTerpilih.konteks }} · {{ fmTerpilih.file }}</em>
                                        <span v-if="fmTerpilih.berkas && fmTerpilih.berkas.olehAdmin" class="plw-oleh plw-oleh--blok" :title="judulOleh(fmTerpilih.berkas)"><i class="bi bi-person-badge-fill"></i>Diunggah admin<template v-if="fmTerpilih.berkas.oleh"> · {{ fmTerpilih.berkas.oleh }}</template></span>
                                    </span>
                                    <button type="button" class="plw-fm__pbtn" @click="bukaDok(fmTerpilih.berkas)">
                                        <i class="bi bi-box-arrow-up-right"></i> Buka Berkas
                                    </button>
                                </div>
                            </section>
                        </div>

                        <div v-else class="plw-forms">
                            <div v-for="(f, i) in profil.formulir" :key="f.no" class="plw-form" :class="{ 'is-open': openForm === i }">
                                <button
                                    type="button" class="plw-form__head"
                                    :class="{ 'is-full': bioFull }"
                                    :disabled="bioFull"
                                    @click="openForm = openForm === i ? -1 : i"
                                >
                                    <span class="plw-form__step" :class="{ 'is-ok': !!f.waktuKirim }">{{ String(i + 1).padStart(2, '0') }}</span>
                                    <span style="flex: 1; min-width: 0">
                                        <span class="plw-form__title">{{ f.label }}</span>
                                        <span class="plw-form__sub">Tahap {{ f.urutan || '—' }} · {{ f.sumber === 'PENDAFTARAN' ? 'Pendaftaran' : 'Tahap seleksi' }}</span>
                                    </span>
                                    <span class="plw-form__pill" :class="f.waktuKirim ? 'is-ok' : 'is-wait'">{{ f.waktuKirim ? 'Lengkap' : 'Menunggu' }}</span>
                                    <svg class="plw-form__chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.4" stroke-linecap="round"><path d="M6 9l6 6 6-6" /></svg>
                                </button>
                                <div v-if="openForm === i || bioFull" class="plw-form__body">
                                    <!-- Formulir yang isiannya kosong tapi berkasnya ada
                                         (mis. hanya foto verifikasi) tetap menggambar kisi
                                         ini — kalau syaratnya cuma jawaban, berkasnya ikut
                                         hilang bersama kisinya. -->
                                    <template v-if="f.jawaban.length || berkasLepas(f).length">
                                    <!-- Pil ringkas: menjawab "formulir ini sebenarnya
                                         berisi apa" sebelum satu barisnya dibaca. -->
                                    <div class="plw-fsum">
                                        <i class="bi bi-check2-square"></i>
                                        <span>{{ ringkasFormulir(f) }}</span>
                                    </div>

                                    <!-- ═══ BIODATA, DIKELOMPOKKAN SEPERTI SAAT DIISI ═══
                                         Judul & ikon kelompok datang dari skema formulir
                                         (langkah), bukan dari nama kunci. Lihat grupIsian().

                                         Teleport-nya MEMINDAHKAN node ini ke hamparan layar
                                         penuh, bukan menyalinnya. Akordion yang sedang
                                         terbuka, gulungan riwayat, dan uraian yang sudah
                                         dilebarkan ikut pindah apa adanya — dan kembali
                                         seperti semula begitu ditutup. -->
                                    <template v-for="g in grupIsian(f)" :key="g.kunci">
                                    <div class="plw-ghead">
                                        <span class="plw-ghead__ico"><i class="bi" :class="g.ikon"></i></span>
                                        <span class="plw-ghead__lbl">{{ g.judul }}</span>
                                        <span class="plw-ghead__garis"></span>
                                    </div>
                                    <div class="plw-fields">
                                        <div
                                            v-for="j in g.items" :key="j.key"
                                            class="plw-field" :class="{ 'is-panjang': isianPanjang(j) || (j.baris && j.baris.length) }"
                                        >
                                            <div v-if="!(j.baris && j.baris.length)" class="plw-field__k">{{ labelIsian(f, j) }}</div>
                                            <!-- ISIAN BERULANG (riwayat kerja, organisasi,
                                                 sertifikasi) — LINIMASA, bukan deretan
                                                 pasangan label-nilai yang mengalir bebas.

                                                 Bentuk lamanya memakai flex-wrap: tiap
                                                 pasangan selebar isinya sendiri, jadi
                                                 "Perusahaan" di baris 1 dan "Perusahaan" di
                                                 baris 2 berhenti di tempat yang berbeda.
                                                 Mata tidak punya kolom untuk diikuti, dan
                                                 uraian panjang menyeret sisa pasangan ke
                                                 posisi acak — inilah yang terbaca sebagai
                                                 tumpang tindih.

                                                 Sekarang: satu kartu per baris di satu garis
                                                 waktu, isinya grid berkolom tetap. Yang
                                                 panjang (uraian) mengambil baris sendiri,
                                                 jadi ia tidak lagi mendorong tetangganya.

                                                 Akordion + gulung dalam: riwayat kerja bisa
                                                 belasan baris, dan tanpa batas tinggi satu
                                                 field mendorong seluruh isi formulir jauh ke
                                                 bawah. -->
                                            <div v-if="j.baris && j.baris.length" class="plw-field__v plw-tl">
                                                <button
                                                    type="button" class="plw-tl__head"
                                                    :aria-expanded="!riwayatTutup(f.no, j.key)"
                                                    @click="toggleRiwayat(f.no, j.key)"
                                                >
                                                    <span class="plw-tl__ico"><i class="bi" :class="ikonRiwayat(j)"></i></span>
                                                    <span class="plw-tl__cap">{{ labelIsian(f, j) }}</span>
                                                    <span class="plw-tl__n">{{ j.baris.length }}</span>
                                                    <span class="plw-tl__garis"></span>
                                                    <svg
                                                        class="plw-tl__chev" :class="{ 'is-up': !riwayatTutup(f.no, j.key) }"
                                                        width="15" height="15" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2.6" stroke-linecap="round"
                                                    ><path d="M6 9l6 6 6-6" /></svg>
                                                </button>

                                                <div v-show="!riwayatTutup(f.no, j.key)" class="plw-tl__body">
                                                    <ol class="plw-tl__line">
                                                        <li v-for="(row, ri) in j.baris" :key="ri" class="plw-tl__item">
                                                            <span class="plw-tl__dot">{{ ri + 1 }}</span>
                                                            <div class="plw-tl__card">
                                                                <!-- KEPALA KARTU — judul + pil periode di kanan.
                                                                     Dulu keduanya sekadar dua sel di kisi yang
                                                                     sama: nama tempat kerja dan rentang tahunnya
                                                                     tampil sebesar dan sepucat kolom lain, jadi
                                                                     satu daftar berisi lima riwayat tidak punya
                                                                     satu pun titik pandang. -->
                                                                <div v-if="selBarisLead(j.baris, row)" class="plw-tl__top">
                                                                    <span class="plw-tl__judul">{{ nilaiTampil(selBarisLead(j.baris, row)) }}</span>
                                                                    <span v-if="selBarisPeriode(j.baris, row)" class="plw-tl__periode">{{ selBarisPeriode(j.baris, row) }}</span>
                                                                </div>
                                                                <!-- ISIAN RINGKAS — kisi berkolom tetap.
                                                                     Yang pertama dinaikkan jadi judul kartu:
                                                                     pada riwayat kerja/organisasi kolom
                                                                     pertama selalu "nama tempatnya", dan
                                                                     itulah yang dicari mata lebih dulu.

                                                                     Sub-isian BERKAS tidak ikut kisi ini:
                                                                     ia turun ke jalur lampiran di dasar
                                                                     kartu (lihat di bawah), sebab satu baris
                                                                     bisa membawa beberapa lembar sekaligus
                                                                     dan sel kisi tak muat memuat daftar. -->
                                                                <div v-if="adaRingkas(j.baris, row)" class="plw-tl__grid">
                                                                    <template v-for="(p, pi) in row" :key="'r' + pi">
                                                                        <div
                                                                            v-if="!selUraian(j.baris, p, pi) && !berkasSel(p).length && !selSudahDipakai(j.baris, row, p, pi)"
                                                                            class="plw-tl__cell"
                                                                        >
                                                                            <span v-if="p.label" class="plw-tl__k">{{ p.label }}</span>
                                                                            <span class="plw-tl__v" :class="{ 'is-rp': selRupiah(p) }">{{ nilaiTampil(p) }}</span>
                                                                        </div>
                                                                    </template>
                                                                </div>

                                                                <!-- URAIAN — satu blok penuh, DILIPAT.
                                                                     Panjangnya tak terbatas dan tak seragam antar
                                                                     kandidat, jadi yang menentukan perlu tidaknya
                                                                     tombol adalah hasil UKUR di layar, bukan jumlah
                                                                     hurufnya. Lihat UraianLipat.vue. -->
                                                                <template v-for="(p, pi) in row" :key="'u' + pi">
                                                                    <div v-if="selUraian(j.baris, p, pi)" class="plw-tl__note">
                                                                        <span v-if="p.label" class="plw-tl__k">{{ p.label }}</span>
                                                                        <UraianLipat :teks="String(p.nilai ?? '')" :baris="4" />
                                                                    </div>
                                                                </template>

                                                                <!-- LAMPIRAN BARIS INI — BISA LEBIH DARI SATU.
                                                                     Satu sertifikat kerap datang berlembar:
                                                                     piagamnya, transkrip nilainya, surat
                                                                     keterangannya. Bentuk lama hanya sanggup
                                                                     menggambar SATU tombol per sub-isian,
                                                                     jadi lembar kedua dan seterusnya masuk ke
                                                                     basis data lalu tak pernah muncul di layar
                                                                     mana pun — tanpa galat, tanpa jejak.
                                                                     Sekarang seluruhnya berjajar di jalurnya
                                                                     sendiri, bernomor, di dasar kartu. -->
                                                                <template v-for="(p, pi) in row" :key="'b' + pi">
                                                                    <div v-if="berkasSel(p).length" class="plw-tl__berkas">
                                                                        <div class="plw-tl__berkashead">
                                                                            <i class="bi bi-paperclip"></i>
                                                                            <span>{{ p.label || 'Lampiran' }}</span>
                                                                            <span class="plw-tl__berkasn">{{ berkasSel(p).length }}</span>
                                                                        </div>
                                                                        <button
                                                                            v-for="(b, bi) in berkasSel(p)" :key="bi"
                                                                            type="button" class="plw-tl__file"
                                                                            :title="b.nama" @click="bukaDok(b)"
                                                                        >
                                                                            <span class="plw-tl__fileico">
                                                                                <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                                                                            </span>
                                                                            <span class="plw-tl__filenama">{{ b.nama }}</span>
                                                                            <span v-if="b.olehAdmin" class="plw-oleh" :title="judulOleh(b)"><i class="bi bi-person-badge-fill"></i>Admin</span>
                                                                            <span class="plw-tl__fileext">{{ (b.ext || '').toUpperCase() }}</span>
                                                                            <span class="plw-tl__filego">Lihat</span>
                                                                        </button>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </li>
                                                    </ol>
                                                </div>
                                            </div>
                                            <!-- Nominal rupiah tampil sebagai UANG, bukan deret
                                                 angka. "9500000" memaksa peninjau menghitung
                                                 digitnya sendiri untuk tahu ini sembilan juta
                                                 atau sembilan puluh juta — dan itu keliru
                                                 justru saat menawar gaji. -->
                                            <!-- ══ DAFTAR BUTIR ══
                                                 "Sebutkan minimal 5 hal" tersimpan sebagai lima
                                                 jawaban terpisah. Dirangkai koma, kelimanya jadi
                                                 satu kalimat — "sdfa, fa, fafda, fafa, fa" — dan
                                                 peninjau tidak lagi bisa melihat mana yang lima
                                                 gagasan dan mana yang satu gagasan berkoma.
                                                 Padahal jumlah itulah yang dijaga formulirnya. -->
                                            <ol v-else-if="isianDaftar(j)" class="plw-field__v plw-butir">
                                                <li v-for="(butir, bi) in isianDaftar(j)" :key="bi">{{ butir }}</li>
                                            </ol>
                                            <div v-else class="plw-field__v" :class="{ 'is-rp': isianRupiah(f, j), 'is-mono': selMono(f, j) }">{{ nilaiIsian(f, j) }}</div>
                                        </div>
                                    </div>
                                    </template>

                                    <!-- ═══ DOKUMEN TERLAMPIR ═══
                                         Seksi sendiri, bukan baris di dalam kisi isian.
                                         Termasuk berkas tanpa pertanyaan (foto verifikasi,
                                         yang diambil sistem saat melamar) dan pertanyaan
                                         dokumen yang masih KOSONG — kartu redup yang tak
                                         bisa ditekan. Daftar yang hanya memuat berkas yang
                                         ada tak pernah bisa menjawab apa yang belum masuk. -->
                                    <template v-if="dokFormulir(f).length">
                                    <div class="plw-ghead">
                                        <span class="plw-ghead__ico"><i class="bi bi-folder2-open"></i></span>
                                        <span class="plw-ghead__lbl">Dokumen Terlampir</span>
                                        <span class="plw-ghead__n">{{ dokFormulir(f).filter((d) => d.berkas).length }} / {{ dokFormulir(f).length }}</span>
                                        <span class="plw-ghead__garis"></span>
                                    </div>
                                    <div class="plw-docgrid">
                                        <component
                                            :is="d.berkas ? 'button' : 'div'"
                                            v-for="d in dokFormulir(f)" :key="d.id"
                                            :type="d.berkas ? 'button' : null"
                                            class="plw-doc" :class="{ 'is-kosong': !d.berkas }"
                                            :title="d.berkas ? d.berkas.nama : 'Belum diunggah kandidat'"
                                            @click="d.berkas && bukaDok(d.berkas)"
                                        >
                                            <!-- GAMBAR TAMPIL SEBAGAI GAMBAR.
                                                 Foto verifikasi wajah sebelumnya hanya
                                                 berupa ikon berkas abu-abu berikut namanya,
                                                 "foto_verifikasi-verifikasi.jpg". Pertanyaan
                                                 yang dibawa peninjau ke kartu itu cuma satu —
                                                 apakah wajahnya cocok dengan KTP — dan itu
                                                 tidak bisa dijawab oleh nama berkas. Ia harus
                                                 membuka lightbox satu per satu hanya untuk
                                                 melihat apa yang seharusnya langsung terlihat. -->
                                            <span class="plw-doc__ico" :class="{ 'is-foto': d.berkas && d.berkas.isImage }">
                                                <img
                                                    v-if="d.berkas && d.berkas.isImage"
                                                    :src="d.berkas.url"
                                                    :alt="d.nama"
                                                    loading="lazy"
                                                    @error="(e) => (e.target.style.display = 'none')"
                                                />
                                                <i v-else-if="d.berkas" class="bi bi-file-earmark-pdf-fill"></i>
                                                <i v-else class="bi bi-file-earmark-x"></i>
                                            </span>
                                            <span class="plw-doc__in">
                                                <span class="plw-doc__nama">{{ d.nama }}</span>
                                                <span class="plw-doc__file">{{ d.berkas ? d.berkas.nama : 'Belum diunggah' }}</span>
                                                <span v-if="d.berkas && d.berkas.olehAdmin" class="plw-oleh plw-oleh--blok" :title="judulOleh(d.berkas)"><i class="bi bi-person-badge-fill"></i>Diunggah admin</span>
                                            </span>
                                            <span v-if="d.berkas" class="plw-doc__ext">{{ (d.berkas.ext || '').toUpperCase() }}</span>
                                        </component>
                                    </div>
                                    </template>
                                    </template>
                                </div>
                            </div>
                        </div>

                        </Teleport>
                    </div>
                </div>


            </template>

            <!-- ═══ KOLOM KANAN: PRATINJAU BERKAS ═══
                 Slot `aside` AdminModal — kolom setinggi penuh di antara
                 kepala dan kaki modal, DI LUAR area gulir isi.

                 Bentuk sebelumnya menaruh panel ini sebagai kolom biasa di
                 dalam isi. Akibatnya ia ikut digulir dan ujung bawahnya
                 selalu tertutup kaki modal: pratinjaunya secara teknis ada,
                 tapi tidak pernah benar-benar terlihat — persis keluhan yang
                 melahirkan bentuk sekarang. Di sini pratinjau punya tinggi
                 tetap milik sendiri, dan panel kirilah yang menggulir. -->
            <!-- Kolomnya digambar hanya bila panelnya terbuka — bukan digambar
                 lalu disembunyikan. Slot yang kosong membuat AdminModal kembali
                 satu kolom, jadi lebar yang tadi dipakai pratinjau benar-benar
                 dikembalikan ke panel kiri, bukan disisakan sebagai jalur mati
                 selebar 340px. Pemanggilnya kembali ada di hero. -->
            <template v-if="detailKandidat && dokPanel" #aside>
                <!-- ═══ PEMBACA BERKAS ═══
                     Empat lapis dari atas ke bawah, dan ketiga lapis pertama
                     berukuran tetap supaya lapis keempat mendapat sisanya:

                       1. bilah judul — nama lembar yang sedang dibuka + tindakan;
                       2. penyaring, satu baris yang digeser ke samping;
                       3. jalur lembar, juga digeser ke samping;
                       4. BIDANG TAMPIL, mengambil seluruh sisa tinggi kolom.

                     Penyaring dan daftar sengaja jadi jalur mendatar, bukan
                     petak bertumpuk seperti bentuk sebelumnya: keduanya cuma
                     alat untuk SAMPAI ke lembarnya, dan tiap baris yang mereka
                     ambil ke bawah diambil langsung dari tinggi pratinjau —
                     satu-satunya bagian yang sebenarnya ingin dilihat orang. -->
                <div class="plw-dok">
                    <div class="plw-dok__head">
                        <span class="plw-dok__headico"><i class="bi" :class="dokLihat ? ikonBerkas(dokLihat) : 'bi-folder2-open'"></i></span>
                        <span class="plw-dok__headtxt">
                            <b :title="dokLihat ? dokLihat.nama : 'Berkas Kandidat'">{{ dokLihat ? dokLihat.nama : 'Berkas Kandidat' }}</b>
                            <em :title="dokLihat ? dokLihat.konteks : ''">
                                <template v-if="dokLihat">{{ dokLihat.folderLabel }} · {{ dokLihat.ext || 'FILE' }}<template v-if="dokLihat.ukuran"> · {{ ukuranBerkas(dokLihat.ukuran) }}</template><template v-if="dokLihat.waktu"> · {{ tglId(dokLihat.waktu) }}</template></template>
                                <template v-else>{{ dokSemua.length }} lembar · {{ profil.formulir.length }} formulir</template>
                            </em>
                        </span>
                        <span v-if="dokLihat && dokLihat.berkas && dokLihat.berkas.olehAdmin" class="plw-oleh" :title="judulOleh(dokLihat.berkas)"><i class="bi bi-person-badge-fill"></i>Admin</span>
                        <button
                            v-if="dokLihat" type="button" class="plw-dok__hbtn"
                            title="Buka besar di tengah layar" @click="bukaDok(dokLihat.berkas)"
                        >
                            <i class="bi bi-arrows-fullscreen"></i>
                        </button>
                        <a
                            v-if="dokLihat" :href="dokLihat.berkas.url" target="_blank" rel="noopener"
                            class="plw-dok__hbtn" title="Buka di tab baru"
                        >
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <button type="button" class="plw-dok__hbtn" title="Sembunyikan kolom berkas" @click="setDokPanel(false)">
                            <i class="bi bi-chevron-double-right"></i>
                        </button>
                    </div>

                    <!-- ── PENYARING, TERSEMBUNYI SAMPAI DIMINTA ──
                         Kolom ini punya tiga bilah tetap di atas pratinjau —
                         judul, penyaring, jalur lembar — dan PDF membawa bilah
                         alatnya sendiri di bawah semuanya. Empat bilah bertumpuk
                         memakan hampir sepertiga tinggi kolom sebelum satu baris
                         dokumen pun terbaca.

                         Yang dipangkas adalah penyaring, bukan jalur lembar:
                         jalur lembar dipakai di hampir setiap kunjungan (itulah
                         cara berpindah berkas), sementara menyaring baru perlu
                         ketika lembarnya banyak — dan ketika itu tiba, satu klik
                         membukanya. Lencana kecil di tombolnya menyebutkan bahwa
                         ada penyaring yang sedang menyala, supaya daftar yang
                         terpotong tidak pernah terbaca sebagai berkas yang
                         hilang. -->
                    <div v-if="dokSemua.length" class="plw-dok__strip">
                        <button
                            type="button" class="plw-dok__saring" :class="{ 'is-on': dokSaring, 'is-aktif': dokTersaring }"
                            :title="dokSaring ? 'Tutup penyaring' : 'Saring & urutkan berkas'"
                            :aria-expanded="dokSaring"
                            @click="dokSaring = !dokSaring"
                        >
                            <i class="bi" :class="dokSaring ? 'bi-x-lg' : 'bi-funnel-fill'"></i>
                            <em v-if="dokTersaring && !dokSaring"></em>
                        </button>

                        <!-- Jalur lembar. Petak kecil berjajar mendatar, yang
                             sedang dibuka bertanda. Gambar memakai lembarannya
                             sendiri sebagai petak: satu deret berisi lima
                             "FOTO.jpg" tak terbedakan oleh ikon apa pun, tapi
                             langsung terbedakan oleh gambarnya. -->
                        <div v-if="dokDaftar.length" class="plw-dok__striplist">
                            <button
                                v-for="(b, i) in dokDaftar" :key="b.id"
                                type="button" class="plw-dok__sitem"
                                :class="{ 'is-on': dokLihat && dokLihat.id === b.id }"
                                :title="`${b.nama} — ${b.folderLabel} · ${b.file}`"
                                @click="lihatDok(i)"
                            >
                                <span class="plw-dok__sthumb" :class="{ 'is-img': b.isImage }">
                                    <img v-if="b.isImage" :src="b.berkas.url" :alt="b.nama" loading="lazy" @error="gagalThumb($event)">
                                    <i v-else class="bi" :class="ikonBerkas(b)"></i>
                                    <em class="plw-dok__sext">{{ b.ext || 'FILE' }}</em>
                                    <em v-if="b.berkas && b.berkas.olehAdmin" class="plw-dok__sadm" title="Diunggah admin"><i class="bi bi-person-badge-fill"></i></em>
                                </span>
                                <span class="plw-dok__sname">{{ b.nama }}</span>
                            </button>
                        </div>
                        <!-- Penyaring yang memulangkan kosong menjelaskan dirinya
                             DI TEMPAT daftarnya, bukan di bidang tampil: di situ
                             pula tombol yang membatalkannya berada. -->
                        <div v-else class="plw-dok__stripkosong">
                            <i class="bi bi-search"></i>
                            <span>Tidak ada berkas yang cocok</span>
                            <button type="button" @click="bersihkanSaring">Tampilkan semua</button>
                        </div>
                    </div>

                    <!-- Laci penyaring — turun DI BAWAH jalur lembar, jadi ia
                         mendorong pratinjau hanya selama benar-benar dipakai. -->
                    <div v-show="dokSaring" class="plw-dok__filter">
                        <div class="plw-dok__cari">
                            <i class="bi bi-search"></i>
                            <input v-model="dokCari" type="text" placeholder="Cari berkas…">
                            <button v-if="dokCari" type="button" title="Bersihkan" @click="dokCari = ''"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <!-- Urutan bisa dibalik. Bawaannya terbaru dulu, tapi
                             membaca riwayat kandidat dari awal (pendaftaran →
                             tahap akhir) menuntut yang sebaliknya. -->
                        <button
                            type="button" class="plw-dok__urut"
                            :title="dokUrutBaru ? 'Urut: terbaru dulu — klik untuk terlama dulu' : 'Urut: terlama dulu — klik untuk terbaru dulu'"
                            @click="dokUrutBaru = !dokUrutBaru"
                        >
                            <i class="bi" :class="dokUrutBaru ? 'bi-sort-down' : 'bi-sort-up'"></i>
                            <span>{{ dokUrutBaru ? 'Terbaru' : 'Terlama' }}</span>
                        </button>
                        <div class="plw-dok__tabs" role="tablist">
                            <button
                                v-for="t in dokTabs" :key="t.key"
                                type="button" class="plw-dok__tab" :class="{ 'is-on': dokTab === t.key }"
                                role="tab" :aria-selected="dokTab === t.key" :title="t.judul || t.label"
                                @click="dokTab = t.key"
                            >
                                <i class="bi" :class="t.ikon"></i>
                                <span>{{ t.label }}</span>
                                <em>{{ t.jumlah }}</em>
                            </button>
                        </div>
                    </div>

                    <!-- ── BIDANG TAMPIL ──
                         Mengambil seluruh sisa tinggi kolom, dan kolomnya sendiri
                         turun sampai dasar jendela — kaki modal duduk di kolom
                         kiri, bukan membentang memotong yang ini. -->
                    <div class="plw-dok__view">
                        <div v-if="loadingProfil" class="plw-dok__state">
                            <span class="plw-spin plw-spin--lg"></span>
                            <span>Memuat berkas…</span>
                        </div>
                        <div v-else-if="!dokSemua.length" class="plw-dok__state">
                            <span class="plw-dok__bigico"><i class="bi bi-folder-x"></i></span>
                            <span>Kandidat ini belum mengunggah berkas apa pun.</span>
                        </div>
                        <!-- Penyaring yang memulangkan kosong sudah dijelaskan
                             di jalur lembar, tempat tombol pembatalnya berada —
                             tidak diulang di sini. -->
                        <div v-else-if="!dokLihat" class="plw-dok__state">
                            <span class="plw-dok__bigico"><i class="bi bi-hand-index-thumb"></i></span>
                            <span>Pilih satu berkas di jalur atas.</span>
                        </div>
                        <div v-else-if="dokMuat" class="plw-dok__state">
                            <span class="plw-spin plw-spin--lg"></span>
                            <span>Memuat berkas…</span>
                        </div>
                        <div v-else-if="dokGagal" class="plw-dok__state">
                            <i class="bi bi-exclamation-triangle-fill" style="font-size: 26px; color: #f87171"></i>
                            <span>Gagal memuat berkas.</span>
                            <button type="button" class="plw-dok__retry" @click="dokCoba">Coba lagi</button>
                        </div>
                        <!-- Berkas yang bukan gambar dan bukan PDF (docx, xlsx)
                             tak bisa disematkan peramban mana pun. Mengatakannya
                             terus terang lebih baik daripada iframe kosong yang
                             terbaca sebagai gagal memuat. -->
                        <div v-else-if="!dokBisaTampil" class="plw-dok__state">
                            <span class="plw-dok__bigico"><i class="bi" :class="ikonBerkas(dokLihat)"></i></span>
                            <span><b>{{ dokLihat.ext || 'Berkas' }}</b> tidak bisa dipratinjau di layar.</span>
                            <a :href="dokLihat.berkas.url" target="_blank" rel="noopener" class="plw-dok__retry">Unduh berkasnya</a>
                        </div>

                        <!-- Keduanya digambar DI LUAR rantai v-if/v-else di atas:
                             mereka harus tetap ada di DOM selagi `dokMuat` benar,
                             sebab justru merekalah yang memicu peristiwa `load`
                             yang mematikan penanda memuat itu. Kalau ikut rantai,
                             spinnernya menunggu peristiwa dari elemen yang belum
                             pernah dipasang — dan menunggu selamanya. -->
                        <iframe
                            v-if="dokLihat && dokBisaTampil && dokPdf"
                            v-show="!dokMuat && !dokGagal"
                            :src="dokSrc" :title="dokLihat.nama"
                            class="plw-dok__pdf" @load="dokSelesai()"
                        ></iframe>
                        <img
                            v-else-if="dokLihat && dokBisaTampil"
                            v-show="!dokMuat && !dokGagal"
                            :src="dokSrc" :alt="dokLihat.nama" class="plw-dok__img"
                            @load="dokSelesai()" @error="dokSelesai(true)"
                        >


                    </div>
                </div>
            </template>

            <!-- FOOTER AKSI — menempel di kaki modal, tidak ikut menggulung.
                 Keputusan adalah alasan jendela ini dibuka; kalau tombolnya ikut
                 hanyut ke bawah, admin harus menggulung dulu setiap kali. Indikator
                 kuota ikut pindah supaya alasan tombol Loloskan hilang/mati tetap
                 terbaca di sebelah tombolnya. -->
            <template #footer>
                <div v-if="detailKandidat && detailKandidat.butuhKeputusan" class="plw-foot">
                    <!-- JAWABAN KANDIDAT atas penawaran. Ditaruh di atas tombol
                         karena inilah yang menentukan tombol mana yang benar:
                         diamnya kandidat dan persetujuannya menuntut tindakan
                         yang sama sekali berbeda, dan admin tak boleh menebaknya. -->
                    <div v-if="detailKandidat.tanggapan" class="plw-jawab" :class="detailKandidat.tanggapan.jawab === 'TERIMA' ? 'is-ya' : 'is-no'">
                        <i class="bi" :class="detailKandidat.tanggapan.jawab === 'TERIMA' ? 'bi-hand-thumbs-up-fill' : 'bi-box-arrow-left'"></i>
                        <div style="min-width: 0">
                            <b>Kandidat {{ detailKandidat.tanggapan.jawab === 'TERIMA' ? 'MENERIMA' : 'MUNDUR' }}</b>
                            <span v-if="detailKandidat.tanggapan.waktu"> · {{ tglId(detailKandidat.tanggapan.waktu) }}</span>
                            <p v-if="detailKandidat.tanggapan.catatan">“{{ detailKandidat.tanggapan.catatan }}”</p>
                        </div>
                    </div>
                    <!-- Keterangan "Penawaran belum diajukan" DIHAPUS.

                         Ia menjelaskan kenapa tombol "Kandidat Menolak / Mundur"
                         belum ada — padahal barisnya memang tidak dirender sama
                         sekali (`putusanKandidat` kosong), jadi tidak ada yang
                         perlu dijelaskan. Yang tersisa hanyalah paragraf yang
                         harus ikut diperbaiki setiap kali alur penawaran
                         berubah, dan dua kali ia tertinggal: ia sempat
                         menjanjikan undangan email untuk negosiasi yang
                         berjadwal privat, dan portal Terima/Mundur yang sudah
                         lama dicabut. Keterangan yang menuntut perawatan tapi
                         tidak menahan kesalahan apa pun lebih baik tidak ada.

                         Langkah berikutnya tetap terbaca di tempat kejadiannya:
                         tombol "Atur Jadwal" di rapor tes, berikut keterangan
                         di modalnya bila jadwal itu internal. -->
                    <!-- Peringatan "Kandidat belum menjawab penawaran — ia bisa
                         menekan Terima / Mundur di portalnya" DIHAPUS.
                         Tombol itu sudah dicabut dari portal; kalimatnya
                         mengarahkan tim ke sesuatu yang tidak ada. Jawabannya
                         kini dicatat tim sendiri di modal "Tandai Hadir" pada
                         aktivitas negosiasi. -->

                    <!-- DITAHAN — mendahului semua keterangan lain. Selama hold
                         berlaku, seluruh tombol keputusan terkunci: melepasnya
                         adalah satu klik, dan klik itulah yang memaksa admin
                         sadar ada alasan kenapa kandidat ini sengaja belum
                         diputus. Tanpa gerbang ini, hold cuma hiasan. -->
                    <div v-if="detailKandidat.hold" class="plw-hold">
                        <div class="plw-hold__top">
                            <span class="plw-hold__ico"><i class="bi bi-pause-circle-fill"></i></span>
                            <div style="min-width: 0; flex: 1">
                                <b>Ditahan{{ detailKandidat.hold.alasanNama ? ' — ' + detailKandidat.hold.alasanNama : '' }}</b>
                                <small>
                                    Oleh {{ detailKandidat.hold.olehSiapa || '—' }}
                                    <template v-if="detailKandidat.hold.sejak"> · sejak {{ tglId(detailKandidat.hold.sejak) }}</template>
                                    <!-- Lama tertahan dalam HARI KERJA, kalender yang sama
                                         dengan SLA. Ini yang membedakan proses yang lambat
                                         karena rekruter dari yang berhenti menunggu pihak
                                         lain — dan angkanya bisa ditunjukkan saat ditanya. -->
                                    <template v-if="detailKandidat.hold.hariKerja > 0">
                                        · <b>{{ detailKandidat.hold.hariKerja }} hari kerja</b> tertahan
                                    </template>
                                    · kandidat <b>tidak</b> dikirimi pemberitahuan apa pun.
                                </small>
                            </div>
                            <button type="button" class="plw-hold__lepas" @click="askHold(false)">
                                <i class="bi bi-play-fill"></i> Lanjutkan
                            </button>
                        </div>
                        <KontenAman
                            v-if="detailKandidat.hold.catatanHtml"
                            :html="detailKandidat.hold.catatanHtml"
                            ringkas
                            class="plw-hold__cat"
                        />
                        <p v-else-if="detailKandidat.hold.catatan" class="plw-hold__cat">{{ detailKandidat.hold.catatan }}</p>
                    </div>

                    <!-- ── JEJAK PENAHANAN YANG SUDAH SELESAI ────────────────
                         Muncul HANYA saat tahapnya tidak sedang ditahan — kalau
                         sedang ditahan, angkanya sudah tampil di panel di atas.

                         Ini yang membuat hold berhenti jadi sekadar informasi:
                         tenggat MPP tidak bergeser (satu MPP dipakai bersama
                         banyak kandidat), tapi rekruter punya angka untuk
                         menjelaskan proses yang berhenti di luar kendalinya. -->
                    <p v-if="!detailKandidat.hold && detailKandidat.holdTotalHariKerja > 0" class="plw-holdsisa">
                        <i class="bi bi-clock-history"></i>
                        Tahap ini pernah tertahan <b>{{ detailKandidat.holdTotalHariKerja }} hari kerja</b>
                        — tidak mengurangi tenggat SLA MPP.
                    </p>

                    <!-- JADWAL PENGISIAN FORMULIR — selama formulir tahap ini
                         belum terkirim. Belum berjadwal → "Atur jadwal"; sudah →
                         hanya "Perpanjang" (jadwal tidak disunting, keputusan user). -->
                    <div v-if="detailKandidat.batas && !detailKandidat.batas.terkirim" class="plw-batasp" :class="kelasBatas(detailKandidat.batas)">
                        <div class="plw-batasp__top">
                            <span class="plw-batasp__ic"><i class="bi" :class="detailKandidat.batas.terkunci ? 'bi-lock-fill' : 'bi-hourglass-split'"></i></span>
                            <div class="plw-batasp__isi">
                                <b v-if="detailKandidat.batas.belumDiatur">Formulir terkunci — jadwal pengisian belum diatur</b>
                                <b v-else-if="detailKandidat.batas.batas">
                                    <template v-if="detailKandidat.batas.bukaTeks">Dibuka {{ detailKandidat.batas.bukaTeks }} · </template>Batas {{ detailKandidat.batas.teks }}
                                </b>
                                <b v-else>Tanpa jadwal pengisian</b>
                                <small>{{ keteranganBatas(detailKandidat.batas) }}</small>
                            </div>
                            <button type="button" class="plw-batasp__ubah" @click="askBatasKandidat([detailKandidat])">
                                <template v-if="kolomDilepas(kolomDari(detailKandidat))"><i class="bi bi-unlock"></i> Lepas batas waktu</template>
                                <template v-else-if="detailKandidat.batas.batas"><i class="bi bi-hourglass-top"></i> Perpanjang</template>
                                <template v-else><i class="bi bi-calendar-plus"></i> Atur jadwal</template>
                            </button>
                        </div>
                        <button v-if="detailKandidat.tahapId" type="button" class="plw-batasp__rw" @click="toggleRiwayatBatas">
                            <i class="bi" :class="riwayatBatasBuka ? 'bi-chevron-up' : 'bi-clock-history'"></i>
                            {{ riwayatBatasBuka ? 'Tutup riwayat' : 'Riwayat jadwal' }}
                        </button>
                        <ul v-if="riwayatBatasBuka" class="plw-batasp__list">
                            <li v-if="riwayatBatas.muat" class="is-kosong"><span class="plw-spin"></span> Memuat…</li>
                            <li v-else-if="!riwayatBatas.baris.length" class="is-kosong">Belum ada perubahan.</li>
                            <li v-for="(h, i) in riwayatBatas.baris" :key="i">
                                <b>
                                    <span v-if="h.sumber === 'UBAH'" class="plw-rwk is-ubah">Diedit superadmin</span>
                                    {{ h.baru || 'tanpa batas' }}
                                </b>
                                <small>{{ h.alasan || '—' }} · {{ h.oleh || 'SISTEM' }} · {{ tglJamId(h.at) }}</small>
                            </li>
                        </ul>
                    </div>

                    <!-- KEHADIRAN DULU, baru keputusan. Selama masih ada aktivitas
                         berjadwal yang kehadirannya belum ditetapkan, meloloskan
                         berarti memutuskan tanpa tahu kandidatnya datang atau tidak.
                         Alasannya ditulis di sini — tombol mati tanpa keterangan
                         membuat admin mengira layarnya rusak. -->
                    <!-- APA YANG MASIH KURANG — disebut satu per satu, bukan
                         "tidak bisa diloloskan" yang memindahkan tebakan ke
                         orang berikutnya. Hanya tombol yang MEMAJUKAN yang
                         terkunci; menutup lamaran tetap selalu bisa. -->
                    <!-- Daftarnya MENDATAR, bukan butir bertumpuk. Tiga aktivitas
                         yang belum tuntas dulu berarti tiga baris penuh di atas
                         tombol keputusan — kaki modal setinggi separuh layar, dan
                         tombol yang justru jadi alasan jendela ini dibuka terdorong
                         keluar pandangan. Sebagai chip, tiga aktivitas muat dalam
                         satu baris; nama dan sebabnya tetap disebut lengkap. -->
                    <!-- BISA DITUTUP. Isinya penjelasan, bukan peringatan yang
                         menuntut tindakan — dan sesudah dibaca sekali ia cuma
                         memakan sepertiga kaki modal. Yang ditutup TIDAK hilang
                         artinya: tombol yang terkunci tetap membawa alasannya di
                         tooltip, dan lencana kecil di baris tombol mengembalikan
                         keterangan ini kapan pun. -->
                    <div v-else-if="belumTuntas.length && !notaTutup" class="plw-kuota is-hadir">
                        <i class="bi bi-hourglass-split"></i>
                        <div class="plw-kuota__isi">
                            <b>Tahap ini belum tuntas</b>
                            <div class="plw-kuota__chips">
                                <span v-for="(x, i) in belumTuntas" :key="i" class="plw-kuota__chip" :title="`${x.label} — ${x.sebab}`">
                                    <b>{{ x.label }}</b><em>{{ x.sebab }}</em>
                                </span>
                            </div>
                            <span>Selama itu, <b>Loloskan</b> dan <b>Talent Pool</b> terkunci. <b>Tidak Lolos</b>, <b>Tahan Dulu</b>, dan keputusan dari kandidat tetap bisa dipakai.</span>
                        </div>
                        <button type="button" class="plw-kuota__x" title="Tutup keterangan" @click="tutupNota(true)">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Indikator kuota (muncul saat kandidat di tahap akhir & posisi berkuota) -->
                    <!-- KETERANGAN KUOTA — dua batas, dan keduanya bisa menutup:

                           LOKER  jatah program ini
                           MPP    rencana yang disetujui, LINTAS program

                         Yang kedua baru ada sejak buku kursi MPP dipasang.
                         Sebelumnya papan hanya melihat jatah loker, sehingga
                         pada MPP yang dibuka di dua program tombol Loloskan
                         tetap hijau di program kedua walau kursinya sudah habis
                         di program pertama — dan penolakannya baru datang
                         sesudah alasan diketik dan modal dikirim.

                         Sebabnya disebut, bukan cuma "penuh": menambah jatah
                         loker dan menambah rencana MPP adalah dua tindakan yang
                         sama sekali berbeda, dan yang membaca perlu tahu yang
                         mana. -->
                    <div v-else-if="detailKandidat.diTahapAkhir && (detailKandidat.kuota > 0 || detailKandidat.mppKuota > 0)" class="plw-kuota" :class="{ 'is-penuh': detailKandidat.kuotaPenuh }">
                        <i class="bi" :class="detailKandidat.kuotaPenuh ? 'bi-lock-fill' : 'bi-people-fill'"></i>
                        <span v-if="detailKandidat.kuotaSebab === 'MPP'">
                            Kuota <b>MPP</b> sudah penuh ({{ detailKandidat.mppTerisi }}/{{ detailKandidat.mppKuota }} disetujui) —
                            termasuk penerimaan di program lain yang memakai MPP yang sama.
                            Loloskan dinonaktifkan; gunakan Talent Pool / Tidak Lolos, atau minta rencana MPP ditambah.
                        </span>
                        <span v-else-if="detailKandidat.kuotaSebab === 'LOKER'">
                            Kuota posisi penuh ({{ detailKandidat.terisiKuota }}/{{ detailKandidat.kuota }}) — Loloskan dinonaktifkan. Gunakan Talent Pool / Tidak Lolos.
                        </span>
                        <span v-else-if="detailKandidat.kuota > 0">
                            Sisa <b>{{ detailKandidat.sisaKuota }}</b> kursi dari {{ detailKandidat.kuota }} (terisi {{ detailKandidat.terisiKuota }}).
                            <template v-if="detailKandidat.mppSisa !== null && detailKandidat.mppSisa !== undefined">
                                MPP: sisa {{ detailKandidat.mppSisa }} dari {{ detailKandidat.mppKuota }}.
                            </template>
                        </span>
                        <span v-else>
                            MPP: sisa <b>{{ detailKandidat.mppSisa }}</b> kursi dari {{ detailKandidat.mppKuota }} disetujui.
                        </span>
                    </div>

                    <!-- KEPUTUSAN PERUSAHAAN — tombolnya DARI MASTER Hasil
                         Keputusan, bukan tiga tombol yang ditulis mati di sini.
                         Semua menunggu kehadiran ditetapkan lebih dulu: menggugurkan
                         atau menyimpan ke Talent Pool orang yang ternyata datang
                         sama kelirunya dengan meloloskan orang yang tidak datang.

                         Tombol ini HANYA MEMBUKA modal konfirmasi; syarat berkas
                         ditahan di tombol konfirmasi di dalamnya — satu-satunya
                         tempat mengunggah berkas justru ada di modal itu. -->
                    <!-- TOMBOL MATI HARUS MENJELASKAN DIRINYA.
                         Deretan tombol kelabu tanpa keterangan terbaca sebagai
                         aplikasi rusak, bukan sebagai batas wewenang — dan
                         tooltip saja tidak terbaca di layar sentuh. -->
                    <div v-if="!bolehPutus" class="plw-nogate">
                        <i class="bi bi-shield-lock-fill"></i>
                        <div>
                            <b>Anda tidak berwenang mengetuk keputusan.</b>
                            Semua keputusan — Loloskan, Tidak Lolos, Talent Pool, sampai Mengundurkan Diri —
                            menuntut izin <b>APPROVE</b> pada menu <b>Worklist Pelamar</b>.
                            Menandai kehadiran, mencatat hasil, menahan, dan menjadwalkan tetap bisa Anda lakukan.
                            <span>Minta penambahan izinnya di <b>Manajemen Hak Akses</b>.</span>
                        </div>
                    </div>

                    <!-- MASTER KOSONG BUKAN "TIDAK BERWENANG", DAN BUKAN PULA DIAM.
                         Seluruh tombol di bawah lahir dari Master Hasil Keputusan.
                         Ketika masternya belum terisi di sebuah lingkungan, deretan
                         tombolnya cuma lenyap tanpa sepatah kata — yang tertinggal
                         hanya "Tahan Dulu", dan admin membaca itu sebagai aplikasi
                         rusak, bukan sebagai data yang belum disiapkan. Satu kalimat
                         di sini memangkas penelusuran dari berjam-jam jadi sedetik. -->
                    <div v-else-if="!hasilKeputusan.length" class="plw-nomaster">
                        <i class="bi bi-database-exclamation"></i>
                        <div>
                            <b>Master Hasil Keputusan masih kosong di lingkungan ini.</b>
                            Tombol <b>Loloskan</b>, <b>Tidak Lolos</b>, dan <b>Talent Pool</b> seluruhnya dibangun
                            dari master tersebut — selama tabelnya belum terisi, tidak ada satu pun keputusan
                            yang bisa diketuk. Menahan dan menjadwalkan tetap berjalan.
                            <span>Jalankan seed <b>N_WEB_CAREERS_Master_Hasil_Keputusan</b> pada basis data lingkungan ini.</span>
                        </div>
                    </div>

                    <!-- SATU BARIS untuk seluruh keputusan tim, TAHAN DULU ikut di
                         dalamnya — seperti di desain. Bentuk lamanya menaruh Tahan
                         Dulu di barisnya sendiri selebar penuh: satu tombol netral
                         memakan tinggi yang sama dengan tiga tombol keputusan, dan
                         kaki modal tumbuh sampai sepertiga layar. Menahan memang
                         keadaan ketiga di samping "putuskan" dan "biarkan
                         menggantung", tapi ia sejajar dengan keputusan lain —
                         bukan lebih besar dari semuanya. -->
                    <div class="plw-actions" :class="{ 'is-padat': putusanPerusahaan.length + (detailKandidat.hold ? 0 : 1) > 3 }">
                        <button
                            v-for="h in putusanPerusahaan" :key="h.kode"
                            type="button" class="plw-btn-putus" :class="kelasPutus(h)"
                            :disabled="!!terkunciPutus(h)"
                            :title="terkunciPutus(h) || h.deskripsi"
                            :onClick="!!terkunciPutus(h) ? null : () => askPutus(detailKandidat, h.kode)"
                        >
                            <i class="bi" :class="h.ikon"></i>
                            {{ labelPutus(h) }}
                        </button>
                        <!-- TANPA SYARAT. Alasan menahan justru paling sering muncul
                             sebelum segala sesuatunya lengkap — kuota belum turun,
                             user department belum menjawab. Menuntut kehadiran atau
                             hasil tes lebih dulu berarti menutup jalan keluar tepat
                             saat ia paling dibutuhkan. -->
                        <button
                            v-if="!detailKandidat.hold"
                            type="button" class="plw-btn-putus is-hold"
                            title="Tunda keputusan tanpa memindahkan kandidat. Tidak ada email atau WA yang dikirim."
                            @click="askHold(true)"
                        >
                            <i class="bi bi-pause-circle"></i> Tahan Dulu
                        </button>
                        <!-- MENGULANG TAHAP — hanya untuk pemegang aksi ULANG.
                             Sengaja dipisah dari APPROVE: mengetuk keputusan dan
                             MEMBATALKAN keputusan yang sudah diketuk bukan
                             kewenangan yang sama. Tombolnya tidak digambar sama
                             sekali bila izinnya tidak ada — bukan digambar lalu
                             ditolak server, karena tombol yang selalu gagal
                             hanya melatih orang untuk mengabaikannya. -->
                        <button
                            v-if="bolehUlang"
                            type="button" class="plw-btn-putus is-ulang"
                            title="Kembalikan kandidat ke tahap sebelumnya. Jejak lamanya diarsipkan, tidak dihapus."
                            @click="bukaUlang()"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i> Ulangi Tahap
                        </button>
                    </div>

                    <!-- KEPUTUSAN DARI KANDIDAT — dipisah, bukan disamakan dengan
                         penilaian tim. Kandidat yang menolak penawaran atau mundur
                         BUKAN kandidat yang gagal seleksi; mencatatnya sebagai
                         "Tidak Lolos" membuat laporan berbunyi "gagal di tahap
                         penawaran" untuk orang yang justru lolos lalu memilih pergi,
                         dan itu menuntun ke perbaikan yang salah sasaran.

                         Labelnya SEBARIS dengan tombolnya, bukan judul di atasnya:
                         satu baris keterangan setinggi 18px yang cuma menamai satu
                         tombol di bawahnya adalah tinggi yang dibayar tanpa imbalan. -->
                    <div v-if="putusanKandidat.length" class="plw-actions2">
                        <span class="plw-actions2__lbl" :title="detailKandidat.tanggapan ? 'Kandidat sudah menjawab lewat portal' : ''">
                            <i class="bi bi-person-lines-fill"></i>
                            Dari kandidat
                            <em v-if="detailKandidat.tanggapan">· sudah menjawab</em>
                        </span>
                        <button
                            v-for="h in putusanKandidat" :key="h.kode"
                            type="button" class="plw-btn-kandidat"
                            :disabled="!!terkunciPutus(h)"
                            :title="terkunciPutus(h) || h.deskripsi"
                            :onClick="!!terkunciPutus(h) ? null : () => askPutus(detailKandidat, h.kode)"
                        >
                            <i class="bi" :class="h.ikon"></i>
                            {{ h.labelTombol }}
                        </button>
                        <!-- Mengembalikan keterangan yang tadi ditutup. Tidak pernah
                             ada jalan buntu: yang disembunyikan tetap bisa dipanggil
                             dari tempat keputusannya diambil. -->
                        <button
                            v-if="belumTuntas.length && notaTutup"
                            type="button" class="plw-actions2__info"
                            title="Tampilkan lagi keterangan kenapa sebagian tombol terkunci"
                            @click="tutupNota(false)"
                        >
                            <i class="bi bi-info-circle"></i>
                        </button>
                    </div>
                    <!-- Tanpa baris keputusan kandidat, lencana pemanggil keterangan
                         tetap perlu tempat. -->
                    <button
                        v-else-if="belumTuntas.length && notaTutup"
                        type="button" class="plw-actions2__info is-solo"
                        title="Tampilkan lagi keterangan kenapa sebagian tombol terkunci"
                        @click="tutupNota(false)"
                    >
                        <i class="bi bi-info-circle"></i> Kenapa sebagian tombol terkunci?
                    </button>
                </div>
                <!-- Kandidat yang tahapnya sudah diputus (lulus, gugur, mundur)
                     tidak punya satu pun tombol keputusan — jendelanya murni
                     arsip. Tanpa tombol tutup di kaki, satu-satunya jalan keluar
                     adalah tombol X di pojok, dan itu jauh dari tempat mata
                     berhenti membaca. -->
                <button v-else type="button" class="wca-btn wca-btn--ghost" @click="tutupKandidat">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
            </template>
        </AdminModal>

        <!-- ══ BIODATA LAYAR PENUH ═══════════════════════════════════════════
             Jendela detail di belakangnya SENGAJA dibiarkan terbuka: yang
             dicari orang di sini adalah membaca biodatanya lebih lega, bukan
             berpindah tempat. Menutupnya berarti kehilangan tab yang sedang
             dibuka, gulungan yang sudah dicapai, dan konteks keputusannya.

             Isinya bukan salinan — nodenya DIPINDAHKAN ke sini oleh Teleport
             di dalam tab Berkas & Biodata, lalu dikembalikan saat ditutup.
             Satu sumber, satu keadaan; tidak ada dua versi yang bisa
             berselisih. -->
        <!-- ALERT UJUNG ANTREAN.
             Di-teleport ke <body> dan diberi z-index di atas segalanya. Alasannya
             bukan selera: jendela detail membuat konteks tumpukan sendiri, jadi
             pemberitahuan yang digambar DI DALAMNYA tidak akan pernah bisa
             melampaui tepinya — ia akan terpotong, atau tenggelam di belakang
             latar modal. Pemberitahuan yang tak terlihat sama saja dengan tidak
             ada, padahal justru inilah yang menjelaskan kenapa tombolnya diam. -->
        <Teleport to="body">
            <transition name="plw-antre">
                <div v-if="antreanAlert" class="plw-antre" :class="'is-' + antreanAlert.nada" role="alert" aria-live="assertive">
                    <span class="plw-antre__ico"><i class="bi" :class="antreanAlert.ikon"></i></span>
                    <div class="plw-antre__t">
                        <b>{{ antreanAlert.judul }}</b>
                        <small>{{ antreanAlert.pesan }}</small>
                    </div>
                    <button type="button" class="plw-antre__x" aria-label="Tutup" @click="antreanAlert = null">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </transition>
        </Teleport>

        <Teleport to="body">
            <transition name="wca-modal">
                <div v-show="bioFull" class="plw-biofull" @click.self="bioFull = false">
                    <div class="plw-biofull__box" role="dialog" aria-modal="true" aria-label="Biodata layar penuh">
                        <div class="plw-biofull__head">
                            <span class="plw-biofull__ico"><i class="bi bi-person-vcard"></i></span>
                            <div class="plw-biofull__t">
                                <b>Biodata Kandidat</b>
                                <small>{{ detailKandidat?.pelamar || '' }}<template v-if="detailKandidat?.posisi"> · {{ detailKandidat.posisi }}</template></small>
                            </div>
                            <button type="button" class="plw-biofull__x" aria-label="Tutup" @click="bioFull = false">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <!-- Cap air. Duduk DI BELAKANG isi, bukan di atasnya —
                             lapisan setransparan apa pun yang menutupi teks tetap
                             menurunkan ketajamannya, dan biodata dibaca untuk
                             memutuskan nasib orang.

                             aria-hidden + pointer-events:none: ia hiasan, bukan
                             gambar yang perlu dibacakan pembaca layar maupun
                             disentuh. -->
                        <div class="plw-biofull__cap" aria-hidden="true">
                            <img src="/logo/EVOGROUP.png" alt="" draggable="false" />
                        </div>

                        <!-- Sasaran teleport. Ia yang menggulir, bukan halamannya. -->
                        <div id="plw-biofull-body" class="plw-biofull__body"></div>
                        <div class="plw-biofull__foot">
                            <span><i class="bi bi-info-circle"></i> Jendela detail tetap terbuka di belakang — tutup ini untuk kembali.</span>
                            <button type="button" class="wca-btn wca-btn--ghost" @click="bioFull = false">
                                <i class="bi bi-x-lg"></i> Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- ═══ LIGHTBOX BERKAS GAMBAR ═══ -->
        <div class="plw-lb" :class="{ 'is-on': !!lightbox }" @click="lightbox = null">
            <div v-if="lightbox" :class="['plw-lb__wrap', { 'is-pdf': lightbox.pdf }]" @click.stop>
                <div class="plw-lb__bar">
                    <div class="plw-lb__id">
                        <span class="plw-lb__ico">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="M21 15l-5-5L5 21" /></svg>
                        </span>
                        <div style="min-width: 0">
                            <div class="plw-lb__name">{{ lightbox.nama }}</div>
                            <div class="plw-lb__desc" :title="lightbox.olehAdmin ? lightbox.catatan || '' : ''">{{ lightbox.olehAdmin ? (lightbox.catatan || 'Diunggah admin atas nama kandidat') : lightbox.field === 'foto_verifikasi' ? 'Foto verifikasi identitas kandidat' : 'Pratinjau berkas kandidat' }}</div>
                        </div>
                    </div>
                    <button type="button" class="plw-lb__close" @click="lightbox = null">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="plw-lb__card">
                    <div v-if="lbLoading" class="plw-lb__state">
                        <span class="plw-spin plw-spin--lg"></span>
                        <span>Memuat berkas…</span>
                    </div>
                    <div v-else-if="lbError" class="plw-lb__state">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 8v5" /><path d="M12 16h.01" /></svg>
                        <span>Gagal memuat berkas.</span>
                        <button type="button" class="plw-lb__retry" @click="lbCoba">Coba lagi</button>
                    </div>
                    <!-- Satu modal melayani gambar dan PDF, supaya tidak ada dua
                         gaya pratinjau untuk hal yang sama. -->
                    <iframe
                        v-if="lightbox.pdf"
                        v-show="!lbLoading && !lbError"
                        :src="lbSrc"
                        :title="lightbox.nama"
                        class="plw-lb__pdf"
                        @load="selesaiMuat()"
                    ></iframe>
                    <img v-else v-show="!lbLoading && !lbError" :src="lbSrc" :alt="lightbox.nama" @load="selesaiMuat()" @error="selesaiMuat(true)" />
                    <div class="plw-lb__foot">
                        <div style="font-size: 12px; color: #8b93a7" class="plw-ell">{{ lightbox.nama }}</div>
                        <div v-if="lightbox.olehAdmin" class="plw-lb__adm" :title="lightbox.catatan || ''">
                            <i class="bi bi-person-badge-fill"></i> Diunggah admin<template v-if="lightbox.oleh"> · {{ lightbox.oleh }}</template>
                        </div>
                        <div v-if="lightbox.status === 'TERVERIFIKASI'" class="plw-lb__ok">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5" /></svg>
                            Terverifikasi
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ KIRIM ULANG EMAIL HASIL ═══
             Satu baris per tahap yang pernah diputus. Tombolnya dipegang
             AdminModal ber-`busy`: selama pengiriman ia berputar, terkunci, dan
             jendelanya tidak bisa ditutup — klik kedua tidak pernah melahirkan
             surat kedua. -->
        <AdminModal
            :show="emailShow"
            title="Kirim Ulang Email Hasil"
            :subtitle="detailKandidat ? detailKandidat.pelamar : ''"
            icon="bi-envelope-arrow-up-fill"
            size="md"
            :busy="emailSibuk"
            busy-label="Mengirim email…"
            save-label="Kirim Ulang"
            :save-disabled="!emailPilih || emailMuat"
            foot-note="Surat yang sama dikirim lagi — keputusan tahapnya tidak berubah."
            @close="emailShow = false"
            @save="kirimUlangEmail"
        >
            <div v-if="emailMuat" class="plw-eml__state"><span class="plw-spin"></span> Memuat riwayat keputusan…</div>
            <div v-else-if="!emailTahap.length" class="plw-eml__state">
                <i class="bi bi-inbox"></i> Belum ada tahap yang diputus — belum ada email hasil yang bisa dikirim ulang.
            </div>
            <template v-else>
                <label
                    v-for="t in emailTahap" :key="t.tahapId"
                    class="plw-eml__row"
                    :class="{ 'is-on': emailPilih === t.tahapId, 'is-mati': !t.kirimEmail }"
                >
                    <input v-model="emailPilih" type="radio" name="plw-eml" :value="t.tahapId" :disabled="!t.kirimEmail || emailSibuk">
                    <span class="plw-eml__no">{{ t.urutan }}</span>
                    <span class="plw-eml__in">
                        <b>{{ t.label }}</b>
                        <small v-if="t.kirimEmail">Diputus {{ tglId(t.diputusAt) }}<template v-if="t.diputusBy"> oleh {{ t.diputusBy }}</template></small>
                        <small v-else>"{{ t.hasilNama }}" memang tidak dikabarkan lewat email</small>
                    </span>
                    <span class="plw-eml__tag" :class="t.lolos ? 'is-lolos' : 'is-gugur'">{{ t.hasilNama }}</span>
                </label>
                <p class="plw-eml__note">
                    <i class="bi bi-envelope-at"></i>
                    <span>
                        Dikirim ke <b>{{ emailTujuan || detailKandidat?.email || '—' }}</b>. Catatan untuk kandidat pada
                        tahap itu (bila ada) ikut di dalam suratnya.
                    </span>
                </p>
            </template>
        </AdminModal>

        <!-- POPUP "EMAIL TERKIRIM" — jawaban yang jelas atas "sudah terkirim
             belum?", bukan toast yang lewat begitu saja di sudut layar. -->
        <AdminModal
            :show="!!emailTerkirim"
            title="Email terkirim"
            :subtitle="emailTerkirim ? emailTerkirim.pelamar : ''"
            icon="bi-send-check-fill"
            size="sm"
            foot-note=""
            @close="emailTerkirim = null"
        >
            <div v-if="emailTerkirim" class="plw-emlok">
                <span class="plw-emlok__ic"><i class="bi bi-check2-circle"></i></span>
                <p>
                    Email hasil tahap <b>{{ emailTerkirim.tahap }}</b> sudah dikirim ulang ke
                    <b>{{ emailTerkirim.email }}</b>.
                </p>
                <small>Diproses antrean pengiriman — biasanya sampai dalam 1–2 menit. Bila belum ada, minta kandidat memeriksa folder Spam.</small>
            </div>
            <template #footer>
                <button type="button" class="wca-btn wca-btn--dark" @click="emailTerkirim = null">
                    <i class="bi bi-check-lg"></i> Oke
                </button>
            </template>
        </AdminModal>

        <!-- ═══ JADWAL PENGISIAN — SATU KOLOM (program × tahap) ═══
             Belum berjadwal → pasang (waktu dibuka + batas akhir, keduanya wajib).
             Sudah → HANYA perpanjang: + hari, + jam, atau tanggal & jam pilihan.
             Keputusan user: sesudah dipasang, jadwal tidak disunting. Bila Master
             Alur sudah mematikan jadwal tahapnya → LEPAS batas waktu (bawaan). -->
        <AdminModal
            :show="batasKolomShow"
            :title="{ LEPAS: 'Lepas Batas Waktu', PERPANJANG: 'Perpanjang Jadwal', UBAH: 'Edit Jadwal' }[batasKolomLangkah] || 'Atur Jadwal Pengisian'"
            :subtitle="batasKolomCol ? batasKolomCol.label + (detail.program ? ' — ' + detail.program.nama : '') : ''"
            icon="bi-hourglass-split"
            size="sm"
            :busy="batasSibuk"
            busy-label="Menyimpan jadwal…"
            :foot-note="{
                LEPAS: 'Kandidat justru mendapat waktu tanpa batas.',
                PERPANJANG: 'Hanya maju — batas tidak pernah dimundurkan.',
                UBAH: 'Tercatat atas nama Anda beserta alasannya.',
            }[batasKolomLangkah] || 'Berlaku untuk seluruh kandidat program ini di tahap tersebut.'"
            @close="batasKolomShow = false"
        >
            <div v-if="batasKolomCol" class="plw-batasm">
                <!-- Pilihan langkah: Master Alur sudah "Tanpa jadwal" → lepas, atau
                     tetap urus jadwal lamanya; SUPERADMIN → juga bisa mengedit. -->
                <div v-if="kolomDilepas(batasKolomCol) || (superadmin && batasKolomCol.batasProgram)" class="plw-seg">
                    <button v-if="kolomDilepas(batasKolomCol)" type="button" class="plw-seg__b is-net" :class="{ 'is-on': batasKolomLangkah === 'LEPAS' }" @click="batasKolomLangkah = 'LEPAS'">
                        <i class="bi bi-unlock"></i> Lepas batas waktu
                    </button>
                    <button
                        type="button" class="plw-seg__b is-net"
                        :class="{ 'is-on': ['PERPANJANG', 'ATUR'].includes(batasKolomLangkah) }"
                        @click="batasKolomLangkah = batasKolomCol.batasProgram ? 'PERPANJANG' : 'ATUR'"
                    >
                        <template v-if="batasKolomCol.batasProgram"><i class="bi bi-hourglass-top"></i> Perpanjang</template>
                        <template v-else><i class="bi bi-calendar-plus"></i> Atur jadwal lama</template>
                    </button>
                    <button
                        v-if="superadmin && batasKolomCol.batasProgram"
                        type="button" class="plw-seg__b is-net"
                        :class="{ 'is-on': batasKolomLangkah === 'UBAH' }"
                        @click="batasKolomLangkah = 'UBAH'"
                    >
                        <i class="bi bi-pencil-square"></i> Edit · superadmin
                    </button>
                </div>

                <template v-if="batasKolomLangkah === 'LEPAS'">
                    <p class="plw-note is-info">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>
                            Di Master Alur, tahap ini sudah <b>Tanpa jadwal</b>. Kandidat yang masuk sesudahnya tidak berjadwal,
                            tapi yang sudah di sini masih memegang aturan lamanya (snapshot).
                        </span>
                    </p>
                    <ul class="plw-batasm__info">
                        <li><b>{{ ringkasBatas(batasKolomCol).belum }}</b> kandidat yang belum mengirim bisa mengisi formulir <b>tanpa batas waktu</b> — termasuk yang sedang terkunci.</li>
                        <li v-if="batasKolomCol.batasProgram">Jadwal kolom ({{ tglJamId(batasKolomCol.bukaProgram) }} – {{ tglJamId(batasKolomCol.batasProgram) }}) dihapus.</li>
                        <li>Kandidat yang sudah mengirim tidak terpengaruh. Setiap pelepasan tercatat di riwayat kandidat.</li>
                    </ul>
                </template>
                <!-- EDIT — khusus superadmin, untuk kasus salah klik. Boleh mundur;
                     alasan wajib; tercatat di riwayat kolom & kandidat. -->
                <template v-else-if="batasKolomLangkah === 'UBAH'">
                    <p class="plw-note is-lock">
                        <i class="bi bi-shield-lock-fill"></i>
                        <span>
                            <b>Khusus superadmin</b> — untuk memperbaiki salah input. Waktu dibuka & batas akhir boleh
                            dimajukan maupun dimundurkan. Kandidat yang mengikuti jadwal kolom ikut berubah; yang berjadwal
                            pribadi tidak.
                        </span>
                    </p>
                    <div class="plw-batasm__dua">
                        <div>
                            <label class="plw-fld__lbl">Formulir dibuka <b>*</b></label>
                            <el-date-picker
                                v-model="batasKolomBuka" type="datetime"
                                format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss"
                                placeholder="Kapan mulai bisa diisi" style="width: 100%"
                            />
                        </div>
                        <div>
                            <label class="plw-fld__lbl">Batas akhir <b>*</b></label>
                            <el-date-picker
                                v-model="batasKolomTgl" type="datetime"
                                format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss"
                                placeholder="Paling lambat dikirim" style="width: 100%"
                                :default-time="JAM_BATAS"
                            />
                        </div>
                    </div>
                    <p class="plw-fld__hint">
                        Sebelumnya: dibuka {{ tglJamId(batasKolomCol.bukaProgram) }} · batas {{ tglJamId(batasKolomCol.batasProgram) }}
                    </p>
                    <p v-if="batasKolomTgl && batasKolomBuka && batasKolomTgl <= batasKolomBuka" class="plw-note is-err">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Batas akhir harus sesudah waktu formulir dibuka.</span>
                    </p>
                    <p v-else-if="kolomUbahLampau" class="plw-note is-err">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Batas akhir ini sudah lewat — kandidat yang mengikuti jadwal kolom akan <b>langsung terkunci</b>.</span>
                    </p>
                    <label class="plw-fld__lbl">Alasan pengeditan <b>*</b></label>
                    <el-input
                        v-model="batasKolomAlasan" type="textarea" :rows="2" maxlength="300" show-word-limit
                        placeholder="mis. salah pilih tanggal saat memasang jadwal"
                    />
                </template>
                <template v-else-if="batasKolomLangkah === 'PERPANJANG'">
                    <div class="plw-batasm__kini">
                        <i class="bi bi-calendar-check"></i>
                        <div>
                            <small>Jadwal kolom sekarang</small>
                            <b>Dibuka {{ tglJamId(batasKolomCol.bukaProgram) }} · Batas {{ tglJamId(batasKolomCol.batasProgram) }}</b>
                        </div>
                    </div>
                    <PerpanjangBatas v-model="perp" :batas-kini="batasKolomCol.batasProgram" />
                    <ul class="plw-batasm__info">
                        <li><b>{{ ringkasBatas(batasKolomCol).belum }}</b> kandidat belum mengirim. Yang batasnya lebih awal ikut maju — termasuk yang pernah diperpanjang pribadi.</li>
                        <li>Waktu dibuka tidak berubah. Jadwal tidak bisa disunting atau dimundurkan, hanya diperpanjang lagi bila perlu.</li>
                    </ul>
                </template>
                <template v-else>
                    <!-- DUA WAKTU, KEDUANYA WAJIB (keputusan user): formulir
                         terkunci sebelum dibuka dan sesudah batasnya. Hari yang
                         sudah lewat tidak bisa dipilih. -->
                    <div class="plw-batasm__dua">
                        <div>
                            <label class="plw-fld__lbl">Formulir dibuka <b>*</b></label>
                            <el-date-picker
                                v-model="batasKolomBuka" type="datetime"
                                format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss"
                                placeholder="Kapan mulai bisa diisi" style="width: 100%"
                                :disabled-date="sebelumHariIni"
                            />
                        </div>
                        <div>
                            <label class="plw-fld__lbl">Batas akhir <b>*</b></label>
                            <el-date-picker
                                v-model="batasKolomTgl" type="datetime"
                                format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss"
                                placeholder="Paling lambat dikirim" style="width: 100%"
                                :default-time="JAM_BATAS"
                                :disabled-date="sebelumHariIni"
                            />
                        </div>
                    </div>
                    <p v-if="salahKolomBaru" class="plw-note is-err">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>{{ salahKolomBaru }}</span>
                    </p>
                    <ul class="plw-batasm__info">
                        <li v-if="kolomDilepas(batasKolomCol)">
                            Hanya kandidat yang masih terikat jadwal lama yang ikut jadwal ini — kandidat baru tetap tanpa jadwal,
                            sesuai Master Alur.
                        </li>
                        <li v-else>
                            Semua kandidat di kolom ini yang belum mengirim formulir ikut jadwal ini<template v-if="ringkasBatas(batasKolomCol).tutup">
                            — <b>{{ ringkasBatas(batasKolomCol).tutup }}</b> di antaranya sedang terkunci menunggu</template>.
                        </li>
                        <li>Sebelum waktu dibuka dan sesudah batas akhir, formulir <b>terkunci</b>; admin yang memutuskan — tidak ada gugur otomatis.</li>
                        <li>Yang masuk tahap ini belakangan ikut otomatis (paling cepat 2 hari sejak formulirnya terbuka).</li>
                    </ul>
                    <p class="plw-note is-lock">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>Periksa dulu sebelum menyimpan: sesudah disimpan, jadwal ini <b>tidak bisa diubah</b> — hanya bisa diperpanjang.</span>
                    </p>
                </template>

                <!-- RIWAYAT JADWAL KOLOM — siapa memasang, memperpanjang, mengedit,
                     atau melepas, kapan, dari berapa ke berapa, dan alasannya. -->
                <button type="button" class="plw-batasp__rw" @click="toggleRiwayatKolom">
                    <i class="bi" :class="riwayatKolom.buka ? 'bi-chevron-up' : 'bi-clock-history'"></i>
                    {{ riwayatKolom.buka ? 'Tutup riwayat jadwal kolom' : 'Riwayat jadwal kolom' }}
                </button>
                <ul v-if="riwayatKolom.buka" class="plw-batasp__list plw-batasm__riwayat">
                    <li v-if="riwayatKolom.muat" class="is-kosong"><span class="plw-spin"></span> Memuat…</li>
                    <li v-else-if="!riwayatKolom.baris.length" class="is-kosong">Belum ada perubahan tercatat.</li>
                    <li v-for="(h, i) in riwayatKolom.baris" :key="i">
                        <b>
                            <span class="plw-rwk" :class="`is-${String(h.aksi).toLowerCase()}`">{{ labelAksiKolom(h.aksi) }}</span>
                            <template v-if="h.aksi === 'LEPAS'">tanpa batas waktu</template>
                            <template v-else>
                                <template v-if="h.batasLama && h.batasLama !== h.batasBaru">{{ h.batasLama }} → </template>{{ h.batasBaru }}
                            </template>
                        </b>
                        <small v-if="h.aksi === 'UBAH' && h.bukaLama !== h.bukaBaru">Waktu dibuka: {{ h.bukaLama || '—' }} → {{ h.bukaBaru || '—' }}</small>
                        <small>
                            {{ h.oleh || 'SISTEM' }}<template v-if="h.peran"> ({{ h.peran }})</template> · {{ tglJamId(h.at) }}
                            <template v-if="h.jumlah !== null"> · {{ h.jumlah }} kandidat</template>
                        </small>
                        <small v-if="h.alasan" class="plw-rwk__alasan">“{{ h.alasan }}”</small>
                    </li>
                </ul>
            </div>
            <template #footer>
                <button type="button" class="wca-btn wca-btn--ghost" :disabled="batasSibuk" @click="batasKolomShow = false">
                    <i class="bi bi-x-lg"></i> Batal
                </button>
                <button type="button" class="wca-btn wca-btn--dark" :disabled="!bolehSimpanBatasKolom" @click="simpanBatasKolom">
                    <span v-if="batasSibuk" class="wca-spin" aria-hidden="true"></span>
                    <i v-else class="bi" :class="{ LEPAS: 'bi-unlock', PERPANJANG: 'bi-hourglass-top', UBAH: 'bi-pencil-square' }[batasKolomLangkah] || 'bi-check2'"></i>
                    {{ batasSibuk ? 'Menyimpan…' : ({ LEPAS: 'Lepas Batas Waktu', PERPANJANG: 'Perpanjang', UBAH: 'Simpan Editan' }[batasKolomLangkah] || 'Simpan Jadwal') }}
                </button>
            </template>
        </AdminModal>

        <!-- ═══ JADWAL PENGISIAN — KANDIDAT TERPILIH (satuan & massal) ═══
             Satu tindakan per simpan: yang BELUM berjadwal hanya bisa diatur,
             yang SUDAH hanya bisa diperpanjang, dan yang tahapnya di Master Alur
             sudah "Tanpa jadwal" bisa dilepas. Pilihan kelompok muncul hanya bila
             ada lebih dari satu kelompok. -->
        <AdminModal
            :show="batasKandShow"
            :title="{ ATUR: 'Atur Jadwal Pengisian', LEPAS: 'Lepas Batas Waktu', UBAH: 'Edit Jadwal' }[batasKandLangkah] || 'Perpanjang Jadwal'"
            :subtitle="batasKandTarget.length === 1 ? batasKandTarget[0].pelamar : `${batasKandTarget.length} kandidat terpilih`"
            icon="bi-hourglass-split"
            size="sm"
            :busy="batasSibuk"
            busy-label="Menyimpan jadwal…"
            :save-label="{ ATUR: 'Simpan Jadwal', LEPAS: 'Lepas Batas Waktu', UBAH: 'Simpan Editan' }[batasKandLangkah] || 'Perpanjang'"
            :save-disabled="!bolehSimpanBatasKand"
            foot-note="Setiap perubahan tercatat di riwayat kandidat."
            @save="simpanBatasKandidat"
            @close="batasKandShow = false"
        >
            <div class="plw-batasm">
                <div v-if="[batasKandBelum, batasKandSudah, batasKandLepas, batasKandUbah].filter((g) => g.length).length > 1" class="plw-seg">
                    <button v-if="batasKandLepas.length" type="button" class="plw-seg__b is-net" :class="{ 'is-on': batasKandLangkah === 'LEPAS' }" @click="batasKandLangkah = 'LEPAS'">
                        <i class="bi bi-unlock"></i> Lepas · {{ batasKandLepas.length }}
                    </button>
                    <button v-if="batasKandBelum.length" type="button" class="plw-seg__b is-net" :class="{ 'is-on': batasKandLangkah === 'ATUR' }" @click="batasKandLangkah = 'ATUR'">
                        <i class="bi bi-calendar-plus"></i> Atur jadwal · {{ batasKandBelum.length }}
                    </button>
                    <button v-if="batasKandSudah.length" type="button" class="plw-seg__b is-net" :class="{ 'is-on': batasKandLangkah === 'PERPANJANG' }" @click="batasKandLangkah = 'PERPANJANG'">
                        <i class="bi bi-hourglass-top"></i> Perpanjang · {{ batasKandSudah.length }}
                    </button>
                    <button v-if="batasKandUbah.length" type="button" class="plw-seg__b is-net" :class="{ 'is-on': batasKandLangkah === 'UBAH' }" @click="batasKandLangkah = 'UBAH'">
                        <i class="bi bi-pencil-square"></i> Edit · {{ batasKandUbah.length }}
                    </button>
                </div>

                <!-- EDIT — khusus superadmin: tetapkan apa adanya, boleh mundur. -->
                <template v-if="batasKandLangkah === 'UBAH'">
                    <p class="plw-note is-lock">
                        <i class="bi bi-shield-lock-fill"></i>
                        <span>
                            <b>Khusus superadmin</b> — untuk memperbaiki salah input. Jadwal {{ batasKandUbah.length }} kandidat
                            ditetapkan apa adanya (boleh mundur) dan menjadi jadwal pribadi.
                        </span>
                    </p>
                    <div class="plw-batasm__dua">
                        <div>
                            <label class="plw-fld__lbl">Formulir dibuka <small>opsional</small></label>
                            <el-date-picker
                                v-model="batasKandBuka" type="datetime"
                                format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss"
                                placeholder="Kosong = sekarang" style="width: 100%"
                            />
                        </div>
                        <div>
                            <label class="plw-fld__lbl">Batas akhir <b>*</b></label>
                            <el-date-picker
                                v-model="batasKandTgl" type="datetime"
                                format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss"
                                placeholder="Paling lambat dikirim" style="width: 100%"
                                :default-time="JAM_BATAS"
                            />
                        </div>
                    </div>
                    <p v-if="batasKandTgl && batasKandBuka && batasKandTgl <= batasKandBuka" class="plw-note is-err">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Batas akhir harus sesudah waktu formulir dibuka.</span>
                    </p>
                    <p v-else-if="kandUbahLampau" class="plw-note is-err">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Batas akhir ini sudah lewat — formulirnya akan <b>langsung terkunci</b>.</span>
                    </p>
                </template>
                <template v-else-if="batasKandLangkah === 'LEPAS'">
                    <p class="plw-note is-info">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>
                            Di Master Alur, tahap {{ batasKandLepas.length === 1 ? 'kandidat ini' : 'mereka' }} sudah <b>Tanpa jadwal</b>.
                            <b>{{ batasKandLepas.length }} kandidat</b> bisa mengisi formulir tanpa batas waktu — termasuk yang sedang terkunci.
                        </span>
                    </p>
                </template>
                <template v-else-if="batasKandLangkah === 'ATUR'">
                    <p v-if="batasKandTarget.length > 1" class="plw-fld__hint">
                        Untuk <b>{{ batasKandBelum.length }} kandidat</b> yang belum berjadwal.
                    </p>
                    <div class="plw-batasm__dua">
                        <div>
                            <label class="plw-fld__lbl">Formulir dibuka <small>opsional</small></label>
                            <el-date-picker
                                v-model="batasKandBuka" type="datetime"
                                format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss"
                                placeholder="Kosong = sekarang" style="width: 100%"
                                :disabled-date="sebelumHariIni"
                            />
                        </div>
                        <div>
                            <label class="plw-fld__lbl">Batas akhir <b>*</b></label>
                            <el-date-picker
                                v-model="batasKandTgl" type="datetime"
                                format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss"
                                placeholder="Paling lambat dikirim" style="width: 100%"
                                :default-time="JAM_BATAS"
                                :disabled-date="sebelumHariIni"
                            />
                        </div>
                    </div>
                    <p v-if="salahKandBaru" class="plw-note is-err">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>{{ salahKandBaru }}</span>
                    </p>
                    <p class="plw-note is-lock">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>Menjadi jadwal pribadi. Sesudah disimpan, jadwal ini <b>tidak bisa diubah</b> — hanya bisa diperpanjang.</span>
                    </p>
                </template>
                <template v-else>
                    <p v-if="batasKandTarget.length > 1" class="plw-fld__hint">
                        Untuk <b>{{ batasKandSudah.length }} kandidat</b> yang sudah berjadwal.
                    </p>
                    <div v-if="batasKandSudah.length === 1" class="plw-batasm__kini">
                        <i class="bi bi-calendar-check"></i>
                        <div>
                            <small>{{ labelSumberBatas(batasKandSudah[0].batas.sumber) }}</small>
                            <b>
                                <template v-if="batasKandSudah[0].batas.buka">Dibuka {{ tglJamId(batasKandSudah[0].batas.buka) }} · </template>Batas {{ tglJamId(batasKandSudah[0].batas.batas) }}
                            </b>
                        </div>
                    </div>
                    <PerpanjangBatas v-model="perp" :batas-kini="batasKandLama" />
                </template>

                <label class="plw-fld__lbl">
                    Alasan <b v-if="batasKandLangkah === 'UBAH'">*</b><small v-else>opsional</small>
                </label>
                <el-input
                    v-model="batasKandAlasan" type="textarea" :rows="2" maxlength="300" show-word-limit
                    :placeholder="batasKandLangkah === 'UBAH' ? 'mis. salah pilih tanggal saat memasang jadwal' : 'mis. kandidat meminta waktu untuk mengurus SKCK'"
                />
                <p v-if="batasKandTarget.length > 1" class="plw-fld__hint">
                    Hanya kandidat yang sedang mengisi formulir tahap yang diubah; sisanya dilewati dan dilaporkan.
                </p>
            </div>
        </AdminModal>

        <!-- ═══ DETAIL SATU TAHAP (dari Progres Seleksi / Hasil tahap sebelumnya) ═══
             Baca-saja: keputusan & catatannya, rapor aktivitas, berkas hasil, dan
             formulir yang diisi di tahap itu — semua berkas bisa dibuka lagi. -->
        <AdminModal
            :show="tahapRiwayat.show"
            :title="tahapRiwayat.data ? `${String(tahapRiwayat.data.tahap.urutan).padStart(2, '0')}. ${tahapRiwayat.data.tahap.label}` : 'Detail Tahap'"
            :subtitle="detailKandidat ? `${detailKandidat.pelamar} · riwayat tahap` : ''"
            icon="bi-signpost-split"
            size="lg"
            foot-note="Baca-saja — keputusan diambil di tahap aktif."
            @close="tahapRiwayat.show = false"
        >
            <div v-if="tahapRiwayat.muat" class="plw-trw__muat"><span class="plw-spin"></span> Memuat isi tahap…</div>
            <div v-else-if="tahapRiwayat.data" class="plw-trw">
                <!-- KEPUTUSAN -->
                <div class="plw-trw__put">
                    <span class="plw-test__pill" :class="kelasHasilTahap(tahapRiwayat.data.tahap)">{{ labelHasilTahap(tahapRiwayat.data.tahap) }}</span>
                    <span v-if="tahapRiwayat.data.tahap.skor !== null" class="plw-trw__skor">Skor {{ tahapRiwayat.data.tahap.skor }}</span>
                    <span class="plw-trw__meta">
                        <template v-if="tahapRiwayat.data.tahap.diputusAt">
                            Diputus {{ tahapRiwayat.data.tahap.diputusBy || '—' }} · {{ tglJamId(tahapRiwayat.data.tahap.diputusAt) }}
                        </template>
                        <template v-else-if="tahapRiwayat.data.tahap.waktuMulai">Dimulai {{ tglJamId(tahapRiwayat.data.tahap.waktuMulai) }}</template>
                    </span>
                </div>
                <p v-if="tahapRiwayat.data.tahap.alasan" class="plw-trw__alasan"><b>Alasan:</b> {{ tahapRiwayat.data.tahap.alasan }}</p>
                <div v-if="tahapRiwayat.data.tahap.catatanHtml || tahapRiwayat.data.tahap.catatan" class="plw-trw__cat">
                    <small><i class="bi bi-lock-fill"></i> Catatan keputusan (internal)</small>
                    <KontenAman v-if="tahapRiwayat.data.tahap.catatanHtml" :html="tahapRiwayat.data.tahap.catatanHtml" ringkas />
                    <p v-else>{{ tahapRiwayat.data.tahap.catatan }}</p>
                </div>
                <div v-if="tahapRiwayat.data.tahap.catatanEksternal" class="plw-trw__cat is-eks">
                    <small><i class="bi bi-megaphone-fill"></i> Catatan untuk kandidat</small>
                    <KontenAman :html="tahapRiwayat.data.tahap.catatanEksternal" ringkas />
                </div>

                <!-- RAPOR AKTIVITAS -->
                <div class="plw-trw__sek">Hasil aktivitas <em>{{ tahapRiwayat.data.tests.length }}</em></div>
                <p v-if="!tahapRiwayat.data.tests.length" class="plw-trw__kosong">Tidak ada aktivitas tercatat.</p>
                <div v-for="t in tahapRiwayat.data.tests" :key="t.id" class="plw-trw__tes">
                    <div class="plw-trw__teshead">
                        <div class="plw-trw__tesnama">
                            <b>{{ t.label }}</b>
                            <small>{{ t.tipeNama || t.tipe }}<template v-if="t.jadwal"> · {{ jadwalRingkas(t.jadwal) }}</template></small>
                        </div>
                        <span v-if="t.skorBermakna && t.nilai !== null && t.nilai !== undefined" class="plw-test__score">{{ t.nilai }}</span>
                        <span class="plw-test__pill" :class="pillTes(t)">{{ labelTes(t) }}</span>
                    </div>
                    <div v-if="t.catatanHtml || t.catatan" class="plw-trw__tescat">
                        <KontenAman v-if="t.catatanHtml" :html="t.catatanHtml" ringkas />
                        <p v-else>{{ t.catatan }}</p>
                    </div>
                    <div v-if="(t.berkas || []).length || (t.berkasKandidat || []).length" class="plw-hts__berkas">
                        <button v-for="b in t.berkas || []" :key="'t' + b.id" type="button" class="plw-hts__file" :title="b.nama" @click="bukaDok(b)">
                            <i class="bi" :class="b.isImage ? 'bi-image' : 'bi-file-earmark-text'"></i><span>{{ b.nama }}</span><em>tim</em>
                        </button>
                        <button v-for="b in t.berkasKandidat || []" :key="'k' + b.id" type="button" class="plw-hts__file" :title="b.nama" @click="bukaDok(b)">
                            <i class="bi" :class="b.isImage ? 'bi-image' : 'bi-file-earmark-text'"></i><span>{{ b.nama }}</span><em>kandidat</em>
                        </button>
                    </div>
                </div>

                <!-- BERKAS HASIL TAHAP (bukan milik satu aktivitas) -->
                <template v-if="tahapRiwayat.data.berkas.length">
                    <div class="plw-trw__sek">Berkas hasil tahap <em>{{ tahapRiwayat.data.berkas.length }}</em></div>
                    <div class="plw-hts__berkas">
                        <button v-for="b in tahapRiwayat.data.berkas" :key="b.id" type="button" class="plw-hts__file" :title="b.nama" @click="bukaDok(b)">
                            <i class="bi" :class="b.isImage ? 'bi-image' : 'bi-file-earmark-text'"></i><span>{{ b.nama }}</span>
                        </button>
                    </div>
                </template>

                <!-- FORMULIR YANG DIISI DI TAHAP INI -->
                <template v-for="f in formulirTahapRiwayat" :key="f.no">
                    <div class="plw-trw__sek">
                        Formulir · {{ f.label }}
                        <em v-if="f.waktuKirim">dikirim {{ tglJamId(f.waktuKirim) }}</em>
                    </div>
                    <div v-if="berkasFormulir(f).length" class="plw-hts__berkas">
                        <button v-for="b in berkasFormulir(f)" :key="b.id" type="button" class="plw-hts__file" :title="b.konteks" @click="bukaDok(b.berkas)">
                            <i class="bi" :class="b.isImage ? 'bi-image' : 'bi-file-earmark-text'"></i><span>{{ b.nama }}</span>
                        </button>
                    </div>
                    <button type="button" class="plw-trw__link" @click="lihatFormulirTahap(f)">
                        <i class="bi bi-card-list"></i> Lihat jawaban lengkap di Berkas &amp; Biodata
                    </button>
                </template>
            </div>
            <template #footer>
                <button type="button" class="wca-btn wca-btn--ghost" :disabled="!tahapRiwayatGeser(-1)" @click="bukaTahapRiwayat(tahapRiwayatGeser(-1))">
                    <i class="bi bi-chevron-left"></i> Tahap sebelumnya
                </button>
                <button type="button" class="wca-btn wca-btn--ghost" @click="tahapRiwayat.show = false">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
                <button type="button" class="wca-btn wca-btn--ghost" :disabled="!tahapRiwayatGeser(1)" @click="bukaTahapRiwayat(tahapRiwayatGeser(1))">
                    Tahap berikutnya <i class="bi bi-chevron-right"></i>
                </button>
            </template>
        </AdminModal>

        <ConfirmModal
            :show="konfirmShow"
            :title="putusJudul"
            :subtitle="putusTarget ? `${putusTarget.pelamar} — tahap ${putusTarget.tahap}` : ''"
            :danger="!!putusDef && !putusDef.lolos"
            :confirm-label="putusLabelKonfirm"
            :busy="sibuk"
            :confirm-disabled="!bolehKonfirmPutus"
            form-mode
            @confirm="konfirmPutus"
            @cancel="konfirmShow = false"
        >
            <!-- Ringkas apa yang akan terjadi. Keputusan ini mengirim email ke
                 kandidat dan tidak bisa ditarik kembali, jadi disebutkan di muka
                 alih-alih membiarkan admin menebak. -->
            <div class="plw-putus__ring" :class="nadaPutus">
                <div v-if="putusTarget" class="plw-putus__row">
                    <i class="bi bi-person-badge"></i>
                    <span><b>{{ putusTarget.pelamar }}</b> · {{ putusTarget.posisi || '—' }}<template v-if="putusTarget.departemen"> · {{ putusTarget.departemen }}</template></span>
                </div>
                <!-- Akibat keputusan DIBACA DARI MASTER, bukan tiga kalimat yang
                     ditulis mati. Menambah hasil baru di master berarti tombolnya
                     langsung muncul BESERTA keterangan akibatnya. -->
                <div v-if="putusDef" class="plw-putus__row">
                    <i class="bi" :class="putusDef.ikon"></i>
                    <span>{{ putusDef.deskripsi }}</span>
                </div>
                <!-- Talent Pool yang MELEKAT pada arti hasilnya (mis. tombol
                     "Talent Pool" itu sendiri) hanya diberitakan. Yang bisa
                     dipilih tampil sebagai pertanyaan tersendiri di bawah —
                     menyatakannya di sini sebagai fakta akan berbohong. -->
                <div v-if="putusDef && putusDef.talentPool && !putusDef.pilihTalentPool" class="plw-putus__row">
                    <i class="bi bi-stars"></i>
                    <span>Datanya <b>disimpan di Talent Pool</b> untuk kesempatan berikutnya.</span>
                </div>
                <div v-if="putusTarget && putusTarget.email" class="plw-putus__row is-mail">
                    <i class="bi bi-envelope"></i>
                    <span>{{ putusDef && putusDef.kirimEmail ? putusTarget.email : 'Tidak dikirimi email' }}</span>
                </div>
            </div>

            <!-- Jawaban yang SUDAH diberikan kandidat lewat portal. Admin yang
                 mencatatkan keputusan dari kandidat perlu melihatnya di sini —
                 kalau tidak, ia mencatat ulang sesuatu yang sudah tercatat. -->
            <div v-if="putusTarget && putusTarget.tanggapan" class="plw-note is-info">
                <i class="bi bi-chat-left-quote-fill"></i>
                <span>
                    Kandidat sudah menjawab lewat portal:
                    <b>{{ putusTarget.tanggapan.jawab === 'TERIMA' ? 'menerima' : 'mundur' }}</b>
                    <template v-if="putusTarget.tanggapan.catatan"> — “{{ putusTarget.tanggapan.catatan }}”</template>
                </span>
            </div>

            <!-- HASIL MCU TIDAK LAGI DI SINI.
                 Status kesehatan dicatat saat KEHADIRAN ditetapkan — satu
                 peristiwa: kandidat datang ke klinik, diperiksa, inilah
                 hasilnya. Menaruhnya di jendela ini berarti petugas yang
                 menerima hasil dari klinik tidak punya tempat mencatatnya, dan
                 orang yang menekan "Loloskan" disodori formulir medis yang
                 bukan urusannya. Server pun tak lagi menerimanya di sini.
                 Lihat modal "Tandai Hadir". -->

            <!-- KANDIDAT MUNDUR — DISIMPAN ATAU TIDAK?
                 Dua pilihan, bukan satu nasib yang dipaksakan. Sebab mundurnya
                 bermacam-macam: yang pergi karena dapat tempat lebih dekat rumah
                 memang layak disimpan, yang menghilang tanpa membalas undangan
                 tidak. Dulu keduanya pasti masuk Talent Pool, sehingga admin yang
                 tidak ingin menyimpan terpaksa mencatatnya "Tidak Lolos" —
                 memperbaiki isi Talent Pool dengan merusak arti datanya, karena
                 di laporan lalu terbaca PERUSAHAAN yang menolak orang yang
                 sebenarnya pergi sendiri.

                 Apa pun yang dipilih, HASILNYA TETAP SATU: "Mengundurkan Diri".
                 Pilihan ini tidak mengubah status kandidat, hanya menentukan
                 datanya disimpan untuk kesempatan berikutnya atau tidak. -->
            <!-- Tahap SEBELUM cut-off: tidak ada pilihan sama sekali. Kandidat
                 dicatat mundur, titik. Menawarkan Talent Pool di tahap awal —
                 sebelum ada satu pun penilaian — hanya mengisinya dengan orang
                 yang belum pernah dinilai siapa pun. -->
            <p v-if="putusDef && putusDef.pilihTalentPool && !putusTarget?.bolehTalentPool" class="plw-note is-info">
                <i class="bi bi-info-circle-fill"></i>
                <span>
                    Tahap ini <b>belum masuk cut-off Talent Pool</b> pada alurnya, jadi kandidat
                    tidak disimpan — cukup dicatat <b>{{ putusDef.nama }}</b>.
                </span>
            </p>

            <div v-if="putusDef && putusDef.pilihTalentPool && putusTarget?.bolehTalentPool" class="plw-tp">
                <div class="plw-tp__head">
                    <i class="bi bi-signpost-split-fill"></i>
                    <span>{{ putusDef.labelTalentPool || 'Simpan kandidat ini di Talent Pool' }}</span>
                </div>
                <div class="plw-tp__opts">
                    <button
                        type="button" class="plw-tp__opt is-simpan" :class="{ 'is-on': putusTalentPool }"
                        @click="putusTalentPool = true"
                    >
                        <span class="plw-tp__ico"><i class="bi bi-stars"></i></span>
                        <span class="plw-tp__txt">
                            <b>Ya, simpan di Talent Pool</b>
                            <small>Kualitasnya sudah terbukti sampai tahap ini — datanya siap ditarik untuk lowongan lain.</small>
                        </span>
                    </button>
                    <button
                        type="button" class="plw-tp__opt is-lepas" :class="{ 'is-on': !putusTalentPool }"
                        @click="putusTalentPool = false"
                    >
                        <span class="plw-tp__ico"><i class="bi bi-box-arrow-left"></i></span>
                        <span class="plw-tp__txt">
                            <b>Tidak, cukup catat pengunduran dirinya</b>
                            <small>Lamaran ditutup sebagai <b>{{ putusDef.nama }}</b> — <b>bukan</b> tidak lolos — tanpa masuk Talent Pool.</small>
                        </span>
                    </button>
                </div>
            </div>

            <!-- TANGGAL KANDIDAT MENYATAKAN MUNDUR/MENOLAK.
                 Hanya muncul untuk keputusan yang datang dari kandidat. Kabar
                 mundur biasanya lewat telepon lebih dulu dan baru dicatat
                 beberapa hari kemudian; tanpa tanggal ini kursi tampak baru
                 kosong hari pencatatan, padahal sudah kosong sejak awal. -->
            <div v-if="putusDef && putusDef.olehKandidat" class="plw-fld">
                <label class="plw-fld__lbl" for="putus-tgl">
                    Tanggal Konfirmasi Pengunduran Diri
                    <small>kapan kandidat menyatakannya</small>
                </label>
                <input
                    id="putus-tgl"
                    v-model="putusTanggal"
                    type="date"
                    class="plw-inp"
                    :max="hariIni"
                />
            </div>

            <!-- Wajib atau tidaknya alasan DARI MASTER (Butuh_Alasan): keputusan
                 yang menutup proses menuntut jejak kenapa, dan keputusan dari
                 kandidat menuntutnya juga — sebab mundurnya adalah satu-satunya
                 umpan balik kenapa penawaran kita kalah. -->
            <div class="plw-fld">
                <label class="plw-fld__lbl">
                    Catatan Keputusan
                    <small v-if="alasanWajib">{{ labelCatatanPutus }} wajib di tab internal</small>
                    <small v-else>opsional</small>
                </label>
                <!-- DUA CATATAN: untuk kandidat (eksternal) & untuk tim (internal).
                     Editor berformat, bukan kotak sebaris — alasan keputusan yang
                     menutup lamaran orang layak ditulis selengkap catatan
                     wawancara, dan yang internal kerap perlu memuat bukti
                     (tangkapan layar percakapan kandidat yang menyatakan mundur). -->
                <CatatanKeputusan
                    v-model:tab="putusTabCatatan"
                    v-model:eksternal="putusCatatanEksternalHtml"
                    v-model:internal="putusCatatanHtml"
                    :eksternal-siap="!!catatanTahap.eksternalSiap"
                    :label-internal="alasanWajib ? labelCatatanPutus : 'Catatan Internal'"
                    :wajib-internal="alasanWajib"
                    :placeholder-internal="placeholderCatatanPutus"
                    :upload-url="URL_GAMBAR"
                    :upload-data="{ tahapId: putusTarget?.tahapId }"
                />
            </div>
            <p v-if="alasanWajib && !catatanCukup" class="plw-note is-err">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>{{ labelCatatanPutus }} wajib diisi, minimal {{ MIN_ALASAN }} karakter (sekarang {{ panjangAlasanPutus }}).</span>
            </p>

            <!-- AKTIVITAS TAHAP INI BELUM SELESAI SEMUA.
                 Aktivitas yang dikerjakan tim (wawancara, FGD, DISC) tidak
                 mengunci tombol keputusan — mengunci akan membuat tahap yang
                 aktivitasnya sengaja dilewati jadi buntu. Tapi memutus tanpa
                 sadar bahwa tiga dari empat aktivitas belum dikerjakan adalah
                 kekeliruan yang tak bisa ditarik kembali.
                 Karena itu bukan dikunci, melainkan DIMINTA DIAKUI dulu. -->
            <div v-if="perluCentangAktivitas" class="plw-belum">
                <div class="plw-belum__head">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>
                        <b>{{ aktivitasBelumSelesai.length }} dari {{ (putusTarget?.tests || []).length }} aktivitas</b>
                        di tahap ini belum selesai
                    </span>
                </div>
                <ul class="plw-belum__list">
                    <li v-for="a in aktivitasBelumSelesai" :key="a.id">
                        {{ a.label }}
                        <em v-if="a.jadwal">sudah dijadwalkan, hasilnya belum dicatat</em>
                        <em v-else>belum dijadwalkan</em>
                    </li>
                </ul>
                <label class="plw-putus__cek is-warn">
                    <input v-model="putusSetujuAktivitas" type="checkbox" />
                    <span>
                        Saya sadar aktivitas di atas <b>belum selesai</b> dan tetap memutuskan sekarang.
                    </span>
                </label>
            </div>

            <!-- Keputusan yang menutup proses DAN mengabari kandidat tak bisa
                 dibatalkan. Centang ini memaksa jeda sadar sebelum mengirim. -->
            <label v-if="butuhCentang" class="plw-putus__cek">
                <input v-model="putusSetuju" type="checkbox" />
                <span>Saya paham keputusan ini <b>final</b> dan email pemberitahuan akan <b>langsung dikirim</b> ke kandidat.</span>
            </label>
        </ConfirmModal>

        <!-- ATUR JADWAL aktivitas tatap muka. Menyimpan jadwal SEKALIGUS mengirim
             undangan — dua hal yang selalu berpasangan, jadi tidak dipisah agar
             tidak ada jadwal yang tersimpan tanpa pernah dikabarkan. -->
        <!-- danger=false: ConfirmModal bawaannya bergaya PERINGATAN (ikon tong
             sampah, tombol merah) karena umumnya dipakai untuk tindakan merusak.
             Menjadwalkan wawancara justru sebaliknya — mengundang kandidat. -->
        <ConfirmModal
            :show="jadwalShow"
            :danger="false"
            icon="bi-calendar-event"
            title="Atur Jadwal Aktivitas"
            :subtitle="jadwalTarget ? `${jadwalTarget.label} \u2014 ${detailKandidat?.pelamar || ''}` : ''"
            :confirm-label="jadwalTarget?.jadwalPrivat ? 'Simpan Jadwal' : 'Simpan &amp; Undang Kandidat'"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanJadwal"
            form-mode
            @confirm="konfirmJadwal"
            @cancel="jadwalShow = false"
        >
            <div class="plw-jdw">
                <!-- Pilihan metode DISEMBUNYIKAN untuk aktivitas yang mustahil
                     daring (MCU itu pemeriksaan fisik, tanda tangan kontrak butuh
                     kehadiran). Menawarkan pilihan yang tidak masuk akal hanya
                     mengundang salah pilih. -->
                <!-- Pilihan ditampilkan bila ada LEBIH DARI SATU bentuk yang sah.
                     MCU punya dua — VENDOR (RS/klinik/lab rekanan) atau MANDIRI
                     (klinik/RS pilihan kandidat) — meski keduanya pemeriksaan
                     fisik; yang daring memang tetap tidak ditawarkan. -->
                <div v-if="modeJadwalDipakai.length > 1" class="plw-fld">
                    <label class="plw-fld__lbl">{{ jadwalTarget?.wajibLuring ? 'Cara pelaksanaan' : 'Metode' }} <b>*</b></label>
                    <!-- Tombolnya DARI MASTER Mode Jadwal. Dulu dua tombol
                         ditulis mati di sini, dan bentuk ketiga — telepon, yang
                         justru paling lazim untuk penawaran gaji — mustahil ada
                         tanpa menyunting layar. -->
                    <div class="plw-seg">
                        <button
                            v-for="m in modeJadwalDipakai" :key="m.kode"
                            type="button" class="plw-seg__b is-net"
                            :class="{ 'is-on': jadwalMode === m.kode }"
                            :title="m.deskripsi"
                            @click="jadwalMode = m.kode"
                        >
                            <i class="bi" :class="m.ikon"></i> {{ m.nama }}
                        </button>
                    </div>
                    <p v-if="modeJadwalDef?.deskripsi" class="plw-fld__hint">{{ modeJadwalDef.deskripsi }}</p>
                </div>
                <p v-else-if="jadwalTarget?.wajibLuring" class="plw-note is-lock">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>
                        {{ jadwalTarget?.tipeNama || 'Aktivitas ini' }} hanya bisa dijalankan
                        <b>tatap muka</b>, jadi metodenya tidak dapat diubah.
                    </span>
                </p>

                <!-- SATU KOLOM PENUH, tidak lagi berdampingan.
                     Dua pemilih tanggal-dan-jam bersebelahan menyisakan lebar
                     yang tak cukup untuk formatnya sendiri: "12 Agu 2026 09:00"
                     terpotong, dan panel kalendernya melebihi kotaknya lalu
                     tertahan tepi modal. Ditumpuk, keduanya terbaca utuh — dan
                     urutannya jadi jelas: mulai dulu, baru selesai. -->
                <!-- JADWAL INTERNAL — dikatakan SEBELUM disimpan, bukan sesudah.
                     Server memang menjawab "kandidat tidak menerima undangan"
                     setelah tombolnya ditekan, tapi saat itu rekruter sudah
                     telanjur mengira pekerjaannya selesai. -->
                <p v-if="jadwalTarget?.jadwalPrivat" class="plw-note is-lock">
                    <i class="bi bi-eye-slash-fill"></i>
                    <span>
                        <b>{{ jadwalTarget?.tipeNama || 'Aktivitas ini' }}</b> dijadwalkan untuk tim sendiri.
                        Kandidat <b>tidak</b> menerima undangan dan tidak melihatnya di portal &mdash;
                        hubungi dia langsung, lalu catat hasilnya lewat <b>Hadir / Tidak Hadir</b>.
                    </span>
                </p>

                <!-- BERRENTANG TANGGAL (MCU vendor / mandiri): tidak ada jam janji
                     temu — yang ditanyakan RENTANGNYA, dan itu pun sudah terisi
                     dari master (Batas_Hari_Bawaan): rekruter cukup menyimpan.
                     Tanggal terakhir = batasnya (unggah mandiri, pengingat). -->
                <template v-if="modeJadwalDef?.batasWaktu">
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Rentang tanggal pemeriksaan <b>*</b></label>
                        <el-date-picker
                            v-model="jadwalRentang" type="daterange"
                            format="DD MMM YYYY" value-format="YYYY-MM-DD"
                            range-separator="–" start-placeholder="Tanggal pertama" end-placeholder="Tanggal terakhir"
                            style="width: 100%"
                            :disabled-date="sebelumHariIni"
                        />
                        <p class="plw-fld__hint">
                            Tanggal terakhir berlaku sampai pukul 23.59<template v-if="modeJadwalDef.batasHari">
                            — terisi otomatis {{ modeJadwalDef.batasHari }} hari dari hari ini</template>.
                            <template v-if="modeJadwalDef.unggahKandidat">
                                Itu juga batas kandidat mengunggah hasilnya; sesudahnya kotak unggah ditutup (bisa diperpanjang).
                            </template>
                            <template v-else>Tanpa jam janji temu — kandidat datang kapan saja dalam rentang ini.</template>
                        </p>
                    </div>
                    <!-- "Tempat" MANDIRI yang dibaca kandidat — dari master mode,
                         bukan isian: tidak ada lokasi yang perlu dipilih tim. -->
                    <p v-if="!modeJadwalDef.butuhTempat && modeJadwalDef.kalimatUndangan" class="plw-note is-info">
                        <i class="bi bi-hospital"></i>
                        <span><b>Tempat untuk kandidat:</b> {{ modeJadwalDef.kalimatUndangan }}</span>
                    </p>
                </template>
                <template v-else>
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Waktu mulai <b>*</b></label>
                    <!-- TANGGAL YANG SUDAH LEWAT TIDAK BISA DIPILIH (masukan user
                         2 Okt 2026) — kalender hanya mematikan HARI, jadi jam yang
                         sudah lewat hari ini ditolak lewat pesan di bawahnya. -->
                    <el-date-picker
                        v-model="jadwalMulai" type="datetime"
                        format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss"
                        placeholder="Pilih tanggal & jam" style="width: 100%"
                        :disabled-date="sebelumHariIni"
                    />
                    <p v-if="jadwalMulaiLewat" class="plw-note is-err">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Waktu mulai itu sudah lewat — pilih tanggal &amp; jam yang akan datang.</span>
                    </p>
                </div>
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Waktu selesai <small>opsional</small></label>
                    <!-- TANGGAL SEBELUM HARI MULAI DIMATIKAN DI KALENDERNYA.
                         Server memang sudah menolaknya (`after:mulai`), tapi
                         penolakan itu baru datang setelah tombol "Simpan &
                         Undang Kandidat" ditekan — dan yang membacanya mengira
                         ada yang rusak, bukan bahwa ia salah pilih. Lebih baik
                         tanggalnya tidak bisa dipilih sejak awal. -->
                    <el-date-picker
                        v-model="jadwalSelesai" type="datetime"
                        format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss"
                        placeholder="Perkiraan selesai" style="width: 100%"
                        :default-time="JAM_TUTUP"
                        :disabled-date="sebelumHariMulai"
                    />
                    <!-- Kalender hanya bisa mematikan TANGGAL, bukan jam. Jadi
                         09:00 masih bisa dipilih pada hari yang sama dengan
                         jadwal yang mulai 14:00, dan yang menahannya hanyalah
                         kalimat ini berikut tombol simpan yang padam. -->
                    <p v-if="jadwalSelesaiSalah" class="plw-note is-err">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Waktu selesai masih lebih awal daripada waktu mulai. Perbaiki dulu, atau kosongkan saja — kolom ini opsional.</span>
                    </p>
                </div>
                <!-- KONFIRMASI KEHADIRAN — batasnya WAKTU MULAI di atas (keputusan
                     user 1 Okt 2026), tidak ada yang perlu diisi. Lewat waktu mulai,
                     tombol kandidat hilang dan kehadiran dicatat seperti biasa. -->
                <p v-if="konfJadwalAktif" class="plw-note is-info">
                    <i class="bi bi-calendar-check"></i>
                    <span>
                        Kandidat diminta <b>konfirmasi kehadiran</b> lewat tombol di email — akan hadir, minta jadwal lain,
                        atau tidak melanjutkan — dan bisa menjawab sampai <b>waktu mulai<template v-if="jadwalMulai"> ({{ waktuKonf(jadwalMulai) }})</template></b>.
                        <template v-if="konfJadwalLama"> Ia sudah menjawab <b>{{ konfJadwalLama.label }}</b>; mengubah waktu, bentuk, atau tempat meminta konfirmasi ulang, mengubah instruksi saja tidak.</template>
                    </span>
                </p>
                </template>

                <div v-if="modeJadwalDef?.butuhTautan" class="plw-fld">
                    <label class="plw-fld__lbl" for="jdw-link">Tautan pertemuan <b>*</b></label>
                    <input id="jdw-link" v-model="jadwalLink" type="url" class="plw-inp" placeholder="https://meet.google.com/..." maxlength="500" />
                </div>

                <!-- TELEPON: tanpa tautan, tanpa lokasi. Yang dibutuhkan cuma
                     nomor yang akan dihubungi — dan nomornya BAGIAN DARI JANJI,
                     bukan salinan profil: kandidat yang sedang bekerja kerap
                     minta dihubungi di nomor lain, dan negosiasi gaji justru
                     percakapan yang paling tidak ingin ia terima di mejanya. -->
                <div v-else-if="modeJadwalDef?.butuhKontak" class="plw-fld">
                    <label class="plw-fld__lbl" for="jdw-kontak">{{ modeJadwalDef.labelKontak }} <b>*</b></label>
                    <input id="jdw-kontak" v-model="jadwalKontak" type="tel" class="plw-inp" placeholder="mis. 0812-3456-7890" maxlength="40" />
                    <p v-if="modeJadwalDef.petunjukKontak" class="plw-fld__hint">{{ modeJadwalDef.petunjukKontak }}</p>
                    <button
                        v-if="detailKandidat?.hp && jadwalKontak !== detailKandidat.hp"
                        type="button" class="plw-fld__isi" @click="jadwalKontak = detailKandidat.hp"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i> Pakai nomor profil ({{ detailKandidat.hp }})
                    </button>
                </div>
                <template v-else-if="modeJadwalDef?.butuhLokasi">
                    <!-- Lokasi DIPILIH dari Master Lokasi, bukan diketik bebas.
                         Titik petanya ikut, sehingga kandidat menerima peta yang
                         bisa dibuka — bukan alamat yang harus disalin sendiri. -->
                    <!-- DAFTARNYA DISARING MENURUT PERUNTUKAN aktivitas ini: MCU
                         hanya menampilkan rumah sakit, wawancara hanya kantor.
                         Labelnya pun dari master — jendela ini tidak tahu bahwa
                         "MEDIS" berarti rumah sakit, ia cuma membaca teksnya. -->
                    <label class="plw-fld__lbl" style="margin-top: 12px">
                        {{ peruntukanJadwal?.nama || 'Lokasi' }} <b>*</b>
                    </label>
                    <el-select
                        v-model="jadwalLokasiId"
                        filterable
                        :placeholder="peruntukanJadwal?.labelPilih || 'Pilih kantor / klinik'"
                        style="width: 100%; margin-top: 6px"
                        :loading="lokasiLoading"
                    >
                        <el-option
                            v-for="l in lokasiUntukJadwal"
                            :key="l.id"
                            :value="l.id"
                            :label="l.nama + (l.kota ? ' — ' + l.kota : '')"
                        >
                            <div class="plw-lok__opt">
                                <b>{{ l.nama }}</b>
                                <small>{{ [l.kategori, l.kota].filter(Boolean).join(' · ') || l.alamat }}</small>
                            </div>
                        </el-option>
                        <!-- TEMPAT DADAKAN — RS yang belum terdaftar. Tanpa ini,
                             nama RS terpaksa dititipkan di kotak patokan, dan di
                             sana ia bukan alamat, bukan peta, dan tak terhitung. -->
                        <el-option v-if="bolehLokasiLain" :value="LOKASI_LAIN" :label="peruntukanJadwal?.labelLainnya || 'Lainnya'">
                            <div class="plw-lok__opt">
                                <b>{{ peruntukanJadwal?.labelLainnya || 'Lainnya' }}</b>
                                <small>Isi sendiri nama &amp; alamatnya</small>
                            </div>
                        </el-option>
                    </el-select>

                    <!-- Daftar kosong disebutkan APA ADANYA. Dropdown kosong tanpa
                         keterangan terbaca sebagai layar yang rusak, dan rekruter
                         menutupnya alih-alih memakai "Lainnya". -->
                    <p v-if="!lokasiLoading && !lokasiUntukJadwal.length" class="plw-note" style="margin-top: 8px">
                        <i class="bi bi-info-circle"></i>
                        <span>{{ peruntukanJadwal?.labelKosong || 'Belum ada lokasi terdaftar untuk aktivitas ini.' }}</span>
                    </p>

                    <!-- Isian tempat di luar daftar. Alamatnya WAJIB (dari master):
                         undangan tanpa alamat membuat kandidat tidak tahu harus
                         datang ke mana, dan itu baru ketahuan di hari-H. -->
                    <template v-if="jadwalLokasiId === LOKASI_LAIN">
                        <div class="plw-fld">
                            <label class="plw-fld__lbl" for="jdw-lok-nama">
                                {{ peruntukanJadwal?.labelNamaLainnya || 'Nama tempat' }} <b>*</b>
                            </label>
                            <input id="jdw-lok-nama" v-model="jadwalLokasiNama" type="text" class="plw-inp" placeholder="mis. RS Siti Khadijah" maxlength="200" />
                        </div>
                        <div class="plw-fld">
                            <label class="plw-fld__lbl" for="jdw-lok-alamat">
                                {{ peruntukanJadwal?.labelAlamatLainnya || 'Alamat' }}
                                <b v-if="peruntukanJadwal?.wajibAlamatLainnya">*</b>
                                <small v-else>opsional</small>
                            </label>
                            <input id="jdw-lok-alamat" v-model="jadwalLokasiAlamat" type="text" class="plw-inp" placeholder="mis. Jl. Demang Lebar Daun No. 7, Palembang" maxlength="500" />
                        </div>
                    </template>

                    <!-- Pratinjau peta: rekruter memastikan titiknya benar SEBELUM
                         undangan terkirim, bukan setelah kandidat tersesat.
                         BERLAKU JUGA untuk tempat yang diketik sendiri — petanya
                         disusun dari nama + alamatnya, jadi tidak ada undangan
                         berperingkat dua hanya karena tempatnya belum terdaftar. -->
                    <div v-if="lokasiTerpilih" class="plw-lok" :class="{ 'is-teks': lokasiTerpilih.lepas }">
                        <iframe
                            v-if="lokasiTerpilih.mapsEmbed"
                            :src="lokasiTerpilih.mapsEmbed"
                            class="plw-lok__map"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            :title="'Peta ' + lokasiTerpilih.nama"
                        ></iframe>
                        <div class="plw-lok__info">
                            <i class="bi bi-geo-alt-fill"></i>
                            <div style="min-width: 0">
                                <b>
                                    {{ lokasiTerpilih.nama }}
                                    <!-- Disebut apa adanya: tempat ini tidak ada di master,
                                         jadi petanya hasil pencarian nama + alamat — bukan
                                         titik yang pernah diverifikasi seseorang. -->
                                    <span v-if="lokasiTerpilih.lepas" class="plw-lok__tag">tanpa peta</span>
                                </b>
                                <small v-if="lokasiTerpilih.alamat">{{ lokasiTerpilih.alamat }}</small>
                                <small v-if="lokasiTerpilih.lepas">Alamat ini dikirim apa adanya ke kandidat. Daftarkan di Master Lokasi bila tempatnya sering dipakai — di sana petanya ikut.</small>
                                <small v-if="lokasiTerpilih.kontakTelp">Kontak: {{ lokasiTerpilih.kontakTelp }}</small>
                            </div>
                            <a v-if="lokasiTerpilih.mapsUrl" :href="lokasiTerpilih.mapsUrl" target="_blank" rel="noopener">Buka peta</a>
                        </div>
                    </div>

                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="jdw-lokasi">Patokan / detail lokasi <small>opsional</small></label>
                        <input id="jdw-lokasi" v-model="jadwalLokasi" type="text" class="plw-inp" placeholder="mis. Gedung B lantai 3, temui resepsionis" maxlength="300" />
                    </div>
                </template>
                <!-- VENDOR: tempat DIKETIK, bukan dipilih dari peta. Vendor
                     berjaringan (laboratorium, klinik) punya puluhan cabang di satu
                     kota — yang dibutuhkan kandidat adalah NAMA vendornya dan cabang
                     mana saja yang melayani, bukan satu titik peta yang pasti salah
                     untuk sebagian orang. Alamat & tautan Google Maps hanya bila
                     memang satu tempat — dan petanya tidak pernah dikarang sistem. -->
                <template v-else-if="modeJadwalDef?.butuhTempat">
                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="jdw-vendor">{{ modeJadwalDef.labelTempat || 'Nama tempat' }} <b>*</b></label>
                        <input id="jdw-vendor" v-model="jadwalLokasiNama" type="text" class="plw-inp" placeholder="mis. Laboratorium Klinik Pramita" maxlength="200" />
                    </div>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Cabang / lokasi yang melayani <small>opsional</small></label>
                        <EditorQuill
                            v-model="jadwalLokasiHtml"
                            ringkas
                            placeholder="mis. Semua cabang di Palembang: Pramita Sudirman, Pramita Demang. Datang 07.00–10.00."
                            hint="Kandidat memilih cabang terdekat dari daftar ini."
                        />
                    </div>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="jdw-vendor-alamat">Alamat lengkap <small>opsional</small></label>
                        <input id="jdw-vendor-alamat" v-model="jadwalLokasiAlamat" type="text" class="plw-inp" placeholder="Bila hanya satu tempat — mis. Jl. Jend. Sudirman No. 12, Palembang" maxlength="500" />
                    </div>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="jdw-vendor-peta">Tautan Google Maps <small>opsional</small></label>
                        <input id="jdw-vendor-peta" v-model="jadwalMapsUrl" type="url" class="plw-inp" placeholder="https://maps.app.goo.gl/..." maxlength="500" />
                        <p v-if="jadwalMapsSalah" class="plw-note is-err">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>Harus tautan Google Maps — salin lewat tombol <b>Bagikan</b> di Google Maps.</span>
                        </p>
                    </div>
                </template>

                <!-- SURAT PENGANTAR — wajib untuk mode ber-Flag_Butuh_Surat. Boleh
                     lebih dari satu, hanya PDF, dengan batas JUMLAH ukuran seluruhnya
                     (bukan per berkas). Surat yang sudah melekat bisa dihapus satu
                     per satu; yang baru langsung diunggah begitu dipilih dan baru
                     terikat ke kandidat ini saat jadwalnya disimpan. -->
                <div v-if="modeJadwalDef?.butuhSurat" class="plw-fld">
                    <label class="plw-fld__lbl">
                        {{ modeJadwalDef.labelSurat }} <b>*</b>
                        <span class="plw-surat__kuota" :class="{ 'is-penuh': jadwalSuratSisa <= 0 }">
                            {{ fmtUkuran(jadwalSuratTotal) }} dari {{ jadwalFitur.suratMaksMb || 5 }} MB
                        </span>
                    </label>
                    <div class="plw-surat">
                        <div v-for="(s, i) in jadwalSuratDaftar" :key="s.ref || 'lama' + s.tetap" class="plw-surat__baris">
                            <i class="bi bi-file-earmark-pdf-fill"></i>
                            <a v-if="s.url" :href="s.url" target="_blank" rel="noopener" class="plw-surat__nama">{{ s.nama }}</a>
                            <span v-else class="plw-surat__nama">{{ s.nama }}</span>
                            <span class="plw-surat__ukuran">{{ fmtUkuran(s.ukuran) }}</span>
                            <small :class="s.ref ? 'is-baru' : 'is-lama'">{{ s.ref ? 'baru' : 'terlampir' }}</small>
                            <button type="button" class="plw-surat__x" title="Hapus surat ini" @click="jadwalSuratDaftar.splice(i, 1)">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <span v-if="!jadwalSuratDaftar.length" class="plw-surat__kosong">Belum ada surat.</span>
                        <label class="plw-surat__pilih" :class="{ 'is-busy': suratUnggahDi === 'satuan', 'is-mati': jadwalSuratSisa <= 0 }">
                            <input
                                type="file" hidden multiple :accept="suratAccept"
                                :disabled="!!suratUnggahDi || jadwalSuratSisa <= 0" @change="unggahSurat($event, 'satuan')"
                            >
                            <i class="bi" :class="suratUnggahDi === 'satuan' ? 'bi-arrow-repeat plw-putar' : 'bi-plus-lg'"></i>
                            {{ suratUnggahDi === 'satuan' ? 'Mengunggah…' : 'Tambah surat (PDF)' }}
                        </label>
                    </div>
                    <p class="plw-fld__hint">
                        Hanya PDF · boleh lebih dari satu · total paling besar {{ jadwalFitur.suratMaksMb || 5 }} MB
                        (sisa {{ fmtUkuran(Math.max(0, jadwalSuratSisa)) }}). Kandidat mengunduhnya dari email dan portal.
                    </p>
                </div>

                <!-- INSTRUKSI UNTUK KANDIDAT — terisi dari Master Alur dan
                     LANGSUNG bisa disunting di sini: editornya selalu tampil,
                     tanpa klik "Ubah" lebih dulu. Data per kandidat (tempat,
                     waktu, biaya) TIDAK ditulis di sini — sistem yang merakitnya
                     di posisi tetap undangan. -->
                <div v-if="jadwalFitur.instruksi" class="plw-fld">
                    <label class="plw-fld__lbl">
                        {{ jadwalTarget?.jadwalPrivat ? 'Catatan internal' : 'Instruksi untuk kandidat' }}
                        <small>opsional</small>
                    </label>
                    <EditorQuill
                        v-model="jadwalCatatanHtml"
                        ringkas
                        placeholder="mis. Bawa KTP asli. Puasa 10 jam sebelum pemeriksaan."
                        hint="Terisi dari Master Alur — ubah bila ada yang khusus. Tanpa tanggal/tempat: keduanya dirakit sistem di undangan."
                    />
                    <p v-if="jadwalInfo.muat" class="plw-fld__hint">
                        <i class="bi bi-arrow-repeat plw-putar"></i> Memuat instruksi dari Master Alur…
                    </p>
                    <button
                        v-else-if="jadwalInfo.instruksiAlur && jadwalCatatanHtml !== jadwalInfo.instruksiAlur"
                        type="button" class="plw-fld__isi" @click="jadwalCatatanHtml = jadwalInfo.instruksiAlur"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i> Kembalikan instruksi dari Master Alur
                    </button>
                </div>
                <div v-else class="plw-fld">
                    <!-- "Untuk kandidat" hanya benar bila ia menerimanya. Pada
                         jadwal privat catatan ini tidak pernah sampai ke mana
                         pun selain catatan tim sendiri. -->
                    <label class="plw-fld__lbl" for="jdw-catatan">
                        {{ jadwalTarget?.jadwalPrivat ? 'Catatan internal' : 'Catatan untuk kandidat' }}
                        <small>opsional</small>
                    </label>
                    <textarea id="jdw-catatan" v-model="jadwalCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="1000" placeholder="mis. Bawa KTP asli dan portofolio cetak."></textarea>
                </div>

                <!-- INFORMASI BIAYA — kalimatnya dari master tipe (MCU: bayar
                     dulu, diganti bila lolos); tampil-tidaknya diatur admin.
                     Bawaannya dari Master Alur, bisa diubah untuk jadwal ini —
                     mis. biaya sudah dijelaskan di instruksi, atau vendor dibayar
                     langsung perusahaan. Penggantiannya tetap ditentukan sistem
                     dari hasil MCU. -->
                <div v-if="biayaJadwal && !jadwalTarget?.jadwalPrivat" class="plw-biaya" :class="{ 'is-off': !jadwalTampilBiaya }">
                    <div class="plw-biaya__head">
                        <span><i class="bi bi-cash-coin"></i> <b>Tampilkan informasi biaya ke kandidat</b></span>
                        <el-switch v-model="jadwalTampilBiaya" size="small" />
                    </div>
                    <!-- Kalimatnya bisa disunting untuk kandidat ini — terisi dari
                         Master Alur (atau kalimat bawaan tipe). -->
                    <textarea
                        v-if="jadwalTampilBiaya"
                        v-model="jadwalKalimatBiaya" class="plw-inp plw-inp--ta plw-biaya__teks" rows="3" maxlength="1000"
                        :placeholder="biayaJadwal"
                    ></textarea>
                    <p v-else class="plw-biaya__isi">{{ jadwalKalimatBiaya || biayaJadwal }}</p>
                    <button
                        v-if="jadwalTampilBiaya && jadwalInfo.kalimatBiayaAlur && jadwalKalimatBiaya.trim() !== jadwalInfo.kalimatBiayaAlur"
                        type="button" class="plw-fld__isi" @click="jadwalKalimatBiaya = jadwalInfo.kalimatBiayaAlur"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i> Kembalikan kalimat bawaan
                    </button>
                    <small class="plw-biaya__hint">
                        {{ jadwalTampilBiaya
                            ? 'Ikut di email & portal kandidat — kalimatnya bisa disunting. Matikan bila biaya sudah dijelaskan di instruksi.'
                            : 'Tidak ditampilkan — email & portal kandidat tanpa informasi biaya.' }}
                    </small>
                </div>

                <!-- ALASAN PERUBAHAN — hanya saat jadwal yang SUDAH dikirim
                     diubah. Menjadwalkan pertama kali tidak menuntut apa pun. -->
                <div v-if="butuhAlasanJadwal" class="plw-fld">
                    <label class="plw-fld__lbl" for="jdw-alasan">Alasan perubahan <b>*</b></label>
                    <textarea
                        id="jdw-alasan" v-model="jadwalAlasan" class="plw-inp plw-inp--ta" rows="2" maxlength="500"
                        placeholder="mis. Vendor penuh minggu ini — dialihkan ke klinik terdekat pilihan kandidat."
                    ></textarea>
                    <p class="plw-fld__hint">Kandidat sudah menerima jadwal lama. Alasannya tercatat di riwayat jadwal.</p>
                </div>

                <!-- RIWAYAT JADWAL — siapa mengubah apa, kapan, dan kenapa. -->
                <div v-if="jadwalInfo.jejak.length" class="plw-jdw__jejak">
                    <button type="button" class="plw-fld__isi" @click="jadwalJejakBuka = !jadwalJejakBuka">
                        <i class="bi" :class="jadwalJejakBuka ? 'bi-chevron-up' : 'bi-clock-history'"></i>
                        Riwayat jadwal ({{ jadwalInfo.jejak.length }})
                    </button>
                    <ol v-if="jadwalJejakBuka" class="plw-jdw__jejaklist">
                        <li v-for="(j, i) in jadwalInfo.jejak" :key="i">
                            <b>{{ labelAksiJejak(j.aksi) }}</b> · {{ fmtWaktu(j.at) }} · {{ j.oleh || 'Sistem' }}
                            <small>{{ ringkasJejak(j) }}</small>
                            <small v-if="j.alasan" class="is-alasan">Alasan: {{ j.alasan }}</small>
                        </li>
                    </ol>
                </div>

                <!-- Janji email hanya diucapkan bila emailnya memang dikirim.
                     Tipe berjadwal privat tidak mengirim apa pun. -->
                <p v-if="!jadwalTarget?.jadwalPrivat" class="plw-note is-info">
                    <i class="bi bi-envelope-fill"></i>
                    <span>
                        <template v-if="modeJadwalDef?.batasWaktu">
                            Email berisi rentang tanggal, {{ modeJadwalDef.butuhTempat ? 'vendor & cabangnya' : 'tempat pilihan kandidat' }}<template v-if="modeJadwalDef.butuhSurat">, tautan {{ hurufKecilAwal(modeJadwalDef.labelSurat || 'surat') }}</template><template v-if="biayaJadwal && jadwalTampilBiaya">, aturan biaya</template>,
                            dan instruksi di atas langsung dikirim ke kandidat.<template v-if="modeJadwalDef.unggahKandidat"> Pengingat unggah terkirim otomatis menjelang batasnya.</template>
                        </template>
                        <template v-else>
                            Undangan berisi waktu, {{ modeJadwalDef?.butuhTautan ? 'tautan pertemuan' : (modeJadwalDef?.butuhLokasi ? 'lokasi berikut petanya' : 'bentuk pelaksanaannya') }}<template v-if="biayaJadwal && jadwalTampilBiaya">, aturan biaya</template>,
                            dan catatan di atas langsung dikirim ke email kandidat.
                        </template>
                    </span>
                </p>
            </div>
        </ConfirmModal>

        <!-- PERPANJANG BATAS UNGGAH — kandidat yang mengunggah hasilnya sendiri
             (MCU mandiri). Tanggal pemeriksaan TIDAK ikut mundur; alasannya
             tercatat di jejak. -->
        <ConfirmModal
            :show="perpanjangShow"
            :danger="false"
            icon="bi-hourglass-split"
            title="Perpanjang Batas Unggah"
            :subtitle="perpanjangTarget ? `${perpanjangTarget.label} — ${detailKandidat?.pelamar || ''}` : ''"
            :confirm-label="perpanjangTarget?.jadwalPrivat ? 'Perpanjang' : 'Perpanjang &amp; Kabari Kandidat'"
            :busy="sibuk"
            :confirm-disabled="!bolehPerpanjang"
            form-mode
            @confirm="konfirmPerpanjang"
            @cancel="perpanjangShow = false"
        >
            <div class="plw-jdw">
                <p class="plw-note is-info">
                    <i class="bi bi-calendar-range"></i>
                    <span>
                        Batas unggah sekarang <b>{{ perpanjangTarget?.jadwal?.batasUnggahTeks || '—' }}</b>.
                        Yang mundur hanya batas unggah hasil — tanggal pemeriksaan
                        <b>{{ perpanjangTarget?.jadwal?.rentangTeks || '—' }}</b>, tempat, surat pengantar, dan instruksi tetap.
                        Kotak unggah kandidat terbuka sampai tanggal baru.
                    </span>
                </p>
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Batas unggah yang baru <b>*</b></label>
                    <el-date-picker
                        v-model="perpanjangTanggal" type="date"
                        format="DD MMM YYYY" value-format="YYYY-MM-DD"
                        placeholder="Pilih tanggal" style="width: 100%"
                        :disabled-date="sebelumBatasBaru"
                    />
                    <p class="plw-fld__hint">Berlaku sampai pukul 23.59 hari itu.</p>
                </div>
                <div class="plw-fld">
                    <label class="plw-fld__lbl" for="jdw-panjang-alasan">Alasan perpanjangan <b>*</b></label>
                    <textarea
                        id="jdw-panjang-alasan" v-model="perpanjangAlasan" class="plw-inp plw-inp--ta" rows="2" maxlength="500"
                        placeholder="mis. Hasil lab kandidat baru keluar lusa."
                    ></textarea>
                    <p class="plw-fld__hint">Tercatat di riwayat jadwal — tidak dikirim ke kandidat.</p>
                </div>
                <p v-if="!perpanjangTarget?.jadwalPrivat" class="plw-note is-info">
                    <i class="bi bi-envelope-fill"></i>
                    <span>Kandidat menerima email "batas unggah diperpanjang" berisi rincian jadwal yang berlaku.</span>
                </p>
            </div>
        </ConfirmModal>

        <!-- TUNDA JADWAL / PERBARUI INFO TUNDA — dari baris aksi aktivitas.
             Pilihan alasan & rentang tanggalnya dimuat jendelanya sendiri. -->
        <TundaDialog
            v-if="tundaBaris.id"
            :show="tundaBaris.show"
            :id-aktivitas="tundaBaris.id"
            :mode="tundaBaris.mode"
            :konteks="tundaBaris.konteks"
            :awal="tundaBaris.awal"
            @close="tundaBaris.show = false"
            @selesai="selesaiTundaBaris"
        />

        <!-- PROSES PERMINTAAN JADWAL LAIN — dari baris aksi aktivitas. -->
        <PermintaanDialog
            v-if="mintaBaris.minta"
            :show="mintaBaris.show"
            :mode="mintaBaris.mode"
            :permintaan="mintaBaris.minta"
            :konteks="mintaBaris.konteks"
            @close="mintaBaris.show = false"
            @selesai="selesaiMintaBaris"
            @berubah="segarkanKartu({ tes: mintaBaris.id })"
        />

        <!-- KIRIM ULANG EMAIL JADWAL — isinya dirakit ulang dari jadwal yang
             berlaku sekarang. Kandidat mandiri yang belum mengunggah menerima
             bunyi pengingat unggah. -->
        <ConfirmModal
            :show="kirimUlangShow"
            :danger="false"
            icon="bi-envelope-arrow-up"
            title="Kirim Ulang Email"
            :subtitle="kirimUlangTarget ? `${kirimUlangTarget.label} — ${detailKandidat?.pelamar || ''}` : ''"
            confirm-label="Kirim Ulang"
            :busy="sibuk"
            form-mode
            @confirm="konfirmKirimUlang"
            @cancel="kirimUlangShow = false"
        >
            <div class="plw-jdw">
                <p class="plw-note is-info">
                    <i class="bi bi-envelope-paper-fill"></i>
                    <span>
                        <template v-if="kirimUlangTarget?.unggahKandidat && !kirimUlangTarget.unggahKandidat.terkirim">
                            Kandidat belum mengunggah hasilnya — email berbunyi <b>pengingat untuk mengunggah</b>
                            sebelum <b>{{ kirimUlangTarget.unggahKandidat.batasTeks || kirimUlangTarget.jadwal?.batasTeks || '—' }}</b>,
                            lengkap dengan rincian jadwalnya<template v-if="kirimUlangTarget.jadwal?.surat"> dan surat pengantar</template>.
                        </template>
                        <template v-else>
                            Email berisi rincian jadwal <b>yang berlaku sekarang</b>
                            ({{ jadwalRingkas(kirimUlangTarget?.jadwal || {}) }})<template v-if="kirimUlangTarget?.jadwal?.surat">, termasuk surat pengantarnya</template>.
                        </template>
                    </span>
                </p>
                <p v-if="kirimUlangTarget?.jadwal?.emailAt" class="plw-fld__hint">
                    Email jadwal terakhir dikirim {{ fmtWaktu(kirimUlangTarget.jadwal.emailAt) }}.
                </p>
            </div>
        </ConfirmModal>

        <!-- KIRIM ULANG MASSAL — per nama aktivitas, sama seperti jadwal massal. -->
        <ConfirmModal
            :show="kirimUlangMassalShow"
            :danger="false"
            icon="bi-envelope-arrow-up"
            title="Kirim Ulang Email Jadwal"
            :subtitle="`${terpilih.length} kandidat terpilih`"
            confirm-label="Kirim Ulang"
            :busy="sibuk"
            :confirm-disabled="!bolehKirimUlangMassal"
            form-mode
            @confirm="konfirmKirimUlangMassal"
            @cancel="kirimUlangMassalShow = false"
        >
            <div class="plw-jdw">
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Aktivitas <b>*</b></label>
                    <el-select v-model="kirimUlangAktivitas" placeholder="Pilih aktivitas" style="width: 100%">
                        <el-option
                            v-for="a in aktivitasKirimUlang" :key="a.label"
                            :value="a.label" :label="`${a.label} — ${a.jumlah} kandidat`"
                        />
                    </el-select>
                </div>
                <div v-if="sasaranKirimUlang.length" class="plw-prev">
                    <div class="plw-prev__head">
                        <i class="bi bi-list-check"></i>
                        <span>{{ sasaranKirimUlang.length }} kandidat akan dikirimi ulang</span>
                    </div>
                    <div class="plw-prev__list">
                        <div v-for="(p, i) in sasaranKirimUlang" :key="p.t.id" class="plw-prev__row">
                            <span class="plw-prev__no">{{ i + 1 }}</span>
                            <span class="plw-ell">{{ p.r.pelamar }}</span>
                            <b>{{ p.t.unggahKandidat && !p.t.unggahKandidat.terkirim ? 'ajakan unggah' : 'info jadwal' }}</b>
                        </div>
                    </div>
                </div>
                <p v-if="lebihanKirimUlang" class="plw-note is-err">
                    <i class="bi bi-shield-exclamation"></i>
                    <span>
                        Maksimal <b>{{ batasKirimUlang }} kandidat</b> sekali kirim — lepas {{ lebihanKirimUlang }} centang,
                        lalu kirim sisanya di gelombang berikutnya.
                    </span>
                </p>
                <p class="plw-note is-info">
                    <i class="bi bi-envelope-fill"></i>
                    <span>
                        Tiap kandidat menerima satu email berisi rincian jadwal yang berlaku sekarang. Yang baru dikirimi email
                        beberapa menit lalu, atau sudah mengirim berkasnya, dilewati dan disebutkan satu per satu.
                    </span>
                </p>
            </div>
        </ConfirmModal>

        <!-- Konfirmasi kehadiran. Catatan opsional untuk keduanya — alasan tidak
             hadir sering perlu dicatat, tapi memaksanya justru menahan tim. -->
        <ConfirmModal
            :show="hadirShow"
            :danger="hadirNilai === 'T'"
            :icon="hadirNilai === 'Y' ? (hadirBerbatas ? 'bi-clipboard2-check-fill' : 'bi-person-check-fill') : 'bi-person-dash-fill'"
            :title="hadirNilai === 'Y'
                ? (hadirBerbatas ? 'Catat Hasil' : 'Tandai Hadir')
                : (hadirBerbatas ? 'Tandai Tidak Melaksanakan' : 'Tandai Tidak Hadir')"
            :subtitle="hadirTarget ? `${hadirTarget.label} \u2014 ${detailKandidat?.pelamar || ''}` : ''"
            :confirm-label="hadirNilai === 'Y'
                ? (hadirBerbatas ? 'Simpan Hasil' : 'Simpan Kehadiran & Hasil')
                : (hadirBerbatas ? 'Ya, Tidak Melaksanakan' : 'Ya, Tidak Hadir')"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanHadir"
            form-mode
            @confirm="konfirmHadir"
            @cancel="tutupModalAktivitas('hadirShow')"
        >
            <p class="plw-hdr__note">
                <template v-if="hadirNilai === 'Y'">
                    Isi hasilnya sekalian di sini — <b>satu langkah</b>, tidak ada jendela lanjutan.
                    Begitu disimpan, aktivitas ini ditutup dan tahapnya dievaluasi sistem.
                </template>
                <template v-else>
                    Aktivitas ini akan ditandai <b>GAGAL</b> dan tahapnya dievaluasi ulang oleh sistem.
                    Tombol keputusan tetap terbuka setelahnya.
                </template>
            </p>
            <!-- Jawaban kandidat atas undangannya — RENCANA, bukan fakta. Yang
                 dicatat di sini adalah faktanya; keduanya sengaja tidak disamakan. -->
            <p
                v-if="hadirTarget?.konfirmasi && !hadirBerbatas"
                class="plw-note"
                :class="['JADWAL_LAIN', 'MUNDUR'].includes(hadirTarget.konfirmasi.status) ? 'is-lock' : 'is-info'"
            >
                <i class="bi" :class="hadirTarget.konfirmasi.ikon || 'bi-info-circle-fill'"></i>
                <span>
                    Konfirmasi kandidat: <b>{{ hadirTarget.konfirmasi.label }}</b>.
                    <template v-if="hadirTarget.konfirmasi.permintaan">Permintaan jadwal lainnya masih terbuka — mencatat kehadiran akan menutupnya.</template>
                    <template v-else-if="hadirTarget.konfirmasi.status === 'MUNDUR'">Kandidat menyatakan tidak melanjutkan — tutup lamarannya lewat keputusan <b>Mengundurkan Diri</b>.</template>
                    <template v-else-if="hadirTarget.konfirmasi.status === 'TANPA_JAWABAN'">Kandidat tidak menjawab sampai batas — ini tidak menggugurkan; catat kehadirannya apa adanya.</template>
                </span>
            </p>

            <!-- KANDIDAT HADIR = penilaiannya sedang berlangsung, dan inilah
                 saat catatannya ditulis — bukan nanti lewat jendela terpisah
                 yang kerap tak pernah dibuka. Keduanya OPSIONAL: menandai
                 kehadiran tidak boleh menuntut penilaian yang belum selesai.

                 Untuk TIDAK HADIR, cukup sebaris alasan. Tidak ada penilaian
                 yang bisa ditulis untuk orang yang tidak datang, dan menyodorkan
                 editor berformat di sana hanya menyiratkan sebaliknya. -->
            <template v-if="hadirNilai === 'Y'">
                <!-- Berkas kandidat ikut terbaca DI SINI, bukan hanya di balik
                     modal: penilai menulis catatannya sambil melihat lembar
                     jawaban yang diserahkan, dan menutup modal untuk membacanya
                     berarti kehilangan yang sudah diketik. -->
                <!-- Mandiri: berkas kandidat ditampilkan di tempat kotak unggah (di
                     bawah), bukan dua kali. -->
                <div v-if="(hadirTarget?.berkasKandidat || []).length && !hasilDariKandidat(hadirTarget)" class="plw-kirimbox">
                    <span class="plw-kirimbox__lbl"><i class="bi bi-inbox-fill"></i> Diserahkan kandidat</span>
                    <!-- Dibuka di MODAL, bukan tab baru: tab baru melempar
                         penilai keluar dari jendela yang sedang ia isi. -->
                    <button
                        v-for="b in hadirTarget.berkasKandidat" :key="b.id"
                        type="button" class="plw-test__kirimfile" :title="b.nama" @click="bukaDok(b)"
                    >
                        <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                        {{ b.nama }}
                    </button>
                </div>

                <!-- JAWABAN KANDIDAT ATAS PENAWARAN.
                     Seluruh proses berakhir pada satu pertanyaan: kandidat
                     mengambil penawaran ini atau tidak. Dicatat DI SINI karena
                     di sinilah jawabannya diterima — tim menelepon, kandidat
                     menjawab. Dulu pertanyaan itu tidak punya satu pun kolom:
                     aktivitas negosiasi berperan INFORMATIF sehingga modal ini
                     tidak menampilkan bidang hasil apa pun. -->
                <div v-if="hadirTarget?.penawaran" class="plw-mform" :class="{ 'is-kurang': jawabKurang }">
                    <div class="plw-mform__head">
                        <span class="plw-mform__ico"><i class="bi bi-envelope-paper-fill"></i></span>
                        <div style="min-width: 0; flex: 1">
                            <div class="plw-mform__title">Jawaban Kandidat atas Penawaran</div>
                            <div class="plw-mform__sub">{{ hadirTarget.label }}<template v-if="hadirTarget.jadwal"> · {{ jadwalRingkas(hadirTarget.jadwal) }}</template></div>
                        </div>
                        <span class="plw-req">wajib</span>
                    </div>

                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Apa jawabannya? <b>*</b></label>
                        <div class="plw-opts">
                            <button
                                v-for="o in jawabanPenawaran" :key="o.kode"
                                type="button" class="plw-opt-card" :class="[`is-${o.nada}`, { 'is-on': jawabPenawaran === o.kode }]"
                                @click="jawabPenawaran = o.kode"
                            >
                                <span class="plw-opt-card__dot"><i class="bi" :class="o.ikon"></i></span>
                                <span class="plw-opt-card__txt">
                                    <b>{{ o.label }}</b>
                                    <small>{{ o.keterangan }}</small>
                                </span>
                            </button>
                        </div>
                        <!-- AKIBATNYA DIKATAKAN SEBELUM DITEKAN. Dua dari tiga
                             jawaban menutup lamaran seketika, dan itu tidak bisa
                             ditarik kembali — admin berhak tahu sebelum, bukan
                             sesudah. -->
                        <p v-if="jawabDef" class="plw-fld__hint" :class="{ 'is-tegas': jawabDef.menutup }">
                            <i class="bi" :class="jawabDef.menutup ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'"></i>
                            <template v-if="jawabDef.menutup">
                                Menyimpan akan <b>menutup lamaran ini seketika</b> dan mengirim pemberitahuan.
                                Tuliskan alasannya di catatan di bawah &mdash; minimal 10 karakter.
                            </template>
                            <template v-else>
                                Lamaran <b>tidak</b> ditutup di sini. Keputusan mengangkat kandidat tetap lewat
                                tombol tahap di worklist.
                            </template>
                        </p>
                    </div>

                    <p v-if="jawabKurang" class="plw-note is-err">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span>{{ jawabKurang }}</span>
                    </p>
                </div>

                <!-- HASIL PEMERIKSAAN KESEHATAN — di langkah inilah tempatnya.
                     Kandidat datang ke klinik, diperiksa, dan inilah hasilnya:
                     satu peristiwa, satu jendela. Empat status baku dari master
                     (Fit / Fit with Note / Temporary Unfit / Unfit) — bukan tiga
                     yang ditulis mati di layar, yang membuat kandidat yang cuma
                     perlu diperiksa ulang dua minggu lagi terpaksa dicatat
                     "Unfit" dan gugur permanen. -->
                <div v-if="hadirTarget?.isMcu" class="plw-mform" :class="{ 'is-kurang': mcuKurangHadir }">
                    <div class="plw-mform__head">
                        <span class="plw-mform__ico"><i class="bi bi-heart-pulse-fill"></i></span>
                        <div style="min-width: 0; flex: 1">
                            <div class="plw-mform__title">Hasil Pemeriksaan Kesehatan</div>
                            <div class="plw-mform__sub">{{ hadirTarget.label }}<template v-if="hadirTarget.jadwal"> · {{ jadwalRingkas(hadirTarget.jadwal) }}</template></div>
                        </div>
                        <span class="plw-req">wajib</span>
                    </div>

                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Status Kesehatan <b>*</b></label>
                        <!-- Kartu, bukan radio bawaan: statusnya berwarna sesuai
                             artinya dan sasaran kliknya selebar baris. -->
                        <div class="plw-opts">
                            <button
                                v-for="o in mcuStatusOpsi" :key="o.kode"
                                type="button" class="plw-opt-card" :class="[`is-${o.nada}`, { 'is-on': mcuStatus === o.kode }]"
                                @click="mcuStatus = o.kode"
                            >
                                <span class="plw-opt-card__dot"><i class="bi" :class="o.ikon"></i></span>
                                <span class="plw-opt-card__txt">
                                    <b>{{ o.label }}</b>
                                    <small>{{ o.keterangan }}</small>
                                </span>
                            </button>
                        </div>
                        <!-- Arti pilihan itu bagi proses DIKATAKAN, tidak dibiarkan
                             ditebak: "Temporary Unfit" dan "Unfit" sama-sama tidak
                             lolos, dan penilai berhak tahu itu SEBELUM menekan. -->
                        <p v-if="mcuDef" class="plw-fld__hint">
                            <i class="bi" :class="mcuDef.lolos ? 'bi-check-circle-fill' : 'bi-x-circle-fill'"></i>
                            Pilihan ini menutup MCU sebagai <b>{{ mcuDef.lolos ? 'LULUS' : 'TIDAK LULUS' }}</b><template v-if="hadirTarget?.biaya">
                            — biaya pemeriksaan kandidat <b>{{ mcuDef.lolos ? 'DIGANTI' : 'TIDAK DIGANTI' }}</b> (otomatis, tidak perlu diisi)</template>.
                        </p>
                    </div>

                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="mcu-penyedia">Penyedia (klinik / RS)</label>
                        <!-- Contoh sengaja TIDAK memakai nama rumah sakit nyata:
                             teks samar mudah terbaca sebagai isian yang sudah
                             terisi, dan nama yang salah pada hasil kesehatan
                             bukan kekeliruan yang murah. -->
                        <input id="mcu-penyedia" v-model="mcuPenyedia" type="text" class="plw-inp" placeholder="Nama klinik / rumah sakit pelaksana" maxlength="200" />
                    </div>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="mcu-tanggal">Tanggal Pemeriksaan</label>
                        <input id="mcu-tanggal" v-model="mcuTanggal" type="date" class="plw-inp" />
                    </div>

                    <!-- DUA KOTAK CATATAN, DAN ITU DISENGAJA.
                         Bukan karena "beda maksud" — karena PEMBACANYA BERBEDA.
                         Yang ini dikirim ke portal dan dibaca KANDIDAT SENDIRI
                         (portalDetail → mcu.catatan); yang berformat di bawah
                         ditahan khusus untuk tim.

                         Menggabungkannya hanya punya dua hasil, dua-duanya
                         buruk: kandidat tak pernah tahu ada pembatasan kerja
                         pada dirinya, atau temuan klinis bocor ke orang yang
                         sedang dinilai. Yang dibetulkan bukan jumlahnya,
                         melainkan labelnya — yang dulu sama-sama berbunyi
                         "catatan" tanpa menyebut siapa yang membacanya. -->
                    <div class="plw-fld">
                        <label class="plw-fld__lbl" for="mcu-catatan">
                            {{ hadirTarget?.internal ? 'Keterangan hasil' : 'Keterangan untuk kandidat' }}
                            <b v-if="mcuDef?.butuhCatatan">*</b>
                            <!-- PENANDA MENGIKUTI SETELAN AKTIVITAS INI, bukan
                                 anggapan umum. MCU bisa disetel internal di
                                 Master Alur (Tampil_Kandidat='T'), dan pada alur
                                 begitu kalimat ini TIDAK pernah sampai ke
                                 kandidat. Memasang lencana "dibaca kandidat"
                                 apa adanya berarti penilai menahan diri menulis
                                 hal yang sebenarnya aman — atau sebaliknya,
                                 menulis untuk pembaca yang tidak ada. -->
                            <span class="plw-lihat" :class="hadirTarget?.internal ? 'is-internal' : 'is-publik'">
                                <i class="bi" :class="hadirTarget?.internal ? 'bi-eye-slash-fill' : 'bi-eye-fill'"></i>
                                {{ hadirTarget?.internal ? 'HANYA TIM' : 'DIBACA KANDIDAT' }}
                            </span>
                        </label>
                        <textarea
                            id="mcu-catatan" v-model="mcuCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="1000"
                            :placeholder="mcuDef?.butuhCatatan
                                ? 'Wajib: pembatasan atau tindak lanjut yang perlu kandidat ketahui — mis. hindari kerja malam; periksa ulang 2 minggu lagi.'
                                : 'mis. Disarankan kontrol tekanan darah berkala.'"
                        ></textarea>
                        <p class="plw-note is-lock">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span v-if="hadirTarget?.internal">
                                Aktivitas MCU ini disetel <b>internal</b> di Master Alur, jadi keterangan
                                ini <b>tidak</b> ditampilkan ke kandidat &mdash; ia tetap perlu diberi tahu
                                lewat jalur lain bila ada pembatasan kerja.
                            </span>
                            <span v-else>
                                Kalimat ini muncul di halaman lamaran kandidat. Tulis <b>akibatnya bagi
                                pekerjaan</b>, bukan diagnosisnya &mdash; temuan klinis rinci ditulis di
                                catatan internal di bawah atau tetap di berkas terlampir.
                            </span>
                        </p>
                    </div>
                    <p v-if="mcuKurangHadir" class="plw-note is-err">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span>{{ mcuKurangHadir }}</span>
                    </p>
                </div>

                <!-- HASIL DI SINI JUGA — satu tindakan, bukan dua.
                     Dulu ada tombol "Catat Hasil" terpisah dengan jendelanya
                     sendiri; keduanya menjelaskan SATU peristiwa ("kandidat
                     datang, hasilnya begini"). Yang paling sering terjadi:
                     jendela kedua tidak pernah dibuka, dan hasilnya tak pernah
                     tercatat. Hanya untuk aktivitas yang hasilnya memang
                     dikerjakan tim — ujian online nilainya datang dari HCLearn. -->
                <div v-if="hadirTarget?.dinilaiTim && hadirTarget?.peran !== 'INFORMATIF' && !hadirTarget?.isMcu && !hadirTarget?.penawaran" class="plw-fld">
                    <label class="plw-fld__lbl">Hasil <b>*</b></label>
                    <div class="plw-seg">
                        <button type="button" class="plw-seg__b is-ok" :class="{ 'is-on': hadirHasil === 'LULUS' }" @click="hadirHasil = 'LULUS'">
                            <i class="bi bi-check-lg"></i> Lulus
                        </button>
                        <button type="button" class="plw-seg__b is-no" :class="{ 'is-on': hadirHasil === 'GAGAL' }" @click="hadirHasil = 'GAGAL'">
                            <i class="bi bi-x-lg"></i> Gagal
                        </button>
                    </div>
                </div>

                <!-- BIDANG NILAI MENGIKUTI MODE PENILAIAN aktivitas ini
                     (disetel di Master Alur). Tes tertulis diberi kotak angka;
                     DISC/FGD diberi daftar predikat; wawancara tidak diberi
                     bidang nilai sama sekali — memaksakan angka pada tes yang
                     hasilnya predikat hanya melahirkan angka karangan. -->
                <div v-if="hadirTarget?.penilaian?.tipe === 'ANGKA'" class="plw-fld">
                    <label class="plw-fld__lbl" for="hadir-nilai">
                        {{ hadirTarget.penilaian.nama }}
                        <small>0 – {{ hadirTarget.penilaian.maks || 1000 }}</small>
                    </label>
                    <input
                        id="hadir-nilai" v-model.number="hadirNilaiSkor" type="number"
                        min="0" :max="hadirTarget.penilaian.maks || 1000" step="0.01"
                        class="plw-inp" placeholder="mis. 85"
                    />
                </div>

                <div v-else-if="hadirTarget?.penilaian?.tipe === 'TEKS'" class="plw-fld">
                    <label class="plw-fld__lbl">{{ hadirTarget.penilaian.nama }}</label>
                    <div class="plw-opts">
                        <button
                            v-for="o in hadirTarget.penilaian.opsi" :key="o"
                            type="button" class="plw-opt-card" :class="{ 'is-on': hadirNilaiTeks === o }"
                            @click="hadirNilaiTeks = hadirNilaiTeks === o ? '' : o"
                        >
                            <span class="plw-opt-card__dot"><i class="bi bi-tag-fill"></i></span>
                            <span class="plw-opt-card__txt"><b>{{ o }}</b></span>
                        </button>
                    </div>
                </div>

                <div class="plw-fld">
                    <label class="plw-fld__lbl">
                        <!-- Dulu dirakit dari nama tipe lalu di-toLowerCase(),
                             sehingga "MCU" jadi "mcu" — nama yang sudah punya
                             bentuk bakunya sendiri jadi rusak. -->
                        {{ hadirTarget?.labelCatatan || 'Catatan hasil aktivitas' }}
                        <small>opsional</small>
                        <!-- Penanda pembaca, bukan hiasan: inilah satu-satunya
                             pembeda antara kotak ini dan "Keterangan untuk
                             kandidat" di atas. Tanpa itu keduanya terbaca
                             sebagai pengulangan, dan orang mengisi salah satu
                             secara acak. -->
                        <span class="plw-lihat is-internal">
                            <i class="bi bi-eye-slash-fill"></i> HANYA TIM
                        </span>
                    </label>
                    <EditorQuill
                        v-model="hadirCatatanHtml"
                        ringkas
                        :upload-url="URL_GAMBAR"
                        :upload-data="{ subTesId: hadirTarget?.id }"
                        placeholder="mis. Komunikasi jelas, pengalaman relevan. Tempelkan juga foto lembar penilaian bila ada."
                        hint="Bisa diberi format & gambar. Catatan ini INTERNAL — kandidat tidak melihatnya."
                    />
                </div>

                <!-- Nama lampirannya DARI TIPE AKTIVITAS, bukan satu kalimat
                     untuk semua: MCU meminta hasil dari klinik, wawancara
                     meminta lembar penilaian, tes offline meminta lembar
                     jawaban. Kalimat yang salah membuat petugas ragu apakah ia
                     sedang membuka jendela yang benar. -->
                <!-- ADA-TIDAKNYA kotak ini ditentukan TIPE AKTIVITASNYA.
                     Negosiasi gaji berlangsung lewat telepon dan tidak
                     menghasilkan dokumen; kotak kosong di sana cuma meminta
                     sesuatu yang memang tidak ada. -->
                <template v-if="hadirTarget?.berkasAktivitas !== false">
                    <!-- HASIL DIUNGGAH KANDIDAT (MCU mandiri): kandidatlah yang
                         memegang hasil & kwitansinya — berkasnya masuk lewat
                         portal. Kotak unggah tim diganti keterangan ini; server
                         juga menolak unggahan tim (subTesBerkasUnggah). Vendor
                         tetap memakai kotak unggah biasa. -->
                    <div v-if="hasilDariKandidat(hadirTarget)" class="plw-hasilk">
                        <div class="plw-hasilk__head">
                            <span class="plw-hasilk__title"><i class="bi bi-paperclip"></i> {{ hadirTarget.labelBerkas || 'Berkas hasil' }}</span>
                            <span class="plw-hasilk__tag"><i class="bi bi-person-fill-lock"></i> diunggah kandidat</span>
                        </div>
                        <div v-if="(hadirTarget.berkasKandidat || []).length" class="plw-hasilk__list">
                            <button
                                v-for="b in hadirTarget.berkasKandidat" :key="b.id"
                                type="button" class="plw-test__kirimfile" :title="b.nama" @click="bukaDok(b)"
                            >
                                <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                                {{ b.nama }}
                            </button>
                        </div>
                        <p v-else class="plw-hasilk__kosong">
                            <i class="bi bi-hourglass-split"></i>
                            Kandidat belum mengunggah hasilnya<template v-if="hadirTarget.unggahKandidat?.batasTeks"> — batas unggah {{ hadirTarget.unggahKandidat.batasTeks }}</template>.
                        </p>
                        <p class="plw-hasilk__ket">
                            <i class="bi bi-lock-fill"></i>
                            <span>
                                {{ hadirTarget.jadwal?.modeNama || 'Mandiri' }}: hasil &amp; kwitansi diunggah kandidat sendiri lewat portal — tim tidak mengunggah atau mengganti berkas ini.
                                <template v-if="hadirTarget.unggahKandidat?.terkirim"> Dikirim kandidat {{ tglId(hadirTarget.unggahKandidat.terkirim) }}.</template>
                                <template v-else-if="(hadirTarget.berkasKandidat || []).length"> Kandidat belum menekan Kirim — berkasnya masih bisa ia ubah.</template>
                            </span>
                        </p>
                    </div>
                    <BerkasAktivitas
                        v-else
                        :sub-tes-id="hadirTarget?.id || ''"
                        :awal="hadirTarget?.berkas || []"
                        :label="hadirTarget?.labelBerkas || 'Berkas hasil / lampiran penilaian'"
                        @berubah="tandaiBerkasBerubah"
                        @lihat="bukaDok"
                    />
                    <p v-if="hadirTarget?.petunjukBerkas && !hasilDariKandidat(hadirTarget)" class="plw-fld__hint">
                        <i class="bi bi-info-circle"></i> {{ hadirTarget.petunjukBerkas }}
                    </p>
                </template>
            </template>

            <!-- Penyunting yang sama dengan cabang "hadir" di modal ini.
                 Dulu satu cabang memakai penyunting dan cabang sebelahnya
                 textarea polos — dua catatan yang berakhir di kolom yang sama,
                 lahir dengan dua wajah berbeda tergantung tombol mana yang
                 ditekan sepuluh detik sebelumnya. -->
            <div v-else class="plw-fld">
                <label class="plw-fld__lbl">Alasan <small>opsional</small></label>
                <EditorQuill
                    v-model="hadirCatatanHtml"
                    ringkas
                    placeholder="Boleh dikosongkan — mis. sakit, minta jadwal ulang"
                />
            </div>
        </ConfirmModal>

        <!-- ALASAN PENOLAKAN — wajib, dan itu sebabnya ia punya jendela sendiri.

             "Kandidat menolak" tanpa satu kalimat pun tidak bisa ditindaklanjuti:
             menolak karena keberatan diperiksa catatan hukumnya berbeda jauh dari
             menolak karena nomornya salah sambung — yang kedua semestinya
             dihubungi ulang, bukan digugurkan. -->
        <ConfirmModal
            :show="tolakShow"
            :busy="sibuk"
            danger
            icon="bi-shield-slash"
            title="Kandidat Menolak Diperiksa"
            :subtitle="tolakTarget ? `${tolakTarget.label} — ${detailKandidat?.pelamar || ''}` : ''"
            confirm-label="Catat Penolakan"
            :confirm-disabled="!tolakAlasan.trim()"
            form-mode
            @cancel="tolakShow = false"
            @confirm="kirimTolakPeriksa"
        >
            <div class="plw-fld">
                <label class="plw-fld__lbl" for="tolak-alasan">Alasan penolakan <b>*</b></label>
                <input
                    id="tolak-alasan" v-model="tolakAlasan" type="text" maxlength="200" class="plw-inp"
                    placeholder="mis. Keberatan catatan hukumnya diperiksa"
                    @keyup.enter="tolakAlasan.trim() && kirimTolakPeriksa()"
                />
                <p class="plw-note is-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Tersimpan sebagai riwayat aktivitas ini. Pemeriksaan tidak boleh dijalankan, dan hasilnya tidak bisa disimpulkan bersih.</span>
                </p>
            </div>
        </ConfirmModal>

        <!-- ══ PHONE SCREENING ═══════════════════════════════════════════════

             Jendela SENDIRI, bukan menumpang "Catat Hasil". Dua alasan:

             1. Urutan kerjanya berbeda. Skrining dijalankan SAMBIL menelepon
                dan berlangsung belasan menit; hasil tahapnya dicatat sesudah
                kesimpulannya ada. Menyatukan keduanya memaksa orang menutup
                keputusan tahap padahal wawancaranya baru mulai.

             2. Isinya bisa 50 pertanyaan. Lebar jendela konfirmasi — bahkan
                yang "lg" — tidak cukup, dan tiap pilihan jadi menumpuk satu
                per baris. -->
        <AdminModal
            :show="skrShow"
            size="full"
            icon="bi-telephone-inbound"
            title="Phone Screening"
            :subtitle="skrTarget ? `${skrTarget.label} — ${detailKandidat?.pelamar || ''}` : ''"
            :busy="skrBusy"
            @close="tutupSkrining"
        >
            <PanelSkrining
                v-if="skrIsi"
                ref="panelSkr"
                :key="skrTarget?.id"
                :sub-tes-id="skrTarget?.id"
                :isi="skrIsi"
                :petunjuk="skrIsi.petunjuk || ''"
                :nomor-akun="detailKandidat?.hp || ''"
                @perbarui="perbaruiSkrining"
                @selesaikan="mintaSelesaiSkrining"
                @buka-kunci="skrBukaKunciShow = true"
            />
            <div v-else class="plw-skrmuat">Memuat kuesioner…</div>

            <template #footer>
                <button type="button" class="wca-btn wca-btn--ghost" @click="tutupSkrining">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
            </template>
        </AdminModal>

        <!-- Menyelesaikan sesi MENGUNCINYA. Yang dikunci adalah angka yang
             mungkin sudah dipakai memutuskan nasib orang, jadi konfirmasinya
             menyebut apa yang terjadi sesudahnya — bukan sekadar "yakin?". -->
        <ConfirmModal
            :show="skrSelesaiShow"
            :danger="false"
            icon="bi-check2-circle"
            title="Selesaikan sesi skrining?"
            confirm-label="Ya, Selesaikan"
            confirm-icon="bi-check2-circle"
            note="Skor akhir dihitung lalu sesi dikunci. Sesudah ini aktivitas skriningnya baru bisa ditutup dan tahapnya diputus. Menyuntingnya lagi menuntut buka kunci, dan pembukaannya tercatat di log."
            :busy="skrBusy"
            @cancel="skrSelesaiShow = false"
            @confirm="selesaikanSkrining"
        />

        <ConfirmModal
            :show="skrBukaKunciShow"
            form-mode
            icon="bi-unlock"
            title="Buka Kunci Sesi Skrining"
            subtitle="Alasannya dicatat di log dan tidak bisa dihapus"
            confirm-label="Buka Kunci"
            confirm-icon="bi-unlock"
            :confirm-disabled="skrAlasan.trim().length < 5"
            :busy="skrBusy"
            @cancel="skrBukaKunciShow = false; skrAlasan = ''"
            @confirm="bukaKunciSkrining"
        >
            <div class="plw-fld">
                <label class="plw-fld__lbl">Alasan membuka kunci <small>wajib</small></label>
                <textarea
                    v-model="skrAlasan"
                    class="plw-inp plw-inp--ta"
                    rows="3"
                    placeholder="mis. Salah pilih jawaban pertanyaan gaji, kandidat mengoreksi lewat telepon susulan."
                    maxlength="500"
                ></textarea>
            </div>
        </ConfirmModal>

        <!-- Catat hasil sub-tes MANUAL (wawancara/FGD) — masuk mesin keputusan yang sama. -->
        <!-- PEMERIKSAAN BUTUH RUANG LEBIH.

             Jendela ini semula seukuran konfirmasi biasa. Untuk aktivitas
             pemeriksaan isinya jauh lebih banyak — daftar komponen atau
             narasumber, borang alamat & kontak, dua editor — dan semuanya
             berdesakan di lebar yang dirancang untuk satu pertanyaan. -->
        <ConfirmModal
            :show="catatShow"
            :danger="false"
            icon="bi-pencil-square"
            title="Catat Hasil Aktivitas"
            :subtitle="catatTarget ? `${catatTarget.label} — ${detailKandidat?.pelamar || ''}` : ''"
            confirm-label="Simpan Hasil"
            :size="catatTarget?.pemeriksaan ? 'lg' : ''"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanCatat"
            form-mode
            @confirm="konfirmCatat"
            @cancel="tutupModalAktivitas('catatShow')"
        >
            <!-- Hanya aktivitas yang DIKERJAKAN TIM yang sampai ke sini. Ujian
                 online tak punya tombol ini — nilainya datang dari HCLearn — dan
                 MCU juga tidak: hasil pemeriksaan kesehatan dicatat langsung di
                 jendela keputusan, bersama berkas dari kliniknya. -->
            <div class="plw-fld">
                <label class="plw-fld__lbl">Hasil <b v-if="catatTarget && catatTarget.peran !== 'INFORMATIF'">*</b></label>
                <div v-if="catatTarget && catatTarget.peran !== 'INFORMATIF'" class="plw-seg">
                    <button type="button" class="plw-seg__b is-ok" :class="{ 'is-on': catatHasil === 'LULUS' }" @click="catatHasil = 'LULUS'">
                        <i class="bi bi-check-lg"></i> Lulus
                    </button>
                    <button type="button" class="plw-seg__b is-no" :class="{ 'is-on': catatHasil === 'GAGAL' }" @click="catatHasil = 'GAGAL'">
                        <i class="bi bi-x-lg"></i> Gagal
                    </button>
                </div>
                <p v-else class="plw-note is-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Aktivitas informatif — cukup nilai/catatan, tidak menentukan lulus.</span>
                </p>
            </div>

            <!-- BIDANG NILAI MENGIKUTI MODE PENILAIAN aktivitas ini, sama seperti
                 jendela kehadiran di atas.

                 Sebelumnya kotak "Nilai 0 – 1000" muncul di SETIAP aktivitas, apa pun
                 jenisnya — termasuk Reference Check dan Background Check, yang memang
                 tidak diangkakan di mana pun. Memaksakan angka pada pemeriksaan hanya
                 melahirkan angka karangan, dan angka karangan terbaca seolah hasil
                 ukur oleh orang berikutnya yang membaca rapor ini.

                 Yang menentukan `tipe` dari master (NONE/ANGKA/TEKS), bukan kodenya —
                 mode baru di master tidak menuntut layar ini ikut diubah. -->
            <div class="plw-fld__row">
                <div v-if="catatTarget?.penilaian?.tipe === 'ANGKA'" class="plw-fld">
                    <label class="plw-fld__lbl" for="catat-nilai">
                        {{ catatTarget.penilaian.nama }}
                        <small>0 – {{ catatTarget.penilaian.maks || 1000 }}</small>
                    </label>
                    <input
                        id="catat-nilai" v-model.number="catatNilai" type="number"
                        min="0" :max="catatTarget.penilaian.maks || 1000" step="0.01"
                        class="plw-inp" placeholder="mis. 85"
                    />
                </div>

                <div v-else-if="catatTarget?.penilaian?.tipe === 'TEKS'" class="plw-fld">
                    <label class="plw-fld__lbl">{{ catatTarget.penilaian.nama }}</label>
                    <div class="plw-opts">
                        <button
                            v-for="o in catatTarget.penilaian.opsi" :key="o"
                            type="button" class="plw-opt-card" :class="{ 'is-on': catatNilaiTeks === o }"
                            @click="catatNilaiTeks = catatNilaiTeks === o ? '' : o"
                        >
                            <span class="plw-opt-card__dot"><i class="bi bi-tag-fill"></i></span>
                            <span class="plw-opt-card__txt"><b>{{ o }}</b></span>
                        </button>
                    </div>
                </div>

                <div class="plw-fld">
                    <label class="plw-fld__lbl">Aktivitas</label>
                    <div class="plw-inp is-static">{{ catatTarget?.tipeNama || '—' }}</div>
                </div>
            </div>

            <div v-if="(catatTarget?.berkasKandidat || []).length" class="plw-kirimbox">
                <span class="plw-kirimbox__lbl"><i class="bi bi-inbox-fill"></i> Diserahkan kandidat</span>
                <a
                    v-for="b in catatTarget.berkasKandidat" :key="b.id"
                    :href="b.url" target="_blank" rel="noopener" class="plw-test__kirimfile" :title="b.nama"
                >
                    <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                    {{ b.nama }}
                </a>
            </div>
            <p v-else-if="catatTarget?.unggahKandidat?.wajib" class="plw-note is-err">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Kandidat <b>belum mengunggah</b> berkas yang diwajibkan untuk aktivitas ini.</span>
            </p>

            <!-- LABEL & CONTOHNYA MILIK TIPE TAHAPNYA SENDIRI.

                 Dulu keduanya tetap: "Catatan Penilai" dengan contoh "hasil FGD baik".
                 Di layar Reference Check, contoh itu menyuruh petugas menuliskan
                 kesan FGD atas sebuah percakapan telepon dengan mantan atasan. Label
                 dan petunjuknya sudah lama tersedia di Master Tipe Tahap — hanya
                 belum pernah dipakai di jendela ini. -->
            <!-- ══ PEMERIKSAAN: TEMUAN DICATAT SATUAN DEMI SATUAN ══

                 Background/reference check tidak punya "hasil" tunggal yang bisa
                 ditulis dalam satu kotak. Panel ini menyimpan tiap komponen &
                 narasumber sendiri-sendiri TANPA menutup aktivitasnya — sebab
                 verifikasi ijazah bisa selesai Kamis sementara riwayat kerja
                 masih menunggu. Yang menutup tetap tombol Simpan Hasil. -->
            <PanelPemeriksaan
                v-if="catatTarget?.pemeriksaan"
                :sub-tes-id="catatTarget.id"
                :data="catatTarget.pemeriksaan"
                :berkas="catatTarget.berkas || []"
                @perbarui="perbaruiPemeriksaan"
                @berkas-berubah="perbaruiBerkasAktivitas"
            />

            <!-- KESIMPULAN PEMERIKSAAN — tiga nilai, dan yang tengah disengaja.

                 Tanpa "Perlu pertimbangan", temuan kecil (selisih satu bulan masa
                 kerja) memaksa admin memilih antara mengabaikannya diam-diam atau
                 menggugurkan orang. Yang pertama tidak meninggalkan jejak. -->
            <div v-if="catatTarget?.pemeriksaan" class="plw-fld">
                <label class="plw-fld__lbl">Kesimpulan pemeriksaan <small>opsional</small></label>
                <div class="plw-opts">
                    <button
                        v-for="a in catatTarget.pemeriksaan.pilihanAdjudikasi" :key="a"
                        type="button" class="plw-opt-card" :class="{ 'is-on': catatAdjudikasi === a }"
                        @click="catatAdjudikasi = catatAdjudikasi === a ? '' : a"
                    >
                        <span class="plw-opt-card__dot"><i class="bi bi-flag-fill"></i></span>
                        <span class="plw-opt-card__txt"><b>{{ labelAdjudikasi(a) }}</b></span>
                    </button>
                </div>
            </div>

            <div class="plw-fld">
                <label class="plw-fld__lbl">
                    {{ catatTarget?.labelCatatan || 'Catatan Penilai' }} <small>opsional</small>
                    <span class="plw-lihat is-internal">
                        <i class="bi bi-eye-slash-fill"></i> HANYA TIM
                    </span>
                </label>
                <EditorQuill
                    v-model="catatCatatanHtml"
                    ringkas
                    :upload-url="URL_GAMBAR"
                    :upload-data="{ subTesId: catatTarget?.id }"
                    :placeholder="contohCatatan"
                    hint="Bisa diberi format & gambar. Catatan ini INTERNAL — kandidat tidak melihatnya."
                />
            </div>

            <!-- Lembar penilaian yang dipindai berlabuh pada AKTIVITAS ini,
                 bukan pada tahapnya — di tahap campuran, berkas setingkat tahap
                 tak bisa dibedakan antara hasil DISC dan hasil wawancara. -->
            <!-- HASIL DIUNGGAH KANDIDAT (MCU mandiri): kandidatlah yang
                 memegang hasil & kwitansinya — berkasnya masuk lewat
                 portal. Kotak unggah tim diganti keterangan ini; server
                 juga menolak unggahan tim (subTesBerkasUnggah). Vendor
                 tetap memakai kotak unggah biasa. -->
            <div v-if="hasilDariKandidat(catatTarget)" class="plw-hasilk">
                <div class="plw-hasilk__head">
                    <span class="plw-hasilk__title"><i class="bi bi-paperclip"></i> {{ catatTarget.labelBerkas || 'Berkas hasil' }}</span>
                    <span class="plw-hasilk__tag"><i class="bi bi-person-fill-lock"></i> diunggah kandidat</span>
                </div>
                <div v-if="(catatTarget.berkasKandidat || []).length" class="plw-hasilk__list">
                    <button
                        v-for="b in catatTarget.berkasKandidat" :key="b.id"
                        type="button" class="plw-test__kirimfile" :title="b.nama" @click="bukaDok(b)"
                    >
                        <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                        {{ b.nama }}
                    </button>
                </div>
                <p v-else class="plw-hasilk__kosong">
                    <i class="bi bi-hourglass-split"></i>
                    Kandidat belum mengunggah hasilnya<template v-if="catatTarget.unggahKandidat?.batasTeks"> — batas unggah {{ catatTarget.unggahKandidat.batasTeks }}</template>.
                </p>
                <p class="plw-hasilk__ket">
                    <i class="bi bi-lock-fill"></i>
                    <span>
                        {{ catatTarget.jadwal?.modeNama || 'Mandiri' }}: hasil &amp; kwitansi diunggah kandidat sendiri lewat portal — tim tidak mengunggah atau mengganti berkas ini.
                        <template v-if="catatTarget.unggahKandidat?.terkirim"> Dikirim kandidat {{ tglId(catatTarget.unggahKandidat.terkirim) }}.</template>
                        <template v-else-if="(catatTarget.berkasKandidat || []).length"> Kandidat belum menekan Kirim — berkasnya masih bisa ia ubah.</template>
                    </span>
                </p>
            </div>
            <BerkasAktivitas
                v-else
                :sub-tes-id="catatTarget?.id || ''"
                :awal="catatTarget?.berkas || []"
                label="Berkas hasil aktivitas"
                @berubah="tandaiBerkasBerubah"
                @lihat="bukaDok"
            />
        </ConfirmModal>

        <!-- TIDAK HADIR — WAJIB dikonfirmasi. Sekali diklik, aktivitasnya final
             dan pada tahap ber-mode gugur-otomatis kandidat langsung dinyatakan
             tidak lolos. Tanpa konfirmasi, satu klik keliru menutup lamaran orang
             dan hanya bisa dibatalkan lewat query manual di database. -->
        <ConfirmModal
            :show="absenShow"
            title="Tandai Tidak Hadir"
            :subtitle="absenTarget ? `${absenTarget.label} — ${detailKandidat?.pelamar || ''}` : ''"
            danger
            confirm-label="Ya, Tandai Tidak Hadir"
            :busy="sibuk"
            @confirm="konfirmTidakHadir"
            @cancel="absenShow = false"
        >
            <div class="plw-putus__ring is-gugur">
                <div class="plw-putus__row">
                    <i class="bi bi-person-x"></i>
                    <span><b>{{ absenTarget?.label }}</b> ditandai <b>tidak hadir</b> dan hasilnya menjadi final — tidak bisa diulang lewat layar ini.</span>
                </div>
                <div v-if="absenTarget?.online" class="plw-putus__row">
                    <i class="bi bi-cloud-slash"></i>
                    <span>Tahap berhenti menunggu hasil dari <b>HCLearn</b>. Bila kandidat sebenarnya mengerjakan tes, pakai <b>Sinkronkan</b>, bukan ini.</span>
                </div>
                <div v-if="absenTarget?.peran !== 'INFORMATIF'" class="plw-putus__row">
                    <i class="bi bi-exclamation-octagon"></i>
                    <span>Ini aktivitas <b>penentu</b>. Pada tahap yang gugurnya otomatis, kandidat <b>langsung dinyatakan tidak lolos</b>.</span>
                </div>
            </div>
            <!-- Catatan OPSIONAL, sama seperti modal kehadiran — dua jalur yang
                 menghasilkan keadaan sama tidak boleh menuntut hal berbeda. -->
            <div class="plw-fld">
                <label class="plw-fld__lbl" for="absen-catatan">Catatan <small>opsional</small></label>
                <textarea
                    id="absen-catatan" v-model="absenCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="500"
                    placeholder="Boleh dikosongkan — mis. tidak merespons undangan sampai batas waktu"
                ></textarea>
            </div>
        </ConfirmModal>

        <!-- EXPORT STUDIO — rakit & cetak BERKAS SELEKSI KANDIDAT.
             Inilah yang dibuka tombol "Cetak Berkas" di kepala drawer. Isinya
             dipilih per bagian dengan pratinjau PDF sungguhan di sebelahnya;
             modal cetak laporan di bawah tetap ada untuk laporan biodata. -->
        <ExportStudio
            :show="studioShow"
            :lamaran-id="studioLamaran"
            :nama="studioNama"
            :kode="studioKode"
            @close="studioShow = false"
            @unduh="studioDiantre"
        />

        <!-- CETAK LAPORAN KANDIDAT.
             Pilihan formulir hanya muncul bila kandidatnya MEMANG punya lebih
             dari satu — MT mengumpulkan data dua kali (pendaftaran, lalu
             kelengkapan data diri). Untuk yang cuma satu, menanyakannya berarti
             menyuruh admin memilih dari daftar berisi satu baris. -->
        <ConfirmModal
            :show="laporanShow"
            :danger="false"
            icon="bi-printer-fill"
            title="Cetak Laporan Kandidat"
            :subtitle="detailKandidat ? `${detailKandidat.pelamar} — ${detailKandidat.lamaranKode}` : ''"
            confirm-label="Buat & Unduh"
            :busy="sibuk || laporanMuat"
            form-mode
            @confirm="konfirmLaporan"
            @cancel="laporanShow = false"
        >
            <div class="plw-fld">
                <label class="plw-fld__lbl">Format berkas <b>*</b></label>
                <div class="plw-opts">
                    <!-- SATU FORMAT, DAN KARTUNYA TETAP DITAMPILKAN.
                         Bukan lagi pilihan, melainkan pemberitahuan: admin tahu
                         persis apa yang akan keluar sebelum menekan "Buat".
                         Menghilangkannya sama sekali membuat modal ini langsung
                         membuka daftar formulir tanpa pernah menyebut berkas apa
                         yang sedang dibuatnya. -->
                    <button type="button" class="plw-opt-card is-fmt" :class="{ 'is-on': laporanFormat === 'PDF' }" @click="laporanFormat = 'PDF'">
                        <span class="plw-opt-card__dot" style="color:#dc2626"><i class="bi bi-file-earmark-pdf-fill"></i></span>
                        <span class="plw-opt-card__txt">
                            <b>PDF — untuk dibaca &amp; diarsip</b>
                            <small>Profil kandidat berkop EVO Group: identitas, perjalanan seleksi, dan jawaban formulir. Siap dicetak.</small>
                        </span>
                        <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                    </button>
                    <!-- EXCEL DINONAKTIFKAN DARI PILIHAN — BUKAN DIHAPUS.
                         Job-nya (WcLaporanKandidatJob format 'XLSX' → buatExcel)
                         masih utuh dan masih dipanggil untuk berkas lama di panel
                         unduhan, jadi yang ditutup hanya pintunya. Kembalikan
                         dengan membuka komentar ini; tidak ada yang lain yang
                         perlu disentuh.
                    <button type="button" class="plw-opt-card is-fmt" :class="{ 'is-on': laporanFormat === 'XLSX' }" @click="laporanFormat = 'XLSX'">
                        <span class="plw-opt-card__dot" style="color:#15803d"><i class="bi bi-file-earmark-spreadsheet-fill"></i></span>
                        <span class="plw-opt-card__txt">
                            <b>Excel — untuk diolah</b>
                            <small>Tiga lembar bersaringan: profil, perjalanan per aktivitas, dan jawaban formulir. Siap dipivot.</small>
                        </span>
                        <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                    </button>
                    -->
                </div>
            </div>

            <!-- DAFTAR FORMULIR MASIH DIAMBIL.
                 Rangkanya ditampilkan, bukan ruang kosong: yang membuka modal
                 ini harus tahu bahwa masih ADA yang akan muncul di bawah pilihan
                 format — kalau tidak, ia menekan "Buat & Unduh" mengira sudah
                 tidak ada yang perlu dipilih. -->
            <div v-if="laporanMuat" class="plw-fld">
                <label class="plw-fld__lbl">Formulir yang disertakan</label>
                <div class="plw-load" style="justify-content: flex-start; padding: 0.35rem 0 0.6rem">
                    <span class="plw-spin"></span> Memuat daftar formulir…
                </div>
                <div class="plw-rangka"></div>
                <div class="plw-rangka plw-rangka--pendek"></div>
            </div>

            <div v-else-if="laporanOpsi.length > 1" class="plw-fld">
                <label class="plw-fld__lbl">Formulir yang disertakan</label>
                <label v-for="f in laporanOpsi" :key="f.id" class="plw-cek">
                    <input type="checkbox" :value="f.id" v-model="laporanFormulir" />
                    <span>
                        <b>{{ f.label }}</b>
                        <small>
                            Dikirim {{ tglId(f.waktuKirim) }}
                            <template v-if="f.utama"> · <b>terbaru</b></template>
                        </small>
                    </span>
                </label>
                <p class="plw-note is-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Bawaannya <b>yang terbaru</b>. Centang keduanya bila perlu membandingkan jawaban lama dengan pembaruannya.</span>
                </p>
            </div>
            <p v-else-if="!laporanMuat && laporanOpsi.length === 1" class="plw-note is-info">
                <i class="bi bi-info-circle-fill"></i>
                <span>Kandidat ini punya satu formulir (<b>{{ laporanOpsi[0].label }}</b>) — langsung disertakan.</span>
            </p>

            <!-- TIDAK ADA LAGI TOMBOL "UNDUH" DI SINI.
                 Dulu: tekan "Buat Laporan" → tunggu di dalam modal → muncul
                 tautan → tekan lagi. Dua kali menekan untuk satu maksud, dan
                 modal yang harus dijaga tetap terbuka selama menunggu. Sekarang
                 modalnya menutup begitu permintaan terkirim, kemajuannya pindah
                 ke panel unduhan di pojok, dan berkasnya tersimpan sendiri
                 begitu siap. -->
            <p v-if="laporanGagal" class="plw-note is-err">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>{{ laporanGagal }}</span>
            </p>
        </ConfirmModal>

        <!-- ══ PANEL UNDUHAN — pojok kanan bawah ══════════════════════════
             Membuat laporan berjalan di antrean dan bisa memakan puluhan
             detik. Menahan admin di dalam modal selama itu berarti ia tidak
             bisa mengerjakan apa pun; menutup modal tanpa jejak berarti ia
             tidak tahu permintaannya masih hidup. Panel ini menjawab keduanya:
             pekerjaannya terlihat, halamannya tetap bisa dipakai. -->
        <!-- ══ PANEL KEMAJUAN KEPUTUSAN MASSAL ══
             Cangkangnya PanelProses — rupa yang sama persis dengan panel antrean
             penjadwalan: pojok kanan bawah, kepala gelap, bilah kemajuan melekat.
             Isinya satu baris per PROGRAM, dan di dalamnya nama kandidat satu per
             satu berikut sebab kegagalan masing-masing.
        
             Angkanya dihitung server dari keadaan data yang sesungguhnya —
             "selesai" berarti tahapnya benar-benar sudah diputus, bukan "job-nya
             dilaporkan sukses". Menutup panel TIDAK membatalkan apa pun. -->
        <PanelProses
            :tampil="!!borong"
            :judul="borong && borong.jalan ? 'Memproses keputusan massal' : 'Keputusan massal selesai'"
            :ringkas="ringkasBorong"
            :nada="nadaBorong"
            :persen="persenBorong"
            :jalan="!!(borong && borong.jalan)"
            :grup="grupBorong"
            :dapat-ulang="!!(borong && !borong.jalan)"
            @tutup="tutupPanelBorong"
            @ulang="ulangiPutusGagal"
            @ulang-satu="(g, o) => ulangiPutusGagal(g, o)"
        />

        <!-- ══ PANEL UNDUHAN — cangkang yang sama, isi bergaya Drive ══
             Membuat laporan berjalan di antrean dan bisa memakan puluhan detik.
             Menahan admin di dalam modal selama itu berarti ia tidak bisa
             mengerjakan apa pun; menutup modal tanpa jejak berarti ia tidak tahu
             permintaannya masih hidup.
        
             Daftarnya TIDAK dijadikan grup: berkas tidak punya "kandidat di
             dalamnya", dan cincin kemajuan per baris memang bentuk yang sudah
             dikenal orang dari panel unduhan Drive. Yang disamakan cangkangnya —
             tempat, ukuran, dan warnanya — bukan isinya. -->
        <PanelProses
            :tampil="unduhan.length > 0"
            :judul="judulUnduhan"
            :ringkas="ringkasUnduhan"
            :nada="nadaUnduhan"
            :persen="persenUnduhan"
            :jalan="unduhanJalan"
            @tutup="bersihkanUnduhan"
        >
            <div class="plw-unduhan__list">
                <div v-for="u in unduhan" :key="u.id" class="plw-unduhan__row">
                    <!-- Cincin kemajuan: satu lingkaran mengatakan "berapa lagi" tanpa
                         perlu dibaca. Saat selesai ia berganti ceklis, bukan hilang —
                         hilang membuat orang ragu berkasnya benar-benar turun. -->
                    <span class="plw-ring" :class="'is-' + u.keadaan">
                        <svg viewBox="0 0 36 36" width="34" height="34">
                            <circle class="plw-ring__bg" cx="18" cy="18" r="15.5" />
                            <circle
                                class="plw-ring__val" cx="18" cy="18" r="15.5"
                                :stroke-dasharray="busur(u.persen)"
                            />
                        </svg>
                        <i v-if="u.keadaan === 'selesai'" class="bi bi-check-lg"></i>
                        <i v-else-if="u.keadaan === 'gagal'" class="bi bi-exclamation-lg"></i>
                        <b v-else>{{ bulat(u.persen) }}</b>
                    </span>
                    <span class="plw-unduhan__txt">
                        <b :title="u.nama">{{ u.nama }}</b>
                        <small :class="{ 'is-err': u.keadaan === 'gagal' }">{{ u.pesan }}</small>
                    </span>
                    <i class="bi plw-unduhan__ikon" :class="u.format === 'XLSX' ? 'bi-file-earmark-spreadsheet-fill is-xls' : 'bi-file-earmark-pdf-fill is-pdf'"></i>
                </div>
            </div>
        </PanelProses>

        <!-- JADWAL MASSAL. Satu jendela untuk seratus kandidat.
             Rinciannya sengaja dipampang di muka (siapa saja, jam berapa
             masing-masing) — undangan yang sudah terkirim tak bisa ditarik. -->
        <ConfirmModal
            :show="massalShow"
            :danger="false"
            icon="bi-calendar-plus"
            title="Jadwalkan Massal"
            :subtitle="`${terpilih.length} kandidat terpilih`"
            :confirm-label="aktivitasMassalDef?.jadwalPrivat ? 'Simpan Semua Jadwal' : 'Simpan &amp; Undang Semua'"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanMassal"
            form-mode
            @confirm="konfirmJadwalMassal"
            @cancel="massalShow = false"
        >
            <div class="plw-jdw">
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Aktivitas yang dijadwalkan <b>*</b></label>
                    <el-select v-model="massalAktivitas" filterable placeholder="Pilih aktivitas" style="width: 100%">
                        <el-option
                            v-for="a in aktivitasTerpilih" :key="a.label"
                            :value="a.label"
                            :label="`${a.label} — ${a.jumlah} kandidat`"
                        />
                    </el-select>
                    <p class="plw-note is-info" style="margin-top: 8px">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>
                            Hanya kandidat yang <b>punya aktivitas ini</b> dan belum selesai yang dijadwalkan.
                            Sisanya dilewati dan dilaporkan satu per satu — tidak ada yang gagal diam-diam.
                        </span>
                    </p>
                </div>

                <!-- Tombolnya DARI MASTER Mode Jadwal (bukan dua tombol tertulis
                     mati): MCU punya "Vendor" dan "Mandiri".
                     Bentuk yang menuntut nomor per kandidat (telepon) tidak
                     ditawarkan di sini — satu nomor untuk seratus orang tidak
                     pernah benar. -->
                <div v-if="modeMassalDipakai.length > 1" class="plw-fld">
                    <label class="plw-fld__lbl">{{ massalWajibLuring ? 'Cara pelaksanaan' : 'Metode' }} <b>*</b></label>
                    <div class="plw-seg">
                        <button
                            v-for="m in modeMassalDipakai" :key="m.kode"
                            type="button" class="plw-seg__b is-net"
                            :class="{ 'is-on': massalMode === m.kode }"
                            :title="m.deskripsi"
                            @click="massalMode = m.kode"
                        >
                            <i class="bi" :class="m.ikon"></i> {{ m.nama }}
                        </button>
                    </div>
                    <p v-if="massalModeDef?.deskripsi" class="plw-fld__hint">{{ massalModeDef.deskripsi }}</p>
                </div>
                <p v-else-if="massalWajibLuring" class="plw-note is-lock">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>Aktivitas ini hanya bisa dijalankan <b>tatap muka</b>, jadi metodenya tidak dapat diubah.</span>
                </p>

                <!-- BERRENTANG TANGGAL: satu rentang untuk semua kandidat, tanpa
                     pola jam — masing-masing datang kapan saja di dalamnya. -->
                <template v-if="massalModeDef?.batasWaktu">
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Rentang tanggal pemeriksaan <b>*</b></label>
                        <el-date-picker
                            v-model="massalRentang" type="daterange"
                            format="DD MMM YYYY" value-format="YYYY-MM-DD"
                            range-separator="–" start-placeholder="Tanggal pertama" end-placeholder="Tanggal terakhir"
                            style="width: 100%"
                            :disabled-date="sebelumHariIni"
                        />
                        <p class="plw-fld__hint">
                            Tanggal terakhir berlaku sampai pukul 23.59<template v-if="massalModeDef.batasHari">
                            — terisi otomatis {{ massalModeDef.batasHari }} hari dari hari ini</template>.
                            <template v-if="massalModeDef.unggahKandidat"> Itu juga batas kandidat mengunggah hasilnya.</template>
                        </p>
                    </div>
                    <p v-if="!massalModeDef.butuhTempat && massalModeDef.kalimatUndangan" class="plw-note is-info">
                        <i class="bi bi-hospital"></i>
                        <span><b>Tempat untuk kandidat:</b> {{ massalModeDef.kalimatUndangan }}</span>
                    </p>
                </template>

                <template v-else>
                <!-- DUA POLA WAKTU — keduanya nyata di lapangan, dan memilih
                     yang salah berarti seratus orang datang berbarengan untuk
                     wawancara yang hanya bisa satu-satu. -->
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Pola waktu <b>*</b></label>
                    <div class="plw-opts">
                        <button type="button" class="plw-opt-card" :class="{ 'is-on': massalPola === 'SERENTAK' }" @click="massalPola = 'SERENTAK'">
                            <span class="plw-opt-card__dot"><i class="bi bi-people-fill"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>Serentak — semua di jam yang sama</b>
                                <small>Untuk FGD, tes tertulis massal, atau briefing bersama. Semua {{ sasaranMassal.length || 'kandidat' }} dapat jam yang sama persis.</small>
                            </span>
                            <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                        </button>
                        <button type="button" class="plw-opt-card" :class="{ 'is-on': massalPola === 'BERGILIR' }" @click="massalPola = 'BERGILIR'">
                            <span class="plw-opt-card__dot"><i class="bi bi-hourglass-split"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>Bergilir — satu per satu</b>
                                <small>Untuk wawancara panel. Tiap kandidat dapat slotnya sendiri, berurutan dari jam mulai.</small>
                            </span>
                            <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                        </button>
                    </div>
                </div>

                <!-- WAKTU MULAI MELEBAR PENUH, DUA ANGKA TURUN KE BAWAHNYA.
                     Sebelumnya ketiganya dipaksa satu baris: tanggal separuh,
                     durasi dan jeda seperempat masing-masing. Di lebar
                     seperempat, "Durasi per orang" membungkus dua baris dan
                     kotak angkanya menyisakan ruang untuk dua digit — padahal
                     nilainya boleh sampai 480. -->
                <div class="plw-fld">
                    <label class="plw-fld__lbl">{{ massalPola === 'BERGILIR' ? 'Slot pertama mulai' : 'Waktu mulai' }} <b>*</b></label>
                    <el-date-picker
                        v-model="massalMulai" type="datetime"
                        format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss"
                        placeholder="Pilih tanggal & jam" style="width: 100%"
                        :disabled-date="sebelumHariIni"
                    />
                    <p v-if="massalMulaiLewat" class="plw-note is-err">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Waktu mulai itu sudah lewat — pilih tanggal &amp; jam yang akan datang.</span>
                    </p>
                </div>
                <div v-if="massalPola === 'BERGILIR'" class="plw-fld__row">
                    <div class="plw-fld">
                        <!-- SATUANNYA DISEBUT DI LABEL. Kotak angka tanpa satuan
                             membuat "30" terbaca sebagai apa saja — menit, orang,
                             atau nomor urut. -->
                        <label class="plw-fld__lbl">Durasi per orang <small>menit</small></label>
                        <el-input-number v-model="massalDurasi" :min="5" :max="480" :step="5" controls-position="right" style="width: 100%" />
                    </div>
                    <div class="plw-fld">
                        <!-- step 1, bukan 5: jeda yang wajar 0–10 menit, dan panah
                             yang melompat lima-lima memaksa admin mengetik manual
                             untuk angka yang paling sering dipakai. -->
                        <label class="plw-fld__lbl">Jeda antar sesi <small>menit</small></label>
                        <el-input-number v-model="massalJeda" :min="0" :max="240" :step="1" controls-position="right" style="width: 100%" />
                    </div>
                </div>

                <!-- ARITMATIKANYA DITULISKAN, BUKAN DISIMPULKAN SENDIRI.
                     Dua kotak angka bersebelahan tidak memberi tahu bahwa slot
                     bergeser sebesar JUMLAH keduanya. Yang membacanya mengira
                     jeda 2 menit berarti orang kedua masuk dua menit kemudian,
                     lalu mengirim undangan dengan jam yang tidak ia maksud —
                     dan undangan yang sudah terkirim tak bisa ditarik. -->
                <p v-if="massalPola === 'BERGILIR'" class="plw-note is-info">
                    <i class="bi bi-calculator-fill"></i>
                    <span>
                        Tiap orang <b>{{ massalDurasi }} menit</b><template v-if="massalJeda > 0">, lalu jeda <b>{{ massalJeda }} menit</b></template> —
                        jadi slotnya bergeser <b>tiap {{ Number(massalDurasi || 0) + Number(massalJeda || 0) }} menit</b>.
                        <template v-if="ritmeMassal.length">
                            Dari jam mulai: <b>{{ ritmeMassal.join(' → ') }}</b><template v-if="sasaranMassal.length > ritmeMassal.length"> → dan seterusnya</template>.
                        </template>
                        <template v-else>Pilih tanggal &amp; jam untuk melihat urutannya.</template>
                    </span>
                </p>
                <!-- Konfirmasi kehadiran: batas tiap kandidat = waktu mulai SESINYA. -->
                <p v-if="massalKonfAktif" class="plw-note is-info">
                    <i class="bi bi-calendar-check"></i>
                    <span>Tiap kandidat bisa mengonfirmasi kehadiran sampai <b>waktu mulai sesinya sendiri</b> — tidak ada batas terpisah yang perlu diisi.</span>
                </p>
                </template>

                <div v-if="massalModeDef?.butuhTautan" class="plw-fld">
                    <label class="plw-fld__lbl">Tautan pertemuan <b>*</b></label>
                    <input v-model="massalLink" type="url" class="plw-inp" placeholder="https://meet.google.com/..." maxlength="500" />
                </div>
                <template v-else-if="massalModeDef?.butuhLokasi">
                    <!-- Disaring menurut peruntukan aktivitas yang dipilih, sama
                         seperti jendela satuan. Tanpa ini, menjadwalkan MCU
                         massal bisa mengirim seratus orang ke kantor sekaligus. -->
                    <label class="plw-fld__lbl" style="margin-top: 12px">
                        {{ peruntukanMassal?.nama || 'Lokasi' }} <b>*</b>
                    </label>
                    <el-select v-model="massalLokasiId" filterable :placeholder="peruntukanMassal?.labelPilih || 'Pilih kantor / klinik'" style="width: 100%; margin-top: 6px" :loading="lokasiLoading">
                        <el-option v-for="l in lokasiUntukMassal" :key="l.id" :value="l.id" :label="l.nama + (l.kota ? ' — ' + l.kota : '')" />
                        <el-option v-if="peruntukanMassal?.izinkanLainnya" :value="LOKASI_LAIN" :label="peruntukanMassal?.labelLainnya || 'Lainnya'" />
                    </el-select>
                    <p v-if="!lokasiLoading && !lokasiUntukMassal.length" class="plw-note" style="margin-top: 8px">
                        <i class="bi bi-info-circle"></i>
                        <span>{{ peruntukanMassal?.labelKosong || 'Belum ada lokasi terdaftar untuk aktivitas ini.' }}</span>
                    </p>
                    <template v-if="massalLokasiId === LOKASI_LAIN">
                        <div class="plw-fld">
                            <label class="plw-fld__lbl">{{ peruntukanMassal?.labelNamaLainnya || 'Nama tempat' }} <b>*</b></label>
                            <input v-model="massalLokasiNama" type="text" class="plw-inp" placeholder="mis. RS Siti Khadijah" maxlength="200" />
                        </div>
                        <div class="plw-fld">
                            <label class="plw-fld__lbl">
                                {{ peruntukanMassal?.labelAlamatLainnya || 'Alamat' }}
                                <b v-if="peruntukanMassal?.wajibAlamatLainnya">*</b>
                                <small v-else>opsional</small>
                            </label>
                            <input v-model="massalLokasiAlamat" type="text" class="plw-inp" placeholder="mis. Jl. Demang Lebar Daun No. 7, Palembang" maxlength="500" />
                        </div>
                    </template>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Patokan / detail lokasi <small>opsional</small></label>
                        <input v-model="massalLokasi" type="text" class="plw-inp" placeholder="mis. Gedung B lantai 3, temui resepsionis" maxlength="300" />
                    </div>
                </template>
                <!-- VENDOR — sama dengan jendela satuan: nama vendor + cabangnya,
                     alamat & tautan Google Maps opsional. -->
                <template v-else-if="massalModeDef?.butuhTempat">
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">{{ massalModeDef.labelTempat || 'Nama tempat' }} <b>*</b></label>
                        <input v-model="massalLokasiNama" type="text" class="plw-inp" placeholder="mis. Laboratorium Klinik Pramita" maxlength="200" />
                    </div>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Cabang / lokasi yang melayani <small>opsional</small></label>
                        <EditorQuill
                            v-model="massalLokasiHtml"
                            ringkas
                            placeholder="mis. Semua cabang di Palembang: Pramita Sudirman, Pramita Demang. Datang 07.00–10.00."
                            hint="Kandidat memilih cabang terdekat dari daftar ini."
                        />
                    </div>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Alamat lengkap <small>opsional</small></label>
                        <input v-model="massalLokasiAlamat" type="text" class="plw-inp" placeholder="Bila hanya satu tempat" maxlength="500" />
                    </div>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Tautan Google Maps <small>opsional</small></label>
                        <input v-model="massalMapsUrl" type="url" class="plw-inp" placeholder="https://maps.app.goo.gl/..." maxlength="500" />
                        <p v-if="massalMapsSalah" class="plw-note is-err">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>Harus tautan Google Maps — salin lewat tombol <b>Bagikan</b> di Google Maps.</span>
                        </p>
                    </div>
                </template>

                <!-- SURAT PENGANTAR MASSAL — SATU surat untuk seluruh sasaran
                     (surat kolektif). Tanpa surat baru, tiap kandidat memakai
                     suratnya sendiri — yang belum punya membuat surat ini wajib. -->
                <div v-if="massalModeDef?.butuhSurat" class="plw-fld">
                    <label class="plw-fld__lbl">
                        {{ massalModeDef.labelSurat }}
                        <b v-if="massalButuhSurat">*</b><small v-else>opsional — semua sudah punya</small>
                        <span class="plw-surat__kuota" :class="{ 'is-penuh': massalSuratSisa <= 0 }">
                            {{ fmtUkuran(massalSuratTotal) }} dari {{ jadwalFitur.suratMaksMb || 5 }} MB
                        </span>
                    </label>
                    <div class="plw-surat">
                        <div v-for="(s, i) in massalSuratDaftar" :key="s.ref" class="plw-surat__baris">
                            <i class="bi bi-file-earmark-pdf-fill"></i>
                            <span class="plw-surat__nama">{{ s.nama }}</span>
                            <span class="plw-surat__ukuran">{{ fmtUkuran(s.ukuran) }}</span>
                            <small class="is-baru">untuk semua</small>
                            <button type="button" class="plw-surat__x" title="Hapus surat ini" @click="massalSuratDaftar.splice(i, 1)">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <span v-if="!massalSuratDaftar.length" class="plw-surat__kosong">
                            {{ massalPunyaSurat ? `${massalPunyaSurat} kandidat memakai suratnya masing-masing.` : 'Belum ada surat.' }}
                        </span>
                        <label class="plw-surat__pilih" :class="{ 'is-busy': suratUnggahDi === 'massal', 'is-mati': massalSuratSisa <= 0 }">
                            <input
                                type="file" hidden multiple :accept="suratAccept"
                                :disabled="!!suratUnggahDi || massalSuratSisa <= 0" @change="unggahSurat($event, 'massal')"
                            >
                            <i class="bi" :class="suratUnggahDi === 'massal' ? 'bi-arrow-repeat plw-putar' : 'bi-plus-lg'"></i>
                            {{ suratUnggahDi === 'massal' ? 'Mengunggah…' : 'Tambah surat (PDF)' }}
                        </label>
                    </div>
                    <p class="plw-fld__hint">
                        Hanya PDF · total paling besar {{ jadwalFitur.suratMaksMb || 5 }} MB. Surat di sini dipakai SEMUA kandidat
                        terpilih (menggantikan suratnya masing-masing) — untuk surat per orang, jadwalkan satu per satu.
                    </p>
                </div>

                <!-- INSTRUKSI — sama untuk seluruh kandidat, terisi dari Master
                     Alur; editornya langsung tampil untuk disunting. -->
                <div v-if="jadwalFitur.instruksi" class="plw-fld">
                    <label class="plw-fld__lbl">
                        Instruksi untuk kandidat <small>opsional</small>
                    </label>
                    <EditorQuill
                        v-model="massalCatatanHtml"
                        ringkas
                        placeholder="mis. Bawa KTP asli. Puasa 10 jam sebelum pemeriksaan."
                        hint="Terisi dari Master Alur — sama untuk semua kandidat terpilih. Tanpa tanggal/tempat: keduanya dirakit sistem di undangan."
                    />
                    <p v-if="massalInstruksiMuat" class="plw-fld__hint">
                        <i class="bi bi-arrow-repeat plw-putar"></i> Memuat instruksi dari Master Alur…
                    </p>
                </div>
                <div v-else class="plw-fld">
                    <label class="plw-fld__lbl">Catatan untuk kandidat <small>opsional</small></label>
                    <textarea v-model="massalCatatan" class="plw-inp plw-inp--ta" rows="2" maxlength="1000" placeholder="mis. Bawa KTP asli dan portofolio cetak."></textarea>
                </div>

                <div v-if="biayaMassal" class="plw-biaya" :class="{ 'is-off': !massalTampilBiaya }">
                    <div class="plw-biaya__head">
                        <span><i class="bi bi-cash-coin"></i> <b>Tampilkan informasi biaya ke kandidat</b></span>
                        <el-switch v-model="massalTampilBiaya" size="small" />
                    </div>
                    <textarea
                        v-if="massalTampilBiaya"
                        v-model="massalKalimatBiaya" class="plw-inp plw-inp--ta plw-biaya__teks" rows="3" maxlength="1000"
                        :placeholder="biayaMassal"
                    ></textarea>
                    <p v-else class="plw-biaya__isi">{{ massalKalimatBiaya || biayaMassal }}</p>
                    <small class="plw-biaya__hint">
                        {{ massalTampilBiaya
                            ? 'Ikut di email & portal semua kandidat terpilih.'
                            : 'Tidak ditampilkan ke kandidat terpilih.' }}
                    </small>
                </div>

                <!-- Sebagian terpilih SUDAH punya jadwal — menggantinya butuh
                     alasan, sama seperti jalur satuan. -->
                <div v-if="massalAdaJadwal && jadwalFitur.jejak" class="plw-fld">
                    <label class="plw-fld__lbl">Alasan perubahan <b>*</b></label>
                    <textarea
                        v-model="massalAlasan" class="plw-inp plw-inp--ta" rows="2" maxlength="500"
                        placeholder="mis. Vendor tutup sementara — seluruh angkatan dialihkan ke klinik terdekat."
                    ></textarea>
                    <p class="plw-fld__hint">
                        {{ massalAdaJadwal }} kandidat terpilih sudah menerima jadwal — alasannya tercatat di riwayat jadwal masing-masing.
                    </p>
                </div>

                <!-- PRATINJAU JAM. Undangan yang sudah terkirim tak bisa ditarik,
                     jadi siapa-dapat-jam-berapa harus terbaca SEBELUM dikirim,
                     bukan disimpulkan dari rumus di kepala. -->
                <div v-if="pratinjauMassal.length" class="plw-prev">
                    <div class="plw-prev__head">
                        <i class="bi bi-list-ol"></i>
                        <span>
                            Pratinjau — {{ pratinjauMassal.length }} kandidat akan
                            {{ massalModeDef?.batasWaktu ? 'menerima instruksi' : 'diundang' }}
                        </span>
                    </div>
                    <div class="plw-prev__list">
                        <div v-for="(p, i) in pratinjauMassal" :key="p.id" class="plw-prev__row">
                            <span class="plw-prev__no">{{ i + 1 }}</span>
                            <span class="plw-ell">{{ p.pelamar }}</span>
                            <b>{{ p.jam }}</b>
                        </div>
                    </div>
                    <p v-if="massalDilewati.length" class="plw-note is-err" style="margin-top: 8px">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><b>{{ massalDilewati.length }}</b> kandidat terpilih tidak punya aktivitas ini dan akan dilewati.</span>
                    </p>
                </div>

                <p v-if="aktivitasMassalDef?.jadwalPrivat" class="plw-note is-lock">
                    <i class="bi bi-eye-slash-fill"></i>
                    <span>
                        Aktivitas ini dijadwalkan untuk tim sendiri — <b>tidak ada undangan</b> yang dikirim
                        dan kandidat tidak melihatnya di portal.
                    </span>
                </p>
                <p v-else class="plw-note is-info">
                    <i class="bi bi-envelope-fill"></i>
                    <span>
                        Undangan dikirim ke email <b>tiap kandidat</b> begitu disimpan, berisi {{ massalModeDef?.batasWaktu ? 'rentang tanggal & rinciannya' : 'jam masing-masing' }} —
                        maksimal <b>{{ batasPutusMassal }} kandidat sekali kirim</b>, supaya alamat pengirim kami
                        tidak ditangguhkan penyedia email.
                    </span>
                </p>
                <!-- KELEBIHAN SASARAN — ditulis, dan tombol simpannya mati
                     (`bolehSimpanMassal`). Disebut dengan angka yang tepat:
                     yang dihitung adalah kandidat yang benar-benar diundang,
                     bukan yang tercentang — yang tidak punya aktivitas ini
                     sudah dilewati beberapa baris di atas. -->
                <p v-if="lebihanSasaranMassal" class="plw-note is-err">
                    <i class="bi bi-shield-exclamation"></i>
                    <span>
                        <b>{{ sasaranMassal.length }} kandidat</b> akan diundang — melebihi batas
                        {{ batasPutusMassal }} sekali kirim. Tutup jendela ini, lepas
                        {{ lebihanSasaranMassal }} centang, lalu jadwalkan sisanya di gelombang berikutnya.
                    </span>
                </p>
            </div>
        </ConfirmModal>

        <!-- TAHAN / LEPASKAN. Alasannya dipilih dari MASTER, bukan diketik
             bebas: inilah yang dihitung saat menjelaskan kenapa satu lowongan
             lama terisi, dan "nunggu user" / "menunggu user dept" / "blm dijawab
             user" adalah tiga tulisan untuk satu sebab yang sama. -->
        <!-- ═══ ULANGI TAHAP ═══
             Dialognya sengaja menyebutkan APA YANG AKAN TERJADI dalam kalimat
             penuh, bukan hanya nama tombol. Pengulangan membatalkan keputusan
             yang sudah diketuk orang lain — dan itu tidak boleh terjadi karena
             seseorang salah membaca satu label pendek. -->
        <ConfirmModal
            :show="ulangShow"
            :danger="true"
            icon="bi-arrow-counterclockwise"
            title="Ulangi Tahap"
            :subtitle="detailKandidat ? `${detailKandidat.pelamar} — putaran ke-${ulangPutaran}` : ''"
            confirm-label="Ya, Ulangi"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanUlang"
            form-mode
            @confirm="konfirmUlang"
            @cancel="ulangShow = false"
        >
            <div v-if="ulangMuat" class="plw-load" style="padding: 2rem 0"><span class="plw-spin"></span> Memuat daftar tahap…</div>

            <!-- Tidak ada satu pun tahap yang bisa diulang. Dijelaskan dengan
                 kalimat, bukan dibiarkan sebagai daftar pilihan kosong: "No data"
                 di dalam kotak pilihan terbaca sebagai halaman gagal memuat,
                 padahal jawabannya sederhana — kandidat ini memang belum
                 menjalani apa pun. -->
            <div v-else-if="!ulangTahapList.length" class="plw-putus__ring">
                <div class="plw-putus__row">
                    <i class="bi bi-info-circle"></i>
                    <span>
                        Belum ada tahap yang bisa diulang. Kandidat ini belum menjalani satu tahap pun —
                        yang sudah berjalan atau selesai saja yang punya jejak untuk dikembalikan.
                    </span>
                </div>
            </div>

            <template v-else>
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Kembalikan ke tahap</label>
                    <el-select v-model="ulangTujuan" placeholder="Pilih tahap" style="width: 100%">
                        <el-option
                            v-for="t in ulangTahapList"
                            :key="t.id"
                            :value="t.id"
                            :label="`${t.urutan}. ${t.label}${t.hasil ? ' — ' + t.hasil : ''}`"
                        />
                    </el-select>
                </div>

                <div class="plw-fld">
                    <label class="plw-fld__lbl">Cakupan</label>
                    <el-radio-group v-model="ulangCakupan">
                        <el-radio value="TAHAP">Tahap ini saja</el-radio>
                        <el-radio value="RANGKAIAN">Tahap ini dan seluruh tahap sesudahnya</el-radio>
                    </el-radio-group>
                </div>

                <div v-if="ulangTahapTerpilih" class="plw-putus__ring is-gagal">
                    <div class="plw-putus__row">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span v-if="ulangCakupan === 'RANGKAIAN'">
                            Tahap <b>{{ ulangTahapTerpilih.urutan }}</b> sampai <b>{{ ulangSampai }}</b> dikembalikan ke
                            keadaan bersih. Kandidat berdiri lagi di <b>{{ ulangTahapTerpilih.label }}</b>.
                        </span>
                        <span v-else>
                            Hanya tahap <b>{{ ulangTahapTerpilih.urutan }} — {{ ulangTahapTerpilih.label }}</b> yang
                            dikembalikan ke keadaan bersih.
                        </span>
                    </div>
                    <div class="plw-putus__row">
                        <i class="bi bi-archive"></i>
                        <span>
                            Status, hasil, nilai, jadwal, dan keputusan lamanya <b>diarsipkan</b> — tidak dihapus.
                            Berkas yang sudah diunggah tetap tersimpan sebagai riwayat putaran ini.
                        </span>
                    </div>
                    <div class="plw-putus__row">
                        <i class="bi bi-calendar-x"></i>
                        <span>Kandidat dikeluarkan dari sesi penjadwalan tahap tersebut; token & tautan ujian lamanya berhenti berlaku.</span>
                    </div>
                </div>

                <div class="plw-fld">
                    <label class="plw-fld__lbl">Alasan pengulangan <b>*</b></label>
                    <el-input
                        v-model="ulangAlasan"
                        type="textarea"
                        :rows="3"
                        maxlength="2000"
                        show-word-limit
                        placeholder="Mis. jadwal tes salah kirim — sesi 14.00 tertukar dengan gelombang berikutnya."
                    />
                    <small class="plw-fld__hint">
                        Wajib, minimal 10 huruf. Inilah satu-satunya jawaban atas "kenapa tahap ini diulang" saat
                        arsipnya dibaca berbulan-bulan kemudian.
                    </small>
                </div>

                <div class="plw-fld">
                    <el-checkbox v-model="ulangEmail">Kirim email pemberitahuan ke kandidat</el-checkbox>
                    <small class="plw-fld__hint">
                        Bawaannya mati. Nyalakan hanya bila pengulangan ini mengubah apa yang harus dikerjakan
                        kandidat — koreksi internal tidak perlu ia ketahui.
                    </small>
                </div>

                <div v-if="ulangRiwayat.length" class="plw-putus__ring">
                    <div class="plw-putus__row">
                        <i class="bi bi-clock-history"></i>
                        <span>
                            Kandidat ini sudah pernah diulang <b>{{ ulangRiwayat.length }}&times;</b>.
                            Terakhir: tahap {{ ulangRiwayat[0].dariUrutan }} oleh {{ ulangRiwayat[0].oleh }}.
                        </span>
                    </div>
                </div>
            </template>
        </ConfirmModal>

        <ConfirmModal
            :show="holdShow"
            :danger="false"
            :icon="holdNilai ? 'bi-pause-circle-fill' : 'bi-play-circle-fill'"
            :title="holdNilai ? 'Tahan Kandidat' : 'Lanjutkan Proses'"
            :subtitle="detailKandidat ? `${detailKandidat.pelamar} — tahap ${detailKandidat.tahap}` : ''"
            :confirm-label="holdNilai ? 'Ya, Tahan' : 'Ya, Lanjutkan'"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanHold"
            form-mode
            @confirm="konfirmHold"
            @cancel="holdShow = false"
        >
            <div class="plw-putus__ring" :class="holdNilai ? 'is-talent_pool' : 'is-lulus'">
                <div class="plw-putus__row">
                    <i class="bi" :class="holdNilai ? 'bi-pause-circle' : 'bi-play-circle'"></i>
                    <span v-if="holdNilai">
                        Kandidat <b>tetap di tahap ini</b> — tidak dipindah, tidak digugurkan.
                        Yang tertunda hanya keputusannya.
                    </span>
                    <span v-else>
                        Penahanan dilepas. Bila hasil tesnya sudah lengkap selama ditahan,
                        sistem langsung menyimpulkan tahap ini begitu Anda menekan Lanjutkan.
                    </span>
                </div>
                <div class="plw-putus__row">
                    <i class="bi bi-envelope-slash"></i>
                    <span><b>Tidak ada email atau WA</b> yang dikirim ke kandidat — ini catatan internal.</span>
                </div>
            </div>

            <template v-if="holdNilai">
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Alasan menahan <b>*</b></label>
                    <!-- Daftar & aturannya SELURUHNYA dari Master Alasan Hold. -->
                    <div class="plw-opts">
                        <button
                            v-for="a in alasanHold" :key="a.value"
                            type="button" class="plw-opt-card"
                            :class="{ 'is-on': holdAlasan === a.value }"
                            :style="holdAlasan === a.value ? { borderColor: a.warna, background: a.warna + '14' } : null"
                            @click="holdAlasan = a.value"
                        >
                            <span class="plw-opt-card__dot" :style="{ color: a.warna }"><i class="bi" :class="a.ikon || 'bi-pause'"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>{{ a.nama }}</b>
                                <small>{{ a.deskripsi }}</small>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="plw-fld">
                    <label class="plw-fld__lbl">
                        Keterangan
                        <b v-if="holdButuhCatatan">*</b>
                        <small v-else>opsional</small>
                    </label>
                    <EditorQuill
                        v-model="holdCatatanHtml"
                        ringkas
                        :upload-url="URL_GAMBAR"
                        :upload-data="{ tahapId: detailKandidat?.tahapId }"
                        placeholder="mis. Menunggu approval MPP dari Direktur — target minggu depan."
                        hint="Bisa diberi format & gambar. Tersimpan sebagai riwayat penahanan tahap ini."
                    />
                </div>
                <p v-if="holdButuhCatatan && !holdCatatanCukup" class="plw-note is-err">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>Alasan ini menuntut keterangan tambahan — tanpa itu, ia tidak menjelaskan apa pun saat ditinjau kembali.</span>
                </p>
            </template>

            <div v-else class="plw-fld">
                <label class="plw-fld__lbl">Catatan pelepasan <small>opsional</small></label>
                <EditorQuill
                    v-model="holdCatatanHtml"
                    ringkas
                    :upload-url="URL_GAMBAR"
                    :upload-data="{ tahapId: detailKandidat?.tahapId }"
                    placeholder="mis. Kuota sudah turun, proses dilanjutkan."
                />
            </div>
        </ConfirmModal>

        <!-- ═══ TAHAN / LANJUTKAN BANYAK SEKALIGUS ═══
             Dua mode, dan keduanya benar-benar dibutuhkan:

             SERAGAM  — satu peristiwa mengenai semua ("MPP belum turun").
                        Mengetik alasan yang sama dua belas kali bukan
                        ketelitian, cuma pekerjaan yang terbuang.
             SENDIRI  — satu angkatan bisa tertahan karena sebab berbeda-beda:
                        tiga menunggu kuota, dua menunggu user department, satu
                        menunggu kandidatnya menjawab. Memaksakan satu alasan
                        membuat lima dari enam catatan itu SALAH, dan justru
                        laporan "kenapa lowongan ini lama terisi" yang rusak. -->
        <ConfirmModal
            :show="hmShow"
            :danger="false"
            :icon="hmNilai ? 'bi-pause-circle-fill' : 'bi-play-circle-fill'"
            :title="hmNilai ? 'Tahan Beberapa Kandidat' : 'Lanjutkan Beberapa Kandidat'"
            :subtitle="`${hmTarget.length} kandidat terpilih`"
            :confirm-label="hmNilai ? `Ya, Tahan ${hmTarget.length} Kandidat` : `Ya, Lanjutkan ${hmTarget.length} Kandidat`"
            :busy="sibuk"
            :confirm-disabled="!bolehSimpanHoldMassal"
            :confirm-icon="hmNilai ? 'bi-pause-circle' : 'bi-play-circle'"
            form-mode
            size="xl"
            @confirm="konfirmHoldMassal"
            @cancel="hmShow = false"
        >
            <div class="plw-putus__ring" :class="hmNilai ? 'is-talent_pool' : 'is-lulus'">
                <div class="plw-putus__row">
                    <i class="bi" :class="hmNilai ? 'bi-pause-circle' : 'bi-play-circle'"></i>
                    <span v-if="hmNilai">
                        Semua kandidat ini <b>tetap di tahapnya</b> — tidak dipindah, tidak digugurkan.
                        Yang tertunda hanya keputusannya.
                    </span>
                    <span v-else>
                        Penahanan dilepas. Yang hasil tesnya sudah lengkap selama ditahan akan
                        langsung disimpulkan sistem begitu Anda menekan Lanjutkan.
                    </span>
                </div>
                <div class="plw-putus__row">
                    <i class="bi bi-envelope-slash"></i>
                    <span><b>Tidak ada email atau WA</b> yang dikirim — ini catatan internal.</span>
                </div>
            </div>

            <!-- Yang TIDAK bisa diproses disebutkan sebelum disimpan, bukan
                 dilaporkan sesudahnya. Admin berhak tahu bahwa dari 12 yang ia
                 centang, hanya 9 yang akan benar-benar tersentuh. -->
            <p v-if="hmDilewati.length" class="plw-note is-err" style="margin-bottom: 10px">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>
                    <b>{{ hmDilewati.length }}</b> kandidat terpilih dilewati —
                    {{ hmNilai ? 'sudah ditahan atau tahapnya sudah diputus' : 'memang tidak sedang ditahan' }}:
                    {{ hmDilewati.map((x) => x.pelamar).join(', ') }}.
                </span>
            </p>

            <template v-if="hmNilai">
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Cara mengisi alasan</label>
                    <div class="plw-opts plw-opts--row">
                        <button
                            type="button" class="plw-opt-card" :class="{ 'is-on': hmPola === 'SERAGAM' }"
                            @click="hmPola = 'SERAGAM'"
                        >
                            <span class="plw-opt-card__dot"><i class="bi bi-collection-fill"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>Sama untuk semua</b>
                                <small>Satu alasan &amp; satu keterangan dipakai {{ hmTarget.length }} kandidat.</small>
                            </span>
                            <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                        </button>
                        <button
                            type="button" class="plw-opt-card" :class="{ 'is-on': hmPola === 'SENDIRI' }"
                            @click="hmPola = 'SENDIRI'"
                        >
                            <span class="plw-opt-card__dot"><i class="bi bi-person-lines-fill"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>Beda per kandidat</b>
                                <small>Tiap orang punya alasan &amp; keterangannya sendiri.</small>
                            </span>
                            <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                        </button>
                    </div>
                </div>

                <!-- ── SERAGAM ── -->
                <template v-if="hmPola === 'SERAGAM'">
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">Alasan menahan <b>*</b></label>
                        <div class="plw-opts">
                            <button
                                v-for="a in alasanHold" :key="a.value"
                                type="button" class="plw-opt-card"
                                :class="{ 'is-on': hmAlasan === a.value }"
                                :style="hmAlasan === a.value ? { borderColor: a.warna, background: a.warna + '14' } : null"
                                @click="hmAlasan = a.value"
                            >
                                <span class="plw-opt-card__dot" :style="{ color: a.warna }"><i class="bi" :class="a.ikon || 'bi-pause'"></i></span>
                                <span class="plw-opt-card__txt">
                                    <b>{{ a.nama }}</b>
                                    <small>{{ a.deskripsi }}</small>
                                </span>
                            </button>
                        </div>
                    </div>
                    <div class="plw-fld">
                        <label class="plw-fld__lbl">
                            Keterangan
                            <b v-if="hmButuhCatatan">*</b>
                            <small v-else>opsional</small>
                        </label>
                        <EditorQuill
                            v-model="hmCatatanHtml"
                            ringkas
                            placeholder="mis. Menunggu approval MPP dari Direktur — target minggu depan."
                            hint="Tersimpan sebagai riwayat penahanan pada tahap SETIAP kandidat terpilih."
                        />
                    </div>
                    <p v-if="hmButuhCatatan && !hmCatatanCukup" class="plw-note is-err">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span>Alasan ini menuntut keterangan tambahan — tanpa itu, ia tidak menjelaskan apa pun saat ditinjau kembali.</span>
                    </p>
                </template>

                <!-- ── SENDIRI-SENDIRI ── -->
                <template v-else>
                    <!-- Jalan pintas: isi baris pertama, lalu tebarkan. Mode ini
                         paling sering dipakai untuk "sebagian besar sama, dua
                         orang berbeda" — memaksa mengetik ulang semuanya
                         membuat orang kembali memilih mode seragam yang keliru. -->
                    <div class="plw-hmbar">
                        <i class="bi bi-magic"></i>
                        <span>Isi baris pertama, lalu salin ke sisanya — yang perlu beda tinggal disunting.</span>
                        <button type="button" :disabled="!hmBaris[0]?.alasan" :onClick="!hmBaris[0]?.alasan ? null : sebarBarisPertama">
                            <i class="bi bi-arrow-down-up"></i> Salin ke semua
                        </button>
                    </div>

                    <div class="plw-hmlist">
                        <div v-for="(b, i) in hmBaris" :key="b.id" class="plw-hmrow" :class="{ 'is-kurang': barisHmKurang(b) }">
                            <div class="plw-hmrow__head">
                                <span class="plw-hmrow__n">{{ i + 1 }}</span>
                                <div style="flex: 1; min-width: 0">
                                    <b>{{ b.pelamar }}</b>
                                    <small>{{ b.posisi || '—' }} · tahap {{ b.tahap }}</small>
                                </div>
                                <span v-if="barisHmKurang(b)" class="plw-hmrow__warn">
                                    <i class="bi bi-exclamation-circle-fill"></i> {{ barisHmKurang(b) }}
                                </span>
                            </div>
                            <div class="plw-hmrow__isi">
                                <el-select
                                    v-model="b.alasan" filterable placeholder="Pilih alasan menahan"
                                    class="plw-hmrow__sel"
                                >
                                    <el-option
                                        v-for="a in alasanHold" :key="a.value"
                                        :value="a.value" :label="a.nama"
                                    />
                                </el-select>
                                <EditorQuill
                                    v-model="b.catatanHtml"
                                    ringkas mungil
                                    :placeholder="alasanButuhCatatan(b.alasan) ? 'Keterangan WAJIB untuk alasan ini…' : 'Keterangan (opsional)…'"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </template>

            <div v-else class="plw-fld">
                <label class="plw-fld__lbl">Catatan pelepasan <small>opsional, dipakai untuk semua</small></label>
                <EditorQuill
                    v-model="hmCatatanHtml"
                    ringkas
                    placeholder="mis. Kuota sudah turun, proses dilanjutkan."
                />
            </div>
        </ConfirmModal>

        <!-- ══ KEPUTUSAN MASSAL (LOLOS / TIDAK LOLOS) ══
             form-mode + danger=false + size: ketiganya wajib di sini.

             Tanpa `form-mode`, ConfirmModal memakai bentuk KALIMAT konfirmasi:
             isinya dibungkus <p> dan dirata-tengahkan. Borang ini berisi <div>,
             dan <div> di dalam <p> ditutup paksa oleh peramban — tata letaknya
             lepas dari kendali CSS, persis seperti yang terjadi pada mode
             "Beda per kandidat". Rata tengahnya juga menghapus garis baca
             borang: label, isian, dan bantuan tak lagi berbaris di kiri.

             Tanpa `danger=false`, kepala modal menampilkan tong sampah merah
             dan tombolnya pun bertong sampah — untuk tindakan yang sama sekali
             tidak menghapus apa pun. Ikonnya jadi janji yang keliru.

             Tanpa `size`, borang selebar konfirmasi memaksa dua kartu pilihan
             menumpuk dan tiap baris kandidat memanjang ke bawah. -->
        <ConfirmModal
            :show="pmShow"
            :busy="sibuk"
            :danger="false"
            size="xl"
            form-mode
            icon="bi-hammer"
            confirm-icon="bi-hammer"
            title="Keputusan untuk Beberapa Kandidat"
            :subtitle="`${pmTarget.length} kandidat terpilih`"
            :confirm-label="`Ya, Putuskan ${pmTarget.length} Kandidat`"
            :confirm-disabled="!bolehSimpanPutusMassal"
            @confirm="konfirmPutusMassal"
            @cancel="pmShow = false"
        >
            <div class="plw-putus__ring is-lulus">
                <div class="plw-putus__row">
                    <i class="bi bi-hammer"></i>
                    <span>Keputusan ini <b>menutup tahap</b> bagi setiap kandidat terpilih dan memindahkannya sesuai hasilnya.</span>
                </div>
                <div class="plw-putus__row">
                    <i class="bi bi-envelope-fill"></i>
                    <span>Email hasil dikirim <b>sesuai setelan masternya</b> — sama seperti keputusan satuan.</span>
                </div>
                <!-- BATASNYA DISEBUT DI SINI, bukan hanya saat dilanggar.
                     Admin yang tahu jatahnya lima akan mencentang lima; yang
                     baru diberitahu setelah mencentang tiga puluh harus
                     membatalkan dua puluh lima centang yang sudah ia timbang
                     satu per satu. -->
                <div class="plw-putus__row">
                    <i class="bi bi-shield-check"></i>
                    <span>
                        Maksimal <b>{{ batasPutusMassal }} kandidat sekali kirim</b>. Tiap keputusan menerbitkan
                        satu email; mengirim puluhan serempak dari satu alamat membuat penyedia email
                        menangguhkan pengirim kami — dan undangan wawancara ikut berhenti. Sisanya dikirim
                        di gelombang berikutnya, tepat setelah yang ini selesai.
                    </span>
                </div>
            </div>

            <!-- Jalan buntu yang tidak seharusnya terjadi — tombolnya sudah mati
                 sejak di bilah. Tetap ditulis: modal ini bertahan melewati
                 refresh halaman, jadi ia bisa terbuka membawa pilihan yang dibuat
                 sebelum batasnya berlaku. -->
            <p v-if="pmTarget.length > batasPutusMassal" class="plw-note is-err" style="margin-bottom: 10px">
                <i class="bi bi-shield-exclamation"></i>
                <span>
                    <b>{{ pmTarget.length }} kandidat</b> melebihi batas {{ batasPutusMassal }} sekali kirim.
                    Tutup jendela ini, lepas {{ pmTarget.length - batasPutusMassal }} centang, lalu putuskan
                    yang {{ batasPutusMassal }} ini dulu.
                </span>
            </p>

            <!-- Yang TIDAK bisa diproses disebut SEBELUM disimpan. Admin berhak
                 tahu bahwa dari 12 yang ia centang, hanya 9 yang tersentuh. -->
            <p v-if="pmDilewati.length" class="plw-note is-err" style="margin-bottom: 10px">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>
                    <b>{{ pmDilewati.length }}</b> kandidat terpilih dilewati — sedang ditahan
                    atau tahapnya sudah diputus:
                    {{ pmDilewati.map((x) => x.pelamar).join(', ') }}.
                </span>
            </p>

            <div class="plw-fld">
                <label class="plw-fld__lbl">Cara mengambil keputusan</label>
                <div class="plw-opts plw-opts--row">
                    <button
                        type="button" class="plw-opt-card" :class="{ 'is-on': pmPola === 'SERAGAM' }"
                        @click="pmPola = 'SERAGAM'"
                    >
                        <span class="plw-opt-card__dot"><i class="bi bi-collection-fill"></i></span>
                        <span class="plw-opt-card__txt">
                            <b>Sama untuk semua</b>
                            <small>Satu keputusan &amp; satu alasan dipakai {{ pmTarget.length }} kandidat.</small>
                        </span>
                        <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                    </button>
                    <button
                        type="button" class="plw-opt-card" :class="{ 'is-on': pmPola === 'SENDIRI' }"
                        @click="pmPola = 'SENDIRI'"
                    >
                        <span class="plw-opt-card__dot"><i class="bi bi-person-lines-fill"></i></span>
                        <span class="plw-opt-card__txt">
                            <b>Beda per kandidat</b>
                            <small>Sebagian lolos, sebagian tidak — dalam satu kali simpan.</small>
                        </span>
                        <span class="plw-opt-card__cek"><i class="bi bi-check-lg"></i></span>
                    </button>
                </div>
            </div>

            <template v-if="pmPola === 'SERAGAM'">
                <div class="plw-fld">
                    <label class="plw-fld__lbl">Keputusan <b>*</b></label>
                    <div class="plw-opts">
                        <button
                            v-for="h in pmHasilOpsi" :key="h.kode"
                            type="button" class="plw-opt-card"
                            :class="{ 'is-on': pmHasil === h.kode }"
                            :style="pmHasil === h.kode ? { borderColor: h.warna, background: h.warna + '14' } : null"
                            @click="pmHasil = h.kode"
                        >
                            <span class="plw-opt-card__dot" :style="{ color: h.warna }"><i class="bi" :class="h.ikon || 'bi-dot'"></i></span>
                            <span class="plw-opt-card__txt">
                                <b>{{ h.labelTombol || h.nama }}</b>
                                <small>{{ h.deskripsi }}</small>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="plw-fld">
                    <label class="plw-fld__lbl">
                        Catatan Keputusan
                        <small v-if="pmButuhCatatan">alasan wajib di tab internal</small>
                        <small v-else>opsional, dipakai untuk semua kandidat terpilih</small>
                    </label>
                    <CatatanKeputusan
                        v-model:tab="pmTabCatatan"
                        v-model:eksternal="pmCatatanEksternalHtml"
                        v-model:internal="pmCatatanHtml"
                        :eksternal-siap="!!catatanTahap.eksternalSiap"
                        :label-internal="pmButuhCatatan ? 'Alasan (internal)' : 'Catatan Internal'"
                        :wajib-internal="pmButuhCatatan"
                        placeholder-internal="mis. Hasil psikotes di bawah ambang batas yang ditetapkan panel."
                    />
                    <p v-if="pmButuhCatatan && !pmCatatanCukup" class="plw-note is-err">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span>Keputusan ini menuntut alasan yang benar-benar ditulis.</span>
                    </p>
                </div>
            </template>

            <template v-else>
                <!-- `plw-hmbar`, bukan `plw-hmhint`: kelas kedua itu tidak pernah
                     punya satu baris CSS pun, jadi bilahnya tampil sebagai teks
                     telanjang dengan tombol menggantung di tengah. -->
                <div class="plw-hmbar">
                    <i class="bi bi-magic"></i>
                    <span>Isi baris pertama, lalu salin ke sisanya — yang perlu beda tinggal disunting.</span>
                    <button type="button" :disabled="!pmBaris[0]?.hasil" :onClick="!pmBaris[0]?.hasil ? null : sebarPutusBarisPertama">
                        <i class="bi bi-arrow-down-up"></i> Salin ke semua
                    </button>
                </div>

                <div class="plw-hmlist">
                    <div v-for="(b, i) in pmBaris" :key="b.id" class="plw-hmrow" :class="{ 'is-kurang': barisPmKurang(b) }">
                        <div class="plw-hmrow__head">
                            <span class="plw-hmrow__n">{{ i + 1 }}</span>
                            <div style="flex: 1; min-width: 0">
                                <b>{{ b.pelamar }}</b>
                                <small>{{ b.posisi || '—' }} · tahap {{ b.tahap }}</small>
                            </div>
                            <span v-if="barisPmKurang(b)" class="plw-hmrow__warn">
                                <i class="bi bi-exclamation-circle-fill"></i> {{ barisPmKurang(b) }}
                            </span>
                        </div>
                        <!-- Keputusan & alasannya berdampingan, tidak bertumpuk:
                             yang dibaca ulang berbulan-bulan kemudian adalah
                             pasangan keduanya, dan di lebar xl keduanya muat. -->
                        <div class="plw-hmrow__isi">
                            <el-select v-model="b.hasil" placeholder="Pilih keputusan" class="plw-hmrow__sel">
                                <el-option
                                    v-for="h in pmHasilOpsi" :key="h.kode"
                                    :value="h.kode" :label="h.labelTombol || h.nama"
                                />
                            </el-select>
                            <!-- Penyunting, bukan textarea. Catatan keputusan
                                 dibaca kembali di rapor tahap bersama catatan
                                 satuan yang memang ber-HTML; kalau yang massal
                                 lahir sebagai teks polos, dua catatan untuk hal
                                 yang sama tampil dengan dua wajah berbeda. -->
                            <CatatanKeputusan
                                v-model:tab="b.tabCatatan"
                                v-model:eksternal="b.catatanEksternalHtml"
                                v-model:internal="b.catatanHtml"
                                mungil
                                :eksternal-siap="!!catatanTahap.eksternalSiap"
                                :label-internal="hasilKeputusan.find((h) => h.kode === b.hasil)?.butuhAlasan ? 'Alasan' : 'Internal'"
                                :wajib-internal="!!hasilKeputusan.find((h) => h.kode === b.hasil)?.butuhAlasan"
                                :placeholder-internal="hasilKeputusan.find((h) => h.kode === b.hasil)?.butuhAlasan
                                    ? 'Alasan WAJIB untuk keputusan ini…'
                                    : 'Catatan tim (opsional)…'"
                                placeholder-eksternal="Pesan untuk kandidat ini (opsional)…"
                            />
                        </div>
                    </div>
                </div>
            </template>

            <!-- ══ SEKALIAN CATAT HASIL AKTIVITAS ══
                 Gerbang ketuntasan menuntut tiap aktivitas punya hasil sebelum
                 tahapnya boleh diputus. Tanpa centang ini, memutus lima puluh orang
                 berarti menekan "Tidak Lulus" lima puluh kali DULU — putaran kerja
                 yang tidak menambah satu pun informasi di luar keputusan massal yang
                 sedang diambil.
            
                 Tetap sebuah centang, bukan perilaku diam-diam: yang ditulis adalah
                 catatan penilaian atas nama admin, dan ia berhak mematikannya untuk
                 aktivitas yang memang ingin ia nilai sendiri. -->
            <label class="plw-auto" :class="{ 'is-on': pmCatatAktivitas }">
                <input v-model="pmCatatAktivitas" type="checkbox">
                <span>
                    <b>Sekalian catat hasil aktivitas yang belum tercatat</b>
                    <small>
                        Aktivitas yang belum punya hasil diisi mengikuti keputusan ini, dan yang sudah
                        berjadwal ditandai <b>hadir</b>. Yang bernilai angka, ujian online yang hasilnya
                        belum masuk, MCU, dan yang belum dijadwalkan <b>tidak diisi</b> — namanya disebut
                        setelah selesai supaya bisa kamu isi sendiri.
                    </small>
                </span>
            </label>
        </ConfirmModal>

        <transition name="plw-toast"><div v-if="toast" class="plw-toast" :class="{ 'is-err': toastErr, 'is-atas-unduhan': unduhan.length }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import PanelProses from '@career/PanelProses.vue';
import BerkasAktivitas from '@career/BerkasAktivitas.vue';
import PanelPemeriksaan from '@career/PanelPemeriksaan.vue';
import PanelSkrining from '@career/PanelSkrining.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import ExportStudio from '@career/ExportStudio.vue';
import EditorQuill from '@career/EditorQuill.vue';
import CatatanKeputusan from '@career/CatatanKeputusan.vue';
import KontenAman from '@career/KontenAman.vue';
import UraianLipat from '@career/UraianLipat.vue';
import PerpanjangBatas from '@career/PerpanjangBatas.vue';
import KonfirmasiPanel from '@career/KonfirmasiPanel.vue';
import TundaDialog from '@career/TundaDialog.vue';
import PermintaanDialog from '@career/PermintaanDialog.vue';
import { keDate, salahPerpanjang } from '@utils/career/batasIsi';
import { muatOpsiTunda, waktuSingkat } from '@utils/career/konfirmasi';
import { grupBagian, grupField, labelField, tipeField } from '@career/formulir';
import { ingatModal } from '@utils/ingatModal';

/**
 * Peta label/tipe/grup per FORMULIR, bukan per kode komponen.
 *
 * Sumbernya kini skema yang dibekukan di tiap pengisian (lihat
 * formulirTerkirim()), jadi dua kandidat bisa memakai versi formulir yang
 * berbeda. WeakMap dikunci ke objek formulirnya: begitu profil kandidat lain
 * dimuat, entri lamanya ikut hilang sendiri tanpa perlu dibersihkan.
 */
const PETA_SKEMA = new WeakMap();

/** Cache kolom-uraian per array baris riwayat. Lihat kolomUraian(). */
const KOLOM_URAIAN = new WeakMap();
/** Cache kolom-periode per array baris riwayat. Lihat kolomPeriode(). */
const KOLOM_PERIODE = new WeakMap();
const EMPTY_SET = new Set();

const CFG = { headers: { Accept: 'application/json' } };


/**
 * Penerima gambar yang ditanam di dalam catatan berformat.
 *
 * Bentuk URL-nya dikunci App\Support\Career\HtmlBersih — hanya tautan berpola
 * ini yang lolos penyaring saat catatannya disimpan.
 */
const URL_GAMBAR = '/api/v1/karir/lamaran/catatan/gambar';

/**
 * HTML catatan → panjang teksnya saja.
 *
 * Gerbang "alasan minimal 10 karakter" harus menghitung yang DIBACA MANUSIA.
 * Menghitung panjang HTML mentah membuat "<p>ok</p>" lolos dengan 9 karakter
 * markup — gerbangnya jadi hiasan.
 */
function teksDariHtml(html) {
    const el = document.createElement('div');
    el.innerHTML = html || '';
    // Gambar dihitung sebagai isi: catatan berupa foto lembar penilaian memang
    // tidak punya satu huruf pun, tapi jelas bukan catatan kosong.
    const punyaGambar = !!el.querySelector('img');
    const teks = (el.textContent || '').replace(/\s+/g, ' ').trim();

    return punyaGambar && teks.length < 10 ? '(catatan berupa gambar)' : teks;
}

/**
 * Teks polos → paragraf HTML untuk editor.
 *
 * Jadwal lama hanya punya catatan teks polos. Membuka "Ubah Jadwal" pada
 * jadwal seperti itu tidak boleh menampilkan editor kosong — catatan yang
 * sudah diterima kandidat akan hilang begitu disimpan ulang.
 */
function htmlDariTeks(teks) {
    const baris = String(teks || '').split(/\r?\n/).map((b) => b.trim()).filter(Boolean);
    if (!baris.length) return '';
    const el = document.createElement('div');

    return baris.map((b) => {
        el.textContent = b;

        return `<p>${el.innerHTML}</p>`;
    }).join('');
}

/** "YYYY-MM-DD" untuk hari ini + `hari` hari (waktu setempat). */
function tanggalDepan(hari) {
    const d = new Date();
    d.setDate(d.getDate() + Number(hari || 0));
    const dua = (n) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${dua(d.getMonth() + 1)}-${dua(d.getDate())}`;
}

/**
 * "11–14 Sep 2026" / "30 Sep – 07 Okt 2026" — rentang tanggal ringkas.
 * Bentuknya sama dengan UndanganJadwal::rentangPendek di server (tanggal
 * selalu dua angka, nama bulan dari daftar yang sama — bukan dari locale
 * peramban, yang menulis Agustus sebagai "Agu" atau "Agt" tergantung versi).
 */
const BULAN_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmtRentang(a, b) {
    if (!b) return '—';
    const tgl = (v) => new Date(String(v).slice(0, 10) + 'T00:00:00');
    const d2 = tgl(b);
    if (Number.isNaN(d2.getTime())) return '—';
    const d1 = a ? tgl(a) : null;
    const hari = (d) => String(d.getDate()).padStart(2, '0');
    const bulan = (d) => `${hari(d)} ${BULAN_PENDEK[d.getMonth()]}`;
    const penuh = (d) => `${bulan(d)} ${d.getFullYear()}`;
    if (!d1 || Number.isNaN(d1.getTime()) || d1 >= d2) return penuh(d2);
    if (d1.getFullYear() === d2.getFullYear() && d1.getMonth() === d2.getMonth()) return `${hari(d1)}–${penuh(d2)}`;
    if (d1.getFullYear() === d2.getFullYear()) return `${bulan(d1)} – ${penuh(d2)}`;

    return `${penuh(d1)} – ${penuh(d2)}`;
}

/**
 * Tautan Google Maps yang ditempel tim sah? Kosong = sah (opsional).
 *
 * Cermin UndanganJadwal::normalkanMaps di server: tombol simpan yang menyala
 * lalu ditolak 422 membuat rekruter mengira sistemnya rusak.
 */
function petaGoogleSah(url) {
    let u = String(url || '').trim();
    if (!u) return true;
    if (/\s/.test(u)) return false;
    if (!/^https?:\/\//i.test(u)) u = 'https://' + u;
    let p;
    try {
        p = new URL(u);
    } catch (e) {
        return false;
    }
    const host = p.hostname.toLowerCase();
    const path = p.pathname || '';
    const tld = '(com|[a-z]{2}|co\\.[a-z]{2}|com\\.[a-z]{2})';

    return host === 'maps.app.goo.gl'
        || (host === 'goo.gl' && path.startsWith('/maps'))
        || (host === 'g.co' && path.startsWith('/kgs/'))
        || (new RegExp(`^(www\\.)?google\\.${tld}$`).test(host) && path.startsWith('/maps'))
        || new RegExp(`^maps\\.google\\.${tld}$`).test(host);
}

/** "Jum, 17 Okt 2026" — tanggal saja, untuk batas waktu. */
function fmtTanggal(v) {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '—';

    return d.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
}

/**
 * Pilihan status MCU + nada warnanya.
 *
 * Hasil pemeriksaan kesehatan tidak cukup "lulus / gagal": keadaan tengah —
 * boleh bekerja dengan catatan — justru yang paling sering, dan itulah yang
 * menentukan penempatan saat onboarding.
 */

export default {
    // Halaman ini merender BEBERAPA simpul akar (konten + modal + lightbox yang
    // di-teleport), sehingga Vue tidak tahu ke mana harus menempelkan atribut
    // bawaan Inertia (errors, auth, flash, ...). Dimatikan supaya peringatan
    // "Extraneous non-props attributes" berhenti — atribut itu memang tidak
    // dipakai sebagai atribut HTML di sini.
    inheritAttrs: false,
    components: { Head, AdminModal, BerkasAktivitas, CatatanKeputusan, ConfirmModal, EditorQuill, ExportStudio, KonfirmasiPanel, KontenAman, PanelPemeriksaan, PanelProses, PanelSkrining, PermintaanDialog, PerpanjangBatas, TundaDialog, UraianLipat },
    props: {
        talent: { type: Array, default: () => [] },
        programAwal: { type: Object, default: () => ({ data: [], page: 1, totalPage: 1, total: 0 }) },
        // Master hasil keputusan — sumber tombol di footer drawer.
        hasilKeputusan: { type: Array, default: () => [] },
        // Bentuk pelaksanaan jadwal (daring / tatap muka / telepon) — dari
        // master, sumber yang sama dengan hasilKeputusan.
        modeJadwal: { type: Array, default: () => [] },
        // Status hasil MCU (Fit / Fit with Note / Temporary Unfit / Unfit) —
        // dari master. Dulu tiga nilai ditulis mati di berkas ini, dan yang
        // hilang justru "belum layak sementara": kandidat yang cuma perlu
        // diperiksa ulang terpaksa dicatat Unfit, yang berarti gugur permanen.
        mcuStatusOpsi: { type: Array, default: () => [] },
        // Jawaban kandidat atas penawaran (setuju / menolak / mundur) — dari
        // master. Dicatat TIM saat menandai kehadiran negosiasi; tombolnya di
        // portal kandidat sudah dicabut.
        jawabanPenawaran: { type: Array, default: () => [] },
        // HAK AKSES pengguna ini, dikirim shell untuk SEMUA halaman admin
        // (PropsShell: "dipakai Vue menyembunyikan tombol yang tidak
        // diizinkan"). Halaman ini tidak pernah membacanya — akibatnya tombol
        // keputusan tetap digambar untuk admin yang tidak berhak, dan
        // penolakannya baru datang setelah alasan diketik & modal dikirim.
        akses: { type: Object, default: () => ({ permissions: {}, konten: {} }) },
        // CATATAN KEPUTUSAN DUA ARAH — lihat CatatanEksternal (server).
        //   eksternalSiap  kolom catatan untuk kandidat sudah ada di basis data;
        //   tabBawaan      tab yang terbuka lebih dulu menurut kategori akun ini
        //                  (hanya Rekrutmen → INTERNAL, selain itu EKSTERNAL).
        catatanTahap: { type: Object, default: () => ({ eksternalSiap: false, tabBawaan: 'EKSTERNAL' }) },
        // BERAPA KANDIDAT BOLEH DIPUTUS SEKALI KIRIM.
        //
        // Datang dari LamaranController::BATAS_PUTUS_MASSAL — konstanta yang
        // sama yang dipakai aturan validasinya. Ditulis mati di sini, angkanya
        // akan berselisih dengan server pada hari batasnya diubah, dan
        // selisihnya muncul sebagai tombol yang tampak boleh ditekan lalu
        // ditolak sesudah alasan panjang terlanjur diketik.
        //
        // Sebabnya bukan teknis: tiap keputusan menerbitkan satu email ke
        // kandidat, dan puluhan email serempak dari satu pengirim membuat
        // penyedia surat menangguhkan alamatnya — yang ikut mematikan undangan
        // wawancara dan setel ulang kata sandi, bukan cuma gelombang ini.
        batasPutusMassal: { type: Number, default: 5 },
        // Jejak jadwal & instruksi berformat (skrip 30-09-2026) sudah aktif di
        // basis data ini? Sebelum skripnya dijalankan, jendela jadwal tidak
        // menuntut alasan dan catatannya tetap teks polos.
        jadwalFitur: { type: Object, default: () => ({ jejak: false, instruksi: false, alasanMin: 5 }) },
    },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/pelamar', {
        // Halaman ini punya belasan penanda boolean; hanya yang benar-benar
        // modal borang yang boleh membangkitkan pemulihan. `panelBuka` &
        // `filterBuka` cuma laci penyaring — bukan pekerjaan yang bisa hilang.
        buka: [
            'emailShow', 'laporanShow', 'konfirmShow', 'catatShow', 'jadwalShow',
            'hadirShow', 'holdShow', 'hmShow', 'pmShow', 'massalShow', 'absenShow',
            'perpanjangShow', 'kirimUlangShow', 'kirimUlangMassalShow',
        ],
        // Panel antrean & pengunduh punya timer yang mati bersama halaman lamanya;
        // memulihkan tampilannya berarti memasang progres yang tidak akan bergerak.
        //
        // Pratinjau di panel berkas kanan ikut dikecualikan dengan alasan yang
        // sama plus satu lagi: URL berkasnya bertanda tangan dan berumur, jadi
        // yang dipulihkan sesudah halaman lama ditinggalkan hampir pasti tautan
        // mati. Pilihan tata letaknya (dokPanel/dokTab/dokUrutBaru) sengaja
        // TIDAK ikut dikecualikan — itu memang layak bertahan.
        abaikan: [
            /^(borong|unduhan|unduhanRaf|lightbox|lbSrc|lbTimer|lbLoading|lbError|studio[A-Z])/,
            /^dok(Lihat|Src|Muat|Gagal|Timer|Cari)$/,
            // Bahan jendela jadwal dari server (instruksi Master Alur, riwayat)
            // dan penanda memuatnya — dipulihkan, keduanya bisa tertinggal di
            // keadaan "memuat…" yang tak akan pernah selesai.
            // Penanda unggah surat pengantar: dipulihkan, tombolnya tertahan
            // "Mengunggah…" selamanya.
            /^(jadwalInfo|massalInstruksiMuat|suratUnggahDi)$/,
        ],
    })],
    data() {
        return {
            // Sama dengan batas di LamaranController::putus().
            MIN_ALASAN: 10,
            URL_GAMBAR,
            programs: this.programAwal.data || [],
            page: this.programAwal.page || 1,
            totalPage: this.programAwal.totalPage || 1,
            total: this.programAwal.total || 0,
            // Disemai dari URL supaya tautan dari Dashboard ("Butuh Aksi")
            // mendarat langsung pada program yang dimaksud, bukan di daftar
            // penuh. Keduanya param yang memang sudah diterima worklistProgram.
            q: new URLSearchParams(window.location.search).get('q') || '',
            jenis: new URLSearchParams(window.location.search).get('jenis') || '',
            loadingProg: false,
            selectedId: null,
            detail: { program: null, posisi: [], kolom: [], pelamar: [] },
            loadingDetail: false,
            // Penyegaran SENYAP sesudah aksi — papan tetap tampil, hanya tombol
            // muat ulang yang berputar. Lihat muatDetail() & segarkanKartu().
            menyegarkan: false,
            // Nomor permintaan papan & per kartu: jawaban yang tiba TERLAMBAT
            // (program sudah diganti, atau permintaan yang lebih baru sudah
            // dikirim) dibuang, bukan ditimpakan ke papan.
            nomorMuatDetail: 0,
            nomorKartu: {},
            // ── DASAR DAFTAR PANEL KIRI ─────────────────────────────────────
            //
            // 'program' | 'loker'. Disimpan di perangkat, sama seperti `mode`:
            // ini soal pertanyaan apa yang sedang dikerjakan orangnya, dan
            // rekruter yang bekerja per lowongan tidak ingin dilempar kembali
            // ke daftar program setiap kali halaman dimuat ulang.
            //
            // Bawaannya tetap 'program' — perilaku yang sudah dikenal tidak
            // boleh berubah sendiri untuk orang yang tidak meminta apa-apa.
            basis: localStorage.getItem('plw.basis') === 'loker' ? 'loker' : 'program',
            // Alur yang sedang dipakai papan pada basis job vacancy.
            // '' = serahkan pada server (ia memilih bawaan yang benar);
            // 'SEMUA' = gabungkan; selain itu id alur sebagai teks.
            alurPilih: '',
            // Bawaan SEMUA, bukan AKTIF. Papan yang menyembunyikan kandidat
            // yang sudah ditutup membuat orang mencarinya di tempat yang salah —
            // dan yang mundur di tahap akhir tampak seolah hilang begitu saja.
            statusTab: 'SEMUA',
            detailKandidat: null,
            /**
             * ANTREAN TINJAU — daftar yang sedang ditelusuri tombol maju/mundur.
             *
             * Diisi SAAT kandidat dibuka, dari daftar tempat ia diklik: kolom
             * kanban yang bersangkutan, atau halaman daftar yang sedang tampil.
             * Bukan dihitung ulang saat tombol ditekan, dan itu disengaja:
             * begitu kandidat diputus (Loloskan/Tidak Lolos) ia berpindah kolom,
             * dan daftar yang dihitung ulang akan menyusut di bawah kaki
             * peninjau — "berikutnya" jadi melompati orang, atau malah memutar
             * balik ke orang yang baru saja dinilai.
             *
             * Hanya berisi id. Datanya sendiri selalu diambil segar dari
             * pelamarTampil saat berpindah, supaya hasil yang baru dicatat ikut
             * terbaca.
             */
            antreanTinjau: [],
            /** Nama daftar asal — ditampilkan di bilah navigasi ("Screening CV"). */
            antreanLabel: '',
            /** Pesan ujung antrean; ditampilkan sebagai alert ber-z-index tertinggi. */
            antreanAlert: null,
            // Tab modal detail: 'rapor' | 'berkas'. Selalu kembali ke
            // 'rapor' setiap kandidat dibuka — lihat bukaKandidat().
            tabAktif: 'rapor',
            profil: { lamaran: null, formulir: [], tahap: [] },
            // Satu tahap kandidat yang dibuka dari Progres Seleksi (baca-saja).
            tahapRiwayat: { show: false, muat: false, id: null, data: null },
            loadingProfil: false,
            berkasHasil: [],
            tahapBerkasId: null,
            berkasBusy: false,
            openForm: 0,
            // ── KIRIM ULANG EMAIL HASIL ──
            emailShow: false,
            emailMuat: false,
            emailSibuk: false,
            emailTahap: [],
            // Popup sukses kirim ulang: { pelamar, tahap, email } | null.
            emailTerkirim: null,
            // ── Jadwal pengisian formulir tahap (lihat App\Support\Career\BatasIsi) ──
            batasKolomShow: false,
            batasKolomCol: null,
            batasKolomBuka: '',
            batasKolomTgl: '',
            // 'ATUR' | 'PERPANJANG' | 'LEPAS' (Master Alur sudah "Tanpa jadwal")
            // | 'UBAH' (khusus superadmin).
            batasKolomLangkah: 'ATUR',
            batasKolomAlasan: '',
            riwayatKolom: { buka: false, muat: false, baris: [] },
            batasKandShow: false,
            batasKandTarget: [],
            // 'ATUR' (yang belum berjadwal) | 'PERPANJANG' (yang sudah) | 'LEPAS'.
            batasKandLangkah: 'PERPANJANG',
            batasKandBuka: '',
            batasKandTgl: '',
            batasKandAlasan: '',
            // Pilihan perpanjangan — dipakai bergantian modal kolom & kandidat.
            perp: { cara: 'HARI', hari: 2, jam: 6, sampai: '' },
            batasSibuk: false,
            riwayatBatas: { buka: false, muat: false, baris: [], tahapId: null },
            // Batas berakhir di detik terakhir harinya — sama dengan server.
            JAM_BATAS: new Date(2000, 0, 1, 23, 59, 59),
            emailPilih: null,
            // Alamat tujuan MENURUT SERVER — hanya untuk ditampilkan dan
            // dicocokkan; bukan alamat yang kita kirimkan sebagai tujuan.
            emailTujuan: null,
            // Akordion riwayat yang SEDANG DITUTUP. Yang disimpan kondisi
            // tutupnya, bukan bukanya — dengan begitu riwayat yang baru muncul
            // (kandidat lain, formulir lain) terbuka sendiri tanpa perlu
            // didaftarkan lebih dulu.
            riwayatDitutup: {},
            // ── BERKAS: DUA CARA MEMBACA SATU ISI ───────────────────────────
            //
            // 'daftar' — akordion per formulir. BAWAAN, dan memang harus:
            //   yang paling sering ditanyakan peninjau bukan "berkas apa saja
            //   yang ada" melainkan "apa jawaban pertanyaan ini" — dan jawaban
            //   itu hanya punya arti bersama pertanyaannya.
            // 'berkas' — file manager: folder per formulir di kiri, petak berkas
            //   di kanan. Dipakai saat yang dicari memang lembarannya sendiri
            //   ("mana ijazahnya?"), yang di mode daftar menuntut membuka tiap
            //   akordion satu per satu.
            //
            // Pilihannya diingat per peramban: seorang verifikator berkas
            // memakai mode yang sama sepanjang hari.
            berkasMode: localStorage.getItem('plw.berkasMode') === 'berkas' ? 'berkas' : 'daftar',
            // Folder yang sedang dibuka di file manager: '' = semua, selain itu
            // nomor formulirnya.
            fmFolder: '',
            fmCari: '',
            // Berkas yang sedang disorot — memunculkan bilah pratinjau di kaki.
            fmSorot: '',

            // ── PANEL BERKAS KANAN ──────────────────────────────────────────
            //
            // Lemari berkas yang berdiri sendiri di sisi kanan jendela detail,
            // terpisah dari file manager di dalam tab Berkas & Biodata. Dua-
            // duanya membaca `fmSemua` yang sama; bedanya PERAN, bukan data:
            // yang di dalam tab untuk menelusuri per formulir, yang di sini
            // untuk MENDAMPINGI pekerjaan lain — ia tetap terlihat sementara
            // rapor tes dibaca dan keputusan ditimbang.
            //
            // Terbuka/terlipatnya diingat per peramban: layar 13 inci dan layar
            // 27 inci menuntut jawaban berbeda, dan jawabannya tidak berubah
            // dari kandidat ke kandidat.
            dokPanel: localStorage.getItem('plw.dokPanel') !== '0',
            // Sub-tab panel: '' semua · 'dok' berkas dokumen · 'img' gambar ·
            // selain itu nomor formulirnya.
            dokTab: '',
            dokCari: '',
            // Laci penyaring sedang terbuka? Bawaannya tertutup: menyaring baru
            // perlu ketika lembarnya banyak, sementara bilahnya membayar tinggi
            // di setiap kunjungan — dan tinggi itu diambil dari pratinjau.
            dokSaring: false,
            // Urutan bawaan TERBARU DULU. Yang paling sering dicari peninjau
            // adalah lembar yang barusan masuk — berkas tahap terakhir — dan
            // urutan formulir justru menaruhnya paling bawah.
            dokUrutBaru: true,
            // Berkas yang sedang dibuka di kolom pratinjau. Diisi sendiri ke
            // lembar pertama begitu berkas kandidat selesai dimuat — kolom yang
            // menyambut peninjaunya dengan bidang kosong menyuruh ia mengklik
            // dulu sebelum ada yang bisa dilihat, padahal melihat itulah
            // gunanya kolom ini.
            dokLihat: null,
            dokSrc: '',
            dokMuat: false,
            dokGagal: false,
            // Penjaga waktu pemuatan: URL bertanda tangan yang mati tidak
            // pernah memicu onerror pada iframe, jadi tanpa ini spinnernya
            // berputar selamanya.
            dokTimer: null,
            // Keterangan "tahap ini belum tuntas" sedang ditutup?
            //
            // Disimpan PER KANDIDAT (kunci id lamaran), bukan sekali untuk
            // seluruh sesi: alasan terkuncinya berbeda-beda tiap orang, dan
            // menutupnya sekali lalu tak pernah melihatnya lagi berarti kandidat
            // berikutnya diputus tanpa tahu apa yang belum selesai.
            notaDitutup: {},
            // ── EXPORT STUDIO (BERKAS SELEKSI) ──────────────────────────────
            // Sengaja TIDAK ikut dipulihkan setelah refresh (lihat `abaikan` di
            // ingatModal): isinya pilihan centang yang bergantung pada kandidat
            // yang sedang dibuka, dan memulihkan modalnya tanpa kandidatnya
            // hanya menampilkan daftar kosong.
            studioShow: false,
            studioLamaran: '',
            studioNama: '',
            studioKode: '',
            // ── CETAK LAPORAN ───────────────────────────────────────────────
            laporanShow: false,
            laporanFormat: 'PDF',
            laporanOpsi: [],
            laporanFormulir: [],
            laporanGagal: '',
            // Daftar formulir diambil SESUDAH modal terbuka. Tanpa penanda ini
            // modalnya terbuka dengan ruang kosong di bawah pilihan format,
            // lalu daftar centangnya muncul tiba-tiba — dan yang sempat menekan
            // "Buat & Unduh" lebih dulu mencetak tanpa pilihan yang ia kira ada.
            laporanMuat: false,
            // Antrean unduhan yang sedang berjalan — ditampilkan di pojok kanan
            // bawah. Array, bukan satu objek: admin kerap mencetak beberapa
            // kandidat berturut-turut tanpa menunggu yang sebelumnya selesai.
            // Panel kemajuan keputusan massal. null = tak ada gelombang.
            borong: null,
            // Pegangan denyut pemantau — satu untuk seluruh panel.
            borongTimer: null,
            unduhan: [],
            // Pegangan gelung animasi progres — satu untuk seluruh baris.
            unduhanRaf: null,
            // Aktivitas yang panel "Catatan & berkas"-nya sedang dibuka.
            // SATU saja: membuka semuanya sekaligus mengembalikan dinding teks
            // yang justru ingin dihindari, dan drawer-nya hanya selebar itu.
            detailTes: null,
            // Aktivitas yang panel KONFIRMASI KEHADIRAN-nya sedang dibuka (satu).
            konfBuka: null,
            lightbox: null,
            // Biodata dibaca di layar penuh, jendela detail tetap terbuka di
            // belakangnya. Lihat hamparan .plw-biofull.
            bioFull: false,
            lepasEscBio: null,
            lbSrc: '',
            lbTimer: null,
            lbLoading: false,
            lbError: false,
            konfirmShow: false,
            putusTarget: null,
            putusHasil: '',
            putusSetuju: false, // centang wajib sebelum menggugurkan
            // Centang "saya sadar aktivitas belum selesai" — terpisah dari yang
            // di atas karena keduanya mengakui hal yang berbeda.
            putusSetujuAktivitas: false,
            // Alasan keputusan ditulis di editor berformat. Ringkasan polosnya
            // DITURUNKAN saat dikirim, tidak disimpan sebagai state kedua —
            // dua salinan yang bisa berselisih hanya menunggu giliran basi.
            putusCatatanHtml: '',
            // Catatan UNTUK KANDIDAT & tab yang sedang terbuka di jendela keputusan.
            putusCatatanEksternalHtml: '',
            putusTabCatatan: 'EKSTERNAL',
            // Kandidat yang mundur disimpan di Talent Pool atau tidak. Hanya
            // ditanyakan untuk hasil ber-`pilihTalentPool`; nilainya disemai
            // dari bawaan masternya saat modal dibuka.
            putusTalentPool: true,
            putusTanggal: '',
            catatShow: false,

            // ── PHONE SCREENING ──────────────────────────────────────────
            // Isi penuhnya (pertanyaan + jawaban) TIDAK ikut payload worklist:
            // satu template bisa berisi 50 pertanyaan, dan mengangkutnya untuk
            // setiap aktivitas di setiap baris berarti ribuan baris yang tidak
            // satu pun digambar sampai jendelanya dibuka.
            skrShow: false,
            skrTarget: null,
            skrIsi: null,
            skrBusy: false,
            skrSelesaiShow: false,
            skrBukaKunciShow: false,
            skrAlasan: '',
            catatTarget: null,
            catatHasil: 'LULUS',
            mcuStatus: 'FIT',
            mcuPenyedia: '',
            mcuTanggal: '',
            mcuCatatan: '',
            jawabPenawaran: '',
            jadwalShow: false,
            jadwalTarget: null,
            jadwalMode: 'DARING',
            jadwalMulai: '',
            jadwalMulaiAsal: '',
            jadwalSelesai: '',
            jadwalLink: '',
            jadwalKontak: '',
            jadwalLokasi: '',
            jadwalLokasiId: null,
            // Tempat di luar master (RS dadakan) — dua isian wajibnya.
            jadwalLokasiNama: '',
            jadwalLokasiAlamat: '',
            // Penanda pilihan "Lainnya". Nilainya SAMA PERSIS dengan konstanta
            // MasterLokasiController::LAINNYA di server; garis bawah membuatnya
            // mustahil bertabrakan dengan hashid mana pun.
            LOKASI_LAIN: '__LAINNYA__',

            /**
             * JAM BAWAAN saat sebuah TANGGAL dipilih tanpa menyentuh jamnya.
             *
             * Element Plus memulai dari 00:00. Untuk "waktu selesai" itu jam
             * yang mustahil benar: memilih tanggal 22 Agustus berarti kegiatannya
             * berakhir tepat saat hari itu dimulai — yaitu sebelum jam mulainya,
             * hampir selalu. Yang dimaksud orang ketika ia menunjuk tanggal
             * selesai tanpa menyebut jam adalah "sampai habis hari itu".
             *
             * Tanggalnya sendiri diabaikan Element Plus; hanya jamnya yang
             * dipakai. Date bukan objek biasa, jadi Vue tidak membungkusnya
             * reaktif — ia sampai ke Element Plus apa adanya.
             */
            JAM_TUTUP: new Date(2000, 0, 1, 23, 59, 0),
            daftarLokasi: [],
            // Master peruntukan (KANTOR / MEDIS / …) berikut seluruh labelnya.
            daftarPeruntukan: [],
            lokasiLoading: false,
            jadwalCatatan: '',
            // ── MODE BERRENTANG TANGGAL (MCU vendor / mandiri) & INSTRUKSI ─────
            // Rentang ["YYYY-MM-DD", "YYYY-MM-DD"] — terisi otomatis dari
            // Batas_Hari_Bawaan mode-nya; rekruter cukup menyimpan.
            jadwalRentang: [],
            // VENDOR: catatan cabang (berformat) & tautan Google Maps yang ditempel.
            jadwalLokasiHtml: '',
            jadwalMapsUrl: '',
            // Daftar surat pengantar di jendela: yang sudah melekat { tetap: urutan }
            // dan yang baru diunggah { ref } — keduanya { nama, ukuran }.
            jadwalSuratDaftar: [],
            // Kalimat biaya untuk kandidat ini (bisa disunting).
            jadwalKalimatBiaya: '',
            // Informasi biaya ikut ke kandidat? Bawaan dari Master Alur.
            jadwalTampilBiaya: true,
            // Surat yang sedang diunggah: 'satuan' | 'massal' | null.
            suratUnggahDi: null,
            // Perpanjang batas & kirim ulang email jadwal.
            perpanjangShow: false,
            perpanjangTarget: null,
            perpanjangTanggal: '',
            perpanjangAlasan: '',
            kirimUlangShow: false,
            kirimUlangTarget: null,
            // Jendela Tunda dari baris aksi aktivitas (tunda | perbarui).
            tundaBaris: { show: false, id: '', mode: 'tunda', awal: null, konteks: {} },
            // Proses permintaan jadwal lain dari baris aksi aktivitas.
            mintaBaris: { show: false, id: '', mode: 'setujui', minta: null, konteks: {} },
            kirimUlangMassalShow: false,
            kirimUlangAktivitas: '',
            // Instruksi berformat untuk kandidat — terisi dari Master Alur.
            jadwalCatatanHtml: '',

            // Alasan perubahan — hanya diminta saat jadwal yang sudah terkirim diubah.
            jadwalAlasan: '',
            // Bahan dari server: instruksi Master Alur + riwayat jadwal aktivitas ini.
            jadwalInfo: { muat: false, id: null, instruksiAlur: null, kalimatBiayaAlur: null, jejak: [] },
            jadwalJejakBuka: false,
            hadirShow: false,
            hadirTarget: null,
            hadirNilai: 'Y',
            // Catatan jendela kehadiran — SATU untuk kedua cabang (hadir maupun
            // tidak), ditulis di editor berformat, boleh memuat gambar lembar
            // penilaian. Dulu cabang "tidak hadir" punya model teks polosnya
            // sendiri, dan alasan ketidakhadiran jadi satu-satunya catatan di
            // riwayat yang tampil tanpa format.
            hadirCatatanHtml: '',
            // Hasil & nilai ikut di jendela Hadir — satu tindakan, bukan dua.
            hadirHasil: 'LULUS',
            hadirNilaiSkor: null,
            hadirNilaiTeks: '',
            catatNilai: null,
            catatNilaiTeks: '',
            catatAdjudikasi: '',

            // Persetujuan pemeriksaan, dijawab langsung dari baris aktivitas.
            setujuId: null,
            tolakShow: false,
            tolakTarget: null,
            tolakAlasan: '',
            catatCatatanHtml: '',
            // Berkas aktivitas diunggah LANGSUNG saat dipilih, bukan saat modal
            // disimpan. Bila admin lalu menekan Batal, layar besar masih
            // menampilkan keadaan lama — penanda ini yang memicu muat ulang.
            berkasAktivitasBerubah: false,
            // ── TAHAN (HOLD) ────────────────────────────────────────────────
            holdShow: false,
            holdNilai: true, // true = menahan, false = melepaskan

            // ── ULANGI TAHAP ──
            ulangShow: false,
            ulangMuat: false,
            ulangTahapList: [],
            ulangRiwayat: [],
            ulangPutaran: 1,
            ulangTujuan: null,
            // TAHAP = tahap itu saja; RANGKAIAN = tahap itu sampai yang terakhir.
            ulangCakupan: 'TAHAP',
            ulangAlasan: '',
            // Bawaannya MATI. Sebagian besar pengulangan adalah koreksi internal
            // yang tidak perlu — dan tidak sebaiknya — dikabarkan ke kandidat.
            ulangEmail: false,
            holdAlasan: '',
            holdCatatanHtml: '',

            /* ── TAHAN / LANJUTKAN MASSAL ── */
            hmShow: false,
            hmNilai: true,          // true = menahan, false = melepaskan
            hmPola: 'SERAGAM',      // SERAGAM = satu alasan untuk semua, SENDIRI = per kandidat
            hmTarget: [],           // baris kandidat yang BENAR-BENAR akan diproses
            hmDilewati: [],         // terpilih tapi tidak memenuhi syarat
            hmAlasan: '',
            hmCatatanHtml: '',
            hmBaris: [],            // [{ id, tahapId, pelamar, posisi, tahap, alasan, catatan }]
            // Daftar alasan dari Master Alasan Hold — dimuat sekali per sesi.
            alasanHold: [],

            /* ── KEPUTUSAN MASSAL (LOLOS / TIDAK LOLOS) ── */
            pmShow: false,
            pmPola: 'SERAGAM',      // SERAGAM = satu keputusan untuk semua, SENDIRI = per kandidat
            pmHasil: '',            // kode hasil pada mode SERAGAM
            pmCatatanHtml: '',      // catatan bersama pada mode SERAGAM
            pmCatatanEksternalHtml: '', // catatan UNTUK KANDIDAT, bersama pada mode SERAGAM
            pmTabCatatan: 'EKSTERNAL',
            // Sekalian mencatat hasil aktivitas yang belum tercatat. Menyala
            // secara bawaan: itulah bentuk pemakaian yang membuat keputusan
            // massal ada gunanya sama sekali.
            pmCatatAktivitas: true,
            pmTarget: [],           // baris yang BENAR-BENAR akan diputus
            pmDilewati: [],         // tercentang tapi tidak memenuhi syarat
            pmBaris: [],            // [{ id, tahapId, pelamar, posisi, tahap, hasil, catatan }]
            // Kandidat yang sedang ditahan/dilepas dari KARTU (bukan drawer).
            holdTarget: null,
            // ── MODE TAMPILAN ───────────────────────────────────────────────
            // Disimpan di perangkat, bukan di server: pilihan ini soal kebiasaan
            // membaca, dan admin yang bekerja dari daftar tidak ingin kembali ke
            // papan setiap kali halaman dimuat ulang.
            mode: localStorage.getItem('plw.mode') === 'list' ? 'list' : 'kanban',
            // ── PENYARING PAPAN ─────────────────────────────────────────────
            cariKandidat: '',
            // LARIK, bukan satu nilai: keempatnya multi-pilih. Larik kosong =
            // tidak menyaring apa pun, sama seperti '' dulu.
            keadaanPilih: [],
            tahapPilih: [],
            kampusPilih: [],
            // [mulai, selesai] dalam 'YYYY-MM-DD', atau null saat kosong —
            // bentuk yang dipakai el-date-picker bertipe daterange.
            rangeTanggal: null,
            /**
             * Panel penyaring sedang terbuka?
             *
             * Terbuka di layar lebar — di sana ia memang menempati ruang yang
             * tak dipakai apa pun, dan menyembunyikannya cuma menambah satu
             * klik ke pekerjaan yang paling sering dilakukan.
             *
             * Tertutup di ponsel: enam isian bertumpuk setinggi satu layar
             * penuh berarti papan kandidat — yang justru dibuka orang — baru
             * muncul setelah digulir melewati seluruh penyaring. Di sana
             * pintunya FAB (lihat .plw-fab).
             */
            panelBuka: typeof window !== 'undefined' ? window.innerWidth >= 768 : true,
            // Pintasan rentang: sekian hari terakhir sampai hari ini.
            pintasTanggal: [
                { hari: 7, label: '7 hari' },
                { hari: 30, label: '30 hari' },
                { hari: 90, label: '90 hari' },
            ],
            // ── PAGINASI MODE LIST ──────────────────────────────────────────
            listPage: 1,
            listPer: 20,
            // ── PILIH BANYAK & JADWAL MASSAL ────────────────────────────────
            // Kunci kolom yang sedang dalam mode pilih — HANYA SATU kolom pada
            // satu waktu. Penjadwalan massal selalu menyangkut satu tahap, jadi
            // memilih lintas kolom hanya membuka jalan memilih orang-orang yang
            // aktivitasnya berbeda lalu bingung kenapa sebagian dilewati.
            kolomPilih: '',
            terpilih: [], // id lamaran (hashid)
            // ── PENYARING PER KOLOM ─────────────────────────────────────────
            // { [kunciKolom]: { q, keadaan, urut } } — dibuat saat kolomnya
            // pertama kali disentuh, bukan disiapkan untuk semua kolom sekaligus.
            filterKolom: {},
            filterBuka: '', // kunci kolom yang panel penyaringnya sedang terbuka
            massalShow: false,
            massalAktivitas: '',
            massalMode: 'LURING',
            massalPola: 'BERGILIR',
            massalMulai: '',
            massalDurasi: 30,
            massalJeda: 0,
            massalLink: '',
            massalLokasi: '',
            massalLokasiId: null,
            massalLokasiNama: '',
            massalLokasiAlamat: '',
            massalCatatan: '',
            // Mode berrentang tanggal: satu rentang untuk semua kandidat.
            massalRentang: [],
            massalLokasiHtml: '',
            massalMapsUrl: '',
            // Surat pengantar kolektif [{ ref, nama, ukuran }] untuk seluruh sasaran.
            massalSuratDaftar: [],
            massalKalimatBiaya: '',
            massalTampilBiaya: true,
            massalCatatanHtml: '',
            // Dipakai untuk kandidat terpilih yang SUDAH punya jadwal.
            massalAlasan: '',
            massalInstruksiMuat: false,
            sibuk: false,
            // Id aktivitas yang sedang ditarik hasilnya dari HCLearn.
            sinkronId: null,
            // Aktivitas yang sedang diputus lulus/tidak (ujian online informatif).
            // Hasilnya ikut disimpan supaya spinner muncul di tombol YANG DITEKAN,
            // bukan di keduanya.
            keputusanId: null,
            keputusanHasil: null,
            // Aktivitas yang sedang dibuka jalannya (tombol "Lanjutkan").
            lanjutId: null,
            // Konfirmasi "Tidak hadir" — aksi final, tak boleh sekali klik.
            absenShow: false,
            absenTarget: null,
            absenCatatan: '',
            toast: '',
            toastErr: false,
            tm: null,
            cariTm: null,
            posisiPilih: [], // larik kosong = semua lowongan
        };
    },
    // Pemantau unduhan hidup di luar siklus Vue — tanpa dibersihkan, ia terus
    // menembak API setelah halaman ditinggalkan.
    beforeUnmount() {
        if (this.lepasEscBio) window.removeEventListener('keydown', this.lepasEscBio, true);
        clearTimeout(this.antreanAlertTimer);
        this.unduhan.forEach((u) => clearTimeout(u.timer));
        if (this.unduhanRaf) cancelAnimationFrame(this.unduhanRaf);
        this.hentikanPantauBorong();
        clearTimeout(this.dokTimer);
    },
    watch: {
        /**
         * Tab Edit (superadmin) untuk SATU kandidat berjadwal: isian awal = jadwalnya
         * sekarang, supaya yang dikoreksi cukup bagian yang salah.
         */
        batasKandLangkah(v) {
            const satu = this.batasKandUbah.length === 1 ? this.batasKandUbah[0].batas : null;
            if (v === 'UBAH' && satu?.batas && !this.batasKandTgl) {
                this.batasKandBuka = satu.buka ? String(satu.buka).slice(0, 19) : '';
                this.batasKandTgl = String(satu.batas).slice(0, 19);
            }
        },
        // Keputusan massal yang MENUNTUT alasan membuka tab internal — di
        // sanalah alasan itu harus ditulis. Lihat tabAwalCatatan().
        pmHasil(v) {
            if (this.hasilKeputusan.find((h) => h.kode === v)?.butuhAlasan) this.pmTabCatatan = 'INTERNAL';
        },
        // Pilihan DIKOSONGKAN saat papan berganti isi.
        //
        // Tanpa ini, kandidat yang terpilih lalu tersaring keluar tetap terbawa
        // diam-diam: penghitungnya bilang "12 terpilih" sementara hanya 3 yang
        // terlihat, dan penjadwalan massal mengundang sembilan orang yang tidak
        // sedang dilihat siapa pun.
        statusTab() { this.terpilih = []; this.listPage = 1; },
        posisiPilih() { this.terpilih = []; this.listPage = 1; },
        // Pindah ke mode berrentang tanggal → rentangnya langsung terisi dari
        // master, supaya rekruter cukup menyimpan.
        jadwalMode() { this.isiRentangBawaan(); },
        massalMode() { this.isiRentangMassal(); },
        /**
         * Ganti aktivitas → lokasi yang sudah dipilih DIBATALKAN.
         *
         * Peruntukannya bisa ikut berganti (wawancara → MCU), dan pilihan lama
         * lalu tak lagi ada di dropdown — tetapi nilainya tetap tersimpan di
         * v-model. Layar menampilkan kotak yang seolah kosong sementara yang
         * terkirim adalah kantor, untuk seratus orang sekaligus.
         */
        massalAktivitas() {
            this.massalLokasiId = null;
            this.massalLokasiNama = '';
            this.massalLokasiAlamat = '';
            // Bentuk yang tidak berlaku untuk aktivitas barunya (wajib tatap
            // muka, atau di luar izin tipenya) dipindah ke yang berlaku. Kalau
            // tidak, layar menyembunyikan pilihannya sementara yang terkirim
            // tetap bentuk lama — dan server menolaknya satu per satu.
            if (!this.massalBoleh(this.massalMode)) {
                this.massalMode = this.modeMassalAwal();
            }
            // Instruksinya milik aktivitas — ganti aktivitas, ganti instruksi.
            if (this.massalShow) {
                this.muatInstruksiMassal();
            }
        },
        keadaanPilih() { this.terpilih = []; this.listPage = 1; },
        // Ganti program = papan yang sama sekali lain. Penyaring kolom milik
        // program lama tidak boleh ikut, karena kunci kolomnya bisa kebetulan
        // sama (dua alur sama-sama punya tahap "Psikotes") dan kolom yang baru
        // dibuka akan langsung tersaring tanpa ada yang menyalakannya.
        //
        // Penyaring TAHAP & KAMPUS ikut dikosongkan: keduanya menyebut nilai
        // milik program lama, dan yang tersisa di kotaknya akan menyaring papan
        // baru sampai kosong tanpa satu pun petunjuk kenapa.
        selectedId() {
            this.tutupPilihKolom();
            this.filterKolom = {};
            this.filterBuka = '';
            this.tahapPilih = [];
            this.kampusPilih = [];
            this.listPage = 1;
        },
        /* Pindah cara membaca papan MENGOSONGKAN pilihan. Dua mode memilih dengan
           cara berbeda — kanban terkurung satu kolom, list bebas melintasi tahap
           — dan pilihan yang terbawa diam-diam jadi tak terlihat di mode
           sebelahnya: kanban hanya menggambar centang pada kolom yang sedang
           dalam mode pilih. Dua puluh orang yang tak tampak di layar tetap ikut
           tersapu tombol massal berikutnya. */
        mode(v) {
            localStorage.setItem('plw.mode', v);
            this.terpilih = [];
            this.tutupPilihKolom();
        },
        /* Saringan berubah → kembali ke halaman satu. Tanpa ini, menyaring dari
           halaman 7 mendarat di halaman yang sudah tidak ada isinya, dan daftar
           terbaca kosong padahal hasilnya ada. */
        cariKandidat() { this.listPage = 1; },
        tahapPilih() { this.listPage = 1; this.terpilih = []; },
        kampusPilih() { this.listPage = 1; this.terpilih = []; },
        rangeTanggal() { this.listPage = 1; this.terpilih = []; },
        listPer() { this.listPage = 1; },
    },
    computed: {
        /** Panel kiri sedang berbasis JOB VACANCY (MPP), bukan program. */
        basisLoker() { return this.basis === 'loker'; },
        /**
         * Alur yang hidup di job vacancy terpilih — bahan penyaring alur.
         *
         * Selalu dari `detail`, tidak pernah disimpan terpisah: daftar ini
         * milik lowongan yang sedang dibuka, dan menyalinnya ke state sendiri
         * berarti satu salinan yang bisa tertinggal saat lowongan berganti —
         * lalu pemilih menawarkan alur milik lowongan sebelumnya.
         */
        alurOpsi() { return this.basisLoker ? (this.detail.alurOpsi || []) : []; },
        /**
         * Rincian job vacancy terpilih, atau null pada basis program.
         *
         * Null-nya penting: di basis program satu papan memuat banyak loker
         * yang departemen & lokasinya berbeda-beda, jadi tidak ada satu nilai
         * yang benar untuk ditulis di kepala halaman.
         */
        lokerInfo() { return this.basisLoker ? (this.detail.loker || null) : null; },

        /**
         * Sisa SLA MPP untuk kepala worklist — KHUSUS REKRUTMEN.
         *
         * MT sengaja dikecualikan: pesertanya direkrut seangkatan mengikuti
         * jadwal program yang sudah ditetapkan HC, bukan mengejar tenggat
         * pemenuhan kursi per MPP. Menampilkan hitung mundur di sana menekan
         * rekruter atas target yang bukan miliknya.
         */
        slaLoker() {
            if (String(this.detail?.program?.kategori || '').toUpperCase() === 'MT') return null;

            return this.lokerInfo?.sla || null;
        },
        /**
         * Berapa persen jatah hari kerja yang SUDAH TERPAKAI.
         *
         * null bila totalnya tidak diketahui — dan itu bukan kasus langka:
         * MPP lama bisa punya tenggat tanpa `Sla_Hari_Kerja`. Menggambar bilah
         * dengan menebak totalnya akan memberi kesan presisi yang tidak dimiliki
         * datanya, jadi lebih baik bilahnya tidak digambar sama sekali.
         *
         * Yang lewat tenggat selalu 100%: bilah tidak bisa melampaui ujungnya,
         * dan besarnya keterlambatan sudah disebut angka di sebelahnya.
         */
        slaPersen() {
            const sla = this.slaLoker;
            const total = Number(sla?.hari || 0);
            if (!sla || total <= 0) return null;
            if (sla.lewat) return 100;

            const terpakai = total - Number(sla.sisa || 0);

            return Math.max(0, Math.min(100, Math.round((terpakai / total) * 100)));
        },
        /**
         * Status lamaran yang berarti KANDIDAT SENDIRI yang mengakhiri.
         *
         * Dibaca dari MASTER (`Flag_Oleh_Kandidat = 'Y'`), bukan didaftar di
         * sini. Master itulah yang menentukan pilihan tombolnya; kalau daftar
         * ini ditulis ulang secara literal, menambah satu sebab mundur di
         * master akan memunculkan tombolnya tapi TIDAK memindahkan kandidatnya
         * ke tab yang benar — dan ia diam-diam kembali menempati papan.
         */
        statusMundur() {
            return new Set(this.hasilKeputusan.filter((h) => h.olehKandidat).map((h) => h.kode));
        },
        // Terminal "tidak lanjut" = GUGUR atau TALENT_POOL. Keduanya keluar dari
        // tab Berjalan dan berkumpul di tab Tidak Lolos (dengan badge berbeda).
        pelamarTampil() {
            const terminal = (r) => r.statusLamaran === 'GUGUR' || r.statusLamaran === 'TALENT_POOL';
            const mundur = (r) => this.statusMundur.has(r.statusLamaran);
            const q = this.cariKandidat.trim().toLowerCase();

            return (this.detail.pelamar || [])
                .filter((r) => {
                    // SEMUA tidak menyaring apa pun — seluruh kandidat program
                    // ini, di kolom tahapnya masing-masing.
                    if (this.statusTab === 'SEMUA') return true;
                    // DITAHAN adalah tab tersendiri, bukan bagian dari
                    // "Berjalan": mereka memang masih berjalan, tapi justru itu
                    // masalahnya — tercampur di sana, orang yang sengaja
                    // disisihkan tenggelam dan tak pernah ditinjau lagi.
                    if (this.statusTab === 'HOLD') return !!r.hold;
                    if (this.statusTab === 'MUNDUR') return mundur(r);
                    if (this.statusTab === 'GUGUR') return terminal(r);

                    return !terminal(r) && !mundur(r) && !r.hold;
                })
                // Penyaring lowongan berlaku untuk SEMUA tab, supaya angka
                // "berjalan" dan "tidak lolos" satu lowongan bisa dibandingkan.
                // Larik kosong = tidak menyaring. Beberapa nilai = gabungan
                // (ATAU) di dalam satu penyaring, dan irisan (DAN) antar
                // penyaring — bentuk yang sama dengan yang orang harapkan dari
                // saringan bertumpuk mana pun.
                .filter((r) => !this.posisiPilih.length || this.posisiPilih.includes(r.posisiId))
                // TAHAP — dicocokkan lewat KUNCI KOLOM, bukan nomor urut. Alasan
                // yang sama dengan penempatan kartu di kartuKolom(): nomor urut
                // berhenti benar begitu alur disunting atau program dialihkan.
                .filter((r) => !this.tahapPilih.length || this.tahapPilih.includes(this.kunciBaris(r)))
                .filter((r) => !this.kampusPilih.length || this.kampusPilih.includes(r.kampus || ''))
                // RENTANG TANGGAL MELAMAR. Batas akhirnya mencakup SELURUH hari
                // yang dipilih: memakai tengah malam membuat orang yang melamar
                // pukul 09.00 pada tanggal akhir jatuh di luar rentangnya sendiri.
                .filter((r) => this.dalamRentang(r.waktuLamar))
                // Cari menjangkau posisi & kampus juga — keduanya yang paling
                // sering diketik orang saat mencari "anak Unsri di QC".
                .filter((r) => !q
                    || (r.pelamar || '').toLowerCase().includes(q)
                    || (r.lamaranKode || '').toLowerCase().includes(q)
                    || (r.posisi || '').toLowerCase().includes(q)
                    || (r.kampus || '').toLowerCase().includes(q))
                // Keadaan menjawab "mana yang menunggu SAYA?" — pertanyaan yang
                // tanpa ini dijawab dengan memindai lencana kartu satu per satu.
                // Beberapa keadaan sekaligus digabung dengan ATAU: "perlu
                // keputusan ATAU sedang ditahan" adalah satu antrean kerja yang
                // memang dibaca bersamaan.
                .filter((r) => !this.keadaanPilih.length
                    || this.keadaanPilih.some((k) => this.cocokKeadaan(r, k)));
        },
        jmlSemua() { return (this.detail.pelamar || []).length; },
        jmlAktif() {
            return (this.detail.pelamar || []).filter((r) => r.statusLamaran !== 'GUGUR'
                && r.statusLamaran !== 'TALENT_POOL'
                && !this.statusMundur.has(r.statusLamaran)
                && !r.hold).length;
        },
        jmlGugur() { return (this.detail.pelamar || []).filter((r) => r.statusLamaran === 'GUGUR' || r.statusLamaran === 'TALENT_POOL').length; },
        jmlHold() { return (this.detail.pelamar || []).filter((r) => !!r.hold).length; },
        jmlMundur() { return (this.detail.pelamar || []).filter((r) => this.statusMundur.has(r.statusLamaran)).length; },
        /* ── PENYARING: PILIHAN & RINGKASANNYA ─────────────────────────────── */
        /**
         * Peta cepat "identitas tahap → kunci kolom".
         *
         * kunciBaris() dipanggil untuk SETIAP kandidat pada setiap render, dan
         * pada program berisi ratusan pelamar pencarian linier ke daftar kolom
         * dikerjakan ribuan kali per render. Petanya dihitung sekali per
         * perubahan alur.
         */
        petaKolom() {
            const kode = new Map();
            const urutan = new Map();
            for (const c of this.kolomTampil) {
                const k = this.kunciKolom(c);
                if (c.kode != null) kode.set(c.kode, k);
                if (c.urutan != null && !urutan.has(c.urutan)) urutan.set(c.urutan, k);
            }

            return { kode, urutan };
        },
        /**
         * Kampus yang BENAR-BENAR ada di program ini, berikut jumlah pelamarnya.
         *
         * Dihitung dari seluruh pelamar program — bukan dari `pelamarTampil` —
         * supaya daftarnya tidak menyusut mengikuti saringan yang sedang
         * berjalan. Dropdown yang isinya ikut menciut membuat pilihan yang baru
         * saja terlihat lenyap begitu satu penyaring lain dinyalakan, dan tak
         * ada cara menebak ke mana perginya.
         */
        kampusOpsi() {
            const peta = new Map();
            for (const r of this.detail.pelamar || []) {
                const nama = (r.kampus || '').trim();
                if (!nama) continue;
                peta.set(nama, (peta.get(nama) || 0) + 1);
            }

            const bendera = this.detail.kampusBendera || {};

            return [...peta.entries()]
                // `bendera` boleh kosong: nama yang diketik sendiri kandidat
                // memang tidak ada di Master Kampus, dan menebak negaranya dari
                // ejaan lebih menyesatkan daripada menggambar bola dunia.
                .map(([nama, jumlah]) => ({ nama, jumlah, bendera: bendera[nama] || null }))
                .sort((a, b) => b.jumlah - a.jumlah || a.nama.localeCompare(b.nama));
        },
        /**
         * Penyaring yang sedang menyala — sumber chip DAN tombol bersihkan.
         *
         * SATU CHIP PER NILAI, bukan satu chip per penyaring. Sejak keempatnya
         * multi-pilih, "Tahap: 3 dipilih" menyembunyikan justru yang perlu
         * dilihat, dan melepas satu di antaranya menuntut membuka dropdown-nya
         * kembali. Kuncinya ikut membawa nilainya supaya copotChip() tahu yang
         * mana yang dicabut.
         */
        chipFilter() {
            const out = [];
            const cari = this.cariKandidat.trim();
            if (cari) out.push({ key: 'cari', jenis: 'Cari', label: cari });
            this.tahapPilih.forEach((v) => {
                const c = this.kolomTampil.find((x) => this.kunciKolom(x) === v);
                out.push({ key: `tahap:${v}`, jenis: 'Tahap', label: c?.label || v });
            });
            this.kampusPilih.forEach((v) => out.push({ key: `kampus:${v}`, jenis: 'Kampus', label: v }));
            this.posisiPilih.forEach((v) => {
                const p = (this.detail.posisi || []).find((x) => x.id === v);
                out.push({ key: `posisi:${v}`, jenis: 'Lowongan', label: p?.posisi || 'Terpilih' });
            });
            this.keadaanPilih.forEach((v) => {
                const teks = {
                    PERLU: 'Perlu keputusan saya',
                    NUNGGU: 'Menunggu hasil tes',
                    JADWAL: 'Belum dijadwalkan',
                    TERJADWAL: 'Sudah dijadwalkan',
                    HOLD: 'Sedang ditahan',
                    KONF_MENUNGGU: 'Belum konfirmasi',
                    KONF_LAIN: 'Minta jadwal lain',
                    KONF_MUNDUR: 'Menyatakan mundur',
                    KONF_LEWAT: 'Tidak menjawab',
                    KONF_HADIR: 'Akan hadir',
                    KONF_DITUNDA: 'Ditunda',
                };
                out.push({ key: `keadaan:${v}`, jenis: 'Keadaan', label: teks[v] || v });
            });
            if (this.rentangAktif) {
                const [a, b] = this.rangeTanggal;
                out.push({ key: 'tanggal', jenis: 'Melamar', label: `${this.tglSingkat(a)} → ${this.tglSingkat(b)}` });
            }

            return out;
        },
        /** Rentang tanggal benar-benar terisi (el-date-picker mengosongkan jadi null). */
        rentangAktif() {
            return Array.isArray(this.rangeTanggal) && !!this.rangeTanggal[0] && !!this.rangeTanggal[1];
        },
        /**
         * Pintasan mana yang sedang menyala.
         *
         * Dicocokkan dari NILAI rentangnya, bukan dari tombol mana yang
         * terakhir ditekan: rentang yang sama boleh saja dipilih lewat
         * kalender, dan menyalakan tombol berdasarkan riwayat klik akan
         * membuatnya padam untuk rentang yang isinya persis sama.
         */
        pintasAktif() {
            if (!this.rentangAktif) return null;
            const cocok = this.pintasTanggal.find((p) => {
                const [a, b] = this.rentangHari(p.hari);

                return a === this.rangeTanggal[0] && b === this.rangeTanggal[1];
            });

            return cocok ? cocok.hari : null;
        },

        /* ── MODE LIST: PAGINASI ───────────────────────────────────────────── */
        /**
         * Urutan daftar: TERLAMA MENUNGGU DULU — sama dengan bawaan kolom kanban.
         * Dua tampilan atas populasi yang sama tidak boleh mengurutkan berbeda;
         * kalau berbeda, "yang paling atas" berarti dua hal tergantung tombol
         * mana yang terakhir ditekan.
         */
        /**
         * Posisi kandidat yang sedang dibuka di dalam antrean tinjau.
         * -1 bila ia sudah tidak ada di sana (mis. baru diputus lalu pindah
         * kolom) — navigasinya tetap jalan, lihat tetanggaTinjau().
         */
        indeksTinjau() {
            if (!this.detailKandidat) return -1;

            return this.antreanTinjau.indexOf(this.detailKandidat.id);
        },
        /**
         * Id tetangga kiri/kanan di antrean.
         *
         * Kandidat yang SUDAH TIDAK ADA di pelamarTampil (tersaring keluar oleh
         * tab, atau baru diputus) dilewati, bukan dibuka sebagai jendela kosong.
         * Yang dicari tetangga terdekat yang masih benar-benar bisa ditampilkan.
         */
        tetanggaTinjau() {
            const i = this.indeksTinjau;
            if (i < 0) return { mundur: null, maju: null };

            const ada = (id) => this.pelamarTampil.some((r) => r.id === id);
            const cari = (arah) => {
                for (let n = i + arah; n >= 0 && n < this.antreanTinjau.length; n += arah) {
                    if (ada(this.antreanTinjau[n])) return this.antreanTinjau[n];
                }

                return null;
            };

            return { mundur: cari(-1), maju: cari(1) };
        },
        /** Bilah navigasi hanya muncul bila antreannya memang lebih dari satu. */
        adaAntreanTinjau() {
            return !!this.detailKandidat && this.antreanTinjau.length > 1 && this.indeksTinjau >= 0;
        },
        barisTerurut() {
            const waktu = (r) => new Date(String(r.waktuLamar || '').replace(' ', 'T')).getTime() || 0;

            return [...this.pelamarTampil].sort((a, b) => waktu(a) - waktu(b));
        },
        totalList() { return this.barisTerurut.length; },
        totalHalamanList() { return Math.max(1, Math.ceil(this.totalList / this.listPer)); },
        /** Halaman yang benar-benar dipakai — dijepit agar tak melewati batas. */
        halamanList() { return Math.min(Math.max(1, this.listPage), this.totalHalamanList); },
        barisList() {
            const awal = (this.halamanList - 1) * this.listPer;

            return this.barisTerurut.slice(awal, awal + this.listPer);
        },
        /** Nomor halaman dengan elipsis — 1 … 4 5 6 … 12. */
        nomorHalaman() {
            const total = this.totalHalamanList;
            const kini = this.halamanList;
            const out = [];
            for (let n = 1; n <= total; n++) {
                if (n === 1 || n === total || Math.abs(n - kini) <= 1) {
                    out.push(n);
                } else if (out[out.length - 1] !== '…') {
                    out.push('…');
                }
            }

            return out;
        },
        teksHasil() {
            if (!this.totalList) return 'Tidak ada kandidat';
            if (this.mode === 'kanban') return `${this.totalList} kandidat di papan`;
            const awal = (this.halamanList - 1) * this.listPer;

            return `Menampilkan ${awal + 1}–${Math.min(awal + this.listPer, this.totalList)} dari ${this.totalList}`;
        },

        /* ── MODAL DETAIL: BERKAS SEBAGAI FILE MANAGER ─────────────────────── */
        /**
         * SELURUH lembar berkas kandidat, diratakan jadi satu daftar.
         *
         * Dikumpulkan dari TIGA tempat, karena di basis data memang berkas
         * kandidat tersebar di tiga bentuk yang berbeda:
         *   1. jawaban tingkat atas yang bertipe dokumen (dok_ktp, dok_cv, …);
         *   2. sub-isian di dalam baris berulang — sertifikat pada riwayat
         *      sertifikasi, yang satu barisnya bisa membawa beberapa lembar;
         *   3. berkas tanpa pertanyaan — foto verifikasi yang diambil sistem.
         *
         * Yang jadi NAMA di layar adalah pertanyaannya, bukan nama file. Nama
         * file bawaan unggahan ("SUJATMIKO_-_PAPI_KOSTICK_-_20260805.pdf") tidak
         * memberi tahu siapa pun ini menjawab apa; ia turun jadi baris kedua.
         */
        fmSemua() {
            const keluar = [];
            const tambah = (f, nama, konteks, b, i) => {
                if (!b) return;
                keluar.push({
                    id: `${f.no}::${b.field || nama}::${i}`,
                    nama,
                    konteks,
                    file: b.nama || '—',
                    ext: String(b.ext || '').toUpperCase(),
                    isImage: !!b.isImage,
                    folder: String(f.no),
                    folderLabel: f.label,
                    berkas: b,
                });
            };

            (this.profil.formulir || []).forEach((f) => {
                (f.jawaban || []).forEach((j) => {
                    const label = this.labelIsian(f, j);
                    this.berkasSel(j).forEach((b, i) => tambah(f, this.namaBerkasFormulir(f, b, label), f.label, b, i));

                    (j.baris || []).forEach((row, ri) => (row || []).forEach((p) => {
                        this.berkasSel(p).forEach((b, i) => tambah(
                            f,
                            this.namaBerkasFormulir(f, b, p.label || label, row),
                            `${label} · baris ${ri + 1}`,
                            b,
                            `${ri}-${i}`,
                        ));
                    }));
                });

                this.berkasLepas(f).forEach((b, i) => tambah(f, this.namaBerkasFormulir(f, b, this.labelBerkas(b)), f.label, b, `x${i}`));
            });

            // HASIL TAHAP — berkas tim & kandidat per tahap/aktivitas (FGD,
            // wawancara, MCU…). Tanpa ini panel hanya memuat berkas formulir,
            // dan hasil tahap yang sudah lewat tidak punya tempat untuk dibuka.
            (this.profil.tahap || []).forEach((t) => {
                (t.berkas || []).forEach((b, i) => keluar.push({
                    id: `t${t.urutan}::${b.sumber}::${b.id || i}`,
                    // Kiriman kandidat ditandai — "FGD" dari tim dan "FGD" dari
                    // kandidat adalah dua lembar yang berbeda.
                    nama: `${b.aktivitas || t.label}${b.sumber === 'KANDIDAT' ? ' · kandidat' : ''}`,
                    konteks: `Hasil · ${t.label}${b.sumber === 'KANDIDAT' ? ' · dari kandidat' : ''}`,
                    file: b.nama || '—',
                    ext: String(b.ext || '').toUpperCase(),
                    isImage: !!b.isImage,
                    folder: `t${t.urutan}`,
                    folderLabel: `Hasil · ${t.label}`,
                    berkas: { ...b, isPdf: String(b.ext || '').toLowerCase() === 'pdf', waktu: b.createdAt },
                }));
            });

            return keluar;
        },
        /** Folder kiri: "Semua" lebih dulu, lalu satu per formulir. */
        fmFolderOpsi() {
            const per = new Map();
            this.fmSemua.forEach((b) => per.set(b.folder, (per.get(b.folder) || 0) + 1));

            return [
                { key: '', label: 'Semua Berkas', ikon: 'bi-collection-fill', jumlah: this.fmSemua.length },
                ...(this.profil.formulir || []).map((f) => ({
                    key: String(f.no),
                    label: f.label,
                    ikon: f.waktuKirim ? 'bi-folder-fill' : 'bi-folder',
                    jumlah: per.get(String(f.no)) || 0,
                })),
                // Satu folder per tahap yang punya berkas hasil.
                ...(this.profil.tahap || []).filter((t) => per.get(`t${t.urutan}`)).map((t) => ({
                    key: `t${t.urutan}`,
                    label: `Hasil · ${t.label}`,
                    ikon: 'bi-clipboard2-check-fill',
                    jumlah: per.get(`t${t.urutan}`),
                })),
            ];
        },
        /** Isi petak kanan: folder terpilih, disaring kata kunci. */
        fmBerkas() {
            const q = this.fmCari.trim().toLowerCase();

            return this.fmSemua.filter((b) => {
                if (this.fmFolder && b.folder !== this.fmFolder) return false;
                if (!q) return true;

                return `${b.nama} ${b.file} ${b.konteks} ${b.ext}`.toLowerCase().includes(q);
            });
        },
        /** Judul remah-roti kanan. */
        fmJudul() {
            return (this.fmFolderOpsi.find((f) => f.key === this.fmFolder) || {}).label || 'Semua Berkas';
        },
        /** Berkas yang sedang disorot — jadi isi bilah pratinjau di kaki panel. */
        fmTerpilih() {
            return this.fmBerkas.find((b) => b.id === this.fmSorot) || null;
        },
        /**
         * Kelengkapan: formulir yang SUDAH DIKIRIM, bukan yang punya berkas.
         * Formulir tanpa satu pun pertanyaan dokumen tetap sah lengkap.
         */
        fmLengkap() {
            return (this.profil.formulir || []).filter((f) => !!f.waktuKirim).length;
        },
        fmPersen() {
            const n = (this.profil.formulir || []).length;

            return n ? Math.round((this.fmLengkap / n) * 100) : 0;
        },

        /* ── PANEL BERKAS KANAN ─────────────────────────────────────────────
         *
         * Membaca `fmSemua` yang sama dengan file manager di dalam tab, lalu
         * menambahkan yang dituntut panel ini dan tidak dituntut di sana:
         * WAKTU UNGGAH tiap lembar. Panel ini mengurutkan dari yang terbaru,
         * dan tanpa waktunya satu-satunya urutan yang tersedia adalah urutan
         * formulir — yang justru menaruh berkas terbaru paling bawah.
         *
         * Berkas lama yang belum punya Waktu_Unggah jatuh ke waktu kirim
         * formulirnya. Itu bukan waktu yang persis, tapi ia benar sampai ke
         * hari — dan hari sudah cukup untuk memisahkan berkas pendaftaran dari
         * berkas tahap akhir, yang memang satu-satunya beda yang dicari.
         */
        dokSemua() {
            const kirimPer = new Map(
                (this.profil.formulir || []).map((f) => [String(f.no), f.waktuKirim || '']),
            );

            return this.fmSemua.map((b) => ({
                ...b,
                waktu: b.berkas?.waktu || kirimPer.get(b.folder) || '',
                ukuran: Number(b.berkas?.ukuran || 0),
                status: b.berkas?.status || '',
            }));
        },
        /**
         * Sub-tab panel.
         *
         * Dua kelompok pertama menyaring menurut JENIS berkasnya, bukan menurut
         * formulirnya: "mana fotonya" dan "mana dokumennya" adalah dua
         * pertanyaan yang paling sering dibawa peninjau ke panel ini, dan
         * keduanya memotong lintas formulir. Sisanya satu tab per formulir,
         * untuk yang memang tahu berkasnya datang dari tahap mana.
         *
         * Tab yang kosong tidak digambar — deretan tab yang setengahnya
         * memulangkan "tidak ada berkas" hanya melatih orang mengabaikannya.
         */
        dokTabs() {
            const dok = this.dokSemua.filter((b) => !b.isImage).length;
            const img = this.dokSemua.filter((b) => b.isImage).length;

            const tabs = [
                { key: '', label: 'Semua', judul: 'Semua berkas kandidat', ikon: 'bi-collection-fill', jumlah: this.dokSemua.length },
            ];
            if (dok) tabs.push({ key: 'dok', label: 'Dokumen', judul: 'PDF, Word, dan lembar kerja', ikon: 'bi-file-earmark-text-fill', jumlah: dok });
            if (img) tabs.push({ key: 'img', label: 'Gambar', judul: 'Foto dan hasil pindai', ikon: 'bi-images', jumlah: img });

            const per = new Map();
            this.dokSemua.forEach((b) => per.set(b.folder, (per.get(b.folder) || 0) + 1));
            (this.profil.formulir || []).forEach((f) => {
                const n = per.get(String(f.no)) || 0;
                if (n) {
                    tabs.push({
                        key: String(f.no),
                        label: this.labelPendek(f.label),
                        judul: f.label,
                        ikon: f.sumber === 'PENDAFTARAN' ? 'bi-person-plus-fill' : 'bi-folder-fill',
                        jumlah: n,
                    });
                }
            });
            // Satu tab per tahap yang punya berkas hasil (FGD, wawancara, MCU…).
            (this.profil.tahap || []).forEach((t) => {
                const n = per.get(`t${t.urutan}`) || 0;
                if (n) {
                    tabs.push({
                        key: `t${t.urutan}`,
                        label: `Hasil ${this.labelPendek(t.label)}`,
                        judul: `Berkas hasil — ${t.label}`,
                        ikon: 'bi-clipboard2-check-fill',
                        jumlah: n,
                    });
                }
            });

            return tabs;
        },
        /** Isi daftar panel: sub-tab terpilih, disaring kata kunci, lalu diurutkan. */
        dokDaftar() {
            const q = this.dokCari.trim().toLowerCase();

            const hasil = this.dokSemua.filter((b) => {
                if (this.dokTab === 'dok' && b.isImage) return false;
                if (this.dokTab === 'img' && !b.isImage) return false;
                if (this.dokTab && this.dokTab !== 'dok' && this.dokTab !== 'img' && b.folder !== this.dokTab) return false;
                if (!q) return true;

                return `${b.nama} ${b.file} ${b.konteks} ${b.folderLabel} ${b.ext}`.toLowerCase().includes(q);
            });

            // Yang tak punya waktu sama sekali dibuang ke ujung, bukan diaduk ke
            // tengah: berkas tanpa keterangan waktu paling sering data lama, dan
            // menaruhnya di antara yang baru membuat urutannya terbaca acak.
            const nilai = (b) => {
                const t = Date.parse(String(b.waktu || '').replace(' ', 'T'));

                return Number.isNaN(t) ? -Infinity : t;
            };

            return hasil.slice().sort((a, b) => (this.dokUrutBaru ? nilai(b) - nilai(a) : nilai(a) - nilai(b)));
        },
        /**
         * Ada penyaring yang sedang menyala?
         *
         * Menyalakan lencana kecil di tombol penyaring. Tanpa penanda itu,
         * penyaring yang tertinggal dari kunjungan sebelumnya memotong jalur
         * lembar tanpa satu pun petunjuk di layar — dan daftar yang terpotong
         * terbaca sebagai berkas yang hilang, bukan sebagai berkas yang sedang
         * disembunyikan.
         */
        dokTersaring() {
            return !!this.dokCari.trim() || !!this.dokTab;
        },
        /** Posisi lembar yang sedang dipratinjau di dalam daftar — dasar tombol maju/mundur. */
        dokIndex() {
            return this.dokLihat ? this.dokDaftar.findIndex((b) => b.id === this.dokLihat.id) : -1;
        },
        /** Lembar ini PDF? Dipercayakan pada tipe dari server, ekstensi cuma cadangan. */
        dokPdf() {
            return !!this.dokLihat && (!!this.dokLihat.berkas?.isPdf || String(this.dokLihat.ext).toUpperCase() === 'PDF');
        },
        /**
         * Bisa disematkan di layar?
         *
         * Hanya gambar dan PDF. Word dan lembar kerja tidak bisa dirender
         * peramban mana pun; menyodorkan iframe untuk keduanya menghasilkan
         * kotak kosong yang terbaca sebagai "gagal memuat" — padahal tidak ada
         * yang gagal, memang tidak pernah bisa.
         */
        dokBisaTampil() {
            return !!this.dokLihat && (!!this.dokLihat.isImage || this.dokPdf);
        },
        /** Total pertanyaan dokumen di seluruh formulir — penyebut "7/7 dokumen". */
        dokTotal() {
            return (this.profil.formulir || []).reduce((n, f) => n + this.dokFormulir(f).length, 0);
        },
        /** Dokumen yang benar-benar sudah terlampir — pembilangnya. */
        dokLengkap() {
            return (this.profil.formulir || []).reduce((n, f) => n + this.dokFormulir(f).filter((d) => d.berkas).length, 0);
        },

        /* ── MODAL DETAIL: TAB & ALUR ──────────────────────────────────────── */
        /** Definisi tab modal detail berikut penghitungnya. */
        tabDetail() {
            // DUA tab, bukan tiga. "Alur Seleksi" dulu berdiri sendiri padahal
            // isinya satu blok progres yang tidak pernah dibaca terpisah — ia
            // justru dicari sambil menilai rapor ("tes ini tahap keberapa, masih
            // sisa berapa lagi?"). Sekarang ia duduk di kaki tab Rapor Tes, tempat
            // pertanyaan itu muncul, dan satu klik tab hilang dari alur kerja.
            return [
                { key: 'rapor', label: 'Rapor Tes', ikon: 'bi-clipboard2-check-fill', jumlah: (this.detailKandidat?.tests || []).length },
                { key: 'berkas', label: 'Berkas & Biodata', ikon: 'bi-folder2-open', jumlah: (this.profil.formulir || []).length },
            ];
        },
        /**
         * Linimasa tahap program dengan posisi kandidat ini.
         *
         * Kolomnya dari papan (`kolomTampil`) — alur yang BENAR-BENAR dipakai
         * kandidat, bukan alur yang sekarang menempel di program. Perbandingan
         * memakai nomor urut karena itulah yang dibawa kandidat pada `urutan`;
         * kolom cadangan "alur lama" tidak punya urutan dan jatuh ke akhir.
         */
        alurKandidat() {
            const d = this.detailKandidat;
            if (!d) return [];
            const kini = Number(d.urutan) || 0;
            const tutup = d.statusLamaran !== 'BERJALAN';
            const lulusPenuh = d.statusLamaran === 'LULUS';

            // Tahap MILIK KANDIDAT INI (dimuat bersama berkas) — lebih jujur daripada
            // kolom papan, yang bisa menggabungkan beberapa versi alur, dan membawa
            // id tahap sehingga tiap tahap yang sudah dimulai bisa dibuka.
            if (this.tahapKandidat.length) {
                return this.tahapKandidat.map((t) => {
                    const no = t.urutan;
                    let keadaan = 'nanti';
                    if (lulusPenuh || no < kini) keadaan = 'lewat';
                    else if (no === kini) keadaan = tutup ? 'tutup' : 'kini';

                    const nBerkas = (t.berkas || []).length;
                    const teks = {
                        lewat: {
                            tag: t.bypass ? 'Dilewati' : 'Selesai',
                            catatan: t.hasil
                                ? `${this.labelHasilTahap(t)}${nBerkas ? ` · ${nBerkas} berkas` : ''}`
                                : 'Sudah dilewati',
                        },
                        kini: { tag: 'Berlangsung', catatan: d.hold ? 'Sedang ditahan' : 'Sedang berjalan' },
                        tutup: { tag: this.statusLabel(d.statusLamaran), catatan: 'Perjalanan berakhir di tahap ini' },
                        nanti: { tag: 'Menunggu', catatan: 'Belum dimulai' },
                    }[keadaan];

                    return {
                        kunci: t.id,
                        id: keadaan === 'nanti' ? null : t.id,
                        nomor: String(no).padStart(2, '0'),
                        label: t.label,
                        keadaan,
                        tag: teks.tag,
                        catatan: teks.catatan,
                        catatanEksternal: (this.profil?.catatanKandidat || []).find((x) => x.urutan === no) || null,
                    };
                });
            }

            return this.kolomTampil.map((c, i) => {
                const no = Number(c.urutan) || i + 1;
                let keadaan = 'nanti';
                if (lulusPenuh || no < kini) keadaan = 'lewat';
                else if (no === kini) keadaan = tutup ? 'tutup' : 'kini';

                const teks = {
                    lewat: { tag: 'Selesai', catatan: 'Sudah dilewati' },
                    kini: { tag: 'Berlangsung', catatan: d.hold ? 'Sedang ditahan' : 'Sedang berjalan' },
                    tutup: { tag: this.statusLabel(d.statusLamaran), catatan: 'Perjalanan berakhir di tahap ini' },
                    nanti: { tag: 'Menunggu', catatan: 'Belum dimulai' },
                }[keadaan];

                return {
                    kunci: this.kunciKolom(c),
                    nomor: String(no).padStart(2, '0'),
                    label: c.label,
                    keadaan,
                    tag: teks.tag,
                    catatan: teks.catatan,
                    // Dimuat bersama berkas saat drawer dibuka (worklistBerkas).
                    catatanEksternal: (this.profil?.catatanKandidat || []).find((x) => x.urutan === no) || null,
                };
            });
        },
        /** Seluruh tahap milik kandidat di drawer (worklistBerkas → profil.tahap). */
        tahapKandidat() { return this.profil?.tahap || []; },
        /** Tahap SEBELUM tahap aktif yang sudah diputus — "Hasil tahap sebelumnya". */
        tahapSebelumnya() {
            const kini = Number(this.detailKandidat?.urutan) || 0;

            return this.tahapKandidat.filter((t) => t.urutan < kini && (t.hasil || t.status === 'SELESAI'));
        },
        /** Formulir yang diisi di tahap yang sedang dibuka di modal riwayat. */
        formulirTahapRiwayat() {
            const no = this.tahapRiwayat.data?.tahap?.urutan;

            return no ? (this.profil.formulir || []).filter((f) => Number(f.urutan) === Number(no)) : [];
        },
        persenAlur() {
            const d = this.detailKandidat;
            if (!d?.totalTahap) return 0;
            if (d.statusLamaran === 'LULUS') return 100;

            return Math.min(100, Math.round(((Number(d.urutan) || 1) - 0.5) / d.totalTahap * 100));
        },

        /* ── PILIH BANYAK & JADWAL MASSAL ──────────────────────────────────── */
        /** Kandidat terpilih yang masih ADA di papan (penyaring bisa berubah). */
        barisTerpilih() {
            return this.pelamarTampil.filter((r) => this.terpilih.includes(r.id));
        },
        /** Seluruh baris HALAMAN INI sudah tercentang? — keadaan centang kepala. */
        halamanTercentangPenuh() {
            return this.barisList.length > 0 && this.barisList.every((r) => this.terpilih.includes(r.id));
        },
        /**
         * Sebagian saja — kotak kepala digambar setengah (garis, bukan centang).
         *
         * Perlu dibedakan dari "kosong": tanpa keadaan tengah ini, memilih tiga
         * dari dua puluh membuat kotak kepala tampak persis seperti belum ada
         * yang dipilih, dan menekannya akan terbaca sebagai "pilih semua"
         * padahal ia justru menghapus tiga pilihan tadi.
         */
        halamanTercentangSebagian() {
            return !this.halamanTercentangPenuh && this.barisList.some((r) => this.terpilih.includes(r.id));
        },
        /**
         * Aktivitas yang bisa dijadwalkan massal, dikelompokkan per NAMA.
         *
         * Dikelompokkan per nama — bukan per id — karena tiap kandidat punya
         * baris aktivitasnya sendiri; yang sama di antara mereka hanyalah
         * namanya ("Wawancara HR"). Itulah yang dipilih admin.
         */
        aktivitasTerpilih() {
            const peta = new Map();
            for (const r of this.barisTerpilih) {
                for (const t of r.tests || []) {
                    if (!t.butuhJadwal || t.selesai || t.terkunci) continue;
                    const k = t.label;
                    if (!peta.has(k)) peta.set(k, { label: k, jumlah: 0, wajibLuring: false, jadwalPrivat: false, lokasiPeruntukan: null, modeIzin: null, modeJadwalBawaan: null, biaya: null, konfirmasiAturan: null });
                    const a = peta.get(k);
                    a.jumlah++;
                    // Aturan konfirmasi kehadiran milik TIPE aktivitasnya.
                    a.konfirmasiAturan = a.konfirmasiAturan || t.konfirmasiAturan || null;
                    // Bentuk bawaan & aturan biaya milik TIPE aktivitasnya — satu
                    // nama aktivitas selalu satu tipe, jadi nilainya sama untuk
                    // seluruh kandidat di baris ini.
                    a.modeJadwalBawaan = a.modeJadwalBawaan || t.modeJadwalBawaan || null;
                    a.biaya = a.biaya || t.biaya?.kalimat || null;
                    a.wajibLuring = a.wajibLuring || !!t.wajibLuring;
                    // Izin bentuk jadwal digabung sebagai IRISAN — yang paling
                    // ketat menang, sama seperti wajib tatap muka di atas.
                    // null = tidak dibatasi.
                    if (t.modeIzin) {
                        a.modeIzin = a.modeIzin ? a.modeIzin.filter((m) => t.modeIzin.includes(m)) : [...t.modeIzin];
                    }
                    // Ikut dibawa karena kaki modalnya menjanjikan email: untuk
                    // tipe berjadwal privat tak satu pun undangan dikirim.
                    a.jadwalPrivat = a.jadwalPrivat || !!t.jadwalPrivat;
                    // Peruntukan lokasinya ikut dibawa — satu nama aktivitas
                    // selalu satu tipe tahap, jadi nilainya sama untuk seluruh
                    // kandidat yang terkumpul di baris ini.
                    a.lokasiPeruntukan = a.lokasiPeruntukan || t.lokasiPeruntukan || null;
                }
            }

            return [...peta.values()].sort((a, b) => b.jumlah - a.jumlah);
        },
        aktivitasMassalDef() {
            return this.aktivitasTerpilih.find((a) => a.label === this.massalAktivitas) || null;
        },
        massalWajibLuring() { return !!this.aktivitasMassalDef?.wajibLuring; },
        /**
         * Bentuk jadwal yang sah untuk aktivitas massal terpilih — aturan yang
         * sama dengan jendela satuan (modeUntuk), dikurangi bentuk yang menuntut
         * NOMOR per kandidat: satu nomor untuk seluruh angkatan tidak pernah benar.
         */
        modeMassalDipakai() {
            return this.modeUntuk(this.aktivitasMassalDef).filter((m) => !m.butuhKontak);
        },
        massalModeDef() { return (this.modeJadwal || []).find((m) => m.kode === this.massalMode) || null; },
        biayaMassal() { return this.aktivitasMassalDef?.biaya || null; },
        /** Berapa sasaran yang SUDAH punya jadwal — menggantinya butuh alasan. */
        massalAdaJadwal() { return this.sasaranMassal.filter((x) => !!x.t.jadwal).length; },
        /** Sasaran yang sudah punya surat pengantar sendiri. */
        massalPunyaSurat() { return this.sasaranMassal.filter((x) => !!x.t.jadwal?.surat?.length).length; },
        /** Batas JUMLAH ukuran surat pengantar satu jadwal (byte). */
        suratMaksByte() { return (this.jadwalFitur?.suratMaksMb || 5) * 1024 * 1024; },
        massalSuratTotal() { return this.massalSuratDaftar.reduce((n, s) => n + (Number(s.ukuran) || 0), 0); },
        massalSuratSisa() { return this.suratMaksByte - this.massalSuratTotal; },
        jadwalSuratTotal() { return this.jadwalSuratDaftar.reduce((n, s) => n + (Number(s.ukuran) || 0), 0); },
        jadwalSuratSisa() { return this.suratMaksByte - this.jadwalSuratTotal; },
        /** Surat kolektif WAJIB? Ya bila ada sasaran yang belum punya surat sama sekali. */
        massalButuhSurat() {
            return !!this.massalModeDef?.butuhSurat && this.sasaranMassal.some((x) => !x.t.jadwal?.surat?.length);
        },
        massalMapsSalah() { return !petaGoogleSah(this.massalMapsUrl); },
        /** Batas sekali kirim ulang — sama dengan batas massal lain. */
        batasKirimUlang() { return this.jadwalFitur?.kirimUlangMaks || this.batasPutusMassal || 5; },
        /**
         * Aktivitas terpilih yang surelnya bisa dikirim ulang, per NAMA —
         * pola yang sama dengan aktivitasTerpilih untuk jadwal massal.
         */
        aktivitasKirimUlang() {
            const peta = new Map();
            for (const r of this.barisTerpilih) {
                for (const t of r.tests || []) {
                    if (!this.bisaKirimUlangBaris(t)) continue;
                    if (!peta.has(t.label)) peta.set(t.label, { label: t.label, jumlah: 0 });
                    peta.get(t.label).jumlah++;
                }
            }

            return [...peta.values()].sort((a, b) => b.jumlah - a.jumlah);
        },
        sasaranKirimUlang() {
            return this.barisTerpilih
                .map((r) => ({ r, t: (r.tests || []).find((x) => x.label === this.kirimUlangAktivitas && this.bisaKirimUlangBaris(x)) }))
                .filter((x) => !!x.t);
        },
        lebihanKirimUlang() { return Math.max(0, this.sasaranKirimUlang.length - this.batasKirimUlang); },
        bolehKirimUlangMassal() {
            return !!this.kirimUlangAktivitas && this.sasaranKirimUlang.length > 0 && !this.lebihanKirimUlang;
        },
        /** Riwayat batas yang terbuka memang milik kandidat di drawer sekarang. */
        riwayatBatasBuka() {
            return this.riwayatBatas.buka && this.riwayatBatas.tahapId === this.detailKandidat?.tahapId;
        },
        /** Terpilih yang sedang mengisi formulir tahap — sasaran tombol "Jadwal". */
        bisaBatasMassal() {
            return this.barisTerpilih.filter((r) => r.tahapId && r.batas && !r.batas.terkirim);
        },
        salahKolomBaru() { return this.salahJadwalBaru(this.batasKolomBuka, this.batasKolomTgl); },
        /** Superadmin boleh MENGEDIT jadwal (boleh mundur) — admin hanya memperpanjang. */
        superadmin() { return this.$page?.props?.careerAuth?.role === 'SUPERADMIN'; },
        kolomUbahLampau() { return !!this.batasKolomTgl && (keDate(this.batasKolomTgl) || 0) < new Date(); },
        kandUbahLampau() { return !!this.batasKandTgl && (keDate(this.batasKandTgl) || 0) < new Date(); },
        bolehSimpanBatasKolom() {
            const col = this.batasKolomCol;
            if (!col || this.batasSibuk) return false;
            if (this.batasKolomLangkah === 'LEPAS') return true;
            if (this.batasKolomLangkah === 'UBAH') {
                return this.superadmin && !!this.batasKolomBuka && !!this.batasKolomTgl
                    && this.batasKolomTgl > this.batasKolomBuka && this.batasKolomAlasan.trim().length >= 5;
            }
            if (this.batasKolomLangkah === 'PERPANJANG') return !!col.batasProgram && !salahPerpanjang(col.batasProgram, this.perp);

            return !!this.batasKolomBuka && !!this.batasKolomTgl && !this.salahKolomBaru;
        },
        /** Kandidat terpilih yang belum berjadwal — hanya bisa DIATUR. */
        batasKandBelum() { return this.batasKandTarget.filter((r) => !r.batas.batas); },
        /** Yang sudah berjadwal — hanya bisa DIPERPANJANG. */
        batasKandSudah() { return this.batasKandTarget.filter((r) => !!r.batas.batas); },
        /** Yang tahapnya di Master Alur sudah "Tanpa jadwal" — bisa DILEPAS. */
        batasKandLepas() { return this.batasKandTarget.filter((r) => this.kolomDilepas(this.kolomDari(r))); },
        /** Superadmin: seluruh yang terikat jadwal bisa DIEDIT. */
        batasKandUbah() { return this.superadmin ? this.batasKandTarget : []; },
        /** Batas sekarang untuk pratinjau; null bila banyak (masing-masing dari batasnya). */
        batasKandLama() { return this.batasKandSudah.length === 1 ? this.batasKandSudah[0].batas.batas : null; },
        salahKandBaru() { return this.salahJadwalBaru(this.batasKandBuka, this.batasKandTgl); },
        bolehSimpanBatasKand() {
            if (this.batasSibuk) return false;
            if (this.batasKandLangkah === 'LEPAS') return this.batasKandLepas.length > 0;
            if (this.batasKandLangkah === 'UBAH') {
                return this.batasKandUbah.length > 0 && !!this.batasKandTgl
                    && (!this.batasKandBuka || this.batasKandTgl > this.batasKandBuka)
                    && this.batasKandAlasan.trim().length >= 5;
            }
            if (this.batasKandLangkah === 'ATUR') return this.batasKandBelum.length > 0 && !!this.batasKandTgl && !this.salahKandBaru;

            return this.batasKandSudah.length > 0 && !salahPerpanjang(this.batasKandLama, this.perp);
        },
        peruntukanMassal() {
            const kode = this.aktivitasMassalDef?.lokasiPeruntukan;

            return kode ? this.daftarPeruntukan.find((p) => p.kode === kode) || null : null;
        },
        lokasiUntukMassal() {
            const kode = this.aktivitasMassalDef?.lokasiPeruntukan;
            if (!kode) return this.daftarLokasi;

            return this.daftarLokasi.filter((l) => (l.peruntukan || []).includes(kode));
        },
        /** Pasangan kandidat→aktivitas untuk nama aktivitas yang dipilih. */
        sasaranMassal() {
            return this.barisTerpilih
                .map((r) => ({
                    r,
                    t: (r.tests || []).find((x) => x.label === this.massalAktivitas && x.butuhJadwal && !x.selesai && !x.terkunci),
                }))
                .filter((x) => !!x.t);
        },
        massalDilewati() {
            return this.barisTerpilih.filter((r) => !(r.tests || []).some(
                (x) => x.label === this.massalAktivitas && x.butuhJadwal && !x.selesai && !x.terkunci,
            ));
        },
        /**
         * Tiga jam pertama saja — contoh irama, bukan daftar lengkap.
         *
         * Daftar utuhnya ada di pratinjau bawah, tapi itu jauh di bawah kolom
         * catatan: yang sedang mengetik durasi & jeda tidak melihatnya, dan
         * justru di detik itulah ia perlu tahu apa arti angkanya. Diambil dari
         * pratinjau yang sama supaya keduanya mustahil berselisih.
         */
        ritmeMassal() {
            if (!this.massalMulai) return [];
            const awal = new Date(String(this.massalMulai).replace(' ', 'T'));
            if (Number.isNaN(awal.getTime())) return [];

            // Rumus yang SAMA dengan pratinjauMassal dan dengan server:
            // mulai = awal + urutan × (durasi + jeda). Jam saja, tanpa tanggal —
            // ini contoh irama, dan tanggalnya sudah terbaca di kolom di atas.
            const langkah = (Number(this.massalDurasi) || 0) + (Number(this.massalJeda) || 0);
            const jumlah = Math.min(3, Math.max(2, this.sasaranMassal.length));

            return Array.from({ length: jumlah }, (_, i) => new Date(awal.getTime() + i * langkah * 60000)
                .toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));
        },
        /** Siapa dapat jam berapa — dihitung dengan rumus yang SAMA dengan server. */
        pratinjauMassal() {
            // Berrentang tanggal: semua mendapat rentang yang sama.
            if (this.massalModeDef?.batasWaktu) {
                if ((this.massalRentang || []).length !== 2 || !this.massalAktivitas) return [];
                const rentang = fmtRentang(this.massalRentang[0], this.massalRentang[1]);

                return this.sasaranMassal.map((x) => ({ id: x.r.id, pelamar: x.r.pelamar, jam: rentang }));
            }
            if (!this.massalMulai || !this.massalAktivitas) return [];
            const awal = new Date(String(this.massalMulai).replace(' ', 'T'));
            if (Number.isNaN(awal.getTime())) return [];
            const langkah = (Number(this.massalDurasi) || 30) + (Number(this.massalJeda) || 0);

            return this.sasaranMassal.map((x, i) => {
                const d = this.massalPola === 'BERGILIR'
                    ? new Date(awal.getTime() + i * langkah * 60000)
                    : awal;

                return {
                    id: x.r.id,
                    pelamar: x.r.pelamar,
                    jam: d.toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }),
                };
            });
        },
        bolehSimpanMassal() {
            const m = this.massalModeDef;
            if (!m || !this.massalAktivitas || !this.sasaranMassal.length) return false;
            // BATAS SEKALI KIRIM. Diperiksa dari `sasaranMassal` — yang
            // benar-benar akan menerima undangan — bukan dari jumlah centang:
            // yang tidak punya aktivitas ini dilewati dan tidak diemail.
            if (this.lebihanSasaranMassal) return false;
            if (this.massalAdaJadwal && this.jadwalFitur?.jejak
                && this.massalAlasan.trim().length < (this.jadwalFitur?.alasanMin || 5)) return false;
            // VENDOR bernama (tautan petanya, bila diisi, Google Maps); surat
            // kolektif bila ada sasaran yang belum punya surat.
            if (m.butuhTempat && (!this.massalLokasiNama.trim() || this.massalMapsSalah)) return false;
            if (m.butuhSurat && this.massalButuhSurat && !this.massalSuratDaftar.length) return false;
            if (this.massalSuratSisa < 0) return false;
            if (this.suratUnggahDi) return false;
            if (m.batasWaktu) return (this.massalRentang || []).length === 2;
            if (!this.massalMulai) return false;
            if (this.massalMulaiLewat) return false;
            if (m.butuhTautan) return !!this.massalLink.trim();
            if (!m.butuhLokasi) return true;
            if (!this.massalLokasiId) return false;
            // Tempat luar-master: syaratnya sama persis dengan jendela satuan
            // dan dengan server.
            if (this.massalLokasiId === this.LOKASI_LAIN) {
                if (!this.massalLokasiNama.trim()) return false;
                if (this.peruntukanMassal?.wajibAlamatLainnya && !this.massalLokasiAlamat.trim()) return false;
            }

            return true;
        },
        /** Definisi hasil yang sedang dipilih — semua labelnya dari master. */
        putusDef() { return this.hasilKeputusan.find((h) => h.kode === this.putusHasil) || null; },
        /**
         * Tahap yang sedang diputus adalah TITIK TUNTAS?
         *
         * Di titik itu "Loloskan" bukan lagi meneruskan ke tahap berikutnya —
         * kandidat DITERIMA bekerja. Kata yang dipakai ikut berubah, karena
         * "Loloskan" pada langkah terakhir terbaca seolah masih ada lanjutannya.
         */
        putusTuntas() {
            // DARI KANDIDATNYA SENDIRI, bukan dari kolom master.
            //
            // Dulu ini mencari kolom yang NOMOR URUT-nya sama lalu membaca flag
            // di sana. Nomor urut cuma benar selama alur tak pernah berubah:
            // begitu alur disunting (baris dipakai ulang per urutan) atau
            // program diarahkan ke alur lain, "kolom ke-6" bisa tahap yang sama
            // sekali berbeda — dan tombol "Loloskan" berubah arti diam-diam
            // menjadi "Terima Kandidat", atau sebaliknya.
            //
            // `perilaku` dibekukan bersama tahapnya, jadi tetap benar apa pun
            // yang terjadi pada master sesudahnya.
            return !!this.putusTarget?.perilaku?.tuntas;
        },
        putusJudul() {
            if (!this.putusDef) return 'Keputusan';

            return this.putusDef.lolos && this.putusTuntas
                ? 'Terima Kandidat — Diterima Bekerja'
                : `${this.putusDef.labelTombol} — ${this.putusDef.nama}`;
        },
        putusLabelKonfirm() {
            if (this.putusDef?.lolos && this.putusTuntas) return 'Ya, Terima Kandidat';

            return this.putusDef?.labelKonfirmasi || 'Ya, Lanjutkan';
        },
        /** Tahap aktif kandidat membawa penawaran yang harus dijawab? */
        tahapPenawaran() {
            return !!this.detailKandidat?.perilaku?.penawaran;
        },
        /** Tahap aktif adalah titik tuntas — meloloskan di sini = diterima bekerja. */
        tahapTuntas() {
            return !!this.detailKandidat?.perilaku?.tuntas;
        },
        /**
         * Keputusan PERUSAHAAN yang boleh muncul untuk kandidat ini.
         *
         * Talent Pool hanya pada tahap yang memang di-cut-off ke sana, dan
         * Loloskan hilang saat kuota tahap akhir sudah penuh — dua aturan yang
         * sudah ada sebelumnya, kini diterapkan pada daftar dari master.
         */
        putusanPerusahaan() {
            if (!this.detailKandidat) return [];

            return this.hasilKeputusan.filter((h) => {
                if (h.olehKandidat) return false;
                if (h.lolos) return !this.kuotaBlokir(this.detailKandidat);
                if (h.talentPool && !h.kirimEmail) return this.bolehTalentPool(this.detailKandidat);

                return true;
            });
        },
        /**
         * Keputusan yang datangnya DARI KANDIDAT.
         *
         * "Menolak penawaran" hanya masuk akal bila memang ada penawaran, jadi
         * ia mengikuti penanda tahap dari Master Tipe Tahap. "Mengundurkan diri"
         * berlaku di tahap mana pun — kandidat bisa mundur kapan saja.
         */
        putusanKandidat() {
            if (!this.detailKandidat) return [];

            // Pada tahap berpenawaran, TIDAK ADA jawaban kandidat yang masuk akal
            // sebelum penawarannya benar-benar diajukan — belum ada yang bisa
            // ditolak. Di tahap lain, mundur tetap boleh kapan saja.
            if (this.tahapPenawaran && !this.detailKandidat.penawaranDiajukan) return [];

            return this.hasilKeputusan.filter((h) => {
                if (!h.olehKandidat) return false;
                // "Menolak penawaran" hanya masuk akal bila ada penawarannya.
                if (h.kode === 'DITOLAK_KANDIDAT') {
                    // Dan bila negosiasinya BERJADWAL, penolakan itu dicatat di
                    // modal kehadirannya — di sanalah peristiwanya terjadi,
                    // lengkap dengan alasan dan penutupan lamarannya. Dua pintu
                    // menuju keadaan yang sama memaksa admin menebak mana yang
                    // benar; yang tak berjadwal (Surat Penawaran / `infoSaja`)
                    // tidak punya modal itu, jadi tombolnya tetap dibutuhkan.
                    return this.tahapPenawaran && !this.jawabanDiCatatKehadiran;
                }

                // MENGUNDURKAN DIRI TETAP ADA DI TAHAP NEGOSIASI.
                //
                // Dulu seluruh tombol ini padam begitu tahapnya punya negosiasi
                // berjadwal — dan mundur pun ikut hilang bersamanya. Padahal
                // keduanya bukan peristiwa yang sama: menolak penawaran adalah
                // jawaban ATAS ANGKA yang kita ajukan, mundur adalah kandidat
                // meninggalkan proses (dapat tempat lain, alasan pribadi) dan
                // itu bisa terjadi kapan saja — termasuk sebelum negosiasinya
                // sempat berlangsung. Tanpa tombol ini, satu-satunya cara
                // mencatatnya adalah memilih "menolak penawaran" yang tidak
                // pernah ada, atau "Tidak Lolos" yang menyalahkan orang yang
                // pergi baik-baik.
                return true;
            });
        },
        /**
         * Jawaban kandidat ditangkap di modal kehadiran, bukan di tombol tahap?
         *
         * Benar bila tahap aktif punya aktivitas penawaran yang BERJADWAL —
         * `infoSaja` menandai penawaran berdokumen (surat penawaran) yang tidak
         * punya jendela kehadiran, jadi ia tidak dihitung.
         */
        jawabanDiCatatKehadiran() {
            return (this.detailKandidat?.tests || []).some((t) => t.penawaran && !t.infoSaja);
        },
        /** Menggugurkan menuntut centang persetujuan dulu; yang lain langsung boleh. */
        /** DARING wajib tautan, LURING wajib lokasi; keduanya wajib waktu mulai. */
        /**
         * Peruntukan yang berlaku untuk aktivitas yang sedang dijadwalkan.
         *
         * null = tipe ini tidak dibatasi (phone screen, negosiasi) — seluruh
         * lokasi boleh, persis seperti sebelum pembagian ini ada.
         */
        peruntukanJadwal() {
            const kode = this.jadwalTarget?.lokasiPeruntukan;

            return kode ? this.daftarPeruntukan.find((p) => p.kode === kode) || null : null;
        },
        /** Lokasi yang boleh dipilih untuk aktivitas ini. */
        lokasiUntukJadwal() {
            const kode = this.jadwalTarget?.lokasiPeruntukan;
            if (!kode) return this.daftarLokasi;

            return this.daftarLokasi.filter((l) => (l.peruntukan || []).includes(kode));
        },
        bolehLokasiLain() { return !!this.peruntukanJadwal?.izinkanLainnya; },
        /* ══ PEMASOK PANEL PROSES ══════════════════════════════════════════
           Bentuk datanya disamakan untuk kedua pekerjaan, sehingga panelnya
           tidak perlu tahu ia sedang menampilkan keputusan massal atau
           unduhan berkas. Yang berbeda cuma isi barisnya. */
        ringkasBorong() {
            if (!this.borong) return '';
            const b = this.borong;

            return b.jalan
                ? `${b.selesai + b.gagal} dari ${b.total} kandidat`
                : `${b.selesai} selesai · ${b.gagal} gagal`;
        },
        nadaBorong() {
            if (!this.borong) return 'jalan';
            const b = this.borong;
            if (b.jalan) return 'jalan';
            if (!b.gagal) return 'ok';

            return b.selesai ? 'separuh' : 'gagal';
        },
        persenBorong() {
            const b = this.borong;

            return b && b.total ? Math.round(((b.selesai + b.gagal) / b.total) * 100) : 0;
        },
        /**
         * SATU BARIS PER PROGRAM, nama kandidat di dalamnya.
         *
         * Gelombang selalu lahir dari satu papan program, jadi dalam praktiknya
         * grupnya satu — tapi bentuknya tetap daftar, supaya panel yang sama
         * bisa menampung dua gelombang berjalan tanpa diubah.
         */
        grupBorong() {
            const b = this.borong;
            if (!b) return [];

            return [{
                id: b.gelombang,
                judul: b.program || 'Program ini',
                sub: b.tahap || null,
                total: b.total || 0,
                selesai: b.selesai || 0,
                gagal: b.gagal || 0,
                menunggu: b.menunggu || 0,
                baris: (b.baris || []).map((x) => ({
                    id: x.tahapId,
                    nama: x.nama,
                    keadaan: x.keadaan,
                    pesan: x.pesan,
                    ket: x.hasil ? `Diputus: ${x.hasil}` : 'Selesai',
                })),
            }];
        },
        unduhanJalan() {
            return this.unduhan.some((u) => u.keadaan === 'siap' || u.keadaan === 'unduh');
        },
        nadaUnduhan() {
            if (this.unduhanJalan) return 'jalan';
            const gagal = this.unduhan.filter((u) => u.keadaan === 'gagal').length;
            if (!gagal) return 'ok';

            return gagal < this.unduhan.length ? 'separuh' : 'gagal';
        },
        persenUnduhan() {
            if (!this.unduhan.length) return 0;
            const jumlah = this.unduhan.reduce((t, u) => t + Math.min(100, u.persen || 0), 0);

            return Math.round(jumlah / this.unduhan.length);
        },
        ringkasUnduhan() {
            const selesai = this.unduhan.filter((u) => u.keadaan === 'selesai').length;

            return `${selesai} dari ${this.unduhan.length} berkas`;
        },
        /** Judul panel unduhan — menyebut jumlahnya, bukan sekadar "Unduhan". */
        judulUnduhan() {
            const jalan = this.unduhan.filter((u) => u.keadaan === 'siap' || u.keadaan === 'unduh').length;

            return jalan
                ? `Menyiapkan ${jalan} berkas…`
                : `${this.unduhan.length} berkas selesai`;
        },
        /**
         * Tempat yang sedang dipilih — dari master ATAU yang diketik sendiri.
         *
         * Yang diketik dibentuk MENYERUPAI baris master (termasuk peta dari nama
         * + alamatnya), sehingga kartu pratinjau di bawahnya tidak perlu tahu
         * bedanya dan rekruter tetap bisa memastikan titiknya sebelum undangan
         * terkirim.
         */
        lokasiTerpilih() {
            if (this.jadwalLokasiId === this.LOKASI_LAIN) {
                const nama = (this.jadwalLokasiNama || '').trim();
                if (!nama) return null;

                // TANPA PETA. Titiknya belum pernah diverifikasi siapa pun, dan
                // peta hasil tebakan tampil sama persis seperti peta yang sudah
                // dipastikan — rekruter lalu mengira ia sudah memeriksa
                // tempatnya padahal belum. Yang dijanjikan ke kandidat cukup
                // alamat yang ia ketik sendiri.
                return {
                    nama,
                    alamat: (this.jadwalLokasiAlamat || '').trim() || null,
                    kontakTelp: null,
                    lepas: true,
                    mapsEmbed: null,
                    mapsUrl: null,
                };
            }

            return this.daftarLokasi.find((l) => l.id === this.jadwalLokasiId) || null;
        },
        /** Bentuk yang boleh dipilih: tipe wajib-luring hanya menerima yang luring. */
        modeJadwalDipakai() {
            return this.modeUntuk(this.jadwalTarget);
        },
        modeJadwalDef() { return (this.modeJadwal || []).find((m) => m.kode === this.jadwalMode) || null; },
        /**
         * Aktivitas yang dijadwalkan MEMINTA konfirmasi kehadiran? Dari tipenya
         * (Master Tipe Tahap → konfirmasiAturan) dan bentuknya — rentang tanggal
         * (MCU mandiri/vendor) tidak punya jam janji temu untuk dikonfirmasi.
         */
        konfJadwalAktif() {
            return !!this.jadwalTarget?.konfirmasiAturan && !!this.modeJadwalDef && !this.modeJadwalDef.batasWaktu;
        },
        /** Jawaban kandidat yang ikut direset bila waktu/tempat diubah. */
        konfJadwalLama() {
            const k = this.jadwalTarget?.konfirmasi;

            return k && ['AKAN_HADIR', 'JADWAL_LAIN'].includes(k.status) ? k : null;
        },
        /** Lencana konfirmasi per kartu (id lamaran → konfirmasi terpenting). */
        konfPerKartu() {
            // DITUNDA menunggu tindakan tim (jadwal pengganti) — di atas yang
            // menunggu jawaban kandidat.
            const URUT = { JADWAL_LAIN: 1, MUNDUR: 2, DITUNDA: 3, TANPA_JAWABAN: 4, MENUNGGU: 5, AKAN_HADIR: 6 };
            const PENDEK = {
                JADWAL_LAIN: 'jadwal lain', MUNDUR: 'mundur', DITUNDA: 'ditunda', TANPA_JAWABAN: 'tak menjawab',
                MENUNGGU: 'belum konfirmasi', AKAN_HADIR: 'akan hadir',
            };
            const out = {};
            for (const r of this.detail?.pelamar || []) {
                let pilih = null;
                for (const t of r.tests || []) {
                    const k = t.konfirmasi;
                    if (!k || t.selesai) continue;
                    if (!pilih || (URUT[k.status] || 9) < (URUT[pilih.k.status] || 9)) pilih = { k, t };
                }
                if (pilih) out[r.id] = { ...pilih.k, aktivitas: pilih.t.label, pendek: PENDEK[pilih.k.status] || pilih.k.label };
            }

            return out;
        },
        massalKonfAktif() {
            return !!this.aktivitasMassalDef?.konfirmasiAturan && !!this.massalModeDef && !this.massalModeDef.batasWaktu;
        },
        /** Aturan biaya aktivitas yang sedang dijadwalkan (null = tak ada). */
        biayaJadwal() { return this.jadwalTarget?.biaya?.kalimat || null; },
        /** Aktivitas di jendela Hadir berbatas waktu (tanpa "kehadiran")? */
        hadirBerbatas() { return !!this.hadirTarget?.jadwal?.batasWaktu; },
        /**
         * Alasan perubahan diminta? Hanya bila jadwalnya SUDAH pernah dikirim
         * dan tabel jejaknya ada — aturan yang sama dengan server.
         */
        butuhAlasanJadwal() { return !!this.jadwalTarget?.jadwal && !!this.jadwalFitur?.jejak; },
        /** Tautan peta vendor diisi tapi bukan Google Maps? Cermin server. */
        jadwalMapsSalah() { return !petaGoogleSah(this.jadwalMapsUrl); },
        /** Format surat pengantar untuk pemilih berkas (".pdf,.jpg,…"). */
        suratAccept() {
            return (this.jadwalFitur?.suratFormat || ['pdf']).map((x) => '.' + x).join(',');
        },
        bolehPerpanjang() {
            return !!this.perpanjangTanggal
                && this.perpanjangAlasan.trim().length >= (this.jadwalFitur?.alasanMin || 5);
        },
        /**
         * Waktu selesai mendahului waktu mulai?
         *
         * Dibandingkan sebagai TEKS, bukan Date. Keduanya datang dari pemilih
         * yang sama dengan `value-format` "YYYY-MM-DD HH:mm:ss" — format yang
         * urutan abjadnya sama dengan urutan waktunya — jadi merakit dua objek
         * Date hanya menambah satu tempat untuk salah tafsir zona waktu.
         */
        jadwalSelesaiSalah() {
            return !!this.jadwalMulai && !!this.jadwalSelesai && this.jadwalSelesai <= this.jadwalMulai;
        },
        /**
         * Waktu mulai yang DIPILIH sudah lewat. Jadwal yang sudah berjalan dan
         * jamnya tidak disentuh (mis. hanya membetulkan tautan) tetap boleh
         * disimpan — yang ditolak hanya memilih waktu lampau. Server memeriksa
         * hal yang sama (jadwalLampau di LamaranController).
         */
        jadwalMulaiLewat() {
            const m = keDate(this.jadwalMulai);
            if (!m) return false;
            const asal = keDate(this.jadwalMulaiAsal);
            if (asal && Math.floor(asal.getTime() / 60000) === Math.floor(m.getTime() / 60000)) return false;

            // Ketelitian MENIT, sama dengan server (startOfMinute).
            return m.getTime() < Math.floor(Date.now() / 60000) * 60000;
        },
        massalMulaiLewat() {
            const m = keDate(this.massalMulai);

            return !!m && m.getTime() < Math.floor(Date.now() / 60000) * 60000;
        },
        /** Syaratnya dibaca dari FLAG mode terpilih — sama persis dengan server. */
        bolehSimpanJadwal() {
            const m = this.modeJadwalDef;
            if (!m) return false;
            if (this.butuhAlasanJadwal && this.jadwalAlasan.trim().length < (this.jadwalFitur?.alasanMin || 5)) return false;
            // VENDOR: namanya wajib, tautan petanya (bila diisi) Google Maps.
            // Surat pengantar: yang baru, atau yang sudah melekat pada jadwal ini.
            if (m.butuhTempat && (!(this.jadwalLokasiNama || '').trim() || this.jadwalMapsSalah)) return false;
            if (m.butuhSurat && (!this.jadwalSuratDaftar.length || this.jadwalSuratSisa < 0)) return false;
            if (this.suratUnggahDi) return false;
            // Berrentang tanggal: rentangnya lengkap — tanpa jam, tanpa tautan.
            if (m.batasWaktu) return (this.jadwalRentang || []).length === 2;
            if (!this.jadwalMulai) return false;
            if (this.jadwalMulaiLewat) return false;
            if (this.jadwalSelesaiSalah) return false;
            if (m.butuhTautan) return !!this.jadwalLink.trim();
            if (m.butuhLokasi) {
                if (!this.jadwalLokasiId) return false;
                // Tempat di luar master: nama wajib, alamat wajib bila masternya
                // bilang begitu. Dicermin dari `periksaPeruntukanLokasi` di
                // server — tombol yang menyala lalu ditolak 422 membuat rekruter
                // mengira sistemnya rusak.
                if (this.jadwalLokasiId === this.LOKASI_LAIN) {
                    if (!(this.jadwalLokasiNama || '').trim()) return false;
                    if (this.peruntukanJadwal?.wajibAlamatLainnya && !(this.jadwalLokasiAlamat || '').trim()) return false;
                }

                return true;
            }
            if (m.butuhKontak) return !!this.jadwalKontak.trim();

            return true;
        },
        /** Alasan wajib? Dari master (Butuh_Alasan), bukan daftar kode di sini. */
        alasanWajib() { return !!this.putusDef?.butuhAlasan; },
        /**
         * Contoh isian catatan — mengikuti jenis aktivitasnya.
         *
         * Contoh yang tetap ("mis. Komunikatif, hasil FGD baik") menyuruh petugas
         * reference check menuliskan kesan FGD atas percakapan telepon dengan
         * mantan atasan. Contoh yang salah lebih menyesatkan daripada tidak ada
         * contoh: ia terbaca sebagai perintah tentang APA yang harus ditulis.
         */
        contohCatatan() {
            const kode = this.catatTarget?.tipe || '';

            if (kode === 'REFERENCE_CHECK') {
                return 'mis. Atasan langsung di PT X membenarkan masa kerja & jabatan; bersedia mempekerjakan kembali';
            }
            if (kode === 'BACKGROUND_CHECK') {
                return 'mis. Ijazah S1 terverifikasi ke universitas; riwayat kerja cocok; tidak ada temuan';
            }
            if (kode === 'MCU') {
                return 'mis. Hasil fit to work, tidak ada catatan khusus dari dokter pemeriksa';
            }

            return 'mis. Komunikatif, jawabannya runtut — direkomendasikan lanjut';
        },
        labelCatatanPutus() {
            if (!this.putusDef) return 'Catatan';
            if (this.putusDef.olehKandidat) return 'Alasan yang disampaikan kandidat';

            return this.putusDef.butuhAlasan ? `Alasan ${this.putusDef.nama}` : 'Catatan Keputusan';
        },
        placeholderCatatanPutus() {
            if (!this.putusDef) return '';
            if (this.putusDef.olehKandidat) return 'mis. sudah menerima tawaran di tempat lain';
            if (this.putusDef.talentPool && !this.putusDef.kirimEmail) return 'mis. kuat wawancara, cocok untuk Finance';

            return this.putusDef.butuhAlasan
                ? 'Dikirim sebagai dasar keputusan — tulis alasannya'
                : 'mis. sesuai rekomendasi sistem / alasan khusus';
        },
        /** Nada kartu ringkasan: hijau lolos, kuning talent pool, merah sisanya. */
        nadaPutus() {
            if (!this.putusDef) return '';
            if (this.putusDef.lolos) return 'is-lulus';

            return this.putusDef.talentPool && !this.putusDef.kirimEmail ? 'is-talent_pool' : 'is-gugur';
        },
        /**
         * Centang sadar hanya untuk keputusan yang MENUTUP proses DAN mengabari
         * kandidat — di situlah satu klik keliru tak bisa ditarik kembali.
         * Keputusan dari kandidat tidak diminta centang: yang dicatat adalah
         * kabar yang sudah terjadi, bukan tindakan yang baru akan dilakukan.
         */
        butuhCentang() { return !!this.putusDef && !this.putusDef.lolos && this.putusDef.kirimEmail && !this.putusDef.olehKandidat; },

        /**
         * Aktivitas tahap ini yang BELUM selesai.
         *
         * Dihitung dari rapor tahap yang sedang ditampilkan — sumber yang sama
         * dengan yang dibaca admin di layar, jadi angka di peringatan mustahil
         * berbeda dari daftar di atasnya.
         */
        /**
         * Aktivitas yang benar-benar MASIH MENUNGGU SESUATU.
         *
         * Dulu ukurannya `!t.selesai` — Flag_Selesai. Itu keliru untuk tahap
         * BERAKTIVITAS TUNGGAL, dan salahnya berbentuk jalan buntu:
         *
         *   Pada tahap satu aktivitas, "Catat Hasil" memang sengaja tidak ada —
         *   keputusan tahap itu sendiri yang mewakilinya (lihat `dicatatTim`).
         *   Akibatnya Flag_Selesai aktivitasnya TIDAK PERNAH bisa jadi 'Y'
         *   sebelum tahapnya diputus. Admin yang sudah menetapkan Hadir dan
         *   menulis catatan tetap disodori centang "saya sadar aktivitas ini
         *   belum selesai" — untuk sesuatu yang mustahil diselesaikan lebih
         *   dulu. Yang ia baca: sistem menganggapnya belum kerja, padahal
         *   sudah.
         *
         * Ukurannya sekarang `tuntas` — apa yang MASIH DITUNGGU dari aktivitas
         * itu, dihitung server dengan aturan yang sama persis dengan gerbang
         * keputusannya. Wawancara yang kehadirannya sudah ditetapkan dan tak
         * punya hasil terpisah untuk dicatat = tuntas, dan tak ada yang perlu
         * diakui.
         */
        aktivitasBelumSelesai() {
            return (this.putusTarget?.tests || []).filter((t) => !(t.tuntas ?? t.selesai));
        },
        /**
         * Perlu pengakuan sadar sebelum memutus?
         *
         * TIDAK berlaku bila seluruh aktivitas sudah selesai — di situ tak ada
         * yang perlu diakui, dan centang yang selalu muncul akan berhenti
         * dibaca lalu dicentang refleks.
         *
         * Juga tidak berlaku untuk keputusan yang datang DARI KANDIDAT: orang
         * yang mengundurkan diri tidak akan menyelesaikan sisa aktivitasnya,
         * jadi menahan admin dengan peringatan itu tak ada gunanya.
         */
        perluCentangAktivitas() {
            return !!this.putusDef
                && !this.putusDef.olehKandidat
                && this.aktivitasBelumSelesai.length > 0;
        },
        hariIni() { return new Date().toISOString().slice(0, 10); },
        /**
         * Alasan dianggap terisi bila melewati batas minimum, bukan sekadar
         * tidak kosong. "-" atau "ok" lolos uji "tidak kosong" tapi tidak
         * menjelaskan apa pun saat riwayat ini dibaca berbulan-bulan kemudian.
         */
        /**
         * Panjang alasan dihitung dari TEKSNYA, bukan dari HTML mentah.
         * "<p>ok</p>" berisi 9 karakter markup — cukup untuk lolos batas 10
         * kalau yang dihitung panjang string apa adanya, padahal isinya "ok".
         */
        panjangAlasanPutus() { return teksDariHtml(this.putusCatatanHtml).length; },
        catatanCukup() { return this.panjangAlasanPutus >= this.MIN_ALASAN; },
        bolehKonfirmPutus() {
            if (!this.putusDef) return false;

            if (this.alasanWajib && !this.catatanCukup) return false;

            // Aktivitas yang belum selesai harus diakui lebih dulu.
            if (this.perluCentangAktivitas && !this.putusSetujuAktivitas) return false;

            return !this.butuhCentang || this.putusSetuju;
        },
        /** Aktivitas yang dikerjakan tim: catatan bebas, tak ada syarat tambahan. */
        bolehSimpanCatat() { return !!this.catatTarget; },
        /**
         * Aktivitas PENENTU yang dikerjakan tim wajib membawa verdict.
         *
         * Digerbangi di layar juga, bukan hanya di server: tanpa hasil, server
         * hanya mencatat kehadiran dan aktivitasnya tetap menggantung — admin
         * mengira sudah selesai padahal tahapnya masih menunggu.
         */
        bolehSimpanHadir() {
            if (this.hadirNilai !== 'Y') return true;
            // MCU: verdict-nya DITURUNKAN dari status kesehatan, jadi yang
            // dituntut status itu — bukan pilihan Lulus/Gagal terpisah yang
            // bisa berselisih dengannya ("Unfit" tapi ditandai lulus).
            if (this.hadirTarget?.isMcu) return !this.mcuKurangHadir;
            // Penawaran: verdict-nya diturunkan dari jawaban kandidat.
            if (this.hadirTarget?.penawaran) return !this.jawabKurang;
            if (!this.hadirTarget?.dinilaiTim) return true;

            return this.hadirTarget.peran === 'INFORMATIF' || !!this.hadirHasil;
        },

        /* ── TAHAN (HOLD) ──────────────────────────────────────────────────── */
        /**
         * Seluruh tombol keputusan terkunci selama kandidat ditahan.
         *
         * Gerbangnya ada juga di server (LamaranService::ketukPalu) — ini hanya
         * supaya admin melihat sebabnya, bukan tombol yang ditekan lalu ditolak.
         */
        /** Aktivitas tahap aktif yang masih menunggu sesuatu — dari server. */
        belumTuntas() { return this.detailKandidat?.belumTuntas || []; },
        /** Keterangan "belum tuntas" kandidat INI sedang ditutup? */
        notaTutup() { return !!this.notaDitutup[this.detailKandidat?.id]; },
        /**
         * KUNCI PER-TOMBOL, bukan satu kunci untuk semuanya.
         *
         * Dulu satu syarat mematikan SELURUH tombol keputusan. Akibatnya dua
         * arah salah sekaligus: "Tidak Lolos" dan "Mengundurkan Diri" ikut mati
         * padahal keduanya justru paling dibutuhkan saat segalanya belum
         * lengkap — sementara "Loloskan" tetap hidup untuk aktivitas yang BELUM
         * PERNAH DIJADWALKAN, karena syaratnya (kehadiran) mensyaratkan
         * jadwalnya sudah ada lebih dulu.
         *
         * Sekarang tiap hasil membawa sikapnya sendiri dari master
         * (`butuhTuntas`), dan server menolak dengan aturan yang sama persis.
         */
        /**
         * Aksi yang dimiliki pengguna ini pada halaman worklist.
         *
         * Dikirim shell ke SEMUA halaman admin lewat props `akses`. Halaman ini
         * dulu tidak pernah membacanya.
         */
        aksiSaya() {
            return this.akses?.permissions?.pelamarPage || [];
        },
        /**
         * Berhak MEMUTUS tahap?
         *
         * Seluruh keputusan — Loloskan, Tidak Lolos, Talent Pool, Mengundurkan
         * Diri, Menolak Penawaran — memakai satu endpoint yang sama
         * (PATCH .../putus) dengan satu izin yang sama: APPROVE. Tidak ada
         * keputusan yang lebih ringan dari yang lain di mata server.
         */
        bolehPutus() {
            return this.aksiSaya.includes('APPROVE');
        },
        terkunciPutus() {
            return (h) => {
                // HAK AKSES DIPERIKSA PALING DULU.
                //
                // Tanpa ini tombol tetap digambar, admin mengetik alasan,
                // memilih tanggal, mencentang persetujuan, menekan simpan —
                // dan BARU di situ server menjawab 403. Seluruh isian hilang,
                // dan pesannya ("tidak memiliki hak akses") muncul di ujung
                // jalan yang seharusnya tidak pernah bisa dimasuki.
                if (! this.bolehPutus) {
                    return 'Akun Anda tidak punya izin APPROVE di Worklist Pelamar, jadi tidak bisa mengetuk keputusan. Minta admin menambahkannya di Manajemen Hak Akses.';
                }
                if (this.detailKandidat?.hold) {
                    return 'Kandidat sedang ditahan — tekan "Lanjutkan" dulu untuk melepasnya.';
                }
                if (h?.butuhTuntas && this.belumTuntas.length) {
                    const rinci = this.belumTuntas.map((x) => `${x.label} (${x.sebab})`).join(', ');

                    return `Belum bisa: ${rinci}. Selesaikan dulu di Rapor Tes di atas.`;
                }

                return '';
            };
        },
        /* ── TAHAN / LANJUTKAN MASSAL ──────────────────────────────────────── */
        /**
         * Terpilih yang MEMANG bisa ditahan.
         *
         * Tahap yang sudah diputus tidak bisa ditahan lagi (server menolaknya),
         * dan yang sudah ditahan tidak perlu ditahan dua kali. Disaring di sini
         * supaya tombolnya mati saat tak ada satu pun yang bisa diproses —
         * bukan membuka modal yang seluruh isinya akan gagal.
         */
        bisaTahanMassal() {
            return this.barisTerpilih.filter((r) => r.tahapId && !r.hold && r.statusLamaran === 'BERJALAN');
        },
        /** Terpilih yang sedang DITAHAN — sasaran tombol "Lanjutkan". */
        bisaLepasMassal() {
            return this.barisTerpilih.filter((r) => r.tahapId && r.hold);
        },
        /**
         * Terpilih yang BISA diputus.
         *
         * Yang sedang ditahan sengaja dikeluarkan: menahan berarti "keputusannya
         * ditunda", dan mengetuk palu massal ke atasnya membatalkan penundaan itu
         * diam-diam — justru pada kandidat yang paling butuh ditimbang sendiri.
         * Lepaskan tahanannya dulu bila memang sudah siap diputus.
         */
        bisaPutusMassal() {
            return this.barisTerpilih.filter((r) => r.tahapId && !r.hold && r.statusLamaran === 'BERJALAN');
        },
        /* ── BATAS SEKALI KIRIM (LIHAT prop `batasPutusMassal`) ─────────────── */
        /**
         * Terpilih yang MELEBIHI batas untuk keputusan.
         *
         * Bukan sekadar `length > batas`: yang dihitung adalah yang benar-benar
         * akan diputus. Mencentang 8 orang yang 4 di antaranya sedang ditahan
         * hanya menerbitkan 4 email, dan menolaknya sebagai "8 melebihi 5"
         * adalah penolakan atas sesuatu yang tidak akan pernah terjadi.
         */
        lebihanPutusMassal() { return Math.max(0, this.bisaPutusMassal.length - this.batasPutusMassal); },
        /**
         * Terpilih yang MELEBIHI batas untuk penjadwalan.
         *
         * Dihitung dari `sasaranMassal` dengan alasan yang sama — kandidat yang
         * tidak punya aktivitas itu dilewati dan tidak menerima undangan.
         *
         * Kalau aktivitasnya belum dipilih (modalnya belum dibuka), sasarannya
         * masih kosong; yang dipakai untuk menyalakan/mematikan TOMBOL adalah
         * jumlah kandidat terpilih, karena pada saat itu itulah dugaan terbaik
         * yang ada — dan tebakan yang terlalu longgar di sini berarti modal
         * terbuka lalu ditolak di kaki, persis yang ingin dihindari.
         */
        lebihanJadwalMassal() { return Math.max(0, this.barisTerpilih.length - this.batasPutusMassal); },
        /**
         * Sasaran penjadwalan yang melebihi batas — dipakai DI DALAM modal.
         *
         * NOL untuk aktivitas berjadwal PRIVAT. Yang dijaga batas ini adalah
         * laju email; tipe privat tidak mengirim satu undangan pun (lihat
         * JadwalPrivat di sisi server, dan kaki modal ini yang menyatakannya),
         * jadi menahan penjadwalan internal 30 orang di baliknya adalah
         * pembatas yang tidak menjaga apa-apa.
         */
        lebihanSasaranMassal() {
            if (this.aktivitasMassalDef?.jadwalPrivat) return 0;

            return Math.max(0, this.sasaranMassal.length - this.batasPutusMassal);
        },
        /**
         * Boleh menekan "Keputusan"? Dan kalau tidak, KENAPA.
         *
         * Satu computed untuk dua bilah — papan kanban dan daftar. Aturan yang
         * sama pernah ditulis dua kali di markup keduanya, dan itulah cara
         * sebuah pembatas hanya setengah terpasang: bilah yang satu diperbarui,
         * yang lain tidak, lalu gelombang ke-51 tetap lolos dari layar daftar.
         *
         * Alasannya dikembalikan bersama izinnya karena tombol mati tanpa sebab
         * adalah jalan buntu — admin tidak bisa menebak bahwa yang salah adalah
         * jumlah centangnya, bukan haknya.
         */
        gerbangPutusMassal() {
            if (!this.bisaPutusMassal.length) {
                return { boleh: false, sebab: 'Yang terpilih sedang ditahan atau tahapnya sudah diputus.' };
            }
            if (this.lebihanPutusMassal) {
                return {
                    boleh: false,
                    sebab: `Maksimal ${this.batasPutusMassal} kandidat sekali kirim — terpilih ${this.bisaPutusMassal.length}. `
                        + `Lepas ${this.lebihanPutusMassal} centang, putuskan yang ${this.batasPutusMassal} ini dulu, `
                        + 'lalu ulangi untuk sisanya. Pembatas ini menjaga alamat pengirim kami tidak ditangguhkan penyedia email.',
                };
            }

            return { boleh: true, sebab: `Ambil keputusan untuk ${this.bisaPutusMassal.length} kandidat terpilih.` };
        },
        /**
         * Boleh menekan "Jadwalkan"?
         *
         * TOMBOLNYA TIDAK DIMATIKAN oleh batas, berbeda dengan "Keputusan".
         * Sebabnya: apakah undangan benar-benar dikirim baru ketahuan setelah
         * NAMA AKTIVITASNYA dipilih — dan aktivitas berjadwal privat tidak
         * mengirim satu email pun. Mematikan tombolnya di sini akan mengunci
         * penjadwalan internal 30 orang demi batas yang tidak berlaku baginya.
         *
         * Batasnya tetap ditegakkan, satu langkah lebih dalam: di kaki modal,
         * lewat `bolehSimpanMassal` yang sudah tahu aktivitas mana yang dipilih.
         * Peringatannya muncul lebih awal — `peringatanBatasMassal` di bilah —
         * supaya kelebihan centang terbaca sebelum modalnya dibuka.
         */
        gerbangJadwalMassal() {
            if (!this.aktivitasTerpilih.length) {
                return { boleh: false, sebab: 'Tak ada aktivitas tatap muka yang bisa dijadwalkan.' };
            }
            if (this.lebihanJadwalMassal) {
                return {
                    boleh: true,
                    sebab: `Terpilih ${this.barisTerpilih.length} kandidat — undangan dikirim maksimal `
                        + `${this.batasPutusMassal} sekali jalan. Aktivitas internal (tanpa undangan) tidak dibatasi.`,
                };
            }

            return { boleh: true, sebab: 'Jadwalkan kandidat terpilih sekaligus.' };
        },
        /**
         * Kalimat yang TAMPIL di bilah saat centangnya kelebihan.
         *
         * Hanya muncul untuk sebab BATAS — bukan untuk "semua sedang ditahan"
         * atau "tak ada aktivitas". Dua sebab terakhir sudah terbaca dari
         * keadaan kartunya sendiri, dan menuliskannya juga mengubah bilah aksi
         * jadi bilah peringatan yang berbunyi sepanjang waktu — persis cara
         * sebuah peringatan berhenti dibaca.
         *
         * Keputusan disebut lebih dulu bila keduanya kelebihan: itulah tombol
         * yang paling sering dituju dari bilah ini.
         */
        peringatanBatasMassal() {
            if (this.bolehPutus && this.bisaPutusMassal.length && this.lebihanPutusMassal) {
                return `Keputusan dikirim maksimal ${this.batasPutusMassal} kandidat sekali jalan — sekarang terpilih `
                    + `${this.bisaPutusMassal.length}. Lepas ${this.lebihanPutusMassal} centang dulu; sisanya menyusul di `
                    + 'gelombang berikutnya. Pembatas ini menjaga alamat pengirim kami tidak ditangguhkan penyedia email.';
            }
            if (this.aktivitasTerpilih.length && this.lebihanJadwalMassal) {
                return `Undangan jadwal dikirim maksimal ${this.batasPutusMassal} kandidat sekali jalan — sekarang terpilih `
                    + `${this.barisTerpilih.length}. Lepas ${this.lebihanJadwalMassal} centang dulu; sisanya menyusul di `
                    + 'gelombang berikutnya. Pembatas ini menjaga alamat pengirim kami tidak ditangguhkan penyedia email.';
            }

            return '';
        },
        /**
         * Pilihan keputusan untuk massal: HANYA keputusan perusahaan.
         *
         * "Mengundurkan diri" dan "menolak tawaran" datang DARI KANDIDAT — satu
         * per satu, dengan alasan masing-masing, kerap disertai tanggal ia
         * mengabari. Menerapkannya ke dua puluh orang sekaligus berarti mengaku
         * dua puluh orang menyatakan hal yang sama pada saat yang sama, dan itu
         * tidak pernah benar. Keputusan itu tetap lewat drawer per kandidat.
         */
        pmHasilOpsi() { return this.hasilKeputusan.filter((h) => !h.olehKandidat); },
        pmHasilDef() { return this.hasilKeputusan.find((h) => h.kode === this.pmHasil) || null; },
        pmButuhCatatan() { return !!this.pmHasilDef?.butuhAlasan; },
        pmCatatanCukup() { return teksDariHtml(this.pmCatatanHtml).trim() !== ''; },
        borongGagal() { return (this.borong?.baris || []).filter((x) => x.keadaan === 'gagal'); },
        /**
         * Boleh disimpan? Pada mode SENDIRI syaratnya berlaku untuk SETIAP baris —
         * satu baris kosong di tengah daftar akan ditolak server dan menyisakan
         * keputusan setengah jadi yang harus dicari sendiri oleh admin.
         */
        bolehSimpanPutusMassal() {
            if (!this.pmTarget.length) return false;
            // BATAS SEKALI KIRIM — dijaga sampai detik terakhir. Modal ini
            // bertahan melewati refresh halaman, jadi `pmTarget` bisa berasal
            // dari pilihan yang dibuat sebelum batasnya sempat menyaring.
            if (this.pmTarget.length > this.batasPutusMassal) return false;
            if (this.pmPola === 'SERAGAM') {
                if (!this.pmHasil) return false;

                return !this.pmButuhCatatan || this.pmCatatanCukup;
            }

            return this.pmBaris.every((b) => !this.barisPmKurang(b));
        },
        hmAlasanDef() { return this.alasanHold.find((a) => a.value === this.hmAlasan) || null; },
        hmButuhCatatan() { return !!this.hmAlasanDef?.butuhCatatan; },
        hmCatatanCukup() { return teksDariHtml(this.hmCatatanHtml).trim() !== ''; },
        /**
         * Boleh disimpan?
         *
         * Melepas tidak menuntut apa pun. Menahan menuntut alasan — dan pada
         * mode SENDIRI, menuntutnya untuk SETIAP baris: satu baris kosong di
         * tengah daftar akan ditolak server dan menyisakan penahanan setengah
         * jadi yang harus dicari sendiri oleh admin.
         */
        bolehSimpanHoldMassal() {
            if (!this.hmTarget.length) return false;
            if (!this.hmNilai) return true;
            if (this.hmPola === 'SERAGAM') {
                if (!this.hmAlasan) return false;

                return !this.hmButuhCatatan || this.hmCatatanCukup;
            }

            return this.hmBaris.every((b) => !this.barisHmKurang(b));
        },
        /** Definisi alasan terpilih — sumber aturan "butuh keterangan". */
        holdAlasanDef() { return this.alasanHold.find((a) => a.value === this.holdAlasan) || null; },
        holdButuhCatatan() { return !!this.holdAlasanDef?.butuhCatatan; },
        holdCatatanCukup() { return teksDariHtml(this.holdCatatanHtml).trim() !== ''; },
        /**
         * Pemegang aksi ULANG di menu Worklist Pelamar.
         *
         * Dibaca dari `akses.permissions`, daftar yang sama yang dipakai server
         * pada middleware `career.permission:pelamarPage,ULANG` — jadi tombol
         * dan gerbangnya tidak bisa berbeda pendapat.
         */
        bolehUlang() {
            return (this.akses?.permissions?.pelamarPage || []).includes('ULANG');
        },
        /** Tahap tujuan yang sedang dipilih, untuk dirangkai jadi kalimat. */
        ulangTahapTerpilih() {
            return (this.ulangTahapList || []).find((t) => t.id === this.ulangTujuan) || null;
        },
        /** Tahap terakhir yang ikut direset bila cakupannya RANGKAIAN. */
        ulangSampai() {
            const t = this.ulangTahapTerpilih;
            if (!t) return null;
            if (this.ulangCakupan !== 'RANGKAIAN') return t.urutan;
            return Math.max(...(this.ulangTahapList || []).map((x) => x.urutan), t.urutan);
        },
        bolehSimpanUlang() {
            return !!this.ulangTujuan && (this.ulangAlasan || '').trim().length >= 10;
        },
        bolehSimpanHold() {
            // Melepas tahan tidak menuntut apa pun — menahan menuntut alasan,
            // dan alasan tertentu menuntut keterangannya.
            if (!this.holdNilai) return true;
            if (!this.holdAlasan) return false;

            return !this.holdButuhCatatan || this.holdCatatanCukup;
        },
        // KEDUA tab memakai kolom tahap alur yang sama. Kandidat gugur tetap
        // "diam" di tahap tempat ia gugur (backend mengirim kolomUrutan dari
        // tahap ber-Hasil GUGUR), bukan ditumpuk jadi satu kolom — supaya admin
        // langsung melihat DI TAHAP MANA kandidat paling banyak berguguran.
        kolomTampil() {
            return this.detail.kolom || [];
        },
    },
    async mounted() {
        // Esc menutup hamparan biodata LEBIH DULU, bukan jendela detail di
        // belakangnya. Ditangkap pada fase capture supaya sampai sebelum
        // penangan milik modal — tanpa itu satu ketukan Esc menutup keduanya
        // sekaligus, dan yang hilang justru konteks yang sedang dipakai membaca.
        this.lepasEscBio = (e) => {
            // PANAH KIRI/KANAN = kandidat sebelumnya/berikutnya.
            //
            // Menumpang penangan yang sudah ada, bukan memasang listener kedua:
            // dua listener pada peristiwa yang sama akan berebut urutan, dan
            // yang kalah tampak seperti tombol yang kadang bekerja kadang tidak.
            //
            // Diabaikan selagi orang sedang MENGETIK (input, textarea, atau
            // apa pun yang contenteditable): panah di dalam kotak teks berarti
            // memindahkan kursor, dan merebutnya untuk berpindah kandidat akan
            // membuang catatan yang sedang ditulis.
            if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                if (!this.detailKandidat || !this.adaAntreanTinjau) return;
                if (e.altKey || e.ctrlKey || e.metaKey) return;

                const t = e.target;
                const tag = String(t?.tagName || '').toUpperCase();
                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || t?.isContentEditable) return;

                // Hamparan biodata & lightbox punya bacaannya sendiri; panah di
                // sana bukan milik antrean.
                if (this.bioFull || this.lightbox) return;

                e.preventDefault();
                this.geserTinjau(e.key === 'ArrowRight' ? 1 : -1);

                return;
            }

            if (e.key !== 'Escape') return;

            if (this.bioFull) {
                e.stopPropagation();
                this.bioFull = false;

                return;
            }

            // Pratinjau berkas di panel kanan juga sebuah LAPISAN, walau ia
            // duduk di dalam jendela dan bukan di atasnya. Esc mundur selapis
            // — kembali ke daftar berkas — sebelum ia menutup jendela detail;
            // tanpa ini satu ketukan membuang seluruh konteks peninjauan hanya
            // karena orangnya ingin keluar dari satu lembar PDF.
            if (this.dokLihat) {
                e.stopPropagation();
                this.tutupDokLihat();
            }
        };
        window.addEventListener('keydown', this.lepasEscBio, true);

        // programAwal dari server dihitung TANPA saringan, jadi kalau URL
        // membawa q/jenis daftarnya diambil ulang dulu — kalau tidak, yang
        // terpilih otomatis adalah program pertama dari daftar penuh, bukan
        // yang ditunjuk tautan Dashboard. Menunggu (await) sebelum memilih,
        // karena muatProgram() sendiri tidak memilih apa pun.
        // `programAwal` dari server selalu daftar PROGRAM, dan dihitung TANPA
        // saringan. Diambil ulang bila salah satu dari dua hal berlaku:
        //
        //   · URL membawa q/jenis — kalau tidak, yang terpilih otomatis adalah
        //     program pertama dari daftar penuh, bukan yang ditunjuk tautan
        //     Dashboard;
        //   · basis tersimpan = job vacancy — benih programnya memang bukan
        //     daftar yang akan digambar.
        //
        // Menunggu (await) sebelum memilih, karena muatProgram() sendiri tidak
        // memilih apa pun.
        if (this.q || this.jenis || this.basisLoker) {
            this.page = 1;
            await this.muatProgram();
        }

        // PROGRAM YANG SEDANG DIKERJAKAN BERTAHAN MELEWATI MUAT ULANG.
        //
        // Tanpa ini, tiap penyegaran melempar admin kembali ke program
        // PERTAMA di daftar — bukan yang sedang ia kerjakan. Papan, saringan,
        // dan posisi bacanya hilang, dan ia harus mencari programnya lagi
        // setiap kali menekan F5 atau kembali dari tab lain.
        //
        // sessionStorage, bukan localStorage: pilihan ini milik SESI kerja
        // yang sedang berjalan. Membuka tab baru untuk program lain tidak
        // boleh mengubah program di tab sebelah.
        this.pulihkanPilihan();

        // Gelombang keputusan yang masih berjalan — dipulihkan di sini juga.
        // Dulu ia punya mounted() sendiri, dan karena satu komponen hanya
        // boleh punya satu, yang belakangan menimpa yang duluan tanpa satu pun
        // peringatan: panelnya tidak pernah muncul lagi sesudah muat ulang.
        try {
            const sisa = JSON.parse(localStorage.getItem('plwBorong') || 'null');
            if (sisa?.gelombang && sisa?.ids?.length) this.pantauGelombang(sisa.gelombang, sisa.ids);
        } catch (e) {
            // Simpanan rusak / peramban menolak — panel mulai bersih saja.
        }
    },
    methods: {
        /* KEDUANYA METODE, BUKAN COMPUTED.

           Template memanggilnya dengan argumen — labelAdjudikasi(a). Sebagai
           computed, getter-nya justru menerima instance proxy sebagai argumen
           pertama, hasilnya undefined, dan yang terbaca di konsol tiga galat
           yang tak satu pun menyebut sebabnya. */
        kelasAdjudikasi(v) {
            return {
                BERSIH: 'is-ok',
                DIREKOMENDASIKAN: 'is-ok',
                PERTIMBANGAN: 'is-warn',
                RAGU: 'is-warn',
                TIDAK_MEMENUHI: 'is-no',
                TIDAK_REKOMENDASI: 'is-no',
            }[v] || 'is-abu';
        },
        labelAdjudikasi(v) {
            return {
                BERSIH: 'Bersih',
                PERTIMBANGAN: 'Perlu pertimbangan',
                TIDAK_MEMENUHI: 'Tidak memenuhi',
                DIREKOMENDASIKAN: 'Direkomendasikan',
                RAGU: 'Ragu',
                // Kodenya dipendekkan agar muat di kolom VARCHAR(20); yang dibaca
                // orang tetap kalimat penuhnya.
                TIDAK_REKOMENDASI: 'Tidak direkomendasikan',
            }[v] || v;
        },
        /**
         * Label satu isian: dari SKEMA formulir bila dikenali, kalau tidak dari
         * tebakan server atas nama kuncinya.
         *
         * Yang tersimpan di database cuma kunci→jawaban; labelnya hidup di
         * skema. Tanpa pencarian ini, peninjau membaca "V Nama" dan "V Wa" —
         * nama kunci yang dirapikan seadanya, bukan pertanyaan yang benar-benar
         * dilihat kandidat saat mengisi.
         */
        labelIsian(form, isian) {
            return this.petaSkema(form).label[isian.key]
                || this.petaSkema(form).bagian[isian.key]?.label
                || this.labelTebakan(isian.key)
                || isian.label;
        },
        /**
         * Peta label + tipe + kelompok satu formulir, dihitung sekali.
         *
         * Sumbernya SKEMA BEKU milik pengisian itu; kode komponen hanya
         * cadangan untuk formulir bawaan lama yang memang tak punya snapshot.
         */
        petaSkema(form) {
            if (!form) return { label: {}, tipe: {}, grup: {}, bagian: {} };

            let peta = PETA_SKEMA.get(form);
            if (peta) return peta;

            const sumber = form.skema || form.komponen || null;
            peta = {
                label: labelField(sumber),
                tipe: tipeField(sumber),
                grup: grupField(sumber),
                bagian: grupBagian(sumber),
            };
            PETA_SKEMA.set(form, peta);

            return peta;
        },
        /**
         * Label cadangan dari nama kunci — dipakai HANYA bila skemanya tak
         * memuat pertanyaan itu (formulir versi lama, field yang sudah dihapus).
         *
         * Awalan sependek satu-dua huruf DIBUANG. `v_nama` berarti "nama pada
         * langkah validasi" bagi yang menulis skemanya; bagi yang membacanya di
         * layar, "V Nama" cuma huruf nyasar di depan kata — dan "V Email" lebih
         * buruk lagi karena terbaca seperti nama sistem.
         *
         * Singkatan yang memang huruf kapital (NIK, KTP, CV, IPK) dikembalikan
         * apa adanya, bukan jadi "Nik" dan "Ktp".
         */
        labelTebakan(key) {
            const potong = String(key || '').split(/[_-]+/).filter(Boolean);
            if (!potong.length) return '';
            if (potong.length > 1 && potong[0].length <= 2) potong.shift();

            const AKRONIM = new Set(['nik', 'ktp', 'kk', 'npwp', 'cv', 'wa', 'hp', 'ipk', 'sim', 'no', 'sk', 'pt', 'bpjs', 'nisn', 'npsn']);

            return potong
                .map((w) => (AKRONIM.has(w.toLowerCase()) ? w.toUpperCase() : w.charAt(0).toUpperCase() + w.slice(1)))
                .join(' ');
        },
        /**
         * Isian ini memang berupa berkas?
         *
         * Dinilai dari SKEMA (tipe field), bukan dari ada-tidaknya berkas yang
         * terunggah — kalau dari berkasnya, isian dokumen yang KOSONG akan
         * terbaca sebagai isian teks biasa dan tampil "—". Padahal justru
         * kekosongan itulah yang perlu terlihat sebagai "Belum ada".
         */
        isianBerkas(isian) {
            return !!isian.berkas || /^(dok|file|berkas|upload)_/i.test(isian.key || '');
        },
        /**
         * Berkas yang TIDAK punya pertanyaan pasangannya.
         *
         * Foto verifikasi diambil sistem saat kandidat melamar — ia tidak
         * pernah menjadi jawaban dari pertanyaan mana pun, jadi tak punya baris
         * di daftar isian. Kalau berkas semacam ini tidak ikut ditampilkan, ia
         * hilang sama sekali dari pandangan peninjau.
         *
         * Yang dihitung "terpakai" mencakup DUA tingkat:
         *   1. berkas jawaban tingkat atas — dok_ktp, dok_cv, …
         *   2. berkas SUB-ISIAN di dalam baris berulang — sert_file pada
         *      riwayat sertifikasi.
         *
         * Tingkat kedua sempat terlewat, dan akibatnya sertifikat muncul dua
         * kali: sekali di dalam baris riwayatnya, sekali lagi di daftar ini.
         */
        berkasLepas(f) {
            const dipakai = new Set();
            (f.jawaban || []).forEach((j) => {
                this.berkasSel(j).forEach((b) => dipakai.add(b.field));
                (j.baris || []).forEach((row) => row.forEach((p) => {
                    this.berkasSel(p).forEach((b) => dipakai.add(b.field));
                }));
            });

            return (f.berkas || []).filter((b) => !dipakai.has(b.field));
        },
        /**
         * Nama baris untuk berkas tanpa pertanyaan. `foto_verifikasi` disebut
         * apa adanya karena ia punya arti tersendiri — bukti kandidat yang
         * mengisi memang orangnya; sisanya dirapikan dari nama kuncinya.
         */
        /**
         * Nama berkas formulir untuk layar: label ASLI isiannya dari skema, tanpa
         * kata kerja "Upload/Unggah" ("Upload Sertifikat" → "Sertifikat"), ditambah
         * nama barisnya bila berkas itu milik bagian berulang ("Sertifikat ·
         * JAVASCRIPT"). Dulu sel di dalam baris hanya punya label tebakan dari
         * kuncinya — `sert_file` → "File" — sehingga tiga sertifikat tertulis
         * "File" semua. Yang kepanjangan dipotong CSS (…), tidak di sini.
         */
        namaBerkasFormulir(f, b, cadangan, baris = null) {
            const asli = String(this.petaSkema(f).label[b?.field] || cadangan || 'Berkas');
            const inti = asli.replace(/^(upload|unggah|lampirkan|lampiran)\s+/i, '').trim() || asli;
            if (!baris) return inti;

            const judul = baris.find((p) => !this.berkasSel(p).length && typeof p.nilai === 'string' && p.nilai.trim());

            return judul ? `${inti} · ${judul.nilai.trim()}` : inti;
        },
        labelBerkas(b) {
            if (b.field === 'foto_verifikasi') return 'Foto Verifikasi';

            const inti = String(b.field || '').replace(/^(dok|file|berkas|upload)[_-]/i, '');

            return this.labelTebakan(inti) || 'Berkas';
        },
        /** Kunci akordion satu riwayat: nomor formulir + kunci isiannya. */
        kunciRiwayat(no, key) { return `${no}::${key}`; },
        /**
         * Riwayat TERBUKA secara bawaan — peninjau membuka formulir justru untuk
         * membacanya, dan memaksa satu klik tambahan per riwayat memperlambat
         * pekerjaan yang paling sering dilakukan. Akordionnya untuk MENUTUP
         * yang sudah selesai dibaca, bukan untuk membuka yang belum.
         */
        riwayatTutup(no, key) {
            return !!this.riwayatDitutup[this.kunciRiwayat(no, key)];
        },
        toggleRiwayat(no, key) {
            const k = this.kunciRiwayat(no, key);
            this.riwayatDitutup = { ...this.riwayatDitutup, [k]: !this.riwayatDitutup[k] };
        },
        /**
         * Kolom mana pada satu riwayat yang jadi blok URAIAN (keluar dari kisi).
         *
         * Diputuskan SEKALI UNTUK SELURUH RIWAYAT, bukan per sel. Kalau diputus
         * per sel, kolom "Uraian" pindah tempat mengikuti panjang jawaban tiap
         * baris: baris 1 yang uraiannya sekalimat tetap duduk di kisi kanan,
         * baris 2 yang uraiannya separagraf melompat jadi blok penuh di bawah.
         * Label yang sama muncul di dua tempat berbeda dalam satu daftar, dan
         * mata kehilangan kolom untuk diikuti — persis penyakit yang dulu
         * membuat bagian ini dibongkar jadi linimasa.
         *
         * Ambangnya PANJANG JAWABAN TERPANJANG di kolom itu, bukan daftar nama
         * kolom: tiap formulir menamai uraiannya sendiri-sendiri ("Uraian",
         * "Deskripsi Tugas", "Rincian Kegiatan"), dan daftar nama pasti
         * tertinggal begitu ada formulir baru.
         *
         * Dikunci per array `baris` lewat WeakMap: drawer ini dirender ulang
         * tiap kali ada centang berubah, dan menghitung ulang tiap render
         * membuang kerja yang jawabannya selalu sama.
         */
        kolomUraian(baris) {
            if (!Array.isArray(baris) || !baris.length) return EMPTY_SET;

            let peta = KOLOM_URAIAN.get(baris);
            if (peta) return peta;

            const maks = {};
            baris.forEach((row) => (row || []).forEach((p, pi) => {
                const k = p.label || `#${pi}`;
                const n = p.berkas ? 0 : String(p.nilai ?? '').length;
                maks[k] = Math.max(maks[k] ?? 0, n);
            }));

            peta = new Set(Object.keys(maks).filter((k) => maks[k] > 45));
            KOLOM_URAIAN.set(baris, peta);

            return peta;
        },
        /** Sel ini termasuk kolom uraian? */
        selUraian(baris, p, pi) {
            return this.kolomUraian(baris).has(p.label || `#${pi}`);
        },
        /**
         * Kolom mana yang jadi JUDUL kartu riwayat: sel pertama yang bukan
         * uraian dan bukan berkas. Pada riwayat kerja/organisasi/sertifikasi
         * kolom itu selalu "nama tempatnya" — yang dicari mata lebih dulu.
         */
        selBarisLead(baris, row) {
            return (row || []).find((p, pi) => !this.selUraian(baris, p, pi) && !this.berkasSel(p).length) || null;
        },
        /**
         * Kolom PERIODE satu riwayat — naik jadi pil di pojok kanan kartu.
         *
         * Diputus SEKALI untuk seluruh riwayat, disiplin yang sama dengan
         * kolomUraian(): kalau diputus per baris, pil periode muncul di baris
         * ini lalu hilang di baris berikutnya hanya karena isinya kebetulan
         * kosong — dan mata kehilangan tempat tetap untuk mencarinya.
         *
         * Kolom judul sengaja dilewati: pada riwayat pendidikan kolom pertama
         * kerap "Tahun Lulus", dan menaikkannya jadi pil menyisakan kartu tanpa
         * judul sama sekali.
         */
        kolomPeriode(baris) {
            if (!Array.isArray(baris) || !baris.length) return null;

            let kunci = KOLOM_PERIODE.get(baris);
            if (kunci !== undefined) return kunci;

            kunci = null;
            const contoh = baris.find((r) => Array.isArray(r) && r.length) || [];
            let lewatiJudul = true;
            for (let i = 0; i < contoh.length; i++) {
                const p = contoh[i];
                if (this.selUraian(baris, p, i) || this.berkasSel(p).length) continue;
                if (lewatiJudul) { lewatiJudul = false; continue; }
                if (/(periode|tahun|masa|tanggal|durasi|tgl)/i.test(p.label || '')) { kunci = p.label || `#${i}`; break; }
            }
            KOLOM_PERIODE.set(baris, kunci);

            return kunci;
        },
        /** Nilai pil periode baris ini — kosong bila kolomnya tak ada/tak terisi. */
        selBarisPeriode(baris, row) {
            const kol = this.kolomPeriode(baris);
            if (!kol) return '';
            const lead = this.selBarisLead(baris, row);
            const p = (row || []).find((x, xi) => (x.label || `#${xi}`) === kol && x !== lead);

            return p && String(p.nilai ?? '').trim() ? this.nilaiTampil(p) : '';
        },
        /** Sel ini sudah dipakai judul atau pil periode? Kalau ya, jangan diulang di kisi. */
        selSudahDipakai(baris, row, p, pi) {
            if (p === this.selBarisLead(baris, row)) return true;
            const kol = this.kolomPeriode(baris);

            return !!kol && (p.label || `#${pi}`) === kol && !!this.selBarisPeriode(baris, row);
        },
        /** Baris ini punya isian ringkas? Bila tidak, kisinya tidak dirender —
         *  kisi kosong menyisakan garis pemisah yang menggantung tanpa isi.
         *  Sub-isian berkas TIDAK dihitung: ia punya jalurnya sendiri di dasar
         *  kartu, jadi baris yang isinya cuma sertifikat + uraian akan menggambar
         *  kisi kosong kalau berkasnya ikut dihitung di sini. */
        adaRingkas(baris, row) {
            return (row || []).some((p, pi) => !this.selUraian(baris, p, pi)
                && !this.berkasSel(p).length
                && !this.selSudahDipakai(baris, row, p, pi));
        },
        /**
         * SELURUH berkas satu sub-isian.
         *
         * `berkasList` bentuk baru (bisa banyak lembar); `berkas` bentuk lama
         * yang tetap dikirim server. Keduanya dibaca supaya muatan yang sempat
         * ter-cache sebelum pembaruan server tidak kehilangan lampirannya.
         */
        berkasSel(p) {
            if (p?.berkasList?.length) return p.berkasList;

            return p?.berkas ? [p.berkas] : [];
        },
        /**
         * Isian formulir yang BUKAN dokumen — bagian biodata di akordion.
         *
         * Dokumen dipisah ke seksinya sendiri di bawah karena keduanya dibaca
         * dengan cara berbeda: isian dipindai berjajar (label kiri, nilai kanan),
         * dokumen ditekan satu per satu. Mencampurnya membuat satu kisi yang
         * separuh selnya berisi teks dan separuh lagi berisi tombol.
         */
        isianData(f) {
            return (f.jawaban || []).filter((j) => !this.isianBerkas(j));
        },
        /**
         * Isian biodata DIKELOMPOKKAN seperti saat kandidat mengisinya.
         *
         * Tiga puluh kotak label-nilai berderet tanpa jeda bukan cuma tidak
         * rapi — hubungan antar jawaban ikut hilang. "Kesesuaian Data: Sesuai"
         * hanya punya arti di sebelah nama, email, dan WA yang divalidasinya.
         * Kelompoknya datang dari skema formulir (langkah + ikonnya), jadi
         * peninjau membaca susunan yang sama dengan yang diisi kandidat.
         *
         * URUTANNYA JUGA DARI SKEMA — kelompok menurut nomor langkah, isi tiap
         * kelompok menurut nomor field. Bentuk sebelumnya mengikuti urutan kunci
         * di `Jawaban_Json`, yaitu urutan PENYIMPANAN: tidak pernah dijanjikan
         * sama dengan urutan pertanyaan, dan pada praktiknya memang berbeda —
         * jawaban langkah 3 bisa muncul di atas jawaban langkah 1. Sekarang
         * halaman ini terbaca lurus dari atas ke bawah seperti formulirnya.
         *
         * Riwayat berulang duduk di posisi aslinya juga (lewat grupBagian),
         * bukan diseret ke akhir: ia memang mengambil lebar penuh, tapi kisi
         * membiarkannya mengambil barisnya sendiri tanpa memutus urutan baca.
         */
        grupIsian(f) {
            const peta = this.petaSkema(f);
            const grup = new Map();
            const ambil = (kunci, judul, ikon, urutan) => {
                if (!grup.has(kunci)) grup.set(kunci, { kunci, judul, ikon, urutan, items: [] });

                return grup.get(kunci);
            };
            // Yang tak dikenal skema dijaga urutan aslinya, di belakang yang
            // dikenal — bukan diselipkan di tengah dengan posisi tebakan.
            let sisa = 0;

            this.isianData(f).forEach((j) => {
                const g = peta.grup[j.key] || peta.bagian[j.key];
                if (g && g.judul) {
                    ambil(g.judul, g.judul, g.ikon, g.urutan).items.push({ ...j, _pos: g.posisi });
                } else {
                    // Formulir versi lama / field yang sudah dihapus dari skema.
                    // Tetap ditampilkan — menyembunyikannya berarti jawaban yang
                    // pernah diberikan kandidat lenyap tanpa ada yang tahu.
                    ambil('__lain', 'Data Lainnya', 'bi-person-lines-fill', 9999)
                        .items.push({ ...j, _pos: 100000 + sisa++ });
                }
            });

            const hasil = [...grup.values()].sort((a, b) => a.urutan - b.urutan);
            hasil.forEach((g) => g.items.sort((a, b) => a._pos - b._pos));

            // Formulir yang skemanya tak dikenal sama sekali (versi lama, formulir
            // dinamis tanpa snapshot) menghasilkan SATU kelompok penampung.
            // Menyebutnya "Data Lainnya" saat tak ada kelompok lain terbaca
            // seperti ada bagian utama yang hilang — padahal inilah bagian
            // utamanya.
            if (hasil.length === 1 && hasil[0].kunci === '__lain') hasil[0].judul = 'Data Isian';

            return hasil;
        },
        /**
         * Dokumen satu formulir — seksi "Dokumen Terlampir".
         *
         * Pertanyaan dokumen yang KOSONG tetap ikut, sebagai kartu redup. Justru
         * kekosongan itu yang perlu terlihat: daftar yang hanya memuat berkas
         * yang ada tidak pernah bisa menjawab "apa yang belum diunggah?".
         *
         * Lampiran di dalam baris berulang (sertifikat pada riwayat) TIDAK ikut —
         * ia sudah punya tempatnya di kartu riwayatnya, dan mencabutnya ke sini
         * memisahkan sertifikat dari nama pelatihannya.
         *
         * Urutannya juga mengikuti formulir, dengan alasan yang sama seperti
         * grupIsian(): verifikator mencocokkan daftar ini dengan syarat yang
         * dibacanya di pengumuman, dan syarat itu ditulis berurutan.
         */
        dokFormulir(f) {
            const peta = this.petaSkema(f);
            const keluar = [];
            // Satu berkas hanya boleh muncul sekali di seksi ini. Kuncinya URL:
            // ia unik per baris berkas, sementara `field` berulang persis sama
            // di tiap baris riwayat (`sert_file` lagi dan lagi).
            const sudah = new Set();

            const tambah = (id, nama, b, pos) => {
                if (b) {
                    if (sudah.has(b.url)) return;
                    sudah.add(b.url);
                }
                keluar.push({ id, nama, berkas: b, pos });
            };

            (f.jawaban || []).forEach((j) => {
                if (!this.isianBerkas(j)) return;
                const pos = peta.grup[j.key]?.posisi ?? 100000;
                const label = this.labelIsian(f, j);
                const berkas = this.berkasSel(j);
                if (!berkas.length) { tambah(`${f.no}-${j.key}`, label, null, pos); return; }
                berkas.forEach((b, i) => tambah(
                    `${f.no}-${j.key}-${i}`,
                    berkas.length > 1 ? `${label} (${i + 1})` : label,
                    b,
                    pos + i / 100,
                ));
            });

            // ══ BERKAS DI DALAM BARIS BERULANG ══════════════════════════════
            //
            // Sertifikat kursus, piagam organisasi, dan surat pengalaman kerja
            // diunggah PER BARIS riwayat. Sebelumnya seluruhnya dilewati di sini
            // dengan alasan "sudah tampil di linimasa" — dan linimasa itu ada di
            // dalam akordion yang tertutup.
            //
            // Akibatnya seksi ini tidak pernah menjadi apa yang namanya
            // janjikan. Pada kandidat pertama yang diperiksa, "Dokumen
            // Terlampir" menulis 1/1 sementara kandidatnya mengunggah dua
            // berkas: foto verifikasi tampil, sertifikat PDF-nya tidak — dan
            // tidak ada satu pun tanda bahwa ia ada. Peninjau yang memindai
            // dokumen menyimpulkan kandidat tidak melampirkan sertifikat.
            //
            // Sekarang ia ikut, dengan konteks barisnya disebut supaya tiga
            // sertifikat dari tiga baris berbeda tidak terbaca sebagai satu
            // nama yang tercetak tiga kali. Tampil di dua tempat memang
            // disengaja: linimasa menjawab "sertifikat kursus yang mana",
            // seksi ini menjawab "apa saja yang ia lampirkan".
            (f.jawaban || []).forEach((j) => {
                const induk = this.labelIsian(f, j);
                const pos = peta.grup[j.key]?.posisi ?? peta.bagian[j.key]?.posisi ?? 150000;

                (j.baris || []).forEach((row, ri) => (row || []).forEach((p) => {
                    this.berkasSel(p).forEach((b, i) => tambah(
                        `${f.no}-${j.key}-${ri}-${p.label || ''}-${i}`,
                        (j.baris.length > 1 ? `${p.label || induk} · ${induk} #${ri + 1}` : `${p.label || induk} · ${induk}`),
                        b,
                        pos + 0.5 + ri / 100 + i / 10000,
                    ));
                }));
            });

            // Berkas tanpa pertanyaan (foto verifikasi pada formulir lama) tak
            // punya tempat di skema — ia memang bukan jawaban isian mana pun.
            this.berkasLepas(f).forEach((b, i) => tambah(
                `${f.no}-x-${b.field}-${i}`,
                this.labelBerkas(b),
                b,
                200000 + i,
            ));

            return keluar.sort((a, b) => a.pos - b.pos);
        },
        /** Pil ringkasan di puncak akordion: berapa data, berapa dokumen lengkap. */
        ringkasFormulir(f) {
            const isi = this.isianData(f).filter((j) => String(j.nilai ?? '').trim() !== '' || (j.baris || []).length).length;
            const dok = this.dokFormulir(f);
            const ada = dok.filter((d) => d.berkas).length;
            const bagian = [`${isi} data`];
            // "6/6", bukan "6": yang perlu terbaca sekilas bukan berapa yang
            // masuk melainkan apakah semuanya sudah masuk.
            if (dok.length) bagian.push(`${ada}/${dok.length} dokumen`);
            if (f.waktuKirim) bagian.push(`dikirim ${this.tglId(f.waktuKirim)}`);

            return bagian.join(' · ');
        },
        /**
         * Nilai ini dibaca DIGIT PER DIGIT, bukan sebagai kata?
         *
         * NIK, nomor telepon, dan email dicocokkan orang karakter demi karakter
         * saat memverifikasi berkas. Huruf proporsional membuat "1607 1111 2222
         * 0003" dan "1607 1111 2222 0008" nyaris kembar; huruf lebar-tetap
         * membuat selisihnya jatuh di kolom yang sama dan langsung terlihat.
         */
        selMono(form, isian) {
            const tipe = this.petaSkema(form).tipe[isian.key] || '';
            if (['email', 'telepon', 'phone', 'nik', 'nomor', 'number'].includes(tipe)) return true;

            const teks = String(isian.nilai ?? '');
            if (!teks) return false;

            // Cadangan untuk formulir yang tipenya tak tercatat di skema:
            // alamat surel, atau deret yang isinya didominasi angka & pemisah.
            return /@/.test(teks) || (/^[\d\s+().-]{8,}$/.test(teks));
        },
        /** Tutup / tampilkan lagi keterangan "tahap ini belum tuntas". */
        tutupNota(tutup) {
            const id = this.detailKandidat?.id;
            if (!id) return;
            this.notaDitutup = { ...this.notaDitutup, [id]: tutup };
        },
        /** Ganti cara membaca berkas ('daftar' | 'berkas') dan ingat pilihannya. */
        setBerkasMode(m) {
            this.berkasMode = m;
            try { localStorage.setItem('plw.berkasMode', m); } catch (e) { /* mode privat */ }
        },
        /**
         * Ikon petak berkas — dari jenis berkasnya, bukan dari namanya.
         * Gambar, PDF, lembar kerja, dan dokumen kata punya cara dibuka yang
         * berbeda, dan itulah yang perlu terbaca sebelum diklik.
         */
        ikonBerkas(b) {
            if (b.isImage) return 'bi-file-earmark-image-fill';
            const e = String(b.ext || '').toLowerCase();
            if (e === 'pdf') return 'bi-file-earmark-pdf-fill';
            if (['xls', 'xlsx', 'csv'].includes(e)) return 'bi-file-earmark-spreadsheet-fill';
            if (['doc', 'docx'].includes(e)) return 'bi-file-earmark-word-fill';

            return 'bi-file-earmark-fill';
        },

        /* ── PANEL BERKAS KANAN ─────────────────────────────────────────────
         *
         * Semua yang di bawah ini melayani lemari berkas di sisi kanan jendela
         * detail. Pratinjaunya sengaja TIDAK memakai lightbox yang sudah ada:
         * lightbox menutupi seluruh layar, termasuk rapor tes yang justru
         * sedang dibandingkan dengan berkasnya. Yang benar-benar butuh layar
         * penuh tetap bisa memanggil lightbox dari tombol perbesar.
         */

        /** Buka/lipat panel, dan ingat pilihannya di perangkat ini. */
        setDokPanel(buka) {
            this.dokPanel = buka;
            if (!buka) this.tutupDokLihat();
            try { localStorage.setItem('plw.dokPanel', buka ? '1' : '0'); } catch (e) { /* mode privat */ }
        },
        /**
         * Lembar mana yang terbuka sendiri saat kandidat dibuka.
         *
         * PDF TERBARU lebih dulu, baru berkas terbaru apa pun sebagai cadangan.
         *
         * Bukan sekadar "yang paling baru": lembar yang paling baru masuk sering
         * kali foto verifikasi atau pindaian pendukung, sementara yang dicari
         * peninjau begitu jendela terbuka hampir selalu dokumen — CV, ijazah,
         * surat lamaran — dan itu praktis selalu PDF. Membuka foto lebih dulu
         * berarti satu klik yang selalu terbuang, di setiap kandidat.
         *
         * Kolom pratinjau yang menyambut dengan bidang kosong lebih buruk lagi:
         * ia menuntut klik sebelum ada apa pun yang bisa dilihat, padahal
         * melihat itulah gunanya kolom tersebut.
         */
        bukaBerkasAwal() {
            if (!this.dokDaftar.length) return;

            const pdf = this.dokDaftar.findIndex((b) => b.berkas?.isPdf || String(b.ext).toUpperCase() === 'PDF');

            this.lihatDok(pdf >= 0 ? pdf : 0);
        },
        /** Tampilkan lembar ke-i dari daftar panel di dalam panel itu sendiri. */
        lihatDok(i) {
            const b = this.dokDaftar[i];
            if (!b) return;

            this.dokLihat = b;
            this.dokSrc = b.berkas.url;
            this.mulaiDokMuat();
        },
        /** Batalkan seluruh penyaring sekaligus — satu tombol, bukan dua langkah. */
        bersihkanSaring() {
            this.dokCari = '';
            this.dokTab = '';
        },
        /** Kembali dari pratinjau ke daftar. */
        tutupDokLihat() {
            this.dokLihat = null;
            this.dokSrc = '';
            this.dokMuat = false;
            this.dokGagal = false;
            clearTimeout(this.dokTimer);
        },
        /** Lembar berikutnya / sebelumnya tanpa mampir ke daftar. */
        geserDok(arah) {
            const i = this.dokIndex + arah;
            if (i >= 0 && i < this.dokDaftar.length) this.lihatDok(i);
        },
        /**
         * Mulai memuat, dengan batas waktu.
         *
         * URL bertanda tangan yang sudah mati tidak pernah memicu onerror pada
         * iframe — ia memulangkan halaman galat yang, bagi peramban, memuat
         * dengan sukses. Tanpa penjaga waktu ini spinnernya berputar selamanya
         * dan panel terbaca sebagai macet.
         */
        mulaiDokMuat() {
            this.dokMuat = true;
            this.dokGagal = false;
            clearTimeout(this.dokTimer);
            this.dokTimer = setTimeout(() => { this.dokMuat = false; }, 10000);
        },
        dokSelesai(gagal = false) {
            clearTimeout(this.dokTimer);
            this.dokMuat = false;
            this.dokGagal = gagal;
        },
        /** Muat ulang — URL bertanda tangan bisa kedaluwarsa; cache-buster kecil. */
        dokCoba() {
            if (!this.dokLihat) return;
            const u = this.dokLihat.berkas.url;
            this.dokSrc = u + (u.includes('?') ? '&' : '?') + 'r=' + Date.now();
            this.mulaiDokMuat();
        },
        /**
         * Petak pratinjau gambar yang gagal dimuat disembunyikan, dan ikon
         * cadangan di sebelahnya yang mengambil alih. Membiarkannya menyisakan
         * kotak pecah — satu-satunya hal di daftar yang terbaca sebagai rusak.
         */
        gagalThumb(e) {
            const img = e?.target;
            if (!img) return;
            img.style.display = 'none';
            img.parentElement?.classList.remove('is-img');
        },
        /**
         * '1843201' → '1,8 MB'. Ukuran nol dibiarkan kosong, bukan ditulis
         * "0 B": nol di sini hampir selalu berarti "tidak tercatat" (data lama),
         * dan "0 B" membacanya sebagai berkas rusak.
         */
        ukuranBerkas(n) {
            const b = Number(n || 0);
            if (!b) return '—';
            if (b < 1024) return `${b} B`;
            if (b < 1024 * 1024) return `${Math.round(b / 1024)} KB`;

            return `${(b / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
        },
        /**
         * Waktu ringkas untuk daftar berkas: '5 Agu' untuk tahun ini, '5 Agu 25'
         * untuk tahun lain. Kolomnya cuma selebar beberapa karakter, dan tahun
         * yang sama dengan hari ini tidak menambah satu pun keterangan.
         */
        tglRingkas(v) {
            const d = new Date(String(v || '').replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '';

            const tgl = d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });

            return d.getFullYear() === new Date().getFullYear()
                ? tgl
                : `${tgl} ${String(d.getFullYear()).slice(2)}`;
        },
        /**
         * Nama formulir dipendekkan untuk muat di sub-tab panel.
         *
         * Dipotong pada BATAS KATA, bukan di tengah kata: "Wawancara Manage…"
         * masih terbaca, "Wawancara Managem" terbaca seperti salah ketik.
         */
        labelPendek(s, maks = 18) {
            const t = String(s || '').trim();
            if (t.length <= maks) return t;

            const potong = t.slice(0, maks);
            const spasi = potong.lastIndexOf(' ');

            return `${(spasi > 8 ? potong.slice(0, spasi) : potong).trim()}…`;
        },
        /**
         * Ikon kepala riwayat — ditebak dari nama isiannya.
         *
         * Sekadar tebakan, dan memang cukup: yang salah tebak jatuh ke ikon
         * daftar netral, bukan ke ikon yang keliru artinya. Mengikatnya ke
         * master akan menuntut satu kolom baru hanya demi hiasan.
         */
        ikonRiwayat(isian) {
            const k = `${isian.key || ''} ${isian.label || ''}`.toLowerCase();
            if (/sertifik|pelatihan|training|lisensi|piagam/.test(k)) return 'bi-patch-check-fill';
            if (/organisasi|kepanitiaan|komunitas/.test(k)) return 'bi-people-fill';
            if (/kerja|pengalaman|magang|intern|karier|karir/.test(k)) return 'bi-briefcase-fill';
            if (/pendidikan|sekolah|kuliah|kampus|studi/.test(k)) return 'bi-mortarboard-fill';
            if (/prestasi|penghargaan|award/.test(k)) return 'bi-trophy-fill';
            if (/keluarga|kerabat|darurat/.test(k)) return 'bi-house-heart-fill';

            return 'bi-list-stars';
        },

        /* ── Nominal rupiah ────────────────────────────────────────────── */

        /** "9500000" → "Rp 9.500.000". Yang bukan angka dikembalikan apa adanya. */
        rupiah(v) {
            const angka = String(v ?? '').replace(/\D/g, '');
            if (!angka) return String(v ?? '');

            return 'Rp ' + angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        },
        /**
         * Isian ini nominal RUPIAH?
         *
         * DUA sumber, dan keduanya memang dibutuhkan:
         *   1. tipe `currency` di skema — sumber yang benar; dan
         *   2. tebakan dari nama isiannya, karena skema yang sudah dipakai
         *      menulis ekspektasi gaji sebagai `number` biasa. Menunggu seluruh
         *      skema lama diperbaiki dulu berarti nominal gaji tetap tampil
         *      telanjang di layar sampai entah kapan.
         *
         * Tebakan hanya berlaku bila jawabannya memang angka bulat, jadi isian
         * bernama "gaji" yang jawabannya kalimat tidak ikut tersulap.
         */
        isianRupiah(form, isian) {
            if (this.petaSkema(form).tipe[isian.key] === 'currency') return true;

            return this.namaUang(isian.key, this.labelIsian(form, isian)) && this.angkaBulat(isian.nilai);
        },
        /** Sel linimasa hanya punya label — tipenya tidak ikut sampai ke sini. */
        selRupiah(p) {
            return !p.berkas && this.namaUang(p.label) && this.angkaBulat(p.nilai);
        },
        namaUang(...teks) {
            return /(gaji|upah|salary|penghasilan|tunjangan|nominal|biaya|honor|rp\b|rupiah)/i.test(teks.filter(Boolean).join(' '));
        },
        angkaBulat(v) {
            return /^\s*\d{4,}\s*$/.test(String(v ?? ''));
        },
        /** Nilai satu isian siap tampil (rupiah bila memang nominal). */
        nilaiIsian(form, isian) {
            if (!isian.nilai && isian.nilai !== 0) return '—';

            return this.isianRupiah(form, isian) ? this.rupiah(isian.nilai) : isian.nilai;
        },
        /** Nilai satu sel linimasa siap tampil. */
        nilaiTampil(p) {
            if (!p.nilai && p.nilai !== 0) return '—';

            return this.selRupiah(p) ? this.rupiah(p.nilai) : p.nilai;
        },
        /**
         * Isian yang layak memakai satu baris penuh.
         *
         * Diukur dari PANJANG JAWABANNYA, bukan dari daftar kunci: alamat,
         * uraian pengalaman, dan alasan melamar sama-sama panjang tapi namanya
         * berbeda di tiap formulir. Ambangnya kasar dan memang cukup — yang
         * dihindari hanya paragraf yang terjepit di kolom setipis dua kata.
         */
        isianPanjang(isian) {
            if (this.isianBerkas(isian)) return false;
            // Isian BERULANG selalu selebar penuh — satu baris riwayat kerja
            // memuat perusahaan, jabatan, periode, dan uraian sekaligus.
            if (isian.baris && isian.baris.length) return true;
            // Begitu pula daftar butir: lima butir di kolom setengah lebar
            // terpotong satu per satu.
            if (this.isianDaftar(isian)) return true;

            return String(isian.nilai ?? '').length > 60;
        },
        /**
         * Isian bertipe DAFTAR BUTIR — digambar bernomor, bukan dirangkai koma.
         *
         * Dikenali dari DATANYA (`daftar` berisi lebih dari satu butir), bukan
         * dari tipe di skema: jawaban lama yang terlanjur tersimpan sebagai
         * larik ikut terbaca benar, dan formulir yang skemanya sudah dihapus
         * tetap tergambar utuh.
         */
        isianDaftar(isian) {
            const d = (isian.daftar || []).filter((x) => String(x ?? '').trim() !== '');

            return d.length > 1 ? d : null;
        },
        inisial(n) { return (n || '?').split(' ').slice(0, 2).map((s) => s[0]).join('').toUpperCase(); },
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship / Magang' }[k] || k || '—'; },
        statusLabel(s) { return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak Lolos', TALENT_POOL: 'Talent Pool' }[s] || s; },
        tglId(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));
            return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        },
        /** Tanggal + jam — batas pengisian selalu menyebut jamnya. */
        tglJamId(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));
            return Number.isNaN(d.getTime())
                ? '—'
                : d.toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        /** Sudah berapa lama sejak melamar — penanda kandidat yang terlalu lama menunggu. */
        umurHari(v) {
            if (!v) return '';
            const d = new Date(String(v).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '';
            const hari = Math.max(0, Math.floor((Date.now() - d.getTime()) / 86400000));
            if (hari === 0) return 'hari ini';
            if (hari < 30) return `${hari} hari`;
            const bulan = Math.floor(hari / 30);
            return `${bulan} bln`;
        },
        aksen(p) { return p.warna || (p.kategori === 'MT' ? '#f59e0b' : '#6366f1'); },
        avatarBg(r) {
            if (r.statusLamaran === 'GUGUR') return 'linear-gradient(135deg,#f87171,#ef4444)';
            if (r.statusLamaran === 'TALENT_POOL') return 'linear-gradient(135deg,#fbbf24,#d97706)';
            if (r.statusLamaran === 'LULUS') return 'linear-gradient(135deg,#34d399,#10b981)';
            if (r.nungguSistem) return 'linear-gradient(135deg,#fbbf24,#f59e0b)';
            return 'linear-gradient(135deg,#8b5cf6,#6366f1)';
        },
        /**
         * Tahap aktif kandidat ini di-cut-off ke Talent Pool?
         *
         * Dibaca dari salinan tahap MILIK KANDIDAT, bukan dari kolom master.
         * Menggeser cut-off di Master Alur tidak boleh mengubah pilihan yang
         * ditawarkan untuk orang yang sudah berjalan — apalagi menawarkan
         * Talent Pool pada tahap yang saat mereka melamar belum termasuk.
         */
        bolehTalentPool(r) {
            return !!(r && (r.perilaku?.talentPool ?? r.bolehTalentPool));
        },
        /** Loloskan diblokir bila kuota penuh DAN kandidat di tahap terakhir. */
        kuotaBlokir(r) { return !!(r && r.kuotaPenuh && r.diTahapAkhir); },
        /**
         * Nada tombol keputusan — DARI FLAG master, bukan dari kodenya.
         * Hasil baru yang ditambahkan lewat master ikut dapat warna yang masuk
         * akal tanpa satu baris pun disentuh di sini.
         */
        kelasPutus(h) {
            if (h.lolos) return 'is-lolos';

            return h.talentPool && !h.kirimEmail ? 'is-talent' : 'is-gugur';
        },
        /** "Loloskan" jadi "Terima" pada tahap yang menutup proses. */
        labelPutus(h) {
            return h.lolos && this.tahapTuntas ? 'Terima' : h.labelTombol;
        },
        /**
         * Waktu terbaca manusia. Dipakai keterangan "dinyatakan lengkap pada …".
         *
         * String kosong bila tak bisa dibaca — BUKAN "Invalid Date", yang di
         * dalam tooltip terbaca sebagai kerusakan sistem padahal hanya berarti
         * kolomnya memang belum terisi.
         */
        fmtWaktu(v) {
            if (!v) return '';
            const d = new Date(String(v).replace(' ', 'T'));

            return Number.isNaN(d.getTime())
                ? ''
                : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        ukuran(b) {
            if (!b) return '';
            return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
        },
        /** Kartu pada satu kolom tahap — berlaku sama untuk tab Berjalan & Tidak Lolos. */
        /**
         * Kartu satu kolom — setelah penyaring GLOBAL, lalu penyaring KOLOM.
         *
         * Dua lapis, bukan satu. Penyaring global menjawab "papan ini sedang
         * membahas siapa"; penyaring kolom menjawab "di tahap ini, siapa yang
         * saya kerjakan sekarang". Satu tahap Psikotes bisa berisi 200 orang
         * sementara tahap sesudahnya berisi 3 — memaksa keduanya ikut satu
         * penyaring berarti menyaring tahap yang tidak perlu disaring.
         */
        kartuKolom(col) {
            const f = this.filterKolom[this.kunciKolom(col)];
            // PENEMPATAN PER IDENTITAS TAHAP, bukan per nomor urut.
            //
            // Server sudah memutuskan kolom mana milik siapa (AlurKolom::cocok)
            // dan mengirimnya sebagai `kolomKode`. Mencocokkan nomor di sini
            // akan mengulang anggapan lama — bahwa tahap ke-N kandidat sama
            // dengan kolom ke-N papan — yang runtuh begitu alur disunting atau
            // program dialihkan ke alur lain.
            //
            // Cadangan ke nomor hanya untuk muatan lama yang belum membawa
            // kolomKode (mis. tab yang sudah lama terbuka lalu difilter ulang).
            let baris = this.pelamarTampil.filter((r) => (
                r.kolomKode != null && col.kode != null
                    ? r.kolomKode === col.kode
                    : r.kolomUrutan === col.urutan
            ));

            if (f?.q) {
                const q = f.q.trim().toLowerCase();
                baris = baris.filter((r) => (r.pelamar || '').toLowerCase().includes(q)
                    || (r.lamaranKode || '').toLowerCase().includes(q)
                    || (r.posisi || '').toLowerCase().includes(q));
            }

            if (f?.keadaan) {
                baris = baris.filter((r) => {
                    switch (f.keadaan) {
                        case 'PERLU': return !!r.butuhKeputusan && !r.hold;
                        case 'NUNGGU': return !!r.nungguSistem;
                        case 'JADWAL': return (r.tests || []).some((t) => t.butuhJadwal && !t.jadwal);
                        case 'TERJADWAL': return (r.tests || []).some((t) => !!t.jadwal && !t.selesai);
                        case 'HOLD': return !!r.hold;
                        case 'BATAS_TUTUP': return !!(r.batas?.belumDiatur || r.batas?.belumBuka);
                        case 'BATAS_LEWAT': return !!r.batas?.lewat;
                        case 'BATAS_DEKAT': return !!r.batas?.batas && !r.batas.lewat && !r.batas.terkirim && r.batas.sisaDetik <= 86400;
                        default: return this.cocokKonfirmasi(r, f.keadaan) ?? true;
                    }
                });
            }

            // Bawaannya TERLAMA DULU — yang paling lama menunggu adalah yang
            // paling mendesak, dan itu yang harus terbaca lebih dulu tanpa
            // perlu menggulung sampai dasar kolom.
            const urut = f?.urut || 'LAMA';
            const waktu = (r) => new Date(String(r.waktuLamar || '').replace(' ', 'T')).getTime() || 0;

            return [...baris].sort((a, b) => {
                if (urut === 'NAMA') return (a.pelamar || '').localeCompare(b.pelamar || '');
                if (urut === 'BARU') return waktu(b) - waktu(a);

                return waktu(a) - waktu(b);
            });
        },
        /**
         * Penyaring kolom — PEMBACA MURNI, tidak pernah menulis.
         *
         * Ia dipanggil dari template (`v-model="filterKol(col).q"`), dan
         * membuat objeknya di sini berarti mengubah state reaktif DI TENGAH
         * render: Vue akan merender ulang, memanggilnya lagi, dan seterusnya.
         * Objeknya dibuat di toggleFilterKolom() — satu-satunya jalan panel ini
         * bisa terbuka, jadi ia dijamin sudah ada saat template membacanya.
         */
        filterKol(col) {
            return this.filterKolom[this.kunciKolom(col)] || { q: '', keadaan: '', urut: 'LAMA' };
        },
        toggleFilterKolom(col) {
            const k = this.kunciKolom(col);
            if (!this.filterKolom[k]) {
                this.filterKolom[k] = { q: '', keadaan: '', urut: 'LAMA' };
            }
            this.filterBuka = this.filterBuka === k ? '' : k;
        },
        /** Kolom ini sedang disaring? Dipakai menandai ikonnya. */
        kolomTersaring(col) {
            const f = this.filterKolom[this.kunciKolom(col)];

            return !!(f && (f.q || f.keadaan || (f.urut && f.urut !== 'LAMA')));
        },
        bersihkanFilterKolom(col) {
            this.filterKolom[this.kunciKolom(col)] = { q: '', keadaan: '', urut: 'LAMA' };
        },
        segKelas(n) {
            const cur = this.detailKandidat?.urutan || 1;
            if (this.detailKandidat?.statusLamaran === 'LULUS') return 'is-done';
            if (n < cur) return 'is-done';
            if (n === cur) return this.detailKandidat?.statusLamaran === 'GUGUR' ? 'is-fail' : 'is-cur';
            return '';
        },
        /* ── Panel kiri ── */
        /**
         * Ganti dasar daftar: program ⇄ job vacancy.
         *
         * Papan lama DIKOSONGKAN lebih dulu, bukan dibiarkan sampai muatan baru
         * datang. `selectedId` dua basis ini bukan hal yang sejenis — hashid
         * program vs nomor MPP — dan membiarkan papan lama terpampang di bawah
         * daftar yang sudah berganti isi membuat orang membaca kandidat sebuah
         * program sambil mengira ia sedang melihat satu lowongan.
         */
        async setBasis(b) {
            if (this.basis === b) return;

            this.basis = b;
            try { localStorage.setItem('plw.basis', b); } catch (e) { /* peramban menolak menyimpan */ }

            this.selectedId = null;
            this.detail = { program: null, posisi: [], kolom: [], pelamar: [] };
            this.alurPilih = '';
            this.page = 1;
            // Penyaring papan ikut dibersihkan: pilihan lowongan/tahap/kampus
            // di dalamnya menunjuk isi papan yang barusan ditinggalkan.
            this.bersihkanFilter();
            await this.muatProgram();
            this.pulihkanPilihan();
        },
        /**
         * Muat daftar panel kiri sesuai basis yang sedang dipakai.
         *
         * Namanya dipertahankan walau kini bisa memuat job vacancy: ia dipanggil
         * dari belasan tempat (pencarian, chip kategori, paginasi, mounted), dan
         * mengganti namanya di semua itu hanya menambah permukaan salah tanpa
         * menjelaskan apa pun yang belum dijelaskan komentar ini.
         */
        async muatProgram() {
            this.loadingProg = true;
            try {
                const url = this.basisLoker
                    ? '/api/v1/karir/lamaran/worklist/loker'
                    : '/api/v1/karir/lamaran/worklist/program';
                const res = await axios.get(url, {
                    params: { page: this.page, q: this.q || undefined, jenis: this.jenis || undefined },
                    ...CFG,
                });
                const r = res.data.result;
                this.programs = r.data;
                this.page = r.page;
                this.totalPage = r.totalPage;
                this.total = r.total;
            } catch (e) {
                this.notice(this.basisLoker ? 'Gagal memuat job vacancy.' : 'Gagal memuat program.', true);
            } finally {
                this.loadingProg = false;
            }
        },
        /** Pilih yang tersimpan bila masih ada di daftar; kalau tidak, yang teratas. */
        pulihkanPilihan() {
            const tersimpan = this.programTersimpan();
            const pilihan = (tersimpan && this.programs.find((p) => p.id === tersimpan)) || this.programs[0];
            if (pilihan) this.pilihProgram(pilihan);
        },
        cariDebounce() {
            clearTimeout(this.cariTm);
            this.cariTm = setTimeout(() => { this.page = 1; this.muatProgram(); }, 400);
        },
        setJenis(j) { this.jenis = j; this.page = 1; this.muatProgram(); },
        gotoPage(n) { if (n < 1 || n > this.totalPage) return; this.page = n; this.muatProgram(); },
        /* ── Panel kanan ── */
        /**
         * Seluruh loker program ini sudah lepas dari akun ini?
         *
         * `lokerTotal > 0` ikut disyaratkan: program yang memang BELUM punya
         * loker sama sekali juga menghasilkan 0 — tapi itu keadaan yang sama
         * sekali berbeda, dan menandainya "diserahkan" akan berbohong.
         *
         * Nilai `undefined` (tanggapan server lama, sebelum kolom ini ada)
         * jatuh ke false: lebih baik kartunya bisa diklik seperti dulu
         * daripada mendadak mati tanpa sebab.
         */
        diserahkan(p) {
            return Number.isFinite(p?.lokerTotal)
                && p.lokerTotal > 0
                && Number(p.lokerSaya || 0) === 0;
        },
        pilihProgram(p) {
            if (this.diserahkan(p)) return;

            this.selectedId = p.id;
            this.statusTab = 'AKTIF';
            // Alur dilepas setiap berpindah lowongan: alur milik lowongan
            // sebelumnya belum tentu ada di lowongan ini, dan memaksakannya
            // membuat server jatuh ke bawaan tanpa pemilihnya ikut berubah —
            // pemilih lalu menunjuk alur yang tidak sedang digambar papan.
            this.alurPilih = '';
            this.simpanProgram(p.id);
            this.muatDetail(p.id);
        },
        /**
         * Yang sedang dikerjakan — bertahan melewati muat ulang.
         *
         * Kuncinya DIPISAH per basis: hashid program dan nomor MPP bukan hal
         * yang sejenis, dan satu kunci bersama membuat pindah basis mencari
         * nomor MPP di daftar program (tak pernah ketemu, selalu jatuh ke baris
         * pertama) — pilihan yang sedang dikerjakan hilang tiap kali berpindah.
         */
        simpanProgram(id) {
            try { sessionStorage.setItem(`plwPilih:${this.basis}`, id || ''); } catch (e) { /* peramban menolak menyimpan */ }
        },
        programTersimpan() {
            try { return sessionStorage.getItem(`plwPilih:${this.basis}`) || null; } catch (e) { return null; }
        },
        /** Ganti alur papan (basis job vacancy) lalu muat ulang papannya. */
        gantiAlur() {
            if (this.selectedId) this.muatDetail(this.selectedId);
        },
        async muatDetail(id, { senyap = false } = {}) {
            // Jawaban yang tiba sesudah permintaan lain dikirim (program
            // diganti, atau aksi berikutnya sudah memicu penyegaran baru)
            // DIBUANG. Tanpa ini, papan program yang baru saja ditinggalkan
            // bisa tiba belakangan dan menimpa papan yang sedang dibuka.
            const nomor = ++this.nomorMuatDetail;
            // SENYAP = penyegaran sesudah aksi. Papan tidak dikosongkan:
            // mengganti ratusan kartu dengan "Memuat…" setiap kali rekruter
            // mencatat sesuatu membuang posisi gulirnya dan terasa seperti
            // aplikasi macet. Pergantian program tetap menampilkan pemuat.
            if (senyap) this.menyegarkan = true;
            else this.loadingDetail = true;
            try {
                // Dua bentuk permintaan, satu bentuk jawaban: detailLoker
                // sengaja memulangkan kunci `program` yang sama, jadi seluruh
                // papan, drawer, dan penyaring di bawah ini tidak perlu tahu
                // dari mana lamarannya dikumpulkan.
                const res = this.basisLoker
                    ? await axios.get('/api/v1/karir/lamaran/worklist/loker/detail', {
                        params: { kunci: id, alur: this.alurPilih || undefined },
                        ...CFG,
                    })
                    : await axios.get(`/api/v1/karir/lamaran/worklist/program/${id}`, CFG);
                if (nomor !== this.nomorMuatDetail) return;

                this.detail = res.data.result;
                // Pemilih diselaraskan dengan alur yang BENAR-BENAR dipakai
                // server. Tanpa ini, permintaan pertama (alur kosong = "pilihkan
                // bawaannya") meninggalkan pemilih dalam keadaan kosong padahal
                // papannya sudah menggambar satu alur tertentu.
                if (this.basisLoker) {
                    this.alurPilih = this.detail.alurAktif ? String(this.detail.alurAktif) : 'SEMUA';
                }
                this.segarkanDrawer();
            } catch (e) {
                if (nomor === this.nomorMuatDetail) this.notice('Gagal memuat papan seleksi.', true);
            } finally {
                if (nomor === this.nomorMuatDetail) {
                    this.loadingDetail = false;
                    this.menyegarkan = false;
                }
            }
        },
        /**
         * SEGARKAN SATU KARTU — sesudah aksi atas SATU kandidat.
         *
         * Dulu setiap aksi (catat hasil, jadwal, hadir, putus, …) memuat ulang
         * SELURUH papan: ratusan kartu dibangun ulang di server demi satu
         * kandidat yang berubah — dikali puluhan rekruter yang bekerja
         * serempak — dan papannya dikosongkan selama menunggu. Sekarang hanya
         * kartu itu yang diminta (papan yang sama, disaring satu lamaran lewat
         * `?lamaran=`), lalu ditempel di tempatnya.
         *
         * Tetap muat ulang penuh (senyap) bila kartunya tak ditemukan, keluar
         * dari papan, pindah ke kolom yang belum ada, atau STATUS lamarannya
         * berubah: status ikut dihitung di rekap lowongan, kuota, dan daftar
         * program — angka yang hanya benar bila seluruh papan dihitung ulang.
         *
         * @param {{lamaran?: string, tahap?: string, tes?: string}} petunjuk
         *        id lamaran, id tahap aktifnya, atau id salah satu aktivitas di
         *        rapornya. Jenisnya DISEBUT, tidak ditebak: hashid dari tabel
         *        berbeda bisa saja sama bunyinya.
         */
        async segarkanKartu(petunjuk = {}) {
            if (!this.selectedId) return;
            const semua = this.detail?.pelamar || [];
            const lama = (petunjuk.lamaran && semua.find((r) => r.id === petunjuk.lamaran))
                || (petunjuk.tahap && semua.find((r) => r.tahapId === petunjuk.tahap))
                || (petunjuk.tes && semua.find((r) => (r.tests || []).some((x) => x.id === petunjuk.tes)))
                || null;
            if (!lama) {
                await this.muatDetail(this.selectedId, { senyap: true });
                return;
            }

            const papan = this.nomorMuatDetail;
            const nomor = (this.nomorKartu[lama.id] || 0) + 1;
            this.nomorKartu[lama.id] = nomor;
            const basi = () => papan !== this.nomorMuatDetail || nomor !== this.nomorKartu[lama.id];
            this.menyegarkan = true;
            let penuh = false;
            try {
                const res = this.basisLoker
                    ? await axios.get('/api/v1/karir/lamaran/worklist/loker/detail', {
                        params: { kunci: this.selectedId, alur: this.alurPilih || undefined, lamaran: lama.id },
                        ...CFG,
                    })
                    : await axios.get(`/api/v1/karir/lamaran/worklist/program/${this.selectedId}`, {
                        params: { lamaran: lama.id },
                        ...CFG,
                    });
                // Basi: papan dimuat ulang/diganti selagi menunggu, atau kartu
                // yang sama sudah diminta lagi oleh aksi berikutnya.
                if (basi()) return;

                const hasil = res.data?.result || {};
                const baru = (hasil.pelamar || [])[0];
                const i = (this.detail?.pelamar || []).findIndex((r) => r.id === lama.id);
                const kolomAda = !!baru && (this.detail.kolom || []).some((k) => k.kode === baru.kolomKode);
                if (!baru || i < 0 || !kolomAda || baru.statusLamaran !== lama.statusLamaran) {
                    penuh = true;
                } else {
                    this.detail.pelamar.splice(i, 1, baru);
                    this.detail.kampusBendera = { ...(this.detail.kampusBendera || {}), ...(hasil.kampusBendera || {}) };
                    this.segarkanDrawer();
                }
            } catch (e) {
                penuh = !basi();
            } finally {
                // Yang akan memuat penuh membiarkan tombolnya tetap berputar —
                // muatDetail() yang menghentikannya.
                if (!basi() && !penuh) this.menyegarkan = false;
            }
            if (penuh) await this.muatDetail(this.selectedId, { senyap: true });
        },
        goJadwal() { router.visit('/karir/penjadwalan'); },
        /* ── Drawer ── */
        /**
         * Tetapkan antrean tinjau dari daftar tempat kandidat diklik.
         *
         * Dipanggil di titik KLIK, bukan di dalam bukaKandidat(): hanya pemanggil
         * yang tahu daftar mana yang sedang dilihat orang. Kartu di kolom
         * "Screening CV" harus menelusuri kolom itu saja, sementara baris di
         * tampilan daftar menelusuri halaman daftar yang sedang tampil.
         */
        setAntrean(daftar, label = '') {
            this.antreanTinjau = (daftar || []).map((x) => x.id);
            this.antreanLabel = label;
        },
        /**
         * Pindah ke tetangga di antrean.
         *
         * Di ujung antrean TIDAK diam saja: tombolnya memang sudah mati, tapi
         * pintasan papan tik masih bisa ditekan, dan diam adalah jawaban paling
         * membingungkan untuk sebuah tombol yang ditekan. Jadi ujung antrean
         * menjawab dengan alert.
         */
        geserTinjau(arah) {
            if (!this.detailKandidat) return;

            const id = arah > 0 ? this.tetanggaTinjau.maju : this.tetanggaTinjau.mundur;

            if (!id) {
                this.alertAntrean(arah);

                return;
            }

            const baris = this.pelamarTampil.find((x) => x.id === id);
            if (!baris) return;

            this.antreanAlert = null;
            this.bukaKandidat(baris);
        },
        /**
         * Pemberitahuan ujung antrean.
         *
         * Sengaja BUKAN this.notice(): notice muncul sebagai toast biasa yang
         * bisa tertutup modal (modal punya konteks tumpukan sendiri). Alert ini
         * di-teleport ke <body> dengan z-index tertinggi supaya benar-benar
         * terlihat di atas jendela detail yang sedang terbuka.
         */
        alertAntrean(arah) {
            const sisa = this.antreanTinjau.length;
            const ke = this.indeksTinjau + 1;

            this.antreanAlert = arah > 0
                ? {
                    nada: 'habis',
                    ikon: 'bi-flag-fill',
                    judul: 'Ini kandidat terakhir',
                    pesan: `Sudah sampai ujung ${this.antreanLabel ? `"${this.antreanLabel}"` : 'antrean'} $— nomor ${ke} dari ${sisa}. Tidak ada kandidat berikutnya di daftar ini.`,
                }
                : {
                    nada: 'awal',
                    ikon: 'bi-arrow-bar-left',
                    judul: 'Ini kandidat pertama',
                    pesan: `Sudah di awal ${this.antreanLabel ? `"${this.antreanLabel}"` : 'antrean'}. Tidak ada kandidat sebelumnya.`,
                };

            clearTimeout(this.antreanAlertTimer);
            this.antreanAlertTimer = setTimeout(() => { this.antreanAlert = null; }, 4200);
        },
        async bukaKandidat(r) {
            this.detailKandidat = r;
            // Pilihan alasan jendela Tunda dimuat diam-diam sejak drawer dibuka
            // (sekali per halaman; tidak menunggu berkas yang bisa lama) — saat
            // tombol Tunda diklik, pilihannya sudah ada.
            if (this.aksiSaya.includes('EDIT') && r?.statusLamaran === 'BERJALAN') muatOpsiTunda().catch(() => {});
            // Selalu kembali ke Rapor Tes. Tab terakhir yang dibuka milik
            // KANDIDAT SEBELUMNYA; membawanya ke kandidat berikutnya berarti
            // jendela terbuka di "Berkas & Biodata" yang masih memuat, dan
            // alasan orang ini dibuka — hasil tesnya — justru tak terlihat.
            this.tabAktif = 'rapor';
            this.openForm = 0;
            // Folder, pencarian, dan sorotan file manager milik kandidat tadi.
            // Yang tersisa akan menyaring berkas orang lain memakai kata kunci
            // yang tak pernah diketikkan untuknya — dan tampak seperti berkasnya
            // hilang. Modenya TIDAK direset: itu preferensi peninjau.
            this.fmFolder = '';
            this.fmCari = '';
            this.fmSorot = '';
            // Lembar & saringan kolom pratinjau juga milik kandidat tadi. Tanpa
            // ini kandidat berikutnya disambut lembar milik orang sebelumnya,
            // dan tak ada apa pun di layar yang menyebut lembar itu bukan
            // miliknya. Terbuka/terlipatnya kolom TIDAK direset: itu pilihan
            // tata letak milik peninjau, bukan bagian dari kandidatnya.
            this.tutupDokLihat();
            this.dokTab = '';
            this.dokCari = '';
            this.dokSaring = false;
            this.profil = { lamaran: null, formulir: [], tahap: [] };
            this.tahapRiwayat = { show: false, muat: false, id: null, data: null };
            this.berkasHasil = [];
            this.loadingProfil = true;
            try {
                const res = await axios.get(`/api/v1/karir/lamaran/berkas/${r.id}`, CFG);
                this.profil = res.data.result || { lamaran: null, formulir: [], tahap: [] };
                this.bukaBerkasAwal();
            } catch (e) {
                this.notice('Gagal memuat berkas kandidat.', true);
            } finally {
                this.loadingProfil = false;
            }
            // Selalu dipanggil, bukan hanya saat boleh mengunggah — pemanggilan
            // inilah yang membersihkan sisa berkas tahap sebelumnya.
            this.loadBerkas(r?.tahapId || null);
        },
        /**
         * Tunjuk ulang kandidat yang sedang dibuka ke data yang BARU dimuat.
         *
         * `detailKandidat` menyimpan objek kartu hasil klik, sementara
         * muatDetail() hanya mengganti `detail`. Tanpa penunjukan ulang ini,
         * drawer tetap memegang salinan lama — hasil yang baru saja dicatat
         * tidak terlihat sampai halaman di-refresh manual, dan admin mengira
         * simpanannya gagal lalu mencatat dua kali.
         */
        segarkanDrawer() {
            if (!this.detailKandidat) return;

            const kunci = this.detailKandidat.id;
            // Dicari di daftar pelamar mentah, bukan lewat kartuKolom() yang
            // ikut tersaring tab aktif — kandidat yang baru diputus berpindah
            // tab dan akan lolos dari pencarian.
            const baru = (this.detail?.pelamar || []).find((r) => r.id === kunci);

            // Tidak ketemu = kandidat berpindah kolom/tab (mis. baru diputus).
            // Objek lama dibiarkan supaya drawer tidak tiba-tiba kosong.
            if (baru) this.detailKandidat = baru;
        },
        tutupKandidat() {
            this.detailKandidat = null;
            // Antrean tinjau ikut dibuang: ia milik daftar yang tadi diklik,
            // dan jendela berikutnya bisa saja dibuka dari daftar yang lain.
            this.antreanTinjau = [];
            this.antreanLabel = '';
            this.antreanAlert = null;
            clearTimeout(this.antreanAlertTimer);
            this.lightbox = null;
            this.berkasHasil = [];
            // Pratinjau di panel kanan ikut ditutup — kalau tidak, kandidat
            // BERIKUTNYA yang dibuka menyambut peninjaunya dengan lembar milik
            // orang sebelumnya, dan tidak ada apa pun di layar yang menyebutkan
            // bahwa lembar itu bukan miliknya. Panelnya sendiri tidak dilipat:
            // terbuka/terlipat adalah pilihan tata letak, bukan bagian dari
            // kandidat yang sedang dibaca.
            this.tutupDokLihat();
            this.dokCari = '';
            this.dokTab = '';
        },
        /* ── Berkas hasil tahap (MCU/Interview) ── */
        // Keduanya dari SALINAN TAHAP milik kandidat, bukan kolom master yang
        // dicari lewat nomor urut. Ini yang paling merugikan kalau salah:
        // menyalakan "wajib unggah" di Master Alur akan mengunci kandidat yang
        // tahapnya sudah selesai dinilai — menuntut dokumen yang saat mereka
        // menjalaninya memang tidak pernah diminta.
        bolehUpload(r) {
            if (!r || !r.butuhKeputusan) return false;

            return !!r.perilaku?.uploadHasil;
        },
        wajibUpload(r) {
            return !!r?.perilaku?.wajibUpload;
        },
        /**
         * Konfirmasi "Ya, Loloskan" tertahan bila upload wajib tapi belum ada
         * berkas. Yang ditahan HANYA tombol di dalam modal — tombol Loloskan di
         * drawer tetap bisa diklik, karena unggahannya ada di modal itu sendiri.
         */
        uploadKurang(r) { return this.bolehUpload(r) && this.wajibUpload(r) && this.berkasHasil.length === 0; },
        /**
         * Ada aktivitas berjadwal yang kehadirannya belum ditetapkan.
         *
         * Inilah SATU-SATUNYA hal yang menahan tombol keputusan: selama hadir
         * atau tidak hadir sudah ditetapkan, admin boleh mengetuk palu.
         */
        /** Sudah ada isinya untuk dibuka? Menghitung ini sekali menghindari
         *  tombol "Detail" yang membuka panel kosong. */
        jumlahDetail(t) {
            return (t.catatanHtml || t.catatan ? 1 : 0)
                + (t.jadwal?.catatanHtml ? 1 : 0)
                + (t.berkasKandidat || []).length
                + (t.berkas || []).length;
        },
        toggleDetail(t) {
            this.detailTes = this.detailTes === t.id ? null : t.id;
        },
        /** Ringkas jadwal untuk satu baris rapor: "Sen, 12 Agu 2026 · 09.00". */
        jadwalRingkas(j) {
            // Berrentang tanggal (MCU vendor / mandiri): yang berarti rentangnya.
            if (j?.batasWaktu) return `${j.modeNama || 'Mandiri'} · ${j.rentangPendek || fmtRentang(j.mulai, j.batas)}`;
            if (!j?.mulai) return '—';
            const d = new Date(String(j.mulai).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '—';
            const tgl = d.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
            const jam = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace(':', '.');

            return `${tgl} · ${jam} WIB`;
        },
        /**
         * Muat berkas milik SATU tahap.
         *
         * Daftarnya DIKOSONGKAN lebih dulu. Tanpa itu, berkas tahap sebelumnya
         * masih terpampang selama permintaan berjalan — dan bila tahap baru
         * ternyata tidak boleh mengunggah, daftar lama bertahan selamanya
         * sehingga berkas wawancara terlihat seolah milik tahap MCU.
         *
         * `tahapDimuat` menjaga balasan yang datang terlambat: kalau admin
         * sudah berpindah tahap, hasil permintaan lama diabaikan.
         */
        async loadBerkas(tahapId) {
            this.berkasHasil = [];
            this.tahapBerkasId = tahapId;
            if (!tahapId) return;

            try {
                const res = await axios.get(`/api/v1/karir/lamaran/tahap/${tahapId}/berkas`, CFG);
                if (this.tahapBerkasId !== tahapId) return;
                this.berkasHasil = res.data.result || [];
            } catch (e) {
                if (this.tahapBerkasId === tahapId) this.berkasHasil = [];
            }
        },
        async unggahBerkas(ev) {
            const file = ev.target.files?.[0];
            ev.target.value = '';
            if (!file || !this.detailKandidat?.tahapId) return;
            const fd = new FormData();
            fd.append('file', file);
            this.berkasBusy = true;
            try {
                await axios.post(`/api/v1/karir/lamaran/tahap/${this.detailKandidat.tahapId}/berkas`, fd, { headers: { Accept: 'application/json', 'Content-Type': 'multipart/form-data' } });
                this.notice('Berkas terunggah.');
                await this.loadBerkas(this.detailKandidat.tahapId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengunggah.', true);
            } finally {
                this.berkasBusy = false;
            }
        },
        async hapusBerkas(b) {
            try {
                await axios.delete(`/api/v1/karir/lamaran/tahap/berkas/${b.id}`, CFG);
                this.notice('Berkas dihapus.');
                await this.loadBerkas(this.detailKandidat.tahapId);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.', true);
            }
        },
        previewBerkas(b) {
            const gambar = ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(String(b.ext || '').toLowerCase());
            this.lightbox = { nama: b.nama, field: 'hasil', status: '', url: b.url, pdf: !gambar };
            this.lbSrc = b.url;
            this.mulaiMuat();
        },
        /**
         * Mulai memuat pratinjau, dengan BATAS WAKTU.
         *
         * URL berkas mengalihkan ke signed URL GCS. Pada PDF di dalam iframe,
         * peristiwa `load` tidak selalu terpicu dan `error` hampir tidak pernah —
         * sehingga spinner bisa berputar selamanya padahal berkasnya sudah
         * tampil. Batas 10 detik membuat keadaan menggantung itu mustahil.
         */
        mulaiMuat() {
            this.lbLoading = true;
            this.lbError = false;
            if (this.lbTimer) clearTimeout(this.lbTimer);
            this.lbTimer = setTimeout(() => { this.lbLoading = false; }, 10000);
        },
        selesaiMuat(gagal = false) {
            if (this.lbTimer) clearTimeout(this.lbTimer);
            this.lbLoading = false;
            this.lbError = gagal;
        },
        /**
         * Keterangan lencana "Diunggah admin": siapa yang mengunggah, lalu
         * alasan yang ia tulis di panel Pemulihan Berkas. Berkas seperti ini
         * bukan kiriman kandidat sendiri — peninjau berhak tahu asal-usulnya
         * sebelum menilainya.
         */
        judulOleh(b) {
            return [`Diunggah admin${b && b.oleh ? ` — ${b.oleh}` : ''}`, b && b.catatan].filter(Boolean).join('\n');
        },
        /**
         * Buka dokumen di modal — gambar MAUPUN PDF.
         *
         * PDF dulu dilempar ke tab baru, jadi admin kehilangan konteks drawer
         * yang sedang dibacanya. Modalnya sanggup menyematkan PDF lewat iframe.
         */
        /* ── Riwayat tahap (Progres Seleksi yang bisa diklik) ── */
        async bukaTahapRiwayat(id) {
            if (!id) return;
            this.tahapRiwayat = { show: true, muat: true, id, data: null };
            try {
                const res = await axios.get(`/api/v1/karir/lamaran/tahap/${id}/detail`, CFG);
                if (this.tahapRiwayat.id === id) this.tahapRiwayat.data = res.data.result;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memuat isi tahap.', true);
                if (this.tahapRiwayat.id === id) this.tahapRiwayat.show = false;
            } finally {
                if (this.tahapRiwayat.id === id) this.tahapRiwayat.muat = false;
            }
        },
        /** Id tahap tetangga (yang sudah dimulai) untuk tombol sebelumnya/berikutnya. */
        tahapRiwayatGeser(arah) {
            const bisa = this.tahapKandidat.filter((t) => !['MENUNGGU', 'BELUM'].includes(t.status));
            const i = bisa.findIndex((t) => t.id === this.tahapRiwayat.id);

            return i < 0 ? null : bisa[i + arah]?.id || null;
        },
        /** Nama hasil dari master Hasil Keputusan — sama dengan tombol keputusan. */
        labelHasilTahap(t) {
            if (t.bypass) return 'Dilewati';
            if (!t.hasil) return t.status === 'BERJALAN' ? 'Berlangsung' : 'Belum diputus';

            return this.hasilKeputusan.find((h) => h.kode === t.hasil)?.nama || t.hasil;
        },
        kelasHasilTahap(t) {
            if (t.bypass) return 'is-note';
            if (!t.hasil) return t.status === 'BERJALAN' ? 'is-sched' : 'is-wait';
            const def = this.hasilKeputusan.find((h) => h.kode === t.hasil);

            return (def ? def.lolos : t.hasil === 'LULUS') ? 'is-pass' : 'is-fail';
        },
        /** Berkas satu formulir (dari daftar panel berkas) — untuk modal riwayat tahap. */
        berkasFormulir(f) {
            return this.fmSemua.filter((b) => b.folder === String(f.no));
        },
        /** Buka tab Berkas & Biodata pada formulir itu, lalu tutup modal riwayat. */
        lihatFormulirTahap(f) {
            this.tahapRiwayat.show = false;
            this.tabAktif = 'berkas';
            this.fmFolder = String(f.no);
        },
        bukaDok(b) {
            this.lightbox = { ...b, pdf: !b.isImage };
            this.lbSrc = b.url;
            this.mulaiMuat();
        },
        // Muat ulang gambar (signed URL bisa kedaluwarsa) — cache-buster kecil.
        lbCoba() {
            if (!this.lightbox) return;
            this.lbError = false;
            this.lbLoading = true;
            this.lbSrc = this.lightbox.url + (this.lightbox.url.includes('?') ? '&' : '?') + 'r=' + Date.now();
        },
        /* ── Rapor tes (multi-tes) ── */
        pillTes(t) {
            if (t.status === 'TIDAK_HADIR') return 'is-absent';
            // Surat penawaran tidak menunggu apa pun — dokumennya diunggah saat
            // keputusan diambil, jadi lencana "Menunggu" hanya menyesatkan.
            if (t.infoSaja && t.status !== 'SELESAI') return 'is-note';
            if (t.status !== 'SELESAI') return t.status === 'DIJADWALKAN' ? 'is-sched' : 'is-wait';
            if (t.peran === 'INFORMATIF') return 'is-done';
            return t.hasil === 'LULUS' ? 'is-pass' : 'is-fail';
        },
        labelTes(t) {
            if (t.status === 'TIDAK_HADIR') return 'Tidak hadir';
            if (t.infoSaja && t.status !== 'SELESAI') return 'Diunggah saat keputusan';
            // Ujian online: sebutkan yang sedang ditunggu. "Menunggu" saja bikin
            // admin mengira ada yang harus ia kerjakan, padahal giliran sistem.
            // Nilainya sudah masuk; yang ditunggu ORANG DI KANTOR INI, bukan HCLearn.
            if (t.butuhKeputusan) return 'Sudah dites — tunggu keputusan';
            if (t.status === 'DIJADWALKAN') return t.online ? 'Menunggu hasil HCLearn' : 'Dijadwalkan';
            if (t.status !== 'SELESAI') return t.online ? 'Belum dijadwalkan' : 'Menunggu';
            if (t.peran === 'INFORMATIF') return 'Selesai';
            return t.hasil === 'LULUS' ? 'Lulus' : 'Gagal';
        },
        /**
         * Boleh dicatat hasilnya? Server yang memutuskan (lihat rapotTes di
         * LamaranController) — aktivitas tunggal tanpa jenis tes tidak punya
         * hasil sendiri, jadi tombolnya tidak ditampilkan.
         */
        /**
         * Hasil hanya boleh dicatat SETELAH kehadiran ditetapkan.
         *
         * Tanpa gerbang ini, tim bisa melampirkan hasil MCU untuk orang yang
         * ternyata tidak datang — dan itu baru ketahuan jauh di belakang.
         */
        /** Nama akun berbeda dari nama formulir? Perbandingan tak peka huruf besar. */
        namaAkunBeda(r) {
            const a = (r?.pelamarAkun || '').trim().toLowerCase();
            const f = (r?.pelamar || '').trim().toLowerCase();

            return !!a && !!f && a !== f;
        },
        bisaCatatKehadiran(t) { return !t.butuhKehadiran; },
        /**
         * Keterangan tombol "Atur / Ubah Jadwal".
         *
         * Tiga bunyi, bukan dua. Jadwal internal (negosiasi penawaran) TIDAK
         * mengirim apa pun: tidak ada email, dan portal kandidat tidak
         * menampilkannya. Menjanjikan "kandidat diundang lewat email" di situ
         * membuat rekruter menunggu jawaban atas undangan yang tak pernah
         * dikirim — dan diamnya kandidat terbaca sebagai penolakan.
         */
        judulTombolJadwal(t) {
            if (t.jadwalPrivat) {
                return 'Tetapkan waktu & tempat. Jadwal ini internal — kandidat tidak diundang dan tidak melihatnya di portal.';
            }

            return t.jadwal
                ? 'Ubah jadwal — undangan dikirim ulang'
                : 'Tetapkan waktu & tempat, kandidat diundang lewat email';
        },
        bisaCatat(t) {
            return this.detailKandidat?.statusLamaran === 'BERJALAN' && !!t.dapatDicatat;
        },
        /**
         * Sepasang tombol Lulus / Tidak Lulus pada ujian online INFORMATIF.
         *
         * Server yang menentukan (`dapatPutusTes` di rapotTes): ujian sudah
         * dikerjakan, nilainya sudah masuk, dan perannya memang menyerahkan
         * verdict ke penilai.
         */
        bisaPutusTes(t) {
            return this.detailKandidat?.statusLamaran === 'BERJALAN' && !!t.dapatPutusTes;
        },
        /**
         * "Tidak hadir" sebagai ESCAPE HATCH — untuk aktivitas yang tidak punya
         * pasangan tombol Hadir/Tidak Hadir (mis. ujian online yang hasilnya tak
         * kunjung datang). Aktivitas berjadwal yang kehadirannya memang sedang
         * ditanyakan tidak ikut: dua tombol bertuliskan hal sama, dengan modal
         * berbeda, hanya membuat admin menebak mana yang benar.
         */
        bisaTidakHadir(t) {
            return this.detailKandidat?.statusLamaran === 'BERJALAN'
                && !!t.dapatTidakHadir
                && !t.butuhKehadiran;
        },
        /**
         * SETUJU — satu tekan, tanpa jendela.
         *
         * Petugas sedang menelepon kandidatnya saat menekan ini. Kalimat
         * lengkapnya (siapa yang menanyakan, kapan) dirakit server, dan
         * tersimpan sebagai riwayat aktivitas.
         */
        async setujuPeriksa(t) {
            if (this.setujuId) return;
            this.setujuId = t.id;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${t.id}/persetujuan`, { sumber: 'TELEPON' }, CFG);
                this.notice(res.data?.message || 'Persetujuan dicatat.');
                await this.segarkanKartu({ tes: t.id });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mencatat persetujuan.', true);
            } finally {
                this.setujuId = null;
            }
        },
        askTolakPeriksa(t) {
            this.tolakTarget = t;
            this.tolakAlasan = '';
            this.tolakShow = true;
        },
        async kirimTolakPeriksa() {
            if (this.sibuk || !this.tolakTarget || !this.tolakAlasan.trim()) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(
                    `/api/v1/karir/lamaran/sub-tes/${this.tolakTarget.id}/persetujuan`,
                    { sumber: 'TOLAK', keterangan: this.tolakAlasan.trim() },
                    CFG,
                );
                const tes = this.tolakTarget.id;
                this.notice(res.data?.message || 'Penolakan dicatat.');
                this.tolakShow = false;
                this.tolakTarget = null;
                await this.segarkanKartu({ tes });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mencatat penolakan.', true);
            } finally {
                this.sibuk = false;
            }
        },
        // ══ PHONE SCREENING ═════════════════════════════════════════════════

        labelRekomSkr(r) {
            return { LANJUT: 'lanjut', PERTIMBANGAN: 'perlu pertimbangan', TIDAK_LANJUT: 'tidak dilanjutkan' }[r] || r;
        },
        kelasRekomSkr(r) {
            return { LANJUT: 'is-ok', PERTIMBANGAN: 'is-warn', TIDAK_LANJUT: 'is-no' }[r] || '';
        },

        async bukaSkrining(t) {
            this.skrTarget = t;
            this.skrIsi = null;
            this.skrShow = true;
            await this.muatSkrining();
        },
        /** Ambil isi penuh — pertanyaan, jawaban, keadaan sesi. */
        async muatSkrining() {
            if (! this.skrTarget) return;

            try {
                const { data } = await axios.get(`/api/v1/karir/lamaran/sub-tes/${this.skrTarget.id}/skrining`, CFG);
                this.skrIsi = data.result || null;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memuat kuesioner.', true);
                this.skrShow = false;
            }
        },
        /**
         * Panel menyimpan sendiri, lalu memanggil ini.
         *
         * Isi jendela dimuat ulang karena skor dan penanda gugur dihitung
         * server — panel tidak boleh menebaknya sendiri. Papan aktivitas di
         * belakang TIDAK ikut dimuat ulang: memuat ulang detail kandidat
         * mengganti objek yang sedang dipegang jendela, dan isinya berkedip di
         * tengah orang mengetik. Kenyataan di papan menyusul saat ditutup.
         */
        async perbaruiSkrining(galat) {
            if (galat) {
                this.notice(galat, true);

                return;
            }

            await this.muatSkrining();
        },
        mintaSelesaiSkrining(kurang) {
            if (kurang && kurang.length) {
                this.notice(`Masih ada ${kurang.length} pertanyaan wajib yang kosong.`, true);

                return;
            }

            this.skrSelesaiShow = true;
        },
        async selesaikanSkrining() {
            const sesi = this.skrIsi?.sesi;
            if (! sesi) return;

            this.skrBusy = true;
            try {
                // Muatan diambil dari panel supaya jawaban yang belum sempat
                // tersimpan otomatis ikut terkirim — kalau tidak, sentuhan
                // terakhir sebelum menekan Selesaikan hilang tanpa jejak.
                const panel = this.$refs.panelSkr;
                const muatan = panel?.muatan
                    ? panel.muatan()
                    : { rekomendasi: sesi.rekomendasi, ringkasanHtml: sesi.ringkasanHtml, jawaban: [] };

                if (! muatan.rekomendasi) {
                    this.notice('Pilih rekomendasi dulu sebelum menyelesaikan sesi.', true);
                    this.skrBusy = false;
                    this.skrSelesaiShow = false;

                    return;
                }

                const { data } = await axios.post(`/api/v1/karir/lamaran/skrining/${sesi.id}/selesai`, muatan, CFG);
                this.notice(data.message || 'Sesi selesai.');
                this.skrSelesaiShow = false;
                await this.muatSkrining();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyelesaikan sesi.', true);
            } finally {
                this.skrBusy = false;
            }
        },
        async bukaKunciSkrining() {
            const sesi = this.skrIsi?.sesi;
            if (! sesi) return;

            this.skrBusy = true;
            try {
                const { data } = await axios.post(
                    `/api/v1/karir/lamaran/skrining/${sesi.id}/buka-kunci`,
                    { alasan: this.skrAlasan },
                    CFG,
                );
                this.notice(data.message || 'Sesi dibuka kembali.');
                this.skrBukaKunciShow = false;
                this.skrAlasan = '';
                await this.muatSkrining();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal membuka kunci.', true);
            } finally {
                this.skrBusy = false;
            }
        },
        /**
         * Papan disegarkan SAAT DITUTUP — bukan tiap kali panel menyimpan.
         *
         * Sejak isian skrining tidak lagi tersimpan sendiri, menutup jendela
         * dengan perubahan yang menggantung berarti membuangnya. Ditanyakan
         * dulu — dan pertanyaannya menyebut APA yang hilang, bukan "yakin?".
         */
        async tutupSkrining() {
            const panel = this.$refs.panelSkr;
            if (panel?.kotor && ! window.confirm(
                'Ada jawaban skrining yang belum disimpan. Tutup dan buang perubahan itu?'
            )) {
                return;
            }

            const tes = this.skrTarget?.id;
            this.skrShow = false;
            this.skrTarget = null;
            this.skrIsi = null;
            if (this.selectedId) await this.segarkanKartu({ tes });
        },

        askCatat(t) {
            this.catatTarget = t;
            this.catatHasil = 'LULUS';
            // Nilai yang SUDAH tercatat ikut dimuat — alasannya sama dengan
            // catatan di bawah: mencatat hasil sering menyusul pencatatan
            // kehadiran, dan membiarkan bidangnya kosong akan menghapus angka
            // atau predikat yang sudah ditulis di sana.
            this.catatNilai = t.nilai ?? null;
            this.catatNilaiTeks = t.nilaiTeks || '';
            this.catatAdjudikasi = t.pemeriksaan?.adjudikasi || '';
            // Catatan yang SUDAH ada dimuat kembali — mencatat hasil kerap
            // menyusul pencatatan kehadiran yang catatannya sudah ditulis, dan
            // membiarkan editor kosong akan menghapusnya begitu disimpan.
            this.catatCatatanHtml = t.catatanHtml || '';
            this.catatShow = true;
        },

        /**
         * Berkas aktivitas berubah dari dalam modal.
         *
         * Detail kandidat TIDAK dimuat ulang di sini: memuat ulang akan
         * mengganti objek `catatTarget`/`hadirTarget` yang sedang dipegang modal
         * sehingga isinya berkedip dan editor kehilangan fokus di tengah
         * mengetik. Daftar berkasnya sudah dikelola komponennya sendiri;
         * kenyataan di layar besar menyusul saat modal ditutup.
         */
        /**
         * Panel pemeriksaan menyimpan sendiri ke server; yang dikembalikannya
         * adalah keadaan TERBARU seluruh pemeriksaan aktivitas ini.
         *
         * Ditempelkan langsung ke objek yang sedang dipegang modal — memuat
         * ulang detail kandidat akan mengganti `catatTarget` dengan objek baru,
         * dan modal yang sedang terbuka kehilangan pegangannya di tengah kerja.
         */
        perbaruiPemeriksaan(baru) {
            if (! baru || ! this.catatTarget) return;

            this.catatTarget = { ...this.catatTarget, pemeriksaan: baru };
            this.berkasAktivitasBerubah = true;
        },
        /**
         * Berkas yang dilampirkan DARI DALAM panel pemeriksaan.
         *
         * Server mengembalikan daftar utuh aktivitas ini, jadi kotak berkas di
         * bawah panel dan lampiran per komponen membaca satu daftar yang sama —
         * bukan dua salinan yang mulai berselisih sesudah unggahan ketiga.
         */
        perbaruiBerkasAktivitas(daftar) {
            if (! this.catatTarget) return;

            this.catatTarget = { ...this.catatTarget, berkas: daftar || [] };
            this.berkasAktivitasBerubah = true;
        },
        tandaiBerkasBerubah() {
            this.berkasAktivitasBerubah = true;
        },

        /**
         * Tutup modal aktivitas tanpa menyimpan.
         *
         * Berkas yang telanjur diunggah TIDAK ikut dibatalkan — ia sudah ada di
         * server dan memang milik aktivitas itu. Yang perlu menyusul hanyalah
         * layar besar, supaya lampirannya tidak "hilang" sampai halaman dimuat
         * ulang secara manual.
         */
        tutupModalAktivitas(kunci) {
            this[kunci] = false;
            if (this.berkasAktivitasBerubah) {
                this.berkasAktivitasBerubah = false;
                this.muatDetail(this.selectedId, { senyap: true });
            }
        },
        /** Alasan HOLD dari master — dimuat sekali per sesi. */
        async muatAlasanHold() {
            if (this.alasanHold.length) return;
            try {
                const res = await axios.get('/api/v1/karir/options/alasan-hold', CFG);
                this.alasanHold = res.data.result || [];
            } catch (e) {
                this.alasanHold = [];
            }
        },
        /**
         * @param {boolean} menahan
         * @param {object|null} baris kandidat dari KARTU; kosong = dari drawer.
         */
        /**
         * Buka dialog pengulangan, lalu ambil daftar tahapnya.
         *
         * Daftarnya diambil SAAT DIBUKA, bukan ikut menempel di muatan worklist:
         * satu halaman worklist berisi puluhan kandidat, dan membawa daftar tahap
         * masing-masing berarti memperbesar setiap pemuatan demi dialog yang
         * mungkin tidak pernah dibuka.
         */
        async bukaUlang() {
            if (!this.detailKandidat) return;

            this.ulangShow = true;
            this.ulangMuat = true;
            this.ulangTahapList = [];
            this.ulangRiwayat = [];
            this.ulangTujuan = null;
            this.ulangCakupan = 'TAHAP';
            this.ulangAlasan = '';
            this.ulangEmail = false;

            try {
                const res = await axios.get(
                    `/api/v1/karir/lamaran/${this.detailKandidat.id}/ulang/tahap`,
                    { headers: { Accept: 'application/json' } },
                );
                const d = res.data?.result || {};
                this.ulangTahapList = d.tahap || [];
                this.ulangRiwayat = d.riwayat || [];
                this.ulangPutaran = d.putaran || 1;

                // Bawaannya tahap yang SEDANG dijalani — itu yang paling sering
                // perlu diulang, dan admin tinggal menggeser bila bukan itu.
                const sekarang = this.ulangTahapList.find((t) => t.urutan === d.urutanSekarang);
                this.ulangTujuan = (sekarang || this.ulangTahapList[this.ulangTahapList.length - 1] || {}).id || null;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memuat daftar tahap.', true);
                this.ulangShow = false;
            } finally {
                this.ulangMuat = false;
            }
        },

        async konfirmUlang() {
            if (!this.bolehSimpanUlang || this.sibuk) return;

            this.sibuk = true;
            try {
                const res = await axios.patch(
                    `/api/v1/karir/lamaran/tahap/${this.ulangTujuan}/ulang`,
                    {
                        cakupan: this.ulangCakupan,
                        alasan: this.ulangAlasan.trim(),
                        kirimEmail: this.ulangEmail,
                    },
                    { headers: { Accept: 'application/json' } },
                );

                this.ulangShow = false;
                this.notice(res.data?.message || 'Tahap diulang.');
                // Kartu kandidat ini disegarkan: tahap, aktivitas, dan berkasnya
                // semuanya baru saja berubah.
                if (this.selectedId) await this.segarkanKartu({ lamaran: this.detailKandidat?.id });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengulang tahap.', true);
            } finally {
                this.sibuk = false;
            }
        },

        askHold(menahan, baris = null) {
            this.holdNilai = menahan;
            this.holdTarget = baris || this.detailKandidat;
            this.holdAlasan = '';
            this.holdCatatanHtml = '';
            if (menahan) this.muatAlasanHold();
            this.holdShow = true;
        },

        /* ── TAHAN / LANJUTKAN MASSAL ──────────────────────────────────────── */
        /** Alasan ini menuntut keterangan? Dibaca dari master, bukan didaftar. */
        alasanButuhCatatan(kode) {
            return !!this.alasanHold.find((a) => a.value === kode)?.butuhCatatan;
        },
        /** Apa yang kurang dari satu baris — dipakai penanda merah & tombol simpan. */
        /** Kekurangan satu baris keputusan massal — dipakai menandai barisnya sendiri. */
        barisPmKurang(b) {
            if (!b?.hasil) return 'Keputusan belum dipilih';
            const def = this.hasilKeputusan.find((h) => h.kode === b.hasil);
            // Isinya kini HTML. `<p><br></p>` — yang ditinggalkan Quill pada
            // editor kosong — panjangnya 11 huruf dan akan lolos dari uji
            // "sudah diisi" kalau dinilai apa adanya.
            if (def?.butuhAlasan && !teksDariHtml(b.catatanHtml).trim()) return 'Alasan wajib';

            return '';
        },
        async askPutusMassal() {
            if (!this.bolehPutus) {
                return this.notice('Anda tidak punya hak akses untuk mengambil keputusan.', true);
            }
            const bisa = this.bisaPutusMassal;
            if (!bisa.length) return;
            // PENJAGA KEDUA, bukan pengulangan yang sia-sia.
            //
            // Tombolnya memang sudah mati saat kelebihan, tapi jalan masuk ke
            // sini bukan cuma tombol itu: modal halaman ini dipulihkan setelah
            // refresh (lihat ingatModal), dan pilihan bisa berubah di antara
            // dibukanya modal dan ditekannya simpan. Menolak di sini jauh lebih
            // murah daripada membiarkan 40 alasan diketik lalu ditolak server.
            if (this.lebihanPutusMassal) {
                return this.notice(this.gerbangPutusMassal.sebab, true);
            }

            this.pmTarget = bisa;
            // Yang tercentang tapi tak bisa diproses — disebut di modal supaya
            // admin tahu SEBELUM menyimpan, bukan sesudah.
            this.pmDilewati = this.barisTerpilih.filter((r) => !bisa.includes(r));
            this.pmPola = 'SERAGAM';
            this.pmHasil = '';
            this.pmCatatanHtml = '';
            this.pmCatatanEksternalHtml = '';
            this.pmTabCatatan = this.tabAwalCatatan('');
            this.pmBaris = bisa.map((r) => ({
                id: r.id,
                tahapId: r.tahapId,
                pelamar: r.pelamar,
                posisi: r.posisi,
                tahap: r.tahap,
                hasil: '',
                catatanHtml: '',
                catatanEksternalHtml: '',
                tabCatatan: this.tabAwalCatatan(''),
            }));
            this.pmShow = true;
        },
        /** Salin keputusan & alasan baris pertama ke seluruh baris. */
        sebarPutusBarisPertama() {
            const a = this.pmBaris[0];
            if (!a?.hasil) return;
            this.pmBaris = this.pmBaris.map((b, i) => (i === 0 ? b : {
                ...b,
                hasil: a.hasil,
                catatanHtml: a.catatanHtml,
                catatanEksternalHtml: a.catatanEksternalHtml,
                tabCatatan: a.tabCatatan,
            }));
            const nama = this.hasilKeputusan.find((x) => x.kode === a.hasil)?.nama || a.hasil;
            this.notice(`Keputusan "${nama}" disalin ke ${this.pmBaris.length - 1} baris lain.`);
        },
        /**
         * KEPUTUSAN MASSAL — DIKIRIM BERGELOMBANG, BUKAN SEKALI TARIKAN.
         *
         * Lima puluh kandidat dulu dikirim dalam SATU permintaan. Tiap item di
         * dalamnya menjalankan keputusan penuh — mesin syarat, tulis riwayat,
         * terbitkan tugas email — dan lima puluh kali pekerjaan itu melewati
         * batas waktu permintaan. Yang terjadi bukan "gagal semua" melainkan
         * yang lebih buruk: beberapa orang pertama benar-benar diputus, sisanya
         * tidak, dan layar hanya memuntahkan satu galat. Admin menekan ulang,
         * lalu orang yang sudah diputus ditolak "tahap sudah diputus" — dan
         * tak ada satu pun tempat yang mengatakan siapa yang sudah selesai.
         *
         * Sekarang dikirim per gelombang kecil, berurutan. Tiap gelombang
         * permintaannya sendiri, jadi tidak ada yang menabrak batas waktu, dan
         * kemajuannya nyata: angkanya dihitung dari balasan server, bukan
         * animasi.
         *
         * YANG GAGAL DISIMPAN BESERTA KIRIMANNYA, supaya "Coba Lagi" mengulang
         * TEPAT yang gagal — bukan seluruh daftar. Mengulang semua berarti
         * mengetuk palu dua kali pada orang yang keputusannya sudah sah.
         *
         * BERJALAN SELAMA TABNYA TERBUKA. Menutupnya di tengah jalan
         * menghentikan sisanya — dan itu disebutkan di panel, bukan dibiarkan
         * jadi kejutan. (Memindahkannya ke antrean latar menuntut putus()
         * berhenti bergantung pada sesi admin yang sedang masuk; itu pekerjaan
         * tersendiri.)
         */
        /**
         * KEPUTUSAN MASSAL — DIANTREKAN, BUKAN DITUNGGU.
         *
         * Layar hanya menitipkan pekerjaannya lalu menutup jendela. Yang
         * mengerjakan keputusan satu per satu adalah WcPutusMassalJob di
         * antrean; kemajuannya dibaca panel dari keadaan data yang
         * sesungguhnya, bukan dari ingatan tab ini. Menutup tab tidak
         * membatalkan apa pun.
         */
        async konfirmPutusMassal() {
            if (this.sibuk || !this.pmTarget.length) return;
            this.sibuk = true;
            try {
                // SATU BENTUK KIRIMAN untuk kedua mode — mode seragam hanya
                // mengisi keputusan yang sama ke tiap item di sini. Server tidak
                // perlu tahu mode mana yang dipakai, jadi tidak ada aturan kedua
                // yang harus dijaga tetap sama dengan yang pertama.
                const item = this.pmTarget.map((r) => {
                    if (this.pmPola === 'SERAGAM') {
                        return {
                            tahapId: r.tahapId,
                            hasil: this.pmHasil,
                            catatan: teksDariHtml(this.pmCatatanHtml) || null,
                            catatanHtml: this.pmCatatanHtml || null,
                            catatanEksternalHtml: this.catatanEksternalKirim(this.pmCatatanEksternalHtml),
                        };
                    }
                    const b = this.pmBaris.find((x) => x.tahapId === r.tahapId);

                    return {
                        tahapId: r.tahapId,
                        hasil: b?.hasil,
                        catatan: teksDariHtml(b?.catatanHtml) || null,
                        catatanHtml: b?.catatanHtml || null,
                        catatanEksternalHtml: this.catatanEksternalKirim(b?.catatanEksternalHtml),
                    };
                });

                const res = await axios.patch(
                    '/api/v1/karir/lamaran/tahap/putus-massal',
                    { item, catatAktivitas: this.pmCatatAktivitas },
                    CFG,
                );

                const r = res.data?.result || {};
                this.notice(res.data?.message || 'Keputusan diantrekan.');

                // Yang DITOLAK DI MUKA (tahapnya sudah diputus, id tak dikenal)
                // tidak pernah masuk antrean, jadi ia tidak akan muncul di panel.
                // Disebut sekarang, atau tidak sama sekali.
                (r.tolak || []).slice(0, 5).forEach((t) => this.notice(`✗ ${t.nama || t.tahapId} — ${t.pesan}`, true));

                this.pmShow = false;
                this.terpilih = [];
                this.tutupPilihKolom();
                this.pantauGelombang(r.gelombang, (r.antre || []).map((a) => a.tahapId));
            } catch (e) {
                const tolak = e.response?.data?.result?.tolak || [];
                const rinci = tolak.length
                    ? ` (${tolak.slice(0, 3).map((t) => t.nama || '?').join(', ')}${tolak.length > 3 ? ', …' : ''})`
                    : '';
                this.notice((e.response?.data?.message || 'Gagal mengantrekan keputusan.') + rinci, true);
            } finally {
                this.sibuk = false;
            }
        },
        /**
         * Pantau satu gelombang sampai tak ada lagi yang menunggu.
         *
         * Daftar id-nya disimpan di peramban, bukan cuma di ingatan komponen:
         * gelombang 150 orang berjalan beberapa menit, dan admin yang menyegarkan
         * halaman di tengahnya tidak boleh kehilangan satu-satunya tempat yang
         * memberitahunya siapa saja yang gagal.
         */
        pantauGelombang(gelombang, ids) {
            if (!gelombang || !ids?.length) return;

            this.borong = {
                gelombang,
                ids,
                total: ids.length,
                selesai: 0,
                gagal: 0,
                menunggu: ids.length,
                program: null,
                tahap: null,
                baris: [],
                jalan: true,
            };

            try {
                localStorage.setItem('plwBorong', JSON.stringify({ gelombang, ids }));
            } catch (e) {
                // Peramban menolak menyimpan (mode privat, kuota penuh) — panel
                // tetap jalan, cuma tidak selamat dari muat ulang.
            }

            this.hentikanPantauBorong();
            this.denyutBorong();
            this.borongTimer = setInterval(() => this.denyutBorong(), 3000);
        },
        hentikanPantauBorong() {
            if (this.borongTimer) clearInterval(this.borongTimer);
            this.borongTimer = null;
        },
        async denyutBorong() {
            const b = this.borong;
            if (!b) return this.hentikanPantauBorong();

            try {
                const res = await axios.get('/api/v1/karir/lamaran/putus-massal/progres', {
                    ...CFG,
                    params: { gelombang: b.gelombang, ids: b.ids.join(',') },
                });
                const r = res.data?.result || {};

                this.borong = { ...b, ...r, jalan: (r.menunggu ?? 0) > 0 };

                if (!this.borong.jalan) {
                    this.hentikanPantauBorong();
                    // Papan baru disegarkan SETELAH gelombangnya tuntas: menyegarkan
                    // tiap denyut berarti kartu berpindah kolom di bawah kursor admin
                    // yang sedang membaca panelnya.
                    this.muatDetail(this.selectedId, { senyap: true });
                    this.muatProgram();
                }
            } catch (e) {
                // Satu denyut gagal bukan alasan menghentikan pemantauan —
                // pekerjaannya berjalan di antrean, bukan di sini.
            }
        },
        /**
         * Ulangi yang gagal — SELURUHNYA, atau satu orang saja.
         *
         * Kirimannya tidak ikut dari sini: server mengambilnya kembali dari
         * payload job yang gagal, jadi alasan yang sudah diketik admin tidak
         * perlu diketik ulang.
         */
        async ulangiPutusGagal(grup = null, orang = null) {
            const b = this.borong;
            if (!b || b.jalan) return;

            const gagal = orang
                ? [orang.id]
                : (b.baris || []).filter((x) => x.keadaan === 'gagal').map((x) => x.tahapId);
            if (!gagal.length) return;

            try {
                const res = await axios.post(
                    '/api/v1/karir/lamaran/putus-massal/ulang',
                    { gelombang: b.gelombang, tahapId: gagal },
                    CFG,
                );
                this.notice(res.data?.message || 'Diantrekan ulang.');
                this.pantauGelombang(b.gelombang, b.ids);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengantrekan ulang.', true);
            }
        },
        tutupPanelBorong() {
            this.hentikanPantauBorong();
            this.borong = null;
            try { localStorage.removeItem('plwBorong'); } catch (e) { /* diabaikan */ }
        },
        barisHmKurang(b) {
            if (!b?.alasan) return 'Alasan belum dipilih';
            // Lihat barisPmKurang(): editor kosong tetap menyisakan markup.
            if (this.alasanButuhCatatan(b.alasan) && !teksDariHtml(b.catatanHtml).trim()) {
                return 'Keterangan wajib';
            }

            return '';
        },
        async askHoldMassal(menahan) {
            const bisa = menahan ? this.bisaTahanMassal : this.bisaLepasMassal;
            if (!bisa.length) return;

            this.hmNilai = menahan;
            this.hmTarget = bisa;
            // Yang tercentang tapi tak bisa diproses — disebut di modal supaya
            // admin tahu sebelum menyimpan, bukan sesudah.
            this.hmDilewati = this.barisTerpilih.filter((r) => !bisa.includes(r));
            this.hmPola = 'SERAGAM';
            this.hmAlasan = '';
            this.hmCatatanHtml = '';
            this.hmBaris = bisa.map((r) => ({
                id: r.id,
                tahapId: r.tahapId,
                pelamar: r.pelamar,
                posisi: r.posisi,
                tahap: r.tahap,
                alasan: '',
                catatanHtml: '',
            }));
            if (menahan) await this.muatAlasanHold();
            this.hmShow = true;
        },
        /** Salin alasan & keterangan baris pertama ke seluruh baris. */
        sebarBarisPertama() {
            const a = this.hmBaris[0];
            if (!a?.alasan) return;
            this.hmBaris = this.hmBaris.map((b, i) => (i === 0 ? b : { ...b, alasan: a.alasan, catatanHtml: a.catatanHtml }));
            this.notice(`Alasan "${this.alasanHold.find((x) => x.value === a.alasan)?.nama || a.alasan}" disalin ke ${this.hmBaris.length - 1} baris lain.`);
        },
        async konfirmHoldMassal() {
            if (this.sibuk || !this.hmTarget.length) return;
            this.sibuk = true;
            try {
                // SATU BENTUK KIRIMAN untuk kedua mode. Mode seragam hanya
                // mengisi alasan yang sama ke tiap item di sini — server tidak
                // perlu tahu mode mana yang dipakai, jadi tidak ada aturan
                // kedua yang harus dijaga tetap sama dengan yang pertama.
                const item = this.hmTarget.map((r) => {
                    const b = this.hmBaris.find((x) => x.tahapId === r.tahapId);
                    if (!this.hmNilai) {
                        return { tahapId: r.tahapId, catatanHtml: this.hmCatatanHtml || null };
                    }
                    if (this.hmPola === 'SERAGAM') {
                        return {
                            tahapId: r.tahapId,
                            alasanKode: this.hmAlasan,
                            catatan: teksDariHtml(this.hmCatatanHtml) || null,
                            catatanHtml: this.hmCatatanHtml || null,
                        };
                    }

                    return {
                        tahapId: r.tahapId,
                        alasanKode: b?.alasan || null,
                        catatan: teksDariHtml(b?.catatanHtml) || null,
                        catatanHtml: b?.catatanHtml || null,
                    };
                });

                const res = await axios.patch('/api/v1/karir/lamaran/tahap/hold-massal', {
                    hold: this.hmNilai,
                    item,
                }, CFG);

                const gagal = res.data?.result?.gagal || [];
                this.notice(res.data?.message || 'Selesai.', gagal.length > 0);
                this.hmShow = false;
                this.terpilih = [];
                this.tutupPilihKolom();
                this.muatDetail(this.selectedId, { senyap: true });
                this.muatProgram();
            } catch (e) {
                // Kegagalan menyeluruh membawa daftar siapa & kenapa — jauh
                // lebih berguna daripada satu kalimat untuk dua puluh orang.
                const gagal = e.response?.data?.result?.gagal || [];
                const rinci = gagal.length
                    ? ` (${gagal.slice(0, 3).map((g) => g.nama || '?').join(', ')}${gagal.length > 3 ? ', …' : ''})`
                    : '';
                this.notice((e.response?.data?.message || 'Gagal memproses penahanan.') + rinci, true);
            } finally {
                this.sibuk = false;
            }
        },
        async konfirmHold() {
            const target = this.holdTarget || this.detailKandidat;
            if (this.sibuk || !target?.tahapId) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/tahap/${target.tahapId}/hold`, {
                    hold: this.holdNilai,
                    alasanKode: this.holdNilai ? this.holdAlasan : null,
                    catatan: teksDariHtml(this.holdCatatanHtml) || null,
                    catatanHtml: this.holdCatatanHtml || null,
                }, CFG);
                this.notice(res.data?.message || 'Tersimpan.');
                this.holdShow = false;
                this.holdTarget = null;
                // Drawer ditutup: melepas tahan bisa membuat tahap langsung
                // menyimpulkan sendiri, sehingga isi drawer yang lama sudah
                // tidak menggambarkan keadaan mana pun.
                this.detailKandidat = null;
                await this.segarkanKartu({ tahap: target.tahapId });
                this.muatProgram();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memproses penahanan.', true);
            } finally {
                this.sibuk = false;
            }
        },

        /* ── CETAK LAPORAN ─────────────────────────────────────────────────── */
        /**
         * Buka pemilih tahap untuk kirim ulang email.
         *
         * Daftarnya diambil SAAT DIBUKA, bukan ikut kartu kandidat. Riwayat
         * keputusan hanya dilihat sesekali, sementara kartu pelamar dikirim
         * ratusan sekaligus untuk satu papan — menempelkannya di sana berarti
         * seluruh papan membawa data yang hampir tak pernah dibaca.
         */
        async bukaEmailUlang() {
            if (!this.detailKandidat) return;
            this.emailShow = true;
            this.emailMuat = true;
            this.emailTahap = [];
            this.emailPilih = null;
            this.emailTujuan = null;
            try {
                const res = await axios.get(`/api/v1/karir/lamaran/${this.detailKandidat.id}/email-hasil`, CFG);
                this.emailTahap = res.data.result?.tahap || [];
                this.emailTujuan = res.data.result?.email || null;
                // Yang terbaru dipilihkan di muka — itu yang paling sering
                // dimaksud, dan admin tinggal menggeser bila bukan itu.
                this.emailPilih = this.emailTahap.find((t) => t.kirimEmail)?.tahapId || null;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memuat riwayat keputusan.', true);
            } finally {
                this.emailMuat = false;
            }
        },
        async kirimUlangEmail() {
            if (!this.emailPilih || this.emailSibuk) return;
            this.emailSibuk = true;
            try {
                // ALAMAT IKUT DIKIRIM, TAPI BUKAN SEBAGAI TUJUAN.
                //
                // Yang dikirim adalah alamat yang DILIHAT admin: dari kartu kandidat
                // bila ada, kalau tidak dari yang dipampang modal ini. Server
                // menghitung sendiri tujuannya dari database lalu membandingkannya.
                // Kalau berselisih — drawer basi, data berubah di tab lain —
                // pengiriman DIBATALKAN, bukan diteruskan diam-diam ke salah satu
                // di antara keduanya.
                const res = await axios.post(
                    `/api/v1/karir/lamaran/tahap/${this.emailPilih}/email-hasil`,
                    { email: this.detailKandidat?.email || this.emailTujuan },
                    CFG,
                );
                const tahap = this.emailTahap.find((t) => t.tahapId === this.emailPilih);
                this.emailTerkirim = {
                    pelamar: this.detailKandidat?.pelamar || '',
                    tahap: tahap?.label || 'terpilih',
                    email: this.emailTujuan || this.detailKandidat?.email || 'alamat kandidat',
                };
                this.emailShow = false;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengantrekan email.', true);
            } finally {
                this.emailSibuk = false;
            }
        },
        /**
         * Buka Export Studio untuk kandidat yang drawer-nya sedang terbuka.
         *
         * Identitas kandidat DISALIN ke state sendiri, tidak dibaca langsung
         * dari `detailKandidat`: drawer bisa ditutup selagi studio terbuka,
         * dan judul modal yang tiba-tiba kosong membuat admin ragu berkas
         * siapa yang sedang ia rakit.
         */
        bukaStudio() {
            if (!this.detailKandidat?.id) return;

            this.studioLamaran = this.detailKandidat.id;
            this.studioNama = this.detailKandidat.pelamar || '';
            this.studioKode = this.detailKandidat.lamaranKode || '';
            this.studioShow = true;
        },

        /**
         * Berkas seleksi sudah masuk antrean — pantau memakai alur yang sama
         * dengan cetak laporan, termasuk panel proses dan unduhan otomatisnya.
         */
        studioDiantre(id) {
            const nama = `${this.studioNama || 'Kandidat'} — ${this.studioKode || ''}`.trim();
            const unduh = this.mulaiUnduhan(nama, 'PDF');

            // JENDELA KANDIDAT IKUT DITUTUP.
            //
            // Export Studio menutup dirinya sendiri, tapi jendela detail di
            // belakangnya tetap terbuka — dan begitu Studio hilang, yang tersisa
            // di layar adalah profil kandidat yang menutupi panel proses di
            // pojok. Admin yang baru menekan "Unduh" lalu tidak melihat satu pun
            // tanda bahwa permintaannya diterima, dan menekannya lagi.
            //
            // Menekan cetak berarti pekerjaan meninjau orang ini sudah selesai;
            // yang ingin dilihat berikutnya adalah kemajuan berkasnya, bukan
            // profil yang barusan dibaca.
            this.tutupKandidat();

            if (!id) {
                this.gagalkanUnduhan(unduh, 'Permintaan cetak tidak menghasilkan nomor antrean.');

                return;
            }

            this.pantauLaporan(unduh, id);
        },

        async askLaporan() {
            if (!this.detailKandidat?.id) return;
            this.laporanFormat = 'PDF';
            this.laporanGagal = '';
            this.laporanOpsi = [];
            this.laporanFormulir = [];
            this.laporanMuat = true;
            this.laporanShow = true;

            try {
                const res = await axios.get(`/api/v1/karir/lamaran/${this.detailKandidat.id}/laporan/opsi`, CFG);
                this.laporanOpsi = res.data?.result?.formulir || [];
                // Bawaan: yang TERBARU. Itulah yang hampir selalu dimaksud
                // "cetak datanya" — jawaban lama sudah digantikan kandidat sendiri.
                this.laporanFormulir = this.laporanOpsi.filter((f) => f.utama).map((f) => f.id);
            } catch (e) {
                this.laporanGagal = e.response?.data?.message || 'Gagal memuat daftar formulir.';
            } finally {
                // `finally`, bukan di ujung `try`: bila permintaannya gagal,
                // penanda yang tak pernah dimatikan membuat tombolnya terkunci
                // selamanya dan modalnya hanya bisa ditutup.
                this.laporanMuat = false;
            }
        },
        /**
         * Minta laporan → modal MENUTUP → kemajuannya pindah ke pojok.
         *
         * Modal tidak lagi menunggui pekerjaannya. Membuat laporan berjalan di
         * antrean dan bisa memakan puluhan detik; menahan admin di dalam jendela
         * selama itu membuat seluruh halaman tak bisa dipakai untuk hal lain.
         */
        async konfirmLaporan() {
            if (!this.detailKandidat?.id) return;
            this.laporanGagal = '';

            const format = this.laporanFormat;
            const nama = `${this.detailKandidat.pelamar || 'Kandidat'} — ${this.detailKandidat.lamaranKode || ''}`.trim();
            const unduh = this.mulaiUnduhan(nama, format);

            this.laporanShow = false;

            try {
                const res = await axios.post(`/api/v1/karir/lamaran/${this.detailKandidat.id}/laporan`, {
                    format,
                    formulir: this.laporanFormulir,
                }, CFG);
                this.pantauLaporan(unduh, res.data?.result?.id);
            } catch (e) {
                this.gagalkanUnduhan(unduh, e.response?.data?.message || 'Gagal meminta laporan.');
            }
        },

        /** Baris baru di panel unduhan. Mengembalikan objeknya, bukan indeksnya:
         *  indeks bergeser begitu ada baris lain yang ditutup. */
        mulaiUnduhan(nama, format) {
            const u = {
                id: `u${Date.now()}${this.unduhan.length}`,
                nama,
                format,
                // DUA ANGKA, BUKAN SATU.
                //
                // `target` adalah kebenaran yang datang dari server; `persen`
                // adalah yang dilihat mata dan selalu MENGEJAR target, tidak
                // pernah melompat ke sana. Bilah yang melompat 0 → 100 saat
                // jawaban tiba terbaca seperti kerusakan, dan bilah yang diam
                // lama lalu melompat justru membuat orang menekan tombolnya
                // lagi. Yang meyakinkan adalah gerak yang tak pernah berhenti.
                persen: 0,
                target: 0,
                keadaan: 'siap',       // siap → unduh → selesai | gagal
                pesan: 'Menyiapkan berkas…',
                // Berkas sudah benar-benar tersimpan; tinggal menunggu cincinnya
                // sampai di 100 supaya perpindahan ke "Selesai" tidak mendahului
                // animasinya.
                tuntas: false,
                timer: null,
            };
            this.unduhan.push(u);
            this.jalankanAnimasi();

            return u;
        },

        gagalkanUnduhan(u, pesan) {
            clearTimeout(u.timer);
            u.keadaan = 'gagal';
            u.pesan = pesan;
            u.persen = 0;
            u.target = 0;
        },

        /**
         * Satu gelung animasi untuk SELURUH baris — bukan satu per baris.
         *
         * Tiap bingkai, angka yang tampil mendekat ke targetnya sebesar
         * sebagian dari selisihnya: cepat saat jauh, melambat saat mendekat.
         * Itu sebabnya lompatan 90 → 100 terbaca sebagai "mengejar", bukan
         * sebagai kedipan.
         *
         * Gelungnya berhenti sendiri begitu tak ada lagi yang perlu digerakkan
         * — rAF yang berjalan selamanya membuat tab ini terus membangunkan CPU
         * meski tak ada unduhan sama sekali.
         */
        jalankanAnimasi() {
            if (this.unduhanRaf) return;

            const langkah = () => {
                let hidup = false;

                for (const u of this.unduhan) {
                    const selisih = u.target - u.persen;
                    if (selisih > 0.05) {
                        // Minimal 0,25 supaya sisa terakhir tidak merayap
                        // selamanya karena selisihnya mengecil terus.
                        u.persen = Math.min(u.target, u.persen + Math.max(0.25, selisih * 0.11));
                        hidup = true;
                    } else if (selisih > 0) {
                        u.persen = u.target;
                    }

                    // Label berganti SETELAH cincinnya penuh — bukan sebelum.
                    if (u.tuntas && u.keadaan !== 'selesai' && u.persen >= 99.5) {
                        u.keadaan = 'selesai';
                        u.pesan = u.pesanSelesai || 'Tersimpan';
                    }
                    if (u.tuntas && u.keadaan !== 'selesai') hidup = true;
                }

                this.unduhanRaf = hidup ? requestAnimationFrame(langkah) : null;
            };

            this.unduhanRaf = requestAnimationFrame(langkah);
        },

        /** Angka bulat untuk ditampilkan — nilainya sendiri disimpan pecahan. */
        bulat(n) { return Math.min(100, Math.round(n || 0)); },

        /**
         * Panjang busur cincin. Memakai nilai PECAHAN, bukan yang sudah
         * dibulatkan: pembulatan membuat lingkarannya bergerak melangkah satu
         * persen sekali — dan gerak melangkah persis yang ingin dihindari.
         */
        busur(n) {
            const KELILING = 97.4;   // 2πr, r = 15.5

            return `${(Math.min(100, Math.max(0, n || 0)) / 100) * KELILING} ${KELILING}`;
        },

        /** Tutup panel — hanya membuang tampilannya, berkas yang sudah turun tetap ada. */
        bersihkanUnduhan() {
            this.unduhan.forEach((u) => clearTimeout(u.timer));
            this.unduhan = [];
            if (this.unduhanRaf) {
                cancelAnimationFrame(this.unduhanRaf);
                this.unduhanRaf = null;
            }
        },

        /**
         * Tanya berkala sampai laporannya jadi, lalu unduh sendiri.
         *
         * Berhenti setelah ~2 menit: antrean yang mati membuat status DIPROSES
         * bertahan selamanya, dan lingkaran berputar tanpa akhir lebih
         * membingungkan daripada pesan gagal yang jujur.
         *
         * KEMAJUAN TAHAP PENYIAPAN DISIMULASIKAN sampai 90%, dan itu disengaja:
         * server tidak tahu berapa persen sebuah PDF "sudah jadi", dan mengarang
         * angka yang melompat ke 100 lalu diam justru lebih menyesatkan daripada
         * bilah yang merambat pelan. Sisa 10% diisi unduhan yang persentasenya
         * BENAR — dihitung dari byte yang sudah turun.
         */
        pantauLaporan(u, id) {
            if (!id) {
                this.gagalkanUnduhan(u, 'Permintaan tidak dikenali.');

                return;
            }

            u.keadaan = 'siap';
            u.pesan = 'Menyiapkan berkas…';

            // ── ANGGARAN WAKTU: 4 MENIT ────────────────────────────────────
            //
            // Diukur dari riwayat nyata di N_WEB_CAREERS_Export_Log: laporan
            // memakan 17–97 detik, dan yang terlama terjadi saat worker baru
            // bangun. Batas sebelumnya 80 detik — lebih pendek daripada
            // pekerjaan yang memang normal, sehingga panelnya menyerah pada
            // berkas yang sebentar lagi jadi dan admin mengulang permintaan
            // yang sebenarnya masih hidup.
            //
            // Jarak tanyanya melebar seiring waktu: cepat di awal supaya yang
            // ringan terasa seketika, melambat kemudian supaya menunggu tiga
            // menit tidak berarti 150 permintaan.
            const MULAI = Date.now();
            const BATAS = 4 * 60 * 1000;
            const jeda = (lewat) => (lewat < 15000 ? 1200 : lewat < 45000 ? 2500 : 5000);

            const tanya = async () => {
                const lewat = Date.now() - MULAI;

                // Yang dinaikkan TARGET-nya; yang tampil mengejarnya sendiri.
                // Merambat melambat mendekati 90 — memberi kesan bergerak tanpa
                // pernah berjanji hampir selesai. Angka 90 disengaja: sisanya
                // milik unduhan yang persentasenya benar-benar terukur, jadi
                // bilah ini tak pernah sampai penuh atas dasar tebakan.
                u.target = Math.min(90, u.target + Math.max(1.2, (90 - u.target) / 9));
                this.jalankanAnimasi();

                if (lewat > BATAS) {
                    this.gagalkanUnduhan(u, 'Belum selesai setelah 4 menit — periksa worker antrean, lalu coba lagi.');

                    return;
                }

                try {
                    const { data } = await axios.get(`/api/v1/karir/lamaran/laporan/${id}`, CFG);
                    const r = data?.result || {};

                    if (r.selesai) {
                        await this.tarikBerkas(u, id);

                        return;
                    }
                    if (r.gagal) {
                        this.gagalkanUnduhan(u, r.pesan || 'Laporan gagal dibuat.');

                        return;
                    }
                    // Menunggu lama bukan kerusakan — tapi diam tanpa kabar
                    // membuatnya terasa begitu. Kalimatnya berubah supaya
                    // terlihat masih hidup.
                    if (lewat > 30000) {
                        u.pesan = `Masih diproses… ${Math.round(lewat / 1000)} detik`;
                    }
                } catch (e) {
                    this.gagalkanUnduhan(u, 'Gagal memeriksa status laporan.');

                    return;
                }

                u.timer = setTimeout(tanya, jeda(lewat));
            };

            clearTimeout(u.timer);
            u.timer = setTimeout(tanya, 900);
        },

        /**
         * Tarik berkasnya sebagai blob lalu SIMPAN SENDIRI.
         *
         * Bukan membuka tautan di tab baru: tab yang terbuka lalu menutup
         * sendiri terlihat seperti kedipan tak jelas, dan pemblokir pop-up
         * kerap menahannya tanpa memberi tahu siapa pun. Dengan blob,
         * persentasenya nyata dan berkasnya benar-benar tersimpan.
         */
        async tarikBerkas(u, id) {
            u.keadaan = 'unduh';
            u.target = Math.max(u.target, 90);
            u.pesan = 'Mengunduh…';
            this.jalankanAnimasi();

            try {
                const res = await axios.get(`/api/v1/karir/lamaran/laporan/${id}/unduh`, {
                    ...CFG,
                    responseType: 'blob',
                    onDownloadProgress: (e) => {
                        if (!e.total) return;
                        // 90–100%: penyiapan sudah memakai 0–90.
                        u.target = 90 + (e.loaded / e.total) * 10;
                        this.jalankanAnimasi();
                    },
                });

                const nama = this.namaBerkas(res, u);
                const url = URL.createObjectURL(res.data);
                const a = document.createElement('a');
                a.href = url;
                a.download = nama;
                document.body.appendChild(a);
                a.click();
                a.remove();
                // Dilepas setelah peramban sempat memulai unduhan; mencabutnya
                // seketika membatalkan berkas yang baru saja diklik.
                setTimeout(() => URL.revokeObjectURL(url), 60000);

                // Berkasnya SUDAH tersimpan. Yang ditunda hanya labelnya —
                // sampai cincinnya benar-benar penuh, supaya "Selesai" tidak
                // muncul di atas lingkaran yang masih separuh.
                u.target = 100;
                u.tuntas = true;
                u.pesanSelesai = `Tersimpan · ${nama}`;
                this.jalankanAnimasi();
            } catch (e) {
                this.gagalkanUnduhan(u, 'Berkas gagal diunduh.');
            }
        },

        /** Nama berkas dari header server; kalau tak ada, disusun sendiri. */
        namaBerkas(res, u) {
            const cd = res.headers?.['content-disposition'] || '';
            const m = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(cd);
            if (m) return decodeURIComponent(m[1]);

            const aman = (u.nama || 'laporan').replace(/[^\w\s.-]+/g, '').trim().replace(/\s+/g, '-');

            return `${aman}.${u.format === 'XLSX' ? 'xlsx' : 'pdf'}`;
        },

        /* ── PENYARING & PILIH BANYAK ──────────────────────────────────────── */
        bersihkanFilter() {
            this.posisiPilih = [];
            this.keadaanPilih = [];
            this.cariKandidat = '';
            this.tahapPilih = [];
            this.kampusPilih = [];
            this.rangeTanggal = null;
            this.listPage = 1;
        },
        /**
         * Copot SATU penyaring dari chip-nya. Kuncinya sama dengan chipFilter.
         *
         * Penyaring multi-pilih berkunci `jenis:nilai` — yang dicabut satu
         * nilai, bukan seluruh penyaringnya. Menyaring "Unsri, Unila, UI" lalu
         * ingin melepas Unila saja tidak boleh berarti kehilangan dua lainnya.
         */
        copotChip(key) {
            const [jenis, ...sisa] = String(key).split(':');
            const nilai = sisa.join(':');
            const larik = {
                tahap: 'tahapPilih', kampus: 'kampusPilih',
                posisi: 'posisiPilih', keadaan: 'keadaanPilih',
            }[jenis];

            if (larik) {
                // Lowongan bernilai angka; sisanya teks. Dicocokkan longgar
                // karena kuncinya selalu lahir sebagai teks dari template.
                this[larik] = this[larik].filter((v) => String(v) !== nilai);
            } else if (jenis === 'cari') {
                this.cariKandidat = '';
            } else if (jenis === 'tanggal') {
                this.rangeTanggal = null;
            }
            this.listPage = 1;
        },
        /**
         * Kunci kolom tempat SATU kandidat berdiri.
         *
         * Cerminan aturan penempatan di kartuKolom(): kode tahap lebih dulu,
         * nomor urut hanya sebagai cadangan untuk muatan lama. Ditulis sekali di
         * sini supaya penyaring "Tahap" dan papan kanban mustahil berselisih
         * soal kandidat ini ada di kolom mana.
         */
        kunciBaris(r) {
            if (r.kolomKode != null) {
                const k = this.petaKolom.kode.get(r.kolomKode);
                if (k) return k;
            }

            return this.petaKolom.urutan.get(r.kolomUrutan) || '';
        },
        /** Jumlah kandidat pada satu kolom — dipakai label pilihan "Tahap". */
        jumlahTahap(col) {
            const k = this.kunciKolom(col);

            return (this.detail.pelamar || []).filter((r) => this.kunciBaris(r) === k).length;
        },
        /**
         * Waktu melamar berada di dalam rentang yang dipilih?
         *
         * Batas akhirnya mencakup seluruh hari terakhir. Membandingkan langsung
         * dengan tengah malam membuat orang yang melamar pukul 09.00 pada tanggal
         * penutup jatuh DI LUAR rentang yang justru dipilih untuk memuatnya —
         * kesalahan yang tak terlihat sampai seseorang menghitung ulang manual.
         */
        dalamRentang(waktu) {
            if (!this.rentangAktif) return true;
            const t = new Date(String(waktu || '').replace(' ', 'T')).getTime();
            if (!t) return false;
            const [a, b] = this.rangeTanggal;

            return t >= new Date(`${a}T00:00:00`).getTime() && t <= new Date(`${b}T23:59:59.999`).getTime();
        },
        /** '2026-08-11' → '11 Agu 2026'. Dipakai label chip rentang. */
        tglSingkat(iso) {
            const d = new Date(`${iso}T00:00:00`);

            return Number.isNaN(d.getTime())
                ? iso
                : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        },
        /**
         * Rentang "sekian hari terakhir sampai hari ini", sebagai ['YYYY-MM-DD', …].
         *
         * Disusun dari komponen tanggal SETEMPAT, bukan lewat toISOString():
         * yang terakhir mengubah ke UTC lebih dulu, dan di WIB tanggal sebelum
         * pukul 07.00 akan mundur satu hari — pintasan "7 hari" jadi menyaring
         * jendela yang meleset sehari dari yang tertulis di tombolnya.
         */
        rentangHari(hari) {
            const pad = (n) => String(n).padStart(2, '0');
            const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            const akhir = new Date();
            const awal = new Date();
            // hari - 1: "7 hari" berarti hari ini plus enam hari ke belakang,
            // bukan delapan tanggal.
            awal.setDate(awal.getDate() - (hari - 1));

            return [iso(awal), iso(akhir)];
        },
        /** Tekan pintasan yang sedang menyala = melepasnya, bukan memasangnya lagi. */
        pakaiPintasTanggal(hari) {
            this.rangeTanggal = this.pintasAktif === hari ? null : this.rentangHari(hari);
        },
        /** Centang / batal satu baris di mode list. */
        toggleBarisList(r) {
            const i = this.terpilih.indexOf(r.id);
            if (i >= 0) this.terpilih.splice(i, 1);
            else this.terpilih.push(r.id);
        },
        /**
         * Centang kepala: sapu HALAMAN INI saja.
         *
         * Pilihan di halaman lain sengaja tidak disentuh — memilih dua puluh
         * orang di halaman 1 lalu pindah ke halaman 2 untuk menambah lima lagi
         * adalah cara orang benar-benar bekerja, dan mengosongkannya diam-diam
         * berarti dua puluh keputusan hilang tanpa satu pun tanda.
         */
        toggleHalaman() {
            const idHal = this.barisList.map((r) => r.id);
            if (this.halamanTercentangPenuh) {
                this.terpilih = this.terpilih.filter((id) => !idHal.includes(id));

                return;
            }
            const set = new Set(this.terpilih);
            idHal.forEach((id) => set.add(id));
            this.terpilih = [...set];
        },
        /** Satu baris cocok dengan SATU kode keadaan. Lihat penyaring keadaan. */
        cocokKeadaan(r, kode) {
            switch (kode) {
                case 'PERLU': return !!r.butuhKeputusan && !r.hold;
                case 'NUNGGU': return !!r.nungguSistem;
                case 'HOLD': return !!r.hold;
                // Punya aktivitas yang menuntut jadwal tapi belum ada jadwalnya
                // — inilah antrean kerja penjadwalan massal.
                case 'JADWAL': return (r.tests || []).some((t) => t.butuhJadwal && !t.jadwal);
                // Sudah punya jadwal yang belum lewat — antrean "siapa yang
                // harus ditandai hadir hari ini".
                case 'TERJADWAL': return (r.tests || []).some((t) => !!t.jadwal && !t.selesai);
                default: return this.cocokKonfirmasi(r, kode) ?? true;
            }
        },
        /**
         * Keadaan KONFIRMASI KEHADIRAN — dibaca dari status EFEKTIF yang dikirim
         * server (MENUNGGU yang lewat batas = TANPA_JAWABAN). Null = bukan kode
         * konfirmasi.
         */
        cocokKonfirmasi(r, kode) {
            const status = {
                KONF_MENUNGGU: 'MENUNGGU',
                KONF_LAIN: 'JADWAL_LAIN',
                KONF_MUNDUR: 'MUNDUR',
                KONF_LEWAT: 'TANPA_JAWABAN',
                KONF_HADIR: 'AKAN_HADIR',
                KONF_DITUNDA: 'DITUNDA',
            }[kode];
            if (!status) return null;

            return (r.tests || []).some((t) => !t.selesai && t.konfirmasi?.status === status);
        },
        /** Kunci stabil sebuah kolom — dipakai menandai kolom mana yang aktif. */
        kunciKolom(col) { return col.kode || col.label || String(col.urutan || ''); },
        togglePilihKolom(col) {
            const k = this.kunciKolom(col);
            // Berpindah kolom MENGOSONGKAN pilihan: membawa serta pilihan dari
            // kolom sebelumnya berarti menjadwalkan orang yang tidak sedang
            // dilihat, dan itu persis kesalahan yang paling mahal di sini.
            this.terpilih = [];
            this.kolomPilih = this.kolomPilih === k ? '' : k;
        },
        tutupPilihKolom() {
            this.kolomPilih = '';
            this.terpilih = [];
        },
        /** Kartu diklik: memilih bila kolomnya sedang memilih, kalau tidak membuka detail. */
        klikKartu(col, r) {
            if (this.kolomPilih === this.kunciKolom(col)) {
                const i = this.terpilih.indexOf(r.id);
                if (i >= 0) this.terpilih.splice(i, 1);
                else this.terpilih.push(r.id);

                return;
            }
            // Antreannya adalah KOLOM INI, bukan seluruh papan: rekruter yang
            // membuka kartu di "Screening CV" sedang mengerjakan tumpukan itu,
            // dan tombol berikutnya harus tetap di dalamnya.
            this.setAntrean(this.kartuKolom(col), col.label || col.nama || '');
            this.bukaKandidat(r);
        },
        kolomTerpilihPenuh(col) {
            const ids = this.kartuKolom(col).map((r) => r.id);

            return ids.length > 0 && ids.every((id) => this.terpilih.includes(id));
        },
        /** Pilih / batalkan seluruh kartu di satu kolom sekaligus. */
        pilihKolom(col) {
            const ids = this.kartuKolom(col).map((r) => r.id);
            if (this.kolomTerpilihPenuh(col)) {
                this.terpilih = this.terpilih.filter((id) => !ids.includes(id));

                return;
            }
            this.terpilih = [...new Set([...this.terpilih, ...ids])];
        },

        /* ── BATAS PENGISIAN FORMULIR ────────────────────────────────────────
           Aturannya seluruhnya di server (App\Support\Career\BatasIsi); layar
           hanya membaca `batas` tiap kartu dan `batasProgram` tiap kolom. */
        /** Satu kolom: belum mengirim, lewat batas, dan terkunci menunggu jadwal. */
        ringkasBatas(col) {
            const baris = (this.detail?.pelamar || []).filter(
                (r) => r.kolomKode === col.kode && r.batas && !r.batas.terkirim,
            );

            return {
                belum: baris.length,
                lewat: baris.filter((r) => r.batas.lewat).length,
                tutup: baris.filter((r) => r.batas.belumDiatur).length,
            };
        },
        /** Kolom papan milik satu kartu / baris kandidat. */
        kolomDari(r) {
            return r ? (this.detail?.kolom || []).find((k) => k.kode === r.kolomKode) || null : null;
        },
        /**
         * Master Alur sudah mematikan jadwal tahap kolom ini, tapi kolomnya masih
         * perlu diurus (ada jadwal lama / kandidat yang terikat) → tawarkan lepas.
         */
        kolomDilepas(col) {
            return !!col && !!col.bolehBatas && !['MANUAL', 'OTOMATIS'].includes(col.batasMode);
        },
        kelasKolomBatas(col) {
            const r = this.ringkasBatas(col);
            if (this.kolomDilepas(col)) return r.belum ? 'is-perlu' : 'is-netral';
            if (col.batasProgram) return { 'is-lewat': r.lewat > 0 };

            return r.tutup ? 'is-perlu' : 'is-netral';
        },
        ikonKolomBatas(col) {
            if (this.kolomDilepas(col)) return 'bi-unlock';
            if (col.batasProgram) return 'bi-hourglass-split';
            if (this.ringkasBatas(col).tutup) return 'bi-lock-fill';

            return col.batasMode === 'OTOMATIS' ? 'bi-stopwatch' : 'bi-calendar-plus';
        },
        labelBatas(b) {
            if (b?.belumDiatur) return 'Belum dijadwalkan';
            if (b?.belumBuka) return `Buka ${this.tglJamId(b.buka)}`;
            const jam = Math.floor(Math.abs(b?.sisaDetik || 0) / 3600);
            if (b?.lewat) return jam < 24 ? `Lewat ${Math.max(1, jam)} jam` : `Lewat ${Math.floor(jam / 24)} hari`;
            if (jam < 1) return '< 1 jam lagi';

            return jam < 24 ? `${jam} jam lagi` : `${Math.floor(jam / 24)} hari lagi`;
        },
        kelasBatas(b) {
            if (!b) return 'is-kosong';
            if (b.belumDiatur || b.belumBuka) return 'is-tutup';
            if (!b.batas) return 'is-kosong';
            if (b.terkunci) return 'is-kunci';
            if (b.lewat) return 'is-lewat';

            return b.sisaDetik <= 86400 ? 'is-dekat' : 'is-aman';
        },
        labelSumberBatas(sumber) {
            return { PROGRAM: 'Jadwal kolom program', PRIBADI: 'Jadwal pribadi', ATURAN: 'Aturan otomatis tahap' }[sumber] || 'Jadwal';
        },
        keteranganBatas(b) {
            if (b.belumDiatur) {
                return 'Kandidat belum bisa mengisi formulir sampai jadwal kolomnya diatur (ikon jam pasir di kepala kolom), atau beri jadwal pribadi lewat Atur jadwal.';
            }
            if (!b.batas) return 'Tahap ini tanpa jadwal — formulir terbuka. Atur jadwal bila kandidat ini perlu batas khusus.';
            const sumber = this.labelSumberBatas(b.sumber);
            if (b.belumBuka) return `${sumber} · formulir baru dibuka ${b.bukaTeks} — sampai itu terkunci.`;
            if (b.terkunci) return `${sumber} · batas sudah lewat — formulir terkunci, kandidat tidak bisa mengirim.`;

            return `${sumber} · ${this.labelBatas(b)}.`;
        },
        /** "YYYY-MM-DD HH:mm:ss" waktu setempat — format pemilih tanggal. */
        waktuKini() {
            const d = new Date();
            const dua = (n) => String(n).padStart(2, '0');

            return `${d.getFullYear()}-${dua(d.getMonth() + 1)}-${dua(d.getDate())} ${dua(d.getHours())}:${dua(d.getMinutes())}:00`;
        },
        /** Jadwal baru: batas akhir sesudah waktu dibuka dan belum lewat. null = sah. */
        salahJadwalBaru(buka, batas) {
            const akhir = keDate(batas);
            if (!akhir) return null;
            if (akhir <= new Date()) return 'Batas akhir itu sudah lewat — pilih waktu yang akan datang.';
            const awal = keDate(buka);
            if (awal && akhir <= awal) return 'Batas akhir harus sesudah waktu formulir dibuka.';

            return null;
        },
        perpBaru() {
            this.perp = { cara: 'HARI', hari: 2, jam: 6, sampai: '' };
        },
        askBatasKolom(col) {
            this.batasKolomCol = col;
            this.batasKolomLangkah = this.kolomDilepas(col) ? 'LEPAS' : (col.batasProgram ? 'PERPANJANG' : 'ATUR');
            // Berjadwal → isian awal = jadwal sekarang (dipakai tab Edit superadmin).
            this.batasKolomBuka = col.batasProgram ? String(col.bukaProgram || '').slice(0, 19) : this.waktuKini();
            this.batasKolomTgl = col.batasProgram ? String(col.batasProgram).slice(0, 19) : '';
            this.batasKolomAlasan = '';
            this.riwayatKolom = { buka: false, muat: false, baris: [] };
            this.perpBaru();
            this.batasKolomShow = true;
        },
        async simpanBatasKolom() {
            const col = this.batasKolomCol;
            if (!this.bolehSimpanBatasKolom || !this.detail?.program?.id) return;
            const dasar = { programId: this.detail.program.id, kodeTahap: col.kode };
            const [alamat, isi] = {
                LEPAS: ['/api/v1/karir/batas-isi/program/lepas', dasar],
                PERPANJANG: ['/api/v1/karir/batas-isi/program/perpanjang', { ...dasar, ...this.isiPerpanjang() }],
                UBAH: ['/api/v1/karir/batas-isi/program/ubah', { ...dasar, buka: this.batasKolomBuka, batas: this.batasKolomTgl, alasan: this.batasKolomAlasan }],
                ATUR: ['/api/v1/karir/batas-isi/program', { ...dasar, buka: this.batasKolomBuka, batas: this.batasKolomTgl }],
            }[this.batasKolomLangkah];
            this.batasSibuk = true;
            try {
                const res = await axios.post(alamat, isi, CFG);
                this.notice(res.data.message || 'Jadwal disimpan.');
                this.batasKolomShow = false;
                await this.muatDetail(this.selectedId, { senyap: true });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan jadwal.', true);
            } finally {
                this.batasSibuk = false;
            }
        },
        /** Isian perpanjangan untuk server: cara + nilai (hari/jam) atau sampai. */
        isiPerpanjang() {
            const p = this.perp;

            return {
                cara: p.cara,
                nilai: p.cara === 'HARI' ? Number(p.hari) : (p.cara === 'JAM' ? Number(p.jam) : null),
                sampai: p.cara === 'SAMPAI' ? p.sampai : null,
            };
        },
        askBatasKandidat(baris) {
            this.batasKandTarget = (baris || []).filter((r) => r && r.tahapId && r.batas && !r.batas.terkirim);
            if (!this.batasKandTarget.length) return;
            // Yang belum berjadwal tidak bisa diperpanjang, yang sudah tidak bisa
            // diatur ulang — kelompok yang lebih besar dibuka lebih dulu. Bila
            // Master Alur tahapnya sudah "Tanpa jadwal", lepas yang didahulukan.
            this.batasKandLangkah = this.batasKandLepas.length
                ? 'LEPAS'
                : (this.batasKandBelum.length > this.batasKandSudah.length ? 'ATUR' : 'PERPANJANG');
            this.batasKandBuka = '';
            this.batasKandTgl = '';
            this.batasKandAlasan = '';
            this.perpBaru();
            this.batasKandShow = true;
        },
        async simpanBatasKandidat() {
            if (!this.bolehSimpanBatasKand) return;
            const langkah = this.batasKandLangkah;
            const sasaran = { ATUR: this.batasKandBelum, LEPAS: this.batasKandLepas, UBAH: this.batasKandUbah }[langkah] || this.batasKandSudah;
            const isi = {
                ATUR: { cara: 'ATUR', buka: this.batasKandBuka || null, sampai: this.batasKandTgl },
                LEPAS: { cara: 'LEPAS' },
                UBAH: { cara: 'UBAH', buka: this.batasKandBuka || null, sampai: this.batasKandTgl },
            }[langkah] || this.isiPerpanjang();
            this.batasSibuk = true;
            try {
                const res = await axios.post('/api/v1/karir/batas-isi/kandidat', {
                    tahapIds: sasaran.map((r) => r.tahapId),
                    ...isi,
                    alasan: this.batasKandAlasan || null,
                }, CFG);
                this.notice(res.data.message || 'Jadwal disimpan.');
                this.batasKandShow = false;
                this.riwayatBatas = { buka: false, muat: false, baris: [], tahapId: null };
                await this.muatDetail(this.selectedId, { senyap: true });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan jadwal.', true);
            } finally {
                this.batasSibuk = false;
            }
        },
        labelAksiKolom(a) {
            return { PASANG: 'Dipasang', PERPANJANG: 'Diperpanjang', UBAH: 'Diedit superadmin', LEPAS: 'Dilepas' }[a] || a;
        },
        async toggleRiwayatKolom() {
            if (this.riwayatKolom.buka) {
                this.riwayatKolom.buka = false;

                return;
            }
            const col = this.batasKolomCol;
            if (!col || !this.detail?.program?.id) return;
            this.riwayatKolom = { buka: true, muat: true, baris: [] };
            try {
                const res = await axios.get('/api/v1/karir/batas-isi/program/riwayat', {
                    params: { programId: this.detail.program.id, kodeTahap: col.kode },
                    ...CFG,
                });
                if (this.batasKolomCol === col) this.riwayatKolom.baris = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat riwayat jadwal kolom.', true);
            } finally {
                this.riwayatKolom.muat = false;
            }
        },
        async toggleRiwayatBatas() {
            const id = this.detailKandidat?.tahapId;
            if (!id) return;
            if (this.riwayatBatas.buka && this.riwayatBatas.tahapId === id) {
                this.riwayatBatas.buka = false;

                return;
            }
            this.riwayatBatas = { buka: true, muat: true, baris: [], tahapId: id };
            try {
                const res = await axios.get(`/api/v1/karir/batas-isi/riwayat/${id}`, CFG);
                if (this.riwayatBatas.tahapId === id) this.riwayatBatas.baris = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat riwayat jadwal.', true);
            } finally {
                if (this.riwayatBatas.tahapId === id) this.riwayatBatas.muat = false;
            }
        },
        /* ── JADWAL MASSAL ─────────────────────────────────────────────────── */
        /** Bentuk berkode `k` berlaku untuk aktivitas massal terpilih? */
        massalBoleh(k) {
            return this.modeMassalDipakai.some((m) => m.kode === k);
        },
        /**
         * Bentuk awal: bawaan tipe aktivitasnya (Master Tipe Tahap), lalu Daring
         * seperti sebelumnya, lalu yang pertama sah — sama dengan jendela satuan.
         */
        modeMassalAwal() {
            const sah = (k) => k && this.massalBoleh(k);

            return [this.aktivitasMassalDef?.modeJadwalBawaan, 'DARING', this.modeMassalDipakai[0]?.kode].find(sah) || '';
        },
        /** Rentang bawaan mode berrentang tanggal untuk jendela massal. */
        isiRentangMassal() {
            const m = this.massalModeDef;
            if (!m?.batasWaktu || (this.massalRentang || []).length === 2 || !m.batasHari) return;
            this.massalRentang = [tanggalDepan(0), tanggalDepan(m.batasHari)];
        },
        /**
         * Instruksi Master Alur untuk aktivitas massal terpilih — dari kandidat
         * pertama. Seluruh sasaran satu nama aktivitas dalam satu program, jadi
         * instruksinya sama; yang berbeda program memang tidak dijadwalkan
         * bersamaan di papan ini.
         */
        async muatInstruksiMassal() {
            const t = this.sasaranMassal[0]?.t;
            this.massalCatatanHtml = '';
            if (!t || !this.jadwalFitur?.instruksi) return;
            this.massalInstruksiMuat = true;
            try {
                const res = await axios.get(`/api/v1/karir/lamaran/sub-tes/${t.id}/jadwal-info`, CFG);
                if (this.sasaranMassal[0]?.t?.id !== t.id) return;
                this.massalCatatanHtml = res.data?.result?.instruksiAlur || '';
                this.massalTampilBiaya = res.data?.result?.tampilBiayaAlur !== false;
                this.massalKalimatBiaya = res.data?.result?.kalimatBiayaAlur || this.massalKalimatBiaya;
            } catch (e) {
                // Diam — jendelanya tetap bisa dipakai tanpa instruksi bawaan.
            } finally {
                this.massalInstruksiMuat = false;
            }
        },
        askJadwalMassal() {
            if (!this.aktivitasTerpilih.length) return;
            this.muatLokasi();
            // Aktivitas yang paling banyak dipunyai kandidat terpilih jadi
            // pilihan awal — itulah yang hampir selalu dimaksud.
            const utama = this.aktivitasTerpilih[0];
            this.massalAktivitas = utama.label;
            this.massalMode = this.modeMassalAwal();
            this.massalPola = 'BERGILIR';
            this.massalMulai = '';
            this.massalDurasi = 30;
            this.massalJeda = 0;
            this.massalLink = '';
            this.massalLokasi = '';
            this.massalLokasiId = null;
            this.massalLokasiNama = '';
            this.massalLokasiAlamat = '';
            this.massalCatatan = '';
            this.massalRentang = [];
            this.isiRentangMassal();
            this.massalLokasiHtml = '';
            this.massalMapsUrl = '';
            this.massalSuratDaftar = [];
            this.massalTampilBiaya = true;
            this.massalKalimatBiaya = this.biayaMassal || '';
            this.massalAlasan = '';
            this.massalShow = true;
            this.muatInstruksiMassal();
        },
        async konfirmJadwalMassal() {
            if (this.sibuk || !this.bolehSimpanMassal) return;
            this.sibuk = true;
            try {
                const m = this.massalModeDef || {};
                const res = await axios.post('/api/v1/karir/lamaran/sub-tes/jadwal-massal', {
                    // Id AKTIVITAS, bukan id kandidat: satu kandidat bisa punya
                    // beberapa aktivitas, dan yang dijadwalkan hanya yang dipilih.
                    subTesIds: this.sasaranMassal.map((x) => x.t.id),
                    mode: this.massalMode,
                    // Berrentang tanggal: tanpa pola & jam — cukup rentangnya.
                    pola: m.batasWaktu ? null : this.massalPola,
                    mulai: m.batasWaktu ? (this.massalRentang?.[0] || null) : this.massalMulai,
                    selesai: m.batasWaktu ? (this.massalRentang?.[1] || null) : null,
                    durasiMenit: !m.batasWaktu && this.massalPola === 'BERGILIR' ? this.massalDurasi : null,
                    jedaMenit: !m.batasWaktu && this.massalPola === 'BERGILIR' ? this.massalJeda : null,
                    // Yang dikirim mengikuti FLAG mode, bukan nama modenya.
                    link: m.butuhTautan ? this.massalLink : null,
                    lokasiId: m.butuhLokasi ? this.massalLokasiId : null,
                    lokasi: m.butuhLokasi ? (this.massalLokasi || null) : null,
                    // Nama & alamat yang DIKETIK: tempat "Lainnya", atau vendor.
                    lokasiNama: (m.butuhLokasi && this.massalLokasiId === this.LOKASI_LAIN) || m.butuhTempat
                        ? (this.massalLokasiNama.trim() || null) : null,
                    lokasiAlamat: (m.butuhLokasi && this.massalLokasiId === this.LOKASI_LAIN) || m.butuhTempat
                        ? (this.massalLokasiAlamat.trim() || null) : null,
                    mapsUrl: m.butuhTempat ? (this.massalMapsUrl.trim() || null) : null,
                    lokasiHtml: m.butuhTempat ? (this.massalLokasiHtml || null) : null,
                    // Satu set surat untuk semua; tanpa set, tiap kandidat memakai suratnya.
                    suratRef: m.butuhSurat && this.massalSuratDaftar.length ? this.massalSuratDaftar.map((s) => s.ref) : null,
                    kalimatBiaya: this.biayaMassal ? ((this.massalKalimatBiaya || '').trim() || null) : null,
                    tampilBiaya: this.biayaMassal ? this.massalTampilBiaya : null,
                    alasan: this.massalAdaJadwal ? (this.massalAlasan.trim() || null) : null,
                    ...(this.jadwalFitur?.instruksi
                        ? { catatanHtml: this.massalCatatanHtml || null, catatan: teksDariHtml(this.massalCatatanHtml) || null }
                        : { catatan: this.massalCatatan || null }),
                }, CFG);

                const gagal = res.data?.result?.gagal || [];
                // Yang dilewati disebut apa adanya — paling banyak lima orang
                // (batas sekali kirim), jadi masih terbaca di satu pesan.
                const rinci = gagal.slice(0, 5).map((g) => `${g.nama}: ${g.alasan}`).join(' · ');
                this.notice([res.data?.message || 'Jadwal massal tersimpan.', rinci].filter(Boolean).join(' — '), gagal.length > 0);
                // Yang ditolak disebutkan satu per satu di log peramban: daftar
                // panjang di dalam toast tak terbaca, tapi menghilangkannya sama
                // saja kehilangan orang tanpa jejak.
                if (gagal.length) {
                    console.warn('[JADWAL MASSAL] dilewati:', gagal);
                }

                this.massalShow = false;
                this.terpilih = [];
                this.kolomPilih = '';
                await this.muatDetail(this.selectedId, { senyap: true });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menjadwalkan massal.', true);
            } finally {
                this.sibuk = false;
            }
        },

        /**
         * Master Lokasi + master peruntukannya — dimuat sekali per sesi.
         *
         * Keduanya sekaligus: daftar lokasi tanpa peruntukan tidak bisa disaring,
         * dan menyaring dengan daftar yang belum sampai berarti dropdown MCU
         * tampak kosong selama sekejap — cukup lama untuk membuat rekruter
         * mengira memang tak ada rumah sakit terdaftar.
         */
        async muatLokasi() {
            if (this.daftarLokasi.length || this.lokasiLoading) return;
            this.lokasiLoading = true;
            try {
                const [lok, per] = await Promise.all([
                    axios.get('/api/v1/master-lokasi', { ...CFG, params: { aktif: 1 } }),
                    axios.get('/api/v1/master-lokasi/peruntukan', CFG),
                ]);
                this.daftarLokasi = lok.data.result || [];
                this.daftarPeruntukan = per.data.result || [];
            } catch (e) {
                this.notice('Daftar lokasi gagal dimuat.', true);
            } finally {
                this.lokasiLoading = false;
            }
        },
        askHadir(t, nilai) {
            this.hadirTarget = t;
            this.hadirNilai = nilai;
            this.hadirCatatanHtml = t.catatanHtml || '';
            // Tanpa prasetel Lulus/Gagal: verdict harus tindakan SADAR. Kalau
            // 'LULUS' sudah tercentang sejak jendela dibuka, penilai yang cuma
            // menulis catatan lalu menyimpan telah meloloskan orang tanpa
            // pernah memutuskannya.
            this.hadirHasil = t.hasil || '';
            this.hadirNilaiSkor = t.nilai ?? null;
            this.hadirNilaiTeks = t.nilaiTeks || '';
            // MCU: isi bidangnya dari yang SUDAH tercatat, dan JANGAN memprasetel
            // status apa pun. 'Fit' yang sudah tercentang sejak jendela dibuka
            // membuat penilai yang cuma menulis catatan lalu menyimpan telah
            // menyatakan orang itu sehat tanpa pernah memutuskannya.
            const m = t.mcu || {};
            this.mcuStatus = m.status || '';
            this.mcuPenyedia = m.penyedia || '';
            this.mcuTanggal = (m.tanggal || '').slice(0, 10);
            this.mcuCatatan = m.catatan || '';
            // Tanpa prasetel: menutup lamaran orang tidak boleh berjarak satu
            // klik tak sengaja dari jendela yang baru saja dibuka.
            this.jawabPenawaran = '';
            this.hadirShow = true;
        },
        async konfirmHadir() {
            if (this.sibuk || !this.hadirTarget) return;
            this.sibuk = true;
            try {
                // HADIR membawa catatan penilaian berformat; TIDAK HADIR cukup
                // sebaris alasan — tak ada penilaian untuk orang yang tak datang.
                const hadir = this.hadirNilai === 'Y';
                // Hasil ikut dikirim bila aktivitas ini memang dicatat tim —
                // server menutup aktivitasnya sekaligus, jadi tak ada jendela
                // lanjutan yang bisa terlupakan.
                const catat = hadir && this.hadirTarget.dinilaiTim;
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${this.hadirTarget.id}/kehadiran`, {
                    hadir: this.hadirNilai,
                    hasil: catat && this.hadirTarget.peran !== 'INFORMATIF' ? (this.hadirHasil || null) : null,
                    nilai: catat ? (this.hadirNilaiSkor ?? null) : null,
                    nilaiTeks: catat ? (this.hadirNilaiTeks || null) : null,
                    // Satu sumber untuk kedua cabang. Sebelumnya cabang "tidak
                    // hadir" mengirim teks polos dan mengosongkan catatanHtml,
                    // sehingga alasan ketidakhadiran tampil beda sendiri di
                    // riwayat yang sama.
                    catatan: teksDariHtml(this.hadirCatatanHtml) || null,
                    catatanHtml: this.hadirCatatanHtml || null,
                    // Hasil pemeriksaan ikut di permintaan yang SAMA. Tidak ada
                    // jendela lanjutan yang bisa terlupakan, dan verdict-nya
                    // diturunkan server dari status ini — bukan ditanyakan dua kali.
                    ...(this.hadirTarget.isMcu && hadir ? {
                        mcuStatus: this.mcuStatus || null,
                        mcuPenyedia: this.mcuPenyedia.trim() || null,
                        mcuTanggal: this.mcuTanggal || null,
                        mcuCatatan: this.mcuCatatan.trim() || null,
                    } : {}),
                    ...(this.hadirTarget.penawaran && hadir ? { jawabanPenawaran: this.jawabPenawaran || null } : {}),
                }, CFG);
                const tes = this.hadirTarget.id;
                this.notice(res.data?.message || 'Kehadiran dicatat.');
                this.hadirShow = false;
                this.hadirTarget = null;
                await this.segarkanKartu({ tes });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mencatat kehadiran.', true);
            } finally {
                this.sibuk = false;
            }
        },
        /**
         * Bentuk jadwal yang boleh untuk sebuah aktivitas — dua aturan master:
         * tipe wajib tatap muka (MCU) dan izin bentuk per tipe (FGD tanpa
         * telepon). Server menegakkan keduanya juga; ini supaya pilihan yang
         * pasti ditolak tidak pernah ditawarkan.
         */
        modeUntuk(t) {
            const izin = t?.modeIzin;

            return (this.modeJadwal || []).filter(
                (m) => (!t?.wajibLuring || m.luring) && (!izin || izin.includes(m.kode)),
            );
        },
        askJadwal(t) {
            this.muatLokasi();
            this.jadwalTarget = t;
            // Jadwal yang sudah ada dimuat kembali supaya "Ubah Jadwal" tidak
            // memaksa mengetik ulang seluruh isinya.
            const j = t.jadwal || {};
            // Tipe tatap-muka selalu LURING, apa pun isi jadwal sebelumnya.
            // Bentuk bawaan DARI MASTER TIPE TAHAP. Negosiasi gaji membuka
            // langsung pada Telepon; tanpa ini admin harus ingat memindahkannya
            // tiap kali, dan yang lupa mengirim undangan bertautan Meet untuk
            // percakapan yang sebenarnya cuma panggilan telepon.
            const pilihan = this.modeUntuk(t);
            const sah = (k) => k && pilihan.some((m) => m.kode === k);
            this.jadwalMode = [j.mode, t.modeJadwalBawaan, pilihan[0]?.kode].find(sah) || '';
            // Nomor profil sebagai TITIK AWAL, bukan yang tersimpan diam-diam:
            // admin tetap harus melihat dan menyetujuinya sebelum menyimpan.
            this.jadwalKontak = j.kontak || this.detailKandidat?.hp || '';
            this.jadwalMulai = j.mulai || '';
            // Pembanding "jam mulai diubah?" — lihat jadwalMulaiLewat.
            this.jadwalMulaiAsal = j.mulai || '';
            this.jadwalSelesai = j.selesai || '';
            this.jadwalLink = j.link || '';
            this.jadwalLokasi = j.lokasi || '';
            // TIDAK ADA lokasi bawaan. Sebelumnya kotak ini terisi sendiri
            // dengan lokasi bertanda UTAMA — hemat satu klik, tetapi menyimpan
            // jadwal SEKALIGUS mengirim undangannya. Rekruter yang tidak
            // menyadari isian itu mengundang kandidat ke tempat yang tidak
            // pernah ia pilih, dan surelnya sudah telanjur terkirim. Memilih
            // tempat harus tindakan sadar.
            this.jadwalLokasiId = j.lokasiId || null;
            this.jadwalLokasiNama = j.lokasiNama || '';
            this.jadwalLokasiAlamat = j.lokasiAlamat || '';
            this.jadwalCatatan = j.catatan || '';
            // Rentang yang sudah ada dipakai lagi; bila belum ada, terisi dari
            // master begitu mode berrentang terpilih (lihat isiRentangBawaan).
            this.jadwalRentang = j.batasWaktu && j.mulai && j.batas
                ? [String(j.mulai).slice(0, 10), String(j.batas).slice(0, 10)]
                : [];
            this.isiRentangBawaan();
            // VENDOR: catatan cabang & tautan peta yang ditempel tim. Surat yang
            // sudah melekat ikut tampil — bisa dihapus satu per satu atau ditambah.
            this.jadwalLokasiHtml = j.lokasiHtml || '';
            this.jadwalMapsUrl = j.mapsUrl || '';
            this.jadwalSuratDaftar = (j.surat || []).map((s, i) => ({ tetap: i, ref: null, nama: s.nama, ukuran: s.ukuran, url: s.url }));
            // Kalimat biaya jadwal ini; jadwal baru memakai kalimat alur (muatInfoJadwal).
            this.jadwalKalimatBiaya = j.kalimatBiaya || t.biaya?.kalimat || '';
            // Informasi biaya: pilihan jadwal ini bila sudah ada; jadwal baru
            // memakai bawaan Master Alur (dibaca muatInfoJadwal).
            this.jadwalTampilBiaya = j.tampilBiaya !== false;
            // Instruksi yang SUDAH dikirim lebih dulu; jadwal lama yang cuma
            // punya teks polos diubah jadi paragraf supaya tidak hilang di editor.
            this.jadwalCatatanHtml = j.catatanHtml || htmlDariTeks(j.catatan || '');

            this.jadwalAlasan = '';
            this.jadwalJejakBuka = false;
            this.jadwalShow = true;
            this.muatInfoJadwal(t, !j.catatanHtml && !j.catatan);
        },
        /** 'Kam, 08 Okt · 10.00' — bentuk ringkas waktu (batas konfirmasi = waktu mulai). */
        waktuKonf(v) { return waktuSingkat(v); },
        /**
         * Instruksi dari Master Alur + riwayat jadwal aktivitas ini.
         *
         * `isiInstruksi`: editor diisi instruksi Master Alur — hanya untuk jadwal
         * yang belum pernah membawa catatan. Yang sudah dikirim tidak ditimpa:
         * isinya adalah apa yang benar-benar diterima kandidat.
         */
        async muatInfoJadwal(t, isiInstruksi) {
            this.jadwalInfo = { muat: true, id: t.id, instruksiAlur: null, kalimatBiayaAlur: null, jejak: [] };
            try {
                const res = await axios.get(`/api/v1/karir/lamaran/sub-tes/${t.id}/jadwal-info`, CFG);
                if (this.jadwalInfo.id !== t.id) return;
                const r = res.data?.result || {};
                this.jadwalInfo.instruksiAlur = r.instruksiAlur || null;
                this.jadwalInfo.jejak = r.jejak || [];
                this.jadwalInfo.kalimatBiayaAlur = r.kalimatBiayaAlur || null;
                if (!t.jadwal) {
                    this.jadwalTampilBiaya = r.tampilBiayaAlur !== false;
                    this.jadwalKalimatBiaya = r.kalimatBiayaAlur || this.jadwalKalimatBiaya;
                }
                if (isiInstruksi && r.instruksiAlur && !this.jadwalCatatanHtml) {
                    this.jadwalCatatanHtml = r.instruksiAlur;
                }
            } catch (e) {
                // Tanpa instruksi bawaan jendela tetap bisa dipakai — cukup diam.
            } finally {
                if (this.jadwalInfo.id === t.id) this.jadwalInfo.muat = false;
            }
        },
        /** Rentang bawaan mode berrentang tanggal: hari ini s.d. + N hari (dari master). */
        isiRentangBawaan() {
            const m = this.modeJadwalDef;
            if (!m?.batasWaktu || (this.jadwalRentang || []).length === 2 || !m.batasHari) return;
            this.jadwalRentang = [tanggalDepan(0), tanggalDepan(m.batasHari)];
        },
        /** Kalender batas: hari yang sudah lewat tidak bisa dipilih. */
        sebelumHariIni(d) {
            const k = new Date();

            return d < new Date(k.getFullYear(), k.getMonth(), k.getDate());
        },
        labelAksiJejak(aksi) {
            return {
                BUAT: 'Dijadwalkan',
                UBAH: 'Diubah',
                PERPANJANG: 'Diperpanjang',
                PENGINGAT: 'Pengingat terkirim',
                KIRIM_ULANG: 'Email dikirim ulang',
            }[aksi] || aksi;
        },
        /**
         * Satu baris ringkas isi jadwal pada riwayat. Berbatas waktu atau tidak
         * dibaca dari FLAG mode-nya (dikirim server), bukan dari kodenya.
         */
        ringkasJejak(j) {
            const bagian = [j.modeNama || j.mode];
            if (j.batasWaktu) {
                if (j.rentang) bagian.push(j.rentang);
                else if (j.selesai) bagian.push(`batas ${fmtTanggal(j.selesai)}`);
                // Perpanjangan hanya memundurkan batas unggah — rentangnya tetap.
                if (j.batasUnggah) bagian.push(`batas unggah ${j.batasUnggah}`);
                if (j.tempat) bagian.push(j.tempat);
            } else {
                if (j.mulai) bagian.push(this.jadwalRingkas({ mulai: j.mulai }));
                if (j.tempat) bagian.push(j.tempat);
            }
            if (j.surat) bagian.push(`surat: ${j.surat}`);
            if (j.undangan === 'GAGAL') bagian.push('email gagal');

            return bagian.filter(Boolean).join(' · ');
        },
        /** "Surat pengantar MCU" → "surat pengantar MCU" — singkatan tetap utuh di tengah kalimat. */
        hurufKecilAwal(teks) {
            const t = String(teks || '');

            return t.charAt(0).toLowerCase() + t.slice(1);
        },
        /** Isian VENDOR: nama, alamat, tautan peta, catatan cabang. */
        isianVendor() {
            return {
                lokasiNama: (this.jadwalLokasiNama || '').trim() || null,
                lokasiAlamat: (this.jadwalLokasiAlamat || '').trim() || null,
                mapsUrl: (this.jadwalMapsUrl || '').trim() || null,
                lokasiHtml: this.jadwalLokasiHtml || null,
            };
        },
        /** "1,2 MB" / "350 KB" — sama dengan SuratJadwal::teksUkuran di server. */
        fmtUkuran(b) {
            const n = Number(b) || 0;

            return n >= 1048576
                ? `${(Math.round(n / 104857.6) / 10).toString().replace('.', ',')} MB`
                : `${Math.max(1, Math.round(n / 1024))} KB`;
        },
        /**
         * Unggah SURAT PENGANTAR begitu dipilih — langkah pertama dari dua.
         * Boleh beberapa berkas sekaligus; tiap berkas diperiksa lebih dulu
         * (hanya PDF, muat di SISA kuota total) supaya yang pasti ditolak tidak
         * sempat terunggah. Server menjawab ref terenkripsi yang lalu dibawa
         * saat jadwal disimpan — dan di sana batas totalnya ditegakkan lagi.
         */
        async unggahSurat(e, untuk) {
            const berkas = [...(e.target.files || [])];
            e.target.value = '';
            if (!berkas.length || this.suratUnggahDi) return;
            const daftar = untuk === 'massal' ? this.massalSuratDaftar : this.jadwalSuratDaftar;
            const format = this.jadwalFitur?.suratFormat || ['pdf'];
            const maksMb = this.jadwalFitur?.suratMaksMb || 5;
            const maksBerkas = this.jadwalFitur?.suratMaksBerkas || 10;
            this.suratUnggahDi = untuk;
            try {
                for (const f of berkas) {
                    const ext = String(f.name.split('.').pop() || '').toLowerCase();
                    if (!format.includes(ext)) {
                        this.notice(`"${f.name}" bukan PDF — surat pengantar hanya menerima PDF.`, true);
                        continue;
                    }
                    if (daftar.length >= maksBerkas) {
                        this.notice(`Surat pengantar paling banyak ${maksBerkas} berkas.`, true);
                        break;
                    }
                    const total = daftar.reduce((n, s) => n + (Number(s.ukuran) || 0), 0);
                    if (total + f.size > this.suratMaksByte) {
                        this.notice(
                            `"${f.name}" (${this.fmtUkuran(f.size)}) tidak muat — total surat paling besar ${maksMb} MB, `
                            + `sisa ${this.fmtUkuran(Math.max(0, this.suratMaksByte - total))}.`,
                            true,
                        );
                        continue;
                    }
                    const fd = new FormData();
                    fd.append('berkas', f);
                    const res = await axios.post('/api/v1/karir/lamaran/jadwal/surat', fd, CFG);
                    const r = res.data?.result || {};
                    daftar.push({ tetap: null, ref: r.ref, nama: r.nama || f.name, ukuran: r.ukuran ?? f.size, url: null });
                }
            } catch (err) {
                this.notice(err.response?.data?.message || 'Surat gagal diunggah.', true);
            } finally {
                this.suratUnggahDi = null;
            }
        },
        /**
         * Hasil aktivitas ini diunggah KANDIDAT sendiri karena mode jadwalnya
         * (MCU mandiri)? Maka tim tidak diberi kotak unggah — hanya melihat
         * berkas kandidat. Unggahan dari setelan Master Alur (tes offline dsb.)
         * tidak termasuk: di sana berkas tim adalah lembar penilaiannya sendiri.
         */
        hasilDariKandidat(t) {
            return t?.unggahKandidat?.sumber === 'MODE';
        },
        /**
         * Batas UNGGAH yang masih bisa diperpanjang? Hanya mode yang kandidatnya
         * mengunggah sendiri (server mengirim batasUnggah hanya untuk itu), dan
         * selama berkasnya belum dikirim — sama dengan gerbang server.
         */
        bisaPerpanjang(t) {
            return this.detailKandidat?.statusLamaran === 'BERJALAN'
                && !!t.jadwal?.batasUnggah && !!t.butuhKehadiran && !t.terkunci
                && !t.unggahKandidat?.terkirim;
        },
        askPerpanjang(t) {
            this.perpanjangTarget = t;
            // Titik awal: tiga hari sesudah batas unggah sekarang (atau sesudah
            // hari ini bila batasnya sudah lewat) — rekruter cukup menyesuaikan.
            const batas = t.jadwal?.batasUnggah ? new Date(String(t.jadwal.batasUnggah).slice(0, 10) + 'T00:00:00') : null;
            const dasar = batas && batas > new Date() ? batas : new Date();
            dasar.setDate(dasar.getDate() + 3);
            const dua = (n) => String(n).padStart(2, '0');
            this.perpanjangTanggal = `${dasar.getFullYear()}-${dua(dasar.getMonth() + 1)}-${dua(dasar.getDate())}`;
            this.perpanjangAlasan = '';
            this.perpanjangShow = true;
        },
        /** Kalender perpanjangan: hanya sesudah batas unggah sekarang, dan tidak sebelum hari ini. */
        sebelumBatasBaru(d) {
            if (this.sebelumHariIni(d)) return true;
            const b = this.perpanjangTarget?.jadwal?.batasUnggah;
            if (!b) return false;
            const [y, m, t] = String(b).slice(0, 10).split('-').map(Number);

            return d <= new Date(y, m - 1, t);
        },
        async konfirmPerpanjang() {
            if (this.sibuk || !this.perpanjangTarget || !this.bolehPerpanjang) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${this.perpanjangTarget.id}/perpanjang`, {
                    selesai: this.perpanjangTanggal,
                    alasan: this.perpanjangAlasan.trim(),
                }, CFG);
                const tes = this.perpanjangTarget.id;
                this.notice(res.data?.message || 'Batas unggah diperpanjang.');
                this.perpanjangShow = false;
                this.perpanjangTarget = null;
                await this.segarkanKartu({ tes });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memperpanjang.', true);
            } finally {
                this.sibuk = false;
            }
        },
        /**
         * Aktivitas terjadwal yang surelnya MASIH masuk akal dikirim ulang —
         * aturan yang sama dengan server (kirimUlangSatu): bukan privat, belum
         * dicatat hasilnya, dan bukan kandidat yang sudah mengirim berkasnya
         * atau yang kotak unggahnya sudah tertutup (perpanjang dulu).
         */
        bisaKirimUlangBaris(t) {
            return !!t.jadwal && !t.jadwalPrivat && !!t.butuhKehadiran && !t.terkunci && !t.selesai
                && !t.unggahKandidat?.terkirim && !t.unggahKandidat?.tertutup;
        },
        bisaKirimUlang(t) {
            // Permintaan jadwal lain terbuka: email jadwal semula yang dikirim ulang
            // hanya membingungkan kandidat yang sedang menunggu jawaban usulannya.
            return this.detailKandidat?.statusLamaran === 'BERJALAN' && !this.mintaTerbuka(t) && this.bisaKirimUlangBaris(t);
        },
        /** Kandidat mengajukan jadwal lain dan tim belum menjawabnya. */
        mintaTerbuka(t) {
            return t.konfirmasi?.permintaan?.status === 'TERBUKA' && !t.selesai && !t.hadir;
        },
        bisaProsesMinta(t) {
            return this.mintaTerbuka(t) && this.aksiSaya.includes('EDIT') && this.detailKandidat?.statusLamaran === 'BERJALAN';
        },
        bukaMintaBaris(t, mode) {
            this.mintaBaris = {
                show: true,
                id: t.id,
                mode,
                minta: t.konfirmasi.permintaan,
                konteks: {
                    kandidat: this.detailKandidat?.pelamar || this.detailKandidat?.nama || '',
                    aktivitas: t.label,
                    waktuTeks: t.jadwal ? this.jadwalRingkas(t.jadwal) : '',
                },
            };
        },
        async selesaiMintaBaris(pesan) {
            const tes = this.mintaBaris.id;
            this.mintaBaris.show = false;
            this.notice(pesan || 'Tersimpan.');
            await this.segarkanKartu({ tes });
        },
        judulKirimUlang(t) {
            return t.unggahKandidat && !t.unggahKandidat.terkirim
                ? 'Kirim ulang email — berbunyi pengingat untuk mengunggah hasil, lengkap dengan rincian jadwalnya'
                : 'Kirim ulang email berisi rincian jadwal yang berlaku sekarang';
        },
        askKirimUlang(t) {
            this.kirimUlangTarget = t;
            this.kirimUlangShow = true;
        },
        /**
         * TUNDA JADWAL dari baris aksi — aturan yang sama dengan server
         * (KonfirmasiJadwal::tunda): aktivitas ber-konfirmasi yang terjadwal,
         * belum dimulai, belum dicatat kehadirannya, lamarannya masih berjalan.
         */
        bisaTunda(t) {
            if (!this.aksiSaya.includes('EDIT') || this.detailKandidat?.statusLamaran !== 'BERJALAN') return false;
            // Usulan kandidat dijawab dulu (Setujui / Tawarkan / Tolak) — menunda di
            // tengahnya menelantarkan permintaan yang sedang menunggu.
            if (this.mintaTerbuka(t)) return false;
            if (!t.konfirmasi || t.konfirmasi.status === 'DITUNDA' || !t.jadwal || t.terkunci || t.selesai || t.hadir) return false;
            const mulai = t.jadwal.mulai ? new Date(String(t.jadwal.mulai).replace(' ', 'T')) : null;

            return !!mulai && !Number.isNaN(mulai.getTime()) && mulai.getTime() > Date.now();
        },
        bisaPerbaruiTunda(t) {
            return this.aksiSaya.includes('EDIT') && this.detailKandidat?.statusLamaran === 'BERJALAN'
                && t.konfirmasi?.status === 'DITUNDA' && !t.jadwal && !t.selesai;
        },
        bukaTundaBaris(t, mode) {
            this.tundaBaris = {
                show: true,
                id: t.id,
                mode,
                awal: mode === 'perbarui' ? (t.konfirmasi?.tunda || null) : null,
                konteks: {
                    kandidat: this.detailKandidat?.pelamar || this.detailKandidat?.nama || '',
                    aktivitas: t.label,
                    waktuTeks: t.jadwal ? this.jadwalRingkas(t.jadwal) : '',
                },
            };
        },
        async selesaiTundaBaris(pesan) {
            const tes = this.tundaBaris.id;
            this.tundaBaris.show = false;
            this.notice(pesan || 'Tersimpan.');
            await this.segarkanKartu({ tes });
        },
        async konfirmKirimUlang() {
            if (this.sibuk || !this.kirimUlangTarget) return;
            this.sibuk = true;
            try {
                const res = await axios.post(`/api/v1/karir/lamaran/sub-tes/${this.kirimUlangTarget.id}/kirim-ulang`, {}, CFG);
                const tes = this.kirimUlangTarget.id;
                this.notice(res.data?.message || 'Email dikirim ulang.');
                this.kirimUlangShow = false;
                this.kirimUlangTarget = null;
                await this.segarkanKartu({ tes });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengirim ulang email.', true);
            } finally {
                this.sibuk = false;
            }
        },
        askKirimUlangMassal() {
            if (!this.aktivitasKirimUlang.length) return;
            this.kirimUlangAktivitas = this.aktivitasKirimUlang[0].label;
            this.kirimUlangMassalShow = true;
        },
        async konfirmKirimUlangMassal() {
            if (this.sibuk || !this.bolehKirimUlangMassal) return;
            this.sibuk = true;
            try {
                const res = await axios.post('/api/v1/karir/lamaran/sub-tes/kirim-ulang-massal', {
                    subTesIds: this.sasaranKirimUlang.map((x) => x.t.id),
                }, CFG);
                const gagal = res.data?.result?.gagal || [];
                const rinci = gagal.slice(0, 5).map((g) => `${g.nama}: ${g.alasan}`).join(' · ');
                this.notice([res.data?.message || 'Email dikirim ulang.', rinci].filter(Boolean).join(' — '), gagal.length > 0);
                this.kirimUlangMassalShow = false;
                this.terpilih = [];
                this.kolomPilih = '';
                await this.muatDetail(this.selectedId, { senyap: true });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengirim ulang email.', true);
            } finally {
                this.sibuk = false;
            }
        },
        /** Nama & alamat tempat luar-master — hanya saat "Lainnya" dipilih. */
        isianLokasiLain() {
            if (!this.modeJadwalDef?.butuhLokasi || this.jadwalLokasiId !== this.LOKASI_LAIN) {
                return { lokasiNama: null, lokasiAlamat: null };
            }

            return {
                lokasiNama: (this.jadwalLokasiNama || '').trim() || null,
                lokasiAlamat: (this.jadwalLokasiAlamat || '').trim() || null,
            };
        },
        /**
         * Tanggal yang dimatikan pada kalender "waktu selesai".
         *
         * Dibandingkan per HARI, bukan per detik: kegiatan yang mulai 14:00
         * boleh selesai 16:00 di hari yang sama, jadi hari mulainya sendiri
         * tidak boleh ikut padam. Sisa harinya dijaga `jadwalSelesaiSalah`.
         */
        sebelumHariMulai(d) {
            if (this.sebelumHariIni(d)) return true;
            if (!this.jadwalMulai) return false;
            const [y, b, t] = this.jadwalMulai.slice(0, 10).split('-').map(Number);

            return d < new Date(y, b - 1, t);
        },
        async konfirmJadwal() {
            if (this.sibuk || !this.jadwalTarget) return;
            this.sibuk = true;
            try {
                const berbatas = !!this.modeJadwalDef?.batasWaktu;
                const m = this.modeJadwalDef || {};
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${this.jadwalTarget.id}/jadwal`, {
                    mode: this.jadwalMode,
                    // Berrentang tanggal: tanggal pertama & terakhir (server
                    // membacanya pukul 00.00 & 23.59), tanpa jam.
                    mulai: berbatas ? (this.jadwalRentang?.[0] || null) : this.jadwalMulai,
                    selesai: berbatas ? (this.jadwalRentang?.[1] || null) : (this.jadwalSelesai || null),
                    alasan: this.butuhAlasanJadwal ? this.jadwalAlasan.trim() : null,
                    // Yang dikirim mengikuti FLAG mode, bukan nama modenya —
                    // sama persis dengan yang ditegakkan server.
                    link: this.modeJadwalDef?.butuhTautan ? this.jadwalLink : null,
                    lokasiId: this.modeJadwalDef?.butuhLokasi ? this.jadwalLokasiId : null,
                    lokasi: this.modeJadwalDef?.butuhLokasi ? (this.jadwalLokasi || null) : null,
                    // Hanya ikut saat "Lainnya" — kalau selalu dikirim, sisa
                    // ketikan dari percobaan sebelumnya tersimpan sebagai tempat
                    // kedua pada jadwal yang lokasinya sudah dipilih dari master.
                    // VENDOR mengirim isiannya sendiri (nama, alamat, peta, cabang).
                    ...(m.butuhTempat ? this.isianVendor() : this.isianLokasiLain()),
                    // Surat: urutan surat lama yang dipertahankan + ref surat baru.
                    suratTetap: m.butuhSurat ? this.jadwalSuratDaftar.filter((s) => s.tetap !== null).map((s) => s.tetap) : null,
                    suratRef: m.butuhSurat ? this.jadwalSuratDaftar.filter((s) => s.ref).map((s) => s.ref) : null,
                    // Hanya berarti untuk tipe yang punya kalimat biaya.
                    tampilBiaya: this.biayaJadwal ? this.jadwalTampilBiaya : null,
                    kalimatBiaya: this.biayaJadwal ? ((this.jadwalKalimatBiaya || '').trim() || null) : null,
                    kontak: this.modeJadwalDef?.butuhKontak ? this.jadwalKontak.trim() : null,
                    // Instruksi berformat bila kolomnya sudah ada; teks polosnya
                    // diturunkan server dari HTML — keduanya mustahil berselisih.
                    ...(this.jadwalFitur?.instruksi
                        ? { catatanHtml: this.jadwalCatatanHtml || null, catatan: teksDariHtml(this.jadwalCatatanHtml) || null }
                        : { catatan: this.jadwalCatatan || null }),
                }, CFG);
                const tes = this.jadwalTarget.id;
                this.notice(res.data?.message || 'Jadwal disimpan.');
                this.jadwalShow = false;
                this.jadwalTarget = null;
                await this.segarkanKartu({ tes });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan jadwal.', true);
            } finally {
                this.sibuk = false;
            }
        },
        async konfirmCatat() {
            if (this.sibuk || !this.catatTarget) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${this.catatTarget.id}/catat-hasil`, {
                    hasil: this.catatTarget.peran === 'INFORMATIF' ? null : this.catatHasil,
                    // Keduanya dikirim; server yang memilih mana yang dipakai
                    // menurut mode aktivitasnya, supaya layar dan penyimpanan
                    // tidak bisa berselisih soal aturan yang sama.
                    nilai: this.catatTarget.penilaian?.tipe === 'ANGKA' ? (this.catatNilai ?? null) : null,
                    nilaiTeks: this.catatTarget.penilaian?.tipe === 'TEKS' ? (this.catatNilaiTeks || null) : null,
                    adjudikasi: this.catatTarget.pemeriksaan ? (this.catatAdjudikasi || null) : null,
                    // Ringkasan polos ikut dikirim untuk daftar & ekspor; server
                    // tetap menurunkannya sendiri dari HTML, jadi keduanya
                    // mustahil berselisih.
                    catatan: teksDariHtml(this.catatCatatanHtml) || null,
                    catatanHtml: this.catatCatatanHtml || null,
                }, CFG);
                const tes = this.catatTarget.id;
                this.notice(res.data?.message || 'Hasil dicatat.');
                this.catatShow = false;
                this.catatTarget = null;
                await this.segarkanKartu({ tes });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mencatat hasil.', true);
            } finally {
                this.sibuk = false;
            }
        },

        /** ESCAPE HATCH: kandidat tak hadir — tahap berhenti menunggu tes ini. */
        /**
         * Tarik hasil ujian online dari HCLearn.
         *
         * Dipakai saat webhook CAT tak sampai. Yang tersimpan tetap angka resmi
         * penyedia — admin tidak pernah mengetik nilai. Aman diulang.
         */
        /**
         * Buka aktivitas berikutnya untuk kandidat ini.
         *
         * Hanya muncul pada tahap BERURUTAN dengan mode MANUAL: tim sengaja
         * menahan agar hasil aktivitas ini dibaca dulu sebelum kandidat
         * diteruskan ke asesmen berikutnya.
         */
        async lanjutkanAktivitas(t) {
            if (this.lanjutId) return;
            this.lanjutId = t.id;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${t.id}/lanjutkan`, {}, CFG);
                this.notice(res.data?.message || 'Aktivitas berikutnya dibuka.');
                await this.segarkanKartu({ tes: t.id });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal membuka aktivitas berikutnya.', true);
            } finally {
                this.lanjutId = null;
            }
        },
        /**
         * Nyatakan lulus / tidak lulus pada ujian online ber-peran INFORMATIF.
         *
         * LANGSUNG, tanpa jendela konfirmasi. Alat tes seperti PAPI Kostick dan
         * DISC tidak berbunyi lulus/gagal sendiri, jadi keputusannya memang milik
         * penilai — dan ia sudah membaca hasilnya sebelum menekan. Menyisipkan
         * dialog "yakin?" di sini hanya menambah satu klik pada pekerjaan yang
         * dilakukan berpuluh kali sehari, sementara yang dipertaruhkan bukan
         * aksi final: lamaran baru berhenti pada keputusan TAHAP, yang tetap
         * punya konfirmasinya sendiri.
         *
         * Nilai dan catatan TIDAK dikirim. Angkanya milik HCLearn dan server
         * mempertahankan catatan lama bila field-nya absen — mengirim keduanya
         * kosong berarti menghapus apa yang sudah ditulis.
         */
        async putusTes(t, hasil) {
            if (this.keputusanId) return;
            this.keputusanId = t.id;
            this.keputusanHasil = hasil;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${t.id}/catat-hasil`, { hasil }, CFG);
                this.notice(res.data?.message || (hasil === 'LULUS' ? 'Dinyatakan lulus.' : 'Dinyatakan tidak lulus.'));
                await this.segarkanKartu({ tes: t.id });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan keputusan.', true);
            } finally {
                this.keputusanId = null;
                this.keputusanHasil = null;
            }
        },
        async sinkronHasil(t) {
            if (this.sinkronId) return;
            this.sinkronId = t.id;
            try {
                const res = await axios.post(`/api/v1/karir/lamaran/sub-tes/${t.id}/sinkron`, {}, CFG);
                this.notice(res.data?.message || 'Hasil ditarik dari HCLearn.');
                await this.segarkanKartu({ tes: t.id });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menarik hasil dari HCLearn.', true);
            } finally {
                this.sinkronId = null;
            }
        },
        askTidakHadir(t) {
            this.absenTarget = t;
            this.absenCatatan = '';
            this.absenShow = true;
        },
        async konfirmTidakHadir() {
            const t = this.absenTarget;
            if (this.sibuk || !t) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/sub-tes/${t.id}/tidak-hadir`, {
                    catatan: this.absenCatatan || null,
                }, CFG);
                this.absenShow = false;
                this.notice(res.data?.message || 'Aktivitas ditandai tidak hadir.');
                await this.segarkanKartu({ tes: t.id });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memproses.', true);
            } finally {
                this.sibuk = false;
            }
        },

        /* ── Keputusan ── */
        /**
         * Tab catatan yang terbuka lebih dulu. Keputusan yang MENUNTUT alasan
         * dibuka di tab internal — alasan itu catatan tim, dan admin yang
         * disambut tab kandidat akan mengetik alasannya di tempat yang salah.
         * Selebihnya mengikuti kategori akun (catatanTahap.tabBawaan).
         */
        tabAwalCatatan(hasil) {
            if (!this.catatanTahap.eksternalSiap) return 'INTERNAL';
            const def = this.hasilKeputusan.find((h) => h.kode === hasil);
            if (def?.butuhAlasan || def?.olehKandidat) return 'INTERNAL';

            return this.catatanTahap.tabBawaan === 'INTERNAL' ? 'INTERNAL' : 'EKSTERNAL';
        },
        /** Catatan untuk kandidat yang layak dikirim — kosong / kolom belum ada = null. */
        catatanEksternalKirim(html) {
            return this.catatanTahap.eksternalSiap && teksDariHtml(html || '').trim() ? html : null;
        },
        askPutus(r, hasil) {
            // Pagar kedua. Tombolnya memang sudah mati, tapi modal ini juga
            // terpanggil dari jalur lain — dan membuka borang yang pasti
            // ditolak server adalah cara terburuk menyampaikan "tidak boleh".
            if (! this.bolehPutus) {
                this.notice('Akun Anda tidak punya izin APPROVE di Worklist Pelamar. Minta ditambahkan di Manajemen Hak Akses.', true);

                return;
            }
            this.putusTarget = r;
            this.putusHasil = hasil;
            this.putusCatatanHtml = '';
            this.putusCatatanEksternalHtml = '';
            this.putusTabCatatan = this.tabAwalCatatan(hasil);
            // Pilihan Talent Pool disemai dari BAWAAN masternya, bukan dari
            // pilihan terakhir admin. Keputusan sebelumnya menyangkut orang
            // lain; mewarisinya membuat kandidat kedua ikut nasib kandidat
            // pertama hanya karena modalnya tidak diperiksa ulang.
            // Bawaan dari master, TAPI dipaksa "tidak" bila tahapnya belum masuk
            // cut-off — supaya nilai yang terkirim selalu sama dengan yang
            // benar-benar terlihat admin.
            this.putusTalentPool = r?.bolehTalentPool
                && (this.hasilKeputusan.find((h) => h.kode === hasil)?.talentPool) !== false;
            // Prasetel hari ini: kabar mundur paling sering dicatat di hari yang sama.
            this.putusTanggal = this.hariIni;
            // Borang kesehatan TIDAK LAGI menumpang modal ini — ia pindah ke
            // langkah kehadiran, tempat hasilnya memang diterima.
            // Tampilkan berkas yang mungkin sudah diunggah sebelumnya.
            this.loadBerkas(r?.tahapId || null);
            this.putusSetuju = false; // selalu minta ulang, jangan warisi centang sebelumnya
            this.putusSetujuAktivitas = false;
            this.konfirmShow = true;
        },
        async konfirmPutus() {
            if (this.sibuk || !this.putusTarget?.tahapId) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/tahap/${this.putusTarget.tahapId}/putus`, {
                    hasil: this.putusHasil,
                    catatan: teksDariHtml(this.putusCatatanHtml) || null,
                    catatanHtml: this.putusCatatanHtml || null,
                    // Catatan UNTUK KANDIDAT — null bila kosong atau kolomnya belum ada.
                    catatanEksternalHtml: this.catatanEksternalKirim(this.putusCatatanEksternalHtml),
                    // Hanya berarti untuk hasil yang memang menyerahkan pilihan
                    // ini ke admin; server mengabaikannya pada hasil lain.
                    ...(this.putusDef?.pilihTalentPool ? { talentPool: this.putusTalentPool } : {}),
                    tanggalKonfirmasi: this.putusDef?.olehKandidat ? (this.putusTanggal || null) : null,
                    // Hasil MCU ikut pada keputusan yang sama — satu perjalanan
                    // ke server, jadi mustahil ada keputusan tanpa hasil
                    // kesehatannya (atau sebaliknya) bila salah satu gagal.
                }, CFG);
                const tahap = this.putusTarget.tahapId;
                this.notice(res.data?.message || 'Keputusan tersimpan.');
                this.konfirmShow = false;
                this.detailKandidat = null;
                this.putusTarget = null;
                this.segarkanKartu({ tahap });
                this.muatProgram();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memproses keputusan.', true);
            } finally {
                this.sibuk = false;
            }
        },
        notice(x, err = false) { this.toast = x; this.toastErr = err; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3500); },
    },
};
</script>

<style scoped>
.plw-ell { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-mono { font-family: 'JetBrains Mono', 'Courier New', monospace; font-weight: 700; color: #9aa3b5; }

/* ═══ SHELL HALAMAN: full-bleed di dalam shell-content ═══ */
.plw { display: flex; margin: -1rem; height: calc(100vh - 68px); min-height: 0; overflow: hidden; }

/* ═══ PANEL PROGRAM ═══ */
.plw-panel { width: 304px; flex: 0 0 304px; display: flex; flex-direction: column; min-height: 0; background: rgba(255, 255, 255, 0.66); border-right: 1px solid rgba(226, 232, 240, 0.75); }
.plw-panel__head { padding: 16px 16px 10px; flex: 0 0 auto; }
.plw-panel__toprow { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
.plw-panel__title { font-size: 12px; font-weight: 900; letter-spacing: 0.1em; color: #0f172a; }
.plw-panel__count { font-size: 11px; font-weight: 700; color: #8b93a7; background: #eef0f7; border-radius: 999px; padding: 3px 10px; }
/* DASAR DAFTAR — program ⇄ job vacancy. Bentuknya sengaja disamakan dengan
   .plw-modes (pemilih Kanban/List): dua-duanya "cara membaca yang sama-sama
   sah", bukan penyaring yang mempersempit. Bentuk yang sama untuk peran yang
   sama membuatnya langsung terbaca tanpa perlu dicoba dulu. */
.plw-basis { display: grid; grid-template-columns: 1fr 1fr; gap: 3px; padding: 4px; border-radius: 13px; background: #f2f4fb; border: 1px solid #e6e9f3; }
.plw-basis__btn { appearance: none; border: none; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 6px; border-radius: 10px; background: transparent; color: #8792a6; transition: all 0.16s; min-width: 0; }
.plw-basis__btn span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plw-basis__btn:hover { color: #4f46e5; }
.plw-basis__btn.is-on { background: #fff; color: #4f46e5; box-shadow: 0 3px 10px rgba(99, 102, 241, 0.14); }

.plw-search { position: relative; display: flex; align-items: center; margin-top: 11px; }
.plw-search svg { position: absolute; left: 14px; }
.plw-search input { width: 100%; padding: 11px 14px 11px 40px; border-radius: 13px; border: 1px solid #e6e9f3; background: #f7f8fc; font-family: inherit; font-size: 13.5px; color: #334155; outline: none; transition: all 0.18s; }
.plw-search input:focus { border-color: #a5b4fc; background: #fff; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }
.plw-chips { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 11px; }
.plw-chip { appearance: none; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 700; padding: 7px 13px; border-radius: 10px; transition: all 0.16s; border: 1px solid #e6e9f3; background: #fff; color: #64748b; }
.plw-chip.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.plw-panel__list { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 11px; padding: 6px 14px 16px; }
.plw-empty { text-align: center; color: #94a3b8; font-size: 13px; padding: 1rem 0; }

/* ═══ INDIKATOR LOADING ═══ */
.plw-load { display: flex; align-items: center; justify-content: center; gap: 9px; color: #8b93a7; font-size: 13px; font-weight: 600; padding: 1rem 0; }
.plw-spin { width: 18px; height: 18px; border-radius: 50%; border: 2.5px solid rgba(99, 102, 241, 0.18); border-top-color: #6366f1; animation: plwSpin 0.7s linear infinite; flex: 0 0 auto; }
.plw-spin--lg { width: 30px; height: 30px; border-width: 3px; }

/* Rangka baris yang sedang dimuat. Tingginya SAMA dengan baris centang
   formulir yang akan menggantikannya, supaya modalnya tidak melonjak saat
   datanya tiba. */
.plw-rangka {
    height: 46px;
    border-radius: 10px;
    margin-bottom: 8px;
    background: linear-gradient(90deg, #eef1f6 25%, #f6f8fb 50%, #eef1f6 75%);
    background-size: 240% 100%;
    animation: plwRangka 1.3s ease-in-out infinite;
}
.plw-rangka--pendek { width: 72%; }
@keyframes plwRangka {
    0% { background-position: 130% 0; }
    100% { background-position: -30% 0; }
}
@keyframes plwSpin { to { transform: rotate(360deg); } }

.plw-prog { position: relative; appearance: none; cursor: pointer; text-align: left; font-family: inherit; width: 100%; padding: 14px 15px 14px 18px; border-radius: 16px; background: #fff; transition: all 0.18s; border: 1px solid #eef0f7; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); display: flex; flex-direction: column; }
.plw-prog:hover { box-shadow: 0 8px 20px rgba(15, 23, 42, 0.07); }

/* ── PROGRAM YANG SUDAH DISERAHTERIMAKAN ─────────────────────────────────────
   Teredam, bukan disembunyikan. Menghilangkannya dari daftar membuat orang
   mencari program yang ia tahu ada — dan pencarian itu berakhir di dugaan
   bahwa sistemnya yang kehilangan data. Yang benar: tetap terlihat, jelas
   tidak bisa dikerjakan, dan menyebutkan sebabnya. */
.plw-prog.is-lepas {
    cursor: not-allowed;
    background: #fbfcfe;
    border-style: dashed;
    border-color: #e2e6f0;
    box-shadow: none;
}
.plw-prog.is-lepas:hover { box-shadow: none; }
/* Isinya diredam, TAPI penanda & notanya tidak — keduanya justru yang perlu
   dibaca di kartu ini. */
.plw-prog.is-lepas > *:not(.plw-prog__nota):not(.plw-prog__toprow) { opacity: .5; }
.plw-prog.is-lepas .plw-prog__bar { background: #cbd5e1 !important; opacity: .6; }

.plw-prog__lepas {
    display: inline-flex; align-items: center; gap: 4px;
    margin-left: auto; margin-right: 6px;
    padding: 2px 8px; border-radius: 999px;
    background: rgba(245, 158, 11, .15); color: #b45309;
    font-size: 9.5px; font-weight: 800; letter-spacing: .03em; text-transform: uppercase;
    white-space: nowrap;
}
.plw-prog__lepas i { font-size: 9px; }

.plw-prog__nota {
    display: flex; align-items: flex-start; gap: 6px;
    margin-top: 10px; padding-top: 9px;
    border-top: 1px dashed #e6eaf3;
    font-size: 10.5px; line-height: 1.5; color: #94a3b8;
}
.plw-prog__nota i { color: #b45309; margin-top: 1px; flex: 0 0 auto; }
/* AKTIF: SATU PENANDA PER SISI, TIDAK DUA DI SISI YANG SAMA.
   Bilah aksen di kiri sudah menandai program terpilih. Memberi warna aksen
   pada border kiri berarti dua garis berwarna sama berdempetan di tepi yang
   sama — terbaca sebagai garis tebal yang cacat, bukan sebagai penekanan.
   Border aksennya karena itu hanya dipakai di kanan, atas, dan bawah. */
.plw-prog.is-on {
    border-color: var(--acc);
    border-left-color: transparent;
    box-shadow: 0 12px 30px rgba(99, 102, 241, 0.14);
}
/* Bilahnya menebal sedikit saat aktif — penekanannya di situ, sekali saja. */
.plw-prog.is-on .plw-prog__bar { width: 5px; }
.plw-prog__bar { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; border-radius: 4px 0 0 4px; background: var(--acc); }
.plw-prog__toprow { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; }
.plw-prog__tag { display: inline-block; font-size: 9.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; padding: 4px 9px; border-radius: 7px; }
.plw-prog__tag.is-mt { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.plw-prog__tag.is-rek { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.plw-prog__code { font-size: 9.5px; font-weight: 700; letter-spacing: 0.08em; color: #aab2c5; font-family: 'JetBrains Mono', monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
.plw-prog__title { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 15px; font-weight: 800; color: #1e293b; letter-spacing: -0.01em; margin-top: 9px; line-height: 1.3; }
.plw-prog__meta { display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12px; color: #8792a6; min-width: 0; }
.plw-prog__meta + .plw-prog__meta { margin-top: 4px; }
.plw-prog__stats { display: flex; align-items: center; gap: 14px; margin-top: 11px; padding-top: 11px; border-top: 1px solid #eef0f7; font-size: 11.5px; font-weight: 600; color: #8792a6; }
.plw-prog__stat { display: inline-flex; align-items: center; gap: 5px; }
.plw-pager { display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 12px; font-weight: 700; color: #8b93a7; padding-top: 4px; }
.plw-pager__btn { appearance: none; cursor: pointer; border: 1px solid #e6e9f3; background: #fff; width: 30px; height: 30px; border-radius: 9px; color: #64748b; }
.plw-pager__btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* ═══ MAIN ═══ */
.plw-main { position: relative; flex: 1; min-width: 0; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 26px 30px 44px; }
.plw-blob { position: absolute; border-radius: 50%; filter: blur(8px); pointer-events: none; z-index: 0; }
.plw-blob--a { top: -120px; right: 14%; width: 420px; height: 420px; background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.13), rgba(139, 92, 246, 0) 70%); animation: plwFloatA 16s ease-in-out infinite; }
.plw-blob--b { bottom: -160px; left: 8%; width: 460px; height: 460px; background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0) 70%); animation: plwFloatB 19s ease-in-out infinite; }
@keyframes plwFloatA { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(30px, -24px); } }
@keyframes plwFloatB { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-26px, 22px); } }
.plw-main__inner { position: relative; z-index: 2; }
.plw-hgroup { margin-bottom: 18px; }
.plw-h1 { margin: 0; font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: -0.025em; }
.plw-sub { margin: 6px 0 0; font-size: 14px; color: #64748b; }

.plw-progrow { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
.plw-eyebrow { font-size: 11px; font-weight: 800; letter-spacing: 0.16em; color: #8b5cf6; }
.plw-progtitle { font-size: 22px; font-weight: 800; color: #1e293b; letter-spacing: -0.02em; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.plw-progflow { display: flex; align-items: center; gap: 7px; margin-top: 5px; font-size: 13px; color: #8792a6; min-width: 0; }
.plw-progact { display: flex; align-items: center; gap: 10px; flex: 0 0 auto; }
.plw-btn-jadwal { appearance: none; cursor: pointer; font-family: inherit; font-size: 13.5px; font-weight: 800; color: #fff; padding: 11px 18px; border-radius: 13px; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 10px 24px rgba(99, 102, 241, 0.3); display: inline-flex; align-items: center; gap: 8px; transition: transform 0.16s; }
.plw-btn-jadwal:hover { transform: translateY(-2px); }
.plw-btn-reload { appearance: none; cursor: pointer; border: 1px solid #e6e9f3; background: #fff; width: 42px; height: 42px; border-radius: 13px; display: flex; align-items: center; justify-content: center; color: #64748b; transition: all 0.3s; }
.plw-btn-reload:hover { color: #4f46e5; transform: rotate(90deg); }
/* Penyegaran senyap sesudah aksi: papan tetap tampil, tombol ini yang berputar. */
.plw-btn-reload.is-segar { color: #4f46e5; }
.plw-btn-reload.is-segar svg { animation: plwSpin 0.9s linear infinite; }

.plw-tabs { display: flex; gap: 9px; margin-bottom: 13px; flex-wrap: wrap; }
.plw-tab { appearance: none; cursor: pointer; font-family: inherit; font-size: 13.5px; font-weight: 800; display: inline-flex; align-items: center; gap: 9px; padding: 11px 18px; border-radius: 14px; transition: all 0.18s; background: #fff; color: #64748b; border: 1px solid #e6e9f3; }
.plw-tab.is-run { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; border-color: transparent; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.plw-tab.is-rej { background: linear-gradient(135deg, #f87171, #ef4444); color: #fff; border-color: transparent; box-shadow: 0 10px 24px rgba(239, 68, 68, 0.26); }
.plw-tab__badge { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 8px; font-size: 12px; font-weight: 800; background: #eef0f7; color: #94a3b8; }
/* MUNDUR: ungu-abu, sengaja BUKAN merah. Merah dipakai "Tidak Lolos" dan
   berarti perusahaan yang menolak; kandidat yang pergi sendiri bukan kegagalan
   seleksi, dan mewarnainya sama membuat dua peristiwa berbeda terbaca sama. */
.plw-tab.is-out { background: linear-gradient(135deg, #a78bfa, #7c3aed); color: #fff; border-color: transparent; box-shadow: 0 10px 24px rgba(124, 58, 237, 0.26); }
/* SEMUA: gelap netral — ia bukan salah satu keadaan, melainkan ketiadaan
   penyaring, jadi ia tidak boleh meminjam warna keadaan mana pun. */
.plw-tab.is-all { background: linear-gradient(135deg, #475569, #1e293b); color: #fff; border-color: transparent; box-shadow: 0 10px 24px rgba(30, 41, 59, 0.22); }
.plw-tab.is-run .plw-tab__badge, .plw-tab.is-rej .plw-tab__badge, .plw-tab.is-out .plw-tab__badge, .plw-tab.is-all .plw-tab__badge { background: rgba(255, 255, 255, 0.24); color: #fff; }

/* KANBAN */
.plw-kanban { display: flex; gap: 16px; align-items: flex-start; overflow-x: auto; padding-bottom: 12px; scroll-snap-type: x proximity; }
.plw-col { flex: 0 0 288px; width: 288px; scroll-snap-align: start; background: rgba(255, 255, 255, 0.62); border: 1px solid rgba(226, 232, 240, 0.8); border-radius: 20px; padding: 14px 13px; }
.plw-col__head { display: flex; align-items: center; gap: 7px; padding: 2px 4px 12px; }
.plw-col__num { width: 26px; height: 26px; border-radius: 9px; background: rgba(99, 102, 241, 0.12); color: #4f46e5; font-size: 12.5px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.plw-col__num.is-hot { background: rgba(245, 158, 11, 0.16); color: #b45309; }
/* Tab "Tidak Lolos": kolom tahapnya sama, tapi diberi nada merah supaya sekali
   lihat ketahuan ini papan kegagalan — dan di tahap mana penyusutan terbesar. */
.plw-kanban.is-gugur .plw-col__num.is-hot { background: rgba(239, 68, 68, .14); color: #b91c1c; }
.plw-kanban.is-gugur .plw-col__count:not(:empty) { color: #b91c1c; background: rgba(239, 68, 68, .1); }
/* Tab "Mundur": nada ungu — di tahap mana kandidat paling sering pergi sendiri. */
.plw-kanban.is-mundur .plw-col__num.is-hot { background: rgba(124, 58, 237, .14); color: #6d28d9; }
.plw-kanban.is-mundur .plw-col__count:not(:empty) { color: #6d28d9; background: rgba(124, 58, 237, .1); }
.plw-col__name { font-size: 13.5px; font-weight: 800; color: #334155; flex: 1; min-width: 0; display: flex; align-items: center; gap: 6px; white-space: nowrap; overflow: hidden; }
/* Kolom rombongan alur lama — dibedakan, bukan diredupkan. Orang-orang di
   dalamnya tetap harus dikerjakan; hanya asal alurnya yang berbeda. */
.plw-col.is-lawas { border-style: dashed; border-color: rgba(167, 139, 250, .55); background: rgba(250, 248, 255, 0.72); }
.plw-col__lawas { flex: 0 0 auto; padding: 1px 7px; border-radius: 999px; font-size: 9.5px; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; background: #ede9fe; color: #6d28d9; cursor: help; }
.plw-col__count { font-size: 12px; font-weight: 800; color: #94a3b8; background: #eef0f7; border-radius: 8px; padding: 2px 9px; flex: 0 0 auto; }
.plw-col__cards { display: flex; flex-direction: column; gap: 11px; min-height: 60px; }
.plw-col__empty { display: flex; align-items: center; justify-content: center; height: 80px; color: #c3cad8; font-size: 22px; font-weight: 300; }

.plw-card { appearance: none; cursor: pointer; text-align: left; font-family: inherit; width: 100%; background: #fff; border: 1px solid #eef0f7; border-radius: 16px; padding: 14px; transition: all 0.18s; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04); animation: plwCardIn 0.4s cubic-bezier(0.22, 1, 0.36, 1) both; }
.plw-card:hover { border-color: #d9def0; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
@keyframes plwCardIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
.plw-card__toprow { display: flex; align-items: center; gap: 10px; width: 100%; min-width: 0; }
.plw-card__avatar { width: 38px; height: 38px; border-radius: 11px; color: #fff; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.plw-card__id { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.plw-card__name { font-size: 14px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-card__code { font-size: 10.5px; font-weight: 700; letter-spacing: 0.06em; color: #aab2c5; font-family: 'JetBrains Mono', monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-card__pos { display: block; margin-top: 10px; font-size: 12.5px; font-weight: 600; color: #6366f1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Konteks lowongan di kartu — dibuat kecil & abu supaya nama kandidat tetap
   jadi hal pertama yang terbaca. */
/* SATU BARIS, DIPOTONG RAPI.
   Sebelumnya `flex-wrap: wrap` + span tanpa batas lebar membiarkan departemen
   panjang ("PRODUCTION SUPPORT · HEALTH SAFETY…") mengalir keluar dan menjebol
   tepi kartu — teksnya terpotong oleh tepi kolom, bukan oleh ellipsis, jadi
   terbaca seperti tampilan yang rusak. */
.plw-card__meta { display: flex; flex-wrap: nowrap; gap: 4px 9px; margin-top: 5px; font-size: 10.5px; color: #94a3b8; min-width: 0; overflow: hidden; }
.plw-card__meta span { display: inline-flex; align-items: center; gap: 4px; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-card__meta span > i { flex: none; }
.plw-card__foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 10px; min-width: 0; }
.plw-card__umur { display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 600; color: #94a3b8; flex: none; }

/* ── Daftar lowongan sekaligus penyaring papan ─────────────────────────── */
.plw-lows { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 10px; margin-bottom: 18px; }
.plw-low { appearance: none; cursor: pointer; text-align: left; font-family: inherit; background: #fff; border: 1px solid #eef0f7; border-radius: 14px; padding: 11px 13px; transition: all .18s; display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.plw-low:hover { border-color: #c7d2fe; box-shadow: 0 6px 16px rgba(15, 23, 42, .06); transform: translateY(-1px); }
.plw-low.is-on { border-color: #6366f1; background: linear-gradient(180deg, #f5f3ff, #fff); box-shadow: 0 6px 18px rgba(99, 102, 241, .16); }
.plw-low.is-penuh { border-left: 3px solid #10b981; }
.plw-low__nama { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 800; color: #0f172a; letter-spacing: -.01em; min-width: 0; }
.plw-low__lvl { flex: none; font-size: 9.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #6366f1; background: rgba(99, 102, 241, .1); padding: 2px 6px; border-radius: 999px; }
.plw-low__meta { display: flex; flex-wrap: wrap; gap: 3px 9px; font-size: 10.5px; color: #94a3b8; min-width: 0; }
.plw-low__meta span { display: inline-flex; align-items: center; gap: 4px; min-width: 0; }
.plw-low__mpp { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 9.5px; color: #64748b; background: #f1f5f9; padding: 1px 5px; border-radius: 4px; }
.plw-low__angka { font-size: 11px; color: #64748b; }
.plw-low__angka b { color: #0f172a; }
/* Lencana keadaan: dipotong bila kepanjangan ("Tidak Lulus — Konfirmasi"),
   bukan mendorong umur-lamaran keluar kartu. Huruf besarnya dibuat lewat CSS,
   bukan lewat .toUpperCase() di template — teks yang sudah dinaikkan di JS
   tidak bisa lagi dikembalikan untuk atribut title. */
.plw-card__chip { display: inline-block; margin-top: 11px; max-width: 100%; font-size: 9.5px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; padding: 4px 9px; border-radius: 7px; background: #eef0f7; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-card__chip.tone-nunggu { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.plw-card__chip.tone-perlu { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.plw-card__chip.tone-skor { background: rgba(139, 92, 246, 0.14); color: #7c3aed; }
.plw-card__chip.tone-lolos { background: rgba(16, 185, 129, 0.12); color: #059669; }
.plw-card__chip.tone-pascaPenerimaan { background: rgba(16, 185, 129, 0.16); color: #047857; }
.plw-card__chip.tone-gugur { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
.plw-card__chip.tone-talent { background: rgba(234, 179, 8, 0.16); color: #a16207; }
/* Kandidat yang pergi sendiri — ungu, sewarna tab "Mengundurkan Diri" dan
   sengaja bukan merah "Tidak Lolos". */
.plw-card__chip.tone-mundur { background: rgba(124, 58, 237, 0.13); color: #6d28d9; }

/* ═══ KARTU PENYARING ═══
   Kisi, bukan baris mengalir. Penyaring yang lebarnya mengikuti isi membuat
   posisinya berpindah-pindah antar program (nama lowongan panjang menggeser
   sisanya), dan otot ingatan "kampus ada di kotak ketiga" tidak pernah
   terbentuk. Kisi menjaga tiap penyaring di tempat yang sama. */
.plw-toolbar { background: #fff; border: 1px solid #e7e3fb; border-radius: 16px; box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06); margin-bottom: 14px; }

/* ── Kepala panel penyaring ────────────────────────────────────────────── */
.plw-fhead { display: flex; align-items: center; gap: 10px; padding: 11px 14px; }
.plw-fhead__ico { flex: none; width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; font-size: 13px; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 5px 12px rgba(99, 102, 241, .28); }
.plw-fhead__ttl { flex: 1; min-width: 0; }
.plw-fhead__ttl b { display: block; font-size: 13px; font-weight: 800; color: #1e293b; letter-spacing: -.01em; }
.plw-fhead__ttl small { display: block; font-size: 11px; color: #94a3b8; margin-top: 1px; }
.plw-fhead__n { flex: none; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; display: grid; place-items: center; font-size: 10.5px; font-weight: 800; color: #4338ca; background: #e0e7ff; }
.plw-fhead__x { appearance: none; flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #e6e9f3; background: #fff; padding: 6px 10px; border-radius: 9px; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 700; color: #64748b; transition: all .16s; }
.plw-fhead__x:hover { color: #dc2626; border-color: #f4d0d0; background: #fef2f2; }
.plw-fhead__tgl { appearance: none; flex: none; width: 30px; height: 30px; border-radius: 9px; border: 1px solid #e6e9f3; background: #fff; cursor: pointer; color: #64748b; display: grid; place-items: center; font-size: 12px; transition: all .16s; }
.plw-fhead__tgl:hover { color: #4f46e5; border-color: #c7cdf0; }
.plw-fbody { border-top: 1px solid #f1f2f9; }

.plw-fgrid { display: grid; grid-template-columns: 1.6fr 1fr 1.2fr 1.2fr 1fr; gap: 9px; padding: 13px 14px 10px; align-items: center; }
.plw-f { width: 100%; min-width: 0; }
.plw-f--tgl { width: 100% !important; }

/* ── Rentang tanggal: baris penuh, berlabel, berpintasan ───────────────── */
.plw-frow { padding: 0 14px 13px; }
.plw-frow__lbl { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 11.5px; font-weight: 800; color: #475569; }
.plw-frow__lbl > .bi { color: #8b5cf6; font-size: 12px; }
.plw-frow__isi { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
/* Pemilih rentang mengambil sisa baris; pintasannya tetap seukuran isinya. */
.plw-frow__isi > .plw-f--tgl { flex: 1 1 300px; min-width: 0; }
.plw-fquick { display: flex; align-items: center; gap: 6px; flex: 0 0 auto; flex-wrap: wrap; }
.plw-fq { appearance: none; border: 1px solid #e6e9f3; background: #fff; padding: 8px 12px; border-radius: 9px; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 700; color: #64748b; transition: all .16s; white-space: nowrap; }
.plw-fq:hover { border-color: #c7d2fe; color: #4f46e5; }
.plw-fq.is-on { border-color: transparent; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 6px 14px rgba(99, 102, 241, .24); }
.plw-fq.is-off { color: #94a3b8; }
.plw-fq.is-off:hover { color: #dc2626; border-color: #f4d0d0; background: #fef2f2; }

/* ── FAB penyaring — hanya ponsel (lihat media query di bawah) ─────────── */
.plw-fab { display: none; }
.plw-ftirai { display: none; }
.plw-lopt { display: flex; flex-direction: column; line-height: 1.35; padding: 2px 0; }
.plw-lopt b { font-size: 12.5px; color: #1e293b; }
.plw-lopt small { font-size: 11px; color: #94a3b8; }
/* Opsi kampus: bendera di kiri, nama & jumlah menumpuk di kanannya — bentuk
   yang sama dengan pemilih kampus di formulir pendaftaran. */
.plw-lopt--kampus { display: grid; grid-template-columns: 20px minmax(0, 1fr); grid-template-rows: auto auto; column-gap: 9px; align-items: center; }
.plw-lopt--kampus > .plw-lopt__flag { grid-row: 1 / span 2; }
.plw-lopt--kampus > b, .plw-lopt--kampus > small { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plw-lopt__flag { width: 20px; height: 15px; border-radius: 2px; box-shadow: 0 0 0 1px rgba(15, 23, 42, .1); object-fit: cover; }
/* Negara tak tercatat di data impor: bola dunia netral, bukan bendera tebakan.
   Bendera yang salah lebih menyesatkan daripada tidak ada bendera. */
.plw-lopt__flag--kosong { display: grid; place-items: center; height: auto; box-shadow: none; color: #cbd5e1; font-size: 13px; }

/* Chip penyaring aktif */
.plw-chiprow { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; padding: 0 14px 13px; }
.plw-fchip { display: inline-flex; align-items: center; gap: 7px; max-width: 100%; padding: 5px 6px 5px 11px; border-radius: 9px; background: #eef2ff; border: 1px solid #c7d2fe; font-size: 11.5px; font-weight: 700; color: #3730a3; }
.plw-fchip__k { opacity: 0.65; flex: 0 0 auto; }
.plw-fchip button { appearance: none; border: none; background: transparent; cursor: pointer; color: #4f46e5; display: flex; padding: 1px; font-size: 10px; flex: 0 0 auto; }
.plw-fchip button:hover { color: #dc2626; }
.plw-fclear { appearance: none; border: none; background: transparent; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 700; color: #64748b; padding: 5px 6px; text-decoration: underline; text-underline-offset: 2px; }
.plw-fclear:hover { color: #dc2626; }

/* ═══ PEMILIH MODE TAMPILAN ═══ */
.plw-moderow { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 0 2px 13px; }
.plw-modes { display: inline-flex; align-items: center; gap: 3px; padding: 4px; border-radius: 13px; background: #fff; border: 1px solid #e7e3fb; box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06); }
.plw-mode { appearance: none; border: none; cursor: pointer; font-family: inherit; font-size: 12.5px; font-weight: 800; display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 10px; background: transparent; color: #94a3b8; transition: all 0.16s; }
.plw-mode:hover { color: #4f46e5; }
.plw-mode.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 6px 16px rgba(99, 102, 241, 0.28); }
/* ── PENYARING ALUR ────────────────────────────────────────────────────────
   Duduk sebaris dengan pemilih Kanban/List, sebab keduanya menjawab
   pertanyaan yang sejenis: "papan ini menggambarkan apa".

   NAMANYA `plw-alurfil`, bukan `plw-alur`. Yang kedua sudah dipakai panel
   progres alur di drawer kandidat (lihat jauh di bawah), dan CSS memenangkan
   definisi TERAKHIR — penyaring ini sempat mewarisi `padding: 18px 20px`
   miliknya lalu tampil sebagai kotak gemuk yang teksnya tak terbaca sama
   sekali. Tabrakan nama kelas tidak memunculkan galat apa pun; ia cuma
   memberi tampilan milik orang lain. */
.plw-moderow__kiri { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; min-width: 0; }

.plw-alurfil {
    display: inline-flex; align-items: center; gap: 8px;
    /* Tidak boleh menciut: di dalam flex, kotak ini akan diperas lebih dulu
       daripada tetangganya dan menyisakan panah tanpa teks. */
    flex: 0 1 auto; min-width: 0;
    padding: 4px 6px 4px 5px; border-radius: 13px;
    background: #fff; border: 1px solid #e7e3fb;
    box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06);
}
.plw-alurfil__ico {
    flex: 0 0 auto; width: 30px; height: 30px; border-radius: 9px;
    display: inline-flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #ede9fe, #e0e7ff); color: #6d28d9; font-size: 14px;
}
.plw-alurfil__lbl { flex: 0 0 auto; font-size: 11px; font-weight: 900; letter-spacing: 0.1em; text-transform: uppercase; color: #94a3b8; }
.plw-alurfil__sel { width: 260px; min-width: 0; }
/* Menyatu dengan wadahnya — garis di dalam garis membuat kendali ini terlihat
   seperti dua kontrol yang bertumpuk. */
.plw-alurfil__sel :deep(.el-select__wrapper) {
    box-shadow: none !important; background: transparent;
    padding: 4px 6px; min-height: 30px; font-weight: 800; font-size: 12.5px; color: #4f46e5;
}
.plw-alurfil__sel :deep(.el-select__placeholder) { color: #4f46e5; font-weight: 800; }

/* Baris di dalam dropdown: nama alur mengambil sisa ruang, keterangannya
   menempel di kanan — jadi yang terpotong saat sempit adalah keterangan, bukan
   nama yang justru dipakai membedakan. */
.plw-alurfil__nama { font-weight: 700; }
.plw-alurfil__versi { margin-left: 6px; font-size: 10.5px; font-weight: 800; padding: 1px 6px; border-radius: 999px; background: #ede9fe; color: #6d28d9; }
.plw-alurfil__n { float: right; margin-left: 14px; font-size: 11.5px; font-weight: 700; color: #64748b; }
.plw-alurfil__n.is-nol { color: #cbd5e1; }

.plw-hasil { font-size: 12.5px; color: #64748b; margin-left: auto; }

/* Layar sempit: penyaring turun ke barisnya sendiri dan memakai lebar penuh —
   dipaksa tetap sebaris, kotaknya menyisakan ruang yang tak cukup untuk satu
   nama alur pun. */
@media (max-width: 880px) {
    .plw-moderow__kiri { width: 100%; }
    .plw-alurfil { flex: 1 1 100%; }
    .plw-alurfil__sel { width: auto; flex: 1 1 auto; }
    .plw-hasil { margin-left: 0; }
}

/* ═══ MODE LIST ═══ */
.plw-list { background: #fff; border: 1px solid #e7e3fb; border-radius: 16px; box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06); overflow: hidden; }
/* Kolom pertama = kotak centang pilih-banyak. Lebarnya tetap 26px: ia bukan
   data, cuma pegangan — kolom yang ikut melar akan menggeser seluruh tabel
   demi ruang yang tak dipakai apa pun. */
.plw-list__head,
.plw-lrow { display: grid; grid-template-columns: 26px 1.8fr 1.3fr 1.2fr 1.4fr 0.95fr 1fr 0.95fr 92px; gap: 12px; align-items: center; }
.plw-lcek { display: flex; align-items: center; justify-content: center; min-width: 0; }
/* NAMANYA `plw-tick`, BUKAN `plw-cek`. Kelas `plw-cek` sudah dipakai kartu
   pilihan formulir di modal cetak laporan — berkas yang sama, komponen yang
   lain. Definisi yang belakangan menang, jadi kotak centang ini memungut
   `padding: 9px 11px` + `border-radius: 10px` + `align-items: flex-start`
   miliknya: 19px berubah jadi gumpalan 37px yang membulat, dan centangnya
   terlempar ke pojok kiri-atas sampai tak terlihat. */
.plw-tick {
    appearance: none; cursor: pointer; flex: none; padding: 0;
    /* 18px = kelipatan genap, jadi tepinya jatuh tepat di batas piksel pada
       layar 1x. 19px membuat garis 1,6px-nya digambar setengah piksel dan
       terbaca kabur/cembung. */
    width: 18px; height: 18px;
    border-radius: 5px; border: 1.6px solid #cbd5e1; background: #fff;
    display: grid; place-items: center; color: #fff;
    transition: background .15s, border-color .15s, box-shadow .15s;
}
/* Ikon dipaksa jadi kotak setinggi glifnya sendiri. Bootstrap Icons menurunkan
   glif 0,125em lewat vertical-align agar sejajar teks di kalimat — di dalam
   kotak yang isinya HANYA ikon, geseran itu justru menjatuhkannya sekitar
   1,5px di bawah titik tengah. Terlihat jelas pada kotak 18px. */
.plw-tick > .bi { display: block; font-size: 11px; line-height: 1; }
.plw-tick > .bi::before { display: block; vertical-align: 0; line-height: 1; }
.plw-tick:hover { border-color: #a5b4fc; }
.plw-tick.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 2px 6px rgba(99, 102, 241, .35); }
.plw-tick.is-half { border-color: #a5b4fc; background: #eef2ff; color: #4f46e5; }
/* Garis "sebagian" digambar sendiri, bukan lewat glif bi-dash-lg: dash di font
   ini lebarnya mengikuti metrik huruf dan terbaca sebagai tanda minus yang
   melayang, bukan sebagai keadaan tengah sebuah kotak centang. */
.plw-tick.is-half > .bi { width: 9px; height: 2px; border-radius: 1px; background: #4f46e5; }
.plw-tick.is-half > .bi::before { content: none; }
.plw-lrow.is-terpilih { background: #f5f3ff; }
.plw-lrow.is-terpilih:hover { background: #ede9fe; }

/* Bilah aksi massal mode list — menempel di puncak tabel, muncul hanya saat
   ada yang tercentang. Sengaja BUKAN melayang di kaki layar: yang ditindak
   ada di tabel tepat di bawahnya, dan jaraknya ke tombol harus sependek
   mungkin agar keduanya terbaca sebagai satu hal. */
.plw-lsel {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    padding: 10px 16px; background: linear-gradient(180deg, #eef2ff, #f8f9ff);
    border-bottom: 1px solid #dfe3f7;
}
.plw-lsel__n { flex: none; display: grid; place-items: center; min-width: 26px; height: 24px; padding: 0 8px; border-radius: 8px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 12px; font-weight: 800; box-shadow: 0 4px 10px rgba(99, 102, 241, .3); }
.plw-lsel__txt { font-size: 12.5px; font-weight: 700; color: #4338ca; }
.plw-lsel__all { appearance: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #c7d2fe; background: #fff; padding: 7px 11px; border-radius: 9px; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 800; color: #4338ca; transition: all .16s; }
.plw-lsel__all:hover { background: #eef2ff; border-color: #a5b4fc; }
.plw-lsel__aksi { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-left: auto; }
.plw-lsel__aksi > button { font-size: 11.5px; padding: 8px 12px; border-radius: 9px; }
.plw-lsel__x { appearance: none; flex: none; border: 1px solid #d5dbf5; background: #fff; width: 28px; height: 28px; border-radius: 8px; cursor: pointer; color: #6366f1; display: grid; place-items: center; font-size: 11px; transition: all .16s; }
.plw-lsel__x:hover { color: #dc2626; border-color: #f4d0d0; background: #fef2f2; }
.plw-sel-enter-active, .plw-sel-leave-active { transition: opacity .18s, transform .18s; }
.plw-sel-enter-from, .plw-sel-leave-to { opacity: 0; transform: translateY(-6px); }
.plw-list__head { padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid #eef0f7; font-size: 9.5px; font-weight: 800; letter-spacing: 0.12em; color: #94a3b8; }
.plw-lrow { padding: 12px 16px; border-bottom: 1px solid #f4f5fb; cursor: pointer; transition: background 0.14s; animation: plwCardIn 0.3s ease both; }
.plw-lrow:last-child { border-bottom: none; }
.plw-lrow:hover { background: #fbfbfe; }
.plw-lrow.is-hold { background: #fffdf6; }
.plw-lrow.is-hold:hover { background: #fffaeb; }
.plw-lcell { min-width: 0; font-size: 12.5px; color: #475569; display: flex; align-items: center; gap: 8px; }
.plw-lcell--who { gap: 10px; }
.plw-lcell--act { justify-content: flex-end; gap: 6px; }
.plw-lname { display: block; font-size: 13px; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-lcode { display: block; font-size: 10.5px; font-family: 'JetBrains Mono', monospace; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-lpill { display: inline-block; max-width: 100%; font-size: 10.5px; font-weight: 700; padding: 4px 10px; border-radius: 8px; background: #f4f2ff; border: 1px solid #e7e3fb; color: #6d28d9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-ltgl { display: flex; flex-direction: column; line-height: 1.3; min-width: 0; }
.plw-ltgl small { font-size: 10.5px; color: #94a3b8; }
.plw-ldok { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 8px; background: #eef2ff; border: 1px solid #dbe2fe; color: #4338ca; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-ldok i { font-size: 11px; flex: 0 0 auto; }
.plw-ldok.is-kosong { background: #f8fafc; border-color: #eef0f7; color: #a2a9ba; }
.plw-ldetail { appearance: none; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 800; color: #4f46e5; background: #fff; border: 1px solid #d9def0; padding: 6px 12px; border-radius: 9px; flex: 0 0 auto; transition: all 0.16s; }
.plw-ldetail:hover { border-color: #a5b4fc; background: #eef2ff; }

/* ═══ KEADAAN KOSONG ═══ */
.plw-kosong { padding: 46px 24px; text-align: center; background: #fff; border: 1px dashed #d9def0; border-radius: 16px; }
.plw-kosong__ico { width: 54px; height: 54px; border-radius: 15px; margin: 0 auto; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #cbd5e1; font-size: 22px; }
.plw-kosong__judul { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 14px; }
.plw-kosong__sub { font-size: 12.5px; color: #64748b; margin-top: 6px; }
.plw-kosong__btn { appearance: none; border: none; cursor: pointer; font-family: inherit; margin-top: 16px; font-size: 13px; font-weight: 800; color: #fff; padding: 11px 20px; border-radius: 12px; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 10px 22px rgba(99, 102, 241, 0.28); }

/* ═══ PAGINASI MODE LIST ═══ */
.plw-pagerbar { display: flex; align-items: center; justify-content: space-between; gap: 13px; flex-wrap: wrap; margin-top: 14px; padding: 11px 16px; background: #fff; border: 1px solid #e7e3fb; border-radius: 16px; box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06); }
.plw-pagerbar__info { font-size: 12px; color: #64748b; min-width: 0; }
.plw-pagerbar__nav { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; justify-content: center; }
.plw-pagerbar__nav > button { appearance: none; cursor: pointer; min-width: 32px; height: 32px; padding: 0 9px; border-radius: 9px; border: 1px solid #e2e8f0; background: #fff; color: #475569; font-family: inherit; font-size: 12.5px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; transition: all 0.16s; }
.plw-pagerbar__nav > button:hover:not(:disabled) { border-color: #a5b4fc; color: #4f46e5; }
.plw-pagerbar__nav > button:disabled { color: #cbd5e1; cursor: not-allowed; }
.plw-pnum.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.plw-pnum.is-gap { border-color: transparent; background: transparent; color: #cbd5e1; cursor: default; }
.plw-pagerbar__per { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #64748b; }
.plw-perpage { width: 84px; }
.plw-tab.is-hold { background: rgba(100, 116, 139, 0.14); color: #475569; border-color: rgba(100, 116, 139, 0.3); }

/* ═══ IKON AKSI DI KEPALA KOLOM (saring & pilih-banyak) ═══ */
.plw-col__ico { flex: none; display: grid; place-items: center; width: 24px; height: 24px; border: 1px solid #e2e8f0; background: #fff; color: #94a3b8; font-size: 11px; border-radius: 7px; cursor: pointer; transition: all .15s; }
.plw-col__ico:hover { border-color: #a5b4fc; color: #6366f1; }
.plw-col__ico.is-on { border-color: #6366f1; background: #eef2ff; color: #4338ca; }
/* Kolom yang SEDANG disaring ditandai walau panelnya tertutup — kalau tidak,
   kolom yang isinya tinggal 2 dari 40 terbaca seolah memang cuma berisi 2. */
.plw-col__ico.is-aktif { border-color: #f59e0b; background: #fffbeb; color: #b45309; }

/* ═══ PANEL PENYARING KOLOM ═══ */
.plw-colf { display: flex; flex-direction: column; gap: 6px; margin-bottom: 9px; padding: 9px; border-radius: 11px; background: #f8fafc; border: 1px solid #e6e9f0; }
.plw-colf__reset { display: inline-flex; align-items: center; justify-content: center; gap: 5px; border: 1px dashed #cbd5e1; background: #fff; color: #64748b; font-size: 10.5px; font-weight: 700; border-radius: 7px; padding: 5px 8px; cursor: pointer; }
.plw-colf__reset:hover { border-color: #94a3b8; color: #475569; }
.plw-selbar { display: flex; flex-direction: column; gap: 7px; margin-bottom: 9px; padding: 7px; border-radius: 10px; background: #eef2ff; border: 1px solid #c7d2fe; }
.plw-selbar__head { display: flex; align-items: center; gap: 6px; }
.plw-selbar__all { display: inline-flex; align-items: center; gap: 4px; border: none; background: transparent; color: #4338ca; font-size: 10.5px; font-weight: 800; cursor: pointer; padding: 0; white-space: nowrap; }
.plw-selbar__n { display: grid; place-items: center; min-width: 20px; height: 19px; padding: 0 5px; border-radius: 6px; background: #4f46e5; color: #fff; font-size: 10px; font-weight: 800; }
/* Tombol aksi selebar panel dan berbagi rata. Jumlahnya berubah-ubah
   (Lanjutkan hanya muncul bila ada yang tertahan), jadi lebarnya ikut
   membagi diri sendiri alih-alih dipatok per tombol. */
.plw-selbar__aksi { display: flex; flex-wrap: wrap; gap: 6px; }
.plw-selbar__aksi > button { flex: 1 1 88px; min-width: 0; justify-content: center; }
.plw-selbar__go { display: inline-flex; align-items: center; gap: 4px; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 10.5px; font-weight: 800; border-radius: 7px; padding: 6px 9px; cursor: pointer; white-space: nowrap; }
.plw-selbar__go:disabled { opacity: .45; cursor: not-allowed; }
.plw-selbar__x { flex: none; margin-left: auto; border: none; background: transparent; color: #818cf8; font-size: 10px; cursor: pointer; padding: 2px; }
.plw-selbar__x:hover { color: #4338ca; }
.plw-card.is-pilih { cursor: pointer; }
.plw-card.is-terpilih { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.18); }
.plw-card__cek { flex: none; display: grid; place-items: center; width: 18px; color: #6366f1; font-size: 14px; }
/* TAHAN — ikon di pojok kartu, TAPI berwarna sejak awal.
   Versi sebelumnya transparan/abu pucat dan baru muncul saat kartu di-hover:
   di layar terang ia praktis tak terlihat, jadi jalan keluar yang paling
   dibutuhkan justru yang paling sulit ditemukan. Kini bernada amber (=
   "tunda") yang kontras dengan kartu putih tanpa berteriak seperti tombol
   keputusan. */
.plw-card__hold { flex: none; display: grid; place-items: center; width: 28px; height: 28px; border: 1px solid #fcd34d; background: #fffbeb; color: #b45309; font-size: 13px; border-radius: 8px; cursor: pointer; transition: all .15s; }
.plw-card__hold:hover { background: #fef3c7; border-color: #f59e0b; color: #92400e; transform: scale(1.06); }
/* SEDANG DITAHAN → hijau "lanjutkan": tindakannya berlawanan, jadi warnanya
   pun harus berlawanan — bukan sekadar ikon yang berganti. */
.plw-card__hold.is-on { border-color: #86efac; background: #f0fdf4; color: #15803d; }
.plw-card__hold.is-on:hover { background: #dcfce7; border-color: #4ade80; color: #166534; }
/* Kartu yang sedang ditahan diberi garis kiri — terbaca sekilas tanpa
   menambah satu baris teks pun ke dalam kartunya. */
.plw-card.is-hold { border-left: 3px solid #94a3b8; background: #fbfcfd; }

/* Pratinjau jadwal massal — siapa dapat jam berapa, sebelum email terkirim. */
.plw-prev { margin-top: 12px; padding: 10px 12px; border-radius: 11px; background: #f8fafc; border: 1px solid #e6e9f0; }
.plw-prev__head { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-size: 11.5px; font-weight: 800; color: #334155; }
.plw-prev__head i { color: #6366f1; }
.plw-prev__list { max-height: 190px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; }
.plw-prev__row { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #475569; background: #fff; border: 1px solid #eef1f6; border-radius: 7px; padding: 5px 9px; }
.plw-prev__row b { margin-left: auto; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; color: #4338ca; white-space: nowrap; }
.plw-prev__no { flex: none; display: grid; place-items: center; width: 18px; height: 18px; border-radius: 5px; background: #eef2ff; color: #4f46e5; font-size: 9.5px; font-weight: 800; }
/* DITAHAN — abu kebiruan: bukan peringatan (tak ada yang salah), bukan pula
   ajakan bertindak (justru sengaja ditunda). */
.plw-card__chip.tone-hold { background: rgba(100, 116, 139, 0.16); color: #475569; }

/* ═══ TAHAN (HOLD) di footer drawer ═══ */
.plw-hold { margin-bottom: 10px; padding: 10px 12px; border-radius: 12px; background: rgba(100, 116, 139, 0.08); border: 1px solid rgba(100, 116, 139, 0.25); }
.plw-hold__top { display: flex; align-items: flex-start; gap: 9px; }
.plw-hold__ico { flex: none; display: grid; place-items: center; width: 28px; height: 28px; border-radius: 9px; background: rgba(100, 116, 139, 0.16); color: #475569; font-size: 15px; }
.plw-hold__top b { display: block; font-size: 12.5px; font-weight: 800; color: #334155; }
.plw-hold__top small { display: block; margin-top: 2px; font-size: 11px; line-height: 1.5; color: #64748b; }
.plw-hold__lepas { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #86efac; background: #f0fdf4; color: #15803d; font-size: 11.5px; font-weight: 800; border-radius: 8px; padding: 6px 11px; cursor: pointer; }
.plw-hold__lepas:hover { background: #dcfce7; }
.plw-holdsisa { display: flex; align-items: center; gap: 7px; margin: 9px 0 0; padding: 8px 11px; border-radius: 9px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 11.5px; line-height: 1.5; color: #64748b; }
.plw-holdsisa .bi { flex: none; color: #94a3b8; }
.plw-holdsisa b { color: #334155; }

/* ═══ SISA SLA MPP (kepala worklist) ═══
   Empat nada, dan warnanya sengaja menanjak: rekruter yang melihat papan
   sepintas harus bisa membedakan "masih lama" dari "hari ini" tanpa membaca
   angkanya. */
/* ══ TENGGAT SLA MPP ══════════════════════════════════════════════════════
   Dulu sebuah pita berwarna selebar kepala halaman: latar pastel penuh, teks
   satu baris, angkanya terbenam di tengah kalimat. Dua hal yang diperbaiki:

   1. ANGKANYA jadi tokoh utama, dipisah ke kolomnya sendiri. Yang dicari mata
      saat melintas adalah "berapa lagi" - membacanya seharusnya tidak menuntut
      membaca kalimat lebih dulu.
   2. WARNA dipakai seperlunya. Latar pastel selebar halaman membuat keadaan
      "aman" berteriak sekeras keadaan "genting"; padahal yang aman justru tidak
      perlu diperhatikan. Sekarang latarnya nyaris putih dan yang berwarna hanya
      pilar kiri, angka, serta bilahnya - jadi merah benar-benar berarti merah.

   Warna disimpan sebagai token per nada, bukan ditulis ulang di tiap aturan:
   menambah satu nada baru cukup satu blok. */
.plw-sla {
    --sla-warna: #10b981;
    --sla-lembut: #ecfdf5;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    margin-top: 9px;
    padding: 9px 13px 9px 15px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #eaecf3;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

/* Pilar kiri - penanda keadaan yang terbaca dari sudut mata tanpa mewarnai
   seluruh kotaknya. */
.plw-sla::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--sla-warna);
}

.plw-sla__num {
    flex: 0 0 auto;
    display: flex;
    align-items: baseline;
    gap: 5px;
    padding-right: 12px;
    border-right: 1px solid #f1f2f7;
}

.plw-sla__num b {
    font-size: 21px;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.03em;
    color: var(--sla-warna);
    font-variant-numeric: tabular-nums;
}

.plw-sla__num em {
    font-style: normal;
    font-size: 8.5px;
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #9aa2b4;
}

.plw-sla__isi { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }

.plw-sla__judul {
    font-size: 12px;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.plw-sla__ket {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 10.5px;
    font-weight: 600;
    color: #98a1b3;
    min-width: 0;
}

.plw-sla__tgl,
.plw-sla__ext { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
.plw-sla__tgl .bi,
.plw-sla__ext .bi { font-size: 10.5px; }

/* Titik pemisah - lebih tenang daripada tanda baca, dan tidak ikut terbaca
   sebagai bagian dari tanggalnya. */
.plw-sla__pisah {
    flex: 0 0 auto;
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: #d5d9e2;
}

.plw-sla__ext { color: #7c6bb8; cursor: help; }

.plw-sla__bar {
    flex: 0 0 auto;
    width: 64px;
    height: 4px;
    border-radius: 999px;
    background: var(--sla-lembut);
    overflow: hidden;
}

.plw-sla__bar > span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: var(--sla-warna);
    transition: width 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}

.plw-sla.is-aman     { --sla-warna: #10b981; --sla-lembut: #ecfdf5; }
.plw-sla.is-waspada  { --sla-warna: #d19a1a; --sla-lembut: #fefce8; }
.plw-sla.is-genting  { --sla-warna: #ea7317; --sla-lembut: #fff7ed; }
.plw-sla.is-hari-ini { --sla-warna: #e0484b; --sla-lembut: #fef2f2; }

/* Lewat tenggat: satu-satunya keadaan yang boleh mewarnai seluruh kotaknya.
   Ia bukan lagi hitung mundur, melainkan janji yang sudah terlewat. */
.plw-sla.is-lewat {
    --sla-warna: #dc2626;
    --sla-lembut: #fee2e2;
    background: #fffbfb;
    border-color: #fbd5d5;
}

.plw-sla.is-lewat .plw-sla__judul { color: #b91c1c; }
.plw-sla.is-lewat .plw-sla__num { border-right-color: #fbd5d5; }

/* Yang sudah lewat berdenyut pelan - bukan animasi hiasan: ia satu-satunya
   keadaan yang menuntut tindakan hari itu juga. */
.plw-sla.is-lewat::before { animation: plwSlaDenyut 2.4s ease-in-out infinite; }

@keyframes plwSlaDenyut {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.35; }
}

@media (prefers-reduced-motion: reduce) {
    .plw-sla.is-lewat::before { animation: none; }
    .plw-sla__bar > span { transition: none; }
}

@media (max-width: 640px) {
    .plw-sla__bar { display: none; }
}
.plw-hold__cat { margin: 8px 0 0; padding-top: 8px; border-top: 1px dashed rgba(100, 116, 139, 0.3); font-size: 11.5px; line-height: 1.55; color: #475569; }

/* ── TAHAN MASSAL ────────────────────────────────────────────────────────── */
/* Tombol di bilah pilih-banyak. Warnanya BUKAN ungu seperti "Jadwalkan":
   menjadwalkan mendorong proses maju, menahan justru menghentikannya — dua
   arah berlawanan yang tidak boleh terbaca sama saat dipindai cepat. */
.plw-selbar__hold,
.plw-selbar__lepas {
    appearance: none; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800;
    display: inline-flex; align-items: center; gap: 5px; padding: 6px 9px;
    white-space: nowrap; border-radius: 7px; border: 1px solid transparent; transition: all .16s;
}
/* Keputusan massal — indigo, sekeluarga dengan tombol keputusan di drawer.
   Sengaja BUKAN hijau/merah: satu tombol ini membuka pilihan Lolos MAUPUN
   Tidak Lolos, jadi mewarnainya seperti salah satunya menyesatkan sebelum
   admin sempat memilih. */
.plw-selbar__putus {
    appearance: none; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800;
    display: inline-flex; align-items: center; gap: 5px; padding: 6px 9px;
    white-space: nowrap; border-radius: 7px; transition: all .16s;
    background: #eef2ff; border: 1px solid #c7d2fe; color: #4338ca;
}
.plw-selbar__putus:hover:not(:disabled) { background: #e0e7ff; border-color: #a5b4fc; }
.plw-selbar__putus:disabled { opacity: .45; cursor: not-allowed; }

.plw-selbar__hold { background: #fff7ed; border-color: #fed7aa; color: #b45309; }
.plw-selbar__hold:hover:not(:disabled) { background: #ffedd5; border-color: #fdba74; }
.plw-selbar__lepas { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
.plw-selbar__lepas:hover { background: #d1fae5; border-color: #6ee7b7; }
.plw-selbar__hold:disabled { opacity: .45; cursor: not-allowed; }

/* SEBAB TOMBOL MATI — kalimatnya, bukan sekadar warna kelabu.
   Kuning-jingga, bukan merah: ini pembatas yang bekerja sebagaimana mestinya,
   bukan kesalahan yang harus diperbaiki. Merah di bilah yang sama dengan tombol
   "Tahan" jingga juga akan terbaca sebagai galat pada tombol itu. */
.plw-batas {
    display: flex; align-items: flex-start; gap: 7px;
    margin: 8px 0 0; padding: 8px 10px; border-radius: 9px;
    background: #fffbeb; border: 1px solid #fde68a;
    font-size: 10.5px; line-height: 1.5; font-weight: 600; color: #92400e;
}
.plw-batas > .bi { flex: none; font-size: 12px; color: #d97706; margin-top: 1px; }
/* Bilah daftar tersusun mendatar & membungkus; tanpa basis penuh, kalimat
   sepanjang ini terjepit di sela tombol dan terpotong jadi satu kata per baris. */
.plw-batas--lsel { flex-basis: 100%; margin-top: 2px; }

/* Jalan pintas "salin ke semua". */
.plw-hmbar {
    display: flex; align-items: center; gap: 9px; flex-wrap: wrap;
    margin: 4px 0 10px; padding: 9px 12px; border-radius: 11px;
    background: #f5f3ff; border: 1px solid #ddd6fe;
    font-size: 11.5px; line-height: 1.5; color: #5b21b6;
}
.plw-hmbar > .bi { flex: none; font-size: 14px; color: #7c3aed; }
.plw-hmbar span { flex: 1; min-width: 140px; }
.plw-hmbar button {
    appearance: none; cursor: pointer; font: inherit; font-size: 11px; font-weight: 800;
    display: inline-flex; align-items: center; gap: 5px; padding: 5px 11px;
    border-radius: 999px; border: 1px solid #c4b5fd; background: #fff; color: #6d28d9;
    transition: all .16s; flex: none;
}
.plw-hmbar button:hover:not(:disabled) { background: #ede9fe; border-color: #a78bfa; }
.plw-hmbar button:disabled { opacity: .45; cursor: not-allowed; }

/* Daftar per kandidat — DIGULUNG DI DALAM, bukan memanjangkan modal.
   Dua puluh kandidat tanpa batas tinggi membuat tombol Simpan terdorong jauh
   di luar layar, dan admin harus menggulung modal setiap kali memeriksanya. */
/* Tinggi diukur dari layar, bukan dari angka tetap: tiap baris kini membawa
   penyunting sendiri, dan 320px yang dulu memuat empat baris textarea hanya
   memuat satu setengah baris sekarang. */
.plw-hmlist { max-height: 44vh; overflow-y: auto; overscroll-behavior: contain; padding-right: 4px; display: flex; flex-direction: column; gap: 8px; }
.plw-hmlist::-webkit-scrollbar { width: 6px; }
.plw-hmlist::-webkit-scrollbar-thumb { background: #d9def0; border-radius: 999px; }
.plw-hmrow { padding: 10px 12px; border: 1px solid #e6e9f3; border-radius: 12px; background: #fbfbfe; }
/* Baris yang belum lengkap ditandai merah DI TEMPATNYA — pada daftar dua
   puluh baris, pesan galat tunggal di bawah tidak memberi tahu yang mana. */
.plw-hmrow.is-kurang { border-color: #fecaca; background: #fef2f2; }
.plw-hmrow__head { display: flex; align-items: center; gap: 9px; margin-bottom: 8px; }
.plw-hmrow__n { flex: none; width: 20px; height: 20px; display: grid; place-items: center; border-radius: 999px; background: #e0e7ff; color: #4338ca; font-size: 10.5px; font-weight: 800; }
.plw-hmrow__head b { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.plw-hmrow__head small { display: block; font-size: 10.5px; color: #94a3b8; margin-top: 1px; }
.plw-hmrow__warn { flex: none; display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 800; color: #dc2626; }
.plw-hmrow__sel { width: 100%; }
/* Keputusan di kiri, alasannya di kanan — sepasang, karena begitulah ia
   dibaca kembali nanti. Bertumpuk, satu baris kandidat jadi setinggi tiga
   baris dan dua puluh kandidat menuntut penggulungan yang tak ada ujungnya. */
.plw-hmrow__isi { display: grid; grid-template-columns: minmax(0, 208px) minmax(0, 1fr); gap: 10px; align-items: start; }

@media (max-width: 860px) {
    .plw-hmrow__isi { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 640px) {
    /* `.plw-opts--row { flex-direction: column }` yang dulu di sini DIHAPUS.
       Media query tidak menambah kekhususan, dan aturan `--row` yang sebenarnya
       berada jauh di bawah berkas ini — jadi ia tetap menang dan baris ini tak
       pernah berlaku. Penumpukan di layar sempit kini datang dari
       `min-width: 190px` + `flex-wrap`, yang bekerja tanpa bergantung urutan. */
    .plw-hmlist { max-height: 52vh; }
}

/* Keterangan "tidak berwenang" — biru keterangan, BUKAN merah galat. Ini
   bukan kesalahan yang dibuat admin, melainkan batas wewenang akunnya; warna
   merah membuatnya terbaca seolah ada yang rusak. */
.plw-nogate {
    display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px;
    padding: 11px 13px; border-radius: 12px;
    background: #eff6ff; border: 1px solid #bfdbfe;
    font-size: 12px; line-height: 1.55; color: #1e40af;
}
.plw-nogate .bi { flex: none; margin-top: 1px; font-size: 14px; color: #2563eb; }
.plw-nogate b { color: #1e3a8a; }
.plw-nogate span { display: block; margin-top: 3px; color: #3b82f6; }

/* Keterangan "master kosong" — kuning peringatan, bukan biru keterangan:
   berbeda dari batas wewenang, keadaan ini MEMANG perlu dibereskan, dan
   yang membereskannya bukan admin yang sedang menatap layar ini. */
.plw-nomaster {
    display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px;
    padding: 11px 13px; border-radius: 12px;
    background: #fffbeb; border: 1px solid #fde68a;
    font-size: 12px; line-height: 1.55; color: #92400e;
}
.plw-nomaster .bi { flex: none; margin-top: 1px; font-size: 14px; color: #d97706; }
.plw-nomaster b { color: #78350f; }
.plw-nomaster span { display: block; margin-top: 3px; color: #b45309; }

/* Aktivitas yang belum tiba gilirannya (tahap BERURUTAN). */
.plw-test.is-terkunci { opacity: 0.72; }
/* LANJUTKAN — ungu: bukan penilaian, melainkan membuka jalan. */
.plw-test__lanjut { display: inline-flex; align-items: center; gap: 5px; border: 1px solid #c4b5fd; background: #f5f3ff; color: #6d28d9; font-size: 11px; font-weight: 800; border-radius: 8px; padding: 5px 10px; cursor: pointer; }
.plw-test__lanjut:hover:not(:disabled) { background: #ede9fe; border-color: #a78bfa; }
.plw-test__lanjut:disabled { opacity: .6; cursor: not-allowed; }
.plw-test__gembok { display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; border-radius: 7px; font-size: 11px; font-weight: 700; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; }
.plw-test__gembok b { color: #334155; }
.plw-test__tag.is-internal { background: rgba(100, 116, 139, 0.14); color: #475569; }

/* ═══ MODAL DETAIL — HERO & TAB ═══
   Hero menempel di puncak isi yang menggulung. Nama orang yang sedang
   diputuskan tidak boleh hilang dari layar: rapor tes bisa sepanjang enam
   aktivitas, dan siapa pun yang menggulung sampai dasar lalu menekan
   "Tidak Lolos" berhak tahu ia sedang menggugurkan siapa. */
/* Penempelannya diurus `.wca-modal__stickybar` (slot `sticky` AdminModal); di
   sini hanya rupa & padding dalamnya. Padding mendatar mengikuti variabel milik
   isi modal supaya hero tetap sejajar dengan isi di bawahnya di tiap lebar
   layar — bukan angka terpisah yang harus diingat ikut diubah. */
.plw-hero { padding: 18px var(--wca-modal-pad, 1.35rem) 0; background: linear-gradient(135deg, #f4f2ff 0%, #eef2ff 52%, #eaf1ff 100%); border-bottom: 1px solid #e4e7f5; }
.plw-hero__row { display: flex; align-items: flex-start; gap: 14px; flex-wrap: wrap; }
.plw-hero__avatar { width: 54px; height: 54px; border-radius: 16px; color: #fff; font-size: 17px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.plw-hero__tags { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-bottom: 7px; }
.plw-hero__name { font-size: 20px; font-weight: 800; color: #1e1b4b; letter-spacing: -0.02em; line-height: 1.2; text-wrap: pretty; }

/* Tab modal — nada sama dengan pemilih mode di papan, jadi keduanya terbaca
   sebagai "pemilih tampilan", bukan sebagai tombol tindakan. */
.plw-mtabs { display: flex; gap: 6px; margin-top: 14px; overflow-x: auto; padding-bottom: 12px; }
.plw-mtab { appearance: none; border: 1px solid rgba(255, 255, 255, 0.85); cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 7px; padding: 9px 14px; border-radius: 11px; font-size: 12.5px; font-weight: 800; white-space: nowrap; background: rgba(255, 255, 255, 0.62); color: #5b5486; transition: all 0.16s; }
.plw-mtab:hover { background: #fff; }
.plw-mtab.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28); }
.plw-mtab__n { display: inline-grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; font-size: 10px; background: rgba(99, 102, 241, 0.14); color: #4338ca; }
.plw-mtab.is-on .plw-mtab__n { background: rgba(255, 255, 255, 0.26); color: #fff; }
.plw-tabpane { display: flex; flex-direction: column; gap: 18px; }

/* ═══ PEMBACA BERKAS — KOLOM KANAN SETINGGI MODAL ════════════════════════════
   Duduk di slot `aside` AdminModal, jadi ia berdiri dari bawah kepala modal
   sampai dasar jendela — melewati kaki, bukan berhenti di atasnya. Kaki modal
   sekarang berada di dalam kolom KIRI (lihat .wca-modal__col), sebab isinya
   tombol keputusan milik panel kiri; kaki yang membentang penuh dulu memotong
   kolom ini dan pratinjaunya berhenti beberapa ratus piksel di atas dasar.

   Tiga lapis pertama berukuran tetap, lapis keempat (bidang tampil) mengambil
   seluruh sisanya. Itu urutan kepentingannya: judul, penyaring, dan jalur
   lembar semuanya cuma alat untuk SAMPAI ke lembarnya. */
.plw-dok { display: flex; flex-direction: column; height: 100%; min-height: 0; background: #fff; overflow: hidden; }

/* ── BILAH JUDUL ── */
.plw-dok__head { display: flex; align-items: center; gap: 9px; padding: 11px 10px 11px 13px; border-bottom: 1px solid #eef0f7; background: linear-gradient(135deg, #f7f5ff, #eef2ff); flex: 0 0 auto; }
.plw-dok__headico { width: 32px; height: 32px; border-radius: 10px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 15px; box-shadow: 0 8px 18px rgba(99, 102, 241, 0.26); }
.plw-dok__headtxt { flex: 1; min-width: 0; }
.plw-dok__headtxt b { display: block; font-size: 12.5px; font-weight: 800; color: #1e1b4b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-dok__headtxt em { display: block; font-size: 10.5px; font-style: normal; color: #8b8bb0; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-dok__hbtn { appearance: none; border: 1px solid #e2ddf9; background: rgba(255, 255, 255, 0.82); cursor: pointer; font-family: inherit; flex: 0 0 auto; width: 28px; height: 28px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 11.5px; color: #7c7ca8; text-decoration: none; transition: all 0.16s; }
.plw-dok__hbtn:hover { background: #fff; color: #4f46e5; border-color: #c7d2fe; }

/* ── JALUR LEMBAR + TOMBOL PENYARING — SATU BARIS ──
   Tingginya tetap dan kecil dengan sengaja: ini daftar untuk BERPINDAH, bukan
   untuk dibaca. Tiap baris yang jalur ini ambil ke bawah diambil langsung dari
   tinggi pratinjau, dan pratinjau itulah satu-satunya bagian yang sebenarnya
   ingin dilihat orang.

   Tombol penyaring duduk SEBARIS di ujung kiri, tidak di bilahnya sendiri.
   Bentuk sebelumnya menaruh penyaring sebagai bilah penuh di atas jalur ini:
   dengan bilah judul di atasnya dan bilah alat bawaan PDF di bawahnya, empat
   bilah bertumpuk memakan hampir sepertiga tinggi kolom sebelum satu baris
   dokumen pun terbaca. */
.plw-dok__strip { flex: 0 0 auto; display: flex; align-items: stretch; gap: 8px; padding: 9px 11px; border-bottom: 1px solid #f1f5f9; background: #fbfbfe; }
.plw-dok__striplist { flex: 1 1 auto; min-width: 0; display: flex; gap: 8px; overflow-x: auto; scrollbar-width: thin; }
.plw-dok__saring { position: relative; appearance: none; border: 1px solid #e6e3f7; background: #fff; cursor: pointer; font-family: inherit; flex: 0 0 auto; width: 36px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #6b6b95; transition: all 0.16s; }
.plw-dok__saring:hover { background: #f4f2ff; color: #4f46e5; border-color: #c7d2fe; }
.plw-dok__saring.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
/* Titik kecil = ada penyaring yang menyala. Tanpa penanda ini, jalur lembar
   yang terpotong terbaca sebagai berkas yang hilang, bukan yang disembunyikan. */
.plw-dok__saring em { position: absolute; top: 5px; right: 5px; width: 7px; height: 7px; border-radius: 999px; background: #f59e0b; box-shadow: 0 0 0 2px #fff; }
.plw-dok__stripkosong { flex: 1 1 auto; min-width: 0; display: flex; align-items: center; gap: 9px; padding: 0 4px; font-size: 11.5px; color: #94a3b8; }
.plw-dok__stripkosong > i { font-size: 13px; color: #c4b5fd; flex: 0 0 auto; }
.plw-dok__stripkosong > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plw-dok__stripkosong > button { appearance: none; border: 1px solid #c7d2fe; background: #fff; cursor: pointer; font-family: inherit; flex: 0 0 auto; margin-left: auto; padding: 5px 11px; border-radius: 8px; font-size: 11px; font-weight: 800; color: #4f46e5; }
.plw-dok__stripkosong > button:hover { background: #f4f2ff; }

.plw-dok__sitem { appearance: none; border: 1px solid #edeaf9; background: #fff; cursor: pointer; font-family: inherit; flex: 0 0 auto; width: 76px; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 6px 5px; border-radius: 11px; transition: transform 0.16s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.16s, border-color 0.16s; }
.plw-dok__sitem:hover { transform: translateY(-2px); border-color: #c7d2fe; box-shadow: 0 10px 22px rgba(99, 102, 241, 0.16); }
.plw-dok__sitem.is-on { border-color: #6366f1; background: #f6f5ff; box-shadow: 0 10px 22px rgba(99, 102, 241, 0.2); }
.plw-dok__sthumb { position: relative; width: 100%; height: 46px; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #eef2ff; border: 1px solid #dfe4fd; color: #4f46e5; font-size: 18px; }
.plw-dok__sthumb.is-img { background: #ecfdf5; border-color: #b6f0d5; color: #059669; }
.plw-dok__sthumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
/* Lencana jenis menumpang di sudut petak. Pada petak selebar 76px satu baris
   teks sendiri untuk "PDF" memakan seperlima tingginya. */
.plw-dok__sext { position: absolute; right: 3px; bottom: 3px; font-style: normal; font-size: 7.5px; font-weight: 800; letter-spacing: 0.04em; padding: 1px 4px; border-radius: 4px; background: rgba(30, 27, 49, 0.72); color: #fff; }
.plw-dok__sname { width: 100%; font-size: 9px; font-weight: 700; color: #64748b; line-height: 1.25; text-align: center; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.plw-dok__sitem.is-on .plw-dok__sname { color: #4338ca; font-weight: 800; }

/* ── LACI PENYARING ──
   Turun di bawah jalur lembar, dan hanya selama dipakai. */
.plw-dok__filter { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; padding: 9px 11px; border-bottom: 1px solid #f1f5f9; background: #f7f6fd; flex: 0 0 auto; }
.plw-dok__cari { position: relative; flex: 1 1 150px; min-width: 120px; display: flex; align-items: center; }
.plw-dok__cari > i { position: absolute; left: 10px; font-size: 11px; color: #a5a5c4; pointer-events: none; }
.plw-dok__cari input { width: 100%; height: 32px; padding: 0 28px 0 28px; border-radius: 9px; border: 1px solid #e6e3f7; background: #fff; font-size: 11.5px; color: #334155; outline: none; font-family: inherit; transition: all 0.16s; }
.plw-dok__cari input:focus { border-color: #a5b4fc; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12); }
.plw-dok__cari > button { position: absolute; right: 7px; appearance: none; border: none; background: transparent; cursor: pointer; color: #a5a5c4; font-size: 9.5px; padding: 4px; display: flex; }
.plw-dok__cari > button:hover { color: #4f46e5; }
.plw-dok__urut { appearance: none; border: 1px solid #e6e3f7; background: #fff; cursor: pointer; font-family: inherit; flex: 0 0 auto; height: 32px; padding: 0 10px; border-radius: 9px; display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; color: #6b6b95; transition: all 0.16s; }
.plw-dok__urut:hover { background: #f4f2ff; color: #4f46e5; border-color: #c7d2fe; }
.plw-dok__urut i { font-size: 13px; }
.plw-dok__tabs { flex: 1 1 100%; min-width: 0; display: flex; gap: 5px; overflow-x: auto; scrollbar-width: none; }
.plw-dok__tabs::-webkit-scrollbar { display: none; }
.plw-dok__tab { appearance: none; border: 1px solid #ece9fb; background: #fff; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px; height: 30px; padding: 0 9px; border-radius: 9px; font-size: 11px; font-weight: 800; white-space: nowrap; color: #6b6b95; flex: 0 0 auto; transition: all 0.16s; }
.plw-dok__tab i { font-size: 11px; }
.plw-dok__tab:hover { background: #f4f2ff; color: #4f46e5; }
.plw-dok__tab.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 6px 16px rgba(99, 102, 241, 0.26); }
.plw-dok__tab em { font-style: normal; font-size: 9.5px; font-weight: 800; padding: 1px 5px; border-radius: 999px; background: rgba(99, 102, 241, 0.12); color: #4338ca; }
.plw-dok__tab.is-on em { background: rgba(255, 255, 255, 0.26); color: #fff; }
/* ── BIDANG TAMPIL ──
   Latar gelap sengaja: lembar pindaian hampir selalu putih, dan di atas latar
   putih tepinya lenyap — halaman jadi tak jelas berhenti di mana. */
.plw-dok__view { flex: 1 1 auto; min-height: 0; display: flex; align-items: center; justify-content: center; background: #1e1b31; overflow: hidden; position: relative; }
.plw-dok__pdf { width: 100%; height: 100%; border: 0; background: #fff; }
.plw-dok__img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
.plw-dok__state { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 24px 18px; text-align: center; font-size: 12px; color: #b9b6d6; }
.plw-dok__bigico { width: 52px; height: 52px; border-radius: 15px; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.1); color: #b9b6d6; font-size: 24px; }
.plw-dok__retry { appearance: none; border: 1px solid rgba(255, 255, 255, 0.24); background: rgba(255, 255, 255, 0.1); cursor: pointer; font-family: inherit; padding: 6px 14px; border-radius: 9px; font-size: 11.5px; font-weight: 800; color: #e9e7fa; text-decoration: none; }
.plw-dok__retry:hover { background: rgba(255, 255, 255, 0.18); }

/* ═══ TAB ALUR SELEKSI ═══ */
.plw-alur { background: #fff; border: 1px solid #eef0f7; border-radius: 18px; padding: 18px 20px; }
.plw-alur__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
/* Catatan untuk kandidat di bawah tahapnya. Ungu = sudah sampai ke kandidat;
   kuning = masih tertahan aturan pengumuman. */
.plw-alur__ce { margin: 8px 0 2px; padding: 9px 11px; border-radius: 12px; background: #f5f3ff; border: 1px solid #ddd6fe; }
.plw-alur__ce.is-tunggu { background: #fffbeb; border-color: #fde68a; }
.plw-alur__cehead { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 7px; margin-bottom: 5px; font-size: 11px; font-weight: 800; color: #6d28d9; }
.plw-alur__ce.is-tunggu .plw-alur__cehead { color: #b45309; }
.plw-alur__cehead em { font-style: normal; font-weight: 700; color: #94a3b8; }
.plw-alur__lbl { font-size: 11px; font-weight: 800; letter-spacing: 0.12em; color: #8b93a7; }
.plw-alur__pos { font-size: 13px; font-weight: 800; color: #4f46e5; }
.plw-alur__bar { height: 8px; border-radius: 99px; background: #eef0f7; overflow: hidden; margin-bottom: 20px; }
.plw-alur__bar > div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); transition: width 0.5s; }
.plw-alur__line { list-style: none; margin: 0; padding: 0 0 0 30px; position: relative; display: flex; flex-direction: column; gap: 14px; }
.plw-alur__line::before { content: ''; position: absolute; left: 13px; top: 8px; bottom: 8px; width: 2px; background: #eef0f7; }
.plw-alur__item { position: relative; }
.plw-alur__node { position: absolute; left: -30px; top: 0; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; border: 3px solid #cbd2e0; }
.plw-alur__node > span { width: 9px; height: 9px; border-radius: 50%; background: #cbd2e0; display: block; }
.plw-alur__item.is-lewat .plw-alur__node { border-color: #10b981; }
.plw-alur__item.is-lewat .plw-alur__node > span { background: #10b981; }
.plw-alur__item.is-kini .plw-alur__node { border-color: #f59e0b; animation: plwPulse 2.2s infinite; }
.plw-alur__item.is-kini .plw-alur__node > span { background: #f59e0b; }
.plw-alur__item.is-tutup .plw-alur__node { border-color: #ef4444; }
.plw-alur__item.is-tutup .plw-alur__node > span { background: #ef4444; }
@keyframes plwPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); }
    70% { box-shadow: 0 0 0 7px rgba(245, 158, 11, 0); }
}
.plw-alur__isi { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.plw-alur__nama { font-size: 13.5px; font-weight: 800; color: #94a3b8; }
.plw-alur__item.is-lewat .plw-alur__nama, .plw-alur__item.is-kini .plw-alur__nama, .plw-alur__item.is-tutup .plw-alur__nama { color: #1e293b; }
.plw-alur__ket { font-size: 11.5px; color: #8792a6; margin-top: 2px; }
.plw-alur__tag { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 4px 9px; border-radius: 999px; white-space: nowrap; background: #eef0f7; color: #94a3b8; }
.plw-alur__item.is-lewat .plw-alur__tag { background: rgba(16, 185, 129, 0.12); color: #059669; }
.plw-alur__item.is-kini .plw-alur__tag { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.plw-alur__item.is-tutup .plw-alur__tag { background: rgba(239, 68, 68, 0.12); color: #dc2626; }

.plw-drawer__meta { font-size: 12.5px; color: #6b6597; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
/* Dulu ditulis dua kali berturut-turut, beda hanya pada `background` — yang
   pertama tidak pernah terpakai sedetik pun. */
.plw-drawer__akun { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; font-size: 11px; font-weight: 600; color: #64748b; background: #f1f5f9; border-radius: 7px; padding: 2px 8px; }
.plw-drawer__chips { display: flex; flex-wrap: wrap; gap: 5px 10px; margin-top: 7px; font-size: 11px; color: #94a3b8; }
.plw-drawer__chips span, .plw-drawer__chips a { display: inline-flex; align-items: center; gap: 4px; }
.plw-drawer__chips .is-mpp { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; color: #64748b; background: #f1f5f9; padding: 1px 6px; border-radius: 4px; }
.plw-drawer__chips .is-link { color: #6366f1; font-weight: 600; text-decoration: none; }
.plw-drawer__chips .is-link:hover { text-decoration: underline; }
.plw-drawer__tag { display: inline-block; padding: 6px 12px; border-radius: 999px; color: #fff; font-size: 11.5px; font-weight: 800; white-space: nowrap; }
.plw-drawer__tag.is-mt { background: linear-gradient(135deg, #fbbf24, #f59e0b); box-shadow: 0 6px 16px rgba(245, 158, 11, 0.34); }
.plw-drawer__tag.is-rek { background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 6px 16px rgba(99, 102, 241, 0.34); }
/* CETAK — tindakan sekunder di kepala drawer: jelas terlihat, tapi tidak
   berebut perhatian dengan tombol keputusan di kaki. */
.plw-drawer__cetak { display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 13px; border: 1px solid #c7d2fe; background: #eef2ff; color: #4338ca; font-size: 12px; font-weight: 800; border-radius: 11px; cursor: pointer; transition: all .16s; }
.plw-drawer__cetak:hover { background: #e0e7ff; border-color: #a5b4fc; }
/* Pilihan formulir & tautan unduh di modal cetak. */
.plw-cek { display: flex; align-items: flex-start; gap: 9px; padding: 9px 11px; margin-bottom: 6px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; cursor: pointer; }
.plw-cek:hover { border-color: #a5b4fc; }
.plw-cek input { margin-top: 2px; width: 15px; height: 15px; accent-color: #4f46e5; flex: none; cursor: pointer; }
.plw-cek b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; }
.plw-cek small { display: block; margin-top: 1px; font-size: 11px; color: #64748b; }
/* ══ PANEL UNDUHAN (pojok kanan bawah) ══════════════════════════════════
   Melayang di atas halaman, bukan di dalam modal: pekerjaannya berjalan di
   antrean dan admin harus tetap bisa memakai papan sementara menunggu. */
/* Kepala panel MENGIKUTI EVO THEME, bukan navy pekat. Panelnya melayang di
   atas papan yang seluruhnya terang; kepala gelap membuatnya terbaca seperti
   jendela milik aplikasi lain yang kebetulan menumpang di pojok. */
.plw-unduhan__list { max-height: 260px; overflow-y: auto; }
.plw-unduhan__row { display: flex; align-items: center; gap: 11px; padding: 11px 13px; border-bottom: 1px solid #f1f4f9; }
.plw-unduhan__row:last-child { border-bottom: 0; }
.plw-unduhan__txt { flex: 1; min-width: 0; }
.plw-unduhan__txt b { display: block; font-size: 12.5px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-unduhan__txt small { display: block; font-size: 11px; color: #94a3b8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-unduhan__txt small.is-err { color: #dc2626; }
.plw-unduhan__ikon { flex: none; font-size: 17px; }
.plw-unduhan__ikon.is-pdf { color: #dc2626; }
.plw-unduhan__ikon.is-xls { color: #15803d; }

/* Cincin kemajuan — satu lingkaran menjawab "berapa lagi" tanpa perlu dibaca. */
.plw-ring { position: relative; flex: none; width: 34px; height: 34px; display: grid; place-items: center; }
.plw-ring svg { position: absolute; inset: 0; transform: rotate(-90deg); }
.plw-ring__bg { fill: none; stroke: #eef1f7; stroke-width: 3; }
.plw-ring__val { fill: none; stroke: #6366f1; stroke-width: 3; stroke-linecap: round; transition: stroke-dasharray .3s ease; }
.plw-ring b { position: relative; font-size: 10px; font-weight: 800; color: #475569; }
.plw-ring i { position: relative; font-size: 15px; }
.plw-ring.is-selesai .plw-ring__val { stroke: #10b981; }
.plw-ring.is-selesai i { color: #10b981; }
.plw-ring.is-gagal .plw-ring__val { stroke: #ef4444; }
.plw-ring.is-gagal i { color: #ef4444; }


@media (max-width: 520px) {
    .plw-unduhan { right: 12px; left: 12px; bottom: 12px; width: auto; }
}


.plw-infocard { background: #fff; border: 1px solid #eef0f7; border-radius: 18px; padding: 18px 20px; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); }
.plw-infogrid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 20px; }
.plw-klabel { font-size: 10.5px; font-weight: 800; letter-spacing: 0.14em; color: #a2a9ba; }
.plw-kval { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 4px; }
.plw-status { display: inline-flex; align-items: center; gap: 7px; margin-top: 6px; font-size: 14px; font-weight: 800; }
.plw-status__dot { width: 9px; height: 9px; border-radius: 50%; }
.plw-status.st-berjalan { color: #b45309; }
.plw-status.st-berjalan .plw-status__dot { background: #f59e0b; animation: plwPulse 2s infinite; }
.plw-status.st-lulus { color: #059669; }
.plw-status.st-lulus .plw-status__dot { background: #10b981; }
.plw-status.st-gugur { color: #dc2626; }
.plw-status.st-gugur .plw-status__dot { background: #ef4444; }
@keyframes plwPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); } 70% { box-shadow: 0 0 0 7px rgba(245, 158, 11, 0); } }
/* `plw-segbar`, bukan `plw-seg`: nama kedua itu dipakai KELOMPOK TOMBOL
   Lulus/Gagal di modal penilaian — komponen lain, berkas yang sama. Selama
   keduanya bernama sama, wadah tombol itu ikut memungut `height: 6px` milik
   bilah kemajuan ini, dan tombol-tombolnya meluber keluar wadah setinggi enam
   piksel. */
.plw-segs { display: flex; gap: 5px; margin-top: 9px; }
.plw-segbar { flex: 1; height: 6px; border-radius: 99px; background: #eef0f7; }
.plw-segbar.is-done { background: linear-gradient(90deg, #8b5cf6, #6366f1); }
.plw-segbar.is-cur { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.plw-segbar.is-fail { background: linear-gradient(90deg, #f87171, #ef4444); }
.plw-sysnote { display: flex; gap: 12px; margin-top: 16px; padding: 13px 15px; border-radius: 14px; background: linear-gradient(135deg, #fffbeb, #fff8ec); border: 1px solid #f5e0a3; font-size: 12.5px; line-height: 1.6; color: #8a6d29; }
.plw-sysnote b { color: #92660a; }
.plw-sysnote.is-ready { background: linear-gradient(135deg, #ecfdf5, #f0fdf9); border-color: #a7f3d0; color: #065f46; }
.plw-sysnote.is-ready b { color: #047857; }
.plw-sysnote.is-fail { background: linear-gradient(135deg, #fef2f2, #fff5f5); border-color: #fecaca; color: #7f1d1d; }
.plw-sysnote.is-fail b { color: #b91c1c; }

/* ── Rapor tes tahap (baterai multi-tes) ── */
.plw-tests { display: flex; flex-direction: column; gap: 8px; }
.plw-test { display: flex; align-items: center; gap: 11px; padding: 11px 13px; border: 1px solid #e8eaf3; border-radius: 13px; background: #fff; }
.plw-test__ico { flex: none; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 15px; }
.plw-test__ico.is-sys { background: rgba(217, 119, 6, .1); color: #b45309; }
.plw-test__ico.is-man { background: rgba(99, 102, 241, .1); color: #4f46e5; }
.plw-test__main { flex: 1; min-width: 0; }
.plw-test__name { font-size: 13px; font-weight: 700; color: #1e2447; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.plw-test__tag { font-size: 10px; font-weight: 700; border-radius: 999px; padding: 1px 7px; background: #f1f5f9; color: #64748b; }
.plw-test__tag.is-info { background: #eef2ff; color: #4338ca; }
.plw-test__tag.is-offer { background: rgba(124, 58, 237, .1); color: #6d28d9; }
.plw-test__sub { font-size: 11px; color: #94a3b8; margin-top: 1px; }
.plw-test__score { font-size: 15px; font-weight: 800; color: #4f46e5; font-variant-numeric: tabular-nums; }
.plw-test__pill { flex: none; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 3px 10px; }
.plw-test__pill.is-pass { background: rgba(16, 185, 129, .13); color: #047857; }
.plw-test__pill.is-fail { background: rgba(239, 68, 68, .12); color: #b91c1c; }
.plw-test__pill.is-done { background: rgba(99, 102, 241, .12); color: #4338ca; }
.plw-test__pill.is-sched { background: rgba(14, 165, 233, .12); color: #0369a1; }
.plw-test__pill.is-wait { background: #f1f5f9; color: #64748b; }
/* Keterangan, bukan keadaan menunggu — nadanya sengaja paling tenang. */
.plw-test__pill.is-note { background: rgba(124, 58, 237, .09); color: #6d28d9; font-weight: 600; }
.plw-test__pill.is-absent { background: rgba(148, 163, 184, .18); color: #475569; }
.plw-test__skip { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #fca5a5; background: #fff; color: #b91c1c; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 9px; cursor: pointer; transition: background .15s; }
.plw-test__skip:hover { background: #fef2f2; }
/* Keputusan lulus/tidak lulus — dua tombol yang saling berlawanan, jadi
   keduanya BERISI warna (bukan garis tepi seperti tombol lain di baris ini):
   pada aktivitas yang menunggu keputusan, inilah satu-satunya yang harus
   ditekan, dan sepasang tombol pucat di antara tombol pucat lain membuatnya
   luput terbaca. */
.plw-test__ver { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid transparent; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 10px; cursor: pointer; transition: filter .15s; }
.plw-test__ver:hover:not(:disabled) { filter: brightness(.94); }
.plw-test__ver:disabled { opacity: .6; cursor: default; }
.plw-test__ver.is-lulus { background: #059669; color: #fff; }
.plw-test__ver.is-gagal { background: #fff; border-color: #fca5a5; color: #b91c1c; }
.plw-test__ver.is-gagal:hover:not(:disabled) { background: #fef2f2; filter: none; }
.plw-test__sync { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #a5b4fc; background: #fff; color: #4338ca; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 9px; cursor: pointer; transition: background .15s; }
.plw-test__sync:hover:not(:disabled) { background: #eef0fe; }
.plw-test__sync:disabled { opacity: .6; cursor: default; }
/* Ikon yang BERPUTAR di tempat — bukan cincin pemuat.
   Dulu keduanya sama-sama bernama .plw-spin di berkas yang sama, dan definisi
   yang belakangan menang: setiap <i class="bi-arrow-repeat plw-spin"> ikut
   memungut lebar 18px, tinggi 18px, dan border 2.5px milik cincin — panah
   berputarnya digambar terkurung di dalam lingkaran bergaris. */
.plw-putar { display: inline-block; animation: plwSpin 1s linear infinite; }
@keyframes plwSpin { to { transform: rotate(360deg); } }
.plw-test__rec { flex: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #a5b4fc; background: #eef2ff; color: #4338ca; font-size: 11px; font-weight: 700; border-radius: 9px; padding: 5px 9px; cursor: pointer; transition: background .15s; }
.plw-test__rec:hover { background: #e0e7ff; }
.plw-test__cat { margin-top: 3px; font-size: 11px; color: #64748b; display: flex; align-items: flex-start; gap: 5px; line-height: 1.45; }
/* Catatan berformat butuh ruang sendiri: isinya bisa berparagraf & bergambar,
   jadi ikonnya diberi lebar tetap agar teksnya tidak melompat-lompat. */
.plw-test__cat.is-kaya { margin-top: 6px; padding: 7px 9px; border-radius: 9px; background: #f8fafc; border: 1px solid #eef1f6; }
.plw-test__cat.is-kaya > i { flex: none; margin-top: 2px; }
.plw-test__cat.is-kaya > div { min-width: 0; flex: 1; }
/* Berkas dari kandidat vs berkas tim — dibedakan warnanya karena cara
   membacanya berbeda: yang satu bukti yang perlu diperiksa, yang lain
   kesimpulan penilai. */
.plw-test__kirim { margin-top: 5px; display: flex; align-items: center; flex-wrap: wrap; gap: 5px; font-size: 11px; }
.plw-test__kirimlbl { display: inline-flex; align-items: center; gap: 4px; font-weight: 800; color: #0369a1; }
.plw-test__kirim.is-tim .plw-test__kirimlbl { color: #7c3aed; }
.plw-test__kirimfile { display: inline-flex; align-items: center; gap: 4px; max-width: 190px; padding: 2px 7px; border-radius: 999px; background: rgba(2, 132, 199, .08); border: 1px solid rgba(2, 132, 199, .2); color: #075985; font-weight: 700; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-test__kirimfile:hover { background: rgba(2, 132, 199, .16); }
.plw-test__kirim.is-tim .plw-test__kirimfile { background: rgba(124, 58, 237, .08); border-color: rgba(124, 58, 237, .2); color: #5b21b6; }
.plw-test__kirim.is-tim .plw-test__kirimfile:hover { background: rgba(124, 58, 237, .16); }
.plw-test__kirimkosong { display: inline-flex; align-items: center; gap: 5px; color: #94a3b8; font-weight: 700; }
.plw-test__kirimkosong.is-wajib { color: #b45309; }
/* Penanda "sudah dinyatakan lengkap" — dibaca sebelum menilai. */
.plw-test__kirimstat { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 800; letter-spacing: .01em; cursor: help; white-space: nowrap; }
.plw-test__kirimstat.is-ok { background: rgba(16, 185, 129, .12); color: #047857; border: 1px solid rgba(16, 185, 129, .3); }
.plw-test__kirimstat.is-nunggu { background: rgba(245, 158, 11, .12); color: #b45309; border: 1px solid rgba(245, 158, 11, .32); }
/* Berkas kandidat di dalam modal penilaian — dipisahkan sebagai kotak sendiri
   supaya tidak terbaca sebagai bagian dari borang yang sedang diisi. */
.plw-kirimbox { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; padding: 9px 11px; border-radius: 10px; font-size: 11px; background: rgba(2, 132, 199, .05); border: 1px solid rgba(2, 132, 199, .18); }
.plw-kirimbox__lbl { display: inline-flex; align-items: center; gap: 5px; font-weight: 800; color: #075985; }
/* Hasil yang diunggah KANDIDAT (MCU mandiri) — pengganti kotak unggah tim. */
.plw-hasilk { margin-top: 4px; padding: 11px 13px; border-radius: 12px; border: 1px dashed rgba(2, 132, 199, .35); background: rgba(2, 132, 199, .04); }
.plw-hasilk__head { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.plw-hasilk__title { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 800; color: #1e293b; }
.plw-hasilk__tag { display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 800; color: #075985; background: rgba(2, 132, 199, .12); }
.plw-hasilk__list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; font-size: 11px; }
.plw-hasilk__kosong { display: flex; align-items: center; gap: 6px; margin: 9px 0 0; font-size: 12px; font-weight: 700; color: #b45309; }
.plw-hasilk__ket { display: flex; align-items: flex-start; gap: 7px; margin: 9px 0 0; font-size: 11.5px; line-height: 1.55; color: #64748b; }
.plw-hasilk__ket > .bi { flex: none; margin-top: 2px; color: #0369a1; }
/* Jadwal yang sudah ditetapkan — dibaca sekilas saat menandai kehadiran. */
/* Sepasang tombol persetujuan di baris aktivitas — sama besar, sebab keduanya
   jawaban yang sah. */
.plw-test__setuju {
    display: inline-flex; align-items: center; gap: 5px; cursor: pointer;
    padding: 6px 12px; border-radius: 9px; font-size: 12px; font-weight: 800;
}
.plw-test__setuju.is-ya { border: 1px solid #a7f3d0; background: #ecfdf5; color: #047857; }
.plw-test__setuju.is-ya:hover { background: #d1fae5; }
.plw-test__setuju.is-tidak { border: 1px solid #fecaca; background: #fff; color: #b91c1c; }
.plw-test__setuju.is-tidak:hover { background: #fef2f2; }
.plw-test__setuju:disabled { opacity: .55; cursor: not-allowed; }

.plw-test__periksa {
    display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
    margin-top: 4px; font-size: 11px; color: #64748b;
}
.plw-test__periksa .is-samar { color: #a3adc2; font-style: italic; }
.plw-test__adj {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 800;
}
.plw-test__adj.is-ok { background: #ecfdf5; color: #047857; }
.plw-test__adj.is-warn { background: #fffbeb; color: #b45309; }
.plw-test__adj.is-no { background: #fef2f2; color: #b91c1c; }
.plw-test__adj.is-abu { background: #f1f5f9; color: #475569; }

.plw-test__jadwalinfo { margin-top: 4px; display: flex; align-items: flex-start; gap: 5px; font-size: 11px; font-weight: 600; color: #4338ca; line-height: 1.45; }
.plw-test__jadwalinfo .bi { flex: none; margin-top: 1px; }

/* ═══ BORANG DI DALAM MODAL ═══
   Kontrol digambar sendiri, bukan memakai bawaan Element Plus. Modal ini
   bertetangga dengan drawer & rapor tes yang seluruhnya bergaya plw-*, dan
   komponen bawaan membawa palet, radius, serta tinggi barisnya sendiri —
   satu jendela jadi terlihat seperti dijahit dari dua aplikasi berbeda. */
.plw-fld { display: flex; flex-direction: column; gap: 6px; margin-top: 12px; text-align: left; }
.plw-fld:first-child { margin-top: 0; }
.plw-fld__lbl { display: flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 800; color: #475569; }
.plw-fld__lbl b { color: #dc2626; }
.plw-fld__lbl small { font-weight: 700; color: #a2a9ba; }
/* Dua isian sebaris; turun sendiri di layar sempit. */
/* Dua kolom sama lebar. Jaraknya diambil alih BARISNYA, dan margin anak-anaknya
   dinolkan — `.plw-fld:first-child` hanya menolkan kolom PERTAMA, sehingga
   kolom kedua turun 12px sendirian dan kedua labelnya tidak pernah sejajar. */
.plw-fld__row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
.plw-fld__row:first-child { margin-top: 0; }
.plw-fld__row > .plw-fld { min-width: 0; margin-top: 0; }

.plw-inp {
    width: 100%; padding: 10px 12px; border: 1px solid #e3e6f0; border-radius: 10px;
    background: #fff; font: inherit; font-size: 13px; color: #1e293b;
    transition: border-color .16s, box-shadow .16s;
}
.plw-inp::placeholder { color: #a8b0c0; }
.plw-inp:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(99, 102, 241, .14); }
.plw-inp.is-err { border-color: #fca5a5; background: #fffafa; }
.plw-inp.is-err:focus { border-color: #f87171; box-shadow: 0 0 0 3px rgba(239, 68, 68, .12); }
.plw-inp--ta { min-height: 68px; line-height: 1.55; resize: vertical; }
/* Keterangan yang bentuknya mengikuti isian lain supaya barisnya sejajar. */
.plw-inp.is-static { background: #f8fafc; color: #64748b; font-weight: 700; }
/* Panah bawaan input angka mengubah lebar isian di tiap peramban. */
.plw-inp[type='number'] { appearance: textfield; -moz-appearance: textfield; }
.plw-inp[type='number']::-webkit-outer-spin-button,
.plw-inp[type='number']::-webkit-inner-spin-button { appearance: none; margin: 0; }
.plw-inp[type='date'] { min-height: 41px; }
.plw-inp[type='date']::-webkit-calendar-picker-indicator { cursor: pointer; opacity: .55; }

/* Pilihan bentuk kartu — pengganti radio bawaan. */
.plw-opts { display: flex; flex-direction: column; gap: 7px; }

/* DUA PILIHAN BERDAMPINGAN — setengah lebar masing-masing. Keduanya setara,
   dan perbandingannya harus terbaca sekali lihat.

   Aturan ini dulu ditulis JAUH DI ATAS `.plw-opts`, padahal kekhususannya
   sama persis (satu kelas). Yang belakangan menang, jadi `flex-direction: row`
   selalu dikalahkan `column` milik aturan dasar — modifier `--row` tidak
   pernah bekerja sehari pun. Akibatnya bukan cuma bertumpuk: pada wadah
   ber-arah kolom, `flex-basis` mengukur TINGGI, sehingga tiap kartu berdiri
   setinggi 200px lebih dengan isi setinggi dua baris di tengah kekosongan.

   Sekarang ia duduk tepat setelah aturan dasarnya — urutan berkas yang
   menentukan, jadi jaraknya harus dekat supaya tetap begitu. */
.plw-opts--row { flex-direction: row; flex-wrap: wrap; align-items: stretch; }
/* calc(50% - 4px): setengah lebar dikurangi separuh gap 7px. Dua kartu pas
   sebaris, dan `min-width` memaksanya turun jadi satu kolom di modal sempit
   alih-alih memeras keduanya sampai judulnya terpotong. */
.plw-opts--row > .plw-opt-card { flex: 1 1 calc(50% - 4px); min-width: 190px; }

.plw-opt-card {
    display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 12px;
    border: 1px solid #e3e6f0; border-radius: 11px; background: #fff;
    font: inherit; text-align: left; cursor: pointer; transition: border-color .16s, background .16s, box-shadow .16s;
}
.plw-opt-card:hover { border-color: #c7cbdb; }
.plw-opt-card__dot { flex: none; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; font-size: 12px; color: #94a3b8; background: #f1f3f9; transition: background .16s, color .16s; }
.plw-opt-card__txt { min-width: 0; }
.plw-opt-card__txt b { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.plw-opt-card__txt small { display: block; font-size: 11px; color: #94a3b8; margin-top: 1px; line-height: 1.4; }
/* ── KARTU TERPILIH ─────────────────────────────────────────────────────
   Aturan ini DULU TIDAK ADA. Yang punya gaya terpilih hanya varian `is-fmt`,
   `is-ok`, dan `is-warn` — sementara kartu polos ber-`is-on` (Pola waktu,
   predikat hasil) berubah NOL PIKSEL saat ditekan. Admin memilih "Bergilir",
   layarnya diam saja, dan ia menekan lagi mengira klik pertamanya tidak masuk.

   Dinaikkan ke kartu dasar, bukan disalin ke tiap varian: varian berikutnya
   akan lupa membawanya, dan bug yang sama lahir lagi. Varian is-ok/is-warn
   tetap menang karena selektornya lebih spesifik. */
.plw-opt-card.is-on {
    border-color: #6366f1; background: rgba(99, 102, 241, .06);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
}
.plw-opt-card.is-on .plw-opt-card__dot { background: #6366f1; color: #fff; }
.plw-opt-card.is-on .plw-opt-card__txt b { color: #3730a3; }

/* Ceklis di kanan — muncul HANYA pada yang terpilih. Kartu yang tidak
   menyediakan span-nya tidak terpengaruh. */
.plw-opt-card__cek {
    flex: none; margin-left: auto; width: 21px; height: 21px; border-radius: 50%;
    display: grid; place-items: center; font-size: 11px;
    border: 1.5px solid #d8dcea; color: transparent; background: #fff;
    transition: background .16s, border-color .16s, color .16s;
}
.plw-opt-card.is-on .plw-opt-card__cek { background: #6366f1; border-color: #6366f1; color: #fff; }
.plw-opt-card.is-fmt { padding: 11px 13px; }
.plw-opt-card.is-fmt.is-on .plw-opt-card__dot { background: #fff; color: inherit; box-shadow: 0 1px 3px rgba(30, 41, 59, .12); }

.plw-opt-card.is-on.is-ok { border-color: rgba(16, 185, 129, .5); background: rgba(16, 185, 129, .07); box-shadow: 0 0 0 3px rgba(16, 185, 129, .1); }
.plw-opt-card.is-on.is-ok .plw-opt-card__dot { background: #10b981; color: #fff; }
.plw-opt-card.is-on.is-warn { border-color: rgba(245, 158, 11, .55); background: rgba(245, 158, 11, .09); box-shadow: 0 0 0 3px rgba(245, 158, 11, .1); }
.plw-opt-card.is-on.is-warn .plw-opt-card__dot { background: #f59e0b; color: #fff; }
.plw-opt-card.is-on.is-no { border-color: rgba(239, 68, 68, .45); background: rgba(239, 68, 68, .06); box-shadow: 0 0 0 3px rgba(239, 68, 68, .09); }
.plw-opt-card.is-on.is-no .plw-opt-card__dot { background: #dc2626; color: #fff; }
/* HOLD — "belum layak SEMENTARA". Sengaja BUKAN merah: ia bukan penolakan,
   melainkan penundaan yang bisa diperiksa ulang. Memberinya warna yang sama
   dengan Unfit membuat penilai yang membaca sekilas memperlakukan keduanya
   sama — dan itu persis kekeliruan yang kategori ini ada untuk mencegahnya. */
.plw-opt-card.is-on.is-hold { border-color: rgba(2, 132, 199, .5); background: rgba(2, 132, 199, .07); box-shadow: 0 0 0 3px rgba(2, 132, 199, .1); }
.plw-opt-card.is-on.is-hold .plw-opt-card__dot { background: #0284c7; color: #fff; }

/* Ikon tiap pilihan tetap berwarna meski belum terpilih: empat status MCU
   dibedakan lebih dulu oleh warnanya, baru oleh kata-katanya. */
.plw-opt-card.is-ok    .plw-opt-card__dot { color: #059669; background: rgba(16, 185, 129, .1); }
.plw-opt-card.is-warn  .plw-opt-card__dot { color: #b45309; background: rgba(245, 158, 11, .12); }
.plw-opt-card.is-hold  .plw-opt-card__dot { color: #0369a1; background: rgba(2, 132, 199, .1); }
.plw-opt-card.is-no    .plw-opt-card__dot { color: #b91c1c; background: rgba(239, 68, 68, .1); }
.plw-opt-card__dot .bi { font-size: 13px; }
/* Kartu MCU lebih tinggi dari pilihan lain — labelnya dua bahasa dan
   keterangannya kerap dua baris. */
.plw-mform .plw-opt-card { align-items: flex-start; padding: 11px 13px; }
.plw-mform .plw-opt-card .plw-opt-card__dot { margin-top: 1px; }
.plw-mform .plw-opt-card__txt small { color: #64748b; }

/* Dua pilihan berdampingan (Lulus / Gagal) — pengganti radio-button bawaan. */
.plw-seg__b { display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 10px; border: 1px solid #e3e6f0; border-radius: 10px; background: #fff; font: inherit; font-size: 13px; font-weight: 800; color: #64748b; cursor: pointer; transition: border-color .16s, background .16s, color .16s; }
.plw-seg__b:hover { border-color: #c7cbdb; }
.plw-seg__b.is-ok.is-on { border-color: rgba(16, 185, 129, .5); background: rgba(16, 185, 129, .1); color: #047857; }
.plw-seg__b.is-no.is-on { border-color: rgba(239, 68, 68, .45); background: rgba(239, 68, 68, .08); color: #b91c1c; }
/* Pilihan yang tidak bermuatan baik/buruk (mis. daring vs tatap muka). */
.plw-seg__b.is-net.is-on { border-color: rgba(99, 102, 241, .5); background: rgba(99, 102, 241, .09); color: #4338ca; }

/* Keterangan sebaris — nadanya dari kelas, bukan gaya sebaris di template. */
.plw-note { display: flex; align-items: flex-start; gap: 7px; margin: 8px 0 0; padding: 9px 11px; border-radius: 10px; font-size: 11.5px; line-height: 1.55; text-align: left; }
.plw-note .bi { flex: none; margin-top: 1px; }
.plw-note.is-info { color: #3730a3; background: rgba(99, 102, 241, .08); border: 1px solid rgba(99, 102, 241, .2); }
.plw-note.is-lock { color: #92400e; background: rgba(245, 158, 11, .09); border: 1px solid rgba(245, 158, 11, .25); }
.plw-note.is-err { color: #b91c1c; background: rgba(239, 68, 68, .07); border: 1px solid rgba(239, 68, 68, .22); }
/* Aturan biaya (MCU) — keterangan yang ikut di undangan, bukan isian. */
.plw-note.is-biaya { color: #065f46; background: rgba(16, 185, 129, .08); border: 1px solid rgba(16, 185, 129, .22); }

/* Blok borang bertajuk (hasil MCU di modal keputusan). */
.plw-mform { margin-bottom: 12px; padding: 13px; border: 1px solid rgba(15, 23, 42, .1); border-radius: 14px; background: #f9fafc; }
.plw-mform.is-kurang { border-color: rgba(239, 68, 68, .3); background: #fffafa; }
.plw-mform__head { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.plw-mform__ico { flex: none; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 15px; color: #fff; background: linear-gradient(140deg, #f87171, #dc2626); }
.plw-mform__title { font-size: 13.5px; font-weight: 800; color: #1e293b; }
.plw-mform__sub { font-size: 11px; color: #8b93a7; margin-top: 1px; }

@media (max-width: 520px) {
    .plw-fld__row { grid-template-columns: 1fr; }
}

/* Tombol catat untuk tes pihak ke-3 dibedakan warnanya — menandai jalur darurat. */
.plw-test__rec.is-manual { color: #b45309; border-color: rgba(234, 179, 8, .45); background: rgba(234, 179, 8, .08); }

.plw-secrow { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.plw-sectitle { display: flex; align-items: center; gap: 9px; font-size: 15px; font-weight: 800; color: #1e293b; }
.plw-seccount { font-size: 11.5px; font-weight: 700; color: #8b93a7; background: #eef0f7; border-radius: 999px; padding: 4px 11px; flex: 0 0 auto; }

/* ── PEMILIH CARA MEMBACA BERKAS (daftar / file manager) ─────────────────── */
/* Saklar tampilan — IKON SAJA, seperti desain. Dua label teks di sini berebut
   baris dengan judul seksi dan hitungannya, lalu ketiganya melipat di layar
   sedang. Artinya tetap terbaca lewat title/aria-pressed. */
.plw-fmview { display: inline-flex; align-items: center; gap: 3px; padding: 4px; border-radius: 13px; background: #fff; border: 1px solid #e7e3fb; flex: 0 0 auto; margin-left: auto; }
.plw-fmview__b { appearance: none; border: none; background: transparent; cursor: pointer; font-family: inherit; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 15px; color: #94a3b8; transition: all 0.16s; }
.plw-fmview__b:hover { color: #4f46e5; background: #f4f2ff; }
.plw-fmview__b.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 8px 18px rgba(99, 102, 241, 0.26); }

/* ── FILE MANAGER ───────────────────────────────────────────────────────────
   Dua panel: folder (kiri, tetap) + petak berkas (kanan, menggulir sendiri).
   Tingginya dipatok supaya panel kanan yang menggulir, bukan seluruh modal —
   kalau modal yang menggulir, daftar folder ikut hanyut dan pindah folder
   menuntut menggulir balik ke atas dulu. */
.plw-fm { display: grid; grid-template-columns: 248px minmax(0, 1fr); background: #fff; border: 1px solid #e7e3fb; border-radius: 18px; overflow: hidden; box-shadow: 0 6px 20px rgba(99, 102, 241, 0.07); min-height: 420px; }
.plw-fm__side { display: flex; flex-direction: column; min-width: 0; background: #fbfbfe; border-right: 1px solid #eef0f7; }
.plw-fm__sidehead { display: flex; align-items: center; gap: 10px; padding: 13px 14px 12px; border-bottom: 1px solid #eef0f7; }
.plw-fm__sideico { width: 34px; height: 34px; border-radius: 11px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 16px; }
.plw-fm__sidetxt { min-width: 0; display: block; }
.plw-fm__sidetxt b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__sidetxt em { display: block; font-size: 10.5px; font-style: normal; color: #94a3b8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__folders { flex: 1; min-height: 0; overflow-y: auto; padding: 8px; display: flex; flex-direction: column; gap: 3px; }
.plw-fm__folder { appearance: none; border: 1px solid transparent; background: transparent; cursor: pointer; font-family: inherit; display: flex; align-items: center; gap: 9px; width: 100%; padding: 9px 10px; border-radius: 11px; text-align: left; transition: all 0.15s; }
.plw-fm__folder:hover { background: #f4f2ff; }
.plw-fm__folder.is-on { background: #fff; border-color: #c7d2fe; box-shadow: 0 6px 16px rgba(99, 102, 241, 0.12); }
.plw-fm__fico { width: 26px; height: 26px; border-radius: 8px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: #eef0f7; color: #8b93a7; font-size: 13px; transition: all 0.15s; }
.plw-fm__folder.is-on .plw-fm__fico { background: #e0e7ff; color: #4f46e5; }
.plw-fm__flabel { flex: 1; min-width: 0; font-size: 12.5px; font-weight: 700; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__folder.is-on .plw-fm__flabel { color: #1e293b; font-weight: 800; }
.plw-fm__fn { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 2px 7px; border-radius: 6px; background: #eef0f7; color: #94a3b8; }
.plw-fm__folder.is-on .plw-fm__fn { background: #ede9fe; color: #6d28d9; }
.plw-fm__meter { padding: 12px 14px; border-top: 1px solid #eef0f7; }
.plw-fm__meterhead { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 10px; font-weight: 800; letter-spacing: 0.1em; color: #94a3b8; }
.plw-fm__meterhead span:last-child { color: #4f46e5; letter-spacing: 0; }
.plw-fm__bar { height: 6px; border-radius: 99px; background: #eef0f7; overflow: hidden; margin-top: 8px; }
.plw-fm__bar > div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); transition: width 0.3s; }

.plw-fm__main { display: flex; flex-direction: column; min-width: 0; }
.plw-fm__toolbar { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-bottom: 1px solid #eef0f7; flex-wrap: wrap; }
.plw-fm__crumb { display: flex; align-items: center; gap: 6px; min-width: 0; flex: 0 1 auto; font-size: 12px; color: #94a3b8; }
.plw-fm__crumb i { font-size: 10px; color: #cbd5e1; flex: 0 0 auto; }
.plw-fm__crumb b { font-size: 12.5px; font-weight: 800; color: #1e293b; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__cari { position: relative; flex: 1 1 180px; min-width: 0; display: flex; align-items: center; }
.plw-fm__cari > i { position: absolute; left: 11px; font-size: 12px; color: #94a3b8; pointer-events: none; }
.plw-fm__cari input { width: 100%; height: 36px; padding: 0 32px 0 31px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 12.5px; color: #334155; outline: none; font-family: inherit; transition: all 0.16s; }
.plw-fm__cari input:focus { background: #fff; border-color: #a5b4fc; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12); }
.plw-fm__cari > button { position: absolute; right: 8px; appearance: none; border: none; background: transparent; cursor: pointer; color: #94a3b8; font-size: 11px; padding: 4px; display: flex; }
.plw-fm__cari > button:hover { color: #4f46e5; }

.plw-fm__grid { flex: 1; min-height: 0; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(182px, 1fr)); gap: 11px; padding: 14px; align-content: start; }
.plw-fm__file { appearance: none; font-family: inherit; cursor: pointer; display: flex; flex-direction: column; align-items: flex-start; text-align: left; padding: 13px; border-radius: 14px; border: 1px solid #e7e3fb; background: #fff; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04); transition: transform 0.18s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.18s, border-color 0.18s; min-width: 0; }
.plw-fm__file:hover { transform: translateY(-3px); border-color: #a5b4fc; box-shadow: 0 14px 30px rgba(99, 102, 241, 0.15); }
.plw-fm__file.is-on { border-color: #6366f1; background: #f6f5ff; box-shadow: 0 12px 28px rgba(99, 102, 241, 0.18); }
.plw-fm__fileico { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: #eef2ff; border: 1px solid #dbe2fe; color: #4f46e5; font-size: 19px; flex: 0 0 auto; }
.plw-fm__fileico.is-img { background: #ecfdf5; border-color: #a7f3d0; color: #059669; }
.plw-fm__filenama { display: block; width: 100%; font-size: 12.5px; font-weight: 800; color: #1e293b; margin-top: 11px; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.plw-fm__filefile { display: block; width: 100%; font-size: 10.5px; color: #94a3b8; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__filefoot { display: flex; align-items: center; gap: 7px; width: 100%; margin-top: 10px; padding-top: 9px; border-top: 1px solid #f1f5f9; min-width: 0; }
.plw-fm__fileext { flex: 0 0 auto; font-size: 9.5px; font-weight: 800; letter-spacing: 0.05em; padding: 3px 7px; border-radius: 6px; background: #eef0f7; color: #64748b; }
.plw-fm__filedari { flex: 1; min-width: 0; font-size: 10px; color: #aab2c5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.plw-fm__kosong { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 7px; padding: 44px 22px; text-align: center; }
.plw-fm__kosongico { width: 52px; height: 52px; border-radius: 15px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #cbd5e1; font-size: 23px; }
.plw-fm__kosong b { font-size: 13.5px; font-weight: 800; color: #334155; margin-top: 6px; }
.plw-fm__kosong > span:last-child { font-size: 12px; color: #94a3b8; max-width: 280px; line-height: 1.55; }

.plw-fm__pratinjau { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-top: 1px solid #eef0f7; background: linear-gradient(135deg, #f7f5ff, #f2f5ff); flex-wrap: wrap; }
.plw-fm__pico { width: 38px; height: 38px; border-radius: 11px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #dbe2fe; color: #4f46e5; font-size: 17px; }
.plw-fm__pin { flex: 1 1 160px; min-width: 0; display: block; }
.plw-fm__pin b { display: block; font-size: 12.5px; font-weight: 800; color: #1e1b4b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__pin em { display: block; font-size: 11px; font-style: normal; color: #6b6597; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-fm__pbtn { appearance: none; border: none; cursor: pointer; font-family: inherit; flex: 0 0 auto; display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 800; color: #fff; padding: 9px 15px; border-radius: 11px; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 10px 22px rgba(99, 102, 241, 0.3); transition: transform 0.16s, box-shadow 0.16s; }
.plw-fm__pbtn:hover { transform: translateY(-1px); box-shadow: 0 14px 28px rgba(99, 102, 241, 0.4); }

.plw-forms { display: flex; flex-direction: column; gap: 12px; }
.plw-form { background: #fff; border: 1px solid #eef0f7; border-radius: 18px; overflow: hidden; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); transition: border-color 0.2s; }
.plw-form.is-open { border-color: #d9def0; }
.plw-form__head { appearance: none; border: none; background: #fff; width: 100%; display: flex; align-items: center; gap: 13px; padding: 15px 18px; cursor: pointer; text-align: left; transition: background 0.18s; font-family: inherit; }
.plw-form.is-open .plw-form__head { background: #fbfbff; }
.plw-form__step { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #cbd2e0, #94a3b8); color: #fff; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.plw-form__step.is-ok { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.plw-form__title { display: block; font-size: 14.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-form__sub { display: block; font-size: 11.5px; color: #8b93a7; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-form__pill { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; letter-spacing: 0.04em; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
.plw-form__pill.is-ok { background: rgba(16, 185, 129, 0.12); color: #059669; }
.plw-form__pill.is-wait { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.plw-form__chev { flex: 0 0 auto; transition: transform 0.26s; }
.plw-form.is-open .plw-form__chev { transform: rotate(180deg); }
.plw-form__body { padding: 6px 18px 18px; animation: plwAccIn 0.28s cubic-bezier(0.22, 1, 0.36, 1) both; }
@keyframes plwAccIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
/* Pil ringkas di puncak isi akordion. */
.plw-fsum { display: inline-flex; align-items: center; gap: 8px; max-width: 100%; margin: 6px 0 4px; padding: 6px 13px; border-radius: 999px; background: #f6f5ff; border: 1px solid #e7e3fb; font-size: 11.5px; font-weight: 700; color: #4f46e5; }
.plw-fsum i { flex: 0 0 auto; font-size: 12px; }
.plw-fsum span { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* KEPALA KELOMPOK BIODATA — tile ikon, judul, garis memudar.
   Judulnya datang dari skema formulir apa adanya ("Validasi Data", "Identitas
   Resmi"), jadi TIDAK dibesarkan jadi HURUF KAPITAL: itu nama kelompok yang
   dibaca kandidat saat mengisi, bukan label sistem. Kapital penuh pada frasa
   sepanjang itu juga lebih lambat dibaca. */
.plw-ghead { display: flex; align-items: center; gap: 9px; margin: 18px 0 10px; }
.plw-ghead__ico { flex: 0 0 auto; width: 28px; height: 28px; border-radius: 9px; display: flex; align-items: center; justify-content: center; background: #eef2ff; border: 1px solid #dbe2fe; color: #4f46e5; font-size: 13px; }
.plw-ghead__lbl { flex: 0 0 auto; min-width: 0; font-size: 13px; font-weight: 800; letter-spacing: 0.01em; color: #4338ca; }
.plw-ghead__n { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 3px 9px; border-radius: 7px; background: #f4f2ff; color: #6d28d9; }
.plw-ghead__garis { flex: 1; min-width: 12px; height: 1px; background: linear-gradient(90deg, #e7e3fb, transparent); }

/* DUA KOLOM TETAP — seperti desain. Kelompoknya sudah memotong daftar jadi
   petak-petak pendek, jadi kolom yang ikut berubah jumlahnya per kelompok
   justru membuat mata kehilangan garis bacanya. Di bawah 720px turun jadi satu
   kolom (lihat media query) karena dua kolom di lebar itu menyisakan ruang
   nilai selebar dua kata.

   Sel adalah KARTU terpisah, bukan petak dalam kisi bergaris rambut. Bentuk
   lama menyatukan semuanya jadi satu blok bergaris 1px: rapi saat isinya
   pendek, tapi begitu satu sel memuat linimasa riwayat, garis-garis itu
   memotongnya di tempat yang tak masuk akal. */
.plw-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.plw-field { background: #fbfbfe; border: 1px solid #eef0f7; border-radius: 11px; padding: 10px 12px; min-width: 0; }
/* Baris riwayat membawa kepala seksinya sendiri; kotak di dalam kotak di sini
   hanya menambah satu bingkai yang tak menerangkan apa pun. */
.plw-field:has(> .plw-tl) { background: transparent; border-color: transparent; padding: 0; }
/* Isian yang isinya panjang (alamat, uraian) diberi seluruh baris, bukan
   dipaksa berbagi dengan tetangganya. */
.plw-field.is-panjang { grid-column: 1 / -1; }
/* ADA / TIDAK ADA — satu-satunya hal yang perlu dijawab di daftar isian.
   Berkasnya sendiri (nama, ukuran, status verifikasi, pratinjau) ada di
   "Dokumen & Verifikasi" tepat di bawah; mengulangnya di sini hanya membuat
   dua tempat yang harus dijaga tetap sama. */
.plw-badge { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 800; }
.plw-badge.is-ada { color: #047857; background: rgba(16, 185, 129, .12); }
.plw-badge.is-kosong { color: #94a3b8; background: #f1f5f9; }
/* Blok "tombol buka berkas" yang dulu di sini SUDAH DIHAPUS.
   Templatenya tidak lagi memakai `.plw-lihat` sebagai tombol maupun
   `.plw-lihat__ext` — yang tersisa cuma lencana penanda pembaca di bawah
   (HANYA TIM / DIBACA KANDIDAT), dan aturan mati ini membocorkan
   `cursor: pointer`, efek hover, serta `.plw-lihat .bi { font-size: 12.5px }`
   ke sana: lencana setinggi 9,5px dengan ikon 12,5px yang mengundang diklik
   padahal tidak melakukan apa pun. */
.plw-field__k { font-size: 10.5px; font-weight: 700; letter-spacing: 0.08em; color: #a2a9ba; text-transform: uppercase; }
.plw-putus__unggah { margin: 12px 0 10px; border: 1px solid #eef0f7; border-radius: 12px; padding: 10px 12px; background: #fbfbfe; }
.plw-putus__unggah-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 12px; font-weight: 800; color: #334155; }
.plw-putus__unggah-head small { font-weight: 700; font-size: 10.5px; color: #94a3b8; margin-left: 4px; }
.plw-putus__unggah-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.plw-putus__berkas { display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; border-radius: 8px; background: #fff; border: 1px solid #e6e8f2; font-size: 12px; }
.plw-putus__berkas .bi { color: #dc2626; }
.plw-putus__berkas .bi-file-earmark-image-fill { color: #6366f1; }
.plw-putus__berkas button { border: 0; background: none; padding: 0; font: inherit; font-weight: 700; color: #4f46e5; cursor: pointer; }
.plw-putus__berkas-del { border: 0; background: none; padding: 0 0 0 2px; color: #94a3b8; cursor: pointer; font-size: 10px; line-height: 1; }
.plw-putus__berkas-del:hover { color: #dc2626; }
.plw-putus__unggah-kosong { margin: 6px 0 0; font-size: 11.5px; color: #94a3b8; text-align: left; }
.plw-putus__unggah-kosong.is-wajib { display: flex; align-items: flex-start; gap: 6px; padding: 8px 10px; border-radius: 9px; line-height: 1.5; color: #b91c1c; background: rgba(239, 68, 68, .07); border: 1px solid rgba(239, 68, 68, .22); }
.plw-putus__unggah-kosong.is-wajib .bi { flex: none; margin-top: 1px; }
/* Baris aktivitas sempit (drawer 560px) dan bisa memuat 3 tombol sekaligus.
   Dulu semuanya dipaksa satu baris dengan teks keterangan, sehingga nama
   aktivitas terpotong per kata dan tombolnya saling menghimpit. Kini isi
   keterangan boleh melebar penuh dan tombol turun ke barisnya sendiri. */
.plw-test { flex-wrap: wrap; row-gap: 8px; }
.plw-test__aksi { display: flex; flex-wrap: wrap; gap: 6px; width: 100%; }
.plw-test__hdr { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border: 1px solid; border-radius: 9px; background: #fff; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; transition: background .16s; }
.plw-test__hdr.is-ya { color: #059669; border-color: rgba(16, 185, 129, .35); }
.plw-test__hdr.is-ya:hover { background: rgba(16, 185, 129, .08); }
.plw-test__hdr.is-tidak { color: #dc2626; border-color: rgba(220, 38, 38, .28); }
.plw-test__hdr.is-tidak:hover { background: #fef2f2; }
.plw-test__hdrtag { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; color: #059669; background: rgba(16, 185, 129, .12); }
.plw-test__hdrtag.is-no { color: #b91c1c; background: rgba(239, 68, 68, .1); }
.plw-hdr__note { margin: 0 0 10px; font-size: 12.5px; line-height: 1.6; color: #475569; text-align: left; }
.plw-test__jdw { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border: 1px solid rgba(99, 102, 241, .3); border-radius: 9px; background: #fff; font: inherit; font-size: 12px; font-weight: 700; color: #4f46e5; cursor: pointer; transition: background .16s, border-color .16s; }
.plw-test__jdw:hover { background: #f5f3ff; border-color: #a5b4fc; }
.plw-test__jdw.is-set { color: #059669; border-color: rgba(16, 185, 129, .35); }
.plw-test__jdw.is-set:hover { background: rgba(16, 185, 129, .07); }
.plw-jdw { text-align: left; }
/* Pratinjau instruksi (terisi dari Master Alur) — dibaca dulu, editornya baru
   dibuka lewat "Ubah". Tingginya dibatasi supaya jendela tetap pendek. */
.plw-jdw__instruksi { margin-top: 6px; padding: 9px 11px; max-height: 150px; overflow: auto; border-radius: 10px; background: #f8fafc; border: 1px solid #e6e9f2; font-size: 12px; color: #334155; }
.plw-jdw__jejak { margin-top: 10px; }
.plw-jdw__jejaklist { margin: 6px 0 0; padding: 0 0 0 18px; display: grid; gap: 6px; font-size: 11.5px; color: #334155; }
.plw-jdw__jejaklist small { display: block; color: #64748b; line-height: 1.45; }
.plw-jdw__jejaklist small.is-alasan { color: #92400e; }
/* Informasi biaya — sakelar tampil/tidak ke kandidat beserta kalimatnya. */
.plw-biaya { margin-top: 12px; padding: 10px 12px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, .25); background: rgba(236, 253, 245, .7); transition: background .16s, border-color .16s; }
.plw-biaya.is-off { border-color: #e2e8f0; background: #f8fafc; }
.plw-biaya__head { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 12.5px; color: #065f46; }
.plw-biaya__head .bi { margin-right: 2px; }
.plw-biaya.is-off .plw-biaya__head { color: #475569; }
.plw-biaya__isi { margin: 6px 0 0; font-size: 12px; line-height: 1.55; color: #065f46; }
.plw-biaya.is-off .plw-biaya__isi { color: #94a3b8; }
.plw-biaya__hint { display: block; margin-top: 4px; font-size: 11px; color: #64748b; }
/* Surat pengantar di jendela jadwal: nama berkas + tombol unggah/ganti sebaris. */
.plw-surat { display: flex; flex-direction: column; align-items: stretch; gap: 6px; margin-top: 6px; padding: 8px 10px; border: 1px dashed #c7cbdb; border-radius: 10px; background: #f8fafc; font-size: 12.5px; color: #334155; }
.plw-surat__baris { display: flex; align-items: center; gap: 8px; padding: 5px 8px; border-radius: 8px; background: #fff; border: 1px solid #eef1f6; }
.plw-surat__baris > .bi { flex: none; font-size: 15px; color: #dc2626; }
.plw-surat__ukuran { flex: none; font-size: 11px; color: #94a3b8; }
.plw-surat small.is-lama { color: #64748b; }
.plw-surat__kuota { margin-left: auto; font-size: 11px; font-weight: 700; color: #475569; background: #eef2ff; padding: 1px 8px; border-radius: 999px; }
.plw-surat__kuota.is-penuh { color: #b91c1c; background: #fee2e2; }
.plw-surat__pilih { align-self: flex-start; }
.plw-surat__pilih.is-mati { opacity: .45; cursor: not-allowed; }
.plw-biaya__teks { margin-top: 6px; font-size: 12.5px; }
.plw-surat__nama { min-width: 0; flex: 1; font-weight: 700; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: none; }
a.plw-surat__nama:hover { color: #4f46e5; text-decoration: underline; }
.plw-surat small { flex: none; font-size: 10.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #059669; }
.plw-surat__kosong { flex: 1; color: #94a3b8; font-style: italic; }
.plw-surat__x { flex: none; appearance: none; border: 0; background: none; color: #94a3b8; cursor: pointer; padding: 2px 4px; }
.plw-surat__x:hover { color: #dc2626; }
.plw-surat__pilih { flex: none; display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border-radius: 8px; border: 1px solid rgba(99, 102, 241, .35); background: #fff; font-size: 12px; font-weight: 700; color: #4f46e5; cursor: pointer; }
.plw-surat__pilih:hover { background: #f5f3ff; }
.plw-surat__pilih.is-busy { opacity: .6; cursor: progress; }
/* Perpanjang & kirim ulang — sekeluarga dengan "Ubah Jadwal", beda nada. */
.plw-test__jdw.is-panjang { color: #0f766e; border-color: rgba(13, 148, 136, .35); }
.plw-test__jdw.is-panjang:hover { background: rgba(13, 148, 136, .07); }
.plw-test__jdw.is-ulang { color: #b45309; border-color: rgba(245, 158, 11, .4); }
.plw-test__jdw.is-ulang:hover { background: #fffbeb; }
/* Tunda — ungu master status DITUNDA. */
.plw-test__jdw.is-tunda { color: #6d28d9; border-color: rgba(124, 58, 237, .35); }
.plw-test__jdw.is-setuju { color: #15803d; border-color: rgba(22, 163, 74, .4); background: rgba(240, 253, 244, .9); }
.plw-test__jdw.is-tolak { color: #b91c1c; border-color: rgba(220, 38, 38, .35); }
.plw-test__jdw.is-tunda:hover { background: #f5f3ff; }
.plw-test__adj.is-surat { text-decoration: none; color: #4338ca; background: #eef2ff; }
.plw-test__adj.is-surat:hover { background: #e0e7ff; }
/* KONFIRMASI KEHADIRAN — chip berwarna status (dari master), bisa diklik. */
.plw-konf {
    appearance: none; cursor: pointer; font-family: inherit; border: 1px solid transparent;
    color: var(--nada, #64748b);
    background: color-mix(in srgb, var(--nada, #94a3b8) 12%, #fff);
    border-color: color-mix(in srgb, var(--nada, #94a3b8) 30%, #fff);
}
.plw-konf:hover { background: color-mix(in srgb, var(--nada, #94a3b8) 20%, #fff); }
.plw-konf:focus-visible { outline: 2px solid #a5b4fc; outline-offset: 1px; }
.plw-konf-panel { margin-top: 6px; }
.plw-card__konf {
    display: inline-flex; align-items: center; gap: 3px; white-space: nowrap;
    padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 800;
    color: var(--nada, #64748b);
    background: color-mix(in srgb, var(--nada, #94a3b8) 12%, #fff);
}
.plw-selbar__ulang {
    appearance: none; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800;
    display: inline-flex; align-items: center; gap: 5px; padding: 6px 9px;
    white-space: nowrap; border-radius: 7px; transition: all .16s;
    background: #fffbeb; border: 1px solid #fde68a; color: #b45309;
}
.plw-selbar__ulang:hover { background: #fef3c7; border-color: #fcd34d; }
.plw-test__catlbl { display: block; margin-bottom: 3px; font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #6366f1; }
/* DUA kendali di modal ini tetap dari Element Plus — pemilih tanggal-waktu dan
   daftar lokasi yang bisa dicari. Keduanya perkakas nyata yang tak ada
   padanannya di HTML biasa (input datetime-local tak punya kalender bahasa
   Indonesia, <select> tak bisa disaring). Yang disetel di sini bentuk luarnya
   saja, supaya sebaris dengan .plw-inp di sebelahnya — bukan kotak abu
   kebiruan bawaan yang terlihat berasal dari aplikasi lain. */
.plw-jdw :deep(.el-input__wrapper),
.plw-jdw :deep(.el-select__wrapper) {
    border-radius: 10px; padding: 3px 12px; min-height: 41px;
    box-shadow: 0 0 0 1px #e3e6f0 inset; background: #fff; transition: box-shadow .16s ease;
}
.plw-jdw :deep(.el-input__wrapper:hover),
.plw-jdw :deep(.el-select__wrapper:hover) { box-shadow: 0 0 0 1px #c7cbdb inset; }
.plw-jdw :deep(.el-input__wrapper.is-focus),
.plw-jdw :deep(.el-select__wrapper.is-focused) { box-shadow: 0 0 0 1px #818cf8 inset, 0 0 0 3px rgba(99, 102, 241, .14); }
.plw-jdw :deep(.el-input__inner),
.plw-jdw :deep(.el-select__placeholder) { font-family: inherit; font-size: 13px; color: #1e293b; }
.plw-jdw :deep(.el-input__inner::placeholder),
.plw-jdw :deep(.el-select__placeholder.is-transparent) { color: #a8b0c0; }
.plw-lok__opt { display: flex; flex-direction: column; line-height: 1.35; padding: 2px 0; }
/* Tempat yang diketik sendiri: petanya hasil pencarian nama + alamat, bukan
   titik yang pernah diverifikasi. Disebut supaya tidak terbaca setara dengan
   lokasi master yang koordinatnya sudah dipastikan. */
.plw-lok__tag { display: inline-block; margin-left: 6px; font-size: 10px; font-weight: 700; border-radius: 999px; padding: 1px 7px; background: #fef3c7; color: #92400e; vertical-align: middle; }
.plw-lok__opt b { font-size: 13px; font-weight: 700; color: #1e293b; }
.plw-lok__opt small { font-size: 11px; color: #94a3b8; }
.plw-lok { margin-top: 8px; border: 1px solid #e6e8f2; border-radius: 12px; overflow: hidden; background: #fff; }
.plw-lok__map { display: block; width: 100%; height: 170px; border: 0; }
.plw-lok__info { display: flex; align-items: flex-start; gap: 9px; padding: 10px 12px; border-top: 1px solid #eef0f7; }
.plw-lok__info .bi { flex: none; color: #dc2626; margin-top: 2px; }
.plw-lok__info b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; }
.plw-lok__info small { display: block; font-size: 11.5px; color: #64748b; margin-top: 1px; }
.plw-lok__info a { flex: none; margin-left: auto; font-size: 11.5px; font-weight: 800; color: #4f46e5; text-decoration: none; }
.plw-lb__pdf { display: block; width: 100%; height: min(74vh, 780px); border: 0; border-radius: 12px; background: #fff; }
.plw-field__file { display: inline-flex; align-items: center; gap: 7px; max-width: 100%; padding: 5px 10px; margin-top: 2px; border: 1px solid #e6e8f2; border-radius: 9px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #1e293b; cursor: pointer; transition: border-color .16s, background .16s; }
.plw-field__file:hover { border-color: #a5b4fc; background: #f5f3ff; }
.plw-field__file .bi { flex: none; color: #dc2626; }
.plw-field__file .bi-file-earmark-image-fill { color: #6366f1; }
.plw-field__file span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plw-field__file em { flex: none; font-style: normal; font-size: 11.5px; font-weight: 800; color: #4f46e5; }
.plw-field__v { font-size: 13.5px; font-weight: 700; color: #1e293b; margin-top: 3px; word-break: break-word; }
/* NIK, telepon, email — dibaca digit per digit saat memverifikasi berkas.
   Huruf lebar-tetap membuat selisih satu angka jatuh di kolom yang sama dan
   langsung terlihat. Lihat selMono(). */
.plw-field__v.is-mono { font-family: 'JetBrains Mono', ui-monospace, 'SFMono-Regular', Menlo, monospace; font-size: 12.5px; letter-spacing: -0.01em; }
/* ── DAFTAR BUTIR ── */
/* Petak ikon dokumen dipakai bersama oleh ikon dan pratinjau gambar. Saat
   berisi gambar, paddingnya dilepas supaya fotonya memenuhi petak — ikon yang
   mengambang di tengah bingkai membuat wajahnya jadi terlalu kecil untuk
   dicocokkan dengan apa pun. */
.plw-doc__ico.is-foto { padding: 0; overflow: hidden; background: #f1f5f9; }
.plw-doc__ico.is-foto img { width: 100%; height: 100%; object-fit: cover; display: block; }

.plw-butir { list-style: none; counter-reset: butir; margin: 5px 0 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.plw-butir li { counter-increment: butir; position: relative; padding: 5px 10px 5px 30px; border-radius: 9px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; line-height: 1.5; word-break: break-word; }
.plw-butir li::before {
    content: counter(butir);
    position: absolute; left: 7px; top: 5px;
    width: 17px; height: 17px; display: grid; place-items: center;
    border-radius: 999px; background: #e0e7ff; color: #4338ca;
    font-size: 10px; font-weight: 800;
}

/* ── ISIAN BERULANG — LINIMASA ────────────────────────────────────────────
   Bentuk lamanya (.plw-rows) memakai flex-wrap: tiap pasangan label-nilai
   selebar isinya sendiri, sehingga kolom yang sama berhenti di tempat berbeda
   antar baris dan uraian panjang menyeret sisanya. Yang terbaca bukan tabel,
   melainkan tumpukan teks.

   Sekarang: satu garis waktu vertikal, satu kartu per baris, isi kartu di
   GRID berkolom tetap. Kolomnya sejajar antar baris karena lebarnya tidak
   lagi ditentukan isi masing-masing. */
.plw-tl { margin-top: 6px; }

/* KEPALA SEKSI RIWAYAT — bukan tombol berbingkai penuh seperti dulu.
   Bentuknya mengikuti kepala seksi di desain: tile ikon, judul ber-tracking,
   pil jumlah, lalu garis memudar yang menutup sisa baris. Yang menandai ia
   bisa ditekan tinggal chevron di ujung — dan itu memang cukup, karena
   seluruh barisnya tetap sasaran klik. */
.plw-tl__head {
    display: flex; align-items: center; gap: 10px; width: 100%; padding: 4px 0 10px;
    appearance: none; border: 0; background: transparent;
    font: inherit; cursor: pointer; color: #4338ca; text-align: left;
}
.plw-tl__ico {
    flex: none; width: 32px; height: 32px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
    background: #eef2ff; border: 1px solid #c7d2fe; color: #4f46e5; font-size: 15px;
}
.plw-tl__cap { flex: 0 0 auto; max-width: 60%; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-tl__n { flex: none; font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 8px; background: #f4f2ff; color: #6d28d9; }
/* Garis memudar — pengganti bingkai. Ia yang mengikat kepala seksi ke isinya
   tanpa menambah satu kotak lagi di dalam kotak formulir. */
.plw-tl__garis { flex: 1; min-width: 12px; height: 1px; background: linear-gradient(90deg, #e7e3fb, transparent); }
.plw-tl__chev { flex: none; color: #94a3b8; transition: transform .2s ease; }
.plw-tl__chev.is-up { transform: rotate(180deg); }
.plw-tl__head:hover .plw-tl__chev { color: #4f46e5; }

/* Tidak ada jendela gulir kedua di sini. Isi modal sendiri yang menggulir;
   dua bilah gulir bersarang membuat roda mouse menggerakkan yang salah, dan
   riwayat yang panjang justru paling sering dibaca berurutan dari atas. */
.plw-tl__body { padding: 2px 0; }

/* GARIS WAKTU.
   Rel digambar sebagai ::before milik <ol>, BUKAN border-left, supaya letak
   titiknya bisa dihitung dari satu angka (--rel) alih-alih ditebak. Bentuk
   lamanya memakai border-left + dot ber-left tetap: pusat titik meleset
   setengah lebarnya dari garis, dan itulah "bulatnya kurang pas" yang
   terlihat. Sekarang titik selalu duduk tepat di atas rel berapa pun
   ukurannya, karena posisinya diturunkan dari --rel dan --titik. */
.plw-tl__line {
    --rel: 13px;        /* jarak sumbu rel dari tepi kiri daftar */
    --titik: 26px;      /* garis tengah bulatan */
    list-style: none; margin: 0; padding: 2px 0 2px calc(var(--rel) + 19px);
    display: flex; flex-direction: column; gap: 11px; position: relative;
}
.plw-tl__line::before {
    content: ''; position: absolute; left: calc(var(--rel) - 1px); top: 10px; bottom: 10px; width: 2px;
    border-radius: 999px;
    background: linear-gradient(180deg, #c7d2fe, #eef0f7);
}
.plw-tl__item { position: relative; }

/* Titik bernomor — SATU warna, ungu merek. Bentuk lamanya menggilir enam warna
   pelangi per baris; itu memang menandai barisnya, tapi warna di antarmuka ini
   sudah punya arti lain (hijau lulus, kuning menunggu, merah gagal), dan
   riwayat kerja yang berwarna-warni terbaca seolah tiap barisnya berstatus
   berbeda. Nomornya sendiri sudah cukup membedakan. */
.plw-tl__dot {
    position: absolute; top: 13px;
    /* Item mulai di (--rel + 19px); mundur sejauh itu lalu setengah bulatan
       lagi, maka pusat titik jatuh persis di sumbu rel. */
    left: calc(-19px - var(--titik) / 2);
    width: var(--titik); height: var(--titik);
    display: inline-flex; align-items: center; justify-content: center;
    color: #fff; border: 3px solid #fff; border-radius: 999px;
    font-size: 11px; font-weight: 800; font-variant-numeric: tabular-nums;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 0 0 1px #e7e3fb;
    transition: transform .18s ease;
}
.plw-tl__item:hover .plw-tl__dot { transform: scale(1.08); }

/* Kartu satu baris riwayat — datar dan tenang (#fbfbfe), bukan kartu putih
   berpita warna di dalam kartu putih formulir. Kartu di dalam kartu dengan
   ketinggian yang sama membuat batas keduanya hilang. */
.plw-tl__card {
    position: relative;
    background: #fbfbfe; border: 1px solid #eef0f7; border-radius: 14px; padding: 13px 15px;
    transition: border-color .18s ease, box-shadow .18s ease;
    animation: plwCardIn .32s ease both;
}
.plw-tl__card:hover { border-color: #c7d2fe; box-shadow: 0 8px 22px rgba(99, 102, 241, .1); }

/* KEPALA KARTU — judul kiri, pil periode kanan. */
.plw-tl__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.plw-tl__judul { min-width: 0; font-size: 14px; font-weight: 800; color: #1e293b; letter-spacing: -.01em; line-height: 1.35; text-wrap: pretty; }
.plw-tl__periode { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 4px 10px; border-radius: 8px; white-space: nowrap; background: #f4f2ff; border: 1px solid #e7e3fb; color: #6d28d9; }

/* Kisi isian ringkas — kartu kecil berlatar putih di atas kartu #fbfbfe,
   mengikuti kisi biodata di desain. Sel polos tanpa latar membuat label dan
   nilai dari kolom bersebelahan terbaca menyambung jadi satu kalimat. */
.plw-tl__grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(min(158px, 100%), 1fr));
    gap: 8px;
}
.plw-tl__cell { min-width: 0; background: #fff; border: 1px solid #eef0f7; border-radius: 11px; padding: 9px 11px; }
.plw-tl__k { display: block; font-size: 9.5px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: #a2a9ba; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-tl__v { display: block; margin-top: 3px; font-size: 12.5px; font-weight: 700; color: #334155; line-height: 1.45; word-break: break-word; }
/* Nominal rupiah: angka rata, jadi ribuan sejajar antar baris. */
.plw-tl__v.is-rp, .plw-field__v.is-rp { font-variant-numeric: tabular-nums; color: #047857; }

/* URAIAN — blok sendiri di bawah kisi, dipisah garis tipis. */
.plw-tl__note { margin-top: 10px; padding: 10px 12px; border-radius: 11px; background: #fff; border: 1px solid #eef0f7; }
/* Baris yang isinya uraian saja: tak ada kisi di atasnya. */
.plw-tl__note:first-child { margin-top: 0; }

/* ── LAMPIRAN SATU BARIS RIWAYAT (bisa lebih dari satu lembar) ────────────
   Jalur sendiri di dasar kartu, bukan sel di dalam kisi: jumlahnya tak
   terbatas, dan satu sel selebar 150px tidak bisa memuat daftar berkas tanpa
   memotong namanya jadi tiga huruf. */
.plw-tl__berkas { margin-top: 11px; display: flex; flex-direction: column; gap: 6px; }
.plw-tl__berkas:first-child { margin-top: 0; }
.plw-tl__berkashead { display: flex; align-items: center; gap: 6px; font-size: 9.5px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: #a2a9ba; }
.plw-tl__berkashead .bi { font-size: 11px; }
.plw-tl__berkasn { display: inline-grid; place-items: center; min-width: 16px; height: 16px; padding: 0 5px; border-radius: 999px; background: #eef2ff; color: #4338ca; font-size: 9.5px; letter-spacing: 0; }
.plw-tl__file {
    display: flex; align-items: center; gap: 9px; width: 100%; padding: 8px 10px;
    appearance: none; border: 1px solid #e7e3fb; border-radius: 11px; background: #fff;
    font: inherit; cursor: pointer; text-align: left; transition: all .16s;
}
.plw-tl__file:hover { border-color: #a5b4fc; background: #f8f7ff; box-shadow: 0 4px 12px rgba(99, 102, 241, .1); }
.plw-tl__fileico { flex: none; width: 28px; height: 28px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; background: #eef2ff; border: 1px solid #c7d2fe; color: #dc2626; font-size: 13px; }
.plw-tl__fileico .bi-file-earmark-image-fill { color: #4f46e5; }
.plw-tl__filenama { flex: 1; min-width: 0; font-size: 12px; font-weight: 700; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-tl__fileext { flex: none; font-size: 9px; font-weight: 800; color: #8b93a7; background: #eef0f7; border-radius: 5px; padding: 2px 6px; }
.plw-tl__filego { flex: none; font-size: 11px; font-weight: 800; color: #4f46e5; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 3px 10px; }

/* Satu pertanyaan berkas dengan beberapa lembar: tombolnya berjajar & melipat. */
.plw-berkasv { display: flex; flex-wrap: wrap; gap: 6px; }

/* ── SEKSI DOKUMEN TERLAMPIR (mode Daftar) ─────────────────────────────────
   Kartu berjajar, satu per lembar. Yang belum diunggah tetap tampil sebagai
   kartu redup dan tidak bisa ditekan — kekosongan itulah yang paling perlu
   terbaca saat memverifikasi berkas. */
.plw-docgrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(228px, 100%), 1fr)); gap: 9px; }
.plw-doc { appearance: none; font-family: inherit; text-align: left; display: flex; align-items: center; gap: 11px; min-width: 0; padding: 11px 13px; border-radius: 13px; background: #fff; border: 1px solid #e7e3fb; transition: border-color 0.16s, box-shadow 0.16s, transform 0.16s; }
button.plw-doc { cursor: pointer; }
button.plw-doc:hover { border-color: #a5b4fc; box-shadow: 0 8px 22px rgba(99, 102, 241, 0.12); transform: translateY(-1px); }
.plw-doc.is-kosong { background: #fbfbfe; border-color: #eef0f7; opacity: 0.68; }
.plw-doc__ico { flex: 0 0 auto; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f4f2ff, #eef2ff); border: 1px solid #dbe2fe; color: #dc2626; font-size: 17px; }
.plw-doc__ico .bi-file-earmark-image-fill { color: #4f46e5; }
.plw-doc.is-kosong .plw-doc__ico { background: #f4f6fb; border-color: #eef0f7; color: #cbd5e1; }
.plw-doc__in { flex: 1; min-width: 0; display: block; }
.plw-doc__nama { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-doc.is-kosong .plw-doc__nama { color: #64748b; }
.plw-doc__file { display: block; font-size: 10.5px; color: #94a3b8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-doc__ext { flex: 0 0 auto; font-size: 9.5px; font-weight: 800; letter-spacing: 0.05em; padding: 3px 8px; border-radius: 7px; background: #eef2ff; color: #4338ca; }

@media (max-width: 640px) {
    .plw-tl__line { --rel: 11px; --titik: 23px; padding-left: calc(var(--rel) + 16px); }
    .plw-tl__dot { left: calc(var(--titik) / -2 - 16px); }
    .plw-tl__card { padding: 11px 12px; }
    .plw-tl__grid { gap: 7px; }
    .plw-tl__v { font-size: 12px; }
    .plw-tl__judul { font-size: 13px; }
    .plw-tl__body { max-height: 300px; }
}

/* ── Tombol keputusan ────────────────────────────────────────────────────
   Grid auto-fit, bukan flex ber-min-width tetap. Sebelumnya tiap tombol
   dipaksa minimal 130–150px; di drawer 460px (breakpoint <1180px) tiga tombol
   + gap sudah memakan 410px, sehingga label panjang pecah jadi dua baris dan
   tingginya jadi tidak sama.

   Dengan auto-fit, jumlah kolom menyesuaikan lebar yang tersedia sendiri:
   drawer lebar → 3 sebaris; menyempit → 2 + 1; sangat sempit → menumpuk.
   Tidak ada breakpoint yang perlu ditebak untuk tiap kombinasi tombol. */
/* Kaki modal: keterangan + deretan keputusan. Batas tingginya sendiri —
   pada tahap yang menahan banyak syarat, panel keterangannya bisa lebih
   tinggi daripada isi modalnya, dan tombol keputusan justru terdorong keluar
   layar oleh penjelasan tentang tombol itu sendiri. */
/* Kaki modal dibatasi SEPEREMPAT layar, bukan separuh.
   Yang dibaca orang ada di badan modal; kaki hanya tempat mengetuk palu.
   Ketika kakinya boleh setinggi 46vh, tiga baris keterangan + dua baris tombol
   cukup untuk menyisakan ruang baca setinggi empat baris teks — dan isi yang
   jadi dasar keputusan itu justru terdorong keluar pandangan. */
.plw-foot { max-height: 26vh; overflow-y: auto; overscroll-behavior: contain; }
.plw-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 9px; }

/* SATU bentuk tombol keputusan; warnanya dipilih lewat kelas nada, karena
   daftar tombolnya kini datang dari master dan bisa bertambah. */
.plw-btn-putus {
    appearance: none; cursor: pointer; font-family: inherit; border: none;
    padding: 11px 14px; border-radius: 13px; min-height: 42px;
    font-size: 13.5px; font-weight: 800; line-height: 1.2;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    /* Label tidak boleh pecah di tengah frasa; kalau benar-benar sempit,
       dipotong dengan elipsis — bukan menambah tinggi tombol. */
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    min-width: 0; transition: transform .16s, background .16s;
}
.plw-btn-putus .bi { flex: none; font-size: 15px; }
.plw-btn-putus.is-lolos { background: linear-gradient(135deg, #34d399, #10b981); color: #fff; box-shadow: 0 10px 24px rgba(16, 185, 129, 0.3); }
.plw-btn-putus.is-lolos:hover { transform: translateY(-2px); }
/* Nada emas: tidak lanjut di lowongan ini, tapi datanya disimpan. */
.plw-btn-putus.is-talent { background: linear-gradient(135deg, #fbbf24, #d97706); color: #fff; box-shadow: 0 10px 24px rgba(217, 119, 6, 0.28); }
.plw-btn-putus.is-talent:hover { transform: translateY(-2px); }
.plw-btn-putus.is-gugur { background: #fff; border: 1px solid #f4c9c9; color: #dc2626; }
.plw-btn-putus.is-gugur:hover { background: #fef2f2; }
/* TAHAN DULU sebaris dengan keputusan lain, tapi tenang: ini penundaan, bukan
   palu. Warnanya netral supaya tidak berebut perhatian dengan tiga tombol yang
   benar-benar memindahkan kandidat. */
.plw-btn-putus.is-hold { background: #fff; border: 1px dashed #d9def0; color: #64748b; }
.plw-btn-putus.is-hold:hover { background: #f8fafc; border-color: #c7d2fe; color: #4f46e5; }
/* ULANGI TAHAP — nada amber, bukan merah maupun netral.
   Merah sudah dipakai "Tidak Lolos" dan artinya kandidat berhenti; ini
   justru kebalikannya, ia kembali berjalan. Netral juga salah: ini
   membatalkan keputusan yang sudah diketuk, bukan catatan internal biasa. */
.plw-btn-putus.is-ulang { background: #fffbeb; border: 1px solid #fde68a; color: #b45309; }
.plw-btn-putus.is-ulang:hover { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
/* Empat tombol berbagi satu baris: label dirapatkan supaya "Talent Pool" tetap
   utuh sebaris tanpa perlu memperkecil tombolnya. */
.plw-actions.is-padat .plw-btn-putus { padding-left: 9px; padding-right: 9px; font-size: 12.5px; gap: 6px; }

/* KEPUTUSAN DARI KANDIDAT — SEBARIS dengan labelnya, sengaja lebih tenang.
   Ini bukan penilaian tim, jadi bobot visualnya tidak boleh menyaingi tombol di
   atas — dan judul setinggi satu baris penuh yang cuma menamai satu tombol
   adalah tinggi yang dibayar tanpa imbalan. */
.plw-actions2 { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e3e6f0; }
.plw-actions2__lbl { display: inline-flex; align-items: center; gap: 6px; flex: 0 0 auto; font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #a2a9ba; }
.plw-actions2__lbl em { font-style: normal; letter-spacing: 0; text-transform: none; color: #7c3aed; }
.plw-btn-kandidat {
    appearance: none; cursor: pointer; font-family: inherit; flex: 0 1 auto;
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    padding: 8px 13px; min-height: 34px; border-radius: 10px;
    border: 1px solid #ddd6fe; background: #faf9ff; color: #6d28d9;
    font-size: 12.5px; font-weight: 800; line-height: 1.2;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0;
    transition: background .16s, border-color .16s;
}
.plw-btn-kandidat:hover:not(:disabled) { background: #f3f0ff; border-color: #c4b5fd; }
.plw-btn-kandidat:disabled { opacity: .45; cursor: not-allowed; }
.plw-btn-kandidat .bi { flex: none; }
/* Pemanggil keterangan yang tadi ditutup — kecil, di ujung baris. */
.plw-actions2__info {
    appearance: none; cursor: pointer; font-family: inherit; margin-left: auto; flex: 0 0 auto;
    display: inline-flex; align-items: center; gap: 7px;
    width: 32px; height: 32px; justify-content: center; padding: 0;
    border-radius: 9px; border: 1px solid #f2e4c4; background: #fffdf7; color: #b45309;
    font-size: 13px; transition: all .16s;
}
.plw-actions2__info:hover { background: #fff8ec; border-color: #e8cf9a; }
.plw-actions2__info.is-solo { width: auto; height: auto; margin: 10px 0 0; padding: 8px 13px; font-size: 12px; font-weight: 800; }

/* Jawaban kandidat atas penawaran — kartu keadaan, bukan tombol. */
.plw-jawab { display: flex; align-items: flex-start; gap: 9px; margin-bottom: 10px; padding: 10px 12px; border-radius: 11px; font-size: 12px; line-height: 1.5; border: 1px solid; }
.plw-jawab .bi { flex: none; margin-top: 1px; font-size: 14px; }
.plw-jawab b { font-weight: 800; }
.plw-jawab p { margin: 3px 0 0; font-style: italic; opacity: .9; }
.plw-jawab.is-ya { color: #047857; background: rgba(16, 185, 129, .08); border-color: rgba(16, 185, 129, .28); }
.plw-jawab.is-no { color: #6d28d9; background: rgba(124, 58, 237, .07); border-color: rgba(124, 58, 237, .25); }
.plw-tp-hint { display: flex; align-items: flex-start; gap: 8px; margin: 0 0 12px; padding: 10px 12px; border-radius: 10px; font-size: 12px; line-height: 1.55; color: #92400e; background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.28); }
.plw-tp-hint .bi { color: #d97706; margin-top: 1px; flex: none; }

/* Ringkasan akibat keputusan — nadanya mengikuti jenis keputusan. */
.plw-putus__ring { text-align: left; margin: 0 0 12px; padding: 11px 13px; border-radius: 12px; border: 1px solid #eef0f7; background: #f8fafc; display: flex; flex-direction: column; gap: 7px; }
.plw-putus__ring.is-lulus { background: rgba(16, 185, 129, .07); border-color: rgba(16, 185, 129, .25); }
.plw-putus__ring.is-gugur { background: rgba(220, 38, 38, .06); border-color: rgba(220, 38, 38, .22); }
.plw-putus__ring.is-talent_pool { background: rgba(234, 179, 8, .08); border-color: rgba(234, 179, 8, .26); }
.plw-putus__row { display: flex; align-items: flex-start; gap: 8px; font-size: 12px; line-height: 1.55; color: #334155; }
.plw-putus__row .bi { flex: none; margin-top: 2px; color: #64748b; }
.plw-putus__row.is-mail { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; color: #64748b; }

/* Panel kemajuan keputusan massal & unduhan memakai cangkang bersama
   PanelProses (pojok kanan bawah, kepala gelap, bilah melekat). Gaya
   cangkang yang dulu berdiri di sini sudah dicabut supaya tidak ada dua
   sumber rupa untuk satu benda; yang tersisa cuma isi baris unduhan. */

/* ── Centang "sekalian catat hasil aktivitas" pada keputusan massal ── */
.plw-auto {
    display: flex; gap: 10px; align-items: flex-start;
    margin-top: 14px; padding: 12px 14px;
    border: 1px solid #e2e8f0; border-radius: 12px;
    background: #f8fafc; cursor: pointer;
    transition: border-color .16s ease, background .16s ease;
}
.plw-auto.is-on { border-color: #a5b4fc; background: rgba(99, 102, 241, .06); }
.plw-auto input { margin-top: 2px; accent-color: #6366f1; flex: 0 0 auto; }
.plw-auto span { min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.plw-auto b { font-size: 12.5px; color: #0f172a; }
.plw-auto small { font-size: 11px; line-height: 1.55; color: #64748b; }

/* ── Kirim ulang email hasil: satu baris per tahap yang pernah diputus ── */
.plw-eml__state { display: flex; align-items: center; gap: 8px; padding: 18px 4px; font-size: 12.5px; color: #64748b; }
.plw-eml__row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; margin-bottom: 8px;
    border: 1px solid #e2e8f0; border-radius: 12px;
    background: #fff; cursor: pointer;
    transition: border-color .16s ease, background .16s ease;
}
.plw-eml__row:hover { border-color: #c7d2fe; background: #fbfcff; }
.plw-eml__row.is-on { border-color: #6366f1; background: rgba(99, 102, 241, .06); }
.plw-eml__row.is-mati { cursor: not-allowed; opacity: .55; background: #f8fafc; }
.plw-eml__row.is-mati:hover { border-color: #e2e8f0; background: #f8fafc; }
.plw-eml__row input { accent-color: #6366f1; flex: 0 0 auto; }
.plw-eml__no {
    flex: 0 0 auto; width: 22px; height: 22px; border-radius: 7px;
    display: grid; place-items: center;
    background: #eef2ff; color: #4f46e5; font-size: 11px; font-weight: 800;
}
.plw-eml__in { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 2px; }
.plw-eml__in b { font-size: 13px; color: #0f172a; }
.plw-eml__in small { font-size: 11px; color: #64748b; }
.plw-eml__tag {
    flex: 0 0 auto; padding: 3px 8px; border-radius: 999px;
    font-size: 10px; font-weight: 800; letter-spacing: .3px;
}
.plw-eml__tag.is-lolos { color: #047857; background: rgba(16, 185, 129, .14); }
.plw-eml__tag.is-gugur { color: #b91c1c; background: rgba(239, 68, 68, .12); }
.plw-eml__note {
    display: flex; gap: 8px; margin: 12px 0 0;
    padding: 10px 12px; border-radius: 10px;
    background: #f8fafc; color: #475569; font-size: 11.5px; line-height: 1.55;
}
.plw-eml__note i { color: #6366f1; }
.plw-emlok { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 8px; padding: 4px 4px 2px; }
.plw-emlok__ic { width: 58px; height: 58px; border-radius: 50%; display: grid; place-items: center; font-size: 28px; color: #059669; background: rgba(16, 185, 129, .12); box-shadow: 0 0 0 8px rgba(16, 185, 129, .06); }
.plw-emlok p { margin: 4px 0 0; font-size: 14px; line-height: 1.6; color: #0f172a; }
.plw-emlok small { font-size: 11.5px; line-height: 1.55; color: #64748b; }

/* ── BATAS PENGISIAN FORMULIR ─────────────────────────────────────────────
   Empat keadaan, satu bahasa warna di kartu, drawer, dan kepala kolom:
   aman (indigo) · ≤ 24 jam (kuning) · lewat (merah muda) · terkunci (merah). */
.plw-colbatas {
    appearance: none; font: inherit; cursor: pointer; text-align: left;
    display: flex; align-items: flex-start; gap: 6px; width: 100%;
    margin: -4px 0 9px; padding: 6px 9px; border-radius: 9px;
    border: 1px solid #c7d2fe; background: #eef2ff; color: #4338ca;
    font-size: 11px; font-weight: 700; line-height: 1.4;
}
.plw-colbatas .bi { flex: none; margin-top: 1px; }
.plw-colbatas b { color: #b91c1c; }
.plw-colbatas.is-perlu { background: #fffbeb; border-color: #fde68a; color: #92400e; }
/* Berjadwal di Master Alur, belum diatur, tapi tak ada yang tertahan. */
.plw-colbatas.is-netral { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
.plw-colbatas.is-lewat { border-color: #fecaca; }
.plw-colbatas:hover { filter: brightness(0.97); }

.plw-card__foot:has(.plw-card__batas) { flex-wrap: wrap; row-gap: 6px; }
.plw-card__batas {
    display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px;
    border-radius: 999px; font-size: 10.5px; font-weight: 800; white-space: nowrap;
}
.plw-card__batas.is-aman { background: #eef2ff; color: #4338ca; }
.plw-card__batas.is-dekat { background: #fef3c7; color: #92400e; }
.plw-card__batas.is-lewat { background: #fee2e2; color: #b91c1c; }
.plw-card__batas.is-kunci { background: #b91c1c; color: #fff; }
/* Belum dibuka / belum dijadwalkan — terkunci, tapi bukan karena terlambat. */
.plw-card__batas.is-tutup { background: #e2e8f0; color: #334155; }

.plw-selbar__batas {
    appearance: none; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800;
    display: inline-flex; align-items: center; gap: 5px; padding: 6px 9px;
    white-space: nowrap; border-radius: 7px; transition: all .16s;
    background: #fffbeb; border: 1px solid #fde68a; color: #92400e;
}
.plw-selbar__batas:hover { background: #fef3c7; border-color: #fcd34d; }

.plw-batasp {
    margin-bottom: 10px; padding: 10px 12px; border-radius: 12px;
    background: #eef2ff; border: 1px solid #c7d2fe;
}
.plw-batasp.is-dekat { background: #fffbeb; border-color: #fde68a; }
.plw-batasp.is-lewat { background: #fef2f2; border-color: #fecaca; }
.plw-batasp.is-kunci { background: #fef2f2; border-color: #fca5a5; }
.plw-batasp.is-kosong { background: #f8fafc; border-color: #e2e8f0; }
.plw-batasp.is-tutup { background: #f1f5f9; border-color: #cbd5e1; }
.plw-batasp.is-tutup .plw-batasp__ic { background: rgba(71, 85, 105, 0.14); color: #334155; }
.plw-batasp__top { display: flex; align-items: flex-start; gap: 9px; }
.plw-batasp__ic {
    flex: none; display: grid; place-items: center; width: 28px; height: 28px; border-radius: 9px;
    background: rgba(99, 102, 241, 0.14); color: #4338ca; font-size: 14px;
}
.plw-batasp.is-dekat .plw-batasp__ic { background: rgba(245, 158, 11, 0.16); color: #b45309; }
.plw-batasp.is-lewat .plw-batasp__ic,
.plw-batasp.is-kunci .plw-batasp__ic { background: rgba(239, 68, 68, 0.14); color: #b91c1c; }
.plw-batasp__isi { min-width: 0; flex: 1; }
.plw-batasp__isi b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; }
.plw-batasp__isi small { display: block; margin-top: 2px; font-size: 11px; line-height: 1.5; color: #64748b; }
.plw-batasp__ubah {
    flex: none; display: inline-flex; align-items: center; gap: 5px; cursor: pointer;
    border: 1px solid #c7d2fe; background: #fff; color: #4338ca;
    font-size: 11.5px; font-weight: 800; border-radius: 8px; padding: 6px 11px;
}
.plw-batasp__ubah:hover { background: #eef2ff; }
.plw-batasp__rw {
    appearance: none; border: 0; background: none; cursor: pointer; font: inherit;
    display: inline-flex; align-items: center; gap: 5px; margin-top: 8px; padding: 0;
    font-size: 11px; font-weight: 700; color: #6366f1;
}
.plw-batasp__list { list-style: none; margin: 8px 0 0; padding: 8px 0 0; border-top: 1px dashed rgba(99, 102, 241, 0.3); display: grid; gap: 7px; }
.plw-batasp__list li b { display: block; font-size: 11.5px; font-weight: 800; color: #334155; }
.plw-batasp__list li small { display: block; font-size: 10.5px; line-height: 1.45; color: #64748b; }
.plw-batasp__list li.is-kosong { font-size: 11px; color: #94a3b8; display: flex; align-items: center; gap: 6px; }

.plw-batasm { display: grid; gap: 10px; }
.plw-batasm .plw-fld__lbl { margin: 2px 0 -4px; }
.plw-batasm__info { margin: 0; padding: 10px 12px 10px 28px; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; display: grid; gap: 4px; }
.plw-batasm__info li { font-size: 12px; line-height: 1.55; color: #475569; }
/* ── HASIL TAHAP SEBELUMNYA & DETAIL TAHAP (riwayat) ───────────────────── */
.plw-hts { margin: 14px 0 4px; padding: 12px 14px; border-radius: 14px; background: #fbfbfe; border: 1px solid #eceef6; }
.plw-hts__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.plw-hts__lbl { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; letter-spacing: .06em; color: #64748b; }
.plw-hts__jml { font-size: 11px; font-weight: 700; color: #94a3b8; }
.plw-hts__item { padding: 9px 0; border-top: 1px dashed #e5e7f0; }
.plw-hts__item:first-of-type { border-top: 0; }
.plw-hts__top { display: flex; align-items: center; gap: 10px; }
.plw-hts__no { flex: none; display: grid; place-items: center; width: 28px; height: 28px; border-radius: 9px; background: #eef2ff; color: #4338ca; font-size: 11px; font-weight: 800; }
.plw-hts__isi { min-width: 0; flex: 1; }
.plw-hts__isi b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; }
.plw-hts__isi small { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; margin-top: 3px; font-size: 11px; color: #64748b; }
.plw-hts__isi .plw-test__pill { padding: 1px 8px; font-size: 10.5px; }
.plw-hts__detail {
    flex: none; display: inline-flex; align-items: center; gap: 4px; padding: 5px 10px; border-radius: 8px; cursor: pointer;
    border: 1px solid #c7d2fe; background: #fff; color: #4338ca; font: inherit; font-size: 11.5px; font-weight: 800;
}
.plw-hts__detail:hover { background: #eef2ff; }
.plw-hts__berkas { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.plw-hts__file {
    display: inline-flex; align-items: center; gap: 6px; max-width: 100%; padding: 5px 10px; border-radius: 9px; cursor: pointer;
    border: 1px solid #e2e8f0; background: #fff; color: #334155; font: inherit; font-size: 11.5px; font-weight: 700;
}
.plw-hts__file:hover { border-color: #a5b4fc; background: #f5f7ff; }
.plw-hts__file .bi { flex: none; color: #6366f1; }
.plw-hts__file span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 240px; }
.plw-hts__file em { flex: none; font-style: normal; font-size: 10px; font-weight: 800; color: #0f766e; background: #ecfdf5; border-radius: 999px; padding: 0 6px; }

.plw-alur__item.is-klik { cursor: pointer; border-radius: 10px; transition: background .15s; }
.plw-alur__item.is-klik:hover { background: #f5f7ff; }
.plw-alur__item.is-klik:focus-visible { outline: 2px solid #a5b4fc; outline-offset: 2px; }
.plw-alur__kanan { flex: none; display: inline-flex; align-items: center; gap: 6px; }
.plw-alur__buka { flex: none; color: #a5b4fc; font-size: 13px; }
.plw-alur__item.is-klik:hover .plw-alur__buka { color: #4f46e5; }

.plw-trw { display: grid; gap: 10px; }
.plw-trw__muat { display: flex; align-items: center; gap: 8px; padding: 24px 4px; font-size: 13px; color: #64748b; }
.plw-trw__put { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.plw-trw__skor { font-size: 12.5px; font-weight: 800; color: #4f46e5; }
.plw-trw__meta { font-size: 11.5px; color: #64748b; }
.plw-trw__alasan { margin: 0; font-size: 12px; color: #475569; }
.plw-trw__cat { padding: 10px 12px; border-radius: 11px; background: #f8fafc; border: 1px solid #e2e8f0; }
.plw-trw__cat.is-eks { background: #f5f3ff; border-color: #ddd6fe; }
.plw-trw__cat > small { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; font-size: 10.5px; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; color: #64748b; }
.plw-trw__cat p { margin: 0; font-size: 12.5px; line-height: 1.55; color: #334155; white-space: pre-line; }
.plw-trw__sek { display: flex; align-items: baseline; gap: 8px; margin-top: 6px; font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
.plw-trw__sek em { font-style: normal; font-weight: 700; letter-spacing: 0; text-transform: none; color: #94a3b8; }
.plw-trw__kosong { margin: 0; font-size: 12px; color: #94a3b8; }
.plw-trw__tes { padding: 10px 12px; border-radius: 12px; border: 1px solid #eceef6; background: #fff; }
.plw-trw__teshead { display: flex; align-items: center; gap: 10px; }
.plw-trw__tesnama { min-width: 0; flex: 1; }
.plw-trw__tesnama b { display: block; font-size: 13px; font-weight: 800; color: #1e2447; }
.plw-trw__tesnama small { display: block; margin-top: 1px; font-size: 11px; color: #94a3b8; }
.plw-trw__tescat { margin-top: 8px; padding: 8px 10px; border-radius: 9px; background: #f8fafc; }
.plw-trw__tescat p { margin: 0; font-size: 12px; line-height: 1.55; color: #334155; white-space: pre-line; }
.plw-trw__link {
    justify-self: start; display: inline-flex; align-items: center; gap: 6px; padding: 0; border: 0; background: none; cursor: pointer;
    font: inherit; font-size: 11.5px; font-weight: 800; color: #4f46e5;
}

/* Riwayat jadwal kolom & tanda editan superadmin. */
.plw-batasm__riwayat { margin-top: 0; }
.plw-rwk {
    display: inline-flex; align-items: center; margin-right: 6px; padding: 1px 7px; border-radius: 999px;
    font-size: 10px; font-weight: 800; letter-spacing: 0.02em; vertical-align: 1px;
    background: #eef2ff; color: #4338ca;
}
.plw-rwk.is-perpanjang { background: #ecfdf5; color: #047857; }
.plw-rwk.is-ubah { background: #fef3c7; color: #92400e; }
.plw-rwk.is-lepas { background: #f1f5f9; color: #334155; }
.plw-rwk__alasan { font-style: italic; color: #475569 !important; }

/* Jadwal yang sedang berlaku — dibaca sebelum memperpanjangnya. */
.plw-batasm__kini {
    display: flex; align-items: flex-start; gap: 9px; padding: 9px 12px; border-radius: 10px;
    background: #f8fafc; border: 1px solid #e2e8f0;
}
.plw-batasm__kini > .bi { flex: none; margin-top: 2px; font-size: 15px; color: #6366f1; }
.plw-batasm__kini small { display: block; font-size: 10.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
.plw-batasm__kini b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; line-height: 1.45; }
.plw-batasm__dua { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.plw-batasm__dua > div { display: grid; gap: 6px; }
@media (max-width: 640px) {
    .plw-batasm__dua { grid-template-columns: 1fr; }
}

/* Centang wajib sebelum menggugurkan — sengaja mencolok, bukan sekadar teks. */
.plw-putus__cek { display: flex; align-items: flex-start; gap: 9px; margin-top: 12px; padding: 10px 12px; border-radius: 10px; cursor: pointer; font-size: 12px; line-height: 1.55; color: #7f1d1d; background: rgba(220, 38, 38, .06); border: 1px solid rgba(220, 38, 38, .22); text-align: left; }
.plw-putus__cek input { margin-top: 2px; width: 15px; height: 15px; accent-color: #dc2626; flex: none; cursor: pointer; }

/* ═══ AKTIVITAS BELUM SELESAI — pengakuan sadar sebelum memutus ═══
   Nadanya PERINGATAN, bukan galat: memutus lebih awal kadang memang benar
   (aktivitas sengaja dilewati), jadi ini menahan sebentar, bukan melarang. */
.plw-belum { margin-top: 12px; padding: 11px 12px; border-radius: 12px; background: #fffbeb; border: 1px solid #fcd34d; }
.plw-belum__head { display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; color: #92400e; line-height: 1.5; }
.plw-belum__head i { flex: none; margin-top: 1px; color: #d97706; }
.plw-belum__head b { color: #78350f; }
.plw-belum__list { margin: 8px 0 0; padding-left: 26px; font-size: 11.5px; color: #92400e; line-height: 1.65; }
.plw-belum__list li { margin-bottom: 2px; }
.plw-belum__list em { font-style: normal; color: #b45309; opacity: .8; }
.plw-belum__list em::before { content: ' — '; }
.plw-putus__cek.is-warn { margin-top: 10px; color: #78350f; background: rgba(217, 119, 6, .1); border-color: rgba(217, 119, 6, .3); }
.plw-putus__cek.is-warn input { accent-color: #d97706; }
/* ═══ PILIHAN TALENT POOL saat kandidat mundur ═══
   Dua kartu setara, bukan satu centang. Menyimpan atau tidak menyimpan
   sama-sama sah — dan centang yang sudah tercentang cenderung dilewati begitu
   saja, sehingga hampir semua yang mundur ikut tersimpan tanpa pernah benar
   benar dipertimbangkan. */
.plw-tp { margin-top: 12px; padding: 11px 12px; border-radius: 12px; background: #f8fafc; border: 1px solid #e6e9f0; }
.plw-tp__head { display: flex; align-items: center; gap: 7px; margin-bottom: 9px; font-size: 12px; font-weight: 800; color: #334155; }
.plw-tp__head i { color: #6366f1; }
.plw-tp__opts { display: flex; flex-direction: column; gap: 7px; }
.plw-tp__opt { display: flex; align-items: flex-start; gap: 9px; width: 100%; padding: 9px 11px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #fff; cursor: pointer; text-align: left; transition: border-color .15s, background .15s; }
.plw-tp__opt:hover { border-color: #cbd5e1; }
.plw-tp__ico { flex: none; display: grid; place-items: center; width: 26px; height: 26px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; font-size: 13px; }
.plw-tp__txt { min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.plw-tp__txt b { font-size: 12.5px; font-weight: 800; color: #1e293b; }
.plw-tp__txt small { font-size: 11.5px; line-height: 1.5; color: #64748b; }
.plw-tp__opt.is-simpan.is-on { border-color: #f59e0b; background: rgba(245, 158, 11, .07); }
.plw-tp__opt.is-simpan.is-on .plw-tp__ico { background: rgba(245, 158, 11, .16); color: #b45309; }
.plw-tp__opt.is-lepas.is-on { border-color: #64748b; background: rgba(100, 116, 139, .08); }
.plw-tp__opt.is-lepas.is-on .plw-tp__ico { background: rgba(100, 116, 139, .16); color: #475569; }

/* Indikator kuota di area keputusan */
.plw-kuota { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; padding: 9px 12px; border-radius: 10px; font-size: 12px; font-weight: 700; color: #3730a3; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.25); }
.plw-kuota.is-penuh { color: #b91c1c; background: rgba(239, 68, 68, 0.09); border-color: rgba(239, 68, 68, 0.28); }
/* Gerbang kehadiran — nadanya "belum saatnya", bukan "ada yang salah". */
.plw-kuota.is-hadir { align-items: flex-start; color: #92400e; background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); line-height: 1.5; }
.plw-kuota.is-hadir .bi { margin-top: 1px; }
.plw-kuota .bi { flex: none; }
/* Upload berkas hasil (MCU/Interview) */
.plw-upload { margin-bottom: 12px; padding: 11px 13px; border-radius: 12px; border: 1px solid rgba(15, 23, 42, 0.1); background: #f8fafc; }
.plw-upload__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.plw-upload__head > span { font-size: 12.5px; font-weight: 800; color: #334155; display: inline-flex; align-items: center; gap: 6px; }
.plw-req { font-size: 9.5px; font-weight: 800; text-transform: uppercase; color: #b91c1c; background: rgba(239, 68, 68, 0.12); border-radius: 999px; padding: 2px 7px; }
.plw-opt { font-size: 9.5px; font-weight: 800; text-transform: uppercase; color: #64748b; background: #eef0f7; border-radius: 999px; padding: 2px 7px; }
.plw-upbtn { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 700; color: #4f46e5; background: #eef2ff; border: 1px solid rgba(79, 70, 229, 0.25); border-radius: 8px; padding: 6px 11px; cursor: pointer; }
.plw-upbtn.is-busy { opacity: 0.6; pointer-events: none; }
.plw-files { display: flex; flex-direction: column; gap: 5px; margin-top: 9px; }
.plw-file { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 8px; padding: 6px 9px; }
.plw-file > .bi { color: #ef4444; font-size: 15px; flex: none; }
.plw-file__name { flex: 1; text-align: left; border: none; background: none; cursor: pointer; font-size: 12px; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plw-file__name:hover { color: #4f46e5; text-decoration: underline; }
.plw-file__del { border: none; background: none; color: #94a3b8; cursor: pointer; padding: 2px; }
.plw-file__del:hover { color: #dc2626; }
.plw-files__empty { margin-top: 8px; font-size: 11.5px; color: #94a3b8; }
.plw-btn-putus:disabled { opacity: 0.45; cursor: not-allowed; transform: none; box-shadow: none; }

/* ═══ LIGHTBOX ═══ */
/* Lightbox HARUS di atas modal keputusan (.wca-modal-mask = 1200).
   Dulu 1090, sehingga pratinjau berkas yang dibuka DARI DALAM modal
   muncul di belakangnya — terlihat seperti tombolnya tidak berfungsi. */
.plw-lb { position: fixed; inset: 0; z-index: 1250; display: flex; align-items: center; justify-content: center; padding: 24px; background: rgba(10, 10, 20, 0.72); backdrop-filter: blur(6px); transition: opacity 0.28s; opacity: 0; pointer-events: none; }
.plw-lb.is-on { opacity: 1; pointer-events: auto; }
/* PDF butuh ruang baca; gambar tetap nyaman di lebar ini. */
.plw-lb__wrap.is-pdf { max-width: 900px; }
.plw-lb__wrap { max-width: 520px; width: 100%; animation: plwPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
@keyframes plwPop { 0% { opacity: 0; transform: scale(0.4); } 60% { transform: scale(1.12); } 100% { opacity: 1; transform: scale(1); } }
.plw-lb__bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.plw-lb__id { display: flex; align-items: center; gap: 11px; color: #fff; min-width: 0; }
.plw-lb__ico { width: 40px; height: 40px; border-radius: 11px; background: rgba(255, 255, 255, 0.14); display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.plw-lb__name { font-size: 15px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-lb__desc { font-size: 12px; color: rgba(255, 255, 255, 0.6); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plw-lb__close { appearance: none; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.08); width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; transition: background 0.16s; flex: 0 0 auto; }
.plw-lb__close:hover { background: rgba(255, 255, 255, 0.18); }
.plw-lb__card { background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5); }
.plw-lb__card img { display: block; width: 100%; max-height: 420px; object-fit: contain; background: #0f172a; }
/* Tinggi SAMA dengan viewer-nya, supaya pergantian spinner -> isi
   tidak membuat kotaknya melompat tinggi. */
.plw-lb__state { width: 100%; min-height: 340px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; background: linear-gradient(135deg, #f1f2f9, #e8eaf6); color: #64748b; font-size: 13.5px; font-weight: 700; }
.plw-lb__retry { appearance: none; cursor: pointer; font-family: inherit; font-size: 12.5px; font-weight: 800; color: #fff; border: none; padding: 9px 18px; border-radius: 11px; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28); transition: transform 0.16s; }
.plw-lb__retry:hover { transform: translateY(-1px); }
.plw-lb__foot { padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid #eef0f7; }
.plw-lb__ok { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 800; color: #059669; flex: 0 0 auto; }
.plw-lb__adm { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 800; color: #c2410c; flex: 0 0 auto; white-space: nowrap; }

/* LENCANA "DIUNGGAH ADMIN" — berkas yang dipulihkan admin lewat panel
   Pemulihan Berkas atas nama kandidat. Jingga, bukan hijau: ini keterangan
   asal-usul, bukan tanda sah. Tooltip-nya menyebut siapa dan alasannya. */
.plw-oleh { display: inline-flex; align-items: center; gap: 4px; flex: 0 0 auto; font-size: 9.5px; font-weight: 800; letter-spacing: 0.02em; padding: 2px 7px; border-radius: 999px; background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; white-space: nowrap; cursor: help; }
.plw-oleh i { font-size: 10px; }
.plw-oleh--blok { margin-top: 5px; }
.plw-dok__sadm { position: absolute; left: 3px; top: 3px; width: 16px; height: 16px; border-radius: 5px; display: grid; place-items: center; font-size: 9px; font-style: normal; background: #f97316; color: #fff; }

/* TOAST */
/* Lapis toast bersama (--wca-z-toast, evo-theme.css). 1300 dulu cukup untuk
   modal EVO (1200), tapi TIDAK untuk lightbox berkas (.plw-lb 1250 & 1500),
   panel unduhan (3000), maupun drawer — padahal justru dari sanalah aksi
   simpan/unduh dijalankan. Satu angka bersama menutup seluruh selisih itu. */
.plw-toast {
    position: fixed; bottom: 24px; right: 24px; z-index: var(--wca-z-toast, 100000);
    display: flex; align-items: center; gap: 9px; padding: 12px 18px;
    max-width: min(520px, calc(100vw - 48px));
    border-radius: 13px; background: #0f172a; color: #fff;
    font-size: 13.5px; font-weight: 700; line-height: 1.5;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.3);
}
/* PANEL UNDUHAN MENEMPATI SUDUT YANG SAMA (.plw-unduhan, kanan-bawah).
   Saat ia terbuka, toast digeser ke ATAS panel alih-alih menimpanya — dua-duanya
   jawaban atas tindakan admin, dan yang satu tidak boleh menghapus yang lain
   dari pandangan. Kelasnya dipasang halaman saat daftar unduhan tidak kosong. */
.plw-toast.is-atas-unduhan { bottom: 96px; }

@media (max-width: 560px) {
    /* Melebar penuh: pada 360px, toast sudut menyisakan ruang teks selebar
       dua kata dan pesan sepanjang "Waktu berakhir harus setelah waktu mulai."
       terpotong jadi lima baris sempit. */
    .plw-toast {
        left: 12px; right: 12px; bottom: 16px;
        max-width: none; align-items: flex-start;
    }
    .plw-toast.is-atas-unduhan { bottom: 92px; }
    .plw-toast .bi { flex: none; margin-top: 1px; }
}
.plw-toast.is-err { background: #dc2626; }
.plw-toast .bi { color: #34d399; }
.plw-toast.is-err .bi { color: #fff; }
.plw-toast-enter-active, .plw-toast-leave-active { transition: opacity 0.25s, transform 0.25s; }
.plw-toast-enter-from, .plw-toast-leave-to { opacity: 0; transform: translateY(12px); }

/* ═══ RESPONSIF ═══
   Tiga titik henti, masing-masing menjawab satu hal yang benar-benar patah:

     1440px — kisi penyaring enam kolom mulai menyempitkan tiap kotak sampai
              teks pilihannya terpotong; dipecah jadi tiga kolom.
     1180px — panel program menyempit; tabel list kehilangan kolom yang tak
              esensial (kampus & tanggal tetap terbaca di kartu detail).
      992px — papan pindah ke bawah panel; kanban menumpuk vertikal; tiap
              baris list MELIPAT jadi kartu berlabel.

   Tabel yang digulung mendatar sengaja tidak dipakai di ponsel: membaca nama
   orang dengan menggeser layar ke kanan bukan cara siapa pun bekerja. */
@media (max-width: 1440px) {
    .plw-fgrid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .plw-f--cari { grid-column: span 2; }
}
@media (max-width: 1179.98px) {
    .plw-panel { width: 264px; flex-basis: 264px; }
    /* Kampus & tanggal keluar dari baris: keduanya masih bisa dicari lewat
       penyaring, sementara nama-posisi-tahap-keadaan adalah tulang punggung
       baris yang tak boleh menyusut.
       Nomor anaknya bergeser satu sejak kolom centang jadi yang pertama:
       Kampus kini anak ke-5, Melamar ke-8. */
    .plw-list__head > :nth-child(5),
    .plw-list__head > :nth-child(8),
    .plw-lcell[data-k='Kampus'],
    .plw-lcell[data-k='Melamar'] { display: none; }
    .plw-list__head,
    .plw-lrow { grid-template-columns: 26px 1.9fr 1.4fr 1.3fr 1fr 1.1fr 92px; }
}
@media (max-width: 991.98px) {
    .plw { flex-direction: column; height: auto; overflow: visible; }
    .plw-panel { width: 100%; flex: 0 0 auto; border-right: 0; border-bottom: 1px solid rgba(226, 232, 240, 0.75); }
    .plw-panel__list { flex-direction: row; overflow-x: auto; padding: 4px 16px 14px; }
    .plw-prog { flex: 0 0 250px; }
    .plw-kanban { flex-direction: column; }
    .plw-col { width: 100%; flex: 1 1 auto; }
    .plw-main { padding: 20px 16px 44px; }

    .plw-fgrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .plw-f--cari { grid-column: span 2; }

    /* BARIS LIST → KARTU. Kolomnya dilipat jadi tumpukan, dan tiap nilai
       mendapat label dari `data-k` — tanpa itu "Universitas Sriwijaya" dan
       "PRODUCTION SUPERVISOR" berdiri berdampingan tanpa keterangan apa pun. */
    .plw-list__head { display: none; }
    .plw-lrow { position: relative; display: flex; flex-direction: column; align-items: stretch; gap: 8px; padding: 14px 15px; }
    /* Kotak centang naik ke sudut kartu. Sebagai anak tumpukan biasa ia jadi
       satu baris kosong berisi satu kotak — dan kepala tabel yang biasanya
       memuatnya memang disembunyikan di lebar ini. */
    .plw-lcek { position: absolute; top: 12px; right: 13px; }
    .plw-lcek--head { display: none; }
    .plw-lcell--who { padding-right: 30px; }
    .plw-lsel__aksi { margin-left: 0; width: 100%; }
    .plw-lsel__aksi > button { flex: 1 1 108px; justify-content: center; }
    .plw-lcell { flex-wrap: wrap; }
    .plw-lcell[data-k]::before {
        content: attr(data-k);
        flex: 0 0 84px;
        font-size: 9.5px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: #a2a9ba;
    }
    .plw-lcell--act { justify-content: flex-start; padding-top: 4px; border-top: 1px solid #f1f5f9; }
    .plw-ltgl { flex-direction: row; align-items: baseline; gap: 6px; }
}
/* FILE MANAGER di layar sempit: folder jadi baris chip mendatar di atas petak.
   Panel kiri selebar 248px pada tablet menyisakan ruang berkas yang lebih
   sempit dari satu kartu — folder yang tak bisa ditinggalkan justru memakan
   tempat isi yang dicari. */
@media (max-width: 900px) {
    .plw-fm { grid-template-columns: minmax(0, 1fr); min-height: 0; }
    .plw-fm__side { border-right: 0; border-bottom: 1px solid #eef0f7; }
    .plw-fm__sidehead { display: none; }
    .plw-fm__folders { flex-direction: row; overflow-x: auto; overflow-y: hidden; padding: 10px 12px; gap: 7px; }
    .plw-fm__folder { width: auto; flex: 0 0 auto; border-color: #e7e3fb; background: #fff; }
    .plw-fm__flabel { max-width: 150px; }
    .plw-fm__meter { display: none; }
    .plw-fm__grid { grid-template-columns: repeat(auto-fill, minmax(158px, 1fr)); max-height: none; }
}
/* ── BIODATA LAYAR PENUH ─────────────────────────────────────────────────────
   Benar-benar penuh: tanpa jarak tepi, tanpa sudut membulat, tanpa lebar
   maksimum pada kotaknya. Yang dicari orang saat menekan "Layar penuh" adalah
   ruang — hamparan yang menyisakan bingkai di keempat sisinya hanya
   memindahkan kotak yang sama ke tempat yang sedikit lebih besar.

   Yang TIDAK ikut melebar adalah barisan isinya: di layar 3440px, kisi yang
   meregang penuh membuat nilai berjarak setengah meter dari labelnya. Kotaknya
   penuh, isinya berhenti di lebar yang masih bisa dipindai mata. */
/* ── BILAH ANTREAN TINJAU ────────────────────────────────────────────────
   Maju/mundur antar kandidat tanpa menutup jendela. */
.plw-nav {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
}

.plw-nav__b {
    appearance: none;
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    border: 1px solid #dcd8f6;
    background: rgba(255, 255, 255, 0.9);
    color: #5b53a8;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.16s;
}

.plw-nav__b:hover:not(:disabled) {
    background: #fff;
    border-color: #a5b4fc;
    color: #4338ca;
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.18);
}

/* Ujung antrean: tombolnya MATI, bukan hilang. Tombol yang lenyap membuat
   posisi tombol satunya bergeser, dan tangan yang sudah hafal tempatnya jadi
   menekan yang keliru. */
.plw-nav__b:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.plw-nav__teks {
    font-size: 11.5px;
    font-weight: 700;
    color: #6b6597;
    display: inline-flex;
    align-items: baseline;
    gap: 6px;
    min-width: 0;
}

.plw-nav__teks b {
    font-size: 13px;
    font-weight: 800;
    color: #4338ca;
}

.plw-nav__teks em {
    font-style: normal;
    font-size: 10.5px;
    font-weight: 700;
    color: #8b8bb0;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid #e4e0f8;
    border-radius: 999px;
    padding: 2px 9px;
    max-width: 190px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ── ALERT UJUNG ANTREAN ─────────────────────────────────────────────────
   z-index DI ATAS SEGALANYA: mask modal 1200, biodata layar penuh 1400.
   Pemberitahuan ini menjelaskan kenapa sebuah tombol diam, jadi ia harus
   terlihat justru ketika jendela lain sedang terbuka. */
.plw-antre {
    position: fixed;
    z-index: 2000;
    top: 22px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: flex-start;
    gap: 11px;
    max-width: min(520px, calc(100vw - 32px));
    padding: 13px 14px;
    border-radius: 14px;
    background: #fff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 18px 44px rgba(15, 23, 42, 0.22);
}

.plw-antre.is-habis { border-color: #fcd9a4; background: #fffbf3; }
.plw-antre.is-awal { border-color: #c7d2fe; background: #f8faff; }

.plw-antre__ico {
    flex: 0 0 auto;
    width: 34px;
    height: 34px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    color: #fff;
}

.plw-antre.is-habis .plw-antre__ico { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.plw-antre.is-awal .plw-antre__ico { background: linear-gradient(135deg, #8b5cf6, #6366f1); }

.plw-antre__t { flex: 1; min-width: 0; }
.plw-antre__t b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; }
.plw-antre__t small { display: block; margin-top: 2px; font-size: 11.5px; line-height: 1.5; color: #64748b; }

.plw-antre__x {
    appearance: none;
    flex: 0 0 auto;
    border: 0;
    background: transparent;
    color: #94a3b8;
    font-size: 11px;
    cursor: pointer;
    padding: 4px;
}

.plw-antre__x:hover { color: #475569; }

.plw-antre-enter-active,
.plw-antre-leave-active { transition: opacity 0.2s, transform 0.2s; }

.plw-antre-enter-from,
.plw-antre-leave-to { opacity: 0; transform: translateX(-50%) translateY(-10px); }

.plw-biofull {
    position: fixed;
    inset: 0;
    z-index: 1400;                 /* di atas .wca-modal-mask (1200) */
    display: flex;
    /* ── KENAPA TIDAK ADA backdrop-filter ─────────────────────────────────
       Dulu ada blur(2px) di sini, dan itulah sebab utama gulirannya patah.
       Penyaring latar sebesar layar memaksa peramban MEMBLUR ULANG seluruh
       halaman di belakangnya pada setiap bingkai — termasuk jendela detail
       yang isinya tujuh tab. Ongkosnya dibayar tiap kali roda tetikus
       diputar, padahal yang bergerak cuma daftar di depannya.

       Yang menggantikan efeknya cukup: latar gelap sedikit lebih pekat. */
    background: rgba(15, 23, 42, .62);
}
.plw-biofull__box {
    position: relative;            /* jangkar cap air */
    flex: 1;
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: #f7f8fc;
}

/* ── CAP AIR ─────────────────────────────────────────────────────────────────
   Diam di tengah sementara isinya bergulir di atasnya — itu yang membuatnya
   terbaca sebagai cap, bukan sebagai gambar yang kebetulan ada di sana.

   Opasitasnya rendah DAN ia berada di belakang: dua pengaman untuk hal yang
   sama, karena satu saja tidak cukup. Kartu isian berlatar putih menutupinya
   sepenuhnya di area padat, jadi ia hanya muncul di sela-sela — persis seperti
   kop surat. */
.plw-biofull__cap {
    position: absolute;
    inset: 0;
    z-index: 0;
    display: grid;
    place-items: center;
    pointer-events: none;
    user-select: none;
    overflow: hidden;
}
.plw-biofull__cap img {
    width: min(46vw, 560px);
    max-width: 80%;
    height: auto;
    opacity: .055;
    filter: grayscale(1);
    transform: rotate(-8deg);
}
@media (max-width: 720px) {
    .plw-biofull__cap img { width: 78vw; opacity: .045; }
}

/* Kepala menempel, dan sengaja gelap: ia batas antara "layar penuh" dan
   jendela detail di belakangnya. Kepala putih membuat keduanya menyatu, dan
   orang kehilangan jejak sedang berada di lapis yang mana. */
.plw-biofull__head {
    flex: none;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px clamp(16px, 3vw, 40px);
    color: #fff;
    background: linear-gradient(115deg, #4338ca 0%, #4f46e5 45%, #6366f1 100%);
    box-shadow: 0 6px 22px -12px rgba(15, 23, 42, .5);
}
.plw-biofull__ico {
    flex: none; width: 42px; height: 42px; border-radius: 13px;
    display: grid; place-items: center; font-size: 19px; color: #fff;
    background: rgba(255, 255, 255, .16);
    border: 1px solid rgba(255, 255, 255, .22);
}
.plw-biofull__t { flex: 1; min-width: 0; line-height: 1.32; }
.plw-biofull__t b { display: block; font-size: 17px; letter-spacing: -.01em; }
.plw-biofull__t small {
    display: block; font-size: 12.5px; color: rgba(255, 255, 255, .78);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.plw-biofull__x {
    flex: none; width: 38px; height: 38px; border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, .26);
    background: rgba(255, 255, 255, .12); color: #fff;
    cursor: pointer; transition: all .16s;
}
.plw-biofull__x:hover { background: rgba(255, 255, 255, .24); transform: rotate(90deg); }

/* YANG MENGGULIR ADALAH INI, bukan halamannya. Kepala dan kaki tinggal diam
   supaya tombol Tutup tak pernah ikut hilang ke bawah pada biodata yang
   panjangnya lima puluh isian. */
.plw-biofull__body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding: clamp(18px, 2.4vw, 34px) clamp(16px, 3vw, 40px) 48px;

    /* scroll-behavior: smooth DICABUT. Ia membuat SETIAP ketukan roda jadi
       animasi bergerak sendiri; di daftar sepanjang lima puluh isian, animasi
       itu belum selesai saat ketukan berikutnya datang, dan hasilnya persis
       terbaca sebagai "patah-patah dan delay". Guliran bawaan peramban sudah
       halus — yang perlu dilakukan hanya tidak menghalanginya. */

    /* Guliran berhenti di sini, tidak menular ke jendela di belakangnya.
       Tanpa ini, mencapai ujung daftar membuat halaman di belakang ikut
       bergerak — dan itu terasa seperti tersendat, bukan seperti mentok. */
    overscroll-behavior: contain;

    /* Lapisan sendiri: peramban cukup menggeser hasil gambar yang sudah jadi,
       bukan menggambar ulang isi di bawahnya tiap bingkai. */
    transform: translateZ(0);
    position: relative;
    z-index: 1;
}
/* Pembungkus isi — inilah yang berhenti melebar, bukan kotaknya. */
.plw-biofull__body > * { max-width: 1680px; margin-left: auto; margin-right: auto; }

/* Kepala kelompok jadi penanda yang benar-benar terbaca saat digulir cepat. */
/* Kepala kelompok menempel saat digulir — latarnya SOLID, bukan gradien.
   Gradien pada elemen sticky digambar ulang tiap bingkai selama guliran, dan
   di daftar sepanjang ini ongkosnya terasa. */
.plw-biofull__body .plw-ghead {
    position: sticky;
    top: calc(clamp(18px, 2.4vw, 34px) * -1);
    z-index: 2;
    background: #f7f8fc;
    padding-top: 14px;
    padding-bottom: 10px;
    box-shadow: 0 8px 12px -10px rgba(15, 23, 42, .18);
}

.plw-biofull__foot {
    flex: none;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 11px clamp(16px, 3vw, 40px);
    border-top: 1px solid #e6eaf3;
    background: #fff;
    font-size: 11.5px; color: #94a3b8;
}
.plw-biofull__foot i { color: #6366f1; }

/* Kolomnya mengikuti lebar yang tersedia. Di layar lebar, empat kolom memakai
   ruangnya untuk MEMENDEKKAN gulungan — bukan meregangkan tiap barisnya. */
.plw-biofull__body .plw-fields { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.plw-biofull__body .plw-field.is-panjang { grid-column: 1 / -1; }

@media (min-width: 1600px) {
    .plw-biofull__body .plw-fields { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
@media (max-width: 1100px) {
    .plw-biofull__body .plw-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
    .plw-biofull__head { padding: 12px 14px; gap: 10px; }
    .plw-biofull__ico { width: 36px; height: 36px; font-size: 16px; }
    .plw-biofull__t b { font-size: 15px; }
    .plw-biofull__body { padding: 14px 14px 40px; }
    .plw-biofull__body .plw-fields { grid-template-columns: 1fr; }
    .plw-biofull__foot span { display: none; }
    .plw-biofull__foot { justify-content: stretch; }
    .plw-biofull__foot .wca-btn { width: 100%; }
}

.plw-biofull__btn {
    display: inline-flex; align-items: center; gap: 6px;
    border: 1px solid #e2e8f0; background: #fff; color: #475569;
    border-radius: 10px; padding: 6px 11px;
    font-family: inherit; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all .15s;
}
.plw-biofull__btn:hover { border-color: #a5b4fc; color: #4338ca; background: #f8f9ff; }
.plw-biofull__btn i { font-size: 12px; }

/* Dua kolom biodata butuh ruang nilai yang layak; di bawah 720px keduanya
   menyisakan lebar selebar dua kata dan setiap alamat terpotong tiga baris. */
@media (max-width: 720px) {
    .plw-fields { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 520px) {
    .plw-fm__grid { grid-template-columns: minmax(0, 1fr); }
    .plw-fm__pbtn { width: 100%; justify-content: center; }
}
@media (max-width: 640px) {
    .plw-fgrid { grid-template-columns: minmax(0, 1fr); }
    .plw-f--cari { grid-column: span 1; }
    .plw-frow__isi > .plw-f--tgl { flex-basis: 100%; }
    .plw-fquick { width: 100%; }
    .plw-fq { flex: 1; text-align: center; }
    .plw-moderow { align-items: flex-start; }
    .plw-modes { width: 100%; }
    .plw-mode { flex: 1; justify-content: center; }
    .plw-pagerbar { justify-content: center; }
    .plw-pagerbar__info, .plw-pagerbar__per { width: 100%; justify-content: center; text-align: center; }
}
/* ── PONSEL: PANEL PENYARING JADI LEMBAR TARIK, DIPANGGIL FAB ──────────────
   Enam isian bertumpuk memakan satu layar penuh sebelum satu kandidat pun
   terlihat. Yang dibuka orang di ponsel hampir selalu papannya — penyaring
   dipakai sekali lalu tidak disentuh lagi sepanjang sesi. Jadi ia dipindahkan
   ke lembar yang muncul saat dipanggil, dan yang tinggal di layar cuma satu
   tombol bulat yang ikut membawa hitungan penyaring aktifnya.

   Lapisannya sengaja DI BAWAH 1200 (topeng modal) supaya modal peninjauan
   tetap menutupi keduanya; di atas 1030 (sidebar) supaya lembarnya tidak
   tertimbun navigasi. */
@media (max-width: 767.98px) {
    .plw-toolbar {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 1095;
        margin: 0; max-height: 84vh;
        display: flex; flex-direction: column;
        border-radius: 18px 18px 0 0; border-bottom: 0;
        box-shadow: 0 -14px 40px rgba(15, 23, 42, .2);
        transform: translateY(101%);
        transition: transform .26s cubic-bezier(.22, 1, .36, 1);
    }
    .plw-toolbar.is-buka { transform: translateY(0); }
    /* Pegangan lembar — penanda bahwa yang muncul ini bisa ditutup. */
    .plw-fhead { position: relative; flex: none; padding-top: 15px; }
    .plw-fhead::before {
        content: ''; position: absolute; top: 7px; left: 50%; transform: translateX(-50%);
        width: 38px; height: 4px; border-radius: 999px; background: #e2e5f0;
    }
    .plw-fbody { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
    /* Ditutup lewat FAB atau tirai; tombol lipat jadi pilihan ketiga yang
       menyelesaikan hal yang sama. */
    .plw-fhead__tgl { display: none; }

    .plw-ftirai {
        display: block; position: fixed; inset: 0; z-index: 1090;
        background: rgba(15, 23, 42, .45); backdrop-filter: blur(2px);
    }

    .plw-fab {
        display: inline-grid; place-items: center; position: fixed;
        right: 16px; bottom: calc(16px + env(safe-area-inset-bottom, 0px)); z-index: 1096;
        width: 54px; height: 54px; border-radius: 50%; border: none; cursor: pointer;
        color: #fff; font-size: 19px;
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        box-shadow: 0 12px 28px rgba(99, 102, 241, .42);
        transition: transform .16s, box-shadow .16s;
    }
    .plw-fab:active { transform: scale(.94); }
    /* Ada penyaring yang menyala: warnanya berubah, karena papan yang
       tersaring tanpa penanda apa pun terbaca sebagai data yang hilang. */
    .plw-fab.is-aktif { background: linear-gradient(135deg, #f59e0b, #ea580c); box-shadow: 0 12px 28px rgba(234, 88, 12, .42); }
    .plw-fab__n {
        position: absolute; top: -3px; right: -3px; min-width: 21px; height: 21px; padding: 0 5px;
        border-radius: 999px; background: #fff; color: #b45309; border: 2px solid #ea580c;
        font-size: 10.5px; font-weight: 800; display: grid; place-items: center;
    }
}

/* Ponsel: tombol keputusan menumpuk penuh selebar drawer. Di lebar sekecil ini
   tiga tombol sebaris membuat labelnya terpotong elipsis — lebih baik satu per
   satu, dan sekalian lebih aman disentuh. */
@media (max-width: 575.98px) {
    .plw-actions, .plw-actions.is-padat { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .plw-actions.is-padat .plw-btn-putus { font-size: 12.5px; padding-left: 10px; padding-right: 10px; gap: 6px; }
    /* Kaki boleh sedikit lebih tinggi di ponsel: tombolnya menumpuk dua kolom,
       dan badan modal di layar setinggi itu tetap menggulir dengan nyaman. */
    .plw-foot { max-height: 38vh; }
    .plw-actions2 { gap: 7px; }
    .plw-actions2__info { margin-left: 0; }
}

/* ══════════════════════════════════════════════════════════════════════════
   RAPOR TES — kartu padat, rincian di balik satu tombol
   ══════════════════════════════════════════════════════════════════════════
   Bentuk lama menaruh SEMUANYA sekaligus di dalam kartu: nama, tag, jadwal,
   catatan penilaian berformat, berkas kandidat, berkas tim, lalu deretan
   tombol. Untuk satu wawancara itu masih terbaca. Untuk tahap berisi empat
   sampai enam tes offline — bentuk yang justru paling sering dipakai — satu
   layar berubah jadi dinding teks setinggi beberapa gulungan, dan tombol yang
   benar-benar perlu ditekan tenggelam di tengahnya.

   Sekarang kartunya menyatakan KEADAAN (nama, status, apa yang ditunggu) dan
   menawarkan TINDAKAN. Bahan bacaan — catatan & berkas — ada di balik satu
   tombol berjumlah, dibuka hanya untuk aktivitas yang sedang ditinjau.
   ═════════════════════════════════════════════════════════════════════════ */

/* Kartu jadi GRID, bukan flex sebaris.
   Flex + flex-wrap membuat pil status melompat ke baris sendiri pada lebar
   tertentu lalu kembali naik pada lebar lain — posisinya berubah-ubah dan mata
   kehilangan tempat membacanya. Grid mengunci kolomnya: ikon | isi | status. */
.plw-test {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: start;
    gap: 6px 11px;
    padding: 12px 13px;
}
.plw-test__ico { grid-row: 1; align-self: center; }
.plw-test__main { grid-column: 2; min-width: 0; }
/* Skor & pil status berbagi kolom kanan, menumpuk ke bawah. */
.plw-test__score,
.plw-test__pill { grid-column: 3; justify-self: end; align-self: center; }
.plw-test__aksi { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 6px; }

/* Apa yang masih ditunggu dari aktivitas ini — kalimat yang sama dengan yang
   dipakai server saat menolak keputusan. */
.plw-test__nunggu {
    margin-top: 5px; display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 8px; font-size: 11px; font-weight: 700;
    color: #b45309; background: rgba(245, 158, 11, .1); border: 1px solid rgba(245, 158, 11, .26);
}

/* Tombol pembuka rincian. */
.plw-test__more {
    margin-top: 7px; display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border: 1px solid #e2e8f0; background: #fff; border-radius: 9px;
    font: inherit; font-size: 11px; font-weight: 700; color: #475569; cursor: pointer;
    transition: background .15s, border-color .15s, color .15s;
}
.plw-test__more:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
.plw-test__more.is-on { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
.plw-test__morecount {
    display: inline-grid; place-items: center; min-width: 17px; height: 17px; padding: 0 5px;
    border-radius: 999px; background: #eef2ff; color: #4338ca; font-size: 10px; font-weight: 800;
}
.plw-test__more.is-on .plw-test__morecount { background: #fff; }

.plw-test__detail {
    margin-top: 9px; padding: 11px 12px; border-radius: 11px;
    background: #fafbfe; border: 1px solid #eef1f8;
    display: flex; flex-direction: column; gap: 10px;
}
.plw-test__detail .plw-test__cat { margin-top: 0; }
.plw-test__detail .plw-test__cat.is-kaya { background: #fff; }

/* ── BERKAS: kepala dan daftarnya DIPISAH BARIS ────────────────────────────
   Dulu label, seluruh nama berkas, dan lencana "dinyatakan lengkap" berdesakan
   di satu baris flex-wrap. Begitu kandidat berhasil mengunggah, nama berkas
   yang panjang mendorong lencananya ke posisi yang berubah-ubah — kadang
   terjepit di antara dua berkas, kadang menggantung sendirian. Kepala terpisah
   membuat lencananya selalu di tempat yang sama, apa pun isinya. */
.plw-test__kirim { margin-top: 0; display: flex; flex-direction: column; align-items: stretch; gap: 6px; font-size: 11px; }
.plw-test__kirimhead { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.plw-test__files { display: flex; flex-wrap: wrap; gap: 6px; min-width: 0; }
/* Nama panjang DIPOTONG, tidak mendorong tetangganya. Judul lengkapnya tetap
   terbaca lewat tooltip, dan isinya lewat modal. */
.plw-test__kirimfile { max-width: 100%; min-width: 0; }
.plw-test__filenama { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plw-test__kirimstat { flex: 0 0 auto; }
.plw-test__kirimkosong {
    margin-top: 6px; display: flex; align-items: flex-start; gap: 6px; font-size: 11px;
    line-height: 1.45; color: #94a3b8; font-weight: 700;
}
.plw-test__kirimkosong > .bi { flex: none; margin-top: 1px; }

/* "Apa yang belum tuntas" di atas tombol keputusan — chip mendatar, bukan
   butir bertumpuk (lihat catatan di template). */
.plw-kuota__isi { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 5px; }
/* Tombol tutup keterangan. Kecil dan tak berlatar: ia jalan keluar, bukan
   tindakan — dan tidak boleh terbaca sebagai "batalkan" pada bilah berwarna. */
.plw-kuota__x {
    appearance: none; cursor: pointer; flex: 0 0 auto; align-self: flex-start;
    width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;
    border: none; background: transparent; border-radius: 7px;
    color: #b45309; font-size: 11px; opacity: .65; transition: all .16s;
}
.plw-kuota__x:hover { opacity: 1; background: rgba(255, 255, 255, 0.7); }
.plw-kuota__chips { display: flex; flex-wrap: wrap; gap: 6px; }
.plw-kuota__chip { display: inline-flex; align-items: baseline; gap: 6px; max-width: 100%; padding: 4px 10px; border-radius: 8px; background: rgba(255, 255, 255, 0.72); border: 1px solid #f2e4c4; line-height: 1.35; min-width: 0; }
.plw-kuota__chip b { flex: 0 0 auto; font-size: 11.5px; font-weight: 800; color: #92660a; }
.plw-kuota__chip em { min-width: 0; font-style: normal; font-size: 11px; color: #a08040; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

@media (max-width: 640px) {
    /* Kepala modal: avatar + nama + tombol cetak tak muat sebaris di ponsel. */
    .plw-hero { padding-top: 15px; }
    .plw-hero__row { gap: 10px; }
    .plw-hero__avatar { width: 44px; height: 44px; border-radius: 13px; font-size: 15px; }
    .plw-hero__name { font-size: 17px; }
    .plw-drawer__cetak { order: 3; }
    .plw-tabpane { gap: 14px; }
    .plw-alur { padding: 15px 16px; }
    .plw-test { padding: 11px; gap: 5px 9px; }
    /* Pil status turun menemani isinya — di lebar ini kolom ketiga
       menyisakan terlalu sedikit ruang untuk nama aktivitas. */
    .plw-test { grid-template-columns: auto minmax(0, 1fr); }
    .plw-test__score, .plw-test__pill { grid-column: 2; justify-self: start; }
    .plw-test__aksi > * { flex: 1 1 auto; justify-content: center; }
}

/* Layar sangat lebar: kartu boleh bernapas, tapi teksnya tidak boleh melar. */
@media (min-width: 1920px) {
    .plw-test { padding: 14px 16px; }
    .plw-test__name { font-size: 13.5px; }
}

/* Petunjuk & pintasan di bawah bidang isian. */
.plw-fld__hint { margin: 5px 0 0; font-size: 11px; line-height: 1.5; color: #94a3b8; }
.plw-fld__isi {
    margin-top: 6px; display: inline-flex; align-items: center; gap: 5px;
    border: 1px dashed #cbd5e1; background: #fff; border-radius: 8px; padding: 4px 9px;
    font: inherit; font-size: 11px; font-weight: 700; color: #475569; cursor: pointer;
}
.plw-fld__isi:hover { background: #f8fafc; border-color: #94a3b8; color: #1e293b; }
/* Segmented mode: tiga bentuk harus muat di modal sempit tanpa terpotong. */
.plw-seg { display: flex; flex-wrap: wrap; gap: 6px; }
.plw-seg .plw-seg__b { flex: 1 1 130px; min-width: 0; justify-content: center; }

/* PENANDA PEMBACA — dipakai di mana pun ada dua kotak isian berdampingan yang
   tujuannya berbeda hanya pada SIAPA YANG MEMBACANYA. Warnanya kontras penuh
   (biru vs abu) karena inilah pembeda satu-satunya; bila ia selembut label
   biasa, matanya terlewat dan orang mengisi kotak yang salah. */
.plw-lihat {
    display: inline-flex; align-items: center; gap: 4px; margin-left: 7px;
    padding: 1px 7px; border-radius: 999px; font-size: 9.5px; font-weight: 800;
    letter-spacing: .04em; vertical-align: middle; white-space: nowrap;
}
.plw-lihat.is-publik   { background: rgba(2, 132, 199, .12); color: #0369a1; border: 1px solid rgba(2, 132, 199, .3); }
.plw-lihat.is-internal { background: rgba(100, 116, 139, .12); color: #475569; border: 1px solid rgba(100, 116, 139, .26); }
/* Peringatan privasi menempel PADA bidangnya, bukan melayang di bawah kotak
   hasil — supaya terbaca sebagai aturan untuk kotak yang sedang diisi. */
.plw-mform .plw-fld .plw-note.is-lock { margin-top: 6px; }

/* Peringatan akibat yang tidak bisa ditarik — lebih tegas dari petunjuk biasa,
   karena dua dari tiga jawaban menutup lamaran seketika. */
.plw-fld__hint.is-tegas { color: #b45309; font-weight: 700; }
.plw-fld__hint.is-tegas b { color: #92400e; }

/* ── PHONE SCREENING ─────────────────────────────────────────────────────── */
.plw-test__skr {
    border-color: rgba(99, 102, 241, 0.35) !important;
    color: #4338ca !important;
}
.plw-test__skr:hover { background: rgba(99, 102, 241, 0.07) !important; }
.plw-skrmuat { padding: 48px; text-align: center; color: #94a3b8; font-size: 13px; }
</style>
