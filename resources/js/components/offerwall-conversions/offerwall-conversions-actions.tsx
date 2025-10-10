import { Button } from '@/components/ui/button';
import { Download, RefreshCw } from 'lucide-react';

export function OfferwallConversionsActions() {
    const handleExport = () => {
        // TODO: Implement export functionality
        console.log('Export button clicked');
    };

    const handleRefresh = () => {
        // TODO: Implement refresh functionality
        console.log('Refresh button clicked');
    };

    return (
        <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={handleRefresh}>
                <RefreshCw className="mr-2 h-4 w-4" />
                Refresh
            </Button>
            <Button variant="outline" size="sm" onClick={handleExport}>
                <Download className="mr-2 h-4 w-4" />
                Export
            </Button>
        </div>
    );
}
