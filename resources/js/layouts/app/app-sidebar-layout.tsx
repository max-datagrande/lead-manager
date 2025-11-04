import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ToastProvider, Toaster } from '@/components/ui/toaster';
import { ModalProvider } from '@/hooks/use-modal';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren, type ReactNode } from 'react';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
  return (
    <AppProvider>
      <AppShell>
        <AppSidebar />
        <AppContent variant="sidebar" className="overflow-x-hidden">
          <AppSidebarHeader breadcrumbs={breadcrumbs} />
          {children}
        </AppContent>
      </AppShell>
    </AppProvider>
  );
}
function AppProvider({ children }: { children: ReactNode }) {
  return (
    <ModalProvider>
      <Toaster />
      <ToastProvider>{children}</ToastProvider>
    </ModalProvider>
  );
}
