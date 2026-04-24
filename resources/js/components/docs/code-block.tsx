import { cn } from '@/lib/utils';
import { Check, Copy } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { codeToHtml, type BundledLanguage } from 'shiki';

interface CodeBlockProps {
  code: string;
  language?: string;
  className?: string;
}

const SHIKI_THEME = 'one-dark-pro';

export function CodeBlock({ code, language, className }: CodeBlockProps) {
  const [copied, setCopied] = useState(false);
  const [html, setHtml] = useState<string | null>(null);

  useEffect(() => {
    if (!language) {
      setHtml(null);
      return;
    }

    let cancelled = false;
    codeToHtml(code, {
      lang: language as BundledLanguage,
      theme: SHIKI_THEME,
      transformers: [
        {
          pre(node) {
            node.properties.style = '';
            node.properties.class = 'shiki-pre';
          },
        },
      ],
    })
      .then((out) => {
        if (!cancelled) setHtml(out);
      })
      .catch(() => {
        if (!cancelled) setHtml(null);
      });

    return () => {
      cancelled = true;
    };
  }, [code, language]);

  const handleCopy = useCallback(() => {
    navigator.clipboard.writeText(code);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }, [code]);

  return (
    <div className={cn('group relative rounded-lg bg-zinc-950 text-zinc-100', className)}>
      {language && (
        <div className="flex items-center justify-between border-b border-zinc-800 px-4 py-2">
          <span className="text-xs font-medium text-zinc-400">{language}</span>
        </div>
      )}
      <button
        type="button"
        onClick={handleCopy}
        className="absolute top-2.5 right-3 rounded-md p-1.5 text-zinc-400 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-zinc-800 hover:text-zinc-200"
        aria-label="Copy code"
      >
        {copied ? <Check className="h-4 w-4 text-emerald-400" /> : <Copy className="h-4 w-4" />}
      </button>
      {html ? (
        <div
          className="overflow-x-auto p-4 text-sm leading-relaxed [&_.shiki-pre]:m-0 [&_.shiki-pre]:bg-transparent [&_.shiki-pre]:p-0"
          dangerouslySetInnerHTML={{ __html: html }}
        />
      ) : (
        <pre className="overflow-x-auto p-4 text-sm leading-relaxed">
          <code>{code}</code>
        </pre>
      )}
    </div>
  );
}
