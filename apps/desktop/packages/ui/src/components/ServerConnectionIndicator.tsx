import * as React from 'react';
import { Wifi, WifiOff } from 'lucide-react';
import { StatusBadge } from './StatusBadge';

export type ServerConnectionState = 'connected' | 'connecting' | 'disconnected';

export function ServerConnectionIndicator({ state }: { state: ServerConnectionState }) {
  const config = {
    connected: { tone: 'success' as const, label: 'Server connected', Icon: Wifi },
    connecting: { tone: 'information' as const, label: 'Connecting to server', Icon: Wifi },
    disconnected: { tone: 'offline' as const, label: 'Server offline', Icon: WifiOff },
  }[state];
  const Icon = config.Icon;

  return (
    <StatusBadge tone={config.tone}>
      <Icon className="h-4 w-4" aria-hidden="true" />
      {config.label}
    </StatusBadge>
  );
}
