import { useMutation, useQuery } from '@tanstack/react-query';
import { Button, Input, StatusBadge } from '@marketlens/ui';
import { useEffect, useState } from 'react';
import { Wifi } from 'lucide-react';
import { checkShopStatus } from '../services/shop-api-client';
import { discoverShopServers, loadManualServerUrl, saveManualServerUrl } from '../services/server-discovery-service';
import { useClientUiStore } from '../state/client-ui-store';

export function ConnectionScreen() {
  const [manualUrl, setManualUrl] = useState(loadManualServerUrl());
  const setConnectionState = useClientUiStore((state) => state.setConnectionState);
  const setShopBaseUrl = useClientUiStore((state) => state.setShopBaseUrl);
  const setScreen = useClientUiStore((state) => state.setScreen);
  const notify = useClientUiStore((state) => state.notify);

  const discovery = useQuery({
    queryKey: ['shop-server-discovery'],
    queryFn: discoverShopServers,
  });

  const connect = useMutation({
    mutationFn: async (baseUrl: string) => {
      setConnectionState('connecting');
      return checkShopStatus(baseUrl);
    },
    onSuccess: (_result, baseUrl) => {
      saveManualServerUrl(baseUrl);
      setShopBaseUrl(baseUrl);
      setConnectionState('connected');
      setScreen('pairing');
      notify({
        tone: 'success',
        title: 'Shop App reachable',
        message: 'Pair this workstation before login.',
      });
    },
    onError: (error) => {
      setConnectionState('disconnected');
      notify({
        tone: 'danger',
        title: 'Connection failed',
        message: error instanceof Error ? error.message : 'The Shop App could not be reached.',
      });
    },
  });

  useEffect(() => {
    setConnectionState(connect.isPending ? 'connecting' : 'disconnected');
  }, [connect.isPending, setConnectionState]);

  return (
    <section className="grid min-h-screen place-items-center p-6">
      <div className="w-full max-w-xl rounded-lg border border-border bg-surface p-6 shadow-sm">
        <div className="flex items-center justify-between gap-4">
          <div>
            <h1 className="text-xl font-semibold">Connect to Shop App</h1>
            <p className="mt-1 text-sm text-muted">Choose a discovered server or enter the Shop App address manually.</p>
          </div>
          <StatusBadge tone={connect.isPending ? 'information' : 'offline'}>
            <Wifi className="h-4 w-4" />
            {connect.isPending ? 'Connecting' : 'Not connected'}
          </StatusBadge>
        </div>

        <div className="mt-6 grid gap-3">
          <Input
            label="Manual Shop App URL"
            value={manualUrl}
            onChange={(event) => setManualUrl(event.target.value)}
            placeholder="http://192.168.1.20:4090"
          />
          <Button onClick={() => connect.mutate(manualUrl)} disabled={connect.isPending}>
            Connect manually
          </Button>
        </div>

        <div className="mt-6">
          <h2 className="text-sm font-semibold">Discovered servers</h2>
          <div className="mt-2 grid gap-2">
            {(discovery.data ?? []).map((server) => (
              <button
                key={server.id}
                type="button"
                className="rounded-md border border-border p-3 text-left hover:bg-surface-muted"
                onClick={() => connect.mutate(server.baseUrl)}
              >
                <span className="block font-medium">{server.name}</span>
                <span className="text-sm text-muted">{server.baseUrl}</span>
              </button>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
