<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Persistence;
use Closure;
use PDO;
final class PgSession
{
    private ?PDO $connection = null;
    public array $versions = [];
    public function __construct(private readonly Closure $connect) {}
    public function pdo(): PDO
    {
        if ($this->connection === null) {
            $this->connection = ($this->connect)();
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->exec("SET TIME ZONE 'UTC'");
        }
        return $this->connection;
    }
    public function all(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    public function one(string $sql, array $parameters = []): ?array { return $this->all($sql, $parameters)[0] ?? null; }
    public function write(string $sql, array $parameters = []): int
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($parameters);
        return $statement->rowCount();
    }
    public function lock(string $key): void
    {
        if (!$this->pdo()->inTransaction()) { throw new \LogicException('El bloqueo requiere transacción.'); }
        $bits = '';
        foreach (unpack('C*', substr(hash('sha256', strtolower($key), true), 0, 8)) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $this->all('SELECT pg_advisory_xact_lock(CAST(CAST(:bits AS bit(64)) AS bigint))', ['bits' => $bits]);
    }
    public static function date(\DateTimeImmutable $date): string { return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP'); }
    public static function pattern(string $query): string { return '%'.strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']).'%'; }
}
