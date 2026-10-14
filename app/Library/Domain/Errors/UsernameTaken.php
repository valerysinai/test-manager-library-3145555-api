<?php

declare(strict_types=1);

namespace App\Library\Domain\Errors;

final class UsernameTaken extends \DomainException
{
    public function __construct()
    {
        parent::__construct('El nombre de usuario ya está registrado.');
    }
}
