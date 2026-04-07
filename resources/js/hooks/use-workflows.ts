import { WorkflowsContext } from '@/context/workflows-provider'
import { useContext } from 'react'

export function useWorkflows() {
  const ctx = useContext(WorkflowsContext)
  if (!ctx) throw new Error('useWorkflows must be used within WorkflowsProvider')
  return ctx
}
