<?php
declare(strict_types=1);
namespace Tests\Application;
use App\Library\Application\Services\CreateBookService;
use App\Library\Application\Services\EditBookService;
use App\Library\Application\Services\DeactivateBookService;
use App\Library\Application\Services\GetBookService;
use App\Library\Application\Services\SearchBooksService;
use App\Library\Application\Services\GetCoverService;
use App\Library\Application\Services\RemoveCoverService;
use App\Library\Application\Services\UploadCoverService;
use App\Library\Domain\Book;
use App\Library\Domain\User;
use App\Library\Domain\Errors\BookHasOpenLoans;
use App\Library\Domain\Errors\ConcurrentModification;
use App\Library\Domain\Errors\ForbiddenRole;
use App\Library\Domain\Errors\IsbnTaken;
use App\Library\Domain\Errors\ResourceNotFound;
use App\Library\Domain\Errors\ValidationError;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\CatalogUnitOfWork;
use Tests\Fakes\DomainFixtures as F;
use Tests\Fakes\FakeClock;
use Tests\Fakes\InMemoryCatalog;
use Tests\Fakes\InMemoryCoverStorage;

final class BooksTest extends TestCase
{
    private InMemoryCatalog $catalog;
    private CatalogUnitOfWork $work;
    private InMemoryCoverStorage $covers;
    private User $admin;
    private FakeClock $clock;
    protected function setUp(): void
    {
        $this->catalog = new InMemoryCatalog;
        $this->work = new CatalogUnitOfWork($this->catalog);
        $this->covers = new InMemoryCoverStorage($this->catalog);
        $this->admin = new User(F::id(900), 'Admin', 'opaque-fixture-value', 'admin');
        $this->clock = new FakeClock(new DateTimeImmutable('2026-10-14T07:00:00-05:00'));
    }
    private function create(int $id = 1, string $title = ' Título ', string $author = ' Autor ', ?string $category = null, int $total = 2): Book
    {
        return (new CreateBookService($this->catalog, $this->catalog, $this->work))->execute(
            $this->admin, F::id($id), $title, $author, str_pad((string) $id, 13, '0', STR_PAD_LEFT),
            $category ?? F::category()->id, $total);
    }
    private function upload(): UploadCoverService { return new UploadCoverService($this->catalog, $this->covers, $this->work); }
    private function remove(): RemoveCoverService { return new RemoveCoverService($this->catalog, $this->covers, $this->work); }
    private function deactivate(): DeactivateBookService { return new DeactivateBookService($this->catalog, $this->work, $this->clock); }

    public function test_create_starts_with_full_stock_trimmed_fields_and_no_cover(): void
    {
        $book = $this->create(total: 3);
        self::assertSame('Título', $book->title);
        self::assertSame('Autor', $book->author);
        self::assertSame(3, $book->availableCopies);
        self::assertNull($book->coverKey);
        self::assertSame($book, (new GetBookService($this->catalog))->execute($book->id));
        self::assertFalse($this->catalog->inTransaction);
    }
    public function test_unknown_category_does_not_create_a_book(): void
    {
        try { $this->create(category: F::id(999)); self::fail('Debió rechazar la categoría.'); }
        catch (ResourceNotFound) { self::assertSame([], $this->catalog->books); }
    }
    public function test_duplicate_isbn_is_unique_only_among_active_books_and_preserves_the_old_record(): void
    {
        $old = $this->create();
        $service = new CreateBookService($this->catalog, $this->catalog, $this->work);
        try {
            $service->execute($this->admin, F::id(2), 'Otro', 'Autor', $old->isbn->value, F::category()->id, 1);
            self::fail('Debió rechazar ISBN activo repetido.');
        } catch (IsbnTaken) { self::assertCount(1, $this->catalog->books); }
        $this->deactivate()->execute($this->admin, $old->id);
        $new = $service->execute($this->admin, F::id(2), 'Otro', 'Autor', $old->isbn->value, F::category()->id, 1);
        self::assertNotSame($old->id, $new->id);
        self::assertNotNull($this->catalog->books[$old->id]->deletedAt);
        self::assertCount(2, $this->catalog->books);
    }
    public function test_insert_conflict_after_precheck_is_not_hidden(): void
    {
        $this->catalog->failAdd = true;
        try { $this->create(); self::fail('Debió preservar el conflicto.'); }
        catch (IsbnTaken) { self::assertSame([], $this->catalog->books); }
    }
    #[DataProvider('invalidBooks')]
    public function test_invalid_book_values_do_not_persist(string $title, string $isbn, int $total): void
    {
        $service = new CreateBookService($this->catalog, $this->catalog, $this->work);
        try {
            $service->execute($this->admin, F::id(1), $title, 'Autor', $isbn, F::category()->id, $total);
            self::fail('Debió rechazar el libro.');
        } catch (ValidationError) { self::assertSame([], $this->catalog->books); }
    }
    public static function invalidBooks(): array
    {
        return [[' ', '1234567890123', 1], ['Libro', '123', 1], ['Libro', '1234567890123', 0]];
    }
    public function test_edit_applies_delta_changes_category_and_preserves_isbn_and_cover(): void
    {
        $book = $this->create(total: 3);
        $this->catalog->books[$book->id] = $book->withdraw()->withdraw()->withCover('existing-key');
        $service = new EditBookService($this->catalog, $this->catalog, $this->work);
        $edited = $service->execute($this->admin, $book->id, ' Nuevo ', 'Autor nuevo',
            '22222222-2222-4222-8222-222222222222', 4);
        self::assertSame(2, $edited->availableCopies);
        self::assertSame('Nuevo', $edited->title);
        self::assertSame('Ciencia', $edited->category->name);
        self::assertSame($book->isbn->value, $edited->isbn->value);
        self::assertSame('existing-key', $edited->coverKey);
        try {
            $service->execute($this->admin, $book->id, 'Incorrecto', 'Autor', F::category()->id, 1);
            self::fail('Debió rechazar total menor que lo prestado.');
        } catch (ValidationError) { self::assertSame($edited, $this->catalog->books[$book->id]); }
    }
    public function test_conflict_retry_rereads_the_book_before_recomputing_stock(): void
    {
        $book = $this->create(total: 2);
        $this->catalog->failSaves = 1;
        $this->catalog->afterConflict = function () use ($book): void {
            $this->catalog->books[$book->id] = $book->withdraw();
        };
        $edited = (new EditBookService($this->catalog, $this->catalog, $this->work))
            ->execute($this->admin, $book->id, 'Nuevo', 'Autor', F::category()->id, 3);
        self::assertSame(2, $edited->availableCopies);
        self::assertContains('rollback', $this->catalog->events);
    }
    public function test_search_filters_active_title_author_and_category_orders_ties_and_counts_pages(): void
    {
        $this->create(2, 'Árbol', 'María');
        $this->create(1, 'Árbol', 'María');
        $this->create(3, 'Otro', 'María', '22222222-2222-4222-8222-222222222222');
        $deleted = $this->create(4, 'Árbol', 'María');
        $this->deactivate()->execute($this->admin, $deleted->id);
        $search = new SearchBooksService($this->catalog);
        $page = $search->execute(' ÁRB ', F::category()->id, 1, 1);
        self::assertSame(2, $page->total);
        self::assertSame(F::id(1), $page->items[0]->id);
        self::assertSame(3, $search->execute('MARÍA')->total);
        self::assertSame(F::id(3), $search->execute('', '22222222-2222-4222-8222-222222222222')->items[0]->id);
        $empty = $search->execute('Árb', null, 3, 1);
        self::assertSame([], $empty->items);
        self::assertSame(2, $empty->total);
        self::assertSame(0, $search->execute(categoryId: F::id(999))->total);
    }
    #[DataProvider('invalidPagination')]
    public function test_search_rejects_invalid_pagination_before_reading(int $page, int $size): void
    {
        $this->expectException(ValidationError::class);
        try { (new SearchBooksService($this->catalog))->execute(page: $page, size: $size); }
        finally { self::assertSame(0, $this->catalog->reads); }
    }
    public static function invalidPagination(): array { return [[0, 20], [1, 0], [1, 51]]; }
    public function test_soft_delete_hides_catalog_details_and_rejects_open_loans(): void
    {
        $book = $this->create();
        $this->catalog->openLoans[$book->id] = true;
        try { $this->deactivate()->execute($this->admin, $book->id); self::fail('Debió rechazar préstamos abiertos.'); }
        catch (BookHasOpenLoans) { self::assertNull($this->catalog->books[$book->id]->deletedAt); }
        $this->catalog->openLoans[$book->id] = false;
        $this->deactivate()->execute($this->admin, $book->id);
        self::assertSame('2026-10-14T12:00:00+00:00', $this->catalog->books[$book->id]->deletedAt->format('c'));
        $this->expectException(ResourceNotFound::class);
        (new GetBookService($this->catalog))->execute($book->id);
    }
    public function test_valid_missing_id_and_malformed_id_are_distinct_errors(): void
    {
        $get = new GetBookService($this->catalog);
        try { $get->execute(F::id(999)); self::fail('Debió indicar recurso ausente.'); }
        catch (ResourceNotFound) { self::assertSame([], $this->catalog->books); }
        $this->expectException(ValidationError::class);
        $get->execute('malformed');
    }
    #[DataProvider('mutations')]
    public function test_every_catalog_mutation_rejects_librarian_before_lookup_or_storage(string $operation): void
    {
        $actor = new User(F::id(901), 'Librarian', 'opaque-fixture-value', 'librarian');
        try {
            match ($operation) {
                'create' => (new CreateBookService($this->catalog, $this->catalog, $this->work))
                    ->execute($actor, 'invalid', '', '', '', 'invalid', 0),
                'edit' => (new EditBookService($this->catalog, $this->catalog, $this->work))
                    ->execute($actor, 'invalid', '', '', 'invalid', 0),
                'deactivate' => $this->deactivate()->execute($actor, 'invalid'),
                'upload' => $this->upload()->execute($actor, 'invalid', ''),
                'remove' => $this->remove()->execute($actor, 'invalid'),
            };
            self::fail('Debió rechazar el actor.');
        } catch (ForbiddenRole) {
            self::assertSame(0, $this->catalog->reads);
            self::assertSame([], $this->catalog->events);
            self::assertSame([], $this->covers->files);
        }
    }
    public static function mutations(): array { return [['create'], ['edit'], ['deactivate'], ['upload'], ['remove']]; }

    #[DataProvider('acceptedMedia')]
    public function test_cover_accepts_exact_decimal_limit_and_returns_binary_by_key(string $prefix, string $type): void
    {
        $book = $this->create();
        $bytes = $prefix.str_repeat('x', 2000000 - strlen($prefix));
        $updated = $this->upload()->execute($this->admin, $book->id, $bytes);
        self::assertSame('cover-1', $updated->coverKey);
        $read = (new GetCoverService($this->catalog, $this->covers))->execute($book->id);
        self::assertSame($bytes, $read->bytes);
        self::assertSame($type, $read->mediaType);
    }
    public static function acceptedMedia(): array { return [['PNG:', 'image/png'], ['JPEG:', 'image/jpeg']]; }
    #[DataProvider('invalidCovers')]
    public function test_invalid_cover_never_writes_or_stores(string $bytes): void
    {
        $book = $this->create();
        $this->catalog->events = [];
        try { $this->upload()->execute($this->admin, $book->id, $bytes); self::fail('Debió rechazar el archivo.'); }
        catch (ValidationError $error) {
            self::assertSame('file', $error->field);
            self::assertSame([], $this->catalog->events);
            self::assertSame([], $this->covers->files);
        }
    }
    public static function invalidCovers(): array
    {
        return [[''], ['GIF:fixture'], ['PNG:'.str_repeat('x', 1999997)],
            ['PNG:'.str_repeat('x', 2097148)], ['PNG:'.str_repeat('x', 2999996)]];
    }
    public function test_replacing_and_removing_confirm_null_before_binary_deletion(): void
    {
        $book = $this->create();
        $first = $this->upload()->execute($this->admin, $book->id, 'PNG:first');
        $this->catalog->events = [];
        $second = $this->upload()->execute($this->admin, $book->id, 'JPEG:second');
        self::assertSame(['begin', 'save-null', 'commit', 'delete:cover-1', 'put:cover-2',
            'begin', 'save-cover', 'commit'], $this->catalog->events);
        self::assertArrayNotHasKey($first->coverKey, $this->covers->files);
        self::assertSame('cover-2', $second->coverKey);
        $this->catalog->events = [];
        $this->remove()->execute($this->admin, $book->id);
        self::assertSame(['begin', 'save-null', 'commit', 'delete:cover-2'], $this->catalog->events);
        self::assertNull($this->catalog->books[$book->id]->coverKey);
        self::assertSame([], $this->covers->files);
        $this->remove()->execute($this->admin, $book->id);
        $this->expectException(ResourceNotFound::class);
        (new GetCoverService($this->catalog, $this->covers))->execute($book->id);
    }
    public function test_failed_cover_association_rolls_back_and_cleans_the_new_binary(): void
    {
        $book = $this->create();
        $this->catalog->failSaves = 2;
        try { $this->upload()->execute($this->admin, $book->id, 'PNG:new'); self::fail('Debió conservar el conflicto.'); }
        catch (ConcurrentModification) {
            self::assertNull($this->catalog->books[$book->id]->coverKey);
            self::assertSame([], $this->covers->files);
            self::assertSame(2, count(array_filter($this->catalog->events, static fn ($event) => $event === 'rollback')));
        }
    }
    public function test_delete_failure_leaves_committed_null_and_reports_storage_error(): void
    {
        $book = $this->create();
        $this->upload()->execute($this->admin, $book->id, 'PNG:old');
        $this->covers->failDelete = true;
        try { $this->remove()->execute($this->admin, $book->id); self::fail('Debió informar el fallo de almacenamiento.'); }
        catch (\RuntimeException $error) {
            self::assertSame('Fallo simulado al borrar portada.', $error->getMessage());
            self::assertNull($this->catalog->books[$book->id]->coverKey);
            self::assertCount(1, $this->covers->files);
        }
    }
    public function test_cleanup_failure_is_explicit_and_keeps_the_original_conflict_as_cause(): void
    {
        $book = $this->create();
        $this->catalog->failSaves = 2;
        $this->covers->failDelete = true;
        try { $this->upload()->execute($this->admin, $book->id, 'PNG:new'); self::fail('Debió informar limpieza fallida.'); }
        catch (\RuntimeException $error) {
            self::assertSame('Falló la limpieza de una portada sin asociar.', $error->getMessage());
            self::assertInstanceOf(ConcurrentModification::class, $error->getPrevious());
            self::assertNull($this->catalog->books[$book->id]->coverKey);
            self::assertCount(1, $this->covers->files);
        }
    }
    public function test_missing_binary_and_deleted_book_are_not_readable_as_covers(): void
    {
        $book = $this->create();
        $this->catalog->books[$book->id] = $book->withCover('missing-key');
        $get = new GetCoverService($this->catalog, $this->covers);
        try { $get->execute($book->id); self::fail('Debió indicar binario ausente.'); }
        catch (ResourceNotFound) { self::assertSame([], $this->covers->files); }
        $this->deactivate()->execute($this->admin, $book->id);
        $this->expectException(ResourceNotFound::class);
        $get->execute($book->id);
    }
    public function test_invalid_category_filter_is_rejected_before_query(): void
    {
        $this->expectException(ValidationError::class);
        try { (new SearchBooksService($this->catalog))->execute(categoryId: 'invalid'); }
        finally { self::assertSame(0, $this->catalog->reads); }
    }
}
