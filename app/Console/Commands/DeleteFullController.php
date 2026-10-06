<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DeleteFullController extends Command
{
    /**
     * Kebalikan dari make:fullcontroller — menghapus semua artefak yang dibuatnya.
     *
     * Format:
     *   php artisan delete:fullcontroller {name} {folder} {developer} [--force]
     *
     * Contoh:
     *   php artisan delete:fullcontroller MasterSiklus careers fransDeveloper
     *   → Hapus folder controller Careers/MasterSiklus (Controller + Api)
     *   → Hapus folder routes/careers/MasterSiklus (Web + Api)
     *   → Lepas require modul dari routes/careers/fransDeveloperDevEvo.php
     *   → Jika induk kosong: hapus induk + lepas require-nya dari routes/web.php
     */
    protected $signature = 'delete:fullcontroller {name} {folder} {developer} {--force : Hapus tanpa konfirmasi}';
    protected $description = 'Rollback make:fullcontroller — menghapus controller, routes modul, dan pendaftaran require-nya.';

    public function handle()
    {
        $name = $this->argument('name');
        $folderArg = $this->argument('folder');
        $developer = $this->argument('developer');

        $folderStudly = Str::studly($folderArg);
        $routesFolder = $folderArg;

        if (
            !$this->option('force') &&
            !$this->confirm("Hapus modul '{$name}' di folder '{$routesFolder}' (developer: {$developer})?", true)
        ) {
            $this->line('Dibatalkan.');
            return 0;
        }

        // === 1. Hapus folder controller modul: Http/Controllers/{Folder}/{name} ===
        $controllerParent = app_path("Http/Controllers/{$folderStudly}");
        $controllerFolder = $controllerParent . "/{$name}";
        if (is_dir($controllerFolder)) {
            File::deleteDirectory($controllerFolder);
            $this->info("🗑️  Folder controller dihapus: Http/Controllers/{$folderStudly}/{$name}");
        } else {
            $this->line("ℹ️ Folder controller tidak ditemukan, dilewati.");
        }

        // === 2. Hapus folder routes modul: routes/{folder}/{name} ===
        $routesFolderPath = base_path("routes/{$routesFolder}");
        $moduleFolderPath = $routesFolderPath . "/{$name}";
        if (is_dir($moduleFolderPath)) {
            File::deleteDirectory($moduleFolderPath);
            $this->info("🗑️  Folder routes modul dihapus: routes/{$routesFolder}/{$name}");
        } else {
            $this->line("ℹ️ Folder routes modul tidak ditemukan, dilewati.");
        }

        // === 3. Lepas require modul dari file induk developer ===
        $routeFileName = "{$developer}DevEvo.php";
        $devRouteFile = $routesFolderPath . "/{$routeFileName}";

        $requireWeb = "require base_path('routes/{$routesFolder}/{$name}/{$name}Web.php');";
        $requireApi = "require base_path('routes/{$routesFolder}/{$name}/{$name}Api.php');";

        $indukDihapus = false;

        if (file_exists($devRouteFile)) {
            $lines = preg_split('/\R/', File::get($devRouteFile));
            $lines = array_values(array_filter($lines, function ($line) use ($requireWeb, $requireApi) {
                $trim = trim($line);
                return $trim !== $requireWeb && $trim !== $requireApi;
            }));
            $newContent = rtrim(implode("\n", $lines)) . "\n";
            File::put($devRouteFile, $newContent);
            $this->info("✅ Require modul dilepas dari routes/{$routesFolder}/{$routeFileName}");

            // Jika sudah tidak ada require modul lain → hapus file induk.
            if (!Str::contains($newContent, "require base_path('routes/{$routesFolder}/")) {
                File::delete($devRouteFile);
                $indukDihapus = true;
                $this->info("🗑️  File induk kosong, dihapus: routes/{$routesFolder}/{$routeFileName}");
            }
        } else {
            $this->line("ℹ️ File induk tidak ditemukan, dilewati.");
        }

        // === 4. Jika induk dihapus, lepas require-nya dari routes/web.php ===
        if ($indukDihapus) {
            $webPhp = base_path('routes/web.php');
            $requireDev = "require __DIR__ . '/{$routesFolder}/{$routeFileName}';";
            if (file_exists($webPhp)) {
                $lines = preg_split('/\R/', File::get($webPhp));
                $lines = array_values(array_filter($lines, fn ($line) => trim($line) !== $requireDev));
                File::put($webPhp, rtrim(implode("\n", $lines)) . "\n");
                $this->info('✅ Require file induk dilepas dari routes/web.php');
            }
        }

        // === 5. Bersihkan folder yang sudah kosong ===
        $this->hapusJikaKosong($routesFolderPath, "routes/{$routesFolder}");
        $this->hapusJikaKosong($controllerParent, "app/Http/Controllers/{$folderStudly}");

        $this->info('🎉 Selesai! Rollback modul berhasil.');
        return 0;
    }

    /** Hapus folder hanya jika benar-benar kosong. */
    private function hapusJikaKosong(string $path, string $label): void
    {
        if (is_dir($path) && count(File::allFiles($path)) === 0 && count(File::directories($path)) === 0) {
            File::deleteDirectory($path);
            $this->info("🗑️  Folder kosong dihapus: {$label}");
        }
    }
}
