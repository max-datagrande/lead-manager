import { Button } from '@/components/ui/button'
import { router } from '@inertiajs/react'
import { Plus } from 'lucide-react'

export const PostbacksActions = () => {
  return (
    <Button onClick={() => router.visit(route('postbacks.create'))} className="flex items-center gap-2">
      <Plus className="h-4 w-4" />
      New Postback
    </Button>
  )
}
