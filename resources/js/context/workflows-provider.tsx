import { useForm } from '@inertiajs/react'
import { createContext, useMemo } from 'react'
import { route } from 'ziggy-js'
import type { Buyer, Workflow, WorkflowBuyer } from '@/types/ping-post'

interface WorkflowFormData {
  name: string
  execution_mode: string
  strategy: string
  global_timeout_ms: number
  is_active: boolean
  cascade_on_post_rejection: boolean
  cascade_max_retries: number
  advance_on_rejection: boolean
  advance_on_timeout: boolean
  advance_on_error: boolean
  buyers: WorkflowBuyer[]
}

interface WorkflowsContextValue {
  isEdit: boolean
  data: WorkflowFormData
  errors: Record<string, string>
  processing: boolean
  handleSubmit: (e: React.FormEvent<HTMLFormElement>) => void
  setData: (key: string, value: any) => void
  availableBuyers: Buyer[]
  strategies: Array<{ value: string; label: string }>
}

export const WorkflowsContext = createContext<WorkflowsContextValue | null>(null)

function buildInitialData(workflow: Workflow | null, availableBuyers: Buyer[]): WorkflowFormData {
  const integrationToBuyer = new Map(availableBuyers.map((b) => [b.integration_id, b.id]))

  return {
    name: workflow?.name ?? '',
    execution_mode: workflow?.execution_mode ?? 'sync',
    strategy: workflow?.strategy ?? 'best_bid',
    global_timeout_ms: workflow?.global_timeout_ms ?? 3000,
    is_active: workflow?.is_active ?? true,
    cascade_on_post_rejection: workflow?.cascade_on_post_rejection ?? true,
    cascade_max_retries: workflow?.cascade_max_retries ?? 3,
    advance_on_rejection: workflow?.advance_on_rejection ?? true,
    advance_on_timeout: workflow?.advance_on_timeout ?? true,
    advance_on_error: workflow?.advance_on_error ?? false,
    buyers: (workflow?.workflow_buyers ?? []).map((wb) => ({
      id: wb.id,
      workflow_id: wb.workflow_id,
      buyer_id: integrationToBuyer.get(wb.integration_id),
      integration_id: wb.integration_id,
      position: wb.position,
      is_fallback: wb.is_fallback,
      buyer_group: wb.buyer_group,
      is_active: wb.is_active,
      integration: wb.integration,
    })),
  }
}

interface Props {
  children: React.ReactNode
  workflow?: Workflow | null
  availableBuyers?: Buyer[]
  strategies?: Array<{ value: string; label: string }>
}

export function WorkflowsProvider({ children, workflow = null, availableBuyers = [], strategies = [] }: Props) {
  const isEdit = !!workflow
  const initialData = useMemo(() => buildInitialData(workflow, availableBuyers), [workflow?.id])

  const { data, setData, post, put, processing, errors } = useForm(initialData)

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const options = { preserveScroll: true }
    if (isEdit && workflow?.id) {
      put(route('ping-post.workflows.update', workflow.id), options)
    } else {
      post(route('ping-post.workflows.store'), options)
    }
  }

  const value: WorkflowsContextValue = {
    isEdit,
    data,
    errors,
    processing,
    handleSubmit,
    setData: setData as any,
    availableBuyers,
    strategies,
  }

  return <WorkflowsContext.Provider value={value}>{children}</WorkflowsContext.Provider>
}
