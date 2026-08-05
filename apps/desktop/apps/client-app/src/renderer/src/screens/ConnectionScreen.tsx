import { useMutation, useQuery } from '@tanstack/react-query';
import { Button, Input, StatusBadge } from '@marketlens/ui';
import { useEffect, useState, type ChangeEvent } from 'react';
import { QrCode, Search, ServerCrash, Wifi } from 'lucide-react';
import {
  clearConnectionSettings,
  discoverShopServers,
  isBusinessChange,
  loadConnectionSettings,
  recordSuccessfulConnection,
  requiresPinnedIdentityViolation,
  saveManualServerUrl,
  testShopServerConnection,
  type ConnectionTestResult,
  type DiscoveredServer,
} from '../services/server-discovery-service';
import { useClientUiStore } from '../state/client-ui-store';

type ConnectionView =
  | 'find_shop_app'
  | 'manual_connection'
  | 'connection_test'
  | 'server_found'
  | 'server_unavailable'
  | 'connection_lost';

function hostPort(urlText: string): { host: string; port: string } {
  try {
    const parsed = new URL(urlText);
    return {
      host: parsed.hostname,
      port: parsed.port || '4090',
    };
  } catch {
    return {
      host: '127.0.0.1',
      port: '4090',
    };
  }
}

function normalizeHost(host: string): string {
  return host.trim().replace(/^https?:\/\//, '').replace(/\/$/, '');
}

function toBaseUrl(host: string, port: string): string {
  const cleanHost = normalizeHost(host);
  const cleanPort = Number(port);
  const resolvedPort = Number.isFinite(cleanPort) && cleanPort > 0 ? cleanPort : 4090;
  return `http://${cleanHost}:${resolvedPort}`;
}

export function ConnectionScreen() {
  const [connectionView, setConnectionView] = useState<ConnectionView>('find_shop_app');
  const [manualHost, setManualHost] = useState('127.0.0.1');
  const [manualPort, setManualPort] = useState('4090');
  const [manualServerName, setManualServerName] = useState('');
  const [pairingCodeEntry, setPairingCodeEntry] = useState('');
  const [requirePinnedServerMatch, setRequirePinnedServerMatch] = useState(false);
  const [pinnedIdentitySummary, setPinnedIdentitySummary] = useState('Not pinned');
  const [lastTestResult, setLastTestResult] = useState<ConnectionTestResult | null>(null);
  const [selectedServer, setSelectedServer] = useState<DiscoveredServer | null>(null);
  const setConnectionState = useClientUiStore((state) => state.setConnectionState);
  const setShopStatus = useClientUiStore((state) => state.setShopStatus);
  const setLastSuccessfulConnectionAt = useClientUiStore((state) => state.setLastSuccessfulConnectionAt);
  const setShopBaseUrl = useClientUiStore((state) => state.setShopBaseUrl);
  const setDeviceAuthorizationState = useClientUiStore((state) => state.setDeviceAuthorizationState);
  const setSessionState = useClientUiStore((state) => state.setSessionState);
  const setPermissions = useClientUiStore((state) => state.setPermissions);
  const setRoles = useClientUiStore((state) => state.setRoles);
  const setCurrentUser = useClientUiStore((state) => state.setCurrentUser);
  const applicationState = useClientUiStore((state) => state.applicationState);
  const lastSuccessfulConnectionAt = useClientUiStore((state) => state.lastSuccessfulConnectionAt);
  const notify = useClientUiStore((state) => state.notify);

  useEffect(() => {
    let cancelled = false;

    const loadSavedUrl = async () => {
      const settings = await loadConnectionSettings();
      const parts = hostPort(settings.currentServerUrl);
      if (!cancelled) {
        setManualHost(parts.host);
        setManualPort(parts.port);
        setLastSuccessfulConnectionAt(settings.lastSuccessfulConnectionAt);
        setRequirePinnedServerMatch(settings.requirePinnedServerMatch);
        setPinnedIdentitySummary(settings.pinnedServerIdentity?.shopServerName ?? 'Not pinned');
      }
    };

    void loadSavedUrl();

    return () => {
      cancelled = true;
    };
  }, []);

  const discovery = useQuery({
    queryKey: ['shop-server-discovery'],
    queryFn: discoverShopServers,
  });

  const connect = useMutation({
    mutationFn: async (baseUrl: string) => {
      setConnectionState('connecting');
      setConnectionView('connection_test');
      return testShopServerConnection(baseUrl);
    },
    onSuccess: async (result, baseUrl) => {
      setLastTestResult(result);
      if (!result.ok || !result.status) {
        setConnectionState('disconnected');
        setConnectionView(applicationState === 'disconnected' ? 'connection_lost' : 'server_unavailable');
        setShopStatus(undefined);
        notify({
          tone: 'danger',
          title: 'Server unavailable',
          message: result.reason ?? 'The Shop App could not be reached on that address.',
        });
        return;
      }

      const settings = await loadConnectionSettings();
      const normalizedCurrentUrl = settings.currentServerUrl.trim().replace(/\/+$/, '');
      const normalizedTargetUrl = baseUrl.trim().replace(/\/+$/, '');

      if (normalizedCurrentUrl && normalizedCurrentUrl !== normalizedTargetUrl) {
        const confirmedSwitch = window.confirm(
          'Changing the connected Shop App requires explicit confirmation and may require this workstation to re-pair. Continue?',
        );

        if (!confirmedSwitch) {
          setConnectionState('disconnected');
          setConnectionView('find_shop_app');
          return;
        }
      }

      if (requiresPinnedIdentityViolation(settings, result.status)) {
        setConnectionState('disconnected');
        setConnectionView('server_unavailable');
        notify({
          tone: 'danger',
          title: 'Server identity mismatch',
          message: 'Pinned server identity verification failed. Connection was blocked.',
        });
        return;
      }

      const businessChanged = isBusinessChange(settings.currentServerIdentity, result.status);
      if (businessChanged) {
        await window.marketlensClient.session.clear();
        setPermissions([]);
        setRoles([]);
        setCurrentUser(undefined);
        setDeviceAuthorizationState('unpaired');
        setSessionState('missing');
      }

      await saveManualServerUrl(baseUrl);
      await recordSuccessfulConnection(baseUrl, result.status);

      const storedSettings = await loadConnectionSettings();
      setShopBaseUrl(baseUrl);
      setShopStatus(result.status);
      setLastSuccessfulConnectionAt(storedSettings.lastSuccessfulConnectionAt);
      setConnectionState('connected');
      setConnectionView('server_found');
      notify({
        tone: 'success',
        title: 'Server found',
        message: `${result.status.shopServerName} is reachable. Proceed with pairing and sign-in.`,
      });
    },
    onError: (error) => {
      setConnectionState('disconnected');
      setShopStatus(undefined);
      setConnectionView('server_unavailable');
      notify({
        tone: 'danger',
        title: 'Connection failed',
        message: error instanceof Error ? error.message : 'The Shop App could not be reached.',
      });
    },
  });

  useEffect(() => {
    if (connect.isPending) {
      setConnectionState('connecting');
    }
  }, [connect.isPending, setConnectionState]);

  useEffect(() => {
    if (applicationState === 'disconnected' && lastSuccessfulConnectionAt) {
      setConnectionView('connection_lost');
    }
  }, [applicationState, lastSuccessfulConnectionAt]);

  const runManualTest = () => {
    const baseUrl = toBaseUrl(manualHost, manualPort);
    connect.mutate(baseUrl);
  };

  const connectToDiscovered = (server: DiscoveredServer) => {
    setSelectedServer(server);
    connect.mutate(server.baseUrl);
  };

  const availableServers = (discovery.data ?? []).filter((server) => server.status === 'available');
  const unavailableServers = (discovery.data ?? []).filter((server) => server.status === 'unavailable');

  const connectionStatusLabel = connect.isPending
    ? 'Testing connection'
    : connectionView === 'server_found'
      ? 'Server found'
      : connectionView === 'connection_lost'
        ? 'Connection lost'
        : connectionView === 'server_unavailable'
          ? 'Server unavailable'
          : 'Not connected';

  const activeBaseUrl = toBaseUrl(manualHost, manualPort);

  return (
    <section className="grid min-h-screen place-items-center p-6">
      <div className="w-full max-w-4xl rounded-lg border border-border bg-surface p-6 shadow-sm">
        <div className="flex items-center justify-between gap-4">
          <div>
            <h1 className="text-xl font-semibold">Find Shop App</h1>
            <p className="mt-1 text-sm text-muted">Discover, test, and securely connect this workstation to one authorized Shop App.</p>
          </div>
          <StatusBadge tone={connect.isPending ? 'information' : 'offline'}>
            <Wifi className="h-4 w-4" />
            {connectionStatusLabel}
          </StatusBadge>
        </div>

        <div className="mt-6 flex flex-wrap gap-2">
          <Button variant={connectionView === 'find_shop_app' ? 'primary' : 'ghost'} onClick={() => setConnectionView('find_shop_app')}>
            <Search className="h-4 w-4" />
            Find Shop App
          </Button>
          <Button variant={connectionView === 'manual_connection' ? 'primary' : 'ghost'} onClick={() => setConnectionView('manual_connection')}>
            Manual connection
          </Button>
          <Button variant="ghost" onClick={() => setConnectionView('connection_test')}>
            Connection test
          </Button>
          <Button variant="ghost" onClick={() => setConnectionView('find_shop_app')}>
            Change server
          </Button>
          <Button
            variant="ghost"
            onClick={async () => {
              await clearConnectionSettings();
              setDeviceAuthorizationState('unpaired');
              setSessionState('missing');
              setLastSuccessfulConnectionAt(undefined);
              notify({
                tone: 'information',
                title: 'Connection settings reset',
                message: 'Saved server identity and recent list were cleared.',
              });
            }}
          >
            Reset server settings
          </Button>
        </div>

        {connectionView === 'find_shop_app' ? (
          <div className="mt-6 grid gap-5 lg:grid-cols-2">
            <section className="rounded-md border border-border p-4">
              <h2 className="text-sm font-semibold">Automatic LAN discovery</h2>
              <p className="mt-1 text-sm text-muted">Discovery runs where local network probing is available.</p>
              <div className="mt-3 flex gap-2">
                <Button onClick={() => discovery.refetch()} disabled={discovery.isFetching}>
                  Refresh discovery
                </Button>
                <StatusBadge tone={discovery.isFetching ? 'information' : 'neutral'}>
                  {discovery.isFetching ? 'Searching LAN' : `${availableServers.length} server(s) available`}
                </StatusBadge>
              </div>
              <div className="mt-3 grid gap-2">
                {availableServers.map((server) => (
                  <button
                    key={server.id}
                    type="button"
                    className="rounded-md border border-border p-3 text-left hover:bg-surface-muted"
                    onClick={() => connectToDiscovered(server)}
                  >
                    <span className="block font-medium">{server.name}</span>
                    <span className="text-xs text-muted">{server.baseUrl}</span>
                    <span className="block text-xs text-muted">
                      {server.businessName ?? 'Unknown business'} • {server.branchName ?? 'Unknown branch'} • {server.deviceName ?? 'Unknown device'}
                    </span>
                  </button>
                ))}
                {availableServers.length === 0 ? <p className="text-sm text-muted">No reachable Shop App discovered yet.</p> : null}
              </div>
            </section>

            <section className="rounded-md border border-border p-4">
              <h2 className="text-sm font-semibold">Recent server list</h2>
              <p className="mt-1 text-sm text-muted">Reconnect securely to previously verified servers.</p>
              <div className="mt-3 rounded-md border border-border p-3">
                <label className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={requirePinnedServerMatch}
                    onChange={async (event) => {
                      const checked = event.target.checked;
                      setRequirePinnedServerMatch(checked);
                      const settings = await loadConnectionSettings();
                      await window.marketlensClient.connectionSettings.save({
                        ...settings,
                        requirePinnedServerMatch: checked,
                      });
                    }}
                  />
                  Enforce pinned server identity verification
                </label>
                <p className="mt-1 text-xs text-muted">Pinned identity: {pinnedIdentitySummary}</p>
              </div>
              <div className="mt-3 grid gap-2">
                {(discovery.data ?? [])
                  .filter((server) => Boolean(server.lastSuccessfulConnectionAt))
                  .map((server) => (
                    <button
                      key={`recent:${server.id}`}
                      type="button"
                      className="rounded-md border border-border p-3 text-left hover:bg-surface-muted"
                      onClick={() => connectToDiscovered(server)}
                    >
                      <span className="block font-medium">{server.name}</span>
                      <span className="block text-xs text-muted">{server.baseUrl}</span>
                      <span className="block text-xs text-muted">Last success: {server.lastSuccessfulConnectionAt}</span>
                    </button>
                  ))}
                {!(discovery.data ?? []).some((server) => Boolean(server.lastSuccessfulConnectionAt)) ? (
                  <p className="text-sm text-muted">No recent servers recorded yet.</p>
                ) : null}
              </div>
            </section>
          </div>
        ) : null}

        {connectionView === 'manual_connection' ? (
          <div className="mt-6 grid gap-4 lg:grid-cols-2">
            <section className="rounded-md border border-border p-4">
              <h2 className="text-sm font-semibold">Manual connection</h2>
              <div className="mt-3 grid gap-3">
                <Input
                  label="Shop server name"
                  value={manualServerName}
                  onChange={(event: ChangeEvent<HTMLInputElement>) => setManualServerName(event.target.value)}
                  placeholder="Main Branch Shop Server"
                />
                <Input
                  label="Server IP"
                  value={manualHost}
                  onChange={(event: ChangeEvent<HTMLInputElement>) => setManualHost(event.target.value)}
                  placeholder="192.168.1.20"
                />
                <Input
                  label="Server port"
                  value={manualPort}
                  onChange={(event: ChangeEvent<HTMLInputElement>) => setManualPort(event.target.value)}
                  placeholder="4090"
                />
                <StatusBadge tone="information">Target URL: {activeBaseUrl}</StatusBadge>
                <Button onClick={runManualTest} disabled={connect.isPending}>
                  Test and connect
                </Button>
              </div>
            </section>

            <section className="rounded-md border border-border p-4">
              <h2 className="text-sm font-semibold">QR or pairing-code entry foundation</h2>
              <p className="mt-1 text-sm text-muted">Use pairing code now. QR scanning bridge can be added to preload later.</p>
              <div className="mt-3 grid gap-3">
                <Input
                  label="Pairing code"
                  value={pairingCodeEntry}
                  onChange={(event: ChangeEvent<HTMLInputElement>) => setPairingCodeEntry(event.target.value)}
                  placeholder="Enter pairing code from Shop App"
                />
                <Button variant="ghost" disabled>
                  <QrCode className="h-4 w-4" />
                  Scan QR (foundation)
                </Button>
              </div>
            </section>
          </div>
        ) : null}

        {connectionView === 'connection_test' ? (
          <section className="mt-6 rounded-md border border-border p-4">
            <h2 className="text-sm font-semibold">Connection test</h2>
            {connect.isPending ? <p className="mt-2 text-sm text-muted">Testing {selectedServer?.baseUrl ?? activeBaseUrl}...</p> : null}
            {!connect.isPending && lastTestResult ? (
              <div className="mt-3 grid gap-2">
                <StatusBadge tone={lastTestResult.ok ? 'success' : 'danger'}>
                  {lastTestResult.ok ? 'Server found' : 'Server unavailable'}
                </StatusBadge>
                <p className="text-sm text-muted">Address: {lastTestResult.baseUrl}</p>
                <p className="text-sm text-muted">{lastTestResult.ok ? 'Connection test succeeded.' : (lastTestResult.reason ?? 'Connection test failed.')}</p>
                <div className="flex gap-2">
                  <Button variant="ghost" onClick={() => setConnectionView('find_shop_app')}>Retry</Button>
                  <Button variant="ghost" onClick={() => setConnectionView('manual_connection')}>Change server</Button>
                </div>
              </div>
            ) : null}
          </section>
        ) : null}

        {connectionView === 'server_found' && lastTestResult?.status ? (
          <section className="mt-6 rounded-md border border-success/30 bg-success/5 p-4">
            <h2 className="text-sm font-semibold">Server found</h2>
            <p className="mt-2 text-sm text-muted">Business: {lastTestResult.status.businessName ?? 'Unknown'} • Branch: {lastTestResult.status.branchName ?? 'Unknown'}</p>
            <p className="text-sm text-muted">Device: {lastTestResult.status.shopDeviceName} • {lastTestResult.status.serverIp}:{lastTestResult.status.serverPort}</p>
            <div className="mt-3 flex gap-2">
              <Button variant="ghost" onClick={() => setConnectionView('manual_connection')}>Change server</Button>
              <Button
                variant="ghost"
                onClick={async () => {
                  const settings = await loadConnectionSettings();
                  await window.marketlensClient.connectionSettings.save({
                    ...settings,
                    pinnedServerIdentity: {
                      businessUuid: lastTestResult.status?.businessUuid ?? undefined,
                      businessName: lastTestResult.status?.businessName ?? undefined,
                      branchUuid: lastTestResult.status?.branchUuid ?? undefined,
                      branchName: lastTestResult.status?.branchName ?? undefined,
                      shopDeviceUuid: lastTestResult.status?.shopDeviceUuid ?? undefined,
                      shopDeviceName: lastTestResult.status?.shopDeviceName,
                      shopServerName: lastTestResult.status?.shopServerName,
                      serverIp: lastTestResult.status?.serverIp,
                      serverPort: lastTestResult.status?.serverPort,
                    },
                  });
                  setPinnedIdentitySummary(lastTestResult.status?.shopServerName ?? 'Pinned server');
                  notify({
                    tone: 'success',
                    title: 'Server identity pinned',
                    message: 'Future reconnection can verify this Shop App identity when enforcement is enabled.',
                  });
                }}
              >
                Pin server identity
              </Button>
              <Button variant="primary" onClick={() => setConnectionView('find_shop_app')}>Continue</Button>
            </div>
          </section>
        ) : null}

        {connectionView === 'server_unavailable' ? (
          <section className="mt-6 rounded-md border border-danger/30 bg-danger/5 p-4">
            <h2 className="text-sm font-semibold">Server unavailable</h2>
            <p className="mt-2 text-sm text-muted">{lastTestResult?.reason ?? 'The selected server could not be reached.'}</p>
            <div className="mt-3 flex gap-2">
              <Button onClick={() => setConnectionView('connection_test')}>Retry</Button>
              <Button variant="ghost" onClick={() => setConnectionView('manual_connection')}>Change server</Button>
            </div>
          </section>
        ) : null}

        {connectionView === 'connection_lost' ? (
          <section className="mt-6 rounded-md border border-warning/30 bg-warning/5 p-4">
            <h2 className="text-sm font-semibold">Connection lost</h2>
            <p className="mt-2 text-sm text-muted">Last successful connection: {lastSuccessfulConnectionAt ?? 'Unknown'}</p>
            <p className="text-sm text-muted">The Shop App became unavailable after a previous successful connection.</p>
            <div className="mt-3 flex gap-2">
              <Button onClick={() => setConnectionView('connection_test')}>Retry</Button>
              <Button variant="ghost" onClick={() => setConnectionView('manual_connection')}>Change server</Button>
            </div>
          </section>
        ) : null}

        {unavailableServers.length > 0 && connectionView === 'find_shop_app' ? (
          <section className="mt-6 rounded-md border border-border p-4">
            <h2 className="text-sm font-semibold">Unavailable discovered endpoints</h2>
            <div className="mt-2 grid gap-2">
              {unavailableServers.slice(0, 6).map((server) => (
                <div key={server.id} className="rounded-md border border-border p-3">
                  <div className="flex items-center gap-2">
                    <ServerCrash className="h-4 w-4" />
                    <span className="font-medium">{server.name}</span>
                  </div>
                  <p className="mt-1 text-xs text-muted">{server.baseUrl}</p>
                </div>
              ))}
            </div>
          </section>
        ) : null}

        <div className="mt-6 text-xs text-muted">
          Do not connect this workstation to multiple businesses silently. Switching to another business requires explicit confirmation and can trigger re-pairing.
        </div>
      </div>
    </section>
  );
}
