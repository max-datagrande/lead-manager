import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { cn } from '@/lib/utils'
import type { Buyer, WorkflowBuyer } from '@/types/ping-post'
import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  type DragEndEvent,
  useSensor,
  useSensors,
} from '@dnd-kit/core'
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { GripVertical, Plus, Trash2, Users } from 'lucide-react'

interface SortableRowProps {
  item: WorkflowBuyer
  position: number
  availableBuyers: Buyer[]
  strategy: string
  onUpdate: (updates: Partial<WorkflowBuyer>) => void
  onRemove: () => void
}

function SortableRow({ item, position, availableBuyers, strategy, onUpdate, onRemove }: SortableRowProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: item.buyer_id! })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  }

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        'flex items-center gap-3 rounded-lg border px-3 py-2.5 transition-all',
        isDragging ? 'opacity-50 shadow-lg' : '',
        !item.is_active ? 'opacity-60 bg-muted/30' : 'bg-card',
        item.is_fallback && item.is_active ? 'border-amber-400/50 bg-amber-50/30 dark:bg-amber-900/10' : '',
      )}
    >
      {/* Drag handle + position */}
      <div className="flex shrink-0 items-center gap-1.5">
        <button
          type="button"
          {...attributes}
          {...listeners}
          className="cursor-grab touch-none text-muted-foreground hover:text-foreground active:cursor-grabbing"
          aria-label="Drag to reorder"
        >
          <GripVertical className="h-4 w-4" />
        </button>
        <span className="w-4 text-center font-mono text-xs text-muted-foreground">{position + 1}</span>
      </div>

      {/* Buyer select */}
      <Select value={String(item.buyer_id)} onValueChange={(v) => onUpdate({ buyer_id: parseInt(v) })}>
        <SelectTrigger className="min-w-0 flex-1 max-w-64">
          <SelectValue placeholder="Select buyer" />
        </SelectTrigger>
        <SelectContent>
          {availableBuyers.map((b) => (
            <SelectItem key={b.id} value={String(b.id)}>
              <span className="block max-w-[220px] truncate">{b.name}</span>
              <span className="ml-1 text-xs text-muted-foreground">({b.integration?.type})</span>
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {/* Group select — combined strategy only */}
      {strategy === 'combined' && (
        <Select value={item.buyer_group} onValueChange={(v) => onUpdate({ buyer_group: v as 'primary' | 'secondary' })}>
          <SelectTrigger className="w-28 shrink-0">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="primary">Primary</SelectItem>
            <SelectItem value="secondary">Secondary</SelectItem>
          </SelectContent>
        </Select>
      )}

      {/* Fallback toggle */}
      <div className="flex shrink-0 items-center gap-1.5">
        <Switch
          checked={item.is_fallback}
          onCheckedChange={(v) => onUpdate({ is_fallback: v })}
          id={`fallback-${item.buyer_id}`}
          className="scale-90"
        />
        <Label htmlFor={`fallback-${item.buyer_id}`} className="cursor-pointer whitespace-nowrap text-xs text-muted-foreground">
          Fallback
        </Label>
      </div>

      {/* Active toggle */}
      <div className="flex shrink-0 items-center gap-1.5">
        <Switch
          checked={item.is_active}
          onCheckedChange={(v) => onUpdate({ is_active: v })}
          id={`active-${item.buyer_id}`}
          className="scale-90"
        />
        <Label htmlFor={`active-${item.buyer_id}`} className="cursor-pointer text-xs text-muted-foreground">
          Active
        </Label>
      </div>

      {/* Delete */}
      <Button
        variant="ghost-destructive"
        size="icon"
        onClick={onRemove}
        type="button"
        className="ml-auto h-8 w-8 shrink-0"
        aria-label="Remove buyer"
      >
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

    const oldIndex = buyers.findIndex((b) => b.buyer_id === active.id)
    const newIndex = buyers.findIndex((b) => b.buyer_id === over.id)
    const reordered = arrayMove(buyers, oldIndex, newIndex).map((b, i) => ({ ...b, position: i }))
    onChange(reordered)
  }

  const addBuyer = () => {
    const used = new Set(buyers.map((b) => b.buyer_id))
    const next = availableBuyers.find((b) => !used.has(b.id))
    if (!next) return
    onChange([...buyers, { buyer_id: next.id, integration_id: next.integration_id, position: buyers.length, is_fallback: false, buyer_group: 'primary', is_active: true }])
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
        <SortableContext items={buyers.map((b) => b.buyer_id!)} strategy={verticalListSortingStrategy}>
          {buyers.map((item, i) => (
            <SortableRow
              key={item.buyer_id}
              item={item}
              position={i}
              availableBuyers={availableBuyers}
              strategy={strategy}
              onUpdate={(updates) => updateAt(i, updates)}
              onRemove={() => removeAt(i)}
            />
          ))}
        </SortableContext>
      </DndContext>

      {buyers.length === 0 && (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed px-6 py-10 text-center">
          <Users className="h-8 w-8 text-muted-foreground/50" />
          <p className="text-sm font-medium text-muted-foreground">No buyers added yet</p>
          <p className="text-xs text-muted-foreground/70">Add buyers to this workflow to start distributing leads.</p>
        </div>
      )}

      {availableBuyers.length > buyers.length && (
        <Button variant="outline" size="sm" onClick={addBuyer} type="button" className="mt-1">
          <Plus className="mr-1.5 h-4 w-4" />
          Add Buyer
        </Button>
      )}
    </div>
  )
}
