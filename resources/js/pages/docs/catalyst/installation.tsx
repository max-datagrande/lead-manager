import { CodeBlock } from '@/components/docs/code-block';
import { useLocale } from '@/hooks/use-locale';
import AppLayout from '@/layouts/app-layout';
import DocsLayout from '@/layouts/docs/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Documentation', href: '/docs/catalyst/overview' },
  { title: 'Installation', href: '/docs/catalyst/installation' },
];

const LOADER_SNIPPET = `<script>
(function(w,d,s,u,c){
  w.Catalyst=w.Catalyst||{_q:[],config:c};
  // Proxy for methods that return Promises
  ['registerLead','updateLead','getOfferwall','convertOfferwall','shareLead'].forEach(function(m){
    w.Catalyst[m]=function(){
      var a=arguments;
      return new Promise(function(resolve, reject){
        w.Catalyst._q.push([m, a, resolve, reject]);
      })
    };
  });
  // Proxy for void methods
  ['on','dispatch'].forEach(function(m){
    w.Catalyst[m]=function(){w.Catalyst._q.push([m].concat([].slice.call(arguments)))};
  });

  var j=d.createElement(s),f=d.getElementsByTagName(s)[0];
  j.async=1;j.type='module';j.src=u;f.parentNode.insertBefore(j,f);
})(window,document,'script','https://your-domain.com/catalyst/v1.0.js',{
  api_url:'https://api.your-domain.com',debug:true
});
</script>`;

export default function Installation() {
  const { t } = useLocale();

  return (
    <DocsLayout>
      <Head title={t('installation.title')} />
      <div className="space-y-8">
        <div>
          <h3 className="text-lg font-semibold">{t('installation.title')}</h3>
          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t('installation.description')}</p>
        </div>

        <div className="rounded-md border border-amber-500/30 bg-amber-500/5 p-4 text-sm text-muted-foreground">
          <strong className="text-foreground">Important:</strong> {t('installation.important_note')}
        </div>

        <CodeBlock code={LOADER_SNIPPET} language="html" />

        <div className="rounded-md border border-blue-500/30 bg-blue-500/5 p-4 text-sm text-muted-foreground">
          <strong className="text-foreground">Astro:</strong> {t('installation.astro_note')}
        </div>

        <div>
          <h3 className="text-lg font-semibold">{t('installation.comparison_title')}</h3>
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left">
                  <th className="pb-2 pr-4 font-medium">Feature</th>
                  <th className="pb-2 pr-4 font-medium">Loader (/engine.js)</th>
                  <th className="pb-2 font-medium">Manual (/v1.0.js)</th>
                </tr>
              </thead>
              <tbody className="text-muted-foreground">
                <tr className="border-b border-border">
                  <td className="py-2 pr-4 font-medium text-foreground">Script</td>
                  <td className="py-2 pr-4">
                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground">/engine.js</code>
                  </td>
                  <td className="py-2">
                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground">/v1.0.js</code>
                  </td>
                </tr>
                <tr className="border-b border-border">
                  <td className="py-2 pr-4 font-medium text-foreground">Config</td>
                  <td className="py-2 pr-4">{t('installation.comparison_loader')}</td>
                  <td className="py-2">{t('installation.comparison_manual')}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </DocsLayout>
  );
}

Installation.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
