import { type BreadcrumbItem } from '@/types';
import { route } from 'ziggy-js';

export const createBreadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Integrations',
    href: route('integrations.index'),
  },
  {
    title: 'Create',
    href: route('integrations.create'),
  },
];

export const indexBreadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Integrations',
    href: route('integrations.index'),
  },
];

