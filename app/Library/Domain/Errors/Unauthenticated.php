<?php
declare(strict_types=1);
namespace App\Library\Domain\Errors;
final class Unauthenticated extends \DomainException
{
    public function __construct() { parent::__construct('Se requiere una autenticación válida.'); }
}
