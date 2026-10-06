// ══════════════════════════════════════════════════════════════
//  WEB CAREER — Sesi Kandidat (skenario REAL berbasis sessionStorage)
//  Tanpa DB. Semua identitas + lamaran hidup di sessionStorage browser:
//   · login mengikuti email → nama diturunkan dari email
//   · fresh (belum login) = kosong total
//   · apply WAJIB login; progress disimpan sbg DRAF (bisa dilanjutkan)
//   · finalisasi → status FINAL + progres tahapan seleksi
// ══════════════════════════════════════════════════════════════
import { router } from '@inertiajs/vue3';

const SKEY = 'evo_career_session';
const AKEY = 'evo_career_apps';

// Tahapan seleksi standar (mirror stages() controller admin)
export const STAGES = ['Lamaran Masuk', 'Screening', 'Tes Online', 'Wawancara', 'Penawaran', 'Diterima'];

// Link tes online eksternal (CAT) — dipakai saat kandidat berada di tahap TES.
export const CAT_URL = 'https://cat-evo-stagging-595840247695.asia-southeast1.run.app';

// ══════════════════════════════════════════════════════════════
//  ⛔ DAFTAR DI BAWAH INI BUKAN SUMBER KEBENARAN
//
//  Alur seleksi yang sesungguhnya ada di Master Alur (database) dan dikirim
//  server sebagai `flow.pipeline`. Daftar di sini hanya CADANGAN untuk kasus
//  program belum punya alur sama sekali.
//
//  Dulu halaman apply memakai daftar ini apa adanya, sehingga kandidat melihat
//  tahapan karangan — "Wawancara 1, Wawancara 2, Onboarding" — padahal alur
//  yang benar-benar dijalankan berbeda. Jangan menambah/mengubah tahap di sini;
//  ubahlah di Master Alur.
// ══════════════════════════════════════════════════════════════
export const MT_FLOW = [
    { tipe: 'FORM', label: 'Registrasi & Seleksi Administrasi' },
    { tipe: 'TES', label: 'Tes Potensi Akademik & Psikotes', cat: true },
    { tipe: 'FORM2', label: 'Pengisian Biodata Lanjutan' },
    { tipe: 'TES', label: 'Tes Potensi Akademik & Psikotes 2', cat: true },
    { tipe: 'INTERVIEW', label: 'Wawancara 1' },
    { tipe: 'INTERVIEW', label: 'Wawancara 2' },
    { tipe: 'ONBOARDING', label: 'Onboarding' },
];
export const REK_FLOW = [
    { tipe: 'SCREENING', label: 'Seleksi Administrasi' },
    { tipe: 'INTERVIEW', label: 'Phone Screening' },
    { tipe: 'TES', label: 'Psikotes Online', cat: true },
    { tipe: 'INTERVIEW', label: 'Wawancara User' },
    { tipe: 'OFFERING', label: 'Penawaran & Onboarding' },
];
/**
 * Alur seleksi yang dipakai kandidat.
 * @param jenis   'MT' | 'REKRUTMEN' — hanya menentukan daftar cadangan
 * @param dariDb  pipeline dari server (Master Alur). Dipakai bila ada.
 */
export function flowFor(jenis, dariDb = null) {
    if (Array.isArray(dariDb) && dariDb.length) {
        return dariDb.map((s) => ({ ...s }));
    }
    return (jenis === 'MT' ? MT_FLOW : REK_FLOW).map((s) => ({ ...s }));
}

// ⛔ CADANGAN — bukan sumber kebenaran.
// Syarat yang berlaku ada di Master Program → Syarat (database) dan dikirim
// server sebagai `flow.syarat`. Daftar tetap di bawah hanya dipakai bila
// program belum punya syarat sama sekali. Dulu daftar inilah yang dipakai,
// sehingga kandidat bisa divonis "tidak memenuhi syarat" oleh aturan yang
// tidak pernah dipasang di programnya.
export const KNOCKOUT = {
    MT: [
        { field: 'ipk', op: '>=', value: 3.0, hint: 'IPK minimal 3.00' },
        { field: 'jenjang', op: '>=', value: 'S1', hint: 'Pendidikan minimal S1' },
    ],
    REKRUTMEN: [
        { field: 'ipk', op: '>=', value: 2.5, hint: 'IPK minimal 2.50' },
    ],
};
const JENJANG_RANK = { SMA: 1, SMK: 1, D3: 2, D4: 3, S1: 4, S2: 5, S3: 6 };

/** Bandingkan satu aturan syarat. Jenjang dibandingkan berdasarkan tingkat. */
function bandingkan(field, nilai, operator, pembanding) {
    if (field === 'jenjang') {
        const a = JENJANG_RANK[String(nilai).toUpperCase()] || 0;
        const b = JENJANG_RANK[String(pembanding).toUpperCase()] || 0;
        nilai = a; pembanding = b;
    }
    const angkaA = Number(nilai);
    const angkaB = Number(pembanding);
    const numerik = !Number.isNaN(angkaA) && !Number.isNaN(angkaB);
    switch (operator) {
        case '>=': return numerik ? angkaA >= angkaB : String(nilai) >= String(pembanding);
        case '>': return numerik ? angkaA > angkaB : String(nilai) > String(pembanding);
        case '<=': return numerik ? angkaA <= angkaB : String(nilai) <= String(pembanding);
        case '<': return numerik ? angkaA < angkaB : String(nilai) < String(pembanding);
        case '!=': return String(nilai) !== String(pembanding);
        case '=':
        default: return String(nilai) === String(pembanding);
    }
}

/**
 * Syarat wajib yang MEMBLOKIR kandidat.
 *
 * @param jenis     'MT' | 'REKRUTMEN' — hanya untuk daftar cadangan
 * @param form      jawaban formulir
 * @param dariDb    flow.syarat dari server (Master Program → Syarat)
 *
 * Hanya syarat ber-Aksi GUGUR yang memblokir. Aksi TANDAI berarti "tandai
 * untuk admin" — kandidat tetap lanjut dan adminlah yang memutuskan. Syarat
 * mode uji diabaikan seluruhnya.
 */
export function checkKnockout(jenis, form, dariDb = null) {
    if (Array.isArray(dariDb) && dariDb.length) {
        const fails = [];
        dariDb.forEach((s) => {
            if (s.uji || String(s.aksi).toUpperCase() !== 'GUGUR') return;

            const daftar = s.aturan?.aturan || [];
            if (!daftar.length) return;
            const penghubung = String(s.aturan?.penghubung || 'DAN').toUpperCase();

            const hasil = daftar.map((r) => {
                const val = form[r.field];
                if (val == null || val === '') return null; // belum diisi → belum dinilai
                return bandingkan(r.field, val, r.operator, r.nilai);
            });

            if (hasil.every((h) => h === null)) return; // tidak ada yang bisa dinilai
            const dinilai = hasil.filter((h) => h !== null);
            const lolos = penghubung === 'ATAU' ? dinilai.some(Boolean) : dinilai.every(Boolean);

            if (!lolos) fails.push(s.pesan || s.nama || 'Syarat wajib belum terpenuhi');
        });
        return fails;
    }

    // Cadangan: program belum punya syarat di database.
    const fails = [];
    (KNOCKOUT[jenis] || []).forEach((r) => {
        const val = form[r.field];
        if (val == null || val === '') return;
        if (!bandingkan(r.field, val, r.op, r.value)) fails.push(r.hint);
    });
    return fails; // [] = memenuhi syarat
}

// Teks tindak lanjut sesuai tahap & hasil terkini.
export function nextActionFor(app) {
    if (!app) return '';
    const flow = app.pipeline || [];
    const s = flow[app.stageIdx] || {};
    if (app.result === 'GAGAL') {
        if (app.knockout?.length) return `Mohon maaf, lamaran Anda belum memenuhi syarat wajib: ${app.knockout.join(' · ')}. Anda dapat melamar posisi lain yang sesuai.`;
        if (app.failStage) return `Mohon maaf, Anda belum lolos pada tahap ${app.failStage}. Terima kasih atas partisipasinya — pantau lowongan lain di EVO Group.`;
        return 'Mohon maaf, lamaran Anda belum berhasil pada tahap ini. Tetap semangat — pantau lowongan lain di EVO Group.';
    }
    if (app.result === 'LULUS' || s.tipe === 'ONBOARDING') return 'Selamat! Anda dinyatakan LULUS. Tim kami akan menghubungi Anda untuk proses onboarding & kontrak.';
    switch (s.tipe) {
        case 'FORM':
        case 'SCREENING': return 'Lamaran Anda sedang ditinjau tim rekrutmen pada tahap Seleksi Administrasi.';
        case 'TES': return `Anda diundang mengikuti ${s.label}. Klik tombol akses tes untuk memulai — pastikan koneksi stabil.`;
        case 'FORM2': return 'Selamat, Anda lolos ke tahap berikutnya! Lengkapi Formulir Tahap Lanjut (data & dokumen tambahan).';
        case 'INTERVIEW': return `Anda dijadwalkan mengikuti ${s.label}. Detail jadwal & tautan dikirim ke email Anda.`;
        default: return 'Lamaran Anda sedang diproses tim rekrutmen.';
    }
}

function read(key, fb) {
    try { const v = JSON.parse(sessionStorage.getItem(key)); return v == null ? fb : v; } catch { return fb; }
}
function write(key, val) {
    try { sessionStorage.setItem(key, JSON.stringify(val)); } catch { /* quota / private mode */ }
}
function today() { return new Date().toISOString().slice(0, 10); }
function titleCase(s) { return s.replace(/\b\w/g, (c) => c.toUpperCase()); }
function deriveName(email) {
    const u = (email || '').split('@')[0].replace(/[._+-]+/g, ' ').trim();
    return u ? titleCase(u) : 'Kandidat';
}

/* ── Sesi ── */
export function getSession() { return read(SKEY, null); }
export function isLoggedIn() { return !!getSession(); }
export function login(email, nama) {
    const s = { email: email || '', nama: (nama && nama.trim()) || deriveName(email), at: today() };
    write(SKEY, s);
    return s;
}
export function logout() { try { sessionStorage.removeItem(SKEY); } catch { /* noop */ } }

// Sinkron dari sesi server (prop Inertia `careerAuth`) → sessionStorage,
// supaya portal/apply (yang berbasis sessionStorage) mengenali user login real.
export function syncFromServer(s) {
    if (!s || !s.email) return getSession();
    const cur = getSession();
    if (!cur || cur.email !== s.email) {
        write(SKEY, { email: s.email, nama: s.nama || deriveName(s.email), role: s.role || 'KANDIDAT', id: s.id || null, at: today() });
    }
    return getSession();
}

/* ── Lamaran (di-key oleh lowonganId — satu lamaran per lowongan) ── */
export function getApps() { return read(AKEY, []); }
function saveApps(a) { write(AKEY, a); }
export function getApp(lowonganId) { return getApps().find((x) => x.lowonganId === lowonganId) || null; }
export function upsertApp(app) {
    const a = getApps();
    const i = a.findIndex((x) => x.lowonganId === app.lowonganId);
    if (i >= 0) a[i] = { ...a[i], ...app, updatedAt: today() };
    else a.unshift({ ...app, updatedAt: today() });
    saveApps(a);
}
export function removeApp(lowonganId) { saveApps(getApps().filter((x) => x.lowonganId !== lowonganId)); }
export function clearApps() { try { sessionStorage.removeItem(AKEY); } catch { /* noop */ } }
export function clearAll() { logout(); clearApps(); }

// Umur dari tanggal lahir (ISO). null bila kosong/invalid.
function ageFrom(iso) {
    if (!iso) return null;
    const b = new Date(iso + 'T00:00:00');
    if (isNaN(b.getTime())) return null;
    const now = new Date();
    let age = now.getFullYear() - b.getFullYear();
    const m = now.getMonth() - b.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < b.getDate())) age--;
    return age >= 0 ? age : null;
}

/* ── Mutator ADMIN (worklist) → tulis balik ke sessionStorage, kandidat lihat real-time ── */
export function advanceApp(lowonganId) {
    const a = getApp(lowonganId);
    if (!a) return null;
    const flow = a.pipeline || [];
    if (a.stageIdx < flow.length - 1) a.stageIdx++;
    a.result = a.stageIdx >= flow.length - 1 ? 'LULUS' : 'BERJALAN';
    a.stageStatus = 'BARU';
    a.nextAction = nextActionFor(a);
    upsertApp(a);
    return a;
}
export function rejectApp(lowonganId) {
    const a = getApp(lowonganId);
    if (!a) return null;
    a.result = 'GAGAL';
    a.stageStatus = 'DITOLAK';
    a.failStage = (a.pipeline || [])[a.stageIdx]?.label || 'Seleksi';
    a.nextAction = nextActionFor(a);
    upsertApp(a);
    return a;
}

/* ── Pengumuman resmi → set hasil kelulusan + tempel banner ke dashboard kandidat ── */
export function announceResult(lowonganId, ann) {
    const a = getApp(lowonganId);
    if (!a) return null;
    a.pengumuman = { judul: ann.judul || 'Pengumuman Hasil Seleksi', isi: ann.isi || '', tanggal: ann.tanggal || null, mode: ann.mode || 'IMMEDIATE', hasil: ann.apply || 'INFO' };
    if (ann.apply === 'LULUS') { a.result = 'LULUS'; a.stageIdx = Math.max(0, (a.pipeline || []).length - 1); a.stageStatus = 'LULUS'; }
    else if (ann.apply === 'GAGAL') { a.result = 'GAGAL'; a.stageStatus = 'DITOLAK'; a.failStage = a.failStage || (a.pipeline || [])[a.stageIdx]?.label || 'Seleksi'; }
    a.nextAction = nextActionFor(a);
    upsertApp(a);
    return a;
}
export function resetApp(lowonganId) {
    const a = getApp(lowonganId);
    if (!a) return null;
    a.result = 'BERJALAN';
    a.stageStatus = 'BARU';
    a.stageIdx = 0;
    a.nextAction = nextActionFor(a);
    upsertApp(a);
    return a;
}

/* ── Adaptor: lamaran sessionStorage → objek pelamar untuk worklist admin ── */
export function appsAsApplicants(kategori) {
    return getApps()
        .filter((a) => a.status === 'FINAL' && a.jenis === kategori)
        .map((a) => {
            const f = a.form || {};
            const byLabel = {};
            (a.bio || []).forEach((b) => { byLabel[b.label] = b.value; });
            return {
                id: 'SS-' + a.lowonganId,
                lowonganId: a.lowonganId,
                _ss: true,
                nama: f.nama || byLabel['Nama Lengkap'] || 'Kandidat',
                email: f.email || byLabel['Email'] || '-',
                phone: f.hp || f.phone || byLabel['No. HP'] || '-',
                posisi: a.posisi,
                asal: kategori === 'MT' ? 'KAMPUS' : 'UMUM',
                kampus: f.kampus || null,
                stageIdx: a.stageIdx || 0,
                stageStatus: a.stageStatus || 'BARU',
                result: a.result || 'BERJALAN',
                skor: null,
                appliedAt: a.appliedAt || a.updatedAt,
                facePhoto: a.facePhoto || null,
                biodata: {
                    ttl: f.lahir || '-', umur: (f.umur ? Number(f.umur) : ageFrom(f.lahir)), jkel: f.jkel || '-',
                    alamat: f.domisili || f.alamat || '-', domisili: f.domisili || f.kota || '-',
                    jenjang: f.jenjang || null, jurusan: f.jurusan || f.prodi || '-',
                    ipk: f.ipk ? Number(f.ipk) : null,
                    pendidikan: [f.jenjang, f.jurusan, f.kampus].filter(Boolean).join(' · ') || '-',
                    pengalaman: (a.experiences || []).map((x) => `${x.posisi} @ ${x.perusahaan}`).join('; ') || '-',
                },
                berkas: Object.entries(a.files || {}).map(([k, v]) => ({ nama: v.name || k, tipe: v.isPdf ? 'PDF' : 'IMG', ada: true, tahap: 0 })),
                rejected: a.result === 'GAGAL',
                autoGugur: false, gugurReason: [],
            };
        });
}

/* ── Sesi Tes Online (CAT): token + OTP + jendela waktu, tersimpan agar stabil ── */
function hashSeed(seed) {
    let h = 2166136261;
    for (let i = 0; i < seed.length; i++) { h ^= seed.charCodeAt(i); h = Math.imul(h, 16777619); }
    return h >>> 0;
}
function tokenFrom(seed, len = 6) {
    const A = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tanpa karakter mirip (0/O, 1/I)
    let h = hashSeed(seed), s = '';
    for (let i = 0; i < len; i++) { h = Math.imul(h ^ (i + 7), 16777619) >>> 0; s += A[h % A.length]; }
    return s;
}
function otpFrom(seed) { return String(hashSeed(seed + '#otp') % 1000000).padStart(6, '0'); }

// Pastikan ada sesi tes untuk tahap TES aktif; dibuat sekali lalu dipersist (mulai/selesai stabil).
export function ensureTestSession(lowonganId, windowHours = 48) {
    const a = getApp(lowonganId);
    if (!a) return null;
    const idx = a.stageIdx;
    const s = (a.pipeline || [])[idx] || {};
    if (s.tipe !== 'TES') return null;
    a.testSessions = a.testSessions || {};
    if (!a.testSessions[idx]) {
        const seed = `${lowonganId}#${idx}#${a.form?.email || ''}`;
        const now = Date.now();
        a.testSessions[idx] = {
            token: 'CAT-' + tokenFrom(seed, 6),
            otp: otpFrom(seed),
            mulai: now,
            selesai: now + windowHours * 3600 * 1000,
            label: s.label,
        };
        upsertApp(a);
    }
    return a.testSessions[idx];
}

/* ── Guard: wajib login sebelum aksi (mis. apply) ── */
export function requireLogin(redirectTo) {
    if (isLoggedIn()) return true;
    const q = redirectTo ? `?redirect=${encodeURIComponent(redirectTo)}` : '';
    router.visit(`/login${q}`);
    return false;
}
