# Plan previo T-09 a T-19

Base main `b2fa106`. Se conserva docs, contrato, seis entidades y stack React/Laravel.
Este plan precede al código de feature/api-completion.

1. T-09/10/11: puertos y servicios de préstamo, devolución, detalle/listado, vencidos y reporte.
   Pruebas con fakes: cardinalidad, duplicados, límite global, vencidos, snapshots, devolución
   repetida y reporte histórico sin datos personales.
2. T-12/13: adaptadores PDO PostgreSQL 16 y unidad de trabajo. READ COMMITTED, bloqueo
   consultivo por socio antes de leer, libros por UUID, xmin por intento, escritura condicional
   de devolución y un reintento completo. Integración real y workers concurrentes para C-01/02/03.
3. T-14/15: Argon2id, JWT HS256 de 8 h con sub/role y Clock común, lectura/escritura de portadas
   en disco con MIME por contenido y clave opaca. Casos de firma alterada, expiración, contraseña
   incorrecta y archivos reales; no se registran cuerpos, hashes ni Authorization.
4. T-16/17: rutas exactas, autorización antes de existencia/entrada protegida, validación estricta
   de tipos/campos, errores únicos, CORS por entorno, composición única y comando de admin inicial.
   El helper de arranque aprovisiona admin desde entorno después de aplicar migraciones explícitas.
   Salud mantiene independencia de la base. HTTP contractual se prueba contra base real.
5. T-18/19: migración adicional de CHECK ya previstas, escrituras SQL inválidas rechazadas y
   arquitectura de núcleo/composición comprobada con casos negativos. No se modifica la migración
   inicial: su prueba histórica se limita a T-02 y se añade prueba del esquema completo.

La configuración del reloj fijo se limita a demo/testing. La creación de admin es idempotente,
con coordinación transaccional, y no permite elevar un bibliotecario existente ni cambiar su clave
silenciosamente. Las credenciales ausentes o inválidas abortan el aprovisionamiento.

La evidencia final distinguirá código/fakes, integración, contrato y concurrencia reales.
TLS confiable, despliegue integrado, rendimiento con 5.000 libros, frontend y sembrador siguen
en infra/app/tool y no se acreditan con estas suites. El corte del README se conserva.
