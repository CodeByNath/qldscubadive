# Admin Station Drawer

The host participates in the locked [Station and Drawer Lifecycle Contract](../architecture/StationDrawerLifecycleContract-v1.md): it transports native identity and the mounted footer slot; the owning Station performs every create, save, settle, activate, mask, travel, and delete operation.

Admin Station owns one entity-agnostic drawer shell. Station Manager resolves registrations; the owning Station supplies its adapter, composition, state, validation, saves. Hosting never transfers authority.

## Registration and runtime

`drawerTypes.ts` defines key, native identity, mode, optional size, and shell bridge. `drawerTemplates.ts` registers contracts and rejects duplicate keys or empty modes. Unknown keys resolve to the shell's neutral unavailable state.

Registration ownership is:

| Key | Registrar | Host / composition owner | Size | Modes |
| --- | --- | --- | --- | --- |
| `category` | Admin Station | Admin Category host adapter / neutral Category drawer | normal | view, edit |
| `service` | Service Station | Service Station | normal | view, edit |

`DrawerMode` is `'view' | 'edit'`; there is no `create` mode. A registration may declare only `view`; the shell clamps a requested mode to what the template supports. Service and Category use a stable `'new'` sentinel that resolves to `null`, never a fabricated identity, and its station holds pending state locally. Service and Category Overview Save create and hand off Pending records; Service child actions lock until then, and Publish settles and activates them.

## Drawer size

`DrawerSize` is `'normal' | 'wide' | 'extra-wide'`. A registration declares one size for every mode, or a `DrawerSizeByMode` map (a size per `DrawerMode`) for content needing more room in one mode than another — an omitted mode, like an absent `size`, resolves to `normal`. `AdminStationDrawer` resolves the declared size against the mode that will render (clamped to supported modes, exactly as content rendering is) into a CSS modifier; the shell never branches on entity type, and wide drawers still yield to the viewport. No current registration is mode-keyed; the capability exists for editors that need a wider table.

The runtime chain is:

```text
kit action with native record id
  → StationSurfaceHost resolves binding action intent
  → AdminStationDrawerContext stores key, id, mode, and wall refetch
  → AdminStationDrawer resolves the registered contract
  → owning Station adapter resolves its record
  → owning composition renders and mutates
  → onSaved refreshes only the originating wall
```

`shell/drawer/AdminStationDrawer.tsx` owns chrome, close guards, scroll lock, focus restoration, and the optional footer. It never switches on entity type.

`AdminStationDrawerContext.tsx` keeps one open drawer and preserves identity across mode changes. Closing clears both state and the originating-wall refetch handle; a late save cannot refresh a wall the user has left.

## Owning compositions

- `admin-station/stations/serviceCategory/CategoryDrawerHost.tsx` mounts the Category composition and uses numeric Category identity, or the stable `'new'` sentinel resolved to `category: null`, and mounts the SAME composition either way.
- `service-station/surface/ServiceDrawerHost.tsx` mounts the Service composition and uses numeric Service identity, or the stable `'new'` sentinel resolved to `service: null`, and mounts the SAME composition either way.

Each composition uses the shared `drawer-kit`; Category and Service writes remain in their owning hooks. Presentation components call no endpoints.

## Invariants

- Record ids pass through without parsing, stringification, or numeric coercion.
- The generic shell never saves domain data directly.
- Drawer registrations are capabilities; Admin's surface policy chooses which key a bound action opens.
- Module editing replaces only the active module; sibling modules remain readable.

## Related Code Maps

[Station Manager](station-manager.md), [Admin Station](admin-station.md), [Cards](admin-station-cards.md), [Drawer System](drawer-system.md), and [Entity Drawer Compositions](entity-drawer-recovery.md).
