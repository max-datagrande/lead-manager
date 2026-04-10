import { CodeBlock } from '@/components/docs/code-block';
import { useLocale } from '@/hooks/use-locale';
import AppLayout from '@/layouts/app-layout';
import DocsLayout from '@/layouts/docs/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Documentation', href: '/docs/catalyst/overview' },
  { title: 'Share Leads', href: '/docs/catalyst/share-leads' },
];

const OPTIONS_TYPE = `interface ShareLeadOptions {
  workflowId: number | string  // The workflow ID to dispatch to
  fields?: Record<string, any> // Lead fields to create/update
  createOnMiss?: boolean       // Create lead if not exists (default: false)
}`;

const RESPONSE_TYPE = `interface ShareLeadResponse {
  success: boolean
  data: {
    dispatch_uuid?: string
    status?: string             // 'sold' | 'not_sold' | 'pending' | ...
    strategy_used?: string      // 'waterfall' | 'best_bid' | ...
    final_price?: number | null
    total_duration_ms?: number | null
    queued?: boolean            // true for async workflows
    workflow_id?: number
  }
  message: string
}`;

const BASIC_EXAMPLE = `Catalyst.on('ready', async () => {
  try {
    const result = await Catalyst.shareLead({
      workflowId: 1,
      fields: {
        first_name: 'John',
        last_name: 'Doe',
        email: 'john@example.com',
        phone: '5551234567',
        zip_code: '90210',
        attorney_status: 'no'
      }
    })
    console.log('Dispatch result:', result.data)
  } catch (error) {
    console.error('Share lead failed:', error)
  }
})`;

const EVENT_EXAMPLE = `Catalyst.on('share:status', function(event) {
  if (event.success) {
    console.log('Lead dispatched to workflow', event.workflowId)
    console.log('Status:', event.data.status)
    console.log('Price:', event.data.final_price)
  } else {
    console.error('Dispatch failed:', event.error)
  }
})`;

export default function ShareLeads() {
  const { t } = useLocale();

  return (
    <DocsLayout>
      <Head title={t('share_leads.title')} />
      <div className="space-y-8">
        <div>
          <h3 className="text-lg font-semibold">{t('share_leads.title')}</h3>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('share_leads.description')}</p>
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('share_leads.method_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('share_leads.method_desc')}</p>
        </div>

        <div>
          <h4 className="font-semibold">{t('share_leads.options_title')}</h4>
          <CodeBlock code={OPTIONS_TYPE} language="typescript" className="mt-3" />
          <ul className="mt-3 list-disc space-y-1 pl-6 text-sm text-muted-foreground">
            <li>{t('share_leads.opt_workflow')}</li>
            <li>{t('share_leads.opt_fields')}</li>
            <li>{t('share_leads.opt_create')}</li>
          </ul>
        </div>

        <div>
          <h4 className="font-semibold">{t('share_leads.response_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('share_leads.response_desc')}</p>
          <CodeBlock code={RESPONSE_TYPE} language="typescript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">Example</h4>
          <CodeBlock code={BASIC_EXAMPLE} language="javascript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">Event Listener</h4>
          <CodeBlock code={EVENT_EXAMPLE} language="javascript" className="mt-3" />
        </div>
      </div>
    </DocsLayout>
  );
}

ShareLeads.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
