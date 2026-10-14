<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Library\Application\Ports\UuidGenerator;
final class SequentialUuid implements UuidGenerator
{
    private int $sequence=10000;
    public function next(): string { return DomainFixtures::id(++$this->sequence); }
}
