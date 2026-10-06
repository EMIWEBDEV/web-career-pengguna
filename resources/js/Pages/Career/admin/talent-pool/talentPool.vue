<!-- WEB CAREER — TALENT POOL.
     Kandidat "bagus tapi belum terpakai" dari Worklist.

     TATA LETAK mengikuti rancangan `Talent Pool.dc.html` (Claude Design).
     Cangkang aplikasi — rail, navbar, sidebar — TIDAK ditiru ulang di sini:
     ketiganya sudah disediakan CareerShell, dan menggandakannya berarti dua
     sidebar yang harus dijaga tetap sama selamanya. Yang diterjemahkan hanya
     isi <main>-nya.

     DATA dari DB via /api/v1/karir/talent-pool. -->
<template>
    <Head title="Talent Pool" />

    <div class="wca tp">
        <!-- ══════════════════ TAMPILAN DAFTAR ══════════════════ -->
        <div v-if="!detailAktif" key="list" class="tp-view">
            <!-- HERO -->
            <div class="tp-hero">
                <span class="tp-hero__aurora" aria-hidden="true"></span>
                <span class="tp-hero__grid" aria-hidden="true"></span>

                <div class="tp-hero__row">
                    <div class="tp-hero__txt">
                        <span class="tp-hero__pill"><i class="tp-dot"></i>KOLAM TALENTA AKTIF</span>
                        <h1>Talent Pool</h1>
                        <p>
                            Kandidat bagus yang belum terpakai di lowongannya. Buka kartu untuk melihat
                            <b>data diri, kelengkapan berkas, dan seluruh alur proses</b> yang pernah diikuti.
                        </p>
                    </div>
                    <button type="button" class="tp-btn-export gbtn" :disabled="loading" :onClick="loading ? null : exportCsv">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v11m0 0l-3.5-3.5M12 14l3.5-3.5" /><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>
                        Ekspor Excel
                    </button>
                </div>

                <!-- KPI — sekaligus saringan status. Angka yang bisa ditekan
                     menghemat satu perjalanan ke dropdown yang ada di bawahnya. -->
                <div class="tp-kpis">
                    <button v-for="st in statKartu" :key="st.key" type="button" class="tp-kpi"
                        :class="{ 'is-on': filters.status === st.key }" @click="setStatus(st.key)">
                        <span class="tp-kpi__row">
                            <span class="tp-kpi__ico" :style="{ background: st.bg, color: st.c }" v-html="st.ikon"></span>
                            <span class="tp-kpi__txt">
                                <span class="tp-kpi__lbl" :style="{ color: filters.status === st.key ? st.c : '#8792a6' }">{{ st.label }}</span>
                                <span class="tp-kpi__num">
                                    {{ st.nilai }}
                                    <em :style="{ color: filters.status === st.key ? st.c : '#a2a9ba' }">{{ st.share }}</em>
                                </span>
                            </span>
                        </span>
                        <span class="tp-kpi__track"><span class="tp-kpi__fill" :style="{ width: st.pct + '%', background: st.grad }"></span></span>
                    </button>
                </div>
            </div>

            <!-- SARINGAN -->
            <div class="tp-tool">
                <div class="tp-tool__grid">
                    <div class="tp-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                        <input v-model="filters.q" type="text" placeholder="Cari nama, posisi, program, atau tag" @input="cariDebounce">
                    </div>

                    <!-- SELECT-nya Element Plus dan BISA DICARI. Daftar program &
                         divisi tumbuh mengikuti data; dropdown tanpa pencarian
                         memaksa menggulir puluhan baris untuk satu pilihan. -->
                    <el-select v-model="filters.program" class="tp-sel" placeholder="Program — semua"
                        clearable filterable @change="reload">
                        <el-option v-for="p in opsi.program" :key="p" :label="p" :value="p" />
                    </el-select>

                    <el-select v-model="filters.divisi" class="tp-sel" placeholder="Divisi — semua"
                        clearable filterable @change="reload">
                        <el-option v-for="d in opsi.divisi" :key="d" :label="d" :value="d" />
                    </el-select>

                    <el-select v-model="sort" class="tp-sel" filterable @change="setSort">
                        <el-option v-for="s in sortOpsi" :key="s.value" :label="s.label" :value="s.value" />
                    </el-select>
                </div>

                <div v-if="chips.length" class="tp-chips">
                    <span v-for="ch in chips" :key="ch.key" class="tp-chip">
                        <span class="tp-chip__k">{{ ch.kind }}</span>
                        <span class="tp-ell">{{ ch.label }}</span>
                        <button type="button" title="Copot" @click="copotChip(ch.key)">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                        </button>
                    </span>
                    <button type="button" class="tp-chips__clear" @click="bersihkanSemua">Bersihkan semua</button>
                </div>
            </div>

            <div class="tp-bar">
                <label class="tp-checkall">
                    <input type="checkbox" :checked="semuaTercentang" @change="toggleSemua">
                    Pilih semua di halaman ini
                </label>
                <div class="tp-bar__info">{{ teksHasil }}</div>
            </div>

            <!-- KARTU -->
            <div v-loading="loading" class="tp-grid">
                <div v-for="(k, i) in list" :key="k.id" class="tpcard"
                    :class="{ 'is-sel': terpilih(k.id) }" :style="{ animationDelay: (i * 45) + 'ms' }">
                    <span class="tpcard__spine" :style="{ background: warna(k.status).grad, opacity: terpilih(k.id) ? 1 : .7 }"></span>

                    <div class="tpcard__top">
                        <input type="checkbox" :checked="terpilih(k.id)" @change="toggleSatu(k.id)">
                        <span class="tpcard__ring" :style="{ background: warna(k.status).grad }">
                            <span class="tpcard__av" :style="{ background: gradAvatar(k.kandidat) }">{{ inisial(k.kandidat) }}</span>
                        </span>
                        <button type="button" class="tpcard__id" @click="bukaDetail(k)">
                            <span class="tpcard__nama">{{ k.kandidat }}</span>
                            <span class="tpcard__mail">{{ k.email || '—' }}</span>
                        </button>
                        <span class="tp-badge" :style="gayaBadge(k.status)">{{ warna(k.status).label }}</span>
                    </div>

                    <div class="tpcard__lines">
                        <span class="tpcard__line is-strong">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></svg>
                            <span class="tp-ell">{{ k.posisi }}</span>
                        </span>
                        <span class="tpcard__line">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#a2a9ba" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v12" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="6" r="3" /><path d="M18 9c0 6-12 3-12 9" /></svg>
                            <span class="tp-ell">{{ k.program }}</span>
                        </span>
                        <span class="tpcard__line">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#a2a9ba" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 21h20M5 21V6.5L12 3l7 3.5V21M9.5 10h5M9.5 14h5" /></svg>
                            <span class="tp-ell">{{ k.departemen || '—' }}</span>
                        </span>
                    </div>

                    <div class="tpcard__meta">
                        <span class="tpcard__chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z" /></svg>
                            {{ k.tahapAsal || 'Tahap tidak tercatat' }}
                        </span>
                        <span v-if="k.skor !== null" class="tpcard__skor" :style="{ color: warnaSkor(k.skor) }">Skor {{ k.skor }}</span>
                    </div>

                    <div v-if="tagList(k.tag).length" class="tpcard__tags">
                        <span v-for="tg in tagList(k.tag)" :key="tg">{{ tg }}</span>
                    </div>

                    <div class="tpcard__note">
                        <div class="tp-ell">{{ k.catatan || 'Tidak ada catatan rekruter.' }}</div>
                        <button type="button" @click="bukaDetail(k)">
                            Lihat profil lengkap
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                    </div>

                    <div class="tpcard__foot">
                        <span class="tp-ell">{{ k.createdAt || '—' }} · {{ k.createdBy }}</span>
                        <div class="tpcard__act">
                            <button type="button" class="tp-btn-ghost gbtn" @click="bukaDetail(k, 'journey')">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h9M4 18h6" /><circle cx="17.5" cy="15.5" r="3.2" /><path d="M19.9 17.9L22 20" /></svg>
                                Lihat Alur
                            </button>
                            <button type="button" class="tp-btn-primary gbtn" @click="bukaTarik(k)">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" /><path d="M10 17l5-5-5-5M15 12H3" /></svg>
                                Tarik
                            </button>
                            <button type="button" class="tp-ico-btn" title="Kelola" @click="bukaKelola(k)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6.5h7M15 6.5h6M3 17.5h4M12 17.5h9" /><circle cx="12.6" cy="6.5" r="2.3" /><circle cx="9.4" cy="17.5" r="2.3" /></svg>
                            </button>
                            <button type="button" class="tp-ico-btn is-danger" title="Hapus" @click="bukaHapus(k)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!loading && !list.length" class="tp-empty">
                <span class="tp-empty__ico">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" /></svg>
                </span>
                <div class="tp-empty__t">{{ adaFilter ? 'Tidak ada kandidat' : 'Talent Pool masih kosong' }}</div>
                <div class="tp-empty__s">
                    {{ adaFilter
                        ? 'Tidak ada kartu yang cocok dengan saringan ini.'
                        : 'Kandidat akan muncul di sini saat admin menekan “Masuk Talent Pool” di Worklist.' }}
                </div>
                <button v-if="adaFilter" type="button" class="tp-btn-primary gbtn" @click="bersihkanSemua">Bersihkan saringan</button>
            </div>

            <!-- PAGINASI -->
            <div v-if="total > 0" class="tp-pager">
                <div class="tp-pager__info">Halaman {{ page }} dari {{ totalPage }} · {{ total }} talenta</div>
                <div class="tp-pager__nav">
                    <button type="button" class="tp-pg-btn" :disabled="page <= 1" title="Sebelumnya" :onClick="page <= 1 ? null : () => gotoPage(page - 1)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7" /></svg>
                    </button>
                    <template v-for="(p, i) in nomorHalaman" :key="i">
                        <span v-if="p === '…'" class="tp-pg-gap">…</span>
                        <button v-else type="button" class="tp-pg-num" :class="{ 'is-on': p === page }" @click="gotoPage(p)">{{ p }}</button>
                    </template>
                    <button type="button" class="tp-pg-btn" :disabled="page >= totalPage" title="Berikutnya" @click="gotoPage(page + 1)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7" /></svg>
                    </button>
                </div>
                <div class="tp-pager__per">
                    Per halaman
                    <el-select v-model="perPage" class="tp-sel tp-sel--mini" @change="gantiPerPage">
                        <el-option v-for="n in [6, 9, 12, 24]" :key="n" :label="String(n)" :value="n" />
                    </el-select>
                </div>
            </div>

            <div style="height:78px"></div>
        </div>

        <!-- ══════════════════ TAMPILAN DETAIL ══════════════════ -->
        <div v-else key="detail" class="tp-view">
            <button type="button" class="tp-back" @click="tutupDetail">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6" /></svg>
                Talent Pool
            </button>

            <div v-loading="detailLoading" class="tp-hero">
                <span class="tp-hero__aurora" aria-hidden="true"></span>
                <div class="tp-prof">
                    <span class="tp-prof__av" :style="{ background: gradAvatar(det.kandidat) }">{{ inisial(det.kandidat) }}</span>
                    <div class="tp-prof__id">
                        <div class="tp-prof__top">
                            <span class="tp-badge-light">{{ warna(det.status).label }}</span>
                            <span class="tp-prof__kode">{{ det.kodeKartu }}</span>
                        </div>
                        <h1>{{ det.kandidat || '—' }}</h1>
                        <div class="tp-prof__sub">{{ det.subtitle }}</div>
                    </div>
                    <div class="tp-prof__act">
                        <button type="button" class="tp-hero-primary gbtn" @click="bukaTarik(det)">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" /><path d="M10 17l5-5-5-5M15 12H3" /></svg>
                            Tarik ke Lowongan
                        </button>
                        <button type="button" class="tp-hero-ghost" @click="bukaKelola(det)">Kelola</button>
                    </div>
                </div>

                <div class="tp-kpis">
                    <div v-for="kp in detKpi" :key="kp.label" class="tp-kpi is-static">
                        <span class="tp-kpi__row">
                            <span class="tp-kpi__ico" :style="{ background: kp.bg, color: kp.c }" v-html="kp.ikon"></span>
                            <span class="tp-kpi__txt">
                                <span class="tp-kpi__lbl">{{ kp.label }}</span>
                                <span class="tp-kpi__num">{{ kp.nilai }}</span>
                            </span>
                        </span>
                        <span class="tp-kpi__track"><span class="tp-kpi__fill" :style="{ width: Math.max(6, kp.pct) + '%', background: kp.grad }"></span></span>
                    </div>
                </div>
            </div>

            <!-- TAB -->
            <div class="tp-tabs">
                <button v-for="t in tabDef" :key="t.key" type="button" class="tp-tab"
                    :class="{ 'is-on': tab === t.key }" @click="tab = t.key">
                    <span v-html="t.ikon"></span>{{ t.label }}
                </button>
            </div>

            <!-- TAB: DATA DIRI -->
            <template v-if="tab === 'bio'">
                <!-- PENILAIAN REKRUTER — CATATAN UTUH, BUKAN SATU BARIS.
                     Di kartu, catatan dipotong satu baris supaya tinggi kartunya
                     seragam. Tombolnya menjanjikan "profil lengkap", jadi di
                     sinilah catatan itu harus benar-benar terbaca — beserta tag,
                     tahap asal, dan skor yang membuat kandidat ini disimpan.
                     Tanpa panel ini, alasan orang ini ada di kolam hanya hidup
                     di kepala orang yang menyimpannya. -->
                <div class="tp-panel tp-panel--wide">
                    <div class="tp-panel__head">
                        <span class="tp-panel__ico">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v13H8l-4 4z" /><path d="M8 9h8M8 13h5" /></svg>
                        </span>
                        <span>Penilaian Rekruter</span>
                    </div>

                    <div class="tp-rek">
                        <!-- SKOR HANYA MUNCUL BILA MEMANG ADA.
                             Kandidat disimpan ke kolam lewat keputusan tahap,
                             dan tahap wawancara tidak menghasilkan angka — jadi
                             skornya kosong untuk hampir semua kartu. Petak
                             bertuliskan "—" mengajari pembacanya bahwa tanda
                             hubung itu wajar, lalu ia berhenti membedakan mana
                             yang benar-benar belum diisi. -->
                        <div class="tp-rek__facts">
                            <div class="tp-fact">
                                <span>Tahap Asal</span>
                                <b>{{ det.tahapAsal || '—' }}</b>
                            </div>
                            <div class="tp-fact">
                                <span>Program Asal</span>
                                <b>{{ det.program || '—' }}</b>
                            </div>
                            <div v-if="det.skor !== null && det.skor !== undefined" class="tp-fact">
                                <span>Skor</span>
                                <b :style="{ color: warnaSkor(det.skor) }">{{ det.skor }}</b>
                            </div>
                            <div class="tp-fact">
                                <span>Disimpan</span>
                                <b>{{ det.createdAt || '—' }}</b>
                            </div>
                            <div class="tp-fact">
                                <span>Oleh</span>
                                <b class="tp-ell">{{ det.createdBy || '—' }}</b>
                            </div>
                        </div>

                        <div class="tp-rek__tags">
                            <span class="tp-rek__lbl">Tag</span>
                            <div v-if="tagList(det.tag).length" class="tpcard__tags" style="margin-top:6px">
                                <span v-for="tg in tagList(det.tag)" :key="tg">{{ tg }}</span>
                            </div>
                            <p v-else class="tp-rek__kosong">Belum ada tag. Tambahkan lewat <b>Kelola</b> agar kandidat ini mudah ditemukan.</p>
                        </div>

                        <div class="tp-rek__note">
                            <span class="tp-rek__lbl">Catatan</span>
                            <p v-if="det.catatan" class="tp-rek__isi">{{ det.catatan }}</p>
                            <p v-else class="tp-rek__kosong">Tidak ada catatan rekruter untuk kartu ini.</p>
                        </div>
                    </div>
                </div>

                <div class="tp-bio">
                    <div v-for="(g, gi) in bioGrup" :key="g.title" class="tp-panel" :style="{ animationDelay: (gi * 70) + 'ms' }">
                    <div class="tp-panel__head">
                        <span class="tp-panel__ico" v-html="g.ikon"></span>
                        <span>{{ g.title }}</span>
                    </div>
                        <div v-for="r in g.rows" :key="r.k" class="tp-kv">
                            <span class="tp-kv__k">{{ r.k }}</span>
                            <span class="tp-kv__v">{{ r.v || '—' }}</span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- TAB: BERKAS -->
            <div v-else-if="tab === 'docs'" class="tp-docs">
                <div class="tp-docs__head">
                    <div class="tp-docs__ttl">
                        <span class="tp-panel__ico">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z" /></svg>
                        </span>
                        <span>Kelengkapan Berkas</span>
                    </div>
                    <div class="tp-docs__meter">
                        <span class="tp-meter"><span class="tp-meter__fill" :style="{ width: docPct + '%', background: docPct === 100 ? 'linear-gradient(90deg,#8b5cf6,#4f46e5)' : 'linear-gradient(90deg,#a78bfa,#7c3aed)' }"></span></span>
                        <span class="tp-docs__num" :style="{ color: docPct === 100 ? '#4f46e5' : '#7c3aed' }">{{ docAda }} dari {{ (det.berkas || []).length }} dokumen</span>
                    </div>
                </div>
                <div class="tp-docs__list">
                    <!-- BARIS BISA DITEKAN BILA MEMANG ADA YANG DIBUKA.
                         Baris yang tampak bisa ditekan lalu tidak melakukan
                         apa-apa membuat orang menekannya berkali-kali dan
                         menyimpulkan halamannya rusak. -->
                    <div v-for="(d, di) in (det.berkas || [])" :key="di" class="docrow"
                        :class="{ 'is-klik': !!(d.id || d.pratinjau) }" :style="{ animationDelay: (di * 40) + 'ms' }"
                        @click="bukaBerkas(d)">
                        <!-- IKON MENGIKUTI BERKAS SUNGGUHAN — lihat
                             ProfilTalenta::barisBerkas. Yang belum diunggah
                             diberi rupa tersendiri (garis putus), bukan ikon
                             gambar yang menjanjikan sesuatu untuk dilihat. -->
                        <span class="docrow__ico" :class="'is-' + d.rupa">
                            <svg v-if="d.rupa === 'pdf'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z" /><path d="M14 3v6h6" /></svg>
                            <svg v-else-if="d.rupa === 'gambar'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="M21 15l-5-5L5 21" /></svg>
                            <svg v-else-if="d.rupa === 'lain'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z" /><path d="M14 3v6h6" /><path d="M9 14h6" /></svg>
                            <svg v-else-if="d.rupa === 'hilang'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z" /><path d="M9.5 12.5l5 5M14.5 12.5l-5 5" /></svg>
                            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="3 3"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z" /></svg>
                        </span>
                        <div class="docrow__id">
                            <div class="docrow__nama">
                                <span class="tp-ell">{{ d.nama }}</span>
                                <span class="docrow__tipe">{{ d.tipe }}</span>
                            </div>
                            <div class="docrow__meta">{{ d.meta }}</div>
                        </div>
                        <span class="tp-badge" :style="gayaBerkas(d)">{{ labelBerkas(d) }}</span>
                        <span v-if="d.id || d.pratinjau" class="docrow__open" title="Buka berkas">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" /><circle cx="12" cy="12" r="3" /></svg>
                        </span>
                    </div>
                    <div v-if="!(det.berkas || []).length" class="tp-kosong">Formulir lamaran ini tidak meminta berkas apa pun.</div>
                </div>
            </div>

            <!-- PRATINJAU BERKAS — memakai lightbox yang SAMA dengan modul
                 Monitoring, bukan lightbox kedua buatan sendiri. Gambar dan PDF
                 tampil di tempat, sisanya menawarkan unduh, dan Esc menutup
                 lewat antrean lapis yang sudah ada. -->
            <BerkasLightbox v-if="berkasLihat" :berkas="berkasLihat" @close="berkasLihat = null" />

            <!-- TAB: ALUR PROSES -->
            <div v-else class="tp-journey">
                <div v-for="(rw, ri) in (det.riwayat || [])" :key="ri" class="tp-jcard"
                    :class="{ 'is-open': jopen[ri] }" :style="{ animationDelay: (ri * 60) + 'ms' }">
                    <button type="button" class="tp-jhead" @click="toggleJourney(ri)">
                        <span class="tp-jchev" :class="{ 'is-on': jopen[ri] }">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6" /></svg>
                        </span>
                        <span class="tp-jhead__txt">
                            <span class="tp-jhead__t">
                                <span class="tp-ell">{{ rw.program }}</span>
                                <span class="tp-badge" :style="gayaHasil(rw.hasil)">{{ labelHasil(rw.hasil) }}</span>
                            </span>
                            <span class="tp-jhead__m tp-ell">{{ rw.posisi }} · {{ rw.periode }} · {{ rw.tahapan.length }} tahap</span>
                        </span>
                        <span class="tp-jhead__r">
                            <span class="tp-jstage">
                                <span>TAHAP</span>
                                <b>{{ tahapLulus(rw) }}/{{ rw.tahapan.length }}</b>
                            </span>
                            <span class="tp-jbar"><span :style="{ width: pctTahap(rw) + '%' }"></span></span>
                        </span>
                    </button>

                    <div v-if="jopen[ri]" class="tp-jbody">
                        <div class="tp-line-wrap">
                            <span class="tp-line-rail"></span>
                            <div v-for="(th, ti) in rw.tahapan" :key="ti" class="tp-step">
                                <span class="tp-step__dot" :style="gayaDot(th.hasil)" v-html="ikonHasil(th.hasil)"></span>
                                <div class="tp-step__row">
                                    <div class="tp-step__id">
                                        <div class="tp-step__nama" :style="{ color: th.hasil === 'BELUM' ? '#94a3b8' : '#0f172a' }">
                                            <!-- NOMOR TAHAP IKUT DITULIS. Rekruter dan kandidat
                                                 menyebut tahap dengan nomornya ("berhenti di
                                                 tahap 5"); linimasa tanpa nomor memaksa keduanya
                                                 menghitung sendiri dan sering meleset. -->
                                            <span class="tp-step__no">{{ th.urutan }}</span>{{ th.nama }}
                                        </div>
                                        <div class="tp-step__meta">{{ th.tgl }}</div>
                                    </div>
                                    <div class="tp-step__r">
                                        <span v-if="th.nilai !== null" class="tp-step__skor">Nilai {{ th.nilai }}</span>
                                        <span class="tp-badge" :style="gayaTahap(th.hasil)">{{ labelTahap(th.hasil) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tp-jnote">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></svg>
                            <span>{{ rw.note }}</span>
                        </div>
                    </div>
                </div>
                <div v-if="!(det.riwayat || []).length && !detailLoading" class="tp-kosong">Belum ada riwayat lamaran untuk kandidat ini.</div>
            </div>

            <div style="height:36px"></div>
        </div>

        <!-- BAR AKSI MASSAL -->
        <transition name="tp-bulk-fade">
            <div v-if="selected.length && !detailAktif" class="tp-bulkwrap">
                <div class="tp-bulkbar">
                    <span class="tp-bulkbar__n"><b>{{ selected.length }}</b>kartu dipilih</span>
                    <span class="tp-bulkbar__sep"></span>
                    <div class="tp-bulkbar__act">
                        <button type="button" @click="bulkAksi('AKTIF')">Aktifkan</button>
                        <button type="button" @click="bulkAksi('ARSIP')">Arsipkan</button>
                        <button type="button" class="is-danger" @click="bulkAksi('HAPUS')">Hapus</button>
                    </div>
                    <button type="button" class="tp-ico-btn" title="Tutup" @click="selected = []">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>
        </transition>

        <!-- ══════════════════ MODAL: TARIK ══════════════════ -->
        <div v-if="tarikShow" class="tp-scrim" @click="tutupModal">
            <div class="tp-modal tp-modal--md" role="dialog" aria-label="Tarik ke Lowongan" @click.stop>
                <div class="tp-modal__head">
                    <span class="tp-modal__ico">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" /><path d="M10 17l5-5-5-5M15 12H3" /></svg>
                    </span>
                    <div class="tp-modal__ttl">
                        <div>Tarik ke Lowongan</div>
                        <div class="tp-ell">{{ tarikTarget?.kandidat }} — asal: {{ tarikTarget?.posisi }}</div>
                    </div>
                    <button type="button" class="tp-ico-btn" title="Tutup" @click="tutupModal">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="tp-modal__body">
                    <div class="tp-search tp-search--plain">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                        <input v-model="lowonganQ" type="text" placeholder="Cari posisi atau program">
                    </div>

                    <div v-loading="lowonganLoading" class="tp-vlist">
                        <button v-for="o in lowonganTampil" :key="o.posisiId" type="button" class="tp-vrow"
                            :class="{ 'is-on': pilihPosisi === o.posisiId }" @click="pilihLowongan(o)">
                            <span class="tp-vrow__ico">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></svg>
                            </span>
                            <span class="tp-vrow__id">
                                <span class="tp-ell">{{ o.posisi }}</span>
                                <small class="tp-ell">{{ o.program }} · {{ o.departemen || '—' }}<template v-if="o.serumpun"> · serumpun</template><template v-if="o.lintasMpp"> · lintas MPP</template></small>
                            </span>
                            <span v-if="pilihPosisi === o.posisiId" class="tp-vrow__check">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                            </span>
                        </button>
                        <div v-if="!lowonganLoading && !lowonganTampil.length" class="tp-vempty">Tidak ada lowongan yang cocok.</div>
                    </div>

                    <!-- TITIK MASUK — tidak ada di rancangan, tapi server MEMINTANYA.
                         Tanpa ini "Tarik Kandidat" selalu ditolak 422. Ditulis dengan
                         kosakata visual yang sama (baris berpilihan) supaya tetap
                         terbaca sebagai satu jendela, bukan tambalan. -->
                    <div v-if="pilihPosisi" class="tp-msec">
                        <div class="tp-msec__lbl">Mulai dari tahap</div>
                        <p class="tp-msec__hint">Tahap sebelum pilihan Anda otomatis <b>dilewati (fast-track)</b>.</p>
                        <div v-loading="tahapLoading" class="tp-steps">
                            <button v-for="t in tahapList" :key="t.urutan" type="button" class="tp-vrow"
                                :class="{ 'is-on': mulaiUrutan === t.urutan }" @click="mulaiUrutan = t.urutan">
                                <span class="tp-steps__no" :class="{ 'is-on': mulaiUrutan === t.urutan }">{{ t.urutan }}</span>
                                <span class="tp-vrow__id"><span class="tp-ell">{{ t.label }}</span></span>
                                <small v-if="mulaiUrutan !== null && t.urutan < mulaiUrutan" class="tp-steps__skip">dilewati</small>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tp-modal__foot">
                    <div class="tp-modal__note tp-ell">
                        {{ pilihPosisi ? 'Riwayat seleksi sebelumnya tetap tersimpan pada kartu ini.' : 'Pilih lowongan tujuan untuk melanjutkan.' }}
                    </div>
                    <div class="tp-modal__btns">
                        <button type="button" class="tp-btn-ghost2" @click="tutupModal">Batal</button>
                        <button type="button" class="tp-btn-primary gbtn" :disabled="!bolehTarik || tarikBusy" :onClick="!bolehTarik || tarikBusy ? null : konfirmTarik">
                            {{ tarikBusy ? 'Memproses…' : 'Tarik Kandidat' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════ MODAL: KELOLA ══════════════════ -->
        <div v-if="kelolaShow" class="tp-scrim" @click="tutupModal">
            <div class="tp-modal tp-modal--md" role="dialog" aria-label="Kelola Kartu" @click.stop>
                <div class="tp-modal__head">
                    <span class="tp-modal__ico">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6.5h7M15 6.5h6M3 17.5h4M12 17.5h9" /><circle cx="12.6" cy="6.5" r="2.3" /><circle cx="9.4" cy="17.5" r="2.3" /></svg>
                    </span>
                    <div class="tp-modal__ttl">
                        <div>Kelola Kartu Talent Pool</div>
                        <div class="tp-ell">{{ edit?.kandidat }}</div>
                    </div>
                    <button type="button" class="tp-ico-btn" title="Tutup" @click="tutupModal">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="tp-modal__body tp-modal__body--gap">
                    <div>
                        <div class="tp-msec__lbl">Status</div>
                        <div class="tp-statuslist">
                            <button v-for="sc in statusPilihan" :key="sc.v" type="button" class="tp-vrow"
                                :class="{ 'is-on': form.status === sc.v }" @click="form.status = sc.v">
                                <span class="tp-radio" :class="{ 'is-on': form.status === sc.v }"><span></span></span>
                                <span class="tp-vrow__id">
                                    <span>{{ sc.label }}</span>
                                    <small>{{ sc.desc }}</small>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="tp-msec__lbl">Tag</div>
                        <el-input v-model="form.tag" placeholder="mis. Kuat wawancara, cocok Finance" maxlength="150" />
                        <div class="tp-tagsug">
                            <button v-for="ts in tagSaran" :key="ts" type="button" @click="tambahTag(ts)">+ {{ ts }}</button>
                        </div>
                    </div>

                    <div>
                        <div class="tp-msec__lbl">Catatan</div>
                        <el-input v-model="form.catatan" type="textarea" :rows="3" placeholder="Catatan penilaian kandidat…" />
                    </div>
                </div>

                <div class="tp-modal__foot">
                    <div class="tp-modal__note">Perubahan tercatat di riwayat kartu.</div>
                    <div class="tp-modal__btns">
                        <button type="button" class="tp-btn-ghost2" @click="tutupModal">Batal</button>
                        <button type="button" class="tp-btn-primary gbtn" :disabled="saving" :onClick="saving ? null : simpanKelola">
                            {{ saving ? 'Menyimpan…' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════ MODAL: HAPUS ══════════════════ -->
        <div v-if="delShow" class="tp-scrim" @click="tutupModal">
            <div class="tp-modal tp-modal--sm" role="dialog" aria-label="Konfirmasi hapus" @click.stop>
                <div class="tp-del">
                    <span class="tp-del__ico">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 16H3z" /><path d="M12 9v4M12 16h.01" /></svg>
                    </span>
                    <div class="tp-del__t">Hapus {{ delTarget?.kandidat }} dari Talent Pool?</div>
                    <div class="tp-del__s">Kartu akan dihapus permanen dari kolam.</div>
                </div>
                <div class="tp-del__foot">
                    <button type="button" class="tp-btn-ghost2 is-wide" @click="tutupModal">Batal</button>
                    <button type="button" class="tp-btn-danger" :disabled="deleting" :onClick="deleting ? null : konfirmHapus">
                        {{ deleting ? 'Menghapus…' : 'Ya, Hapus' }}
                    </button>
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
import BerkasLightbox from '../monitoring/BerkasLightbox.vue';

const API = '/api/v1/karir/talent-pool';
const CFG = { headers: { Accept: 'application/json' } };

/** Palet status — SATU sumber untuk lencana, cincin avatar, dan tulang kartu. */
const STATUS = {
    AKTIF: { label: 'AKTIF', c: '#4f46e5', bg: '#eef2ff', bd: '#c7d2fe', grad: 'linear-gradient(90deg,#8b5cf6,#6366f1)' },
    DITARIK: { label: 'DITARIK', c: '#7c3aed', bg: '#f4f2ff', bd: '#ddd6fe', grad: 'linear-gradient(90deg,#a78bfa,#7c3aed)' },
    ARSIP: { label: 'ARSIP', c: '#64748b', bg: '#f1f5f9', bd: '#e2e8f0', grad: 'linear-gradient(90deg,#cbd5e1,#94a3b8)' },
    // KEDALUWARSA SENGAJA DISIMPAN, TIDAK DIHAPUS.
    //
    // Statusnya masih hidup di server: scheduler harian tetap menandainya dan
    // kartu lama masih menyandangnya. Yang ditiadakan hanya PINTU MASUKNYA di
    // layar (KPI & saringan) — lihat `statKartu`. Menghapus baris ini membuat
    // kartu lama kehilangan warna dan lencananya menjadi kosong.
    KEDALUWARSA: { label: 'KEDALUWARSA', c: '#dc2626', bg: '#fef2f2', bd: '#fecaca', grad: 'linear-gradient(90deg,#f87171,#dc2626)' },
};

const AVATAR = [
    'linear-gradient(135deg,#8b5cf6,#6366f1)',
    'linear-gradient(135deg,#22d3ee,#0e7490)',
    'linear-gradient(135deg,#fbbf24,#f59e0b)',
    'linear-gradient(135deg,#34d399,#16a34a)',
    'linear-gradient(135deg,#f472b6,#db2777)',
];

const ikon = (path, w = 16) =>
    `<svg width="${w}" height="${w}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;

export default {
    components: { Head, BerkasLightbox },

    data() {
        return {
            list: [],
            ringkas: { total: 0, aktif: 0, ditarik: 0, arsip: 0, kedaluwarsa: 0 },
            opsi: { program: [], divisi: [] },
            loading: false,
            page: 1,
            perPage: 12,
            total: 0,
            totalPage: 1,
            sort: 'terbaru',
            filters: { q: '', status: '', program: '', divisi: '' },
            selected: [],
            toast: null,
            timer: null,

            // detail
            detailAktif: false,
            detailLoading: false,
            det: {},
            tab: 'bio',
            jopen: { 0: true },

            // modal kelola
            kelolaShow: false,
            edit: null,
            saving: false,
            form: { status: 'AKTIF', tag: '', catatan: '' },

            // modal hapus
            delShow: false,
            delTarget: null,
            deleting: false,

            // modal tarik
            tarikShow: false,
            tarikTarget: null,
            tarikBusy: false,
            lowonganQ: '',
            lowonganList: [],
            lowonganLoading: false,
            pilihPosisi: null,
            tahapList: [],
            tahapLoading: false,
            mulaiUrutan: null,

            sortOpsi: [
                { value: 'terbaru', label: 'Terbaru disimpan' },
                { value: 'skor', label: 'Skor tertinggi' },
                { value: 'berkas', label: 'Berkas terlengkap' },
                { value: 'nama', label: 'Nama A–Z' },
            ],
            statusPilihan: [
                { v: 'AKTIF', label: 'Aktif', desc: 'Siap dipertimbangkan pada lowongan berikutnya.' },
                { v: 'DITARIK', label: 'Ditarik', desc: 'Sudah dipanggil ke lowongan, proses berjalan di worklist.' },
                { v: 'ARSIP', label: 'Arsip', desc: 'Tidak dipertimbangkan lagi, tetap tersimpan sebagai riwayat.' },
            ],
            tagSaran: ['Kuat wawancara', 'cocok Finance', 'Leadership', 'Fresh talent'],
            tabDef: [
                { key: 'bio', label: 'Data Diri', ikon: ikon('<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>', 15) },
                { key: 'docs', label: 'Kelengkapan Berkas', ikon: ikon('<path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z"/>', 15) },
                { key: 'journey', label: 'Alur Proses', ikon: ikon('<path d="M4 6h16M4 12h9M4 18h6"/><circle cx="17.5" cy="15.5" r="3.2"/>', 15) },
            ],
            berkasLihat: null,
            gayaLengkap: { background: '#f0fdf4', border: '1px solid #bbf7d0', color: '#16a34a' },
            gayaKosong: { background: '#fffbeb', border: '1px solid #fde68a', color: '#b45309' },
            gayaHilang: { background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626' },
        };
    },

    computed: {
        /**
         * KPI sekaligus saringan status.
         *
         * KEDALUWARSA TIDAK DIDAFTARKAN DI SINI — itu satu-satunya tempat ia
         * ditiadakan dari layar. Perhitungannya di server tetap berjalan dan
         * `ringkas.kedaluwarsa` tetap terisi; begitu kolomnya dikembalikan,
         * angkanya sudah benar tanpa perlu menyentuh server.
         */
        statKartu() {
            const t = this.ringkas.total || 0;
            const bagi = (n) => (t ? Math.round((n / t) * 100) : 0);
            const def = [
                { key: '', label: 'Total Talenta', nilai: t, c: '#4338ca', bg: '#eef2ff', grad: 'linear-gradient(90deg,#8b5cf6,#4338ca)', p: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>' },
                { key: 'AKTIF', label: 'Aktif', nilai: this.ringkas.aktif, ...STATUS.AKTIF, p: '<circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/>' },
                { key: 'DITARIK', label: 'Ditarik', nilai: this.ringkas.ditarik, ...STATUS.DITARIK, p: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5M15 12H3"/>' },
                { key: 'ARSIP', label: 'Arsip', nilai: this.ringkas.arsip, ...STATUS.ARSIP, p: '<rect x="3" y="4" width="18" height="5" rx="1.5"/><path d="M5 9v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9M10 13h4"/>' },
                // { key: 'KEDALUWARSA', label: 'Kadaluarsa', nilai: this.ringkas.kedaluwarsa, ...STATUS.KEDALUWARSA, p: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>' },
            ];

            return def.map((d) => ({
                key: d.key,
                label: d.label,
                nilai: d.nilai || 0,
                share: bagi(d.nilai || 0) + '%',
                pct: Math.max(6, bagi(d.nilai || 0)),
                c: d.c,
                bg: d.bg,
                grad: d.grad,
                ikon: ikon(d.p),
            }));
        },

        chips() {
            const c = [];
            if (this.filters.q.trim()) c.push({ key: 'q', kind: 'Cari', label: this.filters.q.trim() });
            if (this.filters.status) c.push({ key: 'status', kind: 'Status', label: this.warna(this.filters.status).label });
            if (this.filters.program) c.push({ key: 'program', kind: 'Program', label: this.filters.program });
            if (this.filters.divisi) c.push({ key: 'divisi', kind: 'Divisi', label: this.filters.divisi });
            if (this.sort !== 'terbaru') {
                c.push({ key: 'sort', kind: 'Urut', label: (this.sortOpsi.find((s) => s.value === this.sort) || {}).label });
            }
            return c;
        },

        adaFilter() {
            return this.chips.length > 0;
        },

        teksHasil() {
            if (!this.total) return 'Tidak ada kartu';
            const mulai = (this.page - 1) * this.perPage + 1;
            return `Menampilkan ${mulai}–${Math.min(mulai + this.perPage - 1, this.total)} dari ${this.total} talenta`;
        },

        semuaTercentang() {
            return this.list.length > 0 && this.list.every((k) => this.selected.includes(k.id));
        },

        nomorHalaman() {
            const out = [];
            for (let n = 1; n <= this.totalPage; n++) {
                const dekat = Math.abs(n - this.page) <= 2 || n === 1 || n === this.totalPage;
                if (!dekat) {
                    if (out[out.length - 1] !== '…') out.push('…');
                    continue;
                }
                out.push(n);
            }
            return out;
        },

        docAda() {
            return (this.det.berkas || []).filter((b) => b.ada).length;
        },

        docPct() {
            const t = (this.det.berkas || []).length;
            return t ? Math.round((this.docAda / t) * 100) : 0;
        },

        detKpi() {
            const rw = this.det.riwayat || [];
            const tot = rw.reduce((a, r) => a + r.tahapan.length, 0);
            const lulus = rw.reduce((a, r) => a + this.tahapLulus(r), 0);
            return [
                { label: 'Tahap Dilalui', nilai: `${lulus}/${tot}`, pct: tot ? Math.round((lulus / tot) * 100) : 0, grad: 'linear-gradient(90deg,#8b5cf6,#6366f1)', bg: '#eef2ff', c: '#4f46e5', ikon: ikon('<path d="M20 6L9 17l-5-5"/>') },
                { label: 'Kelengkapan Berkas', nilai: `${this.docAda} / ${(this.det.berkas || []).length}`, pct: this.docPct, grad: 'linear-gradient(90deg,#a78bfa,#7c3aed)', bg: '#f4f2ff', c: '#7c3aed', ikon: ikon('<path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4z"/>') },
                { label: 'Program Diikuti', nilai: `${rw.length} program`, pct: Math.min(100, rw.length * 45), grad: 'linear-gradient(90deg,#818cf8,#4338ca)', bg: '#eef2ff', c: '#4338ca', ikon: ikon('<path d="M6 3v12"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="6" r="3"/><path d="M18 9c0 6-12 3-12 9"/>') },
                // "Lama di Kolam", BUKAN skor.
                //
                // Skor kartu hampir selalu kosong: yang menyimpan kandidat ke
                // pool adalah keputusan tahap, dan tahap wawancara memang tidak
                // menghasilkan angka. Petak KPI yang selamanya bertuliskan "—"
                // hanya memakan seperempat baris tanpa memberi tahu apa pun.
                // Lama menunggu selalu ada, dan justru itu yang menentukan
                // kandidat mana yang perlu dihubungi lebih dulu.
                { label: 'Lama di Kolam', nilai: this.lamaDiKolam.teks, pct: this.lamaDiKolam.pct, grad: 'linear-gradient(90deg,#c4b5fd,#8b5cf6)', bg: '#f4f2ff', c: '#7c3aed', ikon: ikon('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>') },
            ];
        },

        /** Sejak kartu masuk kolam. Setahun dianggap "penuh" untuk meternya. */
        lamaDiKolam() {
            const t = this.det.createdAtRaw || this.det.createdAt;
            if (!t) return { teks: '—', pct: 0 };
            const mulai = new Date(String(t).replace(' ', 'T'));
            if (Number.isNaN(mulai.getTime())) return { teks: '—', pct: 0 };

            const hari = Math.max(0, Math.floor((Date.now() - mulai.getTime()) / 86400000));
            const teks = hari < 1 ? 'Hari ini'
                : hari < 30 ? `${hari} hari`
                    : hari < 365 ? `${Math.floor(hari / 30)} bulan`
                        : `${Math.floor(hari / 365)} tahun`;

            return { teks, pct: Math.min(100, Math.round((hari / 365) * 100)) };
        },

        /**
         * Biodata — HANYA yang benar-benar dijawab.
         *
         * Formulirnya dinamis: mahasiswa tingkat akhir tidak ditanya tahun
         * lulus, pelamar non-sarjana tidak ditanya IPK, dan seterusnya. Menulis
         * "—" untuk pertanyaan yang MEMANG TIDAK PERNAH DIAJUKAN membuat
         * kandidat terlihat seperti tidak melengkapi datanya — padahal ia
         * mengisi semua yang diminta. Yang kosong dibuang; kelengkapan berkas
         * punya tabnya sendiri untuk menunjukkan apa yang betul-betul kurang.
         */
        bioGrup() {
            const b = this.det.bio || {};
            return [
                {
                    title: 'Identitas',
                    ikon: ikon('<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>'),
                    rows: [
                        { k: 'Nama', v: this.det.kandidat },
                        { k: 'NIK', v: b.nik },
                        { k: 'Tempat/Tgl Lahir', v: b.ttl },
                        { k: 'Jenis Kelamin', v: b.gender },
                        { k: 'Agama', v: b.agama },
                        { k: 'Status', v: b.kawin },
                    ],
                },
                {
                    title: 'Kontak',
                    ikon: ikon('<path d="M4 4h16v16H4z"/><path d="M4 7l8 6 8-6"/>'),
                    rows: [
                        { k: 'Email', v: this.det.email },
                        { k: 'No. HP', v: b.hp },
                        { k: 'Alamat', v: b.alamat },
                        // Kode LAMARAN, bukan "kode kartu". Rancangan memakai
                        // nomor rekaan (TP-2026-0011) yang tidak pernah
                        // diterbitkan sistem ini; yang dipakai sehari-hari untuk
                        // merujuk kandidat adalah kode lamarannya.
                        { k: 'Kode Lamaran', v: this.det.kodeLamaran },
                    ],
                },
                {
                    title: 'Pendidikan',
                    ikon: ikon('<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>'),
                    rows: [
                        { k: 'Jenjang', v: b.jenjang },
                        { k: 'Institusi', v: b.kampus },
                        { k: 'Jurusan', v: b.jurusan },
                        { k: 'IPK', v: b.ipk },
                        { k: 'Tahun Lulus', v: b.tahunLulus },
                        { k: 'Status Studi', v: b.statusStudi },
                        { k: 'Pengalaman', v: b.pengalaman },
                    ],
                },
            ].map((g) => ({
                ...g,
                rows: g.rows.filter((r) => r.v !== null && r.v !== undefined && String(r.v).trim() !== ''),
            })).filter((g) => g.rows.length);
        },

        lowonganTampil() {
            const q = this.lowonganQ.trim().toLowerCase();
            if (!q) return this.lowonganList;
            return this.lowonganList.filter((o) =>
                `${o.posisi} ${o.program} ${o.departemen || ''}`.toLowerCase().includes(q));
        },

        bolehTarik() {
            return !!this.pilihPosisi && this.mulaiUrutan !== null;
        },
    },

    mounted() {
        this.reload();
    },

    beforeUnmount() {
        clearTimeout(this.timer);
    },

    methods: {
        // ── Palet & format ───────────────────────────────────────────────────
        warna(s) {
            return STATUS[s] || STATUS.ARSIP;
        },
        gayaBadge(s) {
            const w = this.warna(s);
            return { background: w.bg, border: `1px solid ${w.bd}`, color: w.c };
        },
        inisial(n) {
            return String(n || '?').split(' ').slice(0, 2).map((w) => w.charAt(0)).join('').toUpperCase();
        },
        gradAvatar(n) {
            return AVATAR[String(n || '').length % AVATAR.length];
        },
        warnaSkor(v) {
            if (v === null || v === undefined) return '#94a3b8';
            return v >= 85 ? '#16a34a' : v >= 75 ? '#4f46e5' : '#d97706';
        },
        tagList(tag) {
            return String(tag || '').split(',').map((t) => t.trim()).filter(Boolean);
        },

        /** TIGA keadaan berkas, bukan dua — lihat ProfilTalenta::barisBerkas. */
        labelBerkas(d) {
            if (d.ada) return 'LENGKAP';
            return d.rupa === 'hilang' ? 'TAK TERSIMPAN' : 'KOSONG';
        },
        gayaBerkas(d) {
            if (d.ada) return this.gayaLengkap;
            return d.rupa === 'hilang' ? this.gayaHilang : this.gayaKosong;
        },
        /**
         * Buka berkas di lightbox — BUKAN tab baru.
         *
         * Tab baru memutus alur: admin sedang menilai satu kandidat, lalu
         * terlempar ke tab berisi PDF tanpa konteks dan harus mencari jalan
         * kembali. Berkas dari GCS dilayani lewat signed URL (redirect 302),
         * yang ditelan <img>/<iframe> tanpa masalah; foto yang tersimpan di
         * dalam jawaban dipakai data URI-nya langsung.
         */
        bukaBerkas(d) {
            const url = d.pratinjau || (d.id ? `${API}/berkas/file/${d.id}` : null);
            if (!url) return;

            this.berkasLihat = {
                nama: d.namaAsli || d.nama,
                url,
                ukuran: d.ukuranByte,
                ext: (d.tipe || '').toLowerCase(),
                mime: d.mime,
            };
        },

        // ── Alur proses ──────────────────────────────────────────────────────
        /**
         * Tahap yang sudah DILALUI — lulus, dilewati, ATAU titik masuk pool.
         *
         * Tahap talent pool ikut dihitung: kandidat memang sampai ke sana dan
         * mengerjakannya. Mengeluarkannya membuat "4/7" padahal ia menyelesaikan
         * lima tahap, dan angka itulah yang dipakai rekruter menilai seberapa
         * jauh proses yang sudah dibayar perusahaan.
         */
        tahapLulus(rw) {
            return rw.tahapan.filter((t) => ['LULUS', 'SKIP', 'TALENT_POOL'].includes(t.hasil)).length;
        },
        pctTahap(rw) {
            return rw.tahapan.length ? Math.round((this.tahapLulus(rw) / rw.tahapan.length) * 100) : 0;
        },
        labelHasil(h) {
            return { TALENT_POOL: 'MASUK TALENT POOL', GUGUR: 'TIDAK LOLOS', BERJALAN: 'BERJALAN', LOLOS: 'DITERIMA' }[h] || h;
        },
        gayaHasil(h) {
            const m = {
                TALENT_POOL: ['#4f46e5', '#eef2ff', '#c7d2fe'],
                GUGUR: ['#dc2626', '#fef2f2', '#fecaca'],
                BERJALAN: ['#d97706', '#fffbeb', '#fde68a'],
                LOLOS: ['#16a34a', '#f0fdf4', '#bbf7d0'],
            }[h] || ['#64748b', '#f1f5f9', '#e2e8f0'];
            return { color: m[0], background: m[1], border: `1px solid ${m[2]}` };
        },
        petaTahap(h) {
            return {
                // TITIK PINDAH KE KOLAM — KUNING, dan ikonnya bintang.
                // Inilah satu-satunya tahap yang menjelaskan kenapa kandidat
                // ada di halaman ini; ia harus terbaca dalam sekali lihat,
                // bukan tenggelam di antara tahap lain yang sama hijaunya.
                TALENT_POOL: { t: 'MASUK TALENT POOL', c: '#b45309', bg: '#fffbeb', bd: '#fcd34d', i: '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/>' },
                LULUS: { t: 'LULUS', c: '#16a34a', bg: '#f0fdf4', bd: '#bbf7d0', i: '<path d="M20 6L9 17l-5-5"/>' },
                GAGAL: { t: 'GAGAL', c: '#dc2626', bg: '#fef2f2', bd: '#fecaca', i: '<path d="M18 6L6 18M6 6l12 12"/>' },
                SKIP: { t: 'DILEWATI', c: '#4f46e5', bg: '#eef2ff', bd: '#c7d2fe', i: '<path d="M5 12h14M13 6l6 6-6 6"/>' },
                BERJALAN: { t: 'BERJALAN', c: '#d97706', bg: '#fffbeb', bd: '#fde68a', i: '<circle cx="12" cy="12" r="4"/>' },
                BELUM: { t: 'BELUM', c: '#64748b', bg: '#f1f5f9', bd: '#e2e8f0', i: '<circle cx="12" cy="12" r="3"/>' },
            }[h] || { t: h, c: '#64748b', bg: '#f1f5f9', bd: '#e2e8f0', i: '<circle cx="12" cy="12" r="3"/>' };
        },
        labelTahap(h) {
            return this.petaTahap(h).t;
        },
        gayaTahap(h) {
            const m = this.petaTahap(h);
            return { color: m.c, background: m.bg, border: `1px solid ${m.bd}` };
        },
        gayaDot(h) {
            const m = this.petaTahap(h);
            return { color: m.c, borderColor: m.bd, animation: h === 'BERJALAN' ? 'tpPing 2.2s infinite' : 'none' };
        },
        ikonHasil(h) {
            return `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">${this.petaTahap(h).i}</svg>`;
        },
        toggleJourney(i) {
            this.jopen = { ...this.jopen, [i]: !this.jopen[i] };
        },

        // ── Muat data ────────────────────────────────────────────────────────
        async reload() {
            this.loading = true;
            try {
                const { data } = await axios.get(API, {
                    ...CFG,
                    params: {
                        q: this.filters.q || undefined,
                        status: this.filters.status || undefined,
                        program: this.filters.program || undefined,
                        divisi: this.filters.divisi || undefined,
                        sort: this.sort,
                        page: this.page,
                        perPage: this.perPage,
                    },
                });
                const r = data.result || {};
                this.list = r.data || [];
                this.ringkas = r.ringkas || this.ringkas;
                this.opsi = r.opsi || this.opsi;
                this.total = r.total || 0;
                this.totalPage = r.totalPage || 1;
                this.page = r.page || 1;
            } catch (e) {
                this.beriTahu('Gagal memuat data talent pool.');
            } finally {
                this.loading = false;
            }
        },

        cariDebounce() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.page = 1;
                this.reload();
            }, 350);
        },
        setStatus(v) {
            this.filters.status = this.filters.status === v ? '' : v;
            this.page = 1;
            this.reload();
        },
        setSort(v) {
            this.sort = v;
            this.page = 1;
            this.reload();
        },
        gantiPerPage(n) {
            this.perPage = n;
            this.page = 1;
            this.reload();
        },
        gotoPage(p) {
            if (p < 1 || p > this.totalPage) return;
            this.page = p;
            this.reload();
        },
        copotChip(k) {
            if (k === 'q') this.filters.q = '';
            if (k === 'status') this.filters.status = '';
            if (k === 'program') this.filters.program = '';
            if (k === 'divisi') this.filters.divisi = '';
            if (k === 'sort') this.sort = 'terbaru';
            this.page = 1;
            this.reload();
        },
        bersihkanSemua() {
            this.filters = { q: '', status: '', program: '', divisi: '' };
            this.sort = 'terbaru';
            this.page = 1;
            this.reload();
        },

        // ── Pilihan massal ───────────────────────────────────────────────────
        terpilih(id) {
            return this.selected.includes(id);
        },
        toggleSatu(id) {
            this.selected = this.terpilih(id) ? this.selected.filter((x) => x !== id) : [...this.selected, id];
        },
        toggleSemua() {
            this.selected = this.semuaTercentang ? [] : this.list.map((k) => k.id);
        },
        async bulkAksi(aksi) {
            if (!this.selected.length) return;
            if (aksi === 'HAPUS' && !window.confirm(`Hapus ${this.selected.length} kartu dari Talent Pool?`)) return;
            try {
                const { data } = await axios.post(`${API}/bulk`, { ids: this.selected, aksi }, CFG);
                this.selected = [];
                this.beriTahu(data.message || 'Aksi massal selesai.');
                this.reload();
            } catch (e) {
                this.beriTahu(e?.response?.data?.message || 'Gagal memproses aksi massal.');
            }
        },

        // ── Detail ───────────────────────────────────────────────────────────
        async bukaDetail(k, tab = 'bio') {
            this.detailAktif = true;
            this.detailLoading = true;
            this.tab = tab;
            this.jopen = { 0: true };
            // Kartu ringkas dipasang LEBIH DULU supaya kepala halaman langsung
            // terisi; profil lengkapnya menyusul tanpa layar kosong.
            this.det = { ...k, subtitle: [k.posisi, k.program, k.departemen].filter(Boolean).join(' · ') };
            window.scrollTo({ top: 0, behavior: 'smooth' });
            try {
                const { data } = await axios.get(`${API}/${k.id}/detail`, CFG);
                const d = data.result || {};
                this.det = { ...d, subtitle: [d.posisi, d.program, d.departemen].filter(Boolean).join(' · ') };
            } catch (e) {
                this.beriTahu('Gagal memuat profil lengkap kandidat.');
            } finally {
                this.detailLoading = false;
            }
        },
        tutupDetail() {
            this.detailAktif = false;
            this.det = {};
        },

        // ── Modal: kelola ────────────────────────────────────────────────────
        bukaKelola(k) {
            this.edit = k;
            this.form = { status: k.status === 'KEDALUWARSA' ? 'AKTIF' : k.status, tag: k.tag || '', catatan: k.catatan || '' };
            this.kelolaShow = true;
        },
        tambahTag(t) {
            this.form.tag = this.form.tag ? `${this.form.tag}, ${t}` : t;
        },
        async simpanKelola() {
            this.saving = true;
            try {
                await axios.patch(`${API}/${this.edit.id}`, this.form, CFG);
                this.kelolaShow = false;
                this.beriTahu('Kartu diperbarui.');
                if (this.detailAktif) this.bukaDetail({ ...this.edit, ...this.form }, this.tab);
                this.reload();
            } catch (e) {
                this.beriTahu(e?.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },

        // ── Modal: hapus ─────────────────────────────────────────────────────
        bukaHapus(k) {
            this.delTarget = k;
            this.delShow = true;
        },
        async konfirmHapus() {
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.delShow = false;
                this.detailAktif = false;
                this.beriTahu('Kartu dihapus.');
                this.reload();
            } catch (e) {
                this.beriTahu(e?.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },

        // ── Modal: tarik ─────────────────────────────────────────────────────
        async bukaTarik(k) {
            this.tarikTarget = k;
            this.tarikShow = true;
            this.lowonganQ = '';
            this.pilihPosisi = null;
            this.mulaiUrutan = null;
            this.tahapList = [];
            this.lowonganLoading = true;
            try {
                const { data } = await axios.get(`${API}/${k.id}/lowongan`, CFG);
                this.lowonganList = data.result?.data || data.result || [];
            } catch (e) {
                this.lowonganList = [];
                this.beriTahu('Gagal memuat daftar lowongan.');
            } finally {
                this.lowonganLoading = false;
            }
        },
        async pilihLowongan(o) {
            this.pilihPosisi = o.posisiId;
            this.mulaiUrutan = null;
            this.tahapLoading = true;
            try {
                const { data } = await axios.get(`${API}/lowongan/${o.posisiId}/tahap`, CFG);
                this.tahapList = data.result?.data || data.result || [];
                this.mulaiUrutan = this.tahapList.length ? this.tahapList[0].urutan : null;
            } catch (e) {
                this.tahapList = [];
                this.beriTahu('Gagal memuat tahap lowongan.');
            } finally {
                this.tahapLoading = false;
            }
        },
        async konfirmTarik() {
            if (!this.bolehTarik) return;
            this.tarikBusy = true;
            try {
                const { data } = await axios.post(`${API}/${this.tarikTarget.id}/tarik`, {
                    posisiId: this.pilihPosisi,
                    mulaiDariUrutan: this.mulaiUrutan,
                }, CFG);
                this.tarikShow = false;
                this.detailAktif = false;
                this.beriTahu(data.message || 'Kandidat ditarik ke lowongan.');
                this.reload();
            } catch (e) {
                this.beriTahu(e?.response?.data?.message || 'Gagal menarik kandidat.');
            } finally {
                this.tarikBusy = false;
            }
        },

        tutupModal() {
            this.tarikShow = false;
            this.kelolaShow = false;
            this.delShow = false;
        },

        exportCsv() {
            const p = new URLSearchParams();
            if (this.filters.q) p.set('q', this.filters.q);
            if (this.filters.status) p.set('status', this.filters.status);
            window.open(`${API}/export?${p.toString()}`, '_blank');
        },

        beriTahu(msg) {
            this.toast = msg;
            setTimeout(() => { this.toast = null; }, 2600);
        },
    },
};
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════════════════════
   TALENT POOL — terjemahan `Talent Pool.dc.html`.

   Rancangannya memakai gaya sebaris (inline) karena itu satu-satunya cara di
   alat desain. Di sini dipindahkan ke kelas: nilainya sama persis, tapi bisa
   dibaca, bisa dicari, dan tidak menghitung ulang string setiap render.
   ══════════════════════════════════════════════════════════════════════════ */

.tp { color: #0f172a; }
.tp-view { animation: tpViewIn .32s cubic-bezier(.22, 1, .36, 1) both; }
.tp-ell { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

@keyframes tpViewIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@keyframes tpCardIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes tpRowIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
@keyframes tpModalIn { from { opacity: 0; transform: translateY(14px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes tpBarIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@keyframes tpAurora { 0% { transform: translate3d(0, 0, 0) scale(1); } 50% { transform: translate3d(26px, -18px, 0) scale(1.06); } 100% { transform: translate3d(0, 0, 0) scale(1); } }
@keyframes tpPing { 0% { box-shadow: 0 0 0 0 rgba(217, 119, 6, .45); } 70% { box-shadow: 0 0 0 7px rgba(217, 119, 6, 0); } 100% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0); } }
@keyframes tpSheen { 0% { transform: translateX(-130%); } 100% { transform: translateX(240%); } }

.gbtn { position: relative; overflow: hidden; transition: transform .16s, box-shadow .16s, background .16s; }
.gbtn:hover:not(:disabled) { transform: translateY(-1px); }
.gbtn:active:not(:disabled) { transform: translateY(0) scale(.98); }
.gbtn::after { content: ""; position: absolute; inset: 0; width: 38%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .42), transparent); transform: translateX(-130%); }
.gbtn:hover:not(:disabled)::after { animation: tpSheen .75s ease; }

/* ── HERO ─────────────────────────────────────────────────────────────── */
.tp-hero {
    position: relative; overflow: hidden; border-radius: 24px; padding: 24px 26px;
    background: linear-gradient(125deg, #4338ca 0%, #6366f1 48%, #8b5cf6 100%);
    box-shadow: 0 20px 50px rgba(79, 70, 229, .3);
}
.tp-hero__aurora {
    position: absolute; top: -90px; right: -40px; width: 340px; height: 340px; border-radius: 50%;
    background: radial-gradient(circle at 40% 40%, rgba(196, 181, 253, .34), rgba(196, 181, 253, 0) 70%);
    animation: tpAurora 18s ease-in-out infinite; pointer-events: none;
}
.tp-hero__grid {
    position: absolute; inset: 0; pointer-events: none;
    background-image: linear-gradient(rgba(255, 255, 255, .05) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, .05) 1px, transparent 1px);
    background-size: 38px 38px;
    -webkit-mask-image: radial-gradient(circle at 82% 0, #000, transparent 68%);
    mask-image: radial-gradient(circle at 82% 0, #000, transparent 68%);
}
.tp-hero__row { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; flex-wrap: wrap; }
.tp-hero__txt { min-width: 0; }
.tp-hero__pill {
    display: inline-flex; align-items: center; gap: 8px; padding: 5px 11px; border-radius: 999px;
    background: rgba(255, 255, 255, .14); border: 1px solid rgba(255, 255, 255, .24);
    font-size: 10.5px; font-weight: 700; letter-spacing: .14em; color: #e9e6ff;
}
.tp-dot { width: 6px; height: 6px; border-radius: 50%; background: #c4b5fd; }
.tp-hero__txt h1 { margin: 13px 0 0; font-size: 26px; font-weight: 800; color: #fff; letter-spacing: -.03em; }
.tp-hero__txt p { margin: 9px 0 0; font-size: 13px; line-height: 1.6; color: rgba(255, 255, 255, .78); max-width: 640px; }
.tp-hero__txt p b { color: #fff; font-weight: 600; }

.tp-btn-export {
    appearance: none; cursor: pointer; font-size: 12.5px; font-weight: 700; color: #fff;
    background: rgba(255, 255, 255, .14); border: 1px solid rgba(255, 255, 255, .28);
    padding: 10px 16px; border-radius: 12px; display: inline-flex; align-items: center; gap: 8px; flex: 0 0 auto;
}

/* ── KPI ──────────────────────────────────────────────────────────────── */
.tp-kpis { position: relative; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 22px; }
.tp-kpi {
    appearance: none; cursor: pointer; font: inherit; display: flex; flex-direction: column;
    padding: 13px 14px; border-radius: 14px; text-align: left; background: #fff;
    border: 1px solid rgba(255, 255, 255, .6); box-shadow: 0 6px 18px rgba(15, 23, 42, .1);
    transition: border-color .18s, box-shadow .18s, transform .18s;
}
.tp-kpi.is-static { cursor: default; }
.tp-kpi:hover:not(.is-static) { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(15, 23, 42, .08); }
.tp-kpi.is-on { box-shadow: 0 0 0 3px rgba(255, 255, 255, .35); }
.tp-kpi__row { display: flex; align-items: center; gap: 10px; width: 100%; }
.tp-kpi__ico { width: 32px; height: 32px; border-radius: 10px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; }
.tp-kpi__txt { flex: 1; min-width: 0; text-align: left; }
.tp-kpi__lbl { display: block; font-size: 9.5px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #8792a6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tp-kpi__num { display: flex; align-items: baseline; gap: 6px; margin-top: 4px; font-size: 22px; font-weight: 800; letter-spacing: -.03em; line-height: 1; font-variant-numeric: tabular-nums; color: #0f172a; }
.tp-kpi__num em { font-style: normal; font-size: 10px; font-weight: 700; }
.tp-kpi__track { display: block; height: 3px; border-radius: 99px; background: #eef2f7; margin-top: 11px; overflow: hidden; }
.tp-kpi__fill { display: block; height: 100%; border-radius: 99px; transition: width .6s cubic-bezier(.22, 1, .36, 1); }

/* ── SARINGAN ─────────────────────────────────────────────────────────── */
.tp-tool { margin-top: 16px; background: #fff; border: 1px solid #e7e3fb; border-radius: 16px; box-shadow: 0 6px 18px rgba(99, 102, 241, .06); }
.tp-tool__grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 9px; padding: 13px 14px; }
.tp-search { position: relative; min-width: 0; }
.tp-search svg { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); pointer-events: none; width: 15px; height: 15px; }
.tp-search input {
    width: 100%; padding: 10px 13px 10px 36px; border-radius: 11px; border: 1px solid #e2e8f0;
    background: #fff; font-size: 13px; color: #0f172a; outline: none; transition: border-color .16s; font-family: inherit;
}
.tp-search input:focus { border-color: #a5b4fc; }
.tp-search--plain svg { left: 12px; width: 14px; height: 14px; }

.tp-chips { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; padding: 0 16px 14px; animation: tpRowIn .22s ease both; }
.tp-chip {
    display: inline-flex; align-items: center; gap: 7px; padding: 5px 6px 5px 11px; border-radius: 9px;
    background: #eef2ff; border: 1px solid #c7d2fe; font-size: 11.5px; font-weight: 600; color: #3730a3; max-width: 100%;
}
.tp-chip__k { opacity: .7; flex: 0 0 auto; }
.tp-chip button { appearance: none; border: none; background: transparent; cursor: pointer; color: #4f46e5; display: flex; padding: 1px; flex: 0 0 auto; }
.tp-chips__clear { appearance: none; cursor: pointer; font-size: 11.5px; font-weight: 700; color: #64748b; background: transparent; border: none; padding: 5px 6px; text-decoration: underline; text-underline-offset: 2px; }

.tp-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 15px 2px 13px; }
.tp-checkall { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 12.5px; font-weight: 600; color: #334155; }
.tp-checkall input, .tpcard__top input { width: 15px; height: 15px; accent-color: #6366f1; cursor: pointer; }
.tp-bar__info { font-size: 12px; color: #64748b; }

/* ── KARTU ────────────────────────────────────────────────────────────── */
.tp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(318px, 1fr)); gap: 14px; align-items: start; min-height: 120px; }
.tpcard {
    position: relative; background: #fff; border: 1px solid #e7e3fb; border-radius: 18px;
    padding: 15px 16px 15px 18px; box-shadow: 0 6px 20px rgba(99, 102, 241, .07);
    animation: tpCardIn .42s cubic-bezier(.22, 1, .36, 1) both;
    transition: border-color .2s, box-shadow .2s, transform .2s;
}
.tpcard:hover { border-color: #a5b4fc; box-shadow: 0 18px 44px rgba(99, 102, 241, .18); transform: translateY(-3px); }
.tpcard.is-sel { border-color: #c7d2fe; }
.tpcard__spine { position: absolute; left: 0; top: 16px; bottom: 16px; width: 3px; border-radius: 0 3px 3px 0; }
.tpcard__top { display: flex; align-items: flex-start; gap: 11px; }
.tpcard__top input { margin-top: 11px; flex: 0 0 auto; }
.tpcard__ring { width: 40px; height: 40px; border-radius: 50%; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; padding: 2px; }
.tpcard__av { width: 100%; height: 100%; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12.5px; font-weight: 700; color: #fff; }
.tpcard__id { appearance: none; border: none; background: transparent; cursor: pointer; padding: 0; text-align: left; flex: 1; min-width: 0; }
.tpcard__nama { display: block; font-size: 14px; font-weight: 700; color: #0f172a; letter-spacing: -.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tpcard__mail { display: block; font-size: 11.5px; color: #94a3b8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.tp-badge { flex: 0 0 auto; display: inline-flex; align-items: center; font-size: 10px; font-weight: 800; letter-spacing: .06em; padding: 4px 9px; border-radius: 7px; white-space: nowrap; }

.tpcard__lines { display: flex; flex-direction: column; gap: 6px; margin-top: 13px; }
.tpcard__line { display: flex; align-items: center; gap: 8px; min-width: 0; font-size: 12.5px; color: #64748b; }
.tpcard__line.is-strong { color: #0f172a; font-weight: 600; }
.tpcard__line svg { width: 13px; height: 13px; flex: 0 0 auto; }

.tpcard__meta { display: flex; align-items: center; gap: 9px; margin-top: 13px; padding: 9px 12px; background: #f7f8fd; border: 1px solid #e7e3fb; border-radius: 11px; flex-wrap: wrap; }
.tpcard__chip { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; color: #4338ca; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 4px 9px; min-width: 0; }
.tpcard__chip svg { width: 12px; height: 12px; flex: 0 0 auto; }
.tpcard__skor { font-size: 11.5px; font-weight: 700; margin-left: auto; white-space: nowrap; }

.tpcard__tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 11px; }
.tpcard__tags span { font-size: 10.5px; font-weight: 600; color: #3730a3; background: #eef2ff; border: 1px solid #e0e7ff; border-radius: 7px; padding: 3px 8px; }

.tpcard__note { margin-top: 10px; }
.tpcard__note > div { font-size: 12px; line-height: 1.5; color: #64748b; }
.tpcard__note button { appearance: none; cursor: pointer; background: transparent; border: none; padding: 0; margin-top: 4px; font-size: 11.5px; font-weight: 700; color: #4f46e5; display: inline-flex; align-items: center; gap: 5px; }
.tpcard__note button:hover { color: #3730a3; }

.tpcard__foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-top: 13px; padding-top: 12px; border-top: 1px solid #f1f5f9; font-size: 11px; color: #94a3b8; }
.tpcard__act { display: flex; align-items: center; gap: 6px; flex: 0 0 auto; }

.tp-btn-primary {
    appearance: none; cursor: pointer; font-size: 12.5px; font-weight: 700; color: #fff; border: none;
    padding: 9px 14px; border-radius: 11px; background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 8px 18px rgba(99, 102, 241, .3); display: inline-flex; align-items: center; gap: 7px; font-family: inherit;
}
.tp-btn-primary:disabled { cursor: not-allowed; background: #c7cbe6; box-shadow: none; }
.tp-btn-ghost { appearance: none; cursor: pointer; font-size: 12.5px; font-weight: 700; color: #4338ca; background: #eef2ff; border: 1px solid #c7d2fe; padding: 9px 13px; border-radius: 11px; display: inline-flex; align-items: center; gap: 7px; font-family: inherit; }
.tp-btn-ghost2 { appearance: none; cursor: pointer; font-size: 12.5px; font-weight: 600; color: #334155; background: #fff; border: 1px solid #e2e8f0; padding: 9px 15px; border-radius: 11px; display: inline-flex; align-items: center; gap: 7px; font-family: inherit; }
.tp-btn-ghost2.is-wide { flex: 1; justify-content: center; }
.tp-btn-danger { appearance: none; cursor: pointer; font-size: 12.5px; font-weight: 700; color: #fff; background: #dc2626; border: none; padding: 10px 16px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center; gap: 7px; flex: 1; font-family: inherit; }
.tp-ico-btn { appearance: none; cursor: pointer; width: 32px; height: 32px; border-radius: 9px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; transition: all .16s; }
.tp-ico-btn:hover { border-color: #c7cbdb; color: #334155; }
.tp-ico-btn.is-danger { border-color: #fecaca; color: #dc2626; }

/* ── KOSONG & PAGINASI ────────────────────────────────────────────────── */
.tp-empty { padding: 50px 24px; text-align: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; }
.tp-empty__ico { width: 56px; height: 56px; border-radius: 15px; margin: 0 auto; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #cbd5e1; }
.tp-empty__t { font-size: 15px; font-weight: 800; margin-top: 14px; }
.tp-empty__s { font-size: 12.5px; color: #64748b; margin-top: 6px; }
.tp-empty .tp-btn-primary { margin-top: 15px; }
.tp-kosong { padding: 26px; text-align: center; font-size: 12.5px; color: #94a3b8; }

.tp-pager { display: flex; align-items: center; justify-content: space-between; gap: 13px; flex-wrap: wrap; margin-top: 16px; padding: 12px 16px; background: #fff; border: 1px solid #e7e3fb; border-radius: 16px; box-shadow: 0 6px 18px rgba(99, 102, 241, .06); }
.tp-pager__info, .tp-pager__per { font-size: 12px; color: #64748b; }
.tp-pager__per { display: flex; align-items: center; gap: 7px; }
.tp-pager__nav { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; justify-content: center; }
.tp-pg-btn { appearance: none; cursor: pointer; width: 32px; height: 32px; border-radius: 9px; border: 1px solid #e2e8f0; background: #fff; color: #334155; display: flex; align-items: center; justify-content: center; transition: all .16s; }
.tp-pg-btn:disabled { cursor: not-allowed; color: #cbd5e1; }
.tp-pg-num { appearance: none; cursor: pointer; min-width: 32px; height: 32px; padding: 0 10px; border-radius: 9px; font-size: 12px; font-weight: 700; border: 1px solid #e2e8f0; background: #fff; color: #334155; transition: all .16s; font-family: inherit; }
.tp-pg-num.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 8px 16px rgba(99, 102, 241, .3); }
.tp-pg-gap { font-size: 12px; font-weight: 700; color: #cbd5e1; padding: 0 3px; }

/* ── DETAIL ───────────────────────────────────────────────────────────── */
.tp-back { appearance: none; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; background: transparent; border: none; font-size: 12.5px; font-weight: 700; color: #64748b; padding: 0; margin-bottom: 12px; font-family: inherit; }
.tp-back:hover { color: #4f46e5; }

.tp-prof { position: relative; display: flex; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
.tp-prof__av { width: 60px; height: 60px; border-radius: 18px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; color: #fff; box-shadow: 0 12px 26px rgba(15, 23, 42, .24); }
.tp-prof__id { flex: 1; min-width: 0; }
.tp-prof__top { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.tp-badge-light { display: inline-flex; align-items: center; font-size: 10px; font-weight: 800; letter-spacing: .08em; padding: 5px 10px; border-radius: 7px; background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .3); color: #fff; }
.tp-prof__kode { font-size: 11px; font-weight: 600; color: rgba(255, 255, 255, .72); font-family: 'JetBrains Mono', monospace; }
.tp-prof__id h1 { margin: 11px 0 0; font-size: 24px; font-weight: 800; color: #fff; letter-spacing: -.03em; }
.tp-prof__sub { font-size: 12.5px; color: rgba(255, 255, 255, .78); margin-top: 5px; }
.tp-prof__act { display: flex; align-items: center; gap: 9px; flex: 0 0 auto; }
.tp-hero-primary { appearance: none; cursor: pointer; font-size: 12.5px; font-weight: 700; color: #4338ca; background: #fff; border: none; padding: 10px 16px; border-radius: 12px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 10px 22px rgba(15, 23, 42, .18); font-family: inherit; }
.tp-hero-ghost { appearance: none; cursor: pointer; font-size: 12.5px; font-weight: 700; color: #fff; background: rgba(255, 255, 255, .14); border: 1px solid rgba(255, 255, 255, .28); padding: 10px 15px; border-radius: 12px; font-family: inherit; }

.tp-tabs { display: flex; gap: 5px; margin-top: 16px; padding: 5px; border-radius: 14px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(15, 23, 42, .06); overflow-x: auto; }
.tp-tab { appearance: none; cursor: pointer; flex: 1; min-width: 132px; padding: 10px 12px; border-radius: 11px; border: none; font-size: 12.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all .18s; background: transparent; color: #64748b; font-family: inherit; }
.tp-tab.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 8px 18px rgba(99, 102, 241, .28); }

.tp-bio { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 14px; align-items: start; }
.tp-panel--wide { margin-top: 16px; }

/* PENILAIAN REKRUTER — catatan diberi ruang, bukan dipotong.
   `white-space: pre-line` menghormati enter yang diketik rekruter: catatan
   berbutir yang dirapatkan jadi satu paragraf kehilangan seluruh strukturnya. */
.tp-rek { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; padding-top: 14px; }
/* auto-fit, bukan jumlah kolom tetap: petak Skor bisa ada bisa tidak, dan
   grid berkolom-4 akan menyisakan lubang di ujung barisnya. */
.tp-rek__facts { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
.tp-fact { padding: 10px 12px; border-radius: 11px; background: #f8fafc; border: 1px solid #eef2f7; min-width: 0; }
.tp-fact span { display: block; font-size: 9.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #a2a9ba; }
.tp-fact b { display: block; font-size: 13px; font-weight: 800; color: #0f172a; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tp-rek__lbl { display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #a2a9ba; }
.tp-rek__isi { margin: 7px 0 0; font-size: 12.5px; line-height: 1.65; color: #334155; white-space: pre-line; word-break: break-word; }
.tp-rek__kosong { margin: 7px 0 0; font-size: 12px; line-height: 1.6; color: #94a3b8; }
.tp-rek__kosong b { color: #64748b; }
.tp-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px 17px; box-shadow: 0 1px 2px rgba(15, 23, 42, .06); animation: tpCardIn .38s cubic-bezier(.22, 1, .36, 1) both; }
.tp-panel__head { display: flex; align-items: center; gap: 9px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; font-size: 11.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #4338ca; }
.tp-panel__ico { width: 30px; height: 30px; border-radius: 9px; background: #eef2ff; border: 1px solid #c7d2fe; color: #4f46e5; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.tp-kv { display: flex; align-items: flex-start; gap: 12px; padding: 9px 0; border-bottom: 1px solid #f8fafc; }
.tp-kv__k { flex: 0 0 104px; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #a2a9ba; }
.tp-kv__v { flex: 1; min-width: 0; font-size: 12.5px; font-weight: 600; color: #0f172a; line-height: 1.5; word-break: break-word; }

.tp-docs { margin-top: 16px; background: #fff; border: 1px solid #e7e3fb; border-radius: 16px; box-shadow: 0 6px 18px rgba(99, 102, 241, .06); overflow: hidden; animation: tpCardIn .34s ease both; }
.tp-docs__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 15px 17px; border-bottom: 1px solid #f1f5f9; }
.tp-docs__ttl { display: flex; align-items: center; gap: 10px; font-size: 13.5px; font-weight: 800; color: #0f172a; }
.tp-docs__meter { display: flex; align-items: center; gap: 11px; min-width: 180px; flex: 1; max-width: 320px; }
.tp-meter { flex: 1; height: 6px; border-radius: 99px; background: #eef2f7; overflow: hidden; }
.tp-meter__fill { display: block; height: 100%; border-radius: 99px; transition: width .7s cubic-bezier(.22, 1, .36, 1); }
.tp-docs__num { font-size: 12.5px; font-weight: 800; font-variant-numeric: tabular-nums; flex: 0 0 auto; white-space: nowrap; }
.tp-docs__list { padding: 14px 17px; display: flex; flex-direction: column; gap: 9px; }
.docrow { display: flex; align-items: center; gap: 12px; padding: 11px 13px; border: 1px solid #eef0f7; border-radius: 12px; background: #fbfbfe; animation: tpCardIn .34s ease both; transition: background .16s, border-color .16s; }
.docrow:hover { background: #fbfbff; border-color: #d9def0; }
.docrow__ico { width: 36px; height: 36px; border-radius: 10px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; }
.docrow__ico.is-pdf { background: #fef2f2; color: #dc2626; }
.docrow__ico.is-gambar { background: #eef2ff; color: #4f46e5; }
.docrow__ico.is-lain { background: #f1f5f9; color: #64748b; }
.docrow__ico.is-kosong { background: #fffbeb; color: #d4a017; }
.docrow__ico.is-hilang { background: #fef2f2; color: #dc2626; }
.docrow.is-klik { cursor: pointer; }
.docrow.is-klik:hover { background: #f4f3ff; border-color: #c7d2fe; }
.docrow__open { flex: 0 0 auto; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 9px; border: 1px solid #d9def0; background: #fff; color: #4f46e5; }
.docrow.is-klik:hover .docrow__open { border-color: #a5b4fc; background: #eef2ff; }
.tp-preview { max-width: min(880px, 92vw); max-height: 88vh; border-radius: 14px; box-shadow: 0 30px 70px rgba(30, 27, 75, .4); background: #fff; }
.docrow__id { flex: 1; min-width: 0; }
.docrow__nama { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 13px; font-weight: 700; color: #0f172a; }
.docrow__tipe { font-size: 9px; font-weight: 700; letter-spacing: .06em; color: #8b93a7; background: #eef0f7; border-radius: 5px; padding: 2px 6px; flex: 0 0 auto; }
.docrow__meta { font-size: 11.5px; color: #94a3b8; margin-top: 2px; }

.tp-journey { margin-top: 16px; display: flex; flex-direction: column; gap: 12px; }
.tp-jcard { background: #fff; border: 1px solid #e6e9f3; border-radius: 16px; box-shadow: 0 1px 2px rgba(15, 23, 42, .06); overflow: hidden; animation: tpCardIn .36s cubic-bezier(.22, 1, .36, 1) both; }
.tp-jcard.is-open { border-color: #c7d2fe; }
.tp-jhead { appearance: none; cursor: pointer; font-family: inherit; width: 100%; display: flex; align-items: center; gap: 12px; padding: 15px 17px; background: #fff; border: none; transition: background .16s; }
.tp-jcard.is-open .tp-jhead { background: #fbfbff; }
.tp-jchev { width: 28px; height: 28px; border-radius: 9px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; transition: all .18s; border: 1px solid #e2e8f0; background: #f8fafc; color: #8792a6; }
.tp-jchev.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.tp-jchev svg { transition: transform .26s; }
.tp-jchev.is-on svg { transform: rotate(90deg); }
.tp-jhead__txt { flex: 1; min-width: 0; text-align: left; }
.tp-jhead__t { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 14px; font-weight: 800; color: #0f172a; }
.tp-jhead__m { display: block; font-size: 11.5px; color: #64748b; margin-top: 4px; font-weight: 500; }
.tp-jhead__r { display: flex; align-items: center; gap: 9px; flex: 0 0 auto; }
.tp-jstage { display: flex; flex-direction: column; align-items: flex-end; }
.tp-jstage span { font-size: 9.5px; font-weight: 700; letter-spacing: .08em; color: #a2a9ba; }
.tp-jstage b { font-size: 13px; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; }
.tp-jbar { width: 52px; height: 5px; border-radius: 99px; background: #eef2f7; overflow: hidden; }
.tp-jbar span { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); transition: width .6s ease; }

.tp-jbody { padding: 4px 18px 18px; border-top: 1px solid #f1f5f9; animation: tpRowIn .26s ease both; }
.tp-line-wrap { position: relative; padding-left: 30px; margin-top: 14px; }
.tp-line-rail { position: absolute; left: 12px; top: 6px; bottom: 8px; width: 2px; background: #eef2f7; }
.tp-step { position: relative; padding-bottom: 15px; }
.tp-step__dot { position: absolute; left: -30px; top: 0; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; border: 2px solid; }
.tp-step__row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.tp-step__id { min-width: 0; }
.tp-step__nama { display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; min-width: 0; }
.tp-step__no { flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; min-width: 19px; height: 19px; padding: 0 5px; border-radius: 6px; background: #f1f5f9; color: #8792a6; font-size: 10px; font-weight: 800; font-variant-numeric: tabular-nums; }
.tp-step__meta { font-size: 11px; color: #94a3b8; margin-top: 2px; }
.tp-step__r { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }
.tp-step__skor { font-size: 11px; font-weight: 700; color: #64748b; white-space: nowrap; }
.tp-jnote { display: flex; gap: 10px; padding: 11px 13px; border-radius: 11px; background: #f8fafc; border: 1px solid #eef2f7; font-size: 12px; line-height: 1.55; color: #64748b; }
.tp-jnote svg { flex: 0 0 auto; margin-top: 1px; }

/* ── BAR MASSAL ───────────────────────────────────────────────────────── */
.tp-bulkwrap { position: fixed; left: 0; right: 0; bottom: 0; z-index: 70; display: flex; justify-content: center; padding: 0 24px 18px; pointer-events: none; }
.tp-bulkbar { pointer-events: auto; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; max-width: 100%; padding: 10px 12px; border-radius: 14px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 14px 34px rgba(15, 23, 42, .16); animation: tpBarIn .24s ease both; }
.tp-bulkbar__n { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; flex: 0 0 auto; }
.tp-bulkbar__n b { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 7px; background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; font-size: 11.5px; }
.tp-bulkbar__sep { width: 1px; height: 22px; background: #e2e8f0; flex: 0 0 auto; }
.tp-bulkbar__act { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; min-width: 0; }
.tp-bulkbar__act button { appearance: none; cursor: pointer; font-size: 12px; font-weight: 600; color: #334155; background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 13px; border-radius: 10px; font-family: inherit; }
.tp-bulkbar__act button.is-danger { font-weight: 700; color: #dc2626; background: #fef2f2; border-color: #fecaca; }
.tp-bulk-fade-enter-active, .tp-bulk-fade-leave-active { transition: opacity .2s, transform .2s; }
.tp-bulk-fade-enter-from, .tp-bulk-fade-leave-to { opacity: 0; transform: translateY(12px); }

/* ── MODAL ────────────────────────────────────────────────────────────── */
.tp-scrim { position: fixed; inset: 0; z-index: 90; display: flex; align-items: center; justify-content: center; padding: 24px; background: rgba(30, 27, 75, .5); backdrop-filter: blur(6px); }
.tp-modal { max-width: 100%; max-height: 88vh; display: flex; flex-direction: column; background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 30px 70px rgba(30, 27, 75, .3); animation: tpModalIn .26s ease both; }
.tp-modal--md { width: 560px; }
.tp-modal--sm { width: 400px; }
.tp-modal__head { flex: 0 0 auto; display: flex; align-items: flex-start; gap: 12px; padding: 16px 19px; border-bottom: 1px solid #e8ebf4; }
.tp-modal__ico { width: 34px; height: 34px; border-radius: 10px; background: #eef2ff; border: 1px solid #c7d2fe; color: #4f46e5; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.tp-modal__ttl { flex: 1; min-width: 0; }
.tp-modal__ttl > div:first-child { font-size: 15.5px; font-weight: 800; letter-spacing: -.015em; }
.tp-modal__ttl > div:last-child { font-size: 12px; color: #64748b; margin-top: 2px; }
.tp-modal__body { flex: 1; min-height: 0; overflow-y: auto; padding: 16px 19px; background: #f8fafc; }
.tp-modal__body--gap { display: flex; flex-direction: column; gap: 14px; }
.tp-modal__foot { flex: 0 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 19px; border-top: 1px solid #e8ebf4; background: #fff; flex-wrap: wrap; }
.tp-modal__note { font-size: 11.5px; color: #94a3b8; min-width: 0; }
.tp-modal__btns { display: flex; align-items: center; gap: 9px; flex: 0 0 auto; }

.tp-vlist { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; min-height: 60px; }
.tp-vrow { appearance: none; cursor: pointer; font-family: inherit; width: 100%; display: flex; align-items: center; gap: 11px; padding: 10px 11px; border-radius: 12px; transition: all .16s; border: 1px solid #e6e9f3; background: #fff; text-align: left; }
.tp-vrow.is-on { border-color: #a5b4fc; background: #eef2ff; }
.tp-vrow__ico { width: 32px; height: 32px; border-radius: 9px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #8792a6; }
.tp-vrow.is-on .tp-vrow__ico { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.tp-vrow__id { flex: 1; min-width: 0; font-size: 13px; font-weight: 700; color: #0f172a; }
.tp-vrow__id small { display: block; font-size: 11.5px; font-weight: 500; color: #64748b; margin-top: 1px; }
.tp-vrow__check { flex: 0 0 auto; display: flex; }
.tp-vempty { padding: 18px; text-align: center; font-size: 12px; color: #a2a9ba; }

.tp-msec { margin-top: 16px; }
.tp-msec__lbl { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #a2a9ba; margin-bottom: 8px; }
.tp-msec__hint { font-size: 11.5px; color: #64748b; margin: 0 0 8px; line-height: 1.5; }
.tp-steps { display: flex; flex-direction: column; gap: 7px; }
.tp-steps__no { width: 24px; height: 24px; border-radius: 8px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; background: #f1f5f9; color: #8792a6; }
.tp-steps__no.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.tp-steps__skip { flex: 0 0 auto; font-size: 10px; font-weight: 700; color: #a2a9ba; }
.tp-statuslist { display: flex; flex-direction: column; gap: 7px; }
.tp-radio { width: 16px; height: 16px; border-radius: 50%; flex: 0 0 auto; margin-top: 2px; display: flex; align-items: center; justify-content: center; border: 2px solid #cbd5e1; }
.tp-radio.is-on { border-color: #6366f1; }
.tp-radio span { width: 8px; height: 8px; border-radius: 50%; background: #6366f1; display: none; }
.tp-radio.is-on span { display: block; }
.tp-tagsug { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.tp-tagsug button { appearance: none; cursor: pointer; font-size: 11px; font-weight: 600; color: #3730a3; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 7px; padding: 4px 9px; font-family: inherit; }

.tp-del { padding: 22px 20px 16px; text-align: center; }
.tp-del__ico { width: 46px; height: 46px; border-radius: 13px; margin: 0 auto; display: flex; align-items: center; justify-content: center; background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
.tp-del__t { font-size: 15px; font-weight: 800; margin-top: 13px; line-height: 1.4; }
.tp-del__s { font-size: 12px; color: #64748b; margin-top: 7px; line-height: 1.55; }
.tp-del__foot { display: flex; align-items: center; gap: 9px; padding: 13px 20px; border-top: 1px solid #e2e8f0; }

/* ── Element Plus disetel agar SATU BAHASA dengan rancangan ───────────── */
.tp-sel :deep(.el-select__wrapper) {
    padding: 4px 13px; border-radius: 11px; min-height: 40px; box-shadow: 0 0 0 1px #e2e8f0 inset;
    background: #fff; font-size: 13px; font-weight: 600;
}
.tp-sel :deep(.el-select__wrapper.is-focused) { box-shadow: 0 0 0 1px #a5b4fc inset; }
.tp-sel :deep(.el-select__placeholder) { color: #334155; font-weight: 600; }
.tp-sel--mini :deep(.el-select__wrapper) { min-height: 30px; padding: 2px 9px; border-radius: 9px; font-size: 12px; font-weight: 700; }
.tp-sel--mini { width: 84px; }
.tp-modal :deep(.el-input__wrapper), .tp-modal :deep(.el-textarea__inner) {
    border-radius: 11px; box-shadow: 0 0 0 1px #e2e8f0 inset; background: #fff; font-size: 13px;
}
.tp-modal :deep(.el-input__wrapper.is-focus), .tp-modal :deep(.el-textarea__inner:focus) { box-shadow: 0 0 0 1px #a5b4fc inset; }

/* ── RESPONSIF ────────────────────────────────────────────────────────── */
@media (max-width: 1180px) {
    .tp-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .tp-tool__grid { grid-template-columns: 1fr 1fr; }
    .tp-bio { grid-template-columns: 1fr; }
    .tp-rek { grid-template-columns: 1fr; }
    .tp-rek__facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 760px) {
    .tp-hero { border-radius: 20px; padding: 20px 18px; }
    .tp-hero__txt h1 { font-size: 22px; }
    .tp-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 18px; }
    .tp-tool__grid { grid-template-columns: 1fr; }
    .tp-grid { grid-template-columns: 1fr; }
    .tp-prof__act { width: 100%; }
    .tp-prof__act .tp-hero-primary { flex: 1; justify-content: center; }
    .tp-pager { justify-content: center; }
    .tp-bulkwrap { padding: 0 12px 12px; }
    /* Modal jadi lembar penuh dari bawah — jangkauan ibu jari, bukan tengah layar. */
    .tp-scrim { align-items: flex-end; padding: 0; }
    .tp-modal { width: 100% !important; border-radius: 20px 20px 0 0; max-height: 92vh; }
}
</style>
