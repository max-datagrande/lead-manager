import { Button } from '@/components/ui/button'
import { Link } from '@inertiajs/react'
import { Plus } from 'lucide-react'
import { route } from 'ziggy-js'

export function BuyerActions() {
  return (
    <Button asChild>
      <Link href={route('ping-post.buyers.create')}>
        <Plus className="mr-2 h-4 w-4" />
        Add Buyer
      </Link>
    </Button>
  )
}
