import { SidebarProvider } from '@/components/ui/sidebar';
import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

interface AppShellProps {
  children: React.ReactNode;
}

export function AppShell({ children }: AppShellProps) {
  const isOpen = usePage<SharedData>().props.sidebarOpen;
  return (
    <SidebarProvider defaultOpen={isOpen}>
      {children}
    </SidebarProvider>
  );
}
