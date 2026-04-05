import PageHeader from '@/components/page-header';
import PostbackForm from '@/components/postbacks/postback-form';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { type Platform } from '@/types/models/platform';
import { type FireModeOption, type DomainOption, type PostbackTypeOption } from '@/types/models/postback';
import { Head, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Postbacks', href: '/postbacks' },
  { title: 'New', href: '/postbacks/create' },
];

interface Props {
  platforms: Platform[];
  fireModes: FireModeOption[];
  postbackTypes: PostbackTypeOption[];
  internalTokens: string[];
  domains: DomainOption[];
}

const Create = ({ platforms, fireModes, postbackTypes, internalTokens, domains }: Props) => {
  const { addMessage } = useToast();

  const { data, setData, post, processing, errors } = useForm({
    name: '',
    type: 'external' as 'external' | 'internal',
    platform_id: '' as number | '',
    base_url: '',
    param_mappings: {} as Record<string, string>,
    result_url: '',
    fire_mode: 'realtime',
    is_active: true,
    is_public: true,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route('postbacks.store'), {
      onSuccess: (page) => {
        const generatedUrl = (page.props as { flash?: { generated_url?: string } }).flash?.generated_url;
        if (generatedUrl) {
          addMessage(`Postback created! URL: ${generatedUrl}`, 'success');
        }
      },
    });
  };

  return (
    <>
      <Head title="New Postback" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Create Postback" description="Create a new postback." />
        <PostbackForm
          data={data}
          setData={setData}
          errors={errors}
          processing={processing}
          platforms={platforms}
          fireModes={fireModes}
          postbackTypes={postbackTypes}
          internalTokens={internalTokens}
          domains={domains}
          onSubmit={handleSubmit}
          isEdit={false}
        />
      </div>
    </>
  );
};

Create.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Create;
