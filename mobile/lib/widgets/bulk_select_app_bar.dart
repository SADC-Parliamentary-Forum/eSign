import 'package:flutter/material.dart';
import '../theme/app_design.dart';

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
            icon: Icon(Icons.delete, color: AppDesign.statusDeclined),
            onPressed: selectedCount > 0 ? onDelete : null,
            tooltip: 'Delete',
          ),
      ],
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);
}
