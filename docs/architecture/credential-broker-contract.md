# Credential Broker Contract

**Status:** Accepted for Security Phase 1 (`main` `da93493`). Security Phase 2A adds the caller allow-list, Rezdy's read-only `connection.verify` scope and the runtime validation route. The Owner-corrected credential flow (2026-10-06) moves key ownership into QSD: a QSD-owned keyring, admin-run rotation and Tool access through `BrokeredAccess`. These await Reviewer acceptance and staging2 runtime evidence.

## Rule

A consumer (a future importer, a booking sync) never receives a stored long-lived provider credential. Connections/Security is a **credential broker**: consumers ask it for narrow, short-lived authority, and it performs the provider operation server-side.

## Flow

1. **Issue.** `CredentialBroker::issue(BrokerGrant)` checks four things before issuing: the provider exists, it declares the requested scope, the caller component is allow-listed for that provider scope, and the provider is configured. It then issues a request key.
   - The allow-list is server code (`SettingsModule::brokerCallers()`, caller → `provider:scope` pairs). A caller that is not listed is refused with `unknown_caller`, and a broker with no allow-list issues nothing.
   - The WordPress user and caller in a grant are always derived server-side. A route never takes either from client input.
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

## Key ownership

An authorised administrator manages provider API keys entirely through `Services Station → Settings → Security → API Keys`. QSD owns the secure-storage machinery behind that flow. Normal use never needs a server-file edit, shell access, hosting setup or database work, and the admin never sees or handles encryption key material.

| | Provider credentials | Credential data key | Wrapping key |
|---|---|---|---|
| What | Rezdy, Stripe and future provider API keys. | A random 32-byte key that seals provider credentials. | The key that seals the data key. |
| Who manages it | An authorised administrator, through API Keys only. | QSD. It is created on the first key save and replaced by Rotate encryption key. | Nobody: QSD derives it from the site's existing WordPress secret keys. |
| Where it lives | Encrypted envelopes in the non-autoloaded `qsd_settings_connections` option. | Only sealed, in the non-autoloaded `qsd_settings_credential_keyring` option. | Never stored. It is derived at runtime from `SECURE_AUTH_KEY` and `SECURE_AUTH_SALT`, which WordPress keeps in its server configuration, outside the database. |
| How it changes | Write-only `PUT`/`DELETE` through `qsd/v1`; never readable back. | `POST /admin/settings/security/rotation` (administrators). | Only if the site's WordPress secret keys change. |

Rules:
- A business admin is never asked to edit `wp-config.php`, the host, a server file or the database, to set up Security or to add a provider key.
- Provider keys are never placed in config files or server files. Tools and importers consume brokered authority, never a key.
- No key material, key id or wrapped key ever reaches the UI, a REST payload, the audit log or a report.
- Hosting hardening (optional, never required): when the server defines `QSD_CREDENTIAL_KEY` (base64 of 32 bytes), QSD prefers it as the wrapping key. It is never an admin step and never a precondition for saving a key.

## Encryption at rest

Secrets are sealed with libsodium XChaCha20-Poly1305 (`CredentialCipher`), under the active data key from `CredentialKeyring`.

- **Hierarchy.** Provider credential → sealed under the data key → the data key is sealed under the wrapping key. The wrapping key is HKDF-SHA256 over the WordPress secret keys with the QSD-specific context `qsd-credential-wrap:v1`, so it cannot collide with any other WordPress use of those keys, and the database alone never holds enough to open a credential.
- **Nonces.** Every seal uses a fresh random nonce.
- **Binding.** Additional data binds each credential ciphertext to its provider and field, and each sealed data key to the versioned context `keyring:v1` and its own key id.
- **Key id.** A key-id fingerprint on each envelope selects the data key that opens it, and detects a value sealed under any other key.
- **Generations.** The keyring holds the active data key and any earlier generation not yet retired by a rotation. It opens envelopes under any generation it can unseal.
- **Fail closed.** Secure storage is unavailable when libsodium is missing or the WordPress secret keys are absent or still the placeholder phrase. Secret saves are then refused (409) and nothing decrypts. A plaintext value is never accepted as a secret.
- **Wrapping-key change.** If the site's WordPress secret keys are regenerated, existing data keys no longer unseal. The affected API keys read as not set, the broker cannot use them, and the admin re-enters them in API Keys. The next save starts a new data-key generation. Nothing is deleted.

## Permission

- **Safe state.** Reading connection state (`configured`, `encryption.available`) and saving non-secret configuration need the platform capability `manage_qsd`.
- **Secrets.** Setting, replacing or clearing a secret, and disconnecting a provider (which removes its secrets), need the WordPress administrator capability `manage_options` (`CredentialAuthority`). A business platform manager holds only `manage_qsd` and is refused with 403.
- **Gates.** `PUT /admin/settings/connections/{provider}` is gated by `requireSaveAuthority`: a request that carries a non-empty secret or a `clear` needs administrator authority. `DELETE` is gated by `requireSecretAuthority`.
- **Projection.** The list reports `permissions.manage_secrets`, so the lane makes secret inputs read-only and hides Disconnect for anyone else. The server enforces the rule either way.
- **Runtime validation.** `POST /admin/settings/security/broker-validation` needs both `manage_qsd` and `manage_options`, because it uses the stored credential for one provider call.
- **Rotation.** `POST /admin/settings/security/rotation` needs both `manage_qsd` and `manage_options`. It takes no input, returns a re-sealed count and any unreadable slot names (409 when refused), and is audited as `rotated` with the session user.
- No new role or capability family is created.

## Key operations

QSD performs these itself. None is an admin step, and none needs server or shell access.

1. **First save.** The first provider-key save creates the keyring: QSD generates the data key, seals it under the wrapping key and stores it. No setup comes first.
2. **Envelopes.** Every stored secret is an AEAD envelope bound to its provider and field, carrying the key id of the data key that sealed it.
3. **Rotation** (`CredentialRotation::rotate()`, from Rotate encryption key in API Keys):
   1. A new data key is generated.
   2. Every stored secret is planned first: opened under the keyring and sealed again under the new key, bound to the same provider and field, with a fresh nonce.
   3. If any value opens under no generation, or is not an envelope, rotation writes nothing and reports the slot as unreadable. The admin replaces or removes that key, then rotates again.
   4. Otherwise the new generation is stored as active (the old one kept), every secret is replaced in one write, and only then is the old generation retired. An interruption between steps leaves every secret openable.
   5. The kept data key is re-sealed under the preferred wrapping key.
4. **No secrets in output.** Rotation output names `provider:field` slots and counts only. It never contains a key, a wrapped key, a key id or a plaintext.
5. **Legacy envelopes.** A secret sealed directly under `QSD_CREDENTIAL_KEY` by an earlier build still opens while that constant is defined, and the next rotation moves it into the keyring.

## Current boundaries

- **One brokered scope.** Rezdy declares only `connection.verify`, performed by `RezdyConnectionCheck`.
  - It sends one read-only `GET products?limit=1` and reads only the HTTP status and `requestStatus.success`; the body is discarded.
  - It returns outcome (`authenticated`, `unauthorized`, `rate_limited`, `upstream_error`, `unexpected_response`, `network_error`), HTTP status and latency. It never returns the key, the URL, the body or upstream error text.
  - Security Phase 2 bound: it runs only when the environment is `staging` (`https://api.rezdy-staging.com/v1/`). Any other environment returns `refused_environment` without a request.
  - Importer scopes and operations are Phase 3 work, after the Owner's Rezdy importer reference is audited.
- **Allow-listed callers.** The only caller is `settings.security-validation`, for `rezdy:connection.verify`.
- **Tool access.** A Tool receives a `BrokeredAccess` bound to its own allow-listed caller (`SettingsModule::brokeredAccess()`). It can only run a declared provider scope for the session user: issue and consume happen inside, in one call. It never holds the broker, a request key, `ConnectorCredentials` or a credential.
- **Runtime validation route.** The broker itself has no REST route. `BrokerValidation`, behind the route above, runs one fixed sequence on the real install for the session user:
  - storage evidence: each secret is an envelope that opens under the current key, with no plaintext in the option;
  - key generations from `CredentialRotation::inspect()`, which is read-only;
  - issue → hash-only bound row → consume and perform (the single provider call);
  - replay refused; expiry refused; an abandoned key swept; a binding mismatch refused and the key burned; no row left behind;
  - audit events for the run, with no key, hash or secret.
  It ignores the request body. Its report holds safe metadata only and is checked for keys, key hashes, secrets and key ids before it is returned.
- **Real credentials.** For Phase 2 validation on staging2 only, an administrator enters one real Rezdy staging API key through API Keys. Production stays out of scope.

See [Settings and Security](../code-map/settings-station.md).
