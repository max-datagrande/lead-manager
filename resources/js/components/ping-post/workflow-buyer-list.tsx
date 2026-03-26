import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import type { Buyer, WorkflowBuyer } from '@/types/ping-post'
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core'
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { GripVertical, Plus, Trash2 } from 'lucide-react'

interface SortableRowProps {
  item: WorkflowBuyer
  availableBuyers: Buyer[]
  strategy: string
  onUpdate: (updates: Partial<WorkflowBuyer>) => void
  onRemove: () => void
}

function SortableRow({ item, availableBuyers, strategy, onUpdate, onRemove }: SortableRowProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: item.integration_id })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  }

  return (
    <div ref={setNodeRef} style={style} className="flex items-center gap-3 rounded-md border bg-card px-3 py-2">
      <button type="button" {...attributes} {...listeners} className="cursor-grab text-muted-foreground hover:text-foreground">
        <GripVertical className="h-4 w-4" />
      </button>

      <Select value={String(item.integration_id)} onValueChange={(v) => onUpdate({ integration_id: parseInt(v) })}>
        <SelectTrigger className="w-48">
          <SelectValue placeholder="Select buyer" />
        </SelectTrigger>
        <SelectContent>
          {availableBuyers.map((b) => (
            <SelectItem key={b.id} value={String(b.id)}>
              {b.name}
              <span className="ml-1 text-xs text-muted-foreground">({b.type})</span>
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {strategy === 'combined' && (
        <Select value={item.buyer_group} onValueChange={(v) => onUpdate({ buyer_group: v as 'primary' | 'secondary' })}>
          <SelectTrigger className="w-28">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="primary">Primary</SelectItem>
            <SelectItem value="secondary">Secondary</SelectItem>
          </SelectContent>
        </Select>
      )}

      <div className="flex items-center gap-1">
        <Switch checked={item.is_fallback} onCheckedChange={(v) => onUpdate({ is_fallback: v })} id={`fallback-${item.integration_id}`} />
        <Label htmlFor={`fallback-${item.integration_id}`} className="cursor-pointer text-xs">
          Fallback
        </Label>
      </div>

      <div className="flex items-center gap-1">
        <Switch checked={item.is_active} onCheckedChange={(v) => onUpdate({ is_active: v })} id={`active-${item.integration_id}`} />
        <Label htmlFor={`active-${item.integration_id}`} className="cursor-pointer text-xs">
          Active
        </Label>
      </div>

      {item.is_fallback && (
        <Badge variant="outline" className="border-amber-500 text-amber-600 text-xs">
          Fallback
        </Badge>
      )}

      <Button variant="ghost" size="icon" onClick={onRemove} type="button" className="ml-auto shrink-0 text-destructive">
        <Trash2 className="h-4 w-4" />
      </Button>
    </div>
  )
}

interface Props {
  buyers: WorkflowBuyer[]
  availableBuyers: Buyer[]
  strategy: string
  onChange: (buyers: WorkflowBuyer[]) => void
}

export function WorkflowBuyerList({ buyers, availableBuyers, strategy, onChange }: Props) {
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event
    if (!over || active.id === over.id) return

    const oldIndex = buyers.findIndex((b) => b.integration_id === active.id)
    const newIndex = buyers.findIndex((b) => b.integration_id === over.id)
    const reordered = arrayMove(buyers, oldIndex, newIndex).map((b, i) => ({ ...b, position: i }))
    onChange(reordered)
  }

  const addBuyer = () => {
    const used = new Set(buyers.map((b) => b.integration_id))
    const next = availableBuyers.find((b) => !used.has(b.id))
    if (!next) return
    onChange([...buyers, { integration_id: next.id, position: buyers.length, is_fallback: false, buyer_group: 'primary', is_active: true }])
  }

  const removeAt = (index: number) => {
    onChange(buyers.filter((_, i) => i !== index).map((b, i) => ({ ...b, position: i })))
  }

  const updateAt = (index: number, updates: Partial<WorkflowBuyer>) => {
    onChange(buyers.map((b, i) => (i === index ? { ...b, ...updates } : b)))
  }

  return (
    <div className="space-y-2">
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
        <SortableContext items={buyers.map((b) => b.integration_id)} strategy={verticalListSortingStrategy}>
          {buyers.map((item, i) => (
            <SortableRow
              key={item.integration_id}
              item={item}
              availableBuyers={availableBuyers}
              strategy={strategy}
              onUpdate={(updates) => updateAt(i, updates)}
              onRemove={() => removeAt(i)}
            />
          ))}
        </SortableContext>
      </DndContext>
      {availableBuyers.length > buyers.length && (
        <Button variant="outline" size="sm" onClick={addBuyer} type="button">
          <Plus className="mr-1 h-4 w-4" />
          Add Buyer
        </Button>
      )}
      {buyers.length === 0 && (
        <p className="py-4 text-center text-sm text-muted-foreground">No buyers added yet. Click "Add Buyer" to start.</p>
      )}
    </div>
  )
}
