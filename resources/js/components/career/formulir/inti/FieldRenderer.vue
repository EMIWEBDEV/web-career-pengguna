<!-- WEB CAREER — Render SATU field sesuai tipe di skema. Dipakai semua template. -->
<template>
    <div
        class="fr"
        :class="{
            'fr--full': lebarPenuh,
            'fr--foto': field.tipe === 'foto',
            'fr--consent': field.tipe === 'consent',
            'fr--consent-aktif': field.tipe === 'consent' && !!nilai,
        }"
        :style="gayaLebar"
    >
        <!-- Consent: label pendek saja di sini -- teks pernyataan lengkapnya
             jadi label CHECKBOX itu sendiri di bawah (satu sumber teks, bukan
             diulang dua kali dengan kata-kata berbeda). -->
        <label v-if="field.tipe === 'consent'" class="fr__lbl fr__lbl--consent">
            <i class="bi bi-shield-check fr__consent-ico"></i> Pernyataan Persetujuan
            <span v-if="wajibSekarang" class="fr__wajib">*</span>
        </label>
        <label v-else class="fr__lbl">
            {{ field.label }}
            <span v-if="wajibSekarang && !prefillTerkunci" class="fr__wajib">*</span>
            <span v-if="prefillTerkunci" class="fr__auto"><i class="bi bi-magic"></i> otomatis</span>
            <span v-else-if="field.tipe === 'prefill'" class="fr__ubah"><i class="bi bi-pencil-fill"></i> dapat diubah</span>
        </label>

        <!-- Terisi otomatis dari profil kandidat, TERKUNCI. -->
        <el-input v-if="prefillTerkunci" :model-value="String(nilai ?? '')" disabled placeholder="—" />

        <!-- Prefill yang DIBUKA: disunting di tempatnya, bukan diketik ulang di
             kolom bebas. `tipe_buka` menentukan perlakuannya (teks/email/telepon). -->
        <TeleponNegara
            v-else-if="field.tipe === 'prefill' && field.tipe_buka === 'phone'"
            :model-value="String(nilai ?? '')"
            :disabled="disabled"
            :placeholder="field.ph || '81234567890'"
            @update:model-value="ubah"
        />

        <el-input
            v-else-if="field.tipe === 'prefill'"
            :model-value="nilai"
            :type="field.tipe_buka === 'email' ? 'email' : 'text'"
            :placeholder="field.ph || 'Tulis data yang benar'"
            @update:model-value="ubah"
        />

        <el-input
            v-else-if="field.tipe === 'text'"
            :model-value="nilai"
            :disabled="disabled"
            :placeholder="field.ph"
            :maxlength="field.maks_panjang || undefined"
            :inputmode="field.hanya_angka ? 'numeric' : undefined"
            @update:model-value="ubah"
        />

        <!-- Telepon SEMUA NEGARA — komponen yang sama dengan formulir apply,
             supaya kandidat tidak menemui dua gaya isian nomor yang berbeda. -->
        <TeleponNegara
            v-else-if="field.tipe === 'phone'"
            :model-value="String(nilai ?? '')"
            :disabled="disabled"
            :placeholder="field.ph || '81234567890'"
            @update:model-value="ubah"
        />

        <el-input
            v-else-if="field.tipe === 'textarea'"
            :model-value="nilai"
            type="textarea"
            :rows="3"
            :disabled="disabled"
            :placeholder="field.ph"
            @update:model-value="ubah"
        />

        <el-input-number
            v-else-if="field.tipe === 'number'"
            :model-value="nilai"
            :min="field.min ?? undefined"
            :max="field.maks ?? undefined"
            :precision="field.desimal || 0"
            :step="field.desimal ? 0.05 : 1"
            :disabled="disabled"
            :placeholder="field.ph"
            controls-position="right"
            style="width: 100%"
            @update:model-value="ubah"
        />

        <!-- ══ TANGGAL — DIKETIK, BUKAN DIKLIK ════════════════════════════════
             Kalender bagus untuk tanggal yang DICARI ("Senin depan tanggal
             berapa?"). Tanggal lahir tidak dicari — ia sudah diingat, dan
             memilihnya lewat kalender menuntut orang menggulir puluhan tahun ke
             belakang demi angka yang sejak awal ada di kepalanya.

             Yang tersimpan TETAP 'YYYY-MM-DD', sama persis dengan keluaran
             el-date-picker. Yang berubah cuma cara memasukkannya.

             Kalendernya masih bisa diminta per field lewat `kalender: true` —
             untuk tanggal yang memang lebih enak dipilih (jadwal, tenggat). -->
        <div v-else-if="field.tipe === 'date' && !field.kalender" class="fr__tgl">
            <el-input
                :model-value="tglTeks"
                :disabled="disabled"
                :placeholder="field.ph || 'hh/bb/tttt'"
                inputmode="numeric"
                maxlength="10"
                @update:model-value="ketikTanggal"
                @blur="rapikanTanggal"
            >
                <template #prefix><i class="bi bi-calendar-event"></i></template>
            </el-input>
            <small v-if="tglGalat" class="fr__tglgalat">{{ tglGalat }}</small>
            <!-- Bacaan panjangnya ditampilkan balik sebagai penegasan: '02/05/1998'
                 dan '05/02/1998' sama-sama sah, dan satu-satunya cara orang
                 menyadari ia tertukar adalah membacanya dalam bentuk yang tak
                 mungkin ambigu. -->
            <small v-else-if="tglPanjang" class="fr__tglbaca">
                <i class="bi bi-check-circle-fill"></i> {{ tglPanjang }}
            </small>
        </div>

        <el-date-picker
            v-else-if="field.tipe === 'date'"
            :model-value="nilai"
            type="date"
            value-format="YYYY-MM-DD"
            format="DD MMM YYYY"
            :disabled="disabled"
            :placeholder="field.ph || 'Pilih tanggal'"
            style="width: 100%"
            @update:model-value="ubah"
        />

        <!-- select: opsi statis dari skema, ATAU dinamis dari konteks (sumber_opsi,
             mis. kampus dari Master Kampus). Field ber-sumber_opsi DIKUNCI ke daftar
             resmi: bisa dicari (filterable) tapi kandidat TIDAK boleh mengetik bebas
             (tanpa allow-create). Kalau daftar belum ada -> input terkunci, bukan bebas.
             `bebas_ketik` — opsi statis dipakai sebagai SARAN saja, kandidat tetap
             boleh mengetik jawabannya sendiri (mis. pekerjaan/pendidikan orang tua). -->
        <template v-else-if="field.tipe === 'select'">
            <el-select
                v-if="opsiEfektif.length"
                :model-value="nilai"
                filterable
                :allow-create="!!field.bebas_ketik"
                :default-first-option="!!field.bebas_ketik"
                :disabled="disabled"
                :placeholder="field.ph || 'Cari lalu pilih'"
                style="width: 100%"
                @update:model-value="ubah"
            >
                <el-option v-for="o in opsiEfektif" :key="o" :value="o" :label="o" />
            </el-select>
            <el-input
                v-else-if="field.sumber_opsi"
                model-value=""
                disabled
                placeholder="Daftar pilihan belum tersedia — hubungi admin"
            />
            <el-input
                v-else
                :model-value="nilai"
                :disabled="disabled"
                :placeholder="field.ph || 'Ketik jawaban'"
                @update:model-value="ubah"
            />
        </template>

        <!-- bulan/tahun: dipakai memisah field "Bulan/Tahun" jadi dua kolom
             sendiri-sendiri (mis. mulai/selesai bekerja) tanpa mengetik bebas. -->
        <el-date-picker
            v-else-if="field.tipe === 'bulan'"
            :model-value="nilai"
            type="month"
            value-format="YYYY-MM"
            format="MMM YYYY"
            :disabled="disabled"
            :placeholder="field.ph || 'Pilih bulan'"
            style="width: 100%"
            @update:model-value="(v) => ubah(v ?? '')"
        />
        <el-date-picker
            v-else-if="field.tipe === 'tahun'"
            :model-value="nilai"
            type="year"
            value-format="YYYY"
            format="YYYY"
            :disabled="disabled"
            :placeholder="field.ph || 'Pilih tahun'"
            style="width: 100%"
            @update:model-value="(v) => ubah(v ?? '')"
        />

        <!-- referensi: opsi DICARI ke server sambil mengetik, bukan dikirim di
             muka. Master Kampus berisi ratusan ribu baris — mustahil dimuat
             seluruhnya. Field terkunci ke daftar resmi, KECUALI ditandai
             `bebas_ketik` — mis. kampus/prodi yang belum masuk daftar resmi
             tetap boleh diketik manual, daftar tetap dipakai sebagai saran. -->
        <el-select
            v-else-if="field.tipe === 'referensi'"
            :model-value="nilai || undefined"
            filterable
            remote
            clearable
            :allow-create="!!field.bebas_ketik"
            :remote-method="cariReferensi"
            :loading="memuat"
            default-first-option
            :disabled="disabled || indukBelumDiisi"
            :placeholder="placeholderReferensi"
            reserve-keyword
            style="width: 100%"
            @visible-change="(buka) => buka && cariReferensi('')"
            @update:model-value="(v) => ubah(v ?? '')"
        >
            <!-- Bendera nilai terpilih: el-select menampilkan `label` sebagai teks
                 polos, jadi bendera di dalam opsi tidak ikut terbawa ke kotaknya. -->
            <template v-if="benderaTerpilih" #prefix>
                <img class="fr__bendera" :src="benderaUrl(benderaTerpilih)" alt="" width="20" height="15" />
            </template>
            <el-option v-for="o in opsiReferensi" :key="o.nilai" :value="o.nilai" :label="o.label">
                <img
                    v-if="o.bendera"
                    class="fr__bendera fr__bendera--opsi"
                    :src="benderaUrl(o.bendera)"
                    alt=""
                    width="20"
                    height="15"
                    loading="lazy"
                />
                <!-- `bendera: null` = baris kampus yang negaranya tidak tercatat di
                     data impor. Diberi ikon netral supaya nama kampus tetap sejajar
                     dengan baris yang berbendera; kunci `bendera` tidak ada sama
                     sekali pada sumber non-kampus (prodi, jenjang), jadi di sana
                     tidak muncul ikon apa pun. -->
                <i
                    v-else-if="o.bendera === null"
                    class="bi bi-globe2 fr__bendera--opsi fr__bendera-kosong"
                    title="Negara tidak tercatat di data institusi"
                ></i>
                <span class="fr__opsi">{{ o.label }}</span>
                <span v-if="o.ket" class="fr__opsi-ket">{{ o.ket }}</span>
            </el-option>
        </el-select>

        <!-- currency: dipakai untuk nominal Rupiah (mis. ekspektasi gaji).
             el-input BIASA (bukan el-input-number) supaya format "Rp 5.000.000"
             tampil LANGSUNG sambil mengetik -- formatter el-input-number di Element
             Plus baru menata ulang tampilan saat blur, bukan tiap ketukan. -->
        <el-input
            v-else-if="field.tipe === 'currency'"
            :model-value="formatRupiah(nilai)"
            :disabled="disabled"
            :placeholder="field.ph || 'Rp 0'"
            inputmode="numeric"
            @update:model-value="ubahRupiah"
        />

        <el-radio-group
            v-else-if="field.tipe === 'radio'"
            :model-value="nilai"
            :disabled="disabled"
            @update:model-value="ubah"
        >
            <el-radio v-for="o in opsiEfektif" :key="o" :value="o">{{ o }}</el-radio>
        </el-radio-group>

        <el-checkbox-group
            v-else-if="field.tipe === 'checkbox'"
            :model-value="nilai || []"
            :disabled="disabled"
            @update:model-value="ubah"
        >
            <el-checkbox v-for="o in opsiEfektif" :key="o" :value="o">{{ o }}</el-checkbox>
        </el-checkbox-group>

        <!-- Berkas: file-nya sendiri disimpan di N_WEB_CAREERS_Formulir_Berkas,
             yang tersimpan di jawaban hanya nama berkasnya sebagai penanda. -->
        <template v-else-if="field.tipe === 'file'">
            <!-- Belum ada berkas -> dropzone ringkas. -->
            <el-upload
                v-if="!nilai"
                class="fr__drop"
                :class="{ 'is-mati': disabled }"
                drag
                :accept="field.accept || '.pdf'"
                :auto-upload="false"
                :show-file-list="false"
                :disabled="disabled"
                :on-change="pilihBerkas"
            >
                <span class="fr__drop-ico"><i class="bi bi-cloud-arrow-up-fill"></i></span>
                <span class="fr__drop-txt">
                    <strong>Klik atau seret berkas ke sini</strong>
                    <small>{{ field.accept || '.pdf' }} · maks {{ field.maks_mb || 2 }} MB</small>
                </span>
            </el-upload>

            <!-- Sudah ada berkas -> satu baris ringkas: pratinjau + nama + ganti + hapus,
                 bukan lagi dropzone besar DITAMBAH blok pratinjau terpisah di bawahnya. -->
            <div v-else class="fr__chip">
                <button
                    type="button"
                    class="fr__chip-pv"
                    :class="{ 'is-kosong': !urlPratinjau }"
                    :disabled="!urlPratinjau"
                    :title="urlPratinjau ? 'Lihat berkas' : 'Berkas belum tersimpan di server'"
                    :onClick="!urlPratinjau ? null : lihatBerkas"
                >
                    <img v-if="urlPratinjau && gambarPratinjau" :src="urlPratinjau" :alt="String(nilai)" />
                    <i v-else class="bi" :class="urlPratinjau ? 'bi-file-earmark-pdf-fill' : 'bi-file-earmark-fill'"></i>
                </button>
                <button
                    type="button"
                    class="fr__chip-nama"
                    :disabled="!urlPratinjau"
                    :title="urlPratinjau ? 'Lihat berkas' : 'Berkas belum tersimpan di server'"
                    :onClick="!urlPratinjau ? null : lihatBerkas"
                >{{ nilai }}</button>
                <el-upload
                    class="fr__chip-ganti"
                    :accept="field.accept || '.pdf'"
                    :auto-upload="false"
                    :show-file-list="false"
                    :disabled="disabled"
                    :on-change="pilihBerkas"
                >
                    <button type="button" class="fr__chip-btn" :disabled="disabled" title="Ganti berkas">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </el-upload>
                <button
                    type="button"
                    class="fr__chip-btn fr__chip-btn--danger"
                    :disabled="disabled"
                    title="Hapus berkas"
                    :onClick="disabled ? null : hapusBerkas"
                >
                    <i class="bi bi-trash3-fill"></i>
                </button>
            </div>

            <small v-if="galatUnggah" class="fr__unggah-galat" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i> {{ galatUnggah }}
            </small>
        </template>


        <!-- ══ DAFTAR BUTIR ═══════════════════════════════════════════════════
             Tiap gagasan punya kotaknya sendiri. Kandidat melihat berapa yang
             masih kurang sebelum menulis, bukan sesudah ditolak. -->
        <div v-else-if="field.tipe === 'daftar'" class="fr__daftar">
            <div v-for="(butir, i) in daftarButir" :key="i" class="fr__daftar-baris">
                <span class="fr__daftar-no">{{ i + 1 }}</span>
                <el-input
                    :model-value="butir"
                    :disabled="disabled"
                    :placeholder="field.ph || `Hal ke-${i + 1}`"
                    @update:model-value="(v) => ubahButir(i, v)"
                />
                <button
                    v-if="!disabled && daftarButir.length > minButir"
                    type="button"
                    class="fr__daftar-buang"
                    title="Hapus baris ini"
                    @click="buangButir(i)"
                ><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="fr__daftar-kaki">
                <button
                    v-if="!disabled && (!maksButir || daftarButir.length < maksButir)"
                    type="button"
                    class="fr__daftar-tambah"
                    @click="tambahButir"
                ><i class="bi bi-plus-lg"></i> Tambah</button>

                <!-- Hitungannya ditulis apa adanya. "Minimal 5" di label saja
                     memaksa orang menghitung sendiri kotak yang sudah terisi. -->
                <small class="fr__daftar-hitung" :class="{ 'is-kurang': butirTerisi < minButir }">
                    {{ butirTerisi }} dari {{ minButir }} terisi
                    <template v-if="maksButir"> &middot; maks {{ maksButir }}</template>
                </small>
            </div>
        </div>

        <!-- Foto verifikasi dari kamera. Yang tersimpan di jawaban hanya nama
             berkasnya (seperti tipe `file`); gambarnya sendiri dikirim ke induk
             lewat event `berkas` supaya ikut jalur unggah yang sama. -->
        <template v-else-if="field.tipe === 'foto'">
            <AmbilFoto
                :model-value="fotoTampil"
                :nama-tersimpan="String(nilai ?? '')"
                :disabled="disabled"
                @update:model-value="(v) => (fotoDataUrl = v)"
                @foto="terimaFoto"
            />
            <small v-if="galatUnggah" class="fr__unggah-galat" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i> {{ galatUnggah }}
            </small>
        </template>

        <el-checkbox
            v-else-if="field.tipe === 'consent'"
            :model-value="!!nilai"
            :disabled="disabled"
            class="fr__consent"
            @update:model-value="ubah"
        >
            {{ field.label }}
        </el-checkbox>

        <el-input v-else :model-value="nilai" :disabled="disabled" @update:model-value="ubah" />

        <div v-if="field.bantuan" class="fr__bantuan">{{ field.bantuan }}</div>
        <div v-if="galat" class="fr__galat"><i class="bi bi-exclamation-circle"></i> {{ galat }}</div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { ambilOpsi, benderaDiingat, tunda } from '@utils/formulir/referensi';
import { syaratTerpenuhi, wajibKini } from '@utils/formulir/aturan';
import { kunciBerkas } from '@utils/formulir/berkasBaris';
import { masker as maskerTgl, keTampilan as tglKeTampilan, keIso as tglKeIso, galat as tglGalatPesan, batasHariIni as tglBatasHariIni } from '@utils/formulir/tanggalKetik';
import TeleponNegara from '@career/TeleponNegara.vue';
import AmbilFoto from './AmbilFoto.vue';

const props = defineProps({
    field: { type: Object, required: true },
    // Posisi field ini bila ia berada di dalam bagian BERULANG. Keduanya null
    // untuk bagian biasa. Tanpa ini sebuah field tidak tahu ia baris ke berapa,
    // dan tiga baris akan mencari berkas draf dengan kunci yang sama persis.
    bagian: { type: String, default: null },
    baris: { type: Number, default: null },
    modelValue: { type: [String, Number, Boolean, Array, Object, null], default: null },
    disabled: { type: Boolean, default: false },
    galat: { type: String, default: '' },
    // Konteks opsi dinamis (mis. { kampus: ['ITB', ...] }) untuk field
    // ber-sumber_opsi. Daftar panjang sekarang memakai tipe `referensi`.
    konteks: { type: Object, default: () => ({}) },
    // Jawaban tetangga — acuan field bertipe `referensi` untuk merantai
    // penyaringnya (jenjang -> jenis institusi -> kampus). Di bagian berulang
    // isinya jawaban BARIS itu, bukan seluruh formulir.
    jawabanKonteks: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'berkas']);

const nilai = computed(() => props.modelValue);

// ── DAFTAR BUTIR ────────────────────────────────────────────────────────────
const minButir = computed(() => Math.max(1, Number(props.field?.min_butir) || 1));
const maksButir = computed(() => Number(props.field?.maks_butir) || 0);

/**
 * Baris yang DIGAMBAR — selalu setidaknya sebanyak minimalnya.
 *
 * Kotak kosongnya sengaja sudah berdiri sejak awal: lima kotak menunggu
 * memberi tahu berapa yang diminta tanpa satu kalimat pun, sementara satu
 * kotak dengan tombol "tambah" menyembunyikan tuntutannya sampai orang
 * menekan Lanjut dan ditolak.
 */
const daftarButir = computed(() => {
    const isi = Array.isArray(nilai.value) ? nilai.value.map((x) => String(x ?? '')) : [];
    while (isi.length < minButir.value) isi.push('');

    return isi;
});

const butirTerisi = computed(() => daftarButir.value.filter((x) => String(x).trim() !== '').length);

function simpanButir(arr) {
    // Butir kosong di EKOR dibuang sebelum disimpan; yang di tengah dibiarkan
    // supaya nomor urut yang sedang dilihat kandidat tidak melompat saat ia
    // mengosongkan satu baris untuk menulis ulang.
    const bersih = [...arr];
    while (bersih.length && String(bersih[bersih.length - 1]).trim() === '') bersih.pop();
    ubah(bersih);
}

function ubahButir(i, v) {
    const arr = [...daftarButir.value];
    arr[i] = v;
    simpanButir(arr);
}

function tambahButir() {
    simpanButir([...daftarButir.value, ' ']);
}

function buangButir(i) {
    const arr = [...daftarButir.value];
    arr.splice(i, 1);
    simpanButir(arr);
}

// ── TANGGAL YANG DIKETIK ────────────────────────────────────────────────────
//
// Teks yang TAMPAK disimpan terpisah dari nilai yang TERSIMPAN. Keduanya tidak
// bisa satu: selama '02/05/19' belum lengkap, tidak ada tanggal sah yang bisa
// ditulis ke jawaban — tapi apa yang sudah diketik harus tetap terlihat.
const tglTeks = ref(tglKeTampilan(props.modelValue));
const tglGalat = ref('');

// Nilai bisa berubah dari luar (draf dipulihkan, prefill datang belakangan).
// Tanpa pengamat ini kolomnya tetap kosong walau jawabannya sudah ada.
watch(() => props.modelValue, (baru) => {
    const tampak = tglKeTampilan(baru);
    if (tampak && tampak !== tglTeks.value) {
        tglTeks.value = tampak;
        tglGalat.value = '';
    }
    if (!baru && !tglGalat.value) tglTeks.value = tglTeks.value || '';
});

/** '2 Mei 1998' — penegasan yang tak mungkin terbaca terbalik. */
const tglPanjang = computed(() => {
    const iso = tglKeIso(tglTeks.value);
    if (!iso) return '';
    const d = new Date(`${iso}T00:00:00`);

    return isNaN(d) ? '' : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
});

/**
 * Bintang merah mengikuti keadaan jawaban SAAT INI, bukan hanya flag `wajib`.
 *
 * Sebuah field bisa wajib hanya pada kondisi tertentu (`wajib_jika`) - mis.
 * "Nama Pasangan" yang baru mengikat setelah status pernikahan dijawab
 * "Menikah". Kalau bintangnya tetap statis, label dan pemeriksa berbicara dua
 * hal berbeda: kandidat melihat kolom bertanda wajib yang ternyata boleh
 * kosong, atau - yang jauh lebih buruk - kolom tanpa bintang yang menahannya
 * saat menekan Lanjut tanpa pernah menjelaskan kenapa.
 *
 * Sumber aturannya SATU dengan validator (wajibKini di aturan.js), jadi
 * keduanya mustahil berselisih.
 */
const wajibSekarang = computed(() => wajibKini(props.field, props.jawabanKonteks));

function ketikTanggal(v) {
    tglTeks.value = maskerTgl(v);
    tglGalat.value = '';

    // Jawaban ditulis HANYA saat tanggalnya utuh dan nyata. Menulis nilai
    // separuh jadi akan tersimpan sebagai jawaban yang tak bisa dibaca siapa
    // pun — dan tersimpan diam-diam, sebab draf menyimpan apa adanya.
    const iso = tglKeIso(tglTeks.value);
    ubah(iso ?? null);
}

/**
 * Galat baru diperiksa saat kolomnya DITINGGALKAN.
 *
 * Memeriksanya tiap ketukan berarti '0' langsung dinyatakan salah — dan pesan
 * merah yang muncul sebelum orang selesai mengetik melatihnya mengabaikan
 * pesan merah.
 */
function rapikanTanggal() {
    // Arah waktunya ditentukan per PERTANYAAN, bukan seragam sekali untuk
    // semua. Dulu barisnya berbunyi `props.field?.maks_hari_ini !== false`:
    // benar untuk tanggal lahir, tapi karena tidak ada satu pun tempat yang
    // pernah MENULIS `maks_hari_ini`, hasilnya selalu true - dan setiap kolom
    // tanggal ikut memakai aturan tanggal lahir. Lihat batasHariIni().
    tglGalat.value = tglGalatPesan(tglTeks.value, {
        maksHariIni: tglBatasHariIni(props.field),
    }) || '';
}

// URL objek berkas yang baru dipilih (belum terkirim) + apakah ia gambar.
const pratinjau = ref('');
const pratinjauGambar = ref(false);

// Berkas draf yang sudah tersimpan di server, dititipkan lewat `konteks`.
// Dipakai setelah halaman dimuat ulang: URL objek lokal ikut hilang bersama
// komponennya, jadi tanpa ini berkas yang sudah ada tampak tak bisa dibuka.
const drafBerkas = computed(
    () => props.konteks?.berkasDraf?.[kunciBerkas(props.bagian, props.baris, props.field.key)] || null,
);
const urlPratinjau = computed(() => pratinjau.value || drafBerkas.value?.url || '');
const gambarPratinjau = computed(
    () => pratinjauGambar.value || String(drafBerkas.value?.mime || '').startsWith('image/'),
);

// Unggahan yang GAGAL untuk isian ini, dititipkan induk lewat `konteks`.
// Pesannya menetap di bawah kotak unggah: notifikasi saja hilang dalam empat
// detik, dan dulu nama berkas yang gagal tetap tampil seolah beres.
const galatUnggah = computed(
    () => props.konteks?.berkasGagal?.[kunciBerkas(props.bagian, props.baris, props.field.key)] || '',
);

// Prefill TANPA `buka_jika` selalu terkunci — perilaku lama tetap utuh untuk
// formulir lain yang memakainya sebagai tampilan baca-saja.
const prefillTerkunci = computed(
    () => props.field.tipe === 'prefill'
        && !(props.field.buka_jika && syaratTerpenuhi(props.field.buka_jika, props.jawabanKonteks)),
);

/**
 * Opsi yang benar-benar dipakai: dari konteks bila field menandai `sumber_opsi`
 * (mis. kampus dari whitelist pembukaan), selain itu dari opsi statis skema.
 */
const opsiEfektif = computed(() => {
    if (props.field.sumber_opsi) {
        return props.konteks?.[props.field.sumber_opsi] || [];
    }
    return props.field.opsi || [];
});

/**
 * Lebar EFEKTIF field dalam persen.
 *
 * `lebar_jika` (diatur admin di Master Formulir) menang atas lebar tetap selama
 * syaratnya terpenuhi — mis. "Status Kemahasiswaan" jadi setengah baris hanya
 * ketika dijawab "Mahasiswa", karena saat itu "Semester" muncul di sebelahnya.
 * Selama syarat belum terpenuhi, lebar tetap yang dipakai.
 */
const lebarPersenEfektif = computed(() => {
    const alt = props.field.lebar_jika;
    if (alt?.field && syaratTerpenuhi(alt, props.jawabanKonteks)) {
        return Number(alt.lebar_persen) || 100;
    }
    if (props.field.penuh) return 100;
    return Number(props.field.lebar_persen || 33);
});
const lebarGrid = computed(() => {
    const persen = Math.min(100, Math.max(33, lebarPersenEfektif.value));
    return Math.min(12, Math.max(4, Math.round((persen / 100) * 12)));
});
// Kelas penuh mengikuti lebar EFEKTIF, bukan `field.penuh` mentah — kalau tidak,
// field ber-`penuh` yang sedang menyusut lewat `lebar_jika` tetap dipaksa
// satu baris penuh oleh `grid-column: 1 / -1` dan aturannya tak terlihat.
const lebarPenuh = computed(() => lebarGrid.value >= 12);
const gayaLebar = computed(() => ({ '--fr-span': String(lebarGrid.value) }));

/* ── Field bertipe `referensi` ──────────────────────────────────────────
   Dua macam penyaring, dan bedanya penting:
     bergantung — induk yang WAJIB terisi lebih dulu. Selama kosong, field
                  ini terkunci; menawarkan 328 ribu kampus tanpa jenjang
                  hanya membuat kandidat tersesat.
     saring     — penyempit opsional. Kalau terisi dipakai, kalau belum
                  daftar tetap bisa dibuka (cuma lebih lebar).
*/
const opsiReferensi = ref([]);
const memuat = ref(false);

function nilaiInduk(peta) {
    const out = {};
    for (const [param, key] of Object.entries(peta || {})) {
        const v = props.jawabanKonteks?.[key];
        out[param] = v === null || v === undefined ? '' : String(v);
    }
    return out;
}

const indukWajib = computed(() => nilaiInduk(props.field.bergantung));
const indukSaring = computed(() => nilaiInduk(props.field.saring));
const indukBelumDiisi = computed(() => Object.values(indukWajib.value).some((v) => v === ''));

const placeholderReferensi = computed(() => {
    if (!indukBelumDiisi.value) return props.field.ph || 'Ketik untuk mencari';
    return props.field.ph_terkunci || 'Lengkapi pertanyaan sebelumnya dulu';
});

async function muat(cari) {
    if (indukBelumDiisi.value) {
        opsiReferensi.value = [];
        return;
    }
    memuat.value = true;
    const hasil = await ambilOpsi(
        props.field.sumber,
        // 50 bawaan terasa terlalu sedikit saat digulir, apalagi untuk kampus
        // yang daftarnya ratusan ribu. 100 adalah batas atas yang diizinkan
        // server; permintaan lebih dari itu tetap dipangkas di sana.
        { cari, limit: 100, ...indukWajib.value, ...indukSaring.value },
        props.field.key,
    );
    // null = permintaan dibatalkan karena ada ketikan lebih baru; jangan
    // menimpa daftar yang sedang tampil dengan hasil usang.
    if (hasil !== null) opsiReferensi.value = sertakanNilaiTerpilih(hasil);
    memuat.value = false;
}

/**
 * Jawaban yang sudah tersimpan harus tetap terbaca walau tidak ikut terbawa
 * hasil pencarian terakhir — kalau tidak, membuka kembali formulir yang sudah
 * diisi memperlihatkan kolom kosong seolah jawabannya hilang.
 */
function sertakanNilaiTerpilih(daftar) {
    const v = props.modelValue;
    if (!v || daftar.some((o) => o.nilai === v)) return daftar;

    const baris = { nilai: v, label: String(v), ket: null };
    // Kunci `bendera` HANYA ditempelkan bila benderanya memang diketahui.
    // Menaruh null di sini akan membuat sumber non-kampus (prodi, jenjang)
    // ikut memunculkan ikon globe "negara tidak tercatat".
    const bendera = benderaDiingat(v);
    if (bendera) baris.bendera = bendera;

    return [baris, ...daftar];
}

const cariReferensi = tunda((cari) => muat(String(cari || '')));

/**
 * Ikon bendera negara kampus — gambar, bukan emoji.
 *
 * Emoji bendera tidak punya glif di Windows/Chrome dan hanya tampil sebagai dua
 * huruf kode negara. Sumbernya disamakan dengan pemilih kode telepon supaya
 * benderanya konsisten di seluruh formulir.
 */
function benderaUrl(kode) {
    return `https://flagcdn.com/20x15/${String(kode).toLowerCase()}.png`;
}

/**
 * Bendera milik nilai yang SEDANG terpilih. Bernilai null sampai daftar opsinya
 * termuat — jawaban tersimpan hanya menyimpan nama kampus, bukan negaranya.
 */
const benderaTerpilih = computed(
    () => opsiReferensi.value.find((o) => o.nilai === props.modelValue)?.bendera
        || benderaDiingat(props.modelValue)
        || null,
);

// Induk berubah -> pilihan anak hampir pasti tidak berlaku lagi (prodi S1
// tidak masuk akal setelah jenjang diganti SMK). Dikosongkan supaya tidak ada
// kombinasi mustahil yang lolos ke database.
watch(
    () => JSON.stringify([indukWajib.value, indukSaring.value]),
    () => {
        opsiReferensi.value = [];
        if (props.disabled) return;
        if (props.modelValue) emit('update:modelValue', '');
    },
);

// Nilai tersimpan perlu dimunculkan sebagai opsi sejak awal, tanpa menunggu
// kandidat membuka dropdown-nya.
watch(
    () => props.modelValue,
    (v) => {
        if (v && !opsiReferensi.value.some((o) => o.nilai === v)) {
            opsiReferensi.value = sertakanNilaiTerpilih(opsiReferensi.value);
        }
    },
    { immediate: true },
);

/** Format angka polos -> "Rp 5.000.000" untuk tampilan field `currency`. */
function formatRupiah(v) {
    const angka = String(v ?? '').replace(/\D/g, '');
    if (!angka) return '';
    return 'Rp ' + angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/** Ketikan "Rp 5.000.000" -> disaring jadi angka murni sebelum disimpan. */
function ubahRupiah(v) {
    const angka = String(v ?? '').replace(/\D/g, '');
    ubah(angka ? Number(angka) : null);
}

function ubah(v) {
    // `hanya_angka` — mis. NIK: apa pun yang diketik disaring jadi digit saja,
    // supaya tidak ada validasi tambahan yang bisa dilewati lewat tempel teks.
    if (props.field.hanya_angka && typeof v === 'string') {
        v = v.replace(/\D/g, '');
        if (props.field.maks_panjang) v = v.slice(0, props.field.maks_panjang);
    }
    emit('update:modelValue', v);
}

/**
 * Normalkan nomor telepon Indonesia agar SELALU berawalan 62 (tanpa +).
 *   08123..  -> 628123..   (0 diganti 62)
 *   8123..   -> 628123..   (langsung ditambah 62)
 *   +62 / 62 -> tetap 62..
 *   620..    -> 62..        (buang 0 setelah 62, mis. hasil ketik 62 lalu 08)
 * Disimpan sebagai '628xxxxxxxxx'; tampilan diberi awalan '+' oleh prepend.
 */

/**
 * Terima berkas dari tombol MAUPUN dari seret-lepas.
 *
 * Atribut `accept` hanya menyaring dialog pilih berkas — seret-lepas melewatinya
 * begitu saja. Tanpa pemeriksaan di sini, berkas .exe yang diseret tetap
 * terpasang dan baru ditolak server setelah kandidat menekan Kirim.
 */
/**
 * Batalkan berkas yang sudah dipilih.
 *
 * URL objeknya dilepas supaya salinan berkas tidak menggantung di memori
 * peramban, dan induknya diberi tahu lewat `hapus: true` agar berkas yang
 * telanjur dikumpulkan untuk dikirim ikut dibuang — kalau tidak, nama berkas
 * hilang dari layar tapi isinya tetap terkirim.
 */
/**
 * Minta induk membuka berkas di MODAL halaman, bukan tab baru.
 *
 * Tab baru melempar kandidat keluar dari formulir yang sedang diisi; modal
 * membiarkannya memeriksa berkas lalu langsung melanjutkan. Modalnya milik
 * halaman, jadi tampilannya sama dengan pratinjau berkas lain di Web Careers.
 */
function lihatBerkas() {
    if (!urlPratinjau.value) return;
    emit('berkas', {
        field: props.field,
        lihat: { url: urlPratinjau.value, nama: String(nilai.value || 'Berkas'), gambar: gambarPratinjau.value },
    });
}

/* ── Foto verifikasi (tipe `foto`) ───────────────────────────────────────
   Gambar hasil jepretan hidup di komponen ini saja; yang mengalir ke jawaban
   hanya NAMA berkasnya, persis seperti tipe `file`. dataURL sebuah foto bisa
   ratusan kilobyte, dan jawaban formulir ikut tersimpan sebagai draf berkali-
   kali — menaruhnya di sana akan menggelembungkan Jawaban_Json tanpa guna. */
const fotoDataUrl = ref('');

// Pratinjau lokal milik berkas yang GAGAL naik dibuang. Tanpa ini kartunya
// tetap bisa dibuka dari salinan di tab — tampak tersimpan padahal server tidak
// pernah menerimanya — atau malah menampilkan isi berkas gagal di bawah nama
// berkas lama yang dipulihkan.
watch(galatUnggah, (g) => {
    if (!g) return;
    if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
    pratinjau.value = '';
    pratinjauGambar.value = false;
    fotoDataUrl.value = '';
});

/**
 * Yang ditampilkan: jepretan baru bila ada, kalau tidak foto draf yang sudah
 * tersimpan di server. Tanpa jalur kedua, kandidat yang melanjutkan pengisian
 * esok hari melihat panggung kamera kosong dan mengira fotonya hilang, lalu
 * mengambil ulang tanpa perlu.
 */
const fotoTampil = computed(() => fotoDataUrl.value || drafBerkas.value?.url || '');

/** dataURL hasil kamera -> File JPG, supaya jalur unggahnya sama dengan berkas biasa. */
function fotoKeFile(dataUrl, nama) {
    const [kepala, b64] = String(dataUrl).split(',');
    const mime = (kepala.match(/:(.*?);/) || [])[1] || 'image/jpeg';
    const biner = atob(b64);
    const buf = new Uint8Array(biner.length);
    for (let i = 0; i < biner.length; i++) buf[i] = biner.charCodeAt(i);

    return new File([buf], nama, { type: mime });
}

function terimaFoto(dataUrl) {
    if (! dataUrl) {
        emit('update:modelValue', '');
        emit('berkas', { field: props.field, hapus: true });

        return;
    }

    const nama = `${props.field.key || 'foto'}-verifikasi.jpg`;
    emit('update:modelValue', nama);
    emit('berkas', { field: props.field, file: fotoKeFile(dataUrl, nama) });
}

function hapusBerkas() {
    if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
    pratinjau.value = '';
    pratinjauGambar.value = false;
    emit('update:modelValue', '');
    emit('berkas', { field: props.field, hapus: true });
}

function pilihBerkas(uf) {
    const file = uf.raw || uf;
    const izin = (props.field.accept || '.pdf')
        .split(',')
        .map((x) => x.trim().toLowerCase())
        .filter(Boolean);

    const ext = '.' + (file.name.split('.').pop() || '').toLowerCase();
    const cocok = izin.some((a) => (a.startsWith('.') ? a === ext : file.type === a));

    if (!cocok) {
        emit('berkas', {
            field: props.field,
            galat: `"${file.name}" ditolak — hanya menerima ${izin.join(', ')}.`,
        });
        return;
    }

    const maks = (props.field.maks_mb || 2) * 1024 * 1024;
    if (file.size > maks) {
        emit('berkas', {
            field: props.field,
            galat: `"${file.name}" melebihi ${props.field.maks_mb || 2} MB.`,
        });
        return;
    }

    // Pratinjau lokal: URL sementara dari berkas yang baru dipilih, supaya
    // kandidat bisa memastikan yang terunggah memang benar SEBELUM mengirim.
    if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
    pratinjau.value = URL.createObjectURL(file);
    pratinjauGambar.value = file.type.startsWith('image/');

    emit('update:modelValue', file.name);
    emit('berkas', { field: props.field, file });
}
</script>

<style scoped>
.fr { display: flex; flex-direction: column; gap: .35rem; min-width: 0; grid-column: span var(--fr-span, 4); }
.fr--full { grid-column: 1 / -1; }

.fr__lbl { font-size: 12px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: .3rem; }
.fr__wajib { color: #dc2626; }
.fr__ubah { display: inline-flex; align-items: center; gap: 4px; margin-left: 6px; padding: 1px 7px; border-radius: 999px; font-size: 10.5px; font-weight: 700; color: #4f46e5; background: rgba(99, 102, 241, .12); }
.fr__auto { font-size: 10.5px; font-weight: 600; color: #4338ca; background: rgba(79, 70, 229, .1); border-radius: 999px; padding: .05rem .4rem; }

.fr__bantuan { font-size: 11px; color: #94a3b8; line-height: 1.5; }
.fr__galat { font-size: 11.5px; color: #dc2626; display: flex; align-items: center; gap: .25rem; }

/* -- Ukuran seragam untuk SEMUA kontrol (input, select, number, date, upload
   trigger) — sebelumnya tiap komponen Element Plus punya tinggi bawaan
   sendiri-sendiri (32px vs 40px vs custom), jadi baris terlihat tidak rata. */
.fr :deep(.el-input__wrapper),
.fr :deep(.el-textarea__inner),
.fr :deep(.el-select__wrapper) {
    min-height: 40px;
    border-radius: 10px;
    box-shadow: 0 0 0 1px rgba(11, 16, 51, .12) inset;
}
.fr :deep(.el-input__wrapper.is-focus),
.fr :deep(.el-select__wrapper.is-focused) {
    box-shadow: 0 0 0 1px #6366f1 inset;
}
.fr :deep(.el-textarea__inner) { border-radius: 10px; padding-top: .55rem; }
.fr :deep(.el-input-number) { width: 100%; }
.fr :deep(.el-input-number .el-input__wrapper) { padding-left: .9rem; }
.fr :deep(.el-input-number.is-without-controls .el-input__inner) { text-align: left; }


/* -- Pilihan Ya/Tidak: satu baris rata -------------------------------------
   Element Plus memberi el-radio margin kanan 30px bawaan dan tinggi tetap 32px,
   sehingga opsi terpilih tampak bergeser dari yang tidak, dan antar pertanyaan
   tidak sejajar. Diseragamkan lewat flex + gap. */
.fr :deep(.el-radio-group),
.fr :deep(.el-checkbox-group) { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .6rem; }
.fr :deep(.el-radio),
.fr :deep(.el-checkbox) {
    margin: 0 !important; height: auto; min-height: 40px; min-width: 4.5rem;
    padding: 0 .9rem; border: 1.5px solid rgba(11, 16, 51, .13); border-radius: 10px;
    transition: border-color .15s ease, background .15s ease;
}
.fr :deep(.el-radio:hover),
.fr :deep(.el-checkbox:hover) { border-color: rgba(99, 102, 241, .45); }
.fr :deep(.el-radio.is-checked),
.fr :deep(.el-checkbox.is-checked) { border-color: #6366f1; background: rgba(99, 102, 241, .07); }
.fr :deep(.el-radio__label),
.fr :deep(.el-checkbox__label) { padding-left: .5rem; font-size: 13px; font-weight: 600; }

/* Aksen kotak centang & radio disamakan dengan aksen renderer ini.
   Tema menyetel --el-color-primary ke #4f46e5, sedangkan cincin fokus, ikon,
   dan bingkai chip saat terpilih semuanya memakai --primary (#6366f1). Dua
   indigo yang beda tipis lalu berdiri bersebelahan DI DALAM satu chip —
   bingkainya satu warna, kotak centang di dalamnya warna lain — dan yang
   terbaca bukan "dua nuansa", melainkan salah satu warnanya salah. */
.fr :deep(.el-checkbox__input.is-checked .el-checkbox__inner),
.fr :deep(.el-radio__input.is-checked .el-radio__inner) { background-color: var(--primary) !important; border-color: var(--primary) !important; }
.fr :deep(.el-checkbox__input.is-focus .el-checkbox__inner),
.fr :deep(.el-radio__input.is-focus .el-radio__inner) { border-color: var(--primary) !important; }

/* -- Unggah berkas: dropzone ringkas saat KOSONG --------------------------- */
.fr__drop { display: block; width: 100%; }
.fr__drop :deep(.el-upload) { display: block; width: 100%; }
.fr__drop :deep(.el-upload-dragger) {
    display: flex; align-items: center; gap: .6rem;
    width: 100%; min-height: 40px; padding: .4rem .7rem;
    border: 1.5px dashed rgba(99, 102, 241, .32); border-radius: 10px;
    background: linear-gradient(135deg, rgba(99, 102, 241, .05), rgba(139, 92, 246, .03));
    transition: border-color .16s ease, background .16s ease;
}
.fr__drop :deep(.el-upload-dragger:hover),
.fr__drop :deep(.el-upload-dragger.is-dragover) { border-color: #6366f1; background: rgba(99, 102, 241, .09); }
.fr__drop-ico { flex: 0 0 auto; width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; background: linear-gradient(140deg, #8b5cf6, #6366f1); color: #fff; font-size: .85rem; }
.fr__drop-txt { flex: 1; min-width: 0; text-align: left; line-height: 1.3; }
.fr__drop-txt strong { display: block; font-size: 12.5px; font-weight: 700; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fr__drop-txt small { font-size: 10.5px; color: #8b93a7; }
/* Unggahan gagal — merah, menetap sampai berkas dipilih ulang. */
.fr__unggah-galat { display: flex; align-items: flex-start; gap: .35rem; font-size: 11.5px; font-weight: 600; line-height: 1.45; color: #b91c1c; }
.fr__unggah-galat .bi { flex: 0 0 auto; margin-top: 1px; }
.fr__drop.is-mati { opacity: .55; pointer-events: none; }

/* -- Berkas TERPASANG: satu baris ringkas (pratinjau + nama + ganti + hapus),
   menggantikan dropzone besar + blok pratinjau terpisah yang tadinya tampil
   berbarengan dan memakan tempat, apalagi di dalam bagian berulang. -------- */
.fr__chip {
    display: flex; align-items: center; gap: .5rem; width: 100%; min-height: 40px;
    padding: .3rem .4rem; border: 1.5px solid rgba(16, 185, 129, .35); border-radius: 10px;
    background: rgba(16, 185, 129, .06);
}
.fr__chip-pv {
    flex: none; width: 30px; height: 30px; border-radius: 8px; overflow: hidden;
    border: 0; padding: 0; display: grid; place-items: center; cursor: zoom-in;
    background: #fff; color: #059669; font-size: .95rem;
}
.fr__chip-pv img { width: 100%; height: 100%; object-fit: cover; }
.fr__chip-pv.is-kosong { cursor: default; color: #94a3b8; }
.fr__chip-nama {
    flex: 1; min-width: 0; border: 0; background: none; padding: 0; text-align: left;
    font: inherit; font-size: 12.5px; font-weight: 700; color: #0f766e; cursor: pointer;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.fr__chip-nama:disabled { color: #475569; cursor: default; }
.fr__chip-btn {
    flex: none; width: 28px; height: 28px; border-radius: 8px; border: 0;
    display: grid; place-items: center; background: rgba(15, 23, 42, .06); color: #475569;
    cursor: pointer; transition: background .15s ease, color .15s ease;
}
.fr__chip-btn:hover:not(:disabled) { background: rgba(99, 102, 241, .14); color: #4f46e5; }
.fr__chip-btn:disabled { opacity: .5; cursor: not-allowed; }
.fr__chip-btn--danger:hover:not(:disabled) { background: rgba(220, 38, 38, .12); color: #dc2626; }

/* Field foto: label & teks bantuan ikut ke tengah mengikuti panggung kameranya.
   Kalau hanya panggungnya yang dipusatkan, labelnya menggantung sendirian di
   kiri dan blok itu justru terlihat lebih berantakan daripada sebelum dirapikan. */
.fr--foto .fr__lbl { justify-content: center; }
.fr--foto .fr__bantuan,
.fr--foto .fr__galat { text-align: center; justify-content: center; }

.fr__consent {
    --el-checkbox-checked-bg-color: var(--primary);
    --el-checkbox-checked-input-border-color: var(--primary);
    --el-checkbox-input-border-color-hover: var(--primary);
    --el-checkbox-checked-text-color: var(--dark);
    white-space: normal;
    height: auto;
}

/* -- Kartu persetujuan: teks pernyataan panjang lebih nyaman dibaca dalam
   kotak sendiri daripada tercampur sebagai "field" biasa, dan berubah warna
   begitu dicentang supaya jelas mana yang sudah disetujui. */
.fr--consent {
    grid-column: 1 / -1; padding: .9rem 1.05rem; border: 1.5px solid rgba(11, 16, 51, .12);
    border-radius: 14px; background: var(--evo-bg); transition: border-color .15s ease, background .15s ease;
}
.fr--consent-aktif { border-color: var(--primary); background: rgba(99, 102, 241, .06); }
.fr--consent .fr__lbl { font-size: 12.5px; font-weight: 700; color: var(--evo-slate); margin-bottom: .55rem; line-height: 1.55; }
.fr__consent-ico { color: var(--primary); font-size: 13px; }

/* Baris pernyataan. Aturan chip di atas dibuat untuk opsi Ya/Tidak yang pendek
   dan sebaris: tinggi tetap 40px, lebar minimum, dan padding HORIZONTAL saja.
   Dipakai pada kalimat yang membungkus, akibatnya terlihat jelas — teksnya
   menempel ke tepi atas-bawah, dan kotak centangnya menggantung di atas baris
   pertama. Bingkainya sendiri juga mubazir: kartu di luar sudah berbingkai dan
   sudah berganti warna saat dicentang, jadi yang terlihat kandidat cuma kotak
   di dalam kotak. Di sini bingkainya dilepas dan kotak centang duduk seimbang
   di tengah kalimat, sehingga kartunya jadi satu blok utuh. */
.fr--consent :deep(.el-checkbox) {
    display: flex; align-items: center; width: 100%;
    min-height: 0; min-width: 0; padding: 0;
    border: 0; background: none; white-space: normal;
}
/* Keadaan tercentang diwakili KARTUNYA (fr--consent-aktif). Tanpa baris ini
   aturan `.el-checkbox.is-checked` di atas menang spesifisitas dan mengecat
   ulang latar barisnya, jadi kotak-dalam-kotak yang baru dilepas muncul lagi
   — justru pada saat kandidat mencentangnya. */
.fr--consent :deep(.el-checkbox.is-checked),
.fr--consent :deep(.el-checkbox:hover) { border-color: transparent; background: none; }
.fr--consent :deep(.el-checkbox__input) { flex: none; }
.fr--consent :deep(.el-checkbox__label),
/* Element Plus mewarnai label yang tercentang dengan warna primernya, dan
   aturannya menang spesifisitas atas baris di atas. Untuk opsi pendek itu
   wajar, tapi di sini yang ikut berubah warna adalah SATU PARAGRAF pernyataan
   — teks yang justru paling perlu tetap terbaca tenang setelah disetujui. */
.fr--consent :deep(.el-checkbox__input.is-checked + .el-checkbox__label) {
    white-space: normal; line-height: 1.6; font-size: 13px; font-weight: 600; color: var(--dark); padding-left: .65rem;
}
.fr--consent :deep(.el-checkbox__input.is-checked .el-checkbox__inner),
.fr__consent :deep(.el-checkbox__input.is-checked .el-checkbox__inner) {
    background-color: var(--primary) !important;
    border-color: var(--primary) !important;
}
.fr--consent :deep(.el-checkbox__input.is-focus .el-checkbox__inner),
.fr--consent :deep(.el-checkbox__input:hover .el-checkbox__inner),
.fr__consent :deep(.el-checkbox__input.is-focus .el-checkbox__inner),
.fr__consent :deep(.el-checkbox__input:hover .el-checkbox__inner) {
    border-color: var(--primary) !important;
}

/* Opsi referensi: nama di kiri, keterangan (kota / gelar) menepi ke kanan. */
.fr__opsi { float: left; }
.fr__opsi-ket { float: right; margin-left: 1.2rem; color: #94a3b8; font-size: 11.5px; }

/* Bendera negara kampus. Garis tipis di tepinya supaya bendera yang sisinya
   putih (mis. Jepang) tidak lenyap ke latar terang. */
.fr__bendera {
    flex: none;
    width: 20px;
    height: 15px;
    border-radius: 2px;
    object-fit: cover;
    box-shadow: 0 0 0 1px rgba(15, 23, 42, .12);
}
/* Baris opsi memakai float; 0.6rem mendudukkannya di tengah baris setinggi 34px. */
.fr__bendera--opsi { float: left; margin: .6rem .5rem 0 0; }
/* Ikon pengganti bendera: lebar dipatok 20px agar nama kampus tetap satu garis
   lurus dengan baris yang berbendera. */
/* `display` dipatok karena ada aturan global `.bi { display: inline-table }`
   yang kalau dibiarkan membuat lebar 20px tidak dihormati. */
.fr__bendera-kosong {
    display: block;
    width: 20px;
    text-align: center;
    color: #cbd5e1;
    font-size: 13px;
    line-height: 15px;
}

/* ── TANGGAL DIKETIK ── */
.fr__tgl { display: flex; flex-direction: column; gap: .25rem; }
.fr__tglgalat { font-size: 11.5px; line-height: 1.5; color: #b91c1c; }
/* Bacaan panjang sengaja tenang — ia penegasan, bukan peringatan. */
/* Centangnya ikon di markup, BUKAN `content` ber-escape CSS.
   Escape heksadesimal di CSS mudah rusak saat berkasnya disunting alat lain —
   dan yang muncul di layar bukan galat, melainkan sampah yang terbaca sebagai
   data ("¹3�a05 Mei 1999"). Ikon biasa tidak punya cara gagal seperti itu. */
.fr__tglbaca { display: inline-flex; align-items: center; gap: .25rem; font-size: 11.5px; color: #64748b; }
.fr__tglbaca .bi { color: #059669; font-size: 11px; }

/* ── DAFTAR BUTIR ── */
.fr__daftar { display: flex; flex-direction: column; gap: .4rem; }
.fr__daftar-baris { display: flex; align-items: center; gap: .5rem; }
.fr__daftar-no {
    flex: none; display: grid; place-items: center;
    width: 1.5rem; height: 1.5rem; border-radius: .45rem;
    background: #eef2ff; color: #4338ca; font-size: 11px; font-weight: 800;
}
.fr__daftar-buang { flex: none; border: none; background: none; color: #cbd5e1; font-size: 12px; cursor: pointer; padding: .25rem; }
.fr__daftar-buang:hover { color: #dc2626; }
.fr__daftar-kaki { display: flex; align-items: center; justify-content: space-between; gap: .6rem; flex-wrap: wrap; margin-top: .15rem; }
.fr__daftar-tambah {
    display: inline-flex; align-items: center; gap: .3rem;
    border: 1px dashed #c7d2fe; background: #fff; color: #4338ca;
    font: inherit; font-size: 11.5px; font-weight: 700;
    border-radius: .5rem; padding: .3rem .65rem; cursor: pointer;
}
.fr__daftar-tambah:hover { background: #eef2ff; }
.fr__daftar-hitung { margin-left: auto; font-size: 11.5px; font-weight: 700; color: #059669; }
/* Merah hanya saat KURANG — bukan sejak awal. Kolom yang merah sebelum
   disentuh melatih orang mengabaikan warna merah. */
.fr__daftar-hitung.is-kurang { color: #b45309; }
</style>
