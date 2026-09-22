# Golden Path Assistant API

Versioned, token-authenticated API so an AI assistant (a Claude Code session, a
ChatGPT custom GPT with Actions, or any HTTP client) can read the athlete's
training and body context and publish **progression recommendations** that
apply immediately inside Golden Path.

- **Base URL:** `https://gym.dernait.com/api/v1/assistant`
- **Auth:** `Authorization: Bearer <token>` (Sanctum personal access token)
- **Abilities:** `training:read` for every GET, `recommendations:write` for the recommendation POST
- **OpenAPI schema:** [`assistant-openapi.yaml`](./assistant-openapi.yaml)
- **Shareable copy:** [`assistant-api-shared.md`](./assistant-api-shared.md) — same guide without any
  credential handling, meant to be uploaded to an external assistant. Keep token
  administration (this file, section 1) private.

Everything is scoped to the token owner. The assistant is the **only** source of
recommendations (the automatic engine was retired): each recommendation it
publishes is applied at once and becomes the target load, repetition goal and
split of the next session for its exercise and set count. There is no
acceptance step. History is never modified. Applied recommendations show with an
"IA" badge on the dashboard, in "Progreso" and during training ("Objetivo de hoy").

---

## 1. Tokens

### Create a token (or replace an expired one)

Tokens expire (90 days by default). When the current one stops working the API
answers `401 {"message":"No autenticado."}`; mint a new one on the server:

```bash
ssh -p 17 root@167.233.42.11
cd /var/www/golden-path/current
compose=(docker compose \
  --env-file /var/www/golden-path/shared/.env \
  --env-file /var/www/golden-path/shared/deploy.env \
  -f docker-compose.production.yml)

# create: prints the plaintext token ONCE, copy it right away
"${compose[@]}" exec -T app php artisan assistant:token weekly-ai \
  --abilities=training:read,recommendations:write --expires=90
```

Useful variants:

```bash
# read-only token (cannot post drafts)
... php artisan assistant:token read-only --abilities=training:read --expires=30

# never expires (use sparingly)
... php artisan assistant:token long-lived --expires=0

# list active tokens (ids, abilities, last use, expiry)
... php artisan assistant:token --list

# revoke a token by id, for example if it leaked
... php artisan assistant:token --revoke=1
```

The plaintext token is shown only at creation; it is stored hashed. Treat it
like a password: it grants read access to personal training and body data.
Revoke and re-create if it is ever pasted somewhere public.

### Verify a token works

```bash
curl -s -H "Authorization: Bearer $TOKEN" https://gym.dernait.com/api/v1/assistant/today
```

---

## 2. Read endpoints (ability `training:read`)

```bash
TOKEN=...  # plaintext token
BASE=https://gym.dernait.com/api/v1/assistant

curl -s -H "Authorization: Bearer $TOKEN" $BASE/today
curl -s -H "Authorization: Bearer $TOKEN" $BASE/routine
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/workouts/recent?limit=5"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/workouts/recent?since=2026-07-01"
curl -s -H "Authorization: Bearer $TOKEN" $BASE/exercises/1/history
curl -s -H "Authorization: Bearer $TOKEN" $BASE/training-context
curl -s -H "Authorization: Bearer $TOKEN" $BASE/body-stats
curl -s -H "Authorization: Bearer $TOKEN" $BASE/recommendations
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/recommendations?status=all&limit=20"
```

| Endpoint | What it answers |
|---|---|
| `GET /today` | Today's day and, per planned exercise, its target, last performance and the recommendation in effect for that set count |
| `GET /routine` | The whole active routine: every slot with its set count and rep range, the alternatives allowed there, the stored goal per (exercise, set count) and `multi_set_exercises` (exercises planned at more than one set count) |
| `GET /workouts/recent` | Recent completed/partial sessions with the working sets of each exercise, the planned set count and, for alternatives, the planned exercise (`limit`, `since`) |
| `GET /exercises/{id}/history` | Per-exposure series for one exercise: day, planned set count, working sets, max weight, best set, volume, estimated 1RM, average RIR, records |
| `GET /training-context` | Profile, active training phase and latest body composition |
| `GET /body-stats` | Latest body composition plus the trend across measurements |
| `GET /recommendations` | Recommendations with their reasoning, `target_sets` and `source`. `status` defaults to `accepted` (the ones in effect) and accepts `all`, one state or several comma-separated (`accepted`, `modified`, `pending`, `ignored`, `superseded`); `limit` caps the list |

Weights are reported with their own unit (`lb` for lifting). Body composition
is in kilograms. Exercises trained as an alternative keep their own history.

---

## 3. Publishing recommendations (ability `recommendations:write`)

```bash
curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  $BASE/recommendation-drafts -d '{
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
  }'
```

Fields per draft:

| Field | Required | Notes |
|---|---|---|
| `exercise_id` | yes | Must belong to the athlete |
| `target_sets` | yes | Set count this recommendation is for (1-10). Must be a count the routine trains the exercise at: its own slots, or for an alternative the slot it is performed in. Send one draft per count |
| `recommendation_type` | yes | `calibrate`, `increase_weight`, `increase_repetitions`, `maintain`, `reduce_weight`, `increase_rest`, `possible_deload`, `manual_review` |
| `confidence` | yes | `low`, `medium`, `high` |
| `reason` | yes | Plain language, max 2000 chars. This is what the athlete reads while training |
| `suggested_weight` | no | Target load in the exercise's unit (0-2000) |
| `suggested_total_repetitions` | no | Goal across all working sets (0-5000) |
| `suggested_rep_distribution` | no | Ideal reps per set, e.g. `[10,10,9]`. Must sum to `suggested_total_repetitions` when both are sent; if sent alone it defines the total. With `target_sets` it needs exactly that many values |
| `data_period` | no | Window the advice is based on |
| `provider` / `model` | yes | Who produced it, recorded for audit |

Up to 50 per request. Each one is stored as `accepted` with
`metadata.source = "assistant"` and **applied immediately**: `suggested_weight`
becomes the target load, `suggested_total_repetitions` the rep goal (clamped to
the slot's range) and `suggested_rep_distribution` the ideal split shown during
training. Anything still pending for that exercise is superseded.

Targets are stored per **(exercise, number of sets)**, so the same movement
trained at three sets in one day and two in another keeps separate goals, and
only the routine slot planning that exercise at that set count is updated. An
exercise performed only as an alternative inherits the sets its slot plans.
A missing `target_sets`, or one the routine never uses for that exercise, is
rejected with `422` naming the valid counts.

### Notes that matter for good advice

- Assisted machines (assisted pull-ups) use the weight as **assistance**: a
  lower number is harder. Progress means reducing it.
- Some loads are logged as plates only (`0` = empty bar or sled).
- Recommend a consistent load across working sets so exposures stay
  comparable.
- The same exercise at different set counts is one movement: judge progress
  from all its exposures (per-set load, reps and RIR), then publish one
  recommendation per set count.

---

## 4. Using it from ChatGPT (custom GPT with Actions)

1. Create a GPT, open **Configure → Actions → Create new action**.
2. **Import** the schema from [`assistant-openapi.yaml`](./assistant-openapi.yaml)
   (paste its contents, or host it and use "Import from URL").
3. **Authentication → API Key**, auth type **Bearer**, and paste a token created
   as described above.
4. Paste the instructions from [`assistant-gpt-instructions.md`](./assistant-gpt-instructions.md)
   and upload [`assistant-api-shared.md`](./assistant-api-shared.md) as knowledge.

5. Test the action with "¿que me toca hoy?" before saving.

For a Claude Code session, just share the base URL, the token and this file.

---

## 5. Suggested weekly flow

1. The assistant reads `today`, `routine`, `workouts/recent`, `body-stats` and `recommendations`.
2. Athlete and assistant discuss the data; the assistant asks follow-ups.
3. The assistant POSTs one recommendation per exercise and set count to `recommendation-drafts`.
4. They apply at once; the athlete sees them as the targets of the next session.

---

## 6. Limits and errors

| Status | Meaning |
|---|---|
| `401` | Missing, expired or revoked token |
| `403` | The token lacks the required ability, or the resource is not the owner's |
| `422` | Validation error (unknown type, split that does not add up or does not match `target_sets`, missing `target_sets` or a count the routine does not use, unknown exercise) |
| `429` | Rate limit: 60 requests per minute |

Every recommendation write is recorded in an audit log with the token that made it.
