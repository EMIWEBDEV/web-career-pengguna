<!--
    KETERANGAN YANG DIMINTA, BUKAN YANG DIPAKSAKAN.

    ══ KENAPA IKON, BUKAN KALIMAT DI BAWAH LABEL ═══════════════════════════════

    Formulir master skrining punya dua puluhan isian, dan hampir semuanya butuh
    penjelasan sekali seumur hidup — orang yang sudah paham "Sub-kelompok" tidak
    perlu membacanya lagi setiap kali membuka jendela. Menulis semuanya sebagai
    kalimat abu di bawah tiap label membuat formulirnya dua kali lebih panjang
    demi keterangan yang, setelah minggu pertama, tidak dibaca siapa pun lagi.

    Ikon tanya menyimpannya sampai diminta. Yang baru belajar menemukannya di
    tempat yang sama setiap kali; yang sudah hafal tidak pernah melihatnya.

    ══ KENAPA KOMPONEN SENDIRI ════════════════════════════════════════════════

    Supaya seluruh keterangan di aplikasi ini muncul dengan bentuk, jeda, dan
    lebar yang sama. Tooltip yang lebarnya berbeda-beda di tiap halaman terbaca
    seperti tiga aplikasi yang kebetulan ditempel jadi satu.

    ══ CARA DIPAKAI ═══════════════════════════════════════════════════════════

        <label class="skr-lbl">
            Kelompok
            <InfoTip>Ia jadi <b>judul bagian</b> di template.</InfoTip>
        </label>
-->
<template>
    <el-tooltip
        :placement="placement"
        :show-after="120"
        :hide-after="0"
        popper-class="skr-pop info-tip__pop"
    >
        <template #content>
            <div class="info-tip__isi"><slot /></div>
        </template>
        <!--
            type="button" wajib: tanpa itu tombol di dalam <form> mengirim
            formulirnya, dan orang yang cuma ingin membaca keterangan justru
            menyimpan data setengah jadi.

            tabindex="-1" karena ini bukan perhentian yang berguna saat mengisi
            dengan papan tik — isinya juga tersedia lewat atribut title.
        -->
        <button class="info-tip" type="button" tabindex="-1" :aria-label="label">
            <i class="bi bi-question-circle"></i>
        </button>
    </el-tooltip>
</template>

<script>
export default {
    name: 'InfoTip',
    props: {
        placement: { type: String, default: 'top' },
        label: { type: String, default: 'Keterangan' },
    },
};
</script>
