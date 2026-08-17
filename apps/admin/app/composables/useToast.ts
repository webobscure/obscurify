export type ToastVariant = 'danger' | 'warning' | 'success' | 'info'

export interface ToastEntry {
  id: string
  variant: ToastVariant
  message: string
}

let counter = 0

/**
 * Imperative toast API — `const toast = useToast(); toast.success('Saved')`
 * — backing a single shared `<Toast>` live region rendered once in
 * AdminShell, so screen readers announce each new toast without
 * re-announcing the whole stack. Danger toasts never auto-dismiss: an
 * error is often the only failure signal a page provides, so hiding it
 * before the user finishes reading is a real friction risk.
 */
export function useToast() {
  const toasts = useState<ToastEntry[]>('admin-toasts', () => [])

  function push(variant: ToastVariant, message: string) {
    const id = `toast-${++counter}`
    toasts.value = [...toasts.value, { id, variant, message }]
    if (variant !== 'danger') {
      setTimeout(() => dismiss(id), 5000)
    }
    return id
  }

  function dismiss(id: string) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  return {
    toasts,
    dismiss,
    success: (message: string) => push('success', message),
    danger: (message: string) => push('danger', message),
    warning: (message: string) => push('warning', message),
    info: (message: string) => push('info', message),
  }
}
