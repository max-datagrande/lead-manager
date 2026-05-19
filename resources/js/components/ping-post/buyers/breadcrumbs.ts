import type { BreadcrumbItem } from '@/types';
import type { Buyer } from '@/types/ping-post';
import { route } from 'ziggy-js';

export const indexBreadcrumbs: BreadcrumbItem[] = [
  { title: 'Share Leads', href: '#' },
  { title: 'Buyers', href: route('ping-post.buyers.index') },
];

export const createBreadcrumbs: BreadcrumbItem[] = [
  { title: 'Share Leads', href: '#' },
  { title: 'Buyers', href: route('ping-post.buyers.index') },
  { title: 'Create', href: route('ping-post.buyers.create') },
];

export const editBreadcrumbs = (buyer: Buyer): BreadcrumbItem[] => [
  { title: 'Share Leads', href: '#' },
  { title: 'Buyers', href: route('ping-post.buyers.index') },
  { title: buyer.name, href: route('ping-post.buyers.show', buyer.id) },
  { title: 'Edit', href: route('ping-post.buyers.edit', buyer.id) },
];

export const showBreadcrumbs = (buyer: Buyer): BreadcrumbItem[] => [
  { title: 'Share Leads', href: '#' },
  { title: 'Buyers', href: route('ping-post.buyers.index') },
  { title: buyer.name, href: route('ping-post.buyers.show', buyer.id) },
];
