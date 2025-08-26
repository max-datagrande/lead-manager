import * as Icons from 'lucide-react';

export function LucideIcon({ name, className = '', size, ...rest }) {
  const IconComponent = Icons[name];

  if (!IconComponent) {
    console.warn(`Icon "${name}" not found in Icons`);
    return null;
  }

  return (
    <IconComponent className={className} size={size} {...rest} />
  );
}

export function mapIcon(data) {
  const mappedData = data.map((item) => {
    return {
      ...item,
      icon: ({ className }) => <LucideIcon name={item.iconName} className={className} size={16} />,
    };
  });
  return mappedData;
}
