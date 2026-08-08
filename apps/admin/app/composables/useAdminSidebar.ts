/**
 * Mobile-only off-canvas state for the sidebar (spec section 10) — the
 * desktop sidebar is always visible and ignores this. Deliberately just
 * one boolean: collapsing the *expanded* desktop sidebar to a compact
 * rail is explicitly optional this milestone and not implemented.
 */
export function useAdminSidebar() {
  const mobileOpen = useState<boolean>('admin-sidebar-mobile-open', () => false)

  function toggle() {
    mobileOpen.value = !mobileOpen.value
  }

  function close() {
    mobileOpen.value = false
  }

  return { mobileOpen, toggle, close }
}
