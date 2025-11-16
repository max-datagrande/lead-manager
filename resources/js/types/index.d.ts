import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface Auth {
  user: User;
}

export interface BreadcrumbItem {
  title: string;
  href: string;
}

export interface NavGroup {
  title: string;
  items: NavItem[];
}
export interface NavSubItem {
  title: string;
  href: string;
  icon?: LucideIcon;
}
export interface NavItem {
  title: string;
  href?: string;
  icon?: LucideIcon | null;
  isActive?: boolean;
  subItems?: NavSubItem[];
}
type ServicesConfig = Record<string, any>;
export interface SharedData {
  name: string;
  quote: { message: string; author: string };
  auth: Auth;
  ziggy: Config & { location: string };
  sidebarOpen: boolean;
  app: {
    env: string;
    services: ServicesConfig;
  };
  flash: {
    message?: string | string[];
    error?: string | string[];
    success?: string | string[];
    info?: string | string[];
    warning?: string | string[];
  };
  [key: string]: unknown;
}
export interface PageLink {
  url: string | null;
  label: string;
  page: number | null;
  active: boolean;
};

export interface User {
  id: number;
  name: string;
  email: string;
  avatar?: string;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
  [key: string]: unknown; // This allows for additional properties...
}

export interface stateDatatable {
  search: string;
  filters: [];
  sort: string;
  page: number;
  per_page: number;
}
export interface metaDatatable {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

/**
 * Tipo genérico para páginas de datatables
 * @template T - Tipo de los elementos en rows.data
 * 
 * @example
 * ```typescript
 * // Para visitantes
 * type VisitorIndexProps = DatatablePageProps<Visitor>;
 * 
 * // Para usuarios  
 * type UserIndexProps = DatatablePageProps<User>;
 * 
 * // Uso en componente
 * const MyComponent = ({ rows, meta, state, data }: DatatablePageProps<MyItem>) => {
 *   return <Table entries={rows.data} meta={meta} state={state} data={data} />;
 * };
 * ```
 */
export interface DatatablePageProps<T = any> {
  rows: {
    data: T[];
  };
  state: stateDatatable;
  meta: metaDatatable;
  data: Record<string, string>;
}
