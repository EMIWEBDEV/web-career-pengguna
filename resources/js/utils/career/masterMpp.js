// WEB CAREER — Master MPP: helper format tampilan. Salinan sendiri (bukan impor
// lintas folder) — folder monitoring-mpp yang dulu punya versi ini sudah dihapus.

export function titleCase(s) {
    return (s || '')
        .toLowerCase()
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function initials(nama) {
    return (nama || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

export function namaLengkap(nama) {
    return nama || '—';
}

export function formatTanggal(t) {
    if (!t) return '—';
    const d = new Date(`${t}T00:00:00`);
    return isNaN(d.getTime()) ? t : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function periodeLabel(p) {
    if (!p) return '—';
    const [y, m] = p.split('-');
    const nama = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][Number(m) - 1] || m;
    return `${nama} ${y}`;
}

export function statusBadge(status) {
    return status === 'AKTIF' ? 'wca-b--green' : 'wca-b--red';
}

export function statusLabel(status) {
    return status === 'AKTIF' ? 'Aktif' : 'Dibatalkan';
}

// Flag_MT: 'Y' = Management Trainee, 'T'/NULL = Rekrutmen biasa.
export function jenisProgramLabel(j) {
    return j === 'MT' ? 'Management Trainee' : 'Rekrutmen';
}
