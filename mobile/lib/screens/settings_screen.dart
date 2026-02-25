// Settings screen per Design/user_profile_and_hamburger_menu.
// Uses AppDesign theme; lists Profile, Notifications, Security, etc.

import 'package:flutter/material.dart';
import 'package:mobile/theme/app_design.dart';
import 'package:mobile/services/api_service.dart';
import 'package:mobile/screens/profile_screen.dart';
import 'package:mobile/screens/notifications_screen.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key, this.onLogout});

  /// Called after logout; parent should navigate to Login (e.g. pushAndRemoveUntil).
  final VoidCallback? onLogout;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final surface = isDark ? AppDesign.surfaceDark : AppDesign.surfaceLight;
    final borderColor = isDark ? AppDesign.borderDark : AppDesign.borderLight;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Settings'),
        centerTitle: true,
      ),
      body: ListView(
        padding: const EdgeInsets.all(AppDesign.spacingMd),
        children: [
          _sectionLabel(context, 'Account'),
          _settingsCard(
            context,
            surface: surface,
            borderColor: borderColor,
            children: [
              _listTile(
                context,
                icon: Icons.person_outline,
                label: 'Profile & Settings',
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const ProfileScreen()),
                ),
              ),
              _listTile(
                context,
                icon: Icons.notifications_outlined,
                label: 'Notifications',
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const NotificationsScreen()),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppDesign.spacingLg),
          _sectionLabel(context, 'Security & Compliance'),
          _settingsCard(
            context,
            surface: surface,
            borderColor: borderColor,
            children: [
              _listTile(context, icon: Icons.lock_outline, label: 'Security Settings', onTap: () {}),
              _listTile(context, icon: Icons.history, label: 'Audit Logs', onTap: () {}),
            ],
          ),
          const SizedBox(height: AppDesign.spacingLg),
          _listTile(
            context,
            icon: Icons.logout,
            label: 'Log out',
            textColor: AppDesign.statusDeclined,
            onTap: () async {
              await ApiService.logout();
              if (context.mounted) onLogout?.call();
            },
          ),
        ],
      ),
    );
  }

  Widget _sectionLabel(BuildContext context, String label) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 8),
      child: Text(
        label.toUpperCase(),
        style: AppDesign.labelUppercase.copyWith(
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
      ),
    );
  }

  Widget _settingsCard(
    BuildContext context, {
    required Color surface,
    required Color borderColor,
    required List<Widget> children,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: surface,
        borderRadius: BorderRadius.circular(AppDesign.radiusLg),
        border: Border.all(color: borderColor),
      ),
      child: Column(children: children),
    );
  }

  Widget _listTile(
    BuildContext context, {
    required IconData icon,
    required String label,
    required VoidCallback onTap,
    Color? textColor,
  }) {
    final fg = textColor ?? Theme.of(context).colorScheme.onSurface;
    return ListTile(
      leading: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: AppDesign.primary.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(AppDesign.radiusMd),
        ),
        child: Icon(icon, size: 20, color: AppDesign.primary),
      ),
      title: Text(label, style: AppDesign.bodyMedium.copyWith(color: fg)),
      trailing: Icon(Icons.chevron_right, color: Theme.of(context).colorScheme.onSurfaceVariant),
      onTap: onTap,
    );
  }
}
