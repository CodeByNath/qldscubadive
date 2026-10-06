// Tools → Rezdy importer — placeholder for the Phase 3 importer.
//
// States what exists today, honestly: the importer is not built. When it is,
// it lives here and uses Security-governed authority through the credential
// broker; it never reads or holds the Rezdy API key itself.

import type { VNode } from 'preact';

export function RezdyImporterTool(): VNode {
  return (
    <p class="cz-settings-muted" data-tool="rezdy-importer">
      Not available yet. The Rezdy importer will live here. It will use the Rezdy API key saved under Security → API Keys through the server, and never see the key itself.
    </p>
  );
}
