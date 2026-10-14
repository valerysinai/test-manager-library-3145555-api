<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use App\Library\Application\UserIdentity;
use SensitiveParameter;
interface TokenVerifier { public function verify(#[SensitiveParameter] string $token): UserIdentity; }
