<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeFullController extends Command
{
    /**
     * Format:
     *   php artisan make:fullcontroller {name} {folder} {developer}
     *
     * Contoh:
     *   php artisan make:fullcontroller MasterSiklus careers fransDeveloper
     *   → Controller : app/Http/Controllers/Careers/MasterSiklus/MasterSiklusController.php
     *                  app/Http/Controllers/Careers/MasterSiklus/MasterSiklusApi.php
     *   → Routes     : routes/careers/MasterSiklus/MasterSiklusWeb.php
     *                  routes/careers/MasterSiklus/MasterSiklusApi.php
     *   → Induk dev  : routes/careers/fransDeveloperDevEvo.php  (auto require ke routes/web.php)
     */
    protected $signature = 'make:fullcontroller {name} {folder} {developer}';
    protected $description = 'Membuat controller (web + api) & routes per modul di dalam folder tertentu, lalu mendaftarkan require-nya ke file induk developer dan ke routes/web.php.';

    public function handle()
    {
        $name = $this->argument('name');
        $folderArg = $this->argument('folder');
        $developer = $this->argument('developer');

        // === Validasi nama controller (harus diawali huruf besar) ===
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->error('Nama controller harus diawali huruf besar & hanya huruf/angka. Contoh: MasterSiklus');
            return 1;
        }

        // === Validasi folder & developer (alfanumerik) ===
        if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $folderArg)) {
            $this->error('Nama folder hanya boleh huruf/angka dan diawali huruf. Contoh: careers');
            return 1;
        }
        if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $developer)) {
            $this->error('Nama developer hanya boleh huruf/angka dan diawali huruf. Contoh: fransDeveloper');
            return 1;
        }

        // Folder untuk namespace controller di-StudlyCase (PSR-4), folder routes memakai versi apa adanya.
        $folderStudly = Str::studly($folderArg);   // careers -> Careers
        $routesFolder = $folderArg;                 // routes/careers
        $lowerName = Str::kebab($name);             // MasterSiklus -> master-siklus (untuk uri route)

        // === 1. Buat controller (web resource + api) di subfolder per modul ===
        // Folder: app/Http/Controllers/{Folder}/{name}
        $controllerFolder = app_path("Http/Controllers/{$folderStudly}/{$name}");
        if (!is_dir($controllerFolder)) {
            mkdir($controllerFolder, 0755, true);
        }

        $this->call('make:controller', [
            'name' => "{$folderStudly}/{$name}/{$name}Controller",
            '-r' => true,
        ]);
        $this->call('make:controller', [
            'name' => "{$folderStudly}/{$name}/{$name}Api",
            '--api' => true,
        ]);

        $this->info("Controllers berhasil dibuat di {$controllerFolder}");

        // === 2. Buat folder & file routes per modul ===
        // Folder induk: routes/{folder}   |   Folder modul: routes/{folder}/{name}
        $routesFolderPath = base_path("routes/{$routesFolder}");
        $moduleFolderPath = $routesFolderPath . "/{$name}";
        if (!is_dir($moduleFolderPath)) {
            mkdir($moduleFolderPath, 0755, true);
        }

        $webRoutesFile = $moduleFolderPath . "/{$name}Web.php";
        $apiRoutesFile = $moduleFolderPath . "/{$name}Api.php";

        // Web routes (resource)
        if (!file_exists($webRoutesFile)) {
            File::put(
                $webRoutesFile,
                "<?php\n\n" .
                    "use Illuminate\\Support\\Facades\\Route;\n" .
                    "use App\\Http\\Controllers\\{$folderStudly}\\{$name}\\{$name}Controller;\n\n" .
                    "Route::resource('{$lowerName}', {$name}Controller::class);\n",
            );
        }

        // API routes (apiResource)
        if (!file_exists($apiRoutesFile)) {
            File::put(
                $apiRoutesFile,
                "<?php\n\n" .
                    "use Illuminate\\Support\\Facades\\Route;\n" .
                    "use App\\Http\\Controllers\\{$folderStudly}\\{$name}\\{$name}Api;\n\n" .
                    "Route::prefix('api')->group(function () {\n" .
                    "    Route::apiResource('{$lowerName}', {$name}Api::class)->names('api.{$lowerName}');\n" .
                    "});\n",
            );
        }

        $this->info("File routes modul berhasil dibuat di {$moduleFolderPath}");

        // === 3. File induk developer di dalam folder yang sama ===
        $routeFileName = "{$developer}DevEvo.php";
        $devRouteFile = $routesFolderPath . "/{$routeFileName}";

        if (!file_exists($devRouteFile)) {
            File::put($devRouteFile, "<?php\n\n// Routes induk milik {$developer} (folder: {$routesFolder})\n");
            $this->info("File induk developer dibuat: routes/{$routesFolder}/{$routeFileName}");
        }

        // Inject require modul ke file induk developer (cek agar tidak dobel)
        $requireWeb = "require base_path('routes/{$routesFolder}/{$name}/{$name}Web.php');";
        $requireApi = "require base_path('routes/{$routesFolder}/{$name}/{$name}Api.php');";

        $devRouteContent = File::get($devRouteFile);
        $appended = false;

        if (strpos($devRouteContent, $requireWeb) === false) {
            File::append($devRouteFile, "\n" . $requireWeb);
            $appended = true;
        }
        if (strpos($devRouteContent, $requireApi) === false) {
            File::append($devRouteFile, "\n" . $requireApi);
            $appended = true;
        }

        if ($appended) {
            $this->info("✅ Require modul ditambahkan ke routes/{$routesFolder}/{$routeFileName}");
        } else {
            $this->line("ℹ️ Require modul sudah ada di routes/{$routesFolder}/{$routeFileName}, dilewati.");
        }

        // === 4. Daftarkan file induk developer ke routes/web.php agar ter-load ===
        $webPhp = base_path('routes/web.php');
        $requireDev = "require __DIR__ . '/{$routesFolder}/{$routeFileName}';";

        if (file_exists($webPhp)) {
            $webContent = File::get($webPhp);
            if (strpos($webContent, $requireDev) === false) {
                File::append($webPhp, "\n" . $requireDev . "\n");
                $this->info("✅ File induk didaftarkan ke routes/web.php");
            } else {
                $this->line('ℹ️ File induk sudah terdaftar di routes/web.php, dilewati.');
            }
        }

        $this->info('🎉 Selesai! Controller, routes, dan pendaftaran require berhasil dibuat.');
        return 0;
    }
}
