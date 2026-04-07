import { CodeBlock } from '@/components/docs/code-block';
import { useLocale } from '@/hooks/use-locale';
import AppLayout from '@/layouts/app-layout';
import DocsLayout from '@/layouts/docs/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Documentation', href: '/docs/catalyst/overview' },
  { title: 'Leads', href: '/docs/catalyst/leads' },
];

const REGISTER_EVENT = `// Event mode
Catalyst.dispatch('lead:register', {
  name: 'John Doe',
  email: 'john@example.com',
  phone: '+1 555 555 5555',
  custom_field: 'value'
})`;

const UPDATE_EVENT = `// Event mode
Catalyst.dispatch('lead:update', {
  company: 'Company Inc.',
  role: 'Manager'
})`;

const ASYNC_EXAMPLE = `Catalyst.on('ready', async () => {
  try {
    await Catalyst.registerLead({
      email: 'test@example.com',
      name: 'Test User'
    })
    console.log('Lead registered successfully')

    await Catalyst.updateLead({ role: 'Admin' })
    console.log('Lead updated successfully')
  } catch (error) {
    console.error('Error in async flow:', error)
  }
})`;

const STATUS_EVENT = `Catalyst.on('lead:status', function(event) {
  // event.type: 'register' | 'update'
  // event.success: boolean
  // event.data: object (if success)
  // event.error: string (if failure)

  if (event.type === 'register' && event.success) {
    console.log('Lead registered:', event.data)
  }
})`;

export default function Leads() {
  const { t } = useLocale();

  return (
    <DocsLayout>
      <Head title={t('leads.title')} />
      <div className="space-y-8">
        <div>
          <h3 className="text-lg font-semibold">{t('leads.title')}</h3>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('leads.description')}</p>
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('leads.register_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('leads.register_desc')}</p>
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('leads.update_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('leads.update_desc')}</p>
        </div>

        <div>
          <h4 className="font-semibold">{t('leads.event_mode_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('leads.event_mode_desc')}</p>
          <CodeBlock code={REGISTER_EVENT} language="javascript" className="mt-3" />
          <CodeBlock code={UPDATE_EVENT} language="javascript" className="mt-3" />
          <CodeBlock code={STATUS_EVENT} language="javascript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">{t('leads.async_mode_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('leads.async_mode_desc')}</p>
          <CodeBlock code={ASYNC_EXAMPLE} language="javascript" className="mt-3" />
        </div>

        <div className="rounded-md border border-amber-500/30 bg-amber-500/5 p-4 text-sm text-muted-foreground">
          <strong className="text-foreground">Warning:</strong> {t('leads.warning_update')}
        </div>
      </div>
    </DocsLayout>
  );
}

Leads.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
