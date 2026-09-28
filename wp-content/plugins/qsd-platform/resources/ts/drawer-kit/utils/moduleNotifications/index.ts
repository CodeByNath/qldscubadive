// Module notifications barrel — preserves the original single-file public
// surface (`@/drawer-kit/utils/moduleNotifications`). The shared engine and the
// per-Station rule groups live in the sibling files; a new Station adds its
// own rule file here and exports it below.

export * from './shared';
export * from './service';
export * from './category';
