<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DIAGNOSTIK — dijalankan lewat peramban, bukan terminal
|--------------------------------------------------------------------------
|
| Cloud Run tidak menyediakan shell. Tidak ada tempat mengetik
| `php artisan …` di sana, padahal justru dari SANA-lah pemeriksaan harus
| dijalankan: yang perlu diuji adalah jalur keluar milik mesin yang gagal,
| bukan milik laptop yang memeriksanya.
|
| Maka perintahnya dipanggil lewat HTTP dan keluarannya dikirim apa adanya
| sebagai teks. Sama persis dengan yang tampil di terminal, hanya salurannya
| yang berbeda.
|
| ══ PENJAGAAN ═════════════════════════════════════════════════════════════
|
| Keluarannya menyebut alamat server surat dan potongan kunci publiknya. Bukan
| rahasia besar, tapi jelas bukan konsumsi publik — halaman ini memetakan
| permukaan jaringan sistem bagi siapa pun yang membacanya.
|
| Karena itu SUPERADMIN saja, lewat sesi login yang sama dengan panel admin.
| Kunci rahasia TIDAK PERNAH ikut tercetak; perintahnya hanya melaporkan
| "terisi" atau "kosong".
*/
Route::middleware(['career.auth', 'career.role:SUPERADMIN'])
    ->prefix('karir/diagnostik')
    ->name('career.diagnostik.')
    ->group(function () {
        /*
         * Penanda hidup. Dibuka lebih dulu saat halaman /surat tampil kosong:
         * ia menjawab seketika, jadi ia memisahkan "rutenya belum ter-deploy /
         * sesi ditolak" dari "perintahnya jalan tapi mati di tengah".
         */
        Route::get('/ping', fn () => response(
            "DIAGNOSTIK HIDUP\n"
            .'mesin       : '.gethostname()."\n"
            .'waktu       : '.now()->toDateTimeString()."\n"
            .'app_env     : '.config('app.env')."\n"
            .'server surat: '.(config('surat.basis') ?: '(kosong)')."\n"
            .'batas eksekusi PHP : '.(ini_get('max_execution_time') ?: '?')." detik\n",
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        ))->name('ping');

        /*
         * Pemeriksa jalur ke EVO Mail Server — pengganti pemeriksa SMTP yang
         * dulu ada di sini. Web Careers sudah tidak membuka port SMTP dari
         * mana pun; yang menentukan sampai-tidaknya surat sekarang adalah
         * server surat, kunci API, dan izin per-template.
         *
         *   ?kirim=alamat@contoh.com   sekalian kirim surat uji SUNGGUHAN
         *   ?semua=1                   kirim contoh SELURUH template
         *   ?template=hasil-lamaran    kirim satu template itu saja
         *
         * Sengaja lewat query, bukan POST: yang memakainya sedang menelusuri
         * gangguan lewat bilah alamat, dan menuntut borang di tengah itu cuma
         * menambah langkah tanpa menambah keamanan — gerbangnya sudah di sesi.
         */
        Route::get('/surat', function (\Illuminate\Http\Request $request) {
            /*
             * Pemeriksaan ini bisa memakan beberapa detik: server surat
             * mengirim SERENTAK, dan satu jabat tangan SMTP di seberang
             * terukur 3–5 detik. Dengan ?semua=1 ia mengirim lima surat
             * berturut-turut, jadi lamanya berlipat.
             *
             * PHP membunuh skrip yang melewati max_execution_time TANPA menulis
             * apa pun ke badan respons. Halaman kosong tanpa galat — persis
             * gejala yang bikin bingung, karena ia terbaca seperti rute yang
             * tidak ada. Batasnya dilepas di sini saja, sebatas permintaan ini.
             */
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');

            $argumen = [];

            $kirim = trim((string) $request->query('kirim', ''));
            // Divalidasi di sini, bukan diserahkan ke perintahnya: alamat asal
            // ketik akan berakhir sebagai percobaan kirim yang gagal dengan
            // galat yang membingungkan, dan itu justru menambah satu misteri
            // baru di tengah penelusuran misteri lama.
            if ($kirim !== '' && filter_var($kirim, FILTER_VALIDATE_EMAIL)) {
                $argumen['--kirim'] = $kirim;
            }

            // Keduanya hanya berarti bila ?kirim= juga diisi — tanpa alamat
            // tujuan tidak ada satu surat pun yang berangkat.
            if ($request->boolean('semua')) {
                $argumen['--semua'] = true;
            } elseif ($request->query('template')) {
                $argumen['--template'] = (string) $request->query('template');
            }

            $mulai = microtime(true);
            $galat = null;

            try {
                Artisan::call('karir:cek-surat', $argumen);
                $keluaran = Artisan::output();
            } catch (\Throwable $e) {
                // Perintah yang melempar tidak boleh berakhir jadi halaman
                // kosong. Yang sedang menelusuri gangguan justru paling butuh
                // membaca lemparannya.
                $keluaran = Artisan::output();
                $galat = get_class($e).': '.$e->getMessage();
            }

            // Kode warna ANSI dibuang — di peramban ia tampil sebagai sampah
            // "[32m" yang menutupi isi yang mau dibaca.
            $keluaran = preg_replace('/\e\[[0-9;]*m/', '', (string) $keluaran);
            $detik = round(microtime(true) - $mulai, 1);

            return response(
                "PEMERIKSAAN JALUR SURAT\n"
                .'dijalankan dari: '.gethostname()."\n"
                .'waktu          : '.now()->toDateTimeString().' ('.config('app.timezone').")\n"
                .'lama proses    : '.$detik." detik\n"
                .str_repeat('=', 64)."\n"
                .($keluaran !== '' ? $keluaran : "(perintah tidak menghasilkan keluaran apa pun)\n")
                .($galat ? "\n".str_repeat('-', 64)."\nGALAT: ".$galat."\n" : ''),
                200,
                ['Content-Type' => 'text/plain; charset=utf-8']
            );
        })->name('surat');
    });
