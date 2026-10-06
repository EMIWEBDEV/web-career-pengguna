export const JENIS_KALENDER = {
    TES: { label: 'Sesi tes', ikon: 'bi-pencil-square', warna: '#0284c7' },
    AGENDA: { label: 'Agenda program', ikon: 'bi-calendar-event-fill', warna: '#6366f1' },
    TUTUP: { label: 'Pendaftaran ditutup', ikon: 'bi-calendar-x-fill', warna: '#d03b3b' },
};

export const KESIAPAN_KALENDER = {
    SIAP: { label: 'Siap', ikon: 'bi-check-circle-fill', warna: '#15803d' },
    MENUNGGU: { label: 'Menunggu', ikon: 'bi-hourglass-split', warna: '#b45309' },
    PERLU_PERHATIAN: { label: 'Perlu perhatian', ikon: 'bi-exclamation-triangle-fill', warna: '#c2410c' },
    SELESAI: { label: 'Selesai', ikon: 'bi-flag-fill', warna: '#64748b' },
};

export function kunciTanggal(value) {
    return value ? String(value).slice(0, 10) : '';
}

export function tambahHari(date, jumlah) {
    const hasil = new Date(date);
    hasil.setDate(hasil.getDate() + jumlah);
    return hasil;
}

export function tanggalApi(date) {
    const tahun = date.getFullYear();
    const bulan = String(date.getMonth() + 1).padStart(2, '0');
    const hari = String(date.getDate()).padStart(2, '0');
    return `${tahun}-${bulan}-${hari}`;
}

export function warnaEvent(event) {
    if (event.konflik) return '#b91c1c';
    if (event.kesiapan === 'PERLU_PERHATIAN') return '#c2410c';
    return JENIS_KALENDER[event.jenis]?.warna || '#6366f1';
}

export function keEventFullCalendar(event) {
    const warna = warnaEvent(event);
    const isFullDay = Boolean(
        event.allDay ||
        event.jenis === 'TUTUP' ||
        (event.akhir && String(event.akhir).includes('23:59'))
    );
    return {
        id: event.id,
        title: event.judul,
        start: event.mulai,
        end: event.akhir || undefined,
        allDay: isFullDay,
        backgroundColor: warna,
        borderColor: warna,
        textColor: '#ffffff',
        extendedProps: event,
    };
}

export function saringEvent(events, { jenis = 'SEMUA', program = '', cari = '' } = {}) {
    const kata = cari.trim().toLocaleLowerCase('id-ID');
    return events.filter((event) => {
        if (jenis !== 'SEMUA' && event.jenis !== jenis) return false;
        if (program && event.program !== program) return false;
        if (!kata) return true;
        return [event.judul, event.program, event.ket]
            .filter(Boolean)
            .some((value) => String(value).toLocaleLowerCase('id-ID').includes(kata));
    });
}

export function formatRentang(event) {
    if (!event?.mulai) return '—';
    const parse = (value) => new Date(String(value).replace(' ', 'T'));
    const mulai = parse(event.mulai);
    if (Number.isNaN(mulai.getTime())) return '—';

    const tanggal = mulai.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    if (event.allDay) {
        // Akhir all-day dari API eksklusif, sehingga dikurangi satu hari untuk dibaca manusia.
        if (!event.akhir) return `${tanggal} · Sepanjang hari`;
        const akhir = tambahHari(parse(event.akhir), -1);
        const hariAkhir = akhir.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        return kunciTanggal(event.mulai) === tanggalApi(akhir) ? `${tanggal} · Sepanjang hari` : `${tanggal} – ${hariAkhir}`;
    }

    const jam = (date) => date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    if (!event.akhir) return `${tanggal} · ${jam(mulai)}`;
    const akhir = parse(event.akhir);
    return `${tanggal} · ${jam(mulai)}–${jam(akhir)}`;
}

export function hitungPersentasePeserta(event) {
    const total = event?.jumlahPeserta || 0;
    if (!total) return 0;
    const ok = event?.pesertaTerkirim || 0;
    return Math.min(100, Math.round((ok / total) * 100));
}

export function salinRingkasanEvent(event) {
    if (!event) return '';
    const jenisLabel = JENIS_KALENDER[event.jenis]?.label || 'Agenda';
    const rentang = formatRentang(event);
    const parts = [
        `📌 ${event.judul || 'Agenda'}`,
        `Jenis: ${jenisLabel}`,
        `Program: ${event.program || '—'}`,
        `Waktu: ${rentang}`,
    ];
    if (event.ket) parts.push(`Keterangan: ${event.ket}`);
    if (event.jumlahPeserta) {
        parts.push(`Peserta: ${event.jumlahPeserta} (${event.pesertaTerkirim || 0} Terkirim)`);
    }
    return parts.join('\n');
}

