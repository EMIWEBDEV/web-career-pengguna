<!-- WEB CAREER — CTA penutup landing (LIGHT, 1:1 desain "EVO Career Landing") -->
<template>
    <section class="ctal wc-reveal">
        <div class="ctal__panel">
            <span class="ctal__eyebrow"><i class="bi bi-stars"></i> Mulai Perjalananmu</span>
            <h2>Siap naik level bersama <span>EVO Group?</span></h2>
            <p>Ambil langkah pertama menuju karier impianmu hari ini — proses transparan, tim yang suportif, dan ruang untuk bertumbuh.</p>

            <div class="ctal__actions">
                <Link class="ctal__btn ctal__btn--primary" href="/karir/lowongan">
                    <i class="bi bi-search"></i> Lihat Semua Lowongan
                </Link>
                <Link class="ctal__btn ctal__btn--ghost" href="/register">
                    <i class="bi bi-person-plus"></i> Buat Akun
                </Link>
            </div>

            <ul class="ctal__trust">
                <li><i class="bi bi-check-lg"></i> Proses seleksi transparan</li>
                <li><i class="bi bi-check-lg"></i> Respons cepat</li>
                <li><i class="bi bi-check-lg"></i> Lingkungan suportif</li>
            </ul>
        </div>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<style scoped>
.ctal {
    width: min(1180px, calc(100vw - 2rem));
    margin: clamp(2.5rem, 5vw, 3.5rem) auto clamp(3rem, 6vw, 5rem);
}
.ctal__panel {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    padding: clamp(2.5rem, 5vw, 3.25rem) clamp(1.4rem, 4vw, 2.15rem);
    text-align: center;
    background:
        radial-gradient(600px 300px at 12% 100%, rgba(139, 92, 246, 0.16), rgba(139, 92, 246, 0) 60%),
        radial-gradient(600px 300px at 92% 0%, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0) 60%),
        linear-gradient(135deg, #eef2ff 0%, #f2effe 55%, #eaf0ff 100%);
    border: 1px solid rgba(99, 102, 241, 0.16);
    box-shadow: 0 24px 60px rgba(99, 102, 241, 0.1);
}
.ctal__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 6px 13px;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.22);
    font-size: 0.69rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #4f46e5;
}
.ctal__panel h2 {
    margin: 1rem 0 0;
    font-size: clamp(1.5rem, 3.4vw, 1.9rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.18;
    color: #1e1b4b;
    text-wrap: pretty;
}
.ctal__panel h2 span {
    color: #6366f1;
}
.ctal__panel p {
    margin: 0.75rem auto 0;
    max-width: 520px;
    font-size: 0.9rem;
    color: #64748b;
    line-height: 1.6;
    font-weight: 600;
}
.ctal__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
    margin-top: 26px;
}
.ctal__btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    font-weight: 800;
    border-radius: 14px;
    padding: 14px 24px;
    text-decoration: none;
    cursor: pointer;
    transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease, background 0.16s ease;
}
.ctal__btn--primary {
    color: #fff;
    border: none;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 14px 32px rgba(99, 102, 241, 0.34);
}
.ctal__btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 42px rgba(99, 102, 241, 0.44);
    color: #fff;
}
.ctal__btn--ghost {
    color: #4f46e5;
    background: #fff;
    border: 1px solid #d9def0;
}
.ctal__btn--ghost:hover {
    border-color: #a5b4fc;
    background: #fbfbff;
    transform: translateY(-2px);
    color: #4f46e5;
}
.ctal__trust {
    list-style: none;
    margin: 24px 0 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px 18px;
}
.ctal__trust li {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
}
.ctal__trust i {
    color: #10b981;
    font-weight: 900;
}
@media (max-width: 560px) {
    /* basis 14rem: dua tombol tetap sebaris selagi muat, lalu turun ke barisnya
       sendiri (bukan terjepit jadi dua baris teks) di layar sempit. */
    .ctal__actions .ctal__btn {
        flex: 1 1 14rem;
        justify-content: center;
    }
    .ctal__panel {
        border-radius: 22px;
    }
}
</style>
