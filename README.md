# Web Careers — Pengguna (zona publik)

Situs karier EVO Group untuk **kandidat**: landing page & lowongan, daftar/masuk,
melamar, portal lamaran (tahapan, formulir, unggah berkas tes, konfirmasi
kehadiran, surat jadwal), dan feedback. Panel admin hidup di repo terpisah
(`web-careers`) dan tidak pernah dijangkau langsung oleh project ini.

## Aturan zona

| Boleh | Tidak boleh |
|---|---|
| Database publik (`DB_DATABASE`, mis. `emi_tm_demo`) | Kredensial/database admin (`Web_HRIS`) — aplikasi menolak menyala bila ada koneksi ke database di `DB_ADMIN_TERLARANG` |
| Menulis tabel **milik** publik + `Sinkron_Outbox` (lewat `usp_PUB_Outbox_Tulis`) | Menulis tabel **salinan** dari admin |
| Menulis bucket **karantina**, membaca bucket **publik** | Bucket admin |
| Menerbitkan peristiwa ke Pub/Sub `wc-masuk` | Memanggil API admin, CAT/HCLearn, HCIS, atau EVO Mail |

Aksi kandidat yang perlu diproses admin dicatat sebagai **peristiwa Outbox** di
transaksi yang sama dengan datanya (`App\Support\Sinkron\Outbox`), lalu
diterbitkan ke Pub/Sub oleh `App\Jobs\TerbitkanOutbox` (queue `wcp-terbit`) atau
penyapu `POST /api/tugas/terbit-outbox` (Cloud Scheduler, header `X-Tugas-Token`).
Jenis peristiwa: `Akun.Terdaftar`, `Akun.Diperbarui`, `Akun.KodeDiminta`,
`Lamaran.Dikirim`, `Lamaran.Dibatalkan`, `Formulir.Dikirim`, `Berkas.Diunggah`,
`Konfirmasi.Dijawab`, `Konfirmasi.Dicabut`, `Feedback.Dikirim`.

Portal membaca **potret** per lamaran (`N_WEB_CAREERS_Pub_Portal_Lamaran`, kontrak 1)
yang diisi Sync Worker admin; bagian yang bergantung waktu dihitung ulang saat
dibaca (`App\Support\Portal\PenilaiWaktu`). Selama potret belum datang, portal
menampilkan "sedang diproses".

## Menjalankan lokal

```bash
composer install
npm ci
npm run dev        # atau: npm run build
```

Kunci `.env` yang wajib selain bawaan Laravel: `DB_*` (database publik),
`GCS_BUCKET_KARANTINA`, `GCS_BUCKET_PUBLIK`, `PUBSUB_TOPIK`, `TAUTAN_KUNCI` dan
`SINKRON_KUNCI_RAHASIA` (keduanya **sama dengan admin**), `TUGAS_TOKEN` (milik
project ini sendiri), dan `APP_KEY` sendiri (**berbeda dari admin**).

## Pengujian

```bash
DB_CONNECTION=sqlite DB_DATABASE=":memory:" php vendor/bin/phpunit
```

Pengujian tidak menyentuh database sungguhan: tabel dibuat di SQLite memori dan
Outbox memakai mode palsu (`Outbox::palsukan()`).

## Deploy (Cloud Run)

`Dockerfile` membangun image dari `.env.example` sebagai cadangan; nilai
sebenarnya datang dari variabel lingkungan service. Yang perlu disiapkan:
`QUEUE_CONNECTION=cloudtasks`, `CLOUD_TASKS_*` (queue `wcp-terbit`,
`CLOUD_TASKS_HANDLER_URL` = URL service ini + `/handle-task`), login database
`app_publik` / `penerbit_publik`, akun layanan khusus pengguna, dan job Cloud
Scheduler untuk penyapu Outbox.
