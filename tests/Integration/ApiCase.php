<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Library\Adapters\Outbound\Security\HmacJwt;
use App\Library\Application\Ports\Clock;
use Illuminate\Support\Facades\{Artisan,DB};
use Tests\TestCase;
abstract class ApiCase extends TestCase
{
    protected string $secret; protected string $password; protected string $covers; protected string $fixed='2026-10-14T12:00:00Z';
    private bool $migrated=false;
    protected function setUp(): void
    {
        parent::setUp();
        $server=DB::selectOne("SELECT current_database() AS name,current_setting('server_version_num')::int AS version");
        self::assertSame('library_test',$server->name);
        self::assertGreaterThanOrEqual(160000,$server->version); self::assertLessThan(170000,$server->version);
        self::assertSame([],DB::select("SELECT table_schema,table_name FROM information_schema.tables WHERE table_type='BASE TABLE'
            AND table_schema NOT IN ('pg_catalog','information_schema') AND table_schema NOT LIKE 'pg_%'
            AND NOT (table_schema='public' AND table_name='migrations')"));
        $this->secret=bin2hex(random_bytes(32)); $this->password=bin2hex(random_bytes(24));
        $this->covers=sys_get_temp_dir().'/library-api-'.bin2hex(random_bytes(10));
        config(['library.jwt_secret'=>$this->secret,'library.admin_username'=>'initial-admin',
            'library.admin_password'=>$this->password,'library.covers_dir'=>$this->covers,
            'library.clock_mode'=>'fixed','library.clock_fixed_at'=>$this->fixed,'library.cors_origin'=>'https://ui.example.test']);
        self::assertSame(0,Artisan::call('migrate',['--force'=>true])); $this->migrated=true;
        self::assertSame(0,Artisan::call('library:bootstrap-admin'));
        self::assertStringNotContainsString($this->password,Artisan::output());
    }
    protected function tearDown(): void
    {
        try {
            if($this->migrated) {
                $pdo=DB::connection()->getPdo(); if($pdo->inTransaction()) $pdo->rollBack();
                self::assertSame(0,Artisan::call('migrate:rollback',['--force'=>true]));
            }
            if(isset($this->covers)&&is_dir($this->covers)) {
                foreach(glob($this->covers.'/*') as $file) unlink($file);
                rmdir($this->covers);
            }
            DB::purge('concurrent');
        } finally { parent::tearDown(); }
    }
    protected function clockAt(string $iso): void
    {
        $this->fixed=$iso; config(['library.clock_fixed_at'=>$iso]);
        $this->app->forgetInstance(Clock::class); $this->app->forgetInstance(HmacJwt::class);
    }
    protected function api(string $method,string $uri,?array $body=null,?string $token=null): \Illuminate\Testing\TestResponse
    {
        $headers=['HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json'];
        if($token!==null) $headers['HTTP_AUTHORIZATION']='Bearer '.$token;
        return $this->call($method,'/api/v1'.$uri,[],[],[],$headers,$body===null?'':json_encode($body,JSON_THROW_ON_ERROR));
    }
    protected function login(string $username='initial-admin',?string $password=null): string
    {
        $response=$this->api('POST','/auth/login',['username'=>$username,'password'=>$password??$this->password]);
        $response->assertOk()->assertJsonStructure(['access_token','token_type','expires_in','role']);
        return $response->json('access_token');
    }
    protected function book(string $token,int $number=1,int $total=2): array
    {
        return $this->api('POST','/books',['title'=>'Libro '.$number,'author'=>'Autor',
            'isbn'=>str_pad((string)$number,13,'0',STR_PAD_LEFT),'category_id'=>'11111111-1111-4111-8111-111111111111',
            'total_copies'=>$total],$token)->assertCreated()->json();
    }
    protected function member(string $token,int $number=1): array
    {
        return $this->api('POST','/members',['full_name'=>'Socio '.$number,'document_number'=>'DOC-'.$number],$token)->assertCreated()->json();
    }
    protected function lend(string $token,string $member,array $books): \Illuminate\Testing\TestResponse
    {
        return $this->api('POST','/loans',['member_id'=>$member,'items'=>array_map(static fn($id)=>['book_id'=>$id],$books)],$token);
    }
    protected function error(\Illuminate\Testing\TestResponse $response,int $status,string $code): void
    {
        $response->assertStatus($status)->assertJsonPath('code',$code)->assertJsonStructure(['code','message','details']);
        self::assertSame(['code','message','details'],array_keys($response->json()));
    }
}
