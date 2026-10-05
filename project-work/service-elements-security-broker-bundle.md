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
