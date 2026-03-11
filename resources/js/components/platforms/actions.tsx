import { Button } from '@/components/ui/button'
import { usePlatforms } from '@/hooks/use-platforms'
import { Plus } from 'lucide-react'

export const PlatformsActions = () => {
  const { showCreateModal } = usePlatforms()

  return (
    <Button onClick={showCreateModal} className="flex items-center gap-2">
      <Plus className="h-4 w-4" />
      Add Platform
    </Button>
  )
}
