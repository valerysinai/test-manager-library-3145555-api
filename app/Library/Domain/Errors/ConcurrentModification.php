<?php
declare(strict_types=1);
namespace App\Library\Domain\Errors;
final class ConcurrentModification extends \DomainException
{
    public function __construct() { parent::__construct('El recurso cambió durante la operación.'); }
}
