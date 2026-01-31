import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/database_helper.dart';
import '../widgets/search_bar.dart';
import '../widgets/loading_skeleton.dart';
import '../widgets/error_widget.dart';
import '../widgets/offline_indicator.dart';
import '../widgets/bulk_select_app_bar.dart';
import '../widgets/filter_bottom_sheet.dart';
import 'upload_document_screen.dart';
import 'workflows_screen.dart';
import 'signatures_screen.dart';
import 'document_detail_screen.dart';
import 'scan_document_screen.dart';
import '../widgets/document_search_delegate.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  List<dynamic> _documents = [];
  List<dynamic> _filteredDocuments = [];
  bool _loading = true;
  bool _error = false;
  String? _errorMessage;
  final TextEditingController _searchController = TextEditingController();
  bool _bulkSelectMode = false;
  final Set<String> _selectedIds = {};
  String? _filterStatus;
  String? _filterDepartment;
  SortOption? _sortOption;
  List<String> _departments = [];

  @override
  void initState() {
    super.initState();
    _searchController.addListener(_filterDocuments);
    _fetchData();
    _loadDepartments();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadDepartments() async {
    try {
      final depts = await ApiService.getDepartments();
      setState(() {
        _departments = depts.map((d) => d['name'] as String? ?? '').where((n) => n.isNotEmpty).toList();
      });
    } catch (e) {
      // Ignore error, departments are optional
    }
  }

  void _applyFilters(String? status, String? department, SortOption? sort) {
    setState(() {
      _filterStatus = status;
      _filterDepartment = department;
      _sortOption = sort;
    });
    _filterDocuments();
  }

  void _filterDocuments() {
    var filtered = List<dynamic>.from(_documents);
    
    // Apply search
    final query = _searchController.text.toLowerCase();
    if (query.isNotEmpty) {
      filtered = filtered.where((doc) {
        final title = (doc['title'] ?? '').toString().toLowerCase();
        final department = (doc['department'] ?? '').toString().toLowerCase();
        final status = (doc['status'] ?? '').toString().toLowerCase();
        return title.contains(query) ||
            department.contains(query) ||
            status.contains(query);
      }).toList();
    }

    // Apply status filter
    if (_filterStatus != null) {
      filtered = filtered.where((doc) => doc['status'] == _filterStatus).toList();
    }

    // Apply department filter
    if (_filterDepartment != null) {
      filtered = filtered.where((doc) => doc['department'] == _filterDepartment).toList();
    }

    // Apply sorting
    if (_sortOption != null) {
      switch (_sortOption) {
        case SortOption.newest:
          filtered.sort((a, b) {
            final aDate = a['created_at'] ?? '';
            final bDate = b['created_at'] ?? '';
            return bDate.compareTo(aDate);
          });
          break;
        case SortOption.oldest:
          filtered.sort((a, b) {
            final aDate = a['created_at'] ?? '';
            final bDate = b['created_at'] ?? '';
            return aDate.compareTo(bDate);
          });
          break;
        case SortOption.titleAsc:
          filtered.sort((a, b) {
            final aTitle = (a['title'] ?? '').toString();
            final bTitle = (b['title'] ?? '').toString();
            return aTitle.compareTo(bTitle);
          });
          break;
        case SortOption.titleDesc:
          filtered.sort((a, b) {
            final aTitle = (a['title'] ?? '').toString();
            final bTitle = (b['title'] ?? '').toString();
            return bTitle.compareTo(aTitle);
          });
          break;
        case SortOption.status:
          filtered.sort((a, b) {
            final aStatus = (a['status'] ?? '').toString();
            final bStatus = (b['status'] ?? '').toString();
            return aStatus.compareTo(bStatus);
          });
          break;
        case null:
          break;
      }
    }

    setState(() {
      _filteredDocuments = filtered;
    });
  }

  Future<void> _fetchData() async {
    setState(() {
      _loading = true;
      _error = false;
      _errorMessage = null;
    });

    try {
      final docs = await ApiService.getDocuments();
      if (docs.isNotEmpty) {
        await DatabaseHelper.instance.cacheDocuments(docs);
      }
      setState(() {
        _documents = docs;
        _loading = false;
      });
      _filterDocuments();
    } catch (e) {
      print('Network Error: $e');
      
      // Try to load from cache
      try {
        final cachedDocs = await DatabaseHelper.instance.getCachedDocuments();
        setState(() {
          _documents = cachedDocs;
          _loading = false;
        });
        _filterDocuments();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Offline Mode: Showing cached data'),
              backgroundColor: Colors.orange,
            ),
          );
        }
      } catch (cacheError) {
        setState(() {
          _loading = false;
          _error = true;
          _errorMessage = e.toString().replaceAll('Exception: ', '');
        });
      }
    }
  }

  void _toggleBulkSelect() {
    setState(() {
      _bulkSelectMode = !_bulkSelectMode;
      if (!_bulkSelectMode) {
        _selectedIds.clear();
      }
    });
  }

  void _toggleSelection(String id) {
    setState(() {
      if (_selectedIds.contains(id)) {
        _selectedIds.remove(id);
      } else {
        _selectedIds.add(id);
      }
    });
  }

  Future<void> _bulkDelete() async {
    if (_selectedIds.isEmpty) return;

    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Documents'),
        content: Text('Are you sure you want to delete ${_selectedIds.length} document(s)?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirm == true) {
      try {
        await ApiService.bulkDeleteDocuments(_selectedIds.toList());
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Documents deleted successfully'),
              backgroundColor: Colors.green,
            ),
          );
          _toggleBulkSelect();
          _fetchData();
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Failed to delete: $e')),
          );
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final pendingCount = _documents.where((d) => d['status'] == 'pending' || d['status'] == 'draft').length;
    final signedCount = _documents.where((d) => d['status'] == 'signed').length;
    final totalCount = _documents.length;

    return Scaffold(
      appBar: _bulkSelectMode
          ? BulkSelectAppBar(
              selectedCount: _selectedIds.length,
              onCancel: _toggleBulkSelect,
              onDelete: _bulkDelete,
            )
          : AppBar(
              title: const Text('Dashboard'),
              backgroundColor: const Color(0xFF2D3748),
              foregroundColor: Colors.white,
              actions: [
                IconButton(
                  icon: const Icon(Icons.document_scanner),
                  tooltip: 'Scan Document',
                  onPressed: () async {
                    final result = await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const ScanDocumentScreen(),
                      ),
                    );
                    if (result == true) {
                      _fetchData();
                    }
                  },
                ),
                IconButton(
                  icon: const Icon(Icons.search),
                  onPressed: () {
                    showSearch(
                      context: context,
                      delegate: DocumentSearchDelegate(_documents),
                    );
                  },
                ),
                IconButton(
                  icon: const Icon(Icons.filter_list),
                  onPressed: () {
                    showModalBottomSheet(
                      context: context,
                      builder: (_) => FilterBottomSheet(
                        selectedStatus: _filterStatus,
                        selectedDepartment: _filterDepartment,
                        sortOption: _sortOption,
                        departments: _departments,
                        onApply: _applyFilters,
                      ),
                    );
                  },
                ),
                IconButton(
                  icon: const Icon(Icons.select_all),
                  onPressed: _toggleBulkSelect,
                ),
                IconButton(onPressed: _fetchData, icon: const Icon(Icons.refresh)),
              ],
            ),
      body: Column(
        children: [
          const OfflineIndicator(),
          Expanded(
            child: _loading
                ? const ListSkeleton(itemCount: 5)
                : _error
                    ? ErrorRetryWidget(
                        message: _errorMessage ?? 'Failed to load documents',
                        onRetry: _fetchData,
                      )
                    : RefreshIndicator(
            onRefresh: _fetchData,
            child: ListView(
              padding: const EdgeInsets.only(top: 8),
              children: [
                SearchBarWidget(
                  controller: _searchController,
                  hintText: 'Search documents...',
                  onChanged: (_) => _filterDocuments(),
                  onClear: () => _filterDocuments(),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                // Stats Cards
                Row(
                  children: [
                    Expanded(
                      child: _StatCard(
                        title: 'Total',
                        value: '$totalCount',
                        color: Colors.blue,
                        icon: Icons.description,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _StatCard(
                        title: 'Pending',
                        value: '$pendingCount',
                        color: Colors.orange,
                        icon: Icons.pending,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _StatCard(
                        title: 'Signed',
                        value: '$signedCount',
                        color: Colors.green,
                        icon: Icons.check_circle,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                // Quick Actions
                const Text(
                  'Quick Actions',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _ActionCard(
                        icon: Icons.upload,
                        label: 'Upload',
                        color: Colors.blue,
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(builder: (_) => const UploadDocumentScreen()),
                          ).then((result) {
                            if (result == true) {
                              _fetchData();
                            }
                          });
                        },
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _ActionCard(
                        icon: Icons.work,
                        label: 'Workflows',
                        color: Colors.purple,
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(builder: (_) => const WorkflowsScreen()),
                          );
                        },
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _ActionCard(
                        icon: Icons.draw,
                        label: 'Signatures',
                        color: Colors.orange,
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(builder: (_) => const SignaturesScreen()),
                          );
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Recent Documents',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                    TextButton(
                      onPressed: () {
                        // Show all documents
                      },
                      child: const Text('View All'),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                if (_filteredDocuments.isEmpty)
                  EmptyStateWidget(
                    icon: _searchController.text.isNotEmpty || _filterStatus != null
                        ? Icons.search_off
                        : Icons.inbox,
                    title: _searchController.text.isNotEmpty || _filterStatus != null
                        ? 'No documents match your filters'
                        : 'No documents found',
                    subtitle: _searchController.text.isEmpty && _filterStatus == null
                        ? 'Upload your first document to get started'
                        : 'Try adjusting your search or filters',
                    action: _searchController.text.isNotEmpty || _filterStatus != null
                        ? OutlinedButton(
                            onPressed: () {
                              _searchController.clear();
                              setState(() {
                                _filterStatus = null;
                                _filterDepartment = null;
                                _sortOption = null;
                              });
                              _filterDocuments();
                            },
                            child: const Text('Clear Filters'),
                          )
                        : null,
                  )
                else
                  ..._filteredDocuments.map((doc) => _DocumentCard(
                        doc: doc,
                        isSelected: _bulkSelectMode && _selectedIds.contains(doc['id'].toString()),
                        onTap: _bulkSelectMode
                            ? () => _toggleSelection(doc['id'].toString())
                            : null,
                      )),
                    ],
                  ),
                ),
              ],
            ),
          ),
          ),
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String title;
  final String value;
  final Color color;
  final IconData icon;

  const _StatCard({
    required this.title,
    required this.value,
    required this.color,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
        border: Border.all(color: color.withOpacity(0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Icon(icon, color: color, size: 24),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: color.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  value,
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: color,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            title,
            style: TextStyle(
              color: Colors.grey[600],
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _ActionCard({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: color.withOpacity(0.1),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withOpacity(0.3)),
        ),
        child: Column(
          children: [
            Icon(icon, color: color, size: 32),
            const SizedBox(height: 8),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontWeight: FontWeight.w600,
                fontSize: 12,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}

class _DocumentCard extends StatelessWidget {
  final dynamic doc;
  final bool isSelected;
  final VoidCallback? onTap;

  const _DocumentCard({
    required this.doc,
    this.isSelected = false,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final status = doc['status'] ?? 'unknown';
    Color statusColor = Colors.grey;
    if (status == 'pending') statusColor = Colors.orange;
    if (status == 'signed') statusColor = Colors.green;
    if (status == 'rejected') statusColor = Colors.red;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        child: InkWell(
          onTap: onTap ??
              () async {
                final result = await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => DocumentDetailScreen(documentId: doc['id'].toString()),
                  ),
                );
                if (result == true) {
                  // Refresh handled by parent
                }
              },
        borderRadius: BorderRadius.circular(12),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                if (isSelected)
                  Padding(
                    padding: const EdgeInsets.only(right: 12),
                    child: Icon(
                      Icons.check_circle,
                      color: Colors.blue[700],
                      size: 24,
                    ),
                  ),
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: statusColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                    border: isSelected
                        ? Border.all(color: Colors.blue[700]!, width: 2)
                        : null,
                  ),
                  child: Icon(Icons.description, color: statusColor),
                ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      doc['title'] ?? 'Untitled',
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 16,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Icon(Icons.folder, size: 14, color: Colors.grey[600]),
                        const SizedBox(width: 4),
                        Text(
                          doc['department'] ?? 'General',
                          style: TextStyle(
                            color: Colors.grey[600],
                            fontSize: 12,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: statusColor.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            status.toUpperCase(),
                            style: TextStyle(
                              color: statusColor,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: Colors.grey[400]),
            ],
          ),
        ),
      ),
    );
  }
}


