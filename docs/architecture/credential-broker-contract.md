# Credential Broker Contract

**Status:** Accepted for Security Phase 1 (`main` `da93493`). Security Phase 2A adds the caller allow-list, Rezdy's read-only `connection.verify` scope and the runtime validation route. Those additions await Reviewer acceptance and staging2 runtime evidence.

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

## Two secret classes

QSD holds two different kinds of secret, with different owners and paths. They are never mixed.

| | Platform master encryption key | Provider credentials |
|---|---|---|
| What | `QSD_CREDENTIAL_KEY` (and `QSD_CREDENTIAL_KEY_PREVIOUS` during rotation). Infrastructure key material used only by `CredentialCipher`. Not a Rezdy, Stripe or any provider key. | Rezdy, Stripe and future provider API keys. |
| Who manages it | The platform operator, as part of hosting/deployment. It is invisible to the business admin. | An authorised administrator, through the QSD UI only (target: `Services Station → Settings → Security → API Keys`). |
| Where it lives | Outside the database and outside the Admin Station, as a server configuration constant. | Encrypted envelopes in the `qsd_settings_connections` option, sealed under the master key. |
| How it changes | Operator provisioning, and rotation via the shell re-seal (Key operations). There is no UI, REST route or deployment-workflow path for it. | Write-only `PUT`/`DELETE` through `qsd/v1`; never readable back through UI or API. |

Rules:
- A business admin is never asked to edit `wp-config.php`, the host or any server file, either to set up Security or to add a provider key.
- Provider keys are never placed in config files or server files. Tools and importers consume brokered authority, never a key.
- The deployment workflow does not write server configuration. Provisioning the master key through it would need a separate explicit Owner approval.
- When the master key is missing, the UI reports only that secure credential storage is not set up on the server, which is an operator task. It offers no way to enter or manage that key.

## Encryption at rest

Secrets are sealed with libsodium XChaCha20-Poly1305 (`CredentialCipher`).

- **Key material** is the platform master key (see Two secret classes). It lives outside the database, as the `QSD_CREDENTIAL_KEY` server constant (base64 of 32 random bytes) provisioned by the operator. This is the same rule `MailService` uses for SMTP credentials.
- **Nonces.** Every seal uses a fresh random nonce.
- **Field binding.** Additional data binds each ciphertext to its provider and field.
- **Key id.** A key-id fingerprint detects a value sealed under another key.
- **Fail closed.** With no valid key, secret saves are refused (409) and nothing decrypts. A plaintext value is never accepted as a secret.

## Permission

- **Safe state.** Reading connection state (`configured`, `encryption.available`) and saving non-secret configuration need the platform capability `manage_qsd`.
- **Secrets.** Setting, replacing or clearing a secret, and disconnecting a provider (which removes its secrets), need the WordPress administrator capability `manage_options` (`CredentialAuthority`). A business platform manager holds only `manage_qsd` and is refused with 403.
- **Gates.** `PUT /admin/settings/connections/{provider}` is gated by `requireSaveAuthority`: a request that carries a non-empty secret or a `clear` needs administrator authority. `DELETE` is gated by `requireSecretAuthority`.
- **Projection.** The list reports `permissions.manage_secrets`, so the lane makes secret inputs read-only and hides Disconnect for anyone else. The server enforces the rule either way.
- **Runtime validation.** `POST /admin/settings/security/broker-validation` needs both `manage_qsd` and `manage_options`, because it uses the stored credential for one provider call.
- No new role or capability family is created.

## Key operations

These are **platform operator** tasks on the server. They are never business-admin steps, and never Admin Station features.

1. **Provision.** The operator defines `QSD_CREDENTIAL_KEY` in the server configuration (`wp-config.php`), outside the database: base64 of 32 random bytes (`php -r "echo base64_encode(random_bytes(32));"`). It is never stored in the database, logged or sent over HTTP.
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

- **One brokered scope.** Rezdy declares only `connection.verify`, performed by `RezdyConnectionCheck`.
  - It sends one read-only `GET products?limit=1` and reads only the HTTP status and `requestStatus.success`; the body is discarded.
  - It returns outcome (`authenticated`, `unauthorized`, `rate_limited`, `upstream_error`, `unexpected_response`, `network_error`), HTTP status and latency. It never returns the key, the URL, the body or upstream error text.
  - Security Phase 2 bound: it runs only when the environment is `staging` (`https://api.rezdy-staging.com/v1/`). Any other environment returns `refused_environment` without a request.
  - Importer scopes and operations are Phase 3 work, after the Owner's Rezdy importer reference is audited.
- **Allow-listed callers.** The only caller is `settings.security-validation`, for `rezdy:connection.verify`.
- **Runtime validation route.** The broker itself has no REST route. `BrokerValidation`, behind the route above, runs one fixed sequence on the real install for the session user:
  - storage evidence: each secret is an envelope that opens under the current key, with no plaintext in the option;
  - rotation readiness from `CredentialRotation::inspect()`, which is read-only;
  - issue → hash-only bound row → consume and perform (the single provider call);
  - replay refused; expiry refused; an abandoned key swept; a binding mismatch refused and the key burned; no row left behind;
  - audit events for the run, with no key, hash or secret.
  It ignores the request body. Its report holds safe metadata only and is checked for keys, key hashes, secrets and the key id before it is returned.
- **Real credentials.** For Phase 2 validation on staging2 only:
  - the operator provisions the platform master key on the server;
  - an administrator enters one real Rezdy staging API key through the QSD UI.
  Production stays out of scope.

See [Settings and Security](../code-map/settings-station.md).
