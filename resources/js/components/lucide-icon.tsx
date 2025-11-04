import { AVAILABLE_ICONS } from '@/config/icons';

interface Props {
  name: keyof typeof AVAILABLE_ICONS;
  className?: string;
  size?: number;
}

export function LucideIcon({ name, className = '', size = 16, ...rest }: Props) {
  const IconComponent = AVAILABLE_ICONS[name];

  if (!IconComponent) {
    console.warn(`Icon "${name}" not available. Available icons:`, Object.keys(AVAILABLE_ICONS));
    return null;
  }

  return <IconComponent className={className} size={size} {...rest} />;
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
