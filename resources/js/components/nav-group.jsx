import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  useSidebar,
} from '@/components/ui/sidebar';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function NavGroup({ title, items, currentHref }) {
  const { state } = useSidebar();
  return (
    <SidebarGroup>
      <SidebarGroupLabel>{title}</SidebarGroupLabel>
      <SidebarMenu>
        {items.map((item) => {
          const key = `${title}-${item.title}`;
          if (!item.subItems) return <SidebarMenuLink key={key} item={item} currentHref={currentHref} />;
          if (state === 'collapsed') return <SidebarMenuCollapsedDropdown key={key} item={item} currentHref={currentHref} />;
          return <SidebarMenuCollapsible key={key} item={item} currentHref={currentHref} />;
        })}
      </SidebarMenu>
    </SidebarGroup>
  );
}

const SidebarMenuLink = ({ item, currentHref }) => {
  const { setOpenMobile } = useSidebar();
  return (
    <SidebarMenuItem>
      <SidebarMenuButton asChild isActive={checkIsActive(currentHref, item)} tooltip={item.title}>
        <Link href={item.href} prefetch onClick={() => setOpenMobile(false)}>
          {item.icon && <item.icon />}
          <span>{item.title}</span>
        </Link>
      </SidebarMenuButton>
    </SidebarMenuItem>
  );
};

const SidebarMenuCollapsible = ({ item, currentHref }) => {
  const { setOpenMobile } = useSidebar();
  return (
    <Collapsible asChild defaultOpen={checkIsActive(currentHref, item, true)} className="group/collapsible">
      <SidebarMenuItem>
        <CollapsibleTrigger asChild>
          <SidebarMenuButton tooltip={item.title}>
            {item.icon && <item.icon />}
            <span>{item.title}</span>
            <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
          </SidebarMenuButton>
        </CollapsibleTrigger>
        <CollapsibleContent className="CollapsibleContent">
          <SidebarMenuSub>
            {item.subItems.map((subItem) => (
              <SidebarMenuSubItem key={subItem.title}>
                <SidebarMenuSubButton asChild isActive={checkIsActive(currentHref, subItem)}>
                  <Link
                    href={subItem.href}
                    prefetch
                    onClick={() => {
                      setOpenMobile(false);
                    }}
                  >
                    {subItem.icon && <subItem.icon />}
                    <span>{subItem.title}</span>
                  </Link>
                </SidebarMenuSubButton>
              </SidebarMenuSubItem>
            ))}
          </SidebarMenuSub>
        </CollapsibleContent>
      </SidebarMenuItem>
    </Collapsible>
  );
};

const SidebarMenuCollapsedDropdown = ({ item, currentHref }) => {
  return (
    <SidebarMenuItem>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <SidebarMenuButton tooltip={item.title} isActive={checkIsActive(currentHref, item)}>
            {item.icon && <item.icon />}
            <span>{item.title}</span>
            <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent side="right" align="start" sideOffset={4}>
          <DropdownMenuLabel>{item.title}</DropdownMenuLabel>
          <DropdownMenuSeparator />
          {item.subItems.map((sub) => (
            <DropdownMenuItem key={`${item.title}-${sub.title}`} asChild>
              <Link href={sub.href} prefetch className={`${checkIsActive(currentHref, sub) ? 'bg-secondary' : ''}`}>
                {sub.icon && <sub.icon />}
                <span className="max-w-52 text-wrap">{sub.title}</span>
              </Link>
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  );
};

function checkIsActive(currentHref, item, mainNav = false) {
  return (
    currentHref === item.href || // /endpint?search=param
    currentHref.split('?')[0] === item.href || // endpoint
    !!item?.items?.filter((i) => i.href === currentHref).length || // if child nav is active
    (mainNav && currentHref.split('/')[1] !== '' && currentHref.split('/')[1] === item?.href?.split('/')[1])
  );
}
