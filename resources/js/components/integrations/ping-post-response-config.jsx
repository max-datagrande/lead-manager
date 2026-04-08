import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useIntegrations } from '@/hooks/use-integrations';

const PING_FIELDS = [
  { key: 'bid_price_path', label: 'Bid Price Path', placeholder: 'e.g. data.bid' },
  { key: 'accepted_path', label: 'Accepted Path', placeholder: 'e.g. status' },
  { key: 'accepted_value', label: 'Accepted Value', placeholder: 'e.g. accepted / true / 1' },
  { key: 'lead_id_path', label: 'Lead ID Path', placeholder: 'e.g. response.lead_id' },
];

const POST_FIELDS = [
  { key: 'accepted_path', label: 'Accepted Path', placeholder: 'e.g. result' },
  { key: 'accepted_value', label: 'Accepted Value', placeholder: 'e.g. success / true / 1' },
  { key: 'rejected_path', label: 'Rejected Path', placeholder: 'e.g. error_message' },
];

const ERROR_FIELDS = [
  { key: 'error_path', label: 'Error Path', placeholder: 'e.g. response.status / outcome' },
  { key: 'error_value', label: 'Error Value', placeholder: 'e.g. Error — leave empty for truthy check' },
  { key: 'error_reason_path', label: 'Error Reason Path', placeholder: 'e.g. response.errors.error / reason' },
];

export function PingPostResponseConfig({ envType, env }) {
  const { data, handleEnvironmentChange } = useIntegrations();
  const responseConfig = data.environments?.[envType]?.[env]?.response_config ?? {};

  const fields = envType === 'ping' ? PING_FIELDS : POST_FIELDS;
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
              <Label htmlFor={`${envType}-${env}-${field.key}`}>{field.label}</Label>
              <Input
                id={`${envType}-${env}-${field.key}`}
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
                <Label htmlFor={`${envType}-${env}-${field.key}`}>{field.label}</Label>
                <Input
                  id={`${envType}-${env}-${field.key}`}
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
