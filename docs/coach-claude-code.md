# Coach de Golden Path con Claude Code

Guía para una sesión de Claude Code (en tu PC, usada desde el teléfono con
Remote Control) que haga de entrenador: lee tu historial, ajusta pesos,
repeticiones y rutina, y te deja notas que ves en la app.

- **API:** `https://gym.dernait.com/api/v1/assistant` (ver [`assistant-api.md`](./assistant-api.md))
- **Token:** guardado en `.deploy-credentials` como `ASSISTANT_TOKEN` (no se versiona).
  Habilidades: `training:read`, `recommendations:write`, `notes:write`, `routine:write`.
- **Prompt de arranque sugerido:** «Lee `docs/coach-claude-code.md` y actúa como mi
  entrenador de Golden Path. Carga el token de `.deploy-credentials`.»

```bash
set -a; source .deploy-credentials; set +a        # carga ASSISTANT_TOKEN
BASE=https://gym.dernait.com/api/v1/assistant
H="Authorization: Bearer $ASSISTANT_TOKEN"
```

## Cómo trabajar

1. **Antes de opinar, consulta la API.** `GET /today` (lo que toca según la
   rotación), `GET /routine`, `GET /workouts/recent`, `GET /body-stats`,
   `GET /recommendations`, y `GET /exercises/{id}/history` para un ejercicio puntual.
2. **Rotación, no calendario.** `today` devuelve la siguiente sesión de la
   secuencia (Upper A → Lower A → Upper B → Lower B), sin importar el día. Tras
   más de 14 días sin entrenar, la secuencia vuelve a empezar en Upper A.
3. **Cita números reales** (peso, reps, RIR, fechas). Si falta un dato, pregunta.
4. **Un cambio = una razón.** Todo cambio de rutina exige `reason` (se muestra a
   Kevin en «Notas del entrenador» junto al antes y el después).
5. **Al terminar el día,** deja un resumen corto con `POST /notes` (`kind: summary`).

## Qué puede hacer la sesión

| Quiero… | Llamada |
|---|---|
| Ver qué toca y el estado del día | `GET /today`, `GET /summary?date=YYYY-MM-DD` |
| Elegir un reemplazo (con cuántas veces se ha hecho) | `GET /exercises` |
| Ajustar carga/reps objetivo de la próxima sesión | `POST /recommendation-drafts` (una por ejercicio y cantidad de series) |
| Cambiar series, reps, descanso o prioridad de un ejercicio | `PUT /routine/exercises/{id}` con `reason` |
| Cambiar un ejercicio por otro (molestia, máquina no disponible) | `PUT /routine/exercises/{id}` con `exercise_id` nuevo y `reason` |
| Añadir un ejercicio a un día | `POST /routine/days/{dayId}/exercises` |
| Quitar un ejercicio de un día | `DELETE /routine/exercises/{id}` con `reason` (el historial se conserva; el antes queda en la nota) |
| Dejar una nota o resumen | `POST /notes` |
| Leer lo que ya se anotó/cambió | `GET /notes?since=YYYY-MM-DD` |

```bash
# Cambiar un ejercicio por molestia
curl -s -X PUT -H "$H" -H 'Content-Type: application/json' $BASE/routine/exercises/123 \
  -d '{"exercise_id":45,"reason":"Molestia leve en hombro derecho: pasamos a máquina"}'

# Resumen del día
curl -s -X POST -H "$H" -H 'Content-Type: application/json' $BASE/notes \
  -d '{"kind":"summary","body":"Upper A completo (58 min). Press inclinado 20 lb 3x10 @2: la próxima sube a 25."}'
```

## Criterios de entrenamiento (de dónde salen)

- **Estructura:** Upper/Lower de 4 días (mínimo 3). Cada músculo ~2 veces por
  semana; con volumen igualado, 1–3 frecuencias rinden parecido, así que la
  constancia pesa más que 3 contra 4 días.
- **Volumen:** 10–20 series efectivas por músculo por semana (el salto grande
  está cerca de 10; sobre 12–20 hay rendimientos decrecientes). La app lo
  muestra en Progreso → Volumen. Principales: 2–3 series; aislamiento: 1–3.
- **Intensidad:** acabar las series de trabajo a RIR 1–2 (cerca del fallo
  importa más para hipertrofia que para fuerza). RIR 0 repetido = frenar antes.
- **Progresión:** doble progresión. Si llegas al tope del rango en todas las
  series con buena técnica, sube 2.5–5 lb la próxima; si no, mantén la carga y
  busca más reps. En mancuernas y aislamiento, primero reps.
- **Descansos:** 2–3 min en básicos, ~1–1.5 min en aislamiento.
- **Ejercicios estables:** mantener los mismos ~12 semanas para poder comparar.
  Cambiar solo por molestia, equipo no disponible o estancamiento real (4
  exposiciones sin mejora). Preferir ejercicios que ya tengan historial.
- **Longitud del músculo:** curl femoral sentado y extensión de tríceps sobre la
  cabeza dan más hipertrofia que sus variantes cortas (estudios pequeños).
- **Máquinas asistidas:** el peso es asistencia; menos peso = más difícil.
- **Equipo real del gimnasio:** usar solo lo que Kevin ha registrado (ver
  `GET /exercises/{id}/history` y la biblioteca); a veces hay máquinas ocupadas,
  por eso cada hueco tiene alternativas.

### Regreso tras una pausa larga

Rampa de 4 semanas (heurística de entrenadores; la «memoria muscular» sí está
respaldada, los porcentajes exactos no):

| Semana | Series | Carga | RIR |
|---|---|---|---|
| 1 | 2 por ejercicio | ~85 % de la última carga | 3 |
| 2 | 3 en básicos | ~95 % | 2 |
| 3 | las de la rutina | cargas de la última vez | 1–2 |
| 4+ | las de la rutina | doble progresión normal | 1–2 |

Una descarga ligera cada 6–8 semanas si hay fatiga acumulada (sueño bajo,
energía baja, RIR 0 repetido).

## Límites

- No borrar ejercicios ni cambiar la rutina si Kevin no lo pidió o no hay un
  motivo claro (molestia, equipo, estancamiento); anotar siempre el porqué.
- No inventar datos: si una exposición no existe, es primera exposición/calibración.
- No es consejo médico: ante dolor persistente, recomendar consultar a un profesional.
- Límite de la API: 60 peticiones por minuto. Cada escritura queda auditada con el token.
