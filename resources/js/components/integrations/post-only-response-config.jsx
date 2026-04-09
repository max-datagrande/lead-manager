import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useIntegrations } from '@/hooks/use-integrations';

const POST_ONLY_FIELDS = [
  { key: 'accepted_path', label: 'Accepted Path', placeholder: 'e.g. result' },
  { key: 'accepted_value', label: 'Accepted Value', placeholder: 'e.g. success / true / 1' },
  { key: 'rejected_path', label: 'Rejected Path', placeholder: 'e.g. error_message' },
];

const ERROR_FIELDS = [
  { key: 'error_path', label: 'Error Path', placeholder: 'e.g. response.status / outcome' },
  { key: 'error_value', label: 'Error Value', placeholder: 'e.g. Error — leave empty for truthy check' },
  { key: 'error_reason_path', label: 'Error Reason Path', placeholder: 'e.g. response.errors.error / reason' },
];

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
            </div>
          ))}
        </div>
        <div className="space-y-4">
          <div>
            <p className="text-sm font-medium">Error Detection</p>
            <p className="text-xs text-muted-foreground">Detect errors in valid JSON responses. Evaluated before accepted/rejected logic.</p>
          </div>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            {ERROR_FIELDS.map((field) => (
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
        </div>
      </CardContent>
    </Card>
  );
}
