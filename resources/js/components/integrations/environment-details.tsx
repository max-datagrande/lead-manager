import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardHeader } from '@/components/ui/card'
import { FieldHint } from '@/components/ui/field-hint'
import JsonViewer from '@/components/ui/json-viewer'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { useToast } from '@/hooks/use-toast'
import { cn } from '@/lib/utils'
import { getCookie } from '@/utils/navigator'
import { EnvironmentDB, MappingEntry } from '@/types/integrations'
import { ArrowRightLeft, Check, Clock, Copy, PlayCircle } from 'lucide-react'
import { Fragment, useState } from 'react'
import { route } from 'ziggy-js'
import { DescriptionList, DescriptionListItem } from './description-list-item'

// ─── HTTP method badge ────────────────────────────────────────────────────────

const METHOD_VARIANTS: Record<string, string> = {
  GET: 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-900/30 dark:text-sky-400 dark:border-sky-800',
  POST: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800',
  PUT: 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800',
  PATCH: 'bg-violet-100 text-violet-700 border-violet-200 dark:bg-violet-900/30 dark:text-violet-400 dark:border-violet-800',
  DELETE: 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400 dark:border-rose-800',
}

function MethodBadge({ method }: { method: string }) {
  const upper = method.toUpperCase()
  return (
    <Badge className={cn('rounded px-1.5 py-0 font-mono text-[11px] font-semibold tracking-wide', METHOD_VARIANTS[upper] ?? 'bg-muted text-muted-foreground border-border')}>
      {upper}
    </Badge>
  )
}

function CopyUrlButton({ url }: { url: string }) {
  const [copied, setCopied] = useState(false)
  const handleCopy = () => {
    navigator.clipboard.writeText(url).then(() => {
      setCopied(true)
      setTimeout(() => setCopied(false), 1500)
    })
  }
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <button
          type="button"
          onClick={handleCopy}
          className="shrink-0 rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          aria-label="Copy URL"
        >
          {copied ? <Check className="size-3.5 text-emerald-500" /> : <Copy className="size-3.5" />}
        </button>
      </TooltipTrigger>
      <TooltipContent side="top">{copied ? 'Copied!' : 'Copy URL'}</TooltipContent>
    </Tooltip>
  )
}

// ─── Token list ───────────────────────────────────────────────────────────────

function ValueMappingPopover({ token, valueMapping }: { token: string; valueMapping: Record<string, string> }) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          className="flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        >
          <ArrowRightLeft className="size-3 shrink-0" />
          mapping
        </button>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-56 p-0">
        <p className="border-b px-3 py-2 font-mono text-xs font-semibold text-foreground">{`{${token}}`}</p>
        <table className="w-full text-xs">
          <thead>
            <tr className="border-b bg-muted/40">
              <th className="px-3 py-1.5 text-left font-medium text-muted-foreground">From</th>
              <th className="px-3 py-1.5 text-left font-medium text-muted-foreground">To</th>
            </tr>
          </thead>
          <tbody>
            {Object.entries(valueMapping).map(([from, to]) => (
              <tr key={from} className="border-b last:border-0">
                <td className="px-3 py-1.5 font-mono text-foreground">{from}</td>
                <td className="px-3 py-1.5 font-mono text-foreground">{to}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </PopoverContent>
    </Popover>
  )
}

function TokenList({ mappingConfig }: { mappingConfig: Record<string, MappingEntry> }) {
  const entries = Object.entries(mappingConfig)
  return (
    <div className="space-y-1">
      <p className="text-[10px] font-medium uppercase tracking-wider text-muted-foreground mb-1.5">Tokens</p>
      <table className="w-full border-0 text-xs">
        <thead>
          <tr className="border-b border-border/50">
            <th className="pb-1 text-left text-[10px] font-medium text-muted-foreground">Token</th>
            <th className="pb-1 text-left text-[10px] font-medium text-muted-foreground">Type</th>
            <th className="pb-1 text-left text-[10px] font-medium text-muted-foreground">Default</th>
            <th className="pb-1 text-left text-[10px] font-medium text-muted-foreground">Mapping</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-border/50">
          {entries.map(([token, entry]) => {
            const hasType = Boolean(entry.type)
            const hasDefault = Boolean(entry.defaultValue)
            const hasValueMapping = Boolean(entry.value_mapping && Object.keys(entry.value_mapping).length > 0)

            return (
              <tr key={token}>
                <td className="py-1.5 pr-4 font-mono text-xs text-foreground">{`{${token}}`}</td>
                <td className="py-1.5 pr-4">
                  {hasType
                    ? <Badge variant="secondary" className="font-mono text-[10px] px-1.5 py-0">{entry.type}</Badge>
                    : <span className="text-muted-foreground/40">—</span>
                  }
                </td>
                <td className="py-1.5 pr-4">
                  {hasDefault
                    ? <span className="font-mono text-muted-foreground">{entry.defaultValue}</span>
                    : <span className="text-muted-foreground/40">—</span>
                  }
                </td>
                <td className="py-1.5">
                  {hasValueMapping
                    ? <ValueMappingPopover token={token} valueMapping={entry.value_mapping!} />
                    : <span className="text-muted-foreground/40">—</span>
                  }
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

// ─── Response config section ──────────────────────────────────────────────────

const PING_FIELDS = [
  { key: 'bid_price_path', label: 'Bid Price Path', hint: 'JSON path to extract the buyer\'s bid price from the response (e.g. "data.bid")' },
  { key: 'accepted_path', label: 'Accepted Path', hint: 'JSON path to the field that signals whether the lead was accepted (e.g. "status")' },
  { key: 'accepted_value', label: 'Accepted Value', hint: 'The value at accepted_path that means the lead was accepted (e.g. "accepted", "true", "1")' },
  { key: 'lead_id_path', label: 'Lead ID Path', hint: 'JSON path to extract the external lead ID assigned by the buyer (e.g. "data.lead_id")' },
]

const POST_FIELDS = [
  { key: 'accepted_path', label: 'Accepted Path', hint: 'JSON path to the field that signals the post was accepted (e.g. "result")' },
  { key: 'accepted_value', label: 'Accepted Value', hint: 'The value at accepted_path that means the lead was accepted (e.g. "success", "true", "1")' },
  { key: 'rejected_path', label: 'Rejected Path', hint: 'JSON path to extract the rejection reason from the response (e.g. "error_message")' },
]

function ResponseConfigSection({ env }: { env: EnvironmentDB }) {
  const responseConfig = (env.response_config ?? {}) as Record<string, string>
  const fields = env.env_type === 'ping' ? PING_FIELDS : POST_FIELDS
  const configured = fields.filter((f) => responseConfig[f.key])

  if (configured.length === 0) return null

  return (
    <div className="border-t px-5 py-3">
      <p className="mb-2 text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Response Config</p>
      <div className="grid grid-cols-[auto_1fr] items-baseline gap-x-4 gap-y-2">
        {configured.map((f) => (
          <Fragment key={f.key}>
            <div className="flex items-center gap-0.5">
              <span className="text-sm font-medium text-foreground">{f.label}</span>
              <FieldHint text={f.hint} side="right" />
            </div>
            <span className="font-mono text-sm text-muted-foreground">{responseConfig[f.key]}</span>
          </Fragment>
        ))}
      </div>
    </div>
  )
}

// ─── Main component ───────────────────────────────────────────────────────────

interface Props {
  integrationId: number
  env: EnvironmentDB
  mappingConfig: Record<string, MappingEntry>
}

export function EnvironmentDetails({ integrationId, env, mappingConfig }: Props) {
  const [testResult, setTestResult] = useState(null)
  const [isTesting, setIsTesting] = useState(false)
  const { addMessage } = useToast()

  const handleTest = async (environmentId: number | string) => {
    setIsTesting(true)
    setTestResult(null)
    try {
      const endpoint = route('integrations.test', { integration: integrationId, environment: environmentId })
      const csrfToken = getCookie('XSRF-TOKEN')
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'x-xsrf-token': csrfToken,
          'x-requested-with': 'XMLHttpRequest',
          accept: 'application/json',
        },
      })

      if (!response.ok) {
        const statusCode = response.status
        const statusText = (response.statusText || '').toLowerCase().length > 0 ? response.statusText : null
        const contentType = response.headers.get('content-type')
        const isJson = contentType.includes('application/json')
        const result = isJson ? await response.json() : { error: 'Bad Response: ' + statusCode, body: await response.text() }
        const shortMessage = result.error ?? result.message ?? statusText ?? 'Bad Response: ' + statusCode
        throw new Error(shortMessage, { cause: result })
      }
      const result = await response.json().catch((error) => {
        return { error: error.message }
      })
      setTestResult(result)
    } catch (error) {
      const cause = error.cause ?? { error: error.message }
      addMessage(error.message, 'error')
      setTestResult(cause)
    } finally {
      setIsTesting(false)
    }
  }

  let parsedHeaders: Record<string, string> | null = null
  try {
    const parsed = JSON.parse(env.request_headers)
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed) && Object.keys(parsed).length > 0) {
      parsedHeaders = parsed
    }
  } catch {
    // not valid JSON or empty — skip
  }

  return (
    <Sheet>
      <Card className="gap-0">
        <CardHeader className="flex flex-row items-center justify-between gap-4 px-5 py-4">
          <div className="flex min-w-0 flex-1 items-center gap-3">
            <MethodBadge method={env.method} />
            <div className="flex min-w-0 flex-1 items-center gap-1.5">
              <span className="truncate font-mono text-sm text-foreground" title={env.url}>
                {env.url}
              </span>
              <CopyUrlButton url={env.url} />
            </div>
          </div>
          <SheetTrigger asChild>
            <Button variant="black" size="sm" onClick={() => handleTest(env.id)} disabled={isTesting} className="shrink-0">
              <PlayCircle className="size-4" />
              {isTesting ? 'Running...' : 'Test'}
            </Button>
          </SheetTrigger>
        </CardHeader>

        {parsedHeaders && (
          <div className="border-t px-5 py-3">
            <p className="mb-2 text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Headers</p>
            <DescriptionList>
              {Object.entries(parsedHeaders).map(([key, value]) => (
                <DescriptionListItem key={key} term={key}>
                  {value}
                </DescriptionListItem>
              ))}
            </DescriptionList>
          </div>
        )}

        {Object.keys(mappingConfig).length > 0 && (
          <div className="border-t px-5 py-3">
            <TokenList mappingConfig={mappingConfig} />
          </div>
        )}

        <ResponseConfigSection env={env} />
      </Card>

      <SheetContent className="w-[400px] gap-0 sm:w-[540px]">
        <SheetHeader>
          <SheetTitle>Test Result</SheetTitle>
          <SheetDescription>Result of the API request test.</SheetDescription>
        </SheetHeader>
        <div className="flex flex-1 flex-col gap-6 overflow-auto px-4">
          {isTesting && (
            <div className="flex items-center justify-center p-8">
              <div className="flex items-center gap-2">
                <Clock className="size-4 animate-spin" />
                <span>Loading test result...</span>
              </div>
            </div>
          )}
          {testResult && (
            <JsonViewer
              data={JSON.stringify(testResult, null, 2)}
              className="flex-1"
              title={<span>Response Data</span>}
            />
          )}
        </div>
        <SheetFooter>
          <SheetClose asChild>
            <Button variant="outline">Close</Button>
          </SheetClose>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
