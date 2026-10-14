# test-manager-library-3145555-api

> **Prueba técnica · Ficha ADSO 3145555**
> Horario: **miércoles 14 de octubre de 2026, de 6:50 a. m. a 11:00 a. m.** (hora Colombia). Entrega a las **11:00 a. m.**

Este repositorio es el **backend** de *Manager Library*, en **PHP con Laravel**. **Empieza vacío a propósito**: se construye en el fork de cada aprendiz.

## Instrucciones

Cada aprendiz debe **crear el fork** de los seis repositorios del proyecto y **resolver el proyecto
con el spec planteado**.

1. Hacer fork, a su cuenta de GitHub, de cada repositorio de la tabla del final.
2. Leer el spec en [`test-manager-library-3145555-docs`](https://github.com/code-sena/test-manager-library-3145555-docs).
   Se entrega en dos versiones: `spec-python/` y `spec-.net/`.
3. Desarrollar en los forks.

## El reto se desarrolla con React y PHP (Laravel)

El spec está escrito para Python y para .NET, pero el reto **no** se hace en esos lenguajes:

| Capa | Tecnología del reto |
|---|---|
| Frontend | React |
| Backend | PHP con Laravel |

Lo que el spec define sobre el negocio —historias, criterios de aceptación, reglas, contrato de la
API, modelo de datos— se respeta. Lo que define sobre la tecnología se traduce a React y Laravel.

## La prueba no consiste en escribir el código

El propósito principal es ver la **capacidad de desempeño con SDD** (*Spec-Driven Development*,
desarrollo guiado por especificación): cómo se lee, se interpreta y se aplica una especificación
para llevarla a un stack distinto. El código es el medio, no el fin.

## Entrega

Cuenta lo que quede **subido a los forks antes de las 11:00 a. m.** Lo que llegue después no se revisa.
No hay prórroga: suban avances parciales con commits pequeños durante la prueba.

## Los seis repositorios

| Repositorio | Qué va ahí |
|---|---|
| [`test-manager-library-3145555-docs`](https://github.com/code-sena/test-manager-library-3145555-docs) | El spec: `spec-python/` y `spec-.net/` |
| [`test-manager-library-3145555-api`](https://github.com/code-sena/test-manager-library-3145555-api) | Backend en PHP (Laravel) |
| [`test-manager-library-3145555-app`](https://github.com/code-sena/test-manager-library-3145555-app) | Frontend en React |
| [`test-manager-library-3145555-page`](https://github.com/code-sena/test-manager-library-3145555-page) | Sitio público estático de presentación |
| [`test-manager-library-3145555-infra`](https://github.com/code-sena/test-manager-library-3145555-infra) | Contenedores, red, volúmenes y motor de base de datos vacío |
| [`test-manager-library-3145555-tool`](https://github.com/code-sena/test-manager-library-3145555-tool) | Utilidades: sembrador de datos de demostración |

## Primer avance: T-01

Los apartados por avance conservan su alcance histórico. El estado de la API completa y su
arranque actual se describen en [T-09 a T-19](#t-09-a-t-19--api-completa); los pendientes
indicados en avances anteriores no sustituyen esa evidencia posterior.

Esta rama prepara el backend con PHP 8.4 y Laravel 13, siguiendo la
[adaptación aprobada](https://github.com/valerysinai/test-manager-library-3145555-docs/blob/main/adaptacion-react-laravel.md).
La única operación de producto implementada en este avance es `GET /api/v1/health`, pública,
con respuesta HTTP 200 y cuerpo `{"status":"ok"}`. No necesita base de datos ni secretos para arrancar.

### Preparar la imagen y verificar

Requisito: Docker con motor Linux activo. Ejecutar desde la raíz de este repositorio:

```sh
docker build --target test -t manager-library-api:test .
docker build -t manager-library-api:bootstrap .
```

El primer comando instala las dependencias de prueba y ejecuta PHPUnit. El segundo crea
la imagen de ejecución con las dependencias de producción fijadas en `composer.lock`.
PHP y Composer se ejecutan dentro del contenedor; no es necesario instalarlos en Windows.

La imagen de PHP y Composer están fijados por digest/checksum. `composer.lock` se generó
con PHP 8.4 en GitHub Actions y se conserva en el commit `b60bc27`. El paso temporal que lo
publicó fue retirado; el flujo final tiene permisos de lectura y no crea commits.
Las instalaciones reproducen las versiones del lockfile sin resolverlas de nuevo.

### Arrancar con un comando

```sh
docker run --rm -p 127.0.0.1:8000:8000 manager-library-api:bootstrap php artisan serve --host=0.0.0.0 --port=8000
```

En otra terminal:

```sh
curl http://localhost:8000/api/v1/health
```

En PowerShell también se puede consultar con:

```powershell
Invoke-RestMethod http://localhost:8000/api/v1/health
```

El puerto queda limitado a la máquina local. Este servidor sirve para verificar T-01;
el despliegue integrado y la terminación HTTPS pertenecen a T-20 en `infra`.
No se envían credenciales en este recorrido de salud. Para detenerlo, usar Ctrl+C.

### Estructura y límites del avance

En T-01, `app/Library/Domain`, `Application/Ports`, `Application/Services` y `Adapters/Outbound`
reservaban las capas del diseño mediante archivos `.gitkeep`. El tercer avance implementa
el dominio y los casos de uso de socios. El adaptador HTTP está en `Adapters/Inbound/Http`; la composición del núcleo
tiene su único punto en `app/Providers/LibraryServiceProvider.php`.

Las pruebas comprueban salud sin conexión a la base, prefijo correcto, 404, 405 y error 500
con el sobre JSON documentado, sin revelar detalles internos incluso en modo debug.
Esto no acredita autenticación ni el contrato completo de T-16, ni las pruebas de arquitectura de T-19.

T-01 no crea tablas, usuarios, sesiones, tokens ni datos de demostración.
La migración de T-02 se describe más abajo y vive en `database/migrations`.
Las variables de despliegue están descritas sin valores secretos en `.env.example`;
el archivo `.env` real y los registros de ejecución no se versionan.

La evidencia de este avance y los límites de su validación están en [evidence/t-01.md](evidence/t-01.md).

## Segundo avance: T-02

La migración inicial crea las seis tablas de negocio en el esquema `library`, con los nombres,
tipos, longitudes y nulabilidad de `data-model.md`. Todas sus columnas carecen de `DEFAULT`.
También crea las relaciones, el CHECK de disponibilidad, los índices de acceso y de unicidad,
incluido el ISBN único entre libros activos. Las cinco categorías se insertan en esa misma migración.
No se crea ningún administrador ni otro dato de demostración.

El registro técnico de Laravel se llama `public.migrations`. Es metadata de despliegue fuera
del modelo de negocio; no es una séptima entidad de `library`. Se reporta su nombre plural y
se conserva, mientras las seis tablas de negocio mantienen sus nombres singulares.

La migración se aplica explícitamente, con las credenciales de PostgreSQL aportadas por entorno:

```sh
php artisan migrate --force
```

La API no aplica migraciones al consultar salud ni crea tablas mediante el ORM al arrancar.
No existe DDL en `infra`. El aprovisionamiento del administrador corresponde a T-17.

### Pruebas contra PostgreSQL 16

El flujo `API schema` construye el objetivo Docker `integration`, crea un PostgreSQL 16 desechable
con contraseña aleatoria en una red privada, ejecuta PHPUnit y las consultas de `data-model.md` §10,
y retira sus contenedores y volúmenes al terminar. La contraseña no se versiona ni se muestra en logs.

Para construir el runner:

```sh
docker build --target integration -t manager-library-api:integration .
```

Para ejecutar la suite fuera de CI se requiere una base **vacía y desechable** llamada `library_test`
en PostgreSQL 16, con `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` configurados para ese
destino. El comando dentro del runner es:

```sh
php vendor/bin/phpunit --configuration phpunit.integration.xml
```

Las pruebas consultan el nombre real de la base antes de cualquier DDL, rechazan tablas ajenas
y aplican/revierten únicamente la migración de este proyecto. No utilizan `migrate:fresh`.
Verifican el contrato físico, las restricciones con escrituras inválidas, la conservación del
histórico, la semilla, la repetición de `migrate`, la reversa y la reaplicación.

Las CHECK documentadas para T-18 siguen pendientes: total positivo, rol válido, ISBN de 13 dígitos,
título/documento no vacíos, usuario normalizado y portada nula o no vacía. Este avance no afirma
que esas reglas ya estén aplicadas en el motor, ni acredita préstamos, devoluciones o concurrencia.

Los resultados ejecutados están en [evidence/t-02.md](evidence/t-02.md).

## Tercer avance: T-03 a T-06

El dominio contiene objetos de valor para ISBN, cantidad, documento y rango de fechas;
libros con disponibilidad y baja lógica; socios, usuarios y préstamos con sus líneas.
Está escrito en PHP nativo, sin importar Laravel, ORM, aplicación ni adaptadores.
El usuario normaliza su nombre y conserva únicamente un hash opaco; el hash se genera
en el adaptador de seguridad de una tarea posterior.

Un préstamo admite de uno a tres libros distintos y congela título, ISBN y categoría.
La política comprueba los ejemplares sin devolver en todos los préstamos del socio y
bloquea nuevos préstamos si tiene vencidos. El vencimiento es a 14 días; el estado se
calcula con el instante recibido y los días de atraso cuentan periodos completos de 24 horas.
La devolución afecta una sola línea y repone un ejemplar; la línea devuelta rechaza otra devolución.

Las operaciones devuelven estados inmutables del préstamo y del inventario. La aplicación
deberá persistir ambos dentro de una transacción. Estas pruebas no acreditan atomicidad
en PostgreSQL ni protección contra solicitudes concurrentes; eso corresponde a T-13.

T-06 incluye los casos de uso de crear, buscar y consultar socios detrás de `MemberRepository`,
con paginación y conteo de libros sin devolver. Se prueban con un repositorio en memoria,
incluyendo documento duplicado y conflicto al insertar. No hay adaptador de persistencia
ni nuevas rutas HTTP en este avance. Autenticación y creación exclusiva de bibliotecarios
siguen en T-07; su exposición HTTP y el rechazo de creación de administradores, en T-16.

### Verificar el núcleo

La construcción del objetivo `test` ejecuta las suites Domain, Application, Architecture y Feature:

```sh
docker build --target test -t manager-library-api:test .
```

Con PHP 8.4 y las dependencias de desarrollo instaladas también puede ejecutarse:

```sh
php vendor/bin/phpunit
```

La suite Architecture comprueba referencias de nombres del dominio y ejecuta un ciclo
de préstamo/devolución en un proceso que carga exclusivamente las clases del dominio.
Es una comprobación inicial de aislamiento; T-19 todavía debe verificar todos los límites
de arquitectura y el punto único de composición al incorporar los adaptadores.

La evidencia y la correspondencia con las tareas están en [evidence/t-03-t-06.md](evidence/t-03-t-06.md).

## Cuarto avance: T-07

`LoginService` y `CreateLibrarianService` usan los puertos `UserRepository`, `PasswordHasher`,
`TokenIssuer` y `Clock`. La aplicación conoce el dominio y sus propios puertos, sin importar
Laravel, persistencia ni adaptadores de seguridad.

El inicio de sesión normaliza el nombre de usuario y verifica la contraseña sin recortarla
ni transformarla. Usuario inexistente y contraseña incorrecta producen el mismo error
`InvalidCredentials`, sin emitir token. El puerto de hash recibe un hash nulo cuando no hay
usuario; el adaptador real de T-14 deberá verificar contra un hash ficticio de coste equivalente.
El fake verifica el recorrido del puerto; no acredita equivalencia de tiempos ni Argon2id.

Una sesión válida solicita un token para el ID y rol del usuario, con instantes UTC tomados
del reloj inyectado y exactamente 28.800 segundos de vigencia. El resultado tiene los campos
`access_token`, `token_type`, `expires_in` y `role` del contrato; su vista de depuración oculta
el token. El fake de emisión no es un JWT: firma HS256 y validación del token se implementan en T-14.

El alta exige un actor administrador autenticado y permite únicamente `librarian` como rol
solicitado. La autorización precede a la consulta del nombre. El nombre normalizado es único;
se conserva `UsernameTaken` si el repositorio detecta un conflicto después de la comprobación
previa. La contraseña obligatoria no vacía pasa al puerto de hash sin añadir reglas de complejidad.
La respuesta de alta contiene únicamente `id`, `username` y `role`.

El actor y el UUID del nuevo usuario proceden del servidor. En T-16, el adaptador HTTP deberá
obtener el actor de la autenticación verificada, rechazar campos ajenos y traducir los errores
a 401, 403, 409 o 422 según el contrato. Este avance no añade rutas, usuarios iniciales ni bindings
de infraestructura. La persistencia real corresponde a T-12, la seguridad a T-14 y el bootstrap
del administrador desde entorno a T-17.

La construcción Docker del objetivo `test` ejecuta también estas pruebas con fakes. Los escenarios,
resultados y límites están en [evidence/t-07.md](evidence/t-07.md).

## Correcciones de la revisión SDD

La base documental integrada en docs `16cba02` da lugar al
[plan previo de correcciones](evidence/sdd-corrections-plan.md), versionado antes del código.
La [evidencia de correcciones](evidence/sdd-corrections.md) distingue lo corregido de la
aceptación pendiente por HTTP, persistencia y seguridad real.

DateRange rechaza entradas con bytes nulos como errores de validación del campo.
Las pruebas de arquitectura resuelven imports y aliases, verifican dependencias del núcleo
y rechazan instanciación/bindings de adaptadores fuera del punto de composición.
API quality incorpora un control de patrones de secretos sobre archivos versionados,
con pruebas sintéticas y resultados sin valores sensibles.
Estos controles complementan las pruebas PHP y de PostgreSQL; no acreditan el sistema integrado.

## T-08 · Casos de uso de libros

La rama implementa creación, detalle, búsqueda, edición, baja lógica y carga/lectura/eliminación
de portadas detrás de puertos de aplicación. El [plan previo](evidence/t-08-plan.md) documenta
las decisiones antes del código y la [evidencia T-08](evidence/t-08.md) relaciona criterios y pruebas.

Las mutaciones exigen un actor admin. La edición conserva el ISBN y ajusta existencias por delta;
las búsquedas solo incluyen activos y conservan paginación y total. La baja utiliza Clock y
rechaza préstamos abiertos. El puerto UnitOfWork expresa rollback y reintento completo acotado,
sin exponer el testigo nativo de PostgreSQL al núcleo.

Las portadas admiten JPEG/PNG por contenido y hasta 2.000.000 bytes, según la decisión decimal
documentada. Para quitar/reemplazar se confirma primero la clave nula y después se borra el binario.
El IO externo ocurre fuera de transacciones; los límites ante fallos están descritos en la evidencia.
Las pruebas usan fakes. Persistencia, disco y rutas HTTP se incorporan en sus tareas posteriores.

## T-09 a T-19 · API completa

El [plan previo](evidence/api-completion-plan.md) precede a la implementación. Los servicios,
adaptadores, rutas y escenarios comprobados se relacionan en la
[evidencia de cierre](evidence/api-completion.md). Se mantienen las seis tablas singulares,
el contrato `/api/v1`, los mensajes españoles y el alcance del producto.

La API incorpora préstamos y devolución por línea, vencidos y reporte histórico, repositorios
PostgreSQL, coordinación transaccional por socio y testigo `xmin`, Argon2id, JWT HS256 de ocho
horas y portadas privadas. Las CHECK de T-18 se añaden en una segunda migración; no se altera
la migración inicial ni se incorporan columnas de auditoría o estado derivado.

### Configurar y arrancar

Construir la imagen de ejecución:

```sh
docker build --target application -t manager-library-api:application .
```

Preparar un archivo local de entorno a partir de `.env.example`, excluido de Git. Configurar
`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `JWT_SECRET` (al menos 32 bytes
aleatorios), `ADMIN_USERNAME`, `ADMIN_PASSWORD`, `CORS_ORIGIN` y `COVERS_DIR`. No escribir
contraseñas ni tokens en comandos compartidos, historial, capturas o archivos versionados.
La red, el motor PostgreSQL 16 vacío, el volumen de portadas y HTTPS corresponden a `infra`.

Con la base vacía disponible, aplicar las migraciones **explícitamente** desde la API:

```sh
docker run --rm --network library --env-file .env manager-library-api:application php artisan migrate --force
```

`library` es el nombre de red del ejemplo y debe coincidir con el despliegue de infra. Después,
arrancar en esa red, con un volumen persistente en el directorio privado de portadas:

```sh
docker run --rm --network library --env-file .env -v library-covers:/app/storage/app/private/covers manager-library-api:application
```

El comando predeterminado ejecuta `library:bootstrap-admin` y abre el servidor solo si el
aprovisionamiento termina correctamente. No ejecuta DDL. El administrador se toma únicamente
del entorno; repetir el arranque conserva su contraseña y no eleva un bibliotecario existente.
Salud sigue siendo pública e independiente de base/secretos cuando se ejecuta directamente el
servidor, como en la verificación histórica T-01.

`CLOCK_MODE=system` es el modo de producción. `fixed` requiere perfil `demo` o `testing` y
`CLOCK_FIXED_AT` UTC válido; autenticación y negocio comparten ese reloj. Las portadas admiten
JPEG/PNG comprobados por contenido y hasta 2.000.000 bytes; su directorio queda fuera de `public`.
Para credenciales y tokens, acceder a través de HTTPS con certificado confiable. El servidor
interno no publica puerto al host en este ejemplo; su exposición la configura infra.

### Verificación y límites

`API bootstrap` ejecuta las pruebas de dominio, aplicación con fakes, arquitectura, adaptadores
reales sin base y composición. `API schema` usa una base PostgreSQL 16 desechable y verifica el
contrato HTTP, rollback, restricciones y solicitudes simultáneas con dos procesos independientes.
También prueba el arranque real y multipart PUT detrás de un proxy TLS temporal con certificado
confiado explícitamente, sin desactivar validación TLS. `API quality` revisa Composer y patrones
de secretos; los diagnósticos públicos de fallos solo incluyen identificadores y ubicaciones.

Estas verificaciones de API no acreditan Compose final, React, sembrador, sitio público ni el
umbral de búsqueda sobre 5.000 libros. Esos entregables continúan en los otros repositorios.
El horario original de entrega permanece vigente; la evidencia posterior no cambia ese corte.
