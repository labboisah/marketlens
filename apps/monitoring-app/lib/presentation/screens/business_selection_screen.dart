import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class BusinessSelectionScreen extends StatelessWidget {
  const BusinessSelectionScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Select Business')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: const Icon(Icons.business),
              title: const Text('MarketLens Demo Business'),
              subtitle: const Text('Access follows Platform authorization.'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.go('/branch-selection'),
            ),
          ),
        ],
      ),
    );
  }
}
