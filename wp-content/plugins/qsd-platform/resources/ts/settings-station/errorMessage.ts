// The apiClient throws `API <METHOD> <path> → <status>: <body>`. Settings
// lanes show the backend's own `message` from that body when there is one.

export function errorMessage(error: unknown, fallback: string): string {
  if (!(error instanceof Error)) return fallback;
  const body = error.message.split(': ').slice(1).join(': ');
  try {
    const parsed = JSON.parse(body) as { message?: unknown };
    if (typeof parsed.message === 'string' && parsed.message) return parsed.message;
  } catch {
    // Not a JSON body — fall through to the raw message.
  }
  return error.message || fallback;
}
