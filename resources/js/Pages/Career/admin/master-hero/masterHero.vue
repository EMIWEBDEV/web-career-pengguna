<!-- WEB CAREER — Master Hero (kelola slide hero landing: gambar desktop/mobile + video).
     Tanpa modal — semua field & media langsung tampil & bisa diedit di kartu list (autosave). -->
<template>
    <Head title="Master Hero" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Hero</h1>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--dark" :disabled="!hasDirty || savingAll" :onClick="!hasDirty || savingAll ? null : saveAll">
                    <span v-if="savingAll" class="wca-spin" aria-hidden="true"></span>
                    <i v-else class="bi bi-save"></i>
                    {{ savingAll ? 'Menyimpan…' : hasDirty ? `Simpan Semua (${dirtyCount})` : 'Simpan Semua' }}
                </button>
                <button class="wca-btn wca-btn--primary" :disabled="creating" :onClick="creating ? null : addSlide">
                    <span v-if="creating" class="wca-spin" aria-hidden="true"></span>
                    <i v-else class="bi bi-plus-lg"></i> Tambah Slide
                </button>
            </div>
        </div>

        <div v-loading="loading" class="mh-list">
            <div v-for="s in sortedList" :key="s.id" class="mh-card" :class="{ off: s.status !== 'AKTIF' }">
                <div v-if="s.tipe === 'IMAGE'" class="mh-media mh-media--image">
                    <div class="mh-slot mh-slot--desktop" v-loading="s._busy.desktop" @click="handleSlotClick(s, 'desktop')">
                        <img v-if="mediaExact(s, 'desktop')" :src="mediaExact(s, 'desktop')" alt="Gambar Desktop" />
                        <div v-else class="mh-slot__empty"><i class="bi bi-display"></i><span>Desktop Image</span></div>
                        <span class="mh-slot__tag">Desktop Image</span>
                        <div v-if="hasMedia(s, 'desktop')" class="mh-slot__actions">
                            <button class="mh-slot__action" title="Ganti gambar desktop" @click.stop="pickMedia(s, 'desktop')"><i class="bi bi-pencil"></i></button>
                            <button class="mh-slot__action mh-slot__action--danger" title="Hapus gambar desktop" @click.stop="removeMedia(s, 'desktop')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mh-slot mh-slot--mobile" v-loading="s._busy.mobile" @click="handleSlotClick(s, 'mobile')">
                        <img v-if="mediaExact(s, 'mobile')" :src="mediaExact(s, 'mobile')" alt="Gambar Mobile" />
                        <div v-else class="mh-slot__empty"><i class="bi bi-phone"></i><span>Mobile Image</span></div>
                        <span class="mh-slot__tag">Mobile Image</span>
                        <div v-if="hasMedia(s, 'mobile')" class="mh-slot__actions">
                            <button class="mh-slot__action" title="Ganti gambar mobile" @click.stop="pickMedia(s, 'mobile')"><i class="bi bi-pencil"></i></button>
                            <button class="mh-slot__action mh-slot__action--danger" title="Hapus gambar mobile" @click.stop="removeMedia(s, 'mobile')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="mh-media mh-media--video">
                    <div class="mh-device">
                        <div class="mh-device__head"><i class="bi bi-display"></i><span>Desktop Video</span></div>

                        <div
                            class="mh-slot mh-slot--poster"
                            v-loading="s._busy.poster_desktop"
                            @click="handleSlotClick(s, 'poster_desktop')"
                        >
                            <img
                                v-if="mediaExact(s, 'poster_desktop')"
                                :src="mediaExact(s, 'poster_desktop')"
                                alt="Poster Video Desktop"
                            />
                            <div v-else class="mh-slot__empty">
                                <i class="bi bi-image"></i><span>Desktop Poster</span>
                            </div>
                            <span class="mh-slot__tag">Desktop Poster</span>
                            <div v-if="hasMedia(s, 'poster_desktop')" class="mh-slot__actions">
                                <button class="mh-slot__action" title="Ganti poster desktop" @click.stop="pickMedia(s, 'poster_desktop')"><i class="bi bi-pencil"></i></button>
                                <button class="mh-slot__action mh-slot__action--danger" title="Hapus poster desktop" @click.stop="removeMedia(s, 'poster_desktop')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>

                        <div
                            class="mh-slot mh-slot--video"
                            v-loading="s._busy.video_desktop"
                            @click="handleSlotClick(s, 'video_desktop')"
                        >
                            <video
                                v-if="mediaExact(s, 'video_desktop')"
                                :src="mediaExact(s, 'video_desktop')"
                                :poster="mediaExact(s, 'poster_desktop')"
                                controls
                                muted
                                autoplay
                                loop
                                playsinline
                                preload="metadata"
                            ></video>
                            <div v-else class="mh-slot__empty">
                                <i class="bi bi-camera-video"></i><span>Desktop Video</span>
                            </div>
                            <span class="mh-slot__tag">Desktop Video</span>
                            <div v-if="hasMedia(s, 'video_desktop')" class="mh-slot__actions">
                                <button class="mh-slot__action" title="Ganti video desktop" @click.stop="pickMedia(s, 'video_desktop')"><i class="bi bi-pencil"></i></button>
                                <button class="mh-slot__action mh-slot__action--danger" title="Hapus video desktop" @click.stop="removeMedia(s, 'video_desktop')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mh-device mh-device--mobile">
                        <div class="mh-device__head"><i class="bi bi-phone"></i><span>Mobile Video</span></div>

                        <div
                            class="mh-slot mh-slot--mobile-poster"
                            v-loading="s._busy.poster_mobile"
                            @click="handleSlotClick(s, 'poster_mobile')"
                        >
                            <img
                                v-if="mediaExact(s, 'poster_mobile')"
                                :src="mediaExact(s, 'poster_mobile')"
                                alt="Poster Video Mobile"
                            />
                            <div v-else class="mh-slot__empty">
                                <i class="bi bi-image"></i><span>Mobile Poster</span>
                            </div>
                            <span class="mh-slot__tag">Mobile Poster</span>
                            <div v-if="hasMedia(s, 'poster_mobile')" class="mh-slot__actions">
                                <button class="mh-slot__action" title="Ganti poster mobile" @click.stop="pickMedia(s, 'poster_mobile')"><i class="bi bi-pencil"></i></button>
                                <button class="mh-slot__action mh-slot__action--danger" title="Hapus poster mobile" @click.stop="removeMedia(s, 'poster_mobile')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>

                        <div
                            class="mh-slot mh-slot--mobile-video"
                            v-loading="s._busy.video_mobile"
                            @click="handleSlotClick(s, 'video_mobile')"
                        >
                            <video
                                v-if="mediaExact(s, 'video_mobile')"
                                :src="mediaExact(s, 'video_mobile')"
                                :poster="mediaExact(s, 'poster_mobile')"
                                controls
                                muted
                                autoplay
                                loop
                                playsinline
                                preload="metadata"
                            ></video>
                            <div v-else class="mh-slot__empty">
                                <i class="bi bi-camera-video"></i><span>Mobile Video</span>
                            </div>
                            <span class="mh-slot__tag">Mobile Video</span>
                            <div v-if="hasMedia(s, 'video_mobile')" class="mh-slot__actions">
                                <button class="mh-slot__action" title="Ganti video mobile" @click.stop="pickMedia(s, 'video_mobile')"><i class="bi bi-pencil"></i></button>
                                <button class="mh-slot__action mh-slot__action--danger" title="Hapus video mobile" @click.stop="removeMedia(s, 'video_mobile')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Field langsung di kartu — perubahan ditandai "belum disimpan" sampai Simpan Semua diklik -->
                <div class="mh-fields">
                    <input
                        v-model="s.label"
                        class="mh-labelinput"
                        maxlength="60"
                        placeholder="Label slide (mis. Budaya Kerja)"
                        @input="markDirty(s)"
                    />

                    <div class="mh-metarow">
                        <label class="mh-metafield">
                            <span>Tipe</span>
                            <el-select v-model="s.tipe" size="small" @change="markDirty(s)">
                                <el-option label="Gambar" value="IMAGE" />
                                <el-option label="Video" value="VIDEO" />
                            </el-select>
                        </label>
                        <label class="mh-metafield">
                            <span>Overlay</span>
                            <el-select v-model="s.overlay" size="small" @change="markDirty(s)">
                                <el-option label="Gelap" value="DARK" />
                                <el-option label="Brand" value="BRAND" />
                                <el-option label="Terang" value="LIGHT" />
                                <el-option label="Tanpa overlay" value="NONE" />
                            </el-select>
                        </label>
                        <label class="mh-metafield">
                            <span>Zoom Animation</span>
                            <el-select v-model="s.zoomAnimation" size="small" @change="markDirty(s)">
                                <el-option label="Ya" value="Y" />
                                <el-option label="Tidak" value="N" />
                            </el-select>
                        </label>
                        <label class="mh-metafield mh-metafield--num">
                            <span>Urutan</span>
                            <el-input-number
                                v-model="s.urutan"
                                :min="0"
                                :max="9999"
                                size="small"
                                @change="markDirty(s)"
                            />
                        </label>
                        <label class="mh-metafield mh-metafield--num">
                            <span>Durasi (ms)</span>
                            <el-input-number
                                v-model="s.durasiMs"
                                :min="1000"
                                :max="60000"
                                :step="500"
                                size="small"
                                @change="markDirty(s)"
                            />
                        </label>
                    </div>

                    <div class="mh-bottomrow">
                        <label class="mh-toggle">
                            <el-switch v-model="s.tampilkanKonten" @change="markDirty(s)" />
                            <span>Tampilkan konten tetap</span>
                        </label>
                        <label class="mh-toggle">
                            <el-switch :model-value="s.status === 'AKTIF'" @change="(v) => setStatus(s, v)" />
                            <span>{{ s.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                        </label>

                        <span v-if="s._dirty" class="mh-dirty"><i class="bi bi-circle-fill"></i> belum disimpan</span>

                        <button
                            class="wca-iconbtn wca-iconbtn--danger mh-delbtn"
                            :class="{ 'is-confirm': s._confirmDel }"
                            :title="s._confirmDel ? 'Klik lagi untuk hapus permanen' : 'Hapus slide'"
                            @click="askRemove(s)"
                        >
                            <i class="bi" :class="s._confirmDel ? 'bi-check-lg' : 'bi-trash'"></i>
                            <span v-if="s._confirmDel">Yakin?</span>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="!loading && !list.length" class="wca-empty">
                <i class="bi bi-images"></i>
                <h4>Belum ada slide hero</h4>
                <p>Klik "Tambah Slide" untuk mengisi carousel hero landing page.</p>
            </div>
        </div>

        <transition name="wca-toast"
            ><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition
        >

        <!-- input file tersembunyi (dipakai semua slot) -->
        <input
            ref="fileInput"
            type="file"
            accept=".jpg,.jpeg,.png,.webp,.mp4,.webm"
            style="display: none"
            @change="onFilePicked"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';

const API = '/api/v1/master-hero';
const CFG = { headers: { Accept: 'application/json' } };
const SLOT_KOLOM = {
    desktop: 'gambarDesktop',
    mobile: 'gambarMobile',
    video_desktop: 'videoDesktopUrl',
    video_mobile: 'videoMobileUrl',
    poster_desktop: 'videoDesktopPoster',
    poster_mobile: 'videoMobilePoster',
};

export default {
    components: { Head },
    data() {
        return {
            list: [],
            loading: false,
            creating: false,
            savingAll: false,
            pending: null, // { slide, slot } — target upload berikutnya
            toast: '',
            tm: null,
            confirmTimers: {},
        };
    },
    computed: {
        sortedList() {
            return [...this.list].sort((a, b) => a.urutan - b.urutan);
        },
        dirtyCount() {
            return this.list.filter((x) => x._dirty).length;
        },
        hasDirty() {
            return this.dirtyCount > 0;
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        decorate(row) {
            return {
                ...row,
                zoomAnimation: row.zoomAnimation || 'N',
                videoDesktopUrl: row.videoDesktopUrl || row.videoUrl || null,
                videoMobileUrl: row.videoMobileUrl || null,
                videoDesktopPoster: row.videoDesktopPoster || row.videoPoster || null,
                videoMobilePoster: row.videoMobilePoster || null,
                _busy: {
                    desktop: false,
                    mobile: false,
                    video_desktop: false,
                    video_mobile: false,
                    poster_desktop: false,
                    poster_mobile: false,
                },
                _dirty: false,
                _confirmDel: false,
            };
        },
        mediaExact(s, slot) {
            const media = {
                desktop: s.gambarDesktop || null,
                mobile: s.gambarMobile || null,
                poster_desktop: s.videoDesktopPoster || null,
                poster_mobile: s.videoMobilePoster || null,
                video_desktop: s.videoDesktopUrl || null,
                video_mobile: s.videoMobileUrl || null,
            };

            return media[slot] || null;
        },
        hasMedia(s, slot) {
            return !!this.mediaExact(s, slot);
        },
        handleSlotClick(s, slot) {
            if (this.hasMedia(s, slot)) return;
            this.pickMedia(s, slot);
        },
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = (res.data.result || []).map(this.decorate);
            } catch (e) {
                this.notice('Gagal memuat data hero slide.');
            } finally {
                this.loading = false;
            }
        },
        async addSlide() {
            if (this.creating) return;
            this.creating = true;
            const nextUrutan = this.list.length ? Math.max(...this.list.map((x) => x.urutan)) + 1 : 0;
            const payload = {
                label: 'Slide Baru',
                tipe: 'IMAGE',
                overlay: 'DARK',
                zoomAnimation: 'N',
                urutan: nextUrutan,
                durasiMs: 5000,
                tampilkanKonten: false,
            };
            try {
                await axios.post(API, payload, CFG);
                await this.load();
                this.notice('Slide ditambahkan — unggah gambar desktop & mobile-nya.');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menambah slide.');
            } finally {
                this.creating = false;
            }
        },
        markDirty(s) {
            s._dirty = true;
        },
        async saveAll() {
            if (this.savingAll || !this.hasDirty) return;
            const dirty = this.list.filter((x) => x._dirty);
            const kosong = dirty.find((x) => !x.label.trim());
            if (kosong) return this.notice('Label tidak boleh kosong.');

            this.savingAll = true;
            let gagal = 0;
            for (const s of dirty) {
                const payload = {
                    label: s.label,
                    tipe: s.tipe,
                    overlay: s.overlay,
                    zoomAnimation: s.zoomAnimation || 'N',
                    urutan: s.urutan,
                    durasiMs: s.durasiMs,
                    tampilkanKonten: s.tampilkanKonten,
                };
                try {
                    await axios.put(`${API}/${s.id}`, payload, CFG);
                    s._dirty = false;
                } catch (e) {
                    gagal++;
                }
            }
            this.savingAll = false;
            this.notice(gagal ? `${gagal} slide gagal disimpan.` : 'Semua perubahan tersimpan.');
        },
        async setStatus(s, v) {
            const prev = s.status;
            s.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${s.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Slide "${s.label}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                s.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(s) {
            if (!s._confirmDel) {
                s._confirmDel = true;
                clearTimeout(this.confirmTimers[s.id]);
                this.confirmTimers[s.id] = setTimeout(() => (s._confirmDel = false), 3000);
                return;
            }
            clearTimeout(this.confirmTimers[s.id]);
            s._confirmDel = false;
            this.removeSlide(s);
        },
        async removeSlide(s) {
            try {
                await axios.delete(`${API}/${s.id}`, CFG);
                this.list = this.list.filter((x) => x.id !== s.id);
                this.notice('Slide dihapus.');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus slide.');
            }
        },
        // ── Upload media (input file tersembunyi dipakai bergantian) ──
        pickMedia(s, slot) {
            this.pending = { slide: s, slot };
            this.$refs.fileInput.value = '';
            this.$refs.fileInput.click();
        },
        isVideoSlot(slot) {
            return slot.startsWith('video_');
        },
        async onFilePicked(e) {
            const file = e.target.files?.[0];
            const { slide: s, slot } = this.pending || {};
            this.pending = null;
            if (!file || !s || !slot) return;

            const isVideo = this.isVideoSlot(slot);
            const maxBytes = isVideo ? 20 * 1024 * 1024 : 2 * 1024 * 1024;
            if (file.size > maxBytes) {
                this.notice(`Ukuran berkas melebihi ${isVideo ? '20 MB' : '2 MB'}.`);
                return;
            }

            s._busy[slot] = true;
            try {
                const fd = new FormData();
                fd.append('slot', slot);
                fd.append('file', file);
                const res = await axios.post(`${API}/${s.id}/media`, fd, CFG);
                s[SLOT_KOLOM[slot]] = res.data.result?.url;
                this.notice('Berkas berhasil diunggah.');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengunggah berkas.');
            } finally {
                s._busy[slot] = false;
            }
        },
        async removeMedia(s, slot) {
            s._busy[slot] = true;
            try {
                await axios.delete(`${API}/${s.id}/media`, { ...CFG, data: { slot } });
                s[SLOT_KOLOM[slot]] = null;
                this.notice('Berkas dihapus.');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus berkas.');
            } finally {
                s._busy[slot] = false;
            }
        },
        notice(x) {
            this.toast = x;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3000);
        },
    },
};
</script>

<style scoped>
.mh-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.mh-card {
    display: grid;
    grid-template-columns: minmax(460px, 2.35fr) minmax(220px, 1.1fr);
    gap: 18px;
    padding: 16px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid rgba(11, 16, 51, 0.08);
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
}
.mh-card.off {
    opacity: 0.6;
}
.mh-media {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
.mh-media {
    width: 100%;
    min-width: 0;
}
.mh-media--image {
    display: grid;
    grid-template-columns: minmax(0, 0.76fr) clamp(180px, 26%, 250px);
    align-items: start;
}
.mh-media--video {
    display: grid;
    grid-template-columns: minmax(0, 0.76fr) clamp(180px, 26%, 250px);
    gap: 12px;
    width: 100%;
}
.mh-device {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.mh-device__head {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 2px 2px 0;
    color: rgba(11, 16, 51, 0.6);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.mh-slot {
    position: relative;
    aspect-ratio: 16 / 9;
    border-radius: 12px;
    overflow: hidden;
    background: #0f1235;
    cursor: pointer;
}
.mh-slot--mobile {
    aspect-ratio: 9 / 16;
}
.mh-slot--mobile-poster,
.mh-slot--mobile-video {
    aspect-ratio: 9 / 16;
}
.mh-slot img,
.mh-slot video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.mh-slot__empty {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    color: rgba(255, 255, 255, 0.45);
    font-size: 20px;
}
.mh-slot__empty span {
    font-size: 10.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.mh-slot__tag {
    position: absolute;
    left: 6px;
    bottom: 6px;
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(15, 18, 53, 0.72);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    pointer-events: none;
}
.mh-slot__actions {
    position: absolute;
    top: 6px;
    right: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.mh-slot__action {
    border: none;
    border-radius: 999px;
    background: rgba(15, 18, 53, 0.84);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 10px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}
.mh-slot__action--danger {
    background: rgba(220, 38, 38, 0.92);
    padding: 0;
    width: 28px;
}
.mh-fields {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}
.mh-labelinput {
    border: none;
    border-bottom: 2px solid rgba(11, 16, 51, 0.1);
    padding: 4px 2px 8px;
    font-size: 17px;
    font-weight: 700;
    color: #0b1033;
    background: transparent;
}
.mh-labelinput:focus {
    outline: none;
    border-bottom-color: #6366f1;
}
.mh-metarow {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.mh-metafield {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1 1 100%;
    min-width: 0;
}
.mh-metafield :deep(.el-select),
.mh-metafield :deep(.el-input-number) {
    width: 100%;
}
.mh-metafield span {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: rgba(11, 16, 51, 0.5);
}
.mh-bottomrow {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 2px;
}
.mh-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    color: rgba(11, 16, 51, 0.75);
}
.mh-dirty {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 600;
    color: #d97706;
}
.mh-dirty i {
    font-size: 7px;
}
.mh-delbtn {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: auto;
    padding: 0 10px;
}
.mh-delbtn.is-confirm {
    background: #dc2626;
    color: #fff;
}

@media (max-width: 860px) {
    .mh-card {
        grid-template-columns: 1fr;
    }
    .mh-media--image,
    .mh-media--video {
        grid-template-columns: 1fr;
    }
}
</style>
