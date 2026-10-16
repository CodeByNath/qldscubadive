# Studio assets (retired — history reference only)

This directory held the versioned delivery seam for WEX's independently
built Admin Dashboard bundle, manually copied in on each release (Phase
1A–1G). As of Phase 1H, `/studio/`'s runtime delivery is handled entirely by
`src/Modules/StudioHost/StudioReleaseLock.php`, which fetches and verifies
WEX's own published, immutable release directly — nothing is copied into
this plugin for that path anymore, and `StudioAssetGateway` no longer reads
anything from this directory.

The files here are kept only as this delivery mechanism's git-history
rollback reference. They are never read at runtime, and a `StudioReleaseLock`
verification failure never falls back to them — the host fails closed
(nothing enqueued) exactly as it did before any manifest was ever supplied
here.
