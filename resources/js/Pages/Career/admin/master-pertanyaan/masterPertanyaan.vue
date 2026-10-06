<!--
    MASTER PERTANYAAN SKRINING — pustaka pertanyaan lintas template.

    ══ GAYANYA DI evo-theme.css, BUKAN DI SINI ════════════════════════════════

    Seluruh kontrol memakai kelas `skr-*` yang didefinisikan global di
    resources/css/evo-theme.css. Itu BUKAN pilihan gaya, melainkan keharusan:
    AdminModal memakai <teleport to="body">, sehingga isi jendela dirender di
    luar elemen akar halaman ini. Custom property yang didefinisikan di akar
    halaman tidak pernah diwariskan ke sana — setiap var() gagal, seluruh
    deklarasi yang memakainya batal, dan yang tersisa tampilan bawaan peramban.

    Itu persis yang membuat jendela "Salin Pertanyaan" dan "Atur Tag" tampil
    tanpa gaya sama sekali. Bukan gayanya jelek — gayanya tidak pernah berlaku.

    <style scoped> di bawah karena itu hanya berisi tata letak khas halaman ini.

    ══ MENYUNTING DI SINI TIDAK MENGUBAH TEMPLATE MANA PUN ════════════════════

    Template memegang salinannya sendiri; yang berubah cuma bawaan untuk
    penarikan BERIKUTNYA. Orang akan mengira sebaliknya — dugaan yang wajar dan
    salah, dan akibatnya mahal. Karena itu angka pemakaian tampil di tiap baris.
-->
<template>
    <Head title="Master Pertanyaan Skrining" />

    <div class="wca mps">
        <div class="wca-phead">
            <div>
                <h1>Master Pertanyaan Skrining</h1>
                <p>
                    Pustaka pertanyaan yang ditarik ke <b>Master Template Skrining</b>. Ditandai per
                    departemen, level, dan tipe kandidat — penyusun template tinggal memilih
                    departemennya, lalu mencentang.
                </p>
                <p class="mps-beku">
                    <i class="bi bi-snow"></i>
                    Menyunting di sini <b>tidak mengubah template yang sudah menariknya</b>. Template
                    memegang salinannya sendiri; drafnya bisa disegarkan dari sini kapan saja.
                </p>
            </div>
            <button class="skr-btn skr-btn--isi" @click="bukaTambah">
                <i class="bi bi-plus-lg"></i> Pertanyaan Baru
            </button>
        </div>

        <div v-if="!siap && !loading" class="wca-note wca-note--warn mps-belum">
            <i class="bi bi-database-exclamation"></i>
            <span>
                Skema pustaka pertanyaan belum ada. Jalankan
                <code>2026-08-24-bank-pertanyaan.sql</code>,
                <code>…-bank-tag-metadata.sql</code>, lalu
                <code>…-bank-isi-pertanyaan.sql</code>.
            </span>
        </div>

        <template v-else>
            <!-- "Cocok penyaring" hanya digambar SAAT ada penyaring aktif —
                 tanpa itu ia mengulang angka pertama persis, dan dua kotak
                 berisi angka sama hanya menyuruh orang membandingkannya sendiri
                 untuk tidak menemukan apa-apa. -->
            <div class="skr-stats">
                <div class="skr-stat">
                    <span class="skr-stat__i" style="--c: #4338ca; --b: rgba(99,102,241,.13)">
                        <i class="bi bi-collection"></i>
                    </span>
                    <div>
                        <b>{{ angka(totalSemua) }}</b>
                        <small>Pertanyaan aktif</small>
                    </div>
                </div>

                <div v-if="adaFilter" class="skr-stat is-sorot">
                    <span class="skr-stat__i" style="--c: #047857; --b: rgba(5,150,105,.14)">
                        <i class="bi bi-funnel-fill"></i>
                    </span>
                    <div>
                        <b>{{ angka(total) }}</b>
                        <small>Cocok penyaring</small>
                    </div>
                </div>
                <div v-else class="skr-stat">
                    <span class="skr-stat__i" style="--c: #0369a1; --b: rgba(14,165,233,.14)">
                        <i class="bi bi-diagram-3"></i>
                    </span>
                    <div>
                        <b>{{ (tag.JOB_FAMILY || []).length }}</b>
                        <small>Departemen</small>
                    </div>
                </div>

                <div class="skr-stat">
                    <span class="skr-stat__i" style="--c: #b45309; --b: rgba(245,158,11,.15)">
                        <i class="bi bi-tags"></i>
                    </span>
                    <div>
                        <b>{{ jmlTag }}</b>
                        <small>Tag tersedia</small>
                    </div>
                </div>

                <div class="skr-stat" :class="{ 'is-sorot': tandai.length }">
                    <span class="skr-stat__i" style="--c: #7c3aed; --b: rgba(139,92,246,.14)">
                        <i class="bi bi-check2-square"></i>
                    </span>
                    <div>
                        <b>{{ tandai.length }}</b>
                        <small>Ditandai</small>
                    </div>
                </div>
            </div>

            <div class="skr-panel">
                <!-- Departemen berdiri paling depan dan paling lebar: itulah
                     pertanyaan pertama orang yang membuka halaman ini. -->
                <div class="skr-panel__bar">
                    <div class="skr-bar">
                        <div class="skr-cari">
                            <i class="bi bi-search"></i>
                            <input v-model="cari" type="text" placeholder="Cari pertanyaan, kode, atau kelompok…" @input="cariTunda" />
                            <button v-if="cari" class="skr-cari__x" @click="cari = ''; muat(1)"><i class="bi bi-x-lg"></i></button>
                        </div>

                        <el-select
                            v-model="f.jf" multiple collapse-tags collapse-tags-tooltip filterable
                            placeholder="Semua departemen" class="skr-sel" popper-class="skr-pop" @change="muat(1)"
                        >
                            <el-option v-for="t in (tag.JOB_FAMILY || [])" :key="t.kode" :label="t.nama" :value="t.kode">
                                <span class="mps-opt">
                                    <i class="bi" :class="t.ikon" :style="{ color: t.warna }"></i>
                                    <span>{{ t.nama }}</span>
                                    <em>{{ t.jml }}</em>
                                </span>
                            </el-option>
                        </el-select>

                        <el-select
                            v-model="f.level" multiple collapse-tags placeholder="Semua level"
                            class="skr-sel" popper-class="skr-pop" @change="muat(1)"
                        >
                            <el-option v-for="t in (tag.LEVEL || [])" :key="t.kode" :label="t.nama" :value="t.kode" />
                        </el-select>

                        <button
                            class="skr-btn skr-btn--ikon" :class="{ 'is-on': lanjut }"
                            :title="lanjut ? 'Sembunyikan penyaring lain' : 'Penyaring lain'" @click="lanjut = !lanjut"
                        >
                            <i class="bi bi-sliders"></i>
                            <span v-if="jmlLanjut" class="skr-btn__n">{{ jmlLanjut }}</span>
                        </button>
                    </div>

                    <transition name="skr-turun">
                        <div v-if="lanjut" class="skr-lanjut">
                            <div class="skr-lanjut__f">
                                <label class="skr-lbl">Tipe kandidat</label>
                                <el-select v-model="f.ct" multiple collapse-tags placeholder="Semua" class="skr-sel" popper-class="skr-pop" @change="muat(1)">
                                    <el-option v-for="x in (tag.CANDIDATE_TYPE || [])" :key="x.kode" :label="x.nama" :value="x.kode" />
                                </el-select>
                            </div>
                            <div class="skr-lanjut__f">
                                <label class="skr-lbl">Fase sesi</label>
                                <el-select v-model="f.fase" multiple collapse-tags placeholder="Semua" class="skr-sel" popper-class="skr-pop" @change="muat(1)">
                                    <el-option v-for="x in (tag.FASE || [])" :key="x.kode" :label="x.nama" :value="x.kode" />
                                </el-select>
                            </div>
                            <div class="skr-lanjut__f">
                                <label class="skr-lbl">Kompetensi</label>
                                <el-select v-model="f.komp" multiple collapse-tags filterable placeholder="Semua" class="skr-sel" popper-class="skr-pop" @change="muat(1)">
                                    <el-option v-for="x in (tag.KOMPETENSI || [])" :key="x.kode" :label="x.nama" :value="x.kode" />
                                </el-select>
                            </div>
                            <div class="skr-lanjut__f">
                                <label class="skr-lbl">Jenis pertanyaan</label>
                                <el-select v-model="f.jenis" multiple collapse-tags placeholder="Semua" class="skr-sel" popper-class="skr-pop" @change="muat(1)">
                                    <el-option v-for="j in jenisPertanyaan" :key="j" :label="labelJenis(j)" :value="j" />
                                </el-select>
                            </div>
                            <div class="skr-lanjut__f">
                                <label class="skr-lbl">Prioritas</label>
                                <el-select v-model="f.prioritas" multiple collapse-tags placeholder="Semua" class="skr-sel" popper-class="skr-pop" @change="muat(1)">
                                    <el-option v-for="pr in prioritasPertanyaan" :key="pr" :label="labelPrioritas(pr)" :value="pr" />
                                </el-select>
                            </div>
                        </div>
                    </transition>

                    <!-- Penyaring yang tersembunyi adalah penyaring yang DILUPAKAN. Orang menutup
                         panelnya, lalu heran kenapa dari 1.022 pertanyaan cuma 16 yang muncul —
                         dan menyalahkan datanya, bukan penyaringnya. Kepingnya tetap terlihat
                         walau panelnya ditutup, dan keping itu sendiri yang melepasnya. -->
                    <div v-if="chipFilter.length" class="skr-aktif">
                        <button
                            v-for="c in chipFilter" :key="c.id"
                            class="skr-aktif__c" :style="c.warna ? { '--c': c.warna } : null"
                            :title="`Lepas penyaring ${c.dim}: ${c.nama}`"
                            @click="lepasFilter(c)"
                        >
                            <em>{{ c.dim }}</em><span>{{ c.nama }}</span><i class="bi bi-x-lg"></i>
                        </button>
                        <button v-if="chipFilter.length > 1" class="skr-aktif__all" @click="resetFilter">
                            Bersihkan semua
                        </button>
                    </div>
                </div>

                <!-- Menandai 40 pertanyaan satu per satu adalah pekerjaan yang
                     membuat orang berhenti memakai tag sama sekali. -->
                <transition name="mps-slide">
                    <div v-if="tandai.length" class="mps-massal">
                        <span class="mps-massal__n">{{ tandai.length }}</span>
                        <span class="mps-massal__t">ditandai</span>
                        <el-select v-model="massalTag" filterable placeholder="Pilih tag…" class="skr-sel mps-massal__s" popper-class="skr-pop">
                            <el-option-group v-for="(isi, dim) in tag" :key="dim" :label="labelDimensi(dim)">
                                <el-option v-for="t in isi" :key="dim + t.kode" :label="t.nama" :value="`${dim}|${t.kode}`" />
                            </el-option-group>
                        </el-select>
                        <button class="skr-btn skr-btn--ind" :disabled="!massalTag || busy" @click="jalankanMassal('PASANG')">
                            <i class="bi bi-plus-lg"></i> Pasang
                        </button>
                        <button class="skr-btn" :disabled="!massalTag || busy" @click="jalankanMassal('LEPAS')">
                            <i class="bi bi-dash-lg"></i> Lepas
                        </button>
                        <button class="skr-ib mps-massal__x" title="Batal menandai" @click="tandai = []">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </transition>

                <!-- Bentuk BARIS, bukan tabel berkolom lima: isi tiap pertanyaan
                     sangat berbeda panjangnya, dan kolom berlebar tetap membuat
                     separuh baris menganga sementara separuh lain berdesakan. -->
                <div v-loading="loading" class="mps-list">
                    <div class="mps-head">
                        <label class="mps-cek"><input type="checkbox" :checked="semuaTertandai" @change="toggleSemua" /></label>
                        <span>{{ angka(total) }} pertanyaan</span>
                        <span v-if="adaFilter" class="mps-head__h">tersaring</span>
                    </div>

                    <div v-if="!loading && !daftar.length" class="skr-kosong">
                        <i class="bi bi-inbox"></i>
                        <strong>Tidak ada pertanyaan yang cocok</strong>
                        <small>Longgarkan penyaringnya, atau buat pertanyaan baru.</small>
                        <button v-if="adaFilter" class="skr-btn" @click="resetFilter">
                            <i class="bi bi-arrow-counterclockwise"></i> Bersihkan penyaring
                        </button>
                    </div>

                    <article
                        v-for="r in daftar" :key="r.id"
                        class="mps-row" :class="{ 'is-mati': !r.aktif, 'is-tandai': tandai.includes(r.id) }"
                    >
                        <label class="mps-cek"><input type="checkbox" :checked="tandai.includes(r.id)" @change="toggleTandai(r.id)" /></label>

                        <div class="mps-isi">
                            <div class="mps-isi__t">
                                <h3>{{ r.label }}</h3>
                                <span v-if="!r.aktif" class="skr-pil skr-pil--mati">nonaktif</span>
                            </div>

                            <!-- Hanya yang MENGUBAH TINDAKAN yang diberi warna.
                                 Chip yang semuanya sama menonjol berarti tidak
                                 ada yang menonjol, dan mata kehilangan pijakan. -->
                            <div class="mps-meta">
                                <code>{{ r.kode }}</code>
                                <span class="mps-meta__s">{{ labelTipe(r.tipe) }}</span>
                                <span v-if="r.jenis" class="mps-meta__s">{{ labelJenis(r.jenis) }}</span>
                                <span v-if="r.prioritas === 'MANDATORY'" class="skr-pil skr-pil--wajib">wajib</span>
                                <span v-else-if="r.prioritas === 'CONDITIONAL'" class="skr-pil skr-pil--syarat">bersyarat</span>
                                <span v-if="r.bobot !== null" class="skr-pil skr-pil--bobot">bobot {{ r.bobot }}</span>
                                <span v-if="r.knockoutOperator" class="skr-pil skr-pil--gugur">
                                    <i class="bi bi-exclamation-octagon-fill"></i>
                                    {{ labelOperator(r.knockoutOperator) }} {{ r.knockoutNilai }}
                                </span>
                                <span v-if="r.durasiDetik" class="mps-meta__s mps-meta__s--w">
                                    <i class="bi bi-stopwatch"></i>{{ r.durasiDetik }}s
                                </span>
                            </div>

                            <p v-if="r.bantuan" class="mps-bantu">{{ r.bantuan }}</p>

                            <div v-if="r.opsi.length" class="mps-opsi">
                                <span v-for="o in r.opsi.slice(0, 5)" :key="o.nilai">
                                    {{ o.label }}<em v-if="o.skor !== null">{{ o.skor }}</em>
                                </span>
                                <span v-if="r.opsi.length > 5" class="mps-opsi__l">+{{ r.opsi.length - 5 }} lagi</span>
                            </div>
                            <div v-else-if="r.skalaMax !== null" class="mps-opsi">
                                <span>Skala {{ r.skalaMin }}–{{ r.skalaMax }}</span>
                                <span v-if="r.labelMin || r.labelMax" class="mps-opsi__l">{{ r.labelMin }} → {{ r.labelMax }}</span>
                            </div>
                        </div>

                        <!-- HANYA departemen. Tiap pertanyaan membawa 10-13 tag
                             (empat level, enam tipe kandidat, fase, kompetensi);
                             menggambar semuanya menghasilkan dinding chip
                             setinggi lima baris yang tak bisa dipindai mata. -->
                        <div class="mps-dept">
                            <span v-for="t in deptTag(r)" :key="t.kode" class="skr-chip" :style="{ '--c': t.warna }">
                                <i v-if="t.ikon" class="bi" :class="t.ikon"></i>{{ t.nama }}
                            </span>
                            <el-popover v-if="sisaTag(r).length" placement="left-start" :width="300" trigger="hover" popper-class="skr-pop">
                                <template #reference>
                                    <button class="skr-chip skr-chip--more">+{{ sisaTag(r).length }}</button>
                                </template>
                                <div class="mps-pop">
                                    <div v-for="(isi, dim) in sisaGrup(r)" :key="dim" class="mps-pop__g">
                                        <span class="mps-pop__h">{{ labelDimensi(dim) }}</span>
                                        <div>
                                            <span v-for="t in isi" :key="t.kode" class="skr-chip" :style="{ '--c': t.warna }">{{ t.nama }}</span>
                                        </div>
                                    </div>
                                </div>
                            </el-popover>
                            <span v-if="!r.tag.length" class="mps-belum-tag">belum ditag</span>
                        </div>

                        <div class="mps-pakai">
                            <template v-if="r.pemakaian.template">
                                <b>{{ r.pemakaian.template }}</b><small>template</small>
                                <span v-if="r.pemakaian.ubahan" class="skr-pil skr-pil--ubah">{{ r.pemakaian.ubahan }} dirombak</span>
                            </template>
                            <span v-else class="mps-belum-tag">belum dipakai</span>
                        </div>

                        <div class="mps-aksi">
                            <el-tooltip content="Sunting" placement="top" :show-after="400">
                                <button class="skr-ib" @click="bukaSunting(r)"><i class="bi bi-pencil"></i></button>
                            </el-tooltip>
                            <el-tooltip content="Atur tag" placement="top" :show-after="400">
                                <button class="skr-ib" @click="bukaTag(r)"><i class="bi bi-tags"></i></button>
                            </el-tooltip>
                            <el-tooltip content="Salin jadi varian" placement="top" :show-after="400">
                                <button class="skr-ib" @click="bukaDuplikat(r)"><i class="bi bi-files"></i></button>
                            </el-tooltip>
                            <el-tooltip :content="r.aktif ? 'Nonaktifkan' : 'Aktifkan'" placement="top" :show-after="400">
                                <button class="skr-ib" :class="{ 'is-on': r.aktif }" @click="mintaToggle(r)">
                                    <i class="bi" :class="r.aktif ? 'bi-toggle-on' : 'bi-toggle-off'"></i>
                                </button>
                            </el-tooltip>
                            <el-tooltip v-if="!r.sistem" content="Hapus" placement="top" :show-after="400">
                                <button class="skr-ib skr-ib--x" @click="mintaHapus(r)"><i class="bi bi-trash"></i></button>
                            </el-tooltip>
                        </div>
                    </article>
                </div>

                <div v-if="total > perHalaman" class="skr-hal">
                    <span class="skr-hal__ket">
                        Menampilkan <b>{{ angka(daftar.length) }}</b> dari <b>{{ angka(total) }}</b> pertanyaan
                        · halaman <b>{{ halaman }}</b> dari <b>{{ Math.ceil(total / perHalaman) }}</b>
                    </span>
                    <el-pagination
                        :layout="sempit ? 'prev, pager, next' : 'total, prev, pager, next'"
                        :pager-count="sempit ? 5 : 7"
                        :current-page="halaman" :page-size="perHalaman" :total="total"
                        @current-change="muat"
                    />
                </div>
            </div>
        </template>

        <!-- ══ JENDELA: PERTANYAAN — BERTAHAP ════════════════════════════════
             Sebelumnya seluruh isian ditumpuk dalam satu gulungan panjang:
             redaksi, penempatan, pilihan jawaban, skala, panduan penilaian,
             bawaan, dan auto-gugur. Dua puluh lebih isian dalam satu layar
             membuat orang tidak pernah tahu ia sudah sampai mana — dan yang
             paling sering terjadi, ia berhenti di tengah dan menyimpan
             pertanyaan tanpa panduan penilaian sama sekali.

             Empat langkah menjawab persis itu. Tiap langkah punya satu
             pertanyaan pokok yang bisa dijawab tanpa memikirkan sisanya:

                1. Apa yang ditanyakan?
                2. Di mana ia diletakkan?
                3. Bagaimana bentuk jawabannya dan apakah dinilai?
                4. Bagaimana petugas menilainya?

             Langkahnya boleh dilompati bebas — ini formulir master, bukan
             pendaftaran. Yang dijaga adalah orang TAHU ada langkah empat. -->
        <AdminModal
            :show="modal"
            :title="form.id ? 'Sunting Pertanyaan' : 'Pertanyaan Baru'"
            :subtitle="form.id ? form.kode : 'Akan tersedia untuk seluruh template'"
            icon="bi-patch-question"
            size="xl"
            :busy="busy"
            @close="modal = false"
            @save="simpan"
        >
            <!-- Rel langkah menempel: ia penunjuk arah, dan penunjuk arah yang
                 tergulir keluar layar berhenti jadi penunjuk arah. -->
            <template #sticky>
                <div class="skr-sticky">
                    <div class="mps-rail">
                        <button
                            v-for="(l, n) in LANGKAH" :key="l.kunci"
                            class="mps-rail__s"
                            :class="{ 'is-on': langkah === n + 1, 'is-lewat': langkah > n + 1, 'is-bad': salahLangkah === n + 1 }"
                            @click="langkah = n + 1"
                        >
                            <span class="mps-rail__n">
                                <i v-if="salahLangkah === n + 1" class="bi bi-exclamation-lg"></i>
                                <i v-else-if="langkah > n + 1" class="bi bi-check-lg"></i>
                                <template v-else>{{ n + 1 }}</template>
                            </span>
                            <span class="mps-rail__t">
                                <b>{{ l.judul }}</b>
                                <small>{{ l.sub }}</small>
                            </span>
                        </button>
                    </div>
                </div>
            </template>

            <!-- ══ 1 · REDAKSI ══════════════════════════════════════════════ -->
            <div v-show="langkah === 1" class="skr-form mps-step">
                <div>
                    <label class="skr-lbl">
                        Pertanyaan <span class="skr-req">*</span>
                        <InfoTip>
                            Redaksi yang <b>dibacakan apa adanya</b> oleh rekruter di telepon.
                            Tulis seperti orang bicara, bukan seperti judul kolom — “Berapa gaji
                            harapan Anda untuk posisi ini?”, bukan “Gaji harapan”.
                        </InfoTip>
                    </label>
                    <textarea v-model="form.label" class="skr-ctl" rows="2" maxlength="500"
                        placeholder="Bersedia ditempatkan di luar kota domisili?"></textarea>
                    <p class="skr-hint">{{ form.label.length }} / 500 karakter</p>
                </div>

                <div class="skr-grid skr-grid--3">
                    <div>
                        <label class="skr-lbl">
                            Tipe isian <span class="skr-req">*</span>
                            <InfoTip>
                                <b>Bentuk kotak jawabannya</b> di layar rekruter: pilihan tunggal,
                                skala angka, teks bebas, dan seterusnya. Hanya tipe berpilihan dan
                                berskala yang bisa ikut dihitung jadi skor.
                            </InfoTip>
                        </label>
                        <el-select v-model="form.tipe" class="skr-sel" popper-class="skr-pop" @change="gantiTipe">
                            <el-option v-for="t in tipe" :key="t" :label="labelTipe(t)" :value="t" />
                        </el-select>
                    </div>
                    <div>
                        <label class="skr-lbl">
                            Jenis pertanyaan
                            <InfoTip>
                                <b>Untuk apa</b> pertanyaan ini ditanyakan — bukan bentuk isiannya.
                                Perilaku menggali kejadian nyata di masa lalu, teknis menguji
                                keahlian, verifikasi mencocokkan data lamaran.
                            </InfoTip>
                        </label>
                        <el-select v-model="form.jenis" clearable placeholder="—" class="skr-sel" popper-class="skr-pop">
                            <el-option v-for="j in jenisPertanyaan" :key="j" :label="labelJenis(j)" :value="j" />
                        </el-select>
                    </div>
                    <div>
                        <label class="skr-lbl">
                            Prioritas
                            <InfoTip>
                                Seberapa sering ia layak ikut ke template. <b>Wajib</b> hampir selalu
                                ditarik, <b>disarankan</b> ditarik bila waktunya cukup,
                                <b>bersyarat</b> hanya untuk kandidat tertentu.
                            </InfoTip>
                        </label>
                        <el-select v-model="form.prioritas" clearable placeholder="—" class="skr-sel" popper-class="skr-pop">
                            <el-option v-for="pr in prioritasPertanyaan" :key="pr" :label="labelPrioritas(pr)" :value="pr" />
                        </el-select>
                    </div>
                </div>

                <div>
                    <label class="skr-lbl">
                        Petunjuk untuk petugas
                        <InfoTip>
                            Kalimat pendek yang tampil <b>di bawah pertanyaan</b> saat rekruter
                            menelepon. Untuk mengarahkan cara bertanya, bukan untuk menilai
                            jawabannya — penilaian ada di langkah 4.
                        </InfoTip>
                    </label>
                    <input v-model="form.bantuan" class="skr-ctl" type="text" maxlength="500"
                        placeholder="Mis. “Tanyakan tanggal pastinya, bukan secepatnya.”" />
                </div>
            </div>

            <!-- ══ 2 · PENEMPATAN ═══════════════════════════════════════════ -->
            <div v-show="langkah === 2" class="skr-form mps-step">
                <!-- Ini yang paling sering ditanya orang, jadi dijawab di layar,
                     bukan di dokumentasi yang tidak akan dibuka siapa pun. -->
                <div class="mps-jelas">
                    <i class="bi bi-diagram-3"></i>
                    <div>
                        <b>Kelompok menentukan dua hal sekaligus.</b>
                        Ia jadi <b>judul bagian</b> saat pertanyaan ini ditarik ke template — rekruter
                        melihatnya sebagai pemisah di layar telepon — dan ia jadi <b>awalan kode</b>
                        yang dibuatkan sistem (Ketersediaan → <code>avl_</code>, Perilaku →
                        <code>bhv_</code>).
                        <br />
                        <b>Sub-kelompok</b> hanya merapikan pustaka ini: ia membantu Anda menemukan
                        kembali pertanyaan yang mirip, dan <b>tidak ikut</b> ke template maupun ke
                        layar rekruter.
                    </div>
                </div>

                <div class="skr-grid skr-grid--3">
                    <div>
                        <label class="skr-lbl">
                            Kelompok
                            <InfoTip>
                                Tidak ada master tersendiri untuk ini — daftarnya <b>tumbuh dari
                                pertanyaan yang sudah ada</b>. Pilih yang sudah ada bila cocok;
                                mengetik nama baru langsung membuat kelompok baru.
                                <br /><br />
                                Kelompok baru mendapat awalan kode dari namanya sendiri, dan
                                pertanyaan berikutnya di kelompok itu ikut memakainya.
                            </InfoTip>
                        </label>
                        <el-select
                            v-model="form.kelompok"
                            filterable allow-create default-first-option clearable
                            placeholder="Pilih atau ketik baru"
                            class="skr-sel" popper-class="skr-pop"
                        >
                            <el-option v-for="k in kelompok" :key="k" :label="k" :value="k" />
                        </el-select>
                        <p class="skr-hint">Jadi judul bagian di template.</p>
                    </div>
                    <div>
                        <label class="skr-lbl">
                            Sub-kelompok
                            <InfoTip>
                                Label halus <b>di dalam</b> kelompok, mis. Ketersediaan →
                                <i>Penempatan</i>, <i>Waktu mulai</i>. Berguna saat satu kelompok
                                sudah berisi puluhan pertanyaan.
                                <br /><br />
                                Boleh dikosongkan. Ia tidak pernah tampil di hadapan kandidat
                                maupun rekruter.
                            </InfoTip>
                        </label>
                        <el-select
                            v-model="form.subKelompok"
                            filterable allow-create default-first-option clearable
                            placeholder="Pilih atau ketik baru"
                            class="skr-sel" popper-class="skr-pop"
                        >
                            <el-option v-for="k in subKelompokPilihan" :key="k" :label="k" :value="k" />
                        </el-select>
                        <p class="skr-hint">Hanya untuk merapikan pustaka.</p>
                    </div>
                    <div>
                        <label class="skr-lbl">
                            Perkiraan durasi <small>detik</small>
                            <InfoTip>
                                Dijumlahkan saat menyusun template supaya panjang teleponnya
                                terlihat sebelum dipakai. Skrining yang lewat 20–25 menit berhenti
                                jadi penyaringan dan berubah jadi wawancara.
                            </InfoTip>
                        </label>
                        <input v-model.number="form.durasiDetik" class="skr-ctl skr-ctl--num" type="number" min="5" max="1800" step="5" />
                        <p class="skr-hint">Perkiraan waktu menjawab, bukan waktu membaca.</p>
                    </div>
                </div>

                <!-- ══ KODE DIBUATKAN SISTEM ═════════════════════════════════
                     Kode inilah yang menyatukan pertanyaan yang sama di template
                     berbeda saat hasilnya diagregasi. Salah ketik satu huruf
                     tidak menimbulkan galat apa pun — ia baru terasa
                     berbulan-bulan kemudian ketika laporannya memecah satu
                     pertanyaan jadi dua kolom. -->
                <div class="mps-kodebox">
                    <label class="skr-lbl">
                        Kode
                        <InfoTip>
                            Pengenal tetap pertanyaan ini. Ia yang menyatukan jawaban dari template
                            berbeda saat hasilnya diagregasi — “gaji harapan” di template HR dan di
                            template MT terhitung sebagai satu hal karena kodenya sama.
                        </InfoTip>
                    </label>
                    <!-- Kode yang sudah bisa dihitung TIDAK disembunyikan.
                         "Dibuatkan sistem" tanpa menunjukkan hasilnya membuat
                         orang kehilangan satu-satunya kesempatan menyadari
                         kelompoknya salah — awalan yang keliru baru ketahuan
                         berbulan-bulan kemudian, lewat laporan yang memecah
                         satu pertanyaan jadi dua kolom. -->
                    <div v-if="!form.id" class="mps-kodeauto" :class="{ 'is-ada': !!kodePratinjau }">
                        <i class="bi" :class="kodeMuat ? 'bi-arrow-repeat mps-putar' : 'bi-magic'"></i>
                        <code v-if="kodePratinjau">{{ kodePratinjau }}</code>
                        <span v-else>Tulis pertanyaannya dulu — kodenya menyusul di sini</span>
                        <em v-if="kodePratinjau">dihitung dari redaksi &amp; kelompoknya</em>
                    </div>
                    <div v-else class="mps-kodeauto is-ada">
                        <code>{{ form.kode }}</code>
                        <span v-if="form.sistem" class="mps-kodeauto__k" title="Bawaan sistem — tidak bisa diubah">
                            <i class="bi bi-lock-fill"></i>
                        </span>
                        <em>tidak berganti saat disunting — sesi yang sudah tercatat memakainya</em>
                    </div>
                </div>
            </div>

            <!-- ══ 3 · JAWABAN & SKOR ═══════════════════════════════════════ -->
            <div v-show="langkah === 3" class="skr-form mps-step">
                <!-- ── PILIHAN JAWABAN ───────────────────────────────────── -->
                <div v-if="beropsi(form.tipe)" class="skr-blok">
                    <div class="skr-blok__h">
                        <span>Pilihan jawaban</span>
                        <small>label dibacakan · nilai tersimpan · skor bila dibobot</small>
                        <button class="skr-add" @click="form.opsi.push({ nilai: '', label: '', skor: null })">
                            <i class="bi bi-plus-lg"></i> Tambah
                        </button>
                    </div>

                    <!-- Kepala kolom sekali di atas, bukan placeholder berulang:
                         placeholder hilang begitu diisi, jadi baris ketujuh ke
                         bawah kehilangan keterangan kolomnya. -->
                    <div class="skr-orow skr-orow--h">
                        <span>Label</span><span>Nilai tersimpan</span><span>Skor</span><span></span>
                    </div>

                    <div v-for="(o, i) in form.opsi" :key="i" class="skr-orow">
                        <input v-model="o.label" class="skr-ctl" type="text" placeholder="mis. Ya, bersedia" maxlength="200" @blur="isiNilai(o)" />
                        <input v-model="o.nilai" class="skr-ctl skr-ctl--mono" type="text" placeholder="YA" maxlength="120" />
                        <input v-model.number="o.skor" class="skr-ctl skr-ctl--num" type="number"
                            :placeholder="form.bobot === null ? '—' : '0'" :disabled="form.bobot === null" min="-999" max="999" />
                        <button class="skr-ib skr-ib--x" title="Hapus pilihan" @click="form.opsi.splice(i, 1)">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <p v-if="form.opsi.length < 2" class="skr-warn">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Minimal dua pilihan — satu pilihan bukan pertanyaan.
                    </p>
                    <p v-else-if="form.bobot === null" class="skr-hint">
                        Kolom skor mati karena pertanyaan ini belum dibobot. Nyalakan <b>Masuk skor</b> di bawah.
                    </p>
                </div>

                <!-- ── SKALA ─────────────────────────────────────────────── -->
                <div v-else-if="berskala(form.tipe)" class="skr-blok">
                    <div class="skr-blok__h">
                        <span>Rentang skala</span>
                        <small>label ujung membantu penilai memakai skala yang sama</small>
                    </div>
                    <div class="mps-skala">
                        <div>
                            <label class="skr-lbl">Dari</label>
                            <input v-model.number="form.skalaMin" class="skr-ctl skr-ctl--num" type="number" min="0" max="100" />
                        </div>
                        <div>
                            <label class="skr-lbl">Sampai</label>
                            <input v-model.number="form.skalaMax" class="skr-ctl skr-ctl--num" type="number" min="1" max="100" />
                        </div>
                        <div>
                            <label class="skr-lbl">Label ujung bawah</label>
                            <input v-model="form.labelMin" class="skr-ctl" type="text" maxlength="100" placeholder="Sangat kurang" />
                        </div>
                        <div>
                            <label class="skr-lbl">Label ujung atas</label>
                            <input v-model="form.labelMax" class="skr-ctl" type="text" maxlength="100" placeholder="Sangat baik" />
                        </div>
                    </div>
                </div>

                <!-- Langkah yang kosong tanpa penjelasan terbaca seperti gagal
                     memuat. Kalimat ini menyatakan bahwa memang tidak ada yang
                     perlu diisi, dan menyebut tipenya supaya orang tahu sebabnya. -->
                <div v-else class="skr-kosong mps-nokolom">
                    <i class="bi bi-input-cursor-text"></i>
                    <strong>{{ labelTipe(form.tipe) }} tidak butuh daftar pilihan</strong>
                    <small>Rekruter mengetik jawabannya apa adanya. Ganti tipe isian di langkah 1 bila ingin jawaban terbatas.</small>
                </div>

                <!-- ── BAWAAN ────────────────────────────────────────────── -->
                <div class="skr-blok">
                    <div class="skr-blok__h">
                        <span>Bawaan saat ditarik ke template</span>
                        <small>semuanya masih bisa diubah per template sesudah ditarik</small>
                    </div>
                    <div class="mps-sakelar">
                        <label class="skr-sw" :class="{ 'is-on': form.wajib }">
                            <input v-model="form.wajib" type="checkbox" /><span class="skr-sw__t"></span><span>Wajib dijawab</span>
                        </label>
                        <label class="skr-sw" :class="{ 'is-on': form.catatan }">
                            <input v-model="form.catatan" type="checkbox" /><span class="skr-sw__t"></span><span>Kotak catatan</span>
                        </label>
                        <label v-if="berskor(form.tipe)" class="skr-sw" :class="{ 'is-on': form.bobot !== null }">
                            <input type="checkbox" :checked="form.bobot !== null" @change="setBobot($event.target.checked)" />
                            <span class="skr-sw__t"></span><span>Masuk skor</span>
                        </label>
                        <span v-else class="mps-mati">
                            <i class="bi bi-slash-circle"></i> {{ labelTipe(form.tipe) }} tidak dinilai angka oleh sistem
                        </span>
                        <div v-if="form.bobot !== null" class="mps-bobot">
                            <input v-model.number="form.bobot" class="skr-ctl skr-ctl--num mps-bobot__in" type="number" min="0" max="9999" />
                            <small>× nilai jawaban</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ 4 · PENILAIAN ════════════════════════════════════════════ -->
            <div v-show="langkah === 4" class="skr-form mps-step">
                <div class="skr-blok">
                    <div class="skr-blok__h">
                        <span>Panduan penilaian</span>
                        <small>ikut tersalin ke template — inilah yang dibaca rekruter di worklist</small>
                    </div>
                    <div class="skr-form">
                        <div>
                            <label class="skr-lbl">
                                Jawaban yang diharapkan
                                <InfoTip>
                                    Ciri jawaban yang baik, bukan contoh jawabannya. Rekruter membaca ini
                                    sambil mendengarkan — kalimat panjang tidak akan sempat dibaca.
                                </InfoTip>
                            </label>
                            <input v-model="form.jawabanDiharapkan" class="skr-ctl" type="text" maxlength="1000"
                                placeholder="Mis. “Menyebut situasi, tindakan, dan hasil yang terukur.”" />
                        </div>
                        <div>
                            <label class="skr-lbl">Potensi risiko yang perlu diperhatikan</label>
                            <input v-model="form.redFlag" class="skr-ctl" type="text" maxlength="1000"
                                placeholder="Mis. “Seluruh sebab dilimpahkan ke pihak lain.”" />
                            <p class="skr-hint">
                                Ditulis sebagai <b>potensi</b>, bukan vonis — hal yang perlu ditelusuri lebih
                                jauh di tahap berikutnya, bukan alasan menggugurkan di telepon.
                            </p>
                        </div>
                        <div>
                            <label class="skr-lbl">Pertanyaan lanjutan</label>
                            <input v-model="form.pertanyaanLanjutan" class="skr-ctl" type="text" maxlength="1000"
                                placeholder="Mis. “Apa yang akan Anda ubah kalau mengulang situasi itu?”" />
                        </div>
                    </div>
                </div>

                <!-- Blok sendiri, dan warnanya sendiri: auto-gugur satu-satunya
                     setelan di jendela ini yang bisa menyingkirkan orang dari
                     proses. Ditempel di bawah daftar sakelar biasa, ia terbaca
                     setara dengan "sediakan kotak catatan". -->
                <div class="skr-blok" :class="{ 'is-bad': !!form.knockoutOperator }">
                    <div class="skr-blok__h skr-blok__h--bad">
                        <span>Bawaan auto-gugur</span>
                        <small>menandai, bukan menggugurkan — palu tetap diketuk lewat mesin keputusan</small>
                        <label class="skr-sw skr-sw--bad" :class="{ 'is-on': !!form.knockoutOperator }">
                            <input type="checkbox" :checked="!!form.knockoutOperator" @change="setKnockout($event.target.checked)" />
                            <span class="skr-sw__t"></span>
                        </label>
                    </div>

                    <template v-if="form.knockoutOperator">
                        <div class="mps-korow">
                            <span class="mps-kolbl">Gugur bila jawabannya</span>
                            <el-select v-model="form.knockoutOperator" class="skr-sel mps-koop" popper-class="skr-pop">
                                <el-option v-for="o in operator" :key="o" :label="labelOperator(o)" :value="o" />
                            </el-select>
                            <input v-model="form.knockoutNilai" class="skr-ctl" type="text" :placeholder="phNilai" maxlength="500" />
                        </div>
                        <input v-model="form.knockoutPesan" class="skr-ctl" type="text" maxlength="500"
                            placeholder="Alasan yang dicatat, mis. “Gaji harapan di atas rentang MPP.”" />
                        <p class="skr-hint">
                            Kosongkan bila ambangnya berbeda tiap MPP — mis. gaji harapan. Bawaan yang salah
                            lebih berbahaya daripada tidak ada bawaan, karena ia ikut tersalin ke
                            <b>setiap</b> template tanpa diperiksa lagi.
                        </p>
                    </template>
                </div>
            </div>

            <!-- ══ 5 · TAG ═════════════════════════════════════════════════ -->
            <div v-show="langkah === 5" class="skr-form mps-step">
                <div v-if="!adaDimensiTag" class="skr-kosong">
                    <i class="bi bi-tags"></i>
                    <strong>Skema tag belum dijalankan</strong>
                    <small>Pertanyaan tetap tersimpan, tapi belum bisa disaring per departemen.</small>
                </div>

                <template v-else>
                    <div class="mps-jelas" :class="{ 'is-bad': !adaDeptTerpilih }">
                        <i class="bi" :class="adaDeptTerpilih ? 'bi-tags-fill' : 'bi-exclamation-triangle-fill'"></i>
                        <div v-if="adaDeptTerpilih">
                            <b>Departemen menentukan pertanyaan ini muncul di mana.</b>
                            Saat orang menyusun template untuk loker IT, yang ditawarkan hanya pertanyaan
                            bertanda IT atau <b>Umum / Semua Posisi</b>. Dimensi lain — level, tipe
                            kandidat, fase — mempersempit lebih jauh, dan boleh dikosongkan.
                        </div>
                        <div v-else>
                            <b>Belum ada departemen yang dipilih.</b>
                            Pertanyaan tanpa departemen <b>tidak akan pernah muncul</b> saat orang menyusun
                            template — penyaring pertamanya justru departemen. Pilih minimal satu; yang
                            berlaku untuk semua posisi ditandai <b>Umum / Semua Posisi</b>.
                        </div>
                    </div>

                    <div v-for="(isi, dim) in tag" :key="dim" class="skr-tagdim">
                        <div class="skr-tagdim__h">
                            <span>{{ labelDimensi(dim) }}</span>
                            <small>{{ (formTag[dim] || []).length }} dipilih</small>
                        </div>
                        <div class="skr-tagdim__l">
                            <button
                                v-for="t in isi" :key="t.kode" type="button" class="skr-tagbtn"
                                :class="{ 'is-on': (formTag[dim] || []).includes(t.kode), 'is-mati': !t.aktif }"
                                :style="(formTag[dim] || []).includes(t.kode)
                                    ? { borderColor: t.warna, color: t.warna, background: t.warna + '14' } : {}"
                                @click="toggleFormTag(dim, t.kode)"
                            >
                                <i v-if="t.ikon" class="bi" :class="t.ikon"></i>{{ t.nama }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <template #footer>
                <button class="skr-btn" :disabled="busy" @click="modal = false">
                    <i class="bi bi-x-lg"></i> Batal
                </button>
                <button class="skr-btn" :disabled="busy || langkah === 1" @click="langkah -= 1">
                    <i class="bi bi-arrow-left"></i> Kembali
                </button>
                <button
                    v-if="langkah < LANGKAH.length"
                    class="skr-btn skr-btn--isi" :disabled="busy" @click="maju"
                >
                    Lanjut <i class="bi bi-arrow-right"></i>
                </button>
                <button v-else class="skr-btn skr-btn--isi" :disabled="busy" @click="simpan">
                    <i class="bi bi-save"></i> {{ busy ? 'Menyimpan…' : 'Simpan Data' }}
                </button>
            </template>
        </AdminModal>

        <!-- ══ JENDELA: TAG ══════════════════════════════════════════════════ -->
        <AdminModal
            :show="modalTag"
            title="Atur Tag Pertanyaan"
            :subtitle="tagTarget ? tagTarget.label : ''"
            icon="bi-tags"
            size="lg"
            :busy="busy"
            save-label="Simpan Tag"
            @close="modalTag = false"
            @save="simpanTag"
        >
            <p class="skr-hint mps-tagnote">
                <b>Departemen</b> menentukan pertanyaan ini muncul saat menyusun template untuk loker
                bidang apa. Boleh lebih dari satu — pertanyaan lintas bidang biasanya juga ditandai
                <b>Umum / Semua Posisi</b>.
            </p>
            <div v-for="(isi, dim) in tag" :key="dim" class="skr-tagdim">
                <div class="skr-tagdim__h">
                    <span>{{ labelDimensi(dim) }}</span>
                    <small>{{ (pilihTag[dim] || []).length }} dipilih</small>
                </div>
                <div class="skr-tagdim__l">
                    <button
                        v-for="t in isi" :key="t.kode" type="button" class="skr-tagbtn"
                        :class="{ 'is-on': (pilihTag[dim] || []).includes(t.kode), 'is-mati': !t.aktif }"
                        :style="(pilihTag[dim] || []).includes(t.kode)
                            ? { borderColor: t.warna, color: t.warna, background: t.warna + '14' } : {}"
                        @click="toggleTagPilih(dim, t.kode)"
                    >
                        <i v-if="t.ikon" class="bi" :class="t.ikon"></i>{{ t.nama }}
                    </button>
                </div>
            </div>
        </AdminModal>

        <!-- ══ JENDELA: DUPLIKAT ═════════════════════════════════════════════ -->
        <AdminModal
            :show="modalDup"
            title="Salin Pertanyaan"
            :subtitle="`Dari ${dup.dari}`"
            icon="bi-files"
            size="lg"
            :busy="busy"
            save-label="Salin"
            @close="modalDup = false"
            @save="simpanDuplikat"
        >
            <div class="skr-form">
                <p class="skr-hint">
                    Seluruh isinya tersalin — opsi, skala, panduan, dan bawaan. Cara paling pendek
                    membuat varian yang cuma beda ambang gugurnya. Tag <b>tidak</b> ikut tersalin.
                </p>
                <div>
                    <label class="skr-lbl">Pertanyaan <span class="skr-req">*</span></label>
                    <textarea v-model="dup.label" class="skr-ctl" rows="3" maxlength="500"></textarea>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="!!tanya"
            :title="tanya?.judul || ''"
            :subtitle="tanya?.sub || ''"
            :icon="tanya?.ikon || 'bi-question-circle'"
            :danger="tanya?.bahaya !== false"
            :confirm-label="tanya?.tombol || 'Ya, Lanjutkan'"
            :note="tanya?.nota || ''"
            :busy="busy"
            @cancel="tanya = null"
            @confirm="jalankanTanya"
        />
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ElMessage } from 'element-plus';

import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import InfoTip from '@career/InfoTip.vue';
import { layarSempit } from '@utils/layarSempit';
import { chipPenyaring, jmlLanjut } from '@utils/career/penyaringPustaka';

const API = '/api/v1/master-pertanyaan';
const CFG = { headers: { Accept: 'application/json' } };

const NAMA_TIPE = {
    RADIO: 'Pilihan tunggal', CHECKBOX: 'Pilihan jamak', SELECT: 'Dropdown',
    RATING: 'Bintang', LIKERT: 'Skala setuju', NPS: 'NPS 0–10', BOOLEAN: 'Ya / Tidak',
    TEXT: 'Teks singkat', TEXTAREA: 'Teks panjang', EDITOR: 'Catatan berformat',
    NUMBER: 'Angka', CURRENCY: 'Rupiah', DATE: 'Tanggal',
};

const NAMA_JENIS = {
    GENERAL: 'Umum', ELIGIBILITY: 'Kelayakan', MOTIVATION: 'Motivasi',
    BEHAVIORAL: 'Perilaku', COMPETENCY: 'Kompetensi', TECHNICAL: 'Teknis',
    SITUATIONAL: 'Situasional', EXPERIENCE: 'Pengalaman', AVAILABILITY: 'Ketersediaan',
    COMPENSATION: 'Kompensasi', CULTURE_FIT: 'Kecocokan budaya',
    CAREER_ASPIRATION: 'Rencana karier', RED_FLAG: 'Deteksi risiko',
    CLOSING: 'Penutup', VERIFICATION: 'Verifikasi',
};

const NAMA_PRIORITAS = {
    MANDATORY: 'Wajib', RECOMMENDED: 'Disarankan',
    OPTIONAL: 'Opsional', CONDITIONAL: 'Bersyarat',
};

const NAMA_DIMENSI = {
    JOB_FAMILY: 'Departemen / Job Family', LEVEL: 'Level jabatan',
    CANDIDATE_TYPE: 'Tipe kandidat', KOMPETENSI: 'Kompetensi', FASE: 'Fase sesi',
};

const NAMA_OPERATOR = {
    '=': 'sama dengan', '!=': 'tidak sama dengan', '>': 'lebih dari', '<': 'kurang dari',
    '>=': 'minimal', '<=': 'maksimal', ANTARA: 'antara',
    ADA_DI: 'salah satu dari', TIDAK_ADA_DI: 'bukan salah satu dari',
};

const FILTER_KOSONG = () => ({ jf: [], level: [], ct: [], fase: [], komp: [], jenis: [], prioritas: [] });

const FORM_KOSONG = () => ({
    id: null, kode: '', kelompok: '', subKelompok: '', tipe: 'RADIO', label: '', bantuan: '',
    jenis: null, prioritas: 'RECOMMENDED', durasiDetik: 60,
    jawabanDiharapkan: '', redFlag: '', pertanyaanLanjutan: '',
    opsi: [
        { nilai: 'YA', label: 'Ya', skor: null },
        { nilai: 'TIDAK', label: 'Tidak', skor: null },
    ],
    skalaMin: 1, skalaMax: 5, labelMin: '', labelMax: '',
    bobot: null, knockoutOperator: null, knockoutNilai: '', knockoutPesan: '',
    wajib: true, catatan: false, urutan: 0, sistem: false,
});

export default {
    components: { Head, AdminModal, ConfirmModal, InfoTip },
    mixins: [layarSempit()],
    data() {
        return {
            loading: true,
            siap: true,
            daftar: [],
            total: 0,
            totalSemua: 0,
            halaman: 1,
            perHalaman: 50,

            kelompok: [],
            subKelompok: [],

            // ── LANGKAH FORMULIR ─────────────────────────────────────────
            //
            // Judulnya menyebut APA yang diisi, sub-judulnya menyebut kenapa.
            // Angka langkah tanpa nama hanya memberi tahu ada berapa banyak
            // yang tersisa, bukan apa yang menanti di sana.
            LANGKAH: [
                { kunci: 'redaksi', judul: 'Pertanyaan', sub: 'Apa yang ditanyakan' },
                { kunci: 'tempat', judul: 'Penempatan', sub: 'Di mana ia diletakkan' },
                { kunci: 'jawab', judul: 'Jawaban & Skor', sub: 'Bentuk & penilaian' },
                { kunci: 'nilai', judul: 'Panduan', sub: 'Cara petugas menilai' },
                { kunci: 'tag', judul: 'Tag', sub: 'Muncul untuk loker apa' },
            ],
            langkah: 1,
            salahLangkah: null,

            // Tag milik FORMULIR, terpisah dari pilihTag milik jendela "Atur
            // Tag" di baris daftar. Menyatukan keduanya berarti membuka satu
            // jendela ikut mengubah isian jendela yang lain.
            formTag: {},
            kodePratinjau: '',
            kodeMuat: false,
            tmKode: null,
            tipe: [],
            tipeBeropsi: [],
            tipeBerskala: [],
            tipeBerskor: [],
            operator: [],
            tag: {},
            jenisPertanyaan: [],
            prioritasPertanyaan: [],

            cari: '',
            f: FILTER_KOSONG(),
            lanjut: false,
            tm: null,

            tandai: [],
            massalTag: null,

            modal: false,
            modalDup: false,
            modalTag: false,
            busy: false,
            form: FORM_KOSONG(),
            dup: { id: null, dari: '', kode: '', label: '' },
            tagTarget: null,
            pilihTag: {},
            tanya: null,
        };
    },
    computed: {
        jmlLanjut() {
            return jmlLanjut(this.f);
        },
        chipFilter() {
            return chipPenyaring({
                cari: this.cari,
                nilai: this.f,
                tag: this.tag,
                labelJenis: this.labelJenis,
                labelPrioritas: this.labelPrioritas,
            });
        },
        /**
         * Sub-kelompok yang ditawarkan menyesuaikan kelompok yang sedang
         * dipilih. Selama kelompoknya belum dipilih, seluruhnya ditawarkan —
         * lebih baik daripada kotak kosong yang terlihat rusak.
         */
        /** Minimal satu departemen — tanpa itu pertanyaannya tak pernah tampil. */
        adaDeptTerpilih() {
            return (this.formTag.JOB_FAMILY || []).length > 0;
        },
        adaDimensiTag() {
            return Object.keys(this.tag).length > 0;
        },
        subKelompokPilihan() {
            const k = this.form.kelompok;
            const cocok = k ? this.subKelompok.filter((x) => x.kelompok === k) : this.subKelompok;

            return [...new Set((cocok.length ? cocok : this.subKelompok).map((x) => x.sub))].sort(
                (a, b) => a.localeCompare(b, 'id'),
            );
        },
        adaFilter() {
            return !!this.cari || Object.values(this.f).some((x) => x.length);
        },
        semuaTertandai() {
            return this.daftar.length > 0 && this.daftar.every((r) => this.tandai.includes(r.id));
        },
        jmlTag() {
            return Object.values(this.tag).reduce((n, x) => n + x.length, 0);
        },
        phNilai() {
            if (this.form.knockoutOperator === 'ANTARA') return '20,25';
            if (['ADA_DI', 'TIDAK_ADA_DI'].includes(this.form.knockoutOperator)) return 'S1,D4';

            return 'nilai pembanding';
        },
    },
    watch: {
        // Kode dihitung server karena awalannya diambil dari data, bukan dari
        // peta di sisi klien. Ditunda 450 ms supaya mengetik satu kalimat tidak
        // berubah jadi tiga puluh permintaan.
        'form.label'() { this.jadwalPratinjauKode(); },
        'form.kelompok'() { this.jadwalPratinjauKode(); },
    },
    mounted() {
        this.muat(1);
    },
    beforeUnmount() {
        clearTimeout(this.tm);
    },
    methods: {
        angka(n) { return (n || 0).toLocaleString('id-ID'); },
        labelTipe(t) { return NAMA_TIPE[t] || t; },
        labelJenis(j) { return NAMA_JENIS[j] || j; },
        labelPrioritas(p) { return NAMA_PRIORITAS[p] || p; },
        labelDimensi(d) { return NAMA_DIMENSI[d] || d; },
        labelOperator(o) { return NAMA_OPERATOR[o] || o; },
        beropsi(t) { return this.tipeBeropsi.includes(t); },
        berskala(t) { return this.tipeBerskala.includes(t); },
        berskor(t) { return this.tipeBerskor.includes(t); },

        /**
         * Departemen yang digambar di baris — MAKSIMAL TIGA.
         *
         * Tiap pertanyaan membawa 10-13 tag: empat level, enam tipe kandidat,
         * satu fase, dan kompetensinya. Menggambar semuanya menghasilkan dinding
         * chip setinggi lima baris yang tak bisa dipindai mata — dan membuat
         * daftar 50 baris jadi sepanjang lima layar.
         */
        deptTag(r) {
            return (r.tag || []).filter((t) => t.dimensi === 'JOB_FAMILY').slice(0, 3);
        },
        sisaTag(r) {
            const tampil = new Set(this.deptTag(r).map((t) => t.dimensi + t.kode));

            return (r.tag || []).filter((t) => !tampil.has(t.dimensi + t.kode));
        },
        sisaGrup(r) {
            const out = {};
            this.sisaTag(r).forEach((t) => {
                (out[t.dimensi] = out[t.dimensi] || []).push(t);
            });

            return out;
        },

        // ── MUAT ────────────────────────────────────────────────────────────
        async muat(halaman = 1) {
            this.halaman = halaman;
            this.loading = true;
            try {
                const { data } = await axios.get(API, {
                    ...CFG,
                    params: { q: this.cari || undefined, ...this.f, halaman },
                });
                const r = data.result || {};

                this.siap = r.siap !== false;
                this.daftar = r.rows || [];
                this.total = r.total || 0;
                if (!this.adaFilter) this.totalSemua = r.total || 0;
                this.perHalaman = r.perHalaman || 50;
                this.kelompok = r.kelompok || [];
                this.subKelompok = r.subKelompok || [];
                this.tipe = r.tipe || [];
                this.tipeBeropsi = r.tipeBeropsi || [];
                this.tipeBerskala = r.tipeBerskala || [];
                this.tipeBerskor = r.tipeBerskor || [];
                this.operator = r.operator || [];
                this.tag = r.tag || {};
                this.jenisPertanyaan = r.jenisPertanyaan || [];
                this.prioritasPertanyaan = r.prioritasPertanyaan || [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat data.');
            } finally {
                this.loading = false;
            }
        },
        /* Ketikan ditunda 350 ms — memanggil server tiap ketukan pada pustaka
           seribu baris membanjiri sambungan tanpa mempercepat hasilnya. */
        cariTunda() {
            clearTimeout(this.tm);
            this.tm = setTimeout(() => this.muat(1), 350);
        },
        lepasFilter(c) {
            if (c.kunci === 'cari') this.cari = '';
            else this.f[c.kunci] = this.f[c.kunci].filter((x) => x !== c.kode);
            this.muat(1);
        },
        resetFilter() {
            this.cari = '';
            this.f = FILTER_KOSONG();
            this.muat(1);
        },

        // ── PENANDAAN MASSAL ────────────────────────────────────────────────
        toggleTandai(id) {
            const i = this.tandai.indexOf(id);
            if (i >= 0) this.tandai.splice(i, 1);
            else this.tandai.push(id);
        },
        toggleSemua() {
            const ids = this.daftar.map((r) => r.id);

            this.tandai = this.semuaTertandai
                ? this.tandai.filter((x) => !ids.includes(x))
                : [...new Set([...this.tandai, ...ids])];
        },
        async jalankanMassal(aksi) {
            if (!this.massalTag || !this.tandai.length) return;

            const [dimensi, kode] = this.massalTag.split('|');
            this.busy = true;
            try {
                const { data } = await axios.post(`${API}/tag-massal`, { id: this.tandai, dimensi, kode, aksi }, CFG);
                ElMessage.success(data.message || 'Selesai.');
                await this.muat(this.halaman);
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menandai.');
            } finally {
                this.busy = false;
            }
        },

        // ── TAG PER PERTANYAAN ──────────────────────────────────────────────
        bukaTag(r) {
            this.tagTarget = r;
            const pilih = {};
            Object.keys(this.tag).forEach((d) => (pilih[d] = []));
            (r.tag || []).forEach((t) => {
                if (!pilih[t.dimensi]) pilih[t.dimensi] = [];
                pilih[t.dimensi].push(t.kode);
            });
            this.pilihTag = pilih;
            this.modalTag = true;
        },
        toggleFormTag(dim, kode) {
            if (!this.formTag[dim]) this.formTag[dim] = [];

            const i = this.formTag[dim].indexOf(kode);
            if (i >= 0) this.formTag[dim].splice(i, 1);
            else this.formTag[dim].push(kode);
        },
        /** {dimensi, kode}[] — bentuk yang diminta endpoint tag. */
        tagKeKirim(peta) {
            const out = [];
            Object.entries(peta).forEach(([dimensi, kode]) => {
                (kode || []).forEach((k) => out.push({ dimensi, kode: k }));
            });

            return out;
        },
        jadwalPratinjauKode() {
            if (this.form.id) return;

            clearTimeout(this.tmKode);
            if (!this.form.label.trim()) {
                this.kodePratinjau = '';

                return;
            }

            this.kodeMuat = true;
            this.tmKode = setTimeout(() => this.ambilPratinjauKode(), 450);
        },
        async ambilPratinjauKode() {
            const label = this.form.label;
            const kelompok = this.form.kelompok;

            try {
                const { data } = await axios.post(`${API}/pratinjau-kode`, { label, kelompok }, CFG);
                // Jawaban yang datang terlambat diabaikan: mengetik cepat
                // membuat dua permintaan saling salip, dan yang menang di layar
                // harus yang cocok dengan apa yang sedang tertulis.
                if (label === this.form.label && kelompok === this.form.kelompok) {
                    this.kodePratinjau = data.result?.kode || '';
                }
            } catch {
                this.kodePratinjau = '';
            } finally {
                this.kodeMuat = false;
            }
        },
        toggleTagPilih(dim, kode) {
            if (!this.pilihTag[dim]) this.pilihTag[dim] = [];

            const i = this.pilihTag[dim].indexOf(kode);
            if (i >= 0) this.pilihTag[dim].splice(i, 1);
            else this.pilihTag[dim].push(kode);
        },
        async simpanTag() {
            const kirim = this.tagKeKirim(this.pilihTag);

            this.busy = true;
            try {
                const { data } = await axios.put(`${API}/${this.tagTarget.id}/tag`, { tag: kirim }, CFG);
                ElMessage.success(data.message || 'Tag tersimpan.');
                this.modalTag = false;
                await this.muat(this.halaman);
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyimpan tag.');
            } finally {
                this.busy = false;
            }
        },

        // ── PERTANYAAN ──────────────────────────────────────────────────────
        bukaTambah() {
            this.form = FORM_KOSONG();
            this.langkah = 1;
            this.salahLangkah = null;
            this.formTag = this.petaTagKosong();
            this.kodePratinjau = '';
            this.modal = true;
        },
        petaTagKosong(sumber = []) {
            const peta = {};
            Object.keys(this.tag).forEach((d) => (peta[d] = []));
            sumber.forEach((t) => {
                if (!peta[t.dimensi]) peta[t.dimensi] = [];
                peta[t.dimensi].push(t.kode);
            });

            return peta;
        },
        bukaSunting(r) {
            this.langkah = 1;
            this.salahLangkah = null;
            this.formTag = this.petaTagKosong(r.tag || []);
            this.kodePratinjau = '';
            this.form = {
                ...FORM_KOSONG(),
                id: r.id, kode: r.kode, kelompok: r.kelompok || '', subKelompok: r.subKelompok || '',
                tipe: r.tipe, label: r.label, bantuan: r.bantuan || '',
                jenis: r.jenis, prioritas: r.prioritas || 'RECOMMENDED',
                durasiDetik: r.durasiDetik ?? 60,
                jawabanDiharapkan: r.jawabanDiharapkan || '',
                redFlag: r.redFlag || '',
                pertanyaanLanjutan: r.pertanyaanLanjutan || '',
                opsi: r.opsi.length ? r.opsi.map((o) => ({ ...o })) : FORM_KOSONG().opsi,
                skalaMin: r.skalaMin ?? 1, skalaMax: r.skalaMax ?? 5,
                labelMin: r.labelMin || '', labelMax: r.labelMax || '',
                bobot: r.bobot, knockoutOperator: r.knockoutOperator,
                knockoutNilai: r.knockoutNilai || '', knockoutPesan: r.knockoutPesan || '',
                wajib: r.wajib, catatan: r.catatan, urutan: r.urutan, sistem: r.sistem,
            };
            this.modal = true;
        },
        gantiTipe() {
            if (this.beropsi(this.form.tipe) && this.form.opsi.length < 2) {
                this.form.opsi = FORM_KOSONG().opsi;
            }
            // Bobot dilepas begitu tipenya tidak bisa dinilai — kalau dibiarkan,
            // simpanan ditolak server dengan pesan yang terasa datang entah dari
            // mana beberapa detik kemudian.
            if (!this.berskor(this.form.tipe)) this.form.bobot = null;
        },
        isiNilai(o) {
            if (o.nilai || !o.label) return;

            o.nilai = o.label.toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 120);
        },
        setBobot(on) {
            this.form.bobot = on ? 1 : null;
            if (!on) this.form.opsi.forEach((o) => (o.skor = null));
        },
        setKnockout(on) {
            this.form.knockoutOperator = on ? '=' : null;
            if (!on) {
                this.form.knockoutNilai = '';
                this.form.knockoutPesan = '';
            }
        },
        /**
         * Selain menyebut APA yang salah, ia menyebut DI LANGKAH MANA.
         *
         * Pesan galat untuk isian yang sedang tidak terlihat adalah pesan yang
         * membingungkan: orang membaca "minimal dua pilihan" sementara di
         * layarnya cuma ada panduan penilaian. Nomor langkahnya dipakai
         * simpan() untuk melompat ke sana lebih dulu.
         */
        periksa() {
            const f = this.form;
            if (!f.label.trim()) return { langkah: 1, pesan: 'Teks pertanyaan belum diisi.' };

            if (this.beropsi(f.tipe)) {
                if (f.opsi.length < 2) return { langkah: 3, pesan: 'Butuh minimal dua pilihan.' };
                if (f.opsi.some((o) => !o.nilai || !o.label)) return { langkah: 3, pesan: 'Ada pilihan yang belum lengkap.' };

                const n = f.opsi.map((o) => o.nilai);
                if (new Set(n).size !== n.length) return { langkah: 3, pesan: 'Ada dua pilihan bernilai sama.' };
            }

            if (this.berskala(f.tipe) && f.skalaMax <= f.skalaMin) {
                return { langkah: 3, pesan: 'Batas atas skala harus lebih besar.' };
            }
            if (f.knockoutOperator && !String(f.knockoutNilai).trim()) {
                return { langkah: 4, pesan: 'Nilai pembanding auto-gugur belum diisi.' };
            }

            // Bukan kerewelan: penyaring pertama saat menyusun template adalah
            // departemen, jadi pertanyaan tanpa departemen tersimpan rapi dan
            // tidak pernah ditemukan siapa pun lagi.
            if (this.adaDimensiTag && !this.adaDeptTerpilih) {
                return {
                    langkah: 5,
                    pesan: 'Pilih minimal satu departemen — tanpa itu pertanyaan ini tidak akan muncul saat menyusun template.',
                };
            }

            return null;
        },
        /**
         * Lanjut ke langkah berikutnya, tapi hanya kalau yang sudah dilewati
         * memang beres.
         *
         * Yang diperiksa cuma galat di langkah ini atau sebelumnya. Menahan
         * orang di langkah 1 karena daftar pilihan di langkah 3 belum diisi
         * adalah menyalahkan dia atas sesuatu yang belum sempat ia kerjakan.
         */
        maju() {
            const salah = this.periksa();
            if (salah && salah.langkah <= this.langkah) {
                this.salahLangkah = salah.langkah;
                this.langkah = salah.langkah;
                ElMessage.warning(salah.pesan);

                return;
            }

            this.salahLangkah = null;
            this.langkah += 1;
        },

        async simpan() {
            const salah = this.periksa();
            if (salah) {
                // Lompat ke langkahnya lebih dulu, baru bicara. Pesan galat
                // tentang isian yang tidak terlihat menyuruh orang mencari
                // sendiri di mana letaknya.
                this.langkah = salah.langkah;
                this.salahLangkah = salah.langkah;
                ElMessage.warning(salah.pesan);

                return;
            }

            this.salahLangkah = null;

            this.busy = true;
            try {
                const kirim = { ...this.form, opsi: this.beropsi(this.form.tipe) ? this.form.opsi : null };
                const { data } = this.form.id
                    ? await axios.put(`${API}/${this.form.id}`, kirim, CFG)
                    : await axios.post(API, kirim, CFG);

                // Tag disimpan lewat endpoint sendiri karena ia tabel pengikat,
                // bukan kolom. Dari sisi orang yang memakainya ini tetap SATU
                // tindakan — id baris baru diambil dari tanggapan simpan tadi,
                // jadi tidak ada langkah kedua yang harus ia ingat sendiri.
                const id = this.form.id || data.result?.id;
                if (id && this.adaDimensiTag) {
                    await axios.put(`${API}/${id}/tag`, { tag: this.tagKeKirim(this.formTag) }, CFG);
                }

                ElMessage.success(data.message || 'Tersimpan.');
                this.modal = false;
                await this.muat(this.halaman);
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.busy = false;
            }
        },

        bukaDuplikat(r) {
            this.dup = { id: r.id, dari: r.kode, kode: '', label: r.label };
            this.modalDup = true;
        },
        async simpanDuplikat() {
            this.busy = true;
            try {
                const { data } = await axios.post(`${API}/${this.dup.id}/duplikat`, { label: this.dup.label }, CFG);
                ElMessage.success(data.message || 'Tersalin.');
                this.modalDup = false;
                await this.muat(this.halaman);
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyalin.');
            } finally {
                this.busy = false;
            }
        },

        mintaToggle(r) {
            this.tanya = {
                judul: r.aktif ? 'Nonaktifkan pertanyaan?' : 'Aktifkan pertanyaan?',
                sub: r.label,
                ikon: r.aktif ? 'bi-toggle-off' : 'bi-toggle-on',
                bahaya: r.aktif,
                tombol: r.aktif ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan',
                nota: r.aktif ? 'Tak lagi ditawarkan saat menyusun template. Yang sudah menariknya tidak berubah.' : '',
                jalan: () => axios.patch(`${API}/${r.id}/toggle`, {}, CFG),
            };
        },
        mintaHapus(r) {
            this.tanya = {
                judul: 'Hapus dari pustaka?',
                sub: r.label,
                ikon: 'bi-trash',
                tombol: 'Ya, Hapus',
                nota: r.pemakaian.template
                    ? `Dipakai ${r.pemakaian.template} template — permintaan akan ditolak. Nonaktifkan saja.`
                    : 'Template tidak akan rusak (mereka punya salinan sendiri), tapi asal-usulnya hilang.',
                jalan: () => axios.delete(`${API}/${r.id}`, CFG),
            };
        },
        async jalankanTanya() {
            if (!this.tanya) return;

            this.busy = true;
            try {
                const res = await this.tanya.jalan();
                ElMessage.success(res?.data?.message || 'Berhasil.');
                this.tanya = null;
                await this.muat(this.halaman);
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memproses.');
            } finally {
                this.busy = false;
            }
        },
    },
};
</script>

<style scoped>
/* Hanya tata letak khas halaman ini. Seluruh kontrol — kotak isian, tombol,
   sakelar, chip, bilah penyaring — hidup di evo-theme.css sebagai `skr-*`,
   karena isi jendela di-teleport ke <body> dan tidak mewarisi apa pun dari
   akar halaman ini. Lihat catatan di kepala berkas. */

.mps-beku { display: flex; align-items: flex-start; gap: 7px; margin-top: 8px; font-size: 12.5px; color: #64748b; }
.mps-beku i { color: #0ea5e9; margin-top: 2px; }
.mps-belum { margin-bottom: 18px; }
.mps-belum code {
    font-family: ui-monospace, Consolas, monospace; font-size: 11.5px;
    background: rgba(100, 116, 139, 0.1); padding: 1px 6px; border-radius: 5px; color: #475569;
}

/* ── REL LANGKAH ─────────────────────────────────────────────────────────────
   Bukan garis bernomor yang cuma menghitung, melainkan daftar bernama: tiap
   langkah menyebut apa yang ada di dalamnya. Angka tanpa nama hanya memberi
   tahu tinggal berapa lagi, bukan apa yang menanti di sana. */
.mps-rail {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(0, 1fr);
    gap: 6px;
}
.mps-rail__s {
    display: flex; align-items: center; gap: 9px; min-width: 0;
    padding: 9px 11px;
    border: 1px solid transparent; border-radius: 11px;
    background: transparent;
    font-family: inherit; text-align: left; cursor: pointer;
    transition: all 0.15s;
}
.mps-rail__s:hover { background: #f1f5f9; }
.mps-rail__n {
    flex: 0 0 auto;
    display: grid; place-items: center;
    width: 25px; height: 25px; border-radius: 8px;
    background: #e2e8f0; color: #64748b;
    font-size: 11.5px; font-weight: 800;
    transition: all 0.15s;
}
.mps-rail__t { min-width: 0; display: flex; flex-direction: column; line-height: 1.3; }
.mps-rail__t b {
    font-size: 12.5px; color: #475569; font-weight: 700;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mps-rail__t small {
    font-size: 10.5px; color: #94a3b8;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* Yang sedang dibuka: bertinta penuh. Yang sudah dilewati: bercentang tapi
   tenang — ia tidak lagi menuntut perhatian. */
.mps-rail__s.is-on {
    border-color: rgba(99, 102, 241, 0.3);
    background: rgba(99, 102, 241, 0.07);
}
.mps-rail__s.is-on .mps-rail__n {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: #fff;
    box-shadow: 0 2px 7px rgba(79, 70, 229, 0.32);
}
.mps-rail__s.is-on .mps-rail__t b { color: #0f172a; }
.mps-rail__s.is-lewat .mps-rail__n { background: rgba(16, 185, 129, 0.14); color: #047857; }
.mps-rail__s.is-bad .mps-rail__n { background: rgba(220, 38, 38, 0.14); color: #b91c1c; }
.mps-rail__s.is-bad .mps-rail__t b { color: #b91c1c; }

@media (max-width: 1100px) {
    .mps-rail__t small { display: none; }
}
@media (max-width: 860px) {
    .mps-rail {
        grid-auto-flow: row;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .mps-rail__t small { display: block; }
}
@media (max-width: 520px) {
    /* Di ponsel namanya tidak muat berdampingan empat-empat; yang tersisa
       hanya nomor, dan nama langkah yang sedang dibuka. */
    .mps-rail {
        grid-auto-flow: column;
        grid-auto-columns: auto;
        grid-template-columns: none;
        justify-content: start;
        gap: 4px;
    }
    .mps-rail__s { padding: 7px 8px; }
    .mps-rail__t { display: none; }
    .mps-rail__s.is-on .mps-rail__t { display: flex; }
    .mps-rail__s.is-on { flex: 1; }
}

.mps-step { animation: mpsMasuk 0.2s ease both; }
@keyframes mpsMasuk {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: none; }
}

/* ── KOTAK PENJELASAN ────────────────────────────────────────────────────────
   Kelompok vs sub-kelompok adalah hal yang paling sering ditanyakan di jendela
   ini, jadi jawabannya ditulis DI LAYAR — bukan di dokumentasi yang tidak akan
   dibuka siapa pun saat sedang mengisi formulir. */
.mps-jelas {
    display: flex; align-items: flex-start; gap: 11px;
    padding: 13px 15px;
    border: 1px solid rgba(14, 165, 233, 0.18);
    border-radius: 12px;
    background: rgba(14, 165, 233, 0.05);
    font-size: 12.5px; line-height: 1.65; color: #475569;
}
.mps-jelas > i { color: #0ea5e9; font-size: 15px; margin-top: 2px; flex: 0 0 auto; }
.mps-jelas b { color: #0f172a; }
.mps-jelas code {
    font-family: ui-monospace, Consolas, monospace; font-size: 11.5px;
    padding: 1px 5px; border-radius: 4px;
    background: rgba(14, 165, 233, 0.12); color: #0369a1;
}

.mps-kodebox {
    padding: 12px 13px;
    border: 1px solid #eef1f7; border-radius: 12px; background: #fbfcfe;
}
.mps-kodeauto em {
    font-style: normal; font-size: 11px; color: #94a3b8;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mps-kodeauto > span { flex: 0 0 auto; }

.mps-nokolom { padding: 26px 20px; }

/* Kotak penjelasan yang sedang memperingatkan: warnanya berganti, bukan cuma
   kalimatnya. Peringatan berwarna biru muda terbaca sebagai keterangan biasa. */
.mps-jelas.is-bad {
    border-color: rgba(245, 158, 11, 0.34);
    background: rgba(245, 158, 11, 0.07);
}
.mps-jelas.is-bad > i { color: #d97706; }
.mps-jelas.is-bad b { color: #92400e; }

.mps-putar { animation: mpsPutar 0.9s linear infinite; }
@keyframes mpsPutar { to { transform: rotate(360deg); } }

/* ── KODE YANG DIBUATKAN SISTEM ─────────────────────────────────────────── */
.mps-kodeauto {
    display: flex; align-items: center; gap: 8px;
    height: 38px; padding: 0 11px;
    border: 1px dashed #c7d2fe; border-radius: 10px;
    background: rgba(99, 102, 241, 0.05);
    font-size: 12.5px; color: #64748b;
}
.mps-kodeauto > i { color: #6366f1; }
.mps-kodeauto.is-ada { border-style: solid; border-color: #e2e8f0; background: #f8fafc; }
.mps-kodeauto code {
    flex: 1; min-width: 0;
    font-family: ui-monospace, Consolas, monospace; font-size: 12.5px;
    font-weight: 700; color: #0f172a;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mps-kodeauto__k { color: #94a3b8; font-size: 11px; }

.mps-opt { display: flex; align-items: center; gap: 9px; }
.mps-opt i { font-size: 13px; width: 16px; }
.mps-opt em { margin-left: auto; font-style: normal; font-size: 11px; color: #cbd5e1; font-variant-numeric: tabular-nums; }

/* ── BILAH TANDAI ────────────────────────────────────────────────────────── */
.mps-massal {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    padding: 12px 16px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.09), rgba(139, 92, 246, 0.06));
    border-bottom: 1px solid rgba(99, 102, 241, 0.18);
}
.mps-massal__n {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 27px; height: 27px; padding: 0 8px; border-radius: 8px;
    background: #4f46e5; color: #fff; font-size: 12.5px; font-weight: 700; font-variant-numeric: tabular-nums;
}
.mps-massal__t { font-size: 13px; color: #4338ca; font-weight: 500; }
.mps-massal__s { width: 250px; flex: 0 1 250px; }
.mps-massal__x { margin-left: auto; }

.mps-slide-enter-active, .mps-slide-leave-active { transition: opacity 0.18s, transform 0.18s; }
.mps-slide-enter-from, .mps-slide-leave-to { opacity: 0; transform: translateY(-6px); }

/* ── DAFTAR ──────────────────────────────────────────────────────────────── */
.mps-list { min-height: 220px; }
.mps-head {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 16px; border-bottom: 1px solid #eef1f7; background: #fafbfe;
    font-size: 11.5px; color: #94a3b8;
}
.mps-head__h { margin-left: auto; font-variant-numeric: tabular-nums; }
.mps-cek { flex: 0 0 auto; display: flex; align-items: center; padding-top: 2px; }
.mps-cek input { accent-color: #6366f1; width: 16px; height: 16px; cursor: pointer; }

.mps-row {
    display: grid;
    grid-template-columns: 26px minmax(0, 1fr) 182px 112px auto;
    gap: 16px; align-items: start;
    padding: 16px; border-bottom: 1px solid #f2f5fa;
    transition: background 0.13s;
}
.mps-row:last-child { border-bottom: 0; }
.mps-row:hover { background: #fbfcfe; }
.mps-row.is-tandai { background: rgba(99, 102, 241, 0.045); }
.mps-row.is-mati { opacity: 0.5; }

.mps-isi { min-width: 0; }
.mps-isi__t { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
.mps-isi__t h3 {
    margin: 0; font-size: 14.5px; font-weight: 600; color: #0f172a;
    line-height: 1.45; letter-spacing: -0.005em;
}
.mps-meta { display: flex; flex-wrap: wrap; gap: 6px 10px; align-items: center; margin-top: 7px; }
.mps-meta code {
    font-family: ui-monospace, Consolas, monospace; font-size: 11px;
    color: #64748b; background: #f1f5f9; padding: 2px 7px; border-radius: 5px;
}
.mps-meta__s { font-size: 11.5px; color: #94a3b8; }
.mps-meta__s--w { display: inline-flex; align-items: center; gap: 3px; font-variant-numeric: tabular-nums; }
.mps-bantu {
    margin: 8px 0 0; font-size: 12px; color: #94a3b8; line-height: 1.55;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.mps-opsi { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 9px; }
.mps-opsi span {
    font-size: 11.5px; color: #64748b; background: #f8fafc;
    border: 1px solid #eef1f7; padding: 3px 9px; border-radius: 7px; white-space: nowrap;
}
.mps-opsi span em {
    font-style: normal; font-weight: 700; color: #047857; margin-left: 5px;
    font-size: 10.5px; font-variant-numeric: tabular-nums;
}
.mps-opsi__l { color: #cbd5e1 !important; background: transparent !important; border-color: transparent !important; }

.mps-dept { display: flex; flex-wrap: wrap; gap: 5px; align-items: flex-start; padding-top: 2px; }
.mps-belum-tag { font-size: 11.5px; color: #cbd5e1; }
.mps-pop__g { margin-bottom: 12px; }
.mps-pop__g:last-child { margin-bottom: 0; }
.mps-pop__h {
    display: block; font-size: 10px; font-weight: 700; letter-spacing: 0.09em;
    text-transform: uppercase; color: #94a3b8; margin-bottom: 7px;
}
.mps-pop__g > div { display: flex; flex-wrap: wrap; gap: 5px; }

.mps-pakai { font-size: 12.5px; color: #64748b; padding-top: 2px; }
.mps-pakai b { font-size: 15px; color: #0f172a; font-variant-numeric: tabular-nums; }
.mps-pakai small { font-size: 11.5px; color: #94a3b8; margin-left: 4px; }
.mps-pakai .skr-pil { display: inline-flex; margin-top: 5px; }
.mps-aksi { display: flex; gap: 2px; flex: 0 0 auto; }

/* ── ISI JENDELA ─────────────────────────────────────────────────────────── */
.mps-skala { display: grid; grid-template-columns: 100px 100px 1fr 1fr; gap: 12px; }
.mps-sakelar { display: flex; flex-wrap: wrap; gap: 12px 24px; align-items: center; }
.mps-mati { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: #b6bfd0; }
.mps-bobot { display: flex; align-items: center; gap: 9px; }
.mps-bobot__in { width: 92px; }
.mps-bobot small { font-size: 11.5px; color: #94a3b8; }
.mps-korow { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; margin-bottom: 9px; }
.mps-korow > .skr-ctl { flex: 1; min-width: 160px; }
.mps-kolbl { font-size: 12.5px; color: #b91c1c; white-space: nowrap; font-weight: 500; }
.mps-koop { width: 168px; flex: 0 0 auto; }
.mps-tagnote { margin-bottom: 20px; font-size: 12.5px; color: #475569; }

@media (max-width: 1280px) {
    .mps-row { grid-template-columns: 26px minmax(0, 1fr) 156px auto; }
    .mps-pakai { display: none; }
}
@media (max-width: 900px) {
    .mps-row { grid-template-columns: 24px minmax(0, 1fr); gap: 11px; }
    .mps-dept, .mps-aksi { grid-column: 2; }
    .mps-aksi { margin-top: 2px; }
    .mps-massal__s { width: 100%; flex: 1 1 100%; }
    .mps-massal__x { margin-left: 0; }
    .mps-skala { grid-template-columns: 1fr 1fr; }
    .mps-koop { width: 100%; }
    .mps-korow > .skr-ctl { min-width: 0; }
}
</style>
