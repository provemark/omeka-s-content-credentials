# Step 24 (M5.2): viewing and downloading evidence in Omeka S

2026-11-05. Step 23 measured the evidence document outside Omeka. This
step measures what M5 needs inside Omeka S. The module has none of these
yet:
- a table of documents, written by a job;
- an admin route with a controller of the module's own;
- an ACL rule;
- a link on the media page;
- a download.

It also measures who can reach the documents, whether MariaDB gives them
back byte for byte, and how the table grows over repeated runs.

## Setup (measured)

- PHP 8.3 container (Omeka S 4.2.1, PHP 8.3.36, MariaDB 11.4), Docker on
  a Mac. There were 1 103 checkable media (JPEG, PNG, WebP with an
  original), left by earlier steps and test runs.
- `tools/probes/EvidenceRouteProbe/` (never shipped) was copied into the
  container (`docker compose cp`) and installed. It has:
  - **A table** `evidence_route_probe`:
    - columns `id` (auto-increment), `media_id` (foreign key to `media`,
      `ON DELETE CASCADE`), `checked_at`, `sha256` and `document`
      (`LONGTEXT`);
    - an index on `(media_id, id)`;
    - `utf8mb4_unicode_ci`, InnoDB, as the module's own table.
  - **A job** that verifies every checkable media item with the module's
    trust settings (`TrustConfig`, built once) and appends
    `evidenceToJson()` with its SHA-256, computed in PHP before the
    insert, through DBAL's `insert()`.
  - **An admin route** `admin/evidence-probe`
    (`/admin/evidence-probe/:media-id[/:action]`) to a controller made by
    a factory. It has three actions:
    - `raw` serves the latest document without asking about the media
      item;
    - `show` and `download` first read the media item with
      `$this->api()->read('media', $id)`;
    - `show` renders the document in the admin layout in a `<pre>`,
      through `escapeHtml`;
    - `download` and `raw` return the Laminas response with the stored
      text as its body.
  - **A setting** that chooses between no ACL rule and
    `$acl->allow(null, controller)`, read in `onBootstrap()`.
  - **A link in `view.show.sidebar`** of the admin media page, built with
    `url('admin/evidence-probe', …)`, carrying
    `userIsAllowed(controller, 'download')`.
- `tools/probes/EvidenceRouteProbe/scripts/measure.php` drives it from
  the host with the configure page, a curl client that keeps headers, and
  `OmekaInstallation`:
  - `run <label>`, `stats`;
  - `acl [item]`;
  - `headers`;
  - `big`.

## Measured

| case | result |
|---|---|
| the route with only `controller` set to the class name | every request 404 ("Page not found"), for every role. Matched in the container: the route matches, and the controller is registered, but the match carries the admin route's `__NAMESPACE__` `Omeka\Controller\Admin`, and the controller name is prefixed with it. With `'__NAMESPACE__' => 'EvidenceRouteProbe\Controller', 'controller' => 'EvidenceController'` the route works |
| the ACL resource | Omeka registers the module's controller as a resource by itself: the probe's `hasResource()` check never had to add it |
| a run over 1 103 media, three times | 1 101 documents per run (2 media have no original file on disk, 8512 and 8516; the job counted them as failures); 3.0–3.2 s per run, of which verify 2.7–2.9 s, `evidenceToJson()` 0.013–0.015 s, the inserts 0.3 s (about 0.3 ms a row); peak memory 18 MB |
| sizes in this mix | 4 273 828 bytes of documents per run, 3 882 on average; the largest 34 433 (`c2pa-rs-ocsp.jpg`). By `validation_state` in the document: `Valid` 860 (average 4 366), `Invalid` 200 (870), `Trusted` 41 (8 412) |
| growth | after 1, 2 and 3 runs: 1 101, 2 202 and 3 303 rows; `DATA_LENGTH` 6.8, 13.1 and 19.4 MB; the `.ibd` file 14.7, 21.0 and 27.3 MB. About 6.3 MB on disk per run, 5.7 KB a row, against 3.9 KB of document text |
| the same media item over three runs | 232 of 1 101 got three identical documents; 869 got three different ones (`judged_at` by the clock, step 23) |
| `LONGTEXT`, byte for byte | `SHA2(document, 256)` equals the SHA-256 computed before the insert in 3 303 of 3 303 rows. A download's body (2 272 bytes) has the same SHA-256 as the row. A 5 MiB document of every printable ASCII character and `\n`, stored through the configure page, came back from the download with all 5 242 880 bytes identical |
| download headers | `200`; `Content-Type: application/json` and `Content-Disposition: attachment; filename="media-2-evidence-2203.json"` as set; `Content-Length` set by the server; the session's `Cache-Control: no-store, no-cache, must-revalidate`, `Pragma: no-cache`, `Expires` in 1981. No `X-Content-Type-Options` |
| the show page with a crafted document (`"<script>alert('m52')</script>"` as `active_manifest`) | 200, `text/html; charset=UTF-8`, in the admin layout; the script appears only escaped (`&lt;script&gt;alert(&#039;m52&#039;)`) |
| a media id that does not exist; a non-numeric id | 404; 404 (the route's constraint) |
| deleting the item of a media item with documents | `DELETE /api/items/{id}` 200; its 2 rows went with it (the foreign key) |
| uninstall | the probe's `uninstall()` dropped the table and deleted the setting |

**Who reaches what.** `P` is a public media item of a public item. `Q`
is the media item of a private item owned by the global admin. Test
users were made and deleted.

| role | media page P / Q | no ACL rule: evidence of P and Q | `allow(null, …)`: `show`, `download` P / Q | `allow(null, …)`: `raw` Q | link on P (`userIsAllowed`) without / with rule |
|---|---|---|---|---|---|
| anonymous | 302 to `/login` / 302 | 302 to `/login` | 302 / 302 | 302 | — |
| global_admin | 200 / 200 | 200 | 200 / 200 | 200 | yes / yes |
| site_admin | 200 / 200 | 200 | 200 / 200 | 200 | yes / yes |
| editor | 200 / 200 | 500 | 200 / 200 | 200 | no / yes |
| reviewer | 200 / 200 | 500 | 200 / 200 | 200 | no / yes |
| author | 200 / 404 | 500 | 200 / 404 | **200** | no / yes |
| researcher | 200 / 404 | 500 | 200 / 404 | **200** | no / yes |

- Without a rule, only global and site admins get through; the others
  get Omeka's 500 for a denied page, as in steps 15 and 18.
- With the rule, `show` and `download` answer exactly as the media page
  does, because they read the media item through the API.
- `raw` hands Q's evidence to an author and a researcher, who cannot see
  Q.

Cleanup: the item made for Q deleted, the probe uninstalled and removed
from the container, its three job rows and its log deleted.

## What follows for M5.3 and M5.4 (reasoned)

- **The route needs its own `__NAMESPACE__`.** Otherwise it fails as a
  404 that says nothing.
- **Access should rest on the media item, not only on the ACL.**
  - A controller that reads the media item through Omeka's API before it
    serves anything shows the evidence to exactly those who can see the
    media item; for this table that is the same set of answers.
  - An ACL rule alone either shuts out editors and reviewers, who can see
    the media, or opens every document to every logged-in user (`raw`).
  - Who may see the evidence is still Maurice's choice. The two natural
    answers are "whoever can see the media item" and "global and site
    admins only", and both can be built with what was measured here.
- **The download can be the stored text as it is.** Omeka adds nothing
  to the body. `X-Content-Type-Options: nosniff` is not there unless the
  module sets it; a spec can require it.
- **Viewing must escape.** The document holds text from the file
  (labels). Through `escapeHtml` a crafted label stays text.
- **Growth.** Appending at every check costs about 6 MB on disk per run
  over 1 100 media in this mix. For step 20's 3 097 media that is about
  18 MB a run, reasoned from the per-row figure, not measured. 869 of
  1 101 media (79%) got a different document at every run. Step 23 found
  such differences to be `judged_at` alone, but that was not checked
  here. Either way, "only when different" would save about a fifth.
- **Deleting a media item removes its evidence** with a foreign key
  `ON DELETE CASCADE`, as the verdict does today. Whether an archive wants
  that is Maurice's choice in M5.4. Keeping the evidence would mean no
  foreign key, so rows that point to a media id that no longer exists.
- **Uninstall** can drop the table, as the probe did. Whether the
  evidence should outlive the module is another choice for M5.4.
