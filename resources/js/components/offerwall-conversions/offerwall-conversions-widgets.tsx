import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface OfferwallConversionsWidgetsProps {
    totalPayout: number;
}

export function OfferwallConversionsWidgets({ totalPayout }: OfferwallConversionsWidgetsProps) {
    const formattedPayout = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(totalPayout);

    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Total Payout</CardTitle>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        className="h-4 w-4 text-muted-foreground"
                    >
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{formattedPayout}</div>
                    <p className="text-xs text-muted-foreground">Total revenue from current search</p>
                </CardContent>
            </Card>
        </div>
    );
}
