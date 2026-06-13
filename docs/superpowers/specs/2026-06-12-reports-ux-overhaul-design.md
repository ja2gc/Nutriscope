# Spec 4 — Reports-UX Overhaul (always-present, period-filtered, on-demand)

- **Date:** 2026-06-12
- **Status:** Draft design, pending review
- **Depends on:** Spec 1 (frozen figures make on-demand re-render safe). Independent of Specs 2/3.
- **Roadmap:** Spec 4 of 5 (see [Spec 1](2026-06-12-fs-costing-immutability-design.md))

---

## 1. Background

Today reports are **manufactured**: you set parameters, click **Generate**, the backend renders a PDF, stores it, and writes a `Report` row; every click drops another file into one **flat "Recent Reports" list** ([reports/page.tsx](../../../frontend/app/(rnd)/reports/page.tsx)). The user's mental model — and a better one — is the opposite: reports should **always already exist** as views over the data, browsable and **filterable by year/month**, downloaded on demand. No manual generation step.

Because **PO-derived** reports are built from frozen snapshots (Spec 1), rendering those on demand yields the same numbers as a stored copy — immutability holds, and only branding/signatories differ on re-render (hence the **Archive** action).

> ⚠️ **Critical exception (review finding #1):** **menu-derived reports** (Program/Project/Activity, Menu Calendar, Budget *planned* line) are computed **live** and are **not** frozen. Re-rendering an old PPA on demand shows **today's** prices/cycle — wrong for a historical/filed document. This makes naive on-demand rendering *unsafe* for those reports. Resolution depends on **Spec 6** giving menu reports a period snapshot; until then, for menu-derived types the browser must render **from the archived snapshot by default** (not a live re-render), or refuse on-demand render of a past period. PO-derived types are safe to live-render.

## 2. Goals / Non-goals

**Goals**
1. **Browse, don't generate:** report **type → year → month** (or → entity for entity-based reports) → the report is just *there* to view/download.
2. **On-demand render:** download streams a freshly rendered PDF from current frozen data; no persisted artifact required.
3. **Archive this copy:** optional action that freezes the as-submitted PDF (data + branding + signatories snapshot) into a stored `Report` row — for the handful you formally file.
4. Repurpose the existing `reports` table as the **archive**, not a dumping ground.

**Non-goals**
- Changing the compliance form layouts themselves (Spec-1 figures unchanged).
- Graphs in reports (that's Spec 3).

## 3. The axis problem (read this first)

"Filter by year/month" works for **dated/range** reports but not all. Reports split by their natural browse axis:

| Report | Natural axis |
|---|---|
| Dietary Cash Book, Budget, Inventory | **date range** (year → month) |
| Procurement Pack | **entity**: a shopping list / received PO (which itself has a date) |
| Program/Project/Activity, Menu Calendar | **entity**: a menu cycle (week) |
| Patient Menu Plan, Demographic Census | **entity/range**: a patient / a date range |

So the browser is **type → {period | entity}** depending on type. A small per-type descriptor declares which axis and how to enumerate instances (e.g. "received POs grouped by month", "menu cycles by week"). This avoids pretending everything is a calendar.

## 4. Design

### 4.1 Backend
- **`available` index:** `GET /reports/{type}/instances?year=&month=` → enumerates renderable instances for that type from real records (POs, cycles, budgets, date buckets) with the params each needs. Driven by the per-type axis descriptor.
- **On-demand render:** `GET /reports/{type}/render?<params>` → streams the PDF using the **existing generators** (`ReportService` + Blade views), **without** writing a `Report` row.
- **Archive:** `POST /reports/{type}/archive?<params>` → render + snapshot branding/signatories + store file + write a `Report` row (status `archived`). This is the only path that persists.
- Keep `Report` model for archives; drop the "every generate persists" behavior.

### 4.2 Frontend
Replace the generate-cards + flat list with a browser: pick type → pick year/month (or entity) → list of instances, each with **Download** (on-demand) and **Archive**. A separate **Archived** view lists frozen copies.

### 4.3 Audit (ties to Spec 5)
"Who created the report" becomes meaningless under on-demand; the audit-worthy events are **downloaded** and **archived** (by whom, which period). Logged via Spec 5.

## 5. Migration / back-compat
- Existing stored reports remain as archives.
- `generateReport`/`generateAll` endpoints either retire or become `archive`/`archive-all`.
- Frontend `reportService` gains `listInstances`, `renderUrl`, `archive`.

## 6. Error handling
- Render of a period/entity with no data → a valid "no data" PDF or a 404 with a clear message (decision §8).
- Permission checks on render/download/archive (owner/role), consistent with current `authorizeOwner`.
- Missing branding/signatory template → fall back to defaults, never crash the render.

## 7. Testing
- Instance enumeration per axis type returns the right records for a period.
- On-demand render of an old period **before vs after** editing a catalog price → identical figures (re-proves Spec 1 immutability through the new path).
- Archive freezes branding: render → edit branding → the archived copy keeps old branding, a fresh render shows new.

## 8. Flaws / risks & open decisions
1. **Branding drift is the whole reason Archive exists** — must make it obvious in the UI which view is "live re-render" vs "as-filed archive," or users will be confused about which is authoritative.
2. **Enumerating instances can be heavy** (e.g. every month with a received PO across years) — needs indexed queries + maybe lazy year/month facets.
3. **Empty periods:** do we list a month with no data at all, or hide it? (Recommend: only list periods that have data.)
4. **Open decision:** retire the old generate endpoints outright, or keep them deprecated for one release?

## 9. Resolutions (2026-06-13)

Resolved in brainstorming; **fully implemented** — Part 1 (backend; full PHP suite 426/426) and Part 2 (frontend browser; `tsc` clean). The reports page is now a browser: a type rail → instances panel (period/entity/singleton axis) with a live **Download** and an **Archive** action, plus an **Archived** tab of frozen as-filed copies.

- **Snapshot mechanism — store PDF bytes (light), not a full data snapshot.** The `Archive` action renders the PDF **once**, stores it, and writes a `Report` row (`status='archived'`) plus a small `snapshot` JSON holding the **branding + signatories + params** actually used. Re-download serves the **frozen stored bytes** — it never re-renders — so the as-filed copy is frozen with zero per-generator/per-Blade-view rewrite. This **closes Spec 6 #1** for the filed-document case (the §16 critical exception): a menu-derived report you archived is frozen as bytes; a *live* render of a past menu period still shows current prices and the UI must label it "live preview." You can only recover a frozen menu-derived doc if you archived it — inherent to not auto-snapshotting at period close (deferred).
- **#3 (no-data render): 404 with a clear message.** The browser only ever lists real instances, so this is the edge guard. Implemented via `InstanceSource::hasData()`.
- **#3 (empty periods): only enumerate periods/entities that have data.** Driven by per-type `InstanceSource`s (period / entity / singleton axes — see §3).
- **#4 (old endpoints): keep `generate`/`generate-all` working but `@deprecated` for one release;** new `instances` / `render` / `archive` endpoints added alongside.

**Implementation notes (Part 1):**
- New: `GET reports/{type}/instances`, `GET reports/{type}/render` (no DB row), `POST reports/{type}/archive`.
- `ReportService::buildPdf()` extracted so render can stream from a **transient** (un-saved) `Report`; `generate()` still stores for archives.
- Browse axis lives in `App\Services\Reports\Instances\*` + `ReportBrowser` (parallel to the generator registry; generators stay render-only).
- Migration converts `reports.type`/`reports.status` from `enum` to plain `string` (the enum CHECK had to be patched per-driver each time the type list grew and was never updated on sqlite) and adds the `snapshot` JSON column.
- Report routes de-duplicated into a single shared closure used by both the `rnd` and `fss` route groups.
