<!-- WEB CAREER — Admin: Master Jenis Tes (CAT via HCLearn vs Manual). Durasi/skor diatur di HCLearn, bukan di sini. -->
<template>
    <Head title="Master Jenis Tes" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Jenis Tes</h1>
                <p>Katalog metode tes untuk alur seleksi. Tes <b>CAT</b> berjalan di platform (HCLearn) — durasi, skor, & kelulusan diatur di sana, hasil masuk otomatis.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Jenis Tes Baru</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-hdd-network"></i>
            <span><b>CAT (Ujian Online)</b> = dijadwalkan lewat HCLearn (token+OTP), skor & kelulusan otomatis dari platform → <b>keputusan sistem</b>. <b>Manual</b> = offline/berkas/wawancara → <b>keputusan admin</b>. Web Career tidak menyimpan durasi/passing score.</span>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2"><i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari nama / kategori tes…" /></div>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead><tr><th>Nama Tes</th><th>Kategori</th><th>Metode</th><th>Pelaksana / Platform</th><th>Keputusan</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="t in filtered" :key="t.id">
                                <td><strong>{{ t.nama }}</strong><br /><small style="color:var(--muted);font-weight:700">{{ t.id }}</small></td>
                                <td>{{ t.kategori }}</td>
                                <td>
                                    <span class="wca-badge" :class="t.cat ? 'wca-b--amber' : 'wca-b--slate'">
                                        <i class="bi" :class="t.cat ? 'bi-pc-display' : 'bi-clipboard-check'"></i> {{ t.cat ? 'CAT (Online)' : 'Manual' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="wca-badge" :class="t.cat ? 'wca-b--indigo' : 'wca-b--slate'">
                                        <i class="bi" :class="t.cat ? 'bi-hdd-network' : 'bi-person-workspace'"></i> {{ t.pelaksana }}
                                    </span>
                                </td>
                                <td>
                                    <span class="wca-flow__dec" :class="t.cat ? 'sys' : 'man'">
                                        <i class="bi" :class="t.cat ? 'bi-cpu' : 'bi-hand-index-thumb'"></i> {{ t.cat ? 'Sistem' : 'Admin' }}
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:.45rem">
                                        <el-switch :model-value="t.status === 'AKTIF'" @change="(v) => setStatus(t, v)" />
                                        <span style="font-size:.74rem;font-weight:800" :style="{ color: t.status === 'AKTIF' ? '#059669' : 'var(--muted)' }">{{ t.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.35rem;justify-content:flex-end">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(t)"><i class="bi bi-pencil"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filtered.length"><td colspan="7"><div class="wca-empty"><i class="bi bi-clipboard-x"></i><h4>Tidak ada tes cocok</h4></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Create/Edit -->
        <AdminModal :show="show" :title="editingId ? 'Ubah Jenis Tes' : 'Tambah Jenis Tes'" subtitle="Definisikan metode tes untuk dipakai di alur seleksi" icon="bi-ui-checks-grid" :save-label="editingId ? 'Perbarui' : 'Simpan Tes'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-ui-checks-grid"></i> Detail Tes</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Tes</label><el-input v-model="form.nama" placeholder="Tes Bahasa Inggris" /></div>
                        <div><label class="wca-field-lbl">Kategori</label><el-input v-model="form.kategori" placeholder="Bahasa" /></div>
                    </div>
                    <div><label class="wca-field-lbl">Metode Pelaksanaan</label>
                        <RefSelect type="metode" v-model="form.metode" placeholder="Pilih metode" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">{{ form.metode === 'CAT' ? 'Platform CAT' : 'Pelaksana / Penilai' }}</label>
                        <el-input v-model="form.pelaksana" :placeholder="form.metode === 'CAT' ? 'HCLearn' : 'Asesor Eksternal / Internal'" />
                    </div>
                    <div class="wca-flow__note" style="margin:0">
                        <i class="bi bi-info-circle"></i>
                        <span v-if="form.metode === 'CAT'">Dijadwalkan lewat <b>HCLearn</b> (token+OTP). Durasi, skor, & kelulusan diatur di platform. Hasil otomatis → <b>keputusan sistem</b>.</span>
                        <span v-else>Dinilai <b>manual</b> oleh pelaksana/penilai → <b>keputusan admin</b> di worklist. Tidak dijadwalkan lewat HCLearn.</span>
                    </div>
                </div>
            </div>
        </AdminModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import AdminModal from '@career/AdminModal.vue';
import RefSelect from '@career/RefSelect.vue';

const props = defineProps({
    tes: { type: Array, default: () => [] },
});

const list = reactive(props.tes.map((t) => ({ ...t })));
const q = ref('');
const filtered = computed(() => {
    const s = q.value.trim().toLowerCase();
    return s ? list.filter((t) => (t.nama + ' ' + t.kategori + ' ' + t.pelaksana).toLowerCase().includes(s)) : list;
});

const show = ref(false);
const editingId = ref(null);
const form = reactive({ nama: '', kategori: '', metode: 'CAT', pelaksana: 'HCLearn' });

function onMetode() {
    // saran default pelaksana saat ganti metode
    if (form.metode === 'CAT' && (!form.pelaksana || form.pelaksana === 'Internal (Tim Rekrutmen)')) form.pelaksana = 'HCLearn';
    if (form.metode === 'MANUAL' && form.pelaksana === 'HCLearn') form.pelaksana = 'Internal (Tim Rekrutmen)';
}
watch(() => form.metode, onMetode);
function reset() {
    Object.assign(form, { nama: '', kategori: '', metode: 'CAT', pelaksana: 'HCLearn' });
}
function openCreate() {
    editingId.value = null;
    reset();
    show.value = true;
}
function openEdit(t) {
    editingId.value = t.id;
    Object.assign(form, { nama: t.nama, kategori: t.kategori, metode: t.metode, pelaksana: t.pelaksana });
    show.value = true;
}
function save() {
    const cat = form.metode === 'CAT';
    if (editingId.value) {
        const row = list.find((x) => x.id === editingId.value);
        if (row) Object.assign(row, { ...form, cat }); // status tak diubah dari modal
        notice('Jenis tes diperbarui (demo dummy).');
    } else {
        list.unshift({ id: 'TES-' + (list.length + 1), ...form, cat, status: 'AKTIF' }); // default aktif
        notice('Jenis tes ditambahkan (demo dummy).');
    }
    show.value = false;
}
function setStatus(t, v) {
    t.status = v ? 'AKTIF' : 'NONAKTIF';
    notice(`Tes ${t.status === 'AKTIF' ? 'diaktifkan' : 'dinonaktifkan'} (demo dummy).`);
}

const toast = ref('');
let t = null;
function notice(m) {
    toast.value = m;
    if (t) clearTimeout(t);
    t = setTimeout(() => (toast.value = ''), 3000);
}
</script>
