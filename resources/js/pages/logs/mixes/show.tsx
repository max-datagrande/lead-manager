import { DescriptionList, DescriptionListItem } from '@/components/integrations/description-list-item';
import PageHeader from '@/components/page-header';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import JsonViewer from '@/components/ui/json-viewer';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Logs',
    href: '/',
  },
  {
    title: 'Offerwall Mixes',
    href: route('logs.offerwall-mixes.index'),
  },
  {
    title: 'Details',
    href: window.location.href,
  },
];

// Define interfaces for props
interface Integration {
  id: number;
  name: string;
}

interface IntegrationCallLog {
  id: number;
  integration: Integration;
  status: string;
  http_status_code: number;
  duration_ms: number;
  request_url: string;
  request_method: string;
  request_headers: object;
  request_payload: object;
  response_headers: object;
  response_body: object | string;
}

interface OfferwallMixLog {
  id: number;
  offerwall_mix: { name: string };
  origin: string;
  successful_integrations: number;
  failed_integrations: number;
  total_integrations: number;
  total_offers_aggregated: number;
  total_duration_ms: number;
  created_at: string;
  integration_call_logs: IntegrationCallLog[];
}

interface ShowProps {
  log: OfferwallMixLog;
}

const Show = ({ log }: ShowProps) => {
  return (
    <>
      <Head title={`Log #${log.id} - ${log.offerwall_mix.name}`} />
      <div className="flex-1 space-y-6 p-6 md:p-8">
        <PageHeader
          smallText={`Offerwall ${log.offerwall_mix.name}`}
          title={`Log #${log.id}`}
        />
        <Card>
          <CardHeader>
            <CardTitle>Execution Summary</CardTitle>
          </CardHeader>
          <CardContent>
            <DescriptionList>
              <DescriptionListItem term="Mix Name">{log.offerwall_mix.name}</DescriptionListItem>
              <DescriptionListItem term="Origin">{log.origin}</DescriptionListItem>
              <DescriptionListItem term="Timestamp">{new Date(log.created_at).toLocaleString()}</DescriptionListItem>
              <DescriptionListItem term="Total Duration">{`${log.total_duration_ms}ms`}</DescriptionListItem>
              <DescriptionListItem term="Integrations">{`${log.successful_integrations} / ${log.total_integrations} successful`}</DescriptionListItem>
              <DescriptionListItem term="Offers Aggregated">{log.total_offers_aggregated}</DescriptionListItem>
            </DescriptionList>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Integration Calls</CardTitle>
            <CardDescription>Individual results for each integration called in this mix.</CardDescription>
          </CardHeader>
          <CardContent>
            <Accordion type="single" collapsible className="w-full">
              {log.integration_call_logs.map((call) => (
                <AccordionItem value={`item-${call.id}`} key={call.id}>
                  <AccordionTrigger>
                    <div className="flex w-full items-center gap-4 pr-4">
                      <Avatar>
                        <AvatarFallback className={call.status === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}>
                          {call.status === 'success' ? <Check className="h-4 w-4" /> : <X className="h-4 w-4" />}
                        </AvatarFallback>
                      </Avatar>
                      <span className="font-semibold">{call.integration.name}</span>
                      <div className="ml-auto flex items-center gap-2">
                        <Badge variant="outline">{call.http_status_code}</Badge>
                        <Badge variant="secondary">{`${call.duration_ms}ms`}</Badge>
                      </div>
                    </div>
                  </AccordionTrigger>
                  <AccordionContent>
                    <Tabs defaultValue="response">
                      <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="request">Request</TabsTrigger>
                        <TabsTrigger value="response">Response</TabsTrigger>
                      </TabsList>
                      <TabsContent value="request" className="mt-4">
                        <div className="space-y-4">
                          <div>
                            <h4 className="font-semibold">Endpoint</h4>
                            <p className="text-sm text-muted-foreground">
                              {call.request_method} {call.request_url}
                            </p>
                          </div>
                          <div>
                            <h4 className="font-semibold">Headers</h4>
                            {Object.keys(call.request_headers).length > 0 ? (
                              <DescriptionList>
                                {Object.entries(call.request_headers).map(([key, value]) => (
                                  <DescriptionListItem term={key} key={key}>
                                    {Array.isArray(value) ? value.join(', ') : String(value)}
                                  </DescriptionListItem>
                                ))}
                              </DescriptionList>
                            ) : (
                              <p className="text-sm text-muted-foreground">No headers sent.</p>
                            )}
                          </div>
                          <div>
                            <h4 className="font-semibold">Payload</h4>
                            <JsonViewer data={call.request_payload} />
                          </div>{' '}
                        </div>
                      </TabsContent>
                      <TabsContent value="response" className="mt-4">
                        <div className="space-y-4">
                          <div>
                            <h4 className="font-semibold">Headers</h4>
                            {Object.keys(call.response_headers).length > 0 ? (
                              <DescriptionList>
                                {Object.entries(call.response_headers).map(([key, value]) => (
                                  <DescriptionListItem term={key} key={key}>
                                    {Array.isArray(value) ? value.join(', ') : String(value)}
                                  </DescriptionListItem>
                                ))}
                              </DescriptionList>
                            ) : (
                              <p className="text-sm text-muted-foreground">No headers received.</p>
                            )}
                          </div>
                          <div>
                            <h4 className="font-semibold">Body</h4>
                            <JsonViewer data={call.response_body} />
                          </div>{' '}
                        </div>
                      </TabsContent>
                    </Tabs>
                  </AccordionContent>
                </AccordionItem>
              ))}
            </Accordion>
          </CardContent>
        </Card>
      </div>
    </>
  );
};

Show.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Show;
