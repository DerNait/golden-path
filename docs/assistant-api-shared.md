# Golden Path Assistant API — guía para asistentes

> Copia pensada para cargar en un asistente externo (por ejemplo un GPT
> personalizado). No incluye administración de credenciales: la clave se
> configura en la autenticación de la Action y nunca debe pedirse ni escribirse
> en la conversación.

API versionada para leer el contexto de entrenamiento y composición corporal
del atleta y publicar **recomendaciones de progresión** que se aplican al
instante en Golden Path.

- **Base URL:** `https://gym.dernait.com/api/v1/assistant`
- **Auth:** `Authorization: Bearer <token>` (configurado en la Action)
- **Permisos:** `training:read` para las lecturas, `recommendations:write` para publicar recomendaciones

Todo está limitado al dueño del token. El asistente es la **única** fuente de
recomendaciones (el motor automático se retiró). Cada recomendación publicada
se aplica de inmediato: pasa a ser la carga, la meta de repeticiones y el
reparto por serie de la próxima sesión de ese ejercicio con esa cantidad de
series. No hay paso de aceptación. El historial nunca se modifica. En la app
aparecen con la etiqueta "IA" (también durante el entrenamiento, en "Objetivo
de hoy").

---

## 1. Lecturas (`training:read`)

| Endpoint | Qué responde |
|---|---|
| `GET /today` | El día de hoy y, por ejercicio planificado, su objetivo, la última ejecución y la recomendación vigente para esa cantidad de series |
| `GET /routine` | La rutina activa completa: cada hueco con sus series y rango de repeticiones, las alternativas permitidas, la meta guardada por (ejercicio, cantidad de series) y `multi_set_exercises` (ejercicios planificados con más de una cantidad de series) |
| `GET /workouts/recent` | Sesiones recientes con las series efectivas de cada ejercicio, las series que pedía la rutina (`planned_sets`) y, en alternativas, el ejercicio planificado. Parámetros: `limit` (1-50), `since` (fecha) |
| `GET /exercises/{id}/history` | Serie por exposición de un ejercicio: día, series planificadas, series efectivas, peso máximo, mejor serie, volumen, 1RM estimado, RIR promedio y records |
| `GET /training-context` | Perfil, fase de entrenamiento activa y composición corporal reciente |
| `GET /body-stats` | Última composición corporal y su tendencia |
| `GET /recommendations` | Recomendaciones con su razonamiento y `target_sets`. Parámetros: `status` (por defecto `accepted`, las vigentes; acepta `all`, un estado o varios separados por coma) y `limit` |

Estados posibles: `accepted`, `modified`, `pending`, `ignored`, `superseded`.
Las recomendaciones nuevas quedan siempre como `accepted`.

```bash
GET /today
GET /routine
GET /workouts/recent?limit=5
GET /workouts/recent?since=2026-07-01
GET /exercises/45/history
GET /training-context
GET /body-stats
GET /recommendations
GET /recommendations?status=all&limit=20
```

Las cargas de levantamiento vienen con su unidad (`lb`); la composición corporal
está en kilogramos. Un ejercicio hecho como alternativa conserva su propio
historial, separado del ejercicio que sustituye.

---

## 2. Publicar recomendaciones (`recommendations:write`)

`POST /recommendation-drafts`

```json
{
  "drafts": [
    {
      "exercise_id": 45,
      "target_sets": 3,
      "recommendation_type": "increase_repetitions",
      "confidence": "high",
      "reason": "Lower A: a 15 lb hiciste 6/8/8 con RIR 1. Manten la carga y busca 23 totales.",
      "suggested_total_repetitions": 23,
      "suggested_rep_distribution": [8, 8, 7],
      "data_period": "3 exposiciones (7-25 ago)",
      "provider": "openai",
      "model": "gpt-5"
    },
    {
      "exercise_id": 45,
      "target_sets": 2,
      "recommendation_type": "increase_repetitions",
      "confidence": "high",
      "reason": "Lower B: el mismo ejercicio a 2 series; con lo visto en Lower A busca 10/9 a 15 lb.",
      "suggested_total_repetitions": 19,
      "suggested_rep_distribution": [10, 9],
      "data_period": "3 exposiciones (7-25 ago)",
      "provider": "openai",
      "model": "gpt-5"
    }
  ]
}
```

| Campo | Obligatorio | Notas |
|---|---|---|
| `exercise_id` | sí | Debe pertenecer al atleta |
| `target_sets` | sí | Cantidad de series para la que es la recomendación (1-10). Debe ser una cantidad con la que la rutina entrena ese ejercicio: la de sus huecos o, en una alternativa, la del hueco donde se hace. Una recomendación por cantidad |
| `recommendation_type` | sí | `calibrate`, `increase_weight`, `increase_repetitions`, `maintain`, `reduce_weight`, `increase_rest`, `possible_deload`, `manual_review` |
| `confidence` | sí | `low`, `medium`, `high` |
| `reason` | sí | Lenguaje claro, máx. 2000 caracteres. Es lo que el atleta lee mientras entrena |
| `suggested_weight` | no | Carga objetivo en la unidad del ejercicio (0-2000) |
| `suggested_total_repetitions` | no | Meta sumando las series efectivas (0-5000) |
| `suggested_rep_distribution` | no | Reparto ideal por serie, p. ej. `[10,10,9]`. Debe sumar el total cuando se envían ambos; si va solo, define el total. Con `target_sets`, debe tener exactamente ese número de valores |
| `data_period` | no | Ventana de datos en que se basa el consejo |
| `provider` / `model` | sí | Quién generó la propuesta (queda auditado) |

Hasta 50 por petición. Cada una queda como `accepted` y **se aplica al
instante**: `suggested_weight` pasa a ser la carga objetivo,
`suggested_total_repetitions` la meta de repeticiones (ajustada al rango del
hueco) y `suggested_rep_distribution` el reparto ideal que se muestra durante
el entrenamiento.

Los objetivos se guardan por **(ejercicio, número de series)**: el mismo
movimiento a tres series un día y a dos en otro mantiene metas separadas, y
solo se actualiza el hueco de la rutina que planifica ese ejercicio con esa
cantidad de series. Si falta `target_sets` o no es una cantidad que la rutina
use para ese ejercicio, la API responde `422` indicando las cantidades válidas.

---

## 3. Cómo dar buenos consejos

- **Lee antes de opinar.** Consulta `today`, `routine`, `workouts/recent`,
  `body-stats` y `recommendations` antes de publicar nada.
- **Mismo ejercicio, distintas series.** Si un ejercicio aparece con distinta
  cantidad de series (en dos días de la rutina o como alternativa), es el mismo
  movimiento: evalúa su progreso con todas sus exposiciones (carga, repeticiones
  por serie y RIR) y publica una recomendación por cada cantidad de series.
- **Carga pareja.** Si el atleta "rampea" (40, luego 50), recomiéndale fijar
  una sola carga y marcar la ligera como calentamiento.
- **Máquinas asistidas** (dominadas asistidas): el peso es **asistencia**, menos
  peso es más difícil. Progresar significa bajar ese número.
- **Pesos en cero:** a veces se registran solo los discos (`0` = barra o trineo
  sin discos). No es un error.
- **RIR objetivo 1-2.** Si las últimas series terminan en RIR 0 de forma
  repetida, sugiere frenar antes en vez de subir carga.
- **Una recomendación por ejercicio y cantidad de series**, con el porqué
  apoyado en números reales (pesos, repeticiones y RIR de las últimas
  exposiciones).
- Las recomendaciones son orientativas y no sustituyen criterio médico ni
  de un entrenador presencial.

---

## 4. Flujo semanal sugerido

1. Leer `today`, `routine`, `workouts/recent`, `body-stats` y `recommendations`.
2. Conversar los datos con el atleta y preguntarle lo que falte.
3. Publicar una recomendación por ejercicio y cantidad de series en `recommendation-drafts`.
4. Se aplican al instante: son los objetivos de su próxima sesión.

---

## 5. Errores

| Código | Significado |
|---|---|
| `401` | Falta la credencial o ya no es válida (avísale al atleta; no intentes generar una) |
| `403` | Falta el permiso necesario, o el recurso no es del atleta |
| `422` | Error de validación (tipo desconocido, reparto que no suma o no coincide con `target_sets`, falta `target_sets` o no es una cantidad que la rutina use, ejercicio inexistente) |
| `429` | Límite de 60 peticiones por minuto |
