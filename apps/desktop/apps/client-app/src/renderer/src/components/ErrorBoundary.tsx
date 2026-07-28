import { Component, type PropsWithChildren, type ReactNode } from 'react';
import { Button } from '@marketlens/ui';

interface ErrorBoundaryState {
  hasError: boolean;
}

export class ErrorBoundary extends Component<PropsWithChildren, ErrorBoundaryState> {
  state: ErrorBoundaryState = { hasError: false };

  static getDerivedStateFromError(): ErrorBoundaryState {
    return { hasError: true };
  }

  render(): ReactNode {
    if (this.state.hasError) {
      return (
        <main className="grid min-h-screen place-items-center bg-background p-6 text-foreground">
          <section className="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-sm">
            <h1 className="text-xl font-semibold">Something went wrong</h1>
            <p className="mt-2 text-sm text-muted">
              The Client App shell recovered from an unexpected UI error. No transaction has been confirmed locally.
            </p>
            <Button className="mt-4" onClick={() => this.setState({ hasError: false })}>
              Try again
            </Button>
          </section>
        </main>
      );
    }

    return this.props.children;
  }
}
