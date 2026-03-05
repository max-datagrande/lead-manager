// Configuración centralizada de iconos
import { Badge, BadgeAlert, BadgeCheck, Edit, Plus, Trash2 } from 'lucide-react';

export const AVAILABLE_ICONS = {
  // Status icons
  Badge: Badge,
  BadgeCheck: BadgeCheck,
  BadgeAlert: BadgeAlert,

  // Action icons (para futuro uso)
  Plus: Plus,
  Edit: Edit,
  Trash2: Trash2,
};

// Función helper para validar iconos en el backend
export const getAvailableIconNames = () => Object.keys(AVAILABLE_ICONS);
