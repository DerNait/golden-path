# Propuesta futura: API para asistentes de IA

Estado: idea documentada, no implementada.

## Objetivo

Permitir que un asistente como ChatGPT consulte el contexto de entrenamiento de Golden Path y genere recomendaciones personalizadas basadas en datos reales, sin darle acceso directo e irrestricto a la base de datos.

La integracion deberia poder responder preguntas como:

- Que entrenamiento corresponde hoy.
- Que pesos, repeticiones, RIR y descansos se realizaron recientemente.
- Como ha evolucionado un ejercicio concreto.
- Que recomendaciones deterministas ya genero Golden Path.
- Que ajuste adicional propone la IA y por que.

## Enfoque recomendado

Crear una API externa, versionada y separada de la API de sesion utilizada por la SPA:

`/api/v1/assistant/...`

La primera version debe ser de solo lectura. Las operaciones de escritura se agregarian despues y siempre producirian borradores que el usuario debe revisar y aceptar dentro de Golden Path.

## Primera fase: consulta

Endpoints tentativos:

- `GET /api/v1/assistant/today`: rutina o entrenamiento correspondiente al dia actual.
- `GET /api/v1/assistant/workouts/recent`: sesiones recientes con filtros de fecha y limite.
- `GET /api/v1/assistant/exercises/{exercise}/history`: progreso de un ejercicio.
- `GET /api/v1/assistant/training-context`: perfil, fase, objetivos y reglas relevantes.
- `GET /api/v1/assistant/recommendations`: recomendaciones pendientes y sus fundamentos.

Las respuestas deben ser resumidas y especificas para el asistente. No deben exponer modelos completos, snapshots internos, credenciales ni datos que no sean necesarios para elaborar la respuesta.

## Segunda fase: recomendaciones personalizadas

Endpoint tentativo:

- `POST /api/v1/assistant/recommendation-drafts`

La IA enviaria una propuesta estructurada que incluya como minimo:

- Ejercicio o sesion afectada.
- Cambio propuesto de peso, repeticiones, series o descanso.
- Explicacion basada en datos observados.
- Nivel de confianza.
- Periodo de datos utilizado.
- Identificador del proveedor y modelo que genero la propuesta.

La propuesta se guardaria como borrador. No modificaria automaticamente la rutina, el historial ni los objetivos. El usuario podria aceptarla, editarla o descartarla usando un flujo equivalente al de las recomendaciones actuales.

## Conexion con ChatGPT

Existen dos alternativas principales:

### GPT personalizado con Actions

- Publicar la API mediante HTTPS.
- Describir los endpoints con un esquema OpenAPI.
- Configurar autenticacion mediante una API key o OAuth.
- Permitir que un GPT personalizado consulte los endpoints al responder.

Esta opcion permite conversar directamente desde ChatGPT, pero requiere que Golden Path este disponible desde Internet y que la superficie expuesta sea minima.

### Asistente integrado en Golden Path

- Golden Path llama a la API de OpenAI desde Laravel.
- La clave de OpenAI permanece exclusivamente en el servidor.
- Laravel entrega al modelo solamente el contexto necesario.
- Las herramientas disponibles para el modelo se controlan desde el backend.

Esta es la opcion recomendada para tener mayor control, auditoria y una experiencia integrada. La API externa podria conservarse para admitir otros asistentes en el futuro.

## Seguridad y control

- Usar tokens independientes de la sesion web, almacenados de forma segura, revocables y con vencimiento.
- Definir permisos separados, por ejemplo `training:read` y `recommendations:write`.
- Exigir HTTPS y aplicar limites de solicitudes.
- Validar propiedad, rangos y tipos de todos los datos recibidos.
- Registrar en auditoria cada consulta sensible y cada recomendacion creada.
- No entregar contrasenas, cookies, tokens internos ni acceso directo a tablas.
- No permitir inicialmente que la IA escriba entrenamientos realizados o reemplace rutinas.
- Tratar las recomendaciones como orientativas, no como diagnostico medico.

## Relacion con el motor actual

La IA no debe reemplazar las recomendaciones deterministas existentes. Debe recibir tanto sus resultados como las metricas que los justifican y funcionar como una capa complementaria para:

- Explicar las recomendaciones en lenguaje natural.
- Detectar patrones que involucren varias sesiones o ejercicios.
- Proponer alternativas sujetas a confirmacion.
- Responder preguntas sobre el historial personal.

Las reglas deterministas deben seguir siendo la fuente principal para ajustes automaticos y explicables.

## Orden de implementacion propuesto

1. Definir el contrato JSON y los datos estrictamente necesarios.
2. Crear autenticacion con tokens, permisos, vencimiento y revocacion.
3. Implementar endpoints de solo lectura y sus pruebas.
4. Publicar el esquema OpenAPI y probar una Action de ChatGPT.
5. Evaluar la utilidad y calidad de las respuestas con datos reales.
6. Agregar recomendaciones en borrador, auditoria y aprobacion manual.
7. Considerar un asistente conversacional dentro de Golden Path.

## Criterio de exito

La integracion sera util si el usuario puede preguntar por su entrenamiento actual o progreso, obtener una respuesta respaldada por sus datos y recibir propuestas claras sin que el asistente pueda modificar informacion permanente sin autorizacion explicita.
