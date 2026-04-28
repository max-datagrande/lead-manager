import { CodeBlock } from '@/components/docs/code-block';
import { useLocale } from '@/hooks/use-locale';
import AppLayout from '@/layouts/app-layout';
import DocsLayout from '@/layouts/docs/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Documentation', href: '/docs/catalyst/overview' },
  { title: 'Validators', href: '/docs/catalyst/validators' },
];

const OPTIONS_TYPE = `interface ValidatePhoneOptions {
  phone: string                    // Raw user input — backend normalizes
  country?: string                 // ISO2, default 'US'
  fingerprint?: string             // Falls back to visitorData.fingerprint
}`;

const RESPONSE_TYPE = `interface ValidatePhoneResponse {
  success: boolean
  message: string
  valid: boolean                   // True for any acceptable classification
  classification:
    // valid: true ↓
    | 'valid_high_confidence'      // PS22 — Premium real-time confirmed
    | 'valid_low_confidence'       // PS01
    | 'low_confidence'             // PS20 only
    | 'compliance_risk'            // PS18 — DNC, surface to caller
    | 'pending_or_timeout'         // PS30 — Premium timeout
    // valid: false ↓
    | 'invalid_phone'              // PE01 / PE02 / PE03
    | 'disconnected_phone'         // PE11
    | 'high_risk_phone'            // PS19 — disposable
    // thrown by the SDK ↓ (caller decides how to handle)
    | 'validation_error'           // license invalid / timeout / no provider
  line_type?: 'cellular' | 'landline' | 'voip' | null
  country?: string | null
  carrier?: string | null
  normalized_phone?: string | null
  error?: string | null
}`;

const BASIC_EXAMPLE = `Catalyst.on('ready', async () => {
  // Inside your form's submit handler, BEFORE requestChallenge.
  let preFilterPassed = true

  try {
    const result = await Catalyst.validatePhone({
      phone: formData.phone,
      country: 'US',
    })

    if (!result.valid) {
      // Hard reject by Melissa — show inline error and abort the submit.
      showInlineError(formData.phone, result.error ?? 'Please enter a valid phone number.')
      return
    }

    // Optional: log the line type for analytics.
    console.log('Phone passed pre-filter:', result.classification, result.line_type)
  } catch (error) {
    // Technical failure (license / timeout / no provider). The SDK is agnostic:
    // YOU decide the policy. Examples:
    //   - block the submit (strict): \`return showInlineError(...)\`
    //   - fall through (permissive): just log and continue
    //   - retry once: implement your own backoff
    // This example is permissive — adjust to your landing's needs.
    console.warn('Phone pre-filter unavailable, continuing without it:', error)
  }

  // Continue with whatever your flow is.
  await Catalyst.registerLead(formData)
})`;

const EVENT_EXAMPLE = `Catalyst.on('phone:status', function(event) {
  // event: { type: 'validate', success, data?, error? }
  if (event.success) {
    console.log('Pre-filter PASS:', event.data.classification)
    return
  }
  if (event.error) {
    // Technical or network failure — same caveat: don't block.
    console.warn('Pre-filter unavailable:', event.error)
  } else {
    // Hard rejection by Melissa.
    console.log('Pre-filter REJECT:', event.data?.classification, event.data?.error)
  }
})`;

export default function Validators() {
  const { t } = useLocale();

  return (
    <DocsLayout>
      <Head title={t('validators.title')} />
      <div className="space-y-8">
        <div>
          <h3 className="text-lg font-semibold">{t('validators.title')}</h3>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('validators.description')}</p>
        </div>

        <div>
          <h4 className="font-semibold">{t('validators.when_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('validators.when_desc')}</p>
        </div>

        <div className="rounded-md border bg-muted/30 p-4">
          <h4 className="font-semibold">{t('validators.not_workflow_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('validators.not_workflow_desc')}</p>
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">validatePhone(options)</code>
          </h4>
        </div>

        <div>
          <h4 className="font-semibold">{t('validators.options_title')}</h4>
          <CodeBlock code={OPTIONS_TYPE} language="typescript" className="mt-3" />
          <ul className="mt-3 list-disc space-y-1 pl-6 text-sm text-muted-foreground">
            <li>{t('validators.opt_phone')}</li>
            <li>{t('validators.opt_country')}</li>
            <li>{t('validators.opt_fingerprint')}</li>
          </ul>
        </div>

        <div>
          <h4 className="font-semibold">{t('validators.response_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('validators.response_desc')}</p>
          <CodeBlock code={RESPONSE_TYPE} language="typescript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">{t('validators.classifications_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('validators.classifications_desc')}</p>
        </div>

        <div className="rounded-md border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-950/40">
          <h4 className="font-semibold text-amber-900 dark:text-amber-200">{t('validators.technical_error_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-amber-900 dark:text-amber-200">{t('validators.technical_error_desc')}</p>
        </div>

        <div>
          <h4 className="font-semibold">{t('validators.cache_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('validators.cache_desc')}</p>
        </div>

        <div>
          <h4 className="font-semibold">Example</h4>
          <CodeBlock code={BASIC_EXAMPLE} language="javascript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">{t('validators.event_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('validators.event_desc')}</p>
          <CodeBlock code={EVENT_EXAMPLE} language="javascript" className="mt-3" />
        </div>
      </div>
    </DocsLayout>
  );
}

Validators.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
