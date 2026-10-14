<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use App\Library\Application\Ports\CategoryRepository;
use App\Library\Domain\Category;
final readonly class PgCategoryRepository implements CategoryRepository
{
    public function __construct(private PgSession $session) {}
    public function all(): array { return array_map(Rows::category(...), $this->session->all('SELECT id,name FROM library.category ORDER BY name,id')); }
    public function find(string $id): ?Category
    {
        $row = $this->session->one('SELECT id,name FROM library.category WHERE id=:id', ['id' => $id]);
        return $row === null ? null : Rows::category($row);
    }
}
