<!--
    GEMBOK — halaman bypass penjadwalan (/ui/bypass/gembok).

    TANPA AppShell. Halaman ini berjalan di luar sesi admin (lihat
    GerbangGembok), jadi tidak ada pengguna yang bisa ditampilkan di bilah atas
    dan tidak ada menu yang bisa disorot di samping — lihat `layout: null`.

    TAMPILANNYA MENGIKUTI TEMA EVO, memakai kelas `wca-*` yang sama dengan
    seluruh panel admin. Sebelumnya halaman ini bergaya gelap sendiri dengan
    alasan "supaya terasa berbeda"; hasilnya justru layar asing yang tidak
    terbaca sebagai bagian dari sistem ini. Yang membedakannya dari layar biasa
    sudah cukup dijelaskan oleh pita peringatan merah di kepala halaman dan
    konfirmasi berketik di tiap aksi — bukan oleh palet warna.

    SUSUNANNYA DUA KARTU BESAR:
      1. Cek Penjadwalan     — hanya membaca, tidak mengubah apa pun.
      2. Batalkan Penjadwalan — seluruh aksi yang MENGHAPUS, dikumpulkan jadi
                                satu: per kandidat, hapus total, baris yatim,
                                dan riwayat OTP-nya.
    Pemisahnya sengaja "membaca" vs "menghapus", bukan per jenis data: yang
    perlu diketahui lebih dulu oleh yang membuka halaman ini adalah apakah
    sebuah tombol akan mengubah sesuatu atau tidak.
-->
<template>
    <Head title="Gembok — Bypass Penjadwalan" />

    <div class="wca gmb">
        <!-- ══ KEPALA ══════════════════════════════════════════════════════ -->
        <div class="wca-phead">
            <div class="gmb-title">
                <span class="gmb-title__ico"><i class="bi bi-shield-lock-fill"></i></span>
                <div>
                    <h1>Gembok</h1>
                    <p>Bypass operasional penjadwalan tes — tanpa sesi admin.</p>
                </div>
            </div>

            <div class="wca-phead__actions">
                <!--
                    Lingkungan ditulis mencolok. Kesalahan paling mahal di
                    halaman ini bukan salah klik tombol, melainkan salah tahu
                    sedang menghapus di server yang mana.
                -->
                <span class="wca-badge" :class="envBadge">
                    <i class="bi bi-hdd-network"></i> {{ appEnv.toUpperCase() }}
                </span>
                <span class="wca-badge" :class="lindungiTerpakai ? 'wca-b--green' : 'wca-b--red'">
                    <i class="bi" :class="lindungiTerpakai ? 'bi-shield-check' : 'bi-shield-slash'"></i>
                    {{ lindungiTerpakai ? 'Pengaman menyala' : 'Pengaman mati' }}
                </span>
            </div>
        </div>

        <div class="wca-note wca-note--danger">
            <i class="bi bi-exclamation-octagon-fill"></i>
            <span>
                <b>Halaman ini menghapus data sungguhan.</b>
                Aksinya berjalan langsung ke basis data tanpa gerbang peran, dan tidak ada tombol batal
                setelah dijalankan. <b>Token di HCLearn tidak ikut terhapus</b> — setiap penghapusan
                memberi kueri SQL yang harus dijalankan sendiri di sana.
            </span>
        </div>

        <!-- ══ DUA KARTU BESAR ═════════════════════════════════════════════ -->
        <div class="gmb-nav">
            <button class="gmb-nav__btn" :class="{ 'is-on': kartu === 'cek' }" @click="kartu = 'cek'">
                <span class="gmb-nav__ico gmb-nav__ico--biru"><i class="bi bi-search"></i></span>
                <span class="gmb-nav__teks">
                    <strong>Cek Penjadwalan</strong>
                    <small>Lihat jadwal tes milik seorang kandidat — tidak mengubah apa pun.</small>
                </span>
            </button>

            <button class="gmb-nav__btn" :class="{ 'is-on': kartu === 'batal' }" @click="kartu = 'batal'">
                <span class="gmb-nav__ico gmb-nav__ico--merah"><i class="bi bi-trash3"></i></span>
                <span class="gmb-nav__teks">
                    <strong>Batalkan Penjadwalan</strong>
                    <small>Per kandidat, hapus total, baris yatim, dan riwayat OTP.</small>
                </span>
            </button>
        </div>

        <!-- ════════════════════════════════════════════════════════════════
             KARTU 1 — CEK PENJADWALAN  (hanya membaca)
             ════════════════════════════════════════════════════════════════ -->
        <div v-show="kartu === 'cek'" class="wca-card">
            <div class="wca-card__head">
                <h3><i class="bi bi-search"></i> Cek Penjadwalan Kandidat</h3>
                <span class="wca-badge wca-b--sky">Hanya membaca</span>
            </div>

            <div class="wca-card__body">
                <p class="gmb-lead">
                    Masukkan <b>Id Users</b> (atau email) untuk melihat seluruh penjadwalan tes milik orang
                    itu — status, id penjadwalan, tahap, token, sampai OTP-nya.
                </p>

                <form class="gmb-form" @submit.prevent="cariKandidat">
                    <div class="gmb-field">
                        <label class="wca-field-lbl">Id Users</label>
                        <input v-model="cek.idUser" type="number" min="1" placeholder="mis. 1284" class="gmb-input" />
                    </div>
                    <span class="gmb-atau">atau</span>
                    <div class="gmb-field gmb-field--wide">
                        <label class="wca-field-lbl">Email</label>
                        <input v-model.trim="cek.email" type="email" placeholder="nama@contoh.com" class="gmb-input" />
                    </div>
                    <button class="wca-btn wca-btn--primary" :disabled="cek.sibuk">
                        <i class="bi" :class="cek.sibuk ? 'bi-arrow-repeat gmb-spin' : 'bi-search'"></i>
                        {{ cek.sibuk ? 'Mencari…' : 'Cari' }}
                    </button>
                </form>

                <p v-if="cek.galat" class="gmb-galat"><i class="bi bi-x-octagon"></i> {{ cek.galat }}</p>

                <div v-if="cek.data" class="gmb-hasil">
                    <div class="gmb-ident">
                        <div class="gmb-ident__ava">{{ inisial(cek.data.user.nama) }}</div>
                        <div class="gmb-ident__isi">
                            <strong>{{ cek.data.user.nama }}</strong>
                            <span>{{ cek.data.user.email }}</span>
                            <div class="gmb-ident__tag">
                                <span class="wca-badge wca-b--slate">Id {{ cek.data.user.id }}</span>
                                <span class="wca-badge wca-b--indigo">{{ cek.data.user.role }}</span>
                                <span class="wca-badge" :class="cek.data.user.status === 'AKTIF' ? 'wca-b--green' : 'wca-b--red'">
                                    {{ cek.data.user.status }}
                                </span>
                                <span v-if="cek.data.user.noHp" class="wca-badge wca-b--slate">{{ cek.data.user.noHp }}</span>
                            </div>
                        </div>
                        <div class="gmb-ident__angka">
                            <div><b>{{ cek.data.ringkas.totalJadwal }}</b><span>jadwal</span></div>
                            <div><b>{{ cek.data.ringkas.punyaToken }}</b><span>bertoken</span></div>
                            <div :class="{ 'is-bahaya': cek.data.ringkas.sudahJalan > 0 }">
                                <b>{{ cek.data.ringkas.sudahJalan }}</b><span>dikerjakan</span>
                            </div>
                        </div>
                    </div>

                    <h4 class="gmb-sub">Penjadwalan Tes</h4>

                    <p v-if="!cek.data.penjadwalan.length" class="gmb-kosong">
                        <i class="bi bi-calendar-x"></i>
                        <span>
                            Kandidat ini <b>belum punya penjadwalan tes sama sekali</b>.
                            Di layar kandidat ia terlihat sebagai “menunggu dijadwalkan”.
                        </span>
                    </p>

                    <div v-else class="gmb-tabel">
                        <table class="wca-table">
                            <thead>
                                <tr>
                                    <th>Peserta</th>
                                    <th>Program / Jadwal</th>
                                    <th>Tahap</th>
                                    <th>Waktu</th>
                                    <th>Token &amp; OTP</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="b in cek.data.penjadwalan" :key="b.idPeserta">
                                    <td>
                                        <b>#{{ b.idPeserta }}</b>
                                        <small>{{ b.kodePeserta }}</small>
                                        <small v-if="b.posisi">{{ b.posisi }}</small>
                                    </td>
                                    <td>
                                        <b>{{ b.namaProgram || '—' }}</b>
                                        <small>{{ b.kodeJadwal }} · jadwal #{{ b.idPenjadwalan }}</small>
                                        <small>{{ b.namaJadwal }}</small>
                                    </td>
                                    <td>
                                        <b v-if="b.labelTahap">{{ b.urutanTahap }}. {{ b.labelTahap }}</b>
                                        <b v-else class="gmb-nil">tahap hilang</b>
                                        <small>tahap #{{ b.idTahap }}</small>
                                        <small v-if="b.namaUjian">{{ b.namaUjian }}</small>
                                    </td>
                                    <td>
                                        <small>{{ tgl(b.waktuMulai) }}</small>
                                        <small>s/d {{ tgl(b.waktuAkhir) }}</small>
                                    </td>
                                    <td>
                                        <code v-if="b.token" class="gmb-code">{{ b.token }}</code>
                                        <span v-else class="gmb-nil">belum terbit</span>
                                        <code v-if="b.otp" class="gmb-code gmb-code--otp">OTP {{ b.otp }}</code>
                                        <small v-if="b.idUjianToken">token #{{ b.idUjianToken }}</small>
                                    </td>
                                    <td>
                                        <span class="wca-badge" :class="badgeKirim(b.statusKirim)">{{ b.statusKirim }}</span>
                                        <span v-if="b.statusKerja" class="wca-badge" :class="badgeKerja(b.statusKerja)">{{ b.statusKerja }}</span>
                                        <small v-if="b.nilai !== null && b.nilai !== undefined">nilai {{ b.nilai }}</small>
                                        <small v-if="b.kelulusan">{{ b.kelulusan }}</small>
                                        <small v-if="b.pesanError" class="gmb-err">{{ b.pesanError }}</small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="gmb-sub">
                        Tahap Lamaran
                        <small>Kolom <b>Terikat Jadwal</b> inilah yang menentukan kandidat muncul atau tidak di daftar “bisa dijadwalkan”.</small>
                    </h4>

                    <p v-if="!cek.data.tahapLamaran.length" class="gmb-kosong">
                        <i class="bi bi-inbox"></i><span>Kandidat ini belum punya lamaran.</span>
                    </p>

                    <div v-else class="gmb-tabel">
                        <table class="wca-table">
                            <thead>
                                <tr>
                                    <th>Lamaran</th>
                                    <th>Urutan</th>
                                    <th>Tahap</th>
                                    <th>Status</th>
                                    <th>Hasil</th>
                                    <th>Terikat Jadwal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="t in cek.data.tahapLamaran" :key="t.id">
                                    <td>#{{ t.idLamaran }}</td>
                                    <td>{{ t.urutan }}</td>
                                    <td>{{ t.label }}</td>
                                    <td><span class="wca-badge wca-b--slate">{{ t.status }}</span></td>
                                    <td>{{ t.hasil || '—' }}</td>
                                    <td>
                                        <span v-if="t.idTahapJadwal" class="wca-badge wca-b--indigo">tahap #{{ t.idTahapJadwal }}</span>
                                        <span v-else class="wca-badge wca-b--green">bebas — bisa dijadwalkan</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="cek.data.penjadwalan.length" class="gmb-aksi">
                        <button class="wca-btn wca-btn--danger" @click="lompatKeBatal(cek.data)">
                            <i class="bi bi-trash3"></i> Batalkan penjadwalan kandidat ini
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════
             KARTU 2 — BATALKAN PENJADWALAN  (semua aksi yang menghapus)
             ════════════════════════════════════════════════════════════════ -->
        <div v-show="kartu === 'batal'" class="wca-card">
            <div class="wca-card__head">
                <h3><i class="bi bi-trash3"></i> Batalkan Penjadwalan</h3>
                <span class="wca-badge wca-b--red">Mengubah data</span>
            </div>

            <div class="wca-card__body">
                <!-- Sub-menu di DALAM kartu batalkan -->
                <div class="gmb-sub-nav">
                    <button
                        v-for="s in subMenu"
                        :key="s.id"
                        class="gmb-sub-nav__btn"
                        :class="{ 'is-on': sub === s.id }"
                        @click="sub = s.id"
                    >
                        <i class="bi" :class="s.ikon"></i>
                        <span>{{ s.judul }}</span>
                        <em v-if="s.id === 'otp' && riwayat.length" class="gmb-hitung">{{ riwayat.length }}</em>
                    </button>
                </div>

                <!-- ── 2a. PER KANDIDAT ──────────────────────────────────── -->
                <section v-show="sub === 'kandidat'" class="gmb-sec">
                    <p class="gmb-lead">
                        Menghapus penjadwalan <b>satu orang</b> beserta runtunnya, sehingga di layar kandidat
                        ia kembali berstatus <b>menunggu dijadwalkan</b> dan admin bisa menjadwalkannya lagi.
                        Peserta lain di gelombang yang sama tidak ikut terbawa.
                    </p>

                    <form class="gmb-form" @submit.prevent="muatBatal">
                        <div class="gmb-field">
                            <label class="wca-field-lbl">Id Users</label>
                            <input v-model="batal.idUser" type="number" min="1" placeholder="mis. 1284" class="gmb-input" />
                        </div>
                        <button class="wca-btn wca-btn--primary" :disabled="batal.sibuk">
                            <i class="bi" :class="batal.sibuk ? 'bi-arrow-repeat gmb-spin' : 'bi-list-check'"></i>
                            {{ batal.sibuk ? 'Memuat…' : 'Tampilkan penjadwalannya' }}
                        </button>
                    </form>

                    <p v-if="batal.galat" class="gmb-galat"><i class="bi bi-x-octagon"></i> {{ batal.galat }}</p>

                    <div v-if="batal.data" class="gmb-hasil">
                        <div class="gmb-ident gmb-ident--rapat">
                            <div class="gmb-ident__ava">{{ inisial(batal.data.user.nama) }}</div>
                            <div class="gmb-ident__isi">
                                <strong>{{ batal.data.user.nama }}</strong>
                                <span>{{ batal.data.user.email }} · Id {{ batal.data.user.id }}</span>
                            </div>
                        </div>

                        <p v-if="!batal.data.penjadwalan.length" class="gmb-kosong">
                            <i class="bi bi-calendar-x"></i><span>Tidak ada penjadwalan yang bisa dibatalkan.</span>
                        </p>

                        <template v-else>
                            <div class="gmb-bar">
                                <label class="gmb-cek">
                                    <input type="checkbox" :checked="semuaTerpilih" @change="togglePilihSemua" />
                                    <span>Pilih semua ({{ batal.data.penjadwalan.length }})</span>
                                </label>
                                <span class="gmb-bar__info">{{ batal.pilih.length }} dipilih</span>
                            </div>

                            <div class="gmb-tabel">
                                <table class="wca-table">
                                    <thead>
                                        <tr>
                                            <th class="gmb-th-cek"></th>
                                            <th>Peserta</th>
                                            <th>Jadwal</th>
                                            <th>Tahap</th>
                                            <th>OTP</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="b in batal.data.penjadwalan"
                                            :key="b.idPeserta"
                                            :class="{ 'is-terpakai': sudahDikerjakan(b) }"
                                        >
                                            <td><input type="checkbox" :value="b.idPeserta" v-model="batal.pilih" /></td>
                                            <td><b>#{{ b.idPeserta }}</b><small>{{ b.kodePeserta }}</small></td>
                                            <td><b>{{ b.kodeJadwal }}</b><small>{{ b.namaProgram }}</small></td>
                                            <td><small>{{ b.labelTahap || '—' }}</small><small>#{{ b.idTahap }}</small></td>
                                            <td><code v-if="b.otp" class="gmb-code gmb-code--otp">{{ b.otp }}</code><span v-else class="gmb-nil">—</span></td>
                                            <td>
                                                <span class="wca-badge" :class="badgeKirim(b.statusKirim)">{{ b.statusKirim }}</span>
                                                <span v-if="sudahDikerjakan(b)" class="wca-badge wca-b--red">sudah dikerjakan</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!--
                                Peringatan keras. Muncul HANYA kalau memang ada
                                yang sudah dikerjakan, supaya ia tidak jadi
                                hiasan yang selalu ada dan karenanya berhenti
                                dibaca.
                            -->
                            <div v-if="batalTerpakai > 0" class="wca-note wca-note--danger gmb-warn">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span>
                                    <b>{{ batalTerpakai }} dari yang dipilih SUDAH MENGERJAKAN ujiannya.</b>
                                    Menghapusnya menghilangkan jejak hasil mereka, dan orangnya berpeluang dites dua kali.
                                    <label class="gmb-cek gmb-cek--bahaya">
                                        <input type="checkbox" v-model="batal.paksa" />
                                        <span>Saya mengerti, hapus juga yang sudah dikerjakan</span>
                                    </label>
                                </span>
                            </div>

                            <div class="gmb-aksi">
                                <button
                                    class="wca-btn wca-btn--danger"
                                    :disabled="!batal.pilih.length || batal.hapusSibuk || (batalTerpakai > 0 && !batal.paksa)"
                                    @click="jalankanBatal"
                                >
                                    <i class="bi" :class="batal.hapusSibuk ? 'bi-arrow-repeat gmb-spin' : 'bi-trash3'"></i>
                                    {{ batal.hapusSibuk ? 'Menghapus…' : `Batalkan ${batal.pilih.length} penjadwalan` }}
                                </button>
                            </div>
                        </template>
                    </div>
                </section>

                <!-- ── 2b. HAPUS TOTAL ───────────────────────────────────── -->
                <section v-show="sub === 'total'" class="gmb-sec">
                    <p class="gmb-lead">
                        Isi <code>N_WEB_CAREERS_Penjadwalan</code> — disaring per <b>program</b> dan per <b>hari</b>.
                        Yang dipilih akan <b>dihitung dulu</b>; penghapusan baru berjalan setelah jumlahnya
                        dilihat dan dikonfirmasi.
                    </p>

                    <!--
                        KEDUA SARINGAN OPSIONAL. Dibiarkan kosong = seluruh
                        penjadwalan ditampilkan; itu keadaan awalnya, dan
                        daftarnya sudah termuat sendiri saat kartu ini dibuka.
                        Menuntut program dipilih dulu berarti menyembunyikan
                        justru pertanyaan pertama yang dibawa orang ke sini:
                        "sebenarnya ada jadwal apa saja di sini?"
                    -->
                    <div class="gmb-form">
                        <div class="gmb-field gmb-field--wide">
                            <label class="wca-field-lbl">Program <span class="gmb-opsional">opsional</span></label>
                            <select v-model="total.idProgram" class="gmb-input" @change="muatPenjadwalan">
                                <option value="">— semua program —</option>
                                <option v-for="p in total.program" :key="p.id" :value="p.id">
                                    {{ p.nama }} ({{ p.jumlahJadwal }} jadwal)
                                </option>
                            </select>
                        </div>

                        <!--
                            Tanggal, bukan daftar pilihan. Kalender menerima
                            hari mana pun — termasuk yang kebetulan belum ada
                            jadwalnya — sehingga "tidak ada jadwal di hari itu"
                            bisa dijawab, bukan disembunyikan dengan cara
                            menghilangkan tanggalnya dari pilihan.

                            `list` menautkan ke <datalist> di bawah: hari yang
                            MEMANG ada isinya tetap muncul sebagai saran di
                            peramban yang mendukungnya.
                        -->
                        <div class="gmb-field">
                            <label class="wca-field-lbl">Tanggal Mulai <span class="gmb-opsional">opsional</span></label>
                            <input
                                v-model="total.tanggal"
                                type="date"
                                class="gmb-input"
                                list="gmb-hari-ada"
                                @change="muatPenjadwalan"
                            />
                            <datalist id="gmb-hari-ada">
                                <option v-for="h in total.hari" :key="h.tanggal" :value="tglIso(h.tanggal)">
                                    {{ h.jumlah }} jadwal
                                </option>
                            </datalist>
                        </div>

                        <button
                            v-if="total.idProgram || total.tanggal"
                            class="wca-btn wca-btn--ghost"
                            :disabled="total.sibuk"
                            @click="resetSaringan"
                        >
                            <i class="bi bi-x-circle"></i> Bersihkan saringan
                        </button>

                        <button class="wca-btn wca-btn--primary" :disabled="total.sibuk" @click="muatPenjadwalan">
                            <i class="bi" :class="total.sibuk ? 'bi-arrow-repeat gmb-spin' : 'bi-arrow-clockwise'"></i>
                            {{ total.sibuk ? 'Memuat…' : 'Muat ulang' }}
                        </button>
                    </div>

                    <!--
                        Hari yang ada isinya, bisa diklik. Kalender tidak
                        menunjukkan hari mana yang berisi jadwal, jadi tanpa
                        deretan ini satu-satunya cara menemukannya adalah
                        menebak tanggal satu per satu.
                    -->
                    <div v-if="total.hari.length" class="gmb-hari">
                        <span class="gmb-hari__label">Hari yang ada jadwalnya:</span>
                        <button
                            v-for="h in total.hari"
                            :key="h.tanggal"
                            class="gmb-hari__btn"
                            :class="{ 'is-on': total.tanggal === tglIso(h.tanggal) }"
                            @click="pilihHari(h.tanggal)"
                        >
                            {{ tglSaja(h.tanggal) }}
                            <em>{{ h.jumlah }}</em>
                        </button>
                    </div>

                    <p v-if="total.galat" class="gmb-galat"><i class="bi bi-x-octagon"></i> {{ total.galat }}</p>

                    <template v-if="total.rows.length">
                        <div class="gmb-bar">
                            <label class="gmb-cek">
                                <input type="checkbox" :checked="semuaJadwalTerpilih" @change="togglePilihSemuaJadwal" />
                                <span>Pilih semua ({{ total.rows.length }})</span>
                            </label>
                            <span class="gmb-bar__info">
                                {{ total.pilih.length }} jadwal dipilih ·
                                <b>{{ pesertaTerpilih }}</b> peserta akan terhapus
                            </span>
                        </div>

                        <div class="gmb-tabel">
                            <table class="wca-table">
                                <thead>
                                    <tr>
                                        <th class="gmb-th-cek"></th>
                                        <th>Kode</th>
                                        <th>Nama Penjadwalan</th>
                                        <th>Program</th>
                                        <th>Tanggal</th>
                                        <th>Peserta</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in total.rows" :key="r.id" :class="{ 'is-terpakai': r.pesertaTerpakai > 0 }">
                                        <td><input type="checkbox" :value="r.id" v-model="total.pilih" /></td>
                                        <td><b>{{ r.kode }}</b><small>#{{ r.id }}</small></td>
                                        <td>{{ r.nama }}<small v-if="r.dibuatOleh">oleh {{ r.dibuatOleh }}</small></td>
                                        <td>{{ r.namaProgram || '—' }}</td>
                                        <td><small>{{ tglSaja(r.tanggalMulai) }}</small><small v-if="r.tanggalSelesai">s/d {{ tglSaja(r.tanggalSelesai) }}</small></td>
                                        <td>
                                            <b>{{ r.pesertaNyata }}</b>
                                            <small v-if="r.pesertaTerpakai > 0" class="gmb-err">{{ r.pesertaTerpakai }} sudah dikerjakan</small>
                                        </td>
                                        <td><span class="wca-badge wca-b--slate">{{ r.status }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="gmb-aksi">
                            <button class="wca-btn wca-btn--danger" :disabled="!total.pilih.length || total.pratinjauSibuk" @click="mintaPratinjau">
                                <i class="bi" :class="total.pratinjauSibuk ? 'bi-arrow-repeat gmb-spin' : 'bi-calculator'"></i>
                                {{ total.pratinjauSibuk ? 'Menghitung…' : `Hitung dulu (${total.pilih.length} jadwal)` }}
                            </button>
                        </div>
                    </template>

                    <p v-else-if="total.sudahMuat && !total.sibuk" class="gmb-kosong">
                        <i class="bi bi-inbox"></i><span>Tidak ada penjadwalan pada saringan ini.</span>
                    </p>
                </section>

                <!-- ── 2c. BARIS YATIM ───────────────────────────────────── -->
                <section v-show="sub === 'yatim'" class="gmb-sec">
                    <p class="gmb-lead">
                        Tidak ada satu pun <i>foreign key</i> di tabel penjadwalan, jadi penghapusan dengan
                        urutan yang salah tidak menimbulkan galat — ia hanya meninggalkan lamaran yang
                        memegang id tahap yang sudah tidak ada. Kandidatnya lalu tidak bisa dijadwalkan
                        ulang, tanpa penjelasan di layar mana pun.
                    </p>

                    <div class="gmb-form">
                        <button class="wca-btn wca-btn--primary" :disabled="sehat.sibuk" @click="periksaKesehatan">
                            <i class="bi" :class="sehat.sibuk ? 'bi-arrow-repeat gmb-spin' : 'bi-activity'"></i>
                            {{ sehat.sibuk ? 'Memeriksa…' : 'Periksa sekarang' }}
                        </button>
                    </div>

                    <p v-if="sehat.galat" class="gmb-galat"><i class="bi bi-x-octagon"></i> {{ sehat.galat }}</p>

                    <div v-if="sehat.data" class="gmb-hasil">
                        <div v-if="sehat.data.sehat" class="wca-note gmb-ok">
                            <i class="bi bi-check-circle-fill"></i>
                            <span><b>Bersih.</b> Tidak ada satu pun baris yang menunjuk penjadwalan yang sudah tidak ada.</span>
                        </div>

                        <div v-else class="wca-note wca-note--danger">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>
                                <b>Ada tautan yang menggantung.</b>
                                Baris di bawah menunjuk penjadwalan yang sudah dihapus. Selama masih begini,
                                kandidatnya tidak akan muncul di daftar “bisa dijadwalkan”.
                            </span>
                        </div>

                        <div class="gmb-angka">
                            <div :class="{ 'is-bahaya': sehat.data.yatim.lamaranTahap > 0 }">
                                <b>{{ sehat.data.yatim.lamaranTahap }}</b><span>Lamaran_Tahap yatim</span>
                            </div>
                            <div :class="{ 'is-bahaya': sehat.data.yatim.lamaranTahapTes > 0 }">
                                <b>{{ sehat.data.yatim.lamaranTahapTes }}</b><span>Lamaran_Tahap_Tes yatim</span>
                            </div>
                            <div :class="{ 'is-bahaya': sehat.data.yatim.peserta > 0 }">
                                <b>{{ sehat.data.yatim.peserta }}</b><span>Peserta tanpa induk</span>
                            </div>
                            <div :class="{ 'is-bahaya': sehat.data.yatim.tahap > 0 }">
                                <b>{{ sehat.data.yatim.tahap }}</b><span>Tahap tanpa induk</span>
                            </div>
                            <div><b>{{ sehat.data.jadwalKosong.length }}</b><span>Jadwal tanpa peserta</span></div>
                        </div>

                        <template v-if="sehat.data.contoh.length">
                            <h4 class="gmb-sub">Contoh baris yatim <small>maksimal 50</small></h4>
                            <div class="gmb-tabel">
                                <table class="wca-table">
                                    <thead>
                                        <tr><th>Lamaran Tahap</th><th>Kandidat</th><th>Tahap</th><th>Status</th><th>Menunjuk tahap</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="c in sehat.data.contoh" :key="c.id">
                                            <td>#{{ c.id }}<small>lamaran #{{ c.idLamaran }}</small></td>
                                            <td><b>{{ c.nama || '—' }}</b><small>{{ c.email }}</small><small v-if="c.idUser">Id {{ c.idUser }}</small></td>
                                            <td>{{ c.label }}</td>
                                            <td><span class="wca-badge wca-b--slate">{{ c.status }}</span></td>
                                            <td><span class="wca-badge wca-b--red">#{{ c.idTahapHilang }} — hilang</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <template v-if="sehat.data.jadwalKosong.length">
                            <h4 class="gmb-sub">
                                Penjadwalan tanpa peserta
                                <small>bukan kerusakan, tapi hampir selalu sisa percobaan yang gagal</small>
                            </h4>
                            <div class="gmb-tabel">
                                <table class="wca-table">
                                    <thead><tr><th>Kode</th><th>Nama</th><th>Status</th><th>Dibuat</th></tr></thead>
                                    <tbody>
                                        <tr v-for="j in sehat.data.jadwalKosong" :key="j.id">
                                            <td><b>{{ j.kode }}</b><small>#{{ j.id }}</small></td>
                                            <td>{{ j.nama }}</td>
                                            <td><span class="wca-badge wca-b--slate">{{ j.status }}</span></td>
                                            <td><small>{{ tgl(j.dibuat) }}</small></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <div v-if="!sehat.data.sehat" class="gmb-aksi gmb-aksi--kolom">
                            <div class="gmb-field gmb-field--wide">
                                <label class="wca-field-lbl">Ketik <b>BERSIHKAN</b> untuk melepas tautan yang menggantung</label>
                                <input v-model="sehat.konfirmasi" class="gmb-input" placeholder="BERSIHKAN" />
                            </div>
                            <button class="wca-btn wca-btn--danger" :disabled="sehat.konfirmasi !== 'BERSIHKAN' || sehat.bersihSibuk" @click="bersihkanYatim">
                                <i class="bi" :class="sehat.bersihSibuk ? 'bi-arrow-repeat gmb-spin' : 'bi-eraser'"></i>
                                {{ sehat.bersihSibuk ? 'Membersihkan…' : 'Bersihkan tautan yatim' }}
                            </button>
                            <p class="gmb-nota">Hanya melepas tautannya (jadi NULL). Tidak ada baris lamaran yang dihapus.</p>
                        </div>
                    </div>
                </section>

                <!-- ── 2d. RIWAYAT OTP ───────────────────────────────────── -->
                <section v-show="sub === 'otp'" class="gmb-sec">
                    <p class="gmb-lead">
                        Setiap penghapusan menyimpan OTP-nya di peramban ini (<code>localStorage</code>),
                        karena token di HCLearn tidak ikut terhapus dan daftar ini satu-satunya jejak yang
                        tersisa. Tersimpan hanya di peramban ini — tidak dikirim ke mana pun.
                    </p>

                    <p v-if="!riwayat.length" class="gmb-kosong">
                        <i class="bi bi-inbox"></i><span>Belum ada riwayat penghapusan di peramban ini.</span>
                    </p>

                    <template v-else>
                        <div class="gmb-bar">
                            <span class="gmb-bar__info">{{ riwayat.length }} batch tersimpan · <b>{{ totalOtpRiwayat }}</b> OTP</span>
                            <button class="wca-btn wca-btn--ghost wca-btn--sm" @click="hapusSemuaRiwayat">
                                <i class="bi bi-trash"></i> Hilangkan semua
                            </button>
                        </div>

                        <div v-for="r in riwayat" :key="r.id" class="gmb-batch">
                            <div class="gmb-batch__head">
                                <div>
                                    <strong>{{ r.judul }}</strong>
                                    <small>{{ r.waktu }} · {{ (r.otp || []).length }} OTP · {{ r.jumlahPeserta }} peserta</small>
                                </div>
                                <div class="gmb-batch__aksi">
                                    <button class="wca-btn wca-btn--soft wca-btn--sm" @click="salin(r.sql)">
                                        <i class="bi bi-clipboard"></i> Salin SQL
                                    </button>
                                    <button class="wca-btn wca-btn--ghost wca-btn--sm" @click="r.buka = !r.buka">
                                        <i class="bi" :class="r.buka ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                        {{ r.buka ? 'Tutup' : 'Lihat' }}
                                    </button>
                                    <button class="wca-btn wca-btn--danger wca-btn--sm" @click="hapusRiwayat(r.id)">
                                        <i class="bi bi-x-lg"></i> Hilangkan
                                    </button>
                                </div>
                            </div>

                            <div v-if="r.buka" class="gmb-batch__isi">
                                <div class="gmb-otplist">
                                    <code v-for="o in r.otp" :key="o" class="gmb-code gmb-code--otp">{{ o }}</code>
                                </div>
                                <pre class="gmb-sql">{{ r.sql }}</pre>
                            </div>
                        </div>
                    </template>
                </section>
            </div>
        </div>

        <!-- ══ MODAL PRATINJAU + KONFIRMASI HAPUS TOTAL ═══════════════════ -->
        <div v-if="pratinjau.buka" class="gmb-modal" @click.self="tutupPratinjau">
            <div class="gmb-modal__box">
                <div class="gmb-modal__head">
                    <h3><i class="bi bi-exclamation-octagon"></i> Konfirmasi Penghapusan</h3>
                    <button class="gmb-x" @click="tutupPratinjau"><i class="bi bi-x-lg"></i></button>
                </div>

                <div class="gmb-modal__isi">
                    <p class="gmb-lead">Yang akan terhapus bila dilanjutkan:</p>

                    <div class="gmb-angka">
                        <div><b>{{ pratinjau.jumlah.penjadwalan }}</b><span>penjadwalan</span></div>
                        <div><b>{{ pratinjau.jumlah.tahap }}</b><span>tahap</span></div>
                        <div><b>{{ pratinjau.jumlah.peserta }}</b><span>peserta</span></div>
                        <div><b>{{ pratinjau.jumlah.berOtp }}</b><span>OTP terbit</span></div>
                        <div :class="{ 'is-bahaya': pratinjau.jumlah.sudahDikerjakan > 0 }">
                            <b>{{ pratinjau.jumlah.sudahDikerjakan }}</b><span>sudah dikerjakan</span>
                        </div>
                    </div>

                    <p class="gmb-nota">
                        <b>{{ pratinjau.jumlah.lamaranTahap }}</b> tahap lamaran dan
                        <b>{{ pratinjau.jumlah.lamaranTahapTes }}</b> aktivitas tes akan dilepas ikatannya,
                        sehingga kandidatnya kembali bisa dijadwalkan.
                    </p>

                    <div class="gmb-daftar">
                        <div v-for="j in pratinjau.jadwal" :key="j.id" class="gmb-daftar__baris">
                            <b>{{ j.kode }}</b>
                            <span>{{ j.nama }}</span>
                            <small>{{ j.namaProgram }} · {{ tglSaja(j.tanggalMulai) }}</small>
                        </div>
                    </div>

                    <div v-if="pratinjau.jumlah.sudahDikerjakan > 0" class="wca-note wca-note--danger gmb-warn">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>
                            <b>{{ pratinjau.jumlah.sudahDikerjakan }} peserta SUDAH MENGERJAKAN ujiannya.</b>
                            Jejak hasil mereka akan hilang dan mereka berpeluang dites dua kali.
                            <label class="gmb-cek gmb-cek--bahaya">
                                <input type="checkbox" v-model="pratinjau.paksa" />
                                <span>Saya mengerti, hapus juga yang sudah dikerjakan</span>
                            </label>
                        </span>
                    </div>

                    <div class="gmb-field gmb-field--wide">
                        <label class="wca-field-lbl">Ketik <b>HAPUS</b> untuk melanjutkan</label>
                        <input v-model="pratinjau.konfirmasi" class="gmb-input" placeholder="HAPUS" @keyup.enter="jalankanHapusTotal" />
                    </div>

                    <p v-if="pratinjau.galat" class="gmb-galat"><i class="bi bi-x-octagon"></i> {{ pratinjau.galat }}</p>
                </div>

                <div class="gmb-modal__kaki">
                    <button class="wca-btn wca-btn--ghost" @click="tutupPratinjau">Batal</button>
                    <button
                        class="wca-btn wca-btn--danger"
                        :disabled="pratinjau.konfirmasi !== 'HAPUS' || pratinjau.sibuk || (pratinjau.jumlah.sudahDikerjakan > 0 && !pratinjau.paksa)"
                        @click="jalankanHapusTotal"
                    >
                        <i class="bi" :class="pratinjau.sibuk ? 'bi-arrow-repeat gmb-spin' : 'bi-trash3'"></i>
                        {{ pratinjau.sibuk ? 'Menghapus…' : 'Hapus sekarang' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ══ MODAL HASIL — KUERI SQL UNTUK HCLEARN ══════════════════════ -->
        <div v-if="hasil.buka" class="gmb-modal" @click.self="hasil.buka = false">
            <div class="gmb-modal__box gmb-modal__box--lebar">
                <div class="gmb-modal__head">
                    <h3><i class="bi bi-check-circle"></i> Selesai — {{ hasil.judul }}</h3>
                    <button class="gmb-x" @click="hasil.buka = false"><i class="bi bi-x-lg"></i></button>
                </div>

                <div class="gmb-modal__isi">
                    <div class="gmb-angka">
                        <div><b>{{ hasil.data.peserta }}</b><span>peserta dihapus</span></div>
                        <div><b>{{ hasil.data.tahap }}</b><span>tahap dihapus</span></div>
                        <div><b>{{ hasil.data.penjadwalan }}</b><span>penjadwalan dihapus</span></div>
                        <div><b>{{ hasil.data.lamaranTahapDilepas }}</b><span>lamaran dilepas</span></div>
                        <div><b>{{ hasil.data.lamaranTahapTesDilepas }}</b><span>aktivitas direset</span></div>
                    </div>

                    <!--
                        Inti dari modal ini. Token di HCLearn tidak ikut
                        terhapus, dan sesudah baris di sisi Web Careers hilang,
                        daftar OTP ini satu-satunya cara menemukannya lagi.
                    -->
                    <div class="wca-note wca-note--gold">
                        <i class="bi bi-database-exclamation"></i>
                        <span>
                            <b>Token di HCLearn belum tersentuh.</b>
                            Jalankan kueri di bawah pada database HCLearn untuk memeriksanya.
                            Daftar ini juga sudah disimpan di <b>Riwayat OTP</b> peramban ini.
                        </span>
                    </div>

                    <div v-if="hasil.data.otp && hasil.data.otp.length" class="gmb-otplist">
                        <code v-for="o in hasil.data.otp" :key="o" class="gmb-code gmb-code--otp">{{ o }}</code>
                    </div>

                    <div class="gmb-sqlwrap">
                        <button class="wca-btn wca-btn--soft wca-btn--sm gmb-sqlwrap__salin" @click="salin(hasil.data.sql)">
                            <i class="bi" :class="tersalin ? 'bi-check-lg' : 'bi-clipboard'"></i>
                            {{ tersalin ? 'Tersalin' : 'Salin' }}
                        </button>
                        <pre class="gmb-sql">{{ hasil.data.sql }}</pre>
                    </div>
                </div>

                <div class="gmb-modal__kaki">
                    <button class="wca-btn wca-btn--primary" @click="hasil.buka = false">Tutup</button>
                </div>
            </div>
        </div>

        <transition name="wca-toast">
            <div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div>
        </transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';

const API = '/ui/bypass/gembok/api';
const CFG = { headers: { Accept: 'application/json' } };

/*
 * Kunci penyimpanan riwayat OTP di peramban.
 *
 * Sengaja localStorage, BUKAN dikirim balik ke server: daftar ini adalah OTP
 * milik orang sungguhan, dan menyimpannya di basis data berarti membuat salinan
 * kedua dari kredensial yang barusan justru dicabut. Yang membutuhkannya cuma
 * satu orang, di satu peramban, selama beberapa menit sampai kueri SQL-nya
 * dijalankan di sisi HCLearn.
 */
const KUNCI_RIWAYAT = 'gembok.riwayatOtp';

/* Berapa batch yang disimpan. Riwayat yang tumbuh tanpa batas akan penuh oleh
 * OTP yang sudah lama tidak berlaku dan tak seorang pun berniat membacanya. */
const BATAS_RIWAYAT = 20;

export default {
    components: { Head },

    // Tanpa cangkang admin — halaman ini berjalan di luar sesi. Lihat catatan
    // di kepala berkas.
    layout: null,

    props: {
        lindungiTerpakai: { type: Boolean, default: false },
        appEnv: { type: String, default: 'local' },
    },

    data() {
        return {
            // Dua kartu besar: 'cek' (membaca) dan 'batal' (mengubah).
            kartu: 'cek',
            // Sub-menu di dalam kartu 'batal'.
            sub: 'kandidat',

            subMenu: [
                { id: 'kandidat', judul: 'Per Kandidat', ikon: 'bi-person-dash' },
                { id: 'total', judul: 'Hapus Total', ikon: 'bi-calendar2-x' },
                { id: 'yatim', judul: 'Baris Yatim', ikon: 'bi-heart-pulse' },
                { id: 'otp', judul: 'Riwayat OTP', ikon: 'bi-key' },
            ],

            cek: { idUser: '', email: '', sibuk: false, galat: '', data: null },

            batal: {
                idUser: '', sibuk: false, hapusSibuk: false, galat: '',
                data: null, pilih: [], paksa: false,
            },

            total: {
                idProgram: '', tanggal: '', sibuk: false, pratinjauSibuk: false,
                galat: '', rows: [], program: [], hari: [], pilih: [], sudahMuat: false,
            },

            sehat: { sibuk: false, bersihSibuk: false, galat: '', data: null, konfirmasi: '' },

            pratinjau: {
                buka: false, sibuk: false, galat: '', konfirmasi: '', paksa: false,
                jadwal: [], jumlah: {}, ids: [],
            },

            hasil: { buka: false, judul: '', data: {} },

            riwayat: [],
            tersalin: false,
            toast: '',
        };
    },

    computed: {
        envBadge() {
            if (this.appEnv === 'production') return 'wca-b--red';

            return this.appEnv === 'staging' ? 'wca-b--amber' : 'wca-b--green';
        },

        semuaTerpilih() {
            const n = this.batal.data?.penjadwalan?.length || 0;

            return n > 0 && this.batal.pilih.length === n;
        },

        semuaJadwalTerpilih() {
            return this.total.rows.length > 0 && this.total.pilih.length === this.total.rows.length;
        },

        /* Berapa dari YANG DIPILIH yang ujiannya sudah dikerjakan. Dihitung dari
         * pilihan, bukan dari seluruh baris — peringatannya harus menyangkut apa
         * yang benar-benar akan terhapus. */
        batalTerpakai() {
            const rows = this.batal.data?.penjadwalan || [];

            return rows.filter((b) => this.batal.pilih.includes(b.idPeserta) && this.sudahDikerjakan(b)).length;
        },

        pesertaTerpilih() {
            return this.total.rows
                .filter((r) => this.total.pilih.includes(r.id))
                .reduce((n, r) => n + (r.pesertaNyata || 0), 0);
        },

        totalOtpRiwayat() {
            return this.riwayat.reduce((n, r) => n + (r.otp?.length || 0), 0);
        },
    },

    watch: {
        /*
         * Daftar penjadwalan dimuat BEGITU sub-menunya dibuka.
         *
         * Sebelumnya dropdown Program & Hari diisi dari jawaban permintaan yang
         * sama dengan tabelnya — padahal permintaan itu baru berjalan setelah
         * tombol "Muat daftar" ditekan. Akibatnya kedua dropdown tampil KOSONG
         * saat kartu dibuka, dan satu-satunya cara mengisinya adalah menekan
         * tombol yang justru terlihat tidak perlu ditekan karena belum ada yang
         * dipilih. Saringan yang isinya baru ada setelah dipakai adalah
         * saringan yang tidak bisa dipakai.
         *
         * Dimuat sekali saja: `sudahMuat` menjaga agar berpindah-pindah tab
         * tidak menembak permintaan berulang kali.
         */
        sub: {
            immediate: true,
            handler(baru) {
                if (baru === 'total' && !this.total.sudahMuat && !this.total.sibuk) {
                    this.muatPenjadwalan();
                }
            },
        },
    },

    mounted() {
        this.muatRiwayat();
    },

    methods: {
        /* ── Bantu tampilan ─────────────────────────────────────────────── */

        inisial(nama) {
            return String(nama || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
        },

        tgl(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));

            return Number.isNaN(d.getTime())
                ? String(v)
                : d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
        },

        tglSaja(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));

            return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleDateString('id-ID', { dateStyle: 'medium' });
        },

        /* Nilai untuk <option>. Dipotong di 'T'/spasi, bukan lewat Date():
         * mengubahnya jadi Date lalu kembali ke ISO menggeser tanggalnya satu
         * hari untuk zona waktu di timur UTC — dan penghapusan massal yang
         * salah hari adalah persis kesalahan yang halaman ini harus cegah. */
        tglIso(v) {
            return v ? String(v).split('T')[0].split(' ')[0] : '';
        },

        sudahDikerjakan(b) {
            return ['mengerjakan', 'selesai', 'timeout'].includes(String(b.statusKerja || '').toLowerCase())
                || b.flagSelesai === 'Y';
        },

        badgeKirim(s) {
            const v = String(s || '').toUpperCase();
            if (v === 'TERKIRIM' || v === 'BERHASIL') return 'wca-b--green';
            if (v === 'GAGAL') return 'wca-b--red';

            return 'wca-b--amber';
        },

        badgeKerja(s) {
            const v = String(s || '').toLowerCase();
            if (v === 'selesai') return 'wca-b--green';
            if (v === 'mengerjakan') return 'wca-b--sky';
            if (v === 'timeout') return 'wca-b--red';

            return 'wca-b--slate';
        },

        pesan(e, bawaan) {
            return e?.response?.data?.message || e?.message || bawaan;
        },

        beriToast(t) {
            this.toast = t;
            setTimeout(() => { this.toast = ''; }, 3200);
        },

        async salin(teks) {
            try {
                await navigator.clipboard.writeText(teks || '');
                this.tersalin = true;
                this.beriToast('Kueri disalin ke papan klip.');
                setTimeout(() => { this.tersalin = false; }, 2000);
            } catch {
                // Papan klip ditolak peramban (bukan HTTPS, atau izin dicabut).
                // Isinya tetap terlihat di layar dan bisa disalin manual — jadi
                // ini pemberitahuan, bukan kegagalan.
                this.beriToast('Tidak bisa menyalin otomatis — silakan blok teksnya lalu salin manual.');
            }
        },

        /* ── KARTU 1 — CEK ──────────────────────────────────────────────── */

        async cariKandidat() {
            if (!this.cek.idUser && !this.cek.email) {
                this.cek.galat = 'Isi Id Users atau email dulu.';

                return;
            }

            this.cek.sibuk = true;
            this.cek.galat = '';

            try {
                const { data } = await axios.get(`${API}/kandidat`, {
                    ...CFG,
                    params: { idUser: this.cek.idUser || '', email: this.cek.email || '' },
                });
                this.cek.data = data.result;
            } catch (e) {
                this.cek.data = null;
                this.cek.galat = this.pesan(e, 'Gagal membaca data kandidat.');
            } finally {
                this.cek.sibuk = false;
            }
        },

        /* Melompat dari kartu Cek ke kartu Batalkan tanpa mengetik ulang id-nya. */
        lompatKeBatal(data) {
            this.kartu = 'batal';
            this.sub = 'kandidat';
            this.batal.idUser = data.user.id;
            this.batal.data = data;
            this.batal.pilih = [];
            this.batal.paksa = false;
            this.batal.galat = '';
        },

        /* ── KARTU 2a — PER KANDIDAT ────────────────────────────────────── */

        async muatBatal() {
            if (!this.batal.idUser) {
                this.batal.galat = 'Isi Id Users dulu.';

                return;
            }

            this.batal.sibuk = true;
            this.batal.galat = '';
            this.batal.pilih = [];
            this.batal.paksa = false;

            try {
                const { data } = await axios.get(`${API}/kandidat`, {
                    ...CFG,
                    params: { idUser: this.batal.idUser },
                });
                this.batal.data = data.result;
            } catch (e) {
                this.batal.data = null;
                this.batal.galat = this.pesan(e, 'Gagal membaca data kandidat.');
            } finally {
                this.batal.sibuk = false;
            }
        },

        togglePilihSemua(ev) {
            this.batal.pilih = ev.target.checked
                ? (this.batal.data?.penjadwalan || []).map((b) => b.idPeserta)
                : [];
        },

        async jalankanBatal() {
            if (!this.batal.pilih.length) return;

            this.batal.hapusSibuk = true;
            this.batal.galat = '';

            try {
                const { data } = await axios.post(`${API}/batal-peserta`, {
                    idUser: this.batal.idUser,
                    idPeserta: this.batal.pilih,
                    paksa: this.batal.paksa,
                }, CFG);

                const r = data.result;
                this.simpanRiwayat(`Batal kandidat #${this.batal.idUser} — ${this.batal.data?.user?.nama || ''}`, r);
                this.bukaHasil('Penjadwalan kandidat dibatalkan', r);

                // Muat ulang supaya layarnya menunjukkan keadaan sesudahnya,
                // bukan daftar lama yang barisnya sudah tidak ada.
                await this.muatBatal();
            } catch (e) {
                this.batal.galat = this.pesan(e, 'Gagal membatalkan.');
            } finally {
                this.batal.hapusSibuk = false;
            }
        },

        /* ── KARTU 2b — HAPUS TOTAL ─────────────────────────────────────── */

        async muatPenjadwalan() {
            this.total.sibuk = true;
            this.total.galat = '';

            try {
                const { data } = await axios.get(`${API}/penjadwalan`, {
                    ...CFG,
                    params: { idProgram: this.total.idProgram || '', tanggal: this.total.tanggal || '' },
                });

                this.total.rows = data.result.penjadwalan || [];

                // Daftar PROGRAM tidak pernah menyempit mengikuti saringan.
                // Ia dihitung dari seluruh tabel penjadwalan, jadi isinya sama
                // untuk saringan apa pun — dan kalau ditimpa dengan hasil yang
                // sudah tersaring, memilih satu program akan menghapus program
                // lain dari dropdown dan pilihannya tak bisa diubah lagi.
                this.total.program = data.result.program || [];

                // Daftar HARI ikut menyempit saat program dipilih (itu memang
                // gunanya: hari yang ada jadwalnya UNTUK program itu). Tapi
                // saat yang dipilih hanya tanggal, jawabannya cuma berisi hari
                // itu sendiri — maka daftar lamanya dipertahankan supaya
                // tombol hari yang lain tidak lenyap setelah satu diklik.
                if (!this.total.tanggal || !this.total.hari.length) {
                    this.total.hari = data.result.hari || [];
                }
                // Pilihan lama dibuang: baris yang dulu dipilih belum tentu
                // masih ada di saringan yang baru, dan id yang menggantung akan
                // terkirim saat menghapus.
                this.total.pilih = [];
                this.total.sudahMuat = true;
            } catch (e) {
                this.total.galat = this.pesan(e, 'Gagal membaca daftar penjadwalan.');
            } finally {
                this.total.sibuk = false;
            }
        },

        /* Klik tombol hari. Mengklik hari yang SEDANG aktif akan mematikannya —
         * tanpa itu, satu-satunya cara melepas saringan tanggal adalah lewat
         * tombol "Bersihkan saringan", dan tombol yang sudah menyala tapi tidak
         * bisa dipadamkan selalu terbaca sebagai macet. */
        pilihHari(tanggal) {
            const iso = this.tglIso(tanggal);
            this.total.tanggal = this.total.tanggal === iso ? '' : iso;
            this.muatPenjadwalan();
        },

        resetSaringan() {
            this.total.idProgram = '';
            this.total.tanggal = '';
            // Dikosongkan supaya daftar hari terisi ulang penuh dari jawaban
            // berikutnya (lihat catatan di muatPenjadwalan).
            this.total.hari = [];
            this.muatPenjadwalan();
        },

        togglePilihSemuaJadwal(ev) {
            this.total.pilih = ev.target.checked ? this.total.rows.map((r) => r.id) : [];
        },

        async mintaPratinjau() {
            if (!this.total.pilih.length) return;

            this.total.pratinjauSibuk = true;
            this.total.galat = '';

            try {
                const { data } = await axios.post(`${API}/pratinjau`, {
                    idPenjadwalan: this.total.pilih,
                }, CFG);

                this.pratinjau = {
                    buka: true,
                    sibuk: false,
                    galat: '',
                    konfirmasi: '',
                    paksa: false,
                    jadwal: data.result.jadwal || [],
                    jumlah: data.result.jumlah || {},
                    ids: [...this.total.pilih],
                };
            } catch (e) {
                this.total.galat = this.pesan(e, 'Gagal menghitung pratinjau.');
            } finally {
                this.total.pratinjauSibuk = false;
            }
        },

        tutupPratinjau() {
            this.pratinjau.buka = false;
        },

        async jalankanHapusTotal() {
            if (this.pratinjau.konfirmasi !== 'HAPUS' || this.pratinjau.sibuk) return;

            this.pratinjau.sibuk = true;
            this.pratinjau.galat = '';

            try {
                const { data } = await axios.post(`${API}/hapus-total`, {
                    idPenjadwalan: this.pratinjau.ids,
                    paksa: this.pratinjau.paksa,
                    konfirmasi: this.pratinjau.konfirmasi,
                }, CFG);

                const r = data.result;
                this.simpanRiwayat(`Hapus total — ${r.penjadwalan} penjadwalan, ${r.peserta} peserta`, r);
                this.pratinjau.buka = false;
                this.bukaHasil('Penjadwalan dihapus', r);

                await this.muatPenjadwalan();
            } catch (e) {
                this.pratinjau.galat = this.pesan(e, 'Gagal menghapus.');
            } finally {
                this.pratinjau.sibuk = false;
            }
        },

        /* ── KARTU 2c — BARIS YATIM ─────────────────────────────────────── */

        async periksaKesehatan() {
            this.sehat.sibuk = true;
            this.sehat.galat = '';

            try {
                const { data } = await axios.get(`${API}/kesehatan`, CFG);
                this.sehat.data = data.result;
            } catch (e) {
                this.sehat.data = null;
                this.sehat.galat = this.pesan(e, 'Gagal memeriksa.');
            } finally {
                this.sehat.sibuk = false;
            }
        },

        async bersihkanYatim() {
            this.sehat.bersihSibuk = true;
            this.sehat.galat = '';

            try {
                const { data } = await axios.post(`${API}/bersihkan-yatim`, {
                    konfirmasi: this.sehat.konfirmasi,
                }, CFG);

                const r = data.result;
                this.beriToast(`${r.lamaranTahap} tahap lamaran & ${r.lamaranTahapTes} aktivitas dilepas.`);
                this.sehat.konfirmasi = '';

                await this.periksaKesehatan();
            } catch (e) {
                this.sehat.galat = this.pesan(e, 'Gagal membersihkan.');
            } finally {
                this.sehat.bersihSibuk = false;
            }
        },

        /* ── HASIL & RIWAYAT ────────────────────────────────────────────── */

        bukaHasil(judul, data) {
            this.hasil = { buka: true, judul, data };
        },

        muatRiwayat() {
            try {
                const mentah = localStorage.getItem(KUNCI_RIWAYAT);
                this.riwayat = mentah ? JSON.parse(mentah).map((r) => ({ ...r, buka: false })) : [];
            } catch {
                // Isi yang rusak (atau localStorage yang ditolak peramban) tidak
                // boleh membuat seluruh halaman gagal dimuat — halaman ini
                // dipakai justru ketika hal lain sedang rusak.
                this.riwayat = [];
            }
        },

        simpanRiwayat(judul, hasil) {
            // Batch tanpa satu OTP pun tidak disimpan: tidak ada yang bisa
            // dikerjakan dengannya di sisi HCLearn, dan ia cuma memenuhi daftar.
            if (!hasil?.otp?.length) return;

            const batch = {
                id: Date.now(),
                judul,
                waktu: hasil.waktu || new Date().toLocaleString('id-ID'),
                otp: hasil.otp || [],
                sql: hasil.sql || '',
                jumlahPeserta: hasil.peserta || 0,
                buka: false,
            };

            this.riwayat = [batch, ...this.riwayat].slice(0, BATAS_RIWAYAT);
            this.tulisRiwayat();
        },

        tulisRiwayat() {
            try {
                localStorage.setItem(
                    KUNCI_RIWAYAT,
                    JSON.stringify(this.riwayat.map(({ buka, ...sisa }) => sisa)),
                );
            } catch {
                this.beriToast('Riwayat tidak bisa disimpan di peramban ini.');
            }
        },

        hapusRiwayat(id) {
            this.riwayat = this.riwayat.filter((r) => r.id !== id);
            this.tulisRiwayat();
            this.beriToast('Satu batch riwayat dihilangkan.');
        },

        hapusSemuaRiwayat() {
            this.riwayat = [];
            this.tulisRiwayat();
            this.beriToast('Seluruh riwayat OTP dihilangkan dari peramban ini.');
        },
    },
};
</script>

<style scoped>
/*
    Halaman ini memakai kelas `wca-*` dari evo-theme.css untuk kartu, tombol,
    tabel, lencana, dan toast. Yang ditulis di sini HANYA yang belum ada di
    tema — bukan penimpaan warnanya.

    `.wca` sendiri (pembungkus halaman admin) tidak ikut memberi latar karena
    halaman ini di luar AppShell, jadi latarnya disebut di sini.
*/
.gmb {
    min-height: 100vh;
    padding: 1.5rem clamp(1rem, 4vw, 2.5rem) 4rem;
    background: var(--evo-bg, #f8fafc);
    color: var(--text, #334155);
}

/* ── Kepala ───────────────────────────────────────────────────────────── */
.gmb-title { display: flex; align-items: center; gap: 0.85rem; }
.gmb-title__ico {
    display: grid; place-items: center;
    width: 2.9rem; height: 2.9rem; border-radius: 0.9rem;
    background: linear-gradient(135deg, var(--primary, #6366f1), #8b5cf6);
    color: #fff; font-size: 1.3rem; flex: none;
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28);
}

/* ── Dua kartu besar ──────────────────────────────────────────────────── */
.gmb-nav {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 0.85rem; margin-bottom: 1.25rem;
}
.gmb-nav__btn {
    display: flex; align-items: center; gap: 0.85rem; text-align: left;
    padding: 1rem 1.15rem; border-radius: 1rem;
    background: var(--panel, #fff); border: 1px solid var(--line, #e2e8f0);
    font: inherit; cursor: pointer; transition: all 0.15s ease;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
}
.gmb-nav__btn:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08); }
.gmb-nav__btn.is-on { border-color: var(--primary, #6366f1); box-shadow: 0 10px 26px rgba(99, 102, 241, 0.18); }
.gmb-nav__ico {
    display: grid; place-items: center; flex: none;
    width: 2.5rem; height: 2.5rem; border-radius: 0.75rem; font-size: 1.05rem;
}
.gmb-nav__ico--biru  { background: #e0e7ff; color: #4338ca; }
.gmb-nav__ico--merah { background: #fee2e2; color: #b91c1c; }
.gmb-nav__teks strong { display: block; font-size: 0.92rem; font-weight: 900; color: var(--ink, #0f172a); }
.gmb-nav__teks small { display: block; margin-top: 0.15rem; font-size: 0.76rem; font-weight: 600; color: var(--muted, #64748b); line-height: 1.45; }

/* ── Sub-menu di dalam kartu Batalkan ─────────────────────────────────── */
.gmb-sub-nav {
    display: flex; flex-wrap: wrap; gap: 0.4rem;
    padding-bottom: 1rem; margin-bottom: 1.1rem;
    border-bottom: 1px solid var(--line, #e2e8f0);
}
.gmb-sub-nav__btn {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 0.9rem; border-radius: 0.65rem;
    background: #f1f5f9; border: 1px solid transparent;
    font: inherit; font-size: 0.8rem; font-weight: 800;
    color: var(--muted, #64748b); cursor: pointer; transition: all 0.15s ease;
}
.gmb-sub-nav__btn:hover { background: #e2e8f0; color: var(--ink, #0f172a); }
.gmb-sub-nav__btn.is-on { background: var(--primary, #6366f1); color: #fff; }
.gmb-hitung {
    padding: 0.05rem 0.4rem; border-radius: 999px;
    background: rgba(255, 255, 255, 0.28); font-size: 0.68rem; font-style: normal; font-weight: 900;
}
.gmb-sub-nav__btn:not(.is-on) .gmb-hitung { background: #cbd5e1; color: #475569; }

.gmb-sec { animation: gmb-masuk 0.18s ease; }
@keyframes gmb-masuk { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

/* ── Teks & borang ────────────────────────────────────────────────────── */
.gmb-lead {
    margin: 0 0 1rem; font-size: 0.84rem; font-weight: 600;
    line-height: 1.65; color: var(--muted, #64748b); max-width: 82ch;
}
.gmb-lead b, .gmb-lead code { color: var(--ink, #0f172a); font-weight: 800; }
.gmb-lead code {
    padding: 0.1rem 0.35rem; border-radius: 0.35rem;
    background: #f1f5f9; font-size: 0.78rem;
}

.gmb-form {
    display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end;
    padding: 1rem; margin-bottom: 0.25rem;
    background: #f8fafc; border: 1px solid var(--line, #e2e8f0); border-radius: 0.9rem;
}
.gmb-field { display: flex; flex-direction: column; gap: 0.35rem; min-width: 11rem; }
.gmb-field--wide { flex: 1; min-width: 14rem; }
.gmb-atau { padding-bottom: 0.65rem; font-size: 0.78rem; font-weight: 700; color: var(--muted, #64748b); }

.gmb-input {
    width: 100%; padding: 0.55rem 0.8rem;
    border: 1px solid var(--line, #e2e8f0); border-radius: 0.6rem;
    background: #fff; color: var(--ink, #0f172a);
    font: inherit; font-size: 0.85rem; font-weight: 600;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.gmb-input:focus {
    outline: none; border-color: var(--primary, #6366f1);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16);
}

/* Penanda "boleh dikosongkan" pada label saringan. */
.gmb-opsional {
    margin-left: 0.3rem; padding: 0.05rem 0.35rem; border-radius: 999px;
    background: #f1f5f9; color: #94a3b8;
    font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em;
}

/* Deretan hari yang ada jadwalnya. */
.gmb-hari {
    display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center;
    margin-top: 0.75rem;
}
.gmb-hari__label { font-size: 0.75rem; font-weight: 700; color: var(--muted, #64748b); margin-right: 0.15rem; }
.gmb-hari__btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.35rem 0.65rem; border-radius: 999px;
    background: #fff; border: 1px solid var(--line, #e2e8f0);
    font: inherit; font-size: 0.75rem; font-weight: 700; color: var(--slate, #475569);
    cursor: pointer; transition: all 0.15s ease;
}
.gmb-hari__btn:hover { border-color: var(--primary, #6366f1); color: var(--primary, #6366f1); }
.gmb-hari__btn.is-on { background: var(--primary, #6366f1); border-color: var(--primary, #6366f1); color: #fff; }
.gmb-hari__btn em {
    padding: 0.02rem 0.32rem; border-radius: 999px;
    background: #f1f5f9; color: #64748b; font-size: 0.66rem; font-style: normal; font-weight: 900;
}
.gmb-hari__btn.is-on em { background: rgba(255, 255, 255, 0.28); color: #fff; }

.gmb-spin { animation: gmb-putar 1s linear infinite; }
@keyframes gmb-putar { to { transform: rotate(360deg); } }

/* Hormati pengguna yang meminta gerak dikurangi — animasi putar terus-menerus
   memicu keluhan pada sebagian orang. */
@media (prefers-reduced-motion: reduce) {
    .gmb-spin { animation: none; }
    .gmb-sec { animation: none; }
    .gmb-nav__btn:hover { transform: none; }
}

/* ── Hasil ────────────────────────────────────────────────────────────── */
.gmb-hasil { margin-top: 1.15rem; }
.gmb-galat {
    display: flex; align-items: center; gap: 0.5rem;
    margin: 0.85rem 0 0; padding: 0.7rem 0.95rem; border-radius: 0.7rem;
    background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.28);
    color: #b91c1c; font-size: 0.82rem; font-weight: 700;
}
.gmb-kosong {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 1.4rem; border-radius: 0.9rem;
    background: #f8fafc; border: 1px dashed var(--line, #e2e8f0);
    color: var(--muted, #64748b); font-size: 0.83rem; font-weight: 600; line-height: 1.6;
}
.gmb-kosong i { font-size: 1.1rem; flex: none; }
.gmb-kosong b { color: var(--ink, #0f172a); }
.gmb-nota { margin: 0.6rem 0 0; font-size: 0.78rem; font-weight: 600; line-height: 1.6; color: var(--muted, #64748b); }
.gmb-nota b { color: var(--ink, #0f172a); }
.gmb-warn { margin: 1rem 0 0; }
.gmb-ok { border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.08); }
.gmb-ok i { color: #059669; }

/* Identitas kandidat */
.gmb-ident {
    display: flex; flex-wrap: wrap; gap: 0.9rem; align-items: center;
    padding: 1rem; margin-bottom: 1.15rem; border-radius: 0.9rem;
    background: #f8fafc; border: 1px solid var(--line, #e2e8f0);
}
.gmb-ident--rapat { margin-bottom: 0.9rem; padding: 0.75rem 0.9rem; }
.gmb-ident__ava {
    display: grid; place-items: center; flex: none;
    width: 2.75rem; height: 2.75rem; border-radius: 0.8rem;
    background: linear-gradient(135deg, var(--primary, #6366f1), #8b5cf6);
    color: #fff; font-weight: 900; font-size: 0.85rem;
}
.gmb-ident__isi { flex: 1; min-width: 11rem; display: flex; flex-direction: column; gap: 0.15rem; }
.gmb-ident__isi strong { font-size: 0.95rem; font-weight: 900; color: var(--ink, #0f172a); }
.gmb-ident__isi span { font-size: 0.79rem; font-weight: 600; color: var(--muted, #64748b); }
.gmb-ident__tag { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.35rem; }
.gmb-ident__angka { display: flex; gap: 1.25rem; }
.gmb-ident__angka > div { display: flex; flex-direction: column; align-items: center; }
.gmb-ident__angka b { font-size: 1.25rem; font-weight: 900; color: var(--ink, #0f172a); }
.gmb-ident__angka span { font-size: 0.7rem; font-weight: 700; color: var(--muted, #64748b); }
.gmb-ident__angka .is-bahaya b { color: #b91c1c; }

/* Angka ringkas */
.gmb-angka { display: flex; flex-wrap: wrap; gap: 0.6rem; margin: 1rem 0; }
.gmb-angka > div {
    flex: 1; min-width: 7.5rem;
    display: flex; flex-direction: column; align-items: center; gap: 0.15rem;
    padding: 0.85rem 0.7rem; border-radius: 0.75rem;
    background: #f8fafc; border: 1px solid var(--line, #e2e8f0);
}
.gmb-angka b { font-size: 1.35rem; font-weight: 900; color: var(--ink, #0f172a); }
.gmb-angka span { font-size: 0.7rem; font-weight: 700; color: var(--muted, #64748b); text-align: center; }
.gmb-angka .is-bahaya { background: rgba(239, 68, 68, 0.07); border-color: rgba(239, 68, 68, 0.28); }
.gmb-angka .is-bahaya b { color: #b91c1c; }

.gmb-sub {
    display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.6rem;
    margin: 1.6rem 0 0.7rem; font-size: 0.9rem; font-weight: 900; color: var(--ink, #0f172a);
}
.gmb-sub small { font-size: 0.75rem; font-weight: 600; color: var(--muted, #64748b); }
.gmb-sub small b { color: var(--ink, #0f172a); }

/* ── Tabel ────────────────────────────────────────────────────────────── */
/* Tabel lebar menggulir DI DALAM kotaknya sendiri — badan halaman tidak
   pernah ikut menggulir mendatar. */
.gmb-tabel {
    overflow-x: auto; border: 1px solid var(--line, #e2e8f0);
    border-radius: 0.85rem; background: var(--panel, #fff);
}
.gmb-tabel :deep(.wca-table td) { vertical-align: top; }
.gmb-tabel :deep(.wca-table td b) { display: block; font-weight: 800; font-size: 0.82rem; }
.gmb-tabel :deep(.wca-table td small) {
    display: block; margin-top: 0.1rem;
    font-size: 0.72rem; font-weight: 600; color: var(--muted, #64748b);
}
.gmb-tabel :deep(.wca-table td small.gmb-err) { color: #b91c1c; }
.gmb-tabel :deep(tbody tr.is-terpakai) { background: rgba(239, 68, 68, 0.05); }
.gmb-tabel :deep(tbody tr.is-terpakai:hover) { background: rgba(239, 68, 68, 0.09); }
.gmb-th-cek { width: 2.2rem; }
.gmb-tabel input[type="checkbox"] { width: 0.95rem; height: 0.95rem; accent-color: var(--primary, #6366f1); cursor: pointer; }

.gmb-nil { color: #94a3b8; font-style: italic; font-size: 0.74rem; font-weight: 600; }

.gmb-code {
    display: inline-block; padding: 0.1rem 0.4rem; margin: 0.05rem 0.2rem 0.05rem 0;
    border-radius: 0.35rem; background: #f1f5f9; border: 1px solid var(--line, #e2e8f0);
    font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
    font-size: 0.73rem; font-weight: 700; color: var(--ink, #0f172a);
}
.gmb-code--otp { background: #fef3c7; border-color: #fcd34d; color: #b45309; }

/* ── Bilah pilih ──────────────────────────────────────────────────────── */
.gmb-bar {
    display: flex; flex-wrap: wrap; gap: 0.75rem;
    align-items: center; justify-content: space-between;
    padding: 0.7rem 0.95rem; margin: 1rem 0 0.6rem; border-radius: 0.7rem;
    background: #f1f5f9; border: 1px solid var(--line, #e2e8f0);
}
.gmb-bar__info { font-size: 0.79rem; font-weight: 700; color: var(--muted, #64748b); }
.gmb-bar__info b { color: var(--ink, #0f172a); }
.gmb-cek { display: inline-flex; align-items: center; gap: 0.45rem; cursor: pointer; font-size: 0.82rem; font-weight: 700; }
.gmb-cek input { width: 0.95rem; height: 0.95rem; accent-color: var(--primary, #6366f1); cursor: pointer; }
.gmb-cek--bahaya { display: flex; margin-top: 0.6rem; color: #b91c1c; }
.gmb-cek--bahaya input { accent-color: #ef4444; }

.gmb-aksi { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: flex-end; margin-top: 1.15rem; }
.gmb-aksi--kolom { flex-direction: column; align-items: stretch; max-width: 32rem; }

/* ── Riwayat OTP ──────────────────────────────────────────────────────── */
.gmb-batch {
    border: 1px solid var(--line, #e2e8f0); border-radius: 0.85rem;
    background: var(--panel, #fff); margin-bottom: 0.6rem; overflow: hidden;
}
.gmb-batch__head {
    display: flex; flex-wrap: wrap; gap: 0.75rem;
    align-items: center; justify-content: space-between; padding: 0.85rem 1rem;
}
.gmb-batch__head strong { display: block; font-size: 0.85rem; font-weight: 800; color: var(--ink, #0f172a); }
.gmb-batch__head small { display: block; margin-top: 0.15rem; font-size: 0.73rem; font-weight: 600; color: var(--muted, #64748b); }
.gmb-batch__aksi { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.gmb-batch__isi { padding: 0.85rem 1rem 1rem; border-top: 1px solid var(--line, #e2e8f0); }
.gmb-otplist { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-bottom: 0.75rem; }

.gmb-sqlwrap { position: relative; }
.gmb-sqlwrap__salin { position: absolute; top: 0.6rem; right: 0.6rem; z-index: 1; }
.gmb-sql {
    margin: 0; padding: 0.9rem; border-radius: 0.7rem;
    background: #0f172a; border: 1px solid #1e293b; color: #a5f3d0;
    font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
    font-size: 0.75rem; line-height: 1.65;
    overflow-x: auto; white-space: pre; max-height: 21rem; overflow-y: auto;
}

/* ── Modal ────────────────────────────────────────────────────────────── */
.gmb-modal {
    position: fixed; inset: 0; z-index: 2000;
    display: flex; align-items: center; justify-content: center; padding: 1.25rem;
    background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(3px);
}
.gmb-modal__box {
    width: 100%; max-width: 38rem; max-height: 90vh;
    display: flex; flex-direction: column;
    background: var(--panel, #fff); border-radius: 1rem; overflow: hidden;
    box-shadow: 0 24px 64px rgba(15, 23, 42, 0.28);
}
.gmb-modal__box--lebar { max-width: 48rem; }
.gmb-modal__head {
    display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--line, #e2e8f0);
}
.gmb-modal__head h3 {
    display: flex; align-items: center; gap: 0.5rem; margin: 0;
    font-size: 0.98rem; font-weight: 900; color: var(--ink, #0f172a);
}
.gmb-x {
    display: grid; place-items: center; width: 1.85rem; height: 1.85rem;
    border-radius: 0.5rem; background: transparent; border: none;
    color: var(--muted, #64748b); cursor: pointer; font-size: 0.85rem;
}
.gmb-x:hover { background: #f1f5f9; color: var(--ink, #0f172a); }
.gmb-modal__isi { padding: 1.15rem 1.25rem; overflow-y: auto; }
.gmb-modal__kaki {
    display: flex; justify-content: flex-end; gap: 0.6rem;
    padding: 0.9rem 1.25rem; border-top: 1px solid var(--line, #e2e8f0); background: #f8fafc;
}
.gmb-daftar {
    max-height: 12rem; overflow-y: auto; margin: 0.85rem 0;
    border: 1px solid var(--line, #e2e8f0); border-radius: 0.7rem; background: #f8fafc;
}
.gmb-daftar__baris { padding: 0.6rem 0.85rem; border-bottom: 1px solid var(--line, #e2e8f0); font-size: 0.8rem; }
.gmb-daftar__baris:last-child { border-bottom: none; }
.gmb-daftar__baris b { margin-right: 0.5rem; font-weight: 800; color: var(--ink, #0f172a); }
.gmb-daftar__baris small { display: block; margin-top: 0.1rem; font-size: 0.72rem; font-weight: 600; color: var(--muted, #64748b); }

@media (max-width: 640px) {
    .gmb-ident__angka { width: 100%; justify-content: space-around; }
}
</style>
