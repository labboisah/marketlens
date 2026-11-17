import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../data/repositories/platform_monitoring_repository.dart';
import '../features/monitoring_feature.dart';

class MonitoringScaffold extends ConsumerWidget {
  const MonitoringScaffold({
    required this.child,
    super.key,
  });

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final branch = ref.watch(selectedMonitoringBranchProvider);
    final modes = branch?.operatingModes ?? const <String>[];
    final visibleFeatures = monitoringFeatures.where((feature) {
      final requiredModes = feature.requiredOperatingModes;
      return requiredModes == null || requiredModes.any(modes.contains);
    }).toList(growable: false);
    final query = branch == null ? <String, String>{} : {
      'business_uuid': branch.businessUuid,
      'branch_uuid': branch.branchUuid,
    };
    return Scaffold(
      appBar: AppBar(title: const Text('MarketLens Monitoring')),
      drawer: NavigationDrawer(
        selectedIndex: _selectedIndex(context, visibleFeatures),
        onDestinationSelected: (index) => context.go(Uri(path: visibleFeatures[index].path, queryParameters: query).toString()),
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(28, 20, 16, 12),
            child: Text('Platform summaries'),
          ),
          for (final feature in visibleFeatures)
            NavigationDrawerDestination(
              icon: Icon(feature.icon),
              label: Text(feature.title),
            ),
        ],
      ),
      body: SafeArea(child: child),
    );
  }

  int _selectedIndex(BuildContext context, List<MonitoringFeature> visibleFeatures) {
    final location = GoRouterState.of(context).uri.path;
    final index = visibleFeatures.indexWhere((feature) => feature.path == location);
    return index < 0 ? 0 : index;
  }
}
