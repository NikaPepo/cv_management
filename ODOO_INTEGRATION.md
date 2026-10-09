# Odoo integration

This document describes the read-only integration between the CV
Management backend and an external Odoo instance.

The integration is **one-way**: the CV Management backend exposes
aggregated statistics over a per-Position API token, and the Odoo
addon (`odoo_addon/cv_management_bridge/`) pulls that data into
Odoo's own models. The CV Management database is never written to
from Odoo.

## 1. Endpoints

All endpoints are JSON. Authentication is session-based for the
token-management endpoints and `Authorization: Bearer <token>` for
the aggregates endpoint.

### Token management (recruiter / admin only)

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/api/positions/{id}/api-token` | Generate a token. Returns the plaintext `secret` exactly once. |
| `GET` | `/api/positions/{id}/api-token` | List tokens for the Position. Never returns the secret or hash. |
| `DELETE` | `/api/positions/{id}/api-token/{tokenId}` | Revoke a token. |

### Aggregates (Bearer auth)

```
GET /api/odoo/positions/aggregates
Authorization: Bearer pst_<68 hex chars>
```

Returns the Position metadata, per-attribute aggregations, and a
summary. The Position is determined exclusively by the token; no
`positionId` parameter is accepted.

Status codes:

- `200` — success, body has the aggregate response
- `401` `{"error":"invalid_token"}` — missing, malformed, unknown, or
  revoked token (all four cases return the same body to prevent
  enumeration)
- `500` — unexpected server error

## 2. Aggregate response shape

```json
{
  "position": {
    "id": 42,
    "title": "Senior PHP Developer",
    "company": "Acme",
    "level": "senior",
    "isPublic": true,
    "shortDescription": "...",
    "maxProjects": 5,
    "projectTagFilter": ["php", "symfony"]
  },
  "attributes": [
    {
      "attributeDefinitionId": 7,
      "name": "Years of experience",
      "dataType": "numeric",
      "required": false,
      "options": null,
      "aggregation": { "count": 87, "avg": 5.4, "min": 0.5, "max": 22.0 }
    }
  ],
  "summary": { "totalCandidates": 145 },
  "generatedAt": "2026-10-09T15:30:00+00:00"
}
```

`aggregation` is shaped per `dataType`:

| `dataType` | Shape |
|---|---|
| `numeric` | `{count, avg, min, max}` |
| `string`, `text` | `{count, topValues: [{value, count}], suppressedCount}` |
| `boolean` | `{count, trueCount, falseCount}` — counts below K=5 are returned as `null` |
| `one_of_many` | `{count, options: [{id, value, count}], suppressedCount}` |
| `date` | `{count, buckets: [{period, count}], suppressedCount}` (bucketed by year) |
| `period` | `{count, closedCount, openEndedCount, avgDays, minDays, maxDays}` (see Period contract below) |
| `image` | `{count, emptyCount}` (URLs are never returned) |

### Period contract

The current application flow and database schema both allow
`period_end < period_start` (no DTO validation, no DB CHECK
constraint). The aggregator handles this consistently:

- `count` — number of candidates who entered at least one date
  (`period_start IS NOT NULL OR period_end IS NOT NULL`). Inverted
  intervals count.
- `closedCount` — number of candidates who entered **both** dates.
  Inverted intervals count (they are still closed answers, just
  with a bad order).
- `openEndedCount` — number of candidates who entered only a start
  date (no end). Rows with only an end date are counted in `count`
  but in neither `closedCount` nor `openEndedCount`.
- `avgDays`, `minDays`, `maxDays` — duration statistics computed
  **only on intervals where `period_end >= period_start`**.
  Inverted intervals are excluded from duration statistics so the
  numbers stay non-negative and meaningful.

The contract is locked in by `testPeriodDurationStats` and
`testPeriodExcludesInvertedIntervalsFromDuration` in
`tests/Service/OdooAggregationServiceTest.php`.

## 3. Privacy

- **PII** (email, names, photo URLs, profile/cv/user IDs, individual
  values) is **never** returned. Tests assert this contractually.
- **K-anonymity** with `K=5`: text/option counts below 5 are removed
  and rolled into `suppressedCount`. Boolean true/false counts below
  5 are returned as `null`.
- **Candidate population** is **published CVs only** — the same
  population that `CvRepository::findPublishedForPosition` and the
  existing `GET /api/positions/{id}/cvs` use. Draft CVs are
  excluded.
- **Image URLs** are dropped on the server. Only `count` /
  `emptyCount` are returned.

## 4. Security

- Tokens are `pst_` + 32 random bytes (64 hex chars) — 256 bits of
  entropy.
- Only the SHA-256 hash is stored. The plaintext is returned exactly
  once and never persisted.
- Comparison uses the existing `hash_equals` pattern (same as
  `MESSENGER_CRON_TOKEN`).
- All four auth-failure modes (missing / malformed / unknown /
  revoked) return the same `401` body to prevent token enumeration.
- Token rows are deleted via `onDelete: CASCADE` when the Position
  is deleted.

## 5. Odoo addon

The Odoo addon lives in `odoo_addon/cv_management_bridge/` and is
a standalone Odoo 18 module. It depends only on `base` and uses
the standard library for HTTP — no extra Python packages.

### Install

```bash
# In your Odoo addons directory
cp -r odoo_addon/cv_management_bridge ./addons/
./odoo-bin -d yourdb -i cv_management_bridge
```

### Configure

1. In the CV Management frontend, open the Position editor and
   click **Generate token** in the new "API token (Odoo)" section.
   Copy the plaintext (you will not see it again).
2. In Odoo, open **Settings → CV Bridge** and paste:
   - **CV Management API base URL** (HTTPS in production)
   - **CV Management API token** (masked in the UI; never logged)
3. Open **CV Bridge → Import Position** and click **Import**. The
   wizard calls the Symfony endpoint and upserts an
   `imported.position` (matched by `external_id`).

### Re-import

Re-running the wizard updates the existing `imported.position` and
its `imported.position.attribute` rows. Attributes that the backend
no longer reports are deleted.

### Security in Odoo

- The token is stored as `ir.config_parameter` (`cv_bridge.api_token`).
- The Settings field uses `password=True` so the form masks it.
- The wizard refuses to send a bearer token over plain HTTP unless
  the host is `localhost` / `127.0.0.1` / `0.0.0.0`.
- The token is never logged.

## 6. Tests

```
# Backend
docker exec -e APP_ENV=test course_project-app-1 \
    vendor/bin/phpunit \
    tests/Service/PositionApiTokenServiceTest.php \
    tests/Service/OdooAggregationServiceTest.php \
    tests/Controller/PositionApiTokenControllerTest.php \
    tests/Controller/OdooApiControllerTest.php

# Frontend
docker exec course_project-node-1 npm run build
```

Last run: 39 new tests, 120 assertions, all green.
