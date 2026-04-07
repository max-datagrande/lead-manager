import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { useIntegrations } from '@/hooks/use-integrations'
import { Settings2 } from 'lucide-react'
import { useRef, useState } from 'react'

const MAPPING_FIELDS = [
  { key: 'title', label: 'Title', supportsFallback: true },
  { key: 'description', label: 'Description', supportsFallback: true },
  { key: 'logo_url', label: 'Logo URL' },
  { key: 'click_url', label: 'Click URL' },
  { key: 'impression_url', label: 'Impression URL' },
  { key: 'cpc', label: 'CPC' },
  { key: 'display_name', label: 'Display Name' },
  { key: 'company', label: 'Company' },
]

const DEFAULT_RESPONSE_CONFIG = {
  offer_list_path: '',
  mapping: { title: '', description: '', logo_url: '', click_url: '', impression_url: '', cpc: '', display_name: '', company: '' },
  fallbacks: {},
}

export function OfferwallParserConfig({ env }) {
  const timer = useRef(null)
  const { data, handleEnvironmentChange } = useIntegrations()
  const envConfig = data.environments[env]?.response_config ?? DEFAULT_RESPONSE_CONFIG

  const [offerListPathRef, setOfferListPathRef] = useState(envConfig.offer_list_path ?? '')
  const [mapping, setMapping] = useState(envConfig.mapping ?? {})
  const [fallbacks, setFallbacks] = useState(envConfig.fallbacks ?? {})

  const handlePathChange = (e) => {
    const newValue = e.target.value
    setOfferListPathRef(newValue)
    if (timer.current) clearTimeout(timer.current)
    timer.current = setTimeout(() => {
      handleEnvironmentChange(env, 'response_config', { ...envConfig, offer_list_path: newValue })
    }, 500)
  }

  const handleMappingChange = (key, value) => {
    const newEntry = { [key]: value }
    setMapping({ ...mapping, ...newEntry })
    if (timer.current) clearTimeout(timer.current)
    timer.current = setTimeout(() => {
      handleEnvironmentChange(env, 'response_config', { ...envConfig, mapping: { ...(envConfig.mapping ?? {}), ...newEntry } })
    }, 500)
  }

  const handleFallbackChange = (key, value) => {
    const newFallbacks = { ...fallbacks, [key]: value }
    setFallbacks(newFallbacks)
    if (timer.current) clearTimeout(timer.current)
    timer.current = setTimeout(() => {
      handleEnvironmentChange(env, 'response_config', { ...envConfig, fallbacks: { ...(envConfig.fallbacks ?? {}), [key]: value } })
    }, 500)
  }

  return (
    <Card className="mt-6">
      <CardHeader>
        <CardTitle>Offerwall Parser Configuration</CardTitle>
        <CardDescription>Specify how to find and map the offers from the API response.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        <div className="space-y-2">
          <Label htmlFor="offer_list_path">Offer List Path</Label>
          <Input id="offer_list_path" value={offerListPathRef} onChange={handlePathChange} placeholder="e.g., response.offers.items" />
        </div>

        <div>
          <h4 className="mb-2 text-sm font-medium">Mapping</h4>
          <div className="grid grid-cols-1 gap-6 rounded-md md:grid-cols-2">
            {MAPPING_FIELDS.map((field) => (
              <div key={field.key} className="flex flex-col gap-0.5 lg:flex-row lg:items-center lg:gap-2">
                <Label htmlFor={`mapping-${field.key}`} className="w-full lg:w-1/3">
                  {field.label}
                </Label>
                <div className="flex w-full items-center gap-1">
                  <Input
                    id={`mapping-${field.key}`}
                    value={mapping[field.key] ?? ''}
                    onChange={(e) => handleMappingChange(field.key, e.target.value)}
                    placeholder={`e.g., ${field.key}`}
                  />
                  {field.supportsFallback && (
                    <Popover>
                      <PopoverTrigger asChild>
                        <Button
                          type="button"
                          variant={`${fallbacks[field.key] ? 'default' : 'outline'}`}
                          size="icon"
                          className="shrink-0"
                          title="Set fallback value"
                        >
                          <Settings2 className="h-4 w-4" />
                        </Button>
                      </PopoverTrigger>
                      <PopoverContent align="end" className="w-72">
                        <div className="space-y-2">
                          <Label htmlFor={`fallback-${field.key}`} className="text-sm font-medium">
                            Fallback for {field.label}
                          </Label>
                          <p className="text-muted-foreground text-xs">Used when the API returns empty or null for this field.</p>
                          <Input
                            id={`fallback-${field.key}`}
                            value={fallbacks[field.key] ?? ''}
                            onChange={(e) => handleFallbackChange(field.key, e.target.value)}
                            placeholder={`Default ${field.label.toLowerCase()}...`}
                          />
                        </div>
                      </PopoverContent>
                    </Popover>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
