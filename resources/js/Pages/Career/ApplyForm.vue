<!-- WEB CAREER — Kandidat: Formulir Lamaran. Memakai CareerLayout (navbar+footer+bg) seperti landing. -->
<template>
    <Head :title="`Lamar — ${flow.lowongan.posisi}`" />
    <CareerLayout :has-mt="hasMt" :offices="offices">
        <main class="wc-af">
            <div class="wc-af__inner">
                <a class="wc-af__back" href="/"><i class="bi bi-arrow-left"></i> Kembali ke Karir</a>

                <header class="wc-af__hero">
                    <span class="wc-eyebrow"
                        ><span class="wc-dot"></span>
                        {{ flow.form === 2 ? 'Formulir Tahap Lanjut · Form 2' : 'Formulir Lamaran' }}</span
                    >
                    <h1 class="wc-af__title">
                        {{ flow.lowongan.posisi }}
                        <span class="wc-af__cat" :class="flow.lowongan.kategori === 'MT' ? 'is-mt' : 'is-rek'">{{
                            flow.lowongan.kategori === 'MT' ? 'Management Trainee' : 'Rekrutmen'
                        }}</span>
                    </h1>
                    <p class="wc-af__meta">
                        <i class="bi bi-building"></i> {{ flow.lowongan.program }} <span class="wc-af__sep">·</span>
                        <i class="bi bi-geo-alt"></i> {{ flow.lowongan.lokasi }}
                    </p>
                </header>

                <!-- Sedang diproses (queue) — JANGAN klaim hasil sebelum server pasti -->
                <div v-if="memproses" class="wca-apply__done wca-apply__proc">
                    <div class="wca-apply__spin"><span class="wca-spinner"></span></div>
                    <h2>Memproses Lamaran…</h2>
                    <p>
                        Kami sedang mengunggah berkas &amp; memverifikasi data Anda untuk posisi
                        <b>{{ flow.lowongan.posisi }}</b
                        >. Mohon tunggu sebentar, jangan tutup halaman ini.
                    </p>
                </div>

                <!-- Hasil NYATA dari server (desain "Halaman Hasil Seleksi") -->
                <div v-else-if="done" class="wc-result">
                    <div class="wc-result__card" :class="'is-' + hasilStatus">
                        <div class="wc-result__strip"></div>
                        <div class="wc-result__inner">
                            <div class="wc-result__iconwrap">
                                <span class="wc-result__ring"></span>
                                <span class="wc-result__ring wc-result__ring--b"></span>
                                <div class="wc-result__badge">
                                    <i
                                        class="bi"
                                        :class="
                                            hasilStatus === 'lolos'
                                                ? 'bi-check-lg'
                                                : hasilStatus === 'pending'
                                                  ? 'bi-hourglass-split'
                                                  : 'bi-x-lg'
                                        "
                                    ></i>
                                </div>
                            </div>

                            <template v-if="hasilStatus === 'lolos'">
                                <h2 class="wc-result__title">Lolos Seleksi Administrasi! 🎉</h2>
                                <p class="wc-result__desc">
                                    Selamat, lamaran <b>{{ flow.lowongan.posisi }}</b> lolos seleksi administrasi secara
                                    otomatis<template v-if="hasilServer && hasilServer.totalTahap">
                                        dan lanjut ke
                                        <b style="color: #059669"
                                            >tahap {{ hasilServer.tahap }} dari {{ hasilServer.totalTahap }}</b
                                        ></template
                                    >. Pantau &amp; kerjakan tahap berikutnya di <b>Lamaran Saya</b>.
                                </p>
                            </template>
                            <template v-else-if="hasilStatus === 'pending'">
                                <h2 class="wc-result__title">Lamaran Sedang Ditinjau</h2>
                                <p class="wc-result__desc">
                                    Terima kasih telah melamar <b>{{ flow.lowongan.posisi }}</b
                                    >. Lamaran Anda sedang <b style="color: #b45309">dalam proses peninjauan</b> oleh
                                    tim rekrutmen kami. Hasilnya akan diinformasikan melalui portal &amp; email.
                                </p>
                            </template>
                            <template v-else>
                                <h2 class="wc-result__title">Belum Memenuhi Syarat</h2>
                                <p class="wc-result__desc">
                                    Terima kasih telah melamar <b>{{ flow.lowongan.posisi }}</b
                                    >. Setelah kami tinjau, untuk kesempatan kali ini Anda
                                    <b style="color: #dc2626">belum memenuhi kualifikasi</b
                                    ><template v-if="doneKo.length"
                                        >: <b>{{ doneKo.join(' · ') }}</b></template
                                    >. Kami menghargai minat &amp; waktu Anda — dan Anda tetap dapat melamar posisi lain
                                    yang sesuai.
                                </p>
                            </template>

                            <div v-if="hasilStatus !== 'gagal'" class="wc-result__prog">
                                <div class="wc-result__segs">
                                    <span v-for="n in 5" :key="n" class="wc-result__seg" :class="segClass(n)"></span>
                                </div>
                                <div class="wc-result__proglbl">{{ progressLabel }}</div>
                            </div>

                            <div class="wc-result__cta">
                                <a href="/kandidat/portal" class="wc-btn-grad"
                                    ><i class="bi bi-list-check"></i> Ke Lamaran Saya</a
                                >
                                <a v-if="hasilStatus === 'gagal'" href="/" class="wc-btn-outline"
                                    >Lihat Lowongan Lain</a
                                >
                            </div>

                            <div v-if="hasilStatus === 'lolos'" class="wc-result__confetti" aria-hidden="true">
                                <span v-for="n in 10" :key="n" :style="confettiStyle(n)"></span>
                            </div>
                        </div>
                    </div>

                    <div class="wc-result__safety">
                        <i class="bi bi-shield-check"></i>
                        <span
                            ><b>EVO Group tidak pernah memungut biaya apa pun</b> dalam proses rekrutmen. Waspadai pihak
                            yang meminta uang mengatasnamakan kami.</span
                        >
                    </div>
                </div>

                <!-- Sudah melamar (read-only) / tidak layak menurut aturan jalur -->
                <div v-else-if="terkunci" class="wca-apply__done" :class="{ 'is-fail': !sudahLamar }">
                    <div class="wca-apply__doneic">
                        <i class="bi" :class="sudahLamar ? 'bi-clipboard-check-fill' : 'bi-shield-lock-fill'"></i>
                    </div>
                    <template v-if="sudahLamar">
                        <h2>Kamu Sudah Melamar Posisi Ini</h2>
                        <p>
                            Lamaran untuk <b>{{ flow.lowongan.posisi }}</b> sudah tercatat — kamu tidak perlu mengisi
                            ulang formulir.
                        </p>
                        <p style="margin-top: 0.6rem; color: #64748b; font-size: 0.9rem; font-weight: 500">
                            Nomor Pendaftaran: <b style="color: #4f46e5">{{ sudahLamar.kode }}</b> · Status:
                            <b>{{ statusLabelId(sudahLamar.status) }}</b
                            ><template v-if="sudahLamar.tanggal">
                                · Melamar: <b>{{ sudahLamar.tanggal }}</b></template
                            >
                        </p>
                        <a href="/kandidat/portal" class="wca-btn wca-btn--primary"
                            ><i class="bi bi-list-check"></i> Ke Lamaran Saya</a
                        >
                    </template>
                    <template v-else>
                        <h2>Belum Dapat Melamar</h2>
                        <p>{{ kelayakan.alasan }}</p>
                        <a href="/" class="wca-btn wca-btn--primary"
                            ><i class="bi bi-search"></i> Lihat Lowongan Lain</a
                        >
                    </template>
                </div>

                <template v-else>
                    <div v-if="formulirDinamis" class="wca-apply__card">
                        <div class="wca-apply__head">
                            <span class="wca-apply__stepic"><i class="bi bi-ui-checks"></i></span>
                            <div>
                                <span class="wca-apply__stepno">Formulir dinamis</span>
                                <h3>{{ flow.formulir?.nama || 'Formulir Lamaran' }}</h3>
                                <p>Lengkapi data berikut untuk melamar posisi ini.</p>
                            </div>
                        </div>
                        <DynamicForm
                            :model-value="dynamicJawaban"
                            @update:model-value="(v) => Object.assign(dynamicJawaban, v)"
                            :skema="flow.formulir.schema"
                            :judul="flow.formulir?.nama || 'Formulir Lamaran'"
                            :keterangan="'Lengkapi data berikut untuk melamar posisi ini.'"
                            :disabled="mengirim"
                            :langkah-awal="drafLangkahAwal"
                            :konteks="konteksApply"
                            label-kirim="Finalisasi & Kirim"
                            @berkas="onDynamicBerkas"
                            @hapus-baris="onDynamicHapusBaris"
                            @kirim="finalizeDynamic"
                            @pindah-langkah="simpanDrafLokal"
                        />
                        <div v-if="err" class="wca-note wca-note--danger" style="margin: 0.8rem 0 0">
                            <i class="bi bi-exclamation-triangle-fill"></i><span>{{ err }}</span>
                        </div>
                    </div>

                    <template v-else>
                        <!-- Stepper -->
                        <div class="wca-steps2">
                            <div
                                v-for="(s, i) in steps"
                                :key="s.key"
                                class="wca-steps2__it"
                                :class="{ done: i < step, cur: i === step }"
                            >
                                <span class="wca-steps2__dot"
                                    ><i v-if="i < step" class="bi bi-check-lg"></i
                                    ><i v-else class="bi" :class="s.ikon"></i
                                ></span>
                                <span class="wca-steps2__lbl">{{ stepShort(s.key) }}</span>
                            </div>
                        </div>

                        <!-- Kartu langkah -->
                        <div class="wca-apply__card">
                            <div class="wca-apply__head">
                                <span class="wca-apply__stepic"><i class="bi" :class="cur.ikon"></i></span>
                                <div>
                                    <span class="wca-apply__stepno">Langkah {{ step + 1 }} / {{ steps.length }}</span>
                                    <h3>
                                        {{ cur.judul }}
                                        <small v-if="cur.opsional" class="wca-apply__opt">· opsional</small>
                                    </h3>
                                    <p v-if="cur.deskripsi">{{ cur.deskripsi }}</p>
                                </div>
                            </div>

                            <!-- MULTI / repeater (mis. Pengalaman — bisa banyak) -->
                            <div v-if="cur.repeat" class="wca-multi">
                                <div v-for="(entry, ei) in multi[cur.key]" :key="ei" class="wca-multi__item">
                                    <div class="wca-multi__head">
                                        <span
                                            ><i class="bi" :class="cur.ikon"></i> {{ cur.itemLabel }} {{ ei + 1 }}</span
                                        >
                                        <button
                                            v-if="multi[cur.key].length > 1"
                                            type="button"
                                            class="wca-iconbtn wca-iconbtn--danger"
                                            title="Hapus"
                                            @click="removeEntry(cur, ei)"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <div class="wca-aform">
                                        <div
                                            v-for="f in cur.fields"
                                            :key="f.key"
                                            class="wca-aform__fld"
                                            :class="{ full: f.full }"
                                        >
                                            <template v-if="f.tipe === 'switch'">
                                                <label class="wca-switchrow"
                                                    ><el-switch v-model="entry[f.key]" @change="onSekarang(entry)" />
                                                    <span>{{ f.label }}</span></label
                                                >
                                            </template>
                                            <template v-else>
                                                <label class="wca-field-lbl">{{ f.label }}</label>
                                                <el-date-picker
                                                    v-if="f.tipe === 'date'"
                                                    v-model="entry[f.key]"
                                                    type="date"
                                                    value-format="YYYY-MM-DD"
                                                    placeholder="Pilih tanggal"
                                                    style="width: 100%"
                                                    :disabled="f.disableIf ? !!entry[f.disableIf] : false"
                                                />
                                                <el-input
                                                    v-else-if="f.tipe === 'textarea'"
                                                    v-model="entry[f.key]"
                                                    type="textarea"
                                                    :rows="3"
                                                    :placeholder="f.ph"
                                                />
                                                <el-input v-else v-model="entry[f.key]" :placeholder="f.ph" />
                                            </template>
                                        </div>
                                    </div>
                                    <div v-if="entry.mulai" class="wca-multi__dur">
                                        <i class="bi bi-hourglass-split"></i> {{ durasiText(entry) }}
                                    </div>
                                </div>
                                <button type="button" class="wca-addrow" @click="addEntry(cur)">
                                    <i class="bi bi-plus-circle"></i> Tambah {{ cur.itemLabel }}
                                </button>
                            </div>

                            <!-- FORM (dukungan kondisional showIf + readonly/prefill) -->
                            <div v-else-if="cur.tipe === 'FORM'" class="wca-aform">
                                <template v-for="f in cur.fields" :key="f.key">
                                    <div v-if="showField(f)" class="wca-aform__fld" :class="{ full: f.full }">
                                        <label class="wca-field-lbl"
                                            >{{ f.label }} <span v-if="f.required" class="wca-req">*</span></label
                                        >
                                        <el-select
                                            v-if="f.tipe === 'select'"
                                            :model-value="form[f.key]"
                                            :placeholder="selectPh(f)"
                                            filterable
                                            clearable
                                            :remote="!!f.cari_async"
                                            :remote-method="f.cari_async ? (q) => loadApiOptions(f, q) : undefined"
                                            :allow-create="!!f.cari_async || !!f.boleh_ketik"
                                            :default-first-option="!!f.cari_async || !!f.boleh_ketik"
                                            :reserve-keyword="false"
                                            :loading="!!apiLoading[f.key]"
                                            :disabled="!fieldEnabled(f)"
                                            style="width: 100%"
                                            @update:model-value="(v) => onChangeField(f, v)"
                                            @visible-change="(vis) => onSelectOpen(f, vis)"
                                        >
                                            <template v-if="f.sumber_api">
                                                <el-option
                                                    v-for="o in apiOpts[f.key] || []"
                                                    :key="o.value"
                                                    :label="o.label"
                                                    :value="o.value"
                                                >
                                                    <span
                                                        style="
                                                            display: inline-flex;
                                                            align-items: center;
                                                            gap: 9px;
                                                            min-width: 0;
                                                        "
                                                    >
                                                        <img
                                                            v-if="o.flag"
                                                            :src="o.flag"
                                                            width="22"
                                                            height="16"
                                                            style="
                                                                border-radius: 2px;
                                                                flex: none;
                                                                object-fit: cover;
                                                                box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
                                                            "
                                                            alt=""
                                                            loading="lazy"
                                                        />
                                                        <span
                                                            style="
                                                                overflow: hidden;
                                                                text-overflow: ellipsis;
                                                                white-space: nowrap;
                                                            "
                                                            >{{ o.label }}</span
                                                        >
                                                    </span>
                                                </el-option>
                                            </template>
                                            <template v-else>
                                                <el-option v-for="o in f.opsi" :key="o" :label="o" :value="o" />
                                            </template>
                                        </el-select>
                                        <el-date-picker
                                            v-else-if="f.tipe === 'date'"
                                            v-model="form[f.key]"
                                            type="date"
                                            value-format="YYYY-MM-DD"
                                            placeholder="Pilih tanggal"
                                            style="width: 100%"
                                            :disabled="!fieldEnabled(f)"
                                        />
                                        <el-input
                                            v-else-if="f.tipe === 'textarea'"
                                            v-model="form[f.key]"
                                            type="textarea"
                                            :rows="2"
                                            :placeholder="fieldPh(f)"
                                            :disabled="f.readonly || !fieldEnabled(f)"
                                        />
                                        <el-input-number
                                            v-else-if="f.tipe === 'number'"
                                            v-model="form[f.key]"
                                            :min="0"
                                            :max="f.key === 'ipk' ? 4 : undefined"
                                            :precision="f.key === 'ipk' ? 2 : 0"
                                            :step="f.key === 'ipk' ? 0.05 : 1"
                                            controls-position="right"
                                            :placeholder="fieldPh(f)"
                                            :disabled="!fieldEnabled(f)"
                                            style="width: 100%"
                                        />
                                        <!-- Telepon dengan pemilih KODE NEGARA (default Indonesia +62, bisa dicari). -->
                                        <TeleponNegara
                                            v-else-if="f.tipe === 'phone'"
                                            :model-value="form[f.key]"
                                            :disabled="!fieldEnabled(f)"
                                            :placeholder="f.ph || '81234567890'"
                                            @update:model-value="(v) => (form[f.key] = v)"
                                        />
                                        <el-input
                                            v-else
                                            v-model="form[f.key]"
                                            :placeholder="fieldPh(f)"
                                            :disabled="f.readonly || !fieldEnabled(f)"
                                        />
                                    </div>
                                </template>
                            </div>

                            <!-- UPLOAD (batasi tipe file + pratinjau) -->
                            <div v-else-if="cur.tipe === 'UPLOAD'" class="wca-aup">
                                <div
                                    v-for="f in cur.files"
                                    :key="f.key"
                                    class="wca-aup__row"
                                    :class="{ ok: files[f.key] }"
                                >
                                    <span class="wca-aup__ic"
                                        ><i
                                            class="bi"
                                            :class="
                                                files[f.key]
                                                    ? 'bi-check-circle-fill'
                                                    : f.hint === 'PDF'
                                                      ? 'bi-file-earmark-pdf'
                                                      : 'bi-cloud-arrow-up'
                                            "
                                        ></i
                                    ></span>
                                    <span class="wca-aup__main">
                                        <strong
                                            >{{ f.label }} <span v-if="f.required" class="wca-req">*</span>
                                            <span class="wca-aup__fmt">{{ f.hint }}</span></strong
                                        >
                                        <small>{{
                                            files[f.key]
                                                ? files[f.key].name + ' · ' + fmtUkuran(files[f.key].size)
                                                : 'Belum ada berkas — hanya ' + f.hint + ' · maks 2 MB'
                                        }}</small>
                                    </span>
                                    <button
                                        v-if="files[f.key]"
                                        type="button"
                                        class="wca-btn wca-btn--soft wca-btn--sm"
                                        @click="showFile(files[f.key])"
                                    >
                                        <i class="bi bi-eye"></i> Lihat
                                    </button>
                                    <el-upload
                                        class="wca-aup__ep"
                                        :accept="f.accept"
                                        :auto-upload="false"
                                        :show-file-list="false"
                                        :on-change="(uf) => onFileEP(f.key, uf, f)"
                                    >
                                        <button type="button" class="wca-btn wca-btn--soft wca-btn--sm">
                                            <i class="bi" :class="files[f.key] ? 'bi-arrow-repeat' : 'bi-upload'"></i>
                                            {{ files[f.key] ? 'Ganti' : 'Unggah' }}
                                        </button>
                                    </el-upload>
                                </div>
                                <div v-if="uploadErr" class="wca-note wca-note--danger" style="margin: 0.6rem 0 0">
                                    <i class="bi bi-exclamation-triangle-fill"></i><span>{{ uploadErr }}</span>
                                </div>
                                <div class="wca-hint" style="margin: 0.5rem 0 0">
                                    <i class="bi bi-info-circle"></i> Tipe file dibatasi sesuai kolom (dokumen wajib
                                    <b>PDF</b>). Klik <b>Lihat</b> untuk pratinjau. Maks 2MB per berkas.
                                </div>
                            </div>

                            <!-- PERNYATAAN (el-checkbox) -->
                            <div v-else-if="cur.tipe === 'PERNYATAAN'" class="wca-astate">
                                <el-checkbox
                                    v-for="(it, i) in cur.items"
                                    :key="i"
                                    v-model="checks[i]"
                                    class="wca-chk"
                                    >{{ it }}</el-checkbox
                                >
                            </div>

                            <!-- FACE -->
                            <!-- FACE — komponen kamera yang SAMA dengan formulir dinamis
                                 (AmbilFoto): jeda pemanasan 6 detik, bingkai gelap
                                 ditolak, dan perangkat tanpa kamera mendapat kalimat
                                 yang jelas. Verifikasi wajah wajib — tidak ada jalan
                                 pintas unggah berkas. -->
                            <div v-else-if="cur.tipe === 'FACE'" class="wca-face">
                                <AmbilFoto
                                    :model-value="facePhoto || ''"
                                    @update:model-value="(v) => (facePhoto = v || null)"
                                />
                            </div>

                            <!-- REVIEW (tabs + timeline + berkas + consent) -->
                            <div v-else-if="cur.tipe === 'REVIEW'" class="wca-arev">
                                <div class="wca-arev__face">
                                    <img v-if="facePhoto" :src="facePhoto" alt="wajah" />
                                    <div v-else class="wca-face__sim wca-face__sim--sm">
                                        <i class="bi bi-person-badge"></i>
                                    </div>
                                    <div>
                                        <strong>{{ form.nama || flow.lowongan.posisi }}</strong
                                        ><small>{{ form.email || '—' }} · {{ form.hp || '—' }}</small>
                                    </div>
                                </div>

                                <el-tabs class="wca-arev__tabs">
                                    <el-tab-pane label="Data Pribadi">
                                        <div v-if="reviewItems.length" class="wca-arev__grid">
                                            <div v-for="r in reviewItems" :key="r.label">
                                                <small>{{ r.label }}</small
                                                ><b>{{ r.value }}</b>
                                            </div>
                                        </div>
                                        <div v-else class="wca-hint" style="margin: 0">
                                            <i class="bi bi-info-circle"></i> Belum ada data terisi.
                                        </div>
                                    </el-tab-pane>
                                    <el-tab-pane
                                        v-if="experiences.length"
                                        :label="`Pengalaman (${experiences.length})`"
                                    >
                                        <el-timeline class="wca-tl">
                                            <el-timeline-item
                                                v-for="(x, i) in experiences"
                                                :key="i"
                                                type="primary"
                                                hollow
                                                :timestamp="x.periode"
                                                placement="top"
                                            >
                                                <div class="wca-tl__card">
                                                    <strong>{{ x.posisi }}</strong>
                                                    <div class="wca-tl__co">
                                                        <i class="bi bi-building"></i> {{ x.perusahaan }}
                                                        <span class="wca-tl__dur"
                                                            ><i class="bi bi-hourglass-split"></i> {{ x.durasi }}</span
                                                        >
                                                    </div>
                                                    <p v-if="x.desc">{{ x.desc }}</p>
                                                </div>
                                            </el-timeline-item>
                                        </el-timeline>
                                    </el-tab-pane>
                                    <el-tab-pane v-if="fileCards.length" :label="`Berkas (${fileCards.length})`">
                                        <div class="wca-fgrid">
                                            <button
                                                v-for="fc in fileCards"
                                                :key="fc.key"
                                                type="button"
                                                class="wca-fcard"
                                                @click="showFile(fc)"
                                            >
                                                <span class="wca-fcard__ic" :class="{ pdf: fc.isPdf }"
                                                    ><i
                                                        class="bi"
                                                        :class="
                                                            fc.isPdf ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image'
                                                        "
                                                    ></i
                                                ></span>
                                                <span class="wca-fcard__nm">{{ fc.name }}</span>
                                                <span class="wca-fcard__view"><i class="bi bi-eye"></i> Pratinjau</span>
                                            </button>
                                        </div>
                                    </el-tab-pane>
                                </el-tabs>

                                <div class="wca-consent">
                                    <el-checkbox v-model="consent[0]" class="wca-chk"
                                        ><b>Kebenaran data.</b> Saya menyatakan seluruh data & dokumen yang saya isi
                                        benar dan dapat dipertanggungjawabkan.</el-checkbox
                                    >
                                    <el-checkbox v-model="consent[1]" class="wca-chk wca-chk--safe"
                                        ><b><i class="bi bi-shield-lock-fill"></i> Anti-penipuan.</b> Saya memahami
                                        bahwa
                                        <b>EVO Group TIDAK PERNAH memungut biaya / pungutan dalam bentuk apa pun</b>
                                        selama proses rekrutmen. Waspadai penipuan yang mengatasnamakan EVO
                                        Group.</el-checkbox
                                    >
                                </div>
                                <div v-if="!consentOk" class="wca-note wca-note--danger" style="margin: 0.5rem 0 0">
                                    <i class="bi bi-info-circle"></i
                                    ><span
                                        >Centang <b>kedua</b> pernyataan di atas untuk mengaktifkan tombol
                                        Finalisasi.</span
                                    >
                                </div>
                            </div>

                            <div v-if="err" class="wca-note wca-note--danger" style="margin: 0.8rem 0 0">
                                <i class="bi bi-exclamation-triangle-fill"></i><span>{{ err }}</span>
                            </div>
                        </div>

                        <!-- Footer aksi -->
                        <div class="wca-apply__foot">
                            <button class="wca-btn wca-btn--ghost" :disabled="step === 0" :onClick="step === 0 ? null : back">
                                <i class="bi bi-arrow-left"></i> Kembali
                            </button>
                            <button v-if="cur.tipe !== 'REVIEW'" class="wca-btn wca-btn--primary" @click="next">
                                Lanjut <i class="bi bi-arrow-right"></i>
                            </button>
                            <button
                                v-else
                                class="wca-btn wca-btn--dark"
                                :disabled="!consentOk || mengirim"
                                :onClick="!consentOk || mengirim ? null : finalize"
                            >
                                <i class="bi" :class="mengirim ? 'bi-hourglass-split' : 'bi-send-check'"></i>
                                {{ mengirim ? 'Mengirim…' : 'Finalisasi & Kirim' }}
                            </button>
                        </div>
                    </template>
                </template>
            </div>
        </main>
    </CareerLayout>

    <!-- Pratinjau berkas (viewer asli) -->
    <teleport to="body">
        <transition name="wca-toast">
            <div v-if="preview" class="wca-drawer-mask wca-drawer-mask--center wca" @click.self="preview = null">
                <div class="wca-filepv wca-filepv--lg">
                    <div class="wca-filepv__head">
                        <span
                            ><i class="bi" :class="preview.isPdf ? 'bi-file-earmark-pdf' : 'bi-image'"></i>
                            {{ preview.name }}</span
                        >
                        <button class="wca-drawer__close" @click="preview = null"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="wca-filepv__view">
                        <iframe v-if="preview.isPdf" :src="preview.url" title="Pratinjau PDF"></iframe>
                        <img v-else :src="preview.url" alt="Pratinjau" />
                    </div>
                    <div class="wca-filepv__foot">
                        <button class="wca-btn wca-btn--ghost" @click="preview = null">
                            <i class="bi bi-x-circle"></i> Tutup
                        </button>
                        <a class="wca-btn wca-btn--primary" :href="preview.url" :download="preview.name"
                            ><i class="bi bi-download"></i> Unduh</a
                        >
                    </div>
                </div>
            </div>
        </transition>
    </teleport>

    <transition name="wca-toast"
        ><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition
    >
</template>

<script setup>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import TeleponNegara from '@career/TeleponNegara.vue';
import DynamicForm from '@career/formulir/DynamicForm.vue';
import AmbilFoto from '@career/formulir/inti/AmbilFoto.vue';
import { jawabanAwal } from '@career/formulir';
import { berkasKurang } from '@utils/formulir/aturan';
import { kunciBerkas } from '@utils/formulir/berkasBaris';
import { normalisasiSkema } from '@utils/formulir/schema';
import {
    bacaDraf,
    hapusDraf,
    kunciDraf,
    petaSkema,
    sapuDrafKedaluwarsa,
    tulisDraf,
} from '@utils/formulir/drafLokal';
import { checkKnockout, clearApps, flowFor, getApp, nextActionFor, removeApp, upsertApp } from '@utils/career/session';

defineOptions({ layout: null }); // tanpa shell HCIS — pakai CareerLayout (situs karir)

const props = defineProps({
    flow: { type: Object, default: () => ({ lowongan: {}, steps: [] }) },
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
});
const steps = props.flow.steps;
const lowongan = props.flow.lowongan || {};
const jenis = lowongan.kategori === 'MT' ? 'MT' : 'REKRUTMEN';
const step = ref(0);
const cur = computed(() => steps[step.value] || {});
const done = ref(false);
const doneKo = ref([]); // alasan knock-out bila lamaran langsung tidak lolos
const memproses = ref(false); // loading saat server memproses lamaran (queue)
const mengirim = ref(false); // true sejak tombol Finalisasi ditekan → cegah double-submit

// Kelayakan jalur (MT/REKRUTMEN) + status sudah-melamar → form dikunci / read-only.
const sudahLamar = computed(() => props.flow.sudahLamar || null);
const kelayakan = computed(() => props.flow.kelayakan || { boleh: true, alasan: null });
const terkunci = computed(() => !!sudahLamar.value || kelayakan.value.boleh === false);
function statusLabelId(s) {
    return { BERJALAN: 'Sedang Diproses', GUGUR: 'Tidak Lolos', LULUS: 'Diterima', MENUNGGU: 'Menunggu' }[s] || s;
}

// ── Layar hasil (desain "Halaman Hasil Seleksi"): lolos | pending | gagal ──
const hasilStatus = computed(() => {
    if (doneKo.value.length) return 'gagal';
    if (hasilServer.value && hasilServer.value.status === 'DIPROSES') return 'pending';
    // Belum ada tahap yang diputus lolos → tahap pertamanya bermode manual dan
    // masih menunggu admin. Jangan ucapkan selamat atas kelulusan yang belum ada.
    if (hasilServer.value && hasilServer.value.menungguKeputusan) return 'pending';
    return 'lolos';
});
const progressLabel = computed(() => {
    const total = (hasilServer.value && hasilServer.value.totalTahap) || 5;
    if (hasilStatus.value === 'lolos') {
        const t = (hasilServer.value && hasilServer.value.tahap) || 2;
        return `Tahap ${t} dari ${total} · lanjut ke tahap berikutnya`;
    }
    const t = (hasilServer.value && hasilServer.value.tahap) || 1;
    const label = (hasilServer.value && hasilServer.value.tahapLabel) || 'peninjauan';
    return `Tahap ${t} dari ${total} · menunggu hasil ${label}`;
});
function segClass(n) {
    const t = (hasilServer.value && hasilServer.value.tahap) || (hasilStatus.value === 'lolos' ? 2 : 1);
    if (hasilStatus.value === 'lolos') {
        return n < t ? 'done' : n === t ? 'active' : 'idle';
    }
    return n < t ? 'done' : n === t ? 'active' : 'idle';
}
const CONFETTI = [
    ['12%', '#34d399', '2.4s', '.05s'],
    ['22%', '#6366f1', '2.7s', '.3s'],
    ['34%', '#f59e0b', '2.2s', '.15s'],
    ['46%', '#8b5cf6', '2.9s', '.4s'],
    ['56%', '#10b981', '2.5s', '.1s'],
    ['66%', '#6366f1', '2.6s', '.5s'],
    ['76%', '#f59e0b', '2.3s', '.22s'],
    ['86%', '#34d399', '2.8s', '.35s'],
    ['40%', '#a78bfa', '3s', '.6s'],
    ['60%', '#f59e0b', '2.4s', '.48s'],
];
function confettiStyle(n) {
    const c = CONFETTI[(n - 1) % CONFETTI.length];
    return {
        position: 'absolute',
        top: '0',
        left: c[0],
        width: '9px',
        height: n % 2 ? '14px' : '9px',
        borderRadius: n % 2 ? '2px' : '50%',
        background: c[1],
        animation: `wcConfetti ${c[2]} ${c[3]} ease-in forwards`,
    };
}
const hasilServer = ref(null); // hasil NYATA dari server: BERJALAN / GUGUR / DIPROSES
const err = ref('');
const preview = ref(null);
const uploadErr = ref('');

const form = reactive({});
const files = reactive({});
const formulirDinamis = computed(() => !!props.flow.formulir?.schema);

// ── Draf lokal formulir dinamis ──────────────────────────────────────────
// Lamaran baru lahir di database saat Kirim ditekan, jadi tidak ada tempat di
// server untuk isian setengah jadi. Draf ditaruh di localStorage peramban
// kandidat; lihat inti/drafLokal.js untuk alasan tiap penjaganya.
const skemaDinamis = props.flow.formulir?.schema || null;
// Bentuk yang dirender DynamicForm — kunci field & bagiannya yang dipakai jawaban.
const skemaNormal = skemaDinamis ? normalisasiSkema(skemaDinamis) : null;
const drafPeta = skemaDinamis ? petaSkema(skemaDinamis) : null;
const drafKunci = skemaDinamis
    ? kunciDraf({ identitas: props.flow.kandidat?.email || '', lowonganId: lowongan.id })
    : null;
// Dibaca SEKARANG, bukan di onMounted: Bertahap memasang langkah aktifnya
// sekali saat setup, jadi posisi yang dipulihkan belakangan akan diabaikan dan
// kandidat tetap dilempar balik ke langkah 1. localStorage sinkron, jadi aman
// dibaca di sini. Formulir yang sudah terkunci (sudah pernah melamar) tidak
// perlu dipulihkan sama sekali.
const drafAwal = drafKunci && !terkunci.value ? bacaDraf(drafKunci, drafPeta.tanda) : null;
const drafLangkahAwal = drafAwal?.langkah || 0;
// Langkah yang sedang dibuka, dikabarkan Bertahap lewat `pindah-langkah`.
// Bukan ref: hanya dipakai saat menulis draf, tidak ada tampilan yang bergantung.
let drafLangkahKini = drafLangkahAwal;

// `posisi` disisipkan ke profil supaya field ber-`prefill: 'posisi'` (mis.
// "Jabatan yang Dilamar") terisi otomatis dari lowongan yang sedang dilamar —
// dikunci, karena jabatannya sudah pasti sesuai lowongan ini, tidak perlu
// (dan tidak boleh) diketik ulang oleh kandidat.
//
// Draf ditumpuk DI ATAS nilai awal, bukan menggantikannya: field terkunci dan
// berkas sengaja tidak ikut tersimpan, jadi kuncinya tidak ada di draf dan
// nilai segar dari akun tetap yang dipakai.
const dynamicJawaban = reactive(
    skemaDinamis
        ? {
              ...jawabanAwal(skemaDinamis, { ...(props.flow.kandidat || {}), posisi: lowongan.posisi || '' }),
              ...(drafAwal?.jawaban || {}),
          }
        : {},
);
// Berkas yang dipilih, berkunci KOMPOSIT (bagian, baris, field) — lihat
// onDynamicBerkas(). Nilainya membawa bagian/baris/field-nya sendiri supaya
// berkas baris berulang terkirim ke barisnya, bukan runtuh jadi satu.
const dynamicFiles = reactive({});
// Isian berkas yang tertinggal/ditolak → pesan yang MENETAP di bawah kotak
// unggahnya (FieldRenderer membacanya lewat konteks.berkasGagal).
const dynamicGagal = reactive({});
const konteksApply = { berkasGagal: dynamicGagal };
// Cloud Run menolak request di atas 32 MiB sebelum sampai ke aplikasi, dan
// seluruh berkas lamaran naik dalam SATU request. Diperiksa di sini supaya
// kandidat mendapat penjelasan, bukan "Gagal mengirim lamaran" tanpa sebab.
const MAKS_TOTAL_BERKAS = 30 * 1024 * 1024;

/**
 * Simpan draf.
 *
 * Tidak menulis lagi begitu lamaran sedang dikirim atau sudah selesai: sumber
 * kebenarannya berpindah ke database, dan menulis ulang di sela-sela itu bisa
 * menghidupkan kembali draf yang baru saja dibersihkan.
 */
function simpanDrafLokal(langkah) {
    if (typeof langkah === 'number') drafLangkahKini = langkah;
    if (!drafKunci || mengirim.value || memproses.value || done.value || terkunci.value) return;
    tulisDraf(drafKunci, { jawaban: dynamicJawaban, langkah: drafLangkahKini, peta: drafPeta });
}

let drafTunda = null;

/** Buang draf beserta tulisan tertunda yang masih mengantre. */
function bersihkanDrafLokal() {
    clearTimeout(drafTunda);
    hapusDraf(drafKunci);
}

// Menulis pada TIAP ketukan huruf berarti menyerialkan seluruh jawaban puluhan
// kali per detik. Ditunda sebentar supaya mengetik tetap ringan, tapi tetap
// tersimpan tanpa menunggu kandidat menekan "Lanjut" — justru refresh di
// tengah langkah yang paling sering menghapus pekerjaan orang.
watch(
    dynamicJawaban,
    () => {
        clearTimeout(drafTunda);
        drafTunda = setTimeout(simpanDrafLokal, 600);
    },
    { deep: true },
);

/**
 * Tulis sisa yang masih tertunda saat halaman ditinggalkan.
 *
 * Tanpa ini, kalimat terakhir yang diketik kandidat sebelum menutup tab hilang
 * bersama timer yang belum sempat berbunyi — persis momen yang paling sering
 * terjadi, karena orang menutup tab justru setelah mengetik sesuatu.
 * `pagehide`, bukan `beforeunload`: yang terakhir tidak dipanggil di Safari iOS
 * saat tab dipindah ke latar.
 */
function tuntaskanDraf() {
    clearTimeout(drafTunda);
    simpanDrafLokal();
}
if (drafKunci) window.addEventListener('pagehide', tuntaskanDraf);
onBeforeUnmount(() => {
    clearTimeout(drafTunda);
    window.removeEventListener('pagehide', tuntaskanDraf);
});
// ── Cascade pendidikan (Jenjang → Jenis Institusi → Nama Kampus) ──
const apiOpts = reactive({}); // key field → [{ value, label, meta? }]
const apiLoading = reactive({});
const PDK = { headers: { Accept: 'application/json' } };
const checks = reactive([]);
const fileList = computed(() =>
    Object.values(files)
        .filter(Boolean)
        .map((v) => v.name),
);

// Persetujuan finalisasi (2 centang wajib: kebenaran data + anti-penipuan)
const consent = reactive([false, false]);
const consentOk = computed(() => consent[0] && consent[1]);

// ── Pengalaman: tanggal → durasi ──
const BULAN_ID = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmtId(iso) {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${parseInt(d)} ${BULAN_ID[parseInt(m) - 1]} ${y}`;
}
function periodeText(e) {
    return `${fmtId(e.mulai)} – ${e.sekarang ? 'Sekarang' : fmtId(e.selesai)}`;
}
function durasiText(e) {
    if (!e.mulai) return '—';
    const start = new Date(e.mulai + 'T00:00:00');
    const end = e.sekarang ? new Date() : e.selesai ? new Date(e.selesai + 'T00:00:00') : null;
    if (!end) return 'Pilih tanggal selesai atau centang "sampai sekarang"';
    if (end < start) return 'Tanggal tidak valid (selesai sebelum mulai)';
    const days = Math.floor((end - start) / 86400000) + 1;
    let years = end.getFullYear() - start.getFullYear();
    let months = end.getMonth() - start.getMonth();
    if (end.getDate() < start.getDate()) months--;
    if (months < 0) {
        years--;
        months += 12;
    }
    const parts = [];
    if (years > 0) parts.push(years + ' tahun');
    if (months > 0) parts.push(months + ' bulan');
    if (!parts.length) parts.push(days + ' hari');
    return `${parts.join(' ')} · ${days} hari${e.sekarang ? ' (berjalan)' : ''}`;
}
function onSekarang(e) {
    if (e.sekarang) e.selesai = '';
}

const experiences = computed(() => {
    const arr = multi.KERJA || [];
    return arr
        .filter((e) => e.perusahaan || e.posisiKerja || e.deskripsiKerja)
        .map((e) => ({
            posisi: e.posisiKerja || '(Posisi belum diisi)',
            perusahaan: e.perusahaan || '—',
            periode: periodeText(e),
            durasi: durasiText(e),
            desc: e.deskripsiKerja,
        }));
});
const fileCards = computed(() =>
    Object.entries(files)
        .filter(([, v]) => v)
        .map(([k, v]) => ({ key: k, ...v })),
);

// Step repeater (mis. Pengalaman) → array entri per step.key
const multi = reactive({});
function emptyEntry(s) {
    return Object.fromEntries((s.fields || []).map((f) => [f.key, '']));
}
steps.forEach((s) => {
    if (s.repeat) multi[s.key] = [emptyEntry(s)];
});
function addEntry(s) {
    multi[s.key].push(emptyEntry(s));
}
function removeEntry(s, i) {
    if (multi[s.key].length > 1) multi[s.key].splice(i, 1);
}

function stepShort(k) {
    return (
        {
            DIRI: 'Data Diri',
            DIDIK: 'Pendidikan',
            KERJA: 'Pengalaman',
            BERKAS: 'Berkas',
            SEDIA: 'Pernyataan',
            VALIDASI: 'Validasi',
            IDENTITAS: 'Identitas',
            DARURAT: 'Kontak',
            KESIAPAN: 'Kesiapan',
            DOKUMEN: 'Dokumen',
            PERSETUJUAN: 'Persetujuan',
            FACE: 'Verifikasi',
            REVIEW: 'Finalisasi',
        }[k] || k
    );
}
// showIf → SEMBUNYIKAN field (mis. semester hanya untuk Mahasiswa).
function showField(f) {
    return !f.showIf || form[f.showIf.key] === f.showIf.value;
}
// tergantung → field cascade TETAP TAMPIL tapi DISABLED (tak bisa diklik)
// sampai induknya terisi; placeholder memberi tahu harus mengisi apa dulu.
function fieldEnabled(f) {
    return !(f.tergantung && !form[f.tergantung]);
}
function tergantungPh(f) {
    return (
        {
            jenjang: 'Pilih jenjang pendidikan dulu',
            institusi: 'Pilih jenis institusi dulu',
            kampus: 'Pilih nama kampus / sekolah dulu',
        }[f.tergantung] || 'Lengkapi isian sebelumnya'
    );
}
function selectPh(f) {
    if (f.tergantung && !form[f.tergantung]) return tergantungPh(f);
    return 'Pilih ' + f.label;
}
function fieldPh(f) {
    if (f.tergantung && !form[f.tergantung]) return tergantungPh(f);
    return f.ph || '';
}

// URL bendera negara (gambar asli — emoji bendera tak tampil di Windows/Chrome).
function benderaUrl(code) {
    return code ? `https://flagcdn.com/24x18/${String(code).toLowerCase()}.png` : '';
}

// Muat opsi field ber-sumber_api (jenjang/jenis) atau pencarian async (kampus).
async function loadApiOptions(f, q = '') {
    const dep = f.tergantung ? form[f.tergantung] || '' : '';
    try {
        if (f.sumber_api === 'jenjang') {
            const r = await axios.get('/api/v1/pendidikan/jenjang', PDK);
            apiOpts[f.key] = (r.data.result || []).map((o) => ({ value: o.kode, label: o.nama }));
        } else if (f.sumber_api === 'jenis_institusi') {
            if (!dep) {
                apiOpts[f.key] = [];
                return;
            }
            const r = await axios.get('/api/v1/pendidikan/jenis-institusi', { ...PDK, params: { jenjang: dep } });
            apiOpts[f.key] = (r.data.result || []).map((o) => ({ value: o.kode, label: o.nama }));
        } else if (f.sumber_api === 'kampus') {
            if (!dep) {
                apiOpts[f.key] = [];
                return;
            }
            apiLoading[f.key] = true;
            const r = await axios.get('/api/v1/pendidikan/kampus', {
                ...PDK,
                params: { jenis: dep, q: q || '', limit: 30 },
            });
            apiOpts[f.key] = (r.data.result || []).map((o) => ({
                value: o.value,
                label: o.label,
                flag: benderaUrl(o.negaraKode),
            }));
        } else if (f.sumber_api === 'fakultas') {
            // Fakultas/rumpun yang tersedia di kampus + jenjang terpilih.
            if (!dep) {
                apiOpts[f.key] = [];
                return;
            }
            apiLoading[f.key] = true;
            const r = await axios.get('/api/v1/pendidikan/fakultas', {
                ...PDK,
                params: { kampus: dep, jenjang: form.jenjang || '' },
            });
            // Cukup namanya — jumlah prodi tidak menolong pelamar memilih.
            apiOpts[f.key] = (r.data.result || []).map((o) => ({ value: o.value, label: o.nama }));
        } else if (f.sumber_api === 'prodi') {
            if (!dep) {
                apiOpts[f.key] = [];
                return;
            }
            apiLoading[f.key] = true;
            const r = await axios.get('/api/v1/pendidikan/prodi', {
                ...PDK,
                params: {
                    kampus: dep,
                    jenjang: form.jenjang || '',
                    // Dipersempit fakultas yang dipilih; kalau diketik bebas dan
                    // tak dikenali, server mengabaikannya (semua prodi tampil).
                    bidang: f.saring_dari ? form[f.saring_dari] || '' : '',
                    q: q || '',
                    limit: 30,
                },
            });
            // Nama prodi saja, tanpa gelar — yang tersimpan pun namanya.
            apiOpts[f.key] = (r.data.result || []).map((o) => ({ value: o.value, label: o.label }));
        }
    } catch (e) {
        apiOpts[f.key] = apiOpts[f.key] || [];
    } finally {
        apiLoading[f.key] = false;
    }
}
// Reset semua anak (rekursif) saat induk berubah → rantai jenjang→jenis→kampus konsisten.
function resetChildren(parentKey) {
    (cur.value.fields || []).forEach((g) => {
        if (g.tergantung === parentKey) {
            form[g.key] = g.tipe === 'number' ? null : '';
            if (g.sumber_api) apiOpts[g.key] = [];
            resetChildren(g.key);
        }
    });
}
function onChangeField(f, value) {
    // Nilai ketik-bebas (allow-create kampus) dirapikan spasinya.
    form[f.key] = typeof value === 'string' ? value.trim() : value;
    resetChildren(f.key);
    (cur.value.fields || []).forEach((g) => {
        // Anak langsung: opsinya dimuat ulang mengikuti induk baru.
        if (g.tergantung === f.key && g.sumber_api) loadApiOptions(g);
        // Field yang DIPERSEMPIT oleh field ini (mis. prodi disaring fakultas).
        // Pilihan lama dikosongkan: prodi dari fakultas sebelumnya tidak lagi
        // masuk akal setelah fakultasnya diganti.
        if (g.saring_dari === f.key && g.sumber_api) {
            form[g.key] = '';
            loadApiOptions(g);
        }
    });
    persistDraft();
}
function onSelectOpen(f, vis) {
    if (!vis || !f.sumber_api || (apiOpts[f.key] || []).length) return;
    // Dimuat saat dropdown dibuka supaya tidak memanggil API sebelum diperlukan.
    if (['kampus', 'fakultas', 'prodi'].includes(f.sumber_api) && form[f.tergantung]) loadApiOptions(f, '');
}

// Telepon Indonesia: SELALU berawalan 62. 08.. -> 628.. ; 8.. -> 628.. ;
// 62/ +62 tetap ; 620.. -> 62.. (buang 0 setelah 62). Disimpan '628xxxxxxxxx'.
function normalTelepon(raw) {
    let s = String(raw ?? '').replace(/\D/g, '');
    if (!s) return '';
    if (s.startsWith('620')) s = '62' + s.slice(3);
    else if (s.startsWith('62')) s = s;
    else if (s.startsWith('0')) s = '62' + s.slice(1);
    else s = '62' + s;
    return s;
}

// ── Verifikasi wajah ──
// Kameranya milik AmbilFoto: ia menyala, memanaskan, menilai bingkai, dan
// mematikan diri saat langkah FACE ditinggalkan (komponennya dilepas).
const facePhoto = ref(null);

// ── Navigasi & validasi ──
function validateStep() {
    err.value = '';
    const s = cur.value;
    if (s.tipe === 'FACE' && !facePhoto.value) {
        err.value = 'Verifikasi wajah wajib: ambil foto langsung dari kamera untuk melanjutkan. Tanpa kamera, pendaftaran tidak bisa dilanjutkan — gunakan perangkat yang memiliki kamera.';
        return false;
    }
    if (s.tipe === 'FORM' && !s.opsional) {
        const miss = (s.fields || []).filter((f) => f.required && showField(f) && fieldEnabled(f) && !form[f.key]);
        if (miss.length) {
            err.value = `Lengkapi: ${miss.map((f) => f.label).join(', ')}.`;
            return false;
        }
    }
    if (s.tipe === 'UPLOAD') {
        const miss = (s.files || []).filter((f) => f.required && !files[f.key]);
        if (miss.length) {
            err.value = `Unggah dulu: ${miss.map((f) => f.label).join(', ')}.`;
            return false;
        }
    }
    if (s.tipe === 'PERNYATAAN') {
        const ok = s.items.every((_, i) => checks[i]);
        if (!ok) {
            err.value = `Centang semua ${s.items.length} pernyataan untuk melanjutkan.`;
            return false;
        }
    }
    return true;
}
function next() {
    if (validateStep() && step.value < steps.length - 1) {
        step.value++;
        err.value = '';
        persistDraft();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}
function back() {
    if (step.value > 0) {
        step.value--;
        err.value = '';
        persistDraft();
    }
}
function onDynamicBerkas(e) {
    if (!e?.field?.key) return;
    // Kunci KOMPOSIT. Dulu hanya field.key: tiga sertifikat di bagian berulang
    // memakai key yang sama persis, sehingga hanya yang terakhir dipilih yang
    // ikut terkirim — dua lainnya tinggal nama di jawaban.
    const bagian = e.bagian ?? null;
    const baris = e.baris ?? null;
    const kunci = kunciBerkas(bagian, baris, e.field.key);
    if (e.hapus) {
        delete dynamicFiles[kunci];
        delete dynamicGagal[kunci];
        return;
    }
    if (e.galat) {
        uploadErr.value = e.galat;
        notice(e.galat);
        return;
    }
    if (e.file) {
        dynamicFiles[kunci] = {
            raw: e.file,
            name: e.file.name,
            size: e.file.size,
            isPdf: /pdf$/i.test(e.file.name) || e.file.type === 'application/pdf',
            bagian,
            baris,
            field: e.field.key,
        };
        delete dynamicGagal[kunci];
    }
}

/**
 * Baris bagian berulang dihapus: berkasnya ikut dibuang dan indeks berkas di
 * atasnya diturunkan — sama dengan BerkasBaris::geser() di server. Dua fase
 * (kumpulkan dulu, baru pasang) supaya entri yang baru digeser tidak ikut
 * terhapus oleh entri lama yang kebetulan berkunci sama.
 */
function onDynamicHapusBaris(bagian, i) {
    const geser = [];
    Object.keys(dynamicFiles).forEach((k) => {
        const v = dynamicFiles[k];
        if (v?.bagian !== bagian || v.baris === null || v.baris === undefined) return;
        delete dynamicFiles[k];
        delete dynamicGagal[k];
        if (v.baris > i) geser.push({ ...v, baris: v.baris - 1 });
    });
    geser.forEach((v) => {
        dynamicFiles[kunciBerkas(bagian, v.baris, v.field)] = v;
    });
}

/** Tulis nilai isian berkas — di akar jawaban atau di baris bagian berulang. */
function aturIsianApply(bagian, baris, field, nilai) {
    if (bagian === null || bagian === undefined || baris === null || baris === undefined) {
        dynamicJawaban[field] = nilai;
        return;
    }
    const daftar = dynamicJawaban[bagian];
    if (Array.isArray(daftar) && daftar[baris] && typeof daftar[baris] === 'object') {
        daftar[baris][field] = nilai;
    }
}

/**
 * Isian yang berkasnya tidak ikut terkirim: namanya dikosongkan (kotak unggah
 * muncul lagi) dan pesannya menetap — di bawah kotak itu dan di bawah formulir.
 */
function tandaiBerkasKurang(daftar, pesan = null) {
    daftar.forEach((k) => {
        const bagian = k.bagian ?? null;
        const baris = k.baris ?? null;
        const kunci = kunciBerkas(bagian, baris, k.field);
        delete dynamicFiles[kunci];
        dynamicGagal[kunci] = 'Berkas ini belum terlampir — pilih (ulang) berkasnya.';
        aturIsianApply(bagian, baris, k.field, '');
    });
    const label = daftar.map((k) => k.label).join(', ');
    err.value = pesan || `Berkas berikut belum terlampir: ${label}. Pilih (ulang) berkasnya, lalu kirim kembali.`;
    notice(err.value, 8000);
}

async function finalizeDynamic(jawaban) {
    if (mengirim.value) return;
    mengirim.value = true;
    err.value = '';

    const ko = checkKnockout(jenis, jawaban || {}, props.flow.syarat);
    if (!lowongan.pembukaanId || !lowongan.posisiId) {
        mengirim.value = false;
        notice('Target lamaran tidak valid. Muat ulang halaman lalu coba kembali.');
        return;
    }

    // Berkas yang diwajibkan — atau yang namanya sudah tercatat di jawaban —
    // harus BENAR-BENAR ikut terkirim. Aturannya sama dengan server
    // (berkasKurang ↔ BerkasFormulir::kurang), yang kini menolak lamaran tanpa
    // berkasnya; di sini dicegah lebih dulu supaya kandidat tahu isian mana.
    const kurang = skemaNormal
        ? berkasKurang(skemaNormal, jawaban || {}, (b, i, f) => !!dynamicFiles[kunciBerkas(b, i, f)]?.raw)
        : [];
    if (kurang.length) {
        mengirim.value = false;
        tandaiBerkasKurang(kurang);
        return;
    }

    const total = Object.values(dynamicFiles).reduce((n, v) => n + (v?.raw?.size || 0), 0);
    if (total > MAKS_TOTAL_BERKAS) {
        mengirim.value = false;
        err.value = `Total ukuran berkas ${(total / 1048576).toFixed(1)} MB melebihi batas 30 MB per lamaran. Perkecil ukuran berkasnya lalu kirim kembali.`;
        notice(err.value, 8000);
        return;
    }

    try {
        const fd = new FormData();
        fd.append('pembukaanId', lowongan.pembukaanId);
        fd.append('posisiId', lowongan.posisiId);
        fd.append('gugur', ko.length ? 1 : 0);
        if (ko.length) fd.append('alasan', ko.join(' � '));
        fd.append('jawaban', JSON.stringify({ ...(jawaban || {}) }));
        // Berkas biasa: berkas[field]. Berkas baris berulang: baris[n][…]
        // dengan identitas barisnya sendiri — dulu semuanya berkas[field],
        // sehingga baris-baris dengan field sama saling menimpa.
        let n = 0;
        Object.values(dynamicFiles).forEach((v) => {
            if (!v?.raw) return;
            if (v.bagian !== null && v.bagian !== undefined && v.baris !== null && v.baris !== undefined) {
                fd.append(`baris[${n}][berkas]`, v.raw, v.name);
                fd.append(`baris[${n}][bagian]`, v.bagian);
                fd.append(`baris[${n}][baris]`, String(v.baris));
                fd.append(`baris[${n}][field]`, v.field);
                n++;
                return;
            }
            fd.append(`berkas[${v.field}]`, v.raw, v.name);
        });

        const res = await axios.post('/api/v1/lamaran', fd, { headers: { Accept: 'application/json' } });
        const procId = res.data?.result?.processId || null;
        if (procId) {
            memproses.value = true;
            pollStatus(procId);
        } else {
            mengirim.value = false;
            notice('Target lamaran tidak valid. Muat ulang halaman lalu coba kembali.');
        }
    } catch (e) {
        const st = e.response?.status;
        if (st === 401) {
            router.visit('/login');
            return;
        }
        mengirim.value = false;
        // Server menolak karena berkas yang disebut jawaban tidak ikut terkirim.
        const kurangServer = e.response?.data?.result?.berkasKurang;
        if (st === 422 && Array.isArray(kurangServer) && kurangServer.length) {
            tandaiBerkasKurang(kurangServer, e.response.data.message);
            return;
        }
        notice(e.response?.data?.message || 'Gagal mengirim lamaran ke sistem.');
    }
}
async function finalize() {
    if (mengirim.value) return; // sudah diproses → cegah klik ganda
    if (!consentOk.value) return;
    mengirim.value = true; // tombol langsung nonaktif sejak ditekan
    for (let i = 0; i < steps.length; i++) {
        step.value = i;
        if (!validateStep()) {
            mengirim.value = false;
            return;
        }
    }
    const isForm2 = props.flow.form === 2;
    // Form 2 wajib dikerjakan dari Detail Lamaran agar memiliki tahapId dan
    // tersimpan lewat endpoint formulir tahap. Guard ini mencegah sukses palsu
    // bila halaman legacy masih terbuka dari cache/tab lama.
    if (isForm2) {
        mengirim.value = false;
        notice('Formulir tahap lanjut harus dibuka dari Detail Lamaran.');
        router.visit('/kandidat/portal');
        return;
    }
    // Syarat dari Master Program (DB), bukan daftar tetap di careerSession.
    const ko = checkKnockout(jenis, form, props.flow.syarat);
    let procId = null; // id proses queue (untuk poll hasil nyata)

    // FINALISASI = benar-benar MENGAJUKAN lamaran ke sistem (DB), bukan sekadar
    // draf sessionStorage. Kalau knock-out (tidak lolos syarat wajib) lamaran
    // TETAP tercatat, tapi statusnya Tidak Lolos.
    if (lowongan.pembukaanId && lowongan.posisiId) {
        try {
            // MULTIPART: jawaban (JSON) + berkas (pdf/jpg ≤2MB) + foto verifikasi.
            // Diproses asinkron oleh server (queue wc-applyform) + unggah GCS.
            const fd = new FormData();
            fd.append('pembukaanId', lowongan.pembukaanId);
            fd.append('posisiId', lowongan.posisiId);
            fd.append('gugur', ko.length ? 1 : 0);
            if (ko.length) fd.append('alasan', ko.join(' · '));
            fd.append('jawaban', JSON.stringify({ ...form }));
            // Berkas dinamis: kirim tiap file yang diunggah kandidat.
            Object.entries(files).forEach(([key, v]) => {
                if (v && v.raw) fd.append(`berkas[${key}]`, v.raw, v.name);
            });
            // Foto verifikasi (dari kamera).
            if (facePhoto.value) fd.append('foto', dataUrlKeBlob(facePhoto.value), 'verifikasi.jpg');

            const res = await axios.post('/api/v1/lamaran', fd, { headers: { Accept: 'application/json' } });
            procId = res.data?.result?.processId || null;
        } catch (e) {
            const st = e.response?.status;
            if (st === 401) {
                router.visit('/login');
                return;
            }
            mengirim.value = false; // gagal → aktifkan lagi tombol agar bisa diulang
            notice(e.response?.data?.message || 'Gagal mengirim lamaran ke sistem.');
            return; // JANGAN tampilkan sukses palsu bila request gagal
        }
    }

    // Apply ke DB diproses ASINKRON (queue) → tampilkan loading, lalu POLL hasil NYATA
    // (lolos administrasi / gugur). Tidak menyatakan berhasil sebelum server memastikan.
    if (procId) {
        memproses.value = true;
        pollStatus(procId);
    } else {
        mengirim.value = false;
        notice('Target lamaran tidak valid. Muat ulang halaman lalu coba kembali.');
        return;
    }
    // Snapshot sessionStorage (legacy card) — tetap dibuat, tapi TAMPILAN akhir ikut server.
    const existing = getApp(lowongan.id);
    // Tahapan seleksi = Master Alur milik program ini (dikirim server).
    const pipeline = existing?.pipeline || flowFor(jenis, props.flow.pipeline);
    const base = {
        step: steps.length,
        pipeline,
        stageIdx: isForm2 ? Math.min((existing?.stageIdx ?? 2) + 1, pipeline.length - 1) : (existing?.stageIdx ?? 0),
        result: 'BERJALAN',
        stageStatus: 'BARU',
        appliedAt: existing?.appliedAt || new Date().toISOString().slice(0, 10),
        form2Done: isForm2 ? true : existing?.form2Done || false,
        bio: isForm2
            ? [...(existing?.bio || []), ...reviewItems.value.map((r) => ({ label: r.label, value: r.value }))]
            : reviewItems.value.map((r) => ({ label: r.label, value: r.value })),
        experiences: experiences.value.length ? experiences.value.map((x) => ({ ...x })) : existing?.experiences || [],
    };
    if (isForm2) {
        base.facePhoto = existing?.facePhoto || facePhoto.value || null;
        base.files = {
            ...(existing?.files || {}),
            ...Object.fromEntries(
                Object.entries(files)
                    .filter(([, v]) => v)
                    .map(([k, v]) => [k, { name: v.name, isPdf: v.isPdf }]),
            ),
        };
    }
    // Knock-out syarat wajib (mis. IPK < min) → langsung Tidak Lolos saat finalisasi.
    if (ko.length) {
        base.result = 'GAGAL';
        base.stageStatus = 'GUGUR';
        base.knockout = ko;
    }
    const finalApp = snapshot('FINAL', base);
    finalApp.nextAction = nextActionFor(finalApp);
    upsertApp(finalApp);
    // Untuk jalur DB (procId): jangan set doneKo di sini — hasil ditentukan server via poll.
    if (!procId) {
        doneKo.value = base.knockout || [];
    }
}

// Poll status pemrosesan lamaran (queue) → tampilkan HASIL NYATA dari server.
async function pollStatus(processId) {
    const mulai = Date.now();
    const CFG = { headers: { Accept: 'application/json' } };
    const tick = async () => {
        try {
            const res = await axios.get(`/api/v1/lamaran/apply-status/${processId}`, CFG);
            const r = res.data?.result;
            if (r?.status === 'SELESAI') {
                const lam = r.lamaran || {};
                hasilServer.value = lam;
                doneKo.value =
                    lam.status === 'GUGUR' ? [lam.alasanGugur || 'Belum memenuhi kualifikasi yang dibutuhkan'] : [];
                memproses.value = false;
                done.value = true;
                // Finalisasi tuntas → bersihkan draf lamaran ini (sumber
                // kebenaran = DB). done=true membuat persistDraft dan
                // simpanDrafLokal tak menulis ulang.
                removeApp(lowongan.id);
                bersihkanDrafLokal();
                return;
            }
            if (r?.status === 'GAGAL') {
                memproses.value = false;
                notice(r.pesan || 'Pemrosesan lamaran gagal. Silakan coba lagi.');
                return;
            }
        } catch (e) {
            /* diamkan, lanjut poll */
        }

        if (Date.now() - mulai > 45000) {
            // Timeout — JANGAN klaim lolos/gugur. Arahkan pantau di Lamaran Saya.
            memproses.value = false;
            hasilServer.value = { status: 'DIPROSES' };
            doneKo.value = [];
            done.value = true;
            // Lamarannya SUDAH masuk antrean server — yang lambat cuma
            // pemrosesannya. Draf dibuang supaya kandidat tidak dipancing
            // mengisi ulang dan mengirim lamaran kedua untuk posisi yang sama.
            removeApp(lowongan.id); // status dipantau via DB
            bersihkanDrafLokal();
            return;
        }
        setTimeout(tick, 1500);
    };
    tick();
}

// ── Sesi kandidat: draf tersimpan di sessionStorage, bisa dilanjutkan ──
function snapshot(status, extra = {}) {
    return {
        lowonganId: lowongan.id,
        posisi: lowongan.posisi,
        program: lowongan.program,
        lokasi: lowongan.lokasi,
        kategori: lowongan.kategori,
        jenis,
        form: props.flow.form,
        status,
        totalSteps: steps.length,
        step: step.value,
        form: { ...form },
        checks: [...checks],
        consent: [...consent],
        multi: JSON.parse(JSON.stringify(multi)),
        files: Object.fromEntries(
            Object.entries(files)
                .filter(([, v]) => v)
                .map(([k, v]) => [k, { name: v.name, isPdf: v.isPdf }]),
        ),
        facePhoto: facePhoto.value || null,
        ...extra,
    };
}
function persistDraft() {
    if (done.value || !lowongan.id) return;
    const cur0 = getApp(lowongan.id);
    if (cur0 && cur0.status === 'FINAL') return; // sudah final — jangan turunkan ke draf
    upsertApp(snapshot('DRAFT', { appliedAt: cur0?.appliedAt || null }));
}
function restore(a) {
    Object.assign(form, a.form || {});
    (a.checks || []).forEach((v, i) => {
        checks[i] = v;
    });
    (a.consent || []).forEach((v, i) => {
        consent[i] = v;
    });
    Object.entries(a.multi || {}).forEach(([k, v]) => {
        if (Array.isArray(v)) multi[k] = v;
    });
    Object.entries(a.files || {}).forEach(([k, v]) => {
        files[k] = { ...v, url: null, stale: true };
    });
    if (a.facePhoto) facePhoto.value = a.facePhoto;
    if (typeof a.step === 'number') step.value = Math.min(a.step, steps.length - 1);
}
function showFile(fc) {
    if (!fc) return;
    if (!fc.url) {
        notice('Berkas dari draf — unggah ulang untuk pratinjau.');
        return;
    }
    preview.value = fc;
}

onMounted(() => {
    // Draf lowongan LAIN yang sudah kedaluwarsa ikut dibuang. Kandidat yang
    // membuka lima lowongan lalu menuntaskan satu meninggalkan empat simpanan
    // berisi data pribadi yang tidak pernah dijenguk siapa pun lagi.
    sapuDrafKedaluwarsa();
    // Sudah pernah melamar posisi ini → drafnya tidak akan pernah dipakai.
    if (terkunci.value) bersihkanDrafLokal();
    if (drafAwal) {
        // Berkas & foto verifikasi sengaja TIDAK ikut tersimpan (isinya tak
        // muat di localStorage), jadi disebut terus terang di sini — kalau
        // tidak, kandidat baru tahu ada yang hilang saat tombol Kirim menolak.
        notice(
            drafPeta.punyaBerkas
                ? 'Melanjutkan isian yang tersimpan. Berkas & foto verifikasi perlu dilampirkan ulang.'
                : 'Melanjutkan isian yang tersimpan sebelumnya.',
        );
    }

    // Login DIJAGA SERVER (career.auth) saat FINALISASI (POST /api/v1/lamaran).
    // JANGAN pakai gate sessionStorage di sini — user login lewat sistem DB asli,
    // sessionStorage bisa kosong dan itu dulu bikin salah-redirect ke /login.
    const isForm2 = props.flow.form === 2;
    const saved = getApp(lowongan.id);
    // Identitas dari AKUN LOGIN (dikirim server via flow.kandidat), fallback draf lokal.
    const akun = props.flow.kandidat || {};
    if (isForm2) {
        // Tahap lanjut: jangan tampilkan layar selesai; prefill field readonly (namaPre/emailPre/waPre).
        const f = saved?.form || {};
        form.namaPre = f.nama || akun.nama || '';
        form.emailPre = f.email || akun.email || '';
        form.waPre = f.hp || f.phone || akun.hp || '';
        return;
    }
    if (saved && saved.status === 'FINAL') {
        done.value = true;
        return;
    } // sudah dilamar (Form 1)
    if (saved) restore(saved);
    // Prefill identitas dari akun login (isi bila kosong).
    if (!form.nama) form.nama = akun.nama || '';
    if (!form.email) form.email = akun.email || '';
    // Muat opsi cascade pendidikan: jenjang selalu; jenis/kampus bila draf terisi.
    steps.forEach((s) =>
        (s.fields || []).forEach((f) => {
            if (!f.sumber_api) return;
            // Nilai draf yang diketik sendiri tidak ada di daftar server — dititipkan
            // dulu sebagai opsi supaya tidak tampil kosong saat draf dibuka lagi.
            if ((f.cari_async || f.boleh_ketik) && form[f.key])
                apiOpts[f.key] = [{ value: form[f.key], label: form[f.key] }];
            if (!f.tergantung || form[f.tergantung]) loadApiOptions(f);
        }),
    );
});

// Review generik: kumpulkan semua field FORM yang terisi (label + nilai), lintas Form 1 / Form 2 / rekrutmen.
const reviewItems = computed(() => {
    const items = [];
    steps.forEach((s) => {
        if (s.tipe === 'FORM')
            (s.fields || []).forEach((f) => {
                if (!f.readonly && showField(f) && form[f.key] !== undefined && form[f.key] !== '')
                    items.push({ label: f.label, value: form[f.key] });
            });
    });
    return items;
});

const MAKS_BERKAS_MB = 2; // wajib maks 2 MB untuk SEMUA berkas
function processFile(key, file, f) {
    uploadErr.value = '';
    if (!file) return;
    // Dibatasi ke format yang BENAR-BENAR diterima server (GcsBerkas::
    // EKSTENSI_DIIZINKAN). Daftar ini harus sama persis dengan yang di sana:
    // begitu keduanya berbeda, kandidat memilih berkas yang lolos di layar lalu
    // ditolak saat mengirim — atau sebaliknya, ditolak di layar untuk format
    // yang sebenarnya diterima. Yang kedua itulah yang sempat terjadi pada PNG.
    const izin = (f.accept || '.pdf,.jpg')
        .split(',')
        .map((s) => s.trim().replace(/^\./, '').toLowerCase())
        .filter((x) => ['pdf', 'jpg', 'jpeg', 'png'].includes(x));
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (!izin.includes(ext)) {
        uploadErr.value = `${f.label}: hanya ${izin.map((x) => '.' + x).join(' / ')} yang diperbolehkan.`;
        return;
    }
    if (file.size > MAKS_BERKAS_MB * 1024 * 1024) {
        uploadErr.value = `${f.label}: melebihi ${MAKS_BERKAS_MB} MB.`;
        return;
    }
    if (files[key]?.url) URL.revokeObjectURL(files[key].url);
    files[key] = { name: file.name, url: URL.createObjectURL(file), isPdf: ext === 'pdf', raw: file, size: file.size };
}

function fmtUkuran(b) {
    if (!b) return '';
    return b < 1024 * 1024 ? Math.round(b / 1024) + ' KB' : (b / 1024 / 1024).toFixed(1) + ' MB';
}

/** Ubah dataURL foto wajah → Blob JPG untuk diunggah. */
function dataUrlKeBlob(dataUrl) {
    const [head, b64] = dataUrl.split(',');
    const mime = (head.match(/:(.*?);/) || [])[1] || 'image/jpeg';
    const bin = atob(b64);
    const arr = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
    return new Blob([arr], { type: mime });
}
// el-upload on-change → ambil File asli dari uploadFile.raw
function onFileEP(key, uf, f) {
    processFile(key, uf && uf.raw, f);
}
onBeforeUnmount(() => Object.values(files).forEach((v) => v && v.url && URL.revokeObjectURL(v.url)));

const toast = ref('');
let tm = null;
function notice(m, lama = 3000) {
    toast.value = m;
    if (tm) clearTimeout(tm);
    tm = setTimeout(() => (toast.value = ''), lama);
}
</script>

<style scoped>
.wca-apply__proc {
    text-align: center;
}
.wca-apply__spin {
    display: grid;
    place-items: center;
    margin-bottom: 1rem;
}
.wca-spinner {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: 4px solid rgba(79, 70, 229, 0.18);
    border-top-color: #4f46e5;
    animation: wcaspin 0.8s linear infinite;
}
@keyframes wcaspin {
    to {
        transform: rotate(360deg);
    }
}

/* ── Kartu Hasil Seleksi (desain "Halaman Hasil Seleksi") ── */
.wc-result {
    max-width: 760px;
    margin: 0 auto;
}
.wc-result__card {
    position: relative;
    background: #fff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 28px;
    box-shadow: 0 30px 80px rgba(30, 27, 75, 0.14);
    overflow: hidden;
    animation: wcFadeUp 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.wc-result__card.is-lolos {
    --acc: #10b981;
    --grad: linear-gradient(135deg, #34d399, #10b981);
    --ring: rgba(16, 185, 129, 0.5);
    --shadow: 0 16px 36px rgba(16, 185, 129, 0.4);
}
.wc-result__card.is-pending {
    --acc: #f59e0b;
    --grad: linear-gradient(135deg, #fbbf24, #f59e0b);
    --ring: rgba(245, 158, 11, 0.5);
    --shadow: 0 16px 36px rgba(245, 158, 11, 0.4);
}
.wc-result__card.is-gagal {
    --acc: #ef4444;
    --grad: linear-gradient(135deg, #f87171, #ef4444);
    --ring: rgba(239, 68, 68, 0.5);
    --shadow: 0 16px 36px rgba(239, 68, 68, 0.4);
}
.wc-result__strip {
    height: 6px;
    background: var(--grad);
}
.wc-result__inner {
    position: relative;
    padding: clamp(30px, 5vw, 54px) clamp(22px, 5vw, 60px) clamp(30px, 5vw, 50px);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}
.wc-result__iconwrap {
    position: relative;
    width: 104px;
    height: 104px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.wc-result__ring {
    position: absolute;
    width: 80px;
    height: 80px;
    border-radius: 26px;
    background: var(--ring);
    animation: wcRing 2.4s 0s ease-out infinite;
}
.wc-result__ring--b {
    animation-delay: 1.2s;
}
.wc-result__badge {
    position: relative;
    z-index: 2;
    width: 80px;
    height: 80px;
    border-radius: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--grad);
    box-shadow: var(--shadow);
    color: #fff;
    font-size: 40px;
    animation: wcPop 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
.wc-result__title {
    margin: 24px 0 0;
    font-size: clamp(24px, 4.2vw, 38px);
    line-height: 1.16;
    font-weight: 900;
    letter-spacing: -0.025em;
    color: #0f172a;
}
.wc-result__desc {
    margin: 14px 0 0;
    max-width: 560px;
    font-size: clamp(14px, 1.5vw, 16px);
    line-height: 1.72;
    color: #5b6478;
}
.wc-result__desc b {
    color: #334155;
}
.wc-result__prog {
    margin-top: 26px;
    width: 100%;
    max-width: 420px;
}
.wc-result__segs {
    display: flex;
    gap: 7px;
}
.wc-result__seg {
    flex: 1;
    height: 7px;
    border-radius: 999px;
    background: #e6e8f2;
}
.wc-result__seg.done {
    background: var(--grad);
}
.wc-result__seg.active {
    background: var(--grad);
    opacity: 0.55;
}
.wc-result__proglbl {
    margin-top: 10px;
    font-size: 12.5px;
    font-weight: 700;
    color: #8b93a7;
}
.wc-result__cta {
    margin-top: 28px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
}
.wc-btn-grad {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 14px 28px;
    border-radius: 14px;
    font-size: 15px;
    font-weight: 800;
    color: #fff;
    text-decoration: none;
    background: linear-gradient(135deg, #7c6df2, #6366f1);
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.34);
    transition:
        transform 0.18s,
        box-shadow 0.18s;
}
.wc-btn-grad:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 44px rgba(99, 102, 241, 0.46);
}
.wc-btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 14px 24px;
    border-radius: 14px;
    font-size: 15px;
    font-weight: 800;
    color: #4f46e5;
    text-decoration: none;
    background: #fff;
    border: 1px solid #d6d9f7;
    transition: all 0.18s;
}
.wc-btn-outline:hover {
    border-color: #a5b4fc;
    background: #f6f5ff;
}
.wc-result__confetti {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
}
.wc-result__confetti span {
    position: absolute;
}
.wc-result__safety {
    margin: 18px 0 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 13px 20px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: #8b93a7;
    font-size: 12.5px;
    font-weight: 600;
    text-align: center;
}
.wc-result__safety b {
    color: #64748b;
}
.wc-result__safety i {
    color: #94a3b8;
    font-size: 15px;
}
@keyframes wcFadeUp {
    from {
        opacity: 0;
        transform: translateY(22px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes wcPop {
    0% {
        opacity: 0;
        transform: scale(0.4);
    }
    60% {
        transform: scale(1.12);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}
@keyframes wcRing {
    0% {
        transform: scale(0.9);
        opacity: 0.6;
    }
    70%,
    100% {
        transform: scale(2.1);
        opacity: 0;
    }
}
</style>

<!-- Keyframe confetti dipakai via inline :style → wajib GLOBAL (scoped me-rename keyframe). -->
<style>
@keyframes wcConfetti {
    0% {
        transform: translateY(-20px) rotate(0);
        opacity: 1;
    }
    100% {
        transform: translateY(360px) rotate(680deg);
        opacity: 0;
    }
}
</style>
