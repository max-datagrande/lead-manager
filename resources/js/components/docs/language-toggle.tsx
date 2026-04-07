import { useLocale } from '@/hooks/use-locale';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

export function LanguageToggle() {
  const { locale, setLocale } = useLocale();

  return (
    <div className="flex items-center gap-2">
      <Label className={locale === 'en' ? 'text-foreground' : 'text-muted-foreground'}>EN</Label>
      <Switch checked={locale === 'es'} onCheckedChange={(checked) => setLocale(checked ? 'es' : 'en')} aria-label="Toggle language" />
      <Label className={locale === 'es' ? 'text-foreground' : 'text-muted-foreground'}>ES</Label>
    </div>
  );
}
