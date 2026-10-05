# Credential Broker Contract

**Status:** Draft. Pending Reviewer acceptance (Security Phase 1). Real provider credentials and key provisioning stay prohibited until this contract is accepted and the Phase 2 validation package runs.

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

## Permission

- **Safe state.** Reading connection state (`configured`, `encryption.available`) and saving non-secret configuration need the platform capability `manage_qsd`.
- **Secrets.** Setting, replacing or clearing a secret, and disconnecting a provider (which removes its secrets), need the WordPress administrator capability `manage_options` (`CredentialAuthority`). A business platform manager holds only `manage_qsd` and is refused with 403.
- **Gates.** `PUT /admin/settings/connections/{provider}` is gated by `requireSaveAuthority`: a request that carries a non-empty secret or a `clear` needs administrator authority. `DELETE` is gated by `requireSecretAuthority`.
- **Projection.** The list reports `permissions.manage_secrets`, so the lane makes secret inputs read-only and hides Disconnect for anyone else. The server enforces the rule either way.
- No new role or capability family is created.

## Key operations

1. **Provision.** `QSD_CREDENTIAL_KEY` lives in `wp-config.php`, outside the database: base64 of 32 random bytes (`php -r "echo base64_encode(random_bytes(32));"`). It is never stored, logged or sent over HTTP.
2. **Envelopes.** Every stored secret is an AEAD envelope bound to its provider and field, carrying the key-id fingerprint of the key that sealed it.
3. **Replacing the key without a re-seal fails closed.** An envelope sealed under another key does not open, so the secret reads as not configured and the broker cannot use it. Nothing is lost: the old key still opens it.
4. **Rotation** is an explicit, privileged shell operation, never a REST route:
   1. Move the old key to `QSD_CREDENTIAL_KEY_PREVIOUS` and put the new key in `QSD_CREDENTIAL_KEY`.
   2. Run `wp qsd credentials reseal` (`CredentialRotationCommand` → `CredentialRotation`). Every secret is opened with the previous key and re-sealed under the new key, bound to the same provider and field, with a fresh nonce.
   3. Remove `QSD_CREDENTIAL_KEY_PREVIOUS`.
5. **No secrets in output.** The re-seal output names `provider:field` slots and counts only. It never contains either key, a key id, or a plaintext.
6. **All or nothing.** Every secret is planned before anything is written. If any value opens under neither key, or is not an envelope, the re-seal reports it as unreadable and writes nothing. An unreadable credential is never marked configured. A repeated re-seal finds every secret already current and writes nothing.

The re-seal refuses to start when either key is missing or invalid, or when both are the same key.

## Current boundaries

- **No brokered scopes yet.** No provider declares a brokered scope or operation. Rezdy declares none, so no request key can be issued until a reviewed Connector adds a scope and its operation. That is Phase 3 work, after the Owner's Rezdy importer reference is audited.
- **Server-internal API.** The broker is server-internal and has no REST route.
- **No real credentials yet.** No real provider credential is stored, no key is provisioned on staging or production, and no provider call is made in this phase.

See [Settings Station](../code-map/settings-station.md).
