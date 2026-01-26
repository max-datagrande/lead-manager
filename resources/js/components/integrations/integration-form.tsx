import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useIntegrations } from '@/hooks/use-integrations';
import { EnvironmentTab } from './enviroments-tab';
import { MappingConfigurator } from './mapping-configurator';
import { OfferwallParserConfig } from './offerwall-parser-config';
import { TokenInserter } from './token-inserter';
import PayloadEditor from '@/components/forms/payload-editor';

export function IntegrationForm({ companies = [], fields = [] }) {
  const { isEdit, data, errors, processing, handleSubmit, setData } = useIntegrations();

  const handleTokenSelect = (tokenName: string) => {
    const newRequestMappingConfig = { ...data.request_mapping_config, [tokenName]: {} };
    setData('request_mapping_config', newRequestMappingConfig);
  };

  const handleMappingChange = (token: string, field: string, fieldValue: any) => {
    const newRequestMappingConfig = {
      ...data.request_mapping_config,
      [token]: {
        ...data.request_mapping_config[token],
        [field]: fieldValue,
      },
    };
    setData('request_mapping_config', newRequestMappingConfig);
  };

  const handleRemoveToken = (tokenName: string) => {
    const newRequestMappingConfig = { ...data.request_mapping_config };
    delete newRequestMappingConfig[tokenName];
    setData('request_mapping_config', newRequestMappingConfig);
  };

  return (
    <form onSubmit={handleSubmit}>
      {/* Form Header */}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        {/* Name */}
        <div className="flex-auto space-y-2">
          <Label htmlFor="name">Name</Label>
          <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g., Client A Offerwall" />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>
        {/* Type */}
        <div className="flex-auto space-y-2">
          <Label htmlFor="type">Integration Type</Label>
          <Select value={data.type} onValueChange={(value) => setData('type', value)}>
            <SelectTrigger id="type">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="post-only">Post Only</SelectItem>
              <SelectItem value="ping-post">Ping-Post</SelectItem>
              <SelectItem value="offerwall">Offerwall</SelectItem>
            </SelectContent>
          </Select>
        </div>
        {/* Company */}
        <div className="flex-auto space-y-2">
          <Label htmlFor="company_id">Company</Label>
          <Select value={data.company_id?.toString()} onValueChange={(value) => setData('company_id', parseInt(value, 10))}>
            <SelectTrigger id="company_id">
              <SelectValue placeholder="Select a company" />
            </SelectTrigger>
            <SelectContent>
              {companies.map((company) => (
                <SelectItem key={company.value} value={company.value.toString()}>
                  {company.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Active Switch */}
      <div className="flex items-center space-x-2 pt-4">
        <Label htmlFor="is_active">Active</Label>
        <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
      </div>

      {/* Environment Tabs */}
      <Tabs defaultValue="development" className="mt-6">
        <TabsList className="flex w-full gap-2">
          <TabsTrigger className="flex-auto" value="development">
            Development
          </TabsTrigger>
          <TabsTrigger className="flex-auto" value="production">
            Production
          </TabsTrigger>
        </TabsList>
        <TabsContent value="development">
          <Card>
            <CardHeader className="gap-0">
              <CardTitle className="text-lg">Development Environment</CardTitle>
              <CardDescription>Configuration for testing and development.</CardDescription>
            </CardHeader>
            <CardContent>
              <EnvironmentTab env="development" fields={fields} />
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="production">
          <Card>
            <CardHeader className="gap-0">
              <CardTitle className="text-lg">Production Environment</CardTitle>
              <CardDescription>Live, production-ready configuration.</CardDescription>
            </CardHeader>
            <CardContent>
              <EnvironmentTab env="production" fields={fields} />
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Custom Payload Transformer */}
      <Card className="mt-6">
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <div className="space-y-1">
            <CardTitle>Custom Payload Transformer</CardTitle>
            <CardDescription>Use a Twig template to transform the payload before sending it.</CardDescription>
          </div>
          <div className="flex items-center space-x-2">
            <Label htmlFor="use_custom_transformer">Enable</Label>
            <Switch
              id="use_custom_transformer"
              checked={data.use_custom_transformer}
              onCheckedChange={(checked: boolean) => setData('use_custom_transformer', checked)}
            />
          </div>
        </CardHeader>
        {data.use_custom_transformer && (
          <CardContent>
            <PayloadEditor code={data.payload_transformer} onChange={(value: string) => setData('payload_transformer', value)} />
          </CardContent>
        )}
      </Card>

      {/* Production Payload Mapping - ONLY IN EDIT MODE */}
      {isEdit && (
        <Card className="mt-6">
          <CardHeader>
            <CardTitle>Production Payload Mapping</CardTitle>
            <CardDescription>Insert dynamic fields and configure how they are parsed.</CardDescription>
            <TokenInserter fields={fields} onTokenSelect={handleTokenSelect} />
          </CardHeader>
          <CardContent>
            <MappingConfigurator
              parsers={data.request_mapping_config}
              onParserChange={handleMappingChange}
              fields={fields}
              onRemoveToken={handleRemoveToken}
            />
          </CardContent>
        </Card>
      )}

      {data.type === 'offerwall' && <OfferwallParserConfig />}

      <div className="mt-6 flex justify-end gap-2">
        <Button type="submit" disabled={processing}>
          {processing ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Integration'}
        </Button>
      </div>
    </form>
  );
}
