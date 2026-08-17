import type { NavigationItem } from '~/config/navigation'
import { findActiveBranch, isBranchActive } from '~/config/navigation'

/**
 * Accordion state for sidebar branches (nav items with `children`) — spec:
 * "only the branch containing the current page should be expanded";
 * "only one top-level branch should normally stay open." Keyed by
 * `item.to` (a route, never a translated label) so it works regardless of
 * locale. `useState` makes this a single shared value across every
 * `SidebarSection` instance (desktop + mobile render the same tree, and a
 * page can have several sections), which is what makes the accordion span
 * the whole sidebar rather than just one section.
 *
 * Active-route detection itself lives in navigation.ts's `isBranchActive` /
 * `findActiveBranch` (pure, route-in/boolean-out) — this composable only
 * adds the reactive route binding and the open/closed state on top.
 */
export function useNavigationExpansion() {
  const route = useRoute()
  const expandedBranch = useState<string | null>('admin-sidebar-expanded-branch', () => null)

  /**
   * Call from a SidebarSection watching route.path (immediate: true): if
   * one of its own items is the active branch, claim the shared expanded
   * slot. Sections whose items don't match the route are no-ops here, so
   * whichever section actually contains the active page wins.
   */
  function syncFromItems(items: NavigationItem[]) {
    const active = findActiveBranch(route.path, items)
    if (active) expandedBranch.value = active.to
  }

  function isExpanded(item: NavigationItem): boolean {
    return expandedBranch.value === item.to
  }

  function toggle(item: NavigationItem) {
    expandedBranch.value = expandedBranch.value === item.to ? null : item.to
  }

  return {
    isBranchActive: (item: NavigationItem) => isBranchActive(route.path, item),
    syncFromItems,
    isExpanded,
    toggle,
  }
}
