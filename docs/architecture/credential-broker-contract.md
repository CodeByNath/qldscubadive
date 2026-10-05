# Credential Broker Contract

**Status:** Draft. Pending Reviewer acceptance. Real provider credentials stay prohibited until this contract, encryption at rest, and the credential permission level are reviewed.

## Rule

A consumer (a future importer, a booking sync) never receives a stored long-lived provider credential. Connections/Security is a **credential broker**: consumers ask it for narrow, short-lived authority, and it performs the provider operation server-side.

## Flow

1. **Issue.** `CredentialBroker::issue(BrokerGrant)` checks three things before issuing: the provider exists, it declares the requested scope, and it is configured. It then issues a request key.
   - The key is `qrk_` plus 32 random bytes in base64url.
   - It is bound to the provider, the scope, the caller component, the WordPress user, and an optional subject (for example a Service `QSDS` or an import id).
   - The default time to live is 60 seconds, and the maximum is 300.
2. **Persist.** Only the key's SHA-256 hash and safe metadata are stored (`WpdbRequestKeyStore`, non-autoloaded `qsd_rk_<hash>` rows, JSON). The key itself is never stored or logged.
3. **Consume.** `perform(key, grant, params)` consumes the key with an atomic row `DELETE` that must affect exactly one row. Only then does it check expiry and binding.
   - An unknown key, a reused key, or a key that loses a concurrent race is refused.
   - An expired key is refused.
   - Any binding mismatch is refused, and the key is **burned**, not left usable.
4. **Perform.** The broker decrypts the credential (`ConnectorCredentials` → `CredentialCipher`) and passes it as `ProviderSecrets` to the provider's Settings-owned `BrokeredProviderOperation`.
   - The consumer receives only the operation's result.
   - A result that contains a secret is withheld.
   - The text of an upstream failure is never returned to the consumer.
5. **Audit.** `BrokerAuditLog` records issued, used, expired and each rejection or failure. Each entry holds the request id, provider, scope, caller, user, subject and time. It never holds a key, a key hash, or a secret. The log is bounded to the newest 200 entries.

A request key and a request id are security artifacts, never Platform IDs.

## Encryption at rest

Secrets are sealed with libsodium XChaCha20-Poly1305 (`CredentialCipher`).

- **Key material** lives outside the database, in the `QSD_CREDENTIAL_KEY` wp-config constant (base64 of 32 random bytes). This is the same rule `MailService` uses for SMTP credentials.
- **Nonces.** Every seal uses a fresh random nonce.
- **Field binding.** Additional data binds each ciphertext to its provider and field.
- **Key id.** A key-id fingerprint detects a value sealed under another key.
- **Fail closed.** With no valid key, secret saves are refused (409) and nothing decrypts. A plaintext value is never accepted as a secret.

## Current boundaries

- **No brokered scopes yet.** No provider declares a brokered scope or operation. Rezdy declares none, so no request key can be issued until a reviewed Connector adds a scope and its operation. That waits for the Owner's pre-built Rezdy importer.
- **Server-internal API.** The broker is server-internal and has no REST route.
- **Permission.** Credential management uses the platform capability `manage_qsd`. Whether a narrower capability is required is an open decision gate.
- **Key operations are open.** Provisioning and rotating the key on staging and production are open operational decisions. Rotation means re-sealing stored secrets.

See [Settings Station](../code-map/settings-station.md).
