import { AVAILABLE_ICONS } from '@/config/icons';

export function LucideIcon({ name, className = '', size, ...rest }) {
  const IconComponent = AVAILABLE_ICONS[name];

  if (!IconComponent) {
    console.warn(`Icon "${name}" not available. Available icons:`, Object.keys(AVAILABLE_ICONS));
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
