import React, { useState } from 'react';
import { ChevronDown, ChevronRight, Copy, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const JsonViewer = ({ data, title, className = "" }) => {
  const [expanded, setExpanded] = useState({});
  const [copied, setCopied] = useState(false);

  const toggleExpanded = (path) => {
    setExpanded(prev => ({
      ...prev,
      [path]: !prev[path]
    }));
  };

  const copyToClipboard = async () => {
    try {
      const jsonString = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
      await navigator.clipboard.writeText(jsonString);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      console.error('Error copying to clipboard:', err);
    }
  };

  const renderValue = (value, path = '', level = 0) => {
    if (value === null) {
      return <span className="text-gray-500 italic">null</span>;
    }

    if (value === undefined) {
      return <span className="text-gray-500 italic">undefined</span>;
    }

    if (typeof value === 'boolean') {
      return <span className={value ? 'text-green-600' : 'text-red-600'}>{value.toString()}</span>;
    }

    if (typeof value === 'number') {
      return <span className="text-blue-600">{value}</span>;
    }

    if (typeof value === 'string') {
      return <span className="text-green-700">"{value}"</span>;
    }

    if (Array.isArray(value)) {
      const isExpanded = expanded[path] !== false; // Default to expanded
      const isEmpty = value.length === 0;

      return (
        <div className="inline-block">
          <button
            onClick={() => toggleExpanded(path)}
            className="flex items-center gap-1 hover:bg-gray-100 rounded px-1 -ml-1"
            disabled={isEmpty}
          >
            {!isEmpty && (
              isExpanded ?
                <ChevronDown className="h-3 w-3" /> :
                <ChevronRight className="h-3 w-3" />
            )}
            <span className="text-gray-600">[{isEmpty ? '' : value.length}]</span>
          </button>
          {isExpanded && !isEmpty && (
            <div className="ml-4 mt-1">
              {value.map((item, index) => (
                <div key={index} className="mb-1">
                  <span className="text-gray-500 text-sm">{index}: </span>
                  {renderValue(item, `${path}[${index}]`, level + 1)}
                </div>
              ))}
            </div>
          )}
        </div>
      );
    }

    if (typeof value === 'object') {
      const isExpanded = expanded[path] !== false; // Default to expanded
      const keys = Object.keys(value);
      const isEmpty = keys.length === 0;

      return (
        <div className="inline-block">
          <button
            onClick={() => toggleExpanded(path)}
            className="flex items-center gap-1 hover:bg-gray-100 rounded px-1 -ml-1"
            disabled={isEmpty}
          >
            {!isEmpty && (
              isExpanded ?
                <ChevronDown className="h-3 w-3" /> :
                <ChevronRight className="h-3 w-3" />
            )}
            <span className="text-gray-600">{isEmpty ? '{}' : `{${keys.length}}`}</span>
          </button>
          {isExpanded && !isEmpty && (
            <div className="ml-4 mt-1">
              {keys.map((key) => (
                <div key={key} className="mb-1">
                  <span className="text-purple-600 font-medium">"{key}"</span>
                  <span className="text-gray-500">: </span>
                  {renderValue(value[key], `${path}.${key}`, level + 1)}
                </div>
              ))}
            </div>
          )}
        </div>
      );
    }

    return <span className="text-gray-800">{String(value)}</span>;
  };

  const parseData = () => {
    if (!data) return null;

    try {
      return typeof data === 'string' ? JSON.parse(data) : data;
    } catch (e) {
      return data;
    }
  };

  const parsedData = parseData();

  if (!parsedData) {
    return (
      <div className={cn('p-3 bg-gray-50 border rounded-md', className)}>
        <div className="text-gray-500 text-sm italic">No data available</div>
      </div>
    );
  }

  return (
    <div className={cn('bg-gray-50 border rounded-md', className)}>
      {title && (
        <div className="flex items-center justify-between p-3 border-b bg-gray-100">
          <h4 className="font-medium text-sm">{title}</h4>
          <Button
            variant="ghost"
            size="sm"
            onClick={copyToClipboard}
            className="h-6 px-2"
          >
            {copied ? (
              <Check className="h-3 w-3 text-green-600" />
            ) : (
              <Copy className="h-3 w-3" />
            )}
          </Button>
        </div>
      )}
      <div className="p-3 font-mono text-xs overflow-auto max-h-64">
        {renderValue(parsedData, 'root')}
      </div>
    </div>
  );
};

export default JsonViewer;
