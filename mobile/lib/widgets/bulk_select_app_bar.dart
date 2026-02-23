import 'package:flutter/material.dart';

class BulkSelectAppBar extends StatelessWidget implements PreferredSizeWidget {
  final int selectedCount;
  final VoidCallback onCancel;
  final VoidCallback? onDelete;
  final VoidCallback? onSign;
  final VoidCallback? onDownload;

  const BulkSelectAppBar({
    super.key,
    required this.selectedCount,
    required this.onCancel,
    this.onDelete,
    this.onSign,
    this.onDownload,
  });

  @override
  Widget build(BuildContext context) {
    return AppBar(
      leading: IconButton(
        icon: const Icon(Icons.close),
        onPressed: onCancel,
      ),
      title: Text('$selectedCount selected'),
      backgroundColor: Theme.of(context).colorScheme.primary,
      foregroundColor: Colors.white,
      actions: [
        if (onDownload != null)
          IconButton(
            icon: const Icon(Icons.download),
            onPressed: selectedCount > 0 ? onDownload : null,
            tooltip: 'Download',
          ),
        if (onSign != null)
          IconButton(
            icon: const Icon(Icons.edit),
            onPressed: selectedCount > 0 ? onSign : null,
            tooltip: 'Sign',
          ),
        if (onDelete != null)
          IconButton(
            icon: const Icon(Icons.delete),
            onPressed: selectedCount > 0 ? onDelete : null,
            tooltip: 'Delete',
            color: Colors.red[300],
          ),
      ],
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);
}
