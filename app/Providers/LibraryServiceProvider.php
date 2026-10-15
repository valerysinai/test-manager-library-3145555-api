<?php
declare(strict_types=1);
namespace App\Providers;
use App\Library\Application\Ports as Ports;
use App\Library\Adapters\Outbound\Persistence as Pg;
use App\Library\Adapters\Outbound\Security as Security;
use App\Library\Adapters\Outbound\Clock\ConfiguredClock;
use App\Library\Adapters\Outbound\Storage\DiskCoverStorage;
use Illuminate\Support\ServiceProvider;
final class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Pg\PgSession::class, static fn($app) =>
            new Pg\PgSession(static fn()=> $app['db']->connection()->getPdo()));
        $this->app->singleton(Ports\UnitOfWork::class, static fn($app)=> new Pg\PgUnitOfWork($app->make(Pg\PgSession::class)));
        foreach ([
            Ports\BookRepository::class=>Pg\PgBookRepository::class,
            Ports\CategoryRepository::class=>Pg\PgCategoryRepository::class,
            Ports\MemberRepository::class=>Pg\PgMemberRepository::class,
            Ports\UserRepository::class=>Pg\PgUserRepository::class,
            Ports\LoanRepository::class=>Pg\PgLoanRepository::class,
            Ports\ReportReader::class=>Pg\PgReportReader::class,
        ] as $port=>$adapter) {
            $this->app->singleton($port, static fn($app)=> new $adapter($app->make(Pg\PgSession::class)));
        }
        $this->app->singleton(Ports\UuidGenerator::class, Security\RandomUuid::class);
        $this->app->singleton(Ports\PasswordHasher::class, Security\ArgonPasswordHasher::class);
        $this->app->singleton(Ports\Clock::class, static fn($app)=> new ConfiguredClock(
            $app['config']->get('library.clock_mode'),$app['config']->get('library.clock_fixed_at'),$app->environment()));
        $this->app->singleton(Security\HmacJwt::class, static fn($app)=> new Security\HmacJwt(
            $app['config']->get('library.jwt_secret'),$app->make(Ports\Clock::class)));
        $this->app->alias(Security\HmacJwt::class,Ports\TokenIssuer::class);
        $this->app->alias(Security\HmacJwt::class,Ports\TokenVerifier::class);
        $this->app->singleton(Ports\CoverStorage::class, static function($app) {
            $directory=$app['config']->get('library.covers_dir');
            if (!str_starts_with($directory,DIRECTORY_SEPARATOR) && preg_match('/\A[A-Za-z]:[\\\\\/]/',$directory)!==1) {
                $directory=base_path($directory);
            }
            return new DiskCoverStorage($directory,$app->make(Ports\UuidGenerator::class),public_path());
        });
    }
}
