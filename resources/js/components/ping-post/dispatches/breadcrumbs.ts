import type { BreadcrumbItem } from '@/types'
import type { LeadDispatch } from '@/types/ping-post'
import { route } from 'ziggy-js'

export const indexBreadcrumbs: BreadcrumbItem[] = [
  { title: 'Share Leads', href: '#' },
  { title: 'Dispatch Logs', href: route('ping-post.dispatches.index') },
]

export const showBreadcrumbs = (dispatch: LeadDispatch): BreadcrumbItem[] => [
  { title: 'Share Leads', href: '#' },
  { title: 'Dispatch Logs', href: route('ping-post.dispatches.index') },
  { title: dispatch.dispatch_uuid.slice(0, 8), href: route('ping-post.dispatches.show', dispatch.id) },
]

export const timelineBreadcrumbs = (dispatch: LeadDispatch): BreadcrumbItem[] => [
  { title: 'Share Leads', href: '#' },
  { title: 'Dispatch Logs', href: route('ping-post.dispatches.index') },
  { title: dispatch.dispatch_uuid.slice(0, 8), href: route('ping-post.dispatches.show', dispatch.id) },
  { title: 'Timeline', href: route('ping-post.dispatches.timeline', dispatch.id) },
]
