<?php

declare(strict_types=1);

namespace App\Library\Domain\Errors;

final class ForbiddenRole extends \DomainException
{
    public function __construct()
    {
        parent::__construct('El rol no permite realizar esta operación.');
    }
}
