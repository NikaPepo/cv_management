#!/bin/bash
# ============================================================
# Custom entrypoint for the Odoo 18 image in this addon.
#
# Why this exists
# --------------
# The upstream `odoo:18.0` image's `/entrypoint.sh` reads
#   : ${PORT:=${DB_PORT_5432_TCP_PORT:=${PGPORT:=5432}}}
# which uses `$PORT` for the **database** port. Render auto-sets
# `$PORT=10000` (or the user-overridden value) to control the
# **HTTP** port that Render's health check scans. When the two
# meanings collide, setting `PORT=5432` makes the DB connection work
# but Render can't find the HTTP service, and setting `PORT=8069`
# makes Render's health check pass but Odoo tries to connect to
# PostgreSQL on port 8069.
#
# This wrapper splits the two concerns: PostgreSQL connection
# parameters come from `PG*` env vars; HTTP listen port comes from
# Render's `$PORT` (falling back to Odoo's standard 8069).
# ============================================================
set -e

# --- Database connection (from PG* / POSTGRES_* env vars) ---
: "${PGHOST:=db}"
: "${PGPORT:=5432}"
: "${PGUSER:=odoo}"
: "${PGPASSWORD:=odoo}"

# --- HTTP listen port (Render's $PORT, default 8069) ---
: "${ODOO_HTTP_PORT:=${PORT:-8069}}"

# Wait for PostgreSQL to be ready (up to 30 s).
wait-for-psql.py \
    --db_host="$PGHOST" \
    --db_port="$PGPORT" \
    --db_user="$PGUSER" \
    --db_password="$PGPASSWORD" \
    --timeout=30

# Start Odoo with explicit args. CLI args override anything the
# entrypoint would otherwise read from env. We bind the HTTP listener
# to 0.0.0.0 so Render's health check from outside the container
# can reach it.
exec odoo \
    --db_host="$PGHOST" \
    --db_port="$PGPORT" \
    --db_user="$PGUSER" \
    --db_password="$PGPASSWORD" \
    --http-interface=0.0.0.0 \
    --http-port="$ODOO_HTTP_PORT"
