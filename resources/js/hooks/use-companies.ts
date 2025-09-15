import { CompaniesContext } from '@/context/companies-provider';
import { useContext } from 'react';

function useCompanies() {
  const context = useContext(CompaniesContext);
  if (!context) {
    throw new Error('useCompanies must be used within a CompaniesProvider');
  }
  return context;
}

export { useCompanies };
