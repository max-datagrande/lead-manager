import { showBreadcrumbs } from '@/components/ping-post/buyers/breadcrumbs'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import AppLayout from '@/layouts/app-layout'
import PageHeader from '@/components/page-header'
import type { Buyer } from '@/types/ping-post'
import type { EnvironmentDB } from '@/types/integrations'
import { Head, Link, router } from '@inertiajs/react'
import { Check, Copy, Edit, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { route } from 'ziggy-js'

const OP_LABEL: Record<string, string> = {
  eq: '=', neq: '≠', gt: '>', gte: '≥', lt: '<', lte: '≤', in: 'in', not_in: 'not in',
}

function CopyButton({ text }: { text: string }) {
  const [copied, setCopied] = useState(false)

  const handleCopy = () => {
    navigator.clipboard.writeText(text)
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }

  return (
    <Button
      variant="ghost"
      size="icon"
      className="h-6 w-6 shrink-0 text-muted-foreground hover:text-foreground"
      onClick={handleCopy}
      type="button"
      aria-label="Copy to clipboard"
    >
      {copied
        ? <Check className="h-3 w-3 text-emerald-500" />
        : <Copy className="h-3 w-3" />
      }
    </Button>
  )
}

interface Props {
  buyer: Buyer
}

const BuyersShow = ({ buyer }: Props) => {
  const [deleteOpen, setDeleteOpen] = useState(false)

  const cfg = buyer.buyerConfig
  const environments = buyer.integration?.environments ?? []

  const getEnv = (envType: 'ping' | 'post', environment: 'production'): EnvironmentDB | undefined =>
    environments.find((e) => e.env_type === envType && e.environment === environment)

  const pingProd = getEnv('ping', 'production')
  const postProd = getEnv('post', 'production')

  const confirmDelete = () => {
    router.delete(route('ping-post.buyers.destroy', buyer.id))
    setDeleteOpen(false)
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
            <Button variant="destructive" onClick={() => setDeleteOpen(true)}>
              <Trash2 className="mr-2 h-4 w-4" />
              Delete
            </Button>
          </div>
        </PageHeader>

        {/* ── Hero stats band ───────────────────────────────────────────────── */}
        <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 px-4 py-3 text-sm">
          {buyer.integration && (
            <>
              <span className="font-medium">{buyer.integration.name}</span>
              <Badge variant="outline" className="text-xs">{buyer.integration.type}</Badge>
            </>
          )}
          {buyer.company && (
            <>
              <span className="h-4 w-px bg-border" />
              <span className="text-muted-foreground">{buyer.company.name}</span>
            </>
          )}
          <span className="h-4 w-px bg-border" />
          {buyer.is_active
            ? <Badge className="bg-emerald-500 hover:bg-emerald-500 text-white text-xs">Active</Badge>
            : <Badge variant="secondary" className="text-xs">Inactive</Badge>
          }
          {cfg && (
            <>
              <span className="h-4 w-px bg-border" />
              <span className="capitalize text-muted-foreground">{cfg.pricing_type.replace('_', ' ')} pricing</span>
            </>
          )}
        </div>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">

          {/* ── Basic Info ──────────────────────────────────────────────────── */}
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
                {buyer.is_active
                  ? <Badge className="bg-emerald-500 hover:bg-emerald-500 text-white">Active</Badge>
                  : <Badge variant="secondary">Inactive</Badge>
                }
              </div>
              {buyer.company && (
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Company</span>
                  <span>{buyer.company.name}</span>
                </div>
              )}
            </CardContent>
          </Card>

          {/* ── Production Endpoints ────────────────────────────────────────── */}
          <Card>
            <CardHeader>
              <CardTitle>Production Endpoints</CardTitle>
              <CardDescription>URLs come from the linked integration (production env).</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
              {pingProd && (
                <div className="space-y-1">
                  <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Ping URL</span>
                  <div className="flex items-center gap-1 rounded-md bg-muted/50 px-2 py-1.5">
                    <Badge variant="outline" className="shrink-0 font-mono text-xs">{pingProd.method}</Badge>
                    <p className="flex-1 truncate font-mono text-xs">{pingProd.url}</p>
                    <CopyButton text={`${pingProd.method} ${pingProd.url}`} />
                  </div>
                </div>
              )}
              {postProd && (
                <div className="space-y-1">
                  <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Post URL</span>
                  <div className="flex items-center gap-1 rounded-md bg-muted/50 px-2 py-1.5">
                    <Badge variant="outline" className="shrink-0 font-mono text-xs">{postProd.method}</Badge>
                    <p className="flex-1 truncate font-mono text-xs">{postProd.url}</p>
                    <CopyButton text={`${postProd.method} ${postProd.url}`} />
                  </div>
                </div>
              )}
              {!pingProd && !postProd && (
                <p className="text-muted-foreground">No production environments configured on the integration.</p>
              )}
            </CardContent>
          </Card>

          {/* ── Pricing ─────────────────────────────────────────────────────── */}
          {cfg && (
            <Card>
              <CardHeader>
                <CardTitle>Pricing</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Pricing Type</span>
                  <Badge variant="outline" className="capitalize">{cfg.pricing_type.replace('_', ' ')}</Badge>
                </div>
                {cfg.fixed_price != null && (
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Fixed Price</span>
                    <span className="font-medium">${Number(cfg.fixed_price).toFixed(2)}</span>
                  </div>
                )}
                {cfg.min_bid != null && (
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Min Bid</span>
                    <span className="font-medium">${Number(cfg.min_bid).toFixed(2)}</span>
                  </div>
                )}
                {cfg.pricing_type === 'postback' && (
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Postback Window</span>
                    <span>{cfg.postback_pending_days} days</span>
                  </div>
                )}
                <div className="flex justify-between border-t pt-3">
                  <span className="text-muted-foreground">Ping Timeout</span>
                  <span className="font-mono text-xs">{cfg.ping_timeout_ms} ms</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Post Timeout</span>
                  <span className="font-mono text-xs">{cfg.post_timeout_ms} ms</span>
                </div>
              </CardContent>
            </Card>
          )}

          {/* ── Eligibility Rules ───────────────────────────────────────────── */}
          <Card>
            <CardHeader>
              <CardTitle>Eligibility Rules</CardTitle>
              <CardDescription>{buyer.eligibilityRules?.length ?? 0} rules</CardDescription>
            </CardHeader>
            <CardContent>
              {buyer.eligibilityRules?.length ? (
                <div className="space-y-2">
                  {buyer.eligibilityRules.map((rule, i) => (
                    <div key={i} className="flex items-center gap-2 rounded-md bg-muted px-3 py-2 text-sm">
                      <span className="font-mono text-xs font-medium">{rule.field}</span>
                      <Badge variant="secondary" className="shrink-0 font-mono text-xs">
                        {OP_LABEL[rule.operator] ?? rule.operator}
                      </Badge>
                      <span className="truncate font-mono text-xs text-muted-foreground">
                        {Array.isArray(rule.value) ? rule.value.join(', ') : rule.value}
                      </span>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">No eligibility rules — all leads are eligible.</p>
              )}
            </CardContent>
          </Card>

          {/* ── Volume Caps ─────────────────────────────────────────────────── */}
          <Card>
            <CardHeader>
              <CardTitle>Volume Caps</CardTitle>
              <CardDescription>{buyer.capRules?.length ?? 0} caps</CardDescription>
            </CardHeader>
            <CardContent>
              {buyer.capRules?.length ? (
                <div className="space-y-2">
                  {buyer.capRules.map((cap, i) => (
                    <div key={i} className="flex items-center gap-3 rounded-md bg-muted px-3 py-2 text-sm">
                      <Badge variant="outline" className="shrink-0 capitalize">{cap.period}</Badge>
                      <div className="flex flex-wrap gap-3">
                        {cap.max_leads != null && (
                          <span className="text-xs text-muted-foreground">
                            <span className="font-medium text-foreground">{cap.max_leads.toLocaleString()}</span> leads
                          </span>
                        )}
                        {cap.max_revenue != null && (
                          <span className="text-xs text-muted-foreground">
                            <span className="font-medium text-foreground">${Number(cap.max_revenue).toFixed(2)}</span> revenue
                          </span>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">No caps configured — unlimited volume.</p>
              )}
            </CardContent>
          </Card>

        </div>
      </div>

      {/* ── Delete confirmation dialog ───────────────────────────────────────── */}
      <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Delete buyer</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete <strong>{buyer.name}</strong>? This action cannot be undone
              and will remove the buyer from all workflows.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDeleteOpen(false)}>Cancel</Button>
            <Button variant="destructive" onClick={confirmDelete}>Delete</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

BuyersShow.layout = (page: React.ReactNode & { props: { buyer: Buyer } }) =>
  <AppLayout children={page} breadcrumbs={showBreadcrumbs(page.props.buyer)} />
export default BuyersShow
