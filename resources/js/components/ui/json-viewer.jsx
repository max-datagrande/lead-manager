import React, { useState } from 'react';
import { ChevronDown, ChevronRight, Copy, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const JsonViewer = ({ data, title = null, className = "", showCounts = true }) => {
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
      return <span className="text-gray-500 dark:text-gray-400 italic">null</span>;
    }

    if (value === undefined) {
      return <span className="text-gray-500 dark:text-gray-400 italic">undefined</span>;
    }

    if (typeof value === 'boolean') {
      return <span className={value ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}>{value.toString()}</span>;
    }

    if (typeof value === 'number') {
      return <span className="text-blue-600 dark:text-blue-400">{value}</span>;
    }

    if (typeof value === 'string') {
      return <span className="text-green-700 dark:text-green-300">"{value}"</span>;
    }

    if (Array.isArray(value)) {
      const isExpanded = expanded[path] !== false;
      const isEmpty = value.length === 0;

      if (isEmpty) return <span className="text-gray-600 dark:text-gray-300">[]</span>;

      if (!isExpanded) {
        return (
          <button onClick={() => toggleExpanded(path)} className="text-gray-600 dark:text-gray-300 hover:text-gray-400 dark:hover:text-gray-100">
            [{showCounts ? value.length : '…'}]
          </button>
        );
      }

      return (
        <div>
          <span className="text-gray-600 dark:text-gray-300">[</span>
          <div className="ml-4 mt-1">
            {value.map((item, index) => {
              const childPath = `${path}[${index}]`;
              const isCollapsible = item !== null && typeof item === 'object';

              return (
                <div key={index} className={"mb-1"}>
                  {isCollapsible ? (
                    <>
                      <button
                        onClick={() => toggleExpanded(childPath)}
                        className="relative inline-flex items-center hover:bg-gray-100 dark:hover:bg-gray-800 rounded px-1.5 py-0.5 -ml-1.5"
                      >
                        <span className="absolute right-full top-1/2 -translate-y-1/2">
                          {expanded[childPath] === false
                            ? <ChevronRight className="h-3.5 w-3.5 text-muted-foreground" />
                            : <ChevronDown className="h-3.5 w-3.5 text-muted-foreground" />
                          }
                        </span>
                        <span className="text-gray-500 dark:text-gray-400 text-sm">{index}</span>
                      </button>
                      <span className="text-gray-500 dark:text-gray-400">: </span>
                      {renderValue(item, childPath, level + 1)}
                    </>
                  ) : (
                    <>
                      <span className="text-gray-500 dark:text-gray-400 text-sm">{index}: </span>
                      {renderValue(item, childPath, level + 1)}
                    </>
                  )}
                </div>
              );
            })}
          </div>
          <span className="text-gray-600 dark:text-gray-300">]</span>
        </div>
      );
    }

    if (typeof value === 'object') {
      const isExpanded = expanded[path] !== false;
      const keys = Object.keys(value);
      const isEmpty = keys.length === 0;

      if (isEmpty) return <span className="text-gray-600 dark:text-gray-300">{'{}'}</span>;

      if (!isExpanded) {
        return (
          <button onClick={() => toggleExpanded(path)} className="text-gray-600 dark:text-gray-300 hover:text-gray-400 dark:hover:text-gray-100">
            {showCounts ? `{${keys.length}}` : '{…}'}
          </button>
        );
      }

      return (
        <div>
          <span className="text-gray-600 dark:text-gray-300">{'{'}</span>
          <div className="ml-4 mt-1">
            {keys.map((key) => {
              const childPath = `${path}.${key}`;
              const childValue = value[key];
              const isCollapsible = childValue !== null && typeof childValue === 'object';

              return (
                <div key={key} className={"mb-1"}>
                  {isCollapsible ? (
                    <>
                      <button
                        onClick={() => toggleExpanded(childPath)}
                        className="relative inline-flex items-center hover:bg-gray-100 dark:hover:bg-gray-800 rounded px-1.5 py-0.5 -ml-1.5"
                      >
                        <span className="absolute right-full top-1/2 -translate-y-1/2">
                          {expanded[childPath] === false
                            ? <ChevronRight className="h-3.5 w-3.5 text-muted-foreground" />
                            : <ChevronDown className="h-3.5 w-3.5 text-muted-foreground" />
                          }
                        </span>
                        <span className="text-purple-600 dark:text-purple-400 font-medium">"{key}"</span>
                      </button>
                      <span className="text-gray-500 dark:text-gray-400">: </span>
                      {renderValue(childValue, childPath, level + 1)}
                    </>
                  ) : (
                    <>
                      <span className="text-purple-600 dark:text-purple-400 font-medium">"{key}"</span>
                      <span className="text-gray-500 dark:text-gray-400">: </span>
                      {renderValue(childValue, childPath, level + 1)}
                    </>
                  )}
                </div>
              );
            })}
          </div>
          <span className="text-gray-600 dark:text-gray-300">{'}'}</span>
        </div>
      );
    }

    return <span className="text-gray-800 dark:text-gray-200">{String(value)}</span>;
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
      <div className={cn('p-3 bg-gray-50 dark:bg-accent border rounded-md', className)}>
        <div className="text-gray-500 dark:text-gray-400 text-sm italic">No data available</div>
      </div>
    );
  }

  return (
    <div className={cn('bg-background border rounded-md overflow-auto', className)}>
      {title && (
        <div className="flex items-center justify-between p-3 border-b bg-muted">
          <h4 className="font-medium text-sm text-gray-900 dark:text-gray-100">{title}</h4>
          <Button
            variant="ghost"
            size="sm"
            onClick={copyToClipboard}
            className="h-6 px-2"
          >
            {copied ? (
              <Check className="h-3 w-3 text-green-600 dark:text-green-400" />
            ) : (
              <Copy className="h-3 w-3" />
            )}
          </Button>
        </div>
      )}
      <div className="p-3 font-mono text-xs text-gray-900 dark:text-gray-100">
        {renderValue(parsedData, 'root')}
      </div>
    </div>
  );
};

export default JsonViewer;
