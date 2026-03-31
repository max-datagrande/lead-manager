import { KeyValueList } from '@/components/forms';
import JsonEditor from '@/components/forms/json-editor';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { HEADER_KEYS, HEADER_VALUES } from '@/config/constants';
import { useIntegrations } from '@/hooks/use-integrations';

/**
 * @param {import('@/types/integrations').EnvironmentTabProps} props
 */
export function EnvironmentTab({ env, envType = null, fields = [] }) {
  const { data, handleEnvironmentChange } = useIntegrations();
  const headerFields = fields.map((field) => `{${field.name}}`);
  const headerValues = [...HEADER_VALUES, ...headerFields];

  // Resolve the data slice for this env slot
  const envData = envType ? data.environments[envType]?.[env] : data.environments[env];
  const onChange = (field, value) => handleEnvironmentChange(env, field, value, envType);

  return (
    <div className="space-y-4">
      <div className="flex w-full gap-4">
        <div className="min-w-36 flex-none space-y-2">
          <Label htmlFor={`${envType ?? 'env'}-${env}-method`}>Method</Label>
          <Select value={envData?.method ?? 'POST'} onValueChange={(value) => onChange('method', value)}>
            <SelectTrigger id={`${envType ?? 'env'}-${env}-method`}>
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
          <Label htmlFor={`${envType ?? 'env'}-${env}-url`}>URL</Label>
          <Input
            id={`${envType ?? 'env'}-${env}-url`}
            value={envData?.url ?? ''}
            onChange={(e) => onChange('url', e.target.value)}
            placeholder="https://api.example.com/endpoint"
          />
        </div>
      </div>

      <div className="space-y-2">
        <KeyValueList
          key={`${envType ?? 'env'}-${env}`}
          label="Request Headers"
          initialValues={envData?.request_headers ?? []}
          onChange={(values) => onChange('request_headers', values)}
          fields={fields}
          keyPlaceholder="Header Name"
          valuePlaceholder="Header Value"
          addButtonText="Add Header"
          textOnTooltip={true}
          keyDatalist={HEADER_KEYS}
          valueDatalist={headerValues}
        />
      </div>
      <div className="space-y-2">
        <JsonEditor
          label="Request Body (JSON)"
          id={`${envType ?? 'env'}-${env}-body`}
          value={envData?.request_body ?? ''}
          onChange={(value) => onChange('request_body', value)}
          placeholder='{ "lead_id": "{lead_id}" }'
        />
      </div>
    </div>
  );
}
