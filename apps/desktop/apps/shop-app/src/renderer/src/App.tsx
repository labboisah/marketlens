import { ServerConnectionIndicator, SynchronizationStatusIndicator } from '@marketlens/ui';
import '../styles/app.css';

export function App() {
  return (
    <main className="min-h-screen bg-background text-foreground">
      <header className="border-b border-border bg-surface px-6 py-4">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <div>
            <h1 className="text-xl font-bold">MarketLens Shop App</h1>
            <p className="text-sm text-muted">Local source of truth scaffold</p>
          </div>
          <div className="flex items-center gap-2">
            <ServerConnectionIndicator state="connected" />
            <SynchronizationStatusIndicator state="delayed" />
          </div>
        </div>
      </header>

      <section className="mx-auto grid max-w-7xl gap-4 p-6 md:grid-cols-3">
        {['Local API', 'SQLite', 'Synchronization queue'].map((title) => (
          <article key={title} className="rounded-lg border border-border bg-surface p-4 shadow-sm">
            <h2 className="font-semibold">{title}</h2>
            <p className="mt-2 text-sm text-muted">Foundation shell ready for implementation.</p>
          </article>
        ))}
      </section>
    </main>
  );
}
