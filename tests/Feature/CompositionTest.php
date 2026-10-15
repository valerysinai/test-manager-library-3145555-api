<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Library\Application\Ports as Ports;
use App\Library\Adapters\Outbound\Persistence as Pg;
use App\Library\Adapters\Outbound\Security as Security;
use App\Library\Adapters\Outbound\Clock\ConfiguredClock;
use App\Library\Adapters\Outbound\Storage\DiskCoverStorage;
use Tests\TestCase;
final class CompositionTest extends TestCase
{
    public function test_all_production_ports_and_services_resolve_without_opening_the_database(): void
    {
        $directory=sys_get_temp_dir().'/library-composition-'.bin2hex(random_bytes(10));
        config(['library.jwt_secret'=>bin2hex(random_bytes(32)),'library.covers_dir'=>$directory,
            'library.clock_mode'=>'fixed','library.clock_fixed_at'=>'2026-10-14T12:00:00Z']);
        try {
            foreach([
                Ports\BookRepository::class=>Pg\PgBookRepository::class,
                Ports\CategoryRepository::class=>Pg\PgCategoryRepository::class,
                Ports\MemberRepository::class=>Pg\PgMemberRepository::class,
                Ports\UserRepository::class=>Pg\PgUserRepository::class,
                Ports\LoanRepository::class=>Pg\PgLoanRepository::class,
                Ports\ReportReader::class=>Pg\PgReportReader::class,
                Ports\UnitOfWork::class=>Pg\PgUnitOfWork::class,
                Ports\PasswordHasher::class=>Security\ArgonPasswordHasher::class,
                Ports\TokenIssuer::class=>Security\HmacJwt::class,
                Ports\TokenVerifier::class=>Security\HmacJwt::class,
                Ports\UuidGenerator::class=>Security\RandomUuid::class,
                Ports\Clock::class=>ConfiguredClock::class,
                Ports\CoverStorage::class=>DiskCoverStorage::class,
            ] as $port=>$implementation) {
                $instance=$this->app->make($port);
                self::assertInstanceOf($implementation,$instance);
                self::assertSame($instance,$this->app->make($port));
            }
            self::assertSame($this->app->make(Ports\TokenIssuer::class),$this->app->make(Ports\TokenVerifier::class));
            foreach(glob(app_path('Library/Application/Services/*Service.php')) as $file) {
                $class='App\\Library\\Application\\Services\\'.basename($file,'.php');
                self::assertInstanceOf($class,$this->app->make($class));
            }
            $session=$this->app->make(Pg\PgSession::class);
            self::assertNull((new \ReflectionProperty($session,'connection'))->getValue($session));
        } finally { if(is_dir($directory)) rmdir($directory); }
    }
}
