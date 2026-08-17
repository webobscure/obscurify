/**
 * Shared Escape-to-close + focus-trap behavior for Modal/Drawer — see
 * docs/design/DESIGN_SYSTEM.md section 13 (Modal/Drawer accessibility
 * contract: focus trap, Escape closes, focus returns to the trigger).
 * StoreSwitcher/UserMenu each still hand-roll their own click-outside
 * listener (a smaller, pre-existing duplication noted in
 * DESIGN_SYSTEM.md section 5.10) — not touched here to keep this change
 * scoped to the new Products components.
 */
export function useDismissable(panel: Ref<HTMLElement | null>, isOpen: Ref<boolean>, onClose: () => void) {
  let previouslyFocused: HTMLElement | null = null

  function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
      event.preventDefault()
      onClose()
      return
    }

    if (event.key !== 'Tab' || !panel.value) return

    const focusable = panel.value.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )
    if (focusable.length === 0) return

    const first = focusable[0]!
    const last = focusable[focusable.length - 1]!

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first.focus()
    }
  }

  // `immediate: true` matters here, not just as a style choice: Modal
  // stays mounted and toggles `open` (a real false->true change the
  // watcher sees on its own), but Drawer usages are wrapped in a parent
  // `v-if` (e.g. VariantDetailDrawer in products/[id].vue) — every open
  // is a fresh mount with `isOpen` already `true`, which a non-immediate
  // watch never fires for. Without this, autofocus-on-open silently
  // never ran for any v-if-mounted dialog.
  watch(isOpen, async (open) => {
    if (open) {
      previouslyFocused = document.activeElement as HTMLElement | null
      document.addEventListener('keydown', handleKeydown)
      await nextTick()
      const first = panel.value?.querySelector<HTMLElement>('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
      first?.focus()
    } else {
      document.removeEventListener('keydown', handleKeydown)
      previouslyFocused?.focus()
    }
  }, { immediate: true })

  // A second, distinct gap from the immediate-watch one above: Modal
  // stays mounted and toggles `open` (the watcher's `else` branch runs
  // focus-return), but a v-if-wrapped Drawer usage (e.g.
  // VariantDetailDrawer in products/[id].vue) closes by the *parent*
  // unmounting the whole subtree directly — `isOpen` never transitions
  // to false, so the watcher's `else` branch never runs either. Cover
  // that path here explicitly.
  onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown)
    if (isOpen.value) previouslyFocused?.focus()
  })
}
