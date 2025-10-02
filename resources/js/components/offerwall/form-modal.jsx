import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { useToast } from '@/hooks/use-toast';

export function FormModal({ onResolve, onReject, ...props }) {
  const [integrations, setIntegrations] = useState([]);
  const [filteredIntegrations, setFilteredIntegrations] = useState([]);
  const [selectedIntegrations, setSelectedIntegrations] = useState(new Set());
  const [searchTerm, setSearchTerm] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const { addMessage: setNotify } = useToast();

  useEffect(() => {
    const fetchIntegrations = async () => {
      try {
        setIsLoading(true);
        const response = await fetch(route('api.offerwall.integrations'));
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        const data = await response.json();
        setIntegrations(data);
        setFilteredIntegrations(data);
      } catch (error) {
        setNotify('Failed to fetch integrations', 'error');
        console.error('Fetch error:', error);
      } finally {
        setIsLoading(false);
      }
    };

    fetchIntegrations();
  }, []);

  useEffect(() => {
    const results = integrations.filter(integration =>
      integration.name.toLowerCase().includes(searchTerm.toLowerCase())
    );
    setFilteredIntegrations(results);
  }, [searchTerm, integrations]);

  const handleSelect = (integrationId) => {
    setSelectedIntegrations(prev => {
      const newSet = new Set(prev);
      if (newSet.has(integrationId)) {
        newSet.delete(integrationId);
      } else {
        newSet.add(integrationId);
      }
      return newSet;
    });
  };

  const handleCreate = () => {
    // Here you would typically post the selectedIntegrations to your backend
    console.log('Creating offerwall mix with:', Array.from(selectedIntegrations));
    setNotify('Offerwall mix created successfully!', 'success');
    onResolve({ success: true, selected: Array.from(selectedIntegrations) });
  };

  return (
    <Dialog open onOpenChange={(isOpen) => !isOpen && onReject()}>
      <DialogContent className="sm:max-w-[625px]">
        <DialogHeader>
          <DialogTitle>Create Offerwall Mix</DialogTitle>
          <DialogDescription>Select integrations to include in this mix.</DialogDescription>
        </DialogHeader>
        <div className="py-4">
          <Input
            placeholder="Search for an integration..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="mb-4"
          />
          <div className="max-h-[300px] overflow-y-auto pr-2">
            {isLoading ? (
              <p>Loading integrations...</p>
            ) : (
              filteredIntegrations.map(integration => (
                <div key={integration.id} className="flex items-center space-x-2 mb-2 p-2 rounded-md hover:bg-gray-100">
                  <Checkbox
                    id={`integration-${integration.id}`}
                    checked={selectedIntegrations.has(integration.id)}
                    onCheckedChange={() => handleSelect(integration.id)}
                  />
                  <label htmlFor={`integration-${integration.id}`} className="flex-1 cursor-pointer">
                    <div className="font-medium">{integration.name}</div>
                    {integration.company && <div className="text-sm text-gray-500">{integration.company.name}</div>}
                  </label>
                </div>
              ))
            )}
            {!isLoading && filteredIntegrations.length === 0 && (
                <p className='text-center text-sm text-gray-500'>No integrations found.</p>
            )}
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onReject}>Cancel</Button>
          <Button onClick={handleCreate} disabled={selectedIntegrations.size === 0}>
            Create Mix
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
