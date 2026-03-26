import { useForm } from '@inertiajs/react'
import { createContext, useMemo } from 'react'
import { route } from 'ziggy-js'
import type { Buyer, CapRule, EligibilityRule } from '@/types/ping-post'

interface BuyersContextValue {
  isEdit: boolean
  data: {
    name: string
    integration_id: number | null
    is_active: boolean
    company_id: number | null
    ping_timeout_ms: number | ''
    post_timeout_ms: number | ''
    pricing_type: string
    fixed_price: string
    min_bid: string
    postback_pending_days: number | ''
    eligibility_rules: EligibilityRule[]
    caps: CapRule[]
  }
  errors: Record<string, string>
  processing: boolean
  handleSubmit: (e: React.FormEvent<HTMLFormElement>) => void
  setData: (key: string, value: any) => void
}

export const BuyersContext = createContext<BuyersContextValue | null>(null)

function buildInitialData(buyer: Buyer | null) {
  const cfg = buyer?.buyerConfig
  return {
    name: buyer?.name ?? '',
    integration_id: buyer?.integration_id ?? null,
    is_active: buyer?.is_active ?? true,
    company_id: buyer?.company_id ?? null,
    ping_timeout_ms: cfg?.ping_timeout_ms ?? '',
    post_timeout_ms: cfg?.post_timeout_ms ?? '',
    pricing_type: cfg?.pricing_type ?? 'fixed',
    fixed_price: String(cfg?.fixed_price ?? ''),
    min_bid: String(cfg?.min_bid ?? ''),
    postback_pending_days: cfg?.postback_pending_days ?? '',
    eligibility_rules: buyer?.eligibilityRules ?? [],
    caps: buyer?.capRules ?? [],
  }
}

interface Props {
  children: React.ReactNode
  buyer?: Buyer | null
}

export function BuyersProvider({ children, buyer = null }: Props) {
  const isEdit = !!buyer
  const initialData = useMemo(() => buildInitialData(buyer), [buyer?.id])

  const { data, setData, post, put, processing, errors } = useForm(initialData)

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const options = { preserveScroll: true }
    if (isEdit && buyer?.id) {
      put(route('ping-post.buyers.update', buyer.id), options)
    } else {
      post(route('ping-post.buyers.store'), options)
    }
  }

  const value: BuyersContextValue = { isEdit, data, errors, processing, handleSubmit, setData: setData as any }

  return <BuyersContext.Provider value={value}>{children}</BuyersContext.Provider>
}
