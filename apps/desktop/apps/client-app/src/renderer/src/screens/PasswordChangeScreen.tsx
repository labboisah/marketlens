import { useMutation } from '@tanstack/react-query';
import { Button, Input, StatusBadge } from '@marketlens/ui';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { changeShopPassword } from '../services/shop-api-client';
import { useClientUiStore } from '../state/client-ui-store';

const passwordChangeSchema = z.object({
  oldPassword: z.string().min(1),
  newPassword: z.string().min(8),
  confirmPassword: z.string().min(8),
}).refine((value) => value.newPassword === value.confirmPassword, {
  message: 'New password confirmation does not match.',
  path: ['confirmPassword'],
});

type PasswordChangeForm = z.infer<typeof passwordChangeSchema>;

export function PasswordChangeScreen() {
  const shopBaseUrl = useClientUiStore((state) => state.shopBaseUrl);
  const setPasswordChangeRequired = useClientUiStore((state) => state.setPasswordChangeRequired);
  const setSessionState = useClientUiStore((state) => state.setSessionState);
  const setScreen = useClientUiStore((state) => state.setScreen);
  const notify = useClientUiStore((state) => state.notify);
  const form = useForm<PasswordChangeForm>({
    defaultValues: {
      oldPassword: '',
      newPassword: '',
      confirmPassword: '',
    },
  });

  const submit = useMutation({
    mutationFn: async (input: PasswordChangeForm) => {
      const session = await window.marketlensClient.session.load();
      if (!session) {
        throw new Error('Authentication failed.');
      }

      return changeShopPassword(shopBaseUrl, session.token, input.oldPassword, input.newPassword);
    },
    onSuccess: async () => {
      setPasswordChangeRequired(false);
      await window.marketlensClient.session.clear();
      setSessionState('expired');
      setScreen('login');
      notify({
        tone: 'success',
        title: 'Password changed',
        message: 'Sign in with the new password to continue.',
      });
    },
    onError: () => {
      notify({
        tone: 'danger',
        title: 'Password change failed',
        message: 'Authentication failed. Check your current password and try again.',
      });
    },
  });

  return (
    <section className="grid min-h-screen place-items-center p-6">
      <form className="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-sm" onSubmit={form.handleSubmit((values) => submit.mutate(passwordChangeSchema.parse(values)))}>
        <StatusBadge tone="warning">Password update required</StatusBadge>
        <h1 className="mt-4 text-xl font-semibold">Change password</h1>
        <p className="mt-2 text-sm text-muted">For security, you must change your password before continuing.</p>

        <div className="mt-5 grid gap-3">
          <Input label="Current password" type="password" autoComplete="current-password" {...form.register('oldPassword')} />
          <Input label="New password" type="password" autoComplete="new-password" {...form.register('newPassword')} />
          <Input label="Confirm new password" type="password" autoComplete="new-password" {...form.register('confirmPassword')} />
        </div>

        <div className="mt-5 flex justify-end">
          <Button type="submit" disabled={submit.isPending}>Update password</Button>
        </div>
      </form>
    </section>
  );
}
