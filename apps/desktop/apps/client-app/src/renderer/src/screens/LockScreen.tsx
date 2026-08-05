import { useMutation } from '@tanstack/react-query';
import { Button, Input, StatusBadge } from '@marketlens/ui';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { loginToShop } from '../services/shop-api-client';
import { useClientUiStore } from '../state/client-ui-store';

const unlockSchema = z.object({
  password: z.string().min(1),
});

type UnlockForm = z.infer<typeof unlockSchema>;

export function LockScreen() {
  const shopBaseUrl = useClientUiStore((state) => state.shopBaseUrl);
  const deviceUuid = useClientUiStore((state) => state.deviceUuid);
  const username = useClientUiStore((state) => state.username);
  const rememberDevice = useClientUiStore((state) => state.rememberDevice);
  const lockReason = useClientUiStore((state) => state.lockReason);
  const setPermissions = useClientUiStore((state) => state.setPermissions);
  const setRoles = useClientUiStore((state) => state.setRoles);
  const setCurrentUser = useClientUiStore((state) => state.setCurrentUser);
  const setSessionState = useClientUiStore((state) => state.setSessionState);
  const clearLock = useClientUiStore((state) => state.clearLock);
  const notify = useClientUiStore((state) => state.notify);
  const form = useForm<UnlockForm>({
    defaultValues: {
      password: '',
    },
  });

  const unlock = useMutation({
    mutationFn: (input: UnlockForm) => {
      if (!username || !deviceUuid) {
        throw new Error('Authentication failed.');
      }

      return loginToShop({
        shopBaseUrl,
        identity: username,
        password: input.password,
        deviceUuid,
        rememberDevice,
      });
    },
    onSuccess: async (session) => {
      setPermissions(session.permissions);
      setRoles(session.roles);
      setCurrentUser(session.user);
      setSessionState('active');
      await window.marketlensClient.session.save({
        shopBaseUrl,
        userUuid: session.userUuid,
        username: session.username,
        deviceUuid: session.deviceUuid,
        token: session.token,
        refreshToken: session.refreshToken,
        permissions: session.permissions,
        roles: session.roles,
        rememberDevice: session.rememberDevice,
        accountStatus: session.accountStatus,
        passwordChangeRequired: session.passwordChangeRequired,
        expiresAt: session.expiresAt,
      });
      clearLock();
      notify({
        tone: 'success',
        title: 'Unlocked',
        message: 'Workstation session restored.',
      });
    },
    onError: () => {
      notify({
        tone: 'danger',
        title: 'Unlock failed',
        message: 'Authentication failed. Try again.',
      });
    },
  });

  return (
    <section className="grid min-h-screen place-items-center p-6">
      <form className="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-sm" onSubmit={form.handleSubmit((values) => unlock.mutate(unlockSchema.parse(values)))}>
        <StatusBadge tone="warning">Locked</StatusBadge>
        <h1 className="mt-4 text-xl font-semibold">Workstation locked</h1>
        <p className="mt-2 text-sm text-muted">{lockReason ?? 'This workstation was locked due to inactivity.'}</p>

        <div className="mt-5 grid gap-3">
          <Input label="Password" type="password" autoComplete="current-password" {...form.register('password')} />
        </div>

        <div className="mt-5 flex justify-end">
          <Button type="submit" disabled={unlock.isPending}>Unlock</Button>
        </div>
      </form>
    </section>
  );
}
