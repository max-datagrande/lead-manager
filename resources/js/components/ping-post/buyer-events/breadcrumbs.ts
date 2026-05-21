import type { BreadcrumbItem } from '@/types';
import { route } from 'ziggy-js';

export const indexBreadcrumbs: BreadcrumbItem[] = [
  { title: 'Share Leads', href: '#' },
  { title: 'Buyer Events', href: route('ping-post.buyer-events.index') },
];
