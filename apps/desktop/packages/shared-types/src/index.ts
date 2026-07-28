export type Uuid = string;

export type TenantScoped = {
  businessId: Uuid;
};

export type BranchScoped = TenantScoped & {
  branchId: Uuid;
};
