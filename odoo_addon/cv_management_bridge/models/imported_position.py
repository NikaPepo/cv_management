"""Imported Position — a read-only mirror of a Position from the
CV Management backend, scoped to the data the Odoo user needs to
see aggregated statistics.

The `external_id` is the Position ID on the Symfony side. It is the
stable identifier used to upsert on re-import: a Position is created
on first import and updated (never duplicated) on subsequent imports
of the same external_id.
"""

import json
import logging

from odoo import api, fields, models

_logger = logging.getLogger(__name__)


class ImportedPosition(models.Model):
    _name = "imported.position"
    _description = "Position imported from CV Management"
    _order = "title"

    external_id = fields.Integer(required=True, index=True)
    title = fields.Char(required=True)
    company = fields.Char()
    level = fields.Selection(
        selection=[
            ("junior", "Junior"),
            ("middle", "Middle"),
            ("senior", "Senior"),
            ("c_level", "C-Level"),
        ],
    )
    short_description = fields.Text()
    is_public = fields.Boolean()
    max_projects = fields.Integer()
    project_tag_filter = fields.Char()
    total_candidates = fields.Integer()
    last_imported_at = fields.Datetime()
    last_payload = fields.Text(
        help="Raw JSON response from the last successful import. Used "
        "for debugging only; not displayed to end users.",
    )

    attribute_ids = fields.One2many(
        "imported.position.attribute",
        "position_id",
        string="Attribute aggregates",
    )

    _sql_constraints = [
        ("external_id_unique", "UNIQUE(external_id)",
         "Each Position can be imported at most once."),
    ]

    def name_get(self):
        result = []
        for record in self:
            label = record.title
            if record.company:
                label = f"{record.title} ({record.company})"
            result.append((record.id, label))
        return result
