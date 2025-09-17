import { type BreadcrumbItem } from '@/types';
import { IntegrationDB } from '@/types/integrations';
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
export const editBreadcrumbs = (integration: IntegrationDB) => {
  return [
    {
      title: 'Integrations',
      href: route('integrations.index'),
    },
    {
      title: 'Edit',
      href: route('integrations.edit', integration.id),
    },
  ];
};

export const indexBreadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Integrations',
    href: route('integrations.index'),
  },
];
