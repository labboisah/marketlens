export type CustomerCreditPolicyInput = {
  customerStatus: string;
  currentBalance: number;
  creditLimit: number;
  requestedAmount: number;
  hasOverdueBalance: boolean;
  hasManagerApproval: boolean;
};

export function assertCreditAllowed(input: CustomerCreditPolicyInput): void {
  if (input.customerStatus !== 'active') {
    throw new Error('Customer is blocked');
  }
  if (input.hasOverdueBalance) {
    throw new Error('Customer has overdue balance');
  }
  if (input.currentBalance + input.requestedAmount > input.creditLimit && !input.hasManagerApproval) {
    throw new Error('Credit limit exceeded');
  }
}
