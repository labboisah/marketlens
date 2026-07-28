import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../features/monitoring_feature.dart';

class MonitoringScaffold extends StatelessWidget {
  const MonitoringScaffold({
    required this.child,
    super.key,
  });

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('MarketLens Monitoring')),
      drawer: NavigationDrawer(
        selectedIndex: _selectedIndex(context),
        onDestinationSelected: (index) => context.go(monitoringFeatures[index].path),
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(28, 20, 16, 12),
            child: Text('Platform summaries'),
          ),
          for (final feature in monitoringFeatures)
            NavigationDrawerDestination(
              icon: Icon(feature.icon),
              label: Text(feature.title),
            ),
        ],
      ),
      body: SafeArea(child: child),
    );
  }

  int _selectedIndex(BuildContext context) {
    final location = GoRouterState.of(context).uri.path;
    final index = monitoringFeatures.indexWhere((feature) => feature.path == location);
    return index < 0 ? 0 : index;
  }
}
