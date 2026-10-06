<!-- WEB CAREER — JADWAL DITUNDA (POV KANDIDAT).

     Kandidat yang jadwalnya ditunda WAJIB tahu tiga hal (permintaan user
     1 Okt 2026): kenapa ditunda, kapan kira-kira penggantinya (atau bahwa
     tim memang belum tahu dan akan mengabari), dan pesan timnya. Dipakai di
     kartu aktivitas Portal Kandidat dan halaman tautan dari email.
     Isinya dari KonfirmasiJadwal::tundaUntukKandidat(). -->
<script setup>
defineProps({
    /** { alasan, perkiraan, perkiraanTeks, pesan, jadwalSemula, ditundaTeks, diperbaruiTeks } */
    tunda: { type: Object, default: null },
    label: { type: String, default: '' },
    kalimat: { type: String, default: '' },
    warna: { type: String, default: '#7c3aed' },
});
</script>

<template>
    <div class="jtd" :style="{ '--nada': warna || '#7c3aed' }">
        <div class="jtd__kepala">
            <span class="jtd__ic"><i class="bi bi-pause-circle-fill"></i></span>
            <div>
                <div class="jtd__alis">Jadwal ditunda</div>
                <div v-if="label" class="jtd__judul">{{ label }}</div>
            </div>
        </div>

        <p class="jtd__kalimat">
            {{ kalimat || 'Jadwal ini ditunda oleh tim rekrutmen.' }}
            Kamu tidak perlu melakukan apa pun sekarang.
        </p>

        <dl class="jtd__info">
            <template v-if="tunda?.alasan">
                <dt>Alasan</dt>
                <dd>{{ tunda.alasan }}</dd>
            </template>
            <dt>Jadwal pengganti</dt>
            <dd>
                <template v-if="tunda?.perkiraanTeks">
                    Diperkirakan <b>{{ tunda.perkiraanTeks }}</b>
                    <small>Waktu dan tempat pastinya menyusul lewat email &amp; halaman ini.</small>
                </template>
                <template v-else>
                    <b>Belum ditetapkan</b> — akan kami kabarkan lewat email &amp; halaman ini.
                </template>
            </dd>
            <template v-if="tunda?.jadwalSemula">
                <dt>Jadwal semula</dt>
                <dd class="jtd__coret">{{ tunda.jadwalSemula }}</dd>
            </template>
        </dl>

        <div v-if="tunda?.pesan" class="jtd__pesan">
            <span><i class="bi bi-chat-left-text-fill"></i> Pesan dari tim rekrutmen</span>
            <p>{{ tunda.pesan }}</p>
        </div>

        <p v-if="tunda?.ditundaTeks" class="jtd__kaki">
            Ditunda {{ tunda.ditundaTeks }}<template v-if="tunda.diperbaruiTeks"> · info diperbarui {{ tunda.diperbaruiTeks }}</template>
        </p>
    </div>
</template>

<style scoped>
.jtd { padding: 14px 16px; border-radius: 16px; background: #fff; border: 1px solid #ede9fe; border-left: 5px solid var(--nada); box-shadow: 0 12px 28px -24px rgba(76, 29, 149, .5); }
.jtd__kepala { display: flex; align-items: center; gap: 11px; }
.jtd__ic { flex: none; width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 17px; background: linear-gradient(135deg, #a78bfa, var(--nada)); }
.jtd__alis { font-size: 10.5px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: var(--nada); }
.jtd__judul { font-size: 15px; font-weight: 800; color: #1e293b; line-height: 1.3; }
.jtd__kalimat { margin: 10px 0 0; font-size: 13px; line-height: 1.6; color: #475569; }
.jtd__info { margin: 12px 0 0; padding: 11px 13px; border-radius: 12px; background: #f5f3ff; display: grid; grid-template-columns: max-content 1fr; gap: 7px 14px; font-size: 13px; }
.jtd__info dt { color: #7c7a99; font-weight: 700; }
.jtd__info dd { margin: 0; color: #1e293b; line-height: 1.5; min-width: 0; }
.jtd__info small { display: block; margin-top: 2px; font-size: 11.5px; color: #64748b; }
.jtd__coret { color: #94a3b8 !important; text-decoration: line-through; }
.jtd__pesan { margin-top: 10px; padding: 10px 12px; border-radius: 12px; border: 1px solid #e9e5ff; background: #fcfbff; }
.jtd__pesan span { display: inline-flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #6366f1; }
.jtd__pesan p { margin: 5px 0 0; font-size: 13px; line-height: 1.6; color: #334155; white-space: pre-line; }
.jtd__kaki { margin: 10px 0 0; font-size: 11.5px; color: #94a3b8; }
@media (max-width: 560px) {
    .jtd__info { grid-template-columns: 1fr; gap: 2px; }
    .jtd__info dd { margin-bottom: 6px; }
}
</style>
