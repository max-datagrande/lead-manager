import React, { useState } from 'react'
import { useModal } from '@/hooks/use-modal'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'

/**
 * Examples of using the useModal hook
 * This file demonstrates different ways to use the modal system
 */

// Example 1: Simple modal with JSX content
export function SimpleModalExample() {
  const modal = useModal()
  const openSimpleModal = () => {
    modal.open(
      <>
        <DialogTitle className="sm:max-w-lg">Simple Modal</DialogTitle>
        <div className="p-4">
          <h2 className="mb-2 text-lg font-semibold">Simple Modal</h2>
          <p className="mb-4 text-sm text-muted-foreground">This is an example of a simple modal with arbitrary JSX content.</p>
          <Button onClick={() => modal.close()}>Close</Button>
        </div>
      </>,
    );
  }

  return (
    <Button onClick={openSimpleModal}>
      Open Simple Modal
    </Button>
  )
}

// Example 2: Confirmation modal
export function ConfirmModalExample() {
  const modal = useModal()
  const [result, setResult] = useState<string>('')

  const handleConfirm = async () => {
    const confirmed = await modal.confirm({
      title: 'Delete element',
      description: 'This action cannot be undone. Are you sure you want to continue?',
      confirmText: 'Delete',
      cancelText: 'Cancel',
      destructive: true
    })

    setResult(confirmed ? 'Confirmed' : 'Cancelled')
  }

  return (
    <div className="space-y-2">
      <Button onClick={handleConfirm} variant="destructive">
        Delete Element
      </Button>
      {result && (
        <p className="text-sm text-muted-foreground">
          Result: {result}
        </p>
      )}
    </div>
  )
}

// Example 3: Modal with form that returns typed data
interface UserFormData {
  name: string
  email: string
}

function UserForm() {
  const modal = useModal()
  const [formData, setFormData] = useState<UserFormData>({ name: '', email: '' })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    // Simulate validation
    if (!formData.name || !formData.email) {
      alert('Please complete all fields')
      return
    }

    // Resolve the modal with form data
    modal.resolve(modal.topId!, formData)
  }

  const handleCancel = () => {
    modal.close()
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>Create User</DialogTitle>
        <DialogDescription>
          Complete the data to create a new user.
        </DialogDescription>
      </DialogHeader>

      <form onSubmit={handleSubmit} className="space-y-4 mt-4">
        <div className="space-y-2">
          <Label htmlFor="name">Name</Label>
          <Input
            id="name"
            value={formData.name}
            onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
            placeholder="Enter the name"
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="email">Email</Label>
          <Input
            id="email"
            type="email"
            value={formData.email}
            onChange={(e) => setFormData(prev => ({ ...prev, email: e.target.value }))}
            placeholder="Enter the email"
          />
        </div>

        <div className="flex justify-end gap-2 pt-4">
          <Button type="button" variant="outline" onClick={handleCancel}>
            Cancel
          </Button>
          <Button type="submit">
            Create User
          </Button>
        </div>
      </form>
    </>
  )
}

export function TypedModalExample() {
  const modal = useModal()
  const [userData, setUserData] = useState<UserFormData | null>(null)

  const openUserForm = async () => {
    try {
      const result = await modal.openAsync<UserFormData>(<UserForm />)
      setUserData(result)
    } catch (error) {
      console.log('Modal cancelled or error:', error)
    }
  }

  return (
    <div className="space-y-4">
      <Button onClick={openUserForm}>
        Create User (Typed Modal)
      </Button>

      {userData && (
        <div className="p-4 bg-muted rounded-md">
          <h3 className="font-semibold mb-2">User Created:</h3>
          <p className="text-sm">Name: {userData.name}</p>
          <p className="text-sm">Email: {userData.email}</p>
        </div>
      )}
    </div>
  )
}

// Example 4: Nested modal (modal inside another modal)
export function NestedModalExample() {
  const modal = useModal()

  const openFirstModal = () => {
    modal.open(
      <div className="p-4">
        <h2 className="text-lg font-semibold mb-4">First Modal</h2>
        <p className="text-sm text-muted-foreground mb-4">
          This is the first modal. You can open another modal from here.
        </p>
        <div className="flex gap-2">
          <Button onClick={openSecondModal}>
            Open Second Modal
          </Button>
          <Button variant="outline" onClick={() => modal.close()}>
            Close
          </Button>
        </div>
      </div>
    )
  }

  const openSecondModal = () => {
    modal.open(
      <div className="p-4">
        <h2 className="text-lg font-semibold mb-4">Second Modal</h2>
        <p className="text-sm text-muted-foreground mb-4">
          This is a nested modal. Modals stack correctly.
        </p>
        <Button onClick={() => modal.close()}>
          Close This Modal
        </Button>
      </div>
    )
  }

  return (
    <Button onClick={openFirstModal}>
      Open Nested Modal
    </Button>
  )
}

// Example 5: Component that demonstrates all examples
export function ModalExamplesDemo() {
  return (
    <div className="space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-bold mb-2">useModal Examples</h1>
        <p className="text-muted-foreground mb-6">
          Different ways to use the global modal system.
        </p>
      </div>

      <div className="grid gap-6 md:grid-cols-2">
        <div className="space-y-4">
          <h2 className="text-lg font-semibold">Simple Modal</h2>
          <SimpleModalExample />
        </div>

        <div className="space-y-4">
          <h2 className="text-lg font-semibold">Confirmation Modal</h2>
          <ConfirmModalExample />
        </div>

        <div className="space-y-4">
          <h2 className="text-lg font-semibold">Typed Form Modal</h2>
          <TypedModalExample />
        </div>

        <div className="space-y-4">
          <h2 className="text-lg font-semibold">Nested Modals</h2>
          <NestedModalExample />
        </div>
      </div>
    </div>
  )
}
