# Panduan Kerja Bersama — Web Careers

> Ditulis karena repo ini dikerjakan lebih dari satu orang secara paralel.
> Ikuti pola di sini dan `app/Support/CareerShell.php` **tidak perlu disentuh
> sama sekali** — berkas itu dulu penyebab bentrok nomor satu.

---

## ⛔ Aturan utama: jangan menambah menu lewat kode

Dulu setiap fitur baru menyisipkan satu baris di array `CareerShell::adminNav()`.
Dua orang menambah fitur di minggu yang sama → menabrak baris yang sama saat
merge → yang kalah merge **kehilangan menunya diam-diam** (route & halaman utuh,
tapi menu lenyap dari sidebar).

Sekarang menu dibaca dari tabel `N_WEB_CAREERS_Menu`. Yang tersisa di kode
tinggal daftar **darurat**, hanya terpakai bila tabel menu belum ada.

---

## Shell sudah dipecah — `CareerShell.php` dibekukan

`CareerShell.php` dipakai ~50 halaman. Selama isinya menu + brand + layout jadi
satu, setiap penyesuaian kecil menyentuh berkas yang sama. Sekarang berkas itu
tinggal penerus (94 baris, tanpa logika), dan isinya pindah ke berkas
masing-masing:

| Mau mengubah apa | Sunting di |
|---|---|
| Menambah / mengubah menu | **jangan di kode** → `/master-menu`, lalu `/hak-akses` |
| Logo, nama aplikasi, anak usaha | `config/career_shell.php` |
| Judul modul, subtitle, ikon | `config/career_shell.php` |
| Props global untuk semua halaman | daftarkan kelasmu di `config/career_shell.php` → `props_tambahan` |
| Aturan peran / identitas pengguna | `app/Support/Career/Shell/IdentitasShell.php` |
| Bentuk payload layout & breadcrumb | `app/Support/Career/Shell/LayoutShell.php` |
| Cadangan menu saat DB kosong | `app/Support/Career/Shell/NavigasiShell.php` |

Karena tiap orang biasanya butuh hal yang berbeda, dua orang jarang menyentuh
berkas yang sama — dan `CareerShell.php` sendiri tidak perlu disentuh lagi.

### Butuh props yang ada di semua halaman?

Jangan sunting `PropsShell`. Buat kelas di folder fiturmu:

```php
namespace App\Support\Career\Shell\Tambahan;

class PropsAnu
{
    public static function tambahan(string $url, string $judul): array
    {
        return ['anu' => ['jumlah' => 3]];
    }
}
```

lalu tambah **satu baris di akhir** daftar `props_tambahan` pada
`config/career_shell.php`. Penyedia yang error dicatat ke log dan dilewati —
halaman tidak ikut jatuh.

---

## Langkah menambah satu halaman baru

### 1. Daftarkan menunya lewat UI — bukan kode

**Master Menu** (`/master-menu`) → tombol **Menu Baru**

| Isian | Contoh | Catatan |
|---|---|---|
| Kunci Halaman | `masterAnuPage` | dipakai middleware; **jangan diubah** setelah dipakai route |
| Nama Menu | Master Anu | |
| Grup (Header) | Master Data | menu segrup otomatis menyatu di sidebar |
| URL | `/master-anu` | |
| Ikon | `bi bi-box` | |
| Untuk Peran | ADMIN / KANDIDAT | |
| Urutan | 15 | posisi dalam grup |

### 2. Beri hak aksesnya

**Manajemen Hak Akses** (`/hak-akses`) → **Beri Akses** → pilih pengguna →
centang halamannya → atur aksi (VIEW / CREATE / EDIT / DELETE).

### 3. Buat berkasnya — semuanya di folder sendiri, bebas bentrok

```
app/Http/Controllers/Career/MasterAnu/MasterAnuController.php
routes/career/MasterAnu/MasterAnuWeb.php
resources/js/Pages/Career/admin/master-anu/masterAnu.vue
```

### 4. Kunci route-nya dengan izin

```php
Route::get('/master-anu', [MasterAnuController::class, 'index'])
    ->name('career.master-anu')
    ->middleware('career.permission:masterAnuPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-anu.')->group(function () {
    Route::get('/master-anu', [MasterAnuController::class, 'list'])
        ->middleware('career.permission:masterAnuPage,VIEW');
    Route::post('/master-anu', [MasterAnuController::class, 'store'])
        ->middleware('career.permission:masterAnuPage,CREATE');
    Route::put('/master-anu/{id}', [MasterAnuController::class, 'update'])
        ->middleware('career.permission:masterAnuPage,EDIT');
    Route::delete('/master-anu/{id}', [MasterAnuController::class, 'destroy'])
        ->middleware('career.permission:masterAnuPage,DELETE');
});
```

### 5. Daftarkan berkas route-nya

Satu baris di `routes/career/fransDeveloperDevEvo.php`:

```php
require base_path('routes/career/MasterAnu/MasterAnuWeb.php');
```

> Ini satu-satunya berkas bersama yang tersentuh. Karena hanya **menambah satu
> baris di akhir daftar**, git hampir selalu bisa menggabungkannya sendiri.
> Kalau tetap konflik: pertahankan **kedua** baris `require`.

---

## Peta berkas bersama

| Berkas | Frekuensi disentuh | Aturan |
|---|---|---|
| `app/Support/CareerShell.php` | **jangan — dibekukan** | lihat tabel "Shell sudah dipecah" di atas |
| `app/Support/Career/Shell/*.php` | jarang | tiap urusan punya berkasnya sendiri |
| `config/career_shell.php` | jarang | tambah `props_tambahan` di **akhir** daftar |
| `routes/career/fransDeveloperDevEvo.php` | 1 baris per fitur | tambah di **akhir** daftar |
| `resources/css/evo-theme.css` | jarang | gaya khusus halaman taruh di `<style scoped>` |
| `app/Http/Kernel.php` | hampir tak pernah | |

Selebihnya setiap fitur hidup di foldernya sendiri → tidak mungkin bentrok.

---

## Alur harian yang disarankan

```bash
# sebelum mulai kerja
git fetch origin && git merge origin/master

# setelah selesai
npm run build
git add -A && git commit -m "feat(anu): ..."
git fetch origin && git merge origin/master   # tarik dulu
git push origin <branch>
```

Tarik dulu sebelum push — merge kecil tiap hari jauh lebih mudah daripada merge
besar tiap minggu.

---

## Kalau tetap kena konflik

| Konflik di | Cara selesaikan |
|---|---|
| berkas route | pertahankan **kedua** baris `require` |
| `public/build/**` | jangan diselesaikan manual: `git checkout --ours public/build && npm run build` |
| `CareerShell.php` | seharusnya tidak mungkin — berarti ada yang menambah logika di berkas beku. Ambil versi terbaru, lalu pindahkan perubahannya ke berkas yang benar (lihat tabel "Shell sudah dipecah") |
| `config/career_shell.php` | biasanya dua baris `props_tambahan` di ujung → ambil keduanya |
| `evo-theme.css` | biasanya penambahan di ujung berbeda → ambil keduanya |

---

## Setelah `git pull`: kalau sidebar kosong / semua 403

Bukan kodenya rusak — hak aksesmu belum ada di database.

1. Jalankan SQL RBAC (`docs/N_WEB_CAREERS_HakAkses_*.sql`) bila tabelnya belum ada.
2. Beri akses akunmu lewat `/hak-akses` (atau minta yang sudah punya akses).
3. **Logout → login ulang** — paket hak akses disusun saat login.

---

## Komponen bersama yang sudah siap pakai

| Komponen | Gunanya |
|---|---|
| `@career/AdminModal.vue` | modal form. Prop `:busy="saving"` → tombol terkunci + spinner. Tidak tertutup oleh klik latar. |
| `@career/ConfirmModal.vue` | konfirmasi hapus / aksi berbahaya |
| `@career/RefSelect.vue` | dropdown dari master (`type="tipe"`, `"alur"`, `"tes"`, …) |
| `@career/IconPicker.vue` | pemilih ikon Bootstrap |
| `App\Support\Career\KodeUnik` | kode unik dari nama, tanpa terpotong |
| `App\Support\Career\AksesService` | hak akses, kategori diizinkan, mode pemeliharaan |
| `App\Support\CareerShell` | props halaman admin — `CareerShell::props($url, $judul, $extra)` |

**Pola halaman** — tiru salah satu:

- `program-kegiatan/programKegiatan.vue` — kartu + statistik + tab + Filter Panel + paginasi (dipakai juga oleh `pembukaan-program`)
- `master-tipe/masterTipe.vue` — grid kartu sederhana

---

## Antrean (queue)

Satu perintah untuk semuanya — apply, email, export:

```bash
php artisan queue:work
```

Tidak perlu lagi `queue:work webcareers`. Job tidak lagi memaksa koneksinya
sendiri; yang menentukan adalah `QUEUE_CONNECTION`:

| `QUEUE_CONNECTION` | Perilaku |
|---|---|
| `webcareers` *(bawaan)* | tabel `N_WEB_CAREERS_Jobs`, dibaca `queue:work` |
| `cloudtasks` | dikirim ke Cloud Tasks & dibaca lewat `/handle-task` — tanpa worker |
| `sync` | dijalankan langsung dalam request, untuk uji cepat |

Nama queue (`wc-applyform`, `wc-applymail`, …) **hanya** dipasang saat
`cloudtasks`, karena di sana tiap queue adalah sumber daya tersendiri. Untuk
driver database sengaja dibiarkan `default` — kalau dinamai, `queue:work`
tidak akan pernah melihatnya. Aturan ini ada di
`app/Jobs/Career/Concerns/AntreanWebCareers.php`; ikuti saat menambah job baru:

```php
use AntreanWebCareers;

public const QUEUE = 'wc-anu';

public function __construct(...)
{
    $this->aturAntrean(self::QUEUE);
}
```

⚠️ `N_LMS_Jobs` dan `N_LMS_Failed_Jobs` milik modul tetangga di basis data yang
sama. Jangan arahkan antrean Web Careers ke sana.

---

## Catatan teknis singkat

- **Jangan pakai `env()` di luar `config/`** — nilainya jadi `null` begitu
  `php artisan config:cache` dijalankan. Taruh di `config/` lalu baca via `config()`.
- **Mode pemeliharaan**: sakelar per menu di Master Menu. Menu ADMIN tampil di
  dalam shell, menu KANDIDAT tampil penuh layar. SUPERADMIN tetap bisa masuk.
- **Pratinjau halaman error**: `/error-preview/404`, `/error-preview/503`,
  tambah `?shell=1` untuk varian dalam shell (lokal / SUPERADMIN).
