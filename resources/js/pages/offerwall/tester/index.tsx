import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Skeleton } from '@/components/ui/skeleton';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Loader2, Play } from 'lucide-react';
import { useMemo, useState } from 'react';
import { route } from 'ziggy-js';
import TestForm from './components/test-form';
import TestResults from './components/test-results';
import type { FieldConfig, IntegrationOption, SectionResult, TestResults as TestResultsType } from './constants';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Offerwall', href: route('offerwall.index') },
  { title: 'Tester', href: route('offerwall.tester.index') },
];

interface IndexProps {
  integrations: IntegrationOption[];
  clientIp: string;
  deviceType: string;
}

const Index = ({ integrations, clientIp, deviceType }: IndexProps) => {
  // Selection
  const [selectedIntegrationId, setSelectedIntegrationId] = useState<string>('');
  const { addMessage: showToast } = useToast();

  // Dynamic fields
  const [fields, setFields] = useState<FieldConfig[]>([]);
  const [cptypeField, setCptypeField] = useState<FieldConfig | null>(null);
  const [stateField, setStateField] = useState<FieldConfig | null>(null);
  const [loadingFields, setLoadingFields] = useState(false);

  // Form values
  const [fieldValues, setFieldValues] = useState<Record<string, string>>({});
  const [selectedCptypes, setSelectedCptypes] = useState<string[]>([]);

  // Execution
  const [submitting, setSubmitting] = useState(false);
  const [results, setResults] = useState<TestResultsType | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Auto-prefill map for known fields
  const autoPrefills: Record<string, string> = useMemo(
    () => ({
      client_ip: clientIp,
      device_type: deviceType,
    }),
    [clientIp, deviceType],
  );

  const handleIntegrationChange = async (value: string) => {
    setSelectedIntegrationId(value);
    setResults(null);
    setError(null);
    setFieldValues({});
    setSelectedCptypes([]);

    if (!value) {
      setFields([]);
      setCptypeField(null);
      setStateField(null);
      return;
    }

    setLoadingFields(true);
    try {
      const response = await axios.get(route('offerwall.tester.fields', { integration: value }));
      const data = response.data;
      setFields(data.fields ?? []);
      setCptypeField(data.cptypeField ?? null);
      setStateField(data.stateField ?? null);

      // Set default values + auto-prefills
      const defaults: Record<string, string> = {};
      for (const field of data.fields ?? []) {
        if (field.default_value) {
          defaults[field.token] = field.default_value;
        }
        // Auto-prefill known fields if no default
        if (!field.default_value && autoPrefills[field.token]) {
          defaults[field.token] = autoPrefills[field.token];
        }
      }
      setFieldValues(defaults);

      // Auto-select all cptypes if available
      if (data.cptypeField?.possible_values?.length) {
        setSelectedCptypes([...data.cptypeField.possible_values]);
      }
    } catch {
      setFields([]);
      setCptypeField(null);
      setStateField(null);
      setError('Failed to load integration fields');
    } finally {
      setLoadingFields(false);
    }
  };

  // Check if all required fields have values
  const canSubmit = useMemo(() => {
    if (!selectedIntegrationId || loadingFields || submitting) return false;
    // All regular fields must have a value
    for (const field of fields) {
      if (!fieldValues[field.token]?.trim()) return false;
    }
    // State must be selected if field exists
    if (stateField && !fieldValues['state']?.trim()) return false;
    // At least one cptype if field exists
    if (cptypeField && cptypeField.possible_values.length > 0 && selectedCptypes.length === 0) return false;
    return true;
  }, [selectedIntegrationId, loadingFields, submitting, fields, fieldValues, stateField, cptypeField, selectedCptypes]);

  const handleSubmit = async () => {
    setSubmitting(true);
    setResults(null);
    setError(null);

    showToast('Test preparation started', 'info');
    try {
      // Step 1: Prepare test context
      const prepareResponse = await axios.post(route('offerwall.tester.prepare'), {
        integration_id: Number(selectedIntegrationId),
        field_values: fieldValues,
      });

      if (!prepareResponse.data.success) {
        showToast(prepareResponse.data.message || 'Preparation failed', 'error');
        setError(prepareResponse.data.message || 'Preparation failed');
        setSubmitting(false);
        return;
      }

      const context = prepareResponse.data.data;
      const iterations: (string | null)[] = selectedCptypes.length > 0 ? selectedCptypes : [null];

      // Progressive state
      const progressiveResults: Record<string, SectionResult> = {};
      let totalOffers = 0;
      let successfulCalls = 0;
      let failedCalls = 0;
      const startTime = Date.now();

      // Step 2: Execute each cptype sequentially
      for (let i = 0; i < iterations.length; i++) {
        const cptype = iterations[i];
        const label = cptype ?? 'default';
        showToast(`Testing ${cptype ? `cptype: ${cptype}` : 'integration'} (${i + 1}/${iterations.length})...`, 'info');

        try {
          const response = await axios.post(route('offerwall.tester.execute'), {
            integration_id: context.integration_id,
            mix_log_id: context.mix_log_id,
            lead_id: context.lead_id,
            cptype: cptype,
          });

          if (response.data.success && response.data.data) {
            const section = response.data.data.section;
            progressiveResults[label] = section;
            totalOffers += section.offers.length;

            if (section.error) {
              failedCalls++;
            } else {
              successfulCalls++;
            }
          } else {
            progressiveResults[label] = {
              offers: [],
              callLog: null,
              error: response.data.message || 'Call failed',
            };
            failedCalls++;
          }
        } catch (err: unknown) {
          const axiosError = err as { response?: { data?: { message?: string } } };
          progressiveResults[label] = {
            offers: [],
            callLog: null,
            error: axiosError?.response?.data?.message || 'Request failed',
          };
          failedCalls++;
        }

        // Update results progressively
        setResults({
          results_by_cptype: { ...progressiveResults },
          meta: {
            mix_log_id: context.mix_log_id,
            total_offers: totalOffers,
            successful_calls: successfulCalls,
            failed_calls: failedCalls,
            duration_ms: Date.now() - startTime,
          },
        });
      }
      showToast(`Test complete! ${totalOffers} offers found`, 'success');
    } catch (err: unknown) {
      const axiosError = err as { response?: { data?: { message?: string } } };
      const message = axiosError?.response?.data?.message || 'An unexpected error occurred';
      showToast(message, 'error');
      setError(message);
    } finally {
      setSubmitting(false);
    }
  };

  const integrationOptions = useMemo(
    () =>
      integrations.map((i) => ({
        value: String(i.id),
        label: i.company?.name ? `${i.name} — ${i.company.name}` : i.name,
      })),
    [integrations],
  );

  const selectedIntegration = integrations.find((i) => i.id === Number(selectedIntegrationId));
  const hasFields = fields.length > 0 || cptypeField || stateField;

  return (
    <>
      <Head title="Offerwall Tester" />
      <div className="flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Offerwall Tester" smallText="Test individual offerwall integrations" />

        {/* Integration Selector + Run Test Button */}
        <Card className="gap-4">
          <CardHeader className="flex">
            <CardTitle>Select Integration</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="w-full sm:max-w-md">
                <SearchableSelect
                  options={integrationOptions}
                  value={selectedIntegrationId}
                  onValueChange={handleIntegrationChange}
                  placeholder="Choose an offerwall integration..."
                  searchPlaceholder="Search integrations..."
                />
              </div>

              {selectedIntegrationId && !loadingFields && hasFields && (
                <Button onClick={handleSubmit} disabled={!canSubmit} className="gap-2 sm:w-auto">
                  {submitting ? (
                    <>
                      <Loader2 className="h-4 w-4 animate-spin" />
                      Running...
                    </>
                  ) : (
                    <>
                      <Play className="h-4 w-4" />
                      Run Test
                    </>
                  )}
                </Button>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Loading skeleton for fields */}
        {loadingFields && (
          <Card>
            <CardHeader>
              <Skeleton className="h-6 w-40" />
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
                <Skeleton className="h-16 w-full" />
                <Skeleton className="h-16 w-full" />
                <Skeleton className="h-16 w-full" />
                <Skeleton className="h-16 w-full" />
              </div>
            </CardContent>
          </Card>
        )}

        {/* Test Form */}
        {!loadingFields && hasFields && (
          <TestForm
            fields={fields}
            cptypeField={cptypeField}
            stateField={stateField}
            fieldValues={fieldValues}
            setFieldValues={setFieldValues}
            selectedCptypes={selectedCptypes}
            setSelectedCptypes={setSelectedCptypes}
          />
        )}

        {/* No fields warning */}
        {!loadingFields && selectedIntegrationId && !hasFields && !error && (
          <Card>
            <CardContent className="py-8 text-center text-muted-foreground">
              No mapping config found for {selectedIntegration?.name ?? 'this integration'}.
            </CardContent>
          </Card>
        )}

        {/* Loading skeleton — only before any results arrive */}
        {submitting && !results && (
          <Card>
            <CardHeader>
              <Skeleton className="h-6 w-32" />
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex gap-4">
                <Skeleton className="h-5 w-24" />
                <Skeleton className="h-5 w-24" />
                <Skeleton className="h-5 w-24" />
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {Array.from({ length: 8 }).map((_, i) => (
                  <Skeleton key={i} className="h-32 w-full rounded-lg" />
                ))}
              </div>
            </CardContent>
          </Card>
        )}

        {/* Error display */}
        {error && !submitting && (
          <Card className="border-destructive">
            <CardContent className="py-6 text-center text-destructive">{error}</CardContent>
          </Card>
        )}

        {/* Results — show progressively even while submitting */}
        {results && <TestResults results={results} />}
      </div>
    </>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Index;
