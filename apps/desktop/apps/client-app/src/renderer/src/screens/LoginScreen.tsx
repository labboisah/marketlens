import { useMutation } from '@tanstack/react-query';
import { Button, Input, StatusBadge } from '@marketlens/ui';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { loginToShop } from '../services/shop-api-client';
import { useClientUiStore } from '../state/client-ui-store';

const loginFormSchema = z.object({
  identity: z.string().min(1),
  password: z.string().min(1),
  rememberDevice: z.boolean().default(false),
});

type LoginForm = z.infer<typeof loginFormSchema>;

export function LoginScreen() {
  const shopBaseUrl = useClientUiStore((state) => state.shopBaseUrl);
  const deviceUuid = useClientUiStore((state) => state.deviceUuid);
  const activeRole = useClientUiStore((state) => state.activeRole);
  const setPermissions = useClientUiStore((state) => state.setPermissions);
  const setRoles = useClientUiStore((state) => state.setRoles);
  const setUsername = useClientUiStore((state) => state.setUsername);
  const setRememberDevice = useClientUiStore((state) => state.setRememberDevice);
  const setAccountStatus = useClientUiStore((state) => state.setAccountStatus);
  const setPasswordChangeRequired = useClientUiStore((state) => state.setPasswordChangeRequired);
  const setCurrentUser = useClientUiStore((state) => state.setCurrentUser);
  const setConnectionState = useClientUiStore((state) => state.setConnectionState);
  const setSessionState = useClientUiStore((state) => state.setSessionState);
  const notify = useClientUiStore((state) => state.notify);
  const form = useForm<LoginForm>({
    defaultValues: {
      identity: '',
      password: '',
      rememberDevice: false,
    },
  });

  const login = useMutation({
    mutationFn: (input: LoginForm) => loginToShop({ ...input, shopBaseUrl, deviceUuid }),
    onSuccess: async (session) => {
      setConnectionState('connected');
      setSessionState('active');
      setPermissions(session.permissions);
      setRoles(session.roles);
      setUsername(session.username);
      setRememberDevice(session.rememberDevice);
      setAccountStatus(session.accountStatus);
      setPasswordChangeRequired(session.passwordChangeRequired);
      setCurrentUser(session.user);
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
      await window.marketlensClient.preferences.save({
        ...(await window.marketlensClient.preferences.load()),
        preferredRole: activeRole,
      });
      notify({
        tone: 'success',
        title: 'Signed in',
        message: 'Features are visible only after the Shop App confirms the authenticated session and permissions.',
      });
    },
    onError: () => {
      setSessionState('missing');
      notify({
        tone: 'danger',
        title: 'Authentication failed',
        message: 'Authentication failed. Check your credentials and try again.',
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
          <Input label="Username or staff code" autoComplete="username" {...form.register('identity')} />
          <Input label="Password" type="password" autoComplete="current-password" {...form.register('password')} />
          <label className="flex items-center gap-2 text-sm text-muted">
            <input type="checkbox" {...form.register('rememberDevice')} />
            Remember this device where policy allows
          </label>
        </div>

        <div className="mt-5 flex justify-between gap-3">
          <Button variant="ghost" onClick={() => setSessionState('missing')}>
            Back
          </Button>
          <Button type="submit" disabled={login.isPending || !deviceUuid}>
            Sign in
          </Button>
        </div>
      </form>
    </section>
  );
}
