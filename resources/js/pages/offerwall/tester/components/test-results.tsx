import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertCircle, Check, Clock, Eye, Layers, X } from 'lucide-react';
import { useState } from 'react';
import type { CallLogData, TestResults as TestResultsType } from '../constants';
import CallDetailModal from './call-detail-modal';
import OfferCard from './offer-card';

interface TestResultsProps {
  results: TestResultsType;
}

export default function TestResults({ results }: TestResultsProps) {
  const [detailModal, setDetailModal] = useState<{ open: boolean; callLog: CallLogData | null; label: string }>({
    open: false,
    callLog: null,
    label: '',
  });

  const openDetail = (callLog: CallLogData, label: string) => {
    setDetailModal({ open: true, callLog, label });
  };

  const sections = Object.entries(results.results_by_cptype);

  return (
    <div className="space-y-6">
      {/* Summary Card */}
      <Card className="gap-2 duration-300 animate-in fade-in slide-in-from-bottom-4 dark:bg-muted">
        <CardHeader>
          <CardTitle>Results Summary</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-wrap gap-4">
            <div className="flex items-center gap-2">
              <Layers className="h-4 w-4 text-muted-foreground" />
              <span className="text-sm font-medium">{results.meta.total_offers} offers</span>
            </div>
            <div className="flex items-center gap-2">
              <Clock className="h-4 w-4 text-muted-foreground" />
              <span className="text-sm font-medium">{results.meta.duration_ms}ms</span>
            </div>
            <div className="flex items-center gap-2">
              <Check className="h-4 w-4 text-green-600" />
              <span className="text-sm font-medium">{results.meta.successful_calls} successful</span>
            </div>
            {results.meta.failed_calls > 0 && (
              <div className="flex items-center gap-2">
                <X className="h-4 w-4 text-red-600" />
                <span className="text-sm font-medium">{results.meta.failed_calls} failed</span>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Sections by cptype — each slides in as it arrives */}
      {sections.map(([cptypeLabel, section]) => (
        <Card key={cptypeLabel} className="gap-3 bg-muted duration-400 animate-in fade-in slide-in-from-bottom-4">
          <CardHeader className="gap-0">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <CardTitle className="text-lg">{cptypeLabel === 'default' ? 'Results' : `cptype: ${cptypeLabel}`}</CardTitle>
                <Badge variant={section.error ? 'destructive' : 'default'}>{section.offers.length} offers</Badge>
                {section.callLog && <Badge variant="secondary">{section.callLog.duration_ms}ms</Badge>}
              </div>
              {section.callLog && (
                <Button variant="black" size="sm" onClick={() => openDetail(section.callLog!, cptypeLabel)}>
                  <Eye className="h-4 w-4" />
                  View Details
                </Button>
              )}
            </div>
          </CardHeader>
          <CardContent>
            {section.error && (
              <Alert variant="destructive" className="mb-4">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>{section.error}</AlertDescription>
              </Alert>
            )}

            {section.offers.length > 0 ? (
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {section.offers.map((offer, index) => (
                  <OfferCard key={`${cptypeLabel}-${index}`} offer={offer} index={index} />
                ))}
              </div>
            ) : (
              !section.error && <p className="text-sm text-muted-foreground">No offers returned for this cptype.</p>
            )}
          </CardContent>
        </Card>
      ))}

      {/* Detail Modal */}
      {detailModal.callLog && (
        <CallDetailModal
          open={detailModal.open}
          onOpenChange={(open) => setDetailModal((prev) => ({ ...prev, open }))}
          callLog={detailModal.callLog}
          cptypeLabel={detailModal.label}
        />
      )}
    </div>
  );
}
