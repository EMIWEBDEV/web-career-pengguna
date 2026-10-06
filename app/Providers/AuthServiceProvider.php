<?php

namespace App\Providers;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //akses khusus supervisor
        Gate::define('akses-svp', function(User $user){
            return $user->isSupervisor();
        });

        // jika kasus akses jabatan tertentu
        Gate::define('akses-spesial', function(User $user, string $page){
            return $user->memilikiJabatan($page);
        });

        Gate::define('open-kpi', function(User $user){
            return $user->openKPI();
        });

        // Akses recovery cuti (admin HC). Sejajar dengan check.jabatan:adminHCKuotaManagementPage
        // tapi dipakai untuk defense-in-depth di controller via Gate::authorize().
        Gate::define('manageKuotaRecovery', function (User $user) {
            return $user->memilikiJabatan('adminHCKuotaManagementPage');
        });
    }



}
