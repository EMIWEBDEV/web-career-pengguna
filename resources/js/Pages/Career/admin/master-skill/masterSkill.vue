<!-- WEB CAREER — Master Skill. Data via web route + ResponseHelper (axios).

     SATU HALAMAN, DUA TAB (pola Master FAQ):
       - Skill    : 75 baris, paginasi server
       - Kategori : belasan baris, TANPA paginasi — dipakai juga sebagai sumber
                    pilihan dropdown di tab Skill, jadi harus utuh.

     PAGINASI, FILTER, PENCARIAN, dan URUT tab Skill dikerjakan SERVER — klien
     hanya memegang satu halaman, jadi semua angka ringkasan & cacah facet ikut
     datang dari server; menjumlahkan baris yang tampil akan salah.

     "Dipakai" = jumlah LOWONGAN yang mensyaratkan skill (tabel jembatan
     N_WEB_CAREERS_Detail_Skill_MPP). Di tab Kategori, "Dipakai" = jumlah SKILL. -->
<template>
    <Head title="Master Skill" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Skill</h1>
                <p>Keahlian yang disyaratkan lowongan (Excel, Komunikasi, Laravel, …), dikelompokkan per <b>kategori</b>. Dipasang saat menyusun <b>MPP</b>, lalu tampil di detail lowongan yang dibaca kandidat.</p>
            </div>
            <div class="wca-phead__actions">
                <button v-if="tab === 'skill'" class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Skill Baru
                </button>
                <button v-else class="wca-btn wca-btn--primary" @click="katOpenCreate">
                    <i class="bi bi-plus-lg"></i> Kategori Baru
                </button>
            </div>
        </div>

        <!-- Tab utama. Kategori tidak diberi menu sidebar sendiri: isinya belasan
             baris yang jarang berubah, dan tempatnya paling masuk akal di sini. -->
        <div class="wca-segt skl-tabs">
            <button class="wca-segt__it" :class="{ on: tab === 'skill' }" @click="tab = 'skill'">
                <i class="bi bi-stars"></i> Skill <span class="wca-tabn">{{ ringkasan.total }}</span>
            </button>
            <button class="wca-segt__it" :class="{ on: tab === 'kategori' }" @click="bukaTabKategori">
                <i class="bi bi-collection"></i> Kategori <span class="wca-tabn">{{ ringkasan.jmlKategori }}</span>
            </button>
        </div>

        <!-- ══════════════════════════ TAB SKILL ══════════════════════════ -->
        <template v-if="tab === 'skill'">
            <div class="wca-note wca-note--info">
                <i class="bi bi-info-circle"></i>
                <span>Skill yang sudah dipakai lowongan <b>tidak bisa dihapus</b> — nonaktifkan saja supaya data lama tetap utuh. Nama skill <b>harus unik</b>.</span>
            </div>

            <div class="wca-stats">
                <div class="wca-stat">
                    <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-stars"></i></span></div>
                    <div class="wca-stat__num">{{ ringkasan.total }}</div>
                    <div class="wca-stat__label">Total Skill</div>
                </div>
                <div class="wca-stat">
                    <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(16, 185, 129, 0.12); color: #059669"><i class="bi bi-check-circle"></i></span></div>
                    <div class="wca-stat__num">{{ ringkasan.aktif }}</div>
                    <div class="wca-stat__label">Aktif (jadi pilihan)</div>
                </div>
                <div class="wca-stat">
                    <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(245, 158, 11, 0.14); color: #b45309"><i class="bi bi-dash-circle"></i></span></div>
                    <div class="wca-stat__num">{{ ringkasan.belumDipakai }}</div>
                    <div class="wca-stat__label">Belum dipakai lowongan</div>
                </div>
                <div class="wca-stat">
                    <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(99, 102, 241, 0.12); color: #4f46e5"><i class="bi bi-clipboard-data"></i></span></div>
                    <div class="wca-stat__num">{{ ringkasan.dipakai }}</div>
                    <div class="wca-stat__label">Pemasangan di MPP</div>
                </div>
            </div>

            <!-- Halaman ini punya EMPAT dimensi saring (cari, kategori 12 nilai,
                 pemakaian, status). Dijadikan chip semua akan menghasilkan ~20 chip
                 dalam tiga baris — itu bukan filter, itu dinding. Kategori &
                 pemakaian dipadatkan jadi dropdown bercacah; status tetap
                 segmented karena cuma tiga nilai dan paling sering dipakai. -->
            <!-- Toolbar filter & bilah filter aktif -->
            <div class="wca-toolbar skl-toolbar">
                <div class="wca-search2 skl-search">
                    <i class="bi bi-search"></i>
                    <input
                        v-model="filters.q" type="text" placeholder="Cari skill…"
                        aria-label="Cari skill" @input="cariTertunda" @keyup.enter="reload" @keyup.esc="bersihkanCari"
                    />
                    <button v-if="filters.q" class="skl-clear" type="button" title="Bersihkan pencarian" @click="bersihkanCari">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <el-select
                    size="small"
                    v-model="filters.kategori" clearable filterable placeholder="Kategori"
                    class="skl-fsel skl-fsel--lg" :class="{ 'is-active': !!filters.kategori }" @change="saringBerubah"
                >
                    <template #prefix>
                        <i class="bi bi-folder2-open skl-prefix-icon"></i>
                    </template>
                    <el-option v-for="k in ringkasan.kategori" :key="k.id" :label="k.nama" :value="k.id">
                        <span class="skl-opt">
                            <i class="bi" :class="k.ikon || 'bi-tag'" :style="{ color: k.warna || '#4f46e5' }"></i>
                            <span class="skl-opt__nm">{{ k.nama }}</span>
                            <b>{{ k.jumlah }}</b>
                        </span>
                    </el-option>
                    <el-option v-if="ringkasan.kategoriBelum" label="Belum berkategori" value="BELUM">
                        <span class="skl-opt">
                            <i class="bi bi-question-circle" style="color: #b45309"></i>
                            <span class="skl-opt__nm">Belum berkategori</span>
                            <b>{{ ringkasan.kategoriBelum }}</b>
                        </span>
                    </el-option>
                </el-select>

                <el-select
                    size="small"
                    v-model="filters.pemakaian" clearable placeholder="Pemakaian"
                    class="skl-fsel" :class="{ 'is-active': !!filters.pemakaian }" @change="saringBerubah"
                >
                    <template #prefix>
                        <i class="bi bi-diagram-3 skl-prefix-icon"></i>
                    </template>
                    <el-option
                        v-for="p in pemakaianOpsi" :key="p.key" :label="p.label" :value="p.key"
                    >
                        <span class="skl-opt"><span class="skl-opt__nm">{{ p.label }}</span><b>{{ p.count }}</b></span>
                    </el-option>
                </el-select>

                <div class="wca-segt wca-segt--sm skl-segt" role="group" aria-label="Saring berdasarkan status">
                    <button
                        v-for="t in tabsStatus" :key="t.key" class="wca-segt__it"
                        :class="[{ on: filters.status === t.key }, t.key ? `st-${t.key.toLowerCase()}` : 'st-all']"
                        :aria-pressed="filters.status === t.key" @click="pilihStatus(t.key)"
                    >
                        <i class="bi" :class="t.icon"></i> <span class="skl-segt__lbl">{{ t.label }}</span>
                        <span class="wca-tabn">{{ t.count }}</span>
                    </button>
                </div>

                <button v-if="adaFilter" class="skl-quick-reset" type="button" title="Reset semua filter" @click="resetFilter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Reset</span>
                </button>
            </div>

            <!-- Bilah filter aktif -->
            <div v-if="adaFilter" class="skl-aktif">
                <span class="skl-aktif__lbl"><i class="bi bi-funnel-fill"></i> Filter aktif</span>
                <button
                    v-for="p in filterAktif" :key="p.kunci" type="button" class="skl-pill"
                    :style="p.warna ? { borderColor: p.warna + '66', color: p.warna } : null"
                    :title="`Lepas filter ${p.label}`" @click="lepasFilter(p.kunci)"
                >
                    <i class="bi" :class="p.ikon"></i> {{ p.label }}
                    <i class="bi bi-x-lg skl-pill__x"></i>
                </button>
                <span class="skl-aktif__sisa">
                    <b>{{ total }}</b> dari {{ ringkasan.total }} skill
                    <button class="skl-reset" type="button" @click="resetFilter">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset semua
                    </button>
                </span>
            </div>

            <div class="wca-card">
                <div class="wca-card__body--flush">
                    <div v-loading="loading" class="wca-tablewrap">
                        <table class="wca-table">
                            <thead>
                                <tr>
                                    <th>
                                        <button class="skl-sort" :class="{ on: sortBy === 'nama' }" @click="setSort('nama')">
                                            Skill <i class="bi" :class="sortIcon('nama')"></i>
                                        </button>
                                    </th>
                                    <th style="width: 210px">
                                        <button class="skl-sort" :class="{ on: sortBy === 'kategori' }" @click="setSort('kategori')">
                                            Kategori <i class="bi" :class="sortIcon('kategori')"></i>
                                        </button>
                                    </th>
                                    <th class="skl-hide-md">Keterangan</th>
                                    <th style="width: 140px">
                                        <button class="skl-sort" :class="{ on: sortBy === 'dipakai' }" @click="setSort('dipakai')">
                                            Dipakai <i class="bi" :class="sortIcon('dipakai')"></i>
                                        </button>
                                    </th>
                                    <th class="skl-hide-sm" style="width: 180px">
                                        <button class="skl-sort" :class="{ on: sortBy === 'baru' }" @click="setSort('baru')">
                                            Dibuat <i class="bi" :class="sortIcon('baru')"></i>
                                        </button>
                                    </th>
                                    <th style="width: 150px">Status</th>
                                    <th style="width: 90px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in list" :key="s.id" :class="{ 'is-off': s.status !== 'AKTIF' }">
                                    <td>
                                        <div class="skl-name">
                                            <span
                                                class="skl-ico"
                                                :style="s.kategori && s.kategori.warna ? { background: s.kategori.warna + '1f', color: s.kategori.warna } : null"
                                            >
                                                <i class="bi" :class="(s.kategori && s.kategori.ikon) || 'bi-stars'"></i>
                                            </span>
                                            <strong>{{ s.nama }}</strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            v-if="s.kategori" class="skl-katbadge"
                                            :style="s.kategori.warna ? { background: s.kategori.warna + '1a', color: s.kategori.warna, borderColor: s.kategori.warna + '40' } : null"
                                            :title="s.kategori.kode"
                                        >
                                            {{ s.kategori.nama }}
                                        </span>
                                        <span v-else class="wca-badge wca-b--amber">Belum berkategori</span>
                                    </td>
                                    <td class="skl-hide-md"><span class="skl-desc">{{ s.keterangan || '—' }}</span></td>
                                    <td>
                                        <span v-if="s.dipakai" class="wca-badge wca-b--indigo" :title="`Dipakai ${s.dipakai} lowongan`">{{ s.dipakai }} lowongan</span>
                                        <span v-else class="wca-badge wca-b--slate" title="Belum pernah dipakai lowongan mana pun">Belum dipakai</span>
                                    </td>
                                    <td class="skl-hide-sm"><AuditStamp :at="s.createdAt" :by="s.createdBy || 'SISTEM'" /></td>
                                    <td>
                                        <div class="skl-status">
                                            <el-switch :model-value="s.status === 'AKTIF'" @change="(v) => setStatus(s, v)" />
                                            <span class="skl-status__lbl" :class="s.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ s.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="skl-actions">
                                            <button class="wca-iconbtn" title="Ubah" @click="openEdit(s)"><i class="bi bi-pencil"></i></button>
                                            <button
                                                class="wca-iconbtn wca-iconbtn--danger"
                                                :disabled="!!s.dipakai"
                                                :title="s.dipakai ? `Tidak bisa dihapus — dipakai ${s.dipakai} lowongan` : 'Hapus'"
                                                :onClick="!!s.dipakai ? null : () => askRemove(s)"
                                            >
                                                <i class="bi" :class="s.dipakai ? 'bi-lock' : 'bi-trash'"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!loading && !list.length">
                                    <td colspan="7">
                                        <div class="wca-empty">
                                            <i class="bi" :class="adaFilter ? 'bi-funnel' : 'bi-stars'"></i>
                                            <h4>{{ adaFilter ? 'Tidak ada skill cocok' : 'Belum ada skill' }}</h4>
                                            <button v-if="adaFilter" class="wca-btn wca-btn--ghost skl-empty__btn" type="button" @click="resetFilter">
                                                <i class="bi bi-arrow-counterclockwise"></i> Reset filter
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="skl-pager">
                        <span class="skl-pager__info">
                            Menampilkan <b>{{ list.length }}</b> dari <b>{{ total.toLocaleString('id-ID') }}</b> skill
                        </span>
                        <el-pagination
                            background layout="prev, pager, next, sizes"
                            :total="total" :current-page="page" :page-size="perPage" :page-sizes="[5, 10, 25, 50, 100]"
                            @current-change="onPage" @size-change="onSize"
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- ════════════════════════ TAB KATEGORI ════════════════════════ -->
        <template v-else>
            <div class="wca-note wca-note--info">
                <i class="bi bi-info-circle"></i>
                <span>Kategori yang masih dipakai skill <b>tidak bisa dihapus</b> — pindahkan skill-nya dulu, atau nonaktifkan saja. <b>Kode dikunci</b> setelah dibuat karena dipakai skrip seed &amp; rujukan lintas sistem. <b>Urutan</b> menentukan susunan chip di tab Skill.</span>
            </div>

            <!-- Toolbar filter & bilah filter aktif tab Kategori -->
            <div class="wca-toolbar skl-toolbar">
                <div class="wca-search2 skl-search">
                    <i class="bi bi-search"></i>
                    <input
                        v-model="katQ" type="text" placeholder="Cari kategori…" aria-label="Cari kategori"
                        @input="katCariTertunda" @keyup.enter="katReload" @keyup.esc="katBersihkanCari"
                    />
                    <button v-if="katQ" class="skl-clear" type="button" title="Bersihkan pencarian" @click="katBersihkanCari">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <el-select
                    size="small"
                    v-model="katPemakaian" clearable placeholder="Pemakaian"
                    class="skl-fsel" :class="{ 'is-active': !!katPemakaian }" @change="katSaringBerubah"
                >
                    <template #prefix>
                        <i class="bi bi-diagram-3 skl-prefix-icon"></i>
                    </template>
                    <el-option
                        v-for="p in katPemakaianOpsi" :key="p.key" :label="p.label" :value="p.key"
                    >
                        <span class="skl-opt"><span class="skl-opt__nm">{{ p.label }}</span><b>{{ p.count }}</b></span>
                    </el-option>
                </el-select>

                <div class="wca-segt wca-segt--sm skl-segt" role="group" aria-label="Saring berdasarkan status kategori">
                    <button
                        v-for="t in katTabsStatus" :key="t.key" class="wca-segt__it"
                        :class="[{ on: katStatus === t.key }, t.key ? `st-${t.key.toLowerCase()}` : 'st-all']"
                        :aria-pressed="katStatus === t.key" @click="katPilihStatus(t.key)"
                    >
                        <i class="bi" :class="t.icon"></i> <span class="skl-segt__lbl">{{ t.label }}</span>
                        <span class="wca-tabn">{{ t.count }}</span>
                    </button>
                </div>

                <button v-if="katAdaFilter" class="skl-quick-reset" type="button" title="Reset semua filter" @click="katResetFilter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Reset</span>
                </button>
            </div>

            <div v-if="katAdaFilter" class="skl-aktif">
                <span class="skl-aktif__lbl"><i class="bi bi-funnel-fill"></i> Filter aktif</span>
                <button
                    v-for="p in katFilterAktif" :key="p.kunci" type="button" class="skl-pill"
                    :title="`Lepas filter ${p.label}`" @click="katLepasFilter(p.kunci)"
                >
                    <i class="bi" :class="p.ikon"></i> {{ p.label }}
                    <i class="bi bi-x-lg skl-pill__x"></i>
                </button>
                <span class="skl-aktif__sisa">
                    <b>{{ katTotal }}</b> dari {{ katRingkasan.total }} kategori
                    <button class="skl-reset" type="button" @click="katResetFilter">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset semua
                    </button>
                </span>
            </div>

            <div class="wca-card">
                <div class="wca-card__body--flush">
                    <div v-loading="katLoading" class="wca-tablewrap">
                        <table class="wca-table">
                            <thead>
                                <tr>
                                    <th style="width: 90px">
                                        <button class="skl-sort" :class="{ on: katSortBy === 'urutan' }" @click="katSetSort('urutan')">
                                            Urutan <i class="bi" :class="katSortIcon('urutan')"></i>
                                        </button>
                                    </th>
                                    <th style="width: 140px">
                                        <button class="skl-sort" :class="{ on: katSortBy === 'kode' }" @click="katSetSort('kode')">
                                            Kode <i class="bi" :class="katSortIcon('kode')"></i>
                                        </button>
                                    </th>
                                    <th>
                                        <button class="skl-sort" :class="{ on: katSortBy === 'nama' }" @click="katSetSort('nama')">
                                            Kategori <i class="bi" :class="katSortIcon('nama')"></i>
                                        </button>
                                    </th>
                                    <th style="width: 140px">
                                        <button class="skl-sort" :class="{ on: katSortBy === 'dipakai' }" @click="katSetSort('dipakai')">
                                            Dipakai <i class="bi" :class="katSortIcon('dipakai')"></i>
                                        </button>
                                    </th>
                                    <th style="width: 150px">Status</th>
                                    <th style="width: 90px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="k in katList" :key="k.id" :class="{ 'is-off': k.status !== 'AKTIF' }">
                                    <td><span class="wca-badge wca-b--slate">{{ k.urutan }}</span></td>
                                    <td><code class="skl-kode">{{ k.kode }}</code></td>
                                    <td>
                                        <div class="skl-name">
                                            <span class="skl-ico" :style="k.warna ? { background: k.warna + '1f', color: k.warna } : null">
                                                <i class="bi" :class="k.ikon || 'bi-tag'"></i>
                                            </span>
                                            <div class="skl-name__txt">
                                                <strong>{{ k.nama }}</strong>
                                                <small v-if="k.deskripsi">{{ k.deskripsi }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span v-if="k.dipakai" class="wca-badge wca-b--indigo">{{ k.dipakai }} skill</span>
                                        <span v-else class="wca-badge wca-b--slate">Kosong</span>
                                    </td>
                                    <td>
                                        <div class="skl-status">
                                            <el-switch :model-value="k.status === 'AKTIF'" @change="(v) => katSetStatus(k, v)" />
                                            <span class="skl-status__lbl" :class="k.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ k.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="skl-actions">
                                            <button class="wca-iconbtn" title="Ubah" @click="katOpenEdit(k)"><i class="bi bi-pencil"></i></button>
                                            <button
                                                class="wca-iconbtn wca-iconbtn--danger"
                                                :disabled="!!k.dipakai"
                                                :title="k.dipakai ? `Tidak bisa dihapus — dipakai ${k.dipakai} skill` : 'Hapus'"
                                                :onClick="!!k.dipakai ? null : () => katAskRemove(k)"
                                            >
                                                <i class="bi" :class="k.dipakai ? 'bi-lock' : 'bi-trash'"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!katLoading && !katList.length">
                                    <td colspan="6">
                                        <div class="wca-empty">
                                            <i class="bi" :class="katAdaFilter ? 'bi-funnel' : 'bi-collection'"></i>
                                            <h4>{{ katAdaFilter ? 'Tidak ada kategori cocok' : 'Belum ada kategori' }}</h4>
                                            <button v-if="katAdaFilter" class="wca-btn wca-btn--ghost skl-empty__btn" type="button" @click="katResetFilter">
                                                <i class="bi bi-arrow-counterclockwise"></i> Reset filter
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="skl-pager">
                        <span class="skl-pager__info">
                            Menampilkan <b>{{ katList.length }}</b> dari <b>{{ katTotal.toLocaleString('id-ID') }}</b> kategori
                        </span>
                        <el-pagination
                            background layout="prev, pager, next, sizes"
                            :total="katTotal" :current-page="katPage" :page-size="katPerPage" :page-sizes="[5, 10, 25, 50, 100]"
                            @current-change="katOnPage" @size-change="katOnSize"
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- ════════════════════════ MODAL SKILL ════════════════════════ -->
        <AdminModal
            :busy="saving" :show="show" icon="bi-stars"
            :title="editingId ? 'Ubah Skill' : 'Skill Baru'"
            subtitle="Keahlian yang disyaratkan lowongan MPP"
            :save-label="editingId ? 'Perbarui' : 'Simpan Skill'"
            foot-note="Nama harus unik — dipakai sebagai label pilihan di MPP."
            @close="show = false" @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-stars"></i> Data Skill</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Nama Skill</label>
                        <el-input v-model="form.nama" maxlength="100" show-word-limit placeholder="mis. Problem Solving" @keyup.enter="save" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Kategori <span class="skl-hint">(boleh dikosongkan, dirapikan belakangan)</span></label>
                        <el-select v-model="form.kategoriId" filterable clearable placeholder="Pilih kategori" style="width: 100%">
                            <el-option v-for="k in kategoriPilihan" :key="k.id" :label="k.nama" :value="k.id">
                                <span class="skl-opt"><i class="bi" :class="k.ikon || 'bi-tag'" :style="{ color: k.warna || '#4f46e5' }"></i> {{ k.nama }}</span>
                            </el-option>
                        </el-select>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Keterangan <span class="skl-hint">(opsional — tampil sebagai pendamping nama)</span></label>
                        <el-input v-model="form.keterangan" type="textarea" :rows="2" maxlength="100" show-word-limit placeholder="mis. Kemampuan analisis & pemecahan masalah" />
                    </div>
                </div>
            </div>

            <div v-if="editingId && editingDipakai" class="wca-note wca-note--info skl-note-modal">
                <i class="bi bi-clipboard-data"></i>
                <span>Skill ini dipakai <b>{{ editingDipakai }} lowongan</b>. Mengubah namanya ikut mengubah label pada lowongan tersebut.</span>
            </div>
        </AdminModal>

        <!-- ═══════════════════════ MODAL KATEGORI ═══════════════════════ -->
        <AdminModal
            :busy="katSaving" :show="katShow" icon="bi-collection"
            :title="katEditingId ? 'Ubah Kategori Skill' : 'Kategori Skill Baru'"
            subtitle="Pengelompokan skill di tab Skill"
            :save-label="katEditingId ? 'Perbarui' : 'Simpan Kategori'"
            foot-note="Kode dipakai skrip seed — dikunci setelah dibuat."
            @close="katShow = false" @save="katSave"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-collection"></i> Data Kategori</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">
                                Kode <span v-if="katEditingId" class="skl-hint">(terkunci)</span>
                            </label>
                            <el-input
                                v-model="katForm.kode" :disabled="!!katEditingId" maxlength="30" show-word-limit
                                placeholder="mis. SKL-DEV" @input="katForm.kode = katForm.kode.toUpperCase()"
                            />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Urutan <span class="skl-hint">(makin kecil makin depan)</span></label>
                            <el-input-number v-model="katForm.urutan" :min="0" :max="9999" :step="10" controls-position="right" style="width: 100%" />
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Nama Kategori</label>
                        <el-input v-model="katForm.nama" maxlength="100" show-word-limit placeholder="mis. Pengembangan Perangkat Lunak" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Deskripsi <span class="skl-hint">(opsional)</span></label>
                        <el-input v-model="katForm.deskripsi" type="textarea" :rows="2" maxlength="300" show-word-limit placeholder="Penjelasan singkat cakupan kategori ini" />
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Ikon</label>
                            <IconPicker v-model="katForm.ikon" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Warna</label>
                            <el-color-picker v-model="katForm.warna" :predefine="palet" />
                        </div>
                    </div>

                    <!-- Ikon & warna dipakai chip filter dan ikon baris skill,
                         jadi akibatnya terlihat sebelum disimpan. -->
                    <div class="skl-preview">
                        <span class="skl-preview__ico" :style="{ background: (katForm.warna || '#4f46e5') + '1f', color: katForm.warna || '#4f46e5' }">
                            <i class="bi" :class="katForm.ikon || 'bi-tag'"></i>
                        </span>
                        <div class="skl-preview__txt">
                            <b>{{ katForm.nama || 'Nama kategori' }}</b>
                            <small>Pratinjau chip filter &amp; ikon baris skill</small>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="katEditingId && katEditingDipakai" class="wca-note wca-note--info skl-note-modal">
                <i class="bi bi-stars"></i>
                <span>Kategori ini dipakai <b>{{ katEditingDipakai }} skill</b>. Mengubah nama/warna/ikon ikut mengubah tampilan skill tersebut.</span>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow" :busy="deleting" title="Hapus Skill" confirm-label="Ya, Hapus Skill"
            note="Skill akan dihapus permanen. Hanya skill yang belum dipakai lowongan yang bisa dihapus."
            @cancel="delShow = false" @confirm="confirmDelete"
        >
            Yakin ingin menghapus <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <ConfirmModal
            :show="katDelShow" :busy="katDeleting" title="Hapus Kategori Skill" confirm-label="Ya, Hapus Kategori"
            note="Kategori akan dihapus permanen. Hanya kategori yang belum dipakai skill yang bisa dihapus."
            @cancel="katDelShow = false" @confirm="katConfirmDelete"
        >
            Yakin ingin menghapus <strong>{{ katDelTarget?.nama }}</strong> ({{ katDelTarget?.kode }})?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import IconPicker from '@career/IconPicker.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-skill';
const API_KAT = '/api/v1/master-skill-kategori';
const CFG = { headers: { Accept: 'application/json' } };

const KOSONG = { nama: '', keterangan: '', kategoriId: '' };
const KAT_KOSONG = { kode: '', nama: '', deskripsi: '', ikon: '', warna: '#4f46e5', urutan: 0 };
const KAT_RINGKASAN_KOSONG = {
    total: 0, aktif: 0, nonaktif: 0, kosong: 0, urutanMaks: 0,
    status: { semua: 0, aktif: 0, nonaktif: 0 },
    pemakaian: { semua: 0, dipakai: 0, belum: 0 },
};
const RINGKASAN_KOSONG = {
    total: 0, aktif: 0, nonaktif: 0, dipakai: 0, belumDipakai: 0, jmlKategori: 0,
    status: { semua: 0, aktif: 0, nonaktif: 0 },
    pemakaian: { semua: 0, dipakai: 0, belum: 0 },
    kategori: [], kategoriSemua: 0, kategoriBelum: 0,
};

export default {
    components: { Head, AdminModal, AuditStamp, ConfirmModal, IconPicker },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-skill/masterSkill')],
    data() {
        return {
            tab: 'skill',

            // ── tab Skill (paginasi server) ──
            list: [], total: 0, page: 1, perPage: 25, loading: false,
            ringkasan: { ...RINGKASAN_KOSONG },
            filters: { q: '', status: '', pemakaian: '', kategori: '' },
            sortBy: 'nama', sortDir: 'asc',
            show: false, editingId: null, editingDipakai: 0, saving: false,
            form: { ...KOSONG },
            delShow: false, delTarget: null, deleting: false,

            // ── tab Kategori (tanpa paginasi — belasan baris) ──
            katList: [], katTotal: 0, katPage: 1, katPerPage: 25, katLoading: false,
            katRingkasan: { ...KAT_RINGKASAN_KOSONG },
            katQ: '', katStatus: '', katPemakaian: '',
            katSortBy: 'urutan', katSortDir: 'asc',
            katShow: false, katEditingId: null, katEditingDipakai: 0, katSaving: false,
            katForm: { ...KAT_KOSONG },
            katDelShow: false, katDelTarget: null, katDeleting: false,
            palet: ['#4f46e5', '#0284c7', '#0891b2', '#059669', '#65a30d', '#d97706', '#b45309', '#e11d48', '#db2777', '#7c3aed', '#475569', '#334155'],

            toast: '', tm: null, dtm: null, katDtm: null,
        };
    },
    computed: {
        /** el-select clearable mengirim null saat dikosongkan — dinormalkan di sini. */
        adaFilter() {
            const f = this.filters;
            return !!(f.q.trim() || f.status || f.pemakaian || f.kategori);
        },
        /**
         * Daftar filter yang sedang aktif, lengkap dengan cara melepasnya satu-satu.
         * Disusun di sini (bukan di template) supaya template tidak berisi
         * rangkaian v-if untuk tiap dimensi.
         */
        filterAktif() {
            const f = this.filters;
            const out = [];

            if (f.q.trim()) {
                out.push({ kunci: 'q', ikon: 'bi-search', label: `“${f.q.trim()}”` });
            }
            if (f.kategori) {
                if (f.kategori === 'BELUM') {
                    out.push({ kunci: 'kategori', ikon: 'bi-question-circle', label: 'Belum berkategori', warna: '#b45309' });
                } else {
                    const k = (this.ringkasan.kategori || []).find((x) => x.id === f.kategori);
                    out.push({ kunci: 'kategori', ikon: k?.ikon || 'bi-tag', label: k?.nama || 'Kategori', warna: k?.warna });
                }
            }
            if (f.pemakaian) {
                const p = this.pemakaianOpsi.find((x) => x.key === f.pemakaian);
                out.push({ kunci: 'pemakaian', ikon: 'bi-clipboard-data', label: p?.label || f.pemakaian });
            }
            if (f.status) {
                out.push({
                    kunci: 'status',
                    ikon: f.status === 'AKTIF' ? 'bi-check-circle' : 'bi-eye-slash',
                    label: f.status === 'AKTIF' ? 'Aktif' : 'Nonaktif',
                });
            }

            return out;
        },
        /** Cacah datang dari server: klien cuma memegang satu halaman. */
        tabsStatus() {
            const s = this.ringkasan.status || RINGKASAN_KOSONG.status;
            return [
                { key: '', label: 'Semua', icon: 'bi-collection', count: s.semua },
                { key: 'AKTIF', label: 'Aktif', icon: 'bi-check-circle', count: s.aktif },
                { key: 'NONAKTIF', label: 'Nonaktif', icon: 'bi-eye-slash', count: s.nonaktif },
            ];
        },
        /** Tanpa entri "Semua" — dropdown-nya clearable, jadi mengosongkan = semua. */
        pemakaianOpsi() {
            const p = this.ringkasan.pemakaian || RINGKASAN_KOSONG.pemakaian;
            return [
                { key: 'DIPAKAI', label: 'Sudah dipakai', count: p.dipakai },
                { key: 'BELUM', label: 'Belum dipakai', count: p.belum },
            ];
        },
        /**
         * Pilihan kategori di modal skill: hanya yang AKTIF, KECUALI kategori yang
         * sedang melekat pada baris ini — kalau disembunyikan, membuka lalu
         * menyimpan baris lama akan diam-diam mencopot kategorinya.
         */
        kategoriPilihan() {
            return (this.ringkasan.kategori || []).filter(
                (k) => k.status === 'AKTIF' || k.id === this.form.kategoriId
            );
        },
        /** Cacah datang dari server: klien cuma memegang satu halaman. */
        katStatusCounts() {
            return this.katRingkasan.status || KAT_RINGKASAN_KOSONG.status;
        },
        katPemakaianCounts() {
            return this.katRingkasan.pemakaian || KAT_RINGKASAN_KOSONG.pemakaian;
        },
        katTabsStatus() {
            const s = this.katStatusCounts;
            return [
                { key: '', label: 'Semua', icon: 'bi-collection', count: s.semua },
                { key: 'AKTIF', label: 'Aktif', icon: 'bi-check-circle', count: s.aktif },
                { key: 'NONAKTIF', label: 'Nonaktif', icon: 'bi-eye-slash', count: s.nonaktif },
            ];
        },
        katPemakaianOpsi() {
            const p = this.katPemakaianCounts;
            return [
                { key: 'DIPAKAI', label: 'Ada skill', count: p.dipakai },
                { key: 'BELUM', label: 'Kosong / Belum dipakai', count: p.belum },
            ];
        },
        katAdaFilter() {
            return !!(this.katQ.trim() || this.katStatus || this.katPemakaian);
        },
        katFilterAktif() {
            const out = [];
            if (this.katQ.trim()) {
                out.push({ kunci: 'q', ikon: 'bi-search', label: `“${this.katQ.trim()}”` });
            }
            if (this.katPemakaian) {
                const p = this.katPemakaianOpsi.find((x) => x.key === this.katPemakaian);
                out.push({ kunci: 'pemakaian', ikon: 'bi-collection-play', label: p?.label || this.katPemakaian });
            }
            if (this.katStatus) {
                out.push({
                    kunci: 'status',
                    ikon: this.katStatus === 'AKTIF' ? 'bi-check-circle' : 'bi-eye-slash',
                    label: this.katStatus === 'AKTIF' ? 'Aktif' : 'Nonaktif',
                });
            }
            return out;
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        // ═══════════ TAB SKILL ═══════════
        reload() {
            this.page = 1;
            this.load();
        },
        cariTertunda() {
            if (this.dtm) clearTimeout(this.dtm);
            this.dtm = setTimeout(() => this.reload(), 400);
        },
        bersihkanCari() {
            this.filters.q = '';
            this.reload();
        },
        pilihStatus(key) {
            this.filters.status = key;
            this.reload();
        },
        /** Dipakai el-select: nilai null dari tombol clear dinormalkan jadi ''. */
        saringBerubah() {
            this.filters.kategori = this.filters.kategori || '';
            this.filters.pemakaian = this.filters.pemakaian || '';
            this.reload();
        },
        lepasFilter(kunci) {
            this.filters[kunci] = '';
            this.reload();
        },
        resetFilter() {
            this.filters = { q: '', status: '', pemakaian: '', kategori: '' };
            this.reload();
        },
        onPage(p) {
            this.page = p;
            this.load();
        },
        onSize(s) {
            this.perPage = s;
            this.page = 1;
            this.load();
        },
        setSort(kolom) {
            if (this.sortBy === kolom) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy = kolom;
                this.sortDir = kolom === 'dipakai' ? 'desc' : 'asc';
            }
            this.reload();
        },
        sortIcon(kolom) {
            if (this.sortBy !== kolom) return 'bi-arrow-down-up';
            return this.sortDir === 'asc' ? 'bi-sort-down-alt' : 'bi-sort-down';
        },
        async load() {
            this.loading = true;
            try {
                const params = {
                    q: this.filters.q.trim(),
                    status: this.filters.status || '',
                    pemakaian: this.filters.pemakaian || '',
                    kategori: this.filters.kategori || '',
                    sortBy: this.sortBy,
                    sortDir: this.sortDir,
                    page: this.page,
                    perPage: this.perPage,
                };
                const res = await axios.get(API, { ...CFG, params });
                const r = res.data.result || {};
                this.list = r.rows || [];
                this.total = r.total || 0;
                this.ringkasan = r.ringkasan || { ...RINGKASAN_KOSONG };

                // Halaman terakhir bisa jadi kosong setelah baris dihapus/disaring.
                if (!this.list.length && this.page > 1) {
                    this.page = 1;
                    await this.load();
                }
            } catch (e) {
                this.notice('Gagal memuat data skill.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.editingDipakai = 0;
            this.form = { ...KOSONG };
            this.show = true;
        },
        openEdit(s) {
            this.editingId = s.id;
            this.editingDipakai = s.dipakai || 0;
            this.form = {
                nama: s.nama,
                keterangan: s.keterangan || '',
                kategoriId: s.kategori ? s.kategori.id : '',
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama skill wajib diisi.');
            this.saving = true;
            const payload = {
                nama: this.form.nama.trim(),
                keterangan: this.form.keterangan.trim(),
                kategoriId: this.form.kategoriId || null,
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Skill diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Skill ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan skill.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(s, v) {
            const prev = s.status;
            s.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${s.id}/toggle`, { aktif: v }, CFG);
                this.notice(`"${s.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
                await this.load();
            } catch (err) {
                s.status = prev;
                this.notice(err.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(s) {
            if (s.dipakai) return this.notice(`"${s.nama}" dipakai ${s.dipakai} lowongan — nonaktifkan saja.`);
            this.delTarget = s;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Skill dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus skill.');
            } finally {
                this.deleting = false;
            }
        },

        // ═══════════ TAB KATEGORI ═══════════
        /** Setiap perubahan filter/urut kembali ke halaman 1. */
        katReload() {
            this.katPage = 1;
            this.katLoad();
        },
        katCariTertunda() {
            if (this.katDtm) clearTimeout(this.katDtm);
            this.katDtm = setTimeout(() => this.katReload(), 400);
        },
        katBersihkanCari() {
            this.katQ = '';
            this.katReload();
        },
        katPilihStatus(key) {
            this.katStatus = key;
            this.katReload();
        },
        /** Dipakai el-select: nilai null dari tombol clear dinormalkan jadi ''. */
        katSaringBerubah() {
            this.katPemakaian = this.katPemakaian || '';
            this.katReload();
        },
        katLepasFilter(kunci) {
            if (kunci === 'q') this.katQ = '';
            if (kunci === 'status') this.katStatus = '';
            if (kunci === 'pemakaian') this.katPemakaian = '';
            this.katReload();
        },
        katResetFilter() {
            this.katQ = '';
            this.katStatus = '';
            this.katPemakaian = '';
            this.katReload();
        },
        katOnPage(p) {
            this.katPage = p;
            this.katLoad();
        },
        katOnSize(s) {
            this.katPerPage = s;
            this.katPage = 1;
            this.katLoad();
        },
        katSetSort(kolom) {
            if (this.katSortBy === kolom) {
                this.katSortDir = this.katSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.katSortBy = kolom;
                this.katSortDir = kolom === 'dipakai' ? 'desc' : 'asc';
            }
            this.katReload();
        },
        katSortIcon(kolom) {
            if (this.katSortBy !== kolom) return 'bi-arrow-down-up';
            return this.katSortDir === 'asc' ? 'bi-sort-down-alt' : 'bi-sort-down';
        },
        bukaTabKategori() {
            this.tab = 'kategori';
            if (!this.katList.length) this.katLoad();
        },
        async katLoad() {
            this.katLoading = true;
            try {
                const params = {
                    q: this.katQ.trim(),
                    status: this.katStatus || '',
                    pemakaian: this.katPemakaian || '',
                    sortBy: this.katSortBy,
                    sortDir: this.katSortDir,
                    page: this.katPage,
                    perPage: this.katPerPage,
                };
                const r = (await axios.get(API_KAT, { ...CFG, params })).data.result || {};
                this.katList = r.rows || [];
                this.katTotal = r.total || 0;
                this.katRingkasan = r.ringkasan || { ...KAT_RINGKASAN_KOSONG };

                // Halaman terakhir bisa jadi kosong setelah baris dihapus/disaring.
                if (!this.katList.length && this.katPage > 1) {
                    this.katPage = 1;
                    await this.katLoad();
                }
            } catch (e) {
                this.notice('Gagal memuat data kategori skill.');
            } finally {
                this.katLoading = false;
            }
        },
        /** Kategori berubah → ringkasan & pilihan kategori di tab Skill ikut berubah. */
        async katSegarkan() {
            await Promise.all([this.katLoad(), this.load()]);
        },
        katOpenCreate() {
            this.katEditingId = null;
            this.katEditingDipakai = 0;
            // Urutan diambil dari RINGKASAN server, bukan dari halaman yang tampil —
            // halaman 1 tidak tahu nilai terbesar kalau daftarnya sudah dipaginasi.
            this.katForm = { ...KAT_KOSONG, urutan: (this.katRingkasan.urutanMaks || 0) + 10 };
            this.katShow = true;
        },
        katOpenEdit(k) {
            this.katEditingId = k.id;
            this.katEditingDipakai = k.dipakai || 0;
            this.katForm = {
                kode: k.kode,
                nama: k.nama,
                deskripsi: k.deskripsi || '',
                ikon: k.ikon || '',
                warna: k.warna || '#4f46e5',
                urutan: k.urutan || 0,
            };
            this.katShow = true;
        },
        async katSave() {
            if (this.katSaving) return;
            if (!this.katEditingId && !this.katForm.kode.trim()) return this.notice('Kode kategori wajib diisi.');
            if (!this.katEditingId && !/^[A-Za-z0-9_-]+$/.test(this.katForm.kode.trim())) {
                return this.notice('Kode hanya boleh huruf, angka, tanda hubung, dan garis bawah.');
            }
            if (!this.katForm.nama.trim()) return this.notice('Nama kategori wajib diisi.');

            this.katSaving = true;
            const f = this.katForm;
            const payload = {
                nama: f.nama.trim(),
                deskripsi: f.deskripsi.trim(),
                ikon: (f.ikon || '').trim(),
                warna: (f.warna || '').trim(),
                urutan: f.urutan || 0,
            };
            try {
                if (this.katEditingId) {
                    await axios.put(`${API_KAT}/${this.katEditingId}`, payload, CFG);
                    this.notice('Kategori diperbarui.');
                } else {
                    await axios.post(API_KAT, { ...payload, kode: f.kode.trim().toUpperCase() }, CFG);
                    this.notice('Kategori ditambahkan.');
                }
                this.katShow = false;
                await this.katSegarkan();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan kategori.');
            } finally {
                this.katSaving = false;
            }
        },
        async katSetStatus(k, v) {
            const prev = k.status;
            k.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API_KAT}/${k.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Kategori "${k.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
                await this.katSegarkan();
            } catch (err) {
                k.status = prev;
                this.notice(err.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        katAskRemove(k) {
            if (k.dipakai) return this.notice(`Kategori "${k.nama}" dipakai ${k.dipakai} skill — pindahkan skill-nya dulu.`);
            this.katDelTarget = k;
            this.katDelShow = true;
        },
        async katConfirmDelete() {
            if (this.katDeleting || !this.katDelTarget) return;
            this.katDeleting = true;
            try {
                await axios.delete(`${API_KAT}/${this.katDelTarget.id}`, CFG);
                this.notice('Kategori dihapus.');
                this.katDelShow = false;
                this.katDelTarget = null;
                await this.katSegarkan();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus kategori.');
            } finally {
                this.katDeleting = false;
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
.skl-tabs { margin-bottom: 0.75rem; }

/* ── Toolbar & filter Container (Compact & Slim 32px) ── */
.skl-toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
    margin-bottom: 0.6rem;
}

/* ── Search Input Box ── */
.skl-search {
    flex: 0 0 170px;
    width: 170px;
    max-width: 170px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    transition: all 0.18s ease;
    padding: 0 0.55rem;
}
.skl-search i.bi-search {
    font-size: 12px;
    color: #64748b;
    margin-right: 4px;
    transition: color 0.18s ease;
}
.skl-search input {
    font-size: 12px;
    color: #0f172a;
    font-weight: 500;
}
.skl-search input::placeholder {
    color: #94a3b8;
}
.skl-search:focus-within {
    background: #ffffff;
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.12);
}
.skl-search:focus-within i.bi-search {
    color: #6366f1;
}

.skl-clear {
    flex: none;
    display: grid;
    place-items: center;
    width: 18px;
    height: 18px;
    padding: 0;
    border: none;
    border-radius: 999px;
    background: #e2e8f0;
    color: #64748b;
    font-size: 8.5px;
    cursor: pointer;
    transition: all 0.16s ease;
}
.skl-clear:hover {
    background: #fee2e2;
    color: #ef4444;
}

/* ── Element Plus Select Styling (Slim 32px) ── */
.skl-fsel {
    width: 130px !important;
    max-width: 130px !important;
    flex: 0 0 130px !important;
}
.skl-fsel--lg {
    width: 145px !important;
    max-width: 145px !important;
    flex: 0 0 145px !important;
}

.skl-prefix-icon {
    font-size: 12px;
    color: #64748b;
    margin-right: 3px;
    transition: color 0.18s ease;
}
:deep(.skl-fsel.is-active .skl-prefix-icon) {
    color: #4f46e5;
}

:deep(.skl-fsel .el-input__wrapper) {
    border-radius: 6px !important;
    background-color: #f8fafc !important;
    box-shadow: 0 0 0 1px #cbd5e1 inset !important;
    height: 32px !important;
    min-height: 32px !important;
    padding: 0 8px !important;
    font-size: 11.5px !important;
    font-weight: 500 !important;
    transition: all 0.18s ease !important;
}
:deep(.skl-fsel .el-input__wrapper:hover) {
    box-shadow: 0 0 0 1px #94a3b8 inset !important;
    background-color: #ffffff !important;
}
:deep(.skl-fsel .el-input__wrapper.is-focus) {
    background-color: #ffffff !important;
    box-shadow: 0 0 0 2px #6366f1 inset !important;
}
:deep(.skl-fsel.is-active .el-input__wrapper) {
    background-color: rgba(99, 102, 241, 0.04) !important;
    box-shadow: 0 0 0 1px #6366f1 inset !important;
}
:deep(.skl-fsel .el-input__inner) {
    font-size: 11.5px !important;
    font-weight: 500 !important;
    color: #1e293b !important;
    height: 32px !important;
}

/* Isi dropdown option */
.skl-opt {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
}
.skl-opt__nm {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 11.5px;
    font-weight: 500;
    color: #1e293b;
}
.skl-opt b {
    flex: none;
    min-width: 18px;
    padding: 1px 6px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
    font-size: 9.5px;
    font-weight: 700;
    text-align: center;
}

/* ── Segmented Control (Status Tabs - Slim 32px) ── */
.skl-segt {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 2px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    height: 32px;
}
.skl-segt .wca-segt__it {
    height: 26px;
    padding: 0 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.16s ease;
    border: none;
    background: transparent;
    cursor: pointer;
}
.skl-segt .wca-segt__it:hover {
    color: #4f46e5;
    background: rgba(255, 255, 255, 0.7);
}
.skl-segt .wca-segt__it.on {
    background: #ffffff;
    color: #4338ca;
    font-weight: 700;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}
.skl-segt .wca-segt__it.on.st-aktif {
    color: #047857;
}
.skl-segt .wca-segt__it.on.st-nonaktif {
    color: #b45309;
}
.skl-segt .wca-tabn {
    font-size: 9.5px;
    padding: 0 5px;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.18);
    color: #475569;
    font-weight: 700;
}
.skl-segt .wca-segt__it.on .wca-tabn {
    background: rgba(79, 70, 229, 0.12);
    color: #4338ca;
}
.skl-segt .wca-segt__it.on.st-aktif .wca-tabn {
    background: rgba(16, 185, 129, 0.14);
    color: #047857;
}
.skl-segt .wca-segt__it.on.st-nonaktif .wca-tabn {
    background: rgba(245, 158, 11, 0.14);
    color: #b45309;
}

/* ── Quick Reset Button on Toolbar ── */
.skl-quick-reset {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    height: 32px;
    padding: 0 9px;
    border-radius: 6px;
    border: 1px solid #fca5a5;
    background: #fef2f2;
    color: #dc2626;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.16s ease;
    margin-left: auto;
}
.skl-quick-reset:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}

/* ── Bilah filter aktif ── */
.skl-aktif {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-bottom: 0.65rem;
    padding: 0.35rem 0.65rem;
    border: 1px solid rgba(99, 102, 241, 0.18);
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.04) 0%, rgba(248, 250, 252, 0.8) 100%);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
}
.skl-aktif__lbl {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-right: 0.2rem;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: #4f46e5;
    text-transform: uppercase;
}
.skl-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 999px;
    background: #ffffff;
    font: 600 11px 'Plus Jakarta Sans', system-ui, sans-serif;
    color: #4338ca;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    transition: all 0.16s ease;
}
.skl-pill:hover {
    background: #fee2e2;
    border-color: #fca5a5 !important;
    color: #b91c1c !important;
}
.skl-pill__x {
    font-size: 9px;
    opacity: 0.6;
    transition: opacity 0.15s ease;
}
.skl-pill:hover .skl-pill__x {
    opacity: 1;
}

.skl-aktif__sisa {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
    font-size: 11.5px;
    font-weight: 600;
    color: #64748b;
}
.skl-aktif__sisa b {
    color: #4338ca;
    font-weight: 700;
}
.skl-reset {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 999px;
    background: #ffffff;
    font: 600 11px 'Plus Jakarta Sans', system-ui, sans-serif;
    color: #4338ca;
    cursor: pointer;
    transition: all 0.16s ease;
}
.skl-reset:hover {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #ffffff;
}

/* ── Tabel ── */
.skl-name { display: flex; align-items: center; gap: 10px; min-width: 0; }
.skl-ico {
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    font-size: 14px;
}
.skl-name strong { font-size: 13px; color: #0f1235; }
.skl-name__txt { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
.skl-name__txt small { font-size: 11px; color: #94a3b8; }
.skl-desc { color: #64748b; font-size: 12.5px; }
.skl-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.skl-note-modal { margin-top: 0.9rem; }
.skl-kode { font-family: 'JetBrains Mono', monospace; font-size: 11px; background: #eef0f7; color: #4f46e5; padding: 2px 8px; border-radius: 6px; }
.skl-katbadge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border: 1px solid rgba(79, 70, 229, 0.25);
    border-radius: 999px;
    background: rgba(79, 70, 229, 0.1);
    color: #4338ca;
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1.4;
}

.skl-preview { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px dashed rgba(79, 70, 229, 0.25); border-radius: 12px; background: rgba(79, 70, 229, 0.04); }
.skl-preview__ico { flex: none; width: 2.4rem; height: 2.4rem; border-radius: 0.7rem; display: grid; place-items: center; font-size: 1.1rem; }
.skl-preview__txt { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
.skl-preview__txt b { font-size: 13.5px; font-weight: 800; color: #0f1235; }
.skl-preview__txt small { font-size: 11px; color: #7c81a3; font-weight: 600; }

.skl-sort {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: none;
    background: none;
    padding: 0;
    font: inherit;
    color: inherit;
    cursor: pointer;
}
.skl-sort i { font-size: 11px; color: #94a3b8; }
.skl-sort:hover, .skl-sort.on { color: #4f46e5; }
.skl-sort.on i { color: #4f46e5; }

.skl-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.skl-status__lbl { font-size: 12px; font-weight: 700; }
.skl-status__lbl.is-on { color: #059669; }
.skl-status__lbl.is-off { color: #94a3b8; }

.skl-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
.skl-actions .wca-iconbtn:disabled { opacity: 0.45; cursor: not-allowed; }

.skl-empty__btn { margin-top: 0.9rem; }

.skl-pager {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.6rem;
    padding: 0.85rem 1.1rem;
    border-top: 1px solid var(--line, rgba(11, 16, 51, 0.08));
}
.skl-pager__info { font-size: 12px; font-weight: 600; color: #64748b; }
.skl-pager__info b { color: #0f1235; }

tr.is-off .skl-ico { background: rgba(148, 163, 184, 0.16) !important; color: #64748b !important; }
tr.is-off .skl-name strong { color: #64748b; }

@media (max-width: 1100px) {
    .skl-hide-md { display: none; }
}
@media (max-width: 900px) {
    .skl-hide-sm { display: none; }
    .skl-toolbar { gap: 0.45rem; }
    .skl-search { flex: 1 1 160px; max-width: 220px; }
}
@media (max-width: 640px) {
    .skl-search { flex: 1 1 100%; max-width: 100%; }
    .skl-fsel { flex: 1 1 calc(50% - 0.25rem) !important; max-width: 100% !important; width: auto !important; }
    .skl-fsel--lg { flex: 1 1 calc(50% - 0.25rem) !important; max-width: 100% !important; width: auto !important; }
    .skl-status__lbl { display: none; }
    .skl-segt { width: 100%; justify-content: space-between; }
    .skl-segt .wca-segt__it { flex: 1; justify-content: center; }
    .skl-quick-reset { width: 100%; justify-content: center; margin-left: 0; }
    .skl-segt__lbl { display: inline-block; }
    .skl-aktif__lbl { display: none; }
    .skl-aktif__sisa { width: 100%; margin-left: 0; justify-content: space-between; }
    .skl-pager { justify-content: center; }
    .skl-pager__info { width: 100%; text-align: center; }
}
</style>
