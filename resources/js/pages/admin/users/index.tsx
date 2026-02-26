import PageHeader from '@/components/page-header';
import { TableUsers, UsersActions } from '@/components/users';
import { UsersProvider } from '@/context/users-provider';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Users',
    href: route('admin.users.index'),
  },
];

interface UserRow {
  id: number;
  name: string;
  email: string;
  role: string;
  is_active: boolean;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

interface IndexProps {
  users: UserRow[];
}

const Index = ({ users }: IndexProps) => {
  return (
    <UsersProvider>
      <Head title="Users" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Users" description="Manage user access, roles and activation status.">
          <UsersActions />
        </PageHeader>
        <TableUsers entries={users} />
      </div>
    </UsersProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Index;
