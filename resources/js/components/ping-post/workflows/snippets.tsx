import { CodeBlock } from '@/components/docs/code-block';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Workflow } from '@/types/ping-post';
import { Braces, Terminal } from 'lucide-react';
import { useState } from 'react';

interface WorkflowSnippetsProps {
  workflow: Pick<Workflow, 'id' | 'name'>;
  apiBaseUrl?: string;
}

function buildCurl(workflowId: number, apiBaseUrl: string): string {
  return `curl -X POST "${apiBaseUrl}/v1/share-leads/dispatch/${workflowId}" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{
    "fingerprint": "<VISITOR_FINGERPRINT>",
    "fields": { "email": "user@example.com", "phone": "+10000000000" },
    "create_on_miss": true
  }'`;
}

function buildSdk(workflowId: number): string {
  return `window.Catalyst.on('ready', async () => {
  try {
    const res = await window.Catalyst.shareLead({
      workflowId: ${workflowId},
      fields: { email: 'user@example.com', phone: '+10000000000' },
      createOnMiss: true,
    });
    console.log('Dispatch OK', res.data);
  } catch (err) {
    console.error('Dispatch failed', err);
  }
});`;
}

export function WorkflowSnippets({ workflow, apiBaseUrl = '{{CATALYST_API_URL}}' }: WorkflowSnippetsProps) {
  const [tab, setTab] = useState<'curl' | 'sdk'>('curl');
  const curl = buildCurl(workflow.id, apiBaseUrl);
  const sdk = buildSdk(workflow.id);

  return (
    <Tabs value={tab} onValueChange={(v) => setTab(v as 'curl' | 'sdk')}>
      <TabsList className="h-8 gap-1 rounded-md px-1">
        <TabsTrigger value="curl" className="h-6 gap-1.5 px-2.5 text-xs">
          <Terminal className="size-3 shrink-0" />
          cURL
        </TabsTrigger>
        <TabsTrigger value="sdk" className="h-6 gap-1.5 px-2.5 text-xs">
          <Braces className="size-3 shrink-0" />
          JS SDK
        </TabsTrigger>
      </TabsList>

      <TabsContent value="curl" className="mt-3 space-y-2">
        <p className="text-sm text-muted-foreground">
          Paste into Postman or your terminal. Replace <code className="rounded bg-muted px-1 py-0.5 text-xs">&lt;VISITOR_FINGERPRINT&gt;</code> with
          the fingerprint returned by the Catalyst SDK on the landing side.
        </p>
        <CodeBlock code={curl} language="bash" />
      </TabsContent>

      <TabsContent value="sdk" className="mt-3 space-y-2">
        <p className="text-sm text-muted-foreground">
          Drop into any landing that already loads the Catalyst loader. The SDK injects the visitor fingerprint automatically.
        </p>
        <CodeBlock code={sdk} language="javascript" />
      </TabsContent>
    </Tabs>
  );
}
