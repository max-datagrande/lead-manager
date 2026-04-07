import { CodeBlock } from '@/components/docs/code-block';
import { useLocale } from '@/hooks/use-locale';
import AppLayout from '@/layouts/app-layout';
import DocsLayout from '@/layouts/docs/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Documentation', href: '/docs/catalyst/overview' },
  { title: 'Offerwall', href: '/docs/catalyst/offerwall' },
];

const GET_EXAMPLE = `const mixId = '123'
const placement = 'thank_you_page' // optional

try {
  const response = await Catalyst.getOfferwall({
    mixId,
    placement
  })
  console.log('Offers:', response.data)
  console.log('Meta:', response.meta)
} catch (error) {
  console.error('Error loading offerwall:', error)
}`;

const CONVERT_EXAMPLE = `const selectedOffer = offers[0]

try {
  const conversion = await Catalyst.convertOfferwall({
    offer_token: selectedOffer.offer_token, // REQUIRED
    amount: 10.50
  })
  console.log('Conversion registered:', conversion)
} catch (error) {
  console.error('Error registering conversion:', error)
}`;

export default function Offerwall() {
  const { t } = useLocale();

  return (
    <DocsLayout>
      <Head title={t('offerwall.title')} />
      <div className="space-y-8">
        <div>
          <h3 className="text-lg font-semibold">{t('offerwall.title')}</h3>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('offerwall.description')}</p>
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('offerwall.get_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('offerwall.get_desc')}</p>
          <p className="mt-1 text-sm text-muted-foreground">{t('offerwall.get_options')}</p>
          <CodeBlock code={GET_EXAMPLE} language="javascript" className="mt-3" />
        </div>

        <div>
          <h4 className="font-semibold">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">{t('offerwall.convert_title')}</code>
          </h4>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('offerwall.convert_desc')}</p>
          <p className="mt-1 text-sm text-muted-foreground">{t('offerwall.convert_options')}</p>
          <CodeBlock code={CONVERT_EXAMPLE} language="javascript" className="mt-3" />
        </div>
      </div>
    </DocsLayout>
  );
}

Offerwall.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
