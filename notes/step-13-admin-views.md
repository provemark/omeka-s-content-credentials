# Step 13 (M2.1): the admin views a verdict can be shown in

2026-11-04. M2 shows SPEC-002's verdict in the admin interface: on the
media page, and in the media browse with a filter. Before a spec rests on
it: which hooks Omeka S 4.2.1 offers there, what a listener receives, and
whether output passed through `escapeHtml` arrives escaped.

## Read (Omeka S 4.2.1 source)

- `view/omeka/admin/media/show.phtml` triggers `view.show.page_actions`,
  `view.show.sidebar` (inside the right-hand sidebar, after Omeka's own
  groups) and `view.show.after`; `show-details.phtml` (the "Details"
  panel opened from the browse) triggers `view.details` with
  `['entity' => $resource]`; `browse.phtml` triggers `view.browse.before`,
  `view.browse.actions` (per row) and `view.browse.after`.
- `Omeka\View\Helper\Trigger`: the event's target is the view, its
  identifier the route's controller, here `Omeka\Controller\Admin\Media`
  (the public site's media pages have `Omeka\Controller\Site\Media`, so an
  admin listener does not fire there).
- The browse table's columns come from `$this->browse()->renderHeaderRow()`
  and `renderContentRow()`: column types (`Omeka\ColumnType\ColumnTypeInterface`)
  registered under `column_types` in the configuration, chosen per user
  (user setting `columns_admin_media`) or, without one, from
  `column_defaults.admin.media`. A type's `getSortBy()` offers a
  `sort_by` value, or none with `null`.
- `view/common/advanced-search.phtml` triggers `view.advanced_search`
  (filterable `partials`); `Omeka\View\Helper\SearchFilters` triggers
  `view.search.filters` (filterable `filters`), the labels of the active
  search above the browse; `api.search.query` lets a module narrow the
  query (step 10 joined the module's entity there).
- `AbstractEntityAdapter::sortQuery()` sorts only by the adapter's own
  `sortFields` and `random`; any other `sort_by` adds no order.

## Measured

A measurement-only module, `tools/probes/ViewProbe/` (never shipped),
installed in the test environment's Omeka S 4.2.1 (PHP 8.3). Every hook
above prints a marker; each marker carries the string
`<script>alert("x")</script>&"'` passed through the view's `escapeHtml`.
It registers a column type `view_probe` and adds it to
`column_defaults.admin.media` in its module configuration, a search
partial with a checkbox `view_probe`, a search-filters label, and an
`api.search.query` condition (media type `image/png`) for `view_probe=1`.
Requested as the test administrator, with 72 media in the database (24
PNG):

| page | answer | markers |
|---|---|---|
| `/admin/media/156` | 200 | `view.show.sidebar` and `view.show.after`, each with the media's id read from the view (`$view->vars('media')`) |
| `/admin/media/156/show-details` | 200 | `view.details` with id 156 from the event's `entity` |
| `/admin/media` | 200 | browse before and after once; the probe's column header, and its content in each of the 25 rows; no user had chosen columns, so the column came from the merged `column_defaults` |
| `/admin/media/search` | 200 | the probe's search partial; the column offered as a sort option `<option value="view_probe">` |
| `/admin/media?view_probe=1` | 200 | the filter label above the browse; the 24 rows shown were all `image/png` (checked in the database) |
| `/admin/media?sort_by=view_probe&sort_order=asc` | 200 | rows in the default order: the unknown `sort_by` was ignored |

- The hostile string arrived escaped everywhere
  (`&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;&amp;&quot;&#039;`),
  and `<script>alert` did not occur in any page.
- After uninstalling the probe, `/admin/media` held no marker; then
  `tools/env/reset.sh`.

## Conclusions for the M2 spec (reasoned from the above)

- The media page: a block in `view.show.sidebar`, where Omeka shows a
  media item's own facts (MIME type, size, ingester); the Details panel in
  the browse can show the same through `view.details`.
- The browse: a column type of the module's own, added to
  `column_defaults.admin.media`, so it shows without each administrator
  choosing it, and removable per user like Omeka's own columns.
- The filter: a search partial through `view.advanced_search`, a label
  through `view.search.filters`, and a join on the module's entity in
  `api.search.query` (step 10).
- No sorting by verdict: `getSortBy()` returns `null`, because a
  `sort_by` the adapter does not know is silently ignored; sorting would
  need its own join and spec.

## Not measured

- A user who has chosen their own columns does not get the module's
  column until they add it (read in `Browse::getColumnsData()`).
- Translations: Omeka translates labels through `translate`; the module
  ships no translation in M2 (reasoned, a spec choice).
