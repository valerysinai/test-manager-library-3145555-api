<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\CoverContent;
use App\Library\Application\Ports\BookRepository;
use App\Library\Application\Ports\CoverStorage;
use App\Library\Application\Ports\UnitOfWork;
use App\Library\Domain\Book;
use App\Library\Domain\User;
use App\Library\Domain\Errors\ConcurrentModification;
use App\Library\Domain\Errors\ValidationError;
use Throwable;
final readonly class UploadCoverService
{
    public function __construct(private BookRepository $books, private CoverStorage $covers, private UnitOfWork $work) {}
    public function execute(User $actor, string $id, string $bytes): Book
    {
        CatalogRules::admin($actor);
        if (strlen($bytes) < 1 || strlen($bytes) > CoverContent::MAX_BYTES) {
            throw new ValidationError('file', 'La portada debe contener entre 1 y 2.000.000 bytes.');
        }
        $type = $this->covers->mediaType($bytes);
        if (!in_array($type, ['image/jpeg', 'image/png'], true)) {
            throw new ValidationError('file', 'La portada debe ser una imagen JPEG o PNG.');
        }
        $oldKey = $this->work->run(function () use ($id): ?string {
            $book = CatalogRules::book($this->books, $id);
            if ($book->coverKey !== null) { $this->books->save($book->withCover(null)); }
            return $book->coverKey;
        });
        if ($oldKey !== null) { $this->covers->delete($oldKey); }
        $key = $this->covers->put(new CoverContent($bytes, $type));
        try {
            return $this->work->run(function () use ($id, $key): Book {
                $book = CatalogRules::book($this->books, $id);
                if ($book->coverKey !== null) { throw new ConcurrentModification; }
                $updated = $book->withCover($key);
                $this->books->save($updated);
                return $updated;
            });
        } catch (Throwable $failure) {
            try { $this->covers->delete($key); }
            catch (Throwable) { throw new \RuntimeException('Falló la limpieza de una portada sin asociar.', 0, $failure); }
            throw $failure;
        }
    }
}
