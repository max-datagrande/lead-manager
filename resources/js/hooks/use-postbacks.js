import { useContext } from 'react';
import { PostbackContext } from '@/context/postback-provider';

function usePostbacks() {
  const context = useContext(PostbackContext);
  if (!context) {
    throw new Error('usePostbacks must be used within a PostbackProvider');
  }
  return context;
}

export { usePostbacks };
