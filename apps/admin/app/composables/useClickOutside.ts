/**
 * Shared outside-click + Escape-to-close + focus-return behavior for
 * lightweight overlays (Popover/Dropdown/Tooltip) — distinct from
 * useDismissable's full Tab-cycle focus trap, which is correct for
 * Modal/Drawer but wrong for a menu that shouldn't trap Tab. This is the
 * consolidation docs/design/ADMIN_DESIGN_SYSTEM.md calls for in place of
 * StoreSwitcher.vue/UserMenu.vue's independently hand-rolled
 * mousedown-listener duplicates (not migrated onto this yet — see the
 * migration plan; this composable is the target for that later phase).
 */
export function useClickOutside(root: Ref<HTMLElement | null>, isOpen: Ref<boolean>, onClose: () => void) {
  let previouslyFocused: HTMLElement | null = null

  function handleMousedown(event: MouseEvent) {
    if (root.value && !root.value.contains(event.target as Node)) {
      onClose()
    }
  }

  function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
      event.preventDefault()
      onClose()
    }
  }

  watch(isOpen, (open) => {
    if (open) {
      previouslyFocused = document.activeElement as HTMLElement | null
      document.addEventListener('mousedown', handleMousedown)
      document.addEventListener('keydown', handleKeydown)
    } else {
      document.removeEventListener('mousedown', handleMousedown)
      document.removeEventListener('keydown', handleKeydown)
      previouslyFocused?.focus()
    }
  })

  onUnmounted(() => {
    document.removeEventListener('mousedown', handleMousedown)
    document.removeEventListener('keydown', handleKeydown)
  })
}
