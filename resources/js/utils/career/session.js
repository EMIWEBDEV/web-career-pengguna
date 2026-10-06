// ══════════════════════════════════════════════════════════════
//  WEB CAREER — Sesi Kandidat (skenario REAL berbasis sessionStorage)
//  Tanpa DB. Semua identitas + lamaran hidup di sessionStorage browser:
//   · login mengikuti email → nama diturunkan dari email
//   · fresh (belum login) = kosong total
//   · apply WAJIB login; progress disimpan sbg DRAF (bisa dilanjutkan)
//   · finalisasi → status FINAL + progres tahapan seleksi
// ══════════════════════════════════════════════════════════════

const SKEY = 'evo_career_session';
const AKEY = 'evo_career_apps';

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

