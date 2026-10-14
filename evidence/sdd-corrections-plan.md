# Plan previo de correcciones SDD

Base documental integrada: docs `16cba02`, con [segunda revisión](https://github.com/valerysinai/test-manager-library-3145555-docs/blob/16cba02/segunda-revision-sdd.md).
Base de API: `8b3a44b`, avance auth publicado; origin/main observado `a7843b0`.
La rama feature/api-sdd-fixes incluye auth porque la corrección SDD-02 afecta su evidencia.
Este archivo se versiona antes de implementar las correcciones.

## Alcance y escenarios previstos

| Hallazgo | Cambio previsto | Verificación |
|---|---|---|
| SDD-02 | Separar CA-02.1 de CA-01.3 en evidence/t-07.md | Revisar la matriz: la protección de todos los endpoints sigue pendiente de T-16 |
| SDD-03 / Artículo IX | Control de secretos de archivos versionados en CI, sin mostrar valores | Fixtures sintéticas temporales detectadas; checkout final y .env.example con valores vacíos pasan; documentar patrones y limitaciones |
| SDD-04 / Artículos I–II | Resolver imports y aliases, tipos y llamadas a funciones del núcleo | Imports simples/agrupados/cualificados, alias, tipos PDO y helpers externos prohibidos fallan; PHP nativo y dependencias internas permitidas pasan |
| SDD-04 / Artículo III | Detectar instanciación y bindings de adaptadores fuera de LibraryServiceProvider | new directo o por alias y binding fuera de composición fallan; dentro de composición pasa |
| SDD-09 / T-03 / Contrato §5.3 | Rechazar fechas con byte nulo como ValidationError | Probar from y to, conservar campo, formato YYYY-MM-DD, fechas reales, extremo inclusivo y rango invertido |

Los ejemplos prohibidos se alojan como fuentes sintéticas de pruebas, no como dependencias
reales de Domain/Application ni como secretos operativos. El control de secretos es una
búsqueda de patrones según artículo IX; no certifica ausencia de todo secreto posible.
La arquitectura se comprueba sobre el código PHP del repositorio; no se promete análisis
completo de programas generados dinámicamente ni composición todavía no implementada.

No se añaden rutas, tablas ni funciones de producto. JWT/Argon2id reales, autorización HTTP
y persistencia permanecen en sus tareas. SDD-10 (umbral exacto de portada) queda para T-08/T-15.
Los resultados ejecutados se registrarán después, con sus commits y enlaces a CI.
