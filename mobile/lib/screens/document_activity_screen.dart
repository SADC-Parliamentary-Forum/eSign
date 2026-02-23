import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';

class DocumentActivityScreen extends StatefulWidget {
  final String? documentId;

  const DocumentActivityScreen({super.key, this.documentId});

  @override
  State<DocumentActivityScreen> createState() => _DocumentActivityScreenState();
}

class _DocumentActivityScreenState extends State<DocumentActivityScreen> {
  List<dynamic> _activities = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadActivities();
  }

  Future<void> _loadActivities() async {
    setState(() => _isLoading = true);
    try {
      final activities = await ApiService.getDocumentActivity(
        documentId: widget.documentId,
      );
      setState(() {
        _activities = activities;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to load activity: $e')),
        );
      }
    }
  }

  IconData _getActivityIcon(String? type) {
    switch (type?.toLowerCase()) {
      case 'signed':
        return Icons.check_circle;
      case 'rejected':
        return Icons.cancel;
      case 'created':
        return Icons.add_circle;
      case 'sent':
        return Icons.send;
      case 'viewed':
        return Icons.visibility;
      default:
        return Icons.info;
    }
  }

  Color _getActivityColor(String? type) {
    switch (type?.toLowerCase()) {
      case 'signed':
        return Colors.green;
      case 'rejected':
        return Colors.red;
      case 'created':
        return Colors.blue;
      case 'sent':
        return Colors.orange;
      case 'viewed':
        return Colors.purple;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Activity Feed'),
        backgroundColor: Theme.of(context).colorScheme.primary,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadActivities,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _activities.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.history, size: 64, color: Colors.grey[400]),
                      const SizedBox(height: 16),
                      Text(
                        'No activity found',
                        style: TextStyle(color: Colors.grey[600], fontSize: 16),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadActivities,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _activities.length,
                    itemBuilder: (context, index) {
                      final activity = _activities[index];
                      final type = activity['type'] ?? 'unknown';
                      final color = _getActivityColor(type);

                      return Card(
                        margin: const EdgeInsets.only(bottom: 12),
                        child: ListTile(
                          leading: CircleAvatar(
                            backgroundColor: color.withOpacity(0.1),
                            child: Icon(_getActivityIcon(type), color: color),
                          ),
                          title: Text(
                            activity['description'] ?? activity['type'] ?? 'Activity',
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              if (activity['user'] != null)
                                Text('By: ${activity['user']['name'] ?? activity['user']['email']}'),
                              if (activity['created_at'] != null)
                                Text(
                                  DateFormat('MMM dd, yyyy HH:mm').format(
                                    DateTime.parse(activity['created_at']),
                                  ),
                                  style: TextStyle(
                                    color: Colors.grey[600],
                                    fontSize: 12,
                                  ),
                                ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
