import { useMutation } from '@tanstack/react-query';
import { Button, Input, StatusBadge } from '@marketlens/ui';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { checkDeviceStatus, pairDevice } from '../services/shop-api-client';
import { useClientUiStore } from '../state/client-ui-store';

const pairingFormSchema = z.object({
  deviceName: z.string().min(1),
  pairingCode: z.string().min(1),
});

type PairingForm = z.infer<typeof pairingFormSchema>;

export function DevicePairingScreen() {
  const shopBaseUrl = useClientUiStore((state) => state.shopBaseUrl);
  const setDeviceUuid = useClientUiStore((state) => state.setDeviceUuid);
  const setActiveRole = useClientUiStore((state) => state.setActiveRole);
  const setDeviceAuthorizationState = useClientUiStore((state) => state.setDeviceAuthorizationState);
  const notify = useClientUiStore((state) => state.notify);
  const form = useForm<PairingForm>({
    defaultValues: {
      deviceName: 'Client workstation',
      pairingCode: '',
    },
  });

  const pairing = useMutation({
    mutationFn: (input: PairingForm) => pairDevice(shopBaseUrl, input),
    onSuccess: async (result, values) => {
      if (result.deviceUuid) {
        setDeviceUuid(result.deviceUuid);
      }

      await window.marketlensClient.preferences.save({
        ...(await window.marketlensClient.preferences.load()),
        lastDeviceName: values.deviceName,
      });

      if (!result.paired || !result.deviceUuid) {
        setDeviceAuthorizationState('pending');
        notify({
          tone: 'information',
          title: 'Pairing requested',
          message: result.reason ?? 'Wait for the Shop App to approve this workstation before signing in.',
        });
        return;
      }

      const deviceStatus = await checkDeviceStatus(shopBaseUrl, result.deviceUuid);
      setDeviceAuthorizationState(deviceStatus);
      if (deviceStatus !== 'paired' && deviceStatus !== 'authorized') {
        notify({
          tone: 'warning',
          title: 'Pairing not confirmed',
          message: 'The Shop App has not yet confirmed that this workstation can sign in.',
        });
        return;
      }

      const preferences = await window.marketlensClient.preferences.load();
      setActiveRole(preferences.preferredRole);
      notify({
        tone: 'success',
        title: 'Device paired',
        message: result.reason ?? 'This workstation can now sign in through the Shop App.',
      });
    },
    onError: (error) => {
      setDeviceAuthorizationState('unpaired');
      notify({
        tone: 'danger',
        title: 'Pairing failed',
        message: error instanceof Error ? error.message : 'The Shop App rejected the pairing request.',
      });
    },
  });

  return (
    <section className="grid min-h-screen place-items-center p-6">
      <form
        className="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-sm"
        onSubmit={form.handleSubmit((values) => {
          const parsed = pairingFormSchema.parse(values);
          pairing.mutate(parsed);
        })}
      >
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="text-xl font-semibold">Pair this device</h1>
            <p className="mt-1 text-sm text-muted">The Shop App must authorize this workstation before login.</p>
          </div>
          <StatusBadge tone="information">Pairing</StatusBadge>
        </div>

        <div className="mt-5 grid gap-3">
          <Input label="Device name" {...form.register('deviceName')} />
          <Input label="Pairing code" {...form.register('pairingCode')} />
        </div>

        <div className="mt-5 flex justify-between gap-3">
          <Button variant="ghost" onClick={() => setDeviceAuthorizationState('unpaired')}>
            Back
          </Button>
          <Button type="submit" disabled={pairing.isPending}>
            Request pairing
          </Button>
        </div>
      </form>
    </section>
  );
}
