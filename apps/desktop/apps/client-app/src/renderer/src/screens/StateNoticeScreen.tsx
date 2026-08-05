import { StatusBadge } from '@marketlens/ui';

function labelForState(state: string): string {
  return state.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
}

export function StateNoticeScreen({ title, message }: { title: string; message: string }) {
  return (
    <section className="grid min-h-[calc(100vh-61px)] place-items-center p-6">
      <div className="w-full max-w-xl rounded-lg border border-border bg-surface p-6 shadow-sm">
        <StatusBadge tone="warning">{labelForState(title)}</StatusBadge>
        <h1 className="mt-4 text-2xl font-semibold">Workstation attention required</h1>
        <p className="mt-2 text-sm text-muted">{message}</p>
      </div>
    </section>
  );
}