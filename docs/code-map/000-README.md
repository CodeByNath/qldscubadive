# Repository Code Map

The Code Map points to the **current implementation**: the small set of authoritative files needed to understand and safely change a subsystem. It is separate from [Project History](../project-history/000-README.md), which records completed milestones. Code maps are updated in place when code moves.

## Subsystem Index

### Admin Station platform

- [Station Manager](station-manager.md)
- [Admin Station](admin-station.md)
- [Admin Station Navigation & Resolver](admin-station-navigation.md)
- [Admin Station Surface Binding](admin-station-surface-binding.md)
- [Admin Station Drawer](admin-station-drawer.md)
- [Entity Drawer Compositions](entity-drawer-recovery.md)
- [Admin Station Home Shell](admin-station-home-shell.md)
- [Admin Station Styles](admin-station-styles.md)
- [Station Tab Set](station-tab-set.md)
- [Admin Station List System](admin-station-list-system.md)
- [Admin Station Cards](admin-station-cards.md)
- [Drawer System](drawer-system.md)
- [Lifecycle and Module State](lifecycle-system.md)

### Domain Stations

- [Service Station](service-station.md)
- [Service Elements](service-elements.md)
- [Service Catalogue](service-catalogue.md)
- [Service Connections](service-connections.md)
- [Categories](categories.md)

### Configuration

- [Settings Station](settings-station.md)

### Shared backend

- [Platform Identifier Station](platform-identifier-station.md)

## How to Use and Maintain This Map

This README is the routing index; do not treat it as a substitute for a subsystem map. For a first-time task:

1. Choose the closest subsystem from the index above. If a task crosses boundaries, begin with the primary owning subsystem and follow only its necessary **Related Code Maps** links.
2. Read that subsystem map before searching or modifying source.
3. Read [Project History guidance](../project-history/000-README.md) only when historical decisions are needed.
4. Open the authoritative source links from the selected map and proceed.

**Do not load every Code Map file automatically.** If no map clearly owns the task, inspect only enough source to identify the owner, then create or correct a focused map rather than reading every map.

A source move is incomplete until imports and tests/contracts are updated, affected Code Maps and local instructions reflect the new ownership, links and paths are verified (`npm run docs:check`), and generated output is rebuilt. Keep each subsystem map at no more than 600 words. A new Station gets its own map, indexed exactly once above.
