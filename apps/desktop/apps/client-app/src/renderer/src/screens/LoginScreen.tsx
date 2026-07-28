import { useMutation } from '@tanstack/react-query';
import { Button, Input, StatusBadge } from '@marketlens/ui';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { loginToShop } from '../services/shop-api-client';
import { useClientUiStore } from '../state/client-ui-store';

const loginFormSchema = z.object({
  username: z.string().min(1),
  password: z.string().min(1),
});

type LoginForm = z.infer<typeof loginFormSchema>;

export function LoginScreen() {
  const shopBaseUrl = useClientUiStore((state) => state.shopBaseUrl);
  const deviceUuid = useClientUiStore((state) => state.deviceUuid);
  const setPermissions = useClientUiStore((state) => state.setPermissions);
  const setScreen = useClientUiStore((state) => state.setScreen);
  const notify = useClientUiStore((state) => state.notify);
  const form = useForm<LoginForm>({
    defaultValues: {
      username: '',
      password: '',
    },
  });

  const login = useMutation({
    mutationFn: (input: LoginForm) => loginToShop({ ...input, shopBaseUrl, deviceUuid }),
    onSuccess: async (session) => {
      setPermissions(session.permissions);
      await window.marketlensClient.session.save({
        ...session,
        shopBaseUrl,
        deviceUuid: deviceUuid ?? 'unpaired-device',
      });
      notify({
        tone: 'success',
        title: 'Signed in',
        message: 'Features are visible according to Shop App permissions.',
      });
      setScreen('workspace');
    },
    onError: (error) => {
      notify({
        tone: 'danger',
        title: 'Login failed',
        message: error instanceof Error ? error.message : 'The Shop App rejected this login.',
      });
    },
  });

  return (
    <section className="grid min-h-screen place-items-center p-6">
      <form
        className="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-sm"
        onSubmit={form.handleSubmit((values) => login.mutate(loginFormSchema.parse(values)))}
      >
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="text-xl font-semibold">Sign in</h1>
            <p className="mt-1 text-sm text-muted">Authentication is handled by the connected Shop App.</p>
          </div>
          <StatusBadge tone="information">Shop auth</StatusBadge>
        </div>

        <div className="mt-5 grid gap-3">
          <Input label="Username" autoComplete="username" {...form.register('username')} />
          <Input label="Password" type="password" autoComplete="current-password" {...form.register('password')} />
        </div>

        <div className="mt-5 flex justify-between gap-3">
          <Button variant="ghost" onClick={() => setScreen('pairing')}>
            Back
          </Button>
          <Button type="submit" disabled={login.isPending}>
            Sign in
          </Button>
        </div>
      </form>
    </section>
  );
}
