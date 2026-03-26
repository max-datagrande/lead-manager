import { Button } from '@/components/ui/button'
import { Link } from '@inertiajs/react'
import { Plus } from 'lucide-react'
import { route } from 'ziggy-js'

export function WorkflowActions() {
  return (
    <Button asChild>
      <Link href={route('ping-post.workflows.create')}>
        <Plus className="mr-2 h-4 w-4" />
        New Workflow
      </Link>
    </Button>
  )
}
