import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../data/repositories/platform_monitoring_repository.dart';

class BranchSelectionScreen extends ConsumerWidget {
  const BranchSelectionScreen({required this.businessUuid, super.key});

  final String businessUuid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final branches = ref.watch(authorizedMonitoringBranchesProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Select Branch')),
      body: branches.when(
        data: (items) {
          final businessBranches = items.where((branch) => branch.businessUuid == businessUuid).toList(growable: false);
          if (businessBranches.isEmpty) return const Center(child: Text('No authorized branches are available.'));
          return ListView(
            padding: const EdgeInsets.all(16),
            children: businessBranches.map((branch) {
              final modes = branch.operatingModes.map(_modeLabel).join(' · ');
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.store),
                  title: Text(branch.branchName),
                  subtitle: Text(modes.isEmpty ? 'No operating modes configured' : modes),
                  trailing: const Icon(Icons.chevron_right),
                  enabled: branch.status == 'active',
                  onTap: () {
                    ref.read(selectedMonitoringBranchProvider.notifier).state = branch;
                    context.go(Uri(path: '/dashboard', queryParameters: {
                      'business_uuid': branch.businessUuid,
                      'branch_uuid': branch.branchUuid,
                    }).toString());
                  },
                ),
              );
            }).toList(growable: false),
          );
        },
        error: (error, _) => Center(child: Text('Could not load branches: $error')),
        loading: () => const Center(child: CircularProgressIndicator()),
      ),
    );
  }

  String _modeLabel(String mode) => switch (mode) {
        'sales' => 'Sales',
        'production' => 'Production',
        'services' => 'Services',
        _ => mode,
      };
}
