# Service Element + Security Broker foundation bundle

Status: BUILDER ACTION REQUIRED
Phase: Post-Settings foundation — Service Element composition + credential broker
Actor: Builder

## Owner-approved architecture

Settings foundation is accepted on `main` at `17f47b563ee39726003197806bb539d7736e1365`.

This next package is intentionally large. Complete the authorised architecture/document updates, Service Element value/composition foundation, credential-broker/request-key foundation, tests and Code Maps on one topic branch.

### Service Element composition

Replace the flat future Service Meta value model with identity-preserving composition:

- Settings owns reusable Element definitions/templates.
- Instantiated Elements belong to Service Station under the owning Service's existing `QSDS` Platform identity.
- Every instantiated Element carries a stable Service-child identity. Durable address is the parent Service identity plus child identity; child identity never replaces or flattens `QSDS`.
- Ordinary Element, Group, Repeater, gallery and nested child composition use one law.
- Group = container Element carrying child Elements.
- Repeater = container Element carrying repeated row instances.
- Editable/reorderable rows and gallery entries require stable child identities; never use position, label, slug or sort order as identity.
- Settings definition identity and Service instance identity stay separate: definition = what the Element is; Service child identity = which instance exists on that Service.
- Removal remains non-destructive retire/detach until explicit purge/recovery semantics are approved.

Before minting any Platform ID family, run the architecture skill audit. Default expectation is parent-qualified rung-2 child identity; a new Platform family requires explicit evidence and Reviewer/Owner approval.

### Connections/Security broker

Connections/Security becomes a credential broker:

- consumers never receive the stored long-lived provider credential directly;
- consumer requests narrowly scoped authority;
- broker issues a cryptographically random short-lived, single-use request key bound to provider + scope + caller, optionally also Service/import operation;
- request key is a security artifact, never a Platform ID;
- persist only a hash plus safe metadata;
- enforce TTL, scope, caller binding and atomic one-time consumption;
- safe audit metadata may record request id, provider, scope, caller and issued/used/expired timestamps, never secrets;
- after successful consumption, broker accesses/decrypts the long-lived provider credential server-side and performs or authorises the provider operation;
- replay must fail.

Real provider credentials remain blocked until encryption-at-rest and credential permission are implemented and reviewed.

## Authority/read order

1. `AGENTS.md`
2. `docs/ai-index.md`
3. `docs/roadmap.md`
4. `skills/qsd-platform-architecture/SKILL.md` + identity/domain ownership references
5. `docs/architecture/service-meta-schema-contract.md`
6. `docs/code-map/settings-station.md`
7. `docs/code-map/service-station.md`
8. `docs/code-map/platform-identifier-station.md`
9. authoritative Settings + Service + PlatformIdentifier source
10. lifecycle contract where the Service module/editor touches drawer lifecycle

## Work package

### A. Make repository authority match Owner-approved architecture
Update the current draft Service Meta contract, roadmap, relevant Code Maps and architecture-skill references so they describe:
- definition vs Service Element instance identity;
- parent-qualified Service child composition;
- Group/Repeater/gallery child identity;
- Service ownership of instantiated values/composition;
- security-broker/request-key model and its boundaries.

Do not leave the current flat `{"<fld_id>": value}` contract as authoritative.

### B. Service Element value/composition foundation
Implement the Service-owned value-side foundation needed for the approved model.

At minimum:
- persisted/draft Service Element collection owned by Service Station;
- stable server-minted child ids for Element instances and nested mutable/reorderable children;
- definition reference remains separate from instance id;
- Group and Repeater shells preserve nested identity through create/update/reorder;
- gallery entries preserve identity through reorder/remove/restore semantics where applicable;
- sanitize/validate against active Settings definitions;
- draft → settle → projection path follows the existing Service module lifecycle;
- no Settings write may mutate Service values;
- no position/label/sort-order matching;
- retired Settings definitions do not destructively erase stored Service Element data;
- public projection remains out of scope unless current Service module contract requires an internal settled projection for future use.

If full presentation/editor work is too large, complete the backend/module/value model and only the minimum Admin UI needed to prove authoritative editing without inventing a second editor system.

### C. Credential broker/request-key foundation
Implement provider-neutral server-side broker primitives where the approved security rules are clear:
- issue request key;
- hash-at-rest token record;
- provider/scope/caller binding;
- TTL;
- atomic consume once;
- replay rejection;
- expiry rejection;
- safe audit metadata;
- broker-owned access to provider credential after successful consumption.

Do not expose stored provider credentials to caller code.

For encryption at rest:
- if repository/runtime authority supports a clear WordPress-safe encryption design with key material outside the stored ciphertext, implement it with tests;
- otherwise stop that sub-part as `BLOCKED — DECISION REQUIRED` and keep real credentials prohibited. Do not invent insecure reversible obfuscation.

Credential-management permission may remain `manage_qsd` only if the Builder can justify it from current platform access authority; otherwise record the narrower capability decision gate.

### D. Tests/contracts
Add focused deterministic tests for:
- Service parent/child identity stability across rename/reorder;
- Group child identity stability;
- Repeater row identity stability;
- gallery-entry identity stability where editable;
- definition id vs Service instance id separation;
- retired definition retains Service data;
- draft → settle identity preservation;
- Settings cannot mutate Service values;
- request token random issuance shape without exposing provider secret;
- hashed token storage;
- TTL enforcement;
- caller/scope/provider binding;
- single-use atomic consume;
- replay rejection;
- secret never returned to consumer;
- existing Settings/Service lifecycle contracts remain green.

Run:
- `npm test`
- `npm run docs:check`

## HARD REZDY OWNER CHECKPOINT

Before designing or implementing ANY:
- Rezdy product/service mapping;
- provider-field → Service Element mapping;
- importer transformations;
- canonical Service import orchestration;
- manual/import convergence rules beyond the generic foundation;

STOP and record:

`OWNER INPUT REQUIRED — obtain and audit the Owner's pre-built Rezdy importer reference before importer design continues.`

Do not reverse-engineer a replacement or guess its contract.

## Hard exclusions

- No production or staging deployment.
- No live Rezdy/Stripe/provider calls.
- No real provider credentials.
- No aggregator/multi-supplier architecture.
- No new Platform ID family without explicit architecture evidence and approval.
- No ownership transfer of Service values to Settings/Admin/Station Manager.
- No destructive field/value purge.
- No importer/mapping work before Owner reference.

## Builder execution model

Use one topic branch. Complete all non-blocked A–D work before handoff. Do not stop at arbitrary sub-phase boundaries.

Stop only when:
- an unresolved architecture/security decision materially blocks safe implementation;
- a new Platform ID family appears necessary;
- encryption/key-management cannot be safely grounded in repository/runtime authority;
- the Rezdy Owner checkpoint is reached.

## Handoff

Push one exact topic SHA and update this file to `AWAITING REVIEWER REVIEW` with:
- changed files;
- identity model actually implemented;
- Service Element persistence/draft/settle/projection evidence;
- Group/Repeater/gallery identity evidence;
- security-broker/token evidence;
- encryption/permission decision or blocker;
- tests/checks;
- confirmation no live credentials/deployment/importer work occurred;
- explicit note if Owner's importer reference is now required.

## Builder handoff

Candidate: `service-elements/elements-security-broker` @ `8ea707b8513564aba457965d275decbb7efe5ee7`. This is one commit on top of accepted `main` `17f47b563ee39726003197806bb539d7736e1365`. Remote verified.

### Identity model implemented

The architecture skill audit was run against the actual cross-Station consumer path (Safeguard 2):

- **Owning Service.** It keeps `QSDS` unchanged.
- **Definitions.** These stay rung 2 inside the Settings schema (`fld_`/`opt_`). The definition id says *what* an Element is and is never used as an instance id.
- **Instances.** These are rung 2 inside their Service, parent-qualified, with durable address (`QSDS…`, child id).
  - Child ids are `el_` (an Element at any depth), `row_` (a Repeater row) and `ent_` (a gallery entry).
  - Each is 10 characters from the Platform ID alphabet, minted only by the Service write boundary and unique within the Service.
- **No new Platform ID family.** Nothing outside the owning Service addresses an instance independently. A future public projection or importer reaches instances through the Service. Revisit only if a second parent must reference an instance directly.
- **Cross-Station definition reference.** This is safe because definitions are never deleted, a type is immutable, and option and sub-field ids cannot be dropped.
- **Request keys.** These are security artifacts (`qrk_…`, audit handle `req_…`), never Platform IDs. A contract enforces that the Security code has no PlatformIdentifier use.

### A. Authority updated

- **Composition contract.** `docs/architecture/service-meta-schema-contract.md` was renamed and rewritten as `docs/architecture/service-element-composition-contract.md`. The flat `{"<fld_id>": value}` model is explicitly superseded.
- **New broker contract:** `docs/architecture/credential-broker-contract.md`.
- **New Code Map:** `docs/code-map/service-elements.md`, indexed once.
- **Updated:**
  - `settings-station.md` and `service-station.md` (route baseline is now 28);
  - `station-tab-set.md`, `docs/roadmap.md` and `docs/ai-index.md`;
  - skill references `domain-ownership-map.md`, `identity-composition-model.md` and `platform-id-families.md`;
  - local `CLAUDE.md` files for the Settings and Service backend and frontend.
- **`agents/openai.yaml`** is unchanged. It mirrors `SKILL.md`, which did not change, and the Platform ID vocabulary is unchanged.

### B. Service Element value/composition foundation

- **Settings side.**
  - `ServiceMetaSchema` gains the `group` container type. Sub-fields of a group or repeater are non-container types.
  - The new read-only `ServiceMeta/ServiceElementDefinitions` is the only path from Service to definitions. `Core\Plugin` injects it into `ServiceModule`.
- **Service side.** `Support/ServiceElements.php` applies one identity law to every level: top-level Element, Group `children`, Repeater `rows[].children`, and gallery `entries`.
  - Ids are minted for new nodes only, and a client-coined id is refused (422).
  - Matching is by id only, never position, label or sort order.
  - `definition_id` is immutable per instance.
  - An omitted node is carried forward as `detached` with its id and value. A status-less resend keeps its prior status, and only an explicit `active` restores.
  - Instances of retired or missing definitions are frozen: carried verbatim, never editable or newly addable.
  - Values are validated against the active definition: number, yes/no, select **option id** (not label), and attachment reference.
  - At most one active instance per definition per slot.
- **Persistence.** `qsd_service_elements` (canonical) and `qsd_service_elements_draft`, each `{version:1, elements}`.
  - `elements` is added to `ServiceSchema::MODULES` and the default module status.
  - `ServiceModules` settles the draft verbatim, resolves the module on activation, and treats it as complete when there is at least one active top-level Element.
  - Elements never gate Publish.
- **Routes.**
  - `GET` and `POST /admin/services/{id}/elements` are new.
  - The settle and revert module regex now includes `elements`.
  - Detail projects `elements` and `drafts.elements`; settle responses project `elements`; the catalogue `has_drafts` includes elements.
  - The route baseline was deliberately regenerated from 26 to 28 routes.
- **Draft → settle → projection.** Saves write only the draft. Publish/Settle-all copies it to canonical with every id preserved. Revert drops only the draft. There is no public projection; it is out of scope.
- **Non-destructive.** Settings writes cannot touch Service values. This is proven byte-for-byte in the test, and a contract check confirms no Settings code references Service post meta.
- **Minimum Admin UI.** A fourth Service drawer module, "Service Elements", built on the existing shell, editor and footer system with no second editor system.
  - Pieces: `serviceElementsShell`, `ServiceElementsEditor.tsx`, `elementsModule` DNA, the `useServiceStation` save/revert/status and the publish-modal summary.
  - New nodes are sent without ids. Removing a saved node sends `detached`. Restoring it, or re-adding its field, revives the same instance. Retired-definition instances show read-only.
  - Service gets definitions from its own Element route, never from the Settings peer.

### C. Credential broker / request keys

- **`Security/CredentialBroker`.**
  - **Issue.** `issue(BrokerGrant)` checks the provider exists, declares the scope, and is configured. The key is `qrk_` + 32 random bytes in base64url, bound to provider, scope, caller component, WP user and an optional subject. TTL defaults to 60 seconds, maximum 300.
  - **Storage.** Only the SHA-256 hash plus safe metadata is stored (`WpdbRequestKeyStore`, non-autoloaded JSON rows).
  - **Consume.** An atomic row `DELETE` (affected rows === 1) consumes the key before the expiry and binding checks. A binding mismatch burns the key. Replay, a concurrent stale read, expiry and forged keys are all refused.
  - **Perform.** Only after consumption does the broker decrypt the credential and hand it as `ProviderSecrets` to a Settings-owned `BrokeredProviderOperation`. The consumer gets only the result. A result containing the secret is withheld, and upstream error text is never returned.
  - **Audit.** `BrokerAuditLog` keeps the newest 200 entries, holding event, request id, provider, scope, caller, user, subject and time. It never holds a key, a hash or a secret.
- **Why `$wpdb` directly.** One-time consumption needs a truly atomic claim. The options-API `add_option`/`delete_option` check-then-act through the object cache, and the existing PID `add_option` + read-back is not strictly atomic under concurrency.
- **Narrowed `ConnectorCredentials`.** It is now Settings-internal, and only the broker reads a decrypted secret. The contract enforces this.
- **Rezdy declares no brokered scope or operation.** No request key can be issued for Rezdy until a reviewed Connector adds them.

### Encryption and permission

- **Encryption at rest: implemented.** It is grounded in repository authority: `MailService` already keeps credentials in wp-config constants, and PHP sodium is available, including PHP 8.2 in CI.
  - `Security/CredentialCipher` seals with XChaCha20-Poly1305 under `QSD_CREDENTIAL_KEY` (base64 of 32 bytes, kept outside the database).
  - Every seal uses a random nonce. AAD binds provider and field, and a key-id fingerprint detects a foreign key.
  - It fails closed. With no key, secret saves get a 409 and nothing is written, and nothing decrypts. A plaintext or legacy value never counts as configured, and there is no obfuscation fallback.
  - The UI shows a no-key warning. No Health check was added, to avoid changing staging health output.
- **DECISION REQUIRED: key operations.** Provisioning `QSD_CREDENTIAL_KEY` on staging and production, plus the rotation procedure (re-sealing stored secrets), are operational Owner decisions. Nothing is deployed.
- **DECISION REQUIRED: credential permission.** `manage_qsd` is retained but **not** justified as sufficient. `PlatformAccess` grants it to business `qsd_platform_manager` users for the admin surfaces generally, so a narrower credential capability, for example one held only by `manage_options` holders, needs an Owner decision.
- **Real provider credentials remain prohibited** until encryption, broker and permission are reviewed.

### D. Tests and checks

- **`tests/service-elements.php` (52 checks).** It runs the real controller and the real Settings schema against an in-memory WordPress, and covers:
  - parent and child id minting and alphabet, and definition-id versus instance-id separation;
  - rename and reorder stability at every level (Element, Group child, Repeater row, gallery entry);
  - detach and restore;
  - draft → settle identity preserved verbatim;
  - revert and activation;
  - retired definitions keeping data (frozen, not detached), and restore re-enabling the same instance;
  - Settings create, update, reorder, retire and restore leaving Service meta byte-identical;
  - the read route, `platform_id` rejection, and client-coined id rejection.
- **`tests/settings-credential-broker.php` (61 checks).** It runs the real broker, `WpdbRequestKeyStore` (with a fake `$wpdb`), the audit log and the cipher, and covers:
  - random key shape and hash-only storage;
  - no secret stored or audited;
  - the TTL default, maximum and expiry edge;
  - single-use consumption, replay rejection, and a racing stale read losing the claim;
  - each of five binding mismatches refused and burned without reaching the credential;
  - secret-in-result withheld and generic failure text;
  - no-operation and undeclared-scope refusals, Rezdy unbrokerable;
  - sweep, audit event coverage and shape, and the audit bound;
  - redaction of the issued key and the secrets handle.
- **`tests/settings-connections.php`, extended.** It adds envelope at rest, no plaintext anywhere, a unique nonce per seal, slot binding, tamper, foreign key, plaintext refusal, and the no-key 409 that writes nothing.
- **`tests/settings-service-meta-schema.php`, extended.** It adds group creation and the nesting refusals.
- **`scripts/service-elements-regression.mjs` (26 checks, mounted real `ServiceDrawerHost`).** Covers:
  - definitions coming via Service's route;
  - retired definitions not addable while their kept instance is sent back untouched;
  - new nodes having no ids;
  - edits, reorders and removals traveling by the minted ids, with `detached` never dropped;
  - restore and re-add reviving the same id;
  - Discard Draft calling only the elements revert;
  - Settle settling through settle-all.
- **Extended contract and regression.**
  - `contract:settings-station` (43 checks) covers the Service → definitions read capability only, no Settings access to Service values, broker-only secret reads, no PlatformIdentifier in Security, Rezdy unbrokered, and the Service frontend never importing settings-station.
  - `regression:settings-home` covers group sub-fields and the no-key warning.
- **Unchanged and still green:** the lifecycle mask, the route baseline (28), drawer-module-entry, module-state snapshot, publish guard and every other existing contract and regression.
- **Results.** `npm test` exits 0: typecheck, all PHP tests, build, 25/25 JS, and docs:check (47 Markdown files, 20 Code Maps). `npm run docs:check` passes.
- **Limitation:** CI runs only on `main`, `staging` and PRs, so topic evidence is the local run. The candidate was not checked in a browser or on a real WordPress install.

### Deviations and limitations for Reviewer judgement

- **Required fields.** `required` is declared but not enforced at Publish readiness. That would change Publish for every existing Service, so it is left for a reviewed step.
- **Projection and media.** There is no public projection. Images are entered as media attachment ids; there is no media picker.
- **One active instance per definition per slot.** This is a presentation and validation rule, not identity. Multiple active instances can be enabled later without changing ids.
- **Frozen instances.** Payload edits to an instance of a retired definition are ignored rather than refused with 422.
- **Extra request.** The Service drawer makes one extra GET (`/elements`) per open to load definitions.
- **Naming.** The Settings UI tab and the code names stay "Service Meta" / `ServiceMetaSchema` for the definition schema. The docs call them Element definitions.
- **Unused seam.** `SettingsModule::credentialBroker()` exists as the seam but has no consumer, and no provider operation is wired.

### Confirmations

- No live Rezdy, Stripe or provider calls. No real credentials. No staging or production deployment.
- No aggregator, no new Platform ID family, no Service value ownership moved, no destructive purge.
- No Project History created.

### OWNER INPUT REQUIRED

Obtain and audit the Owner's pre-built Rezdy importer reference before importer design continues. That covers Rezdy scopes and the brokered operation, product/service → Service Element mapping, importer transformations and import orchestration. None of this was designed or implemented.


## Reviewer decision

Verdict: Stop — architectural risk

Candidate reviewed: `8ea707b8513564aba457965d275decbb7efe5ee7`.

Most of the package follows the approved direction: Service instances are parent-qualified Service children with stable `el_` / `row_` / `ent_` identities; Settings does not own Service values; draft → settle preserves child identity; the broker uses random short-lived single-use keys, hash-only token storage, atomic consume/replay rejection, server-side provider operations, and authenticated encryption at rest; Rezdy mapping/importer work was correctly not started.

### Blocking identity contradiction

The candidate still declares a Settings Element definition (`fld_…`) as **rung 2**, while the same durable definition identity is intentionally referenced and reused by multiple Service records outside the Settings schema.

That conflicts with the architecture skill's locked three-rung rule:

- rung 2 is addressable only inside its parent and is never reused as its own unit elsewhere;
- an identity independently referenced by unrelated parents is the rung-3 test.

The Service child instance itself remains correctly parent-qualified under `QSDS`; this finding concerns the reusable **definition/template identity**, not `el_` / `row_` / `ent_`.

Do not promote this candidate until the Owner chooses the definition identity boundary.

## Owner decision required

Choose one architecture:

1. **Reusable platform definition (recommended by current behavior):** Element definitions are reusable independent platform atoms/templates referenced by many Services. Give the definition its own Platform ID family through existing PlatformIdentifier infrastructure. Service instances keep their separate parent-qualified child identity and store the definition Platform ID as their template reference.

2. **Service-scoped definition:** keep definitions rung 2, but then they cannot be globally reusable durable identities across Services. Each Service must own/copy its definition identity within the Service boundary rather than persistently reference the shared Settings `fld_` identity.

Do not solve this by calling a cross-Station reusable `fld_` id "internal"; durable reuse is the architectural test, not whether the id is public.

### Non-blocking security safeguards

The broker implementation can remain on the candidate branch while the identity decision is resolved. Before any real provider credential or deployment:
- approve/provision `QSD_CREDENTIAL_KEY` and its rotation/re-seal procedure;
- decide credential-management permission. Current `manage_qsd` includes normal business platform managers and is not yet approved as the final secret-management capability.

### Rezdy checkpoint remains locked

The Owner's pre-built Rezdy importer reference is now required **before any next work on Rezdy scopes, brokered Rezdy operations, provider-field → Element mapping, importer transformations, or canonical import orchestration**.

No importer design may begin from this candidate.

## Next action

Owner decides the Element-definition identity model above. After that, Reviewer will issue one bounded Builder correction on this same topic branch; do not restart or discard the otherwise-reviewed Service-child/security work.


## Owner sequencing decision — split by phase, Security first

The Owner has now resolved sequencing. Do **not** hold Security behind the Service Element definition-identity question.

### Phase 1 — Security System (active now)

Use the existing topic branch `service-elements/elements-security-broker`, but make the candidate **Security-only** before the next handoff.

Keep and complete the authorised Security work:
- provider-neutral Connections/Security foundation;
- `CredentialCipher` encrypted-at-rest storage with fail-closed behavior;
- `CredentialBroker`, scoped/caller/provider/user-bound short-lived request keys;
- hash-only request-key storage;
- atomic single-use consume;
- replay, expiry, forged-key and binding-mismatch rejection;
- safe bounded audit trail with no key/hash/secret leakage;
- broker-only provider-secret access;
- minimum governed `qsd/v1` admin surfaces needed to configure/test Security;
- deterministic tests, docs and Code Maps;
- key provisioning + rotation/re-seal operating contract;
- credential-management permission decision implemented conservatively.

### Remove/defer from this phase

The current candidate also contains Service Element implementation. That work is **not rejected**, but it belongs to Phase 3.

Before next handoff, remove from the Security candidate all product-source changes whose only purpose is:
- Service Element persistence/value module;
- Element drawer/editor UI;
- Group/Repeater/gallery Service instance storage;
- Service route-baseline changes caused only by Elements;
- Element-specific architecture/Code Map claims that would become main authority before the identity decision.

Restore the accepted pre-Element Service behavior from `main` where required. Do not rewrite history or force-push; make a normal corrective commit on the same topic branch.

The reusable Element-definition rung/Platform-ID decision is deferred to the **Service Manager phase**. Preserve the analysis in this work file; do not resolve it inside Phase 1.

### Credential permission decision

For Phase 1, provider-secret management must be stricter than ordinary business `manage_qsd` access. Use the existing WordPress administrator-level authority (`manage_options`) for creating, replacing, clearing, or disconnecting provider secrets unless current repository authority provides an already-approved narrower security capability. Safe non-secret connection state may remain visible to ordinary authorised platform managers if architecture permits.

Do not create a new role/capability family solely for this phase.

### Key operations contract

Document and test the rotation model:
1. provision `QSD_CREDENTIAL_KEY` outside the database;
2. stored provider secrets are AEAD envelopes bound to provider+field;
3. key replacement without re-seal fails closed;
4. rotation must decrypt with the old key and re-seal each secret under the new key through an explicit privileged operation/process;
5. never log either key or plaintext;
6. failed/partial rotation must not silently mark unreadable credentials configured.

Do not deploy or provision real secrets in this phase.

### Phase 2 — API/storage/rotation validation (next after acceptance)

After Security Phase 1 is accepted, open one large validation package for controlled provider testing through the broker boundary: encrypted storage, `qsd/v1` flow, request-key issue/consume/replay/expiry, rotation/re-seal and safe audit output.

### Phase 3 — Service Manager tools (later)

Only after Phase 2 acceptance:
- resolve reusable Element-definition identity;
- Service Elements;
- Service Options/variations;
- Rezdy importer and provider → Service mapping;
- manual/import convergence;
- future Service Manager tools.

The Owner's pre-built Rezdy importer PHP reference is now available. In Phase 3 it is behavioral evidence only; QSD implementation must remain PHP backend + `qsd/v1` + TypeScript Admin Station, not the old PHP-admin/ACF architecture.

## Next Builder handoff requirements

Push a new exact SHA on the existing topic branch containing the Security-only candidate and update this file to `AWAITING REVIEWER REVIEW`.

Evidence must include:
- diff showing Service Element implementation is absent/deferred;
- encryption tests;
- permission tests proving secret mutation requires administrator-level authority;
- request-key issuance/storage/TTL/binding/atomic consume/replay tests;
- rotation/re-seal contract/tests or a precise runtime-safe implementation if added;
- no-secret REST/audit/storage evidence;
- `npm test`;
- `npm run docs:check`;
- confirmation of no live credentials, no provider calls, no staging/production deployment.

Do not start Phase 2 or Phase 3 in the same handoff.
