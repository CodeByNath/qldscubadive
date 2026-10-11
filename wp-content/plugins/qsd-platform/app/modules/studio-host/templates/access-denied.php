<?php
/**
 * Bare, QSD-styleless access-denied state for /studio/ — deliberately no
 * Atomic Engine CSS, drawer kit, or admin-station chrome import. Points to
 * the one real sign-in page rather than hosting a second login form.
 */
if (!defined('QSD_PLUGIN_PATH')) { return; }
?>
<main style="font-family:system-ui,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1.5rem;text-align:center;color:#1a1a1a;">
  <h1 style="font-size:1.25rem;">Studio access requires sign-in</h1>
  <p>Sign in with your platform manager account, then return to this address.</p>
  <p><a href="<?php echo esc_url($stationUrl ?? '/'); ?>">Go to sign-in</a></p>
</main>
