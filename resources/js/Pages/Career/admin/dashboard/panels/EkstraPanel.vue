<!--
  ZONA E — EMPAT PANEL EKSTRA (Redesigned)
-->
<template>
    <div class="wcd-grid wcd-grid--2">
        <!-- ══════ TES ══════ -->
        <div class="wcd-card">
            <h3 class="wcd-card__hd">
                <i class="bi bi-pencil-square"></i>
                <span>Kehadiran &amp; Nilai Tes</span>
            </h3>

            <KeadaanPanel v-if="!ekstra.tes.ringkas" keadaan="kosong" rapat ikon="bi-clipboard-x"
                teks="Belum ada peserta tes" ket="Muncul setelah penjadwalan tes berjalan." />
            <template v-else>
                <div class="ex-mini-grid">
                    <div class="ex-stat-item" :style="{ '--tone': '#0284c7' }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.tes.ringkas.peserta) }}</div>
                        <div class="ex-stat-item__lbl">Peserta</div>
                    </div>
                    <div class="ex-stat-item" :style="{ '--tone': STATUS.good.warna }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.tes.ringkas.selesai) }}</div>
                        <div class="ex-stat-item__lbl">Selesai</div>
                    </div>
                    <div class="ex-stat-item" :style="{ '--tone': STATUS.critical.warna }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.tes.ringkas.belumAkses) }}</div>
                        <div class="ex-stat-item__lbl">Belum buka</div>
                    </div>
                    <div class="ex-stat-item" :style="{ '--tone': STATUS.warning.warna }">
                        <div class="ex-stat-item__num">{{ angka(ekstra.tes.ringkas.timeout) }}</div>
                        <div class="ex-stat-item__lbl">Timeout</div>
                    </div>
                </div>

                <p v-if="ekstra.tes.ringkas.rataNilai !== null" class="ex-nota">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    Rata-rata: <b>{{ desimal(ekstra.tes.ringkas.rataNilai, 2) }}</b>
                    (Min {{ desimal(ekstra.tes.ringkas.nilaiMin, 2) }},
                    Max {{ desimal(ekstra.tes.ringkas.nilaiMax, 2) }})
                </p>
                <p v-if="ekstra.tes.ringkas.gagalKirim" class="ex-nota is-buruk">
                    <i class="bi" :class="STATUS.critical.ikon"></i>
                    {{ angka(ekstra.tes.ringkas.gagalKirim) }} undangan tes gagal terkirim.
                </p>

                <div v-if="ekstra.tes.perSesi.length" class="wcd-tw ex-tw">
                    <table class="wcd-tbl">
                        <thead>
                            <tr><th>Sesi</th><th class="wcd-num">Peserta</th><th class="wcd-num">Selesai</th><th class="wcd-num">Rata nilai</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(s, i) in ekstra.tes.perSesi" :key="i">
                                <td class="wcd-tbl__utama">
                                    {{ s.label }}
                                    <span class="wcd-tbl__sub">{{ s.mulai ? tglJam(s.mulai) : 'Tanpa jadwal' }}</span>
                                </td>
                                <td class="wcd-num">{{ angka(s.peserta) }}</td>
                                <td class="wcd-num">{{ angka(s.selesai) }}</td>
                                <td class="wcd-num">
                                    <b>{{ s.rataNilai === null ? '—' : desimal(s.rataNilai, 2) }}</b>
                                    <small v-if="s.ambang !== null" :title="`Ambang lulus ${s.ambang}`">/ {{ desimal(s.ambang, 0) }}</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- ══════ OPERASIONAL ══════ -->
        <div class="wcd-card">
            <div class="ex-card-hd">
                <h3 class="wcd-card__hd">
                    <i class="bi bi-cpu-fill"></i>
                    <span>Operasional &amp; Aktivitas Sistem</span>
                </h3>
                <span class="ex-status-pill" :class="ekstra.operasional.applyGagal ? 'is-kritis' : 'is-normal'">
                    <i class="bi" :class="ekstra.operasional.applyGagal ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'"></i>
                    {{ ekstra.operasional.applyGagal ? 'Perlu Tindakan' : 'Sistem Normal' }}
                </span>
            </div>

            <div class="ex-mini-grid">
                <div class="ex-stat-item" :class="{ 'is-alert': ekstra.operasional.applyGagal }" :style="{ '--tone': ekstra.operasional.applyGagal ? STATUS.critical.warna : '#64748b' }">
                    <div class="ex-stat-item__top">
                        <div class="ex-stat-item__num">{{ angka(ekstra.operasional.applyGagal) }}</div>
                        <i class="bi bi-exclamation-octagon-fill ex-stat-ic"></i>
                    </div>
                    <div class="ex-stat-item__lbl">Lamaran Gagal</div>
                    <div class="ex-stat-item__ket">gagal terbentuk</div>
                </div>
                <div class="ex-stat-item" :style="{ '--tone': ekstra.operasional.applyMenunggu ? STATUS.warning.warna : '#64748b' }">
                    <div class="ex-stat-item__top">
                        <div class="ex-stat-item__num">{{ angka(ekstra.operasional.applyMenunggu) }}</div>
                        <i class="bi bi-hourglass-split ex-stat-ic"></i>
                    </div>
                    <div class="ex-stat-item__lbl">Antre Diproses</div>
                    <div class="ex-stat-item__ket">menunggu job</div>
                </div>
                <div class="ex-stat-item" :style="{ '--tone': ekstra.operasional.berkasMenunggu ? '#d97706' : '#64748b' }">
                    <div class="ex-stat-item__top">
                        <div class="ex-stat-item__num">{{ angka(ekstra.operasional.berkasMenunggu) }}</div>
                        <i class="bi bi-file-earmark-person-fill ex-stat-ic"></i>
                    </div>
                    <div class="ex-stat-item__lbl">Verifikasi Berkas</div>
                    <div class="ex-stat-item__ket">menunggu verifikasi</div>
                </div>
                <div class="ex-stat-item" :style="{ '--tone': '#10b981' }">
                    <div class="ex-stat-item__top">
                        <div class="ex-stat-item__num">{{ angka(ekstra.operasional.applySelesai) }}</div>
                        <i class="bi bi-check-circle-fill ex-stat-ic"></i>
                    </div>
                    <div class="ex-stat-item__lbl">Berhasil Dibentuk</div>
                    <div class="ex-stat-item__ket">lamaran aktif</div>
                </div>
            </div>

            <div v-if="ekstra.operasional.applyGagal" class="ex-alert-box is-kritis">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div>
                    <strong>{{ angka(ekstra.operasional.applyGagal) }} lamaran gagal terbentuk!</strong>
                    <p>Pelamar ini tidak ada di worklist mana pun. Segera periksa log sistem atau hubungi pelamar.</p>
                </div>
            </div>
            <div v-else class="ex-alert-box is-normal">
                <i class="bi bi-shield-check"></i>
                <span>Seluruh proses pembuatan lamaran otomatis sistem berjalan 100% lancar tanpa hambatan.</span>
            </div>
        </div>

        <!-- ══════ FEEDBACK ══════ -->
        <div class="wcd-card">
            <div class="ex-card-hd">
                <h3 class="wcd-card__hd">
                    <i class="bi bi-chat-heart-fill"></i>
                    <span>Pengalaman &amp; Feedback Kandidat</span>
                </h3>
                <a href="/karir/feedback-dashboard" class="ex-action-btn">
                    Dashboard <i class="bi bi-arrow-right-short"></i>
                </a>
            </div>

            <KeadaanPanel v-if="!ekstra.feedback.dibuat" keadaan="kosong" rapat ikon="bi-chat-square-dots"
                teks="Belum ada formulir feedback terkirim"
                ket="Feedback dikirim otomatis setelah keputusan akhir kandidat." />
            <template v-else>
                <div class="ex-csat-header">
                    <div class="ex-csat-score">
                        <span class="ex-csat-val">{{ ekstra.feedback.rataRating === null ? '—' : ekstra.feedback.rataRating }}</span>
                        <small class="ex-csat-max">/100</small>
                    </div>
                    <div class="ex-csat-info">
                        <span class="ex-csat-label" :style="{ color: nadaPuas }">
                            <i class="bi" :class="bintangPuas"></i> {{ labelPuas }}
                        </span>
                        <small class="ex-csat-sub">Dari {{ angka(ekstra.feedback.terisi) }} responden kandidat</small>
                    </div>
                </div>

                <div class="ex-gauge">
                    <div class="ex-gauge__i">
                        <div class="ex-gauge__hd">
                            <span>Response Rate</span>
                            <b>{{ ekstra.feedback.responseRate === null ? '—' : ekstra.feedback.responseRate + '%' }}</b>
                        </div>
                        <div class="wcd-meter">
                            <span :style="{ width: (ekstra.feedback.responseRate || 0) + '%', background: '#6366f1' }"></span>
                        </div>
                        <small>{{ angka(ekstra.feedback.terisi) }} terisi dari {{ angka(ekstra.feedback.dibuat) }} dikirim</small>
                    </div>

                    <div class="ex-gauge__i">
                        <div class="ex-gauge__hd">
                            <span>Indeks Kepuasan</span>
                            <b>{{ ekstra.feedback.rataRating === null ? '—' : ekstra.feedback.rataRating }} / 100</b>
                        </div>
                        <div class="wcd-meter">
                            <span :style="{ width: (ekstra.feedback.rataRating || 0) + '%', background: nadaPuas }"></span>
                        </div>
                        <small>Rata-rata kepuasan seluruh tahap seleksi</small>
                    </div>
                </div>
            </template>
        </div>

        <!-- ══════ TALENT POOL ══════ -->
        <div class="wcd-card">
            <div class="ex-card-hd">
                <h3 class="wcd-card__hd">
                    <i class="bi bi-bookmark-star-fill"></i>
                    <span>Talent Pool Kandidat</span>
                </h3>
                <a href="/karir/talent-pool" class="ex-action-btn">
                    Kelola Pool <i class="bi bi-arrow-right-short"></i>
                </a>
            </div>

            <KeadaanPanel v-if="!ekstra.talent.total" keadaan="kosong" rapat ikon="bi-bookmark-dash"
                teks="Talent pool masih kosong"
                ket="Kandidat masuk ke sini saat diputus TALENT_POOL di worklist." />
            <template v-else>
                <div class="ex-mini-grid">
                    <div class="ex-stat-item" :style="{ '--tone': '#8b5cf6' }">
                        <div class="ex-stat-item__top">
                            <div class="ex-stat-item__num">{{ angka(ekstra.talent.aktif) }}</div>
                            <i class="bi bi-bookmark-star-fill ex-stat-ic"></i>
                        </div>
                        <div class="ex-stat-item__lbl">Siap Ditarik</div>
                        <div class="ex-stat-item__ket">kandidat aktif</div>
                    </div>
                    <div class="ex-stat-item" :class="{ 'is-warn': ekstra.talent.segeraHabis }" :style="{ '--tone': STATUS.warning.warna }">
                        <div class="ex-stat-item__top">
                            <div class="ex-stat-item__num">{{ angka(ekstra.talent.segeraHabis) }}</div>
                            <i class="bi bi-clock-history ex-stat-ic"></i>
                        </div>
                        <div class="ex-stat-item__lbl">Kedaluwarsa ≤ 30h</div>
                        <div class="ex-stat-item__ket">perlu perhatian</div>
                    </div>
                    <div class="ex-stat-item" :style="{ '--tone': '#64748b' }">
                        <div class="ex-stat-item__top">
                            <div class="ex-stat-item__num">{{ angka(ekstra.talent.kedaluwarsa) }}</div>
                            <i class="bi bi-x-circle-fill ex-stat-ic"></i>
                        </div>
                        <div class="ex-stat-item__lbl">Kedaluwarsa</div>
                        <div class="ex-stat-item__ket">masa habis</div>
                    </div>
                    <div class="ex-stat-item" :style="{ '--tone': STATUS.good.warna }">
                        <div class="ex-stat-item__top">
                            <div class="ex-stat-item__num">{{ angka(ekstra.talent.ditarik) }}</div>
                            <i class="bi bi-person-check-fill ex-stat-ic"></i>
                        </div>
                        <div class="ex-stat-item__lbl">Pernah Ditarik</div>
                        <div class="ex-stat-item__ket">sukses direkrut</div>
                    </div>
                </div>

                <!-- Meter Aktif Talent -->
                <div class="ex-talent-health">
                    <div class="ex-talent-health__hd">
                        <span>Ketersediaan Talent Pool Aktif</span>
                        <b>{{ persenAktifTalent }}% Aktif</b>
                    </div>
                    <div class="wcd-meter">
                        <span :style="{ width: persenAktifTalent + '%', background: 'linear-gradient(90deg, #8b5cf6, #6366f1)' }"></span>
                    </div>
                </div>

                <div v-if="ekstra.talent.segeraHabis" class="ex-alert-box is-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        <strong>{{ angka(ekstra.talent.segeraHabis) }} kandidat akan kedaluwarsa dalam 30 hari!</strong>
                        <p>Segera periksa dan tarik kandidat potensial ini ke lowongan kerja baru sebelum masa aktifnya habis.</p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { angka, desimal, tglJam, STATUS } from '@utils/career/dashboard';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ ekstra: { type: Object, required: true } });

const nadaPuas = computed(() => {
    const v = props.ekstra.feedback.rataRating;
    if (v === null) return '#cbd5e1';
    if (v >= 75) return STATUS.good.warna;
    if (v >= 50) return STATUS.warning.warna;
    return STATUS.critical.warna;
});

const labelPuas = computed(() => {
    const v = props.ekstra.feedback.rataRating;
    if (v === null) return 'Belum Ada Data';
    if (v >= 85) return 'Sangat Puas ⭐';
    if (v >= 70) return 'Puas';
    if (v >= 50) return 'Cukup';
    return 'Perlu Evaluasi';
});

const bintangPuas = computed(() => {
    const v = props.ekstra.feedback.rataRating;
    if (v === null) return 'bi-chat-dots-fill';
    if (v >= 70) return 'bi-emoji-smile-fill';
    if (v >= 50) return 'bi-emoji-neutral-fill';
    return 'bi-emoji-frown-fill';
});

const persenAktifTalent = computed(() => {
    const total = props.ekstra.talent.total || 0;
    if (!total) return 0;
    return Math.round(((props.ekstra.talent.aktif || 0) / total) * 100);
});
</script>

<style scoped>
.wcd-grid { display: grid; gap: 16px; }
.wcd-grid--2 { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }

.wcd-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 18px;
    padding: 20px;
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.ex-card-hd {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}

.wcd-card__hd {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    font-size: 0.92rem;
    font-weight: 900;
    color: #0f172a;
}
.wcd-card__hd .bi { color: #6366f1; font-size: 1.15rem; }

.ex-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
}
.ex-status-pill.is-normal { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.ex-status-pill.is-kritis { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

.ex-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    border-radius: 10px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    color: #4338ca;
    font-size: 0.74rem;
    font-weight: 800;
    text-decoration: none;
    transition: all 0.15s ease;
}
.ex-action-btn:hover {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
}

.ex-mini-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
}

.ex-stat-item {
    padding: 12px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
    border-left: 3.5px solid var(--tone, #6366f1);
    transition: all 0.15s ease;
}
.ex-stat-item:hover {
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}
.ex-stat-item.is-alert { background: #fef2f2; border-color: #fecaca; }
.ex-stat-item.is-warn { background: #fff7ed; border-color: #ffedd5; }

.ex-stat-item__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.ex-stat-item__num { font-size: 1.35rem; font-weight: 900; color: #0f172a; line-height: 1; }
.ex-stat-ic { font-size: 1rem; color: var(--tone, #6366f1); opacity: 0.85; }

.ex-stat-item__lbl { margin-top: 6px; font-size: 0.74rem; font-weight: 800; color: #334155; }
.ex-stat-item__ket { font-size: 0.68rem; color: #94a3b8; margin-top: 1px; }

.ex-alert-box {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 14px;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 0.76rem;
    line-height: 1.5;
}
.ex-alert-box.is-kritis {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}
.ex-alert-box.is-kritis .bi { color: #dc2626; font-size: 1.1rem; flex: none; margin-top: 1px; }
.ex-alert-box.is-kritis strong { color: #7f1d1d; display: block; font-weight: 800; }
.ex-alert-box.is-kritis p { margin: 2px 0 0; color: #b91c1c; }

.ex-alert-box.is-warning {
    background: #fff7ed;
    border: 1px solid #ffedd5;
    color: #9a3412;
}
.ex-alert-box.is-warning .bi { color: #ea580c; font-size: 1.1rem; flex: none; margin-top: 1px; }
.ex-alert-box.is-warning strong { color: #7c2d12; display: block; font-weight: 800; }
.ex-alert-box.is-warning p { margin: 2px 0 0; color: #c2410c; }

.ex-alert-box.is-normal {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    font-weight: 600;
}
.ex-alert-box.is-normal .bi { color: #16a34a; font-size: 1.1rem; flex: none; }

/* CSAT SCORE DISPLAY */
.ex-csat-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #f8fafc, #eef2ff);
    border: 1px solid #c7d2fe;
    border-radius: 14px;
    margin-bottom: 14px;
}
.ex-csat-score {
    display: flex;
    align-items: baseline;
    gap: 2px;
}
.ex-csat-val {
    font-size: 1.8rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
}
.ex-csat-max {
    font-size: 0.8rem;
    font-weight: 800;
    color: #64748b;
}
.ex-csat-info {
    display: flex;
    flex-direction: column;
}
.ex-csat-label {
    font-size: 0.88rem;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 5px;
}
.ex-csat-sub {
    font-size: 0.72rem;
    color: #64748b;
    font-weight: 600;
    margin-top: 2px;
}

.ex-gauge { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; }
.ex-gauge__i { padding: 14px; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 14px; }
.ex-gauge__hd {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.ex-gauge__hd span { font-size: 0.76rem; font-weight: 800; color: #334155; }
.ex-gauge__hd b { font-size: 0.95rem; font-weight: 900; color: #0f172a; }

.wcd-meter { height: 7px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-bottom: 6px; }
.wcd-meter span { display: block; height: 100%; border-radius: 999px; transition: width 0.4s ease; }
.ex-gauge__i small { display: block; font-size: 0.7rem; color: #64748b; font-weight: 500; }

/* TALENT HEALTH METER */
.ex-talent-health {
    margin-top: 14px;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
}
.ex-talent-health__hd {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.76rem;
    font-weight: 800;
    color: #334155;
    margin-bottom: 6px;
}
.ex-talent-health__hd b { color: #7c3aed; font-weight: 900; }

.ex-nota {
    margin: 12px 0 0;
    font-size: 0.76rem;
    line-height: 1.5;
    color: #475569;
}
.ex-nota .bi { color: #6366f1; margin-right: 4px; }
.ex-nota b { color: #0f172a; }

.ex-tw { margin-top: 14px; max-height: 240px; overflow-y: auto; background: #fff; border-radius: 12px; }
</style>
