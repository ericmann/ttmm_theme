/**
 * Demo-content script constants (SPEC §5, P0-01, rule 24 amendment: these are
 * script constants, not `Config` keys -- the demo/Playground/GPL-release
 * pipeline runs entirely outside WordPress).
 */

// ⚠️ ASSUMPTION (SPEC §5), tuned in P1-03
export const IMAGE_MAX_BYTES = 350000;

// ⚠️ ASSUMPTION (SPEC §5), tuned in P1-03
export const IMAGE_BUDGET_BYTES = 8000000;

export const OPENVERSE_MIN_WIDTH = 1600;
export const OPENVERSE_PAGE_SIZE = 20;
export const OPENVERSE_PACE_MS = 3500;
export const OPENVERSE_LICENSES = 'cc0,pdm';
