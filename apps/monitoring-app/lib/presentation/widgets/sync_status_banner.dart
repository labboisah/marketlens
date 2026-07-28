import 'package:flutter/material.dart';

class SyncStatusBanner extends StatelessWidget {
  const SyncStatusBanner({
    required this.lastSuccessfulSyncAt,
    required this.isStale,
    super.key,
  });

  final DateTime lastSuccessfulSyncAt;
  final bool isStale;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Material(
      color: isStale ? colorScheme.errorContainer : colorScheme.secondaryContainer,
      borderRadius: BorderRadius.circular(8),
      child: ListTile(
        leading: Icon(isStale ? Icons.sync_problem : Icons.sync, color: isStale ? colorScheme.error : colorScheme.secondary),
        title: Text(isStale ? 'Data may be stale' : 'Synchronized Platform data'),
        subtitle: Text('Last successful shop sync: ${lastSuccessfulSyncAt.toLocal()}'),
      ),
    );
  }
}
