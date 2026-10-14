<?php
declare(strict_types=1);
namespace App\Library\Domain\Errors;
final class IsbnTaken extends \DomainException
{
    public function __construct() { parent::__construct('El ISBN ya pertenece a un libro activo.'); }
}
