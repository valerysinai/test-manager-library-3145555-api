<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Library\Application\Services\BootstrapAdminService;
use App\Library\Application\Ports\{TokenIssuer, CoverStorage};
use Illuminate\Console\Command;
final class BootstrapAdmin extends Command
{
    protected $signature='library:bootstrap-admin';
    protected $description='Aprovisiona el administrador inicial desde entorno sin mostrar credenciales.';
    public function handle(BootstrapAdminService $service,TokenIssuer $tokens,CoverStorage $covers): int
    {
        try {
            $service->execute((string) config('library.admin_username'),(string) config('library.admin_password'));
        } catch (\Throwable) {
            $this->error('No se pudo aprovisionar el administrador. Revise configuración y migraciones.');
            return self::FAILURE;
        }
        $this->info('Administrador inicial disponible.');
        return self::SUCCESS;
    }
}
