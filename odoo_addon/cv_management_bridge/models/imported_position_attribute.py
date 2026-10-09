"""Imported Position Attribute — one row per attribute attached to an
imported Position, holding the aggregated values returned by the
backend. The aggregation JSON is stored verbatim so that Odoo can
render it without re-deriving anything.
"""

import json

from odoo import api, fields, models


class ImportedPositionAttribute(models.Model):
    _name = "imported.position.attribute"
    _description = "Aggregated attribute for an imported Position"
    _order = "position_id, attribute_external_id"

    position_id = fields.Many2one(
        "imported.position",
        required=True,
        ondelete="cascade",
    )
    attribute_external_id = fields.Integer(required=True, index=True)
    name = fields.Char(required=True)
    data_type = fields.Selection(
        selection=[
            ("string", "String"),
            ("text", "Text"),
            ("numeric", "Numeric"),
            ("date", "Date"),
            ("period", "Period"),
            ("boolean", "Boolean"),
            ("one_of_many", "One of many"),
            ("image", "Image"),
        ],
        required=True,
    )
    is_required = fields.Boolean()
    aggregation_json = fields.Text(
        required=True,
        help="Type-specific aggregation block, exactly as returned by "
        "GET /api/odoo/positions/aggregates.",
    )
    aggregation_count = fields.Integer(
        compute="_compute_aggregation_count",
        store=True,
    )

    _sql_constraints = [
        ("attribute_per_position_unique",
         "UNIQUE(position_id, attribute_external_id)",
         "Each attribute can be imported at most once per Position."),
    ]

    @api.depends("aggregation_json")
    def _compute_aggregation_count(self):
        for record in self:
            try:
                data = json.loads(record.aggregation_json or "{}")
            except (ValueError, TypeError):
                data = {}
            record.aggregation_count = int(data.get("count", 0))

    @api.model
    def _parse_aggregation(self):
        """Helper: returns the JSON aggregation block as a dict."""
        self.ensure_one()
        try:
            return json.loads(self.aggregation_json or "{}")
        except (ValueError, TypeError):
            return {}
