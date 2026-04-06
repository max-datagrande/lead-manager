import PageHeader from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useModal } from '@/hooks/use-modal'
import { useToast } from '@/hooks/use-toast'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem, SharedData } from '@/types'
import { Head, Link, router, usePage } from '@inertiajs/react'
import axios from 'axios'
import { Check, Loader2, Pencil, Play, Search } from 'lucide-react'
import { type ReactNode, useMemo, useRef, useState } from 'react'

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Postbacks', href: '/postbacks' },
  { title: 'Fire', href: '#' },
];


interface Postback {
  id: number
  name: string
  param_mappings: Record<string, string>
}

interface Props {
  postback: Postback
}

const InternalFire = ({ postback }: Props) => {
  const tokens = [...new Set(Object.values(postback.param_mappings))].filter(Boolean)
  const modal = useModal()
  const { addMessage } = useToast()
  const { auth } = usePage<SharedData>().props
  const isAdmin = auth.user?.role === 'admin'

  const [fingerprint, setFingerprint] = useState('')
  const [resolvedValues, setResolvedValues] = useState<Record<string, string> | null>(null)
  const [editedValues, setEditedValues] = useState<Record<string, string>>({})
  const [editingTokens, setEditingTokens] = useState<Set<string>>(new Set())
  const [resolving, setResolving] = useState(false)
  const [firing, setFiring] = useState(false)
  const [error, setError] = useState('')
  const contentRef = useRef<HTMLDivElement>(null)

  const currentValues = useMemo(() => {
    if (!resolvedValues) return {}
    const merged: Record<string, string> = {}
    tokens.forEach((token) => {
      merged[token] = editedValues[token] ?? resolvedValues[token] ?? ''
    })
    return merged
  }, [resolvedValues, editedValues, tokens])

  const missingTokens = useMemo(() => tokens.filter((t) => !currentValues[t]), [tokens, currentValues])
  const allResolved = resolvedValues !== null && missingTokens.length === 0

  const handleResolve = async () => {
    if (!fingerprint.trim()) return

    setResolving(true)
    setError('')
    setResolvedValues(null)
    setEditedValues({})
    setEditingTokens(new Set())

    try {
      const { data } = await axios.post(route('postbacks.internal.resolve-tokens'), { fingerprint: fingerprint.trim() })

      if (data.success && Object.keys(data.data).length > 0) {
        setResolvedValues(data.data)

        const missing = tokens.filter((t) => !data.data[t])
        if (missing.length > 0) {
          addMessage(`Missing values for: ${missing.join(', ')}`, 'warning')
        }
      } else {
        setError('No data found for this fingerprint.')
      }
    } catch {
      setError('Failed to resolve fingerprint.')
    } finally {
      setResolving(false)
    }
  }

  const toggleEdit = (token: string) => {
    setEditingTokens((prev) => {
      const next = new Set(prev)
      if (next.has(token)) {
        next.delete(token)
      } else {
        next.add(token)
      }
      return next
    })
  }

  const handleFire = async () => {
    if (!allResolved) return

    const confirmed = await modal.confirm({
      title: 'Confirm manual fire',
      description: `This will fire postback "${postback.name}" with the resolved token values. This action will dispatch an HTTP request to the configured destination.`,
      confirmText: 'Fire',
      destructive: true,
    })

    if (!confirmed) return

    setFiring(true)

    router.post(route('postbacks.internal.fire', postback.id), currentValues, {
      onFinish: () => setFiring(false),
    })
  }

  return (
    <>
      <Head title={`Fire: ${postback.name}`} />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title={`Fire: ${postback.name}`} description="Manually fire this internal postback by resolving a fingerprint." />

        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10">
                <Search className="h-4 w-4 text-primary" />
              </div>
              <div>
                <CardTitle className="text-base">Fingerprint</CardTitle>
                <CardDescription className="text-xs">Enter a visitor fingerprint to resolve token values</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div className="flex gap-2">
              <Input
                value={fingerprint}
                onChange={(e) => setFingerprint(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), handleResolve())}
                placeholder="Paste fingerprint here..."
                className="font-mono text-sm"
              />
              <Button type="button" onClick={handleResolve} disabled={resolving || !fingerprint.trim()}>
                {resolving ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Resolve'}
              </Button>
            </div>
            {error && <p className="mt-2 text-sm text-destructive">{error}</p>}
          </CardContent>
        </Card>

        <div
          ref={contentRef}
          className="space-y-6 overflow-hidden transition-all duration-500 ease-out"
          style={{
            maxHeight: resolvedValues ? `${contentRef.current?.scrollHeight ?? 1000}px` : '0px',
            opacity: resolvedValues ? 1 : 0,
          }}
        >
          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10">
                  <Play className="h-4 w-4 text-primary" />
                </div>
                <div>
                  <CardTitle className="text-base">Resolved Tokens</CardTitle>
                  <CardDescription className="text-xs">Values resolved from fingerprint — these will be sent with the postback</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              {tokens.map((token) => {
                const value = currentValues[token] ?? ''
                const isEditing = editingTokens.has(token)
                const isEmpty = !value

                return (
                  <div key={token} className="space-y-1">
                    <Label className={`font-mono text-xs ${isEmpty ? 'text-destructive' : 'text-muted-foreground'}`}>{token}</Label>
                    <div className="flex gap-1">
                      <Input
                        readOnly={!isEditing}
                        value={value}
                        onChange={(e) => setEditedValues((prev) => ({ ...prev, [token]: e.target.value }))}
                        className={`font-mono text-sm ${isEditing ? '' : 'bg-muted'} ${isEmpty ? 'border-destructive' : ''}`}
                        placeholder={isEmpty ? 'No value found' : ''}
                      />
                      {isAdmin && (
                        <Button
                          type="button"
                          variant={isEditing ? 'default' : 'ghost'}
                          size="icon"
                          className="shrink-0"
                          onClick={() => toggleEdit(token)}
                          title={isEditing ? 'Lock field' : 'Edit field'}
                        >
                          {isEditing ? <Check className="h-3.5 w-3.5" /> : <Pencil className="h-3.5 w-3.5" />}
                        </Button>
                      )}
                    </div>
                  </div>
                )
              })}
            </CardContent>
          </Card>

          <div className="flex items-center justify-end gap-3">
            <Link href={route('postbacks.index')}>
              <Button type="button" variant="outline">
                Cancel
              </Button>
            </Link>
            <Button onClick={handleFire} disabled={firing || !allResolved}>
              {firing ? 'Firing…' : 'Fire Postback'}
            </Button>
          </div>
        </div>
      </div>
    </>
  )
}

InternalFire.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />
export default InternalFire
