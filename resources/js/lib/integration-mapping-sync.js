/**
 * Helpers to reconcile the implicit link between request_body tokens ({$<id>})
 * and the field_mappings array on the client side. Mirrors the backend
 * IntegrationMappingReconciler so the form can warn the user before save.
 */

const TOKEN_REGEX = /\{\$(\d+)\}/g;

/**
 * Scan a single request_body (string or object) for {$N} tokens.
 *
 * @param {string|object|null|undefined} body
 * @param {Set<number>} acc  Accumulator set the ids are added to.
 */
const scanBody = (body, acc) => {
  if (body === null || body === undefined || body === '') return;
  const text = typeof body === 'object' ? JSON.stringify(body) : String(body);
  let m;
  while ((m = TOKEN_REGEX.exec(text)) !== null) {
    acc.add(parseInt(m[1], 10));
  }
};

/**
 * Collect every {$N} field id referenced across all environments.
 * Handles both flat (offerwall/post-only) and nested (ping-post) shapes.
 *
 * @param {object} environments  data.environments from the form
 * @returns {Set<number>}
 */
export const scanAllBodyTokens = (environments = {}) => {
  const ids = new Set();
  for (const val of Object.values(environments)) {
    if (val?.request_body !== undefined) {
      scanBody(val.request_body, ids);
    } else if (val && typeof val === 'object') {
      for (const inner of Object.values(val)) {
        if (inner?.request_body !== undefined) scanBody(inner.request_body, ids);
      }
    }
  }
  return ids;
};

/**
 * True when a value_mapping object actually holds at least one mapped pair.
 *
 * @param {object|null|undefined} valueMapping
 */
const hasValueMapping = (valueMapping) => Boolean(valueMapping && typeof valueMapping === 'object' && Object.keys(valueMapping).length > 0);

/**
 * Non-empty list of usable possible_values for a field, or [] when it has none.
 *
 * @param {{ possible_values?: string[] }|undefined} field
 * @returns {string[]}
 */
const possibleValuesOf = (field) => (Array.isArray(field?.possible_values) ? field.possible_values.filter(Boolean) : []);

/**
 * True when a mapping carries user-entered configuration worth warning about
 * before deletion (a default value or a non-empty value_mapping).
 *
 * @param {{ default_value?: string|null, value_mapping?: object|null }} mapping
 */
const hasUserConfig = (mapping) => {
  const hasDefault = mapping.default_value !== null && mapping.default_value !== undefined && mapping.default_value !== '';
  return Boolean(hasDefault || hasValueMapping(mapping.value_mapping));
};

/**
 * Compute the divergence between body tokens and the current field_mappings.
 *
 * Only fields with possible_values are "mappable": a field without enumerated
 * values has nothing to map and is added with defaults silently — so it never
 * warrants a banner. The actionable signal is a mappable field present in a body
 * whose value_mapping is not configured yet (the buyer expects mapped values).
 *
 * @param {object} environments  data.environments
 * @param {Array<{ field_id: number, default_value?: string|null, value_mapping?: object|null }>} fieldMappings
 * @param {Array<{ id: number, possible_values?: string[] }>} fields
 * @returns {{ bodyTokens: Set<number>, needsValueMapping: number[], orphansWithConfig: number[] }}
 *   - needsValueMapping: field ids in a body that have possible_values but no value_mapping yet.
 *   - orphansWithConfig: mapped ids no longer in any body whose config would be lost.
 */
export const computeMappingSync = (environments = {}, fieldMappings = [], fields = []) => {
  const bodyTokens = scanAllBodyTokens(environments);
  const fieldsById = new Map(fields.map((f) => [f.id, f]));
  const mappingById = new Map(fieldMappings.map((m) => [m.field_id, m]));

  const needsValueMapping = [...bodyTokens].filter((id) => {
    if (possibleValuesOf(fieldsById.get(id)).length === 0) return false;
    return !hasValueMapping(mappingById.get(id)?.value_mapping);
  });

  const orphansWithConfig = fieldMappings.filter((m) => !bodyTokens.has(m.field_id) && hasUserConfig(m)).map((m) => m.field_id);

  return { bodyTokens, needsValueMapping, orphansWithConfig };
};
