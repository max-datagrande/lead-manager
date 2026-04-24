import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldHint } from '@/components/ui/field-hint';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import TagInput from '@/components/ui/tag-input.jsx';
import { useIntegrations } from '@/hooks/use-integrations';

const PING_HINTS = {
  bid_price_path: [
    'JSON path to the bid price offered by the buyer (e.g. "buyers.0.bid").',
    'If left empty, bid_price stays null. Required when the buyer pricing source is Bid or Conditional; not needed for Fixed pricing.',
  ],
  lead_id_path: [
    'JSON path to the external identifier returned in the ping (e.g. "ping_id").',
    'If left empty, the subsequent POST will not forward "ping_lead_id" to the buyer. Many ping-post APIs require it to match the post against the previous ping.',
  ],
  accepted_path: [
    'JSON path that signals the buyer accepted the ping.',
    'If left empty, acceptance falls back to "HTTP 2xx and no configured error detected". Combine with Error Path to catch business errors returned on 200.',
  ],
  accepted_value: [
    'Exact string the Accepted Path must equal to count as accepted. Compared via string cast.',
    'Only used when Accepted Path is set. Warning: if left empty and the path does not exist in the response, comparison ends up "" === "" and falsely marks accepted. Always set a value when Accepted Path is set.',
  ],
};

const POST_HINTS = {
  accepted_path: [
    'JSON path that signals the buyer accepted the lead (e.g. "status").',
    'If left empty, acceptance falls back to "HTTP 2xx and no configured error detected".',
  ],
  accepted_value: [
    'Exact string the Accepted Path must equal to count as accepted (e.g. "succeeded").',
    'Only used when Accepted Path is set. If left empty and the path is missing from the response, the comparison falsely returns accepted. Always pair both fields.',
  ],
  rejected_path: [
    'JSON path to a rejection reason the buyer returns on a non-error rejection (e.g. {"status":"rejected","reason":"out of coverage area"}).',
    'Only read in step 3 of parsing, after HTTP errors and configured error_path have already been ruled out. The extracted string is persisted to post_results.rejection_reason and surfaced in timeline and logs.',
    'Does not affect the accepted / rejected / error decision — pure metadata. Leave empty when the buyer API has no such field; errors already carry their reason through Error Reason Path.',
  ],
  bid_price_path: [
    'JSON path to the actual revenue returned by the buyer after the post (e.g. "rev" or "data.price").',
    'When the post is accepted and this path is set, the extracted number becomes the final price on the post result. The Bid Price from the ping is kept as the offered price for drift tracking, and a price-extracted event is logged to the timeline.',
    'Useful when the buyer decides final pricing server-side — e.g. MediaAlpha selling to a different buyer than the top ping bid, shared sales summing revenue across multiple buyers, or last-minute price adjustments. Leave empty to trust the ping bid as-is.',
  ],
};

const ERROR_HINTS = {
  error_path: [
    'JSON path that marks a business error in a valid JSON response (e.g. "error" or "status").',
    'If left empty, only HTTP 5xx and invalid JSON are treated as errors. Business errors returned on 200 (duplicate, cap reached, expired) will not be distinguished from a normal rejection.',
  ],
  error_value: [
    'Exact string that Error Path must equal to trigger an error.',
    'If left empty, "exists mode" is used: any truthy value at the path triggers an error. Use the exact value when the path also carries non-error states (e.g. status=succeeded|failed).',
  ],
  error_reason_path: [
    'JSON paths used to extract the error message for logs and alerts. Checked in order, first non-empty wins.',
    'If left empty and Error Value is also empty, the reason falls back to the value at Error Path when it is a string; otherwise a generic placeholder is used.',
  ],
  error_excludes: [
    'Case-insensitive substrings matched against the extracted reason.',
    'On match, the result is stored as REJECTED with no alert fired. On no match, the result is stored as ERROR and an alert is dispatched. Use for expected errors like "duplicate", "cap reached", "No match", "Expired".',
  ],
};

const PING_FIELDS = [
  { key: 'bid_price_path', label: 'Bid Price Path', placeholder: 'e.g. buyers.0.bid' },
  { key: 'lead_id_path', label: 'Lead ID Path', placeholder: 'e.g. ping_id' },
  { key: 'accepted_path', label: 'Accepted Path', placeholder: 'e.g. status' },
  { key: 'accepted_value', label: 'Accepted Value', placeholder: 'e.g. accepted / true / 1' },
];

const POST_FIELDS = [
  { key: 'accepted_path', label: 'Accepted Path', placeholder: 'e.g. status' },
  { key: 'accepted_value', label: 'Accepted Value', placeholder: 'e.g. succeeded / true / 1' },
  { key: 'rejected_path', label: 'Rejected Path', placeholder: 'e.g. error_message' },
];

const ERROR_PATH_FIELDS = [
  { key: 'error_path', label: 'Error Path', placeholder: 'e.g. error / status' },
  { key: 'error_value', label: 'Error Value', placeholder: 'e.g. failed — leave empty for truthy check' },
];

function PriceOverrideSection({ env, responseConfig, handleChange }) {
  const enabled = responseConfig.bid_price_path !== null && responseConfig.bid_price_path !== undefined;

  const toggle = (on) => {
    handleChange('bid_price_path', on ? '' : null);
  };

  return (
    <div className="space-y-3 rounded-md border border-dashed p-4">
      <div className="flex items-center justify-between gap-3">
        <Label htmlFor={`post-${env}-override-pricing`} className="flex cursor-pointer items-center text-sm font-medium">
          Override pricing from post response?
          <FieldHint text={POST_HINTS.bid_price_path} />
        </Label>
        <Switch id={`post-${env}-override-pricing`} checked={enabled} onCheckedChange={toggle} />
      </div>
      {enabled && (
        <div className="space-y-2">
          <Label htmlFor={`post-${env}-bid-price-path`}>Bid Price Path</Label>
          <Input
            id={`post-${env}-bid-price-path`}
            placeholder="e.g. rev"
            value={responseConfig.bid_price_path ?? ''}
            onChange={(e) => handleChange('bid_price_path', e.target.value)}
          />
          <p className="text-xs text-muted-foreground">
            Extracted value becomes the final price on the post result. The Bid Price from the ping is kept as the offered price so you can track
            drift.
          </p>
        </div>
      )}
    </div>
  );
}

/**
 * Parse error_reason_path from DB string (pipe-separated) to array for TagInput.
 */
function parseReasonPaths(value) {
  if (Array.isArray(value)) return value;
  if (!value || typeof value !== 'string') return [];
  return value
    .split('|')
    .map((s) => s.trim())
    .filter(Boolean);
}

export function PingPostResponseConfig({ envType, env }) {
  const { data, handleEnvironmentChange } = useIntegrations();
  const responseConfig = data.environments?.[envType]?.[env]?.response_config ?? {};

  const fields = envType === 'ping' ? PING_FIELDS : POST_FIELDS;
  const fieldHints = envType === 'ping' ? PING_HINTS : POST_HINTS;
  const title = envType === 'ping' ? 'Ping Response Config' : 'Post Response Config';
  const description =
    envType === 'ping'
      ? 'Configure how to parse the buyer response to the ping: extract the bid price, acceptance flag, and external lead ID.'
      : 'Configure how to parse the buyer confirmation after posting the full lead.';

  const handleChange = (key, value) => {
    handleEnvironmentChange(env, 'response_config', { ...responseConfig, [key]: value }, envType);
  };

  return (
    <Card className="mt-4">
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {fields.map((field) => (
            <div key={field.key} className="space-y-2">
              <Label htmlFor={`${envType}-${env}-${field.key}`} className="flex items-center">
                {field.label}
                {fieldHints[field.key] && <FieldHint text={fieldHints[field.key]} />}
              </Label>
              <Input
                id={`${envType}-${env}-${field.key}`}
                placeholder={field.placeholder}
                value={responseConfig[field.key] ?? ''}
                onChange={(e) => handleChange(field.key, e.target.value)}
              />
            </div>
          ))}
        </div>
        {envType === 'post' && <PriceOverrideSection env={env} responseConfig={responseConfig} handleChange={handleChange} />}
        <div className="space-y-4">
          <div>
            <p className="text-sm font-medium">Error Detection</p>
            <p className="text-xs text-muted-foreground">Detect errors in valid JSON responses. Evaluated before accepted/rejected logic.</p>
          </div>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {ERROR_PATH_FIELDS.map((field) => (
              <div key={field.key} className="space-y-2">
                <Label htmlFor={`${envType}-${env}-${field.key}`} className="flex items-center">
                  {field.label}
                  <FieldHint text={ERROR_HINTS[field.key]} />
                </Label>
                <Input
                  id={`${envType}-${env}-${field.key}`}
                  placeholder={field.placeholder}
                  value={responseConfig[field.key] ?? ''}
                  onChange={(e) => handleChange(field.key, e.target.value)}
                />
              </div>
            ))}
          </div>
          <div className="space-y-2">
            <Label className="flex items-center">
              Error Reason Path
              <FieldHint text={ERROR_HINTS.error_reason_path} />
            </Label>
            <TagInput value={parseReasonPaths(responseConfig.error_reason_path)} onChange={(paths) => handleChange('error_reason_path', paths)} />
          </div>
          <div className="space-y-2">
            <Label className="flex items-center">
              Error Excludes
              <FieldHint text={ERROR_HINTS.error_excludes} />
            </Label>
            <TagInput value={responseConfig.error_excludes ?? []} onChange={(excludes) => handleChange('error_excludes', excludes)} />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
