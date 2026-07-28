import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:local_auth/local_auth.dart';

final biometricGateProvider = Provider<BiometricGate>((ref) {
  return BiometricGate(authentication: LocalAuthentication());
});

class BiometricGate {
  const BiometricGate({required LocalAuthentication authentication}) : _authentication = authentication;

  final LocalAuthentication _authentication;

  Future<bool> authenticateForSensitiveScreen() {
    return _authentication.authenticate(
      localizedReason: 'Confirm your identity to view sensitive MarketLens information.',
      options: const AuthenticationOptions(biometricOnly: false),
    );
  }
}
