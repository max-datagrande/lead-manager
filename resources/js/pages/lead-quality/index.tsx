import PageHeader from '@/components/page-header'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem } from '@/types'
import { Head, Link } from '@inertiajs/react'
import { ListChecks, ScrollText, ShieldCheck } from 'lucide-react'
import type { ReactNode } from 'react'

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Lead Quality', href: '#' }]

interface Props {
  providers_count: number
  rules_count: number
  logs_count: number
}

interface ModuleCard {
  title: string
  description: string
  href: string
  icon: ReactNode
  count: number
  countLabel: string
}

const Index = ({ providers_count, rules_count, logs_count }: Props) => {
  const cards: ModuleCard[] = [
    {
      title: 'Providers',
      description: 'Configure external OTP and fraud-check providers (Twilio Verify, IPQS, etc.).',
      href: route('lead-quality.providers.index'),
      icon: <ShieldCheck className="size-6" />,
      count: providers_count,
      countLabel: 'provider',
    },
    {
      title: 'Validation Rules',
      description: 'Define reusable validation requirements and attach them to buyers.',
      href: route('lead-quality.validation-rules.index'),
      icon: <ListChecks className="size-6" />,
      count: rules_count,
      countLabel: 'rule',
    },
    {
      title: 'Validation Logs',
      description: 'Audit every challenge issued — who, when, status, technical traces.',
      href: route('lead-quality.validation-logs.index'),
      icon: <ScrollText className="size-6" />,
      count: logs_count,
      countLabel: 'log',
    },
  ]

  return (
    <>
      <Head title="Lead Quality" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Lead Quality" description="Manage OTP providers, validation rules, and review challenge audit logs." />
        <div className="grid gap-4 md:grid-cols-3">
          {cards.map((card) => (
            <Link key={card.title} href={card.href} className="group block focus-visible:outline-none">
              <Card className="h-full transition-all duration-200 group-hover:border-primary/50 group-hover:shadow-md group-focus-visible:ring-2 group-focus-visible:ring-ring group-focus-visible:ring-offset-2 dark:group-hover:border-primary/40">
                <CardHeader className="flex flex-row items-start gap-4 space-y-0 pb-3">
                  <div className="rounded-md bg-primary/10 p-2 text-primary transition-colors duration-200 group-hover:bg-primary/15 dark:bg-primary/15 dark:group-hover:bg-primary/20">
                    {card.icon}
                  </div>
                  <div className="flex-1 space-y-1">
                    <CardTitle className="text-base font-semibold leading-tight">{card.title}</CardTitle>
                    <p className="text-xs text-muted-foreground">
                      {card.count} {card.countLabel}
                      {card.count !== 1 ? 's' : ''}
                    </p>
                  </div>
                </CardHeader>
                <CardContent>
                  <CardDescription className="text-sm leading-relaxed">{card.description}</CardDescription>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
      </div>
    </>
  )
}

Index.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />

export default Index
