"""Import / sync wizard.

Pulls aggregated data for one Position from the CV Management backend
and upserts an `imported.position` (matched by `external_id`) plus its
attributes (matched by `(position_id, attribute_external_id)`).

The Position is selected server-side: the Odoo operator only needs to
provide the API base URL (or accept the configured one) and the token
configured in Settings. The wizard never asks for a Position ID — the
token is what determines the Position, and one token maps to exactly
one Position.

Transactionality: Odoo wraps each HTTP request in a single
transaction that is committed on success and rolled back on any
exception escaping the controller. The wizard does not catch the
exceptions it raises (UserError, ValidationError, etc.), so if any
step fails the database is left in its previous state — there is no
partial import. We rely on the framework's request-level transaction
rather than introducing manual savepoints.
"""

import json
import urllib.error
import urllib.request

from odoo import _, api, fields, models
from odoo.exceptions import UserError, ValidationError


class ImportPositionWizard(models.TransientModel):
    _name = "imported.position.wizard"
    _description = "Import a Position from the CV Management backend"

    api_url = fields.Char(
        string="API base URL",
        help="Override the API base URL for this import. Leave blank "
        "to use the value configured in Settings.",
    )
    state = fields.Selection(
        selection=[("draft", "Draft"), ("done", "Done"), ("error", "Error")],
        default="draft",
        readonly=True,
    )
    last_result = fields.Text(readonly=True)
    imported_position_id = fields.Many2one(
        "imported.position",
        readonly=True,
    )

    def _resolve_api_url(self):
        url = (self.api_url or "").strip()
        if url == "":
            url = (
                self.env["ir.config_parameter"]
                .sudo()
                .get_param("cv_bridge.api_url", "")
            )
        if not url.startswith(("http://", "https://")):
            raise UserError(
                _("API base URL must start with http:// or https://.")
            )
        return url.rstrip("/")

    def _resolve_token(self):
        token = (
            self.env["ir.config_parameter"]
            .sudo()
            .get_param("cv_bridge.api_token", "")
        )
        if not token:
            raise UserError(
                _("No API token configured. Set one in Settings → CV "
                  "Management bridge.")
            )
        return token

    def action_import(self):
        """Call the Symfony endpoint and upsert the result."""
        self.ensure_one()
        api_url = self._resolve_api_url()
        token = self._resolve_token()

        # HTTPS-only in production. We allow plain HTTP only when the
        # host is unambiguously internal: loopback addresses, RFC 1918
        # private network ranges, link-local, or single-label hostnames
        # (Docker Compose service names like "nginx" are guaranteed to
        # resolve inside the compose network, never on the public
        # internet). Anything else must be HTTPS.
        if api_url.startswith("http://"):
            from urllib.parse import urlparse
            parsed = urlparse(api_url)
            host = (parsed.hostname or "").lower()
            port = parsed.port
            loopback_hosts = {"localhost", "127.0.0.1", "0.0.0.0", "::1"}
            private_prefixes = ("10.", "172.16.", "172.17.", "172.18.",
                                "172.19.", "172.20.", "172.21.", "172.22.",
                                "172.23.", "172.24.", "172.25.", "172.26.",
                                "172.27.", "172.28.", "172.29.", "172.30.",
                                "172.31.", "192.168.", "169.254.")
            is_loopback = host in loopback_hosts
            is_private = any(host.startswith(p) for p in private_prefixes)
            # Single-label hostname (no dots) is treated as an
            # internal Docker/service name. RFC 952 + 1123 require a
            # TLD for any public DNS name, so this is safe in
            # practice.
            is_internal_name = (
                host != "" and "." not in host and port in (None, 80, 8069, 8080, 8000)
            )
            if not (is_loopback or is_private or is_internal_name):
                raise UserError(
                    _("Refusing to send a bearer token over plain HTTP. "
                      "Use https:// in production.")
                )

        endpoint = f"{api_url}/api/odoo/positions/aggregates"
        # The Symfony backend's trusted_hosts check rejects requests
        # whose Host header is not on its allowlist. When Odoo lives
        # in the same Docker network and reaches the backend via the
        # internal service name (e.g. "nginx"), the request's Host
        # header is the service name. Pinning it to "localhost" is
        # safe here because:
        #   - the request is server-to-server and never leaves the
        #     Docker network;
        #   - "localhost" is on every default trusted_hosts allowlist;
        #   - the bearer token is the real authentication, not Host.
        request = urllib.request.Request(
            endpoint,
            headers={
                "Authorization": f"Bearer {token}",
                "Accept": "application/json",
                "Host": "localhost",
                "User-Agent": "cv_management_bridge/1.0 (+Odoo)",
            },
        )

        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                if response.status != 200:
                    raise UserError(
                        _("Backend returned HTTP %s. Check the token "
                          "and API URL.") % response.status
                    )
                payload = json.loads(response.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            if e.code == 401:
                raise UserError(
                    _("Backend rejected the token (401). The token may "
                      "be revoked or wrong.")
                ) from e
            raise UserError(
                _("Backend returned HTTP %s.") % e.code
            ) from e
        except urllib.error.URLError as e:
            raise UserError(
                _("Could not reach the backend: %s") % e.reason
            ) from e
        except json.JSONDecodeError as e:
            raise UserError(
                _("Backend returned invalid JSON.")
            ) from e

        Position = self.env["imported.position"].sudo()
        Attr = self.env["imported.position.attribute"].sudo()

        position_data = payload.get("position", {})
        external_id = int(position_data.get("id", 0))
        if external_id <= 0:
            raise ValidationError(_("Backend response is missing position.id."))

        position = Position.search([("external_id", "=", external_id)], limit=1)
        # The Symfony API uses camelCase keys (matches the rest of the
        # CV Management JSON contract). Earlier revisions of this
        # wizard read snake_case keys and silently dropped every
        # attribute because the lookup returned 0 / None.
        vals = {
            "title": position_data.get("title") or "",
            "company": position_data.get("company"),
            "level": position_data.get("level"),
            "short_description": position_data.get("shortDescription"),
            "is_public": bool(position_data.get("isPublic")),
            "max_projects": int(position_data.get("maxProjects", 5)),
            "project_tag_filter": ",".join(
                position_data.get("projectTagFilter") or []
            ),
            "total_candidates": int(
                payload.get("summary", {}).get("totalCandidates", 0)
            ),
            "last_imported_at": fields.Datetime.now(),
            "last_payload": json.dumps(payload, indent=2),
        }
        if position:
            position.write(vals)
        else:
            vals["external_id"] = external_id
            position = Position.create(vals)

        # Upsert attributes by (position_id, attribute_external_id).
        seen_attr_ids = set()
        for attr in payload.get("attributes", []):
            attr_external_id = int(attr.get("attributeDefinitionId", 0))
            if attr_external_id <= 0:
                continue
            attr_vals = {
                "position_id": position.id,
                "attribute_external_id": attr_external_id,
                "name": attr.get("name") or "",
                "data_type": attr.get("dataType") or "string",
                "is_required": bool(attr.get("required")),
                "aggregation_json": json.dumps(
                    attr.get("aggregation", {}), indent=2
                ),
            }
            existing = Attr.search(
                [
                    ("position_id", "=", position.id),
                    ("attribute_external_id", "=", attr_external_id),
                ],
                limit=1,
            )
            if existing:
                existing.write(attr_vals)
                seen_attr_ids.add(existing.id)
            else:
                new_attr = Attr.create(attr_vals)
                seen_attr_ids.add(new_attr.id)

        # Drop attributes that the backend no longer reports (e.g.
        # the Position was edited and an attribute was removed).
        stale = Attr.search(
            [
                ("position_id", "=", position.id),
                ("id", "not in", list(seen_attr_ids) or [0]),
            ]
        )
        if stale:
            stale.unlink()

        self.write({
            "state": "done",
            "last_result": _(
                "Imported Position '%s' with %d attributes."
            ) % (position.title, len(seen_attr_ids)),
            "imported_position_id": position.id,
        })

        return {
            "type": "ir.actions.act_window",
            "res_model": "imported.position",
            "res_id": position.id,
            "view_mode": "form",
            "target": "current",
        }
