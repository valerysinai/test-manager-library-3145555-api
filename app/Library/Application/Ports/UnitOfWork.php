<?php
declare(strict_types=1);
namespace App\Library\Application\Ports;
use Closure;
interface UnitOfWork
{
    /** Confirma antes de retornar; revierte al fallar. Conflicto: un reintento completo máximo.
     * El adaptador conserva xmin por intento; el callback relee/revalida y no hace IO externo.
     */
    public function run(Closure $operation): mixed;
}
