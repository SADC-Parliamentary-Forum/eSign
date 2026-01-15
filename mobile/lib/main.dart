import 'package:flutter/material.dart';
import 'services/api_service.dart';
import 'services/database_helper.dart';
import 'screens/signing_screen.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SADC-eSign',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF2D3748)),
        useMaterial3: true,
      ),
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

    final success = await ApiService.login(
      _emailController.text,
      _passwordController.text,
    );

    setState(() => _isLoading = false);

    if (success && mounted) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const DashboardScreen()),
      );
    } else {
      setState(() => _errorMessage = 'Invalid credentials or connection error');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[100],
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Icon(Icons.security, size: 64, color: Color(0xFF2D3748)),
              const SizedBox(height: 24),
              const Text(
                'SADC-eSign',
                style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
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
                    style: const TextStyle(color: Colors.red),
                    textAlign: TextAlign.center,
                  ),
                ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _isLoading ? null : _login,
                style: FilledButton.styleFrom(
                  padding: const EdgeInsets.all(16),
                  backgroundColor: const Color(0xFF3182CE),
                ),
                child: _isLoading 
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Sign In'),
              ),
            ],
          ),
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
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

import 'database_helper.dart';

// ... (In _DashboardScreenState)

  Future<void> _fetchData() async {
    try {
      final docs = await ApiService.getDocuments();
      if (docs.isNotEmpty) {
        // Cache online results
        await DatabaseHelper.instance.cacheDocuments(docs);
        setState(() {
          _documents = docs;
          _loading = false;
        });
      } else {
        // If API returns empty (but no error), still valid.
        setState(() {
           _documents = [];
           _loading = false;
        });
      }
    } catch (e) {
      print('Network Error: $e');
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Offline Mode: Showing cached data')),
      );
      
      // Load from Cache
      final cachedDocs = await DatabaseHelper.instance.getCachedDocuments();
      setState(() {
        _documents = cachedDocs;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final pendingCount = _documents.where((d) => d['status'] == 'pending' || d['status'] == 'draft').length;
    final signedCount = _documents.where((d) => d['status'] == 'signed').length;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        backgroundColor: const Color(0xFF2D3748),
        foregroundColor: Colors.white,
        actions: [
          IconButton(onPressed: _fetchData, icon: const Icon(Icons.refresh)),
        ],
      ),
      body: _loading 
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: _fetchData,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Row(
                  children: [
                    _StatCard(title: 'Pending', value: '$pendingCount', color: Colors.orange),
                    const SizedBox(width: 16),
                    _StatCard(title: 'Signed', value: '$signedCount', color: Colors.green),
                  ],
                ),
                const SizedBox(height: 24),
                const Text(
                  'Recent Documents',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 16),
                if (_documents.isEmpty)
                  const Center(child: Padding(
                    padding: EdgeInsets.all(32.0),
                    child: Text('No documents found'),
                  ))
                else
                  ..._documents.map((doc) => _DocumentCard(doc: doc)),
              ],
            ),
          ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String title;
  final String value;
  final Color color;

  const _StatCard({required this.title, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(8),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 4, offset: const Offset(0, 2)),
          ],
          border: Border(left: BorderSide(color: color, width: 4)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: TextStyle(color: Colors.grey[600])),
            const SizedBox(height: 8),
            Text(value, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
          ],
        ),
      ),
    );
  }
}

class _DocumentCard extends StatelessWidget {
  final dynamic doc;

  const _DocumentCard({required this.doc});

  @override
  Widget build(BuildContext context) {
    final status = doc['status'] ?? 'unknown';
    Color statusColor = Colors.grey;
    if (status == 'pending') statusColor = Colors.orange;
    if (status == 'signed') statusColor = Colors.green;
    if (status == 'rejected') statusColor = Colors.red;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        leading: Icon(Icons.description, color: Colors.blue[700]),
        title: Text(doc['title'] ?? 'Untitled', style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text('${doc['department'] ?? 'General'} • $status'),
        trailing: Container(
          width: 12,
          height: 12,
          decoration: BoxDecoration(color: statusColor, shape: BoxShape.circle),
        ),
        onTap: () async {
          if (status == 'pending') {
            final result = await Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => SigningScreen(
                  documentId: doc['id'], 
                  documentTitle: doc['title']
                ),
              ),
            );
            
            // Refresh logic if signed
            if (result == true) {
              // We need a callback or global state to refresh. 
              // For simplicity, we assume the user will pull-to-refresh or we could pass a callback.
            }
          }
        },
      ),
    );
  }
}
