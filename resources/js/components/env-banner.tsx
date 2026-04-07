import type { SharedData } from '@/types'
import { usePage } from '@inertiajs/react'

const NON_PRODUCTION_ENVS = new Set(['local', 'development', 'staging'])

export function EnvBanner() {
  const { app } = usePage<SharedData>().props

  if (!NON_PRODUCTION_ENVS.has(app.env)) {
    return null
  }

  return (
    <div
      className="fixed top-0 left-0 right-0 z-50 h-1.5"
      style={{ backgroundColor: 'var(--sidebar)' }}
      title={app.env.toUpperCase()}
      aria-hidden="true"
    />
  )
}
