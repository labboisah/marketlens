import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/repositories/mock_monitoring_repository.dart';
import '../widgets/summary_card.dart';
import '../widgets/sync_status_banner.dart';

final featureSummaryProvider = FutureProvider.family((ref, String featureId) {
  return ref.watch(monitoringRepositoryProvider).getFeatureSummary(featureId);
});

class FeaturePlaceholderScreen extends ConsumerWidget {
  const FeaturePlaceholderScreen({
    required this.featureId,
    super.key,
  });

  final String featureId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
                'Placeholder screen only. This app retrieves Platform summaries and cached read-only reports; it never connects directly to Shop Apps.',
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
