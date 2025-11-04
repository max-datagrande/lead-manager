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
