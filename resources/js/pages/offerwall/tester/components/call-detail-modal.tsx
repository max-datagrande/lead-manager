import { DescriptionList, DescriptionListItem } from '@/components/integrations/description-list-item';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import JsonViewer from '@/components/ui/json-viewer';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { CallLogData } from '../constants';

interface CallDetailModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  callLog: CallLogData;
  cptypeLabel: string;
}

export default function CallDetailModal({ open, onOpenChange, callLog, cptypeLabel }: CallDetailModalProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[85vh] w-11/12 flex-col overflow-hidden overflow-y-auto sm:max-w-[1200px]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-3">
            Call Details — {cptypeLabel}
            <Badge variant={callLog.status === 'success' ? 'default' : 'destructive'}>{callLog.status}</Badge>
            <Badge variant="outline">{callLog.http_status_code}</Badge>
            <Badge variant="secondary">{callLog.duration_ms}ms</Badge>
          </DialogTitle>
        </DialogHeader>

        <Tabs defaultValue="request">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="request">Request</TabsTrigger>
            <TabsTrigger value="response">Response</TabsTrigger>
            <TabsTrigger value="mapping">Field Mapping</TabsTrigger>
          </TabsList>

          <TabsContent value="request" className="mt-4 w-full max-w-full space-y-4">
            <div>
              <h4 className="font-semibold">Endpoint</h4>
              <p className="text-sm text-muted-foreground">
                {callLog.request_method} {callLog.request_url}
              </p>
            </div>
            <div>
              <h4 className="font-semibold">Headers</h4>
              {Object.keys(callLog.request_headers).length > 0 ? (
                <DescriptionList>
                  {Object.entries(callLog.request_headers).map(([key, value]) => (
                    <DescriptionListItem term={key} key={key}>
                      {Array.isArray(value) ? value.join(', ') : String(value)}
                    </DescriptionListItem>
                  ))}
                </DescriptionList>
              ) : (
                <p className="text-sm text-muted-foreground">No headers sent.</p>
              )}
            </div>
            <div className="w-full">
              <h4 className="font-semibold">Payload</h4>
              <div className="mt-2 overflow-x-auto rounded-md bg-muted">
                <JsonViewer data={callLog.request_payload} />
              </div>
            </div>
          </TabsContent>

          <TabsContent value="response" className="mt-4 w-full max-w-full space-y-4 overflow-hidden">
            <div>
              <h4 className="mb-4 font-semibold">Headers</h4>
              {Object.keys(callLog.response_headers).length > 0 ? (
                <DescriptionList>
                  {Object.entries(callLog.response_headers).map(([key, value]) => (
                    <DescriptionListItem term={key} key={key}>
                      {Array.isArray(value) ? value.join(', ') : String(value)}
                    </DescriptionListItem>
                  ))}
                </DescriptionList>
              ) : (
                <p className="text-sm text-muted-foreground">No headers received.</p>
              )}
            </div>
            <div className="w-full max-w-full overflow-x-hidden">
              <h4 className="font-semibold">Body</h4>
              <div className="mt-2 w-full overflow-x-auto rounded-md bg-muted">
                <JsonViewer data={callLog.response_body} />
              </div>
            </div>
          </TabsContent>

          <TabsContent value="mapping" className="mt-4 w-full max-w-full space-y-4">
            <div className="w-full">
              <h4 className="font-semibold">Original Field Values</h4>
              <div className="mt-2 overflow-x-auto rounded-md bg-muted">
                <JsonViewer data={callLog.original_field_values} />
              </div>
            </div>
            <div className="w-full">
              <h4 className="font-semibold">Mapped Field Values</h4>
              <div className="mt-2 overflow-x-auto rounded-md bg-muted">
                <JsonViewer data={callLog.mapped_field_values} />
              </div>
            </div>
          </TabsContent>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
}
