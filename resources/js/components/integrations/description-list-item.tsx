import React from 'react';

interface Props {
  term: string;
  children: React.ReactNode;
}

export function DescriptionListItem({ term, children }: Props) {
  return (
    <>
      <p className="text-sm font-medium text-gray-500">{term}</p>
      <p className="text-sm text-gray-900">{children}</p>
    </>
  );
}
export function DescriptionList({ children }: { children: React.ReactNode }) {
  return <div className="grid grid-cols-[auto_1fr] items-start gap-x-4 gap-y-2">{children}</div>;
}
