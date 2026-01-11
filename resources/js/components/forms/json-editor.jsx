import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { getCurrentTheme } from '@/hooks/use-appearance';
import { useToast } from '@/hooks/use-toast';
import { cn } from '@/lib/utils';
import { json, jsonParseLinter } from '@codemirror/lang-json';
import { linter, lintGutter } from '@codemirror/lint';
import CodeMirror from '@uiw/react-codemirror';
import { parse } from 'jsonc-parser';
import { useCallback } from 'react';

const JsonEditor = ({ label, className = '', value, onChange, placeholder, ...props }) => {
  const theme = getCurrentTheme();

  const { addMessage } = useToast();
  const handleEditorChange = useCallback(
    (val) => {
      onChange(val);
    },
    [onChange],
  );
  const formatJson = () => {
    try {
      // Asegurar que el valor sea un string
      const stringValue = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
      const parsed = parse(stringValue);
      const formatted = JSON.stringify(parsed, null, 2);
      onChange(formatted);
    } catch (error) {
      console.error('Error formatting JSON:', error);
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
        height="450px" // Ajusta la altura según sea necesario
        extensions={[json(), linter(jsonParseLinter()), lintGutter()]}
        onChange={handleEditorChange}
        theme={theme === 'dark' ? 'dark' : 'light'} // O "dark", según tu preferencia
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
