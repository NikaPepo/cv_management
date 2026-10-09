"""Bridge configuration — the API base URL and the token. The token
is stored in `ir.config_parameter` (encrypted at rest by Odoo's
default `vault` for `password` parameters) and is never logged.
"""

from odoo import api, fields, models


class ResConfigSettings(models.TransientModel):
    _inherit = "res.config.settings"

    cv_bridge_api_url = fields.Char(
        string="CV Management API base URL",
        config_parameter="cv_bridge.api_url",
        default="http://localhost:8080",
        help="Base URL of the CV Management backend. Must be reachable "
        "from the Odoo server. Production deployments should use HTTPS.",
    )
    cv_bridge_api_token = fields.Char(
        string="CV Management API token",
        config_parameter="cv_bridge.api_token",
        password=True,
        help="Bearer token issued by /api/positions/{id}/api-token on "
        "the CV Management backend. Stored as a system parameter; "
        "never logged.",
    )
