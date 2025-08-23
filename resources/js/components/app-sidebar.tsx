import { NavFooter } from '@/components/nav-footer';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { type NavGroup as NavGroupType } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Barcode, LayoutGrid, Users } from 'lucide-react';
import AppLogo from './app-logo';

import { NavGroup } from './nav-group';


const GeneralGroup: NavGroupType = {
  title: 'General',
  items: [
    {
      title: 'Dashboard',
      href: '/dashboard',
      icon: LayoutGrid,
    },
    {
      title: 'Visitors',
      href: '/visitors',
      icon: Barcode,
    },
    {
      title: 'Postbacks',
      icon: Barcode,
      subItems: [
        {
          title: 'List',
          href: '/postbacks',
          icon: Barcode,
        },
        {
          title: 'Create',
          href: '/postbacks/create',
          icon: Barcode,
        },
        {
          title: 'Logs',
          href: '/postbacks/logs',
          icon: Barcode,
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
  ],
};

export function AppSidebar() {
  const page = usePage<SharedData>();
  const { props, url } = page;
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
          <NavGroup key={group.title} title={group.title} items={group.items} currentHref={url} />
        ))}
      </SidebarContent>
      <SidebarFooter>
        {isAdmin && <NavFooter items={AdminGroup.items} title={AdminGroup.title} className="mt-auto" />}
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
