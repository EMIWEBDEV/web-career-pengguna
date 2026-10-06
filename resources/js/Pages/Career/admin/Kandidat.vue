<!-- WEB CAREER — Admin: Kandidat (offcanvas detail, dummy) -->
<template>
    <Head title="Kandidat" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Basis Kandidat</h1>
                <p>Profil kandidat lintas lamaran (satu akun, banyak lamaran).</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--ghost" @click="notice('Ekspor kandidat (demo dummy).')"><i class="bi bi-download"></i> Ekspor</button>
            </div>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2"><i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari nama / email…" /></div>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr><th>Kandidat</th><th>Kota</th><th>Lamaran</th><th>Status</th><th>Terdaftar</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in filtered" :key="c.id" style="cursor:pointer" @click="open(c)">
                                <td>
                                    <div class="wca-table__name">
                                        <span class="wca-avatar wca-avatar--sm">{{ initials(c.nama) }}</span>
                                        <div><strong>{{ c.nama }}</strong><small>{{ c.email }} · {{ c.phone }}</small></div>
                                    </div>
                                </td>
                                <td><i class="bi bi-geo-alt" style="color:var(--indigo)"></i> {{ c.kota }}</td>
                                <td><span class="wca-badge wca-b--slate">{{ c.lamaran }} lamaran</span></td>
                                <td><span class="wca-badge" :class="statusBadge(c.status)">{{ c.status }}</span></td>
                                <td>{{ c.daftar }}</td>
                                <td><button class="wca-iconbtn" @click.stop="open(c)"><i class="bi bi-eye"></i></button></td>
                            </tr>
                            <tr v-if="!filtered.length"><td colspan="6"><div class="wca-empty"><i class="bi bi-person-x"></i><h4>Tidak ada kandidat cocok</h4></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Offcanvas detail (teleport ke body → overlay penuh di atas navbar) -->
        <teleport to="body">
        <transition name="wca-toast">
            <div v-if="selected" class="wca-drawer-mask wca" @click.self="selected = null">
                <aside class="wca-drawer">
                    <div class="wca-drawer__head">
                        <span class="wca-avatar">{{ initials(selected.nama) }}</span>
                        <div>
                            <h3>{{ selected.nama }}</h3>
                            <p>{{ selected.email }} · {{ selected.phone }}</p>
                        </div>
                        <button class="wca-drawer__close" @click="selected = null"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="wca-drawer__body">
                        <div class="wca-dsec">
                            <h4>Informasi</h4>
                            <div class="wca-dinfo">
                                <div><small>Kota</small><b>{{ selected.kota }}</b></div>
                                <div><small>Status</small><b>{{ selected.status }}</b></div>
                                <div><small>Total Lamaran</small><b>{{ selected.lamaran }}</b></div>
                                <div><small>Terdaftar</small><b>{{ selected.daftar }}</b></div>
                            </div>
                        </div>
                        <div class="wca-dsec">
                            <h4>Riwayat Lamaran</h4>
                            <div class="wca-list" style="border:1px solid var(--line);border-radius:.7rem">
                                <div class="wca-listrow">
                                    <span class="wca-avatar wca-avatar--sm" style="background:#eef2ff;color:#4338ca"><i class="bi bi-file-earmark-text"></i></span>
                                    <div class="wca-listrow__main"><strong>Sales Executive (Pet Retail)</strong><small>Tahap: Tes Online · Rekrutmen Q3</small></div>
                                    <span class="wca-badge wca-b--indigo">Berjalan</span>
                                </div>
                                <div v-if="selected.lamaran > 1" class="wca-listrow">
                                    <span class="wca-avatar wca-avatar--sm" style="background:#eef2ff;color:#4338ca"><i class="bi bi-file-earmark-text"></i></span>
                                    <div class="wca-listrow__main"><strong>EVO Development Program</strong><small>Tahap: Wawancara · MT Batch 5</small></div>
                                    <span class="wca-badge wca-b--indigo">Berjalan</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wca-drawer__foot">
                        <button class="wca-btn wca-btn--ghost" style="flex:1" @click="notice('Kirim email (demo dummy).')"><i class="bi bi-envelope"></i> Email</button>
                        <button class="wca-btn wca-btn--primary" style="flex:1" @click="notice('Lihat profil lengkap (demo dummy).')"><i class="bi bi-person-vcard"></i> Profil</button>
                    </div>
                </aside>
            </div>
        </transition>
        </teleport>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { initials, statusBadge } from '@utils/career/admin';

const props = defineProps({ kandidat: { type: Array, default: () => [] } });
const q = ref('');
const selected = ref(null);
const filtered = computed(() => {
    const s = q.value.trim().toLowerCase();
    return s ? props.kandidat.filter((c) => (c.nama + ' ' + c.email).toLowerCase().includes(s)) : props.kandidat;
});
function open(c) {
    selected.value = c;
}

const toast = ref('');
let t = null;
function notice(m) {
    toast.value = m;
    if (t) clearTimeout(t);
    t = setTimeout(() => (toast.value = ''), 3000);
}
</script>
