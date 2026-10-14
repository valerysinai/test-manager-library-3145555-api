<?php
declare(strict_types=1);
namespace App\Library\Adapters\Outbound\Security;
use App\Library\Application\Ports\{Clock, TokenIssuer, TokenVerifier};
use App\Library\Application\UserIdentity;
use App\Library\Domain\Guard;
use App\Library\Domain\Errors\Unauthenticated;
use DateTimeImmutable;
use SensitiveParameter;
final readonly class HmacJwt implements TokenIssuer, TokenVerifier
{
    public function __construct(#[SensitiveParameter] private string $secret, private Clock $clock)
    {
        if (strlen($secret) < 32) { throw new \RuntimeException('JWT_SECRET requiere al menos 32 bytes de entorno.'); }
    }
    private static function encode(string $bytes): string { return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='); }
    private static function decode(string $value): string
    {
        if ($value === '' || preg_match('/\A[A-Za-z0-9_-]+\z/', $value) !== 1) { throw new Unauthenticated; }
        $bytes = base64_decode(strtr($value, '-_', '+/'), true);
        if ($bytes === false || self::encode($bytes) !== $value) { throw new Unauthenticated; }
        return $bytes;
    }
    public function issue(string $subject, string $role, DateTimeImmutable $issuedAt, DateTimeImmutable $expiresAt): string
    {
        $identity = new UserIdentity($subject, $role);
        $iat = $issuedAt->getTimestamp(); $exp = $expiresAt->getTimestamp();
        if ($exp - $iat !== 28800) { throw new \LogicException('La sesión debe durar ocho horas.'); }
        $header = self::encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::encode(json_encode(['sub' => $identity->id, 'role' => $identity->role, 'iat' => $iat, 'exp' => $exp], JSON_THROW_ON_ERROR));
        $input = $header.'.'.$payload;
        return $input.'.'.self::encode(hash_hmac('sha256', $input, $this->secret, true));
    }
    public function verify(#[SensitiveParameter] string $token): UserIdentity
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) { throw new Unauthenticated; }
            [$header, $payload, $signature] = $parts;
            if (!hash_equals(hash_hmac('sha256', $header.'.'.$payload, $this->secret, true), self::decode($signature))) {
                throw new Unauthenticated;
            }
            $h = json_decode(self::decode($header), true, 16, JSON_THROW_ON_ERROR);
            $p = json_decode(self::decode($payload), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($h) || count($h) !== 2 || ($h['alg'] ?? null) !== 'HS256' || ($h['typ'] ?? null) !== 'JWT'
                || !is_array($p) || count($p) !== 4 || !is_string($p['sub'] ?? null) || !is_string($p['role'] ?? null)
                || !is_int($p['iat'] ?? null) || !is_int($p['exp'] ?? null) || $p['exp'] - $p['iat'] !== 28800) {
                throw new Unauthenticated;
            }
            $now = $this->clock->now()->getTimestamp();
            if ($p['iat'] > $now || $p['exp'] <= $now) { throw new Unauthenticated; }
            return new UserIdentity(Guard::uuid($p['sub']), $p['role']);
        } catch (\Throwable) { throw new Unauthenticated; }
    }
    public function __debugInfo(): array { return ['algorithm' => 'HS256']; }
}
