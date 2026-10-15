# Evidencia de API · T-09 a T-19

Esta evidencia conserva la revisión inicial de cierre. La corrección de paginación, la cobertura
concurrente de C-08 y las ejecuciones posteriores se registran en la
[revisión final para integración](api-final-review.md), con sus propios commits y resultados.

Revisión del 14 de octubre de 2026. Base main b2fa106; implementación comprobada c4e3d8c,
en feature/api-completion, pendiente de PR/fusión. El plan previo es
[api-completion-plan.md](api-completion-plan.md), commit 10c9b1b, anterior al código.

## Ejecuciones verificadas

- [API bootstrap](https://github.com/valerysinai/test-manager-library-3145555-api/actions/runs/38637457502): éxito; dominio, aplicación, arquitectura, adaptadores y composición.
- [API schema](https://github.com/valerysinai/test-manager-library-3145555-api/actions/runs/38637457471): éxito; PostgreSQL 16, contrato, concurrencia, HTTPS y SQL directo.
- [API quality](https://github.com/valerysinai/test-manager-library-3145555-api/actions/runs/38637457824): éxito; ejemplos sintéticos y escaneo de secretos versionados sin valores en salida.

No se atribuye ejecución PHP local: el runtime se ejecutó en contenedores de CI.

## Correspondencia entre tareas y escenarios

| Tarea | Implementación y evidencia ejecutable | Resultado y límite |
|---|---|---|
| T-09 | LendBooksService; tests/Application/LoanServicesTest.php | Fakes de cardinalidad, duplicados, existencias, límite global y vencidos |
| T-10 | ReturnItemService, GetLoanService, SearchLoansService, OverdueService | Fakes y tests/Integration/ApiContractTest.php: devolución, estados, fechas, paginación y vencidos |
| T-11 | MostBorrowedReportService y PgReportReader | Agregado SQL de snapshots, sin socio/operador; renombrar el catálogo no cambia el histórico |
| T-12 | PgBookRepository, PgMemberRepository, PgUserRepository | Contrato HTTP sobre PostgreSQL real, unicidad, consultas y persistencia |
| T-13 | PgLoanRepository, PgSession, PgUnitOfWork | READ COMMITTED, bloqueo por socio, libros por UUID, xmin y un reintento completo; escritura obsoleta rechazada y rollback tras fallo FK |
| T-14 | ArgonPasswordHasher, HmacJwt, ConfiguredClock | tests/Feature/ProductionAdaptersTest.php: Argon2id, contraseña sin recortar, firma/algoritmo/claims/expiración; JWT y negocio comparten Clock |
| T-15 | DiskCoverStorage e Inputs::upload | PNG real de exactamente 2.000.000 bytes por multipart PUT aceptado; 3.000.000 y contenido inválido rechazados con 422; lectura privada y borrado |
| T-16 | routes/api.php y adaptadores HTTP | Todas las rutas protegidas rechazan ausencia de token; las siete operaciones admin rechazan librarian antes de validar entrada; todos los códigos de negocio de contrato §2 ejercitados |
| T-17 | LibraryServiceProvider, BootstrapAdmin y scripts/start-api.sh | Puertos/servicios resueltos sin abrir DB; migración explícita de base vacía seguida de arranque real y login admin por HTTPS; bootstrap idempotente y sin elevar librarian |
| T-18 | Segunda migración y tests/Integration/FinalConstraintsTest.php | Nueve CHECK totales; escrituras inválidas rechazadas; reversa/reaplicación y tests/Fixtures/check-inserts.sql ejecutado con psql |
| T-19 | tests/Architecture y tests/Feature/CompositionTest.php | Límites de dominio/aplicación y composición única, casos negativos de dependencias/imports/helpers; adaptación PHP de las herramientas de referencia |

## Concurrencia real

tests/Integration/ConcurrencyTest.php libera dos procesos PHP independientes mediante una barrera;
cada uno autentica un bibliotecario distinto y usa su propia conexión PostgreSQL.

- C-01 / RNF-2: último ejemplar → 201 y 409, una línea persistida y disponibilidad cero.
- C-02: devolución de la misma línea → 200 y 409; un incremento, aun con otros ejemplares prestados.
- C-03: mismo socio con dos líneas abiertas y dos libros distintos solicitados a la vez → 201 y 422;
  queda en tres líneas sin devolver.

## Transporte y seguridad

scripts/tls_smoke.py accede a un servidor real detrás del proxy temporal tests/Fixtures/tls-proxy.conf.
El cliente verifica certificado y nombre localhost con confianza explícita; no desactiva TLS.
Credenciales y claves se generan por ejecución, se enmascaran y no se versionan.
La prueba recorre login, categorías, libro, portada, socio, préstamo, devolución y reporte.
El proxy es una fixture de CI, no el despliegue integrado de infra.

Los diagnósticos públicos de PHPUnit permiten únicamente nombres de tests/clases y ubicaciones de
código: no publican mensajes, solicitudes, SQL, contraseñas, hashes ni tokens. El reporte de excepción
de la aplicación registra solo un mensaje genérico y el tipo de error.

Se corrigieron dos problemas detectados durante integración: aislamiento del perfil testing frente
al entorno production del contenedor y doble política CORS. El test de tipo decimal conserva 1.0
en el JSON para comprobar su rechazo real. La caché de archivos se renueva tras parsear PUT nativo.

## Límites de aceptación

Esta evidencia cubre la API y no certifica React, Compose final, sembrador, sitio público ni RNF-1
con 5.000 libros. El control estático de secretos no demuestra ausencia de cualquier secreto posible;
la revisión de logs cubre los recorridos ejecutados. Se conserva public.migrations como metadata
plural de Laravel, sin renombrar las seis entidades singulares. No hay cambios de alcance.

El corte original del README es 2026-10-14 11:00 America/Bogota. Estos avances son posteriores;
las pruebas y publicaciones no modifican ese corte ni acreditan elegibilidad de entrega.
