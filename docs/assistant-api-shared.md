# Golden Path Assistant API — guía para asistentes

> Copia pensada para cargar en un asistente externo (por ejemplo un GPT
> personalizado). No incluye administración de credenciales: la clave se
> configura en la autenticación de la Action y nunca debe pedirse ni escribirse
> en la conversación.

API versionada para leer el contexto de entrenamiento y composición corporal
del atleta y proponer **borradores de recomendación** que él revisa dentro de
Golden Path.

- **Base URL:** `https://gym.dernait.com/api/v1/assistant`
- **Auth:** `Authorization: Bearer <token>` (configurado en la Action)
- **Permisos:** `training:read` para las lecturas, `recommendations:write` para publicar borradores

Todo está limitado al dueño del token. El endpoint de escritura solo crea
**borradores pendientes**: nunca modifica la rutina, el historial ni las metas.
El atleta los acepta, modifica o ignora en la app, donde aparecen con la
etiqueta "IA" (también durante el entrenamiento, en "Objetivo de hoy").

---

## 1. Lecturas (`training:read`)

| Endpoint | Qué responde |
|---|---|
| `GET /today` | El día de hoy y, por ejercicio planificado, su objetivo, la última ejecución y la recomendación pendiente |
| `GET /workouts/recent` | Sesiones recientes con las series efectivas de cada ejercicio. Parámetros: `limit` (1-50), `since` (fecha) |
| `GET /exercises/{id}/history` | Serie por exposición de un ejercicio: peso máximo, mejor serie, volumen, 1RM estimado, RIR promedio y records |
| `GET /training-context` | Perfil, fase de entrenamiento activa y composición corporal reciente |
| `GET /body-stats` | Última composición corporal y su tendencia |
| `GET /recommendations` | Recomendaciones con su razonamiento. Parámetros: `status` (por defecto `pending`; acepta `all`, un estado o varios separados por coma) y `limit` |

Estados posibles: `pending`, `accepted`, `modified`, `ignored`, `superseded`.
Consultar `status=accepted` sirve para saber qué se aplicó en semanas previas.

```bash
GET /today
GET /workouts/recent?limit=5
GET /workouts/recent?since=2026-07-01
GET /exercises/22/history
GET /training-context
GET /body-stats
GET /recommendations
GET /recommendations?status=accepted&limit=20
GET /recommendations?status=pending,accepted
```

Las cargas de levantamiento vienen con su unidad (`lb`); la composición corporal
está en kilogramos. Un ejercicio hecho como alternativa conserva su propio
historial, separado del ejercicio que sustituye.

---

## 2. Publicar borradores (`recommendations:write`)

`POST /recommendation-drafts`

```json
{
  "drafts": [
    {
      "exercise_id": 22,
      "recommendation_type": "increase_repetitions",
      "confidence": "high",
      "reason": "A 80 lb cerraste 10/10/9 con RIR 1. Manten la carga y busca 30 totales.",
      "suggested_total_repetitions": 30,
      "suggested_rep_distribution": [10, 10, 10],
      "data_period": "3 exposiciones (15-29 jul)",
      "provider": "openai",
      "model": "gpt-5"
    }
  ]
}
```

| Campo | Obligatorio | Notas |
|---|---|---|
| `exercise_id` | sí | Debe pertenecer al atleta |
| `recommendation_type` | sí | `calibrate`, `increase_weight`, `increase_repetitions`, `maintain`, `reduce_weight`, `increase_rest`, `possible_deload`, `manual_review` |
| `confidence` | sí | `low`, `medium`, `high` |
| `reason` | sí | Lenguaje claro, máx. 2000 caracteres. Es lo que el atleta lee mientras entrena |
| `suggested_weight` | no | Carga objetivo en la unidad del ejercicio (0-2000) |
| `suggested_total_repetitions` | no | Meta sumando las series efectivas (0-5000) |
| `suggested_rep_distribution` | no | Reparto ideal por serie, p. ej. `[10,10,9]`. Debe sumar el total cuando se envían ambos; si va solo, define el total |
| `data_period` | no | Ventana de datos en que se basa el consejo |
| `provider` / `model` | sí | Quién generó la propuesta (queda auditado) |

Hasta 50 borradores por petición. Cada uno **reemplaza** la recomendación
pendiente de ese ejercicio y queda como pendiente. Al aceptarse,
`suggested_weight` pasa a ser la carga objetivo, `suggested_total_repetitions`
la meta de repeticiones y `suggested_rep_distribution` el reparto ideal que se
muestra como badge durante el entrenamiento.

Los objetivos se guardan por **(ejercicio, número de series)**: el mismo
movimiento a tres series un día y a dos en otro mantiene metas separadas.

---

## 3. Cómo dar buenos consejos

- **Lee antes de opinar.** Consulta `today`, `workouts/recent`, `body-stats` y
  `recommendations` (incluido `status=accepted`) antes de proponer nada.
- **Carga pareja.** El motor determinista solo progresa automáticamente si las
  series efectivas comparten peso y unidad; si el atleta "rampea" (40, luego
  50), recomiéndale fijar una sola carga y marcar la ligera como calentamiento.
- **Máquinas asistidas** (dominadas asistidas): el peso es **asistencia**, menos
  peso es más difícil. Progresar significa bajar ese número.
- **Pesos en cero:** a veces se registran solo los discos (`0` = barra o trineo
  sin discos). No es un error.
- **RIR objetivo 1-2.** Si las últimas series terminan en RIR 0 de forma
  repetida, sugiere frenar antes en vez de subir carga.
- **Un borrador por ejercicio**, con el porqué apoyado en números reales
  (pesos, repeticiones y RIR de las últimas exposiciones).
- Las recomendaciones son orientativas y no sustituyen criterio médico ni
  de un entrenador presencial.

---

## 4. Flujo semanal sugerido

1. Leer `today`, `workouts/recent`, `body-stats` y `recommendations`.
2. Conversar los datos con el atleta y preguntarle lo que falte.
3. Publicar un borrador por ejercicio en `recommendation-drafts`.
4. El atleta revisa y acepta, modifica o ignora cada uno en Golden Path.

Nunca afirmes que la rutina cambió: los borradores requieren su aprobación.

---

## 5. Errores

| Código | Significado |
|---|---|
| `401` | Falta la credencial o ya no es válida (avísale al atleta; no intentes generar una) |
| `403` | Falta el permiso necesario, o el recurso no es del atleta |
| `422` | Error de validación (tipo desconocido, reparto que no suma, ejercicio inexistente) |
| `429` | Límite de 60 peticiones por minuto |
