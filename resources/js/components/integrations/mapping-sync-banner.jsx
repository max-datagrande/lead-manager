import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { AlertTriangle, Trash2 } from 'lucide-react';

/**
 * Resolve a field id to its display name for the warning copy.
 */
const fieldName = (fields, id) => {
  const field = fields.find((f) => f.id === id);
  return field?.name ?? field?.label ?? `#${id}`;
};

/**
 * Inline list of field names rendered as secondary badges so they blend with the copy.
 */
const FieldBadges = ({ fields, ids }) => (
  <span className="inline-flex flex-wrap gap-1 align-middle">
    {ids.map((id) => (
      <Badge key={id} variant="secondary" className="font-mono text-xs font-normal">
        {fieldName(fields, id)}
      </Badge>
    ))}
  </span>
);

/**
 * Pre-save advisory banner. Mirrors what the backend IntegrationMappingReconciler
 * does on save, surfacing only the actionable cases:
 *
 * - needsValueMapping: fields with possible_values present in a body but without a
 *   value_mapping configured yet → the buyer would receive raw, unmapped values.
 * - orphansWithConfig: configured mappings no longer referenced by any body →
 *   deleted on save (their value_mapping / default_value would be lost).
 *
 * Read-only: it never mutates the form. "Show me" opens the Field Mappings modal,
 * which highlights the rows that still need a value mapping (same source: the form
 * computes the divergence once and feeds both this banner and the modal).
 *
 * @param {{ fields: Array<{ id: number, name?: string, label?: string }>, needsValueMapping: number[], orphansWithConfig: number[], onShow: () => void }} props
 */
export function MappingSyncBanner({ fields = [], needsValueMapping = [], orphansWithConfig = [], onShow }) {
  if (needsValueMapping.length === 0 && orphansWithConfig.length === 0) {
    return null;
  }

  return (
    <div className="mt-6 space-y-3">
      {needsValueMapping.length > 0 && (
        <Alert className="border-amber-500/50 text-amber-800 dark:border-amber-400/40 dark:text-amber-300 [&>svg]:text-amber-600 dark:[&>svg]:text-amber-400">
          <AlertTriangle />
          <AlertTitle>
            {needsValueMapping.length} {needsValueMapping.length === 1 ? 'field needs a value mapping' : 'fields need a value mapping'}
          </AlertTitle>
          <AlertDescription className="text-amber-700/90 dark:text-amber-300/80">
            <p>
              <FieldBadges fields={fields} ids={needsValueMapping} /> {needsValueMapping.length === 1 ? 'has' : 'have'} possible values but no value
              mapping configured. Map {needsValueMapping.length === 1 ? 'it' : 'them'} so the buyer receives the expected values.
            </p>
            {onShow && (
              <Button type="button" size="sm" variant="outline" className="mt-1 h-7 gap-1.5 text-xs" onClick={onShow}>
                Show me →
              </Button>
            )}
          </AlertDescription>
        </Alert>
      )}

      {orphansWithConfig.length > 0 && (
        <Alert variant="destructive">
          <Trash2 />
          <AlertTitle>
            {orphansWithConfig.length} {orphansWithConfig.length === 1 ? 'mapping will be removed' : 'mappings will be removed'} on save
          </AlertTitle>
          <AlertDescription>
            <p>
              <FieldBadges fields={fields} ids={orphansWithConfig} /> {orphansWithConfig.length === 1 ? 'is' : 'are'} no longer used in any body and
              had configuration (value mapping or default). That configuration will be lost. Restore the token in the body before saving if you want
              to keep it.
            </p>
          </AlertDescription>
        </Alert>
      )}
    </div>
  );
}
