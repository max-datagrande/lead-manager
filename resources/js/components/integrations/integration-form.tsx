import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useIntegrations } from '@/hooks/use-integrations';
import { EnvironmentTab } from './enviroments-tab';


export function IntegrationForm() {
  const { data, errors, processing, handleSubmit, setData } = useIntegrations();
  return (
    <form onSubmit={handleSubmit}>
      <div className="flex w-full gap-4">
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
      </div>

      <div className="flex items-center space-x-2 pt-2">
        <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
        <Label htmlFor="is_active">Active</Label>
      </div>

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
              <EnvironmentTab env="development" />
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
              <EnvironmentTab env="production" />
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <div className="mt-6 flex justify-end gap-2">
        <Button type="submit" disabled={processing}>
          {processing ? 'Saving...' : 'Create Integration'}
        </Button>
      </div>
    </form>
  );
}
