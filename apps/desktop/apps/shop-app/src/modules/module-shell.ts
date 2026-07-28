import { z } from 'zod';

export const moduleStatusSchema = z.enum(['scaffolded', 'active', 'deprecated']);

export const moduleShellSchema = z.object({
  name: z.string(),
  key: z.string(),
  status: moduleStatusSchema,
  ownsTransactions: z.boolean(),
  auditable: z.boolean(),
  synchronized: z.boolean(),
  localOnly: z.boolean(),
});

export type ModuleShell = z.infer<typeof moduleShellSchema>;

export function createModuleShell(module: ModuleShell): ModuleShell {
  return moduleShellSchema.parse(module);
}
