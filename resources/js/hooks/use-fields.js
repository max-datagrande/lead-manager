import { FieldsContext } from '@/context/fields-provider';
import { useContext } from 'react';

function useFields() {
  const context = useContext(FieldsContext);
  if (!context) {
    throw new Error('useFields must be used within a WhitelistProvider');
  }
  return context;
}

export { useFields };
