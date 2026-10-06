<!--
  Master MPP — TATA LETAK SPLIT (daftar kiri + detail kanan).

  Mengikuti rancangan docs/refrences/v2/.../Realisasi Kuota MPP.dc.html, dengan
  dua perbedaan yang disengaja:

    1. TOMBOL "EKSPOR EXCEL" TIDAK DIPASANG. Diminta tidak dimunculkan, dan
       memang belum ada yang menghasilkan berkasnya — tombol yang tidak
       melakukan apa-apa lebih buruk daripada tombol yang tidak ada.

    2. Cangkang halaman (rail kiri, navbar "Halo, ADMIN", jam) tidak ikut
       dibangun ulang. Itu milik AppSidebar/AppTopbar yang sudah membungkus
       seluruh halaman admin; menggambarnya lagi di sini akan menghasilkan dua
       navbar bertumpuk.

  ── KENAPA SPLIT, BUKAN GRID KARTU ─────────────────────────────────────────

  Pertanyaan yang dibawa orang ke halaman ini hampir selalu tentang SATU MPP —
  "kuotanya sudah terisi berapa", "tenggatnya kapan", "kenapa diperpanjang".
  Grid kartu menjawab "ada MPP apa saja", lalu menutup jawabannya di balik
  panel yang harus dibuka-tutup satu per satu. Split menahan daftarnya tetap
  terlihat sementara detailnya dibaca, jadi berpindah antar MPP tidak
  menghilangkan konteks.
-->
<template>
    <div class="mms">
        <!-- ══ KIRI: DAFTAR MPP ══════════════════════════════════════════ -->
        <aside class="mms-pane mms-left" :class="{ 'is-hidden-mobile': mobileDetail }">
            <div class="mms-left__filter">
                <!-- BARIS ATAS: pencarian + satu tombol pemicu penyaring.
                     Selebihnya disembunyikan sampai diminta — lihat alasannya
                     di komentar `filterBuka` pada blok script. -->
                <div class="mms-srchrow">
                    <div class="mms-srch">
                        <i class="bi bi-search"></i>
                        <input
                            :value="q"
                            type="text"
                            placeholder="Cari jabatan / no. MPP…"
                            @input="$emit('update:q', $event.target.value)"
                        />
                    </div>

                    <button
                        type="button"
                        class="mms-ftgl"
                        :class="{ 'is-on': filterBuka, 'is-aktif': adaFilter }"
                        :title="filterBuka ? 'Tutup penyaring' : 'Saring MPP'"
                        :aria-expanded="filterBuka"
                        @click="filterBuka = !filterBuka"
                    >
                        <i class="bi" :class="filterBuka ? 'bi-x-lg' : 'bi-funnel-fill'"></i>
                        <!-- Titik kecil menandai ada penyaring yang MASIH menyala
                             walau lacinya tertutup. Tanpa itu, daftar yang
                             terpotong terbaca sebagai MPP yang hilang. -->
                        <em v-if="adaFilter && !filterBuka"></em>
                    </button>
                </div>

                <!-- ══ LACI PENYARING ══════════════════════════════════
                     Seluruh isinya hanya digambar saat diminta. -->
                <div v-show="filterBuka" class="mms-flaci">

                <!-- Dua baris tab: KETERISIAN dan SLA. Sengaja dipisah — keduanya
                     pertanyaan berbeda ("kuotanya sudah penuh?" vs "tenggatnya
                     aman?") dan sebuah MPP bisa penuh sekaligus lewat SLA. -->
                <div class="mms-tabs">
                    <button
                        v-for="t in tabIsi"
                        :key="t.key"
                        type="button"
                        class="mms-tab"
                        :class="{ 'is-on': fIsi === t.key }"
                        @click="$emit('update:fIsi', t.key)"
                    >
                        {{ t.label }}<span class="mms-tab__n">{{ t.count }}</span>
                    </button>
                </div>

                <div class="mms-tabs">
                    <button
                        v-for="t in tabSla"
                        :key="t.key"
                        type="button"
                        class="mms-tab"
                        :class="[{ 'is-on': fSla === t.key }, `is-${t.key}`]"
                        @click="$emit('update:fSla', t.key)"
                    >
                        <span class="mms-tab__dot"></span>{{ t.label }}<span class="mms-tab__n">{{ t.count }}</span>
                    </button>
                </div>

                <!-- ── PIC: MULTI-SELECT ────────────────────────────────────
                     Satu dropdown yang menerima BANYAK pilihan, sepola penyaring
                     lain di modul ini (lihat master-feedback) — bukan keping yang
                     ditambah satu per satu lewat tombol "+".

                     `filterable` dipasang karena daftar PIC tumbuh mengikuti
                     jumlah rekruter; mengetik dua huruf jauh lebih cepat daripada
                     menggulir. `collapse-tags` menahan pilihan yang banyak agar
                     tidak mendorong daftar MPP di bawahnya turun berlipat —
                     tooltipnya tetap menyebut seluruh nama yang terpilih. -->
                <!-- ── JENIS PROGRAM ────────────────────────────────────────
                     MT dan Rekrutmen dipisah karena keduanya dinilai dengan
                     aturan yang berbeda: MT tidak terikat SLA level sama sekali.
                     Tanpa penyaring ini, memeriksa "MPP rekrutmen mana yang
                     hampir lewat tenggat" selalu terganggu baris MT yang memang
                     tidak punya tenggat. -->
                <div class="mms-tabs mms-tabs--jenis">
                    <button
                        v-for="t in tabJenis"
                        :key="t.key"
                        type="button"
                        class="mms-tab"
                        :class="{ 'is-on': fJenis === t.key }"
                        @click="$emit('update:fJenis', t.key)"
                    >
                        {{ t.label }}<span class="mms-tab__n">{{ t.count }}</span>
                    </button>
                </div>

                <div class="mms-picsel">
                    <el-select
                        :model-value="fPic"
                        placeholder="PIC — semua"
                        size="small"
                        clearable
                        filterable
                        multiple
                        collapse-tags
                        collapse-tags-tooltip
                        :max-collapse-tags="1"
                        style="width: 100%"
                        @update:model-value="$emit('update:fPic', $event)"
                    >
                        <el-option v-for="o in picOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </div>

                <!-- ── RENTANG TANGGAL PERIODE ──────────────────────────────
                     Diketik/dipilih admin, bukan dipilih dari daftar bulan yang
                     sudah ada. Pertanyaan yang dijawabnya beda: "apa yang jatuh
                     tempo antara A dan B" hampir tidak pernah pas satu bulan.

                     Kedua ujungnya boleh diisi sendiri-sendiri — el-date-picker
                     mengirim null pada sisi yang dikosongkan, dan server memang
                     menerimanya begitu. -->
                <div class="mms-tgl">
                    <el-date-picker
                        :model-value="rentang"
                        type="daterange"
                        size="small"
                        unlink-panels
                        range-separator="→"
                        start-placeholder="Dari tanggal"
                        end-placeholder="Sampai"
                        value-format="YYYY-MM-DD"
                        format="DD MMM YYYY"
                        style="width: 100%"
                        @update:model-value="$emit('update:rentang', $event)"
                    />
                </div>

                </div>
                <!-- ══ akhir laci penyaring ══ -->

                <div class="mms-left__foot">
                    <span class="mms-count">{{ totalMpp }} MPP</span>
                    <button v-if="adaFilter" type="button" class="mms-clear" @click="$emit('bersihkan')">
                        Bersihkan filter
                    </button>
                </div>
            </div>

            <div class="mms-list">
                <button
                    v-for="m in daftar"
                    :key="m.noTransaksi"
                    type="button"
                    class="mms-row"
                    :class="{ 'is-on': m.noTransaksi === terpilih }"
                    @click="$emit('pilih', m.noTransaksi)"
                >
                    <span class="mms-row__rail" :class="railKelas(m)"></span>

                    <span class="mms-row__top">
                        <span class="mms-row__title">{{ m.jabatan?.nama || 'Jabatan belum diisi' }}</span>
                        <span class="mms-row__type" :class="{ 'is-mt': m.jenisProgram === 'MT' }">
                            {{ m.jenisProgram === 'MT' ? 'MT' : 'REK' }}
                        </span>
                    </span>

                    <span class="mms-row__chips">
                        <span v-if="kursiPenuh(m)" class="mms-chip is-full">
                            <i class="bi bi-check-lg"></i> TERPENUHI
                        </span>
                        <span class="mms-chip" :class="`is-${slaKunci(m)}`" :title="slaLabel(m)">
                            <span class="mms-chip__dot"></span>{{ slaPendek(m) }}
                        </span>
                    </span>

                    <span class="mms-row__pic">
                        <span class="mms-ava">{{ (m.penanggungJawab?.nama || '?').charAt(0).toUpperCase() }}</span>
                        <span class="mms-row__picname">{{ m.penanggungJawab?.nama || '—' }}</span>
                        <span class="mms-row__sep"></span>
                        <span class="mms-row__code">{{ m.noTransaksi }}</span>
                    </span>

                    <span class="mms-row__quota">
                        <span class="mms-row__qtxt" :class="{ 'is-full': kursiPenuh(m) }">{{ kuotaTeks(m) }}</span>
                        <span class="mms-row__pct" :class="{ 'is-full': kursiPenuh(m) }">{{ pctTeks(m) }}</span>
                    </span>

                    <span class="mms-track"><span class="mms-bar" :class="barKelas(m)" :style="{ width: Math.max(pct(m), 3) + '%' }"></span></span>
                </button>

                <!-- Dua sebab yang berbeda, dua kalimat yang berbeda: daftar
                     kosong karena PENYARING (ada jalan keluarnya) atau karena
                     memang BELUM ADA MPP sama sekali (yang dibutuhkan tombol
                     buat baru, bukan tombol bersihkan). -->
                <div v-if="!daftar.length" class="mms-nihil">
                    <span class="mms-nihil__ico"><i class="bi" :class="adaFilter ? 'bi-funnel' : 'bi-clipboard-x'"></i></span>
                    <b>{{ adaFilter ? 'Tidak ada MPP yang cocok' : 'Belum ada transaksi MPP' }}</b>
                    <small>
                        {{ adaFilter
                            ? 'Coba longgarkan penyaring di atas — pencarian, jenis, PIC, atau rentang tanggalnya.'
                            : 'Buat transaksi MPP lewat tombol di kanan atas untuk mulai membuka lowongan.' }}
                    </small>
                    <button v-if="adaFilter" type="button" class="mms-nihil__btn" @click="$emit('bersihkan')">
                        <i class="bi bi-x-circle"></i> Bersihkan filter
                    </button>
                </div>
            </div>

            <!-- ── PAGINATION PANEL KIRI ────────────────────────────────────
                 Daftarnya dipenggal di server (12 per halaman). Tanpa pengatur
                 halaman di sini, MPP di halaman kedua dan seterusnya tidak punya
                 satu pun jalan untuk dibuka — panel kiri diam-diam jadi "12 MPP
                 pertama saja", dan tidak ada yang menunjukkan bahwa ada sisanya. -->
            <div v-if="totalHal > 1" class="mms-lpager">
                <span>Hal {{ hal }} / {{ totalHal }}</span>
                <div class="mms-lpager__btns">
                    <button type="button" :disabled="hal <= 1" title="Sebelumnya" @click="$emit('update:hal', hal - 1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button type="button" :disabled="hal >= totalHal" title="Berikutnya" @click="$emit('update:hal', hal + 1)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </aside>

        <!-- ══ KANAN: DETAIL ═════════════════════════════════════════════ -->
        <section class="mms-pane mms-right" :class="{ 'is-hidden-mobile': !mobileDetail }">
            <template v-if="detail">
                <div class="mms-head">
                    <div class="mms-head__row">
                        <button type="button" class="mms-back" title="Kembali ke daftar" @click="$emit('kembali')">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <span class="mms-head__ico"><i class="bi bi-briefcase-fill"></i></span>

                        <div class="mms-head__txt">
                            <div class="mms-head__meta">
                                <span class="mms-head__code">{{ detail.noTransaksi }}</span>
                                <span class="mms-row__type" :class="{ 'is-mt': detail.jenisProgram === 'MT' }">
                                    {{ detail.jenisProgram === 'MT' ? 'Management Trainee' : 'Rekrutmen' }}
                                </span>
                                <span class="mms-stat" :class="detail.status === 'AKTIF' ? 'is-aktif' : 'is-batal'">
                                    <span class="mms-chip__dot"></span>{{ detail.status === 'AKTIF' ? (detail.selesai ? 'Selesai' : 'Berjalan') : 'Dibatalkan' }}
                                </span>
                            </div>
                            <h2>{{ detail.jabatan?.nama || 'Jabatan belum diisi' }}</h2>
                            <div class="mms-head__sub">
                                {{ detail.divisi?.nama || '—' }}<template v-if="detail.subDivisi"> / {{ detail.subDivisi.nama }}</template>
                                · {{ detail.lokasi?.nama || '—' }}
                            </div>
                        </div>

                        <!-- DUA KARTU BERDAMPINGAN, dan itu disengaja: keduanya
                             sering dikira orang yang sama padahal tidak. PJ
                             memegang lowongannya; "diinput oleh" akun yang
                             mengetik barisnya. Ditaruh DI KEPALA — bukan di kartu
                             yang harus digulir — sebab pertanyaan "ini siapa yang
                             input, kapan" muncul bersamaan dengan melihat MPP-nya,
                             bukan sesudah membaca seluruh detailnya. -->
                        <div class="mms-orang">
                            <div class="mms-picard">
                                <span class="mms-ava mms-ava--lg">{{ (detail.penanggungJawab?.nama || '?').charAt(0).toUpperCase() }}</span>
                                <span class="mms-picard__txt">
                                    <small>PENANGGUNG JAWAB</small>
                                    <b>{{ detail.penanggungJawab?.nama || '—' }}</b>
                                    <em>{{ detail.level?.nama || '—' }}</em>
                                </span>
                            </div>

                            <div class="mms-picard mms-picard--input" :title="judulInput">
                                <span class="mms-ava mms-ava--lg mms-ava--input">
                                    <i class="bi bi-pencil-square"></i>
                                </span>
                                <span class="mms-picard__txt">
                                    <small>DIINPUT OLEH</small>
                                    <b>{{ detail.dibuat?.oleh || '—' }}</b>
                                    <em>{{ tglJam(detail.dibuat?.pada) }}</em>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- STRIP SLA — inti halaman ini. Selalu ada bila MPP-nya punya
                         tenggat, berikut tombol Perpanjang di ujung kanannya. -->
                    <div v-if="adaSla" class="mms-strip" :class="`is-${slaKunci(detail)}`">
                        <div class="mms-strip__kiri">
                            <span class="mms-strip__ico"><i class="bi bi-stopwatch"></i></span>
                            <span class="mms-strip__ttl">SLA {{ detail.sla.hari }} hari kerja</span>
                            <span class="mms-strip__track">
                                <span class="mms-strip__bar" :style="{ width: pakaiPersen + '%' }"></span>
                            </span>
                        </div>
                        <div class="mms-strip__kanan">
                            <span class="mms-strip__sisa"><span class="mms-chip__dot"></span>{{ sisaTeks }}</span>
                            <span class="mms-strip__due">
                                <s v-if="diperpanjang">{{ tgl(detail.sla.batasAwal) }}</s>
                                {{ tgl(detail.sla.batas) }}
                            </span>
                            <button type="button" class="mms-strip__btn" @click="$emit('perpanjang', detail)">
                                <i class="bi bi-calendar-plus"></i> Perpanjang
                            </button>
                        </div>
                    </div>

                    <!-- MT: dikatakan, bukan dibiarkan kosong tanpa sebab. -->
                    <div v-else class="mms-strip is-netral">
                        <div class="mms-strip__kiri">
                            <span class="mms-strip__ico"><i class="bi bi-mortarboard-fill"></i></span>
                            <span class="mms-strip__ttl">
                                {{ detail.jenisProgram === 'MT' ? 'Program MT tidak terikat SLA level' : 'MPP ini tidak menyimpan ketentuan SLA' }}
                            </span>
                        </div>
                    </div>

                    <div class="mms-acts">
                        <button type="button" class="mms-act is-primary" @click="$emit('ubah', detail)">
                            <i class="bi bi-pencil"></i> Ubah
                        </button>
                        <button type="button" class="mms-act" @click="$emit('toggle-selesai', detail)">
                            <i class="bi" :class="detail.selesai ? 'bi-arrow-counterclockwise' : 'bi-check2-circle'"></i>
                            {{ detail.selesai ? 'Belum Selesai' : 'Tandai Selesai' }}
                        </button>
                        <button v-if="adaSla" type="button" class="mms-act is-gold" @click="$emit('perpanjang', detail)">
                            <i class="bi bi-calendar-plus"></i> Perpanjang SLA
                        </button>
                        <button
                            v-if="detail.status === 'AKTIF'"
                            type="button"
                            class="mms-act is-danger"
                            @click="$emit('batalkan', detail)"
                        >
                            <i class="bi bi-x-circle"></i> Batalkan
                        </button>
                        <button v-else type="button" class="mms-act" @click="$emit('aktifkan', detail)">
                            <i class="bi bi-arrow-counterclockwise"></i> Aktifkan Kembali
                        </button>
                    </div>

                    <div class="mms-rtabs">
                        <button
                            v-for="t in tabKanan"
                            :key="t.key"
                            type="button"
                            class="mms-rtab"
                            :class="{ 'is-on': tab === t.key }"
                            @click="$emit('update:tab', t.key)"
                        >
                            <i class="bi" :class="t.ikon"></i> {{ t.label }}
                            <span v-if="t.badge !== ''" class="mms-rtab__n">{{ t.badge }}</span>
                        </button>
                    </div>
                </div>

                <!-- ── TAB: KANDIDAT ──────────────────────────────────────── -->
                <template v-if="tab === 'kandidat'">
                    <div class="mms-ctool">
                        <div class="mms-qinline">
                            <span class="mms-qinline__n">{{ kursi.terisi }}</span>
                            <span class="mms-qinline__d">/ {{ kursi.kuota }} kuota</span>
                            <span class="mms-track mms-track--mini">
                                <span class="mms-bar" :class="barKelas(detail)" :style="{ width: Math.max(pct(detail), 3) + '%' }"></span>
                            </span>
                            <span class="mms-qinline__p" :class="{ 'is-full': kursiPenuh(detail) }">{{ pctTeks(detail) }}</span>
                        </div>
                        <div class="mms-srch mms-srch--sm">
                            <i class="bi bi-search"></i>
                            <input :value="cq" type="text" placeholder="Cari kandidat…" @input="$emit('update:cq', $event.target.value)" />
                        </div>
                    </div>

                    <div v-if="kandidatMemuat" class="mms-kosong">Memuat kandidat…</div>

                    <template v-else-if="kandidat.length">
                        <div class="mms-chead">
                            <span>KANDIDAT</span><span>PROGRAM</span><span>TANGGAL DITERIMA</span><span>LOKASI</span><span></span>
                        </div>
                        <div v-for="c in kandidat" :key="c.kode" class="mms-crow">
                            <span class="mms-crow__who">
                                <span class="mms-ava">{{ inisial(c.nama) }}</span>
                                <span class="mms-crow__id">
                                    <b>{{ c.nama }}</b>
                                    <small>{{ c.kode }}</small>
                                </span>
                            </span>
                            <span class="mms-cell">{{ c.program }}</span>
                            <span class="mms-cell">{{ tgl(c.diterimaPada) }}</span>
                            <span class="mms-cell">{{ c.lokasi || '—' }}</span>
                            <!-- Satu-satunya tombol di baris ini, dan sengaja
                                 begitu: halaman MPP menjawab "kursi ini diisi
                                 siapa". Memutuskan nasib kandidat tetap
                                 pekerjaan worklist. -->
                            <span class="mms-cell mms-cell--last">
                                <button type="button" class="mms-detail" @click="$emit('lihat-kandidat', c)">
                                    <i class="bi bi-person-lines-fill"></i> Detail
                                </button>
                            </span>
                        </div>

                        <div v-if="kandidatHal > 1" class="mms-pager">
                            <span>{{ kandidatTeks }}</span>
                            <div class="mms-pager__btns">
                                <button type="button" :disabled="cpage <= 1" @click="$emit('update:cpage', cpage - 1)">
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                                <span class="mms-pager__now">{{ cpage }} / {{ kandidatHal }}</span>
                                <button type="button" :disabled="cpage >= kandidatHal" @click="$emit('update:cpage', cpage + 1)">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <div v-else class="mms-empty">
                        <span class="mms-empty__ico"><i class="bi bi-people"></i></span>
                        <div class="mms-empty__ttl">Belum ada kandidat diterima</div>
                        <div class="mms-empty__sub">
                            Kuota {{ kursi.kuota }} orang masih terbuka. Kandidat muncul di sini setelah
                            diloloskan pada tahap akhir.
                        </div>
                    </div>
                </template>

                <!-- ── TAB: INFORMASI ─────────────────────────────────────── -->
                <div v-else-if="tab === 'info'" class="mms-body">
                    <div class="mms-card">
                        <div class="mms-card__top">
                            <span class="mms-mlabel">REALISASI KUOTA</span>
                            <span class="mms-qbig">
                                <b>{{ kursi.terisi }}</b><small>/ {{ kursi.kuota }} orang</small>
                            </span>
                        </div>
                        <div class="mms-track mms-track--big">
                            <span class="mms-bar" :class="barKelas(detail)" :style="{ width: Math.max(pct(detail), 3) + '%' }"></span>
                        </div>
                        <!-- Satu titik per kursi — kuota kecil (2, 3 orang) lebih
                             terbaca sebagai titik daripada sebagai persen. -->
                        <div v-if="kursi.kuota > 0 && kursi.kuota <= 20" class="mms-pips">
                            <span v-for="n in kursi.kuota" :key="n" class="mms-pip" :class="{ 'is-on': n <= kursi.terisi }"></span>
                        </div>
                        <div class="mms-minis">
                            <div class="mms-mini"><small>KUOTA</small><b>{{ kursi.kuota }} orang</b></div>
                            <div class="mms-mini"><small>TERISI</small><b>{{ kursi.terisi }} orang</b></div>
                            <div class="mms-mini"><small>SISA</small><b>{{ Math.max(0, kursi.kuota - kursi.terisi) }} orang</b></div>
                            <div class="mms-mini"><small>STATUS</small><b>{{ kursiPenuh(detail) ? 'Terpenuhi' : 'Terbuka' }}</b></div>
                        </div>
                    </div>

                    <div v-if="adaSla" class="mms-card">
                        <div class="mms-card__top">
                            <span class="mms-mlabel">SLA PEMENUHAN</span>
                            <span class="mms-pill" :class="`is-${slaKunci(detail)}`">
                                <span class="mms-chip__dot"></span>{{ slaLabel(detail) }}
                            </span>
                        </div>
                        <p class="mms-hint">
                            Dihitung dalam <b>hari kerja</b> (Senin–Sabtu, dikurangi hari libur), dari
                            {{ tgl(detail.sla.mulai) }} sampai {{ tgl(detail.sla.batas) }}.
                        </p>
                        <div class="mms-track mms-track--big">
                            <span class="mms-bar" :class="`is-${slaKunci(detail)}`" :style="{ width: pakaiPersen + '%' }"></span>
                        </div>
                        <div class="mms-scale"><span>{{ tgl(detail.sla.mulai) }}</span><span>{{ tgl(detail.sla.batas) }}</span></div>
                        <div class="mms-minis">
                            <div class="mms-mini"><small>KETENTUAN</small><b>{{ detail.sla.hari }} hari kerja</b></div>
                            <div class="mms-mini"><small>TENGGAT ASLI</small><b>{{ tgl(detail.sla.batasAwal) }}</b></div>
                            <div class="mms-mini"><small>TENGGAT BERLAKU</small><b>{{ tgl(detail.sla.batas) }}</b></div>
                            <div class="mms-mini"><small>DIPERPANJANG</small><b>{{ diperpanjang ? diperpanjang + '×' : 'Belum pernah' }}</b></div>
                        </div>
                    </div>

                    <div class="mms-card">
                        <div class="mms-card__head">
                            <span class="mms-card__ico"><i class="bi bi-info-circle"></i></span>
                            <span class="mms-card__ttl">Informasi MPP</span>
                        </div>
                        <div class="mms-grid">
                            <div v-for="i in infoMpp" :key="i.k" class="mms-box"><small>{{ i.k }}</small><b>{{ i.v }}</b></div>
                        </div>
                    </div>

                    <div class="mms-card">
                        <div class="mms-card__head">
                            <span class="mms-card__ico"><i class="bi bi-briefcase"></i></span>
                            <span class="mms-card__ttl">Detail Pekerjaan</span>
                        </div>
                        <div class="mms-grid">
                            <div class="mms-box"><small>TIPE KERJA</small><b>{{ detail.employmentType?.nama || '—' }}</b></div>
                            <div class="mms-box"><small>LOKASI KERJA</small><b>{{ detail.workplaceType?.nama || '—' }}</b></div>
                            <div class="mms-box"><small>LEVEL PENGALAMAN</small><b>{{ detail.experienceLevel?.nama || '—' }}</b></div>
                        </div>
                        <div class="mms-desc">
                            <small>DESKRIPSI PEKERJAAN</small>
                            <p>{{ detail.deskripsi || '—' }}</p>
                        </div>
                    </div>

                    <div class="mms-2col">
                        <div class="mms-card">
                            <div class="mms-card__head">
                                <span class="mms-card__ico"><i class="bi bi-list-check"></i></span>
                                <span class="mms-card__ttl">Tanggung Jawab</span>
                                <span class="mms-card__n">{{ (detail.tanggungJawab || []).length }}</span>
                            </div>
                            <ul class="mms-ul">
                                <li v-for="(r, i) in detail.tanggungJawab" :key="i">
                                    <i class="bi bi-check-circle-fill is-green"></i><span>{{ r }}</span>
                                </li>
                                <li v-if="!(detail.tanggungJawab || []).length" class="is-kosong">Belum diisi.</li>
                            </ul>
                        </div>
                        <div class="mms-card">
                            <div class="mms-card__head">
                                <span class="mms-card__ico"><i class="bi bi-clipboard-check"></i></span>
                                <span class="mms-card__ttl">Persyaratan</span>
                                <span class="mms-card__n">{{ (detail.persyaratan || []).length }}</span>
                            </div>
                            <ul class="mms-ul">
                                <li v-for="(r, i) in detail.persyaratan" :key="i">
                                    <span class="mms-dot-amber"></span><span>{{ r }}</span>
                                </li>
                                <li v-if="!(detail.persyaratan || []).length" class="is-kosong">Belum diisi.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="mms-card">
                        <div class="mms-card__head">
                            <span class="mms-card__ico"><i class="bi bi-stars"></i></span>
                            <span class="mms-card__ttl">Skill &amp; Benefit</span>
                            <span class="mms-card__n">{{ (detail.skill || []).length + (detail.benefit || []).length }}</span>
                        </div>
                        <div class="mms-chips">
                            <span v-for="s in detail.skill" :key="'s' + s.id" class="mms-skill">{{ s.nama }}</span>
                            <span v-if="!(detail.skill || []).length" class="mms-kosong-inline">Belum ada skill.</span>
                        </div>
                        <div v-if="(detail.benefit || []).length" class="mms-bens">
                            <span v-for="b in detail.benefit" :key="'b' + b.id">
                                <i class="bi bi-check-lg is-green"></i>{{ b.nama }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ── TAB: RIWAYAT SLA ───────────────────────────────────── -->
                <div v-else class="mms-body">
                    <div class="mms-card">
                        <div class="mms-card__head">
                            <span class="mms-card__ico"><i class="bi bi-clock-history"></i></span>
                            <span class="mms-card__ttl">Riwayat &amp; Perpanjangan SLA</span>
                            <span class="mms-card__n">{{ riwayat.length }}</span>
                        </div>

                        <div v-if="riwayat.length" class="mms-tl">
                            <span class="mms-tl__line"></span>
                            <div v-for="r in riwayat" :key="r.id" class="mms-tl__item">
                                <span class="mms-tl__node">{{ r.ke }}</span>
                                <div class="mms-tl__card">
                                    <div class="mms-tl__top">
                                        <b>Perpanjangan ke-{{ r.ke }}</b>
                                        <span class="mms-tl__delta">+{{ r.hari }} hari kerja</span>
                                    </div>
                                    <!-- Pergeseran tenggatnya di baris sendiri; SIAPA
                                         dan KAPAN di baris berikutnya. Dulu keduanya
                                         berdesakan jadi satu baris dan waktunya tidak
                                         ada sama sekali — padahal "diperpanjang jam
                                         berapa" justru yang ditanyakan saat dua
                                         perpanjangan terjadi di hari yang sama. -->
                                    <div class="mms-tl__when">
                                        {{ tgl(r.batasLama) }} <i class="bi bi-arrow-right"></i> {{ tgl(r.batasBaru) }}
                                    </div>
                                    <div class="mms-tl__oleh">
                                        <span><i class="bi bi-person"></i> {{ r.oleh || '—' }}</span>
                                        <span><i class="bi bi-calendar3"></i> {{ tglJam(r.pada) }}</span>
                                    </div>
                                    <p class="mms-tl__why">{{ r.alasan }}</p>
                                    <div v-if="r.kuota !== null" class="mms-tl__kursi">
                                        <i class="bi bi-people"></i> {{ r.terisi }}/{{ r.kuota }} terisi saat itu
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="mms-empty">
                            <span class="mms-empty__ico"><i class="bi bi-clock-history"></i></span>
                            <div class="mms-empty__ttl">Belum pernah diperpanjang</div>
                            <div class="mms-empty__sub">
                                Tenggat MPP ini masih yang ditetapkan saat dibuat. Setiap perpanjangan
                                akan tercatat di sini berikut alasannya.
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div v-else class="mms-empty mms-empty--pane">
                <span class="mms-empty__ico"><i class="bi bi-briefcase"></i></span>
                <div class="mms-empty__ttl">
                    {{ memuat ? 'Memuat…' : daftar.length ? 'Pilih MPP' : 'Tidak ada MPP untuk ditampilkan' }}
                </div>
                <div class="mms-empty__sub">
                    {{ daftar.length
                        ? 'Klik salah satu MPP di panel kiri untuk melihat kandidat, informasi, dan riwayat SLA.'
                        : adaFilter
                            ? 'Penyaring di panel kiri sedang menyembunyikan semuanya. Longgarkan salah satunya untuk melihat isinya lagi.'
                            : 'Belum ada transaksi MPP yang dibuat. Mulai dari tombol “Transaksi MPP Baru” di kanan atas.' }}
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { formatTanggal } from '@utils/career/masterMpp';
import { kursiMpp, penuhMpp, persenKursi, kunciSla, labelSla, pendekSla, sisaHariSla } from '@utils/career/mppSla';

const props = defineProps({
    daftar: { type: Array, default: () => [] },
    detail: { type: Object, default: null },
    terpilih: { type: String, default: null },
    memuat: { type: Boolean, default: false },
    tab: { type: String, default: 'kandidat' },
    q: { type: String, default: '' },
    cq: { type: String, default: '' },
    cpage: { type: Number, default: 1 },
    fIsi: { type: String, default: 'all' },
    fSla: { type: String, default: 'all' },
    tabIsi: { type: Array, default: () => [] },
    tabSla: { type: Array, default: () => [] },
    adaFilter: { type: Boolean, default: false },
    kandidat: { type: Array, default: () => [] },
    kandidatTotal: { type: Number, default: 0 },
    kandidatHal: { type: Number, default: 1 },
    kandidatMemuat: { type: Boolean, default: false },
    mobileDetail: { type: Boolean, default: false },
    // Pagination panel kiri — halamannya dipenggal server, bukan di layar.
    hal: { type: Number, default: 1 },
    totalHal: { type: Number, default: 1 },
    totalMpp: { type: Number, default: 0 },
    // PIC bertumpuk: larik kode karyawan.
    fPic: { type: Array, default: () => [] },
    picOptions: { type: Array, default: () => [] },
    fJenis: { type: String, default: 'all' },
    tabJenis: { type: Array, default: () => [] },
    // [dari, sampai] — null bila belum diisi.
    rentang: { type: Array, default: null },
});

defineEmits([
    'pilih', 'kembali', 'ubah', 'batalkan', 'aktifkan', 'toggle-selesai', 'perpanjang',
    'bersihkan', 'update:q', 'update:cq', 'update:cpage', 'update:tab', 'update:fIsi', 'update:fSla',
    'update:hal', 'update:fPic', 'update:fJenis', 'update:rentang',
    // Buka profil kandidat (baca saja) — ditangani masterMpp.vue.
    'lihat-kandidat',
]);


/**
 * LACI PENYARING — TERTUTUP secara bawaan.
 *
 * Panel kiri ini punya lima penyaring: keterisian, SLA, jenis program, PIC, dan
 * rentang tanggal. Semuanya berguna, tapi bila digambar sekaligus mereka
 * memakan lebih dari separuh tinggi panel — dan yang tersisa untuk daftar MPP
 * itu sendiri cuma dua kartu, di layar yang sebenarnya cukup untuk enam.
 *
 * Padahal yang dibawa orang ke halaman ini hampir selalu "mana MPP anu",
 * dijawab oleh kotak pencarian; menyaring baru perlu ketika daftarnya sudah
 * panjang. Jadi yang tinggal permanen adalah pencarian, dan sisanya menunggu
 * satu klik.
 *
 * PILIHANNYA DIINGAT per peramban. Rekruter yang memang bekerja dengan
 * penyaring terbuka tidak perlu membukanya lagi tiap kali halaman dimuat.
 */
const KUNCI_LACI = 'mmp.filterBuka';

function bacaLaci() {
    try {
        return localStorage.getItem(KUNCI_LACI) === '1';
    } catch (e) {
        // Mode privat / storage penuh — bawaannya saja, tanpa menggagalkan apa pun.
        return false;
    }
}

const filterBuka = ref(bacaLaci());

watch(filterBuka, (v) => {
    try {
        localStorage.setItem(KUNCI_LACI, v ? '1' : '0');
    } catch (e) {
        /* diabaikan — lihat bacaLaci() */
    }
});

/* Seluruh hitungan SLA & kursi dipinjam dari @utils/career/mppSla — SATU sumber
 * yang juga dipakai kartu grid dan tampilan tabel. Menyalinnya ke sini berarti
 * dua layar bisa menyebut angka berbeda untuk MPP yang sama. */
const kursi = computed(() => kursiMpp(props.detail));
const pct = (m) => persenKursi(m);
const kursiPenuh = (m) => penuhMpp(m);
const slaKunci = (m) => kunciSla(m);
const slaLabel = (m) => labelSla(m);
const slaPendek = (m) => pendekSla(m);

const adaSla = computed(() => props.detail?.jenisProgram !== 'MT' && !!props.detail?.sla?.hari);
const diperpanjang = computed(() => Number(props.detail?.sla?.perpanjanganKe || 0));
const riwayat = computed(() => props.detail?.perpanjangan?.riwayat || []);

const sisaTeks = computed(() => {
    const n = sisaHariSla(props.detail);
    if (n === null) return '—';
    if (n < 0) return `Lewat ${Math.abs(n)} hari`;
    if (n === 0) return 'Jatuh tempo hari ini';

    return `Sisa ${n} hari`;
});

/* Porsi waktu TERPAKAI, bukan sisa — yang dibaca sekali lihat adalah seberapa
 * jauh MPP ini berjalan terhadap janjinya. */
const pakaiPersen = computed(() => {
    const sla = props.detail?.sla;
    if (!sla?.mulai || !sla?.batas) return 0;

    const a = new Date(`${sla.mulai}T00:00:00`).getTime();
    const b = new Date(`${sla.batas}T00:00:00`).getTime();
    if (Number.isNaN(a) || Number.isNaN(b) || b <= a) return 0;

    const kini = new Date().setHours(0, 0, 0, 0);

    return Math.min(100, Math.max(0, Math.round(((kini - a) / (b - a)) * 100)));
});

const tabKanan = computed(() => [
    { key: 'kandidat', label: 'Kandidat', ikon: 'bi-people', badge: String(props.kandidatTotal) },
    { key: 'info', label: 'Informasi', ikon: 'bi-info-circle', badge: '' },
    { key: 'riwayat', label: 'Riwayat SLA', ikon: 'bi-clock-history', badge: String(riwayat.value.length) },
]);

const infoMpp = computed(() => {
    const d = props.detail;
    if (!d) return [];

    return [
        { k: 'NO TRANSAKSI', v: d.noTransaksi },
        { k: 'JENIS PROGRAM', v: d.jenisProgram === 'MT' ? 'Management Trainee' : 'Rekrutmen' },
        { k: 'DIVISI', v: d.divisi?.nama || '—' },
        { k: 'DEPARTEMEN', v: d.subDivisi?.nama || '—' },
        { k: 'LEVEL', v: d.level?.nama || '—' },
        { k: 'JABATAN', v: d.jabatan?.nama || '—' },
        { k: 'LOKASI', v: d.lokasi?.nama || '—' },
        { k: 'PENANGGUNG JAWAB', v: d.penanggungJawab?.nama || '—' },
        { k: 'PERIODE TARGET', v: tgl(d.tanggalPeriode) },
    ];
});

/* ── SIAPA YANG MENGINPUT ────────────────────────────────────────────────────
 *
 * Dipisah dari "Informasi MPP" karena menjawab pertanyaan yang berbeda: yang di
 * atas tentang ISI MPP-nya, yang ini tentang SIAPA yang mengetiknya dan KAPAN.
 *
 * Sengaja dibedakan dari Penanggung Jawab, dan itu bagian pentingnya — PJ
 * memegang lowongannya (kode karyawan HRIS), sedangkan "diinput oleh" adalah
 * akun yang benar-benar membuat barisnya. Keduanya kerap bukan orang yang sama:
 * admin rekrutmen sering menginput MPP atas nama PJ di divisi lain, dan saat
 * datanya dipertanyakan, yang dicari justru si penginput.
 *
 * Baris "terakhir diubah" hanya muncul bila MPP-nya memang pernah disunting.
 */
const judulInput = computed(() => {
    const d = props.detail;
    if (!d) return '';

    const dibuat = `Diinput oleh ${d.dibuat?.oleh || '—'} pada ${tglJam(d.dibuat?.pada)}`;

    // Riwayat suntingan ikut di tooltip, bukan di baris tersendiri: ia jarang
    // ditanyakan, dan memberinya baris sendiri di kepala mendorong hal yang
    // memang selalu dibaca — tenggat & aksinya — turun ke bawah.
    return d.diubah?.pada
        ? `${dibuat}.
Terakhir diubah oleh ${d.diubah.oleh || '—'} pada ${tglJam(d.diubah.pada)}.`
        : `${dibuat}.`;
});

const kandidatTeks = computed(
    () => `Halaman ${props.cpage} dari ${props.kandidatHal} · ${props.kandidatTotal} kandidat`,
);

function kuotaTeks(m) {
    const k = kursiMpp(m);

    return k.kuota === 1
        ? (penuhMpp(m) ? 'Kuota 1 · terisi' : 'Kuota 1 · belum terisi')
        : `${k.terisi} dari ${k.kuota} orang`;
}

function pctTeks(m) {
    return penuhMpp(m) ? 'Penuh' : `${persenKursi(m)}%`;
}

function railKelas(m) {
    if (kunciSla(m) === 'over') return 'is-over';

    return penuhMpp(m) ? 'is-full' : m.noTransaksi === props.terpilih ? 'is-on' : '';
}

function barKelas(m) {
    if (kunciSla(m) === 'over') return 'is-over';

    return penuhMpp(m) ? 'is-full' : 'is-jalan';
}

function inisial(n) {
    return (n || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

function tgl(v) {
    return formatTanggal(v);
}

/**
 * "02 Sep 2026, 16.31 WIB" — TANGGAL SEKALIGUS JAM.
 *
 * Tanggal saja tidak cukup: dua perpanjangan bisa terjadi di hari yang sama, dan
 * urutan mana yang lebih dulu jadi tidak terbaca. Nilai dari server berbentuk
 * "YYYY-MM-DD HH:mm:ss"; spasinya diganti "T" supaya Safari ikut mengenalinya —
 * tanpa itu ia memulangkan Invalid Date dan barisnya menampilkan teks mentah.
 */
function tglJam(v) {
    if (!v) return '—';

    const d = new Date(String(v).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(v);

    const tanggal = d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    const jam = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace(':', '.');

    return `${tanggal}, ${jam} WIB`;
}
</script>

<style scoped>
/* ══ RANGKA ═══════════════════════════════════════════════════════════════ */
.mms {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 12px;
    align-items: start;
}

.mms-pane {
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: #fff;
    border: 1px solid #e7e3fb;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06);
}

.mms-left { max-height: calc(100vh - 190px); }
.mms-right { min-height: 420px; }

/* ══ KIRI ═════════════════════════════════════════════════════════════════ */
.mms-left__filter { flex: 0 0 auto; padding: 12px 13px; border-bottom: 1px solid #eef0f7; }

/* Baris atas: pencarian melar, tombol saring tetap. */
.mms-srchrow {
    display: flex;
    align-items: center;
    gap: 7px;
}

.mms-srchrow .mms-srch { flex: 1 1 auto; min-width: 0; }

.mms-ftgl {
    position: relative;
    flex: 0 0 auto;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.82rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.16s, border-color 0.16s, color 0.16s;
}

.mms-ftgl:hover { background: #eef2ff; border-color: #c7d2fe; color: #4f46e5; }

.mms-ftgl.is-on {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-color: transparent;
    color: #fff;
}

/* Titik oranye — ada penyaring menyala sementara lacinya tertutup. */
.mms-ftgl > em {
    position: absolute;
    top: 5px;
    right: 5px;
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #f59e0b;
    box-shadow: 0 0 0 2px #fff;
}

/* Laci penyaring: garis pemisah tipis supaya batasnya jelas saat terbuka,
   tanpa menambah kotak baru yang memakan tinggi. */
.mms-flaci {
    margin-top: 9px;
    padding-top: 9px;
    border-top: 1px dashed #e8ecf5;
}

/* Tab pertama di dalam laci tidak perlu jarak atas lagi — sudah dipisah
   garis di atasnya. */
.mms-flaci > .mms-tabs:first-child { margin-top: 0; }

.mms-srch { position: relative; }
.mms-srch > i {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.8rem;
    color: #94a3b8;
    pointer-events: none;
}
.mms-srch input {
    width: 100%;
    height: 36px;
    padding: 0 12px 0 31px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    font-size: 0.79rem;
    color: #334155;
    outline: none;
}
.mms-srch input:focus { border-color: #a5b4fc; background: #fff; }

.mms-tabs { display: flex; gap: 5px; margin-top: 8px; flex-wrap: wrap; }

/* Baris jenis program diberi jarak sedikit lebih lega dari dua baris tab di
   atasnya: ia menjawab pertanyaan yang berbeda (jenis, bukan keadaan), dan
   tanpa jeda ketiganya terbaca sebagai satu kelompok tab yang panjang. */
.mms-tabs--jenis { margin-top: 10px; }

/* ── Dropdown PIC & rentang tanggal ──────────────────────────────────────────
 *
 * Keduanya sebelumnya tanpa jarak sama sekali — menempel langsung ke baris tab
 * di atasnya sehingga terlihat seperti bagian dari deretan yang sama.
 */
.mms-picsel { margin-top: 10px; }
.mms-tgl { margin-top: 8px; }

/* el-date-picker rentang punya lebar bawaan yang lebih besar daripada panel
   kiri; tanpa ini ia meluber dan mendorong panelnya melebar. */
.mms-tgl :deep(.el-date-editor) { width: 100% !important; }
.mms-tgl :deep(.el-range-input) { font-size: 0.72rem; }
.mms-tgl :deep(.el-range-separator) { font-size: 0.72rem; color: #94a3b8; }

.mms-tab {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 9px;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    font-size: 0.68rem;
    font-weight: 800;
    color: #64748b;
    background: #fff;
    cursor: pointer;
}
.mms-tab__dot { width: 6px; height: 6px; border-radius: 50%; background: #6366f1; }
.mms-tab.is-ontime .mms-tab__dot { background: #10b981; }
.mms-tab.is-risk .mms-tab__dot { background: #f59e0b; }
.mms-tab.is-over .mms-tab__dot { background: #ef4444; }
.mms-tab__n {
    padding: 1px 5px;
    border-radius: 5px;
    font-size: 0.6rem;
    background: #f1f5f9;
    color: #94a3b8;
}
.mms-tab.is-on { color: #fff; border-color: transparent; background: #6366f1; }
.mms-tab.is-on.is-ontime { background: #10b981; }
.mms-tab.is-on.is-risk { background: #f59e0b; }
.mms-tab.is-on.is-over { background: #ef4444; }
.mms-tab.is-on .mms-tab__dot { background: rgba(255, 255, 255, 0.85); }
.mms-tab.is-on .mms-tab__n { background: rgba(255, 255, 255, 0.26); color: #fff; }

/* ── PIC bertumpuk ──────────────────────────────────────────────────────── */
.mms-pic { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; margin-top: 7px; }

.mms-picchip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 4px 3px 3px;
    border: 1px solid #ddd6fe;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 800;
    color: #6d28d9;
    background: #f4f2ff;
}
.mms-picchip__ava {
    width: 17px;
    height: 17px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.55rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    flex: none;
}
.mms-picchip button {
    display: grid;
    place-items: center;
    width: 15px;
    height: 15px;
    border: none;
    border-radius: 50%;
    font-size: 0.62rem;
    color: #7c3aed;
    background: rgba(124, 58, 237, 0.12);
    cursor: pointer;
}
.mms-picchip button:hover { color: #fff; background: #7c3aed; }

.mms-picadd { position: relative; }
.mms-picadd__btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 9px;
    border: 1px dashed #cbd5e1;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 800;
    color: #64748b;
    background: #fff;
    cursor: pointer;
}
.mms-picadd__btn:hover, .mms-picadd__btn.is-on { color: #4f46e5; border-color: #a5b4fc; background: #eef2ff; }

.mms-picmenu {
    position: absolute;
    z-index: 20;
    top: calc(100% + 5px);
    left: 0;
    min-width: 172px;
    max-height: 220px;
    overflow-y: auto;
    padding: 5px;
    border: 1px solid #e7e3fb;
    border-radius: 11px;
    background: #fff;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
}
.mms-picmenu button {
    display: flex;
    align-items: center;
    gap: 7px;
    width: 100%;
    padding: 6px 8px;
    border: none;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #334155;
    background: transparent;
    cursor: pointer;
    text-align: left;
}
.mms-picmenu button:hover { background: #f4f2ff; color: #4f46e5; }
.mms-picmenu__kosong { padding: 8px; font-size: 0.7rem; color: #94a3b8; }

/* ── Pagination panel kiri ─────────────────────────────────────────────── */
.mms-lpager {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 9px 12px;
    border-top: 1px solid #eef0f7;
    background: #fbfbfe;
    font-size: 0.68rem;
    font-weight: 800;
    color: #64748b;
}
.mms-lpager__btns { display: flex; gap: 5px; }
.mms-lpager__btns button {
    width: 27px;
    height: 27px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #334155;
    cursor: pointer;
}
.mms-lpager__btns button:disabled { color: #cbd5e1; cursor: not-allowed; }
.mms-lpager__btns button:not(:disabled):hover { color: #4f46e5; border-color: #a5b4fc; background: #eef2ff; }

.mms-left__foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 9px; }
.mms-count { font-size: 0.66rem; font-weight: 800; letter-spacing: 0.1em; color: #94a3b8; }
.mms-clear {
    border: none;
    background: transparent;
    padding: 2px 4px;
    font-size: 0.69rem;
    font-weight: 700;
    color: #4f46e5;
    text-decoration: underline;
    text-underline-offset: 2px;
    cursor: pointer;
}

.mms-list { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; padding: 10px 12px 12px; }

.mms-row {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    /* Barisnya anak dari .mms-list yang juga flex-column. Tanpa flex:none ia
       IKUT MENYUSUT saat daftarnya lebih panjang dari panelnya — dan karena
       overflow-nya hidden (demi rail sudut membulat), bagian bawah tiap kartu
       terpotong diam-diam: justru baris kuota dan bilah keterisiannya. */
    flex: none;
    width: 100%;
    padding: 12px 13px 13px 16px;
    border: 1px solid #eef0f7;
    border-radius: 14px;
    background: #fff;
    text-align: left;
    cursor: pointer;
    transition: background 0.14s, border-color 0.16s, box-shadow 0.16s;
}
.mms-row:hover { background: #fbfbff; border-color: #c7d2fe; box-shadow: 0 6px 16px rgba(99, 102, 241, 0.08); }
.mms-row.is-on { border-color: #a5b4fc; background: #f6f5ff; box-shadow: 0 8px 22px rgba(99, 102, 241, 0.14); }

.mms-row__rail { position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #e2e8f0; }
.mms-row__rail.is-on { background: linear-gradient(180deg, #8b5cf6, #6366f1); }
.mms-row__rail.is-full { background: linear-gradient(180deg, #34d399, #10b981); }
.mms-row__rail.is-over { background: linear-gradient(180deg, #fb7185, #e11d48); }

.mms-row__top { display: flex; align-items: center; gap: 8px; width: 100%; min-width: 0;  align-self: stretch;}
.mms-row__title {
    flex: 1;
    min-width: 0;
    font-size: 0.8rem;
    font-weight: 800;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mms-row__type {
    flex: none;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.56rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: #4338ca;
    background: #eef2ff;
}
.mms-row__type.is-mt { color: #b45309; background: rgba(245, 158, 11, 0.14); }

.mms-row__chips { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 7px;  align-self: stretch;}
.mms-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.59rem;
    font-weight: 800;
    color: #475569;
    background: #f1f5f9;
}
.mms-chip__dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex: none; }
.mms-chip.is-full { color: #047857; background: rgba(16, 185, 129, 0.12); }
.mms-chip.is-ontime { color: #047857; background: rgba(16, 185, 129, 0.12); }
.mms-chip.is-risk { color: #b45309; background: rgba(245, 158, 11, 0.14); }
.mms-chip.is-over { color: #b91c1c; background: rgba(239, 68, 68, 0.1); }

.mms-row__pic { display: flex; align-items: center; gap: 7px; margin-top: 8px; width: 100%; min-width: 0;  align-self: stretch;}
.mms-ava {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.58rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
}
.mms-ava--lg { width: 34px; height: 34px; border-radius: 10px; font-size: 0.74rem; }
.mms-row__picname {
    font-size: 0.68rem;
    font-weight: 700;
    color: #475569;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mms-row__sep { width: 3px; height: 3px; border-radius: 50%; background: #cbd5e1; flex: none; }
.mms-row__code { flex: none; font-size: 0.62rem; font-weight: 700; color: #94a3b8; font-family: ui-monospace, monospace; }

/* Baris <button> ini flex-column. Tiap bagian diberi lebar penuh secara
   eksplisit — anak sebuah button tidak mewarisinya sendiri, dan tanpa ini baris
   kuota beserta bilahnya menyusut sampai tak terbaca. */
.mms-row__quota { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 9px; width: 100%; flex: none; align-self: stretch; }
.mms-row__qtxt { font-size: 0.68rem; font-weight: 700; color: #64748b; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mms-row__qtxt.is-full { color: #047857; }
.mms-row__pct { flex: none; font-size: 0.65rem; font-weight: 800; color: #4f46e5; }
.mms-row__pct.is-full { color: #047857; }

.mms-track { display: block; height: 7px; border-radius: 99px; background: #f1f5f9; overflow: hidden; margin-top: 7px; width: 100%; flex: none; align-self: stretch; }
.mms-track--mini { flex: 1; min-width: 60px; max-width: 140px; margin-top: 0; height: 6px; }
.mms-track--big { height: 10px; margin-top: 10px; }
.mms-bar { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); transition: width 0.35s ease; }
.mms-bar.is-full { background: linear-gradient(90deg, #34d399, #10b981); }
.mms-bar.is-over { background: linear-gradient(90deg, #fb7185, #e11d48); }
.mms-bar.is-ontime { background: linear-gradient(90deg, #34d399, #10b981); }
.mms-bar.is-risk { background: linear-gradient(90deg, #fbbf24, #f59e0b); }

.mms-kosong { padding: 26px 16px; text-align: center; font-size: 0.75rem; color: #94a3b8; }

/* Keadaan kosong DI DALAM panel kiri — penyaringnya tetap di tempatnya. */
.mms-nihil {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 6px;
    padding: 28px 16px;
}
.mms-nihil__ico {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    display: grid;
    place-items: center;
    font-size: 1.05rem;
    color: #a5b4fc;
    background: #f4f2ff;
    border: 1px solid #e7e3fb;
}
.mms-nihil b { margin-top: 4px; font-size: 0.8rem; font-weight: 800; color: #334155; }
.mms-nihil small { font-size: 0.71rem; line-height: 1.55; color: #94a3b8; }
.mms-nihil__btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 6px;
    padding: 5px 11px;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    font-size: 0.69rem;
    font-weight: 800;
    color: #4f46e5;
    background: #fff;
    cursor: pointer;
}
.mms-nihil__btn:hover { border-color: #a5b4fc; background: #eef2ff; }
.mms-kosong-inline { font-size: 0.73rem; color: #94a3b8; }

/* ══ KANAN: KEPALA ════════════════════════════════════════════════════════ */
.mms-head {
    flex: 0 0 auto;
    padding: 17px 19px;
    background: linear-gradient(135deg, #f3f0ff 0%, #eef2ff 55%, #eaf1ff 100%);
    border-bottom: 1px solid #eef0f7;
}
.mms-head__row { display: flex; align-items: flex-start; gap: 13px; flex-wrap: wrap; }

.mms-back {
    display: none;
    width: 36px;
    height: 36px;
    border: 1px solid #e2e8f0;
    border-radius: 11px;
    background: #fff;
    color: #475569;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    flex: none;
}

.mms-head__ico {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28);
}

.mms-head__txt { flex: 1; min-width: 0; }
.mms-head__meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.mms-head__code {
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: #4338ca;
    font-family: ui-monospace, monospace;
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.1);
}
.mms-head__txt h2 { margin: 9px 0 0; font-size: 1.15rem; font-weight: 800; color: #1e1b4b; letter-spacing: -0.02em; }
.mms-head__sub { font-size: 0.75rem; color: #6b6597; margin-top: 4px; }

.mms-stat { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 6px; font-size: 0.62rem; font-weight: 800; }
.mms-stat.is-aktif { color: #047857; background: rgba(16, 185, 129, 0.12); }
.mms-stat.is-batal { color: #b91c1c; background: rgba(239, 68, 68, 0.1); }

/* Dua kartu orang, berdampingan di kepala. Membungkus (wrap) saat sempit,
   bukan menyusut — nama yang terpotong jadi "ADMIN REKR…" tidak menjawab
   pertanyaan siapa pun. */
.mms-orang { display: flex; gap: 8px; flex-wrap: wrap; flex: none; }

.mms-picard {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 13px;
    background: rgba(255, 255, 255, 0.72);
    border: 1px solid #e7e3fb;
    flex: none;
    min-width: 0;
}

/* Kartu "diinput oleh" dibedakan tipis — ia keterangan asal-usul, bukan orang
   yang sedang bertanggung jawab. Warnanya lebih dingin dan ikonnya pena, bukan
   inisial, supaya tidak terbaca sebagai orang kedua yang memegang lowongan. */
.mms-picard--input { background: rgba(248, 250, 252, 0.9); border-color: #e2e8f0; }
.mms-ava--input { background: linear-gradient(135deg, #94a3b8, #64748b); font-size: 0.86rem; }
.mms-picard__txt { display: flex; flex-direction: column; min-width: 0; }
.mms-picard__txt small { font-size: 0.58rem; font-weight: 800; letter-spacing: 0.1em; color: #94a3b8; }
.mms-picard__txt b { font-size: 0.76rem; font-weight: 800; color: #1e293b; margin-top: 2px; }
.mms-picard__txt em { font-size: 0.64rem; font-style: normal; color: #8792a6; }

/* ── Strip SLA ─────────────────────────────────────────────────────────── */
.mms-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 12px;
    padding: 11px 13px;
    border-radius: 13px;
    border: 1px solid #e7e3fb;
    background: rgba(255, 255, 255, 0.72);
}
.mms-strip.is-ontime { border-color: rgba(16, 185, 129, 0.32); }
.mms-strip.is-risk { border-color: rgba(245, 158, 11, 0.4); background: rgba(255, 251, 235, 0.85); }
.mms-strip.is-over { border-color: rgba(239, 68, 68, 0.34); background: rgba(254, 242, 242, 0.85); }

.mms-strip__kiri { display: flex; align-items: center; gap: 9px; min-width: 0; flex: 1; }
.mms-strip__ico {
    width: 26px;
    height: 26px;
    border-radius: 9px;
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #4f46e5;
    background: #eef2ff;
    font-size: 0.76rem;
}
.mms-strip.is-over .mms-strip__ico { color: #b91c1c; background: rgba(239, 68, 68, 0.12); }
.mms-strip.is-risk .mms-strip__ico { color: #b45309; background: rgba(245, 158, 11, 0.16); }
.mms-strip__ttl { font-size: 0.74rem; font-weight: 800; color: #334155; white-space: nowrap; }
.mms-strip__track { flex: 1; min-width: 70px; max-width: 220px; height: 6px; border-radius: 99px; background: #e2e8f0; overflow: hidden; }
.mms-strip__bar { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); }
.mms-strip.is-risk .mms-strip__bar { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.mms-strip.is-over .mms-strip__bar { background: linear-gradient(90deg, #fb7185, #e11d48); }

.mms-strip__kanan { display: flex; align-items: center; gap: 11px; flex: none; flex-wrap: wrap; }
.mms-strip__sisa { display: inline-flex; align-items: center; gap: 5px; font-size: 0.7rem; font-weight: 800; color: #475569; }
.mms-strip.is-over .mms-strip__sisa { color: #b91c1c; }
.mms-strip.is-risk .mms-strip__sisa { color: #b45309; }
.mms-strip__due { font-size: 0.72rem; font-weight: 800; color: #0f172a; }
.mms-strip__due s { margin-right: 3px; font-size: 0.64rem; font-weight: 700; color: #94a3b8; }
.mms-strip__btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    border: 1px solid #d9def0;
    border-radius: 9px;
    font-size: 0.68rem;
    font-weight: 800;
    color: #4f46e5;
    background: #fff;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.16s, color 0.16s, transform 0.16s;
}
.mms-strip__btn:hover { color: #fff; background: #6366f1; border-color: #6366f1; transform: translateY(-1px); }

/* ── Aksi & tab ────────────────────────────────────────────────────────── */
.mms-acts { display: grid; grid-template-columns: repeat(auto-fit, minmax(132px, 1fr)); gap: 7px; margin-top: 13px; }
.mms-act {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 13px;
    border: 1px solid #d9def0;
    border-radius: 11px;
    font-size: 0.73rem;
    font-weight: 800;
    color: #4f46e5;
    background: #fff;
    cursor: pointer;
    white-space: nowrap;
    transition: transform 0.16s, box-shadow 0.16s;
}
.mms-act:hover { transform: translateY(-1px); }
.mms-act.is-primary { color: #fff; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 8px 18px rgba(99, 102, 241, 0.28); }
.mms-act.is-gold { color: #b45309; background: #fffbeb; border-color: #f5e0a3; }
.mms-act.is-danger { color: #b91c1c; background: #fff; border-color: #fecaca; }

.mms-rtabs { display: flex; gap: 6px; margin-top: 12px; flex-wrap: wrap; }
.mms-rtab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 13px;
    border: 1px solid #e7e3fb;
    border-radius: 11px;
    font-size: 0.73rem;
    font-weight: 800;
    color: #64748b;
    background: #fff;
    cursor: pointer;
}
.mms-rtab.is-on { color: #fff; border-color: transparent; background: #6366f1; }
.mms-rtab__n { padding: 1px 6px; border-radius: 5px; font-size: 0.6rem; background: #f1f5f9; color: #94a3b8; }
.mms-rtab.is-on .mms-rtab__n { background: rgba(255, 255, 255, 0.26); color: #fff; }

/* ── Tab kandidat ──────────────────────────────────────────────────────── */
.mms-ctool { display: flex; align-items: center; gap: 10px; padding: 13px 16px; border-bottom: 1px solid #eef0f7; flex-wrap: wrap; }
.mms-qinline { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; }
.mms-qinline__n { font-size: 1.05rem; font-weight: 800; color: #0f172a; }
.mms-qinline__d { font-size: 0.72rem; font-weight: 700; color: #94a3b8; white-space: nowrap; }
.mms-qinline__p { flex: none; font-size: 0.7rem; font-weight: 800; color: #4f46e5; }
.mms-qinline__p.is-full { color: #047857; }
.mms-srch--sm { flex: 1; min-width: 140px; max-width: 260px; }

.mms-chead {
    display: grid;
    grid-template-columns: 1.9fr 1.4fr 1.1fr 1.3fr 88px;
    gap: 12px;
    padding: 11px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #eef0f7;
    font-size: 0.58rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: #64748b;
}
.mms-crow {
    display: grid;
    grid-template-columns: 1.9fr 1.4fr 1.1fr 1.3fr 88px;
    gap: 12px;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #f4f5fb;
    transition: background 0.14s;
}
.mms-crow:hover { background: #fbfbfe; }
.mms-detail {
    display: inline-flex; align-items: center; gap: 5px;
    border: 1px solid #e2e8f0; background: #fff; color: #475569;
    font-size: 0.68rem; font-weight: 700; border-radius: 8px;
    padding: 5px 10px; cursor: pointer; white-space: nowrap;
    transition: border-color .14s, color .14s, background .14s;
}
.mms-detail:hover { border-color: #c7d2fe; background: #eef2ff; color: #4338ca; }
.mms-crow__who { display: flex; align-items: center; gap: 11px; min-width: 0; }
.mms-crow__id { min-width: 0; }
.mms-crow__id b { display: block; font-size: 0.79rem; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mms-crow__id small { display: block; font-size: 0.63rem; color: #64748b; font-family: ui-monospace, monospace; }
.mms-cell { font-size: 0.75rem; color: #334155; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mms-cell--last { color: #64748b; }

.mms-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 12px 16px; border-top: 1px solid #eef0f7; background: #fbfbfe; font-size: 0.71rem; color: #64748b; }
.mms-pager__btns { display: flex; align-items: center; gap: 6px; }
.mms-pager__btns button {
    width: 30px;
    height: 30px;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    background: #fff;
    color: #334155;
    cursor: pointer;
}
.mms-pager__btns button:disabled { color: #cbd5e1; cursor: not-allowed; }
.mms-pager__now { font-size: 0.71rem; font-weight: 800; color: #475569; }

/* ── Tab informasi & riwayat ───────────────────────────────────────────── */
.mms-body { padding: 15px 17px; display: flex; flex-direction: column; gap: 11px; }

.mms-card { background: #fff; border: 1px solid #eef0f7; border-radius: 15px; padding: 14px 15px; min-width: 0; }
.mms-card__top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.mms-card__head { display: flex; align-items: center; gap: 9px; margin-bottom: 11px; }
.mms-card__ico {
    width: 28px;
    height: 28px;
    border-radius: 9px;
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 0.78rem;
}
.mms-card__ttl { flex: 1; min-width: 0; font-size: 0.79rem; font-weight: 800; color: #0f172a; }
.mms-card__n { flex: none; padding: 3px 8px; border-radius: 6px; font-size: 0.62rem; font-weight: 800; background: #f1f5f9; color: #64748b; }

.mms-mlabel { font-size: 0.62rem; font-weight: 800; letter-spacing: 0.12em; color: #64748b; }
.mms-qbig b { font-size: 1.25rem; font-weight: 800; color: #0f172a; }
.mms-qbig small { font-size: 0.76rem; font-weight: 700; color: #94a3b8; margin-left: 5px; }

.mms-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 6px; font-size: 0.63rem; font-weight: 800; color: #475569; background: #f1f5f9; }
.mms-pill.is-ontime { color: #047857; background: rgba(16, 185, 129, 0.12); }
.mms-pill.is-risk { color: #b45309; background: rgba(245, 158, 11, 0.14); }
.mms-pill.is-over { color: #b91c1c; background: rgba(239, 68, 68, 0.1); }

.mms-pips { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 10px; }
.mms-pip { width: 14px; height: 14px; border-radius: 5px; background: #eef0f7; border: 1px solid #e2e8f0; }
.mms-pip.is-on { background: linear-gradient(135deg, #34d399, #10b981); border-color: transparent; }

.mms-hint { margin: 9px 0 0; font-size: 0.71rem; line-height: 1.55; color: #64748b; }
.mms-scale { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 6px; font-size: 0.62rem; font-weight: 700; color: #94a3b8; }

.mms-minis, .mms-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(124px, 1fr)); gap: 7px; margin-top: 12px; }
.mms-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin-top: 0; }
.mms-mini, .mms-box { background: #fbfbfe; border: 1px solid #eef0f7; border-radius: 11px; padding: 9px 11px; min-width: 0; }
.mms-box { border-radius: 12px; padding: 10px 12px; }
.mms-mini small, .mms-box small, .mms-desc small {
    display: block;
    font-size: 0.58rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mms-mini b, .mms-box b { display: block; font-size: 0.76rem; font-weight: 800; color: #1e293b; margin-top: 3px; overflow-wrap: anywhere; }

.mms-box--jejak { display: flex; align-items: center; gap: 9px; }
.mms-box__ico {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    flex: none;
    display: grid;
    place-items: center;
    font-size: 0.72rem;
    color: #6d28d9;
    background: #f4f2ff;
}
.mms-box__txt { min-width: 0; }

.mms-desc { margin-top: 11px; padding: 12px 13px; border-radius: 12px; background: #fbfbfe; border: 1px solid #eef0f7; }
.mms-desc p { margin: 4px 0 0; font-size: 0.76rem; line-height: 1.6; color: #334155; }

.mms-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 11px; }

.mms-ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.mms-ul li { display: flex; align-items: flex-start; gap: 9px; font-size: 0.76rem; line-height: 1.55; color: #334155; }
.mms-ul li.is-kosong { color: #94a3b8; }
.mms-ul i { flex: none; margin-top: 2px; }
.is-green { color: #10b981; }
.mms-dot-amber { width: 5px; height: 5px; border-radius: 50%; background: #f59e0b; flex: none; margin-top: 7px; }

.mms-chips { display: flex; flex-wrap: wrap; gap: 7px; }
.mms-skill {
    display: inline-flex;
    align-items: center;
    padding: 5px 11px;
    border-radius: 8px;
    font-size: 0.68rem;
    font-weight: 700;
    color: #6d28d9;
    background: #f4f2ff;
    border: 1px solid #e7e3fb;
}
.mms-bens { display: flex; flex-wrap: wrap; gap: 10px 18px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #f4f5fb; }
.mms-bens span { display: inline-flex; align-items: center; gap: 8px; font-size: 0.76rem; color: #334155; }

/* ── Garis waktu ───────────────────────────────────────────────────────── */
.mms-tl { position: relative; padding-left: 30px; display: flex; flex-direction: column; gap: 12px; }
.mms-tl__line { position: absolute; left: 12px; top: 8px; bottom: 8px; width: 2px; background: #eef0f7; }
.mms-tl__item { position: relative; }
.mms-tl__node {
    position: absolute;
    left: -30px;
    top: 2px;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.65rem;
    font-weight: 800;
    color: #fff;
    background: #7c3aed;
}
.mms-tl__card { background: #fbfbfe; border: 1px solid #eef0f7; border-radius: 12px; padding: 11px 13px; }
.mms-tl__top { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.mms-tl__top b { font-size: 0.79rem; font-weight: 800; color: #1e293b; }
.mms-tl__delta { padding: 2px 8px; border-radius: 6px; font-size: 0.62rem; font-weight: 800; color: #6d28d9; background: #ede9fe; }
.mms-tl__when { display: flex; align-items: center; gap: 5px; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-top: 4px; }
.mms-tl__when i { font-size: 0.62rem; color: #7c3aed; }

.mms-tl__oleh { display: flex; flex-wrap: wrap; gap: 4px 12px; margin-top: 3px; font-size: 0.66rem; color: #94a3b8; }
.mms-tl__oleh span { display: inline-flex; align-items: center; gap: 4px; }
.mms-tl__why { margin: 8px 0 0; font-size: 0.76rem; line-height: 1.55; color: #334155; white-space: pre-line; overflow-wrap: anywhere; }
.mms-tl__kursi { display: inline-flex; align-items: center; gap: 5px; margin-top: 6px; font-size: 0.66rem; font-weight: 700; color: #94a3b8; }

/* ── Keadaan kosong ────────────────────────────────────────────────────── */
.mms-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; text-align: center; }
.mms-empty--pane { padding: 60px 24px; min-height: 300px; }
.mms-empty__ico {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    font-size: 1.3rem;
    color: #a5b4fc;
    background: #f4f2ff;
    border: 1px solid #e7e3fb;
}
.mms-empty__ttl { font-size: 0.86rem; font-weight: 800; color: #334155; margin-top: 13px; }
.mms-empty__sub { font-size: 0.75rem; color: #64748b; margin-top: 6px; max-width: 300px; line-height: 1.55; }

/* ══ TANGGAP LAYAR ════════════════════════════════════════════════════════ */
@media (max-width: 1280px) {
    .mms { grid-template-columns: 300px 1fr; }
    .mms-chead, .mms-crow { grid-template-columns: 1.8fr 1.4fr 1.2fr 88px; }
    .mms-cell--last { display: none; }
    .mms-2col { grid-template-columns: 1fr; }
}

@media (max-width: 900px) {
    /* Ponsel: satu layar penuh bergantian — daftar ATAU detail, tidak keduanya.
       Dua kolom selebar 45% masing-masing tidak cukup untuk salah satu pun. */
    .mms { grid-template-columns: 1fr; }
    .mms-left { max-height: none; }
    .mms-left.is-hidden-mobile, .mms-right.is-hidden-mobile { display: none; }
    .mms-back { display: flex; }
    .mms-chead { display: none; }
    .mms-crow { grid-template-columns: 1fr; gap: 8px; }
    .mms-cell { display: none; }
    .mms-orang { display: none; }
}
</style>
