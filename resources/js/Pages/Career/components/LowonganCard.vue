<!-- WEB CAREER — Kartu Lowongan Ultra-Premium (Grid & List View + Bookmark Support + Ultra-Compact Mobile Density) -->
<template>
    <div class="rek__card-wrapper" :class="{ 'is-list-mode': viewMode === 'list' }">
        <Link class="rek__card" :class="{ 'is-list': viewMode === 'list' }" :href="lowonganUrl(job.id)">
            <div class="rek__top">
                <span class="rek__type" :class="typeClass(job.tipeKerja)">
                    <i class="bi" :class="typeIcon(job.tipeKerja)"></i> {{ job.tipeKerja }}
                </span>
                <span v-if="job.unggulan" class="rek__star">
                    <i class="bi bi-star-fill"></i> Unggulan
                </span>
                <!-- Posisi milik program MT: tandai asalnya. Di halaman program
                     induknya sudah jelas, jadi badge ini disembunyikan di sana. -->
                <span v-if="job.induk && showInduk" class="rek__mt">
                    <i class="bi bi-mortarboard-fill"></i> {{ job.induk.nama }}
                </span>

                <!-- Bookmark Button -->
                <button
                    v-if="showSave"
                    type="button"
                    class="rek__bookmark-btn"
                    :class="{ 'is-saved': isSaved }"
                    :title="isSaved ? 'Hapus dari simpanan' : 'Simpan lowongan ini'"
                    @click.prevent.stop="$emit('toggle-save', job.id)"
                >
                    <i class="bi" :class="isSaved ? 'bi-bookmark-fill' : 'bi-bookmark'"></i>
                </button>
            </div>

            <div class="rek__main-info">
                <h3 class="rek__title">{{ job.posisi }}</h3>

                <!-- Sorotan BENEFIT (dari MPP) -->
                <div v-if="(job.benefit || []).length" class="rek__salary">
                    <i class="bi bi-gift-fill"></i>
                    <span>{{ job.benefit[0] }}<template v-if="job.benefit.length > 1"> · +{{ job.benefit.length - 1 }} benefit</template></span>
                </div>

                <p class="rek__desc">{{ job.ringkasan }}</p>
            </div>

            <div class="rek__meta">
                <span><i class="bi bi-geo-alt-fill"></i> {{ lokasiLabel(job) }}</span>
                <span v-if="job.pengalaman && job.pengalaman !== '—'"><i class="bi bi-briefcase-fill"></i> {{ job.pengalaman }}</span>
            </div>

            <div class="rek__tags">
                <span v-for="s in (job.skill || []).slice(0, 3)" :key="s">{{ s }}</span>
                <span v-if="(job.skill || []).length > 3" class="rek__tags-more">+{{ job.skill.length - 3 }}</span>
            </div>

            <div class="rek__foot">
                <!-- Hanya JUMLAH PELAMAR. Kuota tidak lagi dikirim backend:
                     jumlah kursi adalah angka perencanaan internal. -->
                <div class="rek__people">
                    <span class="rek__applicant-txt"><i class="bi bi-people-fill"></i> {{ job.pelamar }} pelamar</span>
                </div>
                <span class="rek__cta">Lihat detail <i class="bi bi-arrow-right"></i></span>
            </div>
        </Link>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { lokasiLabel, lowonganUrl } from '@utils/career/data';

defineProps({
    job: { type: Object, required: true },
    isSaved: { type: Boolean, default: false },
    viewMode: { type: String, default: 'grid' },
    // Daftar simpanan hanya hidup di halaman /karir/lowongan. Di halaman lain
    // (mis. daftar posisi sebuah program MT) tombolnya tidak punya penampung.
    showSave: { type: Boolean, default: true },
    showInduk: { type: Boolean, default: true },
});

defineEmits(['toggle-save']);

function typeClass(type) {
    if (type === 'Contract') return 'is-contract';
    if (type === 'Internship') return 'is-intern';
    return 'is-fulltime';
}

function typeIcon(type) {
    if (type === 'Contract') return 'bi-clock-history';
    if (type === 'Internship') return 'bi-mortarboard-fill';
    return 'bi-briefcase-fill';
}
</script>

<style scoped>
.bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    vertical-align: middle;
}

.rek__card-wrapper {
    width: 100%;
}
.rek__card {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 20px;
    padding: 22px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    text-decoration: none;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease;
}
.rek__card:hover {
    transform: translateY(-4px);
    border-color: rgba(139, 92, 246, 0.4);
    box-shadow: 0 20px 40px rgba(99, 102, 241, 0.14);
}
.rek__top {
    display: flex;
    align-items: center;
    gap: 8px;
}
.rek__type {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    padding: 5px 12px;
    border-radius: 999px;
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.1);
}
.rek__type.is-contract {
    color: #b45309;
    background: rgba(245, 158, 11, 0.13);
}
.rek__type.is-intern {
    color: #059669;
    background: rgba(16, 185, 129, 0.12);
}
.rek__star {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.65rem;
    font-weight: 800;
    color: #b45309;
    background: rgba(245, 158, 11, 0.14);
    border: 1px solid rgba(245, 158, 11, 0.28);
    border-radius: 999px;
    padding: 5px 10px;
}
.rek__mt {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    max-width: 12rem;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: 0.63rem;
    font-weight: 800;
    color: #7c3aed;
    background: rgba(139, 92, 246, 0.11);
    border: 1px solid rgba(139, 92, 246, 0.24);
    border-radius: 999px;
    padding: 5px 10px;
}

/* Bookmark Button */
.rek__bookmark-btn {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 50%;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #94a3b8;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 4;
}
.rek__bookmark-btn:hover {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    border-color: rgba(99, 102, 241, 0.3);
    transform: scale(1.08);
}
.rek__bookmark-btn.is-saved {
    background: #6366f1;
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.rek__title {
    margin: 14px 0 0;
    font-size: 1.1rem;
    font-weight: 800;
    color: #1e293b;
    letter-spacing: -0.01em;
    line-height: 1.28;
    text-wrap: pretty;
}
.rek__salary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #8b5cf6;
    margin-top: 8px;
}
.rek__desc {
    margin: 10px 0 0;
    font-size: 0.82rem;
    line-height: 1.58;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.rek__meta {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 14px;
}
.rek__meta span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 0.76rem;
    color: #64748b;
}
.rek__meta i {
    color: #8b5cf6;
    font-size: 0.84rem;
}
.rek__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 14px;
}
.rek__tags span {
    font-size: 0.68rem;
    font-weight: 700;
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.16);
    border-radius: 8px;
    padding: 4px 10px;
}
.rek__tags-more {
    color: #64748b !important;
    background: #f1f5f9 !important;
    border-color: #e2e8f0 !important;
}
.rek__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid #f1f2f9;
}
.rek__people {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.72rem;
    color: #64748b;
}
.rek__cta {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.78rem;
    font-weight: 800;
    color: #4f46e5;
    white-space: nowrap;
}
.rek__cta i {
    transition: transform 0.2s ease;
}
.rek__card:hover .rek__cta i {
    transform: translateX(4px);
}

/* ═══ LIST VIEW VARIANT (Penyelarasan Sejajar) ═══ */
.rek__card.is-list {
    display: grid;
    grid-template-columns: minmax(220px, 1.2fr) minmax(180px, 1fr) minmax(140px, 0.8fr) auto;
    align-items: center;
    gap: 1.25rem;
    padding: 16px 24px;
}
.rek__card.is-list .rek__top {
    display: flex;
    align-items: center;
    gap: 8px;
    grid-column: span 4;
    margin-bottom: -4px;
}
.rek__card.is-list .rek__title {
    margin-top: 0;
    font-size: 1.05rem;
}
.rek__card.is-list .rek__desc {
    display: none;
}
.rek__card.is-list .rek__salary {
    margin-top: 4px;
}
.rek__card.is-list .rek__meta {
    margin-top: 0;
}
.rek__card.is-list .rek__tags {
    margin-top: 0;
}
.rek__card.is-list .rek__foot {
    margin-top: 0;
    padding-top: 0;
    border-top: none;
    justify-content: flex-end;
}

@media (max-width: 767.98px) {
    .rek__card {
        padding: 12px 12px;
        border-radius: 14px;
    }
    .rek__title {
        font-size: 0.96rem;
        margin-top: 6px;
        line-height: 1.25;
    }
    .rek__salary {
        font-size: 0.72rem;
        margin-top: 4px;
    }
    .rek__desc {
        font-size: 0.78rem;
        margin-top: 4px;
        line-height: 1.4;
    }
    .rek__meta {
        gap: 4px;
        margin-top: 8px;
    }
    .rek__meta span {
        font-size: 0.7rem;
    }
    .rek__tags {
        gap: 4px;
        margin-top: 8px;
    }
    .rek__tags span {
        font-size: 0.64rem;
        padding: 3px 8px;
    }
    .rek__foot {
        margin-top: 10px;
        padding-top: 8px;
    }
    .rek__people {
        font-size: 0.68rem;
    }
    .rek__cta {
        font-size: 0.73rem;
    }
    /* 📱 MOBILE LIST — kartu berdiri sendiri, rapi & mudah dipindai.
       PENTING: align-items & flex-direction WAJIB di-reset di sini. Aturan
       desktop `.rek__card.is-list` memakai grid + align-items:center, dan
       `.rek__meta` dasarnya flex-direction:column — tanpa reset, isi kartu
       ikut ter-center dan metadata menumpuk ke bawah. */
    .rek__card.is-list {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 0;
        padding: 14px 15px 12px 17px;
        border-radius: 16px;
        background: #ffffff;
        border: 1px solid #e9edf7;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.045);
        text-decoration: none;
        transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
    }
    /* Tulang punggung warna di tepi kiri — penanda kartu sekaligus aksen visual */
    .rek__card.is-list::before {
        content: '';
        position: absolute;
        left: 0;
        top: 14px;
        bottom: 14px;
        width: 3px;
        border-radius: 0 3px 3px 0;
        background: linear-gradient(180deg, #a78bfa, #6366f1);
    }
    .rek__card.is-list:active {
        transform: scale(0.99);
        background: #f8fafc;
    }
    /* Order 1: Top Header Bar (Type Badge on Left, Bookmark on Right) */
    .rek__card.is-list .rek__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        order: 1;
        margin-bottom: 2px;
        width: 100%;
    }
    .rek__card.is-list .rek__top .rek__type {
        display: inline-flex;
        font-size: 0.65rem;
        padding: 4px 9px;
        border-radius: 8px;
    }
    .rek__card.is-list .rek__top .rek__star {
        display: none;
    }
    .rek__card.is-list .rek__top .rek__bookmark-btn {
        width: 30px;
        height: 30px;
        font-size: 0.8rem;
        margin-left: auto;
        flex-shrink: 0;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    
    /* Order 2: Full Width Job Title Header (100% Align Left) */
    .rek__card.is-list .rek__main-info {
        order: 2;
        margin-top: 0;
        width: 100%;
        text-align: left;
    }
    .rek__card.is-list .rek__title {
        font-size: 1rem;
        font-weight: 800;
        margin: 0;
        color: #0f172a;
        line-height: 1.3;
        text-align: left;
        white-space: normal;
    }
    
    .rek__card.is-list .rek__salary,
    .rek__card.is-list .rek__desc,
    .rek__card.is-list .rek__tags {
        display: none !important;
    }
    
    /* Order 3: Subline Metadata (100% Align Left) */
    .rek__card.is-list .rek__meta {
        order: 3;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
        gap: 4px;
        margin-top: 4px;
        width: 100%;
    }
    .rek__card.is-list .rek__meta span {
        font-size: 0.74rem;
        font-weight: 600;
        color: #64748b;
        line-height: 1.35;
        text-align: left;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .rek__card.is-list .rek__meta i {
        font-size: 0.78rem;
        color: #6366f1;
    }
    
    /* Order 4: Quota & Action CTA Footer (100% Align Left + Right CTA) */
    .rek__card.is-list .rek__foot {
        order: 4;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px dashed #e2e8f0;
        width: 100%;
    }
    .rek__card.is-list .rek__people {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.72rem;
        color: #64748b;
    }
    .rek__card.is-list .rek__cta {
        font-size: 0.75rem;
        font-weight: 800;
        color: #4f46e5;
        background: rgba(99, 102, 241, 0.09);
        border-radius: 999px;
        padding: 6px 13px;
    }
}
</style>
