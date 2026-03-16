import { FormModal } from '@/components/platforms/index'
import { useModal } from '@/hooks/use-modal'
import { useToast } from '@/hooks/use-toast'
import { useForm } from '@inertiajs/react'
import { createContext, useState } from 'react'

export const PlatformsContext = createContext(null)

export function PlatformsProvider({ children, companies = [], internalTokens = [] }) {
  const { openAsync, confirm: confirmModal } = useModal();
  const { addMessage } = useToast()
  const [sorting, setSorting] = useState([])
  const [globalFilter, setGlobalFilter] = useState('')
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })
  const [resetTrigger, setResetTrigger] = useState(false)
  const { delete: destroy } = useForm()

  const showCreateModal = async () => {
    try {
      await openAsync(<FormModal entry={undefined} companies={companies} internalTokens={internalTokens} />)
    } catch (error) {
      addMessage('Error creating platform', 'error')
    }
  }

  const showEditModal = async (entry) => {
    try {
      await openAsync(<FormModal entry={entry} companies={companies} internalTokens={internalTokens} isEdit />)
    } catch (error) {
      addMessage('Error updating platform', 'error')
    }
  }

  const showDeleteModal = async (entry) => {
    const confirmed = await confirmModal({
      title: 'Delete Platform',
      description: `Are you sure you want to delete "${entry.name}"? This action cannot be undone.`,
      confirmText: 'Delete',
      cancelText: 'Cancel',
      destructive: true,
    });
    if (confirmed) {
      destroy(route('platforms.destroy', entry.id), { preserveScroll: true, preserveState: true })
    }
  }

  return (
    <PlatformsContext.Provider
      value={{
        showCreateModal,
        showEditModal,
        showDeleteModal,
        sorting,
        setSorting,
        globalFilter,
        setGlobalFilter,
        pagination,
        setPagination,
        resetTrigger,
        setResetTrigger,
      }}
    >
      {children}
    </PlatformsContext.Provider>
  )
}
