<?php
declare(strict_types=1);
namespace App\Library\Application\Services;
use App\Library\Application\BookPage;
use App\Library\Application\Ports\BookRepository;
use App\Library\Domain\Guard;
use App\Library\Domain\Errors\ValidationError;
final readonly class SearchBooksService
{
    public function __construct(private BookRepository $books) {}
    public function execute(string $query = '', ?string $categoryId = null, int $page = 1, int $size = 20): BookPage
    {
        if ($page < 1 || $size < 1 || $size > 50) {
            throw new ValidationError('page/size', 'La paginación debe ser positiva y el tamaño no puede superar 50.');
        }
        return $this->books->search(trim($query), $categoryId === null ? null : Guard::uuid($categoryId, 'category_id'), $page, $size);
    }
}
