import 'package:flutter_test/flutter_test.dart';
import 'package:marketlens_monitoring_app/core/cache/read_only_cache_policy.dart';
import 'package:marketlens_monitoring_app/core/notifications/push_notification_policy.dart';
import 'package:marketlens_monitoring_app/presentation/features/monitoring_feature.dart';

void main() {
  test('monitoring features are scaffolded', () {
    expect(
      monitoringFeatures.map((feature) => feature.title),
      containsAll([
        'Dashboard',
        'Sales Summary',
        'Payment Summary',
        'Production Summary',
        'Services Summary',
        'Expenses',
        'Stock Summary',
        'Low-Stock Alerts',
        'Customer Debts',
        'Cashier Performance',
        'Branch Performance',
        'Security Alerts',
        'Synchronization Status',
        'Notifications',
        'Profile',
      ]),
    );
    expect(
      monitoringFeatures.firstWhere((feature) => feature.id == 'sales-summary').requiredOperatingModes,
      ['sales'],
    );
  });

  test('offline cache and push notification boundaries are safe by default', () {
    expect(ReadOnlyCachePolicy.reportsAreReadOnly, isTrue);
    expect(ReadOnlyCachePolicy.cacheStoresSummariesOnly, isTrue);
    expect(PushNotificationPolicy.hideSensitiveFinancialDetailsOnLockScreen, isTrue);
  });
}
