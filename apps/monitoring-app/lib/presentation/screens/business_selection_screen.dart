import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../data/repositories/platform_monitoring_repository.dart';
import '../../domain/entities/monitoring_branch.dart';

class BusinessSelectionScreen extends ConsumerWidget {
  const BusinessSelectionScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final branches = ref.watch(authorizedMonitoringBranchesProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Select Business')),
      body: branches.when(
        data: (items) {
          final businesses = <String, MonitoringBranch>{};
          for (final branch in items) {
            businesses.putIfAbsent(branch.businessUuid, () => branch);
          }
          if (businesses.isEmpty) return const Center(child: Text('No businesses are assigned to this account.'));
          return ListView(
            padding: const EdgeInsets.all(16),
            children: businesses.values.map((business) => Card(
              child: ListTile(
                leading: const Icon(Icons.business),
                title: Text(business.businessName),
                subtitle: Text('${items.where((item) => item.businessUuid == business.businessUuid).length} authorized branches'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => context.go('/branch-selection?business_uuid=${Uri.encodeComponent(business.businessUuid)}'),
              ),
            )).toList(growable: false),
          );
        },
        error: (error, _) => Center(child: Text('Could not load businesses: $error')),
        loading: () => const Center(child: CircularProgressIndicator()),
      ),
    );
  }
}
