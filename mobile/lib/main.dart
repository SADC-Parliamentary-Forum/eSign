import 'package:flutter/material.dart';
import 'package:app_links/app_links.dart';
import 'package:mobile/config/app_config.dart';
import 'package:mobile/theme/app_design.dart';
import 'services/api_service.dart';
import 'services/database_helper.dart';
import 'screens/register_screen.dart';
import 'screens/forgot_password_screen.dart';
import 'screens/profile_screen.dart';
import 'screens/document_detail_screen.dart';
import 'screens/notifications_screen.dart';
import 'screens/templates_screen.dart';
import 'screens/signatures_screen.dart';
import 'screens/upload_document_screen.dart';
import 'screens/settings_screen.dart';
import 'screens/guest_signer_screen.dart';
import 'widgets/search_bar.dart';
import 'widgets/loading_skeleton.dart';
import 'widgets/error_widget.dart';
import 'widgets/offline_indicator.dart';
import 'widgets/bulk_select_app_bar.dart';
import 'widgets/filter_bottom_sheet.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await AppConfig.load();
  runApp(const MyApp());
}

class MyApp extends StatefulWidget {
  const MyApp({super.key});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  final GlobalKey<NavigatorState> _navigatorKey = GlobalKey<NavigatorState>();
  final AppLinks _appLinks = AppLinks();

  @override
  void initState() {
    super.initState();
    _initDeepLinks();
  }

  Future<void> _initDeepLinks() async {
    try {
      // Handle link that launched the app (cold start)
      final initialLink = await _appLinks.getInitialLink();
      if (initialLink != null) {
        _handleDeepLink(initialLink);
      }

      // Subscribe to links received while app is running (warm start)
      _appLinks.uriLinkStream.listen(
        _handleDeepLink,
        onError: (err) => debugPrint('Deep link stream error: $err'),
      );
    } catch (e) {
      debugPrint('Deep links initialization error: $e');
    }
  }

  /// Route incoming URIs to the correct screen.
  /// Handled patterns:
  ///   esign://sign/{token}      — native deep link
  ///   https://*.../sign/{token} — App Link / Universal Link
  void _handleDeepLink(Uri uri) {
    final segments = uri.pathSegments;

    // /sign/{token}
    if (segments.length >= 2 && segments[0] == 'sign') {
      final token = segments[1];
      _navigatorKey.currentState?.push(
        MaterialPageRoute(
          builder: (_) => GuestSignerScreen(token: token),
        ),
      );
      return;
    }

    debugPrint('Unhandled deep link: $uri');
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      navigatorKey: _navigatorKey,
      title: AppDesign.appName,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: ThemeMode.light,
      home: const LoginScreen(),
    );
  }
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;

  Future<void> _login() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final result = await ApiService.login(
        _emailController.text,
        _passwordController.text,
      );

      setState(() => _isLoading = false);

      if (result != null && mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const MainScreen()),
        );
      } else {
        setState(() => _errorMessage = 'Invalid credentials or connection error');
      }
    } catch (e) {
      setState(() {
        _isLoading = false;
        _errorMessage = e.toString().replaceAll('Exception: ', '');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final primary = AppDesign.primary;
    return Scaffold(
      backgroundColor: isDark ? AppDesign.backgroundDark : AppDesign.backgroundLight,
      body: Center(
        child: SingleChildScrollView(
          child: Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
              Icon(Icons.verified_user, size: 64, color: primary),
              const SizedBox(height: 24),
              Text(
                AppDesign.appName,
                style: AppDesign.displayBold.copyWith(fontSize: 28),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 4),
              Text(
                AppDesign.appTagline,
                style: AppDesign.bodySmall,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 48),
              TextField(
                controller: _emailController,
                decoration: const InputDecoration(
                  labelText: 'Email',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.email),
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _passwordController,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Password',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.lock),
                ),
              ),
              if (_errorMessage != null)
                Padding(
                  padding: const EdgeInsets.only(top: 16),
                  child: Text(
                    _errorMessage!,
                    style: TextStyle(color: AppDesign.statusDeclined),
                    textAlign: TextAlign.center,
                  ),
                ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _isLoading ? null : _login,
                style: FilledButton.styleFrom(
                  padding: const EdgeInsets.all(16),
                  backgroundColor: AppDesign.primary,
                ),
                child: _isLoading 
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Sign In'),
              ),
              const SizedBox(height: 16),
              TextButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const ForgotPasswordScreen()),
                  );
                },
                child: const Text('Forgot Password?'),
              ),
              const SizedBox(height: 8),
              TextButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const RegisterScreen()),
                  );
                },
                child: const Text('Create Account'),
              ),
            ],
          ),
        ),
      ),
      ),
    );
  }
}

class MainScreen extends StatefulWidget {
  const MainScreen({super.key});

  @override
  State<MainScreen> createState() => _MainScreenState();
}

class _MainScreenState extends State<MainScreen> {
  int _currentIndex = 0;

  List<Widget> _buildScreens() => [
    const DashboardScreen(),
    const TemplatesScreen(),
    const SignaturesScreen(),
    SettingsScreen(
      onLogout: () {
        if (mounted) {
          Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute(builder: (_) => const LoginScreen()),
            (route) => false,
          );
        }
      },
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _buildScreens()[_currentIndex],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (index) => setState(() => _currentIndex = index),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), label: 'Dashboard'),
          NavigationDestination(icon: Icon(Icons.description_outlined), label: 'Templates'),
          NavigationDestination(icon: Icon(Icons.draw_outlined), label: 'Signatures'),
          NavigationDestination(icon: Icon(Icons.settings_outlined), label: 'Settings'),
        ],
      ),
      drawer: Drawer(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            DrawerHeader(
              decoration: const BoxDecoration(color: AppDesign.primary),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  const Icon(Icons.verified_user, size: 48, color: Colors.white),
                  const SizedBox(height: 8),
                  Text(
                    AppDesign.appName,
                    style: AppDesign.titleBold.copyWith(color: Colors.white, fontSize: 22),
                  ),
                  Text(
                    AppDesign.appTagline,
                    style: AppDesign.bodySmall.copyWith(color: Colors.white70),
                  ),
                ],
              ),
            ),
            ListTile(
              leading: const Icon(Icons.person),
              title: const Text('Profile'),
              onTap: () {
                Navigator.pop(context);
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const ProfileScreen()),
                );
              },
            ),
            const Divider(),
            ListTile(
              leading: Icon(Icons.logout, color: AppDesign.statusDeclined),
              title: Text('Logout', style: TextStyle(color: AppDesign.statusDeclined)),
              onTap: () async {
                await ApiService.logout();
                if (mounted) {
                  Navigator.of(context).pushAndRemoveUntil(
                    MaterialPageRoute(builder: (_) => const LoginScreen()),
                    (route) => false,
                  );
                }
              },
            ),
          ],
        ),
      ),
    );
  }
}

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
  int _unreadNotificationCount = 0;

  @override
  void initState() {
    super.initState();
    _searchController.addListener(_filterDocuments);
    _fetchData();
    _loadDepartments();
    _loadUnreadCount();
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

  Future<void> _loadUnreadCount() async {
    try {
      final notifications = await ApiService.getNotifications();
      final unread = (notifications).where((n) => n['read_at'] == null).length;
      if (mounted) setState(() => _unreadNotificationCount = unread);
    } catch (e) {
      // Ignore — badge count is optional
    }
  }

  void _showAllDocuments() {
    _searchController.clear();
    setState(() {
      _filterStatus = null;
      _filterDepartment = null;
      _sortOption = null;
    });
    _filterDocuments();
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
      switch (_sortOption!) {
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
              backgroundColor: AppDesign.statusPending,
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
            style: FilledButton.styleFrom(backgroundColor: AppDesign.statusDeclined),
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
              backgroundColor: AppDesign.statusCompleted,
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
    final statusMap = _documents.fold<Map<String, int>>(
      {'PENDING': 0, 'COMPLETED': 0, 'DRAFT': 0, 'IN_PROGRESS': 0, 'FAILED': 0, 'VOIDED': 0, 'DECLINED': 0},
      (m, d) {
        final s = ((d['status'] ?? '') as String).toUpperCase();
        if (m.containsKey(s)) {
          m[s] = m[s]! + 1;
        } else {
          m['PENDING'] = (m['PENDING'] ?? 0) + 1;
        }
        return m;
      },
    );
    final pendingCount = (statusMap['PENDING'] ?? 0) + (statusMap['IN_PROGRESS'] ?? 0);
    final completedCount = statusMap['COMPLETED'] ?? 0;
    final draftsCount = statusMap['DRAFT'] ?? 0;
    final declinedCount = (statusMap['DECLINED'] ?? 0) + (statusMap['VOIDED'] ?? 0);

    return Scaffold(
      appBar: _bulkSelectMode
          ? BulkSelectAppBar(
              selectedCount: _selectedIds.length,
              onCancel: _toggleBulkSelect,
              onDelete: _bulkDelete,
            )
          : AppBar(
              titleSpacing: 0,
              leading: IconButton(
                icon: const Icon(Icons.menu),
                onPressed: () => Scaffold.of(context).openDrawer(),
              ),
              title: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: AppDesign.primary.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Icon(Icons.verified_user, color: AppDesign.primary, size: 24),
                  ),
                  const SizedBox(width: 12),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(AppDesign.appName, style: AppDesign.titleBold),
                      Text(AppDesign.appTagline, style: AppDesign.caption.copyWith(fontSize: 10)),
                    ],
                  ),
                ],
              ),
              actions: [
                IconButton(
                  icon: Badge(
                    isLabelVisible: _unreadNotificationCount > 0,
                    label: Text('$_unreadNotificationCount'),
                    child: const Icon(Icons.notifications_outlined),
                  ),
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const NotificationsScreen()),
                    ).then((_) => _loadUnreadCount());
                  },
                ),
                IconButton(
                  icon: const CircleAvatar(radius: 18, child: Icon(Icons.person)),
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const ProfileScreen()),
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
                  padding: const EdgeInsets.symmetric(horizontal: AppDesign.spacingMd),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                // Welcome per Design
                Text(
                  'Welcome back',
                  style: AppDesign.displayBold.copyWith(fontSize: 22),
                ),
                const SizedBox(height: 4),
                Text(
                  'Here is the latest update on your documents.',
                  style: AppDesign.bodySmall,
                ),
                const SizedBox(height: AppDesign.spacingLg),
                // Stats grid per Design/sender_dashboard_overview_1 (2x2: Pending, Completed, Drafts, Declined)
                GridView.count(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  crossAxisCount: 2,
                  mainAxisSpacing: AppDesign.spacingMd,
                  crossAxisSpacing: AppDesign.spacingMd,
                  childAspectRatio: 1.1,
                  children: [
                    _StatCard(
                      title: 'Pending',
                      value: '$pendingCount',
                      color: AppDesign.statusPending,
                      icon: Icons.pending_actions,
                    ),
                    _StatCard(
                      title: 'Completed',
                      value: '$completedCount',
                      color: AppDesign.statusCompleted,
                      icon: Icons.check_circle,
                    ),
                    _StatCard(
                      title: 'Drafts',
                      value: '$draftsCount',
                      color: AppDesign.statusDraft,
                      icon: Icons.edit_document,
                    ),
                    _StatCard(
                      title: 'Declined',
                      value: '$declinedCount',
                      color: AppDesign.statusDeclined,
                      icon: Icons.cancel,
                    ),
                  ],
                ),
                const SizedBox(height: AppDesign.spacingLg),
                // Primary CTA per Design: "Upload New Document"
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const UploadDocumentScreen()),
                      ).then((result) {
                        if (result == true) _fetchData();
                      });
                    },
                    icon: const Icon(Icons.add, size: 22),
                    label: const Text('Upload New Document'),
                    style: FilledButton.styleFrom(
                      backgroundColor: AppDesign.primary,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(AppDesign.radiusLg),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: AppDesign.spacingLg),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Recent Documents',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                    TextButton(
                      onPressed: _showAllDocuments,
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
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final surface = isDark ? AppDesign.surfaceDark : AppDesign.surfaceLight;
    final borderColor = isDark ? AppDesign.borderDark : AppDesign.borderLight;
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: surface,
        borderRadius: BorderRadius.circular(AppDesign.radiusLg),
        border: Border.all(color: borderColor),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned(
            right: 0,
            top: 0,
            child: Opacity(
              opacity: 0.15,
              child: Icon(icon, color: color, size: 48),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                title,
                style: AppDesign.bodySmall.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                value,
                style: AppDesign.displayBold.copyWith(
                  fontSize: 28,
                  color: color,
                ),
              ),
            ],
          ),
        ],
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
    final statusStr = (status as String?)?.toUpperCase() ?? '';
    Color statusColor = AppDesign.statusDraft;
    if (statusStr == 'PENDING' || statusStr == 'IN_PROGRESS') statusColor = AppDesign.statusPending;
    if (statusStr == 'COMPLETED' || statusStr == 'SIGNED') statusColor = AppDesign.statusCompleted;
    if (statusStr == 'DECLINED' || statusStr == 'REJECTED' || statusStr == 'FAILED') statusColor = AppDesign.statusDeclined;

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
                      color: AppDesign.primary,
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
                        ? Border.all(color: AppDesign.primary, width: 2)
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
                        Icon(Icons.folder, size: 14, color: Theme.of(context).colorScheme.onSurfaceVariant),
                        const SizedBox(width: 4),
                        Text(
                          doc['department'] ?? 'General',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.onSurfaceVariant,
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
                            (status ?? '').toString().toUpperCase(),
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
              Icon(Icons.chevron_right, color: Theme.of(context).colorScheme.onSurfaceVariant),
            ],
          ),
        ),
      ),
    );
  }
}
