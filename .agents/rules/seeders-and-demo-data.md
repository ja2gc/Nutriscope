# Seeders and Demo Data

- Normal `db:seed`: deterministic, repeatable, offline, idempotent where applicable.
- No USDA/Pexels/external API, network retry/sleep, runtime key. Never commit provider key.
- Stock provider = one-time source. Commit approved fictional local asset via existing pattern; document provenance in existing docs.
- Rerun preserves manual foods/photos, unrelated users/patients, noncanonical records unless destructive demo refresh approved.
- Canonical demo identity: email/UUID/canonical name; never fragile numeric ID.
- Private assets use existing storage. Validate bytes/MIME/dimensions/limits; no duplicate objects/orphans after failure.
- No seed audit noise unless current convention requires exact row.
- Relative deterministic dates; valid chronology/status; believable horizon.
- Smallest connected demo graph. Real services/listeners generate normal outputs; assert results.
- Never fake-seed generated reports/ledger/snapshots/notifications/transitions.
- Seed twice = no duplicates. Clean seed only confirmed disposable DB.
- Clinical catalog ≠ food-service catalog. No duplicate food DB/recipe engine.
- Historical served population usually 150–200; cost within configured per-head/day cap.
