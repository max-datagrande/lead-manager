import { LanguageToggle } from '@/components/docs/language-toggle';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useLocale } from '@/hooks/use-locale';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

const sidebarNavItems: NavItem[] = [
  { title: 'overview', href: '/docs/catalyst/overview', icon: null },
  { title: 'installation', href: '/docs/catalyst/installation', icon: null },
  { title: 'visitor', href: '/docs/catalyst/visitor', icon: null },
  { title: 'leads', href: '/docs/catalyst/leads', icon: null },
  { title: 'share_leads', href: '/docs/catalyst/share-leads', icon: null },
  { title: 'lead_quality', href: '/docs/catalyst/lead-quality', icon: null },
  { title: 'validators', href: '/docs/catalyst/validators', icon: null },
  { title: 'offerwall', href: '/docs/catalyst/offerwall', icon: null },
  { title: 'events', href: '/docs/catalyst/events', icon: null },
  { title: 'examples', href: '/docs/catalyst/examples', icon: null },
];

export default function DocsLayout({ children }: PropsWithChildren) {
  if (typeof window === 'undefined') return null;

  const currentPath = window.location.pathname;
  const { t } = useLocale();

  return (
    <div className="px-4 py-6">
      <div className="mb-8 flex items-center justify-between">
        <div className="space-y-0.5">
          <h2 className="text-xl font-semibold tracking-tight">Catalyst SDK</h2>
          <p className="text-sm text-muted-foreground">{t('overview.description')}</p>
        </div>
        <LanguageToggle />
      </div>

      <div className="flex flex-col lg:flex-row lg:space-x-12">
        <aside className="w-full max-w-xl lg:w-56">
          <nav className="flex flex-col space-y-1 space-x-0">
            {sidebarNavItems.map((item, index) => (
              <Button
                key={`${item.href}-${index}`}
                size="sm"
                variant="ghost"
                asChild
                className={cn('w-full justify-start', {
                  'bg-muted': currentPath === item.href,
                })}
              >
                <Link href={item.href} prefetch>
                  {t(`nav.${item.title}`)}
                </Link>
              </Button>
            ))}
          </nav>
        </aside>

        <Separator className="my-6 lg:hidden" />

        <div className="flex-1 md:max-w-4xl">
          <section className="space-y-12">{children}</section>
        </div>
      </div>
    </div>
  );
}
