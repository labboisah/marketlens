import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/repositories/mock_monitoring_repository.dart';
import '../widgets/summary_card.dart';
import '../widgets/sync_status_banner.dart';

final dashboardSummaryProvider = FutureProvider((ref) {
  return ref.watch(monitoringRepositoryProvider).getDashboardSummary();
});

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final summary = ref.watch(dashboardSummaryProvider);

    return summary.when(
      data: (data) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SyncStatusBanner(
            lastSuccessfulSyncAt: data.lastSuccessfulSyncAt,
            isStale: data.isStale,
          ),
          const SizedBox(height: 16),
          Text(data.businessName, style: Theme.of(context).textTheme.headlineSmall),
          Text(data.branchName, style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 16),
          LayoutBuilder(
            builder: (context, constraints) {
              final isWide = constraints.maxWidth > 720;
              return GridView.count(
                crossAxisCount: isWide ? 3 : 1,
                childAspectRatio: isWide ? 1.6 : 2.4,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                children: [
                  SummaryCard(
                    title: 'Sales today',
                    value: 'NGN ${data.totalSalesToday.toStringAsFixed(0)}',
                    detail: 'Mock Platform summary',
                  ),
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
                ],
              );
            },
          ),
          const SizedBox(height: 16),
          SizedBox(
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
}
