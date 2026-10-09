# Odoo integration — production deployment on Render

This document describes how to deploy the existing Odoo 18 integration
(`odoo_addon/cv_management_bridge/`) to Render as a separate web
service, alongside the existing CV Management (Symfony + React) app.

The Symfony / React app is **not** redeployed by this document. It is
assumed to already exist on Render under a stable public HTTPS URL.
This document only adds the Odoo side of the bridge.

---

## 1. Architecture

```
        ┌──────────────────────────────┐
        │   Existing CV Management      │
        │   Symfony + React on Render   │
        │   https://cv-app.onrender.com │
        │                              │
        │   PostgreSQL 18 (managed)    │
        └──────────────┬───────────────┘
                       │  HTTPS
                       │  GET /api/odoo/positions/aggregates
                       │  Authorization: Bearer pst_...
                       ▼
        ┌──────────────────────────────┐
        │   Odoo 18 Bridge on Render    │
        │   cv-odoo-web                 │
        │   https://cv-odoo.onrender.com│
        │                              │
        │   addon: cv_management_bridge│
        └──────────────┬───────────────┘
                       │  Render private network
                       │  TCP 5432
                       ▼
        ┌──────────────────────────────┐
        │   PostgreSQL 16 (Render      │
        │   managed, plan: starter)    │
        │   cv-odoo-db                 │
        └──────────────────────────────┘
```

Two Render services, one Render database, one Render persistent disk.
No shared filesystem, no shared container.

---

## 2. Repository layout for deployment

```
docker/
  php/Dockerfile        — local dev only (NOT used in production)
  nginx/production.conf — Symfony/React production nginx
  odoo/Dockerfile       — production Odoo 18 image (used in production)
odoo_addon/
  cv_management_bridge/ — the addon (baked into the Odoo image)
```

The Odoo Dockerfile lives in `docker/odoo/Dockerfile` and is built
with the **repository root** as build context:

```bash
docker build -f docker/odoo/Dockerfile -t cv-odoo .
```

The Render Web Service is configured the same way: same repo, same
branch, Root Directory empty, Dockerfile Path `docker/odoo/Dockerfile`.
No other files in the repo are copied into the Odoo image — only
the addon is.

The repository no longer ships a `render.yaml` — deployment is
performed manually through the Render Dashboard (see §3.3).

---

## 3. First-time Render setup

### 3.1. What the deployer does manually, in order

1. **Confirm the existing CV Management app is deployed and reachable.**
   You need its public HTTPS URL (e.g. `https://cv-management-app.onrender.com`).
   Do not proceed with Odoo deployment until this works end-to-end.

2. **Create a `starter` Render PostgreSQL** named `cv-odoo-db`,
   PostgreSQL 16. Render's `starter` plan includes automated daily
   backups for 7 days. Note its **Internal Database URL** (Render
   will provide one) — you will need it for the Web Service.

3. **Create a new Web Service manually** in the Render Dashboard:

   | Field | Value |
   |---|---|
   | Region | Same as the existing Symfony/React service (Oregon) |
   | Branch | Same as the existing service (e.g. `main`) |
   | Root Directory | (empty — build context is the repo root) |
   | Language | `Docker` |
   | Dockerfile Path | `docker/odoo/Dockerfile` |
   | Instance Type | `Starter` (or higher for more CPU/memory) |
   | Port | `8069` (matches `EXPOSE 8069` in the Dockerfile) |
   | Health Check Path | `/web/health` |

   Render will build the image using `docker build -f docker/odoo/Dockerfile .`.

4. **Set the secret environment variables** on `cv-odoo-web`. None of
   these are committed to the repository:

   | Key | Value |
   |---|---|
   | `ODOO_API_URL` | `https://cv-management-app.onrender.com` (no trailing slash) |
   | `ODOO_API_TOKEN` | the `pst_...` token from the Position editor (see §4) |
   | `ODOO_ADMIN_PASSWORD` | a strong password you'll use once to log in |

   Use Render's **Secret Files** feature for these. Do not paste
   them into the regular environment variables editor — Render logs
   env changes; the secret-files store is not echoed in the same way.

5. **Wait for the first deploy to finish** (~3–5 min). Render assigns
   `cv-odoo-web.onrender.com` (or your custom domain).

6. **Open `https://cv-odoo.onrender.com/web/database/manager`** in a
   browser. The Odoo image's entrypoint creates the `odoo` database
   on first boot; you only need to create it if it doesn't already
   exist. The initial master password for the admin user is the
   value of `ODOO_ADMIN_PASSWORD` set in step 4.

7. **Log in** as `admin` / `<ODOO_ADMIN_PASSWORD>`. The Odoo home
   page appears.

8. **Activate developer mode** (⚙️ → General Settings → bottom of
   page → "Activate the developer mode").

9. **Update Apps list** (Apps → Update Apps List → Update).

10. **Install the addon** (search "CV Bridge" → Install).

11. **Configure the bridge** (CV Bridge → Settings):
    - CV Management API base URL: `<ODOO_API_URL>` (no trailing
      slash, e.g. `https://cv-management-app.onrender.com`).
    - CV Management API token: the `pst_...` token from §4.
    - Save.

12. **Verify the import** (CV Bridge → Import Position → Import).
    The Position form should open with the aggregated data.

---

## 4. Production API token procedure

The bearer token is **generated once on the source app side** and
**copied manually** into Render's environment for the Odoo service.
It is never auto-generated, never logged, never committed to Git.

### 4.1. Generate on the source app

1. Log into the CV Management app as recruiter or admin.
2. Open the Position you want to expose to Odoo → Edit.
3. Scroll to "API token (Odoo)" section.
4. Enter a label (e.g. "Odoo production") → **Generate token**.
5. The plaintext secret (format `pst_<64 hex>`) is shown ONCE.
6. Copy it to your password manager. Do not paste it anywhere
   that will be logged or committed.

### 4.2. Store on Render

1. Open Render dashboard → `cv-odoo-web` → Environment → Secret Files.
2. Add a secret file with key `ODOO_API_TOKEN` and the copied value.
3. Render's env-var editor does not echo values back; once saved,
   you must re-paste to change. This is intentional.

### 4.3. Revoke / rotate

1. Log into the CV Management app → Position → Edit.
2. Find the token in the API token section → **Revoke**.
3. The next Odoo import attempt returns 401. The Position is still
   in Odoo's database but the import fails.
4. **Generate a new token** in the source app.
5. Update the `ODOO_API_TOKEN` secret file on Render.
6. Redeploy `cv-odoo-web`. (Env-var changes on Render require a
   manual redeploy, or "Clear build cache & deploy" if the secret
   file is cached.)

---

## 5. Addon install & update

### 5.1. First install

The addon ships baked into the Odoo Docker image. The first time
`cv-odoo-web` starts, Odoo's entrypoint creates the `odoo` database
(if it doesn't exist) and then starts the Odoo process. The addon
**is not** auto-installed. The user (you) installs it once via the
Odoo UI as described in §3, steps 8–10.

### 5.2. Subsequent restarts / redeploys

When Render redeploys the service (image rebuild or env-var change),
the new container starts. The existing PostgreSQL data and the
persistent disk at `/var/lib/odoo` survive — the addon is already
installed in the database, so no manual re-install is needed.

The Odoo entrypoint **does not** auto-install or auto-update the
addon on boot. This is intentional:
- It means a bad addon version cannot brick a production DB
  automatically.
- It means an image rollback does NOT auto-downgrade the DB schema
  (you'd need a manual downgrade via Odoo shell if you truly need
  that).
- The image and the DB evolve independently.

### 5.3. Updating the addon

When you change addon code (Python / XML / `__manifest__.py`):

1. Commit and push the change.
2. Render auto-rebuilds the image with the new addon version.
3. On first request after deploy, the addon is reloaded. **The
   database schema does not change** (the addon's models have no
   `upgrade` script).
4. Verify by going to Settings → Apps → Installed Apps → "CV Bridge"
   should show the new version.

If the change requires a database schema change (adding a field to
an existing model), you must:

1. Add the field to the Python model.
2. Either ship a migration script under `migrations/` of the addon
   (preferred), or run a one-off `odoo shell` command to ALTER
   TABLE manually. Document the manual step in your release notes.

The current addon (v1.0.0) has no schema changes planned beyond
what's already in production. Schema migrations are not required
for routine addon updates.

---

## 6. Persistence

| Data | Where | How Render keeps it |
|---|---|---|
| Odoo PostgreSQL data | Render `pserv` cv-odoo-db | Managed by Render; 7-day automated backups on `starter` plan |
| Odoo filestore (uploaded files, attachment metadata) | Render persistent disk `cv-odoo-data`, mounted at `/var/lib/odoo` | Survives redeploys; destroyed on service deletion |
| Configuration (`cv_bridge.api_url`, `cv_bridge.api_token`) | Render secret files / env vars | Survives redeploys; you can rotate via the dashboard |
| The addon files in the image | Docker image layer | Rebuilt on every deploy; the addon is always the version in the latest `main` |

### Backup procedure

- **PostgreSQL**: Render → `cv-odoo-db` → Backups → manual snapshot
  before risky operations.
- **Filestore**: from a Render shell session: `tar czf - /var/lib/odoo`
  streamed to S3 or your own backup target.
- **Restoring**: use Render's "Restore from backup" for the DB. For
  the disk, create a new service, mount the disk, restore files.

### What happens if Render deletes the disk

If you delete `cv-odoo-web` and recreate it, the persistent disk
is gone. The PostgreSQL data and addon install on the DB side
**survive**, but the Odoo filestore (any user-uploaded files) is
lost. The addon has no file uploads — it only stores aggregated
JSON in `ir_config_parameter` and database rows — so a disk loss
is non-fatal for this addon.

---

## 7. Health checks

The Odoo Dockerfile declares:

```dockerfile
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8069/web/health || exit 1
```

Render's `healthCheckPath: /web/health` (configured in the Web
Service dashboard) uses the same endpoint externally. Render hits
it every 30s after a 60s grace period; 3 consecutive failures mark
the deploy as failed and roll back.

A successful `/web/health` proves:
- The Odoo process is up
- The HTTP listener is up
- The Odoo image is healthy

It does **NOT** prove:
- The addon is installed (verify via Settings → Apps → Installed
  Apps).
- The token works (verify via CV Bridge → Import Position →
  Import).
- The Symfony API is reachable from Odoo (verify via CV Bridge →
  Import Position after `ODOO_API_URL` is set).

For a true end-to-end check, run the import wizard manually once
after deploy.

---

## 8. Security

| Concern | Status |
|---|---|
| PostgreSQL publicly accessible | No — Render `pserv` with `ipAllowList: []`. Only same-network services can connect. |
| Odoo web service public HTTPS | Yes — Render provisions a managed TLS certificate. |
| Odoo XML-RPC / netrpc ports | Not exposed. Env vars `ODOO_XMLRPC_PORT` etc. left empty so the Odoo image does not open them. |
| Production token in image | Not present. The token is in `ODOO_API_TOKEN` env var, supplied at runtime. |
| Production token in Git | Not present. The only token in the repo is the placeholder `pst_...` mentioned in the Odoo wizard's `UserError` message — it's an example string, not a real token. |
| Token in Odoo DB | Yes — stored in `ir_config_parameter` in plaintext (same as local). Masked in the UI form via `password="True"`. Anyone with DB access (you, the addon code) can read it. |
| `debug` mode in production | Odoo's `--dev=all` is **not** set. `log_level = info` is the default. |
| HTTP-only API URL in production | The wizard allows plain HTTP for loopback / RFC 1918 / single-label hostnames; **HTTPS is enforced** for any other host. The `ODOO_API_URL` is a public Render URL, so HTTPS is required. |
| Symfony `trusted_hosts` | The Symfony service's `SYMFONY_TRUSTED_HOSTS` must include the public host of the Odoo service (or be `^(.*)$`). If the Odoo wizard uses the public URL, this is already covered. |

---

## 9. Operations

### 9.1. View logs

Render dashboard → `cv-odoo-web` → Logs. Search for the Odoo
process output and the entrypoint script output.

The Odoo process logs to stdout. Look for:
- `odoo.modules.loading: Module cv_management_bridge loaded`
  → addon is registered
- `werkzeug: ... 401 -`
  → the bridge token is missing / invalid / revoked
- `werkzeug: ... 200 -`
  → a successful import attempt

The token itself is never logged. The wizard reads it from
`ir_config_parameter` and never echoes it.

### 9.2. Shell access

Render dashboard → `cv-odoo-web` → Shell. You get a root shell in
the running container. Useful commands:

```bash
# Inspect addon presence
ls -la /mnt/extra-addons/cv_management_bridge/

# Inspect token (reveals the secret — never log this)
odoo shell -d odoo --no-http <<'PY'
print(env['ir.config_parameter'].sudo().get_param('cv_bridge.api_token'))
PY

# Manual addon reinstall (rarely needed)
odoo shell -d odoo --no-http <<'PY'
env['ir.module.module'].sudo().search([('name', '=', 'cv_management_bridge')]).button_immediate_install()
env.cr.commit()
PY
```

### 9.3. Redeploy

- **Code change**: push to `main` → Render auto-rebuilds and
  redeploys.
- **Env var change** (e.g. rotating the token): update in the
  dashboard, then "Manual Deploy → Clear build cache & deploy" to
  ensure the new env var is picked up.

---

## 10. Limitations & risks

- **Manual first install**: the addon is not auto-installed. If you
  spin up a fresh DB, you must install via the UI once. There is
  no "add this and run" CLI on Render.
- **Token in Odoo DB**: the production token is stored in plaintext
  in `ir_config_parameter` in Odoo's DB. Anyone with DB access can
  read it. This is the same model as the local development. If you
  need encryption at rest, you need an Odoo module that wraps
  `ir_config_parameter` with a KMS — out of scope for this
  deployment.
- **No CI**: this repo has no CI that builds the Odoo image on
  PR. A syntax error in the addon only surfaces on deploy. For
  production, consider adding a CI job that runs `odoo -d testdb
  -i cv_management_bridge --stop-after-init` to catch import
  errors before deploy.
- **No Render private networking**: the Odoo service uses public
  HTTPS to reach the Symfony app. Render Private Networking could
  in theory be used, but it requires both services to be in the
  same Render team + region + VPC and the Symfony service to be
  re-deployed inside the VPC. The Odoo service uses the public
  URL.

---

## 11. Verification status (per task instructions)

| Step | Status | Notes |
|---|---|---|
| Local Docker integration | **PASS** | `compose.odoo.yaml` works; import smoke-tested against the running Symfony app. |
| Docker image build | **PASS** | `docker build -f docker/odoo/Dockerfile -t cv-odoo .` builds cleanly; addon present at `/mnt/extra-addons/cv_management_bridge/`; runs as `odoo` (uid 100). |
| Render deployment | **NOT TESTED** | Render infrastructure not accessible from this environment. The Dockerfile is the same one used locally; deployment is performed manually via the Render Dashboard as described in §3. |
| Production Odoo import | **NOT TESTED** | Requires the Render services to exist. The integration is verified to work locally; production behavior is inferred from the same code paths. |
