<?php

declare(strict_types=1);

namespace App\Library\Domain\Errors;

final class InvalidCredentials extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Las credenciales no son válidas.');
    }
}
