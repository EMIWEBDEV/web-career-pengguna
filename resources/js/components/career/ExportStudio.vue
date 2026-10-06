<!-- WEB CAREER — EXPORT STUDIO: rakit & cetak Berkas Seleksi Kandidat.

     TIGA PANEL:
       KIRI    susunan & centang — urutannya bisa digeser (drag)
       TENGAH  pratinjau PDF sungguhan
       KANAN   pengaturan tampilan bagian yang sedang dipilih

     ── KENAPA PRATINJAU DIPICU TOMBOL, BUKAN OTOMATIS ──────────────────────
     Pratinjau dirender dompdf sungguhan — mesin yang sama dengan unduhan,
     supaya yang terlihat benar-benar yang didapat. Rendering itu memakan
     beberapa detik. Dipicu otomatis tiap centang, admin yang menyaring
     sepuluh bagian memicu sepuluh render yang sembilan di antaranya terbuang.

     ── PRATINJAU RINGAN, UNDUHAN LENGKAP ───────────────────────────────────
     Pratinjau mengganti tiap lampiran dengan satu halaman penanda alih-alih
     mengunduhnya dari penyimpanan awan. Yang dinilai admin adalah susunan dan
     isi halaman rancangan — bukan isi KTP yang ia sendiri sudah pernah lihat.

     ── EMPAT TINGKAT DI PANEL KIRI ─────────────────────────────────────────
       bab     Sampul, Data Kandidat, Hasil Seleksi, Lampiran, Penutup
       seksi   satu formulir / satu aktivitas / satu lampiran
       bagian  babak di dalam formulir ("B. Identitas")
       field   satu pertanyaan ("Nama Lengkap")

     Klik pada tingkat mana pun MEMBUKA PENGATURANNYA di panel kanan. Melipat
     bab dipindah ke chevron di ujung kanan kepalanya: yang dicari admin saat
     menekan "Hasil Seleksi" adalah mengganti judul bab itu, bukan
     menyembunyikan isinya.

     ── APA YANG BISA DIGESER ───────────────────────────────────────────────
     Bab bisa dibolak-balik, KECUALI Sampul (selalu pertama) dan Penutup
     (selalu terakhir). Seksi bisa digeser di dalam babnya sendiri, bagian di
     dalam seksinya. Urutan bab dijaga dua kali — di layar oleh BAB_TETAP, dan
     di server oleh RakitBerkasSeleksi::urutanBab().

     ── SUSUNAN & PENGATURAN HIDUP SELAMA MODAL TERBUKA ─────────────────────
     Tidak disimpan ke mana pun. Tiap kandidat butuh susunan berbeda, dan
     preset yang tersimpan justru jadi usang begitu alur seleksinya berubah. -->
<template>
    <AdminModal
        :show="show"
        size="full"
        icon="bi-file-earmark-pdf-fill"
        title="Export Studio"
        :subtitle="subjudul"
        :busy="sedangRender"
        busy-label="Menyiapkan pratinjau…"
        foot-blok
        @close="tutup"
    >
        <div class="xps">
            <!-- ══ KIRI — SUSUNAN & CENTANG ═══════════════════════════════ -->
            <aside class="xps__kiri">
                <div class="xps__kiritop">
                    <div class="xps__hint">
                        <i class="bi bi-list-check"></i>
                        <span>Susunan &amp; isi berkas</span>
                    </div>
                    <button type="button" class="xps__mini" :disabled="memuat" @click="setelUlang">
                        <i class="bi bi-arrow-counterclockwise"></i> Setel ulang
                    </button>
                </div>

                <div v-if="memuat" class="xps__kosong">
                    <i class="bi bi-hourglass-split"></i>
                    <p>Memuat daftar isi…</p>
                </div>

                <!-- BAB IKUT BISA DIGESER, tapi hanya yang di tengah.
                     Sampul selalu halaman pertama dan Penutup selalu yang
                     terakhir — keduanya penanda batas dokumen, dan dokumen yang
                     sampulnya di tengah bukan dokumen resmi lagi. Keduanya
                     dikunci lewat handel geser yang memang tidak dipasang, dan
                     dijaga sekali lagi oleh `bolehGeserBab()` di sisi drag. -->
                <draggable
                    v-else
                    v-model="bab"
                    class="xps__bab"
                    item-key="kode"
                    handle=".xps__gesergrup"
                    :animation="150"
                    ghost-class="is-hantu"
                    :move="bolehGeserBab"
                    @end="tandaiKotor"
                >
                    <template #item="{ element: bab }">
                        <section class="xps__grup" :class="{ 'is-tunggal': bab.tunggal }">
                            <!-- BAB TUNGGAL TANPA KEPALA.
                                 Sampul, Surat Lamaran, dan Penutup masing-masing
                                 satu bab berisi satu seksi yang namanya mengulang
                                 nama babnya. Kepala babnya tidak mengelompokkan
                                 apa pun - ia baris kedua yang tak bisa digeser,
                                 tak bisa dilipat, dan pada Sampul tak bisa
                                 dicentang. Yang tampil cukup seksinya saja.

                                KLIK JUDUL = LANGSUNG SUNTING DI TEMPAT.

                                 Judul bab dulu diketik di kotak terpisah pada
                                 panel kanan. Itu memberi DUA tempat untuk satu
                                 hal: nama bab terbaca di kiri, tapi diubahnya di
                                 kanan — dan admin yang mengklik namanya di kiri
                                 menunggu sesuatu terjadi di tempat ia mengklik.
                                 Sekarang labelnya sendiri adalah kolomnya. -->
                            <header
                                v-if="!bab.tunggal"
                                class="xps__grupkepala"
                                :class="{
                                    'is-aktif': aktif === 'bab.' + bab.kode,
                                    'is-mati': !bab.bisaUbahJudul,
                                }"
                                :title="bab.bisaUbahJudul
                                    ? 'Klik judulnya untuk mengganti nama bab ini di dokumen'
                                    : judulBab(bab) + ' — judulnya tetap, tidak bisa diubah'"
                                @click="pilihBab(bab)"
                            >
                                <!-- Handel geser hanya ada pada bab yang boleh
                                     dipindah; Sampul & Penutup tidak punya. -->
                                <span
                                    v-if="babBisaGeser(bab)"
                                    class="xps__gesergrup"
                                    title="Geser untuk mengubah urutan bab"
                                    @click.stop
                                >
                                    <i class="bi bi-grip-vertical"></i>
                                </span>
                                <span v-else class="xps__gesergrup is-mati" :title="alasanBabTetap(bab)">
                                    <i class="bi bi-pin-angle-fill"></i>
                                </span>

                                <!-- Centang induk tri-state: penuh, sebagian, atau kosong. -->
                                <button
                                    type="button"
                                    class="xps__ck"
                                    :class="{
                                        'is-on': statusBab(bab) === 'penuh',
                                        'is-half': statusBab(bab) === 'sebagian',
                                    }"
                                    :disabled="!adaYangBisaDipilih(bab)"
                                    @click.stop="togelBab(bab)"
                                >
                                    <i class="bi" :class="statusBab(bab) === 'sebagian' ? 'bi-dash' : 'bi-check'"></i>
                                </button>
                                <i class="xps__grupikon bi" :class="bab.ikon"></i>

                                <!-- Judul bab = kolom sunting, bukan teks mati.
                                     `placeholder` memegang nama bawaannya, jadi
                                     mengosongkan kolom berarti "pakai yang baku"
                                     tanpa perlu tombol reset tersendiri. -->
                                <input
                                    v-if="bab.bisaUbahJudul"
                                    :value="judulUbah['bab.' + bab.kode] || ''"
                                    type="text"
                                    class="xps__gruplabel xps__gruplabel--ubah"
                                    :placeholder="bab.label"
                                    maxlength="120"
                                    :title="'Judul bab ini di dokumen — kosongkan untuk memakai ' + bab.label"
                                    @click.stop="pilihBab(bab)"
                                    @input="setJudul('bab.' + bab.kode, $event.target.value)"
                                >
                                <span v-else class="xps__gruplabel">{{ judulBab(bab) }}</span>

                                <!-- Pensil jadi PENANDA "judul bab ini bisa
                                     diganti" — kolomnya sendiri tidak berbingkai
                                     sampai disentuh, supaya baris ini tetap
                                     terbaca sebagai judul, bukan formulir. -->
                                <i
                                    v-if="bab.bisaUbahJudul"
                                    class="xps__pensil bi bi-pencil"
                                    :class="{ 'is-aktif': aktif === 'bab.' + bab.kode }"
                                ></i>
                                <span class="xps__grupjml">{{ terpilihDi(bab) }}/{{ bab.seksi.length }}</span>
                                <button
                                    type="button"
                                    class="xps__lipat"
                                    :title="lipat[bab.kode] ? 'Tampilkan isi bab' : 'Sembunyikan isi bab'"
                                    @click.stop="lipat[bab.kode] = !lipat[bab.kode]"
                                >
                                    <i
                                        class="bi"
                                        :class="lipat[bab.kode] ? 'bi-chevron-right' : 'bi-chevron-down'"
                                    ></i>
                                </button>
                            </header>

                            <!-- Urutan seksi bisa digeser DI DALAM babnya sendiri.
                                 Lintas bab tidak diizinkan: memindah "Psikotes" ke
                                 bab Lampiran menghasilkan dokumen yang babnya tidak
                                 lagi berarti apa-apa. -->
                            <!-- Bab tunggal tidak punya tombol lipat, jadi isinya
                                 tidak boleh ikut aturan lipat: tanpa `bab.tunggal`
                                 di sini satu nilai `lipat` sisa dari localStorage
                                 bisa menyembunyikannya selamanya. -->
                            <draggable
                                v-show="bab.tunggal || !lipat[bab.kode]"
                                v-model="bab.seksi"
                                class="xps__isi"
                                item-key="kunci"
                                handle=".xps__geser"
                                :animation="150"
                                ghost-class="is-hantu"
                                @end="tandaiKotor"
                            >
                                <template #item="{ element: s }">
                                    <div
                                        class="xps__baris"
                                        :class="{
                                            'is-kunci': s.terkunci,
                                            'is-wajib': s.wajib,
                                            'is-anak': s.anak,
                                            'is-aktif': aktif === s.kunci,
                                        }"
                                        @click="pilihSeksi(s)"
                                    >
                                        <span
                                            class="xps__geser"
                                            :class="{ 'is-mati': s.terkunci }"
                                            title="Geser untuk mengubah urutan"
                                        >
                                            <i class="bi bi-grip-vertical"></i>
                                        </span>

                                        <button
                                            type="button"
                                            class="xps__ck"
                                            :class="{ 'is-on': pilih[s.kunci] }"
                                            :disabled="s.terkunci || s.wajib"
                                            @click.stop="togel(s)"
                                        >
                                            <i class="bi bi-check"></i>
                                        </button>

                                        <div class="xps__teks">
                                            <div class="xps__label">
                                                {{ judulSeksi(s) }}
                                                <!-- Ikon i muncul HANYA pada seksi terkunci: ia
                                                     menjawab "kenapa tidak bisa dicentang". -->
                                                <span v-if="s.terkunci" class="xps__info" :title="s.alasanKunci">
                                                    <i class="bi bi-info-circle-fill"></i>
                                                </span>
                                                <span v-if="s.sumber === 'CAT'" class="xps__tag">Psikotes</span>
                                                <span v-if="s.wajib" class="xps__tag xps__tag--wajib">Wajib</span>
                                                <!-- Seksi yang judulnya tercetak besar di
                                                     dokumen (mis. Halaman Sampul) boleh
                                                     ditulis ulang. -->
                                                <button
                                                    v-if="s.bisaUbahJudul"
                                                    type="button"
                                                    class="xps__pensil"
                                                    :class="{ 'is-aktif': aktif === s.kunci }"
                                                    title="Ubah judul di dokumen"
                                                    @click.stop="pilihSeksi(s)"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </div>
                                            <div v-if="s.induk" class="xps__induk">{{ s.induk }}</div>
                                            <div v-if="s.catatan" class="xps__catatan">{{ s.catatan }}</div>
                                            <div v-if="s.terkunci" class="xps__kuncitxt">{{ s.alasanKunci }}</div>

                                            <!-- Bagian formulir — centang tingkat ketiga,
                                                 urutannya juga bisa digeser. -->
                                            <draggable
                                                v-if="s.bagian && s.bagian.length && pilih[s.kunci]"
                                                v-model="s.bagian"
                                                class="xps__bagian"
                                                item-key="kunci"
                                                handle=".xps__geserkecil"
                                                :animation="150"
                                                ghost-class="is-hantu"
                                                @end="tandaiKotor"
                                            >
                                                <!-- Bagian + isiannya SATU BLOK (xps__subgrup).
                                                     Dulu daftar isian dirender di v-for terpisah
                                                     SESUDAH seluruh daftar bagian, sehingga membuka
                                                     "Bagian 1" yang pertama memunculkan isiannya di
                                                     bawah keempat bagian - jauh dari baris yang diklik.

                                                     Komentar ini SENGAJA di luar <template #item>:
                                                     vuedraggable memanggil computeNodes() atas seluruh
                                                     anak slot, dan Vue 3 ikut menghitung node komentar
                                                     sebagai anak. Satu baris komentar di dalam slot
                                                     membuatnya berisi 2 node dan melempar
                                                     "Item slot must have only one child". -->
                                                <template #item="{ element: g }">
                                                    <div class="xps__subgrup">
                                                        <div
                                                            class="xps__subbaris"
                                                            :class="{
                                                                'is-aktif': aktif === g.kunci,
                                                                'is-buka': bukaField(g),
                                                            }"
                                                            @click.stop="pilihSeksi(g)"
                                                        >
                                                            <span class="xps__geserkecil" title="Geser untuk mengubah urutan">
                                                                <i class="bi bi-grip-vertical"></i>
                                                            </span>
                                                            <button
                                                                type="button"
                                                                class="xps__ck xps__ck--kecil"
                                                                :class="{ 'is-on': pilih[g.kunci] }"
                                                                @click.stop="togelBagian(g)"
                                                            >
                                                                <i class="bi bi-check"></i>
                                                            </button>

                                                            <div class="xps__subteks">
                                                                <div class="xps__sublabel">
                                                                    {{ g.label }}
                                                                    <!-- Nomor urut hanya ada pada judul
                                                                         kembar - lihat
                                                                         BerkasSeleksi::tandaiKembar(). -->
                                                                    <span v-if="g.urutKembar" class="xps__nomor">
                                                                        {{ g.urutKembar }}
                                                                    </span>
                                                                    <span v-if="g.berulang" class="xps__tag xps__tag--gaya">
                                                                        {{ labelGaya(g) }}
                                                                    </span>
                                                                </div>
                                                                <!-- Cuplikan isi: inilah yang benar-benar
                                                                     membedakan bagian berjudul sama. -->
                                                                <div v-if="g.cuplikan" class="xps__cuplikan">
                                                                    {{ g.cuplikan }}
                                                                </div>
                                                            </div>

                                                            <button
                                                                v-if="g.field && g.field.length"
                                                                type="button"
                                                                class="xps__lipatkecil"
                                                                :title="bukaField(g) ? 'Sembunyikan isian' : 'Tampilkan isian'"
                                                                @click.stop="togelLipatField(g)"
                                                            >
                                                                <i
                                                                    class="bi"
                                                                    :class="bukaField(g) ? 'bi-chevron-down' : 'bi-chevron-right'"
                                                                ></i>
                                                            </button>
                                                        </div>

                                                        <!-- Tingkat keempat: isian satu per satu.
                                                             Ada supaya pertanyaan yang sama di dua
                                                             formulir ("Nama Lengkap") bisa dimatikan
                                                             salah satunya, alih-alih tercetak dua kali. -->
                                                        <div
                                                            v-if="g.field && g.field.length && pilih[g.kunci] && bukaField(g)"
                                                            class="xps__field"
                                                        >
                                                            <div
                                                                v-for="fl in g.field"
                                                                :key="fl.kunci"
                                                                class="xps__fieldbaris"
                                                                @click.stop="togelBagian(fl)"
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="xps__ck xps__ck--kecil"
                                                                    :class="{ 'is-on': pilih[fl.kunci] }"
                                                                    @click.stop="togelBagian(fl)"
                                                                >
                                                                    <i class="bi bi-check"></i>
                                                                </button>
                                                                <span class="xps__fieldlabel">{{ fl.label }}</span>
                                                                <span v-if="fl.berkas" class="xps__tag xps__tag--berkas">Berkas</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </draggable>
                                        </div>
                                    </div>
                                </template>
                            </draggable>
                        </section>
                    </template>
                </draggable>
            </aside>

            <!-- ══ TENGAH — PRATINJAU ═════════════════════════════════════ -->
            <div class="xps__tengah">
                <div class="xps__bar">
                    <div class="xps__barkiri">
                        <i class="bi bi-eye-fill"></i>
                        <span>Pratinjau</span>
                        <span v-if="jumlahTerpilih" class="xps__barjml">{{ jumlahTerpilih }} bagian</span>
                    </div>

                    <!-- Penanda "belum diterapkan": tanpa ini admin bisa menyangka
                         pratinjau yang terlihat sudah memuat perubahan terbarunya. -->
                    <span v-if="kotor && sudahPernah" class="xps__kotor">
                        <i class="bi bi-exclamation-circle-fill"></i> Belum diterapkan
                    </span>

                    <button
                        type="button"
                        class="xps__terap"
                        :class="{ 'is-kotor': kotor }"
                        :disabled="sedangRender || !jumlahTerpilih"
                        @click="render"
                    >
                        <i class="bi" :class="sedangRender ? 'bi-hourglass-split' : 'bi-arrow-repeat'"></i>
                        {{ sedangRender ? 'Merender…' : 'Terapkan' }}
                    </button>
                </div>

                <div class="xps__layar">
                    <template v-if="urlPratinjau">
                        <iframe :src="urlPratinjau" class="xps__frame" title="Pratinjau berkas"></iframe>

                        <!-- Penutup tombol unduh & cetak milik viewer peramban.
                             Toolbar dibiarkan hidup supaya zoom, gulir, dan lompat
                             halaman tetap bisa dipakai; hanya sudut kanannya yang
                             ditutup. Peramban tidak menyediakan cara mematikan
                             tombol itu sendiri — `#toolbar=0` menghapus seluruh
                             bilah, termasuk zoom. -->
                        <div class="xps__tutupbar" aria-hidden="true"></div>
                    </template>

                    <div v-else-if="!sedangRender" class="xps__kosong xps__kosong--besar">
                        <i class="bi" :class="galat ? 'bi-exclamation-triangle' : 'bi-file-earmark-pdf'"></i>
                        <p v-if="galat" class="xps__galat">{{ galat }}</p>
                        <p v-else>Pilih bagian di kiri, lalu tekan <strong>Terapkan</strong>.</p>
                    </div>

                    <!-- TIRAI KEMAJUAN — satu untuk kedua keadaan.
                         Dipakai baik saat pratinjau pertama (layar masih kosong)
                         maupun saat render ulang (pratinjau lama masih terpasang
                         di belakangnya). Sebelumnya keduanya punya tampilan
                         sendiri, dan yang pertama hanya berupa tulisan diam. -->
                    <div v-if="sedangRender" class="xps__tirai">
                        <div class="xps__tiraikotak">
                            <div class="xps__tirailabel">
                                <i class="bi bi-file-earmark-pdf"></i>
                                <span>{{ sudahPernah ? 'Memperbarui pratinjau…' : 'Menyiapkan pratinjau…' }}</span>
                                <strong class="xps__tiraiangka">{{ progres }}%</strong>
                            </div>
                            <div
                                class="xps__bilah"
                                role="progressbar"
                                :aria-valuenow="progres"
                                aria-valuemin="0"
                                aria-valuemax="100"
                            >
                                <div class="xps__bilahisi" :style="{ width: progres + '%' }"></div>
                            </div>
                            <p class="xps__tiraiket">
                                {{ jumlahTerpilih }} bagian dirakit menjadi satu berkas.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ KANAN — PENGATURAN ═════════════════════════════════════ -->
            <aside class="xps__setelan">
                <!-- DUA TAB, karena dua hal yang berbeda sifatnya.
                     "Bagian" mengatur satu seksi yang dipilih di panel kiri.
                     "Tata letak" mengatur satu HALAMAN dokumen — dan halaman
                     tidak sama dengan seksi: satu formulir panjang jadi tiga
                     halaman, dan tiga halaman itu punya sisa ruang yang
                     berbeda-beda. Menggabungkannya di satu daftar membuat
                     admin memilih "Seleksi Administrasi" lalu bertanya-tanya
                     halaman mana dari ketiganya yang sedang ia setel. -->
                <div class="xps__tab">
                    <button
                        type="button"
                        class="xps__tabbtn"
                        :class="{ 'is-on': tab === 'bagian' }"
                        @click="tab = 'bagian'"
                    >
                        <i class="bi bi-sliders"></i>
                        <span class="xps__tabteks">Bagian</span>
                    </button>
                    <button
                        type="button"
                        class="xps__tabbtn"
                        :class="{ 'is-on': tab === 'tata' }"
                        @click="tab = 'tata'"
                    >
                        <i class="bi bi-layout-text-window"></i>
                        <span class="xps__tabteks">Tata letak</span>
                        <span v-if="jumlahDisetel" class="xps__tabjml">{{ jumlahDisetel }}</span>
                    </button>
                    <!-- Tab ini hanya ada bila memang ADA yang berulang.
                         Tab kosong permanen mengajari admin mengabaikannya,
                         dan pada hari ada isi sungguhan ia tidak dibuka. -->
                    <button
                        v-if="duplikat.length"
                        type="button"
                        class="xps__tabbtn"
                        :class="{ 'is-on': tab === 'duplikat' }"
                        title="Informasi duplikat — jawaban yang sama tercetak di dua formulir"
                        @click="tab = 'duplikat'"
                    >
                        <i class="bi bi-files"></i>
                        <span class="xps__tabteks">Duplikat</span>
                        <span class="xps__tabjml xps__tabjml--awas">{{ duplikat.length }}</span>
                    </button>
                </div>

                <!-- ══ TAB: ISIAN DUPLIKAT ═════════════════════════════════ -->
                <div v-if="tab === 'duplikat'" class="xps__setel">
                    <div class="xps__pesan xps__pesan--info">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>
                            <strong>{{ duplikat.length }} informasi duplikat.</strong>
                            <p>
                                Dua formulir menanyakan hal yang sama dengan kata berbeda.
                                Matikan salah satunya bila tidak perlu diulang — atau biarkan
                                keduanya bila memang ingin ditampilkan apa adanya.
                            </p>
                        </div>
                    </div>

                    <div v-for="g in duplikat" :key="g.kunci" class="xps__dup">
                        <div class="xps__duplabel">{{ g.anggota[0].label }}</div>
                        <div class="xps__dupnilai">{{ g.nilai }}</div>

                        <label
                            v-for="a in g.anggota"
                            :key="a.kunci"
                            class="xps__dupbaris"
                            :class="{ 'is-mati': !pilih[a.kunci] }"
                        >
                            <input
                                type="checkbox"
                                :checked="!!pilih[a.kunci]"
                                @change="togelBagian({ kunci: a.kunci })"
                            >
                            <span class="xps__dupteks">
                                <span class="xps__dupasal">{{ a.formulir }}</span>
                                <span class="xps__dupbagian">{{ a.bagian }} · {{ a.label }}</span>
                            </span>
                        </label>

                        <div class="xps__dupaksi">
                            <button type="button" class="xps__mini" @click="pakaiSatu(g, 0)">
                                Pakai yang pertama
                            </button>
                            <button type="button" class="xps__mini" @click="pakaiSemua(g)">
                                Pakai keduanya
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ══ TAB: TATA LETAK HALAMAN ═══════════════════════════ -->
                <div v-else-if="tab === 'tata'" class="xps__setel">
                    <div v-if="!halaman.length" class="xps__kosong">
                        <i class="bi bi-layout-text-window"></i>
                        <p v-if="memuatHalaman">Membaca halaman dokumen…</p>
                        <p v-else>Belum ada halaman yang bisa disetel. Tekan <strong>Terapkan</strong> dulu.</p>
                    </div>

                    <template v-else>
                        <!-- Setelan borongan: satu kerapatan untuk seluruh
                             halaman sekaligus. Ada karena kasus yang paling
                             sering bukan "halaman 3 saja", melainkan "semuanya
                             terlalu renggang". -->
                        <div class="xps__setelblok">
                            <label class="xps__setelk">Semua halaman sekaligus</label>
                            <div class="xps__gaya xps__gaya--baris">
                                <button
                                    v-for="k in KERAPATAN"
                                    :key="k.kode"
                                    type="button"
                                    class="xps__gayabtn"
                                    @click="setSemuaKerapatan(k.kode)"
                                >
                                    <i class="bi" :class="k.ikon"></i>
                                    <span>{{ k.label }}</span>
                                </button>
                            </div>
                            <p class="xps__setelnote">
                                Berlaku ke {{ halaman.length }} halaman yang bisa disetel.
                                Halaman yang isinya sudah penuh tidak terpengaruh.
                            </p>

                            <!-- Pintasan yang paling sering dipakai: merapatkan
                                 SEMUA isi ke atas sekaligus. "Rapat" sendirian
                                 hanya memampatkan sela; halaman tetap turun
                                 sedikit karena bidang isinya mulai di 92px. -->
                            <button
                                type="button"
                                class="xps__mini xps__mini--lebar"
                                @click="naikkanSemua"
                            >
                                <i class="bi bi-arrow-bar-up"></i>
                                Rapatkan semua ke atas
                            </button>
                        </div>

                        <!-- TEMA HALAMAN PENUTUP.
                             Dua pilihan saja, bukan pemilih warna bebas:
                             seluruh warna di halaman itu diturunkan dari satu
                             pilihan supaya teks selalu terbaca di atas
                             latarnya. Warna bebas menghasilkan kombinasi yang
                             tidak terbaca - itu bukan keluwesan. -->
                        <div class="xps__setelblok">
                            <label class="xps__setelk">Warna halaman penutup</label>
                            <div class="xps__gaya xps__gaya--baris">
                                <button
                                    v-for="t in TEMA_PENUTUP"
                                    :key="t.kode"
                                    type="button"
                                    class="xps__gayabtn"
                                    :class="{ 'is-on': temaPenutup === t.kode }"
                                    @click="setTemaPenutup(t.kode)"
                                >
                                    <span class="xps__contohwarna" :style="{ background: t.contoh }"></span>
                                    <span>{{ t.label }}</span>
                                </button>
                            </div>
                            <p class="xps__setelnote">{{ jelasTema }}</p>
                        </div>

                        <div
                            v-for="h in halaman"
                            :key="h.kunci"
                            class="xps__hal"
                            :class="{
                                'is-buka': halBuka === h.kunci,
                                'is-ubah': sudahDisetel(h),
                                'is-sambung': h.sambung,
                            }"
                        >
                            <header class="xps__halkepala" @click="halBuka = halBuka === h.kunci ? '' : h.kunci">
                                <!-- Halaman yang berbagi lembar memakai lencana
                                     penyambung, bukan nomor: nomornya sama
                                     dengan halaman di atasnya, dan dua baris
                                     bernomor sama terbaca seperti kekeliruan. -->
                                <span v-if="h.sambung" class="xps__halno xps__halno--sambung" title="Berbagi halaman dengan bagian di atasnya">
                                    <i class="bi bi-arrow-return-right"></i>
                                </span>
                                <span v-else class="xps__halno">{{ h.no }}</span>
                                <div class="xps__halteks">
                                    <div class="xps__hallabel">{{ h.label }}</div>
                                    <div class="xps__halket">{{ ketHalaman(h) }}</div>
                                </div>
                                <span v-if="sudahDisetel(h)" class="xps__haltanda" title="Disetel manual">
                                    <i class="bi bi-dot"></i>
                                </span>
                                <i class="bi" :class="halBuka === h.kunci ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                            </header>

                            <div v-if="halBuka === h.kunci" class="xps__halisi">
                                <!-- Halaman yang isinya sudah memenuhi kanvas
                                     tidak menawarkan kerapatan: menambah sela di
                                     sana mendorong baris terakhir keluar kanvas,
                                     dan yang hilang justru isi yang sebenarnya
                                     ada. Ukuran teks tetap ditawarkan. -->
                                <!-- Halaman bertata-letak TETAP (sampul,
                                     pembatas bab, penutup): isinya diletakkan
                                     mutlak terhadap kanvas, bukan mengalir dari
                                     atas. Dikatakan apa adanya — menawarkan
                                     tombol yang diam-diam tidak berefek jauh
                                     lebih buruk daripada mengakui halaman ini
                                     memang tidak disetel. -->
                                <div v-if="h.tata.tetap" class="xps__pesan">
                                    <i class="bi bi-lock-fill"></i>
                                    <div>
                                        <strong>Tata letaknya tetap.</strong>
                                        <p>
                                            Halaman ini komposisi utuh — judul, garis, dan kakinya
                                            dipatok pada posisi tertentu di kanvas. Yang bisa diubah
                                            hanya judul babnya (panel Bagian)<template v-if="h.kunci === 'penutup'"> dan warna halaman
                                            penutup di atas</template>.
                                        </p>
                                    </div>
                                </div>

                                <div v-else-if="h.tata.padat" class="xps__pesan">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <div>
                                        <strong>Halaman ini sudah penuh.</strong>
                                        <p>
                                            Tidak ada ruang kosong yang perlu dibagi. Merenggangkannya
                                            justru mendorong baris terakhir keluar halaman.
                                        </p>
                                    </div>
                                </div>

                                <template v-else>
                                    <div class="xps__setelblok">
                                        <label class="xps__setelk">Kerapatan</label>
                                        <div class="xps__gaya xps__gaya--baris">
                                            <button
                                                v-for="k in KERAPATAN"
                                                :key="k.kode"
                                                type="button"
                                                class="xps__gayabtn"
                                                :class="{ 'is-on': kerapatanHal(h) === k.kode }"
                                                @click="setHal(h, 'kerapatan', k.kode)"
                                            >
                                                <i class="bi" :class="k.ikon"></i>
                                                <span>{{ k.label }}</span>
                                            </button>
                                        </div>
                                        <p class="xps__setelnote">{{ jelasKerapatan(h) }}</p>
                                    </div>

                                    <div class="xps__setelblok">
                                        <label class="xps__setelk">Jarak antar-bagian</label>
                                        <div class="xps__geserbaris">
                                            <input
                                                :value="selaHal(h)"
                                                type="range"
                                                min="10"
                                                max="72"
                                                step="2"
                                                class="xps__slider"
                                                @input="setHal(h, 'sela', +$event.target.value)"
                                            >
                                            <span class="xps__geserangka">{{ selaHal(h) }}px</span>
                                        </div>
                                        <p class="xps__setelnote">
                                            Menggesernya sendiri melepas halaman ini dari kerapatan
                                            di atas — angka Anda yang dipakai.
                                        </p>
                                    </div>

                                    <!-- Dua arah: negatif menaikkan, positif
                                         menurunkan. Halaman yang isinya sedikit
                                         tidak selalu ingin diturunkan - sering
                                         yang dicari justru dirapatkan ke atas
                                         supaya ruang kosongnya berkumpul jadi
                                         satu di kaki, bukan terbelah tipis. -->
                                    <div class="xps__setelblok">
                                        <label class="xps__setelk">Posisi isi</label>
                                        <div class="xps__geserbaris">
                                            <input
                                                :value="geserHal(h)"
                                                type="range"
                                                min="-30"
                                                :max="Math.max(0, h.tata.sisa)"
                                                step="4"
                                                class="xps__slider"
                                                @input="setHal(h, 'geser', +$event.target.value)"
                                            >
                                            <span class="xps__geserangka">{{ geserHal(h) }}px</span>
                                        </div>
                                        <p class="xps__setelnote">
                                            Geser ke kiri untuk <strong>menaikkan</strong> isi
                                            merapat ke kop, ke kanan untuk menurunkannya.
                                        </p>
                                    </div>
                                </template>

                                <div class="xps__setelblok">
                                    <label class="xps__setelk">Ukuran teks</label>
                                    <div class="xps__geserbaris">
                                        <input
                                            :value="teksHal(h)"
                                            type="range"
                                            min="9"
                                            max="13"
                                            step="1"
                                            class="xps__slider"
                                            @input="setHal(h, 'teks', +$event.target.value)"
                                        >
                                        <span class="xps__geserangka">{{ teksHal(h) }}px</span>
                                    </div>
                                    <p class="xps__setelnote">
                                        Dibatasi 9–13px: di bawah itu isian tidak terbaca pada
                                        cetakan kertas, di atasnya dokumen justru bertambah panjang.
                                    </p>
                                </div>

                                <!-- SAKLAR PENGGABUNGAN.
                                     Sistem menggabung sendiri begitu isinya
                                     muat, tapi "muat" tidak sama dengan
                                     "pantas": satu formulir yang secara resmi
                                     harus mulai di lembar baru tetap berhak
                                     begitu, dan itu penilaian yang tidak bisa
                                     dihitung sistem. -->
                                <div class="xps__setelblok">
                                    <label class="xps__saklar">
                                        <input
                                            type="checkbox"
                                            :checked="!!setelHal[h.kunci]?.sendiri"
                                            @change="setHal(h, 'sendiri', $event.target.checked || null)"
                                        >
                                        <span>Mulai di halaman sendiri</span>
                                    </label>
                                    <p class="xps__setelnote">
                                        <template v-if="h.sambung">
                                            Sekarang berbagi lembar dengan bagian di atasnya karena
                                            isinya muat. Centang untuk memaksanya mulai di lembar baru.
                                        </template>
                                        <template v-else>
                                            Bagian ini sudah mulai di lembarnya sendiri. Centang untuk
                                            menjaganya tetap begitu walau nanti isinya menyusut.
                                        </template>
                                    </p>
                                </div>

                                <button
                                    v-if="sudahDisetel(h)"
                                    type="button"
                                    class="xps__mini xps__mini--lebar"
                                    @click="resetHal(h)"
                                >
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                    Kembalikan ke hitungan sistem
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div v-else-if="!seksiAktif" class="xps__kosong">
                    <i class="bi bi-hand-index-thumb"></i>
                    <p>Pilih satu bagian di panel kiri untuk mengatur tampilannya.</p>
                </div>

                <div v-else class="xps__setel">
                    <div class="xps__setelkepala">
                        <div class="xps__setelrole">
                            <i class="bi" :class="ikonAktif"></i>
                            <span>{{ perananAktif }}</span>
                        </div>
                        <div class="xps__setellabel">{{ judulAktif }}</div>
                        <div v-if="seksiAktif.induk" class="xps__induk">{{ seksiAktif.induk }}</div>
                    </div>

                    <!-- Yang TIDAK punya satu pun pengaturan (Halaman Sampul,
                         Halaman Penutup, Surat Lamaran, tiap hasil seleksi dan
                         lampiran) tetap membuka panel ini — tapi panelnya harus
                         mengatakan KENAPA kosong. Panel yang terbuka tanpa satu
                         pun kendali, tanpa penjelasan, terbaca sebagai layar
                         yang rusak. -->
                    <div v-if="tanpaPengaturan" class="xps__pesan">
                        <i class="bi bi-lock-fill"></i>
                        <div>
                            <strong>Bagian ini tidak punya pengaturan.</strong>
                            <p>{{ alasanTanpaPengaturan }}</p>
                        </div>
                    </div>

                    <!-- KOLOM JUDUL TIDAK ADA DI SINI — sengaja.
                         Judul disunting langsung pada namanya di panel kiri.
                         Kotak kedua di panel ini dulu membuat satu hal punya dua
                         tempat menulis: nama bab terbaca di kiri, tapi diubahnya
                         di kanan. Yang tersisa di sini hanya penunjuk arah. -->
                    <div v-if="seksiAktif.bisaUbahJudul" class="xps__pesan xps__pesan--info">
                        <i class="bi bi-pencil-square"></i>
                        <div>
                            <strong>Judul bab ini bisa diganti.</strong>
                            <p>
                                Ketik langsung pada namanya di panel kiri — misal
                                "Hasil Seleksi" jadi "Summary". Kosongkan untuk
                                kembali memakai <strong>{{ judulBawaan }}</strong>.
                            </p>
                        </div>
                    </div>

                    <!-- BENTUK ISIAN PENDEK — untuk bagian yang isinya
                         label+nilai, bukan daftar berulang. Bagian seperti
                         "Kesiapan Penempatan & Kerja" yang isinya dua angka
                         penting terbaca lebih baik sebagai kartu berbingkai
                         daripada kisi polos. -->
                    <!-- PENEMPATAN DI HALAMAN — hanya bagian CV.
                         Bawaannya sudah direkomendasikan per peran: fakta
                         ringkas ke kiri, riwayat yang dinilai ke kolom utama,
                         deret pernyataan ke lajur penuh. Ini untuk posisi yang
                         menuntut penekanan berbeda — keahlian teknis layak
                         naik ke kolom utama pada lowongan engineering. -->
                    <div v-if="seksiAktif.bisaLajur" class="xps__setelblok">
                        <label class="xps__setelk">Penempatan</label>
                        <div class="xps__gaya xps__gaya--baris">
                            <button
                                v-for="l in LAJUR"
                                :key="l.kode"
                                type="button"
                                class="xps__gayabtn"
                                :class="{ 'is-on': lajurSeksi(seksiAktif) === l.kode }"
                                @click="setLajur(seksiAktif, l.kode)"
                            >
                                <i class="bi" :class="l.ikon"></i>
                                <span>{{ l.label }}</span>
                            </button>
                        </div>
                        <p class="xps__setelnote">{{ jelasLajur }}</p>
                    </div>

                    <div v-if="seksiAktif.bisaBentuk" class="xps__setelblok">
                        <label class="xps__setelk">Bentuk isian</label>
                        <div class="xps__gaya xps__gaya--baris">
                            <button
                                v-for="b in BENTUK"
                                :key="b.kode"
                                type="button"
                                class="xps__gayabtn"
                                :class="{ 'is-on': bentukSeksi(seksiAktif) === b.kode }"
                                @click="setBentuk(seksiAktif, b.kode)"
                            >
                                <i class="bi" :class="b.ikon"></i>
                                <span>{{ b.label }}</span>
                            </button>
                        </div>
                        <p class="xps__setelnote">{{ jelasBentuk }}</p>
                    </div>

                    <!-- Gaya hanya berlaku untuk bagian BERULANG — daftar riwayat
                         yang punya beberapa baris. Isian tunggal tidak punya
                         bentuk alternatif yang masuk akal. -->
                    <div v-if="seksiAktif.berulang" class="xps__setelblok">
                        <label class="xps__setelk">Bentuk tampilan</label>
                        <div class="xps__gaya">
                            <button
                                v-for="g in gayaTersedia"
                                :key="g.kode"
                                type="button"
                                class="xps__gayabtn"
                                :class="{ 'is-on': gayaSeksi(seksiAktif) === g.kode }"
                                @click="setGaya(seksiAktif, g.kode)"
                            >
                                <i class="bi" :class="g.ikon"></i>
                                <span>{{ g.label }}</span>
                            </button>
                        </div>
                        <p class="xps__setelnote">{{ keteranganGaya }}</p>
                        <p class="xps__setelnote">
                            Hanya mengubah <em>bentuk</em> daftarnya di dokumen; isi
                            dan urutan datanya tetap sama.
                        </p>
                    </div>

                    <!-- Slider jarak HANYA pada seksi yang benar-benar
                         membacanya (halaman formulir & bagiannya, ditandai
                         `bisaJarak` oleh BerkasSeleksi). Sampul, penutup, hasil
                         seleksi, dan lampiran mengabaikannya: isinya berposisi
                         mutlak atau berupa PDF yang digabung apa adanya. -->
                    <div v-if="seksiAktif.bisaJarak" class="xps__setelblok">
                        <label class="xps__setelk">Jarak atas</label>
                        <div class="xps__geserbaris">
                            <input
                                v-model.number="jarakAtas[seksiAktif.kunci]"
                                type="range"
                                min="0"
                                max="60"
                                step="2"
                                class="xps__slider"
                                @change="tandaiKotor"
                            >
                            <span class="xps__geserangka">{{ jarakAtas[seksiAktif.kunci] || 0 }}px</span>
                        </div>
                        <p class="xps__setelnote">
                            Menambah ruang kosong di atas bagian ini bila hasil cetaknya
                            terasa terlalu rapat dengan judul di atasnya.
                        </p>
                    </div>

                    <!-- ── KETERANGAN, BUKAN SAKLAR ──────────────────────────
                         Di sini dulu ada saklar "Mulai di halaman baru". Saklar
                         itu DIHAPUS karena tidak pernah berefek apa pun:
                         dokumen.blade.php memasang pemutus halaman pada SETIAP
                         seksi, jadi tiap bagian memang selalu memulai halaman
                         sendiri — dinyalakan atau tidak, hasilnya sama.

                         Saklar yang tidak mengubah apa-apa lebih buruk daripada
                         tidak ada saklar: admin mengira sudah mengatur sesuatu,
                         lalu bingung kenapa cetakannya tidak berubah. Yang
                         menggantikannya adalah keterangan tentang perilaku yang
                         memang sudah berlaku. -->
                    <div v-if="!seksiAktif.bab" class="xps__pesan xps__pesan--info">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>
                            <strong>Tata letak halaman</strong>
                            <p>
                                Tiap bagian selalu dimulai di halaman sendiri, dan
                                bagian yang isinya panjang otomatis disambung ke
                                halaman berikutnya. Tidak perlu diatur.
                            </p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <template #footer>
            <div class="xps__kaki">
                <!-- Yang diganti penanda di pratinjau HANYA lampiran dokumen.
                     Berkas penilaian FGD/wawancara selalu tampil aslinya, juga
                     di sini - ia isi bab Hasil Seleksi, bukan lampiran, dan
                     justru bagian itulah yang perlu diperiksa sebelum cetak. -->
                <span class="xps__kakinote">
                    <i class="bi bi-shield-check"></i>
                    Lampiran dokumen ditampilkan utuh pada berkas yang diunduh.
                </span>
                <div class="xps__kakibtn">
                    <button type="button" class="xps__batal" @click="tutup">Tutup</button>
                    <button
                        type="button"
                        class="xps__unduh"
                        :disabled="!jumlahTerpilih || sedangKirim"
                        @click="unduh"
                    >
                        <i class="bi" :class="sedangKirim ? 'bi-hourglass-split' : 'bi-download'"></i>
                        {{ sedangKirim ? 'Menyiapkan…' : 'Cetak & Unduh' }}
                    </button>
                </div>
            </div>
        </template>
    </AdminModal>

    <!-- ══ ALERT SAMBUNGAN HCLEARN ═══════════════════════════════════════
         Ditaruh di <Teleport to="body">, DI LUAR modal: modal punya konteks
         penumpukannya sendiri (transform/overflow), dan z-index setinggi apa
         pun di dalamnya tetap terkurung di sana. Satu-satunya cara menjamin
         alert ini di atas segalanya adalah memindahkannya ke body.

         Isinya peringatan bahwa berkas tercetak TANPA hasil psikotes — kalau
         tertutup modal, admin menilai dari dokumen yang tidak lengkap. -->
    <Teleport to="body">
        <div v-if="alert" class="xps__alert" role="alert" aria-live="assertive">
            <div class="xps__alertkotak" :class="`is-${alert.jenis}`">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div class="xps__alertisi">
                    <strong>{{ alert.judul }}</strong>
                    <p>{{ alert.pesan }}</p>
                </div>
                <button type="button" class="xps__alerttutup" @click="tutupAlert">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <!-- ══ PANDUAN PANEL ══════════════════════════════════════════════ -->
        <div v-if="panduan" class="xps__panduan">
            <div class="xps__panduankotak">
                <p class="xps__panduanjudul">Tiga bagian layar ini</p>

                <div class="xps__panduanbaris">
                    <span class="xps__panduanno">1</span>
                    <div>
                        <strong>Kiri — isi berkas</strong>
                        <p>Centang bagian mana yang ikut tercetak. Bagian yang kosong tidak muncul di daftar.</p>
                    </div>
                </div>

                <div class="xps__panduanbaris">
                    <span class="xps__panduanno">2</span>
                    <div>
                        <strong>Tengah — pratinjau</strong>
                        <p>Persis seperti hasil unduhan, kecuali lampiran &amp; laporan psikotes yang diganti halaman penanda supaya cepat.</p>
                    </div>
                </div>

                <div class="xps__panduanbaris">
                    <span class="xps__panduanno">3</span>
                    <div>
                        <strong>Kanan — tampilan</strong>
                        <p><em>Bagian</em> mengatur judul, bentuk, dan penempatan kiri/tengah. <em>Tata letak</em> mengatur kerapatan tiap halaman.</p>
                    </div>
                </div>

                <div class="xps__panduanaksi">
                    <button type="button" class="xps__panduanlain" @click="tutupPanduan(true)">
                        Jangan tampilkan lagi
                    </button>
                    <button type="button" class="xps__panduanok" @click="tutupPanduan(false)">
                        Mengerti
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script>
import axios from 'axios';
import draggable from 'vuedraggable';
import AdminModal from '@career/AdminModal.vue';
import { bacaSetelan, hapusSetelan, simpanSetelan } from '@utils/setelanBerkas';

const CFG = { headers: { Accept: 'application/json' } };

/** Bentuk tampilan yang tersedia untuk bagian berulang. */
/**
 * Bab yang posisinya TETAP di dokumen.
 *
 * Sampul selalu halaman pertama dan Penutup selalu yang terakhir: keduanya
 * penanda batas berkas resmi, dan dokumen yang sampulnya terselip di tengah
 * bukan lagi dokumen yang bisa diarsipkan. Sisanya — Surat Lamaran, Data
 * Kandidat, Hasil Seleksi, Lampiran — bebas dibolak-balik admin.
 */
const BAB_TETAP = {
    SAMPUL: 'Sampul selalu jadi halaman pertama dokumen.',
    PENUTUP: 'Penutup selalu jadi halaman terakhir dokumen.',
};

const GAYA = [
    {
        kode: 'timeline',
        label: 'Timeline',
        ikon: 'bi-list-nested',
        jelas: 'Bertitik dan bergaris — paling terbaca untuk riwayat berurutan waktu.',
    },
    {
        kode: 'tabel',
        label: 'Tabel',
        ikon: 'bi-table',
        jelas: 'Baris bernomor berkolom — ringkas untuk daftar panjang tanpa uraian.',
    },
    {
        kode: 'kartu',
        label: 'Kartu',
        ikon: 'bi-card-text',
        jelas: 'Kotak bernomor per entri — muat untuk isian yang kolomnya berbeda-beda.',
    },
];

/**
 * Kerapatan halaman — seberapa banyak sisa ruang dipakai meregangkan isi.
 *
 * Harus sama persis dengan TataLetakHalaman::KERAPATAN di sisi PHP. Yang
 * mencetak adalah hitungan server; daftar ini hanya nama & keterangan untuk
 * layar. Menambah tingkat baru berarti menambahnya di KEDUA tempat.
 */
/**
 * Tema warna halaman penutup.
 *
 * Harus sama dengan yang dikenali penutup.blade.php — di sana seluruh warna
 * (cincin, bingkai, pita, teks) diturunkan dari pilihan ini.
 */
const TEMA_PENUTUP = [
    {
        kode: 'navy',
        label: 'Navy',
        contoh: '#0f172a',
        jelas: 'Latar navy dengan aksen emas — bawaan rancangan.',
    },
    {
        kode: 'emas',
        label: 'Emas',
        contoh: '#d4a93a',
        jelas: 'Latar emas dengan teks gelap dan pita navy. Warna teks & bingkai ikut menyesuaikan supaya tetap terbaca.',
    },
];

/**
 * Bentuk isian pendek (label + nilai) di halaman formulir.
 *
 * Harus sama dengan yang dikenali formulir.blade.php.
 */
const BENTUK = [
    {
        kode: 'kisi',
        label: 'Kisi',
        ikon: 'bi-layout-three-columns',
        jelas: 'Dua kolom tanpa bingkai — paling padat, bawaan untuk bagian berisi banyak isian.',
    },
    {
        kode: 'kartu',
        label: 'Kartu',
        ikon: 'bi-window',
        jelas: 'Kotak berbingkai per isian, nilainya dicetak besar. Cocok untuk bagian berisi satu-dua angka penting.',
    },
    {
        kode: 'baris',
        label: 'Baris',
        ikon: 'bi-list',
        jelas: 'Satu isian satu baris berbingkai. Untuk label panjang yang tidak muat di kisi dua kolom.',
    },
];

/**
 * Penempatan bagian CV pada halaman.
 *
 * Harus sama dengan SusunCv::BAGIAN['lajur'] di sisi PHP — di sana yang
 * benar-benar menentukan cetakannya.
 */
const LAJUR = [
    {
        kode: 'kiri',
        label: 'Kiri',
        ikon: 'bi-layout-sidebar',
        jelas: 'Kolom sempit — fakta ringkas yang disapu cepat untuk verifikasi: pendidikan, kemampuan, data pribadi.',
    },
    {
        kode: 'utama',
        label: 'Utama',
        ikon: 'bi-layout-sidebar-reverse',
        jelas: 'Kolom lebar — riwayat yang benar-benar dibaca untuk menilai: pengalaman, organisasi, sertifikasi.',
    },
    {
        kode: 'penuh',
        label: 'Penuh',
        ikon: 'bi-layout-text-window-reverse',
        jelas: 'Selebar halaman, di halaman tersendiri — deret pernyataan dan daftar dokumen yang butuh kolom jawaban di kanan.',
    },
];

const KERAPATAN = [
    {
        kode: 'rapat',
        label: 'Rapat',
        ikon: 'bi-chevron-bar-contract',
        jelas: 'Isi menempel di atas, sisa ruang dibiarkan di kaki halaman.',
    },
    {
        kode: 'seimbang',
        label: 'Seimbang',
        ikon: 'bi-distribute-vertical',
        jelas: 'Sisa ruang dibagi sebagian ke sela antar-bagian. Bawaan sistem.',
    },
    {
        kode: 'penuh',
        label: 'Penuh',
        ikon: 'bi-chevron-bar-expand',
        jelas: 'Isi direntangkan hampir memenuhi kanvas, menyisakan ruang nafas di kaki.',
    },
];

export default {
    name: 'ExportStudio',
    components: { AdminModal, draggable },

    props: {
        show: { type: Boolean, default: false },
        // Hashid lamaran — semua id di URL memakai hashid.
        lamaranId: { type: String, default: '' },
        nama: { type: String, default: '' },
        kode: { type: String, default: '' },
    },

    emits: ['close', 'unduh'],

    data() {
        return {
            memuat: false,
            bab: [],
            pilih: {},
            lipat: {},
            // Daftar isian per bagian sengaja TERLIPAT bawaannya: satu formulir
            // bisa punya 40+ pertanyaan, dan membentangkan semuanya membuat
            // panel kiri tak bisa dibaca.
            lipatField: {},
            // Pengaturan per seksi — semuanya sekali pakai, hidup selama modal
            // terbuka. Lihat catatan di kepala berkas.
            gaya: {},
            bentuk: {},
            judulUbah: {},
            jarakAtas: {},
            aktif: '',
            urlPratinjau: '',
            sedangRender: false,
            // Alert sambungan HCLearn — lihat periksaHclearn().
            alert: null,
            hclearn: null,
            // Panduan panel, ditampilkan sesudah pratinjau pertama siap.
            panduan: false,
            sedangKirim: false,
            galat: '',
            // Susunan/centang berubah sejak render terakhir.
            kotor: false,
            sudahPernah: false,
            gayaTersedia: GAYA,
            BENTUK,
            LAJUR,
            lajur: {},
            KERAPATAN,
            // Kemajuan render — lihat mulaiProgres().
            progres: 0,
            progresMulai: 0,
            progresNyata: false,
            jamProgres: null,
            // Pemantau antrean pratinjau — lihat pantauPratinjau().
            jamPantau: null,
            // ── TATA LETAK HALAMAN ──────────────────────────────────────
            // `halaman` datang dari server sesudah tiap render: daftar halaman
            // yang BENAR-BENAR ada di dokumen barusan, lengkap dengan hitungan
            // sistemnya. `setelHal` adalah yang ditetapkan admin di atasnya.
            tab: 'bagian',
            TEMA_PENUTUP,
            temaPenutup: 'navy',
            // Kelompok informasi duplikat dari server — lihat DuplikatIsian.
            duplikat: [],
            halaman: [],
            memuatHalaman: false,
            halBuka: '',
            setelHal: {},
        };
    },

    computed: {
        subjudul() {
            return [this.nama, this.kode].filter(Boolean).join(' · ') || 'Berkas Seleksi Kandidat';
        },

        jumlahTerpilih() {
            return Object.values(this.pilih).filter(Boolean).length;
        },

        /** Seksi/bab yang sedang dipilih di panel kiri — sumber isi panel kanan. */
        seksiAktif() {
            if (!this.aktif) return null;

            // Bab ditandai `bab: true` supaya panel kanan tahu hanya judulnya
            // yang bisa diatur — bukan gaya maupun jarak.
            if (this.aktif.startsWith('bab.')) {
                const b = this.bab.find((x) => `bab.${x.kode}` === this.aktif);

                // Yang sampai ke sini hanya bab yang PUNYA kepala: Data
                // Kandidat, Hasil Seleksi, dan Lampiran — ketiganya boleh
                // ditulis ulang judulnya. Sampul, Surat Lamaran, dan Penutup
                // dirender tanpa kepala bab (`tunggal`), jadi yang diklik di
                // sana selalu SEKSInya, bukan babnya.
                //
                // `bisaUbahJudul` tetap dibawa apa adanya: kalau suatu saat ada
                // bab berkepala yang judulnya tetap, panel kanan masih bisa
                // menerangkan kenapa kosong lewat alasanTanpaPengaturan().
                return b
                    ? {
                        kunci: this.aktif,
                        label: b.label,
                        bab: true,
                        bisaUbahJudul: !!b.bisaUbahJudul,
                        judulDokumen: b.label,
                    }
                    : null;
            }

            for (const b of this.bab) {
                for (const s of b.seksi) {
                    if (s.kunci === this.aktif) return s;

                    const g = (s.bagian || []).find((x) => x.kunci === this.aktif);

                    if (g) return g;
                }
            }

            return null;
        },

        /** Keterangan tema penutup yang sedang dipilih. */
        jelasTema() {
            const t = TEMA_PENUTUP.find((x) => x.kode === this.temaPenutup);

            return t ? t.jelas : '';
        },

        /** Keterangan penempatan yang sedang dipilih. */
        jelasLajur() {
            const l = LAJUR.find((x) => x.kode === this.lajurSeksi(this.seksiAktif));

            return l ? l.jelas : '';
        },

        /** Keterangan bentuk isian pendek yang sedang dipilih. */
        jelasBentuk() {
            const b = BENTUK.find((x) => x.kode === this.bentukSeksi(this.seksiAktif));

            return b ? b.jelas : '';
        },

        keteranganGaya() {
            const g = GAYA.find((x) => x.kode === this.gayaSeksi(this.seksiAktif));

            return g ? g.jelas : '';
        },

        /**
         * Benar bila panel kanan tidak punya satu pun kendali untuk ditampilkan.
         *
         * Disusun dari syarat blok-bloknya sendiri, bukan dari daftar kunci yang
         * ditulis tangan: menambah satu jenis pengaturan nanti cukup menambah
         * satu suku di sini, dan tidak ada daftar terpisah yang bisa ketinggalan
         * lalu membuat panel mengaku kosong padahal ada isinya.
         */
        tanpaPengaturan() {
            const s = this.seksiAktif;

            if (!s) return false;

            return !s.bisaUbahJudul && !s.berulang && !s.bisaJarak;
        },

        /**
         * Kenapa panelnya kosong — dijawab menurut jenis bagiannya.
         *
         * Alasannya berbeda-beda dan admin berhak tahu yang mana: sampul tetap
         * demi keseragaman arsip, surat lamaran menunggu templat, sedangkan
         * hasil seleksi & lampiran memang PDF asli yang tidak boleh disentuh.
         * Satu kalimat umum untuk ketiganya akan terasa seperti penolakan
         * tanpa sebab.
         */
        alasanTanpaPengaturan() {
            const s = this.seksiAktif;

            if (!s) return '';

            if (s.terkunci && s.alasanKunci) return s.alasanKunci;

            if (s.kunci === 'sampul') {
                return 'Halaman sampul adalah muka dokumen resmi. Judul dan tata '
                    + 'letaknya sengaja sama untuk seluruh kandidat supaya berkas '
                    + 'yang tercetak bisa dikenali sebagai satu jenis arsip. '
                    + 'Isinya — nama, nomor lamaran, dan alur seleksi — datang '
                    + 'dari data lamaran ini.';
            }

            if (s.kunci === 'penutup') {
                return 'Halaman penutup hanya penanda akhir dokumen dan pernyataan '
                    + 'kerahasiaan. Isinya baku, jadi tidak ada yang perlu diatur. '
                    + 'Centangnya bisa dilepas di panel kiri bila tidak diperlukan.';
            }

            if (s.bab) {
                return `${s.label} adalah bagian baku dokumen — judulnya tidak `
                    + 'diganti supaya berkas dari seluruh kandidat tetap seragam. '
                    + 'Isinya tetap bisa dicentang dan digeser di panel kiri.';
            }

            return 'Isinya dicetak apa adanya dari sumber aslinya, jadi tidak ada '
                + 'tata letak yang bisa disesuaikan di sini. Yang bisa diatur '
                + 'adalah ikut atau tidaknya bagian ini, lewat centang di panel kiri.';
        },

        /**
         * Sebutan tingkat yang sedang dibuka — "Bab", "Bagian", atau "Sub-bagian".
         *
         * Panel kanan menampung empat tingkat yang tampilannya mirip. Tanpa
         * sebutan ini admin yang menekan "Hasil Seleksi" (bab) dan yang menekan
         * "Psikotes" (seksi di dalamnya) melihat panel yang nyaris sama, lalu
         * mengira pengaturannya berlaku untuk hal yang berbeda dari yang
         * sebenarnya.
         */
        perananAktif() {
            const s = this.seksiAktif;

            if (!s) return '';
            if (s.bab) return 'Bab';
            if (s.field || s.berulang) return 'Sub-bagian';

            return 'Bagian';
        },

        ikonAktif() {
            const s = this.seksiAktif;

            if (!s) return 'bi-sliders';
            if (s.bab) return 'bi-bookmarks-fill';

            return s.berulang ? 'bi-list-ol' : 'bi-file-text-fill';
        },

        /** Judul yang sedang berlaku — yang ditulis ulang admin bila ada. */
        judulAktif() {
            const s = this.seksiAktif;

            if (!s) return '';

            return (this.judulUbah[s.kunci] || '').trim() || s.label;
        },

        /** Judul bawaan bila kolom judul dikosongkan. */
        judulBawaan() {
            const s = this.seksiAktif;

            if (!s) return '';

            return s.judulDokumen || s.label;
        },

        /**
         * Muatan untuk server: kunci terpilih + pengaturannya, DALAM URUTAN
         * yang tampak di panel kiri. Urutan array itulah kontraknya — server
         * merakit dokumen mengikutinya, bukan mengikuti urutan bawaannya.
         */
        muatan() {
            const kunci = [];
            const atur = {};

            this.bab.forEach((b) => {
                // Judul bab yang ditulis ulang ikut dikirim lewat kunci
                // 'bab.<KODE>' — dipakai halaman pemisah bab.
                const kb = `bab.${b.kode}`;

                if ((this.judulUbah[kb] || '').trim()) {
                    atur[kb] = { judul: this.judulUbah[kb].trim() };
                }

                b.seksi.forEach((s) => {
                    if (this.pilih[s.kunci]) kunci.push(s.kunci);

                    (s.bagian || []).forEach((g) => {
                        if (this.pilih[g.kunci]) kunci.push(g.kunci);

                        (g.field || []).forEach((fl) => {
                            if (this.pilih[fl.kunci]) kunci.push(fl.kunci);
                        });
                    });
                });
            });

            kunci.forEach((k) => {
                const a = {};

                if (this.gaya[k]) a.gaya = this.gaya[k];
                if (this.bentuk[k] && this.bentuk[k] !== 'kisi') a.bentuk = this.bentuk[k];
                if (this.lajur[k]) a.lajur = this.lajur[k];
                if ((this.judulUbah[k] || '').trim()) a.judul = this.judulUbah[k].trim();
                if (this.jarakAtas[k]) a.jarakAtas = this.jarakAtas[k];

                if (Object.keys(a).length) atur[k] = a;
            });

            // Tata letak per halaman masuk di bawah `atur.hal`, terpisah dari
            // pengaturan per seksi: kuncinya adalah kunci HALAMAN
            // ('formulir.<hash>#<potongan>'), yang hidup di ruang nama sendiri
            // dan tidak boleh bertabrakan dengan kunci seksi.
            const hal = {};

            Object.entries(this.setelHal).forEach(([k, v]) => {
                const bersih = {};

                if (v?.kerapatan) bersih.kerapatan = v.kerapatan;
                if (v?.sela != null) bersih.sela = v.sela;
                if (v?.teks != null) bersih.teks = v.teks;
                if (v?.geser != null) bersih.geser = v.geser;
                if (v?.sendiri) bersih.sendiri = true;

                if (Object.keys(bersih).length) hal[k] = bersih;
            });

            if (Object.keys(hal).length) atur.hal = hal;

            // Tema penutup hanya dikirim bila BUKAN bawaan — muatan yang
            // memuat nilai bawaan membuat setiap dokumen terlihat "disetel"
            // padahal tidak ada yang disentuh.
            if (this.temaPenutup !== 'navy') atur.penutup = { tema: this.temaPenutup };

            // Urutan bab ikut dikirim: server merakit dokumen mengikuti
            // susunan yang ditampilkan di panel kiri, bukan urutan bawaannya.
            return { kunci, atur, bab: this.bab.map((b) => b.kode) };
        },

        /** Berapa halaman yang tata letaknya sudah disentuh admin. */
        jumlahDisetel() {
            return Object.values(this.setelHal).filter(
                (v) => v && Object.values(v).some((x) => x != null && x !== ''),
            ).length;
        },
    },

    watch: {
        show(baru) {
            if (baru) {
                this.muat();
            } else {
                this.bersihkan();
            }
        },
    },

    beforeUnmount() {
        this.bersihkan();
    },

    methods: {
        async muat() {
            this.memuat = true;
            this.galat = '';

            try {
                const { data } = await axios.get(
                    `/api/v1/karir/lamaran/${this.lamaranId}/berkas-seleksi/opsi`,
                    CFG,
                );

                this.bab = data?.result?.bab || [];
                this.duplikat = data?.result?.duplikat || [];
                this.setelUlang({ diam: true });
                this.pulihkan();
            } catch (e) {
                this.galat = e?.response?.data?.message || 'Daftar isi gagal dimuat.';
                this.bab = [];
            } finally {
                this.memuat = false;
            }

            // PRATINJAU PERTAMA DIRENDER SENDIRI.
            //
            // Dulu modal terbuka dengan bidang tengah kosong dan tulisan "tekan
            // Terapkan". Itu meminta admin menebak satu langkah sebelum bisa
            // melihat apa pun — padahal apa yang akan dicetak justru hal
            // pertama yang ingin ia lihat. Tekan "Terapkan" tetap ada, dan
            // dipakai untuk render BERIKUTNYA sesudah pilihan diubah.
            if (!this.galat && this.jumlahTerpilih) this.render();
        },

        /**
         * Pulihkan pengaturan tersimpan di atas bawaan server.
         *
         * DITUMPANGKAN, bukan menggantikan: struktur seksi datang dari server
         * dan bisa berubah sejak terakhir disimpan (formulir baru dikirim,
         * tahap ditambah). Kunci yang tidak lagi ada diabaikan, dan seksi baru
         * memakai bawaan servernya — jadi simpanan lama tidak pernah
         * menyembunyikan bagian yang baru muncul.
         */
        pulihkan() {
            const s = bacaSetelan(this.lamaranId);

            if (!s) return;

            // Urutan bab dipulihkan dengan mengurutkan ulang, bukan menyalin
            // daftar tersimpan: bab yang sudah tidak dikirim server tidak boleh
            // ikut kembali, dan bab baru harus tetap muncul.
            if (Array.isArray(s.bab) && s.bab.length) {
                this.bab.sort((a, c) => {
                    const ia = s.bab.indexOf(a.kode);
                    const ic = s.bab.indexOf(c.kode);

                    return (ia < 0 ? 9999 : ia) - (ic < 0 ? 9999 : ic);
                });

                this.kunciBabTetap();
            }

            this.bab.forEach((b) => {
                b.seksi.forEach((x) => {
                    if (s.pilih && x.kunci in s.pilih && !x.terkunci && !x.wajib) {
                        this.pilih[x.kunci] = s.pilih[x.kunci];
                    }

                    (x.bagian || []).forEach((g) => {
                        if (s.pilih && g.kunci in s.pilih) this.pilih[g.kunci] = s.pilih[g.kunci];
                    });
                });

                // Urutan dipulihkan dengan mengurutkan ulang, bukan menyalin
                // array tersimpan: seksi yang sudah tidak ada tidak boleh
                // ikut kembali, dan yang baru harus tetap muncul di ujung.
                if (s.urutan) {
                    b.seksi.sort((a, c) => (s.urutan[a.kunci] ?? 9999) - (s.urutan[c.kunci] ?? 9999));

                    b.seksi.forEach((x) => {
                        if (x.bagian) {
                            x.bagian.sort((a, c) => (s.urutan[a.kunci] ?? 9999) - (s.urutan[c.kunci] ?? 9999));
                        }
                    });
                }
            });

            Object.assign(this.gaya, s.gaya || {});
            this.bentuk = { ...(s.bentuk || {}) };
            this.lajur = { ...(s.lajur || {}) };
            this.judulUbah = { ...(s.judul || {}) };
            this.jarakAtas = { ...(s.jarak || {}) };

            // Tata letak halaman ikut dipulihkan. Kunci yang halamannya sudah
            // tidak ada tidak disaring DI SINI — daftar halaman baru diketahui
            // sesudah render pertama. muatHalaman() yang membuangnya.
            this.setelHal = { ...(s.tataHal || {}) };
            this.temaPenutup = s.temaPenutup === 'emas' ? 'emas' : 'navy';
        },

        /** Rekam pengaturan sekarang ke localStorage. */
        simpan() {
            const urutan = {};
            let n = 0;

            this.bab.forEach((b) => {
                b.seksi.forEach((s) => {
                    urutan[s.kunci] = n++;

                    (s.bagian || []).forEach((g) => {
                        urutan[g.kunci] = n++;
                    });
                });
            });

            simpanSetelan(this.lamaranId, {
                pilih: { ...this.pilih },
                gaya: { ...this.gaya },
                bentuk: { ...this.bentuk },
                lajur: { ...this.lajur },
                judul: { ...this.judulUbah },
                jarak: { ...this.jarakAtas },
                tataHal: { ...this.setelHal },
                temaPenutup: this.temaPenutup,
                urutan,
                bab: this.bab.map((b) => b.kode),
            });
        },

        /**
         * Kembalikan centang & pengaturan ke bawaan yang dikirim server.
         *
         * `diam` dipakai saat memuat: bawaan dipasang lebih dulu, lalu
         * pulihkan() menumpanginya. Tanpa itu, simpanan yang baru dipulihkan
         * akan langsung tertimpa oleh bawaan yang ikut tersimpan.
         */
        setelUlang({ diam = false } = {}) {
            const pilih = {};
            const gaya = {};
            const lipatField = {};

            this.bab.forEach((b) => {
                b.seksi.forEach((s) => {
                    // Seksi terkunci tidak pernah tercentang — walau server
                    // menandainya terpilih.
                    pilih[s.kunci] = !s.terkunci && (s.wajib || s.terpilih);

                    if (s.berulang) gaya[s.kunci] = s.gaya || 'timeline';

                    (s.bagian || []).forEach((g) => {
                        pilih[g.kunci] = g.terpilih !== false;

                        if (g.berulang) gaya[g.kunci] = g.gaya || 'timeline';

                        (g.field || []).forEach((fl) => {
                            pilih[fl.kunci] = fl.terpilih !== false;
                        });

                        // Daftar isian terlipat bawaannya — lihat catatan pada
                        // `lipatField` di data().
                        lipatField[g.kunci] = true;
                    });
                });
            });

            this.pilih = pilih;
            this.gaya = gaya;
            this.bentuk = {};
            this.lajur = {};
            this.lipatField = lipatField;
            this.judulUbah = {};
            this.jarakAtas = {};
            // Tata letak ikut kembali ke hitungan sistem: "Setel ulang" harus
            // benar-benar mengembalikan SELURUH tampilan, bukan menyisakan
            // halaman yang masih memakai angka lama tanpa jejak di layar.
            this.setelHal = {};
            this.temaPenutup = 'navy';
            this.kotor = true;

            // Ditekan admin (bukan saat memuat) → simpanan ikut dibuang,
            // supaya "Setel ulang" benar-benar mengembalikan ke bawaan dan
            // tidak dipulihkan lagi saat modal dibuka berikutnya.
            if (! diam) {
                hapusSetelan(this.lamaranId);
            }
        },

        togel(s) {
            if (s.terkunci || s.wajib) return;

            const nyala = !this.pilih[s.kunci];

            this.pilih[s.kunci] = nyala;

            // Mematikan sebuah formulir ikut mematikan bagian & isiannya, supaya
            // kunci turunan tidak tertinggal aktif tanpa induknya. Menyalakannya
            // kembali memulihkan seluruhnya.
            (s.bagian || []).forEach((g) => {
                this.pilih[g.kunci] = nyala;

                (g.field || []).forEach((fl) => {
                    this.pilih[fl.kunci] = nyala;
                });
            });

            this.tandaiKotor();
        },

        togelBagian(g) {
            this.pilih[g.kunci] = !this.pilih[g.kunci];
            this.tandaiKotor();
        },

        /** Centang induk: kosongkan bila sudah penuh, penuhi bila belum. */
        togelBab(bab) {
            const penuh = this.statusBab(bab) === 'penuh';

            bab.seksi.forEach((s) => {
                if (s.terkunci || s.wajib) return;

                this.pilih[s.kunci] = !penuh;

                (s.bagian || []).forEach((g) => {
                    this.pilih[g.kunci] = !penuh;

                    (g.field || []).forEach((fl) => {
                        this.pilih[fl.kunci] = !penuh;
                    });
                });
            });

            this.tandaiKotor();
        },

        pilihSeksi(s) {
            this.aktif = s.terkunci ? '' : s.kunci;
        },

        pilihBab(bab) {
            this.aktif = `bab.${bab.kode}`;
        },

        babBisaGeser(bab) {
            return !(bab.kode in BAB_TETAP);
        },

        /**
         * Kembalikan Sampul ke paling atas dan Penutup ke paling bawah.
         *
         * Penjagaan TERAKHIR, dijalankan sesudah urutan dipulihkan dari
         * localStorage. Simpanan bisa berasal dari versi layar sebelum bab
         * dikunci, atau disunting tangan lewat devtools — dan susunan yang
         * salah di sini menghasilkan dokumen resmi yang sampulnya di tengah.
         * Murah dijalankan, jadi dijalankan tanpa syarat.
         */
        kunciBabTetap() {
            const urut = (kode, keUjung) => {
                const i = this.bab.findIndex((b) => b.kode === kode);

                if (i < 0) return;

                const [b] = this.bab.splice(i, 1);

                if (keUjung) this.bab.push(b);
                else this.bab.unshift(b);
            };

            urut('SAMPUL', false);
            urut('PENUTUP', true);
        },

        alasanBabTetap(bab) {
            return BAB_TETAP[bab.kode] || '';
        },

        /**
         * Penjaga drag bab: tolak pindah yang melibatkan bab tetap.
         *
         * Handel gesernya memang tidak dipasang pada Sampul & Penutup, jadi
         * keduanya tidak bisa DIANGKAT — tapi bab lain masih bisa DIJATUHKAN
         * di atas atau di bawahnya, dan itulah yang dicegah di sini: tanpa
         * penjagaan kedua ini, Data Kandidat bisa mendarat sebelum Sampul.
         */
        bolehGeserBab(e) {
            const tujuan = e?.relatedContext?.element;

            if (tujuan && !this.babBisaGeser(tujuan)) return false;

            // Sampul harus tetap paling atas, Penutup tetap paling bawah.
            const i = e?.draggedContext?.futureIndex;

            if (typeof i !== 'number') return true;

            const n = this.bab.length;

            if (this.bab[0] && !this.babBisaGeser(this.bab[0]) && i === 0) return false;
            if (this.bab[n - 1] && !this.babBisaGeser(this.bab[n - 1]) && i >= n - 1) return false;

            return true;
        },

        /** Judul bab — yang ditulis ulang admin bila ada. */
        judulBab(bab) {
            return (this.judulUbah[`bab.${bab.kode}`] || '').trim() || bab.label;
        },

        /**
         * Simpan judul yang diketik di tempat.
         *
         * Kunci yang KOSONG dihapus, bukan disimpan sebagai string kosong.
         * `judulUbah` ikut tersimpan ke localStorage dan dibaca kembali sebagai
         * `atur[...]['judul']`; membiarkan '' menumpuk di sana berarti simpanan
         * yang terus membengkak oleh entri yang tidak berarti apa-apa, dan
         * pemeriksaan "ada judul khusus atau tidak" jadi harus mengurus dua
         * bentuk kosong.
         */
        setJudul(kunci, nilai) {
            if ((nilai || '').trim() === '') delete this.judulUbah[kunci];
            else this.judulUbah[kunci] = nilai;

            this.tandaiKotor();
        },

        lajurSeksi(s) {
            if (!s) return 'penuh';

            return this.lajur[s.kunci] || s.lajur || 'penuh';
        },

        setLajur(s, kode) {
            this.lajur[s.kunci] = kode;
            this.tandaiKotor();
        },

        bentukSeksi(s) {
            return s ? this.bentuk[s.kunci] || 'kisi' : 'kisi';
        },

        setBentuk(s, kode) {
            this.bentuk[s.kunci] = kode;
            this.tandaiKotor();
        },

        setGaya(s, kode) {
            this.gaya[s.kunci] = kode;
            this.tandaiKotor();
        },

        gayaSeksi(s) {
            return s ? this.gaya[s.kunci] || 'timeline' : 'timeline';
        },

        labelGaya(s) {
            const g = GAYA.find((x) => x.kode === this.gayaSeksi(s));

            return g ? g.label : '';
        },

        judulSeksi(s) {
            return (this.judulUbah[s.kunci] || '').trim() || s.label;
        },

        /**
         * Apakah daftar isian sebuah bagian sedang terbentang.
         *
         * Bagian yang BELUM pernah tercatat di `lipatField` dianggap TERLIPAT.
         * Dibaca lewat method, bukan langsung, supaya bagian yang baru datang
         * dari server (formulir yang baru dikirim kandidat) tidak terbentang
         * sendiri hanya karena kuncinya belum ada di peta.
         */
        bukaField(g) {
            return this.lipatField[g.kunci] === false;
        },

        togelLipatField(g) {
            this.lipatField[g.kunci] = this.bukaField(g);
        },

        terpilihDi(bab) {
            return bab.seksi.filter((s) => this.pilih[s.kunci]).length;
        },

        adaYangBisaDipilih(bab) {
            return bab.seksi.some((s) => !s.terkunci && !s.wajib);
        },

        statusBab(bab) {
            const bisa = bab.seksi.filter((s) => !s.terkunci);

            if (!bisa.length) return 'kosong';

            const n = bisa.filter((s) => this.pilih[s.kunci]).length;

            if (n === 0) return 'kosong';

            return n === bisa.length ? 'penuh' : 'sebagian';
        },

        /**
         * Tandai perubahan & rekam pengaturannya.
         *
         * Dipanggil setiap centang, geser, dan penyesuaian. Menulis ke
         * localStorage di sini — bukan saat menekan "Cetak & Unduh" — supaya
         * pengaturannya selamat walau modal ditutup tanpa mencetak, dan tetap
         * ada sesudah cetak selesai.
         */
        tandaiKotor() {
            this.kotor = true;
            this.simpan();
        },

        /**
         * Rakit pratinjau lewat ANTREAN, lalu pantau sampai siap.
         *
         * ── KENAPA TIDAK LANGSUNG SAJA ────────────────────────────────────
         *
         * Dulu permintaan ini merender PDF-nya sendiri dan menunggu balasannya.
         * Di produksi itu berakhir 503: perakitannya makan ~56 detik —
         * sebagian besar untuk mengambil dan merender laporan psikotes dari
         * CAT — sementara Cloud Run memutus permintaan pada 60 detik.
         *
         * Sekarang server hanya menerima permintaannya (di bawah sedetik),
         * mengantrekannya, dan mengembalikan nomor untuk dipantau. Tidak ada
         * lagi batas waktu yang bisa dilanggar, dan bilah kemajuan punya
         * sesuatu yang benar-benar bisa dilaporkan.
         */
        async render() {
            if (!this.jumlahTerpilih) return;

            this.hentikanPantau();
            this.sedangRender = true;
            this.galat = '';
            this.mulaiProgres();

            try {
                const { data } = await axios.post(
                    `/api/v1/karir/lamaran/${this.lamaranId}/berkas-seleksi/pratinjau`,
                    this.muatan,
                    CFG,
                );

                const id = data?.result?.id;

                if (!id) throw new Error('Nomor pratinjau tidak diterima.');

                this.pantauPratinjau(id);
            } catch (e) {
                this.galat = e?.response?.data?.message || 'Pratinjau gagal diminta.';
                this.lepasUrl();
                this.hentikanProgres();
                this.sedangRender = false;
            }
        },

        /**
         * Tanyakan status pratinjau berkala sampai selesai atau gagal.
         *
         * Selang 1,5 detik: cukup rapat supaya pratinjau yang cepat tidak
         * terasa menggantung, cukup jarang supaya perakitan satu menit tidak
         * menghasilkan puluhan permintaan yang tak berguna.
         *
         * Batas 4 menit adalah jaring pengaman, bukan target. Job-nya sendiri
         * beranggaran 900 detik; yang dijaga di sini adalah layar yang
         * ditinggal terbuka semalaman terus-menerus bertanya kepada server.
         */
        pantauPratinjau(id) {
            const mulai = Date.now();

            this.jamPantau = setInterval(async () => {
                if (Date.now() - mulai > 240000) {
                    this.hentikanPantau();
                    this.galat = 'Pratinjau tidak selesai dalam 4 menit. Coba kurangi bagian yang dicentang.';
                    this.hentikanProgres();
                    this.sedangRender = false;

                    return;
                }

                try {
                    const { data } = await axios.get(
                        `/api/v1/karir/lamaran/laporan/${id}`,
                        CFG,
                    );

                    const r = data?.result || {};

                    if (r.gagal) {
                        this.hentikanPantau();
                        this.galat = r.pesan || 'Pratinjau gagal dibuat.';
                        this.lepasUrl();
                        this.hentikanProgres();
                        this.sedangRender = false;

                        return;
                    }

                    if (!r.selesai) return;

                    this.hentikanPantau();
                    // Status sambungan HCLearn dibaca SEBELUM PDF dipasang:
                    // begitu berkasnya tampil, admin langsung membacanya, dan
                    // peringatan yang datang belakangan sudah terlambat.
                    this.periksaHclearn(r.hclearn);
                    this.pasangPratinjau(id);
                } catch (e) {
                    // Satu jawaban yang gagal tidak menghentikan pemantauan:
                    // jaringan sesaat putus jauh lebih sering daripada job yang
                    // benar-benar gagal, dan job-nya sendiri tetap berjalan.
                    // Yang menghentikan hanya status GAGAL atau batas 4 menit.
                    if (e?.response?.status === 404) {
                        this.hentikanPantau();
                        this.galat = 'Permintaan pratinjau tidak ditemukan.';
                        this.hentikanProgres();
                        this.sedangRender = false;
                    }
                }
            }, 1500);
        },

        /**
         * Pasang PDF yang sudah siap ke bingkai.
         *
         * URL-nya menunjuk rute penyaji kita sendiri, bukan blob: berkasnya
         * sudah ada di penyimpanan, dan mengunduhnya ulang ke memori peramban
         * hanya menambah satu perjalanan tanpa memberi apa pun.
         */
        /**
         * Laporkan keadaan sambungan HCLearn sesudah render.
         *
         * Yang dilaporkan HANYA kegagalan sambungan. Peserta yang tesnya
         * memang belum dikerjakan menjawab 404 dan itu keadaan wajar sepanjang
         * masa seleksi — memperingatkannya tiap kali hanya melatih admin
         * mengabaikan peringatan.
         */
        periksaHclearn(h) {
            this.hclearn = h || null;

            if (!h || !h.putus) return;

            this.alert = {
                jenis: 'galat',
                judul: 'Hasil psikotes gagal diambil dari HCLearn',
                pesan:
                    h.jenis === 'jaringan'
                        ? `Sambungan ke HCLearn tidak terjawab (${h.putus} permintaan). Berkas ini tercetak TANPA hasil psikotes — jangan dipakai menilai sebelum sambungannya pulih.`
                        : `HCLearn menolak ${h.putus} permintaan${h.pesan ? ': ' + h.pesan : '.'} Berkas ini tercetak TANPA hasil psikotes.`,
            };
        },

        /** Tutup alert sambungan. */
        tutupAlert() {
            this.alert = null;
        },

        /** Tutup panduan panel, dan ingat pilihannya. */
        tutupPanduan(jangan) {
            this.panduan = false;

            if (jangan) {
                try {
                    localStorage.setItem('xps.panduan', '1');
                } catch (e) {
                    // Mode privat menolak localStorage. Panduannya tetap
                    // tertutup untuk sesi ini; hanya ingatannya yang hilang.
                }
            }
        },

        pasangPratinjau(id) {
            this.lepasUrl();

            // Panel samping & bilah status dimatikan, TAPI TOOLBAR DIBIARKAN
            // HIDUP: zoom, gulir, dan lompat halaman adalah cara admin
            // memeriksa hasilnya sebelum mencetak. Tombol unduh & cetak
            // ditutup lapisan .xps__tutupbar di atas bingkai.
            this.urlPratinjau = `/api/v1/karir/berkas-seleksi/pratinjau/${id}`
                + '#navpanes=0&statusbar=0&view=FitH';

            const pertama = !this.sudahPernah;

            this.kotor = false;
            this.sudahPernah = true;
            this.progres = 100;
            this.hentikanProgres();
            this.sedangRender = false;

            // Panduan panel muncul sesudah pratinjau PERTAMA siap — bukan saat
            // modal dibuka. Sebelum ada yang terlihat di bingkai, penjelasan
            // "panel kiri memilih isi, kanan mengatur tata letak" tidak punya
            // rujukan apa pun di layar dan hanya jadi kotak yang ditutup
            // refleks.
            //
            // Alert sambungan didahulukan: kalau keduanya muncul bersamaan,
            // yang penting justru tertutup yang basa-basi.
            if (pertama && !this.alert) {
                let pernah = false;

                try {
                    pernah = localStorage.getItem('xps.panduan') === '1';
                } catch (e) {
                    // localStorage ditolak (mode privat) — panduannya tampil.
                }

                this.panduan = !pernah;
            }

            // Peta halaman menyusul, TIDAK ditunggu: pratinjau sudah bisa
            // dilihat, dan tab tata letak baru relevan sesudah admin
            // melihat hasilnya.
            this.muatHalaman();
        },

        hentikanPantau() {
            if (this.jamPantau) clearInterval(this.jamPantau);

            this.jamPantau = null;
        },

        // ══ TATA LETAK HALAMAN ════════════════════════════════════════════
        //
        // Bawaannya dihitung server, kendalinya penuh di admin. Yang tampil
        // di slider ketika admin belum menyentuh apa pun adalah ANGKA SISTEM
        // untuk halaman itu — bukan nol, bukan tebakan layar. Begitu ia
        // menggeser, angkanya tercatat di `setelHal` dan menang atas hitungan
        // sistem sampai ia menekan "Kembalikan ke hitungan sistem".

        /**
         * Ambil daftar halaman dokumen dari server.
         *
         * Dipanggil SESUDAH render, bukan sebelum: berapa halaman yang
         * dihasilkan satu formulir bergantung pada bagian mana yang dicentang,
         * dan itu baru pasti setelah perakit memotongnya.
         */
        async muatHalaman() {
            this.memuatHalaman = true;

            try {
                const { data } = await axios.post(
                    `/api/v1/karir/lamaran/${this.lamaranId}/berkas-seleksi/halaman`,
                    this.muatan,
                    CFG,
                );

                this.halaman = data?.result?.halaman || [];

                // Setelan untuk halaman yang sudah tidak ada dibuang: kandidat
                // mengirim formulir baru, potongannya bergeser, dan setelan
                // yatim itu akan terus dikirim ke server tanpa pernah dipakai.
                const ada = new Set(this.halaman.map((h) => h.kunci));

                Object.keys(this.setelHal).forEach((k) => {
                    if (!ada.has(k)) delete this.setelHal[k];
                });
            } catch {
                // Diam saja: tanpa peta halaman, panel kanan kehilangan tab
                // tata letak — pratinjau dan unduhan tetap berjalan. Melempar
                // galat ke layar hanya membuat admin mengira render gagal.
                this.halaman = [];
            } finally {
                this.memuatHalaman = false;
            }
        },

        /** Setelan halaman ini, dibuat bila belum ada. */
        halSetel(h) {
            if (!this.setelHal[h.kunci]) this.setelHal[h.kunci] = {};

            return this.setelHal[h.kunci];
        },

        sudahDisetel(h) {
            const v = this.setelHal[h.kunci];

            return !!v && Object.values(v).some((x) => x != null && x !== '');
        },

        kerapatanHal(h) {
            return this.setelHal[h.kunci]?.kerapatan || h.tata.kerapatan || 'seimbang';
        },

        selaHal(h) {
            const v = this.setelHal[h.kunci]?.sela;

            return v != null ? v : (h.tata.sela ?? 22);
        },

        geserHal(h) {
            const v = this.setelHal[h.kunci]?.geser;

            return v != null ? v : (h.tata.geserSistem ?? 0);
        },

        teksHal(h) {
            const v = this.setelHal[h.kunci]?.teks;

            return v != null ? v : 11;
        },

        setHal(h, kunci, nilai) {
            const kosong = (v) => v === null || v === undefined || v === '' || v === false;

            const s = this.halSetel(h);

            // Memilih kerapatan MELEPAS sela & geser yang pernah diketik: dua
            // hal itu adalah hasil turunan kerapatan, dan membiarkannya berarti
            // admin menekan "Rapat" lalu tidak melihat perubahan apa pun karena
            // angka lamanya masih menang.
            if (kunci === 'kerapatan') {
                delete s.sela;
                delete s.geser;
            }

            // null/false dihapus, bukan disimpan: `sudahDisetel()` menganggap
            // setiap nilai yang ada sebagai "sudah disentuh", jadi `sendiri:
            // false` yang tertinggal membuat halaman tampak disetel manual
            // padahal saklarnya baru saja dimatikan.
            if (kosong(nilai)) delete s[kunci];
            else s[kunci] = nilai;

            this.tandaiKotor();
        },

        resetHal(h) {
            delete this.setelHal[h.kunci];
            this.tandaiKotor();
        },

        /**
         * Pakai satu anggota saja dari kelompok duplikat.
         *
         * Yang lain DIMATIKAN, bukan dihapus: centangnya tetap ada di panel
         * kiri, jadi admin bisa menyalakannya kembali tanpa mencari-cari.
         */
        pakaiSatu(g, indeks) {
            g.anggota.forEach((a, i) => {
                this.pilih[a.kunci] = i === indeks;
            });

            this.tandaiKotor();
        },

        pakaiSemua(g) {
            g.anggota.forEach((a) => {
                this.pilih[a.kunci] = true;
            });

            this.tandaiKotor();
        },

        setTemaPenutup(kode) {
            this.temaPenutup = kode;
            this.tandaiKotor();
        },

        setSemuaKerapatan(kode) {
            this.halaman.forEach((h) => {
                if (h.tata.padat) return;

                this.setHal(h, 'kerapatan', kode);
            });
        },

        /**
         * Rapatkan seluruh isi ke atas sekaligus.
         *
         * Kerapatan "Rapat" hanya memampatkan SELA antar-bagian; isinya tetap
         * mulai di batas bidang (92px dari tepi kertas). Yang dicari admin
         * ketika ia melihat halaman setengah kosong adalah isinya naik merapat
         * ke kop, dan itu butuh geser negatif -- dua langkah yang digabung di
         * sini karena hampir selalu diminta bersamaan.
         */
        naikkanSemua() {
            this.halaman.forEach((h) => {
                if (h.tata.padat) return;

                const s = this.halSetel(h);

                s.kerapatan = 'rapat';
                delete s.sela;
                s.geser = -30;
            });

            this.tandaiKotor();
        },

        jelasKerapatan(h) {
            const k = KERAPATAN.find((x) => x.kode === this.kerapatanHal(h));

            return k ? k.jelas : '';
        },

        /** Ringkasan keadaan halaman untuk baris tertutup. */
        ketHalaman(h) {
            if (h.tata.tetap) return 'Tata letak tetap — komposisi rancangan';
            if (h.tata.padat) return 'Isinya sudah memenuhi halaman';

            const sisa = h.tata.sisa || 0;
            const persen = Math.round((sisa / 947) * 100);

            return `${persen}% ruang kosong · ${h.tata.jumlahBlok || 1} bagian`;
        },

        // ══ KEMAJUAN RENDER ═══════════════════════════════════════════════
        //
        // Merender berkas berisi puluhan halaman butuh beberapa detik, dan
        // selama itu layar sebelumnya hanya diam. Bilah kemajuan ada supaya
        // yang terlihat adalah "sedang berjalan sampai sini", bukan layar
        // yang mungkin sudah menggantung.
        //
        // ── KENAPA TIDAK MURNI DARI SERVER ────────────────────────────────
        // Endpoint pratinjau mengembalikan satu PDF utuh; dompdf tidak
        // melaporkan kemajuan per halaman, jadi tidak ada angka nyata yang
        // bisa diminta sampai byte pertama dikirim. Yang dipakai:
        //   1. sebelum byte datang — perkiraan dari waktu, ditahan di 90%
        //   2. sesudah byte datang — persentase unduhan yang sebenarnya
        // Ditahan di 90% dengan sengaja: bilah yang menyentuh 100% lalu
        // masih menunggu lebih menyesatkan daripada bilah yang jelas belum
        // selesai.

        mulaiProgres() {
            this.hentikanProgres();

            this.progres = 0;
            this.progresMulai = Date.now();

            this.jamProgres = setInterval(() => this.majuProgres(), 120);
        },

        /**
         * Perkiraan sebelum byte pertama tiba.
         *
         * Melambat makin mendekati batas (pendekatan asimptotik): render yang
         * cepat sampai di angka besar dalam sekejap, sementara render yang
         * lama tetap merayap alih-alih mentok diam di satu angka. Enam detik
         * dipilih sebagai patokan karena mendekati render sesungguhnya untuk
         * berkas 20-30 halaman di mesin produksi.
         */
        majuProgres() {
            if (this.progresNyata) return;

            const detik = (Date.now() - this.progresMulai) / 1000;
            const p = 90 * (1 - Math.exp(-detik / 6));

            // Hanya boleh maju. Tanpa penjagaan ini, satu perhitungan yang
            // lebih kecil dari nilai berjalan membuat bilahnya mundur — dan
            // bilah yang mundur terbaca sebagai kesalahan.
            this.progres = Math.max(this.progres, Math.round(p));
        },

        /** Kemajuan sebenarnya, begitu server mulai mengirim PDF-nya. */
        progresUnduh(e) {
            if (!e?.total) return;

            this.progresNyata = true;
            this.progres = Math.max(this.progres, Math.round((e.loaded / e.total) * 100));
        },

        hentikanProgres() {
            if (this.jamProgres) clearInterval(this.jamProgres);

            this.jamProgres = null;
            this.progresNyata = false;
            this.progres = 0;
        },

        async unduh() {
            if (!this.jumlahTerpilih) return;

            this.sedangKirim = true;

            try {
                const { data } = await axios.post(
                    `/api/v1/karir/lamaran/${this.lamaranId}/berkas-seleksi`,
                    this.muatan,
                    CFG,
                );

                // Pemantauan & unduhan diserahkan ke halaman induk, yang sudah
                // punya panel proses dan alur polling untuk ekspor lain.
                this.$emit('unduh', data?.result?.id);
                this.tutup();
            } catch (e) {
                this.galat = e?.response?.data?.message || 'Permintaan cetak gagal dikirim.';
            } finally {
                this.sedangKirim = false;
            }
        },

        /**
         * Kosongkan bingkai pratinjau.
         *
         * Sejak pratinjau dirakit lewat antrean, alamatnya menunjuk rute
         * penyaji kita — bukan blob: PDF-nya sudah ada di penyimpanan, dan
         * menyalinnya ke memori peramban hanya menambah satu perjalanan tanpa
         * memberi apa pun. Jadi tidak ada lagi yang perlu dibebaskan; cukup
         * lepaskan alamatnya supaya bingkainya berhenti menampilkan berkas
         * dari permintaan sebelumnya.
         */
        lepasUrl() {
            this.urlPratinjau = '';
        },

        bersihkan() {
            this.lepasUrl();
            // Pemantau antrean ikut dimatikan: tanpa ini ia terus menanyakan
            // status ke server setiap 1,5 detik sesudah modal ditutup, untuk
            // pratinjau yang tidak akan pernah ditampilkan lagi.
            this.hentikanPantau();
            // Timer kemajuan wajib dimatikan di sini: ia hidup di luar siklus
            // render dan akan terus berdetak sesudah modal ditutup — termasuk
            // saat komponennya sudah dilepas.
            this.hentikanProgres();
            this.galat = '';
            this.kotor = false;
            this.sudahPernah = false;
            this.aktif = '';
            // Peta halaman milik dokumen yang barusan dirender — dibuang
            // bersama pratinjaunya. `setelHal` TIDAK dibuang: ia pengaturan
            // admin yang tersimpan, bukan hasil render.
            this.halaman = [];
            this.halBuka = '';
            this.tab = 'bagian';
            this.duplikat = [];
        },

        tutup() {
            this.$emit('close');
        },
    },
};
</script>

<style scoped>
/* Tiga kolom mengisi tinggi modal. Kiri & kanan tetap, tengah melar. */
.xps {
    display: flex;
    gap: 0;
    height: min(78vh, 920px);
    margin: -18px -20px;
}

/* ── KIRI ─────────────────────────────────────────────────────────────── */
.xps__kiri {
    width: 320px;
    flex: 0 0 320px;
    display: flex;
    flex-direction: column;
    border-right: 1px solid #e6e8f0;
    background: #fbfcfd;
}

.xps__kiritop {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 13px 15px;
    border-bottom: 1px solid #eef1f6;
}

.xps__hint {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 11.5px;
    font-weight: 700;
    color: #475569;
}
.xps__hint i { color: #a37a2c; }

.xps__mini {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 9px;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    background: #fff;
    color: #64748b;
    font-size: 10.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
}
.xps__mini:hover:not(:disabled) { border-color: #cbd5e1; color: #334155; }
.xps__mini:disabled { opacity: .5; cursor: not-allowed; }

.xps__bab { flex: 1; overflow-y: auto; padding: 8px 0 16px; }

.xps__grup { border-bottom: 1px solid #f1f5f9; }

.xps__grupkepala {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 10px 15px;
    cursor: pointer;
    user-select: none;
}
.xps__grupkepala:hover { background: #f6f8fb; }

/* Bab yang sedang dibuka di panel kanan — penanda yang sama dengan
   baris seksi, supaya jelas panel kanan sedang membicarakan bab ini. */
.xps__grupkepala.is-aktif { background: #fdf9ef; box-shadow: inset 3px 0 0 #a37a2c; }
.xps__grupkepala.is-aktif .xps__gruplabel { color: #a37a2c; }

/* Bab yang judulnya tetap tidak berpura-pura bisa disunting. */
.xps__grupkepala.is-mati { cursor: default; }

.xps__grupikon { color: #a37a2c; font-size: 13px; }

/* Handel geser bab. Bab yang posisinya tetap memakai ikon pin diam. */
.xps__gesergrup {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    color: #cbd5e1;
    cursor: grab;
    line-height: 1;
}
.xps__gesergrup:hover { color: #a37a2c; }
.xps__gesergrup:active { cursor: grabbing; }
.xps__gesergrup i { font-size: 13px; }
.xps__gesergrup.is-mati { color: #e2e8f0; cursor: default; }
.xps__gesergrup.is-mati:hover { color: #e2e8f0; }
.xps__gesergrup.is-mati i { font-size: 11px; }

.xps__gruplabel {
    flex: 1;
    font-size: 12px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: .1px;
}

/* JUDUL BAB YANG DISUNTING DI TEMPAT.
   Tanpa bingkai sampai disentuh: baris ini harus tetap terbaca sebagai judul
   bab, bukan sebagai isian formulir. Bingkainya muncul saat ditunjuk atau
   difokus — cukup untuk memberi tahu bahwa ia bisa diketik, tanpa mengubah
   panel kiri jadi deretan kotak input.
   `min-width: 0` wajib: input punya lebar bawaan sendiri yang mengalahkan
   `flex: 1`, dan tanpa ini nomor "2/2" serta chevron terdorong keluar baris. */
.xps__gruplabel--ubah {
    min-width: 0;
    padding: 3px 6px;
    margin: -3px 0;
    border: 1px solid transparent;
    border-radius: 5px;
    background: transparent;
    font-family: inherit;
    transition: border-color .12s, background .12s;
}
.xps__gruplabel--ubah:hover { border-color: #e2e8f0; background: #fff; }
.xps__gruplabel--ubah:focus {
    outline: 0;
    border-color: #a37a2c;
    background: #fff;
    box-shadow: 0 0 0 2px rgba(163, 122, 44, .12);
}
/* Nama bawaan tampil sebagai placeholder — bedanya dengan judul yang benar-
   benar diketik harus terlihat, kalau tidak admin mengira sudah mengubahnya. */
.xps__gruplabel--ubah::placeholder { color: #0f172a; opacity: 1; }
.xps__gruplabel--ubah:focus::placeholder { color: #cbd5e1; }

.xps__grupjml {
    font-size: 10px;
    font-weight: 700;
    color: #94a3b8;
    font-variant-numeric: tabular-nums;
}

/* Melipat bab pindah ke tombolnya sendiri: kepala bab kini membuka
   pengaturan, bukan melipat. */
.xps__lipat {
    flex: 0 0 auto;
    padding: 3px 5px;
    border: none;
    border-radius: 5px;
    background: transparent;
    color: #cbd5e1;
    cursor: pointer;
    line-height: 1;
    transition: all .14s;
}
.xps__lipat:hover { background: #eef2f7; color: #64748b; }
.xps__lipat i { font-size: 11px; }

.xps__isi { padding: 0 0 8px; }

/* BAB TUNGGAL — Sampul, Surat Lamaran, Penutup.
   Tanpa kepala bab, satu-satunya barisnya adalah baris seksi. Ia diberi bobot
   setingkat kepala bab supaya batas dokumen tetap terbaca sebagai penanda,
   bukan tersamar jadi anak dari bab di atasnya. */
.xps__grup.is-tunggal .xps__isi { padding: 0; }
.xps__grup.is-tunggal .xps__label { font-weight: 600; color: #0f172a; }

.xps__baris {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    padding: 8px 13px 8px 8px;
    cursor: pointer;
    border-left: 2px solid transparent;
    transition: background .12s, border-color .12s;
}
.xps__baris:hover { background: #f6f8fb; }
.xps__baris.is-kunci { opacity: .62; cursor: default; }
.xps__baris.is-aktif { background: #fdf9ef; border-left-color: #a37a2c; }

/* Seksi anak (mis. "Ringkasan penilaian") menjorok dan bergaris kiri. */
.xps__baris.is-anak {
    padding-left: 26px;
    padding-top: 5px;
    padding-bottom: 7px;
    margin-left: 20px;
    border-left: 1px solid #e8ecf3;
}
.xps__baris.is-anak.is-aktif { border-left-color: #a37a2c; }
.xps__baris.is-anak .xps__label { font-size: 11px; font-weight: 500; color: #475569; }

/* Pegangan geser — hanya area ini yang menyeret, supaya klik pada baris
   tetap berarti "pilih untuk diatur". */
.xps__geser,
.xps__geserkecil {
    flex: 0 0 auto;
    color: #cbd5e1;
    cursor: grab;
    line-height: 1;
    padding-top: 2px;
    transition: color .12s;
}
.xps__geser:hover,
.xps__geserkecil:hover { color: #94a3b8; }
.xps__geser:active,
.xps__geserkecil:active { cursor: grabbing; }
.xps__geser i { font-size: 13px; }
.xps__geserkecil i { font-size: 11px; }
.xps__geser.is-mati { visibility: hidden; cursor: default; }

.is-hantu { opacity: .38; background: #fdf9ef; }

/* Kotak centang — tri-state lewat kelas is-on / is-half. */
.xps__ck {
    flex: 0 0 auto;
    width: 17px;
    height: 17px;
    margin-top: 1px;
    border: 1.5px solid #cbd5e1;
    border-radius: 5px;
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    transition: all .14s;
}
.xps__ck i { font-size: 10px; color: #fff; opacity: 0; transition: opacity .12s; }
.xps__ck.is-on,
.xps__ck.is-half { background: #a37a2c; border-color: #a37a2c; }
.xps__ck.is-on i,
.xps__ck.is-half i { opacity: 1; }
.xps__ck:disabled { cursor: not-allowed; background: #f1f5f9; border-color: #e2e8f0; }
.xps__ck:disabled.is-on { background: #cbb98a; border-color: #cbb98a; }

.xps__ck--kecil { width: 14px; height: 14px; border-radius: 4px; margin-top: 0; }
.xps__ck--kecil i { font-size: 8px; }

.xps__teks { min-width: 0; flex: 1; }

.xps__label {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
    font-size: 11.5px;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.35;
}

.xps__info { color: #94a3b8; cursor: help; display: inline-flex; }
.xps__info i { font-size: 11px; }

.xps__tag {
    padding: 1px 6px;
    border-radius: 6px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 8.5px;
    font-weight: 800;
    letter-spacing: .4px;
    text-transform: uppercase;
}
.xps__tag--wajib { background: #f1f5f9; color: #64748b; }
.xps__tag--gaya { background: #fdf9ef; color: #a37a2c; }
.xps__tag--berkas { background: #ecfdf5; color: #0d9668; }

/* Pensil = PENANDA "judul bab ini bisa diganti", bukan sasaran klik:
   seluruh kepala bab sudah membuka pengaturannya. */
.xps__pensil {
    flex: 0 0 auto;
    color: #cbd5e1;
    font-size: 10px;
    line-height: 1;
    transition: color .14s;
}
.xps__grupkepala:hover .xps__pensil { color: #94a3b8; }
.xps__pensil.is-aktif { color: #a37a2c; }

.xps__lipatkecil {
    flex: 0 0 auto;
    padding: 1px 4px;
    border: none;
    border-radius: 4px;
    background: transparent;
    color: #cbd5e1;
    cursor: pointer;
    line-height: 1;
}
.xps__lipatkecil:hover { color: #94a3b8; }
.xps__lipatkecil i { font-size: 9px; }

/* Tingkat keempat: isian satu per satu. Dirender DI DALAM blok
   bagiannya, jadi jaraknya diukur dari tepi bagian itu. */
.xps__field {
    margin: 0 0 6px 22px;
    padding-left: 8px;
    border-left: 1px dashed #e8ecf3;
}

.xps__fieldbaris {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 2px 4px;
    border-radius: 4px;
    cursor: pointer;
}
.xps__fieldbaris:hover { background: #f6f8fb; }

.xps__fieldlabel {
    flex: 1;
    font-size: 10px;
    color: #94a3b8;
    line-height: 1.4;
}

.xps__induk { font-size: 10px; color: #a37a2c; font-weight: 600; margin-top: 2px; }
.xps__catatan { font-size: 10px; color: #94a3b8; margin-top: 2px; line-height: 1.4; }
.xps__kuncitxt { font-size: 9.5px; color: #b45309; margin-top: 3px; line-height: 1.45; }

/* Bagian formulir — centang tingkat ketiga. */
.xps__bagian { margin-top: 7px; padding-left: 2px; border-left: 1px solid #e8ecf3; }

.xps__subgrup { border-radius: 5px; }

.xps__subbaris {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    padding: 4px 6px 4px 8px;
    border-radius: 5px;
    cursor: pointer;
}
.xps__subbaris:hover .xps__sublabel { color: #334155; }
.xps__subbaris.is-aktif { background: #fdf9ef; }
/* Bagian yang isiannya sedang terbentang tetap terlihat menaungi
   daftar di bawahnya. */
.xps__subbaris.is-buka { background: #f8fafc; }
.xps__subbaris.is-buka.is-aktif { background: #fdf9ef; }

.xps__subteks { flex: 1; min-width: 0; }

.xps__sublabel {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
    font-size: 10.5px;
    color: #64748b;
    line-height: 1.4;
    transition: color .12s;
}

/* Nomor urut bagian yang judulnya kembar — "Bagian 1" yang muncul
   empat kali di formulir yang sama. */
.xps__nomor {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 15px;
    height: 15px;
    padding: 0 4px;
    border-radius: 4px;
    background: #eef2f7;
    color: #64748b;
    font-size: 9px;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.xps__subbaris.is-aktif .xps__nomor { background: #f3e6c9; color: #a37a2c; }

/* Cuplikan isi — pembeda sesungguhnya antar bagian berjudul sama. */
.xps__cuplikan {
    margin-top: 2px;
    font-size: 9.5px;
    line-height: 1.45;
    color: #b6c0cd;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ── TENGAH ───────────────────────────────────────────────────────────── */
.xps__tengah {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: #f1f5f9;
}

.xps__bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: #fff;
    border-bottom: 1px solid #e6e8f0;
}

.xps__barkiri {
    display: flex;
    align-items: center;
    gap: 7px;
    flex: 1;
    font-size: 11.5px;
    font-weight: 700;
    color: #475569;
}
.xps__barkiri i { color: #a37a2c; }

.xps__barjml {
    padding: 2px 7px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 10px;
    font-weight: 700;
}

.xps__kotor {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 7px;
    background: #fef3c7;
    color: #a37a2c;
    font-size: 10.5px;
    font-weight: 700;
}

.xps__terap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 15px;
    border: none;
    border-radius: 8px;
    background: #0f172a;
    color: #fff;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all .16s;
}
.xps__terap:hover:not(:disabled) { background: #1e293b; }
.xps__terap.is-kotor { background: #a37a2c; }
.xps__terap.is-kotor:hover:not(:disabled) { background: #8f6a24; }
.xps__terap:disabled { opacity: .45; cursor: not-allowed; }

.xps__layar { flex: 1; position: relative; overflow: hidden; }

.xps__frame { width: 100%; height: 100%; border: 0; display: block; background: #fff; }

/* Penutup tombol unduh & cetak viewer peramban. Lebarnya cukup untuk
   menaungi kelompok tombol di sudut kanan toolbar Chrome/Edge; sisanya
   (zoom, gulir, nomor halaman) tetap bisa dipakai.

   WARNANYA MENGIKUTI TOOLBAR VIEWER, bukan latar layar. Toolbar PDF bawaan
   Chrome/Edge berwarna gelap (#323639); lapisan terang di atasnya terbaca
   sebagai kartu abu-abu yang menempel entah dari mana - persis keluhan yang
   membuat baris ini ditulis. Dengan warna yang sama ia lenyap jadi bagian
   toolbar.

   `right: 1px` menahan lapisan tetap DI DALAM bingkai: pada layar ber-DPI
   pecahan (125%/150%) posisi `right: 0` dibulatkan ke luar dan tepinya
   menyembul melewati panel tengah, menimpa panel kanan. */
.xps__tutupbar {
    position: absolute;
    top: 0;
    right: 1px;
    width: 124px;
    height: 44px;
    background: #323639;
    cursor: not-allowed;
}

.xps__tirai {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    /* Nyaris pekat: saat memperbarui, pratinjau LAMA ada di belakang tirai ini.
       Latar setengah tembus membuat halaman lama terbaca samar di balik angka
       kemajuan, dan yang terlihat adalah dua dokumen bertumpuk. */
    background: rgba(248, 250, 252, .97);
    color: #475569;
}

.xps__tiraikotak { width: 300px; max-width: 72%; text-align: center; }

.xps__tirailabel {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    margin-bottom: 10px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
}
.xps__tirailabel i { color: #a37a2c; font-size: 13px; }

/* Angka persen memakai lebar digit tetap: tanpa itu bilahnya bergeser tiap
   kali angkanya berganti dari 9% ke 10%, dan lompatan kecil itu terlihat
   seperti tampilannya berkedip. */
.xps__tiraiangka {
    margin-left: auto;
    font-size: 12px;
    font-weight: 800;
    color: #a37a2c;
    font-variant-numeric: tabular-nums;
}

.xps__bilah {
    height: 6px;
    border-radius: 99px;
    background: #e6ebf2;
    overflow: hidden;
}

.xps__bilahisi {
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg, #c79a3f, #a37a2c);
    /* Perpindahannya dihaluskan supaya lompatan dari perkiraan ke angka
       unduhan sebenarnya tidak terlihat menyentak. */
    transition: width .18s ease-out;
}

.xps__tiraiket { margin: 9px 0 0; font-size: 10px; color: #94a3b8; }

.xps__kosong {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 40px 20px;
    color: #94a3b8;
    text-align: center;
    font-size: 11.5px;
}
.xps__kosong i { font-size: 26px; opacity: .5; }
.xps__kosong--besar { height: 100%; }
.xps__kosong p { margin: 0; line-height: 1.55; }
.xps__galat { color: #b91c1c; font-weight: 600; max-width: 380px; }

/* ── KANAN — PENGATURAN ───────────────────────────────────────────────── */
/* 288px, bukan 264: tab tata letak memuat daftar halaman berisi slider dengan
   angkanya di sebelah kanan, dan pada 264px angka itu terhimpit sampai
   terbaca menempel pada gagang slidernya. */
.xps__setelan {
    width: 288px;
    flex: 0 0 288px;
    display: flex;
    flex-direction: column;
    border-left: 1px solid #e6e8f0;
    background: #fbfcfd;
}

/* ── TAB PANEL KANAN ─────────────────────────────────────────────────────
   "Bagian" mengatur satu seksi, "Tata letak" mengatur satu halaman. Keduanya
   dipisah karena halaman ≠ seksi: satu formulir panjang jadi tiga halaman. */
.xps__tab {
    display: flex;
    gap: 3px;
    padding: 9px 10px 0;
    border-bottom: 1px solid #e6e8f0;
}

.xps__tabbtn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 6px;
    border: 0;
    border-bottom: 2px solid transparent;
    background: transparent;
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    cursor: pointer;
    transition: color .12s, border-color .12s;
}
.xps__tabbtn:hover { color: #475569; }
.xps__tabbtn.is-on { color: #a37a2c; border-bottom-color: #a37a2c; }
.xps__tabbtn i { font-size: 12px; flex: 0 0 auto; }
.xps__tabbtn { min-width: 0; }

/* Lencana jumlah halaman yang sudah disetel manual — supaya admin tahu ada
   penyesuaian yang berlaku tanpa harus membuka tabnya. */
.xps__tabjml {
    flex: 0 0 auto;
    min-width: 15px;
    padding: 1px 4px;
    border-radius: 99px;
    background: #a37a2c;
    color: #fff;
    font-size: 9px;
    font-weight: 800;
    line-height: 1.5;
}

.xps__setel { flex: 1; overflow-y: auto; padding: 4px 15px 20px; }

/* ── DAFTAR HALAMAN ──────────────────────────────────────────────────── */
.xps__hal { border-bottom: 1px solid #eef1f6; }

.xps__halkepala {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 0;
    cursor: pointer;
    color: #94a3b8;
    font-size: 11px;
}
.xps__halkepala:hover { color: #475569; }

/* Nomor halaman — penanda utama, karena judulnya bisa kembar (satu formulir
   panjang menghasilkan beberapa halaman berjudul sama). */
.xps__halno {
    flex: 0 0 auto;
    width: 20px;
    height: 20px;
    border-radius: 5px;
    background: #eef1f6;
    color: #64748b;
    font-size: 10px;
    font-weight: 800;
    line-height: 20px;
    text-align: center;
    font-variant-numeric: tabular-nums;
}
.xps__hal.is-ubah .xps__halno { background: #a37a2c; color: #fff; }

/* Halaman yang berbagi lembar: menjorok sedikit dan bergaris kiri, supaya
   terbaca sebagai lanjutan baris di atasnya — bukan halaman tersendiri. */
.xps__hal.is-sambung { margin-left: 14px; border-left: 1px solid #e8ecf3; padding-left: 8px; }
.xps__halno--sambung { background: transparent; color: #a37a2c; font-size: 12px; }
.xps__hal.is-sambung.is-ubah .xps__halno--sambung { background: transparent; color: #a37a2c; }

/* Saklar kotak-centang di panel pengaturan. */
.xps__saklar {
    display: flex;
    align-items: center;
    gap: 7px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
    color: #334155;
}
.xps__saklar input { width: 13px; height: 13px; accent-color: #a37a2c; cursor: pointer; }

.xps__halteks { flex: 1; min-width: 0; }
.xps__hallabel {
    font-size: 11.5px;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.xps__halket { margin-top: 2px; font-size: 9.5px; color: #a8b3c2; }

.xps__haltanda { color: #a37a2c; font-size: 14px; line-height: 0; }

.xps__halisi { padding: 2px 0 14px; }

/* Kerapatan tampil berjajar, bukan bertumpuk seperti pilihan bentuk daftar:
   ketiganya satu skala berurutan (rapat → penuh), dan menyusunnya vertikal
   menyembunyikan hubungan itu. */
.xps__gaya--baris { flex-direction: row; gap: 4px; }
.xps__gaya--baris .xps__gayabtn { flex: 1; flex-direction: column; gap: 3px; padding: 8px 4px; text-align: center; }
.xps__gaya--baris .xps__gayabtn span { font-size: 10px; }

.xps__mini--lebar { width: 100%; justify-content: center; margin-top: 4px; }

/* Kotak contoh warna pada tombol tema — lebih jelas daripada nama warnanya. */
.xps__tabjml--awas { background: #b45309; }

/* Nama tab dipotong, bukan memaksa tabnya melebar: tiga tab harus muat di
   panel 288px, dan tab yang melar mendorong dua lainnya sampai terpotong
   sembarangan. Nama penuhnya tetap terbaca lewat tooltip. */
.xps__tabteks {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}

/* ── DAFTAR INFORMASI DUPLIKAT ───────────────────────────────────────── */
.xps__dup {
    padding: 12px 0;
    border-bottom: 1px solid #eef1f6;
}
.xps__duplabel { font-size: 11.5px; font-weight: 700; color: #1e293b; }

/* Nilainya ditampilkan supaya admin tahu ini benar-benar jawaban yang sama,
   bukan dua pertanyaan berbeda yang kebetulan mirip namanya. */
.xps__dupnilai {
    margin: 3px 0 8px;
    padding: 4px 8px;
    border-radius: 5px;
    background: #f6f8fb;
    font-size: 10px;
    color: #64748b;
    word-break: break-all;
}

.xps__dupbaris {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    padding: 5px 0;
    cursor: pointer;
}
.xps__dupbaris input { margin-top: 2px; width: 13px; height: 13px; accent-color: #a37a2c; cursor: pointer; }
.xps__dupbaris.is-mati .xps__dupteks { opacity: .45; }

.xps__dupteks { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.xps__dupasal { font-size: 10.5px; font-weight: 600; color: #334155; }
.xps__dupbagian { font-size: 9.5px; color: #a8b3c2; }

.xps__dupaksi { display: flex; gap: 6px; margin-top: 8px; }
.xps__dupaksi .xps__mini { flex: 1; justify-content: center; }

.xps__contohwarna {
    display: block;
    width: 20px;
    height: 20px;
    border-radius: 5px;
    border: 1px solid rgba(15, 23, 42, .14);
}

.xps__setelkepala { padding: 12px 0 14px; border-bottom: 1px solid #eef1f6; }

/* Sebutan tingkat — "Bab" / "Bagian" / "Sub-bagian". Menjawab
   "pengaturan ini berlaku untuk apa" sebelum admin membaca judulnya. */
.xps__setelrole {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 5px;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .7px;
    text-transform: uppercase;
    color: #a37a2c;
}
.xps__setelrole i { font-size: 10px; }

.xps__setellabel {
    font-size: 12.5px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.4;
}

.xps__setelblok { padding: 16px 0 0; }

.xps__setelk {
    display: block;
    font-size: 9.5px;
    font-weight: 800;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 7px;
}

.xps__setelnote { margin: 7px 0 0; font-size: 10px; line-height: 1.55; color: #94a3b8; }

/* `.xps__inp` DIHAPUS bersama kotak judul di panel kanan — judul kini diketik
   langsung pada namanya di panel kiri (lihat .xps__gruplabel--ubah). */

.xps__gaya { display: flex; flex-direction: column; gap: 6px; }

.xps__gayabtn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 11px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all .14s;
}
.xps__gayabtn:hover { border-color: #cbd5e1; color: #334155; }
.xps__gayabtn.is-on {
    border-color: #a37a2c;
    background: #fdf9ef;
    color: #a37a2c;
    font-weight: 700;
}
.xps__gayabtn i { font-size: 13px; }

/* Blok keterangan panel kanan — menerangkan perilaku yang memang sudah
   berlaku, menggantikan saklar yang dulu tidak berefek apa pun. */
.xps__pesan {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-top: 16px;
    padding: 11px 12px;
    border: 1px solid #eef1f6;
    border-radius: 9px;
    background: #f8fafc;
}
.xps__pesan i { flex: 0 0 auto; margin-top: 1px; color: #94a3b8; font-size: 12px; }
.xps__pesan strong {
    display: block;
    font-size: 11px;
    font-weight: 800;
    color: #334155;
    margin-bottom: 3px;
}
.xps__pesan p { margin: 0; font-size: 10px; line-height: 1.6; color: #94a3b8; }

.xps__pesan--info { border-color: #f3e6c9; background: #fdfaf3; }
.xps__pesan--info i { color: #a37a2c; }

.xps__geserbaris { display: flex; align-items: center; gap: 10px; }

.xps__slider { flex: 1; accent-color: #a37a2c; }

.xps__geserangka {
    flex: 0 0 auto;
    font-size: 10.5px;
    font-weight: 700;
    color: #475569;
    font-variant-numeric: tabular-nums;
    min-width: 34px;
    text-align: right;
}

/* ── KAKI ─────────────────────────────────────────────────────────────── */
.xps__kaki {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
}

.xps__kakinote {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 11px;
    color: #94a3b8;
}
.xps__kakinote i { color: #10b981; }

.xps__kakibtn { display: flex; gap: 8px; }

.xps__batal,
.xps__unduh {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all .16s;
}

.xps__batal { border: 1px solid #e2e8f0; background: #fff; color: #64748b; }
.xps__batal:hover { border-color: #cbd5e1; color: #334155; }

.xps__unduh { border: none; background: linear-gradient(135deg, #a37a2c, #d4a93a); color: #fff; }
.xps__unduh:hover:not(:disabled) { filter: brightness(1.06); }
.xps__unduh:disabled { opacity: .45; cursor: not-allowed; }

/* Di layar sempit panel kanan disembunyikan — pratinjau lebih penting.
   Modal ini memang dirancang untuk layar lebar. */
@media (max-width: 1280px) {
    .xps__setelan { display: none; }
}

@media (max-width: 1024px) {
    .xps { flex-direction: column; height: auto; }
    .xps__kiri { width: 100%; flex: none; border-right: 0; border-bottom: 1px solid #e6e8f0; }
    .xps__bab { max-height: 280px; }
    .xps__layar { height: 60vh; }
}
</style>

<!--
    TIDAK scoped: alert & panduan dipindahkan ke <body> lewat <Teleport>, dan
    gaya scoped tidak ikut ke sana — atributnya menempel pada komponen, bukan
    pada simpul yang sudah berpindah induk.

    Nama kelasnya tetap berawalan .xps__ supaya tidak bertabrakan dengan gaya
    global lain di halaman.
-->
<style>
/* ══ ALERT SAMBUNGAN ═══════════════════════════════════════════════════════
   z-index 2147483000: mendekati batas atas signed 32-bit, sengaja di atas
   modal (Bootstrap 1055), tooltip, dan pemuat berkas apa pun. Isinya
   peringatan bahwa berkas tercetak tanpa hasil psikotes — tertutup satu
   lapis saja sudah cukup membuat admin menilai dari dokumen tak lengkap. */
.xps__alert {
    position: fixed;
    inset: 0 0 auto 0;
    z-index: 2147483000;
    display: flex;
    justify-content: center;
    padding: 18px 16px;
    pointer-events: none;
}

.xps__alertkotak {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    width: min(620px, 100%);
    padding: 14px 16px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #fecaca;
    border-left: 4px solid #dc2626;
    box-shadow: 0 12px 32px rgba(15, 23, 42, .18);
    pointer-events: auto;
}

.xps__alertkotak.is-galat i { color: #dc2626; }
.xps__alertkotak i { flex: 0 0 auto; margin-top: 2px; font-size: 15px; }
.xps__alertisi { flex: 1 1 auto; min-width: 0; }
.xps__alertisi strong { display: block; font-size: 12.5px; color: #0f172a; }
.xps__alertisi p { margin: 4px 0 0; font-size: 11.5px; line-height: 1.55; color: #475569; }

.xps__alerttutup {
    flex: 0 0 auto;
    border: 0;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    padding: 2px 4px;
    font-size: 12px;
}

.xps__alerttutup:hover { color: #0f172a; }

/* ══ PANDUAN PANEL ════════════════════════════════════════════════════════ */
.xps__panduan {
    position: fixed;
    inset: 0;
    z-index: 2147482000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, .38);
}

.xps__panduankotak {
    width: min(430px, 100%);
    padding: 22px;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 20px 50px rgba(15, 23, 42, .28);
}

.xps__panduanjudul {
    margin: 0 0 14px;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
}

.xps__panduanbaris { display: flex; gap: 11px; margin-bottom: 13px; }

.xps__panduanno {
    flex: 0 0 auto;
    width: 20px;
    height: 20px;
    border-radius: 6px;
    background: #fdf6e6;
    color: #a37a2c;
    font-size: 10.5px;
    font-weight: 700;
    line-height: 20px;
    text-align: center;
}

.xps__panduanbaris strong { display: block; font-size: 12px; color: #0f172a; }
.xps__panduanbaris p { margin: 3px 0 0; font-size: 11.5px; line-height: 1.55; color: #64748b; }
.xps__panduanbaris em { font-style: normal; font-weight: 600; color: #475569; }

.xps__panduanaksi {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-top: 18px;
}

.xps__panduanlain {
    border: 0;
    background: transparent;
    color: #94a3b8;
    font-size: 11.5px;
    cursor: pointer;
    padding: 6px 2px;
}

.xps__panduanlain:hover { color: #475569; }

.xps__panduanok {
    border: 0;
    border-radius: 9px;
    background: #a37a2c;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    padding: 9px 20px;
    cursor: pointer;
}

.xps__panduanok:hover { background: #8f6a24; }
</style>
