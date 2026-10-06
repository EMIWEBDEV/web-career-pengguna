<template>
    <div>
        <div class="mfb-inspector__head">
            <h3><i class="bi bi-sliders"></i> Properti Field</h3>
            <span class="mfb-inspector__tag">{{ field.tipe }}</span>
        </div>

        <!-- ── Identitas ── -->
        <div class="mfb-inspector__group">
            <label>Label Pertanyaan</label>
            <el-input v-model="field.label" placeholder="Label pertanyaan" @input="$emit('ubah-label')" />
        </div>

        <div class="mfb-inspector__group">
            <label>Tipe Input Field</label>
            <el-select v-model="field.tipe" style="width: 100%" @change="$emit('ubah-tipe')">
                <el-option v-for="t in tipeOptions" :key="t.value" :value="t.value" :label="t.label" />
            </el-select>
            <small class="mfb-help">Mengubah tipe akan membuang pengaturan yang tidak berlaku bagi tipe barunya.</small>
        </div>

        <div class="mfb-inspector__group">
            <label>Unique Key Identifier</label>
            <div class="mfb-key-input">
                <el-input
                    v-model="field.key"
                    :disabled="keyTerkunci"
                    :maxlength="MAKS_PANJANG_KEY"
                    placeholder="Otomatis dari label"
                    @blur="$emit('rapikan-key')"
                />
                <span v-if="keyTerkunci" class="mfb-lock-tag" title="Key terkunci karena formulir sudah dipublish">
                    <i class="bi bi-lock-fill"></i> Terkunci
                </span>
                <!-- Penghitung hanya muncul saat sudah dekat batas: angka yang
                     selalu terpampang cuma jadi latar yang tak pernah dibaca,
                     dan yang perlu diketahui admin justru ketika ruangnya menipis. -->
                <span v-else-if="panjangKey > MAKS_PANJANG_KEY - 12" class="mfb-key-hitung" :class="{ 'is-mentok': panjangKey >= MAKS_PANJANG_KEY }">
                    {{ panjangKey }}/{{ MAKS_PANJANG_KEY }}
                </span>
            </div>
            <small v-if="keyTerkunci" class="mfb-help">
                Sudah dipakai jawaban & berkas kandidat yang masuk, jadi tidak bisa diubah lagi. Label di atas tetap
                bebas diganti — keduanya memang tidak harus sama.
            </small>
            <small v-else-if="field.key_manual" class="mfb-help mfb-help--nyala">
                <i class="bi bi-pencil-fill"></i>
                Key ini diketik sendiri, jadi tidak lagi ikut berubah saat Label diganti.
                Kosongkan kotaknya untuk kembali mengikuti label.
            </small>
            <small v-else class="mfb-help">
                Kode unik pengenal kolom di database, dibuat otomatis dari label. Boleh diketik sendiri — label yang
                panjang tidak harus jadi key yang panjang.
            </small>
        </div>

        <div class="mfb-inspector__checks">
            <!-- Dimatikan (bukan disembunyikan) selagi ada `wajib_jika`: admin
                 tetap perlu melihat bahwa kolom ini punya aturan wajib, dan
                 di mana aturannya sekarang tinggal. Menyembunyikannya membuat
                 centang yang hilang terbaca sebagai fitur yang rusak. -->
            <el-checkbox v-model="field.wajib" :disabled="!!field.wajib_jika?.field">
                Wajib Diisi (Required)
            </el-checkbox>
            <!-- Label diperbaiki: artinya nilainya bisa dipakai MesinSyarat untuk
                 menggugurkan kandidat, bukan sekadar disaring di layar rekap. -->
            <el-checkbox v-if="punya('dapat_disaring')" v-model="field.dapat_disaring">
                Bisa dipakai syarat auto-gugur
            </el-checkbox>
        </div>
        <small v-if="field.wajib_jika?.field" class="mfb-help mfb-help--nyala">
            <i class="bi bi-asterisk"></i>
            Kewajiban kolom ini diatur bersyarat di bagian <b>Logika &amp; Kondisi</b> di bawah.
        </small>

        <!-- ── Cara mengisi: konfigurasi yang menentukan isi jawaban, mengikuti tipe ── -->
        <div class="mfb-inspector__sep"><span>Cara Mengisi</span></div>

        <div v-if="punya('ph')" class="mfb-inspector__group">
            <label>Text Placeholder</label>
            <el-input v-model="field.ph" placeholder="Contoh: Masukkan nama lengkap..." />
        </div>

        <div v-if="punya('opsi')" class="mfb-inspector__group">
            <label>Daftar Pilihan Opsi</label>
            <div v-for="(_, i) in field.opsi || []" :key="i" class="mfb-optrow">
                <el-input v-model="field.opsi[i]" placeholder="Nama opsi" />
                <button type="button" title="Hapus opsi" @click="field.opsi.splice(i, 1)">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <button class="mfb-mini" type="button" @click="tambahOpsi">
                <i class="bi bi-plus"></i> Tambah Opsi Baru
            </button>
        </div>

        <div v-if="punya('sumber_opsi')" class="mfb-inspector__group">
            <label>Opsi Dinamis dari Konteks</label>
            <el-input v-model="field.sumber_opsi" placeholder="Kosongkan bila memakai daftar opsi di atas" />
            <small class="mfb-help">Nama daftar yang dikirim halaman formulir. Bila diisi, daftar opsi di atas diabaikan.</small>
        </div>

        <div v-if="punya('bebas_ketik')" class="mfb-inspector__group">
            <el-checkbox v-model="field.bebas_ketik">Boleh mengetik jawaban di luar daftar</el-checkbox>
            <small class="mfb-help">Daftar tetap muncul sebagai saran, tapi kandidat tidak terkunci padanya.</small>
        </div>

        <template v-if="punya('min') || punya('maks')">
            <div class="mfb-inspector__row">
                <div class="mfb-inspector__group">
                    <label>Nilai Minimum</label>
                    <el-input-number v-model="field.min" controls-position="right" style="width: 100%" />
                </div>
                <div class="mfb-inspector__group">
                    <label>Nilai Maksimum</label>
                    <el-input-number v-model="field.maks" controls-position="right" style="width: 100%" />
                </div>
            </div>
        </template>

        <div v-if="punya('desimal')" class="mfb-inspector__group">
            <label>Jumlah Angka Desimal</label>
            <el-input-number v-model="field.desimal" :min="0" :max="4" style="width: 100%" />
            <small class="mfb-help">IPK memakai 2; jumlah orang memakai 0.</small>
        </div>

        <!-- ══ DAFTAR BUTIR ══════════════════════════════════════════════════
             Inilah yang membuat "Sebutkan minimal 5 hal" bisa ditegakkan.
             Sebelumnya angka 5 hanya ada di dalam kalimat labelnya, dan tidak
             ada satu pun yang membacanya — jawaban "ga,ga,ga,ga,ga" lolos. -->
        <template v-if="punya('min_butir') || punya('maks_butir')">
            <div class="mfb-inspector__row">
                <div v-if="punya('min_butir')" class="mfb-inspector__group">
                    <label>Minimal Jawaban</label>
                    <el-input-number v-model="field.min_butir" :min="1" :max="20" style="width: 100%" />
                </div>
                <div v-if="punya('maks_butir')" class="mfb-inspector__group">
                    <label>Maksimal Jawaban <span class="mfb-opsional">opsional</span></label>
                    <el-input-number v-model="field.maks_butir" :min="1" :max="20" style="width: 100%" />
                </div>
            </div>
            <small class="mfb-help">
                Kandidat langsung melihat sebanyak <b>minimal</b> kotak kosong, dan tidak bisa lanjut
                sebelum semuanya terisi. Kosongkan maksimal bila tidak ingin dibatasi.
            </small>
        </template>

        <div v-if="punya('maks_panjang')" class="mfb-inspector__group">
            <label>Panjang Maksimal Karakter</label>
            <el-input-number v-model="field.maks_panjang" :min="1" :max="500" style="width: 100%" />
        </div>

        <div v-if="punya('hanya_angka')" class="mfb-inspector__group">
            <el-checkbox v-model="field.hanya_angka">Hanya boleh angka</el-checkbox>
            <small class="mfb-help">
                Digabung dengan panjang maksimal, jawabannya dituntut TEPAT sepanjang itu — dipakai NIK (16 digit).
            </small>
        </div>

        <div v-if="punya('beda_dengan')" class="mfb-inspector__group">
            <label>Nilainya Harus Beda dengan</label>
            <el-select :model-value="field.beda_dengan || ''" style="width: 100%" clearable placeholder="Boleh sama dengan field mana pun" @change="(v) => (field.beda_dengan = v || undefined)">
                <el-option v-for="f in fieldLain" :key="f.field_id || f.key" :value="f.key" :label="f.label || f.key" />
            </el-select>
            <small class="mfb-help">Dipakai kontak darurat: nomor yang sama dengan nomor kandidat kehilangan gunanya.</small>
        </div>

        <template v-if="punya('accept')">
            <div class="mfb-inspector__group">
                <label>Format Berkas Diterima (Accept)</label>
                <el-input v-model="field.accept" placeholder=".pdf,.jpg,.jpeg,.png" />
            </div>
            <div class="mfb-inspector__group">
                <label>Batas Maksimal Ukuran (MB)</label>
                <el-input-number v-model="field.maks_mb" :min="1" :max="20" style="width: 100%" />
            </div>
        </template>

        <template v-if="punya('sumber')">
            <div class="mfb-inspector__group">
                <label>Sumber Master Data Referensi</label>
                <el-select v-model="field.sumber" style="width: 100%">
                    <el-option v-for="s in sumberReferensi" :key="s.value" :value="s.value" :label="s.label" />
                </el-select>
            </div>
            <div class="mfb-inspector__group">
                <label>Induk yang Wajib Diisi Dulu</label>
                <el-select :model-value="field.bergantung?.jenjang || ''" style="width: 100%" clearable placeholder="Tidak bergantung field lain" @change="aturBergantung">
                    <el-option v-for="f in fieldSebelumnya" :key="f.field_id || f.key" :value="f.key" :label="f.label || f.key" />
                </el-select>
                <small class="mfb-help">Selama induknya kosong, kolom ini terkunci dan daftarnya tidak dicari ke server.</small>
            </div>
            <div class="mfb-inspector__group">
                <label>Penyempit Daftar (Opsional)</label>
                <el-select :model-value="field.saring?.jenis || ''" style="width: 100%" clearable placeholder="Tanpa penyempit" @change="aturSaring">
                    <el-option v-for="f in fieldSebelumnya" :key="f.field_id || f.key" :value="f.key" :label="f.label || f.key" />
                </el-select>
            </div>
            <div class="mfb-inspector__group">
                <label>Placeholder Saat Terkunci</label>
                <el-input v-model="field.ph_terkunci" placeholder="Pilih jenjang pendidikan dulu" />
            </div>
        </template>

        <div v-if="punya('prefill')" class="mfb-inspector__group">
            <label><i class="bi bi-magic"></i> Isi Otomatis Dari</label>
            <el-select
                :model-value="field.prefill || ''"
                style="width: 100%"
                clearable
                placeholder="Tidak diisi otomatis"
                @change="(v) => (field.prefill = v || undefined)"
            >
                <el-option v-for="k in prefillTersedia" :key="k.kunci" :value="k.kunci" :label="k.label">
                    <span class="fr__opsi">{{ k.label }}</span>
                    <span class="fr__opsi-ket">{{ k.ket }}</span>
                </el-option>
            </el-select>
            <small v-if="!prefillTersedia.length" class="mfb-help">
                Belum ada sumber isi otomatis untuk konteks pemakaian formulir ini.
            </small>
            <small v-else-if="field.tipe !== 'prefill'" class="mfb-help">
                Nilainya terisi di muka tapi tetap BISA DIUBAH kandidat. Untuk yang terkunci, pakai tipe "Isi Otomatis
                (Terkunci)".
            </small>
        </div>

        <template v-if="punya('tipe_buka')">
            <div class="mfb-inspector__group">
                <label>Tipe Saat Dibuka untuk Disunting</label>
                <el-select v-model="field.tipe_buka" style="width: 100%" clearable placeholder="Teks biasa">
                    <el-option v-for="t in tipeBukaOptions" :key="t.value" :value="t.value" :label="t.label" />
                </el-select>
                <small class="mfb-help">Menentukan pemeriksaan formatnya — mis. telepon atau email.</small>
            </div>
            <div class="mfb-inspector__group">
                <label>Dibuka untuk Disunting Jika</label>
                <el-select
                    :model-value="field.buka_jika?.field || ''"
                    style="width: 100%"
                    clearable
                    placeholder="Selalu terkunci"
                    @change="aturBukaSyarat"
                >
                    <el-option v-for="f in fieldSebelumnya" :key="f.field_id || f.key" :value="f.key" :label="f.label || f.key" />
                </el-select>
                <div v-if="field.buka_jika?.field" class="mfb-condition-row">
                    <el-select v-model="field.buka_jika.operator" style="width: 48%">
                        <el-option v-for="o in operatorOptions" :key="o.value" :value="o.value" :label="o.label" />
                    </el-select>
                    <el-input v-model="field.buka_jika.nilai" placeholder="Nilai pemicu" style="width: 52%" />
                </div>
            </div>
        </template>

        <!-- ── Tampilan: lebar dan kapan lebarnya berubah ── -->
        <div class="mfb-inspector__sep"><span>Tampilan</span></div>

        <div class="mfb-inspector__group">
            <label>Lebar Presets Layout</label>
            <div class="mfb-segmented">
                <button
                    v-for="opt in lebarOptions"
                    :key="opt.value"
                    type="button"
                    class="mfb-segmented__item"
                    :class="{ active: lebarAktif === opt.value }"
                    @click="aturLebar(opt.value)"
                >
                    {{ opt.label }}
                </button>
            </div>
            <div class="mfb-slider">
                <span>33%</span>
                <el-slider
                    v-model="field.lebar_persen"
                    :min="33"
                    :max="100"
                    :step="1"
                    :format-tooltip="(v) => `${v}%`"
                    @change="$emit('rapikan-lebar')"
                />
                <span>100%</span>
            </div>
        </div>

        <div class="mfb-inspector__group">
            <label><i class="bi bi-arrows-angle-expand"></i> Lebar Berubah Jika (Kondisional)</label>
            <el-select
                :model-value="field.lebar_jika?.field || ''"
                style="width: 100%"
                clearable
                placeholder="Lebar selalu tetap"
                @change="aturLebarSyarat"
            >
                <!-- Sengaja menyertakan field ini sendiri: pemakaian utamanya
                     memang begitu — sebuah field menyusut ketika JAWABANNYA
                     SENDIRI memunculkan field pendamping di sebelahnya. -->
                <el-option v-for="f in semuaField" :key="f.field_id || f.key" :value="f.key" :label="f.label || f.key" />
            </el-select>
            <template v-if="field.lebar_jika?.field">
                <div class="mfb-condition-row">
                    <el-select v-model="field.lebar_jika.operator" style="width: 48%">
                        <el-option v-for="o in operatorOptions" :key="o.value" :value="o.value" :label="o.label" />
                    </el-select>
                    <el-input v-model="field.lebar_jika.nilai" placeholder="Nilai pemicu" style="width: 52%" />
                </div>
                <div class="mfb-segmented" style="margin-top: 0.5rem">
                    <button
                        v-for="opt in lebarOptions"
                        :key="opt.value"
                        type="button"
                        class="mfb-segmented__item"
                        :class="{ active: field.lebar_jika.lebar_persen === opt.value }"
                        @click="field.lebar_jika.lebar_persen = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </template>
        </div>

        <!-- ── Logika: hubungan antar field ── -->
        <div class="mfb-inspector__sep"><span>Logika &amp; Kondisi</span></div>

        <div class="mfb-inspector__group">
            <label><i class="bi bi-diagram-3"></i> Logika Tampil Jika (Kondisional)</label>
            <el-select
                :model-value="field.tampil_jika?.field || ''"
                style="width: 100%"
                clearable
                placeholder="Selalu Tampil (Tanpa Syarat)"
                @change="aturTampilSyarat"
            >
                <el-option v-for="f in fieldSebelumnya" :key="f.field_id || f.key" :value="f.key" :label="f.label || f.key" />
            </el-select>
            <small class="mfb-help">
                Hanya field yang letaknya sebelum field ini — syarat pada field di belakangnya tidak akan pernah
                terpenuhi saat kandidat mengisi.
            </small>
            <div v-if="field.tampil_jika?.field" class="mfb-condition-row">
                <el-select v-model="field.tampil_jika.operator" style="width: 48%">
                    <el-option v-for="o in operatorOptions" :key="o.value" :value="o.value" :label="o.label" />
                </el-select>
                <el-input v-model="field.tampil_jika.nilai" placeholder="Nilai pemicu" style="width: 52%" />
            </div>
        </div>

        <!-- WAJIB BERSYARAT.
             Sengaja bertetangga dengan "Tampil Jika": keduanya menjawab
             pertanyaan yang mirip, dan menaruhnya berjauhan membuat admin
             memakai yang satu untuk pekerjaan yang satunya. Bedanya ditulis
             terus terang di bantuan di bawah, karena justru itu yang paling
             sering tertukar. -->
        <div class="mfb-inspector__group">
            <label><i class="bi bi-asterisk"></i> Wajib Diisi Hanya Jika (Kondisional)</label>
            <el-select
                :model-value="field.wajib_jika?.field || ''"
                style="width: 100%"
                clearable
                :placeholder="field.wajib ? 'Selalu wajib' : 'Tidak pernah wajib'"
                :disabled="field.tipe === 'prefill'"
                @change="aturWajibSyarat"
            >
                <el-option v-for="f in fieldSebelumnya" :key="f.field_id || f.key" :value="f.key" :label="f.label || f.key" />
            </el-select>
            <div v-if="field.wajib_jika?.field" class="mfb-condition-row">
                <el-select v-model="field.wajib_jika.operator" style="width: 48%">
                    <el-option v-for="o in operatorOptions" :key="o.value" :value="o.value" :label="o.label" />
                </el-select>
                <el-input v-model="field.wajib_jika.nilai" placeholder="Nilai pemicu" style="width: 52%" />
            </div>
            <small v-if="field.wajib_jika?.field" class="mfb-help mfb-help--nyala">
                <i class="bi bi-info-circle"></i>
                Selama syarat ini tidak terpenuhi, kolomnya boleh dikosongkan dan bintang merahnya ikut hilang.
                Centang <b>Wajib Diisi</b> di atas jadi tidak berlaku — syarat ini yang menggantikannya.
            </small>
            <small v-else class="mfb-help">
                Pakai ini untuk isian yang tetap TERLIHAT tapi baru mengikat pada jawaban tertentu — mis. "Nama
                Pasangan" yang wajib hanya bila status pernikahan "Menikah". Untuk isian yang sebaiknya HILANG sama
                sekali, pakai "Tampil Jika" di atas.
            </small>
        </div>

        <div v-if="punya('reset_anak')" class="mfb-inspector__group">
            <label>Kosongkan Field Ini Saat Jawaban Berubah</label>
            <el-select
                :model-value="field.reset_anak || []"
                multiple
                collapse-tags
                style="width: 100%"
                placeholder="Tidak mengosongkan apa pun"
                @change="(v) => (field.reset_anak = v.length ? v : undefined)"
            >
                <el-option v-for="f in fieldSesudahnya" :key="f.field_id || f.key" :value="f.key" :label="f.label || f.key" />
            </el-select>
            <small class="mfb-help">
                Tanpa ini, mengganti jawaban induk meninggalkan jawaban anak yang sudah tidak cocok — dan jawaban basi
                itu ikut terkirim.
            </small>
        </div>

        <!-- ── Bantuan ── -->
        <div class="mfb-inspector__sep"><span>Bantuan</span></div>

        <div class="mfb-inspector__group">
            <label>Teks Bantuan / Keterangan</label>
            <el-input v-model="field.bantuan" type="textarea" :rows="2" placeholder="Keterangan kecil di bawah field..." />
        </div>

        <button class="mfb-btn mfb-btn--danger mfb-btn--full" type="button" @click="$emit('hapus')">
            <i class="bi bi-trash"></i> Hapus Field Ini
        </button>
    </div>
</template>

<script>
/**
 * Panel properti field Master Formulir.
 *
 * Kotak yang muncul ditentukan KATALOG (inti/katalogField.js), bukan rentetan
 * v-if per tipe. Itu sebabnya "Bisa dipakai syarat auto-gugur" hilang sendiri
 * dari paragraf/berkas/foto/persetujuan, dan placeholder tidak lagi ditawarkan
 * ke tipe yang renderernya tidak membacanya.
 *
 * Dipisah dari masterFormulir.vue yang sudah 4000+ baris — panel ini punya satu
 * tanggung jawab dan bisa dibaca utuh sekali duduk.
 *
 * Gaya kelas mfb-inspector__* disalin dari masterFormulir.vue, karena `<style>`
 * induk bersifat scoped dan tidak menjangkau komponen anak.
 */
import { daftarTipe, propertiTipe } from '@utils/formulir/katalogField';
import { MAKS_PANJANG_KEY } from '@utils/formulir/schema';

export default {
    name: 'PropertiField',
    props: {
        field: { type: Object, required: true },
        semuaField: { type: Array, default: () => [] },
        keyTerkunci: { type: Boolean, default: false },
        katalogPrefill: { type: Array, default: () => [] },
        kunciPrefill: { type: Array, default: () => [] },
        operatorOptions: { type: Array, default: () => [] },
        lebarOptions: { type: Array, default: () => [] },
    },
    emits: ['ubah-tipe', 'ubah-label', 'rapikan-key', 'rapikan-lebar', 'hapus'],
    data() {
        return {
            sumberReferensi: [
                { value: 'jenjang', label: 'Jenjang Pendidikan' },
                { value: 'jenis_institusi', label: 'Jenis Institusi' },
                { value: 'kampus', label: 'Nama Kampus / Perguruan Tinggi' },
                { value: 'prodi', label: 'Program Studi / Jurusan' },
            ],
            tipeBukaOptions: [
                { value: 'text', label: 'Teks' },
                { value: 'phone', label: 'Nomor Telepon' },
                { value: 'email', label: 'Email' },
            ],
        };
    },
    computed: {
        /** Batas panjang key = lebar kolom Field_Key. Lihat schema.js. */
        MAKS_PANJANG_KEY: () => MAKS_PANJANG_KEY,
        panjangKey() {
            return String(this.field.key || '').length;
        },
        tipeOptions() {
            return daftarTipe().map((t) => ({ value: t.value, label: t.label }));
        },
        indeksSaya() {
            return this.semuaField.findIndex((f) => f.field_id === this.field.field_id);
        },
        fieldLain() {
            return this.semuaField.filter((f) => f.key && f.field_id !== this.field.field_id);
        },
        /** Acuan syarat harus di depan — lihat keterangan di bawah dropdown. */
        fieldSebelumnya() {
            const i = this.indeksSaya;
            return (i < 0 ? this.semuaField : this.semuaField.slice(0, i)).filter((f) => f.key);
        },
        /** Yang dikosongkan pasti di belakang: anak cascade selalu ditanya sesudah induknya. */
        fieldSesudahnya() {
            const i = this.indeksSaya;
            return (i < 0 ? this.semuaField : this.semuaField.slice(i + 1)).filter((f) => f.key);
        },
        prefillTersedia() {
            const boleh = new Set(this.kunciPrefill);
            return this.katalogPrefill.filter((k) => boleh.has(k.kunci));
        },
        lebarAktif() {
            const v = Number(this.field.lebar_persen || 33);
            if (v >= 95) return 100;
            if (v >= 60) return 67;
            if (v >= 45) return 50;
            return 33;
        },
    },
    methods: {
        punya(prop) {
            return propertiTipe(this.field.tipe).includes(prop);
        },
        tambahOpsi() {
            if (!Array.isArray(this.field.opsi)) this.field.opsi = [];
            this.field.opsi.push('Opsi Baru');
        },
        aturLebar(v) {
            this.field.lebar_persen = Number(v || 33);
            this.$emit('rapikan-lebar');
        },
        aturLebarSyarat(key) {
            this.field.lebar_jika = key ? { field: key, operator: '=', nilai: '', lebar_persen: 50 } : null;
        },
        aturTampilSyarat(key) {
            this.field.tampil_jika = key ? { field: key, operator: '=', nilai: '' } : null;
        },
        /**
         * Memasang syarat wajib sekaligus MEMATIKAN centang `wajib`.
         *
         * Keduanya menjawab pertanyaan yang sama ("kapan kolom ini mengikat?"),
         * dan membiarkan keduanya menyala membuat inspector menampilkan dua
         * jawaban berbeda untuk satu pertanyaan - sementara mesin aturannya
         * hanya menuruti salah satu (wajib_jika menang). Yang tampak di layar
         * harus sama dengan yang benar-benar berlaku.
         */
        aturWajibSyarat(key) {
            if (key) {
                this.field.wajib_jika = { field: key, operator: '=', nilai: '' };
                this.field.wajib = false;

                return;
            }

            this.field.wajib_jika = null;
        },
        aturBukaSyarat(key) {
            this.field.buka_jika = key ? { field: key, operator: '=', nilai: '' } : undefined;
        },
        aturBergantung(key) {
            this.field.bergantung = key ? { jenjang: key } : undefined;
        },
        aturSaring(key) {
            this.field.saring = key ? { jenis: key } : undefined;
        },
    },
};
</script>

<style scoped>
/* ── NATIVE SEGMENTED BUTTON GROUP (disalin dari masterFormulir.vue) ── */
.mfb-segmented {
    display: flex;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    padding: 3px;
    gap: 3px;
    width: 100%;
}

.mfb-segmented__item {
    flex: 1;
    border: 0;
    background: transparent;
    color: #64748b;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    padding: 0.5rem 0.75rem;
    border-radius: 9px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
}

.mfb-segmented__item:hover:not(.active) {
    color: #0f172a;
    background: rgba(255, 255, 255, 0.6);
}

.mfb-segmented__item.active {
    background: #ffffff;
    color: #4f46e5;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.2);
}

/* Button Variants */
.mfb-btn {
    border: 0;
    border-radius: 10px;
    padding: 0.6rem 1.1rem;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.mfb-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    box-shadow: none !important;
}

.mfb-btn--danger {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}

.mfb-btn--danger:hover:not(:disabled) {
    background: #fca5a5;
}

.mfb-btn--full {
    width: 100%;
}

/* Inspector */
.mfb-inspector__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid #f1f5f9;
}

.mfb-inspector__head h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 800;
    display: flex;
    gap: 0.4rem;
    align-items: center;
    color: #0f172a;
}

/* Pemisah antar babak properti — panel disusun mengikuti alur membangun:
   identitas → aturan → cara mengisi → tampilan → logika → bantuan. */
.mfb-inspector__sep {
    margin: 1.1rem 0 0.9rem;
    padding-top: 0.8rem;
    border-top: 1px dashed #e2e8f0;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #94a3b8;
    text-transform: uppercase;
}

.mfb-inspector__tag {
    background: #eef2ff;
    color: #4338ca;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 10px;
    font-family: monospace;
}

.mfb-inspector__group {
    margin-bottom: 0.95rem;
}

.mfb-inspector__group label {
    display: block;
    font-size: 11.5px;
    font-weight: 800;
    color: #475569;
    margin-bottom: 0.35rem;
}

.mfb-key-input {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.mfb-lock-tag {
    background: #fee2e2;
    color: #b91c1c;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    white-space: nowrap;
}

.mfb-help {
    font-size: 11px;
    color: #64748b;
    margin-top: 0.25rem;
}

/* Bantuan yang menerangkan aturan yang SEDANG BERLAKU, bukan sekadar penjelasan
   umum -- dibedakan warnanya supaya terbaca sebagai keadaan, bukan basa-basi. */
.mfb-help--nyala {
    display: block;
    color: #4f46e5;
    background: #eef2ff;
    border: 1px solid #e0e7ff;
    border-radius: 7px;
    padding: 0.4rem 0.55rem;
    line-height: 1.5;
}

.mfb-help--nyala .bi {
    margin-right: 0.25rem;
}

.mfb-inspector__checks {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 0.95rem;
    background: #f8fafc;
    padding: 0.65rem;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
}

.mfb-slider {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 0.55rem;
    align-items: center;
    color: #64748b;
    font-size: 11.5px;
    margin-top: 0.4rem;
}

.mfb-condition-row {
    display: flex;
    gap: 0.4rem;
    margin-top: 0.4rem;
}

/* TOMBOL KECIL SEKUNDER ("+ Tambah Opsi Baru", "Reset Filter").
   Kelas ini dipakai sejak awal tapi TIDAK PERNAH punya aturan di berkas mana
   pun, jadi tombolnya jatuh ke tampilan bawaan peramban -- kotak abu-abu yang
   tampak seperti sisa markup, bukan tombol yang boleh ditekan. */
.mfb-mini {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.4rem 0.7rem;
    border: 1px dashed #c7d2fe;
    border-radius: 8px;
    background: #f8faff;
    color: #4f46e5;
    font-family: inherit;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.16s, border-color 0.16s, color 0.16s;
}

.mfb-mini:hover {
    background: #eef2ff;
    border-color: #a5b4fc;
    color: #4338ca;
}

.mfb-mini:active {
    background: #e0e7ff;
}

.mfb-mini .bi {
    font-size: 12px;
}

/* Penghitung panjang key. Muncul hanya saat sudah mendekati batas kolom -
   angka yang selalu terpampang cuma jadi latar yang tak pernah dibaca. */
.mfb-key-hitung {
    flex: 0 0 auto;
    font-size: 10.5px;
    font-weight: 700;
    color: #94a3b8;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.mfb-key-hitung.is-mentok {
    color: #b45309;
}

.mfb-optrow {
    display: flex;
    gap: 0.4rem;
    margin-bottom: 0.35rem;
}

.mfb-optrow button {
    border: 0;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    padding: 0 0.4rem;
}

.mfb-optrow button:hover {
    color: #ef4444;
}

/* Baris dua kolom — pasangan Nilai Minimum/Maksimum */
.mfb-inspector__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}
.mfb-opsional { font-weight: 500; font-size: 10.5px; color: #94a3b8; }
</style>

<!-- Gaya untuk opsi dropdown el-select, yang di-teleport ke body —
     gaya scoped di atas tidak menjangkaunya. -->
<style>
.fr__opsi {
    float: left;
}

.fr__opsi-ket {
    float: right;
    margin-left: 1.2rem;
    color: #94a3b8;
    font-size: 11.5px;
}
</style>
