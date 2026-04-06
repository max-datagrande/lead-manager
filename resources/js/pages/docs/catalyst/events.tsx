import { CodeBlock } from '@/components/docs/code-block';
import { useLocale } from '@/hooks/use-locale';
import AppLayout from '@/layouts/app-layout';
import DocsLayout from '@/layouts/docs/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Documentation', href: '/docs/catalyst/overview' },
  { title: 'Events', href: '/docs/catalyst/events' },
];

const ON_EXAMPLE = `// Register a listener
Catalyst.on('ready', function(data) {
  console.log('SDK is ready:', data)
})

// If 'ready' has already fired, the callback
// executes immediately (no missed events)`;

const READY_PAYLOAD = `{
  catalyst: CatalystCore,    // SDK instance
  visitorData: VisitorData   // Visitor session data
}`;

const LEAD_STATUS_PAYLOAD = `{
  type: 'register' | 'update',
  success: boolean,
  data?: object,   // API response data (on success)
  error?: string   // Error message (on failure)
}`;

const SHARE_STATUS_PAYLOAD = `{
  success: boolean,
  workflowId: number | string,
  data?: object,   // dispatch_uuid, status, final_price, etc.
  error?: string
}`;

export default function Events() {
  const { t } = useLocale();

  const events = [
    {
      title: t('events.ready_title'),
      desc: t('events.ready_desc'),
      payload: READY_PAYLOAD,
    },
    {
      title: t('events.lead_status_title'),
      desc: t('events.lead_status_desc'),
      payload: LEAD_STATUS_PAYLOAD,
    },
    {
      title: t('events.share_status_title'),
      desc: t('events.share_status_desc'),
      payload: SHARE_STATUS_PAYLOAD,
    },
    {
      title: t('events.offerwall_loaded_title'),
      desc: t('events.offerwall_loaded_desc'),
      payload: '{ mixId: string, count: number }',
    },
    {
      title: t('events.offerwall_error_title'),
      desc: t('events.offerwall_error_desc'),
      payload: '{ mixId: string, error: any }',
    },
    {
      title: t('events.offerwall_conversion_title'),
      desc: t('events.offerwall_conversion_desc'),
      payload: '{ success: boolean, data?: object, error?: object }',
    },
  ];

  return (
    <DocsLayout>
      <Head title={t('events.title')} />
      <div className="space-y-8">
        <div>
          <h3 className="text-lg font-semibold">{t('events.title')}</h3>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('events.description')}</p>
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('events.on_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('events.on_desc')}</p>
          <CodeBlock code={ON_EXAMPLE} language="javascript" className="mt-3" />
        </div>

        {events.map((event) => (
          <div key={event.title}>
            <h4 className="font-semibold">{event.title}</h4>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{event.desc}</p>
            <CodeBlock code={event.payload} language="typescript" className="mt-3" />
          </div>
        ))}
      </div>
    </DocsLayout>
  );
}

Events.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
