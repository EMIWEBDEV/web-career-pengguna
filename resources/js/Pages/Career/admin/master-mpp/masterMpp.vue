<!-- WEB CAREER — Master MPP. Buat & kelola transaksi HRIS_Transaksi_GForm + N_WEB_CAREERS_Detail_MPP.
     Menggabungkan bekas /karir/monitoring-mpp (read-only, sudah dihapus) — grid/tabel, panel detail,
     dan pagination server pindah ke sini, ditambah aksi Ubah/Batalkan/Tandai Selesai. -->
<template>
    <Head title="Master MPP" />
    <div class="wca">
        <!-- ══ BILAH RINGKAS — hanya tampilan split ══════════════════════
             Mengikuti rancangan: judul + RINGKASAN ANGKA dalam satu baris, bukan
             empat kartu statistik setinggi 150px. Angkanya pun berbeda dari
             kartu lama: yang ditanyakan di halaman ini bukan "berapa MPP", tapi
             "berapa kursi yang masih kosong dan berapa yang lewat tenggat".

             Tombol "Ekspor Excel" pada rancangan TIDAK dipasang — diminta tidak
             dimunculkan, dan belum ada yang menghasilkan berkasnya. -->
        <div class="mmp-bar">
            <span class="mmp-bar__ico"><i class="bi bi-briefcase-fill"></i></span>
            <div class="mmp-bar__txt">
                <h1>MPP</h1>
                <div class="mmp-bar__meta">
                    <span v-for="r in ringkasBar" :key="r.k">
                        <span class="mmp-bar__dot" :style="{ background: r.c }"></span>
                        <b>{{ r.v }}</b> {{ r.k }}
                    </span>
                </div>
            </div>
            <button class="wca-iconbtn" title="Muat ulang" :disabled="loading" @click="load">
                <i class="bi" :class="loading ? 'bi-arrow-repeat mmp-spin' : 'bi-arrow-clockwise'"></i>
            </button>
            <button class="wca-btn wca-btn--primary" @click="openCreate">
                <i class="bi bi-plus-lg"></i> Transaksi MPP Baru
            </button>
        </div>

        <!-- Toolbar: satu baris inline flex-wrap, pola sama dengan bekas Monitoring MPP -->
        <template v-if="loading && !ready">
            <!-- Kerangka SPLIT: dua panel, bukan deretan kartu. Kerangka yang
                 bentuknya berbeda dari isi yang akan datang membuat halaman
                 terlihat melompat saat datanya tiba. -->
            <div class="mmp-sksplit">
                <div class="mmp-skpane">
                    <div v-for="n in 5" :key="n" class="mmp-skrow"></div>
                </div>
                <div class="mmp-skpane mmp-skpane--kanan"></div>
            </div>
        </template>

        <div v-else-if="error" class="wca-card"><div class="wca-empty">
            <i class="bi bi-wifi-off"></i>
            <h4>Gagal memuat data MPP</h4>
            <button class="wca-btn wca-btn--primary wca-btn--sm" @click="load"><i class="bi bi-arrow-clockwise"></i> Coba lagi</button>
        </div></div>

        <!-- DAFTAR KOSONG TIDAK MENGGANTI SELURUH LAYAR.
             Dulu di sini ada cabang yang menukar seluruh split dengan satu kartu
             "Belum ada transaksi MPP". Akibatnya panel penyaring ikut lenyap —
             dan penyaring yang MENYEBABKAN daftarnya kosong jadi tidak bisa
             dicabut lagi kecuali dengan memuat ulang halaman.

             Keadaan kosongnya sekarang digambar DI DALAM panel kiri (lihat
             "Tidak ada MPP yang cocok" di MasterMppSplit), sehingga penyaringnya
             tetap ada di tempatnya. -->
        <template v-else>
            <!-- SPLIT — daftar kiri + detail kanan, mengikuti rancangan
                 docs/refrences/v2/.../Realisasi Kuota MPP.dc.html. Seluruh aksi
                 (Ubah/Selesai/Batalkan/Perpanjang) memakai handler yang sama
                 dengan grid & tabel; yang berbeda cuma tata letaknya. -->
            <MasterMppSplit
                :daftar="daftarSplit"
                :detail="splitDetail"
                :terpilih="splitNo"
                :memuat="splitMemuat"
                :tab="splitTab"
                :q="q"
                :cq="cq"
                :cpage="cpage"
                :f-isi="fIsiSplit"
                :f-sla="fSlaSplit"
                :tab-isi="tabIsiSplit"
                :tab-sla="tabSlaSplit"
                :ada-filter="hasFilter || fIsiSplit !== 'all' || fSlaSplit !== 'all' || fPicSplit.length > 0 || !!rentangTgl"
                :kandidat="kandidat"
                :kandidat-total="kandidatTotal"
                :kandidat-hal="kandidatHal"
                :kandidat-memuat="kandidatMemuat"
                :mobile-detail="splitMobileDetail"
                :hal="page"
                :total-hal="totalPages"
                :total-mpp="totalData"
                :f-pic="fPicSplit"
                :pic-options="picOptions"
                :f-jenis="fJenis || 'all'"
                :tab-jenis="tabJenisSplit"
                :rentang="rentangTgl"
                @update:hal="gantiHalSplit"
                @update:f-pic="gantiPic"
                @update:f-jenis="gantiJenis"
                @update:rentang="gantiRentang"
                @pilih="pilihSplit"
                @lihat-kandidat="bukaKandidat"
                @kembali="splitMobileDetail = false"
                @ubah="openEdit"
                @batalkan="askBatalkan"
                @aktifkan="(d) => aktifkanKembali(d, true)"
                @toggle-selesai="(d) => toggleSelesai(d, true)"
                @perpanjang="askPerpanjang"
                @bersihkan="bersihkanSplit"
                @update:q="(v) => { q = v; onSearchInput(); }"
                @update:cq="(v) => { cq = v; cpage = 1; muatKandidat(); }"
                @update:cpage="(v) => { cpage = v; muatKandidat(); }"
                @update:tab="(v) => (splitTab = v)"
                @update:f-isi="(v) => (fIsiSplit = v)"
                @update:f-sla="(v) => (fSlaSplit = v)"
            />

        </template>

        <AdminModal
            :busy="saving || loadingDetail"
            :busy-label="loadingDetail ? 'Memuat detail…' : 'Menyimpan…'"
            :show="show"
            xl
            icon="bi-clipboard-data"
            :title="editingNo ? `Ubah MPP — ${editingNo}` : 'Transaksi MPP Baru'"
            subtitle="Isi data transaksi MPP & konfigurasi lowongan kerja"
            :foot-note="formFootNote"
            @close="show = false"
        >
            <!-- BILAH LANGKAH — penunjuk posisi, sekaligus jalan LOMPAT.

                 Mundur satu langkah ada di kaki modal ("Kembali"). Bilah ini untuk
                 melompat jauh — dari Langkah 4 langsung ke Langkah 1 — tanpa menekan
                 Kembali tiga kali. Keduanya memanggil pergiKeLangkah() yang sama,
                 jadi penjagaan dan penggulirannya tidak bisa berbeda. -->
            <div class="mmp-modal-tabs">
                <button
                    v-for="l in LANGKAH"
                    :key="l.no"
                    type="button"
                    class="mmp-tab-btn"
                    :class="{ 'is-active': modalTab === l.no, 'is-tuntas': modalTab > l.no }"
                    :disabled="l.no > 1 && slaMenahan"
                    @click="pergiKeLangkah(l.no)"
                >
                    <span class="mmp-tab-num">
                        <i v-if="modalTab > l.no" class="bi bi-check-lg"></i>
                        <template v-else>{{ l.no }}</template>
                    </span>
                    <div class="mmp-tab-text">
                        <strong>{{ l.judul }}</strong>
                        <small>{{ l.sub }}</small>
                    </div>
                </button>
            </div>

            <!-- TAB 1: Transaksi & Posisi -->
            <div v-show="modalTab === 1" class="mmp-tab-pane">
                <div class="mmp-form-card">
                    <div class="mmp-form-card__head">
                        <i class="bi bi-diagram-3-fill"></i>
                        <div>
                            <h4>Kategori & Jenis Program</h4>
                            <p>
                                Tentukan apakah transaksi ini untuk Rekrutmen Posisi Reguler atau Program Management
                                Trainee
                            </p>
                        </div>
                    </div>
                    <div class="mmp-seg-grid">
                        <div
                            class="mmp-seg-card"
                            :class="{ 'is-active': form.jenisProgram === 'REKRUTMEN' }"
                            @click="form.jenisProgram = 'REKRUTMEN'"
                        >
                            <div class="mmp-seg-card__icon"><i class="bi bi-person-workspace"></i></div>
                            <div class="mmp-seg-card__body">
                                <strong>Rekrutmen Reguler</strong>
                                <span>Penerimaan karyawan posisi spesifik / umum</span>
                            </div>
                            <i class="bi bi-check-circle-fill mmp-seg-card__check"></i>
                        </div>
                        <div
                            class="mmp-seg-card mmp-seg-card--mt"
                            :class="{ 'is-active': form.jenisProgram === 'MT' }"
                            @click="form.jenisProgram = 'MT'"
                        >
                            <div class="mmp-seg-card__icon"><i class="bi bi-mortarboard-fill"></i></div>
                            <div class="mmp-seg-card__body">
                                <strong>Management Trainee (MT)</strong>
                                <span>Program percepatan karir & calon pemimpin</span>
                            </div>
                            <i class="bi bi-check-circle-fill mmp-seg-card__check"></i>
                        </div>
                    </div>
                </div>

                <div class="mmp-form-card">
                    <div class="mmp-form-card__head">
                        <i class="bi bi-building"></i>
                        <div>
                            <h4>Struktur Organisasi & Jabatan</h4>
                            <p>Pilih divisi, departemen, level HRIS, dan jabatan yang dibutuhkan</p>
                        </div>
                    </div>
                    <div class="wca-form">
                        <div class="wca-frow">
                            <div>
                                <label class="wca-field-lbl"
                                    ><i class="bi bi-diagram-3"></i> Divisi <span class="mmp-req">*</span></label
                                >
                                <el-select
                                    v-model="form.idDivisi"
                                    placeholder="Pilih divisi"
                                    filterable
                                    style="width: 100%"
                                    @change="onDivisiChange"
                                >
                                    <el-option
                                        v-for="d in divisiOptions"
                                        :key="d.value"
                                        :label="d.label"
                                        :value="d.value"
                                    />
                                </el-select>
                            </div>
                            <div>
                                <label class="wca-field-lbl"><i class="bi bi-diagram-2"></i> Departemen <span class="mmp-hint">(opsional)</span></label>
                                <el-select v-model="form.idSubDivisi" placeholder="Pilih departemen" filterable clearable :disabled="!form.idDivisi" style="width: 100%">
                                    <el-option v-for="s in subDivisiOptions" :key="s.value" :label="s.label" :value="s.value" />
                                    <template #empty><div class="mmp-selempty">{{ form.idDivisi ? 'Tidak ada departemen untuk divisi ini' : 'Pilih divisi terlebih dahulu' }}</div></template>
                                </el-select>
                            </div>
                        </div>
                        <div class="wca-frow">
                            <div>
                                <label class="wca-field-lbl"
                                    ><i class="bi bi-bar-chart-steps"></i> Level HRIS
                                    <span class="mmp-req">*</span></label
                                >
                                <el-select
                                    v-model="form.idLevel"
                                    placeholder="Pilih level"
                                    filterable
                                    style="width: 100%"
                                >
                                    <el-option
                                        v-for="l in levelOptions"
                                        :key="l.value"
                                        :label="l.label"
                                        :value="l.value"
                                    />
                                </el-select>
                            </div>
                            <div>
                                <label class="wca-field-lbl"
                                    ><i class="bi bi-briefcase"></i> Jabatan Posisi
                                    <span class="mmp-req">*</span></label
                                >
                                <el-select
                                    v-model="form.idJabatan"
                                    placeholder="Pilih jabatan"
                                    filterable
                                    style="width: 100%"
                                >
                                    <el-option
                                        v-for="j in jabatanOptions"
                                        :key="j.value"
                                        :label="j.label"
                                        :value="j.value"
                                    />
                                </el-select>
                            </div>
                        </div>

                        <!-- KETENTUAN SLA & PERIODE TARGET
                             Tanggal periode diisi sistem, bukan diketik admin. Selama
                             tidak ada yang menuliskannya, layar ini diam-diam menaruh
                             tanggal yang tak pernah dijelaskan asalnya - dan angka
                             yang menentukannya, "berapa hari kerja", tidak muncul di
                             mana pun padahal justru itu isi kebijakannya. -->

                        <!-- MT: bukan peringatan. Tidak terikat SLA itu keadaan
                             normal baginya, jadi nadanya netral (is-longgar). -->
                        <div v-if="mtDipilih" class="mmp-slawrap">
                            <div class="mmp-slanull is-longgar">
                                <div class="mmp-slanull__head">
                                    <i class="bi bi-mortarboard-fill"></i>
                                    <div>
                                        <b>Program MT tidak terikat SLA level</b>
                                        <span>
                                            Periode targetnya mengikuti <b>tanggal MPP ini dibuat</b><template v-if="form.tanggalPeriode"> &mdash; {{ tglPanjang(form.tanggalPeriode) }}</template>.
                                            Ketentuan hari kerja per level hanya berlaku untuk Rekrutmen Reguler.
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── MPP INI SUDAH DIPERPANJANG ────────────────────────────
                             Tenggatnya BEKU di layar ini, dan itu harus dikatakan —
                             bukan dibiarkan sebagai tanggal yang tidak ikut berubah saat
                             levelnya diganti. Admin yang mengganti level lalu melihat
                             tanggal tidak bergerak akan mengira borangnya rusak, dan
                             mencoba lagi.

                             Tenggat hanya boleh bergeser lewat tombol Perpanjang, yang
                             menuntut alasan tertulis. Itu disebutkan berikut tombolnya,
                             supaya yang memang perlu memindahkannya tahu ke mana. -->
                        <div v-else-if="editingNo && slaTerkunciPerpanjangan" class="mmp-slawrap">
                            <div class="mmp-slanull is-panjang">
                                <div class="mmp-slanull__head">
                                    <i class="bi bi-calendar-check-fill"></i>
                                    <div>
                                        <b>Tenggat MPP ini sudah diperpanjang {{ slaPerpanjanganKe }}&times;</b>
                                        <span>
                                            Tenggat yang berlaku <b>{{ tglPanjang(slaBatasBerlaku) }}</b>
                                            <template v-if="slaBatasAsli && slaBatasAsli !== slaBatasBerlaku">
                                                (aslinya {{ tglPanjang(slaBatasAsli) }})</template>.
                                            Menyunting MPP di sini <b>tidak menggeser tenggat</b> &mdash; termasuk bila
                                            levelnya diganti. Gunakan tombol <b>Perpanjang SLA</b> di kartu atau panel
                                            detail, yang mencatat alasannya.
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="slaMemuat" class="mmp-sla is-muat">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>Membaca ketentuan SLA level yang dipilih&hellip;</span>
                        </div>

                        <!-- ANGKA HARINYA DITULIS, bukan cuma rentang tanggalnya.
                             "20 Agu - 1 Okt" bisa lahir dari janji 30 hari kerja
                             maupun 45 - tergantung berapa akhir pekan dan hari libur
                             yang kebetulan jatuh di dalamnya. Menghitungnya mundur
                             dari dua tanggal bukan pekerjaan pembaca. -->
                        <div v-else-if="periodeHari" class="mmp-slabox">
                            <div class="mmp-tgl">
                                <i class="bi bi-calendar-check"></i>
                                <div class="mmp-tgl__txt">
                                    <small>Periode Target</small>
                                    <b>{{ periodeTeks }}</b>
                                    <em><b>{{ periodeHari }} hari kerja</b> untuk level {{ namaLevelTerpilih }}.</em>
                                </div>
                                <i class="bi bi-lock-fill mmp-tgl__lock" title="Dihitung dari ketentuan level - tidak diisi manual."></i>
                            </div>
                            <p class="mmp-slabox__note">
                                <i class="bi bi-snow"></i>
                                <span>
                                    Angka ini <b>dibekukan</b> pada MPP saat disimpan. Ketentuan master boleh berubah
                                    nanti; MPP ini tetap dinilai dengan angka yang berlaku hari ini.
                                </span>
                            </p>
                        </div>

                        <!-- Gagal DIBEDAKAN dari tidak ada. Yang pertama bisa dicoba
                             lagi; yang kedua butuh orang menambah aturannya. -->
                        <div v-else-if="slaGagal" class="mmp-slawrap">
                            <div class="mmp-slanull">
                                <div class="mmp-slanull__head">
                                    <i class="bi bi-wifi-off"></i>
                                    <div>
                                        <b>Ketentuan SLA gagal dibaca</b>
                                        <span>Bukan berarti aturannya tidak ada &mdash; yang gagal pembacaannya. Periode target belum bisa dihitung sampai ini berhasil.</span>
                                    </div>
                                </div>
                                <div class="mmp-slanull__act">
                                    <button type="button" class="wca-btn wca-btn--ghost" @click="muatSla(form.idLevel)">
                                        <i class="bi bi-arrow-clockwise"></i> Periksa lagi
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="slaKurang" class="mmp-slawrap">
                            <div class="mmp-slanull" :class="{ 'is-longgar': !!editingNo }">
                                <div class="mmp-slanull__head">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <div>
                                        <b>Level {{ namaLevelTerpilih }} belum punya ketentuan SLA</b>
                                        <span v-if="editingNo">
                                            MPP ini tetap boleh disunting &mdash; periodenya sudah tercatat sejak dibuat, dan
                                            kebijakan yang berubah belakangan tidak menghapusnya.
                                        </span>
                                        <span v-else>
                                            Periode target dihitung dari <b>jumlah hari kerja</b> milik level ini, jadi MPP
                                            baru belum bisa dibuat sampai aturannya ada.
                                        </span>
                                    </div>
                                </div>
                                <div v-if="!editingNo" class="mmp-slanull__act">
                                    <button type="button" class="wca-btn wca-btn--ghost" @click="bukaMasterSla">
                                        <i class="bi bi-box-arrow-up-right"></i> Buka Master SLA MPP
                                    </button>
                                    <button type="button" class="wca-btn wca-btn--ghost" @click="muatSla(form.idLevel)">
                                        <i class="bi bi-arrow-clockwise"></i> Periksa lagi
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mmp-form-card">
                    <div class="mmp-form-card__head">
                        <i class="bi bi-geo-alt-fill"></i>
                        <div>
                            <h4>Target, Kuota & Penanggung Jawab</h4>
                            <p>Tentukan kuota rekrutmen, lokasi penempatan, dan PIC</p>
                        </div>
                    </div>
                    <div class="wca-form">
                        <div class="mmp-target-box">
                            <div>
                                <label class="wca-field-lbl"
                                    ><i class="bi bi-people-fill"></i> Kuota Target (Orang)
                                    <span class="mmp-req">*</span></label
                                >
                                <el-input-number
                                    v-model="form.jumlahRekrutmen"
                                    :min="1"
                                    controls-position="right"
                                    style="width: 100%"
                                />
                            </div>
                        </div>
                        <div class="wca-frow">
                            <div>
                                <label class="wca-field-lbl"
                                    ><i class="bi bi-geo-alt"></i> Lokasi Penempatan
                                    <span class="mmp-req">*</span></label
                                >
                                <el-select
                                    v-model="form.kodeLokasi"
                                    placeholder="Pilih lokasi"
                                    filterable
                                    style="width: 100%"
                                >
                                    <el-option
                                        v-for="l in lokasiOptions"
                                        :key="l.value"
                                        :label="l.label"
                                        :value="l.value"
                                    />
                                </el-select>
                            </div>
                            <div>
                                <label class="wca-field-lbl"
                                    ><i class="bi bi-person-badge"></i> Penanggung Jawab (PIC)
                                    <span class="mmp-req">*</span></label
                                >
                                <el-select
                                    v-model="form.kodeKaryawan"
                                    placeholder="Cari nama karyawan (ketik min. 2 huruf)…"
                                    filterable
                                    remote
                                    reserve-keyword
                                    :remote-method="cariKaryawan"
                                    :loading="karyawanLoading"
                                    style="width: 100%"
                                >
                                    <el-option v-for="k in karyawanOptions" :key="k.value" :label="k.label" :value="k.value" />
                                    <template #empty><div class="mmp-selempty">Ketik nama untuk mencari karyawan aktif</div></template>
                                </el-select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Klasifikasi Lowongan -->
            <div v-show="modalTab === 2" class="mmp-tab-pane">
                <div class="mmp-form-card">
                    <div class="mmp-form-card__head">
                        <i class="bi bi-card-text"></i>
                        <div>
                            <h4>Ringkasan Deskripsi Lowongan</h4>
                            <p>Tuliskan gambaran umum posisi untuk ditampilkan di detail lowongan</p>
                        </div>
                    </div>
                    <div class="wca-form">
                        <div>
                            <label class="wca-field-lbl">Deskripsi Posisi</label>
                            <el-input
                                v-model="form.deskripsi"
                                type="textarea"
                                :rows="4"
                                maxlength="1000"
                                show-word-limit
                                placeholder="Jelaskan ringkasan peran, tujuan utama posisi ini, dan lingkup kerja secara singkat…"
                            />
                        </div>
                    </div>
                </div>

                <div class="mmp-form-card">
                    <div class="mmp-form-card__head">
                        <i class="bi bi-tags-fill"></i>
                        <div>
                            <h4>Atribut Klasifikasi Kerja</h4>
                            <p>Label pencarian & filter lowongan publik untuk pencari kerja</p>
                        </div>
                    </div>
                    <div class="wca-form">
                        <div class="wca-frow wca-frow--3">
                            <div>
                                <label class="wca-field-lbl"><i class="bi bi-briefcase"></i> Tipe Kerja</label>
                                <el-select
                                    v-model="form.employmentType"
                                    placeholder="Pilih tipe kerja"
                                    filterable
                                    clearable
                                    style="width: 100%"
                                >
                                    <el-option
                                        v-for="e in klasifikasi.employment"
                                        :key="e.value"
                                        :label="e.label"
                                        :value="e.value"
                                    />
                                </el-select>
                            </div>
                            <div>
                                <label class="wca-field-lbl"><i class="bi bi-laptop"></i> Lokasi Kerja</label>
                                <el-select
                                    v-model="form.workplaceType"
                                    placeholder="Pilih lokasi kerja"
                                    filterable
                                    clearable
                                    style="width: 100%"
                                >
                                    <el-option
                                        v-for="w in klasifikasi.workplace"
                                        :key="w.value"
                                        :label="w.label"
                                        :value="w.value"
                                    />
                                </el-select>
                            </div>
                            <div>
                                <label class="wca-field-lbl"><i class="bi bi-stars"></i> Tingkat Pengalaman</label>
                                <el-select
                                    v-model="form.experienceLevel"
                                    placeholder="Pilih tingkat pengalaman"
                                    filterable
                                    clearable
                                    style="width: 100%"
                                >
                                    <el-option
                                        v-for="x in klasifikasi.experience"
                                        :key="x.value"
                                        :label="x.label"
                                        :value="x.value"
                                    />
                                </el-select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Detail & Benefit -->
            <div v-show="modalTab === 3" class="mmp-tab-pane">
                <div class="mmp-form-card">
                    <div class="mmp-form-card__head">
                        <i class="bi bi-list-check"></i>
                        <div>
                            <h4>Tanggung Jawab & Persyaratan Posisi</h4>
                            <p>Daftar poin utama tugas dan kualifikasi kandidat (tampil di portal karir)</p>
                        </div>
                    </div>
                    <div class="wca-form">
                        <div class="wca-frow">
                            <PointsEditor
                                v-model="form.tanggungJawab"
                                label="Tanggung Jawab Utama"
                                icon="bi-check-circle-fill"
                                placeholder="mis. Mengelola laporan keuangan bulanan divisi"
                            />
                            <PointsEditor
                                v-model="form.persyaratan"
                                label="Persyaratan & Kualifikasi"
                                icon="bi-dot"
                                placeholder="mis. Pendidikan min. S1 Akuntansi / Keuangan"
                            />
                        </div>
                    </div>
                </div>

                <div class="mmp-form-card">
                    <div class="mmp-form-card__head">
                        <i class="bi bi-award-fill"></i>
                        <div>
                            <h4>Skill & Benefit Karyawan</h4>
                            <p>Keahlian yang dicari dan fasilitas/kompensasi yang ditawarkan</p>
                        </div>
                    </div>
                    <div class="wca-form">
                        <!-- SKILL & BENEFIT MASING-MASING SELEBAR KARTU.

                             Keduanya kotak multi-pilih yang isinya menumpuk ke bawah: enam skill
                             saja sudah membuat kotaknya setinggi tiga baris, sementara tetangganya
                             di sebelah tetap satu baris — dan separuh lebar itu juga yang membuat
                             penyaring kategori terpangkas jadi "S…".

                             Dua baris penuh, bukan dua kolom sempit. -->
                        <div>
                            <div class="mmp-sk-label-row">
                                <label class="wca-field-lbl"
                                    ><i class="bi bi-tools"></i> Skill yang Dibutuhkan
                                    <span class="mmp-hint">(ketik & enter untuk buat baru)</span></label
                                >

                                <!-- Penyaring kategori skill — mempersempit DAFTAR PILIHAN,
                                     bukan menghapus skill yang sudah terpilih. -->
                                <div v-if="skillCategories.length" class="mmp-cat-filter-inline">
                                    <span class="mmp-cat-filter-lbl">Filter Kategori:</span>
                                    <el-select
                                        v-model="selectedSkillCategory"
                                        class="mmp-cat-filter__sel"
                                        placeholder="Semua Kategori"
                                    >
                                        <el-option value="ALL" label="Semua Kategori" />
                                        <el-option
                                            v-for="cat in skillCategories"
                                            :key="cat.id"
                                            :value="cat.id"
                                            :label="cat.nama"
                                        >
                                            <div class="mmp-skopt">
                                                <span
                                                    class="mmp-skopt__dot"
                                                    :style="{ background: cat.warna }"
                                                ></span>
                                                <span>{{ cat.nama }}</span>
                                            </div>
                                        </el-option>
                                    </el-select>
                                </div>
                            </div>

                            <el-select
                                v-model="form.skills"
                                multiple
                                filterable
                                allow-create
                                default-first-option
                                placeholder="Pilih atau ketik skill baru…"
                                style="width: 100%"
                            >
                                <el-option-group
                                    v-for="grp in filteredSkillGroups"
                                    :key="grp.key"
                                    :label="grp.label"
                                >
                                    <el-option
                                        v-for="s in grp.items"
                                        :key="s.value"
                                        :label="s.label"
                                        :value="s.value"
                                    >
                                        <div class="mmp-skopt">
                                            <span class="mmp-skopt__dot" :style="{ background: grp.warna }"></span>
                                            <span class="mmp-skopt__name">{{ s.label }}</span>
                                        </div>
                                    </el-option>
                                </el-option-group>
                            </el-select>
                        </div>
                        <div>
                            <label class="wca-field-lbl"
                                ><i class="bi bi-gift-fill"></i> Benefit & Fasilitas
                                <span class="mmp-hint">(ketik & enter untuk buat baru)</span></label
                            >
                            <el-select
                                v-model="form.benefits"
                                multiple
                                filterable
                                allow-create
                                default-first-option
                                placeholder="Pilih atau ketik benefit baru…"
                                style="width: 100%"
                            >
                                <el-option
                                    v-for="b in benefitOptions"
                                    :key="b.value"
                                    :label="b.label"
                                    :value="b.value"
                                />
                            </el-select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ TAB 4: PRATINJAU & SIMPAN ══════════════════════════════════
                 Halaman terakhir sengaja TIDAK berisi kotak isian. Isinya bacaan:
                 seluruh isi borang dirangkai jadi kalimat yang bisa diperiksa
                 sekali jalan, sebab tiga langkah sebelumnya tidak pernah terlihat
                 bersamaan — dan kekeliruan yang paling mahal (level salah, kuota
                 salah, lokasi salah) justru yang paling mudah lolos saat tiap
                 bagian dibaca terpisah.

                 Tiap kartu punya tautan "Ubah" yang melompat ke langkah asalnya,
                 jadi memperbaiki satu bidang tidak menuntut orang menekan Kembali
                 tiga kali lalu mencari sendiri bidangnya. -->
            <div v-show="modalTab === 4" class="mmp-tab-pane">
                <div class="mmp-rev">
                    <!-- IKHTISAR -->
                    <div class="mmp-rev__hero">
                        <span class="mmp-rev__tag" :class="{ 'is-mt': form.jenisProgram === 'MT' }">
                            <i class="bi" :class="form.jenisProgram === 'MT' ? 'bi-mortarboard-fill' : 'bi-person-workspace'"></i>
                            {{ ringkas.jenis }}
                        </span>
                        <h3>{{ ringkas.jabatan || 'Jabatan belum dipilih' }}</h3>
                        <p>
                            {{ ringkas.divisi || 'Divisi belum dipilih' }}<template v-if="ringkas.departemen"> &middot; {{ ringkas.departemen }}</template>
                            <template v-if="ringkas.level"> &middot; {{ ringkas.level }}</template>
                        </p>

                        <div class="mmp-rev__angka">
                            <div><small>Kuota Target</small><b>{{ ringkas.kuota || 0 }} orang</b></div>
                            <div><small>Periode Target</small><b>{{ periodeTeks || '—' }}</b></div>
                            <div><small>Lokasi Penempatan</small><b>{{ ringkas.lokasi || '—' }}</b></div>
                        </div>
                    </div>

                    <!-- KELENGKAPAN — yang kurang disebut NAMANYA, dan namanya bisa
                         ditekan. "Masih ada bidang wajib yang kosong" tanpa menyebut
                         yang mana memindahkan pencarian ke orang yang membacanya. -->
                    <div v-if="bidangKurang.length" class="mmp-rev__kurang">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <b>{{ bidangKurang.length }} bidang wajib masih kosong.</b>
                            <span>Tekan namanya untuk melompat ke langkah tempat bidang itu berada.</span>
                            <div class="mmp-rev__chips">
                                <button
                                    v-for="b in bidangKurang"
                                    :key="b.label"
                                    type="button"
                                    class="mmp-rev__chip"
                                    :title="`Buka Langkah ${b.langkah}`"
                                    @click="pergiKeLangkah(b.langkah)"
                                >{{ b.label }}</button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mmp-rev__siap">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>
                            <b>Seluruh bidang wajib sudah terisi.</b>
                            Periksa sekali lagi di bawah — nomor transaksi dibuat otomatis saat disimpan.
                        </div>
                    </div>

                    <!-- POKOK TRANSAKSI -->
                    <div class="mmp-rev__grid">
                        <div class="mmp-rev__card">
                            <div class="mmp-rev__cardhead">
                                <i class="bi bi-building"></i><h5>Struktur &amp; Jabatan</h5>
                                <button type="button" class="mmp-rev__ubah" @click="pergiKeLangkah(1)">Ubah</button>
                            </div>
                            <div class="mmp-rev__row"><span>Divisi</span><b>{{ ringkas.divisi || '—' }}</b></div>
                            <div class="mmp-rev__row"><span>Departemen</span><b>{{ ringkas.departemen || '—' }}</b></div>
                            <div class="mmp-rev__row"><span>Level HRIS</span><b>{{ ringkas.level || '—' }}</b></div>
                            <div class="mmp-rev__row"><span>Jabatan</span><b>{{ ringkas.jabatan || '—' }}</b></div>
                        </div>

                        <div class="mmp-rev__card">
                            <div class="mmp-rev__cardhead">
                                <i class="bi bi-geo-alt-fill"></i><h5>Target &amp; Penanggung Jawab</h5>
                                <button type="button" class="mmp-rev__ubah" @click="pergiKeLangkah(1)">Ubah</button>
                            </div>
                            <div class="mmp-rev__row"><span>Kuota</span><b>{{ ringkas.kuota || 0 }} orang</b></div>
                            <div class="mmp-rev__row"><span>Periode Target</span><b>{{ periodeTeks || '—' }}</b></div>
                            <!-- Angka harinya ditulis, bukan cuma rentang tanggalnya:
                                 rentang yang sama bisa lahir dari janji 30 hari kerja
                                 maupun 45, tergantung akhir pekan & libur di dalamnya. -->
                            <div class="mmp-rev__row">
                                <span>Ketentuan SLA</span>
                                <b v-if="mtDipilih">Tidak terikat SLA</b>
                                <b v-else>{{ periodeHari ? `${periodeHari} hari kerja` : '—' }}</b>
                            </div>
                            <div class="mmp-rev__row"><span>Lokasi</span><b>{{ ringkas.lokasi || '—' }}</b></div>
                            <div class="mmp-rev__row"><span>PIC</span><b>{{ ringkas.pic || '—' }}</b></div>
                        </div>

                        <div class="mmp-rev__card">
                            <div class="mmp-rev__cardhead">
                                <i class="bi bi-sliders"></i><h5>Klasifikasi Kerja</h5>
                                <button type="button" class="mmp-rev__ubah" @click="pergiKeLangkah(2)">Ubah</button>
                            </div>
                            <div class="mmp-rev__row"><span>Tipe Kerja</span><b>{{ ringkas.employment || '—' }}</b></div>
                            <div class="mmp-rev__row"><span>Lokasi Kerja</span><b>{{ ringkas.workplace || '—' }}</b></div>
                            <div class="mmp-rev__row"><span>Pengalaman</span><b>{{ ringkas.experience || '—' }}</b></div>
                        </div>
                    </div>

                    <!-- DESKRIPSI -->
                    <div class="mmp-rev__card">
                        <div class="mmp-rev__cardhead">
                            <i class="bi bi-card-text"></i><h5>Ringkasan Deskripsi Lowongan</h5>
                            <button type="button" class="mmp-rev__ubah" @click="pergiKeLangkah(2)">Ubah</button>
                        </div>
                        <p class="mmp-rev__teks">{{ form.deskripsi || 'Belum diisi.' }}</p>
                    </div>

                    <!-- TANGGUNG JAWAB & PERSYARATAN -->
                    <div class="mmp-rev__grid2">
                        <div class="mmp-rev__card">
                            <div class="mmp-rev__cardhead">
                                <i class="bi bi-list-task"></i><h5>Tanggung Jawab</h5>
                                <span v-if="isiTanggungJawab.length" class="mmp-rev__hitung">{{ isiTanggungJawab.length }}</span>
                                <button type="button" class="mmp-rev__ubah" @click="pergiKeLangkah(3)">Ubah</button>
                            </div>
                            <ul v-if="isiTanggungJawab.length" class="mmp-rev__list">
                                <li v-for="(t, i) in isiTanggungJawab" :key="i">{{ t }}</li>
                            </ul>
                            <p v-else class="mmp-rev__teks">Belum ada butir.</p>
                        </div>

                        <div class="mmp-rev__card">
                            <div class="mmp-rev__cardhead">
                                <i class="bi bi-check2-square"></i><h5>Persyaratan</h5>
                                <span v-if="isiPersyaratan.length" class="mmp-rev__hitung">{{ isiPersyaratan.length }}</span>
                                <button type="button" class="mmp-rev__ubah" @click="pergiKeLangkah(3)">Ubah</button>
                            </div>
                            <ul v-if="isiPersyaratan.length" class="mmp-rev__list">
                                <li v-for="(t, i) in isiPersyaratan" :key="i">{{ t }}</li>
                            </ul>
                            <p v-else class="mmp-rev__teks">Belum ada butir.</p>
                        </div>
                    </div>

                    <!-- SKILL & BENEFIT -->
                    <div class="mmp-rev__grid2">
                        <div class="mmp-rev__card">
                            <div class="mmp-rev__cardhead">
                                <i class="bi bi-stars"></i><h5>Skill</h5>
                                <span v-if="ringkas.skills.length" class="mmp-rev__hitung">{{ ringkas.skills.length }}</span>
                                <button type="button" class="mmp-rev__ubah" @click="pergiKeLangkah(3)">Ubah</button>
                            </div>
                            <div v-if="ringkas.skills.length" class="mmp-rev__tags">
                                <span v-for="(s, i) in ringkas.skills" :key="i" class="mmp-rev__tag2">{{ s }}</span>
                            </div>
                            <p v-else class="mmp-rev__teks">Belum ada skill dipilih.</p>
                        </div>

                        <div class="mmp-rev__card">
                            <div class="mmp-rev__cardhead">
                                <i class="bi bi-gift"></i><h5>Benefit</h5>
                                <span v-if="ringkas.benefits.length" class="mmp-rev__hitung">{{ ringkas.benefits.length }}</span>
                                <button type="button" class="mmp-rev__ubah" @click="pergiKeLangkah(3)">Ubah</button>
                            </div>
                            <div v-if="ringkas.benefits.length" class="mmp-rev__tags">
                                <span v-for="(b, i) in ringkas.benefits" :key="i" class="mmp-rev__tag2 is-benefit">{{ b }}</span>
                            </div>
                            <p v-else class="mmp-rev__teks">Belum ada benefit dipilih.</p>
                        </div>
                    </div>
                </div>
            </div>


            <!-- ══ TOMBOL WIZARD — DI KAKI MODAL, BUKAN DI BADAN ══════════════
                 Sebelumnya bilah tombol duduk di dalam badan yang tergulir, jadi
                 pada borang sepanjang ini ia ikut hanyat ke luar layar; sementara
                 kaki modal menampilkan "Simpan Data" bawaan AdminModal yang tidak
                 tersambung ke apa pun. Satu-satunya tombol yang terlihat justru
                 yang tidak bekerja.

                 Tiga tombol, dan ketiganya membaca keadaan yang sama:
                   Kembali  - hanya muncul bila ada langkah sebelumnya
                   Batal    - selalu ada, satu-satunya jalan keluar selain X
                   Lanjut   - berubah jadi Simpan HANYA di langkah terakhir
                              (labelAksi), tidak pernah di tengah. -->
            <template #footer>
                <button
                    v-if="modalTab > 1"
                    type="button"
                    class="wca-btn wca-btn--ghost"
                    :disabled="modalSibuk"
                    @click="pergiKeLangkah(modalTab - 1)"
                >
                    <i class="bi bi-arrow-left"></i> Kembali
                </button>

                <button
                    type="button"
                    class="wca-btn wca-btn--ghost"
                    :disabled="modalSibuk"
                    @click="show = false"
                >
                    <i class="bi bi-x-lg"></i> Batal
                </button>

                <button
                    type="button"
                    class="wca-btn wca-btn--dark"
                    :disabled="aksiTerkunci"
                    :title="judulAksi"
                    @click="aksiUtama"
                >
                    <span v-if="modalSibuk" class="wca-spin" aria-hidden="true"></span>
                    <i v-else class="bi" :class="langkahAkhir ? 'bi-save' : 'bi-arrow-right'"></i>
                    {{ labelAksi }}
                </button>
            </template>
        </AdminModal>

        <ConfirmModal
            :show="batalShow"
            :busy="batalBusy"
            title="Batalkan Transaksi MPP"
            confirm-label="Ya, Batalkan"
            note="MPP ini akan berhenti muncul sebagai pilihan saat membuka program rekrutmen baru. Riwayat lamaran/program yang sudah ada tetap utuh dan bisa diaktifkan kembali kapan saja."
            @cancel="batalShow = false"
            @confirm="confirmBatalkan"
        >
            Yakin ingin membatalkan transaksi <strong>{{ batalTarget?.noTransaksi }}</strong> ({{
                batalTarget?.jabatan?.nama
            }})?
        </ConfirmModal>

        <!-- Profil kandidat pengisi kursi — BACA SAJA, tanpa tombol aksi.
             Lihat catatan kepala KandidatDetailModal.vue. -->
        <KandidatDetailModal
            :show="kandidatShow"
            :no-mpp="kandidatNo"
            :kandidat="kandidatTarget"
            @tutup="kandidatShow = false"
        />

        <PerpanjangSlaModal
            :show="panjangShow"
            :busy="panjangBusy"
            :no="panjangTarget?.noTransaksi || ''"
            :keadaan="panjangKeadaan"
            @close="panjangShow = false"
            @confirm="konfirmasiPerpanjang"
        />

        <MasterMppDetailPanel
            ref="panelRef"
            :no="selectedNo"
            @close="selectedNo = null"
            @edit="
                (d) => {
                    selectedNo = null;
                    openEdit(d);
                }
            "
            @toggle-selesai="(d) => toggleSelesai(d, true)"
            @batalkan="
                (d) => {
                    selectedNo = null;
                    askBatalkan(d);
                }
            "
            @aktifkan="(d) => aktifkanKembali(d, true)"
            @perpanjang="askPerpanjang"
        />

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import Pagination from '../../../../components/ui/Pagination.vue';
import MasterMppCard from './MasterMppCard.vue';
import MasterMppDetailPanel from './MasterMppDetailPanel.vue';
import MasterMppSplit from './MasterMppSplit.vue';
import PerpanjangSlaModal from './PerpanjangSlaModal.vue';
import KandidatDetailModal from './KandidatDetailModal.vue';
import PointsEditor from './PointsEditor.vue';
import { formatTanggal, initials, statusBadge, statusLabel, periodeLabel, jenisProgramLabel } from '@utils/career/masterMpp';
import { ingatModal } from '@utils/ingatModal';
import { punyaRentang as cekRentang, rentangPanjang } from '@utils/rentangTanggal';
import { hariIni } from '@utils/tanggalLokal';
import { kunciSla, penuhMpp, kursiMpp } from '@utils/career/mppSla';

/* 'full' | 'partial' | 'empty' — dipakai tab keterisian di panel kiri. */
function kunciIsi(m) {
    if (penuhMpp(m)) return 'full';

    return kursiMpp(m).terisi > 0 ? 'partial' : 'empty';
}

const API = '/api/v1/master-mpp';
const CFG = { headers: { Accept: 'application/json' } };
// Kolom yang bisa diklik di kepala tabel. 'terbaru' TIDAK ikut: ia keadaan
// awal daftar (urutan MPP dibuat), bukan kolom yang ditampilkan.
const SORTABLE = ['tanggal_periode', 'status'];

// Langkah wizard MPP. Ditulis sebagai data, bukan empat blok tombol kembar:
// judulnya dipakai bilah langkah, nomornya dipakai tombol kaki modal dan
// tautan lompat di pratinjau — semuanya membaca sumber yang sama.
const LANGKAH = [
    { no: 1, judul: 'Transaksi & Posisi', sub: 'Program, Divisi & Target' },
    { no: 2, judul: 'Klasifikasi Lowongan', sub: 'Tipe, Lokasi & Pengalaman' },
    { no: 3, judul: 'Detail & Benefit', sub: 'Tanggung Jawab & Skill' },
    { no: 4, judul: 'Pratinjau & Simpan', sub: 'Periksa sebelum tersimpan' },
];
const LANGKAH_AKHIR = LANGKAH.length;

const FORM_KOSONG = () => ({
    jenisProgram: 'REKRUTMEN',
    idDivisi: null,
    idSubDivisi: null,
    idLevel: null,
    idJabatan: null,
    jumlahRekrutmen: 1,
    tanggalPeriode: '',
    kodeLokasi: null,
    kodeKaryawan: null,
    deskripsi: '',
    employmentType: null,
    workplaceType: null,
    experienceLevel: null,
    tanggungJawab: [],
    persyaratan: [],
    skills: [],
    benefits: [],
});

export default {
    components: { Head, AdminModal, ConfirmModal, Pagination, MasterMppCard, MasterMppDetailPanel, MasterMppSplit, PerpanjangSlaModal, KandidatDetailModal, PointsEditor },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-mpp/masterMpp')],
    data() {
        return {
            list: [],
            stats: { total: 0, aktif: 0, selesai: 0, dibatalkan: 0 },
            loading: false,
            ready: false,
            error: false,
            page: 1,
            perPage: 12,
            totalPages: 1,
            totalData: 0,
            // Yang baru dibuat muncul paling atas — di situ orang mencarinya
            // sesaat setelah menyimpan.
            sort: 'terbaru',
            dir: 'desc',
            searchTimer: null,

            q: '',
            fStatus: '',
            fSelesai: '',
            fJenis: '',
            fDivisi: null,
            fPeriode: null,
            fEmployment: null,
            fWorkplace: null,
            fExperience: null,
            periodeOptions: [],

            divisiOptions: [],
            subDivisiOptions: [],
            levelOptions: [],
            jabatanOptions: [],
            lokasiOptions: [],
            karyawanOptions: [],
            karyawanLoading: false,
            klasifikasi: { employment: [], workplace: [], experience: [] },
            skillOptions: [],
            benefitOptions: [],
            selectedSkillCategory: 'ALL',

            LANGKAH,

            show: false,
            editingNo: null,
            saving: false,
            loadingDetail: false,
            modalTab: 1,
            form: FORM_KOSONG(),

            batalShow: false,
            batalTarget: null,
            batalBusy: false,

            // PERPANJANG SLA. `panjangKeadaan` sengaja BUKAN turunan dari kartu:
            // syarat boleh/tidaknya dihitung server (PerpanjangSla::keadaan) dan
            // ikut menimbang hal-hal yang tidak ada di kartu — kursi yang sudah
            // penuh, jatah yang sudah habis. Layar yang menyimpulkan sendiri
            // cepat atau lambat menawarkan tombol yang lalu ditolak.
            // Snapshot perpanjangan MPP yang sedang DISUNTING (bukan yang sedang
            // diperpanjang) — lihat panel beku di langkah 1.
            slaPanjang: null,

            // ── TAMPILAN SPLIT ──────────────────────────────────────────────
            // Detailnya diambil terpisah (endpoint detail) karena daftar sengaja
            // tidak membawa Tanggung Jawab/Persyaratan/Skill/Benefit — lihat list().
            splitNo: null,
            splitDetail: null,
            splitMemuat: false,
            splitTab: 'kandidat',
            splitMobileDetail: false,
            // Penyaring MILIK PANEL KIRI, bekerja di atas baris yang sudah dimuat.
            // Tidak dikirim ke server: keduanya (keterisian & SLA) dihitung dari
            // tanggal + kursi yang sudah ada di tiap baris, jadi memutarnya lewat
            // server berarti satu perjalanan jaringan untuk jawaban yang sudah
            // dipegang layar.
            fIsiSplit: 'all',
            fSlaSplit: 'all',
            // PIC bisa LEBIH DARI SATU — larik, bukan satu nilai. Lihat catatan
            // di panel split: rekruter sering membandingkan beban dua-tiga PIC
            // sekaligus, dan penyaring nilai-tunggal memaksa itu jadi tiga kali
            // muat halaman yang hasilnya tidak pernah bisa dilihat berdampingan.
            fPicSplit: [],
            picOptions: [],
            ringkasKursi: { kuota: 0, terisi: 0, sisa: 0, lewat: 0 },
            // [dari, sampai] — null berarti belum disaring. Dikirim apa adanya
            // ke server; sisi yang dikosongkan memang boleh sendirian.
            rentangTgl: null,

            kandidat: [],
            kandidatTotal: 0,
            kandidatHal: 1,
            kandidatMemuat: false,
            cq: '',
            cpage: 1,

            panjangShow: false,
            // Profil kandidat (baca saja) — lihat KandidatDetailModal.vue.
            kandidatShow: false,
            kandidatTarget: null,
            kandidatNo: '',
            panjangTarget: null,
            panjangKeadaan: null,
            panjangBusy: false,
            selectedNo: null,
            toast: '',
            tm: null,

            // BATAS SLA LEVEL YANG SEDANG DIPILIH — datang dari server, tidak
            // pernah dihitung di sini. Hitungan hari kerja hidup di satu tempat
            // (SlaMpp), supaya kalender di layar tidak pernah menawarkan tanggal
            // yang lalu ditolak pintu simpan.
            sla: { hari: null, mulai: null, batas: null },
            // Ketentuan yang DIBEKUKAN pada MPP yang sedang disunting. Berbeda
            // dari `sla` di atas, yang selalu hitungan hari ini.
            slaBeku: null,
            // Tanggal MPP yang sedang disunting DIBUAT. Inilah periode sebuah
            // program MT — dibaca dari barisnya, bukan ditebak "hari ini".
            mppDibuat: null,
            slaMemuat: false,
            slaGagal: false,
            levelBerubah: false,
        };
    },
    computed: {
        /* Ringkasan kursi & SLA untuk bilah atas — angka LINTAS SELURUH MPP,
         * bukan halaman yang sedang terlihat (lihat ringkasKursiSla() di server). */
        /* NAMANYA `ringkasBar`, BUKAN `ringkas`.
         *
         * `ringkas` sudah dipakai pratinjau wizard di bawah, dan dua computed
         * bernama sama tidak menimbulkan galat apa pun saat dibangun — yang
         * terakhir sekadar menang diam-diam. Akibatnya bilah atas menerima objek
         * pratinjau lalu v-for mencacahnya jadi null, dan SELURUH halaman gagal
         * digambar. Kegagalan yang paling mahal justru karena paling sunyi. */
        ringkasBar() {
            const r = this.ringkasKursi || { kuota: 0, terisi: 0, sisa: 0, lewat: 0 };

            return [
                { k: 'kuota', v: r.kuota, c: '#6366f1' },
                { k: 'terisi', v: r.terisi, c: '#10b981' },
                { k: 'sisa', v: r.sisa, c: '#f59e0b' },
                { k: 'lewat SLA', v: r.lewat, c: '#ef4444' },
            ];
        },
        /* ── TAMPILAN SPLIT ───────────────────────────────────────────────────
         *
         * Penyaring keterisian & SLA bekerja DI LAYAR, di atas baris yang sudah
         * dimuat. Keduanya dihitung dari data yang memang sudah ada di tiap baris
         * (kursi + tanggal batas), jadi memutarnya lewat server berarti satu
         * perjalanan jaringan untuk jawaban yang sudah dipegang.
         *
         * Yang TIDAK dilakukan di layar: pencarian, filter divisi/periode, dan
         * pagination. Ketiganya menyentuh baris yang belum dimuat, dan menyaring
         * halaman yang sedang terlihat saja akan menyembunyikan MPP yang cocok
         * hanya karena ia ada di halaman berikutnya. */
        daftarSplit() {
            return (this.list || []).filter(
                (m) =>
                    (this.fIsiSplit === 'all' || kunciIsi(m) === this.fIsiSplit) &&
                    (this.fSlaSplit === 'all' || kunciSla(m) === this.fSlaSplit),
            );
        },
        tabIsiSplit() {
            const n = (k) => (k === 'all' ? this.list.length : this.list.filter((m) => kunciIsi(m) === k).length);

            return [
                { key: 'all', label: 'Semua', count: n('all') },
                { key: 'full', label: 'Terpenuhi', count: n('full') },
                { key: 'partial', label: 'Sebagian', count: n('partial') },
                { key: 'empty', label: 'Kosong', count: n('empty') },
            ];
        },
        /* Jenis program. Hitungannya dari `stats`-lah yang benar (lintas seluruh
         * MPP), bukan dari `list` yang cuma halaman ini — tapi stats belum
         * memecah per jenis, jadi angkanya diambil dari halaman yang terlihat
         * dan sengaja TIDAK ditampilkan saat penyaring lain sedang aktif. */
        tabJenisSplit() {
            const n = (k) =>
                k === 'all'
                    ? this.list.length
                    : this.list.filter((m) => (k === 'MT' ? m.jenisProgram === 'MT' : m.jenisProgram !== 'MT')).length;

            return [
                { key: 'all', label: 'Semua Jenis', count: n('all') },
                { key: 'REKRUTMEN', label: 'Rekrutmen', count: n('REKRUTMEN') },
                { key: 'MT', label: 'MT', count: n('MT') },
            ];
        },
        tabSlaSplit() {
            const n = (k) => (k === 'all' ? this.list.length : this.list.filter((m) => kunciSla(m) === k).length);

            return [
                { key: 'all', label: 'Semua SLA', count: n('all') },
                { key: 'ontime', label: 'Dalam SLA', count: n('ontime') },
                { key: 'risk', label: 'Mendekati', count: n('risk') },
                { key: 'over', label: 'Lewat SLA', count: n('over') },
            ];
        },
        /* ── MPP yang sedang disunting sudah pernah diperpanjang? ─────────────
         *
         * Kalau ya, tenggatnya BEKU di borang Ubah: mengganti level tetap
         * memperbarui angka hari kerjanya (itu memang ikut level), tapi TANGGAL
         * batasnya tidak bergerak. Server yang menegakkannya — ini cuma yang
         * mengatakannya, sebab tanggal yang diam saat levelnya diganti tanpa
         * satu kata pun terbaca sebagai borang yang rusak. */
        slaTerkunciPerpanjangan() {
            return !this.mtDipilih && Number(this.slaPanjang?.ke || 0) > 0;
        },
        slaPerpanjanganKe() {
            return Number(this.slaPanjang?.ke || 0);
        },
        slaBatasBerlaku() {
            return this.slaPanjang?.batas || null;
        },
        slaBatasAsli() {
            return this.slaPanjang?.batasAwal || null;
        },
        hasFilter() {
            return !!(
                this.q ||
                this.fStatus ||
                this.fSelesai ||
                this.fJenis ||
                this.fDivisi ||
                this.fPeriode ||
                this.fEmployment ||
                this.fWorkplace ||
                this.fExperience
            );
        },
        /**
         * PROGRAM MT? — satu jawaban yang dibaca semua aturan di bawah.
         *
         * Ditulis sekali supaya "SLA tidak berlaku untuk MT" tidak tersebar jadi
         * enam perbandingan string yang bisa berbeda pendapat setelah salah satu
         * di antaranya lupa ikut diubah.
         */
        mtDipilih() {
            return this.form.jenisProgram === 'MT';
        },
        /**
         * Awal periode: tanggal MPP ini DIBUAT.
         *
         * Pada MPP lama dipakai tanggal yang dibekukan di barisnya sendiri, bukan
         * hari ini — MPP yang dibuat Juni tidak boleh terbaca seolah baru mulai
         * berjalan sejak layarnya dibuka lagi hari ini.
         */
        periodeMulai() {
            // MT tidak punya rentang: periodenya SATU tanggal, yaitu hari MPP
            // itu dibuat. Memberi awal di sini akan mencetak "22 Agu – 22 Agu",
            // rentang sehari yang tidak pernah dimaksudkan siapa pun.
            if (this.mtDipilih) return null;
            if (this.editingNo && this.slaBeku?.mulai) return this.slaBeku.mulai;

            return this.sla.mulai;
        },
        /**
         * Akhir periode = tenggatnya, dan itu nilai yang benar-benar disimpan.
         *
         * Dibaca dari borang, bukan dari sla.batas: pada MPP lama, sla.batas
         * adalah hitungan ulang dari HARI INI — angka yang tidak pernah tersimpan
         * di mana pun dan tidak sama dengan tenggat MPP itu.
         */
        periodeAkhir() {
            return this.form.tanggalPeriode || this.sla.batas || null;
        },
        periodeHari() {
            if (this.mtDipilih) return null;
            if (this.editingNo && this.slaBeku?.hari) return this.slaBeku.hari;

            return this.sla.hari;
        },
        /**
         * Rentangnya bisa ditulis? — hanya bila awalnya diketahui DAN masuk akal.
         *
         * MPP yang dibuat sebelum kolom snapshot SLA ada tidak menyimpan tanggal
         * mulai. Menambalnya dengan "hari ini" menghasilkan rentang terbalik
         * ("20 Agustus – 1 Juli"), yang lebih buruk daripada tidak ada rentang.
         */
        punyaRentang() {
            return cekRentang(this.periodeMulai, this.periodeAkhir);
        },
        /** "20 Agustus – 1 Oktober 2026" (tahun ditulis sekali bila sama). */
        periodeTeks() {
            if (!this.punyaRentang) return this.tglPanjang(this.periodeAkhir);

            return this.rentangTanggal(this.periodeMulai, this.periodeAkhir);
        },
        /**
         * Butir yang BENAR-BENAR berisi.
         *
         * Editor butir menyisakan baris kosong begitu orang menambah lalu batal
         * mengetik; server memang membuangnya saat menyimpan, tapi pratinjau
         * dibaca SEBELUM itu — dan daftar yang menampilkan tiga bullet kosong
         * terbaca seperti data yang hilang.
         */
        isiTanggungJawab() {
            return (this.form.tanggungJawab || []).map((t) => String(t).trim()).filter(Boolean);
        },
        isiPersyaratan() {
            return (this.form.persyaratan || []).map((t) => String(t).trim()).filter(Boolean);
        },
        /** Nama level yang sedang dipilih — untuk kalimat ketentuan SLA. */
        namaLevelTerpilih() {
            return this.levelOptions.find((o) => o.value === this.form.idLevel)?.label || 'ini';
        },
        /**
         * Level sudah dipilih, aturannya sudah dibaca, dan hasilnya: TIDAK ADA.
         *
         * Bukan sekadar "sla.batas kosong" — saat borang baru dibuka atau saat
         * permintaannya masih di jalan, batasnya juga kosong, dan menandai itu
         * sebagai masalah akan memerahkan borang sebelum ada yang salah.
         */
        slaKurang() {
            // MT tidak pernah "kurang SLA" — ia memang tidak memakainya.
            if (this.mtDipilih) return false;

            return !!this.form.idLevel && !this.slaMemuat && !this.sla.batas;
        },
        /**
         * Borang ditahan? — sama persis dengan gerbang di server.
         *
         * MPP LAMA dikecualikan: aturannya bisa dihapus jauh setelah MPP itu
         * dibuat, dan tanggalnya sudah tercatat sejak dulu. Mengunci suntingan
         * karenanya berarti menghukum orang atas kebijakan yang berubah setelah
         * pekerjaannya selesai.
         */
        slaMenahan() {
            // MT TIDAK PERNAH DITAHAN SLA.
            //
            // Inilah inti perubahannya: sebelumnya MT ikut antre di gerbang yang
            // sama, sehingga memilih level yang belum punya aturan SLA membuat MT
            // mustahil dibuat — terhalang ketentuan yang tidak pernah berlaku
            // untuknya.
            if (this.mtDipilih) return false;

            return this.slaMemuat || (this.slaKurang && !this.editingNo);
        },
        /** Modal sedang bekerja — dipakai semua tombol di kakinya. */
        modalSibuk() {
            return this.saving || this.loadingDetail;
        },
        langkahAkhir() {
            return this.modalTab >= LANGKAH_AKHIR;
        },
        /** Tulisan pada satu-satunya tombol maju. */
        labelAksi() {
            if (this.saving) return 'Menyimpan…';
            if (this.loadingDetail) return 'Memuat detail…';
            if (!this.langkahAkhir) return `Lanjut ke Langkah ${this.modalTab + 1}`;

            return this.editingNo ? 'Perbarui Transaksi' : 'Buat Transaksi';
        },
        /**
         * Tombol maju terkunci?
         *
         * Di langkah 1 hanya SLA yang menahan — bidang wajib lain sengaja TIDAK,
         * supaya orang boleh mengisi tidak berurutan dan melihat pratinjaunya
         * dulu. Yang menahan penyimpanan ada di ujung: di langkah terakhir tombol
         * baru terbuka setelah semua bidang wajib terisi, dan yang kurang
         * disebutkan satu per satu di halaman pratinjau.
         */
        aksiTerkunci() {
            if (this.modalSibuk) return true;
            if (this.langkahAkhir) return !this.isFormValid;

            return this.langkah1Tertahan;
        },
        judulAksi() {
            if (this.langkah1Tertahan) return 'Lengkapi ketentuan SLA level ini dulu';
            if (this.langkahAkhir && !this.isFormValid) return 'Masih ada bidang wajib yang kosong';

            return '';
        },
        /**
         * Bidang wajib yang masih kosong, beserta langkah tempatnya berada.
         *
         * Semuanya kebetulan ada di langkah 1 — tapi nomornya tetap ditulis per
         * bidang, bukan dipukul rata, supaya menambah bidang wajib di langkah lain
         * tidak diam-diam mengirim orang ke halaman yang salah.
         */
        bidangKurang() {
            const f = this.form;
            const kurang = [];

            if (!f.idDivisi) kurang.push({ label: 'Divisi', langkah: 1 });
            if (!f.idLevel) kurang.push({ label: 'Level HRIS', langkah: 1 });
            if (!f.idJabatan) kurang.push({ label: 'Jabatan', langkah: 1 });
            if (!f.jumlahRekrutmen || f.jumlahRekrutmen < 1) kurang.push({ label: 'Kuota target', langkah: 1 });
            if (!f.tanggalPeriode) kurang.push({ label: 'Tanggal periode target', langkah: 1 });
            if (!f.kodeLokasi) kurang.push({ label: 'Lokasi penempatan', langkah: 1 });
            if (!f.kodeKaryawan) kurang.push({ label: 'Penanggung jawab', langkah: 1 });

            return kurang;
        },
        /**
         * Isi borang dalam bentuk yang bisa dibaca manusia.
         *
         * Borangnya menyimpan id; pratinjau tidak boleh memperlihatkan id. Nilai
         * yang tidak ada di daftar opsi (benefit ketikan sendiri) dipakai apa
         * adanya — lebih baik menampilkan teks aslinya daripada tanda pisah.
         */
        ringkas() {
            const f = this.form;
            const cari = (opsi, v) => (opsi || []).find((o) => o.value === v)?.label || null;
            const daftar = (opsi, nilai) => (nilai || []).map((v) => cari(opsi, v) || v);

            return {
                jenis: f.jenisProgram === 'MT' ? 'Management Trainee (MT)' : 'Rekrutmen Reguler',
                divisi: cari(this.divisiOptions, f.idDivisi),
                departemen: cari(this.subDivisiOptions, f.idSubDivisi),
                level: cari(this.levelOptions, f.idLevel),
                jabatan: cari(this.jabatanOptions, f.idJabatan),
                kuota: f.jumlahRekrutmen,
                target: f.tanggalPeriode,
                lokasi: cari(this.lokasiOptions, f.kodeLokasi),
                pic: cari(this.karyawanOptions, f.kodeKaryawan),
                employment: cari(this.klasifikasi.employment, f.employmentType),
                workplace: cari(this.klasifikasi.workplace, f.workplaceType),
                experience: cari(this.klasifikasi.experience, f.experienceLevel),
                skills: daftar(this.skillOptions, f.skills),
                benefits: daftar(this.benefitOptions, f.benefits),
            };
        },
        /** Langkah 1 ditahan selama tenggatnya belum bisa dihitung. */
        langkah1Tertahan() {
            return this.modalTab === 1 && this.slaMenahan;
        },
        isFormValid() {
            // TANPA SLA, TANPA MPP.
            //
            // Tanggal periode target bukan lagi isian bebas: ia hasil hitungan
            // dari ketentuan level. Kalau ketentuannya belum ada, tidak ada
            // tenggat yang bisa dipertanggungjawabkan — dan MPP tanpa tenggat
            // adalah MPP yang tidak pernah telat, sebab tidak ada yang bisa
            // dilanggarnya.
            if (this.slaMenahan) return false;

            return !!(
                this.form.idDivisi &&
                this.form.idLevel &&
                this.form.idJabatan &&
                this.form.jumlahRekrutmen &&
                this.form.jumlahRekrutmen >= 1 &&
                this.form.tanggalPeriode &&
                this.form.kodeLokasi &&
                this.form.kodeKaryawan
            );
        },
        formFootNote() {
            if (this.saving) return 'Menyimpan transaksi MPP…';
            if (this.loadingDetail) return 'Memuat detail data MPP…';
            // MT lebih dulu: ketiga kalimat SLA di bawah tidak berlaku baginya,
            // dan menampilkannya hanya menyuruh orang membereskan aturan yang
            // tidak pernah dipakai program yang sedang ia buat.
            if (this.mtDipilih)
                return 'Program MT tidak terikat SLA level — periode targetnya mengikuti tanggal MPP ini dibuat.';
            if (this.slaMemuat) return 'Membaca ketentuan SLA level yang dipilih…';
            if (this.slaGagal && !this.editingNo)
                return 'Ketentuan SLA level ini gagal dibaca, jadi tanggal periode target belum bisa dihitung. Tekan “Periksa lagi” pada keterangan di Langkah 1.';
            if (this.slaKurang && !this.editingNo)
                return `Level ${this.namaLevelTerpilih} belum punya ketentuan SLA — tambahkan aturannya di Master SLA MPP dulu, sebab tanggal periode target dihitung dari sana.`;
            if (this.langkahAkhir && this.bidangKurang.length)
                return `${this.bidangKurang.length} bidang wajib masih kosong — tekan tandanya di ringkasan untuk melompat ke langkahnya.`;
            if (!this.langkahAkhir)
                return 'Isi bertahap — ringkasan lengkapnya muncul di Langkah 4 sebelum apa pun tersimpan.';

            return 'No Transaksi unik dibuat otomatis oleh sistem saat disimpan.';
        },
        // Kelompokkan opsi skill per N_WEB_CAREERS_Master_Skill_Kategori (ikon+warna).
        // skillOptions sudah terurut per Urutan kategori dari server, jadi urutan
        // Map di sini otomatis ikut urutan itu — "Tanpa Kategori" natural jadi
        // grup terakhir (server men-sort NULL kategori paling belakang juga).
        skillGroups() {
            const map = new Map();
            for (const s of this.skillOptions) {
                const key = s.kategori ? s.kategori.id : '__tanpa__';
                if (!map.has(key)) {
                    map.set(key, {
                        key,
                        label: s.kategori ? s.kategori.nama : 'Tanpa Kategori',
                        warna: s.kategori ? s.kategori.warna : '#94a3b8',
                        items: [],
                    });
                }
                map.get(key).items.push(s);
            }
            return [...map.values()];
        },
        skillCategories() {
            const cats = [];
            const seen = new Set();
            for (const s of this.skillOptions || []) {
                if (s.kategori && !seen.has(s.kategori.id)) {
                    seen.add(s.kategori.id);
                    cats.push({ id: s.kategori.id, nama: s.kategori.nama, warna: s.kategori.warna || '#6366f1' });
                }
            }
            return cats;
        },
        filteredSkillGroups() {
            const groups = this.skillGroups;
            if (!this.selectedSkillCategory || this.selectedSkillCategory === 'ALL') {
                return groups;
            }
            return groups.filter((g) => String(g.key) === String(this.selectedSkillCategory));
        },
    },
    watch: {
        /**
         * Level berganti → batas tanggalnya ikut berganti.
         *
         * Tanggal yang SUDAH terpilih tidak dihapus diam-diam kalau ternyata
         * melewati batas baru: yang dihapus tanpa pemberitahuan akan diisi ulang
         * dengan tanggal yang sama oleh orang yang tidak sadar apa-apa. Ia
         * dibiarkan terlihat, dan pintu simpan yang menolaknya dengan kalimat
         * yang menyebut sebabnya.
         */
        'form.idLevel': {
            handler(v, lama) {
                // Bedakan "borang baru dibuka" dari "admin mengganti level".
                // Yang kedua memang harus menghitung ulang tenggatnya.
                this.levelBerubah = lama !== undefined && lama !== null && v !== lama;
                this.muatSla(v);
            },
        },
        /**
         * Berpindah Rekrutmen <-> MT MENGUBAH ASAL TANGGALNYA.
         *
         * Rekrutmen mengambilnya dari ketentuan SLA level; MT dari tanggal MPP
         * dibuat. Tanpa pengamat ini, tanggal milik jenis yang LAMA akan tetap
         * tertinggal di borang setelah jenisnya diganti — dan pratinjau di
         * Langkah 4 memperlihatkan tanggal yang tidak akan pernah tersimpan.
         */
        'form.jenisProgram': {
            handler(v, lama) {
                if (lama === undefined || v === lama) return;

                // Bukan "level berubah" — tapi asal tanggalnya berubah, dan
                // itulah yang membuat muatSla() boleh menimpa isinya kembali.
                this.levelBerubah = true;
                this.muatSla(this.form.idLevel);
            },
        },
    },
    mounted() {
        this.load();
        this.loadOpsiTetap();
        this.loadOpsiFilter();
    },
    methods: {
        /**
         * Baris tabel: perlukah tombol Perpanjang?
         *
         * Syaratnya sama persis dengan kartu grid (lihat MasterMppCard) — tombol
         * yang muncul di satu tampilan tapi tidak di tampilan lain untuk MPP yang
         * sama membuat orang mengira salah satunya rusak.
         *
         * Hanya menyaring yang membuat tombolnya mustahil berguna: MT, yang
         * dibatalkan, yang sudah selesai, dan yang tidak punya angka SLA sama
         * sekali. Sisanya diputuskan server dan diterangkan di dalam modal.
         */
        bolehPerpanjang(m) {
            return m.jenisProgram !== 'MT' && m.status === 'AKTIF' && !m.selesai && !!m.sla?.hari;
        },
        /** Batas SLA level ini — kosong berarti level itu memang tidak dikunci. */
        async muatSla(idLevel) {
            this.sla = { hari: null, mulai: null, batas: null };
            this.slaGagal = false;

            // ── MT: PERIODENYA TANGGAL MPP DIBUAT ───────────────────────────
            //
            // Tidak ada permintaan SLA yang dikirim sama sekali — bukan dikirim
            // lalu hasilnya diabaikan. Level yang belum punya aturan tidak boleh
            // memunculkan kegagalan pada layar yang memang tidak memakainya.
            //
            // Pada MPP yang SEDANG DISUNTING tanggalnya dibiarkan: itu tanggal
            // pembuatan aslinya, dan menyunting deskripsi tidak boleh
            // memindahkannya ke hari ini. Server menjaga hal yang sama.
            if (this.mtDipilih) {
                this.form.tanggalPeriode = this.editingNo
                    ? this.mppDibuat || this.form.tanggalPeriode
                    : hariIni();
                this.levelBerubah = false;

                return;
            }

            if (!idLevel) return;

            this.slaMemuat = true;
            try {
                const res = await axios.get('/api/v1/master-sla-mpp/hitung', {
                    ...CFG,
                    params: { idLevel },
                });
                // Jenisnya bisa sudah berpindah ke MT selagi permintaan ini di
                // jalan. Jawaban yang datang terlambat untuk jenis yang sudah
                // ditinggalkan harus dibuang, bukan dipasang — kalau tidak, ia
                // menimpa tanggal MT dengan tenggat SLA beberapa saat setelah
                // layarnya terlihat benar.
                if (this.mtDipilih) return;

                const r = res.data.result || {};
                this.sla = { hari: r.hari, mulai: r.mulai, batas: r.batas };

                // TANGGALNYA DIISI SENDIRI, bukan diserahkan ke admin.
                //
                // Selama levelnya punya SLA, tanggal target bukan pilihan: ia
                // hasil hitungan "hari MPP dibuat + lama penyelesaian levelnya". Membiarkan
                // kolomnya kosong lalu menuntut admin menebak angka yang sudah
                // dihitung sistem adalah pekerjaan yang tidak menambah apa pun —
                // dan tebakan yang meleset akan ditolak pintu simpan.
                //
                // Pada MPP yang SEDANG DISUNTING, tanggal lamanya dibiarkan
                // selama levelnya tidak berganti: menyunting deskripsi tidak
                // boleh diam-diam memindahkan tenggat yang sudah disepakati.
                if (r.batas && (!this.editingNo || this.levelBerubah)) {
                    this.form.tanggalPeriode = r.batas;
                }
                this.levelBerubah = false;
            } catch (e) {
                // Ditandai, bukan didiamkan. Tanpa aturannya tanggal target memang
                // tidak bisa dihitung — borangnya tetap tertahan — tapi orang yang
                // membacanya harus tahu bahwa yang gagal itu PEMBACAANNYA, bukan
                // bahwa aturannya tidak ada.
                this.slaGagal = true;
                this.sla = { hari: null, mulai: null, batas: null };
            } finally {
                this.slaMemuat = false;
            }
        },
        /**
         * Satu tombol, dua peran: melangkah, lalu menyimpan.
         *
         * Peralihannya terjadi di langkah terakhir dan tidak pernah di tengah —
         * tombol yang kadang menyimpan dan kadang berpindah tanpa aturan yang bisa
         * dilihat adalah tombol yang ditekan dengan ragu-ragu.
         */
        aksiUtama() {
            if (this.aksiTerkunci) return;
            if (this.langkahAkhir) {
                this.save();

                return;
            }

            this.pergiKeLangkah(this.modalTab + 1);
        },
        /**
         * Pindah langkah — dari bilah atas, tombol kaki, maupun tautan pratinjau.
         *
         * Isi modal digulirkan kembali ke atas: tanpa itu, orang yang melompat dari
         * pratinjau ke "Divisi" mendarat di tengah halaman dengan bidang yang
         * dicarinya berada di luar layar, dan menyimpulkan tautannya tidak jalan.
         */
        pergiKeLangkah(no) {
            if (no > 1 && this.slaMenahan) return;

            this.modalTab = Math.min(Math.max(no, 1), LANGKAH_AKHIR);
            this.$nextTick(() => {
                document.querySelector('.wca-modal__body')?.scrollTo({ top: 0, behavior: 'smooth' });
            });
        },
        /**
         * Master SLA MPP dibuka di TAB BARU.
         *
         * Borang MPP ini biasanya sudah setengah terisi saat orang menyadari
         * aturannya belum ada; berpindah halaman akan membuang isian itu tanpa
         * peringatan. Tab baru menahannya tetap terbuka, dan "Periksa lagi"
         * membaca ulang aturannya begitu ia kembali.
         */
        bukaMasterSla() {
            window.open('/master-sla-mpp', '_blank', 'noopener');
        },
        /**
         * Dua tanggal jadi satu rentang yang bisa dibaca sekali lihat.
         *
         * Tahun ditulis sekali kalau keduanya setahun ("20 Agustus – 1 Oktober
         * 2026"), dan dua kali kalau menyeberang tahun — di situ tahunnya justru
         * bagian yang paling perlu terlihat.
         */
        rentangTanggal(mulai, akhir) {
            // Aturannya sama di kartu, panel detail, dan borang ini — jadi ia
            // tinggal di satu tempat. Yang berbeda cuma cadangannya: di borang,
            // tanggal tunggal ditulis lengkap dengan nama harinya.
            if (!cekRentang(mulai, akhir)) return this.tglPanjang(akhir);

            return rentangPanjang(mulai, akhir);
        },
        /** "2026-10-01" → "Kamis, 1 Oktober 2026". */
        tglPanjang(v) {
            if (!v) return '—';
            const d = new Date(`${v}T00:00:00`);

            return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleDateString('id-ID', { dateStyle: 'full' });
        },
        formatTanggal,
        initials,
        statusBadge,
        statusLabel,
        periodeLabel,
        jenisProgramLabel,
        /** Pilih satu MPP di panel kiri → muat detail + kandidatnya. */
        /**
         * Buka profil kandidat pengisi kursi.
         *
         * Barisnya dioper apa adanya supaya modal bisa langsung menampilkan
         * nama & kodenya sambil menunggu profil lengkapnya tiba — kepala modal
         * yang kosong beberapa detik terbaca seperti salah klik.
         */
        bukaKandidat(baris) {
            if (!baris?.id) return;

            // Nomor MPP-nya DIBEKUKAN saat modal dibuka, bukan dibaca terus
            // dari state. Tampilan split memakai `splitNo` sementara panel
            // lama memakai `selectedNo`; membaca salah satunya membuat modal
            // kosong di tampilan yang lain — persis kekeliruan yang membuat
            // modal ini sempat tidak memanggil apa pun.
            this.kandidatNo = this.splitNo || this.selectedNo || '';
            this.kandidatTarget = baris;
            this.kandidatShow = true;
        },

        async pilihSplit(no) {
            if (!no) return;

            this.splitNo = no;
            this.splitMobileDetail = true;
            this.splitMemuat = true;
            this.splitDetail = null;
            this.cq = '';
            this.cpage = 1;
            this.kandidat = [];
            this.kandidatTotal = 0;

            try {
                const res = await axios.get(`${API}/${encodeURIComponent(no)}`, CFG);

                // Pilihan bisa sudah berpindah selagi permintaan ini di jalan.
                // Jawaban untuk MPP yang sudah ditinggalkan dibuang, bukan
                // dipasang — kalau tidak, panel menampilkan MPP yang tidak
                // sedang disorot di daftar.
                if (this.splitNo !== no) return;

                this.splitDetail = res.data.result || null;
            } catch (e) {
                if (this.splitNo === no) this.notice(e.response?.data?.message || 'Gagal memuat detail MPP.');
            } finally {
                if (this.splitNo === no) this.splitMemuat = false;
            }

            this.muatKandidat();
        },
        /** Kandidat yang sudah DITERIMA pada MPP terpilih — dibaca dari Lamaran. */
        async muatKandidat() {
            const no = this.splitNo;
            if (!no) return;

            this.kandidatMemuat = true;
            try {
                const res = await axios.get(`${API}/${encodeURIComponent(no)}/kandidat`, {
                    ...CFG,
                    params: { page: this.cpage, per_page: 6, q: this.cq || undefined },
                });
                if (this.splitNo !== no) return;

                const r = res.data.result || {};
                this.kandidat = r.data || [];
                this.kandidatTotal = r.total || 0;
                this.kandidatHal = r.totalPages || 1;
            } catch (e) {
                if (this.splitNo === no) {
                    this.kandidat = [];
                    this.kandidatTotal = 0;
                    this.kandidatHal = 1;
                }
            } finally {
                if (this.splitNo === no) this.kandidatMemuat = false;
            }
        },
        /**
         * Muat ulang detail MPP yang sedang terbuka di panel split.
         *
         * Dipanggil sesudah setiap aksi yang mengubah keadaan MPP (selesai,
         * batalkan, perpanjang). Tanpa ini panel kanan tetap menampilkan
         * keadaan sebelum aksi — dan tombolnya menawarkan tindakan yang baru
         * saja dilakukan.
         */
        segarkanSplit() {
            if (!this.splitNo) return;

            const no = this.splitNo;
            axios
                .get(`${API}/${encodeURIComponent(no)}`, CFG)
                .then((res) => {
                    if (this.splitNo === no) this.splitDetail = res.data.result || this.splitDetail;
                })
                .catch(() => {});
        },
        /** Pindah halaman di panel kiri — daftarnya dipenggal server. */
        gantiHalSplit(n) {
            const h = Math.min(Math.max(1, n), this.totalPages);
            if (h === this.page) return;

            this.page = h;
            // Di ponsel tetap di panel daftar: yang barusan dilakukan orang
            // adalah BERPINDAH HALAMAN, dan isinya yang ingin ia lihat.
            this.splitMobileDetail = false;
            this.load();
        },
        /**
         * PIC terpilih berubah — dropdown mengirim larik utuh, bukan satuan.
         *
         * Kembali ke halaman 1: hasilnya daftar yang berbeda, dan bertahan di
         * halaman 5 dari daftar lama hampir selalu mendarat di halaman kosong.
         */
        gantiPic(daftar) {
            this.fPicSplit = Array.isArray(daftar) ? daftar : [];
            this.page = 1;
            this.load();
        },
        /** Jenis program — disaring di server (kolom Flag_MT). */
        gantiJenis(k) {
            this.fJenis = k === 'all' ? '' : k;
            this.page = 1;
            this.load();
        },
        /** Rentang tanggal periode. el-date-picker mengirim null saat dibersihkan. */
        gantiRentang(v) {
            this.rentangTgl = Array.isArray(v) && v.length === 2 ? v : null;
            this.page = 1;
            this.load();
        },
        /** Bersihkan SELURUH penyaring — yang di server maupun yang di panel kiri. */
        bersihkanSplit() {
            this.fIsiSplit = 'all';
            this.fSlaSplit = 'all';
            this.fPicSplit = [];
            this.rentangTgl = null;
            this.resetFilter();
        },
        async load() {
            this.loading = true;
            this.error = false;
            try {
                const res = await axios.get(API, {
                    ...CFG,
                    params: {
                        page: this.page,
                        per_page: this.perPage,
                        sort: this.sort,
                        dir: this.dir,
                        search: this.q || undefined,
                        status: this.fStatus || undefined,
                        selesai: this.fSelesai || undefined,
                        jenis: this.fJenis || undefined,
                        divisi: this.fDivisi || undefined,
                        periode: this.fPeriode || undefined,
                        employment: this.fEmployment || undefined,
                        workplace: this.fWorkplace || undefined,
                        experience: this.fExperience || undefined,
                        // Bertumpuk: dikirim sebagai daftar dipisah koma. Disaring
                        // DI SERVER, bukan di layar — kalau tidak, memilih satu PIC
                        // hanya menyembunyikan baris di halaman ini sementara MPP
                        // miliknya di halaman berikutnya tetap tak terlihat.
                        pic: this.fPicSplit.length ? this.fPicSplit.join(',') : undefined,
                        dari: this.rentangTgl?.[0] || undefined,
                        sampai: this.rentangTgl?.[1] || undefined,
                    },
                });
                this.list = res.data.result || [];
                this.totalData = res.data.total_data || 0;
                this.totalPages = res.data.total_page || 1;

                // SPLIT: pastikan selalu ada yang terpilih. Dua keadaan yang
                // ditangani sekaligus — belum pernah memilih, dan MPP yang
                // terpilih hilang dari daftar karena filternya berubah.
                {
                    const masih = this.daftarSplit.some((m) => m.noTransaksi === this.splitNo);
                    if (!masih && this.daftarSplit.length) {
                        this.pilihSplit(this.daftarSplit[0].noTransaksi);
                        // Di ponsel jangan langsung melompat ke detail: yang baru
                        // saja dilakukan orang adalah MENYARING daftar, dan
                        // hasilnyalah yang ingin ia lihat.
                        this.splitMobileDetail = false;
                    } else if (!this.daftarSplit.length) {
                        this.splitNo = null;
                        this.splitDetail = null;
                    }
                }
            } catch (e) {
                this.error = true;
            } finally {
                this.loading = false;
                this.ready = true;
            }
        },
        reload() {
            this.page = 1;
            this.load();
        },
        async loadOpsiTetap() {
            try {
                const [divisi, level, jabatan, lokasi, klas, skill, benefit] = await Promise.all([
                    axios.get(`${API}/opsi/divisi`, CFG),
                    axios.get(`${API}/opsi/level`, CFG),
                    axios.get(`${API}/opsi/jabatan`, CFG),
                    axios.get(`${API}/opsi/lokasi`, CFG),
                    axios.get(`${API}/opsi/klasifikasi`, CFG),
                    axios.get(`${API}/opsi/skill`, CFG),
                    axios.get(`${API}/opsi/benefit`, CFG),
                ]);
                this.divisiOptions = divisi.data.result || [];
                this.levelOptions = level.data.result || [];
                this.jabatanOptions = jabatan.data.result || [];
                this.lokasiOptions = lokasi.data.result || [];
                this.klasifikasi = klas.data.result || this.klasifikasi;
                this.skillOptions = skill.data.result || [];
                this.benefitOptions = benefit.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat opsi form.');
            }
        },
        async loadOpsiFilter() {
            try {
                const res = await axios.get(`${API}/opsi/filter`, CFG);
                this.periodeOptions = res.data.result?.periode || [];
                this.stats = res.data.result?.stats || this.stats;
                this.picOptions = res.data.result?.pic || [];
                this.ringkasKursi = res.data.result?.ringkas || this.ringkasKursi;
            } catch (e) {
                /* stat/filter opsional, tidak menghentikan halaman */
            }
        },
        async onDivisiChange(idDivisi, keepSub = false) {
            if (!keepSub) this.form.idSubDivisi = null;
            this.subDivisiOptions = [];
            if (!idDivisi) return;
            try {
                const res = await axios.get(`${API}/opsi/sub-divisi`, { ...CFG, params: { divisi: idDivisi } });
                this.subDivisiOptions = res.data.result || [];
            } catch (e) {
                /* opsi departemen opsional */
            }
        },
        async cariKaryawan(q) {
            this.karyawanLoading = true;
            try {
                const res = await axios.get(`${API}/opsi/karyawan`, { ...CFG, params: { q } });
                this.karyawanOptions = res.data.result || [];
            } catch (e) {
                this.karyawanOptions = [];
            } finally {
                this.karyawanLoading = false;
            }
        },
        onSearchInput() {
            if (this.searchTimer) clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.reload(), 350);
        },
        toggleChip(field, val) {
            this[field] = this[field] === val ? '' : val;
            this.reload();
        },
        toggleSort(kolom) {
            if (!SORTABLE.includes(kolom)) return;
            if (this.sort === kolom) {
                this.dir = this.dir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sort = kolom;
                this.dir = 'desc';
            }
            this.reload();
        },
        sortIcon(kolom) {
            if (this.sort !== kolom) return 'bi-arrow-down-up';
            return this.dir === 'asc' ? 'bi-sort-down-alt' : 'bi-sort-down';
        },
        changePage(p) {
            this.page = p;
            this.load();
        },
        resetFilter() {
            this.q = '';
            this.fStatus = '';
            this.fSelesai = '';
            this.fJenis = '';
            this.fDivisi = null;
            this.fPeriode = null;
            this.fEmployment = null;
            this.fWorkplace = null;
            this.fExperience = null;
            this.reload();
        },
        openDetail(no) {
            this.selectedNo = no;
        },
        openCreate() {
            this.editingNo = null;
            this.modalTab = 1;
            this.form = FORM_KOSONG();
            this.slaBeku = null;
            // Dikosongkan juga — sisa dari MPP sebelumnya akan memasang panel
            // "sudah diperpanjang" pada MPP yang tidak pernah diperpanjang.
            this.slaPanjang = null;
            this.mppDibuat = null;
            this.subDivisiOptions = [];
            this.karyawanOptions = [];
            this.show = true;
        },
        async openEdit(m) {
            // List/panel tidak membawa Tanggung Jawab/Persyaratan/Skill/Benefit
            // (sengaja — list() tetap ringan), jadi ambil detail lengkap dulu.
            this.editingNo = m.noTransaksi;
            this.modalTab = 1;
            this.form = FORM_KOSONG();
            this.slaBeku = null;
            // Dikosongkan juga — sisa dari MPP sebelumnya akan memasang panel
            // "sudah diperpanjang" pada MPP yang tidak pernah diperpanjang.
            this.slaPanjang = null;
            this.mppDibuat = null;
            this.subDivisiOptions = [];
            this.karyawanOptions = [];
            this.show = true;
            this.loadingDetail = true;
            try {
                const res = await axios.get(`${API}/${m.noTransaksi}`, CFG);
                const d = res.data.result;

                // Pre-populate option arrays to guarantee labels are available for select boxes
                if (d.divisi) this.mergeOpsi(this.divisiOptions, [{ id: d.divisi.id, nama: d.divisi.nama }]);
                if (d.level) this.mergeOpsi(this.levelOptions, [{ id: d.level.id, nama: d.level.nama }]);
                if (d.jabatan) this.mergeOpsi(this.jabatanOptions, [{ id: d.jabatan.id, nama: d.jabatan.nama }]);
                if (d.lokasi) this.mergeOpsi(this.lokasiOptions, [{ id: d.lokasi.kode, nama: d.lokasi.nama }]);

                if (d.divisi?.id) {
                    await this.onDivisiChange(d.divisi.id, true);
                }
                if (d.subDivisi) {
                    this.mergeOpsi(this.subDivisiOptions, [{ id: d.subDivisi.id, nama: d.subDivisi.nama }]);
                }

                this.form = {
                    jenisProgram: d.jenisProgram,
                    idDivisi: this.findValue(this.divisiOptions, d.divisi?.id),
                    idSubDivisi: this.findValue(this.subDivisiOptions, d.subDivisi?.id),
                    idLevel: this.findValue(this.levelOptions, d.level?.id),
                    idJabatan: this.findValue(this.jabatanOptions, d.jabatan?.id),
                    jumlahRekrutmen: d.jumlahRekrutmen,
                    tanggalPeriode: d.tanggalPeriode,
                    kodeLokasi: this.findValue(this.lokasiOptions, d.lokasi?.kode),
                    kodeKaryawan: d.penanggungJawab?.kode ?? null,
                    deskripsi: d.deskripsi || '',
                    employmentType: this.findValue(this.klasifikasi.employment, d.employmentType?.id),
                    workplaceType: this.findValue(this.klasifikasi.workplace, d.workplaceType?.id),
                    experienceLevel: this.findValue(this.klasifikasi.experience, d.experienceLevel?.id),
                    tanggungJawab: [...(d.tanggungJawab || [])],
                    persyaratan: [...(d.persyaratan || [])],
                    skills: (d.skill || []).map((s) => s.id),
                    benefits: (d.benefit || []).map((b) => b.id),
                };

                // Periode MPP ini dibaca dari barisnya sendiri, bukan dihitung ulang.
                this.slaBeku = d.sla ? { mulai: d.sla.mulai, hari: d.sla.hari } : null;
                // Keadaan perpanjangan MPP yang sedang disunting. Dipakai panel
                // beku di langkah 1 — dan HANYA untuk memberi tahu; tenggatnya
                // ditahan server, bukan oleh layar ini.
                this.slaPanjang = d.sla
                    ? {
                          ke: Number(d.sla.perpanjanganKe || 0),
                          batas: d.sla.batas || d.tanggalPeriode || null,
                          batasAwal: d.sla.batasAwal || null,
                      }
                    : null;
                // Dipasang SESUDAH form diisi: kalau jenisnya diubah menjadi MT di
                // tengah suntingan, inilah tanggal yang akan dipakai — sama dengan
                // yang ditulis server, bukan sisa tenggat SLA jenis sebelumnya.
                this.mppDibuat = d.tanggalDibuat || null;
                this.karyawanOptions = d.penanggungJawab?.kode
                    ? [
                          {
                              value: d.penanggungJawab.kode,
                              label: `${d.penanggungJawab.nama} (${d.penanggungJawab.kode})`,
                          },
                      ]
                    : [];
                this.mergeOpsi(this.skillOptions, d.skill);
                this.mergeOpsi(this.benefitOptions, d.benefit);
            } catch (e) {
                this.notice('Gagal memuat detail transaksi untuk diubah.');
                this.show = false;
            } finally {
                this.loadingDetail = false;
            }
        },
        findValue(options, val) {
            if (val === null || val === undefined) return null;
            const found = (options || []).find((o) => String(o.value) === String(val));
            return found ? found.value : val;
        },
        mergeOpsi(list, items) {
            for (const it of items || []) {
                if (!it) continue;
                const val = it.value !== undefined ? it.value : it.id !== undefined ? it.id : it.kode;
                const lbl = it.label !== undefined ? it.label : it.nama;
                if (val !== undefined && val !== null && !list.some((o) => String(o.value) === String(val))) {
                    list.push({ value: val, label: lbl, kategori: it.kategori || null });
                }
            }
        },
        async save() {
            if (this.saving || !this.isFormValid) return;
            this.saving = true;
            try {
                if (this.editingNo) {
                    await axios.put(`${API}/${this.editingNo}`, this.form, CFG);
                    this.notice('Transaksi MPP diperbarui.');
                } else {
                    await axios.post(API, this.form, CFG);
                    this.notice('Transaksi MPP dibuat.');
                }
                this.show = false;
                await this.load();
                await this.loadOpsiFilter();
                await this.refreshOpsiTag();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan transaksi MPP.');
            } finally {
                this.saving = false;
            }
        },
        // Skill/Benefit baru bisa muncul dari allow-create — segarkan daftarnya
        // supaya transaksi berikutnya melihat opsi terbaru tanpa reload halaman.
        async refreshOpsiTag() {
            try {
                const [skill, benefit] = await Promise.all([
                    axios.get(`${API}/opsi/skill`, CFG),
                    axios.get(`${API}/opsi/benefit`, CFG),
                ]);
                this.skillOptions = skill.data.result || this.skillOptions;
                this.benefitOptions = benefit.data.result || this.benefitOptions;
            } catch (e) {
                /* opsional, tidak menghentikan alur */
            }
        },
        async toggleSelesai(m, dariPanel = false) {
            const target = m.selesai;
            try {
                await axios.patch(`${API}/${m.noTransaksi}/selesai`, { selesai: !target }, CFG);
                this.notice(
                    !target ? `"${m.noTransaksi}" ditandai selesai.` : `"${m.noTransaksi}" ditandai belum selesai.`,
                );
                await this.load();
                await this.loadOpsiFilter();
                if (dariPanel) this.$refs.panelRef?.load();
                this.segarkanSplit();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengubah status selesai.');
            }
        },
        askBatalkan(m) {
            this.batalTarget = m;
            this.batalShow = true;
        },
        /**
         * Buka modal perpanjangan — SESUDAH menanyakan keadaannya ke server.
         *
         * Modalnya dibuka lebih dulu dengan keadaan kosong, baru diisi. Menunggu
         * jawaban sebelum membuka membuat tombol terasa mati beberapa saat, dan
         * yang menekannya menekan lagi.
         *
         * Keadaannya TIDAK disusun dari data kartu. Boleh/tidaknya ikut menimbang
         * hal-hal yang memang tidak ada di kartu — kursi yang sudah penuh, jatah
         * yang sudah habis — dan tanggal barunya harus dihitung dengan
         * penanggalan hari kerja yang sama dengan yang kelak menuliskannya.
         */
        async askPerpanjang(m) {
            this.panjangTarget = m;
            this.panjangKeadaan = null;
            this.panjangShow = true;

            try {
                const res = await axios.get(`${API}/${encodeURIComponent(m.noTransaksi)}`, CFG);
                this.panjangKeadaan = res.data.result?.perpanjangan || null;

                // Jawaban yang datang untuk MPP yang sudah ditinggalkan dibuang.
                // Tanpa ini, membuka lalu menutup lalu membuka MPP lain dengan
                // cepat bisa memasang keadaan MPP pertama pada modal MPP kedua.
                if (this.panjangTarget?.noTransaksi !== m.noTransaksi) return;
            } catch (e) {
                this.panjangShow = false;
                this.notice(e.response?.data?.message || 'Gagal memuat keadaan SLA MPP ini.');
            }
        },
        /** Kirim perpanjangannya. Alasannya sudah divalidasi panjangnya di modal. */
        async konfirmasiPerpanjang(alasan) {
            if (this.panjangBusy || !this.panjangTarget) return;
            this.panjangBusy = true;

            try {
                const res = await axios.post(
                    `${API}/${encodeURIComponent(this.panjangTarget.noTransaksi)}/perpanjang-sla`,
                    { alasan },
                    CFG,
                );

                this.panjangShow = false;
                this.panjangTarget = null;
                this.panjangKeadaan = null;

                // Pesannya datang dari server, bukan dikarang ulang di sini: ia
                // menyebut tenggat baru yang BENAR-BENAR tertulis, dan itu satu-
                // satunya angka yang layak dipakai untuk memberi tahu admin.
                this.notice(res.data.message || 'SLA diperpanjang.');

                await this.load();
                await this.loadOpsiFilter();
                this.$refs.panelRef?.load();
                this.segarkanSplit();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memperpanjang SLA.');
            } finally {
                this.panjangBusy = false;
            }
        },
        async confirmBatalkan() {
            if (this.batalBusy || !this.batalTarget) return;
            this.batalBusy = true;
            try {
                await axios.patch(`${API}/${this.batalTarget.noTransaksi}/batalkan`, { batalkan: true }, CFG);
                this.notice('Transaksi MPP dibatalkan.');
                this.batalShow = false;
                this.batalTarget = null;
                await this.load();
                await this.loadOpsiFilter();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal membatalkan transaksi.');
            } finally {
                this.batalBusy = false;
            }
        },
        async aktifkanKembali(m, dariPanel = false) {
            try {
                await axios.patch(`${API}/${m.noTransaksi}/batalkan`, { batalkan: false }, CFG);
                this.notice(`"${m.noTransaksi}" diaktifkan kembali.`);
                await this.load();
                await this.loadOpsiFilter();
                if (dariPanel) this.$refs.panelRef?.load();
                this.segarkanSplit();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengaktifkan kembali.');
            }
        },
        notice(m) {
            this.toast = m;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3000);
        },
    },
};
</script>

<style scoped>
/* Kartu punya popover "+N atribut" (position:absolute, tersembunyi via
   visibility:hidden) yang tetap dihitung dalam area scroll dokumen walau tak
   terlihat, dan bisa melebihi tepi grid untuk kartu di kolom pinggir. Tanpa
   penjagaan ini seluruh halaman jadi bisa di-scroll horizontal untuk
   mengakomodasi elemen yang sebenarnya tak terlihat itu — beda dengan
   .wc-page (landing publik) yang sudah punya proteksi serupa, .wca (dipakai
   semua halaman admin) belum. Dikunci di sini saja (scoped), bukan di
   evo-theme.css, supaya tidak mengubah perilaku halaman admin lain. */
.wca {
    overflow-x: hidden;
}

/* ── BILAH RINGKAS (tampilan split) ─────────────────────────────────────────
 *
 * Menggantikan kepala besar + 4 kartu statistik + barisan penyaring, yang
 * bersama-sama memakan separuh layar pertama. Di sini semuanya jadi SATU baris
 * setinggi ~64px, sehingga daftar MPP dan detailnya mulai tepat di bawah lipatan
 * pertama — dan itulah yang sebenarnya dicari orang di halaman ini.
 */
.mmp-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    padding: 13px 16px;
    margin-bottom: 12px;
    background: #fff;
    border: 1px solid #e7e3fb;
    border-radius: 16px;
    box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06);
}

/* Kerangka muat untuk tampilan split — dua panel, sepadan dengan .mms. */
.mmp-sksplit { display: grid; grid-template-columns: 340px 1fr; gap: 12px; }
.mmp-skpane {
    min-height: 420px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #fff;
    border: 1px solid #e7e3fb;
    border-radius: 16px;
}
.mmp-skpane--kanan { background: linear-gradient(135deg, #f8f7ff, #fff); }
@media (max-width: 900px) {
    .mmp-sksplit { grid-template-columns: 1fr; }
    .mmp-skpane--kanan { display: none; }
}

.mmp-bar__ico {
    width: 36px;
    height: 36px;
    border-radius: 11px;
    flex: none;
    display: grid;
    place-items: center;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28);
}

.mmp-bar__txt { flex: 1; min-width: 0; }
.mmp-bar__txt h1 { margin: 0; font-size: 1.2rem; font-weight: 800; color: #0f172a; letter-spacing: -0.025em; line-height: 1.15; }

.mmp-bar__meta { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-top: 4px; font-size: 0.72rem; }
.mmp-bar__meta > span { display: inline-flex; align-items: center; gap: 5px; }
.mmp-bar__meta b { font-weight: 800; color: #334155; }
.mmp-bar__meta > span { color: #94a3b8; }
.mmp-bar__dot { width: 7px; height: 7px; border-radius: 50%; flex: none; }

@media (max-width: 640px) {
    /* Ringkasan angka disembunyikan lebih dulu: ia keterangan, sementara judul
       dan tombol "MPP Baru" adalah hal yang dipakai. */
    .mmp-bar__meta { display: none; }
}

.mmp-toggle {
    display: inline-flex;
    gap: 0.25rem;
    border: 1px solid var(--line);
    border-radius: 0.7rem;
    padding: 0.2rem;
}
.mmp-toggle .wca-iconbtn.is-active {
    background: #eef0ff;
    color: #4338ca;
    border-color: #c7d2fe;
}

.mmp-tb {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.1rem;
}
.mmp-tb__srch {
    flex: 1 1 240px;
    min-width: 200px;
}
.mmp-spin {
    animation: mmp-spin 0.9s linear infinite;
}
@keyframes mmp-spin {
    to {
        transform: rotate(360deg);
    }
}

.mmp-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid var(--line);
    background: #fff;
    color: var(--muted);
    font-size: 0.8rem;
    font-weight: 800;
    padding: 0.5rem 0.9rem;
    border-radius: 0.7rem;
    cursor: pointer;
}
.mmp-chip.on {
    background: #eef0ff;
    border-color: #c7d2fe;
    color: #4338ca;
}
.mmp-div {
    width: 1px;
    height: 24px;
    background: var(--line);
    margin: 0 0.15rem;
}
.mmp-sel {
    width: 150px;
}

.mmp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    min-height: 120px;
    transition: opacity 0.15s ease;
}
.mmp-grid.mmp-busy {
    opacity: 0.55;
    pointer-events: none;
}

.mmp-skcard {
    height: 230px;
    border-radius: 1.15rem;
    background: linear-gradient(90deg, #f1f5f9 25%, #e9eef5 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: mmpShimmer 1.3s ease infinite;
}
.mmp-skrows {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 1rem;
}
.mmp-skrow {
    height: 2.6rem;
    border-radius: 0.6rem;
    background: linear-gradient(90deg, #f1f5f9 25%, #e9eef5 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: mmpShimmer 1.3s ease infinite;
}
@keyframes mmpShimmer {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: -100% 0;
    }
}

.mmp-table.mmp-busy,
.wca-card.mmp-busy {
    opacity: 0.55;
    pointer-events: none;
}
.mmp-no {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 800;
    font-size: 0.78rem;
    color: var(--muted);
}
.mmp-num {
    text-align: center;
}
.mmp-sortable {
    cursor: pointer;
    user-select: none;
}
.mmp-sortable i {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-left: 3px;
}
.mmp-tb-actions {
    display: flex;
    gap: 0.3rem;
    justify-content: flex-end;
}

.mmp-tb-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.2rem 0.55rem;
    border-radius: 0.5rem;
    white-space: nowrap;
}
.mmp-tb--emp {
    background: rgba(99, 102, 241, 0.1);
    color: #4338ca;
}
.mmp-tb--wp {
    background: rgba(16, 185, 129, 0.1);
    color: #0f766e;
}
.mmp-tb--mt {
    background: rgba(139, 92, 246, 0.1);
    color: #7c3aed;
}
.mmp-tb--rek {
    background: #f1f5f9;
    color: #475569;
}

.mmp-segjenis {
    display: inline-flex;
    gap: 0.3rem;
    padding: 0.25rem;
    border: 1px solid var(--line);
    border-radius: 0.75rem;
    background: #f8fafc;
}
.mmp-segjenis__it {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: none;
    background: transparent;
    font: inherit;
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--muted);
    padding: 0.5rem 0.9rem;
    border-radius: 0.55rem;
    cursor: pointer;
}
.mmp-segjenis__it.on {
    background: #fff;
    color: #7c3aed;
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.08);
}

.mmp-flag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 800;
    color: #94a3b8;
    white-space: nowrap;
}
.mmp-flag__dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background: #cbd5e1;
}
.mmp-flag.is-done {
    color: #15803d;
}
.mmp-flag.is-done .mmp-flag__dot {
    background: #22c55e;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.18);
}

.mmp-pagerow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin-top: 0.9rem;
}
.mmp-count {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--muted);
}

.mmp-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.mmp-selempty { padding: 0.6rem; font-size: 0.8rem; color: #94a3b8; text-align: center; }

.mmp-skopt {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}
.mmp-skopt__dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    flex: none;
}

/* Redesigned Modal Styling */
.mmp-modal-tabs {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    margin-bottom: 1.25rem;
    background: #f1f5f9;
    padding: 0.35rem;
    border-radius: 0.85rem;
    border: 1px solid #e2e8f0;
}

.mmp-tab-btn {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 0.8rem;
    border: none;
    background: transparent;
    border-radius: 0.65rem;
    cursor: pointer;
    text-align: left;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.mmp-tab-btn:hover {
    background: rgba(255, 255, 255, 0.6);
}

.mmp-tab-btn.is-active {
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}

.mmp-tab-num {
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 50%;
    background: #e2e8f0;
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 800;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.mmp-tab-btn.is-active .mmp-tab-num {
    background: #6366f1;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
}

/* Langkah yang sudah dilewati: hijau, bukan abu. Bilah ini satu-satunya cara
   mundur sekarang, jadi ia harus memperlihatkan sudah sampai mana. */
.mmp-tab-btn.is-tuntas .mmp-tab-num {
    background: #d1fae5;
    color: #047857;
}
.mmp-tab-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.mmp-tab-btn:disabled:hover {
    background: transparent;
}
@media (max-width: 900px) {
    .mmp-modal-tabs {
        grid-template-columns: repeat(2, 1fr);
    }
}

.mmp-tab-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.mmp-tab-text strong {
    font-size: 0.82rem;
    font-weight: 800;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mmp-tab-btn.is-active .mmp-tab-text strong {
    color: #4338ca;
}

.mmp-tab-text small {
    font-size: 0.68rem;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mmp-tab-pane {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.mmp-form-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    padding: 1.1rem;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.02);
}

.mmp-form-card__head {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.mmp-form-card__head i {
    font-size: 1.25rem;
    color: #6366f1;
    background: #eef2ff;
    width: 2.3rem;
    height: 2.3rem;
    border-radius: 0.6rem;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.mmp-form-card__head h4 {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 800;
    color: #0f172a;
}

.mmp-form-card__head p {
    margin: 0.15rem 0 0;
    font-size: 0.75rem;
    color: #64748b;
}

.mmp-seg-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.mmp-seg-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 0.75rem;
    background: #f8fafc;
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
}

.mmp-seg-card:hover {
    border-color: #c7d2fe;
    background: #ffffff;
}

.mmp-seg-card.is-active {
    border-color: #6366f1;
    background: #ffffff;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.12);
}

.mmp-seg-card--mt.is-active {
    border-color: #8b5cf6;
    box-shadow: 0 4px 14px rgba(139, 92, 246, 0.12);
}

.mmp-seg-card__icon {
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 0.6rem;
    background: #e2e8f0;
    color: #64748b;
    font-size: 1.1rem;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.mmp-seg-card.is-active .mmp-seg-card__icon {
    background: #eef2ff;
    color: #4338ca;
}

.mmp-seg-card--mt.is-active .mmp-seg-card__icon {
    background: #f5f3ff;
    color: #6d28d9;
}

.mmp-seg-card__body {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.mmp-seg-card__body strong {
    font-size: 0.86rem;
    font-weight: 800;
    color: #1e293b;
}

.mmp-seg-card__body span {
    font-size: 0.72rem;
    color: #64748b;
}

.mmp-seg-card__check {
    font-size: 1.1rem;
    color: #cbd5e1;
    transition: color 0.2s ease;
}

.mmp-seg-card.is-active .mmp-seg-card__check {
    color: #6366f1;
}

.mmp-seg-card--mt.is-active .mmp-seg-card__check {
    color: #8b5cf6;
}

/* Tinggal KUOTA di sini — tanggal periode target pindah ke bawah level HRIS,
   tempat angkanya sebenarnya ditentukan. Satu kolom penuh: kotak setengah
   lebar menyisakan ruang kosong yang tampak seperti kolom kedua yang gagal
   dimuat — persis kesan yang ditinggalkan kolom tanggal sebelum dibuang. */
.mmp-target-box {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    padding: 0.85rem 1rem;
    background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
    border: 1px solid #c7d2fe;
    border-radius: 0.75rem;
    margin-bottom: 0.75rem;
}

.mmp-req {
    color: #ef4444;
    font-weight: 800;
}

/* .mmp-tab-foot & .wca-btn--indigo DIBUANG.

   Yang pertama bilah tombol kedua di dalam badan modal; yang kedua warna
   ungu buatan halaman ini sendiri (#4338ca rata) yang tidak ada di palet
   tema — satu-satunya tombol di seluruh modal yang tidak memakai gradien
   EVO. Keduanya hilang bersamaan saat tombolnya pindah ke kaki modal. */

.mmp-skopt {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.mmp-skopt__dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    flex-shrink: 0;
}

.mmp-sk-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
}

.mmp-sk-label-row .wca-field-lbl {
    margin-bottom: 0;
}

.mmp-cat-filter-inline {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    flex: none;
}

.mmp-cat-filter-lbl {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    /* "Filter Kategori:" sempat patah jadi dua baris di sebelah kotak yang
       sudah sempit — dua hal kecil yang saling memperburuk. */
    white-space: nowrap;
}

/* Kotaknya dulu el-select "small" selebar 180px di dalam kolom setengah
   lebar, dan yang tersisa untuk teksnya cuma "S…". Sekarang tingginya sama
   dengan kolom isian lain di kartu ini, dan lebarnya cukup untuk nama
   kategori terpanjang. */
.mmp-cat-filter__sel {
    width: 260px;
}
@media (max-width: 700px) {
    /* Layar sempit: penyaring turun ke barisnya sendiri, selebar kartu —
       berdesakan dengan judul di satu baris cuma mengulang masalah lama. */
    .mmp-cat-filter-inline {
        width: 100%;
    }
    .mmp-cat-filter__sel {
        width: auto;
        flex: 1 1 auto;
    }
}

@media (max-width: 768px) {
    .mmp-modal-tabs {
        grid-template-columns: 1fr;
    }
    .mmp-seg-grid,
    .mmp-target-box {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .mmp-sel {
        width: 100%;
    }
}

/* Pratinjau hitungan SLA: dibuat + lama penyelesaian = target. */
.mmp-slabox {
    margin-top: 8px; padding: 10px 12px;
    border: 1px solid #c7d2fe; border-radius: 12px;
    background: linear-gradient(180deg, #f8faff, #fff);
}
/* Tanggal yang sudah ditentukan — TAMPILAN, bukan kolom isian. */
.mmp-tgl {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 12px; border-radius: 10px;
    border: 1px dashed #c7d2fe; background: #f8faff;
}
.mmp-tgl > i { flex: none; color: #6366f1; font-size: 15px; }
.mmp-tgl__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.mmp-tgl__txt small { font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #818cf8; }
.mmp-tgl b { font-size: 14px; line-height: 1.35; color: #1e293b; overflow-wrap: anywhere; }
/* Baris kedua: angka SLA & tenggatnya, kecil — rentang di atasnya yang dibaca
   duluan, ini keterangan bagi yang butuh tanggal pastinya. */
.mmp-tgl__txt em { font-style: normal; font-size: 10.5px; line-height: 1.5; color: #64748b; }
.mmp-tgl__lock { flex: none; color: #a5b4fc; font-size: 11px; }

/* LEVEL BELUM PUNYA ATURAN — jalan buntu, tapi jalan keluarnya ikut ditulis. */
.mmp-slawrap { margin-top: 2px; }
.mmp-slanull {
    padding: 11px 13px; border-radius: 12px;
    border: 1px solid #fde68a; background: linear-gradient(180deg, #fffbeb, #fff);
}
.mmp-slanull__head { display: flex; gap: 9px; align-items: flex-start; }
.mmp-slanull__head > i { flex: none; margin-top: 1px; color: #d97706; font-size: 15px; }
.mmp-slanull__head > div { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.mmp-slanull__head b { font-size: 12.5px; color: #92400e; }
.mmp-slanull__head span { font-size: 11.5px; line-height: 1.6; color: #78716c; }
.mmp-slanull__head span b { font-size: inherit; color: #92400e; }
/* MPP LAMA: memberi tahu, tidak menahan — jadi warnanya tidak ikut menuduh. */
.mmp-slanull.is-longgar { border-color: #e2e8f0; background: linear-gradient(180deg, #f8fafc, #fff); }
.mmp-slanull.is-longgar .mmp-slanull__head > i { color: #94a3b8; }
.mmp-slanull.is-longgar .mmp-slanull__head b,
.mmp-slanull.is-longgar .mmp-slanull__head span b { color: #475569; }
.mmp-slanull.is-longgar .mmp-slanull__head span { color: #64748b; }
/* SUDAH DIPERPANJANG: ungu, sewarna dengan lencana & tombol perpanjang di
   kartu dan panel detail. Bukan kuning — ini bukan peringatan, melainkan
   keterangan tentang kenapa tenggatnya tidak ikut bergerak di layar ini. */
.mmp-slanull.is-panjang { border-color: #ddd6fe; background: linear-gradient(180deg, #faf9ff, #fff); }
.mmp-slanull.is-panjang .mmp-slanull__head > i { color: #7c3aed; }
.mmp-slanull.is-panjang .mmp-slanull__head b,
.mmp-slanull.is-panjang .mmp-slanull__head span b { color: #6d28d9; }
.mmp-slanull.is-panjang .mmp-slanull__head span { color: #64748b; }

.mmp-slanull__act { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 11px; }
.mmp-slanull__act .wca-btn { flex: 0 0 auto; height: 32px; border-radius: 9px; }
@media (max-width: 520px) {
    .mmp-slanull__act .wca-btn { flex: 1 1 100%; justify-content: center; }
}

/* ══ HALAMAN PRATINJAU (LANGKAH 4) ══ */
.mmp-rev { display: flex; flex-direction: column; gap: 0.85rem; }

.mmp-rev__hero {
    padding: 1.1rem 1.2rem;
    border-radius: 0.9rem;
    border: 1px solid #c7d2fe;
    background: linear-gradient(135deg, #eef2ff 0%, #faf5ff 55%, #fff 100%);
}
.mmp-rev__tag {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.24rem 0.6rem; border-radius: 999px;
    background: #e0e7ff; color: #4338ca;
    font-size: 0.68rem; font-weight: 800; letter-spacing: 0.03em; text-transform: uppercase;
}
.mmp-rev__tag.is-mt { background: #ede9fe; color: #6d28d9; }
.mmp-rev__hero h3 {
    margin: 0.55rem 0 0.15rem; font-size: 1.12rem; font-weight: 800; color: #1e1b4b;
    line-height: 1.3; overflow-wrap: anywhere;
}
.mmp-rev__hero > p { margin: 0; font-size: 0.8rem; color: #64748b; }

.mmp-rev__angka {
    display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.6rem;
    margin-top: 0.9rem; padding-top: 0.85rem; border-top: 1px dashed #c7d2fe;
}
.mmp-rev__angka small { display: block; font-size: 0.63rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; color: #94a3b8; }
.mmp-rev__angka b { display: block; margin-top: 0.15rem; font-size: 0.86rem; color: #312e81; overflow-wrap: anywhere; }
@media (max-width: 620px) {
    .mmp-rev__angka { grid-template-columns: 1fr; }
}

/* Kelengkapan: yang kurang disebut namanya, dan namanya bisa ditekan. */
.mmp-rev__kurang, .mmp-rev__siap {
    display: flex; gap: 0.6rem; align-items: flex-start;
    padding: 0.75rem 0.9rem; border-radius: 0.75rem; font-size: 0.78rem; line-height: 1.55;
}
.mmp-rev__kurang { border: 1px solid #fde68a; background: #fffbeb; color: #78716c; }
.mmp-rev__kurang > i { color: #d97706; font-size: 0.95rem; }
.mmp-rev__kurang b { color: #92400e; }
.mmp-rev__kurang > div { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.mmp-rev__siap { border: 1px solid #a7f3d0; background: #ecfdf5; color: #047857; }
.mmp-rev__siap > i { color: #059669; font-size: 0.95rem; }
.mmp-rev__siap b { color: #065f46; }

.mmp-rev__chips { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.4rem; }
.mmp-rev__chip {
    display: inline-flex; align-items: center; gap: 0.1rem;
    padding: 0.22rem 0.55rem; border-radius: 999px; cursor: pointer;
    border: 1px solid #fcd34d; background: #fff; color: #92400e;
    font-size: 0.7rem; font-weight: 700;
}
.mmp-rev__chip:hover { background: #fef3c7; }

.mmp-rev__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem; }
.mmp-rev__grid2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
@media (max-width: 860px) {
    .mmp-rev__grid, .mmp-rev__grid2 { grid-template-columns: 1fr; }
}

.mmp-rev__card {
    padding: 0.85rem 0.95rem; border-radius: 0.8rem;
    border: 1px solid #e2e8f0; background: #fff;
}
.mmp-rev__cardhead {
    display: flex; align-items: center; gap: 0.45rem;
    padding-bottom: 0.55rem; margin-bottom: 0.55rem; border-bottom: 1px solid #f1f5f9;
}
.mmp-rev__cardhead > i { color: #6366f1; font-size: 0.85rem; }
.mmp-rev__cardhead h5 { margin: 0; font-size: 0.78rem; font-weight: 800; color: #334155; }
.mmp-rev__hitung {
    padding: 0.05rem 0.4rem; border-radius: 999px;
    background: #eef2ff; color: #4338ca; font-size: 0.66rem; font-weight: 800;
}
/* "Ubah" sengaja tautan, bukan tombol: di kaki modal sudah ada tombol yang
   melangkah, dan yang ini cuma pintasan — bentuknya tidak boleh bersaing. */
.mmp-rev__ubah {
    margin-left: auto; padding: 0; border: none; background: none; cursor: pointer;
    font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-decoration: underline;
    text-underline-offset: 2px;
}
.mmp-rev__ubah:hover { color: #4338ca; }

.mmp-rev__row {
    display: flex; align-items: baseline; gap: 0.6rem;
    padding: 0.28rem 0; font-size: 0.78rem;
}
.mmp-rev__row span { flex: none; width: 7.5rem; color: #94a3b8; }
.mmp-rev__row b { min-width: 0; color: #334155; overflow-wrap: anywhere; }
@media (max-width: 480px) {
    .mmp-rev__row { flex-direction: column; gap: 0.1rem; }
    .mmp-rev__row span { width: auto; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.03em; }
}

.mmp-rev__teks { margin: 0; font-size: 0.78rem; line-height: 1.65; color: #475569; white-space: pre-line; }.mmp-rev__list { margin: 0; padding-left: 1.05rem; display: flex; flex-direction: column; gap: 0.25rem; }
.mmp-rev__list li { font-size: 0.78rem; line-height: 1.55; color: #475569; }
.mmp-rev__tags { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.mmp-rev__tag2 {
    padding: 0.2rem 0.55rem; border-radius: 0.5rem;
    background: #eef2ff; color: #4338ca; font-size: 0.72rem; font-weight: 700;
}
.mmp-rev__tag2.is-benefit { background: #ecfdf5; color: #047857; }

/* TOMBOL LANGKAH DI KAKI MODAL.
   "Lanjut" sengaja ungu lembut, bukan gradien penuh: di kaki yang sama sudah
   ada satu tombol gradien, dan dua gradien berdampingan membuat keduanya
   sama-sama tampak seperti tombol utama. Yang menulis data cuma satu. */
.wca-modal__foot .wca-btn--soft {
    border-radius: 13px;
    font-weight: 800;
    background: rgba(99, 102, 241, 0.12);
    color: #4338ca;
}
.wca-modal__foot .wca-btn--soft:hover {
    background: rgba(99, 102, 241, 0.2);
}
.wca-modal__foot .wca-btn--soft:disabled {
    background: #f1f5f9;
    color: #a3adc2;
}

.mmp-slabox__head {
    display: flex; gap: 7px; align-items: flex-start;
    font-size: 12px; line-height: 1.55; color: #334155;
}
.mmp-slabox__head i { flex: none; margin-top: 2px; color: #6366f1; }

/* Tabel tiga sel (MPP dibuat + lama penyelesaian → tanggal target) DIBUANG.
   Isinya sekarang satu kalimat di kepala kotak, dan tanggalnya sudah tertulis
   utuh sebagai rentang di atas — tabel itu cuma mengulanginya sekali lagi,
   dengan label ketiga yang justru bikin orang bertanya "yang mana tanggal
   targetnya?". */
.mmp-slabox__note {
    display: flex; gap: 6px; align-items: flex-start;
    margin: 10px 0 0; padding-top: 9px; border-top: 1px dashed #e0e7ff;
    font-size: 10.5px; line-height: 1.6; color: #64748b;
}
.mmp-slabox__note b { color: #475569; }
.mmp-slabox__note i { flex: none; color: #6366f1; }

/* Keterangan batas SLA di bawah pemilih tanggal. */
.mmp-sla {
    display: flex;
    gap: 7px;
    margin: 7px 0 0;
    font-size: 11.5px;
    line-height: 1.5;
    color: #4338ca;
}
.mmp-sla i {
    flex: none;
}
.mmp-sla.is-muat {
    color: #94a3b8;
}
</style>
