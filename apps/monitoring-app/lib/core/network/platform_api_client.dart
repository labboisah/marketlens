import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/api_config.dart';

final platformApiClientProvider = Provider<PlatformApiClient>((ref) {
  return PlatformApiClient(config: MonitoringApiConfig.development());
});

class PlatformApiClient {
  PlatformApiClient({required MonitoringApiConfig config})
      : dio = Dio(
          BaseOptions(
            baseUrl: config.baseUrl,
            connectTimeout: config.timeout,
            receiveTimeout: config.timeout,
          ),
        );

  final Dio dio;
}
