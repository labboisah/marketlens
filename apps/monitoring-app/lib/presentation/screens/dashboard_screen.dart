import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/repositories/platform_monitoring_repository.dart';
import '../widgets/summary_card.dart';
import '../widgets/sync_status_banner.dart';

final dashboardSummaryProvider = FutureProvider((ref) {
  return ref.watch(monitoringRepositoryProvider).getDashboardSummary(
        branch: ref.watch(selectedMonitoringBranchProvider),
      );
});

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final summary = ref.watch(dashboardSummaryProvider);
    final selectedBranch = ref.watch(selectedMonitoringBranchProvider);
    if (selectedBranch == null) {
      return Center(
        child: FilledButton(
          onPressed: () => context.go('/business-selection'),
          child: const Text('Select a branch'),
        ),
      );
    }
    final operatingModes = selectedBranch?.operatingModes ?? const ['sales'];
    final hasSales = operatingModes.contains('sales');
    final hasStockOperations = operatingModes.any((mode) => mode == 'sales' || mode == 'production');

    return summary.when(
      data: (data) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SyncStatusBanner(
            lastSuccessfulSyncAt: data.lastSuccessfulSyncAt,
            isStale: data.isStale,
          ),
          const SizedBox(height: 16),
          Text(selectedBranch?.businessName ?? data.businessName, style: Theme.of(context).textTheme.headlineSmall),
          Text(selectedBranch?.branchName ?? data.branchName, style: Theme.of(context).textTheme.bodyMedium),
          if (selectedBranch != null) ...[
            const SizedBox(height: 4),
            Text(operatingModes.map(_modeLabel).join(' · '), style: Theme.of(context).textTheme.labelMedium),
          ],
          const SizedBox(height: 16),
          LayoutBuilder(
            builder: (context, constraints) {
              final isWide = constraints.maxWidth > 720;
              final cards = <Widget>[
                if (hasSales)
                  SummaryCard(
                    title: 'Sales today',
                    value: 'NGN ${data.totalSalesToday.toStringAsFixed(0)}',
                    detail: 'Mock Platform summary',
                  ),
                if (hasStockOperations)
                  SummaryCard(
                    title: 'Low-stock alerts',
                    value: '${data.lowStockCount}',
                    detail: 'Products below threshold',
                  ),
                SummaryCard(
                  title: 'Security alerts',
                  value: '${data.securityAlertCount}',
                  detail: 'Requires authorized review',
                ),
              ];
              return GridView.count(
                crossAxisCount: isWide ? (cards.length > 3 ? 3 : cards.length) : 1,
                childAspectRatio: isWide ? 1.6 : 2.4,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                children: cards,
              );
            },
          ),
          if (hasSales) const SizedBox(height: 16),
          if (hasSales) SizedBox(
            height: 220,
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: BarChart(
                  BarChartData(
                    borderData: FlBorderData(show: false),
                    titlesData: const FlTitlesData(),
                    barGroups: [
                      BarChartGroupData(x: 0, barRods: [BarChartRodData(toY: 8)]),
                      BarChartGroupData(x: 1, barRods: [BarChartRodData(toY: 12)]),
                      BarChartGroupData(x: 2, barRods: [BarChartRodData(toY: 10)]),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
      error: (error, stackTrace) => Center(child: Text('Dashboard summary failed: $error')),
      loading: () => const Center(child: CircularProgressIndicator()),
    );
  }

  String _modeLabel(String mode) => switch (mode) {
        'sales' => 'Sales',
        'production' => 'Production',
        'services' => 'Services',
        _ => mode,
      };
}
