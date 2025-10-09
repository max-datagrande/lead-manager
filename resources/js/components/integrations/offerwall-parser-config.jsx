import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useIntegrations } from '@/hooks/use-integrations';

const MAPPING_FIELDS = [
  { key: 'title', label: 'Title' },
  { key: 'description', label: 'Description' },
  { key: 'logo_url', label: 'Logo URL' },
  { key: 'click_url', label: 'Click URL' },
  { key: 'impression_url', label: 'Impression URL' },
  { key: 'cpc', label: 'CPC' },
  { key: 'display_name', label: 'Display Name' },
];

export function OfferwallParserConfig() {
  const { data, setData } = useIntegrations();

  const handlePathChange = (e) => {
    setData('parser_config', { ...data.response_parser_config, offer_list_path: e.target.value });
  };

  const handleMappingChange = (key, value) => {
    setData('parser_config', { ...data.response_parser_config, mapping: { ...data.response_parser_config.mapping, [key]: value } });
  };

  return (
    <Card className="mt-6">
      <CardHeader>
        <CardTitle>Offerwall Parser Configuration</CardTitle>
        <CardDescription>Specify how to find and map the offers from the API response.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        <div className="space-y-2">
          <Label htmlFor="offer_list_path">Offer List Path</Label>
          <Input
            id="offer_list_path"
            value={data.response_parser_config.offer_list_path}
            onChange={handlePathChange}
            placeholder="e.g., response.offers.items"
          />
        </div>

        <div>
          <h4 className="mb-2 text-sm font-medium">Mapping</h4>
          <div className="grid grid-cols-1 gap-6 rounded-md md:grid-cols-2">
            {MAPPING_FIELDS.map((field) => (
              <div key={field.key} className="flex flex-col gap-0.5 lg:flex-row lg:items-center lg:gap-2">
                <Label htmlFor={`mapping-${field.key}`} className="w-full lg:w-1/3">
                  {field.label}
                </Label>
                <Input
                  id={`mapping-${field.key}`}
                  value={data.response_parser_config.mapping[field.key] ?? ''}
                  onChange={(e) => handleMappingChange(field.key, e.target.value)}
                  placeholder={`e.g., ${field.key}`}
                />
              </div>
            ))}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
