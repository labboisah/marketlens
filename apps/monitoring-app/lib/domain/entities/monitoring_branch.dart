class MonitoringBranch {
  const MonitoringBranch({
    required this.businessUuid,
    required this.businessName,
    required this.branchUuid,
    required this.branchName,
    required this.status,
    required this.operatingModes,
  });

  final String businessUuid;
  final String businessName;
  final String branchUuid;
  final String branchName;
  final String status;
  final List<String> operatingModes;
}