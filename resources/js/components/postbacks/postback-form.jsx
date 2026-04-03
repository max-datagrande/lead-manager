import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { extractUrlParams } from '@/lib/url-params';
import { Link } from '@inertiajs/react';
import { ArrowRight, Check, Copy, Globe, Link2, Lock, Settings2, Shuffle, Trash2, Zap } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

export default function PostbackForm({ data, setData, errors, processing, platforms, fireModes = [], domains = [], onSubmit, isEdit = false }) {
  const [availableTokens, setAvailableTokens] = useState([]);
  const [detectedParams, setDetectedParams] = useState([]);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    const platform = platforms.find((p) => p.id === Number(data.platform_id));
    const mappings = platform?.token_mappings ?? {};
    setAvailableTokens([...new Set(Object.values(mappings))].sort());
  }, [data.platform_id, platforms]);

  useEffect(() => {
    if (data.base_url) {
      const params = extractUrlParams(data.base_url);
      setDetectedParams(params);
      const updatedMappings = { ...data.param_mappings };
      params.forEach(({ key, value }) => {
        if (!(key in updatedMappings)) updatedMappings[key] = value;
      });
      Object.keys(updatedMappings).forEach((k) => {
        if (!params.find((p) => p.key === k)) delete updatedMappings[k];
      });
      setData('param_mappings', updatedMappings);
    } else {
      setDetectedParams([]);
      setData('param_mappings', {});
    }
  }, []);

  const handleChange = (e) => {
    const baseUrl = e.target.value;
    setData('base_url', baseUrl);
    try {
      new URL(baseUrl);
    } catch {
      return;
    }
    const params = extractUrlParams(baseUrl);
    setDetectedParams(params);
    const updatedMappings = {};
    params.forEach(({ key, value }) => {
      updatedMappings[key] = data.param_mappings?.[key] ?? value;
    });
    setData('param_mappings', updatedMappings);
  };

  const updateMapping = (param, token) => {
    setData('param_mappings', { ...data.param_mappings, [param]: token });
  };

  const removeParam = (key) => {
    try {
      const url = new URL(data.base_url);
      url.searchParams.delete(key);
      const newBaseUrl = decodeURIComponent(url.toString());
      setData('base_url', newBaseUrl);
      setDetectedParams((prev) => prev.filter((p) => p.key !== key));
      const updated = { ...data.param_mappings };
      delete updated[key];
      setData('param_mappings', updated);
    } catch {
      // fallback: just remove from state
      setDetectedParams((prev) => prev.filter((p) => p.key !== key));
      const updated = { ...data.param_mappings };
      delete updated[key];
      setData('param_mappings', updated);
    }
  };

  const parsedUrl = useMemo(() => {
    if (!data.base_url || !detectedParams.length) return '';
    try {
      const url = new URL(data.base_url);
      detectedParams.forEach(({ key }) => {
        const token = data.param_mappings?.[key];
        url.searchParams.set(key, token ? `{${token}}` : '');
      });
      return decodeURIComponent(url.toString());
    } catch {
      return '';
    }
  }, [data.base_url, data.param_mappings, detectedParams]);

  useEffect(() => {
    setData('result_url', parsedUrl);
  }, [parsedUrl]);

  const copyParsedUrl = () => {
    navigator.clipboard.writeText(parsedUrl);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const mappedCount = detectedParams.filter(({ key }) => data.param_mappings?.[key]).length;

  return (
    <form onSubmit={onSubmit} className="space-y-6">
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 auto-rows-auto">
        {/* Status & Fire Mode */}
        <Card className={`gap-2 ${data.is_active ? 'bg-green-500/10' : 'bg-muted/50'}`}>
          <CardHeader className="flex flex-row items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <div className={`flex h-8 w-8 items-center justify-center rounded-md ${data.is_active ? 'bg-green-500/15' : 'bg-muted'}`}>
                <Zap className={`h-4 w-4 ${data.is_active ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground'}`} />
              </div>
              <div>
                <CardTitle className="text-base">Status & Fire Mode</CardTitle>
                <CardDescription className="text-xs">Control when and how this postback fires</CardDescription>
              </div>
            </div>
            <div className="flex items-end space-x-2 pb-1">
              <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
              <Label htmlFor="is_active">Active</Label>
            </div>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Fire Mode</Label>
                <Select value={data.fire_mode} onValueChange={(val) => setData('fire_mode', val)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select mode" />
                  </SelectTrigger>
                  <SelectContent>
                    {fireModes.map((mode) => (
                      <SelectItem key={mode.value} value={mode.value}>
                        {mode.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errors.fire_mode && <p className="text-xs text-destructive">{errors.fire_mode}</p>}
              </div>
              <div className="space-y-1.5">
                <Label>Exposure</Label>
                <Select value={data.is_public ? 'public' : 'internal'} onValueChange={(val) => setData('is_public', val === 'public')}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {domains.map((d) => (
                      <SelectItem key={d.value} value={d.value}>
                        <span className="flex items-center gap-1.5">
                          {d.value === 'public' ? <Globe className="h-3.5 w-3.5" /> : <Lock className="h-3.5 w-3.5" />}
                          {d.label}
                        </span>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errors.is_public && <p className="text-xs text-destructive">{errors.is_public}</p>}
              </div>
            </div>
            {domains.length > 0 && (
              <p className="mt-2 text-xs text-muted-foreground">
                Domain: <span className="font-mono font-medium text-foreground">{domains.find((d) => d.value === (data.is_public ? 'public' : 'internal'))?.url}</span>
              </p>
            )}
          </CardContent>
        </Card>

        {/* Parameter Mappings — spans both rows on col 2 */}
        <Card className="row-span-2 flex flex-col gap-0">
          <CardHeader>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10">
                  <Shuffle className="h-4 w-4 text-primary" />
                </div>
                <div>
                  <CardTitle className="text-base">Parameter Mappings</CardTitle>
                  <CardDescription className="text-xs">Map URL params to internal tokens</CardDescription>
                </div>
              </div>
              {detectedParams.length > 0 && (
                <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                  {mappedCount}/{detectedParams.length} mapped
                </span>
              )}
            </div>
          </CardHeader>
          <CardContent className='flex-1 flex items-center justify-center'>
            {detectedParams.length === 0 ? (
              <div className="flex flex-col items-center justify-center gap-2 py-8 text-center text-muted-foreground">
                <Shuffle className="size-10 opacity-30" />
                <p className="text-muted-foreground">Paste a postback URL to detect parameters</p>
              </div>
            ) : (
              <div className="space-y-2">
                {/* Column headers */}
                <div className="grid grid-cols-[1fr_auto_1fr_auto] items-center gap-2 px-1 pb-1">
                  <span className="text-xs font-medium text-muted-foreground">URL Param</span>
                  <span className="w-5" />
                  <span className="text-xs font-medium text-muted-foreground">Internal Token</span>
                  <span className="w-8" />
                </div>

                {detectedParams.map(({ key, value: urlValue }) => {
                  const isMapped = Boolean(data.param_mappings?.[key]);
                  return (
                    <div key={key} className="grid grid-cols-[1fr_auto_1fr_auto] items-center gap-2">
                      <code className="truncate rounded-md border bg-muted px-2 py-1.5 font-mono text-xs" title={key}>
                        {key}
                      </code>
                      <ArrowRight className={`h-3.5 w-3.5 shrink-0 ${isMapped ? 'text-primary' : 'text-muted-foreground/40'}`} />
                      <Select value={data.param_mappings?.[key] ?? ''} onValueChange={(val) => updateMapping(key, val)}>
                        <SelectTrigger className="font-mono text-xs">
                          <SelectValue placeholder="Select token" />
                        </SelectTrigger>
                        <SelectContent>
                          {availableTokens.map((token) => (
                            <SelectItem key={token} value={token}>
                              {token}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 shrink-0 p-0 text-muted-foreground hover:bg-destructive hover:text-white"
                        onClick={() => removeParam(key)}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </div>
                  );
                })}

                {errors.param_mappings && <p className="pt-1 text-xs text-destructive">{errors.param_mappings}</p>}
              </div>
            )}
          </CardContent>
        </Card>

        {/* Configuration */}
        <Card className="gap-2">
          <CardHeader>
            <div className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10">
                <Settings2 className="h-4 w-4 text-primary" />
              </div>
              <div>
                <CardTitle className="text-base">Configuration</CardTitle>
                <CardDescription className="text-xs">Name, platform and base URL</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="name">Name</Label>
              <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. ClickFlare – Car Insurance" />
              {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
            </div>

            <div className="space-y-1.5">
              <Label>Platform</Label>
              <SearchableSelect
                options={platforms.map((p) => ({ value: String(p.id), label: p.name }))}
                value={data.platform_id ? String(data.platform_id) : ''}
                onValueChange={(val) => setData('platform_id', val ? Number(val) : '')}
                placeholder="Select a platform"
              />
              {errors.platform_id && <p className="text-xs text-destructive">{errors.platform_id}</p>}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="base_url">Postback URL</Label>
              <div className="relative">
                <Link2 className="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input
                  id="base_url"
                  value={data.base_url}
                  onChange={handleChange}
                  placeholder="https://platform.com/postback?amount=..."
                  className="pl-8 font-mono text-xs"
                />
              </div>
              {errors.base_url && <p className="text-xs text-destructive">{errors.base_url}</p>}
              {detectedParams.length > 0 && (
                <p className="text-xs text-muted-foreground">
                  {detectedParams.length} param{detectedParams.length !== 1 ? 's' : ''} detected:{' '}
                  <span className="font-medium text-foreground">{detectedParams.map((p) => p.key).join(', ')}</span>
                </p>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Parsed URL preview */}
      {parsedUrl && (
        <Card className="gap-2">
          <CardHeader>
            <div className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10">
                <Link2 className="h-4 w-4 text-primary" />
              </div>
              <div>
                <CardTitle className="text-base">Parsed URL Preview</CardTitle>
                <CardDescription className="text-xs">Final URL with mapped tokens — this is what gets called when the postback fires</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div className="flex overflow-hidden rounded-md border bg-background">
              <p className="no-scrollbar flex flex-1 items-center justify-start overflow-scroll bg-muted px-4 font-mono text-sm leading-relaxed break-all">
                {parsedUrl.split(/(\{[^}]+\})/).map((part, i) =>
                  /^\{[^}]+\}$/.test(part) ? (
                    <span key={i} className="font-semibold whitespace-nowrap text-orange-500 dark:text-orange-400">
                      {part}
                    </span>
                  ) : (
                    <span key={i} className="whitespace-nowrap">
                      {part}
                    </span>
                  ),
                )}
              </p>
              <Button
                type="button"
                variant="black"
                size="icon"
                onClick={copyParsedUrl}
                className="flex aspect-square shrink-0 items-center justify-center rounded-l-none px-2 py-2"
              >
                {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Actions */}
      <div className="flex items-center justify-end gap-3">
        <Link href={route('postbacks.index')}>
          <Button type="button" variant="outline">
            Cancel
          </Button>
        </Link>
        <Button type="submit" disabled={processing}>
          {processing ? 'Saving…' : isEdit ? 'Update Postback' : 'Create Postback'}
        </Button>
      </div>
    </form>
  );
}
