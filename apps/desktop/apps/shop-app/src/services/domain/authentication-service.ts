export type LoginInput = {
  username: string;
  password: string;
  deviceUuid?: string;
};

export type LoginResult = {
  authenticated: boolean;
  reason?: string;
};

export class AuthenticationService {
  async login(input: LoginInput): Promise<LoginResult> {
    if (!input.username || !input.password) {
      return { authenticated: false, reason: 'Missing credentials.' };
    }

    return { authenticated: false, reason: 'Operational user authentication is not implemented yet.' };
  }
}
