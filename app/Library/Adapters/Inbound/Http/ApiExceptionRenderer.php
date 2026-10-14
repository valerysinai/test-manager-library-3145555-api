<?php
declare(strict_types=1);
namespace App\Library\Adapters\Inbound\Http;
use App\Library\Domain\Errors as Errors;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
final class ApiExceptionRenderer
{
    public static function render(Throwable $exception,Request $request): JsonResponse
    {
        $mapping=[
            Errors\InvalidCredentials::class=>[401,'INVALID_CREDENTIALS'],
            Errors\Unauthenticated::class=>[401,'UNAUTHENTICATED'],
            Errors\ForbiddenRole::class=>[403,'FORBIDDEN_ROLE'],
            Errors\ResourceNotFound::class=>[404,'NOT_FOUND'],
            Errors\UsernameTaken::class=>[409,'USERNAME_TAKEN'],
            Errors\IsbnTaken::class=>[409,'ISBN_TAKEN'],
            Errors\DocumentTaken::class=>[409,'DOCUMENT_TAKEN'],
            Errors\BookHasOpenLoans::class=>[409,'BOOK_HAS_OPEN_LOANS'],
            Errors\BookNotAvailable::class=>[409,'BOOK_NOT_AVAILABLE'],
            Errors\MemberHasOverdue::class=>[409,'MEMBER_HAS_OVERDUE'],
            Errors\ItemAlreadyReturned::class=>[409,'ITEM_ALREADY_RETURNED'],
            Errors\ConcurrentModification::class=>[409,'CONCURRENT_MODIFICATION'],
            Errors\MemberLimitExceeded::class=>[422,'MEMBER_LIMIT_EXCEEDED'],
            Errors\DuplicateBookInLoan::class=>[422,'DUPLICATE_BOOK_IN_LOAN'],
            Errors\ValidationError::class=>[422,'VALIDATION_ERROR'],
            Errors\InvalidDateRange::class=>[422,'INVALID_DATE_RANGE'],
        ];
        $details=[]; $headers=[];
        if (isset($mapping[$exception::class])) {
            [$status,$code]=$mapping[$exception::class]; $message=$exception->getMessage();
            if ($exception instanceof Errors\ValidationError) { $details[]=['field'=>$exception->field,'issue'=>$message]; }
            if ($exception instanceof Errors\MemberLimitExceeded) {
                $details[]=['field'=>'items','issue'=>'El socio tiene actualmente '.$exception->currentCount.' libros sin devolver.'];
            }
        } else {
            $status=$exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            [$status,$code,$message]=match($status) {
                401=>[401,'UNAUTHENTICATED','Se requiere una autenticación válida.'],
                403=>[403,'FORBIDDEN_ROLE','El rol no permite realizar esta operación.'],
                404=>[404,'NOT_FOUND','No se encontró el recurso solicitado.'],
                405=>[405,'METHOD_NOT_ALLOWED','El método no está permitido para esta ruta.'],
                413,422=>[422,'VALIDATION_ERROR','La entrada no es válida.'],
                default=>[500,'INTERNAL_ERROR','Ocurrió un error interno.'],
            };
            if ($status===405 && $exception instanceof HttpExceptionInterface) {
                $headers=array_intersect_key($exception->getHeaders(),['Allow'=>true]);
            }
        }
        $response=new JsonResponse(['code'=>$code,'message'=>$message,'details'=>$details],$status,$headers);
        if ($request->headers->get('Origin')===config('library.cors_origin')) {
            $response->headers->set('Access-Control-Allow-Origin',config('library.cors_origin'));
            $response->headers->set('Vary','Origin');
        }
        return $response;
    }
}
