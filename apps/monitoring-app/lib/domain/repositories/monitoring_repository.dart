import '../entities/feature_summary.dart';
import '../entities/monitoring_branch.dart';
import '../entities/monitoring_summary.dart';

abstract interface class MonitoringRepository {
  Future<void> login(String email, String password);

  Future<List<MonitoringBranch>> getAuthorizedBranches();

  Future<MonitoringSummary> getDashboardSummary({MonitoringBranch? branch});

  Future<FeatureSummary> getFeatureSummary(String featureId, {MonitoringBranch? branch});
}
