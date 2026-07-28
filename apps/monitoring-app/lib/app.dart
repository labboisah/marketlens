import 'package:flutter/material.dart';

import 'core/routing/app_router.dart';
import 'theme/marketlens_theme.dart';

class MarketLensMonitoringApp extends StatelessWidget {
  const MarketLensMonitoringApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'MarketLens Monitoring',
      theme: MarketLensTheme.light(),
      darkTheme: MarketLensTheme.dark(),
      themeMode: ThemeMode.system,
      routerConfig: appRouter,
    );
  }
}
