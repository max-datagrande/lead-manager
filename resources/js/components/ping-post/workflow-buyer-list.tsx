import { Alert, AlertDescription } from '@/components/ui/alert'
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
import { GripVertical, Plus, ShieldAlert, Trash2, Users } from 'lucide-react'

// ── Sortable row (regular buyers only, no fallback) ────────────────────────────

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

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(
        'flex items-center gap-3 rounded-lg border bg-card px-3 py-2.5 transition-all',
        isDragging && 'opacity-50 shadow-lg',
        !item.is_active && 'opacity-60 bg-muted/30',
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
        <SelectTrigger className="max-w-64 min-w-0 flex-1">
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

// ── Fallback row (single, amber, no drag) ──────────────────────────────────────

interface FallbackRowProps {
  item: WorkflowBuyer
  availableBuyers: Buyer[]
  onUpdate: (updates: Partial<WorkflowBuyer>) => void
  onRemove: () => void
}

function FallbackRow({ item, availableBuyers, onUpdate, onRemove }: FallbackRowProps) {
  return (
    <div
      className={cn(
        'flex items-center gap-3 rounded-lg border px-3 py-2.5 transition-all',
        'border-amber-400/50 bg-amber-50/30 dark:bg-amber-900/10',
        !item.is_active && 'opacity-60',
      )}
    >
      <ShieldAlert className="h-4 w-4 shrink-0 text-amber-500" aria-label="Fallback buyer" />

      <Select value={String(item.buyer_id)} onValueChange={(v) => onUpdate({ buyer_id: parseInt(v) })}>
        <SelectTrigger className="max-w-64 min-w-0 flex-1">
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

      <div className="flex shrink-0 items-center gap-1.5">
        <Switch
          checked={item.is_active}
          onCheckedChange={(v) => onUpdate({ is_active: v })}
          id={`fallback-active-${item.buyer_id}`}
          className="scale-90"
        />
        <Label htmlFor={`fallback-active-${item.buyer_id}`} className="cursor-pointer text-xs text-muted-foreground">
          Active
        </Label>
      </div>

      <Button
        variant="ghost-destructive"
        size="icon"
        onClick={onRemove}
        type="button"
        className="ml-auto h-8 w-8 shrink-0"
        aria-label="Remove fallback buyer"
      >
        <Trash2 className="h-4 w-4" />
      </Button>
    </div>
  )
}

// ── Main component ─────────────────────────────────────────────────────────────

interface Props {
  buyers: WorkflowBuyer[]
  availableBuyers: Buyer[]
  strategy: string
  onChange: (buyers: WorkflowBuyer[]) => void
}

export function WorkflowBuyerList({ buyers, availableBuyers, strategy, onChange }: Props) {
  const regularBuyers = buyers.filter((b) => !b.is_fallback)
  const fallbackBuyer = buyers.find((b) => b.is_fallback) ?? null

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  // Persist fallback when updating regular list
  const commitRegular = (updated: WorkflowBuyer[]) => {
    onChange(fallbackBuyer ? [...updated, fallbackBuyer] : updated)
  }

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event
    if (!over || active.id === over.id) return
    const oldIndex = regularBuyers.findIndex((b) => b.buyer_id === active.id)
    const newIndex = regularBuyers.findIndex((b) => b.buyer_id === over.id)
    commitRegular(arrayMove(regularBuyers, oldIndex, newIndex).map((b, i) => ({ ...b, position: i })))
  }

  const addBuyer = () => {
    const used = new Set(buyers.map((b) => b.buyer_id))
    const next = availableBuyers.find((b) => !used.has(b.id))
    if (!next) return
    commitRegular([
      ...regularBuyers,
      { buyer_id: next.id, integration_id: next.integration_id, position: regularBuyers.length, is_fallback: false, buyer_group: 'primary', is_active: true },
    ])
  }

  const removeRegularAt = (index: number) => {
    commitRegular(regularBuyers.filter((_, i) => i !== index).map((b, i) => ({ ...b, position: i })))
  }

  const updateRegularAt = (index: number, updates: Partial<WorkflowBuyer>) => {
    commitRegular(regularBuyers.map((b, i) => (i === index ? { ...b, ...updates } : b)))
  }

  const addFallback = () => {
    const used = new Set(buyers.map((b) => b.buyer_id))
    const next = availableBuyers.find((b) => !used.has(b.id))
    if (!next) return
    onChange([
      ...regularBuyers,
      { buyer_id: next.id, integration_id: next.integration_id, position: regularBuyers.length, is_fallback: true, buyer_group: 'primary', is_active: true },
    ])
  }

  const updateFallback = (updates: Partial<WorkflowBuyer>) => {
    if (!fallbackBuyer) return
    onChange([...regularBuyers, { ...fallbackBuyer, ...updates }])
  }

  const removeFallback = () => onChange(regularBuyers)

  const canAddMore = availableBuyers.length > buyers.length
  const isEmpty = regularBuyers.length === 0 && !fallbackBuyer
  const showFallbackSection = regularBuyers.length > 0 || fallbackBuyer !== null

  return (
    <div className="space-y-2">

      {/* ── Empty state ─────────────────────────────────────────────────── */}
      {isEmpty && (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed px-6 py-10 text-center">
          <Users className="h-8 w-8 text-muted-foreground/50" />
          <p className="text-sm font-medium text-muted-foreground">No buyers added yet</p>
          <p className="text-xs text-muted-foreground/70">Add buyers to this workflow to start distributing leads.</p>
        </div>
      )}

      {/* ── Sortable buyers ─────────────────────────────────────────────── */}
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
        <SortableContext items={regularBuyers.map((b) => b.buyer_id!)} strategy={verticalListSortingStrategy}>
          {regularBuyers.map((item, i) => (
            <SortableRow
              key={item.buyer_id}
              item={item}
              position={i}
              availableBuyers={availableBuyers}
              strategy={strategy}
              onUpdate={(updates) => updateRegularAt(i, updates)}
              onRemove={() => removeRegularAt(i)}
            />
          ))}
        </SortableContext>
      </DndContext>

      {canAddMore && (
        <Button variant="outline" size="sm" onClick={addBuyer} type="button" className="mt-1">
          <Plus className="mr-1.5 h-4 w-4" />
          Add Buyer
        </Button>
      )}

      {/* ── Fallback section ────────────────────────────────────────────── */}
      {showFallbackSection && (
        <div className="space-y-2 pt-2">
          {/* Separator */}
          <div className="flex items-center gap-2">
            <div className="h-px flex-1 border-t border-dashed border-muted-foreground/25" />
            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <ShieldAlert className="h-3 w-3 text-amber-500" />
              Fallback
            </span>
            <div className="h-px flex-1 border-t border-dashed border-muted-foreground/25" />
          </div>

          {/* Fallback row */}
          {fallbackBuyer ? (
            <FallbackRow
              item={fallbackBuyer}
              availableBuyers={availableBuyers}
              onUpdate={updateFallback}
              onRemove={removeFallback}
            />
          ) : canAddMore ? (
            <button
              type="button"
              onClick={addFallback}
              className="flex w-full items-center gap-2 rounded-lg border border-dashed px-4 py-3 text-left text-sm text-muted-foreground transition-colors hover:border-amber-400/50 hover:bg-amber-50/30 hover:text-amber-700 dark:hover:bg-amber-900/10 dark:hover:text-amber-400"
            >
              <Plus className="h-4 w-4 shrink-0" />
              <span>Add Fallback Buyer</span>
              <span className="ml-auto text-xs opacity-70">Last resort if all buyers fail</span>
            </button>
          ) : (
            <Alert>
              <ShieldAlert className="h-4 w-4 text-muted-foreground" />
              <AlertDescription>
                No available buyers to assign as fallback. All configured buyers are already in the workflow. Remove one from the list above to assign it as fallback.
              </AlertDescription>
            </Alert>
          )}
        </div>
      )}
    </div>
  )
}
