import { Button } from '@/components/ui/button';
import { Check, Copy } from 'lucide-react';
import React, { useState } from 'react';

/**
 * Componente wrapper que proporciona funcionalidad de copiar al portapapeles
 *
 * @param {Object} props - Propiedades del componente
 * @param {React.ReactNode} props.children - Contenido a envolver
 * @param {string} props.textToCopy - Texto que se copiará al portapapeles
 * @param {string} [props.className] - Clases CSS adicionales para el contenedor
 * @returns {JSX.Element} Contenido envuelto con botón de copia
 */
const CopyToClipboard = ({ children, textToCopy, className = '' }) => {
  const [isCopied, setIsCopied] = useState(false);

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(textToCopy);
      setIsCopied(true);
      setTimeout(() => {
        setIsCopied(false);
      }, 4000);
    } catch (error) {
      console.error('Error when copying to clipboard:', error);
      // Fallback para navegadores que no soportan clipboard API
      const textArea = document.createElement('textarea');
      textArea.value = textToCopy;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
      setIsCopied(true);
      setTimeout(() => {
        setIsCopied(false);
      }, 4000);
    }
  };

  return (
    <div className={`inline-flex items-center gap-2 ${className}`}>
      {children}
      <Button variant="ghost" size="icon" onClick={handleCopy} title={isCopied ? 'Copied!' : 'Copy to clipboard'}>
        {isCopied ? <Check className="slide-in-up h-3 w-3" /> : <Copy className="h-3 w-3 fade-in" />}
      </Button>
    </div>
  );
};

export default CopyToClipboard;
