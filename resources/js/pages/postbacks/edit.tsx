import PageHeader from '@/components/page-header';
import PostbackForm from '@/components/postbacks/postback-form';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { type Platform } from '@/types/models/platform';
import { type FireModeOption, type DomainOption, type Postback, type PostbackTypeOption } from '@/types/models/postback';
import { Head, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Postbacks', href: '/postbacks' },
  { title: 'Edit', href: '#' },
];

interface Props {
  postback: Postback;
  platforms: Platform[];
  fireModes: FireModeOption[];
  postbackTypes: PostbackTypeOption[];
  internalTokens: string[];
  domains: DomainOption[];
}

const Edit = ({ postback, platforms, fireModes, postbackTypes, internalTokens, domains }: Props) => {
  const { addMessage } = useToast();

  const { data, setData, put, processing, errors } = useForm({
    name: postback.name,
    type: postback.type ?? 'external',
    platform_id: (postback.platform_id ?? '') as number | '',
    base_url: postback.base_url,
    param_mappings: postback.param_mappings ?? {},
    result_url: postback.result_url ?? '',
    fire_mode: postback.fire_mode ?? 'realtime',
    is_active: postback.is_active ?? true,
    is_public: postback.is_public ?? true,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route('postbacks.update', postback.id), {
      onSuccess: () => {
        addMessage('Postback updated successfully', 'success');
      },
    });
  };

  return (
    <>
      <Head title="Edit Postback" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Edit Postback" description="Edit the postback." />
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
          isEdit
        />
      </div>
    </>
  );
};

Edit.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Edit;
