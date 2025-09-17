import { KeyValueList } from '@/components/forms';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useIntegrations } from '@/hooks/use-integrations';

interface Props {
  env: 'development' | 'production';
}

interface KeyValue {
  key: string;
  value: string;
}
export function EnvironmentTab({ env }: Props) {
  const { data, handleEnvironmentChange } = useIntegrations();
  return (
    <div className="space-y-4">
      <div className="flex w-full gap-4">
        <div className="flex-none space-y-2 min-w-36">
          <Label htmlFor={`${env}-method`}>Method</Label>
          <Select value={data.environments[env]?.method ?? 'POST'} onValueChange={(value) => handleEnvironmentChange(env, 'method', value)}>
            <SelectTrigger id={`${env}-method`}>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="GET">GET</SelectItem>
              <SelectItem value="POST">POST</SelectItem>
              <SelectItem value="PUT">PUT</SelectItem>
              <SelectItem value="PATCH">PATCH</SelectItem>
              <SelectItem value="DELETE">DELETE</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="flex-auto space-y-2">
          <Label htmlFor={`${env}-url`}>URL</Label>
          <Input
            id={`${env}-url`}
            value={data.environments[env]?.url ?? ''}
            onChange={(e) => handleEnvironmentChange(env, 'url', e.target.value)}
            placeholder="https://api.example.com/endpoint"
          />
        </div>
      </div>

      <div className="space-y-2">
        <KeyValueList
          label="Request Headers"
          initialValues={data.environments[env]?.request_headers ?? []}
          onChange={(values: KeyValue[]) => handleEnvironmentChange(env, 'request_headers', values)}
          keyPlaceholder="Header Name"
          valuePlaceholder="Header Value"
          addButtonText="Add Header"
          textOnTooltip={true}
        />
      </div>
      <div className="space-y-2">
        <Label htmlFor={`${env}-body`}>Request Body (JSON)</Label>
        <Textarea
          id={`${env}-body`}
          value={data.environments[env]?.request_body ?? ''}
          onChange={(e) => handleEnvironmentChange(env, 'request_body', e.target.value)}
          placeholder='{ "lead_id": "{lead_id}" }'
          rows={6}
        />
      </div>
    </div>
  );
}
