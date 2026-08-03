# Assistant API

Versioned, token-authenticated API so an AI assistant (a Claude Code session, a
ChatGPT custom GPT with Actions, etc.) can read your training and body context
and submit **recommendation drafts** that you review inside Golden Path.

- Base URL: `https://gym.dernait.com/api/v1/assistant`
- Auth: `Authorization: Bearer <token>` (Sanctum personal access token)
- Abilities: `training:read` (all GETs) and `recommendations:write` (draft POST)
- OpenAPI schema: [`assistant-openapi.yaml`](./assistant-openapi.yaml)

Everything is scoped to the token owner. The write endpoint only creates
**pending drafts** — it never changes your routine, history or goals. You accept,
modify or ignore each draft from the "Recomendaciones" screen (drafts show an
"IA" badge).

## Tokens

Tokens are minted server-side and are revocable with an expiry:

```bash
# create (prints the plaintext token once)
php artisan assistant:token weekly-ai --abilities=training:read,recommendations:write --expires=90
# list / revoke
php artisan assistant:token --list
php artisan assistant:token --revoke=<id>
```

## Read endpoints

```bash
TOKEN=... # the plaintext token
BASE=https://gym.dernait.com/api/v1/assistant

curl -s -H "Authorization: Bearer $TOKEN" $BASE/today
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/workouts/recent?limit=5"
curl -s -H "Authorization: Bearer $TOKEN" $BASE/exercises/1/history
curl -s -H "Authorization: Bearer $TOKEN" $BASE/training-context
curl -s -H "Authorization: Bearer $TOKEN" $BASE/body-stats
curl -s -H "Authorization: Bearer $TOKEN" $BASE/recommendations
```

## Submitting recommendation drafts

```bash
curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  $BASE/recommendation-drafts -d '{
    "drafts": [
      {
        "exercise_id": 1,
        "recommendation_type": "increase_weight",
        "confidence": "high",
        "reason": "Cerraste 3x10 con RIR 2 en la ultima exposicion; sube un escalon.",
        "suggested_weight": 75,
        "data_period": "ultimas 4 semanas",
        "provider": "anthropic",
        "model": "claude-opus-4-8"
      }
    ]
  }'
```

Each draft supersedes the current pending recommendation for that exercise and is
stored with `metadata.source = "assistant"`. When you accept it, `suggested_weight`
becomes the routine's target weight and `suggested_total_repetitions` its total-rep
goal — the same flow as the automatic engine.

## Suggested weekly flow

1. The assistant calls `today`, `workouts/recent`, `body-stats` and `recommendations`.
2. You discuss the data and it asks follow-ups.
3. It POSTs drafts to `recommendation-drafts`.
4. You review and accept/modify/ignore them in Golden Path.
