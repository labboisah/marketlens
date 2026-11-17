import 'package:go_router/go_router.dart';

import '../../presentation/features/monitoring_feature.dart';
import '../../presentation/screens/authentication_screen.dart';
import '../../presentation/screens/branch_selection_screen.dart';
import '../../presentation/screens/business_selection_screen.dart';
import '../../presentation/screens/dashboard_screen.dart';
import '../../presentation/screens/feature_placeholder_screen.dart';
import '../../presentation/shell/monitoring_scaffold.dart';

final appRouter = GoRouter(
  initialLocation: '/auth',
  routes: [
    GoRoute(
      path: '/auth',
      builder: (context, state) => const AuthenticationScreen(),
    ),
    GoRoute(
      path: '/business-selection',
      builder: (context, state) => const BusinessSelectionScreen(),
    ),
    GoRoute(
      path: '/branch-selection',
      builder: (context, state) => BranchSelectionScreen(
        businessUuid: state.uri.queryParameters['business_uuid'] ?? '',
      ),
    ),
    ShellRoute(
      builder: (context, state, child) => MonitoringScaffold(child: child),
      routes: [
        GoRoute(
          path: '/dashboard',
          builder: (context, state) => const DashboardScreen(),
        ),
        for (final feature in monitoringFeatures.where((feature) => feature.id != 'dashboard'))
          GoRoute(
            path: feature.path,
            builder: (context, state) => FeaturePlaceholderScreen(featureId: feature.id),
          ),
      ],
    ),
  ],
);
