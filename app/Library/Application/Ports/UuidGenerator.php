<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
interface UuidGenerator { public function next(): string; }
