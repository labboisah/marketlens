import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class BranchSelectionScreen extends StatelessWidget {
  const BranchSelectionScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Select Branch')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: const Icon(Icons.store),
              title: const Text('Main Branch'),
              subtitle: const Text('Shows synchronized Platform data only.'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.go('/dashboard'),
            ),
          ),
        ],
      ),
    );
  }
}
