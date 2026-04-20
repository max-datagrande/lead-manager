import { useToast } from '@/hooks/use-toast';
import type { Buyer } from '@/types/ping-post';
import { useForm } from '@inertiajs/react';
import { createContext, useMemo } from 'react';
import { route } from 'ziggy-js';

type BuyerFormData = ReturnType<typeof buildInitialData>;

interface BuyersContextValue {
  isEdit: boolean;
  data: BuyerFormData;
  errors: Record<string, string>;
  processing: boolean;
  handleSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
  setData: (key: string, value: any) => void;
}

export const BuyersContext = createContext<BuyersContextValue | null>(null);

function buildInitialData(buyer: Buyer | null) {
  const cfg = buyer?.buyer_config;
  return {
    name: buyer?.name ?? '',
    integration_id: buyer?.integration_id ?? null,
    is_active: buyer?.is_active ?? true,
    company_id: buyer?.company_id ?? null,
    ping_timeout_ms: cfg?.ping_timeout_ms ?? '',
    post_timeout_ms: cfg?.post_timeout_ms ?? '',
    price_source: cfg?.price_source ?? 'fixed',
    fixed_price: String(cfg?.fixed_price ?? ''),
    min_bid: String(cfg?.min_bid ?? ''),
    postback_pending_days: cfg?.postback_pending_days ?? '',
    pricing_postback: cfg?.pricing_postback?.[0]
      ? {
          postback_id: cfg.pricing_postback[0].id,
          identifier_token: cfg.pricing_postback[0].pivot.identifier_token,
          price_token: cfg.pricing_postback[0].pivot.price_token,
        }
      : null,
    sell_on_zero_price: cfg?.sell_on_zero_price ?? false,
    conditional_pricing_rules: cfg?.conditional_pricing_rules ?? [],
    eligibility_rules: buyer?.eligibility_rules ?? [],
    caps: buyer?.cap_rules ?? [],
  };
}

interface Props {
  children: React.ReactNode;
  buyer?: Buyer | null;
}

export function BuyersProvider({ children, buyer = null }: Props) {
  const isEdit = !!buyer;
  const initialData = useMemo(() => buildInitialData(buyer), [buyer?.id]);
  const { addMessage } = useToast();

  const { data, setData, post, put, processing, errors } = useForm(initialData);

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const options = {
      preserveScroll: true,
      onSuccess: (page: any) => {
        const flash = page.props?.flash;
        if (flash?.success) addMessage(flash.success, 'success');
      },
      onError: () => {
        addMessage('Please fix the validation errors below.', 'error');
      },
    };
    if (isEdit && buyer?.id) {
      put(route('ping-post.buyers.update', buyer.id), options);
    } else {
      post(route('ping-post.buyers.store'), options);
    }
  };

  const value: BuyersContextValue = { isEdit, data, errors, processing, handleSubmit, setData: setData as any };

  return <BuyersContext.Provider value={value}>{children}</BuyersContext.Provider>;
}
