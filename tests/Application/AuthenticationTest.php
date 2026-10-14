<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Library\Application\Services\CreateLibrarianService;
use App\Library\Application\Services\LoginService;
use App\Library\Domain\Errors\ForbiddenRole;
use App\Library\Domain\Errors\InvalidCredentials;
use App\Library\Domain\Errors\UsernameTaken;
use App\Library\Domain\Errors\ValidationError;
use App\Library\Domain\User;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\DomainFixtures as F;
use Tests\Fakes\FakeClock;
use Tests\Fakes\FakePasswordHasher;
use Tests\Fakes\FakeTokenIssuer;
use Tests\Fakes\InMemoryUserRepository;

final class AuthenticationTest extends TestCase
{
    private InMemoryUserRepository $users;
    private FakePasswordHasher $passwords;
    private FakeTokenIssuer $tokens;
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository;
        $this->passwords = new FakePasswordHasher;
        $this->tokens = new FakeTokenIssuer;
        $this->clock = new FakeClock(new DateTimeImmutable('2026-10-14T07:00:00-05:00'));
    }

    private function user(string $role, string $password, int $id = 100, string $username = 'Ana'): User
    {
        return new User(F::id($id), $username, $this->passwords->hash($password), $role);
    }

    private function login(): LoginService
    {
        return new LoginService($this->users, $this->passwords, $this->tokens, $this->clock);
    }

    #[DataProvider('authenticatedRoles')]
    public function test_valid_login_issues_eight_hours_with_only_identity_role_and_utc_times(string $role): void
    {
        $password = bin2hex(random_bytes(20));
        $user = $this->user($role, $password);
        $this->users->add($user);
        $result = $this->login()->execute(' ANA ', $password);
        self::assertSame(['access_token' => 'fake-token-1', 'token_type' => 'Bearer',
            'expires_in' => 28800, 'role' => $role], json_decode(json_encode($result, JSON_THROW_ON_ERROR), true));
        self::assertSame(['subject', 'role', 'issuedAt', 'expiresAt'], array_keys($this->tokens->issued[0]));
        $claims = $this->tokens->issued[0];
        self::assertSame($user->id, $claims['subject']);
        self::assertSame($role, $claims['role']);
        self::assertSame('UTC', $claims['issuedAt']->getTimezone()->getName());
        self::assertSame('UTC', $claims['expiresAt']->getTimezone()->getName());
        self::assertSame(28800, $claims['expiresAt']->getTimestamp() - $claims['issuedAt']->getTimestamp());
        self::assertSame('2026-10-14T20:00:00+00:00', $claims['expiresAt']->format('c'));
        self::assertSame('[REDACTED]', $result->__debugInfo()['access_token']);
    }

    public static function authenticatedRoles(): array
    {
        return [['admin'], ['librarian']];
    }

    public function test_missing_user_and_wrong_password_have_the_same_error_and_neither_issues_a_token(): void
    {
        $password = bin2hex(random_bytes(20));
        $this->users->add($this->user('librarian', $password));
        $messages = [];
        foreach (['ana', 'missing-user'] as $username) {
            try {
                $this->login()->execute($username, bin2hex(random_bytes(20)));
                self::fail('Debió rechazar las credenciales.');
            } catch (InvalidCredentials $error) {
                $messages[] = $error->getMessage();
            }
        }
        self::assertSame(['Las credenciales no son válidas.', 'Las credenciales no son válidas.'], $messages);
        self::assertSame(2, $this->passwords->verifyCount);
        self::assertSame(1, $this->passwords->missingHashCount);
        self::assertSame([], $this->tokens->issued);
        self::assertCount(1, $this->users->users);
    }

    public function test_create_then_login_preserves_password_spaces_and_normalizes_unicode_username(): void
    {
        $actor = $this->user('admin', bin2hex(random_bytes(20)), 200, 'Administrador');
        $password = ' '.bin2hex(random_bytes(20)).' ';
        $created = (new CreateLibrarianService($this->users, $this->passwords))
            ->execute($actor, F::id(101), ' ÁNA ', $password, 'librarian');
        self::assertSame(['id' => F::id(101), 'username' => 'ána', 'role' => 'librarian'], $created->jsonSerialize());
        self::assertSame(['id', 'username', 'role'], array_keys(get_object_vars($created)));
        $stored = $this->users->users[F::id(101)];
        self::assertNotSame($password, $stored->passwordHash());
        self::assertStringNotContainsString($password, json_encode($created, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($stored->passwordHash(), json_encode($created, JSON_THROW_ON_ERROR));
        self::assertSame('librarian', $this->login()->execute(' ÁNA ', $password)->jsonSerialize()['role']);
        $this->expectException(InvalidCredentials::class);
        $this->login()->execute('ána', trim($password));
    }

    public function test_password_has_no_extra_complexity_or_trimming_policy(): void
    {
        $actor = $this->user('admin', bin2hex(random_bytes(20)), 200);
        (new CreateLibrarianService($this->users, $this->passwords))
            ->execute($actor, F::id(101), 'Lector', ' ', 'librarian');
        self::assertSame('librarian', $this->login()->execute('lector', ' ')->jsonSerialize()['role']);
    }

    public function test_librarian_cannot_create_users_and_authorization_precedes_lookup_and_validation(): void
    {
        $actor = $this->user('librarian', bin2hex(random_bytes(20)));
        $hashCount = $this->passwords->hashCount;
        try {
            (new CreateLibrarianService($this->users, $this->passwords))->execute($actor, 'invalid', '', '', 'admin');
            self::fail('Debió rechazar el rol del actor.');
        } catch (ForbiddenRole) {
            self::assertSame(0, $this->users->lookupCount);
            self::assertSame($hashCount, $this->passwords->hashCount);
            self::assertSame([], $this->users->users);
        }
    }

    #[DataProvider('invalidCreationRoles')]
    public function test_admin_cannot_create_an_admin_or_any_other_requested_role(string $role): void
    {
        $actor = $this->user('admin', bin2hex(random_bytes(20)));
        try {
            (new CreateLibrarianService($this->users, $this->passwords))
                ->execute($actor, F::id(101), 'Bibliotecario', bin2hex(random_bytes(20)), $role);
            self::fail('Debió rechazar el rol solicitado.');
        } catch (ValidationError $error) {
            self::assertSame('role', $error->field);
            self::assertSame([], $this->users->users);
            self::assertSame(0, $this->users->lookupCount);
        }
    }

    public static function invalidCreationRoles(): array
    {
        return [['admin'], ['reader'], ['LIBRARIAN'], [' librarian '], ['']];
    }

    public function test_normalized_duplicate_username_does_not_overwrite_or_hash_again(): void
    {
        $actor = $this->user('admin', bin2hex(random_bytes(20)), 200);
        $create = new CreateLibrarianService($this->users, $this->passwords);
        $first = $create->execute($actor, F::id(101), ' ÁNA ', bin2hex(random_bytes(20)), 'librarian');
        $hashCount = $this->passwords->hashCount;
        try {
            $create->execute($actor, F::id(102), 'Ána', bin2hex(random_bytes(20)), 'librarian');
            self::fail('Debió rechazar el nombre repetido.');
        } catch (UsernameTaken) {
            self::assertCount(1, $this->users->users);
            self::assertSame($first->id, $this->users->findByUsername('ána')->id);
            self::assertSame($hashCount, $this->passwords->hashCount);
        }
    }

    public function test_repository_conflict_after_precheck_is_preserved(): void
    {
        $actor = $this->user('admin', bin2hex(random_bytes(20)), 200);
        $this->users->conflictOnAdd = true;
        try {
            (new CreateLibrarianService($this->users, $this->passwords))
                ->execute($actor, F::id(101), 'Ana', bin2hex(random_bytes(20)), 'librarian');
            self::fail('Debió conservar el conflicto del repositorio.');
        } catch (UsernameTaken) {
            self::assertSame([], $this->users->users);
        }
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_login_inputs_do_not_reach_security_or_repository(string $username, string $password, string $field): void
    {
        try {
            $this->login()->execute($username, $password);
            self::fail('Debió rechazar la entrada inválida.');
        } catch (ValidationError $error) {
            self::assertSame($field, $error->field);
            self::assertSame(0, $this->users->lookupCount);
            self::assertSame(0, $this->passwords->verifyCount);
        }
    }

    public static function invalidInputs(): array
    {
        return [[' ', ' ', 'username'], [str_repeat('a', 121), ' ', 'username'], ['ana', '', 'password']];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_creation_inputs_do_not_hash_or_persist(string $username, string $password, string $field): void
    {
        $actor = $this->user('admin', bin2hex(random_bytes(20)));
        $hashCount = $this->passwords->hashCount;
        try {
            (new CreateLibrarianService($this->users, $this->passwords))
                ->execute($actor, F::id(101), $username, $password, 'librarian');
            self::fail('Debió rechazar la entrada inválida.');
        } catch (ValidationError $error) {
            self::assertSame($field, $error->field);
            self::assertSame($hashCount, $this->passwords->hashCount);
            self::assertSame([], $this->users->users);
        }
    }

    public function test_new_login_uses_the_current_injected_time(): void
    {
        $password = bin2hex(random_bytes(20));
        $this->users->add($this->user('admin', $password));
        $service = $this->login();
        $service->execute('ana', $password);
        $this->clock->set(new DateTimeImmutable('2026-10-15T12:00:00Z'));
        $service->execute('ana', $password);
        self::assertSame('2026-10-15T12:00:00+00:00', $this->tokens->issued[1]['issuedAt']->format('c'));
        self::assertSame('2026-10-15T20:00:00+00:00', $this->tokens->issued[1]['expiresAt']->format('c'));
    }

    public function test_hash_failure_never_persists_a_user(): void
    {
        $actor = $this->user('admin', bin2hex(random_bytes(20)));
        $this->passwords->failOnHash = true;
        try {
            (new CreateLibrarianService($this->users, $this->passwords))
                ->execute($actor, F::id(101), 'Ana', bin2hex(random_bytes(20)), 'librarian');
            self::fail('Debió propagar el fallo de hash.');
        } catch (\RuntimeException $error) {
            self::assertSame('No se pudo generar el hash.', $error->getMessage());
            self::assertSame([], $this->users->users);
        }
    }

    public function test_empty_token_from_a_broken_adapter_cannot_be_a_successful_login(): void
    {
        $password = bin2hex(random_bytes(20));
        $this->users->add($this->user('admin', $password));
        $this->tokens->returnEmpty = true;
        $this->expectException(\InvalidArgumentException::class);
        $this->login()->execute('ana', $password);
    }

    public function test_token_issuer_failure_is_not_misrepresented_as_invalid_credentials(): void
    {
        $password = bin2hex(random_bytes(20));
        $this->users->add($this->user('admin', $password));
        $this->tokens->failOnIssue = true;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se pudo emitir el token.');
        $this->login()->execute('ana', $password);
    }
}
