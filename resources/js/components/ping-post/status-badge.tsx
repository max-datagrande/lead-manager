import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

// ─── Dispatch Status ──────────────────────────────────────────────────────────

const dispatchColors: Record<string, string> = {
  pending: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
  running: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  sold: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  not_sold: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  error: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  timeout: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
}

const pingColors: Record<string, string> = {
  skipped: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
  duplicate: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
  ineligible: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  cap_exceeded: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  accepted: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  rejected: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  timeout: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
  error: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
}

const postColors: Record<string, string> = {
  posted: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  accepted: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  rejected: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  error: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  timeout: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
  retry_queued: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300',
  pending_postback: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  postback_resolved: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
  skipped: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
}

const formatLabel = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())

interface Props {
  status: string
  variant?: 'dispatch' | 'ping' | 'post'
  className?: string
}

export function StatusBadge({ status, variant = 'dispatch', className }: Props) {
  const map = variant === 'ping' ? pingColors : variant === 'post' ? postColors : dispatchColors
  const color = map[status] ?? 'bg-gray-100 text-gray-600'
  return (
    <Badge variant="outline" className={cn('border-0 font-medium', color, className)}>
      {formatLabel(status)}
    </Badge>
  )
}
