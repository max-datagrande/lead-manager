import { CodeBlock } from '@/components/docs/code-block';
import { useLocale } from '@/hooks/use-locale';
import AppLayout from '@/layouts/app-layout';
import DocsLayout from '@/layouts/docs/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Documentation', href: '/docs/catalyst/overview' },
  { title: 'Overview', href: '/docs/catalyst/overview' },
];

const CONFIG_EXAMPLE = `{
  api_url: 'https://api.your-domain.com',
  debug: true,
  environment: 'production',
  dev_origin: 'https://localhost:3000'
}`;

export default function Overview() {
  const { t } = useLocale();

  return (
    <DocsLayout>
      <Head title={t('overview.title')} />
      <div className="space-y-8">
        <div>
          <h3 className="text-lg font-semibold">{t('overview.title')}</h3>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('overview.description')}</p>
        </div>

        <div>
          <h3 className="text-lg font-semibold">{t('overview.config_title')}</h3>
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left">
                  <th className="pb-2 pr-4 font-medium">Property</th>
                  <th className="pb-2 pr-4 font-medium">Type</th>
                  <th className="pb-2 font-medium">{t('overview.config_title')}</th>
                </tr>
              </thead>
              <tbody className="text-muted-foreground">
                <tr className="border-b border-border">
                  <td className="py-2 pr-4">
                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground">api_url</code>
                  </td>
                  <td className="py-2 pr-4">string</td>
                  <td className="py-2">{t('overview.config_api_url')}</td>
                </tr>
                <tr className="border-b border-border">
                  <td className="py-2 pr-4">
                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground">debug</code>
                  </td>
                  <td className="py-2 pr-4">boolean</td>
                  <td className="py-2">{t('overview.config_debug')}</td>
                </tr>
                <tr className="border-b border-border">
                  <td className="py-2 pr-4">
                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground">environment</code>
                  </td>
                  <td className="py-2 pr-4">string</td>
                  <td className="py-2">{t('overview.config_environment')}</td>
                </tr>
                <tr className="border-b border-border">
                  <td className="py-2 pr-4">
                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground">dev_origin</code>
                  </td>
                  <td className="py-2 pr-4">string</td>
                  <td className="py-2">{t('overview.config_dev_origin')}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <CodeBlock code={CONFIG_EXAMPLE} language="javascript" className="mt-4" />
        </div>

        <div>
          <h3 className="text-lg font-semibold">{t('overview.architecture_title')}</h3>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('overview.architecture_desc')}</p>
        </div>
      </div>
    </DocsLayout>
  );
}

Overview.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
