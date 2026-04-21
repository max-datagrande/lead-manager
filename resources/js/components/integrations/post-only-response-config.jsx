import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TagInput from '@/components/ui/tag-input.jsx';
import { useIntegrations } from '@/hooks/use-integrations';

const POST_ONLY_FIELDS = [
  { key: 'accepted_path', label: 'Accepted Path', placeholder: 'e.g. result' },
  { key: 'accepted_value', label: 'Accepted Value', placeholder: 'e.g. success / true / 1' },
  { key: 'rejected_path', label: 'Rejected Path', placeholder: 'e.g. error_message' },
  { key: 'bid_price_path', label: 'Bid Price Path', placeholder: 'e.g. price or data.bid_amount' },
];

const ERROR_PATH_FIELDS = [
  { key: 'error_path', label: 'Error Path', placeholder: 'e.g. response.status / outcome' },
  { key: 'error_value', label: 'Error Value', placeholder: 'e.g. Error — leave empty for truthy check' },
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
              <Label htmlFor={`post-only-${env}-${field.key}`}>{field.label}</Label>
              <Input
                id={`post-only-${env}-${field.key}`}
                placeholder={field.placeholder}
                value={responseConfig[field.key] ?? ''}
                onChange={(e) => handleChange(field.key, e.target.value)}
              />
              {field.key === 'bid_price_path' && (
                <p className="text-xs text-muted-foreground">
                  JSON path to extract the price from the POST response. Used when price source is "Response Bid" on post-only integrations.
                </p>
              )}
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
                <Label htmlFor={`post-only-${env}-${field.key}`}>{field.label}</Label>
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
            <Label>Error Reason Path</Label>
            <TagInput value={parseReasonPaths(responseConfig.error_reason_path)} onChange={(paths) => handleChange('error_reason_path', paths)} />
            <p className="text-xs text-muted-foreground">JSON paths to extract the error message. Checked in order, first match wins.</p>
          </div>
          <div className="space-y-2">
            <Label>Error Excludes</Label>
            <TagInput value={responseConfig.error_excludes ?? []} onChange={(excludes) => handleChange('error_excludes', excludes)} />
            <p className="text-xs text-muted-foreground">
              Substrings to match against the error reason. Matching errors are treated as rejections (no alert). E.g. "duplicate", "cap reached".
            </p>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
