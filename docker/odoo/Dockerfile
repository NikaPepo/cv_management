# syntax=docker/dockerfile:1.7
# ============================================================
# Production image for the Odoo 18 CV Bridge.
#
# This image is intended for the Render "Odoo Bridge" web service.
# It is independent of the main Symfony / React application —
# the two services are deployed as separate Render services and
# communicate over HTTPS (Odoo calls the Symfony API with a Bearer
# token).
#
# Build context:  the repository root.
# Build command:  docker build -f docker/odoo/Dockerfile -t cv-odoo .
# Run (local):    docker run --rm -p 8069:8069 \
#                    -e PGHOST=... -e PGUSER=odoo -e PGPASSWORD=... \
#                    -e ODOO_API_URL=https://... -e ODOO_API_TOKEN=pst_... \
#                    cv-odoo
#
# What goes INTO the image:
#   - The odoo:18.0 base image (Debian, odoo user, official entrypoint).
#   - The cv_management_bridge addon, copied to the standard
#     /mnt/extra-addons/ directory. Render doesn't have a
#     "post-deploy install" step, so the addon must be present in
#     the image at boot time.
#
# What does NOT go in:
#   - .env, .env.local, secrets, tokens.
#   - The Symfony / React source tree, the main project's
#     docker/ directory, node_modules, vendor/.
# ============================================================


# ---- Stage 1: copy the addon to a clean layer ----
# We use a separate builder stage to keep the final image lean
# (it inherits only /mnt/extra-addons, not a full build context).
# This also lets Render's build cache reuse the addon when only
# unrelated files in the repo change.
FROM alpine:3.20 AS addon-builder

# COPY the addon and ONLY the addon — nothing else from the
# repository makes it into this stage. The trailing slash on the
# source path keeps the destination as a directory.
COPY odoo_addon/cv_management_bridge/ /tmp/cv_management_bridge/


# ---- Stage 2: production runtime ----
# Pin the Odoo minor version. :18.0 floats to the latest 18.0
# patch release, which is what Render wants for automatic security
# updates, while keeping us on a known major.
FROM odoo:18.0

# Verify the base image's user and the standard addon path.
# The image ships with a non-root `odoo` user (uid 100) and a
# writable /var/lib/odoo for the filestore.
#   - /mnt/extra-addons is the documented location for custom
#     addons; Odoo scans it on startup.
#   - /var/lib/odoo is where Odoo keeps the filestore, addons
#     history, and session data. Render mounts a persistent disk
#     here.

# Drop any pre-existing /mnt/extra-addons contents (the base image
# is empty there, but make this explicit so a future base-image
# change doesn't accidentally carry stale addons).
RUN rm -rf /mnt/extra-addons/*

# Copy the addon from the builder stage. --chown ensures the
# running `odoo` user (uid 100) can read everything without the
# image needing to chmod at boot.
COPY --from=addon-builder --chown=odoo:odoo /tmp/cv_management_bridge /mnt/extra-addons/cv_management_bridge

# Make sure the standard Odoo locations are writable by the
# `odoo` user, in case a mounted persistent disk has restrictive
# permissions at first attach.
RUN chown -R odoo:odoo /var/lib/odoo /mnt/extra-addons

# Render's Web Service UI asks for the container port the app
# listens on. Odoo's default config uses 8069. We expose that
# port; in the Render dashboard the Web Service must be configured
# with Port = 8069 (or any free port Render will proxy to 8069).
EXPOSE 8069

# The base image already declares USER odoo and an entrypoint
# that:
#   1. waits for the database (via PGHOST/PGUSER/PGPASSWORD env),
#   2. creates the database if it doesn't exist (only on first
#      boot, controlled by ODOO_DB_AUTO_CREATE), and
#   3. starts the Odoo process.
# We do not override it.
#
# Note: the Odoo image's entrypoint does NOT install or update
# addons automatically. The addon is present in the image at
# /mnt/extra-addons/cv_management_bridge and is picked up by
# Odoo's addon loader on first boot, but the module must be
# installed via the Odoo UI (or the Odoo shell) once. See
# ODOO_RENDER_DEPLOYMENT.md for the first-run procedure.
#
# No secrets (POSTGRES_PASSWORD, ODOO_API_TOKEN, etc.) are baked
# into the image — they must be supplied as Render environment
# variables / secret files at runtime.

# A simple HTTP health check so Render can detect a broken
# Odoo and roll the deploy back. The Odoo image serves
# /web/health on the HTTP listener (verified in the local
# compose.odoo.yaml). We use the local odoo user and `curl`
# (already installed in the base image) for the probe.
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8069/web/health || exit 1

# Inherit USER odoo, WORKDIR, ENTRYPOINT, CMD from odoo:18.0.
