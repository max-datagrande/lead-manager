import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useModal } from '@/hooks/use-modal';
import { getCookie } from '@/utils/navigator';
import { useEffect, useState } from 'react';

export function FormModal({ entry = 0, ...props }) {
  const initialIntegrations = entry.integrations.map((i) => i.id);
  const modal = useModal();
  const [integrations, setIntegrations] = useState([]);
  const [filteredIntegrations, setFilteredIntegrations] = useState([]);
  const [selectedIntegrations, setSelectedIntegrations] = useState(new Set(initialIntegrations));
  const [searchTerm, setSearchTerm] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [isCreating, setIsCreating] = useState(false);
  const [formData, setFormData] = useState({
    name: entry.name ?? '',
    description: entry.description ?? '',
  });

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
        console.error('Fetch error:', error);
      } finally {
        setIsLoading(false);
      }
    };

    fetchIntegrations();
  }, []);

  useEffect(() => {
    const results = integrations.filter((integration) => integration.name.toLowerCase().includes(searchTerm.toLowerCase()));
    setFilteredIntegrations(results);
  }, [searchTerm, integrations]);

  const handleSelect = (integrationId) => {
    setSelectedIntegrations((prev) => {
      const newSet = new Set(prev);
      if (newSet.has(integrationId)) {
        newSet.delete(integrationId);
      } else {
        newSet.add(integrationId);
      }
      return newSet;
    });
  };

  const handleCancel = () => {
    const modalId = modal.topId;
    modal.resolve(modalId, false);
  };

  const handleCreate = async () => {
    if (!formData.name.trim()) {
      return;
    }
    setIsCreating(true);
    try {
      const endpoint = route('api.offerwall.mixes.store');
      const csrfToken = getCookie('XSRF-TOKEN');

      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'x-xsrf-token': csrfToken,
          'x-requested-with': 'XMLHttpRequest',
          accept: 'application/json',
        },
        body: JSON.stringify({
          name: formData.name,
          description: formData.description,
          integration_ids: Array.from(selectedIntegrations),
        }),
      });

      const data = await response.json();

      if (response.ok && data.success) {
        const modalId = modal.topId;
        modal.resolve(modalId, {
          success: true,
          data: data.data,
          message: data.message,
        });
      } else {
        throw new Error(data.message || 'Error creating offerwall mix');
      }
    } catch (error) {
      console.error('Error creating mix:', error);
      alert('Error al crear el mix: ' + error.message);
    } finally {
      setIsCreating(false);
    }
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>Create Offerwall Mix</DialogTitle>
        <DialogDescription>Create a new mix by providing a name, description, and selecting integrations.</DialogDescription>
      </DialogHeader>
      <div className="space-y-4 py-4">
        <div className="space-y-2">
          <Label htmlFor="mix-name">Name *</Label>
          <Input
            id="mix-name"
            placeholder="Enter mix name..."
            value={formData.name}
            onChange={(e) => setFormData((prev) => ({ ...prev, name: e.target.value }))}
            disabled={isCreating}
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="mix-description">Description</Label>
          <Textarea
            id="mix-description"
            placeholder="Enter mix description (optional)..."
            value={formData.description}
            onChange={(e) => setFormData((prev) => ({ ...prev, description: e.target.value }))}
            disabled={isCreating}
            rows={3}
          />
        </div>

        <div className="space-y-2">
          <Label>Integrations *</Label>
          <Input
            placeholder="Search for an integration..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            disabled={isCreating}
          />
          <div className="max-h-[300px] space-y-2 overflow-y-auto rounded-md border p-2 pr-2">
            {isLoading ? (
              <p className="py-4 text-center">Loading integrations...</p>
            ) : (
              filteredIntegrations.map((integration) => (
                <div key={integration.id} className="flex items-center space-x-2 rounded-md p-2 hover:bg-gray-100">
                  <Checkbox
                    id={`integration-${integration.id}`}
                    checked={selectedIntegrations.has(integration.id)}
                    onCheckedChange={() => handleSelect(integration.id)}
                    disabled={isCreating}
                  />
                  <label htmlFor={`integration-${integration.id}`} className="flex-1 cursor-pointer">
                    <div className="font-medium">{integration.name}</div>
                    {integration.company && <div className="text-sm text-gray-500">{integration.company.name}</div>}
                  </label>
                </div>
              ))
            )}
            {!isLoading && filteredIntegrations.length === 0 && <p className="py-4 text-center text-sm text-gray-500">No integrations found.</p>}
          </div>
        </div>
      </div>
      <DialogFooter>
        <Button type="button" variant="outline" onClick={handleCancel} disabled={isCreating}>
          Cancel
        </Button>
        <Button onClick={handleCreate} disabled={selectedIntegrations.size === 0 || !formData.name.trim() || isCreating}>
          {isCreating ? 'Creating...' : entry.id ? 'Update Mix' : 'Create Mix'}
        </Button>
      </DialogFooter>
    </>
  );
}
