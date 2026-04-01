import type { BreadcrumbItem } from '@/types'
import type { Workflow } from '@/types/ping-post'
import { route } from 'ziggy-js'

export const indexBreadcrumbs: BreadcrumbItem[] = [
  { title: 'Share Leads', href: '#' },
  { title: 'Workflows', href: route('ping-post.workflows.index') },
]

export const createBreadcrumbs: BreadcrumbItem[] = [
  { title: 'Share Leads', href: '#' },
  { title: 'Workflows', href: route('ping-post.workflows.index') },
  { title: 'Create', href: route('ping-post.workflows.create') },
]

export const editBreadcrumbs = (workflow: Workflow): BreadcrumbItem[] => [
  { title: 'Share Leads', href: '#' },
  { title: 'Workflows', href: route('ping-post.workflows.index') },
  { title: workflow.name, href: route('ping-post.workflows.show', workflow.id) },
  { title: 'Edit', href: route('ping-post.workflows.edit', workflow.id) },
]

export const showBreadcrumbs = (workflow: Workflow): BreadcrumbItem[] => [
  { title: 'Share Leads', href: '#' },
  { title: 'Workflows', href: route('ping-post.workflows.index') },
  { title: workflow.name, href: route('ping-post.workflows.show', workflow.id) },
]
