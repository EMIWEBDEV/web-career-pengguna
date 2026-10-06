<!--
    MASTER PHONE SCREENING — pustaka template pertanyaan skrining.

    ══ SATU ATURAN YANG MENJELASKAN SELURUH LAYAR INI ═════════════════════════

    VERSI TERBIT TIDAK PERNAH DISUNTING.

    Menyunting template yang sudah dipakai selalu lewat: buat draf → sunting →
    terbitkan. Versi lama diarsipkan, tidak dihapus — ia satu-satunya cara
    membaca ulang sesi kandidat yang memakainya.

    Karena itu penyunting pertanyaan di panel kanan HANYA muncul untuk versi
    berstatus DRAF. Untuk versi terbit dan arsip, isinya ditampilkan sebagai
    bacaan — lengkap, tapi tanpa satu pun kotak isian. Itu bukan pembatasan
    yang disembunyikan: tombol "Buat Draf Baru" berdiri tepat di sebelahnya.

    ══ BENTUK JELAJAH, BUKAN TABEL ════════════════════════════════════════════

    Template berisi 50 pertanyaan tidak muat di baris tabel, dan membandingkan
    dua template berarti berpindah-pindah tanpa kehilangan tempat. ExplorerLayout
    memberi keduanya: daftar diam di kiri, isinya berganti di kanan.
-->
<template>
    <Head title="Master Template Skrining" />

    <div class="wca msk">
        <!-- ══ KEPALA ════════════════════════════════════════════════════════ -->
        <div class="wca-phead">
            <div>
                <h1>Master Template Skrining</h1>
                <p>
                    Susunan pertanyaan yang diisi <b>rekruter</b> saat menskrining kandidat.
                    Isinya ditarik dari <b>Master Pertanyaan Skrining</b>, lalu dipasang per loker
                    di Program Kegiatan — satu program bisa memakai template berbeda tiap MPP.
                </p>
                <p class="msk-beku">
                    <i class="bi bi-snow"></i>
                    Menyunting template <b>tidak menulis ulang sesi yang sudah tercatat</b>: tiap
                    sesi membekukan versi dan salinan pertanyaannya sendiri.
                </p>
            </div>
            <button class="skr-btn skr-btn--isi" @click="bukaTambah">
                <i class="bi bi-plus-lg"></i> Template Baru
            </button>
        </div>

        <!-- Skema belum dijalankan. Bukan pesan galat: fitur ini memang diam
             sampai berkas .sql-nya dijalankan, dan halaman lain tidak terganggu. -->
        <div v-if="!siap && !loading" class="wca-note wca-note--warn msk-belum">
            <i class="bi bi-database-exclamation"></i>
            <span>
                Skema phone screening belum ada di basis data ini. Jalankan
                <code>database/sql/2026-08-24-phone-screening.sql</code> lebih dulu, lalu muat ulang
                halaman ini.
            </span>
        </div>

        <template v-else>
            <div class="wca-stats">
                <div class="wca-stat">
                    <div class="wca-stat__top">
                        <span class="wca-stat__ico" style="background: rgba(99, 102, 241, 0.14); color: #4338ca">
                            <i class="bi bi-collection"></i>
                        </span>
                    </div>
                    <div class="wca-stat__num">{{ daftar.length }}</div>
                    <div class="wca-stat__label">Template</div>
                </div>
                <div class="wca-stat">
                    <div class="wca-stat__top">
                        <span class="wca-stat__ico" style="background: rgba(5, 150, 105, 0.14); color: #047857">
                            <i class="bi bi-broadcast"></i>
                        </span>
                    </div>
                    <div class="wca-stat__num">{{ jmlTerbit }}</div>
                    <div class="wca-stat__label">Sudah terbit</div>
                </div>
                <div class="wca-stat">
                    <div class="wca-stat__top">
                        <span class="wca-stat__ico" style="background: rgba(245, 158, 11, 0.14); color: #b45309">
                            <i class="bi bi-pencil-square"></i>
                        </span>
                    </div>
                    <div class="wca-stat__num">{{ jmlDraf }}</div>
                    <div class="wca-stat__label">Punya draf</div>
                </div>
                <div class="wca-stat">
                    <div class="wca-stat__top">
                        <span class="wca-stat__ico" style="background: rgba(14, 165, 233, 0.14); color: #0369a1">
                            <i class="bi bi-link-45deg"></i>
                        </span>
                    </div>
                    <div class="wca-stat__num">{{ jmlTerikat }}</div>
                    <div class="wca-stat__label">Terpasang di loker</div>
                </div>
            </div>

            <ExplorerLayout
                :items="tersaring"
                :selected-id="pilih"
                :loading="loading"
                item-key="id"
                @select="gantiPilih"
            >
                <!-- ══ KIRI: PENCARIAN ═══════════════════════════════════════ -->
                <template #aside-head>
                    <div class="msk-cari">
                        <div class="skr-cari">
                            <i class="bi bi-search"></i>
                            <input v-model="cari" type="text" placeholder="Cari nama, kode, keterangan…" />
                            <button v-if="cari" class="skr-cari__x" @click="cari = ''"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <button
                            class="skr-btn skr-btn--ikon" :class="{ 'is-on': saringBuka }"
                            :title="saringBuka ? 'Sembunyikan penyaring' : 'Penyaring'"
                            @click="saringBuka = !saringBuka"
                        >
                            <i class="bi bi-sliders"></i>
                            <span v-if="jmlSaring" class="skr-btn__n">{{ jmlSaring }}</span>
                        </button>
                    </div>

                    <transition name="skr-turun">
                        <div v-if="saringBuka" class="skr-lanjut msk-saring">
                            <div class="skr-lanjut__f">
                                <label class="skr-lbl">Keadaan</label>
                                <el-select v-model="saring.status" class="skr-sel" popper-class="skr-pop">
                                    <el-option v-for="o in opsiStatus" :key="o.v" :label="o.t" :value="o.v" />
                                </el-select>
                            </div>
                            <!-- Kategori hanya ditawarkan bila memang ada lebih dari
                                 satu yang boleh dilihat akun ini. -->
                            <div v-if="kategoriOpsi.length > 1" class="skr-lanjut__f">
                                <label class="skr-lbl">Kategori</label>
                                <el-select v-model="saring.kategori" clearable placeholder="Semua" class="skr-sel" popper-class="skr-pop">
                                    <el-option v-for="k in kategoriOpsi" :key="k.kode" :label="k.label" :value="k.kode" />
                                </el-select>
                            </div>
                            <div class="skr-lanjut__f msk-saring__lebar">
                                <label class="skr-lbl">Dibuat antara</label>
                                <el-date-picker
                                    v-model="saring.tanggal" type="daterange" unlink-panels
                                    range-separator="–" start-placeholder="Dari" end-placeholder="Sampai"
                                    value-format="YYYY-MM-DD" format="DD MMM YYYY"
                                    class="skr-sel" popper-class="skr-pop"
                                />
                            </div>
                            <div class="skr-lanjut__f msk-saring__lebar">
                                <label class="skr-lbl">Urutkan</label>
                                <el-select v-model="saring.urut" class="skr-sel" popper-class="skr-pop">
                                    <el-option v-for="o in opsiUrut" :key="o.v" :label="o.t" :value="o.v" />
                                </el-select>
                            </div>
                        </div>
                    </transition>

                    <div class="msk-hitung">
                        <span>{{ tersaring.length }} dari {{ daftar.length }} template</span>
                        <button v-if="adaSaring" class="msk-hitung__x" @click="resetSaring">
                            <i class="bi bi-arrow-counterclockwise"></i> Bersihkan
                        </button>
                    </div>
                </template>

                <!-- ══ KIRI: SATU BARIS ══════════════════════════════════════ -->
                <template #item="{ item }">
                    <div class="msk-row">
                        <div class="msk-row__head">
                            <b>{{ item.nama }}</b>
                            <span v-if="!item.aktif" class="msk-pill msk-pill--off">Nonaktif</span>
                        </div>
                        <div class="msk-row__meta">
                            <code>{{ item.kode }}</code>
                            <span v-if="item.kategori" class="msk-kat">{{ item.kategori }}</span>
                        </div>
                        <div class="msk-row__badges">
                            <span v-if="item.versiTerbit" class="msk-pill msk-pill--live">
                                <i class="bi bi-broadcast"></i> v{{ item.versiTerbit }} terbit
                            </span>
                            <span v-else class="msk-pill msk-pill--none">Belum terbit</span>
                            <span v-if="item.versiDraf" class="msk-pill msk-pill--draf">
                                <i class="bi bi-pencil"></i> draf v{{ item.versiDraf }}
                            </span>
                            <span v-if="item.dipakai" class="msk-pill msk-pill--pakai">
                                {{ item.dipakai }} sesi
                            </span>
                        </div>
                    </div>
                </template>

                <template #empty>
                    <i class="bi bi-inbox"></i>
                    {{ cari ? 'Tidak ada template yang cocok.' : 'Belum ada template. Buat yang pertama.' }}
                </template>

                <!-- ══ KANAN: KEPALA ═════════════════════════════════════════ -->
                <template #detail-head="{ item }">
                    <div class="msk-dhead">
                        <div class="msk-dhead__t">
                            <h2>{{ item.nama }}</h2>
                            <div class="msk-dhead__sub">
                                <code>{{ item.kode }}</code>
                                <span v-if="item.kategori">· {{ item.kategori }}</span>
                                <span v-if="item.deskripsi">· {{ item.deskripsi }}</span>
                            </div>
                        </div>
                        <div class="msk-dhead__act">
                            <el-tooltip content="Sunting nama & keterangan" placement="bottom">
                                <button class="skr-ib" @click="bukaSunting(item)"><i class="bi bi-pencil"></i></button>
                            </el-tooltip>
                            <el-tooltip content="Salin jadi template baru" placement="bottom">
                                <button class="skr-ib" @click="bukaDuplikat(item)"><i class="bi bi-files"></i></button>
                            </el-tooltip>
                            <el-tooltip :content="item.aktif ? 'Nonaktifkan' : 'Aktifkan'" placement="bottom">
                                <button class="skr-ib" @click="mintaToggle(item)">
                                    <i class="bi" :class="item.aktif ? 'bi-toggle-on' : 'bi-toggle-off'"></i>
                                </button>
                            </el-tooltip>
                            <el-tooltip content="Hapus template" placement="bottom">
                                <button class="skr-ib skr-ib--x" @click="mintaHapus(item)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </el-tooltip>
                        </div>
                    </div>
                </template>

                <!-- ══ KANAN: ISI ════════════════════════════════════════════ -->
                <template #detail="{ item }">
                    <!-- Bilah versi. Urutannya terbaru di kiri: yang paling
                         sering dibuka adalah draf atau versi terbit, bukan
                         arsip dari dua tahun lalu. -->
                    <div class="msk-vbar">
                        <div class="msk-vbar__list">
                            <button
                                v-for="v in versiUrut(item)"
                                :key="v.id"
                                type="button"
                                class="msk-vtab"
                                :class="[`is-${v.status.toLowerCase()}`, { 'is-on': v.id === versiPilih }]"
                                @click="bukaVersi(v.id)"
                            >
                                <span class="msk-vtab__n">v{{ v.versi }}</span>
                                <span class="msk-vtab__s">{{ labelStatus(v.status) }}</span>
                                <span class="msk-vtab__q">{{ v.jmlPertanyaan }} pertanyaan</span>
                            </button>
                        </div>
                        <button
                            v-if="!item.versiDraf"
                            class="skr-btn msk-vbar__new"
                            @click="mintaDrafBaru(item)"
                        >
                            <i class="bi bi-plus-lg"></i> Buat Draf Baru
                        </button>
                    </div>

                    <div v-if="muatVersi" class="msk-loading">Memuat pertanyaan…</div>

                    <template v-else-if="versiAktif">
                        <!-- Penjelasan status. Ditulis di muka karena inilah yang
                             menentukan apakah panel di bawahnya bisa disunting. -->
                        <div class="wca-note msk-vnote" :class="`msk-vnote--${versiAktif.status.toLowerCase()}`">
                            <i class="bi" :class="ikonStatus(versiAktif.status)"></i>
                            <span v-if="versiAktif.status === 'DRAFT'">
                                <b>Draf v{{ versiAktif.versi }}</b> — bisa disunting bebas. Belum dipakai
                                sesi mana pun sampai diterbitkan.
                            </span>
                            <span v-else-if="versiAktif.status === 'PUBLISHED'">
                                <b>Versi terbit</b> — dipakai semua sesi skrining baru. Tidak bisa
                                disunting; buat draf baru untuk mengubahnya.
                            </span>
                            <span v-else>
                                <b>Arsip</b> — pernah terbit, sekarang digantikan. Ditahan supaya sesi
                                lama yang memakainya tetap terbaca utuh.
                            </span>
                        </div>

                        <!-- ── PENYUNTING (hanya draf) ────────────────────── -->
                        <div v-if="bisaSunting" class="msk-edit">
                            <div class="msk-edit__bar">
                                <div class="msk-edit__info">
                                    <b>{{ draf.length }}</b> pertanyaan
                                    <span v-if="totalBobot > 0">· total bobot {{ totalBobot }}</span>
                                    <span v-if="jmlKnockout" class="msk-edit__ko">
                                        · {{ jmlKnockout }} auto-gugur
                                    </span>
                                </div>
                                <div class="msk-edit__btns">
                                    <!-- Bank berdiri di KIRI "Pertanyaan" karena itulah
                                         jalan yang seharusnya dipilih lebih dulu: menarik
                                         yang sudah ada lebih baik daripada mengetik ulang
                                         pertanyaan yang sudah dirumuskan orang lain. -->
                                    <button class="skr-btn skr-btn--ind" @click="bukaBank">
                                        <i class="bi bi-collection"></i> Ambil dari Pustaka
                                    </button>
                                    <el-tooltip
                                        v-if="adaDariBank"
                                        content="Tarik ulang redaksi terbaru dari bank (yang sudah dirombak di sini dilewati)"
                                        placement="bottom"
                                    >
                                        <button class="skr-ib" @click="mintaSegarkan"><i class="bi bi-arrow-repeat"></i></button>
                                    </el-tooltip>
                                    <button class="skr-btn" @click="tambahPertanyaan">
                                        <i class="bi bi-plus-lg"></i> Tulis Sendiri
                                    </button>
                                    <button class="skr-btn" :disabled="simpanBusy" @click="simpanDraf">
                                        <i class="bi bi-save"></i> {{ simpanBusy ? 'Menyimpan…' : 'Simpan Draf' }}
                                    </button>
                                    <button class="skr-btn skr-btn--isi" :disabled="!draf.length" @click="mintaTerbit">
                                        <i class="bi bi-broadcast"></i> Terbitkan
                                    </button>
                                    <el-tooltip v-if="versiAktif.versi > 1" content="Buang draf ini" placement="bottom">
                                        <button class="skr-ib skr-ib--x" @click="mintaBuangDraf">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </el-tooltip>
                                </div>
                            </div>

                            <div v-if="!draf.length" class="skr-kosong">
                                <i class="bi bi-question-circle"></i>
                                <strong>Draf ini belum berisi pertanyaan</strong>
                                <small>Tarik dari Master Pertanyaan Skrining, atau tulis sendiri kalau memang belum ada di sana.</small>
                                <button class="skr-btn skr-btn--isi" @click="bukaBank">
                                    <i class="bi bi-collection"></i> Ambil dari Pustaka
                                </button>
                            </div>

                            <div
                                v-for="(p, i) in draf"
                                :key="p._k"
                                class="msk-q"
                                :class="{ 'is-ko': p.knockout }"
                            >
                                <div class="msk-q__head">
                                    <span class="msk-q__no">{{ i + 1 }}</span>
                                    <!-- Asal-usul. Yang bertanda "dirombak" TIDAK ikut
                                         tersegarkan dari bank — dan orang perlu tahu itu
                                         sebelum menekan tombol segarkan, bukan sesudah. -->
                                    <el-tooltip
                                        v-if="p.bankKode"
                                        :content="p.ubahan
                                            ? `Dari bank \u201c${p.bankKode}\u201d, sudah dirombak di template ini — tidak ikut disegarkan`
                                            : `Dari bank \u201c${p.bankKode}\u201d, masih sama dengan pustaka`"
                                        placement="top"
                                    >
                                        <span class="msk-asal" :class="{ 'is-ubah': p.ubahan }">
                                            <i class="bi" :class="p.ubahan ? 'bi-pencil-fill' : 'bi-patch-question-fill'"></i>
                                        </span>
                                    </el-tooltip>
                                    <input
                                        v-model="p.label"
                                        class="msk-q__label"
                                        type="text"
                                        placeholder="Tulis pertanyaannya…"
                                        maxlength="500"
                                    />
                                    <div class="msk-q__tools">
                                        <button class="skr-ib" :disabled="i === 0" @click="geser(i, -1)">
                                            <i class="bi bi-arrow-up"></i>
                                        </button>
                                        <button class="skr-ib" :disabled="i === draf.length - 1" @click="geser(i, 1)">
                                            <i class="bi bi-arrow-down"></i>
                                        </button>
                                        <button class="skr-ib" @click="salinPertanyaan(i)">
                                            <i class="bi bi-files"></i>
                                        </button>
                                        <button class="skr-ib skr-ib--x" @click="hapusPertanyaan(i)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="msk-q__grid">
                                    <div>
                                        <label class="skr-lbl">Kode</label>
                                        <input
                                            v-model="p.kode"
                                            class="skr-ctl skr-ctl--mono"
                                            type="text"
                                            placeholder="gaji_harapan"
                                            maxlength="40"
                                            @blur="rapikanKode(p)"
                                        />
                                    </div>
                                    <div>
                                        <label class="skr-lbl">Tipe</label>
                                        <el-select v-model="p.tipe" class="skr-sel" popper-class="skr-pop" @change="gantiTipe(p)">
                                            <el-option v-for="t in tipe" :key="t" :label="labelTipe(t)" :value="t" />
                                        </el-select>
                                    </div>
                                    <div>
                                        <label class="skr-lbl">
                                            Kelompok
                                            <InfoTip>
                                                Judul bagian yang memisahkan pertanyaan di layar rekruter.
                                                Pertanyaan yang ditarik dari pustaka membawa kelompoknya
                                                sendiri; yang ditulis di sini boleh disamakan supaya
                                                keduanya menyatu jadi satu bagian.
                                            </InfoTip>
                                        </label>
                                        <el-select
                                            v-model="p.seksi"
                                            filterable allow-create default-first-option clearable
                                            placeholder="Pilih atau ketik baru"
                                            class="skr-sel" popper-class="skr-pop"
                                        >
                                            <el-option v-for="sk in seksiTerpakai" :key="sk" :label="sk" :value="sk" />
                                        </el-select>
                                    </div>
                                    <div class="msk-q__flags">
                                        <label class="skr-sw" :class="{ 'is-on': p.wajib }">
                                            <input v-model="p.wajib" type="checkbox" /><span class="skr-sw__t"></span><span>Wajib</span>
                                        </label>
                                        <label class="skr-sw" :class="{ 'is-on': p.catatan }">
                                            <input v-model="p.catatan" type="checkbox" /><span class="skr-sw__t"></span><span>Catatan</span>
                                        </label>
                                    </div>
                                </div>

                                <input
                                    v-model="p.bantuan"
                                    class="skr-ctl msk-q__bantu"
                                    type="text"
                                    placeholder="Petunjuk kecil di bawah pertanyaan (opsional)"
                                    maxlength="500"
                                />

                                <!-- ── OPSI ──────────────────────────────── -->
                                <div v-if="beropsi(p.tipe)" class="msk-opsi">
                                    <div class="msk-opsi__head">
                                        <span>Pilihan jawaban</span>
                                        <!-- Kolom skor hanya berarti bila pertanyaannya
                                             dibobot. Menampilkannya selalu membuat orang
                                             mengisi angka yang tidak akan pernah dipakai. -->
                                        <small v-if="p.bobot === null">Isi bobot di bawah agar bisa diberi skor</small>
                                        <button class="skr-ib" @click="tambahOpsi(p)"><i class="bi bi-plus-lg"></i></button>
                                    </div>
                                    <div v-for="(o, oi) in p.opsi" :key="oi" class="msk-opsi__row">
                                        <input v-model="o.label" class="skr-ctl" type="text" placeholder="Label" maxlength="200" @blur="isiNilai(o)" />
                                        <input v-model="o.nilai" class="skr-ctl skr-ctl--mono" type="text" placeholder="NILAI" maxlength="120" />
                                        <input
                                            v-if="p.bobot !== null" v-model.number="o.skor"
                                            class="skr-ctl skr-ctl--num msk-skor" type="number"
                                            placeholder="0" min="-999" max="999"
                                        />
                                        <button class="skr-ib skr-ib--x" @click="p.opsi.splice(oi, 1)">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                    <div v-if="p.opsi.length < 2" class="skr-warn">
                                        <i class="bi bi-exclamation-triangle"></i> Minimal dua pilihan — satu pilihan bukan pertanyaan.
                                    </div>
                                </div>

                                <!-- ── SKALA ─────────────────────────────── -->
                                <div v-if="berskala(p.tipe)" class="msk-skala">
                                    <div>
                                        <label class="skr-lbl">Dari</label>
                                        <input v-model.number="p.skalaMin" class="skr-ctl skr-ctl--num" type="number" min="0" max="100" />
                                    </div>
                                    <div>
                                        <label class="skr-lbl">Sampai</label>
                                        <input v-model.number="p.skalaMax" class="skr-ctl skr-ctl--num" type="number" min="1" max="100" />
                                    </div>
                                    <div>
                                        <label class="skr-lbl">Label bawah</label>
                                        <input v-model="p.labelMin" class="skr-ctl" type="text" placeholder="Sangat kurang" maxlength="100" />
                                    </div>
                                    <div>
                                        <label class="skr-lbl">Label atas</label>
                                        <input v-model="p.labelMax" class="skr-ctl" type="text" placeholder="Sangat baik" maxlength="100" />
                                    </div>
                                </div>

                                <!-- ── BOBOT & AUTO-GUGUR ────────────────── -->
                                <div class="msk-q__foot">
                                    <div v-if="berskor(p.tipe)" class="msk-bobot">
                                        <label class="skr-sw" :class="{ 'is-on': p.bobot !== null }">
                                            <input type="checkbox" :checked="p.bobot !== null" @change="setBobot(p, $event.target.checked)" />
                                            <span class="skr-sw__t"></span><span>Masuk skor</span>
                                        </label>
                                        <input
                                            v-if="p.bobot !== null" v-model.number="p.bobot"
                                            class="skr-ctl skr-ctl--num msk-skor" type="number" min="0" max="9999"
                                        />
                                        <small v-if="p.bobot !== null">× nilai jawaban</small>
                                    </div>
                                    <div v-else class="msk-bobot msk-bobot--mati">
                                        <i class="bi bi-slash-circle"></i>
                                        Tipe {{ labelTipe(p.tipe) }} tidak dinilai angka oleh sistem
                                    </div>

                                    <label class="skr-sw skr-sw--bad" :class="{ 'is-on': p.knockout }">
                                        <input v-model="p.knockout" type="checkbox" /><span class="skr-sw__t"></span><span>Auto-gugur</span>
                                    </label>
                                </div>

                                <div v-if="p.knockout" class="msk-ko">
                                    <div class="msk-ko__lead">
                                        <i class="bi bi-exclamation-octagon-fill"></i>
                                        Kandidat <b>ditandai gugur</b> bila jawabannya
                                    </div>
                                    <div class="msk-ko__row">
                                        <el-select v-model="p.knockoutOperator" placeholder="operator" class="skr-sel" popper-class="skr-pop">
                                            <el-option v-for="o in operator" :key="o" :label="labelOperator(o)" :value="o" />
                                        </el-select>
                                        <input v-model="p.knockoutNilai" class="skr-ctl" type="text" :placeholder="phNilai(p)" maxlength="500" />
                                    </div>
                                    <input
                                        v-model="p.knockoutPesan"
                                        class="skr-ctl"
                                        type="text"
                                        placeholder="Alasan yang dicatat (mis. “Gaji harapan di atas rentang MPP”)"
                                        maxlength="500"
                                    />
                                    <small class="msk-ko__note">
                                        Penandaan ini <b>rekomendasi</b>, bukan keputusan — palu tetap
                                        diketuk lewat mesin keputusan tahap.
                                    </small>
                                </div>
                            </div>

                        </div>

                        <!-- ── BACAAN (versi terbit / arsip) ──────────────── -->
                        <div v-else class="msk-baca">
                            <div v-if="!bacaan.length" class="skr-kosong">
                                <i class="bi bi-question-circle"></i>
                                <strong>Versi ini tidak berisi pertanyaan</strong>
                            </div>
                            <div v-for="(grup, nama) in bacaanGrup" :key="nama" class="msk-baca__grup">
                                <h4 v-if="nama !== '_'">{{ nama }}</h4>
                                <div v-for="p in grup" :key="p.kode" class="msk-baca__q">
                                    <div class="msk-baca__t">
                                        <span class="msk-baca__no">{{ p.urutan }}</span>
                                        <div>
                                            <b>{{ p.label }}</b>
                                            <small v-if="p.bantuan">{{ p.bantuan }}</small>
                                        </div>
                                        <div class="msk-baca__tags">
                                            <span class="msk-tag">{{ labelTipe(p.tipe) }}</span>
                                            <span v-if="p.wajib" class="msk-tag msk-tag--wajib">wajib</span>
                                            <span v-if="p.bobot !== null" class="msk-tag msk-tag--bobot">bobot {{ p.bobot }}</span>
                                            <span v-if="p.knockout" class="msk-tag msk-tag--ko">auto-gugur</span>
                                        </div>
                                    </div>
                                    <div v-if="p.opsi.length" class="msk-baca__opsi">
                                        <span v-for="o in p.opsi" :key="o.nilai">
                                            {{ o.label }}<em v-if="o.skor !== null"> ({{ o.skor }})</em>
                                        </span>
                                    </div>
                                    <div v-else-if="p.skalaMax !== null" class="msk-baca__opsi">
                                        Skala {{ p.skalaMin }}–{{ p.skalaMax }}
                                        <em v-if="p.labelMin || p.labelMax">({{ p.labelMin }} → {{ p.labelMax }})</em>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="skr-audit">
                        <span class="skr-audit__av" :style="{ background: warnaOrang(item.diperbaruiOleh) }">
                            {{ inisial(item.diperbaruiOleh) }}
                        </span>
                        <span>Terakhir diubah oleh <b>{{ item.diperbaruiOleh || 'Sistem' }}</b></span>
                        <span class="skr-audit__at">
                            <i class="bi bi-clock"></i> {{ tglJam(item.diperbaruiPada) }}
                        </span>
                        <span v-if="item.terikat" class="skr-audit__at">
                            <i class="bi bi-link-45deg"></i> terpasang di {{ item.terikat }} loker/program
                        </span>
                    </div>
                </template>
            </ExplorerLayout>
        </template>

        <!-- ══ MODAL: TEMPLATE ═══════════════════════════════════════════════ -->
        <AdminModal
            :show="modal"
            :title="form.id ? 'Sunting Template' : 'Template Baru'"
            :subtitle="form.id ? form.kode : 'Kepala template — pertanyaannya diisi setelah ini'"
            icon="bi-telephone-inbound"
            size="lg"
            :busy="busy"
            @close="modal = false"
            @save="simpanTemplate"
        >
            <div class="msk-form">
                <div class="msk-form__row">
                    <!-- ══ KODE DIBUATKAN SISTEM ═════════════════════════
                         Kode template menyambungkan pengikatan loker dengan sesi
                         yang sudah tercatat. Salah ketik satu huruf di sini tidak
                         menimbulkan galat apa pun — ia baru terasa berbulan-bulan
                         kemudian ketika laporannya memisahkan satu template jadi
                         dua. Karena itu tidak lagi diketik tangan.

                         Saat menyunting, kodenya masih boleh diganti — tapi harus
                         diminta lebih dulu, supaya tidak berganti karena tidak
                         sengaja tersenggol. -->
                    <div>
                        <label class="skr-lbl">Kode</label>
                        <div v-if="!form.id" class="msk-kodeauto">
                            <i class="bi bi-magic"></i>
                            <span>Dibuatkan sistem dari namanya</span>
                        </div>
                        <template v-else-if="!ubahKode">
                            <div class="msk-kodeauto is-ada">
                                <code>{{ form.kode }}</code>
                                <button class="msk-kodeauto__u" @click="ubahKode = true">Ubah</button>
                            </div>
                            <small class="skr-hint">Mengganti kode ikut memperbarui pengikatan loker.</small>
                        </template>
                        <template v-else>
                            <input
                                v-model="form.kode"
                                class="skr-ctl skr-ctl--mono"
                                type="text"
                                placeholder="SKR-HR-UMUM"
                                maxlength="30"
                                @input="form.kode = form.kode.toUpperCase()"
                            />
                            <small class="skr-warn">
                                <i class="bi bi-exclamation-triangle"></i>
                                Sesi yang sudah tercatat menyimpan kode lama — hasilnya tidak lagi
                                tergabung dengan yang baru.
                            </small>
                        </template>
                    </div>
                    <!-- ══ KATEGORI IKUT HAK AKSES ═══════════════════════
                         Akun yang cuma berhak SATU kategori tidak ditawari
                         memilih: kategorinya dipasang otomatis dan ditampilkan
                         sebagai keterangan. Kotak berisi satu pilihan yang
                         sudah pasti hanya menyisakan pertanyaan apa gunanya —
                         dan lebih buruk, mengundang orang mengosongkannya. -->
                    <div>
                        <label class="skr-lbl">Kategori</label>
                        <el-select
                            v-if="kategoriOpsi.length > 1"
                            v-model="form.kategori" placeholder="Pilih kategori" clearable
                            class="skr-sel" popper-class="skr-pop"
                        >
                            <el-option v-for="k in kategoriOpsi" :key="k.kode" :label="k.label" :value="k.kode" />
                        </el-select>
                        <div v-else-if="kategoriOpsi.length === 1" class="msk-katfix">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span>{{ kategoriOpsi[0].label }}</span>
                            <small>sesuai hak akses Anda</small>
                        </div>
                        <div v-else class="msk-katfix msk-katfix--kosong">
                            <i class="bi bi-exclamation-triangle"></i>
                            <span>Belum ada kategori yang diizinkan untuk akun ini</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="skr-lbl">Nama <span class="skr-req">*</span></label>
                    <input v-model="form.nama" class="skr-ctl" type="text" placeholder="Skrining HR — Umum" maxlength="120" />
                </div>

                <div>
                    <label class="skr-lbl">Keterangan</label>
                    <input v-model="form.deskripsi" class="skr-ctl" type="text" placeholder="Dipakai untuk posisi staf non-teknis" maxlength="500" />
                </div>

                <div>
                    <label class="skr-lbl">Petunjuk untuk rekruter</label>
                    <textarea
                        v-model="form.petunjuk"
                        class="skr-ctl"
                        rows="3"
                        placeholder="Ditampilkan di atas kuesioner saat sesi dibuka — mis. perkenalan, izin merekam, perkiraan durasi."
                        maxlength="1000"
                    ></textarea>
                </div>
            </div>
        </AdminModal>

        <!-- ══ MODAL: DUPLIKAT ═══════════════════════════════════════════════ -->
        <AdminModal
            :show="modalDup"
            title="Salin Template"
            :subtitle="`Menyalin dari ${dup.dari}`"
            icon="bi-files"
            :busy="busy"
            save-label="Salin"
            @close="modalDup = false"
            @save="simpanDuplikat"
        >
            <div class="msk-form">
                <p class="skr-hint">
                    Versi terbit template asal disalin jadi <b>draf v1</b> di template baru. Sesi yang
                    sudah tercatat tidak ikut terbawa.
                </p>
                <div>
                    <label class="skr-lbl">Nama baru <span class="skr-req">*</span></label>
                    <input v-model="dup.nama" class="skr-ctl" type="text" maxlength="120" />
                </div>
            </div>
        </AdminModal>

        <!-- ══ MODAL: PEMILIH BANK ═══════════════════════════════
             Banknya berisi seribu lebih pertanyaan, jadi ini PENCARIAN —
             bukan daftar yang digulir. Penyaring utamanya departemen (job
             family), karena itulah pertanyaan pertama penyusun template:
             "pertanyaan apa yang cocok untuk loker IT?" -->
        <AdminModal
            :show="modalBank"
            title="Ambil dari Pustaka Pertanyaan"
            :subtitle="`${pilihBank.length} dipilih · ${bankTotal} pertanyaan cocok dari ${'' + (bankTotalSemua || 0)}`"
            icon="bi-patch-question"
            size="full"
            :busy="busy"
            :save-label="pilihBank.length ? `Tambahkan ${pilihBank.length} Pertanyaan` : 'Tambahkan'"
            :save-disabled="!pilihBank.length"
            @close="modalBank = false"
            @save="tarikBank"
        >
            <template #sticky>
                <div class="skr-sticky">
                    <div class="skr-sticky__k">
                        <div class="skr-bar">
                            <div class="skr-cari">
                                <i class="bi bi-search"></i>
                                <input v-model="cariBank" type="text" placeholder="Cari pertanyaan, kode, atau kelompok…" @input="cariBankTunda" />
                                <button v-if="cariBank" class="skr-cari__x" @click="cariBank = ''; muatBank(1)">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>

                            <el-select
                                v-model="fBank.jf" multiple collapse-tags collapse-tags-tooltip filterable
                                placeholder="Semua departemen" class="skr-sel" popper-class="skr-pop" @change="muatBank(1)"
                            >
                                <el-option v-for="t in (tag.JOB_FAMILY || [])" :key="t.kode" :label="t.nama" :value="t.kode">
                                    <span class="msk-opt">
                                        <i class="bi" :class="t.ikon" :style="{ color: t.warna }"></i>
                                        <span>{{ t.nama }}</span>
                                        <em>{{ t.jml }}</em>
                                    </span>
                                </el-option>
                            </el-select>

                            <el-select
                                v-model="fBank.level" multiple collapse-tags placeholder="Semua level"
                                class="skr-sel" popper-class="skr-pop" @change="muatBank(1)"
                            >
                                <el-option v-for="t in (tag.LEVEL || [])" :key="t.kode" :label="t.nama" :value="t.kode" />
                            </el-select>

                            <button
                                class="skr-btn skr-btn--ikon" :class="{ 'is-on': bankLanjut }"
                                :title="bankLanjut ? 'Sembunyikan penyaring lain' : 'Penyaring lain'"
                                @click="bankLanjut = !bankLanjut"
                            >
                                <i class="bi bi-sliders"></i>
                                <span v-if="jmlLanjut" class="skr-btn__n">{{ jmlLanjut }}</span>
                            </button>
                        </div>

                        <transition name="skr-turun">
                            <div v-if="bankLanjut" class="skr-lanjut">
                                <div class="skr-lanjut__f">
                                    <label class="skr-lbl">Tipe kandidat</label>
                                    <el-select v-model="fBank.ct" multiple collapse-tags placeholder="Semua" class="skr-sel" popper-class="skr-pop" @change="muatBank(1)">
                                        <el-option v-for="x in (tag.CANDIDATE_TYPE || [])" :key="x.kode" :label="x.nama" :value="x.kode" />
                                    </el-select>
                                </div>
                                <div class="skr-lanjut__f">
                                    <label class="skr-lbl">Fase sesi</label>
                                    <el-select v-model="fBank.fase" multiple collapse-tags placeholder="Semua" class="skr-sel" popper-class="skr-pop" @change="muatBank(1)">
                                        <el-option v-for="x in (tag.FASE || [])" :key="x.kode" :label="x.nama" :value="x.kode" />
                                    </el-select>
                                </div>
                                <div class="skr-lanjut__f">
                                    <label class="skr-lbl">Kompetensi</label>
                                    <el-select v-model="fBank.komp" multiple collapse-tags filterable placeholder="Semua" class="skr-sel" popper-class="skr-pop" @change="muatBank(1)">
                                        <el-option v-for="x in (tag.KOMPETENSI || [])" :key="x.kode" :label="x.nama" :value="x.kode" />
                                    </el-select>
                                </div>
                                <div class="skr-lanjut__f">
                                    <label class="skr-lbl">Jenis pertanyaan</label>
                                    <el-select v-model="fBank.jenis" multiple collapse-tags placeholder="Semua" class="skr-sel" popper-class="skr-pop" @change="muatBank(1)">
                                        <el-option v-for="j in jenisPertanyaan" :key="j" :label="labelJenis(j)" :value="j" />
                                    </el-select>
                                </div>
                                <div class="skr-lanjut__f">
                                    <label class="skr-lbl">Prioritas</label>
                                    <el-select v-model="fBank.prioritas" multiple collapse-tags placeholder="Semua" class="skr-sel" popper-class="skr-pop" @change="muatBank(1)">
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
                            <button v-if="chipFilter.length > 1" class="skr-aktif__all" @click="resetFilterBank">
                                Bersihkan semua
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <div v-loading="bankMuat" class="msk-bank">
                <p class="skr-hint msk-bank__lead">
                    <i class="bi bi-info-circle"></i>
                    Yang ditarik <b>disalin</b> ke draf — mengubahnya di sini tidak menyentuh
                    pustaka, dan menyunting pustaka besok tidak menyentuh template ini.
                </p>

                <div v-if="!bankRows.length && !bankMuat" class="skr-kosong">
                    <i class="bi bi-inbox"></i>
                    <strong>Tidak ada yang cocok</strong>
                    <small>Longgarkan penyaringnya, atau tulis pertanyaannya sendiri.</small>
                    <button v-if="adaFilterBank" class="skr-btn" @click="resetFilterBank">
                        <i class="bi bi-arrow-counterclockwise"></i> Bersihkan penyaring
                    </button>
                </div>

                <div v-for="(grup, nama) in bankGrup" :key="nama" class="msk-bgrup">
                    <div class="msk-bgrup__h">
                        <span>{{ nama === '_' ? 'Tanpa kelompok' : nama }}</span>
                        <small>{{ grup.length }}</small>
                        <button class="msk-bgrup__all" @click="pilihGrup(grup)">
                            {{ grupTerpilih(grup) ? 'Batal semua' : 'Pilih semua' }}
                        </button>
                    </div>

                    <label
                        v-for="b in grup" :key="b.kode"
                        class="msk-brow" :class="{ 'is-on': pilihBank.includes(b.kode) }"
                    >
                        <input type="checkbox" :checked="pilihBank.includes(b.kode)" @change="togglePilih(b.kode)" />
                        <div class="msk-brow__i">
                            <h4>{{ b.label }}</h4>
                            <div class="msk-brow__m">
                                <code>{{ b.kode }}</code>
                                <span class="msk-brow__s">{{ labelTipe(b.tipe) }}</span>
                                <span v-if="b.jenis" class="msk-brow__s">{{ labelJenis(b.jenis) }}</span>
                                <span v-if="b.prioritas === 'MANDATORY'" class="skr-pil skr-pil--wajib">wajib</span>
                                <span v-else-if="b.prioritas === 'CONDITIONAL'" class="skr-pil skr-pil--syarat">bersyarat</span>
                                <span v-if="b.bobot !== null" class="skr-pil skr-pil--bobot">bobot {{ b.bobot }}</span>
                                <span v-if="b.knockoutOperator" class="skr-pil skr-pil--gugur">
                                    <i class="bi bi-exclamation-octagon-fill"></i>
                                    {{ labelOperator(b.knockoutOperator) }} {{ b.knockoutNilai }}
                                </span>
                                <span v-if="b.durasiDetik" class="msk-brow__s msk-brow__s--w">
                                    <i class="bi bi-stopwatch"></i>{{ b.durasiDetik }}s
                                </span>
                            </div>

                            <!-- Departemen & kompetensi ditampilkan supaya orang
                                 tahu KENAPA pertanyaan ini muncul di hasilnya. -->
                            <div v-if="tagRingkas(b).length" class="msk-brow__t">
                                <span v-for="t in tagRingkas(b)" :key="t.dimensi + t.kode" class="skr-chip" :style="{ '--c': t.warna }">
                                    {{ t.nama }}
                                </span>
                            </div>

                            <div v-if="b.opsi.length" class="msk-brow__o">
                                <span v-for="o in b.opsi" :key="o.nilai">
                                    {{ o.label }}<em v-if="o.skor !== null">{{ o.skor }}</em>
                                </span>
                            </div>

                            <p v-if="b.jawabanDiharapkan" class="msk-brow__p">
                                <i class="bi bi-check2-circle"></i> {{ b.jawabanDiharapkan }}
                            </p>
                        </div>
                    </label>
                </div>

                <div v-if="bankTotal > bankPer" class="skr-hal">
                    <span class="skr-hal__ket">
                        Menampilkan <b>{{ bankRows.length }}</b> dari <b>{{ bankTotal.toLocaleString('id-ID') }}</b> pertanyaan
                    </span>
                    <el-pagination
                        :layout="sempit ? 'prev, pager, next' : 'total, prev, pager, next'"
                        :pager-count="sempit ? 5 : 7"
                        :current-page="bankHalaman" :page-size="bankPer" :total="bankTotal"
                        @current-change="muatBank"
                    />
                </div>
            </div>
        </AdminModal>

        <!-- ══ KONFIRMASI ════════════════════════════════════════════════════ -->
        <ConfirmModal
            :show="!!tanya"
            :title="tanya?.judul || ''"
            :subtitle="tanya?.sub || ''"
            :icon="tanya?.ikon || 'bi-question-circle'"
            :danger="tanya?.bahaya !== false"
            :confirm-label="tanya?.tombol || 'Ya, Lanjutkan'"
            :confirm-icon="tanya?.tombolIkon || ''"
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
import InfoTip from '@career/InfoTip.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import ExplorerLayout from '@career/ExplorerLayout.vue';
import { tglJam } from '@utils/tanggal';
import { inisial, warnaOrang } from '@utils/orang';
import { chipPenyaring, jmlLanjut } from '@utils/career/penyaringPustaka';

const API = '/api/v1/master-skrining';
const CFG = { headers: { Accept: 'application/json' } };

const NAMA_TIPE = {
    RADIO: 'Pilihan tunggal',
    CHECKBOX: 'Pilihan jamak',
    SELECT: 'Dropdown',
    RATING: 'Bintang',
    LIKERT: 'Skala setuju',
    NPS: 'NPS 0–10',
    BOOLEAN: 'Ya / Tidak',
    TEXT: 'Teks singkat',
    TEXTAREA: 'Teks panjang',
    EDITOR: 'Catatan berformat',
    NUMBER: 'Angka',
    CURRENCY: 'Rupiah',
    DATE: 'Tanggal',
};

const NAMA_OPERATOR = {
    '=': 'sama dengan',
    '!=': 'tidak sama dengan',
    '>': 'lebih dari',
    '<': 'kurang dari',
    '>=': 'minimal',
    '<=': 'maksimal',
    ANTARA: 'antara',
    ADA_DI: 'salah satu dari',
    TIDAK_ADA_DI: 'bukan salah satu dari',
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

let urutKunci = 0;

export default {
    components: { Head, AdminModal, ConfirmModal, ExplorerLayout, InfoTip },
    data() {
        return {
            kategoriOpsi: [],
            loading: true,
            siap: true,
            daftar: [],
            cari: '',
            pilih: null,

            tipe: [],
            tipeBeropsi: [],
            tipeBerskala: [],
            tipeBerskor: [],
            operator: [],

            versiPilih: null,
            versiAktif: null,
            muatVersi: false,
            /* Dua penampung terpisah, bukan satu yang dipakai bergantian:
               `draf` bisa disunting dan kotor, `bacaan` selalu bersih. Satu
               penampung berarti berpindah dari draf ke versi terbit diam-diam
               membuang suntingan yang belum disimpan. */
            draf: [],
            bacaan: [],

            // Bank tidak lagi dimuat sekaligus — lihat catatan di modal.
            tag: {},
            jenisPertanyaan: [],
            prioritasPertanyaan: [],
            bankRows: [],
            bankTotal: 0,
            bankTotalSemua: 0,
            bankHalaman: 1,
            bankPer: 40,
            bankMuat: false,
            bankLanjut: false,
            tmBank: null,
            fBank: { jf: [], level: [], ct: [], fase: [], komp: [], jenis: [], prioritas: [] },

            modalBank: false,
            cariBank: '',
            pilihBank: [],

            saringBuka: false,
            saring: { status: '', kategori: null, tanggal: null, urut: 'baru' },
            opsiStatus: [
                { v: '', t: 'Semua' },
                { v: 'TERBIT', t: 'Sudah terbit' },
                { v: 'DRAF', t: 'Punya draf' },
                { v: 'BELUM', t: 'Belum pernah terbit' },
                { v: 'TERPASANG', t: 'Terpasang di loker' },
                { v: 'MATI', t: 'Nonaktif' },
            ],
            opsiUrut: [
                { v: 'baru', t: 'Terbaru dibuat' },
                { v: 'lama', t: 'Terlama dibuat' },
                { v: 'nama', t: 'Nama A–Z' },
                { v: 'ubah', t: 'Terakhir diubah' },
                { v: 'pakai', t: 'Paling banyak dipakai' },
            ],

            ubahKode: false,
            modal: false,
            modalDup: false,
            busy: false,
            simpanBusy: false,
            form: { id: null, kode: '', nama: '', deskripsi: '', petunjuk: '', kategori: null },
            dup: { id: null, dari: '', kode: '', nama: '' },
            tanya: null,
        };
    },
    computed: {
        /**
         * Penyaringan dikerjakan di sini, bukan di server: seluruh daftar
         * template memang sudah ada di memori (jumlahnya puluhan, bukan ribuan
         * seperti pustaka pertanyaan), jadi menyaringnya lewat jaringan hanya
         * menambah tunggu tanpa menghemat apa pun.
         */
        tersaring() {
            const q = this.cari.trim().toLowerCase();
            const { status, kategori, tanggal } = this.saring;
            const [dari, sampai] = tanggal || [];

            const hasil = this.daftar.filter((r) => {
                if (q && !(
                    r.nama.toLowerCase().includes(q)
                    || r.kode.toLowerCase().includes(q)
                    || (r.deskripsi || '').toLowerCase().includes(q)
                )) return false;

                if (kategori && r.kategori !== kategori) return false;

                if (status === 'TERBIT' && !r.versiTerbit) return false;
                if (status === 'DRAF' && !r.versiDraf) return false;
                if (status === 'BELUM' && r.versiTerbit) return false;
                if (status === 'TERPASANG' && !r.terikat) return false;
                if (status === 'MATI' && r.aktif) return false;

                // Tanggal dibanding sebagai teks ISO: 'YYYY-MM-DD' berurut
                // secara leksikografis, jadi tidak perlu membangun Date hanya
                // untuk membandingkan dua hari.
                if (dari || sampai) {
                    const t = (r.dibuatPada || '').slice(0, 10);
                    if (!t) return false;
                    if (dari && t < dari) return false;
                    if (sampai && t > sampai) return false;
                }

                return true;
            });

            const urut = {
                nama: (a, b) => a.nama.localeCompare(b.nama, 'id'),
                baru: (a, b) => (b.dibuatPada || '').localeCompare(a.dibuatPada || ''),
                lama: (a, b) => (a.dibuatPada || '').localeCompare(b.dibuatPada || ''),
                ubah: (a, b) => (b.diperbaruiPada || '').localeCompare(a.diperbaruiPada || ''),
                pakai: (a, b) => (b.dipakai + b.terikat) - (a.dipakai + a.terikat),
            }[this.saring.urut];

            return urut ? [...hasil].sort(urut) : hasil;
        },
        adaSaring() {
            const { status, kategori, tanggal, urut } = this.saring;

            return !!this.cari || !!status || !!kategori || !!(tanggal && tanggal.length) || urut !== 'baru';
        },
        /** Yang dihitung hanya yang tersembunyi di balik tombol — cari tidak. */
        jmlSaring() {
            const { status, kategori, tanggal, urut } = this.saring;

            return (status ? 1 : 0) + (kategori ? 1 : 0)
                + (tanggal && tanggal.length ? 1 : 0) + (urut !== 'baru' ? 1 : 0);
        },
        terpilih() {
            return this.daftar.find((r) => r.id === this.pilih) || null;
        },
        jmlTerbit() {
            return this.daftar.filter((r) => r.versiTerbit).length;
        },
        jmlDraf() {
            return this.daftar.filter((r) => r.versiDraf).length;
        },
        jmlTerikat() {
            return this.daftar.reduce((n, r) => n + r.terikat, 0);
        },
        bisaSunting() {
            return this.versiAktif?.status === 'DRAFT';
        },
        totalBobot() {
            return this.draf.reduce((n, p) => n + (p.bobot || 0), 0);
        },
        jmlKnockout() {
            return this.draf.filter((p) => p.knockout).length;
        },
        seksiTerpakai() {
            return [...new Set(this.draf.map((p) => p.seksi).filter(Boolean))];
        },
        /** Ada pertanyaan yang berasal dari bank? Menentukan tombol segarkan digambar. */
        adaDariBank() {
            return this.draf.some((p) => p.bankKode);
        },
        bankGrup() {
            const out = {};
            this.bankRows.forEach((b) => {
                const k = b.kelompok || '_';
                (out[k] = out[k] || []).push(b);
            });

            return out;
        },
        adaFilterBank() {
            return !!this.cariBank || Object.values(this.fBank).some((x) => x.length);
        },
        jmlLanjut() {
            return jmlLanjut(this.fBank);
        },
        chipFilter() {
            return chipPenyaring({
                cari: this.cariBank,
                nilai: this.fBank,
                tag: this.tag,
                labelJenis: this.labelJenis,
                labelPrioritas: this.labelPrioritas,
            });
        },
        /** Bacaan dikelompokkan per seksi; '_' menampung yang tanpa kelompok. */
        bacaanGrup() {
            const out = {};
            this.bacaan.forEach((p) => {
                const k = p.seksi || '_';
                (out[k] = out[k] || []).push(p);
            });

            return out;
        },
    },
    mounted() {
        this.muat();
    },
    methods: {
        tglJam,
        inisial,
        warnaOrang,
        labelTipe(t) {
            return NAMA_TIPE[t] || t;
        },
        labelOperator(o) {
            return NAMA_OPERATOR[o] || o;
        },
        labelJenis(j) {
            return NAMA_JENIS[j] || j;
        },
        labelPrioritas(p) {
            return NAMA_PRIORITAS[p] || p;
        },
        kelasPrioritas(p) {
            return { MANDATORY: 'msk-tag--wajib', CONDITIONAL: 'msk-tag--kondisi' }[p] || '';
        },
        labelStatus(s) {
            return { DRAFT: 'Draf', PUBLISHED: 'Terbit', ARCHIVED: 'Arsip' }[s] || s;
        },
        ikonStatus(s) {
            return { DRAFT: 'bi-pencil-square', PUBLISHED: 'bi-broadcast', ARCHIVED: 'bi-archive' }[s] || 'bi-info-circle';
        },
        beropsi(t) {
            return this.tipeBeropsi.includes(t);
        },
        berskala(t) {
            return this.tipeBerskala.includes(t);
        },
        berskor(t) {
            return this.tipeBerskor.includes(t);
        },
        /** Terbaru di kiri — draf dan versi terbit yang paling sering dibuka. */
        versiUrut(item) {
            return [...(item.versi || [])].sort((a, b) => b.versi - a.versi);
        },
        phNilai(p) {
            if (p.knockoutOperator === 'ANTARA') return '20,25';
            if (p.knockoutOperator === 'ADA_DI' || p.knockoutOperator === 'TIDAK_ADA_DI') return 'S1,D4';

            return 'nilai pembanding';
        },

        // ── MUAT ────────────────────────────────────────────────────────────
        async muat() {
            this.loading = true;
            try {
                const { data } = await axios.get(API, CFG);
                const r = data.result || {};

                this.siap = r.siap !== false;
                this.daftar = r.rows || [];
                this.tipe = r.tipe || [];
                this.tipeBeropsi = r.tipeBeropsi || [];
                this.tipeBerskala = r.tipeBerskala || [];
                this.tipeBerskor = r.tipeBerskor || [];
                this.operator = r.operator || [];
                this.tag = r.tag || {};
                this.jenisPertanyaan = r.jenisPertanyaan || [];
                this.prioritasPertanyaan = r.prioritasPertanyaan || [];
                this.kategoriOpsi = r.kategoriOpsi || [];

                // Pilihan sebelumnya dipertahankan bila barisnya masih ada —
                // memuat ulang sesudah menyimpan tidak boleh melempar orang
                // kembali ke panel kosong.
                if (this.pilih && !this.daftar.some((x) => x.id === this.pilih)) {
                    this.pilih = null;
                    this.versiPilih = null;
                    this.versiAktif = null;
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat data.');
            } finally {
                this.loading = false;
            }
        },
        gantiPilih(id) {
            this.pilih = id;
            this.versiPilih = null;
            this.versiAktif = null;
            this.draf = [];
            this.bacaan = [];

            if (!id) return;

            // Versi yang dibuka lebih dulu: draf kalau ada (di situlah pekerjaan
            // biasanya dilanjutkan), kalau tidak versi terbit.
            const it = this.daftar.find((x) => x.id === id);
            const v = (it?.versi || []).find((x) => x.status === 'DRAFT')
                || (it?.versi || []).find((x) => x.status === 'PUBLISHED')
                || (it?.versi || [])[0];

            if (v) this.bukaVersi(v.id);
        },
        async bukaVersi(versiId) {
            this.versiPilih = versiId;
            this.muatVersi = true;
            try {
                const { data } = await axios.get(`${API}/versi/${versiId}`, CFG);
                const r = data.result || {};

                this.versiAktif = { versi: r.versi, status: r.status, catatan: r.catatan };
                this.bacaan = r.pertanyaan || [];
                this.draf = r.status === 'DRAFT' ? r.pertanyaan.map((p) => this.keDraf(p)) : [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat pertanyaan.');
                this.versiAktif = null;
            } finally {
                this.muatVersi = false;
            }
        },
        /**
         * Pertanyaan dari server → bentuk yang bisa disunting.
         *
         * `_k` adalah kunci render yang bertahan saat baris digeser. Memakai
         * indeks larik sebagai :key membuat Vue memakai ulang komponen input
         * yang salah begitu urutannya berubah — isian berpindah ke baris lain.
         */
        keDraf(p) {
            return {
                _k: `q${++urutKunci}`,
                kode: p.kode || '',
                tipe: p.tipe || 'RADIO',
                label: p.label || '',
                seksi: p.seksi || '',
                bantuan: p.bantuan || '',
                opsi: (p.opsi || []).map((o) => ({ ...o })),
                skalaMin: p.skalaMin ?? 1,
                skalaMax: p.skalaMax ?? 5,
                labelMin: p.labelMin || '',
                labelMax: p.labelMax || '',
                wajib: !!p.wajib,
                bobot: p.bobot ?? null,
                knockout: !!p.knockout,
                knockoutOperator: p.knockoutOperator || '=',
                knockoutNilai: p.knockoutNilai || '',
                knockoutPesan: p.knockoutPesan || '',
                catatan: !!p.catatan,
                tampilJika: p.tampilJika || null,
                bankId: p.bankId ?? null,
                bankKode: p.bankKode || null,
                ubahan: !!p.ubahan,
            };
        },

        // ── PENYUNTING ──────────────────────────────────────────────────────
        tambahPertanyaan() {
            this.draf.push(this.keDraf({
                tipe: 'RADIO',
                opsi: [
                    { nilai: 'YA', label: 'Ya', skor: null },
                    { nilai: 'TIDAK', label: 'Tidak', skor: null },
                ],
                wajib: true,
            }));
        },
        salinPertanyaan(i) {
            const s = JSON.parse(JSON.stringify(this.draf[i]));
            s._k = `q${++urutKunci}`;
            // Kode wajib unik, jadi salinannya tidak boleh membawa kode aslinya —
            // dikosongkan supaya kegagalannya terlihat sekarang, bukan saat simpan.
            s.kode = '';
            this.draf.splice(i + 1, 0, s);
        },
        hapusPertanyaan(i) {
            this.draf.splice(i, 1);
        },
        geser(i, arah) {
            const j = i + arah;
            if (j < 0 || j >= this.draf.length) return;

            const [x] = this.draf.splice(i, 1);
            this.draf.splice(j, 0, x);
        },
        rapikanKode(p) {
            p.kode = (p.kode || '')
                .toLowerCase()
                .replace(/[^a-z0-9_]+/g, '_')
                .replace(/^_+|_+$/g, '');
        },
        gantiTipe(p) {
            if (this.beropsi(p.tipe) && p.opsi.length < 2) {
                p.opsi = [
                    { nilai: 'YA', label: 'Ya', skor: null },
                    { nilai: 'TIDAK', label: 'Tidak', skor: null },
                ];
            }

            // Bobot dilepas begitu tipenya tidak bisa dinilai — kalau dibiarkan,
            // simpanan ditolak server dengan pesan yang terasa datang entah dari
            // mana beberapa menit kemudian.
            if (!this.berskor(p.tipe)) p.bobot = null;
        },
        tambahOpsi(p) {
            p.opsi.push({ nilai: '', label: '', skor: null });
        },
        /** Label diisi, nilai kosong → nilai diturunkan dari labelnya. */
        isiNilai(o) {
            if (o.nilai || !o.label) return;

            o.nilai = o.label.toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 120);
        },
        setBobot(p, on) {
            p.bobot = on ? 1 : null;
            if (!on) p.opsi.forEach((o) => (o.skor = null));
        },

        // ── SIMPAN ──────────────────────────────────────────────────────────
        async simpanDraf() {
            const salah = this.periksaDraf();
            if (salah) {
                ElMessage.warning(salah);

                return;
            }

            this.simpanBusy = true;
            try {
                const { data } = await axios.put(
                    `${API}/versi/${this.versiPilih}/pertanyaan`,
                    { pertanyaan: this.draf.map((p) => this.keKirim(p)), catatan: this.versiAktif?.catatan || null },
                    CFG,
                );
                ElMessage.success(data.message || 'Tersimpan.');
                await this.muat();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.simpanBusy = false;
            }
        },
        keKirim(p) {
            return {
                kode: p.kode,
                tipe: p.tipe,
                label: p.label,
                seksi: p.seksi || null,
                bantuan: p.bantuan || null,
                opsi: this.beropsi(p.tipe)
                    ? p.opsi.map((o) => ({ nilai: o.nilai, label: o.label, skor: o.skor ?? null }))
                    : null,
                skalaMin: this.berskala(p.tipe) ? p.skalaMin : null,
                skalaMax: this.berskala(p.tipe) ? p.skalaMax : null,
                labelMin: this.berskala(p.tipe) ? p.labelMin || null : null,
                labelMax: this.berskala(p.tipe) ? p.labelMax || null : null,
                wajib: p.wajib,
                bobot: p.bobot,
                knockout: p.knockout,
                knockoutOperator: p.knockout ? p.knockoutOperator : null,
                knockoutNilai: p.knockout ? p.knockoutNilai : null,
                knockoutPesan: p.knockout ? p.knockoutPesan || null : null,
                catatan: p.catatan,
                tampilJika: p.tampilJika,
                // Asal-usul dikirim balik apa adanya. `ubahan` TIDAK dikirim:
                // server yang menghitungnya dengan membandingkan isi terhadap
                // bank, dan penanda yang berasal dari layar bisa salah — atau
                // dikarang. Penanda itulah yang menentukan sebuah baris ikut
                // tersegarkan atau tidak.
                bankId: p.bankId,
                bankKode: p.bankKode,
            };
        },
        /**
         * Periksa di sini SEBELUM dikirim.
         *
         * Server memeriksa hal yang sama dan itu memang harus — tapi kesalahan
         * yang balik dari server hanya menyebut satu pertanyaan sekaligus, dan
         * orang yang baru menyusun 40 pertanyaan harus mengulangi perjalanannya
         * berkali-kali untuk menemukan semuanya.
         */
        periksaDraf() {
            const kode = new Map();

            for (let i = 0; i < this.draf.length; i += 1) {
                const p = this.draf[i];
                const no = i + 1;

                if (!p.label.trim()) return `Pertanyaan ${no} belum punya teks.`;
                if (!p.kode.trim()) return `Pertanyaan ${no} belum punya kode.`;
                if (!/^[a-z0-9_]+$/.test(p.kode)) return `Kode pertanyaan ${no} hanya boleh huruf kecil, angka, dan garis bawah.`;

                if (kode.has(p.kode)) return `Kode "${p.kode}" dipakai dua kali (pertanyaan ${kode.get(p.kode)} dan ${no}).`;
                kode.set(p.kode, no);

                if (this.beropsi(p.tipe)) {
                    if (p.opsi.length < 2) return `Pertanyaan ${no} butuh minimal dua pilihan.`;
                    if (p.opsi.some((o) => !o.nilai || !o.label)) return `Ada pilihan kosong di pertanyaan ${no}.`;

                    const nilai = p.opsi.map((o) => o.nilai);
                    if (new Set(nilai).size !== nilai.length) return `Ada dua pilihan bernilai sama di pertanyaan ${no}.`;
                }

                if (this.berskala(p.tipe) && p.skalaMax <= p.skalaMin) {
                    return `Skala pertanyaan ${no} terbalik — batas atas harus lebih besar.`;
                }

                if (p.knockout && (!p.knockoutOperator || !String(p.knockoutNilai).trim())) {
                    return `Syarat auto-gugur pertanyaan ${no} belum lengkap.`;
                }
            }

            return null;
        },

        // ── BANK PERTANYAAN ─────────────────────────────────────────────────
        bukaBank() {
            this.pilihBank = [];
            this.cariBank = '';
            this.bankLanjut = false;
            this.modalBank = true;
            this.muatBank(1);
        },
        /**
         * Ambil sehalaman hasil dari bank.
         *
         * `kecuali` berisi kode yang SUDAH ada di draf — disingkirkan di sisi
         * server, bukan sekadar ditandai di layar. Menawarkan sesuatu yang
         * tidak bisa dipilih cuma memenuhi daftar dengan baris mati, dan di
         * daftar berhalaman ia juga mengacaukan hitungan halamannya.
         */
        async muatBank(halaman = 1) {
            this.bankHalaman = halaman;
            this.bankMuat = true;
            try {
                const { data } = await axios.get(`${API}/bank/cari`, {
                    ...CFG,
                    params: {
                        q: this.cariBank || undefined,
                        ...this.fBank,
                        kecuali: this.draf.map((p) => p.kode).filter(Boolean),
                        halaman,
                    },
                });
                const r = data.result || {};
                this.bankRows = r.rows || [];
                this.bankTotal = r.total || 0;
                if (!this.adaFilterBank) this.bankTotalSemua = r.total || 0;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat bank.');
                this.bankRows = [];
            } finally {
                this.bankMuat = false;
            }
        },
        /* Ketikan ditunda 350 ms. Memanggil server tiap ketukan pada bank
           seribu baris membanjiri sambungan tanpa membuat hasilnya lebih cepat
           terlihat. */
        cariBankTunda() {
            clearTimeout(this.tmBank);
            this.tmBank = setTimeout(() => this.muatBank(1), 350);
        },
        lepasFilter(c) {
            if (c.kunci === 'cari') this.cariBank = '';
            else this.fBank[c.kunci] = this.fBank[c.kunci].filter((x) => x !== c.kode);
            this.muatBank(1);
        },
        resetFilterBank() {
            this.cariBank = '';
            this.fBank = { jf: [], level: [], ct: [], fase: [], komp: [], jenis: [], prioritas: [] };
            this.muatBank(1);
        },
        /** Dua dimensi yang paling menjelaskan kenapa sebuah pertanyaan muncul. */
        tagRingkas(b) {
            return (b.tag || []).filter((t) => t.dimensi === 'JOB_FAMILY' || t.dimensi === 'KOMPETENSI').slice(0, 4);
        },
        togglePilih(kode) {
            const i = this.pilihBank.indexOf(kode);
            if (i >= 0) this.pilihBank.splice(i, 1);
            else this.pilihBank.push(kode);
        },
        grupTerpilih(grup) {
            return grup.length > 0 && grup.every((b) => this.pilihBank.includes(b.kode));
        },
        pilihGrup(grup) {
            const kode = grup.map((b) => b.kode);

            if (this.grupTerpilih(grup)) {
                this.pilihBank = this.pilihBank.filter((k) => !kode.includes(k));

                return;
            }

            this.pilihBank = [...new Set([...this.pilihBank, ...kode])];
        },
        /**
         * Tarik pilihan ke draf.
         *
         * Draf disimpan DULU, baru bank ditarik. Tanpa itu, suntingan yang
         * belum ditekan Simpan hilang begitu server menulis ulang seluruh
         * daftar pertanyaan versi ini — dan hilangnya tidak terlihat sampai
         * orang menyadari pekerjaan setengah jamnya lenyap.
         */
        async tarikBank() {
            if (!this.pilihBank.length) return;

            this.busy = true;
            try {
                if (this.draf.length) {
                    await axios.put(
                        `${API}/versi/${this.versiPilih}/pertanyaan`,
                        { pertanyaan: this.draf.map((p) => this.keKirim(p)), catatan: this.versiAktif?.catatan || null },
                        CFG,
                    );
                }

                const { data } = await axios.post(
                    `${API}/versi/${this.versiPilih}/dari-bank`,
                    { kode: this.pilihBank },
                    CFG,
                );

                ElMessage.success(data.message || 'Ditambahkan.');
                this.modalBank = false;
                await this.muat();
                await this.bukaVersi(this.versiPilih);
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menambahkan.');
            } finally {
                this.busy = false;
            }
        },
        mintaSegarkan() {
            const segar = this.draf.filter((p) => p.bankKode && !p.ubahan).length;
            const lewat = this.draf.filter((p) => p.bankKode && p.ubahan).length;

            this.tanya = {
                judul: 'Segarkan dari bank?',
                sub: `${segar} pertanyaan ditarik ulang`,
                ikon: 'bi-arrow-repeat',
                bahaya: false,
                tombol: 'Ya, Segarkan',
                tombolIkon: 'bi-arrow-repeat',
                nota: lewat
                    ? `${lewat} pertanyaan yang sudah dirombak di template ini DILEWATI — bobot dan ambang gugurnya tetap seperti sekarang.`
                    : 'Urutan dan kelompok di template ini tidak ikut berubah.',
                jalan: async () => {
                    if (this.draf.length) {
                        await axios.put(
                            `${API}/versi/${this.versiPilih}/pertanyaan`,
                            { pertanyaan: this.draf.map((p) => this.keKirim(p)), catatan: this.versiAktif?.catatan || null },
                            CFG,
                        );
                    }

                    return axios.post(`${API}/versi/${this.versiPilih}/segarkan-bank`, {}, CFG);
                },
            };
        },

        // ── TEMPLATE ────────────────────────────────────────────────────────
        resetSaring() {
            this.cari = '';
            this.saring = { status: '', kategori: null, tanggal: null, urut: 'baru' };
        },
        bukaTambah() {
            this.ubahKode = false;
            this.form = { id: null, kode: '', nama: '', deskripsi: '', petunjuk: '', kategori: null };
            // Satu kategori berarti tidak ada yang perlu dipilih — dipasang di
            // sini supaya isian tersimpan benar tanpa orang menyentuhnya.
            if (this.kategoriOpsi.length === 1) this.form.kategori = this.kategoriOpsi[0].kode;
            this.modal = true;
        },
        bukaSunting(r) {
            this.ubahKode = false;
            this.form = {
                id: r.id,
                kode: r.kode,
                nama: r.nama,
                deskripsi: r.deskripsi || '',
                petunjuk: r.petunjuk || '',
                kategori: r.kategori || (this.kategoriOpsi.length === 1 ? this.kategoriOpsi[0].kode : null),
            };
            this.modal = true;
        },
        async simpanTemplate() {
            if (!this.form.nama.trim()) {
                ElMessage.warning('Nama template wajib diisi.');

                return;
            }

            this.busy = true;
            try {
                const { data } = this.form.id
                    ? await axios.put(`${API}/${this.form.id}`, this.form, CFG)
                    : await axios.post(API, this.form, CFG);

                ElMessage.success(data.message || 'Tersimpan.');
                this.modal = false;
                const baru = data.result?.id;
                await this.muat();
                if (baru) this.gantiPilih(baru);
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.busy = false;
            }
        },
        bukaDuplikat(r) {
            this.dup = { id: r.id, dari: `${r.nama} (${r.kode})`, kode: '', nama: `${r.nama} (salinan)`.slice(0, 120) };
            this.modalDup = true;
        },
        async simpanDuplikat() {
            this.busy = true;
            try {
                const { data } = await axios.post(`${API}/${this.dup.id}/duplikat`, { nama: this.dup.nama }, CFG);
                ElMessage.success(data.message || 'Tersalin.');
                this.modalDup = false;
                await this.muat();
                if (data.result?.id) this.gantiPilih(data.result.id);
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyalin.');
            } finally {
                this.busy = false;
            }
        },

        // ── KONFIRMASI ──────────────────────────────────────────────────────
        mintaToggle(r) {
            this.tanya = {
                judul: r.aktif ? 'Nonaktifkan template?' : 'Aktifkan template?',
                sub: r.nama,
                ikon: r.aktif ? 'bi-toggle-off' : 'bi-toggle-on',
                bahaya: r.aktif,
                tombol: r.aktif ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan',
                nota: r.aktif
                    ? 'Template tak lagi ditawarkan untuk pengikatan baru. Sesi yang sedang berjalan tidak berubah.'
                    : '',
                jalan: () => axios.patch(`${API}/${r.id}/toggle`, {}, CFG),
            };
        },
        mintaHapus(r) {
            this.tanya = {
                judul: 'Hapus template?',
                sub: `${r.nama} (${r.kode})`,
                ikon: 'bi-trash',
                tombol: 'Ya, Hapus',
                tombolIkon: 'bi-trash',
                nota: r.dipakai
                    ? `Template ini sudah dipakai ${r.dipakai} sesi — permintaan akan ditolak. Nonaktifkan saja.`
                    : 'Seluruh versi dan pertanyaannya ikut terhapus.',
                jalan: () => axios.delete(`${API}/${r.id}`, CFG),
                sesudah: () => {
                    this.pilih = null;
                },
            };
        },
        mintaDrafBaru(r) {
            this.tanya = {
                judul: 'Buat draf baru?',
                sub: r.nama,
                ikon: 'bi-pencil-square',
                bahaya: false,
                tombol: 'Ya, Buat Draf',
                tombolIkon: 'bi-plus-lg',
                nota: r.versiTerbit
                    ? `Seluruh pertanyaan v${r.versiTerbit} disalin ke draf baru. Versi terbit tetap dipakai sesi sampai draf ini diterbitkan.`
                    : 'Draf dibuat kosong.',
                jalan: () => axios.post(`${API}/${r.id}/draf-baru`, {}, CFG),
                sesudah: (res) => {
                    if (res?.result?.versiId) this.versiPilih = res.result.versiId;
                },
            };
        },
        mintaTerbit() {
            const belum = this.periksaDraf();
            if (belum) {
                ElMessage.warning(belum);

                return;
            }

            this.tanya = {
                judul: `Terbitkan v${this.versiAktif.versi}?`,
                sub: `${this.draf.length} pertanyaan`,
                ikon: 'bi-broadcast',
                bahaya: false,
                tombol: 'Ya, Terbitkan',
                tombolIkon: 'bi-broadcast',
                nota: 'Sesi skrining BARU mulai memakai versi ini. Sesi yang sudah berjalan tetap dengan versi lamanya. Versi terbit sebelumnya diarsipkan.',
                // Draf disimpan dulu, baru diterbitkan — kalau tidak, suntingan
                // terakhir yang belum ditekan Simpan hilang tanpa jejak, dan
                // yang terbit bukan yang barusan dilihat orang di layar.
                jalan: async () => {
                    await axios.put(
                        `${API}/versi/${this.versiPilih}/pertanyaan`,
                        { pertanyaan: this.draf.map((p) => this.keKirim(p)), catatan: this.versiAktif?.catatan || null },
                        CFG,
                    );

                    return axios.post(`${API}/versi/${this.versiPilih}/terbitkan`, {}, CFG);
                },
            };
        },
        mintaBuangDraf() {
            this.tanya = {
                judul: `Buang draf v${this.versiAktif.versi}?`,
                sub: `${this.draf.length} pertanyaan ikut terbuang`,
                ikon: 'bi-x-octagon',
                tombol: 'Ya, Buang',
                nota: 'Versi terbit dan arsip tidak terpengaruh.',
                jalan: () => axios.delete(`${API}/versi/${this.versiPilih}`, CFG),
                sesudah: () => {
                    this.versiPilih = null;
                    this.versiAktif = null;
                },
            };
        },
        async jalankanTanya() {
            if (!this.tanya) return;

            const t = this.tanya;
            this.busy = true;
            try {
                const res = await t.jalan();
                ElMessage.success(res?.data?.message || 'Berhasil.');
                this.tanya = null;
                t.sesudah?.(res?.data);
                await this.muat();

                // Panel kanan disegarkan hanya bila versinya masih ada — kalau
                // barusan dibuang, membukanya lagi akan berakhir 404.
                if (this.versiPilih) await this.bukaVersi(this.versiPilih);
                else if (this.pilih) this.gantiPilih(this.pilih);
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
   karena isi jendela di-teleport ke <body> dan tidak mewarisi satu pun custom
   property dari akar halaman ini. */

/* ── KEPALA ──────────────────────────────────────────────────────────────── */
.msk-beku { display: flex; align-items: flex-start; gap: 7px; margin-top: 8px; font-size: 12.5px; color: #64748b; }
.msk-beku i { color: #0ea5e9; margin-top: 2px; }
.msk-belum { margin-bottom: 18px; }
.msk-belum code {
    font-family: ui-monospace, Consolas, monospace; font-size: 12px;
    background: rgba(15, 23, 42, 0.06); padding: 1px 6px; border-radius: 5px;
}

/* ── PANEL KIRI ──────────────────────────────────────────────────────────── */
/* ── PENYARING PANEL KIRI ────────────────────────────────────────────────────
   Panelnya sempit (±260px), jadi penyaringnya menumpuk ke bawah satu kolom
   dan hanya kotak cari yang selalu terlihat. Sisanya di balik tombol: enam
   kotak yang selalu terbuka menyisakan tiga baris daftar di layar laptop. */
.msk-cari { display: flex; align-items: center; gap: 7px; }
.msk-cari .skr-cari { flex: 1; min-width: 0; }
.msk-saring { grid-template-columns: 1fr; gap: 10px; padding: 12px; }
.msk-saring__lebar { grid-column: 1 / -1; }

.msk-hitung {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    font-size: 11.5px; color: #94a3b8; margin-top: 9px;
}
.msk-hitung__x {
    display: inline-flex; align-items: center; gap: 5px;
    border: 0; background: transparent; padding: 0;
    font-family: inherit; font-size: 11px; font-weight: 600;
    color: #4338ca; cursor: pointer;
}
.msk-hitung__x:hover { text-decoration: underline; }

/* ── KODE YANG DIBUATKAN SISTEM ─────────────────────────────────────────── */
.msk-kodeauto {
    display: flex; align-items: center; gap: 8px;
    height: 38px; padding: 0 11px;
    border: 1px dashed #c7d2fe; border-radius: 10px;
    background: rgba(99, 102, 241, 0.05);
    font-size: 12.5px; color: #64748b;
}
.msk-kodeauto i { color: #6366f1; }
.msk-kodeauto.is-ada { border-style: solid; border-color: #e2e8f0; background: #f8fafc; }
.msk-kodeauto code {
    flex: 1; min-width: 0;
    font-family: ui-monospace, Consolas, monospace; font-size: 12.5px;
    font-weight: 700; color: #0f172a;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.msk-kodeauto__u {
    border: 0; background: transparent; padding: 0;
    font-family: inherit; font-size: 11.5px; font-weight: 700;
    color: #4338ca; cursor: pointer;
}
.msk-kodeauto__u:hover { text-decoration: underline; }
.msk-row { display: flex; flex-direction: column; gap: 6px; width: 100%; }
.msk-row__head { display: flex; align-items: center; gap: 7px; }
.msk-row__head b { font-size: 13.5px; color: #0f172a; line-height: 1.35; }
.msk-row__meta { display: flex; align-items: center; gap: 7px; font-size: 11px; }
.msk-row__meta code {
    font-family: ui-monospace, Consolas, monospace; color: #64748b;
    background: rgba(100, 116, 139, 0.1); padding: 1px 6px; border-radius: 5px;
}
.msk-kat { color: #6366f1; font-weight: 600; letter-spacing: 0.04em; }
.msk-row__badges { display: flex; flex-wrap: wrap; gap: 5px; }
.msk-pill {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 700; letter-spacing: 0.03em;
    padding: 2px 8px; border-radius: 6px;
}
.msk-pill--live { background: rgba(5, 150, 105, 0.13); color: #047857; }
.msk-pill--draf { background: rgba(245, 158, 11, 0.15); color: #b45309; }
.msk-pill--none { background: rgba(100, 116, 139, 0.12); color: #64748b; }
.msk-pill--pakai { background: rgba(99, 102, 241, 0.12); color: #4338ca; }
.msk-pill--off { background: rgba(220, 38, 38, 0.12); color: #b91c1c; }

/* ── PANEL KANAN ─────────────────────────────────────────────────────────── */
.msk-dhead { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; width: 100%; flex-wrap: wrap; }
.msk-dhead__t h2 { margin: 0; font-size: 19px; font-weight: 700; color: #0f172a; letter-spacing: -0.015em; }
.msk-dhead__sub { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 5px; font-size: 12px; color: #64748b; }
.msk-dhead__sub code {
    font-family: ui-monospace, Consolas, monospace;
    background: rgba(100, 116, 139, 0.1); padding: 1px 6px; border-radius: 5px;
}
.msk-dhead__act { display: flex; gap: 3px; flex: 0 0 auto; }

.msk-vbar {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    padding-bottom: 15px; border-bottom: 1px solid #e8ebf2; margin-bottom: 17px;
}
.msk-vbar__list { display: flex; gap: 8px; overflow-x: auto; flex: 1; padding-bottom: 3px; min-width: 0; }
.msk-vbar__new { flex: 0 0 auto; }
.msk-vtab {
    display: flex; flex-direction: column; gap: 2px;
    padding: 8px 14px; border-radius: 11px;
    border: 1px solid #e2e8f0; background: #fff;
    cursor: pointer; text-align: left; flex: 0 0 auto;
    transition: all 0.14s; font-family: inherit;
}
.msk-vtab:hover { border-color: #cbd5e1; background: #f8fafc; }
.msk-vtab.is-on { border-color: #6366f1; background: rgba(99, 102, 241, 0.06); box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.25); }
.msk-vtab__n { font-size: 13px; font-weight: 700; color: #0f172a; }
.msk-vtab__s { font-size: 10px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }
.msk-vtab__q { font-size: 10.5px; color: #94a3b8; }
.msk-vtab.is-draft .msk-vtab__s { color: #b45309; }
.msk-vtab.is-published .msk-vtab__s { color: #047857; }
.msk-vtab.is-archived .msk-vtab__s { color: #94a3b8; }

.msk-vnote { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 17px; }
.msk-vnote--draft { background: rgba(245, 158, 11, 0.08); border-color: rgba(245, 158, 11, 0.25); }
.msk-vnote--draft i { color: #b45309; }
.msk-vnote--published { background: rgba(5, 150, 105, 0.08); border-color: rgba(5, 150, 105, 0.22); }
.msk-vnote--published i { color: #047857; }
.msk-vnote--archived { background: rgba(100, 116, 139, 0.08); border-color: rgba(100, 116, 139, 0.2); }
.msk-vnote--archived i { color: #64748b; }
.msk-loading { padding: 44px; text-align: center; color: #94a3b8; font-size: 13px; }

/* ── PENYUNTING PERTANYAAN ───────────────────────────────────────────────── */
.msk-edit__bar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 14px; flex-wrap: wrap; margin-bottom: 15px;
}
.msk-edit__info { font-size: 12.5px; color: #64748b; }
.msk-edit__info b { color: #0f172a; }
.msk-edit__ko { color: #b91c1c; font-weight: 600; }
.msk-edit__btns { display: flex; gap: 7px; flex-wrap: wrap; }

.msk-q {
    border: 1px solid #e8ebf2; border-radius: 13px; background: #fff;
    padding: 15px; margin-bottom: 12px;
}
.msk-q.is-ko { border-color: rgba(220, 38, 38, 0.28); box-shadow: inset 3px 0 0 rgba(220, 38, 38, 0.5); }
.msk-q__head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.msk-q__no {
    flex: 0 0 auto; width: 26px; height: 26px; border-radius: 8px;
    background: rgba(99, 102, 241, 0.11); color: #4338ca;
    font-size: 11.5px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}
.msk-asal {
    flex: 0 0 auto; width: 22px; height: 22px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center; font-size: 10px;
    background: rgba(99, 102, 241, 0.12); color: #4338ca;
}
.msk-asal.is-ubah { background: rgba(245, 158, 11, 0.16); color: #b45309; }
.msk-q__label {
    flex: 1; min-width: 180px; border: 0; border-bottom: 1px solid #e2e8f0;
    padding: 6px 2px; font-size: 14px; font-weight: 600; color: #0f172a;
    background: transparent; font-family: inherit;
}
.msk-q__label:focus { outline: none; border-bottom-color: #6366f1; }
.msk-q__tools { display: flex; gap: 2px; flex: 0 0 auto; }

.msk-q__grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 11px; margin-bottom: 11px; }
.msk-q__flags { display: flex; flex-direction: column; gap: 7px; justify-content: flex-end; padding-bottom: 5px; }
.msk-q__bantu { margin-bottom: 11px; }
.msk-q__note { margin-top: 8px; }
.msk-skor { width: 84px; }

.msk-opsi { border: 1px solid #eef1f7; border-radius: 11px; padding: 12px; background: #fbfcfe; margin-bottom: 11px; }
.msk-opsi__head {
    display: flex; align-items: center; gap: 9px; margin-bottom: 9px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.06em;
    text-transform: uppercase; color: #64748b;
}
.msk-opsi__head small { font-weight: 400; letter-spacing: 0; text-transform: none; color: #94a3b8; }
.msk-opsi__head button { margin-left: auto; }
.msk-opsi__row { display: grid; grid-template-columns: minmax(0, 1fr) 150px auto auto; gap: 7px; align-items: center; margin-bottom: 6px; }

.msk-skala { display: grid; grid-template-columns: repeat(4, 1fr); gap: 11px; padding: 12px; border: 1px solid #eef1f7; border-radius: 11px; background: #fbfcfe; margin-bottom: 11px; }

.msk-q__foot {
    display: flex; align-items: center; justify-content: space-between;
    gap: 16px; flex-wrap: wrap; padding-top: 11px; border-top: 1px solid #f1f5f9;
}
.msk-bobot { display: flex; align-items: center; gap: 10px; font-size: 12.5px; color: #475569; flex-wrap: wrap; }
.msk-bobot small { color: #94a3b8; }
.msk-bobot--mati { color: #94a3b8; font-size: 11.5px; }

.msk-ko {
    margin-top: 12px; padding: 12px; border-radius: 11px;
    background: rgba(220, 38, 38, 0.05); border: 1px solid rgba(220, 38, 38, 0.16);
    display: flex; flex-direction: column; gap: 8px;
}
.msk-ko__lead { font-size: 12.5px; color: #b91c1c; display: flex; align-items: center; gap: 7px; }
.msk-ko__row { display: flex; gap: 8px; flex-wrap: wrap; }
.msk-ko__row > .skr-ctl { flex: 1; min-width: 150px; }
.msk-ko__note { font-size: 11px; color: #94a3b8; line-height: 1.55; }

/* ── BACAAN ──────────────────────────────────────────────────────────────── */
.msk-baca__grup { margin-bottom: 20px; }
.msk-baca__grup h4 {
    margin: 0 0 10px; font-size: 11px; font-weight: 700;
    letter-spacing: 0.09em; text-transform: uppercase; color: #6366f1;
}
.msk-baca__q { border: 1px solid #eef1f7; border-radius: 11px; padding: 12px 14px; margin-bottom: 8px; background: #fff; }
.msk-baca__t { display: flex; align-items: flex-start; gap: 10px; flex-wrap: wrap; }
.msk-baca__no {
    flex: 0 0 auto; width: 24px; height: 24px; border-radius: 7px;
    background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}
.msk-baca__t > div { flex: 1; min-width: 160px; }
.msk-baca__t b { display: block; font-size: 13.5px; color: #0f172a; line-height: 1.45; }
.msk-baca__t small { display: block; font-size: 11.5px; color: #94a3b8; margin-top: 3px; }
.msk-baca__tags { display: flex; flex-wrap: wrap; gap: 4px; flex: 0 0 auto; }
.msk-tag {
    font-size: 10px; font-weight: 700; letter-spacing: 0.03em;
    padding: 2px 7px; border-radius: 5px; background: #f1f5f9; color: #64748b; white-space: nowrap;
}
.msk-tag--wajib { background: rgba(99, 102, 241, 0.12); color: #4338ca; }
.msk-tag--bobot { background: rgba(5, 150, 105, 0.12); color: #047857; }
.msk-tag--ko { background: rgba(220, 38, 38, 0.12); color: #b91c1c; }
.msk-baca__opsi { display: flex; flex-wrap: wrap; gap: 5px 10px; margin: 8px 0 0 34px; font-size: 12px; color: #64748b; }
.msk-baca__opsi em { color: #047857; font-style: normal; font-weight: 600; }

/* ── KATEGORI TERKUNCI HAK AKSES ─────────────────────────────────────────── */
.msk-katfix {
    display: flex; align-items: center; gap: 9px;
    padding: 0 12px; height: 38px; border-radius: 10px;
    background: rgba(99, 102, 241, 0.07); border: 1px solid rgba(99, 102, 241, 0.2);
    font-size: 13px; color: #4338ca;
}
.msk-katfix i { flex: 0 0 auto; }
.msk-katfix span { font-weight: 600; }
.msk-katfix small { margin-left: auto; font-size: 11px; color: #94a3b8; font-weight: 400; }
.msk-katfix--kosong { background: rgba(245, 158, 11, 0.08); border-color: rgba(245, 158, 11, 0.25); color: #b45309; }
.msk-katfix--kosong span { font-weight: 500; }

/* ── JENDELA PEMILIH PUSTAKA ─────────────────────────────────────────────── */
.msk-opt { display: flex; align-items: center; gap: 9px; }
.msk-opt i { font-size: 13px; width: 16px; }
.msk-opt em { margin-left: auto; font-style: normal; font-size: 11px; color: #cbd5e1; font-variant-numeric: tabular-nums; }

.msk-bank__lead {
    display: flex; align-items: flex-start; gap: 7px;
    margin: 0 0 16px; padding: 9px 11px;
    border-radius: 9px;
    background: rgba(99, 102, 241, 0.05);
}
.msk-bank__lead i { color: #6366f1; margin-top: 1px; flex: 0 0 auto; }
.msk-bgrup { margin-bottom: 20px; }
.msk-bgrup__h {
    display: flex; align-items: center; gap: 9px; margin-bottom: 9px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.09em;
    text-transform: uppercase; color: #6366f1;
}
.msk-bgrup__h small { font-weight: 500; letter-spacing: 0; color: #cbd5e1; }
.msk-bgrup__all {
    margin-left: auto; border: 0; background: transparent; color: #64748b;
    font-size: 11.5px; font-weight: 600; cursor: pointer; font-family: inherit;
    letter-spacing: 0; text-transform: none; padding: 3px 6px; border-radius: 6px;
}
.msk-bgrup__all:hover { color: #4338ca; background: rgba(99, 102, 241, 0.08); }

.msk-brow {
    display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 12px;
    align-items: start; padding: 13px 14px;
    border: 1px solid #eef1f7; border-radius: 12px; margin-bottom: 7px;
    cursor: pointer; background: #fff; transition: all 0.13s;
}
.msk-brow:hover { border-color: #cbd5e1; background: #fbfcfe; }
.msk-brow.is-on { border-color: #6366f1; background: rgba(99, 102, 241, 0.05); }
.msk-brow > input { margin-top: 3px; accent-color: #6366f1; width: 16px; height: 16px; }
.msk-brow__i { min-width: 0; }
.msk-brow__i h4 { margin: 0; font-size: 13.5px; font-weight: 600; color: #0f172a; line-height: 1.45; }
.msk-brow__m { display: flex; flex-wrap: wrap; gap: 6px 10px; align-items: center; margin-top: 7px; }
.msk-brow__m code {
    font-family: ui-monospace, Consolas, monospace; font-size: 11px;
    background: #f1f5f9; padding: 2px 7px; border-radius: 5px; color: #64748b;
}
.msk-brow__s { font-size: 11.5px; color: #94a3b8; }
.msk-brow__s--w { display: inline-flex; align-items: center; gap: 3px; font-variant-numeric: tabular-nums; }
.msk-brow__t { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
.msk-brow__o { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
.msk-brow__o span {
    font-size: 11.5px; color: #64748b; background: #f8fafc;
    border: 1px solid #eef1f7; padding: 3px 9px; border-radius: 7px; white-space: nowrap;
}
.msk-brow__o em { font-style: normal; font-weight: 700; color: #047857; margin-left: 5px; font-size: 10.5px; }
.msk-brow__p {
    display: flex; gap: 7px; align-items: flex-start; margin: 8px 0 0;
    font-size: 11.5px; color: #64748b; line-height: 1.55;
}
.msk-brow__p i { color: #059669; margin-top: 2px; flex: 0 0 auto; }

/* ── ISI JENDELA TEMPLATE ────────────────────────────────────────────────── */
.msk-form { display: flex; flex-direction: column; gap: 14px; }
.msk-form__row { display: grid; grid-template-columns: 1fr 220px; gap: 14px; align-items: start; }

@media (max-width: 1024px) {
    .msk-q__grid { grid-template-columns: 1fr 1fr; }
    .msk-skala { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 720px) {
    .msk-q__grid { grid-template-columns: 1fr; }
    .msk-form__row { grid-template-columns: 1fr; }
    .msk-opsi__row { grid-template-columns: minmax(0, 1fr) auto; }
    .msk-opsi__row > *:nth-child(2),
    .msk-opsi__row > *:nth-child(3) { grid-column: 1; }
    .msk-opsi__row > *:last-child { grid-column: 2; grid-row: 1; }
    .msk-opsi__row { padding-bottom: 9px; border-bottom: 1px dashed #eef1f7; }
    .msk-skor { width: 100%; }
    .msk-vbar { gap: 9px; }
    .msk-vbar__new { width: 100%; }
    .msk-edit__btns { width: 100%; }
    .msk-edit__btns > .skr-btn { flex: 1; }
    .msk-dhead__act { width: 100%; justify-content: flex-end; }
}
</style>
