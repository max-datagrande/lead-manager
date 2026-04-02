import { CodeBlock } from '@/components/docs/code-block';
import { useLocale } from '@/hooks/use-locale';
import AppLayout from '@/layouts/app-layout';
import DocsLayout from '@/layouts/docs/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Documentation', href: '/docs/catalyst/overview' },
  { title: 'Examples', href: '/docs/catalyst/examples' },
];

const BASIC_FORM = `// Basic: Register lead on form submit
Catalyst.on('ready', function() {
  document.getElementById('myForm').addEventListener('submit', function(e) {
    e.preventDefault()

    Catalyst.dispatch('lead:register', {
      name: document.getElementById('name').value,
      email: document.getElementById('email').value,
      phone: document.getElementById('phone').value
    })
  })
})

Catalyst.on('lead:status', function(event) {
  if (event.type === 'register' && event.success) {
    alert('Thank you! Your information has been submitted.')
  }
})`;

const MULTI_STEP = `// Multi-step: Register -> Update -> Share
Catalyst.on('ready', async () => {
  // Step 1: Register the lead with basic info
  await Catalyst.registerLead({
    email: 'john@example.com',
    first_name: 'John',
    last_name: 'Doe',
    phone: '5551234567'
  })

  // Step 2: Update with additional qualification data
  await Catalyst.updateLead({
    zip_code: '90210',
    attorney_status: 'no',
    injury_type: 'car_accident'
  })

  // Step 3: Dispatch to a ping-post workflow
  const result = await Catalyst.shareLead({
    workflowId: 1,
    fields: {
      accident_date: 'last_30',
      medical_treatment: 'yes'
    }
  })

  if (result.data.status === 'sold') {
    console.log('Lead sold for $' + result.data.final_price)
  }
})`;

const OFFERWALL_FLOW = `// Offerwall: Load offers and handle conversion
Catalyst.on('ready', async () => {
  // Register lead first (required for offerwall)
  await Catalyst.registerLead({
    email: 'user@example.com',
    name: 'User'
  })

  // Fetch available offers
  const response = await Catalyst.getOfferwall({
    mixId: 'mix-uuid-here',
    placement: 'thank_you_page'
  })

  // Render offers in your UI
  response.data.forEach(offer => {
    const btn = document.createElement('button')
    btn.textContent = offer.title
    btn.onclick = async () => {
      await Catalyst.convertOfferwall({
        offer_token: offer.offer_token,
        amount: offer.payout
      })
      alert('Conversion registered!')
    }
    document.getElementById('offers').appendChild(btn)
  })
})`;

export default function Examples() {
  const { t } = useLocale();

  return (
    <DocsLayout>
      <Head title={t('examples.title')} />
      <div className="space-y-10">
        <div>
          <h3 className="text-lg font-semibold">{t('examples.title')}</h3>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('examples.description')}</p>
        </div>

        <div>
          <h4 className="font-semibold">{t('examples.basic_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('examples.basic_desc')}</p>
          <CodeBlock code={BASIC_FORM} language="javascript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">{t('examples.multi_step_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('examples.multi_step_desc')}</p>
          <CodeBlock code={MULTI_STEP} language="javascript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">{t('examples.offerwall_title')}</h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('examples.offerwall_desc')}</p>
          <CodeBlock code={OFFERWALL_FLOW} language="javascript" className="mt-3" />
        </div>

        {/* FAQ Section */}
        <div>
          <h3 className="text-lg font-semibold">{t('examples.faq_title')}</h3>
          <div className="mt-4 space-y-6">
            {[
              { q: t('examples.faq_update_q'), a: t('examples.faq_update_a') },
              { q: t('examples.faq_reload_q'), a: t('examples.faq_reload_a') },
              { q: t('examples.faq_error_q'), a: t('examples.faq_error_a') },
              { q: t('examples.faq_debug_q'), a: t('examples.faq_debug_a') },
              { q: t('examples.faq_auto_q'), a: t('examples.faq_auto_a') },
            ].map((faq, i) => (
              <div key={i}>
                <h4 className="text-sm font-semibold">{faq.q}</h4>
                <p className="mt-1 text-sm leading-relaxed text-muted-foreground">{faq.a}</p>
              </div>
            ))}
          </div>
        </div>
      </div>
    </DocsLayout>
  );
}

Examples.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
