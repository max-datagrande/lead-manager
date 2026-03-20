import { NavFooter } from '@/components/nav-footer';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { type NavGroup as NavGroupType } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bug, Coins, Cpu, Factory, FileText, FlaskConical, LayoutGrid, LayoutList, List, Shield, TextCursorInput, Users, Webhook, Layers, Globe } from 'lucide-react';
import AppLogo from './app-logo';
import { NavGroup } from './nav-group';

const DashboardGroup: NavGroupType = {
  title: 'Dashboard',
  items: [
    {
      title: 'Overview',
      href: '/',
      icon: LayoutGrid,
    },
  ],
};

const LeadManagementGroup: NavGroupType = {
  title: 'Lead Management',
  items: [
    {
      title: 'Visitors',
      href: '/visitors',
      icon: Users,
    },
    {
      title: 'Forms',
      icon: FileText,
      subItems: [
        {
          title: 'Fields',
          href: '/forms/fields',
          icon: TextCursorInput,
        },
      ],
    },
  ],
};

const PostbacksGroup: NavGroupType = {
  title: 'Postbacks',
  items: [
    {
      title: 'List',
      href: route('postbacks.index'),
      icon: List,
    },
    {
      title: 'Executions',
      href: route('postbacks.executions.index'),
      icon: LayoutList,
    },
    {
      title: 'Queue Legacy',
      href: route('postbacks.queue-legacy.index'),
      icon: LayoutList,
    },
    {
      title: 'Platforms',
      href: route('platforms.index'),
      icon: Cpu,
    },
  ],
};

const OfferwallGroup: NavGroupType = {
  title: 'Offerwall',
  items: [
    {
      title: 'List',
      href: route('offerwall.index'),
      icon: LayoutList,
    },
    {
      title: 'Conversions',
      href: route('offerwall.conversions'),
      icon: Coins,
    },
    {
      title: 'Logs',
      href: route('logs.offerwall-mixes.index'),
      icon: Bug,
    },
    {
      title: 'Tester',
      href: route('offerwall.tester.index'),
      icon: FlaskConical,
    },
  ],
};

const SystemGroup: NavGroupType = {
  title: 'Settings',
  items: [
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
    {
      title: 'Verticals',
      href: route('verticals.index'),
      icon: Layers,
    },
    {
      title: 'Landing Pages',
      href: route('landing_pages.index'),
      icon: Globe,
    },
  ],
};

const navGroups: NavGroupType[] = [DashboardGroup, LeadManagementGroup, PostbacksGroup, OfferwallGroup, SystemGroup];

const AdminGroup: NavGroupType = {
  title: 'Admin',
  items: [
    {
      title: 'Users',
      href: route('admin.users.index'),
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
              <Link href="/" prefetch>
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
