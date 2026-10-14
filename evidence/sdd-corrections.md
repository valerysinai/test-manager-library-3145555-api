# Evidencia de las correcciones SDD

## Base y propósito

El [plan previo](sdd-corrections-plan.md), commit `27fd8fc`, se versionó antes de los
cambios de implementación. Aplica la revisión de docs integrada en `16cba02`.
La rama incluye el avance de autenticación `8b3a44b`, que aún no estaba en origin/main
`a7843b0` al iniciarla. La corrección de evidencia T-07 se revisa junto a ese código.

## Hallazgos atendidos

| Hallazgo | Cambio | Verificación específica |
|---|---|---|
| SDD-02 | evidence/t-07.md separa CA-02.1 del alcance completo de CA-01.3 | Revisión de la tabla y sus pendientes; ninguna ruta nueva ni afirmación de autenticación HTTP completa |
| SDD-03 | scripts/check_secrets.py y workflow API quality | Tests de patrones con muestras sintéticas en memoria y repositorio temporal, checkout final escaneado sin mostrar valores |
| SDD-04 | DependencyInspector resuelve imports simples/agrupados, aliases, nombres cualificados y funciones nativas | Casos prohibidos y permitidos en DependencyInspectorTest; inspección de Domain/Application reales y de composición en app/bootstrap/routes |
| SDD-09 | DateRange valida el formato antes de invocar el analizador nativo | ValuesTest prueba from y to con byte nulo: ValidationError con el campo correcto; la suite conserva fechas reales, rango invertido y extremo inclusivo |

El formato de fecha conserva exactamente YYYY-MM-DD del contrato §5.3. No se añade
una regla de negocio ni se transforma una fecha inválida en error interno.
La prueba de arquitectura permite usar estáticamente el renderizador HTTP en bootstrap
sin confundir una referencia con una instanciación o un binding.

## Control de secretos: alcance

El control usa únicamente la biblioteca estándar de Python del runner de CI y Git para
leer archivos versionados del checkout. La aplicación y sus pruebas de negocio siguen
en PHP/Laravel. No se añade ninguna dependencia al runtime ni a composer.lock.

Se buscan encabezados de claves privadas, formatos de tokens GitHub, IDs de acceso AWS,
hashes Argon2/BCrypt precalculados, URLs de base con credenciales y asignaciones literales
de password/secret en los campos cubiertos por el script. Un .env real versionado también falla.
El .env.example vacío, interpolaciones y ejemplos enmascarados pasan.

No hay exclusiones por carpetas: tests, documentación y lockfiles también se recorren.
Los ejemplos prohibidos se generan durante el test, en memoria o en una carpeta temporal;
se retiran al terminar. El informe solo contiene ruta, número de línea y nombre de regla.
El proceso falla si no puede completar el recorrido.

Esta búsqueda acredita el control de patrones previsto por artículo IX sobre el checkout.
No analiza el historial Git, variables remotas ni todos los secretos de formato arbitrario.
Tampoco acredita ausencia de datos sensibles en los futuros logs ni transporte HTTPS de RNF-3.

## Arquitectura: alcance

Se analizan los tokens PHP sin ejecutar las fuentes inyectadas ni cargar librerías externas.
La comprobación del núcleo resuelve imports y aliases, rechaza dependencias fuera de sus
capas, PDO en sus firmas/referencias y helpers externos. Rechaza también carga dinámica de
fuentes, llamadas a funciones variables y algunos despachadores dinámicos.

La comprobación de composición rechaza new de adaptadores, construcción dinámica,
bindings, resolución de contenedor y service locators fuera de LibraryServiceProvider.
Dentro del punto de composición se permite la unión de infraestructura y puertos.
Los casos prohibidos de la prueba son texto sintético, no dependencias del núcleo.

Es un control estático del código existente y de los patrones de prueba documentados.
No es un análisis completo de reflexión, código generado o comportamiento de dependencias
de terceros. La futura composición y los adaptadores reales deben ampliar su evidencia
al incorporarse; este avance no cierra T-17 ni T-19 completo.

## Reproducción

Desde la raíz de API, con Git y Python 3.10 o posterior:

```sh
python3 -m unittest discover -s scripts -p test_check_secrets.py
python3 scripts/check_secrets.py
```

El chequeo se integra automáticamente en API quality con permisos de lectura.
Los tests PHP y la salud se ejecutan mediante el objetivo Docker test y API bootstrap.
API schema verifica la regresión de la migración contra PostgreSQL 16 real.
Los comandos anteriores no usan credenciales operativas ni despliegan el sistema.

## Resultados ejecutados

Commit de código verificado: `b30d90bd0c517f6fdddd0693bc1862e1a04ed18e`.

- [API bootstrap: success](https://github.com/valerysinai/test-manager-library-3145555-api/actions/runs/38629300466): suites PHP, casos permitidos/prohibidos del detector, fechas inválidas y regresión de salud/runtime.
- [API schema: success](https://github.com/valerysinai/test-manager-library-3145555-api/actions/runs/38629300821): esquema y semilla contra PostgreSQL 16 real.
- [API quality: success](https://github.com/valerysinai/test-manager-library-3145555-api/actions/runs/38629301308): muestras sintéticas y scan del checkout final de ese commit.

Las ejecuciones pertenecen al mismo commit. La validación PHP se ejecutó en GitHub Actions
con PHP 8.4 y el lockfile del proyecto; no se afirma ejecución local en Windows.
El commit siguiente añade únicamente este resultado y la explicación al README.

## Pendientes conservados

- CA-01.3, errores por HTTP y autenticación del actor real: T-16.
- Hash Argon2id, JWT firmado y expiración real: T-14.
- Repositorios, transacciones y concurrencia: T-12/T-13.
- Composición integrada, administrador y arquitectura completa: T-17/T-19.
- SDD-10: precisar el umbral en bytes de portada antes de su frontera de aceptación.
- La regla de entrega del README no cambia; estas correcciones son posteriores al corte.
