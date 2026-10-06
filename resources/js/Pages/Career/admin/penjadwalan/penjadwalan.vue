<!--
    WEB CAREER — PENJADWALAN TES.

    Tata letak mengikuti template desain `docs/refrences/Template design/
    Penjadwalan Tes.dc.html`: latar berblob, kepala halaman ber-ikon, tab
    kategori di kanan, dua panel (konfigurasi & kandidat), lalu daftar
    penjadwalan berkartu. Shell (sidebar + navbar) datang dari CareerShell,
    jadi di sini hanya isi halamannya.

    Komponen Element Plus disisakan hanya di tempat yang memang memerlukannya —
    pemilih program (butuh pencarian) dan pemilih tanggal — lalu digayakan ulang
    agar menyatu. Sisanya markup sendiri supaya bentuknya persis dan tidak
    bergantung pada gaya bawaan pustaka.
-->
<template>
    <Head title="Penjadwalan Tes" />

    <div class="pjd">
        <span class="pjd-blob pjd-blob--a"></span>
        <span class="pjd-blob pjd-blob--b"></span>

        <div class="pjd-wrap">
            <!-- ═══ KEPALA HALAMAN ═══ -->
            <div class="pjd-head">
                <div class="pjd-head__l">
                    <div class="pjd-head__title">
                        <span class="pjd-head__ico">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /><path d="M9 15l2 2 4-4" /></svg>
                        </span>
                        <h1>Penjadwalan Tes</h1>
                    </div>
                    <p>Susun sesi tes online untuk kandidat — pilih lowongan atau program, paket tes, jendela waktu, lalu kirim token akses secara otomatis.</p>
                </div>

                <div class="pjd-head__r">
                    <!-- SATU PINTU MASUK. Dulu susunan sesi terbentang permanen di
                         puncak halaman: dua panel setinggi layar yang hanya dipakai
                         saat benar-benar membuat jadwal, sementara yang dibuka admin
                         sehari-hari — daftar sesi yang sudah ada — terdorong ke bawah
                         lipatan dan harus digulir dulu setiap kali. -->
                    <button type="button" class="pjd-buat" @click="bukaWizard">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                        Buat Sesi Baru
                    </button>
                </div>
            </div>

            <!-- ═══════════ WIZARD: BUAT SESI PENJADWALAN ═══════════
                 Kulit modal EVO ukuran XL — sama dengan modal peninjauan di
                 Worklist, supaya dua layar yang dipakai bergantian sepanjang hari
                 tidak terasa datang dari dua aplikasi berbeda.

                 TIGA LANGKAH: apa yang diujikan → kapan & siapa → tinjau.
                 Pembagiannya mengikuti urutan ketergantungan yang memang ada:
                 aktivitas menentukan siapa kandidatnya, paket menentukan apa yang
                 dikerjakan. Menyodorkan semuanya sekaligus membuat admin mengisi
                 bagian bawah lebih dulu lalu harus mengulanginya begitu pilihan di
                 atas berubah — dan itulah yang dulu terjadi setiap kali program
                 diganti.

                 Langkah terakhir TINJAU sengaja tidak berisi isian apa pun: satu
                 sesi menerbitkan token untuk puluhan orang sekaligus dan tidak bisa
                 ditarik kembali, jadi harus ada satu layar yang hanya menyatakan
                 "inilah yang akan terjadi" sebelum tombolnya ditekan. -->
            <AdminModal
                :show="wizTampil"
                size="xl"
                icon="bi-calendar-plus-fill"
                title="Buat Sesi Penjadwalan"
                :subtitle="wizSub"
                cancel-label="Tutup"
                foot-note=""
                foot-blok
                :busy="menyimpan"
                busy-label="Membuat sesi…"
                @close="tutupWizard"
            >
                <template #sticky>
                    <div class="pjd-wiz__rail">
                        <button
                            v-for="s in wizSteps" :key="s.no"
                            type="button" class="pjd-wiz__step"
                            :class="{ 'is-on': wizLangkah === s.no, 'is-done': s.no < wizLangkah, 'is-locked': s.no > wizLangkah && !s.bisa }"
                            :disabled="s.no > wizLangkah && !s.bisa"
                            :title="s.no > wizLangkah && !s.bisa ? 'Selesaikan langkah sebelumnya dulu' : s.judul"
                            @click="keLangkah(s.no)"
                        >
                            <span class="pjd-wiz__no">
                                <i v-if="s.no < wizLangkah" class="bi bi-check-lg"></i>
                                <template v-else>{{ s.no }}</template>
                            </span>
                            <span class="pjd-wiz__txt">
                                <b>{{ s.judul }}</b>
                                <em>{{ s.nilai || s.hint }}</em>
                            </span>
                        </button>
                    </div>
                </template>

                <div class="pjd-wiz">
                <!-- ═══ LANGKAH 1 — PROGRAM & AKTIVITAS ═══ -->
                <div v-show="wizLangkah === 1" class="pjd-wiz__pane">
                        <!-- Kategori Talent Acquisition YANG DIIZINKAN untuk pengguna
                             ini — bukan seluruh master. Ada di sini, bukan di kepala
                             halaman: yang disaringnya cuma daftar program di bawah,
                             dan di kepala halaman ia terbaca seolah menyaring daftar
                             sesi. Satu kategori = tak ada yang bisa dipindah, jadi
                             bilahnya tidak perlu ada sama sekali. -->
                        <div v-if="opsi.talent.length > 1">
                            <label class="pjd-lbl">Kategori</label>
                            <div class="pjd-tabs">
                                <button
                                    v-for="t in opsi.talent"
                                    :key="t.kode"
                                    type="button"
                                    class="pjd-tab"
                                    :class="{ 'is-on': kategori === t.kode }"
                                    @click="gantiKategori(t.kode)"
                                >
                                    {{ t.nama }}
                                </button>
                            </div>
                        </div>

                        <!-- ═══ SASARAN SESI: LOWONGAN atau PROGRAM ═══
                             Yang dijadwalkan admin hampir selalu satu lowongan —
                             40 pelamar Operator Produksi, bukan 180 pelamar seisi
                             program. Dengan basis program, 140 nama yang tidak ia
                             maksud tetap ikut tampil di langkah berikutnya, dan
                             "Pilih semua" berubah jadi jebakan.

                             Basis program TIDAK dihapus: satu sesi untuk seisi
                             program tetap sah, dan kadang memang itu yang dimau.
                             Yang berubah cuma bawaannya. -->
                        <div>
                            <label class="pjd-lbl">Sesi Ini Untuk</label>
                            <div class="pjd-basis">
                                <button
                                    type="button" class="pjd-basis__b"
                                    :class="{ 'is-on': basisLoker }"
                                    @click="gantiBasis('LOKER')"
                                >
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" /></svg>
                                    Satu Lowongan
                                </button>
                                <button
                                    type="button" class="pjd-basis__b"
                                    :class="{ 'is-on': !basisLoker }"
                                    @click="gantiBasis('PROGRAM')"
                                >
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z" /></svg>
                                    Seluruh Program
                                </button>
                            </div>
                        </div>

                        <!-- Lowongan (MPP) -->
                        <div v-if="basisLoker">
                            <label class="pjd-lbl">Lowongan</label>
                            <el-select
                                v-model="form.posisiId"
                                filterable
                                placeholder="Pilih lowongan / MPP"
                                class="pjd-select"
                                @change="onLoker"
                            >
                                <el-option
                                    v-for="l in lokerKategori"
                                    :key="l.id"
                                    :label="`${l.posisi} — ${l.programNama}`"
                                    :value="l.id"
                                >
                                    <span class="pjd-lokopt">
                                        <span class="pjd-lokopt__n">{{ l.posisi }}</span>
                                        <span class="pjd-lokopt__m">{{ l.programNama }}<template v-if="l.mppRef"> · {{ l.mppRef }}</template></span>
                                    </span>
                                    <!-- Yang menentukan ada tidaknya pekerjaan di
                                         sini, jadi ia tampil sejak daftar dibuka —
                                         bukan setelah lokernya dipilih. -->
                                    <span class="pjd-opttag" :class="{ 'is-wait': l.menunggu > 0 }">{{ l.menunggu }} menunggu</span>
                                </el-option>
                            </el-select>

                            <div v-if="lokerTerpilih" class="pjd-lokinfo">
                                <span class="pjd-lokinfo__b">
                                    <em>Program</em>
                                    <b>{{ lokerTerpilih.programNama }}</b>
                                </span>
                                <span v-if="lokerTerpilih.mppRef" class="pjd-lokinfo__b">
                                    <em>MPP</em>
                                    <b class="pjd-mono">{{ lokerTerpilih.mppRef }}</b>
                                </span>
                                <span class="pjd-lokinfo__b">
                                    <em>Kuota</em>
                                    <b>{{ lokerTerpilih.terisi }} / {{ lokerTerpilih.kuota || 'tanpa batas' }}</b>
                                </span>
                                <span v-if="lokerTerpilih.lokasi" class="pjd-lokinfo__b">
                                    <em>Lokasi</em>
                                    <b>{{ lokerTerpilih.lokasi }}</b>
                                </span>
                                <span class="pjd-lokinfo__b">
                                    <em>Alur</em>
                                    <b>{{ lokerTerpilih.alurNama || 'belum diatur' }}</b>
                                </span>
                            </div>

                            <div v-if="lokerTerpilih && !lokerTerpilih.alurId" class="pjd-note pjd-note--warn">
                                <i class="bi bi-exclamation-triangle"></i>
                                Program lowongan ini belum terhubung ke alur seleksi. Atur dulu di Program Kegiatan.
                            </div>
                            <div v-else-if="lokerTerpilih && !lokerTerpilih.menunggu" class="pjd-note">
                                <i class="bi bi-info-circle"></i>
                                Tidak ada kandidat lowongan ini yang sedang menunggu jadwal ujian online.
                            </div>
                        </div>

                        <!-- Program -->
                        <div v-else>
                            <label class="pjd-lbl">Program</label>
                            <el-select
                                v-model="form.programId"
                                filterable
                                placeholder="Pilih program"
                                class="pjd-select"
                                @change="onProgram"
                            >
                                <el-option v-for="p in programKategori" :key="p.id" :label="p.nama" :value="p.id">
                                    <span>{{ p.nama }}</span>
                                    <span class="pjd-opttag">{{ p.alurNama || 'alur belum diatur' }}</span>
                                </el-option>
                            </el-select>
                            <div v-if="programTerpilih && !programTerpilih.alurId" class="pjd-note pjd-note--warn">
                                <i class="bi bi-exclamation-triangle"></i>
                                Program ini belum terhubung ke alur seleksi. Atur dulu di Program Kegiatan.
                            </div>
                        </div>

                        <!-- Ujian dari ALUR PROGRAM, bukan daftar jenis tes global. Yang
                             dipilih admin = tahap yang juga dilihat kandidat di portalnya. -->
                        <div>
                            <label class="pjd-lbl">{{ tesAlur.length > 1 ? 'Ujian Mana yang Dijadwalkan' : 'Ujian yang Dijadwalkan' }}</label>

                            <!-- Satu program bisa punya beberapa ujian online di alurnya, dan
                                 masing-masing butuh paket + jendela waktunya sendiri — jadi
                                 pilihan ini tak bisa dihilangkan. Kalau hanya satu, ia dipilih
                                 otomatis dan cukup ditampilkan sebagai keterangan. -->
                            <div v-if="tesAlur.length === 1" class="pjd-note">
                                <i class="bi bi-info-circle"></i>
                                Alur program ini hanya punya satu ujian online — sudah dipilih otomatis.
                            </div>

                            <div v-loading="memuatTes" class="pjd-stages">
                                <div v-if="!memuatTes && !tesAlur.length" class="pjd-empty pjd-empty--sm">
                                    <span class="pjd-empty__ico">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /></svg>
                                    </span>
                                    <p>{{ alasanTes || 'Pilih program terlebih dahulu' }}</p>
                                </div>

                                <button
                                    v-for="t in tesAlur"
                                    :key="kunciTes(t)"
                                    type="button"
                                    class="pjd-stage"
                                    :class="{ 'is-on': tesTerpilihKey === kunciTes(t), 'is-lawas': t.alurLain }"
                                    @click="pilihTes(t)"
                                >
                                    <span class="pjd-stage__no">{{ t.tahapUrutan }}</span>
                                    <span class="pjd-stage__in">
                                        <span class="pjd-stage__nama">{{ t.tahapLabel }}<template v-if="t.multi"> › {{ t.tesLabel }}</template></span>
                                        <span class="pjd-stage__meta">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" /><path d="M9 9h6v6H9z" /></svg>
                                            {{ t.tipeNama || 'Tes Online' }}<template v-if="t.peran === 'INFORMATIF'"> · informatif</template>
                                            <!-- Baris ini tidak ada di alur program sekarang: sisa
                                                 kandidat dari alur sebelumnya. Tanpa keterangan ini
                                                 admin mengira daftarnya salah dan tidak berani menekan. -->
                                            <template v-if="t.alurLain"> · <b>alur lama</b></template>
                                        </span>
                                    </span>
                                    <span class="pjd-stage__badge" :class="{ 'is-wait': t.menunggu > 0 }">{{ t.menunggu }} menunggu</span>
                                </button>
                            </div>
                        </div>

                        <!-- Paket tes ikut LANGKAH 1: "aktivitas apa" dan "soal mana"
                             adalah satu pertanyaan yang sama, dan memisahkannya jadi
                             dua layar membuat admin bolak-balik memastikan paketnya
                             cocok dengan aktivitasnya. -->
                        <div>
                            <label class="pjd-lbl">Nama Ujian / Paket Tes</label>
                            <div class="pjd-field">
                                <svg class="pjd-field__ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                                <input v-model="cariPaket" type="text" class="pjd-input" placeholder="Cari paket tes…" @input="debounceCari">
                            </div>

                            <div v-loading="memuatPaket" class="pjd-pkgs">
                                <div v-if="!memuatPaket && !paket.length" class="pjd-empty pjd-empty--sm pjd-empty--span">
                                    <span class="pjd-empty__ico">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 8h8M8 12h8M8 16h4" /></svg>
                                    </span>
                                    <p>Paket tes tidak ditemukan</p>
                                </div>

                                <button
                                    v-for="p in paket"
                                    :key="p.Id_Master_Ujian"
                                    type="button"
                                    class="pjd-pkg"
                                    :class="{ 'is-on': form.idMasterUjian === p.Id_Master_Ujian }"
                                    @click="pilihPaket(p)"
                                >
                                    <span class="pjd-pkg__head">
                                        <span class="pjd-pkg__nama">{{ p.Nama_Ujian }}</span>
                                        <span v-if="form.idMasterUjian === p.Id_Master_Ujian" class="pjd-pkg__check">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                                        </span>
                                    </span>
                                    <span class="pjd-pkg__kode">{{ p.Kode_Paket }}</span>
                                    <span class="pjd-pkg__chips">
                                        <span v-for="d in p.detail" :key="d.id_paket_detail" class="pjd-ptag">{{ d.nama_indikator }}</span>
                                    </span>
                                    <span class="pjd-pkg__foot">
                                        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 8h8M8 12h8M8 16h4" /></svg>{{ p.Jumlah_Soal }} soal</span>
                                        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h6V10H3zM9 21h6V3H9zM15 21h6v-7h-6z" /></svg>{{ p.Tingkatan }}</span>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- KAMERA — kabar baik, bukan peringatan, karena itulah
                             artinya: pengawasan sudah menyala tanpa perlu disetel.
                             Wajib disebut di sini justru karena tak ada saklarnya
                             di layar ini: tanpa keterangan ini admin mencari-cari
                             setelan yang tidak ada, atau lebih buruk — mengira
                             ujiannya berjalan tanpa pengawasan sama sekali. -->
                        <div class="pjd-ok">
                            <span class="pjd-ok__ico">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 7l-7 5 7 5V7z" /><rect x="1" y="5" width="15" height="14" rx="2" /></svg>
                            </span>
                            <div>
                                <b>Kamera peserta otomatis aktif.</b>
                                Setiap sesi ujian di HCLearn menyalakan kamera pengawas sendiri begitu peserta masuk — tidak ada yang perlu disetel di sini, dan peserta tidak bisa mematikannya.
                            </div>
                        </div>
                </div>

                <!-- ═══ LANGKAH 2 — JADWAL & PESERTA ═══ -->
                <div v-show="wizLangkah === 2" class="pjd-wiz__pane">
                        <!-- Jendela waktu -->
                        <div class="pjd-times">
                            <div>
                                <label class="pjd-lbl">Waktu Mulai</label>
                                <!-- default-time: memilih TANGGAL saja jangan berakhir
                                     di 00:00 — jendela ujian yang mulai & berakhir di
                                     tengah malam praktis mustahil dikerjakan. Mulai
                                     jatuh ke 08.00, berakhir ke 23.59. -->
                                <el-date-picker
                                    v-model="form.waktuMulai"
                                    type="datetime"
                                    placeholder="Tanggal &amp; jam mulai"
                                    format="DD MMM YYYY HH:mm"
                                    value-format="YYYY-MM-DD HH:mm:ss"
                                    :default-time="jamMulaiBawaan"
                                    :disabled-date="(d) => sebelumHari(d, null)"
                                    class="pjd-date"
                                />
                            </div>
                            <div>
                                <label class="pjd-lbl">Waktu Berakhir</label>
                                <!-- disabled-date memadamkan seluruh TANGGAL sebelum
                                     hari mulai, jadi jendela terbalik tidak bisa
                                     dipilih sejak dari kalendernya. Panel JAM tidak
                                     ikut terkunci Element Plus — untuk itu ada
                                     jendelaSalah di bawah, yang menangkap kasus
                                     "hari sama, jam mundur". -->
                                <el-date-picker
                                    v-model="form.waktuAkhir"
                                    type="datetime"
                                    placeholder="Tanggal &amp; jam berakhir"
                                    format="DD MMM YYYY HH:mm"
                                    value-format="YYYY-MM-DD HH:mm:ss"
                                    :default-time="jamAkhirBawaan"
                                    :disabled-date="(d) => sebelumHari(d, form.waktuMulai)"
                                    class="pjd-date"
                                />
                            </div>
                        </div>

                        <!-- Jendela terbalik. Ditahan DI SINI, bukan dibiarkan
                             sampai server: admin sudah mencentang puluhan kandidat
                             saat menekan Generate, dan penolakan di ujung jalan
                             berarti ia mengulang seluruh pemilihan itu. -->
                        <div v-if="jendelaLewat" class="pjd-warn">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                <b>Waktu berakhir sudah lewat.</b>
                                Sesi yang jendelanya sudah tertutup tidak bisa dikerjakan siapa pun — pilih waktu berakhir yang akan datang.
                            </div>
                        </div>
                        <div v-if="jendelaSalah" class="pjd-warn">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                <b>Waktu berakhir tidak boleh sebelum waktu mulai.</b>
                                Sekarang terbaca {{ fmtWaktu(form.waktuMulai) }} → {{ fmtWaktu(form.waktuAkhir) }}.
                                Perbaiki dulu salah satunya.
                            </div>
                        </div>

                        <div class="pjd-lock">
                            <span class="pjd-lock__ico">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                            </span>
                            <div><b>Token + OTP unik</b> akan dikirim ke kandidat terpilih untuk mengakses tes pada jendela waktu ini.</div>
                        </div>

                        <!-- Peserta menyusul di layar yang sama: jendela waktu dan
                             siapa yang mengisinya diputuskan bersamaan — ruangan,
                             gelombang, dan kapasitas satu pertimbangan. -->
                        <div class="pjd-candbar pjd-candbar--gap">
                            <button type="button" class="pjd-all" @click="toggleSemua">
                                <span class="pjd-box" :class="{ 'is-on': semuaTercentang, 'is-half': sebagianTercentang }">
                                    <svg v-if="semuaTercentang" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                                    <i v-else-if="sebagianTercentang"></i>
                                </span>
                                <!-- Kalimatnya menyebut CAKUPANNYA. "Pilih semua"
                                     saat pencarian menyala dulu terbaca sebagai
                                     seluruh kandidat, padahal yang tercentang
                                     hanya hasil pencariannya. -->
                                {{ cariKandidat ? 'Pilih semua hasil' : 'Pilih semua' }}
                            </button>
                            <div class="pjd-field pjd-field--grow">
                                <svg class="pjd-field__ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                                <input v-model="cariKandidat" type="text" class="pjd-input" placeholder="Cari nama / posisi…" @input="debounceKandidat">
                            </div>
                            <span class="pjd-count">{{ form.peserta.length }} / {{ kandTotal || kandidat.length }}</span>
                        </div>

                        <!-- ══ GELOMBANG ══
                             Satu sesi = satu ruangan = jumlah kursi yang tetap.
                             Tanpa baris ini, menyusun 250 orang menjadi gelombang
                             75-an berarti mencentang lewat 13 halaman, dan yang
                             benar-benar terjadi adalah "Pilih semua" lalu 175
                             orang ikut terjadwal ke ruangan yang tak memuat
                             mereka. -->
                        <div v-if="kandidat.length" class="pjd-gel">
                            <span class="pjd-gel__l">Ambil per gelombang</span>
                            <input
                                v-model.number="gelombang"
                                type="number" min="1" :max="kandBatas || 1000"
                                class="pjd-gel__n"
                                @change="simpanGelombang"
                            >
                            <button
                                type="button" class="pjd-gel__b"
                                :disabled="!sisaTampil.length || form.peserta.length >= gelombangSah"
                                :onClick="!sisaTampil.length || form.peserta.length >= gelombangSah ? null : ambilGelombang"
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                                Ambil {{ gelombangSah }}
                            </button>
                            <span v-if="perkiraanGelombang > 1" class="pjd-gel__e">
                                {{ kandTotal || kandidat.length }} menunggu · {{ gelombangSah }} per sesi → <b>{{ perkiraanGelombang }} gelombang</b>
                            </span>
                            <span v-else-if="form.peserta.length >= gelombangSah" class="pjd-gel__e">Kuota gelombang ini sudah penuh.</span>
                        </div>

                        <!-- ══ CENTANG YANG SEDANG TERTUTUP PENCARIAN ══
                             Pencariannya dijalankan server, jadi mengetik satu nama
                             mengganti seluruh isi daftar. Yang sudah tercentang
                             tetap dibawa — tapi kalau tak disebutkan, hitungan
                             "3 / 40" terbaca sebagai kehilangan, dan admin
                             mengulang pencentangan yang sebenarnya masih ada. -->
                        <div v-if="pesertaTersembunyi.length" class="pjd-simpan">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                            <span>
                                <b>{{ pesertaTersembunyi.length }} kandidat</b> yang sudah kamu pilih tidak tampil di hasil sekarang — mereka tetap ikut terjadwal.
                            </span>
                            <button type="button" class="pjd-simpan__x" @click="kosongkanPeserta">Kosongkan semua</button>
                        </div>

                        <!-- ══ DAFTARNYA TERPOTONG ══
                             Satu sesi menampung sebanyak batas peserta. Kalau yang
                             menunggu lebih banyak, itu harus tertulis — bukan
                             berhenti diam-diam di baris terakhir. -->
                        <div v-if="kandTerpotong" class="pjd-warn pjd-warn--info">
                            <i class="bi bi-layers-half"></i>
                            <div>
                                <b>{{ kandTotal }} kandidat menunggu, {{ kandidat.length }} yang bisa masuk satu sesi.</b>
                                Jadwalkan bergelombang — sisanya tetap menunggu di sini dan bisa dibuatkan sesi berikutnya. Menyaring per lowongan biasanya sudah cukup memecahnya.
                            </div>
                        </div>

                        <!-- Kosong itu wajar; yang tidak boleh adalah kosong tanpa sebab.
                             Pesan dari server menjelaskan kenapa.
                             Saat memuat, yang berkedip HANYA daftarnya — menyelimuti
                             seluruh kartu membuat pencarian & "pilih semua" ikut
                             mati padahal keduanya tidak sedang berubah. -->
                        <div class="pjd-cands">
                            <div v-if="memuatKandidat" class="pjd-skel">
                                <span v-for="n in 4" :key="n" class="pjd-skel__row"></span>
                            </div>

                            <div v-else-if="!kandidat.length" class="pjd-empty">
                                <span class="pjd-empty__ico pjd-empty__ico--lg">
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 11l-3 3M19 11l3 3" /></svg>
                                </span>
                                <p>{{ alasanKandidat || 'Tidak ada kandidat' }}</p>
                            </div>

                            <button
                                v-for="k in kandidatHal"
                                v-else
                                :key="k.kode"
                                type="button"
                                class="pjd-cand"
                                :class="{ 'is-on': pesertaSet.has(k.kode) }"
                                @click="toggleKandidat(k.kode)"
                            >
                                <span class="pjd-box" :class="{ 'is-on': pesertaSet.has(k.kode) }">
                                    <svg v-if="pesertaSet.has(k.kode)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                                </span>
                                <span class="pjd-ava" :class="{ 'is-on': pesertaSet.has(k.kode) }">{{ inisial(k.nama) }}</span>
                                <span class="pjd-cand__in">
                                    <span class="pjd-cand__nama">{{ k.nama }}</span>
                                    <span class="pjd-cand__meta">{{ k.posisi || '—' }} · <span class="pjd-mono">{{ k.kode }}</span></span>
                                    <!-- Satu tahap bisa berisi beberapa tes — sebutkan yang mana. -->
                                    <span v-if="k.tes" class="pjd-cand__flow">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3v12" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="6" r="3" /><path d="M18 9c0 6-12 3-12 9" /></svg>
                                        {{ k.tahap }} › {{ k.tes }}
                                    </span>
                                </span>
                            </button>
                        </div>

                        <!-- Paginasi kandidat — 20 per layar supaya masih terpindai
                             mata; centangan disimpan per kode, jadi pindah halaman
                             tidak menghilangkan pilihan di halaman sebelumnya. -->
                        <div v-if="kandidat.length" class="pjd-pager pjd-pager--sm">
                            <span class="pjd-pager__info">
                                {{ rentang(kandPage, kandPerPage, kandidat.length) }} dari {{ kandidat.length }} kandidat<template v-if="form.peserta.length"> · {{ form.peserta.length }} dipilih</template>
                            </span>
                            <div v-if="kandTotalPage > 1" class="pjd-pager__btns">
                                <button type="button" class="pjd-pg" title="Sebelumnya" :disabled="kandPage <= 1" :onClick="kandPage <= 1 ? null : () => kandPage--">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                                </button>
                                <template v-for="(n, i) in nomorHalaman(kandPage, kandTotalPage)" :key="i">
                                    <span v-if="n === '…'" class="pjd-pg pjd-pg--gap">…</span>
                                    <button v-else type="button" class="pjd-pg" :class="{ 'is-on': n === kandPage }" @click="kandPage = n">{{ n }}</button>
                                </template>
                                <button type="button" class="pjd-pg" title="Berikutnya" :disabled="kandPage >= kandTotalPage" @click="kandPage++">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                                </button>
                            </div>
                        </div>
                </div>

                <!-- ═══ LANGKAH 3 — TINJAU ═══
                     Tanpa satu pun isian. Yang dilakukan halaman ini cuma satu:
                     membacakan kembali keputusan yang sudah diambil, dalam kalimat
                     yang sama dengan akibatnya. Sesi ini menerbitkan token untuk
                     puluhan orang dan mengirimkannya — tidak ada tombol batal
                     setelah itu. -->
                <div v-show="wizLangkah === 3" class="pjd-wiz__pane">
                    <div class="pjd-tinjau">
                        <div class="pjd-tinjau__head">
                            <span class="pjd-tinjau__ico">
                                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 5 2 10l10 5 10-5z" /><path d="M6 12v5c3 3 9 3 12 0v-5" /></svg>
                            </span>
                            <div style="min-width: 0; flex: 1">
                                <div class="pjd-tinjau__ttl">{{ form.namaUjian || 'Paket tes belum dipilih' }}</div>
                                <div class="pjd-tinjau__sub">{{ ringkasAktivitas || 'Aktivitas belum dipilih' }}</div>
                            </div>
                            <span class="pjd-tinjau__n">{{ form.peserta.length }} peserta</span>
                        </div>

                        <div class="pjd-tinjau__grid">
                            <!-- Lowongan lebih dulu bila memang itu sasarannya:
                                 dua sesi pada program yang sama dibedakan justru
                                 oleh lokernya, bukan oleh nama programnya. -->
                            <div v-if="lokerTerpilih" class="pjd-tinjau__box">
                                <span class="pjd-tinjau__k">LOWONGAN</span>
                                <span class="pjd-tinjau__v">{{ lokerTerpilih.posisi }}</span>
                                <span class="pjd-tinjau__e">{{ lokerTerpilih.mppRef || 'tanpa MPP' }}</span>
                            </div>
                            <div class="pjd-tinjau__box">
                                <span class="pjd-tinjau__k">PROGRAM</span>
                                <span class="pjd-tinjau__v">{{ programTerpilih ? programTerpilih.nama : '—' }}</span>
                                <span v-if="programTerpilih && programTerpilih.alurNama" class="pjd-tinjau__e">{{ programTerpilih.alurNama }}</span>
                            </div>
                            <div class="pjd-tinjau__box">
                                <span class="pjd-tinjau__k">AKTIVITAS</span>
                                <span class="pjd-tinjau__v">{{ ringkasAktivitas || '—' }}</span>
                                <span v-if="tesTerpilih && tesTerpilih.tipeNama" class="pjd-tinjau__e">{{ tesTerpilih.tipeNama }}</span>
                            </div>
                            <div class="pjd-tinjau__box">
                                <span class="pjd-tinjau__k">JENDELA UJIAN</span>
                                <span class="pjd-tinjau__v">{{ fmtWaktu(form.waktuMulai) || '—' }}</span>
                                <span class="pjd-tinjau__e">s.d. {{ fmtWaktu(form.waktuAkhir) || '—' }}</span>
                            </div>
                            <div class="pjd-tinjau__box">
                                <span class="pjd-tinjau__k">PAKET TES</span>
                                <span class="pjd-tinjau__v">{{ form.namaUjian || '—' }}</span>
                                <span v-if="paketTerpilih" class="pjd-tinjau__e">{{ paketTerpilih.Kode_Paket }} · {{ paketTerpilih.Jumlah_Soal }} soal</span>
                            </div>
                        </div>

                        <!-- SIAPA SAJA. Disebut namanya, bukan cuma dihitung: yang
                             paling sering keliru bukan jumlahnya melainkan satu
                             orang yang ikut tercentang karena namanya mirip. -->
                        <div class="pjd-tinjau__orang">
                            <div class="pjd-tinjau__oh">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></svg>
                                PESERTA TERPILIH
                                <span>{{ form.peserta.length }}</span>
                            </div>
                            <div class="pjd-tinjau__ol">
                                <span v-for="k in pesertaTerpilih" :key="k.kode" class="pjd-tinjau__o" :title="`${k.nama} · ${k.posisi || '—'}`">
                                    <span class="pjd-ava pjd-ava--sm is-on">{{ inisial(k.nama) }}</span>
                                    {{ k.nama }}
                                </span>
                                <span v-if="!pesertaTerpilih.length" class="pjd-tinjau__kosong">Belum ada peserta terpilih.</span>
                            </div>
                        </div>

                        <div class="pjd-ok">
                            <span class="pjd-ok__ico">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 7l-7 5 7 5V7z" /><rect x="1" y="5" width="15" height="14" rx="2" /></svg>
                            </span>
                            <div>
                                <b>Kamera peserta otomatis aktif.</b>
                                Sesi ujian HCLearn menyalakan kamera pengawas sendiri begitu peserta masuk — tidak perlu disetel, dan tidak bisa dimatikan peserta.
                            </div>
                        </div>

                        <div v-if="jendelaSalah" class="pjd-warn">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                <b>Waktu berakhir tidak boleh sebelum waktu mulai.</b>
                                Kembali ke langkah <b>Jendela Waktu</b> untuk memperbaikinya.
                            </div>
                        </div>

                        <div class="pjd-lock">
                            <span class="pjd-lock__ico">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                            </span>
                            <div><b>Token + OTP unik</b> terbit untuk tiap peserta lalu dikirim otomatis. Setelah ditekan, sesi ini tidak bisa ditarik kembali — hanya jadwalnya yang masih bisa digeser per kandidat.</div>
                        </div>
                    </div>
                </div>
                </div>

                <!-- KAKI WIZARD — navigasi langkah. Tombol Generate hanya muncul di
                     langkah terakhir: selama masih di tengah, tombol simpan yang
                     terlihat menggoda ditekan sebelum semuanya terisi. -->
                <template #footer>
                    <div class="pjd-wiz__foot">
                        <button type="button" class="pjd-wiz__back" :disabled="wizLangkah === 1 || menyimpan" :onClick="wizLangkah === 1 || menyimpan ? null : () => keLangkah(wizLangkah - 1)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                            Kembali
                        </button>
                        <span class="pjd-wiz__dots">
                            <i v-for="s in wizSteps" :key="s.no" :class="{ 'is-on': s.no === wizLangkah, 'is-done': s.no < wizLangkah }"></i>
                        </span>
                        <button v-if="wizLangkah < 3" type="button" class="pjd-wiz__next" :disabled="!wizBisaLanjut" :title="wizAlasan" :onClick="!wizBisaLanjut ? null : () => keLangkah(wizLangkah + 1)">
                            Lanjut
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                        </button>
                        <button v-else type="button" class="pjd-gen pjd-gen--foot" :disabled="!bisaGenerate || menyimpan" :onClick="!bisaGenerate || menyimpan ? null : simpan">
                            <svg v-if="!menyimpan" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" /></svg>
                            <i v-else class="bi bi-arrow-repeat pjd-spin"></i>
                            {{ labelGenerate }}
                        </button>
                    </div>
                </template>
            </AdminModal>

            <!-- ═══════════ DAFTAR PENJADWALAN ═══════════
                 Bentuknya mengikuti desain: bilah alat sendiri di atas, lalu satu
                 KARTU PER PROGRAM yang bisa dibuka. Di dalam kartu yang terbuka:
                 bilah tahap seleksi, lalu dua panel — daftar sesi di kiri, rincian
                 sesi terpilih di kanan.

                 Dulu seluruhnya satu panel raksasa: sesi jadi deretan chip di atas
                 tabel peserta, dan memilih gelombang berarti membaca chip sepanjang
                 dua baris tanpa tanggal, tanpa jumlah peserta, tanpa jendela waktu.
                 Panel kiri mengembalikan ketiganya — dikelompokkan per tanggal,
                 karena "sesi hari ini" adalah cara orang benar-benar mencarinya. -->
            <div class="pjd-toolbar">
                <div class="pjd-toolbar__row">
                    <div class="pjd-field pjd-field--grow">
                        <svg class="pjd-field__ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                        <input v-model="filter.q" type="text" class="pjd-input" placeholder="Cari kode, nama ujian, atau program…" @input="debounceFilter">
                    </div>

                    <!-- Chip kategori — disaring DI SERVER (lihat list() di
                         PenjadwalanController). Menyaring hasil satu halaman di
                         layar hanya menyembunyikan sebagian dan menyisakan
                         hitungan halaman yang tak lagi cocok dengan isinya. -->
                    <div v-if="opsi.talent.length > 1" class="pjd-chips">
                        <button
                            type="button" class="pjd-chip" :class="{ 'is-on': !filter.kategori }"
                            @click="pilihKategori('')"
                        >
                            Semua
                        </button>
                        <button
                            v-for="t in opsi.talent" :key="t.kode"
                            type="button" class="pjd-chip" :class="{ 'is-on': filter.kategori === t.kode }"
                            @click="pilihKategori(t.kode)"
                        >
                            {{ t.nama }}
                        </button>
                    </div>

                    <!-- Dua cara membaca daftar sesi yang sama: dua panel (bawaan)
                         atau petak berkas. Petak dipakai saat yang dicari sesinya
                         sendiri di antara puluhan gelombang; dua panel saat yang
                         dicari orangnya di dalam satu gelombang. -->
                    <div class="pjd-views" role="group" aria-label="Tampilan sesi">
                        <button
                            type="button" class="pjd-view" :class="{ 'is-on': tampilan === 'panel' }"
                            title="Tampilan dua panel — daftar sesi & pesertanya"
                            :aria-pressed="tampilan === 'panel'"
                            @click="setTampilan('panel')"
                        >
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
                        </button>
                        <button
                            type="button" class="pjd-view" :class="{ 'is-on': tampilan === 'petak' }"
                            title="Tampilan petak — seluruh sesi sebagai kartu"
                            :aria-pressed="tampilan === 'petak'"
                            @click="setTampilan('petak')"
                        >
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Penyaring lanjutan. Tiap el-select DIBUNGKUS kotak berlebar
                     tetap: tema EVO memaksa `.el-select { width: 100% !important }`
                     supaya isian di dalam modal memenuhi barisnya, dan di bilah
                     alat aturan itu membuat tiap penyaring menuntut satu baris
                     penuh untuk dirinya sendiri. Bungkusnya yang diberi lebar,
                     jadi select tetap memenuhi 100% — 100% dari kotaknya. -->
                <div class="pjd-toolbar__row pjd-toolbar__row--sub">
                    <div class="pjd-fbox">
                        <el-select v-model="filter.programId" clearable filterable placeholder="Semua program" class="pjd-fsel" @change="gantiFilter">
                            <el-option v-for="p in opsi.program" :key="p.id" :label="p.nama" :value="p.id" />
                        </el-select>
                    </div>
                    <div class="pjd-fbox pjd-fbox--sm">
                        <el-select v-model="filter.status" clearable placeholder="Semua status" class="pjd-fsel" @change="gantiFilter">
                            <el-option label="Diantrikan" value="DIANTRIKAN" />
                            <el-option label="Berjalan" value="BERJALAN" />
                            <el-option label="Gagal" value="GAGAL" />
                            <el-option label="Selesai" value="SELESAI" />
                        </el-select>
                    </div>
                    <button v-if="adaFilter" type="button" class="pjd-reset" title="Bersihkan filter" @click="resetFilter">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                        Reset
                    </button>
                    <span class="pjd-toolbar__hasil">{{ daftarTotal }} program</span>
                </div>
            </div>

            <!-- Rangka muat: hanya daftarnya yang berkedip, bilah alat tetap dipakai. -->
            <div v-if="memuat" class="pjd-skel">
                <span v-for="n in 3" :key="n" class="pjd-skel__card"></span>
            </div>

            <div v-else-if="!daftar.length" class="pjd-empty pjd-empty--kartu">
                <span class="pjd-empty__ico pjd-empty__ico--xl">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4M9 15h6" /></svg>
                </span>
                <b>{{ adaFilter ? 'Tidak ada yang cocok' : 'Belum ada penjadwalan' }}</b>
                <p>{{ adaFilter ? 'Ubah kata kunci atau bersihkan filternya.' : 'Tekan Buat Sesi Baru untuk menyusun sesi tes pertama.' }}</p>
            </div>

            <div v-else class="pjd-progs">
                <article
                    v-for="j in daftar"
                    :id="`penjadwalan-${j.id}`"
                    :key="j.id"
                    class="pjd-prog"
                    :class="{ 'is-open': terbuka === j.id, 'is-focus': sorotId === j.id }"
                >
                    <!-- ── KEPALA KARTU PROGRAM ── -->
                    <div class="pjd-prog__head">
                        <button
                            type="button" class="pjd-prog__caret" :class="{ 'is-open': terbuka === j.id }"
                            :title="terbuka === j.id ? 'Tutup' : 'Buka sesi program ini'"
                            :aria-expanded="terbuka === j.id"
                            @click="toggleBaris(j)"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6" /></svg>
                        </button>

                        <div class="pjd-prog__id">
                            <button type="button" class="pjd-prog__ttlbtn" @click="toggleBaris(j)">
                                <span v-if="j.kategori" class="pjd-prog__tag" :class="j.kategori === 'MT' ? 'is-mt' : 'is-rek'">{{ j.kategori }}</span>
                                <span class="pjd-prog__nama">{{ j.program }}</span>
                            </button>
                            <div class="pjd-prog__metas">
                                <span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /></svg>
                                    <b>{{ j.jumlahSesi }}</b> sesi
                                </span>
                                <span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></svg>
                                    <b>{{ j.jumlahPeserta }}</b> peserta
                                </span>
                                <span v-if="(j.tahap || []).length">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3v12" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="6" r="3" /><path d="M18 9c0 6-12 3-12 9" /></svg>
                                    <b>{{ j.tahap.length }}</b> tahap
                                </span>
                                <!-- ALUR YANG DIPAKAI. Satu program bisa dijadwalkan
                                     dengan alur berbeda dari waktu ke waktu — yang lama
                                     untuk angkatan berjalan, yang baru untuk berikutnya.
                                     Saat itu terjadi, kartu ini WAJIB mengatakannya:
                                     rangkaian tahap di dalamnya tidak sama untuk semua
                                     sesi. -->
                                <span v-if="(j.alur || []).length" :class="{ 'is-warn': j.alur.length > 1 }" :title="j.alur.join(' · ')">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
                                    {{ j.alur.length > 1 ? `${j.alur.length} alur berbeda` : j.alur[0] }}
                                </span>
                                <!-- Yang benar-benar perlu ditindaklanjuti: orang yang
                                     tokennya belum terbit. -->
                                <span v-if="menungguSesi(j)" class="is-warn" title="Kandidat yang tokennya belum terbit">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01" /><path d="M10.3 3.8L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0z" /></svg>
                                    <b>{{ menungguSesi(j) }}</b> menunggu token
                                </span>
                            </div>

                            <!-- Catatan sistem: alasan gagal, atau kabar bahwa
                                 tokennya masih diterbitkan di latar belakang. -->
                            <div v-if="j.catatan" class="pjd-prog__note" :class="{ 'is-fail': j.status === 'GAGAL' }">
                                <i class="bi" :class="j.status === 'GAGAL' ? 'bi-exclamation-triangle-fill' : 'bi-hourglass-split'"></i>
                                {{ j.catatan }}
                            </div>
                        </div>

                        <span class="pjd-status" :class="statusKelas(j.status)">
                            <i v-if="j.status === 'BERJALAN' || j.status === 'DIANTRIKAN'" class="pjd-dot"></i>{{ j.status }}
                        </span>
                    </div>

                    <!-- ── ISI KARTU (terbuka) ── -->
                    <div v-if="terbuka === j.id" class="pjd-prog__body">
                        <!-- ALUR SELEKSI — bilah tahap. Konteks yang membuat
                             "Psikotes (Tahap 1)" terbaca sebagai langkah ke-2 dari 7,
                             bukan sekadar nama aktivitas. Menekan satu tahap
                             menyisakan sesi tahap itu saja. -->
                        <div v-if="(j.tahap || []).length" class="pjd-alur">
                            <div class="pjd-alur__head">
                                <span class="pjd-alur__lbl">ALUR SELEKSI</span>
                                <span v-if="(j.alur || []).length" class="pjd-alur__nama">{{ j.alur.join(' · ') }}</span>
                            </div>
                            <div class="pjd-alur__rail">
                                <button
                                    type="button" class="pjd-tahap" :class="{ 'is-on': !tahapPilih }"
                                    @click="pilihTahap(j, '')"
                                >
                                    <span class="pjd-tahap__no">•</span>
                                    <span class="pjd-tahap__in">
                                        <b>Semua Tahap</b>
                                        <em>{{ j.jumlahSesi }} sesi</em>
                                    </span>
                                </button>
                                <button
                                    v-for="t in j.tahap" :key="t.urutan"
                                    type="button" class="pjd-tahap"
                                    :class="[stepKelas(t), { 'is-on': tahapPilih === t.label, 'is-kosong': !sesiTahap(j, t.label).length }]"
                                    :title="t.label"
                                    @click="pilihTahap(j, t.label)"
                                >
                                    <span class="pjd-tahap__no">{{ t.urutan }}</span>
                                    <span class="pjd-tahap__in">
                                        <b>{{ t.label }}</b>
                                        <em>{{ sesiTahap(j, t.label).length ? `${sesiTahap(j, t.label).length} sesi` : 'tanpa sesi' }}</em>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- COBA LAGI melekat pada SESI, bukan program: yang gagal
                             selalu satu gelombang tertentu. Aman diulang — hanya
                             kandidat yang tokennya belum terbit yang dikirim. -->
                        <div v-if="sesiPerluUlang(j).length" class="pjd-ulangbar">
                            <button
                                v-for="s in sesiPerluUlang(j)"
                                :key="`ulang-${s.id}`"
                                type="button" class="pjd-retry" :disabled="ulangId === s.id"
                                title="Antrekan ulang penerbitan token untuk kandidat yang belum berhasil"
                                :onClick="ulangId === s.id ? null : () => ulangJadwal(s)"
                            >
                                <i class="bi" :class="ulangId === s.id ? 'bi-arrow-repeat pjd-spin' : 'bi-arrow-clockwise'"></i>
                                {{ ulangId === s.id ? 'Mengantrekan…' : `Coba Lagi ${s.kode}` }}
                            </button>
                        </div>

                        <!-- ═══ TAMPILAN PETAK ═══ -->
                        <template v-if="tampilan === 'petak'">
                        <div class="pjd-petak">
                            <button
                                v-for="s in sesiHal(j)" :key="s.id"
                                type="button" class="pjd-tile" :class="{ 'is-on': pesFilter.sesi === s.kode }"
                                :title="s.paketUjian || s.nama || s.aktivitas"
                                @click="pilihSesi(j, s)"
                            >
                                <span class="pjd-tile__top">
                                    <span class="pjd-tile__ico">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z" /></svg>
                                    </span>
                                    <span class="pjd-tile__kode">{{ s.kode }}</span>
                                </span>
                                <span class="pjd-tile__nama">{{ s.aktivitas || '—' }}</span>
                                <span class="pjd-tile__meta">
                                    <span>{{ fmtTanggal(s.waktuMulai) || '—' }}</span>
                                    <i></i>
                                    <span>{{ s.jumlahPeserta }} peserta</span>
                                </span>
                                <span v-if="s.menunggu" class="pjd-tile__wait">{{ s.menunggu }} menunggu token</span>
                            </button>
                            <div v-if="!sesiTampil(j).length" class="pjd-empty pjd-empty--sm pjd-empty--span">
                                <p>Tidak ada sesi pada tahap ini.</p>
                            </div>
                        </div>

                        <!-- PAGINASI SESI — milik program ini sendiri.
                             Satu program yang sudah berjalan setahun bisa punya
                             puluhan gelombang; menuangkan semuanya sekaligus
                             membuat kartu program membentang beberapa layar dan
                             gelombang terlama mustahil dijangkau tanpa menggulir
                             seluruhnya. Halamannya per program, bukan global:
                             yang dipenggal di sini isi SATU kartu, sementara
                             paginasi di bawah memenggal daftar programnya. -->
                        <div v-if="sesiTotalPage(j) > 1" class="pjd-pager pjd-pager--sesi">
                            <span class="pjd-pager__info">
                                Sesi {{ rentang(sesiPage, sesiPerPage, sesiTampil(j).length) }} dari {{ sesiTampil(j).length }}
                            </span>
                            <div class="pjd-pager__btns">
                                <button type="button" class="pjd-pg" title="Sesi sebelumnya" :disabled="sesiPage <= 1" :onClick="sesiPage <= 1 ? null : () => gantiHalSesi(j, sesiPage - 1)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                                </button>
                                <template v-for="(n, i) in nomorHalaman(sesiPage, sesiTotalPage(j))" :key="i">
                                    <span v-if="n === '…'" class="pjd-pg pjd-pg--gap">…</span>
                                    <button v-else type="button" class="pjd-pg" :class="{ 'is-on': n === sesiPage }" @click="gantiHalSesi(j, n)">{{ n }}</button>
                                </template>
                                <button type="button" class="pjd-pg" title="Sesi berikutnya" :disabled="sesiPage >= sesiTotalPage(j)" @click="gantiHalSesi(j, sesiPage + 1)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                                </button>
                            </div>
                        </div>
                        </template>

                        <!-- ═══ TAMPILAN DUA PANEL ═══ -->
                        <div v-else class="pjd-split">
                            <!-- KIRI: daftar sesi, dikelompokkan per tanggal -->
                            <aside class="pjd-sesipane">
                                <div class="pjd-sesipane__head">
                                    <div class="pjd-sesipane__top">
                                        <span class="pjd-sesipane__lbl">SESI PENJADWALAN</span>
                                        <span class="pjd-sesipane__n">{{ sesiTampil(j).length }} sesi</span>
                                    </div>
                                    <div class="pjd-field pjd-field--sm">
                                        <svg class="pjd-field__ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                                        <input v-model="sesiCari" type="text" class="pjd-input" placeholder="Cari kode / aktivitas…">
                                    </div>
                                </div>
                                <div class="pjd-sesipane__list">
                                    <div v-for="g in sesiPerTanggal(j)" :key="g.label" class="pjd-sesigrup">
                                        <div class="pjd-sesigrup__head">
                                            <span class="pjd-sesigrup__dot" :class="{ 'is-kini': g.hariIni }"></span>
                                            <span class="pjd-sesigrup__lbl">{{ g.label }}</span>
                                            <span class="pjd-sesigrup__garis"></span>
                                            <span class="pjd-sesigrup__n">{{ g.items.length }} sesi</span>
                                        </div>
                                        <button
                                            v-for="s in g.items" :key="s.id"
                                            type="button" class="pjd-sesirow"
                                            :class="{ 'is-on': pesFilter.sesi === s.kode, 'is-fail': s.status === 'GAGAL' }"
                                            :title="`${s.paketUjian || s.nama || ''} · dibuat ${fmtWaktu(s.createdAt) || '—'} oleh ${s.createdBy || '—'}`"
                                            @click="pilihSesi(j, s)"
                                        >
                                            <span class="pjd-sesirow__rel"></span>
                                            <span class="pjd-sesirow__top">
                                                <span class="pjd-sesirow__kode">{{ s.kode }}</span>
                                                <span class="pjd-sesirow__n">{{ s.jumlahPeserta }}</span>
                                            </span>
                                            <span class="pjd-sesirow__nama">{{ s.aktivitas || '—' }}</span>
                                            <span class="pjd-sesirow__jam">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                                                {{ fmtWaktu(s.waktuMulai) || '—' }}
                                            </span>
                                            <span v-if="s.menunggu" class="pjd-sesirow__wait">{{ s.menunggu }} menunggu</span>
                                        </button>
                                    </div>
                                    <div v-if="!sesiTampil(j).length" class="pjd-sesipane__kosong">Tidak ada sesi yang cocok</div>
                                </div>

                                <!-- Paginasi panel kiri — ringkas, karena lebarnya
                                     cuma sekolom: panah + "2/5". Deretan nomor
                                     lengkap akan membungkus jadi tiga baris di
                                     ruang selebar ini. -->
                                <div v-if="sesiTotalPage(j) > 1" class="pjd-pager pjd-pager--pane">
                                    <span class="pjd-pager__info">{{ rentang(sesiPage, sesiPerPage, sesiTampil(j).length) }}</span>
                                    <div class="pjd-pager__btns">
                                        <button type="button" class="pjd-pg pjd-pg--sm" title="Sesi sebelumnya" :disabled="sesiPage <= 1" :onClick="sesiPage <= 1 ? null : () => gantiHalSesi(j, sesiPage - 1)">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                                        </button>
                                        <span class="pjd-pager__nomor">{{ sesiPage }} / {{ sesiTotalPage(j) }}</span>
                                        <button type="button" class="pjd-pg pjd-pg--sm" title="Sesi berikutnya" :disabled="sesiPage >= sesiTotalPage(j)" @click="gantiHalSesi(j, sesiPage + 1)">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </aside>

                            <!-- KANAN: rincian sesi terpilih + pesertanya -->
                            <section class="pjd-detail">
                                <template v-if="sesiTerpilih(j)">
                                    <div class="pjd-detail__head">
                                        <div class="pjd-detail__top">
                                            <span class="pjd-detail__ico">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 5 2 10l10 5 10-5z" /><path d="M6 12v5c3 3 9 3 12 0v-5" /></svg>
                                            </span>
                                            <div style="flex: 1; min-width: 0">
                                                <div class="pjd-detail__tags">
                                                    <span class="pjd-detail__kode">{{ sesiTerpilih(j).kode }}</span>
                                                    <span class="pjd-status" :class="statusKelas(sesiTerpilih(j).status)">{{ sesiTerpilih(j).status }}</span>
                                                </div>
                                                <div class="pjd-detail__nama">{{ sesiTerpilih(j).aktivitas || '—' }}</div>
                                                <div class="pjd-detail__paket">Paket: {{ sesiTerpilih(j).paketUjian || sesiTerpilih(j).nama || '—' }}</div>
                                            </div>
                                            <button
                                                v-if="sesiTerpilih(j).menunggu"
                                                type="button" class="pjd-detail__ulang" :disabled="ulangId === sesiTerpilih(j).id"
                                                title="Antrekan ulang penerbitan token untuk kandidat yang belum berhasil"
                                                :onClick="ulangId === sesiTerpilih(j).id ? null : () => ulangJadwal(sesiTerpilih(j))"
                                            >
                                                <i class="bi" :class="ulangId === sesiTerpilih(j).id ? 'bi-arrow-repeat pjd-spin' : 'bi-send'"></i>
                                                Kirim Ulang
                                            </button>
                                        </div>

                                        <div class="pjd-stats">
                                            <div class="pjd-stat">
                                                <span class="pjd-stat__ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg></span>
                                                <span class="pjd-stat__in">
                                                    <em>JENDELA UJIAN</em>
                                                    <b>{{ fmtWaktu(sesiTerpilih(j).waktuMulai) || '—' }}</b>
                                                    <i>s.d. {{ fmtWaktu(sesiTerpilih(j).waktuAkhir) || '—' }}</i>
                                                </span>
                                            </div>
                                            <div class="pjd-stat">
                                                <span class="pjd-stat__ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></svg></span>
                                                <span class="pjd-stat__in">
                                                    <em>PESERTA</em>
                                                    <b>{{ sesiTerpilih(j).jumlahPeserta }} kandidat</b>
                                                    <i v-if="sesiTerpilih(j).menunggu">{{ sesiTerpilih(j).menunggu }} menunggu token</i>
                                                </span>
                                            </div>
                                            <div class="pjd-stat">
                                                <span class="pjd-stat__ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5" /></svg></span>
                                                <span class="pjd-stat__in">
                                                    <em>DIJADWALKAN OLEH</em>
                                                    <b>{{ sesiTerpilih(j).createdBy || '—' }}</b>
                                                    <i>{{ fmtWaktu(sesiTerpilih(j).createdAt) || '—' }}</i>
                                                </span>
                                            </div>
                                            <div class="pjd-stat is-gold">
                                                <span class="pjd-stat__ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg></span>
                                                <span class="pjd-stat__in">
                                                    <em>TOKEN + OTP</em>
                                                    <b>Terkirim otomatis</b>
                                                    <i>Kamera pengawas aktif</i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- PENYARING PESERTA. Semuanya terhubung ke server
                                         (lihat muatPeserta): daftar peserta satu program
                                         bisa ribuan baris, dan memotongnya di browser
                                         berarti mengirim seribu baris demi menampilkan
                                         dua puluh. -->
                                    <div class="pjd-ptool">
                                        <div class="pjd-field pjd-field--grow pjd-field--sm">
                                            <svg class="pjd-field__ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                                            <input v-model="pesFilter.q" type="text" class="pjd-input" placeholder="Cari nama, kode, posisi, atau kampus…" @input="debouncePeserta(j)">
                                        </div>
                                        <div class="pjd-fbox pjd-fbox--lg">
                                            <el-date-picker
                                                v-model="pesFilter.tanggal"
                                                type="daterange"
                                                value-format="YYYY-MM-DD"
                                                range-separator="→"
                                                start-placeholder="Dari tanggal"
                                                end-placeholder="Sampai"
                                                class="pjd-fdate"
                                                @change="filterPeserta(j)"
                                            />
                                        </div>
                                        <!-- Kampus dari ISIAN PELAMAR, bukan master:
                                             sebagian mengetik sendiri nama kampusnya, dan
                                             yang diketik itulah yang dipakai merekrut. -->
                                        <div class="pjd-fbox">
                                            <el-select v-model="pesFilter.kampus" clearable filterable placeholder="Semua kampus" class="pjd-fsel" @change="filterPeserta(j)">
                                                <el-option v-for="k in pes.kampusOpsi" :key="k" :label="k" :value="k" />
                                            </el-select>
                                        </div>
                                        <div class="pjd-fbox pjd-fbox--sm">
                                            <el-select v-model="pes.perPage" class="pjd-fsel" @change="gantiPerPage(j)">
                                                <el-option v-for="n in [20, 50, 100]" :key="n" :label="`${n} / halaman`" :value="n" />
                                            </el-select>
                                        </div>
                                        <!-- BUKA SEMUA KREDENSIAL sekaligus. Membacakan
                                             token satu per satu untuk dua puluh orang
                                             berarti dua puluh klik mata-mata; yang
                                             menutupnya kembali tetap satu klik. -->
                                        <button type="button" class="pjd-reveal" :class="{ 'is-on': kredSemua }" @click="toggleKredSemua">
                                            <i class="bi" :class="kredSemua ? 'bi-eye-slash' : 'bi-eye'"></i>
                                            {{ kredSemua ? 'Sembunyikan' : 'Tampilkan' }}
                                        </button>
                                        <button v-if="adaFilterPeserta" type="button" class="pjd-reset" title="Bersihkan penyaring" @click="resetPeserta(j)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                            Reset
                                        </button>
                                    </div>

                                    <div v-if="memuatPeserta" class="pjd-skel pjd-skel--in">
                                        <span v-for="n in 3" :key="n" class="pjd-skel__row"></span>
                                    </div>

                                    <div v-else-if="!pes.rows.length" class="pjd-empty pjd-empty--sm">
                                        <span class="pjd-empty__ico">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></svg>
                                        </span>
                                        <p>{{ adaFilterPeserta ? 'Tidak ada peserta yang cocok dengan penyaring ini.' : 'Belum ada peserta pada sesi ini.' }}</p>
                                    </div>

                                    <template v-else>
                                        <!-- Tabel layar lebar. Kolom Pengerjaan & Nilai
                                             sengaja tidak ada: keduanya milik Worklist, dan
                                             di layar penjadwalan yang dicari admin adalah
                                             kredensial masuk ujian — token & OTP. -->
                                        <div class="pjd-tbl">
                                            <div class="pjd-tbl__head">
                                                <span>KANDIDAT</span><span>KAMPUS</span><span class="c">TOKEN</span><span class="c">OTP</span><span class="c">JENDELA UJIAN</span><span>DIJADWALKAN OLEH</span><span class="r">AKSI</span>
                                            </div>
                                            <div v-for="row in pes.rows" :key="row.id" class="pjd-tbl__row">
                                                <span class="pjd-tbl__nama">
                                                    <span class="pjd-ava pjd-ava--sm is-on">{{ inisial(row.nama) }}</span>
                                                    <span class="pjd-tbl__who">
                                                        <b>{{ row.nama }}</b>
                                                        <em>{{ row.posisi || '—' }}</em>
                                                    </span>
                                                </span>
                                                <span class="pjd-tbl__kampus" :title="row.kampus || 'Belum mengisi kampus di formulir'">
                                                    <span v-if="row.kampus">{{ row.kampus }}</span>
                                                    <span v-else class="pjd-tbl__sub">—</span>
                                                </span>
                                                <!-- KREDENSIAL DISAMARKAN. Token & OTP adalah
                                                     kunci masuk ujian: siapa pun yang melihat
                                                     layar — atau screenshot-nya — bisa
                                                     mengerjakan tes atas nama kandidat. -->
                                                <span class="c">
                                                    <span v-if="row.shortToken" class="pjd-credwrap">
                                                        <button type="button" class="pjd-cred" title="Salin token akses" @click="salin(row.shortToken, 'Token')">
                                                            <span class="pjd-mono">{{ kredTampak(row.id, 'tok') ? row.shortToken : '••••••••' }}</span>
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h8" /></svg>
                                                        </button>
                                                        <button type="button" class="pjd-eye" :title="kredTampak(row.id, 'tok') ? 'Sembunyikan token' : 'Tampilkan token'" @click="toggleKred(row.id, 'tok')">
                                                            <svg v-if="kredTampak(row.id, 'tok')" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18" /><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" /><path d="M9.4 5.2A9.5 9.5 0 0 1 12 5c5 0 9 4.5 9 7a11 11 0 0 1-2.2 3.1M6.2 6.2A11.6 11.6 0 0 0 3 12c0 2.5 4 7 9 7a9.7 9.7 0 0 0 3.3-.6" /></svg>
                                                            <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="2.6" /></svg>
                                                        </button>
                                                    </span>
                                                    <span v-else class="pjd-tbl__sub">{{ row.pesanError ? 'gagal' : '—' }}</span>
                                                </span>
                                                <span class="c">
                                                    <span v-if="row.otp" class="pjd-credwrap">
                                                        <button type="button" class="pjd-cred pjd-cred--otp" title="Salin kode OTP" @click="salin(row.otp, 'OTP')">
                                                            <span class="pjd-mono">{{ kredTampak(row.id, 'otp') ? row.otp : '••••••••' }}</span>
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h8" /></svg>
                                                        </button>
                                                        <button type="button" class="pjd-eye" :title="kredTampak(row.id, 'otp') ? 'Sembunyikan OTP' : 'Tampilkan OTP'" @click="toggleKred(row.id, 'otp')">
                                                            <svg v-if="kredTampak(row.id, 'otp')" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18" /><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" /><path d="M9.4 5.2A9.5 9.5 0 0 1 12 5c5 0 9 4.5 9 7a11 11 0 0 1-2.2 3.1M6.2 6.2A11.6 11.6 0 0 0 3 12c0 2.5 4 7 9 7a9.7 9.7 0 0 0 3.3-.6" /></svg>
                                                            <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="2.6" /></svg>
                                                        </button>
                                                    </span>
                                                    <span v-else class="pjd-tbl__sub">—</span>
                                                </span>
                                                <span class="c pjd-tbl__win">
                                                    <span>{{ fmtWaktu(row.waktuMulai) || '—' }}</span>
                                                    <span class="pjd-tbl__win2">s.d. {{ fmtWaktu(row.waktuAkhir) || '—' }}</span>
                                                    <b class="pjd-tbl__sesi">{{ row.sesi }}<template v-if="row.aktivitas"> · {{ row.aktivitas }}</template></b>
                                                    <em v-if="row.jadwalSendiri" title="Jadwal khusus kandidat ini, tidak mengikuti jendela bawaan">jadwal sendiri</em>
                                                </span>
                                                <span class="pjd-by" :title="labelOleh(row)">
                                                    <span class="pjd-by__ava">{{ inisial(row.ubahNama || row.olehNama) }}</span>
                                                    <span class="pjd-by__in">
                                                        <b>{{ row.ubahNama || row.olehNama || '—' }}</b>
                                                        <i>{{ fmtWaktu(row.ubahPada || row.olehPada) || '—' }}</i>
                                                        <u v-if="row.ubahNama">digeser ulang</u>
                                                    </span>
                                                </span>
                                                <span class="r">
                                                    <!-- TAUTAN UJIAN MELEKAT PADA ORANGNYA.
                                                         Dulu tombol ini satu-satunya, berdiri
                                                         di kaki tabel bertuliskan "Salin tautan
                                                         ujian" — dan yang tersalin adalah tautan
                                                         milik baris PERTAMA yang kebetulan
                                                         punya. Tautannya bukan alamat umum: di
                                                         dalamnya menempel `?wo_aut=<token>&otp=`
                                                         milik satu kandidat. Menyerahkannya ke
                                                         orang lain sama dengan menyerahkan kunci
                                                         masuk ujian atas nama kandidat itu. -->
                                                    <button
                                                        v-if="row.linkUjian"
                                                        type="button" class="pjd-ibtn pjd-ibtn--link"
                                                        :title="`Salin tautan ujian ${row.nama} — berisi token & OTP miliknya sendiri`"
                                                        @click="salin(row.linkUjian, `Tautan ujian ${row.nama}`)"
                                                    >
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1" /><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1" /></svg>
                                                    </button>
                                                    <button
                                                        type="button" class="pjd-ibtn" :disabled="row.terkunci || !row.dapatDiubah"
                                                        :title="row.terkunci ? 'Ujian sudah dikerjakan — jadwal terkunci'
                                                            : (!row.dapatDiubah ? 'Sesi ini dibuat sebelum penautan ke HCLearn ada — buat ulang penjadwalannya agar bisa diubah' : 'Ubah jadwal kandidat ini')"
                                                        :onClick="row.terkunci || !row.dapatDiubah ? null : () => bukaEdit(row, j)"
                                                    >
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
                                                    </button>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Kartu layar sempit -->
                                        <div class="pjd-rows">
                                            <div v-for="row in pes.rows" :key="row.id" class="pjd-row">
                                                <div class="pjd-row__top">
                                                    <span class="pjd-ava pjd-ava--sm is-on">{{ inisial(row.nama) }}</span>
                                                    <div class="pjd-row__in">
                                                        <div class="pjd-row__nama">{{ row.nama }}</div>
                                                        <div class="pjd-row__pos">{{ row.posisi || '—' }}</div>
                                                        <div class="pjd-row__pos">{{ row.kampus || 'Kampus belum diisi' }}</div>
                                                        <div class="pjd-row__pos">{{ row.sesi }} · {{ fmtWaktu(row.waktuMulai) || '—' }}<template v-if="row.jadwalSendiri"> · digeser</template></div>
                                                        <div class="pjd-row__pos">oleh {{ row.ubahNama || row.olehNama || '—' }}</div>
                                                    </div>
                                                    <button
                                                        type="button" class="pjd-ibtn" :disabled="row.terkunci || !row.dapatDiubah"
                                                        :title="row.terkunci ? 'Ujian sudah dikerjakan — jadwal terkunci'
                                                            : (!row.dapatDiubah ? 'Sesi ini dibuat sebelum penautan ke HCLearn ada — buat ulang penjadwalannya agar bisa diubah' : 'Ubah jadwal kandidat ini')"
                                                        :onClick="row.terkunci || !row.dapatDiubah ? null : () => bukaEdit(row, j)"
                                                    >
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
                                                    </button>
                                                </div>
                                                <div class="pjd-row__bot">
                                                    <button v-if="row.shortToken" type="button" class="pjd-cred" @click="salin(row.shortToken, 'Token')">
                                                        <b>TOKEN</b><span class="pjd-mono">{{ kredTampak(row.id, 'tok') ? row.shortToken : '••••••••' }}</span>
                                                    </button>
                                                    <button v-if="row.otp" type="button" class="pjd-cred pjd-cred--otp" @click="salin(row.otp, 'OTP')">
                                                        <b>OTP</b><span class="pjd-mono">{{ kredTampak(row.id, 'otp') ? row.otp : '••••••••' }}</span>
                                                    </button>
                                                    <button
                                                        v-if="row.linkUjian" type="button" class="pjd-cred pjd-cred--link"
                                                        :title="`Salin tautan ujian ${row.nama}`"
                                                        @click="salin(row.linkUjian, `Tautan ujian ${row.nama}`)"
                                                    >
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1" /><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1" /></svg>
                                                        TAUTAN
                                                    </button>
                                                    <span v-if="!row.shortToken && !row.otp" class="pjd-tbl__sub">{{ row.pesanError || 'Kredensial belum terbit' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="pjd-pager pjd-pager--in">
                                            <span class="pjd-pager__info">
                                                {{ rentang(pes.page, pes.perPage, pes.total) }} dari {{ pes.total }} peserta
                                            </span>
                                            <div v-if="pes.totalPage > 1" class="pjd-pager__btns">
                                                <button type="button" class="pjd-pg" title="Sebelumnya" :disabled="pes.page <= 1" :onClick="pes.page <= 1 ? null : () => gantiHalPeserta(j, pes.page - 1)">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                                                </button>
                                                <template v-for="(n, i) in nomorHalaman(pes.page, pes.totalPage)" :key="i">
                                                    <span v-if="n === '…'" class="pjd-pg pjd-pg--gap">…</span>
                                                    <button v-else type="button" class="pjd-pg" :class="{ 'is-on': n === pes.page }" @click="gantiHalPeserta(j, n)">{{ n }}</button>
                                                </template>
                                                <button type="button" class="pjd-pg" title="Berikutnya" :disabled="pes.page >= pes.totalPage" @click="gantiHalPeserta(j, pes.page + 1)">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </template>

                                <!-- Belum ada sesi yang dipilih. Panel kanan kosong tanpa
                                     keterangan terbaca seperti data yang gagal dimuat. -->
                                <div v-else class="pjd-detail__pilih">
                                    <span class="pjd-detail__pilihico">
                                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /></svg>
                                    </span>
                                    <b>Pilih sesi penjadwalan</b>
                                    <span>Klik salah satu sesi di panel kiri untuk melihat peserta, token, dan jendela ujiannya.</span>
                                </div>
                            </section>
                        </div>
                    </div>
                </article>

                <!-- Paginasi DAFTAR PROGRAM. Muncul hanya saat memang ada halaman
                     berikutnya: satu bilah berisi "1–2 dari 2" tanpa satu pun
                     tombol tidak menyampaikan apa pun yang belum tertulis di
                     bilah alat, dan terbaca seperti paginasi yang rusak.
                     Yang dipenggal di sini PROGRAM — sesi di dalam tiap kartu
                     punya paginasinya sendiri. -->
                <div v-if="daftarTotalPage > 1" class="pjd-pager pjd-pager--luar">
                    <span class="pjd-pager__info">
                        Menampilkan {{ rentang(daftarPage, daftarPerPage, daftarTotal) }} dari {{ daftarTotal }} program
                    </span>
                    <div class="pjd-pager__btns">
                        <button type="button" class="pjd-pg" title="Sebelumnya" :disabled="daftarPage <= 1" :onClick="daftarPage <= 1 ? null : () => gantiHalaman(daftarPage - 1)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                        </button>
                        <template v-for="(n, i) in nomorHalaman(daftarPage, daftarTotalPage)" :key="i">
                            <span v-if="n === '…'" class="pjd-pg pjd-pg--gap">…</span>
                            <button v-else type="button" class="pjd-pg" :class="{ 'is-on': n === daftarPage }" @click="gantiHalaman(n)">{{ n }}</button>
                        </template>
                        <button type="button" class="pjd-pg" title="Berikutnya" :disabled="daftarPage >= daftarTotalPage" @click="gantiHalaman(daftarPage + 1)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>


        <!-- Edit jendela waktu tes -->
        <!-- Ubah jadwal SATU kandidat. Memakai AdminModal — kulit modal yang
             sama dengan seluruh halaman admin lain, supaya tidak ada modal yang
             berbeda sendiri. -->
        <AdminModal
            :show="editTampil"
            title="Ubah Jadwal Kandidat"
            :subtitle="editTarget ? editTarget.nama : ''"
            icon="bi-calendar-event"
            save-label="Simpan Jadwal"
            :busy="editSibuk"
            :save-disabled="editSalah"
            foot-note="Jendela ujian & token kandidat ini ikut diperbarui."
            @close="editTampil = false"
            @save="simpanEdit"
        >
            <div v-if="editTarget" class="pjd-edit">
                <div class="pjd-edit__who">
                    <span class="pjd-ava is-on">{{ inisial(editTarget.nama) }}</span>
                    <div>
                        <b>{{ editTarget.nama }}</b>
                        <span>{{ editTarget.posisi || '—' }}<template v-if="editTarget.paketUjian || editTarget.aktivitas"> · {{ editTarget.paketUjian || editTarget.aktivitas }}</template></span>
                    </div>
                </div>

                <p class="pjd-edit__note">
                    <i class="bi bi-info-circle"></i>
                    Yang berubah <b>hanya jadwal kandidat ini</b> — peserta lain di penjadwalan yang sama tidak ikut bergeser. Token ujiannya di HCLearn diperbarui otomatis.
                </p>

                <label class="pjd-lbl">Waktu Mulai</label>
                <el-date-picker v-model="editMulai" type="datetime" placeholder="Tanggal &amp; jam mulai" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" :default-time="jamMulaiBawaan" :disabled-date="(d) => sebelumHari(d, null)" class="pjd-date" />
                <label class="pjd-lbl pjd-lbl--gap">Waktu Berakhir</label>
                <el-date-picker v-model="editAkhir" type="datetime" placeholder="Tanggal &amp; jam berakhir" format="DD MMM YYYY HH:mm" value-format="YYYY-MM-DD HH:mm:ss" :default-time="jamAkhirBawaan" :disabled-date="(d) => sebelumHari(d, editMulai)" class="pjd-date" />

                <div v-if="editLewat" class="pjd-warn pjd-warn--gap">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div><b>Waktu berakhir sudah lewat.</b> Pilih waktu berakhir yang akan datang.</div>
                </div>
                <div v-if="editSalah" class="pjd-warn pjd-warn--gap">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div><b>Waktu berakhir tidak boleh sebelum waktu mulai.</b> Perbaiki dulu sebelum menyimpan.</div>
                </div>
            </div>
        </AdminModal>

        <!-- PANEL ANTREAN — pojok kanan-bawah, bentuk panel unggahan Drive.
             Menekan Generate untuk 500 kandidat menyerahkan pekerjaannya ke
             Cloud Tasks lalu mengembalikan admin ke daftar yang tampak tidak
             berubah; panel inilah yang menunjukkan bahwa ia sedang berjalan,
             sampai di mana, dan siapa yang gagal.

             `ids` berisi gelombang yang BARU dibuat di tab ini supaya panelnya
             muncul seketika — tanpa itu ia baru menyusul pada denyut berikutnya
             dan tombol Generate terasa tidak melakukan apa-apa. -->
        <PanelAntrean :ids="antreanIds" @selesai="onAntreanSelesai" />

        <!-- Toast — bentuk pemberitahuan yang sama dengan halaman admin lain,
             menggantikan spanduk alert yang dulu mendorong isi halaman turun. -->
        <transition name="pjd-toast">
            <div v-if="notice" class="pjd-toast" :class="{ 'is-err': noticeType === 'error' }">
                <i class="bi" :class="noticeType === 'error' ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i>
                {{ notice }}
            </div>
        </transition>
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AdminModal from '@career/AdminModal.vue';
import PanelAntrean from '@career/PanelAntrean.vue';
import { ingatModal } from '@utils/ingatModal';

export default {
    name: 'Penjadwalan',
    components: { AdminModal, Head, PanelAntrean },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/penjadwalan/penjadwalan')],
    data() {
        return {
            memuat: false,
            memuatPaket: false,
            memuatKandidat: false,
            memuatTes: false,
            menyimpan: false,
            kategori: '',
            opsi: { talent: [], program: [], loker: [] },
            paket: [],
            /**
             * SESI DISUSUN ATAS APA — lowongan (bawaan) atau program.
             *
             * Yang dijadwalkan admin hampir selalu satu lowongan: 40 pelamar
             * Operator Produksi, bukan 180 pelamar seisi program. Dengan basis
             * program, 140 nama yang tidak ia maksud tetap ikut tampil dan
             * "Pilih semua" menjadi jebakan.
             *
             * Basis PROGRAM tidak dihapus — satu sesi untuk seisi program tetap
             * sah dan kadang memang itu yang diinginkan. Pilihannya diingat per
             * peramban, sama seperti tata letak daftar sesi.
             */
            basis: localStorage.getItem('pjd.basis') === 'PROGRAM' ? 'PROGRAM' : 'LOKER',
            // Tes/tahap yang bisa dijadwalkan pada alur program terpilih.
            tesAlur: [],
            alasanTes: '',
            kandidat: [],
            /**
             * Kandidat terpilih yang SEDANG TIDAK tampil di daftar.
             *
             * Pencarian kandidat dijalankan di server: mengetik satu nama
             * mengganti seluruh isi `kandidat`. Dulu pilihan ikut dipangkas ke
             * hasil pencarian itu — admin yang sudah mencentang 400 orang lalu
             * mencari satu nama kehilangan keempat ratusnya, tanpa satu pun
             * pesan. Sejak sekarang yang tercentang disimpan di sini, dan
             * hanya dilepas ketika RUANGNYA berganti (lowongan/program/tahap).
             */
            pesertaInfo: {},
            /**
             * UKURAN SATU GELOMBANG.
             *
             * 250 orang menunggu, ruang tesnya memuat 75. Tanpa kendali ini
             * pilihannya cuma dua: mencentang 75 nama satu per satu lewat 13
             * halaman, atau "Pilih semua" lalu melepas 175 — dan yang kedua
             * itulah yang biasanya terjadi, sampai ada yang sadar ruangannya
             * tidak cukup.
             *
             * Diingat per peramban: ukuran gelombang adalah sifat ruangan dan
             * kebiasaan tim, bukan sifat satu sesi.
             */
            gelombang: Number(localStorage.getItem('pjd.gelombang')) || 75,
            // Berapa yang benar-benar menunggu di server (bisa lebih banyak
            // daripada yang muat dikirim — lihat kandTerpotong).
            kandTotal: 0,
            kandBatas: 0,
            kandTerpotong: false,
            // Penjelasan dari server saat daftar kandidat kosong.
            alasanKandidat: '',
            // Halaman daftar kandidat (dipotong di klien; servernya sudah
            // membatasi 500 baris, dan 20 per layar cukup untuk dipindai mata).
            kandPage: 1,
            kandPerPage: 20,

            // ── Daftar penjadwalan: disaring & dipaginasi di server ──
            daftar: [],
            daftarTotal: 0,
            daftarPage: 1,
            daftarPerPage: 10,
            daftarTotalPage: 1,
            filter: { q: '', programId: null, status: '', kategori: '' },
            // Dua cara membaca daftar sesi: 'panel' (kiri-kanan, bawaan) atau
            // 'petak' (kartu berjajar). Diingat per peramban.
            tampilan: localStorage.getItem('pjd.tampilan') === 'petak' ? 'petak' : 'panel',
            // Pencarian DI DALAM panel sesi kiri — kode/aktivitas, disaring di layar
            // karena seluruh sesi program ini memang sudah ikut terkirim.
            sesiCari: '',
            // Halaman daftar sesi DI DALAM kartu program yang terbuka. Satu
            // angka, bukan peta per program: hanya satu kartu yang bisa terbuka,
            // jadi halaman kartu lain tak akan pernah dibaca siapa pun.
            //
            // Dipotong di layar, tidak di server — seluruh sesi program ini
            // sudah ikut terkirim bersama daftarnya, dan bilah alur di atas juga
            // menghitung sesi per tahap dari kumpulan yang sama.
            sesiPage: 1,
            sesiPerPage: 8,
            // Tahap yang sedang disorot di bilah alur; kosong = semua tahap.
            tahapPilih: '',
            // Semua kredensial dibuka sekaligus (lihat toggleKredSemua).
            kredSemua: false,
            timerFilter: null,
            // Pewaktu penutupan otomatis tiap kredensial yang dibuka.
            timerKred: {},
            timerToast: null,
            // Baris akordion yang terbuka (id PROGRAM).
            terbuka: null,
            // Kredensial yang sedang dibuka: kunci `{idPeserta}-tok|otp`.
            kredBuka: {},

            // ── Peserta program yang sedang dibuka ──
            //
            // SATU wadah, bukan peta ber-kunci id seperti dulu. Hanya satu
            // akordion yang bisa terbuka, jadi cache per-id cuma menyimpan data
            // yang tak akan dilihat siapa pun — dan menyimpannya justru
            // menyulitkan: hasil yang tersaring dan yang tidak berebut kunci
            // yang sama, lalu tampil silang.
            pes: { rows: [], total: 0, page: 1, perPage: 20, totalPage: 1, kampusOpsi: [] },
            // Penyaring DI DALAM program: gelombang, tanggal, kampus, pencarian.
            pesFilter: { q: '', kampus: '', sesi: '', tanggal: null },
            timerPes: null,
            memuatPeserta: false,

            cariPaket: '',
            cariKandidat: '',
            timerPaket: null,
            timerKandidat: null,
            editTampil: false,
            editTarget: null,
            // Penjadwalan induk kandidat yang sedang diubah — untuk keterangan.
            editJadwal: null,
            editMulai: '',
            editAkhir: '',
            editSibuk: false,
            notice: '',
            noticeType: 'success',
            // Id penjadwalan yang sedang diantrekan ulang (tombol "Coba Lagi").
            ulangId: '',
            fokusId: new URLSearchParams(window.location.search).get('fokus'),
            // Program yang disorot karena tautan `?fokus=` — id PROGRAM, sedang
            // fokusId berisi id PENJADWALAN yang dikirim Dashboard.
            sorotId: '',
            sudahSorot: false,
            // Hanya bagian JAM yang dipakai Element Plus; tanggalnya diabaikan.
            jamMulaiBawaan: new Date(2000, 0, 1, 8, 0, 0),
            jamAkhirBawaan: new Date(2000, 0, 1, 23, 59, 0),
            form: { programId: null, posisiId: null, tahapUrutan: null, tahapKode: null, tesUrutan: null, tesId: null, idMasterUjian: null, namaUjian: '', waktuMulai: '', waktuAkhir: '', peserta: [] },

            /**
             * Gelombang yang dipantau paksa oleh panel antrean.
             *
             * Diisi tepat setelah Generate atau Coba Lagi. Sesudah itu panel
             * menemukannya sendiri lewat status DIANTRIKAN — daftar ini hanya
             * menjembatani beberapa detik pertama, saat barisnya sudah ada tapi
             * jobnya belum menyentuh apa pun.
             */
            antreanIds: [],

            // ── WIZARD BUAT SESI ────────────────────────────────────────────
            // Susunan sesi pindah ke modal berlangkah; halaman ini kembali jadi
            // apa yang sebenarnya dibuka admin sehari-hari — daftar sesi.
            wizTampil: false,
            wizLangkah: 1,
        };
    },
    computed: {
        programKategori() {
            return this.opsi.program.filter((p) => !this.kategori || p.kategori === this.kategori);
        },
        programTerpilih() {
            return this.opsi.program.find((p) => p.id === this.form.programId) || null;
        },
        basisLoker() {
            return this.basis === 'LOKER';
        },
        /**
         * Yang tercentang, sebagai Set.
         *
         * Bukan kerapian: baris kandidat memanggilnya sekali per baris, dan
         * `Array.includes` pada 250–1.000 kode berarti puluhan ribu sampai
         * sejuta perbandingan setiap kali satu centang berubah. Itu terasa
         * sebagai layar yang tersendat justru pada gelombang besar — keadaan
         * yang paling membutuhkannya lancar.
         */
        pesertaSet() {
            return new Set(this.form.peserta);
        },
        /** Yang tampil dan BELUM tercentang — bahan gelombang berikutnya. */
        sisaTampil() {
            return this.kandidat.filter((k) => !this.pesertaSet.has(k.kode));
        },
        /** Berapa sesi yang dibutuhkan untuk menghabiskan antrean, pada ukuran ini. */
        perkiraanGelombang() {
            const n = Number(this.gelombang) || 0;

            return n > 0 ? Math.ceil((this.kandTotal || this.kandidat.length) / n) : 0;
        },
        /**
         * Lowongan yang boleh dipilih — kategori yang sedang aktif saja.
         *
         * Yang berisi orang menunggu didahulukan, lalu diurutkan per program.
         * Daftar ini bisa panjang (satu program membuka lima loker), dan yang
         * dicari admin saat membukanya selalu yang sama: mana yang ada
         * pekerjaannya hari ini.
         */
        lokerKategori() {
            return (this.opsi.loker || [])
                .filter((l) => !this.kategori || l.kategori === this.kategori)
                .slice()
                .sort((a, b) => (b.menunggu || 0) - (a.menunggu || 0)
                    || (a.programNama || '').localeCompare(b.programNama || '')
                    || (a.posisi || '').localeCompare(b.posisi || ''));
        },
        lokerTerpilih() {
            return (this.opsi.loker || []).find((l) => l.id === this.form.posisiId) || null;
        },
        /**
         * Terpilih tapi tidak sedang tampil — tertutup pencarian.
         *
         * Angkanya disebut di layar supaya "12 dari 40" tidak terbaca sebagai
         * kehilangan. Tanpa itu admin mengulang pencentangan yang sudah ada.
         */
        pesertaTersembunyi() {
            const tampak = new Set(this.kandidat.map((k) => k.kode));

            return this.form.peserta.filter((k) => !tampak.has(k));
        },
        /** Ukuran gelombang yang benar-benar dipakai tombol — dijaga masuk akal. */
        gelombangSah() {
            const n = Math.floor(Number(this.gelombang) || 0);

            return Math.min(Math.max(n, 1), this.kandBatas || 1000);
        },
        /** Seluruh yang SEDANG TAMPIL sudah tercentang? */
        semuaTercentangHalaman() {
            return this.kandidat.length > 0 && this.kandidat.every((k) => this.pesertaSet.has(k.kode));
        },
        tesTerpilihKey() {
            // Kunci memakai KODE tahap bila ada. Dengan hadirnya baris
            // "rombongan alur lama", dua baris bisa bernomor urut sama —
            // memilih satu akan menyorot keduanya kalau kuncinya cuma nomor.
            return this.form.tahapUrutan || this.form.tahapKode
                ? this.kunciTes({ tahapKode: this.form.tahapKode, tahapUrutan: this.form.tahapUrutan, tesUrutan: this.form.tesUrutan, tesId: this.form.tesId })
                : '';
        },
        semuaTercentang() {
            return this.semuaTercentangHalaman;
        },
        sebagianTercentang() {
            return this.form.peserta.length > 0 && !this.semuaTercentang;
        },
        /**
         * Jendela ujian TERBALIK — berakhir sebelum (atau tepat saat) mulai.
         *
         * Server sudah menolaknya (`after:waktuMulai`), tapi penolakan itu baru
         * datang setelah admin memilih paket, mengisi waktu, DAN mencentang
         * kandidatnya. Ditahan di layar, kesalahannya terbaca di detik yang sama
         * saat dibuat.
         *
         * Sama juga ditolak: mulai == berakhir. Jendela berdurasi nol berarti
         * token terbit untuk tes yang tidak pernah bisa dibuka.
         */
        jendelaSalah() {
            return this.terbalik(this.form.waktuMulai, this.form.waktuAkhir);
        },
        editSalah() {
            return this.terbalik(this.editMulai, this.editAkhir);
        },
        /** Jendela yang SUDAH tertutup (berakhir di masa lalu) — tak bisa dikerjakan siapa pun. */
        jendelaLewat() {
            const b = this.keTanggal(this.form.waktuAkhir);

            return !!b && b.getTime() <= Date.now();
        },
        editLewat() {
            const b = this.keTanggal(this.editAkhir);

            return !!b && b.getTime() <= Date.now();
        },
        bisaGenerate() {
            const f = this.form;
            if (this.jendelaSalah || this.jendelaLewat) return false;

            return !!(f.programId && f.tahapUrutan && f.idMasterUjian && f.waktuMulai && f.waktuAkhir && f.peserta.length);
        },
        labelGenerate() {
            return this.menyimpan ? 'Membuat sesi…' : `Generate ${this.form.peserta.length} Sesi Tes`;
        },
        /** Potongan kandidat untuk halaman yang sedang dilihat. */
        kandidatHal() {
            const a = (this.kandPage - 1) * this.kandPerPage;

            return this.kandidat.slice(a, a + this.kandPerPage);
        },
        kandTotalPage() {
            return Math.max(1, Math.ceil(this.kandidat.length / this.kandPerPage));
        },
        adaFilter() {
            const f = this.filter;

            return !!(f.q || f.programId || f.status || f.kategori);
        },
        adaFilterPeserta() {
            const f = this.pesFilter;

            return !!(f.q || f.kampus || f.sesi || (f.tanggal && f.tanggal.length));
        },

        /* ── WIZARD ─────────────────────────────────────────────────────────── */
        /** Baris tes yang sedang dipilih — untuk ringkasan & tinjauan. */
        tesTerpilih() {
            return this.tesAlur.find((t) => this.kunciTes(t) === this.tesTerpilihKey) || null;
        },
        paketTerpilih() {
            return this.paket.find((p) => p.Id_Master_Ujian === this.form.idMasterUjian) || null;
        },
        /** "Tahap › Aktivitas" — satu tahap bisa berisi beberapa aktivitas. */
        ringkasAktivitas() {
            const t = this.tesTerpilih;
            if (!t) return '';

            return t.multi ? `${t.tahapLabel} › ${t.tesLabel}` : t.tahapLabel;
        },
        /** Objek kandidat yang tercentang, untuk disebut namanya di tinjauan. */
        /**
         * Nama-nama yang akan dikirim — dibaca dari CATATAN, bukan dari daftar
         * yang kebetulan sedang tampil.
         *
         * Bentuk lama menyaring `kandidat`, jadi admin yang menyisakan pencarian
         * menyala saat menekan Lanjut membaca "40 peserta" di kepala kartu dan
         * tiga nama di bawahnya. Layar yang tugasnya membacakan kembali keputusan
         * justru jadi tempat yang paling tidak boleh menyembunyikan apa pun.
         */
        pesertaTerpilih() {
            return this.form.peserta.map((kode) => this.pesertaInfo[kode] || { kode, nama: kode, posisi: null });
        },
        /**
         * Definisi langkah + apa yang SUDAH terisi di masing-masing.
         *
         * `nilai` membuat bilah langkah berguna setelah dilewati: tanpa itu ia
         * hanya tiga angka, dan admin harus mundur satu per satu cuma untuk
         * memastikan paket mana yang tadi dipilih.
         */
        wizSteps() {
            const f = this.form;

            const jendelaSiap = !!(f.waktuMulai && f.waktuAkhir) && !this.jendelaSalah;

            return [
                {
                    no: 1,
                    judul: 'Ujian',
                    hint: this.basisLoker ? 'Lowongan, aktivitas, paket soal' : 'Program, aktivitas, paket soal',
                    nilai: [this.ringkasAktivitas, f.namaUjian].filter(Boolean).join(' · '),
                    bisa: !!(f.programId && f.tahapUrutan && f.idMasterUjian),
                },
                {
                    no: 2,
                    judul: 'Jadwal & Peserta',
                    hint: 'Kapan dikerjakan, siapa pesertanya',
                    nilai: jendelaSiap && f.peserta.length
                        ? `${this.fmtWaktu(f.waktuMulai)} · ${f.peserta.length} kandidat`
                        : '',
                    bisa: jendelaSiap && f.peserta.length > 0,
                },
                {
                    no: 3,
                    judul: 'Tinjau & Kirim',
                    hint: 'Periksa sebelum token terbit',
                    nilai: '',
                    bisa: this.bisaGenerate,
                },
            ];
        },
        /** Langkah sekarang sudah cukup terisi untuk maju? */
        wizBisaLanjut() {
            return !!(this.wizSteps.find((s) => s.no === this.wizLangkah) || {}).bisa;
        },
        /** Kenapa tombol Lanjut mati — dijelaskan, bukan dibiarkan menebak. */
        wizAlasan() {
            if (this.wizBisaLanjut) return '';

            const f = this.form;
            if (this.wizLangkah === 1) {
                if (!f.programId) return this.basisLoker ? 'Pilih lowongan dulu.' : 'Pilih program dulu.';
                if (!f.tahapUrutan) return 'Pilih aktivitas yang dijadwalkan.';

                return 'Pilih satu paket tes dari HCLearn.';
            }
            if (this.wizLangkah === 2) {
                if (this.jendelaSalah) return 'Waktu berakhir harus setelah waktu mulai.';
                if (!f.waktuMulai || !f.waktuAkhir) return 'Isi waktu mulai dan waktu berakhir.';

                return 'Centang minimal satu kandidat.';
            }

            return '';
        },
        /** Subjudul modal — konteks yang sudah dipilih, terbaca di tiap langkah. */
        wizSub() {
            const l = this.lokerTerpilih;
            const bagian = [
                // Lowongan lebih dulu bila memang itu sasarannya: nama program
                // sudah ikut di belakangnya, dan yang membedakan dua sesi pada
                // program yang sama justru lokernya.
                l ? `${l.posisi} · ${l.programNama}` : (this.programTerpilih ? this.programTerpilih.nama : ''),
                this.ringkasAktivitas,
            ].filter(Boolean);

            return bagian.length ? bagian.join(' · ') : 'Susun sesi tes online dalam tiga langkah';
        },
    },
    watch: {
        // Jumlah kandidat berubah (ganti tahap / cari) → jangan tertinggal di
        // halaman yang sudah tidak ada isinya.
        'kandidat.length'() { this.kandPage = 1; },
        // Mengetik di panel sesi mempersempit daftarnya; halaman ke-4 dari
        // daftar sebelum diketik biasanya sudah tidak ada.
        sesiCari() { this.sesiPage = 1; },
        /**
         * Menggeser waktu MULAI melewati waktu berakhir mengosongkan yang
         * berakhir, bukan membiarkannya jadi jendela terbalik.
         *
         * Urutan isian di lapangan hampir selalu mulai → berakhir, lalu mulai
         * digeser lagi karena ruangannya pindah hari. Membiarkan nilai lama
         * bertahan berarti admin harus INGAT untuk membetulkannya; mengosongkan
         * membuat kolomnya menagih sendiri.
         */
        'form.waktuMulai'() {
            if (this.jendelaSalah) this.form.waktuAkhir = '';
        },
        editMulai() {
            if (this.editSalah) this.editAkhir = '';
        },
    },
    beforeUnmount() {
        // Pewaktu penutupan kredensial jangan menyala setelah halaman ditinggal.
        Object.values(this.timerKred).forEach((t) => clearTimeout(t));
        clearTimeout(this.timerToast);
        clearTimeout(this.timerFilter);
        clearTimeout(this.timerPes);
        clearTimeout(this.timerPaket);
        clearTimeout(this.timerKandidat);
    },
    mounted() {
        this.muatOpsi();
        this.muatPaket();
        this.muat();
    },
    methods: {
        /** Toast: muncul lalu hilang sendiri. Pesan baru menyela yang lama. */
        beritahu(pesan, tipe = 'success') {
            this.notice = pesan;
            this.noticeType = tipe;
            clearTimeout(this.timerToast);
            this.timerToast = setTimeout(() => { this.notice = ''; }, tipe === 'error' ? 6000 : 4000);
        },
        inisial(nama) {
            return (nama || '?').split(' ').slice(0, 2).map((n) => n[0]).join('').toUpperCase();
        },
        /* ── WIZARD BUAT SESI ──────────────────────────────────────────────── */
        bukaWizard() {
            this.wizLangkah = 1;
            this.wizTampil = true;
            // Paket bisa berubah di HCLearn di antara dua kali buka; daftar yang
            // basi membuat admin memilih paket yang sudah tidak ada di sana.
            if (!this.paket.length) this.muatPaket();
        },
        /**
         * Menutup TIDAK mengosongkan formulir.
         *
         * Alasan paling sering wizard ditutup di tengah bukan membatalkan, tapi
         * memeriksa sesuatu di daftar di belakangnya — sesi kembar, jendela
         * kemarin. Membuang isian di situ berarti menghukum pemeriksaan itu.
         * Yang mengosongkan adalah simpan yang berhasil.
         */
        tutupWizard() {
            if (this.menyimpan) return;
            this.wizTampil = false;
        },
        /**
         * Pindah langkah. Maju hanya bila langkah SEKARANG sudah terisi; mundur
         * dan melompat ke langkah yang sudah dilewati selalu boleh — itu cara
         * membetulkan pilihan tanpa mengulang dari awal.
         */
        keLangkah(n) {
            if (n < 1 || n > 3 || this.menyimpan) return;
            if (n > this.wizLangkah && !this.wizBisaLanjut) return;
            this.wizLangkah = n;
        },
        /**
         * COBA LAGI penjadwalan yang gagal.
         *
         * Tidak menyusun apa pun dari awal: jadwal, paket ujian, dan daftar
         * pesertanya sudah tersimpan — yang diulang hanya penerbitan tokennya,
         * dan hanya untuk kandidat yang tokennya belum terbit.
         */
        async ulangJadwal(s) {
            if (this.ulangId) return;
            this.ulangId = s.id;
            // Program yang sedang terbuka — dicatat sebelum daftar dimuat ulang,
            // karena objek barisnya diganti yang baru setelah itu.
            const dibuka = this.terbuka;
            try {
                const res = await axios.post(`/api/v1/penjadwalan/${s.id}/ulang`, {}, {
                    headers: { Accept: 'application/json' },
                });
                this.beritahu(res.data?.message || 'Penjadwalan diantrekan ulang.');
                // Ikut dipantau panel antrean — percobaan ulang untuk ratusan
                // orang sama lamanya dengan penerbitan pertama.
                this.antreanIds = [...new Set([...this.antreanIds, s.id])];
                await this.muat();
                // Akordion yang terbuka ikut disegarkan: statusnya baru saja
                // berubah, dan yang sedang dilihat admin justru bagian ini.
                const program = this.daftar.find((p) => p.id === dibuka);
                if (program) await this.muatPeserta(program);
            } catch (e) {
                this.beritahu(e.response?.data?.message || 'Gagal mengantrekan ulang.', 'error');
            } finally {
                this.ulangId = '';
            }
        },
        /**
         * Rupa chip tahap pada kartu penjadwalan:
         *   is-active — tahap yang ujiannya dijadwalkan di sini (punya Nama_Ujian);
         *   is-hcl    — tahap ujian online lain di alur yang sama;
         *   is-todo   — tahap yang ditangani tim.
         */
        stepKelas(t) {
            if (t.namaUjian) return 'is-active';

            return t.kirimHclearn ? 'is-hcl' : 'is-todo';
        },
        async muatOpsi() {
            try {
                const res = await axios.get('/api/v1/penjadwalan/opsi', { headers: { Accept: 'application/json' } });
                this.opsi = res.data.result || { talent: [], program: [], loker: [] };
                if (!this.opsi.loker) this.opsi.loker = [];
                if (!this.kategori && this.opsi.talent.length) this.kategori = this.opsi.talent[0].kode;
            } catch (e) {
                this.beritahu('Gagal memuat opsi program', 'error');
            }
        },
        gantiKategori(kode) {
            this.kategori = kode;
            this.form.programId = null;
            this.form.posisiId = null;
            this.lupakanTes();
            this.muatTesAlur(); // tanpa program → daftar tes ikut dikosongkan
        },
        /**
         * Pindah basis: lowongan ↔ program.
         *
         * Yang ditinggalkan dikosongkan seluruhnya, bukan disisakan. Membiarkan
         * `posisiId` lama bertahan saat basis berpindah ke program berarti sesi
         * yang admin kira mencakup seisi program diam-diam tersaring ke satu
         * loker — dan tak ada satu pun tulisan di layar yang menyebutkannya.
         */
        gantiBasis(b) {
            if (this.basis === b) return;
            this.basis = b;
            try { localStorage.setItem('pjd.basis', b); } catch (e) { /* mode privat */ }
            this.form.programId = null;
            this.form.posisiId = null;
            this.lupakanTes();
            this.muatTesAlur();
        },
        /**
         * Lowongan dipilih — programnya ikut, tanpa ditanya lagi.
         *
         * Loker membawa Program_Id sendiri, dan alur/tahap tetap milik program.
         * Jadi yang berubah cuma cara admin menyebut sasarannya, bukan bentuk
         * data di bawahnya.
         */
        onLoker() {
            const l = this.lokerTerpilih;
            this.form.programId = l ? l.programId : null;
            this.lupakanTes();
            this.muatTesAlur();
        },
        onProgram() {
            // Ganti program = ganti alur, jadi pilihan tes lama tidak berlaku lagi.
            this.form.posisiId = null;
            this.lupakanTes();
            this.muatTesAlur();
        },
        // Kosongkan pilihan tes + turunannya (kandidat ikut tahap yang dipilih).
        lupakanTes() {
            this.form.tahapUrutan = null;
            this.form.tahapKode = null;
            this.form.tesUrutan = null;
            this.form.tesId = null;
            this.form.peserta = [];
            // Ruangnya berganti — di sinilah pilihan MEMANG harus dilepas.
            // Bandingkan dengan muatKandidat(), yang tidak boleh melepasnya.
            this.pesertaInfo = {};
            this.kandidat = [];
            this.kandTotal = 0;
            this.kandTerpotong = false;
            this.alasanKandidat = '';
        },
        async muatTesAlur() {
            if (!this.form.programId) { this.tesAlur = []; this.alasanTes = ''; return; }
            this.memuatTes = true;
            try {
                const params = { programId: this.form.programId };
                // Angka "N menunggu" pada tiap baris tes ikut lowongan yang
                // dipilih — lihat PenjadwalanController::hitungMenunggu().
                if (this.form.posisiId) params.posisiId = this.form.posisiId;
                const res = await axios.get('/api/v1/penjadwalan/tes', {
                    params,
                    headers: { Accept: 'application/json' },
                });
                this.tesAlur = res.data.result || [];
                this.alasanTes = this.tesAlur.length ? '' : (res.data.message || '');
                // Belum memilih apa pun → pilihkan. Satu ujian saja: langsung itu.
                // Beberapa: arahkan ke yang benar-benar ada kandidat menunggu —
                // itu yang dicari admin saat membuka halaman ini. Kalau admin
                // sudah memilih sendiri, jangan digeser.
                if (!this.form.tahapUrutan) {
                    const perlu = this.tesAlur.length === 1
                        ? this.tesAlur[0]
                        : this.tesAlur.find((t) => t.menunggu > 0);
                    if (perlu) this.pilihTes(perlu);
                }
            } catch (e) {
                this.beritahu('Gagal memuat tes pada alur program', 'error');
            } finally {
                this.memuatTes = false;
            }
        },
        /** Identitas satu baris tes — dipakai `:key` maupun penanda terpilih. */
        kunciTes(t) {
            // Id aktivitas lebih dulu — nomor urut hanya untuk baris yang memang
            // tak punya id (aktivitas pra-mesin). Dua baris berbeda di tahap yang
            // sama bisa memakai nomor urut yang sama setelah alur disunting, dan
            // kunci yang bertabrakan membuat dua baris tersorot sekaligus.
            return `${t.tahapKode || 'U' + t.tahapUrutan}#${t.tesId ? 'T' + t.tesId : 'N' + t.tesUrutan}`;
        },
        pilihTes(t) {
            this.form.tahapUrutan = t.tahapUrutan;
            // IDENTITAS tahap ikut dibawa. Nomor urut saja tidak cukup begitu
            // alur program disunting atau diganti: nomor yang sama bisa
            // menunjuk tahap yang lain, dan jadwal terkirim untuk tes yang
            // bukan itu — ke orang yang bukan itu juga.
            this.form.tahapKode = t.tahapKode || null;
            this.form.tesUrutan = t.tesUrutan;
            // IDENTITAS AKTIVITAS, dengan alasan yang sama seperti tahapKode di
            // atas — satu tingkat lebih dalam. Nomor urut aktivitas milik master,
            // sedangkan yang dicocokkan di server adalah snapshot milik kandidat;
            // keduanya berselisih begitu urutan aktivitas di Master Alur disunting.
            this.form.tesId = t.tesId || null;
            // Ganti aktivitas = ganti orang yang menunggunya. Centang lama tidak
            // boleh ikut — lihat kosongkanPeserta() untuk alasan pemisahannya
            // dari pemangkasan saat mencari.
            this.kosongkanPeserta();
            this.muatKandidat();
        },
        async muatPaket() {
            this.memuatPaket = true;
            this.paket = [];
            this.form.idMasterUjian = null;
            this.form.namaUjian = '';
            try {
                const res = await axios.get('/api/v1/penjadwalan/paket-ujian', {
                    params: this.cariPaket ? { q: this.cariPaket } : {},
                    headers: { Accept: 'application/json' },
                });
                this.paket = res.data.result || [];
            } catch (e) {
                this.beritahu(e.response?.data?.message || 'Gagal memuat paket tes dari HCLearn', 'error');
            } finally {
                this.memuatPaket = false;
            }
        },
        debounceCari() {
            clearTimeout(this.timerPaket);
            this.timerPaket = setTimeout(() => this.muatPaket(), 400);
        },
        pilihPaket(p) {
            this.form.idMasterUjian = p.Id_Master_Ujian;
            this.form.namaUjian = p.Nama_Ujian;
        },
        async muatKandidat() {
            // Kandidat = pelamar NYATA program terpilih yang punya SUB-TES pihak
            // ke-3 menunggu jadwal. Satu tahap bisa berisi beberapa tes, jadi
            // orang yang sama bisa muncul lagi untuk tes berikutnya di tahap itu.
            // Belum ada sasaran/aktivitas → daftarnya dikosongkan, TAPI pilihan
            // tahap tidak ikut dibuang: yang memanggil di sini kadang justru
            // sedang menyusunnya (lihat lupakanTes(), yang memang membuangnya).
            if (!this.form.programId || !this.form.tahapUrutan) {
                this.kandidat = [];
                this.kandTotal = 0;
                this.kandTerpotong = false;
                this.kosongkanPeserta();
                this.alasanKandidat = '';

                return;
            }
            this.memuatKandidat = true;
            try {
                const params = { programId: this.form.programId, tahapUrutan: this.form.tahapUrutan };
                if (this.form.posisiId) params.posisiId = this.form.posisiId;
                if (this.form.tahapKode) params.tahapKode = this.form.tahapKode;
                if (this.form.tesUrutan) params.tesUrutan = this.form.tesUrutan;
                if (this.form.tesId) params.tesId = this.form.tesId;
                if (this.cariKandidat) params.q = this.cariKandidat;
                const res = await axios.get('/api/v1/penjadwalan/kandidat', { params, headers: { Accept: 'application/json' } });
                const hasil = res.data.result || {};
                this.kandidat = hasil.items || [];
                this.kandTotal = hasil.total || this.kandidat.length;
                this.kandBatas = hasil.batas || 0;
                this.kandTerpotong = !!hasil.terpotong;
                this.alasanKandidat = this.kandidat.length ? (this.kandTerpotong ? (res.data.message || '') : '') : (res.data.message || '');

                // ══ PILIHAN TIDAK DIPANGKAS DI SINI ══════════════════════════
                //
                // Baris yang dulu berdiri di tempat ini membuang setiap kode
                // yang tak ada di daftar TERBARU. Selama daftar itu selalu
                // seluruh kandidat, ia tak pernah salah. Tapi pencariannya
                // dijalankan server: mengetik "budi" membuat daftar terbaru
                // berisi satu orang, dan 400 centang yang sudah dikumpulkan
                // admin lenyap seketika — tanpa pesan, tanpa cara memulihkan.
                //
                // Yang dicatat sekarang justru sebaliknya: rincian orang yang
                // tercentang disimpan supaya ia tetap bisa disebut namanya di
                // layar Tinjau walau daftarnya sedang menampilkan yang lain.
                this.ingatPeserta(this.kandidat);
            } catch (e) {
                this.beritahu('Gagal memuat kandidat', 'error');
            } finally {
                this.memuatKandidat = false;
            }
        },
        debounceKandidat() {
            clearTimeout(this.timerKandidat);
            this.timerKandidat = setTimeout(() => this.muatKandidat(), 400);
        },
        /** Simpan rincian kandidat yang tercentang, supaya selamat dari pencarian. */
        ingatPeserta(daftar) {
            const catat = { ...this.pesertaInfo };
            daftar.forEach((k) => {
                if (this.form.peserta.includes(k.kode)) catat[k.kode] = k;
            });
            this.pesertaInfo = catat;
        },
        toggleKandidat(kode) {
            const i = this.form.peserta.indexOf(kode);
            if (i >= 0) {
                this.form.peserta.splice(i, 1);
                delete this.pesertaInfo[kode];
            } else {
                this.form.peserta.push(kode);
                const k = this.kandidat.find((c) => c.kode === kode);
                if (k) this.pesertaInfo[kode] = k;
            }
        },
        /**
         * "Pilih semua" bekerja pada YANG SEDANG TAMPIL, dan MENAMBAH.
         *
         * Bentuk lama menimpa seluruh pilihan dengan isi daftar saat itu. Dengan
         * pencarian di server itu berarti: cari "produksi", pilih semua, cari
         * "gudang", pilih semua — dan yang tersisa hanya gudang. Menambah
         * membuat penyaringan bertahap jadi cara yang sah untuk menyusun
         * gelombang, bukan jebakan.
         *
         * Melepasnya pun hanya melepas yang tampil; centang di luar hasil
         * pencarian tidak ikut terbawa. Untuk mengosongkan semuanya ada
         * tombolnya sendiri (kosongkanPeserta).
         */
        toggleSemua() {
            const tampil = this.kandidat.map((k) => k.kode);

            if (this.semuaTercentangHalaman) {
                const buang = new Set(tampil);
                this.form.peserta = this.form.peserta.filter((k) => !buang.has(k));
                buang.forEach((k) => delete this.pesertaInfo[k]);

                return;
            }

            this.form.peserta = [...new Set([...this.form.peserta, ...tampil])];
            this.ingatPeserta(this.kandidat);
        },
        /**
         * AMBIL SATU GELOMBANG — N nama pertama yang belum tercentang.
         *
         * Urutannya mengikuti daftar apa adanya (nama A–Z dari server), jadi
         * gelombang berikutnya melanjutkan dari tempat yang sama dan tidak ada
         * orang yang terus-menerus kebagian gelombang terakhir.
         *
         * MENAMBAH, tidak menimpa: admin yang sudah menandai lima orang tertentu
         * lalu menekan tombol ini mendapat lima itu plus sisanya — bukan
         * kehilangan kelimanya.
         */
        ambilGelombang() {
            const n = this.gelombangSah;
            const ambil = this.sisaTampil.slice(0, Math.max(0, n - this.form.peserta.length));

            if (! ambil.length) return;

            this.form.peserta = [...this.form.peserta, ...ambil.map((k) => k.kode)];
            this.ingatPeserta(ambil);
        },
        simpanGelombang() {
            this.gelombang = this.gelombangSah;
            try { localStorage.setItem('pjd.gelombang', String(this.gelombang)); } catch (e) { /* mode privat */ }
        },
        /** Lepas SELURUH centang — termasuk yang sedang tertutup pencarian. */
        kosongkanPeserta() {
            this.form.peserta = [];
            this.pesertaInfo = {};
        },
        async muat() {
            this.memuat = true;
            try {
                const params = { page: this.daftarPage, perPage: this.daftarPerPage };
                if (this.filter.q) params.q = this.filter.q;
                if (this.filter.programId) params.programId = this.filter.programId;
                if (this.filter.status) params.status = this.filter.status;
                if (this.filter.kategori) params.kategori = this.filter.kategori;
                const res = await axios.get('/api/v1/penjadwalan', { params, headers: { Accept: 'application/json' } });
                const r = res.data.result || {};
                this.daftar = r.data || [];
                this.daftarTotal = r.total || 0;
                this.daftarTotalPage = r.totalPage || 1;
                // Halaman bisa jadi kosong setelah menyaring atau menghapus.
                if (this.daftarPage > this.daftarTotalPage) { this.daftarPage = this.daftarTotalPage; return this.muat(); }
                this.sorotDariTautan();
            } catch (e) {
                this.beritahu('Gagal memuat daftar penjadwalan', 'error');
            } finally {
                this.memuat = false;
            }
        },
        async simpan() {
            // Pagar terakhir di layar. Tombolnya memang sudah mati saat jendela
            // terbalik, tapi simpan() juga terpanggil dari jalur lain.
            if (this.jendelaSalah) {
                this.beritahu('Waktu berakhir harus setelah waktu mulai.', 'error');

                return;
            }
            this.menyimpan = true;
            try {
                const res = await axios.post('/api/v1/penjadwalan', this.form, { headers: { Accept: 'application/json' } });
                this.beritahu(res.data.message || 'Penjadwalan dibuat');
                // Panel antrean langsung menampilkannya. Tanpa baris ini ia
                // baru menyusul beberapa detik kemudian, dan justru detik-detik
                // pertama itulah yang paling menuntut kepastian.
                const idBaru = res.data?.result?.id;
                if (idBaru) this.antreanIds = [...new Set([...this.antreanIds, idBaru])];
                this.kosongkanPeserta();
                // Sesi sudah terbit — wizard ditutup dan dikembalikan ke langkah
                // pertama. Membiarkannya terbuka di layar Tinjau yang isinya
                // sudah tidak berlaku membuat tombol Generate tampak masih bisa
                // ditekan sekali lagi untuk orang yang sama.
                this.wizTampil = false;
                this.wizLangkah = 1;
                this.muat();
                // Yang barusan dijadwalkan hilang dari antrean — segarkan hitungannya.
                this.muatTesAlur();
                this.muatKandidat();
            } catch (e) {
                this.beritahu(e.response?.data?.message || 'Gagal membuat penjadwalan', 'error');
            } finally {
                this.menyimpan = false;
            }
        },
        /* ── Daftar penjadwalan: filter, halaman, akordion ── */
        debounceFilter() {
            clearTimeout(this.timerFilter);
            this.timerFilter = setTimeout(() => { this.daftarPage = 1; this.muat(); }, 400);
        },
        gantiFilter() {
            this.daftarPage = 1;
            this.muat();
        },
        resetFilter() {
            this.filter = { q: '', programId: null, status: '', kategori: '' };
            this.daftarPage = 1;
            this.muat();
        },
        gantiHalaman(n) {
            if (n < 1 || n > this.daftarTotalPage || n === this.daftarPage) return;
            this.daftarPage = n;
            this.terbuka = null;
            this.muat();
        },
        /**
         * Buka/tutup satu baris. Pesertanya baru diambil saat pertama dibuka —
         * daftar bisa panjang, dan yang tak dibuka tak perlu dikueri sama sekali.
         */
        async toggleBaris(j) {
            // Pindah/tutup baris → semua kredensial yang telanjur dibuka ditutup.
            this.kredBuka = {};
            this.kredSemua = false;
            if (this.terbuka === j.id) { this.terbuka = null; return; }
            this.terbuka = j.id;
            // Program lain = pertanyaan lain. Penyaring lama dilupakan, kalau
            // tidak admin membuka kartu berisi nol baris tanpa sebab yang
            // terlihat — kampus dari program sebelumnya masih menempel.
            this.pesFilter = { q: '', kampus: '', sesi: '', tanggal: null };
            this.sesiCari = '';
            this.tahapPilih = '';
            this.sesiPage = 1;
            this.pes = { ...this.pes, rows: [], total: 0, page: 1, totalPage: 1, kampusOpsi: [] };
            // Panel kanan langsung menunjuk sesi TERBARU. Membuka kartu lalu
            // disambut setengah layar kosong berarti satu klik wajib sebelum
            // apa pun terbaca — padahal sesi terbaru hampir selalu yang dicari.
            const pertama = this.sesiTampil(j)[0];
            if (pertama) this.pesFilter.sesi = pertama.kode;
            await this.muatPeserta(j);
        },
        /**
         * `?fokus=` — datang dari Dashboard, dan isinya id PENJADWALAN.
         *
         * Kartunya kini ber-id PROGRAM, jadi pencocokan langsung tidak lagi
         * ketemu. Alih-alih membiarkan tautannya mati diam-diam, gelombang itu
         * dicari di dalam daftar sesi tiap program: kartunya dibuka, digulir ke
         * layar, dan chip sesinya langsung terpilih — admin mendarat tepat pada
         * gelombang yang ia klik, bukan sekadar pada programnya.
         */
        sorotDariTautan() {
            if (!this.fokusId) return;
            const fokus = String(this.fokusId);
            const program = this.daftar.find((p) => String(p.id) === fokus)
                || this.daftar.find((p) => (p.sesi || []).some((s) => String(s.id) === fokus));
            if (!program) return;

            // Kartunya tetap disorot (fokusId dibiarkan utuh), tapi penggulirannya
            // hanya sekali — kalau tidak, tiap pemuatan ulang daftar menyeret
            // admin kembali ke sini di tengah pekerjaan lain.
            if (this.sudahSorot) return;
            this.sudahSorot = true;
            this.sorotId = program.id;

            this.$nextTick(async () => {
                document.getElementById(`penjadwalan-${program.id}`)
                    ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (this.terbuka === program.id) return;
                await this.toggleBaris(program);
                const sesi = (program.sesi || []).find((s) => String(s.id) === fokus);
                if (sesi) this.pilihSesi(program, sesi);
            });
        },
        /** Ambil satu halaman peserta program — seluruh penyaring ikut ke server. */
        async muatPeserta(j) {
            if (!j) return;
            this.memuatPeserta = true;
            try {
                const f = this.pesFilter;
                const params = { page: this.pes.page, perPage: this.pes.perPage };
                if (f.q) params.q = f.q;
                if (f.kampus) params.kampus = f.kampus;
                if (f.sesi) params.sesi = f.sesi;
                if (f.tanggal && f.tanggal.length === 2) {
                    [params.dari, params.sampai] = f.tanggal;
                }
                const res = await axios.get(`/api/v1/penjadwalan/program/${j.id}/peserta`, {
                    params, headers: { Accept: 'application/json' },
                });
                const r = res.data.result || {};
                this.pes = {
                    rows: r.data || [],
                    total: r.total || 0,
                    page: r.page || 1,
                    perPage: r.perPage || this.pes.perPage,
                    totalPage: r.totalPage || 1,
                    // Pilihan kampus datang dari SELURUH program, bukan dari
                    // hasil yang sedang tersaring — kalau tidak, memilih satu
                    // kampus akan membuat pilihan lainnya lenyap.
                    kampusOpsi: r.kampusOpsi || [],
                };
                // Halaman bisa jadi kosong setelah penyaring dipersempit.
                if (this.pes.page > this.pes.totalPage) {
                    this.pes.page = this.pes.totalPage;

                    return this.muatPeserta(j);
                }
            } catch (e) {
                this.beritahu('Gagal memuat peserta program', 'error');
            } finally {
                this.memuatPeserta = false;
            }
        },
        /** Mengetik tidak langsung menembak server — jeda dulu. */
        debouncePeserta(j) {
            clearTimeout(this.timerPes);
            this.timerPes = setTimeout(() => this.filterPeserta(j), 400);
        },
        filterPeserta(j) {
            this.pes.page = 1;
            this.kredBuka = {};
            this.muatPeserta(j);
        },
        /**
         * Pilih sesi di panel kiri.
         *
         * SATU ARAH, tidak lagi dua arah seperti saat masih berupa chip: panel
         * kanan seluruhnya milik sesi terpilih, dan melepasnya hanya
         * mengosongkan setengah layar tanpa memberi apa pun sebagai gantinya.
         */
        pilihSesi(j, s) {
            if (this.pesFilter.sesi === s.kode) return;
            this.pesFilter.sesi = s.kode;
            this.filterPeserta(j);
        },
        /**
         * Satu gelombang tuntas di panel antrean → daftar ikut menyusul.
         *
         * Statusnya baru saja berubah di server (DIANTRIKAN → BERJALAN/GAGAL)
         * dan hitungan "menunggu token" di kartu programnya ikut bergeser.
         * Tanpa ini, satu-satunya cara melihat hasilnya adalah menyegarkan
         * halaman — persis pekerjaan yang panel ini hendak hapus.
         */
        async onAntreanSelesai(s) {
            this.beritahu(
                s.gagal > 0
                    ? `${s.kode}: ${s.selesai} token terbit, ${s.gagal} gagal.`
                    : `${s.kode}: ${s.selesai} token selesai diterbitkan.`,
                s.gagal > 0 && s.selesai === 0 ? 'error' : 'success',
            );
            const dibuka = this.terbuka;
            await this.muat();
            const program = this.daftar.find((p) => p.id === dibuka);
            if (program) await this.muatPeserta(program);
        },
        /** Simpan cara membaca daftar sesi ('panel' | 'petak'). */
        setTampilan(v) {
            this.tampilan = v;
            try { localStorage.setItem('pjd.tampilan', v); } catch (e) { /* mode privat */ }
        },
        pilihKategori(kode) {
            this.filter.kategori = this.filter.kategori === kode ? '' : kode;
            this.daftarPage = 1;
            this.terbuka = null;
            this.muat();
        },
        /** Sorot satu tahap di bilah alur — menyaring daftar sesi di panel kiri. */
        pilihTahap(j, label) {
            this.tahapPilih = this.tahapPilih === label ? '' : label;
            // Kumpulan sesinya berganti, jadi halaman ke-3 milik kumpulan lama
            // hampir pasti tidak ada di kumpulan baru.
            this.sesiPage = 1;
            // Sesi terpilih bisa jadi milik tahap yang barusan disaring keluar;
            // panel kanan yang menunjuk sesi tak terlihat membuat dua panel
            // bercerita tentang dua hal berbeda.
            const masih = this.sesiTampil(j).some((s) => s.kode === this.pesFilter.sesi);
            if (!masih) this.pilihSesiPertama(j);
        },
        /** Sesi milik satu tahap — dipakai bilah alur untuk menyebut jumlahnya. */
        sesiTahap(j, label) {
            return (j.sesi || []).filter((s) => s.aktivitas === label);
        },
        /** Sesi yang lolos sorotan tahap + pencarian panel kiri. */
        sesiTampil(j) {
            const q = this.sesiCari.trim().toLowerCase();

            return (j.sesi || []).filter((s) => {
                if (this.tahapPilih && s.aktivitas !== this.tahapPilih) return false;
                if (!q) return true;

                return `${s.kode} ${s.aktivitas || ''} ${s.paketUjian || ''}`.toLowerCase().includes(q);
            });
        },
        /** Sepotong halaman dari sesi yang lolos saringan — inilah yang dirender. */
        sesiHal(j) {
            const awal = (this.sesiPage - 1) * this.sesiPerPage;

            return this.sesiTampil(j).slice(awal, awal + this.sesiPerPage);
        },
        sesiTotalPage(j) {
            return Math.max(1, Math.ceil(this.sesiTampil(j).length / this.sesiPerPage));
        },
        gantiHalSesi(j, n) {
            if (n < 1 || n > this.sesiTotalPage(j) || n === this.sesiPage) return;
            this.sesiPage = n;
        },
        /**
         * Sesi dikelompokkan PER TANGGAL.
         *
         * Begitulah orang mencarinya: "sesi hari ini", "yang kemarin". Daftar
         * datar berisi tujuh belas JDW-00xx menuntut membaca tanggal tiap baris
         * satu per satu untuk menemukan yang mana.
         *
         * Yang dikelompokkan HANYA sesi di halaman ini — pengelompokannya
         * hiasan bagi daftar yang sedang dibaca, bukan penentu isinya.
         */
        sesiPerTanggal(j) {
            const hariIni = this.fmtTanggal(new Date().toISOString());
            const peta = new Map();
            this.sesiHal(j).forEach((s) => {
                const label = this.fmtTanggal(s.waktuMulai) || 'Tanpa tanggal';
                if (!peta.has(label)) peta.set(label, []);
                peta.get(label).push(s);
            });

            return [...peta.entries()].map(([label, items]) => ({ label, items, hariIni: label === hariIni }));
        },
        /** Sesi yang sedang dibuka di panel kanan. */
        sesiTerpilih(j) {
            return this.sesiTampil(j).find((s) => s.kode === this.pesFilter.sesi) || null;
        },
        /** Panel kanan tak boleh kosong saat masih ada sesi yang bisa dibuka. */
        pilihSesiPertama(j) {
            const pertama = this.sesiTampil(j)[0];
            this.pesFilter.sesi = pertama ? pertama.kode : '';
            this.filterPeserta(j);
        },
        /**
         * Buka/tutup SELURUH kredensial di halaman ini sekaligus.
         *
         * Menutup kembali tetap satu klik — dan itu yang penting: token & OTP
         * adalah kunci masuk ujian, jadi keadaan bawaannya harus tertutup dan
         * kembali tertutup semudah dibuka.
         */
        toggleKredSemua() {
            this.kredSemua = !this.kredSemua;
            if (!this.kredSemua) { this.kredBuka = {}; return; }
            const buka = {};
            (this.pes.rows || []).forEach((r) => {
                if (r.shortToken) buka[`${r.id}-tok`] = true;
                if (r.otp) buka[`${r.id}-otp`] = true;
            });
            this.kredBuka = buka;
        },
        sesiPerluUlang(j) {
            return (j.sesi || []).filter((s) => s.menunggu > 0);
        },
        menungguSesi(j) {
            return (j.sesi || []).reduce((n, s) => n + (s.menunggu || 0), 0);
        },
        gantiPerPage(j) {
            this.pes.page = 1;
            this.muatPeserta(j);
        },
        resetPeserta(j) {
            this.pesFilter = { q: '', kampus: '', sesi: '', tanggal: null };
            this.pes.page = 1;
            this.muatPeserta(j);
        },
        gantiHalPeserta(j, n) {
            if (n < 1 || n > this.pes.totalPage || n === this.pes.page) return;
            this.pes.page = n;
            // Kredensial yang telanjur dibuka ditutup saat pindah halaman.
            this.kredBuka = {};
            this.muatPeserta(j);
        },
        /** Rentang baris yang sedang tampil, mis. "21–40". */
        rentang(halaman, perHalaman, total) {
            if (! total) return '0';
            const awal = (halaman - 1) * perHalaman + 1;

            return `${awal}–${Math.min(halaman * perHalaman, total)}`;
        },
        /**
         * Nomor halaman yang ditampilkan: selalu halaman pertama & terakhir,
         * plus tetangga halaman aktif. Sisanya diringkas '…' supaya deretannya
         * tidak melebar tak terkendali saat halamannya puluhan.
         */
        nomorHalaman(aktif, total) {
            if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
            const n = new Set([1, total, aktif, aktif - 1, aktif + 1]);
            const urut = [...n].filter((x) => x >= 1 && x <= total).sort((a, b) => a - b);

            return urut.reduce((acc, x, i) => {
                if (i && x - urut[i - 1] > 1) acc.push('…');
                acc.push(x);

                return acc;
            }, []);
        },
        /** Warna pil status penjadwalan. DIANTRIKAN = token belum terbit. */
        statusKelas(status) {
            if (status === 'BERJALAN') return 'is-run';
            if (status === 'DIANTRIKAN') return 'is-queue';
            if (status === 'GAGAL') return 'is-fail';

            return 'is-idle';
        },
        kredTampak(idPeserta, jenis) {
            return !!this.kredBuka[`${idPeserta}-${jenis}`];
        },
        /**
         * Buka/tutup satu kredensial.
         *
         * Dibuka per nilai, bukan per baris — membuka token tidak ikut membuka
         * OTP-nya. Tertutup lagi otomatis setelah 30 detik supaya layar yang
         * ditinggal pergi tidak memamerkan kunci ujian.
         */
        toggleKred(idPeserta, jenis) {
            const kunci = `${idPeserta}-${jenis}`;
            const buka = !this.kredBuka[kunci];
            this.kredBuka = { ...this.kredBuka, [kunci]: buka };
            clearTimeout(this.timerKred[kunci]);
            if (buka) {
                this.timerKred[kunci] = setTimeout(() => {
                    this.kredBuka = { ...this.kredBuka, [kunci]: false };
                }, 30000);
            }
        },
        /**
         * Keterangan lengkap penanggung jawab jadwal satu kandidat.
         *
         * Bila jadwalnya pernah digeser, KEDUANYA disebut — pemasang awal dan
         * penggeser terakhir. Dengan sepuluh perekrut di satu angkatan, "siapa
         * yang terakhir menyentuh ini" adalah pertanyaan pertama saat ada
         * jadwal yang keliru.
         */
        labelOleh(row) {
            const awal = `Dijadwalkan oleh ${row.olehNama || '—'}${this.fmtWaktu(row.olehPada) ? ` · ${this.fmtWaktu(row.olehPada)}` : ''}`;
            if (!row.ubahNama) return awal;

            return `${awal}\nDigeser oleh ${row.ubahNama}${this.fmtWaktu(row.ubahPada) ? ` · ${this.fmtWaktu(row.ubahPada)}` : ''}`;
        },
        /** Tanggal ringkas untuk kepala akordion. */
        fmtWaktu(v) {
            if (!v) return null;
            const d = new Date(String(v).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return null;

            return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        /** Tanggal saja — dipakai chip sesi & rentang, di mana jam cuma mengganggu. */
        fmtTanggal(v) {
            if (!v) return '';
            const d = new Date(String(v).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '';

            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        /**
         * Buka pengubahan jadwal SATU kandidat.
         *
         * Sasarannya baris peserta, bukan penjadwalannya: yang perlu digeser
         * hampir selalu satu orang, dan menggeser di level jadwal berarti
         * memindahkan seisi angkatan demi satu orang.
         */
        bukaEdit(row, j) {
            if (row.terkunci || ! row.dapatDiubah) return;
            this.editTarget = row;
            this.editJadwal = j || null;
            this.editMulai = this.normalWaktu(row.waktuMulai);
            this.editAkhir = this.normalWaktu(row.waktuAkhir);
            this.editTampil = true;
        },
        // Samakan format waktu dari server (ISO / 'YYYY-MM-DD HH:mm:ss') ke value-format picker.
        normalWaktu(v) {
            if (!v) return '';
            return String(v).replace('T', ' ').slice(0, 19);
        },
        /** 'YYYY-MM-DD HH:mm:ss' → Date, atau null bila tak terbaca. */
        keTanggal(v) {
            if (!v) return null;
            const d = new Date(String(v).replace(' ', 'T'));

            return Number.isNaN(d.getTime()) ? null : d;
        },
        /**
         * Jendela terbalik? Hanya menilai bila KEDUANYA sudah terisi — selama
         * salah satunya kosong yang berlaku adalah "belum lengkap", bukan
         * "salah", dan peringatan merah untuk kolom yang belum disentuh cuma
         * mengganggu.
         */
        terbalik(mulai, akhir) {
            const a = this.keTanggal(mulai);
            const b = this.keTanggal(akhir);

            return !!(a && b) && b.getTime() <= a.getTime();
        },
        /**
         * Sel kalender yang harus padam di pemilih "Waktu Berakhir": seluruh
         * HARI sebelum hari mulai. Element Plus memberi Date di 00:00 tiap sel,
         * jadi pembandingnya pun dipangkas ke awal hari — kalau tidak, memilih
         * mulai 17:00 akan ikut memadamkan hari yang sama.
         */
        sebelumHari(sel, mulai) {
            if (!sel) return false;
            // HARI YANG SUDAH LEWAT tidak bisa dipilih sama sekali (masukan user
            // 2 Okt 2026) — termasuk saat waktu mulainya belum diisi.
            const k = new Date();
            if (sel.getTime() < new Date(k.getFullYear(), k.getMonth(), k.getDate()).getTime()) return true;
            const a = this.keTanggal(mulai);
            if (!a) return false;
            const awal = new Date(a.getFullYear(), a.getMonth(), a.getDate()).getTime();

            return sel.getTime() < awal;
        },
        async simpanEdit() {
            if (this.editSibuk || !this.editTarget) return;
            if (!this.editMulai || !this.editAkhir) {
                this.beritahu('Isi waktu mulai dan waktu berakhir dulu.', 'error');

                return;
            }
            if (this.editLewat) {
                this.beritahu('Waktu berakhir sudah lewat — pilih waktu yang akan datang.', 'error');

                return;
            }
            if (this.editSalah) {
                this.beritahu('Waktu berakhir harus setelah waktu mulai.', 'error');

                return;
            }
            this.editSibuk = true;
            try {
                const res = await axios.put(`/api/v1/penjadwalan/peserta/${this.editTarget.id}`, {
                    waktuMulai: this.editMulai,
                    waktuAkhir: this.editAkhir,
                }, { headers: { Accept: 'application/json' } });
                this.beritahu(res.data.message || 'Jadwal kandidat diperbarui');
                this.editTampil = false;
                // Muat ulang halaman yang sedang dilihat — dengan penyaringnya
                // utuh, supaya admin tidak terlempar kembali ke halaman satu
                // hanya karena menggeser jadwal satu orang.
                if (this.editJadwal) await this.muatPeserta(this.editJadwal);
            } catch (e) {
                this.beritahu(e.response?.data?.message || 'Gagal memperbarui jadwal', 'error');
            } finally {
                this.editSibuk = false;
            }
        },
        /**
         * Salin ke papan klip. Labelnya disebut supaya admin yakin YANG MANA
         * yang tersalin — token, OTP, dan tautan mudah tertukar saat mendikte
         * ulang ke kandidat lewat telepon.
         */
        async salin(teks, label = 'Teks') {
            if (!teks) return;
            const nilai = String(teks);
            try {
                await navigator.clipboard.writeText(nilai);
                // Token & OTP disebut apa adanya — pendek, dan justru itu yang
                // sering perlu didiktekan lewat telepon. Tautan ujian tidak:
                // panjangnya seratusan huruf, dan menempelkannya di toast cuma
                // menimbun layar sambil memamerkan token yang ada di dalamnya.
                this.beritahu(nilai.length > 40 ? `${label} disalin.` : `${label} disalin: ${nilai}`);
            } catch (e) {
                this.beritahu('Peramban menolak menyalin — salin manual dari layar.', 'error');
            }
        },
    },
};
</script>

<style scoped>
/* Nilai warna, radius, dan bayangan mengikuti template desain apa adanya —
   jangan "dirapikan" tanpa mengubah templatenya juga, karena halaman lain
   yang memakai template sekeluarga akan ikut tidak sinkron. */

.pjd { position: relative; margin: -1rem; padding: 26px 30px 44px; min-height: calc(100vh - 68px); overflow-x: clip; }
.pjd-wrap { position: relative; z-index: 2; width: 100%; }

/* Blob latar — diklip oleh overflow-x: clip di atas. */
.pjd-blob { position: absolute; border-radius: 50%; filter: blur(8px); pointer-events: none; z-index: 0; }
.pjd-blob--a { top: -120px; right: 12%; width: 440px; height: 440px; background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, .12), rgba(139, 92, 246, 0) 70%); animation: pjdFloatA 16s ease-in-out infinite; }
.pjd-blob--b { bottom: -160px; left: 6%; width: 460px; height: 460px; background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, .1), rgba(99, 102, 241, 0) 70%); animation: pjdFloatB 19s ease-in-out infinite; }
@keyframes pjdFloatA { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(28px, -22px); } }
@keyframes pjdFloatB { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-24px, 20px); } }
@keyframes pjdRise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@keyframes pjdPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, .5); } 70% { box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); } }
@keyframes pjdSpin { to { transform: rotate(360deg); } }

/* ── KEPALA HALAMAN ── */
.pjd-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; flex-wrap: wrap; margin-bottom: 18px; }
.pjd-head__l { min-width: 0; }
.pjd-head__title { display: flex; align-items: center; gap: 10px; }
.pjd-head__ico { width: 38px; height: 38px; border-radius: 12px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; box-shadow: 0 10px 24px rgba(99, 102, 241, .3); }
.pjd-head h1 { margin: 0; font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: -.025em; }
.pjd-head p { margin: 9px 0 0; font-size: 14px; color: #64748b; line-height: 1.6; max-width: 640px; }

.pjd-tabs { display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 5px; border-radius: 15px; background: rgba(255, 255, 255, .8); border: 1px solid rgba(226, 232, 240, .9); box-shadow: 0 6px 18px rgba(15, 23, 42, .05); flex: 0 0 auto; }
.pjd-tab { appearance: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 800; padding: 9px 15px; border-radius: 11px; border: none; background: transparent; color: #64748b; transition: all .16s; }
.pjd-tab:hover { color: #4f46e5; }
.pjd-tab.is-on { color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); }

/* ── TOAST ── seragam dengan Worklist Pelamar & portal kandidat. */
/* Lapis toast bersama — lihat --wca-z-toast di evo-theme.css. */
.pjd-toast { position: fixed; bottom: 24px; right: 24px; z-index: var(--wca-z-toast, 100000); display: flex; align-items: center; gap: 9px; max-width: min(520px, calc(100vw - 48px)); padding: 12px 18px; border-radius: 13px; background: #0f172a; color: #fff; font-size: 13.5px; font-weight: 700; line-height: 1.5; box-shadow: 0 18px 40px rgba(0, 0, 0, .3); }
.pjd-toast.is-err { background: #dc2626; }
.pjd-toast .bi { flex: 0 0 auto; color: #34d399; }
.pjd-toast.is-err .bi { color: #fff; }
.pjd-toast-enter-active, .pjd-toast-leave-active { transition: opacity .25s, transform .25s; }
.pjd-toast-enter-from, .pjd-toast-leave-to { opacity: 0; transform: translateY(12px); }

/* Pil hitungan — dipakai bilah peserta di wizard. */
.pjd-count { font-size: 11px; font-weight: 800; color: #8b93a7; background: #eef0f7; border-radius: 999px; padding: 4px 11px; flex: 0 0 auto; }

.pjd-lbl { display: block; font-size: 12px; font-weight: 800; letter-spacing: .04em; color: #475569; margin-bottom: 8px; }
.pjd-lbl--gap { margin-top: 14px; }
.pjd-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }

/* Kolom isian + ikon kiri */
.pjd-field { position: relative; }
.pjd-field--grow { flex: 1; min-width: 170px; }
.pjd-field__ico { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); pointer-events: none; }
.pjd-input { width: 100%; padding: 12px 14px 12px 40px; border-radius: 13px; border: 1px solid #e6e9f3; background: #f7f8fc; font-family: inherit; font-size: 13.5px; color: #334155; outline: none; transition: all .18s; }
.pjd-input::placeholder { color: #94a3b8; }
.pjd-input:focus { border-color: #a5b4fc; background: #fff; box-shadow: 0 0 0 3px rgba(99, 102, 241, .1); }

.pjd-note { display: flex; gap: 7px; align-items: flex-start; font-size: 12px; line-height: 1.55; color: #8792a6; margin: 8px 0 10px; }
.pjd-note--warn { color: #b45309; }
.pjd-opttag { float: right; color: #a2a9ba; font-size: 11.5px; margin-left: 1rem; }
.pjd-opttag.is-wait { color: #b45309; font-weight: 800; }

/* ── SASARAN SESI: LOWONGAN / PROGRAM ──
   Dua tombol berdampingan, bukan dropdown: pilihannya cuma dua dan keduanya
   harus terbaca sekaligus — yang tersembunyi di dalam dropdown tidak pernah
   dicoba orang yang tidak tahu ia ada. */
.pjd-basis { display: inline-flex; gap: 4px; padding: 4px; border-radius: 13px; background: #f4f5fb; border: 1px solid #eef0f7; }
.pjd-basis__b {
    appearance: none; font-family: inherit; display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 14px; border: none; border-radius: 10px; background: transparent; cursor: pointer;
    font-size: 12.5px; font-weight: 700; color: #64748b; transition: all .16s;
}
.pjd-basis__b:hover { color: #4338ca; }
.pjd-basis__b.is-on { background: #fff; color: #4338ca; box-shadow: 0 3px 10px rgba(15, 23, 42, .07); }
.pjd-basis__b svg { flex: 0 0 auto; }

/* Baris opsi lowongan di dalam dropdown — nama loker di atas, program & MPP
   sebagai baris kecil di bawahnya. Satu baris datar membuat "Operator Produksi"
   pada tiga program berbeda terlihat sebagai tiga baris kembar. */
.pjd-lokopt { display: inline-flex; flex-direction: column; min-width: 0; line-height: 1.35; }
.pjd-lokopt__n { font-weight: 700; color: #1e293b; }
.pjd-lokopt__m { font-size: 11px; color: #94a3b8; }

/* Keterangan lowongan terpilih — yang menerangkan sasaran sesi sebelum admin
   melangkah ke jendela waktu. */
.pjd-lokinfo { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.pjd-lokinfo__b {
    display: inline-flex; flex-direction: column; gap: 2px; min-width: 0;
    padding: 8px 12px; border-radius: 12px; background: #fbfbfe; border: 1px solid #eef0f7;
}
.pjd-lokinfo__b em { font-style: normal; font-size: 9.5px; font-weight: 800; letter-spacing: .09em; color: #a2a9ba; }
.pjd-lokinfo__b b { font-size: 12.5px; font-weight: 800; color: #334155; }

/* Centang yang sedang tertutup pencarian — kabar baik, jadi hijau, bukan
   kuning peringatan: tidak ada yang hilang, hanya tidak sedang tampil. */
.pjd-simpan {
    display: flex; align-items: center; gap: 9px; margin: 10px 0 0; padding: 9px 13px;
    border-radius: 13px; background: #f0fdf4; border: 1px solid #bbf7d0;
    font-size: 12.5px; line-height: 1.5; color: #166534;
}
.pjd-simpan svg { flex: 0 0 auto; color: #16a34a; }
.pjd-simpan span { min-width: 0; flex: 1; }
.pjd-simpan__x {
    appearance: none; font-family: inherit; flex: 0 0 auto; border: 1px solid #bbf7d0;
    background: #fff; border-radius: 9px; padding: 5px 11px; cursor: pointer;
    font-size: 11.5px; font-weight: 800; color: #15803d; transition: all .16s;
}
.pjd-simpan__x:hover { background: #dcfce7; }

/* ── GELOMBANG ── */
.pjd-gel { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; margin-top: 10px; padding: 9px 13px; border-radius: 13px; background: #fbfbfe; border: 1px solid #eef0f7; }
.pjd-gel__l { font-size: 11.5px; font-weight: 800; letter-spacing: .02em; color: #64748b; }
.pjd-gel__n {
    appearance: textfield; font-family: inherit; width: 74px; padding: 6px 9px;
    border: 1px solid #e6e9f0; border-radius: 9px; background: #fff;
    font-size: 12.5px; font-weight: 800; color: #1e293b; text-align: center;
}
.pjd-gel__n:focus { outline: none; border-color: #a5b4fc; box-shadow: 0 0 0 3px rgba(99, 102, 241, .12); }
.pjd-gel__b {
    appearance: none; font-family: inherit; display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 13px; border-radius: 10px; border: none; cursor: pointer;
    font-size: 12px; font-weight: 800; color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1); transition: all .16s;
}
.pjd-gel__b:hover:not(:disabled) { filter: brightness(1.06); }
.pjd-gel__b:disabled { background: #e6e9f0; color: #a2a9ba; cursor: not-allowed; }
.pjd-gel__e { font-size: 11.5px; color: #8792a6; margin-left: auto; }
.pjd-gel__e b { color: #4338ca; }

/* Varian KETERANGAN dari .pjd-warn — bentuk yang sama, nada yang tidak
   menuduh: daftar yang terpotong bukan kesalahan admin. */
.pjd-warn--info { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
.pjd-warn--info .bi { color: #3b82f6; }
.pjd-warn--info b { color: #1e3a8a; }

/* ── DAFTAR TAHAP/UJIAN ── */
.pjd-stages { display: flex; flex-direction: column; gap: 10px; }
.pjd-stage { appearance: none; cursor: pointer; font-family: inherit; width: 100%; display: flex; align-items: center; gap: 12px; padding: 13px 15px; border-radius: 15px; border: 1.5px solid #eef0f7; background: #fff; transition: all .18s; }
.pjd-stage:hover { border-color: #c7cdf0; }
.pjd-stage.is-on { border-color: #8b5cf6; background: linear-gradient(135deg, rgba(139, 92, 246, .06), rgba(99, 102, 241, .06)); box-shadow: 0 8px 22px rgba(99, 102, 241, .12); }
/* Rombongan alur lama — dibedakan lembut, bukan diredupkan: barisnya tetap
   harus dikerjakan, hanya asalnya yang berbeda. */
.pjd-stage.is-lawas { border-style: dashed; border-color: #dcd3f0; background: #fbfaff; }
.pjd-stage.is-lawas .pjd-stage__no { background: #ede9fe; color: #6d28d9; }
.pjd-stage__no { width: 28px; height: 28px; border-radius: 9px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: #8792a6; background: #f1f2f9; }
.pjd-stage.is-on .pjd-stage__no { color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pjd-stage__in { flex: 1; min-width: 0; text-align: left; }
.pjd-stage__nama { display: block; font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-stage__meta { display: flex; align-items: center; gap: 5px; font-size: 11.5px; color: #8792a6; margin-top: 3px; }
.pjd-stage__badge { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 4px 10px; border-radius: 8px; white-space: nowrap; background: #eef0f7; color: #94a3b8; }
.pjd-stage__badge.is-wait { background: rgba(245, 158, 11, .14); color: #b45309; }

/* ── KARTU PAKET TES ── */
.pjd-pkgs { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 12px; padding: 2px; }
.pjd-pkg { appearance: none; cursor: pointer; font-family: inherit; text-align: left; padding: 14px 15px; border-radius: 16px; border: 1.5px solid #eef0f7; background: #fff; box-shadow: 0 2px 8px rgba(15, 23, 42, .03); transition: all .18s; }
.pjd-pkg:hover { border-color: #c7cdf0; }
.pjd-pkg.is-on { border-color: #8b5cf6; background: linear-gradient(135deg, rgba(139, 92, 246, .05), rgba(99, 102, 241, .05)); box-shadow: 0 10px 26px rgba(99, 102, 241, .14); }
.pjd-pkg__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; width: 100%; }
.pjd-pkg__nama { font-size: 13.5px; font-weight: 800; color: #1e293b; text-align: left; line-height: 1.3; }
.pjd-pkg__check { width: 22px; height: 22px; border-radius: 7px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pjd-pkg__kode { display: block; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 10.5px; font-weight: 600; color: #a2a9ba; margin-top: 6px; }
.pjd-pkg__chips { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 10px; }
.pjd-ptag { font-size: 10px; font-weight: 700; color: #4f46e5; background: rgba(99, 102, 241, .1); border-radius: 6px; padding: 3px 8px; }
.pjd-pkg__foot { display: flex; align-items: center; gap: 12px; margin-top: 11px; padding-top: 10px; border-top: 1px solid #f1f2f9; font-size: 11px; color: #8792a6; }
.pjd-pkg__foot > span { display: inline-flex; align-items: center; gap: 5px; }

.pjd-times { display: grid; grid-template-columns: 1fr; gap: 14px; }

.pjd-lock { display: flex; gap: 12px; padding: 13px 15px; border-radius: 15px; background: linear-gradient(135deg, #fffdf7, #fff8ec); border: 1px solid #f2e4c4; font-size: 12.5px; line-height: 1.55; color: #8a6d29; }
.pjd-lock b { color: #92660a; }

/* Jendela terbalik — merah, bukan kuning seperti pjd-lock: yang satu
   keterangan, yang ini penghalang. Warnanya harus membedakan keduanya. */
.pjd-warn { display: flex; gap: 11px; align-items: flex-start; margin-top: 12px; padding: 11px 14px; border-radius: 14px; background: #fef2f2; border: 1px solid #fecaca; font-size: 12.5px; line-height: 1.55; color: #9f1239; }
.pjd-warn--gap { margin-top: 14px; }
.pjd-warn .bi { flex: none; margin-top: 1px; font-size: 15px; color: #e11d48; }
.pjd-warn b { display: block; color: #881337; }
.pjd-lock__ico { width: 32px; height: 32px; border-radius: 10px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #fff; }

.pjd-gen { appearance: none; font-family: inherit; width: 100%; font-size: 14px; font-weight: 800; padding: 14px; border-radius: 14px; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 9px; cursor: pointer; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 14px 32px rgba(99, 102, 241, .34); transition: all .18s; }
.pjd-gen:hover:not(:disabled) { filter: brightness(1.05); }
.pjd-gen:disabled { cursor: not-allowed; color: #a5abc9; background: #eef0f7; box-shadow: none; }
.pjd-spin { display: inline-block; animation: pjdSpin 1s linear infinite; }

/* ── PENYARING PESERTA DI DALAM PANEL RINCIAN ─────────────────────────── */
/* Lebarnya dari .pjd-fbox--lg yang membungkusnya; el-date-picker sendiri
   dipaksa 100% oleh tema, sama seperti el-select. */
.pjd-fdate { width: 100%; }

/* Nama kampus bisa sangat panjang ("Adventist International Institute of…").
   Dipotong dengan elipsis, lengkapnya tetap terbaca lewat title. */
.pjd-tbl__kampus { font-size: 12px; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pjd-tbl__sesi { display: block; margin-top: 3px; font-size: 10px; font-weight: 700; letter-spacing: .02em; color: #6366f1; }

/* ── KANDIDAT ── */
.pjd-candbar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
/* Peserta menempel di bawah jendela waktu pada langkah yang sama — beri jarak
   pemisah supaya dua kelompok isian tidak terbaca menyatu. */
.pjd-candbar--gap { margin-top: 6px; padding-top: 16px; border-top: 1px solid #eef0f7; }
.pjd-all { display: inline-flex; align-items: center; gap: 9px; appearance: none; border: none; background: transparent; cursor: pointer; font-family: inherit; padding: 0; font-size: 13px; font-weight: 700; color: #475569; }
.pjd-box { width: 20px; height: 20px; border-radius: 6px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; border: 1.5px solid #cbd2e0; background: #fff; transition: all .16s; }
.pjd-box.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pjd-box.is-half i { width: 9px; height: 2.5px; border-radius: 2px; background: #8b5cf6; display: block; }

/* flex:1 → daftar kandidat memakan sisa tinggi kartu, sehingga kartu ini
   berujung sama tinggi dengan Konfigurasi Tes di sebelahnya. */
/* Di dalam wizard, isi modal sendiri yang menggulir — daftar kandidat tidak
   perlu jendela gulir kedua di dalamnya. Dua bilah gulir bersarang membuat roda
   mouse menggerakkan yang salah, dan bilah langkah di atas sudah menempel. */
.pjd-cands { display: flex; flex-direction: column; gap: 10px; flex: 1; min-height: 220px; padding: 2px; }
.pjd-cand { appearance: none; cursor: pointer; font-family: inherit; width: 100%; display: flex; align-items: flex-start; gap: 12px; padding: 14px 15px; border-radius: 16px; border: 1.5px solid #eef0f7; background: #fff; box-shadow: 0 2px 8px rgba(15, 23, 42, .03); transition: all .18s; }
.pjd-cand:hover { border-color: #c7cdf0; }
.pjd-cand.is-on { border-color: #8b5cf6; background: linear-gradient(135deg, rgba(139, 92, 246, .05), rgba(99, 102, 241, .05)); box-shadow: 0 8px 22px rgba(99, 102, 241, .12); }
.pjd-ava { width: 38px; height: 38px; border-radius: 12px; background: linear-gradient(135deg, #a5b4fc, #818cf8); color: #fff; font-size: 14px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.pjd-ava.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.pjd-ava--sm { width: 30px; height: 30px; border-radius: 10px; font-size: 11.5px; }
.pjd-cand__in { flex: 1; min-width: 0; text-align: left; }
.pjd-cand__nama { display: block; font-size: 14px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-cand__meta { display: block; font-size: 11.5px; color: #8792a6; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-cand__meta .pjd-mono { color: #a2a9ba; }
.pjd-cand__flow { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; color: #7c74b0; background: rgba(139, 92, 246, .1); border-radius: 6px; padding: 3px 8px; margin-top: 7px; }

/* ── KEADAAN KOSONG ── */
.pjd-empty { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 36px 18px; text-align: center; }
.pjd-empty--sm { padding: 26px 18px; }
.pjd-empty--span { grid-column: 1 / -1; }
.pjd-empty__ico { width: 56px; height: 56px; border-radius: 18px; background: linear-gradient(135deg, #f4f2ff, #eef2ff); border: 1px solid #e7e3fb; display: flex; align-items: center; justify-content: center; color: #a5b4fc; flex: 0 0 auto; }
.pjd-empty__ico--lg { width: 56px; height: 56px; }
.pjd-empty__ico--xl { width: 64px; height: 64px; border-radius: 20px; }
.pjd-empty b { font-size: 14px; font-weight: 800; color: #475569; }
.pjd-empty p { margin: 0; font-size: 12.5px; line-height: 1.55; color: #8b93a7; max-width: 320px; }

/* ── FILTER DAFTAR ── */
/* Kotak penyaring. LEBARNYA di sini, bukan di el-select-nya: tema EVO
   memaksa `.el-select { width: 100% !important }` demi isian di dalam modal,
   dan aturan ber-!important itu menang atas apa pun yang ditulis di sini.
   Yang dilebarkan bungkusnya — selectnya boleh tetap 100%. */
.pjd-fbox { flex: 0 1 220px; min-width: 150px; }
.pjd-fbox--sm { flex: 0 1 168px; min-width: 130px; }
/* Rentang tanggal butuh dua kolom + pemisah di dalam satu kotak. */
.pjd-fbox--lg { flex: 0 1 262px; min-width: 210px; }
.pjd-reset { appearance: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e6e9f3; background: #fff; padding: 10px 13px; border-radius: 12px; cursor: pointer; font-family: inherit; font-size: 12.5px; font-weight: 800; color: #64748b; transition: all .16s; }
.pjd-reset:hover { color: #dc2626; border-color: #f4d0d0; background: #fef2f2; }

/* ── RANGKA MUAT ── */
.pjd-skel { display: flex; flex-direction: column; gap: 10px; padding: 2px; }
.pjd-skel__row, .pjd-skel__card { display: block; border-radius: 16px; background: linear-gradient(90deg, #f1f2f9 25%, #f8f9fc 37%, #f1f2f9 63%); background-size: 400% 100%; animation: pjdShimmer 1.3s ease-in-out infinite; }
.pjd-skel__row { height: 74px; }
.pjd-skel__card { height: 112px; border-radius: 18px; }
.pjd-skel--in { padding: 14px 16px; }
@keyframes pjdShimmer { from { background-position: 100% 50%; } to { background-position: 0 50%; } }

/* COBA LAGI — hanya muncul saat GAGAL, jadi nadanya boleh tegas: inilah satu
   satunya hal yang perlu dilakukan pada baris itu. */
.pjd-retry { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #fca5a5; background: #fff1f2; color: #b91c1c; font-size: 12px; font-weight: 800; border-radius: 9px; padding: 7px 12px; cursor: pointer; transition: all .15s; }
.pjd-retry:hover:not(:disabled) { background: #fee2e2; border-color: #f87171; }
.pjd-retry:disabled { opacity: .6; cursor: not-allowed; }

/* Penjadwal — kartu orang, bukan chip teks. Melekat pada baris kandidat. */
.pjd-by { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
.pjd-by__ava { width: 28px; height: 28px; border-radius: 9px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #a5b4fc, #818cf8); color: #fff; font-size: 10.5px; font-weight: 800; letter-spacing: .02em; }
.pjd-by__in { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
.pjd-by__in b { font-size: 12px; font-weight: 800; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-by__in i { font-style: normal; font-size: 10.5px; color: #8792a6; }
.pjd-by__in u { text-decoration: none; font-size: 9px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #b45309; margin-top: 2px; }
.pjd-status { display: inline-flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 800; border-radius: 8px; padding: 5px 11px; }
.pjd-status.is-run { color: #059669; background: rgba(16, 185, 129, .12); }
.pjd-status.is-idle { color: #8b93a7; background: #eef0f7; }
.pjd-status.is-queue { color: #b45309; background: rgba(245, 158, 11, .14); }
.pjd-status.is-queue .pjd-dot { background: #f59e0b; }
.pjd-status.is-fail { color: #b91c1c; background: rgba(239, 68, 68, .1); }
.pjd-dot { width: 7px; height: 7px; border-radius: 50%; background: #10b981; animation: pjdPulse 2s infinite; }
.pjd-ibtn { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; transition: all .16s; }
.pjd-ibtn:hover { color: #4f46e5; border-color: #c7cdf0; }
.pjd-ibtn--del { border-color: #f4d0d0; color: #dc2626; }
.pjd-ibtn--del:hover { background: #fef2f2; color: #b91c1c; border-color: #f4d0d0; }
/* Tautan ujian — sewarna kredensial di baris yang sama, karena itulah
   isinya: token & OTP orang ini, dibungkus jadi satu alamat. */
.pjd-ibtn--link { border-color: #d9def0; color: #6366f1; background: #f7f8fc; }
.pjd-ibtn--link:hover { background: #6366f1; color: #fff; border-color: #6366f1; }

/* Rupa tahap di bilah alur menurut stepKelas(): tahap yang ujiannya dijadwalkan
   di program ini (is-active), tahap ujian online lain di alur yang sama
   (is-hcl), dan tahap yang ditangani tim (is-todo, tanpa penanda). Nomornya
   ikut berwarna supaya terbaca sekilas tanpa membaca judulnya dulu. */
.pjd-tahap.is-active:not(.is-on) .pjd-tahap__no { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.pjd-tahap.is-hcl:not(.is-on) .pjd-tahap__no { background: linear-gradient(135deg, #34d399, #10b981); color: #fff; }

/* Tabel peserta — grid, bukan <table>, supaya kolomnya persis desain. */
.pjd-tbl { display: none; overflow-x: auto; }
/* Tujuh kolom sejak KAMPUS ikut tampil — lihat catatan di templatenya. */
/* Kolom AKSI kini memuat DUA tombol — salin tautan & ubah jadwal — jadi
   jatahnya dinaikkan; sisanya dipangkas seimbang agar lebar totalnya tetap. */
.pjd-tbl__head, .pjd-tbl__row { display: grid; grid-template-columns: 1.6fr 1.1fr .95fr .85fr 1.15fr 1.25fr .8fr; gap: 12px; align-items: center; }
.pjd-tbl__head { padding: 11px 18px; background: #f7f8fc; border-bottom: 1px solid #eef0f7; font-size: 10px; font-weight: 800; letter-spacing: .08em; color: #94a3b8; }
.pjd-tbl__row { padding: 14px 18px; border-bottom: 1px solid #f4f5fb; transition: background .14s; }
.pjd-tbl__row:hover { background: #fbfbfe; }
.pjd-tbl .c { text-align: center; }
.pjd-tbl .r { text-align: right; }
.pjd-tbl__row .r { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
.pjd-tbl__nama { display: flex; align-items: center; gap: 10px; min-width: 0; }
.pjd-tbl__who { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
.pjd-tbl__who b { font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-tbl__who em { font-style: normal; font-size: 11.5px; color: #8792a6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-tbl__pos { font-size: 12.5px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-tbl__sub { font-size: 12.5px; color: #94a3b8; }
.pjd-tbl__win { display: flex; flex-direction: column; font-size: 11.5px; color: #475569; line-height: 1.4; }
.pjd-tbl__win2 { color: #8792a6; }
.pjd-tbl__win em { font-style: normal; font-size: 9.5px; font-weight: 800; letter-spacing: .06em; color: #b45309; text-transform: uppercase; margin-top: 3px; }
.pjd-tbl__tok { display: flex; align-items: center; justify-content: flex-end; gap: 8px; min-width: 0; }
.pjd-tok { font-size: 12px; font-weight: 700; color: #4f46e5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-pill { display: inline-block; font-size: 9.5px; font-weight: 800; letter-spacing: .06em; border-radius: 7px; padding: 4px 9px; }
.pjd-pill.is-ok { color: #059669; background: rgba(16, 185, 129, .12); }
.pjd-pill.is-bad { color: #b91c1c; background: rgba(239, 68, 68, .1); }
.pjd-pill.is-wait { color: #8b93a7; background: #eef0f7; }

/* Kredensial — seluruh pilnya jadi tombol salin: sasaran kliknya lebar, dan
   nilainya tetap terbaca untuk didikte lewat telepon. */
.pjd-credwrap { display: inline-flex; align-items: center; gap: 4px; }
.pjd-eye { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 26px; height: 26px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #94a3b8; flex: 0 0 auto; transition: all .16s; }
.pjd-eye:hover { color: #4f46e5; border-color: #c7cdf0; }
.pjd-cred { appearance: none; display: inline-flex; align-items: center; gap: 7px; border: 1px solid #d9def0; background: #f7f8fc; padding: 5px 10px; border-radius: 9px; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 700; color: #4f46e5; transition: all .16s; letter-spacing: .04em; }
.pjd-cred:hover { background: #eef0fe; border-color: #a5b4fc; }
.pjd-cred:active { transform: scale(.97); }
.pjd-cred b { font-size: 9px; font-weight: 800; letter-spacing: .08em; color: #a2a9ba; }
.pjd-cred--otp { color: #b45309; border-color: #f2e4c4; background: #fffdf7; }
.pjd-cred--otp:hover { background: #fff8ec; border-color: #f59e0b; }
/* Kartu layar sempit: tautan berdampingan dengan token & OTP milik orang
   yang sama, bukan menggantung sendirian di kaki daftar. */
.pjd-cred--link { font-size: 9.5px; font-weight: 800; letter-spacing: .08em; gap: 5px; }

/* ── PAGINASI ── */
.pjd-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding-top: 14px; border-top: 1px solid #f1f2f9; }
.pjd-pager--sm { padding-top: 12px; }
.pjd-pager__info { font-size: 12px; font-weight: 700; color: #8792a6; }
.pjd-pager__btns { display: flex; gap: 6px; }
.pjd-pg { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; transition: all .16s; }
.pjd-pg:hover:not(:disabled) { color: #4f46e5; border-color: #c7cdf0; }
.pjd-pg:disabled { opacity: .4; cursor: not-allowed; }
.pjd-pg.is-on { color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); border-color: transparent; box-shadow: 0 6px 16px rgba(99, 102, 241, .28); }
.pjd-pg--gap { border: none; background: transparent; color: #a2a9ba; cursor: default; width: 20px; }
.pjd-pg--sm { width: 28px; height: 28px; border-radius: 9px; }
.pjd-pager--in { padding: 12px 18px 14px; border-top: 1px solid #f4f5fb; }

/* Paginasi SESI — milik satu kartu program, bukan daftar programnya. */
.pjd-pager--sesi { padding: 12px 18px 16px; background: #fbfbfe; border-top: 1px dashed #e9ebf5; }
/* Di panel kiri yang selebar satu kolom, deretan nomor lengkap membungkus
   jadi tiga baris. Yang tersisa cuma yang benar-benar dipakai di sana:
   maju, mundur, dan "di halaman berapa saya sekarang". */
.pjd-pager--pane { padding: 10px 13px; border-top: 1px solid #eef0f7; background: #fbfbfe; gap: 8px; }
.pjd-pager--pane .pjd-pager__info { font-size: 11px; }
.pjd-pager__nomor { font-size: 11.5px; font-weight: 800; color: #4338ca; min-width: 40px; text-align: center; letter-spacing: .02em; }

/* Kartu peserta — dipakai di layar sempit. */
.pjd-rows { display: flex; flex-direction: column; gap: 10px; padding: 14px 15px; }
.pjd-row { border: 1px solid #eef0f7; border-radius: 14px; background: #fbfbfe; padding: 14px 15px; }
.pjd-row__top { display: flex; align-items: center; gap: 11px; }
.pjd-row__in { min-width: 0; flex: 1; }
.pjd-row__nama { font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-row__pos { font-size: 11.5px; color: #8792a6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-row__bot { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 12px; padding-top: 11px; border-top: 1px solid #eef0f7; }

/* ── MODAL UBAH JADWAL KANDIDAT ── */
.pjd-edit { display: flex; flex-direction: column; }
.pjd-edit__who { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 14px; background: #f7f8fc; border: 1px solid #eef0f7; margin-bottom: 14px; }
.pjd-edit__who b { display: block; font-size: 14px; font-weight: 800; color: #1e293b; }
.pjd-edit__who span:not(.pjd-ava) { display: block; font-size: 11.5px; color: #8792a6; margin-top: 2px; }
.pjd-edit__note { display: flex; gap: .5rem; margin: 0 0 1rem; padding: .6rem .75rem; border-radius: 10px; background: rgba(245, 158, 11, .1); border: 1px solid rgba(245, 158, 11, .24); font-size: .78rem; line-height: 1.5; color: #92660a; }
.pjd-edit__note .bi { flex: 0 0 auto; margin-top: 1px; color: #d97706; }
.pjd-ibtn:disabled { opacity: .35; cursor: not-allowed; }
.pjd-ibtn:disabled:hover { color: #64748b; border-color: #e6e9f3; }

/* ── ELEMENT PLUS: disamakan dengan kolom isian di atas ── */
.pjd-select, .pjd-date { width: 100%; }
.pjd :deep(.el-select__wrapper),
.pjd :deep(.el-input__wrapper) { border-radius: 13px; background: #fff; box-shadow: 0 0 0 1px #e6e9f3 inset; padding: 6px 15px; min-height: 46px; transition: all .18s; }
.pjd :deep(.el-select__wrapper.is-focused),
.pjd :deep(.el-input__wrapper.is-focus) { box-shadow: 0 0 0 1px #a5b4fc inset, 0 0 0 3px rgba(99, 102, 241, .1); }
.pjd :deep(.el-select__placeholder),
.pjd :deep(.el-input__inner) { font-size: 13.5px; font-weight: 700; color: #1e293b; }
.pjd :deep(.el-select__placeholder.is-transparent),
.pjd :deep(.el-input__inner::placeholder) { font-weight: 500; color: #94a3b8; }
.pjd :deep(.el-input__prefix) { color: #8b5cf6; }
.pjd :deep(.el-loading-mask) { background: rgba(255, 255, 255, .7); border-radius: 14px; }

/* ══════════════════════════════════════════════════════════════════════════
   DAFTAR PENJADWALAN — bilah alat, kartu program, dua panel
   ══════════════════════════════════════════════════════════════════════════ */
.pjd-toolbar { background: #fff; border: 1px solid #e7e3fb; border-radius: 16px; box-shadow: 0 6px 18px rgba(99, 102, 241, .06); padding: 12px 14px; margin-bottom: 14px; display: flex; flex-direction: column; gap: 10px; }
.pjd-toolbar__row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pjd-toolbar__row--sub { padding-top: 10px; border-top: 1px solid #f4f5fb; }
.pjd-toolbar__hasil { margin-left: auto; font-size: 12px; font-weight: 700; color: #8b93a7; flex: 0 0 auto; }
.pjd-chips { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; flex: 0 0 auto; }
.pjd-chip { appearance: none; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 800; padding: 9px 14px; border-radius: 11px; border: 1px solid #e7e3fb; background: #fff; color: #64748b; transition: all .16s; }
.pjd-chip:hover { border-color: #c7d2fe; color: #4f46e5; }
.pjd-chip.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 8px 18px rgba(99, 102, 241, .26); }
.pjd-views { display: inline-flex; align-items: center; gap: 3px; padding: 4px; border-radius: 12px; background: #fff; border: 1px solid #e7e3fb; flex: 0 0 auto; }
.pjd-view { appearance: none; border: none; cursor: pointer; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: transparent; color: #94a3b8; transition: all .16s; }
.pjd-view:hover { color: #4f46e5; background: #f4f2ff; }
.pjd-view.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }

.pjd-progs { display: flex; flex-direction: column; gap: 14px; }
.pjd-prog { background: #fff; border: 1px solid #e7e3fb; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 16px rgba(15, 23, 42, .04); transition: border-color .2s, box-shadow .2s; animation: pjdCardIn .34s ease both; }
@keyframes pjdCardIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.pjd-prog.is-open { border-color: #c7cdf0; box-shadow: 0 16px 40px rgba(99, 102, 241, .11); }
.pjd-prog.is-focus { border-color: #a5b4fc; box-shadow: 0 0 0 3px rgba(99, 102, 241, .18); }
.pjd-prog__head { display: flex; align-items: flex-start; gap: 13px; padding: 16px 18px; }
.pjd-prog__caret { appearance: none; cursor: pointer; flex: 0 0 auto; width: 30px; height: 30px; border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 1px solid #e6e9f3; background: #f7f8fc; color: #8792a6; transition: all .18s; }
.pjd-prog__caret svg { transition: transform .26s; }
.pjd-prog__caret.is-open { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.pjd-prog__caret.is-open svg { transform: rotate(90deg); }
.pjd-prog__id { flex: 1; min-width: 0; }
.pjd-prog__ttlbtn { appearance: none; border: none; background: transparent; cursor: pointer; padding: 0; text-align: left; width: 100%; display: flex; align-items: center; gap: 9px; flex-wrap: wrap; font-family: inherit; }
.pjd-prog__tag { flex: 0 0 auto; font-size: 9.5px; font-weight: 800; letter-spacing: .08em; padding: 4px 9px; border-radius: 7px; }
.pjd-prog__tag.is-mt { background: rgba(245, 158, 11, .14); color: #b45309; }
.pjd-prog__tag.is-rek { background: rgba(99, 102, 241, .12); color: #4f46e5; }
.pjd-prog__nama { min-width: 0; font-size: 16px; font-weight: 800; color: #0f172a; letter-spacing: -.015em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-prog__metas { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-top: 8px; }
.pjd-prog__metas > span { display: inline-flex; align-items: center; gap: 6px; min-width: 0; font-size: 11.5px; color: #64748b; }
.pjd-prog__metas > span svg { flex: 0 0 auto; color: #8b5cf6; }
.pjd-prog__metas b { color: #334155; font-weight: 700; }
.pjd-prog__metas > span.is-warn { color: #b45309; }
.pjd-prog__metas > span.is-warn svg, .pjd-prog__metas > span.is-warn b { color: #b45309; }
.pjd-prog__note { display: flex; align-items: flex-start; gap: 8px; margin-top: 9px; padding: 8px 11px; border-radius: 11px; background: #f8fafc; border: 1px solid #eef0f7; font-size: 11.5px; line-height: 1.5; color: #64748b; }
.pjd-prog__note.is-fail { background: #fef2f2; border-color: #fecaca; color: #9f1239; }
.pjd-prog__note .bi { flex: none; margin-top: 1px; }
.pjd-prog__body { border-top: 1px solid #eef0f7; animation: pjdAccIn .3s cubic-bezier(.22, 1, .36, 1) both; }
@keyframes pjdAccIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

/* ── BILAH ALUR SELEKSI ── */
.pjd-alur { padding: 15px 18px 5px; }
.pjd-alur__head { display: flex; align-items: center; gap: 8px; margin-bottom: 11px; flex-wrap: wrap; }
.pjd-alur__lbl { font-size: 10.5px; font-weight: 800; letter-spacing: .13em; color: #4338ca; flex: 0 0 auto; }
.pjd-alur__nama { min-width: 0; font-size: 11.5px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-alur__rail { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 8px; }
.pjd-tahap { appearance: none; cursor: pointer; font-family: inherit; flex: 0 0 auto; display: inline-flex; align-items: center; gap: 9px; padding: 9px 13px; border-radius: 12px; border: 1px solid #e7e3fb; background: #fff; color: #334155; text-align: left; transition: all .16s; }
.pjd-tahap:hover { border-color: #c7d2fe; }
.pjd-tahap.is-kosong { color: #a2a9ba; }
.pjd-tahap.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 10px 22px rgba(99, 102, 241, .3); }
.pjd-tahap__no { width: 20px; height: 20px; border-radius: 6px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 800; background: #f1f5f9; color: #8792a6; }
.pjd-tahap.is-on .pjd-tahap__no { background: rgba(255, 255, 255, .25); color: #fff; }
.pjd-tahap__in { display: flex; flex-direction: column; min-width: 0; }
.pjd-tahap__in b { font-size: 12px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }
.pjd-tahap__in em { font-size: 9.5px; font-style: normal; font-weight: 700; margin-top: 1px; white-space: nowrap; color: #a2a9ba; }
.pjd-tahap.is-on .pjd-tahap__in em { color: rgba(255, 255, 255, .8); }

.pjd-ulangbar { display: flex; flex-wrap: wrap; gap: 8px; padding: 4px 18px 12px; }

/* ── TAMPILAN PETAK ── */
.pjd-petak { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(210px, 100%), 1fr)); gap: 11px; padding: 14px 18px 18px; background: #fbfbfe; border-top: 1px solid #eef0f7; }
.pjd-tile { appearance: none; cursor: pointer; font-family: inherit; display: flex; flex-direction: column; align-items: flex-start; text-align: left; padding: 14px; border-radius: 14px; border: 1px solid #e7e3fb; background: #fff; box-shadow: 0 2px 10px rgba(15, 23, 42, .04); transition: all .16s; min-width: 0; }
.pjd-tile:hover { transform: translateY(-2px); border-color: #a5b4fc; box-shadow: 0 12px 28px rgba(99, 102, 241, .14); }
.pjd-tile.is-on { border-color: #6366f1; background: #f6f5ff; box-shadow: 0 12px 28px rgba(99, 102, 241, .16); }
.pjd-tile__top { display: flex; align-items: center; justify-content: space-between; gap: 9px; width: 100%; }
.pjd-tile__ico { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #eef0f7; color: #94a3b8; flex: 0 0 auto; }
.pjd-tile.is-on .pjd-tile__ico { background: #e0e7ff; border-color: #c7d2fe; color: #4f46e5; }
.pjd-tile__kode { flex: 0 0 auto; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 10.5px; font-weight: 700; padding: 3px 8px; border-radius: 6px; background: #f1f5f9; color: #64748b; }
.pjd-tile.is-on .pjd-tile__kode { background: #e0e7ff; color: #4338ca; }
.pjd-tile__nama { display: block; width: 100%; font-size: 13px; font-weight: 800; color: #1e293b; margin-top: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-tile__meta { display: flex; align-items: center; gap: 8px; margin-top: 6px; width: 100%; min-width: 0; font-size: 11px; color: #8792a6; }
.pjd-tile__meta > span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-tile__meta i { width: 3px; height: 3px; border-radius: 50%; background: #cbd5e1; flex: 0 0 auto; }
.pjd-tile__wait { margin-top: 8px; font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 6px; background: rgba(245, 158, 11, .14); color: #b45309; }

/* ── DUA PANEL ── */
.pjd-split { display: grid; grid-template-columns: 306px minmax(0, 1fr); border-top: 1px solid #eef0f7; }
.pjd-sesipane { display: flex; flex-direction: column; min-width: 0; background: #fbfbfe; border-right: 1px solid #eef0f7; }
.pjd-sesipane__head { padding: 14px 14px 11px; border-bottom: 1px solid #eef0f7; }
.pjd-sesipane__top { display: flex; align-items: center; justify-content: space-between; gap: 9px; margin-bottom: 10px; }
.pjd-sesipane__lbl { font-size: 10.5px; font-weight: 800; letter-spacing: .13em; color: #4338ca; }
.pjd-sesipane__n { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 3px 9px; border-radius: 7px; background: #f4f2ff; color: #6d28d9; }
.pjd-sesipane__list { flex: 1; min-height: 0; max-height: 520px; overflow-y: auto; padding: 8px 0 10px; }
.pjd-sesipane__kosong { padding: 26px 16px; text-align: center; font-size: 12px; color: #a2a9ba; }
.pjd-sesigrup__head { display: flex; align-items: center; gap: 8px; padding: 9px 14px 8px; position: sticky; top: 0; background: #fbfbfe; z-index: 1; }
.pjd-sesigrup__dot { width: 7px; height: 7px; border-radius: 50%; flex: 0 0 auto; background: #cbd5e1; }
.pjd-sesigrup__dot.is-kini { background: #10b981; }
.pjd-sesigrup__lbl { font-size: 11px; font-weight: 800; letter-spacing: .06em; color: #475569; white-space: nowrap; }
.pjd-sesigrup__garis { flex: 1; height: 1px; background: #eef0f7; }
.pjd-sesigrup__n { font-size: 10.5px; font-weight: 700; color: #a2a9ba; flex: 0 0 auto; }
.pjd-sesirow { position: relative; overflow: hidden; appearance: none; cursor: pointer; font-family: inherit; display: flex; flex-direction: column; width: calc(100% - 24px); margin: 0 12px 7px; padding: 11px 12px 11px 15px; border-radius: 12px; text-align: left; border: 1px solid #e7e3fb; background: #fff; transition: all .16s; }
.pjd-sesirow:hover { border-color: #c7d2fe; }
.pjd-sesirow.is-on { border-color: #a5b4fc; background: #f6f5ff; box-shadow: 0 8px 20px rgba(99, 102, 241, .13); }
.pjd-sesirow__rel { position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #eef0f7; }
.pjd-sesirow.is-on .pjd-sesirow__rel { background: linear-gradient(180deg, #8b5cf6, #6366f1); }
.pjd-sesirow.is-fail .pjd-sesirow__rel { background: #f43f5e; }
.pjd-sesirow__top { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; }
.pjd-sesirow__kode { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; background: #f1f5f9; color: #64748b; }
.pjd-sesirow.is-on .pjd-sesirow__kode { background: #e0e7ff; color: #4338ca; }
.pjd-sesirow__n { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 3px 8px; border-radius: 6px; background: #f8fafc; color: #94a3b8; }
.pjd-sesirow.is-on .pjd-sesirow__n { background: #ede9fe; color: #6d28d9; }
.pjd-sesirow__nama { display: block; font-size: 12.5px; font-weight: 700; color: #1e293b; margin-top: 7px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 100%; }
.pjd-sesirow__jam { display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 11px; color: #8792a6; width: 100%; }
.pjd-sesirow__jam svg { flex: 0 0 auto; }
.pjd-sesirow__wait { margin-top: 6px; font-size: 9.5px; font-weight: 800; padding: 2px 7px; border-radius: 6px; background: rgba(245, 158, 11, .14); color: #b45309; align-self: flex-start; }

.pjd-detail { display: flex; flex-direction: column; min-width: 0; background: #fff; }
.pjd-detail__head { padding: 17px 18px 15px; background: linear-gradient(135deg, #f6f4ff 0%, #f2f5ff 55%, #eef4ff 100%); border-bottom: 1px solid #eef0f7; }
.pjd-detail__top { display: flex; align-items: flex-start; gap: 13px; flex-wrap: wrap; }
.pjd-detail__ico { width: 44px; height: 44px; border-radius: 13px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 10px 24px rgba(99, 102, 241, .3); }
.pjd-detail__tags { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.pjd-detail__kode { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 8px; background: #e0e7ff; color: #4338ca; }
.pjd-detail__nama { font-size: 17px; font-weight: 800; color: #1e1b4b; letter-spacing: -.02em; margin-top: 7px; text-wrap: pretty; }
.pjd-detail__paket { font-size: 12px; color: #6b6597; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-detail__ulang { appearance: none; cursor: pointer; font-family: inherit; flex: 0 0 auto; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 800; color: #fff; border: none; padding: 9px 14px; border-radius: 11px; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 8px 18px rgba(99, 102, 241, .26); }
.pjd-detail__ulang:disabled { cursor: not-allowed; background: #eef0f7; color: #a5abc9; box-shadow: none; }
.pjd-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(168px, 100%), 1fr)); gap: 9px; margin-top: 14px; }
.pjd-stat { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; background: rgba(255, 255, 255, .86); border: 1px solid rgba(226, 232, 240, .9); min-width: 0; }
.pjd-stat__ico { width: 32px; height: 32px; border-radius: 9px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: #eef2ff; color: #4f46e5; }
.pjd-stat.is-gold .pjd-stat__ico { background: rgba(245, 158, 11, .14); color: #d97706; }
.pjd-stat__in { display: flex; flex-direction: column; min-width: 0; }
.pjd-stat__in em { font-size: 9.5px; font-style: normal; font-weight: 800; letter-spacing: .1em; color: #a2a9ba; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-stat__in b { font-size: 13px; font-weight: 800; color: #1e293b; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-stat__in i { font-size: 10.5px; font-style: normal; color: #8792a6; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-ptool { display: flex; align-items: center; gap: 9px; padding: 13px 16px; border-bottom: 1px solid #eef0f7; flex-wrap: wrap; }
.pjd-reveal { appearance: none; cursor: pointer; font-family: inherit; flex: 0 0 auto; display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 800; padding: 9px 14px; border-radius: 11px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; transition: all .16s; }
.pjd-reveal:hover { border-color: #c7d2fe; color: #4f46e5; }
.pjd-reveal.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.pjd-detail__pilih { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 60px 24px; text-align: center; min-height: 280px; }
.pjd-detail__pilihico { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; background: #f4f2ff; border: 1px solid #e7e3fb; color: #a5b4fc; }
.pjd-detail__pilih b { font-size: 14.5px; font-weight: 800; color: #334155; margin-top: 8px; }
.pjd-detail__pilih > span:last-child { font-size: 12.5px; color: #8792a6; max-width: 280px; line-height: 1.55; }
.pjd-empty--kartu { background: #fff; border: 1px dashed #d9def0; border-radius: 18px; padding: 44px 22px; }
/* Kartu berdiri sendiri, jadi ia butuh empuk di keempat sisinya. Tanpa
   padding sisi, keterangannya menempel di garis tepi dan bilahnya terbaca
   seperti kotak kosong yang gagal terisi. */
.pjd-pager--luar { margin-top: 4px; padding: 13px 17px; background: #fff; border: 1px solid #e7e3fb; border-radius: 16px; }
.pjd-field--sm .pjd-input { height: 36px; font-size: 12.5px; }

/* ══════════════════════════════════════════════════════════════════════════
   WIZARD BUAT SESI — di dalam AdminModal (ukuran XL)
   ══════════════════════════════════════════════════════════════════════════ */
.pjd-head__r { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 0 0 auto; }
.pjd-buat { appearance: none; border: none; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 800; color: #fff; padding: 12px 20px; border-radius: 14px; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 12px 28px rgba(99, 102, 241, .3); transition: transform .16s, box-shadow .16s; flex: 0 0 auto; }
.pjd-buat:hover { transform: translateY(-1px); box-shadow: 0 16px 34px rgba(99, 102, 241, .4); }

/* Bilah langkah — menempel di puncak isi modal lewat slot `sticky`. Tidak ikut
   tergulung: di langkah Peserta isinya bisa ratusan baris, dan pengingat "ini
   langkah keberapa dari lima" justru paling dibutuhkan di sana. */
.pjd-wiz__rail { display: flex; gap: 7px; overflow-x: auto; padding: 14px var(--wca-modal-pad, 1.35rem); background: linear-gradient(135deg, #f6f4ff 0%, #f2f5ff 55%, #eef4ff 100%); border-bottom: 1px solid #e4e7f5; }
.pjd-wiz__step { appearance: none; font-family: inherit; cursor: pointer; flex: 0 0 auto; display: inline-flex; align-items: center; gap: 9px; padding: 9px 13px; border-radius: 13px; border: 1px solid #e7e3fb; background: rgba(255, 255, 255, .85); color: #64748b; text-align: left; transition: all .16s; }
.pjd-wiz__step:hover:not(:disabled) { border-color: #c7d2fe; background: #fff; }
.pjd-wiz__step.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 10px 22px rgba(99, 102, 241, .3); }
.pjd-wiz__step.is-done { border-color: #bbf7d0; background: #f0fdf4; color: #15803d; }
.pjd-wiz__step:disabled { cursor: not-allowed; opacity: .55; }
.pjd-wiz__no { width: 22px; height: 22px; border-radius: 7px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 800; background: #f1f5f9; color: #8792a6; }
.pjd-wiz__step.is-on .pjd-wiz__no { background: rgba(255, 255, 255, .26); color: #fff; }
.pjd-wiz__step.is-done .pjd-wiz__no { background: #dcfce7; color: #16a34a; }
.pjd-wiz__txt { display: flex; flex-direction: column; min-width: 0; }
.pjd-wiz__txt b { font-size: 12px; font-weight: 800; white-space: nowrap; }
.pjd-wiz__txt em { font-size: 9.5px; font-style: normal; font-weight: 700; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 168px; opacity: .72; }

.pjd-wiz { min-width: 0; }
.pjd-wiz__pane { display: flex; flex-direction: column; gap: 18px; animation: pjdPaneIn .26s cubic-bezier(.22, 1, .36, 1) both; }
.pjd-wiz__pane--tight { gap: 12px; }
@keyframes pjdPaneIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

.pjd-wiz__foot { display: flex; align-items: center; gap: 12px; width: 100%; flex-wrap: wrap; }
.pjd-wiz__back, .pjd-wiz__next { appearance: none; font-family: inherit; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 800; padding: 11px 18px; border-radius: 13px; transition: all .16s; flex: 0 0 auto; }
.pjd-wiz__back { border: 1px solid #e6e9f3; background: #fff; color: #64748b; }
.pjd-wiz__back:hover:not(:disabled) { background: #f8f9fc; color: #334155; }
.pjd-wiz__next { border: none; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 12px 26px rgba(99, 102, 241, .3); }
.pjd-wiz__next:hover:not(:disabled) { filter: brightness(1.05); }
.pjd-wiz__back:disabled, .pjd-wiz__next:disabled { cursor: not-allowed; color: #a5abc9; background: #eef0f7; border-color: transparent; box-shadow: none; filter: none; }
.pjd-wiz__dots { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; min-width: 0; }
.pjd-wiz__dots i { width: 7px; height: 7px; border-radius: 50%; background: #e2e8f0; transition: all .2s; }
.pjd-wiz__dots i.is-done { background: #a5b4fc; }
.pjd-wiz__dots i.is-on { width: 22px; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); }
.pjd-gen--foot { width: auto; flex: 0 0 auto; padding: 12px 22px; }

/* KABAR BAIK (hijau) — bukan peringatan. Dipakai untuk keadaan yang sudah benar
   dengan sendirinya dan tidak menuntut tindakan apa pun: kamera pengawas. */
.pjd-ok { display: flex; gap: 12px; align-items: flex-start; padding: 13px 15px; border-radius: 15px; background: linear-gradient(135deg, #f0fdf4, #ecfdf5); border: 1px solid #bbf7d0; font-size: 12.5px; line-height: 1.55; color: #15803d; }
.pjd-ok b { display: block; color: #14532d; font-weight: 800; margin-bottom: 2px; }
.pjd-ok__ico { width: 32px; height: 32px; border-radius: 10px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #34d399, #10b981); color: #fff; box-shadow: 0 8px 18px rgba(16, 185, 129, .28); }

/* ── LANGKAH TINJAU ── */
.pjd-tinjau { display: flex; flex-direction: column; gap: 14px; }
.pjd-tinjau__head { display: flex; align-items: flex-start; gap: 13px; flex-wrap: wrap; padding: 15px 16px; border-radius: 18px; background: linear-gradient(135deg, #f6f4ff 0%, #f2f5ff 55%, #eef4ff 100%); border: 1px solid #e4e7f5; }
.pjd-tinjau__ico { width: 44px; height: 44px; border-radius: 13px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 10px 24px rgba(99, 102, 241, .3); }
.pjd-tinjau__ttl { font-size: 17px; font-weight: 800; color: #1e1b4b; letter-spacing: -.02em; text-wrap: pretty; }
.pjd-tinjau__sub { font-size: 12.5px; color: #6b6597; margin-top: 4px; }
.pjd-tinjau__n { flex: 0 0 auto; font-size: 11.5px; font-weight: 800; padding: 6px 13px; border-radius: 999px; background: #fff; border: 1px solid #dbe2fe; color: #4338ca; }
.pjd-tinjau__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; }
.pjd-tinjau__box { display: flex; flex-direction: column; min-width: 0; padding: 12px 14px; border-radius: 14px; background: #fff; border: 1px solid #eef0f7; }
.pjd-tinjau__k { font-size: 9.5px; font-weight: 800; letter-spacing: .1em; color: #a2a9ba; }
.pjd-tinjau__v { font-size: 13.5px; font-weight: 800; color: #1e293b; margin-top: 5px; line-height: 1.4; text-wrap: pretty; }
.pjd-tinjau__e { font-size: 11.5px; color: #8792a6; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-tinjau__orang { border: 1px solid #eef0f7; border-radius: 16px; background: #fff; overflow: hidden; }
.pjd-tinjau__oh { display: flex; align-items: center; gap: 8px; padding: 11px 14px; border-bottom: 1px solid #f4f5fb; background: #fbfbfe; font-size: 10.5px; font-weight: 800; letter-spacing: .1em; color: #4338ca; }
.pjd-tinjau__oh svg { color: #8b5cf6; flex: 0 0 auto; }
.pjd-tinjau__oh span { margin-left: auto; letter-spacing: 0; font-size: 10.5px; padding: 3px 9px; border-radius: 7px; background: #f4f2ff; color: #6d28d9; }
.pjd-tinjau__ol { display: flex; flex-wrap: wrap; gap: 7px; padding: 12px 14px; max-height: 190px; overflow-y: auto; }
.pjd-tinjau__o { display: inline-flex; align-items: center; gap: 7px; max-width: 100%; padding: 5px 11px 5px 5px; border-radius: 999px; background: #f8fafc; border: 1px solid #eef0f7; font-size: 12px; font-weight: 700; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pjd-tinjau__kosong { font-size: 12.5px; color: #a2a9ba; }

@media (min-width: 760px) {
    .pjd-pkgs { grid-template-columns: repeat(2, 1fr); }
    .pjd-times { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1180px) {
    .pjd-tbl { display: block; }
    .pjd-rows { display: none; }
}
/* Dua panel butuh lebar; di bawah 1180px keduanya menumpuk dan panel sesi
   dibatasi tingginya supaya rincian di bawahnya tetap terjangkau tanpa
   menggulir sepanjang tujuh belas sesi lebih dulu. */
@media (max-width: 1179.98px) {
    .pjd-split { grid-template-columns: minmax(0, 1fr); }
    .pjd-sesipane { border-right: 0; border-bottom: 1px solid #eef0f7; }
    .pjd-sesipane__list { max-height: 290px; }
}
@media (max-width: 760px) {
    .pjd { padding: 20px 16px 44px; }
    .pjd-head h1 { font-size: 22px; }
    .pjd-fbox, .pjd-fbox--sm, .pjd-fbox--lg { flex: 1 1 100%; }
    .pjd-head__r { width: 100%; }
    .pjd-toolbar__hasil { margin-left: 0; width: 100%; }
    .pjd-chips { width: 100%; }
    .pjd-chip { flex: 1; }
    .pjd-views { width: 100%; justify-content: center; }
    .pjd-prog__head { padding: 14px 15px; }
    .pjd-alur { padding: 13px 15px 4px; }
    .pjd-petak { padding: 12px 15px 16px; grid-template-columns: minmax(0, 1fr); }
    .pjd-detail__head { padding: 15px 15px 13px; }
    .pjd-ptool { padding: 12px 15px; }
    .pjd-detail__ulang { width: 100%; justify-content: center; }
    .pjd-buat { flex: 1; justify-content: center; }
    /* Bilah langkah menciut jadi nomor + judul saja: nilai terpilih di baris
       kedua membuat tiap langkah selebar setengah layar, dan menggeser lima
       kali cuma untuk melihat sudah sampai mana bukan pertolongan. */
    .pjd-wiz__txt em { display: none; }
    .pjd-wiz__foot { gap: 8px; }
    .pjd-wiz__dots { order: 3; width: 100%; flex: 1 0 100%; }
    .pjd-wiz__back, .pjd-wiz__next, .pjd-gen--foot { flex: 1; justify-content: center; }
}

/* PONSEL — toast sudut melebar penuh. Pada 360px, lebar sudut hanya menyisakan
   ruang teks selebar dua kata dan pesan panjang terpotong jadi banyak baris
   sempit. Lihat --wca-z-toast di evo-theme.css untuk lapisannya. */
@media (max-width: 560px) {
    .pjd-toast {
        left: 12px;
        right: 12px;
        max-width: none;
        align-items: flex-start;
    }
    .pjd-toast .bi { flex: none; margin-top: 1px; }
}
</style>
