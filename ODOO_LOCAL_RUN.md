# Local Odoo 18 — CV Bridge

This file documents how to run the bundled Odoo 18 Community stack
alongside the existing Course Project, install the `cv_management_bridge`
addon, and exercise the import flow against the live Symfony API.

The Odoo stack is **additive**: it joins the existing `course_project_app`
Docker network and does not touch any of the existing services
(`app`, `nginx`, `postgres`, `node`).

## Prerequisites

- The main Course Project must be up (`docker compose up -d` from the
  repo root). The Odoo container reaches the Symfony API through
  the existing `nginx` service on the same network.
- Docker Engine with Compose v2.

## Start Odoo

From the repo root:

```bash
docker compose -f compose.odoo.yaml up -d
```

This starts two new services:

| Service     | Image                  | Port  | Persistent volume |
|-------------|------------------------|-------|-------------------|
| `odoo-db`   | `postgres:16-alpine`   | (internal) | `odoo-db-data` |
| `odoo-web`  | `odoo:18.0`            | `8069` | `odoo-web-data` |

The addon at `odoo_addon/cv_management_bridge/` is bind-mounted
read-only into `/mnt/extra-addons/cv_management_bridge` of the
`odoo-web` container.

The Odoo web container can reach the Symfony API at
`http://nginx:80` because both containers are attached to the
existing `course_project_app` network.

## Initial Odoo setup

On first launch, Odoo does not auto-create its `odoo` database. From
the host:

```bash
docker exec course_project-odoo-web-1 odoo -d odoo -i base --stop-after-init --no-http
```

Then restart the web container so the freshly-initialised database is
visible to the running process:

```bash
docker compose -f compose.odoo.yaml restart odoo-web
```

Open <http://localhost:8069> in a browser. The first screen will be
the **Login** screen (the database was auto-selected).

If the database field on the login screen does not show `odoo`:
**Settings → General Settings → Developer Tools → "Set/Change Database
Name"** is not needed; instead go to <http://localhost:8069/web/database/manager>
and either confirm `odoo` exists or create it.

> Default credentials for the `admin` user are set during the
> initial browser visit (`/web/database/manager` first time you go
> there, then Settings → Users). For a throwaway local DB you can
> accept whatever you enter.

## Install the addon

1. Activate **developer mode**: ⚙️ → General Settings → "Activate
   the developer mode" (bottom of the page).
2. **Apps** → **Update Apps List** (left sidebar) → **Update**.
3. Search for `CV Bridge` (or just `cv_management_bridge`).
4. Click **Install**.

After install, the main menu gains a **CV Bridge** section with three
submenus: Imported Positions, Import Position, Settings.

## Configure the API connection

1. In Odoo: **CV Bridge → Settings**.
2. Set:
   - **CV Management API base URL**: `http://nginx:80`
     (the internal Docker service name, only reachable from the
     Odoo container).
   - **CV Management API token**: paste the `pst_…` token issued
     from the Symfony side.
3. **Save**.

> The token is stored as an `ir.config_parameter` and masked in the
> form. It is never logged.

## Generate a token on the Symfony side

The token is generated through the existing API. From a shell on the
Course Project host:

```bash
# Login as recruiter/admin first, then call:
curl -sS -X POST http://localhost:8080/api/positions/3/api-token \
  -H "Content-Type: application/json" \
  -H "Origin: http://localhost:5173" \
  -b cookies.txt \
  -d '{"label":"Odoo smoke test"}'
```

The response contains `secret: "pst_..."` (one-time). Copy the
`pst_...` value into the Odoo Settings field.

The CLI alternative (works without a session) — generates a token
directly via Doctrine:

```bash
docker exec course_project-app-1 php -r '
require "vendor/autoload.php";
$_SERVER["APP_ENV"] = "dev";
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env");
$kernel = new App\Kernel("dev", false); $kernel->boot();
$em = $kernel->getContainer()->get("doctrine.orm.entity_manager");
$pos = $em->getRepository(App\Entity\Position::class)->find(3);
$secret = "pst_" . bin2hex(random_bytes(32));
$t = new App\Entity\PositionApiToken();
$t->setPosition($pos);
$t->setTokenHash(hash("sha256", $secret));
$t->setPrefix(substr($secret, 0, 8));
$t->setLabel("smoke-test");
$em->persist($t); $em->flush();
echo $secret;
'
```

## Run an import

In Odoo: **CV Bridge → Import Position → Import**.

A successful import opens the freshly-imported Position form. To
verify without a browser, run from the Odoo host:

```bash
docker exec course_project-odoo-web-1 odoo shell -d odoo --no-http <<'PY'
env["ir.config_parameter"].sudo().set_param("cv_bridge.api_url", "http://nginx:80")
env["ir.config_parameter"].sudo().set_param(
    "cv_bridge.api_token", "pst_...")
wizard = env["imported.position.wizard"].sudo().create({})
print(wizard.action_import())
env.cr.commit()
PY
```

A second import of the same token is **idempotent**: it updates the
existing row by `external_id`, no duplicates are created.

## Useful commands

```bash
# Odoo logs
docker logs -f course_project-odoo-web-1

# Odoo shell (Python REPL with `env` available)
docker exec -it course_project-odoo-web-1 odoo shell -d odoo

# Reset the Odoo addon (clears settings, re-runs setup)
docker exec course_project-odoo-web-1 odoo -d odoo -u cv_management_bridge --stop-after-init --no-http

# Stop Odoo (does NOT touch the main stack)
docker compose -f compose.odoo.yaml down

# Start Odoo again (volumes preserved)
docker compose -f compose.odoo.yaml up -d

# Wipe the Odoo test database — IRREVERSIBLE. Only use during local development.
docker compose -f compose.odoo.yaml down -v
docker volume rm course_project_odoo-db-data course_project_odoo-web-data
docker compose -f compose.odoo.yaml up -d
docker exec course_project-odoo-web-1 odoo -d odoo -i base --stop-after-init --no-http
```

## Architecture

```
+-------------------+       +--------------------+       +-------------------+
| Symfony backend   |       | Odoo 18            |       | Odoo DB           |
| (course_project-  |<----->| (course_project-   |<----->| (course_project-  |
|  app / nginx)     |  HTTP |  odoo-web)         |  SQL  |  odoo-db)         |
|  port 8080        |  GET  |  port 8069         |       |  internal         |
| /api/odoo/...     |       | cv_management_     |       |  Postgres 16      |
|                   |       |  bridge addon      |       |                   |
+-------------------+       +--------------------+       +-------------------+
        ^                            ^
        |                            |
        |        shared Docker network
        |       (course_project_app)
        |
   Host: localhost:8080        Host: localhost:8069
```

The Odoo container does **not** connect to the Symfony PostgreSQL
database. The two services are isolated; communication is
HTTP/JSON only.
