import { AlertChannelsActions, TableAlertChannels } from '@/components/alert-channels';
import PageHeader from '@/components/page-header';
import { AlertChannelsProvider } from '@/context/alert-channels-provider';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Alert Channels',
    href: route('alert-channels.index'),
  },
];

export type ChannelType = {
  value: string;
  label: string;
};

export type AlertChannel = {
  id: number;
  name: string;
  type: string;
  webhook_url: string;
  active: boolean;
  user_id: number;
  updated_user_id: number | null;
  created_at: string;
  updated_at: string;
  creator?: {
    id: number;
    name: string;
  };
};

interface IndexProps {
  alert_channels: AlertChannel[];
  channel_types: ChannelType[];
}

const Index = ({ alert_channels, channel_types }: IndexProps) => {
  return (
    <AlertChannelsProvider channelTypes={channel_types}>
      <Head title="Alert Channels" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Alert Channels" description="Manage notification channels for system alerts.">
          <AlertChannelsActions />
        </PageHeader>
        <TableAlertChannels entries={alert_channels} />
      </div>
    </AlertChannelsProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;
