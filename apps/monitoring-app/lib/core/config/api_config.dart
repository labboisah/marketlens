class MonitoringApiConfig {
  const MonitoringApiConfig({
    required this.baseUrl,
    required this.timeout,
  });

  factory MonitoringApiConfig.development() {
    return const MonitoringApiConfig(
      baseUrl: String.fromEnvironment(
        'MONITORING_API_BASE_URL',
        defaultValue: 'http://localhost:8000/api',
      ),
      timeout: Duration(seconds: 20),
    );
  }

  final String baseUrl;
  final Duration timeout;
}
