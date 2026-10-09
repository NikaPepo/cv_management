CV Management — Odoo Bridge
============================

Read-only bridge that imports aggregated Position statistics from the
CV Management Symfony backend.

How it works
------------

1. In the CV Management frontend, open a Position you want to expose
   to Odoo and click "Generate token" in the API token section. The
   plaintext is shown exactly once; copy it.

2. In Odoo, open Settings → CV Bridge and paste:

   - **CV Management API base URL** — the Symfony frontend's public URL.
   - **CV Management API token** — the token from step 1.

3. Open CV Bridge → Import Position. The wizard calls
   ``GET /api/odoo/positions/aggregates`` with the token, parses the
   response, and creates (or updates) an ``imported.position`` plus
   its ``imported.position.attribute`` rows.

4. The wizard matches records by ``external_id`` (the Position ID on
   the Symfony side), so re-running the import on the same token
   updates the existing record rather than creating a duplicate.

5. Open CV Bridge → Imported Positions to browse the imported data.

What it never does
------------------

* It never writes to the Symfony database. The connection is one-way.
* It never creates or modifies Positions in Symfony.
* It never logs the bearer token.
* It never sends the token over plain HTTP unless the host is
  ``localhost`` / ``127.0.0.1`` / ``0.0.0.0`` (development).
* It never exposes individual candidate data: the backend returns
  aggregates, not profiles, and the addon stores them verbatim.

Security
--------

The token is stored in ``ir.config_parameter`` (``cv_bridge.api_token``)
and masked in the Settings form (``password=True`` on the field).
Production deployments must use HTTPS — the wizard refuses to send a
bearer token over plain HTTP to any non-loopback host.

Compatibility
-------------

This addon targets Odoo 18.0. It depends only on ``base`` and uses
the standard library (``urllib``) for HTTP, so no extra Python packages
are required.
