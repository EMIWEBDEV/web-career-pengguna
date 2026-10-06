<!-- WEB CAREER — Admin: Pengumuman (create modal, dummy) -->
<template>
    <Head title="Pengumuman" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Pengumuman</h1>
                <p>Kelola pengumuman hasil seleksi — publikasi langsung atau terjadwal (embargo).</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openAdd"><i class="bi bi-plus-lg"></i> Buat Pengumuman</button>
            </div>
        </div>

        <!-- Publikasi hasil ke kandidat (real-time, sessionStorage) -->
        <div class="wca-card" style="margin-bottom:1.25rem">
            <div class="wca-card__head">
                <h3><i class="bi bi-broadcast"></i> Publikasi Hasil ke Kandidat</h3>
                <span class="wca-badge wca-b--indigo">{{ selected.length }} dipilih</span>
            </div>
            <div class="wca-card__body">
                <div v-if="!candidates.length" class="wca-empty"><i class="bi bi-inbox"></i><h4>Belum ada kandidat</h4><p style="margin:.3rem 0 0">Kandidat muncul setelah melamar (tersimpan di sesi peramban ini).</p></div>
                <div v-else class="wca-grid wca-grid--2">
                    <div>
                        <div class="wca-ckbar">
                            <el-checkbox :model-value="allSel" :indeterminate="someSel" @change="toggleAll">Pilih semua ({{ candidates.length }})</el-checkbox>
                            <el-select v-model="jenisF" size="small" style="width:130px"><el-option label="Semua" value="ALL" /><el-option label="Rekrutmen" value="REKRUTMEN" /><el-option label="MT" value="MT" /></el-select>
                        </div>
                        <div class="wca-cklist">
                            <label v-for="c in filteredCandidates" :key="c.lowonganId" class="wca-ckrow" :class="{ on: selected.includes(c.lowonganId) }">
                                <el-checkbox :model-value="selected.includes(c.lowonganId)" @change="toggle(c.lowonganId)" />
                                <span class="wca-avatar wca-avatar--sm">{{ initials(c.nama) }}</span>
                                <div class="wca-ckrow__main"><strong>{{ c.nama }}</strong><small>{{ c.posisi }} · <span class="wca-ckrow__tahap">{{ c.tahap }}</span></small></div>
                                <span class="wca-badge" :class="resBadge(c.result)">{{ resText(c.result) }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="wca-form">
                        <div><label class="wca-field-lbl">Judul Pengumuman <span style="color:#ef4444">*</span></label><el-input v-model="pf.judul" placeholder="Mis. Hasil Seleksi Administrasi EDP 2026" /></div>
                        <div>
                            <label class="wca-field-lbl">Terapkan Hasil</label>
                            <el-select v-model="pf.apply" style="width:100%">
                                <el-option label="Umumkan LULUS (loloskan)" value="LULUS" />
                                <el-option label="Umumkan TIDAK LOLOS" value="GAGAL" />
                                <el-option label="Hanya kirim info (status tetap)" value="INFO" />
                            </el-select>
                        </div>
                        <div><label class="wca-field-lbl">Tanggal Publish</label><el-date-picker v-model="pf.tanggal" type="date" value-format="YYYY-MM-DD" placeholder="Pilih tanggal" style="width:100%" :disabled-date="hariLampau" /></div>
                        <div><label class="wca-field-lbl">Isi Pengumuman</label><el-input v-model="pf.isi" type="textarea" :rows="4" placeholder="Tulis isi pengumuman untuk kandidat…" /></div>
                        <div class="wca-hint"><i class="bi bi-info-circle"></i> Pengumuman langsung tampil di dashboard kandidat terpilih beserta status kelulusannya.</div>
                        <button class="wca-btn wca-btn--primary" :disabled="!selected.length || !pf.judul.trim()" :onClick="!selected.length || !pf.judul.trim() ? null : publishToCandidates"><i class="bi bi-send"></i> Publikasikan ke {{ selected.length }} Kandidat</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="wca-card">
            <div class="wca-card__head"><h3><i class="bi bi-clock-history"></i> Riwayat Pengumuman</h3></div>
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr><th>Judul</th><th>Kegiatan</th><th>Mode</th><th>Penerima</th><th>Status</th><th>Publish</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in list" :key="p.id">
                                <td><strong>{{ p.judul }}</strong></td>
                                <td>{{ p.kegiatan }}</td>
                                <td><span class="wca-badge" :class="p.mode === 'EMBARGO' ? 'wca-b--amber' : 'wca-b--sky'"><i class="bi" :class="p.mode === 'EMBARGO' ? 'bi-clock-history' : 'bi-lightning'"></i> {{ p.mode }}</span></td>
                                <td>{{ p.penerima }}</td>
                                <td><span class="wca-badge" :class="statusBadge(p.status)">{{ p.status }}</span></td>
                                <td>{{ p.publish }}</td>
                                <td>
                                    <div style="display:flex;gap:.35rem;justify-content:flex-end">
                                        <button v-if="p.status !== 'TERBIT'" class="wca-iconbtn" title="Terbitkan" @click="publish(p)"><i class="bi bi-send"></i></button>
                                        <button class="wca-iconbtn" title="Edit" @click="openEdit(p)"><i class="bi bi-pencil"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AdminModal
            :show="show"
            :title="editing ? 'Ubah Pengumuman' : 'Buat Pengumuman'"
            subtitle="Publikasi hasil seleksi — langsung atau terjadwal (embargo)"
            icon="bi-megaphone"
            :save-label="editing ? 'Perbarui' : 'Simpan Data'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-card-heading"></i> Informasi Pengumuman</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Judul <span style="color:#ef4444">*</span></label>
                        <el-input v-model="form.judul" placeholder="Mis. Hasil Tahap Wawancara" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Kegiatan</label>
                        <el-select v-model="form.kegiatan" placeholder="Pilih kegiatan">
                            <el-option v-for="k in kegiatanOptions" :key="k" :label="k" :value="k" />
                        </el-select>
                    </div>
                </div>
            </div>
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-send"></i> Publikasi</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Mode Publikasi</label>
                            <el-select v-model="form.mode" placeholder="Pilih mode">
                                <el-option label="Langsung" value="IMMEDIATE" />
                                <el-option label="Terjadwal (Embargo)" value="EMBARGO" />
                            </el-select>
                        </div>
                        <div>
                            <label class="wca-field-lbl">Tanggal Publish</label>
                            <el-date-picker v-model="form.publish" type="date" value-format="YYYY-MM-DD" placeholder="Pilih tanggal" :disabled-date="hariLampau" />
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Isi Pengumuman</label>
                        <el-input v-model="form.isi" type="textarea" :rows="4" placeholder="Tulis isi pengumuman…" />
                    </div>
                </div>
            </div>
        </AdminModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import AdminModal from '@career/AdminModal.vue';
import { initials, statusBadge } from '@utils/career/admin';
import { announceResult, getApps } from '@utils/career/session';

/** Hari yang sudah lewat tidak bisa dipilih sebagai tanggal publish (masukan user 2 Okt 2026). */
function hariLampau(d) {
    const k = new Date();

    return d < new Date(k.getFullYear(), k.getMonth(), k.getDate());
}

const props = defineProps({
    pengumuman: { type: Array, default: () => [] },
    kegiatanOptions: { type: Array, default: () => [] },
});

const list = reactive(props.pengumuman.map((p) => ({ ...p })));

/* ── Publikasi hasil ke kandidat (sessionStorage, real-time) ── */
const candidates = ref([]);
const selected = ref([]);
const jenisF = ref('ALL');
const pf = reactive({ judul: '', apply: 'LULUS', tanggal: '', isi: '' });

function loadCandidates() {
    candidates.value = getApps().filter((a) => a.status === 'FINAL').map((a) => ({
        lowonganId: a.lowonganId,
        nama: a.form?.nama || a.form?.namaPre || 'Kandidat',
        posisi: a.posisi,
        jenis: a.jenis,
        tahap: (a.pipeline || [])[a.stageIdx]?.label || '—',
        result: a.result || 'BERJALAN',
    }));
    // buang seleksi yang sudah tak ada
    selected.value = selected.value.filter((id) => candidates.value.some((c) => c.lowonganId === id));
}
onMounted(() => {
    loadCandidates();
    window.addEventListener('focus', loadCandidates);
    document.addEventListener('visibilitychange', loadCandidates);
});
onBeforeUnmount(() => {
    window.removeEventListener('focus', loadCandidates);
    document.removeEventListener('visibilitychange', loadCandidates);
});

const filteredCandidates = computed(() => candidates.value.filter((c) => jenisF.value === 'ALL' || c.jenis === jenisF.value));
const allSel = computed(() => filteredCandidates.value.length > 0 && filteredCandidates.value.every((c) => selected.value.includes(c.lowonganId)));
const someSel = computed(() => filteredCandidates.value.some((c) => selected.value.includes(c.lowonganId)) && !allSel.value);
function toggle(id) { selected.value = selected.value.includes(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id]; }
function toggleAll() {
    if (allSel.value) selected.value = selected.value.filter((id) => !filteredCandidates.value.some((c) => c.lowonganId === id));
    else filteredCandidates.value.forEach((c) => { if (!selected.value.includes(c.lowonganId)) selected.value.push(c.lowonganId); });
}
function resBadge(r) { return { LULUS: 'wca-b--green', GAGAL: 'wca-b--red' }[r] || 'wca-b--indigo'; }
function resText(r) { return { LULUS: 'Lulus', GAGAL: 'Tidak Lolos' }[r] || 'Berjalan'; }

function publishToCandidates() {
    if (!selected.value.length || !pf.judul.trim()) return;
    selected.value.forEach((id) => announceResult(id, { judul: pf.judul, isi: pf.isi, tanggal: pf.tanggal || null, apply: pf.apply, mode: 'IMMEDIATE' }));
    list.unshift({ id: 'ANN-' + String(list.length + 1).padStart(2, '0'), judul: pf.judul, kegiatan: `${selected.value.length} kandidat`, mode: 'IMMEDIATE', penerima: selected.value.length, status: 'TERBIT', publish: pf.tanggal || 'Sekarang' });
    notice(`Pengumuman dipublikasikan ke ${selected.value.length} kandidat.`);
    selected.value = [];
    pf.judul = ''; pf.isi = '';
    loadCandidates();
}
const kegiatanOptions = props.kegiatanOptions.length
    ? props.kegiatanOptions
    : ['Rekrutmen Reguler Q3 2026', 'EDP 2026 — Batch 5'];

const show = ref(false);
const editing = ref(null);
const form = reactive({ judul: '', kegiatan: kegiatanOptions[0], mode: 'IMMEDIATE', publish: '', isi: '' });

function openAdd() {
    editing.value = null;
    Object.assign(form, { judul: '', kegiatan: kegiatanOptions[0], mode: 'IMMEDIATE', publish: '', isi: '' });
    show.value = true;
}
function openEdit(p) {
    editing.value = p;
    Object.assign(form, { judul: p.judul, kegiatan: p.kegiatan, mode: p.mode, publish: p.publish, isi: p.isi || '' });
    show.value = true;
}
function save() {
    if (!form.judul.trim()) return notice('Judul wajib diisi.');
    const status = form.mode === 'EMBARGO' ? 'TERJADWAL' : 'TERBIT';
    if (editing.value) {
        Object.assign(editing.value, { ...form, status });
        notice('Pengumuman diperbarui (demo dummy).');
    } else {
        list.unshift({ id: 'ANN-' + String(list.length + 1).padStart(2, '0'), penerima: 0, status, ...form });
        notice('Pengumuman dibuat (demo dummy).');
    }
    show.value = false;
}
function publish(p) {
    p.status = 'TERBIT';
    notice('Pengumuman diterbitkan (demo dummy).');
}

const toast = ref('');
let t = null;
function notice(m) {
    toast.value = m;
    if (t) clearTimeout(t);
    t = setTimeout(() => (toast.value = ''), 3000);
}
</script>
