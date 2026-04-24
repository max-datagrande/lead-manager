import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldHint } from '@/components/ui/field-hint';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TagInput from '@/components/ui/tag-input.jsx';
import { useIntegrations } from '@/hooks/use-integrations';

const FIELD_HINTS = {
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
    'JSON path to extract the final price from the POST response (e.g. "data.bid_amount").',
    'Only used when the buyer pricing source is "Response Bid" on post-only integrations. Ignored otherwise.',
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
    'On match, the result is stored as REJECTED with no alert fired. On no match, the result is stored as ERROR and an alert is dispatched. Use for expected errors like "duplicate", "cap reached", "Expired".',
  ],
};

const POST_ONLY_FIELDS = [
  { key: 'accepted_path', label: 'Accepted Path', placeholder: 'e.g. status' },
  { key: 'accepted_value', label: 'Accepted Value', placeholder: 'e.g. success / true / 1' },
  { key: 'rejected_path', label: 'Rejected Path', placeholder: 'e.g. error_message' },
  { key: 'bid_price_path', label: 'Bid Price Path', placeholder: 'e.g. price or data.bid_amount' },
];

const ERROR_PATH_FIELDS = [
  { key: 'error_path', label: 'Error Path', placeholder: 'e.g. error / status' },
  { key: 'error_value', label: 'Error Value', placeholder: 'e.g. failed — leave empty for truthy check' },
];

function parseReasonPaths(value) {
  if (Array.isArray(value)) return value;
  if (!value || typeof value !== 'string') return [];
  return value
    .split('|')
    .map((s) => s.trim())
    .filter(Boolean);
}

export function PostOnlyResponseConfig({ env }) {
  const { data, handleEnvironmentChange } = useIntegrations();
  const responseConfig = data.environments?.[env]?.response_config ?? {};

  const handleChange = (key, value) => {
    handleEnvironmentChange(env, 'response_config', { ...responseConfig, [key]: value });
  };

  return (
    <Card className="mt-4">
      <CardHeader>
        <CardTitle>Post Response Config</CardTitle>
        <CardDescription>Configure how to parse the buyer confirmation after posting the full lead.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {POST_ONLY_FIELDS.map((field) => (
            <div key={field.key} className="space-y-2">
              <Label htmlFor={`post-only-${env}-${field.key}`} className="flex items-center">
                {field.label}
                <FieldHint text={FIELD_HINTS[field.key]} />
              </Label>
              <Input
                id={`post-only-${env}-${field.key}`}
                placeholder={field.placeholder}
                value={responseConfig[field.key] ?? ''}
                onChange={(e) => handleChange(field.key, e.target.value)}
              />
            </div>
          ))}
        </div>
        <div className="space-y-4">
          <div>
            <p className="text-sm font-medium">Error Detection</p>
            <p className="text-xs text-muted-foreground">Detect errors in valid JSON responses. Evaluated before accepted/rejected logic.</p>
          </div>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {ERROR_PATH_FIELDS.map((field) => (
              <div key={field.key} className="space-y-2">
                <Label htmlFor={`post-only-${env}-${field.key}`} className="flex items-center">
                  {field.label}
                  <FieldHint text={ERROR_HINTS[field.key]} />
                </Label>
                <Input
                  id={`post-only-${env}-${field.key}`}
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
