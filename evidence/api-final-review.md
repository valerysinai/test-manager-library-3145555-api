# Revisión final de API para integrar en main

## Alcance y orden SDD

Se conserva `feature/api-completion`, nacida de main `b2fa106`. La API completa ya estaba
implementada en `590a012`, pero no fusionada. Esta revisión contrasta README, constitución,
contrato, modelo, tasks y decisiones C-01 a C-10 de docs `8e62f0f`, sin sustituirlos.

El [plan de revisión](api-final-review-plan.md) se publicó en `38b6e66` antes de la regresión
`e4412ee`. La precisión de C-08 `26713c8` precede a su nueva prueba. Se mantiene el plan previo
de implementación `10c9b1b`; una revisión final no se presenta como análisis previo de ese código.

## Hallazgo reproducido y corrección

Contrato §5.2: una página posterior al final devuelve `items: []` y el total real. Los tres
adaptadores PostgreSQL multiplicaban `(page-1)*size` incluso si no podía haber resultados.
Una página positiva representable como `PHP_INT_MAX` desbordaba el offset para size=50.

Se ejecutaron seis regresiones contra PostgreSQL 16 antes de corregir: libros, socios y préstamos,
con un resultado existente y con colección vacía. Las seis fallaron. La
[CI de esa regresión sin corregir](https://github.com/valerysinai/test-manager-library-3145555-api/actions/runs/38671009543)
también falló; el estado fallido se conserva como evidencia del defecto.

`6c19fd7` devuelve la página vacía después del COUNT y antes del offset cuando no puede contener
filas, calculando el límite con división entera. No añade un máximo arbitrario de página ni cambia
el error a 422. Se conservan filtros, orden, tamaño, página solicitada y total real.
También se verifican primera/última página, siguiente página, filtro sin coincidencias e
invalidación de page cero/negativa/decimal/array y size mayor que 50.

## Cobertura adicional requerida por Docs

C-08 exige dos altas simultáneas del mismo documento; la evidencia anterior de duplicados era
secuencial. `68b41b0` añade dos bibliotecarios en procesos PHP separados, cada uno con su conexión,
liberados mediante la barrera existente. Se comprueban 201/409, `DOCUMENT_TAKEN` y un único socio
persistido. La prueba pasa sin modificar el alta ni añadir una regla de negocio.

## Ejecución local real

Sobre `68b41b073ae5bf6be3c5f7bc9d21e62ed701d658`, `python scripts/verify_local.py` pasó:

- 172 pruebas de dominio, aplicación, arquitectura, adaptadores y composición.
- 28 pruebas de integración, incluidas seis regresiones y los cuatro escenarios concurrentes.
- Cero fallos y limpieza confirmada de los recursos generados.
- Escaneo de archivos versionados y cinco pruebas del detector de secretos correctos.

Se ejecutó PHP 8.4 dentro de Docker; no se afirma instalación PHP nativa de Windows.
El [resumen seguro](api-final-local-summary.json) conserva la secuencia fallo/corrección/cierre.
No se publican XML completos, cuerpos de error, peticiones, claves ni tokens.

## Verificación independiente de la revisión final

Las tres suites remotas sobre `68b41b0` terminaron con éxito:

- [API bootstrap](https://github.com/valerysinai/test-manager-library-3145555-api/actions/runs/38671283487): núcleo, arquitectura, composición y salud sin base.
- [API schema](https://github.com/valerysinai/test-manager-library-3145555-api/actions/runs/38671283430): PostgreSQL, contrato, cuatro escenarios concurrentes, introspección, HTTPS multipart real e INSERT inválidos por psql.
- [API quality](https://github.com/valerysinai/test-manager-library-3145555-api/actions/runs/38671283428): dependencias Composer y control de secretos.

Esto complementa la ejecución local y reemplaza la atribución a CI de revisiones antiguas para
la corrección de paginación. Las evidencias originales conservan su contexto histórico.

## C-04 y C-07 sobre la API corregida

Se ejecutó nuevamente `npm run verify` de tool `e494a3a`, con API `68b41b0` e infra `b496573`.
El [resumen demo](api-final-demo.json) confirma 40 libros, 10 socios, 15 préstamos y exactamente
tres líneas vencidas, preservación de la base al recrear solo API, token anterior rechazado y
login nuevo. Todos los datos de negocio entraron por HTTPS; no se usó SQL desde tool.

Otro proyecto nuevo, perfil production/reloj real, recibió 5.000 libros por API. El
[reporte íntegro de rendimiento](api-final-benchmark.json) conserva las 400 medidas, 80 warmups
excluidos y 40 solicitudes del diagnóstico de diez clientes simultáneos, junto a consultas,
commits, CPU/RAM y ubicación. Medición: `2026-10-15T02:34:10.826Z` a `2026-10-15T02:34:30.102Z`.

Las 400 respuestas fueron válidas y estrictamente menores de 300 ms: máximo 70,5113 ms,
mediana 36,29535 ms y p95 50,0072 ms. No se descartaron fallos ni muestras lentas. El diagnóstico
concurrente también terminó sin errores. RNF-1 pasa en este entorno concreto; no es una garantía
de latencia para cualquier equipo. Las mediciones no se atribuyen a la revisión antigua `590a012`.

Los proyectos y volúmenes de prueba se limpiaron al terminar. No se modificó ningún archivo
de producto de infra/tool/app. Este recorrido comprueba la API por HTTPS; no se presenta como
un nuevo recorrido de navegador React sobre main.

El último commit conserva solo documentación y reportes seguros. La
[comparación para crear el PR](https://github.com/valerysinai/test-manager-library-3145555-api/compare/main...feature/api-completion?expand=1)
tiene como base `main` del fork de la aprendiz, no el repositorio upstream.

## Correspondencia con el producto existente

| Requisito | Evidencia de API |
|---|---|
| HU-01/02 | AuthenticationTest, ProductionAdaptersTest y ApiContractTest: roles, login genérico, JWT HS256 8 h, Argon2id y alta solo librarian |
| HU-03/04 | BooksTest y ApiContractTest: catálogo, ISBN activo único, existencias por delta, baja lógica y portada privada; PaginationBoundaryTest: páginas y totales |
| HU-05 | MembersTest, ApiContractTest y nuevo ConcurrencyTest: socios, detalle con líneas activas, documento único incluso concurrente |
| HU-06 | LoanTest, LoanServicesTest, ApiContractTest y ConcurrencyTest: 1–3 libros distintos, límite global, vencidos, snapshots y transacción |
| HU-07/08/09 | Devolución por línea, repetición rechazada, consulta/filtros/estado calculado y vencidos; concurrencia sin doble reposición |
| HU-10 / C-05 | Reporte SQL agrupado por ID/título/categoría congelados, sin dimensión personal; pruebas con renombrado |
| Artículos I–IV | Arquitectura, puertos, composición única y casos negativos; aplicación/dominio sin Laravel/PDO |
| Artículos V–VIII / T-18 | Migraciones propiedad de API, introspección del modelo, restricciones y rollback; sin columnas de estado o DEFAULT |
| Artículo IX / C-06 | Control de secretos, logger genérico y recorrido con HTTPS confiable en CI; sin hash/contraseña en respuestas |

Se conserva `public.migrations` como metadata plural fuera de las seis tablas de negocio singulares.
No se renombra ni mueve ninguna entidad, ruta o columna. No se incorporan funciones fuera del spec.

## Límites del cierre

El PR sigue pendiente de fusión en el fork. La evidencia de otros repositorios identifica sus
propias revisiones: una prueba de esta rama no certifica una instalación sobre main ni la corrección
posterior de React pendiente allí. La demostración manual de feria y la revisión integral de
accesibilidad no pertenecen a este cierre de API. Se conserva el horario original de entrega;
estos avances posteriores no cambian elegibilidad ni certifican ausencia de cualquier defecto.
