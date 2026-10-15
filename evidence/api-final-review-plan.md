# Plan previo de revisión final de API

Se continúa `feature/api-completion`, base main `b2fa106`, código inicial `590a012`.
La revisión prepara el PR pendiente; no confunde una rama publicada con código fusionado.
Docs de referencia: `8e62f0f`, decisiones C-01 a C-10, contrato y tasks en ambas versiones.
El plan anterior `10c9b1b` y su evidencia siguen vigentes para T-09 a T-19.

## Verificar antes de cambiar

- Contrastar roles/errores, invariantes y snapshots con la especificación; conservar seis tablas
  singulares y reportar `public.migrations` como metadata plural, sin renombrarla.
- Usar las pruebas existentes de dominio, aplicación, arquitectura, PostgreSQL, concurrencia,
  seguridad y TLS. Los resultados de infra/tool sobre `590a012` se citan con su revisión real.
- El contrato §5.2 exige página posterior al final vacía con total real. Por inspección, los tres
  repositorios calculan `($page-1)*$size` después del COUNT sin limitarlo al total. Para una página
  positiva representable en PHP, esa multiplicación puede desbordar y llegar como float a OFFSET.
  Es una sospecha pendiente de reproducción; no se añade un límite de páginas nuevo.

## Escenarios preparados

1. Regresión HTTP/PostgreSQL por cada colección: un libro, socio y préstamo; `page=PHP_INT_MAX`,
   `size=50` debe responder 200, items vacío, misma página y total 1. Registrar primero el fallo.
2. Si se reproduce, devolver la página vacía después del conteo y antes de calcular un offset
   que no puede contener filas. Usar aritmética entera; conservar filtros, orden y tamaño máximo.
3. Verificar primera/última página, página siguiente y colección filtrada vacía. La autorización
   y los errores de tamaño/tipo conservan su contrato; no cambiar entradas a 422 arbitrariamente.
4. Ejecutar las suites apropiadas y el escaneo de secretos; diagnósticos de fallos solo con nombres
   y ubicaciones, nunca mensajes de petición, SQL, claves o tokens.
5. Actualizar evidencia/README de cierre con resultados exactos y enlaces a CI, sin certificar
   perfección ni atribuir las pruebas de otras revisiones al código nuevo.

No se reescribe el producto ni se cambia tecnología. La prueba de integración se ejecuta solo
contra una base nueva, desechable y llamada library_test. El PR queda para revisión/fusión manual
en el fork; se mantiene el corte original de entrega y sus limitaciones.

## Precisión de cobertura antes de añadir otra prueba

La inspección de ConcurrencyTest muestra los tres escenarios C-01 a C-03, pero C-08 exige además
dos altas simultáneas del mismo documento. La prueba HTTP actual solo comprueba el duplicado
secuencial. Se reutilizará la barrera de dos procesos/bibliotecarios existentes para verificar
201/409 DOCUMENT_TAKEN y un único socio persistido, sin cambiar el contrato ni anticipar un defecto.
