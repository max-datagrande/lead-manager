import Editor from '@monaco-editor/react';

const PayloadEditor = ({ code, onChange }) => {
  const handleEditorChange = (value) => {
    onChange(value);
  };

  return (
    <div className="overflow-hidden rounded-md border">
      <div className="border-b bg-background p-2 font-mono text-xs text-foreground">TWIG TRANSFORMER (PAYLOAD)</div>
      <Editor
        height="400px"
        defaultLanguage="html" // Twig se parece mucho a HTML/Handlebars para el resaltado
        value={code}
        theme="vs-dark"
        onChange={handleEditorChange}
        options={{
          minimap: { enabled: false },
          fontSize: 14,
          wordWrap: 'on',
          scrollBeyondLastLine: false,
          automaticLayout: true,
        }}
      />
      <div className="bg-background p-2 text-xs text-foreground">
        Tip: Use <code>{'{{ data }}'}</code> to access the form data.
      </div>
    </div>
  );
};

export default PayloadEditor;
