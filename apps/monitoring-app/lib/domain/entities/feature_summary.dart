class FeatureSummary {
  const FeatureSummary({
    required this.title,
    required this.value,
    required this.detail,
    required this.lastSuccessfulSyncAt,
    required this.isStale,
  });

  final String title;
  final String value;
  final String detail;
  final DateTime lastSuccessfulSyncAt;
  final bool isStale;
}
