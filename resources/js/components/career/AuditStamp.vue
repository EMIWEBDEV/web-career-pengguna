<!-- WEB CAREER — Cap audit: avatar inisial + nama akun pembuat + tanggal/jam (dari Created_At). -->
<template>
    <div class="astamp" :title="titleText">
        <span class="astamp__ava" :style="{ background: color }">{{ initials }}</span>
        <span class="astamp__txt">
            <b>{{ by || '—' }}</b>
            <small><i class="bi bi-clock"></i> {{ when }}</small>
        </span>
    </div>
</template>

<script>
export default {
    props: {
        by: { type: String, default: '' },
        at: { type: String, default: '' },
        label: { type: String, default: 'Dibuat' },
        color: { type: String, default: '#6366f1' },
    },
    computed: {
        initials() {
            return (this.by || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
        },
        when() {
            if (!this.at) return '—';
            const d = new Date(String(this.at).replace(' ', 'T'));
            return isNaN(d.getTime()) ? this.at : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        titleText() {
            return `${this.label} oleh ${this.by || '—'} · ${this.when}`;
        },
    },
};
</script>

<style scoped>
.astamp { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
.astamp__ava {
    flex: 0 0 auto;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    color: #fff;
    font: 700 10.5px 'Plus Jakarta Sans', system-ui, sans-serif;
    letter-spacing: 0.02em;
}
.astamp__txt { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
.astamp__txt b { font-size: 11.5px; color: #0f1235; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.astamp__txt small { font-size: 10.5px; color: #7c81a3; display: inline-flex; align-items: center; gap: 4px; }
</style>
