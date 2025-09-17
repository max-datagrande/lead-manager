import React from 'react';

interface Props {
  term: string;
  children: React.ReactNode;
}

export function DescriptionListItem({ term, children }: Props) {
  return (
    <div className="grid grid-cols-3 gap-4 py-2">
      <dt className="text-sm font-medium text-gray-500">{term}</dt>
      <dd className="col-span-2 text-sm text-gray-900">{children}</dd>
    </div>
  );
}
