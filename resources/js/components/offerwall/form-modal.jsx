import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useModal } from '@/hooks/use-modal';
import { getCookie } from '@/utils/navigator';
import { useEffect, useState } from 'react';

export function FormModal({ entry = null, isEdit, ...props }) {
  const initialIntegrations = entry?.integrations?.map((i) => i.id) ?? [];
  const modal = useModal();
  const [integrations, setIntegrations] = useState([]);
  const [filteredIntegrations, setFilteredIntegrations] = useState([]);
  const [selectedIntegrations, setSelectedIntegrations] = useState(new Set(initialIntegrations));
  const [searchTerm, setSearchTerm] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [formData, setFormData] = useState({
    name: entry?.name ?? '',
    description: entry?.description ?? '',
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
    setIsSaving(true);
    try {
      // Determinar endpoint y método según si es edición o creación
      const endpoint = isEdit ? route('offerwall.update', entry.id) : route('offerwall.store');

      const method = isEdit ? 'PUT' : 'POST';
      const csrfToken = getCookie('XSRF-TOKEN');

      const response = await fetch(endpoint, {
        method: method,
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
          ...(isEdit && { _method: 'PUT' }), // Laravel method spoofing para formularios
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
        throw new Error(data.message || `Error ${isEdit ? 'updating' : 'creating'} offerwall mix`);
      }
    } catch (error) {
      const message = isEdit ? 'Error updating offerwall mix' : 'Error creating offerwall mix';
      console.error(message + ':', error);
      modal.reject(modal.topId, message);
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>{isEdit ? 'Edit Offerwall Mix' : 'Create Offerwall Mix'}</DialogTitle>
        <DialogDescription>
          {isEdit
            ? 'Update the mix by modifying the name, description, and selecting integrations.'
            : 'Create a new mix by providing a name, description, and selecting integrations.'}
        </DialogDescription>
      </DialogHeader>
      <div className="space-y-4 py-4">
        <div className="space-y-2">
          <Label htmlFor="mix-name">Name *</Label>
          <Input
            id="mix-name"
            placeholder="Enter mix name..."
            value={formData.name}
            onChange={(e) => setFormData((prev) => ({ ...prev, name: e.target.value }))}
            disabled={isSaving}
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="mix-description">Description</Label>
          <Textarea
            id="mix-description"
            placeholder="Enter mix description (optional)..."
            value={formData.description}
            onChange={(e) => setFormData((prev) => ({ ...prev, description: e.target.value }))}
            disabled={isSaving}
            rows={3}
          />
        </div>

        <div className="space-y-2">
          <Label>Integrations *</Label>
          <Input placeholder="Search for an integration..." value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} disabled={isSaving} />
          <div className="max-h-[300px] overflow-y-auto rounded-md border">
            {isLoading ? (
              <p className="py-4 text-center">Loading integrations...</p>
            ) : (
              filteredIntegrations.map((integration) => (
                <div key={integration.id} className="flex items-center rounded-md py-2 px-3 hover:bg-muted">
                  <Checkbox
                    id={`integration-${integration.id}`}
                    checked={selectedIntegrations.has(integration.id)}
                    onCheckedChange={() => handleSelect(integration.id)}
                    disabled={isSaving}
                  />
                  <label htmlFor={`integration-${integration.id}`} className="flex-1 cursor-pointer pl-3">
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
        <Button type="button" variant="outline" onClick={handleCancel} disabled={isSaving}>
          Cancel
        </Button>
        <Button onClick={handleCreate} disabled={selectedIntegrations.size === 0 || !formData.name.trim() || isSaving}>
          {isSaving ? 'Saving...' : entry?.id ? 'Update Mix' : 'Create Mix'}
        </Button>
      </DialogFooter>
    </>
  );
}
