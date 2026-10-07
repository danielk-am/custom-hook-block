# Interactive preview regression evidence

7 October2026: before parent updated the disposable Studio plugin, `interactive.php` returned22pass/9fail. Failing checks were exactly the new PHP metadata contract: explicit defaultfalse, explicittrue, rejection of six nonboolean values (includingnull), and opt-in via display-hook registration. Existing22 schema/injection/authorization checks passed.

`node tests/preview-registry.mjs` initially failed ENOENT because the new module was not yet implemented. This is a feature-absence red result, not evidence of a lifecycle bug.

Tests modify only their own temporary WordPress user and remove it in finally. No activation/install or production changes were performed by the test author.

After the worker supplied the actual preview registry/lifecycle module, Node tests passed26/26. Lifecycle coverage uses the actual exported mountPreview: distinct roots/settings/context/signals, abort-before-cleanup, independent concurrent instances, idempotent cleanup, replacement mount with fresh settings/signal, thrown initializer abort, asynchronous initializer rejection and optional cleanup. No React lifecycle behavior is claimed by these helper tests; actual editor mount/rerender wiring and global registration API are reserved for browser verification.

After parent installed2.1.0, interactive PHP suite passed31/31. Baseline regression suite passed68/68 and actual site-adapter suite10/10. Default-hook test now isolates pre-existing request-local UI demo registration/actions and restores them; no saved registration or content changed. Existing3 post/product records including all metadata had identical before/after SHA256 snapshots; own temporary fixtures were removed. Logs: work/custom-hook-block-2026-10-07/interactive-2.1/.

Commands: `node tests/preview-registry.mjs`; on the disposable Studio site, `studio wp eval-file <absolute tests/run.php>`, `studio wp eval-file <absolute tests/interactive.php>`, and `studio wp eval-file <absolute tests/site-adapter.php>`. Parent separately confirmed actual editor AJAX, settings refresh and Escape behavior.
