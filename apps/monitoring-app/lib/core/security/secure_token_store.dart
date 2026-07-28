import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final secureTokenStoreProvider = Provider<SecureTokenStore>((ref) {
  return const SecureTokenStore();
});

class SecureTokenStore {
  const SecureTokenStore({
    FlutterSecureStorage storage = const FlutterSecureStorage(),
  }) : _storage = storage;

  final FlutterSecureStorage _storage;

  static const _accessTokenKey = 'marketlens.monitoring.access_token';

  Future<String?> readAccessToken() {
    return _storage.read(key: _accessTokenKey);
  }

  Future<void> saveAccessToken(String token) {
    return _storage.write(key: _accessTokenKey, value: token);
  }

  Future<void> clear() {
    return _storage.delete(key: _accessTokenKey);
  }
}
