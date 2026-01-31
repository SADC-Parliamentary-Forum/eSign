import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../services/api_service.dart';
import '../services/database_helper.dart';
import '../widgets/search_bar.dart';
import '../widgets/loading_skeleton.dart';
import '../widgets/error_widget.dart';
import '../widgets/offline_indicator.dart';
import '../widgets/bulk_select_app_bar.dart';
import '../widgets/filter_bottom_sheet.dart';
import '../widgets/premium_card.dart';
import '../widgets/glass_app_bar.dart';
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
  // ... (State logic remains mostly same, repeating crucial parts for context)
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

  // ... (Keeping existing methods: _loadDepartments, _applyFilters, _filterDocuments, _fetchData, _toggleBulkSelect, _toggleSelection, _bulkDelete)
  
  Future<void> _loadDepartments() async {
    try {
      final depts = await ApiService.getDepartments();
      setState(() {
        _departments = depts.map((d) => d['name'] as String? ?? '').where((n) => n.isNotEmpty).toList();
      });
    } catch (e) {
      // Ignore
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
    // ... (Filter logic unchanged)
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

    if (_filterStatus != null) {
      filtered = filtered.where((doc) => doc['status'] == _filterStatus).toList();
    }

    if (_filterDepartment != null) {
      filtered = filtered.where((doc) => doc['department'] == _filterDepartment).toList();
    }
    
    // ... (Sorting logic)
     if (_sortOption != null) {
      switch (_sortOption) {
        case SortOption.newest:
          filtered.sort((a, b) => (b['created_at'] ?? '').compareTo(a['created_at'] ?? ''));
          break;
        case SortOption.oldest:
          filtered.sort((a, b) => (a['created_at'] ?? '').compareTo(b['created_at'] ?? ''));
          break;
        case SortOption.titleAsc:
          filtered.sort((a, b) => (a['title'] ?? '').toString().compareTo((b['title'] ?? '').toString()));
          break;
        case SortOption.titleDesc:
          filtered.sort((a, b) => (b['title'] ?? '').toString().compareTo((a['title'] ?? '').toString()));
          break;
        case SortOption.status:
          filtered.sort((a, b) => (a['status'] ?? '').toString().compareTo((b['status'] ?? '').toString()));
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
      // Offline fallback logic remains
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
    // ... (Bulk delete logic unchanged)
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
            const SnackBar(content: Text('Documents deleted successfully'), backgroundColor: Colors.green),
          );
          _toggleBulkSelect();
          _fetchData();
        }
      } catch (e) {
         if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed to delete: $e')));
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
      extendBodyBehindAppBar: true, 
      appBar: _bulkSelectMode
          ? BulkSelectAppBar(
              selectedCount: _selectedIds.length,
              onCancel: _toggleBulkSelect,
              onDelete: _bulkDelete,
            )
          : GlassAppBar( 
              title: 'Dashboard',
              actions: [
                IconButton(
                  icon: const Icon(Icons.document_scanner),
                  tooltip: 'Scan Document',
                  onPressed: () async {
                    final result = await Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const ScanDocumentScreen()),
                    );
                    if (result == true) _fetchData();
                  },
                ),
                IconButton(
                  icon: const Icon(Icons.search),
                  onPressed: () => showSearch(
                    context: context,
                    delegate: DocumentSearchDelegate(_documents),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.filter_list),
                  onPressed: () => showModalBottomSheet(
                    context: context,
                    builder: (_) => FilterBottomSheet(
                      selectedStatus: _filterStatus,
                      selectedDepartment: _filterDepartment,
                      sortOption: _sortOption,
                      departments: _departments,
                      onApply: _applyFilters,
                    ),
                  ),
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
          SizedBox(height: _bulkSelectMode ? kToolbarHeight : kToolbarHeight + 40), 
          const OfflineIndicator(),
          Expanded(
            child: _loading
                ? const ListSkeleton(itemCount: 5)
                : _error
                    ? ErrorRetryWidget(message: _errorMessage ?? 'Failed to load documents', onRetry: _fetchData)
                    : RefreshIndicator(
                        onRefresh: _fetchData,
                        child: ListView(
                          padding: const EdgeInsets.all(16),
                          children: [
                            SearchBarWidget(
                              controller: _searchController,
                              hintText: 'Search documents...',
                              onChanged: (_) => _filterDocuments(),
                              onClear: () => _filterDocuments(),
                            ).animate().fadeIn().slideY(begin: 0.2, duration: 400.ms),
                            
                            const SizedBox(height: 16),
                            
                            // Stats Cards with animation
                            Row(
                              children: [
                                Expanded(child: _StatCard(title: 'Total', value: '$totalCount', color: Colors.blue, icon: Icons.description)),
                                const SizedBox(width: 12),
                                Expanded(child: _StatCard(title: 'Pending', value: '$pendingCount', color: Colors.orange, icon: Icons.pending)),
                                const SizedBox(width: 12),
                                Expanded(child: _StatCard(title: 'Signed', value: '$signedCount', color: Colors.green, icon: Icons.check_circle)),
                              ],
                            ).animate().fadeIn().slideY(begin: 0.2, duration: 400.ms, delay: 100.ms),
                            
                            const SizedBox(height: 24),
                            
                            // Quick Actions with animation
                            const Text(
                              'Quick Actions',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                            ).animate().fadeIn(delay: 200.ms),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                Expanded(child: _ActionCard(
                                  icon: Icons.upload, label: 'Upload', color: Colors.blue, 
                                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const UploadDocumentScreen())).then((res) => res == true ? _fetchData() : null))),
                                const SizedBox(width: 12),
                                Expanded(child: _ActionCard(
                                  icon: Icons.work, label: 'Workflows', color: Colors.purple, 
                                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const WorkflowsScreen())))),
                                const SizedBox(width: 12),
                                Expanded(child: _ActionCard(
                                  icon: Icons.draw, label: 'Signatures', color: Colors.orange, 
                                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SignaturesScreen())))),
                              ],
                            ).animate().fadeIn().slideX(begin: 0.1, duration: 400.ms, delay: 200.ms),
                            
                            const SizedBox(height: 24),
                            
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Recent Documents', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)).animate().fadeIn(delay: 300.ms),
                                TextButton(onPressed: () {}, child: const Text('View All')),
                              ],
                            ),
                            const SizedBox(height: 12),
                            
                            if (_filteredDocuments.isEmpty)
                              EmptyStateWidget(
                                icon: _searchController.text.isNotEmpty || _filterStatus != null ? Icons.search_off : Icons.inbox,
                                title: _searchController.text.isNotEmpty || _filterStatus != null ? 'No documents match' : 'No documents found',
                                subtitle: 'Try uploading or adjusting filters',
                                action: _searchController.text.isNotEmpty || _filterStatus != null 
                                  ? OutlinedButton(onPressed: () { _searchController.clear(); setState(() { _filterStatus = null; _filterDepartment = null; }); _filterDocuments(); }, child: const Text('Clear Filters')) 
                                  : null,
                              ).animate().fadeIn(duration: 500.ms)
                            else
                              ListView.builder(
                                shrinkWrap: true,
                                physics: const NeverScrollableScrollPhysics(),
                                itemCount: _filteredDocuments.length,
                                itemBuilder: (context, index) {
                                  final doc = _filteredDocuments[index];
                                  return _DocumentCard(
                                    doc: doc,
                                    isSelected: _bulkSelectMode && _selectedIds.contains(doc['id'].toString()),
                                    onTap: _bulkSelectMode ? () => _toggleSelection(doc['id'].toString()) : null,
                                  ).animate().fadeIn().slideY(begin: 0.1, delay: (index * 50).ms);
                                },
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

  const _StatCard({required this.title, required this.value, required this.color, required this.icon});

  @override
  Widget build(BuildContext context) {
    return PremiumCard( 
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Icon(icon, color: color, size: 20),
              Text(value, style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: color)),
            ],
          ),
          const SizedBox(height: 4),
          Text(title, style: TextStyle(color: Colors.grey[600], fontSize: 11)),
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

  const _ActionCard({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      onTap: onTap,
      padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
      color: color.withOpacity(0.05),
      child: Column(
        children: [
          Icon(icon, color: color, size: 28),
          const SizedBox(height: 8),
          Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 12), textAlign: TextAlign.center),
        ],
      ),
    );
  }
}

class _DocumentCard extends StatelessWidget {
  final dynamic doc;
  final bool isSelected;
  final VoidCallback? onTap;

  const _DocumentCard({required this.doc, this.isSelected = false, this.onTap});

  @override
  Widget build(BuildContext context) {
    final status = doc['status'] ?? 'unknown';
    Color statusColor = Colors.grey;
    if (status == 'pending') statusColor = Colors.orange;
    if (status == 'signed') statusColor = Colors.green;
    if (status == 'rejected') statusColor = Colors.red;

    return PremiumCard(
      onTap: onTap ?? () async {
        final result = await Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => DocumentDetailScreen(documentId: doc['id'].toString())),
        );
        if (result == true) {} 
      },
      child: Row(
        children: [
           if (isSelected) Padding(padding: const EdgeInsets.only(right: 12), child: Icon(Icons.check_circle, color: Colors.blue[700], size: 24)),
           Container(
             width: 48, height: 48,
             decoration: BoxDecoration(color: statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
             child: Icon(Icons.description, color: statusColor),
           ),
           const SizedBox(width: 16),
           Expanded(
             child: Column(
               crossAxisAlignment: CrossAxisAlignment.start,
               children: [
                 Text(doc['title'] ?? 'Untitled', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15), maxLines: 1, overflow: TextOverflow.ellipsis),
                 const SizedBox(height: 4),
                 Row(
                   children: [
                     Text(doc['department'] ?? 'General', style: TextStyle(color: Colors.grey[600], fontSize: 12)),
                     const SizedBox(width: 8),
                     Container(
                       padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                       decoration: BoxDecoration(color: statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
                       child: Text(status.toUpperCase(), style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.bold)),
                     ),
                   ],
                 ),
               ],
             ),
           ),
           Icon(Icons.chevron_right, color: Colors.grey[400]),
        ],
      ),
    );
  }
}


