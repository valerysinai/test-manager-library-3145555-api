<?php
// Worker de integración: no imprime headers, credenciales, excepciones ni SQL.
try {
    require __DIR__.'/../../vendor/autoload.php';
    $app=require __DIR__.'/../../bootstrap/app.php';
    $kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);
    $kernel->bootstrap();
    \Illuminate\Support\Facades\DB::statement("SET statement_timeout='10000ms'");
    $task=json_decode(getenv('LIBRARY_WORK'),true,16,JSON_THROW_ON_ERROR);
    $barrier=getenv('LIBRARY_BARRIER');
    file_put_contents($barrier.'/'.getmypid().'.ready','');
    $until=microtime(true)+15;
    while(!is_file($barrier.'/go')) {
        if(microtime(true)>$until) throw new RuntimeException;
        usleep(10000);
    }
    $request=\Illuminate\Http\Request::create($task['uri'],$task['method'],[],[],[],[
        'CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json',
        'HTTP_AUTHORIZATION'=>'Bearer '.getenv('LIBRARY_TOKEN')],
        $task['body']===null?'':json_encode($task['body'],JSON_THROW_ON_ERROR));
    $response=$kernel->handle($request);
    echo json_encode(['status'=>$response->getStatusCode(),'body'=>json_decode($response->getContent(),true)],JSON_THROW_ON_ERROR);
    $kernel->terminate($request,$response);
} catch(Throwable) { echo json_encode(['worker_error'=>true]); exit(1); }
