import { KeyValueList } from '@/components/forms';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import JsonEditor from '@/components/forms/json-editor';
import { useIntegrations } from '@/hooks/use-integrations';
import { HEADER_KEYS, HEADER_VALUES } from '@/config/constants';

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
        <div className="min-w-36 flex-none space-y-2">
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
          keyDatalist={HEADER_KEYS}
          valueDatalist={HEADER_VALUES}
        />
      </div>
      <div className="space-y-2">
        <JsonEditor
          label="Request Body (JSON)"
          id={`${env}-body`}
          value={data.environments[env]?.request_body ?? ''}
          onChange={(value) => handleEnvironmentChange(env, 'request_body', value)}
          placeholder='{ "lead_id": "{lead_id}" }'
        />
      </div>
    </div>
  );
}
