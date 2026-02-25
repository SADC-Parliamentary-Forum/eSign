import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../theme/app_design.dart';
import '../services/api_service.dart';
import '../widgets/loading_skeleton.dart';
import '../widgets/error_widget.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<dynamic> _notifications = [];
  bool _isLoading = true;
  bool _error = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadNotifications();
  }

  Future<void> _loadNotifications() async {
    setState(() {
      _isLoading = true;
      _error = false;
      _errorMessage = null;
    });
    try {
      final notifications = await ApiService.getNotifications();
      setState(() {
        _notifications = notifications;
        _isLoading = false;
        _error = false;
        _errorMessage = null;
      });
    } catch (e) {
      setState(() {
        _isLoading = false;
        _error = true;
        _errorMessage = e.toString();
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to load notifications: $e')),
        );
      }
    }
  }

  Future<void> _markAsRead(String notificationId) async {
    await ApiService.markNotificationAsRead(notificationId);
    _loadNotifications();
  }

  Future<void> _markAllAsRead() async {
    await ApiService.markAllNotificationsAsRead();
    _loadNotifications();
  }

  IconData _getNotificationIcon(String? type) {
    switch (type?.toLowerCase()) {
      case 'document_signed':
        return Icons.check_circle;
      case 'document_pending':
        return Icons.pending;
      case 'document_rejected':
        return Icons.cancel;
      case 'new_document':
        return Icons.description;
      default:
        return Icons.notifications;
    }
  }

  Color _getNotificationColor(String? type) {
    switch (type?.toLowerCase()) {
      case 'document_signed':
        return AppDesign.statusCompleted;
      case 'document_pending':
        return AppDesign.statusPending;
      case 'document_rejected':
        return AppDesign.statusDeclined;
      case 'new_document':
        return AppDesign.primary;
      default:
        return AppDesign.statusDraft;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (_notifications.any((n) => n['read_at'] == null))
            IconButton(
              icon: const Icon(Icons.done_all),
              onPressed: _markAllAsRead,
              tooltip: 'Mark all as read',
            ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadNotifications,
          ),
        ],
      ),
      body: _isLoading
          ? const ListSkeleton(itemCount: 5)
          : _error
              ? ErrorRetryWidget(
                  message: _errorMessage ?? 'Failed to load notifications',
                  onRetry: _loadNotifications,
                )
              : _notifications.isEmpty
                  ? const EmptyStateWidget(
                      icon: Icons.notifications_none,
                      title: 'No notifications',
                      subtitle: 'You\'re all caught up!',
                    )
                  : RefreshIndicator(
                  onRefresh: _loadNotifications,
                  child: ListView.builder(
                    itemCount: _notifications.length,
                    itemBuilder: (context, index) {
                      final notification = _notifications[index];
                      final isRead = notification['read_at'] != null;
                      final type = notification['type'];
                      final color = _getNotificationColor(type);

                      return Dismissible(
                        key: Key(notification['id'].toString()),
                        direction: DismissDirection.endToStart,
                        background: Container(
                          color: AppDesign.statusDeclined,
                          alignment: Alignment.centerRight,
                          padding: const EdgeInsets.only(right: 20),
                          child: const Icon(Icons.delete, color: Colors.white),
                        ),
                        onDismissed: (direction) {
                          _markAsRead(notification['id'].toString());
                        },
                        child: InkWell(
                          onTap: () {
                            if (!isRead) {
                              _markAsRead(notification['id'].toString());
                            }
                          },
                          child: Container(
                            color: isRead
                                ? Theme.of(context).colorScheme.surface
                                : AppDesign.primary.withValues(alpha: 0.08),
                            child: ListTile(
                              leading: CircleAvatar(
                                backgroundColor: color.withOpacity(0.1),
                                child: Icon(_getNotificationIcon(type), color: color),
                              ),
                              title: Text(
                                notification['title'] ?? 'Notification',
                                style: TextStyle(
                                  fontWeight: isRead ? FontWeight.normal : FontWeight.bold,
                                ),
                              ),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const SizedBox(height: 4),
                                  Text(notification['message'] ?? ''),
                                  const SizedBox(height: 4),
                                  Text(
                                    notification['created_at'] != null
                                        ? DateFormat('MMM dd, yyyy HH:mm').format(
                                            DateTime.parse(notification['created_at']))
                                        : '',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                                    ),
                                  ),
                                ],
                              ),
                              trailing: isRead
                                  ? null
                                  : Container(
                                      width: 8,
                                      height: 8,
                                      decoration: const BoxDecoration(
                                        color: AppDesign.primary,
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
