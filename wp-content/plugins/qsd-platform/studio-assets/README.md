# Studio assets

This directory is the versioned delivery seam for WEX's independently built
Admin Dashboard bundle. It is empty until WEX supplies a build here; QSD's
`/studio/` host route works standalone without it (see
`src/Modules/StudioHost/StudioAssetGateway.php`).

WEX supplies exactly:

- `manifest.json` — `{ "entry": "<path to the JS entry, relative to this directory>", "styles": ["<path>", ...], "version": "<string>" }`
- the files `entry` and `styles` point at.

`StudioAssetGateway` enqueues `entry` as an ES module and each `styles` entry
as a stylesheet, only on the authorized `/studio/` route for a signed-in
platform manager, cache-busted by each file's own `filemtime()`. No QSD
visual code, Atomic Engine CSS, or drawer kit is loaded here.
