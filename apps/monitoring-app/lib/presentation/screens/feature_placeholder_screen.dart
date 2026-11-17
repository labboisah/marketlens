import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/repositories/platform_monitoring_repository.dart';
import '../features/monitoring_feature.dart';
import '../widgets/summary_card.dart';
import '../widgets/sync_status_banner.dart';

final featureSummaryProvider = FutureProvider.family((ref, String featureId) {
  return ref.watch(monitoringRepositoryProvider).getFeatureSummary(
        featureId,
        branch: ref.watch(selectedMonitoringBranchProvider),
      );
});

class FeaturePlaceholderScreen extends ConsumerWidget {
  const FeaturePlaceholderScreen({
    required this.featureId,
    super.key,
  });

  final String featureId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final selectedBranch = ref.watch(selectedMonitoringBranchProvider);
    final feature = monitoringFeatures.firstWhere((item) => item.id == featureId);
    final requiredModes = feature.requiredOperatingModes;
    if (requiredModes != null &&
        (selectedBranch == null || !requiredModes.any(selectedBranch.operatingModes.contains))) {
      return const Center(child: Text('This feature is not configured for the selected branch.'));
    }

    final summary = ref.watch(featureSummaryProvider(featureId));

    return summary.when(
      data: (data) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SyncStatusBanner(
            lastSuccessfulSyncAt: data.lastSuccessfulSyncAt,
            isStale: data.isStale,
          ),
          const SizedBox(height: 16),
          SummaryCard(
            title: data.title,
            value: data.value,
            detail: data.detail,
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Text(
                'Placeholder screen only. This app retrieves Platform summaries and cached read-only reports; it never connects directly to Business Servers.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ),
          ),
        ],
      ),
      error: (error, stackTrace) => Center(child: Text('Feature summary failed: $error')),
      loading: () => const Center(child: CircularProgressIndicator()),
    );
  }
}
