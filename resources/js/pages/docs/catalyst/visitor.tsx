import { CodeBlock } from '@/components/docs/code-block';
import { useLocale } from '@/hooks/use-locale';
import AppLayout from '@/layouts/app-layout';
import DocsLayout from '@/layouts/docs/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Documentation', href: '/docs/catalyst/overview' },
  { title: 'Visitor', href: '/docs/catalyst/visitor' },
];

const VISITOR_DATA_TYPE = `interface VisitorData {
  fingerprint: string
  id?: string | number
  device_type?: string
  is_bot?: boolean
  lead_registered?: boolean
  lead_data?: any
  geolocation?: {
    ip: string
    city: string
    region: string
    region_code: string
    country: string
    postal: string
    timezone: string
    currency: string
  }
}`;

const INIT_EXAMPLE = `Catalyst.on('ready', function(eventData) {
  console.log('Visitor fingerprint:', eventData.visitorData.fingerprint)
  console.log('Geolocation:', eventData.visitorData.geolocation)
})`;

const FINGERPRINT_EXAMPLE = `const fp = Catalyst.getFingerprint()
console.log('Current fingerprint:', fp) // string or null`;

const UPDATE_VISIT_EXAMPLE = `// The click_id arrived late via the cf_click_id cookie.
// Persist it onto the already-registered visit:
const result = await Catalyst.updateVisit({ s10: clickId })

if (result.success) {
  // visit patched -> safe to enable the CTA
} else {
  console.warn('updateVisit failed:', result.error?.code, result.error?.message)
}`;

const UPDATE_VISIT_INPUT_TYPE = `interface UpdateVisitData {
  s10: string            // required: the click_id (cf_click_id cookie value)
  [key: string]: string | number | null | undefined // open: extra tracking columns
}`;

const UPDATE_VISIT_RESULT_TYPE = `interface UpdateVisitResult {
  success: boolean
  fingerprint: string | null
  s10: string | null         // echo of the persisted value
  updated_at?: string | null // ISO timestamp
  error?: {
    code: 'NO_ACTIVE_VISIT' | 'NETWORK_ERROR' | 'UNKNOWN'
    message: string
  }
}`;

const UPDATE_VISIT_EVENT_EXAMPLE = `window.addEventListener('catalyst:visit:updated', (e) => {
  const result = e.detail // UpdateVisitResult
  console.log('Visit updated:', result.success, result.s10)
})`;

export default function Visitor() {
  const { t } = useLocale();

  return (
    <DocsLayout>
      <Head title={t('visitor.title')} />
      <div className="space-y-8">
        <div>
          <h3 className="text-lg font-semibold">{t('visitor.title')}</h3>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('visitor.description')}</p>
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('visitor.init_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('visitor.init_desc')}</p>
          <CodeBlock code={INIT_EXAMPLE} language="javascript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">{t('visitor.return_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('visitor.return_desc')}</p>
          <CodeBlock code={VISITOR_DATA_TYPE} language="typescript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">{t('visitor.throttle_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('visitor.throttle_desc')}</p>
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('visitor.fingerprint_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('visitor.fingerprint_desc')}</p>
          <CodeBlock code={FINGERPRINT_EXAMPLE} language="javascript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('visitor.update_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('visitor.update_desc')}</p>
          <CodeBlock code={UPDATE_VISIT_EXAMPLE} language="javascript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">{t('visitor.update_input_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('visitor.update_input_desc')}</p>
          <CodeBlock code={UPDATE_VISIT_INPUT_TYPE} language="typescript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">{t('visitor.update_result_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('visitor.update_result_desc')}</p>
          <CodeBlock code={UPDATE_VISIT_RESULT_TYPE} language="typescript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('visitor.update_event_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('visitor.update_event_desc')}</p>
          <CodeBlock code={UPDATE_VISIT_EVENT_EXAMPLE} language="javascript" className="mt-3" />
        </div>
      </div>
    </DocsLayout>
  );
}

Visitor.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
