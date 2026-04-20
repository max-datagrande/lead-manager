import { CodeBlock } from '@/components/docs/code-block';
import { useLocale } from '@/hooks/use-locale';
import AppLayout from '@/layouts/app-layout';
import DocsLayout from '@/layouts/docs/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Documentation', href: '/docs/catalyst/overview' },
  { title: 'Lead Quality', href: '/docs/catalyst/lead-quality' },
];

const SEND_OPTIONS_TYPE = `interface SendChallengeOptions {
  workflowId: number | string
  leadId?: number | string        // Fallback: visitorData.lead_data.id
  fingerprint?: string             // Fallback: visitorData.fingerprint
  to?: string                      // Destination (phone E.164 / email)
  channel?: 'sms' | 'call' | 'email' | 'whatsapp'
  locale?: string                  // e.g. 'en', 'es'
}`;

const SEND_RESPONSE_TYPE = `interface SendChallengeResponse {
  success: boolean
  message: string
  data: {
    dispatch_id: number
    dispatch_uuid: string
    challenges: Array<{
      challenge_token: string       // Submit this to verifyChallenge
      rule_id: number
      rule_name: string
      channel: string | null
      masked_destination: string | null  // e.g. '+1******1234'
      expires_at: string            // ISO 8601
    }>
    errors: Array<{
      rule_id: number
      rule_name: string
      error: string
    }>
  }
}`;

const VERIFY_OPTIONS_TYPE = `interface VerifyChallengeOptions {
  challengeToken: string            // From SendChallengeResponse
  code: string                      // User-entered code
  to?: string                       // Same destination as sendChallenge
}`;

const VERIFY_RESPONSE_TYPE = `interface VerifyChallengeResponse {
  success: boolean
  message: string
  verified: boolean
  status:
    | 'verified'
    | 'already_verified'
    | 'retry'            // code was wrong, more attempts available
    | 'failed'           // terminal: max_attempts reached
    | 'expired'          // terminal: challenge timed out
    | 'already_failed'
    | 'invalid_token'
    | 'not_found'
    | 'error'
  retry_remaining?: number
  reason?: string
  dispatch_uuid?: string
}`;

const BASIC_EXAMPLE = `Catalyst.on('ready', async () => {
  // 1. Save the lead (so we have a lead_id)
  await Catalyst.registerLead({
    first_name: 'John',
    email: 'john@example.com',
    phone: '+15555551234',
  })

  // 2. Issue the challenge
  const { data } = await Catalyst.sendChallenge({
    workflowId: 42,
    to: '+15555551234',
    channel: 'sms',
  })

  if (data.challenges.length === 0) {
    // No Lead Quality rules apply to this workflow — dispatch directly.
    return Catalyst.shareLead({ workflowId: 42 })
  }

  const token = data.challenges[0].challenge_token

  // 3. Render your OTP UI, collect the code, then:
  const result = await Catalyst.verifyChallenge({
    challengeToken: token,
    code: userEnteredCode,
    to: '+15555551234',
  })

  if (result.verified) {
    // Backend auto-queues the dispatch. Do NOT call shareLead() here.
    console.log('Dispatch running:', result.dispatch_uuid)
    return
  }

  if (result.status === 'retry') {
    // Wrong code — re-prompt, N attempts remaining.
    showRetryUI(result.retry_remaining)
    return
  }

  // Terminal failure: expired, failed, invalid_token, not_found.
  showFailureUI(result.reason ?? result.status)
})`;

const EVENT_EXAMPLE = `Catalyst.on('challenge:status', function(event) {
  // event: { type: 'send' | 'verify', success, data?, error? }
  if (event.type === 'send' && event.success) {
    console.log('Challenges issued:', event.data.challenges.length)
  }
  if (event.type === 'verify' && event.success) {
    console.log('Verified! Dispatch is queued:', event.data.dispatch_uuid)
  }
  if (!event.success) {
    console.error('Challenge', event.type, 'failed:', event.error)
  }
})`;

export default function LeadQuality() {
  const { t } = useLocale();

  return (
    <DocsLayout>
      <Head title={t('lead_quality.title')} />
      <div className="space-y-8">
        <div>
          <h3 className="text-lg font-semibold">{t('lead_quality.title')}</h3>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('lead_quality.description')}</p>
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('lead_quality.send_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('lead_quality.send_desc')}</p>
        </div>

        <div>
          <h4 className="font-semibold">{t('lead_quality.send_options_title')}</h4>
          <CodeBlock code={SEND_OPTIONS_TYPE} language="typescript" className="mt-3" />
          <ul className="mt-3 list-disc space-y-1 pl-6 text-sm text-muted-foreground">
            <li>{t('lead_quality.send_opt_workflow')}</li>
            <li>{t('lead_quality.send_opt_lead')}</li>
            <li>{t('lead_quality.send_opt_fingerprint')}</li>
            <li>{t('lead_quality.send_opt_to')}</li>
            <li>{t('lead_quality.send_opt_channel')}</li>
            <li>{t('lead_quality.send_opt_locale')}</li>
          </ul>
        </div>

        <div>
          <h4 className="font-semibold">{t('lead_quality.send_response_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('lead_quality.send_response_desc')}</p>
          <CodeBlock code={SEND_RESPONSE_TYPE} language="typescript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('lead_quality.verify_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('lead_quality.verify_desc')}</p>
        </div>

        <div>
          <h4 className="font-semibold">{t('lead_quality.verify_options_title')}</h4>
          <CodeBlock code={VERIFY_OPTIONS_TYPE} language="typescript" className="mt-3" />
          <ul className="mt-3 list-disc space-y-1 pl-6 text-sm text-muted-foreground">
            <li>{t('lead_quality.verify_opt_token')}</li>
            <li>{t('lead_quality.verify_opt_code')}</li>
            <li>{t('lead_quality.verify_opt_to')}</li>
          </ul>
        </div>

        <div>
          <h4 className="font-semibold">{t('lead_quality.verify_response_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('lead_quality.verify_response_desc')}</p>
          <CodeBlock code={VERIFY_RESPONSE_TYPE} language="typescript" className="mt-3" />
        </div>

        <div className="rounded-md border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-950/40">
          <h4 className="font-semibold text-amber-900 dark:text-amber-200">{t('lead_quality.backend_auto_dispatch_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-amber-900 dark:text-amber-200">{t('lead_quality.backend_auto_dispatch_desc')}</p>
        </div>

        <div className="rounded-md border bg-muted/30 p-4">
          <h4 className="font-semibold">{t('lead_quality.no_rules_note_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('lead_quality.no_rules_note_desc')}</p>
        </div>

        <div>
          <h4 className="font-semibold">Example</h4>
          <CodeBlock code={BASIC_EXAMPLE} language="javascript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">{t('lead_quality.event_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('lead_quality.event_desc')}</p>
          <CodeBlock code={EVENT_EXAMPLE} language="javascript" className="mt-3" />
        </div>
      </div>
    </DocsLayout>
  );
}

LeadQuality.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
