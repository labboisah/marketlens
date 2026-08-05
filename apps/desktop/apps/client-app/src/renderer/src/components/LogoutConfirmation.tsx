import { Button } from '@marketlens/ui';

interface LogoutConfirmationProps {
  open: boolean;
  pending: boolean;
  onCancel: () => void;
  onConfirm: () => void;
}

export function LogoutConfirmation({ open, pending, onCancel, onConfirm }: LogoutConfirmationProps) {
  if (!open) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/30 p-4">
      <div className="w-full max-w-sm rounded-lg border border-border bg-surface p-5 shadow-sm">
        <h2 className="text-base font-semibold">Confirm logout</h2>
        <p className="mt-2 text-sm text-muted">Do you want to sign out from this workstation now?</p>
        <div className="mt-4 flex justify-end gap-2">
          <Button variant="ghost" onClick={onCancel} disabled={pending}>Cancel</Button>
          <Button onClick={onConfirm} disabled={pending}>Sign out</Button>
        </div>
      </div>
    </div>
  );
}
