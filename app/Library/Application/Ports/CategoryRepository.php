<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Domain\Category;
interface CategoryRepository
{
    public function find(string $id): ?Category;
}
