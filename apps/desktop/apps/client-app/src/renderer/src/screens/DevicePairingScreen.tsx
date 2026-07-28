import { useMutation } from '@tanstack/react-query';
import { Button, Input, StatusBadge } from '@marketlens/ui';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { pairDevice } from '../services/shop-api-client';
import { useClientUiStore } from '../state/client-ui-store';

const pairingFormSchema = z.object({
  deviceName: z.string().min(1),
  pairingCode: z.string().min(1),
});

type PairingForm = z.infer<typeof pairingFormSchema>;

export function DevicePairingScreen() {
  const shopBaseUrl = useClientUiStore((state) => state.shopBaseUrl);
  const setDeviceUuid = useClientUiStore((state) => state.setDeviceUuid);
  const setScreen = useClientUiStore((state) => state.setScreen);
  const notify = useClientUiStore((state) => state.notify);
  const form = useForm<PairingForm>({
    defaultValues: {
      deviceName: 'Client workstation',
      pairingCode: '',
    },
  });

  const pairing = useMutation({
    mutationFn: (input: PairingForm) => pairDevice(shopBaseUrl, input),
    onSuccess: (result) => {
      if (result.data.deviceUuid) {
        setDeviceUuid(result.data.deviceUuid);
      }
      notify({
        tone: result.data.paired ? 'success' : 'information',
        title: result.data.paired ? 'Device paired' : 'Pairing requested',
        message: result.data.reason ?? 'Continue after the Shop App approves this workstation.',
      });
      setScreen('login');
    },
    onError: (error) => {
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
          <Button variant="ghost" onClick={() => setScreen('connection')}>
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
