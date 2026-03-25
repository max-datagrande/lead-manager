import { showBreadcrumbs } from '@/components/ping-post/buyers/breadcrumbs'
import { StatusBadge } from '@/components/ping-post/status-badge'
import PageHeader from '@/components/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/app-layout'
import type { Buyer } from '@/types/ping-post'
import type { EnvironmentDB } from '@/types/integrations'
import { Head, Link, router } from '@inertiajs/react'
import { Copy, Edit, Trash2 } from 'lucide-react'
import { route } from 'ziggy-js'

interface Props {
  buyer: Buyer
}

const BuyersShow = ({ buyer }: Props) => {
  const cfg = buyer.buyerConfig
  const environments = buyer.integration?.environments ?? []

  const getEnv = (envType: 'ping' | 'post', environment: 'production'): EnvironmentDB | undefined =>
    environments.find((e) => e.env_type === envType && e.environment === environment)

  const pingProd = getEnv('ping', 'production')
  const postProd = getEnv('post', 'production')

  const handleDelete = () => {
    if (confirm(`Delete buyer "${buyer.name}"?`)) {
      router.delete(route('ping-post.buyers.destroy', buyer.id))
    }
  }

  return (
    <>
      <Head title={buyer.name} />
      <div className="relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title={buyer.name} description={`Buyer — ${buyer.integration?.type ?? 'unknown type'}`}>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => router.post(route('ping-post.buyers.duplicate', buyer.id))}>
              <Copy className="mr-2 h-4 w-4" />
              Duplicate
            </Button>
            <Button variant="outline" asChild>
              <Link href={route('ping-post.buyers.edit', buyer.id)}>
                <Edit className="mr-2 h-4 w-4" />
                Edit
              </Link>
            </Button>
            <Button variant="destructive" onClick={handleDelete}>
              <Trash2 className="mr-2 h-4 w-4" />
              Delete
            </Button>
          </div>
        </PageHeader>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          {/* Basic Info */}
          <Card>
            <CardHeader>
              <CardTitle>Basic Info</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div className="flex justify-between">
                <span className="text-muted-foreground">Integration</span>
                <span>{buyer.integration?.name ?? '—'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Type</span>
                <Badge variant="outline">{buyer.integration?.type === 'ping-post' ? 'Ping-Post' : 'Post-Only'}</Badge>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Status</span>
                <StatusBadge status={buyer.is_active ? 'sold' : 'not_sold'} />
              </div>
              {buyer.company && (
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Company</span>
                  <span>{buyer.company.name}</span>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Endpoints (production) */}
          <Card>
            <CardHeader>
              <CardTitle>Production Endpoints</CardTitle>
              <CardDescription>URLs come from the linked integration (production env).</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              {pingProd && (
                <div>
                  <span className="text-muted-foreground">Ping URL</span>
                  <p className="mt-0.5 truncate font-mono text-xs">{pingProd.method} {pingProd.url}</p>
                </div>
              )}
              {postProd && (
                <div>
                  <span className="text-muted-foreground">Post URL</span>
                  <p className="mt-0.5 truncate font-mono text-xs">{postProd.method} {postProd.url}</p>
                </div>
              )}
              {!pingProd && !postProd && (
                <p className="text-muted-foreground">No production environments configured on the integration.</p>
              )}
            </CardContent>
          </Card>

          {/* Pricing */}
          {cfg && (
            <Card>
              <CardHeader>
                <CardTitle>Pricing</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Pricing Type</span>
                  <span className="capitalize">{cfg.pricing_type.replace('_', ' ')}</span>
                </div>
                {cfg.fixed_price && (
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Fixed Price</span>
                    <span>${Number(cfg.fixed_price).toFixed(2)}</span>
                  </div>
                )}
                {cfg.min_bid && (
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Min Bid</span>
                    <span>${Number(cfg.min_bid).toFixed(2)}</span>
                  </div>
                )}
                {cfg.pricing_type === 'postback' && (
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Postback Window</span>
                    <span>{cfg.postback_pending_days} days</span>
                  </div>
                )}
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Ping Timeout</span>
                  <span>{cfg.ping_timeout_ms}ms</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Post Timeout</span>
                  <span>{cfg.post_timeout_ms}ms</span>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Eligibility Rules */}
          <Card>
            <CardHeader>
              <CardTitle>Eligibility Rules</CardTitle>
              <CardDescription>{buyer.eligibilityRules?.length ?? 0} rules</CardDescription>
            </CardHeader>
            <CardContent>
              {buyer.eligibilityRules?.length ? (
                <div className="space-y-2 text-sm">
                  {buyer.eligibilityRules.map((rule, i) => (
                    <div key={i} className="flex gap-2 rounded bg-muted px-3 py-1.5">
                      <span className="font-mono">{rule.field}</span>
                      <span className="text-muted-foreground">{rule.operator}</span>
                      <span className="font-mono">{Array.isArray(rule.value) ? rule.value.join(', ') : rule.value}</span>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">No eligibility rules configured.</p>
              )}
            </CardContent>
          </Card>

          {/* Cap Rules */}
          <Card>
            <CardHeader>
              <CardTitle>Volume Caps</CardTitle>
              <CardDescription>{buyer.capRules?.length ?? 0} caps</CardDescription>
            </CardHeader>
            <CardContent>
              {buyer.capRules?.length ? (
                <div className="space-y-2 text-sm">
                  {buyer.capRules.map((cap, i) => (
                    <div key={i} className="flex gap-4 rounded bg-muted px-3 py-1.5">
                      <span className="capitalize">{cap.period}</span>
                      {cap.max_leads && <span>{cap.max_leads} leads</span>}
                      {cap.max_revenue && <span>${Number(cap.max_revenue).toFixed(2)} revenue</span>}
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">No caps configured.</p>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  )
}

BuyersShow.layout = (page: React.ReactNode & { props: { buyer: Buyer } }) =>
  <AppLayout children={page} breadcrumbs={showBreadcrumbs(page.props.buyer)} />
export default BuyersShow
