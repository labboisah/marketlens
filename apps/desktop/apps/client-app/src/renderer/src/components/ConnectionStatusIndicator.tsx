import { ServerConnectionIndicator } from '@marketlens/ui';
import { useClientUiStore } from '../state/client-ui-store';

export function ConnectionStatusIndicator() {
  const connectionState = useClientUiStore((state) => state.connectionState);
  return <ServerConnectionIndicator state={connectionState} />;
}
