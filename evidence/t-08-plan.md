# Plan previo T-08 · Libros

Base de API: main `5b03da4`. Referencia: HU-03/HU-04, contrato §§3.4/5,
data-model §7 y decisiones C-01. Se documenta antes de implementar esta rama.

## Decisiones y escenarios previstos

- Crear, consultar, buscar, editar y dar de baja mediante BookRepository y CategoryRepository.
  Alta/edición/baja/portada requieren actor admin; lectura admite ambos roles.
- El ISBN permanece inmutable; disponibilidad inicial igual al total y edición por delta.
  Categoría debe existir. ISBN único solo entre activos, incluyendo conflicto al insertar.
- Búsqueda parcial título/autor, categoría opcional, solo activos, orden título/ID,
  paginación 1/20, máximo 50, total real y página posterior vacía.
- UnitOfWork expresa confirmación/rollback y relectura completa ante conflictos acotados;
  los testigos xmin pertenecen al adaptador, no al dominio ni al contrato del puerto.
  La baja comprueba préstamos abiertos dentro de esa misma unidad de trabajo.
- SDD-10: se interpreta MB como unidad decimal: límite inclusivo de 2.000.000 bytes.
  Es una decisión de representación del límite existente, registrada explícitamente antes
  de implementar la frontera. No se sustituye por 2 MiB (2.097.152 bytes).
- CoverStorage identifica MIME por contenido: image/jpeg o image/png. No recibe nombre,
  extensión ni MIME del cliente. La aplicación limita bytes antes de escribir.
- Quitar/reemplazar portada confirma primero cover_key nula y después borra el binario viejo.
  El almacenamiento externo ocurre fuera de transacciones. Un fallo posterior puede dejar
  el libro sin portada; no se promete atomicidad entre base y almacenamiento.
- La portada nueva usa una clave opaca; un conflicto al asociarla limpia el binario nuevo.
  Eliminar físicamente un libro o modificar préstamos no es parte de estos casos de uso.

Se probarán éxito, autorización previa a consulta, validación, duplicados, baja con préstamos,
búsquedas y paginación, stock por delta, límites de archivo, orden de borrado y fallo al guardar
la clave nueva. Los fakes no acreditan detección MIME real, disco, concurrencia PostgreSQL ni HTTP.
Esas garantías permanecen en T-12/T-13/T-15/T-16.
