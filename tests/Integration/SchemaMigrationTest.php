<?php

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SchemaMigrationTest extends TestCase
{
    private bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Validar el destino real antes de cualquier DDL; una URL puede sobrescribir DB_DATABASE.
        $server = DB::selectOne("SELECT current_database() AS name, current_setting('server_version_num')::integer AS version");
        $this->assertSame('library_test', $server->name, 'Las pruebas solo admiten una base desechable library_test.');
        $this->assertGreaterThanOrEqual(160000, $server->version);
        $this->assertLessThan(170000, $server->version);
        $this->assertSame([], DB::select(<<<'SQL'
            SELECT table_schema, table_name FROM information_schema.tables
            WHERE table_type = 'BASE TABLE'
              AND table_schema NOT IN ('pg_catalog', 'information_schema')
              AND table_schema NOT LIKE 'pg_%'
              AND NOT (table_schema = 'public' AND table_name = 'migrations')
            SQL), 'La base de pruebas debe estar vacía antes de aplicar las migraciones.');

        if (DB::selectOne("SELECT to_regclass('public.migrations') AS name")->name !== null) {
            $this->assertSame(0, DB::table('public.migrations')->count());
        }

        $this->assertSame(0, Artisan::call('migrate', ['--force' => true, '--path' => 'database/migrations/2026_10_14_000001_create_library_schema.php']));
        $this->migrated = true;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->migrated) {
                $this->assertSame(0, Artisan::call('migrate:rollback', ['--force' => true, '--path' => 'database/migrations/2026_10_14_000001_create_library_schema.php']));
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_physical_schema_matches_the_document_without_defaults_or_extra_columns(): void
    {
        $expected = [
            'book' => [
                'id' => ['uuid', null, false],
                'title' => ['character varying', 250, false],
                'author' => ['character varying', 200, false],
                'isbn' => ['character', 13, false],
                'category_id' => ['uuid', null, false],
                'total_copies' => ['integer', null, false],
                'available_copies' => ['integer', null, false],
                'cover_key' => ['character varying', 512, true],
                'deleted_at' => ['timestamp with time zone', null, true],
            ],
            'category' => ['id' => ['uuid', null, false], 'name' => ['character varying', 120, false]],
            'loan' => [
                'id' => ['uuid', null, false],
                'member_id' => ['uuid', null, false],
                'loaned_by_user_id' => ['uuid', null, false],
                'loaned_at' => ['timestamp with time zone', null, false],
            ],
            'loan_item' => [
                'id' => ['uuid', null, false],
                'loan_id' => ['uuid', null, false],
                'book_id' => ['uuid', null, false],
                'book_title' => ['character varying', 250, false],
                'isbn' => ['character', 13, false],
                'category_name' => ['character varying', 120, false],
                'due_at' => ['timestamp with time zone', null, false],
                'returned_at' => ['timestamp with time zone', null, true],
            ],
            'member' => [
                'id' => ['uuid', null, false],
                'full_name' => ['character varying', 200, false],
                'document_number' => ['character varying', 30, false],
            ],
            'user' => [
                'id' => ['uuid', null, false],
                'username' => ['character varying', 120, false],
                'password_hash' => ['character varying', 512, false],
                'role' => ['character varying', 40, false],
            ],
        ];

        $actual = [];
        foreach (DB::select(<<<'SQL'
            SELECT table_name, column_name, data_type, character_maximum_length, is_nullable, column_default
            FROM information_schema.columns WHERE table_schema = 'library'
            ORDER BY table_name, ordinal_position
            SQL) as $column) {
            $this->assertNull($column->column_default, $column->table_name.'.'.$column->column_name);
            $actual[$column->table_name][$column->column_name] = [
                $column->data_type, $column->character_maximum_length, $column->is_nullable === 'YES',
            ];
        }

        $this->assertSame($expected, $actual);
        $this->assertSame('UTC', DB::selectOne("SELECT current_setting('TimeZone') AS timezone")->timezone);
        $this->assertNotNull(DB::selectOne("SELECT to_regclass('public.migrations') AS name")->name);
    }

    public function test_categories_have_the_documented_identifiers_and_no_admin_is_seeded(): void
    {
        $this->assertSame([
            '11111111-1111-4111-8111-111111111111' => 'Novela',
            '22222222-2222-4222-8222-222222222222' => 'Ciencia',
            '33333333-3333-4333-8333-333333333333' => 'Tecnología',
            '44444444-4444-4444-8444-444444444444' => 'Historia',
            '55555555-5555-4555-8555-555555555555' => 'Infantil',
        ], DB::table('library.category')->orderBy('id')->pluck('name', 'id')->all());
        foreach (['book', 'member', 'user', 'loan', 'loan_item'] as $table) {
            $this->assertSame(0, DB::table('library.'.$table)->count());
        }
    }

    public function test_foreign_keys_preserve_history_and_use_the_documented_actions(): void
    {
        $actual = [];
        foreach (DB::select(<<<'SQL'
            SELECT c.conname, child.relname AS child, source.attname AS source_column,
                   parent.relname AS parent, target.attname AS target_column,
                   c.confdeltype AS delete_action, c.confupdtype AS update_action
            FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace
            JOIN pg_class child ON child.oid = c.conrelid
            JOIN pg_class parent ON parent.oid = c.confrelid
            JOIN pg_attribute source ON source.attrelid = c.conrelid AND source.attnum = c.conkey[1]
            JOIN pg_attribute target ON target.attrelid = c.confrelid AND target.attnum = c.confkey[1]
            WHERE n.nspname = 'library' AND c.contype = 'f' ORDER BY c.conname
            SQL) as $constraint) {
            $actual[$constraint->conname] = [
                $constraint->child, $constraint->source_column, $constraint->parent,
                $constraint->target_column, $constraint->delete_action, $constraint->update_action,
            ];
        }
        $this->assertSame([
            'fk_book_category' => ['book', 'category_id', 'category', 'id', 'r', 'a'],
            'fk_loan_item_book' => ['loan_item', 'book_id', 'book', 'id', 'r', 'a'],
            'fk_loan_item_loan' => ['loan_item', 'loan_id', 'loan', 'id', 'c', 'a'],
            'fk_loan_member' => ['loan', 'member_id', 'member', 'id', 'r', 'a'],
            'fk_loan_user' => ['loan', 'loaned_by_user_id', 'user', 'id', 'r', 'a'],
        ], $actual);

        $this->seedHistory();
        foreach (['category', 'book', 'member', 'user'] as $table) {
            $this->assertSqlState('23503', fn () => DB::table('library.'.$table)->delete());
        }
        $this->assertSame(1, DB::table('library.loan_item')->count());

        // Comprobar la FK CASCADE no habilita borrado de préstamos en el producto.
        DB::table('library.loan')->delete();
        $this->assertSame(0, DB::table('library.loan_item')->count());
    }

    public function test_partial_isbn_index_allows_reuse_only_after_soft_deletion(): void
    {
        $this->seedHistory();
        $copy = $this->book('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
        $this->assertSqlState('23505', fn () => DB::table('library.book')->insert($copy));
        DB::table('library.book')->where('id', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')
            ->update(['deleted_at' => '2026-10-14T12:00:00Z']);
        DB::table('library.book')->insert($copy);
        $this->assertSame(2, DB::table('library.book')->count());
        $this->assertSame('Título histórico', DB::table('library.loan_item')->value('book_title'));
    }

    #[DataProvider('invalidAvailability')]
    public function test_database_rejects_availability_outside_the_documented_range(int $available): void
    {
        $book = $this->book();
        $book['available_copies'] = $available;
        $this->assertSqlState('23514', fn () => DB::table('library.book')->insert($book));
        $this->assertSame(0, DB::table('library.book')->count());
    }

    public static function invalidAvailability(): array
    {
        return [[-1], [3]];
    }

    public function test_required_columns_and_orphan_references_are_rejected(): void
    {
        $book = $this->book();
        unset($book['id']);
        $this->assertSqlState('23502', fn () => DB::table('library.book')->insert($book));
        $book = $this->book();
        $book['category_id'] = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
        $this->assertSqlState('23503', fn () => DB::table('library.book')->insert($book));
    }

    public function test_unique_constraints_are_enforced_by_the_engine(): void
    {
        $this->seedHistory();
        foreach (['category', 'member', 'user', 'loan_item'] as $table) {
            $copy = (array) DB::table('library.'.$table)->orderBy('id')->first();
            $copy['id'] = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
            $this->assertSqlState('23505', fn () => DB::table('library.'.$table)->insert($copy));
        }
    }

    public function test_access_indexes_and_current_constraint_stage_match_the_document(): void
    {
        $indexes = DB::select("SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = 'library' ORDER BY indexname");
        $definitions = [];
        foreach ($indexes as $index) {
            $definitions[$index->indexname] = $index->indexdef;
        }
        $this->assertCount(15, $definitions); // 6 PK, 4 UNIQUE, 1 UNIQUE parcial y 4 índices de acceso.
        foreach ([
            'uq_book_active_isbn' => ['UNIQUE', '(isbn)', 'deleted_at IS NULL'],
            'ix_book_active_category_title' => ['(category_id, title)', 'deleted_at IS NULL'],
            'ix_loan_loaned_at' => ['(loaned_at)'],
            'ix_loan_item_book' => ['(book_id)'],
            'ix_loan_item_open_due_at' => ['(due_at)', 'returned_at IS NULL'],
        ] as $name => $fragments) {
            foreach ($fragments as $fragment) {
                $this->assertStringContainsString($fragment, $definitions[$name]);
            }
        }
        $this->assertSame(['ck_book_available_range'], array_column(DB::select(<<<'SQL'
            SELECT c.conname FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace
            WHERE n.nspname = 'library' AND c.contype = 'c' ORDER BY c.conname
            SQL), 'conname')); // Las demás CHECK conservan su estado pendiente de T-18.
        $this->assertCount(6, DB::select(<<<'SQL'
            SELECT c.conname FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace
            WHERE n.nspname = 'library' AND c.contype = 'p'
            SQL));
    }

    public function test_migration_is_repeatable_and_can_be_rolled_back_and_reapplied(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true, '--path' => 'database/migrations/2026_10_14_000001_create_library_schema.php']));
        $this->assertSame(5, DB::table('library.category')->count());
        $this->assertSame(1, DB::table('public.migrations')->count());
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--force' => true, '--path' => 'database/migrations/2026_10_14_000001_create_library_schema.php']));
        $this->migrated = false;
        $this->assertNull(DB::selectOne("SELECT to_regnamespace('library') AS name")->name);
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true, '--path' => 'database/migrations/2026_10_14_000001_create_library_schema.php']));
        $this->migrated = true;
        $this->assertSame(5, DB::table('library.category')->count());
    }

    private function assertSqlState(string $expected, callable $operation): void
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            $this->assertSame($expected, $exception->errorInfo[0]);

            return;
        }
        $this->fail('La base aceptó una operación que debía rechazar.');
    }

    private function book(string $id = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'): array
    {
        return [
            'id' => $id, 'title' => 'Título actual', 'author' => 'Autor de prueba', 'isbn' => '9780307474728',
            'category_id' => '11111111-1111-4111-8111-111111111111', 'total_copies' => 2,
            'available_copies' => 2, 'cover_key' => null, 'deleted_at' => null,
        ];
    }

    private function seedHistory(): void
    {
        $id = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $book = $this->book();
        $book['title'] = 'Título histórico';
        DB::table('library.book')->insert($book);
        DB::table('library.member')->insert(['id' => $id, 'full_name' => 'Socio de prueba', 'document_number' => 'fixture-001']);
        DB::table('library.user')->insert([
            'id' => $id, 'username' => 'fixture',
            'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_ARGON2ID), 'role' => 'librarian',
        ]);
        DB::table('library.loan')->insert([
            'id' => $id, 'member_id' => $id, 'loaned_by_user_id' => $id, 'loaned_at' => '2026-10-01T12:00:00Z',
        ]);
        DB::table('library.loan_item')->insert([
            'id' => $id, 'loan_id' => $id, 'book_id' => $id, 'book_title' => 'Título histórico',
            'isbn' => '9780307474728', 'category_name' => 'Novela',
            'due_at' => '2026-10-15T12:00:00Z', 'returned_at' => '2026-10-05T12:00:00Z',
        ]);
        DB::table('library.book')->where('id', $id)->update(['title' => 'Título actual']);
    }
}
