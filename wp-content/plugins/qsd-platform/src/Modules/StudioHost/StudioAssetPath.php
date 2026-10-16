<?php

namespace QSD\Platform\Modules\StudioHost;

/**
 * The one relative-path safety check every Studio asset path is run
 * through before it is ever used to build a filesystem path or a browser
 * URL — shared by `StudioAssetGateway` (the retained plugin-local bundle)
 * and `StudioReleaseLock` (the verified remote release's digest inventory),
 * since both face the same risk: a path string that looks like a plain
 * relative filename but is actually a traversal, an absolute path, or a
 * percent-encoded escape.
 */
final class StudioAssetPath
{
    /**
     * A plain, forward-slash relative path only: no `..`/`.` segment, no
     * leading slash or backslash, no Windows drive letter, and no `%`
     * (rejected outright rather than decoded — a manifest value is never a
     * URL that should carry percent-encoding in the first place).
     */
    public static function isUrlSafeRelativePath(string $relative): bool
    {
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '%')) {
            return false;
        }
        if (str_starts_with($relative, '/') || str_starts_with($relative, '\\') || str_contains($relative, '\\')) {
            return false;
        }
        if (preg_match('/^[A-Za-z]:/', $relative) === 1) {
            return false;
        }
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }
        return true;
    }
}
