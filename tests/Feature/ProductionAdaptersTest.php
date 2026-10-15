<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Library\Adapters\Outbound\Security\{ArgonPasswordHasher,HmacJwt,RandomUuid};
use App\Library\Adapters\Outbound\Clock\ConfiguredClock;
use App\Library\Adapters\Outbound\Storage\DiskCoverStorage;
use App\Library\Application\CoverContent;
use App\Library\Domain\Errors\Unauthenticated;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeClock;
use Tests\Fakes\DomainFixtures as F;
final class ProductionAdaptersTest extends TestCase
{
    public static function png(): string
    {
        $chunk=static fn($type,$data)=>pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));
        return "\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',1,1,8,2,0,0,0))
            .$chunk('IDAT',gzcompress("\0\xff\0\0")).$chunk('IEND','');
    }
    public function test_real_hash_preserves_password_and_uses_argon2id_without_exposing_dummy(): void
    {
        $hasher=new ArgonPasswordHasher;
        $password=' '.bin2hex(random_bytes(24)).' ';
        $hash=$hasher->hash($password);
        self::assertSame('argon2id',password_get_info($hash)['algoName']);
        self::assertTrue($hasher->verify($password,$hash));
        self::assertFalse($hasher->verify(trim($password),$hash));
        self::assertFalse($hasher->verify($password,null));
        self::assertSame(['algorithm'=>'argon2id'],$hasher->__debugInfo());
    }
    public function test_jwt_checks_signature_algorithm_and_exact_expiration_with_injected_clock(): void
    {
        $clock=new FakeClock(new DateTimeImmutable('2026-10-14T12:00:00Z'));
        $jwt=new HmacJwt(bin2hex(random_bytes(32)),$clock);
        $token=$jwt->issue(F::id(1),'admin',$clock->now(),$clock->now()->modify('+8 hours'));
        self::assertSame(F::id(1),$jwt->verify($token)->id);
        $payload=json_decode(base64_decode(strtr(explode('.',$token)[1],'-_','+/')),true);
        self::assertSame(['sub','role','iat','exp'],array_keys($payload));
        $bad=substr($token,0,-1).(str_ends_with($token,'a')?'b':'a');
        try { $jwt->verify($bad); self::fail('Debió rechazar la firma.'); } catch(Unauthenticated) {}
        $clock->set(new DateTimeImmutable('2026-10-14T20:00:00Z'));
        $this->expectException(Unauthenticated::class);
        $jwt->verify($token);
    }
    public function test_fixed_clock_is_allowed_only_in_demo_or_testing(): void
    {
        self::assertSame('2026-10-14T12:00:00+00:00',(new ConfiguredClock('fixed','2026-10-14T12:00:00Z','demo'))->now()->format('c'));
        $this->expectException(\RuntimeException::class);
        new ConfiguredClock('fixed','2026-10-14T12:00:00Z','production');
    }
    public function test_signed_tokens_cannot_override_algorithm_role_claims_or_lifetime(): void
    {
        $clock=new FakeClock(new DateTimeImmutable('2026-10-14T12:00:00Z'));
        $secret=bin2hex(random_bytes(32)); $jwt=new HmacJwt($secret,$clock);
        $encode=static fn($value)=>rtrim(strtr(base64_encode(json_encode($value,JSON_THROW_ON_ERROR)),'+/','-_'),'=');
        $header=['alg'=>'HS256','typ'=>'JWT'];
        $payload=['sub'=>F::id(1),'role'=>'admin','iat'=>$clock->now()->getTimestamp(),'exp'=>$clock->now()->getTimestamp()+28800];
        foreach([
            [['alg'=>'none','typ'=>'JWT'],$payload],
            [['alg'=>'HS512','typ'=>'JWT'],$payload],
            [$header,array_replace($payload,['role'=>'reader'])],
            [$header,$payload+['username'=>'unexpected']],
            [$header,array_replace($payload,['exp'=>$payload['exp']+1])],
            [$header,array_replace($payload,['iat'=>$payload['iat']+1,'exp'=>$payload['exp']+1])],
            [$header,array_replace($payload,['sub'=>'invalid'])],
        ] as [$h,$p]) {
            $input=$encode($h).'.'.$encode($p);
            $token=$input.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256',$input,$secret,true)),'+/','-_'),'=');
            try { $jwt->verify($token); self::fail('El verificador aceptó un contrato alterado.'); }
            catch(Unauthenticated) { self::assertTrue(true); }
        }
    }
    public function test_real_png_storage_is_private_opaque_and_cannot_traverse_paths(): void
    {
        $directory=sys_get_temp_dir().'/library-cover-'.bin2hex(random_bytes(10));
        $storage=new DiskCoverStorage($directory,new RandomUuid,dirname(__DIR__,2).'/public');
        try {
            $png=self::png();
            self::assertSame('image/png',$storage->mediaType($png));
            self::assertNull($storage->mediaType('PNG:fake'));
            $key=$storage->put(new CoverContent($png,'image/png'));
            self::assertSame($png,$storage->read($key)->bytes);
            self::assertDoesNotMatchRegularExpression('~[/\\\\]~',$key);
            $storage->delete($key); $storage->delete($key);
            self::assertNull($storage->read($key));
            $this->expectException(\UnexpectedValueException::class);
            $storage->read('../outside.png');
        } finally { foreach(glob($directory.'/*') as $file) { unlink($file); } rmdir($directory); }
    }
}
