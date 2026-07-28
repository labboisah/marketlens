class MonitoringSummary {
  const MonitoringSummary({
    required this.businessName,
    required this.branchName,
    required this.lastSuccessfulSyncAt,
    required this.isStale,
    required this.totalSalesToday,
    required this.lowStockCount,
    required this.securityAlertCount,
  });

  final String businessName;
  final String branchName;
  final DateTime lastSuccessfulSyncAt;
  final bool isStale;
  final double totalSalesToday;
  final int lowStockCount;
  final int securityAlertCount;
}
