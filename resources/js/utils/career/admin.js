// WEB CAREER — Helper Admin & Portal (badge status, inisial).
// Menu sidebar admin & portal kini dibangun di controller (shellLayout) — bukan lagi konstanta JS.

export function initials(name) {
    if (!name) return '?';
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}

// Warna badge status stage / lamaran
export function statusBadge(status) {
    const map = {
        LULUS: 'wca-b--green',
        PASSED: 'wca-b--green',
        DITERIMA: 'wca-b--green',
        GAGAL: 'wca-b--red',
        FAILED: 'wca-b--red',
        DITOLAK: 'wca-b--red',
        BERJALAN: 'wca-b--indigo',
        IN_PROGRESS: 'wca-b--indigo',
        REVIEW: 'wca-b--amber',
        BORDERLINE: 'wca-b--amber',
        PENDING: 'wca-b--slate',
        SCHEDULED: 'wca-b--sky',
        PENUH: 'wca-b--red',
        BUKA: 'wca-b--green',
        SEGERA: 'wca-b--amber',
        AKTIF: 'wca-b--green',
        SELESAI: 'wca-b--slate',
        DRAFT: 'wca-b--slate',
        TERJADWAL: 'wca-b--sky',
        TERBIT: 'wca-b--green',
        EMBARGO: 'wca-b--amber',
    };
    return map[status] || 'wca-b--slate';
}
