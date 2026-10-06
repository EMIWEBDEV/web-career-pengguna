<!-- WEB CAREER — Master Tahapan Seleksi / Alur (induk-detail: Alur + Tahap/Stages). DATA dari DB via /api/v1/master-alur. -->
<template>
    <Head title="Master Tahapan Seleksi" />
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico"><i class="bi bi-signpost-split"></i></span>
                    <h1>Master Tahapan Seleksi</h1>
                </div>
                <p>Susun urutan tahap seleksi per kategori — tes, mode keputusan, dan pengumuman diatur di tiap tahap.</p>
            </div>
            <button class="pkg-newbtn" @click="openCreate"><i class="bi bi-plus-lg"></i> Alur Baru</button>
        </div>

        <!-- FILTER PANEL — semua saringan dikirim ke backend (bukan disaring di browser). -->
        <div v-if="sheetOpen" class="alr-sheetbg" @click="sheetOpen = false"></div>
        <div class="alr-filter" :class="{ 'is-open': sheetOpen }">
            <div class="alr-filter__head">
                <span class="alr-filter__title"><i class="bi bi-funnel"></i> Filter Panel</span>
                <div class="alr-filter__act">
                    <button v-if="adaFilter" class="alr-filter__reset" type="button" @click="resetFilter"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    <button class="alr-filter__close" type="button" aria-label="Tutup filter" @click="sheetOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="alr-filter__grid">
                <div>
                    <label class="wca-field-lbl">Cari</label>
                    <el-input v-model="filters.q" placeholder="Nama / kode / deskripsi" clearable @input="cariDebounce">
                        <template #prefix><i class="bi bi-search"></i></template>
                    </el-input>
                </div>
                <!-- Penyaring kategori hanya berarti bila ADA yang bisa disaring.
                     Pengguna yang dijatah satu kategori melihat kotak berisi satu
                     pilihan yang sudah pasti terpilih — kendali yang tidak bisa
                     mengubah apa pun, dan menyisakan pertanyaan apa gunanya. -->
                <div v-if="kategoriOpsi.length > 1">
                    <label class="wca-field-lbl">Kategori</label>
                    <el-select v-model="filters.kategori" placeholder="Semua kategori" clearable filterable style="width:100%">
                        <el-option v-for="k in kategoriOpsi" :key="k.kode" :label="k.label" :value="k.kode" />
                    </el-select>
                </div>
                <div>
                    <label class="wca-field-lbl">Status</label>
                    <el-select v-model="filters.status" placeholder="Semua status" clearable style="width:100%" @change="load">
                        <el-option label="Aktif" value="AKTIF" />
                        <el-option label="Nonaktif" value="NONAKTIF" />
                    </el-select>
                </div>
                <div>
                    <label class="wca-field-lbl">Tanggal Dibuat</label>
                    <el-date-picker
                        v-model="filters.rentang" type="daterange" value-format="YYYY-MM-DD"
                        start-placeholder="Dari" end-placeholder="Sampai" range-separator="—"
                        style="width:100%" @change="load"
                    />
                </div>
            </div>
        </div>

        <!-- FAB filter (mobile) -->
        <button class="alr-fab" type="button" aria-label="Buka filter" @click="sheetOpen = true">
            <i class="bi bi-funnel-fill"></i>
            <span v-if="jumlahFilter" class="alr-fab__badge">{{ jumlahFilter }}</span>
        </button>

        <div v-loading="loading" class="pkg-list">
            <div v-for="a in list" :key="a.id" class="pkg-card" :class="{ open: open === a.id }">
                <!-- header row (pola standar "Program Kegiatan") -->
                <div class="pkg-row">
                    <button type="button" class="pkg-chev" :class="{ open: open === a.id }" title="Buka detail" @click="open = (open === a.id ? null : a.id)"><i class="bi bi-chevron-right"></i></button>
                    <div class="pkg-row__main">
                        <button type="button" class="pkg-row__titlebtn" @click="open = (open === a.id ? null : a.id)">
                            <span class="pkg-row__title">{{ a.nama }}</span>
                        </button>
                        <div class="pkg-row__meta">
                            <span class="pkg-code">{{ a.kode }}</span>
                            <template v-if="a.deskripsi"><span class="pkg-sep"></span><span class="pkg-mi">{{ a.deskripsi }}</span></template>
                        </div>
                        <div class="pkg-pills">
                            <span class="pkg-pill pkg-pill--violet"><i class="bi bi-tags"></i> {{ katLabel(a.kategori) }}</span>
                            <span class="pkg-pill pkg-pill--struct"><i class="bi bi-list-ol"></i> {{ a.stages.length }} tahap</span>
                            <span class="pkg-pill" :class="a.status === 'AKTIF' ? 'pkg-pill--green' : 'pkg-pill--slate'"><span class="pkg-pill__dot"></span> {{ a.status }}</span>
                        </div>
                    </div>
                    <div class="pkg-row__act" @click.stop>
                        <el-switch :model-value="a.status === 'AKTIF'" @change="(v) => setStatus(a, v)" />
                        <button class="pkg-ibtn" title="Ubah" @click="openEdit(a)"><i class="bi bi-pencil"></i></button>
                        <!-- DUPLIKAT — menyusun ulang alur 8 tahap dari nol untuk
                             angkatan berikutnya adalah pekerjaan setengah jam yang
                             hasilnya nyaris sama persis, dan satu setelan yang
                             terlewat tidak menimbulkan galat apa pun. -->
                        <button class="pkg-ibtn" :title="`Duplikat alur &quot;${a.nama}&quot;`" @click="openDuplicate(a)"><i class="bi bi-files"></i></button>
                        <button class="pkg-ibtn pkg-ibtn--danger" title="Hapus" @click="askRemove(a)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <!-- creator strip -->
                <div class="pkg-creator">
                    <span class="pkg-creator__av" style="background:#6366f1">{{ initials(a.createdBy) }}</span>
                    <span class="pkg-creator__name">{{ a.createdBy || 'Sistem' }}</span>
                    <span class="pkg-creator__at"><i class="bi bi-clock"></i> {{ a.createdAt || '—' }}</span>
                </div>

                <!-- expanded detail -->
                <div v-if="open === a.id" class="pkg-detail">
                    <div class="pkg-dhead pkg-dhead--indigo"><i class="bi bi-list-ol"></i> TAHAPAN SELEKSI ({{ a.stages.length }})</div>
                        <ol class="wca-flow">
                            <li v-for="(s, i) in a.stages" :key="i" class="wca-flow__step" :class="{ 'is-system': s.keputusan === 'SYSTEM' }">
                                <span class="wca-flow__num">{{ i + 1 }}</span>
                                <div class="wca-flow__card">
                                    <div class="wca-flow__top">
                                        <strong>{{ s.label }}</strong>
                                        <span class="wca-badge" :class="s.provider === 'THIRD_PARTY' ? 'wca-b--amber' : 'wca-b--indigo'">
                                            <i class="bi" :class="s.provider === 'THIRD_PARTY' ? 'bi-robot' : 'bi-person-workspace'"></i>
                                            {{ s.provider === 'THIRD_PARTY' ? 'Pihak ke-3' : 'Internal' }}
                                        </span>
                                    </div>
                                    <div class="wca-flow__meta">
                                        <span><i class="bi bi-tag"></i> {{ s.tipe }}</span>
                                        <span v-if="s.formulirId"><i class="bi bi-input-cursor-text"></i> {{ s.formulirId }}</span>
                                        <span class="alr-pill is-mode"><i class="bi bi-diagram-3"></i> {{ namaMode(s.mode) }}</span>
                                        <span class="wca-flow__dec" :class="s.keputusan === 'SYSTEM' ? 'sys' : 'man'">
                                            <i class="bi" :class="s.keputusan === 'SYSTEM' ? 'bi-cpu' : 'bi-hand-index-thumb'"></i>
                                            {{ s.keputusan === 'SYSTEM' ? 'Konfirmasi Sistem' : 'Keputusan Admin' }}
                                        </span>
                                        <span class="alr-pill" :class="`is-${(s.pengumuman || 'OTOMATIS').toLowerCase()}`">
                                            <i class="bi" :class="ikonPengumuman(s.pengumuman)"></i>
                                            {{ labelPengumuman(s.pengumuman) }}<template v-if="modeButuhJeda(s.pengumuman) && s.jedaHari != null"> +{{ s.jedaHari }} hr</template>
                                        </span>
                                        <span v-if="s.notifikasi === false" class="alr-pill is-mute" title="Kandidat tidak dikirimi notifikasi saat hasil terbit">
                                            <i class="bi bi-bell-slash"></i> Tanpa notifikasi
                                        </span>
                                        <span v-if="s.tuntas" class="alr-pill is-tuntas" title="Tahap ini menutup proses — kandidat dinyatakan diterima di sini">
                                            <i class="bi bi-flag-fill"></i> Titik Tuntas
                                        </span>
                                        <span v-if="s.talentPool" class="alr-pill is-talent" title="Kandidat tak lolos di tahap ini bisa dialihkan ke Talent Pool">
                                            <i class="bi bi-stars"></i> Cut-off Talent Pool
                                        </span>
                                    </div>
                                    <!-- Sub-tes tahap: inilah yang dinilai mesin keputusan. -->
                                    <ul v-if="(s.tests || []).length" class="alr-subtes">
                                        <li v-for="(t, k) in s.tests" :key="k" :class="t.peran === 'INFORMATIF' ? 'is-info' : 'is-penentu'">
                                            <i class="bi" :class="tesOnline(t.tipe || s.tipe) ? 'bi-robot' : 'bi-person-workspace'"></i>
                                            <b>{{ t.label }}</b>
                                            <span class="alr-subtes__tes">{{ namaTipe(t.tipe || s.tipe) }}</span>
                                            <span class="alr-subtes__peran">{{ t.peran === 'INFORMATIF' ? 'informatif' : 'penentu' }}</span>
                                            <span v-if="!t.wajib" class="alr-subtes__opt">opsional</span>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                            <li v-if="!a.stages.length" class="alr-empty-stage">Belum ada tahap.</li>
                        </ol>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="pkg-empty"><i class="bi bi-signpost-split"></i> {{ adaFilter ? 'Tidak ada alur yang cocok dengan filter.' : 'Belum ada alur.' }}</div>
        </div>

        <!-- Modal buat/ubah/duplikat alur — builder tahapan -->
        <AdminModal :busy="saving" :show="show" :title="judulModal" subtitle="Identitas alur & susunan tahapan." icon="bi-signpost-split" lg :save-label="labelSimpan" @close="tutupModal" @save="save">
            <!-- Asal salinan DIKATAKAN, bukan disimpulkan dari nama yang
                 kebetulan berakhiran "(Salinan)". Setelah beberapa suntingan,
                 layar ini tidak lagi terbedakan dari modal Ubah — dan menekan
                 Simpan sambil mengira sedang memperbarui alur lama justru
                 melahirkan alur kembar yang keduanya terpakai. -->
            <div v-if="duplikatDari" class="alr-dupnote">
                <i class="bi bi-files"></i>
                <div>
                    <b>Salinan dari “{{ duplikatDari }}”.</b>
                    Seluruh tahap &amp; aktivitasnya sudah disalin ke bawah — silakan ubah, tambah, atau hapus seperlunya.
                    Menyimpan akan membuat <b>alur baru</b>; alur asalnya tidak tersentuh.
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-signpost-split"></i> Detail Alur</div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Untuk Kategori (kelompok)</label>
                        <el-select v-if="!kategoriTunggal" v-model="form.kategori" placeholder="Pilih kelompok" filterable style="width:100%">
                            <el-option v-for="k in kategoriOpsi" :key="k.kode" :label="k.label" :value="k.kode" />
                        </el-select>

                        <!-- Satu-satunya kategori yang boleh: tidak ada yang perlu
                             dipilih. Nilainya sudah dipasang saat borang dibuka;
                             yang tersisa cuma keterangan agar admin tahu alur ini
                             akan muncul di mana. -->
                        <div v-else class="alr-kat-tetap">
                            <span class="pkg-pill pkg-pill--violet"><i class="bi bi-tags"></i> {{ kategoriTunggal.label }}</span>
                            <small><i class="bi bi-lock-fill"></i> satu-satunya kategori yang menjadi hak akses Anda</small>
                        </div>

                        <small v-if="!kategoriTunggal" style="display:block;margin-top:.3rem;color:var(--muted);font-weight:700;font-size:.72rem">Menentukan alur ini muncul untuk kategori mana saat buat program.</small>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Alur</label><el-input v-model="form.nama" placeholder="mis. Alur Rekrutmen Ekspres" /></div>
                        <div><label class="wca-field-lbl">Deskripsi</label><el-input v-model="form.deskripsi" placeholder="Ringkasan singkat" /></div>
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between">
                    <span><i class="bi bi-list-ol"></i> Susun Tahapan ({{ form.stages.length }})</span>
                    <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addStage"><i class="bi bi-plus-circle"></i> Tambah Tahap</button>
                </div>
                <div v-if="!form.stages.length" class="wca-hint" style="margin:0 0 .6rem"><i class="bi bi-info-circle"></i> Belum ada tahap. Klik <b>Tambah Tahap</b> untuk mulai menyusun urutan seleksi.</div>

                <div v-for="(s, i) in form.stages" :key="i" class="wca-stagecard" :class="{ 'is-tp': tahapTalentPool(i) }">
                    <div class="wca-stagecard__num">{{ i + 1 }}</div>
                    <div class="wca-stagecard__body">
                        <!-- Penanda titik tuntas ikut di kartunya, bukan hanya di
                             pemilih di atas: pada alur panjang, pemilih itu sudah
                             tergulung jauh ke atas saat admin membaca tahap ke-9. -->
                        <div v-if="form.tuntasTahap === i + 1" class="alr-tpflag is-tuntas">
                            <i class="bi bi-flag-fill"></i>
                            Titik tuntas — kandidat dinyatakan <b>DITERIMA</b> begitu tahap ini diloloskan, dan kuota terpotong.
                        </div>
                        <div v-if="tahapTalentPool(i)" class="alr-tpflag"><i class="bi bi-stars"></i> Talent Pool aktif — kandidat tak lolos di tahap ini bisa disimpan (dari cut-off Tahap {{ form.talentPoolMulai }}).</div>
                        <div class="wca-frow">
                            <div><label class="wca-field-lbl">Nama Tahap</label><el-input v-model="s.label" placeholder="mis. Psikotes Online" /></div>
                            <div><label class="wca-field-lbl">Tipe</label>
                                <!-- Ganti tipe bisa membuat mode keputusan yang dipilih
                                     tak berlaku lagi (mis. jadi ujian online) — selaraskan
                                     saat itu juga, jangan biarkan ketahuan saat menyimpan. -->
                                <RefSelect type="tipe" v-model="s.tipe" placeholder="Pilih tipe" @update:model-value="samakanMode(s)" />
                                <!-- Tipe yang sudah dipensiunkan tidak ada di daftar
                                     pilihan, jadi kotaknya tampak KOSONG padahal
                                     tahapnya punya tipe. Tanpa keterangan ini admin
                                     mengira tipenya belum diisi, memilih yang baru,
                                     dan diam-diam mengubah perilaku tahap yang
                                     sebenarnya cuma ingin ia salin. -->
                                <small v-if="tipeUsang(s.tipe)" class="alr-usang">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    Tipe <b>{{ s.tipe }}</b> sudah dinonaktifkan di Master Tipe Tahap, jadi tak muncul di daftar.
                                    Biarkan kosong bila ingin mempertahankannya — memilih tipe lain akan mengubah perilaku tahap ini.
                                </small>
                            </div>
                        </div>
                        <div v-if="butuhFormulir(s)" class="wca-frow">
                            <div><label class="wca-field-lbl">Formulir yang Diisi</label>
                                <RefSelect type="formulir" v-model="s.formulirId" placeholder="Pilih formulir (Master Formulir)" clearable />
                            </div>
                        </div>

                        <!-- JADWAL PENGISIAN FORMULIR — setiap tahap bertipe Formulir,
                             termasuk tahap 1 (keputusan user: dikendalikan dari sini,
                             bukan posisi tahap). Worklist hanya menampilkan pengaturan
                             jadwal di kolom yang di sini diberi jadwal. Tanggal pastinya
                             TIDAK disimpan di sini: satu alur dipakai banyak program,
                             jadi tanggal diisi per program dari kepala kolom worklist. -->
                        <div v-if="butuhFormulir(s)" class="alr-batas" :class="{ 'is-on': (s.batasMode || 'TANPA') !== 'TANPA' }">
                            <div class="alr-batas__head">
                                <b><i class="bi bi-hourglass-split"></i> Jadwal pengisian formulir</b>
                                <small>Kandidat hanya bisa mengisi formulir tahap ini di antara waktu dibuka dan batas akhirnya. Lewat batas, formulir terkunci — admin yang memutuskan kelanjutannya.</small>
                            </div>
                            <!-- Tahap 1 = formulir pendaftaran: pelamar biasa mengirimnya
                                 saat melamar, dalam masa pendaftaran Pembukaan Program.
                                 Dikatakan terang-terangan supaya jadwal di sini tidak
                                 disangka membatasi pendaftaran. -->
                            <p v-if="i === 0" class="alr-batas__note">
                                <i class="bi bi-info-circle-fill"></i>
                                <span>
                                    Tahap pertama adalah <b>formulir pendaftaran</b>: pelamar biasa mengisinya saat melamar,
                                    dalam masa pendaftaran yang diatur di <b>Pembukaan Program</b>. Jadwal di sini berlaku bagi
                                    kandidat yang mengisi formulir tahap ini <b>sesudah masuk</b> — mis. ditarik dari Talent Pool
                                    langsung ke tahap ini.
                                </span>
                            </p>
                            <div class="alr-batas__opsi">
                                <button
                                    v-for="o in opsiBatas" :key="o.v" type="button" class="alr-batas__opt"
                                    :class="{ 'is-on': (s.batasMode || 'TANPA') === o.v }"
                                    @click="s.batasMode = o.v"
                                >
                                    <i class="bi" :class="o.ikon"></i>
                                    <span><b>{{ o.l }}</b><small>{{ o.d }}</small></span>
                                </button>
                            </div>
                            <div v-if="s.batasMode === 'OTOMATIS'" class="alr-batas__row">
                                <label class="wca-field-lbl">Berapa hari sejak tahap terbuka</label>
                                <el-input-number v-model="s.batasHari" :min="1" :max="90" controls-position="right" style="width: 180px" />
                                <small>Berakhir pukul 23.59 WIB pada hari ke-{{ s.batasHari || 'N' }} sejak tahapnya terbuka untuk kandidat.</small>
                            </div>
                        </div>

                        <!-- DAFTAR TES / AKTIVITAS — satu tahap bisa berisi banyak tes.
                             Online atau manual ditentukan TIPE TAHAP di atas, bukan
                             per aktivitas: tipe "Tes Online" = semua aktivitasnya ujian
                             HC Learn yang dijadwalkan; tipe lain = ditangani tim.
                             Peran INFORMATIF tidak menentukan lulus. -->
                        <div class="alr-tests">
                            <div class="alr-tests__head">
                                <span><i class="bi bi-list-check"></i> Daftar Tes / Aktivitas <template v-if="(s.tests || []).length">({{ s.tests.length }})</template><span v-else class="alr-tests__opt">— opsional</span></span>
                                <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addTest(s)"><i class="bi bi-plus-circle"></i> Tambah Tes</button>
                            </div>
                            <div v-if="!(s.tests || []).length" class="alr-tests__empty">
                                <i class="bi bi-check-circle-fill"></i>
                                <div>
                                    <b>Biarkan kosong bila tahap ini hanya satu aktivitas.</b>
                                    Sistem otomatis menganggapnya 1 aktivitas bernama “{{ s.label || 'nama tahap' }}”.
                                    <br>Isi daftar ini <b>hanya</b> bila tahap berisi beberapa tes sekaligus — mis. FGD = Psikotes 1 + Psikotes 2 + Wawancara.
                                </div>
                            </div>
                            <!-- URUTAN PENGERJAAN — hanya berarti bila aktivitasnya
                                 lebih dari satu. Untuk satu aktivitas, "berurutan"
                                 dan "bersamaan" berarti hal yang persis sama, dan
                                 menawarkannya cuma menambah pilihan tanpa akibat. -->
                            <div v-if="(s.tests || []).length > 1 && modeUrutan.length" class="alr-urut">
                                <div class="alr-urut__head">
                                    <i class="bi bi-diagram-3"></i>
                                    <span>Cara {{ s.tests.length }} aktivitas ini dikerjakan</span>
                                </div>
                                <!-- Pilihan, label, dan penjelasannya SELURUHNYA dari
                                     Master Mode Urutan. Menambah mode ketiga cukup
                                     satu baris di master — layar ini tidak perlu
                                     disentuh, dan tidak ada kode mode yang ditulis
                                     di sini untuk dibandingkan. -->
                                <div class="alr-urut__opts">
                                    <button
                                        v-for="m in modeUrutan" :key="m.value"
                                        type="button" class="alr-urut__opt"
                                        :class="{ 'is-on': urutanTerpilih(s) === m.value }"
                                        :style="urutanTerpilih(s) === m.value ? { borderColor: m.warna, background: m.warna + '14' } : null"
                                        @click="s.urutanAktivitas = m.value"
                                    >
                                        <span class="alr-urut__ico" :style="{ color: m.warna }"><i class="bi" :class="m.ikon || 'bi-diagram-3'"></i></span>
                                        <span>
                                            <b>{{ m.label }}</b>
                                            <small>{{ m.deskripsi }}</small>
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div v-for="(t, k) in s.tests" :key="k" class="alr-test">
                                <span class="alr-test__no">{{ k + 1 }}</span>
                                <div class="alr-test__body">
                                    <div class="wca-frow">
                                        <div><label class="wca-field-lbl">Nama Tes / Aktivitas</label><el-input v-model="t.label" placeholder="mis. Papi Kostick" /></div>
                                        <div><label class="wca-field-lbl">Tipe Aktivitas</label>
                                            <RefSelect type="tipe" v-model="t.tipe" :placeholder="`Ikut tahap (${namaTipe(s.tipe)})`" clearable @update:model-value="tipeAktivitasBerubah(t, s)" />
                                        </div>
                                    </div>
                                    <div class="wca-frow">
                                        <div><label class="wca-field-lbl">Peran</label>
                                            <el-select v-model="t.peran" style="width:100%">
                                                <el-option label="Penentu — menentukan lulus/tidak" value="PENENTU" />
                                                <el-option label="Informatif — data saja, tidak menentukan" value="INFORMATIF" />
                                            </el-select>
                                        </div>
                                        <div class="alr-test__flags">
                                            <span class="alr-test__prov" :class="tesOnline(t.tipe || s.tipe) ? 'is-sys' : 'is-man'">
                                                <i class="bi" :class="tesOnline(t.tipe || s.tipe) ? 'bi-robot' : 'bi-person-workspace'"></i>
                                                {{ tesOnline(t.tipe || s.tipe) ? 'Ujian online — dijadwalkan di Penjadwalan' : 'Ditangani tim — hasilnya dicatat di Worklist' }}
                                            </span>
                                        </div>
                                    </div>
                                    <!-- PERPINDAHAN KE AKTIVITAS BERIKUTNYA.
                                         Hanya berarti pada tahap BERURUTAN —
                                         di tahap paralel semua sudah terbuka
                                         bersamaan, jadi tak ada perpindahan yang
                                         bisa dipicu. Juga tidak untuk aktivitas
                                         TERAKHIR: tak ada yang menyusul di
                                         belakangnya. -->
                                    <div
                                        v-if="infoUrutan(s)?.berurutan && k < (s.tests || []).length - 1 && modeLanjut.length"
                                        class="alr-lanjut"
                                    >
                                        <div class="alr-lanjut__head">
                                            <i class="bi bi-arrow-right-circle"></i>
                                            <span>Setelah aktivitas ini selesai</span>
                                        </div>
                                        <div class="alr-lanjut__opts">
                                            <button
                                                v-for="m in modeLanjut" :key="m.value"
                                                type="button" class="alr-lanjut__opt"
                                                :class="{ 'is-on': (t.lanjutMode || lanjutBawaan()) === m.value }"
                                                @click="t.lanjutMode = m.value"
                                            >
                                                <span class="alr-lanjut__ico" :style="{ color: m.warna }"><i class="bi" :class="m.ikon"></i></span>
                                                <span>
                                                    <b>{{ m.nama }}</b>
                                                    <small>{{ m.deskripsi }}</small>
                                                </span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- CARA AKTIVITAS INI DINILAI.
                                         Hanya untuk yang hasilnya dicatat TIM —
                                         ujian online nilainya datang dari HCLearn,
                                         jadi menyetelnya di sana hanya menyimpan
                                         aturan yang tak pernah dipakai.

                                         Tes tertulis punya angka; DISC & FGD
                                         punya predikat; wawancara cukup
                                         Lulus/Gagal + catatan. Memaksakan satu
                                         bentuk untuk ketiganya membuat penilai
                                         mengarang angka, dan angka karangan itu
                                         terbaca seolah hasil ukur. -->
                                    <!-- PEMERIKSAAN ≠ TES, JADI LAYARNYA BEDA.

                                         Reference check & background check tidak dinilai: tidak ada angka,
                                         tidak ada predikat. Hasilnya berupa TEMUAN per komponen (atau per
                                         narasumber) lalu satu adjudikasi. Menawarkan "Cara aktivitas ini
                                         dinilai" di sini membuat admin memilih skala 0–1000 untuk verifikasi
                                         ijazah — dan angka karangan itu terbaca seolah hasil ukur.

                                         Penandanya dari Master Tipe Tahap (Flag_Pemeriksaan), bukan daftar
                                         kode di layar ini. -->
                                    <div v-if="pemeriksaan(t, s)" class="alr-periksa">
                                        <div class="alr-periksa__head">
                                            <i class="bi bi-shield-check"></i>
                                            <span>Pemeriksaan — tidak dinilai dengan angka</span>
                                        </div>
                                        <p class="alr-periksa__ket">
                                            Hasilnya dicatat sebagai <b>{{ satuanPeriksa(t, s) }}</b> beserta statusnya,
                                            lalu ditutup satu kesimpulan: <b>{{ adjudikasiPeriksa(t, s) }}</b>.
                                            Berkas bukti diunggah tim di kotak “Berkas hasil” pada tahap ini.
                                        </p>
                                        <p v-if="!tampilKandidat(t, s)" class="alr-periksa__ket is-samar">
                                            <i class="bi bi-eye-slash"></i>
                                            Aktivitas ini internal — kandidat tidak melihatnya. Pastikan persetujuan
                                            pemeriksaan sudah diambil di formulir lamaran.
                                        </p>
                                    </div>

                                    <div v-if="!tesOnline(t.tipe || s.tipe) && !pemeriksaan(t, s) && modePenilaian.length" class="alr-nilai">
                                        <div class="alr-nilai__head">
                                            <i class="bi bi-clipboard-data"></i>
                                            <span>Cara aktivitas ini dinilai</span>
                                        </div>
                                        <el-select
                                            :model-value="t.penilaianMode || penilaianBawaan()"
                                            style="width:100%"
                                            @update:model-value="(v) => (t.penilaianMode = v)"
                                        >
                                            <el-option v-for="m in modePenilaian" :key="m.value" :value="m.value" :label="m.nama">
                                                <div class="alr-nilai__opt">
                                                    <b>{{ m.nama }}</b>
                                                    <small>{{ m.deskripsi }}</small>
                                                </div>
                                            </el-option>
                                        </el-select>

                                        <div v-if="infoPenilaian(t)?.butuhOpsi" class="wca-frow wca-frow--full" style="margin-top:.5rem">
                                            <div>
                                                <label class="wca-field-lbl">Daftar pilihan <b>*</b></label>
                                                <el-input
                                                    v-model="t.penilaianOpsi"
                                                    placeholder="mis. Dominance, Influence, Steadiness, Compliance"
                                                />
                                                <small class="alr-nilai__ket">Pisahkan dengan koma. Penilai memilih SATU dari daftar ini — bukan mengetik bebas, supaya hasilnya bisa direkap.</small>
                                            </div>
                                        </div>

                                        <div v-else-if="infoPenilaian(t)?.tipe === 'ANGKA'" class="wca-frow" style="margin-top:.5rem">
                                            <div>
                                                <label class="wca-field-lbl">Nilai maksimum</label>
                                                <el-input-number v-model="t.nilaiMaks" :min="1" :max="10000" :step="5" controls-position="right" style="width:100%" />
                                            </div>
                                            <div></div>
                                        </div>

                                        <p v-if="infoPenilaian(t)" class="alr-nilai__ket" style="margin-top:.45rem">
                                            <i class="bi bi-info-circle"></i>
                                            {{ infoPenilaian(t).deskripsi }}
                                            <b>Catatan penilai selalu tersedia</b> di semua mode.
                                        </p>
                                    </div>

                                    <!-- TERLIHAT KANDIDAT ATAU TIDAK.
                                         Sebagian aktivitas murni urusan internal:
                                         Background Check, cek referensi, verifikasi
                                         ijazah. Tim wajib mencatatnya, kandidat tak
                                         punya urusan dengannya — dan barisnya di
                                         portal hanya memancing pertanyaan tentang
                                         sesuatu yang tak bisa ia kerjakan.

                                         Aktivitas yang MENUNTUT sesuatu dari kandidat
                                         (ujian online, atau yang meminta unggahan)
                                         tidak boleh disembunyikan — itu jalan buntu,
                                         jadi pilihannya dikunci, bukan sekadar
                                         diingatkan.

                                         Begitu pula yang BERJADWAL. MCU, wawancara,
                                         tes offline, tanda tangan kontrak: kandidat
                                         harus hadir di tanggal dan tempat tertentu,
                                         dan portal adalah tempat ia memastikannya.
                                         Menyembunyikannya pernah terjadi pada MCU —
                                         undangannya terkirim, portalnya berbunyi
                                         "menunggu dijadwalkan", dan yang tidak
                                         datang dicatat mangkir. -->
                                    <label class="alr-lihat" :class="{ 'is-off': !tampilKandidat(t, s), 'is-locked': wajibTampil(t, s) }">
                                        <el-switch
                                            :model-value="tampilKandidat(t, s)"
                                            :disabled="wajibTampil(t, s)"
                                            @update:model-value="(v) => (t.tampilKandidat = v)"
                                        />
                                        <span>
                                            <b>
                                                <i class="bi" :class="tampilKandidat(t, s) ? 'bi-eye-fill' : 'bi-eye-slash-fill'"></i>
                                                {{ tampilKandidat(t, s) ? 'Terlihat kandidat' : 'Internal — tidak terlihat kandidat' }}
                                            </b>
                                            <small v-if="wajibTampil(t, s)">
                                                {{ alasanWajibTampil(t, s) }}
                                            </small>
                                            <small v-else-if="tampilKandidat(t, s)">
                                                Muncul di portal kandidat berikut status &amp; jadwalnya.
                                            </small>
                                            <small v-else>
                                                Hilang sama sekali dari portal kandidat. Tetap dijadwalkan &amp; dicatat di Worklist,
                                                dan tetap ikut menentukan kesimpulan tahap.
                                            </small>
                                        </span>
                                    </label>

                                    <!-- UNGGAH OLEH KANDIDAT. Beda dari "berkas hasil"
                                         yang diunggah TIM: ini berkas yang HARUS
                                         DISERAHKAN kandidat (lembar jawaban terpindai,
                                         sertifikat, foto hasil kerja). Diatur per
                                         aktivitas karena satu tahap bisa memuat tes
                                         offline yang menuntutnya dan wawancara yang
                                         tidak. Tidak ditawarkan untuk ujian online —
                                         berkasnya datang dari penyedia, bukan kandidat. -->
                                    <div v-if="!tesOnline(t.tipe || s.tipe)" class="alr-unggah" :class="{ 'is-on': t.unggahKandidat }">
                                        <div class="alr-unggah__head">
                                            <div>
                                                <b><i class="bi bi-cloud-arrow-up"></i> Kandidat harus mengunggah berkas</b>
                                                <small>Berkas diminta di portal kandidat pada aktivitas ini.</small>
                                            </div>
                                            <el-switch v-model="t.unggahKandidat" />
                                        </div>

                                        <div v-if="t.unggahKandidat" class="alr-unggah__body">
                                            <div class="wca-frow">
                                                <div>
                                                    <label class="wca-field-lbl">Format yang diterima</label>
                                                    <el-select v-model="t.unggahFormat" style="width:100%">
                                                        <el-option label="PDF saja" value="pdf" />
                                                        <el-option label="Gambar saja (JPG/PNG)" value="jpg,jpeg,png" />
                                                        <el-option label="PDF & gambar" value="pdf,jpg,jpeg,png" />
                                                    </el-select>
                                                </div>
                                                <div>
                                                    <label class="wca-field-lbl">Ukuran maksimum</label>
                                                    <!-- Pilihannya dari Master Batas Unggah, bukan angka
                                                         di sini — batas yang dijanjikan admin dijamin sama
                                                         dengan yang diberlakukan saat kandidat mengunggah. -->
                                                    <el-select v-model="t.unggahMaksMb" style="width:100%">
                                                        <el-option v-for="o in opsiBatasUnggah(t)" :key="o.value" :label="o.label" :value="o.value" />
                                                    </el-select>
                                                </div>
                                            </div>
                                            <div class="wca-frow wca-frow--full">
                                                <div>
                                                    <label class="wca-field-lbl">Petunjuk untuk kandidat</label>
                                                    <el-input v-model="t.unggahPetunjuk" placeholder="mis. Unggah lembar jawaban yang sudah dipindai." />
                                                </div>
                                            </div>
                                            <label class="alr-unggah__wajib">
                                                <el-checkbox v-model="t.unggahWajib" />
                                                <span><b>Wajib</b> — aktivitas tidak bisa dianggap selesai tanpa berkas.</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- INSTRUKSI UNTUK KANDIDAT — ditulis SEKALI di sini,
                                         lalu ikut di undangan & portal setiap kandidat
                                         saat aktivitas ini dijadwalkan. Rekruter tidak
                                         mengetik ulang paket pemeriksaan atau persiapan
                                         tiap angkatan; per program cukup di alurnya.
                                         Hanya untuk aktivitas BERJADWAL yang dibaca
                                         kandidat — jadwal privat tidak pernah dikirim. -->
                                    <div v-if="bolehInstruksi(t, s)" class="alr-unggah" :class="{ 'is-on': adaInstruksi(t) }">
                                        <div class="alr-unggah__head">
                                            <div>
                                                <b><i class="bi bi-card-checklist"></i> Instruksi untuk kandidat</b>
                                                <small>
                                                    Ikut otomatis di undangan &amp; portal tiap kandidat. Tulis yang sama untuk semua
                                                    (pemeriksaan, persiapan, dokumen) — tanggal dan tempat sudah dirakit sistem.
                                                </small>
                                            </div>
                                            <button v-if="!t.instruksiBuka && !adaInstruksi(t)" type="button" class="alr-instruksi__buka" @click="t.instruksiBuka = true">
                                                <i class="bi bi-pencil"></i> Tulis
                                            </button>
                                        </div>
                                        <div v-if="t.instruksiBuka || adaInstruksi(t)" class="alr-unggah__body">
                                            <EditorQuill
                                                v-model="t.instruksiHtml"
                                                ringkas
                                                placeholder="mis. Pemeriksaan yang wajib ada: darah & urine lengkap, rontgen thorax. Bawa KTP asli. Puasa 10 jam sebelum pemeriksaan."
                                            />
                                        </div>
                                    </div>
                                    <!-- INFORMASI BIAYA — hanya untuk tipe yang punya kalimat
                                         biaya (mis. MCU). Bawaan untuk setiap jadwal di alur ini;
                                         rekruter masih bisa mengubahnya per jadwal. Matikan bila
                                         biayanya dijelaskan sendiri di instruksi, atau ditanggung
                                         langsung perusahaan. -->
                                    <div
                                        v-if="bolehInstruksi(t, s) && infoTipe(t.tipe || s.tipe)?.biaya"
                                        class="alr-unggah" :class="{ 'is-on': t.tampilBiaya !== false }"
                                    >
                                        <div class="alr-unggah__head">
                                            <div>
                                                <b><i class="bi bi-cash-coin"></i> Tampilkan informasi biaya ke kandidat</b>
                                                <small>Bawaan untuk setiap jadwal di alur ini — rekruter masih bisa mengubahnya per jadwal.</small>
                                            </div>
                                            <el-switch v-model="t.tampilBiaya" />
                                        </div>
                                        <!-- Kalimatnya bisa disunting per alur. Kosong = kalimat
                                             bawaan tipenya (terlihat sebagai contoh di kotaknya). -->
                                        <div v-if="t.tampilBiaya !== false" class="alr-unggah__body">
                                            <label class="wca-field-lbl">Kalimat biaya</label>
                                            <el-input
                                                v-model="t.kalimatBiaya" type="textarea" :rows="3" maxlength="1000" show-word-limit
                                                :placeholder="infoTipe(t.tipe || s.tipe).biaya"
                                            />
                                            <small class="alr-biaya__hint">Kosongkan untuk memakai kalimat bawaan di atas (abu-abu).</small>
                                        </div>
                                    </div>
                                </div>
                                <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus tes" @click="removeTest(s, k)"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>

                        <!-- MODE KEPUTUSAN — sengaja DI BAWAH daftar tes, karena ini
                             kesimpulan ATAS tes-tes di atasnya. Pilihannya menyesuaikan:
                             1 aktivitas → cukup "otomatis vs admin"; 2+ aktivitas →
                             aturan menunggu ikut berarti, jadi seluruh mode ditampilkan. -->
                        <div class="alr-dec" :class="{ 'is-multi': jumlahTes(s) >= 2 }">
                            <div class="alr-dec__head">
                                <i class="bi bi-signpost-2"></i>
                                <span>Cara tahap ini menyimpulkan</span>
                                <span class="alr-dec__count">{{ jumlahTes(s) }} aktivitas</span>
                            </div>

                            <el-select v-model="s.mode" style="width:100%" placeholder="Pilih cara menyimpulkan">
                                <el-option v-for="m in modeTampil(s)" :key="m.value" :label="labelMode(m, s)" :value="m.value" />
                            </el-select>

                            <p class="alr-dec__note">
                                <i class="bi" :class="modeInfo(s)?.ikon || 'bi-info-circle'"></i>
                                <span>{{ catatanMode(s) }}</span>
                            </p>
                        </div>

                        <!-- PENGUMUMAN HASIL — kapan hasil tahap ini boleh dilihat kandidat.
                             Yang disimpan di sini cuma ATURAN-nya; tanggal pastinya diisi
                             saat penjadwalan angkatan, karena satu alur dipakai banyak program. -->
                        <div class="alr-ann">
                            <div class="alr-ann__head"><i class="bi bi-megaphone"></i> Pengumuman Hasil</div>
                            <div class="wca-frow">
                                <div>
                                    <label class="wca-field-lbl">Kapan hasil terlihat kandidat</label>
                                    <!-- Opsi DIAMBIL DARI Master Mode Pengumuman (Flag_Aktif) — tak hardcode. -->
                                    <el-select v-model="s.pengumuman" style="width:100%" placeholder="Pilih mode" no-data-text="Tidak ada mode aktif">
                                        <el-option v-for="m in modePengumuman" :key="m.value" :label="m.label" :value="m.value" />
                                    </el-select>
                                </div>
                                <div v-if="modeButuhJeda(s.pengumuman)">
                                    <label class="wca-field-lbl">Saran jeda (hari)</label>
                                    <el-input-number v-model="s.jedaHari" :min="0" :max="3650" controls-position="right" style="width:100%" />
                                </div>
                            </div>
                            <label class="alr-ann__check">
                                <el-checkbox v-model="s.notifikasi">Beri tahu kandidat lewat email saat hasil terbit</el-checkbox>
                            </label>
                            <p class="alr-ann__note">{{ catatanPengumuman(s) }}</p>
                        </div>

                        <!-- Cut-off Talent Pool kini SATU titik di atas (bukan per tahap). -->

                        <!-- UPLOAD BERKAS HASIL — mis. hasil wawancara. Bisa diwajibkan
                             atau opsional per tahap. Disembunyikan untuk tipe yang di
                             Master Tipe Tahap tidak menawarkannya (Flag_Opsi_Upload):
                             tipe berbasis berkas (MCU, Penawaran, BG/Reference Check,
                             Tanda Tangan) tetap menerima unggahan dari sifat tipenya. -->
                        <div v-if="opsiUpload(s)" class="alr-tp alr-up" :class="{ 'is-on': s.uploadHasil }">
                            <div class="alr-tp__main">
                                <span class="alr-tp__ico"><i class="bi bi-paperclip"></i></span>
                                <div class="alr-tp__txt">
                                    <b>Upload berkas hasil (PDF/JPG)</b>
                                    <small>Aktifkan agar admin/requester mengunggah hasil di tahap ini (mis. hasil wawancara atau FGD). <template v-if="s.uploadHasil">Centang <em>“wajib”</em> bila berkas harus ada sebelum Loloskan.</template></small>
                                </div>
                            </div>
                            <div class="alr-up__ctl">
                                <label v-if="s.uploadHasil" class="alr-up__wajib"><el-checkbox v-model="s.wajibUpload" /> wajib</label>
                                <el-switch v-model="s.uploadHasil" />
                            </div>
                        </div>

                        <!-- TITIK TUNTAS tidak lagi disetel di sini — lihat seksi
                             "Peraturan Tambahan" di bawah daftar tahap. Sakelar
                             per tahap yang saling mematikan membuat sesuatu yang
                             hanya boleh ada SATU tampak seperti pilihan bebas,
                             dan pada alur panjang penanda yang berpindah tidak
                             terlihat sama sekali. -->

                    </div>
                    <div class="wca-stagecard__actions">
                        <button class="wca-iconbtn" type="button" title="Naik" :disabled="i === 0" :onClick="i === 0 ? null : () => moveStage(i, -1)"><i class="bi bi-chevron-up"></i></button>
                        <button class="wca-iconbtn" type="button" title="Turun" :disabled="i === form.stages.length - 1" :onClick="i === form.stages.length - 1 ? null : () => moveStage(i, 1)"><i class="bi bi-chevron-down"></i></button>
                        <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus" @click="removeStage(i)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>

            <!-- ═══ PERATURAN TAMBAHAN ═══
                 Aturan setingkat ALUR, bukan setingkat tahap: keduanya menunjuk
                 SATU tahap dari daftar di atas. Karena itu letaknya di bawah —
                 tahapnya harus sudah tersusun dulu sebelum ada yang bisa
                 ditunjuk, dan memintanya lebih dulu berarti memilih dari daftar
                 yang masih kosong. -->
            <div v-if="form.stages.length" class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders"></i> Peraturan Tambahan</div>

                <!-- TITIK TUNTAS. WAJIB: alur tanpa penanda ini tidak pernah bisa
                     menyatakan kandidat DITERIMA dan kuota tidak pernah terpotong —
                     kesalahan tanpa galat, yang baru ketahuan berbulan-bulan
                     kemudian saat ada yang menghitung kursi. -->
                <div class="alr-rule is-tuntascut" :class="{ 'is-on': form.tuntasTahap > 0, 'is-kurang': !form.tuntasTahap }">
                    <div class="alr-rule__l">
                        <span class="alr-rule__ico"><i class="bi bi-flag-fill"></i></span>
                        <div class="alr-rule__txt">
                            <b>Tahap yang menutup proses seleksi <span class="alr-wajib">wajib</span></b>
                            <small>
                                Begitu tahap terpilih <b>diloloskan</b>, kandidat dinyatakan <b>DITERIMA</b>
                                dan kuota terpotong. Tahap sesudahnya tetap dikerjakan (tanda tangan kontrak,
                                onboarding) tapi tidak lagi menentukan diterima atau tidaknya.
                            </small>
                        </div>
                    </div>
                    <!-- filterable: alur bisa memuat belasan tahap, dan mencari
                         "Offering" jauh lebih cepat daripada menggulung daftar. -->
                    <el-select
                        v-model="form.tuntasTahap"
                        class="alr-rule__sel"
                        filterable
                        placeholder="Pilih tahap"
                    >
                        <el-option
                            v-for="(s, i) in form.stages" :key="i"
                            :value="i + 1"
                            :label="`Tahap ${i + 1}${s.label ? ' — ' + s.label : ''}`"
                        />
                    </el-select>
                </div>

                <!-- CUT-OFF TALENT POOL — SATU titik saja. Tahap dari sini sampai
                     akhir otomatis aktif; tak perlu klik per tahap. -->
                <div class="alr-rule" :class="{ 'is-on': form.talentPoolMulai > 0 }">
                    <div class="alr-rule__l">
                        <span class="alr-rule__ico"><i class="bi bi-stars"></i></span>
                        <div class="alr-rule__txt">
                            <b>Cut-off ke Talent Pool</b>
                            <small>
                                Kandidat yang <b>tidak lolos</b> mulai tahap terpilih sampai tahap akhir
                                boleh disimpan ke Talent Pool. Pilih <b>satu</b> titik mulai — tahap
                                sesudahnya otomatis ikut.
                            </small>
                        </div>
                    </div>
                    <el-select
                        v-model="form.talentPoolMulai"
                        class="alr-rule__sel"
                        filterable
                        placeholder="Nonaktif"
                    >
                        <el-option :value="0" label="Nonaktif — tanpa cut-off" />
                        <el-option
                            v-for="(s, i) in form.stages" :key="i"
                            :value="i + 1"
                            :label="`Mulai Tahap ${i + 1}${s.label ? ' — ' + s.label : ''}`"
                        />
                    </el-select>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Alur" :busy="deleting" confirm-label="Ya, Hapus" note="Alur & seluruh tahapannya akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus alur <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <!-- ═══ NASIB ROMBONGAN YANG SEDANG BERJALAN ═══════════════════════
             Muncul HANYA bila alur ini memang sudah dipakai lamaran. Alur yang
             belum pernah dijalani siapa pun tersimpan langsung seperti dulu —
             tidak ada rombongan untuk dilindungi, jadi tidak ada yang perlu
             ditanyakan.

             Dua tombol, dan angkanya disebutkan LEBIH DULU. Tanpa pratinjau,
             pilihan "terapkan ke yang berjalan" adalah pertaruhan: admin baru
             tahu berapa orang yang ikut sesudah ia menekan, saat sudah tidak
             bisa dibatalkan. -->
        <AdminModal v-if="dampakShow" :show="dampakShow" title="Alur ini sedang dipakai" size="md" @close="dampakShow = false">
            <div class="wca-note wca-note--info" style="margin-bottom: 1rem">
                <i class="bi bi-info-circle"></i>
                <span>
                    Menyimpan akan membuat <b>versi baru</b> dari alur ini. Versi lama tetap utuh
                    supaya papan seleksi rombongan yang sedang berjalan tidak berubah, dan program
                    diarahkan ke versi baru mulai sekarang.
                </span>
            </div>

            <div class="wca-dinfo" style="margin-bottom: 1rem">
                <div><small>Masih punya tempat di versi baru</small><b>{{ dampak?.ikut ?? 0 }} kandidat</b></div>
                <div><small>Tetap di versi lama</small><b>{{ dampak?.tidak ?? 0 }} kandidat</b></div>
            </div>

            <!-- Yang TIDAK ikut disebut satu per satu beserta sebabnya. Angka
                 ringkas saja akan meninggalkan pertanyaan "yang mana, dan
                 kenapa" — pertanyaan yang justru paling sering ditanyakan. -->
            <div v-if="dampakTidakIkut.length" class="mal-dampak">
                <div class="mal-dampak__ttl">Tidak ikut pindah</div>
                <div v-for="r in dampakTidakIkut" :key="r.kode" class="mal-dampak__baris">
                    <b>{{ r.nama || r.kode }}</b>
                    <small>{{ r.sebab }}</small>
                </div>
            </div>

            <template #footer>
                <button type="button" class="wca-btn wca-btn--ghost" :disabled="saving" @click="dampakShow = false">
                    Batal
                </button>
                <button type="button" class="wca-btn wca-btn--soft" :disabled="saving" @click="simpanDenganPilihan('TIDAK')">
                    <i class="bi bi-people"></i> Simpan — yang berjalan pakai alur lama
                </button>
                <button type="button" class="wca-btn wca-btn--primary" :disabled="saving || !dampak?.ikut" @click="simpanDenganPilihan('SEMUA')">
                    <i class="bi bi-arrow-right-circle"></i>
                    Simpan + pindahkan {{ dampak?.ikut ?? 0 }} kandidat
                </button>
            </template>
        </AdminModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import RefSelect from '@career/RefSelect.vue';
import EditorQuill from '@career/EditorQuill.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-alur';
const CFG = { headers: { Accept: 'application/json' } };

/* Cadangan penanda pemeriksaan, dipakai HANYA selama kolom Flag_Pemeriksaan
   belum ada di basis data. Sesudah skrip skemanya dijalankan, masterlah yang
   menentukan — termasuk untuk tipe pemeriksaan yang belum ada hari ini. */
const PEMERIKSAAN_CADANGAN = ['REFERENCE_CHECK', 'BACKGROUND_CHECK'];

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect, EditorQuill },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-alur/masterAlur')],
    data() {
        return {
            list: [],
            loading: false,
            open: null,
            show: false,
            editingId: null,
            // DUPLIKAT — nama alur SUMBER saat modal dibuka lewat tombol Duplikat.
            // Disimpan namanya (bukan id-nya) supaya tak tergoda dipakai sebagai
            // rujukan: salinan berdiri sendiri sejak detik pertama, tidak terikat
            // apa pun ke asalnya. Nilainya cuma untuk dibaca manusia di layar.
            duplikatDari: null,
            // Filter Panel — semua nilai dikirim ke backend saat berubah.
            filters: { q: '', kategori: null, status: null, rentang: null },
            // Kategori yang BOLEH dilihat pengguna ini — datang dari server bersama
            // daftar alurnya, bukan dari master lengkap. Menentukan dua hal:
            // penyaring digambar atau tidak, dan pilihan apa saja di borang.
            kategoriOpsi: [],
            sheetOpen: false,
            cariTimer: null,
            // Mode pengumuman AKTIF dari Master Mode Pengumuman (bukan hardcode).
            modePengumuman: [],
            // Mode keputusan AKTIF — bagaimana tahap menyimpulkan (multi-tes).
            modeKeputusan: [],
            // Mode urutan AKTIF — aktivitas dikerjakan bersamaan atau berurutan.
            // Isi, label, dan perilakunya dari Master Mode Urutan.
            modeUrutan: [],
            // Aturan batas pengisian formulir tahap — kodenya sama dengan server
            // (BatasIsi::TANPA / MANUAL / OTOMATIS); NULL di basis data = TANPA.
            opsiBatas: [
                { v: 'TANPA', l: 'Tanpa jadwal', d: 'Formulir terbuka kapan saja.', ikon: 'bi-infinity' },
                { v: 'MANUAL', l: 'Dijadwalkan admin', d: 'Terkunci sampai waktu dibuka & batas akhirnya diisi per program dari worklist; sesudahnya hanya bisa diperpanjang.', ikon: 'bi-calendar-event' },
                { v: 'OTOMATIS', l: 'Otomatis', d: 'Terbuka sejak tahap dimulai, sekian hari.', ikon: 'bi-stopwatch' },
            ],
            // Mode penilaian AKTIF — cara aktivitas dinilai (tanpa nilai/angka/kategori).
            modePenilaian: [],
            // Mode lanjut AKTIF — otomatis / dipicu admin.
            modeLanjut: [],
            // Pilihan batas ukuran unggahan — dimuat dari Master Batas Unggah.
            batasUnggah: [],
            // Tipe tahap + perilakunya ('CAT' = ujian online berjadwal).
            tipeTahap: [],
            // talentPoolMulai: 0 = nonaktif; N = cut-off Talent Pool mulai tahap ke-N (sampai akhir).
            // tuntasTahap: 0 = BELUM DIPILIH (menghalangi simpan); N = tahap ke-N
            // menutup proses seleksi. Hanya boleh satu — karena itu berupa
            // pemilih, bukan sakelar di tiap tahap.
            form: { nama: '', kategori: '', deskripsi: '', stages: [], talentPoolMulai: 0, tuntasTahap: 0 },
            delShow: false,
            delTarget: null,
            // ── PILIHAN NASIB ROMBONGAN YANG SEDANG BERJALAN ────────────────
            // Terisi hanya saat alur yang disimpan MEMANG sudah dipakai
            // lamaran. `dampak` membawa hitungan dari server; `dampakPayload`
            // menahan muatan yang siap dikirim sampai admin memilih.
            dampakShow: false,
            dampak: null,
            dampakPayload: null,
            deleting: false,
            saving: false,
            toast: '',
            tm: null,
        };
    },
    watch: {
        // RefSelect hanya emit update:modelValue — pantau nilainya langsung.
        'filters.kategori'() { this.load(); },
        // Jaga cut-off tetap valid saat jumlah tahap berubah (mis. tahap dihapus).
        //
        // Titik tuntas ikut dijaga: menghapus tahap terakhir pada alur yang
        // titik tuntasnya ada DI SANA akan meninggalkan nomor yang menunjuk
        // tahap yang tidak ada lagi — tersimpan sebagai alur tanpa titik tuntas
        // sama sekali, tanpa satu pun peringatan.
        'form.stages.length'(n) {
            if (this.form.talentPoolMulai > n) this.form.talentPoolMulai = n;
            if (this.form.tuntasTahap > n) this.form.tuntasTahap = n;
        },
    },
    mounted() {
        this.load();
        this.loadModePengumuman();
        this.loadModeKeputusan();
        this.loadModeUrutan();
        this.loadModePenilaian();
        this.loadModeLanjut();
        this.loadBatasUnggah();
        this.loadTipeTahap();
    },
    computed: {
        /** Yang TIDAK ikut pindah — disebut satu per satu beserta sebabnya. */
        dampakTidakIkut() {
            return (this.dampak?.rincian || []).filter((r) => !r.ikut);
        },
        // Tiga keadaan modal, bukan dua. Duplikat memang MEMBUAT alur baru,
        // tapi menyebutnya "Buat Alur Seleksi" saat layarnya sudah penuh isi
        // salinan membuat orang mengira ia sedang menyunting yang lama.
        judulModal() {
            if (this.editingId) return 'Ubah Alur Seleksi';
            return this.duplikatDari ? 'Duplikat Alur Seleksi' : 'Buat Alur Seleksi';
        },
        labelSimpan() {
            if (this.editingId) return 'Perbarui';
            return this.duplikatDari ? 'Simpan Salinan' : 'Simpan Alur';
        },
        // Peta Kode Mode -> objek mode, untuk render label/ikon/catatan di daftar.
        modeMap() {
            const map = {};
            this.modePengumuman.forEach((m) => { map[m.value] = m; });
            return map;
        },
        /** Tepat satu kategori yang boleh -> tidak ada yang perlu dipilih. */
        kategoriTunggal() {
            return this.kategoriOpsi.length === 1 ? this.kategoriOpsi[0] : null;
        },
        adaFilter() {
            return !!(this.filters.q || this.filters.kategori || this.filters.status || (this.filters.rentang && this.filters.rentang.length));
        },
        jumlahFilter() {
            return [this.filters.q, this.filters.kategori, this.filters.status, this.filters.rentang?.length ? 1 : null].filter(Boolean).length;
        },
    },
    methods: {
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k; },

        /** Info satu tipe dari master (bukan daftar kode yang ditulis di sini). */
        infoTipe(kode) { return this.tipeTahap.find((t) => t.value === kode) || null; },
        /**
         * Aktivitas ini menerima instruksi untuk kandidat? Hanya yang BERJADWAL
         * dan diumumkan ke kandidat (Flag_Jadwal, bukan jadwal privat) — dari
         * master tipe, bukan daftar kode.
         */
        bolehInstruksi(t, s) {
            const i = this.infoTipe(t.tipe || s.tipe);

            return !!i?.jadwal && !i?.jadwalPrivat;
        },
        /** Instruksinya berisi sesuatu (bukan sekadar paragraf kosong editor). */
        adaInstruksi(t) {
            return !!String(t.instruksiHtml || '').replace(/<[^>]*>/g, '').trim();
        },
        /**
         * Tahap ini memakai tipe yang sudah dinonaktifkan? (punya nilai, tapi
         * tak ada di daftar pilihan). Dijaga agar tidak berteriak sebelum
         * daftar tipenya selesai dimuat — saat itu SEMUA tipe tampak usang.
         */
        tipeUsang(kode) { return !!kode && this.tipeTahap.length > 0 && !this.infoTipe(kode); },
        namaTipe(kode) { return this.infoTipe(kode)?.label || kode || '—'; },

        /**
         * Tipe ini menempelkan Master Formulir? — dari flag master, bukan daftar
         * kode. Dulu di sini tertulis `tipe === 'FORM' || tipe === 'DOCUMENT'`,
         * sehingga tipe baru yang juga berbasis formulir tak akan pernah bisa
         * memilih formulirnya tanpa mengubah file ini.
         */
        butuhFormulir(s) { return this.infoTipe(s.tipe)?.formulir === true; },
        /** Sakelar "Upload berkas hasil" ditawarkan untuk tipe tahap ini? (data Master Tipe Tahap) */
        opsiUpload(s) { return this.infoTipe(s.tipe)?.opsiUpload !== false; },

        /**
         * Aktivitas bertipe ini ujian online (CAT)? — dari Perilaku di Master
         * Tipe Tahap. Menentukan aktivitas itu dijadwalkan lewat Penjadwalan
         * (token HCLearn) atau dikerjakan tim & dicatat di Worklist.
         */
        tesOnline(kode) { return this.infoTipe(kode)?.perilaku === 'CAT'; },

        /**
         * Aktivitas ini PEMERIKSAAN (reference check / background check)?
         *
         * Sumber utamanya penanda master (Flag_Pemeriksaan). Daftar kode di
         * bawah hanya cadangan untuk lingkungan yang skrip skemanya belum
         * dijalankan — begitu kolomnya ada, master yang menentukan, termasuk
         * untuk tipe pemeriksaan baru yang belum terpikirkan sekarang.
         */
        pemeriksaan(t, s) {
            const kode = t?.tipe || s?.tipe || null;
            if (! kode) return false;

            return !! this.infoTipe(kode)?.pemeriksaan || PEMERIKSAAN_CADANGAN.includes(kode);
        },
        /** "temuan per komponen" vs "keterangan per narasumber". */
        satuanPeriksa(t, s) {
            return (t?.tipe || s?.tipe) === 'REFERENCE_CHECK'
                ? 'keterangan per narasumber'
                : 'temuan per komponen pemeriksaan';
        },
        adjudikasiPeriksa(t, s) {
            return (t?.tipe || s?.tipe) === 'REFERENCE_CHECK'
                ? 'Direkomendasikan / Ragu / Tidak'
                : 'Bersih / Perlu Pertimbangan / Tidak Memenuhi';
        },
        /**
         * Tipe aktivitas diganti — setelan yang tak lagi masuk akal ikut dibetulkan.
         *
         * Tanpa ini, aktivitas yang tadinya "Tes Offline" lalu diubah jadi
         * Background Check membawa serta mode penilaian ANGKA beserta nilai
         * maksimumnya. Layarnya memang tak lagi menampilkannya, tapi angkanya
         * tetap tersimpan dan ikut membeku ke setiap lamaran baru.
         */
        tipeAktivitasBerubah(t, s) {
            this.samakanMode(s);
            if (! this.pemeriksaan(t, s)) return;

            t.penilaianMode = this.penilaianBawaan();
            t.penilaianOpsi = '';
            t.nilaiMaks = null;

            // Internal secara bawaan — kecuali aktivitasnya memang meminta
            // berkas dari kandidat, yang berarti ia harus bisa melihatnya.
            if (t.unggahKandidat !== true) t.tampilKandidat = false;
        },

        /** Muat tipe tahap AKTIF beserta perilaku & flag-nya. */
        async loadTipeTahap() {
            try {
                this.tipeTahap = (await axios.get('/api/v1/karir/options/tipe', CFG)).data.result || [];
            } catch (e) { this.tipeTahap = []; }
        },

        /** Muat mode keputusan AKTIF (bagaimana tahap menyimpulkan). */
        async loadModeKeputusan() {
            try {
                this.modeKeputusan = (await axios.get('/api/v1/karir/options/mode-keputusan', CFG)).data.result || [];
            } catch (e) { this.modeKeputusan = []; }
        },
        /** Info mode terpilih (untuk kalimat efek di bawah dropdown). */
        modeInfo(s) { return this.modeKeputusan.find((m) => m.value === s.mode) || null; },

        async loadModeLanjut() {
            try {
                this.modeLanjut = (await axios.get('/api/v1/karir/options/mode-lanjut', CFG)).data.result || [];
            } catch (e) { this.modeLanjut = []; }
        },

        /**
         * Pilihan batas ukuran unggahan — DARI MASTER, bukan daftar angka di sini.
         *
         * Angka yang ditulis di layar harus dicocokkan manual dengan batas yang
         * benar-benar diberlakukan saat kandidat mengunggah; begitu keduanya
         * menyimpang, admin menjanjikan 5 MB dan kandidat ditolak di 2 MB tanpa
         * ada yang tahu sebabnya. Satu sumber menutup celah itu.
         */
        async loadBatasUnggah() {
            try {
                this.batasUnggah = (await axios.get('/api/v1/karir/options/batas-unggah', CFG)).data.result || [];
            } catch (e) { this.batasUnggah = []; }
        },
        /** Batas bawaan untuk aktivitas baru = pilihan aktif TERKECIL. */
        batasUnggahBawaan() {
            return this.batasUnggah[0]?.value ?? null;
        },
        /**
         * Pilihan yang ditampilkan untuk SATU aktivitas.
         *
         * Nilai lama yang sudah tersimpan tapi tidak lagi aktif tetap ikut
         * ditawarkan — kalau tidak, el-select tampil KOSONG dan admin yang
         * sekadar membuka alur lalu menyimpannya akan menghapus batas yang
         * sedang berlaku bagi kandidat berjalan.
         */
        opsiBatasUnggah(t) {
            const opsi = [...this.batasUnggah];
            const kini = t?.unggahMaksMb;
            if (kini && !opsi.some((o) => o.value === kini)) {
                opsi.push({ value: kini, label: `${kini} MB (setelan lama)`, lawas: true });
            }

            return opsi.sort((a, b) => a.value - b.value);
        },
        /** Bawaan = mode yang TIDAK menahan — perilaku sebelum fitur ini ada. */
        lanjutBawaan() {
            return (this.modeLanjut.find((m) => !m.butuhTrigger) || this.modeLanjut[0])?.value || null;
        },
        /** Perilaku mode urutan tahap — dipakai menyembunyikan pilihan yang tak berlaku. */
        infoUrutan(s) {
            return this.modeUrutan.find((m) => m.value === this.urutanTerpilih(s)) || null;
        },
        async loadModePenilaian() {
            try {
                this.modePenilaian = (await axios.get('/api/v1/karir/options/mode-penilaian', CFG)).data.result || [];
            } catch (e) { this.modePenilaian = []; }
        },
        /**
         * Mode bawaan = yang TIDAK meminta nilai apa pun.
         * Aktivitas baru tidak boleh diam-diam menuntut angka; menambahkannya
         * adalah keputusan sadar penyusun alur.
         */
        penilaianBawaan() {
            return (this.modePenilaian.find((m) => m.tipe === 'NONE') || this.modePenilaian[0])?.value || null;
        },
        /** Perilaku mode yang sedang dipilih sebuah aktivitas. */
        infoPenilaian(t) {
            const kode = t.penilaianMode || this.penilaianBawaan();
            return this.modePenilaian.find((m) => m.value === kode) || null;
        },
        async loadModeUrutan() {
            try {
                this.modeUrutan = (await axios.get('/api/v1/karir/options/mode-urutan', CFG)).data.result || [];
            } catch (e) { this.modeUrutan = []; }
        },
        /**
         * Mode urutan yang berlaku untuk tahap ini.
         *
         * Bawaannya = mode pertama yang TIDAK mengunci, dibaca dari masternya
         * (`berurutan === false`) — bukan kode 'PARALEL' yang ditulis di sini.
         * Alur lama tidak menyimpan field ini sama sekali, dan yang benar untuk
         * mereka adalah "tanpa urutan", persis perilaku selama ini.
         */
        urutanBawaan() { return (this.modeUrutan.find((m) => !m.berurutan) || this.modeUrutan[0])?.value || null; },
        urutanTerpilih(s) {
            return this.modeUrutan.some((m) => m.value === s.urutanAktivitas)
                ? s.urutanAktivitas
                : this.urutanBawaan();
        },

        /**
         * Aktivitas ini terlihat kandidat?
         *
         * Alur lama tak punya field ini; `undefined` harus berarti TERLIHAT —
         * itulah perilaku sebelum fitur ini ada.
         */
        tampilKandidat(t, s) { return this.wajibTampil(t, s) || t.tampilKandidat !== false; },
        /**
         * Aktivitas yang MUSTAHIL disembunyikan karena menuntut tindakan
         * kandidat: ujian online (ia harus menekan tombol mengerjakan) atau
         * aktivitas yang meminta unggahan.
         *
         * Penandanya dari Master Tipe Tahap (`wajibTampil`), bukan daftar kode
         * di layar — server memakai flag yang sama, jadi keduanya tak bisa
         * berselisih.
         */
        wajibTampil(t, s) {
            const i = this.infoTipe(t.tipe || s.tipe);

            // BERJADWAL = KANDIDAT HARUS HADIR, jadi ia harus melihatnya.
            // Undangan MCU-nya sudah terkirim lewat email; kalau portalnya
            // menutup jadwal itu, satu-satunya kabar yang ia punya adalah surel
            // yang mungkin sudah terkubur — dan yang tidak datang dicatat
            // mangkir. Negosiasi penawaran dikecualikan: itu rapat tim, kandidat
            // memang tidak pernah diundang ke sana.
            return !!i?.wajibTampil || (!!i?.jadwal && !i?.jadwalPrivat) || t.unggahKandidat === true;
        },
        /** Kenapa sakelarnya terkunci — kalimatnya berbeda per sebab. */
        alasanWajibTampil(t, s) {
            const i = this.infoTipe(t.tipe || s.tipe);
            if (!!i?.jadwal && !i?.jadwalPrivat) {
                return 'Aktivitas ini dijadwalkan dan kandidat harus hadir, jadi jadwalnya wajib terlihat di portalnya.';
            }

            return 'Aktivitas ini menuntut kandidat mengerjakan atau mengunggah sesuatu, jadi harus terlihat.';
        },

        /** Jumlah aktivitas nyata: daftar kosong tetap dihitung 1 (dibuat sistem). */
        jumlahTes(s) { return Math.max(1, (s.tests || []).length); },

        /**
         * Pilihan mode yang MASUK AKAL untuk tahap ini.
         *
         * SELURUH MODE DITAWARKAN, termasuk yang manual penuh.
         *
         * Dulu di sini ada penyaring kedua: tahap ber-ujian online PENENTU
         * kehilangan semua mode yang `autoGugur`-nya mati, karena server memang
         * akan menimpanya saat disimpan. Penimpaan itu sudah dicabut — lihat
         * MasterAlurController::simpanTahap() — dan alasannya ada dua.
         *
         * Pertama, gerbangnya diuji di level TAHAP: satu psikotes online cukup
         * untuk mencabut mode manual dari FGD dan wawancara di tahap yang sama,
         * dua hal yang justru mustahil disimpulkan mesin.
         *
         * Kedua, kelulusan itu KEBIJAKAN, bukan aritmetika. Kandidat yang
         * nilainya di bawah ambang bisa saja tetap diloloskan setelah ditinjau
         * ulang, dan itu keputusan yang memang milik manusia. Menghapus
         * pilihannya dari layar berarti memutuskan lebih dulu atas nama admin.
         *
         * Yang tersisa cuma satu penyederhanaan, dan itu murni soal keterbacaan:
         * dengan 1 aktivitas, "tunggu semua" dan "tunggu tes terakhir" berakhir
         * sama persis. Maka mode dikelompokkan per PASANGAN perilaku yang
         * benar-benar berbeda — maju-otomatis dan gugur-otomatis — dan tiap
         * pasangan diwakili satu.
         */
        modeTampil(s) {
            const layak = this.modeKeputusan;
            if (this.jumlahTes(s) >= 2) return layak;

            const wakil = [];
            for (const m of layak) {
                if (!wakil.some((w) => w.autoLanjut === m.autoLanjut && w.autoGugur === m.autoGugur)) wakil.push(m);
            }

            return wakil;
        },

        /** Label dropdown: disederhanakan saat tahap hanya satu aktivitas. */
        labelMode(m, s) {
            if (this.jumlahTes(s) >= 2) return m.label;
            if (m.autoLanjut) return 'Otomatis — lulus maju sendiri, gagal langsung tidak lolos';

            return m.autoGugur
                ? 'Gagal otomatis tidak lolos — kelulusan dikonfirmasi admin'
                : 'Manual — admin yang memutuskan lulus maupun gagal';
        },

        /** Kalimat akibat, ditulis mengikuti jumlah aktivitas nyata di tahap ini. */
        catatanMode(s) {
            const m = this.modeInfo(s);
            if (!m) return 'Pilih bagaimana tahap ini menyimpulkan hasil.';

            const n = this.jumlahTes(s);
            if (n < 2) {
                if (m.autoLanjut) {
                    return 'Begitu aktivitas ini selesai: lulus → kandidat langsung maju ke tahap berikutnya, gagal → langsung dinyatakan tidak lolos. Admin tidak mengetuk palu di tahap ini.';
                }

                return m.autoGugur
                    ? 'Gagal → kandidat langsung dinyatakan tidak lolos tanpa menunggu admin. Lulus → kandidat TETAP di tahap ini sampai admin menekan Loloskan; di portalnya tertulis hasil sedang ditinjau.'
                    : 'Setelah aktivitas ini selesai, tahap ditandai SIAP DIPUTUS — admin yang menekan lolos/tolak, baik untuk yang lulus maupun yang gagal.';
            }

            const tunggu = { SEMUA: `menunggu ${n} aktivitas selesai`, TERAKHIR: 'menunggu aktivitas terakhir selesai', SEGERA: 'dievaluasi begitu hasil pertama masuk' }[m.tunggu] || 'dievaluasi';
            const lanjut = m.autoLanjut ? 'lalu maju otomatis' : 'lalu menunggu keputusan admin';
            const gugur = m.autoGugur ? ' Ada tes penentu gagal → kandidat langsung gugur.' : '';

            return `Tahap ${tunggu}, ${lanjut}.${gugur}`;
        },

        /**
         * Jaga agar mode terpilih selalu ada di daftar yang ditampilkan.
         * Dipanggil saat tes ditambah/dihapus — mis. admin memilih "tunggu tes
         * terakhir" (hanya masuk akal untuk 2+ tes) lalu menghapus tesnya.
         */
        samakanMode(s) {
            const boleh = this.modeTampil(s);
            if (boleh.some((m) => m.value === s.mode)) return;

            // Mode lama tak lagi ditawarkan (mis. tipe tahap diubah jadi Tes
            // Online, sehingga mode yang gagalnya menggantung tak berlaku lagi).
            // Pilih penggantinya yang PERILAKU MAJU-nya sama, bukan sekadar
            // yang pertama di daftar — jatuh ke "maju otomatis" tanpa diminta
            // akan diam-diam mengubah arti tahap yang sudah disusun admin.
            const lama = this.modeKeputusan.find((m) => m.value === s.mode);
            const sepadan = lama ? boleh.find((m) => m.autoLanjut === lama.autoLanjut) : null;
            s.mode = (sepadan || boleh[0])?.value || null;
        },
        /** Nama pendek mode untuk pil di daftar alur. */
        namaMode(kode) { return this.modeKeputusan.find((m) => m.value === kode)?.nama || kode || 'Manual'; },

        /**
         * Buang baris tes BAWAAN dari data yang dimuat untuk diedit.
         * Bawaan = tepat 1 tes, PENENTU, tanpa jenis tes, dan namanya sama
         * dengan nama tahap — persis yang dibuat backend saat daftar dikosongkan.
         * Tahap yang memang punya beberapa tes tidak tersentuh.
         */
        /**
         * Aktivitas yang PUNYA SETELAN sendiri — bukan sekadar baris bawaan.
         *
         * Dipakai memutuskan apakah satu-satunya aktivitas sebuah tahap boleh
         * disembunyikan dari form. Aktivitas yang meminta unggahan, punya cara
         * penilaian, disembunyikan dari kandidat, atau menunggu pemicu admin
         * membawa keputusan yang TIDAK bisa dibentuk ulang dari label tahap.
         */
        tesPunyaSetelan(x) {
            return x.unggahKandidat === true
                || x.tampilKandidat === false
                // Instruksi untuk kandidat juga setelan milik aktivitas ini —
                // menyembunyikan barisnya berarti tak ada tempat menyuntingnya.
                || this.adaInstruksi(x)
                || !!x.penilaianMode
                || !!x.lanjutMode
                || x.ambang != null
                || (x.peran || 'PENENTU') !== 'PENENTU';
        },

        buangTesBawaan(s) {
            const t = s.tests || [];

            // BARIS BAWAAN = satu aktivitas yang seluruhnya bisa dibentuk ulang
            // dari tahapnya sendiri. Hanya baris seperti itu yang boleh
            // disembunyikan dari form.
            //
            // KENAPA `tesPunyaSetelan` IKUT MENENTUKAN
            // Dulu penilaiannya hanya label + tipe + peran. Aktivitas yang
            // labelnya kebetulan sama dengan tahapnya ikut dibuang walau ia
            // membawa setelan unggahan, penilaian, atau visibilitas. Formnya
            // lalu menyimpan `tests: []`, dan server membuatkan baris bawaan
            // KOSONG sebagai gantinya — seluruh setelan itu lenyap. Setiap kali
            // alur dibuka lalu disimpan, tanpa admin menyentuh apa pun.
            const bawaan = t.length === 1
                && (t[0].peran || 'PENENTU') === 'PENENTU'
                && (t[0].tipe || s.tipe) === s.tipe
                && (t[0].label || '').trim() === (s.label || '').trim()
                && !this.tesPunyaSetelan(t[0]);

            // SELURUH FIELD DIPERTAHANKAN (`...x`).
            //
            // Sebelumnya fungsi ini hanya meneruskan label/tipe/peran/ambang.
            // Sepuluh field lain — unggahKandidat, unggahWajib, unggahFormat,
            // unggahMaksMb, unggahPetunjuk, tampilKandidat, lanjutMode,
            // penilaianMode, penilaianOpsi, nilaiMaks — hilang di sini, sebelum
            // openEdit() sempat menormalkannya. Akibatnya membuka alur untuk
            // diedit sudah cukup untuk menghapus semuanya.
            return bawaan ? [] : t.map((x) => ({
                ...x,
                label: x.label,
                // Tipe DITAMPILKAN APA ADANYA.
                //
                // Sebelumnya dikosongkan bila kebetulan sama dengan tipe tahap,
                // supaya kotaknya terbaca "ikut tahap". Akibatnya: aktivitas
                // "Offering Leter" yang tipenya sengaja dipilih Penawaran — sama
                // dengan tahapnya — kembali kosong setiap kali alur dibuka lagi,
                // dan admin melihatnya sebagai pilihan yang tidak tersimpan.
                // Lebih buruk lagi, mengubah tipe TAHAP diam-diam ikut mengubah
                // tipe seluruh aktivitas yang pernah dipilih sama dengannya.
                tipe: x.tipe || null,
                peran: x.peran || 'PENENTU',
                ambang: x.ambang ?? null,
            }));
        },
        addTest(s) {
            if (!Array.isArray(s.tests)) s.tests = [];
            s.tests.push({ label: '', tipe: null, peran: 'PENENTU', ambang: null,
                unggahKandidat: false, unggahWajib: false,
                unggahFormat: 'pdf,jpg,jpeg,png', unggahMaksMb: this.batasUnggahBawaan(), unggahPetunjuk: '',
                // Bawaannya TERLIHAT. Menyembunyikan aktivitas adalah keputusan
                // sadar; kalau bawaannya tersembunyi, satu aktivitas yang lupa
                // disetel akan hilang dari portal tanpa ada yang menyadarinya.
                tampilKandidat: true,
                lanjutMode: null, penilaianMode: null, penilaianOpsi: '', nilaiMaks: null,
                instruksiHtml: '', tampilBiaya: true, kalimatBiaya: '' });
            this.samakanMode(s);
        },
        removeTest(s, k) {
            s.tests.splice(k, 1);
            this.samakanMode(s);
        },

        /** Muat mode pengumuman AKTIF dari master (Flag_Aktif). */
        async loadModePengumuman() {
            try {
                const res = await axios.get('/api/v1/karir/options/mode-pengumuman', CFG);
                this.modePengumuman = res.data.result || [];
            } catch (e) { this.modePengumuman = []; }
        },
        /** Mode memakai input jeda hari? (mis. TERJADWAL) — dari flag master. */
        modeButuhJeda(kode) { return !!this.modeMap[kode]?.butuhJeda; },
        initials(name) {
            if (!name) return 'SY';
            const p = String(name).trim().split(/\s+/);
            return ((p[0]?.[0] || '') + (p[1]?.[0] || p[0]?.[1] || '')).toUpperCase() || 'SY';
        },
        async load() {
            this.loading = true;
            try {
                // Filter dikirim ke backend — daftar yang kembali sudah tersaring.
                const params = {
                    q: this.filters.q || undefined,
                    kategori: this.filters.kategori || undefined,
                    status: this.filters.status || undefined,
                    dari: this.filters.rentang?.[0] || undefined,
                    sampai: this.filters.rentang?.[1] || undefined,
                };
                const res = await axios.get(API, { ...CFG, params });
                const r = res.data.result || {};
                this.list = r.data || [];
                this.kategoriOpsi = r.kategori || [];
            } catch (e) {
                this.notice('Gagal memuat data alur.');
            } finally {
                this.loading = false;
            }
        },
        /** Ketik di kolom cari → tunggu 400ms lalu request backend. */
        cariDebounce() {
            if (this.cariTimer) clearTimeout(this.cariTimer);
            this.cariTimer = setTimeout(() => this.load(), 400);
        },
        resetFilter() {
            this.filters = { q: '', kategori: null, status: null, rentang: null };
            this.load();
        },
        /**
         * Bersihkan penanda duplikat saat modal ditutup. Tanpa ini, membuka
         * "Alur Baru" sesudah membatalkan sebuah duplikat akan tetap memakai
         * judul "Duplikat Alur Seleksi" — layar yang berbohong tentang apa
         * yang sedang dikerjakan.
         */
        tutupModal() {
            this.show = false;
            this.duplikatDari = null;
        },
        openCreate() {
            this.editingId = null;
            this.duplikatDari = null;
            this.form = { nama: '', kategori: '', deskripsi: '', stages: [], talentPoolMulai: 0, tuntasTahap: 0 };
            // Hak akses cuma satu kategori -> langsung dipasang. Tanpa ini borang
            // menampilkan pil terkunci berisi nama kategorinya, tapi nilainya tetap
            // kosong — dan penyimpanan ditolak "Kategori wajib dipilih" untuk
            // pilihan yang memang tidak pernah ditawarkan kepadanya.
            if (this.kategoriTunggal) this.form.kategori = this.kategoriTunggal.kode;
            this.show = true;
        },
        /**
         * Bentuk isi modal dari satu baris alur — dipakai Ubah DAN Duplikat.
         *
         * Sengaja satu fungsi untuk keduanya. Kalau pemetaannya disalin, tiap
         * field baru harus diingat dua kali; yang terlupa pada salinan tidak
         * memunculkan galat apa pun — ia cuma hilang diam-diam dari alur hasil
         * duplikat, dan baru ketahuan saat satu angkatan sudah berjalan dengan
         * setelan yang berbeda dari yang disalin.
         */
        formDariAlur(a) {
            const stages = (a.stages || []).map((s) => ({
                // IDENTITAS TAHAP — dibawa pulang-pergi, tidak pernah ditampilkan.
                //
                // Inilah yang membuat mengganti judul tahap TIDAK memindahkan
                // kandidat yang sedang menjalaninya. Server mencocokkan baris
                // lewat kode ini; tanpa dikirim balik, ia jatuh ke pencocokan
                // nomor urut — dan memindahkan urutan tahap akan menukar arti
                // dua baris sekaligus.
                //
                // Pada DUPLIKAT alur kode ini ikut terbawa, dan itu memang
                // diinginkan: dua alur yang sama-sama punya "PSIKOTES" digambar
                // sebagai satu kolom di papan worklist (lihat AlurKolom::susun),
                // sebab bagi tim rekrutmen itu memang satu tahap yang sama.
                kode: s.kode || null,
                label: s.label,
                tipe: s.tipe,
                mode: s.mode || 'MANUAL_REVIEW',
                formulirId: s.formulirId ?? null,
                pengumuman: s.pengumuman || 'OTOMATIS',
                jedaHari: s.jedaHari ?? null,
                notifikasi: s.notifikasi !== false,
                talentPool: s.talentPool === true,
                uploadHasil: s.uploadHasil === true,
                wajibUpload: s.wajibUpload === true,
                tuntas: s.tuntas === true,
                // Batas pengisian formulir (lihat BatasIsi di server).
                batasMode: s.batasMode || 'TANPA',
                batasHari: s.batasHari ?? null,
                urutanAktivitas: s.urutanAktivitas || null,
                // SATU kunci `tests`, bukan dua.
                //
                // Sebelumnya objek ini punya `tests:` dua kali — buangTesBawaan()
                // lebih dulu, lalu daftar yang dinormalkan. Dalam JavaScript
                // kunci terakhir yang menang, jadi buangTesBawaan() tidak pernah
                // benar-benar berjalan: baris tes bawaan tetap muncul saat alur
                // dibuka untuk diedit, persis yang komentarnya bilang dihindari.
                //
                // Keduanya digabung: buang baris bawaan DULU, baru normalkan.
                // Normalisasi tetap perlu karena el-switch yang menerima
                // undefined tampak mati padahal nilainya belum tentu false.
                tests: this.buangTesBawaan(s).map((t) => ({
                    ...t,
                    unggahKandidat: t.unggahKandidat === true,
                    unggahWajib: t.unggahWajib === true,
                    unggahFormat: t.unggahFormat || 'pdf,jpg,jpeg,png',
                    // `||` bukan `??` disengaja: 0 bukan batas yang sah.
                    unggahMaksMb: t.unggahMaksMb || this.batasUnggahBawaan(),
                    unggahPetunjuk: t.unggahPetunjuk || '',
                    // `!== false` — alur lama tidak punya field ini sama sekali,
                    // dan `undefined` harus berarti TERLIHAT (perilaku selama ini),
                    // bukan tersembunyi.
                    tampilKandidat: t.tampilKandidat !== false,
                    lanjutMode: t.lanjutMode || null,
                    penilaianMode: t.penilaianMode || null,
                    penilaianOpsi: t.penilaianOpsi || '',
                    nilaiMaks: t.nilaiMaks || null,
                    instruksiHtml: t.instruksiHtml || '',
                    // `!== false` — alur lama tanpa kunci ini tetap menampilkan biaya.
                    tampilBiaya: t.tampilBiaya !== false,
                    kalimatBiaya: t.kalimatBiaya || '',
                })),
            }));
            // Turunkan titik cut-off dari data: tahap PERTAMA yang talentPool aktif.
            const idx = stages.findIndex((s) => s.talentPool);
            // Titik tuntas juga diturunkan dari data, bukan disimpan sebagai
            // field tersendiri — sumbernya tetap satu: tahap mana yang
            // ber-Flag_Tuntas. Alur lama yang belum punya titik tuntas terbuka
            // dengan pemilih KOSONG, dan tombol simpan menahan sampai diisi:
            // alur seperti itu memang tidak pernah bisa menyatakan siapa pun
            // DITERIMA, jadi membiarkannya lolos lagi hanya memperpanjang
            // kesalahan yang sudah berjalan.
            const idxTuntas = stages.findIndex((s) => s.tuntas);
            return {
                nama: a.nama,
                kategori: a.kategori,
                deskripsi: a.deskripsi || '',
                stages,
                talentPoolMulai: idx >= 0 ? idx + 1 : 0,
                tuntasTahap: idxTuntas >= 0 ? idxTuntas + 1 : 0,
            };
        },
        openEdit(a) {
            this.editingId = a.id;
            this.duplikatDari = null;
            this.form = this.formDariAlur(a);
            this.show = true;
        },
        /**
         * DUPLIKAT — buka modal BUAT dengan seluruh isi alur sumber sudah
         * terpasang: identitas, semua tahap, semua aktivitas, sampai titik
         * tuntas & cut-off Talent Pool. Semuanya tetap bisa disunting, ditambah,
         * atau dihapus sebelum disimpan.
         *
         * Tidak ada endpoint "duplicate" di server, dan itu disengaja. Alur
         * hasil duplikat lahir lewat store() yang sama dengan alur baru mana
         * pun, jadi seluruh gerbangnya ikut berlaku: wajib satu titik tuntas,
         * formulir yang belum publish ditolak, mode keputusan diperbaiki.
         * Endpoint penyalin sendiri akan menempuh jalur lain yang lambat laun
         * berbeda aturannya — dan salinan yang lolos gerbang bisa langsung
         * dipakai satu angkatan sebelum ada yang sadar.
         */
        openDuplicate(a) {
            this.editingId = null;
            this.duplikatDari = a.nama;
            this.form = this.formDariAlur(a);
            this.form.nama = this.namaSalinan(a.nama);
            this.show = true;
        },
        /**
         * Usulkan nama salinan yang belum terpakai: "X (Salinan)", lalu
         * "X (Salinan 2)", dan seterusnya.
         *
         * Nama dibedakan sejak awal karena dua alur bernama sama di daftar
         * penjadwalan tidak bisa dibedakan sama sekali — dan yang salah pilih
         * baru ketahuan setelah kandidat masuk ke alur yang keliru.
         */
        namaSalinan(nama) {
            const dasar = String(nama || 'Alur').trim();
            const dipakai = new Set(this.list.map((x) => (x.nama || '').trim().toLowerCase()));
            let calon = `${dasar} (Salinan)`;
            for (let n = 2; dipakai.has(calon.toLowerCase()) && n < 100; n += 1) {
                calon = `${dasar} (Salinan ${n})`;
            }
            // Kolom Nama dibatasi 120 karakter di server; potong dari nama dasar
            // supaya penanda "(Salinan)" tidak ikut terpangkas dan salinannya
            // berakhir bernama persis sama dengan sumbernya.
            if (calon.length > 120) {
                const sufiks = calon.slice(dasar.length);
                calon = dasar.slice(0, 120 - sufiks.length).trim() + sufiks;
            }
            return calon;
        },
        /** Tahap ke-i (0-based) termasuk cut-off Talent Pool? (dari titik mulai sampai akhir). */
        tahapTalentPool(i) { return this.form.talentPoolMulai > 0 && (i + 1) >= this.form.talentPoolMulai; },
        /**
         * hanyaSatuTuntas() DIHAPUS bersama sakelar per tahapnya.
         *
         * Aturan "hanya boleh satu" kini dijamin oleh BENTUKNYA: `tuntasTahap`
         * menyimpan satu nomor tahap, sehingga dua titik tuntas mustahil ada —
         * bukan lagi sesuatu yang harus dijaga dengan mematikan sakelar lain
         * setiap kali admin menyalakan yang baru.
         */
        addStage() { this.form.stages.push({ label: '', tipe: '', mode: 'MANUAL_REVIEW', formulirId: null, tests: [], pengumuman: 'OTOMATIS', jedaHari: null, notifikasi: true, uploadHasil: false, wajibUpload: false, batasMode: 'TANPA', batasHari: null }); },
        removeStage(i) { this.form.stages.splice(i, 1); },

        // Label & ikon pil diambil dari master (fallback ke kode bila belum termuat).
        labelPengumuman(kode) { return this.modeMap[kode]?.nama || this.modeMap[kode]?.label || kode || 'Otomatis'; },
        ikonPengumuman(kode) { return this.modeMap[kode]?.ikon || 'bi-megaphone'; },

        /** Kalimat konsekuensi — deskripsi diambil dari master, jeda dari flag. */
        catatanPengumuman(s) {
            const notif = s.notifikasi !== false ? 'Kandidat diberi tahu lewat email.' : 'Kandidat TIDAK diberi tahu.';
            const mode = this.modeMap[s.pengumuman];
            const dasar = mode?.deskripsi || 'Atur kapan hasil tahap ini terlihat kandidat.';
            if (this.modeButuhJeda(s.pengumuman)) {
                const jeda = s.jedaHari != null && s.jedaHari !== '' ? ` Saat menjadwalkan angkatan, tanggal disarankan ${s.jedaHari} hari setelah tahap selesai — tetap bisa diubah.` : ' Tanggal pastinya diisi saat menjadwalkan angkatan.';
                return `${dasar}${jeda} ${notif}`;
            }
            return `${dasar} ${notif}`;
        },
        moveStage(i, dir) {
            const j = i + dir;
            if (j < 0 || j >= this.form.stages.length) return;
            const arr = this.form.stages;
            [arr[i], arr[j]] = [arr[j], arr[i]];
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama alur wajib diisi.');
            if (!this.form.kategori) return this.notice('Kategori wajib dipilih.');
            if (this.form.stages.some((s) => !s.label || !s.label.trim())) return this.notice('Setiap tahap wajib punya label.');
            if (this.form.stages.some((s) => !s.tipe)) return this.notice('Setiap tahap wajib punya tipe.');
            // TITIK TUNTAS WAJIB. Alur tanpa penanda ini tidak pernah bisa
            // menyatakan kandidat DITERIMA dan kuotanya tidak pernah terpotong —
            // kesalahan yang tidak menimbulkan galat apa pun dan baru ketahuan
            // berbulan-bulan kemudian, saat ada yang menghitung kursi terisi.
            if (this.form.stages.length && !this.form.tuntasTahap) {
                return this.notice('Pilih dulu tahap yang menutup proses seleksi — tanpa itu kandidat tidak pernah dinyatakan DITERIMA.');
            }
            this.saving = true;
            const payload = {
                nama: this.form.nama,
                kategori: this.form.kategori,
                deskripsi: this.form.deskripsi,
                stages: this.form.stages.map((s, i) => ({
                    // Identitas tahap — lihat formDariAlur(). Tahap yang baru
                    // ditambahkan di layar tidak punya kode, dan server yang
                    // membangkitkannya.
                    kode: s.kode || null,
                    label: s.label,
                    tipe: s.tipe,
                    mode: s.mode || null,
                    // Provider tak dikirim — backend menurunkannya dari sub-tes.
                    formulirId: this.butuhFormulir(s) ? (s.formulirId || null) : null,
                    // Tiap aktivitas membawa TIPE-nya sendiri; kosong = ikut tahap.
                    tests: (s.tests || []).filter((t) => (t.label || '').trim()).map((t) => ({
                        label: t.label,
                        tipe: t.tipe || null,
                        peran: t.peran || 'PENENTU',
                        unggahKandidat: t.unggahKandidat === true,
                        unggahWajib: t.unggahWajib === true,
                        unggahFormat: t.unggahFormat || null,
                        unggahMaksMb: t.unggahMaksMb || null,
                        unggahPetunjuk: t.unggahPetunjuk || null,
                        tampilKandidat: this.tampilKandidat(t, s),
                        // Mode penilaian hanya untuk aktivitas yang dikerjakan tim.
                        lanjutMode: t.lanjutMode || this.lanjutBawaan(),
                        penilaianMode: this.tesOnline(t.tipe || s.tipe) ? null : (t.penilaianMode || this.penilaianBawaan()),
                        penilaianOpsi: t.penilaianOpsi || null,
                        nilaiMaks: t.nilaiMaks || null,
                        ambang: t.ambang ?? null,
                        // Instruksi hanya untuk aktivitas berjadwal yang dibaca
                        // kandidat; selebihnya dikosongkan supaya sisa ketikan dari
                        // tipe sebelumnya tidak ikut terkirim diam-diam.
                        instruksiHtml: this.bolehInstruksi(t, s) && this.adaInstruksi(t) ? t.instruksiHtml : null,
                        tampilBiaya: t.tampilBiaya !== false,
                        // Kosong = kalimat bawaan tipe.
                        kalimatBiaya: (t.kalimatBiaya || '').trim() || null,
                    })),
                    // Hanya berarti bila aktivitasnya lebih dari satu — server
                    // pun memaksanya ke mode tak-mengunci bila tidak.
                    urutanAktivitas: (s.tests || []).filter((t) => (t.label || '').trim()).length > 1
                        ? this.urutanTerpilih(s)
                        : this.urutanBawaan(),
                    pengumuman: s.pengumuman || 'OTOMATIS',
                    // Jeda hanya bermakna untuk mode ber-flag butuhJeda; lainnya null.
                    jedaHari: this.modeButuhJeda(s.pengumuman) ? (s.jedaHari ?? null) : null,
                    notifikasi: s.notifikasi !== false,
                    // Cut-off Talent Pool DITURUNKAN dari satu titik (talentPoolMulai):
                    // tahap ke-N sampai akhir → 'Y'. Tak lagi per-tahap manual.
                    talentPool: this.tahapTalentPool(i),
                    // Tipe yang sakelarnya disembunyikan tidak membawa setelan
                    // per tahap; server menerapkan aturan yang sama.
                    uploadHasil: this.opsiUpload(s) && s.uploadHasil === true,
                    wajibUpload: this.opsiUpload(s) && s.wajibUpload === true,
                    // DITURUNKAN dari satu pemilih, sepola dengan cut-off Talent
                    // Pool — tidak lagi disetel per tahap.
                    tuntas: this.form.tuntasTahap === i + 1,
                    // Jadwal pengisian — setiap tahap bertipe Formulir, termasuk
                    // tahap 1; server menerapkan aturan yang sama (isiBatas).
                    batasMode: this.butuhFormulir(s) ? (s.batasMode || 'TANPA') : 'TANPA',
                    batasHari: this.butuhFormulir(s) && s.batasMode === 'OTOMATIS' ? (s.batasHari || null) : null,
                })),
            };
            try {
                if (this.editingId) {
                    // ── ALUR YANG SEDANG DIPAKAI = LAHIR VERSI BARU ─────────
                    //
                    // Ditanyakan DULU ke server: berapa kandidat berjalan yang
                    // masih punya tempat di susunan baru, dan siapa yang tidak
                    // beserta sebabnya. Menyimpan lebih dulu lalu memberi tahu
                    // akibatnya sesudahnya berarti memberitahu saat sudah tidak
                    // bisa dibatalkan.
                    const dampak = await this.hitungDampak(payload.stages);

                    if (dampak?.berversi) {
                        this.dampak = dampak;
                        this.dampakPayload = payload;
                        this.dampakShow = true;
                        this.saving = false;

                        return; // dilanjutkan simpanDenganPilihan()
                    }

                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Alur diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice(this.duplikatDari
                        ? `Salinan dibuat — "${this.form.nama}". Alur "${this.duplikatDari}" tidak berubah.`
                        : 'Alur ditambahkan.');
                }
                this.tutupModal();
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        /**
         * Berapa kandidat berjalan yang ikut pindah bila susunan ini disimpan.
         *
         * Gagal menghitung TIDAK menghentikan penyimpanan: server tetap
         * memutuskan sendiri apakah versi baru perlu dilahirkan. Yang hilang
         * cuma pratinjaunya — dan menahan pekerjaan admin karena satu panel
         * keterangan tak bisa dimuat jauh lebih merugikan.
         */
        async hitungDampak(stages) {
            try {
                const res = await axios.get(`${API}/${this.editingId}/dampak`, {
                    ...CFG,
                    params: { kode: stages.map((s) => s.kode).filter(Boolean) },
                });

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
                this.notice(res.data.message || 'Alur diperbarui.');
                this.tutupModal();
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(a, v) {
            const prev = a.status;
            a.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${a.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Alur "${a.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                a.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(a) { this.delTarget = a; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const a = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${a.id}`, CFG);
                this.notice('Alur dihapus.');
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
.alr-head-act { display: inline-flex; align-items: center; gap: .4rem; margin-left: auto; }

/* Kategori yang sudah pasti — KETERANGAN, bukan kendali.
   Sengaja tidak menyerupai kotak input: bingkai kosong dengan satu nilai di
   dalamnya terbaca sebagai input yang mati, dan orang mencoba mengkliknya.
   Pilnya dibuat sama dengan pil kategori di daftar alur supaya sekali lihat
   sudah terbaca sebagai hal yang sama. */
.alr-kat-tetap { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; padding: .15rem 0; }
.alr-kat-tetap small { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 700; color: var(--muted); }

/* ── FILTER PANEL — card di desktop, bottom-sheet via FAB di mobile ── */
.alr-filter { background: #fff; border: 1px solid rgba(15, 23, 42, .08); border-radius: 16px; padding: .9rem 1rem 1rem; margin-bottom: 1rem; box-shadow: 0 8px 24px rgba(15, 23, 42, .04); }
.alr-filter__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .65rem; }
.alr-filter__title { display: inline-flex; align-items: center; gap: .45rem; font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #4338ca; }
.alr-filter__act { display: inline-flex; align-items: center; gap: .4rem; }
.alr-filter__reset { display: inline-flex; align-items: center; gap: .3rem; border: 1px solid rgba(79, 70, 229, .25); background: #eef2ff; color: #4338ca; font-size: 11.5px; font-weight: 700; border-radius: 9px; padding: 4px 10px; cursor: pointer; }
.alr-filter__reset:hover { background: #e0e7ff; }
.alr-filter__close { display: none; border: none; background: transparent; color: #64748b; font-size: 15px; cursor: pointer; padding: 4px; }
.alr-filter__grid { display: grid; grid-template-columns: minmax(200px, 1.4fr) 1fr 1fr 1.4fr; gap: .7rem; align-items: end; }
@media (max-width: 960px) { .alr-filter__grid { grid-template-columns: 1fr 1fr; } }

/* FAB — hanya mobile */
.alr-fab { display: none; position: fixed; right: 18px; bottom: 20px; z-index: 70; width: 52px; height: 52px; border-radius: 50%; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 19px; cursor: pointer; box-shadow: 0 12px 28px rgba(99, 102, 241, .45); }
.alr-fab__badge { position: absolute; top: -4px; right: -4px; min-width: 19px; height: 19px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 10.5px; font-weight: 800; display: grid; place-items: center; padding: 0 5px; border: 2px solid #fff; }
.alr-sheetbg { display: none; }

@media (max-width: 640px) {
    /* Panel disembunyikan; FAB membukanya sebagai bottom-sheet. */
    .alr-filter { display: none; }
    .alr-filter.is-open { display: block; position: fixed; left: 0; right: 0; bottom: 0; z-index: 80; margin: 0; border-radius: 18px 18px 0 0; box-shadow: 0 -18px 40px rgba(15, 23, 42, .25); max-height: 78vh; overflow-y: auto; }
    .alr-filter__grid { grid-template-columns: 1fr; }
    .alr-filter__close { display: inline-flex; }
    .alr-fab { display: grid; place-items: center; }
    .alr-sheetbg { display: block; position: fixed; inset: 0; z-index: 75; background: rgba(15, 23, 42, .45); }
}
.alr-meta { margin-bottom: .8rem; }
.alr-empty-stage { color: #94a3b8; font-size: 13px; }

/* Pemberitahuan asal salinan di puncak modal Duplikat. Nada amber, bukan
   indigo seperti blok informasi lain: ini bukan keterangan yang boleh
   terlewat dibaca — ia satu-satunya tanda bahwa Simpan akan MELAHIRKAN alur
   baru, bukan memperbarui yang sedang tampak isinya. */
.alr-dupnote { display: flex; align-items: flex-start; gap: .55rem; margin: 0 0 1rem; padding: .7rem .85rem; border-radius: 12px; border: 1px solid rgba(217, 119, 6, .28); background: linear-gradient(180deg, rgba(245, 158, 11, .09), rgba(245, 158, 11, .04)); font-size: 12px; line-height: 1.6; color: #78350f; }
.alr-dupnote > i { flex: none; margin-top: .1rem; font-size: 14px; color: #b45309; }
.alr-dupnote b { font-weight: 800; }

/* Keterangan tipe tahap yang sudah dipensiunkan — muncul tepat di bawah
   kotak pilihannya, bukan sebagai ringkasan di puncak modal: pada alur 9
   tahap, peringatan yang jauh dari tempatnya menyuruh orang mencari sendiri
   tahap mana yang dimaksud. */
.alr-usang { display: flex; align-items: flex-start; gap: .35rem; margin-top: .35rem; font-size: .72rem; line-height: 1.5; font-weight: 600; color: #b45309; }
.alr-usang > i { flex: none; margin-top: .15rem; }
.alr-usang b { font-weight: 800; }

/* Blok "cara tahap menyimpulkan" — diletakkan SETELAH daftar tes karena ia
   adalah kesimpulan atas tes-tes tersebut. Diberi nada indigo agar terbaca
   sebagai keputusan, bukan sekadar isian tambahan. */
.alr-dec { margin-top: .55rem; padding: .7rem .8rem; border-radius: 12px; border: 1px solid rgba(79, 70, 229, .18); background: linear-gradient(180deg, rgba(79, 70, 229, .05), rgba(124, 58, 237, .04)); }
.alr-dec.is-multi { border-color: rgba(79, 70, 229, .34); box-shadow: 0 4px 14px rgba(79, 70, 229, .08); }
.alr-dec__head { display: flex; align-items: center; gap: .4rem; font-size: 11.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #4338ca; margin-bottom: .5rem; }
.alr-dec__count { margin-left: auto; text-transform: none; letter-spacing: 0; font-size: 10.5px; font-weight: 700; color: #4338ca; background: rgba(79, 70, 229, .1); border-radius: 999px; padding: 1px 8px; }
.alr-dec__note { display: flex; align-items: flex-start; gap: .4rem; margin: .5rem 0 0; font-size: 11.5px; line-height: 1.55; color: #64748b; }
.alr-dec__note > i { color: #4338ca; margin-top: 1px; flex: none; }

/* Blok daftar tes (sub-tes) di dalam kartu tahap pada modal builder. */
.alr-tests { border: 1px solid rgba(15, 23, 42, .1); border-radius: 12px; padding: .7rem .8rem; margin-top: .5rem; background: #f8fafc; }
.alr-tests__head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; font-size: 11.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #334155; margin-bottom: .55rem; }
.alr-tests__opt { font-weight: 600; color: #94a3b8; text-transform: none; letter-spacing: 0; margin-left: .25rem; }
.alr-tests__empty { display: flex; align-items: flex-start; gap: .45rem; font-size: 11.5px; line-height: 1.6; color: #475569; padding: .5rem .6rem; border-radius: 9px; background: rgba(16, 185, 129, .07); border: 1px solid rgba(16, 185, 129, .2); }
.alr-tests__empty > i { color: #059669; font-size: 13px; margin-top: 1px; flex: none; }
.alr-test { display: flex; gap: .55rem; align-items: flex-start; padding: .6rem; border: 1px solid rgba(15, 23, 42, .08); border-radius: 10px; background: #fff; margin-bottom: .5rem; }
.alr-test__no { flex: none; width: 1.4rem; height: 1.4rem; border-radius: 50%; background: #e0e7ff; color: #4338ca; font-size: 11px; font-weight: 800; display: grid; place-items: center; }
.alr-test__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .45rem; }
/* ═══ URUTAN PENGERJAAN AKTIVITAS ═══ */
.alr-urut { margin: 4px 0 12px; padding: 11px 12px; border-radius: 12px; background: #f8fafc; border: 1px solid #e8ebf3; }
.alr-urut__head { display: flex; align-items: center; gap: 7px; margin-bottom: 9px; font-size: 12px; font-weight: 800; color: #334155; }
.alr-urut__head i { color: #6366f1; }
.alr-urut__opts { display: flex; flex-direction: column; gap: 7px; }
.alr-urut__opt { display: flex; align-items: flex-start; gap: 9px; width: 100%; padding: 9px 11px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: #fff; cursor: pointer; text-align: left; transition: border-color .15s, background .15s; }
.alr-urut__opt:hover { border-color: #cbd5e1; }
.alr-urut__ico { flex: none; display: grid; place-items: center; width: 26px; height: 26px; border-radius: 8px; background: #f1f5f9; font-size: 13px; }
.alr-urut__opt b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; }
.alr-urut__opt small { display: block; margin-top: 2px; font-size: 11.5px; line-height: 1.5; color: #64748b; }

/* ═══ TERLIHAT KANDIDAT ATAU INTERNAL ═══ */
.alr-lihat { display: flex; align-items: flex-start; gap: 10px; margin-top: 10px; padding: 9px 11px; border-radius: 12px; border: 1px solid #e6f0ea; background: #f6fbf8; cursor: pointer; }
.alr-lihat b { display: flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 800; color: #15803d; }
.alr-lihat small { display: block; margin-top: 2px; font-size: 11.5px; line-height: 1.5; color: #64748b; }
/* Internal — nadanya "sengaja disembunyikan", bukan "ada yang salah". */
.alr-lihat.is-off { border-color: #e6e9f0; background: #f7f8fb; }
.alr-lihat.is-off b { color: #475569; }
.alr-lihat.is-locked { cursor: not-allowed; opacity: .85; }

/* ═══ PERPINDAHAN KE AKTIVITAS BERIKUTNYA ═══ */
.alr-lanjut { margin-top: 10px; padding: 10px 11px; border-radius: 12px; border: 1px solid #ecebf7; background: #fbfaff; }
.alr-lanjut__head { display: flex; align-items: center; gap: 7px; margin-bottom: 8px; font-size: 12px; font-weight: 800; color: #334155; }
.alr-lanjut__head i { color: #7c3aed; }
.alr-lanjut__opts { display: flex; flex-direction: column; gap: 6px; }
.alr-lanjut__opt { display: flex; align-items: flex-start; gap: 9px; width: 100%; padding: 8px 10px; border-radius: 10px; border: 1.5px solid #e6e3f5; background: #fff; cursor: pointer; text-align: left; }
.alr-lanjut__opt:hover { border-color: #c4b5fd; }
.alr-lanjut__opt.is-on { border-color: #8b5cf6; background: rgba(139, 92, 246, .06); }
.alr-lanjut__ico { flex: none; display: grid; place-items: center; width: 24px; height: 24px; border-radius: 8px; background: #f4f2fd; font-size: 12px; }
.alr-lanjut__opt b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; }
.alr-lanjut__opt small { display: block; margin-top: 2px; font-size: 11px; line-height: 1.5; color: #64748b; }

/* ═══ MODE PENILAIAN AKTIVITAS ═══ */
.alr-nilai { margin-top: 10px; padding: 10px 11px; border-radius: 12px; border: 1px solid #e6ecf5; background: #f7fafd; }
.alr-nilai__head { display: flex; align-items: center; gap: 7px; margin-bottom: 8px; font-size: 12px; font-weight: 800; color: #334155; }
.alr-nilai__head i { color: #0891b2; }
.alr-nilai__opt { display: flex; flex-direction: column; line-height: 1.35; padding: 2px 0; }
.alr-nilai__opt b { font-size: 12.5px; color: #1e293b; }
.alr-nilai__opt small { font-size: 11px; color: #94a3b8; white-space: normal; }
.alr-nilai__ket { display: block; margin-top: .3rem; font-size: 11px; line-height: 1.5; color: #64748b; }

/* Panel pemeriksaan — menggantikan pemilih "cara dinilai" pada reference &
   background check. Warnanya sengaja beda dari kotak penilaian: yang ini
   keterangan, bukan sesuatu yang perlu disetel. */
.alr-periksa { margin-top: 10px; padding: 10px 11px; border-radius: 12px; border: 1px solid #dbeafe; background: #f5f9ff; }
.alr-periksa__head { display: flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 800; color: #1e40af; }
.alr-periksa__head i { color: #2563eb; }
.alr-periksa__ket { margin: 6px 0 0; font-size: 11.5px; line-height: 1.55; color: #475569; }
.alr-periksa__ket b { color: #1e293b; }
.alr-periksa__ket.is-samar { display: flex; gap: 6px; margin-top: 7px; padding-top: 7px; border-top: 1px dashed #dbeafe; color: #64748b; }
.alr-periksa__ket.is-samar i { flex: none; margin-top: 2px; }

.alr-unggah { margin-top: 10px; border: 1px solid #eef0f7; border-radius: 12px; background: #fbfbfe; transition: border-color .18s, background .18s; }
.alr-unggah.is-on { border-color: rgba(99, 102, 241, .32); background: rgba(99, 102, 241, .05); }
.alr-unggah__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 13px; }
.alr-unggah__head b { display: block; font-size: 12.5px; font-weight: 800; color: #334155; }
.alr-unggah__head small { display: block; font-size: 11px; color: #94a3b8; margin-top: 2px; }
.alr-unggah__body { padding: 0 13px 13px; border-top: 1px dashed rgba(99, 102, 241, .22); padding-top: 12px; }
.alr-unggah__wajib { display: flex; align-items: center; gap: 9px; margin-top: 10px; font-size: 12px; color: #475569; }
.alr-biaya__hint { display: block; margin-top: 4px; font-size: 11px; color: #94a3b8; }
.alr-instruksi__buka { flex: none; display: inline-flex; align-items: center; gap: 5px; padding: 6px 11px; border-radius: 9px; border: 1px solid #c7d2fe; background: #fff; font-size: 11.5px; font-weight: 800; color: #4f46e5; cursor: pointer; }
.alr-instruksi__buka:hover { background: #eef2ff; }
.alr-unggah__wajib b { color: #dc2626; }

/* BATAS PENGISIAN FORMULIR — sepola dengan kotak unggah di atas. */
.alr-batas { margin-top: 12px; border: 1px solid #eef0f7; border-radius: 12px; background: #fbfbfe; transition: border-color .18s, background .18s; }
.alr-batas.is-on { border-color: rgba(245, 158, 11, .4); background: rgba(245, 158, 11, .05); }
.alr-batas__head { padding: 11px 13px 8px; }
.alr-batas__head b { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 800; color: #334155; }
.alr-batas__head small { display: block; font-size: 11px; color: #94a3b8; margin-top: 2px; }
.alr-batas__note { display: flex; align-items: flex-start; gap: 7px; margin: 0 13px 10px; padding: 8px 10px; border-radius: 9px; font-size: 11.5px; line-height: 1.55; color: #3730a3; background: rgba(99, 102, 241, .07); border: 1px solid rgba(99, 102, 241, .18); }
.alr-batas__note .bi { flex: none; margin-top: 2px; }
.alr-batas__opsi { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; padding: 0 13px 12px; }
.alr-batas__opt {
    appearance: none; font: inherit; cursor: pointer; text-align: left;
    display: flex; align-items: flex-start; gap: 8px; padding: 9px 10px; border-radius: 10px;
    border: 1px solid #e5e7f0; background: #fff; color: #475569; transition: all .16s;
}
.alr-batas__opt .bi { flex: none; margin-top: 1px; font-size: 14px; color: #94a3b8; }
.alr-batas__opt b { display: block; font-size: 12px; font-weight: 800; color: #334155; }
.alr-batas__opt small { display: block; margin-top: 1px; font-size: 10.5px; line-height: 1.4; color: #94a3b8; }
.alr-batas__opt:hover { border-color: #c7d2fe; }
.alr-batas__opt.is-on { border-color: #f59e0b; background: #fffbeb; box-shadow: 0 0 0 3px rgba(245, 158, 11, .12); }
.alr-batas__opt.is-on .bi { color: #d97706; }
.alr-batas__row { display: grid; gap: 6px; padding: 10px 13px 12px; border-top: 1px dashed rgba(245, 158, 11, .3); }
.alr-batas__row > small { font-size: 11px; color: #94a3b8; }
@media (max-width: 640px) {
    .alr-batas__opsi { grid-template-columns: 1fr; }
}
.alr-test__flags { display: flex; align-items: center; gap: .7rem; flex-wrap: wrap; padding-top: 1.35rem; }
.alr-test__prov { display: inline-flex; align-items: center; gap: .3rem; font-size: 11px; font-weight: 700; }
.alr-test__prov.is-sys { color: #b45309; }
.alr-test__prov.is-man { color: #4338ca; }

/* Daftar sub-tes ringkas di tampilan daftar alur (read-only). */
.alr-subtes { list-style: none; margin: .5rem 0 0; padding: 0; display: flex; flex-direction: column; gap: .25rem; }
.alr-subtes li { display: flex; align-items: center; gap: .4rem; font-size: 11.5px; color: #475569; flex-wrap: wrap; }
.alr-subtes li.is-info { color: #64748b; }
.alr-subtes__tes { background: #eef2ff; color: #4338ca; border-radius: 999px; padding: .05rem .4rem; font-weight: 700; font-size: 10.5px; }
.alr-subtes__peran { background: #f1f5f9; color: #475569; border-radius: 999px; padding: .05rem .4rem; font-size: 10.5px; font-weight: 700; }
.alr-subtes li.is-info .alr-subtes__peran { background: #f8fafc; color: #94a3b8; }
.alr-subtes__opt { color: #94a3b8; font-size: 10.5px; font-style: italic; }
.alr-pill.is-mode { color: #4338ca; background: rgba(79, 70, 229, .1); }

/* Blok pengumuman di dalam kartu tahap — dibedakan agar terbaca sebagai
   aturan perilaku, bukan sekadar field tambahan. */
.alr-ann { border: 1px solid rgba(79, 70, 229, .16); border-radius: 12px; background: linear-gradient(180deg, rgba(79, 70, 229, .05), rgba(124, 58, 237, .04)); padding: .7rem .8rem; margin-top: .2rem; }
.alr-ann__head { display: flex; align-items: center; gap: .4rem; font-size: 11.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #4338ca; margin-bottom: .55rem; }
.alr-ann__check { display: block; margin-top: .5rem; }
.alr-ann__note { margin: .45rem 0 0; font-size: 11.5px; line-height: 1.55; color: #64748b; }

/* Pil ringkas di daftar alur. Warna mengikuti sifat: hijau = langsung terbit,
   indigo = menunggu tanggal, amber = menunggu tindakan admin. */
.alr-pill { display: inline-flex; align-items: center; gap: .3rem; font-size: 11px; font-weight: 600; border-radius: 999px; padding: .12rem .5rem; }
.alr-pill.is-otomatis { color: #047857; background: rgba(16, 185, 129, .12); }
.alr-pill.is-terjadwal { color: #4338ca; background: rgba(79, 70, 229, .12); }
.alr-pill.is-manual { color: #b45309; background: rgba(245, 158, 11, .14); }
.alr-pill.is-mute { color: #64748b; background: #f1f5f9; }
.alr-pill.is-tuntas { color: #059669; background: rgba(16, 185, 129, .12); }
.alr-pill.is-talent { color: #a16207; background: rgba(234, 179, 8, .16); }

/* Kartu switch Cut-off Talent Pool di dalam editor tahap. Netral saat mati,
   menyala kuning-emas saat aktif agar terbaca sebagai "jalur khusus". */
.alr-tp { display: flex; align-items: center; gap: .8rem; justify-content: space-between; margin-top: .5rem; padding: .7rem .8rem; border-radius: 12px; border: 1px dashed rgba(15, 23, 42, .16); background: #f8fafc; transition: background .2s, border-color .2s; }
.alr-tp.is-on { border-style: solid; border-color: rgba(234, 179, 8, .5); background: linear-gradient(180deg, rgba(234, 179, 8, .1), rgba(245, 158, 11, .05)); }
.alr-tp__main { display: flex; align-items: flex-start; gap: .55rem; min-width: 0; }
.alr-tp__ico { flex: none; width: 1.9rem; height: 1.9rem; border-radius: 9px; display: grid; place-items: center; background: #fff7ed; color: #d97706; font-size: 15px; border: 1px solid rgba(234, 179, 8, .3); }
.alr-tp.is-on .alr-tp__ico { background: #fde68a; color: #92400e; }
.alr-tp__txt { min-width: 0; }
.alr-tp__txt b { display: block; font-size: 12.5px; color: #1e293b; }
.alr-tp__txt small { display: block; font-size: 11px; line-height: 1.5; color: #64748b; margin-top: .1rem; }
.alr-tp__txt em { color: #b45309; font-style: normal; font-weight: 700; }

/* Cut-off Talent Pool — SATU titik di atas daftar tahap. */
/* ═══ PERATURAN TAMBAHAN — aturan setingkat alur (menunjuk satu tahap) ═══

   Latarnya SENGAJA netral, bukan berwarna seperti sebelumnya. Kotak bernuansa
   kuning/hijau membuat kotak isian di dalamnya terlihat berbeda dari seluruh
   isian lain di borang yang sama — padahal ia el-select yang persis sama.
   Warna dipindahkan ke ikon dan garis tepinya saja. */
.alr-rule { display: flex; align-items: center; justify-content: space-between; gap: .9rem; padding: .85rem .9rem; border-radius: 13px; border: 1px solid #e6e9f0; background: #fff; margin: 0 0 .7rem; flex-wrap: wrap; }
.alr-rule__l { display: flex; align-items: flex-start; gap: .6rem; min-width: 0; flex: 1 1 320px; }
.alr-rule__ico { flex: none; width: 2rem; height: 2rem; border-radius: 9px; display: grid; place-items: center; background: #f1f5f9; color: #94a3b8; font-size: 15px; border: 1px solid #e2e8f0; }
.alr-rule__txt { min-width: 0; }
/* HANYA <b> anak langsung yang jadi judul.
   Sebelumnya selektornya `.alr-tpcut__txt b`, sehingga <b> penegas DI DALAM
   kalimat deskripsi ikut kena `display:block` — "diloloskan", "DITERIMA",
   "tidak lolos", "satu" masing-masing patah jadi barisnya sendiri dan
   kalimatnya tidak lagi terbaca sebagai kalimat. */
.alr-rule__txt > b { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.alr-rule__txt small { display: block; font-size: 11px; line-height: 1.55; color: #64748b; margin-top: .15rem; }
.alr-rule__txt small b { font-weight: 800; color: #334155; }
/* Lebar tetap di layar lapang, memenuhi baris sendiri di layar sempit —
   supaya tidak pernah terjepit jadi kotak selebar dua karakter. */
.alr-rule__sel { width: 260px; flex: none; }
@media (max-width: 640px) {
    .alr-rule__sel { width: 100%; }
}
/* TERISI — warnanya di tepi & ikon, bukan di seluruh kotak. */
.alr-rule.is-on { border-color: rgba(217, 119, 6, .45); }
.alr-rule.is-on .alr-rule__ico { background: #fef3c7; color: #b45309; border-color: rgba(234, 179, 8, .35); }
.alr-rule.is-tuntascut.is-on { border-color: rgba(5, 150, 105, .45); }
.alr-rule.is-tuntascut.is-on .alr-rule__ico { background: #d1fae5; color: #047857; border-color: rgba(16, 185, 129, .35); }
/* BELUM DIPILIH — nada peringatan, karena inilah satu-satunya isian yang
   menahan tombol simpan. Tanpa itu ia terbaca sebagai pilihan opsional yang
   kebetulan kosong. */
.alr-rule.is-kurang { border-color: rgba(220, 38, 38, .45); background: rgba(254, 242, 242, .6); }
.alr-rule.is-kurang .alr-rule__ico { background: #fee2e2; color: #b91c1c; border-color: rgba(220, 38, 38, .3); }

/* Penanda tahap yang tercakup cut-off (read-only, otomatis). */
.wca-stagecard.is-tp { border-color: rgba(234, 179, 8, .45); box-shadow: 0 0 0 1px rgba(234, 179, 8, .18); }
.alr-tpflag { display: flex; align-items: center; gap: .4rem; font-size: 11px; font-weight: 700; color: #a16207; background: rgba(234, 179, 8, .14); border-radius: 8px; padding: .4rem .6rem; margin-bottom: .5rem; }
.alr-tpflag > i { color: #d97706; }
/* Penanda titik tuntas di kartu tahapnya — hijau, sepadan dengan pil
   "Titik Tuntas" di daftar alur, supaya keduanya terbaca sebagai hal yang sama. */
.alr-tpflag.is-tuntas { color: #047857; background: rgba(16, 185, 129, .13); }
.alr-tpflag.is-tuntas > i { color: #059669; }

.alr-wajib { display: inline-block; margin-left: .35rem; padding: .05rem .35rem; border-radius: 999px; font-size: 9.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #b91c1c; background: rgba(220, 38, 38, .12); vertical-align: middle; }
/* Kartu upload hasil — nada biru saat aktif (beda dari talent pool yang kuning). */
.alr-up.is-on { border-color: rgba(79, 70, 229, .45); background: linear-gradient(180deg, rgba(79, 70, 229, .07), rgba(99, 102, 241, .03)); }
.alr-up .alr-tp__ico { background: #eef2ff; color: #4f46e5; border-color: rgba(79, 70, 229, .3); }
.alr-up.is-on .alr-tp__ico { background: #c7d2fe; color: #3730a3; }
.alr-up__ctl { display: inline-flex; align-items: center; gap: .7rem; flex: none; }
.alr-up__wajib { display: inline-flex; align-items: center; gap: .3rem; font-size: 11.5px; font-weight: 700; color: #4338ca; white-space: nowrap; }
/* Daftar "tidak ikut pindah" — sengaja bergaya catatan, bukan tabel: isinya
   kalimat sebab, bukan angka yang perlu dibandingkan berkolom. */
.mal-dampak { border: 1px solid #fde68a; background: #fffbeb; border-radius: 12px; padding: 10px 12px; }
.mal-dampak__ttl { font-size: 11.5px; font-weight: 800; letter-spacing: .04em; color: #92400e; margin-bottom: 6px; text-transform: uppercase; }
.mal-dampak__baris { display: flex; flex-direction: column; gap: 1px; padding: 6px 0; border-top: 1px solid #fef3c7; }
.mal-dampak__baris:first-of-type { border-top: none; }
.mal-dampak__baris b { font-size: 13px; color: #1e293b; }
.mal-dampak__baris small { font-size: 12px; color: #92400e; }

@media (max-width: 560px) {
    .wca-frow { grid-template-columns: 1fr; }
}
</style>
