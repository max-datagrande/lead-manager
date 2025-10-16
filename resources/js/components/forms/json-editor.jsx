import { useCallback } from 'react';
import CodeMirror from '@uiw/react-codemirror';
import { json } from '@codemirror/lang-json';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Label } from '@/components/ui/label';
import { useToast } from '@/hooks/use-toast';
import { getCurrentTheme } from '@/hooks/use-appearance';

const theme = getCurrentTheme();

const JsonEditor = ({ label, className = "", value, onChange, placeholder, ...props }) => {
  const { addMessage } = useToast();
  const handleEditorChange = useCallback((val) => {
    onChange(val);
  }, [onChange]);
  const formatJson = () => {
    try {
      // Asegurar que el valor sea un string
      const stringValue = typeof value === 'string' ? value : JSON.stringify(value, null, 2);

      // Parsear y formatear manualmente ya que Prettier standalone no incluye el parser JSON
      const parsed = JSON.parse(stringValue);
      const formattedJson = JSON.stringify(parsed, null, 2);
      onChange(formattedJson);
    } catch (error) {
      console.error("Error formatting JSON:", error);
      addMessage('Error formatting JSON', 'error');
    }
  };

  return (
    <div className={cn('space-y-2', className)}>
      <div className="mb-2 flex justify-between gap-4">
        {label && (
          <Label className="flex items-center gap-2" htmlFor="json-editor">
            {label}
          </Label>
        )}
        <Button type="button" variant="outline" size="sm" onClick={formatJson}>
          Format JSON
        </Button>
      </div>
      <CodeMirror
        {...props}
        id="json-editor"
        value={typeof value === 'string' ? value : JSON.stringify(value, null, 2)}
        height="200px" // Ajusta la altura según sea necesario
        extensions={[json()]}
        onChange={handleEditorChange}
        theme={theme} // O "dark", según tu preferencia
        placeholder={placeholder}
        // Las opciones de CodeMirror se pasan directamente al componente
        // No hay una prop 'options' en @uiw/react-codemirror, se configuran con props
        basicSetup={{
          lineNumbers: true,
          indentOnInput: true,
        }}
        indentWithTab={false}
      />
    </div>
  );
};

export default JsonEditor;
