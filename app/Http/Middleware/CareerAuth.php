<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * WEB CAREER — wajib login.
 *
 * Sebelum ini SELURUH halaman admin terbuka tanpa sesi sama sekali: siapa pun
 * yang tahu URL-nya bisa membuka /karir dan seluruh master data. Middleware ini
 * menutup celah itu.
 *
 * Sengaja MEMERIKSA ULANG KE DATABASE tiap permintaan, tidak cuma percaya isi
 * sesi. Alasannya: akun bisa dinonaktifkan atau masa berlakunya habis SETELAH
 * kandidat login. Kalau hanya membaca sesi, orang yang sudah dinonaktifkan
 * tetap bisa berkeliling sampai dia sendiri menekan logout. Biayanya satu
 * lookup berindeks — murah dibanding risikonya.
 */
class CareerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = session('career_auth');

        if (! $auth || empty($auth['id'])) {
            return $this->tolak($request, 'Silakan masuk terlebih dahulu.');
        }

        $row = DB::table('N_WEB_CAREERS_Users')
            ->where('Id_Users', $auth['id'])
            ->select('Id_Users', 'Role', 'Status', 'Valid_Until', 'Pwd_Changed_At')
            ->first();

        if (! $row) {
            return $this->keluar($request, 'Akun tidak ditemukan.');
        }

        if ($row->Status !== 'AKTIF') {
            return $this->keluar($request, 'Akun Anda dinonaktifkan. Hubungi tim rekrutmen EVO Group.');
        }

        if ($row->Valid_Until !== null
            && Carbon::parse($row->Valid_Until)->startOfDay()->lessThan(Carbon::now()->startOfDay())) {
            return $this->keluar($request, 'Masa berlaku akun Anda telah berakhir pada '
                . Carbon::parse($row->Valid_Until)->format('d M Y') . '.');
        }

        // Kata sandi diganti SETELAH sesi ini dibuat → sesi lama tidak lagi sah.
        // `Pwd_Changed_At` di-bump saat reset; sesi menyimpan snapshot-nya
        // (pwd_epoch) waktu login. NULL berarti akun belum pernah reset — jangan
        // paksa keluar (mencegah logout massal saat kolom baru ditambahkan).
        // Bandingkan sebagai string agar aman dari perbedaan tipe Carbon/DateTime.
        if ($row->Pwd_Changed_At !== null && (string) ($auth['pwd_epoch'] ?? '') !== (string) $row->Pwd_Changed_At) {
            return $this->keluar($request, 'Kata sandi akun kamu baru saja diubah. Silakan masuk kembali.');
        }

        // Peran diambil ULANG dari DB, bukan dari sesi. Kalau admin menurunkan
        // peran seseorang, perubahannya berlaku saat itu juga — bukan menunggu
        // sesi lamanya berakhir.
        if (($auth['role'] ?? null) !== $row->Role) {
            $auth['role'] = $row->Role;
            session(['career_auth' => $auth]);
        }

        return $next($request);
    }

    /** Belum login — arahkan ke halaman masuk (atau 401 untuk permintaan JSON). */
    private function tolak(Request $request, string $pesan): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'status' => 401, 'message' => $pesan], 401);
        }

        return redirect($this->alamatMasuk($request))->with('pesan', $pesan);
    }

    /** Sesi tidak lagi sah — bersihkan lalu arahkan ke halaman masuk. */
    private function keluar(Request $request, string $pesan): Response
    {
        session()->forget(['career_auth', 'career_akses']);

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'status' => 401, 'message' => $pesan], 401);
        }

        return redirect($this->alamatMasuk($request))->with('pesan', $pesan);
    }

    /**
     * Halaman masuk, DENGAN MEMBAWA tujuan yang tadi hendak dibuka.
     *
     * KENAPA PENTING
     * Kasus paling nyata: kandidat pergi mengerjakan tes CAT selama satu-dua
     * jam, lalu dipulangkan ke halaman lamarannya. Sesi Web Careers berumur
     * SESSION_LIFETIME menit (bawaan 120) — pas di ambang durasi ujian. Begitu
     * sesinya lewat, kandidat mendarat di halaman masuk dan tujuannya HILANG:
     * sesudah masuk mereka dilempar ke portal umum, bukan ke lamaran yang
     * barusan mereka tinggalkan. Perjalanan pulang yang susah payah dirancang
     * itu putus tepat di langkah terakhir.
     *
     * Halaman masuk sudah bisa membaca `?redirect=` (lihat Career/Auth.vue) —
     * yang belum ada hanyalah pihak yang mengisinya.
     *
     * HANYA PATH + QUERY yang dibawa, tidak pernah host. Tujuan yang menerima
     * alamat penuh dari luar adalah pintu open-redirect: penyerang tinggal
     * mengirim /karir?redirect=https://situs-palsu dan halaman masuk kita
     * sendiri yang mengantar korbannya ke sana.
     */
    private function alamatMasuk(Request $request): string
    {
        // Hanya permintaan halaman (GET) yang punya "tujuan" bermakna. POST
        // /PATCH tidak bisa diulang begitu saja sesudah masuk.
        if (! $request->isMethod('GET')) {
            return '/login';
        }

        // Diperiksa MENTAH, sebelum dinormalkan. Merapikan dulu baru memeriksa
        // membuat gerbangnya tak pernah berbunyi: `ltrim($uri, '/')` mengubah
        // `//situs-palsu` jadi `situs-palsu`, sehingga pemeriksaan `//` di
        // bawahnya tak akan pernah menemukan apa pun untuk ditolak.
        $tujuan = $request->getRequestUri();

        // `//situs-luar` dibaca peramban sebagai URL berprotokol-relatif —
        // tetap keluar dari domain kita walau diawali garis miring.
        if (! str_starts_with($tujuan, '/') || str_starts_with($tujuan, '//')) {
            return '/login';
        }

        if ($tujuan === '/login' || str_starts_with($tujuan, '/login?')) {
            return '/login';
        }

        return '/login?redirect=' . urlencode($tujuan);
    }
}
