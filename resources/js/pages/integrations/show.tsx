import { EnvironmentDetails } from '@/components/integrations'
import { showBreadcrumbs } from '@/components/integrations/breadcrumbs'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import AppLayout from '@/layouts/app-layout'
import { EnvironmentDB, IntegrationDB, MappingEntry } from '@/types/integrations'
import { Head, Link } from '@inertiajs/react'
import { Radio, Send } from 'lucide-react'

function relativeDate(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const days = Math.floor(diff / 86_400_000)
  if (days === 0) return 'today'
  if (days === 1) return 'yesterday'
  if (days < 30) return `${days} days ago`
  const months = Math.floor(days / 30)
  return months === 1 ? '1 month ago' : `${months} months ago`
}

interface Props {
  integration: IntegrationDB
}

function EmptyEnvironment() {
  return (
    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed px-6 py-10 text-center">
      <p className="text-sm text-muted-foreground">No configuration found for this environment.</p>
    </div>
  )
}

function PingPostEnvContent({
  environments,
  integrationId,
  mappingConfig,
}: {
  environments: EnvironmentDB[]
  integrationId: number
  mappingConfig: Record<string, MappingEntry>
}) {
  const pingEnv = environments.find((e) => e.env_type === 'ping') ?? null
  const postEnv = environments.find((e) => e.env_type === 'post') ?? null

  return (
    <Tabs defaultValue="ping" className="mt-3">
      <TabsList className="h-8 gap-1 rounded-md px-1">
        <TabsTrigger value="ping" className="h-6 gap-1.5 px-2.5 text-xs">
          <Radio className="size-3 shrink-0" />
          Ping
        </TabsTrigger>
        <TabsTrigger value="post" className="h-6 gap-1.5 px-2.5 text-xs">
          <Send className="size-3 shrink-0" />
          Post
        </TabsTrigger>
      </TabsList>
      <TabsContent value="ping" className="mt-3">
        {pingEnv ? <EnvironmentDetails integrationId={integrationId} env={pingEnv} mappingConfig={mappingConfig} /> : <EmptyEnvironment />}
      </TabsContent>
      <TabsContent value="post" className="mt-3">
        {postEnv ? <EnvironmentDetails integrationId={integrationId} env={postEnv} mappingConfig={mappingConfig} /> : <EmptyEnvironment />}
      </TabsContent>
    </Tabs>
  )
}

function PingPostTabs({ integration }: { integration: IntegrationDB }) {
  const devEnvs = integration.environments.filter((e) => e.environment === 'development')
  const prodEnvs = integration.environments.filter((e) => e.environment === 'production')
  const mappingConfig = integration.request_mapping_config ?? {}

  return (
    <Tabs defaultValue="development">
      <TabsList className="flex w-full gap-2">
        <TabsTrigger className="flex-auto" value="development">
          Development
        </TabsTrigger>
        <TabsTrigger className="flex-auto" value="production">
          Production
        </TabsTrigger>
      </TabsList>
      <TabsContent value="development">
        <PingPostEnvContent environments={devEnvs} integrationId={integration.id} mappingConfig={mappingConfig} />
      </TabsContent>
      <TabsContent value="production">
        <PingPostEnvContent environments={prodEnvs} integrationId={integration.id} mappingConfig={mappingConfig} />
      </TabsContent>
    </Tabs>
  )
}

function FlatTabs({ integration }: { integration: IntegrationDB }) {
  const devEnv = integration.environments.find((e) => e.environment === 'development') ?? null
  const prodEnv = integration.environments.find((e) => e.environment === 'production') ?? null
  const mappingConfig = integration.request_mapping_config ?? {}

  return (
    <Tabs defaultValue="development">
      <TabsList className="flex w-full gap-2">
        <TabsTrigger className="flex-auto" value="development">
          Development
        </TabsTrigger>
        <TabsTrigger className="flex-auto" value="production">
          Production
        </TabsTrigger>
      </TabsList>
      <TabsContent value="development" className="mt-3">
        {devEnv ? <EnvironmentDetails integrationId={integration.id} env={devEnv} mappingConfig={mappingConfig} /> : <EmptyEnvironment />}
      </TabsContent>
      <TabsContent value="production" className="mt-3">
        {prodEnv ? <EnvironmentDetails integrationId={integration.id} env={prodEnv} mappingConfig={mappingConfig} /> : <EmptyEnvironment />}
      </TabsContent>
    </Tabs>
  )
}

const TYPE_LABELS: Record<string, string> = {
  'ping-post': 'Ping-Post',
  'post-only': 'Post Only',
  offerwall: 'Offerwall',
}

const ShowIntegration = ({ integration }: Props) => {
  return (
    <>
      <Head title={`Integration | ${integration.name}`} />
      <div className="relative flex-1 space-y-6 p-6 md:p-8">
        <div className="flex items-start justify-between gap-4">
          <div className="space-y-1">
            <p className="text-sm text-muted-foreground">ID: {integration.id}</p>
            <h2 className="text-3xl font-bold tracking-tight">{integration.name}</h2>
            <div className="flex items-center gap-2 pt-0.5">
              <Badge variant="secondary" className="font-mono text-xs">
                {TYPE_LABELS[integration.type] ?? integration.type}
              </Badge>
              {integration.updated_at && (
                <span className="text-xs text-muted-foreground">
                  Updated {relativeDate(integration.updated_at)}
                </span>
              )}
            </div>
          </div>
          <Link href={route('integrations.edit', integration.id)}>
            <Button>Edit</Button>
          </Link>
        </div>

        <div>
          {integration.type === 'ping-post' ? (
            <PingPostTabs integration={integration} />
          ) : (
            <FlatTabs integration={integration} />
          )}
        </div>
      </div>
    </>
  )
}

ShowIntegration.layout = (page: React.ReactNode & { props: Props }) => {
  const { integration } = page.props
  const breadcrumbs = showBreadcrumbs(integration)
  return <AppLayout children={page} breadcrumbs={breadcrumbs} />
}

export default ShowIntegration
