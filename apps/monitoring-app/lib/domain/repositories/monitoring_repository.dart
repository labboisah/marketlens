import '../entities/feature_summary.dart';
import '../entities/monitoring_summary.dart';

abstract interface class MonitoringRepository {
  Future<MonitoringSummary> getDashboardSummary();

  Future<FeatureSummary> getFeatureSummary(String featureId);
}
