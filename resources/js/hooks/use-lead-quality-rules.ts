import { LeadQualityRulesContext } from '@/context/lead-quality-rules-provider';
import { useContext } from 'react';

export function useLeadQualityRules() {
  const context = useContext(LeadQualityRulesContext);
  if (!context) throw new Error('useLeadQualityRules must be used within LeadQualityRulesProvider');
  return context;
}
