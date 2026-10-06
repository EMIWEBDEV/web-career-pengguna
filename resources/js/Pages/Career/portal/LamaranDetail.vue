<!-- WEB CAREER — Portal Kandidat: DETAIL LAMARAN (POV peserta), 1:1 desain
     "Lamaran Saya" (Claude Design). Layout fluid + blob, kartu hero, banner
     status, progres seleksi, kartu Tahap Aktif (tes HCLearn token/OTP/jendela +
     Mulai Tes, ATAU formulir tahap), timeline tahap, akordeon Formulir & Berkas
     Saya + lightbox. Data 100% nyata; fungsi akses tes & kirim formulir utuh. -->
<template>
    <Head title="Detail Lamaran" />

    <div class="ld">
        <div class="ld-blob ld-blob--a"></div>
        <div class="ld-blob ld-blob--b"></div>

        <div class="ld-wrap">
            <!-- [feat/feedback] Banner feedback -->
            <!-- ═══ FEEDBACK CTA BANNER (Eye-Catching & Ultra-Premium) ═══ -->
            <div
                v-if="showFeedbackBanner && feedbackPending"
                :class="[
                    'ld-feedback-banner',
                    feedbackPending.Hasil_Akhir === 'DITERIMA'
                        ? 'ld-feedback-banner--wajib'
                        : 'ld-feedback-banner--optional',
                ]"
            >
                <div class="ld-feedback-banner__glow"></div>
                <div class="ld-feedback-banner__content">
                    <div class="ld-feedback-banner__left">
                        <div class="ld-feedback-banner__icon">
                            <i class="bi bi-stars"></i>
                        </div>
                        <div class="ld-feedback-banner__text">
                            <h4 class="ld-feedback-banner__title">
                                <template v-if="feedbackPending.Hasil_Akhir === 'DITERIMA'"
                                    >Selamat! Mohon isi feedback untuk menyelesaikan proses 🎉</template
                                >
                                <template v-else>Bantu Kami Berbenah &amp; Tingkatkan Layanan ✨</template>
                            </h4>
                            <p class="ld-feedback-banner__sub">
                                {{
                                    feedbackPending.Hasil_Akhir === 'DITERIMA'
                                        ? 'Masukan Anda wajib diisi untuk kelengkapan administrasi.'
                                        : 'Ulasan dan saran Anda sangat berharga bagi evaluasi rekrutmen kami (hanya 1-2 menit).'
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="ld-feedback-banner__actions">
                        <a
                            :href="feedbackPending.feedback_url"
                            class="ld-feedback-banner__btn ld-feedback-banner__btn--fill"
                            title="Bantu kami evaluasi proses rekrutmen ini, masukan Anda sangat berharga!"
                        >
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                <path d="M12 8v4M12 16h.01" />
                            </svg>
                            <span>Bantu Kami Berbenah ✨</span>
                        </a>
                    </div>
                </div>
            </div>

            <Link href="/kandidat/portal" class="ld-back">
                <svg
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M19 12H5M11 18l-6-6 6-6" />
                </svg>
                Lamaran Saya
            </Link>

            <!-- Lamaran sudah tercatat, tapi tim rekrutmen belum memprosesnya
                 (potret portal belum tersedia) — jangan tampilkan tahapan kosong
                 seolah tidak ada apa-apa. -->
            <div v-if="lamaran.menungguPotret" class="ld-proses" role="status">
                <i class="bi bi-hourglass-split"></i>
                <div>
                    <strong>Lamaranmu sudah kami terima dan sedang diproses.</strong>
                    <span>Tahapan seleksi, jadwal, dan formulir lanjutan akan tampil di sini begitu tim rekrutmen memprosesnya — biasanya hanya beberapa saat.</span>
                </div>
            </div>

            <!-- ═══ HERO HEADER ═══ -->
            <div class="ld-hero">
                <span class="ld-hero__bar"></span>
                <div class="ld-hero__glow"></div>
                <div class="ld-hero__in">
                    <div class="ld-hero__avatar">
                        <svg
                            v-if="lamaran.kategori === 'MT'"
                            width="26"
                            height="26"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M22 10L12 5 2 10l10 5 10-5z" />
                            <path d="M6 12v5c3 3 9 3 12 0v-5" />
                        </svg>
                        <svg
                            v-else
                            width="26"
                            height="26"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect x="3" y="7" width="18" height="13" rx="2" />
                            <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        </svg>
                    </div>
                    <div style="flex: 1; min-width: 0">
                        <div class="ld-hero__badges">
                            <span class="ld-chip" :style="katChipStyle">{{ katLabel(lamaran.kategori) }}</span>
                            <span class="ld-chip" :style="stChipStyle">
                                <span v-if="stKey === 'berjalan'" class="ld-pulse"></span>
                                <svg
                                    v-else-if="stKey === 'lolos'"
                                    width="13"
                                    height="13"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.6"
                                    stroke-linecap="round"
                                >
                                    <path d="M20 6L9 17l-5-5" />
                                </svg>
                                <svg
                                    v-else
                                    width="13"
                                    height="13"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.2"
                                    stroke-linecap="round"
                                >
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M15 9l-6 6M9 9l6 6" />
                                </svg>
                                {{ stLabel(lamaran.status) }}
                            </span>
                        </div>
                        <h1 class="ld-hero__title">{{ lamaran.posisi || lamaran.program }}</h1>
                        <div class="ld-hero__meta">
                            {{ lamaran.program }}<template v-if="lamaran.lokasi"> · {{ lamaran.lokasi }}</template> ·
                            Dilamar {{ fmtTanggal(lamaran.waktuLamar) }} ·
                            <span class="ld-mono">{{ lamaran.kode }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ BANNER STATUS ═══ -->
            <div class="ld-banner" :style="{ background: ST[stKey].bg, borderColor: ST[stKey].bg }">
                <span class="ld-banner__ico" :style="{ background: ST[stKey].dot }">
                    <svg
                        v-if="stKey === 'lolos'"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                    >
                        <path d="M20 6L9 17l-5-5" />
                    </svg>
                    <svg
                        v-else-if="stKey === 'gugur'"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                    >
                        <circle cx="12" cy="12" r="9" />
                        <path d="M15 9l-6 6M9 9l6 6" />
                    </svg>
                    <svg
                        v-else
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                    >
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v5l3 2" />
                    </svg>
                </span>
                <div style="min-width: 0">
                    <div class="ld-banner__title" :style="{ color: ST[stKey].c }">{{ banner.title }}</div>
                    <div class="ld-banner__text" :style="{ color: ST[stKey].c }">{{ banner.text }}</div>
                </div>
            </div>

            <!-- ═══ KEPUTUSAN TAHAP ═══
                 Muncul begitu admin mengetuk palu DAN hasilnya memang sudah boleh
                 diumumkan (Mode Pengumuman). Server yang menahan: kalau belum
                 boleh, `hasil` tidak dikirim sama sekali ke sini. -->
            <transition name="ld-verdict">
                <div v-if="putusanTahap" class="ld-verdict" :class="putusanTahap.lolos ? 'is-lolos' : 'is-gugur'">
                    <span class="ld-nico" aria-hidden="true">
                        <svg viewBox="0 0 44 44" width="40" height="40">
                            <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                            <circle class="ld-nico__ring" cx="22" cy="22" r="13.5" />
                            <path v-if="putusanTahap.lolos" class="ld-nico__check" d="M15.8 22.3l4.3 4.3 8.1-8.9" />
                            <g v-else class="ld-nico__cross">
                                <line class="ld-nico__bang" x1="17" y1="17" x2="27" y2="27" />
                                <line class="ld-nico__bang" x1="27" y1="17" x2="17" y2="27" />
                            </g>
                        </svg>
                    </span>
                    <div style="min-width: 0; flex: 1">
                        <div class="ld-verdict__eyebrow">
                            TAHAP {{ putusanTahap.urutan }} DARI {{ totalTahap }} · KEPUTUSAN
                        </div>
                        <div class="ld-verdict__title">
                            {{ putusanTahap.label }} — {{ putusanTahap.lolos ? 'Lolos' : 'Tidak Lolos' }}
                        </div>
                        <p class="ld-verdict__text">{{ putusanTahap.teks }}</p>

                        <!-- Lolos & lanjut: catatannya menunggu di kartu tahap
                             berikutnya — di sini cukup penunjuk ke sana. -->
                        <a
                            v-if="catatanMasuk && catatanMasuk.urutan === putusanTahap.urutan"
                            href="#ld-ctim"
                            class="ld-verdict__cat"
                            @click.prevent="keCatatan"
                        >
                            <i class="bi bi-megaphone-fill"></i> Ada catatan untuk tahap {{ catatanMasuk.berikut || 'berikutnya' }}
                            <i class="bi bi-arrow-down-short"></i>
                        </a>

                        <!-- Tidak lolos, atau tahap terakhir: tidak ada tahap
                             berikutnya untuk ditempati, jadi catatannya di sini. -->
                        <div v-if="catatanPutusan" class="ld-ctim ld-ctim--putusan">
                            <div class="ld-ctim__hd">
                                <span class="ld-ctim__ic" aria-hidden="true"><i class="bi bi-megaphone-fill"></i></span>
                                <div class="ld-ctim__ttl">
                                    <b>Catatan</b>
                                    <small v-if="catatanPutusan.at">{{ fmtWaktu(catatanPutusan.at) }}</small>
                                </div>
                            </div>
                            <KontenAman :html="tautkan(catatanPutusan.html)" class="ld-ctim__html" />
                            <details v-if="!tahapAktif && catatanLama.length" class="ld-ctim__lama">
                                <summary><i class="bi bi-clock-history"></i> Catatan sebelumnya ({{ catatanLama.length }})</summary>
                                <article v-for="c in catatanLama" :key="c.urutan" class="ld-ctim__item">
                                    <div class="ld-ctim__itemhd">
                                        <b>{{ judulCatatan(c) }}</b>
                                        <span v-if="c.at">{{ fmtD(c.at) }}</span>
                                    </div>
                                    <KontenAman :html="tautkan(c.html)" class="ld-ctim__html" />
                                </article>
                            </details>
                        </div>
                    </div>
                    <span class="ld-verdict__badge">{{ putusanTahap.lolos ? 'LOLOS' : 'TIDAK LOLOS' }}</span>
                </div>
            </transition>

            <!-- Keputusan sudah ada tapi belum waktunya diumumkan. Kandidat tetap
                 diberi tahu bahwa tahapnya SUDAH diputus, tanpa isinya. -->
            <div v-if="!putusanTahap && tahapTertunda" class="ld-verdict is-tunda">
                <span class="ld-nico" aria-hidden="true">
                    <svg viewBox="0 0 44 44" width="40" height="40">
                        <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                        <circle class="ld-nico__ring" cx="22" cy="22" r="13.5" />
                        <line class="ld-nico__hand ld-nico__hand--h" x1="22" y1="22" x2="22" y2="16" />
                        <line class="ld-nico__hand ld-nico__hand--m" x1="22" y1="22" x2="26.5" y2="22" />
                        <circle class="ld-nico__pin" cx="22" cy="22" r="1.6" />
                    </svg>
                </span>
                <div style="min-width: 0; flex: 1">
                    <div class="ld-verdict__eyebrow">
                        TAHAP {{ tahapTertunda.urutan }} DARI {{ totalTahap }} · MENUNGGU PENGUMUMAN
                    </div>
                    <div class="ld-verdict__title">{{ tahapTertunda.label }} sudah diputuskan</div>
                    <p class="ld-verdict__text">
                        {{ tahapTertunda.pengumuman?.label || 'Hasilnya diumumkan menyusul.'
                        }}<template v-if="tahapTertunda.pengumuman?.tanggal">
                            Dijadwalkan {{ fmtWaktu(tahapTertunda.pengumuman.tanggal) }}.</template
                        >
                    </p>
                </div>
            </div>

            <!-- ═══ PROGRES SELEKSI ═══ -->
            <!-- <details>, bukan accordion buatan sendiri: buka-tutup, keyboard,
                 dan Ctrl+F sudah ditangani browser. Ringkasan tetap membawa bar
                 progres + "Tahap X dari Y" sehingga status terbaca tanpa dibuka. -->
            <details class="ld-prog" :open="!kompak">
                <summary class="ld-prog__sum">
                    <span class="ld-prog__head">
                        <span class="ld-seclabel">PROGRES SELEKSI</span>
                        <span class="ld-prog__count">
                            Tahap {{ posisiSekarang }} dari {{ totalTahap }} · {{ persen }}%
                            <svg class="ld-prog__chev" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
                        </span>
                    </span>
                    <span class="ld-prog__bar"><span :style="{ width: persen + '%' }"></span></span>
                    <!-- Nama tahap berjalan ikut di ringkasan — sama dengan kartu
                         di halaman Lamaran Saya. Disembunyikan saat stepper dibuka
                         karena tahap yang sama sudah ditandai di dalamnya. -->
                    <span v-if="tahapRingkas" class="ld-prog__now" :class="'is-' + tahapRingkas.st">
                        <span class="ld-prog__now-dot"></span>
                        <span class="ld-prog__now-lbl">{{ tahapRingkas.teks }}</span>
                        <strong>{{ tahapRingkas.name }}</strong>
                    </span>
                </summary>
                <div class="ld-steps">
                    <div v-for="(s, i) in heroSteps" :key="i" class="ld-step">
                        <div v-if="i > 0" class="ld-step__line" :style="{ background: s.line }"></div>
                        <div class="ld-step__node" :class="'is-' + s.st">
                            <svg
                                v-if="s.st === 'done'"
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M20 6L9 17l-5-5" />
                            </svg>
                            <svg
                                v-else-if="s.st === 'fail'"
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.6"
                                stroke-linecap="round"
                            >
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                            <template v-else>{{ i + 1 }}</template>
                        </div>
                        <div class="ld-step__lbl" :style="{ color: s.lbl }">{{ s.name }}</div>
                    </div>
                </div>
            </details>

            <div class="ld-mainc">
                <!-- ══ TAHAP AKTIF — SATU KARTU, SATU BLOK PER AKTIVITAS ══
                     Dulu di sini berdiri TIGA kartu yang saling meniadakan
                     (`v-if` tes online / `v-else-if` formulir / `v-else-if`
                     ditangani tim), dan masing-masing merender ulang JadwalKartu,
                     UnggahAktivitas, serta hasil MCU-nya sendiri. Tiga salinan
                     berarti tiga peluang menyimpang, dan itu sudah terjadi
                     berkali-kali — komentar di sekitarnya masih menyimpan
                     jejaknya ("sebelumnya keduanya hanya dirender di kartu tes
                     online").

                     Lebih buruk lagi, isi kartu pertama seluruhnya digerbangi
                     satu baris: `v-if="!sesi.ujian?.terjadwal"`. `sesi` selalu
                     aktivitas ONLINE, jadi psikotes yang belum dijadwalkan
                     menyembunyikan kartu jadwal FGD dan tombol unggah berkasnya
                     sekaligus — kandidat membaca "Menunggu dijadwalkan" untuk
                     aktivitas yang sebenarnya sudah dijadwalkan dan sedang
                     menunggu dia.

                     Sekarang: satu kartu tahap, lalu satu blok mandiri per
                     aktivitas, urut sesuai setelan alur. Tiap blok membaca
                     keadaannya dari DIRINYA SENDIRI, jadi tidak ada aktivitas
                     yang bisa menutupi aktivitas lain. -->
                <div v-if="tahapAktif" class="ld-card ld-act">
                    <div class="ld-act__head">
                        <span class="ld-act__ico">
                            <svg v-if="tesList.length" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 10L12 5 2 10l10 5 10-5z" />
                                <path d="M6 12v5c3 3 9 3 12 0v-5" />
                            </svg>
                            <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            </svg>
                        </span>
                        <div style="min-width: 0">
                            <div class="ld-eyebrow">
                                TAHAP {{ tahapAktif.urutan }} DARI {{ totalTahap }} ·
                                {{ (tahapAktif.tipeNama || 'Proses Seleksi').toUpperCase() }}
                            </div>
                            <!-- Judul memakai NAMA TAHAP, bukan nama alat tesnya.
                                 Nama instrumen ("General Reasoning Test") dan
                                 platform penyelenggara adalah informasi internal
                                 rekrutmen — membocorkannya memudahkan kandidat
                                 mencari bocoran soal, dan tidak ada gunanya
                                 bagi dia. -->
                            <div class="ld-act__title">{{ tahapAktif.label }}</div>
                            <div class="ld-act__sub">
                                {{ subTahap }}
                            </div>
                        </div>
                    </div>

                    <!-- ══ CATATAN DARI TIM — "kamu lolos, ini catatan kami" ══
                         Catatan yang ditulis tim saat memutus tahap SEBELUMNYA
                         menjadi catatan tahap ini: link Zoom psikotes ditulis
                         ketika Seleksi Administrasi diloloskan, dan dibaca
                         ketika kandidat berada di tahap Psikotes. Karena itu ia
                         duduk paling atas di kartu tahap aktif — sebelum
                         aktivitas mana pun. Server baru mengirim isinya setelah
                         hasil tahap sebelumnya boleh diumumkan. -->
                    <section v-if="catatanMasuk || catatanLama.length" id="ld-ctim" class="ld-ctim" aria-labelledby="ld-ctim-judul">
                        <template v-if="catatanMasuk">
                            <div class="ld-ctim__hd">
                                <span class="ld-ctim__ic" aria-hidden="true"><i class="bi bi-megaphone-fill"></i></span>
                                <div class="ld-ctim__ttl">
                                    <!-- Cukup "Catatan" — tanpa "dari Tim Rekrutmen" (permintaan user). -->
                                    <b id="ld-ctim-judul">Catatan</b>
                                    <small>
                                        <template v-if="catatanMasuk.hasil === 'LULUS'">
                                            Kamu lolos {{ catatanMasuk.label }} — berikut pesan kami untuk tahap ini.
                                        </template>
                                        <template v-else>Dari keputusan tahap {{ catatanMasuk.label }}.</template>
                                    </small>
                                </div>
                            </div>
                            <KontenAman :html="tautkan(catatanMasuk.html)" class="ld-ctim__html" />
                            <div v-if="catatanMasuk.at" class="ld-ctim__waktu">
                                <i class="bi bi-clock"></i> {{ fmtWaktu(catatanMasuk.at) }}
                            </div>
                        </template>
                        <details v-if="catatanLama.length" class="ld-ctim__lama">
                            <summary>
                                <i class="bi bi-clock-history"></i>
                                {{ catatanMasuk ? 'Catatan sebelumnya' : 'Catatan tahap sebelumnya' }} ({{ catatanLama.length }})
                            </summary>
                            <article v-for="c in catatanLama" :key="c.urutan" class="ld-ctim__item">
                                <div class="ld-ctim__itemhd">
                                    <b>{{ judulCatatan(c) }}</b>
                                    <span v-if="c.at">{{ fmtD(c.at) }}</span>
                                </div>
                                <KontenAman :html="tautkan(c.html)" class="ld-ctim__html" />
                            </article>
                        </details>
                    </section>

                    <!-- KEADAAN TAHAP — SATU KALIMAT, BUKAN DAFTAR.
                         Dulu di sini berdiri daftar seluruh aktivitas berikut
                         statusnya ("DISC — Menunggu", "FGD — Menunggu"). Itu
                         membuka susunan asesmen perusahaan kepada orang yang
                         sedang dinilai: ia tahu persis alat ukur apa yang
                         dipakai dan yang mana belum dilalui, dan itu bisa
                         dipersiapkan.

                         Yang tersisa hanya yang benar-benar berguna baginya:
                         sedang menunggu apa, dan apakah ada yang harus ia
                         kerjakan. Rincian tiap aktivitas tetap tampil di
                         bloknya masing-masing di bawah — itu memang undangan
                         untuk dia. -->
                    <div v-if="aktivitasTampil.length > 1" class="ld-keadaan" :class="`is-${keadaanTahap.nada}`">
                        <!-- IKON SVG BERANIMASI, bukan glyph font. Tiap keadaan
                             punya gerakannya sendiri dan gerakan itulah yang
                             menyampaikan artinya sebelum kalimatnya dibaca.
                             Seluruhnya dimatikan otomatis bagi pengguna yang
                             meminta gerak dikurangi. -->
                        <span class="ld-keadaan__ico" aria-hidden="true">
                            <svg viewBox="0 0 48 48" width="30" height="30" fill="none">
                                <circle class="ld-kico__halo" cx="24" cy="24" r="21" />

                                <!-- MENUNGGU JADWAL — jarum jam berputar pelan. -->
                                <template v-if="keadaanTahap.nada === 'tunggu'">
                                    <circle class="ld-kico__ring" cx="24" cy="24" r="14" />
                                    <line class="ld-kico__jarum ld-kico__jarum--h" x1="24" y1="24" x2="24" y2="16.5" />
                                    <line class="ld-kico__jarum ld-kico__jarum--m" x1="24" y1="24" x2="29.5" y2="24" />
                                    <circle class="ld-kico__pin" cx="24" cy="24" r="1.7" />
                                </template>

                                <!-- DITAHAN TIM — gembok dengan gagang yang berdenyut. -->
                                <template v-else-if="keadaanTahap.nada === 'tinjau'">
                                    <path class="ld-kico__gagang" d="M18.5 22v-3.5a5.5 5.5 0 0 1 11 0V22" />
                                    <rect class="ld-kico__body" x="15.5" y="22" width="17" height="12.5" rx="3" />
                                    <circle class="ld-kico__pin" cx="24" cy="28.2" r="1.8" />
                                </template>

                                <!-- DITUNDA TIM — tanda jeda: jadwalnya berhenti, belum batal. -->
                                <template v-else-if="keadaanTahap.nada === 'tunda'">
                                    <circle class="ld-kico__ring" cx="24" cy="24" r="14" />
                                    <line class="ld-kico__jarum" x1="21" y1="18.5" x2="21" y2="29.5" />
                                    <line class="ld-kico__jarum" x1="27" y1="18.5" x2="27" y2="29.5" />
                                </template>

                                <!-- SUDAH DIJADWALKAN — kalender dengan centang tergambar. -->
                                <template v-else-if="keadaanTahap.nada === 'jadwal'">
                                    <rect class="ld-kico__ring" x="14" y="15.5" width="20" height="18" rx="3" />
                                    <line class="ld-kico__jarum" x1="14" y1="21" x2="34" y2="21" />
                                    <line class="ld-kico__jarum" x1="19.5" y1="13" x2="19.5" y2="17" />
                                    <line class="ld-kico__jarum" x1="28.5" y1="13" x2="28.5" y2="17" />
                                    <path class="ld-kico__check" d="M19.5 27.5l3.4 3.4 6-6.4" />
                                </template>

                                <!-- BISA DIKERJAKAN — tombol putar yang berdenyut. -->
                                <template v-else-if="keadaanTahap.nada === 'aksi'">
                                    <circle class="ld-kico__ring ld-kico__ring--denyut" cx="24" cy="24" r="14" />
                                    <path class="ld-kico__play" d="M21 18.8l9 5.2-9 5.2z" />
                                </template>

                                <!-- BARU TERKIRIM — pesawat kertas melaju,
                                     centang menyusul saat ia mendarat. -->
                                <template v-else-if="keadaanTahap.nada === 'kirim'">
                                    <path class="ld-kico__pesawat" d="M33.5 15.5L14 23.2l7.6 2.6 2.6 7.6z" />
                                    <path class="ld-kico__check" d="M21.6 25.8l11.9-10.3" />
                                </template>

                                <!-- SEDANG DITINJAU — pasir jam mengalir. -->
                                <template v-else>
                                    <path class="ld-kico__ring" d="M17 14h14M17 34h14M18.5 14c0 6 5.5 7.4 5.5 10s-5.5 4-5.5 10M29.5 14c0 6-5.5 7.4-5.5 10s5.5 4 5.5 10" />
                                    <circle class="ld-kico__pasir" cx="24" cy="24" r="1.5" />
                                </template>
                            </svg>
                        </span>
                        <div class="ld-keadaan__teks">
                            <b>{{ keadaanTahap.judul }}</b>
                            <p>{{ keadaanTahap.pesan }}</p>
                        </div>
                    </div>

                    <!-- FORMULIR TAHAP.
                         Dulu ini cabang `v-else-if` tersendiri, jadi tahap yang
                         memuat formulir DAN ujian online hanya menampilkan salah
                         satunya — formulirnya tak pernah muncul, tanpa galat di
                         mana pun. Sekarang ia satu bagian di dalam kartu yang
                         sama, berdampingan dengan aktivitas lainnya. -->
                    <div v-if="tugas && (komponen || tugas.schema)" class="ld-act__form">
                        <!-- BATAS PENGISIAN — di atas formulir, karena menentukan
                             sampai kapan formulir di bawahnya bisa dikirim. Lewat
                             batas (mode kunci): formulirnya diganti pesan; isian
                             yang tersimpan tetap aman bila tim memperpanjang. -->
                        <div v-if="tugas.batas && (tugas.batas.batas || tugas.batas.belumDiatur)" class="ld-batas" :class="kelasBatasPortal" role="status">
                            <span class="ld-batas__ic" aria-hidden="true">
                                <i class="bi" :class="formTerkunci ? 'bi-lock-fill' : 'bi-hourglass-split'"></i>
                            </span>
                            <div class="ld-batas__isi">
                                <!-- Jadwal pengisian belum diatur tim: terkunci. -->
                                <template v-if="tugas.batas.belumDiatur">
                                    <b>Formulir belum dibuka</b>
                                    <small>Tim rekrutmen akan mengumumkan jadwal pengisiannya. Pantau halaman ini dan email kamu.</small>
                                </template>
                                <!-- Waktu dibukanya belum tiba: terkunci sampai itu. -->
                                <template v-else-if="belumBukaLive">
                                    <b>Formulir dibuka {{ tugas.batas.bukaTeks }}</b>
                                    <small>
                                        Kamu bisa mulai mengisi <strong>{{ durasiTeks(bukaMs - now) }}</strong> lagi,
                                        paling lambat {{ tugas.batas.teks }}.
                                    </small>
                                </template>
                                <template v-else-if="formTerkunci">
                                    <b>Batas pengisian sudah lewat</b>
                                    <small>
                                        Formulir ini terkunci sejak {{ tugas.batas.teks }}. Isianmu yang sudah tersimpan tidak
                                        hilang — hubungi tim rekrutmen bila memerlukan perpanjangan.
                                    </small>
                                </template>
                                <template v-else>
                                    <b>Kirim sebelum {{ tugas.batas.teks }}</b>
                                    <small>
                                        Sisa waktu <strong>{{ sisaBatasTeks }}</strong>.
                                        Setelah lewat, formulir terkunci dan tidak bisa dikirim lagi.
                                    </small>
                                </template>
                            </div>
                        </div>
                        <DynamicForm
                            v-if="tugas.schema && !formTerkunci"
                            v-model="jawaban"
                            :skema="tugas.schema"
                            :judul="tugas.formulirNama || tugas.label"
                            :konteks="konteksForm"
                            :langkah-awal="langkahAwal"
                            :disabled="mengirim"
                            label-kirim="Kirim &amp; Lanjutkan"
                            @kirim="kirim"
                            @berkas="onBerkas"
                            @hapus-baris="onHapusBaris"
                            @pindah-langkah="simpanDraf"
                        />
                        <component
                            v-else-if="!formTerkunci"
                            :is="komponen"
                            v-model="jawaban"
                            :konteks="konteksForm"
                            :langkah-awal="langkahAwal"
                            :disabled="mengirim"
                            label-kirim="Kirim &amp; Lanjutkan"
                            @kirim="kirim"
                            @berkas="onBerkas"
                            @hapus-baris="onHapusBaris"
                            @pindah-langkah="simpanDraf"
                        />
                    </div>

                    <!-- ══ SATU BLOK PER AKTIVITAS ══
                         Urutannya mengikuti `Urutan` yang disetel di Master Alur —
                         FGD dulu, lalu psikotes, lalu wawancara — sehingga apa
                         yang dibaca kandidat sama persis dengan yang dilihat tim
                         di worklist. Tiap blok berbingkai dan berlencana sendiri
                         supaya dua aktivitas yang dijadwalkan bersamaan tetap
                         terbaca sebagai dua hal terpisah. -->
                    <section
                        v-for="a in aktivitasKartu"
                        :key="a.id"
                        class="ld-akt"
                        :class="`is-${a.keadaan.nada}`"
                    >
                        <header class="ld-akt__head">
                            <span class="ld-akt__no">{{ a.nomor }}</span>
                            <div class="ld-akt__id">
                                <div class="ld-akt__label">{{ a.label }}</div>
                                <div v-if="a.tipeNama" class="ld-akt__tipe">{{ a.tipeNama }}</div>
                            </div>
                            <span class="ld-akt__badge" :class="`is-${a.keadaan.nada}`">{{ a.keadaan.label }}</span>
                        </header>

                        <div class="ld-akt__body">
                            <!-- Blok "Belum gilirannya" beserta pembungkusnya
                                 DIHAPUS: aktivitas yang belum gilirannya tidak
                                 lagi dirender sama sekali, jadi tidak ada lagi
                                 dua cabang yang perlu dipisahkan. Lihat
                                 aktivitasKartu().

                                 Pembungkusnya WAJIB ikut dibuang: tag template
                                 tanpa direktif (v-if / v-for / #slot) tidak
                                 merender isinya sama sekali di Vue 3 -- ia
                                 menelan seluruh isi kartu, termasuk jadwal
                                 dan petanya. -->
                            <!-- ══ SUDAH BERAKHIR — DIDAHULUKAN DARI KEADAAN MANA PUN ══
                                 Dikerjakan kandidat atau ditutup tim, dua-duanya sama:
                                 tak ada yang tersisa untuk ia kerjakan, dan tak satu pun
                                 cabang di bawah boleh menyuruhnya menunggu apa pun selain
                                 hasil. Urutan ketiga cabang ini bukan selera — lihat
                                 blokAktivitas(). -->
                            <div v-if="blokAktivitas(a) === 'selesai'" class="ld-notice ld-notice--done">
                                <span class="ld-nico ld-nico--done" aria-hidden="true">
                                    <svg viewBox="0 0 44 44" width="34" height="34">
                                        <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                                        <!-- Cincin gema: berdenyut keluar dua kali lalu diam. Yang
                                             membedakan "selesai" dari sekadar centang statis. -->
                                        <circle class="ld-nico__gema" cx="22" cy="22" r="13.5" />
                                        <circle class="ld-nico__ring" cx="22" cy="22" r="13.5" />
                                        <path class="ld-nico__check" d="M15.8 22.3l4.3 4.3 8.1-8.9" />
                                    </svg>
                                </span>
                                <div>
                                    <b>{{ judulSelesai(a) }}</b>
                                    <p>{{ pesanSelesai(a) }}</p>
                                </div>
                            </div>

                            <!-- UJIAN ONLINE YANG BELUM PUNYA SESI.
                                 Keadaan MILIK AKTIVITAS INI saja — tidak lagi
                                 menutupi aktivitas lain di tahap yang sama. -->
                            <div v-else-if="blokAktivitas(a) === 'jadwal'" class="ld-notice ld-notice--wait">
                                <span class="ld-nico ld-nico--wait" aria-hidden="true">
                                    <svg viewBox="0 0 44 44" width="34" height="34">
                                        <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                                        <g class="ld-nico__glass">
                                            <path class="ld-nico__stroke" d="M14.5 12.5h15l-7.5 9.5zM14.5 31.5h15l-7.5-9.5z" />
                                            <line class="ld-nico__stroke" x1="13.5" y1="12.5" x2="30.5" y2="12.5" />
                                            <line class="ld-nico__stroke" x1="13.5" y1="31.5" x2="30.5" y2="31.5" />
                                        </g>
                                        <circle class="ld-nico__sand" cx="22" cy="22" r="1.4" />
                                    </svg>
                                </span>
                                <div>
                                    <b>Menunggu dijadwalkan</b>
                                    <p>
                                        Tim rekrutmen sedang menyiapkan jadwalnya — token, kode OTP, dan waktu
                                        pengerjaan akan muncul di sini begitu terbit, dan kamu diberi tahu lewat
                                        email.
                                    </p>
                                </div>
                            </div>

                            <template v-else>
                                <!-- KREDENSIAL — hanya ada di cabang ini, jadi ia
                                     hilang sendiri begitu tesnya berakhir: token yang
                                     tak bisa dipakai lagi membuat kandidat mengira
                                     masih ada yang harus dibuka. -->
                                <div v-if="a.eksternal" class="ld-cred">
                                    <div class="ld-cred__item">
                                        <span class="ld-cred__lbl">TOKEN AKSES</span>
                                        <span class="ld-cred__val ld-mono">{{ a.ujian.token || '—' }}</span>
                                    </div>
                                    <div class="ld-cred__item">
                                        <span class="ld-cred__lbl">KODE OTP</span>
                                        <span class="ld-cred__val ld-mono">{{ a.ujian.otp || '—' }}</span>
                                    </div>
                                    <div class="ld-cred__item">
                                        <span class="ld-cred__lbl">WAKTU MULAI</span>
                                        <span class="ld-cred__val">{{ fmtWaktu(a.ujian.waktuMulai) }}</span>
                                    </div>
                                    <div class="ld-cred__item">
                                        <span class="ld-cred__lbl">WAKTU BERAKHIR</span>
                                        <span class="ld-cred__val">{{ fmtWaktu(a.ujian.waktuSelesai) }}</span>
                                    </div>
                                </div>

                                <!-- JADWAL TATAP MUKA (wawancara / tes offline / MCU). -->
                                <div v-if="a.jadwal" class="ld-jdwwrap">
                                    <JadwalKartu
                                        :jadwal="{ label: a.label, ...a.jadwal }"
                                        :konfirmasi="a.konfirmasi || null"
                                        @konfirmasi-berubah="muatUlangKonfirmasi"
                                    />
                                </div>
                                <!-- DITUNDA tim — alasan, perkiraan jadwal pengganti
                                     (atau "akan dikabarkan"), dan pesan tim. -->
                                <div v-else-if="a.konfirmasi && a.konfirmasi.status === 'DITUNDA'" class="ld-jdwwrap">
                                    <JadwalTunda :tunda="a.konfirmasi.tunda" :label="a.label" :kalimat="a.konfirmasi.kalimat" :warna="a.konfirmasi.warna" />
                                </div>

                                <!-- BERKAS YANG HARUS KANDIDAT UNGGAH.
                                     Dikirim sebagai daftar berisi SATU aktivitas
                                     — komponennya memang menerima daftar, dan
                                     dengan begitu tombol unggahnya berdiri di
                                     dalam blok aktivitas yang memintanya, bukan
                                     di tumpukan terpisah di dasar kartu.
                                
                                     Syaratnya lihat unggahSiap(): kotak ini menunggu
                                     jadwalnya terbit dulu untuk aktivitas yang memang
                                     dijadwalkan tim. -->
                                <UnggahAktivitas
                                    v-if="unggahSiap(a)"
                                    :daftar="[{ key: a.urutan, id: a.id, label: a.label, ...a.unggah }]"
                                    :berkas="berkasTes" :unggah-di="unggahDi" :kirim-di="kirimDi"
                                    @pilih="unggahBerkasTes" @hapus="hapusBerkasTes"
                                    @buka="bukaDok" @kirim="kirimBerkasTes"
                                />

                                <!-- ── KEADAAN UJIAN ONLINE ──
                                     Seluruhnya dilewati bila aktivitasnya
                                     sudah tuntas: tak ada token yang masih
                                     berlaku, tak ada tombol yang masih boleh
                                     ditekan, dan lencana di kepala blok sudah
                                     berbunyi "Selesai". -->
                                <template v-if="a.eksternal">
                                    <div v-if="belumMulai(a)" class="ld-notice ld-notice--wait">
                                        <span class="ld-nico ld-nico--wait" aria-hidden="true">
                                            <svg viewBox="0 0 44 44" width="34" height="34">
                                                <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                                                <circle class="ld-nico__ring" cx="22" cy="22" r="13.5" />
                                                <line class="ld-nico__hand ld-nico__hand--h" x1="22" y1="22" x2="22" y2="16" />
                                                <line class="ld-nico__hand ld-nico__hand--m" x1="22" y1="22" x2="26.5" y2="22" />
                                                <circle class="ld-nico__pin" cx="22" cy="22" r="1.6" />
                                            </svg>
                                        </span>
                                        <div>
                                            <b>Tes belum dibuka</b>
                                            <p>
                                                Tombol akan aktif otomatis saat waktu mulai tiba{{
                                                    hitungMundur(a) ? ' — ' + hitungMundur(a) : ''
                                                }}.
                                            </p>
                                        </div>
                                    </div>
                                    <div v-else-if="sudahLewat(a)" class="ld-notice ld-notice--err">
                                        <span class="ld-nico ld-nico--err" aria-hidden="true">
                                            <svg viewBox="0 0 44 44" width="34" height="34">
                                                <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                                                <path class="ld-nico__tri" d="M22 12.2 33.2 31.2H10.8z" />
                                                <line class="ld-nico__bang" x1="22" y1="19.6" x2="22" y2="24.4" />
                                                <circle class="ld-nico__dot" cx="22" cy="27.6" r="1.4" />
                                            </svg>
                                        </span>
                                        <div>
                                            <b>Waktu tes berakhir</b>
                                            <p>Jendela pengerjaan sudah lewat. Hubungi tim rekrutmen bila ada kendala.</p>
                                        </div>
                                    </div>

                                    <!-- LAYAR ANTARA — kandidat tahu ia sedang
                                         dipindahkan, bukan halaman yang tiba-tiba
                                         hilang. Terikat pada AKTIVITAS INI lewat
                                         `menujuId`: tanpa itu, hitungan mundurnya
                                         muncul serentak di semua blok ujian. -->
                                    <div v-if="menujuUjian && menujuId === a.id" class="ld-pindah">
                                        <span class="ld-pindah__ring" aria-hidden="true">
                                            <svg viewBox="0 0 48 48" width="34" height="34" fill="none">
                                                <circle class="ld-pindah__jalur" cx="24" cy="24" r="19" />
                                                <circle class="ld-pindah__isi" cx="24" cy="24" r="19" />
                                            </svg>
                                            <b>{{ hitungPindah }}</b>
                                        </span>
                                        <div class="ld-pindah__teks">
                                            <b>Menyiapkan ruang ujian…</b>
                                            <p>Kamu akan dipindahkan ke halaman ujian dalam {{ hitungPindah }} detik. Jangan tutup halaman ini.</p>
                                        </div>
                                        <button type="button" class="ld-pindah__batal" @click="batalKeUjian">Batal</button>
                                    </div>
                                    <!-- BARU PULANG DARI RUANG UJIAN, hasilnya
                                         belum sampai. Menggantikan tombol, tidak
                                         sekadar mematikannya: tombol mati pun masih
                                         terbaca sebagai "tesnya masih menungguku",
                                         padahal ia sudah menekan kirim semenit
                                         yang lalu. -->
                                    <div v-else-if="tesDitunggu && tesDitunggu.id === a.id" class="ld-terkirim">
                                        <span class="ld-terkirim__ring" aria-hidden="true">
                                            <svg viewBox="0 0 44 44" width="32" height="32" fill="none">
                                                <circle class="ld-terkirim__halo" cx="22" cy="22" r="17" />
                                                <path class="ld-terkirim__pesawat" d="M31 13.5L12.5 21l7.2 2.5 2.5 7.2z" />
                                            </svg>
                                        </span>
                                        <div class="ld-terkirim__teks">
                                            <b>Jawabanmu sudah terkirim</b>
                                            <p>Hasilnya sedang diterima sistem. Halaman ini memperbarui dirinya sendiri — tidak perlu kamu muat ulang.</p>
                                        </div>
                                    </div>
                                    <!-- Tombolnya hanya ada di cabang ini, dan cabang ini
                                         hanya dimasuki ujian yang BELUM berakhir — yang
                                         sudah dikerjakan berhenti di cabang teratas.
                                         Menampilkan tombol mati pun mengesankan tes bisa
                                         diulang. -->
                                    <button
                                        v-else
                                        class="ld-btn-tes"
                                        :disabled="!bisaAkses(a)"
                                        :onClick="!bisaAkses(a) ? null : () => bukaTes(a)"
                                    >
                                        <svg v-if="bisaAkses(a)" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M15 3h6v6M10 14L21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                                        </svg>
                                        <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                                            <rect x="4" y="11" width="16" height="10" rx="2" />
                                            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                                        </svg>
                                        {{ bisaAkses(a) ? 'Mulai Tes Sekarang' : 'Tes Terkunci' }}
                                    </button>
                                </template>

                                <!-- AKTIVITAS TIM YANG BELUM PUNYA APA PUN untuk
                                     ditampilkan: belum dijadwalkan, tak menuntut
                                     berkas, bukan ujian. Tanpa baris ini bloknya
                                     berdiri kosong dan terbaca seperti kartu yang
                                     gagal dimuat. -->
                                <div v-else-if="!a.jadwal && !a.mcu && !unggahSiap(a)" class="ld-akt__sunyi">
                                    {{ a.pesan || 'Belum ada yang perlu kamu kerjakan di aktivitas ini. Jadwal atau instruksinya muncul di sini begitu tersedia.' }}
                                </div>
                            </template>

                            <!-- HASIL MCU — DI LUAR ketiga cabang di atas.
                                 Menyangkut kesehatan kandidat sendiri, jadi ia berhak
                                 tahu — tapi hanya kesimpulannya, bukan rincian medis.
                                 Berdiri di luar karena hasil MCU justru terbit ketika
                                 aktivitasnya sudah SELESAI. Di dalam cabang "masih
                                 berjalan", hasil yang sudah keluar akan ikut hilang
                                 begitu tim menutup aktivitasnya. -->
                            <div v-if="a.mcu" class="ld-mcu" :class="'is-' + a.mcu.status.toLowerCase()">
                                <span class="ld-mcu__ico"><i class="bi bi-heart-pulse-fill"></i></span>
                                <div style="min-width: 0; flex: 1">
                                    <div class="ld-eyebrow">HASIL PEMERIKSAAN KESEHATAN</div>
                                    <div class="ld-mcu__judul">{{ a.mcu.label }}</div>
                                    <div class="ld-mcu__meta">
                                        <span v-if="a.mcu.penyedia">{{ a.mcu.penyedia }}</span>
                                        <span v-if="a.mcu.tanggal">{{ fmtWaktu(a.mcu.tanggal) }}</span>
                                    </div>
                                    <p v-if="a.mcu.catatan" class="ld-mcu__cat">{{ a.mcu.catatan }}</p>
                                    <!-- PENGGANTIAN BIAYA — kesimpulan otomatis dari hasil
                                         ini (lolos = diganti). Kandidat membacanya di
                                         tempat yang sama dengan hasilnya, tanpa perlu
                                         menanyakan ke siapa pun. -->
                                    <p v-if="a.biaya?.hasilKalimat" class="ld-mcu__biaya" :class="'is-' + String(a.biaya.kode).toLowerCase()">
                                        <i class="bi bi-cash-coin"></i> {{ a.biaya.hasilKalimat }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ═══ PENAWARAN — KETERANGAN, BUKAN TINDAKAN ═══
                         Tombol "Terima Penawaran" dan "Mengundurkan Diri" DICABUT
                         beserta endpoint-nya. Keputusan itu kini hanya dicatat tim
                         di worklist, lewat "Keputusan dari Kandidat" — satu pintu,
                         dengan jejak siapa mencatat dan kapan.

                         Kartunya sendiri TETAP ADA. Menghapusnya sekalian membuat
                         kandidat di tahap penawaran melihat halaman yang kosong. -->
                    <div v-if="tahapAktif.penawaran" class="ld-offer">
                        <div class="ld-offer__head">
                            <span class="ld-offer__ico"><i class="bi bi-envelope-paper-fill"></i></span>
                            <div style="min-width: 0; flex: 1">
                                <div class="ld-eyebrow">PENAWARAN UNTUKMU</div>
                                <div class="ld-offer__judul">{{ tahapAktif.label }}</div>
                            </div>
                        </div>

                        <div class="ld-offer__wait">
                            <i class="bi bi-hourglass-split"></i>
                            <div style="min-width: 0">
                                <b>Tim rekrutmen akan menghubungimu.</b>
                                <p>
                                    Jadwal pertemuan dan surat penawarannya dikirim lewat email dan muncul di
                                    halaman ini. Sampaikan keputusanmu langsung kepada tim rekrutmen yang
                                    menghubungimu — merekalah yang mencatatnya.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- TAHAP TANPA SATU PUN KARTU — dua sebab, satu akibat.
                         (1) Tak ada aktivitas yang terlihat kandidat sama
                             sekali: background check, negosiasi penawaran,
                             verifikasi referensi.
                         (2) Semuanya belum gilirannya — penghalangnya aktivitas
                             INTERNAL yang sengaja disembunyikan, jadi yang
                             terlihat kandidat semuanya terkunci.

                         Diperiksa lewat `aktivitasKartu`, bukan
                         `aktivitasTampil`: yang kedua ikut menghitung kartu
                         yang tidak dirender, sehingga kasus (2) lolos dari
                         penjagaan ini dan halaman berdiri kosong tanpa satu
                         kalimat pun. Pada tahap penawaran tidak diulang: kartu
                         di atas sudah menjelaskan. -->
                    <div v-if="!aktivitasKartu.length && !tugas && !tahapAktif.penawaran" class="ld-notice ld-notice--wait">
                        <span class="ld-nico ld-nico--wait" aria-hidden="true">
                            <svg viewBox="0 0 44 44" width="34" height="34">
                                <circle class="ld-nico__halo" cx="22" cy="22" r="17" />
                                <g class="ld-nico__glass">
                                    <path class="ld-nico__stroke" d="M14.5 12.5h15l-7.5 9.5zM14.5 31.5h15l-7.5-9.5z" />
                                    <line class="ld-nico__stroke" x1="13.5" y1="12.5" x2="30.5" y2="12.5" />
                                    <line class="ld-nico__stroke" x1="13.5" y1="31.5" x2="30.5" y2="31.5" />
                                </g>
                                <circle class="ld-nico__sand" cx="22" cy="22" r="1.4" />
                            </svg>
                        </span>
                        <div>
                            <b>{{ pesanTahap.judul }}</b>
                            <p>{{ pesanTahap.teks }}</p>
                        </div>
                    </div>
                </div>

                <!-- ═══ TAB ISI BAWAH ═══
                         Alur, detail lowongan, formulir, dan catatan hasil dulu
                         ditumpuk vertikal sehingga halaman ini sangat panjang dan
                         kandidat harus menggulung jauh untuk menemukan berkasnya.
                         Dipisah jadi tab: yang dicari langsung terjangkau. -->
                <div class="ld-tabs" role="tablist">
                    <button
                        v-for="t in tabs"
                        :key="t.k"
                        type="button"
                        role="tab"
                        class="ld-tabs__b"
                        :class="{ 'is-on': tab === t.k }"
                        :aria-selected="tab === t.k"
                        @click="tab = t.k"
                    >
                        <i class="bi" :class="t.ikon"></i>
                        {{ t.label }}
                        <span v-if="t.jml" class="ld-tabs__n">{{ t.jml }}</span>
                    </button>
                </div>

                <!-- ── ALUR SELEKSI · WATERFALL (gantt bertingkat) ── -->
                <div v-if="isMtCategory" v-show="tab === 'alur'" class="ld-card ld-wf">
                    <div class="ld-wf__head">
                        <div class="ld-sectitle">
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#6366f1"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                            </svg>
                            Alur Seleksi <span style="color: #8b5cf6; font-weight: 700">· Waterfall</span>
                        </div>
                        <div class="ld-wf__legend">
                            <span><i style="background: #8b5cf6"></i> Selesai</span>
                            <span><i style="background: #f59e0b"></i> Berlangsung</span>
                            <span><i style="background: #cbd5e1"></i> Menunggu</span>
                        </div>
                    </div>
                    <!-- SUMBU TANGGAL DIHAPUS.
                         Sebagian besar tanggal di sini bukan tanggal: tahap yang
                         belum terjadwal diberi perkiraan tiga hari per tahap oleh
                         kode ini sendiri, lalu ditumpuk berurutan. Ditandai "±",
                         tapi tanda itu tidak menahan apa pun — yang dibaca
                         kandidat tetap tanggal, dan tanggal di layar perusahaan
                         terbaca sebagai janji. Waterfall-nya tetap: yang menyusun
                         batangnya sekarang URUTAN tahap, bukan kalender. -->
                    <div class="ld-wf__body">
                        <div v-for="(w, i) in gantt.rows" :key="i" class="ld-wf__row">
                            <div class="ld-wf__side">
                                <span class="ld-wf__node" :class="'is-' + w.state">
                                    <svg
                                        v-if="w.state === 'done'"
                                        width="14"
                                        height="14"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                    <svg
                                        v-else-if="w.state === 'fail'"
                                        width="13"
                                        height="13"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.6"
                                        stroke-linecap="round"
                                    >
                                        <path d="M18 6L6 18M6 6l12 12" />
                                    </svg>
                                    <template v-else>{{ w.urutan }}</template>
                                </span>
                                <div style="min-width: 0">
                                    <div
                                        class="ld-wf__name"
                                        :style="{ color: w.state === 'todo' ? '#94a3b8' : '#1e293b' }"
                                    >
                                        {{ w.name }}
                                    </div>
                                    <div class="ld-wf__st" :style="{ color: w.stColor }">{{ w.statusLabel }}</div>
                                </div>
                            </div>
                            <div class="ld-wf__track">
                                <span
                                    v-for="gi in gantt.kolom"
                                    :key="gi"
                                    class="ld-wf__grid"
                                    :style="{ left: ((gi - 1) / gantt.kolom) * 100 + '%' }"
                                ></span>
                                <span
                                    class="ld-wf__bar"
                                    :class="{ 'is-glow': w.glow, 'is-empty': w.state === 'todo' }"
                                    :style="{ marginLeft: w.left + '%', width: w.width + '%', background: w.bg }"
                                ></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Data kandidat (dari formulir pendaftaran) ──
                         Diambil dari jawaban yang SUDAH kandidat kirim, bukan
                         disalin ulang: foto, kampus, tanggal lahir, dan sisanya
                         mengikuti urutan pertanyaan di skema formulirnya. -->
                <div v-if="profilKandidat" v-show="tab === 'berkas'" class="ld-card ld-profil">
                    <div class="ld-profil__head">
                        <div class="ld-profil__foto">
                            <img
                                v-if="profilKandidat.foto"
                                :src="profilKandidat.foto.url"
                                :alt="profilKandidat.nama"
                                @click="previewBerkas(profilKandidat.foto)"
                            />
                            <span v-else>{{ inisialNama }}</span>
                        </div>
                        <div style="min-width: 0">
                            <div class="ld-eyebrow">DATA KANDIDAT</div>
                            <div class="ld-profil__nama">{{ profilKandidat.nama || '—' }}</div>
                            <div class="ld-profil__sub">Dikirim {{ fmtWaktu(profilKandidat.waktuKirim) }}</div>
                        </div>
                    </div>
                    <div v-if="profilKandidat.data.length" class="ld-profil__grid">
                        <div v-for="d in profilKandidat.data" :key="d.key" class="ld-profil__item">
                            <span class="ld-profil__lbl">{{ d.label }}</span>
                            <span class="ld-profil__val">{{ d.nilai }}</span>
                        </div>
                    </div>
                    <!-- Selebihnya ada di bawah, utuh. Dikatakan terang-terangan:
                         kartu yang berhenti di delapan baris tanpa penjelasan
                         terbaca seperti data yang hilang. -->
                    <p class="ld-profil__kaki">
                        <i class="bi bi-info-circle"></i>
                        Seluruh jawaban Anda — termasuk riwayat, keluarga, dan berkas —
                        ada lengkap di <b>Formulir &amp; Berkas</b> di bawah.
                    </p>
                </div>

                <!-- ── Formulir & Berkas Saya ── -->
                <div v-if="formulir.length" v-show="tab === 'berkas'">
                    <div class="ld-secrow">
                        <div class="ld-sectitle">
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#6366f1"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z" />
                            </svg>
                            Formulir &amp; Berkas Saya
                        </div>
                        <span class="ld-seccount">{{ formulir.length }} formulir</span>
                    </div>
                    <div class="ld-forms">
                        <div
                            v-for="(f, fi) in formulir"
                            :key="f.no"
                            class="ld-form"
                            :class="{ 'is-open': openForm === fi }"
                        >
                            <button type="button" class="ld-form__head" @click="openForm = openForm === fi ? -1 : fi">
                                <span class="ld-form__step" :class="{ 'is-ok': !!f.waktuKirim }">{{
                                    String(fi + 1).padStart(2, '0')
                                }}</span>
                                <span style="flex: 1; min-width: 0">
                                    <span class="ld-form__title">{{ f.label }}</span>
                                    <span class="ld-form__sub"
                                        >{{
                                            f.sumber === 'PENDAFTARAN' ? 'Pendaftaran' : 'Tahap ' + (f.urutan || '—')
                                        }}
                                        · {{ f.jawaban.length }} isian<template v-if="f.berkas.length">
                                            · {{ f.berkas.length }} berkas</template
                                        ></span
                                    >
                                </span>
                                <span class="ld-form__pill" :class="statusFormulir(f).kelas">{{
                                    statusFormulir(f).teks
                                }}</span>
                                <svg
                                    class="ld-form__chev"
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="#94a3b8"
                                    stroke-width="2.4"
                                    stroke-linecap="round"
                                >
                                    <path d="M6 9l6 6 6-6" />
                                </svg>
                            </button>
                            <div v-if="openForm === fi" class="ld-form__body">
                                <!-- Dikelompokkan per LANGKAH, urut seperti
                                     formulirnya sendiri. Deretan kotak tanpa jeda
                                     memaksa kandidat mencari sendiri di mana satu
                                     bagian berakhir dan bagian lain dimulai. -->
                                <template v-for="(grup, gi) in kelompokIsian(f)" :key="gi">
                                <div class="ld-ghead">
                                    <span class="ld-ghead__ico"><i class="bi" :class="grup.ikon"></i></span>
                                    <span class="ld-ghead__lbl">{{ grup.judul }}</span>
                                    <span class="ld-ghead__garis"></span>
                                </div>
                                <div class="ld-fields">
                                    <div
                                        v-for="j in grup.items"
                                        :key="j.key"
                                        class="ld-field"
                                        :class="{ 'is-panjang': isianPanjang(f, j) }"
                                    >
                                        <div class="ld-field__k">{{ labelIsian(f, j) }}</div>
                                        <!-- ISIAN BERUPA BERKAS = LENCANA, bukan nama file +
                                                 tombol Lihat. Berkasnya sendiri — berikut ukuran,
                                                 status verifikasi, dan pratinjau — sudah ada di
                                                 "Dokumen & Verifikasi" tepat di bawah. Di daftar
                                                 isian yang perlu dijawab cuma satu: ADA atau TIDAK. -->
                                        <div v-if="isianBerkas(f, j)" class="ld-field__v">
                                            <!-- GAMBAR TAMPIL SEBAGAI GAMBAR.
                                                 Foto verifikasi wajah sebelumnya terbaca
                                                 "foto_verifikasi-verifikasi.jpg" — nama berkas
                                                 di layar milik orang yang wajahnya ada di
                                                 dalamnya. Yang ingin dipastikan kandidat cuma
                                                 satu: fotonya benar terkirim dan benar dirinya.
                                                 Itu hanya bisa dijawab oleh fotonya sendiri. -->
                                            <button
                                                v-if="j.berkas && j.berkas.isImage"
                                                type="button"
                                                class="ld-field__thumb"
                                                :title="j.berkas.nama"
                                                @click="bukaDok(j.berkas)"
                                            >
                                                <img :src="j.berkas.url" :alt="labelIsian(f, j)" loading="lazy" />
                                                <span><i class="bi bi-zoom-in"></i> Lihat</span>
                                            </button>
                                            <span v-else class="ld-badge" :class="j.berkas ? 'is-ada' : 'is-kosong'">
                                                <i
                                                    class="bi"
                                                    :class="j.berkas ? 'bi-check-circle-fill' : 'bi-dash-circle'"
                                                ></i>
                                                {{ j.berkas ? 'Terlampir' : 'Belum ada' }}
                                            </span>
                                        </div>
                                        <!-- ISIAN BERULANG (riwayat kerja, organisasi,
                                             sertifikasi) — daftar, bukan satu paragraf.
                                             Digabung jadi satu baris teks, isinya jadi
                                             dinding kata yang tak bisa dibaca siapa pun. -->
                                        <div v-else-if="j.baris && j.baris.length" class="ld-field__v ld-rows">
                                            <div v-for="(row, ri) in j.baris" :key="ri" class="ld-row">
                                                <span v-if="j.baris.length > 1" class="ld-row__no">{{ ri + 1 }}</span>
                                                <div class="ld-row__isi">
                                                    <span v-for="(p, pi) in row" :key="pi" class="ld-row__p">
                                                        <b v-if="p.label">{{ p.label }}</b>
                                                        <!-- SUB-ISIAN BERUPA BERKAS = TOMBOL, bukan nama
                                                             berkas sebagai teks mati.

                                                             Sertifikat diunggah PER BARIS, jadi ia tidak
                                                             pernah muncul di daftar "Dokumen & Verifikasi"
                                                             di bawah — daftar itu hanya memuat berkas
                                                             tingkat atas (KTP, CV, ijazah). Tanpa tombol di
                                                             sini, satu-satunya berkas yang tidak bisa dibuka
                                                             kandidat justru yang ia unggah sendiri paling
                                                             banyak. Layar admin sudah punya tombol ini sejak
                                                             awal (Pelamar.vue); yang tertinggal cuma sisi
                                                             kandidatnya.

                                                             `p.berkas` kosong berarti berkasnya memang tidak
                                                             ada di server — namanya tetap ditampilkan apa
                                                             adanya supaya kandidat tahu baris itu pernah
                                                             diisi, bukan menghilang tanpa penjelasan. -->
                                                        <button
                                                            v-if="p.berkas"
                                                            type="button"
                                                            class="ld-row__file"
                                                            :title="p.berkas.nama"
                                                            @click="bukaDok(p.berkas)"
                                                        >
                                                            <svg
                                                                width="13"
                                                                height="13"
                                                                viewBox="0 0 24 24"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                stroke-width="2"
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                            >
                                                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                                                                <circle cx="12" cy="12" r="3" />
                                                            </svg>
                                                            Lihat berkas
                                                            <span class="ld-row__ext">{{ (p.berkas.ext || '').toUpperCase() }}</span>
                                                        </button>
                                                        <template v-else>{{ p.nilai }}</template>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- ══ DAFTAR BUTIR ══
                                             "Sebutkan minimal 5 hal" disimpan sebagai lima
                                             jawaban terpisah. Dirangkai koma, kelimanya jadi
                                             satu kalimat panjang tanpa batas yang terlihat —
                                             dan aturan "minimal 5" yang dijaga formulir
                                             mustahil diperiksa ulang dengan mata. Bernomor,
                                             jumlahnya terbaca sekali lihat. -->
                                        <ol v-else-if="isianDaftar(j)" class="ld-field__v ld-butir">
                                            <li v-for="(butir, bi) in isianDaftar(j)" :key="bi">{{ butir }}</li>
                                        </ol>
                                        <div v-else class="ld-field__v">{{ nilaiTampil(f, j) }}</div>
                                    </div>
                                </div>
                                </template>
                                <div v-if="f.berkas.length" class="ld-docs">
                                    <div class="ld-docs__label">DOKUMEN &amp; VERIFIKASI</div>
                                    <!-- Kunci dari URL berkas (unik per baris
                                         tabel), bukan field: tiga sertifikat
                                         memakai field yang sama persis, dan tiga
                                         elemen berbagi satu kunci membuat Vue
                                         salah memasangkan DOM-nya. -->
                                    <div v-for="b in f.berkas" :key="b.url" class="ld-doc">
                                        <span class="ld-doc__ico" :class="b.isImage ? 'is-img' : 'is-pdf'">
                                            <svg
                                                v-if="b.isImage"
                                                width="20"
                                                height="20"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="#6366f1"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <circle cx="8.5" cy="8.5" r="1.5" />
                                                <path d="M21 15l-5-5L5 21" />
                                            </svg>
                                            <svg
                                                v-else
                                                width="20"
                                                height="20"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="#ef4444"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                <path d="M14 2v6h6" />
                                            </svg>
                                        </span>
                                        <div style="flex: 1; min-width: 0">
                                            <div class="ld-doc__toprow">
                                                <span class="ld-doc__name">{{ b.nama }}</span>
                                                <!-- Tanpa nomor, tiga sertifikat
                                                     tampil sebagai tiga baris yang
                                                     hanya bisa dibedakan dari nama
                                                     berkasnya. -->
                                                <span v-if="b.nomor" class="ld-doc__no">#{{ b.nomor }}</span>
                                                <span class="ld-doc__ext">{{ (b.ext || '').toUpperCase() }}</span>
                                            </div>
                                            <div class="ld-doc__desc">
                                                {{
                                                    b.field === 'foto_verifikasi'
                                                        ? 'Foto verifikasi identitas yang kamu unggah.'
                                                        : 'Berkas ' +
                                                          b.field.replace(/[_-]/g, ' ') +
                                                          ' · ' +
                                                          ukuran(b.ukuran)
                                                }}
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            class="ld-doc__eye"
                                            title="Lihat berkas"
                                            @click="bukaDok(b)"
                                        >
                                            <svg
                                                width="18"
                                                height="18"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="height: 8px"></div>
        </div>

        <!-- LIGHTBOX -->
        <div class="ld-lb" :class="{ 'is-on': !!lightbox }" @click="lightbox = null">
            <div v-if="lightbox" :class="['ld-lb__wrap', { 'is-pdf': lightbox.pdf }]" @click.stop>
                <div class="ld-lb__bar">
                    <div style="display: flex; align-items: center; gap: 11px; color: #fff; min-width: 0">
                        <span class="ld-lb__ico"
                            ><svg
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#fff"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <path d="M21 15l-5-5L5 21" /></svg
                        ></span>
                        <div style="min-width: 0">
                            <div class="ld-lb__name">{{ lightbox.nama }}</div>
                            <div class="ld-lb__desc">
                                {{
                                    lightbox.field === 'foto_verifikasi'
                                        ? 'Foto verifikasi identitas'
                                        : 'Pratinjau berkas'
                                }}
                            </div>
                        </div>
                    </div>
                    <button type="button" class="ld-lb__close" @click="lightbox = null">
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.4"
                            stroke-linecap="round"
                        >
                            <path d="M18 6L6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="ld-lb__card">
                    <div v-if="lbLoading" class="ld-lb__state"><span class="ld-spin"></span> Memuat berkas…</div>
                    <div v-else-if="lbError" class="ld-lb__state">
                        <svg
                            width="28"
                            height="28"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#f87171"
                            stroke-width="2"
                            stroke-linecap="round"
                        >
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 8v5M12 16h.01" />
                        </svg>
                        Gagal memuat berkas.
                    </div>
                    <!-- PDF disematkan lewat iframe; gambar tetap pakai <img>.
                         Satu modal melayani keduanya supaya tidak ada dua gaya. -->
                    <iframe
                        v-if="lightbox.pdf"
                        v-show="!lbLoading && !lbError"
                        :src="lightbox.url"
                        :title="lightbox.nama"
                        class="ld-lb__pdf"
                        @load="selesaiMuat()"
                        @error="selesaiMuat(true)"
                    ></iframe>
                    <img
                        v-else
                        v-show="!lbLoading && !lbError"
                        :src="lightbox.url"
                        :alt="lightbox.nama"
                        @load="selesaiMuat()"
                        @error="selesaiMuat(true)"
                    />
                    <div class="ld-lb__foot">
                        <span style="font-size: 12px; color: #8b93a7">Berkas milik akunmu · pratinjau aman</span>
                        <span v-if="lightbox.status === 'TERVERIFIKASI'" class="ld-lb__ok"
                            ><svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.4"
                            >
                                <path d="M20 6L9 17l-5-5" />
                            </svg>
                            Terverifikasi</span
                        >
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialog "Terima / Mundur" DICABUT bersama tombolnya. -->


        <transition name="ld-toast"
            ><div v-if="toast" class="ld-toast" :class="{ 'is-err': toastErr }">
                <i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}
            </div></transition
        >
    </div>
</template>

<script>
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    FORMULIR,
    komponenFormulir,
    skemaFormulir,
    jawabanAwal,
    grupBagian,
    grupField,
    labelField,
    tipeField,
} from '@career/formulir';
import DynamicForm from '@career/formulir/DynamicForm.vue';
import { kunciBerkas } from '@utils/formulir/berkasBaris';
import { berkasKurang } from '@utils/formulir/aturan';
import { normalisasiSkema } from '@utils/formulir/schema';
import { tautkan } from '@utils/career/tautanOtomatis';
import JadwalKartu from '@career/JadwalKartu.vue';
import JadwalTunda from '@career/JadwalTunda.vue';
import KontenAman from '@career/KontenAman.vue';
import UnggahAktivitas from '@career/UnggahAktivitas.vue';

/**
 * Peta key -> label pertanyaan, dikumpulkan dari SKEMA seluruh formulir.
 *
 * Label diambil dari sumber aslinya (skema formulir), bukan ditulis ulang di
 * sini — jadi mengubah pertanyaan cukup di satu tempat. Key yang tidak
 * ditemukan (mis. jawaban dari versi formulir lama) memakai label turunan
 * dari server sebagai cadangan.
 */
const LABEL_FIELD = (() => {
    const peta = {};
    Object.values(FORMULIR).forEach((f) =>
        (f.skema?.langkah || []).forEach((L) =>
            (L.bagian || []).forEach((B) =>
                (B.field || []).forEach((x) => {
                    if (x.key && x.label) peta[x.key] = x.label;
                }),
            ),
        ),
    );
    return peta;
})();

// Peta skema per formulir, dihitung sekali. WeakMap: kuncinya objek formulir
// itu sendiri, jadi tidak perlu dibersihkan saat lamaran lain dibuka.
const PETA_SKEMA = new WeakMap();

/**
 * Singkatan yang memang huruf kapital.
 *
 * Tanpa daftar ini tebakan-dari-kunci menghasilkan "Nik" dan "Ktp" — kata yang
 * tidak pernah ditulis siapa pun, dan langsung terbaca sebagai kesalahan
 * sistem oleh orang yang melihatnya di layar sendiri.
 */
const AKRONIM = new Set(['nik', 'ktp', 'kk', 'npwp', 'cv', 'wa', 'hp', 'ipk', 'sim', 'no', 'sk', 'pt', 'bpjs', 'nisn', 'npsn']);

/**
 * RINGKASAN IDENTITAS — kunci yang boleh naik ke kepala halaman.
 *
 * Sengaja SEDIKIT dan sengaja berupa pola, bukan daftar kunci mati: tiap
 * formulir memberi nama kuncinya sendiri (`tgl_lahir`, `tanggal_lahir`,
 * `v_tgl_lahir`), dan daftar mati akan diam-diam kosong begitu formulirnya
 * berganti versi.
 *
 * Yang di luar ini TIDAK hilang — seluruhnya tetap terbaca lengkap di
 * "Formulir & Berkas" di bawah. Yang dihapus cuma penggandaannya.
 */
const RINGKAS_IDENTITAS = [
    { slot: 'posisi', pola: /^(posisi|jabatan)(_dilamar)?$/i, label: 'Posisi Dilamar' },
    { slot: 'nik', pola: /^(v_)?nik$|^no_?ktp$/i, label: 'NIK' },
    { slot: 'lahir', pola: /^tempat_lahir$|(tempat).*(lahir)/i, label: 'Tempat Lahir' },
    { slot: 'tgl', pola: /(tgl|tanggal)_?lahir/i, label: 'Tanggal Lahir' },
    { slot: 'jk', pola: /^(v_)?(jk|jenis_kelamin|gender)$/i, label: 'Jenis Kelamin' },
    { slot: 'hp', pola: /^(v_)?(hp|no_?hp|telp|telepon|wa|no_?wa)$/i, label: 'Nomor HP / WA' },
    // DOMISILI DULU, KTP BELAKANGAN — dan keduanya berbagi satu slot.
    //
    // Formulir kerap memuat dua alamat. Yang menjawab "di mana orang ini
    // sekarang" adalah domisilinya; alamat KTP menjawab pertanyaan lain.
    // Tanpa urutan ini pencarian berhenti di kunci yang kebetulan tersimpan
    // lebih dulu — biasanya alamat_ktp — dan ringkasan mengatakan hal yang
    // tidak ditanyakan siapa pun.
    { slot: 'alamat', pola: /(domisili|alamat_sekarang|alamat_tinggal|^alamat$)/i, label: 'Alamat Domisili' },
    { slot: 'alamat', pola: /alamat/i, label: 'Alamat' },
    { slot: 'pendidikan', pola: /^pendidikan(_terakhir)?$/i, label: 'Pendidikan' },
    { slot: 'jurusan', pola: /^(jurusan|prodi|program_studi)$/i, label: 'Jurusan' },
];

/**
 * Nama kandidat di kepala kartu — dicari lewat POLA, bukan kunci `nama` mati.
 *
 * Formulir yang dipakai sekarang menamainya `nama_lengkap`. Selama pencarian
 * berhenti di kunci `nama` persis, kepala kartu menampilkan "—" pada halaman
 * milik kandidat itu sendiri — data yang jelas-jelas ada, tepat di bawahnya.
 */
const POLA_NAMA = /^(v_)?nama(_lengkap|_kandidat|_pelamar)?$/i;

// Sudah tampil di kepala kartu profil — tidak perlu diulang di daftar.
const POLA_SEMBUNYI = /^(v_)?(nama(_lengkap|_kandidat|_pelamar)?|email)$/i;

const CFG = { headers: { Accept: 'application/json' } };
const ST = {
    berjalan: { c: '#b45309', bg: 'rgba(245,158,11,.14)', dot: '#f59e0b' },
    lolos: { c: '#059669', bg: 'rgba(16,185,129,.12)', dot: '#10b981' },
    gugur: { c: '#dc2626', bg: 'rgba(239,68,68,.1)', dot: '#ef4444' },
    // Disimpan untuk kesempatan berikutnya — belum berakhir, jadi bukan merah.
    menunggu: { c: '#4f46e5', bg: 'rgba(99,102,241,.12)', dot: '#6366f1' },
    // KEPUTUSAN DARI KANDIDAT (mengundurkan diri, menolak penawaran).
    // Sengaja abu-abu: prosesnya memang berhenti, tetapi bukan karena ia
    // ditolak. Mewarnainya merah seperti "Tidak Lolos" mengatakan hal yang
    // salah kepada orang yang justru memilih pergi sendiri.
    netral: { c: '#475569', bg: 'rgba(100,116,139,.12)', dot: '#64748b' },
};
// Ikon fakta cepat (stroke) — dipakai kartu Detail Lowongan.
const SVG = (path) =>
    `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
const P = {
    koper: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    build: '<path d="M3 21h18M6 21V7l6-4 6 4v14"/><path d="M9 9h.01M15 9h.01M9 13h.01M15 13h.01"/>',
    pin: '<path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    jam: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    level: '<path d="M4 20h4V10H4zM10 20h4V4h-4zM16 20h4v-8h-4z"/>',
    topi: '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
    doc: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
};

// Kalimat "sedang menunggu apa" TIDAK ditulis di sini. Setiap tipe aktivitas
// membawa Pesan_Kandidat-nya sendiri dari Master Tipe Tahap, jadi menambah tipe
// baru (mis. "Tes Praktik Lapangan") cukup lewat master tanpa menyentuh file
// ini. Satu kalimat cadangan di bawah hanya dipakai bila master belum diisi.
const PESAN_UMUM =
    'Tidak ada yang perlu kamu kerjakan sekarang — tim rekrutmen akan mengabarimu lewat email dan halaman ini.';

/**
 * KALIMAT TETAP untuk tahap yang jadwalnya ditangani tim sendiri (negosiasi
 * penawaran). Sengaja SATU kalimat untuk SEMUA keadaan tahap itu: belum
 * dijadwalkan, sudah dijadwalkan, sedang berlangsung, atau baru selesai
 * dirundingkan — kandidat membaca hal yang sama persis.
 *
 * KENAPA TIDAK MENGIKUTI KEADAAN
 * Kalimat yang berubah adalah kalimat yang bisa dibaca mundur. Kandidat yang
 * hari ini melihat "menunggu jadwal" lalu besok melihat kalimat lain tahu ada
 * sesuatu yang bergerak — dan sejak itu ia menghitung hari. Justru itu yang
 * ingin dihindari: perundingan angka bisa memakan waktu, dan diamnya bukan
 * kabar buruk. Kalimat yang tidak bergerak tidak bisa ditafsirkan.
 *
 * KENAPA DI KODE, BUKAN Pesan_Kandidat DI MASTER
 * Master boleh disunting siapa saja yang memegang halaman Master Tipe Tahap,
 * dan satu suntingan yang bermaksud baik ("Selamat, penawaran sedang
 * disiapkan!") mengembalikan persis masalah yang kalimat ini ada untuk
 * mencegahnya. Ditaruh di sini, mengubahnya menuntut tinjauan kode.
 */
const PESAN_TAHAP_INTERNAL =
    'Tim rekrutmen kami akan menghubungi Anda untuk menyampaikan informasi lebih lanjut terkait proses offering. ' +
    'Mohon pastikan nomor telepon dan email Anda tetap aktif.';

export default {
    components: { Head, Link, JadwalKartu, JadwalTunda, DynamicForm, KontenAman, UnggahAktivitas },
    props: {
        lamaran: { type: Object, default: () => ({}) },
        tahap: { type: Array, default: () => [] },
        tugas: { type: Object, default: null },
        formulir: { type: Array, default: () => [] },
        lowonganUrl: { type: String, default: null },
        kartu: { type: Object, default: null },
        profil: { type: Object, default: () => ({}) },
        konteks: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            ST,
            // Stepper baru dilipat jadi dropdown di layar sempit; di tablet ke
            // atas ruangnya cukup, jadi dibiarkan terbuka seperti semula.
            kompak: false,
            mqKompak: null,
            jawaban: {},
            berkas: {},
            // Langkah wizard yang dipulihkan dari draf server. Dipakai sebagai
            // nilai AWAL komponen formulir, bukan diikat dua arah — mengikatnya
            // membuat wizard melompat setiap draf tersimpan.
            langkahAwal: 0,
            drafSiap: false,
            berkasDraf: {},
            // Unggahan draf yang MASIH BERJALAN, per kunci berkas. Kirim menunggu
            // semuanya selesai — dulu formulir terkirim selagi berkasnya masih
            // di jalan, dan berkas itu tidak pernah ikut tercatat.
            unggahan: {},
            // Nomor unggahan TERBARU per isian. Unggahan lama yang selesai
            // belakangan tidak boleh menimpa hasil milik yang lebih baru.
            nomorUnggah: {},
            // Isian yang unggahannya gagal → pesan yang MENETAP di bawah kotak
            // unggahnya. Notifikasi saja hilang dalam 4 detik, sementara nama
            // berkasnya dulu tetap tercatat seolah beres.
            berkasGagal: {},
            // bagian/baris/field tiap berkas yang dipilih di tab ini — untuk
            // mencoba ulang unggahannya sebelum formulir dikirim.
            berkasMeta: {},
            mengirim: false,
            openForm: 0,
            lightbox: null,
            lbTimer: null,
            berkasTes: {},
            unggahDi: null,
            // Aktivitas yang tombol kirimnya sedang diproses.
            kirimDi: null,
            tab: 'alur',
            // Layar antara menuju ruang ujian (tab yang sama, jeda 3 detik).
            menujuUjian: false,
            // AKTIVITAS MANA yang sedang dituju. Tanpa ini hitungan mundur
            // "menyiapkan ruang ujian" muncul serentak di semua blok ujian
            // pada tahap yang memuat lebih dari satu tes online.
            menujuId: null,
            hitungPindah: 3,
            timerPindah: null,
            // ── PULANG DARI RUANG UJIAN ──
            // { id, at } aktivitas yang barusan dikerjakan, dibaca kembali dari
            // sessionStorage saat kandidat diantar pulang oleh CAT.
            pulangTes: null,
            pulangCek: 0,
            pulangTimer: null,
            lbLoading: false,
            lbError: false,
            toast: '',
            toastErr: false,
            tm: null,
            now: Date.now(),
            jam: null,
            // [feat/feedback]
            showFeedbackBanner: true,
        };
    },
    computed: {
        /**
         * Catatan UNTUK KANDIDAT yang sudah boleh dibaca — terbaru dulu.
         *
         * Server hanya mengirim `catatanEksternal` setelah hasil tahapnya boleh
         * diumumkan (lihat LamaranController::terbitKeKandidat), jadi tidak ada
         * aturan kedua yang perlu dijaga di sini.
         */
        catatanKandidat() {
            const waktu = (x) => {
                const t = Date.parse(String(x || '').replace(' ', 'T'));

                return Number.isNaN(t) ? 0 : t;
            };

            return (this.tahap || [])
                .filter((t) => t.catatanEksternal)
                .map((t) => ({
                    urutan: t.urutan,
                    label: t.label,
                    hasil: t.hasil,
                    html: t.catatanEksternal,
                    at: t.catatanAt ? String(t.catatanAt).replace(' ', 'T') : null,
                    berikut: (this.tahap.find((x) => x.urutan === t.urutan + 1) || {}).label || null,
                }))
                .sort((a, b) => waktu(b.at) - waktu(a.at) || b.urutan - a.urutan);
        },
        /**
         * BATAS PENGISIAN formulir tahap aktif (server: BatasIsi::status).
         *
         * Jam halaman berdetak tiap 15 detik, jadi formulir ikut terkunci SAAT
         * batasnya lewat — tanpa menunggu halaman dimuat ulang. Server tetap
         * menolak kiriman yang lolos dari layar.
         */
        batasMs() {
            const b = this.tugas?.batas;

            return b?.batas ? new Date(String(b.batas).replace(' ', 'T')).getTime() : null;
        },
        bukaMs() {
            const b = this.tugas?.batas;

            return b?.buka ? new Date(String(b.buka).replace(' ', 'T')).getTime() : null;
        },
        /** Waktu dibuka belum tiba — dihitung hidup, jadi formulir terbuka SAAT waktunya. */
        belumBukaLive() {
            return this.bukaMs !== null && this.now < this.bukaMs;
        },
        batasLewat() {
            return this.batasMs !== null && this.now > this.batasMs;
        },
        formTerkunci() {
            const b = this.tugas?.batas;
            if (!b || b.terkirim) return false;
            if (b.belumDiatur || this.belumBukaLive) return true;

            // Lewat batas SELALU terkunci — tidak ada "ditandai terlambat".
            return this.batasLewat;
        },
        sisaBatasTeks() {
            return this.batasMs === null ? '' : this.durasiTeks(this.batasMs - this.now);
        },
        kelasBatasPortal() {
            const b = this.tugas?.batas;
            if (b?.belumDiatur || this.belumBukaLive) return 'is-tutup';
            if (this.formTerkunci) return 'is-kunci';

            return this.batasMs !== null && this.batasMs - this.now <= 86400000 ? 'is-dekat' : 'is-aman';
        },
        /**
         * Catatan untuk TAHAP AKTIF = catatan keputusan tahap tepat sebelumnya.
         *
         * "Kamu lolos, ini catatan kami": yang ditulis saat Seleksi
         * Administrasi diloloskan adalah pesan untuk tahap Psikotes. Hanya
         * tahap TERAKHIR yang diputus sebelum tahap aktif yang dihitung —
         * catatan dua tahap lalu bukan pesan untuk tahap ini, walaupun tahap
         * sesudahnya diputus tanpa catatan.
         */
        catatanMasuk() {
            if (!this.tahapAktif) return null;
            const sebelum = this.tahap.filter((t) => t.urutan < this.tahapAktif.urutan && t.hasil);
            const t = sebelum.length ? sebelum[sebelum.length - 1] : null;

            return t ? this.catatanKandidat.find((c) => c.urutan === t.urutan) || null : null;
        },
        /**
         * Keputusan yang TIDAK berlanjut ke tahap aktif (tidak lolos, tahap
         * terakhir): tidak ada kartu tahap berikutnya, jadi catatannya ikut
         * kartu keputusan.
         */
        catatanPutusan() {
            const p = this.putusanTahap;
            if (!p || this.catatanMasuk?.urutan === p.urutan) return null;

            return this.catatanKandidat.find((c) => c.urutan === p.urutan) || null;
        },
        /** Catatan lain yang sudah lewat — dilipat, tidak dibuang. */
        catatanLama() {
            const tampil = [this.catatanMasuk?.urutan, this.catatanPutusan?.urutan];

            return this.catatanKandidat.filter((c) => !tampil.includes(c.urutan));
        },
        isMtCategory() {
            const cat = (this.lamaran?.kategori || this.lamaran?.Kategori || this.lamaran?.kategori_program || '')
                .toString()
                .toUpperCase();
            return cat === 'MT';
        },
        // [feat/feedback]
        feedbackPending() {
            return this.$page.props.feedbackPending;
        },
        komponen() {
            return this.tugas ? komponenFormulir(this.tugas.komponen) : null;
        },
        skemaTugas() {
            return this.tugas?.schema || skemaFormulir(this.tugas?.komponen);
        },
        totalTahap() {
            return this.lamaran.totalTahap || this.tahap.length || 0;
        },
        /**
         * Nada status DARI SERVER (turunan Master Hasil Keputusan).
         *
         * Peta literal sebelumnya hanya mengenal tiga status, sehingga lamaran
         * ber-status MENGUNDURKAN_DIRI jatuh ke cadangan 'berjalan' — halaman
         * menampilkan proses yang masih berlangsung untuk lamaran yang sudah
         * ditutup kandidatnya sendiri.
         */
        stKey() {
            return ST[this.lamaran.statusNada] ? this.lamaran.statusNada : 'berjalan';
        },
        /** Status yang SUDAH final — dipakai menghentikan animasi "sedang jalan". */
        lamaranBerhenti() {
            return !!this.lamaran.status && this.lamaran.status !== 'BERJALAN';
        },
        katChipStyle() {
            return this.lamaran.kategori === 'MT'
                ? { background: 'rgba(245,158,11,.14)', color: '#b45309' }
                : this.lamaran.kategori === 'INTERNSHIP'
                  ? { background: 'rgba(16,185,129,.12)', color: '#059669' }
                  : { background: 'rgba(99,102,241,.12)', color: '#4f46e5' };
        },
        stChipStyle() {
            const t = ST[this.stKey];
            return { background: t.bg, color: t.c };
        },
        posisiSekarang() {
            const aktif = this.tahap.find((t) => t.status === 'BERJALAN');
            if (aktif) return aktif.urutan;
            const selesai = this.tahap.filter((t) => t.status === 'SELESAI').length;
            return Math.min(selesai + 1, this.totalTahap) || this.lamaran.urutanTahap || 1;
        },
        persen() {
            if (!this.totalTahap) return 0;
            const selesai = this.tahap.filter((t) => t.status === 'SELESAI' && t.hasil !== 'GUGUR').length;
            return Math.round((selesai / this.totalTahap) * 100);
        },
        tahapAktif() {
            return this.tahap.find((t) => t.status === 'BERJALAN') || null;
        },
        // Seluruh aktivitas tahap aktif, apa pun tipenya.
        aktivitas() {
            return this.tahapAktif?.tes || [];
        },
        /**
         * Tahap ini masih menunggu ISIAN kandidat?
         *
         * `tugas` hanya dikirim server untuk tahap BERJALAN yang formulirnya
         * belum terisi (lihat LamaranController: `whereNull Formulir_Pengisian_Id`),
         * jadi keberadaannya sudah menjawab pertanyaannya. Id-nya tetap
         * dicocokkan supaya lencana aktivitas tidak ikut menyala bila yang
         * menunggu isian ternyata tahap lain.
         */
        isianTertunda() {
            return !! this.tugas && this.tugas.tahapId === this.tahapAktif?.id;
        },
        // Aktivitas ujian online saja — hanya inilah yang punya token & jendela
        // waktu. Wawancara/tes manual di tahap yang sama TIDAK ikut ke sini.
        tesList() {
            return this.aktivitas.filter((x) => x.eksternal);
        },
        // Aktivitas non-online yang masih ditunggu (wawancara, tes manual, MCU).
        aktivitasManual() {
            return this.aktivitas.filter((x) => !x.eksternal && !x.selesai);
        },
        /**
         * AKTIVITAS TAHAP AKTIF, SIAP DIRENDER — inti perbaikan halaman ini.
         *
         * Dulu tidak ada daftar semacam ini. Yang ada `sesi`: SATU aktivitas
         * online terpilih yang mewakili seluruh tahap, dan keadaannya menjadi
         * gerbang bagi segalanya. Psikotes yang belum dijadwalkan menyembunyikan
         * kartu jadwal FGD berikut tombol unggah berkasnya — kandidat membaca
         * "Menunggu dijadwalkan" untuk aktivitas yang justru sedang menunggu
         * DIA.
         *
         * Sekarang tiap aktivitas berdiri sendiri, membawa keadaannya sendiri,
         * dan tak satu pun bisa menutupi yang lain.
         *
         * URUTANNYA dari `Urutan` yang disetel di Master Alur — sumber yang sama
         * dengan yang dipakai worklist dan mesin penguncian giliran. Server
         * sudah mengirimnya menaik; diurutkan lagi di sini supaya tampilan tidak
         * bergantung pada urutan kueri yang bisa berubah kapan saja.
         */
        /**
         * Seluruh aktivitas tahap ini, terurut & bernomor.
         *
         * TERMASUK yang belum gilirannya — dipakai menghitung nomor urut,
         * kalimat tahap, dan penentuan mode berurutan. Yang DITAMPILKAN
         * sebagai kartu adalah `aktivitasKartu`.
         */
        aktivitasSemua() {
            return [...this.aktivitas]
                .sort((a, b) => (a.urutan || 0) - (b.urutan || 0))
                .map((a, i) => ({ ...a, nomor: i + 1, keadaan: this.keadaanAktivitas(a) }));
        },
        /**
         * Nama lama, dipertahankan supaya seluruh pemanggil lain tidak berubah
         * artinya: yang mereka maksud memang "semua aktivitas tahap ini".
         */
        aktivitasTampil() {
            return this.aktivitasSemua;
        },
        /**
         * Kartu yang benar-benar dirender.
         *
         * ── BERURUTAN: SATU KARTU SAJA ────────────────────────────────────
         *
         * Aktivitas yang belum gilirannya TIDAK ditampilkan. Ia tidak bisa
         * dikerjakan, jadwalnya belum ada, dan satu-satunya yang bisa
         * dikatakan tentangnya adalah "tunggu yang di atas" — kalimat yang
         * sudah disampaikan kartu di atasnya. Menampilkannya sebagai kartu
         * penuh berarti dua blok besar yang menjelaskan satu kenyataan yang
         * sama, dan itulah yang membuat halaman terbaca sebagai informasi
         * ganda.
         *
         * Begitu aktivitas pertama tuntas dan giliran berpindah, server
         * membuka kuncinya dan kartunya muncul dengan sendirinya — itu inti
         * dari mode berurutan.
         *
         * Pada tahap BERSAMAAN tidak ada yang terkunci, jadi seluruhnya tetap
         * tampil — dan memang harus: semuanya bisa ia kerjakan sekarang.
         */
        aktivitasKartu() {
            return this.aktivitasSemua.filter((x) => !x.terkunci);
        },
        /**
         * Tahap ini dikerjakan BERURUTAN?
         *
         * Dibaca dari akibatnya (`terkunci`), bukan dari kode mode urutannya.
         * Penguncian giliran sudah dihitung server terhadap Master Mode Urutan
         * — termasuk atas aktivitas yang sengaja disembunyikan dari kandidat —
         * jadi menanyakannya ulang di sini hanya membuka peluang dua jawaban
         * yang berbeda untuk satu pertanyaan yang sama.
         */
        adaGiliran() {
            return this.aktivitasTampil.some((x) => x.terkunci);
        },
        /**
         * Aktivitas yang paling menuntut perhatian — dipakai spanduk atas.
         *
         * Urutan kepentingannya SAMA dengan `keadaanTahap` di halaman ini dan
         * dengan PipelineReadModel::bucket() di sisi admin. Ketiganya harus
         * sepakat: kalimat yang dibaca kandidat dan keadaan yang dilihat tim
         * tentang tahap yang sama tidak boleh bercerita berbeda.
         */
        aktivitasSorot() {
            // DISARING dengan tesTuntas(), bukan `selesai` saja.
            //
            // Tes yang sudah dikerjakan kandidat tapi belum ditutup tim masih
            // ber-`selesai` false. Pada tahap berisi DUA ATAU TIGA psikotes —
            // dan itu susunan yang wajar — tes yang baru saja ia selesaikan
            // bisa merebut spanduk dari tes berikutnya yang justru sedang
            // menunggu dikerjakan. Kalimat paling menonjol di halaman lalu
            // berbunyi "sudah kamu kerjakan" sementara ada tes lain yang
            // tombolnya terbuka di bawah.
            const hidup = this.aktivitasTampil.filter((x) => !this.tesTuntas(x) && !x.terkunci);

            return hidup.find((x) => x.keadaan.nada === 'aksi')
                || hidup.find((x) => x.keadaan.nada === 'jadwal')
                || hidup.find((x) => x.keadaan.nada === 'tunda')
                || hidup.find((x) => x.keadaan.nada === 'tunggu')
                || hidup[0]
                // Semuanya sudah tuntas: yang paling berhak disorot adalah yang
                // baru saja ia kerjakan — spanduk "sudah kamu kerjakan" tetap
                // muncul, bukan berganti jadi kartu kosong.
                || this.aktivitasTampil.find((x) => !x.selesai)
                || this.aktivitasTampil[0]
                || null;
        },
        /**
         * KEADAAN TAHAP dalam satu kalimat — pengganti daftar aktivitas.
         *
         * Yang dijawab hanya dua hal yang memang milik kandidat: apakah ada
         * yang harus ia kerjakan sekarang, dan kalau tidak, ia sedang menunggu
         * apa. Nama-nama asesmen yang belum dilalui SENGAJA tidak disebut —
         * membeberkannya kepada orang yang sedang dinilai memberinya waktu
         * mempersiapkan alat ukurnya, dan itu merusak nilai ukurnya sendiri.
         *
         * Urutannya dari yang paling menuntut tindakan ke yang paling pasif;
         * yang pertama cocok yang dipakai, supaya kandidat tidak pernah membaca
         * "sedang diproses" padahal ada tes yang menunggu dikerjakannya.
         */
        /**
         * Aktivitas yang jawabannya SUDAH dikirim tapi hasilnya belum sampai.
         *
         * Null berarti tidak ada yang ditunggu — entah karena kandidat memang
         * tidak baru pulang dari ujian, atau karena hasilnya sudah masuk.
         */
        tesDitunggu() {
            if (!this.pulangTes) return null;

            // Tahap sudah bergerak sejak kandidat berangkat ujian → jejaknya
            // bercerita tentang tahap yang sudah lewat. Diperiksa lebih dulu
            // daripada pencocokan id: tes tahap BERIKUTNYA tidak boleh terpungut
            // hanya karena kebetulan ia satu-satunya tes daring yang tersisa.
            if (this.pulangTes.tahap && this.tahapAktif?.id && this.pulangTes.tahap !== this.tahapAktif.id) {
                return null;
            }

            const t = (this.aktivitas || []).find((x) => x.id === this.pulangTes.id);

            // Aktivitasnya tak lagi ada di tahap aktif = tahapnya sudah bergerak.
            // Itu kabar yang lebih baru daripada jejak kita, jadi jejaknya kalah.
            if (!t) return null;

            if (this.sudahSelesai(t) || t.selesai) return null;

            // ── HARUS ADA BUKTI DARI SERVER, BUKAN CUMA JEJAK KLIK ──────────
            //
            // Jejaknya ditulis saat kandidat MENEKAN "Mulai Tes", bukan saat ia
            // mengirim jawaban — pada saat itu belum ada satu pun jawaban yang
            // dikirim. Kandidat yang menekan tombolnya, sampai di HCLearn, lalu
            // kembali TANPA login atau mengerjakan apa pun, akan disambut
            // "Jawabanmu sudah terkirim" untuk tes yang belum ia sentuh.
            //
            // Bagi kandidat itu bukan sekadar keliru, tapi menyesatkan: ia
            // mengira tesnya sudah beres dan tidak perlu dikerjakan lagi.
            //
            // Jadi jejaknya hanya boleh dipercaya bila server SETUJU bahwa ada
            // sesuatu yang sedang berjalan: entah ujiannya sudah tidak bisa
            // diakses lagi (`bisaAkses` false, artinya CAT sudah menutupnya),
            // atau statusnya memang sudah bergerak dari BELUM.
            //
            // Selama server masih menawarkan tesnya, yang benar adalah tombol
            // "Mulai Tes Sekarang" — bukan ucapan terima kasih.
            const status = String(t.ujian?.statusPengerjaan || '').toLowerCase();
            const dikerjakan = status !== '' && status !== 'belum';

            if (t.ujian?.bisaAkses && !dikerjakan) return null;

            return t;
        },
        keadaanTahap() {
            const akt = this.aktivitas || [];
            const belum = akt.filter((x) => !x.selesai);

            // BARU PULANG DARI RUANG UJIAN — didahulukan dari keadaan mana pun.
            // Selama hasilnya belum sampai, keadaan lain masih membaca tes itu
            // sebagai "bisa dikerjakan sekarang", dan kandidat yang baru saja
            // menekan kirim akan disambut ajakan mengerjakannya lagi.
            if (this.tesDitunggu) {
                return {
                    nada: 'kirim',
                    ikon: 'bi-send-check-fill',
                    judul: 'Jawaban tesmu sudah terkirim',
                    pesan: 'Terima kasih sudah menyelesaikan tes. Hasilnya sedang diterima sistem — halaman ini memperbarui dirinya sendiri, jadi tidak perlu kamu muat ulang.',
                };
            }

            // TAHAP YANG SELURUH ISINYA DIKERJAKAN TIM — kandidat tidak punya
            // satu pun aktivitas yang terlihat di sini. Negosiasi penawaran
            // begitu: dijadwalkan tim untuk dirinya sendiri, dan jadwalnya
            // sengaja tidak diumumkan.
            //
            // Dibedakan dari cabang di bawahnya, yang berbunyi "seluruh
            // rangkaian tahap ini sudah KAMU selesaikan". Untuk tahap semacam
            // ini kalimat itu keliru dua kali: kandidat tidak menyelesaikan apa
            // pun di sini, dan mengatakan ia sudah selesai membuatnya menunggu
            // keputusan yang sebetulnya belum mulai dirundingkan.
            //
            // KALIMATNYA STATIS, DAN SENGAJA TIDAK MENJANJIKAN APA-APA.
            //
            // Pesan_Kandidat dari master TIDAK dipakai di sini. Milik negosiasi
            // berbunyi "Tim rekrutmen akan menghubungimu untuk membahas
            // penawaran" — dan kalimat semacam itu justru yang harus dihindari:
            // ia memberi tahu bahwa ada penawaran sedang disiapkan, menerbitkan
            // harapan atas sesuatu yang belum diputuskan, lalu membuat tiap hari
            // tanpa kabar terasa seperti penolakan. Padahal yang boleh diketahui
            // kandidat cuma satu hal yang sudah pasti: ia melewati tahap
            // sebelumnya, dan sekarang tidak ada yang perlu ia kerjakan.
            //
            // Tidak menyebut nama tahapnya, tidak menyebut apa yang dikerjakan
            // tim, tidak menjanjikan kabar baik.
            if (!akt.length) {
                return {
                    nada: 'tunggu',
                    ikon: 'bi-hourglass-split',
                    judul: 'Kamu sudah melewati tahap sebelumnya',
                    pesan: this.tahapAktif?.adaJadwalInternal
                        ? PESAN_TAHAP_INTERNAL
                        : 'Tidak ada yang perlu kamu kerjakan saat ini. Perkembangan berikutnya muncul di halaman ini.',
                };
            }

            // Kartu keadaan dan banner tidak boleh berselisih di satu layar:
            // yang satu berkata "menunggu jadwal", yang lain kalimat tetap.
            // Selama tahapnya ditangani tim, keduanya berbunyi sama.
            if (this.tahapAktif?.adaJadwalInternal && !this.tahapAktif?.hasilTampil && !akt.some((x) => x.jadwal)) {
                return {
                    nada: 'tunggu',
                    ikon: 'bi-hourglass-split',
                    judul: 'Sedang ditangani tim rekrutmen',
                    pesan: PESAN_TAHAP_INTERNAL,
                };
            }

            if (!belum.length) {
                return {
                    nada: 'proses',
                    ikon: 'bi-hourglass-split',
                    judul: 'Seluruh rangkaian tahap ini sudah kamu selesaikan',
                    pesan: 'Hasilnya sedang ditinjau tim rekrutmen. Keputusannya muncul di halaman ini begitu terbit, dan kamu juga diberi tahu lewat email.',
                };
            }

            // Ada yang bisa dikerjakan SEKARANG (tes online terbuka).
            if (belum.some((x) => x.ujian?.bisaAkses && !x.terkunci)) {
                return {
                    nada: 'aksi',
                    ikon: 'bi-play-circle-fill',
                    judul: 'Ada aktivitas yang bisa kamu kerjakan sekarang',
                    pesan: 'Ikuti petunjuk pada kartu di bawah. Pastikan koneksi stabil sebelum memulai.',
                };
            }

            // DITAHAN TIM. Rangkaian berikutnya sengaja belum dibuka karena
            // hasil sebelumnya sedang ditinjau. Ini keadaan yang paling mudah
            // disalahpahami kandidat sebagai "sistemnya macet", jadi ia
            // didahulukan dan disebut apa adanya.
            if (belum.every((x) => x.terkunci) && belum.length) {
                return {
                    nada: 'tinjau',
                    ikon: 'bi-shield-lock-fill',
                    judul: 'Hasil kamu sedang ditinjau',
                    pesan: 'Rangkaian berikutnya dibuka setelah tim rekrutmen selesai meninjau bagian yang sudah kamu jalani. Tidak ada yang perlu kamu lakukan sekarang.',
                };
            }

            // Sudah ada undangan bertanggal — itu yang paling perlu ia tahu.
            const terjadwal = belum.find((x) => x.jadwal);
            if (terjadwal) {
                return {
                    nada: 'jadwal',
                    ikon: 'bi-calendar-check-fill',
                    judul: 'Kamu sudah dijadwalkan',
                    pesan: 'Rincian waktu dan tempatnya ada di bawah. Undangan yang sama juga dikirim ke emailmu — mohon hadir tepat waktu.',
                };
            }

            // DITUNDA tim — sebut apa adanya, berikut perkiraan penggantinya.
            // Tanpa cabang ini kartu besar berbunyi "Menunggu jadwal" seolah
            // jadwalnya belum pernah ada.
            const ditunda = belum.find((x) => !x.jadwal && x.konfirmasi?.status === 'DITUNDA');
            if (ditunda) {
                const perkiraan = ditunda.konfirmasi?.tunda?.perkiraanTeks;
                return {
                    nada: 'tunda',
                    ikon: 'bi-pause-circle-fill',
                    judul: `Jadwal ${ditunda.label} ditunda`,
                    pesan: perkiraan
                        ? `Jadwal penggantinya diperkirakan ${perkiraan}. Rinciannya menyusul lewat email dan halaman ini — tidak ada yang perlu kamu lakukan sekarang.`
                        : 'Jadwal penggantinya akan dikabarkan lewat email dan halaman ini. Tidak ada yang perlu kamu lakukan sekarang.',
                };
            }

            // ══ TAK SATU PUN SISANYA MENUNGGU JADWAL ══
            //
            // Kartu keadaan dan lencana aktivitas di bawahnya membaca sumber
            // yang sama (Master_Tipe_Tahap.Flag_Jadwal, lihat keadaanAktivitas).
            // Tanpa cabang ini keduanya berselisih di satu layar: lencananya
            // berbunyi "Menunggu isianmu" sementara kalimat besar di atasnya
            // menyuruh kandidat menunggu jadwal yang tidak akan pernah terbit.
            if (belum.every((x) => ! x.perluJadwal)) {
                if (this.isianTertunda && belum.some((x) => x.berformulir)) {
                    return {
                        nada: 'aksi',
                        ikon: 'bi-pencil-square',
                        judul: 'Ada yang perlu kamu lengkapi',
                        pesan: 'Isi formulir pada kartu di halaman ini lalu kirim — tahap berikutnya terbuka setelah isianmu masuk.',
                    };
                }

                return {
                    nada: 'tinjau',
                    ikon: 'bi-hourglass-split',
                    judul: 'Sedang diproses tim rekrutmen',
                    pesan: 'Tahap ini tidak dijadwalkan — tim yang mengerjakannya. Tidak ada yang perlu kamu lakukan sekarang; perkembangannya muncul di halaman ini.',
                };
            }

            return {
                nada: 'tunggu',
                ikon: 'bi-clock-history',
                judul: 'Menunggu jadwal dari tim rekrutmen',
                pesan: 'Tahap ini masih berjalan dan belum ada yang perlu kamu kerjakan. Begitu jadwalnya ditetapkan, rinciannya muncul di halaman ini dan dikirim ke emailmu.',
            };
        },

        // Apa yang terjadi SETELAH tes dikerjakan. Sengaja TIDAK menyebut lulus
        // atau tidak: kapan hasil boleh dilihat kandidat diatur Mode Pengumuman
        // tahap, jadi membocorkannya di sini akan mendahului aturan itu.
        pesanSetelahTes() {
            if (this.tahapAktif?.otomatis) {
                return 'Hasilnya diproses otomatis oleh sistem. Begitu keputusan tahap ini terbit, status di halaman ini langsung berubah — kamu juga diberi tahu lewat email.';
            }
            // Diperiksa dari SELURUH aktivitas tahap, bukan hanya `tesList` yang
            // berisi ujian online saja. Satu tahap bisa memuat psikotes online +
            // tes manual + wawancara; kalau hanya yang online dihitung, kandidat
            // yang baru selesai ujian diberi tahu "hasil sedang ditinjau" padahal
            // dua aktivitas lain belum dijalankan sama sekali.
            const sisa = this.aktivitas.filter((x) => !x.selesai);
            if (sisa.length) {
                // NAMA AKTIVITAS YANG TERSISA TIDAK DISEBUT.
                //
                // Sebelumnya kalimat ini berbunyi "masih menunggu: DISC, FGD,
                // Wawancara" — membeberkan alat ukur yang belum dilalui kepada
                // orang yang sedang diukur. Yang berguna baginya bukan nama
                // asesmennya, melainkan bahwa tahapnya belum selesai dan tak ada
                // yang perlu ia kerjakan sekarang.
                return (
                    'Tahap ini masih berjalan. Tim rekrutmen akan menghubungi kamu bila ada yang perlu kamu ikuti berikutnya — ' +
                    'keputusan tahap baru diambil setelah seluruh rangkaiannya rampung.'
                );
            }

            return 'Hasilnya sudah masuk dan sedang ditinjau tim rekrutmen. Keputusan tahap ini akan muncul di halaman ini begitu terbit.';
        },
        // Kalimat untuk tahap yang ditangani tim — diambil dari aktivitas yang
        // sedang ditunggu, teksnya dari Master Tipe Tahap (bukan dari kode ini).
        pesanTahap() {
            const t = this.tahapAktif;
            if (!t) return { judul: '', sub: '', teks: '' };

            // Formulir tahap ini SUDAH dikirim -> berhenti memintanya lagi.
            // Kalimat "lengkapi formulir di bawah" pada kandidat yang baru saja
            // mengirim membuatnya mengira kiriman tadi tidak terbaca.
            if (t.sudahIsi) {
                return {
                    judul: 'Terima kasih — data kamu sudah lengkap',
                    sub: t.label,
                    teks: 'Formulir dan berkasmu sudah kami terima dan sedang diperiksa tim rekrutmen. Hasil tahap ini akan muncul di halaman ini begitu terbit.',
                };
            }
            const a = this.aktivitasManual[0] || null;
            const nama = a?.tipeNama || t.tipeNama;
            return {
                judul: nama ? `Menunggu ${nama.toLowerCase()}` : 'Sedang ditangani tim rekrutmen',
                sub: a && this.aktivitas.length > 1 ? a.label : nama || 'Proses seleksi',
                teks: a?.pesan || t.pesan || PESAN_UMUM,
            };
        },
        /**
         * SUBJUDUL KARTU TAHAP — satu baris kecil di bawah nama tahap.
         *
         * "Ikuti aktivitas di bawah sesuai jadwalnya" adalah PERINTAH, dan
         * perintah untuk sesuatu yang sudah dikerjakan terbaca sebagai
         * "kamu belum mengerjakannya". Kandidat yang baru menyelesaikan
         * psikotesnya membacanya sebagai kabar bahwa kirimannya tidak
         * terbaca — persis ketika lencana di bawahnya berbunyi "Selesai".
         *
         * "dikerjakan berurutan" DITURUNKAN dari data (`terkunci`), bukan
         * dari membandingkan kode mode dengan 'BERURUTAN' di sini.
         * Penguncian gilirannya sudah dihitung server dari Master Mode
         * Urutan; yang sampai ke layar cuma akibatnya. Dengan begitu mode
         * urutan ketiga yang ditambahkan lewat master ikut terbaca tanpa
         * menyentuh berkas ini.
         */
        subTahap() {
            const akt = this.aktivitasTampil || [];
            if (!akt.length) return this.pesanTahap.sub;

            // Tak ada satu pun yang masih bisa ia kerjakan.
            if (akt.every((a) => this.tesTuntas(a))) {
                return akt.length > 1
                    ? 'Seluruh aktivitas tahap ini sudah selesai — tinggal menunggu hasilnya.'
                    : 'Aktivitas tahap ini sudah selesai — tinggal menunggu hasilnya.';
            }

            if (akt.length > 1) {
                return `Tahap ini terdiri dari ${akt.length} aktivitas${this.adaGiliran ? ', dikerjakan berurutan' : ''}.`;
            }

            return 'Ikuti aktivitas di bawah sesuai jadwalnya.';
        },
        banner() {
            if (this.stKey === 'lolos')
                return {
                    title: 'Selamat, kamu diterima! 🎉',
                    text: 'Seluruh tahap seleksi telah kamu selesaikan. Tim rekrutmen akan menghubungimu.',
                };
            if (this.stKey === 'gugur')
                return {
                    title: 'Belum lolos pada tahap ini',
                    text:
                        this.lamaran.alasanGugur ||
                        'Terima kasih atas partisipasimu. Jangan menyerah — banyak peluang lain menantimu.',
                };

            // TALENT POOL — dua kabar sekaligus, dan keduanya harus terdengar:
            // TIDAK LOLOS di lowongan ini, TAPI datanya disimpan untuk kesempatan
            // berikutnya. Dulu keadaan ini tidak punya cabang sama sekali, jadi
            // ia jatuh ke cadangan di bawah dan kandidat yang lamarannya sudah
            // DITUTUP dibalas "Lamaranmu sedang diproses" — menyuruhnya menunggu
            // kabar yang tidak akan pernah datang. Catatan yang ditulis tim ikut
            // hilang bersamanya, padahal justru di sinilah ia paling berarti:
            // ia menerangkan kenapa disimpan, bukan sekadar kenapa berhenti.
            if (this.stKey === 'menunggu')
                return {
                    title: 'Belum lolos di posisi ini — datamu kami simpan',
                    text:
                        this.lamaran.alasanGugur ||
                        'Kamu belum lolos untuk posisi ini, tetapi profilmu kami simpan di Talent Pool. Kami menghubungimu lebih dulu begitu ada posisi yang cocok.',
                };

            // Ditutup oleh KANDIDAT sendiri (mengundurkan diri / menolak
            // penawaran). Judulnya diambil dari master supaya kalimatnya persis
            // sama dengan yang tercatat, dan tidak berbunyi seperti penolakan.
            if (this.stKey === 'netral')
                return {
                    title: this.lamaran.statusLabel || 'Proses seleksi dihentikan',
                    text:
                        this.lamaran.alasanGugur ||
                        'Proses seleksimu untuk posisi ini sudah ditutup. Kamu tetap bisa melamar lowongan lain di EVO Group.',
                };

            const cur = this.tahapAktif;
            if (!cur)
                return { title: 'Lamaranmu sedang diproses', text: 'Pantau halaman ini untuk perkembangan seleksimu.' };

            const posisi = `Tahap ${cur.urutan} dari ${this.totalTahap}`;

            // ══ TAHAP YANG JADWALNYA DITANGANI TIM SENDIRI (negosiasi) ══
            //
            // Didahulukan dari seluruh keadaan di bawahnya, dan sengaja TIDAK
            // membedakan sudah/belum dijadwalkan — lihat PESAN_TAHAP_INTERNAL.
            //
            // Tanpa cabang ini kandidat jatuh ke kalimat bawaan "menunggu jadwal
            // dari tim rekrutmen … begitu jadwalnya ditetapkan, rinciannya
            // muncul di halaman ini dan dikirim ke emailmu" — janji yang tidak
            // akan pernah ditepati, karena jadwal yang dimaksud memang sengaja
            // tidak pernah dikirimkan kepadanya.
            //
            // DUA HAL MEMBATALKANNYA, dan dua-duanya berarti ada sesuatu yang
            // NYATA untuk kandidat:
            //   - keputusan tahapnya sudah terbit (hasilTampil) → layar berganti
            //     ke kabar keputusan itu;
            //   - ada aktivitas TERLIHAT yang sudah punya jadwal — mis. surat
            //     penawaran yang dijadwalkan untuk diserahkan. Ia memang harus
            //     datang, jadi jadwalnya tidak boleh tertutup kalimat ini.
            const adaJadwalTampak = (this.aktivitas || []).some((x) => x.jadwal);

            if (cur.adaJadwalInternal && !cur.hasilTampil && !adaJadwalTampak) {
                return { title: `${posisi} · ${cur.label}`, text: PESAN_TAHAP_INTERNAL };
            }

            // Ada yang harus DIKERJAKAN kandidat → itu yang disebut lebih dulu.
            if (this.tugas && (this.komponen || this.tugas.schema)) {
                // Batas pengisian sudah lewat & formulirnya terkunci: yang harus
                // dilakukan bukan lagi mengisi, melainkan menghubungi tim.
                if (this.formTerkunci) {
                    const nama = this.tugas.formulirNama || 'Formulir';
                    const b = this.tugas.batas;
                    let text = `Batas pengisian ${nama} sudah lewat. Hubungi tim rekrutmen bila memerlukan perpanjangan.`;
                    if (b.belumDiatur) text = `${nama} belum dibuka. Tim rekrutmen akan mengumumkan jadwal pengisiannya.`;
                    else if (this.belumBukaLive) text = `${nama} dibuka ${b.bukaTeks}, paling lambat ${b.teks}.`;

                    return { title: `${posisi} · ${cur.label}`, text };
                }

                return {
                    title: `${posisi} · ${cur.label}`,
                    text: `Lengkapi ${this.tugas.formulirNama || 'formulir'} di bawah untuk melanjutkan ke tahap berikutnya.`
                        + (this.tugas.batas?.batas && !this.tugas.batas.lewat ? ` Batasnya ${this.tugas.batas.teks}.` : ''),
                };
            }

            // SPANDUK MENGIKUTI AKTIVITAS YANG PALING MENUNTUT PERHATIAN.
            //
            // Dulu ia mengikuti `sesi` — yang selalu aktivitas ONLINE. Pada
            // tahap "FGD + Psikotes + Wawancara", spanduknya berbunyi "psikotes
            // menunggu jadwal" sementara yang sebenarnya menunggu kandidat hari
            // itu adalah FGD yang sudah dijadwalkan dan berkasnya belum
            // diunggah. Kalimat paling menonjol di halaman menunjuk hal yang
            // salah.
            const a = this.aktivitasSorot;

            if (a && !a.terkunci) {
                const u = a.ujian;
                const nama = a.label || cur.label;

                if (a.eksternal) {
                    if (!u || !u.terjadwal) {
                        return {
                            title: `${posisi} · ${nama} — menunggu jadwal`,
                            text: a.pesan || cur.pesan || PESAN_UMUM,
                        };
                    }
                    if (u.statusPengerjaan === 'selesai') {
                        return { title: `${nama} sudah kamu kerjakan`, text: this.pesanSetelahTes };
                    }
                    if (u.belumMulai) {
                        const mundur = this.hitungMundur(a);
                        return {
                            title: `${nama} dijadwalkan ${this.fmtWaktu(u.waktuMulai)}`,
                            text: `Tesnya belum dibuka${mundur ? ` — ${mundur}` : ''}. Siapkan koneksi internet, kamera, dan ruangan yang tenang.`,
                        };
                    }
                    if (u.sudahLewat) {
                        return {
                            title: `Jendela ${nama} sudah lewat`,
                            text: 'Waktu pengerjaan berakhir. Hubungi tim rekrutmen bila kamu terkendala saat tes.',
                        };
                    }

                    return {
                        title: `${nama} bisa dikerjakan sekarang`,
                        text: `Tesnya terbuka sampai ${this.fmtWaktu(u.waktuSelesai)}. Tekan "Mulai Tes Sekarang" di bawah.`,
                    };
                }

                // Aktivitas yang ditangani tim: berkas dulu — itu satu-satunya
                // yang benar-benar menuntut kandidat — baru jadwalnya.
                if (a.unggah?.tertutup) {
                    return {
                        title: `${posisi} · ${nama} — batas unggah sudah lewat`,
                        text: 'Kotak unggahnya sudah ditutup. Hubungi tim rekrutmen bila kamu membutuhkan perpanjangan.',
                    };
                }
                if (a.unggah && !a.unggah.terkirim) {
                    return {
                        title: `${posisi} · ${nama} — menunggu berkasmu`,
                        text: (a.unggah.petunjuk || 'Unggah berkas yang diminta pada aktivitas ini, lalu tekan kirim bila sudah lengkap.')
                            + (a.unggah.batasTeks ? ` Paling lambat ${a.unggah.batasTeks}.` : ''),
                    };
                }
                if (a.jadwal) {
                    return {
                        // Berrentang tanggal (MCU vendor / mandiri): tidak ada jam
                        // janji temu — "dijadwalkan 11 Sep 00.00" menyesatkan.
                        title: a.jadwal.batasWaktu
                            // Titik tengah, bukan tanda pisah — rentangnya sendiri sudah
                            // memakai "–" ("30 September – 07 Oktober 2026").
                            ? `${nama} · ${a.jadwal.rentangTeks || a.jadwal.batasTeks}`
                            : `${nama} dijadwalkan ${this.fmtWaktu(a.jadwal.mulai)}`,
                        text: a.jadwal.catatan || 'Rincian tempat dan waktunya ada di kartu jadwal pada halaman ini.',
                    };
                }
                // DITUNDA tim — spanduk menyebut penundaannya, bukan pesan
                // "menunggu jadwal" umum tahap ini.
                if (a.konfirmasi?.status === 'DITUNDA') {
                    const perkiraan = a.konfirmasi.tunda?.perkiraanTeks;
                    return {
                        title: `${posisi} · ${nama} ditunda`,
                        text: perkiraan
                            ? `Jadwal penggantinya diperkirakan ${perkiraan}. Rinciannya menyusul lewat email dan halaman ini.`
                            : 'Jadwal penggantinya akan dikabarkan lewat email dan halaman ini.',
                    };
                }
            }

            return { title: `${posisi} · ${cur.label}`, text: this.pesanTahap.teks };
        },
        /** Tahap terakhir yang keputusannya SUDAH boleh dilihat kandidat. */
        putusanTahap() {
            const sudah = this.tahap.filter((t) => t.hasilTampil && t.hasil);
            const t = sudah.length ? sudah[sudah.length - 1] : null;
            if (!t) return null;

            // Sudah mengerjakan tahap SESUDAHNYA -> keputusan lama tidak relevan
            // lagi dan hanya menutupi keadaan terkini. Kandidat tetap bisa
            // melihatnya di timeline Alur Seleksi.
            const berikut = this.tahap.find((x) => x.urutan === t.urutan + 1);
            if (berikut && (berikut.sudahIsi || berikut.status === 'SELESAI')) return null;

            const lolos = t.hasil === 'LULUS';
            const lanjut = this.tahap.find((x) => x.urutan === t.urutan + 1) || null;

            let teks;
            if (!lolos) {
                teks =
                    this.lamaran.alasanGugur ||
                    'Terima kasih sudah mengikuti tahap ini. Kamu tetap bisa melamar lowongan lain di EVO Group.';
            } else if (lanjut) {
                teks = `Selamat, kamu lanjut ke tahap ${lanjut.urutan} — ${lanjut.label}.`;
            } else {
                teks = 'Selamat, kamu menyelesaikan seluruh tahap seleksi.';
            }
            if (t.diputusAt) teks += ` Diputuskan ${this.fmtWaktu(t.diputusAt)}.`;

            return { urutan: t.urutan, label: t.label, lolos, teks };
        },
        /** Sudah diputus tapi hasilnya belum boleh diumumkan. */
        tahapTertunda() {
            const tunda = this.tahap.filter((t) => t.menungguPengumuman);
            return tunda.length ? tunda[tunda.length - 1] : null;
        },
        inisialNama() {
            const n = (this.profilKandidat?.nama || '').trim();
            return n ? n.charAt(0).toUpperCase() : '?';
        },
        /**
         * RINGKASAN identitas kandidat — bukan salinan seluruh formulir.
         *
         * ══ KENAPA DIPERPENDEK ══
         *
         * Kartu ini dulu menuangkan SETIAP jawaban formulir pendaftaran apa
         * adanya, sebagai pasangan label-nilai datar. Dua akibatnya sama-sama
         * buruk, dan keduanya terlihat di layar kandidat sendiri:
         *
         *   1. Jawaban berulang — keluarga, riwayat kerja, organisasi — punya
         *      bentuk baris, tapi yang digambar cuma ringkasan teksnya. Satu
         *      kotak berisi "Utama Hubungan: Ayah, Utama Nama: …, Utama Jk: L,
         *      Utama Usia: …" berdempet tanpa jeda: kalimat mesin, bukan data
         *      yang bisa dibaca orang.
         *
         *   2. Seluruh isinya SUDAH ada di "Formulir & Berkas" tepat di bawah,
         *      dalam bentuk yang benar. Jadi yang di atas bukan sekadar jelek —
         *      ia versi rusak dari sesuatu yang di bawahnya sudah betul.
         *
         * Sekarang: identitas pokok saja, dipilih lewat pola kunci, dan hanya
         * yang berupa satu nilai tunggal. Sisanya dibaca di bawah — utuh.
         */
        profilKandidat() {
            const f = this.formulir.find((x) => x.sumber === 'PENDAFTARAN') || this.formulir[0];
            if (!f) return null;

            const gambar = (f.berkas || []).filter((b) => b.isImage);
            const foto = gambar.find((b) => /foto/i.test(b.field || '')) || gambar[0] || null;

            const jawaban = f.jawaban || [];
            const dipakai = new Set();
            const slotTerisi = new Set();
            const data = [];

            RINGKAS_IDENTITAS.forEach((r) => {
                if (slotTerisi.has(r.slot)) return;

                const j = jawaban.find(
                    (x) =>
                        !dipakai.has(x.key) &&
                        !POLA_SEMBUNYI.test(x.key || '') &&
                        r.pola.test(x.key || '') &&
                        // Hanya nilai TUNGGAL. Baris berulang, daftar butir, dan
                        // berkas punya bentuknya sendiri di bawah; dipaksa masuk
                        // ke kotak sesempit ini, semuanya kembali jadi teks
                        // berdempet yang justru sedang diperbaiki.
                        !(x.baris || []).length &&
                        !(x.daftar || []).length &&
                        !x.berkas &&
                        String(x.nilai ?? '').trim() !== '',
                );
                if (!j) return;

                dipakai.add(j.key);
                slotTerisi.add(r.slot);
                data.push({ key: j.key, label: r.label, nilai: this.nilaiTampil(f, j) });
            });

            const nama = jawaban.find((j) => POLA_NAMA.test(j.key || ''))?.nilai || '';

            return { foto, nama, data, waktuKirim: f.waktuKirim };
        },
        /** Konteks formulir + berkas draf yang sudah tersimpan di server + yang gagal naik. */
        konteksForm() {
            return { ...(this.konteks || {}), berkasDraf: this.berkasDraf, berkasGagal: this.berkasGagal };
        },
        /** Tab isi bawah — hanya yang memang ada isinya yang ditampilkan. */
        tabs() {
            const out = [];
            if (this.isMtCategory) out.push({ k: 'alur', label: 'Alur Seleksi', ikon: 'bi-list-task' });
            // TAB "DETAIL LOWONGAN" DIHAPUS.
            //
            // Halaman ini menjawab "bagaimana lamaranku berjalan". Rincian
            // lowongannya — deskripsi, kualifikasi, benefit — sudah dibaca
            // kandidat SEBELUM ia melamar, dan masih terbuka di halaman
            // lowongannya sendiri. Menyalinnya ke sini membuat halaman proses
            // dibuka untuk membaca ulang iklan, sementara yang benar-benar
            // ditunggu (jadwal, tes, keputusan) terdorong ke bawah.
            if (this.formulir.length) {
                out.push({
                    k: 'berkas',
                    label: 'Formulir & Berkas',
                    ikon: 'bi-folder-fill',
                    jml: this.formulir.length,
                });
            }
            // TAB "HASIL TAHAPAN" DIHAPUS.
            //
            // Isinya menggandakan apa yang sudah terbaca di halaman ini: hasil
            // tiap tahap tampil di kartu keputusan paling atas dan di timeline
            // progres, lengkap dengan status dan tanggalnya. Tab tersendiri
            // membuat kandidat mengira ada keterangan LAIN yang belum ia baca,
            // lalu membukanya dan menemukan hal yang sama — hanya dengan tata
            // letak berbeda.
            //
            // `catatanTahap` sengaja DIPERTAHANKAN: dipakai bagian lain dan
            // menghapusnya hanya menambah perubahan tanpa manfaat.

            return out;
        },
        /**
         * Catatan keputusan per tahap yang boleh dibaca kandidat.
         *
         * NILAI tidak ikut — kandidat cukup tahu keterangannya, bukan angkanya.
         * Hanya tahap yang hasilnya sudah boleh diumumkan yang masuk, supaya
         * aturan Mode Pengumuman tetap dihormati di sini.
         *
         * CATATAN PER AKTIVITAS DIHAPUS DARI SINI. Yang ditulis tim di sana
         * adalah penilaian internal ("gugup, tidak direkomendasikan"), dan sejak
         * catatannya bisa memuat lembar penilaian terpindai, menampilkannya
         * berarti menyerahkan rapor internal ke kandidat. Server pun tidak lagi
         * mengirimkannya — lihat LamaranController::portalDetail().
         */
        catatanTahap() {
            return this.tahap
                .filter((t) => t.hasilTampil && (t.catatan || (t.berkas || []).length))
                .map((t) => ({
                    urutan: t.urutan,
                    label: t.label,
                    hasil: t.hasil,
                    catatan: t.catatan,
                    at: t.diputusAt,
                    berkas: t.berkas || [],
                }));
        },
        /**
         * Aktivitas tahap aktif yang meminta kandidat mengunggah berkas.
         *
         * Dipakai METODE, bukan tampilan: layar merender unggahan di dalam blok
         * aktivitasnya masing-masing (lihat `aktivitasTampil`). Dua saudaranya
         * yang dulu berdiri di sini — `jadwalAktivitas` dan `hasilMcu` — sudah
         * dihapus karena keduanya memang hanya melayani tiga loop terpisah yang
         * mengelompokkan isi tahap per JENIS KOMPONEN, bukan per aktivitas.
         * Itulah yang membuat kartu jadwal FGD dan tombol unggahnya terpisah
         * jauh di layar seolah tak berhubungan.
         */
        unggahAktivitas() {
            return this.aktivitas
                .filter((x) => x.unggah && !x.selesai)
                .map((x) => ({ key: x.urutan, id: x.id, label: x.label, ...x.unggah }));
        },
        k() {
            return this.kartu || {};
        },
        ringkasan() {
            return this.k.ringkasan || this.k.deskripsi || '';
        },
        tanggungJawab() {
            return this.k.tanggungJawab || [];
        },
        persyaratan() {
            return this.k.persyaratan || [];
        },
        skill() {
            return this.k.skill || [];
        },
        benefit() {
            return [...(this.k.benefit || []), ...(this.k.fasilitas || [])];
        },
        // Fakta cepat — adaptif MT vs rekrutmen.
        facts() {
            const k = this.k;
            const out = [];
            const push = (t, v, icon) => {
                if (v) out.push({ t, v, icon: SVG(icon) });
            };
            if (this.lamaran.kategori === 'MT') {
                push('Jenis', k.tipeKegiatan || 'Management Trainee', P.topi);
                push('Penempatan', k.penempatan || this.lamaran.lokasi, P.pin);
                push('Durasi', k.durasi, P.jam);
                push('Ikatan', k.ikatan, P.doc);
            } else {
                push('Status Kerja', k.tipeKerja || 'Full-time', P.koper);
                push('Tempat Kerja', k.tempatKerja || 'On-site', P.build);
                push('Lokasi', k.lokasi || this.lamaran.lokasi, P.pin);
                push('Level', k.level, P.level);
                push('Pengalaman', k.pengalaman, P.jam);
            }
            return out;
        },
        heroSteps() {
            const src = this.tahap.length
                ? this.tahap
                : Array.from({ length: this.totalTahap }, (_, i) => ({
                      urutan: i + 1,
                      label: `Tahap ${i + 1}`,
                      status: 'MENUNGGU',
                  }));
            return src.map((t) => {
                // Tahap yang masih BERJALAN pada lamaran yang sudah BERHENTI
                // bukan lagi tahap "sedang berlangsung". Dulu hanya status
                // GUGUR yang dihitung, sehingga kandidat yang mengundurkan diri
                // tetap melihat tahapnya berkedip seolah prosesnya jalan terus.
                const st =
                    t.status === 'SELESAI'
                        ? t.hasil === 'GUGUR'
                            ? 'fail'
                            : 'done'
                        : t.status === 'BERJALAN'
                          ? this.lamaranBerhenti
                              ? this.stKey === 'gugur'
                                  ? 'fail'
                                  : 'todo'
                              : 'current'
                          : 'todo';
                return {
                    name: t.label,
                    st,
                    line: st === 'done' || st === 'current' ? '#a5b4fc' : st === 'fail' ? '#fca5a5' : '#e2e8f0',
                    lbl: st === 'todo' ? '#94a3b8' : st === 'fail' ? '#dc2626' : '#334155',
                };
            });
        },
        /**
         * Tahap yang sedang dijalani — untuk ringkasan progres, supaya terbaca
         * tanpa membuka stepper. Kata pengantarnya mengikuti keadaan: yang
         * berhenti di suatu tahap bukan sedang "berjalan" di sana.
         */
        tahapRingkas() {
            const steps = this.heroSteps;
            if (!steps.length) return null;
            const jalan = steps.find((s) => s.st === 'current');
            if (jalan) return { ...jalan, teks: 'Sedang berjalan:' };
            const gagal = steps.find((s) => s.st === 'fail');
            if (gagal) return { ...gagal, teks: 'Berhenti di:' };
            const selesai = [...steps].reverse().find((s) => s.st === 'done');
            if (selesai) return { ...selesai, teks: 'Tahap terakhir:' };
            return { ...steps[0], teks: 'Tahap berikutnya:' };
        },
        /**
         * WATERFALL alur seleksi — bertingkat menurut URUTAN TAHAP, bukan tanggal.
         *
         * KENAPA BUKAN TANGGAL LAGI
         * Bentuk sebelumnya adalah gantt sungguhan: batangnya diletakkan menurut
         * tanggal nyata, dan tahap yang belum terjadwal — yaitu hampir seluruh
         * sisa alur — diberi perkiraan tiga hari per tahap oleh kode ini sendiri,
         * lalu ditumpuk berurutan dari tanggal melamar. Perkiraan itu ditandai
         * "±", tapi tanda sekecil itu tidak menahan apa pun: yang dibaca kandidat
         * tetap sebuah tanggal, dan tanggal yang dipampang perusahaan terbaca
         * sebagai janji. Kandidat lalu menunggu sampai tanggal itu, lalu merasa
         * dilupakan ketika tak ada yang terjadi — padahal tak seorang pun pernah
         * menjanjikannya.
         *
         * Yang benar-benar dijawab bagan ini cuma "aku di sebelah mana", dan itu
         * dijawab urutan tahap tanpa perlu satu tanggal pun. Bentuk air terjunnya
         * tetap: tiap tahap bergeser satu langkah ke kanan dari tahap sebelumnya.
         */
        gantt() {
            const bgOf = (s) =>
                s === 'done'
                    ? 'linear-gradient(90deg,#8b5cf6,#6366f1)'
                    : s === 'current'
                      ? 'linear-gradient(90deg,#fbbf24,#f59e0b)'
                      : s === 'fail'
                        ? 'linear-gradient(90deg,#f87171,#ef4444)'
                        : '#dfe4ee';
            const stColorOf = (s) =>
                s === 'done' ? '#6366f1' : s === 'current' ? '#b45309' : s === 'fail' ? '#dc2626' : '#94a3b8';

            const n = this.tahap.length;
            if (!n) return { rows: [], kolom: 0 };

            // Satu tahap = satu kolom. Batangnya sengaja SELALU lebih lebar dari
            // satu kolom (dua kolom, dipotong di ujung) supaya tiap baris tetap
            // bertindih dengan tetangganya — itulah yang membuatnya terbaca
            // sebagai air terjun yang mengalir, bukan tangga yang terputus-putus.
            const lebarKolom = 100 / n;
            const rows = this.tahap.map((t, i) => {
                const state = this.tlState(t);
                const left = +(i * lebarKolom).toFixed(2);

                return {
                    urutan: t.urutan,
                    name: t.label,
                    statusLabel: this.stepStatus(t),
                    state,
                    left,
                    width: +Math.min(lebarKolom * 2, 100 - left).toFixed(2),
                    bg: bgOf(state),
                    stColor: stColorOf(state),
                    glow: state === 'current',
                };
            });

            return { rows, kolom: n };
        },
    },
    watch: {
        // Tab bawaan 'alur' hanya ada untuk kategori MT. Untuk kategori lain
        // daftar tabnya berbeda, dan tanpa penjagaan ini kandidat membuka
        // halaman ke panel yang tidak pernah dirender — terlihat kosong melompong.
        tabs: {
            immediate: true,
            handler(v) {
                if (v.length && !v.some((t) => t.k === this.tab)) this.tab = v[0].k;
            },
        },
    },
    mounted() {
        this.mqKompak = window.matchMedia('(max-width: 767.98px)');
        this.syncKompak();
        this.mqKompak.addEventListener('change', this.syncKompak);
        this.muatBerkasTes();
        if (this.tugas) {
            this.jawaban = jawabanAwal(this.skemaTugas, this.profil);
            this.muatDraf();
        }
        this.jam = setInterval(() => {
            this.now = Date.now();
        }, 15000);
        this.sambutPulangTes();
    },
    beforeUnmount() {
        this.mqKompak?.removeEventListener('change', this.syncKompak);
        if (this.jam) clearInterval(this.jam);
        if (this.tm) clearTimeout(this.tm);
        if (this.timerPindah) clearInterval(this.timerPindah);
        this.hentikanPantauHasil();
    },
    methods: {
        syncKompak() {
            this.kompak = !!this.mqKompak?.matches;
        },
        katLabel(k) {
            return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k || '—';
        },
        /**
         * Status satu formulir = KEPUTUSAN tahapnya, bukan sekadar "sudah dikirim".
         *
         * Dulu labelnya hanya melihat waktuKirim, sehingga formulir tahap yang
         * SUDAH diloloskan tetap tertulis "Menunggu" berdampingan dengan formulir
         * yang memang belum diproses — kandidat tidak bisa membedakan keduanya.
         *
         * Hasil hanya dipakai bila memang sudah boleh diumumkan (hasilTampil),
         * jadi aturan Mode Pengumuman tetap dihormati di sini.
         */
        /**
         * Peta label + tipe + kelompok satu formulir, dihitung sekali.
         *
         * Sumbernya SKEMA BEKU milik pengisian itu (Schema_Snapshot_Json);
         * kode komponen hanya cadangan untuk formulir bawaan lama.
         *
         * ══ KENAPA INI YANG DIPAKAI, BUKAN LABEL_FIELD ══
         *
         * LABEL_FIELD disusun dari formulir BAWAAN saja. Formulir yang dirakit
         * lewat Master Formulir — dan sekarang semuanya begitu — tidak ada di
         * sana, jadi setiap kuncinya meleset dan jatuh ke tebakan server:
         * "V Nama", "Dok Cv", "Utama Hubungan". Kandidat membaca nama kolom
         * basis data sebagai ganti pertanyaan yang baru saja ia jawab sendiri.
         *
         * Layar admin sudah membaca snapshot sejak awal; sisi kandidatnya yang
         * tertinggal — dan justru dialah pemilik datanya.
         */
        petaSkema(f) {
            if (!f) return { label: {}, tipe: {}, grup: {}, bagian: {} };

            let peta = PETA_SKEMA.get(f);
            if (peta) return peta;

            const sumber = f.skema || f.komponen || null;
            peta = {
                label: labelField(sumber),
                tipe: tipeField(sumber),
                grup: grupField(sumber),
                bagian: grupBagian(sumber),
            };
            PETA_SKEMA.set(f, peta);

            return peta;
        },
        /** Label isian: skema beku dulu, lalu tebakan, lalu apa pun dari server. */
        labelIsian(f, j) {
            const peta = this.petaSkema(f);

            return (
                peta.label[j.key] ||
                peta.bagian[j.key]?.label ||
                LABEL_FIELD[j.key] ||
                this.labelTebakan(j.key) ||
                j.label
            );
        },
        /**
         * Label cadangan dari nama kunci — HANYA bila skemanya tak memuatnya.
         *
         * Awalan sependek satu-dua huruf dibuang: `v_nama` berarti "nama pada
         * langkah validasi" bagi yang menulis skemanya, tapi bagi yang
         * membacanya di layar "V Nama" cuma huruf nyasar di depan kata.
         */
        labelTebakan(key) {
            const potong = String(key || '').split(/[_-]+/).filter(Boolean);
            if (!potong.length) return '';
            if (potong.length > 1 && potong[0].length <= 2) potong.shift();

            return potong
                .map((w) => (AKRONIM.has(w.toLowerCase()) ? w.toUpperCase() : w.charAt(0).toUpperCase() + w.slice(1)))
                .join(' ');
        },
        /**
         * Nilai satu isian dalam bentuk yang dibaca ORANG.
         *
         * Nominal rupiah tampil sebagai uang. "9500000" memaksa pembacanya
         * menghitung digit sendiri untuk tahu ini sembilan juta atau sembilan
         * puluh juta — dan kandidat pun perlu memastikan angka yang ia tulis
         * memang terbaca benar.
         */
        nilaiTampil(f, j) {
            const teks = String(j.nilai ?? '').trim();
            if (teks === '') return '—';

            if (this.petaSkema(f).tipe[j.key] === 'currency' && /^\d+$/.test(teks)) {
                return 'Rp ' + Number(teks).toLocaleString('id-ID');
            }

            return teks;
        },
        /**
         * Isian bertipe DAFTAR BUTIR — digambar bernomor, bukan dirangkai koma.
         *
         * Bentuknya dikenali dari datanya sendiri (`daftar` berisi lebih dari
         * satu butir), bukan dari tipe di skema: jawaban lama yang terlanjur
         * tersimpan sebagai larik pun ikut terbaca benar, dan formulir yang
         * skemanya sudah tidak ada tetap tergambar utuh.
         */
        isianDaftar(j) {
            const d = (j.daftar || []).filter((x) => String(x ?? '').trim() !== '');

            return d.length > 1 ? d : null;
        },
        /**
         * Isian formulir dikelompokkan per LANGKAH, urut seperti formulirnya.
         *
         * Tanpa ini, tiga puluh kotak label-nilai berderet tanpa jeda dalam
         * urutan penyimpanan — urutan yang tidak pernah dijanjikan sama dengan
         * urutan pertanyaan. Yang hilang bukan cuma kerapian: "Kesesuaian Data:
         * Sesuai" hanya punya arti di sebelah nama dan kontak yang divalidasinya.
         *
         * Kunci yang tidak ada di skema TIDAK dibuang — ia masuk kelompok
         * "Isian Lain" di akhir. Menyembunyikannya berarti jawaban yang pernah
         * diberikan kandidat lenyap dari layarnya tanpa satu pun tanda.
         */
        kelompokIsian(f) {
            const peta = this.petaSkema(f);
            const kel = new Map();

            (f.jawaban || []).forEach((j) => {
                const info = peta.grup[j.key] || peta.bagian[j.key] || null;
                const kunci = info ? `${info.urutan}|${info.judul}` : 'zz|lain';

                if (!kel.has(kunci)) {
                    kel.set(kunci, {
                        judul: info?.judul || 'Isian Lain',
                        ikon: info?.ikon || 'bi-list-ul',
                        urutan: info ? info.urutan : 9999,
                        items: [],
                    });
                }
                kel.get(kunci).items.push({ isian: j, posisi: info?.posisi ?? 9999 });
            });

            return [...kel.values()]
                .sort((a, b) => a.urutan - b.urutan)
                .map((g) => ({
                    ...g,
                    items: g.items.sort((a, b) => a.posisi - b.posisi).map((x) => x.isian),
                }));
        },
        /**
         * Isian ini memang berupa berkas?
         *
         * Dinilai dari POLA KUNCI juga, bukan cuma dari ada-tidaknya berkas
         * terunggah — kalau hanya dari berkasnya, dokumen yang BELUM diunggah
         * terbaca sebagai isian teks biasa lalu tampil "—". Padahal justru
         * kekosongan itu yang perlu terbaca sebagai "Belum ada".
         */
        isianBerkas(f, j) {
            // Tipe dari SKEMA didahulukan. Tanpa itu, pertanyaan dokumen yang
            // BELUM diunggah tidak dikenali sebagai dokumen sama sekali — ia
            // terbaca sebagai isian teks kosong dan tampil "—", padahal justru
            // kekosongan itulah yang perlu terbaca "Belum ada".
            if (['file', 'foto', 'signature'].includes(this.petaSkema(f).tipe[j.key])) return true;

            return !!j.berkas || /^(dok|file|berkas|upload)_/i.test(j.key || '');
        },
        /** Jawaban panjang (alamat, uraian) memakai satu baris penuh. */
        isianPanjang(f, j) {
            if (this.isianBerkas(f, j)) return false;
            // Daftar butir selalu selebar penuh: lima butir di setengah kolom
            // terpotong satu per satu, dan yang tersisa cuma potongan kata.
            if (this.isianDaftar(j)) return true;
            // Isian BERULANG selalu memakai lebar penuh: satu baris riwayat
            // kerja saja sudah memuat perusahaan, jabatan, periode, dan uraian
            // — dijejalkan ke setengah kolom, semuanya terpotong.
            if (j.baris && j.baris.length) return true;

            return String(j.nilai ?? '').length > 60;
        },
        statusFormulir(f) {
            const t = this.tahap.find((x) => x.urutan === f.urutan);

            if (t && t.hasilTampil && t.hasil) {
                return t.hasil === 'LULUS'
                    ? { teks: 'Diterima', kelas: 'is-ok' }
                    : { teks: 'Tidak Lolos', kelas: 'is-err' };
            }
            if (t && t.menungguPengumuman) {
                return { teks: 'Menunggu Pengumuman', kelas: 'is-wait' };
            }
            if (!f.waktuKirim) {
                return { teks: 'Belum Dikirim', kelas: 'is-wait' };
            }

            return { teks: 'Menunggu Diproses', kelas: 'is-wait' };
        },
        /**
         * Kata status — dari server (Master Hasil Keputusan), bukan peta di sini.
         *
         * Peta literal sebelumnya berhenti di tiga status, sehingga kandidat
         * yang mengundurkan diri membaca `MENGUNDURKAN_DIRI` apa adanya di
         * layarnya sendiri.
         */
        stLabel(s) {
            if (s === this.lamaran.status && this.lamaran.statusLabel) {
                return this.lamaran.statusLabel;
            }

            return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak Lolos' }[s] || s;
        },
        stepStatus(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'Gugur' : 'Lulus';
            if (t.status === 'BERJALAN') return 'Berlangsung';
            return 'Menunggu';
        },
        // ── Aktivitas dalam satu tahap (mis. Psikotes 2 · DISC · Wawancara) ──
        // Aktivitas ONLINE punya token & jendela waktu; aktivitas manual tidak —
        // yang ditunggu di sana adalah kabar dari tim, bukan tombol mulai tes.
        subState(x) {
            if (x.selesai) return x.hasil === 'GAGAL' ? 'fail' : 'done';
            // TAHAP BERURUTAN — giliran aktivitas ini belum tiba. Didahulukan
            // di atas segalanya: tanpa ini, tes online yang terkunci tetap
            // terbaca "bisa dikerjakan" padahal tombolnya tidak akan bekerja.
            if (x.terkunci) return 'antre';
            if (!x.eksternal) return 'tim';
            if (x.ujian?.bisaAkses) return 'open';
            if (x.ujian?.terjadwal) return 'sched';
            return 'wait';
        },
        subLabel(x) {
            // Nama aktivitas yang ditunggu disebut bila ada — tapi hanya bila
            // server memang mengirimkannya. Aktivitas internal sengaja tidak
            // punya nama di sini, dan menyebutnya akan membocorkan langkah yang
            // justru disembunyikan.
            if (this.subState(x) === 'antre') {
                return x.menunggu ? `menunggu ${x.menunggu} selesai` : 'menunggu tahap sebelumnya';
            }

            return {
                done: 'selesai',
                fail: 'tidak lolos',
                open: 'bisa dikerjakan',
                sched: 'terjadwal',
                wait: 'menunggu jadwal',
                tim: 'diatur tim rekrutmen',
            }[this.subState(x)];
        },
        // Timeline node styling (design).
        // Lamaran yang sudah BERHENTI — apa pun sebabnya — tidak lagi punya
        // tahap "sedang berlangsung". Pemeriksaan lama hanya mengenal GUGUR,
        // sehingga pengunduran diri menyisakan titik berkedip di timeline.
        tlState(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'fail' : 'done';
            if (t.status === 'BERJALAN') {
                if (!this.lamaranBerhenti) return 'current';

                return this.stKey === 'gugur' ? 'fail' : 'todo';
            }

            return 'todo';
        },
        tlTodo(t) {
            return t.status !== 'SELESAI' && t.status !== 'BERJALAN';
        },
        tlCol(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? '#ef4444' : '#10b981';
            if (t.status === 'BERJALAN') {
                if (!this.lamaranBerhenti) return '#f59e0b';

                return this.stKey === 'gugur' ? '#ef4444' : '#cbd2e0';
            }

            return '#cbd2e0';
        },
        tlNode(t) {
            return {
                borderColor: this.tlCol(t),
                animation: t.status === 'BERJALAN' && !this.lamaranBerhenti ? 'ldPulse 2s infinite' : 'none',
            };
        },
        tlTagStyle(t) {
            if (t.status === 'SELESAI')
                return t.hasil === 'GUGUR'
                    ? { background: 'rgba(239,68,68,.1)', color: '#dc2626' }
                    : { background: 'rgba(16,185,129,.12)', color: '#059669' };
            if (t.status === 'BERJALAN') return { background: 'rgba(245,158,11,.14)', color: '#b45309' };
            return { background: '#eef0f7', color: '#94a3b8' };
        },
        /** "Seleksi Administrasi → Psikotes" bila lolos ke tahap berikutnya; selain itu nama tahapnya. */
        judulCatatan(c) {
            return c.hasil === 'LULUS' && c.berikut ? `${c.label} → ${c.berikut}` : c.label;
        },
        tautkan,
        /** "2 hari 5 jam" / "3 jam" / "12 menit" — sisa waktu menuju suatu saat. */
        durasiTeks(ms) {
            if (!ms || ms <= 0) return 'habis';
            const jam = Math.floor(ms / 3600000);
            if (jam < 1) return `${Math.max(1, Math.floor(ms / 60000))} menit`;
            if (jam < 24) return `${jam} jam`;
            const hari = Math.floor(jam / 24);
            const sisa = jam % 24;

            return sisa ? `${hari} hari ${sisa} jam` : `${hari} hari`;
        },
        /** Dari kartu keputusan ke catatan di kartu tahap aktif. */
        keCatatan() {
            document.getElementById('ld-ctim')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },
        fmtD(iso, short) {
            if (!iso) return '';
            const d = new Date(String(iso).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '';
            return d.toLocaleDateString(
                'id-ID',
                short ? { day: '2-digit', month: 'short' } : { day: '2-digit', month: 'short', year: 'numeric' },
            );
        },
        ukuran(b) {
            if (!b) return '';
            return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
        },
        /**
         * Buka berkas di modal halaman — gambar maupun PDF.
         *
         * Dulu PDF dilempar ke tab baru, jadi ada dua gaya pratinjau untuk hal
         * yang sama. Modalnya sudah sanggup menyematkan PDF lewat iframe, jadi
         * tidak ada lagi alasan memisahkannya.
         */
        /**
         * Klik foto verifikasi di kartu data kandidat.
         *
         * Template memanggil ini sejak lama, tapi metodenya TIDAK PERNAH ADA —
         * setiap klik hanya melempar "previewBerkas is not a function" di
         * console dan tidak terjadi apa-apa. Diarahkan ke bukaDok() supaya
         * fotonya memakai modal yang sama dengan berkas lain.
         */
        previewBerkas(b) {
            if (!b?.url) return;
            this.bukaDok({ ...b, isImage: b.isImage ?? true });
        },
        /** Muat berkas yang sudah diunggah untuk tiap aktivitas yang memintanya. */
        async muatBerkasTes() {
            for (const u of this.unggahAktivitas) {
                try {
                    const res = await axios.get(`/kandidat/lamaran/tes/${u.id}/berkas`);
                    this.berkasTes = { ...this.berkasTes, [u.key]: res.data.result || [] };
                } catch (e) {
                    // Diam: daftar kosong lebih baik daripada halaman gagal muat.
                }
            }
        },
        /**
         * Kirim berkas ke server.
         *
         * MENGUNGGAH ≠ MENGIRIM. Dua nama yang berbeda, karena keduanya memang
         * dua perbuatan berbeda: yang satu menaruh satu berkas, yang satu
         * menyatakan seluruhnya sudah lengkap. Sempat sama-sama bernama
         * `kirimBerkasTes`, dan karena kunci objek yang kembar diam-diam saling
         * menimpa di JavaScript, yang menang justru pernyataan lengkapnya —
         * sehingga memilih berkas TIDAK MELAKUKAN APA PUN, tanpa satu pesan
         * galat pun. Nama yang berbeda membuat kekeliruan itu mustahil terulang.
         *
         * Format & ukuran diperiksa DI SINI juga supaya kandidat dapat kabar
         * seketika — tapi server tetap memeriksanya sendiri, karena pemeriksaan
         * di layar bisa dilewati.
         */
        async unggahBerkasTes(file, u) {
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            if (!u.format.includes(ext)) {
                this.notice(`"${file.name}" ditolak — hanya menerima ${u.format.join(', ').toUpperCase()}.`, true);

                return;
            }
            if (file.size > u.maksMb * 1024 * 1024) {
                this.notice(`"${file.name}" melebihi ${u.maksMb} MB.`, true);

                return;
            }

            this.unggahDi = u.key;
            const fd = new FormData();
            fd.append('berkas', file);
            try {
                const res = await axios.post(`/kandidat/lamaran/tes/${u.id}/berkas`, fd);
                const baru = res.data.result;
                this.berkasTes = { ...this.berkasTes, [u.key]: [...(this.berkasTes[u.key] || []), baru] };
                this.notice('Berkas terunggah.');
            } catch (e) {
                const err = e.response?.data?.errors;
                this.notice(err ? Object.values(err).flat()[0] : (e.response?.data?.message || 'Berkas gagal diunggah.'), true);
            } finally {
                this.unggahDi = null;
            }
        },
        async hapusBerkasTes(b, u) {
            try {
                await axios.delete(`/kandidat/lamaran/tes/berkas/${b.id}`);
                this.berkasTes = { ...this.berkasTes, [u.key]: (this.berkasTes[u.key] || []).filter((x) => x.id !== b.id) };
                this.notice('Berkas dihapus.');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus berkas.', true);
            }
        },

        /**
         * Nyatakan berkas untuk satu aktivitas SUDAH LENGKAP.
         *
         * Halaman disegarkan sesudahnya, bukan sekadar menandai di layar: yang
         * berubah bukan cuma tombol ini — keadaan tahap di atas ikut berpindah
         * dari "ada yang perlu kamu kerjakan" jadi "menunggu penilaian". Kalau
         * hanya tombolnya yang berubah, dua bagian layar yang sama-sama terlihat
         * akan saling bertentangan.
         */
        async kirimBerkasTes(u) {
            if (this.kirimDi || !(this.berkasTes[u.key] || []).length) {
                return;
            }

            this.kirimDi = u.key;
            try {
                const { data } = await axios.patch(`/kandidat/lamaran/tes/${u.id}/berkas/kirim`);
                this.notice(data?.message || 'Berkas terkirim.');
                router.reload({ preserveScroll: true });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Berkas gagal dikirim.', true);
            } finally {
                this.kirimDi = null;
            }
        },
        bukaDok(b) {
            this.lightbox = { ...b, pdf: !b.isImage };
            this.mulaiMuat();
        },
        /**
         * Mulai memuat pratinjau, dengan BATAS WAKTU.
         *
         * URL berkas mengalihkan ke signed URL GCS. Pada PDF di dalam iframe,
         * peristiwa `load` tidak selalu terpicu dan `error` hampir tidak pernah,
         * sehingga spinner bisa berputar selamanya padahal berkasnya sudah
         * tampil. Batas 10 detik membuat keadaan menggantung itu mustahil.
         */
        mulaiMuat() {
            this.lbLoading = true;
            this.lbError = false;
            if (this.lbTimer) clearTimeout(this.lbTimer);
            this.lbTimer = setTimeout(() => {
                this.lbLoading = false;
            }, 10000);
        },
        selesaiMuat(gagal = false) {
            if (this.lbTimer) clearTimeout(this.lbTimer);
            this.lbLoading = false;
            this.lbError = gagal;
        },
        // ── Gerbang waktu tes (fungsi asli, dipertahankan) ──
        /**
         * LENCANA satu aktivitas — diturunkan dari aktivitas itu SENDIRI.
         *
         * Tidak satu pun cabang di sini melihat aktivitas lain, dan itulah
         * intinya: FGD yang sudah dijadwalkan berbunyi "Sudah dijadwalkan"
         * walau psikotes di sebelahnya belum punya sesi sama sekali.
         *
         * `nada` dipakai dua kali — mewarnai bingkai bloknya, dan mengurutkan
         * kepentingan di `aktivitasSorot`. Kosakatanya sengaja sama dengan
         * `keadaanTahap` supaya keduanya tidak bisa menyimpang diam-diam.
         */
        keadaanAktivitas(a) {
            if (a.selesai) return { nada: 'selesai', label: 'Selesai' };
            if (a.terkunci) return { nada: 'kunci', label: 'Belum gilirannya' };

            if (a.eksternal) {
                if (!a.ujian?.terjadwal) return { nada: 'tunggu', label: 'Menunggu dijadwalkan' };
                if (this.sudahSelesai(a)) return { nada: 'proses', label: 'Sudah dikerjakan' };
                if (this.tesDitunggu && this.tesDitunggu.id === a.id) return { nada: 'kirim', label: 'Jawaban terkirim' };
                if (this.belumMulai(a)) return { nada: 'jadwal', label: 'Belum dibuka' };
                if (this.sudahLewat(a)) return { nada: 'lewat', label: 'Waktu berakhir' };

                return { nada: 'aksi', label: 'Bisa dikerjakan' };
            }

            // Aktivitas yang dikerjakan tim. Yang menuntut kandidat cuma satu:
            // berkas yang belum ia nyatakan lengkap — DAN hanya bila kotak
            // unggahnya memang sudah boleh tampil. Lencana "Menunggu berkasmu"
            // di atas blok yang isinya "menunggu jadwal" menuntut kandidat
            // mengerjakan sesuatu yang halaman itu sendiri belum sediakan.
            // Batas aktivitas berbatas waktu (MCU mandiri) sudah lewat — itulah
            // yang paling perlu terbaca, di atas "menunggu berkasmu".
            if (a.unggah?.tertutup) return { nada: 'lewat', label: 'Unggah ditutup' };
            if (a.jadwal?.lewat) return { nada: 'lewat', label: 'Lewat batas' };
            if (this.unggahSiap(a) && ! a.unggah.terkirim) return { nada: 'aksi', label: 'Menunggu berkasmu' };
            if (a.unggah?.terkirim) return { nada: 'proses', label: 'Berkas terkirim' };
            // DITUNDA tim — jadwalnya dikosongkan, penggantinya menyusul.
            // Bukan "Menunggu jadwal": kandidat perlu tahu jadwalnya pernah ada
            // dan kenapa sekarang tidak (kartunya ada di bawah lencana ini).
            if (!a.jadwal && a.konfirmasi?.status === 'DITUNDA') return { nada: 'tunda', label: 'Ditunda' };
            if (a.jadwal) return { nada: 'jadwal', label: 'Sudah dijadwalkan' };

            // ══ TIPE YANG MEMANG TIDAK PERNAH DIJADWALKAN ══
            //
            // Formulir, screening berkas, background check, reference check,
            // phone screening: Master_Tipe_Tahap.Flag_Jadwal = 'T'. Tak satu
            // pun akan pernah punya tanggal, jadi lencana "Menunggu jadwal"
            // menjanjikan kabar yang tidak akan datang — dan pada aktivitas
            // BERFORMULIR ia sekaligus bertolak belakang dengan isi bloknya
            // sendiri, yang tepat di bawahnya berbunyi "Lengkapi formulir yang
            // diminta di bawah". Satu blok, dua cerita.
            //
            // Yang berformulir menunggu KANDIDAT — selama isiannya belum masuk
            // (`tugas` masih dikirim server untuk tahap ini). Sisanya memang
            // sedang dikerjakan tim, dan itulah yang disebut apa adanya.
            if (! a.perluJadwal) {
                return a.berformulir && this.isianTertunda
                    ? { nada: 'aksi', label: 'Menunggu isianmu' }
                    : { nada: 'tinjau', label: 'Sedang diproses tim' };
            }

            return { nada: 'tunggu', label: 'Menunggu jadwal' };
        },
        /**
         * Kandidat menjawab konfirmasi LANGSUNG di kartu jadwal — muat ulang
         * data lamaran di tempat (tanpa pindah halaman) supaya status, aturan
         * ubah, dan lencananya ikut berubah. Pesan suksesnya dipegang kartu.
         */
        muatUlangKonfirmasi() {
            router.reload({ preserveScroll: true });
        },
        belumMulai(t) {
            return t.ujian?.waktuMulai ? this.now < new Date(t.ujian.waktuMulai).getTime() : false;
        },
        sudahLewat(t) {
            return t.ujian?.waktuSelesai ? this.now > new Date(t.ujian.waktuSelesai).getTime() : false;
        },
        sudahSelesai(t) {
            return t.ujian?.statusPengerjaan === 'selesai';
        },
        /**
         * Aktivitas ujian ini SUDAH BERAKHIR — apa pun caranya.
         *
         * Ada dua cara, dan keduanya harus dihitung:
         *   `ujian.statusPengerjaan === 'selesai'` kandidat mengerjakannya;
         *   `selesai`                             tim menutupnya (dicatat
         *                                         manual, tidak hadir, atau
         *                                         tahapnya ditutup lebih dulu).
         *
         * Dulu hanya yang pertama diperiksa. Aktivitas online yang ditutup tim
         * tanpa pernah dikerjakan karenanya tetap memamerkan TOKEN, OTP, dan
         * tombol "Mulai Tes Sekarang" — untuk sesi yang sudah tidak ada.
         */
        tesTuntas(t) {
            return !!t.selesai || this.sudahSelesai(t);
        },
        /**
         * BLOK MANA yang mengisi badan satu aktivitas — SATU keputusan, di
         * satu tempat, dengan urutan yang tidak bisa terbalik:
         *
         *   selesai  sudah berakhir, apa pun caranya — tak ada yang tersisa
         *            untuk ia kerjakan, dan tak ada yang boleh menyuruhnya
         *            menunggu apa pun selain hasil;
         *   jadwal   ujian daring yang sesinya memang belum dibuat tim;
         *   jalan    selebihnya — kredensial, jadwal, berkas, tombol tes.
         *
         * DIPUTUSKAN DI SINI, BUKAN DI RANGKAIAN v-if DI TEMPLATE. Bug yang
         * melahirkannya persis begitu: cabang "sudah berakhir" berdiri di
         * tengah rangkaian, jadi aktivitas yang sudah dikerjakan lalu
         * ditutup tim melewatinya dan jatuh ke cadangan paling bawah —
         * yang menayangkan Pesan_Kandidat dari master, "Tim rekrutmen
         * sedang menyiapkan jadwal ujianmu", tepat di bawah lencana
         * "Selesai" miliknya sendiri. Kandidat dikembalikan ke keadaan
         * sebelum ia mengerjakan tesnya, oleh halaman yang di baris atas
         * baru saja berterima kasih karena ia sudah mengerjakannya.
         */
        blokAktivitas(a) {
            if (this.tesTuntas(a)) return 'selesai';
            if (a.eksternal && !a.ujian?.terjadwal) return 'jadwal';

            return 'jalan';
        },
        /**
         * JUDUL blok "sudah berakhir". Dua cara berakhir, dua kalimat —
         * dan keduanya harus jujur soal SIAPA yang mengakhirinya.
         *
         * "Sudah kamu kerjakan" untuk aktivitas yang ditutup tim tanpa
         * pernah ia sentuh (dicatat manual, tidak hadir, atau tahapnya
         * ditutup lebih dulu) adalah klaim yang salah tentang dirinya —
         * dan kandidat yang tahu ia tidak mengerjakannya jadi curiga
         * catatan kami tertukar dengan orang lain.
         */
        /**
         * KOTAK UNGGAH BOLEH TAMPIL?
         *
         * Tes offline yang belum dijadwalkan tetap memamerkan "Seret berkas
         * ke sini" beserta peringatan merah "Belum ada berkas — aktivitas ini
         * belum bisa dianggap selesai". Kandidat diminta mengunggah JAWABAN
         * atas tes yang waktu dan tempatnya saja belum ia ketahui: ia tidak
         * punya apa pun untuk diunggah, dan yang ia baca adalah tuduhan bahwa
         * ia belum mengerjakan bagiannya. FGD dan wawancara di tahap yang sama
         * — sama-sama belum dijadwalkan — dengan tenang berbunyi "menunggu
         * jadwal". Tiga aktivitas sederajat, tiga cerita berbeda.
         *
         * `perluJadwal` datang dari Master_Tipe_Tahap.Flag_Jadwal, jadi tipe
         * baru yang kelak ditandai "dijadwalkan" ikut terbaca di sini tanpa
         * menyentuh berkas ini. Yang TIDAK dijadwalkan (formulir, dokumen)
         * tetap meminta berkasnya sejak awal — memang tidak ada yang ditunggu.
         *
         * Yang sudah terkirim atau sudah punya berkas SELALU tampil: apa pun
         * yang terjadi pada jadwalnya kemudian, bukti kiriman kandidat tidak
         * boleh lenyap dari halamannya sendiri.
         */
        unggahSiap(a) {
            if (! a.unggah) return false;
            if (a.unggah.terkirim || (this.berkasTes[a.urutan] || []).length) return true;

            return ! a.perluJadwal || !! a.jadwal;
        },
        judulSelesai(a) {
            // Ditutup karena kandidat sendiri menyatakan tidak melanjutkan — bukan
            // "sudah selesai" biasa, dan jangan menjanjikan langkah berikutnya.
            if (this.mundurSendiri(a)) return 'Kamu menyatakan tidak melanjutkan seleksi';

            return this.sudahSelesai(a) ? 'Tes sudah kamu kerjakan — tidak dapat diulang' : 'Aktivitas ini sudah selesai';
        },
        pesanSelesai(a) {
            if (this.mundurSendiri(a)) {
                return `${a.konfirmasi.kalimat || 'Pernyataanmu sudah kami terima.'} Tim rekrutmen akan menutup lamaranmu — tidak ada lagi yang perlu kamu lakukan di sini.`;
            }

            return this.pesanSetelahTes;
        },
        /** Aktivitas ditutup (Tidak Hadir) karena kandidat menyatakan tidak melanjutkan. */
        mundurSendiri(a) {
            return a.konfirmasi?.status === 'MUNDUR';
        },
        bisaAkses(t) {
            const u = t.ujian;
            if (!u || !u.link || this.sudahSelesai(t)) return false;
            // Tahap BERURUTAN: tesnya boleh saja sudah punya token dan jendela
            // waktunya terbuka, tapi gilirannya belum tiba. Membiarkan tombolnya
            // hidup berarti kandidat mengerjakan tes di luar urutan yang sudah
            // disusun — dan urutan itu tidak bisa dikembalikan setelahnya.
            if (t.terkunci) return false;

            return !this.belumMulai(t) && !this.sudahLewat(t);
        },
        hitungMundur(t) {
            if (!t.ujian?.waktuMulai) return '';
            const selisih = new Date(t.ujian.waktuMulai).getTime() - this.now;
            if (selisih <= 0) return '';
            const menit = Math.floor(selisih / 60000);
            if (menit < 60) return `${menit} menit lagi`;
            const jam = Math.floor(menit / 60);
            if (jam < 24) return `${jam} jam lagi`;
            return `${Math.floor(jam / 24)} hari lagi`;
        },
        /**
         * Masuk ruang ujian DI TAB YANG SAMA, setelah jeda singkat.
         *
         * Tab baru bermasalah pada dua hal sekaligus: pemblokir popup kerap
         * menahannya tanpa kabar (kandidat mengira tombolnya rusak), dan begitu
         * ujian selesai, tab CAT tidak punya jalan pulang — kandidat menutupnya
         * lalu menatap halaman lamaran yang isinya belum berubah.
         *
         * Jeda 3 detik bukan hiasan: berpindah domain tanpa peringatan terbaca
         * seperti halaman yang tiba-tiba hilang, dan di layar inilah kandidat
         * sempat membaca bahwa ia akan dipindahkan.
         */
        bukaTes(t) {
            if (!this.bisaAkses(t) || this.menujuUjian) {
                return;
            }

            // TITIPKAN JEJAK sebelum pergi. Saat kandidat diantar pulang oleh
            // CAT, hasil tesnya belum tentu sudah sampai ke sini — callback-nya
            // antrean, bukan seketika. Tanpa jejak ini halaman menyambutnya
            // dengan tombol "Mulai Tes Sekarang" untuk tes yang baru saja ia
            // kerjakan: pesan paling menakutkan yang bisa dibaca seseorang yang
            // baru selesai ujian.
            //
            // sessionStorage, bukan localStorage: jejaknya milik tab ini saja
            // dan ikut hilang saat tabnya ditutup — tidak menempel di peramban
            // bersama orang lain yang memakai komputer yang sama.
            this.tandaiPergiTes(t);

            this.menujuUjian = true;
            this.menujuId = t.id;
            this.hitungPindah = 3;

            this.timerPindah = setInterval(() => {
                this.hitungPindah--;
                if (this.hitungPindah > 0) {
                    return;
                }

                clearInterval(this.timerPindah);
                // `replace`, bukan `href`: menekan Kembali dari ruang ujian tak
                // boleh melempar kandidat ke halaman perantara ini lalu
                // memindahkannya lagi secara otomatis.
                window.location.replace(t.ujian.link);
            }, 1000);
        },
        batalKeUjian() {
            clearInterval(this.timerPindah);
            this.menujuUjian = false;
            this.menujuId = null;
            // Batal berarti tidak jadi pergi — jejaknya harus ikut hilang,
            // kalau tidak halaman ini menyambut kepulangan yang tak pernah ada.
            this.lupakanPergiTes();
        },

        // ── Jejak "sedang di ruang ujian" ────────────────────────────────────
        kunciPergiTes() {
            return 'wc_tes_pergi_' + (this.lamaran?.id || '');
        },
        tandaiPergiTes(t) {
            try {
                // `tahap` ikut dicatat: jejak ini hanya sah selama kandidat masih
                // berdiri di tahap yang sama. Lihat bacaPergiTes().
                sessionStorage.setItem(this.kunciPergiTes(), JSON.stringify({
                    id: t.id,
                    tahap: this.tahapAktif?.id || null,
                    at: Date.now(),
                }));
            } catch (e) {
                // Mode privat / storage penuh — fitur sambutan hilang, tapi
                // membuka tesnya TIDAK BOLEH ikut gagal karenanya.
            }
        },
        bacaPergiTes() {
            try {
                const isi = JSON.parse(sessionStorage.getItem(this.kunciPergiTes()) || 'null');
                if (!isi || !isi.id) return null;

                // TAHAPNYA SUDAH BERGERAK — jejaknya hangus.
                //
                // Jejak lama hanya bercerita tentang tes di tahap yang sudah
                // lewat. Dibiarkan hidup, ia bisa tersambung ke tes tahap
                // BERIKUTNYA yang belum tersentuh sama sekali, dan kandidat
                // disambut "jawabanmu sudah terkirim" untuk tes yang bahkan
                // belum ia buka. Jejak dibuang, bukan sekadar diabaikan, supaya
                // pemuatan berikutnya tidak mengulang pemeriksaan yang sama.
                if (isi.tahap && this.tahapAktif?.id && isi.tahap !== this.tahapAktif.id) {
                    this.lupakanPergiTes();

                    return null;
                }

                // Jejak basi dibuang.
                //
                // Batasnya 15 MENIT, bukan 12 jam seperti dulu. Yang diukur
                // bukan lamanya mengerjakan tes — jejak ini baru ditulis saat
                // kandidat MENINGGALKAN halaman menuju ruang ujian, dan yang
                // ditunggu sesudahnya cuma callback hasil, yang datangnya dalam
                // hitungan detik sampai beberapa menit.
                //
                // Dengan batas 12 jam, kandidat yang tesnya gagal terkirim akan
                // terus disambut "Jawabanmu sudah terkirim" sepanjang sisa hari
                // itu — melewati muat ulang dan logout-login, sebab
                // sessionStorage tidak ikut dibersihkan keduanya. Kartu tes yang
                // sebenarnya masih harus dikerjakan tertutup kabar yang keliru,
                // dan satu-satunya jalan keluarnya membersihkan data peramban.
                //
                // Lima belas menit jauh lebih panjang daripada jeda callback
                // yang wajar, tapi cukup pendek untuk tidak menyandera kandidat.
                if (Date.now() - (isi.at || 0) > 15 * 60 * 1000) {
                    this.lupakanPergiTes();

                    return null;
                }

                return isi;
            } catch (e) {
                return null;
            }
        },
        lupakanPergiTes() {
            this.pulangTes = null;
            this.hentikanPantauHasil();
            try {
                sessionStorage.removeItem(this.kunciPergiTes());
            } catch (e) {
                /* diabaikan — lihat tandaiPergiTes() */
            }
        },

        /**
         * Cadangan bila jejak sessionStorage tidak ada: penanda `?dari=tes`
         * yang dipasang di alamat pulang (lihat WcPenjadwalanJob).
         *
         * Jejak bisa hilang secara wajar — kandidat menutup tabnya di tengah
         * ujian lalu membuka tautan pulang dari email, peramban ponsel yang
         * membersihkan penyimpanan sesi saat berpindah domain, atau mode privat.
         *
         * Penanda alamat tidak menyebut aktivitas MANA, jadi ia hanya dipakai
         * bila jawabannya cuma satu: tepat satu tes daring yang belum selesai.
         * Bila ada dua, menebak berarti bisa menyembunyikan tes yang justru
         * masih harus dikerjakan — itu lebih buruk daripada tidak menyambut.
         *
         * ══ PENANDANYA HABIS SEKALI PAKAI ══
         *
         * `?dari=tes` berarti "aku BARU SAJA keluar dari ruang ujian" — sebuah
         * peristiwa, bukan keadaan. Dulu ia dibiarkan menempel di alamat, dan
         * itulah sumber laporan "tes tahap 5 disambut 'jawabanmu sudah
         * terkirim' padahal belum dikerjakan":
         *
         *   1. kandidat selesai tes tahap 3, CAT memulangkannya ke ?dari=tes
         *   2. halaman memantau hasil dengan router.reload() — yang MEMBAWA
         *      SERTA seluruh query, termasuk penanda ini
         *   3. hasil masuk, tahap bergerak ke 5 yang juga punya satu tes daring
         *   4. penanda yang sama terbaca lagi, syarat "tepat satu tes daring
         *      belum selesai" kini dipenuhi oleh tes tahap 5 — dan tes yang
         *      belum tersentuh itu disambut sebagai sudah dikerjakan.
         *
         * Karena itu penandanya dicabut dari alamat begitu dibaca. Dibaca sekali,
         * habis sekali; reload, tombol Kembali, dan tautan yang tersimpan di
         * riwayat peramban tidak bisa membangkitkannya lagi.
         */
        tebakPulangDariAlamat() {
            try {
                const alamat = new URL(window.location.href);
                if (alamat.searchParams.get('dari') !== 'tes') {
                    return null;
                }

                // replaceState, bukan push: mencabut penanda tidak boleh
                // menambah langkah baru di riwayat peramban — tombol Kembali
                // kandidat harus tetap membawanya ke tempat asalnya.
                alamat.searchParams.delete('dari');
                window.history.replaceState(window.history.state, '', alamat.pathname + alamat.search + alamat.hash);
            } catch (e) {
                return null;
            }

            const daring = (this.aktivitas || []).filter((x) => x.ujian && !x.selesai && !this.sudahSelesai(x));

            return daring.length === 1
                ? { id: daring[0].id, tahap: this.tahapAktif?.id || null, at: Date.now() }
                : null;
        },

        /**
         * Sambut kepulangan dari ruang ujian, lalu tunggu hasilnya masuk.
         *
         * Hasil tes datang lewat callback CAT yang diproses antrean, jadi ada
         * jeda antara "kandidat sampai di halaman ini" dan "tahapnya berubah
         * jadi selesai". Halaman menyegarkan dirinya sendiri selama jeda itu,
         * supaya posisinya berubah di depan mata kandidat — bukan menuntutnya
         * menekan muat-ulang untuk sesuatu yang memang sedang berjalan.
         */
        sambutPulangTes() {
            // PENANDA ALAMAT SELALU DIHABISKAN LEBIH DULU — meski nanti tidak
            // dipakai.
            //
            // Dulu barisnya `bacaPergiTes() || tebakPulangDariAlamat()`. Pada
            // jalur yang normal jejak sessionStorage-lah yang menang, sehingga
            // tebakan alamat TIDAK PERNAH dipanggil — dan karena hanya ia yang
            // mencabut `?dari=tes` dari alamat, penandanya tertinggal di sana.
            //
            // Penanda yang tertinggal itu menunggu. Ia ikut tersimpan di riwayat
            // peramban, ikut terbawa router.reload(), dan hidup lagi pada
            // pemuatan berikutnya. Saat itu tiba, tahapnya sudah bergerak: jejak
            // sessionStorage-nya hangus (memang begitu aturannya), tebakan
            // alamat akhirnya dijalankan, lalu ia memungut satu-satunya tes
            // daring yang belum selesai di tahap yang SEKARANG — tes yang belum
            // pernah dibuka kandidat. Hasilnya kartu tes tertutup keterangan
            // "Jawabanmu sudah terkirim", dan kandidat mengira tak ada lagi yang
            // perlu ia kerjakan.
            //
            // Dipanggil lebih dulu, penandanya habis pada pemuatan pertama —
            // pemuatan mana pun sesudahnya tidak punya apa-apa lagi untuk
            // ditebak. Hasil tebakannya tetap dipakai sebagai cadangan, untuk
            // kandidat yang sessionStorage-nya tidak tersedia (mode privat) atau
            // diantar pulang ke tab lain.
            const tebakan = this.tebakPulangDariAlamat();
            const jejak = this.bacaPergiTes() || tebakan;
            if (!jejak) {
                return;
            }

            this.pulangTes = jejak;

            // Sudah tercatat selesai sebelum ia sampai — tidak ada yang perlu
            // ditunggu, dan sambutannya tidak perlu ditampilkan sama sekali.
            if (!this.tesDitunggu) {
                this.lupakanPergiTes();

                return;
            }

            this.pulangCek = 0;
            this.pulangTimer = setInterval(() => {
                if (!this.tesDitunggu) {
                    this.lupakanPergiTes();

                    return;
                }

                // Berhenti setelah ~3 menit. Callback yang belum juga sampai
                // sesudah itu bukan lagi soal jeda antrean, dan menyegarkan
                // halaman tanpa akhir hanya membebani server tanpa mengubah
                // apa pun.
                //
                // JEJAKNYA IKUT DIBUANG, bukan cuma pemeriksaannya dihentikan.
                //
                // Dulu keterangan "Jawabanmu sudah terkirim" dibiarkan tetap
                // tampil sesudah pemantauan berhenti. Niatnya menenangkan, tapi
                // akibatnya kebalikannya: jejaknya hidup 12 jam di
                // sessionStorage, jadi keterangan itu bertahan melewati muat
                // ulang, melewati logout-login, dan menutupi kartu tes yang
                // SEBENARNYA masih harus dikerjakan — sampai kandidat
                // membersihkan data peramban sendiri.
                //
                // Sesudah tiga menit, yang jujur bukan "hasilnya sedang
                // diterima" melainkan keadaan tes yang sebenarnya. Jejak dibuang
                // supaya kartunya kembali bercerita apa adanya.
                if (++this.pulangCek > 12) {
                    this.lupakanPergiTes();

                    return;
                }

                router.reload({ preserveScroll: true });
            }, 15000);
        },
        hentikanPantauHasil() {
            if (this.pulangTimer) {
                clearInterval(this.pulangTimer);
                this.pulangTimer = null;
            }
        },
        fmtWaktu(iso) {
            if (!iso) return '—';
            return new Date(iso).toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        fmtTanggal(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));
            return Number.isNaN(d.getTime())
                ? '—'
                : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        onBerkas(e) {
            if (e.galat) {
                this.notice(e.galat, true);
                return;
            }
            // Kunci KOMPOSIT. Sebelumnya `e.bagian` dan `e.baris` dibuang di sini
            // padahal BagianRenderer sudah mengirimnya — dan itulah lapis pertama
            // yang membuat sertifikat baris kedua menimpa yang pertama, sejak di
            // memori tab sebelum satu byte pun naik ke server.
            const kunci = kunciBerkas(e.bagian, e.baris, e.field.key);

            // Pakai lightbox yang SAMA dengan pratinjau berkas lain di halaman
            // ini, supaya kandidat tidak menemui dua gaya pratinjau berbeda.
            if (e.lihat) {
                this.lbLoading = true;
                this.lbError = false;
                this.lightbox = { ...e.lihat, field: kunci, pdf: !e.lihat.gambar };
                return;
            }
            // Dihapus di kartu pratinjau -> berkasnya harus ikut dibuang dari
            // kumpulan yang akan dikirim, bukan hanya hilang dari layar.
            if (e.hapus) {
                delete this.berkas[kunci];
                delete this.berkasDraf[kunci];
                delete this.berkasMeta[kunci];
                delete this.berkasGagal[kunci];
                this.notice('Berkas dihapus.');
                return;
            }
            if (e.file) {
                this.berkas[kunci] = e.file;
                this.berkasMeta[kunci] = { bagian: e.bagian ?? null, baris: e.baris ?? null, field: e.field.key };
                delete this.berkasGagal[kunci];
                this.unggahDraf(kunci, e.file, e.field.key, e.bagian, e.baris);
            }
        },
        /**
         * Baris berulang dihapus -> berkasnya ikut dibuang di server, dan indeks
         * berkas di atasnya digeser turun.
         *
         * Penggeserannya SENGAJA dikerjakan server, lalu daftarnya ditarik ulang.
         * Menghitungnya di sini berarti dua sumber kebenaran untuk satu urutan.
         */
        async onHapusBaris(bagian, baris) {
            // Salinan lokal ikut dibuang lebih dulu supaya kartu berkasnya tidak
            // sempat berkedip dengan isi baris yang sudah tidak ada.
            const awalan = `${bagian}[`;
            Object.keys(this.berkas)
                .filter((k) => k.startsWith(awalan))
                .forEach((k) => delete this.berkas[k]);

            if (!this.tugas) return;
            try {
                await axios.delete(`/kandidat/lamaran/tahap/${this.tugas.tahapId}/draf/berkas`, {
                    data: { bagian, baris },
                });
            } catch (e) {
                this.notice('Berkas baris gagal dibuang di server. Muat ulang halaman bila tautannya kacau.', true);
            }
            await this.muatBerkasDraf();
        },
        /**
         * Kirim formulir tahap — HANYA bila setiap berkas yang disebut jawaban
         * benar-benar sudah ada di server.
         *
         * Nama berkas masuk ke jawaban begitu dipilih, sedangkan isinya naik
         * lewat unggahan terpisah. Dulu Kirim tidak menunggu unggahan itu dan
         * tidak memeriksa hasilnya: unggahan yang gagal atau masih berjalan
         * berakhir sebagai nama berkas tanpa berkas (CV, KTP, sertifikat yang
         * "hilang" di produksi). Server kini menolak kiriman seperti itu; di
         * sini ia dicegah lebih dulu, dan unggahan yang tertinggal dicoba ulang.
         */
        async kirim(nilai) {
            if (this.mengirim || !this.tugas) return;
            this.mengirim = true;
            try {
                // 1. Unggahan yang masih berjalan ditunggu sampai tuntas.
                if (Object.keys(this.unggahan).length) {
                    this.notice('Menunggu unggahan berkas selesai…');
                    await Promise.allSettled(Object.values(this.unggahan));
                }

                // 2. Nama berkas yang belum punya salinan di server: dicoba unggah
                //    ulang sekali selagi berkasnya masih dipegang tab ini.
                let kurang = this.berkasBelumTersimpan(nilai);
                const ulang = kurang.filter((k) => this.berkas[kunciBerkas(k.bagian, k.baris, k.field)]);
                if (ulang.length) {
                    await Promise.allSettled(
                        ulang.map((k) => {
                            const kn = kunciBerkas(k.bagian, k.baris, k.field);
                            return this.unggahDraf(kn, this.berkas[kn], k.field, k.bagian, k.baris);
                        }),
                    );
                    kurang = this.berkasBelumTersimpan(nilai);
                }
                if (kurang.length) {
                    this.tandaiBerkasKurang(kurang);
                    return;
                }

                const res = await axios.post(
                    `/api/v1/lamaran/tahap/${this.tugas.tahapId}/kirim`,
                    { jawaban: nilai },
                    CFG,
                );
                this.notice(res.data?.message || 'Formulir terkirim.');
                setTimeout(() => router.reload(), 800);
            } catch (e) {
                // Server menolak karena berkas yang disebut jawaban tidak ada:
                // kosongkan tepat isian itu supaya kotak unggahnya muncul lagi.
                const kurangServer = e.response?.data?.result?.berkasKurang;
                if (e.response?.status === 422 && Array.isArray(kurangServer) && kurangServer.length) {
                    await this.muatBerkasDraf();
                    this.tandaiBerkasKurang(kurangServer, e.response.data.message);
                    return;
                }
                this.notice(e.response?.data?.message || 'Gagal mengirim formulir.', true);
                // 409 = sudah terkirim / tahap tertutup: yang tepat memuat ulang.
                if (e.response?.status === 409) setTimeout(() => router.reload(), 1500);
            } finally {
                this.mengirim = false;
            }
        },
        /**
         * Berkas yang seharusnya ada tapi belum punya salinan di server — aturan
         * yang SAMA dengan penolakan server (berkasKurang ↔ BerkasFormulir).
         */
        berkasBelumTersimpan(jawaban) {
            const ada = (bagian, baris, field) => !!this.berkasDraf[kunciBerkas(bagian, baris, field)];
            if (this.tugas?.schema) {
                return berkasKurang(normalisasiSkema(this.tugas.schema), jawaban || {}, ada);
            }

            // Formulir komponen lama (tanpa skema): yang bisa diperiksa hanya
            // berkas yang dipilih di tab ini.
            return Object.entries(this.berkasMeta)
                .filter(([k]) => !this.berkasDraf[k])
                .map(([, m]) => ({ ...m, label: m.field, wajib: false, nama: '' }));
        },
        /**
         * Tandai isian yang berkasnya tidak ada di server: nama berkasnya
         * dikosongkan (kotak unggah muncul lagi, validasi "wajib diisi" ikut
         * menyala), dan pesannya menetap di bawah kotak itu.
         */
        tandaiBerkasKurang(daftar, pesan = null) {
            const gagal = { ...this.berkasGagal };
            daftar.forEach((k) => {
                const bagian = k.bagian ?? null;
                const baris = k.baris ?? null;
                const kn = kunciBerkas(bagian, baris, k.field);
                gagal[kn] = 'Belum tersimpan di server — pilih ulang berkasnya.';
                delete this.berkas[kn];
                delete this.berkasMeta[kn];
                this.aturIsianBerkas(bagian, baris, k.field, '');
            });
            this.berkasGagal = gagal;

            const label = daftar.map((k) => k.label).join(', ');
            this.notice(
                pesan || `Berkas berikut belum tersimpan di server: ${label}. Unggah ulang, lalu kirim kembali.`,
                true,
                9000,
            );
        },
        /** Tulis nilai sebuah isian berkas — di akar jawaban atau di baris bagian berulang. */
        aturIsianBerkas(bagian, baris, field, nilai) {
            if (bagian === null || bagian === undefined || baris === null || baris === undefined) {
                this.jawaban[field] = nilai;
                return;
            }
            const daftar = this.jawaban[bagian];
            if (Array.isArray(daftar) && daftar[baris] && typeof daftar[baris] === 'object') {
                daftar[baris][field] = nilai;
            }
        },
        /**
         * Muat simpanan sementara dari server lalu lanjutkan dari sana.
         *
         * Gagal memuat TIDAK memblokir pengisian: kandidat tetap bisa mulai dari
         * isian kosong. Draf adalah kenyamanan, bukan syarat.
         */
        async muatDraf() {
            if (!this.tugas) return;
            try {
                const { data } = await axios.get(`/kandidat/lamaran/tahap/${this.tugas.tahapId}/draf`);
                const d = data?.result?.draf;
                if (d) {
                    this.jawaban = { ...this.jawaban, ...(d.jawaban || {}) };
                    this.langkahAwal = d.langkah || 0;
                    this.berkasDraf = Object.fromEntries(
                        (d.berkas || []).map((b) => [kunciBerkas(b.bagian, b.baris, b.field), b]),
                    );
                    this.buangBerkasBasi();
                    if (d.disimpanAt) this.notice('Melanjutkan isian yang tersimpan sebelumnya.');
                }
            } catch (e) {
                // Diam saja — lihat alasan di atas.
            } finally {
                this.drafSiap = true;
            }
        },
        /**
         * Naikkan berkas ke simpanan sementara di server.
         *
         * Dilakukan begitu berkas dipilih, bukan ditunda sampai Kirim: tanpa ini
         * berkas hanya hidup di memori tab, sehingga refresh membuat kandidat
         * harus memilih ulang semuanya — dan pratinjaunya tidak bisa dibuka
         * karena tidak ada apa pun di server untuk ditandatangani URL-nya.
         */
        async unggahDraf(kunci, file, field, bagian = null, baris = null) {
            if (!this.tugas) return;
            const nomor = (this.nomorUnggah[kunci] || 0) + 1;
            this.nomorUnggah[kunci] = nomor;

            // Didaftarkan supaya Kirim bisa MENUNGGU-nya (lihat kirim()).
            const janji = this.naikkanDraf(kunci, file, field, bagian, baris, nomor);
            this.unggahan[kunci] = janji;
            try {
                await janji;
            } finally {
                if (this.unggahan[kunci] === janji) delete this.unggahan[kunci];
            }
        },
        async naikkanDraf(kunci, file, field, bagian, baris, nomor) {
            const fd = new FormData();
            fd.append('field', field);
            // Dikirim hanya bila memang berkas baris berulang — endpoint
            // memperlakukan ketiadaannya sebagai berkas biasa.
            if (bagian !== null && bagian !== undefined && baris !== null && baris !== undefined) {
                fd.append('bagian', bagian);
                fd.append('baris', String(baris));
            }
            fd.append('berkas', file);

            let galat = null;
            for (let coba = 0; coba < 2; coba++) {
                try {
                    const { data } = await axios.post(`/kandidat/lamaran/tahap/${this.tugas.tahapId}/draf/berkas`, fd);
                    // Sudah digantikan berkas yang lebih baru untuk isian yang sama.
                    if (this.nomorUnggah[kunci] !== nomor) return;

                    const r = data?.result;
                    if (r?.url) {
                        this.berkasDraf = { ...this.berkasDraf, [kunci]: r };
                    } else {
                        // Server menerima berkasnya tapi tidak mengembalikan URL.
                        // Tarik ulang daftarnya daripada membiarkan kartu tanpa tautan.
                        await this.muatBerkasDraf();
                    }
                    delete this.berkasGagal[kunci];
                    return;
                } catch (err) {
                    galat = err;
                    // Penolakan server (4xx: format, ukuran, formulir tertutup)
                    // tidak akan berubah bila diulang. Yang dicoba ulang hanya
                    // gangguan jaringan & galat server.
                    const st = err.response?.status;
                    if (st && st < 500) break;
                    await new Promise((selesai) => setTimeout(selesai, 1200));
                }
            }

            if (this.nomorUnggah[kunci] !== nomor) return;
            this.gagalUnggah(kunci, galat, file?.name);
        },
        /**
         * Unggahan GAGAL: isiannya dikembalikan ke keadaan terakhir yang benar-
         * benar tersimpan di server — berkas sebelumnya bila ada, kosong bila
         * belum pernah — dan pesannya menetap di bawah kotak unggah.
         *
         * Dulu cukup notifikasi 4 detik, sementara nama berkas yang gagal tetap
         * tercatat di jawaban dan pratinjau lokalnya tetap bisa dibuka: kartu
         * tampak beres, formulir terkirim, berkasnya tidak pernah ada.
         */
        gagalUnggah(kunci, err, nama) {
            const pesan =
                err?.response?.data?.message || 'Berkas gagal diunggah. Periksa koneksi Anda lalu pilih ulang berkasnya.';
            const meta = this.berkasMeta[kunci];
            const tersimpan = this.berkasDraf[kunci];

            delete this.berkas[kunci];
            if (!tersimpan) delete this.berkasMeta[kunci];
            if (meta) this.aturIsianBerkas(meta.bagian, meta.baris, meta.field, tersimpan?.nama || '');

            this.berkasGagal = {
                ...this.berkasGagal,
                [kunci]: tersimpan ? `${pesan} Berkas sebelumnya tetap dipakai.` : pesan,
            };
            this.notice(`${nama ? `"${nama}" ` : 'Berkas '}belum tersimpan: ${pesan}`, true, 9000);

            // 409 = formulirnya sudah terkirim / tahap tertutup.
            if (err?.response?.status === 409) setTimeout(() => router.reload(), 1500);
        },
        /**
         * Bersihkan nama berkas di draf yang TIDAK punya salinan di server.
         *
         * Bisa terjadi pada draf yang dibuat sebelum unggahan draf ada, atau bila
         * unggahannya gagal setelah jawabannya telanjur tersimpan. Kalau
         * dibiarkan, kandidat melihat kotak hijau "berkas siap dikirim" untuk
         * berkas yang isinya tidak ada di mana pun — terlihat beres, padahal
         * yang terkirim nanti kosong. Lebih baik dikembalikan ke keadaan kosong
         * dan diminta unggah ulang.
         */
        buangBerkasBasi() {
            if (!this.tugas) return;
            const skema = this.skemaTugas;
            const bagianSemua = (skema?.langkah || []).flatMap((L) => L.bagian || []);

            // Satu daftar sasaran berisi field biasa DAN field di dalam baris
            // berulang. Versi sebelumnya meratakan semua bagian lalu memeriksa
            // jawaban[key] — untuk field di dalam bagian berulang kunci itu tidak
            // pernah ada, jadi berkas basi di sana tak pernah ketahuan.
            const sasaran = [];
            bagianSemua.forEach((B) => {
                const fieldBerkas = (B.field || []).filter((x) => x.tipe === 'file');
                if (!fieldBerkas.length) return;

                if (!B.berulang) {
                    fieldBerkas.forEach((x) => sasaran.push({ field: x.key, bagian: null, baris: null }));
                    return;
                }

                const kunciB = B.key || '';
                const daftar = this.jawaban[kunciB];
                if (!Array.isArray(daftar)) return;

                daftar.forEach((_, i) => {
                    fieldBerkas.forEach((x) => sasaran.push({ field: x.key, bagian: kunciB, baris: i }));
                });
            });

            const nilai = (s) =>
                s.bagian === null ? this.jawaban[s.field] : (this.jawaban[s.bagian]?.[s.baris] || {})[s.field];

            const basi = sasaran.filter((s) => {
                const k = kunciBerkas(s.bagian, s.baris, s.field);
                return nilai(s) && !this.berkasDraf[k] && !this.berkas[k];
            });
            if (!basi.length) return;

            basi.forEach((s) => {
                if (s.bagian === null) {
                    this.jawaban[s.field] = '';
                } else {
                    this.jawaban[s.bagian][s.baris][s.field] = '';
                }
            });
            this.notice(`${basi.length} berkas perlu diunggah ulang — salinannya tidak ditemukan di server.`, true);
        },
        /** Tarik ulang daftar berkas draf dari server. */
        async muatBerkasDraf() {
            if (!this.tugas) return;
            try {
                const { data } = await axios.get(`/kandidat/lamaran/tahap/${this.tugas.tahapId}/draf`);
                const b = data?.result?.draf?.berkas || [];
                this.berkasDraf = Object.fromEntries(
                    b.map((x) => [kunciBerkas(x.bagian, x.baris, x.field), x]),
                );
            } catch (e) {
                // Tidak fatal: kartu berkas cukup kehilangan tautannya.
            }
        },
        /** Simpan tiap kali kandidat berpindah langkah ("Lanjut"/"Kembali"). */
        async simpanDraf(langkah) {
            if (!this.tugas || !this.drafSiap) return;
            try {
                await axios.post(`/kandidat/lamaran/tahap/${this.tugas.tahapId}/draf`, {
                    jawaban: this.jawaban,
                    langkah,
                    komponen: this.tugas.komponen,
                    schema: this.tugas.schema || null,
                });
            } catch (e) {
                // Pesan dari server ditampilkan apa adanya bila ada — galat
                // validasi yang disembunyikan di balik kalimat umum membuat
                // sebabnya mustahil ditebak dari layar.
                //
                // (Dulu blok ini memakai `err` yang tidak pernah ada, sehingga
                // galat simpan draf justru melempar ReferenceError dan pesannya
                // tidak pernah tampil.)
                this.notice(
                    e.response?.data?.message || 'Isian belum tersimpan ke server. Periksa koneksi Anda.',
                    true,
                );
                // 409 = formulirnya sudah terkirim / tahap tertutup.
                if (e.response?.status === 409) setTimeout(() => router.reload(), 1500);
            }
        },
        notice(x, err = false, lama = 4000) {
            this.toast = x;
            this.toastErr = err;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), lama);
        },
    },
};
</script>

<style scoped>
.ld-mono {
    font-family: 'JetBrains Mono', 'Courier New', monospace;
    font-weight: 700;
    color: #8b93a7;
    /* Kode lamaran tidak boleh patah di tengah (LMR-
TQVA5K0T) — ia dibaca
       & disalin sebagai satu satuan. */
    white-space: nowrap;
}
.ld-seclabel {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.14em;
    color: #8b93a7;
}
.ld-eyebrow {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.14em;
    color: #8b5cf6;
}
.ld-sectitle {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 15px;
    font-weight: 800;
    color: #1e293b;
}

/* overflow-x: clip mengeklip blob horizontal TANPA menjadikan .ld scroll
   container (hidden akan memaksa overflow-y:auto → scrollbar ganda). */
/* Lihat catatan sama di LamaranSaya.vue: margin bawah negatif + min-height
   tebakan membuat halaman menjulur keluar gradasi .app-shell (pita putih di
   bawah), dan blob dekoratif bottom: -160px ikut lolos kalau hanya sumbu X
   yang di-clip. */
.ld {
    position: relative;
    margin: -1rem -1rem 0;
    padding: 26px 30px 44px;
    overflow: clip;
}
.ld-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(8px);
    pointer-events: none;
    z-index: 0;
}
.ld-blob--a {
    top: -120px;
    right: 10%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.13), rgba(139, 92, 246, 0) 70%);
    animation: ldFloatA 16s ease-in-out infinite;
}
.ld-blob--b {
    bottom: -160px;
    left: 6%;
    width: 440px;
    height: 440px;
    background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0) 70%);
    animation: ldFloatB 19s ease-in-out infinite;
}
@keyframes ldFloatA {
    0%,
    100% {
        transform: translate(0, 0);
    }
    50% {
        transform: translate(28px, -22px);
    }
}
@keyframes ldFloatB {
    0%,
    100% {
        transform: translate(0, 0);
    }
    50% {
        transform: translate(-24px, 20px);
    }
}
/* Fluid: mengisi penuh lebar shell-content (bukan container terpusat). */
.ld-wrap {
    position: relative;
    z-index: 2;
    width: 100%;
}

.ld-back {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    text-decoration: none;
    margin-bottom: 14px;
    transition: color 0.16s;
}
.ld-back:hover {
    color: #4f46e5;
}

/* ═══ CATATAN DARI TIM — di kartu tahap aktif / kartu keputusan ═══
   Prefiks sendiri (ld-ctim): `.ld-cat` sudah dipakai blok lain di berkas ini. */
.ld-ctim {
    position: relative;
    margin: 16px 20px 0;
    padding: 16px 18px 16px 21px;
    border-radius: 18px;
    background: linear-gradient(135deg, #f7f5ff, #ffffff 70%);
    border: 1px solid #e2dcfb;
    box-shadow: 0 10px 26px rgba(99, 102, 241, 0.08);
    overflow: hidden;
    animation: ldKeadaanMasuk 0.45s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.ld-ctim::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #8b5cf6, #6366f1);
}
.ld-ctim__hd {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.ld-ctim__ic {
    flex: none;
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1rem;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 8px 18px rgba(99, 102, 241, 0.3);
}
.ld-ctim__ttl {
    min-width: 0;
}
.ld-ctim__ttl b {
    display: block;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: #1e1b4b;
}
.ld-ctim__ttl small {
    display: block;
    margin-top: 2px;
    font-size: 12.5px;
    font-weight: 600;
    line-height: 1.45;
    color: #6d28d9;
}
.ld-ctim .ld-ctim__html {
    font-size: 14.5px;
    line-height: 1.7;
    color: #334155;
}
.ld-ctim .ld-ctim__html :deep(a) {
    color: #4f46e5;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 3px;
    overflow-wrap: anywhere;
}
.ld-ctim__waktu {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #94a3b8;
}
.ld-ctim__lama {
    margin-top: 10px;
    padding-top: 6px;
    border-top: 1px dashed #e2dcfb;
}
.ld-ctim > .ld-ctim__lama:first-child {
    margin-top: 0;
    padding-top: 0;
    border-top: 0;
}
.ld-ctim__lama summary {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 40px;
    font-size: 13px;
    font-weight: 800;
    color: #6d28d9;
    list-style: none;
    cursor: pointer;
}
.ld-ctim__lama summary::-webkit-details-marker {
    display: none;
}
.ld-ctim__item {
    margin-top: 8px;
    padding: 12px 14px;
    border-radius: 14px;
    background: #fff;
    border: 1px solid #eef0f7;
}
.ld-ctim__itemhd {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 4px 10px;
    margin-bottom: 6px;
    font-size: 12.5px;
    color: #94a3b8;
}
.ld-ctim__itemhd b {
    color: #1e293b;
}
/* Di dalam kartu keputusan: menempel di kolom teks, bukan kartu kedua. */
.ld-ctim--putusan {
    margin: 12px 0 0;
    background: rgba(255, 255, 255, 0.78);
    border-color: rgba(15, 23, 42, 0.08);
    box-shadow: none;
}
/* Penunjuk di kartu keputusan: "ada catatan untuk tahap berikutnya ↓". */
.ld-verdict__cat {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 34px;
    margin-top: 9px;
    padding: 6px 12px;
    /* Bukan 999px: di ponsel kalimatnya membungkus 2–3 baris, dan pil bulat
       yang membungkus terlihat seperti gumpalan. */
    border-radius: 14px;
    font-size: 12.5px;
    font-weight: 800;
    line-height: 1.45;
    color: #047857;
    background: rgba(16, 185, 129, 0.13);
    text-decoration: none;
    transition: background 0.16s;
}
.ld-verdict__cat:hover {
    background: rgba(16, 185, 129, 0.22);
}
@media (max-width: 640px) {
    .ld-ctim {
        margin: 14px 14px 0;
        padding: 14px 14px 14px 18px;
        border-radius: 16px;
    }
    .ld-ctim--putusan {
        margin: 12px 0 0;
    }
    .ld-ctim .ld-ctim__html {
        font-size: 14px;
    }
}

/* HERO */
.ld-hero {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #eef0f7;
    box-shadow: 0 12px 34px rgba(15, 23, 42, 0.07);
    animation: ldRise 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
}
@keyframes ldRise {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.ld-hero__bar {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: linear-gradient(180deg, #8b5cf6, #6366f1);
}
.ld-hero__glow {
    position: absolute;
    top: -70px;
    right: -40px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.08), rgba(99, 102, 241, 0) 70%);
    pointer-events: none;
}
.ld-hero__in {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 22px 26px 22px 28px;
}
.ld-hero__avatar {
    width: 52px;
    height: 52px;
    border-radius: 15px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28);
}
.ld-hero__badges {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.ld-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
}
.ld-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #f59e0b;
    animation: ldPulse 2s infinite;
}
@keyframes ldPulse {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5);
    }
    70% {
        box-shadow: 0 0 0 8px rgba(245, 158, 11, 0);
    }
}
.ld-hero__title {
    margin: 11px 0 0;
    font-size: 23px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
}
.ld-hero__meta {
    font-size: 12.5px;
    color: #8792a6;
    margin-top: 5px;
}

/* BANNER */
/* Jarak DUA ARAH. Banner, kartu keputusan, dan progres bisa muncul
   berurutan; kalau hanya margin-atas yang diberi, blok pertama menempel
   ke blok sesudahnya dan terbaca seperti satu kotak yang tumpang tindih. */
.ld-banner {
    display: flex;
    gap: 13px;
    align-items: center;
    margin: 16px 0 14px;
    padding: 15px 17px;
    border-radius: 16px;
    border: 1px solid transparent;
}
.ld-banner__ico {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}
.ld-banner__title {
    font-size: 14px;
    font-weight: 800;
}
.ld-banner__text {
    font-size: 12.5px;
    line-height: 1.55;
    opacity: 0.85;
    margin-top: 2px;
}

/* PROGRES */
.ld-prog {
    margin-top: 16px;
    background: rgba(255, 255, 255, 0.72);
    border: 1px solid rgba(226, 232, 240, 0.75);
    border-radius: 20px;
    padding: 16px 20px;
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
}
/* Segitiga bawaan <summary> diganti chevron sendiri agar sebaris dengan teks. */
.ld-prog__sum {
    cursor: pointer;
    list-style: none;
}
.ld-prog__sum::-webkit-details-marker {
    display: none;
}
.ld-prog__sum:focus-visible {
    outline: 2px solid #6366f1;
    outline-offset: 3px;
    border-radius: 10px;
}
.ld-prog__count {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 800;
    color: #4f46e5;
}
.ld-prog__chev {
    transition: transform 0.24s cubic-bezier(0.22, 1, 0.36, 1);
}
.ld-prog[open] .ld-prog__chev {
    transform: rotate(180deg);
}
.ld-prog[open] .ld-steps {
    margin-top: 16px;
}
/* Baris "sedang berjalan" — mubazir begitu stepper dibuka. */
.ld-prog[open] .ld-prog__now {
    display: none;
}
.ld-prog__now {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 11px;
    font-size: 12.5px;
    line-height: 1.35;
    color: #64748b;
    flex-wrap: wrap;
}
.ld-prog__now strong {
    font-weight: 800;
    color: #1e293b;
}
.ld-prog__now-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex: none;
    background: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.18);
}
.ld-prog__now.is-fail .ld-prog__now-dot {
    background: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.16);
}
.ld-prog__now.is-fail strong {
    color: #dc2626;
}
.ld-prog__now.is-done .ld-prog__now-dot {
    background: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.16);
}
.ld-prog__now.is-todo .ld-prog__now-dot {
    background: #94a3b8;
    box-shadow: none;
}
.ld-prog__now-lbl {
    flex: none;
}
.ld-prog__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.ld-prog__head .ld-seclabel {
    letter-spacing: 0.1em;
}
.ld-prog__bar {
    display: block;
    height: 7px;
    border-radius: 99px;
    background: #eef0f7;
    overflow: hidden;
}
/* Anak <span> (bukan div): isi <summary> harus phrasing content agar HTML-nya sah. */
.ld-prog__bar > * {
    display: block;
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg, #8b5cf6, #6366f1);
    transition: width 0.4s ease;
}
.ld-steps {
    display: flex;
    align-items: flex-start;
    overflow-x: auto;
    padding-bottom: 6px;
}
.ld-step {
    flex: 0 0 120px;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}
.ld-step__line {
    position: absolute;
    top: 14px;
    left: -50%;
    width: 100%;
    height: 3px;
}
.ld-step__node {
    position: relative;
    z-index: 1;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
    background: #eef0f7;
    color: #94a3b8;
}
.ld-step__node.is-done {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
}
.ld-step__node.is-current {
    background: #fff;
    color: #4f46e5;
    border: 2px solid #f59e0b;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.18);
}
.ld-step__node.is-fail {
    background: #fff;
    color: #dc2626;
    border: 2px solid #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.14);
}
.ld-step__lbl {
    margin-top: 9px;
    font-size: 10.5px;
    font-weight: 700;
    text-align: center;
    line-height: 1.3;
    padding: 0 5px;
}

/* LAYOUT — single column fluid */
.ld-mainc {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 22px;
}
.ld-card {
    background: #fff;
    border: 1px solid #eef0f7;
    border-radius: 20px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
}

/* TAHAP AKTIF */
.ld-act {
    overflow: hidden;
}
.ld-act__head {
    display: flex;
    gap: 13px;
    padding: 18px 20px;
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.05), transparent);
    border-bottom: 1px solid #eef0f7;
}
.ld-act__ico {
    flex: 0 0 auto;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28);
}
.ld-act__title {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    margin-top: 3px;
    letter-spacing: -0.01em;
}
.ld-act__sub {
    font-size: 12.5px;
    color: #8792a6;
    margin-top: 2px;
}
.ld-act__form {
    padding: 18px 20px;
}
/* BATAS PENGISIAN — pita di atas formulir; warnanya ikut mendesaknya waktu. */
.ld-batas {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin: 0 0 16px;
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid #c7d2fe;
    background: linear-gradient(135deg, #eef2ff, #f8faff);
}
.ld-batas__ic {
    flex: none;
    width: 34px;
    height: 34px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    font-size: 15px;
    background: rgba(99, 102, 241, 0.14);
    color: #4338ca;
}
.ld-batas__isi {
    min-width: 0;
}
.ld-batas__isi b {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: #1e1b4b;
}
.ld-batas__isi small {
    display: block;
    margin-top: 3px;
    font-size: 12.5px;
    line-height: 1.55;
    color: #475569;
}
.ld-batas.is-dekat {
    border-color: #fde68a;
    background: linear-gradient(135deg, #fffbeb, #fffdf5);
}
.ld-batas.is-dekat .ld-batas__ic {
    background: rgba(245, 158, 11, 0.16);
    color: #b45309;
}
.ld-batas.is-dekat .ld-batas__isi b {
    color: #78350f;
}
.ld-batas.is-lewat {
    border-color: #fdba74;
    background: #fff7ed;
}
.ld-batas.is-lewat .ld-batas__ic {
    background: rgba(249, 115, 22, 0.16);
    color: #c2410c;
}
.ld-batas.is-kunci {
    border-color: #fca5a5;
    background: #fef2f2;
}
.ld-batas.is-kunci .ld-batas__ic {
    background: rgba(239, 68, 68, 0.14);
    color: #b91c1c;
}
.ld-batas.is-kunci .ld-batas__isi b {
    color: #991b1b;
}
/* Belum dibuka — terkunci, tapi bukan kesalahan kandidat: nada netral. */
.ld-batas.is-tutup {
    border-color: #cbd5e1;
    background: linear-gradient(135deg, #f1f5f9, #f8fafc);
}
.ld-batas.is-tutup .ld-batas__ic {
    background: rgba(71, 85, 105, 0.14);
    color: #334155;
}
@media (max-width: 640px) {
    .ld-batas {
        padding: 11px 12px;
    }
    .ld-batas__isi b {
        font-size: 13.5px;
    }
}

/* Daftar aktivitas dalam satu tahap (tahap multi-tes) */
.ld-subtes {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 16px 20px 0;
}
.ld-subtes__i {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 11px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
    background: #f6f7fb;
    border: 1px solid #e7eaf3;
}
.ld-subtes__i > i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #cbd5e1;
    flex: 0 0 auto;
}
.ld-subtes__i em {
    font-style: normal;
    font-weight: 600;
    font-size: 11px;
    color: #94a3b8;
}
.ld-subtes__i.is-done > i {
    background: #10b981;
}
.ld-subtes__i.is-fail > i {
    background: #ef4444;
}
.ld-subtes__i.is-open {
    border-color: #a5b4fc;
    background: #eef0fe;
}
.ld-subtes__i.is-open > i {
    background: #6366f1;
    animation: ldPulse 2s infinite;
}
.ld-subtes__i.is-sched > i {
    background: #f59e0b;
}
.ld-subtes__i.is-tim > i {
    background: #94a3b8;
}

.ld-cred {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1px;
    background: #eef0f7;
    border: 1px solid #eef0f7;
    border-radius: 14px;
    overflow: hidden;
    margin: 18px 20px 0;
}
.ld-cred__item {
    background: #fff;
    padding: 13px 15px;
    min-width: 0;
}
.ld-cred__lbl {
    display: block;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.1em;
    color: #a2a9ba;
}
.ld-cred__val {
    display: block;
    font-size: 15.5px;
    font-weight: 800;
    color: #1e293b;
    margin-top: 4px;
    word-break: break-word;
}
.ld-cred__val.ld-mono {
    color: #4f46e5;
    letter-spacing: 0.05em;
}

.ld-notice {
    display: flex;
    gap: 11px;
    align-items: flex-start;
    margin: 14px 20px 0;
    padding: 13px 15px;
    border-radius: 14px;
    font-size: 12.5px;
    line-height: 1.55;
}
.ld-notice b {
    display: block;
    margin-bottom: 1px;
    color: #1e293b;
}
.ld-notice p {
    margin: 0;
    color: #64748b;
}
/* Kotak informasi yang jadi elemen terakhir kartu butuh jarak bawah
   sendiri — tombol yang biasanya memberi jarak itu disembunyikan pada
   sebagian keadaan, sehingga kotaknya menempel ke tepi kartu. */
.ld-notice + .ld-notice {
    margin-top: 10px;
}
.ld-notice:last-child {
    margin-bottom: 22px;
}
.ld-notice--wait {
    --ldico: #d97706;
    background: linear-gradient(135deg, #fffbeb, #fff8ec);
    border: 1px solid #f5e0a3;
}
.ld-notice--done {
    --ldico: #059669;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.24);
}
.ld-notice--err {
    --ldico: #dc2626;
    background: rgba(239, 68, 68, 0.07);
    border: 1px solid rgba(239, 68, 68, 0.22);
}
/* ── Ikon status animatif ────────────────────────────────────────────────────
   Warna diambil dari --ldico milik variannya, jadi satu set markup dipakai
   ulang untuk hijau/kuning/merah tanpa menduplikasi SVG. Gerakannya halus dan
   berulang pelan; saat kartunya di-hover, temponya dipercepat sebagai umpan
   balik — bukan sekadar hiasan yang berputar terus. */
.ld-nico {
    flex: 0 0 auto;
    margin-top: -3px;
}
.ld-nico svg {
    display: block;
    overflow: visible;
}
.ld-nico__halo {
    fill: var(--ldico);
    opacity: 0.13;
    transform-origin: 22px 22px;
    animation: ldHalo 3.2s ease-in-out infinite;
}
.ld-nico__ring,
.ld-nico__check,
.ld-nico__hand,
.ld-nico__tri,
.ld-nico__bang,
.ld-nico__stroke {
    fill: none;
    stroke: var(--ldico);
    stroke-width: 2.2;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.ld-nico__pin,
.ld-nico__dot,
.ld-nico__sand {
    fill: var(--ldico);
    stroke: none;
}

/* Cincin & centang menggambar dirinya sendiri sekali saat muncul. */
.ld-nico__ring {
    stroke-dasharray: 85;
    stroke-dashoffset: 85;
    animation: ldGambar 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
.ld-nico__check {
    stroke-width: 2.7;
    stroke-dasharray: 24;
    stroke-dashoffset: 24;
    animation: ldGambarKecil 0.42s 0.52s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

/* ── SELESAI ────────────────────────────────────────────────────────────
   Aktivitas yang sudah berakhir memakai ikon yang SAMA dengan keadaan lain,
   dan itu membuatnya terbaca seperti keadaan lain. Di sini ia dibedakan:
   cincin gema mengembang dua kali lalu diam, dan centangnya mengentak sekali
   sesudah tergambar. Berhenti sendiri — gerak yang berputar tanpa akhir
   justru membaca sebagai "sedang berjalan", persis lawan dari maksudnya. */
.ld-nico--done .ld-nico__gema {
    fill: none;
    stroke: var(--ldico);
    stroke-width: 2;
    opacity: 0;
    transform-box: view-box;
    transform-origin: 22px 22px;
    animation: ldGemaSelesai 1.5s 0.45s cubic-bezier(0.22, 0.61, 0.36, 1) 2 both;
}
.ld-nico--done .ld-nico__check {
    transform-box: view-box;
    transform-origin: 22px 22px;
    animation:
        ldGambarKecil 0.42s 0.52s cubic-bezier(0.4, 0, 0.2, 1) forwards,
        ldCapSelesai 0.46s 0.92s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes ldGemaSelesai {
    0% { transform: scale(0.74); opacity: 0.5; }
    65% { opacity: 0; }
    100% { transform: scale(1.4); opacity: 0; }
}
@keyframes ldCapSelesai {
    0% { transform: scale(1); }
    45% { transform: scale(1.16); }
    100% { transform: scale(1); }
}
@media (prefers-reduced-motion: reduce) {
    .ld-nico--done .ld-nico__gema { animation: none; opacity: 0; }
    .ld-nico--done .ld-nico__check { animation: none; stroke-dashoffset: 0; }
}
.ld-nico__tri {
    stroke-dasharray: 64;
    stroke-dashoffset: 64;
    animation: ldGambar 0.75s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
.ld-nico__bang,
.ld-nico__dot {
    animation: ldKedip 2.1s ease-in-out infinite;
}

/* Jam: jarum menit berputar 4s, jarum jam 24s — terbaca sebagai "berjalan". */
.ld-nico__hand {
    transform-origin: 22px 22px;
}
.ld-nico__hand--h {
    animation: ldPutar 24s linear infinite;
}
.ld-nico__hand--m {
    animation: ldPutar 4s linear infinite;
}

/* Jam pasir: dibalik dua kali per siklus supaya kembali ke 360° tanpa lompat. */
.ld-nico__glass {
    transform-origin: 22px 22px;
    animation: ldBalik 5s cubic-bezier(0.65, 0, 0.35, 1) infinite;
}
.ld-nico__sand {
    animation: ldPasir 2.5s cubic-bezier(0.45, 0, 0.9, 0.55) infinite;
}

/* Interaksi: kartunya terangkat sedikit dan animasinya dipercepat saat hover. */
.ld-notice {
    transition:
        transform 0.22s ease,
        box-shadow 0.22s ease;
}
.ld-notice:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
}
.ld-notice:hover .ld-nico__halo {
    animation-duration: 1.3s;
}
.ld-notice:hover .ld-nico__hand--m {
    animation-duration: 1.1s;
}
.ld-notice:hover .ld-nico__glass {
    animation-duration: 2.2s;
}
.ld-notice:hover .ld-nico__sand {
    animation-duration: 1.1s;
}

@keyframes ldHalo {
    0%,
    100% {
        transform: scale(1);
        opacity: 0.13;
    }
    50% {
        transform: scale(1.13);
        opacity: 0.2;
    }
}
@keyframes ldGambar {
    to {
        stroke-dashoffset: 0;
    }
}
@keyframes ldGambarKecil {
    to {
        stroke-dashoffset: 0;
    }
}
@keyframes ldPutar {
    to {
        transform: rotate(360deg);
    }
}
@keyframes ldKedip {
    0%,
    100% {
        opacity: 1;
    }
    45% {
        opacity: 0.25;
    }
}
@keyframes ldBalik {
    0%,
    30% {
        transform: rotate(0deg);
    }
    45%,
    80% {
        transform: rotate(180deg);
    }
    95%,
    100% {
        transform: rotate(360deg);
    }
}
@keyframes ldPasir {
    0%,
    6% {
        transform: translateY(-5px);
        opacity: 0;
    }
    16% {
        opacity: 1;
    }
    62% {
        transform: translateY(5px);
        opacity: 1;
    }
    72%,
    100% {
        opacity: 0;
    }
}

.ld-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 18px 0 14px;
    padding: 5px;
    border-radius: 14px;
    background: #f1f2f9;
}
.ld-tabs__b {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 14px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    transition:
        background 0.16s,
        color 0.16s,
        box-shadow 0.16s;
}
.ld-tabs__b:hover {
    color: #4f46e5;
}
.ld-tabs__b.is-on {
    background: #fff;
    color: #4338ca;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.07);
}
.ld-tabs__n {
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 800;
    background: rgba(99, 102, 241, 0.13);
    color: #4f46e5;
}

.ld-cat {
    padding-bottom: 6px;
}
.ld-cat__i {
    padding: 14px 20px;
    border-top: 1px solid #f4f5fa;
}
.ld-cat__i:first-of-type {
    border-top: 0;
}
.ld-cat__head {
    display: flex;
    align-items: center;
    gap: 11px;
}
.ld-cat__no {
    flex: none;
    width: 30px;
    height: 30px;
    border-radius: 9px;
    display: grid;
    place-items: center;
    background: #eef0fb;
    color: #4f46e5;
    font-size: 11.5px;
    font-weight: 800;
}
.ld-cat__head b {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: #1e293b;
}
.ld-cat__head small {
    display: block;
    font-size: 11px;
    color: #94a3b8;
    margin-top: 1px;
}
.ld-cat__st {
    flex: none;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 800;
}
.ld-cat__st.is-ok {
    color: #059669;
    background: rgba(16, 185, 129, 0.12);
}
.ld-cat__st.is-err {
    color: #dc2626;
    background: rgba(239, 68, 68, 0.1);
}
/* Catatan tim — kartu berbingkai, bukan paragraf lepas. */
.ld-cat__note {
    margin: 10px 0 0 41px;
    padding: 10px 13px;
    border: 1px solid #e6e8f2;
    border-left: 3px solid #6366f1;
    border-radius: 0 12px 12px 0;
    background: #fafbff;
}
.ld-cat__note-lbl {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 9.5px;
    font-weight: 800;
    letter-spacing: 0.09em;
    color: #6366f1;
}
.ld-cat__note p {
    margin: 5px 0 0;
    font-size: 13px;
    line-height: 1.6;
    color: #475569;
    white-space: pre-line;
}

/* Catatan per aktivitas — daftar bernomor visual, tiap butir berbingkai
   supaya terbaca sebagai catatan terpisah, bukan satu blok teks panjang. */
.ld-cat__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 10px 0 0 41px;
}
.ld-cat__sub {
    padding: 9px 12px;
    border: 1px solid #eceefb;
    border-left: 3px solid #a5b4fc;
    background: #fafaff;
    border-radius: 0 10px 10px 0;
}
.ld-cat__sub b {
    font-size: 12.5px;
    font-weight: 800;
    color: #334155;
}
.ld-cat__sub small {
    font-weight: 600;
    color: #94a3b8;
}
.ld-cat__sub p {
    margin: 3px 0 0;
    font-size: 12.5px;
    line-height: 1.6;
    color: #475569;
    white-space: pre-line;
}
.ld-cat__berkas {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 7px;
    margin: 11px 0 0 41px;
}
.ld-cat__berkas-lbl {
    width: 100%;
    font-size: 9.5px;
    font-weight: 800;
    letter-spacing: 0.09em;
    color: #a2a9ba;
}
.ld-cat__berkas button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    max-width: 100%;
    padding: 6px 11px;
    border: 1px solid #e6e8f2;
    border-radius: 10px;
    background: #fff;
    font: inherit;
    font-size: 12.5px;
    font-weight: 700;
    color: #1e293b;
    cursor: pointer;
    transition:
        border-color 0.16s,
        background 0.16s;
}
.ld-cat__berkas button:hover {
    border-color: #a5b4fc;
    background: #f5f3ff;
}
.ld-cat__berkas .bi {
    flex: none;
    color: #dc2626;
}
.ld-cat__berkas .bi-file-earmark-image-fill {
    color: #6366f1;
}
.ld-cat__berkas span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ld-cat__berkas em {
    flex: none;
    font-style: normal;
    font-size: 11px;
    font-weight: 800;
    color: #4f46e5;
}

/* ══ BLOK SATU AKTIVITAS ══
   Bingkai inilah yang menjawab "jangan sampai komponennya saling tabrakan":
   jadwal, berkas, kredensial, dan tombol milik satu aktivitas berdiri di dalam
   satu kotak, sehingga dua aktivitas yang dijadwalkan bersamaan tetap terbaca
   sebagai dua hal terpisah. Warna kiri mengikuti keadaannya, jadi mata bisa
   memindai kolom itu saja untuk tahu mana yang menuntut tindakan. */
.ld-akt {
    margin: 16px 20px 0;
    border: 1px solid #eef0f7;
    border-left: 3px solid #cbd5e1;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
}

.ld-akt:last-child { margin-bottom: 20px; }

.ld-akt__head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 14px;
    background: #fbfbfe;
    border-bottom: 1px solid #f1f2f9;
}

/* Nomor urut aktivitas — bukan hiasan: pada tahap berurutan, inilah yang
   memberi tahu kandidat kenapa yang ketiga belum bisa dibuka. */
.ld-akt__no {
    flex: none;
    width: 22px;
    height: 22px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eef2ff;
    color: #4338ca;
    font-size: 11px;
    font-weight: 800;
}

.ld-akt__id { min-width: 0; flex: 1; }
.ld-akt__label { font-size: 13.5px; font-weight: 800; color: #1e293b; line-height: 1.35; }
.ld-akt__tipe { font-size: 11px; color: #94a3b8; margin-top: 1px; }

.ld-akt__badge {
    flex: none;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .01em;
    background: #f1f5f9;
    color: #64748b;
}

.ld-akt__body > *:first-child { margin-top: 14px; }
.ld-akt__body > *:last-child { margin-bottom: 14px; }
.ld-akt__body .ld-jdwwrap,
.ld-akt__body .ld-notice,
.ld-akt__body .ld-mcu,
.ld-akt__body .ld-antre,
.ld-akt__body .ld-pindah,
.ld-akt__body .ld-terkirim,
.ld-akt__body .ld-cred { margin-left: 14px; margin-right: 14px; }
.ld-akt__body .ld-btn-tes { margin: 14px; width: calc(100% - 28px); }

/* Aktivitas yang tak punya apa pun untuk ditampilkan. Tanpa kalimat ini
   bloknya berdiri kosong dan terbaca seperti kartu yang gagal dimuat. */
.ld-akt__sunyi {
    margin: 14px;
    font-size: 12.5px;
    line-height: 1.6;
    color: #64748b;
}

/* Warna keadaan — kosakatanya sama dengan `nada` di keadaanAktivitas(). */
.ld-akt.is-aksi { border-left-color: #6366f1; }
.ld-akt.is-aksi .ld-akt__badge { background: #eef2ff; color: #4338ca; }
.ld-akt.is-jadwal { border-left-color: #0ea5e9; }
.ld-akt.is-jadwal .ld-akt__badge { background: #e0f2fe; color: #0369a1; }
.ld-akt.is-tunggu { border-left-color: #f59e0b; }
.ld-akt.is-tunggu .ld-akt__badge { background: #fef3c7; color: #92400e; }
.ld-akt.is-proses,
.ld-akt.is-kirim { border-left-color: #8b5cf6; }
.ld-akt.is-proses .ld-akt__badge,
.ld-akt.is-kirim .ld-akt__badge { background: #f3e8ff; color: #6b21a8; }
/* Sedang diproses tim — ungu tenang, sama dengan `is-tinjau` pada kartu
   keadaan tahap. Sengaja BUKAN kuning "menunggu": tidak ada jadwal yang
   sedang ditunggu di sini. */
.ld-akt.is-tinjau { border-left-color: #7c3aed; }
.ld-akt.is-tinjau .ld-akt__badge { background: #ede9fe; color: #5b21b6; }
.ld-akt.is-selesai { border-left-color: #10b981; }
.ld-akt.is-selesai .ld-akt__badge { background: #d1fae5; color: #047857; }
.ld-akt.is-lewat { border-left-color: #ef4444; }
.ld-akt.is-lewat .ld-akt__badge { background: #fee2e2; color: #b91c1c; }
/* Ditunda tim — ungu master status DITUNDA. */
.ld-akt.is-tunda { border-left-color: #7c3aed; }
.ld-akt.is-tunda .ld-akt__badge { background: #ede9fe; color: #5b21b6; }

/* Belum gilirannya — sengaja diredupkan seluruhnya, bukan cuma dilencanai:
   yang terbuka hari ini harus menonjol di antara yang belum. */
.ld-akt.is-kunci { border-left-color: #cbd5e1; background: #fcfcfd; }
.ld-akt.is-kunci .ld-akt__no { background: #f1f5f9; color: #94a3b8; }
.ld-akt.is-kunci .ld-akt__label { color: #64748b; }

@media (max-width: 640px) {
    .ld-akt { margin-left: 12px; margin-right: 12px; }
    /* Kartu jadwal di dalam blok aktivitas: tiga lapis bantalan (halaman, blok,
       kartu) menyisakan kartu selebar 300px di ponsel 390px — rapatkan lapis
       tengahnya supaya isi kartu (tanggal, tombol Gabung) tidak berdesakan. */
    .ld-akt__body .ld-jdwwrap { margin-left: 8px; margin-right: 8px; }
}

/* Jarak kartu jadwal terhadap kartu tahap; isinya milik JadwalKartu.vue. */
.ld-jdwwrap {
    margin: 16px 20px 0;
}

/* Kartu tahap (.ld-act) tidak punya padding bawah — anak-anaknya yang membawa
   margin ATAS saja. Elemen terakhir karenanya menempel persis di tepi kartu,
   dan pada tahap tatap muka (jadwal sebagai isian terakhir) hasilnya terlihat
   seperti kartu yang terpotong. */
.ld-jdwwrap:last-child,
.ld-mcu:last-child,
.ld-keadaan:last-child,
.ld-notice:last-child {
    margin-bottom: 20px;
}

.ld-mcu {
    display: flex;
    gap: 13px;
    margin: 16px 20px 0;
    padding: 15px 17px;
    border-radius: 16px;
    border: 1px solid;
}
.ld-mcu.is-fit {
    background: rgba(16, 185, 129, 0.07);
    border-color: rgba(16, 185, 129, 0.3);
}
.ld-mcu.is-fit_with_note {
    background: rgba(245, 158, 11, 0.09);
    border-color: rgba(245, 158, 11, 0.32);
}
.ld-mcu.is-unfit {
    background: rgba(239, 68, 68, 0.07);
    border-color: rgba(239, 68, 68, 0.28);
}
.ld-mcu__ico {
    flex: none;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 17px;
}
.ld-mcu.is-fit .ld-mcu__ico {
    background: linear-gradient(140deg, #34d399, #10b981);
}
.ld-mcu.is-fit_with_note .ld-mcu__ico {
    background: linear-gradient(140deg, #fbbf24, #f59e0b);
}
.ld-mcu.is-unfit .ld-mcu__ico {
    background: linear-gradient(140deg, #f87171, #dc2626);
}
.ld-mcu__judul {
    font-size: 15px;
    font-weight: 800;
    color: #1e293b;
    margin-top: 2px;
}
.ld-mcu__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 14px;
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}
.ld-mcu__cat {
    margin: 9px 0 0;
    font-size: 12.5px;
    line-height: 1.6;
    color: #475569;
}
/* Penggantian biaya — hijau bila diganti, netral bila tidak (bukan merah:
   hasilnya sudah disampaikan di atas, baris ini hanya akibatnya). */
.ld-mcu__biaya {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    margin: 10px 0 0;
    padding: 8px 11px;
    border-radius: 10px;
    font-size: 12.5px;
    line-height: 1.55;
    font-weight: 600;
}
.ld-mcu__biaya .bi { flex: none; margin-top: 2px; }
.ld-mcu__biaya.is-diganti { color: #065f46; background: #ecfdf5; border: 1px solid #a7f3d0; }
.ld-mcu__biaya.is-tidak_diganti { color: #475569; background: #f1f5f9; border: 1px solid #e2e8f0; }


/* ── KEADAAN TAHAP — satu panel tenang, bukan daftar asesmen ────────────────
   Menggantikan daftar aktivitas berikut statusnya. Bentuknya sengaja
   menyerupai pemberitahuan, bukan tabel: tidak ada yang bisa ditelusuri
   kandidat di sini, dan tabel selalu mengundang untuk ditelusuri. */
.ld-keadaan {
    display: flex; align-items: flex-start; gap: 14px;
    margin: 16px 20px 0; padding: 16px 18px;
    border-radius: 18px; border: 1px solid;
    /* --ldk = warna aksen keadaan; seluruh bagian di dalam mewarisinya, jadi
       menambah keadaan baru cukup menyetel satu variabel. */
    color: var(--ldk);
    animation: ldKeadaanMasuk .45s cubic-bezier(.22, 1, .36, 1) both;
}
@keyframes ldKeadaanMasuk { from { opacity: 0; transform: translateY(6px); } }

.ld-keadaan__ico {
    flex: none; display: grid; place-items: center;
    width: 46px; height: 46px; border-radius: 15px;
    background: color-mix(in srgb, var(--ldk) 13%, transparent);
}
.ld-keadaan__teks { min-width: 0; }
.ld-keadaan b { display: block; font-size: 14.5px; font-weight: 800; letter-spacing: -.01em; color: var(--ldk); }
.ld-keadaan p { margin: 5px 0 0; font-size: 12.5px; line-height: 1.62; color: #64748b; }

/* ── Bagian SVG: mewarisi --ldk, jadi satu set gaya melayani semua keadaan ── */
.ld-kico__halo { fill: color-mix(in srgb, var(--ldk) 12%, transparent); stroke: none; transform-origin: center; }
.ld-kico__ring,
.ld-kico__jarum,
.ld-kico__gagang,
.ld-kico__check { stroke: var(--ldk); stroke-width: 2.1; stroke-linecap: round; stroke-linejoin: round; fill: none; }
.ld-kico__body { stroke: var(--ldk); stroke-width: 2.1; fill: color-mix(in srgb, var(--ldk) 14%, transparent); }
.ld-kico__pin,
.ld-kico__pasir,
.ld-kico__play { fill: var(--ldk); stroke: none; }

/* Jam berdetak — jarum menitnya berputar penuh, jarum jamnya lambat. */
.ld-kico__jarum--m { transform-origin: 24px 24px; animation: ldPutar 6s linear infinite; }
.ld-kico__jarum--h { transform-origin: 24px 24px; animation: ldPutar 36s linear infinite; }
@keyframes ldPutar { to { transform: rotate(360deg); } }

/* Halo berdenyut pelan — menandakan proses masih hidup, bukan macet. */
.ld-keadaan .ld-kico__halo { animation: ldDenyut 2.8s ease-in-out infinite; }
@keyframes ldDenyut { 0%, 100% { opacity: .55; transform: scale(1); } 50% { opacity: 1; transform: scale(1.06); } }

/* Centang & gembok digambar sekali saat muncul. */
.ld-kico__check { stroke-dasharray: 22; stroke-dashoffset: 22; animation: ldGambar .7s .25s ease-out forwards; }
.ld-kico__gagang { stroke-dasharray: 30; stroke-dashoffset: 30; animation: ldGambar .6s .2s ease-out forwards; }
@keyframes ldGambar { to { stroke-dashoffset: 0; } }

/* Tombol putar berdenyut — satu-satunya keadaan yang meminta tindakan. */
.ld-kico__ring--denyut { animation: ldRing 2s ease-out infinite; transform-origin: center; }
@keyframes ldRing { 0% { transform: scale(.9); opacity: 1; } 100% { transform: scale(1.18); opacity: 0; } }

/* Butir pasir jatuh — proses yang berjalan tanpa perlu campur tangan. */
.ld-kico__pasir { animation: ldPasir 1.9s ease-in infinite; }
@keyframes ldPasir { 0% { transform: translateY(-6px); opacity: 0; } 30% { opacity: 1; } 100% { transform: translateY(8px); opacity: 0; } }

/* Pesawat kertas melaju & kembali — "sudah berangkat, sedang di jalan".
   Jejak garisnya digambar sekali, memakai animasi ldGambar yang sama. */
.ld-kico__pesawat { fill: var(--ldk); stroke: none; animation: ldTerbang 2.6s ease-in-out infinite; }
@keyframes ldTerbang {
    0%, 100% { transform: translate(0, 0); opacity: 1; }
    45% { transform: translate(3px, -3px); opacity: .85; }
}

/* ── Nada tiap keadaan: cukup satu variabel + latar ── */
.ld-keadaan.is-tunggu { --ldk: #475569; background: #f8fafc; border-color: #e6e9f0; }
/* TERKIRIM — biru laut: kabar baik yang sudah tuntas dari sisi kandidat,
   tapi belum jadi keputusan. Sengaja beda dari hijau "bisa dikerjakan"
   supaya tidak terbaca sebagai ajakan mengerjakan sesuatu lagi. */
.ld-keadaan.is-kirim  { --ldk: #0284c7; background: linear-gradient(135deg, rgba(14, 165, 233, .1), rgba(14, 165, 233, .03)); border-color: rgba(14, 165, 233, .3); }
.ld-keadaan.is-tinjau { --ldk: #7c3aed; background: linear-gradient(135deg, rgba(124, 58, 237, .09), rgba(124, 58, 237, .03)); border-color: rgba(124, 58, 237, .26); }
.ld-keadaan.is-tunda { --ldk: #7c3aed; background: linear-gradient(135deg, rgba(124, 58, 237, .09), rgba(124, 58, 237, .03)); border-color: rgba(124, 58, 237, .26); }
.ld-keadaan.is-jadwal { --ldk: #4f46e5; background: linear-gradient(135deg, rgba(99, 102, 241, .09), rgba(99, 102, 241, .03)); border-color: rgba(99, 102, 241, .28); }
.ld-keadaan.is-aksi   { --ldk: #059669; background: linear-gradient(135deg, rgba(16, 185, 129, .1), rgba(16, 185, 129, .03)); border-color: rgba(16, 185, 129, .3); }
.ld-keadaan.is-proses { --ldk: #b45309; background: linear-gradient(135deg, rgba(245, 158, 11, .1), rgba(245, 158, 11, .03)); border-color: rgba(245, 158, 11, .3); }

/* Ponsel: ikon mengecil & panel merapat, teks tetap terbaca penuh. */
@media (max-width: 520px) {
    .ld-keadaan { gap: 11px; padding: 14px; border-radius: 15px; }
    .ld-keadaan__ico { width: 38px; height: 38px; border-radius: 12px; }
    .ld-keadaan__ico svg { width: 25px; height: 25px; }
    .ld-keadaan b { font-size: 13.5px; }
    .ld-keadaan p { font-size: 12px; }
}

/* Gerak dihentikan bagi yang memintanya — animasi di sini hiasan, bukan isi. */
@media (prefers-reduced-motion: reduce) {
    .ld-keadaan,
    .ld-keadaan * { animation: none !important; }
    .ld-kico__check, .ld-kico__gagang { stroke-dashoffset: 0; }
}


/* ── Kartu keputusan tahap ─────────────────────────────────────────────── */
.ld-verdict {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 0 0 16px;
    padding: 16px 18px;
    border-radius: 18px;
    border: 1px solid;
}
.ld-verdict.is-lolos {
    --ldico: #059669;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.04));
    border-color: rgba(16, 185, 129, 0.3);
}
.ld-verdict.is-gugur {
    --ldico: #dc2626;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.09), rgba(239, 68, 68, 0.03));
    border-color: rgba(239, 68, 68, 0.28);
}
.ld-verdict.is-tunda {
    --ldico: #4338ca;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.09), rgba(99, 102, 241, 0.03));
    border-color: rgba(99, 102, 241, 0.26);
}
.ld-verdict__eyebrow {
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.1em;
    color: #94a3b8;
}
.ld-verdict__title {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin-top: 2px;
    letter-spacing: -0.01em;
}
.ld-verdict__text {
    margin: 3px 0 0;
    font-size: 12.8px;
    line-height: 1.55;
    color: #64748b;
}
.ld-verdict__badge {
    flex: 0 0 auto;
    align-self: flex-start;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: #fff;
    background: var(--ldico);
}
.ld-nico__cross .ld-nico__bang {
    stroke-width: 2.7;
    animation: ldGambarKecil 0.4s 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    stroke-dasharray: 15;
    stroke-dashoffset: 15;
}
.ld-verdict-enter-active {
    transition:
        opacity 0.45s ease,
        transform 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}
.ld-verdict-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}

/* ── Kartu data kandidat ───────────────────────────────────────────────── */
.ld-profil {
    padding: 18px 20px 20px;
}
.ld-profil__head {
    display: flex;
    align-items: center;
    gap: 14px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eef0f7;
}
.ld-profil__foto {
    flex: 0 0 auto;
    width: 62px;
    height: 62px;
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    font-size: 22px;
    font-weight: 800;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28);
}
.ld-profil__foto img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: zoom-in;
}
.ld-profil__nama {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.015em;
}
.ld-profil__sub {
    font-size: 11.5px;
    color: #94a3b8;
    margin-top: 2px;
}
.ld-profil__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 2px 22px;
    margin-top: 4px;
}
.ld-profil__item {
    padding: 11px 0;
    border-bottom: 1px solid #f4f5fa;
    min-width: 0;
}
.ld-profil__lbl {
    display: block;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #a2a9ba;
    text-transform: uppercase;
}
.ld-profil__val {
    display: block;
    font-size: 13.5px;
    font-weight: 700;
    color: #1e293b;
    margin-top: 3px;
    word-break: break-word;
}
.ld-profil__kaki {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    margin: 14px 0 0;
    padding: 9px 12px;
    border-radius: 11px;
    background: #f6f7fb;
    font-size: 12px;
    line-height: 1.55;
    color: #64748b;
}
.ld-profil__kaki .bi {
    flex: none;
    margin-top: 1px;
    color: #94a3b8;
}
.ld-profil__kaki b {
    color: #475569;
}

@media (max-width: 640px) {
    .ld-verdict {
        flex-wrap: wrap;
    }
    .ld-verdict__badge {
        order: -1;
    }
}

/* Hormati preferensi sistem: tanpa gerak, ikonnya tetap tampil utuh. */
@media (prefers-reduced-motion: reduce) {
    .ld-nico * {
        animation: none !important;
        stroke-dashoffset: 0 !important;
    }
    .ld-notice,
    .ld-notice:hover {
        transition: none;
        transform: none;
        box-shadow: none;
    }
}

.ld-btn-tes {
    margin: 16px 20px 20px;
    width: calc(100% - 40px);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 14px;
    border: none;
    border-radius: 14px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 12px 28px rgba(99, 102, 241, 0.3);
    transition: transform 0.16s;
}
.ld-btn-tes:hover:not(:disabled) {
    transform: translateY(-2px);
}
.ld-btn-tes:disabled {
    background: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
    box-shadow: none;
}

/* ── Layar antara menuju ruang ujian ─────────────────────────────────────── */
.ld-pindah { display: flex; align-items: center; gap: 14px; margin: 16px 20px 20px; padding: 15px 17px; border-radius: 16px; background: linear-gradient(135deg, rgba(99, 102, 241, .1), rgba(99, 102, 241, .03)); border: 1px solid rgba(99, 102, 241, .3); }
.ld-pindah__ring { position: relative; flex: none; display: grid; place-items: center; width: 44px; height: 44px; }
.ld-pindah__ring b { position: absolute; font-size: 14px; font-weight: 800; color: #4f46e5; font-variant-numeric: tabular-nums; }
.ld-pindah__jalur { stroke: rgba(99, 102, 241, .2); stroke-width: 3.5; }
/* Lingkaran menyusut selama tiga detik — hitungannya terlihat, bukan cuma angka. */
.ld-pindah__isi { stroke: #4f46e5; stroke-width: 3.5; stroke-linecap: round; stroke-dasharray: 119.4; transform: rotate(-90deg); transform-origin: center; animation: ldPindah 3s linear forwards; }
@keyframes ldPindah { from { stroke-dashoffset: 0; } to { stroke-dashoffset: 119.4; } }
.ld-pindah__teks { flex: 1; min-width: 0; }
.ld-pindah__teks b { display: block; font-size: 14px; font-weight: 800; color: #3730a3; }
.ld-pindah__teks p { margin: 3px 0 0; font-size: 12.5px; line-height: 1.55; color: #64748b; }
.ld-pindah__batal { flex: none; border: 1px solid #c7d2fe; background: #fff; color: #4f46e5; font-size: 12px; font-weight: 800; border-radius: 9px; padding: 7px 13px; cursor: pointer; }
.ld-pindah__batal:hover { background: #eef2ff; }
@media (max-width: 520px) {
    .ld-pindah { flex-wrap: wrap; margin-left: 14px; margin-right: 14px; }
    .ld-pindah__batal { width: 100%; }
}
@media (prefers-reduced-motion: reduce) { .ld-pindah__isi { animation: none; } }

/* Belum gilirannya — keterangan, bukan tombol mati. Nadanya netral: tidak ada
   yang salah, hanya belum saatnya. */
.ld-antre { margin: 16px 20px 20px; display: flex; align-items: flex-start; gap: 9px; padding: 13px 15px; border-radius: 14px; font-size: 13px; line-height: 1.55; color: #475569; background: #f1f5f9; border: 1px solid #e2e8f0; }
.ld-antre svg { flex: none; margin-top: 1px; color: #94a3b8; }
.ld-antre b { color: #334155; }

/* ── Jawaban baru terkirim, hasilnya belum sampai ──────────────────────────
   Menempati posisi tombol "Mulai Tes" supaya kandidat yang baru pulang dari
   ruang ujian tidak pernah melihat ajakan mengerjakannya lagi. Birunya sama
   dengan panel keadaan is-kirim — satu peristiwa, satu warna. */
.ld-terkirim { --ldt: #0284c7; margin: 16px 20px 20px; display: flex; align-items: flex-start; gap: 11px; padding: 13px 15px; border-radius: 14px; background: linear-gradient(135deg, rgba(14, 165, 233, .1), rgba(14, 165, 233, .03)); border: 1px solid rgba(14, 165, 233, .3); }
.ld-terkirim__ring { flex: 0 0 auto; margin-top: -2px; }
.ld-terkirim__ring svg { display: block; overflow: visible; }
.ld-terkirim__halo { fill: var(--ldt); opacity: .14; transform-origin: 22px 22px; animation: ldHalo 3.2s ease-in-out infinite; }
.ld-terkirim__pesawat { fill: var(--ldt); stroke: none; animation: ldTerbang 2.6s ease-in-out infinite; }
.ld-terkirim__teks { min-width: 0; }
.ld-terkirim b { display: block; font-size: 13.5px; font-weight: 800; color: #075985; }
.ld-terkirim p { margin: 4px 0 0; font-size: 12.5px; line-height: 1.6; color: #64748b; }

@media (max-width: 520px) {
    .ld-terkirim { margin: 14px 14px 18px; padding: 12px 13px; gap: 9px; }
    .ld-terkirim b { font-size: 13px; }
    .ld-terkirim p { font-size: 12px; }
}

/* Gerak hanyalah penekanan di sini — isinya tetap terbaca penuh tanpanya. */
@media (prefers-reduced-motion: reduce) {
    .ld-terkirim * { animation: none !important; }
}

/* ALUR SELEKSI · WATERFALL (gantt bertingkat) */
.ld-wf {
    padding: 18px 20px;
}
.ld-wf__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.ld-wf__legend {
    display: flex;
    align-items: center;
    gap: 14px;
}
.ld-wf__legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 700;
    color: #64748b;
}
.ld-wf__legend i {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    display: inline-block;
}
.ld-wf__body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.ld-wf__row {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 16px;
    align-items: center;
}
.ld-wf__side {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}
.ld-wf__node {
    flex: 0 0 auto;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
    background: #eef0f7;
    color: #94a3b8;
}
.ld-wf__node.is-done {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
}
.ld-wf__node.is-current {
    background: #fff;
    color: #b45309;
    border: 2px solid #f59e0b;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.16);
}
.ld-wf__node.is-fail {
    background: #fff;
    color: #dc2626;
    border: 2px solid #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.14);
}
.ld-wf__name {
    font-size: 13.5px;
    font-weight: 800;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ld-wf__st {
    font-size: 11px;
    font-weight: 700;
    margin-top: 1px;
}
.ld-wf__track {
    position: relative;
    height: 32px;
}
/* Garis pemisah antar kolom tahap — pembagi yang rata, bukan penanda tanggal. */
.ld-wf__grid {
    position: absolute;
    top: -6px;
    bottom: -6px;
    width: 1px;
    background: #eef0f7;
}
.ld-wf__bar {
    position: absolute;
    top: 4px;
    height: 24px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0 10px;
    transition:
        width 0.4s ease,
        margin-left 0.4s ease;
    z-index: 1;
}
.ld-wf__bar.is-empty {
    box-shadow: none;
}
.ld-wf__bar.is-glow {
    box-shadow: 0 6px 18px rgba(245, 158, 11, 0.4);
    animation: ldWfGlow 2s ease-in-out infinite;
}
@keyframes ldWfGlow {
    0%,
    100% {
        box-shadow: 0 6px 18px rgba(245, 158, 11, 0.35);
    }
    50% {
        box-shadow: 0 6px 24px rgba(245, 158, 11, 0.55);
    }
}

/* DETAIL LOWONGAN — info kaya */
.ld-info {
    padding: 18px 20px;
}
.ld-inforow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.ld-detailbtn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 12.5px;
    font-weight: 800;
    color: #4f46e5;
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 11px;
    background: rgba(99, 102, 241, 0.09);
    border: 1px solid rgba(99, 102, 241, 0.18);
    transition: all 0.16s;
}
.ld-detailbtn:hover {
    background: rgba(99, 102, 241, 0.16);
    color: #4338ca;
}
.ld-info__desc {
    margin: 14px 0 0;
    font-size: 13.5px;
    line-height: 1.65;
    color: #64748b;
}
.ld-facts {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
}
.ld-fact {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 9px 14px;
    border-radius: 13px;
    background: #f6f7fb;
    border: 1px solid #eef0f7;
}
.ld-fact__ico {
    flex: 0 0 auto;
    display: flex;
}
.ld-fact__lbl {
    display: block;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #a2a9ba;
}
.ld-fact__val {
    display: block;
    font-size: 13px;
    font-weight: 800;
    color: #1e293b;
    margin-top: 1px;
}
.ld-blk {
    margin-top: 20px;
}
.ld-blk__title {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 13.5px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 11px;
}
.ld-blk__ico {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.ld-blk__ico--green {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}
.ld-blk__ico--amber {
    background: rgba(245, 158, 11, 0.14);
    color: #d97706;
}
.ld-blk__ico--indigo {
    background: rgba(99, 102, 241, 0.12);
    color: #6366f1;
}
.ld-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.ld-list li {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    font-size: 13px;
    line-height: 1.55;
    color: #475569;
}
.ld-list--check li svg {
    flex: 0 0 auto;
    margin-top: 2px;
}
.ld-list--dot li span {
    flex: 0 0 auto;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #cbd2e0;
    margin-top: 7px;
}
.ld-skills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.ld-skills span {
    font-size: 12px;
    font-weight: 700;
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 9px;
    padding: 6px 12px;
}
.ld-benefits {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px 16px;
}
.ld-benefit {
    display: inline-flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
}
.ld-benefit svg {
    flex: 0 0 auto;
    margin-top: 2px;
}

/* FORMULIR & BERKAS */
.ld-secrow { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.ld-seccount { font-size: 11.5px; font-weight: 700; color: #8b93a7; background: #eef0f7; border-radius: 999px; padding: 4px 11px; }
.ld-forms { display: flex; flex-direction: column; gap: 12px; }
.ld-form { background: #fff; border: 1px solid #eef0f7; border-radius: 18px; overflow: hidden; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); transition: border-color 0.2s; }
.ld-form.is-open { border-color: #d9def0; }
.ld-form__head { appearance: none; border: none; background: #fff; width: 100%; display: flex; align-items: center; gap: 13px; padding: 15px 18px; cursor: pointer; text-align: left; font-family: inherit; }
.ld-form.is-open .ld-form__head { background: #fbfbff; }
.ld-form__step { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #cbd2e0, #94a3b8); color: #fff; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.ld-form__step.is-ok { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.ld-form__title { display: block; font-size: 14.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ld-form__sub { display: block; font-size: 11.5px; color: #8b93a7; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ld-form__pill { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
.ld-form__pill.is-ok { background: rgba(16, 185, 129, 0.12); color: #059669; }
.ld-field__file { display: inline-flex; align-items: center; gap: 7px; max-width: 100%; padding: 5px 10px; margin-top: 2px; border: 1px solid #e6e8f2; border-radius: 9px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #1e293b; cursor: pointer; transition: border-color .16s, background .16s; }
.ld-field__file:hover { border-color: #a5b4fc; background: #f5f3ff; }
.ld-field__file .bi { flex: none; color: #dc2626; }
.ld-field__file .bi-file-earmark-image-fill { color: #6366f1; }
.ld-field__file span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ld-field__file em { flex: none; font-style: normal; font-size: 11.5px; font-weight: 800; color: #4f46e5; }
.ld-form__pill.is-err { color: #dc2626; background: rgba(239, 68, 68, .1); }
.ld-form__pill.is-wait { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.ld-form__chev { flex: 0 0 auto; transition: transform 0.26s; }
.ld-form.is-open .ld-form__chev { transform: rotate(180deg); }
.ld-form__body { padding: 6px 18px 18px; animation: ldAcc 0.28s cubic-bezier(0.22, 1, 0.36, 1) both; }
@keyframes ldAcc { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
/* DUA KOLOM HANYA BILA MUAT. `1fr 1fr` yang dipatok memaksa dua kolom sesempit
   apa pun layarnya — di ponsel, alamat dan uraian terjepit jadi kolom setipis
   dua kata. auto-fit + minmax menurunkannya sendiri jadi satu kolom. */
/* ── JUDUL KELOMPOK (satu langkah formulir) ── */
.ld-ghead { display: flex; align-items: center; gap: 8px; margin: 16px 0 0; }
.ld-ghead:first-child { margin-top: 4px; }
.ld-ghead__ico { flex: none; width: 24px; height: 24px; display: grid; place-items: center; border-radius: 8px; background: #eef2ff; color: #4f46e5; font-size: 12px; }
.ld-ghead__lbl { font-size: 11.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #475569; }
.ld-ghead__garis { flex: 1; height: 1px; background: linear-gradient(90deg, #e2e8f0, transparent); }

/* auto-fit + minmax: satu kolom di ponsel, dua-tiga di layar lebar — tanpa
   satu pun titik henti media query yang harus dijaga selaras. */
.ld-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr)); gap: 1px; background: #eef0f7; border: 1px solid #eef0f7; border-radius: 14px; overflow: hidden; margin-top: 8px; }
.ld-field { background: #fff; padding: 11px 14px; min-width: 0; }
.ld-field.is-panjang { grid-column: 1 / -1; }
/* ADA / TIDAK ADA — berkasnya sendiri ada di "Dokumen & Verifikasi" di bawah. */
.ld-badge { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 800; }
.ld-badge.is-ada { color: #047857; background: rgba(16, 185, 129, .12); }
.ld-badge.is-kosong { color: #94a3b8; background: #f1f5f9; }
.ld-field__k { font-size: 10.5px; font-weight: 700; letter-spacing: 0.06em; color: #a2a9ba; text-transform: uppercase; }
.ld-field__v { font-size: 13.5px; font-weight: 700; color: #1e293b; margin-top: 3px; word-break: break-word; }

/* ── ISIAN BERULANG: tiap baris berdiri sendiri ──────────────────────────────
   Bernomor hanya bila lebih dari satu — nomor "1" tunggal cuma menambah bunyi
   pada baris yang sudah jelas berdiri sendirian. */
/* Gambar jawaban — pratinjau kecil yang bisa diperbesar. */
.ld-field__thumb { appearance: none; display: inline-flex; align-items: center; gap: 9px; margin-top: 5px; padding: 5px 10px 5px 5px; border: 1px solid #e6e8f2; border-radius: 12px; background: #fff; font: inherit; font-size: 12px; font-weight: 700; color: #4f46e5; cursor: zoom-in; transition: border-color .16s, background .16s; max-width: 100%; }
.ld-field__thumb:hover { border-color: #a5b4fc; background: #f5f3ff; }
.ld-field__thumb img { flex: none; width: 54px; height: 54px; border-radius: 9px; object-fit: cover; background: #f1f5f9; }
.ld-field__thumb span { display: inline-flex; align-items: center; gap: 4px; }

/* ── DAFTAR BUTIR ── */
.ld-butir { list-style: none; counter-reset: butir; margin: 5px 0 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.ld-butir li { counter-increment: butir; position: relative; padding: 5px 10px 5px 30px; border-radius: 9px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; line-height: 1.5; word-break: break-word; }
.ld-butir li::before {
    content: counter(butir);
    position: absolute; left: 7px; top: 5px;
    width: 17px; height: 17px; display: grid; place-items: center;
    border-radius: 999px; background: #e0e7ff; color: #4338ca;
    font-size: 10px; font-weight: 800;
}

.ld-rows { display: flex; flex-direction: column; gap: 6px; margin-top: 5px; }
.ld-row { display: flex; gap: 8px; align-items: flex-start; background: #f8fafc; border: 1px solid #e8eef6; border-radius: 9px; padding: 7px 9px; }
.ld-row__no { flex: none; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; background: #e0e7ff; color: #4338ca; border-radius: 999px; font-size: 10.5px; font-weight: 800; margin-top: 1px; }
.ld-row__isi { display: flex; flex-wrap: wrap; gap: 3px 14px; min-width: 0; }
.ld-row__p { font-size: 12.5px; font-weight: 600; color: #334155; min-width: 0; word-break: break-word; }
.ld-row__p b { display: block; font-size: 10px; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; color: #94a3b8; }
/* Tombol berkas di dalam baris berulang — sengaja SEUKURAN TEKS di sebelahnya,
   bukan tombol besar seperti di daftar "Dokumen & Verifikasi". Satu baris
   riwayat bisa memuat beberapa sub-isian; tombol setinggi kartu akan mendorong
   pasangan label-nilai di kanan-kirinya turun dan barisnya patah. */
.ld-row__file { appearance: none; display: inline-flex; align-items: center; gap: 5px; margin-top: 1px; padding: 3px 9px; border: 1px solid #d9def0; border-radius: 8px; background: #fff; color: #6366f1; font-size: 12px; font-weight: 700; font-family: inherit; cursor: pointer; max-width: 100%; transition: all .16s; }
.ld-row__file:hover { background: #6366f1; border-color: #6366f1; color: #fff; }
.ld-row__ext { font-size: 9.5px; font-weight: 800; letter-spacing: .04em; opacity: .7; }

@media (max-width: 640px) {
    .ld-row__isi { gap: 3px 10px; }
    .ld-row__p { font-size: 12px; }
}
.ld-docs { margin-top: 14px; display: flex; flex-direction: column; gap: 9px; }
.ld-docs__label { font-size: 11px; font-weight: 800; letter-spacing: 0.12em; color: #a2a9ba; }
.ld-doc { display: flex; align-items: center; gap: 13px; padding: 12px 14px; border: 1px solid #eef0f7; border-radius: 14px; background: #fbfbfe; }
.ld-doc__ico { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.ld-doc__ico.is-img { background: rgba(99, 102, 241, 0.12); }
.ld-doc__ico.is-pdf { background: rgba(239, 68, 68, 0.1); }
.ld-doc__toprow { display: flex; align-items: center; gap: 8px; }
.ld-doc__name { font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ld-doc__no { flex: none; font-size: 11px; font-weight: 700; color: #7c3aed; }
.ld-doc__ext { font-size: 9px; font-weight: 800; letter-spacing: 0.06em; color: #8b93a7; background: #eef0f7; border-radius: 5px; padding: 2px 6px; flex: 0 0 auto; }
.ld-doc__desc { font-size: 11.5px; color: #8b93a7; margin-top: 2px; line-height: 1.4; }
.ld-doc__eye { appearance: none; border: 1px solid #d9def0; background: #fff; width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #6366f1; flex: 0 0 auto; transition: all 0.16s; }
.ld-doc__eye:hover { background: #6366f1; color: #fff; border-color: #6366f1; transform: translateY(-1px); }

/* WATERFALL TAHAPAN (kiri, sticky) — garis penghubung mengalir ke bawah */
.ld-tl {
    background: rgba(255, 255, 255, 0.72);
    border: 1px solid rgba(226, 232, 240, 0.75);
    border-radius: 20px;
    padding: 20px 18px;
    position: sticky;
    top: 16px;
}
.ld-tl__list {
    position: relative;
    padding-left: 34px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.ld-tl__item {
    position: relative;
    padding-bottom: 18px;
}
.ld-tl__item:last-child {
    padding-bottom: 0;
}
/* garis vertikal penghubung antar-node (waterfall) — warna mengikuti progres */
.ld-tl__item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: -20px;
    top: 26px;
    bottom: -4px;
    width: 2.5px;
    border-radius: 3px;
    background: #eef0f7;
}
.ld-tl__item.is-done:not(:last-child)::before {
    background: linear-gradient(180deg, #8b5cf6, #a5b4fc);
}
.ld-tl__item.is-current:not(:last-child)::before {
    background: linear-gradient(180deg, #f59e0b, #eef0f7);
}
.ld-tl__item.is-fail:not(:last-child)::before {
    background: linear-gradient(180deg, #ef4444, #eef0f7);
}
.ld-tl__node {
    position: absolute;
    left: -34px;
    top: 0;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 3px solid #cbd2e0;
    z-index: 1;
}
.ld-tl__node span {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: block;
}
.ld-tl__lbl {
    font-size: 13px;
    font-weight: 800;
}
.ld-tl__meta {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 4px;
    flex-wrap: wrap;
}
.ld-tl__tag {
    font-size: 10px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
}
.ld-tl__tag--hcl {
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
}
.ld-tl__st {
    font-size: 10.5px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 999px;
}

/* LIGHTBOX */
/* Lightbox HARUS di atas modal keputusan (.wca-modal-mask = 1200).
   Dulu 1090, sehingga pratinjau berkas yang dibuka DARI DALAM modal
   muncul di belakangnya — terlihat seperti tombolnya tidak berfungsi. */
.ld-lb {
    position: fixed;
    inset: 0;
    z-index: 1250;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(10, 10, 20, 0.72);
    backdrop-filter: blur(6px);
    transition: opacity 0.28s;
    opacity: 0;
    pointer-events: none;
}
.ld-lb.is-on {
    opacity: 1;
    pointer-events: auto;
}
/* PDF butuh ruang baca; gambar tetap nyaman di lebar ini. */
.ld-lb__wrap.is-pdf {
    max-width: 900px;
}
.ld-lb__wrap {
    max-width: 520px;
    width: 100%;
    animation: ldPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes ldPop {
    0% {
        opacity: 0;
        transform: scale(0.9);
    }
    60% {
        transform: scale(1.02);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}
.ld-lb__bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}
.ld-lb__ico {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    background: rgba(255, 255, 255, 0.14);
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.ld-lb__name {
    font-size: 15px;
    font-weight: 800;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ld-lb__desc {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.6);
}
.ld-lb__close {
    appearance: none;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.08);
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #fff;
    flex: 0 0 auto;
}
.ld-lb__close:hover {
    background: rgba(255, 255, 255, 0.18);
}
.ld-lb__card {
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
}
.ld-lb__card img {
    display: block;
    width: 100%;
    max-height: 420px;
    object-fit: contain;
    background: #0f172a;
}
.ld-lb__state {
    height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: linear-gradient(135deg, #f1f2f9, #e8eaf6);
    color: #64748b;
    font-size: 13.5px;
    font-weight: 700;
}@keyframes ldSpin {
    to {
        transform: rotate(360deg);
    }
}
.ld-lb__foot {
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    border-top: 1px solid #eef0f7;
}
.ld-lb__pdf {
    display: block;
    width: 100%;
    height: min(74vh, 780px);
    border: 0;
    border-radius: 12px;
    background: #fff;
}
.ld-lb__ok {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 800;
    color: #059669;
    flex: 0 0 auto;
}

/* TOAST */
/* ═══ PENAWARAN: kartu + dialog jawaban kandidat ═══ */
.ld-offer {
    margin: 16px 20px 20px;
    padding: 16px 18px;
    border-radius: 16px;
    border: 1px solid rgba(99, 102, 241, 0.3);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.04));
}
.ld-offer__head {
    display: flex;
    align-items: center;
    gap: 12px;
}
.ld-offer__ico {
    flex: none;
    width: 42px;
    height: 42px;
    border-radius: 13px;
    display: grid;
    place-items: center;
    font-size: 18px;
    color: #fff;
    background: linear-gradient(140deg, #818cf8, #6366f1);
}
.ld-offer__judul {
    font-size: 16px;
    font-weight: 800;
    color: #1e293b;
    margin-top: 2px;
}


/* Penawaran belum terbit — keadaan menunggu, bukan galat. */
.ld-offer__wait {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    margin-top: 13px;
    padding: 13px 15px;
    border-radius: 13px;
    color: #92400e;
    background: rgba(245, 158, 11, 0.09);
    border: 1px solid rgba(245, 158, 11, 0.28);
}
.ld-offer__wait .bi {
    flex: none;
    font-size: 17px;
    margin-top: 1px;
}
.ld-offer__wait b {
    display: block;
    font-size: 13.5px;
    font-weight: 800;
}
.ld-offer__wait p {
    margin: 4px 0 0;
    font-size: 12.5px;
    line-height: 1.6;
    color: #475569;
}


@media (max-width: 560px) {
    .ld-offer {
        margin-left: 14px;
        margin-right: 14px;
    }
}

/* Lapis toast bersama — lihat --wca-z-toast di evo-theme.css. Halaman ini
   punya lightbox berkas di 1250; 1300 lolos tipis dari situ tapi kalah oleh
   overlay lain, jadi angkanya ikut disamakan. */
.ld-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    max-width: min(520px, calc(100vw - 48px));
    line-height: 1.5;
    z-index: var(--wca-z-toast, 100000);
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 12px 18px;
    border-radius: 13px;
    background: #0f172a;
    color: #fff;
    font-size: 13.5px;
    font-weight: 700;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.3);
}
.ld-toast.is-err {
    background: #dc2626;
}
.ld-toast .bi {
    color: #34d399;
}
.ld-toast.is-err .bi {
    color: #fff;
}
.ld-toast-enter-active,
.ld-toast-leave-active {
    transition:
        opacity 0.25s,
        transform 0.25s;
}
.ld-toast-enter-from,
.ld-toast-leave-to {
    opacity: 0;
    transform: translateY(12px);
}

@media (max-width: 760px) {
    .ld-wf__row {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .ld-wf__track {
        display: none;
    }
}
@media (max-width: 760px) {
    /* STEPPER: mendatar → menurun. 120px per tahap berarti pada 7 tahap sebagian
       besar isinya tersembunyi di balik gulir samping yang tak terlihat —
       padahal ini ringkasan utama halaman. Menurun: semua terbaca sekaligus. */
    .ld-steps {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
        overflow-x: visible;
        padding-bottom: 0;
    }
    .ld-step {
        flex: 0 0 auto;
        flex-direction: row;
        align-items: center;
        gap: 12px;
        width: 100%;
    }
    .ld-step__line {
        top: auto;
        bottom: calc(100% + 1px);
        left: 13.5px;
        width: 3px;
        height: 15px;
    }
    .ld-step__node {
        flex: 0 0 auto;
    }
    .ld-step__lbl {
        margin-top: 0;
        padding: 0;
        text-align: left;
        font-size: 12.5px;
    }
    /* Aksen kartu: pita kiri → garis atas, hanya di layar sempit (kartu paling
       panjang di sana, dan pita setinggi kartu terbaca seperti salah render). */
    .ld-hero__bar {
        right: 0;
        bottom: auto;
        width: auto;
        height: 4px;
        background: linear-gradient(90deg, #8b5cf6, #6366f1);
    }
    .ld-hero__in {
        padding-left: 20px;
    }
}
/* Tablet ke atas: stepper bukan dropdown — selalu terbuka & tak bisa dilipat. */
@media (min-width: 768px) {
    .ld-prog__sum {
        cursor: default;
        pointer-events: none;
    }
    .ld-prog__chev {
        display: none;
    }
    .ld-prog__bar {
        margin-bottom: 16px;
    }
    .ld-prog[open] .ld-steps {
        margin-top: 0;
    }
}
@media (max-width: 640px) {
    .ld {
        padding: 20px 16px 40px;
    }
    .ld-cred,
    .ld-fields,
    .ld-profil__grid,
    .ld-benefits {
        grid-template-columns: 1fr;
    }
    /* Di ponsel kepala kartu ditumpuk: foto 62px di samping nama panjang
       menyisakan lebar tak cukup untuk satu kata pun. */
    .ld-profil__head {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    .ld-profil__grid {
        gap: 0;
    }
    .ld-ghead {
        margin-top: 14px;
    }
    .ld-field__thumb img {
        width: 46px;
        height: 46px;
    }
    .ld-hero__in {
        padding: 18px;
    }
    .ld-prog {
        padding: 14px 16px;
    }
    /* Kartu jadwal & hasil menempel lebih rapat ke tepi layar sempit —
       margin 20px membuat isinya tinggal separuh lebar di ponsel. */
    .ld-jdwwrap,
    .ld-mcu,
    .ld-keadaan {
        margin-left: 14px;
        margin-right: 14px;
    }
    .ld-cat__i {
        padding: 14px;
    }
    /* Indent 41px (selebar nomor tahap) tidak muat di ponsel: catatannya
       jadi kolom sempit yang setiap kalimatnya patah. */
    .ld-cat__note,
    .ld-cat__list,
    .ld-cat__berkas {
        margin-left: 0;
    }
}

/* ═══ FEEDBACK BANNER GLASSMORPHISM & CTA STYLING ═══ */
.ld-feedback-banner {
    position: relative;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(254, 243, 199, 0.6) 100%);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(245, 158, 11, 0.35);
    border-radius: 18px;
    padding: 18px 24px;
    margin-bottom: 20px;
    box-shadow:
        0 12px 32px rgba(245, 158, 11, 0.15),
        0 2px 8px rgba(0, 0, 0, 0.02);
    overflow: hidden;
    transition: all 0.3s ease;
}

.ld-feedback-banner--wajib {
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.95) 0%, rgba(209, 250, 229, 0.7) 100%);
    border-color: rgba(16, 185, 129, 0.4);
    box-shadow: 0 12px 32px rgba(16, 185, 129, 0.15);
}

.ld-feedback-banner__glow {
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(245, 158, 11, 0.25) 0%, transparent 70%);
    pointer-events: none;
}

.ld-feedback-banner__content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
}

.ld-feedback-banner__left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1 1 320px;
}

.ld-feedback-banner__icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #f59e0b 0%, #f43f5e 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
    box-shadow: 0 6px 18px rgba(245, 158, 11, 0.35);
}

.ld-feedback-banner__title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px;
    line-height: 1.3;
}

.ld-feedback-banner__sub {
    font-size: 0.85rem;
    color: #475569;
    margin: 0;
    line-height: 1.45;
}

.ld-feedback-banner__actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.ld-feedback-banner__btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 800;
    padding: 10px 20px;
    border-radius: 12px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    text-decoration: none;
    cursor: pointer;
}

.ld-feedback-banner__btn--fill {
    background: linear-gradient(135deg, #f59e0b 0%, #f43f5e 100%);
    color: #ffffff !important;
    border: none;
    box-shadow: 0 8px 20px rgba(245, 158, 11, 0.35);
    animation: fb-cta-pulse 2.2s ease-in-out infinite;
}

.ld-feedback-banner__btn--fill:hover {
    transform: translateY(-2px) scale(1.02);
    background: linear-gradient(135deg, #fbbf24 0%, #e11d48 100%);
    box-shadow: 0 12px 28px rgba(244, 63, 94, 0.45);
}

.ld-feedback-banner__btn--ghost {
    background: rgba(255, 255, 255, 0.8);
    color: #64748b;
    border: 1px solid #cbd5e1;
}

.ld-feedback-banner__btn--ghost:hover {
    background: #ffffff;
    color: #0f172a;
    border-color: #94a3b8;
}

/* PONSEL — toast sudut melebar penuh. Pada 360px, lebar sudut hanya menyisakan
   ruang teks selebar dua kata dan pesan panjang terpotong jadi banyak baris
   sempit. Lihat --wca-z-toast di evo-theme.css untuk lapisannya. */
@media (max-width: 560px) {
    .ld-toast {
        left: 12px;
        right: 12px;
        max-width: none;
        align-items: flex-start;
    }
    .ld-toast .bi { flex: none; margin-top: 1px; }
}
.ld-proses {
    display: flex;
    gap: 0.85rem;
    align-items: flex-start;
    margin: 0 0 1rem;
    padding: 0.95rem 1.1rem;
    border-radius: 14px;
    border: 1px solid rgba(99, 102, 241, 0.25);
    background: rgba(99, 102, 241, 0.07);
    color: #3730a3;
}
.ld-proses i {
    font-size: 1.25rem;
    line-height: 1.2;
}
.ld-proses div {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.9rem;
}
.ld-proses span {
    color: #4b5563;
}
</style>
