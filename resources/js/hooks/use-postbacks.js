import { PostbackContext } from '@/context/postback-provider';
import { useContext } from 'react';

function usePostbacks() {
  const context = useContext(PostbackContext);
  if (!context) {
    throw new Error('usePostbacks must be used within a PostbackProvider');
  }
  return context;
}

export { usePostbacks };
