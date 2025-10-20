import { NavFooter } from '@/components/nav-footer';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { type NavGroup as NavGroupType } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Cog, Factory, FileText, LayoutGrid, List, Shield, TextCursorInput, Users, Webhook } from 'lucide-react';
import AppLogo from './app-logo';

import { NavGroup } from './nav-group';

const GeneralGroup: NavGroupType = {
  title: 'General',
  items: [
    {
      title: 'Dashboard',
      href: '/',
      icon: LayoutGrid, // ✅ Perfecto para dashboard
    },
    {
      title: 'Visitors',
      href: '/visitors',
      icon: Users,
    },
    {
      title: 'Postbacks',
      icon: Webhook,
      subItems: [
        {
          title: 'Queue',
          href: '/postbacks',
          icon: List,
        },
      ],
    },
    {
      title: 'Forms',
      icon: FileText,
      subItems: [
        /* {
          title: 'List',
          href: '/forms',
          icon: List,
        }, */
        {
          title: 'Fields',
          href: '/forms/fields',
          icon: TextCursorInput,
        },
      ],
    },
    {
      title: 'Offerwall',
      icon: Webhook, // Using Webhook icon as a placeholder
      subItems: [
        {
          title: 'Mixes',
          href: route('offerwall.index'),
          icon: List,
        },
        {
          title: 'Conversions',
          href: route('offerwall.conversions'),
          icon: List,
        },
      ],
    },
    {
      title: 'Logs',
      icon: List, // Using List icon as a placeholder
      subItems: [
        {
          title: 'Offerwall Mixes',
          href: route('logs.offerwall-mixes.index'),
          icon: List,
        },
      ],
    },
    {
      title: 'Settings',
      icon: Cog,
      subItems: [
        {
          title: 'Companies',
          href: route('companies.index'),
          icon: Factory,
        },
        {
          title: 'Integrations',
          href: route('integrations.index'),
          icon: Webhook,
        },
      ],
    },
  ],
};
const navGroups: NavGroupType[] = [GeneralGroup];

const AdminGroup: NavGroupType = {
  title: 'Admin',
  items: [
    {
      title: 'Users',
      href: '/users',
      icon: Users,
    },
    {
      title: 'Whitelist',
      href: route('whitelist.index'),
      icon: Shield,
    },
  ],
};

export function AppSidebar() {
  const page = usePage<SharedData>();
  const { props, url: currentUrl } = page;
  const { auth } = props;
  const isAdmin = auth.user?.role === 'admin';
  return (
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link href="/dashboard" prefetch>
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent>
        {navGroups.map((group) => (
          <NavGroup key={group.title} title={group.title} items={group.items} currentHref={currentUrl} />
        ))}
      </SidebarContent>
      <SidebarFooter>
        {isAdmin && <NavFooter items={AdminGroup.items} title={AdminGroup.title} className="mt-auto" />}
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
